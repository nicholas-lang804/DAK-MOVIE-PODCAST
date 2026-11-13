<?php
/**
 * Host Model
 * Handles podcast host database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Host {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find host by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT h.*, ma.storage_path as profile_image_path
            FROM hosts h
            LEFT JOIN media_assets ma ON h.profile_image_id = ma.id
            WHERE h.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find host by slug
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug) {
        $stmt = $this->pdo->prepare("
            SELECT h.*, ma.storage_path as profile_image_path
            FROM hosts h
            LEFT JOIN media_assets ma ON h.profile_image_id = ma.id
            WHERE h.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /**
     * Get all active hosts
     * @return array
     */
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT h.*, ma.storage_path as profile_image_path
            FROM hosts h
            LEFT JOIN media_assets ma ON h.profile_image_id = ma.id
            WHERE h.status = 'active'
            ORDER BY h.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Create new host
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO hosts (name, slug, biography, profile_image_id, role, social_links, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['slug'],
                $data['biography'] ?? null,
                $data['profile_image_id'] ?? null,
                $data['role'] ?? null,
                $data['social_links'] ?? null,
                $data['status'] ?? 'active'
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Host creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update host
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
            $sql = "UPDATE hosts SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Host update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete host
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM hosts WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Host deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get episodes by host
     * @param int $hostId
     * @return array
     */
    public function getEpisodes($hostId) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name
            FROM episodes e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.created_by = ? AND e.status = 'published'
            ORDER BY e.published_at DESC
        ");
        $stmt->execute([$hostId]);
        return $stmt->fetchAll();
    }
}
?>
