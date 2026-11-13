<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/EventPublishFee.php';

class Payment {
    private $pdo;
    private static $logReady = false;

    public function __construct() { $this->pdo = getDB(); $this->ensureLogTable(); }

    /** payment_status_log: audit trail of every status transition. */
    private function ensureLogTable() {
        if (self::$logReady) return;
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS payment_status_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_id INT NULL,
                reference VARCHAR(80) NOT NULL,
                status VARCHAR(20) NOT NULL,
                gateway_reference VARCHAR(120) NULL,
                note VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_psl_reference (reference),
                INDEX idx_psl_payment (payment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) { error_log('payment_status_log setup failed: ' . $e->getMessage()); }
        self::$logReady = true;
    }

    private function logStatus($paymentId, $reference, $status, $trackingId = null, $note = null) {
        try {
            $this->pdo->prepare("INSERT INTO payment_status_log (payment_id, reference, status, gateway_reference, note) VALUES (?,?,?,?,?)")
                ->execute([(int)$paymentId, substr((string)$reference, 0, 80), substr((string)$status, 0, 20), $trackingId ? substr((string)$trackingId, 0, 120) : null, $note ? substr((string)$note, 0, 255) : null]);
        } catch (Throwable $e) { /* logging must never break payments */ }
    }

    public function hasMovieAccess($userId, $movieId) {
        $stmt = $this->pdo->prepare("SELECT id FROM movie_access WHERE user_id = ? AND movie_id = ? LIMIT 1");
        $stmt->execute([(int)$userId, (int)$movieId]);
        return (bool) $stmt->fetchColumn() || $this->hasActiveSubscription($userId);
    }

    public function hasActiveSubscription($userId) {
        $stmt = $this->pdo->prepare("SELECT id FROM user_subscriptions WHERE user_id = ? AND status = 'active' AND ends_at > NOW() LIMIT 1");
        $stmt->execute([(int)$userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function createPendingMoviePayment($userId, $movieId, $amount, $currency) {
        $reference = 'TMZ-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare("INSERT INTO payments (user_id, amount, currency, status, reference, payment_method, gateway, gateway_reference, movie_id) VALUES (?, ?, ?, 'pending', ?, 'pesapal', 'pesapal', NULL, ?)");
        $stmt->execute([(int)$userId, $amount, strtoupper($currency), $reference, (int)$movieId]);
        $id = (int)$this->pdo->lastInsertId();
        return array_merge(['id' => $id, 'reference' => $reference], $this->getById($id));
    }

    public function createPendingSubscriptionPayment($userId, $planId, $amount, $currency) {
        $reference = 'TMZ-SUB-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare("INSERT INTO payments (user_id, amount, currency, status, reference, payment_method, gateway, subscription_plan_id) VALUES (?, ?, ?, 'pending', ?, 'pesapal', 'pesapal', ?)");
        $stmt->execute([(int)$userId, $amount, strtoupper($currency), $reference, (int)$planId]);
        $id=(int)$this->pdo->lastInsertId(); return array_merge(['id'=>$id,'reference'=>$reference],$this->getById($id));
    }

    public function createPendingEventPayment($userId, $orderId, $amount, $currency) {
        $reference = 'TMZ-EVT-PAY-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare("INSERT INTO payments (user_id, amount, currency, status, reference, payment_method, gateway, gateway_reference, event_order_id) VALUES (?, ?, ?, 'pending', ?, 'pesapal', 'pesapal', NULL, ?)");
        $stmt->execute([(int)$userId, $amount, strtoupper($currency), $reference, (int)$orderId]);
        $id = (int)$this->pdo->lastInsertId();
        return array_merge(['id' => $id, 'reference' => $reference], $this->getById($id));
    }

    public function getById($id) { $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ?"); $stmt->execute([(int)$id]); return $stmt->fetch() ?: []; }
    public function getByReference($reference) { $stmt=$this->pdo->prepare('SELECT * FROM payments WHERE reference=? LIMIT 1'); $stmt->execute([$reference]); return $stmt->fetch() ?: []; }
    public function setGatewayReference($id, $trackingId) { $stmt = $this->pdo->prepare("UPDATE payments SET gateway_reference = ? WHERE id = ?"); return $stmt->execute([$trackingId, (int)$id]); }
    public function completeMoviePayment($reference, $trackingId, $status) {
        $payment = $this->getByReference($reference); if (!$payment) return false;
        $status = strtoupper((string)$status); $completed = in_array($status, ['COMPLETED','1'], true); $newStatus = $completed ? 'completed' : (in_array($status, ['FAILED','REVERSED','2','3'], true) ? 'failed' : 'pending');
        // Completed is terminal — a late FAILED/pending notification must not downgrade it.
        if ($payment['status'] !== 'completed') {
            $stmt = $this->pdo->prepare("UPDATE payments SET status = ?, gateway_reference = ?, paid_at = CASE WHEN ? = 'completed' THEN NOW() ELSE paid_at END WHERE id = ?"); $stmt->execute([$newStatus, $trackingId, $newStatus, $payment['id']]);
            $this->logStatus($payment['id'], $reference, $newStatus, $trackingId, 'movie/subscription payment');
        }
        // Only grant access on the first transition to completed — prevents duplicates from callback+IPN.
        if ($newStatus === 'completed' && $payment['status'] !== 'completed') {
            if (!empty($payment['movie_id'])) { $grant = $this->pdo->prepare("INSERT INTO movie_access (user_id, movie_id, payment_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE payment_id = VALUES(payment_id)"); $grant->execute([$payment['user_id'], $payment['movie_id'], $payment['id']]); }
            if (!empty($payment['subscription_plan_id'])) {
                $plan = $this->pdo->prepare("SELECT duration_months FROM subscription_plans WHERE id = ?"); $plan->execute([$payment['subscription_plan_id']]); $months=(int)$plan->fetchColumn();
                $subscription = $this->pdo->prepare("INSERT INTO user_subscriptions (user_id, subscription_plan_id, payment_id, starts_at, ends_at, status) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MONTH), 'active')");
                $subscription->execute([$payment['user_id'], $payment['subscription_plan_id'], $payment['id'], $months]);
            }
        }
        return $newStatus === 'completed';
    }

    public function createPendingGiftPayment($userId, $amount, $currency, $reference) {
        $stmt = $this->pdo->prepare("INSERT INTO payments (user_id, amount, currency, status, reference, payment_method, gateway, gateway_reference) VALUES (?, ?, ?, 'pending', ?, 'pesapal', 'pesapal', NULL)");
        $stmt->execute([(int)$userId, $amount, strtoupper($currency), $reference]);
        $id = (int)$this->pdo->lastInsertId();
        return array_merge(['id' => $id, 'reference' => $reference], $this->getById($id));
    }

    /** Confirm a gift payment. The gift_orders row carries the reference; fulfillment happens in Gift::fulfill. */
    public function completeGiftPayment($reference, $trackingId, $status) {
        $payment = $this->getByReference($reference);
        if (!$payment) return false;
        $status = strtoupper((string)$status);
        $ok = in_array($status, ['COMPLETED', '1'], true);
        $new = $ok ? 'completed' : (in_array($status, ['FAILED', 'REVERSED', '2', '3'], true) ? 'failed' : 'pending');
        if ($payment['status'] !== 'completed') {
            $this->pdo->prepare("UPDATE payments SET status = ?, gateway_reference = ?, paid_at = CASE WHEN ? = 'completed' THEN NOW() ELSE paid_at END WHERE id = ?")
                ->execute([$new, $trackingId, $new, $payment['id']]);
            $this->logStatus($payment['id'], $reference, $new, $trackingId, 'gift payment');
        }
        require_once __DIR__ . '/Gift.php';
        $gift = new Gift();
        if ($ok) { $gift->fulfill($reference, $trackingId); }
        elseif ($new === 'failed') { $gift->fail($reference); }
        return $ok;
    }

    /** Confirm an event-ticket payment. Safe to call repeatedly (callback and IPN both call it). */
    public function completeEventPayment($reference, $trackingId, $status) {
        $payment = $this->getByReference($reference);
        if (!$payment) return false;
        $status = strtoupper((string)$status);
        $ok = in_array($status, ['COMPLETED', '1'], true);
        $new = $ok ? 'completed' : (in_array($status, ['FAILED', 'REVERSED', '2', '3'], true) ? 'failed' : 'pending');
        if ($payment['status'] !== 'completed') {
            $this->pdo->prepare("UPDATE payments SET status = ?, gateway_reference = ?, paid_at = CASE WHEN ? = 'completed' THEN NOW() ELSE paid_at END WHERE id = ?")
                ->execute([$new, $trackingId, $new, $payment['id']]);
            $this->logStatus($payment['id'], $reference, $new, $trackingId, 'event payment');
        }
        if ($ok && $payment['status'] !== 'completed' && !empty($payment['event_order_id'])) {
            require_once __DIR__ . '/Event.php';
            (new Event())->completeOrder($payment['event_order_id'], $payment['id'], $trackingId);
        }
        return $ok;
    }

    /** Payment stats for the admin dashboard. */
    /** Pending payment for an event publishing fee. */
    public function createPendingEventFeePayment($userId, $feeId, $amount, $currency) {
        $reference = 'EPF-' . strtoupper(bin2hex(random_bytes(6)));
        $stmt = $this->pdo->prepare("INSERT INTO payments (user_id, amount, currency, status, reference, payment_method, gateway, gateway_reference, event_fee_id) VALUES (?, ?, ?, 'pending', ?, 'pesapal', 'pesapal', NULL, ?)");
        $stmt->execute([(int)$userId, $amount, $currency, $reference, (int)$feeId]);
        $id = (int)$this->pdo->lastInsertId();
        return array_merge(['id' => $id, 'reference' => $reference], $this->getById($id));
    }

    /** Confirm an event publishing-fee payment after gateway verification. Idempotent. */
    public function completeEventFeePayment($reference, $trackingId, $status) {
        $payment = $this->getByReference($reference);
        if (!$payment || empty($payment['event_fee_id'])) return false;
        $s = strtoupper((string)$status);
        $new = in_array($s, ['COMPLETED','1'], true) ? 'completed' : (in_array($s, ['FAILED','REVERSED','2','3'], true) ? 'failed' : 'pending');
        if ($payment['status'] !== $new) {
            $this->pdo->prepare("UPDATE payments SET status=?, gateway_reference=COALESCE(?, gateway_reference), paid_at=IF(?='completed', NOW(), paid_at) WHERE id=?")
                ->execute([$new, $trackingId, $new, $payment['id']]);
            $this->logStatus($payment['id'], $reference, $new, $trackingId, 'event fee payment');
        }
        if ($new === 'completed' && $payment['status'] !== 'completed') {
            (new EventPublishFee())->markPaid((int)$payment['event_fee_id'], (int)$payment['id']);
        } elseif (in_array($new, ['failed','reversed'], true)) {
            (new EventPublishFee())->markFailed((int)$payment['event_fee_id']);
        }
        return true;
    }

    public function stats() {
        $row = $this->pdo->query("SELECT COUNT(*) total, SUM(status='completed') completed, SUM(status='pending') pending, SUM(status='failed') failed, COALESCE(SUM(CASE WHEN status='completed' THEN amount END),0) revenue FROM payments")->fetch();
        return $row ?: ['total'=>0,'completed'=>0,'pending'=>0,'failed'=>0,'revenue'=>0];
    }

    /** Recent payments joined with user + item info for the admin table. */
    public function recent($limit = 50) {
        $stmt = $this->pdo->prepare("SELECT p.*, u.username, m.title movie_title, go.gift_name gift_title, go.recipient_name gift_recipient, go.live_id gift_live_id
            FROM payments p LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN movies m ON m.id = p.movie_id
            LEFT JOIN gift_orders go ON go.reference = p.reference
            ORDER BY p.id DESC LIMIT " . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Status history for one payment. */
    public function statusLog($paymentId) {
        $stmt = $this->pdo->prepare("SELECT * FROM payment_status_log WHERE payment_id = ? ORDER BY id DESC LIMIT 20");
        $stmt->execute([(int)$paymentId]);
        return $stmt->fetchAll();
    }
}
