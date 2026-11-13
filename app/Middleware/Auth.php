<?php
/**
 * Authentication Middleware
 * Enhanced authentication with role-based permissions
 */

require_once __DIR__ . '/../../includes/auth.php';

class AuthMiddleware {
    /**
     * Require user to be logged in
     */
    public static function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: /login.php');
            exit();
        }
    }

    /**
     * Require admin access
     */
    public static function requireAdmin() {
        if (!isAdmin()) {
            header('Location: /admin/login.php');
            exit();
        }
    }

    /**
     * Require specific permission
     * @param string $permission
     */
    public static function requirePermission($permission) {
        if (!self::hasPermission($permission)) {
            http_response_code(403);
            die('You do not have permission to access this resource.');
        }
    }

    /**
     * Check if current user has specific permission
     * @param string $permission
     * @return bool
     */
    public static function hasPermission($permission) {
        if (!isLoggedIn()) {
            return false;
        }

        try {
            $pdo = getDB();
            $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;

            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM role_permissions rp
                JOIN roles r ON rp.role_id = r.id
                JOIN users u ON u.role_id = r.id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE u.id = ? AND p.permission_name = ?
            ");
            $stmt->execute([$userId, $permission]);
            $result = $stmt->fetch();

            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Permission check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if current user has any of the given permissions
     * @param array $permissions
     * @return bool
     */
    public static function hasAnyPermission(array $permissions) {
        foreach ($permissions as $permission) {
            if (self::hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get current user's role
     * @return string|null
     */
    public static function getUserRole() {
        if (!isLoggedIn()) {
            return null;
        }

        try {
            $pdo = getDB();
            $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;

            $stmt = $pdo->prepare("
                SELECT r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();

            return $result ? $result['role_name'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Log user activity
     * @param string $action
     * @param string $description
     */
    public static function logActivity($action, $description = '') {
        if (!isLoggedIn()) {
            return;
        }

        try {
            $pdo = getDB();
            $userId = getCurrentUserId();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $description, $ipAddress]);
        } catch (PDOException $e) {
            error_log("Activity log failed: " . $e->getMessage());
        }
    }
}
?>
