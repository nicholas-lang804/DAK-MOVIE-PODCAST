<?php
/**
 * Admin Sponsors Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Sponsors</h1>
        <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=sponsors&action=create" class="btn btn-primary">Add Sponsor</a>
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
            <h2><?php echo $action === 'create' ? 'Create Sponsor' : 'Edit Sponsor'; ?></h2>
            <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=sponsors" class="admin-form">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $sponsor['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($sponsor['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($sponsor['website'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($sponsor['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo ($sponsor['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($sponsor['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Sponsor</button>
                    <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=sponsors" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Website</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sponsors as $sponsor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sponsor['name']); ?></td>
                            <td><?php echo htmlspecialchars($sponsor['website'] ?? 'N/A'); ?></td>
                            <td><span class="status-badge status-<?php echo $sponsor['status']; ?>"><?php echo ucfirst($sponsor['status']); ?></span></td>
                            <td>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=sponsors&action=edit&id=<?php echo $sponsor['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=sponsors&action=delete&id=<?php echo $sponsor['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
