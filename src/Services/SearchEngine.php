<?php

namespace LocalSearch\Services;

use LocalSearch\Models\Document;
use LocalSearch\Models\Project;
use LocalSearch\Models\Site;
use LocalSearch\Models\Term;
use LocalSearch\Config\Configuration;

/**
 * Advanced search engine service
 * Provides comprehensive search functionality with relevance scoring
 */
class SearchEngine
{
    private $documentModel;
    private $projectModel;
    private $siteModel;
    private $termModel;

    public function __construct()
    {
        $this->documentModel = new Document();
        $this->projectModel = new Project();
        $this->siteModel = new Site();
        $this->termModel = new Term();
    }

    /**
     * Perform advanced search with analysis and relevance scoring
     */
    public function search(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        // Default options
        $defaults = [
            'project_id' => null,
            'content_type' => null,
            'site_id' => null,
            'language' => null,
            'date_from' => null,
            'date_to' => null,
            'sort' => 'relevance',
            'page' => 1,
            'per_page' => Configuration::get('RESULTS_PER_PAGE', 20),
            'include_synonyms' => true,
            'exact_phrase' => false
        ];

        $options = array_merge($defaults, $options);

        // Analyze the query
        $queryAnalysis = $this->analyzeQuery($query, $options);

        // Execute search
        $searchResults = $this->executeSearch($queryAnalysis, $options);

        // Enrich results with additional data
        $enrichedResults = $this->enrichResults($searchResults, $queryAnalysis);

        $searchTime = microtime(true) - $startTime;

        return [
            'query' => $query,
            'total_results' => $enrichedResults['total'],
            'results' => $enrichedResults['results'],
            'facets' => $this->getFacets($queryAnalysis, $options),
            'suggestions' => $this->getSuggestions($query, $options),
            'search_time' => $searchTime,
            'analysis' => $queryAnalysis
        ];
    }

    /**
     * Analyze search query to understand intent and extract components
     */
    private function analyzeQuery(string $query, array $options): array
    {
        $startTime = microtime(true);

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

        // Detect exact phrases
        if (preg_match_all('/\"([^\"]+)\"/', $query, $matches)) {
            $analysis['phrases'] = $matches[1];
            $query = preg_replace('/\"[^\"]+\"/', '', $query);
        }

        // Extract terms
        $terms = $this->extractTerms($analysis['cleaned_query']);
        $analysis['terms'] = $this->expandTerms($terms, $options['include_synonyms']);

        // Detect search intent
        $analysis['intent'] = $this->detectIntent($query);

        $analysis['processing_time'] = microtime(true) - $startTime;

        return $analysis;
    }

    /**
     * Clean and normalize query
     */
    private function cleanQuery(string $query): string
    {
        // Remove special characters except quotes and basic operators
        $query = preg_replace('/[^\\p{L}\\p{N}\\s\"\\+\\-]/u', ' ', $query);
        
        // Normalize whitespace
        $query = preg_replace('/\\s+/', ' ', trim($query));
        
        return $query;
    }

    /**
     * Extract individual terms from query
     */
    private function extractTerms(string $query): array
    {
        $words = preg_split('/\\s+/', strtolower($query));
        $terms = [];
        
        $stopWords = ['le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'est', 'sont', 'avec', 'pour'];
        
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 2 && !in_array($word, $stopWords)) {
                $terms[] = $word;
            }
        }
        
        return array_unique($terms);
    }

    /**
     * Expand terms with synonyms if enabled
     */
    private function expandTerms(array $terms, bool $includeSynonyms): array
    {
        if (!$includeSynonyms) {
            return $terms;
        }

        $expanded = $terms;
        
        // Simple synonym expansion (can be enhanced with a proper synonym database)
        $synonyms = [
            'recherche' => ['search', 'trouve', 'cherche'],
            'document' => ['fichier', 'file', 'doc'],
            'site' => ['website', 'web', 'page'],
            'information' => ['info', 'données', 'data'],
        ];

        foreach ($terms as $term) {
            if (isset($synonyms[$term])) {
                $expanded = array_merge($expanded, $synonyms[$term]);
            }
        }

        return array_unique($expanded);
    }

    /**
     * Detect search intent from query
     */
    private function detectIntent(string $query): string
    {
        $query = strtolower($query);
        
        if (preg_match('/^(qu|comment|pourquoi|quand|où)/', $query)) {
            return 'question';
        }
        
        if (preg_match('/(définition|define|what is)/', $query)) {
            return 'definition';
        }
        
        if (preg_match('/(télécharger|download|pdf|doc)/', $query)) {
            return 'download';
        }
        
        return 'search';
    }

    /**
     * Execute the actual search query
     */
    private function executeSearch(array $analysis, array $options): array
    {
        // Use term-based search for better relevance
        if (!empty($analysis['terms'])) {
            return $this->searchByTerms($analysis, $options);
        }

        // Fallback to basic search
        return $this->documentModel->search([
            'query' => $analysis['original_query'],
            'project_id' => $options['project_id'],
            'content_type' => $options['content_type'],
            'site_id' => $options['site_id'],
            'language' => $options['language'],
            'date_from' => $options['date_from'],
            'date_to' => $options['date_to'],
            'sort' => $options['sort'],
            'page' => $options['page'],
            'per_page' => $options['per_page']
        ]);
    }

    /**
     * Search using term index for better relevance
     */
    private function searchByTerms(array $analysis, array $options): array
    {
        $termResults = $this->termModel->searchTerms(
            implode(' ', $analysis['terms']),
            ['project_id' => $options['project_id'], 'limit' => 1000]
        );

        if (empty($termResults)) {
            return ['total' => 0, 'results' => [], 'page' => 1, 'per_page' => $options['per_page'], 'total_pages' => 0];
        }

        // Get document IDs with relevance scores
        $documentIds = [];
        $relevanceScores = [];
        foreach ($termResults as $result) {
            $documentIds[] = $result['document_id'];
            $relevanceScores[$result['document_id']] = $result['relevance_score'];
        }

        // Fetch document details
        $documents = $this->getDocumentsByIds($documentIds, $options);

        // Add relevance scores and sort
        foreach ($documents as &$doc) {
            $doc['relevance_score'] = $relevanceScores[$doc['id']] ?? 0;
        }

        // Sort by relevance
        usort($documents, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        // Paginate results
        $total = count($documents);
        $offset = ($options['page'] - 1) * $options['per_page'];
        $paginatedResults = array_slice($documents, $offset, $options['per_page']);

        return [
            'total' => $total,
            'results' => $paginatedResults,
            'page' => $options['page'],
            'per_page' => $options['per_page'],
            'total_pages' => ceil($total / $options['per_page'])
        ];
    }

    /**
     * Get documents by IDs with filters
     */
    private function getDocumentsByIds(array $documentIds, array $options): array
    {
        if (empty($documentIds)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($documentIds) - 1) . '?';
        $params = $documentIds;

        $where = ["d.id IN ($placeholders)"];

        // Apply filters
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

        $sql = "SELECT d.*, s.domain, sp.name as project_name 
                FROM documents d 
                JOIN sites s ON d.site_id = s.id 
                JOIN search_projects sp ON d.project_id = sp.id 
                WHERE " . implode(" AND ", $where);

        return $this->documentModel->raw($sql, $params);
    }

    /**
     * Enrich search results with additional metadata
     */
    private function enrichResults(array $searchResults, array $analysis): array
    {
        foreach ($searchResults['results'] as &$result) {
            // Highlight search terms in title and description
            $result['highlighted_title'] = $this->highlightTerms($result['title'], $analysis['terms']);
            $result['highlighted_description'] = $this->highlightTerms($result['description'], $analysis['terms']);
            
            // Add content quality indicators
            $result['content_quality'] = $this->assessContentQuality($result);
            
            // Add snippet with highlighted terms
            $result['snippet'] = $this->generateSnippet($result['content_text'], $analysis['terms']);
        }

        return $searchResults;
    }

    /**
     * Highlight search terms in text
     */
    private function highlightTerms(string $text, array $terms): string
    {
        if (empty($terms) || empty($text)) {
            return $text;
        }

        foreach ($terms as $term) {
            $pattern = '/\\b' . preg_quote($term, '/') . '\\b/i';
            $text = preg_replace($pattern, '<mark>$0</mark>', $text);
        }

        return $text;
    }

    /**
     * Assess content quality
     */
    private function assessContentQuality(array $document): array
    {
        $factors = [];
        $score = 0;

        // Title presence and length
        if (!empty($document['title'])) {
            $factors[] = 'title';
            $score += 20;
        }

        // Description presence
        if (!empty($document['description'])) {
            $factors[] = 'description';
            $score += 15;
        }

        // Content length
        if (strlen($document['content_text']) > 500) {
            $factors[] = 'content_length';
            $score += 25;
        }

        // Language detection
        if (!empty($document['language'])) {
            $factors[] = 'language';
            $score += 10;
        }

        // PageRank score
        if ($document['pagerank_score'] > 1) {
            $factors[] = 'pagerank';
            $score += 30;
        }

        return [
            'score' => min(100, $score),
            'factors' => $factors
        ];
    }

    /**
     * Generate content snippet with highlighted terms
     */
    private function generateSnippet(string $content, array $terms, int $maxLength = 300): string
    {
        if (empty($content)) {
            return '';
        }

        // Find best position to start snippet (around first term occurrence)
        $bestPosition = 0;
        foreach ($terms as $term) {
            $pos = stripos($content, $term);
            if ($pos !== false) {
                $bestPosition = max(0, $pos - 50);
                break;
            }
        }

        // Extract snippet
        $snippet = substr($content, $bestPosition, $maxLength);
        
        // Clean up and add ellipsis
        if ($bestPosition > 0) {
            $snippet = '...' . $snippet;
        }
        if (strlen($content) > $bestPosition + $maxLength) {
            $snippet .= '...';
        }

        // Highlight terms
        return $this->highlightTerms($snippet, $terms);
    }

    /**
     * Get search facets
     */
    private function getFacets(array $analysis, array $options): array
    {
        $facets = [];

        // Content type facets
        $facets['content_types'] = $this->getContentTypeFacets($options);
        
        // Site facets
        $facets['sites'] = $this->getSiteFacets($options);
        
        // Language facets
        $facets['languages'] = $this->getLanguageFacets($options);

        return $facets;
    }

    /**
     * Get content type facets
     */
    private function getContentTypeFacets(array $options): array
    {
        $where = [];
        $params = [];

        if ($options['project_id']) {
            $where[] = "project_id = ?";
            $params[] = $options['project_id'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT content_type, COUNT(*) as count 
                FROM documents {$whereClause}
                GROUP BY content_type 
                ORDER BY count DESC";

        return $this->documentModel->raw($sql, $params);
    }

    /**
     * Get site facets
     */
    private function getSiteFacets(array $options): array
    {
        $where = [];
        $params = [];

        if ($options['project_id']) {
            $where[] = "d.project_id = ?";
            $params[] = $options['project_id'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT s.domain, s.id, COUNT(d.id) as count 
                FROM sites s 
                LEFT JOIN documents d ON s.id = d.site_id 
                {$whereClause}
                GROUP BY s.id, s.domain 
                HAVING count > 0
                ORDER BY count DESC 
                LIMIT 10";

        return $this->documentModel->raw($sql, $params);
    }

    /**
     * Get language facets
     */
    private function getLanguageFacets(array $options): array
    {
        $where = [];
        $params = [];

        if ($options['project_id']) {
            $where[] = "project_id = ?";
            $params[] = $options['project_id'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT language, COUNT(*) as count 
                FROM documents {$whereClause}
                GROUP BY language 
                ORDER BY count DESC";

        return $this->documentModel->raw($sql, $params);
    }

    /**
     * Get search suggestions
     */
    private function getSuggestions(string $query, array $options): array
    {
        if (strlen($query) < 3) {
            return [];
        }

        return $this->termModel->getTermSuggestions(
            $query, 
            $options['project_id'], 
            10
        );
    }

    /**
     * Get search statistics
     */
    public function getSearchStatistics(int $projectId = null): array
    {
        return [
            'documents' => $this->documentModel->getStatistics($projectId),
            'terms' => $this->termModel->getTermStatistics($projectId)
        ];
    }
}