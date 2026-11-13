<?php
session_start();
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/SiteNotification.php';

function notificationResponse(array $data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

$isAdmin = isAdmin();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $isAdmin ? 'admin' : ($userId > 0 ? 'user' : 'guest');
if ($role === 'guest') {
    if (empty($_SESSION['notification_guest_id'])) $_SESSION['notification_guest_id'] = bin2hex(random_bytes(16));
    $recipientId = (string)$_SESSION['notification_guest_id'];
} else {
    $recipientId = (string)($isAdmin ? (int)$_SESSION['admin_id'] : $userId);
}
$model = new SiteNotification();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $action = trim((string)($_GET['action'] ?? ''));
        if ($action === 'thread') {
            if ($role === 'guest') notificationResponse(['error' => 'Sign in to open messages.'], 401);
            $threadId = filter_var($_GET['thread_id'] ?? null, FILTER_VALIDATE_INT);
            $thread = $threadId ? $model->thread($threadId) : null;
            if (!$thread || (!$isAdmin && (int)$thread['user_id'] !== $userId)) {
                notificationResponse(['error' => 'That message thread was not found.'], 404);
            }
            $model->markThreadRead($threadId, $role);
            $model->markThreadNotificationsRead($threadId, $role, $recipientId);
            notificationResponse(['thread' => $thread, 'messages' => $model->messages($threadId)]);
        }

        $notifications = $model->listForRecipient($role, $recipientId);
        $threads = $role === 'guest' ? [] : ($isAdmin
            ? $model->threadsForAdmin()
            : $model->threadsForUser($userId));
        $unreadMessages = 0;
        foreach ($threads as $thread) $unreadMessages += (int)$thread['unread_count'];
        notificationResponse([
            'notifications' => $notifications,
            'threads' => $threads,
            'unread_messages' => $unreadMessages,
            'role' => $role,
            'can_message' => $role !== 'guest',
        ]);
    } catch (Throwable $error) {
        error_log('Notification panel read failed: ' . $error->getMessage());
        notificationResponse(['error' => 'Could not load notifications. Please try again.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') notificationResponse(['error' => 'GET or POST is required.'], 405);
CSRF::requireValidToken();
$input = $_POST;
$action = trim((string)($input['action'] ?? ''));

try {
    if ($action === 'clear' || $action === 'read') {
        $notificationId = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$notificationId) notificationResponse(['error' => 'A valid notification is required.'], 400);
        if (!$model->setNotificationState($notificationId, $role, $recipientId, $action === 'clear' ? 'cleared' : 'read')) {
            notificationResponse(['error' => 'Notification not found.'], 404);
        }
        notificationResponse(['ok' => true]);
    }
    if ($action === 'clear_all') {
        $model->clearAllForRecipient($role, $recipientId);
        notificationResponse(['ok' => true]);
    }
    if ($role === 'guest') notificationResponse(['error' => 'Sign in to send or reply to messages.'], 401);

    $body = trim((string)($input['body'] ?? ''));
    if ($body === '' || strlen($body) > 5000) {
        notificationResponse(['error' => 'Messages must be between 1 and 5,000 characters.'], 400);
    }

    if ($action === 'send_message') {
        if ($isAdmin) notificationResponse(['error' => 'Open a member conversation to reply, or sign in as a member to start a new message.'], 403);
        $subject = trim((string)($input['subject'] ?? ''));
        if ($subject === '' || strlen($subject) > 180) {
            notificationResponse(['error' => 'Enter a subject between 1 and 180 characters.'], 400);
        }
        $threadId = $model->createThread($userId, $subject, $body);
        $username = (string)($_SESSION['username'] ?? 'A member');
        $model->create('admin', null, 'message', 'New member message: ' . $subject, $username . ': ' . substr($body, 0, 800), null, $threadId);
        notificationResponse(['ok' => true, 'thread_id' => $threadId]);
    }

    if ($action === 'reply') {
        $threadId = filter_var($input['thread_id'] ?? null, FILTER_VALIDATE_INT);
        $thread = $threadId ? $model->thread($threadId) : null;
        if (!$thread || (!$isAdmin && (int)$thread['user_id'] !== $userId)) {
            notificationResponse(['error' => 'That message thread was not found.'], 404);
        }
        if ($thread['status'] !== 'open') notificationResponse(['error' => 'This message thread is closed.'], 409);

        $senderRole = $isAdmin ? 'admin' : 'user';
        $senderId = $isAdmin ? (int)$_SESSION['admin_id'] : $userId;
        $model->addMessage($threadId, $senderRole, $senderId, $body);
        $recipientRole = $isAdmin ? 'user' : 'admin';
        $recipientIdForNotice = $isAdmin ? (int)$thread['user_id'] : null;
        $title = $isAdmin ? 'New reply from the THE MOVIE ZONE PODCAST 256 team' : 'New member message: ' . $thread['subject'];
        $model->create(
            $recipientRole,
            $recipientIdForNotice,
            'message',
            $title,
            substr($body, 0, 800),
            null,
            $threadId
        );
        notificationResponse(['ok' => true]);
    }

    notificationResponse(['error' => 'Unsupported notification action.'], 400);
} catch (Throwable $error) {
    error_log('Notification action failed: ' . $error->getMessage());
    notificationResponse(['error' => 'The action could not be completed. Please try again.'], 500);
}
