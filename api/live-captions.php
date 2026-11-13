<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/LivePodcast.php';
require_once __DIR__ . '/../app/Models/Payment.php';

function liveCaptionJson(array $data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

function liveCaptionTimecode($milliseconds, $separator = ',') {
    $milliseconds = max(0, (int)$milliseconds);
    $hours = intdiv($milliseconds, 3600000);
    $minutes = intdiv($milliseconds % 3600000, 60000);
    $seconds = intdiv($milliseconds % 60000, 1000);
    $remainder = $milliseconds % 1000;
    return sprintf('%02d:%02d:%02d%s%03d', $hours, $minutes, $seconds, $separator, $remainder);
}

$liveId = filter_var($_GET['live_id'] ?? $_POST['live_id'] ?? null, FILTER_VALIDATE_INT);
$model = new LivePodcast();
$live = $liveId ? $model->getById($liveId) : null;
if (!$live) liveCaptionJson(['error' => 'Live show not found.'], 404);

$userId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = isAdmin();
$canView = $isAdmin || $live['access_type'] === 'free';
if (!$canView && $userId > 0) {
    $canView = $model->userCanAccess($userId, $liveId) || (new Payment())->hasActiveSubscription($userId);
}
if (!$canView) liveCaptionJson(['error' => 'Unlock this show to view its transcript.'], 403);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = trim((string)($_GET['q'] ?? ''));
    if (strlen($search) > 100) liveCaptionJson(['error' => 'Search text must be 100 characters or fewer.'], 400);
    $format = strtolower((string)($_GET['format'] ?? ''));
    if ($format !== '' && !in_array($format, ['txt', 'srt', 'vtt'], true)) {
        liveCaptionJson(['error' => 'Unsupported transcript format.'], 400);
    }
    $fromMs = 0;
    $toMs = PHP_INT_MAX;
    if (isset($_GET['from_ms']) || isset($_GET['to_ms'])) {
        $fromMs = filter_var($_GET['from_ms'] ?? '0', FILTER_VALIDATE_INT);
        $toMs = filter_var($_GET['to_ms'] ?? (string)PHP_INT_MAX, FILTER_VALIDATE_INT);
        if ($fromMs === false || $toMs === false || $fromMs < 0 || $toMs < $fromMs) {
            liveCaptionJson(['error' => 'Enter a valid start and end time range.'], 400);
        }
    }
    try {
        $lines = (isset($_GET['from_ms']) || isset($_GET['to_ms']))
            ? $model->transcriptLinesInRange($liveId, $fromMs, $toMs, $format === '' ? 500 : 20000)
            : $model->transcriptLines($liveId, $search, $format === '' ? 500 : 20000);
        if ($search !== '' && (isset($_GET['from_ms']) || isset($_GET['to_ms']))) {
            $needle = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);
            $lines = array_values(array_filter($lines, static function ($line) use ($needle) {
                $text = function_exists('mb_strtolower') ? mb_strtolower((string)$line['caption_text'], 'UTF-8') : strtolower((string)$line['caption_text']);
                return strpos($text, $needle) !== false;
            }));
        }
    } catch (Throwable $error) {
        error_log('Live transcript read failed: ' . $error->getMessage());
        liveCaptionJson(['error' => 'Could not load the transcript.'], 500);
    }
    if ($format !== '') {
        $output = [];
        if ($format === 'vtt') $output[] = 'WEBVTT' . PHP_EOL;
        foreach ($lines as $index => $line) {
            $text = trim(str_replace(['-->', "\r", "\n"], ['—>', ' ', ' '], $line['caption_text']));
            $speakerName = trim(str_replace(["-->", "\r", "\n"], ['—>', ' ', ' '], $line['speaker_name']));
            if ($format === 'txt') {
                $output[] = '[' . liveCaptionTimecode($line['start_time_ms']) . '] ' . $speakerName . ': ' . $text;
                continue;
            }
            $start = (int)$line['start_time_ms'];
            $nextStart = isset($lines[$index + 1]) ? (int)$lines[$index + 1]['start_time_ms'] : $start + 3000;
            $end = max($start + 1000, min($start + 8000, $nextStart));
            if ($format === 'srt') $output[] = (string)($index + 1);
            $output[] = liveCaptionTimecode($start, $format === 'srt' ? ',' : '.') . ' --> ' .
                liveCaptionTimecode($end, $format === 'srt' ? ',' : '.');
            $output[] = $speakerName . ': ' . $text;
            $output[] = '';
        }
        header('Content-Type: ' . ($format === 'vtt' ? 'text/vtt' : 'text/plain') . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="tmz-live-' . (int)$liveId . '.' . $format . '"');
        echo implode(PHP_EOL, $output);
        exit;
    }

    liveCaptionJson([
        'lines' => $lines,
        'can_correct' => $isAdmin,
        'show_status' => $live['status'],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') liveCaptionJson(['error' => 'GET or POST is required.'], 405);
CSRF::requireValidToken();

$captionId = filter_var($_POST['caption_id'] ?? null, FILTER_VALIDATE_INT);
if ($captionId) {
    if (!$isAdmin) liveCaptionJson(['error' => 'Administrator access is required to correct a transcript.'], 403);
    $text = trim((string)($_POST['text'] ?? ''));
    if ($text === '' || strlen($text) > 1200) liveCaptionJson(['error' => 'Corrected captions must be between 1 and 1200 characters.'], 400);
    try {
        if (!$model->correctTranscriptLine($liveId, $captionId, $text, (int)$_SESSION['admin_id'])) {
            liveCaptionJson(['error' => 'Transcript line not found.'], 404);
        }
    } catch (Throwable $error) {
        error_log('Live transcript correction failed: ' . $error->getMessage());
        liveCaptionJson(['error' => 'Could not save that transcript correction.'], 500);
    }
    liveCaptionJson(['ok' => true]);
}

if ($live['status'] !== 'live') liveCaptionJson(['error' => 'Captions can only be recorded while the show is live.'], 409);
$identity = '';
if ($isAdmin) {
    $identity = (string)($_SESSION['live_identities'][$liveId]['host_' . (int)$_SESSION['admin_id']] ?? '');
} elseif ($userId > 0) {
    $identity = (string)($_SESSION['live_identities'][$liveId]['member_' . $userId] ?? '');
}
if ($identity === '') liveCaptionJson(['error' => 'Join the live room before sending captions.'], 403);
$grant = $model->participantByIdentity($liveId, $identity);
if (!$grant || !empty($grant['banned_at']) || !empty($grant['removed_at']) ||
    (!$isAdmin && (empty($grant['can_publish_microphone']) || !empty($model->roomControls($liveId)['mute_all'])))) {
    liveCaptionJson(['error' => 'Only an enabled speaker can submit captions.'], 403);
}

$text = trim((string)($_POST['text'] ?? ''));
$language = trim((string)($_POST['language'] ?? 'en-US'));
if ($text === '' || strlen($text) > 1200) liveCaptionJson(['error' => 'Caption text must be between 1 and 1200 characters.'], 400);
if (!preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $language)) liveCaptionJson(['error' => 'Invalid caption language.'], 400);
$now = microtime(true);
$last = (float)($_SESSION['live_caption_last_sent'][$liveId] ?? 0);
if ($now - $last < 0.3) liveCaptionJson(['error' => 'Captions are arriving too quickly.'], 429);
$_SESSION['live_caption_last_sent'][$liveId] = $now;

$speakerName = $isAdmin ? 'THE MOVIE ZONE PODCAST 256 Host' : $model->participantName($userId);
$startedAt = !empty($live['started_at']) ? strtotime($live['started_at']) : false;
$startTimeMs = $startedAt ? max(0, (int)round(($now - $startedAt) * 1000)) : 0;
try {
    $lineId = $model->addTranscriptLine($liveId, $identity, $speakerName, $text, $language, $startTimeMs);
} catch (Throwable $error) {
    error_log('Live transcript insert failed: ' . $error->getMessage());
    liveCaptionJson(['error' => 'Could not save this caption to the transcript.'], 500);
}
liveCaptionJson([
    'ok' => true,
    'line' => [
        'id' => $lineId,
        'speaker_identity' => $identity,
        'speaker_name' => $speakerName,
        'caption_text' => $text,
        'language' => $language,
        'start_time_ms' => $startTimeMs,
    ],
]);
