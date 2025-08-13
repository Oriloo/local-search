<?php
// Définir le chemin racine seulement s'il n'est pas déjà défini
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/functions.php';

$search_engine = new SearchEngine();

// Récupérer les données
$database = new Database();
$db = $database->connect();

// Statistiques des crawlings
$sql = "SELECT ch.*, sp.name as project_name 
        FROM crawl_history ch
        JOIN search_projects sp ON ch.project_id = sp.id
        ORDER BY ch.started_at DESC 
        LIMIT 20";
$crawl_history = $db->query($sql)->fetchAll();

// Queue actuelle
$sql = "SELECT cq.*, sp.name as project_name 
        FROM crawl_queue cq
        JOIN search_projects sp ON cq.project_id = sp.id
        WHERE cq.status IN ('pending', 'processing')
        ORDER BY cq.priority DESC, cq.scheduled_at ASC 
        LIMIT 50";
$crawl_queue = $db->query($sql)->fetchAll();

// Statistiques par statut
$sql = "SELECT status, COUNT(*) as count FROM crawl_queue GROUP BY status";
$queue_stats = $db->query($sql)->fetchAll();

// Sites avec erreurs
$sql = "SELECT s.*, sp.name as project_name,
        (SELECT COUNT(*) FROM crawl_queue WHERE project_id = s.project_id AND status = 'failed') as failed_count
        FROM sites s 
        JOIN search_projects sp ON s.project_id = sp.id 
        WHERE s.status = 'error' OR 
        (SELECT COUNT(*) FROM crawl_queue WHERE project_id = s.project_id AND status = 'failed') > 0
        ORDER BY failed_count DESC";
$error_sites = $db->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statut Crawling - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/common.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/crawl-status.css">
    <style>
        .refresh-btn {
            float: right;
            margin-bottom: 20px;
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .refresh-btn:hover { background: #2980b9; }
        .queue-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #3498db;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .queue-item.failed { border-left-color: #e74c3c; }
        .queue-item.processing { border-left-color: #f39c12; }
        .auto-refresh {
            color: #6c757d;
            font-size: 0.9em;
            float: right;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <header class="header">
        <h1><?= APP_NAME ?> - Statut Crawling</h1>
        <p>Surveillance en temps réel des opérations de crawling</p>
        <nav class="nav">
            <a href="index.php">Projets</a>
            <a href="../search.php">Recherche</a>
            <a href="#" class="active">Statut Crawling</a>
        </nav>
    </header>

    <!-- Statistiques de la queue -->
    <div class="card">
        <h2>
            Queue de crawling
            <button class="refresh-btn" onclick="location.reload()">🔄 Actualiser</button>
            <span class="auto-refresh">Auto-refresh dans <span id="countdown">30</span>s</span>
        </h2>

        <div class="stats">
            <?php
            $stats_array = [];
            foreach ($queue_stats as $stat) {
                $stats_array[$stat['status']] = $stat['count'];
            }
            ?>
            <div class="stat-card">
                <div class="stat-number"><?= $stats_array['pending'] ?? 0 ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats_array['processing'] ?? 0 ?></div>
                <div class="stat-label">En cours</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats_array['failed'] ?? 0 ?></div>
                <div class="stat-label">Échoués</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats_array['completed'] ?? 0 ?></div>
                <div class="stat-label">Terminés</div>
            </div>
        </div>

        <?php if (!empty($crawl_queue)): ?>
            <h3>URLs en cours de traitement</h3>
            <?php foreach ($crawl_queue as $item): ?>
                <div class="queue-item <?= $item['status'] ?>">
                    <strong><?= htmlspecialchars($item['project_name']) ?></strong><br>
                    <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank"><?= htmlspecialchars($item['url']) ?></a><br>
                    <small>
                        Profondeur: <?= $item['depth'] ?> |
                        Priorité: <?= $item['priority'] ?> |
                        Statut: <?= ucfirst($item['status']) ?> |
                        Planifié: <?= timeAgo($item['scheduled_at']) ?>
                        <?php if ($item['status'] === 'failed' && $item['last_error']): ?>
                            <br><span style="color: #e74c3c;">Erreur: <?= htmlspecialchars($item['last_error']) ?></span>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucune URL en cours de traitement.</p>
        <?php endif; ?>
    </div>

    <!-- Sites avec erreurs -->
    <?php if (!empty($error_sites)): ?>
        <div class="card">
            <h2>Sites avec erreurs</h2>
            <table class="table">
                <thead>
                <tr>
                    <th>Projet</th>
                    <th>Domaine</th>
                    <th>URL de base</th>
                    <th>Erreurs</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($error_sites as $site): ?>
                    <tr>
                        <td><?= htmlspecialchars($site['project_name']) ?></td>
                        <td><?= htmlspecialchars($site['domain']) ?></td>
                        <td><a href="<?= htmlspecialchars($site['base_url']) ?>" target="_blank"><?= htmlspecialchars($site['base_url']) ?></a></td>
                        <td><?= $site['failed_count'] ?> URLs échouées</td>
                        <td><span class="status error"><?= ucfirst($site['status']) ?></span></td>
                        <td>
                            <button class="btn btn-warning btn-crawl" data-site-id="<?= $site['id'] ?>">🔄 Relancer</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Historique des crawlings -->
    <div class="card">
        <h2>Historique des crawlings</h2>
        <?php if (!empty($crawl_history)): ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Projet</th>
                    <th>Démarré</th>
                    <th>Durée</th>
                    <th>URLs découvertes</th>
                    <th>Succès</th>
                    <th>Échecs</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($crawl_history as $history): ?>
                    <tr>
                        <td><?= htmlspecialchars($history['project_name']) ?></td>
                        <td><?= timeAgo($history['started_at']) ?></td>
                        <td>
                            <?php if ($history['completed_at']): ?>
                                <?php
                                $duration = strtotime($history['completed_at']) - strtotime($history['started_at']);
                                echo gmdate("H:i:s", $duration);
                                ?>
                            <?php else: ?>
                                <span class="status processing">En cours...</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($history['urls_discovered']) ?></td>
                        <td><?= number_format($history['urls_successful']) ?></td>
                        <td><?= number_format($history['urls_failed']) ?></td>
                        <td>
                            <?php if ($history['completed_at']): ?>
                                <span class="status active">Terminé</span>
                            <?php else: ?>
                                <span class="status processing">En cours</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun crawling effectué pour le moment.</p>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/crawl.js"></script>
<script>
    // Auto-refresh de la page
    let countdown = 30;
    const countdownEl = document.getElementById('countdown');

    const timer = setInterval(() => {
        countdown--;
        countdownEl.textContent = countdown;

        if (countdown <= 0) {
            location.reload();
        }
    }, 1000);

    // Arrêter l'auto-refresh si l'utilisateur interagit
    document.addEventListener('click', () => {
        clearInterval(timer);
        countdownEl.textContent = 'Arrêté';
    });
</script>
</body>
</html>
