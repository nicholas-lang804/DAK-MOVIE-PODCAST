<?php
/**
 * Movie Controller
 * Handles movie catalogue and reviews
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/Movie.php';
require_once __DIR__ . '/../Models/MovieReview.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Comment.php';
require_once __DIR__ . '/../Models/Payment.php';
require_once __DIR__ . '/../Models/SiteSetting.php';

class MovieController extends Controller {
    private $movieModel;
    private $reviewModel;
    private $categoryModel;
    private $commentModel;
    private $paymentModel;

    public function __construct() {
        parent::__construct();
        $this->movieModel = new Movie();
        $this->reviewModel = new MovieReview();
        $this->categoryModel = new Category();
        $this->commentModel = new Comment();
        $this->paymentModel = new Payment();
    }

    /**
     * Display movie catalogue
     */
    public function index() {
        $searchQuery = $this->getSearchQuery();
        $genreId = $this->getFilter('genre');
        [$limit, $offset, $page] = $this->getPagination(12);

        if ($searchQuery) {
            $movies = $this->movieModel->search($searchQuery, $limit);
        } elseif ($genreId) {
            $movies = $this->movieModel->getByGenre($genreId, $limit, $offset);
        } else {
            $movies = $this->movieModel->getPublished($limit, $offset);
        }

        $genres = $this->categoryModel->getMovieGenresWithCounts();
        $totalCount = $genreId ? $this->movieModel->getPublishedCountByGenre($genreId) : $this->movieModel->getPublishedCount();
        $totalPages = ceil($totalCount / $limit);

        $data = [
            'title' => 'Movies & Reviews',
            'description' => 'Browse our movie catalogue and read professional reviews.',
            'movies' => $movies,
            'genres' => $genres,
            'searchQuery' => $searchQuery,
            'selectedGenre' => $genreId,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount
        ];

        $this->render('movies', $data);
    }

    /**
     * Display single movie
     * @param string $slug
     */
    public function show($slug) {
        $movie = $this->movieModel->findBySlug($slug);
        
        if (!$movie || $movie['status'] !== 'published') {
            http_response_code(404);
            $this->render('404', ['title' => 'Movie Not Found']);
            return;
        }

        // Get reviews
        $reviews = $this->reviewModel->getByMovie($movie['id'], 'published');
        $averageRating = $this->reviewModel->getAverageRating($movie['id']);
        $reviewCount = $this->reviewModel->getReviewCount($movie['id']);
        
        // Get comments
        $comments = $this->commentModel->getByContent('movie', $movie['id'], 'approved');
        $userId = $_SESSION['user_id'] ?? 0;
        $canWatch = $movie['access_type'] !== 'paid' || ($userId && $this->paymentModel->hasMovieAccess($userId, $movie['id']));

        $data = [
            'title' => $movie['title'],
            'description' => $movie['synopsis'] ?? 'View movie details and reviews.',
            'movie' => $movie,
            'reviews' => $reviews,
            'averageRating' => $averageRating,
            'reviewCount' => $reviewCount,
            'comments' => $comments,
            'canWatch' => $canWatch,
            'canDownload' => $canWatch,
            'downloadsEnabled' => SiteSetting::downloadsEnabled()
        ];

        $this->render('movie-detail', $data);
    }

    /**
     * Handle comment submission
     */
    public function addComment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/TMZ%20PODCAST/movies.php');
        }

        $movieId = intval($_POST['movie_id'] ?? 0);
        $userName = trim($_POST['user_name'] ?? '');
        $userEmail = trim($_POST['user_email'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if (empty($userName) || empty($comment)) {
            $_SESSION['comment_error'] = 'Please fill in all required fields';
            $this->redirect('/TMZ%20PODCAST/movies.php');
        }

        $result = $this->commentModel->create([
            'movie_id' => $movieId,
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

        $this->redirect('/TMZ%20PODCAST/movies.php');
    }
}
?>
