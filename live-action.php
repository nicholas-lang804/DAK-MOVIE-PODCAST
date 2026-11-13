<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Middleware/CSRF.php';
require_once __DIR__ . '/app/Models/LivePodcast.php';
require_once __DIR__ . '/app/Models/Payment.php';

if (!isLoggedIn() || empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Sign in to interact with the live show.');
}
CSRF::requireValidToken();

$liveId = filter_var($_POST['live_id'] ?? null, FILTER_VALIDATE_INT);
$model = new LivePodcast();
$live = $liveId ? $model->getById($liveId) : null;
$userId = (int)$_SESSION['user_id'];
if (!$live || $live['status'] !== 'live') {
    http_response_code(404);
    exit('This live show is not available.');
}
if ($live['access_type'] === 'paid' &&
    !$model->userCanAccess($userId, $liveId) &&
    !(new Payment())->hasActiveSubscription($userId)) {
    http_response_code(403);
    exit('Unlock this show before interacting.');
}

$identity = $_SESSION['live_identities'][$liveId]['member_' . $userId] ?? '';
$participant = $identity !== '' ? $model->participantByIdentity($liveId, $identity) : null;
$hasRoomAction = trim((string)($_POST['comment'] ?? '')) !== '' ||
    isset($_POST['reaction']) ||
    isset($_POST['request_speaker']);
if ($hasRoomAction &&
    (!$participant || empty($participant['can_chat']) || !empty($participant['banned_at']) || !empty($participant['removed_at']))) {
    http_response_code(403);
    exit('You do not have permission to interact in this live room.');
}

$comment = trim((string)($_POST['comment'] ?? ''));
if ($comment !== '') {
    if (strlen($comment) > 500) {
        http_response_code(400);
        exit('Messages must be 500 characters or fewer.');
    }
    $model->addComment($liveId, $userId, $comment);
}
if (isset($_POST['reaction'])) {
    if (!in_array($_POST['reaction'], ['like', 'love', 'fire', 'clap'], true)) {
        http_response_code(400);
        exit('Invalid reaction.');
    }
    $model->react($liveId, $userId, $_POST['reaction']);
}
if (isset($_POST['request_speaker'])) {
    if (!in_array($_POST['request_speaker'], ['audio', 'video'], true)) {
        http_response_code(400);
        exit('Invalid speaker request.');
    }
    $model->requestSpeaker($liveId, $userId, $_POST['request_speaker'], $identity);
}

if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}
header('Location: /live.php');
exit;
