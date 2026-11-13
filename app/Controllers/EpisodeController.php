<?php
/**
 * Episode Controller
 * Handles podcast episode pages
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/Episode.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Comment.php';
require_once __DIR__ . '/../Models/Guest.php';

class EpisodeController extends Controller {
    private $episodeModel;
    private $categoryModel;
    private $commentModel;
    private $guestModel;

    public function __construct() {
        parent::__construct();
        $this->episodeModel = new Episode();
        $this->categoryModel = new Category();
        $this->commentModel = new Comment();
        $this->guestModel = new Guest();
    }

    /**
     * Display episode library
     */
    public function index() {
        $searchQuery = $this->getSearchQuery();
        $categoryId = $this->getFilter('category');
        [$limit, $offset, $page] = $this->getPagination(12);

        if ($searchQuery) {
            $episodes = $this->episodeModel->search($searchQuery, $limit);
        } elseif ($categoryId) {
            $episodes = $this->episodeModel->getByCategory($categoryId, $limit, $offset);
        } else {
            $episodes = $this->episodeModel->getPublished($limit, $offset);
        }

        $categories = $this->categoryModel->getByType('episode');
        $totalCount = $this->episodeModel->getPublishedCount();
        $totalPages = ceil($totalCount / $limit);

        $data = [
            'title' => 'Podcast Episodes',
            'description' => 'Browse our complete library of podcast episodes.',
            'episodes' => $episodes,
            'categories' => $categories,
            'searchQuery' => $searchQuery,
            'selectedCategory' => $categoryId,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount
        ];

        $this->render('episodes', $data);
    }

    /**
     * Display single episode
     * @param string $slug
     */
    public function show($slug) {
        $episode = $this->episodeModel->findBySlug($slug);
        
        if (!$episode || $episode['status'] !== 'published') {
            http_response_code(404);
            $this->render('404', ['title' => 'Episode Not Found']);
            return;
        }

        // Get related episodes
        $relatedEpisodes = $this->episodeModel->getPublished(4);
        
        // Get comments
        $comments = $this->commentModel->getByContent('episode', $episode['id'], 'approved');
        
        // Get guests
        $guests = $this->guestModel->getAll();

        $data = [
            'title' => $episode['title'],
            'description' => $episode['description'] ?? 'Listen to this podcast episode.',
            'episode' => $episode,
            'relatedEpisodes' => $relatedEpisodes,
            'comments' => $comments,
            'guests' => $guests
        ];

        $this->render('episode-detail', $data);
    }

    /**
     * Handle comment submission
     */
    public function addComment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/TMZ%20PODCAST/episodes.php');
        }

        $episodeId = intval($_POST['episode_id'] ?? 0);
        $userName = trim($_POST['user_name'] ?? '');
        $userEmail = trim($_POST['user_email'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if (empty($userName) || empty($comment)) {
            $_SESSION['comment_error'] = 'Please fill in all required fields';
            $this->redirect('/TMZ%20PODCAST/episodes.php');
        }

        $result = $this->commentModel->create([
            'episode_id' => $episodeId,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'comment' => $comment,
            'status' => 'pending'
        ]);

        if ($result) {
            $_SESSION['comment_success'] = 'Your comment has been submitted and is awaiting approval.';
        } else {
            $_SESSION['comment_error'] = 'Failed to submit comment. Please try again.';
        }

        $this->redirect('/TMZ%20PODCAST/episodes.php');
    }
}
?>
