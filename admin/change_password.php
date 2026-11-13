<?php
require_once '../includes/auth.php';

requireAdmin();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'All fields are required';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters';
        $messageType = 'error';
    } else {
        $result = changeAdminPassword($_SESSION['admin_id'], $currentPassword, $newPassword);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Admin Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-page">
    <nav class="navbar admin-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <h1>Admin Portal</h1>
            </div>
            <div class="nav-menu">
                <span>Admin: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="/admin/dashboard.php" class="btn btn-outline">Dashboard</a>
                <a href="/admin/logout.php" class="btn btn-outline">Logout</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <div class="admin-card">
                <h2>Change Admin Password</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($messageType === 'success'): ?>
                    <p class="info-text">Your session has been invalidated. Please login with your new password.</p>
                    <a href="/admin/login.php" class="btn btn-primary">Login Again</a>
                <?php else: ?>
                    <form method="POST" action="change_password.php" class="admin-form">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
