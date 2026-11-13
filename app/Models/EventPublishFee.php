<?php
/**
 * EventPublishFee — fixed publishing fee per event, paid via the existing
 * Pesapal payment pipeline. Publishing an event requires a 'paid' or 'exempt'
 * fee record when the fee is enabled in settings.
 *
 * Tables (additive):
 *   event_publish_fees  one row per fee request/exemption per event
 *   event_fee_audit     who changed what (fee settings, exemptions, payments)
 * payments.event_fee_id links a payment row back to its fee record.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/SiteSetting.php';

class EventPublishFee {
    private $pdo;
    private static $ready = false;

    public function __construct() { $this->pdo = getDB(); $this->ensureSchema(); }

    private function ensureSchema() {
        if (self::$ready) return;
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS event_publish_fees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                user_id INT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                currency VARCHAR(8) NOT NULL DEFAULT 'UGX',
                status ENUM('pending','paid','exempt','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
                kind ENUM('fee','exemption') NOT NULL DEFAULT 'fee',
                payment_id INT NULL,
                reference VARCHAR(80) NULL,
                granted_by INT NULL,
                note VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME NULL,
                INDEX idx_epf_event (event_id),
                INDEX idx_epf_status (status),
                UNIQUE KEY uq_epf_event_active (event_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // The unique key above only allows ONE row per (event,status) — pending+paid coexist fine.
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS event_fee_audit (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actor_id INT NULL,
                event_id INT NULL,
                action VARCHAR(60) NOT NULL,
                detail VARCHAR(500) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_efa_event (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // Link payments -> fee record (additive column).
            $cols = $this->pdo->query("SHOW COLUMNS FROM payments LIKE 'event_fee_id'")->fetchAll();
            if (empty($cols)) $this->pdo->exec("ALTER TABLE payments ADD COLUMN event_fee_id INT NULL AFTER event_order_id, ADD INDEX idx_pay_epf (event_fee_id)");
        } catch (Throwable $e) { error_log('EventPublishFee schema: ' . $e->getMessage()); }
        self::$ready = true;
    }

    /* ---------------- settings ---------------- */

    public static function feeEnabled() {
        return (new SiteSetting())->get('event_publish_fee_enabled') === '1';
    }
    public static function feeAmount() {
        return max(0, (float)(new SiteSetting())->get('event_publish_fee_amount'));
    }
    public static function feeCurrency() {
        $c = strtoupper(trim((string)(new SiteSetting())->get('event_publish_fee_currency')));
        return in_array($c, ['UGX','USD','KES','TZS','EUR'], true) ? $c : 'UGX';
    }
    public static function saveSettings($enabled, $amount, $currency, $actorId) {
        $s = new SiteSetting();
        $s->set('event_publish_fee_enabled', $enabled ? '1' : '0');
        $s->set('event_publish_fee_amount', (string)max(0, (float)$amount));
        $s->set('event_publish_fee_currency', strtoupper($currency));
        (new self())->audit($actorId, null, 'settings', "enabled=" . ($enabled ? 1 : 0) . " amount={$amount} {$currency}");
    }

    /* ---------------- fee state ---------------- */

    /** Latest fee record for an event (any status). */
    public function forEvent($eventId) {
        $s = $this->pdo->prepare("SELECT * FROM event_publish_fees WHERE event_id = ? ORDER BY id DESC LIMIT 1");
        $s->execute([(int)$eventId]);
        return $s->fetch() ?: null;
    }

    /** Can this event be published right now? */
    public function canPublish($eventId) {
        if (!self::feeEnabled()) return ['ok' => true, 'reason' => 'fees disabled'];
        $fee = $this->forEvent($eventId);
        if ($fee && in_array($fee['status'], ['paid', 'exempt'], true)) return ['ok' => true, 'reason' => $fee['status']];
        return ['ok' => false, 'reason' => 'publishing fee required', 'fee' => $fee];
    }

    /** Create (or return existing) pending fee for an event. Idempotent. */
    public function ensureFee($eventId, $userId = null) {
        $existing = $this->forEvent($eventId);
        if ($existing && in_array($existing['status'], ['pending', 'paid', 'exempt'], true)) return $existing;
        $amount = self::feeAmount(); $currency = self::feeCurrency();
        $ref = 'EVTFEE-' . strtoupper(bin2hex(random_bytes(5)));
        $this->pdo->prepare("INSERT INTO event_publish_fees (event_id, user_id, amount, currency, status, kind, reference) VALUES (?,?,?,?, 'pending','fee',?)")
            ->execute([(int)$eventId, $userId ? (int)$userId : null, $amount, $currency, $ref]);
        $this->audit(null, $eventId, 'fee_created', "{$amount} {$currency} ref={$ref}");
        return $this->forEvent($eventId);
    }

    /** Admin grants a free-publish exemption for an event. */
    public function grantExemption($eventId, $actorId, $note = '') {
        // Close any pending fee first so it can't later be paid/activated.
        $this->pdo->prepare("UPDATE event_publish_fees SET status='cancelled' WHERE event_id=? AND status='pending'")->execute([(int)$eventId]);
        $this->pdo->prepare("INSERT INTO event_publish_fees (event_id, amount, currency, status, kind, granted_by, note) VALUES (?,0,'UGX','exempt','exemption',?,?)")
            ->execute([(int)$eventId, (int)$actorId, substr($note, 0, 250)]);
        $this->audit($actorId, $eventId, 'exemption_granted', $note);
        return true;
    }

    public function revokeExemption($eventId, $actorId) {
        $this->pdo->prepare("UPDATE event_publish_fees SET status='cancelled' WHERE event_id=? AND status='exempt'")->execute([(int)$eventId]);
        $this->audit($actorId, $eventId, 'exemption_revoked', '');
        return true;
    }

    /** Called by Payment::completeEventFeePayment after gateway verification. */
    public function markPaid($feeId, $paymentId) {
        $this->pdo->prepare("UPDATE event_publish_fees SET status='paid', payment_id=?, paid_at=NOW() WHERE id=? AND status='pending'")
            ->execute([(int)$paymentId, (int)$feeId]);
        $fee = $this->pdo->prepare("SELECT event_id FROM event_publish_fees WHERE id=?"); $fee->execute([(int)$feeId]);
        $ev = $fee->fetchColumn();
        if ($ev) $this->audit(null, (int)$ev, 'fee_paid', "payment_id={$paymentId}");
        return true;
    }

    public function markFailed($feeId) {
        $this->pdo->prepare("UPDATE event_publish_fees SET status='failed' WHERE id=? AND status='pending'")->execute([(int)$feeId]);
    }

    /* ---------------- reporting ---------------- */

    public function all($limit = 100, $offset = 0, $status = '') {
        $sql = "SELECT f.*, e.title AS event_title, u.username AS payer FROM event_publish_fees f
                LEFT JOIN events e ON e.id = f.event_id LEFT JOIN users u ON u.id = f.user_id";
        $args = [];
        if ($status !== '' && in_array($status, ['pending','paid','exempt','failed','refunded','cancelled'], true)) { $sql .= " WHERE f.status = ?"; $args[] = $status; }
        $sql .= " ORDER BY f.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $s = $this->pdo->prepare($sql); $s->execute($args);
        return $s->fetchAll();
    }

    public function stats() {
        $r = $this->pdo->query("SELECT
            SUM(status='paid') paid_count, COALESCE(SUM(IF(status='paid',amount,0)),0) revenue,
            SUM(status='pending') pending_count, SUM(status='exempt') exempt_count,
            SUM(status='failed') failed_count, COALESCE(SUM(IF(status='refunded',amount,0)),0) refunded
            FROM event_publish_fees")->fetch();
        return $r ?: [];
    }

    public function audit($actorId, $eventId, $action, $detail = '') {
        try {
            $this->pdo->prepare("INSERT INTO event_fee_audit (actor_id, event_id, action, detail) VALUES (?,?,?,?)")
                ->execute([$actorId ? (int)$actorId : null, $eventId ? (int)$eventId : null, $action, substr((string)$detail, 0, 490)]);
        } catch (Throwable $e) { /* audit must never break the main flow */ }
    }

    public function auditTrail($eventId = null, $limit = 100) {
        if ($eventId) {
            $s = $this->pdo->prepare("SELECT a.*, u.username AS actor FROM event_fee_audit a LEFT JOIN users u ON u.id=a.actor_id WHERE a.event_id=? ORDER BY a.id DESC LIMIT " . (int)$limit);
            $s->execute([(int)$eventId]);
        } else {
            $s = $this->pdo->query("SELECT a.*, u.username AS actor FROM event_fee_audit a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT " . (int)$limit);
        }
        return $s->fetchAll();
    }
}
