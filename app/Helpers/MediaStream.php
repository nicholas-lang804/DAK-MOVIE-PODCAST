<?php
/**
 * Send a file with HTTP Range support (seeking in players, resumable downloads).
 * Streams in small chunks so large audio/video files never load into memory.
 */

if (!function_exists('tmz_send_file')) {
    /**
     * @param string      $path         Absolute path to an existing file
     * @param string      $mime         Content-Type
     * @param string|null $downloadName When set, forces a download with this file name
     * @return bool       True when the whole requested range was sent from byte 0 (use to count downloads)
     */
    function tmz_send_file($path, $mime, $downloadName = null) {
        $size = filesize($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;

        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {                       // suffix range: last N bytes
                $start = max(0, $size - (int)$m[2]);
            } else {
                $start = (int)$m[1];
                if ($m[2] !== '') $end = min($end, (int)$m[2]);
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return false;
            }
            $status = 206;
        }

        $length = $end - $start + 1;
        http_response_code($status);
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $length);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        if ($status === 206) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        if ($downloadName !== null) {
            $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $downloadName);
            header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        } else {
            header('Content-Disposition: inline');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') return $start === 0;

        @set_time_limit(0);
        while (ob_get_level() > 0) @ob_end_clean();
        $fh = fopen($path, 'rb');
        if (!$fh) return false;
        fseek($fh, $start);
        $left = $length;
        while ($left > 0 && !feof($fh) && !connection_aborted()) {
            $chunk = fread($fh, min(65536, $left));
            if ($chunk === false) break;
            echo $chunk;
            $left -= strlen($chunk);
            flush();
        }
        fclose($fh);
        return $start === 0;
    }
}
