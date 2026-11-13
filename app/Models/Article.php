<?php
/**
 * Article Model
 * Handles entertainment article database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Article {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find article by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find article by slug
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /**
     * Get published articles with pagination
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getPublished($limit = 12, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.status = 'published'
            ORDER BY a.published_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get featured articles
     * @param int $limit
     * @return array
     */
    public function getFeatured($limit = 3) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.status = 'published' AND a.is_featured = 1
            ORDER BY a.published_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get articles by category
     * @param int $categoryId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByCategory($categoryId, $limit = 12, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.status = 'published' AND a.category_id = ?
            ORDER BY a.published_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$categoryId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Create new article
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO articles (author_id, category_id, title, slug, excerpt, content, 
                    featured_image_id, status, is_featured, published_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['author_id'] ?? null,
                $data['category_id'] ?? null,
                $data['title'],
                $data['slug'],
                $data['excerpt'] ?? null,
                $data['content'] ?? null,
                $data['featured_image_id'] ?? null,
                $data['status'] ?? 'draft',
                $data['is_featured'] ?? false,
                $data['published_at'] ?? null
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Article creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update article
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
            $sql = "UPDATE articles SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Article update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete article
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM articles WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Article deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total published article count
     * @return int
     */
    public function getPublishedCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM articles WHERE status = 'published'");
        return $stmt->fetch()['count'];
    }

    /**
     * Search articles
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search($query, $limit = 20) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.status = 'published' 
            AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)
            ORDER BY a.published_at DESC
            LIMIT ?
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get latest articles
     * @param int $limit
     * @return array
     */
    public function getLatest($limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.status = 'published'
            ORDER BY a.published_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
?>
