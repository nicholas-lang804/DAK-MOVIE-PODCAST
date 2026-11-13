<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Models/SiteSetting.php';
use Firebase\JWT\JWT;

function livekitConfig() {
    $saved = [];
    try {
        foreach ((new SiteSetting())->getAll() as $row) $saved[$row['setting_key']] = $row['setting_value'];
    } catch (Throwable $e) {
        error_log('LiveKit settings could not be loaded: ' . $e->getMessage());
    }
    return [
        'url' => rtrim(trim((string)(getenv('LIVEKIT_URL') ?: ($saved['livekit_url'] ?? ''))), '/'),
        'api_key' => trim((string)(getenv('LIVEKIT_API_KEY') ?: ($saved['livekit_api_key'] ?? ''))),
        'api_secret' => trim((string)(getenv('LIVEKIT_API_SECRET') ?: ($saved['livekit_api_secret'] ?? ''))),
        'recording_s3' => [
            'access_key' => trim((string)(getenv('LIVEKIT_RECORDING_S3_ACCESS_KEY') ?: ($saved['livekit_recording_s3_access_key'] ?? ''))),
            'secret' => trim((string)(getenv('LIVEKIT_RECORDING_S3_SECRET') ?: ($saved['livekit_recording_s3_secret'] ?? ''))),
            'region' => trim((string)(getenv('LIVEKIT_RECORDING_S3_REGION') ?: ($saved['livekit_recording_s3_region'] ?? ''))),
            'bucket' => trim((string)(getenv('LIVEKIT_RECORDING_S3_BUCKET') ?: ($saved['livekit_recording_s3_bucket'] ?? ''))),
            'endpoint' => rtrim(trim((string)(getenv('LIVEKIT_RECORDING_S3_ENDPOINT') ?: ($saved['livekit_recording_s3_endpoint'] ?? ''))), '/'),
            'force_path_style' => filter_var(getenv('LIVEKIT_RECORDING_S3_FORCE_PATH_STYLE') ?: ($saved['livekit_recording_s3_force_path_style'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
        ],
    ];
}

function createLiveKitToken($identity, $room, $permissions) {
    $config = livekitConfig();
    if (!$config['url'] || !$config['api_key'] || !$config['api_secret']) throw new RuntimeException('LiveKit is not configured on the server.');
    if (is_bool($permissions)) {
        $permissions = [
            'canPublish' => $permissions,
            'canPublishData' => true,
            'canPublishSources' => $permissions ? ['camera', 'microphone', 'screen_share', 'screen_share_audio'] : [],
        ];
    }
    $now = time();
    $grant = [
        'roomJoin' => true,
        'room' => $room,
        'canSubscribe' => true,
        'canPublish' => !empty($permissions['canPublish']),
        'canPublishData' => !empty($permissions['canPublishData']),
    ];
    if (!empty($permissions['canPublishSources'])) $grant['canPublishSources'] = array_values($permissions['canPublishSources']);
    $claims = ['iss'=>$config['api_key'],'sub'=>$identity,'nbf'=>$now-5,'exp'=>$now+300,'video'=>$grant];
    if (!empty($permissions['name'])) $claims['name'] = (string)$permissions['name'];
    return ['url'=>$config['url'],'token'=>JWT::encode($claims,$config['api_secret'],'HS256')];
}

/** Call a LiveKit RoomService Twirp endpoint with a short-lived server credential. */
function livekitRoomRequest($method, array $payload, $room) {
    $config = livekitConfig();
    if (!$config['url'] || !$config['api_key'] || !$config['api_secret']) throw new RuntimeException('LiveKit is not configured on the server.');
    if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for LiveKit room administration.');
    $serviceToken = JWT::encode([
        'iss' => $config['api_key'],
        'sub' => 'tmz-live-room-admin',
        'nbf' => time() - 5,
        'exp' => time() + 60,
        'video' => ['roomAdmin' => true, 'room' => $room],
    ], $config['api_secret'], 'HS256');
    $endpoint = preg_replace('/^wss:/i', 'https:', $config['url']);
    $endpoint = preg_replace('/^ws:/i', 'http:', $endpoint);
    $endpoint = rtrim($endpoint, '/') . '/twirp/livekit.RoomService/' . rawurlencode($method);
    $handle = curl_init($endpoint);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $serviceToken, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
    ]);
    $responseBody = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);
    if ($responseBody === false) throw new RuntimeException('Could not reach LiveKit room service: ' . $curlError);
    $response = json_decode($responseBody, true);
    if ($status < 200 || $status >= 300) {
        $message = is_array($response) ? ($response['msg'] ?? $response['message'] ?? '') : '';
        throw new RuntimeException('LiveKit room service rejected the request' . ($message !== '' ? ': ' . $message : ' (HTTP ' . $status . ').'));
    }
    return is_array($response) ? $response : [];
}

function livekitEgressRequest($method, array $payload, $room) {
        $config = livekitConfig();
        if (!$config['url'] || !$config['api_key'] || !$config['api_secret']) throw new RuntimeException('LiveKit is not configured on the server.');
        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for LiveKit recordings.');
        $serviceToken = JWT::encode([
            'iss' => $config['api_key'],
            'sub' => 'tmz-live-recording-service',
            'nbf' => time() - 5,
            'exp' => time() + 60,
            'video' => ['roomRecord' => true, 'room' => $room],
        ], $config['api_secret'], 'HS256');
        $endpoint = preg_replace('/^wss:/i', 'https:', $config['url']);
        $endpoint = preg_replace('/^ws:/i', 'http:', $endpoint);
        $endpoint = rtrim($endpoint, '/') . '/twirp/livekit.Egress/' . rawurlencode($method);
        $handle = curl_init($endpoint);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $serviceToken, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);
        if ($body === false) throw new RuntimeException('Could not reach LiveKit Egress: ' . $curlError);
        $response = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($response) ? ($response['msg'] ?? $response['message'] ?? '') : '';
            throw new RuntimeException('LiveKit Egress rejected the request' . ($message !== '' ? ': ' . $message : ' (HTTP ' . $status . ').'));
        }
        return is_array($response) ? $response : [];
}

function livekitRecordingS3Config() {
        $s3 = livekitConfig()['recording_s3'];
        foreach (['access_key', 'secret', 'region', 'bucket'] as $required) {
            if ($s3[$required] === '') throw new RuntimeException('Live recording storage is not configured. Enter the required values in Admin Settings → LiveKit recording storage, or configure the LIVEKIT_RECORDING_S3_* server environment variables.');
        }
        return $s3;
}

function livekitRecordingReadiness() {
        $config = livekitConfig();
        $missing = [];
        foreach ([
            'LIVEKIT_URL' => $config['url'],
            'LIVEKIT_API_KEY' => $config['api_key'],
            'LIVEKIT_API_SECRET' => $config['api_secret'],
            'LIVEKIT_RECORDING_S3_ACCESS_KEY' => $config['recording_s3']['access_key'],
            'LIVEKIT_RECORDING_S3_SECRET' => $config['recording_s3']['secret'],
            'LIVEKIT_RECORDING_S3_REGION' => $config['recording_s3']['region'],
            'LIVEKIT_RECORDING_S3_BUCKET' => $config['recording_s3']['bucket'],
        ] as $name => $value) {
            if ($value === '') $missing[] = $name;
        }
        if (!function_exists('curl_init')) $missing[] = 'PHP cURL extension';
        if ($missing) {
            return [
                'ready' => false,
                'message' => 'Recording is not configured. Missing: ' . implode(', ', $missing) . '.',
            ];
        }
        return ['ready' => true, 'message' => 'Recording configuration is present.'];
}

function livekitRecordingDownloadUrl($objectKey, $expiresSeconds = 600) {
        $s3 = livekitRecordingS3Config();
        $expiresSeconds = max(60, min(604800, (int)$expiresSeconds));
        $key = implode('/', array_map('rawurlencode', explode('/', ltrim((string)$objectKey, '/'))));
        $endpoint = $s3['endpoint'];
        if ($endpoint === '') {
            $host = $s3['force_path_style']
                ? 's3.' . $s3['region'] . '.amazonaws.com'
                : $s3['bucket'] . '.s3.' . $s3['region'] . '.amazonaws.com';
            $path = $s3['force_path_style'] ? '/' . rawurlencode($s3['bucket']) . '/' . $key : '/' . $key;
            $scheme = 'https';
        } else {
            $endpointParts = parse_url($endpoint);
            if (!is_array($endpointParts) || !in_array(strtolower($endpointParts['scheme'] ?? ''), ['http', 'https'], true) || empty($endpointParts['host'])) {
                throw new RuntimeException('The recording S3 endpoint must be an absolute HTTP or HTTPS URL.');
            }
            $scheme = strtolower($endpointParts['scheme']);
            $host = strtolower($endpointParts['host']) . (isset($endpointParts['port']) ? ':' . (int)$endpointParts['port'] : '');
            $prefix = rtrim((string)($endpointParts['path'] ?? ''), '/');
            if ($s3['force_path_style']) {
                $path = $prefix . '/' . rawurlencode($s3['bucket']) . '/' . $key;
            } else {
                $host = $s3['bucket'] . '.' . $host;
                $path = $prefix . '/' . $key;
            }
        }

        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $scope = $date . '/' . $s3['region'] . '/s3/aws4_request';
        $params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $s3['access_key'] . '/' . $scope,
            'X-Amz-Date' => $now,
            'X-Amz-Expires' => (string)$expiresSeconds,
            'X-Amz-SignedHeaders' => 'host',
            'response-content-disposition' => 'attachment',
        ];
        ksort($params, SORT_STRING);
        $canonicalQuery = [];
        foreach ($params as $name => $value) $canonicalQuery[] = rawurlencode($name) . '=' . rawurlencode($value);
        $canonicalQueryString = implode('&', $canonicalQuery);
        $canonicalRequest = "GET\n" . $path . "\n" . $canonicalQueryString . "\nhost:" . $host . "\n\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n" . $now . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $s3['secret'], true);
        $regionKey = hash_hmac('sha256', $s3['region'], $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return $scheme . '://' . $host . $path . '?' . $canonicalQueryString . '&X-Amz-Signature=' . $signature;
}

function livekitDeleteRecordingObject($objectKey) {
        $s3 = livekitRecordingS3Config();
        $key = implode('/', array_map('rawurlencode', explode('/', ltrim((string)$objectKey, '/'))));
        $endpoint = $s3['endpoint'];
        if ($endpoint === '') {
            $host = $s3['force_path_style']
                ? 's3.' . $s3['region'] . '.amazonaws.com'
                : $s3['bucket'] . '.s3.' . $s3['region'] . '.amazonaws.com';
            $path = $s3['force_path_style'] ? '/' . rawurlencode($s3['bucket']) . '/' . $key : '/' . $key;
            $scheme = 'https';
        } else {
            $endpointParts = parse_url($endpoint);
            if (!is_array($endpointParts) || !in_array(strtolower($endpointParts['scheme'] ?? ''), ['http', 'https'], true) || empty($endpointParts['host'])) {
                throw new RuntimeException('The recording S3 endpoint must be an absolute HTTP or HTTPS URL.');
            }
            $scheme = strtolower($endpointParts['scheme']);
            $host = strtolower($endpointParts['host']) . (isset($endpointParts['port']) ? ':' . (int)$endpointParts['port'] : '');
            $prefix = rtrim((string)($endpointParts['path'] ?? ''), '/');
            if ($s3['force_path_style']) {
                $path = $prefix . '/' . rawurlencode($s3['bucket']) . '/' . $key;
            } else {
                $host = $s3['bucket'] . '.' . $host;
                $path = $prefix . '/' . $key;
            }
        }

        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $payloadHash = hash('sha256', '');
        $canonicalHeaders = "host:" . $host . "\n" .
            "x-amz-content-sha256:" . $payloadHash . "\n" .
            "x-amz-date:" . $now . "\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $scope = $date . '/' . $s3['region'] . '/s3/aws4_request';
        $canonicalRequest = "DELETE\n" . $path . "\n\n" . $canonicalHeaders . "\n" .
            $signedHeaders . "\n" . $payloadHash;
        $stringToSign = "AWS4-HMAC-SHA256\n" . $now . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $s3['secret'], true);
        $regionKey = hash_hmac('sha256', $s3['region'], $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $url = $scheme . '://' . $host . $path;

        if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required to delete recordings.');
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Host: ' . $host,
                'x-amz-content-sha256: ' . $payloadHash,
                'x-amz-date: ' . $now,
                'Authorization: AWS4-HMAC-SHA256 Credential=' . $s3['access_key'] . '/' . $scope .
                    ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $responseBody = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);
        if ($responseBody === false) throw new RuntimeException('Could not reach recording storage: ' . $curlError);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Recording storage rejected the delete request (HTTP ' . $status . ').');
        }
}
