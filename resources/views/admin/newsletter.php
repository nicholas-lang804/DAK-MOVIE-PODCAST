<?php
/**
 * Admin Newsletter Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Newsletter Subscribers</h1>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Subscribed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscribers as $subscriber): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                        <td><span class="status-badge status-<?php echo $subscriber['status']; ?>"><?php echo ucfirst($subscriber['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($subscriber['subscribed_at'])); ?></td>
                        <td>
                            <?php if ($subscriber['status'] === 'active'): ?>
                                <form method="POST" action="/admin/dashboard.php?tab=newsletter" style="display: inline;">
                                    <?php echo CSRF::getField(); ?>
                                    <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                    <input type="hidden" name="action" value="unsubscribe">
                                    <button type="submit" class="btn btn-small">Unsubscribe</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="/admin/dashboard.php?tab=newsletter" style="display: inline;">
                                <?php echo CSRF::getField(); ?>
                                <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
