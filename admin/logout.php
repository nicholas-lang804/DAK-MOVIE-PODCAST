<?php
/**
 * Admin Logout Handler
 * Handles admin logout
 */

// Start session
session_start();

// Clear admin session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_role']);

// Redirect to admin login
header('Location: /TMZ%20PODCAST/admin/login.php');
exit;

