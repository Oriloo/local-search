<?php
// Utiliser le chemin absolu pour les includes
require_once dirname(__DIR__) . '/config/database.php';

class SearchEngine {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    // Gestion des projets
    public function createProject($name, $description, $domains, $config = []) {
        $sql = "INSERT INTO search_projects (name, description, base_domains, crawl_config) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $name,
            $description,
            json_encode($domains),
            json_encode($config)
        ]);
    }

    public function updateProject($id, $name, $description, $domains, $config = []) {
        $sql = "UPDATE search_projects SET name = ?, description = ?, base_domains = ?, crawl_config = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $name,
            $description,
            json_encode($domains),
            json_encode($config),
            $id
        ]);
    }

    public function getProjects() {
        $sql = "SELECT *, 
                (SELECT COUNT(*) FROM sites WHERE project_id = sp.id) as sites_count,
                (SELECT COUNT(*) FROM documents WHERE project_id = sp.id) as documents_count
                FROM search_projects sp ORDER BY created_at DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getProject($id) {
        $sql = "SELECT * FROM search_projects WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // Gestion des sites
    public function addSite($project_id, $domain, $base_url) {
        $sql = "INSERT INTO sites (project_id, domain, base_url) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$project_id, $domain, $base_url]);
    }

    public function getSites($project_id = null) {
        if ($project_id) {
            $sql = "SELECT s.*, sp.name as project_name FROM sites s 
                    JOIN search_projects sp ON s.project_id = sp.id 
                    WHERE s.project_id = ? ORDER BY s.domain";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$project_id]);
            return $stmt->fetchAll();
        } else {
            $sql = "SELECT s.*, sp.name as project_name FROM sites s 
                    JOIN search_projects sp ON s.project_id = sp.id 
                    ORDER BY sp.name, s.domain";
            return $this->db->query($sql)->fetchAll();
        }
    }

    // Recherche
    public function search($query, $project_id = null, $content_type = null, $page = 1) {
        $offset = ($page - 1) * RESULTS_PER_PAGE;
        $where_conditions = [];
        $params = [];

        // Construction de la requête
        if ($project_id) {
            $where_conditions[] = "d.project_id = ?";
            $params[] = $project_id;
        }

        if ($content_type) {
            $where_conditions[] = "d.content_type = ?";
            $params[] = $content_type;
        }

        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

        // Recherche avec FULLTEXT si disponible, sinon LIKE
        if ($this->hasFulltextIndex()) {
            $sql = "SELECT d.*, s.domain, sp.name as project_name,
                    MATCH(d.title, d.description, d.content_text) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance_score
                    FROM documents d
                    JOIN sites s ON d.site_id = s.id
                    JOIN search_projects sp ON d.project_id = sp.id
                    $where_clause
                    AND MATCH(d.title, d.description, d.content_text) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance_score DESC, d.pagerank_score DESC
                    LIMIT ? OFFSET ?";

            $params = array_merge([$query], $params, [$query], [RESULTS_PER_PAGE, $offset]);
        } else {
            // Fallback pour recherche basique
            $like_clause = "(d.title LIKE ? OR d.description LIKE ? OR d.content_text LIKE ?)";
            $where_clause = $where_clause ? "$where_clause AND $like_clause" : "WHERE $like_clause";

            $sql = "SELECT d.*, s.domain, sp.name as project_name, 1 as relevance_score
                    FROM documents d
                    JOIN sites s ON d.site_id = s.id
                    JOIN search_projects sp ON d.project_id = sp.id
                    $where_clause
                    ORDER BY d.indexed_at DESC
                    LIMIT ? OFFSET ?";

            $like_param = '%' . $query . '%';
            $params = array_merge($params, [$like_param, $like_param, $like_param], [RESULTS_PER_PAGE, $offset]);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function hasFulltextIndex() {
        try {
            $sql = "SHOW INDEX FROM documents WHERE Key_name = 'idx_content'";
            $result = $this->db->query($sql)->fetch();
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    // Statistiques
    public function getStats($project_id = null) {
        $where = $project_id ? "WHERE project_id = $project_id" : "";

        $stats = [];

        // Total documents
        $sql = "SELECT COUNT(*) as total FROM documents $where";
        $stats['total_documents'] = $this->db->query($sql)->fetch()['total'];

        // Par type
        $sql = "SELECT content_type, COUNT(*) as count FROM documents $where GROUP BY content_type";
        $types = $this->db->query($sql)->fetchAll();
        $stats['by_type'] = [];
        foreach ($types as $type) {
            $stats['by_type'][$type['content_type']] = $type['count'];
        }

        return $stats;
    }
}

// Fonctions utilitaires
function generateUrlHash($url) {
    return hash('sha256', $url);
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    $units = [
        31536000 => 'an',
        2592000 => 'mois',
        604800 => 'semaine',
        86400 => 'jour',
        3600 => 'heure',
        60 => 'minute',
        1 => 'seconde'
    ];

    foreach ($units as $unit => $val) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return ($val == 'mois' ? 'il y a ' : 'il y a ') . $numberOfUnits . ' ' . $val . ($numberOfUnits > 1 && $val !== 'mois' ? 's' : '');
    }
    return 'à l\'instant';
}
