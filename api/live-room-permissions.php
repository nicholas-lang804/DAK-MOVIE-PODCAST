<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/livekit.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/LivePodcast.php';

function livePermissionsResponse(array $data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isAdmin()) livePermissionsResponse(['error' => 'Administrator access is required.'], 403);

$model = new LivePodcast();
$liveId = filter_var($_GET['live_id'] ?? $_POST['live_id'] ?? null, FILTER_VALIDATE_INT);
$live = $liveId ? $model->getById($liveId) : null;
if (!$live) livePermissionsResponse(['error' => 'Live room not found.'], 404);

function liveParticipantSources(array $grant, array $controls) {
    $sources = [];
    $isHost = ($grant['role'] ?? '') === 'host';
    if (!empty($grant['can_publish_microphone']) && ($isHost || empty($controls['mute_all']))) $sources[] = 'microphone';
    if (!empty($grant['can_publish_camera']) && ($isHost || empty($controls['disable_cameras']))) $sources[] = 'camera';
    if (!empty($grant['can_publish_screen_share'])) {
        $sources[] = 'screen_share';
        $sources[] = 'screen_share_audio';
    }
    return $sources;
}

function liveUpdateParticipant($room, array $grant, array $controls) {
    $sources = liveParticipantSources($grant, $controls);
    $sources = array_map('strtoupper', $sources);
    return livekitRoomRequest('UpdateParticipant', [
        'room' => $room,
        'identity' => $grant['identity'],
        'permission' => [
            'canSubscribe' => true,
            'canPublish' => !empty($sources),
            'canPublishData' => !empty($grant['can_chat']),
            'canPublishSources' => $sources,
        ],
    ], $room);
}

function liveRemovePublishedSources($room, $identity, array $tracks, array $sources) {
    $sources = array_map('strtolower', $sources);
    foreach ($tracks as $track) {
        if (in_array(strtolower((string)($track['source'] ?? '')), $sources, true) && !empty($track['sid'])) {
            try {
                // Silences/hides the live track immediately. The revoked permission (UpdateParticipant) is what keeps them off.
                livekitRoomRequest('MutePublishedTrack', [
                    'room' => $room,
                    'identity' => $identity,
                    'trackSid' => $track['sid'],
                    'muted' => true,
                ], $room);
            } catch (Throwable $error) {
                error_log('Could not mute a revoked live track (the permission change still applies): ' . $error->getMessage());
            }
        }
    }
}

function liveCurrentParticipants($room) {
    $result = livekitRoomRequest('ListParticipants', ['room' => $room], $room);
    return $result['participants'] ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $participants = liveCurrentParticipants($live['room_name']);
        $grants = [];
        foreach ($model->permissionRows($liveId) as $grant) $grants[$grant['identity']] = $grant;
        $visible = [];
        foreach ($participants as $participant) {
            $identity = (string)($participant['identity'] ?? '');
            $grant = $grants[$identity] ?? null;
            $visible[] = [
                'identity' => $identity,
                'name' => (string)($participant['name'] ?? (($grant['role'] ?? '') === 'host' ? 'Host' : ($grant['role'] ?? 'Participant'))),
                'role' => $grant['role'] ?? 'participant',
                'tracks' => $participant['tracks'] ?? [],
                'state' => $participant['state'] ?? 'active',
                'can_publish_microphone' => !empty($grant['can_publish_microphone']),
                'can_publish_camera' => !empty($grant['can_publish_camera']),
                'can_publish_screen_share' => !empty($grant['can_publish_screen_share']),
                'can_chat' => !empty($grant['can_chat']),
                'user_id' => $grant['user_id'] ?? null,
            ];
        }
        livePermissionsResponse([
            'participants' => $visible,
            'speaker_requests' => array_values(array_filter(
                $model->requests($liveId),
                static function ($request) { return ($request['status'] ?? '') === 'pending'; }
            )),
            'controls' => $model->roomControls($liveId),
            'status' => $live['status'],
        ]);
    } catch (Throwable $error) {
        error_log('Live participant list failed: ' . $error->getMessage());
        livePermissionsResponse(['error' => 'Could not load live participants from LiveKit.'], 502);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') livePermissionsResponse(['error' => 'GET or POST is required.'], 405);
CSRF::requireValidToken();
if ($live['status'] !== 'live') livePermissionsResponse(['error' => 'Participant permissions can only be changed while live.'], 409);

$action = trim($_POST['action'] ?? '');
$identity = trim($_POST['identity'] ?? '');
$adminId = (int)$_SESSION['admin_id'];
$room = $live['room_name'];

try {
    $participants = liveCurrentParticipants($room);
    $participantIndex = [];
    foreach ($participants as $participant) $participantIndex[$participant['identity'] ?? ''] = $participant;

    if ($action === 'room_control') {
        $control = $_POST['control'] ?? '';
        $allowed = ['mute_all', 'disable_cameras', 'room_locked'];
        if (!in_array($control, $allowed, true)) livePermissionsResponse(['error' => 'Invalid room control.'], 400);
        $value = filter_var($_POST['value'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) livePermissionsResponse(['error' => 'A boolean value is required.'], 400);
        $before = $model->roomControls($liveId);
        $model->setRoomControl($liveId, $control, $value);
        $after = $model->roomControls($liveId);
        try {
            foreach ($participants as $participant) {
                $grant = $model->participantByIdentity($liveId, $participant['identity'] ?? '');
                if ($grant) liveUpdateParticipant($room, $grant, $after);
                if ($value && $control === 'mute_all' && $grant && ($grant['role'] ?? '') !== 'host') {
                    liveRemovePublishedSources($room, $grant['identity'], $participant['tracks'] ?? [], ['microphone']);
                }
                if ($value && $control === 'disable_cameras' && $grant) {
                    liveRemovePublishedSources($room, $grant['identity'], $participant['tracks'] ?? [], ['camera']);
                }
            }
        } catch (Throwable $error) {
            $model->setRoomControl($liveId, $control, !empty($before[$control]));
            foreach ($participants as $participant) {
                $grant = $model->participantByIdentity($liveId, $participant['identity'] ?? '');
                if ($grant) {
                    try {
                        liveUpdateParticipant($room, $grant, $before);
                    } catch (Throwable $rollbackError) {
                        error_log('Live room control rollback failed: ' . $rollbackError->getMessage());
                    }
                }
            }
            throw $error;
        }
        $model->logPermissionAudit($liveId, $adminId, null, $control, $before, $after);
        livePermissionsResponse(['ok' => true, 'controls' => $after]);
    }

    if ($action === 'end_for_all') {
        livekitRoomRequest('DeleteRoom', ['room' => $room], $room);
        $model->setStatus($liveId, 'ended');
        $model->logPermissionAudit($liveId, $adminId, null, 'end_for_all', ['status' => 'live'], ['status' => 'ended']);
        livePermissionsResponse(['ok' => true, 'status' => 'ended']);
    }

    if ($identity === '' || !isset($participantIndex[$identity])) {
        livePermissionsResponse(['error' => 'That participant is no longer in the live room.'], 404);
    }
    $grant = $model->participantByIdentity($liveId, $identity);
    if (!$grant) livePermissionsResponse(['error' => 'Participant permission record not found.'], 404);
    if ($grant['role'] === 'host') livePermissionsResponse(['error' => 'The host permissions cannot be changed here.'], 400);
    $trackList = $participantIndex[$identity]['tracks'] ?? [];
    $before = $grant;

    if ($action === 'permission') {
        $fieldMap = [
            'microphone' => 'can_publish_microphone',
            'camera' => 'can_publish_camera',
            'screen_share' => 'can_publish_screen_share',
            'chat' => 'can_chat',
        ];
        $key = $_POST['permission'] ?? '';
        if (!isset($fieldMap[$key])) livePermissionsResponse(['error' => 'Invalid participant permission.'], 400);
        if ($key === 'chat' && empty($grant['user_id'])) livePermissionsResponse(['error' => 'Guests cannot post in member chat.'], 400);
        $value = filter_var($_POST['value'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) livePermissionsResponse(['error' => 'A boolean value is required.'], 400);
        $model->updateParticipantPermission($liveId, $identity, $fieldMap[$key], $value);
        try {
            $grant = $model->participantByIdentity($liveId, $identity);
            if (!$grant) throw new RuntimeException('Participant permission record disappeared.');
            $controls = $model->roomControls($liveId);
            liveUpdateParticipant($room, $grant, $controls);
            if (!$value) {
                $sources = $key === 'microphone' ? ['microphone'] : ($key === 'camera' ? ['camera'] : ($key === 'screen_share' ? ['screen_share', 'screen_share_audio'] : []));
                liveRemovePublishedSources($room, $identity, $trackList, $sources);
            }
        } catch (Throwable $error) {
            $previous = $key === 'chat' ? !empty($before['can_chat']) : !empty($before[$fieldMap[$key]]);
            $model->updateParticipantPermission($liveId, $identity, $fieldMap[$key], $previous);
            $restored = $model->participantByIdentity($liveId, $identity);
            if ($restored) {
                try {
                    liveUpdateParticipant($room, $restored, $model->roomControls($liveId));
                } catch (Throwable $rollbackError) {
                    error_log('Participant permission rollback failed: ' . $rollbackError->getMessage());
                }
            }
            throw $error;
        }
    } elseif ($action === 'promote') {
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['co_host', 'presenter', 'moderator', 'participant'], true)) {
            livePermissionsResponse(['error' => 'Invalid live-room role.'], 400);
        }
        $model->updateParticipantPermission($liveId, $identity, 'role', $role);
        $grant = $model->participantByIdentity($liveId, $identity);
    } elseif ($action === 'remove' || $action === 'ban') {
        livekitRoomRequest('RemoveParticipant', ['room' => $room, 'identity' => $identity], $room);
        $model->markParticipantRemoved($liveId, $identity, $action === 'ban');
    } else {
        livePermissionsResponse(['error' => 'Unsupported participant action.'], 400);
    }

    $after = $model->participantByIdentity($liveId, $identity);
    $model->logPermissionAudit($liveId, $adminId, $identity, $action . ($action === 'permission' ? ':' . $key : ''), $before, $after);
    livePermissionsResponse(['ok' => true, 'participant' => $after]);
} catch (Throwable $error) {
    error_log('Live permission update failed: ' . $error->getMessage());
    livePermissionsResponse(['error' => 'LiveKit could not apply that change. Check the room service connection and try again.'], 502);
}
