<?php
/**
 * Admin Comments Management View
 */
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Comments</h1>
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
                    <th>User</th>
                    <th>Content</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $comment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($comment['user_name']); ?></td>
                        <td>
                            <?php if ($comment['content_type'] === 'episode'): ?>
                                <a href="/TMZ%20PODCAST/episodes.php?slug=<?php echo urlencode($comment['episode_slug'] ?? ''); ?>"><?php echo htmlspecialchars($comment['episode_title'] ?? 'Episode'); ?></a>
                            <?php elseif ($comment['content_type'] === 'article'): ?>
                                <a href="/TMZ%20PODCAST/news.php?slug=<?php echo urlencode($comment['article_slug'] ?? ''); ?>"><?php echo htmlspecialchars($comment['article_title'] ?? 'Article'); ?></a>
                            <?php elseif ($comment['content_type'] === 'movie'): ?>
                                <a href="/TMZ%20PODCAST/movies.php?slug=<?php echo urlencode($comment['movie_slug'] ?? ''); ?>"><?php echo htmlspecialchars($comment['movie_title'] ?? 'Movie'); ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(substr($comment['comment'], 0, 100)); ?>...</td>
                        <td><span class="status-badge status-<?php echo $comment['status']; ?>"><?php echo ucfirst($comment['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></td>
                        <td>
                            <?php if ($comment['status'] === 'pending'): ?>
                                <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=comments" style="display: inline;">
                                    <?php echo CSRF::getField(); ?>
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-small btn-success">Approve</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=comments" style="display: inline;">
                                <?php echo CSRF::getField(); ?>
                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                <input type="hidden" name="status" value="spam">
                                <button type="submit" class="btn btn-small btn-danger">Spam</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
