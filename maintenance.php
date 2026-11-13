<?php
/**
 * THE DAK FILM PODCAST MEDIA - Maintenance Mode
 * Shows maintenance page when site is under maintenance
 */

// Start session
session_start();

// Load configuration
require_once __DIR__ . '/config/database.php';

// Check if maintenance mode is enabled
$maintenanceMode = false;
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode'");
    $result = $stmt->fetch();
    $maintenanceMode = $result && $result['setting_value'] === '1';
} catch (Exception $e) {
    // If database is not available, assume maintenance mode is off
    $maintenanceMode = false;
}

// Check if user is admin (allow admin access during maintenance)
$isAdmin = isset($_SESSION['admin_id']);

// If maintenance mode is enabled and user is not admin, show maintenance page
if ($maintenanceMode && !$isAdmin) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Maintenance Mode - THE DAK FILM PODCAST MEDIA</title>
        <link rel="stylesheet" href="/assets/css/modern.css">
    </head>
    <body>
        <div class="maintenance-page">
            <div class="maintenance-container">
                <div class="maintenance-icon">🔧</div>
                <h1>Under Maintenance</h1>
                <p>We're currently performing scheduled maintenance on our website. Please check back soon!</p>
                <div class="maintenance-info">
                    <p>Expected completion: <strong>Within 2 hours</strong></p>
                    <p>We apologize for any inconvenience.</p>
                </div>
                <div class="maintenance-actions">
                    <a href="/" class="btn btn-primary">Refresh Page</a>
                    <a href="/contact.php" class="btn btn-outline">Contact Support</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// If maintenance mode is disabled or user is admin, redirect to main site
header('Location: /');
exit;
