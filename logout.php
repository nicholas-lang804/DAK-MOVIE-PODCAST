<?php
/**
 * Logout Handler
 * Handles user logout
 */

// Start session
session_start();

// Clear session
session_unset();
session_destroy();

// Redirect to homepage
header('Location: /TMZ%20PODCAST/');
exit;

