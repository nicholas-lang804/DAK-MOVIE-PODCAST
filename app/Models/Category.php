<?php
/**
 * Category Model
 * Handles category database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Category {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find category by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find category by slug
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug) {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /**
     * Get all categories by type
     * @param string $type
     * @return array
     */
    public function getByType($type) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM categories 
            WHERE category_type = ? 
            ORDER BY name ASC
        ");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    /** Get movie genres with the number of published movies in each genre. */
    public function getMovieGenresWithCounts() {
        $stmt = $this->pdo->query("
            SELECT c.*, COUNT(DISTINCT CASE WHEN m.status = 'published' THEN mc.movie_id END) AS movie_count
            FROM categories c
            LEFT JOIN movie_categories mc ON mc.category_id = c.id
            LEFT JOIN movies m ON m.id = mc.movie_id
            WHERE c.category_type = 'movie'
            GROUP BY c.id
            ORDER BY movie_count DESC, c.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get all categories
     * @return array
     */
    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY category_type, name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Create new category
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO categories (name, slug, description, category_type)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                $data['category_type'] ?? 'article'
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Category creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update category
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
            $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Category update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete category
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Category deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get category count
     * @return int
     */
    public function getCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM categories");
        return $stmt->fetch()['count'];
    }
}
?>
