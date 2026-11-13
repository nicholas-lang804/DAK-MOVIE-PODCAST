<?php
/**
 * Home Page View
 * Public homepage
 */
?>

<div class="home-layout">
    <div class="home-main-column">
        <!-- Featured Movie Showcase -->
        <section class="movie-showcase">
    <div class="movie-showcase-shell">
        <?php $featuredMovie = $featuredShowcaseMovies[0] ?? null; ?>
        <?php if ($featuredMovie): ?>
            <?php $featuredMovieLink = (($featuredMovie['access_type'] ?? 'free') === 'paid') ? '/TMZ%20PODCAST/payment.php?movie_id=' . (int)$featuredMovie['id'] : '/TMZ%20PODCAST/movies.php?slug=' . urlencode($featuredMovie['slug']) . '#watch-movie'; ?>
            <?php $featuredMovieLabel = (($featuredMovie['access_type'] ?? 'free') === 'paid') ? 'Pay to Watch' : 'Watch now'; ?>
            <div class="movie-showcase-feature">
                <div class="movie-showcase-poster">
                    <?php if (!empty($featuredMovie['poster_path'])): ?>
                        <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($featuredMovie['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($featuredMovie['title']); ?>">
                    <?php else: ?>
                        <div class="movie-showcase-fallback">
                            <span>DAK</span>
                            <strong><?php echo htmlspecialchars($featuredMovie['title']); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="movie-showcase-content">
                    <span class="movie-showcase-label">Featured today</span>
                    <h2><?php echo htmlspecialchars($featuredMovie['title']); ?></h2>
                    <div class="movie-showcase-meta">
                        <span class="movie-showcase-tag"><?php echo htmlspecialchars($featuredMovie['genre_names'] ?: 'Adventure'); ?></span>
                        <span><?php echo htmlspecialchars($featuredMovie['release_date'] ? date('Y', strtotime($featuredMovie['release_date'])) : '2025'); ?></span>
                        <?php if (!empty($featuredMovie['runtime_minutes'])): ?>
                            <span><?php echo (int)$featuredMovie['runtime_minutes']; ?> min</span>
                        <?php endif; ?>
                    </div>
                    <p><?php echo htmlspecialchars(substr($featuredMovie['synopsis'] ?? '', 0, 160)); ?><?php echo !empty($featuredMovie['synopsis']) && strlen($featuredMovie['synopsis']) > 160 ? '...' : ''; ?></p>

                    <div class="movie-showcase-actions">
                        <a href="<?php echo htmlspecialchars($featuredMovieLink); ?>" class="btn btn-primary"><?php echo htmlspecialchars($featuredMovieLabel); ?></a>
                        <a href="/TMZ%20PODCAST/membership.php" class="btn btn-outline">Plans from UGX 500</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="movie-showcase-controls" aria-label="Movie navigation">
            <button type="button" class="movie-showcase-arrow prev" aria-label="Previous movie">‹</button>
            <button type="button" class="movie-showcase-arrow next" aria-label="Next movie">›</button>
        </div>

        <div class="movie-showcase-rail" aria-label="Featured movies" hidden>
            <div class="movie-showcase-track">
                <?php foreach (array_merge($featuredShowcaseMovies, $featuredShowcaseMovies) as $movie): ?>
                    <?php $isPaid = (($movie['access_type'] ?? 'free') === 'paid'); ?>
                    <?php $movieUrl = $isPaid ? '/TMZ%20PODCAST/payment.php?movie_id=' . (int)$movie['id'] : '/TMZ%20PODCAST/movies.php?slug=' . urlencode($movie['slug']) . '#watch-movie'; ?>
                    <?php $movieButtonText = $isPaid ? 'Pay to Watch' : 'Watch now'; ?>
                    <article class="movie-showcase-item"
                        data-title="<?php echo htmlspecialchars(addslashes($movie['title'])); ?>"
                        data-image="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim((string)($movie['poster_path'] ?? ''), '/')); ?>"
                        data-link="<?php echo htmlspecialchars($movieUrl); ?>"
                        data-button-label="<?php echo htmlspecialchars($movieButtonText); ?>"
                        data-synopsis="<?php echo htmlspecialchars(addslashes(substr($movie['synopsis'] ?? '', 0, 160))); ?>"
                        data-genre="<?php echo htmlspecialchars(addslashes($movie['genre_names'] ?: 'Adventure')); ?>"
                        data-year="<?php echo htmlspecialchars(!empty($movie['release_date']) ? date('Y', strtotime($movie['release_date'])) : '2025'); ?>"
                        data-runtime="<?php echo htmlspecialchars(!empty($movie['runtime_minutes']) ? (int)$movie['runtime_minutes'] . ' min' : ''); ?>"
                        data-paid="<?php echo $isPaid ? '1' : '0'; ?>">
                        <div class="movie-showcase-item-poster">
                            <?php if (!empty($movie['poster_path'])): ?>
                                <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($movie['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                            <?php else: ?>
                                <div class="movie-showcase-item-fallback"><?php echo htmlspecialchars($movie['title']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="movie-showcase-item-content">
                            <span class="movie-showcase-item-status"><?php echo $isPaid ? 'Paid' : 'New'; ?></span>
                            <strong><?php echo htmlspecialchars($movie['title']); ?></strong>
                            <a href="<?php echo htmlspecialchars($movieUrl); ?>" class="movie-showcase-item-button"><?php echo htmlspecialchars($movieButtonText); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($currentLive)): ?>
<section class="home-live-alert"><div><span class="live-pill">● LIVE NOW</span><h2><?php echo htmlspecialchars($currentLive['title']); ?></h2><p><?php echo htmlspecialchars($currentLive['access_type']==='paid' ? 'Premium live episode — account and payment required.' : 'Free live episode — create an account to join.'); ?></p></div><a class="btn btn-primary" href="/TMZ%20PODCAST/live.php?live_id=<?php echo (int)$currentLive['id']; ?>">Watch Now</a></section>
<?php endif; ?>

<!-- Statistics -->
<section class="statistics">
    <div class="stats-container">
        <div class="stat-item">
            <div class="stat-number"><?php echo number_format($statistics['episodes']); ?></div>
            <div class="stat-label">Podcast Episodes</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo number_format($statistics['articles']); ?></div>
            <div class="stat-label">News Articles</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo number_format($statistics['movies']); ?></div>
            <div class="stat-label">Movies Reviewed</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo number_format($statistics['subscribers']); ?></div>
            <div class="stat-label">Subscribers</div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../partials/stars-strip.php'; ?>

<!-- Featured Episode -->
<?php if (!empty($featuredEpisodes)): ?>
<section class="featured-episode">
    <div class="section-header">
        <h2>Featured Episode</h2>
        <a href="/TMZ%20PODCAST/episodes.php" class="btn btn-outline">View All Episodes</a>
    </div>
    <div class="featured-episode-card">
        <?php $episode = $featuredEpisodes[0]; ?>
        <div class="episode-image">
            <?php if ($episode['featured_image_id']): ?>
                <img src="<?php echo htmlspecialchars(mediaUrl($episode['featured_image_id'])); ?>" alt="<?php echo htmlspecialchars($episode['title']); ?>">
            <?php else: ?>
                <div class="placeholder-image">Episode Image</div>
            <?php endif; ?>
        </div>
        <div class="episode-content">
            <h3><?php echo htmlspecialchars($episode['title']); ?></h3>
            <p class="episode-meta">
                <?php if ($episode['category_name']): ?>
                    <span class="category"><?php echo htmlspecialchars($episode['category_name']); ?></span>
                <?php endif; ?>
                <?php if ($episode['published_at']): ?>
                    <span class="date"><?php echo date('M d, Y', strtotime($episode['published_at'])); ?></span>
                <?php endif; ?>
            </p>
            <p class="episode-description"><?php echo htmlspecialchars(substr($episode['description'] ?? '', 0, 200)); ?>...</p>
            <a href="/TMZ%20PODCAST/episodes.php?slug=<?php echo urlencode($episode['slug']); ?>" class="btn btn-primary">Listen Now</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Latest Episodes -->
<section class="latest-episodes">
    <div class="section-header">
        <h2>Latest Episodes</h2>
        <a href="/TMZ%20PODCAST/episodes.php" class="btn btn-outline">View All</a>
    </div>
    <div class="episodes-grid">
        <?php foreach ($latestEpisodes as $episode): ?>
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
                    <p class="episode-meta">
                        <?php if ($episode['category_name']): ?>
                            <span class="category"><?php echo htmlspecialchars($episode['category_name']); ?></span>
                        <?php endif; ?>
                    </p>
                    <a href="/TMZ%20PODCAST/episodes.php?slug=<?php echo urlencode($episode['slug']); ?>" class="btn btn-small">Listen</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Featured Articles -->
<section class="featured-articles">
    <div class="section-header">
        <h2>Entertainment News</h2>
        <a href="/TMZ%20PODCAST/news.php" class="btn btn-outline">View All News</a>
    </div>
    <div class="articles-grid">
        <?php foreach ($featuredArticles as $article): ?>
            <div class="article-card">
                <div class="article-image">
                    <?php if ($article['featured_image_id']): ?>
                        <img src="<?php echo htmlspecialchars(mediaUrl($article['featured_image_id'])); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
                    <?php else: ?>
                        <div class="placeholder-image">Article</div>
                    <?php endif; ?>
                </div>
                <div class="article-content">
                    <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                    <p class="article-meta">
                        <?php if ($article['category_name']): ?>
                            <span class="category"><?php echo htmlspecialchars($article['category_name']); ?></span>
                        <?php endif; ?>
                        <?php if ($article['published_at']): ?>
                            <span class="date"><?php echo date('M d, Y', strtotime($article['published_at'])); ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="article-excerpt"><?php echo htmlspecialchars(substr($article['excerpt'] ?? '', 0, 150)); ?>...</p>
                    <a href="/TMZ%20PODCAST/news.php?slug=<?php echo urlencode($article['slug']); ?>" class="btn btn-small">Read More</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Latest Movies -->
<section class="latest-movies">
    <div class="section-header">
        <h2>Latest Movies</h2>
        <a href="/TMZ%20PODCAST/movies.php" class="btn btn-outline">View All Movies</a>
    </div>
    <div class="movies-grid">
        <?php foreach ($latestMovies as $movie): ?>
            <div class="movie-card">
                <div class="movie-poster">
                    <?php if ($movie['poster_path']): ?>
                        <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($movie['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    <?php else: ?>
                        <div class="placeholder-image">Movie Poster</div>
                    <?php endif; ?>
                </div>
                <div class="movie-content">
                    <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                    <p class="movie-meta">
                        <?php if ($movie['release_date']): ?>
                            <span class="date"><?php echo date('M d, Y', strtotime($movie['release_date'])); ?></span>
                        <?php endif; ?>
                        <?php if ($movie['director']): ?>
                            <span class="director">Dir: <?php echo htmlspecialchars($movie['director']); ?></span>
                        <?php endif; ?>
                    </p>
                    <a href="/TMZ%20PODCAST/movies.php?slug=<?php echo urlencode($movie['slug']); ?>" class="btn btn-small">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php tmz_ad_slot('between_content', 'leaderboard', 3); ?>

<!-- Upcoming Events -->
<?php if (!empty($upcomingEvents)): ?>
<section class="upcoming-events">
    <div class="section-header">
        <h2>Upcoming Events</h2>
        <a href="/TMZ%20PODCAST/events.php" class="btn btn-outline">View All Events</a>
    </div>
    <div class="events-grid">
        <?php foreach ($upcomingEvents as $event): ?>
            <div class="event-card">
                <div class="event-image">
                    <?php if (!empty($event['poster_path'])): ?>
                        <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($event['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                    <?php else: ?>
                        <div class="placeholder-image">Event</div>
                    <?php endif; ?>
                </div>
                <div class="event-content">
                    <h3><?php echo htmlspecialchars($event['title']); ?><?php if (empty($event['user_id'])): ?> <span class="discount-pill">Official Event</span><?php endif; ?></h3>
                    <p class="event-meta">
                        <?php if ($event['event_date']): ?>
                            <span class="date"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                        <?php endif; ?>
                        <?php if ($event['location']): ?>
                            <span class="location"><?php echo htmlspecialchars($event['location']); ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if (isset($event['min_price']) && $event['min_price'] !== null): ?><p class="event-from-price">Tickets from <strong><?php echo htmlspecialchars(Event::money($event['price_currency'], $event['min_price'])); ?></strong><?php if (!empty($event['active_discounts'])): ?> <span class="discount-pill">Discount available</span><?php endif; ?></p><?php endif; ?>
                    <a href="/TMZ%20PODCAST/events.php?slug=<?php echo urlencode($event['slug']); ?>" class="btn btn-small btn-primary">Tickets &amp; details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Hosts & Guests -->
<section class="hosts-guests">
    <div class="section-header">
        <h2>Meet Our Team</h2>
        <a href="/TMZ%20PODCAST/hosts.php" class="btn btn-outline">View All</a>
    </div>
    <div class="hosts-grid">
        <?php foreach (array_slice($hosts, 0, 4) as $host): ?>
            <div class="host-card">
                <div class="host-image">
                    <?php if ($host['profile_image_path']): ?>
                        <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($host['profile_image_path'], '/')); ?>" alt="<?php echo htmlspecialchars($host['name']); ?>">
                    <?php else: ?>
                        <div class="placeholder-image">Host</div>
                    <?php endif; ?>
                </div>
                <div class="host-content">
                    <h3><?php echo htmlspecialchars($host['name']); ?></h3>
                    <?php if ($host['role']): ?>
                        <p class="host-role"><?php echo htmlspecialchars($host['role']); ?></p>
                    <?php endif; ?>
                    <a href="/TMZ%20PODCAST/hosts.php?slug=<?php echo urlencode($host['slug']); ?>" class="btn btn-small">View Profile</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!empty($featuredVideos)): ?>
<section class="video-showcase">
    <div class="section-header">
        <div><span class="eyebrow">DAK WATCH</span><h2>Latest Videos</h2></div>
        <a href="/TMZ%20PODCAST/videos.php" class="btn btn-outline">View Video Centre</a>
    </div>
    <div class="video-grid">
        <?php foreach ($featuredVideos as $video): ?>
            <article class="video-card">
                <video controls preload="metadata" playsinline src="/TMZ%20PODCAST/video-stream.php?media_id=<?php echo (int)$video['id']; ?>"></video>
                <div class="video-card-body">
                    <span class="video-kicker">VIDEO</span>
                    <h3><?php echo htmlspecialchars($video['alt_text'] ?: pathinfo($video['original_filename'], PATHINFO_FILENAME)); ?></h3>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Newsletter -->
<section class="newsletter" id="newsletter">
    <div class="newsletter-content">
        <h2>Stay Updated</h2>
        <p>Subscribe to our newsletter for the latest episodes, news, and exclusive content.</p>
        <?php if (isset($_SESSION['newsletter_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['newsletter_success']); ?></div>
            <?php unset($_SESSION['newsletter_success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['newsletter_error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['newsletter_error']); ?></div>
            <?php unset($_SESSION['newsletter_error']); ?>
        <?php endif; ?>
        <form method="POST" action="/TMZ%20PODCAST/subscribe.php" class="newsletter-form">
            <?php echo CSRF::getField(); ?>
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit" class="btn btn-primary">Subscribe</button>
        </form>
    </div>
</section>

<!-- Advertisements -->
        <?php tmz_ad_slot('homepage', 'leaderboard', 3); ?>
    </div>

    <aside class="home-side-column" aria-label="Trending movies and events">
        <div class="home-rail-card">
            <div class="home-rail-header">
                <span class="eyebrow">Trending</span>
                <h3>New Movies</h3>
            </div>
            <div class="home-rail-list">
                <?php foreach (array_slice($latestMovies, 0, 4) as $movie): ?>
                    <a class="home-mini-item" href="/TMZ%20PODCAST/movies.php?slug=<?php echo urlencode($movie['slug']); ?>">
                        <div class="home-mini-poster">
                            <?php if (!empty($movie['poster_path'])): ?>
                                <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($movie['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                            <?php else: ?>
                                <span>DAK</span>
                            <?php endif; ?>
                        </div>
                        <div class="home-mini-copy">
                            <strong><?php echo htmlspecialchars($movie['title']); ?></strong>
                            <small><?php echo htmlspecialchars(!empty($movie['release_date']) ? date('M d, Y', strtotime($movie['release_date'])) : 'New arrival'); ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="home-rail-card">
            <div class="home-rail-header">
                <span class="eyebrow">Tickets</span>
                <h3>Live Events</h3>
            </div>
            <div class="home-rail-list">
                <?php foreach (array_slice($upcomingEvents, 0, 3) as $event): ?>
                    <a class="home-mini-item event-mini" href="/TMZ%20PODCAST/events.php?slug=<?php echo urlencode($event['slug']); ?>">
                        <div class="home-mini-copy">
                            <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                            <small><?php echo htmlspecialchars(!empty($event['event_date']) ? date('M d, Y', strtotime($event['event_date'])) : 'Coming soon'); ?><?php echo !empty($event['location']) ? ' · ' . htmlspecialchars($event['location']) : ''; ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
        $sideStars = [];
        try { $sideStars = array_slice((new Star())->getFeatured(4), 0, 4); } catch (Throwable $e) { error_log('Side stars: ' . $e->getMessage()); }
        if (!empty($sideStars)): ?>
        <div class="home-rail-card">
            <div class="home-rail-header">
                <span class="eyebrow">Spotlight</span>
                <h3>Actors &amp; Actresses</h3>
            </div>
            <div class="home-rail-list">
                <?php foreach ($sideStars as $ss): ?>
                    <a class="home-mini-item" href="/TMZ%20PODCAST/stars.php?slug=<?php echo urlencode($ss['slug']); ?>">
                        <div class="home-mini-poster">
                            <?php if (!empty($ss['photo_path'])): ?>
                                <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($ss['photo_path'], '/')); ?>" alt="<?php echo htmlspecialchars($ss['name']); ?>" loading="lazy">
                            <?php else: ?><span>DAK</span><?php endif; ?>
                        </div>
                        <div class="home-mini-copy">
                            <strong><?php echo htmlspecialchars($ss['name']); ?></strong>
                            <small><?php echo $ss['category'] === 'actress' ? 'Actress' : 'Actor'; ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php tmz_ad_slot('sidebar', 'rail', 3); ?>
    </aside>
</div>
