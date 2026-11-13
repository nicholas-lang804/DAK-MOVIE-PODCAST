<?php
/**
 * Pesapal IPN endpoint (server-to-server payment notification).
 * Always verifies with GetTransactionStatus before updating records. Safe to call repeatedly.
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/Models/Payment.php';
require_once dirname(__DIR__) . '/app/Models/Gift.php';
require_once dirname(__DIR__) . '/app/Services/PesapalGateway.php';

$payload = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: $_GET;
$trackingId = trim($payload['OrderTrackingId'] ?? '');
$reference = trim($payload['OrderMerchantReference'] ?? '');
$status = 500;
if ($trackingId && $reference) {
    try {
        $result = (new PesapalGateway())->transactionStatus($trackingId);
        $payments = new Payment();
        $row = $payments->getByReference($reference);
        $pstatus = $result['payment_status_description'] ?? ($result['status_code'] ?? 'pending');
        if ((new Gift())->orderByReference($reference)) { $payments->completeGiftPayment($reference, $trackingId, $pstatus); }
        elseif (!empty($row['event_fee_id'])) { $payments->completeEventFeePayment($reference, $trackingId, $pstatus); }
        elseif (!empty($row['event_order_id'])) { $payments->completeEventPayment($reference, $trackingId, $pstatus); }
        else { $payments->completeMoviePayment($reference, $trackingId, $pstatus); }
        $status = 200;
    } catch (Throwable $e) { $status = 500; }
}
header('Content-Type: application/json');
echo json_encode(['orderNotificationType' => 'IPNCHANGE', 'orderTrackingId' => $trackingId, 'orderMerchantReference' => $reference, 'status' => $status]);
