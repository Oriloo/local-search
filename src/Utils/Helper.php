<?php

namespace LocalSearch\Utils;

use LocalSearch\Config\Configuration;

/**
 * Helper utility class with common functions
 */
class Helper
{
    /**
     * Get application base URL
     */
    public static function getBaseUrl(): string
    {
        return rtrim(Configuration::get('APP_URL'), '/');
    }

    /**
     * Generate URL hash for uniqueness checking
     */
    public static function generateUrlHash(string $url): string
    {
        return hash('sha256', $url);
    }

    /**
     * Format file size in human readable format
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format time difference in human readable format
     */
    public static function formatTimeAgo(string $datetime): string
    {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) {
            return "à l'instant";
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            return "il y a {$minutes} minute" . ($minutes > 1 ? 's' : '');
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            return "il y a {$hours} heure" . ($hours > 1 ? 's' : '');
        } elseif ($time < 2592000) {
            $days = floor($time / 86400);
            return "il y a {$days} jour" . ($days > 1 ? 's' : '');
        } elseif ($time < 31536000) {
            $months = floor($time / 2592000);
            return "il y a {$months} mois";
        } else {
            $years = floor($time / 31536000);
            return "il y a {$years} an" . ($years > 1 ? 's' : '');
        }
    }

    /**
     * Clean and validate URL
     */
    public static function cleanUrl(string $url): ?string
    {
        $url = trim($url);
        
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        return $url;
    }

    /**
     * Extract domain from URL
     */
    public static function extractDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? null;
    }

    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Escape HTML output
     */
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate secure random string
     */
    public static function generateRandomString(int $length = 16): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Check if request is AJAX
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get client IP address
     */
    public static function getClientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 
                   'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                                  FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Redirect to URL
     */
    public static function redirect(string $url, int $code = 302): void
    {
        header("Location: {$url}", true, $code);
        exit;
    }

    /**
     * Send JSON response
     */
    public static function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Log message to application log
     */
    public static function log(string $message, string $level = 'info'): void
    {
        $logFile = Configuration::get('LOG_FILE', 'logs/app.log');
        $logDir = dirname($logFile);
        
        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Truncate text to specified length with ellipsis
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Convert array to HTML attributes string
     */
    public static function attributesToString(array $attributes): string
    {
        $parts = [];
        
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            
            if ($value === true) {
                $parts[] = $name;
            } else {
                $parts[] = $name . '="' . self::escape($value) . '"';
            }
        }
        
        return implode(' ', $parts);
    }

    /**
     * Check if string starts with another string
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * Check if string ends with another string
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * Convert camelCase to snake_case
     */
    public static function camelToSnake(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    /**
     * Convert snake_case to camelCase
     */
    public static function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    /**
     * Create directory if it doesn't exist
     */
    public static function ensureDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        
        return true;
    }

    /**
     * Clean filename for safe file operations
     */
    public static function cleanFilename(string $filename): string
    {
        // Remove path separators and special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Trim underscores from start and end
        return trim($filename, '_');
    }

    /**
     * Get file extension from filename
     */
    public static function getFileExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Check if file extension is allowed
     */
    public static function isAllowedFileType(string $filename, array $allowedTypes): bool
    {
        $extension = self::getFileExtension($filename);
        return in_array($extension, $allowedTypes);
    }
}