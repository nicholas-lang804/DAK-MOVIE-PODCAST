<?php
/**
 * Articles Page View
 * Entertainment news library
 */
?>

<section class="page-header">
    <h1>Entertainment News</h1>
    <p>Latest entertainment news, celebrity stories, and industry updates.</p>
</section>

<!-- Search and Filters -->
<section class="filters">
    <form method="GET" action="/news.php" class="filter-form">
        <div class="filter-group">
            <input type="text" name="q" placeholder="Search articles..." value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
        </div>
        <div class="filter-group">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo ($selectedCategory == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>
</section>

<!-- Articles Grid -->
<section class="articles-library">
    <?php if (empty($articles)): ?>
        <div class="no-results">
            <p>No articles found. Try adjusting your search or filters.</p>
        </div>
    <?php else: ?>
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
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
                        <a href="/news.php?slug=<?php echo urlencode($article['slug']); ?>" class="btn btn-primary">Read More</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo $searchQuery ? '&q=' . urlencode($searchQuery) : ''; ?><?php echo $selectedCategory ? '&category=' . $selectedCategory : ''; ?>" class="btn btn-outline">Previous</a>
                <?php endif; ?>
                
                <span class="page-info">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo $searchQuery ? '&q=' . urlencode($searchQuery) : ''; ?><?php echo $selectedCategory ? '&category=' . $selectedCategory : ''; ?>" class="btn btn-outline">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
