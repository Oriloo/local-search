<?php

namespace LocalSearch\Services;

use LocalSearch\Models\Term;
use LocalSearch\Models\Document;

/**
 * Indexing service for processing and indexing document content
 */
class IndexingService
{
    private $termModel;
    private $documentModel;

    public function __construct()
    {
        $this->termModel = new Term();
        $this->documentModel = new Document();
    }

    /**
     * Index a document for search
     */
    public function indexDocument(int $documentId, int $projectId, array $parsedContent): void
    {
        // Extract terms from content
        $terms = $this->extractTerms($parsedContent);
        
        // Calculate term weights and positions
        $processedTerms = $this->processTerms($terms, $parsedContent);
        
        // Save terms to database
        $this->termModel->indexTermsForDocument($documentId, $projectId, $processedTerms);
    }

    /**
     * Extract terms from parsed content
     */
    private function extractTerms(array $parsedContent): array
    {
        $allTerms = [];

        // Extract from title (higher weight)
        if (!empty($parsedContent['title'])) {
            $titleTerms = $this->tokenizeText($parsedContent['title']);
            foreach ($titleTerms as $term) {
                $allTerms[] = [
                    'term' => $term,
                    'source' => 'title',
                    'weight' => 3.0
                ];
            }
        }

        // Extract from description (medium weight)
        if (!empty($parsedContent['description'])) {
            $descTerms = $this->tokenizeText($parsedContent['description']);
            foreach ($descTerms as $term) {
                $allTerms[] = [
                    'term' => $term,
                    'source' => 'description',
                    'weight' => 2.0
                ];
            }
        }

        // Extract from content (normal weight)
        if (!empty($parsedContent['content_text'])) {
            $contentTerms = $this->tokenizeText($parsedContent['content_text']);
            foreach ($contentTerms as $term) {
                $allTerms[] = [
                    'term' => $term,
                    'source' => 'content',
                    'weight' => 1.0
                ];
            }
        }

        return $allTerms;
    }

    /**
     * Process and aggregate terms
     */
    private function processTerms(array $terms, array $parsedContent): array
    {
        $processed = [];
        $positions = [];

        foreach ($terms as $termData) {
            $term = $termData['term'];
            $source = $termData['source'];
            $weight = $termData['weight'];

            if (!isset($processed[$term])) {
                $processed[$term] = [
                    'frequency' => 0,
                    'weight' => 0,
                    'positions' => [],
                    'context' => ''
                ];
            }

            $processed[$term]['frequency']++;
            $processed[$term]['weight'] = max($processed[$term]['weight'], $weight);
            
            // Store context from first occurrence
            if (empty($processed[$term]['context'])) {
                $processed[$term]['context'] = $this->extractContext($term, $parsedContent, $source);
            }
        }

        // Calculate final weights based on frequency and source
        foreach ($processed as $term => &$data) {
            // TF-IDF inspired weighting
            $tf = $data['frequency'];
            $data['weight'] = $data['weight'] * (1 + log($tf));
            $data['position'] = $positions[$term] ?? 0;
        }

        return $processed;
    }

    /**
     * Tokenize text into searchable terms
     */
    private function tokenizeText(string $text): array
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remove HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Remove punctuation except apostrophes
        $text = preg_replace('/[^\p{L}\p{N}\s\']/u', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', trim($text));
        
        // Filter words
        $terms = [];
        $stopWords = $this->getStopWords();
        
        foreach ($words as $word) {
            $word = trim($word, "'");
            
            // Skip if too short, too long, or is stop word
            if (strlen($word) < 2 || strlen($word) > 50 || in_array($word, $stopWords)) {
                continue;
            }
            
            // Skip if all numbers
            if (is_numeric($word)) {
                continue;
            }
            
            $terms[] = $word;
        }
        
        return $terms;
    }

    /**
     * Extract context around a term
     */
    private function extractContext(string $term, array $parsedContent, string $source): string
    {
        $text = '';
        
        switch ($source) {
            case 'title':
                $text = $parsedContent['title'] ?? '';
                break;
            case 'description':
                $text = $parsedContent['description'] ?? '';
                break;
            case 'content':
                $text = $parsedContent['content_text'] ?? '';
                break;
        }
        
        if (empty($text)) {
            return '';
        }
        
        // Find term position and extract surrounding context
        $termPos = mb_stripos($text, $term);
        if ($termPos === false) {
            return mb_substr($text, 0, 100);
        }
        
        $contextStart = max(0, $termPos - 50);
        $contextLength = min(200, mb_strlen($text) - $contextStart);
        
        return mb_substr($text, $contextStart, $contextLength);
    }

    /**
     * Get list of stop words to exclude from indexing
     */
    private function getStopWords(): array
    {
        return [
            // French stop words
            'le', 'de', 'et', 'à', 'un', 'il', 'être', 'et', 'en', 'avoir', 'que', 'pour',
            'dans', 'ce', 'son', 'une', 'sur', 'avec', 'ne', 'se', 'pas', 'tout', 'plus',
            'par', 'grand', 'comme', 'mais', 'que', 'premier', 'vous', 'ou', 'son', 'nous',
            'faire', 'du', 'aller', 'voir', 'temps', 'petit', 'que', 'être', 'avoir',
            'la', 'les', 'des', 'au', 'aux', 'du', 'ces', 'cette', 'ses', 'mes', 'tes',
            'nos', 'vos', 'leurs', 'mon', 'ton', 'son', 'ma', 'ta', 'sa', 'notre', 'votre',
            'leur', 'je', 'tu', 'il', 'elle', 'nous', 'vous', 'ils', 'elles', 'me', 'te',
            'se', 'nous', 'vous', 'se', 'moi', 'toi', 'lui', 'elle', 'nous', 'vous', 'eux',
            'elles', 'qui', 'que', 'quoi', 'dont', 'où', 'quand', 'comment', 'pourquoi',
            'quel', 'quelle', 'quels', 'quelles', 'lequel', 'laquelle', 'lesquels', 'lesquelles',
            
            // English stop words (common in web content)
            'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'it', 'for', 'not',
            'on', 'with', 'he', 'as', 'you', 'do', 'at', 'this', 'but', 'his', 'by', 'from',
            'they', 'we', 'say', 'her', 'she', 'or', 'an', 'will', 'my', 'one', 'all', 'would',
            'there', 'their', 'what', 'so', 'up', 'out', 'if', 'about', 'who', 'get', 'which',
            'go', 'me', 'when', 'make', 'can', 'like', 'time', 'no', 'just', 'him', 'know',
            'take', 'people', 'into', 'year', 'your', 'good', 'some', 'could', 'them', 'see',
            'other', 'than', 'then', 'now', 'look', 'only', 'come', 'its', 'over', 'think',
            'also', 'back', 'after', 'use', 'two', 'how', 'our', 'work', 'first', 'well',
            'way', 'even', 'new', 'want', 'because', 'any', 'these', 'give', 'day', 'most', 'us'
        ];
    }

    /**
     * Reindex all documents for a project
     */
    public function reindexProject(int $projectId): array
    {
        $stats = ['processed' => 0, 'errors' => 0];
        
        // Get all documents for the project
        $documents = $this->documentModel->findAll(['project_id' => $projectId]);
        
        foreach ($documents as $document) {
            try {
                // Re-extract content (simplified - in real implementation, 
                // you might want to re-parse the original content)
                $parsedContent = [
                    'title' => $document['title'],
                    'description' => $document['description'],
                    'content_text' => $document['content_text']
                ];
                
                $this->indexDocument($document['id'], $projectId, $parsedContent);
                $stats['processed']++;
                
            } catch (\Exception $e) {
                $stats['errors']++;
                error_log("Error reindexing document {$document['id']}: " . $e->getMessage());
            }
        }
        
        return $stats;
    }

    /**
     * Clean up orphaned terms
     */
    public function cleanupOrphanedTerms(): int
    {
        // Remove terms for documents that no longer exist
        $sql = "DELETE t FROM search_terms t 
                LEFT JOIN documents d ON t.document_id = d.id 
                WHERE d.id IS NULL";
        
        $stmt = $this->termModel->raw($sql);
        return $stmt->rowCount ?? 0;
    }

    /**
     * Get indexing statistics
     */
    public function getIndexingStatistics(int $projectId = null): array
    {
        $stats = [];
        
        // Document count
        $documentCount = $this->documentModel->count(
            $projectId ? ['project_id' => $projectId] : []
        );
        $stats['total_documents'] = $documentCount;
        
        // Term statistics
        $termStats = $this->termModel->getTermStatistics($projectId);
        $stats['term_statistics'] = $termStats;
        
        // Index size estimation
        $where = $projectId ? "WHERE project_id = ?" : "";
        $params = $projectId ? [$projectId] : [];
        
        $sql = "SELECT 
                    COUNT(*) as total_term_entries,
                    AVG(frequency) as avg_term_frequency,
                    SUM(frequency) as total_term_occurrences
                FROM search_terms {$where}";
                
        $indexStats = $this->termModel->rawOne($sql, $params);
        $stats['index_statistics'] = $indexStats;
        
        return $stats;
    }

    /**
     * Optimize search index
     */
    public function optimizeIndex(): array
    {
        $stats = ['operations' => [], 'errors' => []];
        
        try {
            // Clean up orphaned terms
            $orphanedCount = $this->cleanupOrphanedTerms();
            $stats['operations'][] = "Removed {$orphanedCount} orphaned terms";
            
            // Update term weights based on document frequency (IDF calculation)
            $this->updateTermWeights();
            $stats['operations'][] = "Updated term weights";
            
            // Analyze and optimize database tables
            $this->optimizeTables();
            $stats['operations'][] = "Optimized database tables";
            
        } catch (\Exception $e) {
            $stats['errors'][] = $e->getMessage();
        }
        
        return $stats;
    }

    /**
     * Update term weights using IDF calculation
     */
    private function updateTermWeights(): void
    {
        // Get total document count
        $totalDocs = $this->documentModel->count();
        
        if ($totalDocs == 0) {
            return;
        }
        
        // Calculate IDF for each unique term
        $sql = "SELECT term_hash, term, COUNT(DISTINCT document_id) as doc_frequency
                FROM search_terms 
                GROUP BY term_hash, term";
        
        $termFrequencies = $this->termModel->raw($sql);
        
        foreach ($termFrequencies as $termData) {
            $df = $termData['doc_frequency'];
            $idf = log($totalDocs / $df);
            
            // Update weights for this term
            $updateSql = "UPDATE search_terms 
                         SET weight = weight * ? 
                         WHERE term_hash = ?";
            
            $this->termModel->db->execute($updateSql, [$idf, $termData['term_hash']]);
        }
    }

    /**
     * Optimize database tables
     */
    private function optimizeTables(): void
    {
        $tables = ['search_terms', 'documents'];
        
        foreach ($tables as $table) {
            $sql = "OPTIMIZE TABLE {$table}";
            $this->termModel->db->execute($sql);
        }
    }
}