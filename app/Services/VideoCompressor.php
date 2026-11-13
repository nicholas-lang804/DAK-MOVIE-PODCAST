<?php
/**
 * VideoCompressor — optional background compression for uploaded videos.
 *
 * Re-encodes large uploads to H.264 720p (CRF 28, AAC 128k, faststart) so movies
 * and music videos take far less disk space. Runs asynchronously via
 * tools/compress-video.php so uploads never block on encoding time.
 *
 * Requires FFmpeg: set `ffmpeg_path` in site settings, put ffmpeg.exe/ffmpeg on
 * PATH, or drop it in <project>/bin/ffmpeg(.exe). Without FFmpeg everything is a no-op.
 */
class VideoCompressor {
    private static $ready = null;

    public static function ffmpegPath() {
        $custom = trim((string)(new SiteSetting())->get('ffmpeg_path'));
        if ($custom !== '' && is_file($custom)) return $custom;
        foreach ([
            __DIR__ . '/../../bin/ffmpeg.exe',
            __DIR__ . '/../../bin/ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg',
        ] as $candidate) {
            if (is_file($candidate)) return $candidate;
        }
        // Fall back to PATH lookup (exec('where'/'which') is cheap enough per upload).
        if (!function_exists('exec')) return 'ffmpeg'; // shell disabled — treat as unavailable
        $cmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where ffmpeg 2>NUL' : 'which ffmpeg 2>/dev/null';
        $out = [];
        @exec($cmd, $out);
        return !empty($out[0]) ? trim($out[0]) : 'ffmpeg';
    }

    public static function available() {
        if (self::$ready !== null) return self::$ready;
        if (!function_exists('exec')) { self::$ready = false; return false; } // shared hosts disable exec
        $enabled = (new SiteSetting())->get('video_compression_enabled');
        if ($enabled === '0') { self::$ready = false; return false; }
        $ffmpeg = self::ffmpegPath();
        // 'ffmpeg' bare means PATH lookup failed too — verify once.
        if ($ffmpeg === 'ffmpeg') {
            $out = [];
            @exec('ffmpeg -version 2>&1', $out);
            self::$ready = isset($out[0]) && stripos($out[0], 'ffmpeg version') === 0;
        } else {
            self::$ready = true;
        }
        return self::$ready;
    }

    /**
     * DEPRECATED destructive path — kept only as a fallback.
     * Now delegates to the non-destructive RenditionBuilder (originals are never touched).
     */
    public static function queue($absolutePath) {
        require_once __DIR__ . '/RenditionBuilder.php';
        $r = RenditionBuilder::enqueue(null, $absolutePath);
        return !empty($r['ok']);
    }

    /** True while a compression job is running for this file. */
    public static function isCompressing($absolutePath) {
        return is_file($absolutePath . '.compressing');
    }
}
