<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/livekit.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/LivePodcast.php';
require_once __DIR__ . '/../app/Models/SiteNotification.php';

function liveHostReadyResponse(array $data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    liveHostReadyResponse(['error' => 'POST is required.'], 405);
}
if (!isAdmin()) {
    liveHostReadyResponse(['error' => 'Administrator access is required.'], 403);
}
CSRF::requireValidToken();

$liveId = filter_var($_POST['live_id'] ?? null, FILTER_VALIDATE_INT);
if (!$liveId) {
    liveHostReadyResponse(['error' => 'A valid live show is required.'], 400);
}

try {
    $model = new LivePodcast();
    $live = $model->getById($liveId);
    if (!$live || !in_array($live['status'], ['scheduled', 'live'], true)) {
        liveHostReadyResponse(['error' => 'This show cannot be started.'], 409);
    }

    if (!livekitRoomHasHost($live['room_name'])) {
        liveHostReadyResponse(['error' => 'LiveKit has not confirmed the host connection yet. Please try again.'], 409);
    }

    if ($live['status'] === 'scheduled') {
        $model->setStatus($liveId, 'live');
        try {
            (new SiteNotification())->create(
                'all',
                null,
                'live_started',
                'A live show just started',
                $live['title'],
                '/TMZ%20PODCAST/live.php?live_id=' . (int)$liveId
            );
        } catch (Throwable $error) {
            error_log('Live-start notification could not be saved: ' . $error->getMessage());
        }
    }

    liveHostReadyResponse(['ok' => true]);
} catch (Throwable $error) {
    error_log('Live host readiness check failed: ' . $error->getMessage());
    liveHostReadyResponse(['error' => 'The host connected, but the live show could not be started. Please try again.'], 503);
}
