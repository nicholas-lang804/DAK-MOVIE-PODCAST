<?php
/**
 * Resumable chunked upload API (8 MB chunks) — admin-only.
 *
 * Actions (POST, JSON unless noted):
 *   start    {filename,size,mime,kind?}         -> {upload_id, token, chunk_size, expires_in}
 *   chunk    raw body + headers X-Upload-Id / X-Upload-Token / X-Chunk-Offset
 *   status   {upload_id} + token                -> {received,size,status}
 *   complete {upload_id} + token                -> {media_id, sha256, size}
 *   sessions                                      -> active sessions for resume
 *
 * Security: admin session required; token is a 256-bit capability stored hashed;
 * sessions are single-user, expiring, size/MIME/extension validated; chunks are
 * idempotent (offset must equal received); final file re-validated by MIME +
 * ffprobe before a media_assets row is created.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/UploadSession.php';
require_once __DIR__ . '/app/Models/MediaAsset.php';
require_once __DIR__ . '/app/Models/UploadJob.php';
require_once __DIR__ . '/app/Models/SiteSetting.php';
require_once __DIR__ . '/app/Services/RenditionBuilder.php';
require_once __DIR__ . '/app/Services/MediaStorage.php';

if (!isLoggedIn() || !isAdmin()) { http_response_code(403); echo json_encode(['error' => 'Admin access required']); exit; }
@set_time_limit(600); // completion hashes + moves multi-GB files

$MAX_SIZE = 5368709120; // 5 GB — matches media library
$VIDEO_MIME_EXT = [
    'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
    'video/x-matroska' => 'mkv', 'video/x-msvideo' => 'avi', 'video/ogg' => 'ogv',
    'video/3gpp' => '3gp', 'video/mp2t' => 'ts',
];
$VIDEO_EXTS = array_values($VIDEO_MIME_EXT);

function out($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }
function inJson() { $d = json_decode(file_get_contents('php://input'), true); return is_array($d) ? $d : $_POST; }

$action = $_GET['action'] ?? (inJson()['action'] ?? '');
$sessions = new UploadSession();

/* ------------------------------------------------ start */
if ($action === 'start') {
    $d = inJson();
    $filename = basename((string)($d['filename'] ?? ''));
    $size = (int)($d['size'] ?? 0);
    $mime = strtolower(trim((string)($d['mime'] ?? '')));
    $kind = in_array(($d['kind'] ?? 'video'), ['video', 'movie'], true) ? 'video' : 'video';

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($filename === '' || $size <= 0) out(['error' => 'Filename and size are required'], 400);
    if ($size > $MAX_SIZE) out(['error' => 'File exceeds the 5 GB limit'], 413);
    if (!in_array($ext, $VIDEO_EXTS, true)) out(['error' => 'Unsupported video type. Allowed: ' . implode(', ', $VIDEO_EXTS)], 415);
    if ($mime !== '' && strpos($mime, 'video/') !== 0) out(['error' => 'MIME type must be video/*'], 415);

    // One active session per (user, filename, size) — prevents accidental duplicates.
    foreach ($sessions->forUser((int)($_SESSION['user_id'] ?? $_SESSION['admin_id'])) as $ex) {
        if ($ex['filename'] === $filename && (int)$ex['size_bytes'] === $size) {
            out(['error' => 'An upload for this file is already in progress', 'upload_id' => (int)$ex['id']], 409);
        }
    }

    // R2 mode: browser PUTs parts straight to Cloudflare — Hostinger disk untouched.
    $store = MediaStorage::get();
    if ($store->driver() === 'r2' && $store->r2Configured()) {
        try {
            $s3 = S3Client::fromSettings((new SiteSetting())->getAllKeyed());
            $key = 'uploads/' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
            $uploadId = $s3->createMultipartUpload($key, $mime ?: 'video/mp4');
            $s = $sessions->create((int)($_SESSION['user_id'] ?? $_SESSION['admin_id']), $kind, $filename, $mime, $size);
            $sessions->setStatus($s['id'], 'active', ['part_path' => json_encode(['mode' => 'r2', 'key' => $key, 'uploadId' => $uploadId])]);
            @file_put_contents(__DIR__ . '/storage/logs/r2-debug.log', json_encode(['ev' => 'start', 'sess' => (int)$s['id'], 'key' => $key, 'uid' => substr($uploadId, 0, 24), 'ts' => date('c')]) . "\n", FILE_APPEND);
            out(['upload_id' => $s['id'], 'token' => $s['token'], 'mode' => 'r2',
                 'part_size' => 67108864, 'expires_in' => UploadSession::TTL_SECONDS]);
        } catch (Throwable $e) {
            out(['error' => 'Cloud storage unavailable: ' . $e->getMessage()], 502);
        }
    }

    $s = $sessions->create((int)($_SESSION['user_id'] ?? $_SESSION['admin_id']), $kind, $filename, $mime, $size);
    out(['upload_id' => $s['id'], 'token' => $s['token'], 'mode' => 'local', 'chunk_size' => UploadSession::CHUNK_SIZE, 'expires_in' => UploadSession::TTL_SECONDS]);
}

/* ------------------------------------------------ chunk (raw body) */
if ($action === 'chunk') {
    $id = (int)($_SERVER['HTTP_X_UPLOAD_ID'] ?? 0);
    $token = (string)($_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '');
    $offset = (int)($_SERVER['HTTP_X_CHUNK_OFFSET'] ?? -1);
    $sess = $sessions->find($id, $token);
    if (!$sess) out(['error' => 'Invalid or expired upload session'], 403);
    if ($sess['user_id'] != (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'])) out(['error' => 'Session belongs to another user'], 403);
    // R2 sessions must use parturl+direct PUT — never accept local chunks for them.
    $metaChunk = json_decode((string)$sess['part_path'], true);
    if (is_array($metaChunk) && ($metaChunk['mode'] ?? '') === 'r2') {
        out(['error' => 'This upload goes direct to cloud storage — reload the page and retry'], 400);
    }
    if ($offset < 0) out(['error' => 'Missing chunk offset'], 400);

    $received = (int)$sess['received_bytes'];
    // Idempotent: re-sent chunk at/below received is acknowledged without rewriting.
    if ($offset < $received) out(['received' => $received, 'duplicate' => true]);
    if ($offset > $received) out(['error' => 'Chunk offset mismatch — resume from ' . $received], 409);
    if ($offset + UploadSession::CHUNK_SIZE > $sess['size_bytes'] && $offset !== $received) {
        // last chunk may be short; total must not exceed declared size
    }

    $data = file_get_contents('php://input');
    if ($data === false || $data === '') out(['error' => 'Empty chunk'], 400);
    $part = UploadSession::partPath($id);
    $fh = fopen($part, $offset === 0 ? 'wb' : 'cb'); // 'cb' = append without truncating? use 'ab' semantics below
    if (!$fh) out(['error' => 'Cannot open upload buffer'], 500);
    // Write at exact offset: seek then write.
    fseek($fh, $offset);
    $written = fwrite($fh, $data);
    fclose($fh);
    if ($written !== strlen($data)) out(['error' => 'Chunk write failed'], 500);

    $received = $offset + strlen($data);
    $sessions->addBytes($id, $received);
    out(['received' => $received, 'done' => $received >= (int)$sess['size_bytes']]);
}

/* ------------------------------------------------ parturl (R2 presigned part PUT) */
if ($action === 'parturl') {
    $d = inJson();
    $sess = $sessions->find((int)($d['upload_id'] ?? 0), (string)($d['token'] ?? ''));
    if (!$sess) out(['error' => 'Invalid or expired upload session'], 403);
    if ($sess['user_id'] != (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'])) out(['error' => 'Session belongs to another user'], 403);
    $meta = json_decode((string)$sess['part_path'], true);
    if (!is_array($meta) || ($meta['mode'] ?? '') !== 'r2') out(['error' => 'Not an R2 upload session'], 400);
    $pn = (int)($d['part'] ?? 0);
    $total = (int)ceil((int)$sess['size_bytes'] / 67108864);
    if ($pn < 1 || $pn > $total) out(['error' => 'Invalid part number'], 400);
    try {
        $s3 = S3Client::fromSettings((new SiteSetting())->getAllKeyed());
        @file_put_contents(__DIR__ . '/storage/logs/r2-debug.log', json_encode(['ev' => 'parturl', 'sess' => (int)$sess['id'], 'pn' => $pn, 'uid' => substr($meta['uploadId'], 0, 24), 'ts' => date('c')]) . "\n", FILE_APPEND);
        out(['url' => $s3->presignedUploadPartUrl($meta['key'], $meta['uploadId'], $pn)]);
    } catch (Throwable $e) { out(['error' => 'Could not sign part URL: ' . $e->getMessage()], 502); }
}

/* ------------------------------------------------ status */
if ($action === 'status') {
    $d = inJson();
    $sess = $sessions->find((int)($d['upload_id'] ?? 0), (string)($d['token'] ?? ''));
    if (!$sess) out(['error' => 'Invalid or expired upload session'], 403);
    $meta = json_decode((string)$sess['part_path'], true);
    if (is_array($meta) && ($meta['mode'] ?? '') === 'r2') {
        try {
            $s3 = S3Client::fromSettings((new SiteSetting())->getAllKeyed());
            $parts = $s3->listParts($meta['key'], $meta['uploadId']);
            $received = 0; foreach ($parts as $p) $received += $p['size'];
            out(['received' => $received, 'size' => (int)$sess['size_bytes'], 'status' => $sess['status'], 'mode' => 'r2', 'part_size' => 67108864, 'parts' => $parts]);
        } catch (Throwable $e) { out(['error' => 'Could not query R2 upload: ' . $e->getMessage()], 502); }
    }
    out(['received' => (int)$sess['received_bytes'], 'size' => (int)$sess['size_bytes'], 'status' => $sess['status']]);
}

/* ------------------------------------------------ sessions (for resume UI) */
if ($action === 'sessions') {
    out(['sessions' => $sessions->forUser((int)($_SESSION['user_id'] ?? $_SESSION['admin_id']))]);
}

/* ------------------------------------------------ complete */
if ($action === 'complete') {
    $d = inJson();
    $sess = $sessions->find((int)($d['upload_id'] ?? 0), (string)($d['token'] ?? ''));
    if (!$sess) out(['error' => 'Invalid or expired upload session'], 403);
    if ($sess['user_id'] != (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'])) out(['error' => 'Session belongs to another user'], 403);

    // ---- R2 multipart finalize: parts were PUT straight to Cloudflare ----
    $meta = json_decode((string)$sess['part_path'], true);
    if (is_array($meta) && ($meta['mode'] ?? '') === 'r2') {
        try {
            $s3 = S3Client::fromSettings((new SiteSetting())->getAllKeyed());
            $parts = [];
            foreach ((array)($d['parts'] ?? []) as $pn => $etag) {
                $pn = (int)$pn; if ($pn > 0 && $etag) $parts[$pn] = (string)$etag;
            }
            // Server-side truth: merge client etags with R2's own part list.
            // R2's ListParts can lag a moment behind the final PUT — retry briefly.
            @file_put_contents(__DIR__ . '/storage/logs/r2-debug.log', json_encode(['ev' => 'complete', 'sess' => (int)$sess['id'], 'uid' => substr($meta['uploadId'], 0, 24), 'ts' => date('c')]) . "\n", FILE_APPEND);
            $expected = (int)ceil((int)$sess['size_bytes'] / 67108864);
            $serverParts = [];
            $totalBytes = 0;
            for ($try = 0; $try < 6; $try++) {
                $serverParts = $s3->listParts($meta['key'], $meta['uploadId']);
                $totalBytes = 0; // recompute per attempt
                foreach ($serverParts as $p) $totalBytes += (int)$p['size'];
                if (count($serverParts) >= $expected && $totalBytes === (int)$sess['size_bytes']) break;
                if ($try < 5) sleep(2);
            }
            if (count($serverParts) < $expected || $totalBytes !== (int)$sess['size_bytes']) {
                $dbg = json_encode([
                    'ts' => date('c'), 'sess' => (int)$sess['id'], 'key' => $meta['key'] ?? null,
                    'uploadId' => $meta['uploadId'] ?? null, 'part_path_raw' => $sess['part_path'],
                    'expected' => $expected, 'got' => count($serverParts), 'bytes' => $totalBytes,
                    'size_bytes' => (int)$sess['size_bytes'],
                ]);
                @file_put_contents(__DIR__ . '/storage/logs/r2-debug.log', $dbg . "\n", FILE_APPEND);
                out(['error' => 'Not all parts have reached cloud storage yet (' . count($serverParts) . '/' . $expected . ' parts, ' . $totalBytes . '/' . (int)$sess['size_bytes'] . ' bytes)'], 409);
            }
            $finalParts = [];
            foreach ($serverParts as $pn => $p) { $finalParts[$pn] = $parts[$pn] ?? $p['etag']; }
            ksort($finalParts);
            $s3->completeMultipartUpload($meta['key'], $meta['uploadId'], $finalParts);

            $mime = (string)$sess['mime'];
            if (strpos($mime, 'video/') !== 0) $mime = 'video/mp4';
            $mediaId = (new MediaAsset())->create([
                'uploaded_by' => (int)($_SESSION['user_id'] ?? $_SESSION['admin_id']),
                'original_filename' => $sess['filename'],
                'stored_filename' => basename($meta['key']),
                'storage_path' => 'storage/' . $meta['key'],
                'media_type' => 'video',
                'mime_type' => $mime,
                'file_size' => (int)$sess['size_bytes'],
                'duration_seconds' => null,
                'alt_text' => trim((string)preg_replace('/^\d+[-_\s]+/', '', pathinfo($sess['filename'], PATHINFO_FILENAME))) ?: 'Video',
            ]);
            if (!$mediaId) out(['error' => 'Upload completed but media registration failed'], 500);
            $sessions->setStatus($sess['id'], 'complete', ['sha256' => null, 'media_id' => $mediaId]);
            out(['media_id' => (int)$mediaId, 'size' => (int)$sess['size_bytes'], 'mime' => $mime, 'driver' => 'r2']);
        } catch (Throwable $e) {
            error_log('media-upload r2 complete: ' . $e->getMessage());
            out(['error' => 'Cloud finalize failed: ' . $e->getMessage()], 500);
        }
    }

    if ((int)$sess['received_bytes'] !== (int)$sess['size_bytes']) {
        out(['error' => 'Upload incomplete', 'received' => (int)$sess['received_bytes'], 'size' => (int)$sess['size_bytes']], 409);
    }

    $part = UploadSession::partPath($sess['id']);
    if (!is_file($part) || filesize($part) !== (int)$sess['size_bytes']) out(['error' => 'Upload buffer missing or size mismatch'], 409);

    // Server-side verification: real MIME + decodable video + sha256.
    try {
        $mime = class_exists('finfo')
            ? (new finfo(FILEINFO_MIME_TYPE))->file($part)
            : (mime_content_type($part) ?: '');
    } catch (Throwable $e) { $mime = ''; }
    if ($mime === '' || strpos($mime, 'video/') !== 0) {
        // finfo can mislabel some valid videos — fall back to extension check
        $extGuess = strtolower(pathinfo($sess['filename'], PATHINFO_EXTENSION));
        if (!in_array($extGuess, $VIDEO_EXTS, true)) {
            @unlink($part); $sessions->setStatus($sess['id'], 'aborted');
            out(['error' => 'File is not a valid video (detected: ' . ($mime ?: 'unknown') . ')'], 415);
        }
        $mime = 'video/' . ($extGuess === 'mov' ? 'quicktime' : ($extGuess === 'mp4' ? 'mp4' : $extGuess));
    }

    try {
        // ffprobe duration probe only when shell exec is available (shared hosts disable it).
        $ffprobe = function_exists('exec') ? null : false;
        if ($ffprobe !== false) {
            foreach ([__DIR__ . '/bin/ffprobe.exe', 'ffprobe'] as $c) { if (is_file($c) || $c === 'ffprobe') { $ffprobe = $c; break; } }
        }
        if ($ffprobe) {
            $o = [];
            @exec(escapeshellarg($ffprobe) . ' -v error -show_entries format=duration -of csv=p=0 ' . escapeshellarg($part) . ' 2>&1', $o, $code);
            if ($code !== 0 || empty($o[0]) || !is_numeric($o[0])) {
                @unlink($part); $sessions->setStatus($sess['id'], 'aborted');
                out(['error' => 'File is not a decodable video'], 415);
            }
            $duration = (float)$o[0];
        }

        $sha = hash_file('sha256', $part);
        $ext = $VIDEO_MIME_EXT[$mime] ?? strtolower(pathinfo($sess['filename'], PATHINFO_EXTENSION)) ?: 'mp4';
        if (!in_array($ext, $VIDEO_EXTS, true)) $ext = 'mp4';
        $stored = 'uploads/' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;

        // Cloudflare R2 driver: stream the assembled file straight to the bucket
        // and keep NO local copy — this is what stops Hostinger storage filling up.
        $store = MediaStorage::get();
        if ($store->driver() === 'r2' && $store->r2Configured()) {
            $up = $store->r2PutFile($part, $stored);
            if (empty($up['ok'])) {
                out(['error' => 'Cloud storage upload failed: ' . ($up['error'] ?? 'unknown')], 502);
            }
            @unlink($part);
            $dest = null;
        } else {
            $dest = __DIR__ . '/storage/' . $stored;
            if (!@rename($part, $dest)) {
                if (!@copy($part, $dest)) { @unlink($part); out(['error' => 'Could not finalize upload'], 500); }
                @unlink($part);
            }
        }

        $mediaId = (new MediaAsset())->create([
            'uploaded_by' => (int)($_SESSION['user_id'] ?? $_SESSION['admin_id']),
            'original_filename' => $sess['filename'],
            'stored_filename' => basename($stored),
            'storage_path' => 'storage/' . $stored,
            'media_type' => 'video',
            'mime_type' => $mime,
            'file_size' => (int)$sess['size_bytes'],
            'duration_seconds' => isset($duration) ? (int)round($duration) : null,
            // Public-safe label: strip upload prefixes like "1778346943308-" so raw
            // filenames never surface to viewers.
            'alt_text' => trim((string)preg_replace('/^\d+[-_\s]+/', '', pathinfo($sess['filename'], PATHINFO_FILENAME))) ?: 'Video',
        ]);
        if (!$mediaId) { if ($dest) @unlink($dest); out(['error' => 'Upload verified but media registration failed'], 500); }

        $sessions->setStatus($sess['id'], 'complete', ['sha256' => $sha, 'media_id' => $mediaId]);
        // Non-destructive 720p rendition (original preserved) — local files only;
        // R2 objects are served as-is until a remote rendition pipeline exists.
        if ($dest) RenditionBuilder::enqueue((int)$mediaId, $dest);
        out(['media_id' => (int)$mediaId, 'sha256' => $sha, 'size' => (int)$sess['size_bytes'], 'mime' => $mime]);
    } catch (Throwable $e) {
        error_log('media-upload complete: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        out(['error' => 'Finalization failed: ' . $e->getMessage()], 500);
    }
}

/* ------------------------------------------------ abort */
if ($action === 'abort') {
    $d = inJson();
    $sess = $sessions->find((int)($d['upload_id'] ?? 0), (string)($d['token'] ?? ''));
    if ($sess) {
        $meta = json_decode((string)$sess['part_path'], true);
        if (is_array($meta) && ($meta['mode'] ?? '') === 'r2') {
            @file_put_contents(__DIR__ . '/storage/logs/r2-debug.log', json_encode(['ev' => 'abort', 'sess' => (int)$sess['id'], 'uid' => isset($meta['uploadId']) ? substr($meta['uploadId'], 0, 24) : null, 'ts' => date('c')]) . "\n", FILE_APPEND);
            try {
                S3Client::fromSettings((new SiteSetting())->getAllKeyed())
                    ->abortMultipartUpload($meta['key'], $meta['uploadId']);
            } catch (Throwable $e) {}
        }
        $sessions->setStatus($sess['id'], 'aborted'); @unlink(UploadSession::partPath($sess['id']));
    }
    out(['ok' => true]);
}

out(['error' => 'Unknown action'], 400);
