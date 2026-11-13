<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/livekit.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/LivePodcast.php';
require_once __DIR__ . '/../app/Models/Payment.php';
require_once __DIR__ . '/../app/Models/SiteNotification.php';

function liveInvitationResponse(array $data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

$liveId = filter_var($_GET['live_id'] ?? $_POST['live_id'] ?? null, FILTER_VALIDATE_INT);
$model = new LivePodcast();
$live = $liveId ? $model->getById($liveId) : null;
if (!$live || $live['status'] !== 'live') liveInvitationResponse(['error' => 'This live room is not active.'], 404);
$userId = (int)($_SESSION['user_id'] ?? 0);
$isRoomAdmin = isAdmin();
if (!$isRoomAdmin) {
    if ($live['access_type'] === 'paid' &&
        ($userId <= 0 || (!$model->userCanAccess($userId, $liveId) && !(new Payment())->hasActiveSubscription($userId)))) {
        liveInvitationResponse(['error' => 'Unlock this show before responding to an invitation.'], 403);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $identity = (string)($_SESSION['live_identities'][$liveId]['member_' . $userId] ?? '');
    if ($userId <= 0 || $identity === '') liveInvitationResponse(['invitations' => []]);
    $participant = $model->participantByIdentity($liveId, $identity);
    if (!$participant || !empty($participant['banned_at']) || !empty($participant['removed_at'])) {
        liveInvitationResponse(['invitations' => []]);
    }
    liveInvitationResponse(['invitations' => $model->userSpeakerInvitations($liveId, $userId, $identity)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') liveInvitationResponse(['error' => 'GET or POST is required.'], 405);
CSRF::requireValidToken();
$action = trim((string)($_POST['action'] ?? ''));
$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
if (!$requestId) liveInvitationResponse(['error' => 'A valid speaker request is required.'], 400);

if ($isRoomAdmin) {
    if (!in_array($action, ['invite', 'deny'], true)) liveInvitationResponse(['error' => 'Unsupported administrator action.'], 400);
    $request = $model->speakerRequest($liveId, $requestId);
    if (!$request || $request['status'] !== 'pending') liveInvitationResponse(['error' => 'That request is no longer waiting.'], 409);

    if ($action === 'deny') {
        if (!$model->updateSpeakerRequest($liveId, $requestId, 'denied', 'pending')) {
            liveInvitationResponse(['error' => 'That request was handled by another administrator.'], 409);
        }
        try {
            (new SiteNotification())->create(
                'user',
                (int)$request['user_id'],
                'speaker_request',
                'Speaker request update',
                'Your request to speak during ' . $live['title'] . ' was not approved.',
                '/live.php?live_id=' . (int)$liveId
            );
        } catch (Throwable $error) {
            error_log('Speaker-request notification could not be saved: ' . $error->getMessage());
        }
        liveInvitationResponse(['ok' => true, 'status' => 'denied']);
    }

    $identity = (string)($request['participant_identity'] ?? '');
    $grant = $identity !== '' ? $model->participantByIdentity($liveId, $identity) : null;
    if (!$grant || (int)$grant['user_id'] !== (int)$request['user_id'] ||
        !empty($grant['banned_at']) || !empty($grant['removed_at'])) {
        liveInvitationResponse(['error' => 'The requesting participant is no longer eligible to speak.'], 409);
    }
    try {
        $participants = livekitRoomRequest('ListParticipants', ['room' => $live['room_name']], $live['room_name']);
        $connected = false;
        foreach (($participants['participants'] ?? []) as $participant) {
            if (($participant['identity'] ?? '') === $identity) {
                $connected = true;
                break;
            }
        }
    } catch (Throwable $error) {
        error_log('Speaker invitation participant lookup failed: ' . $error->getMessage());
        liveInvitationResponse(['error' => 'Could not verify the participant in LiveKit. Please try again.'], 502);
    }
    if (!$connected) liveInvitationResponse(['error' => 'The requesting participant is no longer connected.'], 409);
    if (!$model->updateSpeakerRequest($liveId, $requestId, 'invited', 'pending')) {
        liveInvitationResponse(['error' => 'That request was handled by another administrator.'], 409);
    }
    try {
        $model->logPermissionAudit($liveId, (int)$_SESSION['admin_id'], $identity, 'speaker_invite:' . $request['request_type'], ['request' => 'pending'], ['request' => 'invited']);
    } catch (Throwable $error) {
        error_log('Speaker invitation audit write failed: ' . $error->getMessage());
    }
    try {
        (new SiteNotification())->create(
            'user',
            (int)$request['user_id'],
            'speaker_invitation',
            'You have been invited to speak',
            $live['title'] . ' · ' . ucfirst($request['request_type']),
            '/live.php?live_id=' . (int)$liveId
        );
    } catch (Throwable $error) {
        error_log('Speaker invitation notification could not be saved: ' . $error->getMessage());
    }
    liveInvitationResponse(['ok' => true, 'status' => 'invited']);
}

$identity = (string)($_SESSION['live_identities'][$liveId]['member_' . $userId] ?? '');
if (!in_array($action, ['accept', 'decline', 'revoke'], true)) liveInvitationResponse(['error' => 'Unsupported invitation response.'], 400);
if ($userId <= 0 || $identity === '') liveInvitationResponse(['error' => 'Join the live room before responding to an invitation.'], 403);
$request = $model->speakerRequest($liveId, $requestId);
$expectedStatus = $action === 'revoke' ? 'accepted' : 'invited';
if (!$request || (int)$request['user_id'] !== $userId ||
    ($request['participant_identity'] ?? '') !== $identity || $request['status'] !== $expectedStatus) {
    liveInvitationResponse(['error' => 'That speaker invitation is no longer available.'], 409);
}

if ($action === 'decline') {
    if (!$model->updateSpeakerRequest($liveId, $requestId, 'declined', 'invited')) {
        liveInvitationResponse(['error' => 'That invitation was already handled.'], 409);
    }
    try {
        (new SiteNotification())->create(
            'admin',
            null,
            'speaker_declined',
            'A speaker declined an invitation',
            $model->participantName($userId) . ' declined an invitation for ' . $live['title'] . '.',
            '/admin/dashboard.php?tab=live&live_id=' . (int)$liveId
        );
    } catch (Throwable $notificationError) {
        error_log('Speaker-declined notification could not be saved: ' . $notificationError->getMessage());
    }
    liveInvitationResponse(['ok' => true, 'status' => 'declined']);
}
$grant = $model->participantByIdentity($liveId, $identity);
if (!$grant || !empty($grant['banned_at']) || !empty($grant['removed_at'])) {
    liveInvitationResponse(['error' => 'Your access to this room has changed.'], 403);
}
$controls = $model->roomControls($liveId);
$requestedVideo = $request['request_type'] === 'video';
if ($action === 'accept' && (($requestedVideo && !empty($controls['disable_cameras'])) ||
    (!$requestedVideo && !empty($controls['mute_all'])))) {
    liveInvitationResponse(['error' => 'The host has currently disabled that media in this room.'], 423);
}

$permissionField = $requestedVideo ? 'can_publish_camera' : 'can_publish_microphone';
$previousValue = !empty($grant[$permissionField]);
$nextValue = $action === 'accept';
$model->updateParticipantPermission($liveId, $identity, $permissionField, $nextValue);
$grant = $model->participantByIdentity($liveId, $identity);
if (!$grant || !empty($grant['banned_at']) || !empty($grant['removed_at'])) {
    liveInvitationResponse(['error' => 'Your access to this room has changed.'], 403);
}
$sources = [];
if (!empty($grant['can_publish_microphone']) && empty($controls['mute_all'])) $sources[] = 'MICROPHONE';
if (!empty($grant['can_publish_camera']) && empty($controls['disable_cameras'])) $sources[] = 'CAMERA';
if (!empty($grant['can_publish_screen_share'])) {
    $sources[] = 'SCREEN_SHARE';
    $sources[] = 'SCREEN_SHARE_AUDIO';
}

try {
    livekitRoomRequest('UpdateParticipant', [
        'room' => $live['room_name'],
        'identity' => $identity,
        'permission' => [
            'canSubscribe' => true,
            'canPublish' => !empty($sources),
            'canPublishData' => !empty($grant['can_chat']),
            'canPublishSources' => $sources,
        ],
    ], $live['room_name']);

    if ($action === 'accept') {
        if (!$model->updateSpeakerRequest($liveId, $requestId, 'accepted', 'invited')) {
            throw new RuntimeException('Speaker invitation changed before it could be accepted.');
        }
        try {
            (new SiteNotification())->create(
                'admin',
                null,
                'speaker_accepted',
                'A speaker accepted an invitation',
                $model->participantName($userId) . ' accepted an invitation for ' . $live['title'] . '.',
                '/admin/dashboard.php?tab=live&live_id=' . (int)$liveId
            );
        } catch (Throwable $notificationError) {
            error_log('Speaker acceptance notification could not be saved: ' . $notificationError->getMessage());
        }
        try {
            $model->logPermissionAudit($liveId, null, $identity, 'permission:' . $permissionField, [$permissionField => $previousValue], [$permissionField => true]);
        } catch (Throwable $auditError) {
            error_log('Speaker permission audit write failed: ' . $auditError->getMessage());
        }
    } elseif (!$model->updateSpeakerRequest($liveId, $requestId, 'declined', 'accepted')) {
        throw new RuntimeException('Speaker permission was already withdrawn.');
    } else {
        try {
            $model->logPermissionAudit($liveId, null, $identity, 'permission:' . $permissionField, [$permissionField => $previousValue], [$permissionField => false]);
        } catch (Throwable $auditError) {
            error_log('Speaker permission audit write failed: ' . $auditError->getMessage());
        }
    }
    liveInvitationResponse(['ok' => true, 'status' => $action === 'accept' ? 'accepted' : 'revoked']);
} catch (Throwable $error) {
    $model->updateParticipantPermission($liveId, $identity, $permissionField, $previousValue);
    if ($action === 'accept') $model->updateSpeakerRequest($liveId, $requestId, 'invited', 'accepted');
    if ($action === 'revoke') $model->updateSpeakerRequest($liveId, $requestId, 'accepted', 'declined');
    $restored = $model->participantByIdentity($liveId, $identity);
    $restoreSources = [];
    if (!empty($restored['can_publish_microphone']) && empty($controls['mute_all'])) $restoreSources[] = 'MICROPHONE';
    if (!empty($restored['can_publish_camera']) && empty($controls['disable_cameras'])) $restoreSources[] = 'CAMERA';
    if (!empty($restored['can_publish_screen_share'])) {
        $restoreSources[] = 'SCREEN_SHARE';
        $restoreSources[] = 'SCREEN_SHARE_AUDIO';
    }
    try {
        livekitRoomRequest('UpdateParticipant', [
            'room' => $live['room_name'],
            'identity' => $identity,
            'permission' => [
                'canSubscribe' => true,
                'canPublish' => !empty($restoreSources),
                'canPublishData' => !empty($restored['can_chat']),
                'canPublishSources' => $restoreSources,
            ],
        ], $live['room_name']);
    } catch (Throwable $rollbackError) {
        error_log('Speaker invitation rollback failed: ' . $rollbackError->getMessage());
    }
    error_log('Speaker invitation permission update failed: ' . $error->getMessage());
    liveInvitationResponse(['error' => 'LiveKit could not apply the invitation permission. Please try again.'], 502);
}
