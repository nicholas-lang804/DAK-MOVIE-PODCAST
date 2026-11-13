<?php
/**
 * Music API (JSON)
 * GET : track (details + lyrics), playlists, suggest
 * POST: like, played, playlist_create, playlist_add, playlist_remove, playlist_rename, playlist_delete
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/Music.php';
require_once __DIR__ . '/../app/Models/Payment.php';

function mz_out(array $data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$isAdmin = function_exists('isAdmin') && isAdmin();
$read = ['track', 'playlists', 'suggest'];
$write = ['like', 'played', 'playlist_create', 'playlist_add', 'playlist_remove', 'playlist_rename', 'playlist_delete'];
$needLogin = ['like', 'playlists', 'playlist_create', 'playlist_add', 'playlist_remove', 'playlist_rename', 'playlist_delete'];

if (!in_array($action, array_merge($read, $write), true)) mz_out(['error' => 'Unknown action.'], 400);
if (in_array($action, $write, true)) {
    if (!$isPost) mz_out(['error' => 'POST required.'], 405);
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!CSRF::validateToken((string)$token)) mz_out(['error' => 'Security token expired. Refresh the page.'], 403);
}
if (in_array($action, $needLogin, true) && !$userId) mz_out(['error' => 'Sign in to use this feature.', 'login' => true], 401);

$music = new Music();
$trackId = filter_var($_POST['track_id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT);

function mz_viewer($userId, $isAdmin, $needSub) {
    $v = ['user_id' => $userId, 'admin' => $isAdmin, 'sub' => false];
    if ($userId && $needSub) { try { $v['sub'] = (new Payment())->hasActiveSubscription($userId); } catch (Throwable $e) {} }
    return $v;
}

switch ($action) {
    case 'track':
        $t = $trackId ? $music->trackById($trackId, $isAdmin) : null;
        if (!$t) mz_out(['error' => 'Track not found.'], 404);
        $viewer = mz_viewer($userId, $isAdmin, true);
        // Lyrics are shown to anyone who may play the track.
        $lyrics = Music::allowed($t['play_access'], $viewer) ? (string)($t['lyrics'] ?? '') : '';
        mz_out(['track' => $music->export($t, $viewer, $music->likedIds($userId, [(int)$t['id']])), 'lyrics' => function_exists('mb_substr') ? mb_substr($lyrics, 0, 20000) : substr($lyrics, 0, 20000)]);

    case 'suggest':
        $q = trim((string)($_GET['q'] ?? ''));
        if (strlen($q) < 2) mz_out(['items' => []]);
        $items = [];
        foreach ($music->search($q, 6) as $t) $items[] = ['title' => $t['title'], 'artist' => $t['artist_name'], 'url' => '/TMZ%20PODCAST/music.php?track=' . rawurlencode($t['slug'])];
        mz_out(['items' => $items]);

    case 'like':
        if (!$trackId || !$music->trackById($trackId)) mz_out(['error' => 'Track not found.'], 404);
        mz_out(['ok' => true, 'liked' => $music->toggleLike($userId, $trackId)]);

    case 'played':
        $t = $trackId ? $music->trackById($trackId) : null;
        if (!$t) mz_out(['error' => 'Track not found.'], 404);
        $viewer = mz_viewer($userId, $isAdmin, $t['play_access'] === 'subscribers');
        if (!Music::allowed($t['play_access'], $viewer)) mz_out(['error' => 'Not allowed.'], 403);
        $key = 'mz_played_' . (int)$trackId;
        if (time() - (int)($_SESSION[$key] ?? 0) < 25) mz_out(['ok' => true, 'counted' => false]); // ignore rapid repeats
        $_SESSION[$key] = time();
        mz_out(['ok' => true, 'counted' => $music->recordPlay((int)$trackId, $userId)]);

    case 'playlists':
        mz_out(['playlists' => $music->playlists($userId)]);

    case 'playlist_create':
        $id = $music->createPlaylist($userId, (string)($_POST['name'] ?? ''));
        if (!$id) mz_out(['error' => 'Give the playlist a name (you can have up to 100 playlists).'], 422);
        if ($trackId) $music->addToPlaylist($id, $userId, $trackId);
        mz_out(['ok' => true, 'id' => $id, 'playlists' => $music->playlists($userId)]);

    case 'playlist_add':
        $ok = $music->addToPlaylist((int)($_POST['playlist_id'] ?? 0), $userId, (int)$trackId);
        mz_out(['ok' => $ok, 'playlists' => $music->playlists($userId)], $ok ? 200 : 404);

    case 'playlist_remove':
        $ok = $music->removeFromPlaylist((int)($_POST['playlist_id'] ?? 0), $userId, (int)$trackId);
        mz_out(['ok' => $ok], $ok ? 200 : 404);

    case 'playlist_rename':
        $ok = $music->playlist((int)($_POST['playlist_id'] ?? 0), $userId) && $music->renamePlaylist((int)$_POST['playlist_id'], $userId, (string)($_POST['name'] ?? ''));
        mz_out(['ok' => (bool)$ok], $ok ? 200 : 404);

    case 'playlist_delete':
        $ok = $music->deletePlaylist((int)($_POST['playlist_id'] ?? 0), $userId);
        mz_out(['ok' => $ok], $ok ? 200 : 404);
}
mz_out(['error' => 'Unhandled action.'], 400);
