<?php
/**
 * Admin Guests Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Guests</h1>
        <a href="/admin/dashboard.php?tab=guests&action=create" class="btn btn-primary">Add Guest</a>
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
            <h2><?php echo $action === 'create' ? 'Create Guest' : 'Edit Guest'; ?></h2>
            <form method="POST" action="/admin/dashboard.php?tab=guests" class="admin-form">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="id" value="<?php echo $guest['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($guest['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="profession">Profession</label>
                    <input type="text" id="profession" name="profession" value="<?php echo htmlspecialchars($guest['profession'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="biography">Biography</label>
                    <textarea id="biography" name="biography" rows="6"><?php echo htmlspecialchars($guest['biography'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo ($guest['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($guest['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Guest</button>
                    <a href="/admin/dashboard.php?tab=guests" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Profession</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guests as $guest): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($guest['name']); ?></td>
                            <td><?php echo htmlspecialchars($guest['profession'] ?? 'N/A'); ?></td>
                            <td><span class="status-badge status-<?php echo $guest['status']; ?>"><?php echo ucfirst($guest['status']); ?></span></td>
                            <td>
                                <a href="/admin/dashboard.php?tab=guests&action=edit&id=<?php echo $guest['id']; ?>" class="btn btn-small">Edit</a>
                                <a href="/admin/dashboard.php?tab=guests&action=delete&id=<?php echo $guest['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
