<?php
/**
 * Episodes Page View
 * Podcast episode library
 */
?>

<section class="page-header">
    <h1>Podcast Episodes</h1>
    <p>Browse our complete library of podcast episodes.</p>
</section>

<!-- Search and Filters -->
<section class="filters">
    <form method="GET" action="/episodes.php" class="filter-form">
        <div class="filter-group">
            <input type="text" name="q" placeholder="Search episodes..." value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
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

<!-- Episodes Grid -->
<section class="episodes-library">
    <?php if (empty($episodes)): ?>
        <div class="no-results">
            <p>No episodes found. Try adjusting your search or filters.</p>
        </div>
    <?php else: ?>
        <div class="episodes-grid">
            <?php foreach ($episodes as $episode): ?>
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
                            <?php if ($episode['published_at']): ?>
                                <span class="date"><?php echo date('M d, Y', strtotime($episode['published_at'])); ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="episode-description"><?php echo htmlspecialchars(substr($episode['description'] ?? '', 0, 150)); ?>...</p>
                        <a href="/episodes.php?slug=<?php echo urlencode($episode['slug']); ?>" class="btn btn-primary">Listen Now</a>
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
