<?php
/**
 * RenditionBuilder — non-destructive video rendition pipeline.
 *
 * Guarantees:
 *  - Originals are NEVER modified, moved, renamed or deleted.
 *  - The 'original' rendition row points at the untouched upload.
 *  - 720p H.264 output is a NEW file under storage/renditions/<id>/.
 *  - Failed encodes leave the original playable; jobs retry via upload_jobs.
 *
 * Phase 2 scope: 720p only. The media_renditions table already supports
 * 1080p/480p/hls rows — future phases just add encodes, no schema change.
 */
require_once __DIR__ . '/../Models/UploadJob.php';
require_once __DIR__ . '/VideoCompressor.php'; // reuses ffmpegPath()

class RenditionBuilder {
    public const QUALITY = '720p';
    private static $ffprobe = null;

    /* ---------------- enqueue ---------------- */

    /**
     * Register the original + queue a 720p rendition job.
     * @param int|null $mediaId  media_assets id, or null for path-only files (e.g. music videos)
     * @param string   $absPath  absolute path of the uploaded original
     * @return array{ok:bool,job_id?:int,message?:string}
     */
    public static function enqueue($mediaId, $absPath) {
        try {
            if (!is_file($absPath) || !is_readable($absPath)) {
                return ['ok' => false, 'message' => 'Source file missing.'];
            }
            if (!VideoCompressor::available()) {
                return ['ok' => false, 'message' => 'FFmpeg is not installed — rendition skipped, original untouched.'];
            }
            $mediaId = $mediaId ? (int)$mediaId : null;
            $jobs = new UploadJob();

            // Register the original (additive row; never touches the file).
            $sha = hash_file('sha256', $absPath);
            $size = filesize($absPath);
            self::recordOriginal($mediaId, $absPath, $size, $sha);

            // Dedupe: skip if a rendition already exists or an active job covers this source.
            if ($mediaId && ($r = $jobs->rendition($mediaId, self::QUALITY, 'local')) && $r['status'] === 'ready') {
                return ['ok' => true, 'message' => '720p rendition already exists.'];
            }
            if (!$mediaId && self::pathRenditionReady($absPath)) {
                return ['ok' => true, 'message' => '720p rendition already exists.'];
            }
            $dup = self::findActiveJob($absPath, $mediaId);
            if ($dup) return ['ok' => true, 'job_id' => (int)$dup['id'], 'message' => 'Rendition job already queued/running.'];

            $jobId = $jobs->create('rendition', [
                'src' => $absPath,
                'media_id' => $mediaId,
                'quality' => self::QUALITY,
                'driver' => 'local',
            ], $mediaId, $size);
            self::spawnWorker($jobId);
            return ['ok' => true, 'job_id' => $jobId];
        } catch (Throwable $e) {
            error_log('RenditionBuilder::enqueue: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not queue rendition.'];
        }
    }

    /**
     * Queue an HLS package (VOD .m3u8 + .ts segments) remuxed from an existing
     * rendition — stream copy, no re-encode, so it is fast and cheap.
     * Only for media_id-keyed assets (movies/episodes/library videos).
     */
    public static function enqueueHls($mediaId, $srcPath) {
        try {
            $mediaId = (int)$mediaId;
            if ($mediaId <= 0 || !is_file($srcPath)) return ['ok' => false, 'message' => 'HLS needs a media id and existing rendition file.'];
            if (!VideoCompressor::available()) return ['ok' => false, 'message' => 'FFmpeg unavailable.'];
            $jobs = new UploadJob();
            $existing = $jobs->rendition($mediaId, 'hls', 'local');
            if ($existing && $existing['status'] === 'ready') return ['ok' => true, 'message' => 'HLS already exists.'];
            $dup = self::findActiveJob($srcPath, $mediaId);
            if ($dup) return ['ok' => true, 'job_id' => (int)$dup['id'], 'message' => 'Job already queued/running.'];
            $jobId = $jobs->create('rendition', [
                'src' => $srcPath,
                'media_id' => $mediaId,
                'quality' => 'hls',
                'driver' => 'local',
            ], $mediaId, filesize($srcPath));
            self::spawnWorker($jobId);
            return ['ok' => true, 'job_id' => $jobId];
        } catch (Throwable $e) {
            error_log('RenditionBuilder::enqueueHls: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not queue HLS.'];
        }
    }

    private static function recordOriginal($mediaId, $absPath, $size, $sha) {
        try {
            $pdo = getDB();
            // Path stored relative to project root when possible (portable across hosts).
            $root = str_replace('\\', '/', dirname(__DIR__, 2));
            $p = str_replace('\\', '/', $absPath);
            $rel = strpos($p, $root) === 0 ? ltrim(substr($p, strlen($root)), '/') : basename($p);
            if ($mediaId) {
                $pdo->prepare("INSERT INTO media_renditions (media_id, quality, storage_driver, storage_path, file_size, sha256, status)
                    VALUES (?, 'original', 'local', ?, ?, ?, 'ready')
                    ON DUPLICATE KEY UPDATE file_size=VALUES(file_size), sha256=VALUES(sha256), status='ready'")
                    ->execute([$mediaId, $rel, $size, $sha]);
            } else {
                $pdo->prepare("INSERT INTO media_objects (media_id, driver, object_key, sha256, size_bytes, is_original) VALUES (NULL,'local',?,?,?,1)
                    ON DUPLICATE KEY UPDATE sha256=VALUES(sha256), size_bytes=VALUES(size_bytes)")
                    ->execute([$rel, $sha, $size]);
            }
        } catch (Throwable $e) { error_log('recordOriginal: ' . $e->getMessage()); }
    }

    /** True when a ready 720p rendition already exists for a path-only source. */
    private static function pathRenditionReady($absPath) {
        $dir = self::outDir(null, basename($absPath));
        $final = $dir . DIRECTORY_SEPARATOR . self::QUALITY . '.mp4';
        return is_file($final) && filesize($final) > 1024;
    }

    private static function findActiveJob($absPath, $mediaId) {
        $pdo = getDB();
        if ($mediaId) {
            $s = $pdo->prepare("SELECT * FROM upload_jobs WHERE kind='rendition' AND media_id=? AND status IN ('queued','uploading','processing') LIMIT 1");
            $s->execute([$mediaId]);
        } else {
            // Match the exact src path stored in the JSON payload (JSON_UNQUOTE handles escaping).
            $s = $pdo->prepare("SELECT * FROM upload_jobs WHERE kind='rendition' AND status IN ('queued','uploading','processing')
                AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.src')) = ? LIMIT 1");
            $s->execute([$absPath]);
        }
        return $s->fetch() ?: null;
    }

    /* ---------------- worker ---------------- */

    /** Public wrapper so admin retry can spawn the worker for a specific job. */
    public static function spawnWorkerPublic($jobId) { self::spawnWorker($jobId); }

    private static function spawnWorker($jobId = null) {
        $worker = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'rendition-worker.php';
        if (!is_file($worker)) return;
        $php = PHP_BINARY ?: 'php';
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ($jobId ? ' ' . (int)$jobId : '');
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen('start /B "" ' . $cmd, 'r'));
        } else {
            exec('nohup ' . $cmd . ' > /dev/null 2>&1 &');
        }
    }

    /** Process one queued rendition job. Returns true on success. */
    public static function processJob($jobId) {
        $jobs = new UploadJob();
        $job = $jobs->get($jobId);
        if (!$job || $job['kind'] !== 'rendition') return false;
        $payload = json_decode((string)$job['payload'], true) ?: [];
        $src = (string)($payload['src'] ?? '');
        $mediaId = !empty($payload['media_id']) ? (int)$payload['media_id'] : null;
        $quality = (string)($payload['quality'] ?? self::QUALITY);

        if (!is_file($src)) {
            $jobs->update($jobId, ['status' => 'failed', 'last_error' => 'Source file missing: ' . basename($src), 'attempts' => ((int)$job['attempts']) + 1]);
            return false;
        }

        $jobs->update($jobId, ['status' => 'processing', 'attempts' => ((int)$job['attempts']) + 1, 'progress' => 5]);

        // HLS package: stream-copy remux of an existing rendition into .m3u8 + .ts segments.
        if ($quality === 'hls') {
            return self::processHlsJob($jobId, $jobs, $job, $src, $mediaId);
        }

        $outDir = self::outDir($mediaId, basename($src));
        if (!is_dir($outDir) && !@mkdir($outDir, 0755, true)) {
            $jobs->update($jobId, ['status' => 'failed', 'last_error' => 'Could not create rendition directory.']);
            return false;
        }
        $final = $outDir . DIRECTORY_SEPARATOR . $quality . '.mp4';
        $tmp = $final . '.tmp.mp4';

        try {
            $duration = self::probeDuration($src); // seconds or null
            $ffmpeg = VideoCompressor::ffmpegPath();
            // Progress + stderr go to FILES, not pipes: PHP pipe EOF/status is unreliable
            // on Windows. ffmpeg -progress writes "progress=end" as its last record —
            // that is our completion signal. stdout is discarded.
            $progFile = tempnam(sys_get_temp_dir(), 'tmzprog_');
            $errFile = tempnam(sys_get_temp_dir(), 'tmzerr_');
            $nullFile = tempnam(sys_get_temp_dir(), 'tmznull_');
            $args = [
                '-hide_banner', '-nostdin', '-y', '-i', $src,
                '-vf', "scale='min(1280,iw)':-2",
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
                '-c:a', 'aac', '-b:a', '128k',
                '-movflags', '+faststart',
                '-progress', $progFile, '-nostats',
                $tmp,
            ];
            $argv = array_merge([$ffmpeg], $args);
            $proc = proc_open($argv, [
                0 => ['file', 'NUL', 'r'],
                1 => ['file', $nullFile, 'w'],
                2 => ['file', $errFile, 'w'],
            ], $pipes);
            if (!is_resource($proc)) {
                @unlink($progFile); @unlink($errFile); @unlink($nullFile);
                $jobs->update($jobId, ['status' => 'failed', 'last_error' => 'Could not start ffmpeg.']);
                return false;
            }
            $stderrTail = '';
            $lastProgressWrite = time() - 3;
            $deadline = time() + 7200; // 2h hard cap per rendition
            $stallDeadline = time() + 120; // no file activity for 2min => ffmpeg died
            $lastActivity = 0;
            $timedOut = false;
            $seenEnd = false;
            while (true) {
                // Poll the progress file (ffmpeg flushes each record).
                $progMtime = is_file($progFile) ? (int)@filemtime($progFile) : 0;
                $tmpMtime = is_file($tmp) ? (int)@filemtime($tmp) : 0;
                $activity = max($progMtime, $tmpMtime);
                if ($activity > $lastActivity) { $lastActivity = $activity; $stallDeadline = time() + 120; }
                if (is_file($progFile) && filesize($progFile) > 0) {
                    $chunk = (string)@file_get_contents($progFile);
                    if (strpos($chunk, 'progress=end') !== false) { $seenEnd = true; }
                    self::parseProgress($chunk, $duration, $jobId, $jobs, $lastProgressWrite);
                }
                if ($seenEnd) {
                    // Give ffmpeg a moment to flush trailer + exit.
                    usleep(500000);
                    if (is_file($progFile) && strpos((string)@file_get_contents($progFile), 'progress=end') !== false) break;
                }
                if (time() > $deadline) { $timedOut = true; break; }
                if (time() > $stallDeadline) break; // ffmpeg died without progress=end
                usleep(400000);
            }
            // NOTE: proc_close() hangs on Windows PHP 8.2 even after the child exits.
            // progress=end + a valid output file is proof enough; terminate is a no-op
            // if ffmpeg already exited, and reaps the handle otherwise.
            @proc_terminate($proc); // no-op if ffmpeg already exited; reaps the handle
            $stderrTail = is_file($errFile) ? (string)@file_get_contents($errFile) : '';
            @unlink($progFile); @unlink($errFile); @unlink($nullFile);

            $exitCode = $timedOut ? -999 : 0;
            if ($timedOut || !is_file($tmp) || filesize($tmp) < 1024) {
                @unlink($tmp);
                $tail = trim((string)$stderrTail);
                $jobs->update($jobId, ['status' => 'failed', 'last_error' => ($timedOut ? 'ffmpeg timed out' : 'ffmpeg exit ' . $exitCode) . ($tail ? ': ' . substr($tail, -300) : ''), 'progress' => 0]);
                return false;
            }

            // Atomic-ish publish: tmp -> final (same dir = same volume).
            if (is_file($final)) @unlink($final); // only ever replaces OUR previous rendition, never the original
            if (!@rename($tmp, $final)) {
                @copy($tmp, $final); @unlink($tmp);
                if (!is_file($final)) { $jobs->update($jobId, ['status' => 'failed', 'last_error' => 'Could not publish rendition file.']); return false; }
            }

            $size = filesize($final);
            $sha = hash_file('sha256', $final);
            $rel = self::relPath($final);

            if ($mediaId) {
                $jobs->upsertRendition($mediaId, $quality, 'local', $rel, $size, $sha, 'ready');
            } else {
                $pdo = getDB();
                $pdo->prepare("INSERT INTO media_objects (media_id, driver, object_key, sha256, size_bytes, is_original) VALUES (NULL,'local',?,?,?,0)
                    ON DUPLICATE KEY UPDATE sha256=VALUES(sha256), size_bytes=VALUES(size_bytes)")
                    ->execute([$rel, $sha, $size]);
            }
            $jobs->update($jobId, ['status' => 'ready', 'progress' => 100, 'bytes_done' => $size, 'bytes_total' => $size, 'last_error' => null]);
            // Chain: a ready 720p MP4 automatically gets an HLS package (stream-copy, cheap).
            if ($quality === self::QUALITY && $mediaId) {
                self::enqueueHls($mediaId, $final);
            }
            return true;
        } catch (Throwable $e) {
            @unlink($tmp ?? '');
            $jobs->update($jobId, ['status' => 'failed', 'last_error' => substr($e->getMessage(), 0, 500), 'progress' => 0]);
            return false;
        }
    }

    /** HLS remux job: -c copy into storage/hls/m{id}/index.m3u8 + seg*.ts. */
    private static function processHlsJob($jobId, UploadJob $jobs, $job, $src, $mediaId) {
        $hlsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'hls'
            . DIRECTORY_SEPARATOR . ($mediaId ? 'm' . (int)$mediaId : 'p_' . preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($src, PATHINFO_FILENAME)));
        if (!is_dir($hlsDir) && !@mkdir($hlsDir, 0755, true)) {
            $jobs->update($jobId, ['status' => 'failed', 'last_error' => 'Could not create HLS directory.']);
            return false;
        }
        $playlist = $hlsDir . DIRECTORY_SEPARATOR . 'index.m3u8';
        $tmpPlaylist = $hlsDir . DIRECTORY_SEPARATOR . 'index.m3u8.tmp';
        // Clean previous attempt's segments so stale .ts files can't leak into the new playlist.
        foreach (glob($hlsDir . DIRECTORY_SEPARATOR . 'seg*.ts') ?: [] as $old) @unlink($old);
        @unlink($playlist);

        try {
            $ffmpeg = VideoCompressor::ffmpegPath();
            $progFile = tempnam(sys_get_temp_dir(), 'tmzprog_');
            $errFile = tempnam(sys_get_temp_dir(), 'tmzerr_');
            $nullFile = tempnam(sys_get_temp_dir(), 'tmznull_');
            $args = [
                '-hide_banner', '-nostdin', '-y', '-i', $src,
                '-c', 'copy', '-start_number', '0',
                '-f', 'hls', '-hls_time', '6', '-hls_playlist_type', 'vod',
                '-hls_segment_filename', $hlsDir . DIRECTORY_SEPARATOR . 'seg%03d.ts',
                '-progress', $progFile, '-nostats',
                $tmpPlaylist,
            ];
            $argv = array_merge([$ffmpeg], $args);
            $proc = proc_open($argv, [
                0 => ['file', 'NUL', 'r'],
                1 => ['file', $nullFile, 'w'],
                2 => ['file', $errFile, 'w'],
            ], $pipes);
            if (!is_resource($proc)) {
                @unlink($progFile); @unlink($errFile); @unlink($nullFile);
                $jobs->update($jobId, ['status' => 'failed', 'last_error' => 'Could not start ffmpeg.']);
                return false;
            }
            $lastProgressWrite = time() - 3;
            $deadline = time() + 3600;
            $stallDeadline = time() + 120;
            $lastActivity = 0;
            $seenEnd = false;
            $duration = self::probeDuration($src);
            while (true) {
                $progMtime = is_file($progFile) ? (int)@filemtime($progFile) : 0;
                $tmpMtime = is_file($tmpPlaylist) ? (int)@filemtime($tmpPlaylist) : 0;
                $segMtime = 0;
                foreach (glob($hlsDir . DIRECTORY_SEPARATOR . 'seg*.ts') ?: [] as $s) { $segMtime = max($segMtime, (int)@filemtime($s)); }
                $activity = max($progMtime, $tmpMtime, $segMtime);
                if ($activity > $lastActivity) { $lastActivity = $activity; $stallDeadline = time() + 120; }
                if (is_file($progFile) && filesize($progFile) > 0) {
                    $chunk = (string)@file_get_contents($progFile);
                    if (strpos($chunk, 'progress=end') !== false) $seenEnd = true;
                    self::parseProgress($chunk, $duration, $jobId, $jobs, $lastProgressWrite);
                }
                if ($seenEnd) {
                    usleep(500000);
                    if (is_file($progFile) && strpos((string)@file_get_contents($progFile), 'progress=end') !== false) break;
                }
                if (time() > $deadline || time() > $stallDeadline) break;
                usleep(400000);
            }
            @proc_terminate($proc);
            $stderrTail = is_file($errFile) ? (string)@file_get_contents($errFile) : '';
            @unlink($progFile); @unlink($errFile); @unlink($nullFile);

            if (!$seenEnd || !is_file($tmpPlaylist) || filesize($tmpPlaylist) < 50) {
                @unlink($tmpPlaylist);
                $jobs->update($jobId, ['status' => 'failed', 'last_error' => 'HLS remux failed: ' . substr(trim($stderrTail), -300), 'progress' => 0]);
                return false;
            }
            if (is_file($playlist)) @unlink($playlist);
            if (!@rename($tmpPlaylist, $playlist)) { @copy($tmpPlaylist, $playlist); @unlink($tmpPlaylist); }

            // Playlist path stored project-relative like other renditions.
            $rel = self::relPath($playlist);
            $size = filesize($playlist);
            if ($mediaId) {
                $jobs->upsertRendition($mediaId, 'hls', 'local', $rel, $size, hash_file('sha256', $playlist), 'ready');
            }
            $jobs->update($jobId, ['status' => 'ready', 'progress' => 100, 'bytes_done' => $size, 'bytes_total' => $size, 'last_error' => null]);
            return true;
        } catch (Throwable $e) {
            @unlink($tmpPlaylist ?? '');
            $jobs->update($jobId, ['status' => 'failed', 'last_error' => substr($e->getMessage(), 0, 500), 'progress' => 0]);
            return false;
        }
    }

    /** Parse ffmpeg -progress stdout lines; update job percent (10–95 band while encoding). */
    private static function parseProgress($chunk, $duration, $jobId, UploadJob $jobs, &$lastWrite) {
        static $outTime = 0;
        static $lastJobId = 0;
        if ($jobId !== $lastJobId) { $outTime = 0; $lastJobId = $jobId; }
        if (preg_match('/out_time_ms=(\d+)/', $chunk, $m)) $outTime = max($outTime, (int)$m[1]);
        if ($duration && $duration > 0 && $outTime > 0) {
            $pct = 10 + (int)min(85, ($outTime / 1000000 / $duration) * 85);
            if ($pct !== $lastWrite && (time() - $lastWrite) >= 2) {
                $jobs->update($jobId, ['progress' => $pct]);
                $lastWrite = time();
            }
        }
    }

    /* ---------------- helpers ---------------- */

    private static function outDir($mediaId, $srcName) {
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'renditions';
        $name = $mediaId ? 'm' . $mediaId : 'p_' . preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($srcName, PATHINFO_FILENAME));
        return $base . DIRECTORY_SEPARATOR . $name;
    }

    private static function relPath($abs) {
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $p = str_replace('\\', '/', $abs);
        // storage_path convention: project-root-relative, e.g. 'storage/renditions/m1/720p.mp4'
        return strpos($p, $root) === 0 ? ltrim(substr($p, strlen($root)), '/') : $p;
    }

    private static function probeDuration($src) {
        $ffprobe = self::ffprobePath();
        if (!$ffprobe) return null;
        $out = [];
        @exec(escapeshellarg($ffprobe) . ' -v error -show_entries format=duration -of csv=p=0 ' . escapeshellarg($src) . ' 2>NUL', $out);
        return isset($out[0]) && is_numeric($out[0]) ? (float)$out[0] : null;
    }

    private static function ffprobePath() {
        if (self::$ffprobe !== null) return self::$ffprobe;
        $ffmpeg = VideoCompressor::ffmpegPath();
        $candidate = preg_replace('/ffmpeg(\.exe)?$/i', 'ffprobe$1', $ffmpeg);
        self::$ffprobe = ($candidate !== $ffmpeg && is_file($candidate)) ? $candidate : null;
        return self::$ffprobe;
    }
}
