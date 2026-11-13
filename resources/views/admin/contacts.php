<?php
/**
 * Admin Contacts Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Contact Messages</h1>
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
                    <th>Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($contacts ?? []) as $message): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($message['name']); ?></td>
                        <td><?php echo htmlspecialchars($message['email']); ?></td>
                        <td><?php echo htmlspecialchars($message['subject'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($message['enquiry_type'] ?? 'general'); ?></td>
                        <td><span class="status-badge status-<?php echo $message['status']; ?>"><?php echo ucfirst($message['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($message['created_at'])); ?></td>
                        <td>
                            <a href="/admin/dashboard.php?tab=contacts&action=view&id=<?php echo $message['id']; ?>" class="btn btn-small">View</a>
                            <?php if ($message['status'] === 'new'): ?>
                                <form method="POST" action="/admin/dashboard.php?tab=contacts" style="display: inline;">
                                    <?php echo CSRF::getField(); ?>
                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                    <input type="hidden" name="status" value="read">
                                    <button type="submit" class="btn btn-small">Mark Read</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
