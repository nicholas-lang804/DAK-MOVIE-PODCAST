<?php
/**
 * Admin Controller
 * Handles admin dashboard and management
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Episode.php';
require_once __DIR__ . '/../Models/Article.php';
require_once __DIR__ . '/../Models/Movie.php';
require_once __DIR__ . '/../Models/MovieReview.php';
require_once __DIR__ . '/../Models/Host.php';
require_once __DIR__ . '/../Models/Guest.php';
require_once __DIR__ . '/../Models/Star.php';
require_once __DIR__ . '/../Models/SiteExtras.php';
require_once __DIR__ . '/../Models/Music.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/EventPublishFee.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Comment.php';
require_once __DIR__ . '/../Models/ContactMessage.php';
require_once __DIR__ . '/../Models/NewsletterSubscriber.php';
require_once __DIR__ . '/../Models/Sponsor.php';
require_once __DIR__ . '/../Models/Advertisement.php';
require_once __DIR__ . '/../Models/MediaAsset.php';
require_once __DIR__ . '/../Models/SiteSetting.php';
require_once __DIR__ . '/../Models/ActivityLog.php';
require_once __DIR__ . '/../Models/SubscriptionPlan.php';
require_once __DIR__ . '/../Models/LivePodcast.php';
require_once __DIR__ . '/../Models/SiteNotification.php';
require_once __DIR__ . '/../Models/Gift.php';
require_once __DIR__ . '/../Models/FinancialReport.php';
require_once __DIR__ . '/../Models/Payment.php';
require_once __DIR__ . '/../Services/PesapalGateway.php';
require_once __DIR__ . '/../Services/VideoCompressor.php';
require_once __DIR__ . '/../Services/RenditionBuilder.php';
require_once __DIR__ . '/../Models/UploadJob.php';
require_once __DIR__ . '/../Helpers/PublicUrl.php';
require_once __DIR__ . '/../../config/livekit.php';

class AdminController extends Controller {
    private $userModel;
    private $episodeModel;
    private $articleModel;
    private $movieModel;
    private $reviewModel;
    private $hostModel;
    private $guestModel;
    private $starModel;
    private $eventModel;
    private $categoryModel;
    private $commentModel;
    private $contactModel;
    private $newsletterModel;
    private $sponsorModel;
    private $advertisementModel;
    private $mediaModel;
    private $settingModel;
    private $logModel;
    private $subscriptionPlanModel;
    private $liveModel;

    public function __construct() {
        parent::__construct();
        $this->userModel = new User();
        $this->episodeModel = new Episode();
        $this->articleModel = new Article();
        $this->movieModel = new Movie();
        $this->reviewModel = new MovieReview();
        $this->hostModel = new Host();
        $this->guestModel = new Guest();
        $this->starModel = new Star();
        $this->eventModel = new Event();
        $this->categoryModel = new Category();
        $this->commentModel = new Comment();
        $this->contactModel = new ContactMessage();
        $this->newsletterModel = new NewsletterSubscriber();
        $this->sponsorModel = new Sponsor();
        $this->advertisementModel = new Advertisement();
        $this->mediaModel = new MediaAsset();
        $this->settingModel = new SiteSetting();
        $this->logModel = new ActivityLog();
        $this->subscriptionPlanModel = new SubscriptionPlan();
        $this->liveModel = new LivePodcast();
    }

    public function manageLive() {
        AuthMiddleware::requireAdmin();
        $requestedLiveId = filter_var($_GET['live_id'] ?? null, FILTER_VALIDATE_INT);
        $live = $requestedLiveId ? $this->liveModel->getById($requestedLiveId) : $this->liveModel->getCurrent();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken(); $action=$_POST['live_action']??'';
            if ($action==='create') {
                $coverPath = null;
                try {
                    $coverPath = $this->uploadLiveCover();
                    $this->liveModel->create(['title'=>trim($_POST['title']??'Live Podcast'),'description'=>trim($_POST['description']??''),'stream_url'=>trim($_POST['stream_url']??''),'cover_image_path'=>$coverPath,'started_by'=>$_SESSION['admin_id'],'access_type'=>($_POST['access_type']??'free')==='paid'?'paid':'free','price'=>max(0,(float)($_POST['price']??0)),'currency'=>strtoupper($_POST['currency']??'USD'),'status'=>'scheduled']);
                    $_SESSION['success']='Live room prepared. Share its link and preview, then start the broadcast when ready.';
                } catch (Throwable $error) {
                    if ($coverPath) $this->removeLiveCover($coverPath);
                    error_log('Live show creation failed: ' . $error->getMessage());
                    $_SESSION['error']='The live room could not be created. Check the live-show migration and upload-folder permissions.';
                }
            }
            if ($action==='update_share' && $live && in_array($live['status'], ['scheduled','live'], true)) {
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
                $descriptionLength = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description);
                if ($title === '' || $titleLength > 255 || $descriptionLength > 5000) {
                    $_SESSION['error']='Enter a title of 1–255 characters and a description no longer than 5,000 characters.';
                } else {
                    $coverPath = null;
                    try {
                        $coverPath = $this->uploadLiveCover();
                        $this->liveModel->updateShareDetails($live['id'], $title, $description, $coverPath);
                        if ($coverPath && !empty($live['cover_image_path'])) $this->removeLiveCover($live['cover_image_path']);
                        $_SESSION['success']='Live-show title, description, and share image updated.';
                    } catch (Throwable $error) {
                        if ($coverPath) $this->removeLiveCover($coverPath);
                        error_log('Live share details update failed: ' . $error->getMessage());
                        $_SESSION['error']='Share details could not be saved. Check the live-show migration and upload-folder permissions.';
                    }
                }
            }
            if ($action==='status' && $live) {
                $newStatus=$_POST['status']==='ended'?'ended':'live';
                $stopWarning='';
                if($newStatus==='ended'){
                    $activeRecording=$this->liveModel->activeRecordingForLive($live['id']);
                    if($activeRecording){
                        try{
                            $egress=livekitEgressRequest('StopEgress',['egressId'=>$activeRecording['egress_id']],$live['room_name']);
                            if(!empty($egress['egressId'])&&array_key_exists('status',$egress))$this->liveModel->updateRecordingFromEgress($live['id'],$egress);
                        }catch(Throwable $error){
                            error_log('Recording could not be stopped while ending live show: '.$error->getMessage());
                            $stopWarning=' The active recording could not be stopped automatically; stop it from the recording controls.';
                        }
                    }
                }
                $this->liveModel->setStatus($live['id'],$newStatus);
                if($newStatus==='live'&&$live['status']!=='live'){
                    try{
                        (new SiteNotification())->create('all',null,'live_started','A live show just started',$live['title'],'/TMZ%20PODCAST/live.php?live_id='.(int)$live['id']);
                    }catch(Throwable $error){
                        error_log('Live-start notification could not be saved: '.$error->getMessage());
                    }
                }
                if($stopWarning!=='')$_SESSION['error']='Live room ended.'.$stopWarning;
                else $_SESSION['success']='Live room status updated.';
            }
            if ($action==='delete_all_speaker_requests' && $live) {
                try {
                    $deletedRequests=$this->liveModel->deleteAllSpeakerRequests((int)$live['id']);
                    $_SESSION['success']=$deletedRequests>0
                        ? $deletedRequests.' live request'.($deletedRequests===1?'':'s').' deleted.'
                        : 'There are no live requests to delete.';
                } catch (Throwable $error) {
                    error_log('Live speaker requests could not be deleted: '.$error->getMessage());
                    $_SESSION['error']='Live requests could not be deleted. Please try again.';
                }
                $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=live&live_id='.(int)$live['id']);
            }
            if ($action==='delete_all_live_comments' && $live) {
                try {
                    $deletedComments=$this->liveModel->deleteAllComments((int)$live['id']);
                    $_SESSION['success']=$deletedComments>0
                        ? $deletedComments.' live comment'.($deletedComments===1?'':'s').' deleted.'
                        : 'There are no live comments to delete.';
                } catch (Throwable $error) {
                    error_log('Live comments could not be deleted: '.$error->getMessage());
                    $_SESSION['error']='Live comments could not be deleted. Please try again.';
                }
                $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=live&live_id='.(int)$live['id']);
            }
            if ($action==='delete_live') {
                $targetId=(int)($_POST['past_live_id']??0);
                $target=$targetId?$this->liveModel->getById($targetId):null;
                if($target && $this->liveModel->deleteLive($targetId)){
                    $this->removeLiveCover($target['cover_image_path']??null);
                    $_SESSION['success']='Past live show deleted.';
                } else {
                    $_SESSION['error']='That live show could not be deleted (it may still be live).';
                }
            }
            if ($action==='delete_all_past') {
                $deleted=0; $failed=0;
                foreach($this->liveModel->pastLives(200) as $past){
                    if($this->liveModel->deleteLive((int)$past['id'])){ $this->removeLiveCover($past['cover_image_path']??null); $deleted++; } else { $failed++; }
                }
                $_SESSION[$deleted?'success':'error']=$deleted.' past live show'.($deleted===1?'':'s').' deleted.'.($failed?' '.$failed.' could not be deleted.':'');
            }
            if ($action==='speaker') { $this->liveModel->updateRequest($_POST['request_id']??0, in_array($_POST['speaker_status']??'', ['approved','denied','ended'], true) ? $_POST['speaker_status'] : 'denied'); $_SESSION['success']='Speaker request moderated.'; }
            if ($action==='comment_reply' && $live) { $this->liveModel->addComment($live['id'], $_SESSION['admin_id'], $_POST['comment']??'', $_POST['parent_id']??null); $_SESSION['success']='Reply sent to the live room.'; }
            if ($action==='comment_hide') { $this->liveModel->updateComment($_POST['comment_id']??0,'hidden'); $_SESSION['success']='Comment hidden.'; }
            $redirect='/TMZ%20PODCAST/admin/dashboard.php?tab=live';
            if ($action==='update_share' && $live) $redirect.='&live_id='.(int)$live['id'];
            if ($action==='status' && $live && ($_POST['status']??'')==='ended') $redirect.='&live_id='.(int)$live['id'];
            $this->redirect($redirect);
        }
        $this->renderAdmin('live', ['title'=>'Live Podcast Studio','activeTab'=>'live','live'=>$live,'speakerRequests'=>$live?$this->liveModel->requests($live['id']):[],'liveComments'=>$live?$this->liveModel->comments($live['id'],true):[],'pastLives'=>$this->liveModel->pastLives(50)]);
    }

    private function uploadLiveCover() {
        if (!isset($_FILES['cover_image']) || ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $file = $_FILES['cover_image'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Live cover upload failed with code ' . (int)$file['error'] . '.');
        if (($file['size'] ?? 0) <= 0 || $file['size'] > 8388608) throw new RuntimeException('Live cover images must be smaller than 8 MB.');
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $image = @getimagesize($file['tmp_name']);
        if (!isset($allowed[$mime]) || !$image || ($image['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Live covers must be valid JPEG, PNG, or WebP images.');
        }
        $directory = __DIR__ . '/../../storage/uploads/live-covers';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the live cover upload directory.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new RuntimeException('Could not save the live cover image.');
        }
        return 'storage/uploads/live-covers/' . $filename;
    }

    private function removeLiveCover($path) {
        if (strpos((string)$path, 'storage/uploads/live-covers/') !== 0) return;
        $root = realpath(__DIR__ . '/../../storage/uploads/live-covers');
        $file = realpath(__DIR__ . '/../../' . $path);
        if ($root && $file && strpos($file, $root . DIRECTORY_SEPARATOR) === 0 && is_file($file)) @unlink($file);
    }

    /**
     * Display admin dashboard
     */
    public function dashboard() {
        AuthMiddleware::requireAdmin();

        $tab = $_GET['tab'] ?? 'dashboard';
        $data = [
            'title' => 'Admin Dashboard',
            'activeTab' => $tab
        ];

        // Load data based on tab
        switch ($tab) {
            case 'episodes':
                $data['episodes'] = $this->episodeModel->getPublished(50);
                $data['categories'] = $this->categoryModel->getByType('episode');
                break;
            case 'articles':
                $data['articles'] = $this->articleModel->getPublished(50);
                $data['categories'] = $this->categoryModel->getByType('article');
                break;
            case 'movies':
                $data['movies'] = $this->movieModel->getPublished(50);
                $data['genres'] = $this->categoryModel->getByType('movie');
                break;
            case 'reviews':
                $data['reviews'] = $this->reviewModel->getAll(50);
                $data['movies'] = $this->movieModel->getPublished(50);
                break;
            case 'hosts':
                $data['hosts'] = $this->hostModel->getAll();
                break;
            case 'guests':
                $data['guests'] = $this->guestModel->getAll();
                break;
            case 'events':
                $data['events'] = $this->eventModel->getAll(50);
                break;
            case 'categories':
                $data['categories'] = $this->categoryModel->getAll();
                break;
            case 'comments':
                $data['comments'] = $this->commentModel->getAll(50);
                break;
            case 'contacts':
                $data['contacts'] = $this->contactModel->getAll(50);
                break;
            case 'newsletter':
                $data['subscribers'] = $this->newsletterModel->getAll(50);
                break;
            case 'sponsors':
                $data['sponsors'] = $this->sponsorModel->getAll();
                break;
            case 'advertisements':
                $data['advertisements'] = $this->advertisementModel->getAll(50);
                $data['sponsors'] = $this->sponsorModel->getAll();
                break;
            case 'media':
                $data['media'] = $this->mediaModel->getAll(50);
                break;
            case 'users':
                $data['users'] = $this->userModel->getAll(50);
                break;
            case 'settings':
                $data['settings'] = $this->settingModel->getAll();
                break;
            case 'finance':
                $this->manageFinancials();
                return;
            case 'payments':
                $this->managePayments();
                return;
            case 'logs':
                $data['logs'] = $this->logModel->getAll(50);
                break;
            case 'gifts':
                $this->manageGifts($data);
                return;
        }

        // Get dashboard statistics
        $data['stats'] = [
            'episodes' => $this->episodeModel->getPublishedCount(),
            'articles' => $this->articleModel->getPublishedCount(),
            'movies' => $this->movieModel->getPublishedCount(),
            'users' => $this->userModel->getCount(),
            'comments' => $this->commentModel->getPendingCount(),
            'contacts' => $this->contactModel->getNewCount(),
            'subscribers' => $this->newsletterModel->getActiveCount(),
            'media' => $this->mediaModel->getCount()
        ];

        $this->renderAdmin('dashboard', $data);
    }

    /** Live gifts: catalog, orders and per-currency exchange rates. */
    public function manageGifts(array &$data) {
        AuthMiddleware::requireAdmin();
        $giftModel = new Gift();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $action = $_POST['gift_action'] ?? 'save_gift';
            if ($action === 'save_gift') {
                $ok = $giftModel->saveGift([
                    'id' => (int)($_POST['gift_id'] ?? 0),
                    'name' => trim($_POST['name'] ?? ''),
                    'emoji' => trim($_POST['emoji'] ?? '🎁'),
                    'price_usd' => (float)($_POST['price_usd'] ?? 0),
                    'category' => trim($_POST['category'] ?? 'fun'),
                    'is_active' => !empty($_POST['is_active']),
                    'sort_order' => (int)($_POST['sort_order'] ?? 0),
                ]);
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Gift saved.' : 'Could not save the gift.';
            } elseif ($action === 'delete_gift') {
                $ok = $giftModel->deleteGift((int)($_POST['gift_id'] ?? 0));
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Gift deleted.' : 'Could not delete the gift.';
            } elseif ($action === 'save_rates') {
                $codes = $_POST['rate_code'] ?? []; $names = $_POST['rate_name'] ?? []; $vals = $_POST['rate_value'] ?? [];
                $saved = 0;
                foreach ($codes as $i => $code) {
                    if (trim((string)$code) === '') continue;
                    if ($giftModel->saveRate($code, $names[$i] ?? '', $vals[$i] ?? 0)) $saved++;
                }
                $_SESSION['success'] = $saved . ' exchange rate' . ($saved === 1 ? '' : 's') . ' saved.';
            }
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=gifts');
        }
        $data['title'] = 'Live Gifts';
        $data['activeTab'] = 'gifts';
        $data['gifts'] = $giftModel->gifts(false);
        $data['giftRates'] = $giftModel->rates();
        $data['giftOrders'] = $giftModel->recentOrders(50);
        $data['giftEdit'] = isset($_GET['edit']) ? $giftModel->giftById((int)$_GET['edit']) : null;
        $this->renderAdmin('gifts', $data);
    }

    /**
     * Pesapal payment management: credentials, environment, IPN auto-registration,
     * connection tests, transaction stats/history and manual verification.
     */
    public function managePayments() {
        AuthMiddleware::requireAdmin();
        $settings = new SiteSetting();
        $gateway = new PesapalGateway();
        $payments = new Payment();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $action = $_POST['payment_action'] ?? '';
            try {
                if ($action === 'save_credentials') {
                    $env = ($_POST['pesapal_environment'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
                    $settings->set('pesapal_environment', $env);
                    if (trim((string)($_POST['pesapal_consumer_key'] ?? '')) !== '') $settings->set('pesapal_consumer_key', trim((string)$_POST['pesapal_consumer_key']));
                    // Secret is write-only from the UI: empty field keeps the stored value.
                    if (trim((string)($_POST['pesapal_consumer_secret'] ?? '')) !== '') $settings->set('pesapal_consumer_secret', trim((string)$_POST['pesapal_consumer_secret']));
                    $base = trim((string)($_POST['pesapal_public_base_url'] ?? ''));
                    if ($base !== '') {
                        $p = parse_url($base);
                        if (!is_array($p) || !in_array(strtolower($p['scheme'] ?? ''), ['http','https'], true) || empty($p['host'])) {
                            throw new RuntimeException('Public base URL must be a full http(s) URL, e.g. https://pay.example.com/TMZ%20PODCAST');
                        }
                        $settings->set('pesapal_public_base_url', rtrim($base, '/'));
                    } else { $settings->set('pesapal_public_base_url', ''); }
                    // Clear cached token after credential changes.
                    $settings->set('pesapal_token_cache', ''); $settings->set('pesapal_token_expires', '0');
                    $_SESSION['success'] = 'Pesapal settings saved (' . strtoupper($env) . '). Click "Test connection" to verify.';
                } elseif ($action === 'test_connection') {
                    $result = $gateway->testConnection();
                    $settings->set('pesapal_last_test_at', date('Y-m-d H:i:s'));
                    $settings->set('pesapal_connection_status', $result['success'] ? 'ok' : 'fail');
                    $_SESSION[$result['success'] ? 'success' : 'error'] = 'Connection test: ' . $result['message'];
                } elseif ($action === 'register_ipn') {
                    // Validate endpoints are publicly reachable AND on a public host before registering.
                    $cb = $gateway->callbackUrl(); $ipn = $gateway->ipnUrl();
                    if (!$gateway->isPublicUrl($cb) || !$gateway->isPublicUrl($ipn)) {
                        $_SESSION['error'] = 'IPN registration blocked — the callback/IPN URLs point to localhost or a private address. Open the admin panel through your public site URL (e.g. the Cloudflare tunnel or your domain), then try again.';
                    } else {
                    [$cbOk, $cbCode, $cbMsg] = $gateway->checkReachable($cb);
                    [$ipnOk, $ipnCode, $ipnMsg] = $gateway->checkReachable($ipn);
                    if (!$cbOk || !$ipnOk) {
                        $_SESSION['error'] = 'IPN registration blocked — endpoints not publicly reachable. Callback: ' . $cbMsg . '; IPN: ' . $ipnMsg . '. Deploy to a public HTTPS URL or set the Public Base URL above.';
                    } else {
                        $force = !empty($_POST['force_new_ipn']);
                        $result = $gateway->ensureIpn(null, $force);
                        if ($result['success']) {
                            $settings->set('pesapal_notification_id', $result['ipn_id']);
                            $settings->set('pesapal_ipn_url', $ipn);
                            $settings->set('pesapal_ipn_registered_at', date('Y-m-d H:i:s'));
                            $settings->set('pesapal_ipn_status', 'registered');
                            $settings->set('pesapal_token_cache', ''); $settings->set('pesapal_token_expires', '0');
                            $_SESSION['success'] = $result['message'] . ' Notification ID saved: ' . $result['ipn_id'];
                        } else {
                            $_SESSION['error'] = $result['message'];
                        }
                    }
                    }
                } elseif ($action === 'verify_payment') {
                    $payment = $payments->getById((int)($_POST['payment_id'] ?? 0));
                    $trackingId = trim((string)($payment['gateway_reference'] ?? ''));
                    if (!$payment || $trackingId === '') {
                        $_SESSION['error'] = 'This payment has no gateway tracking ID to verify yet.';
                    } else {
                        $status = $gateway->transactionStatus($trackingId);
                        $pstatus = $status['payment_status_description'] ?? ($status['status_code'] ?? 'pending');
                        require_once __DIR__ . '/../Models/Gift.php';
                        if ((new Gift())->orderByReference($payment['reference'])) {
                            $ok = $payments->completeGiftPayment($payment['reference'], $trackingId, $pstatus);
                        } elseif (!empty($payment['event_order_id'])) {
                            $ok = $payments->completeEventPayment($payment['reference'], $trackingId, $pstatus);
                        } else {
                            $ok = $payments->completeMoviePayment($payment['reference'], $trackingId, $pstatus);
                        }
                        $_SESSION[$ok ? 'success' : 'error'] = 'Verification result: ' . $pstatus . ' — payment is ' . ($ok ? 'completed.' : 'not completed.');
                    }
                }
            } catch (Throwable $e) {
                $_SESSION['error'] = 'Payment action failed: ' . $e->getMessage();
            }
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=payments');
        }

        $this->renderAdmin('payments', [
            'title' => 'Payments',
            'activeTab' => 'payments',
            'gateway' => $gateway,
            'settings' => $settings->getAllKeyed(),
            'paymentStats' => $payments->stats(),
            'paymentRows' => $payments->recent(60),
        ]);
    }

    public function manageFinancials() {
        AuthMiddleware::requireAdmin();
        $report = new FinancialReport();
        $pageSize = 50;
        $paymentPage = max(1, (int) ($_GET['payments_page'] ?? 1));
        $ticketPage = max(1, (int) ($_GET['tickets_page'] ?? 1));
        $summary = $report->getSummary();

        $this->renderAdmin('finance', [
            'title' => 'Financial Control',
            'activeTab' => 'finance',
            'summary' => $summary,
            'monthlyRevenue' => $report->getMonthlyRevenue(),
            'payments' => $report->getPayments($pageSize, ($paymentPage - 1) * $pageSize),
            'paymentCount' => $report->getPaymentCount(),
            'paymentPage' => $paymentPage,
            'tickets' => $report->getTickets($pageSize, ($ticketPage - 1) * $pageSize),
            'ticketCount' => $report->getTicketCount(),
            'ticketPage' => $ticketPage,
            'pageSize' => $pageSize
        ]);
    }

    /**
     * Handle episode CRUD operations
     */
    public function manageEpisodes() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_episodes');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleEpisodePost();
        }

        $data = [
            'title' => 'Manage Episodes',
            'activeTab' => 'episodes',
            'action' => $action,
            'episode' => $id ? $this->episodeModel->findById($id) : null,
            'episodes' => $this->episodeModel->getPublished(50),
            'categories' => $this->categoryModel->getByType('episode'),
            'images' => $this->mediaModel->getByType('image', 100),
            'audios' => $this->mediaModel->getByType('audio', 100),
            'videos' => $this->mediaModel->getByType('video', 100)
        ];

        $this->renderAdmin('episodes', $data);
    }

    /**
     * Handle episode form submission
     */
    private function handleEpisodePost() {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = Validation::generateSlug($title);
        $description = trim($_POST['description'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0);
        $status = $_POST['status'] ?? 'draft';
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $existingEpisode = $id ? $this->episodeModel->findById($id) : null;
        $featuredImageId = intval($_POST['featured_image_id'] ?? 0) ?: ($existingEpisode['featured_image_id'] ?? null);
        $audioMediaId = intval($_POST['audio_media_id'] ?? 0) ?: ($existingEpisode['audio_media_id'] ?? null);
        $uploadedImage = $this->uploadMovieAsset('episode_cover_file', 'image');
        $uploadedAudio = $this->uploadMovieAsset('episode_audio_file', 'audio');
        $uploadedVideo = $this->uploadMovieAsset('episode_video_file', 'video');
        if ($uploadedImage) $featuredImageId = $uploadedImage;
        if ($uploadedAudio) $audioMediaId = $uploadedAudio;
        if (!$audioMediaId && !$uploadedVideo && empty($existingEpisode['video_url'])) {
            $_SESSION['error'] = 'An episode needs podcast audio or video. Upload a file or choose existing media.';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=episodes&action=' . ($id ? 'edit&id=' . $id : 'create'));
        }

        if (empty($title)) {
            $_SESSION['error'] = 'Title is required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=episodes');
        }

        $data = [
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'category_id' => $categoryId ?: null,
            'featured_image_id' => $featuredImageId,
            'audio_media_id' => $audioMediaId,
            'video_url' => $uploadedVideo ? mediaUrl($uploadedVideo) : ($existingEpisode['video_url'] ?? null),
            'status' => $status,
            'is_featured' => $isFeatured,
            'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null
        ];

        if ($id) {
            $result = $this->episodeModel->update($id, $data);
            $message = $result ? 'Episode updated successfully' : 'Failed to update episode';
        } else {
            $data['created_by'] = $_SESSION['admin_id'] ?? null;
            $result = $this->episodeModel->create($data);
            $message = $result ? 'Episode created successfully' : 'Failed to create episode';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=episodes');
    }

    /**
     * Handle article CRUD operations
     */
    public function manageArticles() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_articles');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleArticlePost();
        }

        $data = [
            'title' => 'Manage Articles',
            'activeTab' => 'articles',
            'action' => $action,
            'article' => $id ? $this->articleModel->findById($id) : null,
            'articles' => $this->articleModel->getPublished(50),
            'categories' => $this->categoryModel->getByType('article')
            ,'images' => $this->mediaModel->getByType('image', 100)
        ];

        $this->renderAdmin('articles', $data);
    }

    /**
     * Handle article form submission
     */
    private function handleArticlePost() {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = Validation::generateSlug($title);
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = $_POST['content'] ?? '';
        $categoryId = intval($_POST['category_id'] ?? 0);
        $status = $_POST['status'] ?? 'draft';
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $existingArticle = $id ? $this->articleModel->findById($id) : null;
        $featuredImageId = intval($_POST['featured_image_id'] ?? 0) ?: ($existingArticle['featured_image_id'] ?? null);
        $uploadedImage = $this->uploadMovieAsset('article_image_file', 'image');
        if ($uploadedImage) $featuredImageId = $uploadedImage;

        if (empty($title)) {
            $_SESSION['error'] = 'Title is required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=articles');
        }

        $data = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'content' => $content,
            'category_id' => $categoryId ?: null,
            'featured_image_id' => $featuredImageId,
            'status' => $status,
            'is_featured' => $isFeatured,
            'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null
        ];

        if ($id) {
            $result = $this->articleModel->update($id, $data);
            $message = $result ? 'Article updated successfully' : 'Failed to update article';
        } else {
            $data['author_id'] = $_SESSION['admin_id'] ?? null;
            $result = $this->articleModel->create($data);
            $message = $result ? 'Article created successfully' : 'Failed to create article';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=articles');
    }

    /**
     * Handle movie CRUD operations
     */
    public function manageMovies() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_movies');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            if (isset($_POST['delete_id'])) {
                $this->handleMovieDelete((int)$_POST['delete_id']);
            }
            $this->handleMoviePost();
        }

        $data = [
            'title' => 'Manage Movies',
            'activeTab' => 'movies',
            'action' => $action,
            'movie' => $id ? $this->movieModel->findById($id) : null,
            'movies' => $this->movieModel->getPublished(50),
            'genres' => $this->categoryModel->getMovieGenresWithCounts(),
            'images' => $this->mediaModel->getByType('image', 100),
            'videos' => $this->mediaModel->getByType('video', 100)
        ];

        $data['selectedGenreIds'] = $id ? $this->movieModel->getGenreIds($id) : [];

        $this->renderAdmin('movies', $data);
    }

    /**
     * Handle movie form submission
     */
    private function handleMoviePost() {
        $id = intval($_POST['id'] ?? 0);
        $existingMovie = $id ? $this->movieModel->findById($id) : null;
        $title = trim($_POST['title'] ?? '');
        $slug = Validation::generateSlug($title);
        $synopsis = trim($_POST['synopsis'] ?? '');
        $director = trim($_POST['director'] ?? '');
        $releaseDate = $_POST['release_date'] ?? null;
        $runtime = intval($_POST['runtime_minutes'] ?? 0);
        $status = $_POST['status'] ?? 'draft';
        $accessType = ($_POST['access_type'] ?? 'free') === 'paid' ? 'paid' : 'free';
        $videoDisplay = $_POST['video_display'] ?? 'video_centre';
        if (!in_array($videoDisplay, ['none', 'homepage', 'video_centre', 'both'], true)) {
            $videoDisplay = 'video_centre';
        }
        if ($accessType === 'paid') {
            $videoDisplay = 'none';
        }
        $price = $accessType === 'paid' ? max(0, (float)($_POST['price'] ?? 0)) : null;
        $priceCurrency = strtoupper(substr(trim($_POST['price_currency'] ?? 'USD'), 0, 3));
        $posterPath = trim((string)($existingMovie['poster_path'] ?? ''));
        $videoMediaId = intval($_POST['video_media_id'] ?? 0) ?: ($existingMovie['video_media_id'] ?? null);
        $genreId = intval($_POST['genre_id'] ?? 0) ?: null;

        $uploadedPosterPath = $this->uploadMoviePoster();
        $uploadedVideoId = $this->uploadMovieAsset('movie_video_file', 'video');
        if ($uploadedPosterPath) { $posterPath = $uploadedPosterPath; }
        if ($uploadedVideoId) { $videoMediaId = $uploadedVideoId; }
        if (!$posterPath || !$videoMediaId) {
            $_SESSION['error'] = 'A movie cover and movie video are required. Upload a movie cover and choose a valid video file.';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies&action=' . ($id ? 'edit&id=' . $id : 'create'));
        }

        if (empty($title)) {
            $_SESSION['error'] = 'Title is required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies');
        }

        if ($videoMediaId) {
            $videoCheck = getDB()->prepare("SELECT id FROM media_assets WHERE id = ? AND media_type = 'video'");
            $videoCheck->execute([$videoMediaId]);
            if (!$videoCheck->fetchColumn()) {
                $_SESSION['error'] = 'Please select a valid video from the media library.';
                $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies');
            }
        }

        if ($genreId) {
            $genreCheck = getDB()->prepare("SELECT id FROM categories WHERE id = ? AND category_type = 'movie'");
            $genreCheck->execute([$genreId]);
            if (!$genreCheck->fetchColumn()) {
                $_SESSION['error'] = 'Please select a valid movie genre.';
                $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies');
            }
        }

        $data = [
            'title' => $title,
            'slug' => $slug,
            'synopsis' => $synopsis,
            'poster_path' => $posterPath,
            'poster_media_id' => null,
            'video_media_id' => $videoMediaId,
            'genre_ids' => $genreId ? [$genreId] : [],
            'director' => $director,
            'release_date' => $releaseDate,
            'runtime_minutes' => $runtime ?: null,
            'status' => $status,
            'access_type' => $accessType,
            'video_display' => $videoDisplay,
            'allow_download' => !empty($_POST['allow_download']) ? 1 : 0,
            'price' => $price,
            'price_currency' => $priceCurrency
        ];

        if ($id) {
            $result = $this->movieModel->update($id, $data);
            $message = $result ? 'Movie updated successfully' : 'Failed to update movie';
        } else {
            $result = $this->movieModel->create($data);
            $message = $result ? 'Movie created successfully' : 'Failed to create movie';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies');
    }

    /** Delete a movie plus its video file (when unused elsewhere) and poster file. */
    private function handleMovieDelete($id) {
        $movie = $id ? $this->movieModel->findById($id) : null;
        if (!$movie) {
            $_SESSION['error'] = 'Movie not found.';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies');
        }
        $videoMediaId = $movie['video_media_id'] ?? null;
        $posterPath = (string)($movie['poster_path'] ?? '');
        if (!$this->movieModel->delete($id)) {
            $_SESSION['error'] = 'Failed to delete the movie.';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies');
        }
        $this->removeStoredFile($posterPath);
        if ($videoMediaId) {
            $media = $this->mediaModel->findById($videoMediaId);
            if ($media && !$this->mediaModel->isMediaUsed($videoMediaId)) {
                $this->removeStoredFile($media['storage_path']);
                $this->mediaModel->delete($videoMediaId);
            }
        }
        $_SESSION['success'] = 'Movie deleted.';
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies');
    }

    /** Delete a file only when it lives inside the project storage folders. */
    private function removeStoredFile($path) {
        if (!$path) return;
        $root = realpath(__DIR__ . '/../..');
        $full = realpath($root . '/' . ltrim((string)$path, '/'));
        if ($root && $full && strpos($full, $root . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
            @unlink($full);
        }
    }

    /** Upload a movie poster and keep it tied to the movie instead of registering it in the shared media library. */
    private function uploadMoviePoster() {
        if (!isset($_FILES['movie_cover_file']) || ($_FILES['movie_cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $uploadDir = __DIR__ . '/../../storage/uploads/movies/';
        $result = $this->handleFileUpload('movie_cover_file', $uploadDir, $allowed, 15728640);
        if (!$result[0]) {
            $_SESSION['error'] = 'Movie cover upload failed: ' . $result[2];
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies&action=' . (($_POST['id'] ?? 0) ? 'edit&id=' . (int)$_POST['id'] : 'create'));
        }

        return 'storage/uploads/movies/' . basename($result[1]);
    }

    /** Upload an asset directly from the Add/Edit Movie form and register it in Media Library. */
    private function uploadMovieAsset($field, $type) {
        if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($type === 'video') {
            $allowed = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
        } elseif ($type === 'audio') {
            $allowed = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/x-m4a'];
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        }
        $maxSize = $type === 'video' ? 5368709120 : ($type === 'audio' ? 524288000 : 15728640);
        $result = $this->handleFileUpload($field, __DIR__ . '/../../storage/uploads/', $allowed, $maxSize);
        if (!$result[0]) {
            $_SESSION['error'] = ucfirst($type) . ' upload failed: ' . $result[2];
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies&action=create');
        }
        $filePath = $result[1];
        $mimeType = mime_content_type($filePath);
        $imageInfo = $type === 'image' ? getimagesize($filePath) : false;
        $id = $this->mediaModel->create([
            'uploaded_by' => $_SESSION['admin_id'] ?? null,
            'original_filename' => $_FILES[$field]['name'],
            'stored_filename' => basename($filePath),
            'storage_path' => 'storage/uploads/' . basename($filePath),
            'media_type' => $type,
            'mime_type' => $mimeType,
            'file_size' => filesize($filePath),
            'width' => $imageInfo ? $imageInfo[0] : null,
            'height' => $imageInfo ? $imageInfo[1] : null,
            'alt_text' => pathinfo($_FILES[$field]['name'], PATHINFO_FILENAME)
        ]);
        if (!$id) {
            $_SESSION['error'] = 'The ' . $type . ' uploaded but could not be registered in Media Library.';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=movies&action=create');
        }
        if ($type === 'video') { RenditionBuilder::enqueue((int)$id, $filePath); }
        return (int)$id;
    }

    /**
     * Handle review CRUD operations
     */
    public function manageReviews() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_reviews');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleReviewPost();
        }

        $data = [
            'title' => 'Manage Reviews',
            'activeTab' => 'reviews',
            'action' => $action,
            'review' => $id ? $this->reviewModel->findById($id) : null,
            'reviews' => $this->reviewModel->getAll(50),
            'movies' => $this->movieModel->getPublished(50)
        ];

        $this->renderAdmin('reviews', $data);
    }

    /**
     * Handle review form submission
     */
    private function handleReviewPost() {
        $id = intval($_POST['id'] ?? 0);
        $movieId = intval($_POST['movie_id'] ?? 0);
        $reviewTitle = trim($_POST['review_title'] ?? '');
        $reviewContent = trim($_POST['review_content'] ?? '');
        $rating = floatval($_POST['rating'] ?? 0);
        $status = $_POST['status'] ?? 'draft';

        if (empty($reviewContent) || empty($movieId)) {
            $_SESSION['error'] = 'Movie and review content are required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=reviews');
        }

        $data = [
            'movie_id' => $movieId,
            'review_title' => $reviewTitle,
            'review_content' => $reviewContent,
            'rating' => $rating,
            'status' => $status,
            'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null
        ];

        if ($id) {
            $result = $this->reviewModel->update($id, $data);
            $message = $result ? 'Review updated successfully' : 'Failed to update review';
        } else {
            $data['author_id'] = $_SESSION['admin_id'] ?? null;
            $result = $this->reviewModel->create($data);
            $message = $result ? 'Review created successfully' : 'Failed to create review';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=reviews');
    }

    /**
     * Handle host CRUD operations
     */
    public function manageHosts() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_hosts');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleHostPost();
        }

        $data = [
            'title' => 'Manage Hosts',
            'activeTab' => 'hosts',
            'action' => $action,
            'host' => $id ? $this->hostModel->findById($id) : null,
            'hosts' => $this->hostModel->getAll()
        ];

        $this->renderAdmin('hosts', $data);
    }

    /**
     * Handle host form submission
     */
    private function handleHostPost() {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = Validation::generateSlug($name);
        $biography = trim($_POST['biography'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            $_SESSION['error'] = 'Name is required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=hosts');
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'biography' => $biography,
            'role' => $role,
            'status' => $status
        ];

        if ($id) {
            $result = $this->hostModel->update($id, $data);
            $message = $result ? 'Host updated successfully' : 'Failed to update host';
        } else {
            $result = $this->hostModel->create($data);
            $message = $result ? 'Host created successfully' : 'Failed to create host';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=hosts');
    }

    /**
     * Handle guest CRUD operations
     */
    public function manageGuests() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_guests');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleGuestPost();
        }

        $data = [
            'title' => 'Manage Guests',
            'activeTab' => 'guests',
            'action' => $action,
            'guest' => $id ? $this->guestModel->findById($id) : null,
            'guests' => $this->guestModel->getAll()
        ];

        $this->renderAdmin('guests', $data);
    }

    /**
     * Handle guest form submission
     */
    private function handleGuestPost() {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $biography = trim($_POST['biography'] ?? '');
        $profession = trim($_POST['profession'] ?? '');

        if (empty($name)) {
            $_SESSION['error'] = 'Name is required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=guests');
        }

        $data = [
            'name' => $name,
            'biography' => $biography,
            'profession' => $profession
        ];

        if ($id) {
            $result = $this->guestModel->update($id, $data);
            $message = $result ? 'Guest updated successfully' : 'Failed to update guest';
        } else {
            $result = $this->guestModel->create($data);
            $message = $result ? 'Guest created successfully' : 'Failed to create guest';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=guests');
    }

    /**
     * Music Zone: upload songs (audio + optional music video + cover), lyrics, access rules, artist bios.
     */
    public function manageMusic() {
        AuthMiddleware::requireAdmin();
        if (!AuthMiddleware::hasPermission('manage_music') && !AuthMiddleware::hasPermission('manage_guests')) {
            http_response_code(403);
            die('You do not have permission to access this resource.');
        }
        $music = new Music();
        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleMusicPost($music);
        }

        $this->renderAdmin('music', [
            'title' => 'Manage Music',
            'activeTab' => 'music',
            'action' => $action,
            'track' => $id ? $music->trackById($id, true) : null,
            'tracks' => $music->allForAdmin(),
            'artistNames' => $music->allArtistNames(),
            'genres' => Music::GENRES
        ]);
    }

    private function handleMusicPost(Music $music) {
        $back = '/TMZ%20PODCAST/admin/dashboard.php?tab=music';
        $id = intval($_POST['id'] ?? 0);
        $formAction = $_POST['form_action'] ?? 'save';

        if ($formAction === 'delete') {
            $t = $id ? $music->trackById($id, true) : null;
            if ($t && $music->deleteTrack($id)) {
                $this->removeMusicFile($t['audio_path'] ?? ''); $this->removeMusicFile($t['video_path'] ?? ''); $this->removeMusicFile($t['cover_path'] ?? '');
                $_SESSION['success'] = 'Track deleted';
            } else {
                $_SESSION['error'] = 'Could not delete that track';
            }
            $this->redirect($back);
        }

        if ($formAction === 'artist') {
            $name = trim($_POST['artist_name'] ?? '');
            if ($name === '') { $_SESSION['error'] = 'Choose or type an artist name.'; $this->redirect($back . '&action=artists'); }
            $artistId = $music->artistIdFor($name);
            $photo = $this->uploadMusicFile('artist_photo', Music::IMAGE_TYPES, 10485760, 'storage/uploads/music', $back . '&action=artists', 'Artist photo');
            $music->saveArtistProfile($artistId, trim($_POST['artist_bio'] ?? ''), $photo);
            $_SESSION['success'] = 'Artist profile saved';
            $this->redirect($back . '&action=artists');
        }

        $existing = $id ? $music->trackById($id, true) : null;
        $retry = $back . '&action=' . ($id ? 'edit&id=' . $id : 'create');

        $title = trim($_POST['title'] ?? '');
        $artistName = trim($_POST['artist_name'] ?? '');
        if ($title === '' || $artistName === '') { $_SESSION['error'] = 'Title and artist are required.'; $this->redirect($retry); }

        $levels = ['everyone', 'members', 'subscribers'];
        $playAccess = in_array($_POST['play_access'] ?? '', $levels, true) ? $_POST['play_access'] : 'everyone';
        $dlLevels = ['off', 'everyone', 'members', 'subscribers'];
        $dlAccess = in_array($_POST['download_access'] ?? '', $dlLevels, true) ? $_POST['download_access'] : 'members';

        $audio = $existing['audio_path'] ?? '';
        $video = $existing['video_path'] ?? '';
        $cover = $existing['cover_path'] ?? '';
        $previousVideo = $video;

        $newVideo = $this->uploadMusicFile('video_file', Music::VIDEO_TYPES, 2147483648, 'storage/music/video', $retry, 'Video');
        if ($newVideo) { $this->removeMusicFile($video); $video = $newVideo; }
        if (!empty($_POST['remove_video']) && !$newVideo) { $this->removeMusicFile($video); $video = ''; }
        // When the audio file was really the old video file and the video changed, it is gone.
        if ($audio !== '' && $audio === $previousVideo && $video !== $previousVideo) { $audio = ''; }

        $newAudio = $this->uploadMusicFile('audio_file', Music::AUDIO_TYPES, 314572800, 'storage/music/audio', $retry, 'Audio');
        if ($newAudio) { if ($audio !== $video) $this->removeMusicFile($audio); $audio = $newAudio; }
        if ($audio === '' && $video !== '') {
            $audio = $this->extractAudioFromVideo($video) ?: $video;
        }
        if ($audio === '') { $_SESSION['error'] = 'Please choose an audio file (MP3, M4A, AAC, OGG, WAV or FLAC) or upload a music video to extract the audio from.'; $this->redirect($retry); }

        $newCover = $this->uploadMusicFile('cover_file', Music::IMAGE_TYPES, 10485760, 'storage/uploads/music', $retry, 'Cover');
        if ($newCover) { $this->removeMusicFile($cover); $cover = $newCover; }

        $year = intval($_POST['release_year'] ?? 0);
        $year = ($year >= 1900 && $year <= 2100) ? $year : null;
        $artistId = $music->artistIdFor($artistName);
        $albumId = $music->albumIdFor($artistId, trim($_POST['album_title'] ?? ''), $cover ?: null, $year);

        $savedId = $music->saveTrack($id, [
            'title' => mb_substr($title, 0, 190),
            'artist_name' => $artistName,
            'artist_id' => $artistId,
            'album_id' => $albumId,
            'genre' => mb_substr(trim($_POST['genre'] ?? ''), 0, 60),
            'track_no' => max(0, intval($_POST['track_no'] ?? 0)),
            'duration_seconds' => max(0, min(36000, intval($_POST['duration_seconds'] ?? 0))),
            'audio_path' => $audio,
            'video_path' => $video,
            'cover_path' => $cover,
            'lyrics' => trim($_POST['lyrics'] ?? ''),
            'is_explicit' => !empty($_POST['is_explicit']) ? 1 : 0,
            'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
            'play_access' => $playAccess,
            'download_access' => $dlAccess,
            'status' => (($_POST['status'] ?? 'published') === 'hidden') ? 'hidden' : 'published'
        ]);

        $_SESSION[$savedId ? 'success' : 'error'] = $savedId ? 'Track saved' : 'Could not save the track';
        $this->redirect($savedId ? $back : $retry);
    }

    /** Save an uploaded music file. Extension comes from the detected MIME type, never the client's file name. */
    private function uploadMusicFile($field, array $allowed, $maxSize, $relDir, $retryUrl, $label) {
        if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $f = $_FILES[$field];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = $label . ' upload failed (code ' . (int)$f['error'] . '). Large files need higher upload_max_filesize and post_max_size in php.ini.';
            $this->redirect($retryUrl);
        }
        if ($f['size'] > $maxSize) { $_SESSION['error'] = $label . ' is too large (max ' . round($maxSize / 1048576) . ' MB).'; $this->redirect($retryUrl); }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!isset($allowed[$mime])) {
            $_SESSION['error'] = $label . ' must be one of: ' . implode(', ', array_unique(array_values($allowed))) . ' (detected: ' . $mime . ').';
            $this->redirect($retryUrl);
        }
        $dir = __DIR__ . '/../../' . $relDir . '/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) { $_SESSION['error'] = 'Could not create the folder ' . $relDir; $this->redirect($retryUrl); }
        $name = bin2hex(random_bytes(8)) . '_' . time() . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], $dir . $name)) { $_SESSION['error'] = $label . ' could not be saved. Check folder permissions on ' . $relDir; $this->redirect($retryUrl); }
        if (strpos($mime, 'video/') === 0) { VideoCompressor::queue($dir . $name); }
        return $relDir . '/' . $name;
    }

    /** Delete a music file, but only inside storage/music or storage/uploads/music. */
    private function removeMusicFile($path) {
        if (!$path) return;
        foreach (['storage/music', 'storage/uploads/music'] as $rel) {
            if (strpos($path, $rel . '/') === 0) {
                $root = realpath(__DIR__ . '/../../' . $rel);
                $full = realpath(__DIR__ . '/../../' . $path);
                if ($root && $full && strpos($full, $root . DIRECTORY_SEPARATOR) === 0 && is_file($full)) @unlink($full);
                return;
            }
        }
    }

    /** Locate an ffmpeg binary: PATH first, then common Windows install folders and a bundled bin/. */
    private function findFfmpeg($name = 'ffmpeg') {
        static $cache = [];
        if (array_key_exists($name, $cache)) return $cache[$name];
        $found = false;
        $candidates = [
            __DIR__ . '/../../bin/' . $name . '.exe',
            __DIR__ . '/../../bin/' . $name,
            'C:\\ffmpeg\\bin\\' . $name . '.exe',
            'C:\\Program Files\\ffmpeg\\bin\\' . $name . '.exe',
            'C:\\ffmpeg-' . $name . '\\bin\\' . $name . '.exe',
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) { $found = $candidate; break; }
        }
        if (!$found) {
            $out = [];
            $cmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where ' . $name . ' 2>NUL' : 'command -v ' . $name . ' 2>/dev/null';
            @exec($cmd, $out);
            foreach ($out as $line) {
                $line = trim($line);
                if ($line !== '' && is_file($line)) { $found = $line; break; }
            }
        }
        return $cache[$name] = $found;
    }

    /**
     * Extract the audio track of a stored music video into storage/music/audio.
     * Returns the stored relative path, or null when ffmpeg is unavailable or extraction fails.
     */
    private function extractAudioFromVideo($videoPath) {
        $ffmpeg = $this->findFfmpeg('ffmpeg');
        if (!$ffmpeg) return null;
        $root = realpath(__DIR__ . '/../../');
        $src = $root ? realpath($root . '/' . ltrim((string)$videoPath, '/')) : false;
        if (!$src || !is_file($src)) return null;
        $dir = __DIR__ . '/../../storage/music/audio/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) return null;

        $attempts = [
            ['-vn -acodec libmp3lame -q:a 2', 'mp3'],
            ['-vn -acodec aac -b:a 192k', 'm4a'],
        ];
        @set_time_limit(0);
        foreach ($attempts as [$codecArgs, $ext]) {
            $out = $dir . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
            $cmd = '"' . $ffmpeg . '" -hide_banner -loglevel error -y -i ' . escapeshellarg($src) . ' ' . $codecArgs . ' ' . escapeshellarg($out) . ' 2>&1';
            $output = [];
            $code = 1;
            @exec($cmd, $output, $code);
            if ($code === 0 && is_file($out) && filesize($out) > 0) {
                return 'storage/music/audio/' . basename($out);
            }
            if (is_file($out)) @unlink($out);
        }
        error_log('Music audio extraction failed for ' . $videoPath . ': ' . implode(' ', $output));
        return null;
    }

    /**
     * Stars (actors & actresses): admin adds a name, a write-up, a picture and a video.
     */
    public function manageStars() {
        AuthMiddleware::requireAdmin();
        if (!AuthMiddleware::hasPermission('manage_stars') && !AuthMiddleware::hasPermission('manage_guests')) {
            http_response_code(403);
            die('You do not have permission to access this resource.');
        }

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleStarPost();
        }

        $data = [
            'title' => 'Manage Stars',
            'activeTab' => 'stars',
            'action' => $action,
            'star' => $id ? $this->starModel->findById($id) : null,
            'stars' => $this->starModel->getAll(),
            'allMovies' => (new SiteExtras())->publishedMoviesLite(),
            'starMovieIds' => $id ? (new SiteExtras())->starMovieIds($id) : []
        ];

        $this->renderAdmin('stars', $data);
    }

    private function handleStarPost() {
        $back = '/TMZ%20PODCAST/admin/dashboard.php?tab=stars';
        $id = intval($_POST['id'] ?? 0);

        if (($_POST['form_action'] ?? 'save') === 'delete') {
            $existing = $id ? $this->starModel->findById($id) : null;
            if ($existing && $this->starModel->delete($id)) {
                $this->removeStarFile($existing['photo_path'] ?? '');
                $this->removeStarFile($existing['video_path'] ?? '');
                $_SESSION['success'] = 'Star deleted';
            } else {
                $_SESSION['error'] = 'Could not delete that star';
            }
            $this->redirect($back);
        }

        $existing = $id ? $this->starModel->findById($id) : null;
        $retry = $back . '&action=' . ($id ? 'edit&id=' . $id : 'create');

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $_SESSION['error'] = 'Name is required';
            $this->redirect($retry);
        }

        $category = ($_POST['category'] ?? 'actor') === 'actress' ? 'actress' : 'actor';
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $videoUrl = trim($_POST['video_url'] ?? '');
        if ($videoUrl !== '' && Star::embedUrl($videoUrl) === '') {
            $_SESSION['error'] = 'Video link must be a YouTube or Vimeo address (https://...). To use your own file, upload it instead.';
            $this->redirect($retry);
        }

        $photoPath = $existing['photo_path'] ?? '';
        $videoPath = $existing['video_path'] ?? '';

        $newPhoto = $this->uploadStarFile('star_photo', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'], 15728640, $retry, 'Picture');
        if ($newPhoto) { $this->removeStarFile($photoPath); $photoPath = $newPhoto; }

        $newVideo = $this->uploadStarFile('star_video', ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogv', 'video/quicktime' => 'mov'], 524288000, $retry, 'Video');
        if ($newVideo) { $this->removeStarFile($videoPath); $videoPath = $newVideo; }

        if (!empty($_POST['remove_video']) && !$newVideo) { $this->removeStarFile($videoPath); $videoPath = ''; }

        $slug = $this->starModel->uniqueSlug(Validation::generateSlug($name), $id);

        $data = [
            'name' => $name,
            'slug' => $slug,
            'category' => $category,
            'known_for' => mb_substr(trim($_POST['known_for'] ?? ''), 0, 255),
            'biography' => trim($_POST['biography'] ?? ''),
            'photo_path' => $photoPath,
            'video_path' => $videoPath,
            'video_url' => $videoUrl,
            'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'status' => $status
        ];

        if ($id) {
            $result = $this->starModel->update($id, $data);
            $message = $result ? 'Star updated successfully' : 'Failed to update star';
        } else {
            $result = $this->starModel->create($data);
            $message = $result ? 'Star added successfully' : 'Failed to add star';
        }

        if ($result) {
            // Save which movies this star appears in (the form always posts movie_ids[]; none selected = none).
            $savedId = $id ?: (int)$result;
            $movieIds = (isset($_POST['movie_ids']) && is_array($_POST['movie_ids'])) ? $_POST['movie_ids'] : [];
            try { (new SiteExtras())->setStarMovies($savedId, $movieIds); } catch (Throwable $e) { error_log('Star cast save failed: ' . $e->getMessage()); }
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect($result ? $back : $retry);
    }

    /** Save an uploaded star picture/video. The extension comes from the detected MIME type, never the client's filename. */
    private function uploadStarFile($field, array $allowedExt, $maxSize, $retryUrl, $label) {
        if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $file = $_FILES[$field];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = $label . ' upload failed (error code ' . (int)$file['error'] . '). Check the file size limits in php.ini.';
            $this->redirect($retryUrl);
        }
        if ($file['size'] > $maxSize) {
            $_SESSION['error'] = $label . ' is too large (max ' . round($maxSize / 1048576) . ' MB).';
            $this->redirect($retryUrl);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowedExt[$mime])) {
            $_SESSION['error'] = $label . ' must be one of: ' . implode(', ', array_values($allowedExt)) . '.';
            $this->redirect($retryUrl);
        }
        $dir = __DIR__ . '/../../storage/uploads/stars/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $_SESSION['error'] = 'Could not create the uploads folder for stars.';
            $this->redirect($retryUrl);
        }
        $name = bin2hex(random_bytes(8)) . '_' . time() . '.' . $allowedExt[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
            $_SESSION['error'] = $label . ' could not be saved. Check folder permissions on storage/uploads/stars/.';
            $this->redirect($retryUrl);
        }
        return 'storage/uploads/stars/' . $name;
    }

    /** Delete a star file, but only inside storage/uploads/stars/. */
    private function removeStarFile($path) {
        if (!$path || strpos($path, 'storage/uploads/stars/') !== 0) return;
        $full = realpath(__DIR__ . '/../../' . $path);
        $root = realpath(__DIR__ . '/../../storage/uploads/stars');
        if ($full && $root && strpos($full, $root . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
            @unlink($full);
        }
    }

    /**
     * Handle event CRUD operations, ticket levels, discounts, physical sales and gate check-in
     */
    public function manageEvents() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_events');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleEventPost();
        }

        $event = $id ? $this->eventModel->findById($id) : null;
        if (($action === 'edit') && !$event) {
            $_SESSION['error'] = 'Event not found.';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=events');
        }

        $data = [
            'title' => 'Manage Events',
            'activeTab' => 'events',
            'action' => $action,
            'event' => $event,
            'ticketTypes' => $id ? $this->eventModel->getTicketTypes($id) : [],
            'discountRules' => $id ? $this->eventModel->getDiscountRules($id) : [],
            'salesSummary' => ($id && $action === 'edit') ? $this->eventModel->getSalesSummary($id) : null,
            'orders' => ($id && $action === 'edit') ? $this->eventModel->getOrders($id, 25) : [],
            'events' => $this->eventModel->getAll(50),
            'feeSettings' => ['enabled' => EventPublishFee::feeEnabled(), 'amount' => EventPublishFee::feeAmount(), 'currency' => EventPublishFee::feeCurrency()],
            'eventFee' => $id ? (new EventPublishFee())->forEvent($id) : null,
            'feeRecords' => (new EventPublishFee())->all(50),
            'feeStats' => (new EventPublishFee())->stats(),
            'feeAudit' => (new EventPublishFee())->auditTrail(null, 30)
        ];

        $this->renderAdmin('events', $data);
    }

    /**
     * Handle event form submissions (form_action: save_event | delete_event | physical_sale | check_in)
     */
    private function handleEventPost() {
        $formAction = $_POST['form_action'] ?? 'save_event';
        $id = intval($_POST['id'] ?? 0);
        $editUrl = '/TMZ%20PODCAST/admin/dashboard.php?tab=events&action=edit&id=' . $id;
        $listUrl = '/TMZ%20PODCAST/admin/dashboard.php?tab=events';

        if ($formAction === 'delete_event') {
            if ($id && $this->eventModel->hasPaidOrders($id)) {
                $_SESSION['error'] = 'This event has paid tickets, so it cannot be deleted. Set its status to Archived instead.';
            } else {
                $_SESSION[$id && $this->eventModel->delete($id) ? 'success' : 'error'] = $id ? 'Event deleted.' : 'Event not found.';
            }
            $this->redirect($listUrl);
        }

        if ($formAction === 'physical_sale') {
            try {
                $sale = $this->eventModel->recordPhysicalSale(
                    $id,
                    intval($_POST['ticket_type_id'] ?? 0),
                    intval($_POST['quantity'] ?? 1),
                    ['name' => $_POST['buyer_name'] ?? '', 'phone' => $_POST['buyer_phone'] ?? '', 'email' => $_POST['buyer_email'] ?? ''],
                    $_POST['discount_code'] ?? '',
                    $_SESSION['admin_id'] ?? null
                );
                $_SESSION['success'] = 'Physical sale recorded: ' . $sale['quantity'] . ' ticket(s), ' . Event::money($sale['currency'], $sale['total'])
                    . ($sale['discount'] > 0 ? ' (discount ' . Event::money($sale['currency'], $sale['discount']) . ')' : '') . '. Ref ' . $sale['reference'];
            } catch (Throwable $e) {
                $_SESSION['error'] = $e->getMessage();
            }
            $this->redirect($editUrl);
        }

        if ($formAction === 'check_in') {
            $result = $this->eventModel->checkInTicket($_POST['ticket_code'] ?? '', $id);
            $_SESSION[$result['ok'] ? 'success' : 'error'] = $result['message'];
            $this->redirect($editUrl);
        }

        // ---- publishing fee admin actions ----
        if ($formAction === 'save_fee_settings') {
            EventPublishFee::saveSettings(!empty($_POST['fee_enabled']), (float)($_POST['fee_amount'] ?? 0), strtoupper($_POST['fee_currency'] ?? 'UGX'), $_SESSION['admin_id'] ?? null);
            $_SESSION['success'] = 'Publishing fee settings saved.';
            $this->redirect($listUrl);
        }
        if ($formAction === 'grant_exemption') {
            (new EventPublishFee())->grantExemption($id, $_SESSION['admin_id'] ?? null, trim($_POST['fee_note'] ?? ''));
            $_SESSION['success'] = 'Fee exemption granted — this event can be published free.';
            $this->redirect($id ? $editUrl : $listUrl);
        }
        if ($formAction === 'revoke_exemption') {
            (new EventPublishFee())->revokeExemption($id, $_SESSION['admin_id'] ?? null);
            $_SESSION['success'] = 'Fee exemption revoked.';
            $this->redirect($id ? $editUrl : $listUrl);
        }
        if ($formAction === 'pay_event_fee') {
            $feeModel = new EventPublishFee();
            $fee = $feeModel->ensureFee($id, $_SESSION['admin_id'] ?? null);
            $payments = new Payment();
            $pay = $payments->createPendingEventFeePayment($_SESSION['admin_id'] ?? 1, (int)$fee['id'], (float)$fee['amount'], $fee['currency']);
            $this->pdo->prepare("UPDATE event_publish_fees SET payment_id=? WHERE id=?")->execute([$pay['id'], (int)$fee['id']]);
            try {
                $gw = (new PesapalGateway())->submitOrder($pay + ['reference' => $pay['reference'], 'amount' => (float)$fee['amount'], 'currency' => $fee['currency']],
                    ['title' => 'Event publishing fee — #' . $id], (new User())->findById($_SESSION['admin_id'] ?? 1));
                if (!empty($gw['success'])) {
                    $payments->setGatewayReference($pay['id'], $gw['tracking_id'] ?? '');
                    header('Location: ' . $gw['redirect_url']); exit;
                }
                $_SESSION['error'] = 'Pesapal error: ' . ($gw['message'] ?? 'unknown');
            } catch (Throwable $e) { $_SESSION['error'] = 'Payment gateway unreachable: ' . $e->getMessage(); }
            $this->redirect($editUrl);
        }

        // ---- save_event ----
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $eventDate = Event::normalizeDate($_POST['event_date'] ?? '');
        $registrationUrl = trim($_POST['registration_url'] ?? '');
        $status = in_array($_POST['status'] ?? 'draft', ['draft', 'published', 'archived', 'past'], true) ? $_POST['status'] : 'draft';

        if ($title === '') {
            $_SESSION['error'] = 'Title is required';
            $this->redirect($listUrl);
        }

        $data = [
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
            'event_date' => $eventDate,
            'registration_url' => $registrationUrl,
            'status' => $status
        ];

        // Publishing-fee gate applies ONLY to external organiser events
        // (events.user_id set). Platform/admin events (user_id NULL) publish free.
        if ($status === 'published') {
            $feeModel = new EventPublishFee();
            $prior = $id ? $this->eventModel->findById($id) : null;
            $wasPublished = $prior && $prior['status'] === 'published';
            $isOrganiserEvent = $prior && !empty($prior['user_id']);
            if (!$wasPublished && $isOrganiserEvent) {
                $gate = $feeModel->canPublish($id);
                if (!$gate['ok']) {
                    // Save as draft instead of publishing; tell admin to pay the fee.
                    $status = 'draft';
                    $data['status'] = 'draft';
                    $_SESSION['fee_pending'] = true;
                }
            }
        }

        if ($id) {
            // keep the existing slug so shared links keep working
            $result = $this->eventModel->update($id, $data);
            $message = $result ? 'Event updated successfully' : 'Failed to update event';
            if ($status === 'published' && !empty($_SESSION['fee_pending'])) { /* handled below */ }
        } else {
            $data['slug'] = $this->eventModel->uniqueSlug(Validation::generateSlug($title));
            $result = $this->eventModel->create($data);
            $message = $result ? 'Event created successfully' : 'Failed to create event';
        }

        $eventId = $id ?: (int)$result;
        // If we just created the event as draft because of the fee gate, ensure a fee row exists.
        if ($eventId && !empty($_SESSION['fee_pending'])) {
            unset($_SESSION['fee_pending']);
            $feeModel->ensureFee($eventId, $_SESSION['admin_id'] ?? null);
            $message .= ' Publishing fee of ' . EventPublishFee::feeAmount() . ' ' . EventPublishFee::feeCurrency() . ' is required before this event can go live — pay it from the Events tab.';
        }
        if ($eventId) {
            try {
                $this->eventModel->saveTicketTypes($eventId, $_POST['tickets'] ?? []);
                $this->eventModel->saveDiscountRules($eventId, $_POST['discounts'] ?? []);
            } catch (Throwable $e) {
                error_log('Event ticket save failed: ' . $e->getMessage());
                $message .= ', but ticket levels/discounts could not be saved: ' . $e->getMessage();
                $result = false;
            }
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect($eventId ? '/TMZ%20PODCAST/admin/dashboard.php?tab=events&action=edit&id=' . $eventId : $listUrl);
    }

    /**
     * Handle category CRUD operations
     */
    public function manageCategories() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_categories');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleCategoryPost();
        }

        $data = [
            'title' => 'Manage Categories',
            'activeTab' => 'categories',
            'action' => $action,
            'category' => $id ? $this->categoryModel->findById($id) : null,
            'categories' => $this->categoryModel->getAll()
        ];

        $this->renderAdmin('categories', $data);
    }

    /**
     * Handle category form submission
     */
    private function handleCategoryPost() {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = Validation::generateSlug($name);
        $description = trim($_POST['description'] ?? '');
        $categoryType = $_POST['category_type'] ?? 'article';

        if (empty($name)) {
            $_SESSION['error'] = 'Name is required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=categories');
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'category_type' => $categoryType
        ];

        if ($id) {
            $result = $this->categoryModel->update($id, $data);
            $message = $result ? 'Category updated successfully' : 'Failed to update category';
        } else {
            $result = $this->categoryModel->create($data);
            $message = $result ? 'Category created successfully' : 'Failed to create category';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=categories');
    }

    /**
     * Handle comment moderation
     */
    public function moderateComments() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_comments');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);
        $status = $_GET['status'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleCommentModeration();
        }

        $data = [
            'title' => 'Moderate Comments',
            'activeTab' => 'comments',
            'action' => $action,
            'comments' => $this->commentModel->getAll(50, 0, $status),
            'selectedStatus' => $status
        ];

        $this->renderAdmin('comments', $data);
    }

    /**
     * Handle comment moderation actions
     */
    private function handleCommentModeration() {
        $id = intval($_POST['comment_id'] ?? 0);
        $status = $_POST['status'] ?? 'approved';

        $result = false;
        if ($id) {
            $result = $this->commentModel->updateStatus($id, $status);
            $message = $result ? 'Comment status updated' : 'Failed to update comment';
        } else {
            $message = 'Invalid comment ID';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=comments');
    }

    /**
     * Handle contact message management
     */
    public function manageContacts() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_contacts');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);
        $status = $_GET['status'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleContactPost();
        }

        $data = [
            'title' => 'Contact Messages',
            'activeTab' => 'contacts',
            'action' => $action,
            'contacts' => $this->contactModel->getAll(50, 0, $status),
            'selectedStatus' => $status
        ];

        $this->renderAdmin('contacts', $data);
    }

    /**
     * Handle contact message actions
     */
    private function handleContactPost() {
        $id = intval($_POST['message_id'] ?? 0);
        $status = $_POST['status'] ?? 'read';

        $result = false;
        if ($id) {
            $result = $this->contactModel->updateStatus($id, $status);
            $message = $result ? 'Message status updated' : 'Failed to update message';
        } else {
            $message = 'Invalid message ID';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=contacts');
    }

    /**
     * Handle newsletter management
     */
    public function manageNewsletter() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_newsletter');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);
        $status = $_GET['status'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleNewsletterPost();
        }

        $data = [
            'title' => 'Newsletter Subscribers',
            'activeTab' => 'newsletter',
            'action' => $action,
            'subscribers' => $this->newsletterModel->getAll(50, 0, $status),
            'selectedStatus' => $status
        ];

        $this->renderAdmin('newsletter', $data);
    }

    /**
     * Handle newsletter actions
     */
    private function handleNewsletterPost() {
        $id = intval($_POST['subscriber_id'] ?? 0);
        $action = $_POST['action'] ?? 'unsubscribe';

        $result = false;
        if ($id) {
            if ($action === 'unsubscribe') {
                $result = $this->newsletterModel->unsubscribe($id);
                $message = $result ? 'Subscriber unsubscribed' : 'Failed to unsubscribe';
            } else {
                $result = $this->newsletterModel->delete($id);
                $message = $result ? 'Subscriber deleted' : 'Failed to delete subscriber';
            }
        } else {
            $message = 'Invalid subscriber ID';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=newsletter');
    }

    /**
     * Handle sponsor CRUD operations
     */
    public function manageSponsors() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_sponsors');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleSponsorPost();
        }

        $data = [
            'title' => 'Manage Sponsors',
            'activeTab' => 'sponsors',
            'action' => $action,
            'sponsor' => $id ? $this->sponsorModel->findById($id) : null,
            'sponsors' => $this->sponsorModel->getAll()
        ];

        $this->renderAdmin('sponsors', $data);
    }

    /**
     * Handle sponsor form submission
     */
    private function handleSponsorPost() {
        $id = intval($_POST['id'] ?? 0);
        $companyName = trim($_POST['company_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($companyName)) {
            $_SESSION['error'] = 'Company name is required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=sponsors');
        }

        $data = [
            'company_name' => $companyName,
            'contact_person' => $contactPerson,
            'email' => $email,
            'phone' => $phone,
            'website' => $website,
            'status' => $status
        ];

        if ($id) {
            $result = $this->sponsorModel->update($id, $data);
            $message = $result ? 'Sponsor updated successfully' : 'Failed to update sponsor';
        } else {
            $result = $this->sponsorModel->create($data);
            $message = $result ? 'Sponsor created successfully' : 'Failed to create sponsor';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=sponsors');
    }

    /**
     * Handle advertisement CRUD operations
     */
    public function manageAdvertisements() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_advertisements');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleAdvertisementPost();
        }

        $data = [
            'title' => 'Manage Advertisements',
            'activeTab' => 'advertisements',
            'action' => $action,
            'advertisement' => $id ? $this->advertisementModel->findById($id) : null,
            'advertisements' => $this->advertisementModel->getAll(50),
            'sponsors' => $this->sponsorModel->getAll(),
            'images' => $this->mediaModel->getByType('image', 100)
        ];

        $this->renderAdmin('advertisements', $data);
    }

    /**
     * Handle advertisement form submission
     */
    private function handleAdvertisementPost() {
        $id = intval($_POST['id'] ?? 0);
        $sponsorId = intval($_POST['sponsor_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $targetUrl = trim($_POST['target_url'] ?? '');
        $placement = $_POST['placement'] ?? 'homepage';
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $status = $_POST['status'] ?? 'active';
        $existingAdvertisement = $id ? $this->advertisementModel->findById($id) : null;
        $imageMediaId = intval($_POST['image_media_id'] ?? 0) ?: ($existingAdvertisement['image_media_id'] ?? null);
        $uploadedImage = $this->uploadMovieAsset('advertisement_image_file', 'image');
        if ($uploadedImage) $imageMediaId = $uploadedImage;

        if (empty($startDate) || empty($endDate)) {
            $_SESSION['error'] = 'Start and end dates are required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=advertisements');
        }

        $data = [
            'sponsor_id' => $sponsorId ?: null,
            'title' => $title,
            'image_media_id' => $imageMediaId,
            'target_url' => $targetUrl,
            'placement' => $placement,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status
        ];

        if ($id) {
            $result = $this->advertisementModel->update($id, $data);
            $message = $result ? 'Advertisement updated successfully' : 'Failed to update advertisement';
        } else {
            $result = $this->advertisementModel->create($data);
            $message = $result ? 'Advertisement created successfully' : 'Failed to create advertisement';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=advertisements');
    }

    /**
     * Handle media library management
     */
    public function manageMedia() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_media');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);
        $type = $_GET['type'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleMediaPost();
        }

        $data = [
            'title' => 'Media Library',
            'activeTab' => 'media',
            'action' => $action,
            'media' => $this->mediaModel->getAll(50, 0, $type),
            'selectedType' => $type,
            'uploadJobs' => (new UploadJob())->recent(30)
        ];

        $this->renderAdmin('media', $data);
    }

    /**
     * Handle media upload
     */
    private function handleMediaPost() {
        $action = $_POST['action'] ?? 'upload';

        if ($action === 'upload') {
            $this->handleMediaUpload();
        } elseif ($action === 'delete') {
            $this->handleMediaDelete();
        } elseif ($action === 'retry_job') {
            CSRF::requireValidToken();
            $jobId = (int)($_POST['job_id'] ?? 0);
            $jobs = new UploadJob();
            $job = $jobs->get($jobId);
            if ($job && $job['kind'] === 'rendition') {
                // Requeue: reset to queued so the worker picks it up (attempts preserved for max_attempts guard).
                $jobs->update($jobId, ['status' => 'queued', 'progress' => 0, 'last_error' => null]);
                $payload = json_decode((string)$job['payload'], true) ?: [];
                if (!empty($payload['src'])) { RenditionBuilder::spawnWorkerPublic($jobId); }
                $_SESSION['success'] = 'Rendition job re-queued.';
            } else {
                $_SESSION['error'] = 'Job not found or not a rendition job.';
            }
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
        } elseif ($action === 'download_backup') {
            CSRF::requireValidToken();
            $name = basename((string)($_POST['bundle'] ?? ''));
            $dir = __DIR__ . '/../../storage/backups/' . $name;
            $file = $dir . DIRECTORY_SEPARATOR . basename((string)($_POST['file'] ?? 'database.sql'));
            if ($name === '' || !is_file($file) || strpos(realpath($file), realpath($dir)) !== 0) {
                $_SESSION['error'] = 'Backup file not found.';
            } else {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
            }
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
        } elseif ($action === 'run_backup') {
            CSRF::requireValidToken();
            require_once __DIR__ . '/../Services/BackupManager.php';
            $r = (new BackupManager())->create(['checksum' => !empty($_POST['backup_checksum'])]);
            if ($r['ok']) {
                $s = $r['stats'];
                $_SESSION['success'] = "Backup {$r['name']} created: {$s['tables']} tables, {$s['media_files']} media files ({$s['missing_files']} missing). Copy the bundle offsite — same-disk is not a backup.";
            } else {
                $_SESSION['error'] = 'Backup failed: ' . ($r['error'] ?? 'unknown');
            }
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
        } elseif ($action === 'bulk_renditions') {
            CSRF::requireValidToken();
            $queued = 0; $skipped = 0;
            $jobs = new UploadJob();
            foreach ($this->mediaModel->getAll(500, 0, 'video') as $m) {
                $ren = $jobs->rendition((int)$m['id'], '720p', 'local');
                if ($ren && $ren['status'] === 'ready') { $skipped++; continue; }
                $abs = __DIR__ . '/../../' . ltrim((string)$m['storage_path'], '/');
                if (!is_file($abs)) { $skipped++; continue; }
                $r = RenditionBuilder::enqueue((int)$m['id'], $abs);
                if (!empty($r['ok'])) $queued++; else $skipped++;
            }
            $_SESSION['success'] = "Rendition jobs queued: {$queued}. Skipped (already ready/missing): {$skipped}. Originals are never modified.";
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
        } elseif ($action === 'cancel_job') {
            CSRF::requireValidToken();
            $jobId = (int)($_POST['job_id'] ?? 0);
            $job = (new UploadJob())->get($jobId);
            if ($job && in_array($job['status'], ['queued', 'failed'], true)) {
                (new UploadJob())->update($jobId, ['status' => 'cancelled']);
                $_SESSION['success'] = 'Job cancelled.';
            }
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
        }
    }

    /**
     * Handle media file upload
     */
    private function handleMediaUpload() {
        $uploadDir = __DIR__ . '/../../storage/uploads/';
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/ogg',
            'audio/mpeg', 'audio/wav', 'audio/ogg',
            'application/pdf'
        ];

        // Movies can be large; PHP upload_max_filesize/post_max_size are configured for this workflow.
        $result = $this->handleFileUpload('media_file', $uploadDir, $allowedTypes, 5368709120); // 5GB

        if (!$result[0]) {
            $_SESSION['error'] = $result[2];
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
        }

        $filePath = $result[1];
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath);

        // Determine media type
        $mediaType = 'document';
        if (strpos($mimeType, 'image/') === 0) {
            $mediaType = 'image';
        } elseif (strpos($mimeType, 'video/') === 0) {
            $mediaType = 'video';
        } elseif (strpos($mimeType, 'audio/') === 0) {
            $mediaType = 'audio';
        }

        // Get image dimensions if applicable
        $width = null;
        $height = null;
        if ($mediaType === 'image') {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        $data = [
            'uploaded_by' => $_SESSION['admin_id'] ?? null,
            'original_filename' => $_FILES['media_file']['name'],
            'stored_filename' => $fileName,
            'storage_path' => 'storage/uploads/' . $fileName,
            'media_type' => $mediaType,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'alt_text' => trim($_POST['alt_text'] ?? '')
        ];

        $result = $this->mediaModel->create($data);
        
        if ($result) {
            $queued = ($mediaType === 'video') ? self::queueRenditionJob($filePath, (int)$result) : '';
            $_SESSION['success'] = 'Media uploaded successfully' . $queued;
        } else {
            $_SESSION['error'] = 'Failed to save media information';
        }

        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
    }

    /** Queue a non-destructive 720p rendition for an uploaded video. Returns a status suffix for the flash message. */
    private static function queueRenditionJob($filePath, $mediaId = null) {
        $r = RenditionBuilder::enqueue($mediaId ?: null, $filePath);
        if (empty($r['ok'])) return isset($r['message']) ? ' Note: ' . $r['message'] : '';
        return ' — 720p rendition queued (original preserved).';
    }

    /**
     * Handle media deletion
     */
    private function handleMediaDelete() {
        $id = intval($_POST['media_id'] ?? 0);

        if ($id) {
            $media = $this->mediaModel->findById($id);
            
            if ($media) {
                $force = !empty($_POST['force']);
                $inUse = $this->mediaModel->isMediaUsed($id);
                // Check if media is used anywhere
                if (!$force && $inUse) {
                    $_SESSION['error'] = 'Cannot delete media that is in use. Edit the pages using it first, or use "Delete anyway".';
                    $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
                }

                // Delete file
                $filePath = __DIR__ . '/../../' . $media['storage_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // Delete database record
                $result = $this->mediaModel->delete($id);
                $_SESSION[$result ? 'success' : 'error'] = $result
                    ? 'Media deleted successfully' . ($force && $inUse ? ' (it was still referenced by content)' : '')
                    : 'Failed to delete media';
            }
        }

        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=media');
    }

    /**
     * Handle user management
     */
    public function manageUsers() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_users');

        $action = $_GET['action'] ?? 'list';
        $id = intval($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleUserPost();
        }

        $data = [
            'title' => 'Manage Users',
            'activeTab' => 'users',
            'action' => $action,
            'user' => $id ? $this->userModel->findById($id) : null,
            'users' => $this->userModel->getAll(50)
        ];

        $this->renderAdmin('users', $data);
    }

    /**
     * Handle user form submission
     */
    private function handleUserPost() {
        $id = intval($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = intval($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($username) || empty($email)) {
            $_SESSION['error'] = 'Username and email are required';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=users');
        }

        $data = [
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'role_id' => $roleId ?: null,
            'status' => $status
        ];

        if ($password) {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($id) {
            $result = $this->userModel->update($id, $data);
            $message = $result ? 'User updated successfully' : 'Failed to update user';
        } else {
            if (empty($password)) {
                $_SESSION['error'] = 'Password is required for new users';
                $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=users');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $result = $this->userModel->create($data);
            $message = $result ? 'User created successfully' : 'Failed to create user';
        }

        $_SESSION[$result ? 'success' : 'error'] = $message;
        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=users');
    }

    /**
     * Handle website settings
     */
    public function manageSettings() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('manage_settings');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CSRF::requireValidToken();
            $this->handleSettingsPost();
        }

        $settingsRows = $this->settingModel->getAll();
        $settings = [];
        foreach ($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $data = [
            'title' => 'Website Settings',
            'activeTab' => 'settings',
            'settings' => $settings,
            'subscriptionPlans' => $this->subscriptionPlanModel->getActive()
        ];

        $this->renderAdmin('settings', $data);
    }

    /**
     * Handle settings form submission
     */
    private function handleSettingsPost() {
        $settings = [];
        
        $postedSettings = $_POST['settings'] ?? $_POST;
        $contactEmail = trim((string) ($postedSettings['contact_email'] ?? ''));
        if ($contactEmail !== '' && !Validation::validateEmail($contactEmail)) {
            $_SESSION['error'] = 'Please enter a valid contact email address';
            $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=settings');
        }

        $allowedKeys = ['site_name','site_description','contact_email','contact_phone','site_location','social_twitter','social_instagram','social_youtube','pesapal_consumer_key','pesapal_consumer_secret','pesapal_environment','pesapal_ipn_url','pesapal_notification_id','pesapal_callback_url','livekit_url','livekit_api_key','livekit_api_secret','livekit_recording_s3_access_key','livekit_recording_s3_secret','livekit_recording_s3_region','livekit_recording_s3_bucket','livekit_recording_s3_endpoint','livekit_recording_s3_force_path_style','downloads_enabled','video_compression_enabled','ffmpeg_path'];
        foreach ($allowedKeys as $key) {
            if ($key === 'downloads_enabled' || $key === 'video_compression_enabled') {
                $settings[$key] = !empty($postedSettings[$key]) ? '1' : '0';
                continue;
            }
            if (array_key_exists($key, $postedSettings)) {
                $value = trim((string) $postedSettings[$key]);
                if (in_array($key, ['livekit_api_secret','livekit_recording_s3_access_key','livekit_recording_s3_secret'], true) && $value === '') continue;
                $settings[$key] = $value;
            }
        }

        $result = $this->settingModel->setMultiple($settings);

        foreach ($this->subscriptionPlanModel->getActive() as $plan) {
            $key = $plan['plan_key'];
            $price = $_POST['subscription_price'][$key] ?? $plan['price'];
            $currency = $_POST['subscription_currency'][$key] ?? $plan['currency'];
            $discount = $_POST['subscription_discount'][$key] ?? $plan['discount_percent'];
            $this->subscriptionPlanModel->savePrice($key, $price, $currency, $discount);
        }
        
        if ($result) {
            $_SESSION['success'] = 'Settings updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update settings';
        }

        $this->redirect('/TMZ%20PODCAST/admin/dashboard.php?tab=settings');
    }

    /**
     * View activity logs
     */
    public function viewLogs() {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requirePermission('view_analytics');

        $data = [
            'title' => 'Activity Logs',
            'activeTab' => 'logs',
            'logs' => $this->logModel->getAll(100)
        ];

        $this->renderAdmin('logs', $data);
    }
}
?>
