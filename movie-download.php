<?php
/**
 * Protected movie download — only when ALL of these are true:
 *   1. Admin → Settings → "Allow downloads" is ON (site-wide switch)
 *   2. The movie's own "Allow downloads" checkbox is ON (per-movie opt-in)
 *   3. The viewer may watch it (free movie, or paid movie with verified payment)
 *
 * Otherwise the button is hidden in the UI AND this endpoint refuses with 403.
 * Streams the ORIGINAL file (not the 720p rendition) as an attachment.
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/Movie.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Models/SiteSetting.php';
require_once __DIR__ . '/app/Helpers/MediaStream.php';

function dlFail($code, $msg) { http_response_code($code); exit($msg); }

if (!SiteSetting::downloadsEnabled()) {
    dlFail(403, 'Downloads are currently disabled on this site.');
}

$movieId = filter_var($_GET['movie_id'] ?? null, FILTER_VALIDATE_INT);
if (!$movieId) dlFail(400, 'A movie is required.');

$movie = (new Movie())->findById($movieId);
$isAdmin = isAdmin();
if (!$movie || (!$isAdmin && $movie['status'] !== 'published')) dlFail(404, 'Movie not found.');

// Per-movie opt-in — the admin checkbox on the movie form.
if (empty($movie['allow_download'])) dlFail(403, 'Downloads are not enabled for this title.');

$relativePath = (string)($movie['video_path'] ?? '');
if ($relativePath === '') dlFail(404, 'No video file is attached to this movie.');

// Same access rule as streaming: paid movies need a verified payment.
if (!$isAdmin && $movie['access_type'] === 'paid' &&
    (empty($_SESSION['user_id']) || !(new Payment())->hasMovieAccess((int)$_SESSION['user_id'], $movieId))) {
    dlFail(403, 'Payment is required to download this movie.');
}

$storageRoot = realpath(__DIR__ . '/storage');
$file = realpath(__DIR__ . '/' . ltrim($relativePath, '/\\'));
if (!$storageRoot || !$file || strpos($file, $storageRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    dlFail(404, 'Video file is unavailable.');
}

$mime = (string)($movie['video_mime_type'] ?? '');
if (strpos($mime, 'video/') !== 0) {
    $det = function_exists('mime_content_type') ? mime_content_type($file) : false;
    $mime = is_string($det) && strpos($det, 'video/') === 0 ? $det : 'video/mp4';
}

// Download name = clean movie title, never the raw upload filename.
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: 'mp4';
$downloadName = trim((string)preg_replace('/[^\p{L}\p{N} ._\-]+/u', '', (string)$movie['title'])) . '.' . $ext;

session_write_close();
tmz_send_file($file, $mime, $downloadName);
