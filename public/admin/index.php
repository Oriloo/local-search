<?php

/**
 * Admin interface entry point
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use LocalSearch\Controllers\AdminController;
use LocalSearch\Utils\Helper;

// Initialize the admin controller
$adminController = new AdminController();

// Handle different request types
try {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Remove query string from URI
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Remove /admin prefix if present
    $path = preg_replace('/^\/admin/', '', $path);
    if (empty($path)) {
        $path = '/';
    }
    
    // Route to appropriate controller method
    switch ($path) {
        case '/':
        case '/index':
        case '/index.php':
            $adminController->index();
            break;
            
        case '/projects':
        case '/projects.php':
            $adminController->projects();
            break;
            
        case '/crawl-status':
        case '/crawl-status.php':
            $adminController->crawlStatus();
            break;
            
        case '/settings':
        case '/settings.php':
            $adminController->settings();
            break;
            
        case '/api/crawl-stats':
            if ($requestMethod === 'GET') {
                $adminController->crawlStats();
            } else {
                Helper::jsonResponse(['error' => 'Method Not Allowed'], 405);
            }
            break;
            
        case '/api/start-crawl':
            if ($requestMethod === 'POST') {
                $adminController->ajaxStartCrawl();
            } else {
                Helper::jsonResponse(['error' => 'Method Not Allowed'], 405);
            }
            break;
            
        default:
            // 404 Not Found
            http_response_code(404);
            echo '<!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Page non trouvée - Administration</title>
                <link rel="stylesheet" href="/assets/css/main.css">
            </head>
            <body>
                <div class="container">
                    <div class="text-center p-4">
                        <div class="alert alert-error">
                            <h1>404 - Page non trouvée</h1>
                            <p>La page d\'administration demandée n\'existe pas.</p>
                            <p><a href="/admin" class="btn btn-primary">Retour à l\'administration</a></p>
                        </div>
                    </div>
                </div>
            </body>
            </html>';
            break;
    }
    
} catch (\Exception $e) {
    // Handle any uncaught exceptions
    http_response_code(500);
    
    if (\LocalSearch\Config\Configuration::get('APP_ENV') !== 'production') {
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erreur - Administration</title>
            <link rel="stylesheet" href="/assets/css/main.css">
        </head>
        <body>
            <div class="container">
                <div class="text-center p-4">
                    <div class="alert alert-error">
                        <h1>Erreur interne du serveur</h1>
                        <p><strong>Message:</strong> ' . Helper::escape($e->getMessage()) . '</p>
                        <p><strong>Fichier:</strong> ' . Helper::escape($e->getFile()) . ':' . $e->getLine() . '</p>
                        <pre style="text-align: left; background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto;">' . Helper::escape($e->getTraceAsString()) . '</pre>
                        <p><a href="/admin" class="btn btn-primary">Retour à l\'administration</a></p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    } else {
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erreur - Administration</title>
            <link rel="stylesheet" href="/assets/css/main.css">
        </head>
        <body>
            <div class="container">
                <div class="text-center p-4">
                    <div class="alert alert-error">
                        <h1>Erreur interne du serveur</h1>
                        <p>Une erreur inattendue s\'est produite. Veuillez réessayer plus tard.</p>
                        <p><a href="/admin" class="btn btn-primary">Retour à l\'administration</a></p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    // Log the error
    Helper::log("Uncaught exception in admin: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), 'error');
}