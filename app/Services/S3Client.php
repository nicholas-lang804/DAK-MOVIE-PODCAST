<?php
/**
 * S3Client — minimal AWS Signature V4 client for Cloudflare R2 (S3-compatible).
 *
 * No SDK dependency. Supports: PUT object, presigned GET (playback/download),
 * HEAD, DELETE, and usage listing. Region is always 'auto' for R2.
 * Credentials come from site_settings — server-side only, never in JS.
 */
class S3Client {
    private $endpoint;   // https://<accountid>.r2.cloudflarestorage.com
    private $bucket;
    private $accessKey;
    private $secretKey;

    public function __construct($accountId, $accessKey, $secretKey, $bucket) {
        $this->endpoint  = 'https://' . trim($accountId) . '.r2.cloudflarestorage.com';
        $this->accessKey = trim($accessKey);
        $this->secretKey = trim($secretKey);
        $this->bucket    = trim($bucket);
        if ($this->accessKey === '' || $this->secretKey === '' || $this->bucket === '') {
            throw new RuntimeException('R2 storage is not fully configured.');
        }
    }

    public static function fromSettings(array $s) {
        return new self($s['r2_account_id'] ?? '', $s['r2_access_key'] ?? '',
            $s['r2_secret_key'] ?? '', $s['r2_bucket'] ?? '');
    }

    private function enc($s) { return rawurlencode($s); }

    /** Object URL path: /<bucket>/<key> with each segment encoded. */
    private function objectPath($key) {
        $key = ltrim(str_replace('\\', '/', (string)$key), '/');
        $parts = array_map([$this, 'enc'], explode('/', $key));
        return '/' . $this->enc($this->bucket) . '/' . implode('/', $parts);
    }

    private function amzDate() {
        $t = time();
        return gmdate('Ymd\THis\Z', $t) . '|' . gmdate('Ymd', $t);
    }

    /** Canonical query string: assoc array -> sorted, encoded, joined. */
    private function canonicalQuery(array $params) {
        ksort($params);
        $out = [];
        foreach ($params as $k => $v) $out[] = $this->enc($k) . '=' . $this->enc($v);
        return implode('&', $out);
    }

    private function signingKey($dateStamp) {
        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', 'auto', $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /** Signed request via cURL. Returns [httpCode, body]. */
    private function signedRequest($method, $key, array $query, $body = null, array $extraHeaders = [], $timeout = 3600) {
        [$amz, $dateStamp] = explode('|', $this->amzDate());
        $path = $this->objectPath($key);
        $host = parse_url($this->endpoint, PHP_URL_HOST);

        // Unsigned payload keeps huge uploads cheap; R2 accepts it over TLS.
        $payloadHash = 'UNSIGNED-PAYLOAD';
        $headers = array_merge([
            'host'                 => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'           => $amz,
        ], array_change_key_case($extraHeaders, CASE_LOWER));
        ksort($headers);

        $canonHeaders = '';
        foreach ($headers as $k => $v) $canonHeaders .= $k . ':' . trim((string)$v) . "\n";
        $signedHeaders = implode(';', array_keys($headers));

        $canonQuery = $this->canonicalQuery($query);
        $canonRequest = $method . "\n" . $path . "\n" . $canonQuery . "\n" . $canonHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;

        $scope = $dateStamp . '/auto/s3/aws4_request';
        $stringToSign = 'AWS4-HMAC-SHA256' . "\n" . $amz . "\n" . $scope . "\n" . hash('sha256', $canonRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($dateStamp));

        $auth = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope
              . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $qs = $canonQuery !== '' ? '?' . $canonQuery : '';
        $ch = curl_init($this->endpoint . $path . $qs);
        $reqHeaders = [];
        foreach ($headers as $k => $v) {
            if ($k === 'host') continue;
            $reqHeaders[] = $k . ': ' . $v;
        }
        $reqHeaders[] = 'Authorization: ' . $auth;
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $reqHeaders,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $uploadFh = null;
        if ($body !== null) {
            if (is_string($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } else {
                // Stream a file without loading it into memory.
                $uploadFh = fopen($body, 'rb');
                curl_setopt($ch, CURLOPT_INFILE, $uploadFh);
                curl_setopt($ch, CURLOPT_INFILESIZE, filesize($body));
                curl_setopt($ch, CURLOPT_UPLOAD, true);
            }
        }
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_resource($uploadFh)) fclose($uploadFh);
        if ($err) throw new RuntimeException('R2 request failed: ' . $err);
        return [$code, $resp];
    }

    /* ---------------- operations ---------------- */

    /** Upload a local file (streamed) to the bucket. Throws on failure. */
    public function putFile($localPath, $key, $contentType = 'application/octet-stream') {
        if (!is_file($localPath)) throw new RuntimeException('Local file missing.');
        [$code, $body] = $this->signedRequest('PUT', $key, [], $localPath,
            ['content-type' => $contentType], 0);
        if ($code !== 200) {
            throw new RuntimeException('R2 upload failed (HTTP ' . $code . '): ' . substr((string)$body, 0, 300));
        }
        return true;
    }

    /** Presigned GET URL — playback or forced download. */
    public function presignedGetUrl($key, $ttl = 900, $downloadName = null) {
        [$amz, $dateStamp] = explode('|', $this->amzDate());
        $path = $this->objectPath($key);
        $host = parse_url($this->endpoint, PHP_URL_HOST);

        $params = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $this->accessKey . '/' . $dateStamp . '/auto/s3/aws4_request',
            'X-Amz-Date'          => $amz,
            'X-Amz-Expires'       => (string)max(60, (int)$ttl),
            'X-Amz-SignedHeaders' => 'host',
        ];
        if ($downloadName !== null) {
            // Forces "Save as" with a clean name on the S3 response.
            $params['response-content-disposition'] = 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._ -]+/', '_', $downloadName) . '"';
        }

        // Canonical request for query-string auth.
        ksort($params);
        $canonQuery = $this->canonicalQuery($params);
        $canonRequest = 'GET' . "\n" . $path . "\n" . $canonQuery . "\n" . 'host:' . $host . "\n" . "\n" . 'host' . "\n" . 'UNSIGNED-PAYLOAD';
        $scope = $dateStamp . '/auto/s3/aws4_request';
        $stringToSign = 'AWS4-HMAC-SHA256' . "\n" . $amz . "\n" . $scope . "\n" . hash('sha256', $canonRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($dateStamp));

        return $this->endpoint . $path . '?' . $canonQuery . '&X-Amz-Signature=' . $signature;
    }

    public function head($key) {
        [$code] = $this->signedRequest('HEAD', $key, []);
        return $code === 200;
    }

    public function delete($key) {
        [$code] = $this->signedRequest('DELETE', $key, []);
        return $code === 204 || $code === 200;
    }
}
