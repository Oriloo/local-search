<?php
// Définir le chemin racine seulement s'il n'est pas déjà défini
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Inclure les fichiers avec chemins absolus
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

$search_engine = new SearchEngine();

// Traitement des actions
if ($_POST) {
    if (isset($_POST['create_project'])) {
        $domains = array_filter(array_map('trim', explode("\n", $_POST['domains'])));
        $config = [
            'max_depth' => (int)$_POST['max_depth'],
            'crawl_delay' => (int)$_POST['crawl_delay'],
            'respect_robots' => isset($_POST['respect_robots'])
        ];

        if ($search_engine->createProject($_POST['name'], $_POST['description'], $domains, $config)) {
            $success = "Projet créé avec succès !";
        } else {
            $error = "Erreur lors de la création du projet.";
        }
    }

    if (isset($_POST['add_site'])) {
        $url = parse_url($_POST['base_url']);
        $domain = $url['host'];

        if ($search_engine->addSite($_POST['project_id'], $domain, $_POST['base_url'])) {
            $success = "Site ajouté avec succès !";
        } else {
            $error = "Erreur lors de l'ajout du site.";
        }
    }
}

$projects = $search_engine->getProjects();
$sites = $search_engine->getSites();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/common.css">
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<div class="container">
    <header class="header">
        <h1><?= APP_NAME ?> - Administration</h1>
        <p>Gestion des projets de crawling et indexation</p>
        <nav class="nav">
            <a href="#" class="active">Projets</a>
            <a href="../search.php">Recherche</a>
            <a href="crawl_status.php">Statut Crawling</a>
        </nav>
    </header>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <!-- Statistiques générales -->
    <div class="stats">
        <?php
        $total_projects = count($projects);
        $total_sites = count($sites);
        $total_stats = $search_engine->getStats();
        ?>
        <div class="stat-card">
            <div class="stat-number"><?= $total_projects ?></div>
            <div class="stat-label">Projets</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $total_sites ?></div>
            <div class="stat-label">Sites</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($total_stats['total_documents']) ?></div>
            <div class="stat-label">Documents indexés</div>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="card">
        <h2>Actions rapides</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="openModal('projectModal')">
                ✨ Nouveau projet
            </button>
            <button class="btn btn-success" onclick="testCrawlAPI()">
                🧪 Tester API Crawling
            </button>
            <button class="btn btn-warning" onclick="refreshAllStatus()">
                🔄 Actualiser statuts
            </button>
        </div>
    </div>

    <!-- Liste des projets -->
    <div class="card">
        <h2>Projets de recherche</h2>

        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <h3>Aucun projet créé</h3>
                <p>Créez votre premier projet pour commencer l'indexation.</p>
                <button class="btn btn-primary" onclick="openModal('projectModal')">+ Créer un projet</button>
            </div>
        <?php else: ?>
            <div class="admin-table">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Description</th>
                        <th>Sites</th>
                        <th>Documents</th>
                        <th>Statut</th>
                        <th>Créé</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($project['name']) ?></strong>
                            </td>
                            <td>
                                <?= htmlspecialchars(substr($project['description'], 0, 100)) ?><?= strlen($project['description']) > 100 ? '...' : '' ?>
                            </td>
                            <td><?= $project['sites_count'] ?></td>
                            <td><?= number_format($project['documents_count']) ?></td>
                            <td><span class="status <?= $project['status'] ?>"><?= ucfirst($project['status']) ?></span></td>
                            <td><?= timeAgo($project['created_at']) ?></td>
                            <td class="table-actions">
                                <button class="btn btn-success" onclick="openSiteModal(<?= $project['id'] ?>)" title="Ajouter un site">
                                    🌐 Site
                                </button>
                                <a href="project_form.php?id=<?= $project['id'] ?>" class="btn btn-secondary" title="Éditer">
                                    ✏️
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Liste des sites avec crawling -->
    <div class="card">
        <h2>Sites indexés et crawling</h2>

        <?php if (empty($sites)): ?>
            <div class="empty-state">
                <h3>Aucun site ajouté</h3>
                <p>Ajoutez des sites à vos projets pour commencer l'indexation.</p>
            </div>
        <?php else: ?>
            <div class="admin-table">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Projet</th>
                        <th>Domaine</th>
                        <th>URL de base</th>
                        <th>Documents</th>
                        <th>Dernier crawl</th>
                        <th>Statut</th>
                        <th>Actions Crawling</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr id="site-row-<?= $site['id'] ?>">
                            <td>
                                <span class="badge badge-new"><?= htmlspecialchars($site['project_name']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($site['domain']) ?></td>
                            <td>
                                <a href="<?= htmlspecialchars($site['base_url']) ?>" target="_blank" title="Ouvrir le site">
                                    <?= htmlspecialchars(substr($site['base_url'], 0, 40)) ?><?= strlen($site['base_url']) > 40 ? '...' : '' ?>
                                </a>
                            </td>
                            <td>
                                <strong id="docs-<?= $site['id'] ?>">-</strong>
                                <div class="queue-info" id="queue-<?= $site['id'] ?>"></div>
                            </td>
                            <td><?= $site['last_crawled'] ? timeAgo($site['last_crawled']) : '<em>Jamais</em>' ?></td>
                            <td>
                                    <span id="status-<?= $site['id'] ?>">
                                        <span class="status <?= $site['status'] ?>"><?= ucfirst($site['status']) ?></span>
                                    </span>
                            </td>
                            <td class="table-actions">
                                <button
                                        class="btn btn-warning btn-crawl"
                                        data-site-id="<?= $site['id'] ?>"
                                        title="Lancer le crawling (50 pages max)"
                                        onclick="startQuickCrawl(<?= $site['id'] ?>)">
                                    🕷️ Crawler
                                </button>
                                <button
                                        class="btn btn-primary"
                                        onclick="showCrawlSettings(<?= $site['id'] ?>)"
                                        title="Paramètres avancés">
                                    ⚙️
                                </button>
                                <button
                                        class="btn btn-secondary"
                                        onclick="viewSiteDetails(<?= $site['id'] ?>)"
                                        title="Voir détails">
                                    👁️
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal nouveau projet -->
<div id="projectModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('projectModal')">&times;</span>
        <h2>Nouveau projet de recherche</h2>

        <form method="POST">
            <div class="form-group">
                <label for="name">Nom du projet *</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="ex: Manga, Local, Fruits...">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" placeholder="Description du projet..."></textarea>
            </div>

            <div class="form-group">
                <label for="domains">Domaines autorisés (un par ligne)</label>
                <textarea id="domains" name="domains" class="form-control" placeholder="example.com&#10;subdomain.example.com"></textarea>
                <small>Laissez vide pour autoriser tous les domaines du même site</small>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="max_depth">Profondeur max</label>
                    <input type="number" id="max_depth" name="max_depth" class="form-control" value="3" min="1" max="10">
                </div>

                <div class="form-group">
                    <label for="crawl_delay">Délai entre requêtes (sec)</label>
                    <input type="number" id="crawl_delay" name="crawl_delay" class="form-control" value="1" min="0" max="10">
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="respect_robots" checked> Respecter robots.txt
                </label>
                <small>Recommandé pour respecter les directives des sites. Décochez pour ignorer robots.txt (moteurs privés)</small>
            </div>

            <div class="form-actions">
                <button type="submit" name="create_project" class="btn btn-primary">Créer le projet</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('projectModal')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal nouveau site -->
<div id="siteModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('siteModal')">&times;</span>
        <h2>Ajouter un site</h2>

        <form method="POST">
            <input type="hidden" id="site_project_id" name="project_id">

            <div class="form-group">
                <label for="base_url">URL de base *</label>
                <input type="url" id="base_url" name="base_url" class="form-control" required placeholder="https://example.com">
                <small>URL complète du site à crawler (avec http:// ou https://)</small>
            </div>

            <div class="form-actions">
                <button type="submit" name="add_site" class="btn btn-success">Ajouter le site</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('siteModal')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal paramètres crawling -->
<div id="crawlSettingsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('crawlSettingsModal')">&times;</span>
        <h2>Paramètres de crawling</h2>

        <div class="form-group">
            <label for="max_pages">Nombre maximum de pages</label>
            <input type="number" id="max_pages" class="form-control" value="50" min="1" max="1000">
            <small>Limiter le nombre de pages à crawler pour ce lancement</small>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="force_recrawl"> Forcer le re-crawling des pages existantes
            </label>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-warning" onclick="startCustomCrawl()">🕷️ Lancer le crawling</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('crawlSettingsModal')">Annuler</button>
        </div>
    </div>
</div>

<!-- Modal détails site -->
<div id="siteDetailsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('siteDetailsModal')">&times;</span>
        <h2>Détails du site</h2>
        <div id="siteDetailsContent">
            <!-- Contenu chargé dynamiquement -->
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../assets/script.js"></script>
<script src="../assets/crawl.js"></script>

<script>
    let currentSiteId = null;

    // Fonctions pour les modals
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function openSiteModal(projectId) {
        document.getElementById('site_project_id').value = projectId;
        openModal('siteModal');
    }

    function showCrawlSettings(siteId) {
        currentSiteId = siteId;
        openModal('crawlSettingsModal');
    }

    function startCustomCrawl() {
        if (!currentSiteId) return;

        const maxPages = document.getElementById('max_pages').value;
        if (window.crawlManager) {
            window.crawlManager.startCrawl(currentSiteId, parseInt(maxPages));
            closeModal('crawlSettingsModal');
        } else {
            console.error('CrawlManager non initialisé');
            showNotification('Erreur: Système de crawling non disponible', 'error');
        }
    }

    // Fonction de crawling rapide
    function startQuickCrawl(siteId) {
        if (window.crawlManager) {
            window.crawlManager.startCrawl(siteId, 50);
        } else {
            console.error('CrawlManager non initialisé');
            showNotification('Erreur: Système de crawling non disponible', 'error');
        }
    }

    // Test de l'API
    async function testCrawlAPI() {
        try {
            const response = await fetch('../api/crawl_api.php');
            const result = await response.json();

            if (result.success) {
                showNotification('API Crawling fonctionnelle !', 'success');
                console.log('API Response:', result);
            } else {
                showNotification('Erreur API: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Erreur connexion API: ' + error.message, 'error');
            console.error('Erreur API:', error);
        }
    }

    // Actualiser tous les statuts
    function refreshAllStatus() {
        if (window.crawlManager) {
            window.crawlManager.loadCrawlStatus();
            showNotification('Statuts actualisés', 'info');
        }
    }

    // Voir détails d'un site
    async function viewSiteDetails(siteId) {
        try {
            const response = await fetch(`../api/crawl_api.php?action=status&site_id=${siteId}`);
            const result = await response.json();

            if (result.success) {
                const site = result.data;
                document.getElementById('siteDetailsContent').innerHTML = `
                        <div class="site-details">
                            <p><strong>Domaine:</strong> ${site.domain}</p>
                            <p><strong>URL:</strong> <a href="${site.base_url}" target="_blank">${site.base_url}</a></p>
                            <p><strong>Documents indexés:</strong> ${site.documents_count || 0}</p>
                            <p><strong>Queue en attente:</strong> ${site.queue_pending || 0}</p>
                            <p><strong>Queue en traitement:</strong> ${site.queue_processing || 0}</p>
                            <p><strong>Statut:</strong> <span class="status ${site.status}">${site.status}</span></p>
                            <p><strong>Dernier crawl:</strong> ${site.last_crawled || 'Jamais'}</p>
                        </div>
                    `;
                openModal('siteDetailsModal');
            } else {
                showNotification('Erreur chargement détails', 'error');
            }
        } catch (error) {
            showNotification('Erreur: ' + error.message, 'error');
        }
    }

    // Fermer modal en cliquant à l'extérieur
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page admin chargée');

        // Vérifier que le crawlManager est bien initialisé
        setTimeout(() => {
            if (window.crawlManager) {
                console.log('CrawlManager initialisé avec succès');
                window.crawlManager.loadCrawlStatus();
            } else {
                console.warn('CrawlManager non trouvé');
            }
        }, 1000);
    });
</script>
</body>
</html>
