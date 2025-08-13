<?php

namespace LocalSearch\Controllers;

use LocalSearch\Models\Project;
use LocalSearch\Models\Site;
use LocalSearch\Services\WebCrawler;
use LocalSearch\Utils\Helper;
use LocalSearch\Utils\Validator;

/**
 * Admin controller for managing projects, sites, and crawling
 */
class AdminController
{
    private $projectModel;
    private $siteModel;
    private $webCrawler;
    private $validator;

    public function __construct()
    {
        $this->projectModel = new Project();
        $this->siteModel = new Site();
        $this->webCrawler = new WebCrawler();
        $this->validator = new Validator();
    }

    /**
     * Display admin dashboard
     */
    public function index(): void
    {
        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        
        // Clear flash messages
        unset($_SESSION['success'], $_SESSION['error']);

        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
            return;
        }

        // Get data for display
        $projects = $this->projectModel->getAllWithStats();
        $sites = $this->siteModel->getSitesWithProjects();

        $this->renderView('admin/index', [
            'projects' => $projects,
            'sites' => $sites,
            'success' => $success,
            'error' => $error
        ]);
    }

    /**
     * Handle POST requests (form submissions)
     */
    private function handlePostRequest(): void
    {
        try {
            // Verify CSRF token
            $token = $_POST['csrf_token'] ?? '';
            $this->validator->validateCsrfToken($token);

            $action = $_POST['action'] ?? '';

            switch ($action) {
                case 'create_project':
                    $this->createProject();
                    break;

                case 'add_site':
                    $this->addSite();
                    break;

                case 'start_crawl':
                    $this->startCrawl();
                    break;

                default:
                    throw new \InvalidArgumentException('Action non reconnue');
            }

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            Helper::redirect('/admin');
        }
    }

    /**
     * Create a new project
     */
    private function createProject(): void
    {
        $name = $this->validator->sanitizeString($_POST['name'] ?? '');
        $description = $this->validator->sanitizeString($_POST['description'] ?? '');
        $domainsText = $this->validator->sanitizeString($_POST['domains'] ?? '');
        $maxDepth = $this->validator->sanitizeInt($_POST['max_depth'] ?? '3');
        $crawlDelay = $this->validator->sanitizeInt($_POST['crawl_delay'] ?? '1');
        $respectRobots = isset($_POST['respect_robots']);

        // Validate inputs
        $this->validator->required($name, 'Nom du projet');
        $this->validator->validateLength($name, 3, 100, 'Nom du projet');
        $this->validator->validateLength($description, 0, 500, 'Description');
        $this->validator->validateRange($maxDepth, 1, 10, 'Profondeur maximale');
        $this->validator->validateRange($crawlDelay, 0, 60, 'Délai de crawling');

        // Parse domains
        $domains = array_filter(array_map('trim', explode("\n", $domainsText)));
        if (empty($domains)) {
            throw new \InvalidArgumentException('Au moins un domaine est requis');
        }

        // Validate domains
        foreach ($domains as $domain) {
            $this->validator->validateDomain($domain);
        }

        // Configuration
        $config = [
            'max_depth' => $maxDepth,
            'crawl_delay' => $crawlDelay,
            'respect_robots' => $respectRobots
        ];

        // Create project
        $projectId = $this->projectModel->createProject($name, $description, $domains, $config);

        $_SESSION['success'] = "Projet '{$name}' créé avec succès !";
        Helper::redirect('/admin');
    }

    /**
     * Add a site to a project
     */
    private function addSite(): void
    {
        $projectId = $this->validator->sanitizeInt($_POST['project_id'] ?? '');
        $baseUrl = $this->validator->sanitizeString($_POST['base_url'] ?? '');

        // Validate inputs
        $this->validator->required($projectId, 'Projet');
        $this->validator->required($baseUrl, 'URL de base');

        // Clean and validate URL
        $baseUrl = Helper::cleanUrl($baseUrl);
        if (!$baseUrl) {
            throw new \InvalidArgumentException('URL invalide');
        }

        $domain = Helper::extractDomain($baseUrl);
        if (!$domain) {
            throw new \InvalidArgumentException('Impossible d\'extraire le domaine de l\'URL');
        }

        // Check if domain already exists for this project
        if ($this->siteModel->domainExists($projectId, $domain)) {
            throw new \InvalidArgumentException('Ce domaine existe déjà pour ce projet');
        }

        // Add site
        $siteId = $this->siteModel->addSite($projectId, $domain, $baseUrl);

        $_SESSION['success'] = "Site '{$domain}' ajouté avec succès !";
        Helper::redirect('/admin');
    }

    /**
     * Start crawling a site
     */
    private function startCrawl(): void
    {
        $siteId = $this->validator->sanitizeInt($_POST['site_id'] ?? '');
        $maxPages = $this->validator->sanitizeInt($_POST['max_pages'] ?? '100');

        // Validate inputs
        $this->validator->required($siteId, 'Site');
        $this->validator->validateRange($maxPages, 1, 1000, 'Nombre maximum de pages');

        // Start crawling in background (simplified - in production, use job queue)
        try {
            $stats = $this->webCrawler->crawlSite($siteId, $maxPages);
            $_SESSION['success'] = "Crawling terminé ! {$stats['urls_successful']} pages indexées.";
        } catch (\Exception $e) {
            $_SESSION['error'] = "Erreur lors du crawling : " . $e->getMessage();
        }

        Helper::redirect('/admin');
    }

    /**
     * Display projects management page
     */
    public function projects(): void
    {
        $projects = $this->projectModel->getAllWithStats();

        $this->renderView('admin/projects', [
            'projects' => $projects
        ]);
    }

    /**
     * Display crawl status page
     */
    public function crawlStatus(): void
    {
        $sites = $this->siteModel->getSitesWithProjects();

        // Get crawl statistics for each site
        foreach ($sites as &$site) {
            $site['stats'] = $this->siteModel->getSiteStats($site['id']);
        }

        $this->renderView('admin/crawl-status', [
            'sites' => $sites
        ]);
    }

    /**
     * Display settings page
     */
    public function settings(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->updateSettings();
            return;
        }

        $this->renderView('admin/settings', [
            'config' => [
                'max_crawl_depth' => \LocalSearch\Config\Configuration::get('MAX_CRAWL_DEPTH'),
                'crawl_delay' => \LocalSearch\Config\Configuration::get('CRAWL_DELAY'),
                'results_per_page' => \LocalSearch\Config\Configuration::get('RESULTS_PER_PAGE'),
                'max_content_length' => \LocalSearch\Config\Configuration::get('MAX_CONTENT_LENGTH'),
            ]
        ]);
    }

    /**
     * Update application settings
     */
    private function updateSettings(): void
    {
        try {
            // This would typically update configuration in a database or file
            // For now, we'll just show a success message
            $_SESSION['success'] = "Paramètres mis à jour avec succès !";
            Helper::redirect('/admin/settings');

        } catch (\Exception $e) {
            $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
            Helper::redirect('/admin/settings');
        }
    }

    /**
     * AJAX endpoint for crawl statistics
     */
    public function crawlStats(): void
    {
        header('Content-Type: application/json');

        $siteId = $this->validator->sanitizeInt($_GET['site_id'] ?? '');

        if (!$siteId) {
            Helper::jsonResponse(['error' => 'Site ID requis'], 400);
            return;
        }

        try {
            $stats = $this->siteModel->getSiteStats($siteId);
            Helper::jsonResponse(['success' => true, 'stats' => $stats]);

        } catch (\Exception $e) {
            Helper::jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX endpoint to start crawling
     */
    public function ajaxStartCrawl(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $this->validator->sanitizeInt($input['site_id'] ?? '');
        $maxPages = $this->validator->sanitizeInt($input['max_pages'] ?? '100');

        if (!$siteId) {
            Helper::jsonResponse(['error' => 'Site ID requis'], 400);
            return;
        }

        try {
            // In a real application, this would be queued as a background job
            $stats = $this->webCrawler->crawlSite($siteId, $maxPages);
            Helper::jsonResponse([
                'success' => true,
                'message' => "Crawling terminé ! {$stats['urls_successful']} pages indexées.",
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Helper::jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Render admin view template
     */
    private function renderView(string $template, array $data = []): void
    {
        // Extract data to variables
        extract($data);
        
        // Add CSRF token to all views
        $csrfToken = Helper::generateCsrfToken();

        // For now, render a simplified admin interface
        if ($template === 'admin/index') {
            $this->renderAdminIndex($data, $csrfToken);
        } else {
            // Placeholder for other admin views
            echo "Admin view: {$template}";
        }
    }

    /**
     * Render admin index page (temporary implementation)
     */
    private function renderAdminIndex(array $data, string $csrfToken): void
    {
        extract($data);
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Administration - <?= \LocalSearch\Config\Configuration::get('APP_NAME') ?></title>
            <link rel="stylesheet" href="/assets/css/main.css">
            <link rel="stylesheet" href="/assets/css/admin.css">
        </head>
        <body>
            <div class="container">
                <header class="admin-header">
                    <h1><?= \LocalSearch\Config\Configuration::get('APP_NAME') ?> - Administration</h1>
                    <nav class="admin-nav">
                        <a href="/" class="btn btn-outline">Retour à la recherche</a>
                        <a href="/admin/projects" class="btn btn-secondary">Projets</a>
                        <a href="/admin/crawl-status" class="btn btn-secondary">Statut crawling</a>
                        <a href="/admin/settings" class="btn btn-secondary">Paramètres</a>
                    </nav>
                </header>

                <main class="admin-main">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= Helper::escape($success) ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= Helper::escape($error) ?></div>
                    <?php endif; ?>

                    <!-- Create Project Form -->
                    <section class="admin-section">
                        <h2>Créer un nouveau projet</h2>
                        <form method="POST" class="admin-form">
                            <input type="hidden" name="action" value="create_project">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="form-group">
                                <label for="name" class="form-label">Nom du projet *</label>
                                <input type="text" id="name" name="name" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" class="form-textarea"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="domains" class="form-label">Domaines autorisés (un par ligne) *</label>
                                <textarea id="domains" name="domains" class="form-textarea" required 
                                          placeholder="example.com&#10;subdomain.example.com"></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_depth" class="form-label">Profondeur max</label>
                                    <input type="number" id="max_depth" name="max_depth" class="form-input" value="3" min="1" max="10">
                                </div>

                                <div class="form-group">
                                    <label for="crawl_delay" class="form-label">Délai (secondes)</label>
                                    <input type="number" id="crawl_delay" name="crawl_delay" class="form-input" value="1" min="0" max="60">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="respect_robots" checked>
                                    Respecter robots.txt
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary">Créer le projet</button>
                        </form>
                    </section>

                    <!-- Add Site Form -->
                    <?php if (!empty($projects)): ?>
                    <section class="admin-section">
                        <h2>Ajouter un site</h2>
                        <form method="POST" class="admin-form">
                            <input type="hidden" name="action" value="add_site">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="form-group">
                                <label for="project_id" class="form-label">Projet *</label>
                                <select id="project_id" name="project_id" class="form-select" required>
                                    <option value="">Choisir un projet</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>">
                                        <?= Helper::escape($project['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="base_url" class="form-label">URL de base *</label>
                                <input type="url" id="base_url" name="base_url" class="form-input" required 
                                       placeholder="https://example.com">
                            </div>

                            <button type="submit" class="btn btn-primary">Ajouter le site</button>
                        </form>
                    </section>
                    <?php endif; ?>

                    <!-- Sites List -->
                    <?php if (!empty($sites)): ?>
                    <section class="admin-section">
                        <h2>Sites configurés</h2>
                        <div class="admin-table">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Projet</th>
                                        <th>Domaine</th>
                                        <th>URL de base</th>
                                        <th>Documents</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sites as $site): ?>
                                    <tr>
                                        <td><?= Helper::escape($site['project_name']) ?></td>
                                        <td><?= Helper::escape($site['domain']) ?></td>
                                        <td>
                                            <a href="<?= Helper::escape($site['base_url']) ?>" target="_blank">
                                                <?= Helper::escape($site['base_url']) ?>
                                            </a>
                                        </td>
                                        <td><?= number_format($site['documents_count']) ?></td>
                                        <td>
                                            <span class="status status-<?= $site['status'] ?>">
                                                <?= Helper::escape($site['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="start_crawl">
                                                <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                                <input type="hidden" name="max_pages" value="100">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <button type="submit" class="btn btn-small btn-primary">
                                                    Crawler
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <?php endif; ?>
                </main>
            </div>

            <script src="/assets/js/admin.js"></script>
        </body>
        </html>
        <?php
    }
}