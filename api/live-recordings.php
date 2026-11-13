<?php
session_start();
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/livekit.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/LivePodcast.php';
require_once __DIR__ . '/../app/Models/Payment.php';

function liveRecordingJson(array $data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

$liveId = filter_var($_GET['live_id'] ?? $_POST['live_id'] ?? null, FILTER_VALIDATE_INT);
$model = new LivePodcast();
$live = $liveId ? $model->getById($liveId) : null;
if (!$live) liveRecordingJson(['error' => 'Live show not found.'], 404);

$userId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = isAdmin();
$canView = $isAdmin || $live['access_type'] === 'free';
if (!$canView && $userId > 0) {
    $canView = $model->userCanAccess($userId, $liveId) || (new Payment())->hasActiveSubscription($userId);
}
if (!$canView) liveRecordingJson(['error' => 'Unlock this show before downloading its recordings.'], 403);

function syncLiveRecordings(LivePodcast $model, array $live) {
    if (!$model->claimRecordingStatusSync((int)$live['id'])) return;
    $response = livekitEgressRequest('ListEgress', ['roomName' => $live['room_name'], 'active' => false], $live['room_name']);
    foreach (($response['items'] ?? []) as $egress) $model->updateRecordingFromEgress((int)$live['id'], $egress);
}

if (($_GET['action'] ?? '') === 'download') {
    $recordingId = filter_var($_GET['recording_id'] ?? null, FILTER_VALIDATE_INT);
    $recording = $recordingId ? $model->recordingById($recordingId) : null;
    if (!$recording || (int)$recording['live_id'] !== (int)$liveId) {
        liveRecordingJson(['error' => 'Recording not found.'], 404);
    }
    if (!$isAdmin && $recording['status'] !== 'complete') {
        liveRecordingJson(['error' => 'This recording is not ready to download.'], 409);
    }
    if ($recording['status'] !== 'complete' || strpos($recording['object_key'], 'live-recordings/live-' . (int)$liveId . '/') !== 0) {
        liveRecordingJson(['error' => 'This recording is not ready to download.'], 409);
    }
    try {
        header('Location: ' . livekitRecordingDownloadUrl($recording['object_key']));
        http_response_code(302);
        exit;
    } catch (Throwable $error) {
        error_log('Live recording download URL failed: ' . $error->getMessage());
        liveRecordingJson(['error' => 'Recording storage is not configured for downloads.'], 503);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $readiness = livekitRecordingReadiness();
    try {
        syncLiveRecordings($model, $live);
    } catch (Throwable $error) {
        error_log('Live recording status refresh failed: ' . $error->getMessage());
    }
    liveRecordingJson([
        'recordings' => $model->liveRecordings($liveId),
        'can_record' => $isAdmin && $live['status'] === 'live' && $readiness['ready'],
        'recording_setup_error' => $readiness['ready'] ? null : $readiness['message'],
        'active_recording_id' => ($active = $model->activeRecordingForLive($liveId)) ? (int)$active['id'] : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') liveRecordingJson(['error' => 'GET or POST is required.'], 405);
if (!$isAdmin) liveRecordingJson(['error' => 'Administrator access is required to manage recordings.'], 403);
CSRF::requireValidToken();

$action = trim((string)($_POST['action'] ?? ''));
try {
    if ($action === 'start') {
        if ($live['status'] !== 'live') liveRecordingJson(['error' => 'Recording can only be started while the show is live.'], 409);
        if (empty($live['room_name'])) liveRecordingJson(['error' => 'This show has no LiveKit room configured.'], 409);
        $readiness = livekitRecordingReadiness();
        if (!$readiness['ready']) liveRecordingJson(['error' => $readiness['message']], 503);
        $mediaType = trim((string)($_POST['media_type'] ?? ''));
        $format = strtolower(trim((string)($_POST['format'] ?? '')));
        if (!in_array($mediaType, ['video', 'audio'], true) ||
            ($mediaType === 'video' && $format !== 'mp4') ||
            ($mediaType === 'audio' && !in_array($format, ['mp3', 'ogg'], true))) {
            liveRecordingJson(['error' => 'Choose video MP4 or audio MP3/OGG.'], 400);
        }
        if ($model->activeRecordingForLive($liveId)) {
            liveRecordingJson(['error' => 'Stop the current recording before starting another.'], 409);
        }

        $s3 = livekitRecordingS3Config();
        $filename = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $format;
        $objectKey = 'live-recordings/live-' . (int)$liveId . '/' . $filename;
        $fileOutput = [
            'fileType' => strtoupper($format),
            'filepath' => $objectKey,
            's3' => [
                'accessKey' => $s3['access_key'],
                'secret' => $s3['secret'],
                'region' => $s3['region'],
                'bucket' => $s3['bucket'],
                'forcePathStyle' => $s3['force_path_style'],
            ],
        ];
        if ($s3['endpoint'] !== '') $fileOutput['s3']['endpoint'] = $s3['endpoint'];
        $payload = [
            'roomName' => $live['room_name'],
            'layout' => 'grid',
            'audioOnly' => $mediaType === 'audio',
            'videoOnly' => false,
            'fileOutputs' => [$fileOutput],
        ];
        $egress = livekitEgressRequest('StartRoomCompositeEgress', $payload, $live['room_name']);
        $egressId = trim((string)($egress['egressId'] ?? ''));
        if ($egressId === '') throw new RuntimeException('LiveKit did not return a recording ID.');
        try {
            $model->createRecording($liveId, $egressId, $mediaType, $format, $objectKey, (int)$_SESSION['admin_id']);
        } catch (Throwable $databaseError) {
            try {
                livekitEgressRequest('StopEgress', ['egressId' => $egressId], $live['room_name']);
            } catch (Throwable $stopError) {
                error_log('Orphan LiveKit recording could not be stopped: ' . $stopError->getMessage());
            }
            throw $databaseError;
        }
        $model->updateRecordingFromEgress($liveId, $egress);
        liveRecordingJson(['ok' => true, 'recording_id' => $model->recordingByEgressId($liveId, $egressId)['id'] ?? null]);
    }

    if ($action === 'stop') {
        $recordingId = filter_var($_POST['recording_id'] ?? null, FILTER_VALIDATE_INT);
        $recording = $recordingId ? $model->recordingById($recordingId) : null;
        if (!$recording || (int)$recording['live_id'] !== (int)$liveId) {
            liveRecordingJson(['error' => 'Recording not found.'], 404);
        }
        if (!in_array($recording['status'], ['starting', 'active', 'ending'], true)) {
            liveRecordingJson(['error' => 'That recording is no longer active.'], 409);
        }
        $egress = livekitEgressRequest('StopEgress', ['egressId' => $recording['egress_id']], $live['room_name']);
        if (!empty($egress['egressId']) && array_key_exists('status', $egress)) $model->updateRecordingFromEgress($liveId, $egress);
        liveRecordingJson(['ok' => true]);
    }

    if ($action === 'delete') {
        $recordingId = filter_var($_POST['recording_id'] ?? null, FILTER_VALIDATE_INT);
        $recording = $recordingId ? $model->recordingById($recordingId) : null;
        if (!$recording || (int)$recording['live_id'] !== (int)$liveId) {
            liveRecordingJson(['error' => 'Recording not found.'], 404);
        }
        if ($recording['status'] !== 'complete') {
            liveRecordingJson(['error' => 'Only completed recordings can be deleted.'], 409);
        }
        if (strpos($recording['object_key'], 'live-recordings/live-' . (int)$liveId . '/') !== 0) {
            liveRecordingJson(['error' => 'Recording storage path is invalid.'], 409);
        }
        livekitDeleteRecordingObject($recording['object_key']);
        if (!$model->deleteRecording($liveId, $recordingId)) {
            throw new RuntimeException('The file was deleted, but its recording entry could not be removed.');
        }
        liveRecordingJson(['ok' => true]);
    }

    liveRecordingJson(['error' => 'Unsupported recording action.'], 400);
} catch (Throwable $error) {
    error_log('Live recording action failed: ' . $error->getMessage());
    liveRecordingJson(['error' => $error->getMessage()], 502);
}
