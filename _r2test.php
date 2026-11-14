<?php
/**
 * TEMPORARY diagnostic — delete after use.
 * Runs a full multipart cycle against R2 from THIS server and prints raw results.
 * Admin-only.
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
    return $v === '' ? '(empty)' : 'len=' . strlen($v) . ' first4=' . substr($v, 0, 4) . ' last4=' . substr($v, -4);
};
echo "r2_secret_key : " . $mask($s['r2_secret_key'] ?? null) . " (want len=64)\n";
echo "server time   : " . date('c') . "\n\n";

try {
    $s3 = S3Client::fromSettings($s);
    $key = 'uploads/_diag_' . time() . '.mp4';
    $uid = $s3->createMultipartUpload($key, 'video/mp4');
    echo "createMultipartUpload: OK\n";
    echo "uploadId length: " . strlen($uid) . "\n";

    // PUT one small part via presigned URL (same path the browser uses)
    $url = $s3->presignedUploadPartUrl($key, $uid, 1);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => 'DIAGPART',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "part PUT: HTTP $code\n";

    usleep(1000000); // give R2 a moment
    $parts = $s3->listParts($key, $uid);
    echo "listParts count: " . count($parts) . "\n";
    var_export($parts);
    echo "\n";

    $s3->abortMultipartUpload($key, $uid);
    echo "abort: OK\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
