<?php

class TmzPublicUrl {
    public static function base() {
        $configured = trim((string)(getenv('TMZ_BASE_URL') ?: ''));
        if ($configured !== '') {
            $parts = parse_url($configured);
            if (is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) &&
                !empty($parts['host']) && !array_key_exists('user', $parts) && !array_key_exists('pass', $parts) &&
                !array_key_exists('query', $parts) && !array_key_exists('fragment', $parts)) {
                return rtrim($configured, '/');
            }
        }

        $host = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.-]+$/', $host)) $host = 'localhost';
        $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        $scheme = $https ? 'https' : 'http';
        $port = (int)($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
        if ($port > 0 && $port !== ($https ? 443 : 80)) $host .= ':' . $port;

        $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
        $basePath = rtrim(dirname($scriptPath), '/');
        if (substr($basePath, -6) === '/admin') $basePath = substr($basePath, 0, -6);
        if (substr($basePath, -7) === '/public') $basePath = substr($basePath, 0, -7);
        if ($basePath === '.' || $basePath === '/') $basePath = '';
        if ($basePath !== '') {
            $segments = explode('/', trim(rawurldecode($basePath), '/'));
            $basePath = '/' . implode('/', array_map('rawurlencode', $segments));
        }

        return $scheme . '://' . $host . $basePath;
    }

    public static function to($path) {
        $path = (string)$path;
        $parts = parse_url($path);
        if (is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) && isset($parts['host'])) return $path;

        $relativePath = ltrim((string)($parts['path'] ?? ''), '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $relativePath)));
        $url = self::base() . '/' . $encodedPath;
        if (isset($parts['query'])) $url .= '?' . $parts['query'];
        if (isset($parts['fragment'])) $url .= '#' . rawurlencode($parts['fragment']);
        return $url;
    }
}
