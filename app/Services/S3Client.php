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

    /** Bucket-level URL path: /<bucket> (no key) — for cors/lifecycle ops. */
    private function bucketPath() {
        return '/' . $this->enc($this->bucket);
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

    /* ---------------- multipart upload (direct browser -> R2) ---------------- */

    private function xmlList($xml, $tag) {
        $out = [];
        if (preg_match_all('/<' . $tag . '>(.*?)<\/' . $tag . '>/s', (string)$xml, $m)) {
            foreach ($m[1] as $v) $out[] = trim(html_entity_decode($v));
        }
        return $out;
    }

    /** Start a multipart upload. Returns the UploadId. */
    public function createMultipartUpload($key, $contentType = 'application/octet-stream') {
        [$code, $body] = $this->signedRequest('POST', $key, ['uploads' => ''], '',
            ['content-type' => $contentType], 60);
        if ($code !== 200) {
            $msg = 'R2 create multipart failed (HTTP ' . $code . ')';
            if (preg_match('/<Code>([^<]+)<\/Code>/', (string)$body, $cm)) $msg .= ' [' . $cm[1] . ']';
            if (preg_match('/<Message>([^<]+)<\/Message>/', (string)$body, $mm)) $msg .= ': ' . $mm[1];
            throw new RuntimeException($msg);
        }
        $ids = $this->xmlList($body, 'UploadId');
        if (!$ids) throw new RuntimeException('R2 did not return an UploadId.');
        return $ids[0];
    }

    /** Presigned PUT URL for one part (partNumber 1..10000, <=5GB each). */
    public function presignedUploadPartUrl($key, $uploadId, $partNumber, $ttl = 7200) {
        [$amz, $dateStamp] = explode('|', $this->amzDate());
        $path = $this->objectPath($key);
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $params = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $this->accessKey . '/' . $dateStamp . '/auto/s3/aws4_request',
            'X-Amz-Date'          => $amz,
            'X-Amz-Expires'       => (string)max(60, (int)$ttl),
            'X-Amz-SignedHeaders' => 'host',
            'partNumber'          => (string)(int)$partNumber,
            'uploadId'            => (string)$uploadId,
        ];
        ksort($params);
        $canonQuery = $this->canonicalQuery($params);
        $canonRequest = 'PUT' . "\n" . $path . "\n" . $canonQuery . "\n" . 'host:' . $host . "\n" . "\n" . 'host' . "\n" . 'UNSIGNED-PAYLOAD';
        $scope = $dateStamp . '/auto/s3/aws4_request';
        $stringToSign = 'AWS4-HMAC-SHA256' . "\n" . $amz . "\n" . $scope . "\n" . hash('sha256', $canonRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($dateStamp));
        return $this->endpoint . $path . '?' . $canonQuery . '&X-Amz-Signature=' . $signature;
    }

    /** List uploaded parts: returns [partNumber => ['etag'=>..,'size'=>..]]. */
    public function listParts($key, $uploadId) {
        [$code, $body] = $this->signedRequest('GET', $key, ['uploadId' => (string)$uploadId], null, [], 60);
        if ($code === 404) {
            $c = '';
            if (preg_match('/<Code>([^<]+)<\/Code>/', (string)$body, $m)) $c = $m[1];
            throw new RuntimeException('R2 multipart upload not found (' . ($c ?: 'NoSuchUpload') . ') — it was aborted or expired');
        }
        if ($code !== 200) return [];
        $parts = [];
        if (preg_match_all('/<Part>(.*?)<\/Part>/s', (string)$body, $blocks)) {
            foreach ($blocks[1] as $b) {
                preg_match('/<PartNumber>(\d+)<\/PartNumber>/', $b, $pn);
                preg_match('/<ETag>([^<]+)<\/ETag>/', $b, $et);
                preg_match('/<Size>(\d+)<\/Size>/', $b, $sz);
                if (!empty($pn[1])) $parts[(int)$pn[1]] = [
                    'etag' => isset($et[1]) ? trim(html_entity_decode($et[1]), '"') : '',
                    'size' => isset($sz[1]) ? (int)$sz[1] : 0,
                ];
            }
        }
        ksort($parts);
        return $parts;
    }

    /** Finish the multipart upload. $parts = [partNumber => etag]. */
    public function completeMultipartUpload($key, $uploadId, array $parts) {
        ksort($parts);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><CompleteMultipartUpload>';
        foreach ($parts as $pn => $etag) {
            $xml .= '<Part><PartNumber>' . (int)$pn . '</PartNumber><ETag>"' . htmlspecialchars($etag, ENT_XML1) . '"</ETag></Part>';
        }
        $xml .= '</CompleteMultipartUpload>';
        [$code, $body] = $this->signedRequest('POST', $key, ['uploadId' => (string)$uploadId], $xml,
            ['content-type' => 'application/xml'], 120);
        if ($code !== 200) {
            $msg = 'R2 complete multipart failed (HTTP ' . $code . ')';
            if (preg_match('/<Code>([^<]+)<\/Code>/', (string)$body, $cm)) $msg .= ' [' . $cm[1] . ']';
            if (preg_match('/<Message>([^<]+)<\/Message>/', (string)$body, $mm)) $msg .= ': ' . $mm[1];
            throw new RuntimeException($msg . ' | ' . substr((string)$body, 0, 300));
        }
        return true;
    }

    /** Abort an unfinished multipart upload (frees R2 storage). */
    public function abortMultipartUpload($key, $uploadId) {
        $this->signedRequest('DELETE', $key, ['uploadId' => (string)$uploadId], null, [], 60);
        return true;
    }

    /* ---------------- bucket-level ops ---------------- */

    /** Signed request against the bucket root (no object key). */
    private function bucketRequest($method, array $query, $body = null, array $extraHeaders = [], $timeout = 60) {
        [$amz, $dateStamp] = explode('|', $this->amzDate());
        $path = $this->bucketPath();
        $host = parse_url($this->endpoint, PHP_URL_HOST);
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
        if (is_string($body)) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) throw new RuntimeException('R2 bucket request failed: ' . $err);
        return [$code, $resp];
    }

    /** Allow browser PUTs (multipart parts) from the site origin. Exposes ETag. */
    public function putBucketCors(array $origins) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><CORSConfiguration><CORSRule>';
        foreach ($origins as $o) $xml .= '<AllowedOrigin>' . htmlspecialchars($o, ENT_XML1) . '</AllowedOrigin>';
        $xml .= '<AllowedMethod>PUT</AllowedMethod><AllowedMethod>GET</AllowedMethod><AllowedMethod>HEAD</AllowedMethod>'
              . '<AllowedHeader>*</AllowedHeader><ExposeHeader>ETag</ExposeHeader>'
              . '<MaxAgeSeconds>3600</MaxAgeSeconds></CORSRule></CORSConfiguration>';
        [$code, $body] = $this->bucketRequest('PUT', ['cors' => ''], $xml, ['content-type' => 'application/xml']);
        if ($code !== 200) throw new RuntimeException('R2 putBucketCors failed (HTTP ' . $code . '): ' . substr((string)$body, 0, 300));
        return true;
    }
}
