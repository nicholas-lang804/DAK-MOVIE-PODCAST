<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Middleware/CSRF.php';
require_once __DIR__ . '/app/Models/Gift.php';
require_once __DIR__ . '/app/Models/LivePodcast.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Services/PesapalGateway.php';

if (!isLoggedIn() || empty($_SESSION['user_id'])) { header('Location: /TMZ%20PODCAST/login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /TMZ%20PODCAST/'); exit; }
CSRF::requireValidToken();

$liveId = (int)($_POST['live_id'] ?? 0);
$giftId = (int)($_POST['gift_id'] ?? 0);
$currency = strtoupper(substr(trim((string)($_POST['currency'] ?? 'USD')), 0, 8));
$recipientType = ($_POST['recipient_type'] ?? 'host') === 'user' ? 'user' : 'host';
$recipientUserId = (int)($_POST['recipient_user_id'] ?? 0);
$recipientName = trim((string)($_POST['recipient_name'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$back = '/TMZ%20PODCAST/gift.php?live_id=' . $liveId . '&gift_id=' . $giftId . '&currency=' . urlencode($currency);

$giftModel = new Gift();
$gift = $giftModel->giftById($giftId);
$live = $liveId ? (new LivePodcast())->getById($liveId) : null;
if (!$gift || empty($gift['is_active']) || !$live) {
    $_SESSION['gift_error'] = 'That gift or live show is not available.';
    header('Location: ' . $back); exit;
}

// Validate the chosen recipient.
if ($recipientType === 'user') {
    $okRecipient = false;
    foreach ($giftModel->recentCommenters($liveId, 50) as $c) {
        if ((int)$c['user_id'] === $recipientUserId) { $okRecipient = true; $recipientName = $recipientName !== '' ? $recipientName : $c['username']; break; }
    }
    if (!$okRecipient) { $recipientType = 'host'; $recipientUserId = 0; }
}
if ($recipientType === 'host') { $recipientName = 'The Host'; $recipientUserId = 0; }
if ($recipientName === '') $recipientName = 'A participant';

$user = (new User())->findById($_SESSION['user_id']);
$senderName = $user['username'] ?? ('User #' . (int)$_SESSION['user_id']);
$amount = $giftModel->convert($gift['price_usd'], $currency);
if ($amount <= 0) { $_SESSION['gift_error'] = 'Invalid gift amount.'; header('Location: ' . $back); exit; }

$order = $giftModel->createOrder([
    'live_id' => $liveId, 'sender_id' => $_SESSION['user_id'], 'sender_name' => $senderName,
    'recipient_type' => $recipientType, 'recipient_user_id' => $recipientUserId, 'recipient_name' => $recipientName,
    'gift_id' => $gift['id'], 'gift_name' => $gift['name'], 'emoji' => $gift['emoji'],
    'amount' => $amount, 'currency' => $currency, 'message' => $message
]);

$payment = (new Payment())->createPendingGiftPayment($_SESSION['user_id'], $amount, $currency, $order['reference']);
$giftModel->attachPayment($order['id'], $payment['id']);

try {
    $item = ['title' => 'Live Gift: ' . $gift['name'] . ' → ' . $recipientName . ' (' . $live['title'] . ')'];
    $gateway = (new PesapalGateway())->submitOrder($payment, $item, $user);
    if (!empty($gateway['success'])) {
        (new Payment())->setGatewayReference($payment['id'], $gateway['tracking_id'] ?? '');
        header('Location: ' . $gateway['redirect_url']); exit;
    }
    $giftModel->fail($order['reference']);
    $_SESSION['gift_error'] = $gateway['message'] ?? 'Payment could not be started.';
} catch (Throwable $e) {
    $giftModel->fail($order['reference']);
    $_SESSION['gift_error'] = 'Pesapal could not be reached: ' . $e->getMessage();
}
header('Location: ' . $back); exit;
