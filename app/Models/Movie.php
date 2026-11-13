<?php
/**
 * Movie Model
 * Handles movie database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Movie {
    private $pdo;

    public function __construct() {
        $this->pdo = getDB();
        $this->ensureAllowDownloadColumn();
    }

    /** Per-movie download opt-in. Added automatically so older installs get it too. */
    private function ensureAllowDownloadColumn() {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $this->pdo->exec("ALTER TABLE movies ADD COLUMN allow_download TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Throwable $e) { /* column already exists */ }
    }

    /**
     * Find movie by ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $posterColumn = $this->tableHasColumn('movies', 'poster_path') ? 'm.poster_path' : 'ma.storage_path';
        $stmt = $this->pdo->prepare("
            SELECT m.*, COALESCE(" . $posterColumn . ", ma.storage_path) as poster_path,
                   mv.storage_path as video_path, mv.original_filename as video_filename,
                   mv.mime_type as video_mime_type,
                   (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                    FROM movie_categories mcg JOIN categories c ON c.id = mcg.category_id
                    WHERE mcg.movie_id = m.id) AS genre_names
            FROM movies m
            LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
            LEFT JOIN media_assets mv ON m.video_media_id = mv.id AND mv.media_type = 'video'
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find movie by slug
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug) {
        $posterColumn = $this->tableHasColumn('movies', 'poster_path') ? 'm.poster_path' : 'ma.storage_path';
        $stmt = $this->pdo->prepare("
            SELECT m.*, COALESCE(" . $posterColumn . ", ma.storage_path) as poster_path,
                   mv.storage_path as video_path, mv.original_filename as video_filename,
                   mv.mime_type as video_mime_type,
                   (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                    FROM movie_categories mcg JOIN categories c ON c.id = mcg.category_id
                    WHERE mcg.movie_id = m.id) AS genre_names
            FROM movies m
            LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
            LEFT JOIN media_assets mv ON m.video_media_id = mv.id AND mv.media_type = 'video'
            WHERE m.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /**
     * Get published movies with pagination
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getPublished($limit = 12, $offset = 0) {
        $posterColumn = $this->tableHasColumn('movies', 'poster_path') ? 'm.poster_path' : 'ma.storage_path';
        $stmt = $this->pdo->prepare("
            SELECT m.*, COALESCE(" . $posterColumn . ", ma.storage_path) as poster_path,
                   mv.storage_path as video_path, mv.original_filename as video_filename,
                   mv.mime_type as video_mime_type,
                   (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                    FROM movie_categories mcg JOIN categories c ON c.id = mcg.category_id
                    WHERE mcg.movie_id = m.id) AS genre_names
            FROM movies m
            LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
            LEFT JOIN media_assets mv ON m.video_media_id = mv.id AND mv.media_type = 'video'
            WHERE m.status = 'published'
            ORDER BY m.release_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get latest movies
     * @param int $limit
     * @return array
     */
    public function getLatest($limit = 6) {
        $posterColumn = $this->tableHasColumn('movies', 'poster_path') ? 'm.poster_path' : 'ma.storage_path';
        $stmt = $this->pdo->prepare("
            SELECT m.*, COALESCE(" . $posterColumn . ", ma.storage_path) as poster_path,
                   mv.storage_path as video_path, mv.original_filename as video_filename,
                   mv.mime_type as video_mime_type,
                   (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                    FROM movie_categories mcg JOIN categories c ON c.id = mcg.category_id
                    WHERE mcg.movie_id = m.id) AS genre_names
            FROM movies m
            LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
            LEFT JOIN media_assets mv ON m.video_media_id = mv.id AND mv.media_type = 'video'
            WHERE m.status = 'published'
            ORDER BY m.release_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Create new movie
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        try {
            $columns = [
                'title', 'slug', 'synopsis', 'video_media_id', 'trailer_url',
                'release_date', 'director', 'runtime_minutes', 'status', 'access_type'
            ];
            $values = [
                $data['title'],
                $data['slug'],
                $data['synopsis'] ?? null,
                $data['video_media_id'] ?? null,
                $data['trailer_url'] ?? null,
                $data['release_date'] ?? null,
                $data['director'] ?? null,
                $data['runtime_minutes'] ?? null,
                $data['status'] ?? 'draft',
                $data['access_type'] ?? 'free'
            ];

            if ($this->tableHasColumn('movies', 'poster_path')) {
                $columns[] = 'poster_path';
                $values[] = $data['poster_path'] ?? null;
            } elseif (!empty($data['poster_media_id'])) {
                $columns[] = 'poster_media_id';
                $values[] = $data['poster_media_id'];
            }

            if ($this->tableHasColumn('movies', 'video_display')) {
                $columns[] = 'video_display';
                $values[] = $data['video_display'] ?? 'video_centre';
            }

            if ($this->tableHasColumn('movies', 'allow_download')) {
                $columns[] = 'allow_download';
                $values[] = !empty($data['allow_download']) ? 1 : 0;
            }

            $columns[] = 'price';
            $values[] = ($data['access_type'] ?? 'free') === 'paid' ? ($data['price'] ?? null) : null;
            $columns[] = 'price_currency';
            $values[] = $data['price_currency'] ?? 'USD';

            $placeholders = array_fill(0, count($columns), '?');
            $stmt = $this->pdo->prepare("INSERT INTO movies (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")");
            $stmt->execute($values);
            $movieId = $this->pdo->lastInsertId();
            $this->setGenres($movieId, $data['genre_ids'] ?? []);
            return $movieId;
        } catch (PDOException $e) {
            error_log("Movie creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update movie
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($key === 'id' || $key === 'genre_ids') {
                    continue;
                }
                if ($key === 'video_display' && !$this->tableHasColumn('movies', 'video_display')) {
                    continue;
                }
                if ($key === 'allow_download' && !$this->tableHasColumn('movies', 'allow_download')) {
                    continue;
                }
                if ($key === 'poster_path' && !$this->tableHasColumn('movies', 'poster_path')) {
                    continue;
                }
                if ($key === 'poster_media_id' && !$this->tableHasColumn('movies', 'poster_media_id')) {
                    continue;
                }
                $fields[] = "$key = ?";
                $values[] = $value;
            }

            if (empty($fields)) {
                return true;
            }

            $values[] = $id;
            $sql = "UPDATE movies SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($values);
            if ($result && array_key_exists('genre_ids', $data)) {
                $this->setGenres($id, $data['genre_ids']);
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Movie update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete movie
     * @param int $id
     * @return bool
     */
    private function tableHasColumn($table, $column) {
        $safeTable = in_array($table, ['movies'], true) ? $table : 'movies';
        $stmt = $this->pdo->query('SHOW COLUMNS FROM `' . $safeTable . '` LIKE ' . $this->pdo->quote($column));
        return (bool) $stmt->fetch();
    }

    public function delete($id) {
        try {
            foreach (['movie_categories', 'movie_reviews'] as $table) {
                try { $this->pdo->prepare("DELETE FROM $table WHERE movie_id = ?")->execute([$id]); } catch (Throwable $e) {}
            }
            $stmt = $this->pdo->prepare("DELETE FROM movies WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Movie deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total published movie count
     * @return int
     */
    public function getPublishedCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM movies WHERE status = 'published'");
        return $stmt->fetch()['count'];
    }

    public function getPublishedCountByGenre($categoryId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT m.id) FROM movies m JOIN movie_categories mc ON mc.movie_id = m.id WHERE m.status = 'published' AND mc.category_id = ?");
        $stmt->execute([(int) $categoryId]);
        return (int) $stmt->fetchColumn();
    }

    public function getGenreIds($movieId) {
        $stmt = $this->pdo->prepare("SELECT category_id FROM movie_categories WHERE movie_id = ? ORDER BY category_id");
        $stmt->execute([(int) $movieId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function setGenres($movieId, $genreIds) {
        $delete = $this->pdo->prepare("DELETE FROM movie_categories WHERE movie_id = ?");
        $delete->execute([(int) $movieId]);
        $insert = $this->pdo->prepare("INSERT IGNORE INTO movie_categories (movie_id, category_id) VALUES (?, ?)");
        foreach (array_unique(array_map('intval', (array) $genreIds)) as $genreId) {
            if ($genreId > 0) {
                $insert->execute([(int) $movieId, $genreId]);
            }
        }
    }

    /**
     * Search movies
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search($query, $limit = 20) {
        $posterColumn = $this->tableHasColumn('movies', 'poster_path') ? 'm.poster_path' : 'ma.storage_path';
        $stmt = $this->pdo->prepare("
            SELECT m.*, COALESCE(" . $posterColumn . ", ma.storage_path) as poster_path,
                   mv.storage_path as video_path, mv.original_filename as video_filename,
                   mv.mime_type as video_mime_type,
                   (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                    FROM movie_categories mcg JOIN categories c ON c.id = mcg.category_id
                    WHERE mcg.movie_id = m.id) AS genre_names
            FROM movies m
            LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
            LEFT JOIN media_assets mv ON m.video_media_id = mv.id AND mv.media_type = 'video'
            WHERE m.status = 'published' 
            AND (m.title LIKE ? OR m.synopsis LIKE ? OR m.director LIKE ?)
            ORDER BY m.release_date DESC
            LIMIT ?
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get movie reviews
     * @param int $movieId
     * @return array
     */
    public function getReviews($movieId) {
        $stmt = $this->pdo->prepare("
            SELECT mr.*, u.username as author_name
            FROM movie_reviews mr
            LEFT JOIN users u ON mr.author_id = u.id
            WHERE mr.movie_id = ? AND mr.status = 'published'
            ORDER BY mr.created_at DESC
        ");
        $stmt->execute([$movieId]);
        return $stmt->fetchAll();
    }

    /**
     * Get movies by genre
     * @param string $genre
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByGenre($genre, $limit = 12, $offset = 0) {
        $posterColumn = $this->tableHasColumn('movies', 'poster_path') ? 'm.poster_path' : 'ma.storage_path';
        $stmt = $this->pdo->prepare("
            SELECT m.*, COALESCE(" . $posterColumn . ", ma.storage_path) as poster_path,
                   mv.storage_path as video_path, mv.original_filename as video_filename,
                   mv.mime_type as video_mime_type,
                   (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                    FROM movie_categories mcg JOIN categories c ON c.id = mcg.category_id
                    WHERE mcg.movie_id = m.id) AS genre_names
            FROM movies m
            LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
            LEFT JOIN media_assets mv ON m.video_media_id = mv.id AND mv.media_type = 'video'
            JOIN movie_categories mc ON mc.movie_id = m.id
            WHERE m.status = 'published' AND mc.category_id = ?
            ORDER BY m.release_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$genre, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get average rating for movie
     * @param int $movieId
     * @return float
     */
    public function getAverageRating($movieId) {
        $stmt = $this->pdo->prepare("
            SELECT AVG(rating) as avg_rating
            FROM movie_reviews
            WHERE movie_id = ? AND status = 'published'
        ");
        $stmt->execute([$movieId]);
        $result = $stmt->fetch();
        return $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
    }
}
?>
