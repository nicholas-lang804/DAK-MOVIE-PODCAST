<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/Movie.php';
require_once __DIR__ . '/app/Models/MediaAsset.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Helpers/MediaStream.php';
require_once __DIR__ . '/app/Helpers/StreamToken.php';

$movieId = filter_var($_GET['movie_id'] ?? null, FILTER_VALIDATE_INT);
$mediaId = filter_var($_GET['media_id'] ?? null, FILTER_VALIDATE_INT);
$pdo = getDB();
$isAdmin = isAdmin();

if ($movieId) {
    $movie = (new Movie())->findById($movieId);
    if (!$movie || (!$isAdmin && $movie['status'] !== 'published') || empty($movie['video_path'])) {
        http_response_code(404);
        exit('Movie video not found.');
    }
    if (!$isAdmin && $movie['access_type'] === 'paid' &&
        (empty($_SESSION['user_id']) || !(new Payment())->hasMovieAccess((int)$_SESSION['user_id'], $movieId))) {
        http_response_code(403);
        exit('Payment is required to watch this movie.');
    }
    // Non-admin movie streams must use a signed expiring URL (issued by the
    // movie page) — closes the unsigned-endpoint bypass.
    if (!$isAdmin && !tmz_movie_stream_valid($movieId, (int)($_SESSION['user_id'] ?? 0),
            (string)($_GET['tok'] ?? ''), (int)($_GET['exp'] ?? 0))) {
        http_response_code(403);
        exit('Playback link expired — reload the movie page.');
    }
    $relativePath = (string)$movie['video_path'];
    $mime = (string)($movie['video_mime_type'] ?? '');
} elseif ($mediaId) {
    $media = (new MediaAsset())->findById($mediaId);
    if (!$media || ($media['media_type'] ?? '') !== 'video') {
        http_response_code(404);
        exit('Video not found.');
    }

    $movieCheck = $pdo->prepare(
        "SELECT status, access_type, video_display FROM movies WHERE video_media_id = ? LIMIT 1"
    );
    $movieCheck->execute([$mediaId]);
    $linkedMovie = $movieCheck->fetch();
    if (!$isAdmin && $linkedMovie && (
        $linkedMovie['status'] !== 'published' ||
        $linkedMovie['access_type'] !== 'free' ||
        $linkedMovie['video_display'] === 'none'
    )) {
        http_response_code(404);
        exit('Video not found.');
    }

    $relativePath = (string)$media['storage_path'];
    $mime = (string)($media['mime_type'] ?? '');
} else {
    http_response_code(400);
    exit('A movie or video is required.');
}

$uploadsRoot = realpath(__DIR__ . '/storage/uploads');
$file = realpath(__DIR__ . '/' . ltrim($relativePath, '/\\'));
if (!$uploadsRoot || !$file || strpos($file, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    http_response_code(404);
    exit('Video file is unavailable.');
}

if (strpos($mime, 'video/') !== 0) {
    $detectedMime = function_exists('mime_content_type') ? mime_content_type($file) : false;
    $mime = is_string($detectedMime) && strpos($detectedMime, 'video/') === 0 ? $detectedMime : 'video/mp4';
}

session_write_close();
tmz_send_file($file, $mime);
