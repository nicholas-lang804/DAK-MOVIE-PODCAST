<?php
/**
 * THE DAK FILM PODCAST MEDIA - Cron Jobs
 * Handles scheduled tasks
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

// Get the requested task
$task = $_GET['task'] ?? '';

// Route the request
switch ($task) {
    case 'cleanup':
        cleanupOldData();
        break;
    case 'expire-ads':
        expireAdvertisements();
        break;
    case 'cleanup-tokens':
        cleanupPasswordTokens();
        break;
    case 'update-events':
        updateEventStatuses();
        break;
    case 'send-newsletter':
        sendNewsletter();
        break;
    default:
        echo "Invalid task. Available tasks: cleanup, expire-ads, cleanup-tokens, update-events, send-newsletter";
        break;
}

function cleanupOldData() {
    $logModel = new ActivityLog();
    $deleted = $logModel->deleteOldLogs(90); // Delete logs older than 90 days
    echo "Deleted $deleted old activity logs";
}

function expireAdvertisements() {
    $advertisementModel = new Advertisement();
    $expired = $advertisementModel->expireOutdated();
    echo "Expired $expired advertisements";
}

function cleanupPasswordTokens() {
    $passwordResetModel = new PasswordReset();
    $deleted = $passwordResetModel->cleanupExpired();
    echo "Deleted $deleted expired password reset tokens";
}

function updateEventStatuses() {
    $eventModel = new Event();
    $updated = $eventModel->updatePastEvents();
    echo "Updated $updated past events";
}

function sendNewsletter() {
    $newsletterModel = new NewsletterSubscriber();
    $subscribers = $newsletterModel->getActiveSubscribers();
    
    // This would typically send emails to subscribers
    // For now, just return the count
    echo "Would send newsletter to " . count($subscribers) . " subscribers";
}
