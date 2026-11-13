<?php
/**
 * MediaStorage — storage driver abstraction (local | remote).
 *
 * Local driver (default): files live under <project>/storage/, addressed by keys
 * like 'uploads/movie.mp4' or 'music/video/song.mp4'. Fully functional today.
 *
 * Remote driver: files live on the Oracle media server, addressed by object keys.
 * Browser uploads and playback go through short-lived tokens / signed URLs —
 * never permanent credentials. Requires media_remote_base + media_remote_token
 * in site settings (see Admin → Settings → Media storage).
 */
require_once __DIR__ . '/../Models/SiteSetting.php';
require_once __DIR__ . '/RemoteMediaClient.php';

class MediaStorage {
    private static $instance = null;
    private $settings;
    private $driver;
    private $localRoot;

    private function __construct() {
        $this->settings = (new SiteSetting())->getAllKeyed();
        $this->driver = ($this->settings['media_driver'] ?? 'local') === 'remote' ? 'remote' : 'local';
        $this->localRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
    }

    public static function get() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function driver() { return $this->driver; }

    public static function localRoot() {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
    }

    /* ---------------- configuration ---------------- */

    public function remoteConfigured() {
        return $this->driver === 'remote'
            && trim((string)($this->settings['media_remote_base'] ?? '')) !== ''
            && trim((string)($this->settings['media_remote_token'] ?? '')) !== '';
    }

    /* ---------------- write path ---------------- */

    /** Store a local file under a storage key. Returns ['ok'=>bool,'key'=>,'error'=>]. */
    public function putFile($localPath, $key, $isOriginal = false) {
        $key = $this->normalizeKey($key);
        if ($this->driver === 'remote') {
            if (!$this->remoteConfigured()) return ['ok' => false, 'key' => $key, 'error' => 'Remote media storage is not configured.'];
            return (new RemoteMediaClient($this->settings))->putFile($localPath, $key, $isOriginal);
        }
        $dest = $this->localRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['ok' => false, 'key' => $key, 'error' => 'Could not create directory.'];
        if (!@copy($localPath, $dest)) return ['ok' => false, 'key' => $key, 'error' => 'Could not write file.'];
        return ['ok' => true, 'key' => $key];
    }

    /** Register a file already on disk (local driver no-op bookkeeping). */
    public function register($key, $size = 0, $sha = null, $mediaId = null, $isOriginal = false) {
        try {
            require_once __DIR__ . '/../Models/UploadJob.php';
            $pdo = getDB();
            $pdo->prepare("INSERT INTO media_objects (media_id, driver, object_key, sha256, size_bytes, is_original) VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE size_bytes=VALUES(size_bytes), sha256=VALUES(sha256), is_original=VALUES(is_original)")
                ->execute([$mediaId, $this->driver, $this->normalizeKey($key), $sha, (int)$size, $isOriginal ? 1 : 0]);
        } catch (Throwable $e) { error_log('MediaStorage::register: ' . $e->getMessage()); }
        return true;
    }

    /* ---------------- read path ---------------- */

    /** Public/stream URL for a storage key. Video types should go through media-play.php instead. */
    public function url($key) {
        $key = $this->normalizeKey($key);
        if ($this->driver === 'remote') {
            if (!$this->remoteConfigured()) return '';
            return (new RemoteMediaClient($this->settings))->signedUrl($key);
        }
        // Local: web path relative to app base (same convention as mediaUrl()).
        return '/storage/' . str_replace('%2F', '/', rawurlencode($key));
    }

    /** Resolve a key to a local filesystem path (local driver only). */
    public function localPath($key) {
        if ($this->driver !== 'local') return null;
        $path = $this->localRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->normalizeKey($key));
        $real = realpath($path);
        $root = realpath($this->localRoot);
        // Jail: never escape storage/.
        if ($real === false || $root === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) return null;
        return $real;
    }

    public function exists($key) {
        if ($this->driver === 'remote') {
            if (!$this->remoteConfigured()) return false;
            return (new RemoteMediaClient($this->settings))->exists($key);
        }
        return is_file($this->localRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->normalizeKey($key)));
    }

    public function delete($key) {
        if ($this->driver === 'remote') {
            if (!$this->remoteConfigured()) return false;
            return (new RemoteMediaClient($this->settings))->delete($key);
        }
        $path = $this->localRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->normalizeKey($key));
        return is_file($path) ? @unlink($path) : true; // missing file = already gone
    }

    /* ---------------- integrity / stats ---------------- */

    public function checksum($keyOrPath, $algo = 'sha256') {
        if ($this->driver === 'local' && is_file($keyOrPath)) {
            return hash_file($algo, $keyOrPath);
        }
        $path = $this->localPath($keyOrPath);
        return $path ? hash_file($algo, $path) : null;
    }

    /** Total bytes stored under storage/ (local) — recursive, cached per call. */
    public function usageBytes() {
        if ($this->driver === 'remote') {
            if (!$this->remoteConfigured()) return 0;
            return (int)(new RemoteMediaClient($this->settings))->usageBytes();
        }
        $total = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->localRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->isFile()) $total += $f->getSize(); }
        return $total;
    }

    public function usageHuman() {
        $b = $this->usageBytes();
        foreach (['EB','PB','TB','GB','MB','KB','B'] as $i => $u) {
            if ($b >= 1024 ** (6 - $i) || $i === 6) return round($b / (1024 ** (6 - $i)), 2) . ' ' . $u;
        }
        return $b . ' B';
    }

    private function normalizeKey($key) {
        $key = str_replace('\\', '/', (string)$key);
        $key = ltrim(preg_replace('#/+#', '/', $key), '/');
        // Strip a leading 'storage/' if callers pass storage-relative paths.
        if (strpos($key, 'storage/') === 0) $key = substr($key, 8);
        // Prevent traversal.
        $key = implode('/', array_filter(explode('/', $key), function ($p) { return $p !== '..' && $p !== '.' && $p !== ''; }));
        return $key;
    }
}
