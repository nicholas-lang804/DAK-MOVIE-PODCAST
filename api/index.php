<?php
/**
 * THE MOVIE ZONE PODCAST 256 - API Endpoints
 * Handles AJAX requests and API calls
 */

// Start session
session_start();

// Load configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Helpers/Validation.php';
require_once __DIR__ . '/app/Middleware/CSRF.php';
require_once __DIR__ . '/app/Middleware/Auth.php';

// Load models
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Models/Episode.php';
require_once __DIR__ . '/app/Models/Article.php';
require_once __DIR__ . '/app/Models/Movie.php';
require_once __DIR__ . '/app/Models/MediaAsset.php';
require_once __DIR__ . '/app/Models/Host.php';
require_once __DIR__ . '/app/Models/Guest.php';
require_once __DIR__ . '/app/Models/Event.php';
require_once __DIR__ . '/app/Models/Category.php';
require_once __DIR__ . '/app/Models/Comment.php';
require_once __DIR__ . '/app/Models/ContactMessage.php';
require_once __DIR__ . '/app/Models/NewsletterSubscriber.php';
require_once __DIR__ . '/app/Models/MovieReview.php';
require_once __DIR__ . '/app/Models/Sponsor.php';
require_once __DIR__ . '/app/Models/Advertisement.php';
require_once __DIR__ . '/app/Models/SiteSetting.php';
require_once __DIR__ . '/app/Models/ActivityLog.php';
require_once __DIR__ . '/app/Models/PasswordReset.php';

// Initialize database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Set JSON header
header('Content-Type: application/json');

// Get the requested endpoint
$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Route the request
switch ($endpoint) {
    case 'episodes':
        handleEpisodes($method);
        break;
    case 'articles':
        handleArticles($method);
        break;
    case 'movies':
        handleMovies($method);
        break;
    case 'events':
        handleEvents($method);
        break;
    case 'hosts':
        handleHosts($method);
        break;
    case 'comments':
        handleComments($method);
        break;
    case 'newsletter':
        handleNewsletter($method);
        break;
    case 'contact':
        handleContact($method);
        break;
    case 'search':
        handleSearch($method);
        break;
    default:
        echo json_encode(['error' => 'Invalid endpoint']);
        break;
}

function handleEpisodes($method) {
    $episodeModel = new Episode();
    
    if ($method === 'GET') {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $category = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        
        if ($search) {
            $episodes = $episodeModel->search($search, $limit, $offset);
        } elseif ($category) {
            $episodes = $episodeModel->getByCategory($category, $limit, $offset);
        } else {
            $episodes = $episodeModel->getPublished($limit, $offset);
        }
        
        echo json_encode(['success' => true, 'data' => $episodes]);
    } elseif ($method === 'POST') {
        // Create new episode (admin only)
        if (!isset($_SESSION['admin_id'])) {
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $episodeModel->create($data);
        
        if ($result) {
            echo json_encode(['success' => true, 'id' => $result]);
        } else {
            echo json_encode(['error' => 'Failed to create episode']);
        }
    }
}

function handleArticles($method) {
    $articleModel = new Article();
    
    if ($method === 'GET') {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $category = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        
        if ($search) {
            $articles = $articleModel->search($search, $limit, $offset);
        } elseif ($category) {
            $articles = $articleModel->getByCategory($category, $limit, $offset);
        } else {
            $articles = $articleModel->getPublished($limit, $offset);
        }
        
        echo json_encode(['success' => true, 'data' => $articles]);
    } elseif ($method === 'POST') {
        // Create new article (admin only)
        if (!isset($_SESSION['admin_id'])) {
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $articleModel->create($data);
        
        if ($result) {
            echo json_encode(['success' => true, 'id' => $result]);
        } else {
            echo json_encode(['error' => 'Failed to create article']);
        }
    }
}

function handleMovies($method) {
    $movieModel = new Movie();
    
    if ($method === 'GET') {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $genre = $_GET['genre'] ?? null;
        $search = $_GET['search'] ?? null;
        
        if ($search) {
            $movies = $movieModel->search($search, $limit, $offset);
        } elseif ($genre) {
            $movies = $movieModel->getByGenre($genre, $limit, $offset);
        } else {
            $movies = $movieModel->getPublished($limit, $offset);
        }
        
        echo json_encode(['success' => true, 'data' => $movies]);
    } elseif ($method === 'POST') {
        // Create new movie (admin only)
        if (!isset($_SESSION['admin_id'])) {
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $movieModel->create($data);
        
        if ($result) {
            echo json_encode(['success' => true, 'id' => $result]);
        } else {
            echo json_encode(['error' => 'Failed to create movie']);
        }
    }
}

function handleEvents($method) {
    $eventModel = new Event();
    
    if ($method === 'GET') {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $upcoming = $_GET['upcoming'] ?? false;
        
        if ($upcoming) {
            $events = $eventModel->getUpcoming($limit);
        } else {
            $events = $eventModel->getAll($limit, $offset);
        }
        
        echo json_encode(['success' => true, 'data' => $events]);
    } elseif ($method === 'POST') {
        // Create new event (admin only)
        if (!isset($_SESSION['admin_id'])) {
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $eventModel->create($data);
        
        if ($result) {
            echo json_encode(['success' => true, 'id' => $result]);
        } else {
            echo json_encode(['error' => 'Failed to create event']);
        }
    }
}

function handleHosts($method) {
    $hostModel = new Host();
    
    if ($method === 'GET') {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $hosts = $hostModel->getAll($limit, $offset);
        
        echo json_encode(['success' => true, 'data' => $hosts]);
    } elseif ($method === 'POST') {
        // Create new host (admin only)
        if (!isset($_SESSION['admin_id'])) {
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $hostModel->create($data);
        
        if ($result) {
            echo json_encode(['success' => true, 'id' => $result]);
        } else {
            echo json_encode(['error' => 'Failed to create host']);
        }
    }
}

function handleComments($method) {
    $commentModel = new Comment();
    
    if ($method === 'GET') {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $status = $_GET['status'] ?? 'approved';
        $contentType = $_GET['content_type'] ?? null;
        $contentId = $_GET['content_id'] ?? null;
        
        if ($contentType && $contentId) {
            $comments = $commentModel->getByContent($contentType, $contentId, $status);
        } else {
            $comments = $commentModel->getAll($limit, $offset, $status);
        }
        
        echo json_encode(['success' => true, 'data' => $comments]);
    } elseif ($method === 'POST') {
        // Create new comment
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $commentModel->create($data);
        
        if ($result) {
            echo json_encode(['success' => true, 'id' => $result]);
        } else {
            echo json_encode(['error' => 'Failed to create comment']);
        }
    }
}

function handleNewsletter($method) {
    $newsletterModel = new NewsletterSubscriber();
    
    if ($method === 'GET') {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $subscribers = $newsletterModel->getAll($limit, $offset);
        
        echo json_encode(['success' => true, 'data' => $subscribers]);
    } elseif ($method === 'POST') {
        // Subscribe to newsletter
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        
        if (!Validation::validateEmail($email)) {
            echo json_encode(['error' => 'Invalid email address']);
            return;
        }
        
        // Check if already subscribed
        $existing = $newsletterModel->findByEmail($email);
        if ($existing) {
            echo json_encode(['error' => 'Email already subscribed']);
            return;
        }
        
        $result = $newsletterModel->create($email);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Subscription successful']);
        } else {
            echo json_encode(['error' => 'Subscription failed']);
        }
    }
}

function handleContact($method) {
    $contactModel = new ContactMessage();
    
    if ($method === 'GET') {
        // Admin only
        if (!isset($_SESSION['admin_id'])) {
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $messages = $contactModel->getAll($limit, $offset);
        
        echo json_encode(['success' => true, 'data' => $messages]);
    } elseif ($method === 'POST') {
        // Submit contact form
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }
        
        if (!Validation::validateEmail($data['email'])) {
            echo json_encode(['error' => 'Invalid email address']);
            return;
        }
        
        $result = $contactModel->create($data);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
        } else {
            echo json_encode(['error' => 'Failed to send message']);
        }
    }
}

function handleSearch($method) {
    if ($method !== 'GET') {
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'all';
    
    if (empty($query)) {
        echo json_encode(['error' => 'Search query required']);
        return;
    }
    
    $results = [];
    
    // Search episodes
    if ($type === 'all' || $type === 'episodes') {
        $episodeModel = new Episode();
        $episodes = $episodeModel->search($query, 5);
        $results['episodes'] = $episodes;
    }
    
    // Search articles
    if ($type === 'all' || $type === 'articles') {
        $articleModel = new Article();
        $articles = $articleModel->search($query, 5);
        $results['articles'] = $articles;
    }
    
    // Search movies
    if ($type === 'all' || $type === 'movies') {
        $movieModel = new Movie();
        $movies = $movieModel->search($query, 5);
        $results['movies'] = $movies;
    }
    
    echo json_encode(['success' => true, 'data' => $results]);
}
