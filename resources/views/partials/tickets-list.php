<?php
/** Shared list of a user's tickets. Expects $tickets. */
$now = time();
?>
<?php if (empty($tickets)): ?>
    <div class="no-results"><p>You have no tickets yet. <a href="/TMZ%20PODCAST/events.php">Find an event</a> and pick Ordinary, VIP or VVIP.</p></div>
<?php else: ?>
    <div class="my-ticket-grid">
    <?php foreach ($tickets as $tk):
        $past = !empty($tk['event_date']) && strtotime($tk['event_date']) < $now; ?>
        <article class="my-ticket <?php echo $past ? 'is-past' : ''; ?> status-<?php echo htmlspecialchars($tk['status']); ?>">
            <div class="my-ticket-main">
                <span class="ticket-code"><?php echo htmlspecialchars($tk['ticket_name']); ?></span>
                <h3><?php echo htmlspecialchars($tk['title']); ?></h3>
                <p><?php echo !empty($tk['event_date']) ? htmlspecialchars(date('D, M d Y · H:i', strtotime($tk['event_date']))) : 'Date to be announced'; ?></p>
                <p><?php echo htmlspecialchars($tk['location'] ?? ''); ?></p>
            </div>
            <div class="my-ticket-stub">
                <code><?php echo htmlspecialchars($tk['ticket_code']); ?></code>
                <span class="ticket-status"><?php echo $tk['status'] === 'used' ? 'Checked in' : ($tk['status'] === 'cancelled' ? 'Cancelled' : ($past ? 'Event ended' : 'Valid')); ?></span>
                <a class="btn btn-small btn-primary" href="/TMZ%20PODCAST/ticket.php?code=<?php echo urlencode($tk['ticket_code']); ?>">View / print</a>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
