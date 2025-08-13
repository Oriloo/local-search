-- Local Search Database Schema
-- Version: 2.0
-- Compatible with: MySQL 5.7+, MariaDB 10.2+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: local_search
CREATE DATABASE IF NOT EXISTS `local_search` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `local_search`;

-- --------------------------------------------------------

-- Table structure for table `search_projects`
CREATE TABLE `search_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `base_domains` json NOT NULL,
  `crawl_config` json,
  `status` enum('active','inactive','error') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `sites`
CREATE TABLE `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `base_url` varchar(500) NOT NULL,
  `status` enum('pending','active','processing','error','inactive') DEFAULT 'pending',
  `last_crawled` timestamp NULL DEFAULT NULL,
  `crawl_frequency` int(11) DEFAULT 24,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_domain` (`project_id`, `domain`),
  KEY `idx_status` (`status`),
  KEY `idx_last_crawled` (`last_crawled`),
  KEY `fk_sites_project` (`project_id`),
  CONSTRAINT `fk_sites_project` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `documents`
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `url` varchar(1000) NOT NULL,
  `url_hash` varchar(64) NOT NULL,
  `title` varchar(500),
  `description` text,
  `content_text` longtext,
  `content_type` varchar(100) DEFAULT 'text/html',
  `file_size` bigint(20) DEFAULT 0,
  `language` varchar(10) DEFAULT 'fr',
  `pagerank_score` decimal(10,8) DEFAULT 1.00000000,
  `quality_score` decimal(10,8) DEFAULT 1.00000000,
  `indexed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_url_hash` (`url_hash`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_content_type` (`content_type`),
  KEY `idx_language` (`language`),
  KEY `idx_indexed_at` (`indexed_at`),
  KEY `idx_pagerank_score` (`pagerank_score`),
  KEY `idx_quality_score` (`quality_score`),
  FULLTEXT KEY `ft_search_content` (`title`, `description`, `content_text`),
  CONSTRAINT `fk_documents_project` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documents_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `search_terms`
CREATE TABLE `search_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `term` varchar(255) NOT NULL,
  `term_hash` varchar(64) NOT NULL,
  `frequency` int(11) DEFAULT 1,
  `position` int(11) DEFAULT 0,
  `context` text,
  `weight` decimal(10,8) DEFAULT 1.00000000,
  PRIMARY KEY (`id`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_term_hash` (`term_hash`),
  KEY `idx_frequency` (`frequency`),
  KEY `idx_weight` (`weight`),
  KEY `idx_term_project` (`term_hash`, `project_id`),
  CONSTRAINT `fk_terms_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_terms_project` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `crawl_queue`
CREATE TABLE `crawl_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `url` varchar(1000) NOT NULL,
  `url_hash` varchar(64) NOT NULL,
  `parent_url` varchar(1000),
  `depth` int(11) DEFAULT 0,
  `priority` int(11) DEFAULT 5,
  `status` enum('pending','processing','completed','failed','skipped') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_url_hash_project` (`url_hash`, `project_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_depth` (`depth`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_queue_processing` (`status`, `priority`, `created_at`),
  CONSTRAINT `fk_queue_project` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_queue_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `search_logs`
CREATE TABLE `search_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `query` varchar(500) NOT NULL,
  `project_id` int(11),
  `results_count` int(11) DEFAULT 0,
  `search_time` decimal(10,6) DEFAULT 0.000000,
  `user_ip` varchar(45),
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_query` (`query`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_searchlogs_project` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `crawl_statistics`
CREATE TABLE `crawl_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `crawl_date` date NOT NULL,
  `urls_discovered` int(11) DEFAULT 0,
  `urls_successful` int(11) DEFAULT 0,
  `urls_failed` int(11) DEFAULT 0,
  `total_size` bigint(20) DEFAULT 0,
  `crawl_duration` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_site_date` (`site_id`, `crawl_date`),
  KEY `idx_crawl_date` (`crawl_date`),
  CONSTRAINT `fk_crawlstats_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Insert sample data for testing

-- Sample project
INSERT INTO `search_projects` (`name`, `description`, `base_domains`, `crawl_config`) VALUES
('Projet Test', 'Projet de démonstration', '["example.com", "test.com"]', '{"max_depth": 3, "crawl_delay": 1, "respect_robots": true}');

-- Sample site
INSERT INTO `sites` (`project_id`, `domain`, `base_url`, `status`) VALUES
(1, 'example.com', 'https://example.com', 'active');

-- --------------------------------------------------------

-- Create indexes for optimization

-- Additional indexes for better search performance
CREATE INDEX `idx_documents_search` ON `documents` (`project_id`, `content_type`, `language`, `indexed_at`);
CREATE INDEX `idx_terms_search` ON `search_terms` (`project_id`, `term_hash`, `weight`);
CREATE INDEX `idx_documents_url_project` ON `documents` (`url_hash`, `project_id`);

-- --------------------------------------------------------

-- Create views for common queries

-- View for document search with site information
CREATE VIEW `v_documents_search` AS
SELECT 
    d.*,
    s.domain,
    s.base_url as site_base_url,
    sp.name as project_name
FROM documents d
JOIN sites s ON d.site_id = s.id
JOIN search_projects sp ON d.project_id = sp.id;

-- View for crawl statistics
CREATE VIEW `v_crawl_overview` AS
SELECT 
    s.id as site_id,
    s.domain,
    s.base_url,
    s.status,
    s.last_crawled,
    sp.name as project_name,
    COUNT(d.id) as document_count,
    SUM(d.file_size) as total_size,
    AVG(d.quality_score) as avg_quality_score
FROM sites s
JOIN search_projects sp ON s.project_id = sp.id
LEFT JOIN documents d ON s.id = d.site_id
GROUP BY s.id, s.domain, s.base_url, s.status, s.last_crawled, sp.name;

-- --------------------------------------------------------

COMMIT;