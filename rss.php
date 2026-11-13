<?php
/**
 * THE DAK FILM PODCAST MEDIA - RSS Feed Generator
 * Generates RSS feeds for episodes and articles
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

// Set XML header
header('Content-Type: application/rss+xml; charset=utf-8');

// Base URL
$baseUrl = 'http://localhost/TMZ%20PODCAST';

// Get feed type
$type = $_GET['type'] ?? 'episodes';

// Generate RSS feed based on type
switch ($type) {
    case 'episodes':
        generateEpisodeFeed($baseUrl);
        break;
    case 'articles':
        generateArticleFeed($baseUrl);
        break;
    case 'movies':
        generateMovieFeed($baseUrl);
        break;
    case 'all':
        generateAllFeed($baseUrl);
        break;
    default:
        generateEpisodeFeed($baseUrl);
        break;
}

function generateEpisodeFeed($baseUrl) {
    $episodeModel = new Episode();
    $episodes = $episodeModel->getPublished(50);
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    echo '  <channel>' . "\n";
    echo '    <title>THE DAK FILM PODCAST MEDIA - Episodes</title>' . "\n";
    echo '    <link>' . $baseUrl . '/episodes.php</link>' . "\n";
    echo '    <description>Latest podcast episodes from THE DAK FILM PODCAST MEDIA</description>' . "\n";
    echo '    <language>en-us</language>' . "\n";
    echo '    <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
    echo '    <atom:link href="' . $baseUrl . '/rss.php?type=episodes" rel="self" type="application/rss+xml" />' . "\n";
    
    foreach ($episodes as $episode) {
        echo '    <item>' . "\n";
        echo '      <title>' . htmlspecialchars($episode['title']) . '</title>' . "\n";
        echo '      <link>' . $baseUrl . '/episodes.php?slug=' . urlencode($episode['slug']) . '</link>' . "\n";
        echo '      <description>' . htmlspecialchars($episode['description'] ?? '') . '</description>' . "\n";
        echo '      <pubDate>' . date('r', strtotime($episode['published_at'])) . '</pubDate>' . "\n";
        echo '      <guid>' . $baseUrl . '/episodes.php?slug=' . urlencode($episode['slug']) . '</guid>' . "\n";
        
        if ($episode['audio_media_id']) {
            echo '      <enclosure url="' . $baseUrl . '/storage/uploads/' . htmlspecialchars($episode['audio_media_id']) . '" type="audio/mpeg" />' . "\n";
        }
        
        echo '    </item>' . "\n";
    }
    
    echo '  </channel>' . "\n";
    echo '</rss>' . "\n";
}

function generateArticleFeed($baseUrl) {
    $articleModel = new Article();
    $articles = $articleModel->getPublished(50);
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    echo '  <channel>' . "\n";
    echo '    <title>THE DAK FILM PODCAST MEDIA - Articles</title>' . "\n";
    echo '    <link>' . $baseUrl . '/news.php</link>' . "\n";
    echo '    <description>Latest entertainment articles from THE DAK FILM PODCAST MEDIA</description>' . "\n";
    echo '    <language>en-us</language>' . "\n";
    echo '    <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
    echo '    <atom:link href="' . $baseUrl . '/rss.php?type=articles" rel="self" type="application/rss+xml" />' . "\n";
    
    foreach ($articles as $article) {
        echo '    <item>' . "\n";
        echo '      <title>' . htmlspecialchars($article['title']) . '</title>' . "\n";
        echo '      <link>' . $baseUrl . '/news.php?slug=' . urlencode($article['slug']) . '</link>' . "\n";
        echo '      <description>' . htmlspecialchars($article['excerpt'] ?? '') . '</description>' . "\n";
        echo '      <pubDate>' . date('r', strtotime($article['published_at'])) . '</pubDate>' . "\n";
        echo '      <guid>' . $baseUrl . '/news.php?slug=' . urlencode($article['slug']) . '</guid>' . "\n";
        echo '    </item>' . "\n";
    }
    
    echo '  </channel>' . "\n";
    echo '</rss>' . "\n";
}

function generateMovieFeed($baseUrl) {
    $movieModel = new Movie();
    $movies = $movieModel->getPublished(50);
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    echo '  <channel>' . "\n";
    echo '    <title>THE DAK FILM PODCAST MEDIA - Movies</title>' . "\n";
    echo '    <link>' . $baseUrl . '/movies.php</link>' . "\n";
    echo '    <description>Latest movie reviews and updates from THE DAK FILM PODCAST MEDIA</description>' . "\n";
    echo '    <language>en-us</language>' . "\n";
    echo '    <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
    echo '    <atom:link href="' . $baseUrl . '/rss.php?type=movies" rel="self" type="application/rss+xml" />' . "\n";
    
    foreach ($movies as $movie) {
        echo '    <item>' . "\n";
        echo '      <title>' . htmlspecialchars($movie['title']) . '</title>' . "\n";
        echo '      <link>' . $baseUrl . '/movies.php?slug=' . urlencode($movie['slug']) . '</link>' . "\n";
        echo '      <description>' . htmlspecialchars($movie['description'] ?? '') . '</description>' . "\n";
        echo '      <pubDate>' . date('r', strtotime($movie['published_at'])) . '</pubDate>' . "\n";
        echo '      <guid>' . $baseUrl . '/movies.php?slug=' . urlencode($movie['slug']) . '</guid>' . "\n";
        echo '    </item>' . "\n";
    }
    
    echo '  </channel>' . "\n";
    echo '</rss>' . "\n";
}

function generateAllFeed($baseUrl) {
    // Generate a combined feed with all content types
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    echo '  <channel>' . "\n";
    echo '    <title>THE DAK FILM PODCAST MEDIA - All Content</title>' . "\n";
    echo '    <link>' . $baseUrl . '</link>' . "\n";
    echo '    <description>All latest content from THE DAK FILM PODCAST MEDIA</description>' . "\n";
    echo '    <language>en-us</language>' . "\n";
    echo '    <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
    echo '    <atom:link href="' . $baseUrl . '/rss.php?type=all" rel="self" type="application/rss+xml" />' . "\n";
    
    // Add episodes
    $episodeModel = new Episode();
    $episodes = $episodeModel->getPublished(20);
    foreach ($episodes as $episode) {
        echo '    <item>' . "\n";
        echo '      <title>[Episode] ' . htmlspecialchars($episode['title']) . '</title>' . "\n";
        echo '      <link>' . $baseUrl . '/episodes.php?slug=' . urlencode($episode['slug']) . '</link>' . "\n";
        echo '      <description>' . htmlspecialchars($episode['description'] ?? '') . '</description>' . "\n";
        echo '      <pubDate>' . date('r', strtotime($episode['published_at'])) . '</pubDate>' . "\n";
        echo '      <guid>' . $baseUrl . '/episodes.php?slug=' . urlencode($episode['slug']) . '</guid>' . "\n";
        echo '    </item>' . "\n";
    }
    
    // Add articles
    $articleModel = new Article();
    $articles = $articleModel->getPublished(20);
    foreach ($articles as $article) {
        echo '    <item>' . "\n";
        echo '      <title>[Article] ' . htmlspecialchars($article['title']) . '</title>' . "\n";
        echo '      <link>' . $baseUrl . '/news.php?slug=' . urlencode($article['slug']) . '</link>' . "\n";
        echo '      <description>' . htmlspecialchars($article['excerpt'] ?? '') . '</description>' . "\n";
        echo '      <pubDate>' . date('r', strtotime($article['published_at'])) . '</pubDate>' . "\n";
        echo '      <guid>' . $baseUrl . '/news.php?slug=' . urlencode($article['slug']) . '</guid>' . "\n";
        echo '    </item>' . "\n";
    }
    
    // Add movies
    $movieModel = new Movie();
    $movies = $movieModel->getPublished(20);
    foreach ($movies as $movie) {
        echo '    <item>' . "\n";
        echo '      <title>[Movie] ' . htmlspecialchars($movie['title']) . '</title>' . "\n";
        echo '      <link>' . $baseUrl . '/movies.php?slug=' . urlencode($movie['slug']) . '</link>' . "\n";
        echo '      <description>' . htmlspecialchars($movie['description'] ?? '') . '</description>' . "\n";
        echo '      <pubDate>' . date('r', strtotime($movie['published_at'])) . '</pubDate>' . "\n";
        echo '      <guid>' . $baseUrl . '/movies.php?slug=' . urlencode($movie['slug']) . '</guid>' . "\n";
        echo '    </item>' . "\n";
    }
    
    echo '  </channel>' . "\n";
    echo '</rss>' . "\n";
}
