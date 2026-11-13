<?php
/**
 * Background worker: compress one video file to H.264 720p, then atomically replace the original.
 * Invoked by VideoCompressor::queue(). Safe to run standalone:
 *   php tools/compress-video.php "C:\path\to\video.mp4"
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
// LEGACY destructive worker — retained for rollback reference only.
// It overwrites the original file. Blocked unless explicitly enabled:
if (getenv('TMZ_ALLOW_DESTRUCTIVE_COMPRESS') !== '1') {
    fwrite(STDERR, "Blocked: this legacy worker replaces originals. Use tools/rendition-worker.php instead. Set TMZ_ALLOW_DESTRUCTIVE_COMPRESS=1 to override.\n");
    exit(9);
}
$input = $argv[1] ?? '';
if ($input === '' || !is_file($input)) exit(1);

$root = dirname(__DIR__);
require_once $root . '/config/database.php';
require_once $root . '/app/Models/SiteSetting.php';
require_once $root . '/app/Services/VideoCompressor.php';

$marker = $input . '.compressing';
$logDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$log = $logDir . DIRECTORY_SEPARATOR . 'compress.log';
$tmp = $input . '.tmp.mp4';
$bak = $input . '.orig';

function clog($log, $msg) { file_put_contents($log, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND); }

try {
    $ffmpeg = VideoCompressor::ffmpegPath();
    // Probe duration/size for the log.
    $sizeBefore = filesize($input);
    clog($log, "START {$input} (" . round($sizeBefore / 1048576, 1) . " MB)");

    // 720p max, keep aspect, CRF 28 ≈ good quality / ~70-80% smaller, AAC audio, web-optimized.
    $cmd = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -y -i ' . escapeshellarg($input)
        . ' -vf "scale=\'min(1280,iw)\':-2" -c:v libx264 -preset veryfast -crf 28 -c:a aac -b:a 128k -movflags +faststart '
        . escapeshellarg($tmp) . ' 2>>' . escapeshellarg($log);
    exec($cmd, $o, $code);

    if ($code !== 0 || !is_file($tmp) || filesize($tmp) < 1024) {
        @unlink($tmp);
        clog($log, "FAIL {$input} ffmpeg exit={$code}");
        @unlink($marker);
        exit(2);
    }

    // Replace original only if the result is actually smaller (never grow files).
    $sizeAfter = filesize($tmp);
    if ($sizeAfter < $sizeBefore) {
        @rename($input, $bak);
        if (!@rename($tmp, $input)) { @rename($bak, $input); @unlink($tmp); clog($log, "FAIL rename {$input}"); @unlink($marker); exit(3); }
        @unlink($bak);
        clog($log, "OK {$input} " . round($sizeBefore / 1048576, 1) . "MB -> " . round($sizeAfter / 1048576, 1) . "MB");
    } else {
        @unlink($tmp);
        clog($log, "SKIP {$input} compressed size not smaller");
    }
} catch (Throwable $e) {
    clog($log, 'ERROR ' . $e->getMessage());
} finally {
    @unlink($marker);
}
