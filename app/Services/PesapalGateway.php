<?php
require_once __DIR__ . '/../Models/SiteSetting.php';
require_once __DIR__ . '/../Helpers/PublicUrl.php';

class PesapalGateway {
    private $settings;
    private static $tokenCache = null;

    public function __construct() {
        $this->settings = [];
        foreach ((new SiteSetting())->getAll() as $row) { $this->settings[$row['setting_key']] = $row['setting_value']; }
    }

    public function isConfigured() {
        return !empty($this->settings['pesapal_consumer_key']) && !empty($this->settings['pesapal_consumer_secret']);
    }

    public function environment() {
        return ($this->settings['pesapal_environment'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    private function baseUrl() {
        return $this->environment() === 'live' ? 'https://pay.pesapal.com/v3/api' : 'https://cybqa.pesapal.com/pesapalv3/api';
    }

    /* ------------------------------------------------------------ URLs */

    /** Public base URL: manual override first, then auto-detection (works with Cloudflare Tunnel / custom domains). */
    public function publicBaseUrl() {
        $override = trim((string)($this->settings['pesapal_public_base_url'] ?? ''));
        if ($override !== '') {
            $parts = parse_url($override);
            if (is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) && !empty($parts['host'])) {
                return rtrim($override, '/');
            }
        }
        return TmzPublicUrl::base();
    }

    /** Base URL guaranteed to end with the app's / path segment. */
    private function appBaseUrl() {
        $base = $this->publicBaseUrl();
        $path = (string)(parse_url($base, PHP_URL_PATH) ?? '');
        $segments = array_values(array_filter(explode('/', rawurldecode($path)), 'strlen'));
        $last = strtolower((string)end($segments));
        if ($last !== 'tmz podcast') {
            $base = rtrim($base, '/') . '/';
        }
        return rtrim($base, '/');
    }

    public function callbackUrl() {
        $u = trim((string)($this->settings['pesapal_callback_url'] ?? ''));
        return $u !== '' ? $u : $this->appBaseUrl() . '/payments/pesapal_callback.php';
    }

    public function ipnUrl() {
        $u = trim((string)($this->settings['pesapal_ipn_url'] ?? ''));
        return $u !== '' ? $u : $this->appBaseUrl() . '/payments/pesapal_ipn.php';
    }

    /** True if the URL's host is a real public hostname (not localhost / private IP). */
    public function isPublicUrl($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        $host = strtolower($host);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) return false;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        // *.local and single-label hosts are not public either.
        if (strpos($host, '.') === false || substr($host, -6) === '.local') return false;
        return true;
    }

    /** Check that a URL is reachable over HTTP(S). Returns [ok, http_code, message]. */
    public function checkReachable($url) {
        if (!preg_match('#^https?://#i', $url)) return [false, 0, 'Not a valid http(s) URL.'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return [false, 0, $err];
        // Any HTTP response means the endpoint is reachable (even 4xx/405 on HEAD).
        return [$code > 0, $code, $code > 0 ? 'Reachable (HTTP ' . $code . ')' : 'No response.'];
    }

    /* ------------------------------------------------------------ HTTP */

    private function request($url, $method = 'GET', $body = null, $token = null, $retry = true) {
        $ch = curl_init($url);
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($token) { $headers[] = 'Authorization: Bearer ' . $token; }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
        $raw = curl_exec($ch); $error = curl_error($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($error) { throw new RuntimeException($error); }
        $data = json_decode($raw, true);
        if (!is_array($data)) { throw new RuntimeException('Invalid Pesapal response (HTTP ' . $status . ').'); }
        // Expired token → refresh once and retry.
        if ($status === 401 && $token && $retry) {
            self::$tokenCache = null;
            return $this->request($url, $method, $body, $this->token(), false);
        }
        return $data;
    }

    /** Bearer token, cached per request and persisted briefly in site_settings. */
    private function token() {
        if (self::$tokenCache !== null) return self::$tokenCache;
        $store = new SiteSetting();
        $cached = $store->get('pesapal_token_cache');
        $expiry = (int)$store->get('pesapal_token_expires');
        if ($cached && $expiry > time() + 30) { self::$tokenCache = $cached; return $cached; }
        $data = $this->request($this->baseUrl() . '/Auth/RequestToken', 'POST', [
            'consumer_key' => $this->settings['pesapal_consumer_key'],
            'consumer_secret' => $this->settings['pesapal_consumer_secret']
        ]);
        if (empty($data['token'])) {
            $err = $data['error']['message'] ?? $data['error']['error_type'] ?? $data['message'] ?? 'Pesapal authentication failed.';
            throw new RuntimeException('Pesapal auth error: ' . $err);
        }
        self::$tokenCache = $data['token'];
        // Cache for 4 minutes (Pesapal tokens are short-lived; refresh proactively).
        $store->set('pesapal_token_cache', self::$tokenCache);
        $store->set('pesapal_token_expires', (string)(time() + 240));
        return self::$tokenCache;
    }

    /* ------------------------------------------------------------ orders */

    public function submitOrder($payment, $item, $user) {
        if (!$this->isConfigured()) { return ['success' => false, 'message' => 'Pesapal is not configured. Enter the Consumer Key and Secret in Admin → Payments.']; }
        $itemName = is_array($item) ? ($item['title'] ?? $item['name'] ?? 'THE DAK FILM PODCAST MEDIA') : (string)$item;
        $notificationId = trim((string)($this->settings['pesapal_notification_id'] ?? ''));
        if ($notificationId === '') { return ['success' => false, 'message' => 'No IPN notification ID. Register the IPN in Admin → Payments first.']; }
        $payload = [
            'id' => $payment['reference'],
            'currency' => $payment['currency'],
            'amount' => (float)$payment['amount'],
            'description' => 'DAK Film Podcast Media: ' . substr($itemName, 0, 85),
            'callback_url' => $this->callbackUrl(),
            'cancellation_url' => $this->callbackUrl(),
            'notification_id' => $notificationId,
            'redirect_mode' => 'TOP_WINDOW',
            'billing_address' => [
                'email_address' => $user['email'] ?? '',
                'phone_number' => $user['phone'] ?? '',
                'country_code' => 'UG',
                'first_name' => $user['username'] ?? 'TMZ User'
            ]
        ];
        $data = $this->request($this->baseUrl() . '/Transactions/SubmitOrderRequest', 'POST', $payload, $this->token());
        if (empty($data['redirect_url'])) {
            $err = $data['error']['message'] ?? $data['error']['code'] ?? $data['message'] ?? null;
            $detail = $err ? ' (' . $err . ')' : '';
            if (isset($data['error']['code']) && $data['error']['code'] === 'amount_exceeds_default_limit') {
                $detail = ' — this amount is above your Pesapal account\'s default transaction limit. Contact Pesapal support to raise the limit, or lower the price.';
            }
            return ['success' => false, 'message' => 'Pesapal did not return a payment URL' . $detail . '.'];
        }
        return ['success' => true, 'redirect_url' => $data['redirect_url'], 'tracking_id' => $data['order_tracking_id'] ?? null];
    }

    public function transactionStatus($trackingId) {
        return $this->request($this->baseUrl() . '/Transactions/GetTransactionStatus?orderTrackingId=' . rawurlencode($trackingId), 'GET', null, $this->token());
    }

    /* ------------------------------------------------------------ IPN setup */

    /** List IPN registrations from Pesapal. Returns array of ['ipn_id','url',...] or throws. */
    public function ipnList() {
        $data = $this->request($this->baseUrl() . '/URLSetup/GetIpnList', 'GET', null, $this->token());
        return $data['ipn_list'] ?? $data['data'] ?? (is_array($data) ? $data : []);
    }

    /** Register (or reuse) an IPN URL. Returns ['success','ipn_id','reused','message']. */
    public function ensureIpn($url = null, $forceNew = false) {
        if (!$this->isConfigured()) return ['success' => false, 'message' => 'Consumer Key and Secret are required.'];
        $url = $url ?: $this->ipnUrl();
        try {
            if (!$forceNew) {
                foreach ($this->ipnList() as $row) {
                    $existing = $row['url'] ?? $row['ipn_url'] ?? '';
                    $id = $row['ipn_id'] ?? $row['id'] ?? null;
                    if ($existing === $url && $id) {
                        return ['success' => true, 'ipn_id' => (string)$id, 'reused' => true, 'message' => 'Existing IPN registration found and reused.'];
                    }
                }
            }
            $data = $this->request($this->baseUrl() . '/URLSetup/RegisterIPN', 'POST', [
                'url' => $url,
                'ipn_notification_type' => 'POST'
            ], $this->token());
            $ipnId = $data['ipn_id'] ?? $data['data']['ipn_id'] ?? null;
            if (!$ipnId) return ['success' => false, 'message' => $data['message'] ?? 'Pesapal did not return an IPN ID.'];
            return ['success' => true, 'ipn_id' => (string)$ipnId, 'reused' => false, 'message' => 'IPN registered successfully.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'IPN registration failed: ' . $e->getMessage()];
        }
    }

    /** Full connection test: auth token + IPN list. */
    public function testConnection() {
        if (!$this->isConfigured()) return ['success' => false, 'message' => 'Consumer Key and Secret are not set.'];
        try {
            $this->token(); // throws on auth failure
            $this->ipnList();
            return ['success' => true, 'message' => 'Connected to Pesapal (' . $this->environment() . ').'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }
}
