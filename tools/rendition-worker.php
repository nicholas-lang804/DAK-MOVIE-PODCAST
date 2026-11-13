<?php
/**
 * Rendition worker — processes queued 'rendition' jobs from upload_jobs.
 * Usage:
 *   php tools/rendition-worker.php          # process ALL queued jobs
 *   php tools/rendition-worker.php <job_id> # process one job
 * Safe to run repeatedly; jobs are claimed one at a time.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$root = dirname(__DIR__);
require_once $root . '/config/database.php';
require_once $root . '/app/Models/SiteSetting.php';
require_once $root . '/app/Models/UploadJob.php';
require_once $root . '/app/Services/VideoCompressor.php';
require_once $root . '/app/Services/RenditionBuilder.php';

$jobs = new UploadJob();
$target = isset($argv[1]) ? (int)$argv[1] : null;

if ($target) {
    // Claim only if still queued (prevents double-processing when admin retries).
    $job = $jobs->get($target);
    if (!$job || !in_array($job['status'], ['queued', 'failed'], true)) exit(0);
    if ($job['status'] === 'failed' && (int)$job['attempts'] >= (int)$job['max_attempts']) exit(0);
    exit(RenditionBuilder::processJob($target) ? 0 : 1);
}

// Reclaim jobs stuck in 'processing' (worker crash) — older than 15 minutes go back to queued.
$pdo = getDB();
$pdo->exec("UPDATE upload_jobs SET status='queued', progress=0, last_error='recovered: worker interrupted' WHERE kind='rendition' AND status='processing' AND updated_at < (NOW() - INTERVAL 15 MINUTE)");

// Drain queue.
$ids = $jobs->recent(50);
$processed = 0;
foreach ($ids as $j) {
    if ($j['kind'] !== 'rendition' || $j['status'] !== 'queued') continue;
    RenditionBuilder::processJob((int)$j['id']);
    $processed++;
}
echo "processed={$processed}\n";
