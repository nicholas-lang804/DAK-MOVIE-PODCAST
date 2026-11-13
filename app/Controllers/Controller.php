<?php
/**
 * Base Controller
 * Provides common functionality for all controllers
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Helpers/Validation.php';
require_once __DIR__ . '/../Middleware/CSRF.php';
require_once __DIR__ . '/../Middleware/Auth.php';
require_once __DIR__ . '/../Helpers/Ads.php';
require_once __DIR__ . '/../Models/SiteSetting.php';

abstract class Controller {
    protected $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Render view with layout
     * @param string $view
     * @param array $data
     * @param string $layout
     */
    protected function render($view, $data = [], $layout = 'main') {
        if ($layout === 'main') {
            $settings = [];
            foreach ((new SiteSetting())->getAll() as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            $data['contactEmail'] = $settings['contact_email'] ?? 'info@tmzpodcast.com';
            $data['contactPhone'] = $settings['contact_phone'] ?? '+254700000000';
            $data['contactLocation'] = $settings['site_location'] ?? 'Kampala, Uganda';
        }

        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the view
        $viewsRoot = __DIR__ . '/../../resources/views/';
        $viewPath = $viewsRoot . ltrim($view, '/') . '.php';
        if (!file_exists($viewPath)) {
            $viewPath = $viewsRoot . 'pages/' . ltrim($view, '/') . '.php';
        }
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            die("View not found: $view");
        }
        
        // Get the view content
        $content = ob_get_clean();
        
        // Include the layout
        $layoutPath = __DIR__ . '/../../resources/views/layouts/' . $layout . '.php';
        if (file_exists($layoutPath)) {
            include $layoutPath;
        } else {
            echo $content;
        }
    }

    /**
     * Render admin view
     * @param string $view
     * @param array $data
     */
    protected function renderAdmin($view, $data = []) {
        $this->render('admin/' . ltrim($view, '/'), $data, 'admin');
    }

    /**
     * Redirect to URL
     * @param string $url
     */
    protected function redirect($url) {
        header("Location: $url");
        exit();
    }

    /**
     * Get JSON request body
     * @return array
     */
    protected function getJsonInput() {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }

    /**
     * Return JSON response
     * @param array $data
     * @param int $statusCode
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }

    /**
     * Get pagination parameters
     * @param int $defaultLimit
     * @return array [limit, offset, page]
     */
    protected function getPagination($defaultLimit = 12) {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(1, intval($_GET['limit'] ?? $defaultLimit));
        $offset = ($page - 1) * $limit;
        
        return [$limit, $offset, $page];
    }

    /**
     * Get search query
     * @return string
     */
    protected function getSearchQuery() {
        return trim($_GET['q'] ?? '');
    }

    /**
     * Get filter parameter
     * @param string $key
     * @return string|null
     */
    protected function getFilter($key) {
        return $_GET[$key] ?? null;
    }

    /**
     * Validate required fields
     * @param array $data
     * @param array $required
     * @return array [valid, errors]
     */
    protected function validateRequired($data, $required) {
        $errors = [];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "$field is required";
            }
        }
        
        return [empty($errors), $errors];
    }

    /**
     * Handle file upload
     * @param string $fieldName
     * @param string $uploadDir
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array [success, filePath, error]
     */
    protected function handleFileUpload($fieldName, $uploadDir, $allowedTypes = [], $maxSize = 10485760) {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return [false, null, 'No file uploaded or upload error'];
        }

        $file = $_FILES[$fieldName];
        
        // Validate file
        $validation = Validation::validateFile($file, $allowedTypes, $maxSize);
        if (!$validation['valid']) {
            return [false, null, $validation['error']];
        }

        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [true, $filePath, null];
        } else {
            return [false, null, 'Failed to move uploaded file'];
        }
    }

    /**
     * Log activity
     * @param string $action
     * @param string $description
     */
    protected function logActivity($action, $description = '') {
        AuthMiddleware::logActivity($action, $description);
    }
}
?>
