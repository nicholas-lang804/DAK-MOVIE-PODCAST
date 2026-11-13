<?php
/**
 * Comment Model
 * Handles comment database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Comment {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find comment by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get comments by content type and ID
     * @param string $type episode|article|movie
     * @param int $contentId
     * @param string $status
     * @return array
     */
    public function getByContent($type, $contentId, $status = 'approved') {
        $column = $type . '_id';
        $stmt = $this->pdo->prepare("
            SELECT * FROM comments 
            WHERE $column = ? AND status = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$contentId, $status]);
        return $stmt->fetchAll();
    }

    /**
     * Get all comments with pagination
     * @param int $limit
     * @param int $offset
     * @param string $status
     * @return array
     */
    public function getAll($limit = 50, $offset = 0, $status = null) {
        $sql = "SELECT c.*, e.title as episode_title, a.title as article_title, m.title as movie_title
                FROM comments c
                LEFT JOIN episodes e ON c.episode_id = e.id
                LEFT JOIN articles a ON c.article_id = a.id
                LEFT JOIN movies m ON c.movie_id = m.id";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE c.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Create new comment
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO comments (episode_id, article_id, movie_id, user_name, user_email, comment, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['episode_id'] ?? null,
                $data['article_id'] ?? null,
                $data['movie_id'] ?? null,
                $data['user_name'],
                $data['user_email'] ?? null,
                $data['comment'],
                $data['status'] ?? 'pending'
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Comment creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update comment status
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status) {
        try {
            $stmt = $this->pdo->prepare("UPDATE comments SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch (PDOException $e) {
            error_log("Comment status update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete comment
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM comments WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Comment deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get pending comments count
     * @return int
     */
    public function getPendingCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM comments WHERE status = 'pending'");
        return $stmt->fetch()['count'];
    }

    /**
     * Get comments by user email
     * @param string $email
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByUser($email, $limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM comments 
            WHERE user_email = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$email, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get comments count by user email
     * @param string $email
     * @return int
     */
    public function getByUserCount($email) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE user_email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch()['count'];
    }
}
?>
