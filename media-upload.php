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

if (!isLoggedIn() || !isAdmin()) { http_response_code(403); echo json_encode(['error' => 'Admin access required']); exit; }

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

    $s = $sessions->create((int)($_SESSION['user_id'] ?? $_SESSION['admin_id']), $kind, $filename, $mime, $size);
    out(['upload_id' => $s['id'], 'token' => $s['token'], 'chunk_size' => UploadSession::CHUNK_SIZE, 'expires_in' => UploadSession::TTL_SECONDS]);
}

/* ------------------------------------------------ chunk (raw body) */
if ($action === 'chunk') {
    $id = (int)($_SERVER['HTTP_X_UPLOAD_ID'] ?? 0);
    $token = (string)($_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '');
    $offset = (int)($_SERVER['HTTP_X_CHUNK_OFFSET'] ?? -1);
    $sess = $sessions->find($id, $token);
    if (!$sess) out(['error' => 'Invalid or expired upload session'], 403);
    if ($sess['user_id'] != (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'])) out(['error' => 'Session belongs to another user'], 403);
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

/* ------------------------------------------------ status */
if ($action === 'status') {
    $d = inJson();
    $sess = $sessions->find((int)($d['upload_id'] ?? 0), (string)($d['token'] ?? ''));
    if (!$sess) out(['error' => 'Invalid or expired upload session'], 403);
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
    if ((int)$sess['received_bytes'] !== (int)$sess['size_bytes']) {
        out(['error' => 'Upload incomplete', 'received' => (int)$sess['received_bytes'], 'size' => (int)$sess['size_bytes']], 409);
    }

    $part = UploadSession::partPath($sess['id']);
    if (!is_file($part) || filesize($part) !== (int)$sess['size_bytes']) out(['error' => 'Upload buffer missing or size mismatch'], 409);

    // Server-side verification: real MIME + decodable video + sha256.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($part);
    if (strpos($mime, 'video/') !== 0) { @unlink($part); $sessions->setStatus($sess['id'], 'aborted'); out(['error' => 'File is not a valid video (detected: ' . $mime . ')'], 415); }

    $ffprobe = null;
    foreach ([__DIR__ . '/bin/ffprobe.exe', 'ffprobe'] as $c) { if (is_file($c) || $c === 'ffprobe') { $ffprobe = $c; break; } }
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
    $dest = __DIR__ . '/storage/' . $stored;
    if (!@rename($part, $dest)) {
        if (!@copy($part, $dest)) { @unlink($part); out(['error' => 'Could not finalize upload'], 500); }
        @unlink($part);
    }

    $mediaId = (new MediaAsset())->create([
        'uploaded_by' => (int)($_SESSION['user_id'] ?? $_SESSION['admin_id']),
        'original_filename' => $sess['filename'],
        'stored_filename' => basename($dest),
        'storage_path' => 'storage/' . $stored,
        'media_type' => 'video',
        'mime_type' => $mime,
        'file_size' => (int)$sess['size_bytes'],
        'duration_seconds' => isset($duration) ? (int)round($duration) : null,
        'alt_text' => pathinfo($sess['filename'], PATHINFO_FILENAME),
    ]);
    if (!$mediaId) { @unlink($dest); out(['error' => 'Upload verified but media registration failed'], 500); }

    $sessions->setStatus($sess['id'], 'complete', ['sha256' => $sha, 'media_id' => $mediaId]);
    // Non-destructive 720p rendition (original preserved).
    RenditionBuilder::enqueue((int)$mediaId, $dest);
    out(['media_id' => (int)$mediaId, 'sha256' => $sha, 'size' => (int)$sess['size_bytes'], 'mime' => $mime]);
}

/* ------------------------------------------------ abort */
if ($action === 'abort') {
    $d = inJson();
    $sess = $sessions->find((int)($d['upload_id'] ?? 0), (string)($d['token'] ?? ''));
    if ($sess) { $sessions->setStatus($sess['id'], 'aborted'); @unlink(UploadSession::partPath($sess['id'])); }
    out(['ok' => true]);
}

out(['error' => 'Unknown action'], 400);
