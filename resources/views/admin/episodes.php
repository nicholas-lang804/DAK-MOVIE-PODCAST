<?php
/**
 * Admin Episodes Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Episodes</h1>
        <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=episodes&action=create" class="btn btn-primary">Add Episode</a>
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
        <!-- Episode Form -->
        <div class="form-container">
            <h2><?php echo $action === 'create' ? 'Create Episode' : 'Edit Episode'; ?></h2>
            <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=episodes" class="admin-form" enctype="multipart/form-data">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $episode['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($episode['title'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="6"><?php echo htmlspecialchars($episode['description'] ?? ''); ?></textarea>
                </div>

                <div class="admin-media-upload-panel">
                    <h3>Episode media</h3>
                    <label for="episode_audio_file">Upload podcast audio</label>
                    <input type="file" id="episode_audio_file" name="episode_audio_file" accept="audio/*">
                    <label for="audio_media_id">Or choose existing audio</label>
                    <select id="audio_media_id" name="audio_media_id"><option value="">Choose audio</option><?php foreach (($audios ?? []) as $audio): ?><option value="<?php echo (int)$audio['id']; ?>" <?php echo ((int)($episode['audio_media_id'] ?? 0)===(int)$audio['id'])?'selected':''; ?>><?php echo htmlspecialchars($audio['original_filename']); ?></option><?php endforeach; ?></select>
                    <label for="episode_video_file">Upload episode video (optional)</label>
                    <input type="file" id="episode_video_file" name="episode_video_file" accept="video/*">
                    <label for="episode_cover_file">Upload episode cover</label>
                    <input type="file" id="episode_cover_file" name="episode_cover_file" accept="image/*">
                    <label for="featured_image_id">Or choose existing cover</label>
                    <select id="featured_image_id" name="featured_image_id"><option value="">Choose cover</option><?php foreach (($images ?? []) as $image): ?><option value="<?php echo (int)$image['id']; ?>" <?php echo ((int)($episode['featured_image_id'] ?? 0)===(int)$image['id'])?'selected':''; ?>><?php echo htmlspecialchars($image['original_filename']); ?></option><?php endforeach; ?></select>
                    <small>New uploads are automatically registered in Media Library.</small>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($episode['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo ($episode['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo ($episode['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo ($episode['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_featured" value="1" <?php echo ($episode['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                        Featured Episode
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Episode</button>
                    <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=episodes" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Episodes List -->
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
                    <?php foreach ($episodes as $episode): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($episode['title']); ?></td>
                            <td><?php echo htmlspecialchars($episode['category_name'] ?? 'N/A'); ?></td>
                            <td><span class="status-badge status-<?php echo $episode['status']; ?>"><?php echo ucfirst($episode['status']); ?></span></td>
                            <td><?php echo $episode['is_featured'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $episode['published_at'] ? date('M d, Y', strtotime($episode['published_at'])) : 'N/A'; ?></td>
                            <td>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=episodes&action=edit&id=<?php echo $episode['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=episodes&action=delete&id=<?php echo $episode['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
