<?php
/**
 * Movie Review Model
 * Handles movie review database operations
 */

require_once __DIR__ . '/../../config/database.php';

class MovieReview {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find review by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT mr.*, m.title as movie_title, u.username as author_name
            FROM movie_reviews mr
            LEFT JOIN movies m ON mr.movie_id = m.id
            LEFT JOIN users u ON mr.author_id = u.id
            WHERE mr.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get reviews by movie
     * @param int $movieId
     * @param string $status
     * @return array
     */
    public function getByMovie($movieId, $status = 'published') {
        $stmt = $this->pdo->prepare("
            SELECT mr.*, u.username as author_name
            FROM movie_reviews mr
            LEFT JOIN users u ON mr.author_id = u.id
            WHERE mr.movie_id = ? AND mr.status = ?
            ORDER BY mr.published_at DESC
        ");
        $stmt->execute([$movieId, $status]);
        return $stmt->fetchAll();
    }

    /**
     * Get all reviews with pagination
     * @param int $limit
     * @param int $offset
     * @param string $status
     * @return array
     */
    public function getAll($limit = 50, $offset = 0, $status = null) {
        $sql = "SELECT mr.*, m.title as movie_title, u.username as author_name
                FROM movie_reviews mr
                LEFT JOIN movies m ON mr.movie_id = m.id
                LEFT JOIN users u ON mr.author_id = u.id";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE mr.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY mr.published_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Create new review
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO movie_reviews (movie_id, author_id, review_title, review_content, rating, status, published_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['movie_id'],
                $data['author_id'] ?? null,
                $data['review_title'] ?? null,
                $data['review_content'],
                $data['rating'],
                $data['status'] ?? 'draft',
                $data['published_at'] ?? null
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Movie review creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update review
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
            $sql = "UPDATE movie_reviews SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Movie review update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete review
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM movie_reviews WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Movie review deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get average rating for movie
     * @param int $movieId
     * @return float
     */
    public function getAverageRating($movieId) {
        $stmt = $this->pdo->prepare("
            SELECT AVG(rating) as avg_rating
            FROM movie_reviews
            WHERE movie_id = ? AND status = 'published'
        ");
        $stmt->execute([$movieId]);
        $result = $stmt->fetch();
        return $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
    }

    /**
     * Get review count for movie
     * @param int $movieId
     * @return int
     */
    public function getReviewCount($movieId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM movie_reviews
            WHERE movie_id = ? AND status = 'published'
        ");
        $stmt->execute([$movieId]);
        return $stmt->fetch()['count'];
    }
}
?>
