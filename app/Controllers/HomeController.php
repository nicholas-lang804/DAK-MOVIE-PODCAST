<?php
/**
 * Home Controller
 * Handles homepage and public landing pages
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/Episode.php';
require_once __DIR__ . '/../Models/Article.php';
require_once __DIR__ . '/../Models/Movie.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Host.php';
require_once __DIR__ . '/../Models/Advertisement.php';
require_once __DIR__ . '/../Models/NewsletterSubscriber.php';
require_once __DIR__ . '/../Models/MediaAsset.php';
require_once __DIR__ . '/../Models/LivePodcast.php';
require_once __DIR__ . '/../Models/SiteNotification.php';

class HomeController extends Controller {
    private $episodeModel;
    private $articleModel;
    private $movieModel;
    private $eventModel;
    private $hostModel;
    private $advertisementModel;
    private $newsletterModel;
    private $mediaModel;
    private $liveModel;

    public function __construct() {
        parent::__construct();
        $this->episodeModel = new Episode();
        $this->articleModel = new Article();
        $this->movieModel = new Movie();
        $this->eventModel = new Event();
        $this->hostModel = new Host();
        $this->advertisementModel = new Advertisement();
        $this->newsletterModel = new NewsletterSubscriber();
        $this->mediaModel = new MediaAsset();
        $this->liveModel = new LivePodcast();
    }

    /**
     * Display homepage
     */
    public function index() {
        // Get featured content
        $featuredEpisodes = $this->episodeModel->getFeatured(1);
        $latestEpisodes = $this->episodeModel->getPublished(3);
        $featuredArticles = $this->articleModel->getFeatured(3);
        $featuredShowcaseMovies = $this->movieModel->getPublished(12, 0);
        $latestMovies = $this->movieModel->getLatest(8);
        $upcomingEvents = $this->eventModel->getUpcoming(3);
        $hosts = $this->hostModel->getAll();
        
        // Get advertisements
        $homepageAds = $this->advertisementModel->getActiveByPlacement('homepage');
        $featuredVideos = $this->mediaModel->getPublicVideos(4, 0, '', 'homepage');
        $currentLive = $this->liveModel->getCurrent();
        
        // Get statistics
        $episodeCount = $this->episodeModel->getPublishedCount();
        $articleCount = $this->articleModel->getPublishedCount();
        $movieCount = $this->movieModel->getPublishedCount();
        $subscriberCount = $this->newsletterModel->getActiveCount();

        $data = [
            'title' => 'Home',
            'description' => 'Discover the latest films, exclusive live podcasts, and entertainment news.',
            'featuredEpisodes' => $featuredEpisodes,
            'latestEpisodes' => $latestEpisodes,
            'featuredArticles' => $featuredArticles,
            'featuredShowcaseMovies' => $featuredShowcaseMovies,
            'latestMovies' => $latestMovies,
            'upcomingEvents' => $upcomingEvents,
            'hosts' => $hosts,
            'homepageAds' => $homepageAds,
            'featuredVideos' => $featuredVideos,
            'currentLive' => $currentLive,
            'statistics' => [
                'episodes' => $episodeCount,
                'articles' => $articleCount,
                'movies' => $movieCount,
                'subscribers' => $subscriberCount
            ]
        ];

        $this->render('home', $data);
    }

    /**
     * Handle newsletter subscription
     */
    public function subscribe() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/TMZ%20PODCAST/');
        }

        $email = trim($_POST['email'] ?? '');
        
        if (!Validation::validateEmail($email)) {
            $_SESSION['newsletter_error'] = 'Please enter a valid email address';
            $this->redirect('/TMZ%20PODCAST/#newsletter');
        }

        // Check if already subscribed
        $existing = $this->newsletterModel->findByEmail($email);
        if ($existing) {
            $_SESSION['newsletter_error'] = 'This email is already subscribed';
            $this->redirect('/TMZ%20PODCAST/#newsletter');
        }

        // Create subscription
        $result = $this->newsletterModel->create($email);
        
        if ($result) {
            $_SESSION['newsletter_success'] = 'Thank you for subscribing! Please check your email to confirm.';
        } else {
            $_SESSION['newsletter_error'] = 'Subscription failed. Please try again.';
        }

        $this->redirect('/TMZ%20PODCAST/#newsletter');
    }

    /**
     * Handle contact form submission
     */
    public function contact() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->render('contact', [
                'title' => 'Contact Us',
                'description' => 'Contact THE DAK FILM PODCAST MEDIA for business, interview, sponsorship and collaboration enquiries.'
            ]);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $enquiryType = $_POST['enquiry_type'] ?? 'general';

        // Validate
        if (empty($name) || empty($email) || empty($message)) {
            $_SESSION['contact_error'] = 'Please fill in all required fields';
            $this->redirect('/TMZ%20PODCAST/contact.php');
        }

        if (!Validation::validateEmail($email)) {
            $_SESSION['contact_error'] = 'Please enter a valid email address';
            $this->redirect('/TMZ%20PODCAST/contact.php');
        }

        // Save to database
        $contactModel = new ContactMessage();
        $result = $contactModel->create([
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'enquiry_type' => $enquiryType
        ]);

        if ($result) {
            try {
                (new SiteNotification())->create(
                    'admin',
                    null,
                    'contact_message',
                    'New website contact message',
                    $name . ' · ' . ($subject !== '' ? $subject : 'General inquiry') . ': ' . substr($message, 0, 600),
                    '/TMZ%20PODCAST/admin/dashboard.php?tab=contacts'
                );
            } catch (Throwable $error) {
                error_log('Contact-message notification could not be saved: ' . $error->getMessage());
            }
            $_SESSION['contact_success'] = 'Your message has been sent successfully. We will get back to you soon.';
        } else {
            $_SESSION['contact_error'] = 'Failed to send message. Please try again.';
        }

        $this->redirect('/TMZ%20PODCAST/contact.php');
    }

    /**
     * Display about page
     */
    public function about() {
        $data = [
            'title' => 'About Us',
            'description' => 'Learn more about THE DAK FILM PODCAST MEDIA.'
        ];
        $this->render('about', $data, 'main');
    }

    /**
     * Display 404 page
     */
    public function notFound() {
        $data = [
            'title' => 'Page Not Found'
        ];
        $this->render('404', $data, 'main');
    }
}
?>
