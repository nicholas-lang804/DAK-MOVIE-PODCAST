<?php
/**
 * OAuth configuration for social sign-in providers.
 * Set these values in your server environment or Apache/PHP-FPM config.
 *
 * Example:
 *   TMZ_GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
 *   TMZ_GOOGLE_CLIENT_SECRET=your-client-secret
 *   TMZ_GOOGLE_REDIRECT_URI=http://localhost/TMZ%20PODCAST/auth/google-callback.php
 *
 * For local XAMPP on Windows, use Apache environment variables or add them in php.ini.
 */

function tmzOAuthConfig() {
    $getEnv = function ($name, $fallback = '') {
        $value = getenv($name);
        if ($value !== false && $value !== null && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        $value = $_ENV[$name] ?? $_SERVER[$name] ?? $fallback;
        return is_string($value) ? trim($value) : (string) $value;
    };

    $baseUrl = rtrim($getEnv('TMZ_BASE_URL', 'http://localhost/TMZ%20PODCAST'), '/');

    return [
        'google' => [
            'client_id' => $getEnv('TMZ_GOOGLE_CLIENT_ID', ''),
            'client_secret' => $getEnv('TMZ_GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => $getEnv('TMZ_GOOGLE_REDIRECT_URI', $baseUrl . '/auth/google-callback.php'),
            'scopes' => ['openid', 'email', 'profile'],
        ],
    ];
}

function tmzGoogleOAuthConfigured() {
    $config = tmzOAuthConfig()['google'] ?? [];
    return !empty($config['client_id']) && !empty($config['client_secret']) && !empty($config['redirect_uri']);
}
