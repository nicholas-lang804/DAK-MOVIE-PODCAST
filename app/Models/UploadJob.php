<?php
/**
 * UploadJob — background media jobs (upload, rendition, migrate, backup).
 * Also ensures the additive Phase-1 tables exist (media_renditions, upload_jobs, media_objects).
 */
class UploadJob {
    private $pdo;
    private static $ready = false;

    public function __construct() {
        $this->pdo = getDB();
        $this->ensureSchema();
    }

    /** Additive, idempotent schema — mirrors migrations/2026_media_renditions.sql. */
    private function ensureSchema() {
        if (self::$ready) return;
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS media_renditions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                media_id INT NOT NULL,
                quality VARCHAR(10) NOT NULL,
                storage_driver VARCHAR(10) NOT NULL DEFAULT 'local',
                storage_path VARCHAR(500) NOT NULL,
                file_size BIGINT NOT NULL DEFAULT 0,
                sha256 CHAR(64) NULL,
                status ENUM('pending','processing','ready','failed') NOT NULL DEFAULT 'pending',
                error TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_rendition (media_id, quality, storage_driver),
                INDEX idx_rendition_status (status),
                INDEX idx_rendition_media (media_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS upload_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                media_id INT NULL,
                kind VARCHAR(20) NOT NULL DEFAULT 'upload',
                status ENUM('queued','uploading','processing','ready','failed','cancelled') NOT NULL DEFAULT 'queued',
                progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
                speed_bps BIGINT NOT NULL DEFAULT 0,
                bytes_done BIGINT NOT NULL DEFAULT 0,
                bytes_total BIGINT NOT NULL DEFAULT 0,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
                last_error TEXT NULL,
                payload TEXT NULL,
                remote_token VARCHAR(120) NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_jobs_status (status, kind),
                INDEX idx_jobs_media (media_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS media_objects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                media_id INT NULL,
                driver VARCHAR(10) NOT NULL DEFAULT 'local',
                object_key VARCHAR(500) NOT NULL,
                sha256 CHAR(64) NULL,
                size_bytes BIGINT NOT NULL DEFAULT 0,
                is_original TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_object (driver, object_key),
                INDEX idx_object_media (media_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) { error_log('UploadJob schema: ' . $e->getMessage()); }
        self::$ready = true;
    }

    public function create($kind, array $payload = [], $mediaId = null, $bytesTotal = 0, $createdBy = null) {
        $stmt = $this->pdo->prepare("INSERT INTO upload_jobs (media_id, kind, status, bytes_total, payload, created_by) VALUES (?,?, 'queued', ?, ?, ?)");
        $stmt->execute([$mediaId, $kind, $bytesTotal, json_encode($payload), $createdBy]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update($id, array $fields) {
        $allowed = ['status','progress','speed_bps','bytes_done','bytes_total','attempts','last_error','payload','remote_token','media_id'];
        $set = []; $vals = [];
        foreach ($fields as $k => $v) { if (in_array($k, $allowed, true)) { $set[] = "$k = ?"; $vals[] = $v; } }
        if (!$set) return false;
        $vals[] = (int)$id;
        return $this->pdo->prepare("UPDATE upload_jobs SET " . implode(',', $set) . " WHERE id = ?")->execute($vals);
    }

    public function get($id) { $s = $this->pdo->prepare("SELECT * FROM upload_jobs WHERE id = ?"); $s->execute([(int)$id]); return $s->fetch() ?: null; }

    public function recent($limit = 50, $kind = null) {
        $sql = "SELECT * FROM upload_jobs" . ($kind ? " WHERE kind = " . $this->pdo->quote($kind) : "") . " ORDER BY id DESC LIMIT " . (int)$limit;
        return $this->pdo->query($sql)->fetchAll();
    }

    public function activeCount() {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM upload_jobs WHERE status IN ('queued','uploading','processing')")->fetchColumn();
    }

    /* ---------------- media_renditions ---------------- */

    public function upsertRendition($mediaId, $quality, $driver, $path, $size = 0, $sha = null, $status = 'ready') {
        $stmt = $this->pdo->prepare("INSERT INTO media_renditions (media_id, quality, storage_driver, storage_path, file_size, sha256, status)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE storage_path=VALUES(storage_path), file_size=VALUES(file_size), sha256=VALUES(sha256), status=VALUES(status), error=NULL");
        return $stmt->execute([(int)$mediaId, $quality, $driver, $path, (int)$size, $sha, $status]);
    }

    public function rendition($mediaId, $quality, $driver = null) {
        $sql = "SELECT * FROM media_renditions WHERE media_id = ? AND quality = ?" . ($driver ? " AND storage_driver = " . $this->pdo->quote($driver) : "") . " LIMIT 1";
        $stmt = $this->pdo->prepare($sql); $stmt->execute([(int)$mediaId, $quality]);
        return $stmt->fetch() ?: null;
    }

    public function renditions($mediaId) {
        $stmt = $this->pdo->prepare("SELECT * FROM media_renditions WHERE media_id = ? ORDER BY FIELD(quality,'hls','1080p','720p','480p','original')");
        $stmt->execute([(int)$mediaId]);
        return $stmt->fetchAll();
    }
}
