<?php
require_once dirname(__DIR__) . '/config/database.php';

class AdvancedSearchEngine {
    private $db;
    private $stopWords;
    private $synonyms;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->initializeStopWords();
        $this->initializeSynonyms();
    }

    /**
     * Recherche avancée avec scoring intelligent
     */
    public function search($query, $options = []) {
        $defaults = [
            'project_id' => null,
            'content_type' => null,
            'language' => null,
            'site_id' => null,
            'date_from' => null,
            'date_to' => null,
            'sort' => 'relevance',
            'page' => 1,
            'per_page' => 20,
            'exact_phrase' => false,
            'include_synonyms' => true,
            'boost_title' => 2.0,
            'boost_description' => 1.5,
            'min_score' => 0.1
        ];

        $options = array_merge($defaults, $options);

        // Analyser la requête
        $queryAnalysis = $this->analyzeQuery($query, $options);

        // Construire la recherche
        $searchResults = $this->executeSearch($queryAnalysis, $options);

        // Enrichir les résultats
        $enrichedResults = $this->enrichResults($searchResults, $queryAnalysis);

        return [
            'query' => $query,
            'total_results' => $enrichedResults['total'],
            'results' => $enrichedResults['items'],
            'facets' => $this->getFacets($queryAnalysis, $options),
            'suggestions' => $this->getSuggestions($query),
            'search_time' => $enrichedResults['search_time'],
            'analysis' => $queryAnalysis
        ];
    }

    /**
     * Analyse la requête utilisateur
     */
    private function analyzeQuery($query, $options) {
        $start_time = microtime(true);

        $analysis = [
            'original_query' => $query,
            'cleaned_query' => $this->cleanQuery($query),
            'terms' => [],
            'phrases' => [],
            'operators' => [],
            'filters' => [],
            'intent' => 'search',
            'language' => 'fr'
        ];

        // Détecter les phrases exactes
        if (preg_match_all('/"([^"]+)"/', $query, $matches)) {
            $analysis['phrases'] = $matches[1];
            $query = preg_replace('/"[^"]+"/', '', $query);
        }

        // Extraire les termes
        $terms = $this->extractTerms($analysis['cleaned_query']);
        $analysis['terms'] = $this->expandTerms($terms, $options['include_synonyms']);

        // Détecter l'intention
        $analysis['intent'] = $this->detectIntent($query);

        $analysis['processing_time'] = microtime(true) - $start_time;

        return $analysis;
    }

    /**
     * Nettoie la requête
     */
    private function cleanQuery($query) {
        $query = mb_strtolower($query, 'UTF-8');
        $query = preg_replace('/[^\p{L}\p{N}\s\-"]/u', ' ', $query);
        $query = preg_replace('/\s+/', ' ', trim($query));
        return $query;
    }

    /**
     * Extrait les termes de la requête
     */
    private function extractTerms($query) {
        $words = explode(' ', $query);
        $terms = [];

        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 2 && !$this->isStopWord($word)) {
                $terms[] = [
                    'term' => $word,
                    'normalized' => $this->normalizeWord($word),
                    'weight' => 1.0,
                    'type' => 'term'
                ];
            }
        }

        return $terms;
    }

    /**
     * Étend les termes avec synonymes
     */
    private function expandTerms($terms, $includeSynonyms) {
        if (!$includeSynonyms) {
            return $terms;
        }

        $expandedTerms = $terms;

        foreach ($terms as $term) {
            $synonyms = $this->getSynonymsForTerm($term['term']);
            foreach ($synonyms as $synonym) {
                $expandedTerms[] = [
                    'term' => $synonym,
                    'normalized' => $this->normalizeWord($synonym),
                    'weight' => 0.7,
                    'type' => 'synonym',
                    'original' => $term['term']
                ];
            }
        }

        return $expandedTerms;
    }

    /**
     * Détecte l'intention de recherche
     */
    private function detectIntent($query) {
        $questionWords = ['qui', 'que', 'quoi', 'où', 'quand', 'comment', 'pourquoi', 'combien'];
        $definitionWords = ['définition', 'qu\'est-ce que', 'c\'est quoi'];

        $queryLower = mb_strtolower($query, 'UTF-8');

        foreach ($questionWords as $word) {
            if (strpos($queryLower, $word) !== false) {
                return 'question';
            }
        }

        foreach ($definitionWords as $word) {
            if (strpos($queryLower, $word) !== false) {
                return 'definition';
            }
        }

        return 'search';
    }

    /**
     * Exécute la recherche
     */
    private function executeSearch($analysis, $options) {
        $start_time = microtime(true);

        if (empty($analysis['terms']) && empty($analysis['phrases'])) {
            return ['items' => [], 'total' => 0, 'search_time' => 0];
        }

        // Version simplifiée mais efficace de la recherche
        $sql = $this->buildSimpleSearchQuery($analysis, $options);

        try {
            $stmt = $this->db->prepare($sql['query']);
            $stmt->execute($sql['params']);
            $results = $stmt->fetchAll();

            // Compter le total avec une requête séparée
            $countSql = $this->buildCountQuery($analysis, $options);
            $countStmt = $this->db->prepare($countSql['query']);
            $countStmt->execute($countSql['params']);
            $total = $countStmt->fetch()['total'];

        } catch (PDOException $e) {
            // En cas d'erreur, fallback sur une recherche basique
            error_log("Erreur recherche avancée: " . $e->getMessage());
            return $this->executeBasicSearch($analysis, $options);
        }

        $search_time = microtime(true) - $start_time;

        return [
            'items' => $results,
            'total' => $total,
            'search_time' => $search_time
        ];
    }

    /**
     * Recherche simple et robuste
     */
    private function buildSimpleSearchQuery($analysis, $options) {
        $where = [];
        $params = [];

        // Construire les conditions de recherche
        $searchConditions = [];

        // Recherche par termes
        if (!empty($analysis['terms'])) {
            foreach ($analysis['terms'] as $termData) {
                $term = $termData['term'];
                $searchConditions[] = "(d.title LIKE ? OR d.description LIKE ? OR d.content_text LIKE ?)";
                $likeTerm = "%$term%";
                $params[] = $likeTerm;
                $params[] = $likeTerm;
                $params[] = $likeTerm;
            }
        }

        // Recherche par phrases exactes
        if (!empty($analysis['phrases'])) {
            foreach ($analysis['phrases'] as $phrase) {
                $searchConditions[] = "(d.title LIKE ? OR d.description LIKE ? OR d.content_text LIKE ?)";
                $likePhrase = "%$phrase%";
                $params[] = $likePhrase;
                $params[] = $likePhrase;
                $params[] = $likePhrase;
            }
        }

        if (!empty($searchConditions)) {
            $where[] = "(" . implode(' OR ', $searchConditions) . ")";
        }

        // Filtres
        if ($options['project_id']) {
            $where[] = "d.project_id = ?";
            $params[] = $options['project_id'];
        }

        if ($options['content_type']) {
            $where[] = "d.content_type = ?";
            $params[] = $options['content_type'];
        }

        if ($options['site_id']) {
            $where[] = "d.site_id = ?";
            $params[] = $options['site_id'];
        }

        if ($options['language']) {
            $where[] = "d.language = ?";
            $params[] = $options['language'];
        }

        if ($options['date_from']) {
            $where[] = "d.indexed_at >= ?";
            $params[] = $options['date_from'];
        }

        if ($options['date_to']) {
            $where[] = "d.indexed_at <= ?";
            $params[] = $options['date_to'];
        }

        // Calcul simple du score de pertinence
        $scoreCalc = $this->buildScoreCalculation($analysis);

        // Construire la requête
        $query = "SELECT d.*, s.domain, sp.name as project_name, $scoreCalc as relevance_score
                  FROM documents d
                  JOIN sites s ON d.site_id = s.id
                  JOIN search_projects sp ON d.project_id = sp.id";

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        // Tri
        $orderBy = $this->getOrderBy($options['sort']);
        $query .= " ORDER BY $orderBy";

        // Pagination
        $offset = ($options['page'] - 1) * $options['per_page'];
        $query .= " LIMIT {$options['per_page']} OFFSET $offset";

        return [
            'query' => $query,
            'params' => $params
        ];
    }

    /**
     * Calcul de score simple
     */
    private function buildScoreCalculation($analysis) {
        $conditions = [];

        if (!empty($analysis['terms'])) {
            foreach ($analysis['terms'] as $termData) {
                $term = $termData['term'];
                $weight = $termData['weight'];

                // Score pour titre (poids x3)
                $conditions[] = "CASE WHEN d.title LIKE '%$term%' THEN 3.0 * $weight ELSE 0 END";
                // Score pour description (poids x2)
                $conditions[] = "CASE WHEN d.description LIKE '%$term%' THEN 2.0 * $weight ELSE 0 END";
                // Score pour contenu (poids x1)
                $conditions[] = "CASE WHEN d.content_text LIKE '%$term%' THEN 1.0 * $weight ELSE 0 END";
            }
        }

        if (!empty($analysis['phrases'])) {
            foreach ($analysis['phrases'] as $phrase) {
                // Boost important pour les phrases exactes
                $conditions[] = "CASE WHEN (d.title LIKE '%$phrase%' OR d.description LIKE '%$phrase%') THEN 5.0 ELSE 0 END";
            }
        }

        if (empty($conditions)) {
            return "(d.pagerank_score * 0.5 + d.quality_score * 0.3)";
        }

        return "(" . implode(' + ', $conditions) . " + d.pagerank_score * 0.5 + d.quality_score * 0.3)";
    }

    /**
     * Recherche basique de fallback
     */
    private function executeBasicSearch($analysis, $options) {
        $start_time = microtime(true);

        $searchTerms = [];
        foreach ($analysis['terms'] as $term) {
            $searchTerms[] = $term['term'];
        }
        foreach ($analysis['phrases'] as $phrase) {
            $searchTerms[] = $phrase;
        }

        if (empty($searchTerms)) {
            return ['items' => [], 'total' => 0, 'search_time' => 0];
        }

        $searchTerm = implode(' ', $searchTerms);
        $likeTerm = "%$searchTerm%";

        $where = ["(d.title LIKE ? OR d.description LIKE ? OR d.content_text LIKE ?)"];
        $params = [$likeTerm, $likeTerm, $likeTerm];

        // Ajouter les filtres
        if ($options['project_id']) {
            $where[] = "d.project_id = ?";
            $params[] = $options['project_id'];
        }

        if ($options['content_type']) {
            $where[] = "d.content_type = ?";
            $params[] = $options['content_type'];
        }

        $query = "SELECT d.*, s.domain, sp.name as project_name, 1.0 as relevance_score
                  FROM documents d
                  JOIN sites s ON d.site_id = s.id
                  JOIN search_projects sp ON d.project_id = sp.id
                  WHERE " . implode(" AND ", $where) . "
                  ORDER BY d.indexed_at DESC
                  LIMIT {$options['per_page']} OFFSET " . (($options['page'] - 1) * $options['per_page']);

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Compter le total
        $countQuery = "SELECT COUNT(*) as total FROM documents d WHERE " . $where[0];
        $countStmt = $this->db->prepare($countQuery);
        $countStmt->execute(array_slice($params, 0, 3));
        $total = $countStmt->fetch()['total'];

        return [
            'items' => $results,
            'total' => $total,
            'search_time' => microtime(true) - $start_time
        ];
    }

    /**
     * Construit la requête de comptage
     */
    private function buildCountQuery($analysis, $options) {
        $where = [];
        $params = [];

        // Conditions de recherche simplifiées
        $searchConditions = [];

        if (!empty($analysis['terms'])) {
            foreach ($analysis['terms'] as $termData) {
                $term = $termData['term'];
                $searchConditions[] = "(d.title LIKE ? OR d.description LIKE ? OR d.content_text LIKE ?)";
                $likeTerm = "%$term%";
                $params[] = $likeTerm;
                $params[] = $likeTerm;
                $params[] = $likeTerm;
            }
        }

        if (!empty($analysis['phrases'])) {
            foreach ($analysis['phrases'] as $phrase) {
                $searchConditions[] = "(d.title LIKE ? OR d.description LIKE ? OR d.content_text LIKE ?)";
                $likePhrase = "%$phrase%";
                $params[] = $likePhrase;
                $params[] = $likePhrase;
                $params[] = $likePhrase;
            }
        }

        if (!empty($searchConditions)) {
            $where[] = "(" . implode(' OR ', $searchConditions) . ")";
        }

        // Appliquer les mêmes filtres
        if ($options['project_id']) {
            $where[] = "d.project_id = ?";
            $params[] = $options['project_id'];
        }

        if ($options['content_type']) {
            $where[] = "d.content_type = ?";
            $params[] = $options['content_type'];
        }

        if ($options['site_id']) {
            $where[] = "d.site_id = ?";
            $params[] = $options['site_id'];
        }

        $query = "SELECT COUNT(*) as total FROM documents d";

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        return [
            'query' => $query,
            'params' => $params
        ];
    }

    /**
     * Détermine l'ordre de tri
     */
    private function getOrderBy($sort) {
        switch ($sort) {
            case 'date':
                return 'd.indexed_at DESC';
            case 'title':
                return 'd.title ASC';
            case 'pagerank':
                return 'd.pagerank_score DESC, d.quality_score DESC';
            case 'relevance':
            default:
                return 'relevance_score DESC, d.indexed_at DESC';
        }
    }

    /**
     * Enrichit les résultats
     */
    private function enrichResults($searchResults, $analysis) {
        $items = [];

        foreach ($searchResults['items'] as $result) {
            $enriched = $result;

            // Générer des extraits avec mise en évidence
            $enriched['highlighted_title'] = $this->highlightTerms($result['title'], $analysis['terms']);
            $enriched['highlighted_description'] = $this->highlightTerms($result['description'], $analysis['terms']);
            $enriched['snippet'] = $this->generateSnippet($result['content_text'], $analysis['terms']);

            // Informations sur le score
            $enriched['score_breakdown'] = [
                'relevance' => round($result['relevance_score'] ?? 0, 3),
                'pagerank' => round($result['pagerank_score'] ?? 0, 3),
                'quality' => round($result['quality_score'] ?? 0, 3)
            ];

            // Métadonnées enrichies
            $enriched['reading_time'] = $this->estimateReadingTime($result['content_text']);
            $enriched['content_quality'] = $this->assessContentQuality($result);

            $items[] = $enriched;
        }

        return [
            'items' => $items,
            'total' => $searchResults['total'],
            'search_time' => $searchResults['search_time']
        ];
    }

    /**
     * Met en évidence les termes de recherche
     */
    private function highlightTerms($text, $terms) {
        if (!$text || empty($terms)) {
            return $text;
        }

        $highlighted = $text;

        foreach ($terms as $termData) {
            $term = $termData['term'];
            if (strlen($term) >= 2) {
                $pattern = '/\b' . preg_quote($term, '/') . '\b/iu';
                $highlighted = preg_replace($pattern, '<mark>$0</mark>', $highlighted);
            }
        }

        return $highlighted;
    }

    /**
     * Génère un extrait pertinent
     */
    private function generateSnippet($content, $terms, $maxLength = 300) {
        if (!$content || empty($terms)) {
            return substr($content, 0, $maxLength) . '...';
        }

        $content = strip_tags($content);

        // Trouver la première occurrence d'un terme
        $bestPosition = 0;

        foreach ($terms as $termData) {
            $term = $termData['term'];
            $pos = mb_stripos($content, $term, 0, 'UTF-8');
            if ($pos !== false && ($bestPosition === 0 || $pos < $bestPosition)) {
                $bestPosition = $pos;
            }
        }

        // Créer l'extrait autour de ce terme
        $start = max(0, $bestPosition - 100);
        $snippet = mb_substr($content, $start, $maxLength, 'UTF-8');

        // Nettoyer les débuts/fins de mots coupés
        if ($start > 0) {
            $firstSpace = mb_strpos($snippet, ' ', 0, 'UTF-8');
            if ($firstSpace !== false) {
                $snippet = mb_substr($snippet, $firstSpace + 1, null, 'UTF-8');
            }
            $snippet = '...' . $snippet;
        }

        if (mb_strlen($content, 'UTF-8') > $start + $maxLength) {
            $lastSpace = mb_strrpos($snippet, ' ', 0, 'UTF-8');
            if ($lastSpace !== false) {
                $snippet = mb_substr($snippet, 0, $lastSpace, 'UTF-8');
            }
            $snippet .= '...';
        }

        return $this->highlightTerms($snippet, $terms);
    }

    /**
     * Estime le temps de lecture
     */
    private function estimateReadingTime($content) {
        $wordCount = str_word_count(strip_tags($content));
        $readingSpeed = 200;
        $minutes = ceil($wordCount / $readingSpeed);

        return [
            'words' => $wordCount,
            'minutes' => $minutes,
            'text' => $minutes . ' min de lecture'
        ];
    }

    /**
     * Évalue la qualité du contenu
     */
    private function assessContentQuality($document) {
        $score = 0;
        $factors = [];

        if (!empty($document['title'])) {
            $titleLength = mb_strlen($document['title'], 'UTF-8');
            if ($titleLength >= 30 && $titleLength <= 60) {
                $score += 2;
                $factors[] = 'Titre bien dimensionné';
            }
        }

        if (!empty($document['description'])) {
            $score += 1;
            $factors[] = 'Description présente';
        }

        if (!empty($document['content_text'])) {
            $contentLength = mb_strlen($document['content_text'], 'UTF-8');
            if ($contentLength > 500) {
                $score += 1;
                $factors[] = 'Contenu substantiel';
            }
            if ($contentLength > 2000) {
                $score += 1;
                $factors[] = 'Contenu détaillé';
            }
        }

        return [
            'score' => min($score, 5),
            'factors' => $factors
        ];
    }

    /**
     * Obtient les facettes de recherche
     */
    private function getFacets($analysis, $options) {
        $facets = [];

        try {
            // Facettes par type de contenu
            $sql = "SELECT content_type, COUNT(*) as count 
                    FROM documents d 
                    WHERE 1=1";
            $params = [];

            if ($options['project_id']) {
                $sql .= " AND d.project_id = ?";
                $params[] = $options['project_id'];
            }

            $sql .= " GROUP BY content_type ORDER BY count DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $facets['content_types'] = $stmt->fetchAll();

            // Facettes par projet
            $sql = "SELECT sp.id, sp.name, COUNT(d.id) as count 
                    FROM search_projects sp 
                    LEFT JOIN documents d ON sp.id = d.project_id 
                    GROUP BY sp.id, sp.name 
                    ORDER BY count DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $facets['projects'] = $stmt->fetchAll();

            // Facettes par site
            $sql = "SELECT s.id, s.domain, COUNT(d.id) as count 
                    FROM sites s 
                    LEFT JOIN documents d ON s.id = d.site_id";

            if ($options['project_id']) {
                $sql .= " WHERE s.project_id = ?";
                $params = [$options['project_id']];
            } else {
                $params = [];
            }

            $sql .= " GROUP BY s.id, s.domain ORDER BY count DESC LIMIT 10";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $facets['sites'] = $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Erreur facettes: " . $e->getMessage());
            $facets = ['content_types' => [], 'projects' => [], 'sites' => []];
        }

        return $facets;
    }

    /**
     * Génère des suggestions de recherche - VERSION CORRIGÉE
     */
    private function getSuggestions($query) {
        if (mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }

        $suggestions = [];

        try {
            // Suggestions basées sur les termes populaires
            $sql = "SELECT t.term, t.frequency 
                    FROM terms t 
                    WHERE t.term LIKE ? 
                    ORDER BY t.frequency DESC 
                    LIMIT 5";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(["%$query%"]);
            $termSuggestions = $stmt->fetchAll();

            foreach ($termSuggestions as $term) {
                $suggestions[] = [
                    'text' => $term['term'],
                    'type' => 'term',
                    'frequency' => $term['frequency']
                ];
            }

            // Suggestions basées sur les titres de documents - CORRIGÉ
            $sql = "SELECT d.title, d.pagerank_score
                    FROM documents d 
                    WHERE d.title LIKE ? 
                    ORDER BY d.pagerank_score DESC, d.indexed_at DESC
                    LIMIT 3";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(["%$query%"]);
            $titleSuggestions = $stmt->fetchAll();

            foreach ($titleSuggestions as $title) {
                $suggestions[] = [
                    'text' => $title['title'],
                    'type' => 'document',
                    'frequency' => 0
                ];
            }

        } catch (PDOException $e) {
            error_log("Erreur suggestions: " . $e->getMessage());
        }

        return array_slice($suggestions, 0, 8);
    }

    /**
     * Méthodes utilitaires
     */
    private function initializeStopWords() {
        $this->stopWords = [
            'le', 'de', 'et', 'à', 'un', 'il', 'être', 'en', 'avoir', 'que', 'pour',
            'dans', 'ce', 'son', 'une', 'sur', 'avec', 'ne', 'se', 'pas', 'tout', 'plus',
            'par', 'grand', 'comme', 'depuis', 'du', 'la', 'les', 'des', 'nous', 'vous',
            'ils', 'elle', 'elles', 'je', 'tu', 'me', 'te'
        ];
    }

    private function initializeSynonyms() {
        $this->synonyms = [
            'recherche' => ['rechercher', 'chercher', 'trouver', 'quête'],
            'ordinateur' => ['pc', 'computer', 'machine'],
            'internet' => ['web', 'net', 'toile'],
            'téléphone' => ['mobile', 'portable', 'smartphone'],
            'voiture' => ['auto', 'automobile', 'véhicule'],
            'maison' => ['domicile', 'habitation', 'logement'],
            'travail' => ['boulot', 'emploi', 'job', 'métier']
        ];
    }

    private function isStopWord($word) {
        return in_array(mb_strtolower($word, 'UTF-8'), $this->stopWords);
    }

    private function normalizeWord($word) {
        $word = mb_strtolower($word, 'UTF-8');
        $word = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $word);
        $word = preg_replace('/[^a-z0-9]/', '', $word);
        return $word;
    }

    private function getSynonymsForTerm($word) {
        $word = mb_strtolower($word, 'UTF-8');

        foreach ($this->synonyms as $key => $synonymList) {
            if ($key === $word || in_array($word, $synonymList)) {
                $allSynonyms = array_merge([$key], $synonymList);
                return array_diff($allSynonyms, [$word]);
            }
        }

        return [];
    }
}
