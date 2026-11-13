<?php
/** Site-wide search results. Expects $query, $results, $resultCount. */
require_once __DIR__ . '/../partials/star-card.php';
require_once __DIR__ . '/../partials/movie-tile.php';
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<section class="search-page">
    <header class="cine-page-hero">
        <span class="cine-eyebrow">Find anything</span>
        <h1>Search</h1>
        <form class="cine-search search-page-form" method="GET" action="/TMZ%20PODCAST/search.php" role="search">
            <input type="search" name="q" value="<?php echo $h($query); ?>" placeholder="Movies, actors, episodes, news, live shows…" aria-label="Search the site" autofocus>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </header>

    <?php if ($query === ''): ?>
        <p class="cine-empty">Type at least two letters to search movies, actors and actresses, episodes, news and live shows.</p>
    <?php elseif ($resultCount === 0): ?>
        <p class="cine-empty">Nothing found for “<?php echo $h($query); ?>”. Try a different spelling or a shorter word.</p>
    <?php else: ?>
        <p class="search-count"><?php echo (int)$resultCount; ?> result<?php echo $resultCount === 1 ? '' : 's'; ?> for “<?php echo $h($query); ?>”</p>

        <?php if (!empty($results['movies'])): ?>
            <section class="search-group"><h2>Movies</h2>
                <div class="movie-tile-grid"><?php foreach ($results['movies'] as $m) { tmz_movie_tile($m); } ?></div>
            </section>
        <?php endif; ?>

        <?php if (!empty($results['stars'])): ?>
            <section class="search-group"><h2>Actors &amp; actresses</h2>
                <div class="stars-grid"><?php foreach ($results['stars'] as $s) { tmz_star_card($s); } ?></div>
            </section>
        <?php endif; ?>

        <?php foreach ([['episodes', 'Podcast episodes', 'episodes.php'], ['articles', 'News', 'news.php']] as $g): ?>
            <?php if (!empty($results[$g[0]])): ?>
                <section class="search-group"><h2><?php echo $g[1]; ?></h2>
                    <ul class="search-list">
                        <?php foreach ($results[$g[0]] as $r): ?>
                            <li><a href="/TMZ%20PODCAST/<?php echo $g[2]; ?>?slug=<?php echo urlencode($r['slug']); ?>"><?php echo $h($r['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!empty($results['live'])): ?>
            <section class="search-group"><h2>Live shows</h2>
                <ul class="search-list">
                    <?php foreach ($results['live'] as $r): ?>
                        <li><a href="/TMZ%20PODCAST/live.php?live_id=<?php echo (int)$r['id']; ?>"><?php echo $h($r['title']); ?></a>
                            <small><?php echo $r['status'] === 'live' ? '● live now' : ($r['status'] === 'ended' ? 'replay' : 'upcoming'); ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
