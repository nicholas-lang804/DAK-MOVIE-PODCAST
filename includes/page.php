<?php
/**
 * Helper for standalone pages (checkout, my tickets, ticket view)
 * so they share the public navigation, footer and styling.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../app/Helpers/Ads.php';
require_once __DIR__ . '/../app/Helpers/Validation.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Models/SiteSetting.php';

function tmzH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Render $content inside the public layout. */
function tmzRenderPage($title, $content, $description = null) {
    $settings = [];
    foreach ((new SiteSetting())->getAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $contactEmail = $settings['contact_email'] ?? 'info@tmzpodcast.com';
    $contactPhone = $settings['contact_phone'] ?? '+254700000000';
    $contactLocation = $settings['site_location'] ?? 'Kampala, Uganda';
    $layout = __DIR__ . '/../resources/views/layouts/main.php';
    include $layout;
}

/** Send a logged-out visitor to login and bring them back afterwards. */
function tmzRequireCustomerLogin() {
    if (empty($_SESSION['user_id'])) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php');
        exit;
    }
}
