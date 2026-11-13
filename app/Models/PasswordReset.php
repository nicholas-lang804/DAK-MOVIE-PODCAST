<?php
/**
 * Password Reset Model
 * Handles password reset database operations
 */

require_once __DIR__ . '/../../config/database.php';

class PasswordReset {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Create password reset token
     * @param int $userId
     * @return string|false
     */
    public function create($userId) {
        try {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $this->pdo->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $tokenHash, $expiresAt]);

            return $token;
        } catch (PDOException $e) {
            error_log("Password reset creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate password reset token
     * @param string $token
     * @return array|null
     */
    public function validate($token) {
        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $this->pdo->prepare("
                SELECT pr.*, u.username, u.email
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token_hash = ? AND pr.expires_at > NOW()
            ");
            $stmt->execute([$tokenHash]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Password reset validation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete password reset token
     * @param string $token
     * @return bool
     */
    public function delete($token) {
        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE token_hash = ?");
            return $stmt->execute([$tokenHash]);
        } catch (PDOException $e) {
            error_log("Password reset deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete expired tokens
     * @return int
     */
    public function deleteExpired() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Expired password reset deletion failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cleanup expired tokens
     * @return int
     */
    public function cleanupExpired() {
        return $this->deleteExpired();
    }
}
?>
