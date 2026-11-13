<?php
/**
 * Star Controller
 * Public pages for actors & actresses (open to visitors and logged-in members alike).
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/Star.php';

class StarController extends Controller {
    private $starModel;

    public function __construct() {
        parent::__construct();
        $this->starModel = new Star();
    }

    public function index() {
        $category = $_GET['type'] ?? '';
        if (!in_array($category, ['actor', 'actress'], true)) $category = '';
        $search = $this->getSearchQuery();
        list($limit, $offset, $page) = $this->getPagination(12);
        $limit = min($limit, 48);

        $total = $this->starModel->getActiveCount($category, $search);
        $this->render('stars', [
            'title' => 'Actors & Actresses',
            'description' => 'Meet the stars of the DAK Film: profiles, pictures and videos of your favourite actors and actresses.',
            'stars' => $this->starModel->getActive($limit, $offset, $category, $search),
            'category' => $category,
            'search' => $search,
            'page' => $page,
            'totalPages' => max(1, (int)ceil($total / $limit)),
            'total' => $total
        ]);
    }

    public function show($slug) {
        $star = $this->starModel->findBySlug($slug);
        if (!$star) {
            http_response_code(404);
            $this->render('404', ['title' => 'Star Not Found']);
            return;
        }
        $this->render('star-detail', [
            'title' => $star['name'],
            'description' => $star['known_for'] ?: ('Meet ' . $star['name'] . ' on THE DAK FILM PODCAST MEDIA.'),
            'star' => $star,
            'related' => $this->starModel->getRelated($star['id'], 6)
        ]);
    }
}
?>
