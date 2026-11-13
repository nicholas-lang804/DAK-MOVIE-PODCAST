<?php

require_once __DIR__ . '/../../config/database.php';

class SiteNotification {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    public function create($recipientRole, $recipientId, $category, $title, $body, $targetUrl = null, $threadId = null) {
        $allowedRoles = ['admin', 'user', 'all'];
        if (!in_array($recipientRole, $allowedRoles, true)) {
            throw new InvalidArgumentException('Unsupported notification recipient.');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO site_notifications (recipient_role, recipient_id, category, title, body, target_url, thread_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $recipientRole,
            $recipientId === null ? null : (int)$recipientId,
            substr(trim((string)$category), 0, 32),
            substr(trim((string)$title), 0, 180),
            substr(trim((string)$body), 0, 1000),
            $targetUrl === null ? null : substr((string)$targetUrl, 0, 500),
            $threadId === null ? null : (int)$threadId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listForRecipient($role, $recipientId, $limit = 50) {
        $limit = max(1, min(100, (int)$limit));
        $stmt = $this->pdo->prepare(
            "SELECT n.id, n.category, n.title, n.body, n.target_url, n.thread_id, n.created_at,
                    COALESCE(s.read_at, '') AS read_at
             FROM site_notifications n
             LEFT JOIN site_notification_states s
               ON s.notification_id = n.id AND s.recipient_role = ? AND s.recipient_id = ?
             WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND (
                    (n.recipient_role = ? AND (n.recipient_id IS NULL OR n.recipient_id = ?))
                    OR n.recipient_role = 'all'
               )
               AND s.cleared_at IS NULL
             ORDER BY n.id DESC LIMIT " . $limit
        );
        $stmt->execute([$role, (string)$recipientId, $role, (int)$recipientId]);
        return $stmt->fetchAll();
    }

    public function setNotificationState($notificationId, $role, $recipientId, $state) {
        if (!in_array($role, ['admin', 'user', 'guest'], true) || !in_array($state, ['read', 'cleared'], true)) {
            throw new InvalidArgumentException('Unsupported notification state.');
        }
        $column = $state === 'read' ? 'read_at' : 'cleared_at';
        $check = $this->pdo->prepare(
            "SELECT 1 FROM site_notifications n
             WHERE n.id = ? AND (
                (n.recipient_role = ? AND (n.recipient_id IS NULL OR n.recipient_id = ?))
                OR n.recipient_role = 'all'
             ) LIMIT 1"
        );
        $check->execute([(int)$notificationId, $role, (int)$recipientId]);
        if (!$check->fetchColumn()) return false;
        $stmt = $this->pdo->prepare(
            "INSERT INTO site_notification_states (notification_id, recipient_role, recipient_id, {$column})
             SELECT n.id, ?, ?, NOW() FROM site_notifications n
             WHERE n.id = ? AND (
                (n.recipient_role = ? AND (n.recipient_id IS NULL OR n.recipient_id = ?))
                OR n.recipient_role = 'all'
             )
             ON DUPLICATE KEY UPDATE {$column} = COALESCE({$column}, NOW())"
        );
        $stmt->execute([$role, (string)$recipientId, (int)$notificationId, $role, (int)$recipientId]);
        return true;
    }

    public function clearAllForRecipient($role, $recipientId) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO site_notification_states (notification_id, recipient_role, recipient_id, cleared_at)
             SELECT n.id, ?, ?, NOW() FROM site_notifications n
             WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND ((n.recipient_role = ? AND (n.recipient_id IS NULL OR n.recipient_id = ?)) OR n.recipient_role = 'all')
             ON DUPLICATE KEY UPDATE cleared_at = COALESCE(cleared_at, NOW())"
        );
        return $stmt->execute([$role, (string)$recipientId, $role, (int)$recipientId]);
    }

    public function createThread($userId, $subject, $body) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO site_message_threads (user_id, subject) VALUES (?, ?)');
            $stmt->execute([(int)$userId, substr(trim((string)$subject), 0, 180)]);
            $threadId = (int)$this->pdo->lastInsertId();
            $this->addMessage($threadId, 'user', (int)$userId, $body);
            $this->pdo->commit();
            return $threadId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function addMessage($threadId, $senderRole, $senderId, $body) {
        if (!in_array($senderRole, ['admin', 'user'], true)) {
            throw new InvalidArgumentException('Unsupported message sender.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO site_messages (thread_id, sender_role, sender_id, body) VALUES (?, ?, ?, ?)');
        $stmt->execute([(int)$threadId, $senderRole, (int)$senderId, trim((string)$body)]);
        $messageId = (int)$this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('UPDATE site_message_threads SET last_message_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([(int)$threadId]);
        return $messageId;
    }

    public function thread($threadId) {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, u.username, u.email FROM site_message_threads t
             JOIN users u ON u.id = t.user_id WHERE t.id = ? LIMIT 1'
        );
        $stmt->execute([(int)$threadId]);
        return $stmt->fetch() ?: null;
    }

    public function threadsForUser($userId, $limit = 30) {
        $limit = max(1, min(50, (int)$limit));
        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.subject, t.status, t.last_message_at,
                    (SELECT m.body FROM site_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                    (SELECT COUNT(*) FROM site_messages m WHERE m.thread_id = t.id AND m.sender_role = 'admin' AND m.read_at IS NULL) AS unread_count
             FROM site_message_threads t WHERE t.user_id = ? ORDER BY t.last_message_at DESC LIMIT " . $limit
        );
        $stmt->execute([(int)$userId]);
        return $stmt->fetchAll();
    }

    public function threadsForAdmin($limit = 50) {
        $limit = max(1, min(100, (int)$limit));
        $stmt = $this->pdo->query(
            "SELECT t.id, t.user_id, t.subject, t.status, t.last_message_at, u.username, u.email,
                    (SELECT m.body FROM site_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                    (SELECT COUNT(*) FROM site_messages m WHERE m.thread_id = t.id AND m.sender_role = 'user' AND m.read_at IS NULL) AS unread_count
             FROM site_message_threads t JOIN users u ON u.id = t.user_id
             ORDER BY t.last_message_at DESC LIMIT " . $limit
        );
        return $stmt->fetchAll();
    }

    public function messages($threadId, $limit = 100) {
        $limit = max(1, min(200, (int)$limit));
        $stmt = $this->pdo->prepare(
            'SELECT id, thread_id, sender_role, sender_id, body, created_at, read_at
             FROM site_messages WHERE thread_id = ? ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute([(int)$threadId]);
        return array_reverse($stmt->fetchAll());
    }

    public function markThreadRead($threadId, $readerRole) {
        $otherRole = $readerRole === 'admin' ? 'user' : 'admin';
        $stmt = $this->pdo->prepare(
            'UPDATE site_messages SET read_at = NOW() WHERE thread_id = ? AND sender_role = ? AND read_at IS NULL'
        );
        return $stmt->execute([(int)$threadId, $otherRole]);
    }

    public function markThreadNotificationsRead($threadId, $role, $recipientId) {
        $stmt = $this->pdo->prepare('SELECT id FROM site_notifications WHERE thread_id = ? ORDER BY id ASC');
        $stmt->execute([(int)$threadId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $notificationId) {
            $this->setNotificationState((int)$notificationId, $role, $recipientId, 'read');
        }
    }
}
