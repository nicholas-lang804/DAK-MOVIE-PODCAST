<?php
/**
 * Activity Log Model
 * Handles activity logging database operations
 */

require_once __DIR__ . '/../../config/database.php';

class ActivityLog {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Create new activity log
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['user_id'] ?? null,
                $data['action'],
                $data['description'] ?? null,
                $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Activity log creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all activity logs with pagination
     * @param int $limit
     * @param int $offset
     * @param int $userId
     * @return array
     */
    public function getAll($limit = 50, $offset = 0, $userId = null) {
        $sql = "SELECT al.*, u.username as user_name
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id";
        
        $params = [];
        if ($userId) {
            $sql .= " WHERE al.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get recent activity logs
     * @param int $limit
     * @return array
     */
    public function getRecent($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT al.*, u.username as user_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get activity logs by action
     * @param string $action
     * @param int $limit
     * @return array
     */
    public function getByAction($action, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT al.*, u.username as user_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.action = ?
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$action, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Delete old activity logs
     * @param int $days
     * @return int
     */
    public function deleteOld($days = 90) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM activity_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Activity log deletion failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get activity log count
     * @return int
     */
    public function getCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM activity_logs");
        return $stmt->fetch()['count'];
    }

    /**
     * Delete old activity logs
     * @param int $days
     * @return int
     */
    public function deleteOldLogs($days = 90) {
        return $this->deleteOld($days);
    }
}
?>
