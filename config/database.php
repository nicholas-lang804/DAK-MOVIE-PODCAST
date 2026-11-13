<?php
/**
 * Database Connection Configuration
 * Uses PDO for secure database connections
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'tmz_podcast');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

/**
 * Get database connection
 * @return PDO
 */
function getDB() {
    global $pdo;
    return $pdo;
}

/** Resolve a media asset id to its public URL with a per-request cache. */
function mediaUrl($mediaId) {
    static $cache = [];
    $mediaId = (int) $mediaId;
    if ($mediaId <= 0) return '';
    if (!array_key_exists($mediaId, $cache)) {
        $stmt = getDB()->prepare('SELECT storage_path FROM media_assets WHERE id = ? LIMIT 1');
        $stmt->execute([$mediaId]);
        $path = $stmt->fetchColumn();
        $cache[$mediaId] = $path ? '/' . ltrim($path, '/') : '';
    }
    return $cache[$mediaId];
}
?>
