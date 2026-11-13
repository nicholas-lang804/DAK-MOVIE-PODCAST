<?php
/**
 * BackupManager — database dump + media manifest + integrity checksums.
 *
 * Produces timestamped bundles under storage/backups/<name>/:
 *   database.sql      full mysqldump (or PHP fallback dump)
 *   media-manifest.json  every media_assets row + file size/mtime/sha256(optional)
 *   files-manifest.txt   every file under storage/ (path, size, mtime)
 *   bundle-checksums.txt sha256 of the bundle's own files (verification)
 *
 * Usage:
 *   CLI:  php tools/backup.php [--checksum] [--keep=N]
 *   Web:  AdminController action run_backup -> same code path.
 *
 * IMPORTANT: a backup on the same disk is NOT a backup. Copy the bundle
 * offsite (second machine, Oracle volume, external drive) — see DEPLOYMENT.md.
 */
class BackupManager {
    private $pdo;
    private $root;

    public function __construct() {
        $this->pdo = getDB();
        $this->root = dirname(__DIR__, 2);
    }

    public static function backupRoot() {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    /** Create a backup bundle. Returns ['ok'=>bool,'dir'=>path,'error'=>?,'stats'=>[]]. */
    public function create($opts = []) {
        $withFileChecksums = !empty($opts['checksum']); // sha256 every media file (slow on large libraries)
        $name = 'backup_' . date('Ymd_His');
        $dir = self::backupRoot() . DIRECTORY_SEPARATOR . $name;
        if (!@mkdir($dir, 0755, true)) return ['ok' => false, 'error' => 'Cannot create backup dir'];

        $stats = ['tables' => 0, 'rows' => 0, 'media_files' => 0, 'media_bytes' => 0, 'missing_files' => 0];

        // 1) Database dump — mysqldump preferred, PHP fallback.
        $sqlFile = $dir . DIRECTORY_SEPARATOR . 'database.sql';
        if (!$this->dumpDatabase($sqlFile, $stats)) {
            return ['ok' => false, 'error' => 'Database dump failed', 'dir' => $dir];
        }

        // 2) Media manifest — DB rows joined with on-disk verification.
        $manifest = $this->mediaManifest($withFileChecksums, $stats);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'media-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 3) Full storage file listing (catches files not in media_assets).
        $filesList = $this->storageFileList();
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'files-manifest.txt', implode("\n", $filesList));

        // 4) Checksums of the bundle itself — proves the backup is readable later.
        $sums = '';
        foreach (['database.sql', 'media-manifest.json', 'files-manifest.txt'] as $f) {
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($p)) $sums .= hash_file('sha256', $p) . '  ' . $f . "\n";
        }
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'bundle-checksums.txt', $sums);

        // 5) Meta
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'backup-meta.json', json_encode([
            'created_at' => date('c'),
            'app' => 'DAK Film Podcast Media',
            'db' => DB_NAME,
            'stats' => $stats,
            'with_file_checksums' => $withFileChecksums,
        ], JSON_PRETTY_PRINT));

        // Record last-backup timestamp for the admin staleness warning.
        try {
            $this->pdo->exec("INSERT INTO site_settings (setting_key, setting_value) VALUES ('last_backup_at', '" . date('Y-m-d H:i:s') . "')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        } catch (Throwable $e) { /* non-fatal */ }

        return ['ok' => true, 'dir' => $dir, 'name' => $name, 'stats' => $stats];
    }

    /** mysqldump with credentials; falls back to row-by-row PHP dump. */
    private function dumpDatabase($outFile, &$stats) {
        $mysqldump = $this->findMysqldump();
        if ($mysqldump) {
            $cmd = escapeshellarg($mysqldump)
                . ' --host=' . escapeshellarg(DB_HOST)
                . ' --user=' . escapeshellarg(DB_USER)
                . (DB_PASS !== '' ? ' --password=' . escapeshellarg(DB_PASS) : '')
                . ' --single-transaction --routines --triggers --default-character-set=utf8mb4'
                . ' ' . escapeshellarg(DB_NAME);
            $fp = @fopen($outFile, 'wb');
            if ($fp) {
                $proc = proc_open($cmd, [1 => $fp, 2 => ['file', $outFile . '.err', 'a']], $pipes);
                if (is_resource($proc)) {
                    $code = proc_close($proc);
                    if ($code === 0 && is_file($outFile) && filesize($outFile) > 100) {
                        @unlink($outFile . '.err');
                        $stats['tables'] = count($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
                        return true;
                    }
                }
            }
        }
        return $this->dumpDatabasePhp($outFile, $stats);
    }

    private function dumpDatabasePhp($outFile, &$stats) {
        $fh = @fopen($outFile, 'wb');
        if (!$fh) return false;
        fwrite($fh, "-- DAK Film Podcast Media PHP fallback dump\n-- " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $this->pdo->query("SHOW CREATE TABLE `" . str_replace('`', '', $table) . "`")->fetch(PDO::FETCH_ASSOC);
            fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n");
            $rows = $this->pdo->query("SELECT * FROM `{$table}`");
            $count = 0;
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $this->pdo->quote((string)$v), array_values($row));
                fwrite($fh, "INSERT INTO `{$table}` VALUES (" . implode(',', $vals) . ");\n");
                $count++;
            }
            $stats['rows'] += $count;
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        $stats['tables'] = count($tables);
        return is_file($outFile) && filesize($outFile) > 100;
    }

    private function mediaManifest($withChecksums, &$stats) {
        $out = ['generated_at' => date('c'), 'assets' => []];
        $rows = $this->pdo->query('SELECT id, original_filename, stored_filename, storage_path, media_type, mime_type, file_size, created_at FROM media_assets')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $abs = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $r['storage_path']);
            $entry = [
                'id' => (int)$r['id'], 'path' => $r['storage_path'], 'type' => $r['media_type'],
                'mime' => $r['mime_type'], 'db_size' => (int)$r['file_size'],
                'exists' => is_file($abs),
            ];
            if (is_file($abs)) {
                $entry['disk_size'] = filesize($abs);
                $entry['mtime'] = date('c', filemtime($abs));
                $stats['media_files']++;
                $stats['media_bytes'] += filesize($abs);
                if ($withChecksums) $entry['sha256'] = hash_file('sha256', $abs);
            } else {
                $stats['missing_files']++;
            }
            $out['assets'][] = $entry;
        }
        // Renditions too — they're regenerable but listing them aids restore verification.
        try {
            $out['renditions'] = $this->pdo->query('SELECT media_id, quality, storage_path, file_size, status FROM media_renditions')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $out['renditions'] = []; }
        return $out;
    }

    private function storageFileList() {
        $base = $this->root . DIRECTORY_SEPARATOR . 'storage';
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $rel = str_replace($this->root . DIRECTORY_SEPARATOR, '', $f->getPathname());
                $out[] = str_replace('\\', '/', $rel) . "\t" . $f->getSize() . "\t" . date('c', $f->getMTime());
            }
        }
        sort($out);
        return $out;
    }

    private function findMysqldump() {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/c/xampp/mysql/bin/mysqldump.exe',
            'mysqldump',
        ];
        foreach ($candidates as $c) {
            if ($c === 'mysqldump') return trim((string)@shell_exec('where mysqldump 2>NUL') ?: trim((string)@shell_exec('which mysqldump 2>/dev/null')));
            if (is_file($c) || trim((string)@shell_exec('where ' . escapeshellarg($c) . ' 2>NUL'))) return $c;
        }
        return null;
    }

    /** List existing bundles, newest first. */
    public static function list() {
        $dir = self::backupRoot();
        $out = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . 'backup_*', GLOB_ONLYDIR) ?: [] as $d) {
            $meta = @json_decode((string)@file_get_contents($d . DIRECTORY_SEPARATOR . 'backup-meta.json'), true);
            $out[] = [
                'name' => basename($d),
                'path' => $d,
                'created' => $meta['created_at'] ?? date('c', filemtime($d)),
                'stats' => $meta['stats'] ?? [],
                'files' => array_map('basename', glob($d . DIRECTORY_SEPARATOR . '*') ?: []),
            ];
        }
        usort($out, fn($a, $b) => strcmp($b['created'], $a['created']));
        return $out;
    }

    public static function lastBackupAt() {
        try {
            $v = getDB()->query("SELECT setting_value FROM site_settings WHERE setting_key='last_backup_at' LIMIT 1")->fetchColumn();
            return $v ?: null;
        } catch (Throwable $e) { return null; }
    }
}
