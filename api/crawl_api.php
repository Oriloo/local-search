<?php
// Clean output buffer au début
if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Définir le chemin racine
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

try {
    require_once ROOT_PATH . '/config/config.php';
    require_once ROOT_PATH . '/includes/functions.php';
    require_once ROOT_PATH . '/includes/crawler.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur inclusion fichiers: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$search_engine = new SearchEngine();

try {
    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'start_crawl':
                    $input_raw = file_get_contents('php://input');
                    $input = json_decode($input_raw, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('JSON invalide: ' . json_last_error_msg());
                    }

                    $site_id = $input['site_id'] ?? 0;
                    $max_pages = $input['max_pages'] ?? 50;

                    if (!$site_id) {
                        throw new Exception('Site ID requis');
                    }

                    // Vérifier que le site existe
                    $database = new Database();
                    $db = $database->connect();
                    $stmt = $db->prepare("SELECT * FROM sites WHERE id = ?");
                    $stmt->execute([$site_id]);
                    $site = $stmt->fetch();

                    if (!$site) {
                        throw new Exception("Site ID $site_id non trouvé");
                    }

                    // Créer le crawler et lancer
                    $crawler = new WebCrawler();
                    $result = $crawler->crawlSite($site_id, $max_pages);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Crawling terminé',
                        'data' => $result
                    ]);
                    break;

                default:
                    throw new Exception('Action POST non reconnue: ' . $action);
            }
            break;

        case 'GET':
            switch ($action) {
                case 'status':
                    $site_id = $_GET['site_id'] ?? 0;

                    if ($site_id) {
                        $status = getCrawlStatus($site_id);
                    } else {
                        $status = getAllCrawlStatus();
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $status
                    ]);
                    break;

                case 'history':
                    $project_id = $_GET['project_id'] ?? null;
                    $history = getCrawlHistory($project_id);

                    echo json_encode([
                        'success' => true,
                        'data' => $history
                    ]);
                    break;

                case 'queue':
                    $project_id = $_GET['project_id'] ?? null;
                    $queue = getCrawlQueue($project_id);

                    echo json_encode([
                        'success' => true,
                        'data' => $queue
                    ]);
                    break;

                default:
                    echo json_encode([
                        'success' => true,
                        'message' => 'API Crawling active',
                        'endpoints' => [
                            'POST /api/crawl_api.php?action=start_crawl' => 'Lancer un crawling',
                            'GET /api/crawl_api.php?action=status' => 'Statut des crawlings',
                            'GET /api/crawl_api.php?action=history' => 'Historique des crawlings',
                            'GET /api/crawl_api.php?action=queue' => 'Queue de crawling'
                        ]
                    ]);
            }
            break;

        default:
            throw new Exception('Méthode non autorisée: ' . $method);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Fonctions utilitaires
function getCrawlStatus($site_id) {
    $database = new Database();
    $db = $database->connect();

    $sql = "SELECT s.*, sp.name as project_name,
            (SELECT COUNT(*) FROM documents WHERE site_id = s.id) as documents_count,
            (SELECT COUNT(*) FROM crawl_queue WHERE site_id = s.id AND status = 'pending') as queue_pending,
            (SELECT COUNT(*) FROM crawl_queue WHERE site_id = s.id AND status = 'processing') as queue_processing
            FROM sites s 
            JOIN search_projects sp ON s.project_id = sp.id 
            WHERE s.id = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$site_id]);

    return $stmt->fetch();
}

function getAllCrawlStatus() {
    $database = new Database();
    $db = $database->connect();

    $sql = "SELECT s.*, sp.name as project_name,
            (SELECT COUNT(*) FROM documents WHERE site_id = s.id) as documents_count,
            (SELECT COUNT(*) FROM crawl_queue WHERE site_id = s.id AND status = 'pending') as queue_pending,
            (SELECT COUNT(*) FROM crawl_queue WHERE site_id = s.id AND status = 'processing') as queue_processing
            FROM sites s 
            JOIN search_projects sp ON s.project_id = sp.id 
            ORDER BY s.last_crawled DESC";

    return $db->query($sql)->fetchAll();
}

function getCrawlHistory($project_id = null) {
    $database = new Database();
    $db = $database->connect();

    $where = $project_id ? "WHERE ch.project_id = $project_id" : "";

    $sql = "SELECT ch.*, sp.name as project_name 
            FROM crawl_history ch
            JOIN search_projects sp ON ch.project_id = sp.id
            $where
            ORDER BY ch.started_at DESC 
            LIMIT 50";

    return $db->query($sql)->fetchAll();
}

function getCrawlQueue($project_id = null) {
    $database = new Database();
    $db = $database->connect();

    $where = $project_id ? "WHERE cq.project_id = $project_id" : "";

    $sql = "SELECT cq.*, sp.name as project_name 
            FROM crawl_queue cq
            JOIN search_projects sp ON cq.project_id = sp.id
            $where
            ORDER BY cq.priority DESC, cq.scheduled_at ASC 
            LIMIT 100";

    return $db->query($sql)->fetchAll();
}
