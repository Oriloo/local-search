<?php

namespace LocalSearch\Models;

/**
 * Document model for managing indexed documents
 */
class Document extends BaseModel
{
    protected $table = 'documents';
    protected $fillable = [
        'project_id',
        'site_id',
        'url',
        'url_hash',
        'title',
        'description',
        'content_text',
        'content_type',
        'file_size',
        'language',
        'pagerank_score',
        'quality_score',
        'indexed_at'
    ];

    protected $timestamps = false; // Using indexed_at instead

    /**
     * Save a crawled document
     */
    public function saveDocument(int $projectId, int $siteId, string $url, array $parsed): int
    {
        $data = [
            'project_id' => $projectId,
            'site_id' => $siteId,
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'title' => $parsed['title'] ?? '',
            'description' => $parsed['description'] ?? '',
            'content_text' => $parsed['content_text'] ?? '',
            'content_type' => $parsed['content_type'] ?? 'text/html',
            'file_size' => $parsed['file_size'] ?? 0,
            'language' => $parsed['language'] ?? 'fr',
            'pagerank_score' => $parsed['pagerank_score'] ?? 1.0,
            'quality_score' => $parsed['quality_score'] ?? 1.0,
            'indexed_at' => date('Y-m-d H:i:s')
        ];

        return $this->create($data);
    }

    /**
     * Check if URL already exists
     */
    public function urlExists(string $url): bool
    {
        $urlHash = hash('sha256', $url);
        $sql = "SELECT COUNT(*) as count FROM documents WHERE url_hash = ?";
        $result = $this->rawOne($sql, [$urlHash]);
        return (int)$result['count'] > 0;
    }

    /**
     * Search documents with advanced options
     */
    public function search(array $searchParams): array
    {
        $query = $searchParams['query'] ?? '';
        $projectId = $searchParams['project_id'] ?? null;
        $contentType = $searchParams['content_type'] ?? null;
        $siteId = $searchParams['site_id'] ?? null;
        $language = $searchParams['language'] ?? null;
        $dateFrom = $searchParams['date_from'] ?? null;
        $dateTo = $searchParams['date_to'] ?? null;
        $sort = $searchParams['sort'] ?? 'relevance';
        $page = $searchParams['page'] ?? 1;
        $perPage = $searchParams['per_page'] ?? 20;

        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];

        // Search conditions
        if (!empty($query)) {
            $where[] = "(d.title LIKE ? OR d.description LIKE ? OR d.content_text LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filters
        if ($projectId) {
            $where[] = "d.project_id = ?";
            $params[] = $projectId;
        }

        if ($contentType) {
            $where[] = "d.content_type = ?";
            $params[] = $contentType;
        }

        if ($siteId) {
            $where[] = "d.site_id = ?";
            $params[] = $siteId;
        }

        if ($language) {
            $where[] = "d.language = ?";
            $params[] = $language;
        }

        if ($dateFrom) {
            $where[] = "d.indexed_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $where[] = "d.indexed_at <= ?";
            $params[] = $dateTo;
        }

        // Build ORDER BY
        $orderBy = $this->getOrderBy($sort);

        // Count total results
        $countSql = "SELECT COUNT(*) as total FROM documents d";
        if (!empty($where)) {
            $countSql .= " WHERE " . implode(" AND ", $where);
        }
        $totalResult = $this->rawOne($countSql, $params);
        $total = (int)$totalResult['total'];

        // Get results
        $sql = "SELECT d.*, s.domain, sp.name as project_name 
                FROM documents d 
                JOIN sites s ON d.site_id = s.id 
                JOIN search_projects sp ON d.project_id = sp.id";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";

        $results = $this->raw($sql, $params);

        return [
            'total' => $total,
            'results' => $results,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get document statistics
     */
    public function getStatistics(int $projectId = null): array
    {
        $where = $projectId ? "WHERE project_id = ?" : "";
        $params = $projectId ? [$projectId] : [];

        $stats = [];

        // Total documents
        $sql = "SELECT COUNT(*) as total FROM documents {$where}";
        $result = $this->rawOne($sql, $params);
        $stats['total'] = (int)$result['total'];

        // By content type
        $sql = "SELECT content_type, COUNT(*) as count FROM documents {$where} GROUP BY content_type";
        $types = $this->raw($sql, $params);
        $stats['by_type'] = [];
        foreach ($types as $type) {
            $stats['by_type'][$type['content_type']] = (int)$type['count'];
        }

        // By language
        $sql = "SELECT language, COUNT(*) as count FROM documents {$where} GROUP BY language";
        $languages = $this->raw($sql, $params);
        $stats['by_language'] = [];
        foreach ($languages as $lang) {
            $stats['by_language'][$lang['language']] = (int)$lang['count'];
        }

        return $stats;
    }

    /**
     * Get order by clause for sorting
     */
    private function getOrderBy(string $sort): string
    {
        switch ($sort) {
            case 'date':
                return 'd.indexed_at DESC';
            case 'title':
                return 'd.title ASC';
            case 'relevance':
            default:
                return 'd.pagerank_score DESC, d.quality_score DESC';
        }
    }
}