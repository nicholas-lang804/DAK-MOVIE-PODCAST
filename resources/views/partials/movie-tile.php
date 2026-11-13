<?php
/** Poster tile for a movie. Usage: tmz_movie_tile($movieRow) with id/title/slug/poster_path. */
if (!function_exists('tmz_movie_tile')) {
    function tmz_movie_tile($m) {
        $h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
        $poster = !empty($m['poster_path']) ? '/' . ltrim($m['poster_path'], '/') : '';
        ?>
        <a class="movie-tile" href="/movies.php?slug=<?php echo urlencode($m['slug']); ?>">
            <span class="movie-tile-poster">
                <?php if ($poster): ?><img src="<?php echo $h($poster); ?>" alt="<?php echo $h($m['title']); ?>" loading="lazy">
                <?php else: ?><span class="movie-tile-fallback"><?php echo $h($m['title']); ?></span><?php endif; ?>
            </span>
            <span class="movie-tile-title"><?php echo $h($m['title']); ?></span>
        </a>
        <?php
    }
}
