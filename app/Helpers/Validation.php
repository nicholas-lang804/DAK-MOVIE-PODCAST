<?php
/**
 * Validation Helper
 * Common validation functions for input sanitization
 */

class Validation {
    /**
     * Sanitize input string
     * @param string $input
     * @return string
     */
    public static function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL
     * @param string $url
     * @return bool
     */
    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate integer
     * @param mixed $value
     * @return bool
     */
    public static function validateInt($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate file upload
     * @param array $file
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateFile($file, $allowedTypes = [], $maxSize = 10485760) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error: ' . $file['error']];
        }

        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds maximum allowed size'];
        }

        if (!empty($allowedTypes)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ['valid' => false, 'error' => 'Invalid file type'];
            }
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Generate slug from string
     * @param string $string
     * @return string
     */
    public static function generateSlug($string) {
        $slug = strtolower($string);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Validate password strength
     * @param string $password
     * @param int $minLength
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validatePassword($password, $minLength = 8) {
        if (strlen($password) < $minLength) {
            return ['valid' => false, 'error' => "Password must be at least {$minLength} characters"];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one uppercase letter'];
        }

        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one lowercase letter'];
        }

        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one number'];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Escape output for safe display
     * @param string $output
     * @return string
     */
    public static function escape($output) {
        return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    }
}
?>
