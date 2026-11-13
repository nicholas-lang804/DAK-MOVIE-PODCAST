<?php
/**
 * UploadSession — resumable chunked upload sessions (8 MB chunks).
 * Additive table; sessions are short-lived, single-user, admin-only.
 */
class UploadSession {
    public const CHUNK_SIZE = 8388608; // 8 MB
    public const TTL_SECONDS = 86400;  // 24h
    private $pdo;
    private static $ready = false;

    public function __construct() {
        $this->pdo = getDB();
        $this->ensureSchema();
    }

    private function ensureSchema() {
        if (self::$ready) return;
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS upload_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                kind VARCHAR(20) NOT NULL DEFAULT 'video',
                filename VARCHAR(255) NOT NULL,
                mime VARCHAR(120) NOT NULL DEFAULT '',
                size_bytes BIGINT NOT NULL,
                received_bytes BIGINT NOT NULL DEFAULT 0,
                sha256 CHAR(64) NULL,
                status ENUM('active','complete','aborted','expired') NOT NULL DEFAULT 'active',
                part_path VARCHAR(500) NULL,
                media_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_us_user (user_id, status),
                INDEX idx_us_token (token_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) { error_log('UploadSession schema: ' . $e->getMessage()); }
        self::$ready = true;
    }

    public function create($userId, $kind, $filename, $mime, $size) {
        $token = bin2hex(random_bytes(32)); // 64 hex chars — capability token, stored hashed
        $stmt = $this->pdo->prepare("INSERT INTO upload_sessions (user_id, token_hash, kind, filename, mime, size_bytes, expires_at)
            VALUES (?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? SECOND))");
        $stmt->execute([(int)$userId, hash('sha256', $token), $kind, basename($filename), $mime, (int)$size, self::TTL_SECONDS]);
        return ['id' => (int)$this->pdo->lastInsertId(), 'token' => $token];
    }

    /** Fetch a session by id+token; enforces owner, expiry and status. */
    public function find($id, $token) {
        $stmt = $this->pdo->prepare("SELECT * FROM upload_sessions WHERE id = ? AND token_hash = ? LIMIT 1");
        $stmt->execute([(int)$id, hash('sha256', (string)$token)]);
        $row = $stmt->fetch();
        if (!$row) return null;
        if ($row['status'] !== 'active' || strtotime($row['expires_at']) < time()) {
            if ($row['status'] === 'active') $this->setStatus($row['id'], 'expired');
            return null;
        }
        return $row;
    }

    public function findForUser($id, $userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM upload_sessions WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([(int)$id, (int)$userId]);
        return $stmt->fetch() ?: null;
    }

    public function addBytes($id, $received) {
        $this->pdo->prepare("UPDATE upload_sessions SET received_bytes = ? WHERE id = ?")->execute([(int)$received, (int)$id]);
    }

    public function setStatus($id, $status, $extra = []) {
        $sets = ['status = ?']; $vals = [$status];
        foreach (['sha256' => 'sha256', 'part_path' => 'part_path', 'media_id' => 'media_id'] as $k => $col) {
            if (array_key_exists($k, $extra)) { $sets[] = "$col = ?"; $vals[] = $extra[$k]; }
        }
        $vals[] = (int)$id;
        return $this->pdo->prepare("UPDATE upload_sessions SET " . implode(',', $sets) . " WHERE id = ?")->execute($vals);
    }

    /** Sessions owned by a user (for resume after refresh). */
    public function forUser($userId, $limit = 10) {
        $stmt = $this->pdo->prepare("SELECT id, kind, filename, size_bytes, received_bytes, status, created_at FROM upload_sessions WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT " . (int)$limit);
        $stmt->execute([(int)$userId]);
        return $stmt->fetchAll();
    }

    public static function chunkDir() {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.chunks';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    public static function partPath($id) {
        return self::chunkDir() . DIRECTORY_SEPARATOR . 'up_' . (int)$id . '.part';
    }
}
