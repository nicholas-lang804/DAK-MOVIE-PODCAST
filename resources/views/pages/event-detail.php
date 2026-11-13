<?php
/**
 * Event Detail Page View
 * Single event view
 */
?>

<article class="event-detail">
    <div class="event-header">
        <div class="event-image">
            <?php if (!empty($event['poster_path'])): ?>
                <img src="<?php echo htmlspecialchars('/' . ltrim($event['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
            <?php else: ?>
                <div class="placeholder-image">Event Image</div>
            <?php endif; ?>
        </div>
        <div class="event-info">
            <h1><?php echo htmlspecialchars($event['title']); ?><?php if (empty($event['user_id'])): ?> <span class="discount-pill">Official Event</span><?php endif; ?></h1>
            <p class="event-meta">
                <?php if (!empty($event['event_date'])): ?>
                    <span class="date"><?php echo date('D, M d, Y · H:i', strtotime($event['event_date'])); ?></span>
                <?php endif; ?>
                <?php if (!empty($event['location'])): ?>
                    <span class="location"><?php echo htmlspecialchars($event['location']); ?></span>
                <?php endif; ?>
            </p>
            <p class="event-description"><?php echo nl2br(htmlspecialchars($event['description'] ?? '')); ?></p>
            <p><a href="/sell-tickets.php" class="btn btn-outline btn-small">🎟 Sale Tickets With Us — host your own event</a></p>
            <?php if (!empty($event['contact_name']) || !empty($event['contact_phone'])): ?>
                <div class="event-contact-card">
                    <strong>Event contact</strong>
                    <?php if (!empty($event['contact_name'])): ?><span><?php echo htmlspecialchars($event['contact_name']); ?></span><?php endif; ?>
                    <?php if (!empty($event['contact_phone'])): ?><span><?php echo htmlspecialchars($event['contact_phone']); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($event['registration_url'])): ?>
                <a href="<?php echo htmlspecialchars($event['registration_url']); ?>" target="_blank" class="btn btn-primary">Register Now</a>
            <?php endif; ?>
        </div>
    </div>
    <section class="event-ticketing" id="tickets">
        <div>
            <span class="eyebrow">SECURE EVENT ACCESS</span>
            <h2>Choose your ticket level</h2>
            <p>Pick Ordinary, VIP or VVIP and pay securely online with Pesapal. Your ticket appears in <strong>My Tickets</strong> straight after payment.
            <?php if (empty($isCustomer)): ?><a href="/login.php">Log in</a> or <a href="/register.php">register</a> to buy.<?php endif; ?></p>
        </div>

        <?php if (!empty($isPast)): ?>
            <p class="event-discount-note is-warning">This event has already taken place.</p>
        <?php else: ?>
        <div class="event-ticket-grid">
            <?php foreach (($ticketTypes ?? []) as $ticket):
                $left = $ticket['capacity'] === null ? null : max(0, (int)$ticket['capacity'] - (int)$ticket['sold_count']); ?>
                <div class="event-ticket-card">
                    <span class="ticket-code"><?php echo htmlspecialchars($ticket['code']); ?></span>
                    <h3><?php echo htmlspecialchars($ticket['name']); ?></h3>
                    <strong><?php echo htmlspecialchars(Event::money($ticket['currency'], $ticket['price'])); ?></strong>
                    <?php if ($left !== null): ?>
                        <small><?php echo $left > 0 ? $left . ' tickets remaining' : 'Sold out'; ?></small>
                    <?php else: ?>
                        <small>Per person</small>
                    <?php endif; ?>
                    <?php if ($left === 0): ?>
                        <span class="btn btn-outline disabled">Sold out</span>
                    <?php else: ?>
                        <a class="btn btn-primary" href="/event-ticket.php?event_id=<?php echo (int)$event['id']; ?>&amp;ticket_type_id=<?php echo (int)$ticket['id']; ?>">Buy <?php echo htmlspecialchars($ticket['name']); ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($ticketTypes)): ?>
                <p class="form-help">Tickets will be available once the organiser publishes the ticket levels.</p>
            <?php endif; ?>
        </div>

        <?php foreach (($discountRules ?? []) as $rule): ?>
            <p class="event-discount-note">
                <strong><?php echo rtrim(rtrim(number_format((float)$rule['discount_percent'], 2), '0'), '.'); ?>% off</strong>
                for <?php echo htmlspecialchars($rule['eligible_label']); ?> — use code <strong><?php echo htmlspecialchars($rule['code']); ?></strong> at checkout
                <?php if (!empty($rule['ends_at'])): ?>until <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($rule['ends_at']))); ?><?php endif; ?>
                <?php if ($rule['channel'] === 'online'): ?>(online sales)<?php elseif ($rule['channel'] === 'physical'): ?>(physical sales)<?php endif; ?>.
            </p>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <section class="event-enquiry" id="event-contact">
        <div class="event-enquiry-heading">
            <span class="eyebrow">HAVE A QUESTION?</span>
            <h2>Contact the event team</h2>
            <p>Send us a message about this event. Your enquiry will be delivered to the team’s contact email.</p>
        </div>
        <?php if (isset($_SESSION['event_contact_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['event_contact_success'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php unset($_SESSION['event_contact_success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['event_contact_error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['event_contact_error'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php unset($_SESSION['event_contact_error']); ?>
        <?php endif; ?>
        <?php if (!empty($eventEnquiryAvailable)): ?>
            <form method="post" action="/events.php?slug=<?php echo rawurlencode($event['slug']); ?>#event-contact" class="event-enquiry-form">
                <?php echo CSRF::getField(); ?>
                <div class="event-enquiry-fields">
                    <div class="form-group">
                        <label for="event_contact_name">Your name</label>
                        <input type="text" id="event_contact_name" name="name" maxlength="160" autocomplete="name" required>
                    </div>
                    <div class="form-group">
                        <label for="event_contact_email">Your email</label>
                        <input type="email" id="event_contact_email" name="email" maxlength="254" autocomplete="email" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="event_contact_message">Message</label>
                    <textarea id="event_contact_message" name="message" rows="5" maxlength="5000" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send enquiry</button>
            </form>
        <?php else: ?>
            <p class="event-enquiry-unavailable">Event enquiries are temporarily unavailable. Please contact us through the <a href="/contact.php">Contact Us page</a>.</p>
        <?php endif; ?>
    </section>
</article>
