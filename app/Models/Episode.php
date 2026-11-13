<?php
/**
 * Episode Model
 * Handles podcast episode database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Episode {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find episode by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name, u.username as created_by_name
            FROM episodes e
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find episode by slug
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name, u.username as created_by_name
            FROM episodes e
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /**
     * Get published episodes with pagination
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getPublished($limit = 12, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name
            FROM episodes e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.status = 'published'
            ORDER BY e.published_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get featured episodes
     * @param int $limit
     * @return array
     */
    public function getFeatured($limit = 3) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name
            FROM episodes e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.status = 'published' AND e.is_featured = 1
            ORDER BY e.published_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get episodes by category
     * @param int $categoryId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByCategory($categoryId, $limit = 12, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name
            FROM episodes e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.status = 'published' AND e.category_id = ?
            ORDER BY e.published_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$categoryId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Create new episode
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO episodes (title, slug, description, episode_number, season_number, 
                    category_id, featured_image_id, audio_media_id, video_url, duration_seconds, 
                    status, is_featured, published_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['title'],
                $data['slug'],
                $data['description'] ?? null,
                $data['episode_number'] ?? null,
                $data['season_number'] ?? 1,
                $data['category_id'] ?? null,
                $data['featured_image_id'] ?? null,
                $data['audio_media_id'] ?? null,
                $data['video_url'] ?? null,
                $data['duration_seconds'] ?? null,
                $data['status'] ?? 'draft',
                $data['is_featured'] ?? false,
                $data['published_at'] ?? null,
                $data['created_by'] ?? null
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Episode creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update episode
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
            $sql = "UPDATE episodes SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Episode update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete episode
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM episodes WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Episode deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total published episode count
     * @return int
     */
    public function getPublishedCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM episodes WHERE status = 'published'");
        return $stmt->fetch()['count'];
    }

    /**
     * Search episodes
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search($query, $limit = 20) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name
            FROM episodes e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.status = 'published' 
            AND (e.title LIKE ? OR e.description LIKE ?)
            ORDER BY e.published_at DESC
            LIMIT ?
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get latest episodes
     * @param int $limit
     * @return array
     */
    public function getLatest($limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.name as category_name
            FROM episodes e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.status = 'published'
            ORDER BY e.published_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
?>
