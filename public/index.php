<?php
/**
 * Public Website Router
 * Handles all public-facing routes using MVC pattern
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
require_once __DIR__ . '/../app/Controllers/HomeController.php';
require_once __DIR__ . '/../app/Controllers/EpisodeController.php';
require_once __DIR__ . '/../app/Controllers/ArticleController.php';
require_once __DIR__ . '/../app/Controllers/MovieController.php';
require_once __DIR__ . '/../app/Controllers/EventController.php';
require_once __DIR__ . '/../app/Controllers/HostController.php';
require_once __DIR__ . '/../app/Controllers/StarController.php';
require_once __DIR__ . '/../app/Controllers/SiteController.php';
require_once __DIR__ . '/../app/Controllers/MusicController.php';
require_once __DIR__ . '/../app/Controllers/VideoController.php';
require_once __DIR__ . '/../app/Controllers/LiveController.php';

// Initialize database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get the requested path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$query = $_GET;

// Remove the base path and the public entry script portion from the requested URL.
foreach (['/public', '/', '/public', '/'] as $basePath) {
    if (strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
        break;
    }
}

if ($path === 'public' || $path === 'public/index.php') {
    $path = '';
}

$path = trim($path, '/');

// Route the request
switch ($path) {
    case '':
    case 'index.php':
        $controller = new HomeController();
        $controller->index();
        break;
        
    case 'episodes.php':
        if (!empty($query['slug'])) {
            (new EpisodeController())->show($query['slug']);
            break;
        }
        $controller = new EpisodeController();
        $controller->index();
        break;
        
    case 'news.php':
        if (!empty($query['slug'])) {
            (new ArticleController())->show($query['slug']);
            break;
        }
        $controller = new ArticleController();
        $controller->index();
        break;
        
    case 'movies.php':
        if (!empty($query['slug'])) {
            (new MovieController())->show($query['slug']);
            break;
        }
        $controller = new MovieController();
        $controller->index();
        break;
        
    case 'events.php':
        if (!empty($query['slug'])) {
            (new EventController())->show($query['slug']);
            break;
        }
        $controller = new EventController();
        $controller->index();
        break;
        
    case 'hosts.php':
        if (!empty($query['slug'])) {
            (new HostController())->show($query['slug']);
            break;
        }
        $controller = new HostController();
        $controller->index();
        break;

    case 'stars.php':
        if (!empty($query['slug'])) {
            (new StarController())->show($query['slug']);
            break;
        }
        (new StarController())->index();
        break;

    case 'music.php':
        (new MusicController())->index();
        break;

    case 'search.php':
        (new SiteController())->search();
        break;

    case 'my-list.php':
        (new SiteController())->myList();
        break;

    case 'privacy.php':
    case 'terms.php':
    case 'cookies.php':
        (new SiteController())->legal(basename($path, '.php'));
        break;

    case 'videos.php':
        (new VideoController())->index();
        break;
    case 'live.php': (new LiveController())->show(); break;
        
    case 'about.php':
        $controller = new HomeController();
        $controller->about();
        break;
        
    case 'contact.php':
        $controller = new HomeController();
        $controller->contact();
        break;
        
    case 'subscribe.php':
        $controller = new HomeController();
        $controller->subscribe();
        break;
        
    default:
        http_response_code(404);
        $controller = new HomeController();
        $controller->notFound();
        break;
}
