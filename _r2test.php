<?php
/**
 * TEMPORARY diagnostic — delete after use.
 * Verifies the R2 settings stored in the DB actually match what Cloudflare expects.
 * Admin-only. Paste output back for debugging.
 */
session_start();
header('Content-Type: text/plain');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/SiteSetting.php';
require_once __DIR__ . '/app/Services/S3Client.php';

if (!isLoggedIn() || !isAdmin()) { http_response_code(403); echo "Admin access required\n"; exit; }

$s = (new SiteSetting())->getAllKeyed();
$mask = function ($v) {
    $v = (string)$v;
    if ($v === '') return '(empty)';
    return 'len=' . strlen($v) . '  first4=' . substr($v, 0, 4) . '  last4=' . substr($v, -4);
};

echo "=== Stored R2 settings ===\n";
echo "media_driver     : " . var_export($s['media_driver'] ?? null, true) . "\n";
echo "r2_account_id    : " . $mask($s['r2_account_id'] ?? null) . "  [expected len=32, first4=9330, last4=7e39]\n";
echo "r2_access_key    : " . $mask($s['r2_access_key'] ?? null) . "  [expected len=32, first4=0b91, last4=3376]\n";
echo "r2_secret_key    : " . $mask($s['r2_secret_key'] ?? null) . "  [expected len=64, first4=d035, last4=6918]\n";
echo "r2_bucket        : " . $mask($s['r2_bucket'] ?? null) . "  [expected len=12, first4=dakf, last4=media]\n";
echo "\nServer clock    : " . date('c') . "  (GMT: " . gmdate('c') . ")\n";
echo "Time skew       : " . (time() - gmmktime()) . " seconds\n";

try {
    $s3 = S3Client::fromSettings($s);
    $key = 'uploads/_diag_' . time() . '.mp4';
    $uploadId = $s3->createMultipartUpload($key, 'video/mp4');
    echo "\ncreateMultipartUpload: OK (uploadId len=" . strlen($uploadId) . ")\n";
    $s3->abortMultipartUpload($key, $uploadId);
    echo "abortMultipartUpload: OK\n";
} catch (Throwable $e) {
    echo "\nFAILED: " . $e->getMessage() . "\n";
}
