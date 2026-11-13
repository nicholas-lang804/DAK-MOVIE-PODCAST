<?php
/**
 * Host Detail Page View
 * Single host profile
 */
?>

<article class="host-detail">
    <div class="host-header">
        <div class="host-image">
            <?php if ($host['profile_image_path']): ?>
                <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($host['profile_image_path'], '/')); ?>" alt="<?php echo htmlspecialchars($host['name']); ?>">
            <?php else: ?>
                <div class="placeholder-image">Host Image</div>
            <?php endif; ?>
        </div>
        <div class="host-info">
            <h1><?php echo htmlspecialchars($host['name']); ?></h1>
            <?php if ($host['role']): ?>
                <p class="host-role"><?php echo htmlspecialchars($host['role']); ?></p>
            <?php endif; ?>
            <p class="host-bio"><?php echo nl2br(htmlspecialchars($host['biography'] ?? '')); ?></p>
            
            <?php if ($host['social_links']): ?>
                <div class="social-links">
                    <?php $socialLinks = json_decode($host['social_links'], true); ?>
                    <?php if (is_array($socialLinks)): ?>
                        <?php foreach ($socialLinks as $platform => $url): ?>
                            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="social-link"><?php echo ucfirst($platform); ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Episodes by Host -->
    <?php if (!empty($episodes)): ?>
        <section class="host-episodes">
            <h2>Episodes</h2>
            <div class="episodes-grid">
                <?php foreach ($episodes as $episode): ?>
                    <div class="episode-card">
                        <div class="episode-image">
                            <?php if ($episode['featured_image_id']): ?>
                                <img src="<?php echo htmlspecialchars(mediaUrl($episode['featured_image_id'])); ?>" alt="<?php echo htmlspecialchars($episode['title']); ?>">
                            <?php else: ?>
                                <div class="placeholder-image">Episode</div>
                            <?php endif; ?>
                        </div>
                        <div class="episode-content">
                            <h3><?php echo htmlspecialchars($episode['title']); ?></h3>
                            <a href="/TMZ%20PODCAST/episodes.php?slug=<?php echo urlencode($episode['slug']); ?>" class="btn btn-small">Listen</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
