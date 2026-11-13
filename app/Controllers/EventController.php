<?php
/**
 * Event Controller
 * Handles event pages
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/ContactMessage.php';
require_once __DIR__ . '/../Models/SiteSetting.php';
require_once __DIR__ . '/../Models/SiteNotification.php';

class EventController extends Controller {
    private $eventModel;
    private $contactMessageModel;
    private $settingModel;

    public function __construct() {
        parent::__construct();
        $this->eventModel = new Event();
        $this->contactMessageModel = new ContactMessage();
        $this->settingModel = new SiteSetting();
    }

    /**
     * Display events page
     */
    public function index() {
        $upcomingEvents = $this->eventModel->getUpcoming(10);
        $pastEvents = $this->eventModel->getPast(10);

        $data = [
            'title' => 'Events',
            'description' => 'Upcoming and past events from THE DAK FILM PODCAST MEDIA.',
            'upcomingEvents' => $upcomingEvents,
            'pastEvents' => $pastEvents
        ];

        $this->render('events', $data);
    }

    /**
     * Display single event
     * @param string $slug
     */
    public function show($slug) {
        $event = $this->eventModel->findBySlug($slug);
        
        if (!$event || $event['status'] !== 'published') {
            http_response_code(404);
            $this->render('404', ['title' => 'Event Not Found']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->sendEventEnquiry($event);
        }

        $data = [
            'title' => $event['title'],
            'description' => $event['description'] ?? 'Event details.',
            'event' => $event,
            'ticketTypes' => $this->eventModel->getTicketTypes($event['id']),
            'discountRules' => $this->eventModel->getDiscountRules($event['id'], 'online', true),
            'isPast' => !empty($event['event_date']) && strtotime($event['event_date']) < time(),
            'isCustomer' => !empty($_SESSION['user_id']),
            'eventEnquiryAvailable' => Validation::validateEmail($this->settingModel->get('contact_email') ?? '')
        ];

        $this->render('event-detail', $data);
    }

    private function sendEventEnquiry($event) {
        CSRF::requireValidToken();

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($name === '' || strlen($name) > 640 || !Validation::validateEmail($email) || strlen($email) > 254 || $message === '' || strlen($message) > 20000) {
            $_SESSION['event_contact_error'] = 'Please enter your name, a valid email address, and a message of no more than 5,000 characters.';
            $this->redirect('/TMZ%20PODCAST/events.php?slug=' . rawurlencode($event['slug']) . '#event-contact');
        }

        $messageId = $this->contactMessageModel->create([
            'name' => $name,
            'email' => $email,
            'subject' => 'Event enquiry',
            'message' => 'Event: ' . $event['title'] . "\n\nMessage:\n" . $message,
            'enquiry_type' => 'general'
        ]);

        if (!$messageId) {
            $_SESSION['event_contact_error'] = 'We could not save your enquiry. Please try again.';
            $this->redirect('/TMZ%20PODCAST/events.php?slug=' . rawurlencode($event['slug']) . '#event-contact');
        }
        try {
            (new SiteNotification())->create(
                'admin',
                null,
                'contact_message',
                'New event enquiry',
                $name . ' · ' . $event['title'] . ': ' . substr($message, 0, 600),
                '/TMZ%20PODCAST/admin/dashboard.php?tab=contacts'
            );
        } catch (Throwable $error) {
            error_log('Event-enquiry notification could not be saved: ' . $error->getMessage());
        }

        $recipient = trim((string) $this->settingModel->get('contact_email'));
        if (!Validation::validateEmail($recipient)) {
            error_log('Event enquiry ' . $messageId . ' saved but no valid site contact email is configured.');
            $_SESSION['event_contact_error'] = 'Your enquiry was saved, but email delivery is unavailable right now. Please try again later.';
            $this->redirect('/TMZ%20PODCAST/events.php?slug=' . rawurlencode($event['slug']) . '#event-contact');
        }

        $subject = 'New event enquiry';
        $body = "Event: " . $event['title'] . "\n"
            . "From: " . $name . " <" . $email . ">\n\n"
            . $message;
        $headers = "Reply-To: " . $email . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";

        if (!@mail($recipient, $subject, $body, $headers)) {
            error_log('Event enquiry ' . $messageId . ' was saved, but email delivery failed.');
            $_SESSION['event_contact_error'] = 'Your enquiry was saved, but email delivery failed. Please try again later.';
            $this->redirect('/TMZ%20PODCAST/events.php?slug=' . rawurlencode($event['slug']) . '#event-contact');
        }

        $_SESSION['event_contact_success'] = 'Your message has been sent to the event team.';
        $this->redirect('/TMZ%20PODCAST/events.php?slug=' . rawurlencode($event['slug']) . '#event-contact');
    }
}
?>
