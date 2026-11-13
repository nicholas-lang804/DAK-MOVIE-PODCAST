<?php
/**
 * User Controller
 * Handles user dashboard and profile management
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Episode.php';
require_once __DIR__ . '/../Models/Article.php';
require_once __DIR__ . '/../Models/Movie.php';
require_once __DIR__ . '/../Models/MediaAsset.php';
require_once __DIR__ . '/../Models/Host.php';
require_once __DIR__ . '/../Models/Guest.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Comment.php';
require_once __DIR__ . '/../Models/ContactMessage.php';
require_once __DIR__ . '/../Models/NewsletterSubscriber.php';
require_once __DIR__ . '/../Models/MovieReview.php';
require_once __DIR__ . '/../Models/Sponsor.php';
require_once __DIR__ . '/../Models/Advertisement.php';
require_once __DIR__ . '/../Models/SiteSetting.php';
require_once __DIR__ . '/../Models/ActivityLog.php';
require_once __DIR__ . '/../Models/PasswordReset.php';
require_once __DIR__ . '/../Models/EventPublishFee.php';
require_once __DIR__ . '/../Models/Payment.php';
require_once __DIR__ . '/../Services/PesapalGateway.php';

class UserController extends Controller {
    private $userModel;
    private $episodeModel;
    private $articleModel;
    private $movieModel;
    private $mediaModel;
    private $hostModel;
    private $guestModel;
    private $eventModel;
    private $categoryModel;
    private $commentModel;
    private $contactModel;
    private $newsletterModel;
    private $reviewModel;
    private $sponsorModel;
    private $advertisementModel;
    private $settingModel;
    private $logModel;

    public function __construct() {
        parent::__construct();
        $this->userModel = new User();
        $this->episodeModel = new Episode();
        $this->articleModel = new Article();
        $this->movieModel = new Movie();
        $this->mediaModel = new MediaAsset();
        $this->hostModel = new Host();
        $this->guestModel = new Guest();
        $this->eventModel = new Event();
        $this->categoryModel = new Category();
        $this->commentModel = new Comment();
        $this->contactModel = new ContactMessage();
        $this->newsletterModel = new NewsletterSubscriber();
        $this->reviewModel = new MovieReview();
        $this->sponsorModel = new Sponsor();
        $this->advertisementModel = new Advertisement();
        $this->settingModel = new SiteSetting();
        $this->logModel = new ActivityLog();
    }

    /**
     * Display user dashboard
     */
    public function dashboard() {
        AuthMiddleware::requireLogin();

        $userId = $_SESSION['user_id'];

        // Organiser event management POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['org_action'])) {
            $this->handleOrganiserPost($userId);
        }

        $tab = $_GET['tab'] ?? 'dashboard';
        
        $data = [
            'title' => 'User Dashboard',
            'activeTab' => $tab,
            'user' => $this->userModel->findById($userId)
        ];

        // Load data based on tab
        switch ($tab) {
            case 'episodes':
                $data['episodes'] = $this->episodeModel->getPublished(50);
                break;
            case 'articles':
                $data['articles'] = $this->articleModel->getPublished(50);
                break;
            case 'movies':
                $data['movies'] = $this->movieModel->getPublished(50);
                break;
            case 'media':
                $data['media'] = $this->mediaModel->getAll(50);
                break;
            case 'events':
                $data['events'] = $this->eventModel->getUpcoming(50);
                $data['pastEvents'] = $this->eventModel->getPast(10);
                break;
            case 'my-events':
                $data['myEvents'] = $this->eventModel->getByOrganiser($userId);
                $data['feeModel'] = new EventPublishFee();
                break;
            case 'event-create':
            case 'event-edit':
                $editId = (int)($_GET['id'] ?? 0);
                $data['editEvent'] = $editId ? $this->eventModel->findByIdForOrganiser($editId, $userId) : null;
                if ($editId && !$data['editEvent']) { $tab = 'my-events'; break; }
                $data['editTickets'] = $data['editEvent'] ? $this->eventModel->getTicketTypes($data['editEvent']['id'], false) : [];
                $data['feeSettings'] = ['enabled' => EventPublishFee::feeEnabled(), 'amount' => EventPublishFee::feeAmount(), 'currency' => EventPublishFee::feeCurrency()];
                $data['eventFee'] = $data['editEvent'] ? (new EventPublishFee())->forEvent($data['editEvent']['id']) : null;
                break;
            case 'event-fees':
                $feeModel = new EventPublishFee();
                $data['feeEvents'] = [];
                foreach ($this->eventModel->getByOrganiser($userId) as $ev) {
                    $ev['fee'] = $feeModel->forEvent($ev['id']);
                    $ev['fee_ok'] = $feeModel->canPublish($ev['id'])['ok'];
                    $data['feeEvents'][] = $ev;
                }
                break;
            case 'payment-history':
                $s = $this->pdo->prepare("SELECT p.*, e.title AS event_title FROM payments p
                    LEFT JOIN event_publish_fees f ON f.id = p.event_fee_id
                    LEFT JOIN events e ON e.id = f.event_id
                    WHERE p.user_id = ? AND p.event_fee_id IS NOT NULL
                    ORDER BY p.id DESC LIMIT 100");
                $s->execute([$userId]);
                $data['feePayments'] = $s->fetchAll();
                break;
            case 'event-reports':
                $data['reportEvents'] = $this->eventModel->getByOrganiser($userId);
                break;
            case 'tickets':
                $data['tickets'] = $this->eventModel->getUserTickets($userId);
                break;
            case 'hosts':
                $data['hosts'] = $this->hostModel->getAll();
                $data['guests'] = $this->guestModel->getAll();
                break;
            case 'comments':
                $data['comments'] = $this->commentModel->getByUser($userId, 50);
                break;
            case 'settings':
                $data['settings'] = $this->settingModel->getAll();
                break;
            default:
                // Dashboard overview
                $data['stats'] = [
                    'episodes' => $this->episodeModel->getPublishedCount(),
                    'articles' => $this->articleModel->getPublishedCount(),
                    'movies' => $this->movieModel->getPublishedCount(),
                    'events' => $this->eventModel->getUpcomingCount(),
                    'comments' => $this->commentModel->getByUserCount($userId),
                    'videos' => $this->mediaModel->getCountByType('video')
                ];
                
                // Get recent activity
                $data['recentEpisodes'] = $this->episodeModel->getPublished(5);
                $data['recentArticles'] = $this->articleModel->getPublished(5);
                $data['recentMovies'] = $this->movieModel->getLatest(6);
                $data['recentVideos'] = $this->mediaModel->getPublicVideos(6, 0, '', 'all');
                $data['upcomingEvents'] = $this->eventModel->getUpcoming(5);
                $data['myTicketCount'] = count($this->eventModel->getUserTickets($userId));
                break;
        }

        $this->render('user/dashboard', $data, 'main');
    }

    /**
     * Organiser event management: save draft/publish, upload poster, pay publishing fee.
     */
    private function handleOrganiserPost($userId) {
        $token = $_POST['csrf_token'] ?? '';
        if (!CSRF::validateToken($token)) { $_SESSION['error'] = 'Security check failed. Please try again.'; $this->redirect('/user/dashboard.php?tab=my-events'); }
        $action = $_POST['org_action'] ?? '';
        $back = '/user/dashboard.php?tab=my-events';

        if ($action === 'save_event') {
            $id = (int)($_POST['id'] ?? 0);
            $event = $id ? $this->eventModel->findByIdForOrganiser($id, $userId) : null;
            if ($id && !$event) { $_SESSION['error'] = 'Event not found.'; $this->redirect($back); }

            $title = trim($_POST['title'] ?? '');
            if ($title === '') { $_SESSION['error'] = 'Event title is required.'; $this->redirect('/user/dashboard.php?tab=event-create' . ($id ? '&id=' . $id : '')); }

            $wantPublish = ($_POST['save_as'] ?? 'draft') === 'publish';
            $data = [
                'title' => $title,
                'description' => trim($_POST['description'] ?? '') ?: null,
                'location' => trim($_POST['venue_name'] ?? '') ?: (trim($_POST['location'] ?? '') ?: null),
                'venue_name' => trim($_POST['venue_name'] ?? '') ?: null,
                'venue_address' => trim($_POST['venue_address'] ?? '') ?: null,
                'category' => trim($_POST['category'] ?? '') ?: null,
                'contact_name' => trim($_POST['contact_name'] ?? '') ?: null,
                'contact_phone' => trim($_POST['contact_phone'] ?? '') ?: null,
                'contact_email' => trim($_POST['contact_email'] ?? '') ?: null,
                'event_date' => $this->combineDateTime($_POST['event_date'] ?? '', $_POST['start_time'] ?? ''),
                'start_time' => $_POST['start_time'] ?? null,
                'end_time' => $_POST['end_time'] ?? null,
                'sales_open_at' => $this->combineDateTime($_POST['sales_open_date'] ?? '', ''),
                'sales_close_at' => $this->combineDateTime($_POST['sales_close_date'] ?? '', ''),
                'registration_url' => trim($_POST['registration_url'] ?? '') ?: null,
                'user_id' => $userId,
            ];

            // Poster upload (optional image)
            $posterId = $event['poster_media_id'] ?? null;
            $posterId = $this->uploadEventPoster($userId) ?: $posterId;
            if ($posterId) $data['poster_media_id'] = $posterId;

            // Publishing fee gate
            $status = 'draft';
            $feeModel = new EventPublishFee();
            if ($wantPublish) {
                $targetId = $id ?: 0;
                if ($event && $event['status'] === 'published') {
                    $status = 'published'; // already live: edits stay live, no re-charge
                } else {
                    $gate = $feeModel->canPublish($targetId);
                    if ($gate['ok']) { $status = 'published'; }
                    else {
                        $status = 'draft';
                        $_SESSION['fee_notice'] = 'Saved as draft. Publishing fee of ' . EventPublishFee::feeAmount() . ' ' . EventPublishFee::feeCurrency() . ' is required before this event can go live.';
                    }
                }
            }
            $data['status'] = $status;

            if ($id) {
                unset($data['user_id']); // ownership cannot change
                $ok = $this->eventModel->update($id, $data);
            } else {
                $data['slug'] = $this->eventModel->uniqueSlug(Validation::generateSlug($title));
                $ok = $this->eventModel->create($data);
                $id = (int)$ok;
            }
            if (!$ok || !$id) { $_SESSION['error'] = 'Could not save the event. Please try again.'; $this->redirect('/user/dashboard.php?tab=event-create' . ($id ? '&id=' . $id : '')); }

            // Ticket categories
            $this->eventModel->saveTicketTypes($id, $_POST['tickets'] ?? []);

            // Ensure a fee record exists so the payments page shows it
            if (EventPublishFee::feeEnabled()) $feeModel->ensureFee($id, $userId);

            $_SESSION['success'] = $status === 'published' ? 'Event published!' : 'Event saved as draft.';
            $this->redirect('/user/dashboard.php?tab=my-events');
        }

        if ($action === 'pay_event_fee') {
            $id = (int)($_POST['id'] ?? 0);
            $event = $this->eventModel->findByIdForOrganiser($id, $userId);
            if (!$event) { $_SESSION['error'] = 'Event not found.'; $this->redirect($back); }
            $feeModel = new EventPublishFee();
            $fee = $feeModel->ensureFee($id, $userId);
            if (in_array($fee['status'], ['paid', 'exempt'], true)) { $_SESSION['success'] = 'Publishing fee already settled.'; $this->redirect('/user/dashboard.php?tab=event-fees'); }
            $payments = new Payment();
            $pay = $payments->createPendingEventFeePayment($userId, (int)$fee['id'], (float)$fee['amount'], $fee['currency']);
            $this->pdo->prepare("UPDATE event_publish_fees SET payment_id=? WHERE id=?")->execute([$pay['id'], (int)$fee['id']]);
            try {
                $gw = (new PesapalGateway())->submitOrder($pay + ['reference' => $pay['reference'], 'amount' => (float)$fee['amount'], 'currency' => $fee['currency']],
                    ['title' => 'Event publishing fee — ' . $event['title']], $this->userModel->findById($userId));
                if (!empty($gw['success'])) {
                    $payments->setGatewayReference($pay['id'], $gw['tracking_id'] ?? '');
                    header('Location: ' . $gw['redirect_url']); exit;
                }
                $_SESSION['error'] = 'Pesapal error: ' . ($gw['message'] ?? 'unknown');
            } catch (Throwable $e) { $_SESSION['error'] = 'Payment gateway unreachable: ' . $e->getMessage(); }
            $this->redirect('/user/dashboard.php?tab=event-fees');
        }

        if ($action === 'publish_event') {
            $id = (int)($_POST['id'] ?? 0);
            $event = $this->eventModel->findByIdForOrganiser($id, $userId);
            if (!$event) { $_SESSION['error'] = 'Event not found.'; $this->redirect($back); }
            $gate = (new EventPublishFee())->canPublish($id);
            if ($gate['ok']) {
                $this->eventModel->update($id, ['status' => 'published']);
                $_SESSION['success'] = 'Event is now published and on sale.';
            } else {
                (new EventPublishFee())->ensureFee($id, $userId);
                $_SESSION['error'] = 'Publishing fee of ' . EventPublishFee::feeAmount() . ' ' . EventPublishFee::feeCurrency() . ' must be paid before publishing.';
            }
            $this->redirect('/user/dashboard.php?tab=my-events');
        }

        if ($action === 'unpublish_event') {
            $id = (int)($_POST['id'] ?? 0);
            $event = $this->eventModel->findByIdForOrganiser($id, $userId);
            if ($event && $event['status'] === 'published') {
                $this->eventModel->update($id, ['status' => 'draft']);
                $_SESSION['success'] = 'Event unpublished (set back to draft).';
            }
            $this->redirect($back);
        }

        if ($action === 'delete_event') {
            $id = (int)($_POST['id'] ?? 0);
            $event = $this->eventModel->findByIdForOrganiser($id, $userId);
            if ($event && $this->eventModel->delete($id)) {
                $_SESSION['success'] = 'Event deleted.';
            } else {
                $_SESSION['error'] = 'Could not delete this event (events with paid tickets are kept for records — contact support to archive).';
            }
            $this->redirect($back);
        }

        $this->redirect($back);
    }

    private function combineDateTime($date, $time) {
        $date = trim((string)$date);
        if ($date === '') return null;
        $time = trim((string)$time);
        return $date . ($time !== '' ? ' ' . substr($time, 0, 5) . ':00' : ' 00:00:00');
    }

    /** Accept a poster image upload (jpg/png/webp, <= 5MB) into storage/uploads/event-posters. */
    private function uploadEventPoster($userId) {
        $file = $_FILES['poster'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) { $_SESSION['error'] = 'Poster must be under 5MB.'; return null; }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext) { $_SESSION['error'] = 'Poster must be a JPG, PNG or WebP image.'; return null; }
        $dir = __DIR__ . '/../../storage/uploads/event-posters';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = 'evt-' . $userId . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name)) { $_SESSION['error'] = 'Poster upload failed.'; return null; }
        $info = @getimagesize($dir . DIRECTORY_SEPARATOR . $name);
        $mediaId = (new MediaAsset())->create([
            'uploaded_by' => $userId,
            'original_filename' => $file['name'],
            'stored_filename' => $name,
            'storage_path' => 'storage/uploads/event-posters/' . $name,
            'media_type' => 'image',
            'mime_type' => $mime,
            'file_size' => filesize($dir . DIRECTORY_SEPARATOR . $name),
            'width' => $info[0] ?? null,
            'height' => $info[1] ?? null,
            'alt_text' => 'Event poster',
        ]);
        return $mediaId ?: null;
    }

    /**
     * Display user profile
     */
    public function profile() {
        AuthMiddleware::requireLogin();
        
        $userId = $_SESSION['user_id'];
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            $this->redirect('/login.php');
        }

        $data = [
            'title' => 'Profile',
            'user' => $user
        ];

        $this->render('user/profile', $data, 'main');
    }

    /**
     * Handle profile update
     */
    public function updateProfile() {
        AuthMiddleware::requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/user/profile.php');
        }

        $userId = $_SESSION['user_id'];
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate
        if (empty($username) || empty($email)) {
            $_SESSION['profile_error'] = 'Username and email are required';
            $this->redirect('/user/profile.php');
        }

        if (!Validation::validateEmail($email)) {
            $_SESSION['profile_error'] = 'Invalid email format';
            $this->redirect('/user/profile.php');
        }

        // Check if email is already taken by another user
        $existingUser = $this->userModel->findByEmail($email);
        if ($existingUser && $existingUser['id'] != $userId) {
            $_SESSION['profile_error'] = 'Email is already in use';
            $this->redirect('/user/profile.php');
        }

        // Prepare update data
        $updateData = [
            'username' => $username,
            'email' => $email,
            'phone' => $phone
        ];

        if (!empty($_FILES['profile_image']['name']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            if ($file['size'] > 5 * 1024 * 1024) {
                $_SESSION['profile_error'] = 'Profile picture must be 5 MB or smaller';
                $this->redirect('/user/profile.php');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
                $_SESSION['profile_error'] = 'Only valid JPG, PNG or WebP profile pictures are allowed';
                $this->redirect('/user/profile.php');
            }
            $directory = __DIR__ . '/../../storage/uploads/images/profiles';
            if (!is_dir($directory)) mkdir($directory, 0755, true);
            $filename = 'user-' . (int)$userId . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
                $_SESSION['profile_error'] = 'The profile picture could not be saved';
                $this->redirect('/user/profile.php');
            }
            $updateData['profile_image_path'] = 'storage/uploads/images/profiles/' . $filename;
        }

        // Handle password change
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                $_SESSION['profile_error'] = 'Current password is required to change password';
                $this->redirect('/user/profile.php');
            }

            $user = $this->userModel->findById($userId);
            if (!password_verify($currentPassword, $user['password_hash'] ?? '')) {
                $_SESSION['profile_error'] = 'Current password is incorrect';
                $this->redirect('/user/profile.php');
            }

            if ($newPassword !== $confirmPassword) {
                $_SESSION['profile_error'] = 'New passwords do not match';
                $this->redirect('/user/profile.php');
            }

            if (!Validation::validatePassword($newPassword)) {
                $_SESSION['profile_error'] = 'Password must be at least 8 characters with uppercase, lowercase, and number';
                $this->redirect('/user/profile.php');
            }

            $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        // Update user
        $result = $this->userModel->update($userId, $updateData);

        if ($result) {
            // Update session
            $_SESSION['user_username'] = $username;
            $_SESSION['user_email'] = $email;
            
            // Log activity
            $this->logModel->create([
                'user_id' => $userId,
                'action' => 'profile_update',
                'description' => 'User updated profile',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            
            $_SESSION['profile_success'] = 'Profile updated successfully';
        } else {
            $_SESSION['profile_error'] = 'Failed to update profile';
        }

        $this->redirect('/user/profile.php');
    }

    /**
     * Display user settings
     */
    public function settings() {
        AuthMiddleware::requireLogin();
        
        $userId = $_SESSION['user_id'];
        $user = $this->userModel->findById($userId);
        
        $data = [
            'title' => 'Settings',
            'user' => $user
        ];

        $this->render('user/settings', $data, 'main');
    }

    /**
     * Handle settings update
     */
    public function updateSettings() {
        AuthMiddleware::requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/user/settings.php');
        }

        $userId = $_SESSION['user_id'];
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $pushNotifications = isset($_POST['push_notifications']) ? 1 : 0;
        $newsletterSubscription = isset($_POST['newsletter_subscription']) ? 1 : 0;
        $supportedCurrencies = ['DZD','AOA','BWP','BIF','CVE','XAF','XOF','KMF','CDF','DJF','EGP','ERN','ETB','SZL','GMD','GHS','GNF','KES','LSL','LRD','LYD','MGA','MWK','MRO','MRU','MUR','MAD','MZN','NAD','NGN','RWF','STN','SCR','SLL','SLE','SOS','ZAR','SSP','SDG','TZS','TND','UGX','ZMW','USD','EUR','GBP','CHF','JPY','CNY','INR','AUD','CAD','NZD','SGD','HKD','AED','SAR','QAR','KWD','BHD','OMR','ILS','TRY','RUB','BRL','MXN','ARS','CLP','COP','PEN','KRW','THB','MYR','IDR','PHP','VND','PKR','BDT','LKR','NPR','PLN','SEK','NOK','DKK','CZK','HUF','RON','UAH'];
        $preferredCurrency = strtoupper(trim($_POST['preferred_currency'] ?? 'USD'));
        if (!in_array($preferredCurrency, $supportedCurrencies, true)) {
            $preferredCurrency = 'USD';
        }

        // Update user settings
        $updateData = [
            'email_notifications' => $emailNotifications,
            'push_notifications' => $pushNotifications,
            'newsletter_subscription' => $newsletterSubscription
            ,'preferred_currency' => $preferredCurrency
        ];

        $result = $this->userModel->update($userId, $updateData);

        if ($result) {
            $_SESSION['settings_success'] = 'Settings updated successfully';
        } else {
            $_SESSION['settings_error'] = 'Failed to update settings';
        }

        $this->redirect('/user/settings.php');
    }
}
?>
