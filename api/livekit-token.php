<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/livekit.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/LivePodcast.php';
require_once __DIR__ . '/../app/Models/Payment.php';
require_once __DIR__ . '/../app/Models/SiteNotification.php';

function liveTokenResponse(array $data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') liveTokenResponse(['error' => 'POST is required.'], 405);
CSRF::requireValidToken();

$liveId = filter_var($_POST['live_id'] ?? null, FILTER_VALIDATE_INT);
$requestedRole = $_POST['role'] ?? 'viewer';
$isPublisherRequest = $requestedRole === 'publisher';
$isAdmin = isAdmin();
if (!$liveId || !in_array($requestedRole, ['viewer', 'publisher'], true)) {
    liveTokenResponse(['error' => 'Invalid live room request.'], 400);
}
if ($isPublisherRequest && !$isAdmin) liveTokenResponse(['error' => 'Administrator access is required to publish.'], 403);

try {
    $model = new LivePodcast();
    $live = $model->getById($liveId);
    if (!$live || $live['status'] === 'ended') liveTokenResponse(['error' => 'This broadcast is not available.'], 404);
    if ($isPublisherRequest && !in_array($live['status'], ['live', 'scheduled'], true)) {
        liveTokenResponse(['error' => 'This room cannot be started.'], 409);
    }

    $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $host = $isPublisherRequest && $isAdmin;
    $hasPaidAccess = $userId && (
        $model->userCanAccess($userId, $liveId) ||
        (new Payment())->hasActiveSubscription($userId)
    );
    if (!$host && $live['status'] !== 'live') liveTokenResponse(['error' => 'This broadcast is not live yet.'], 409);
    if (!$host && $live['access_type'] === 'paid' && !$hasPaidAccess) {
        liveTokenResponse(['error' => 'Payment or an active membership is required to watch this broadcast.'], 403);
    }

    $controls = $model->roomControls($liveId);
    if (!$host && !empty($controls['room_locked'])) liveTokenResponse(['error' => 'The host has locked this room to new participants.'], 423);

    if ($host) {
        $identityKey = 'host_' . (int)$_SESSION['admin_id'];
        $role = 'host';
        $canChat = true;
        $displayName = 'DAK FILM PODCAST MEDIA Host';
    } elseif ($userId) {
        $identityKey = 'member_' . $userId;
        $role = 'participant';
        $canChat = true;
        $displayName = $model->participantName($userId);
    } else {
        if ($live['access_type'] !== 'free') liveTokenResponse(['error' => 'Sign in and unlock this paid broadcast to continue.'], 401);
        $identityKey = 'guest';
        $role = 'guest';
        $canChat = false;
        $displayName = 'Guest';
    }

    if (!isset($_SESSION['live_identities'][$liveId][$identityKey])) {
        $_SESSION['live_identities'][$liveId][$identityKey] = $identityKey . '-' . bin2hex(random_bytes(12));
    }
    $identity = $_SESSION['live_identities'][$liveId][$identityKey];

    if (!$host && $userId && $model->participantByUser($liveId, $userId)) {
        liveTokenResponse(['error' => 'You are banned from this live room.'], 403);
    }

    $participant = $model->participantByIdentity($liveId, $identity);
    if ($participant && !empty($participant['banned_at'])) {
        liveTokenResponse(['error' => 'You are banned from this live room.'], 403);
    }
    if ($participant && !empty($participant['removed_at'])) {
        $_SESSION['live_identities'][$liveId][$identityKey] = $identityKey . '-' . bin2hex(random_bytes(12));
        $identity = $_SESSION['live_identities'][$liveId][$identityKey];
        $participant = null;
    }

    if (!$participant) {
        $participant = $model->ensureParticipant($liveId, $userId, $identity, $role, $canChat);
    }
    if ($participant && !$host && $model->upgradeLegacyTalkDefaults($liveId, $identity)) {
        $participant = $model->participantByIdentity($liveId, $identity);
    }
    if (!$participant) throw new RuntimeException('Could not load live-room participant permissions.');
    if (!$host) $role = $participant['role'] ?? $role;

    if ($host && $live['status'] === 'scheduled') {
        $model->setStatus($liveId, 'live');
        $live['status'] = 'live';
        try {
            (new SiteNotification())->create(
                'all',
                null,
                'live_started',
                'A live show just started',
                $live['title'],
                '/live.php?live_id=' . (int)$liveId
            );
        } catch (Throwable $error) {
            error_log('Live-start notification could not be saved: ' . $error->getMessage());
        }
    }

    $sources = [];
    if ($host || !empty($participant['can_publish_microphone'])) {
        if ($host || empty($controls['mute_all'])) $sources[] = 'microphone';
    }
    if ($host || !empty($participant['can_publish_camera'])) {
        if ($host || empty($controls['disable_cameras'])) $sources[] = 'camera';
    }
    if ($host || !empty($participant['can_publish_screen_share'])) {
        $sources[] = 'screen_share';
        $sources[] = 'screen_share_audio';
    }
    $token = createLiveKitToken($identity, $live['room_name'], [
        'canPublish' => !empty($sources),
        'canPublishData' => $host || !empty($participant['can_chat']),
        'canPublishSources' => $sources,
        'name' => $displayName,
    ]);
    $token['identity'] = $identity;
    $token['role'] = $role;
    $token['canChat'] = $host || !empty($participant['can_chat']);
    liveTokenResponse($token);
} catch (Throwable $error) {
    error_log('LiveKit token issue failed: ' . $error->getMessage() . ' @ ' . $error->getFile() . ':' . $error->getLine());
    // TEMP debug: surface the real error while we diagnose the live-room failure.
    liveTokenResponse(['error' => 'Unable to join the live room right now. Please try again.', 'debug' => $error->getMessage()], 503);
}
