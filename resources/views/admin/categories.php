<?php
/**
 * Admin Categories Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Categories</h1>
        <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=categories&action=create" class="btn btn-primary">Add Category</a>
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
            <h2><?php echo $action === 'create' ? 'Create Category' : 'Edit Category'; ?></h2>
            <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=categories" class="admin-form">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $category['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($category['slug'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="category_type" name="category_type">
                        <option value="episode" <?php echo ($category['category_type'] ?? '') === 'episode' ? 'selected' : ''; ?>>Episode</option>
                        <option value="article" <?php echo ($category['category_type'] ?? '') === 'article' ? 'selected' : ''; ?>>Article</option>
                        <option value="movie" <?php echo ($category['category_type'] ?? '') === 'movie' ? 'selected' : ''; ?>>Movie</option>
                        <option value="video" <?php echo ($category['category_type'] ?? '') === 'video' ? 'selected' : ''; ?>>Video</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Category</button>
                    <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=categories" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td><?php echo ucfirst($category['category_type'] ?? 'article'); ?></td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=categories&action=edit&id=<?php echo $category['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=categories&action=delete&id=<?php echo $category['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
