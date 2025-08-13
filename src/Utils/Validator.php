<?php

namespace LocalSearch\Utils;

/**
 * Validator utility class for input validation and sanitization
 */
class Validator
{
    /**
     * Sanitize string input
     */
    public function sanitizeString(?string $input): string
    {
        if ($input === null) {
            return '';
        }
        
        // Remove null bytes and trim
        $input = str_replace("\0", '', trim($input));
        
        // Convert to UTF-8 if needed
        if (!mb_check_encoding($input, 'UTF-8')) {
            $input = mb_convert_encoding($input, 'UTF-8', 'auto');
        }
        
        return $input;
    }

    /**
     * Sanitize integer input
     */
    public function sanitizeInt(?string $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }
        
        $filtered = filter_var($input, FILTER_VALIDATE_INT);
        return $filtered !== false ? $filtered : null;
    }

    /**
     * Sanitize float input
     */
    public function sanitizeFloat(?string $input): ?float
    {
        if ($input === null || $input === '') {
            return null;
        }
        
        $filtered = filter_var($input, FILTER_VALIDATE_FLOAT);
        return $filtered !== false ? $filtered : null;
    }

    /**
     * Sanitize email input
     */
    public function sanitizeEmail(?string $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }
        
        $email = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Sanitize URL input
     */
    public function sanitizeUrl(?string $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }
        
        $url = filter_var(trim($input), FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Validate required field
     */
    public function required($value, string $fieldName = 'Field'): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException("{$fieldName} is required");
        }
    }

    /**
     * Validate string length
     */
    public function validateLength(string $value, int $min = 0, int $max = PHP_INT_MAX, string $fieldName = 'Field'): void
    {
        $length = mb_strlen($value);
        
        if ($length < $min) {
            throw new \InvalidArgumentException("{$fieldName} must be at least {$min} characters long");
        }
        
        if ($length > $max) {
            throw new \InvalidArgumentException("{$fieldName} must not exceed {$max} characters");
        }
    }

    /**
     * Validate integer range
     */
    public function validateRange(int $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX, string $fieldName = 'Field'): void
    {
        if ($value < $min || $value > $max) {
            throw new \InvalidArgumentException("{$fieldName} must be between {$min} and {$max}");
        }
    }

    /**
     * Validate email format
     */
    public function validateEmail(string $email, string $fieldName = 'Email'): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("{$fieldName} is not a valid email address");
        }
    }

    /**
     * Validate URL format
     */
    public function validateUrl(string $url, string $fieldName = 'URL'): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("{$fieldName} is not a valid URL");
        }
    }

    /**
     * Validate domain name
     */
    public function validateDomain(string $domain, string $fieldName = 'Domain'): void
    {
        // Basic domain validation
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            throw new \InvalidArgumentException("{$fieldName} is not a valid domain name");
        }
    }

    /**
     * Validate that value is in allowed list
     */
    public function validateInList($value, array $allowedValues, string $fieldName = 'Field'): void
    {
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            throw new \InvalidArgumentException("{$fieldName} must be one of: {$allowed}");
        }
    }

    /**
     * Validate array contains only allowed values
     */
    public function validateArrayValues(array $values, array $allowedValues, string $fieldName = 'Field'): void
    {
        $invalid = array_diff($values, $allowedValues);
        if (!empty($invalid)) {
            $invalidStr = implode(', ', $invalid);
            $allowedStr = implode(', ', $allowedValues);
            throw new \InvalidArgumentException("{$fieldName} contains invalid values: {$invalidStr}. Allowed values: {$allowedStr}");
        }
    }

    /**
     * Validate date format
     */
    public function validateDate(string $date, string $format = 'Y-m-d', string $fieldName = 'Date'): void
    {
        $dateTime = \DateTime::createFromFormat($format, $date);
        if (!$dateTime || $dateTime->format($format) !== $date) {
            throw new \InvalidArgumentException("{$fieldName} must be a valid date in format {$format}");
        }
    }

    /**
     * Validate file upload
     */
    public function validateFileUpload(array $file, array $allowedTypes = [], int $maxSize = 0, string $fieldName = 'File'): void
    {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException("{$fieldName} upload failed");
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException("{$fieldName} upload error: " . $this->getUploadErrorMessage($file['error']));
        }

        // Check file size
        if ($maxSize > 0 && $file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 2);
            throw new \InvalidArgumentException("{$fieldName} size exceeds maximum allowed size of {$maxSizeMB}MB");
        }

        // Check file type
        if (!empty($allowedTypes)) {
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $allowedTypes)) {
                $allowedStr = implode(', ', $allowedTypes);
                throw new \InvalidArgumentException("{$fieldName} type not allowed. Allowed types: {$allowedStr}");
            }
        }
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File size exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File size exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary directory';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Validate password strength
     */
    public function validatePassword(string $password, int $minLength = 8, string $fieldName = 'Password'): void
    {
        if (strlen($password) < $minLength) {
            throw new \InvalidArgumentException("{$fieldName} must be at least {$minLength} characters long");
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            throw new \InvalidArgumentException("{$fieldName} must contain at least one uppercase letter");
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            throw new \InvalidArgumentException("{$fieldName} must contain at least one lowercase letter");
        }

        // Check for at least one digit
        if (!preg_match('/\d/', $password)) {
            throw new \InvalidArgumentException("{$fieldName} must contain at least one digit");
        }

        // Check for at least one special character
        if (!preg_match('/[^a-zA-Z\d]/', $password)) {
            throw new \InvalidArgumentException("{$fieldName} must contain at least one special character");
        }
    }

    /**
     * Validate JSON string
     */
    public function validateJson(string $json, string $fieldName = 'JSON'): void
    {
        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("{$fieldName} is not valid JSON: " . json_last_error_msg());
        }
    }

    /**
     * Validate IP address
     */
    public function validateIp(string $ip, string $fieldName = 'IP Address'): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("{$fieldName} is not a valid IP address");
        }
    }

    /**
     * Validate regex pattern
     */
    public function validateRegex(string $pattern, string $fieldName = 'Pattern'): void
    {
        if (@preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException("{$fieldName} is not a valid regex pattern");
        }
    }

    /**
     * Clean HTML input - strip tags and encode entities
     */
    public function cleanHtml(string $input, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            // Strip all HTML tags
            $input = strip_tags($input);
        } else {
            // Allow specific tags
            $allowedTagsStr = '<' . implode('><', $allowedTags) . '>';
            $input = strip_tags($input, $allowedTagsStr);
        }
        
        // Encode remaining HTML entities
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(string $token, string $fieldName = 'CSRF Token'): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new \InvalidArgumentException("{$fieldName} is invalid or expired");
        }
    }

    /**
     * Sanitize filename for safe file operations
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove directory traversal attempts
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }

    /**
     * Validate multiple values at once
     */
    public function validateAll(array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            try {
                foreach ($fieldRules as $rule) {
                    call_user_func($rule);
                }
            } catch (\InvalidArgumentException $e) {
                $errors[$field] = $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
        
        return $errors;
    }
}