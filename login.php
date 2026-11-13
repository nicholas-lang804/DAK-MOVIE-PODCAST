<?php
/**
 * User Login Page
 * Handles user authentication
 */

// Start session
session_start();

// Load configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/oauth.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/app/Helpers/Validation.php';
require_once __DIR__ . '/app/Middleware/CSRF.php';
require_once __DIR__ . '/app/Middleware/Auth.php';

// Load models
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Models/ActivityLog.php';

// Initialize database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /TMZ%20PODCAST/dashboard.php');
    exit;
}

$error = '';
$oauthError = $_SESSION['oauth_error'] ?? '';
unset($_SESSION['oauth_error']);
$googleOAuthEnabled = tmzGoogleOAuthConfigured();

// Handle social sign-in and standard login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['social_provider'])) {
        $provider = trim($_POST['social_provider'] ?? '');
        $socialEmail = trim($_POST['social_email'] ?? '');
        $socialName = trim($_POST['social_name'] ?? '');

        if (empty($socialEmail)) {
            $error = 'Please select a social account to continue.';
        } else {
            $result = handleSocialAuthentication($provider, $socialEmail, $socialName);
            if ($result['success']) {
                $next = $_SESSION['after_login'] ?? '';
                unset($_SESSION['after_login']);
                if ($next && strpos($next, '/TMZ%20PODCAST/') === 0 && strpos($next, '//') === false && strpos($next, "\n") === false) {
                    header('Location: ' . $next);
                    exit;
                }
                header('Location: /TMZ%20PODCAST/dashboard.php');
                exit;
            }
            $error = $result['message'];
        }
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($identifier) || empty($password)) {
            $error = 'Please enter email/phone and password';
        } else {
            // Find user by email or phone
            $userModel = new User();
            $user = $userModel->findByEmail($identifier);
            
            if (!$user) {
                $user = $userModel->findByPhone($identifier);
            }
            
            $role = $user['role_name'] ?? $user['role'] ?? 'user';
            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if user is active
                if ($user['status'] === 'active') {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $role;
                    
                    // Update last login
                    $userModel->updateLastLogin($user['id']);
                    
                    // Log activity
                    $logModel = new ActivityLog();
                    $logModel->create([
                        'user_id' => $user['id'],
                        'action' => 'user_login',
                        'description' => 'User logged in',
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                    ]);
                    
                    $next = $_SESSION['after_login'] ?? '';
                    unset($_SESSION['after_login']);
                    if (strpos($next, '/TMZ%20PODCAST/') === 0 && strpos($next, '//') === false && strpos($next, "\n") === false) {
                        header('Location: ' . $next);
                        exit;
                    }
                    header('Location: /TMZ%20PODCAST/dashboard.php');
                    exit;
                } else {
                    $error = 'Your account is not active. Please contact support.';
                }
            } else {
                $error = 'Invalid email/phone or password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - THE DAK FILM PODCAST MEDIA</title>
    <link rel="stylesheet" href="/TMZ%20PODCAST/assets/css/modern.css">
</head>
<body>
    <div class="admin-layout">
        <div class="admin-main" style="margin-left: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh;">
            <div class="form-container" style="max-width: 400px; width: 100%;">
                <h2 style="text-align: center; margin-bottom: 2rem;">User Login</h2>
                
                <?php if ($oauthError): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($oauthError); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php echo CSRF::getField(); ?>
                    
                    <div class="form-group">
                        <label for="identifier" class="form-label">Email or Phone</label>
                        <input type="text" id="identifier" name="identifier" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>

                <?php if ($googleOAuthEnabled): ?>
                    <div style="margin: 1.5rem 0 1rem;">
                        <div style="display:flex; align-items:center; gap: .75rem; margin-bottom: .85rem;">
                            <span style="flex:1; height:1px; background: #dfe3ea;"></span>
                            <span style="font-size: .75rem; font-weight: 600; color: #667085; text-transform: uppercase; letter-spacing: .08em;">or continue with</span>
                            <span style="flex:1; height:1px; background: #dfe3ea;"></span>
                        </div>

                        <a href="/TMZ%20PODCAST/auth/google.php" style="display:flex; align-items:center; justify-content:center; gap:.7rem; width:100%; padding:.8rem 1rem; border:1px solid #dfe3ea; border-radius: 10px; background:#ffffff; color:#111827; font-weight:600; text-decoration:none;">
                            <span aria-hidden="true" style="display:inline-flex; width:1.8rem; height:1.8rem; align-items:center; justify-content:center; border-radius:999px; background:#fff; border:1px solid #e5e7eb; overflow:hidden; flex-shrink:0;">
                                <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.76 1.22 9.29 3.62l6.94-6.94C35.92 2.36 30.58 0 24 0 14.64 0 6.6 5.38 2.57 13.22l8.09 6.27C12.31 14.13 17.7 9.5 24 9.5z"/>
                                    <path fill="#4285F4" d="M46.5 24.5c0-1.68-.15-3.29-.42-4.84H24v9.17h12.69c-.55 2.9-2.18 5.35-4.65 7.02l7.52 5.84c4.39-4.04 6.94-10 6.94-17.19z"/>
                                    <path fill="#FBBC05" d="M32.04 36.35c-1.67 1.12-3.81 1.79-8.04 1.79-6.3 0-11.62-4.25-13.51-9.96l-8.1 6.26C5.2 42.33 13.66 48 24 48c7.12 0 13.1-2.35 17.5-6.42l-9.46-5.23z"/>
                                    <path fill="#34A853" d="M10.99 28.18A14.49 14.49 0 0 1 10.5 24c0-1.62.28-3.2.77-4.7L3.18 12.96A23.96 23.96 0 0 0 0 24c0 3.86.93 7.52 2.57 10.78l8.42-6.6z"/>
                                </svg>
                            </span>
                            <span>Continue with Google</span>
                        </a>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 1rem; text-align: center;">
                    <p>Don't have an account? <a href="/TMZ%20PODCAST/register.php">Register here</a></p>
                    <p><a href="/TMZ%20PODCAST/" class="btn btn-outline btn-small">Back to Website</a></p>
                    <p style="margin-top:.75rem;font-size:.8rem;color:#8a94a6">Built by <a href="/TMZ%20PODCAST/nictech.php" style="color:#e11d2e;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem"><img src="/TMZ%20PODCAST/assets/img/nictech-logo.svg" alt="" width="18" height="18" style="border-radius:4px;vertical-align:-3px">NicTech Companies Uganda</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

