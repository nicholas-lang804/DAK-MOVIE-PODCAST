<?php
/**
 * Hosts Page View
 * Hosts and guests listing
 */
?>

<section class="page-header">
    <h1>Hosts & Guests</h1>
    <p>Meet our podcast hosts and featured guests.</p>
</section>

<!-- Hosts -->
<section class="hosts-section">
    <h2>Our Hosts</h2>
    <?php if (empty($hosts)): ?>
        <div class="no-results">
            <p>No hosts to display at this time.</p>
        </div>
    <?php else: ?>
        <div class="hosts-grid">
            <?php foreach ($hosts as $host): ?>
                <div class="host-card">
                    <div class="host-image">
                        <?php if ($host['profile_image_path']): ?>
                            <img src="<?php echo htmlspecialchars('/' . ltrim($host['profile_image_path'], '/')); ?>" alt="<?php echo htmlspecialchars($host['name']); ?>">
                        <?php else: ?>
                            <div class="placeholder-image">Host</div>
                        <?php endif; ?>
                    </div>
                    <div class="host-content">
                        <h3><?php echo htmlspecialchars($host['name']); ?></h3>
                        <?php if ($host['role']): ?>
                            <p class="host-role"><?php echo htmlspecialchars($host['role']); ?></p>
                        <?php endif; ?>
                        <p class="host-bio"><?php echo htmlspecialchars(substr($host['biography'] ?? '', 0, 200)); ?>...</p>
                        <a href="/hosts.php?slug=<?php echo urlencode($host['slug']); ?>" class="btn btn-primary">View Profile</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Guests -->
<section class="guests-section">
    <h2>Featured Guests</h2>
    <?php if (empty($guests)): ?>
        <div class="no-results">
            <p>No guests to display at this time.</p>
        </div>
    <?php else: ?>
        <div class="guests-grid">
            <?php foreach ($guests as $guest): ?>
                <div class="guest-card">
                    <div class="guest-image">
                        <?php if ($guest['profile_image_path']): ?>
                            <img src="<?php echo htmlspecialchars('/' . ltrim($guest['profile_image_path'], '/')); ?>" alt="<?php echo htmlspecialchars($guest['name']); ?>">
                        <?php else: ?>
                            <div class="placeholder-image">Guest</div>
                        <?php endif; ?>
                    </div>
                    <div class="guest-content">
                        <h3><?php echo htmlspecialchars($guest['name']); ?></h3>
                        <?php if ($guest['profession']): ?>
                            <p class="guest-profession"><?php echo htmlspecialchars($guest['profession']); ?></p>
                        <?php endif; ?>
                        <p class="guest-bio"><?php echo htmlspecialchars(substr($guest['biography'] ?? '', 0, 200)); ?>...</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
