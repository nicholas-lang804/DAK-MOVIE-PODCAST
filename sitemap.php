<?php
/**
 * THE DAK FILM PODCAST MEDIA - Sitemap Generator
 * Generates XML sitemap for SEO
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
header('Content-Type: application/xml; charset=utf-8');

// Base URL
$baseUrl = 'http://localhost/TMZ%20PODCAST';

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Add homepage
addUrl($baseUrl . '/', '1.0', 'daily', 'now');

// Add static pages
addUrl($baseUrl . '/about.php', '0.8', 'monthly', 'now');
addUrl($baseUrl . '/contact.php', '0.8', 'monthly', 'now');
addUrl($baseUrl . '/subscribe.php', '0.8', 'monthly', 'now');

// Add episodes
$episodeModel = new Episode();
$episodes = $episodeModel->getPublished(1000);
foreach ($episodes as $episode) {
    $url = $baseUrl . '/episodes.php?slug=' . urlencode($episode['slug']);
    $lastmod = $episode['updated_at'] ?? $episode['published_at'] ?? 'now';
    addUrl($url, '0.7', 'weekly', $lastmod);
}

// Add articles
$articleModel = new Article();
$articles = $articleModel->getPublished(1000);
foreach ($articles as $article) {
    $url = $baseUrl . '/news.php?slug=' . urlencode($article['slug']);
    $lastmod = $article['updated_at'] ?? $article['published_at'] ?? 'now';
    addUrl($url, '0.7', 'weekly', $lastmod);
}

// Add movies
$movieModel = new Movie();
$movies = $movieModel->getPublished(1000);
foreach ($movies as $movie) {
    $url = $baseUrl . '/movies.php?slug=' . urlencode($movie['slug']);
    $lastmod = $movie['updated_at'] ?? $movie['published_at'] ?? 'now';
    addUrl($url, '0.7', 'weekly', $lastmod);
}

// Add events
$eventModel = new Event();
$events = $eventModel->getAll(1000);
foreach ($events as $event) {
    $url = $baseUrl . '/events.php?slug=' . urlencode($event['slug']);
    $lastmod = $event['updated_at'] ?? $event['event_date'] ?? 'now';
    addUrl($url, '0.6', 'weekly', $lastmod);
}

// Add hosts
$hostModel = new Host();
$hosts = $hostModel->getAll();
foreach ($hosts as $host) {
    $url = $baseUrl . '/hosts.php?slug=' . urlencode($host['slug']);
    $lastmod = $host['updated_at'] ?? 'now';
    addUrl($url, '0.6', 'monthly', $lastmod);
}

// Close XML
echo '</urlset>' . "\n";

function addUrl($loc, $priority, $changefreq, $lastmod) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($loc) . '</loc>' . "\n";
    echo '    <lastmod>' . date('Y-m-d', strtotime($lastmod)) . '</lastmod>' . "\n";
    echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
    echo '    <priority>' . $priority . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
