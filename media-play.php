<?php
/**
 * Protected media playback — unified endpoint for movies, episodes and media-library videos.
 *
 *   media-play.php?movie_id=N[&q=720p|original]   movie rules (paid => verified access)
 *   media-play.php?media_id=N[&q=720p|original]   media_assets rules (linked paid movie => 404 unless movie_id used)
 *   media-play.php?hls=N&tok=...&seg=...          HLS playlist/segment (future; token-signed)
 *
 * Rules preserved from video-stream.php: admins bypass; paid movies require
 * completed payment; direct storage URLs stay blocked by .htaccess.
 * Prefers the ready 720p rendition; falls back to the original. ?q=original forces it.
 * Remote driver: issues a short-lived signed URL redirect (Phase 3 prep; driver stays local).
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/Movie.php';
require_once __DIR__ . '/app/Models/MediaAsset.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Models/UploadJob.php';
require_once __DIR__ . '/app/Helpers/MediaStream.php';
require_once __DIR__ . '/app/Services/MediaStorage.php';
require_once __DIR__ . '/app/Services/RemoteMediaClient.php';
require_once __DIR__ . '/app/Models/SiteSetting.php';
require_once __DIR__ . '/app/Helpers/StreamToken.php';

$isAdmin = isAdmin();
$pdo = getDB();
$quality = ($_GET['q'] ?? '') === 'original' ? 'original' : 'auto';

/* ------------------------------------------------ HLS (future-proofed, token-gated) */
if (isset($_GET['hls'])) {
    // Token = HMAC(secret, media_id|exp|q). Segments re-validate on every request.
    $mid = (int)$_GET['hls'];
    $tok = (string)($_GET['tok'] ?? '');
    $exp = (int)($_GET['exp'] ?? 0);
    $q = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['q'] ?? '720p'));
    $seg = isset($_GET['seg']) ? basename((string)$_GET['seg']) : null;
    $settings = new SiteSetting();
    $secret = $settings->get('media_signing_secret');
    if (!$secret) { // first use: generate a dedicated signing secret (never reuse payment keys)
        $secret = bin2hex(random_bytes(32));
        try { $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('media_signing_secret', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$secret]); } catch (Throwable $e) {}
    }
    $expect = hash_hmac('sha256', $mid . '|' . $exp . '|' . $q, $secret);
    if ($exp < time() || !hash_equals($expect, $tok)) { http_response_code(403); exit('Expired or invalid playback token.'); }
    // Defense in depth: re-check access for the movie that owns this media id.
    $owner = $pdo->prepare("SELECT id, status, access_type FROM movies WHERE video_media_id = ? LIMIT 1");
    $owner->execute([$mid]);
    $linked = $owner->fetch();
    if ($linked) {
        if (!$isAdmin && $linked['status'] !== 'published') { http_response_code(404); exit('Not available.'); }
        if (!$isAdmin && $linked['access_type'] === 'paid' &&
            (empty($_SESSION['user_id']) || !(new Payment())->hasMovieAccess((int)$_SESSION['user_id'], (int)$linked['id']))) {
            http_response_code(403); exit('Payment is required to watch this movie.');
        }
    }
    // Resolve rendition path then enforce the same access rules as MP4 below.
    $jobs = new UploadJob();
    $ren = $jobs->rendition($mid, 'hls', 'local');
    if (!$ren || $ren['status'] !== 'ready') { http_response_code(404); exit('HLS stream not available.'); }
    $file = MediaStorage::get()->driver() === 'local'
        ? MediaStorage::get()->localPath($ren['storage_path'])
        : null;
    if (!$file || !is_file($file)) { http_response_code(404); exit('Stream file missing.'); }
    if ($seg !== null) {
        $segPath = dirname($file) . DIRECTORY_SEPARATOR . $seg;
        if (strpos($seg, '.ts') === false || !is_file($segPath)) { http_response_code(404); exit('Segment not found.'); }
        session_write_close();
        tmz_send_file($segPath, 'video/mp2t');
        exit;
    }
    // Serve the playlist, rewriting segment URIs through this signed endpoint.
    $base = '/media-play.php?hls=' . $mid . '&tok=' . urlencode($tok) . '&exp=' . $exp . '&q=' . urlencode($q) . '&seg=';
    $body = file_get_contents($file);
    $body = preg_replace_callback('/^(?!#)(\S+\.ts)(\?.*)?$/m', function ($m) use ($base) { return $base . rawurlencode($m[1]); }, (string)$body);
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: private, max-age=60');
    echo $body;
    exit;
}

/* ------------------------------------------------ resolve target + access */
$relativePath = null;
$mime = '';
$renditionJobs = null;

if (!empty($_GET['movie_id'])) {
    $movieId = (int)$_GET['movie_id'];
    $movie = (new Movie())->findById($movieId);
    if (!$movie || (!$isAdmin && $movie['status'] !== 'published')) { http_response_code(404); exit('Movie not found.'); }
    if (!$isAdmin && $movie['access_type'] === 'paid' &&
        (empty($_SESSION['user_id']) || !(new Payment())->hasMovieAccess((int)$_SESSION['user_id'], $movieId))) {
        http_response_code(403); exit('Payment is required to watch this movie.');
    }
    // Signed, expiring URL — stream links can't be shared, bookmarked or reused.
    $tokUser = (int)($_SESSION['user_id'] ?? 0);
    if (!tmz_movie_stream_valid($movieId, $tokUser, (string)($_GET['tok'] ?? ''), (int)($_GET['exp'] ?? 0))) {
        http_response_code(403); exit('Playback link expired — reload the movie page.');
    }
    // Movie video: prefer video_media_id rendition, else movie video_path.
    $mediaIdForRendition = (int)($movie['video_media_id'] ?? 0) ?: $movieId;
    $relativePath = (string)($movie['video_path'] ?? '');
    $mime = (string)($movie['video_mime_type'] ?? '');
    $renditionJobs = new UploadJob();
    $ren = $quality === 'original' ? null : $renditionJobs->rendition($mediaIdForRendition, '720p', 'local');
    if ($ren && $ren['status'] === 'ready') {
        $p = MediaStorage::get()->localPath($ren['storage_path']);
        if ($p) { $relativePath = $ren['storage_path']; $mime = 'video/mp4'; }
    }
} elseif (!empty($_GET['media_id']) || !empty($_GET['path'])) {
    if (!empty($_GET['media_id'])) {
        $media = (new MediaAsset())->findById((int)$_GET['media_id']);
        if (!$media || ($media['media_type'] ?? '') !== 'video') { http_response_code(404); exit('Video not found.'); }
        // Same rule as video-stream.php: linked paid movie videos must go through movie_id.
        $chk = $pdo->prepare("SELECT status, access_type, video_display FROM movies WHERE video_media_id = ? LIMIT 1");
        $chk->execute([(int)$media['id']]);
        $linked = $chk->fetch();
        if (!$isAdmin && $linked && ($linked['status'] !== 'published' || $linked['access_type'] !== 'free' || $linked['video_display'] === 'none')) {
            http_response_code(404); exit('Video not found.');
        }
        $relativePath = (string)$media['storage_path'];
        $mime = (string)($media['mime_type'] ?? '');
        $mediaKey = (int)$media['id'];
    } else {
        // Path form (episodes): accept only storage-relative video paths, resolve the owning media_asset.
        $rel = ltrim((string)$_GET['path'], '/');
        if (strpos($rel, 'storage/') !== 0) { http_response_code(400); exit('Invalid path.'); }
        $stmt = $pdo->prepare("SELECT id, storage_path, mime_type, media_type FROM media_assets WHERE storage_path = ? LIMIT 1");
        $stmt->execute([$rel]);
        $media = $stmt->fetch();
        if (!$media || $media['media_type'] !== 'video') { http_response_code(404); exit('Video not found.'); }
        $chk = $pdo->prepare("SELECT status, access_type, video_display FROM movies WHERE video_media_id = ? LIMIT 1");
        $chk->execute([(int)$media['id']]);
        $linked = $chk->fetch();
        if (!$isAdmin && $linked && ($linked['status'] !== 'published' || $linked['access_type'] !== 'free' || $linked['video_display'] === 'none')) {
            http_response_code(404); exit('Video not found.');
        }
        $relativePath = (string)$media['storage_path'];
        $mime = (string)$media['mime_type'];
        $mediaKey = (int)$media['id'];
    }
    $renditionJobs = new UploadJob();
    $ren = $quality === 'original' ? null : $renditionJobs->rendition((int)$mediaKey, '720p', 'local');
    if ($ren && $ren['status'] === 'ready') {
        $p = MediaStorage::get()->localPath($ren['storage_path']);
        if ($p) { $relativePath = $ren['storage_path']; $mime = 'video/mp4'; }
    }
} else {
    http_response_code(400); exit('A movie or media id is required.');
}

/* ------------------------------------------------ remote driver: signed redirect */
$store = MediaStorage::get();
if ($store->driver() === 'remote') {
    // Map storage_path ('storage/uploads/x.mp4') to object key ('uploads/x.mp4').
    $key = preg_replace('#^storage/#', '', $relativePath);
    try {
        $url = (new RemoteMediaClient((new SiteSetting())->getAllKeyed()))->signedUrl($key, null, 900);
        if ($url) { header('Location: ' . $url, true, 302); exit; }
    } catch (Throwable $e) { /* fall through to local attempt below */ }
}

/* ------------------------------------------------ local stream */
$uploadsRoot = realpath(__DIR__ . '/storage');
$file = realpath(__DIR__ . '/' . ltrim($relativePath, '/\\'));
if (!$uploadsRoot || !$file || strpos($file, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    http_response_code(404); exit('Video file is unavailable.');
}
if (strpos($mime, 'video/') !== 0) {
    $det = function_exists('mime_content_type') ? mime_content_type($file) : false;
    $mime = is_string($det) && strpos($det, 'video/') === 0 ? $det : 'video/mp4';
}
session_write_close();
tmz_send_file($file, $mime);
