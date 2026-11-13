<?php
/** Public star profile: picture, write-up and video. Open to everyone. */
require_once __DIR__ . '/../partials/star-card.php';
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$photo = !empty($star['photo_path']) ? '/' . ltrim($star['photo_path'], '/') : '';
$video = !empty($star['video_path']) ? '/' . ltrim($star['video_path'], '/') : '';
$embed = Star::embedUrl($star['video_url'] ?? '');
?>
<article class="star-detail">
    <nav class="cine-crumbs" aria-label="Breadcrumb"><a href="/stars.php">&larr; All actors &amp; actresses</a></nav>

    <header class="star-hero">
        <div class="star-hero-photo">
            <?php if ($photo): ?>
                <img src="<?php echo $h($photo); ?>" alt="<?php echo $h($star['name']); ?>">
            <?php else: ?>
                <span class="star-card-initial" aria-hidden="true"><?php echo $h(function_exists('mb_substr') ? mb_strtoupper(mb_substr($star['name'], 0, 1)) : strtoupper(substr($star['name'], 0, 1))); ?></span>
            <?php endif; ?>
        </div>
        <div class="star-hero-copy">
            <span class="cine-eyebrow"><?php echo $star['category'] === 'actress' ? 'Actress' : 'Actor'; ?><?php echo !empty($star['is_featured']) ? ' · Featured' : ''; ?></span>
            <h1><?php echo $h($star['name']); ?></h1>
            <?php if (!empty($star['known_for'])): ?><p class="star-known"><?php echo $h($star['known_for']); ?></p><?php endif; ?>
            <?php if (!empty($star['biography'])): ?>
                <div class="star-bio"><?php echo nl2br($h($star['biography'])); ?></div>
            <?php else: ?>
                <p class="cine-empty">More about <?php echo $h($star['name']); ?> is coming soon.</p>
            <?php endif; ?>
            <div class="star-actions">
                <a class="btn btn-primary" href="/movies.php">Watch movies</a>
                <?php if (!function_exists('isLoggedIn') || !isLoggedIn()): ?>
                    <a class="btn btn-outline" href="/register.php">Join free</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($video || $embed): ?>
        <section class="star-video" aria-label="Video of <?php echo $h($star['name']); ?>">
            <div class="cine-heading"><div><span class="cine-eyebrow">Watch</span><h2>Video</h2></div></div>
            <?php if ($video): ?>
                <div class="cine-player"><video controls preload="metadata" playsinline<?php echo $photo ? ' poster="' . $h($photo) . '"' : ''; ?> src="<?php echo $h($video); ?>"></video></div>
            <?php endif; ?>
            <?php if ($embed): ?>
                <div class="cine-player"><iframe src="<?php echo $h($embed); ?>" title="<?php echo $h($star['name']); ?> video" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php
    require_once __DIR__ . '/../partials/movie-tile.php';
    require_once __DIR__ . '/../../../app/Models/SiteExtras.php';
    $starFilms = (new SiteExtras())->moviesForStar((int)$star['id']);
    if (!empty($starFilms)): ?>
        <section class="stars-section">
            <div class="cine-heading"><div><span class="cine-eyebrow">Filmography</span><h2>Watch <?php echo $h($star['name']); ?> in</h2></div></div>
            <div class="movie-tile-grid"><?php foreach ($starFilms as $film) { tmz_movie_tile($film); } ?></div>
        </section>
    <?php endif; ?>

    <?php tmz_ad_slot('homepage', 'leaderboard', 3); ?>

    <?php if (!empty($related)): ?>
        <section class="stars-section">
            <div class="cine-heading"><div><span class="cine-eyebrow">More</span><h2>More stars</h2></div></div>
            <div class="stars-rail">
                <?php foreach ($related as $r) { tmz_star_card($r); } ?>
            </div>
        </section>
    <?php endif; ?>
</article>
