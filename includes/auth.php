<?php
/**
 * Authentication Helper Functions
 * Handles user registration, login, session management, and access control
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
}

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin() {
    $adminRole = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? null;
    return isset($_SESSION['admin_id']) && in_array($adminRole, ['admin', 'super_admin'], true);
}

/**
 * Require user login
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

/**
 * Require admin login
 * Redirects to admin login if not admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit();
    }
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, username, email, phone, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Register a new user
 * @param string $username
 * @param string $email
 * @param string $phone
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function registerUser($username, $email, $phone, $password) {
    try {
        $pdo = getDB();
        
        // Check if email or phone already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email or phone number already registered'];
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'user')");
        $stmt->execute([$username, $email, $phone, $passwordHash]);
        
        return ['success' => true, 'message' => 'Registration successful'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

/**
 * Login user
 * @param string $identifier Email or phone
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function loginUser($identifier, $password) {
    try {
        $pdo = getDB();
        
        // Find user by email or phone
        $stmt = $pdo->prepare("SELECT id, username, email, phone, password_hash, role FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
    }
}

/**
 * Handle social sign-in by provider and email.
 * If the account does not yet exist, create it automatically and log the user in.
 */
function handleSocialAuthentication($provider, $email, $name = null) {
    $provider = strtolower(trim((string) $provider));
    $email = trim((string) $email);
    $name = trim((string) $name);

    if ($provider === '' || $email === '') {
        return ['success' => false, 'message' => 'Please choose a valid social account to continue.'];
    }

    if (!class_exists('User', false)) {
        require_once __DIR__ . '/../app/Models/User.php';
    }

    $userModel = new User();
    $user = $userModel->findByEmail($email);

    if (!$user) {
        $baseUsername = preg_replace('/[^a-zA-Z0-9._-]+/', '', str_replace(' ', '.', $name ?: explode('@', $email)[0] ?? 'tmzuser'));
        $baseUsername = strtolower($baseUsername ?: 'tmzuser');

        $username = $baseUsername;
        $counter = 1;
        while ($userModel->findByUsername($username)) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        $created = $userModel->create([
            'username' => $username,
            'email' => $email,
            'phone' => null,
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'role' => 'user',
            'status' => 'active',
        ]);

        if (!$created) {
            return ['success' => false, 'message' => 'We could not create your account using this social sign-in.'];
        }

        $user = $userModel->findByEmail($email);
    }

    if (!$user) {
        return ['success' => false, 'message' => 'Unable to find your social account after registration.'];
    }

    if (($user['status'] ?? 'active') !== 'active') {
        return ['success' => false, 'message' => 'Your account is not active yet. Please contact support.'];
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role_name'] ?? $user['role'] ?? 'user';
    $_SESSION['role'] = $_SESSION['user_role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];

    $userModel->updateLastLogin((int) $user['id']);

    return ['success' => true, 'message' => ucfirst($provider) . ' sign-in successful'];
}

/**
 * Login admin
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function loginAdmin($username, $password) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM users WHERE username = ? AND role = 'admin'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            return ['success' => false, 'message' => 'Invalid admin credentials'];
        }
        
        if (!password_verify($password, $admin['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid admin credentials'];
        }
        
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['is_admin'] = true;
        $_SESSION['role'] = 'admin';
        
        return ['success' => true, 'message' => 'Admin login successful'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Admin login failed: ' . $e->getMessage()];
    }
}

/**
 * Logout user
 */
function logout() {
    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit();
}

/**
 * Logout admin
 */
function adminLogout() {
    session_unset();
    session_destroy();
    header('Location: /admin/login.php');
    exit();
}

/**
 * Change user password
 * @param int $userId
 * @param string $currentPassword
 * @param string $newPassword
 * @return array ['success' => bool, 'message' => string]
 */
function changePassword($userId, $currentPassword, $newPassword) {
    try {
        $pdo = getDB();
        
        // Get current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Password change failed: ' . $e->getMessage()];
    }
}

/**
 * Change admin password and invalidate session
 * @param int $adminId
 * @param string $currentPassword
 * @param string $newPassword
 * @return array ['success' => bool, 'message' => string]
 */
function changeAdminPassword($adminId, $currentPassword, $newPassword) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            return ['success' => false, 'message' => 'Admin not found'];
        }
        
        if (!password_verify($currentPassword, $admin['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newPasswordHash, $adminId]);
        
        // Destroy session to force re-login
        session_unset();
        session_destroy();
        
        return ['success' => true, 'message' => 'Password changed successfully. Please login again.'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Password change failed: ' . $e->getMessage()];
    }
}

/**
 * Check if user has access to media
 * @param int $userId
 * @param int $mediaId
 * @param string $accessType 'watch' or 'download'
 * @return bool
 */
function hasMediaAccess($userId, $mediaId, $accessType = 'watch') {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM user_media_access 
            WHERE user_id = ? AND media_id = ? AND access_type = ?
        ");
        $stmt->execute([$userId, $mediaId, $accessType]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
        
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if user has access to podcast
 * @param int $userId
 * @param int $podcastId
 * @return bool
 */
function hasPodcastAccess($userId, $podcastId) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM user_podcast_access 
            WHERE user_id = ? AND podcast_id = ?
        ");
        $stmt->execute([$userId, $podcastId]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
        
    } catch (PDOException $e) {
        return false;
    }
}
?>
