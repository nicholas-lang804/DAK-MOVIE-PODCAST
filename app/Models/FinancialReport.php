<?php
require_once __DIR__ . '/../../config/database.php';

class FinancialReport {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    public function getSummary() {
        $payments = $this->pdo->query("
            SELECT status, COUNT(*) AS transactions
            FROM payments
            GROUP BY status
        ")->fetchAll();
        $paymentCounts = ['completed' => 0, 'pending' => 0, 'failed' => 0, 'refunded' => 0];
        foreach ($payments as $row) {
            $paymentCounts[$row['status']] = (int) $row['transactions'];
        }

        $revenue = $this->pdo->query("
            SELECT currency, SUM(amount) AS total
            FROM (
                SELECT currency, amount
                FROM payments
                WHERE status = 'completed'
                UNION ALL
                SELECT currency, total AS amount
                FROM event_orders
                WHERE status = 'paid' AND channel = 'physical'
            ) AS income
            GROUP BY currency
            ORDER BY currency
        ")->fetchAll();

        $tickets = (int) $this->pdo->query("
            SELECT COUNT(*)
            FROM event_tickets
            WHERE status <> 'cancelled'
        ")->fetchColumn();

        return [
            'paymentCounts' => $paymentCounts,
            'revenue' => $revenue,
            'ticketsSold' => $tickets
        ];
    }

    public function getMonthlyRevenue($months = 6) {
        $months = max(1, min(24, (int) $months));
        $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
        $stmt = $this->pdo->prepare("
            SELECT DATE_FORMAT(income_date, '%Y-%m') AS month, currency, SUM(amount) AS total
            FROM (
                SELECT COALESCE(paid_at, created_at) AS income_date, currency, amount
                FROM payments
                WHERE status = 'completed' AND COALESCE(paid_at, created_at) >= ?
                UNION ALL
                SELECT COALESCE(paid_at, created_at) AS income_date, currency, total AS amount
                FROM event_orders
                WHERE status = 'paid' AND channel = 'physical' AND COALESCE(paid_at, created_at) >= ?
            ) AS income
            GROUP BY DATE_FORMAT(income_date, '%Y-%m'), currency
            ORDER BY month, currency
        ");
        $stmt->execute([$start, $start]);
        return $stmt->fetchAll();
    }

    public function getPayments($limit = 50, $offset = 0) {
        $limit = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.amount, p.currency, p.status, p.reference, p.payment_method,
                p.gateway, p.gateway_reference, p.created_at, p.paid_at,
                u.username, u.email AS customer_email, m.title AS movie_title,
                sp.name AS plan_name, e.title AS event_title
            FROM payments p
            LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN movies m ON m.id = p.movie_id
            LEFT JOIN subscription_plans sp ON sp.id = p.subscription_plan_id
            LEFT JOIN event_orders eo ON eo.id = p.event_order_id
            LEFT JOIN events e ON e.id = eo.event_id
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPaymentCount() {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
    }

    public function getTickets($limit = 50, $offset = 0) {
        $limit = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);
        $stmt = $this->pdo->prepare("
            SELECT tk.ticket_code, tk.status AS ticket_status, tk.issued_at, tk.checked_in_at,
                e.title AS event_title, tt.name AS ticket_type, tt.currency,
                o.reference, o.status AS order_status, o.channel, o.total, o.buyer_name,
                o.buyer_email, o.buyer_phone, u.username, u.email AS customer_email
            FROM event_tickets tk
            JOIN events e ON e.id = tk.event_id
            JOIN event_ticket_types tt ON tt.id = tk.ticket_type_id
            JOIN event_orders o ON o.id = tk.order_id
            LEFT JOIN users u ON u.id = tk.user_id
            ORDER BY tk.issued_at DESC, tk.id DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTicketCount() {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM event_tickets')->fetchColumn();
    }

    public function exportPayments() {
        return $this->pdo->query("
            SELECT p.id, p.reference, p.status, p.amount, p.currency, p.payment_method,
                p.gateway, p.gateway_reference, u.username, u.email AS customer_email,
                m.title AS movie_title, sp.name AS subscription_plan, e.title AS event_title,
                p.created_at, p.paid_at
            FROM payments p
            LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN movies m ON m.id = p.movie_id
            LEFT JOIN subscription_plans sp ON sp.id = p.subscription_plan_id
            LEFT JOIN event_orders eo ON eo.id = p.event_order_id
            LEFT JOIN events e ON e.id = eo.event_id
            ORDER BY p.created_at DESC, p.id DESC
        ");
    }

    public function exportTickets() {
        return $this->pdo->query("
            SELECT tk.ticket_code, tk.status AS ticket_status, e.title AS event_title,
                tt.name AS ticket_type, tt.currency, o.reference AS order_reference,
                o.status AS order_status, o.channel, o.total, o.buyer_name, o.buyer_email,
                o.buyer_phone, u.username, u.email AS customer_email,
                tk.issued_at, tk.checked_in_at
            FROM event_tickets tk
            JOIN events e ON e.id = tk.event_id
            JOIN event_ticket_types tt ON tt.id = tk.ticket_type_id
            JOIN event_orders o ON o.id = tk.order_id
            LEFT JOIN users u ON u.id = tk.user_id
            ORDER BY tk.issued_at DESC, tk.id DESC
        ");
    }
}
?>
