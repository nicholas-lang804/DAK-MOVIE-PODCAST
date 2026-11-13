<?php
/**
 * Contact Message Model
 * Handles contact message database operations
 */

require_once __DIR__ . '/../../config/database.php';

class ContactMessage {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find message by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get all messages with pagination
     * @param int $limit
     * @param int $offset
     * @param string $status
     * @return array
     */
    public function getAll($limit = 50, $offset = 0, $status = null) {
        $sql = "SELECT * FROM contact_messages";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Create new contact message
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message, enquiry_type, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['subject'] ?? null,
                $data['message'],
                $data['enquiry_type'] ?? 'general',
                $data['status'] ?? 'new'
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Contact message creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update message status
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status) {
        try {
            $stmt = $this->pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch (PDOException $e) {
            error_log("Contact message status update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete message
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Contact message deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get new messages count
     * @return int
     */
    public function getNewCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'new'");
        return $stmt->fetch()['count'];
    }
}
?>
