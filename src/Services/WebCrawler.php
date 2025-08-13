<?php

namespace LocalSearch\Services;

use LocalSearch\Models\Document;
use LocalSearch\Models\Site;
use LocalSearch\Models\Term;
use LocalSearch\Config\Configuration;
use DOMDocument;
use DOMXPath;

/**
 * Web crawler service for discovering and indexing content
 */
class WebCrawler
{
    private $documentModel;
    private $siteModel;
    private $termModel;
    private $indexingService;

    private $userAgent;
    private $crawlDelay;
    private $maxDepth;
    private $maxContentLength;
    private $allowedContentTypes;

    public function __construct()
    {
        $this->documentModel = new Document();
        $this->siteModel = new Site();
        $this->termModel = new Term();
        $this->indexingService = new IndexingService();

        // Load configuration
        $this->userAgent = Configuration::get('USER_AGENT');
        $this->crawlDelay = Configuration::get('CRAWL_DELAY');
        $this->maxDepth = Configuration::get('MAX_CRAWL_DEPTH');
        $this->maxContentLength = Configuration::get('MAX_CONTENT_LENGTH');
        
        $this->allowedContentTypes = [
            'text/html',
            'text/plain',
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'video/webm',
            'video/ogg'
        ];
    }

    /**
     * Crawl a specific site
     */
    public function crawlSite(int $siteId, int $maxPages = 100): array
    {
        $site = $this->siteModel->find($siteId);
        if (!$site) {
            throw new \Exception("Site not found: {$siteId}");
        }

        $stats = [
            'site_id' => $siteId,
            'urls_discovered' => 0,
            'urls_successful' => 0,
            'urls_failed' => 0,
            'start_time' => time(),
            'errors' => []
        ];

        // Update site status
        $this->siteModel->updateStatus($siteId, 'processing');

        try {
            // Initialize crawl queue
            $this->initializeCrawlQueue($site);

            // Process URLs from queue
            $processed = 0;
            while ($processed < $maxPages) {
                $urlData = $this->getNextUrlFromQueue($site['project_id']);
                if (!$urlData) {
                    break; // No more URLs to process
                }

                $result = $this->crawlUrl($urlData);

                if ($result['success']) {
                    $stats['urls_successful']++;
                    
                    // Discover new links
                    if (!empty($result['links']) && $urlData['depth'] < $this->maxDepth) {
                        foreach ($result['links'] as $link) {
                            if ($this->shouldCrawlUrl($link, $site)) {
                                $this->addToQueue(
                                    $site['project_id'],
                                    $link,
                                    $urlData['depth'] + 1,
                                    $urlData['url'],
                                    $siteId
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

                // Respect crawl delay
                if ($this->crawlDelay > 0) {
                    sleep($this->crawlDelay);
                }
            }

            $this->siteModel->updateStatus($siteId, 'active');
            $this->siteModel->updateLastCrawled($siteId);

        } catch (\Exception $e) {
            $this->siteModel->updateStatus($siteId, 'error');
            $stats['errors'][] = $e->getMessage();
            throw $e;
        }

        $stats['end_time'] = time();
        $stats['duration'] = $stats['end_time'] - $stats['start_time'];

        return $stats;
    }

    /**
     * Initialize crawl queue with base URL
     */
    private function initializeCrawlQueue(array $site): void
    {
        // Check if base URL is already queued
        if (!$this->isUrlInQueue($site['base_url'], $site['project_id'])) {
            $this->addToQueue(
                $site['project_id'],
                $site['base_url'],
                0, // depth
                null, // parent URL
                $site['id']
            );
        }
    }

    /**
     * Crawl a single URL
     */
    private function crawlUrl(array $urlData): array
    {
        $url = $urlData['url'];
        $projectId = $urlData['project_id'];
        $siteId = $urlData['site_id'];
        $depth = $urlData['depth'];

        try {
            // Check if URL already exists
            if ($this->documentModel->urlExists($url)) {
                $this->updateQueueStatus($urlData['id'], 'skipped', 'URL already exists');
                return ['success' => true, 'links' => []];
            }

            // Fetch content
            $content = $this->fetchUrl($url);
            if (!$content['success']) {
                $this->updateQueueStatus($urlData['id'], 'failed', $content['error']);
                return $content;
            }

            // Parse content
            $parsed = $this->parseContent($content['data'], $url, $content['content_type']);

            // Save document
            $documentId = $this->documentModel->saveDocument($projectId, $siteId, $url, $parsed);

            if ($documentId) {
                // Index terms
                $this->indexingService->indexDocument($documentId, $projectId, $parsed);

                $this->updateQueueStatus($urlData['id'], 'completed');
                return [
                    'success' => true,
                    'document_id' => $documentId,
                    'links' => $parsed['links'] ?? []
                ];
            } else {
                $this->updateQueueStatus($urlData['id'], 'failed', 'Failed to save document');
                return ['success' => false, 'error' => 'Failed to save document'];
            }

        } catch (\Exception $e) {
            $this->updateQueueStatus($urlData['id'], 'failed', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch URL content
     */
    private function fetchUrl(string $url): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'error' => "cURL error: {$error}"];
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "HTTP {$httpCode}"];
        }

        $body = substr($response, $headerSize);
        
        // Check content length
        if (strlen($body) > $this->maxContentLength) {
            return ['success' => false, 'error' => 'Content too large'];
        }

        // Check content type
        $mainContentType = strtok($contentType, ';');
        if (!in_array($mainContentType, $this->allowedContentTypes)) {
            return ['success' => false, 'error' => "Unsupported content type: {$mainContentType}"];
        }

        return [
            'success' => true,
            'data' => $body,
            'content_type' => $mainContentType,
            'size' => strlen($body)
        ];
    }

    /**
     * Parse content based on type
     */
    private function parseContent(string $content, string $url, string $contentType): array
    {
        $parsed = [
            'title' => '',
            'description' => '',
            'content_text' => '',
            'content_type' => $contentType,
            'file_size' => strlen($content),
            'language' => 'fr',
            'links' => [],
            'pagerank_score' => 1.0,
            'quality_score' => 1.0
        ];

        switch ($contentType) {
            case 'text/html':
                return $this->parseHtmlContent($content, $url, $parsed);
            case 'text/plain':
                return $this->parseTextContent($content, $parsed);
            default:
                // For other content types, extract basic metadata
                $parsed['title'] = basename(parse_url($url, PHP_URL_PATH));
                $parsed['content_text'] = substr($content, 0, 1000); // Limited preview
                return $parsed;
        }
    }

    /**
     * Parse HTML content
     */
    private function parseHtmlContent(string $html, string $url, array $parsed): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        // Extract title
        $titleNodes = $xpath->query('//title');
        if ($titleNodes->length > 0) {
            $parsed['title'] = trim($titleNodes->item(0)->textContent);
        }

        // Extract meta description
        $metaDesc = $xpath->query('//meta[@name="description"]/@content');
        if ($metaDesc->length > 0) {
            $parsed['description'] = trim($metaDesc->item(0)->value);
        }

        // Extract language
        $langAttr = $xpath->query('//html/@lang');
        if ($langAttr->length > 0) {
            $parsed['language'] = substr($langAttr->item(0)->value, 0, 2);
        }

        // Remove scripts, styles, and navigation elements
        $elementsToRemove = $xpath->query('//script | //style | //nav | //header | //footer | //aside');
        foreach ($elementsToRemove as $element) {
            if ($element->parentNode) {
                $element->parentNode->removeChild($element);
            }
        }

        // Extract main content
        $contentNodes = $xpath->query('//main | //article | //div[@class*="content"] | //div[@id*="content"] | //body');
        $contentText = '';
        if ($contentNodes->length > 0) {
            $contentText = $this->extractTextContent($contentNodes->item(0));
        } else {
            // Fallback to body content
            $bodyNodes = $xpath->query('//body');
            if ($bodyNodes->length > 0) {
                $contentText = $this->extractTextContent($bodyNodes->item(0));
            }
        }
        
        $parsed['content_text'] = $this->cleanTextContent($contentText);

        // Extract links
        $linkNodes = $xpath->query('//a[@href]');
        $baseUrl = $this->getBaseUrl($url);
        
        foreach ($linkNodes as $linkNode) {
            $href = $linkNode->getAttribute('href');
            $absoluteUrl = $this->resolveUrl($href, $baseUrl);
            if ($absoluteUrl && $this->isValidUrl($absoluteUrl)) {
                $parsed['links'][] = $absoluteUrl;
            }
        }

        // Calculate quality score based on content analysis
        $parsed['quality_score'] = $this->calculateQualityScore($parsed);

        return $parsed;
    }

    /**
     * Parse plain text content
     */
    private function parseTextContent(string $text, array $parsed): array
    {
        $parsed['content_text'] = $this->cleanTextContent($text);
        $parsed['title'] = substr($parsed['content_text'], 0, 100) . '...';
        $parsed['quality_score'] = min(1.0, strlen($text) / 1000); // Simple quality based on length
        
        return $parsed;
    }

    /**
     * Extract text content from DOM node
     */
    private function extractTextContent(\DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent . ' ';
            } elseif ($child->nodeType === XML_ELEMENT_NODE && 
                     !in_array($child->nodeName, ['script', 'style', 'nav', 'header', 'footer'])) {
                $text .= $this->extractTextContent($child);
            }
        }
        return $text;
    }

    /**
     * Clean and normalize text content
     */
    private function cleanTextContent(string $text): string
    {
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove control characters
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        
        // Trim and limit length
        $text = trim($text);
        if (strlen($text) > 50000) {
            $text = substr($text, 0, 50000) . '...';
        }
        
        return $text;
    }

    /**
     * Calculate content quality score
     */
    private function calculateQualityScore(array $parsed): float
    {
        $score = 0.0;
        
        // Title presence and quality
        if (!empty($parsed['title'])) {
            $score += 0.3;
            if (strlen($parsed['title']) > 10 && strlen($parsed['title']) < 100) {
                $score += 0.1;
            }
        }
        
        // Description presence
        if (!empty($parsed['description'])) {
            $score += 0.2;
        }
        
        // Content length (optimal around 1000-5000 chars)
        $contentLength = strlen($parsed['content_text']);
        if ($contentLength > 500) {
            $score += 0.3;
            if ($contentLength > 1000 && $contentLength < 10000) {
                $score += 0.2;
            }
        }
        
        return min(1.0, $score);
    }

    /**
     * Check if URL should be crawled
     */
    private function shouldCrawlUrl(string $url, array $site): bool
    {
        // Parse site domains from configuration
        $allowedDomains = json_decode($site['base_domains'] ?? '[]', true);
        if (empty($allowedDomains)) {
            $allowedDomains = [$site['domain']];
        }
        
        $urlDomain = parse_url($url, PHP_URL_HOST);
        
        // Check if URL domain is in allowed domains
        foreach ($allowedDomains as $domain) {
            if ($urlDomain === $domain || str_ends_with($urlDomain, '.' . $domain)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Resolve relative URL to absolute
     */
    private function resolveUrl(string $url, string $baseUrl): ?string
    {
        // Already absolute
        if (parse_url($url, PHP_URL_SCHEME)) {
            return $url;
        }
        
        // Protocol relative
        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
            return $scheme . ':' . $url;
        }
        
        // Absolute path
        if (str_starts_with($url, '/')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
            $host = parse_url($baseUrl, PHP_URL_HOST);
            $port = parse_url($baseUrl, PHP_URL_PORT);
            return $scheme . '://' . $host . ($port ? ':' . $port : '') . $url;
        }
        
        // Relative path
        $basePath = dirname(parse_url($baseUrl, PHP_URL_PATH));
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        
        return $scheme . '://' . $host . ($port ? ':' . $port : '') . rtrim($basePath, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Get base URL from full URL
     */
    private function getBaseUrl(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['scheme'] . '://' . $parsed['host'] . 
               (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    }

    /**
     * Validate URL format
     */
    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false &&
               in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https']);
    }

    /**
     * Add URL to crawl queue
     */
    private function addToQueue(int $projectId, string $url, int $depth, ?string $parentUrl, int $siteId): void
    {
        // Implementation would add to crawl_queue table
        // This is a simplified version
    }

    /**
     * Get next URL from queue
     */
    private function getNextUrlFromQueue(int $projectId): ?array
    {
        // Implementation would fetch from crawl_queue table
        // This is a simplified version
        return null;
    }

    /**
     * Check if URL is already in queue
     */
    private function isUrlInQueue(string $url, int $projectId): bool
    {
        // Implementation would check crawl_queue table
        return false;
    }

    /**
     * Update queue status
     */
    private function updateQueueStatus(int $queueId, string $status, ?string $error = null): void
    {
        // Implementation would update crawl_queue table
    }

    /**
     * Get crawl statistics
     */
    public function getCrawlStatistics(int $siteId = null): array
    {
        return $this->siteModel->getSiteStats($siteId);
    }
}