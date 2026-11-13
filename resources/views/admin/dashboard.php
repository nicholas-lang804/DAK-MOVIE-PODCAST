<?php
/**
 * Admin Dashboard View
 * Main admin dashboard
 */
?>

<section class="admin-dashboard">
    <div class="dashboard-header">
        <h1>Admin Dashboard</h1>
        <p>Welcome to the THE DAK FILM PODCAST MEDIA administration panel.</p>
    </div>

    <!-- Statistics -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['episodes']); ?></div>
            <div class="stat-label">Episodes</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['articles']); ?></div>
            <div class="stat-label">Articles</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['movies']); ?></div>
            <div class="stat-label">Movies</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['users']); ?></div>
            <div class="stat-label">Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['comments']); ?></div>
            <div class="stat-label">Pending Comments</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['contacts']); ?></div>
            <div class="stat-label">New Messages</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['subscribers']); ?></div>
            <div class="stat-label">Subscribers</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['media']); ?></div>
            <div class="stat-label">Media Files</div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
            <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=finance" class="action-card">
                <h3>Financial Control</h3>
                <p>Review payments, ticket sales, revenue charts and downloadable reports</p>
            </a>
            <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=episodes&action=create" class="action-card">
                <h3>Add Episode</h3>
                <p>Create a new podcast episode</p>
            </a>
            <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=articles&action=create" class="action-card">
                <h3>Add Article</h3>
                <p>Write a new news article</p>
            </a>
            <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=movies&action=create" class="action-card">
                <h3>Add Movie</h3>
                <p>Add a new movie to the catalogue</p>
            </a>
            <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=media&action=upload" class="action-card">
                <h3>Upload Media</h3>
                <p>Upload images, audio, or video</p>
            </a>
        </div>
    </section>

    <!-- Recent Activity -->
    <?php if (isset($logs) && !empty($logs)): ?>
        <section class="recent-activity">
            <h2>Recent Activity</h2>
            <div class="activity-list">
                <?php foreach (array_slice($logs, 0, 10) as $log): ?>
                    <div class="activity-item">
                        <div class="activity-info">
                            <strong><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                            <span class="activity-action"><?php echo htmlspecialchars($log['action']); ?></span>
                            <?php if ($log['description']): ?>
                                <p class="activity-description"><?php echo htmlspecialchars($log['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="activity-date"><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
