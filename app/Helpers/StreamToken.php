<?php
/**
 * Signed, expiring stream URLs for movie playback.
 *
 * A stream link is bound to (movie, user, expiry) via HMAC — it cannot be
 * shared to another account, bookmarked forever, or reused after expiry.
 * The secret lives in site_settings.media_signing_secret (auto-created).
 */
require_once __DIR__ . '/../Models/SiteSetting.php';

if (!function_exists('tmz_stream_secret')) {
    function tmz_stream_secret() {
        static $secret = null;
        if ($secret !== null) return $secret;
        $secret = trim((string)(new SiteSetting())->get('media_signing_secret'));
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            try {
                getDB()->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('media_signing_secret', ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$secret]);
            } catch (Throwable $e) { /* read-only fallback still works per-request */ }
        }
        return $secret;
    }
}

if (!function_exists('tmz_movie_stream_token')) {
    /** HMAC token binding movie + viewer + expiry. */
    function tmz_movie_stream_token($movieId, $userId, $exp) {
        return hash_hmac('sha256', 'movie|' . (int)$movieId . '|' . (int)$userId . '|' . (int)$exp, tmz_stream_secret());
    }
}

if (!function_exists('tmz_movie_stream_url')) {
    /** Signed media-play URL valid for $ttlSeconds (default 6h). */
    function tmz_movie_stream_url($movieId, $userId = 0, $ttlSeconds = 21600, $extra = '') {
        $exp = time() + (int)$ttlSeconds;
        $tok = tmz_movie_stream_token($movieId, $userId, $exp);
        return '/media-play.php?movie_id=' . (int)$movieId . '&tok=' . urlencode($tok) . '&exp=' . $exp . $extra;
    }
}

if (!function_exists('tmz_movie_stream_valid')) {
    function tmz_movie_stream_valid($movieId, $userId, $tok, $exp) {
        if (!$exp || $exp < time() || strlen((string)$tok) !== 64) return false;
        return hash_equals(tmz_movie_stream_token($movieId, $userId, (int)$exp), (string)$tok);
    }
}
