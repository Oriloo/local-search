<?php

namespace LocalSearch\Models;

/**
 * Site model for managing crawled sites
 */
class Site extends BaseModel
{
    protected $table = 'sites';
    protected $fillable = [
        'project_id',
        'domain',
        'base_url',
        'status',
        'last_crawled',
        'crawl_frequency'
    ];

    /**
     * Add a new site to a project
     */
    public function addSite(int $projectId, string $domain, string $baseUrl): int
    {
        $data = [
            'project_id' => $projectId,
            'domain' => $domain,
            'base_url' => $baseUrl,
            'status' => 'pending'
        ];

        return $this->create($data);
    }

    /**
     * Get sites with project information
     */
    public function getSitesWithProjects(int $projectId = null): array
    {
        $where = $projectId ? "WHERE s.project_id = ?" : "";
        $params = $projectId ? [$projectId] : [];

        $sql = "SELECT s.*, sp.name as project_name,
                (SELECT COUNT(*) FROM documents WHERE site_id = s.id) as documents_count
                FROM sites s 
                JOIN search_projects sp ON s.project_id = sp.id 
                {$where}
                ORDER BY sp.name, s.domain";

        return $this->raw($sql, $params);
    }

    /**
     * Update site status
     */
    public function updateStatus(int $siteId, string $status): bool
    {
        return $this->update($siteId, ['status' => $status]);
    }

    /**
     * Update last crawled timestamp
     */
    public function updateLastCrawled(int $siteId): bool
    {
        return $this->update($siteId, ['last_crawled' => date('Y-m-d H:i:s')]);
    }

    /**
     * Get sites that need crawling
     */
    public function getSitesForCrawling(): array
    {
        $sql = "SELECT s.*, sp.crawl_config 
                FROM sites s 
                JOIN search_projects sp ON s.project_id = sp.id 
                WHERE s.status IN ('pending', 'active') 
                AND (s.last_crawled IS NULL OR s.last_crawled < DATE_SUB(NOW(), INTERVAL s.crawl_frequency HOUR))
                ORDER BY s.last_crawled ASC NULLS FIRST";

        return $this->raw($sql);
    }

    /**
     * Get site statistics
     */
    public function getSiteStats(int $siteId): array
    {
        $stats = [];

        // Document count
        $sql = "SELECT COUNT(*) as total FROM documents WHERE site_id = ?";
        $result = $this->rawOne($sql, [$siteId]);
        $stats['total_documents'] = (int)$result['total'];

        // Documents by content type
        $sql = "SELECT content_type, COUNT(*) as count 
                FROM documents 
                WHERE site_id = ? 
                GROUP BY content_type";
        $types = $this->raw($sql, [$siteId]);
        $stats['by_type'] = [];
        foreach ($types as $type) {
            $stats['by_type'][$type['content_type']] = (int)$type['count'];
        }

        // Crawl queue status
        $sql = "SELECT status, COUNT(*) as count 
                FROM crawl_queue 
                WHERE site_id = ? 
                GROUP BY status";
        $queue = $this->raw($sql, [$siteId]);
        $stats['queue_status'] = [];
        foreach ($queue as $item) {
            $stats['queue_status'][$item['status']] = (int)$item['count'];
        }

        return $stats;
    }

    /**
     * Get sites for a specific project
     */
    public function getByProject(int $projectId): array
    {
        return $this->findAll(['project_id' => $projectId], 'domain ASC');
    }

    /**
     * Check if domain already exists in project
     */
    public function domainExists(int $projectId, string $domain): bool
    {
        $sql = "SELECT COUNT(*) as count FROM sites WHERE project_id = ? AND domain = ?";
        $result = $this->rawOne($sql, [$projectId, $domain]);
        return (int)$result['count'] > 0;
    }
}