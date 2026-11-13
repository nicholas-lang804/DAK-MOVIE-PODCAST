<?php
/**
 * Admin Dashboard Router
 * Handles all admin-facing routes using MVC pattern
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
require_once __DIR__ . '/../app/Models/Star.php';
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
require_once __DIR__ . '/../app/Controllers/AdminController.php';

// Initialize database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

// Get the requested path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove the base path
$basePath = '/admin';
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');

// Route the request
switch ($path) {
    case '':
    case 'index.php':
    case 'dashboard.php':
        $tab = $_GET['tab'] ?? 'dashboard';
        $controller = new AdminController();
        $handlers = [
            'episodes' => 'manageEpisodes', 'articles' => 'manageArticles',
            'movies' => 'manageMovies', 'reviews' => 'manageReviews',
            'hosts' => 'manageHosts', 'guests' => 'manageGuests', 'stars' => 'manageStars', 'music' => 'manageMusic',
            'events' => 'manageEvents', 'categories' => 'manageCategories',
            'contacts' => 'manageContacts', 'newsletter' => 'manageNewsletter',
            'sponsors' => 'manageSponsors', 'advertisements' => 'manageAdvertisements',
            'media' => 'manageMedia', 'users' => 'manageUsers', 'settings' => 'manageSettings', 'finance' => 'manageFinancials', 'live' => 'manageLive'
        ];
        if ($tab === 'videos') $tab = 'media';
        if (isset($handlers[$tab])) {
            $controller->{$handlers[$tab]}();
        } else {
            $controller->dashboard();
        }
        break;
        
    case 'login.php':
        // Redirect to login if not logged in
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /admin/login.php');
            exit;
        }
        $controller = new AdminController();
        $controller->dashboard();
        break;
        
    case 'logout.php':
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
        header('Location: /admin/login.php');
        exit;
        break;
        
    default:
        $tab = $_GET['tab'] ?? 'dashboard';
        $controller = new AdminController();
        $handlers = [
            'episodes' => 'manageEpisodes', 'articles' => 'manageArticles',
            'movies' => 'manageMovies', 'reviews' => 'manageReviews',
            'hosts' => 'manageHosts', 'guests' => 'manageGuests', 'stars' => 'manageStars', 'music' => 'manageMusic',
            'events' => 'manageEvents', 'categories' => 'manageCategories',
            'contacts' => 'manageContacts', 'newsletter' => 'manageNewsletter',
            'sponsors' => 'manageSponsors', 'advertisements' => 'manageAdvertisements',
            'media' => 'manageMedia', 'users' => 'manageUsers', 'settings' => 'manageSettings', 'finance' => 'manageFinancials', 'live' => 'manageLive'
        ];
        if ($tab === 'videos') $tab = 'media';
        if (isset($handlers[$tab])) {
            $controller->{$handlers[$tab]}();
        } else {
            $controller->dashboard();
        }
        break;
}
