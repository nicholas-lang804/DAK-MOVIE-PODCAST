<?php
/**
 * SiteExtras: cast links (movie <-> actors/actresses), member watchlist and site-wide search.
 * Tables are created automatically on first use.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Star.php';

class SiteExtras {
    private $pdo;
    private static $ready = false;
    private $posterExpr = null;

    public function __construct() {
        $this->pdo = getDB();
        new Star(); // makes sure the stars table exists before we join to it
        $this->ensureSchema();
    }

    private function ensureSchema() {
        if (self::$ready) return;
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS movie_cast (
                movie_id INT NOT NULL,
                star_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (movie_id, star_id),
                INDEX idx_movie_cast_star (star_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_watchlist (
                user_id INT NOT NULL,
                movie_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, movie_id),
                INDEX idx_watchlist_movie (movie_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            error_log('SiteExtras schema setup failed: ' . $e->getMessage());
        }
        self::$ready = true;
    }

    /** Poster path expression that works whether or not movies.poster_path exists. */
    private function poster() {
        if ($this->posterExpr === null) {
            $has = false;
            try { $has = (bool)$this->pdo->query("SHOW COLUMNS FROM movies LIKE 'poster_path'")->fetch(); } catch (Throwable $e) {}
            $this->posterExpr = $has ? "COALESCE(NULLIF(m.poster_path, ''), ma.storage_path)" : "ma.storage_path";
        }
        return $this->posterExpr;
    }

    /* -------------------------------------------------------------------- cast */

    public function castForMovie($movieId) {
        try {
            $s = $this->pdo->prepare("SELECT s.id, s.name, s.slug, s.category, s.photo_path, s.known_for
                FROM movie_cast mc JOIN stars s ON s.id = mc.star_id
                WHERE mc.movie_id = ? AND s.status = 'active' ORDER BY s.is_featured DESC, s.name ASC");
            $s->execute([(int)$movieId]);
            return $s->fetchAll();
        } catch (Throwable $e) { error_log('castForMovie: ' . $e->getMessage()); return []; }
    }

    public function moviesForStar($starId) {
        try {
            $s = $this->pdo->prepare("SELECT m.id, m.title, m.slug, " . $this->poster() . " AS poster_path
                FROM movie_cast mc JOIN movies m ON m.id = mc.movie_id
                LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
                WHERE mc.star_id = ? AND m.status = 'published' ORDER BY m.release_date DESC, m.id DESC");
            $s->execute([(int)$starId]);
            return $s->fetchAll();
        } catch (Throwable $e) { error_log('moviesForStar: ' . $e->getMessage()); return []; }
    }

    public function starMovieIds($starId) {
        try {
            $s = $this->pdo->prepare("SELECT movie_id FROM movie_cast WHERE star_id = ?");
            $s->execute([(int)$starId]);
            return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) { return []; }
    }

    public function publishedMoviesLite() {
        try {
            return $this->pdo->query("SELECT id, title FROM movies WHERE status = 'published' ORDER BY title ASC LIMIT 1000")->fetchAll();
        } catch (Throwable $e) { return []; }
    }

    /** Replace the list of movies a star appears in. */
    public function setStarMovies($starId, array $movieIds) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $movieIds), function ($v) { return $v > 0; })));
        try {
            $this->pdo->beginTransaction();
            $d = $this->pdo->prepare("DELETE FROM movie_cast WHERE star_id = ?");
            $d->execute([(int)$starId]);
            $i = $this->pdo->prepare("INSERT IGNORE INTO movie_cast (movie_id, star_id) VALUES (?, ?)");
            foreach ($ids as $mid) $i->execute([$mid, (int)$starId]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('setStarMovies: ' . $e->getMessage());
            return false;
        }
    }

    /* --------------------------------------------------------------- watchlist */

    public function inWatchlist($userId, $movieId) {
        try {
            $s = $this->pdo->prepare("SELECT 1 FROM user_watchlist WHERE user_id = ? AND movie_id = ?");
            $s->execute([(int)$userId, (int)$movieId]);
            return (bool)$s->fetchColumn();
        } catch (Throwable $e) { return false; }
    }

    /** Returns true when the movie is now in the list, false when it was removed, null when the movie does not exist. */
    public function toggleWatchlist($userId, $movieId) {
        $m = $this->pdo->prepare("SELECT 1 FROM movies WHERE id = ? AND status = 'published'");
        $m->execute([(int)$movieId]);
        if (!$m->fetchColumn()) return null;
        if ($this->inWatchlist($userId, $movieId)) {
            $d = $this->pdo->prepare("DELETE FROM user_watchlist WHERE user_id = ? AND movie_id = ?");
            $d->execute([(int)$userId, (int)$movieId]);
            return false;
        }
        $i = $this->pdo->prepare("INSERT IGNORE INTO user_watchlist (user_id, movie_id) VALUES (?, ?)");
        $i->execute([(int)$userId, (int)$movieId]);
        return true;
    }

    public function watchlist($userId, $limit = 100) {
        $limit = max(1, min(300, (int)$limit));
        try {
            $s = $this->pdo->prepare("SELECT m.id, m.title, m.slug, " . $this->poster() . " AS poster_path
                FROM user_watchlist w JOIN movies m ON m.id = w.movie_id
                LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
                WHERE w.user_id = ? AND m.status = 'published' ORDER BY w.created_at DESC LIMIT $limit");
            $s->execute([(int)$userId]);
            return $s->fetchAll();
        } catch (Throwable $e) { error_log('watchlist: ' . $e->getMessage()); return []; }
    }

    /* ------------------------------------------------------------------ search */

    /** Search across movies, stars, episodes, articles and live shows. Each group fails independently. */
    public function search($q, $perGroup = 8) {
        $q = trim((string)$q);
        $perGroup = max(1, min(24, (int)$perGroup));
        $out = ['movies' => [], 'stars' => [], 'episodes' => [], 'articles' => [], 'live' => []];
        if (function_exists('mb_strlen') ? mb_strlen($q) < 2 : strlen($q) < 2) return $out;
        $like = '%' . addcslashes($q, '\\%_') . '%';

        $run = function ($key, $sql, array $params) use (&$out) {
            try {
                $s = $this->pdo->prepare($sql);
                $s->execute($params);
                $out[$key] = $s->fetchAll();
            } catch (Throwable $e) { error_log("Search ($key): " . $e->getMessage()); }
        };
        $run('movies', "SELECT m.id, m.title, m.slug, m.synopsis, " . $this->poster() . " AS poster_path FROM movies m
            LEFT JOIN media_assets ma ON m.poster_media_id = ma.id
            WHERE m.status = 'published' AND (m.title LIKE ? OR m.synopsis LIKE ? OR m.director LIKE ?)
            ORDER BY m.release_date DESC LIMIT $perGroup", [$like, $like, $like]);
        $run('stars', "SELECT id, name, slug, category, known_for, photo_path FROM stars
            WHERE status = 'active' AND (name LIKE ? OR known_for LIKE ? OR biography LIKE ?)
            ORDER BY is_featured DESC, name ASC LIMIT $perGroup", [$like, $like, $like]);
        $run('episodes', "SELECT id, title, slug FROM episodes WHERE status = 'published' AND (title LIKE ? OR description LIKE ?)
            ORDER BY published_at DESC LIMIT $perGroup", [$like, $like]);
        $run('articles', "SELECT id, title, slug FROM articles WHERE status = 'published' AND title LIKE ?
            ORDER BY published_at DESC LIMIT $perGroup", [$like]);
        $run('live', "SELECT id, title, status FROM live_podcasts WHERE (title LIKE ? OR description LIKE ?)
            ORDER BY id DESC LIMIT $perGroup", [$like, $like]);
        return $out;
    }
}
?>
