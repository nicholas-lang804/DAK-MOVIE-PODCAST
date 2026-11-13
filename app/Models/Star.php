<?php
/**
 * Star Model
 * Actors & actresses profiles managed by the admin and shown to visitors before and after login.
 * The table is created automatically on first use, so running the migration is optional.
 */

require_once __DIR__ . '/../../config/database.php';

class Star {
    private $pdo;
    private static $ready = false;

    public function __construct() {
        $this->pdo = getDB();
        $this->ensureTable();
    }

    private function ensureTable() {
        if (self::$ready) return;
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS stars (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                category ENUM('actor','actress') NOT NULL DEFAULT 'actor',
                known_for VARCHAR(255) NULL,
                biography TEXT NULL,
                photo_path VARCHAR(500) NULL,
                video_path VARCHAR(500) NULL,
                video_url VARCHAR(500) NULL,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_star_status (status),
                INDEX idx_star_featured (is_featured, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            error_log('Stars table setup failed: ' . $e->getMessage());
        }
        self::$ready = true;
    }

    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM stars WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public function findBySlug($slug) {
        $stmt = $this->pdo->prepare("SELECT * FROM stars WHERE slug = ? AND status = 'active'");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /** Public list: featured first, then newest. $category = 'actor' | 'actress' | ''. */
    public function getActive($limit = 24, $offset = 0, $category = '', $search = '') {
        $sql = "SELECT * FROM stars WHERE status = 'active'";
        $params = [];
        if (in_array($category, ['actor', 'actress'], true)) { $sql .= " AND category = ?"; $params[] = $category; }
        if ($search !== '') { $sql .= " AND (name LIKE ? OR known_for LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $sql .= " ORDER BY is_featured DESC, sort_order ASC, created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getActiveCount($category = '', $search = '') {
        $sql = "SELECT COUNT(*) FROM stars WHERE status = 'active'";
        $params = [];
        if (in_array($category, ['actor', 'actress'], true)) { $sql .= " AND category = ?"; $params[] = $category; }
        if ($search !== '') { $sql .= " AND (name LIKE ? OR known_for LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getFeatured($limit = 8) {
        $stmt = $this->pdo->prepare("SELECT * FROM stars WHERE status = 'active' ORDER BY is_featured DESC, sort_order ASC, created_at DESC LIMIT " . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRelated($id, $limit = 6) {
        $stmt = $this->pdo->prepare("SELECT * FROM stars WHERE status = 'active' AND id <> ? ORDER BY is_featured DESC, created_at DESC LIMIT " . (int)$limit);
        $stmt->execute([(int)$id]);
        return $stmt->fetchAll();
    }

    /** Admin list (all statuses). */
    public function getAll() {
        return $this->pdo->query("SELECT * FROM stars ORDER BY created_at DESC")->fetchAll();
    }

    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO stars
                (name, slug, category, known_for, biography, photo_path, video_path, video_url, is_featured, sort_order, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'], $data['slug'], $data['category'], $data['known_for'] ?: null, $data['biography'] ?: null,
                $data['photo_path'] ?: null, $data['video_path'] ?: null, $data['video_url'] ?: null,
                (int)$data['is_featured'], (int)$data['sort_order'], $data['status']
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('Star creation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $stmt = $this->pdo->prepare("UPDATE stars SET name = ?, slug = ?, category = ?, known_for = ?, biography = ?,
                photo_path = ?, video_path = ?, video_url = ?, is_featured = ?, sort_order = ?, status = ? WHERE id = ?");
            return $stmt->execute([
                $data['name'], $data['slug'], $data['category'], $data['known_for'] ?: null, $data['biography'] ?: null,
                $data['photo_path'] ?: null, $data['video_path'] ?: null, $data['video_url'] ?: null,
                (int)$data['is_featured'], (int)$data['sort_order'], $data['status'], (int)$id
            ]);
        } catch (PDOException $e) {
            error_log('Star update failed: ' . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM stars WHERE id = ?");
            return $stmt->execute([(int)$id]);
        } catch (PDOException $e) {
            error_log('Star deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Make a slug unique ("jane-doe", "jane-doe-2", ...). */
    public function uniqueSlug($slug, $ignoreId = 0) {
        $base = $slug !== '' ? $slug : 'star';
        $candidate = $base;
        $n = 2;
        while (true) {
            $stmt = $this->pdo->prepare("SELECT id FROM stars WHERE slug = ? AND id <> ? LIMIT 1");
            $stmt->execute([$candidate, (int)$ignoreId]);
            if (!$stmt->fetchColumn()) return $candidate;
            $candidate = $base . '-' . $n++;
        }
    }

    /** Public URL for a stored path ("storage/uploads/stars/x.jpg"). */
    public static function url($path) {
        return $path ? '/TMZ%20PODCAST/' . ltrim($path, '/') : '';
    }

    /**
     * Turn a pasted YouTube / Vimeo link into a safe embed URL. Returns '' for anything else,
     * so only these two hosts can ever be placed in an iframe.
     */
    public static function embedUrl($url) {
        $url = trim((string)$url);
        if ($url === '') return '';
        if (preg_match('~^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        if (preg_match('~^https://(?:www\.)?vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return '';
    }
}
?>
