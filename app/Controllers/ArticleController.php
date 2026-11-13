<?php
/**
 * Article Controller
 * Handles entertainment news and article pages
 */

require_once __DIR__ . '/../Controllers/Controller.php';
require_once __DIR__ . '/../Models/Article.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Comment.php';

class ArticleController extends Controller {
    private $articleModel;
    private $categoryModel;
    private $commentModel;

    public function __construct() {
        parent::__construct();
        $this->articleModel = new Article();
        $this->categoryModel = new Category();
        $this->commentModel = new Comment();
    }

    /**
     * Display article library
     */
    public function index() {
        $searchQuery = $this->getSearchQuery();
        $categoryId = $this->getFilter('category');
        [$limit, $offset, $page] = $this->getPagination(12);

        if ($searchQuery) {
            $articles = $this->articleModel->search($searchQuery, $limit);
        } elseif ($categoryId) {
            $articles = $this->articleModel->getByCategory($categoryId, $limit, $offset);
        } else {
            $articles = $this->articleModel->getPublished($limit, $offset);
        }

        $categories = $this->categoryModel->getByType('article');
        $totalCount = $this->articleModel->getPublishedCount();
        $totalPages = ceil($totalCount / $limit);

        $data = [
            'title' => 'Entertainment News',
            'description' => 'Latest entertainment news, celebrity stories, and industry updates.',
            'articles' => $articles,
            'categories' => $categories,
            'searchQuery' => $searchQuery,
            'selectedCategory' => $categoryId,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount
        ];

        $this->render('articles', $data);
    }

    /**
     * Display single article
     * @param string $slug
     */
    public function show($slug) {
        $article = $this->articleModel->findBySlug($slug);
        
        if (!$article || $article['status'] !== 'published') {
            http_response_code(404);
            $this->render('404', ['title' => 'Article Not Found']);
            return;
        }

        // Get related articles
        $relatedArticles = $this->articleModel->getPublished(4);
        
        // Get comments
        $comments = $this->commentModel->getByContent('article', $article['id'], 'approved');

        $data = [
            'title' => $article['title'],
            'description' => $article['excerpt'] ?? 'Read this entertainment news article.',
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'comments' => $comments
        ];

        $this->render('article-detail', $data);
    }

    /**
     * Handle comment submission
     */
    public function addComment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/TMZ%20PODCAST/news.php');
        }

        $articleId = intval($_POST['article_id'] ?? 0);
        $userName = trim($_POST['user_name'] ?? '');
        $userEmail = trim($_POST['user_email'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if (empty($userName) || empty($comment)) {
            $_SESSION['comment_error'] = 'Please fill in all required fields';
            $this->redirect('/TMZ%20PODCAST/news.php');
        }

        $result = $this->commentModel->create([
            'article_id' => $articleId,
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

        $this->redirect('/TMZ%20PODCAST/news.php');
    }
}
?>
