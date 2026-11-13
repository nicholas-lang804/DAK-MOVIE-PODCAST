<?php
/**
 * Newsletter Subscriber Model
 * Handles newsletter subscription database operations
 */

require_once __DIR__ . '/../../config/database.php';

class NewsletterSubscriber {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find subscriber by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM newsletter_subscribers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find subscriber by email
     * @param string $email
     * @return array|null
     */
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM newsletter_subscribers WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Get all subscribers with pagination
     * @param int $limit
     * @param int $offset
     * @param string $status
     * @return array
     */
    public function getAll($limit = 50, $offset = 0, $status = null) {
        $sql = "SELECT * FROM newsletter_subscribers";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY subscribed_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Create new subscriber
     * @param string $email
     * @return int|false
     */
    public function create($email) {
        try {
            $token = bin2hex(random_bytes(32));
            $stmt = $this->pdo->prepare("
                INSERT INTO newsletter_subscribers (email, status, confirmation_token)
                VALUES (?, 'pending', ?)
            ");
            $stmt->execute([$email, $token]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Newsletter subscriber creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirm subscription
     * @param string $token
     * @return bool
     */
    public function confirm($token) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE newsletter_subscribers 
                SET status = 'active', subscribed_at = NOW(), confirmation_token = NULL
                WHERE confirmation_token = ? AND status = 'pending'
            ");
            return $stmt->execute([$token]);
        } catch (PDOException $e) {
            error_log("Newsletter confirmation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsubscribe
     * @param int $id
     * @return bool
     */
    public function unsubscribe($id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE newsletter_subscribers 
                SET status = 'unsubscribed', unsubscribed_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Newsletter unsubscribe failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete subscriber
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Newsletter subscriber deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get active subscribers count
     * @return int
     */
    public function getActiveCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM newsletter_subscribers WHERE status = 'active'");
        return $stmt->fetch()['count'];
    }

    /**
     * Get total subscribers count
     * @return int
     */
    public function getCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM newsletter_subscribers");
        return $stmt->fetch()['count'];
    }

    /**
     * Get active subscribers
     * @return array
     */
    public function getActiveSubscribers() {
        $stmt = $this->pdo->query("SELECT * FROM newsletter_subscribers WHERE status = 'active'");
        return $stmt->fetchAll();
    }
}
?>
