<?php

namespace LocalSearch\Controllers;

use LocalSearch\Services\SearchEngine;
use LocalSearch\Models\Project;
use LocalSearch\Models\Site;
use LocalSearch\Utils\Helper;
use LocalSearch\Utils\Validator;

/**
 * Search controller handling search requests and display
 */
class SearchController
{
    private $searchEngine;
    private $projectModel;
    private $siteModel;
    private $validator;

    public function __construct()
    {
        $this->searchEngine = new SearchEngine();
        $this->projectModel = new Project();
        $this->siteModel = new Site();
        $this->validator = new Validator();
    }

    /**
     * Display search page and handle search requests
     */
    public function index(): void
    {
        // Get search parameters
        $query = $this->validator->sanitizeString($_GET['q'] ?? '');
        $projectId = $this->validator->sanitizeInt($_GET['project'] ?? null);
        $contentType = $this->validator->sanitizeString($_GET['type'] ?? '');
        $siteId = $this->validator->sanitizeInt($_GET['site_id'] ?? null);
        $sort = $this->validator->sanitizeString($_GET['sort'] ?? 'relevance');
        $page = max(1, $this->validator->sanitizeInt($_GET['page'] ?? 1));
        $dateFrom = $this->validator->sanitizeString($_GET['date_from'] ?? '');
        $dateTo = $this->validator->sanitizeString($_GET['date_to'] ?? '');
        $synonyms = isset($_GET['synonyms']) ? true : false;
        $exact = isset($_GET['exact']) ? true : false;

        $results = null;
        $error = null;
        $searchTime = 0;

        // Perform search if query provided
        if (!empty($query)) {
            try {
                $searchOptions = [
                    'project_id' => $projectId,
                    'content_type' => $contentType,
                    'site_id' => $siteId,
                    'sort' => $sort,
                    'page' => $page,
                    'date_from' => $dateFrom ?: null,
                    'date_to' => $dateTo ?: null,
                    'include_synonyms' => $synonyms,
                    'exact_phrase' => $exact
                ];

                $results = $this->searchEngine->search($query, $searchOptions);
                $searchTime = $results['search_time'];

            } catch (\Exception $e) {
                $error = "Erreur lors de la recherche: " . $e->getMessage();
            }
        }

        // Get data for filters
        $projects = $this->projectModel->getForSelect();
        $sites = $this->siteModel->getSitesWithProjects($projectId);
        
        // Content types for filter
        $contentTypes = [
            'text/html' => 'Pages Web',
            'text/plain' => 'Texte',
            'application/pdf' => 'PDF',
            'image/jpeg' => 'Images JPEG',
            'image/png' => 'Images PNG'
        ];

        // Pass data to view
        $this->renderView('search/index', [
            'query' => $query,
            'results' => $results,
            'error' => $error,
            'searchTime' => $searchTime,
            'projects' => $projects,
            'sites' => $sites,
            'contentTypes' => $contentTypes,
            'filters' => [
                'project_id' => $projectId,
                'content_type' => $contentType,
                'site_id' => $siteId,
                'sort' => $sort,
                'page' => $page,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'synonyms' => $synonyms,
                'exact' => $exact
            ]
        ]);
    }

    /**
     * Handle AJAX search requests
     */
    public function ajaxSearch(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $query = $this->validator->sanitizeString($input['query'] ?? '');

        if (empty($query)) {
            echo json_encode(['error' => 'Query is required']);
            return;
        }

        try {
            $searchOptions = [
                'project_id' => $this->validator->sanitizeInt($input['project_id'] ?? null),
                'content_type' => $this->validator->sanitizeString($input['content_type'] ?? ''),
                'site_id' => $this->validator->sanitizeInt($input['site_id'] ?? null),
                'sort' => $this->validator->sanitizeString($input['sort'] ?? 'relevance'),
                'page' => max(1, $this->validator->sanitizeInt($input['page'] ?? 1)),
                'include_synonyms' => !empty($input['include_synonyms']),
                'exact_phrase' => !empty($input['exact_phrase'])
            ];

            $results = $this->searchEngine->search($query, $searchOptions);
            
            // Prepare response
            $response = [
                'success' => true,
                'query' => $query,
                'total_results' => $results['total_results'],
                'results' => $results['results'],
                'facets' => $results['facets'],
                'suggestions' => $results['suggestions'],
                'search_time' => round($results['search_time'], 4),
                'page' => $searchOptions['page'],
                'total_pages' => ceil($results['total_results'] / 20)
            ];

            echo json_encode($response);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get search suggestions for autocomplete
     */
    public function suggestions(): void
    {
        header('Content-Type: application/json');

        $query = $this->validator->sanitizeString($_GET['q'] ?? '');
        $projectId = $this->validator->sanitizeInt($_GET['project_id'] ?? null);

        if (strlen($query) < 2) {
            echo json_encode([]);
            return;
        }

        try {
            $suggestions = $this->searchEngine->getSuggestions($query, ['project_id' => $projectId]);
            echo json_encode($suggestions);

        } catch (\Exception $e) {
            echo json_encode([]);
        }
    }

    /**
     * Get search statistics
     */
    public function statistics(): void
    {
        header('Content-Type: application/json');

        $projectId = $this->validator->sanitizeInt($_GET['project_id'] ?? null);

        try {
            $stats = $this->searchEngine->getSearchStatistics($projectId);
            echo json_encode($stats);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Build search URL with parameters
     */
    public function buildSearchUrl(array $params): string
    {
        $baseUrl = Helper::getBaseUrl() . '/search.php';
        $queryParams = [];

        foreach ($params as $key => $value) {
            if (!empty($value)) {
                $queryParams[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        return $baseUrl . (!empty($queryParams) ? '?' . implode('&', $queryParams) : '');
    }

    /**
     * Render view template
     */
    private function renderView(string $template, array $data = []): void
    {
        // Extract data to variables
        extract($data);

        // Include view template
        $templatePath = dirname(dirname(__DIR__)) . "/public/views/{$template}.php";
        
        if (file_exists($templatePath)) {
            require $templatePath;
        } else {
            // Fallback to old template system for now
            $this->renderLegacyTemplate($template, $data);
        }
    }

    /**
     * Temporary method to render legacy templates during migration
     */
    private function renderLegacyTemplate(string $template, array $data): void
    {
        // For now, output the search page using the existing structure
        // This will be replaced once we create the new view templates
        
        extract($data);
        
        if ($template === 'search/index') {
            $this->renderSearchPage($data);
        }
    }

    /**
     * Render search page (temporary implementation)
     */
    private function renderSearchPage(array $data): void
    {
        extract($data);
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= !empty($query) ? htmlspecialchars($query) . ' - ' : '' ?>Moteur de Recherche Local</title>
            <link rel="stylesheet" href="assets/css/main.css">
            <link rel="stylesheet" href="assets/css/search.css">
        </head>
        <body>
            <div class="container">
                <header class="search-header">
                    <h1>Moteur de Recherche Local</h1>
                    <p>Recherche intelligente avec analyse sémantique</p>
                </header>

                <main class="search-main">
                    <!-- Search Form -->
                    <form method="GET" class="search-form">
                        <div class="search-box">
                            <input type="text" 
                                   name="q" 
                                   value="<?= htmlspecialchars($query) ?>" 
                                   placeholder="Recherche avancée..." 
                                   class="search-input"
                                   autofocus>
                            <button type="submit" class="search-button">Rechercher</button>
                        </div>

                        <!-- Filters -->
                        <div class="search-filters">
                            <?php if (!empty($projects)): ?>
                            <select name="project" class="filter-select">
                                <option value="">Tous les projets</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" <?= $filters['project_id'] == $project['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>

                            <select name="type" class="filter-select">
                                <option value="">Tous les types</option>
                                <?php foreach ($contentTypes as $type => $label): ?>
                                <option value="<?= $type ?>" <?= $filters['content_type'] === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="sort" class="filter-select">
                                <option value="relevance" <?= $filters['sort'] === 'relevance' ? 'selected' : '' ?>>Pertinence</option>
                                <option value="date" <?= $filters['sort'] === 'date' ? 'selected' : '' ?>>Date</option>
                                <option value="title" <?= $filters['sort'] === 'title' ? 'selected' : '' ?>>Titre</option>
                            </select>

                            <label>
                                <input type="checkbox" name="synonyms" <?= $filters['synonyms'] ? 'checked' : '' ?>>
                                Inclure synonymes
                            </label>

                            <label>
                                <input type="checkbox" name="exact" <?= $filters['exact'] ? 'checked' : '' ?>>
                                Phrase exacte
                            </label>
                        </div>
                    </form>

                    <!-- Results -->
                    <?php if ($error): ?>
                        <div class="error-message">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php elseif ($results): ?>
                        <div class="search-results">
                            <div class="results-info">
                                <?= number_format($results['total_results']) ?> résultats 
                                (<?= round($searchTime, 3) ?>s)
                            </div>

                            <?php if (!empty($results['results'])): ?>
                                <?php foreach ($results['results'] as $result): ?>
                                <div class="result-item">
                                    <h3 class="result-title">
                                        <a href="<?= htmlspecialchars($result['url']) ?>" target="_blank">
                                            <?= $result['highlighted_title'] ?? htmlspecialchars($result['title']) ?>
                                        </a>
                                    </h3>
                                    <div class="result-url">
                                        <?= htmlspecialchars($result['domain']) ?> - 
                                        <?= htmlspecialchars($result['project_name']) ?>
                                    </div>
                                    <div class="result-description">
                                        <?= $result['highlighted_description'] ?? htmlspecialchars($result['description']) ?>
                                    </div>
                                    <?php if (!empty($result['snippet'])): ?>
                                    <div class="result-snippet">
                                        <?= $result['snippet'] ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($results['total_results'] > 20): ?>
                            <div class="pagination">
                                <?php
                                $totalPages = ceil($results['total_results'] / 20);
                                $currentPage = $filters['page'];
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                ?>

                                <?php if ($currentPage > 1): ?>
                                <a href="?<?= http_build_query(array_merge($filters, ['page' => $currentPage - 1])) ?>" class="page-btn">← Précédent</a>
                                <?php endif; ?>

                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>" 
                                   class="page-btn <?= $i === $currentPage ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                                <?php endfor; ?>

                                <?php if ($currentPage < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($filters, ['page' => $currentPage + 1])) ?>" class="page-btn">Suivant →</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </main>
            </div>

            <script src="assets/js/search.js"></script>
        </body>
        </html>
        <?php
    }
}