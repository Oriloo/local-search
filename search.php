<?php
// Définir le chemin racine
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/search_engine.php';

$search_engine = new SearchEngine();
$advanced_search = new AdvancedSearchEngine();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$project_id = isset($_GET['project']) ? (int)$_GET['project'] : null;
$content_type = isset($_GET['type']) ? $_GET['type'] : null;
$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$synonyms = !isset($_GET['no_synonyms']);
$exact = isset($_GET['exact']);
$advanced = isset($_GET['advanced']) ? true : false;

$results = null;
$search_time = 0;

if ($query) {
    $options = [
        'project_id' => $project_id,
        'content_type' => $content_type,
        'site_id' => $site_id,
        'sort' => $sort,
        'page' => $page,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'per_page' => 20,
        'include_synonyms' => $synonyms,
        'exact_phrase' => $exact
    ];

    $results = $advanced_search->search($query, $options);
    $search_time = round($results['search_time'] * 1000, 2);
}

$projects = $search_engine->getProjects();
?>

    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $query ? htmlspecialchars($query) . ' - ' : '' ?><?= APP_NAME ?></title>
        <link rel="stylesheet" href="assets/common.css">
        <link rel="stylesheet" href="assets/search.css">
        <link rel="stylesheet" href="assets/media-preview.css">
    </head>
    <body>
    <header class="search-header">
        <div class="container">
            <h1 class="logo"><?= APP_NAME ?></h1>
            <p class="tagline">Recherche intelligente avec analyse sémantique</p>

            <div class="search-form">
                <!-- FORMULAIRE PRINCIPAL CORRIGÉ -->
                <form method="GET" id="searchForm" class="search-box">
                    <input type="text"
                           name="q"
                           class="search-input"
                           value="<?= htmlspecialchars($query) ?>"
                           placeholder="Recherche avancée avec synonymes..."
                           autofocus>
                    <button type="submit" class="search-btn">🔍 Rechercher</button>

                    <!-- CHAMPS CACHÉS POUR PRÉSERVER LES FILTRES -->
                    <input type="hidden" name="project" value="<?= $project_id ?>">
                    <input type="hidden" name="type" value="<?= $content_type ?>">
                    <input type="hidden" name="site_id" value="<?= $site_id ?>">
                    <input type="hidden" name="sort" value="<?= $sort ?>">
                    <input type="hidden" name="date_from" value="<?= $date_from ?>">
                    <input type="hidden" name="date_to" value="<?= $date_to ?>">
                    <?php if (!$synonyms): ?>
                        <input type="hidden" name="no_synonyms" value="1">
                    <?php endif; ?>
                    <?php if ($exact): ?>
                        <input type="hidden" name="exact" value="1">
                    <?php endif; ?>
                    <?php if ($advanced): ?>
                        <input type="hidden" name="advanced" value="1">
                    <?php endif; ?>
                </form>

                <div class="filters">
                    <!-- SELECT AVEC JAVASCRIPT POUR MISE À JOUR -->
                    <select name="project" class="filter-select" data-param="project">
                        <option value="">Tous les projets</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['id'] ?>" <?= $project_id == $project['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="type" class="filter-select" data-param="type">
                        <option value="">Tous les types</option>
                        <option value="webpage" <?= $content_type == 'webpage' ? 'selected' : '' ?>>Pages web</option>
                        <option value="image" <?= $content_type == 'image' ? 'selected' : '' ?>>Images</option>
                        <option value="video" <?= $content_type == 'video' ? 'selected' : '' ?>>Vidéos</option>
                    </select>

                    <select name="sort" class="filter-select" data-param="sort">
                        <option value="relevance" <?= $sort == 'relevance' ? 'selected' : '' ?>>Pertinence</option>
                        <option value="date" <?= $sort == 'date' ? 'selected' : '' ?>>Date</option>
                        <option value="title" <?= $sort == 'title' ? 'selected' : '' ?>>Titre</option>
                        <option value="pagerank" <?= $sort == 'pagerank' ? 'selected' : '' ?>>Popularité</option>
                    </select>

                    <button type="button" class="btn btn-secondary" onclick="toggleAdvanced()">
                        ⚙️ Avancé <?= $advanced ? '(actif)' : '' ?>
                    </button>

                    <?php if ($query): ?>
                        <a href="search.php" class="btn btn-secondary">🗑️ Effacer</a>
                    <?php endif; ?>
                </div>

                <div class="advanced-search <?= $advanced ? 'active' : '' ?>" id="advancedSearch">
                    <h4>Options de recherche avancées</h4>
                    <div class="search-options">
                        <div class="search-option">
                            <input type="checkbox"
                                   id="synonyms"
                                   name="synonyms"
                                <?= $synonyms ? 'checked' : '' ?>
                                   onchange="updateAdvancedOption('synonyms', this.checked)">
                            <label for="synonyms">Inclure les synonymes</label>
                        </div>
                        <div class="search-option">
                            <input type="checkbox"
                                   id="exact"
                                   name="exact"
                                <?= $exact ? 'checked' : '' ?>
                                   onchange="updateAdvancedOption('exact', this.checked)">
                            <label for="exact">Phrase exacte seulement</label>
                        </div>
                    </div>

                    <div class="advanced-grid">
                        <div class="form-group">
                            <label for="date_from">Date de début</label>
                            <input type="date"
                                   id="date_from"
                                   name="date_from"
                                   class="form-control"
                                   value="<?= $date_from ?>"
                                   onchange="updateAdvancedOption('date_from', this.value)">
                        </div>
                        <div class="form-group">
                            <label for="date_to">Date de fin</label>
                            <input type="date"
                                   id="date_to"
                                   name="date_to"
                                   class="form-control"
                                   value="<?= $date_to ?>"
                                   onchange="updateAdvancedOption('date_to', this.value)">
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="button" class="btn btn-primary" onclick="applyAdvancedSearch()">
                            ✨ Appliquer la recherche avancée
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetAdvancedSearch()">
                            🔄 Réinitialiser
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if ($query && $results): ?>
            <!-- Statistiques de recherche -->
            <div class="search-stats">
                <strong><?= number_format($results['total_results']) ?></strong> résultats trouvés
                pour "<strong><?= htmlspecialchars($query) ?></strong>"
                en <strong><?= $search_time ?> ms</strong>

                <!-- Afficher les filtres actifs -->
                <?php
                $activeFilters = [];
                if ($project_id) {
                    $projectName = '';
                    foreach ($projects as $p) {
                        if ($p['id'] == $project_id) {
                            $projectName = $p['name'];
                            break;
                        }
                    }
                    $activeFilters[] = "Projet: $projectName";
                }
                if ($content_type) $activeFilters[] = "Type: " . ucfirst($content_type);
                if ($sort !== 'relevance') $activeFilters[] = "Tri: " . ucfirst($sort);
                if ($date_from) $activeFilters[] = "Depuis: $date_from";
                if ($date_to) $activeFilters[] = "Jusqu'à: $date_to";
                if (!$synonyms) $activeFilters[] = "Sans synonymes";
                if ($exact) $activeFilters[] = "Phrase exacte";

                if (!empty($activeFilters)): ?>
                    <br><small><strong>Filtres actifs:</strong> <?= implode(' • ', $activeFilters) ?></small>
                <?php endif; ?>

                <?php if (!empty($results['analysis']['terms'])): ?>
                    <br><small>
                        Termes analysés:
                        <?php foreach ($results['analysis']['terms'] as $term): ?>
                            <span class="badge"><?= htmlspecialchars($term['term']) ?></span>
                        <?php endforeach; ?>
                    </small>
                <?php endif; ?>
            </div>

            <div class="layout-with-sidebar">
                <div class="results">
                    <?php if (empty($results['results'])): ?>
                        <div class="no-results">
                            <h2>Aucun résultat trouvé</h2>
                            <p>Essayez avec des mots-clés différents ou utilisez les suggestions ci-dessous.</p>

                            <?php if (!empty($results['suggestions'])): ?>
                                <div class="suggestions">
                                    <h4>Suggestions :</h4>
                                    <?php foreach ($results['suggestions'] as $suggestion): ?>
                                        <a href="<?= buildSearchUrl($suggestion['text'], $project_id, $content_type, $sort) ?>" class="suggestion-item">
                                            <?= htmlspecialchars($suggestion['text']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($results['results'] as $result): ?>
                            <div class="result-item">
                                <div class="result-title">
                                    <a href="<?= htmlspecialchars($result['url']) ?>" target="_blank">
                                        <?= $result['highlighted_title'] ?: htmlspecialchars($result['title'] ?: 'Sans titre') ?>
                                    </a>
                                </div>
                                <div class="result-url"><?= htmlspecialchars($result['url']) ?></div>

                                <?php if (!empty($result['snippet'])): ?>
                                    <div class="highlighted-snippet">
                                        <?= $result['snippet'] ?>
                                    </div>
                                <?php elseif (!empty($result['highlighted_description'])): ?>
                                    <div class="result-snippet">
                                        <?= $result['highlighted_description'] ?>
                                    </div>
                                <?php endif; ?>

                                <div class="result-meta">
                                    <span class="content-type <?= $result['content_type'] ?>">
                                        <?= ucfirst($result['content_type']) ?>
                                    </span>
                                    <span>Projet: <?= htmlspecialchars($result['project_name']) ?></span>
                                    <span>Domaine: <?= htmlspecialchars($result['domain']) ?></span>

                                    <?php if (!empty($result['reading_time'])): ?>
                                        <span>📖 <?= $result['reading_time']['text'] ?></span>
                                    <?php endif; ?>

                                    <?php if (!empty($result['score_breakdown'])): ?>
                                        <span>Score: <?= $result['score_breakdown']['relevance'] ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($advanced && !empty($result['content_quality']['factors'])): ?>
                                    <div class="result-meta-advanced">
                                        <div class="score-breakdown">
                                            <span>Pertinence: <?= $result['score_breakdown']['relevance'] ?></span>
                                            <span>PageRank: <?= $result['score_breakdown']['pagerank'] ?></span>
                                            <span>Qualité: <?= $result['score_breakdown']['quality'] ?></span>
                                        </div>
                                        <div style="margin-top: 5px;">
                                            Qualité: <?= implode(', ', $result['content_quality']['factors']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($results['total_results'] > 20): ?>
                            <div class="pagination">
                                <?php
                                $total_pages = ceil($results['total_results'] / 20);
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                ?>

                                <?php if ($page > 1): ?>
                                    <a href="<?= buildSearchUrl($query, $project_id, $content_type, $sort, $page-1, $date_from, $date_to, $synonyms, $exact) ?>" class="page-btn">← Précédent</a>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="<?= buildSearchUrl($query, $project_id, $content_type, $sort, $i, $date_from, $date_to, $synonyms, $exact) ?>"
                                       class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildSearchUrl($query, $project_id, $content_type, $sort, $page+1, $date_from, $date_to, $synonyms, $exact) ?>" class="page-btn">Suivant →</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar avec facettes -->
                <aside class="sidebar">
                    <?php if (!empty($results['facets'])): ?>
                        <div class="facets">
                            <h3>Affiner la recherche</h3>

                            <?php if (!empty($results['facets']['content_types'])): ?>
                                <div class="facet-group">
                                    <h4>Types de contenu</h4>
                                    <?php foreach ($results['facets']['content_types'] as $facet): ?>
                                        <div class="facet-item">
                                            <a href="<?= buildSearchUrl($query, $project_id, $facet['content_type'], $sort, 1, $date_from, $date_to, $synonyms, $exact) ?>">
                                                <?= ucfirst($facet['content_type']) ?>
                                            </a>
                                            <span class="facet-count"><?= $facet['count'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($results['facets']['projects']) && !$project_id): ?>
                                <div class="facet-group">
                                    <h4>Projets</h4>
                                    <?php foreach (array_slice($results['facets']['projects'], 0, 5) as $facet): ?>
                                        <div class="facet-item">
                                            <a href="<?= buildSearchUrl($query, $facet['id'], $content_type, $sort, 1, $date_from, $date_to, $synonyms, $exact) ?>">
                                                <?= htmlspecialchars($facet['name']) ?>
                                            </a>
                                            <span class="facet-count"><?= $facet['count'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($results['facets']['sites'])): ?>
                                <div class="facet-group">
                                    <h4>Sites</h4>
                                    <?php foreach (array_slice($results['facets']['sites'], 0, 5) as $facet): ?>
                                        <div class="facet-item">
                                            <a href="<?= buildSearchUrl($query, $project_id, $content_type, $sort, 1, $date_from, $date_to, $synonyms, $exact, $facet['id']) ?>">
                                                <?= htmlspecialchars($facet['domain']) ?>
                                            </a>
                                            <span class="facet-count"><?= $facet['count'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($results['suggestions'])): ?>
                        <div class="facets">
                            <h3>Suggestions</h3>
                            <div class="suggestions">
                                <?php foreach ($results['suggestions'] as $suggestion): ?>
                                    <a href="<?= buildSearchUrl($suggestion['text'], $project_id, $content_type, $sort, 1, $date_from, $date_to, $synonyms, $exact) ?>" class="suggestion-item">
                                        <?= htmlspecialchars($suggestion['text']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        <?php elseif ($query): ?>
            <div class="no-results">
                <h2>Recherche en cours...</h2>
            </div>
        <?php else: ?>
            <div class="no-results">
                <h2>Bienvenue sur <?= APP_NAME ?></h2>
                <p>Utilisez la barre de recherche ci-dessus pour commencer votre recherche intelligente.</p>
                <div style="margin-top: 30px;">
                    <h3>🚀 Fonctionnalités avancées :</h3>
                    <ul style="text-align: left; display: inline-block; margin-top: 15px;">
                        <li>🔍 <strong>Recherche sémantique</strong> avec synonymes automatiques</li>
                        <li>📊 <strong>Scoring intelligent</strong> basé sur la pertinence</li>
                        <li>🎯 <strong>Extraits mis en évidence</strong> avec termes de recherche</li>
                        <li>📈 <strong>Facettes</strong> pour affiner vos résultats</li>
                        <li>💡 <strong>Suggestions automatiques</strong> pour améliorer vos recherches</li>
                        <li>⏱️ <strong>Temps de lecture estimé</strong> pour chaque document</li>
                    </ul>
                </div>
                <?php if (empty($projects)): ?>
                    <p style="margin-top: 30px;"><strong>Pour commencer :</strong> <a href="admin/">Créez votre premier projet</a> et ajoutez des sites à indexer.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <a href="admin/" class="admin-link">⚙️ Administration</a>

    <script>
        // Gestion des filtres avec mise à jour URL
        function updateFilter(paramName, value) {
            const form = document.getElementById('searchForm');
            const hiddenInput = form.querySelector(`input[name="${paramName}"]`);

            if (hiddenInput) {
                hiddenInput.value = value;
            }

            // Si il y a une requête, soumettre automatiquement
            const queryInput = form.querySelector('input[name="q"]');
            if (queryInput && queryInput.value.trim()) {
                form.submit();
            }
        }

        // Gestion des selects de filtre
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                const paramName = this.getAttribute('data-param');
                updateFilter(paramName, this.value);
            });
        });

        // Gestion des options avancées
        function updateAdvancedOption(optionName, value) {
            const form = document.getElementById('searchForm');

            if (optionName === 'synonyms') {
                // Gérer le paramètre no_synonyms (inverse)
                let noSynonymsInput = form.querySelector('input[name="no_synonyms"]');
                if (!value) { // Si synonymes désactivés
                    if (!noSynonymsInput) {
                        noSynonymsInput = document.createElement('input');
                        noSynonymsInput.type = 'hidden';
                        noSynonymsInput.name = 'no_synonyms';
                        form.appendChild(noSynonymsInput);
                    }
                    noSynonymsInput.value = '1';
                } else { // Si synonymes activés
                    if (noSynonymsInput) {
                        noSynonymsInput.remove();
                    }
                }
            } else if (optionName === 'exact') {
                let exactInput = form.querySelector('input[name="exact"]');
                if (value) {
                    if (!exactInput) {
                        exactInput = document.createElement('input');
                        exactInput.type = 'hidden';
                        exactInput.name = 'exact';
                        form.appendChild(exactInput);
                    }
                    exactInput.value = '1';
                } else {
                    if (exactInput) {
                        exactInput.remove();
                    }
                }
            } else {
                // Pour date_from et date_to
                let input = form.querySelector(`input[name="${optionName}"]`);
                if (input) {
                    input.value = value;
                }
            }
        }

        // Toggle advanced search
        function toggleAdvanced() {
            const advanced = document.getElementById('advancedSearch');
            const isActive = advanced.classList.toggle('active');

            const form = document.getElementById('searchForm');
            let advancedInput = form.querySelector('input[name="advanced"]');

            if (isActive) {
                if (!advancedInput) {
                    advancedInput = document.createElement('input');
                    advancedInput.type = 'hidden';
                    advancedInput.name = 'advanced';
                    form.appendChild(advancedInput);
                }
                advancedInput.value = '1';
            } else {
                if (advancedInput) {
                    advancedInput.remove();
                }
            }
        }

        // Appliquer recherche avancée
        function applyAdvancedSearch() {
            const form = document.getElementById('searchForm');
            const queryInput = form.querySelector('input[name="q"]');

            if (queryInput && queryInput.value.trim()) {
                form.submit();
            } else {
                alert('Veuillez saisir une requête de recherche');
                queryInput.focus();
            }
        }

        // Réinitialiser recherche avancée
        function resetAdvancedSearch() {
            // Réinitialiser les champs
            document.getElementById('synonyms').checked = true;
            document.getElementById('exact').checked = false;
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';

            // Mettre à jour le formulaire
            updateAdvancedOption('synonyms', true);
            updateAdvancedOption('exact', false);
            updateAdvancedOption('date_from', '');
            updateAdvancedOption('date_to', '');

            // Relancer la recherche si nécessaire
            const form = document.getElementById('searchForm');
            const queryInput = form.querySelector('input[name="q"]');
            if (queryInput && queryInput.value.trim()) {
                form.submit();
            }
        }

        // Auto-complétion simple (optionnel)
        let searchTimeout;
        const searchInput = document.querySelector('.search-input');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();

                if (query.length >= 3) {
                    searchTimeout = setTimeout(() => {
                        // Ici on pourrait ajouter des suggestions en temps réel
                        console.log('Recherche suggestion pour:', query);
                    }, 300);
                }
            });
        }
    </script>
    <script src="assets/media-preview.js"></script>
    </body>
    </html>

<?php
/**
 * Fonction helper pour construire les URLs de recherche
 */
function buildSearchUrl($query, $project_id = null, $content_type = null, $sort = 'relevance', $page = 1, $date_from = null, $date_to = null, $synonyms = true, $exact = false, $site_id = null) {
    $params = [];

    if ($query) $params['q'] = $query;
    if ($project_id) $params['project'] = $project_id;
    if ($content_type) $params['type'] = $content_type;
    if ($site_id) $params['site_id'] = $site_id;
    if ($sort && $sort !== 'relevance') $params['sort'] = $sort;
    if ($page && $page > 1) $params['page'] = $page;
    if ($date_from) $params['date_from'] = $date_from;
    if ($date_to) $params['date_to'] = $date_to;
    if (!$synonyms) $params['no_synonyms'] = '1';
    if ($exact) $params['exact'] = '1';

    return 'search.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}
?>
