<?php
/**
 * Admin Hosts Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Hosts</h1>
        <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=hosts&action=create" class="btn btn-primary">Add Host</a>
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
            <h2><?php echo $action === 'create' ? 'Create Host' : 'Edit Host'; ?></h2>
            <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=hosts" class="admin-form">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $host['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($host['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <input type="text" id="role" name="role" value="<?php echo htmlspecialchars($host['role'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="biography">Biography</label>
                    <textarea id="biography" name="biography" rows="6"><?php echo htmlspecialchars($host['biography'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo ($host['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($host['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Host</button>
                    <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=hosts" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hosts as $host): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($host['name']); ?></td>
                            <td><?php echo htmlspecialchars($host['role'] ?? 'N/A'); ?></td>
                            <td><span class="status-badge status-<?php echo $host['status']; ?>"><?php echo ucfirst($host['status']); ?></span></td>
                            <td>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=hosts&action=edit&id=<?php echo $host['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=hosts&action=delete&id=<?php echo $host['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
