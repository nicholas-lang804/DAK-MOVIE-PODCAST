<?php
/** Download a track's audio (or ?type=video for its music video), honouring the track's download rule. */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/Music.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Helpers/MediaStream.php';
require_once __DIR__ . '/app/Models/SiteSetting.php';

function music_dl_page($status, $message, $linkHref = '', $linkText = '') {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Download</title>'
       . '<body style="font-family:system-ui,sans-serif;background:#050a1a;color:#eef3ff;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:1rem">'
       . '<main><h1>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>';
    if ($linkHref) echo '<p><a style="color:#fff;background:#e11d2e;padding:.7rem 1.4rem;border-radius:99px;text-decoration:none;font-weight:700" href="' . htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8') . '</a></p>';
    echo '<p><a style="color:#8fb8ff" href="/music.php">Back to Music</a></p></main>';
    exit;
}

if (!SiteSetting::downloadsEnabled()) {
    music_dl_page(403, 'Downloads are currently disabled.');
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$type = (($_GET['type'] ?? 'audio') === 'video') ? 'video' : 'audio';
$isAdmin = function_exists('isAdmin') && isAdmin();
$music = new Music();
$track = $id ? $music->trackById($id, $isAdmin) : null;
if (!$track) music_dl_page(404, 'Track not found.');

$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$viewer = ['user_id' => $userId, 'admin' => $isAdmin, 'sub' => false];
if ($userId && $track['download_access'] === 'subscribers') {
    try { $viewer['sub'] = (new Payment())->hasActiveSubscription($userId); } catch (Throwable $e) { $viewer['sub'] = false; }
}
if (!Music::allowed($track['download_access'], $viewer)) {
    if ($track['download_access'] === 'members') music_dl_page(403, 'Sign in to download this track.', '/login.php', 'Sign in');
    if ($track['download_access'] === 'subscribers') music_dl_page(403, 'Downloads are included with All Access.', '/membership.php', 'See plans');
    music_dl_page(403, 'Downloads are not available for this track.');
}
// A listener must also be allowed to play it.
if (!Music::allowed($track['play_access'], $viewer)) music_dl_page(403, 'You do not have access to this track.', '/membership.php', 'See plans');

$relative = (string)($track[$type . '_path'] ?? '');
$root = realpath(__DIR__ . '/storage/music');
$full = $relative !== '' ? realpath(__DIR__ . '/' . $relative) : false;
if (!$root || !$full || strpos($full, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) music_dl_page(404, 'The file is missing.');

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$name = trim($track['artist_name'] . ' - ' . $track['title']) . ($type === 'video' ? ' (Video)' : '') . '.' . $ext;

session_write_close();
if (tmz_send_file($full, Music::mimeFor($full), $name)) $music->recordDownload((int)$track['id']);
