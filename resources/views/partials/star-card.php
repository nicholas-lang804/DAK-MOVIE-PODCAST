<?php
/** Shared star card. Usage: tmz_star_card($star); */
if (!function_exists('tmz_star_card')) {
    function tmz_star_card($s) {
        $h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
        $photo = !empty($s['photo_path']) ? '/' . ltrim($s['photo_path'], '/') : '';
        $hasVideo = !empty($s['video_path']) || !empty($s['video_url']);
        $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($s['name'], 0, 1)) : strtoupper(substr($s['name'], 0, 1));
        ?>
        <a class="star-card" href="/stars.php?slug=<?php echo urlencode($s['slug']); ?>">
            <span class="star-card-photo">
                <?php if ($photo): ?>
                    <img src="<?php echo $h($photo); ?>" alt="<?php echo $h($s['name']); ?>" loading="lazy">
                <?php else: ?>
                    <span class="star-card-initial" aria-hidden="true"><?php echo $h($initial); ?></span>
                <?php endif; ?>
                <?php if ($hasVideo): ?><span class="star-card-play" title="Has video" aria-label="Has video">&#9654;</span><?php endif; ?>
                <?php if (!empty($s['is_featured'])): ?><span class="star-card-flag">Featured</span><?php endif; ?>
            </span>
            <span class="star-card-body">
                <strong><?php echo $h($s['name']); ?></strong>
                <small><?php echo $s['category'] === 'actress' ? 'Actress' : 'Actor'; ?><?php echo !empty($s['known_for']) ? ' · ' . $h($s['known_for']) : ''; ?></small>
            </span>
        </a>
        <?php
    }
}
