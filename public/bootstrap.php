<?php

/**
 * Bootstrap file for Local Search application
 * Handles autoloading, configuration, and initialization
 */

// Define constants
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

// Error reporting (will be overridden by configuration)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader
require_once ROOT_PATH . '/vendor/autoload.php';

// Load configuration
use LocalSearch\Config\Configuration;
Configuration::load();

// Set error reporting based on environment
if (Configuration::get('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Set timezone
date_default_timezone_set('Europe/Paris');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (isset($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Helper function to handle errors gracefully
function handleError($errno, $errstr, $errfile, $errline) {
    if (Configuration::get('APP_ENV') !== 'production') {
        echo "<div style='background: #ffebee; color: #c62828; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
        echo "<strong>Error:</strong> {$errstr} in {$errfile} on line {$errline}";
        echo "</div>";
    }
    
    // Log error
    error_log("Error: {$errstr} in {$errfile} on line {$errline}");
    
    return true;
}

set_error_handler('handleError');

// Helper function to handle exceptions
function handleException($exception) {
    if (Configuration::get('APP_ENV') !== 'production') {
        echo "<div style='background: #ffebee; color: #c62828; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
        echo "<strong>Uncaught Exception:</strong> " . $exception->getMessage();
        echo "<br><strong>File:</strong> " . $exception->getFile() . ":" . $exception->getLine();
        echo "</div>";
    }
    
    // Log exception
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
}

set_exception_handler('handleException');

// Load legacy functions for backward compatibility
$legacyFunctions = ROOT_PATH . '/includes/functions.php';
if (file_exists($legacyFunctions)) {
    require_once $legacyFunctions;
}