<?php
session_start();

require_once __DIR__ . '/../config/oauth.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../app/Models/User.php';

function tmzBase64UrlDecode($value) {
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }
    return base64_decode(strtr($value, '-_', '+/'));
}

if (empty($_GET['code'])) {
    $_SESSION['oauth_error'] = 'Google sign-in was cancelled or failed.';
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

$config = tmzOAuthConfig()['google'] ?? [];
if (empty($config['client_id']) || empty($config['client_secret'])) {
    $_SESSION['oauth_error'] = 'Google sign-in is not configured yet. Add TMZ_GOOGLE_CLIENT_ID and TMZ_GOOGLE_CLIENT_SECRET.';
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

if (($_GET['state'] ?? '') !== ($_SESSION['oauth_state'] ?? '')) {
    $_SESSION['oauth_error'] = 'The Google sign-in request is invalid or expired.';
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

$tokenRequest = [
    'code' => $_GET['code'],
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'redirect_uri' => $config['redirect_uri'],
    'grant_type' => 'authorization_code',
    'code_verifier' => $_SESSION['oauth_code_verifier'] ?? '',
];

$curl = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($tokenRequest),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 30,
]);
$tokenResponse = curl_exec($curl);
$tokenStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($tokenStatus >= 400 || $tokenResponse === false) {
    $_SESSION['oauth_error'] = 'Google token exchange failed.';
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

$tokenData = json_decode($tokenResponse, true);
if (!isset($tokenData['access_token'])) {
    $_SESSION['oauth_error'] = 'Google did not return an access token.';
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

$userInfoCurl = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
curl_setopt_array($userInfoCurl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenData['access_token']],
    CURLOPT_TIMEOUT => 30,
]);
$userInfoResponse = curl_exec($userInfoCurl);
$userInfoStatus = curl_getinfo($userInfoCurl, CURLINFO_HTTP_CODE);
curl_close($userInfoCurl);

if ($userInfoStatus >= 400 || $userInfoResponse === false) {
    $_SESSION['oauth_error'] = 'Google user info request failed.';
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

$userProfile = json_decode($userInfoResponse, true);
$email = trim((string) ($userProfile['email'] ?? ''));
$name = trim((string) ($userProfile['name'] ?? ''));

if ($email === '') {
    $_SESSION['oauth_error'] = 'Google returned no email address for this account.';
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

$result = handleSocialAuthentication('google', $email, $name);
if (!$result['success']) {
    $_SESSION['oauth_error'] = $result['message'];
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

unset($_SESSION['oauth_state'], $_SESSION['oauth_code_verifier']);
$next = $_SESSION['after_login'] ?? '/TMZ%20PODCAST/dashboard.php';
unset($_SESSION['after_login']);
header('Location: ' . $next);
exit;
