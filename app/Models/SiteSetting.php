<?php
/**
 * Site Setting Model
 * Handles website settings database operations
 */

require_once __DIR__ . '/../../config/database.php';

class SiteSetting {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Get setting value by key
     * @param string $key
     * @return string|null
     */
    public function get($key) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    }

    /**
     * Get all settings
     * @return array
     */
    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM site_settings ORDER BY setting_key ASC");
        return $stmt->fetchAll();
    }

    /** All settings as key => value map. */
    public function getAllKeyed() {
        $out = [];
        foreach ($this->getAll() as $row) { $out[$row['setting_key']] = $row['setting_value']; }
        return $out;
    }

    /**
     * Set setting value
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function set($key, $value) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            return $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            error_log("Site setting update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update multiple settings
     * @param array $settings
     * @return bool
     */
    public function setMultiple($settings) {
        try {
            $this->pdo->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO site_settings (setting_key, setting_value) 
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$key, $value]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Site settings update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Site-wide downloads switch (admin-controlled). Off unless explicitly enabled.
     * @return bool
     */
    public static function downloadsEnabled() {
        static $enabled = null;
        if ($enabled === null) {
            try {
                $stmt = getDB()->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'downloads_enabled' LIMIT 1");
                $stmt->execute();
                $value = $stmt->fetchColumn();
                $enabled = in_array(strtolower((string)$value), ['1', 'true', 'on', 'yes'], true);
            } catch (Throwable $e) {
                $enabled = false;
            }
        }
        return $enabled;
    }

    /**
     * Delete setting
     * @param string $key
     * @return bool
     */
    public function delete($key) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM site_settings WHERE setting_key = ?");
            return $stmt->execute([$key]);
        } catch (PDOException $e) {
            error_log("Site setting deletion failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
