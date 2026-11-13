<?php
/** Member watchlist. Expects $listMovies. */
require_once __DIR__ . '/../partials/movie-tile.php';
?>
<section class="search-page">
    <header class="cine-page-hero">
        <span class="cine-eyebrow">Saved for later</span>
        <h1>My List</h1>
        <p>Movies you saved to watch next.</p>
    </header>
    <?php if (empty($listMovies)): ?>
        <p class="cine-empty">Your list is empty. Open any movie and tap “Add to My List”.</p>
        <p style="text-align:center"><a class="btn btn-primary" href="/TMZ%20PODCAST/movies.php">Browse movies</a></p>
    <?php else: ?>
        <div class="movie-tile-grid"><?php foreach ($listMovies as $m) { tmz_movie_tile($m); } ?></div>
    <?php endif; ?>
</section>
