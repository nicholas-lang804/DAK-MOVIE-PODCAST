<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Models/FinancialReport.php';

AuthMiddleware::requireAdmin();

$type = $_GET['type'] ?? '';
if (!in_array($type, ['payments', 'tickets'], true)) {
    http_response_code(400);
    exit('Invalid financial report type.');
}

$report = new FinancialReport();
$statement = $type === 'payments' ? $report->exportPayments() : $report->exportTickets();
$filename = 'tmz-' . $type . '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'w');
if ($output === false) {
    http_response_code(500);
    exit('Unable to generate financial report.');
}
fwrite($output, "\xEF\xBB\xBF");
$headers = $type === 'payments'
    ? ['id', 'reference', 'status', 'amount', 'currency', 'payment_method', 'gateway', 'gateway_reference', 'username', 'customer_email', 'movie_title', 'subscription_plan', 'event_title', 'created_at', 'paid_at']
    : ['ticket_code', 'ticket_status', 'event_title', 'ticket_type', 'currency', 'order_reference', 'order_status', 'channel', 'total', 'buyer_name', 'buyer_email', 'buyer_phone', 'username', 'customer_email', 'issued_at', 'checked_in_at'];
fputcsv($output, $headers);
$first = $statement->fetch(PDO::FETCH_ASSOC);
if ($first !== false) {
    $writeRow = function ($row) use ($output) {
        foreach ($row as &$value) {
            if (is_string($value) && preg_match('/^[\s]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $row);
    };
    $writeRow($first);
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $writeRow($row);
    }
}
fclose($output);
exit;
