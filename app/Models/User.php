<?php
/**
 * User Model
 * Handles user-related database operations
 */

require_once __DIR__ . '/../../config/database.php';

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find user by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, r.role_name 
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find user by email
     * @param string $email
     * @return array|null
     */
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, r.role_name 
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.username = ? LIMIT 1");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public function findByPhone($phone) {
        $stmt = $this->pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        return $stmt->fetch();
    }

    /**
     * Create new user
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $passwordHash = $data['password_hash'] ?? null;
            if (!$passwordHash && !empty($data['password'])) {
                $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if (!$passwordHash) {
                error_log('User creation rejected: password_hash is required');
                return false;
            }
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, phone, password_hash, role_id, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['username'],
                $data['email'],
                $data['phone'] ?? null,
                $passwordHash,
                $data['role_id'] ?? null,
                $data['status'] ?? 'active'
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("User creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }

            $values[] = $id;
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("User update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last login time
     * @param int $id
     * @return bool
     */
    public function updateLastLogin($id) {
        return $this->update($id, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Get all users with pagination
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll($limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, r.role_name 
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get total user count
     * @return int
     */
    public function getCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users");
        return $stmt->fetch()['count'];
    }

    /**
     * Delete user
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("User deletion failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
