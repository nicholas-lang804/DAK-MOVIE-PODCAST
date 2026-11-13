<?php
/**
 * Episode Detail Page View
 * Single episode view
 */
?>

<section class="episode-detail">
    <div class="episode-header">
        <div class="episode-image">
            <?php if ($episode['featured_image_id']): ?>
                <img src="<?php echo htmlspecialchars(mediaUrl($episode['featured_image_id'])); ?>" alt="<?php echo htmlspecialchars($episode['title']); ?>">
            <?php else: ?>
                <div class="placeholder-image">Episode Image</div>
            <?php endif; ?>
        </div>
        <div class="episode-info">
            <h1><?php echo htmlspecialchars($episode['title']); ?></h1>
            <p class="episode-meta">
                <?php if ($episode['category_name']): ?>
                    <span class="category"><?php echo htmlspecialchars($episode['category_name']); ?></span>
                <?php endif; ?>
                <?php if ($episode['published_at']): ?>
                    <span class="date"><?php echo date('M d, Y', strtotime($episode['published_at'])); ?></span>
                <?php endif; ?>
                <?php if ($episode['duration_seconds']): ?>
                    <span class="duration"><?php echo gmdate('H:i:s', $episode['duration_seconds']); ?></span>
                <?php endif; ?>
            </p>
            <p class="episode-description"><?php echo nl2br(htmlspecialchars($episode['description'] ?? '')); ?></p>
            
            <?php if ($episode['audio_media_id']): ?>
                <div class="audio-player">
                    <audio controls>
                        <source src="<?php echo htmlspecialchars(mediaUrl($episode['audio_media_id'])); ?>" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            <?php endif; ?>
            
            <?php if ($episode['video_url']): ?>
                <div class="video-player">
                    <?php
                    // Route through the protected player: fixes the 403 from direct storage URLs
                    // and applies the same access rules as movies.
                    $evUrl = (string)$episode['video_url'];
                    $evSrc = $evUrl;
                    if (strpos($evUrl, '/storage/') !== false) {
                        $evPath = substr($evUrl, strpos($evUrl, 'storage/'));
                        $evSrc = '/TMZ%20PODCAST/media-play.php?path=' . rawurlencode($evPath);
                    } elseif (strpos($evUrl, 'media-play.php') === false && strpos($evUrl, 'http') !== 0) {
                        $evSrc = '/TMZ%20PODCAST/' . ltrim($evUrl, '/');
                    }
                    ?>
                    <iframe src="<?php echo htmlspecialchars($evSrc); ?>" frameborder="0" allowfullscreen></iframe>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
        
        <form method="POST" action="/TMZ%20PODCAST/episodes.php" class="comment-form">
            <?php echo CSRF::getField(); ?>
            <input type="hidden" name="episode_id" value="<?php echo $episode['id']; ?>">
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

    <!-- Related Episodes -->
    <?php if (!empty($relatedEpisodes)): ?>
        <section class="related-episodes">
            <h2>Related Episodes</h2>
            <div class="episodes-grid">
                <?php foreach (array_slice($relatedEpisodes, 0, 4) as $related): ?>
                    <div class="episode-card">
                        <div class="episode-image">
                            <?php if ($related['featured_image_id']): ?>
                                <img src="<?php echo htmlspecialchars(mediaUrl($related['featured_image_id'])); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>">
                            <?php else: ?>
                                <div class="placeholder-image">Episode</div>
                            <?php endif; ?>
                        </div>
                        <div class="episode-content">
                            <h3><?php echo htmlspecialchars($related['title']); ?></h3>
                            <a href="/TMZ%20PODCAST/episodes.php?slug=<?php echo urlencode($related['slug']); ?>" class="btn btn-small">Listen</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
