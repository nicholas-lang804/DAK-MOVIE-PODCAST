<?php
/**
 * Sponsor Model
 * Handles sponsor database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Sponsor {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Find sponsor by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM sponsors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get all sponsors
     * @return array
     */
    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM sponsors ORDER BY company_name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Get active sponsors
     * @return array
     */
    public function getActive() {
        $stmt = $this->pdo->query("SELECT * FROM sponsors WHERE status = 'active' ORDER BY company_name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Create new sponsor
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sponsors (company_name, contact_person, email, phone, website, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['company_name'],
                $data['contact_person'] ?? null,
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['website'] ?? null,
                $data['status'] ?? 'active'
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Sponsor creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update sponsor
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
            $sql = "UPDATE sponsors SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Sponsor update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete sponsor
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sponsors WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Sponsor deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get sponsor count
     * @return int
     */
    public function getCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM sponsors");
        return $stmt->fetch()['count'];
    }
}
?>
