<?php
/**
 * Music model: artists, albums, tracks (audio + optional music video), likes, plays, playlists.
 * Tables are created automatically on first use.
 *
 * Access rules per track:
 *   play_access      everyone | members | subscribers   (all-access subscription)
 *   download_access  off | everyone | members | subscribers
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/SiteSetting.php';

class Music {
    const GENRES = ['Afrobeat', 'Afropop', 'Hip Hop', 'R&B', 'Pop', 'Gospel', 'Reggae', 'Dancehall', 'Rock', 'Jazz', 'Soul', 'Electronic', 'Soundtrack', 'Traditional', 'Other'];

    /** Detected MIME type => stored extension, for uploads. */
    const AUDIO_TYPES = ['audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac',
        'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav',
        'audio/flac' => 'flac', 'audio/x-flac' => 'flac', 'audio/webm' => 'webm'];
    const VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/ogg' => 'ogv'];
    const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private $pdo;
    private static $ready = false;

    /** Content-Type for a stored media file, from its extension. */
    public static function mimeFor($path) {
        $map = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'flac' => 'audio/flac',
            'webm' => 'video/webm', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'ogv' => 'video/ogg'];
        $ext = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
        return $map[$ext] ?? 'application/octet-stream';
    }

    public function __construct() {
        $this->pdo = getDB();
        $this->ensureSchema();
    }

    private function ensureSchema() {
        if (self::$ready) return;
        $opts = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $sql = [
            "CREATE TABLE IF NOT EXISTS music_artists (
                id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE,
                bio TEXT NULL, photo_path VARCHAR(500) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) $opts",
            "CREATE TABLE IF NOT EXISTS music_albums (
                id INT AUTO_INCREMENT PRIMARY KEY, artist_id INT NOT NULL, title VARCHAR(190) NOT NULL, slug VARCHAR(220) NOT NULL UNIQUE,
                cover_path VARCHAR(500) NULL, release_year SMALLINT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_music_albums_artist (artist_id)
            ) $opts",
            "CREATE TABLE IF NOT EXISTS music_tracks (
                id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, slug VARCHAR(220) NOT NULL UNIQUE,
                artist_id INT NOT NULL, album_id INT NULL, genre VARCHAR(60) NULL, track_no INT NOT NULL DEFAULT 0,
                duration_seconds INT NOT NULL DEFAULT 0, audio_path VARCHAR(500) NOT NULL, video_path VARCHAR(500) NULL,
                cover_path VARCHAR(500) NULL, lyrics MEDIUMTEXT NULL,
                is_explicit TINYINT(1) NOT NULL DEFAULT 0, is_featured TINYINT(1) NOT NULL DEFAULT 0,
                play_access ENUM('everyone','members','subscribers') NOT NULL DEFAULT 'everyone',
                download_access ENUM('off','everyone','members','subscribers') NOT NULL DEFAULT 'members',
                status ENUM('published','hidden') NOT NULL DEFAULT 'published',
                plays_total INT NOT NULL DEFAULT 0, downloads_total INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_music_tracks_artist (artist_id), INDEX idx_music_tracks_album (album_id),
                INDEX idx_music_tracks_genre (genre), INDEX idx_music_tracks_status (status, created_at)
            ) $opts",
            "CREATE TABLE IF NOT EXISTS music_likes (
                user_id INT NOT NULL, track_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, track_id), INDEX idx_music_likes_track (track_id)
            ) $opts",
            "CREATE TABLE IF NOT EXISTS music_plays (
                id BIGINT AUTO_INCREMENT PRIMARY KEY, track_id INT NOT NULL, user_id INT NULL, played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_music_plays_track (track_id, played_at), INDEX idx_music_plays_user (user_id, played_at)
            ) $opts",
            "CREATE TABLE IF NOT EXISTS music_playlists (
                id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(120) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_music_playlists_user (user_id)
            ) $opts",
            "CREATE TABLE IF NOT EXISTS music_playlist_tracks (
                playlist_id INT NOT NULL, track_id INT NOT NULL, position INT NOT NULL DEFAULT 0, added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (playlist_id, track_id)
            ) $opts",
        ];
        try {
            foreach ($sql as $q) $this->pdo->exec($q);
        } catch (Throwable $e) {
            error_log('Music schema setup failed: ' . $e->getMessage());
        }
        self::$ready = true;
    }

    /* ----------------------------------------------------------------- helpers */

    public static function slugify($text) {
        $t = strtolower(trim((string)$text));
        $t = preg_replace('/[^a-z0-9]+/', '-', $t);
        $t = trim($t, '-');
        return $t !== '' ? substr($t, 0, 150) : 'item';
    }

    private function uniqueSlug($table, $base, $ignoreId = 0) {
        $allowed = ['music_artists', 'music_albums', 'music_tracks'];
        if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('Bad table');
        $slug = $base; $n = 2;
        while (true) {
            $s = $this->pdo->prepare("SELECT id FROM $table WHERE slug = ? AND id <> ? LIMIT 1");
            $s->execute([$slug, (int)$ignoreId]);
            if (!$s->fetchColumn()) return $slug;
            $slug = $base . '-' . $n++;
        }
    }

    public static function url($path) {
        return $path ? '/TMZ%20PODCAST/' . ltrim($path, '/') : '';
    }

    /** Evaluate an access level for a viewer. $viewer = ['user_id'=>int,'sub'=>bool,'admin'=>bool]. */
    public static function allowed($level, array $viewer) {
        if (!empty($viewer['admin']) || $level === 'everyone') return true;
        if ($level === 'off') return false;
        if ($level === 'members') return !empty($viewer['user_id']);
        if ($level === 'subscribers') return !empty($viewer['user_id']) && !empty($viewer['sub']);
        return false;
    }

    public static function accessHint($level) {
        if ($level === 'off') return 'Downloads are not available for this track.';
        if ($level === 'members') return 'Sign in to download.';
        if ($level === 'subscribers') return 'Download is included with All Access.';
        return '';
    }

    /* ------------------------------------------------------------ track queries */

    private function base() {
        return "SELECT t.*, a.name AS artist_name, a.slug AS artist_slug, al.title AS album_title, al.slug AS album_slug,
                    COALESCE(NULLIF(t.cover_path, ''), al.cover_path) AS cover,
                    (SELECT COUNT(*) FROM music_likes l WHERE l.track_id = t.id) AS likes_total
                FROM music_tracks t
                JOIN music_artists a ON a.id = t.artist_id
                LEFT JOIN music_albums al ON al.id = t.album_id";
    }

    private function fetch($where, array $params, $order, $limit = 24, $offset = 0) {
        $limit = max(1, min(500, (int)$limit)); $offset = max(0, (int)$offset);
        try {
            $s = $this->pdo->prepare($this->base() . " WHERE $where ORDER BY $order LIMIT $limit OFFSET $offset");
            $s->execute($params);
            return $s->fetchAll();
        } catch (Throwable $e) { error_log('Music fetch: ' . $e->getMessage()); return []; }
    }

    public function newest($limit = 12) { return $this->fetch("t.status = 'published'", [], 't.created_at DESC, t.id DESC', $limit); }
    public function featured($limit = 1) { return $this->fetch("t.status = 'published' AND t.is_featured = 1", [], 't.updated_at DESC', $limit); }
    public function videos($limit = 12) { return $this->fetch("t.status = 'published' AND t.video_path IS NOT NULL AND t.video_path <> ''", [], 't.created_at DESC', $limit); }
    public function byGenre($genre, $limit = 100) { return $this->fetch("t.status = 'published' AND t.genre = ?", [$genre], 't.plays_total DESC, t.id DESC', $limit); }
    public function byArtist($artistId, $limit = 100) { return $this->fetch("t.status = 'published' AND t.artist_id = ?", [(int)$artistId], 't.plays_total DESC, t.id DESC', $limit); }
    public function byAlbum($albumId, $limit = 100) { return $this->fetch("t.status = 'published' AND t.album_id = ?", [(int)$albumId], 't.track_no ASC, t.id ASC', $limit); }

    /** Most played in the last 14 days; falls back to all-time plays. */
    public function trending($limit = 10) {
        $limit = max(1, min(50, (int)$limit));
        try {
            $s = $this->pdo->query($this->base() . " WHERE t.status = 'published'
                ORDER BY (SELECT COUNT(*) FROM music_plays p WHERE p.track_id = t.id AND p.played_at > DATE_SUB(NOW(), INTERVAL 14 DAY)) DESC,
                         t.plays_total DESC, t.id DESC LIMIT $limit");
            return $s->fetchAll();
        } catch (Throwable $e) { error_log('Music trending: ' . $e->getMessage()); return []; }
    }

    public function search($q, $limit = 40) {
        $like = '%' . addcslashes(trim((string)$q), '\\%_') . '%';
        return $this->fetch("t.status = 'published' AND (t.title LIKE ? OR a.name LIKE ? OR al.title LIKE ? OR t.genre LIKE ?)",
            [$like, $like, $like, $like], 't.plays_total DESC, t.id DESC', $limit);
    }

    public function trackBySlug($slug) {
        $r = $this->fetch("t.status = 'published' AND t.slug = ?", [$slug], 't.id DESC', 1);
        return $r ? $r[0] : null;
    }

    public function trackById($id, $includeHidden = false) {
        $r = $this->fetch(($includeHidden ? '1=1' : "t.status = 'published'") . ' AND t.id = ?', [(int)$id], 't.id DESC', 1);
        return $r ? $r[0] : null;
    }

    /** Fetch published tracks by id, keeping the order of $ids. */
    public function tracksByIds(array $ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) { return $v > 0; })));
        if (!$ids) return [];
        $ids = array_slice($ids, 0, 300);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->fetch("t.status = 'published' AND t.id IN ($in)", $ids, 't.id ASC', 300);
        $by = [];
        foreach ($rows as $r) $by[(int)$r['id']] = $r;
        $out = [];
        foreach ($ids as $id) if (isset($by[$id])) $out[] = $by[$id];
        return $out;
    }

    public function genres() {
        try {
            return $this->pdo->query("SELECT genre, COUNT(*) AS total FROM music_tracks WHERE status = 'published' AND genre IS NOT NULL AND genre <> ''
                GROUP BY genre ORDER BY total DESC, genre ASC LIMIT 30")->fetchAll();
        } catch (Throwable $e) { return []; }
    }

    /* ------------------------------------------------------- artists and albums */

    public function artistBySlug($slug) {
        $s = $this->pdo->prepare("SELECT * FROM music_artists WHERE slug = ?");
        $s->execute([$slug]);
        return $s->fetch() ?: null;
    }

    public function albumBySlug($slug) {
        $s = $this->pdo->prepare("SELECT al.*, a.name AS artist_name, a.slug AS artist_slug FROM music_albums al JOIN music_artists a ON a.id = al.artist_id WHERE al.slug = ?");
        $s->execute([$slug]);
        return $s->fetch() ?: null;
    }

    public function topArtists($limit = 12) {
        $limit = max(1, min(60, (int)$limit));
        try {
            return $this->pdo->query("SELECT a.id, a.name, a.slug, a.photo_path, COUNT(t.id) AS tracks,
                    COALESCE(SUM(t.plays_total), 0) AS plays,
                    (SELECT COALESCE(NULLIF(t2.cover_path, ''), al2.cover_path) FROM music_tracks t2 LEFT JOIN music_albums al2 ON al2.id = t2.album_id
                       WHERE t2.artist_id = a.id AND t2.status = 'published' AND COALESCE(NULLIF(t2.cover_path, ''), al2.cover_path) IS NOT NULL
                       ORDER BY t2.plays_total DESC LIMIT 1) AS cover
                FROM music_artists a JOIN music_tracks t ON t.artist_id = a.id AND t.status = 'published'
                GROUP BY a.id, a.name, a.slug, a.photo_path ORDER BY plays DESC, tracks DESC, a.name ASC LIMIT $limit")->fetchAll();
        } catch (Throwable $e) { error_log('Music topArtists: ' . $e->getMessage()); return []; }
    }

    public function newestAlbums($limit = 12, $artistId = 0) {
        $limit = max(1, min(60, (int)$limit));
        $where = $artistId ? ' AND al.artist_id = ' . (int)$artistId : '';
        try {
            return $this->pdo->query("SELECT al.id, al.title, al.slug, al.cover_path, al.release_year, a.name AS artist_name, a.slug AS artist_slug,
                    COUNT(t.id) AS tracks
                FROM music_albums al JOIN music_artists a ON a.id = al.artist_id
                JOIN music_tracks t ON t.album_id = al.id AND t.status = 'published'
                WHERE 1=1 $where GROUP BY al.id, al.title, al.slug, al.cover_path, al.release_year, a.name, a.slug
                ORDER BY al.created_at DESC LIMIT $limit")->fetchAll();
        } catch (Throwable $e) { error_log('Music newestAlbums: ' . $e->getMessage()); return []; }
    }

    public function allArtistNames() {
        try { return $this->pdo->query("SELECT name FROM music_artists ORDER BY name ASC LIMIT 500")->fetchAll(PDO::FETCH_COLUMN); }
        catch (Throwable $e) { return []; }
    }

    /** Find an artist by name (case-insensitive) or create it. Returns the artist id. */
    public function artistIdFor($name) {
        $name = trim($name);
        $s = $this->pdo->prepare("SELECT id FROM music_artists WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $s->execute([$name]);
        $id = $s->fetchColumn();
        if ($id) return (int)$id;
        $slug = $this->uniqueSlug('music_artists', self::slugify($name));
        $i = $this->pdo->prepare("INSERT INTO music_artists (name, slug) VALUES (?, ?)");
        $i->execute([$name, $slug]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Find an album for the artist by title or create it. Cover/year fill in only when missing. */
    public function albumIdFor($artistId, $title, $coverPath = null, $year = null) {
        $title = trim($title);
        if ($title === '') return null;
        $s = $this->pdo->prepare("SELECT id, cover_path, release_year FROM music_albums WHERE artist_id = ? AND LOWER(title) = LOWER(?) LIMIT 1");
        $s->execute([(int)$artistId, $title]);
        $row = $s->fetch();
        if ($row) {
            if ((!$row['cover_path'] && $coverPath) || (!$row['release_year'] && $year)) {
                $u = $this->pdo->prepare("UPDATE music_albums SET cover_path = COALESCE(cover_path, ?), release_year = COALESCE(release_year, ?) WHERE id = ?");
                $u->execute([$coverPath ?: null, $year ?: null, (int)$row['id']]);
            }
            return (int)$row['id'];
        }
        $slug = $this->uniqueSlug('music_albums', self::slugify($title));
        $i = $this->pdo->prepare("INSERT INTO music_albums (artist_id, title, slug, cover_path, release_year) VALUES (?, ?, ?, ?, ?)");
        $i->execute([(int)$artistId, $title, $slug, $coverPath ?: null, $year ?: null]);
        return (int)$this->pdo->lastInsertId();
    }

    /* ------------------------------------------------------------- admin CRUD */

    public function artistProfile($artistId) {
        $s = $this->pdo->prepare("SELECT * FROM music_artists WHERE id = ?");
        $s->execute([(int)$artistId]);
        return $s->fetch() ?: null;
    }

    public function saveArtistProfile($artistId, $bio, $photoPath) {
        $s = $this->pdo->prepare("UPDATE music_artists SET bio = ?, photo_path = COALESCE(?, photo_path) WHERE id = ?");
        return $s->execute([$bio !== '' ? $bio : null, $photoPath ?: null, (int)$artistId]);
    }

    public function allForAdmin() {
        try {
            return $this->pdo->query($this->base() . " ORDER BY t.created_at DESC, t.id DESC LIMIT 1000")->fetchAll();
        } catch (Throwable $e) { error_log('Music admin list: ' . $e->getMessage()); return []; }
    }

    public function saveTrack($id, array $d) {
        $slugBase = self::slugify($d['artist_name'] . ' ' . $d['title']);
        $slug = $this->uniqueSlug('music_tracks', $slugBase, (int)$id);
        try {
            if ($id) {
                $s = $this->pdo->prepare("UPDATE music_tracks SET title=?, slug=?, artist_id=?, album_id=?, genre=?, track_no=?, duration_seconds=?, audio_path=?, video_path=?,
                    cover_path=?, lyrics=?, is_explicit=?, is_featured=?, play_access=?, download_access=?, status=? WHERE id=?");
                $s->execute([$d['title'], $slug, $d['artist_id'], $d['album_id'], $d['genre'] ?: null, (int)$d['track_no'], (int)$d['duration_seconds'], $d['audio_path'],
                    $d['video_path'] ?: null, $d['cover_path'] ?: null, $d['lyrics'] !== '' ? $d['lyrics'] : null, (int)$d['is_explicit'], (int)$d['is_featured'],
                    $d['play_access'], $d['download_access'], $d['status'], (int)$id]);
                return (int)$id;
            }
            $s = $this->pdo->prepare("INSERT INTO music_tracks (title, slug, artist_id, album_id, genre, track_no, duration_seconds, audio_path, video_path, cover_path, lyrics,
                is_explicit, is_featured, play_access, download_access, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $s->execute([$d['title'], $slug, $d['artist_id'], $d['album_id'], $d['genre'] ?: null, (int)$d['track_no'], (int)$d['duration_seconds'], $d['audio_path'],
                $d['video_path'] ?: null, $d['cover_path'] ?: null, $d['lyrics'] !== '' ? $d['lyrics'] : null, (int)$d['is_explicit'], (int)$d['is_featured'],
                $d['play_access'], $d['download_access'], $d['status']]);
            return (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('Music saveTrack: ' . $e->getMessage());
            return 0;
        }
    }

    public function deleteTrack($id) {
        try {
            foreach (["DELETE FROM music_likes WHERE track_id = ?", "DELETE FROM music_plays WHERE track_id = ?",
                      "DELETE FROM music_playlist_tracks WHERE track_id = ?", "DELETE FROM music_tracks WHERE id = ?"] as $q) {
                $s = $this->pdo->prepare($q); $s->execute([(int)$id]);
            }
            return true;
        } catch (Throwable $e) { error_log('Music deleteTrack: ' . $e->getMessage()); return false; }
    }

    /* ------------------------------------------------------------ likes & plays */

    public function likedIds($userId, array $trackIds = []) {
        if (!$userId) return [];
        try {
            if ($trackIds) {
                $in = implode(',', array_fill(0, count($trackIds), '?'));
                $s = $this->pdo->prepare("SELECT track_id FROM music_likes WHERE user_id = ? AND track_id IN ($in)");
                $s->execute(array_merge([(int)$userId], array_map('intval', $trackIds)));
            } else {
                $s = $this->pdo->prepare("SELECT track_id FROM music_likes WHERE user_id = ?");
                $s->execute([(int)$userId]);
            }
            return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) { return []; }
    }

    /** True when the track is now liked, false when un-liked. */
    public function toggleLike($userId, $trackId) {
        $c = $this->pdo->prepare("SELECT 1 FROM music_likes WHERE user_id = ? AND track_id = ?");
        $c->execute([(int)$userId, (int)$trackId]);
        if ($c->fetchColumn()) {
            $d = $this->pdo->prepare("DELETE FROM music_likes WHERE user_id = ? AND track_id = ?");
            $d->execute([(int)$userId, (int)$trackId]);
            return false;
        }
        $i = $this->pdo->prepare("INSERT IGNORE INTO music_likes (user_id, track_id) VALUES (?, ?)");
        $i->execute([(int)$userId, (int)$trackId]);
        return true;
    }

    public function likedTracks($userId, $limit = 300) {
        return $this->fetch("t.status = 'published' AND t.id IN (SELECT track_id FROM music_likes WHERE user_id = ?)", [(int)$userId],
            "(SELECT MAX(l.created_at) FROM music_likes l WHERE l.track_id = t.id AND l.user_id = " . (int)$userId . ") DESC", $limit);
    }

    public function recordPlay($trackId, $userId) {
        try {
            $s = $this->pdo->prepare("INSERT INTO music_plays (track_id, user_id) VALUES (?, ?)");
            $s->execute([(int)$trackId, $userId ? (int)$userId : null]);
            $u = $this->pdo->prepare("UPDATE music_tracks SET plays_total = plays_total + 1 WHERE id = ?");
            $u->execute([(int)$trackId]);
            return true;
        } catch (Throwable $e) { error_log('Music recordPlay: ' . $e->getMessage()); return false; }
    }

    public function recordDownload($trackId) {
        try { $u = $this->pdo->prepare("UPDATE music_tracks SET downloads_total = downloads_total + 1 WHERE id = ?"); $u->execute([(int)$trackId]); } catch (Throwable $e) {}
    }

    /** Recently played, newest first, each track once. */
    public function history($userId, $limit = 30) {
        $limit = max(1, min(100, (int)$limit));
        try {
            $s = $this->pdo->prepare("SELECT track_id, MAX(played_at) AS last_played FROM music_plays WHERE user_id = ? GROUP BY track_id ORDER BY last_played DESC LIMIT $limit");
            $s->execute([(int)$userId]);
            return $this->tracksByIds(array_map(function ($r) { return (int)$r['track_id']; }, $s->fetchAll()));
        } catch (Throwable $e) { return []; }
    }

    /* ---------------------------------------------------------------- playlists */

    public function playlists($userId) {
        try {
            $s = $this->pdo->prepare("SELECT p.id, p.name, (SELECT COUNT(*) FROM music_playlist_tracks x WHERE x.playlist_id = p.id) AS tracks
                FROM music_playlists p WHERE p.user_id = ? ORDER BY p.updated_at DESC, p.id DESC LIMIT 100");
            $s->execute([(int)$userId]);
            return $s->fetchAll();
        } catch (Throwable $e) { return []; }
    }

    public function playlist($id, $userId) {
        $s = $this->pdo->prepare("SELECT * FROM music_playlists WHERE id = ? AND user_id = ?");
        $s->execute([(int)$id, (int)$userId]);
        return $s->fetch() ?: null;
    }

    public function playlistTracks($playlistId) {
        try {
            $s = $this->pdo->prepare("SELECT track_id FROM music_playlist_tracks WHERE playlist_id = ? ORDER BY position ASC, added_at ASC");
            $s->execute([(int)$playlistId]);
            return $this->tracksByIds(array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN)));
        } catch (Throwable $e) { return []; }
    }

    public function createPlaylist($userId, $name) {
        $name = function_exists('mb_substr') ? mb_substr(trim($name), 0, 120) : substr(trim($name), 0, 120);
        if ($name === '') return 0;
        $n = $this->pdo->prepare("SELECT COUNT(*) FROM music_playlists WHERE user_id = ?");
        $n->execute([(int)$userId]);
        if ((int)$n->fetchColumn() >= 100) return 0;
        $s = $this->pdo->prepare("INSERT INTO music_playlists (user_id, name) VALUES (?, ?)");
        $s->execute([(int)$userId, $name]);
        return (int)$this->pdo->lastInsertId();
    }

    public function renamePlaylist($id, $userId, $name) {
        $name = function_exists('mb_substr') ? mb_substr(trim($name), 0, 120) : substr(trim($name), 0, 120);
        if ($name === '') return false;
        $s = $this->pdo->prepare("UPDATE music_playlists SET name = ? WHERE id = ? AND user_id = ?");
        $s->execute([$name, (int)$id, (int)$userId]);
        return $s->rowCount() >= 0;
    }

    public function deletePlaylist($id, $userId) {
        if (!$this->playlist($id, $userId)) return false;
        $a = $this->pdo->prepare("DELETE FROM music_playlist_tracks WHERE playlist_id = ?"); $a->execute([(int)$id]);
        $b = $this->pdo->prepare("DELETE FROM music_playlists WHERE id = ? AND user_id = ?"); $b->execute([(int)$id, (int)$userId]);
        return true;
    }

    public function addToPlaylist($playlistId, $userId, $trackId) {
        if (!$this->playlist($playlistId, $userId)) return false;
        if (!$this->trackById($trackId)) return false;
        $m = $this->pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM music_playlist_tracks WHERE playlist_id = ?");
        $m->execute([(int)$playlistId]);
        $i = $this->pdo->prepare("INSERT IGNORE INTO music_playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)");
        $i->execute([(int)$playlistId, (int)$trackId, (int)$m->fetchColumn()]);
        $t = $this->pdo->prepare("UPDATE music_playlists SET updated_at = NOW() WHERE id = ?"); $t->execute([(int)$playlistId]);
        return true;
    }

    public function removeFromPlaylist($playlistId, $userId, $trackId) {
        if (!$this->playlist($playlistId, $userId)) return false;
        $d = $this->pdo->prepare("DELETE FROM music_playlist_tracks WHERE playlist_id = ? AND track_id = ?");
        $d->execute([(int)$playlistId, (int)$trackId]);
        return true;
    }

    /* ------------------------------------------------------------- JSON export */

    /**
     * Shape a track row for the player. Never includes server file paths.
     * @param array $liked list of liked track ids for this viewer
     */
    public function export(array $t, array $viewer, array $liked = []) {
        $canPlay = self::allowed($t['play_access'], $viewer);
        $downloadsOn = SiteSetting::downloadsEnabled();
        $canDl = $downloadsOn && self::allowed($t['download_access'], $viewer);
        return [
            'id' => (int)$t['id'],
            'slug' => $t['slug'],
            'title' => $t['title'],
            'artist' => $t['artist_name'],
            'artist_slug' => $t['artist_slug'],
            'album' => $t['album_title'] ?? '',
            'album_slug' => $t['album_slug'] ?? '',
            'cover' => self::url($t['cover'] ?? ''),
            'duration' => (int)$t['duration_seconds'],
            'src' => '/TMZ%20PODCAST/music-stream.php?id=' . (int)$t['id'],
            'video' => !empty($t['video_path']) ? '/TMZ%20PODCAST/music-stream.php?id=' . (int)$t['id'] . '&type=video' : '',
            'explicit' => !empty($t['is_explicit']),
            'liked' => in_array((int)$t['id'], $liked, true),
            'likes' => (int)($t['likes_total'] ?? 0),
            'plays' => (int)$t['plays_total'],
            'locked' => !$canPlay,
            'lock_hint' => $canPlay ? '' : ($t['play_access'] === 'subscribers' ? 'This track is included with All Access.' : 'Sign in to listen to this track.'),
            'can_download' => $canDl,
            'downloads_enabled' => $downloadsOn,
            'download_hint' => $canDl ? '' : ($downloadsOn ? self::accessHint($t['download_access']) : 'Downloads are currently disabled.'),
            'download' => '/TMZ%20PODCAST/music-download.php?id=' . (int)$t['id'],
            'download_video' => !empty($t['video_path']) ? '/TMZ%20PODCAST/music-download.php?id=' . (int)$t['id'] . '&type=video' : '',
            'url' => '/TMZ%20PODCAST/music.php?track=' . rawurlencode($t['slug']),
        ];
    }
}
?>
