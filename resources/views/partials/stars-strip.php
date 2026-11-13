<?php
/** Horizontal "Stars of the Zone" strip for the homepage and member dashboard. */
require_once __DIR__ . '/star-card.php';
$tmzStrip = [];
try {
    require_once __DIR__ . '/../../../app/Models/Star.php';
    $tmzStrip = (new Star())->getFeatured(10);
} catch (Throwable $e) { error_log('Stars strip: ' . $e->getMessage()); }
?>
<section class="stars-section" aria-labelledby="stars-heading">
    <div class="cine-heading">
        <div>
            <span class="cine-eyebrow">Spotlight</span>
            <h2 id="stars-heading">Stars of the Zone</h2>
        </div>
        <a href="/TMZ%20PODCAST/stars.php" class="btn btn-outline">All actors &amp; actresses</a>
    </div>
    <?php if (empty($tmzStrip)): ?>
        <p class="cine-empty">New stars are being added. Check back soon.</p>
    <?php else: ?>
        <div class="stars-rail">
            <?php foreach ($tmzStrip as $s) { tmz_star_card($s); } ?>
        </div>
    <?php endif; ?>
</section>
