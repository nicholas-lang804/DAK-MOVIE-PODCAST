<?php
/**
 * THE DAK FILM PODCAST MEDIA - Error Handler
 * Handles application errors and exceptions
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
    // Database connection failed, show error page
    showErrorPage('Database Connection Failed', 'Unable to connect to the database. Please check your configuration.', 500);
    exit;
}

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log error
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    
    // Don't execute PHP internal error handler
    return true;
});

// Set exception handler
set_exception_handler(function($exception) {
    // Log exception
    error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Show error page
    showErrorPage('Application Error', 'An unexpected error occurred. Please try again later.', 500);
    exit;
});

// Handle 404 errors
if (isset($_GET['error']) && $_GET['error'] == '404') {
    showErrorPage('Page Not Found', 'The page you are looking for does not exist.', 404);
    exit;
}

// Handle 403 errors
if (isset($_GET['error']) && $_GET['error'] == '403') {
    showErrorPage('Access Denied', 'You do not have permission to access this page.', 403);
    exit;
}

// Handle 500 errors
if (isset($_GET['error']) && $_GET['error'] == '500') {
    showErrorPage('Server Error', 'An internal server error occurred.', 500);
    exit;
}

function showErrorPage($title, $message, $code) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - THE DAK FILM PODCAST MEDIA</title>
        <link rel="stylesheet" href="/TMZ%20PODCAST/assets/css/modern.css">
    </head>
    <body>
        <div class="error-page">
            <div class="error-container">
                <div class="error-code"><?php echo $code; ?></div>
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <p><?php echo htmlspecialchars($message); ?></p>
                <div class="error-actions">
                    <a href="/TMZ%20PODCAST/" class="btn btn-primary">Go to Homepage</a>
                    <a href="/TMZ%20PODCAST/contact.php" class="btn btn-outline">Contact Support</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
