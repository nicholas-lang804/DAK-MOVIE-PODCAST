<?php
session_start();

require_once __DIR__ . '/../config/oauth.php';

function tmzBase64UrlEncode($value) {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

$config = tmzOAuthConfig()['google'] ?? [];
if (empty($config['client_id']) || empty($config['client_secret'])) {
    $_SESSION['oauth_error'] = 'Google sign-in is not configured yet. Add TMZ_GOOGLE_CLIENT_ID and TMZ_GOOGLE_CLIENT_SECRET.';
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

$state = bin2hex(random_bytes(32));
$verifier = bin2hex(random_bytes(32));
$challenge = tmzBase64UrlEncode(hash('sha256', $verifier, true));

$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_code_verifier'] = $verifier;

$params = [
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => implode(' ', $config['scopes']),
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
