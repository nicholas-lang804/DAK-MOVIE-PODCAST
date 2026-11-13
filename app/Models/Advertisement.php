<?php
/**
 * Advertisement Model
 * Handles advertisement database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Advertisement {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find advertisement by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT ad.*, s.company_name as sponsor_name, ma.storage_path as image_path
            FROM advertisements ad
            LEFT JOIN sponsors s ON ad.sponsor_id = s.id
            LEFT JOIN media_assets ma ON ad.image_media_id = ma.id
            WHERE ad.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get active advertisements by placement
     * @param string $placement
     * @return array
     */
    public function getActiveByPlacement($placement) {
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT ad.*, s.company_name as sponsor_name, ma.storage_path as image_path
            FROM advertisements ad
            LEFT JOIN sponsors s ON ad.sponsor_id = s.id
            LEFT JOIN media_assets ma ON ad.image_media_id = ma.id
            WHERE ad.status = 'active' 
            AND ad.placement = ?
            AND ad.start_date <= ? 
            AND ad.end_date >= ?
            ORDER BY ad.created_at DESC
        ");
        $stmt->execute([$placement, $today, $today]);
        return $stmt->fetchAll();
    }

    /**
     * Get all advertisements with pagination
     * @param int $limit
     * @param int $offset
     * @param string $status
     * @return array
     */
    public function getAll($limit = 50, $offset = 0, $status = null) {
        $sql = "SELECT ad.*, s.company_name as sponsor_name, ma.storage_path as image_path
                FROM advertisements ad
                LEFT JOIN sponsors s ON ad.sponsor_id = s.id
                LEFT JOIN media_assets ma ON ad.image_media_id = ma.id";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE ad.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY ad.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Create new advertisement
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO advertisements (sponsor_id, title, image_media_id, target_url, placement, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['sponsor_id'] ?? null,
                $data['title'] ?? null,
                $data['image_media_id'] ?? null,
                $data['target_url'] ?? null,
                $data['placement'] ?? 'homepage',
                $data['start_date'],
                $data['end_date'],
                $data['status'] ?? 'active'
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Advertisement creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update advertisement
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
            $sql = "UPDATE advertisements SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Advertisement update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete advertisement
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM advertisements WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Advertisement deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get advertisement count
     * @return int
     */
    public function getCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM advertisements");
        return $stmt->fetch()['count'];
    }

    /**
     * Update expired advertisements
     * @return int
     */
    public function updateExpired() {
        try {
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                UPDATE advertisements 
                SET status = 'expired' 
                WHERE status = 'active' AND end_date < ?
            ");
            $stmt->execute([$today]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Advertisement expiry update failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Expire outdated advertisements
     * @return int
     */
    public function expireOutdated() {
        return $this->updateExpired();
    }
}
?>
