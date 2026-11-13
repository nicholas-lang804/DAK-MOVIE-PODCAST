<?php
/**
 * Article Detail Page View
 * Single article view
 */
?>

<article class="article-detail">
    <div class="article-header">
        <div class="article-image">
            <?php if ($article['featured_image_id']): ?>
                <img src="<?php echo htmlspecialchars(mediaUrl($article['featured_image_id'])); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
            <?php else: ?>
                <div class="placeholder-image">Article Image</div>
            <?php endif; ?>
        </div>
        <div class="article-info">
            <h1><?php echo htmlspecialchars($article['title']); ?></h1>
            <p class="article-meta">
                <?php if ($article['category_name']): ?>
                    <span class="category"><?php echo htmlspecialchars($article['category_name']); ?></span>
                <?php endif; ?>
                <?php if ($article['published_at']): ?>
                    <span class="date"><?php echo date('M d, Y', strtotime($article['published_at'])); ?></span>
                <?php endif; ?>
                <?php if ($article['author_name']): ?>
                    <span class="author">By <?php echo htmlspecialchars($article['author_name']); ?></span>
                <?php endif; ?>
            </p>
            <?php if ($article['excerpt']): ?>
                <p class="article-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="article-content">
        <?php echo nl2br(htmlspecialchars($article['content'] ?? '')); ?>
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
        
        <form method="POST" action="/news.php" class="comment-form">
            <?php echo CSRF::getField(); ?>
            <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
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

    <!-- Related Articles -->
    <?php if (!empty($relatedArticles)): ?>
        <section class="related-articles">
            <h2>Related Articles</h2>
            <div class="articles-grid">
                <?php foreach (array_slice($relatedArticles, 0, 3) as $related): ?>
                    <div class="article-card">
                        <div class="article-image">
                            <?php if ($related['featured_image_id']): ?>
                                <img src="<?php echo htmlspecialchars(mediaUrl($related['featured_image_id'])); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>">
                            <?php else: ?>
                                <div class="placeholder-image">Article</div>
                            <?php endif; ?>
                        </div>
                        <div class="article-content">
                            <h3><?php echo htmlspecialchars($related['title']); ?></h3>
                            <a href="/news.php?slug=<?php echo urlencode($related['slug']); ?>" class="btn btn-small">Read More</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
