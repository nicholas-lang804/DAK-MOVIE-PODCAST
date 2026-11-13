<?php
/** Printable ticket with QR code. Visible to the ticket owner or an admin. */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/app/Models/Event.php';

$ticket = (new Event())->getTicketByCode($_GET['code'] ?? '');
$isOwner = $ticket && !empty($_SESSION['user_id']) && (int)$ticket['user_id'] === (int)$_SESSION['user_id'];
$isAdminViewer = $ticket && isAdmin();
if (!$ticket || (!$isOwner && !$isAdminViewer)) {
    if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) tmzRequireCustomerLogin();
    http_response_code(404);
    tmzRenderPage('Ticket not found', '<section class="checkout-page"><div class="checkout-card"><h1>Ticket not found</h1><p>This ticket does not exist or belongs to another account.</p><a class="btn btn-primary" href="/my-tickets.php">My tickets</a></div></section>');
    exit;
}

ob_start(); ?>
<section class="checkout-page">
    <div class="print-ticket">
        <div class="print-ticket-head">
            <span class="eyebrow">THE DAK FILM PODCAST MEDIA · ADMIT <?php echo $ticket['channel'] === 'physical' ? 'ONE (PHYSICAL SALE)' : 'ONE'; ?></span>
            <h1><?php echo tmzH($ticket['title']); ?></h1>
            <div class="print-level"><?php echo tmzH($ticket['ticket_name']); ?></div>
        </div>
        <div class="print-ticket-body">
            <dl>
                <dt>Date</dt><dd><?php echo !empty($ticket['event_date']) ? tmzH(date('l, M d Y · H:i', strtotime($ticket['event_date']))) : 'To be announced'; ?></dd>
                <dt>Venue</dt><dd><?php echo tmzH($ticket['location'] ?: 'To be announced'); ?></dd>
                <dt>Holder</dt><dd><?php echo tmzH($ticket['username'] ?: ($ticket['buyer_name'] ?: 'Guest')); ?></dd>
                <?php if (!empty($ticket['contact_name']) || !empty($ticket['contact_phone'])): ?>
                    <dt>Enquiries</dt><dd><?php echo tmzH(trim(($ticket['contact_name'] ?? '') . ' ' . ($ticket['contact_phone'] ?? ''))); ?></dd>
                <?php endif; ?>
                <dt>Order</dt><dd><?php echo tmzH($ticket['reference']); ?></dd>
                <dt>Status</dt><dd><?php echo tmzH(ucfirst($ticket['status'])); ?></dd>
            </dl>
            <div class="print-qr">
                <div id="qr"></div>
                <code><?php echo tmzH($ticket['ticket_code']); ?></code>
            </div>
        </div>
        <div class="print-actions">
            <button class="btn btn-primary" onclick="window.print()">Print ticket</button>
            <a class="btn btn-outline" href="/my-tickets.php">All my tickets</a>
        </div>
    </div>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    if (window.QRCode) new QRCode(document.getElementById('qr'), { text: <?php echo json_encode($ticket['ticket_code']); ?>, width: 160, height: 160 });
})();
</script>
<?php
$content = ob_get_clean();
tmzRenderPage('Ticket ' . $ticket['ticket_code'], $content);
