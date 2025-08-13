<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

class WebCrawler {
    private $db;
    private $user_agent;
    private $max_redirects = 3;
    private $timeout = 10;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->user_agent = USER_AGENT;
    }

    /**
     * Lance le crawling d'un site spécifique
     */
    public function crawlSite($site_id, $max_pages = 50) {
        $site = $this->getSiteInfo($site_id);
        if (!$site) {
            return ['error' => 'Site non trouvé'];
        }

        $stats = [
            'started_at' => date('Y-m-d H:i:s'),
            'site_id' => $site_id,
            'project_id' => $site['project_id'],
            'urls_discovered' => 0,
            'urls_successful' => 0,
            'urls_failed' => 0,
            'errors' => []
        ];

        // Marquer le site comme en cours de crawling
        $this->updateSiteStatus($site_id, 'processing');

        try {
            // Ajouter l'URL de base à la queue avec le site_id
            $this->addToQueue($site['project_id'], $site['base_url'], 0, null, 1, $site_id);

            // Traiter la queue
            $processed = 0;
            while ($processed < $max_pages) {
                $url_data = $this->getNextFromQueue($site['project_id']);
                if (!$url_data) {
                    break;
                }

                // Assurer que site_id est présent
                if (!isset($url_data['site_id']) || !$url_data['site_id']) {
                    $url_data['site_id'] = $site_id;
                }

                $result = $this->crawlUrl($url_data);

                if ($result['success']) {
                    $stats['urls_successful']++;

                    // Découvrir de nouveaux liens
                    if (!empty($result['links']) && $url_data['depth'] < MAX_CRAWL_DEPTH) {
                        foreach ($result['links'] as $link) {
                            if ($this->shouldCrawlUrl($link, $site)) {
                                $this->addToQueue(
                                    $site['project_id'],
                                    $link,
                                    $url_data['depth'] + 1,
                                    $url_data['url'],
                                    5,
                                    $site_id
                                );
                                $stats['urls_discovered']++;
                            }
                        }
                    }
                } else {
                    $stats['urls_failed']++;
                    if (!empty($result['error'])) {
                        $stats['errors'][] = $result['error'];
                    }
                }

                $processed++;

                // Délai entre les requêtes
                if (CRAWL_DELAY > 0) {
                    sleep(CRAWL_DELAY);
                }
            }

            $this->updateSiteStatus($site_id, 'active');
            $this->updateSiteLastCrawled($site_id);

        } catch (Exception $e) {
            $this->updateSiteStatus($site_id, 'error');
            $stats['errors'][] = $e->getMessage();
        }

        $stats['completed_at'] = date('Y-m-d H:i:s');
        $this->saveCrawlHistory($stats);

        return $stats;
    }

    /**
     * Crawle une URL spécifique
     */
    private function crawlUrl($url_data) {
        $url = $url_data['url'];
        $project_id = $url_data['project_id'];
        $site_id = $url_data['site_id'];

        // Marquer comme en cours de traitement
        $this->updateQueueStatus($url_data['id'], 'processing');

        try {
            // Vérifier si l'URL existe déjà
            if ($this->urlExists($url)) {
                $this->updateQueueStatus($url_data['id'], 'completed');
                return ['success' => true, 'message' => 'URL déjà indexée', 'links' => []];
            }

            // Télécharger le contenu
            $content = $this->fetchUrl($url);
            if (!$content['success']) {
                $this->updateQueueStatus($url_data['id'], 'failed', $content['error']);
                return $content;
            }

            // Analyser le contenu
            $parsed = $this->parseContent($content['data'], $url);

            // Sauvegarder en base
            $document_id = $this->saveDocument($project_id, $site_id, $url, $parsed);

            if ($document_id) {
                // Indexer les termes
                $this->indexTerms($document_id, $parsed, $project_id);

                $this->updateQueueStatus($url_data['id'], 'completed');
                return [
                    'success' => true,
                    'document_id' => $document_id,
                    'links' => $parsed['links'] ?? []
                ];
            } else {
                $this->updateQueueStatus($url_data['id'], 'failed', 'Erreur sauvegarde');
                return ['success' => false, 'error' => 'Erreur lors de la sauvegarde'];
            }

        } catch (Exception $e) {
            $this->updateQueueStatus($url_data['id'], 'failed', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Télécharge le contenu d'une URL
     */
    private function fetchUrl($url) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->max_redirects,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->user_agent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if (curl_error($ch)) {
            curl_close($ch);
            return ['success' => false, 'error' => curl_error($ch)];
        }

        curl_close($ch);

        if ($http_code >= 400) {
            return ['success' => false, 'error' => "HTTP $http_code"];
        }

        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        return [
            'success' => true,
            'data' => [
                'content' => $body,
                'content_type' => $content_type,
                'http_code' => $http_code,
                'url' => $url,
                'size' => strlen($body)
            ]
        ];
    }

    /**
     * Parse le contenu selon le type
     */
    private function parseContent($data, $url) {
        $content_type = strtolower($data['content_type']);
        $parsed = [
            'url' => $url,
            'content_type' => 'webpage',
            'title' => '',
            'description' => '',
            'content_text' => '',
            'language' => 'fr',
            'file_size' => $data['size'],
            'links' => [],
            'images' => [],
            'metadata' => []
        ];

        if (strpos($content_type, 'text/html') !== false) {
            return $this->parseHTML($data['content'], $parsed);
        } elseif (strpos($content_type, 'image/') !== false) {
            $parsed['content_type'] = 'image';
            return $this->parseImage($data, $parsed);
        } elseif (strpos($content_type, 'video/') !== false) {
            $parsed['content_type'] = 'video';
            return $this->parseVideo($data, $parsed);
        }

        return $parsed;
    }

    /**
     * Parse le contenu HTML
     */
    private function parseHTML($html, $parsed) {
        // Nettoyer le HTML pour éviter les erreurs d'encodage
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Titre
        $title_nodes = $xpath->query('//title');
        if ($title_nodes->length > 0) {
            $parsed['title'] = trim($title_nodes->item(0)->textContent);
        }

        // Meta description
        $meta_desc = $xpath->query('//meta[@name="description"]/@content');
        if ($meta_desc->length > 0) {
            $parsed['description'] = trim($meta_desc->item(0)->value);
        }

        // Langue
        $lang = $xpath->query('//html/@lang');
        if ($lang->length > 0) {
            $parsed['language'] = substr($lang->item(0)->value, 0, 2);
        }

        // Contenu textuel (sans scripts et styles)
        $scripts = $xpath->query('//script | //style | //nav | //header | //footer');
        foreach ($scripts as $script) {
            if ($script->parentNode) {
                $script->parentNode->removeChild($script);
            }
        }

        $body = $xpath->query('//body');
        if ($body->length > 0) {
            $text_content = $body->item(0)->textContent;
            $parsed['content_text'] = $this->cleanText($text_content);
        }

        // Liens (limiter pour éviter la surcharge)
        $links = $xpath->query('//a[@href]');
        $link_count = 0;
        foreach ($links as $link) {
            if ($link_count >= 50) break; // Limiter à 50 liens

            $href = $link->getAttribute('href');
            $absolute_url = $this->makeAbsoluteUrl($href, $parsed['url']);
            if ($absolute_url && filter_var($absolute_url, FILTER_VALIDATE_URL)) {
                $parsed['links'][] = $absolute_url;
                $link_count++;
            }
        }

        return $parsed;
    }

    /**
     * Nettoie le texte extrait
     */
    private function cleanText($text) {
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return substr($text, 0, 50000); // 50KB max
    }

    /**
     * Parse une image
     */
    private function parseImage($data, $parsed) {
        $url_parts = pathinfo($parsed['url']);
        $parsed['title'] = $url_parts['filename'] ?? '';
        $parsed['metadata']['format'] = $url_parts['extension'] ?? '';
        return $parsed;
    }

    /**
     * Parse une vidéo
     */
    private function parseVideo($data, $parsed) {
        $url_parts = pathinfo($parsed['url']);
        $parsed['title'] = $url_parts['filename'] ?? '';
        $parsed['metadata']['format'] = $url_parts['extension'] ?? '';
        return $parsed;
    }

    /**
     * Transforme une URL relative en absolue
     */
    private function makeAbsoluteUrl($url, $base) {
        if (empty($url)) return null;

        if (parse_url($url, PHP_URL_SCHEME)) {
            return $url;
        }

        $base_parts = parse_url($base);
        if (!$base_parts) return null;

        if (substr($url, 0, 2) === '//') {
            return $base_parts['scheme'] . ':' . $url;
        }

        if (substr($url, 0, 1) === '/') {
            return $base_parts['scheme'] . '://' . $base_parts['host'] . $url;
        }

        $base_dir = dirname($base_parts['path'] ?? '/');
        if ($base_dir === '.') $base_dir = '/';

        return $base_parts['scheme'] . '://' . $base_parts['host'] . $base_dir . '/' . $url;
    }

    /**
     * Vérifie si une URL doit être crawlée
     */
    private function shouldCrawlUrl($url, $site) {
        $url_parts = parse_url($url);
        if (!$url_parts || !isset($url_parts['host'])) {
            return false;
        }

        $site_parts = parse_url($site['base_url']);
        if (!$site_parts || !isset($site_parts['host'])) {
            return false;
        }

        // Même domaine seulement
        if ($url_parts['host'] !== $site_parts['host']) {
            return false;
        }

        // Éviter certaines extensions
        $avoid_extensions = ['pdf', 'doc', 'docx', 'css', 'js', 'json', 'xml'];
        $path = $url_parts['path'] ?? '';
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array(strtolower($extension), $avoid_extensions)) {
            return false;
        }

        if (strlen($url) > 500) {
            return false;
        }

        return true;
    }

    /**
     * Sauvegarde un document en base
     */
    private function saveDocument($project_id, $site_id, $url, $parsed) {
        $url_hash = generateUrlHash($url);

        $sql = "INSERT INTO documents (
            project_id, site_id, url, url_hash, content_type, title, description, 
            content_text, language, file_size, last_modified, pagerank_score, quality_score
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0)";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            $project_id,
            $site_id,
            $url,
            $url_hash,
            $parsed['content_type'],
            substr($parsed['title'], 0, 500),
            substr($parsed['description'], 0, 1000),
            $parsed['content_text'],
            $parsed['language'],
            $parsed['file_size']
        ]);

        if ($success) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Indexe les termes d'un document (version simplifiée)
     */
    private function indexTerms($document_id, $parsed, $project_id) {
        // Version ultra-simplifiée pour éviter les erreurs
        try {
            $text = $parsed['title'] . ' ' . $parsed['description'];
            $words = explode(' ', strtolower($text));

            foreach (array_slice($words, 0, 20) as $i => $word) { // Max 20 mots
                $word = preg_replace('/[^a-z0-9]/', '', $word);
                if (strlen($word) >= 3) {
                    $term_id = $this->getOrCreateTerm($word);
                    if ($term_id) {
                        $this->saveInvertedIndex($term_id, $document_id, $project_id, 1.0, [$i], 'title');
                    }
                }
            }
        } catch (Exception $e) {
            // Ignorer les erreurs d'indexation
        }
    }

    private function getOrCreateTerm($word) {
        try {
            $sql = "SELECT id FROM terms WHERE term = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$word]);
            $result = $stmt->fetch();

            if ($result) {
                return $result['id'];
            } else {
                $sql = "INSERT INTO terms (term, term_normalized, frequency) VALUES (?, ?, 1)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$word, $word]);
                return $this->db->lastInsertId();
            }
        } catch (Exception $e) {
            return null;
        }
    }

    private function saveInvertedIndex($term_id, $document_id, $project_id, $tf_score, $positions, $field_type) {
        try {
            $sql = "INSERT INTO inverted_index (term_id, document_id, project_id, tf_score, positions, field_type) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE tf_score = VALUES(tf_score)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $term_id,
                $document_id,
                $project_id,
                $tf_score,
                json_encode($positions),
                $field_type
            ]);
        } catch (Exception $e) {
            // Ignorer les erreurs
        }
    }

    // Méthodes utilitaires

    private function getSiteInfo($site_id) {
        $sql = "SELECT s.*, sp.base_domains FROM sites s 
                JOIN search_projects sp ON s.project_id = sp.id 
                WHERE s.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$site_id]);
        return $stmt->fetch();
    }

    private function addToQueue($project_id, $url, $depth, $parent_url, $priority = 5, $site_id = null) {
        $sql = "INSERT IGNORE INTO crawl_queue (project_id, url, depth, parent_url, priority, site_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$project_id, $url, $depth, $parent_url, $priority, $site_id]);
    }

    private function getNextFromQueue($project_id) {
        $sql = "SELECT * FROM crawl_queue 
                WHERE project_id = ? AND status = 'pending' 
                ORDER BY priority DESC, id ASC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$project_id]);
        return $stmt->fetch();
    }

    private function updateQueueStatus($queue_id, $status, $error = null) {
        $valid_statuses = ['pending', 'processing', 'completed', 'failed'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'failed';
        }

        $sql = "UPDATE crawl_queue SET status = ?, last_error = ?, processed_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $error, $queue_id]);
    }

    private function updateSiteStatus($site_id, $status) {
        $valid_statuses = ['active', 'blocked', 'error', 'processing'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'error';
        }

        $sql = "UPDATE sites SET status = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $site_id]);
    }

    private function updateSiteLastCrawled($site_id) {
        $sql = "UPDATE sites SET last_crawled = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$site_id]);
    }

    private function urlExists($url) {
        $url_hash = generateUrlHash($url);
        $sql = "SELECT id FROM documents WHERE url_hash = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$url_hash]);
        return $stmt->fetch() !== false;
    }

    private function saveCrawlHistory($stats) {
        $sql = "INSERT INTO crawl_history (project_id, started_at, completed_at, urls_discovered, urls_successful, urls_failed, errors) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $stats['project_id'],
            $stats['started_at'],
            $stats['completed_at'],
            $stats['urls_discovered'],
            $stats['urls_successful'],
            $stats['urls_failed'],
            json_encode($stats['errors'])
        ]);
    }
}
