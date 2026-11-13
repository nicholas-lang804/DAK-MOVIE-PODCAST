<?php
/**
 * Events Page View
 * Events listing
 */
?>

<section class="page-header">
    <h1>Events</h1>
    <p>Upcoming and past events from THE DAK FILM PODCAST MEDIA.</p>
    <p style="margin-top:12px"><a href="/sell-tickets.php" class="btn btn-primary">🎟 Sale Tickets With Us</a></p>
</section>

<!-- Upcoming Events -->
<section class="upcoming-events">
    <h2>Upcoming Events</h2>
    <?php if (empty($upcomingEvents)): ?>
        <div class="no-results">
            <p>No upcoming events at this time.</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($upcomingEvents as $event): ?>
                <div class="event-card">
                    <div class="event-image">
                        <?php if (!empty($event['poster_path'])): ?>
                            <img src="<?php echo htmlspecialchars('/' . ltrim($event['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                        <?php else: ?>
                            <div class="placeholder-image">Event</div>
                        <?php endif; ?>
                    </div>
                    <div class="event-content">
                        <h3><?php echo htmlspecialchars($event['title']); ?><?php if (empty($event['user_id'])): ?> <span class="discount-pill">Official Event</span><?php endif; ?></h3>
                        <p class="event-meta">
                            <?php if ($event['event_date']): ?>
                                <span class="date"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                            <?php endif; ?>
                            <?php if ($event['location']): ?>
                                <span class="location"><?php echo htmlspecialchars($event['location']); ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if (isset($event['min_price']) && $event['min_price'] !== null): ?><p class="event-from-price">Tickets from <strong><?php echo htmlspecialchars(Event::money($event['price_currency'], $event['min_price'])); ?></strong><?php if (!empty($event['active_discounts'])): ?> <span class="discount-pill">Discount available</span><?php endif; ?></p><?php endif; ?>
                        <p class="event-description"><?php echo htmlspecialchars(substr($event['description'] ?? '', 0, 150)); ?>...</p>
                        <a href="/events.php?slug=<?php echo urlencode($event['slug']); ?>" class="btn btn-primary">Tickets &amp; details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Past Events -->
<section class="past-events">
    <h2>Past Events</h2>
    <?php if (empty($pastEvents)): ?>
        <div class="no-results">
            <p>No past events to display.</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($pastEvents as $event): ?>
                <div class="event-card">
                    <div class="event-image">
                        <?php if (!empty($event['poster_path'])): ?>
                            <img src="<?php echo htmlspecialchars('/' . ltrim($event['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                        <?php else: ?>
                            <div class="placeholder-image">Event</div>
                        <?php endif; ?>
                    </div>
                    <div class="event-content">
                        <h3><?php echo htmlspecialchars($event['title']); ?><?php if (empty($event['user_id'])): ?> <span class="discount-pill">Official Event</span><?php endif; ?></h3>
                        <p class="event-meta">
                            <?php if ($event['event_date']): ?>
                                <span class="date"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                            <?php endif; ?>
                            <?php if ($event['location']): ?>
                                <span class="location"><?php echo htmlspecialchars($event['location']); ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="event-description"><?php echo htmlspecialchars(substr($event['description'] ?? '', 0, 150)); ?>...</p>
                        <a href="/events.php?slug=<?php echo urlencode($event['slug']); ?>" class="btn btn-outline">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
