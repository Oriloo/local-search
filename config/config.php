<?php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

define('APP_NAME', 'Moteur de Recherche Local');
define('BASE_URL', 'http://localhost:8090/local-search/');
define('MAX_CRAWL_DEPTH', 3);
define('USER_AGENT', 'SearchBot/1.0');
define('CRAWL_DELAY', 1);

define('MAX_CONTENT_LENGTH', 1000000);
define('MAX_FILE_SIZE', 50000000);
define('RESULTS_PER_PAGE', 20);

$allowed_content_types = [
    'text/html',
    'text/plain',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'video/mp4',
    'video/webm',
    'video/ogg'
];
