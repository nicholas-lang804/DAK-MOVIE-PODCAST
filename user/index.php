<?php
/**
 * User Dashboard Router
 * Handles all user-facing dashboard routes
 */

// Start session
session_start();

// Load configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Validation.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';

// Load models
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Episode.php';
require_once __DIR__ . '/../app/Models/Article.php';
require_once __DIR__ . '/../app/Models/Movie.php';
require_once __DIR__ . '/../app/Models/MediaAsset.php';
require_once __DIR__ . '/../app/Models/Host.php';
require_once __DIR__ . '/../app/Models/Guest.php';
require_once __DIR__ . '/../app/Models/Event.php';
require_once __DIR__ . '/../app/Models/Category.php';
require_once __DIR__ . '/../app/Models/Comment.php';
require_once __DIR__ . '/../app/Models/ContactMessage.php';
require_once __DIR__ . '/../app/Models/NewsletterSubscriber.php';
require_once __DIR__ . '/../app/Models/MovieReview.php';
require_once __DIR__ . '/../app/Models/Sponsor.php';
require_once __DIR__ . '/../app/Models/Advertisement.php';
require_once __DIR__ . '/../app/Models/SiteSetting.php';
require_once __DIR__ . '/../app/Models/ActivityLog.php';
require_once __DIR__ . '/../app/Models/PasswordReset.php';

// Load controllers
require_once __DIR__ . '/../app/Controllers/Controller.php';
require_once __DIR__ . '/../app/Controllers/UserController.php';

// Initialize database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}

// Get the requested path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove the base path
$basePath = '/TMZ%20PODCAST/user';
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');

// Route the request
switch ($path) {
    case '':
    case 'index.php':
    case 'dashboard.php':
        $controller = new UserController();
        $controller->dashboard();
        break;
        
    case 'profile.php':
        $controller = new UserController();
        $controller->profile();
        break;
        
    case 'settings.php':
        $controller = new UserController();
        $controller->settings();
        break;
        
    case 'logout.php':
        unset($_SESSION['user_id']);
        unset($_SESSION['user_username']);
        unset($_SESSION['user_email']);
        unset($_SESSION['user_role']);
        header('Location: /TMZ%20PODCAST/');
        exit;
        break;
        
    default:
        // Handle tab-based routing
        $tab = $_GET['tab'] ?? 'dashboard';
        
        $controller = new UserController();
        $controller->dashboard();
        break;
}
