<?php
/**
 * Host Controller
 * Handles host and guest profile pages
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/Host.php';
require_once __DIR__ . '/../Models/Guest.php';
require_once __DIR__ . '/../Models/Episode.php';

class HostController extends Controller {
    private $hostModel;
    private $guestModel;
    private $episodeModel;

    public function __construct() {
        parent::__construct();
        $this->hostModel = new Host();
        $this->guestModel = new Guest();
        $this->episodeModel = new Episode();
    }

    /**
     * Display hosts and guests page
     */
    public function index() {
        $hosts = $this->hostModel->getAll();
        $guests = $this->guestModel->getAll();

        $data = [
            'title' => 'Hosts & Guests',
            'description' => 'Meet our podcast hosts and featured guests.',
            'hosts' => $hosts,
            'guests' => $guests
        ];

        $this->render('hosts', $data);
    }

    /**
     * Display single host profile
     * @param string $slug
     */
    public function show($slug) {
        $host = $this->hostModel->findBySlug($slug);
        
        if (!$host || $host['status'] !== 'active') {
            http_response_code(404);
            $this->render('404', ['title' => 'Host Not Found']);
            return;
        }

        $episodes = $this->episodeModel->getPublished(10);

        $data = [
            'title' => $host['name'],
            'description' => $host['biography'] ?? 'Podcast host profile.',
            'host' => $host,
            'episodes' => $episodes
        ];

        $this->render('host-detail', $data);
    }
}
?>
