<?php
/**
 * Stream a track (audio, or its music video with ?type=video) with seeking support.
 * Media files live in storage/music/ (blocked from direct web access); this script checks
 * the viewer's access first.
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/Music.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Helpers/MediaStream.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$type = (($_GET['type'] ?? 'audio') === 'video') ? 'video' : 'audio';
$isAdmin = function_exists('isAdmin') && isAdmin();
$music = new Music();
$track = $id ? $music->trackById($id, $isAdmin) : null;
if (!$track) { http_response_code(404); exit('Track not found.'); }

$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$viewer = ['user_id' => $userId, 'admin' => $isAdmin, 'sub' => false];
if ($userId && $track['play_access'] === 'subscribers') {
    try { $viewer['sub'] = (new Payment())->hasActiveSubscription($userId); } catch (Throwable $e) { $viewer['sub'] = false; }
}
if (!Music::allowed($track['play_access'], $viewer)) {
    http_response_code(403);
    exit($track['play_access'] === 'subscribers' ? 'This track is included with All Access.' : 'Sign in to listen to this track.');
}

$relative = (string)($track[$type . '_path'] ?? '');
$root = realpath(__DIR__ . '/storage/music');
$full = $relative !== '' ? realpath(__DIR__ . '/' . $relative) : false;
if (!$root || !$full || strpos($full, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) {
    http_response_code(404);
    exit('Media file is missing.');
}

session_write_close(); // do not hold the session lock while streaming
tmz_send_file($full, Music::mimeFor($full));
