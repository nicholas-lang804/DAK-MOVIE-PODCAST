<?php
/**
 * Admin Advertisements Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Advertisements</h1>
        <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=advertisements&action=create" class="btn btn-primary">Add Advertisement</a>
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
            <h2><?php echo $action === 'create' ? 'Create Advertisement' : 'Edit Advertisement'; ?></h2>
            <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=advertisements" class="admin-form" enctype="multipart/form-data">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $advertisement['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($advertisement['title'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="placement">Placement</label>
                    <select id="placement" name="placement">
                        <option value="homepage" <?php echo ($advertisement['placement'] ?? '') === 'homepage' ? 'selected' : ''; ?>>Homepage</option>
                        <option value="sidebar" <?php echo ($advertisement['placement'] ?? '') === 'sidebar' ? 'selected' : ''; ?>>Sidebar</option>
                        <option value="footer" <?php echo ($advertisement['placement'] ?? '') === 'footer' ? 'selected' : ''; ?>>Footer</option>
                        <option value="between_content" <?php echo ($advertisement['placement'] ?? '') === 'between_content' ? 'selected' : ''; ?>>Between Content</option>
                        <option value="dashboard" <?php echo ($advertisement['placement'] ?? '') === 'dashboard' ? 'selected' : ''; ?>>Member Dashboard</option>
                        <option value="banner" <?php echo ($advertisement['placement'] ?? '') === 'banner' ? 'selected' : ''; ?>>Top Banner (all pages)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="target_url">Target URL</label>
                    <input type="url" id="target_url" name="target_url" value="<?php echo htmlspecialchars($advertisement['target_url'] ?? ''); ?>">
                </div>

                <div class="admin-media-upload-panel">
                    <h3>Advertisement media</h3>
                    <label for="advertisement_image_file">Upload advert image</label>
                    <input type="file" id="advertisement_image_file" name="advertisement_image_file" accept="image/*">
                    <label for="image_media_id">Or choose existing image</label>
                    <select id="image_media_id" name="image_media_id"><option value="">Choose advert image</option><?php foreach (($images ?? []) as $image): ?><option value="<?php echo (int)$image['id']; ?>" <?php echo ((int)($advertisement['image_media_id'] ?? 0)===(int)$image['id'])?'selected':''; ?>><?php echo htmlspecialchars($image['original_filename']); ?></option><?php endforeach; ?></select>
                    <small>Upload or select the creative that visitors will see.</small>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $advertisement['start_date'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $advertisement['end_date'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo ($advertisement['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($advertisement['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Advertisement</button>
                    <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=advertisements" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Placement</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advertisements as $advertisement): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($advertisement['title']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $advertisement['placement'])); ?></td>
                            <td><?php echo $advertisement['start_date'] ? date('M d, Y', strtotime($advertisement['start_date'])) : 'N/A'; ?></td>
                            <td><?php echo $advertisement['end_date'] ? date('M d, Y', strtotime($advertisement['end_date'])) : 'N/A'; ?></td>
                            <td><span class="status-badge status-<?php echo $advertisement['status']; ?>"><?php echo ucfirst($advertisement['status']); ?></span></td>
                            <td>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=advertisements&action=edit&id=<?php echo $advertisement['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=advertisements&action=delete&id=<?php echo $advertisement['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
