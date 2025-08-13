<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Définir le chemin racine
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/search_engine.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $searchEngine = new AdvancedSearchEngine();

    switch ($method) {
        case 'GET':
        case 'POST':
            $query = $_GET['q'] ?? $_POST['q'] ?? '';

            if (empty($query)) {
                throw new Exception('Requête de recherche requise');
            }

            $options = [
                'project_id' => $_GET['project_id'] ?? $_POST['project_id'] ?? null,
                'content_type' => $_GET['content_type'] ?? $_POST['content_type'] ?? null,
                'language' => $_GET['language'] ?? $_POST['language'] ?? null,
                'site_id' => $_GET['site_id'] ?? $_POST['site_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? $_POST['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? $_POST['date_to'] ?? null,
                'sort' => $_GET['sort'] ?? $_POST['sort'] ?? 'relevance',
                'page' => (int)($_GET['page'] ?? $_POST['page'] ?? 1),
                'per_page' => min(100, (int)($_GET['per_page'] ?? $_POST['per_page'] ?? 20)),
                'exact_phrase' => filter_var($_GET['exact_phrase'] ?? $_POST['exact_phrase'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'include_synonyms' => filter_var($_GET['include_synonyms'] ?? $_POST['include_synonyms'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'boost_title' => (float)($_GET['boost_title'] ?? $_POST['boost_title'] ?? 2.0),
                'boost_description' => (float)($_GET['boost_description'] ?? $_POST['boost_description'] ?? 1.5),
                'min_score' => (float)($_GET['min_score'] ?? $_POST['min_score'] ?? 0.1)
            ];

            $results = $searchEngine->search($query, $options);

            echo json_encode([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'query' => $query,
                    'options' => $options,
                    'timestamp' => date('c')
                ]
            ]);
            break;

        default:
            throw new Exception('Méthode non autorisée');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
