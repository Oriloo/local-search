<?php
// Définir le chemin racine seulement s'il n'est pas déjà défini
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

$search_engine = new SearchEngine();
$project = null;
$mode = 'create';

// Vérifier si on édite un projet existant
if (isset($_GET['id'])) {
    $project = $search_engine->getProject($_GET['id']);
    if ($project) {
        $mode = 'edit';
    }
}

// Traitement du formulaire
if ($_POST) {
    if ($mode === 'edit' && isset($_POST['update_project'])) {
        // Mise à jour du projet
        $domains = array_filter(array_map('trim', explode("\n", $_POST['domains'])));
        $config = [
            'max_depth' => (int)$_POST['max_depth'],
            'crawl_delay' => (int)$_POST['crawl_delay'],
            'respect_robots' => isset($_POST['respect_robots'])
        ];

        if ($search_engine->updateProject($project['id'], $_POST['name'], $_POST['description'], $domains, $config)) {
            header('Location: index.php?success=project_updated');
            exit;
        } else {
            $error = "Erreur lors de la mise à jour du projet.";
        }
    } elseif ($mode === 'create' && isset($_POST['create_project'])) {
        // Création du projet
        $domains = array_filter(array_map('trim', explode("\n", $_POST['domains'])));
        $config = [
            'max_depth' => (int)$_POST['max_depth'],
            'crawl_delay' => (int)$_POST['crawl_delay'],
            'respect_robots' => isset($_POST['respect_robots'])
        ];

        if ($search_engine->createProject($_POST['name'], $_POST['description'], $domains, $config)) {
            header('Location: index.php?success=project_created');
            exit;
        } else {
            $error = "Erreur lors de la création du projet.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Éditer' : 'Nouveau' ?> Projet - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/common.css">
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<div class="container">
    <header class="header">
        <h1><?= APP_NAME ?> - <?= $mode === 'edit' ? 'Éditer' : 'Nouveau' ?> Projet</h1>
        <nav class="nav">
            <a href="index.php">← Retour aux projets</a>
            <a href="../search.php">Recherche</a>
            <a href="crawl_status.php">Statut Crawling</a>
        </nav>
    </header>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <h2><?= $mode === 'edit' ? 'Éditer le projet' : 'Nouveau projet de recherche' ?></h2>

        <form method="POST">
            <div class="form-group">
                <label for="name">Nom du projet *</label>
                <input type="text" id="name" name="name" class="form-control" required
                       value="<?= $project ? htmlspecialchars($project['name']) : '' ?>"
                       placeholder="ex: Manga, Local, Fruits...">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control"
                          placeholder="Description du projet..."><?= $project ? htmlspecialchars($project['description']) : '' ?></textarea>
            </div>

            <div class="form-group">
                <label for="domains">Domaines autorisés (un par ligne)</label>
                <textarea id="domains" name="domains" class="form-control"
                          placeholder="example.com&#10;subdomain.example.com"><?php
                    if ($project && $project['base_domains']) {
                        $domains = json_decode($project['base_domains'], true);
                        echo htmlspecialchars(implode("\n", $domains));
                    }
                    ?></textarea>
                <small>Laissez vide pour autoriser tous les domaines du même site</small>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="max_depth">Profondeur maximale</label>
                    <input type="number" id="max_depth" name="max_depth" class="form-control"
                           value="<?= $project ? json_decode($project['crawl_config'], true)['max_depth'] ?? 3 : 3 ?>"
                           min="1" max="10">
                    <small>Nombre de niveaux de liens à suivre</small>
                </div>

                <div class="form-group">
                    <label for="crawl_delay">Délai entre requêtes (secondes)</label>
                    <input type="number" id="crawl_delay" name="crawl_delay" class="form-control"
                           value="<?= $project ? json_decode($project['crawl_config'], true)['crawl_delay'] ?? 1 : 1 ?>"
                           min="0" max="10" step="0.1">
                    <small>Temps d'attente entre chaque page crawlée</small>
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="respect_robots"
                        <?php
                        if ($project) {
                            $config = json_decode($project['crawl_config'], true);
                            echo isset($config['respect_robots']) && $config['respect_robots'] ? 'checked' : '';
                        } else {
                            echo 'checked'; // Default to checked for new projects
                        }
                        ?>>
                    Respecter robots.txt
                </label>
                <small>Recommandé pour respecter les directives des sites. Décochez pour ignorer robots.txt (moteurs privés)</small>
            </div>

            <div class="form-group">
                <label for="status">Statut du projet</label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?= $project && $project['status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                    <option value="paused" <?= $project && $project['status'] === 'paused' ? 'selected' : '' ?>>En pause</option>
                    <option value="archived" <?= $project && $project['status'] === 'archived' ? 'selected' : '' ?>>Archivé</option>
                </select>
            </div>

            <div class="form-actions">
                <?php if ($mode === 'edit'): ?>
                    <button type="submit" name="update_project" class="btn btn-primary">💾 Mettre à jour le projet</button>
                    <a href="index.php" class="btn btn-secondary">Annuler</a>
                <?php else: ?>
                    <button type="submit" name="create_project" class="btn btn-primary">✨ Créer le projet</button>
                    <a href="index.php" class="btn btn-secondary">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($mode === 'edit'): ?>
        <div class="card">
            <h2>Statistiques du projet</h2>
            <?php
            $stats = $search_engine->getStats($project['id']);
            $sites = $search_engine->getSites($project['id']);
            ?>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= count($sites) ?></div>
                    <div class="stat-label">Sites configurés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['total_documents']) ?></div>
                    <div class="stat-label">Documents indexés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['by_type']['webpage'] ?? 0 ?></div>
                    <div class="stat-label">Pages web</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= ($stats['by_type']['image'] ?? 0) + ($stats['by_type']['video'] ?? 0) ?></div>
                    <div class="stat-label">Médias</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .form-actions {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #ecf0f1;
    }

    .btn-secondary {
        background: #95a5a6;
        color: white;
        text-decoration: none;
        display: inline-block;
        padding: 12px 24px;
        border-radius: 6px;
        margin-left: 10px;
    }

    .btn-secondary:hover {
        background: #7f8c8d;
    }

    small {
        display: block;
        color: #6c757d;
        margin-top: 5px;
        font-size: 0.9em;
    }
</style>
</body>
</html>
