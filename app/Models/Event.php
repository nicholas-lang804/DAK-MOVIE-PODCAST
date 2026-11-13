<?php
/**
 * Event Model
 * Handles event database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Event {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
        $this->ensureSchema();
    }

    /**
     * Find event by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, ma.storage_path as poster_path
            FROM events e
            LEFT JOIN media_assets ma ON e.poster_media_id = ma.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find event by slug
     * @param string $slug
     * @return array|null
     */
    /** Events created by a specific organiser (user). */
    public function getByOrganiser($userId, $limit = 100) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, ma.storage_path AS poster_path,
                (SELECT COUNT(*) FROM event_ticket_types t WHERE t.event_id = e.id AND t.status = 'active') AS ticket_count,
                (SELECT COUNT(*) FROM event_tickets tk WHERE tk.event_id = e.id AND tk.status = 'valid') AS tickets_sold
            FROM events e
            LEFT JOIN media_assets ma ON e.poster_media_id = ma.id
            WHERE e.user_id = ?
            ORDER BY e.event_date IS NULL, e.event_date DESC, e.id DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute([(int)$userId]);
        return $stmt->fetchAll();
    }

    /** Single event only if owned by this organiser. */
    public function findByIdForOrganiser($id, $userId) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, ma.storage_path AS poster_path
            FROM events e LEFT JOIN media_assets ma ON e.poster_media_id = ma.id
            WHERE e.id = ? AND e.user_id = ?
        ");
        $stmt->execute([(int)$id, (int)$userId]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug($slug) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, ma.storage_path as poster_path
            FROM events e
            LEFT JOIN media_assets ma ON e.poster_media_id = ma.id
            WHERE e.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /**
     * Get upcoming events
     * @param int $limit
     * @return array
     */
    public function getUpcoming($limit = 10) {
        $limit = max(1, (int)$limit);
        $stmt = $this->pdo->query("
            SELECT e.*, ma.storage_path as poster_path,
                (SELECT MIN(t.price) FROM event_ticket_types t WHERE t.event_id = e.id AND t.status = 'active') AS min_price,
                (SELECT t.currency FROM event_ticket_types t WHERE t.event_id = e.id AND t.status = 'active' ORDER BY t.price ASC, t.id ASC LIMIT 1) AS price_currency,
                (SELECT COUNT(*) FROM event_discount_rules r WHERE r.event_id = e.id AND r.status = 'active'
                    AND (r.starts_at IS NULL OR r.starts_at <= NOW()) AND (r.ends_at IS NULL OR r.ends_at >= NOW())) AS active_discounts
            FROM events e
            LEFT JOIN media_assets ma ON e.poster_media_id = ma.id
            WHERE e.status = 'published' AND e.event_date >= NOW()
            ORDER BY e.event_date ASC
            LIMIT $limit
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get past events
     * @param int $limit
     * @return array
     */
    public function getPast($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, ma.storage_path as poster_path
            FROM events e
            LEFT JOIN media_assets ma ON e.poster_media_id = ma.id
            WHERE e.status = 'past' OR (e.status = 'published' AND e.event_date < NOW())
            ORDER BY e.event_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get all events with pagination
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll($limit = 20, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, ma.storage_path as poster_path,
                (SELECT MIN(t.price) FROM event_ticket_types t WHERE t.event_id = e.id AND t.status = 'active') AS min_price,
                (SELECT t.currency FROM event_ticket_types t WHERE t.event_id = e.id AND t.status = 'active' ORDER BY t.price ASC, t.id ASC LIMIT 1) AS price_currency
            FROM events e
            LEFT JOIN media_assets ma ON e.poster_media_id = ma.id
            ORDER BY e.event_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Create new event
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO events (title, slug, description, location, contact_name, contact_phone, contact_email, event_date,
                    start_time, end_time, venue_name, venue_address, category, sales_open_at, sales_close_at,
                    poster_media_id, registration_url, status, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['title'],
                $data['slug'],
                $data['description'] ?? null,
                $data['location'] ?? null,
                $data['contact_name'] ?? null,
                $data['contact_phone'] ?? null,
                $data['contact_email'] ?? null,
                $data['event_date'] ?? null,
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                $data['venue_name'] ?? null,
                $data['venue_address'] ?? null,
                $data['category'] ?? null,
                $data['sales_open_at'] ?? null,
                $data['sales_close_at'] ?? null,
                $data['poster_media_id'] ?? null,
                $data['registration_url'] ?? null,
                $data['status'] ?? 'draft',
                $data['user_id'] ?? null
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Event creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update event
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }

            $values[] = $id;
            $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Event update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete event
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            if ($this->hasPaidOrders($id)) return false; // keep sales history: archive instead
            $id = (int)$id;
            $this->pdo->beginTransaction();
            foreach (['event_tickets', 'event_orders', 'event_ticket_types', 'event_discount_rules'] as $table) {
                if ($table === 'event_orders') {
                    $this->pdo->prepare("DELETE i FROM event_order_items i JOIN event_orders o ON o.id = i.order_id WHERE o.event_id = ?")->execute([$id]);
                }
                $this->pdo->prepare("DELETE FROM $table WHERE event_id = ?")->execute([$id]);
            }
            $this->pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log("Event deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total event count
     * @return int
     */
    public function getCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM events");
        return $stmt->fetch()['count'];
    }

    /* ------------------------------------------------------------------
     * TICKETING
     * ------------------------------------------------------------------ */

    /** Format money for display (UGX has no decimals). */
    public static function money($currency, $amount) {
        $currency = strtoupper($currency ?: 'UGX');
        $decimals = in_array($currency, ['UGX', 'KES', 'TZS', 'RWF'], true) ? 0 : 2;
        return $currency . ' ' . number_format((float)$amount, $decimals);
    }

    /** Convert a datetime-local value (2026-11-05T18:30) to a MySQL DATETIME or null. */
    public static function normalizeDate($value) {
        $value = trim((string)$value);
        if ($value === '') return null;
        $value = str_replace('T', ' ', $value);
        if (strlen($value) === 16) $value .= ':00';
        return strtotime($value) === false ? null : $value;
    }

    /** Create ticketing tables/columns if they are missing (safe to run repeatedly). */
    private function ensureSchema() {
        static $done = false;
        if ($done) return;
        $useSession = session_status() === PHP_SESSION_ACTIVE;
        if ($useSession && !empty($_SESSION['tmz_event_schema_v2'])) { $done = true; return; }

        try {
            $tail = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS event_ticket_types (
                id INT AUTO_INCREMENT PRIMARY KEY, event_id INT NOT NULL, name VARCHAR(80) NOT NULL, code VARCHAR(40) NOT NULL,
                price DECIMAL(12,2) NOT NULL DEFAULT 0, currency VARCHAR(3) NOT NULL DEFAULT 'UGX', capacity INT NULL,
                sold_count INT NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY event_ticket_code (event_id, code)) $tail");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS event_discount_rules (
                id INT AUTO_INCREMENT PRIMARY KEY, event_id INT NOT NULL, code VARCHAR(60) NOT NULL, eligible_label VARCHAR(120) NOT NULL,
                discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0, starts_at DATETIME NULL, ends_at DATETIME NULL,
                channel ENUM('online','physical','both') NOT NULL DEFAULT 'both', status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY event_discount_code (event_id, code)) $tail");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS event_orders (
                id INT AUTO_INCREMENT PRIMARY KEY, event_id INT NOT NULL, user_id INT NULL, reference VARCHAR(100) NOT NULL UNIQUE,
                subtotal DECIMAL(12,2) NOT NULL, discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0, total DECIMAL(12,2) NOT NULL,
                currency VARCHAR(3) NOT NULL, discount_code VARCHAR(60) NULL, channel ENUM('online','physical') NOT NULL DEFAULT 'online',
                status ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'pending', payment_id INT NULL,
                buyer_name VARCHAR(160) NULL, buyer_phone VARCHAR(60) NULL, buyer_email VARCHAR(190) NULL, sold_by_admin_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, paid_at DATETIME NULL, INDEX idx_event_order_status (event_id, status)) $tail");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS event_order_items (
                id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, ticket_type_id INT NOT NULL, quantity INT NOT NULL,
                unit_price DECIMAL(12,2) NOT NULL) $tail");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS event_tickets (
                id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, event_id INT NOT NULL, ticket_type_id INT NOT NULL, user_id INT NULL,
                ticket_code VARCHAR(80) NOT NULL UNIQUE, status ENUM('valid','used','cancelled') NOT NULL DEFAULT 'valid',
                issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, checked_in_at DATETIME NULL) $tail");

            $addColumn = function ($table, $column, $definition) {
                $q = $this->pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                if (!$q->fetch()) $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            };
            $addColumn('events', 'contact_name', 'VARCHAR(160) NULL');
            $addColumn('events', 'contact_phone', 'VARCHAR(60) NULL');
            $addColumn('events', 'contact_email', 'VARCHAR(190) NULL');
            $addColumn('events', 'user_id', 'INT NULL');           // organiser who created it
            $addColumn('events', 'category', 'VARCHAR(80) NULL');
            $addColumn('events', 'venue_name', 'VARCHAR(160) NULL');
            $addColumn('events', 'venue_address', 'VARCHAR(255) NULL');
            $addColumn('events', 'start_time', 'TIME NULL');
            $addColumn('events', 'end_time', 'TIME NULL');
            $addColumn('events', 'sales_open_at', 'DATETIME NULL');
            $addColumn('events', 'sales_close_at', 'DATETIME NULL');
            $addColumn('event_ticket_types', 'description', 'VARCHAR(255) NULL');
            $addColumn('event_ticket_types', 'sales_open_at', 'DATETIME NULL');
            $addColumn('event_ticket_types', 'sales_close_at', 'DATETIME NULL');
            $addColumn('payments', 'event_order_id', 'INT NULL');
            $addColumn('event_orders', 'buyer_name', 'VARCHAR(160) NULL');
            $addColumn('event_orders', 'buyer_phone', 'VARCHAR(60) NULL');
            $addColumn('event_orders', 'buyer_email', 'VARCHAR(190) NULL');
            $addColumn('event_orders', 'sold_by_admin_id', 'INT NULL');
            $addColumn('event_tickets', 'checked_in_at', 'DATETIME NULL');

            // Physical (walk-in) buyers have no website account.
            foreach (['event_orders', 'event_tickets'] as $table) {
                try {
                    $col = $this->pdo->query("SHOW COLUMNS FROM `$table` LIKE 'user_id'")->fetch();
                    if ($col && strtoupper($col['Null']) === 'NO') $this->pdo->exec("ALTER TABLE `$table` MODIFY user_id INT NULL");
                } catch (Throwable $e) { error_log('Event schema (user_id): ' . $e->getMessage()); }
            }
            $done = true;
            if ($useSession) $_SESSION['tmz_event_schema_v2'] = true;
        } catch (Throwable $e) {
            error_log('Event schema check failed: ' . $e->getMessage());
        }
    }

    /** Ticket levels for an event. */
    public function getTicketTypes($eventId, $onlyActive = true) {
        $sql = 'SELECT * FROM event_ticket_types WHERE event_id = ?' . ($onlyActive ? " AND status = 'active'" : '') . ' ORDER BY price ASC, id ASC';
        $s = $this->pdo->prepare($sql);
        $s->execute([(int)$eventId]);
        return $s->fetchAll();
    }

    public function getTicketType($eventId, $ticketTypeId) {
        $s = $this->pdo->prepare("SELECT * FROM event_ticket_types WHERE id = ? AND event_id = ? AND status = 'active'");
        $s->execute([(int)$ticketTypeId, (int)$eventId]);
        return $s->fetch() ?: null;
    }

    /**
     * Save ticket levels. Existing levels (matched by code) are updated so that sales history
     * and sold counts are preserved; levels removed from the form are deactivated, never deleted.
     */
    public function saveTicketTypes($eventId, $tickets) {
        $eventId = (int)$eventId;
        $keep = [];
        $upsert = $this->pdo->prepare("INSERT INTO event_ticket_types (event_id, name, code, price, currency, capacity, description, sales_open_at, sales_close_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), currency = VALUES(currency), capacity = VALUES(capacity),
                description = VALUES(description), sales_open_at = VALUES(sales_open_at), sales_close_at = VALUES(sales_close_at), status = 'active'");
        foreach ((array)$tickets as $ticket) {
            $name = trim($ticket['name'] ?? '');
            if ($name === '') continue;
            $code = strtoupper(trim($ticket['code'] ?? ''));
            if ($code === '') $code = strtoupper($name);
            $code = trim(preg_replace('/[^A-Z0-9]+/', '_', $code), '_') ?: 'LEVEL';
            if (isset($keep[$code])) continue;
            $currency = strtoupper(substr(trim($ticket['currency'] ?? 'UGX'), 0, 3)) ?: 'UGX';
            $capacity = ($ticket['capacity'] ?? '') === '' ? null : max(1, (int)$ticket['capacity']);
            $upsert->execute([$eventId, mb_substr($name, 0, 80), $code, max(0, (float)($ticket['price'] ?? 0)), $currency, $capacity,
                isset($ticket['description']) ? mb_substr(trim($ticket['description']), 0, 250) : null,
                self::normalizeDate($ticket['sales_open_at'] ?? ''), self::normalizeDate($ticket['sales_close_at'] ?? '')]);
            $keep[$code] = true;
        }
        $existing = $this->pdo->prepare('SELECT id, code FROM event_ticket_types WHERE event_id = ?');
        $existing->execute([$eventId]);
        $deactivate = $this->pdo->prepare("UPDATE event_ticket_types SET status = 'inactive' WHERE id = ?");
        foreach ($existing->fetchAll() as $row) {
            if (!isset($keep[$row['code']])) $deactivate->execute([(int)$row['id']]);
        }
        return true;
    }

    /** All discount rules saved for an event (admin view). */
    public function getDiscountRules($eventId, $channel = null, $onlyCurrent = false) {
        $sql = "SELECT * FROM event_discount_rules WHERE event_id = ? AND status = 'active'";
        $params = [(int)$eventId];
        if ($channel) { $sql .= " AND (channel = ? OR channel = 'both')"; $params[] = $channel; }
        if ($onlyCurrent) $sql .= ' AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())';
        $s = $this->pdo->prepare($sql . ' ORDER BY discount_percent DESC, id ASC');
        $s->execute($params);
        return $s->fetchAll();
    }

    /** Find a discount rule that is valid right now for a code and channel (a code is always required). */
    public function getDiscountRule($eventId, $code, $channel = 'online') {
        $code = strtoupper(trim((string)$code));
        if ($code === '') return null;
        $s = $this->pdo->prepare("SELECT * FROM event_discount_rules
            WHERE event_id = ? AND code = ? AND status = 'active' AND (channel = ? OR channel = 'both')
              AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) LIMIT 1");
        $s->execute([(int)$eventId, $code, $channel]);
        return $s->fetch() ?: null;
    }

    /** Save discount rules: upsert by code, deactivate removed ones. */
    public function saveDiscountRules($eventId, $rules) {
        $eventId = (int)$eventId;
        $keep = [];
        $upsert = $this->pdo->prepare("INSERT INTO event_discount_rules (event_id, code, eligible_label, discount_percent, starts_at, ends_at, channel, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE eligible_label = VALUES(eligible_label), discount_percent = VALUES(discount_percent),
                starts_at = VALUES(starts_at), ends_at = VALUES(ends_at), channel = VALUES(channel), status = 'active'");
        foreach ((array)$rules as $rule) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]+/', '', trim($rule['code'] ?? '')));
            $percent = min(100, max(0, (float)($rule['discount_percent'] ?? 0)));
            if ($code === '' || $percent <= 0 || isset($keep[$code])) continue;
            $channel = in_array($rule['channel'] ?? 'both', ['online', 'physical', 'both'], true) ? $rule['channel'] : 'both';
            $starts = self::normalizeDate($rule['starts_at'] ?? '');
            $ends = self::normalizeDate($rule['ends_at'] ?? '');
            if ($starts && $ends && strtotime($starts) > strtotime($ends)) { $tmp = $starts; $starts = $ends; $ends = $tmp; }
            $label = trim($rule['eligible_label'] ?? '') ?: 'Eligible guests';
            $upsert->execute([$eventId, $code, mb_substr($label, 0, 120), $percent, $starts, $ends, $channel]);
            $keep[$code] = true;
        }
        $existing = $this->pdo->prepare('SELECT id, code FROM event_discount_rules WHERE event_id = ?');
        $existing->execute([$eventId]);
        $off = $this->pdo->prepare("UPDATE event_discount_rules SET status = 'inactive' WHERE id = ?");
        foreach ($existing->fetchAll() as $row) {
            if (!isset($keep[$row['code']])) $off->execute([(int)$row['id']]);
        }
        return true;
    }

    /** Work out price, discount and total for a purchase. Throws RuntimeException with a friendly message. */
    public function quote($eventId, $ticketTypeId, $quantity, $discountCode = '', $channel = 'online') {
        $event = $this->findById($eventId);
        if (!$event || $event['status'] !== 'published') throw new RuntimeException('This event is not open for ticket sales.');
        if ($event['event_date'] && strtotime($event['event_date']) < time()) throw new RuntimeException('This event has already taken place.');

        $ticket = $this->getTicketType($eventId, $ticketTypeId);
        if (!$ticket) throw new RuntimeException('That ticket level is unavailable.');
        $quantity = max(1, min(20, (int)$quantity));
        if ($ticket['capacity'] !== null) {
            $left = (int)$ticket['capacity'] - (int)$ticket['sold_count'];
            if ($left <= 0) throw new RuntimeException($ticket['name'] . ' tickets are sold out.');
            if ($quantity > $left) throw new RuntimeException('Only ' . $left . ' ' . $ticket['name'] . ' ticket(s) are left.');
        }

        $rule = null;
        if (trim((string)$discountCode) !== '') {
            $rule = $this->getDiscountRule($eventId, $discountCode, $channel);
            if (!$rule) throw new RuntimeException('That discount code is invalid, has expired, or is not valid for this kind of sale.');
        }
        $decimals = in_array($ticket['currency'], ['UGX', 'KES', 'TZS', 'RWF'], true) ? 0 : 2;
        $subtotal = round((float)$ticket['price'] * $quantity, $decimals);
        $discount = $rule ? round($subtotal * ((float)$rule['discount_percent'] / 100), $decimals) : 0.0;

        return ['event' => $event, 'ticket' => $ticket, 'quantity' => $quantity, 'rule' => $rule,
                'subtotal' => $subtotal, 'discount' => $discount, 'total' => max(0, $subtotal - $discount)];
    }

    /** Create a pending online order. */
    public function createOrder($eventId, $userId, $ticketTypeId, $quantity, $discountCode = '') {
        $q = $this->quote($eventId, $ticketTypeId, $quantity, $discountCode, 'online');
        $reference = 'TMZ-EVT-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("INSERT INTO event_orders (event_id, user_id, reference, subtotal, discount_amount, total, currency, discount_code, channel, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'online', 'pending')")
                ->execute([(int)$eventId, (int)$userId, $reference, $q['subtotal'], $q['discount'], $q['total'], $q['ticket']['currency'], $q['rule']['code'] ?? null]);
            $orderId = (int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO event_order_items (order_id, ticket_type_id, quantity, unit_price) VALUES (?, ?, ?, ?)')
                ->execute([$orderId, (int)$ticketTypeId, $q['quantity'], $q['ticket']['price']]);
            $this->pdo->commit();
        } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }

        return ['id' => $orderId, 'reference' => $reference, 'amount' => $q['total'], 'currency' => $q['ticket']['currency'],
                'event_title' => $q['event']['title'], 'quantity' => $q['quantity'], 'ticket_type_id' => (int)$ticketTypeId];
    }

    /**
     * Mark an order paid and issue its tickets. Safe to call many times (callback + IPN):
     * an order that is already paid is left untouched, so tickets are never duplicated.
     */
    public function completeOrder($orderId, $paymentId = null, $trackingId = null) {
        $this->pdo->beginTransaction();
        try {
            $s = $this->pdo->prepare('SELECT * FROM event_orders WHERE id = ? FOR UPDATE');
            $s->execute([(int)$orderId]);
            $order = $s->fetch();
            if (!$order) { $this->pdo->rollBack(); return false; }
            if ($order['status'] === 'paid') { $this->pdo->commit(); return true; }
            if ($order['status'] !== 'pending') { $this->pdo->rollBack(); return false; }

            $this->pdo->prepare("UPDATE event_orders SET status = 'paid', payment_id = ?, paid_at = NOW() WHERE id = ?")
                ->execute([$paymentId ? (int)$paymentId : null, (int)$orderId]);

            $items = $this->pdo->prepare('SELECT * FROM event_order_items WHERE order_id = ?');
            $items->execute([(int)$orderId]);
            $bump = $this->pdo->prepare('UPDATE event_ticket_types SET sold_count = sold_count + ? WHERE id = ?');
            $issue = $this->pdo->prepare('INSERT INTO event_tickets (order_id, event_id, ticket_type_id, user_id, ticket_code) VALUES (?, ?, ?, ?, ?)');
            foreach ($items->fetchAll() as $item) {
                $bump->execute([(int)$item['quantity'], (int)$item['ticket_type_id']]);
                for ($i = 0; $i < (int)$item['quantity']; $i++) {
                    $issue->execute([(int)$order['id'], (int)$order['event_id'], (int)$item['ticket_type_id'],
                        $order['user_id'] ? (int)$order['user_id'] : null, 'TMZ-' . strtoupper(bin2hex(random_bytes(5)))]);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Admin records an in-person (physical) sale, optionally with a discount code. */
    public function recordPhysicalSale($eventId, $ticketTypeId, $quantity, $buyer, $discountCode, $adminId) {
        $q = $this->quote($eventId, $ticketTypeId, $quantity, $discountCode, 'physical');
        $reference = 'TMZ-POS-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $this->pdo->prepare("INSERT INTO event_orders (event_id, user_id, reference, subtotal, discount_amount, total, currency, discount_code, channel, status, buyer_name, buyer_phone, buyer_email, sold_by_admin_id)
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 'physical', 'pending', ?, ?, ?, ?)")
            ->execute([(int)$eventId, $reference, $q['subtotal'], $q['discount'], $q['total'], $q['ticket']['currency'], $q['rule']['code'] ?? null,
                trim($buyer['name'] ?? '') ?: 'Walk-in buyer', trim($buyer['phone'] ?? '') ?: null, trim($buyer['email'] ?? '') ?: null, $adminId ? (int)$adminId : null]);
        $orderId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO event_order_items (order_id, ticket_type_id, quantity, unit_price) VALUES (?, ?, ?, ?)')
            ->execute([$orderId, (int)$ticketTypeId, $q['quantity'], $q['ticket']['price']]);
        $this->completeOrder($orderId);
        return ['order_id' => $orderId, 'reference' => $reference, 'total' => $q['total'], 'currency' => $q['ticket']['currency'],
                'quantity' => $q['quantity'], 'discount' => $q['discount']];
    }

    /** Recent orders for the admin sales panel. */
    public function getOrders($eventId, $limit = 30) {
        $limit = max(1, (int)$limit);
        $s = $this->pdo->prepare("SELECT o.*, u.username, u.email AS user_email, t.name AS ticket_name, i.quantity
            FROM event_orders o
            LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN event_order_items i ON i.order_id = o.id
            LEFT JOIN event_ticket_types t ON t.id = i.ticket_type_id
            WHERE o.event_id = ? ORDER BY o.id DESC LIMIT $limit");
        $s->execute([(int)$eventId]);
        return $s->fetchAll();
    }

    /** Sales totals for the admin panel. */
    public function getSalesSummary($eventId) {
        $s = $this->pdo->prepare("SELECT channel, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue, COALESCE(SUM(discount_amount),0) AS discounts
            FROM event_orders WHERE event_id = ? AND status = 'paid' GROUP BY channel");
        $s->execute([(int)$eventId]);
        $summary = ['online' => ['orders' => 0, 'revenue' => 0, 'discounts' => 0], 'physical' => ['orders' => 0, 'revenue' => 0, 'discounts' => 0]];
        foreach ($s->fetchAll() as $row) $summary[$row['channel']] = $row;
        $t = $this->pdo->prepare("SELECT COUNT(*) AS issued, SUM(status = 'used') AS used FROM event_tickets WHERE event_id = ? AND status <> 'cancelled'");
        $t->execute([(int)$eventId]);
        $tk = $t->fetch();
        $summary['tickets_issued'] = (int)($tk['issued'] ?? 0);
        $summary['tickets_used'] = (int)($tk['used'] ?? 0);
        return $summary;
    }

    private function ticketSelect() {
        return "SELECT tk.*, e.title, e.slug, e.event_date, e.location, e.contact_name, e.contact_phone, e.contact_email,
                    t.name AS ticket_name, t.currency, t.price, o.reference, o.channel, o.buyer_name, o.discount_code, u.username
                FROM event_tickets tk
                JOIN events e ON e.id = tk.event_id
                JOIN event_ticket_types t ON t.id = tk.ticket_type_id
                JOIN event_orders o ON o.id = tk.order_id
                LEFT JOIN users u ON u.id = tk.user_id";
    }

    /** All tickets a user owns. */
    public function getUserTickets($userId) {
        $s = $this->pdo->prepare($this->ticketSelect() . ' WHERE tk.user_id = ? ORDER BY e.event_date DESC, tk.id DESC');
        $s->execute([(int)$userId]);
        return $s->fetchAll();
    }

    public function getTicketByCode($code) {
        $s = $this->pdo->prepare($this->ticketSelect() . ' WHERE tk.ticket_code = ? LIMIT 1');
        $s->execute([strtoupper(trim((string)$code))]);
        return $s->fetch() ?: null;
    }

    /** Gate check-in. Returns ['ok' => bool, 'message' => string, 'ticket' => array|null]. */
    public function checkInTicket($code, $eventId) {
        $ticket = $this->getTicketByCode($code);
        if (!$ticket || (int)$ticket['event_id'] !== (int)$eventId) return ['ok' => false, 'message' => 'Ticket not found for this event.', 'ticket' => null];
        if ($ticket['status'] === 'cancelled') return ['ok' => false, 'message' => 'This ticket has been cancelled.', 'ticket' => $ticket];
        if ($ticket['status'] === 'used') return ['ok' => false, 'message' => 'ALREADY USED at ' . $ticket['checked_in_at'] . '.', 'ticket' => $ticket];
        $this->pdo->prepare("UPDATE event_tickets SET status = 'used', checked_in_at = NOW() WHERE id = ? AND status = 'valid'")->execute([(int)$ticket['id']]);
        return ['ok' => true, 'message' => 'Valid — ' . $ticket['ticket_name'] . ' ticket checked in.', 'ticket' => $ticket];
    }

    /** True when paid orders exist (event must be archived, not deleted). */
    public function hasPaidOrders($eventId) {
        $s = $this->pdo->prepare("SELECT COUNT(*) FROM event_orders WHERE event_id = ? AND status = 'paid'");
        $s->execute([(int)$eventId]);
        return (int)$s->fetchColumn() > 0;
    }

    /** Make a slug unique among events. */
    public function uniqueSlug($slug, $ignoreId = 0) {
        $base = $slug ?: 'event';
        $candidate = $base;
        $n = 2;
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM events WHERE slug = ? AND id <> ?');
        while (true) {
            $s->execute([$candidate, (int)$ignoreId]);
            if ((int)$s->fetchColumn() === 0) return $candidate;
            $candidate = $base . '-' . $n++;
        }
    }

    /**
     * Get upcoming events count
     * @return int
     */
    public function getUpcomingCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'published' AND event_date >= NOW()");
        return $stmt->fetch()['count'];
    }

    /**
     * Update past events status
     * @return int
     */
    public function updatePastEvents() {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE events 
                SET status = 'past' 
                WHERE status = 'published' AND event_date < NOW()
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Event status update failed: " . $e->getMessage());
            return 0;
        }
    }
}
?>
