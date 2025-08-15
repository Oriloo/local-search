-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : db
-- Généré le : ven. 15 août 2025 à 13:29
-- Version du serveur : 8.4.6
-- Version de PHP : 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `local_search`
--

-- --------------------------------------------------------

--
-- Structure de la table `crawl_history`
--

CREATE TABLE `crawl_history` (
  `id` bigint NOT NULL,
  `project_id` int NOT NULL,
  `started_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `urls_discovered` int DEFAULT '0',
  `urls_successful` int DEFAULT '0',
  `urls_failed` int DEFAULT '0',
  `errors` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `crawl_queue`
--

CREATE TABLE `crawl_queue` (
  `id` bigint NOT NULL,
  `project_id` int NOT NULL,
  `site_id` int DEFAULT NULL,
  `url` varchar(1000) NOT NULL,
  `priority` int DEFAULT '5',
  `depth` int DEFAULT '0',
  `parent_url` varchar(1000) DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `attempts` int DEFAULT '0',
  `last_error` text,
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `documents`
--

CREATE TABLE `documents` (
  `id` bigint NOT NULL,
  `project_id` int NOT NULL,
  `site_id` int NOT NULL,
  `url` varchar(1000) NOT NULL,
  `url_hash` varchar(64) NOT NULL,
  `content_type` enum('webpage','image','video') NOT NULL,
  `title` varchar(500) DEFAULT NULL,
  `description` text,
  `content_text` longtext,
  `language` varchar(10) DEFAULT NULL,
  `file_size` bigint DEFAULT '0',
  `last_modified` timestamp NULL DEFAULT NULL,
  `indexed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `pagerank_score` float DEFAULT '0',
  `quality_score` float DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `document_metadata`
--

CREATE TABLE `document_metadata` (
  `document_id` bigint NOT NULL,
  `meta_key` varchar(100) NOT NULL,
  `meta_value` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inverted_index`
--

CREATE TABLE `inverted_index` (
  `term_id` bigint NOT NULL,
  `document_id` bigint NOT NULL,
  `project_id` int NOT NULL,
  `tf_score` float DEFAULT '0',
  `positions` json DEFAULT NULL,
  `field_type` enum('title','content','alt','meta') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `page_links`
--

CREATE TABLE `page_links` (
  `from_document_id` bigint NOT NULL,
  `to_document_id` bigint NOT NULL,
  `anchor_text` varchar(200) DEFAULT NULL,
  `link_type` enum('internal','external') DEFAULT 'internal',
  `discovered_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `search_projects`
--

CREATE TABLE `search_projects` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `base_domains` json DEFAULT NULL,
  `crawl_config` json DEFAULT NULL,
  `status` enum('active','paused','archived') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sites`
--

CREATE TABLE `sites` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `domain` varchar(255) NOT NULL,
  `base_url` varchar(500) NOT NULL,
  `crawl_frequency` int DEFAULT '86400',
  `last_crawled` timestamp NULL DEFAULT NULL,
  `robots_txt` text,
  `sitemap_url` varchar(500) DEFAULT NULL,
  `status` enum('active','blocked','error','processing') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `terms`
--

CREATE TABLE `terms` (
  `id` bigint NOT NULL,
  `term` varchar(100) NOT NULL,
  `term_normalized` varchar(100) NOT NULL,
  `frequency` bigint DEFAULT '0',
  `idf_score` float DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `crawl_history`
--
ALTER TABLE `crawl_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_started` (`started_at`);

--
-- Index pour la table `crawl_queue`
--
ALTER TABLE `crawl_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_scheduled` (`scheduled_at`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `fk_crawl_queue_site` (`site_id`);

--
-- Index pour la table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `url_hash` (`url_hash`),
  ADD UNIQUE KEY `unique_url` (`url_hash`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_content_type` (`content_type`),
  ADD KEY `idx_indexed_at` (`indexed_at`);
ALTER TABLE `documents` ADD FULLTEXT KEY `idx_content` (`title`,`description`,`content_text`);

--
-- Index pour la table `document_metadata`
--
ALTER TABLE `document_metadata`
  ADD PRIMARY KEY (`document_id`,`meta_key`),
  ADD KEY `idx_meta_key` (`meta_key`);

--
-- Index pour la table `inverted_index`
--
ALTER TABLE `inverted_index`
  ADD PRIMARY KEY (`term_id`,`document_id`,`field_type`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_document` (`document_id`);

--
-- Index pour la table `page_links`
--
ALTER TABLE `page_links`
  ADD PRIMARY KEY (`from_document_id`,`to_document_id`),
  ADD KEY `idx_from` (`from_document_id`),
  ADD KEY `idx_to` (`to_document_id`);

--
-- Index pour la table `search_projects`
--
ALTER TABLE `search_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_name` (`name`);

--
-- Index pour la table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `terms`
--
ALTER TABLE `terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `term` (`term`),
  ADD KEY `idx_term` (`term`),
  ADD KEY `idx_normalized` (`term_normalized`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `crawl_history`
--
ALTER TABLE `crawl_history`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `crawl_queue`
--
ALTER TABLE `crawl_queue`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `search_projects`
--
ALTER TABLE `search_projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `terms`
--
ALTER TABLE `terms`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `crawl_history`
--
ALTER TABLE `crawl_history`
  ADD CONSTRAINT `crawl_history_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `crawl_queue`
--
ALTER TABLE `crawl_queue`
  ADD CONSTRAINT `crawl_queue_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_crawl_queue_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document_metadata`
--
ALTER TABLE `document_metadata`
  ADD CONSTRAINT `document_metadata_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `inverted_index`
--
ALTER TABLE `inverted_index`
  ADD CONSTRAINT `inverted_index_ibfk_1` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inverted_index_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inverted_index_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `page_links`
--
ALTER TABLE `page_links`
  ADD CONSTRAINT `page_links_ibfk_1` FOREIGN KEY (`from_document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `page_links_ibfk_2` FOREIGN KEY (`to_document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `search_projects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
