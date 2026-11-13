<?php
/**
 * Guest Model
 * Handles podcast guest database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Guest {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find guest by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT g.*, ma.storage_path as profile_image_path
            FROM guests g
            LEFT JOIN media_assets ma ON g.profile_image_id = ma.id
            WHERE g.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get all guests
     * @return array
     */
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT g.*, ma.storage_path as profile_image_path
            FROM guests g
            LEFT JOIN media_assets ma ON g.profile_image_id = ma.id
            ORDER BY g.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Create new guest
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO guests (name, biography, profile_image_id, profession, social_links)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['biography'] ?? null,
                $data['profile_image_id'] ?? null,
                $data['profession'] ?? null,
                $data['social_links'] ?? null
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Guest creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update guest
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
            $sql = "UPDATE guests SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Guest update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete guest
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM guests WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Guest deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get episodes by guest
     * @param int $guestId
     * @return array
     */
    public function getEpisodes($guestId) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name
            FROM episodes e
            LEFT JOIN episode_guests eg ON e.id = eg.episode_id
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE eg.guest_id = ? AND e.status = 'published'
            ORDER BY e.published_at DESC
        ");
        $stmt->execute([$guestId]);
        return $stmt->fetchAll();
    }
}
?>
