<?php
/**
 * Admin Activity Logs View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Activity Logs</h1>
    </div>

    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td><?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
