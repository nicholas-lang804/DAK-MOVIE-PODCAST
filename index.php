<?php
/**
 * THE DAK FILM PODCAST MEDIA - Main Entry Point
 * Redirects to the appropriate router based on user type
 */

// Start session
session_start();

// Check if user is admin
if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /user/index.php');
    exit;
}

// Redirect to public website
header('Location: /public/index.php');
exit;

