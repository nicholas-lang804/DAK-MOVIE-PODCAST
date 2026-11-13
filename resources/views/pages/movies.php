<?php
/**
 * Movies Page View
 * Movie catalogue
 */
?>

<section class="page-header">
    <h1>Movies & Reviews</h1>
    <p>Browse our movie catalogue and read professional reviews.</p>
</section>

<section class="genre-directory" aria-label="Movie genres">
    <div class="genre-directory-heading">
        <span class="eyebrow">EXPLORE THE CATALOGUE</span>
        <h2>Browse by genre</h2>
        <p>Choose a genre to see every published film in that collection.</p>
    </div>
    <div class="genre-directory-grid">
        <?php foreach (($genres ?? []) as $genre): ?>
            <a class="genre-directory-card <?php echo ((string)$selectedGenre === (string)$genre['id']) ? 'active' : ''; ?>" href="/TMZ%20PODCAST/movies.php?genre=<?php echo (int)$genre['id']; ?>">
                <span class="genre-card-number"><?php echo str_pad((string)$genre['id'], 2, '0', STR_PAD_LEFT); ?></span>
                <strong><?php echo htmlspecialchars($genre['name']); ?></strong>
                <span><?php echo (int)($genre['movie_count'] ?? 0); ?> <?php echo ((int)($genre['movie_count'] ?? 0) === 1) ? 'movie' : 'movies'; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Search and Filters -->
<section class="filters">
    <form method="GET" action="/TMZ%20PODCAST/movies.php" class="filter-form">
        <div class="filter-group">
            <input type="text" name="q" placeholder="Search movies..." value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
        </div>
        <div class="filter-group">
            <select name="genre">
                <option value="">All Genres</option>
                <?php foreach ($genres as $genre): ?>
                    <option value="<?php echo $genre['id']; ?>" <?php echo ($selectedGenre == $genre['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($genre['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>
</section>

<!-- Movies Grid -->
<section class="movies-library">
    <?php if (empty($movies)): ?>
        <div class="no-results">
            <p>No movies found. Try adjusting your search or filters.</p>
        </div>
    <?php else: ?>
        <div class="movies-grid">
            <?php foreach ($movies as $movie): ?>
                <div class="movie-card">
                    <div class="movie-poster">
                        <?php if ($movie['poster_path']): ?>
                            <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($movie['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                        <?php else: ?>
                            <div class="movie-cover-fallback">
                                <span>DAK</span>
                                <strong><?php echo htmlspecialchars($movie['title']); ?></strong>
                                <small>FEATURED FILM</small>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($movie['video_path'])): ?>
                            <span class="movie-play-badge">VIDEO AVAILABLE</span>
                            <a class="movie-quick-play" href="/TMZ%20PODCAST/movies.php?slug=<?php echo urlencode($movie['slug']); ?>#watch-movie" aria-label="Play <?php echo htmlspecialchars($movie['title']); ?>">
                                <span aria-hidden="true">▶</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="movie-content">
                        <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                        <p class="movie-meta">
                            <?php if ($movie['release_date']): ?>
                                <span class="date"><?php echo date('M d, Y', strtotime($movie['release_date'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($movie['genre_names'])): ?>
                                <span class="movie-genre-label"><?php echo htmlspecialchars($movie['genre_names']); ?></span>
                            <?php endif; ?>
                            <?php if (($movie['access_type'] ?? 'free') === 'paid'): ?>
                                <span class="movie-price-label">PAID · <?php echo htmlspecialchars($movie['price_currency']); ?> <?php echo number_format((float)$movie['price'], 2); ?></span>
                            <?php else: ?>
                                <span class="movie-free-label">FREE TO WATCH</span>
                            <?php endif; ?>
                            <?php if ($movie['director']): ?>
                                <span class="director">Dir: <?php echo htmlspecialchars($movie['director']); ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="movie-synopsis"><?php echo htmlspecialchars(substr($movie['synopsis'] ?? '', 0, 150)); ?>...</p>
                        <div class="movie-card-actions">
                            <?php if (!empty($movie['video_path'])): ?>
                                <a href="/TMZ%20PODCAST/movies.php?slug=<?php echo urlencode($movie['slug']); ?>#watch-movie" class="btn btn-primary movie-watch-button">Watch Full Movie</a>
                            <?php else: ?>
                                <span class="movie-unavailable">Video not yet available</span>
                            <?php endif; ?>
                            <a href="/TMZ%20PODCAST/movies.php?slug=<?php echo urlencode($movie['slug']); ?>" class="btn btn-outline">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo $searchQuery ? '&q=' . urlencode($searchQuery) : ''; ?><?php echo $selectedGenre ? '&genre=' . $selectedGenre : ''; ?>" class="btn btn-outline">Previous</a>
                <?php endif; ?>
                
                <span class="page-info">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo $searchQuery ? '&q=' . urlencode($searchQuery) : ''; ?><?php echo $selectedGenre ? '&genre=' . $selectedGenre : ''; ?>" class="btn btn-outline">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
