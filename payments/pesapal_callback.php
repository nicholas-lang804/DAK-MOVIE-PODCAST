<?php
/**
 * Pesapal return/callback endpoint (canonical path used for SubmitOrderRequest callback_url).
 * Verifies the transaction server-side before touching payment records — never trusts the redirect alone.
 */
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/Models/Payment.php';
require_once dirname(__DIR__) . '/app/Models/Movie.php';
require_once dirname(__DIR__) . '/app/Models/Gift.php';
require_once dirname(__DIR__) . '/app/Services/PesapalGateway.php';

$trackingId = trim($_GET['OrderTrackingId'] ?? '');
$reference = trim($_GET['OrderMerchantReference'] ?? '');
$message = 'Payment status could not be confirmed.';
$isEvent = false;
$isGift = false;
$movieId = 0;
$giftLiveId = 0;
if ($trackingId && $reference) {
    try {
        $payment = new Payment(); $gateway = new PesapalGateway(); $status = $gateway->transactionStatus($trackingId);
        $paymentRow = $payment->getByReference($reference);
        $movieId = (int)($paymentRow['movie_id'] ?? 0);
        $paymentStatus = $status['payment_status_description'] ?? ($status['status_code'] ?? 'pending');
        $isEvent = !empty($paymentRow['event_order_id']);
        $isFee = !empty($paymentRow['event_fee_id']);
        $giftOrder = (new Gift())->orderByReference($reference);
        $isGift = (bool)$giftOrder;
        $giftLiveId = $isGift ? (int)$giftOrder['live_id'] : 0;
        $ok = $isGift ? $payment->completeGiftPayment($reference, $trackingId, $paymentStatus)
            : ($isFee ? $payment->completeEventFeePayment($reference, $trackingId, $paymentStatus)
            : ($isEvent ? $payment->completeEventPayment($reference, $trackingId, $paymentStatus)
            : $payment->completeMoviePayment($reference, $trackingId, $paymentStatus)));
        $message = $ok ? ($isGift ? 'Payment confirmed. Your gift is on its way!' : ($isEvent ? 'Payment confirmed. Your tickets are ready.' : 'Payment confirmed. Your access is now active.'))
                      : 'Payment is still pending or was not completed.';
    } catch (Throwable $e) {
        $message = 'Payment verification failed. Please contact support with your payment reference.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment status</title><link rel="stylesheet" href="/assets/css/style.css"></head><body><main class="payment-page"><div class="payment-card"><span class="eyebrow">PAYMENT STATUS</span><h1><?php echo htmlspecialchars($message); ?></h1><?php if ($movieId): ?><a class="btn btn-primary" href="/movies.php">Return to movies</a><?php endif; ?><?php if ($isEvent): ?><a class="btn btn-primary" href="/my-tickets.php">View my tickets</a><?php endif; ?><?php if ($giftLiveId): ?><a class="btn btn-primary" href="/live.php?live_id=<?php echo $giftLiveId; ?>">Back to live show</a><?php endif; ?><a class="btn btn-outline" href="/dashboard.php">Dashboard</a></div></main></body></html>
