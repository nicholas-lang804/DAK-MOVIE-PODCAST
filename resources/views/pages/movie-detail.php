<?php
/**
 * Movie Detail Page View
 * Single movie view with reviews
 */
?>

<article class="movie-detail">
    <div class="movie-header">
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
        </div>
        <div class="movie-info">
            <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
            <?php
            require_once __DIR__ . '/../../../app/Models/SiteExtras.php';
            $tmzExtras = new SiteExtras();
            $tmzInList = !empty($_SESSION['user_id']) && $tmzExtras->inWatchlist((int)$_SESSION['user_id'], (int)$movie['id']);
            ?>
            <div class="movie-extra-actions">
                <button type="button" class="btn btn-outline" data-watchlist="<?php echo (int)$movie['id']; ?>"
                        data-csrf="<?php echo htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8'); ?>"
                        data-logged-in="<?php echo !empty($_SESSION['user_id']) ? '1' : '0'; ?>"
                        aria-pressed="<?php echo $tmzInList ? 'true' : 'false'; ?>"><?php echo $tmzInList ? '✓ In My List' : '＋ Add to My List'; ?></button>
            </div>
            <p class="movie-meta">
                <?php if ($movie['release_date']): ?>
                    <span class="date"><?php echo date('M d, Y', strtotime($movie['release_date'])); ?></span>
                <?php endif; ?>
                <?php if ($movie['director']): ?>
                    <span class="director">Director: <?php echo htmlspecialchars($movie['director']); ?></span>
                <?php endif; ?>
                <?php if ($movie['runtime_minutes']): ?>
                    <span class="runtime"><?php echo $movie['runtime_minutes']; ?> min</span>
                <?php endif; ?>
            </p>
            
            <?php if ($averageRating > 0): ?>
                <div class="movie-rating">
                    <span class="rating"><?php echo number_format($averageRating, 1); ?>/10</span>
                    <span class="review-count">(<?php echo $reviewCount; ?> reviews)</span>
                </div>
            <?php endif; ?>
            
            <?php if ($movie['synopsis']): ?>
                <p class="movie-synopsis"><?php echo nl2br(htmlspecialchars($movie['synopsis'])); ?></p>
            <?php endif; ?>
            
            <?php if ($movie['trailer_url']): ?>
                <div class="trailer-player">
                    <iframe src="<?php echo htmlspecialchars($movie['trailer_url']); ?>" frameborder="0" allowfullscreen></iframe>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($movie['video_path']) && $canWatch): ?>
        <section id="watch-movie" class="movie-video-player" aria-labelledby="watch-movie-heading">
            <div class="movie-video-heading">
                <div>
                    <span class="eyebrow">WATCH NOW</span>
                    <h2 id="watch-movie-heading">Watch <?php echo htmlspecialchars($movie['title']); ?></h2>
                </div>
                <span class="movie-video-file"><?php echo htmlspecialchars($movie['video_filename'] ?? 'Full movie video'); ?></span>
            </div>
            <?php
            // HLS when a packaged stream exists for this movie's video asset; signed, expiring URL.
            $hlsSrc = null;
            $vidMediaId = (int)($movie['video_media_id'] ?? 0);
            if ($vidMediaId > 0) {
                require_once __DIR__ . '/../../../app/Models/UploadJob.php';
                require_once __DIR__ . '/../../../app/Models/SiteSetting.php';
                $hlsRen = (new UploadJob())->rendition($vidMediaId, 'hls', 'local');
                if ($hlsRen && $hlsRen['status'] === 'ready') {
                    $sec = (new SiteSetting())->get('media_signing_secret');
                    if (!$sec) { $sec = 'tmz-media'; } // media-play.php auto-creates the real secret on first hit
                    $hexp = time() + 6 * 3600;
                    $htok = hash_hmac('sha256', $vidMediaId . '|' . $hexp . '|hls', $sec);
                    $hlsSrc = '/TMZ%20PODCAST/media-play.php?hls=' . $vidMediaId . '&tok=' . urlencode($htok) . '&exp=' . $hexp . '&q=hls';
                }
            }
            ?>
            <video id="moviePlayer" controls preload="metadata" playsinline>
                <source src="/TMZ%20PODCAST/media-play.php?movie_id=<?php echo (int)$movie['id']; ?>" type="<?php echo htmlspecialchars($movie['video_mime_type'] ?? 'video/mp4'); ?>">
                Your browser does not support this video format.
            </video>
            <?php if ($hlsSrc): ?>
            <script src="/TMZ%20PODCAST/assets/js/hls.min.js"></script>
            <script>
            (function(){
                var v = document.getElementById('moviePlayer');
                var src = <?php echo json_encode($hlsSrc); ?>;
                if (window.Hls && Hls.isSupported()) {
                    var hls = new Hls({ maxBufferLength: 30 });
                    hls.loadSource(src); hls.attachMedia(v);
                    hls.on(Hls.Events.ERROR, function(e,d){ if(d.fatal){ hls.destroy(); /* <video><source> mp4 fallback already in DOM */ } });
                } else if (v.canPlayType('application/vnd.apple.mpegurl')) {
                    v.src = src; // Safari native HLS
                }
            })();
            </script>
            <?php endif; ?>
            <p class="movie-stream-only-note">Streaming only — this title cannot be downloaded.</p>
        </section>
    <?php elseif (!empty($movie['video_path']) && $movie['access_type'] === 'paid'): ?>
        <section class="movie-access-required" aria-labelledby="access-heading">
            <span class="eyebrow">PREMIUM SCREENING</span>
            <h2 id="access-heading">Unlock <?php echo htmlspecialchars($movie['title']); ?></h2>
            <p>This movie is available after payment. Watch access is granted to your account after Pesapal confirms payment.</p>
            <strong><?php echo htmlspecialchars($movie['price_currency']); ?> <?php echo number_format((float)$movie['price'], 2); ?></strong>
            <a class="btn btn-primary" href="/TMZ%20PODCAST/payment.php?movie_id=<?php echo (int)$movie['id']; ?>">Continue to Pesapal payment</a>
        </section>
    <?php endif; ?>

    <?php
    require_once __DIR__ . '/../partials/star-card.php';
    $tmzCast = isset($tmzExtras) ? $tmzExtras->castForMovie((int)$movie['id']) : [];
    if (!empty($tmzCast)): ?>
        <section class="stars-section movie-cast" aria-labelledby="cast-heading">
            <div class="cine-heading"><div><span class="cine-eyebrow">Cast</span><h2 id="cast-heading">Starring</h2></div></div>
            <div class="stars-rail"><?php foreach ($tmzCast as $castStar) { tmz_star_card($castStar); } ?></div>
        </section>
    <?php endif; ?>

    <!-- Reviews -->
    <section class="reviews-section">
        <h2>Reviews</h2>
        
        <?php if (!empty($reviews)): ?>
            <div class="reviews-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="review">
                        <div class="review-header">
                            <strong><?php echo htmlspecialchars($review['review_title'] ?? 'Review'); ?></strong>
                            <span class="review-rating"><?php echo number_format($review['rating'], 1); ?>/10</span>
                        </div>
                        <p class="review-content"><?php echo nl2br(htmlspecialchars($review['review_content'])); ?></p>
                        <div class="review-meta">
                            <?php if ($review['author_name']): ?>
                                <span class="author">By <?php echo htmlspecialchars($review['author_name']); ?></span>
                            <?php endif; ?>
                            <?php if ($review['published_at']): ?>
                                <span class="date"><?php echo date('M d, Y', strtotime($review['published_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No reviews yet for this movie.</p>
        <?php endif; ?>
    </section>

    <!-- Comments -->
    <section class="comments-section">
        <h2>Comments</h2>
        
        <?php if (isset($_SESSION['comment_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['comment_success']); ?></div>
            <?php unset($_SESSION['comment_success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['comment_error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['comment_error']); ?></div>
            <?php unset($_SESSION['comment_error']); ?>
        <?php endif; ?>
        
        <?php if (!empty($comments)): ?>
            <div class="comments-list">
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-header">
                            <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                            <span class="comment-date"><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></span>
                        </div>
                        <p class="comment-content"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No comments yet. Be the first to comment!</p>
        <?php endif; ?>
        
        <form method="POST" action="/TMZ%20PODCAST/movies.php" class="comment-form">
            <?php echo CSRF::getField(); ?>
            <input type="hidden" name="movie_id" value="<?php echo $movie['id']; ?>">
            <div class="form-group">
                <label for="user_name">Name *</label>
                <input type="text" id="user_name" name="user_name" required>
            </div>
            <div class="form-group">
                <label for="user_email">Email</label>
                <input type="email" id="user_email" name="user_email">
            </div>
            <div class="form-group">
                <label for="comment">Comment *</label>
                <textarea id="comment" name="comment" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Comment</button>
        </form>
    </section>
</article>
