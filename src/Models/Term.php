<?php

namespace LocalSearch\Models;

/**
 * Term model for managing search terms and indexing
 */
class Term extends BaseModel
{
    protected $table = 'search_terms';
    protected $fillable = [
        'document_id',
        'project_id',
        'term',
        'term_hash',
        'frequency',
        'position',
        'context',
        'weight'
    ];

    protected $timestamps = false;

    /**
     * Index terms for a document
     */
    public function indexTermsForDocument(int $documentId, int $projectId, array $terms): void
    {
        // Delete existing terms for this document
        $this->deleteTermsForDocument($documentId);

        // Insert new terms
        $batch = [];
        foreach ($terms as $term => $data) {
            $batch[] = [
                'document_id' => $documentId,
                'project_id' => $projectId,
                'term' => $term,
                'term_hash' => hash('sha256', strtolower($term)),
                'frequency' => $data['frequency'] ?? 1,
                'position' => $data['position'] ?? 0,
                'context' => $data['context'] ?? '',
                'weight' => $data['weight'] ?? 1.0
            ];
        }

        if (!empty($batch)) {
            $this->bulkInsert($batch);
        }
    }

    /**
     * Delete terms for a specific document
     */
    public function deleteTermsForDocument(int $documentId): void
    {
        $sql = "DELETE FROM search_terms WHERE document_id = ?";
        $this->db->execute($sql, [$documentId]);
    }

    /**
     * Search terms with relevance scoring
     */
    public function searchTerms(string $query, array $options = []): array
    {
        $projectId = $options['project_id'] ?? null;
        $limit = $options['limit'] ?? 100;

        $terms = $this->extractSearchTerms($query);
        if (empty($terms)) {
            return [];
        }

        $where = [];
        $params = [];

        // Build term matching conditions
        $termConditions = [];
        foreach ($terms as $term) {
            $termConditions[] = "t.term LIKE ?";
            $params[] = "%{$term}%";
        }
        $where[] = "(" . implode(" OR ", $termConditions) . ")";

        // Project filter
        if ($projectId) {
            $where[] = "t.project_id = ?";
            $params[] = $projectId;
        }

        $sql = "SELECT t.document_id, 
                       SUM(t.frequency * t.weight) as relevance_score,
                       GROUP_CONCAT(DISTINCT t.term) as matched_terms
                FROM search_terms t
                WHERE " . implode(" AND ", $where) . "
                GROUP BY t.document_id
                ORDER BY relevance_score DESC
                LIMIT {$limit}";

        return $this->raw($sql, $params);
    }

    /**
     * Get term statistics
     */
    public function getTermStatistics(int $projectId = null): array
    {
        $where = $projectId ? "WHERE project_id = ?" : "";
        $params = $projectId ? [$projectId] : [];

        $stats = [];

        // Total unique terms
        $sql = "SELECT COUNT(DISTINCT term_hash) as total FROM search_terms {$where}";
        $result = $this->rawOne($sql, $params);
        $stats['total_unique_terms'] = (int)$result['total'];

        // Most frequent terms
        $sql = "SELECT term, SUM(frequency) as total_frequency 
                FROM search_terms {$where}
                GROUP BY term_hash, term 
                ORDER BY total_frequency DESC 
                LIMIT 20";
        $stats['most_frequent'] = $this->raw($sql, $params);

        return $stats;
    }

    /**
     * Get term suggestions for autocomplete
     */
    public function getTermSuggestions(string $query, int $projectId = null, int $limit = 10): array
    {
        $where = ["term LIKE ?"];
        $params = ["{$query}%"];

        if ($projectId) {
            $where[] = "project_id = ?";
            $params[] = $projectId;
        }

        $sql = "SELECT term, SUM(frequency) as total_frequency 
                FROM search_terms 
                WHERE " . implode(" AND ", $where) . "
                GROUP BY term_hash, term 
                ORDER BY total_frequency DESC 
                LIMIT {$limit}";

        return $this->raw($sql, $params);
    }

    /**
     * Bulk insert terms for better performance
     */
    private function bulkInsert(array $terms): void
    {
        if (empty($terms)) {
            return;
        }

        $fields = array_keys($terms[0]);
        $placeholders = '(' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $allPlaceholders = array_fill(0, count($terms), $placeholders);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $this->table,
            implode(', ', $fields),
            implode(', ', $allPlaceholders)
        );

        $params = [];
        foreach ($terms as $term) {
            foreach ($fields as $field) {
                $params[] = $term[$field];
            }
        }

        $this->db->execute($sql, $params);
    }

    /**
     * Extract search terms from query
     */
    private function extractSearchTerms(string $query): array
    {
        // Remove special characters and split into words
        $query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
        $words = preg_split('/\s+/', trim($query));
        
        // Filter out short words and common stop words
        $stopWords = ['le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'est', 'sont'];
        $terms = [];
        
        foreach ($words as $word) {
            $word = strtolower(trim($word));
            if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                $terms[] = $word;
            }
        }

        return array_unique($terms);
    }
}