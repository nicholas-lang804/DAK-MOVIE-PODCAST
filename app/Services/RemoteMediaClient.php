<?php
/**
 * RemoteMediaClient — HTTPS client for the Oracle media server.
 *
 * Contract (implemented by oracle/media-api on the media host):
 *   POST {base}/api/uploads            {name,size,sha256,content_type} -> {upload_id, chunk_url, chunk_size, expires}
 *   PUT  {base}/api/uploads/{id}/part  binary body + ?offset=N         -> {received}
 *   POST {base}/api/uploads/{id}/complete                               -> {key, sha256}
 *   GET  {base}/api/sign?key=&exp=&q=                                   -> {url}  (short-lived playback URL)
 *   GET  {base}/api/objects/head?key=                                   -> {exists,size}
 *   DELETE {base}/api/objects?key=                                      -> {ok}
 *   GET  {base}/api/usage                                               -> {bytes}
 *
 * Auth: Authorization: Bearer <media_remote_token> — server-side only,
 * never sent to browsers. All requests use cURL with TLS verification ON.
 */
class RemoteMediaClient {
    private $base;
    private $token;
    private $timeout = 30;

    public function __construct(array $settings) {
        $this->base = rtrim(trim((string)($settings['media_remote_base'] ?? '')), '/');
        $this->token = trim((string)($settings['media_remote_token'] ?? ''));
    }

    private function request($method, $path, $body = null, $headers = [], $timeout = null) {
        if ($this->base === '' || $this->token === '') {
            throw new RuntimeException('Remote media storage is not configured.');
        }
        $ch = curl_init($this->base . $path);
        $h = array_merge(['Accept: application/json', 'Authorization: Bearer ' . $this->token], $headers);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => $timeout ?? $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) throw new RuntimeException('Media server unreachable: ' . $err);
        $data = json_decode((string)$raw, true);
        if ($code >= 400) {
            $msg = is_array($data) ? ($data['error'] ?? $data['message'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);
            throw new RuntimeException('Media server error: ' . $msg);
        }
        return is_array($data) ? $data : [];
    }

    /* ---------------- uploads ---------------- */

    /** Begin a chunked upload session on the remote. Returns session info. */
    public function beginUpload($key, $size, $sha256, $contentType = 'application/octet-stream') {
        return $this->request('POST', '/api/uploads', json_encode([
            'name' => basename($key), 'key' => $key, 'size' => (int)$size,
            'sha256' => $sha256, 'content_type' => $contentType,
        ]), ['Content-Type: application/json']);
    }

    /** Upload one chunk. $offset is bytes already received. Returns bytes acknowledged. */
    public function putChunk($uploadId, $offset, $chunkData) {
        return $this->request('PUT', '/api/uploads/' . rawurlencode($uploadId) . '/part?offset=' . (int)$offset,
            $chunkData, ['Content-Type: application/octet-stream'], 120);
    }

    public function completeUpload($uploadId) {
        return $this->request('POST', '/api/uploads/' . rawurlencode($uploadId) . '/complete', '{}', ['Content-Type: application/json']);
    }

    /** Convenience: whole-file PUT for small files / migration. */
    public function putFile($localPath, $key, $isOriginal = false) {
        if (!is_file($localPath)) return ['ok' => false, 'key' => $key, 'error' => 'Local file missing.'];
        $size = filesize($localPath);
        $sha = hash_file('sha256', $localPath);
        $session = $this->beginUpload($key, $size, $sha, mime_content_type($localPath) ?: 'application/octet-stream');
        $uploadId = $session['upload_id'] ?? null;
        $chunkUrl = $session['chunk_size'] ?? 8388608;
        if (!$uploadId) return ['ok' => false, 'key' => $key, 'error' => 'Remote did not return an upload session.'];
        $fh = fopen($localPath, 'rb');
        $offset = 0;
        try {
            while (!feof($fh)) {
                $data = fread($fh, $chunkUrl);
                if ($data === false || $data === '') break;
                $this->putChunk($uploadId, $offset, $data);
                $offset += strlen($data);
            }
        } finally { fclose($fh); }
        $done = $this->completeUpload($uploadId);
        return ['ok' => !empty($done['ok']) || isset($done['key']), 'key' => $done['key'] ?? $key, 'sha256' => $sha];
    }

    /* ---------------- playback / objects ---------------- */

    /** Ask the media server for a short-lived signed playback URL. */
    public function signedUrl($key, $quality = null, $ttl = 900) {
        $q = '?key=' . rawurlencode($key) . '&exp=' . (time() + $ttl);
        if ($quality) $q .= '&q=' . rawurlencode($quality);
        $res = $this->request('GET', '/api/sign' . $q);
        return $res['url'] ?? '';
    }

    public function exists($key) {
        $res = $this->request('GET', '/api/objects/head?key=' . rawurlencode($key));
        return !empty($res['exists']);
    }

    public function delete($key) {
        $res = $this->request('DELETE', '/api/objects?key=' . rawurlencode($key));
        return !empty($res['ok']);
    }

    public function usageBytes() {
        $res = $this->request('GET', '/api/usage');
        return (int)($res['bytes'] ?? 0);
    }
}
