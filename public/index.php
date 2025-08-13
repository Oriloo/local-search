<?php

/**
 * Main entry point for the search application
 */

require_once __DIR__ . '/bootstrap.php';

use LocalSearch\Controllers\SearchController;
use LocalSearch\Utils\Helper;

// Initialize the search controller
$searchController = new SearchController();

// Handle different request types
try {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Remove query string from URI
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Route to appropriate controller method
    switch ($path) {
        case '/':
        case '/search':
        case '/search.php':
        case '/index.php':
            if ($requestMethod === 'GET') {
                $searchController->index();
            } else {
                http_response_code(405);
                echo 'Method Not Allowed';
            }
            break;
            
        case '/api/search':
            if ($requestMethod === 'POST') {
                $searchController->ajaxSearch();
            } else {
                http_response_code(405);
                Helper::jsonResponse(['error' => 'Method Not Allowed'], 405);
            }
            break;
            
        case '/api/suggestions':
            if ($requestMethod === 'GET') {
                $searchController->suggestions();
            } else {
                http_response_code(405);
                Helper::jsonResponse(['error' => 'Method Not Allowed'], 405);
            }
            break;
            
        case '/api/statistics':
            if ($requestMethod === 'GET') {
                $searchController->statistics();
            } else {
                http_response_code(405);
                Helper::jsonResponse(['error' => 'Method Not Allowed'], 405);
            }
            break;
            
        default:
            // Try to serve static files or legacy files
            $legacyFile = ROOT_PATH . $path;
            if (file_exists($legacyFile) && is_file($legacyFile)) {
                // Serve legacy file
                include $legacyFile;
            } else {
                // 404 Not Found
                http_response_code(404);
                echo '<!DOCTYPE html>
                <html lang="fr">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Page non trouvée - ' . \LocalSearch\Config\Configuration::get('APP_NAME') . '</title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                        .error { background: #ffebee; color: #c62828; padding: 20px; border-radius: 8px; display: inline-block; }
                        a { color: #1976d2; text-decoration: none; }
                        a:hover { text-decoration: underline; }
                    </style>
                </head>
                <body>
                    <div class="error">
                        <h1>404 - Page non trouvée</h1>
                        <p>La page demandée n\'existe pas.</p>
                        <p><a href="/">Retour à l\'accueil</a></p>
                    </div>
                </body>
                </html>';
            }
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
            <title>Erreur - ' . \LocalSearch\Config\Configuration::get('APP_NAME') . '</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { background: #ffebee; color: #c62828; padding: 20px; border-radius: 8px; display: inline-block; text-align: left; }
                pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>Erreur interne du serveur</h1>
                <p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                <p><strong>Fichier:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>
                <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
            </div>
        </body>
        </html>';
    } else {
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erreur - ' . \LocalSearch\Config\Configuration::get('APP_NAME') . '</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { background: #ffebee; color: #c62828; padding: 20px; border-radius: 8px; display: inline-block; }
                a { color: #1976d2; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>Erreur interne du serveur</h1>
                <p>Une erreur inattendue s\'est produite. Veuillez réessayer plus tard.</p>
                <p><a href="/">Retour à l\'accueil</a></p>
            </div>
        </body>
        </html>';
    }
    
    // Log the error
    Helper::log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), 'error');
}