<?php
/**
 * Admin Login Page
 * Handles admin authentication
 */

// Start session
session_start();

// Load configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Validation.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';

// Load models
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/ActivityLog.php';

// Initialize database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Find user by username
        $userModel = new User();
        $user = $userModel->findByUsername($username);
        
        $role = $user['role_name'] ?? $user['role'] ?? null;
        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if user is admin
            if (in_array($role, ['admin', 'super_admin'], true)) {
                // Set session
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $role;
                
                // Update last login
                $userModel->updateLastLogin($user['id']);
                
                // Log activity
                $logModel = new ActivityLog();
                $logModel->create([
                    'user_id' => $user['id'],
                    'action' => 'admin_login',
                    'description' => 'Admin logged in',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);
                
                header('Location: /admin/index.php');
                exit;
            } else {
                $error = 'Access denied. Admin privileges required.';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - THE MOVIE ZONE PODCAST 256</title>
    <link rel="stylesheet" href="/assets/css/modern.css">
</head>
<body>
    <div class="admin-layout">
        <div class="admin-main" style="margin-left: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh;">
            <div class="form-container" style="max-width: 400px; width: 100%;">
                <h2 style="text-align: center; margin-bottom: 2rem;">Admin Login</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php echo CSRF::getField(); ?>
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>
                
                <div style="margin-top: 1rem; text-align: center;">
                    <a href="/" class="btn btn-outline btn-small">Back to Website</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

