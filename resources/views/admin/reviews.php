<?php
/**
 * Admin Reviews Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Reviews</h1>
        <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=reviews&action=create" class="btn btn-primary">Add Review</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if ($action === 'create' || $action === 'edit'): ?>
        <div class="form-container">
            <h2><?php echo $action === 'create' ? 'Create Review' : 'Edit Review'; ?></h2>
            <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=reviews" class="admin-form">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $review['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="movie_id">Movie *</label>
                    <select id="movie_id" name="movie_id" required>
                        <option value="">Select Movie</option>
                        <?php foreach ($movies as $movie): ?>
                            <option value="<?php echo $movie['id']; ?>" <?php echo ($review['movie_id'] ?? '') == $movie['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="review_title">Review Title</label>
                    <input type="text" id="review_title" name="review_title" value="<?php echo htmlspecialchars($review['review_title'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="review_content">Review Content *</label>
                    <textarea id="review_content" name="review_content" rows="8" required><?php echo htmlspecialchars($review['review_content'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="rating">Rating (1-10)</label>
                    <input type="number" id="rating" name="rating" min="1" max="10" value="<?php echo $review['rating'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo ($review['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo ($review['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Review</button>
                    <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=reviews" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Movie</th>
                        <th>Title</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($review['movie_title'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($review['review_title'] ?? 'N/A'); ?></td>
                            <td><?php echo $review['rating'] ? number_format($review['rating'], 1) : 'N/A'; ?></td>
                            <td><span class="status-badge status-<?php echo $review['status']; ?>"><?php echo ucfirst($review['status']); ?></span></td>
                            <td>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=reviews&action=edit&id=<?php echo $review['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=reviews&action=delete&id=<?php echo $review['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
