<?php
/**
 * Admin Stars (Actors & Actresses) Management View
 */
$e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<section class="admin-section">
    <div class="section-header">
        <h1>Manage Stars</h1>
        <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=stars&amp;action=create" class="btn btn-primary">Add Actor / Actress</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $e($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $e($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if ($action === 'create' || $action === 'edit'): ?>
        <?php $star = $star ?: []; ?>
        <div class="form-container">
            <h2><?php echo $action === 'create' ? 'Add a Star' : 'Edit ' . $e($star['name'] ?? 'Star'); ?></h2>
            <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=stars" class="admin-form" enctype="multipart/form-data">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo (int)($star['id'] ?? 0); ?>">
                <input type="hidden" name="form_action" value="save">

                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" maxlength="255" value="<?php echo $e($star['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="category">Actor or actress</label>
                    <select id="category" name="category">
                        <option value="actor" <?php echo ($star['category'] ?? 'actor') === 'actor' ? 'selected' : ''; ?>>Actor</option>
                        <option value="actress" <?php echo ($star['category'] ?? '') === 'actress' ? 'selected' : ''; ?>>Actress</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="known_for">Known for (short line)</label>
                    <input type="text" id="known_for" name="known_for" maxlength="255" placeholder="e.g. Award-winning lead in action and drama" value="<?php echo $e($star['known_for'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="biography">About this star</label>
                    <textarea id="biography" name="biography" rows="8" placeholder="Speak about him or her: career, notable films, achievements..."><?php echo $e($star['biography'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="star_photo">Picture (JPG, PNG, WebP or GIF, up to 15 MB)</label>
                    <?php if (!empty($star['photo_path'])): ?>
                        <p><img src="<?php echo $e(Star::url($star['photo_path'])); ?>" alt="" style="max-width:140px;max-height:180px;border-radius:8px;display:block;margin-bottom:.5rem;"></p>
                    <?php endif; ?>
                    <input type="file" id="star_photo" name="star_photo" accept="image/jpeg,image/png,image/webp,image/gif">
                    <?php if (!empty($star['photo_path'])): ?><small>Leave empty to keep the current picture.</small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="star_video">Video upload (MP4, WebM, OGG or MOV, up to 500 MB)</label>
                    <?php if (!empty($star['video_path'])): ?>
                        <p><video src="<?php echo $e(Star::url($star['video_path'])); ?>" controls preload="metadata" style="max-width:320px;width:100%;border-radius:8px;"></video></p>
                        <label style="font-weight:normal;"><input type="checkbox" name="remove_video" value="1"> Remove this video</label>
                    <?php endif; ?>
                    <input type="file" id="star_video" name="star_video" accept="video/mp4,video/webm,video/ogg,video/quicktime">
                </div>

                <div class="form-group">
                    <label for="video_url">or a YouTube / Vimeo link</label>
                    <input type="url" id="video_url" name="video_url" maxlength="500" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo $e($star['video_url'] ?? ''); ?>">
                    <small>If you give both, the uploaded file plays first and the link shows beneath it.</small>
                </div>

                <div class="form-group">
                    <label for="sort_order">Display order (smaller shows first)</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?php echo (int)($star['sort_order'] ?? 0); ?>">
                </div>

                <div class="form-group">
                    <label style="font-weight:normal;"><input type="checkbox" name="is_featured" value="1" <?php echo !empty($star['is_featured']) ? 'checked' : ''; ?>> Feature this star on the homepage and member dashboard</label>
                </div>

                <div class="form-group">
                    <label for="movie_ids">Appears in these movies (hold Ctrl or Cmd to choose several)</label>
                    <select id="movie_ids" name="movie_ids[]" multiple size="7">
                        <?php foreach (($allMovies ?? []) as $mv): ?>
                            <option value="<?php echo (int)$mv['id']; ?>" <?php echo in_array((int)$mv['id'], $starMovieIds ?? [], true) ? 'selected' : ''; ?>><?php echo $e($mv['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Shows a "Cast" row on each movie page and a "Filmography" row on this star's page.</small>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo ($star['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active (visible to everyone)</option>
                        <option value="inactive" <?php echo ($star['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Hidden</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Star</button>
                    <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=stars" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr><th>Picture</th><th>Name</th><th>Type</th><th>Video</th><th>Featured</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($stars)): ?>
                        <tr><td colspan="7">No stars yet. Click “Add Actor / Actress” to publish the first one.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($stars as $row): ?>
                        <tr>
                            <td><?php if (!empty($row['photo_path'])): ?><img src="<?php echo $e(Star::url($row['photo_path'])); ?>" alt="" style="width:44px;height:56px;object-fit:cover;border-radius:6px;"><?php else: ?>—<?php endif; ?></td>
                            <td><?php echo $e($row['name']); ?></td>
                            <td><?php echo $row['category'] === 'actress' ? 'Actress' : 'Actor'; ?></td>
                            <td><?php echo (!empty($row['video_path']) || !empty($row['video_url'])) ? 'Yes' : '—'; ?></td>
                            <td><?php echo !empty($row['is_featured']) ? 'Yes' : '—'; ?></td>
                            <td><span class="status-badge status-<?php echo $e($row['status']); ?>"><?php echo $row['status'] === 'active' ? 'Active' : 'Hidden'; ?></span></td>
                            <td>
                                <a href="/TMZ%20PODCAST/stars.php?slug=<?php echo urlencode($row['slug']); ?>" class="btn btn-small" target="_blank" rel="noopener">View</a>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=stars&amp;action=edit&amp;id=<?php echo (int)$row['id']; ?>" class="btn btn-small">Edit</a>
                                <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=stars" style="display:inline" onsubmit="return confirm('Delete <?php echo $e(addslashes($row['name'])); ?>? This also removes their picture and video.')">
                                    <?php echo CSRF::getField(); ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
