<?php
/**
 * CLI backup runner — php tools/backup.php [--checksum] [--keep=N]
 * --checksum  sha256 every media file (slow on large libraries; thorough)
 * --keep=N    delete bundles older than the N newest (default: keep all)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/BackupManager.php';

$checksum = in_array('--checksum', $argv ?? [], true);
$keep = null;
foreach ($argv ?? [] as $a) if (preg_match('/^--keep=(\d+)$/', $a, $m)) $keep = (int)$m[1];

$t0 = microtime(true);
$r = (new BackupManager())->create(['checksum' => $checksum]);
if (!$r['ok']) { fwrite(STDERR, "BACKUP FAILED: " . ($r['error'] ?? '?') . "\n"); exit(1); }

$s = $r['stats'];
printf("Backup OK: %s\n  tables=%d rows=%d media_files=%d (%.1f MB) missing=%d time=%.1fs\n  dir=%s\n",
    $r['name'], $s['tables'], $s['rows'], $s['media_files'], $s['media_bytes'] / 1048576, $s['missing_files'],
    microtime(true) - $t0, $r['dir']);
if ($s['missing_files'] > 0) echo "  WARNING: {$s['missing_files']} media_assets rows point at missing files!\n";

if ($keep !== null) {
    $all = BackupManager::list();
    foreach (array_slice($all, $keep) as $old) {
        // Only remove bundle dirs, never the originals they describe.
        if (is_dir($old['path'])) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($old['path'], FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
            @rmdir($old['path']);
            echo "  pruned old bundle: {$old['name']}\n";
        }
    }
}
echo "REMINDER: copy {$r['dir']} offsite — same-disk backups do not protect against disk failure.\n";
