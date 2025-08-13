<?php

namespace LocalSearch\Config;

/**
 * Configuration management class
 * Handles environment variables and application configuration
 */
class Configuration
{
    private static $config = [];
    private static $loaded = false;

    /**
     * Load configuration from .env file and set defaults
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Load .env file if it exists
        $envFile = dirname(dirname(__DIR__)) . '/.env';
        if (file_exists($envFile)) {
            self::loadEnvironmentFile($envFile);
        }

        // Set default values
        self::setDefaults();
        self::$loaded = true;
    }

    /**
     * Get configuration value
     */
    public static function get(string $key, $default = null)
    {
        self::load();
        return self::$config[$key] ?? $default;
    }

    /**
     * Set configuration value
     */
    public static function set(string $key, $value): void
    {
        self::$config[$key] = $value;
    }

    /**
     * Load environment file
     */
    private static function loadEnvironmentFile(string $filePath): void
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue; // Skip comments
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                // Set environment variable
                $_ENV[$key] = $value;
                putenv("$key=$value");
                
                self::$config[$key] = $value;
            }
        }
    }

    /**
     * Set default configuration values
     */
    private static function setDefaults(): void
    {
        $defaults = [
            'APP_NAME' => $_ENV['APP_NAME'] ?? 'Moteur de Recherche Local',
            'APP_ENV' => $_ENV['APP_ENV'] ?? 'production',
            'APP_DEBUG' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'APP_URL' => $_ENV['APP_URL'] ?? 'http://localhost',
            
            'DB_CONNECTION' => $_ENV['DB_CONNECTION'] ?? 'mysql',
            'DB_HOST' => $_ENV['DB_HOST'] ?? 'localhost',
            'DB_PORT' => $_ENV['DB_PORT'] ?? '3306',
            'DB_DATABASE' => $_ENV['DB_DATABASE'] ?? 'local_search',
            'DB_USERNAME' => $_ENV['DB_USERNAME'] ?? 'root',
            'DB_PASSWORD' => $_ENV['DB_PASSWORD'] ?? '',
            'DB_CHARSET' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            
            'MAX_CRAWL_DEPTH' => (int)($_ENV['MAX_CRAWL_DEPTH'] ?? 3),
            'USER_AGENT' => $_ENV['USER_AGENT'] ?? 'SearchBot/1.0',
            'CRAWL_DELAY' => (int)($_ENV['CRAWL_DELAY'] ?? 1),
            'MAX_CONTENT_LENGTH' => (int)($_ENV['MAX_CONTENT_LENGTH'] ?? 1000000),
            'MAX_FILE_SIZE' => (int)($_ENV['MAX_FILE_SIZE'] ?? 50000000),
            
            'RESULTS_PER_PAGE' => (int)($_ENV['RESULTS_PER_PAGE'] ?? 20),
            
            'SESSION_LIFETIME' => (int)($_ENV['SESSION_LIFETIME'] ?? 1440),
            'CSRF_TOKEN_NAME' => $_ENV['CSRF_TOKEN_NAME'] ?? '_token',
            
            'CACHE_ENABLED' => filter_var($_ENV['CACHE_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'CACHE_TTL' => (int)($_ENV['CACHE_TTL'] ?? 3600),
            
            'LOG_LEVEL' => $_ENV['LOG_LEVEL'] ?? 'info',
            'LOG_FILE' => $_ENV['LOG_FILE'] ?? 'logs/app.log',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }
    }

    /**
     * Get database configuration array
     */
    public static function getDatabaseConfig(): array
    {
        self::load();
        
        return [
            'connection' => self::get('DB_CONNECTION'),
            'host' => self::get('DB_HOST'),
            'port' => self::get('DB_PORT'),
            'database' => self::get('DB_DATABASE'),
            'username' => self::get('DB_USERNAME'),
            'password' => self::get('DB_PASSWORD'),
            'charset' => self::get('DB_CHARSET'),
        ];
    }
}