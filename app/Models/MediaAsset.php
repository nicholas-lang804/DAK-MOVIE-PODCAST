<?php
/**
 * Media Asset Model
 * Handles media library database operations
 */

require_once __DIR__ . '/../../config/database.php';

class MediaAsset {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
        try {
            $this->pdo->exec("ALTER TABLE movies ADD COLUMN allow_download TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Throwable $e) { /* column already exists or table missing */ }
    }

    /**
     * Find media by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT ma.*, u.username as uploaded_by_name
            FROM media_assets ma
            LEFT JOIN users u ON ma.uploaded_by = u.id
            WHERE ma.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get all media with pagination
     * @param int $limit
     * @param int $offset
     * @param string $type
     * @return array
     */
    public function getAll($limit = 50, $offset = 0, $type = null) {
        $sql = "
            SELECT ma.*, u.username as uploaded_by_name
            FROM media_assets ma
            LEFT JOIN users u ON ma.uploaded_by = u.id
        ";
        
        $params = [];
        if ($type) {
            $sql .= " WHERE ma.media_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY ma.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get media by type
     * @param string $type
     * @param int $limit
     * @return array
     */
    public function getByType($type, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT ma.*, u.username as uploaded_by_name
            FROM media_assets ma
            LEFT JOIN users u ON ma.uploaded_by = u.id
            WHERE ma.media_type = ?
            ORDER BY ma.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$type, $limit]);
        return $stmt->fetchAll();
    }

    public function getPublicVideos($limit = 24, $offset = 0, $query = '', $placement = 'all') {
        $hasVideoDisplay = $this->tableHasColumn('movies', 'video_display');
        $sql = "SELECT ma.*, (m.id IS NOT NULL) AS linked_to_movie, COALESCE(m.allow_download, 0) AS movie_allow_download
            FROM media_assets ma
            LEFT JOIN movies m ON m.video_media_id = ma.id
            WHERE ma.media_type = 'video'
            AND (m.id IS NULL OR (m.access_type = 'free'" . ($hasVideoDisplay ? " AND m.video_display <> 'none'" : '') . "))";
        $params = [];

        if ($placement !== 'all' && $hasVideoDisplay) {
            $sql .= ' AND (m.id IS NULL OR m.video_display IN (?, ?))';
            $params[] = $placement;
            $params[] = 'both';
        }

        if ($query !== '') {
            $sql .= ' AND (ma.original_filename LIKE ? OR ma.alt_text LIKE ?)';
            $term = '%' . $query . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= ' ORDER BY ma.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function tableHasColumn($table, $column) {
        $safeTable = in_array($table, ['movies', 'media_assets'], true) ? $table : 'movies';
        $stmt = $this->pdo->query('SHOW COLUMNS FROM `' . $safeTable . '` LIKE ' . $this->pdo->quote($column));
        return (bool) $stmt->fetch();
    }

    /**
     * Search media
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search($query, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT ma.*, u.username as uploaded_by_name
            FROM media_assets ma
            LEFT JOIN users u ON ma.uploaded_by = u.id
            WHERE ma.original_filename LIKE ? OR ma.alt_text LIKE ?
            ORDER BY ma.created_at DESC
            LIMIT ?
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Create new media asset
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO media_assets (uploaded_by, original_filename, stored_filename, 
                    storage_path, media_type, mime_type, file_size, width, height, 
                    duration_seconds, alt_text)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['uploaded_by'] ?? null,
                $data['original_filename'],
                $data['stored_filename'],
                $data['storage_path'],
                $data['media_type'],
                $data['mime_type'] ?? null,
                $data['file_size'] ?? null,
                $data['width'] ?? null,
                $data['height'] ?? null,
                $data['duration_seconds'] ?? null,
                $data['alt_text'] ?? null
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Media asset creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update media asset
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
            $sql = "UPDATE media_assets SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Media asset update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete media asset
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM media_assets WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Media asset deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if media is used anywhere
     * @param int $id
     * @return bool
     */
    public function isMediaUsed($id) {
        $references = [
            ['episodes', 'featured_image_id'], ['episodes', 'audio_media_id'],
            ['articles', 'featured_image_id'], ['movies', 'poster_media_id'], ['movies', 'video_media_id'],
            ['hosts', 'profile_image_id'], ['events', 'poster_media_id'],
            ['episode_media', 'media_id'], ['article_media', 'media_id'],
            ['movie_media', 'media_id'], ['host_media', 'media_id'],
            ['event_media', 'media_id'], ['advertisements', 'image_media_id']
        ];

        foreach ($references as [$table, $column]) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()['count'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get total media count
     * @return int
     */
    public function getCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM media_assets");
        return $stmt->fetch()['count'];
    }

    /**
     * Get media count by type
     * @param string $type
     * @return int
     */
    public function getCountByType($type) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM media_assets WHERE media_type = ?");
        $stmt->execute([$type]);
        return $stmt->fetch()['count'];
    }
}
?>
