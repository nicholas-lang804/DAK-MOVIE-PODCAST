<?php
/** Public stars listing (actors & actresses). Works for visitors and logged-in members. */
require_once __DIR__ . '/../partials/star-card.php';
$qs = function ($extra) use ($category, $search) {
    $params = array_filter(['type' => $category, 'q' => $search], function ($v) { return $v !== '' && $v !== null; });
    return '/stars.php?' . http_build_query(array_merge($params, $extra));
};
?>
<section class="stars-page">
    <header class="cine-page-hero">
        <span class="cine-eyebrow">The DAK Film</span>
        <h1>Actors &amp; Actresses</h1>
        <p>Meet the faces behind your favourite films: their stories, pictures and videos.</p>
    </header>

    <div class="cine-toolbar">
        <nav class="cine-pills" aria-label="Filter stars">
            <a href="<?php echo $qs(['type' => '', 'page' => 1]); ?>" class="<?php echo $category === '' ? 'is-active' : ''; ?>">All</a>
            <a href="<?php echo $qs(['type' => 'actor', 'page' => 1]); ?>" class="<?php echo $category === 'actor' ? 'is-active' : ''; ?>">Actors</a>
            <a href="<?php echo $qs(['type' => 'actress', 'page' => 1]); ?>" class="<?php echo $category === 'actress' ? 'is-active' : ''; ?>">Actresses</a>
        </nav>
        <form class="cine-search" method="GET" action="/stars.php" role="search">
            <?php if ($category !== ''): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($category); ?>"><?php endif; ?>
            <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search a name…" aria-label="Search stars">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <?php if (empty($stars)): ?>
        <p class="cine-empty">No stars found<?php echo $search !== '' ? ' for “' . htmlspecialchars($search) . '”' : ''; ?>.</p>
    <?php else: ?>
        <div class="stars-grid">
            <?php foreach ($stars as $s) { tmz_star_card($s); } ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="cine-pager" aria-label="Pages">
            <?php if ($page > 1): ?><a href="<?php echo $qs(['page' => $page - 1]); ?>">&larr; Previous</a><?php endif; ?>
            <span>Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
            <?php if ($page < $totalPages): ?><a href="<?php echo $qs(['page' => $page + 1]); ?>">Next &rarr;</a><?php endif; ?>
        </nav>
    <?php endif; ?>

    <?php tmz_ad_slot('homepage', 'leaderboard', 3); ?>
</section>
