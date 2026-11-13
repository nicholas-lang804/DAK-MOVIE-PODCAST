<?php
/**
 * Event ticket checkout: choose quantity, apply discount code, pay with Pesapal.
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/app/Models/Event.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Services/PesapalGateway.php';

tmzRequireCustomerLogin();

$eventModel = new Event();
$eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$ticketTypeId = (int)($_GET['ticket_type_id'] ?? $_POST['ticket_type_id'] ?? 0);
$event = $eventModel->findById($eventId);
$ticket = $event ? $eventModel->getTicketType($eventId, $ticketTypeId) : null;

$error = '';
$quote = null;
$quantity = max(1, min(20, (int)($_POST['quantity'] ?? 1)));
$code = trim($_POST['discount_code'] ?? '');

if (!$event || $event['status'] !== 'published' || !$ticket) {
    $error = 'The selected event ticket is unavailable.';
} else {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            // preview first so errors (bad code, sold out) are shown before any order exists
            $quote = $eventModel->quote($eventId, $ticketTypeId, $quantity, $code, 'online');

            if (($_POST['do'] ?? '') === 'pay') {
                $order = $eventModel->createOrder($eventId, $_SESSION['user_id'], $ticketTypeId, $quantity, $code);

                if ($order['amount'] <= 0) { // free ticket: issue immediately
                    $eventModel->completeOrder($order['id']);
                    header('Location: /TMZ%20PODCAST/my-tickets.php?new=1');
                    exit;
                }

                $payments = new Payment();
                $payment = $payments->createPendingEventPayment($_SESSION['user_id'], $order['id'], $order['amount'], $order['currency']);
                $user = (new User())->findById($_SESSION['user_id']);
                $gateway = (new PesapalGateway())->submitOrder($payment, ['title' => $event['title'] . ' - ' . $ticket['name']], $user);
                if (!empty($gateway['success'])) {
                    $payments->setGatewayReference($payment['id'], $gateway['tracking_id'] ?? '');
                    header('Location: ' . $gateway['redirect_url']);
                    exit;
                }
                $error = $gateway['message'] ?? 'Pesapal did not return a payment link.';
            }
        } else {
            $quote = $eventModel->quote($eventId, $ticketTypeId, 1, '', 'online');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (!$quote) { try { $quote = $eventModel->quote($eventId, $ticketTypeId, $quantity, '', 'online'); } catch (Throwable $e2) {} }
    }
}

ob_start();
?>
<section class="checkout-page">
    <div class="checkout-card event-checkout-card">
        <span class="eyebrow">THE DAK FILM PODCAST MEDIA TICKETING</span>
        <h1><?php echo tmzH($event['title'] ?? 'Event ticket'); ?></h1>
        <?php if ($event): ?>
            <p class="checkout-meta">
                <?php if (!empty($event['event_date'])): ?><span><?php echo tmzH(date('D, M d Y · H:i', strtotime($event['event_date']))); ?></span><?php endif; ?>
                <?php if (!empty($event['location'])): ?><span><?php echo tmzH($event['location']); ?></span><?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($error): ?><div class="alert alert-error"><?php echo tmzH($error); ?></div><?php endif; ?>

        <?php if ($ticket): ?>
            <div class="checkout-level">
                <span class="ticket-code"><?php echo tmzH($ticket['code']); ?></span>
                <strong><?php echo tmzH($ticket['name']); ?></strong>
                <span><?php echo tmzH(Event::money($ticket['currency'], $ticket['price'])); ?> each</span>
            </div>
            <form method="post">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                <input type="hidden" name="ticket_type_id" value="<?php echo (int)$ticketTypeId; ?>">
                <label>Quantity
                    <input type="number" name="quantity" value="<?php echo (int)$quantity; ?>" min="1" max="20" required>
                </label>
                <label>Discount code (if you qualify)
                    <input name="discount_code" value="<?php echo tmzH($code); ?>" placeholder="Enter the code from the organiser" autocomplete="off">
                </label>

                <?php if ($quote): ?>
                    <div class="order-summary">
                        <div><span>Subtotal (<?php echo (int)$quote['quantity']; ?> × <?php echo tmzH($ticket['name']); ?>)</span><span><?php echo tmzH(Event::money($ticket['currency'], $quote['subtotal'])); ?></span></div>
                        <?php if ($quote['rule']): ?>
                            <div class="discount-line"><span>Discount <?php echo tmzH($quote['rule']['code']); ?> (<?php echo rtrim(rtrim(number_format((float)$quote['rule']['discount_percent'], 2), '0'), '.'); ?>% — <?php echo tmzH($quote['rule']['eligible_label']); ?>)</span><span>− <?php echo tmzH(Event::money($ticket['currency'], $quote['discount'])); ?></span></div>
                        <?php endif; ?>
                        <div class="order-total"><span>Total</span><span><?php echo tmzH(Event::money($ticket['currency'], $quote['total'])); ?></span></div>
                    </div>
                <?php endif; ?>

                <div class="checkout-actions">
                    <button class="btn btn-outline" type="submit" name="do" value="preview">Update total</button>
                    <button class="btn btn-primary" type="submit" name="do" value="pay">
                        <?php echo ($quote && $quote['total'] <= 0) ? 'Get free ticket' : 'Pay securely with Pesapal'; ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <a class="back-link" href="/TMZ%20PODCAST/events.php?slug=<?php echo urlencode($event['slug'] ?? ''); ?>">← Back to event</a>
    </div>
</section>
<?php
$content = ob_get_clean();
tmzRenderPage('Buy ticket', $content);
