<?php
/**
 * Admin Articles Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Articles</h1>
        <a href="/admin/dashboard.php?tab=articles&action=create" class="btn btn-primary">Add Article</a>
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
        <!-- Article Form -->
        <div class="form-container">
            <h2><?php echo $action === 'create' ? 'Create Article' : 'Edit Article'; ?></h2>
            <form method="POST" action="/admin/dashboard.php?tab=articles" class="admin-form" enctype="multipart/form-data">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $article['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($article['title'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($article['excerpt'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="content">Content *</label>
                    <textarea id="content" name="content" rows="12"><?php echo htmlspecialchars($article['content'] ?? ''); ?></textarea>
                </div>

                <div class="admin-media-upload-panel">
                    <h3>Article media</h3>
                    <label for="article_image_file">Upload article cover image</label>
                    <input type="file" id="article_image_file" name="article_image_file" accept="image/*">
                    <label for="featured_image_id">Or choose existing image</label>
                    <select id="featured_image_id" name="featured_image_id"><option value="">Choose image</option><?php foreach (($images ?? []) as $image): ?><option value="<?php echo (int)$image['id']; ?>" <?php echo ((int)($article['featured_image_id'] ?? 0)===(int)$image['id'])?'selected':''; ?>><?php echo htmlspecialchars($image['original_filename']); ?></option><?php endforeach; ?></select>
                    <small>Articles remain full editorial/news articles, with cover media attached automatically to Media Library.</small>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($article['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo ($article['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo ($article['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo ($article['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_featured" value="1" <?php echo ($article['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                        Featured Article
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Article</button>
                    <a href="/admin/dashboard.php?tab=articles" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Articles List -->
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Featured</th>
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($article['title']); ?></td>
                            <td><?php echo htmlspecialchars($article['category_name'] ?? 'N/A'); ?></td>
                            <td><span class="status-badge status-<?php echo $article['status']; ?>"><?php echo ucfirst($article['status']); ?></span></td>
                            <td><?php echo $article['is_featured'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $article['published_at'] ? date('M d, Y', strtotime($article['published_at'])) : 'N/A'; ?></td>
                            <td>
                                <a href="/admin/dashboard.php?tab=articles&action=edit&id=<?php echo $article['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/admin/dashboard.php?tab=articles&action=delete&id=<?php echo $article['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
