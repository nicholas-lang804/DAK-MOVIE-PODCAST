<?php
/**
 * Live Extras API (JSON)
 *
 * Public / member:  state, vote, ask, upvote, react, remind
 * Admin only:       admin_state, set_stage, set_schedule, poll_create, poll_close,
 *                   question_status, send_reminders, report, report_csv
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/LivePodcast.php';
require_once __DIR__ . '/../app/Models/Payment.php';
require_once __DIR__ . '/../app/Models/LiveExtras.php';

function lx_out(array $data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'state');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$isAdmin = function_exists('isAdmin') && isAdmin();
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$adminActions = ['admin_state', 'set_stage', 'set_schedule', 'poll_create', 'poll_close', 'question_status', 'send_reminders', 'report', 'report_csv'];
$memberActions = ['vote', 'ask', 'upvote', 'react', 'remind'];
$readActions = ['state', 'admin_state', 'report', 'report_csv'];

if (!in_array($action, array_merge($adminActions, $memberActions, ['state']), true)) lx_out(['error' => 'Unknown action.'], 400);

if (!in_array($action, $readActions, true)) {
    if (!$isPost) lx_out(['error' => 'This action requires POST.'], 405);
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!CSRF::validateToken((string)$token)) lx_out(['error' => 'Security token expired. Refresh the page and try again.'], 403);
}
if (in_array($action, $adminActions, true) && !$isAdmin) lx_out(['error' => 'Administrator access is required.'], 403);
if (in_array($action, $memberActions, true) && !$userId && !($action === 'react' && $isAdmin)) lx_out(['error' => 'Sign in to take part.'], 401);

$liveId = filter_var($_POST['live_id'] ?? $_GET['live_id'] ?? null, FILTER_VALIDATE_INT);
if (!$liveId) lx_out(['error' => 'Missing show.'], 400);

$live = new LivePodcast();
$extras = new LiveExtras();
$row = $extras->liveRow($liveId);
if (!$row) lx_out(['error' => 'Show not found.'], 404);

// Same access rule as the live page: free shows, purchased shows or an active all-access plan.
$canAccess = $isAdmin || $row['access_type'] === 'free';
if (!$canAccess && $userId) {
    try {
        $canAccess = $live->userCanAccess($userId, $liveId) || (new Payment())->hasActiveSubscription($userId);
    } catch (Throwable $e) {
        $canAccess = false;
    }
}

/** Short per-session cooldown for write actions. */
function lx_cooldown($name, $seconds) {
    $now = time();
    $last = (int)($_SESSION['lx_rate'][$name] ?? 0);
    if ($now - $last < $seconds) lx_out(['error' => 'Please wait a moment before doing that again.'], 429);
    $_SESSION['lx_rate'][$name] = $now;
}

function lx_viewer_key($userId) {
    if ($userId) return 'u' . (int)$userId;
    if (!empty($_SESSION['admin_id'])) return 'a' . (int)$_SESSION['admin_id'];
    return 'g' . substr(sha1(session_id()), 0, 20);
}

function lx_state_payload($extras, array $row, $liveId, $userId, $isAdmin, $withPresence) {
    $viewers = null; $peak = (int)$row['viewer_peak'];
    if ($withPresence && $row['status'] === 'live') {
        list($viewers, $peak) = $extras->touchPresence($liveId, lx_viewer_key($userId), $userId ?: null);
    } elseif ($row['status'] === 'live') {
        $viewers = $extras->currentViewers($liveId);
    }
    return [
        'live' => [
            'id' => (int)$row['id'], 'title' => $row['title'], 'status' => $row['status'],
            'scheduled_at' => LiveExtras::toIsoUtc($row['scheduled_at']),
        ],
        'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
        'viewers' => $viewers,
        'peak' => $peak,
        'stage' => $extras->stage($liveId),
        'poll' => $extras->activePoll($liveId, $userId),
        'questions' => $extras->questions($liveId, $userId, false, 30),
        'logged_in' => (bool)$userId,
        'reminder' => $userId ? $extras->hasReminder($liveId, $userId) : false,
    ];
}

switch ($action) {
    case 'state':
        // Opportunistic: deliver "starting soon" reminders without needing a cron job.
        if (mt_rand(1, 6) === 1) $extras->dispatchDueReminders();
        if ($row['access_type'] === 'paid' && !$canAccess) {
            lx_out(['live' => ['id' => (int)$row['id'], 'title' => $row['title'], 'status' => $row['status'], 'scheduled_at' => LiveExtras::toIsoUtc($row['scheduled_at'])],
                    'server_time' => gmdate('Y-m-d\TH:i:s\Z'), 'locked' => true, 'logged_in' => (bool)$userId]);
        }
        $since = (int)($_GET['since'] ?? 0);
        session_write_close(); // polling must not hold the session lock
        $payload = lx_state_payload($extras, $row, $liveId, $userId, $isAdmin, !$isAdmin);
        list($latest, $emojis) = $extras->reactionsSince($liveId, $since);
        $payload['reactions'] = ['latest' => $latest, 'items' => $emojis];
        lx_out($payload);

    case 'vote':
        if (!$canAccess) lx_out(['error' => 'Unlock this show first.'], 403);
        $ok = $extras->vote($liveId, (int)($_POST['poll_id'] ?? 0), (int)($_POST['option_id'] ?? 0), $userId);
        lx_out(['ok' => $ok, 'poll' => $extras->activePoll($liveId, $userId)], $ok ? 200 : 409);

    case 'ask':
        if (!$canAccess) lx_out(['error' => 'Unlock this show first.'], 403);
        if ($row['status'] !== 'live') lx_out(['error' => 'Questions open while the show is live.'], 409);
        $text = LiveExtras::cleanText($_POST['question'] ?? '', 300);
        if (strlen($text) < 3) lx_out(['error' => 'Please write a question (at least 3 characters).'], 422);
        if (time() - $extras->lastQuestionTime($liveId, $userId) < 20) lx_out(['error' => 'Please wait a few seconds between questions.'], 429);
        $id = $extras->ask($liveId, $userId, $text);
        lx_out(['ok' => (bool)$id, 'questions' => $extras->questions($liveId, $userId, false, 30)]);

    case 'upvote':
        if (!$canAccess) lx_out(['error' => 'Unlock this show first.'], 403);
        lx_cooldown('upvote', 1);
        $r = $extras->toggleUpvote($liveId, (int)($_POST['question_id'] ?? 0), $userId);
        if ($r === null) lx_out(['error' => 'That question is no longer available.'], 404);
        lx_out(['ok' => true, 'voted' => $r, 'questions' => $extras->questions($liveId, $userId, false, 30)]);

    case 'react':
        if (!$canAccess) lx_out(['error' => 'Unlock this show first.'], 403);
        if ($row['status'] !== 'live') lx_out(['error' => 'Reactions open while the show is live.'], 409);
        lx_cooldown('react', 1);
        $ok = $extras->addReaction($liveId, (string)($_POST['emoji'] ?? ''));
        lx_out(['ok' => $ok], $ok ? 200 : 422);

    case 'remind':
        if ($row['status'] !== 'scheduled') lx_out(['error' => 'Reminders are for upcoming shows.'], 409);
        $set = $extras->toggleReminder($liveId, $userId);
        lx_out(['ok' => true, 'reminder' => $set]);

    /* ----------------------------------------------------------- admin only */

    case 'admin_state':
        $payload = lx_state_payload($extras, $row, $liveId, 0, true, false);
        $payload['polls'] = $extras->listPolls($liveId, 8);
        $payload['questions'] = $extras->questions($liveId, 0, true, 100);
        $payload['engagement'] = $extras->engagement($liveId);
        lx_out($payload);

    case 'set_stage':
        $topic = LiveExtras::cleanText($_POST['topic'] ?? '', 255);
        $pinned = LiveExtras::cleanText($_POST['pinned'] ?? '', 500);
        $shout = LiveExtras::cleanText($_POST['shoutout'] ?? '', 255);
        $ok = $extras->setStage($liveId, $topic, $pinned, $shout, (int)($_POST['shoutout_minutes'] ?? 1));
        lx_out(['ok' => $ok, 'stage' => $extras->stage($liveId)]);

    case 'set_schedule':
        $iso = trim((string)($_POST['scheduled_at'] ?? ''));
        if ($iso === '') {
            $extras->setSchedule($liveId, null);
            lx_out(['ok' => true, 'scheduled_at' => null]);
        }
        $utc = LiveExtras::parseIsoToUtc($iso);
        if (!$utc) lx_out(['error' => 'That date and time is not valid.'], 422);
        $extras->setSchedule($liveId, $utc);
        lx_out(['ok' => true, 'scheduled_at' => LiveExtras::toIsoUtc($utc)]);

    case 'poll_create':
        $question = LiveExtras::cleanText($_POST['question'] ?? '', 300);
        $options = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : [];
        $id = $extras->createPoll($liveId, $question, $options);
        if (!$id) lx_out(['error' => 'A poll needs a question and between 2 and 6 options.'], 422);
        lx_out(['ok' => true, 'poll_id' => $id, 'polls' => $extras->listPolls($liveId, 8)]);

    case 'poll_close':
        $ok = $extras->closePoll($liveId, (int)($_POST['poll_id'] ?? 0));
        lx_out(['ok' => $ok, 'polls' => $extras->listPolls($liveId, 8)]);

    case 'question_status':
        $ok = $extras->setQuestionStatus($liveId, (int)($_POST['question_id'] ?? 0), (string)($_POST['status'] ?? ''));
        lx_out(['ok' => $ok, 'questions' => $extras->questions($liveId, 0, true, 100)]);

    case 'send_reminders':
        $sent = $extras->dispatchDueReminders(true, $liveId);
        lx_out(['ok' => true, 'sent' => $sent]);

    case 'report':
        $rows = $extras->attendance($liveId);
        $total = 0;
        foreach ($rows as $r) $total += (int)$r['seconds'];
        lx_out([
            'live' => ['id' => (int)$row['id'], 'title' => $row['title'], 'status' => $row['status']],
            'unique_viewers' => count($rows),
            'members' => count(array_filter($rows, function ($r) { return !empty($r['user_id']); })),
            'guests' => count(array_filter($rows, function ($r) { return empty($r['user_id']); })),
            'peak' => (int)$row['viewer_peak'],
            'average_minutes' => count($rows) ? round($total / count($rows) / 60, 1) : 0,
            'engagement' => $extras->engagement($liveId),
        ]);

    case 'report_csv':
        $rows = $extras->attendance($liveId);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="live-' . (int)$liveId . '-attendance.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Viewer', 'Type', 'First seen (server time)', 'Last seen (server time)', 'Minutes watched']);
        $cell = function ($v) { $v = (string)$v; return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; }; // block spreadsheet formula injection
        foreach ($rows as $r) {
            fputcsv($out, [$cell($r['username'] ?: 'Guest'), $r['user_id'] ? 'Member' : 'Guest', $r['first_seen'], $r['last_seen'], round((int)$r['seconds'] / 60, 1)]);
        }
        fclose($out);
        exit;
}
lx_out(['error' => 'Unhandled action.'], 400);
