<?php

namespace LocalSearch\Models;

/**
 * Project model for managing search projects
 */
class Project extends BaseModel
{
    protected $table = 'search_projects';
    protected $fillable = [
        'name',
        'description', 
        'base_domains',
        'crawl_config',
        'status'
    ];

    /**
     * Get all projects with site and document counts
     */
    public function getAllWithStats(): array
    {
        $sql = "SELECT sp.*, 
                (SELECT COUNT(*) FROM sites WHERE project_id = sp.id) as sites_count,
                (SELECT COUNT(*) FROM documents WHERE project_id = sp.id) as documents_count
                FROM search_projects sp 
                ORDER BY sp.created_at DESC";
        
        return $this->raw($sql);
    }

    /**
     * Create a new project with domains and configuration
     */
    public function createProject(string $name, string $description, array $domains, array $config = []): int
    {
        $data = [
            'name' => $name,
            'description' => $description,
            'base_domains' => json_encode($domains),
            'crawl_config' => json_encode($config),
            'status' => 'active'
        ];

        return $this->create($data);
    }

    /**
     * Update project configuration
     */
    public function updateProject(int $id, string $name, string $description, array $domains, array $config = []): bool
    {
        $data = [
            'name' => $name,
            'description' => $description,
            'base_domains' => json_encode($domains),
            'crawl_config' => json_encode($config)
        ];

        return $this->update($id, $data);
    }

    /**
     * Get project with parsed domains and config
     */
    public function getWithParsedData(int $id): ?array
    {
        $project = $this->find($id);
        
        if ($project) {
            $project['base_domains'] = json_decode($project['base_domains'], true) ?? [];
            $project['crawl_config'] = json_decode($project['crawl_config'], true) ?? [];
        }

        return $project;
    }

    /**
     * Get projects for dropdown/select options
     */
    public function getForSelect(): array
    {
        return $this->findAll([], 'name ASC');
    }

    /**
     * Get project statistics
     */
    public function getStats(int $projectId = null): array
    {
        $where = $projectId ? "WHERE project_id = ?" : "";
        $params = $projectId ? [$projectId] : [];

        $stats = [];

        // Total documents
        $sql = "SELECT COUNT(*) as total FROM documents {$where}";
        $result = $this->rawOne($sql, $params);
        $stats['total_documents'] = (int)$result['total'];

        // Documents by type
        $sql = "SELECT content_type, COUNT(*) as count FROM documents {$where} GROUP BY content_type";
        $types = $this->raw($sql, $params);
        $stats['by_type'] = [];
        foreach ($types as $type) {
            $stats['by_type'][$type['content_type']] = (int)$type['count'];
        }

        // Recent activity (last 7 days)
        $sql = "SELECT DATE(indexed_at) as date, COUNT(*) as count 
                FROM documents 
                WHERE indexed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) {$where ? 'AND ' . str_replace('WHERE ', '', $where) : ''}
                GROUP BY DATE(indexed_at) 
                ORDER BY date DESC";
        $recent = $this->raw($sql, $params);
        $stats['recent_activity'] = $recent;

        return $stats;
    }
}