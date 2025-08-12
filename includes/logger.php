<?php
declare(strict_types=1);

namespace StinkinPark;

use PDO;
use Exception;

/**
 * Comprehensive logging system for debugging and monitoring
 */
class Logger
{
    private const LOG_LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    
    private static ?self $instance = null;
    private string $logFile;
    private bool $enableDatabase = true;
    private bool $enableFile = true;
    private bool $enableConsole = true;
    private int $minLevel;
    
    private function __construct()
    {
        $this->logFile = dirname(__DIR__) . '/logs/app.log';
        $this->minLevel = self::LOG_LEVELS['DEBUG'];
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Create log table if it doesn't exist
        $this->createLogTable();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create database log table if it doesn't exist
     */
    private function createLogTable(): void
    {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS system_logs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    level VARCHAR(20) NOT NULL,
                    category VARCHAR(50) DEFAULT NULL,
                    message TEXT NOT NULL,
                    context JSON DEFAULT NULL,
                    user_agent VARCHAR(255) DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    request_uri VARCHAR(255) DEFAULT NULL,
                    session_id VARCHAR(128) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    
                    INDEX idx_level (level),
                    INDEX idx_category (category),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB
            ";
            
            Database::execute($sql);
        } catch (Exception $e) {
            // If database logging fails, fall back to file only
            $this->enableDatabase = false;
            error_log("Failed to create log table: " . $e->getMessage());
        }
    }
    
    /**
     * Main logging method
     */
    public function log(string $level, string $message, array $context = [], ?string $category = null): void
    {
        $level = strtoupper($level);
        
        // Check if we should log this level
        if (!isset(self::LOG_LEVELS[$level]) || self::LOG_LEVELS[$level] < $this->minLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $requestInfo = $this->getRequestInfo();
        
        // Prepare log entry
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => $context,
            'request_info' => $requestInfo
        ];
        
        // Log to file
        if ($this->enableFile) {
            $this->logToFile($logEntry);
        }
        
        // Log to database
        if ($this->enableDatabase) {
            $this->logToDatabase($logEntry);
        }
        
        // Log to console (for browser debugging)
        if ($this->enableConsole) {
            $this->logToConsole($logEntry);
        }
    }
    
    /**
     * Log to file
     */
    private function logToFile(array $entry): void
    {
        $line = sprintf(
            "[%s] %s [%s]: %s %s\n",
            $entry['timestamp'],
            $entry['level'],
            $entry['category'] ?? 'GENERAL',
            $entry['message'],
            !empty($entry['context']) ? json_encode($entry['context']) : ''
        );
        
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log to database
     */
    private function logToDatabase(array $entry): void
    {
        try {
            $sql = "
                INSERT INTO system_logs 
                (level, category, message, context, user_agent, ip_address, request_uri, session_id)
                VALUES 
                (:level, :category, :message, :context, :user_agent, :ip_address, :request_uri, :session_id)
            ";
            
            Database::execute($sql, [
                ':level' => $entry['level'],
                ':category' => $entry['category'],
                ':message' => $entry['message'],
                ':context' => !empty($entry['context']) ? json_encode($entry['context']) : null,
                ':user_agent' => $entry['request_info']['user_agent'],
                ':ip_address' => $entry['request_info']['ip_address'],
                ':request_uri' => $entry['request_info']['request_uri'],
                ':session_id' => $entry['request_info']['session_id']
            ]);
        } catch (Exception $e) {
            // Silently fail to avoid infinite loop
            error_log("Database logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Output to browser console
     */
    private function logToConsole(array $entry): void
    {
        if (php_sapi_name() === 'cli') {
            return; // Skip console output in CLI mode
        }
        
        $jsMessage = json_encode([
            'level' => $entry['level'],
            'category' => $entry['category'] ?? 'GENERAL',
            'message' => $entry['message'],
            'context' => $entry['context'],
            'timestamp' => $entry['timestamp']
        ]);
        
        echo "<script>
            if (typeof console !== 'undefined') {
                const logData = $jsMessage;
                const style = {
                    'DEBUG': 'color: #888',
                    'INFO': 'color: #2196F3',
                    'WARNING': 'color: #FF9800',
                    'ERROR': 'color: #F44336',
                    'CRITICAL': 'color: #F44336; font-weight: bold'
                };
                console.log(
                    '%c[' + logData.level + '] [' + logData.category + '] ' + logData.message,
                    style[logData.level] || '',
                    logData.context || {}
                );
            }
        </script>\n";
    }
    
    /**
     * Get request information for logging
     */
    private function getRequestInfo(): array
    {
        return [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'session_id' => session_id() ?: null
        ];
    }
    
    // Convenience methods
    public function debug(string $message, array $context = [], ?string $category = null): void
    {
        $this->log('DEBUG', $message, $context, $category);
    }
    
    public function info(string $message, array $context = [], ?string $category = null): void
    {
        $this->log('INFO', $message, $context, $category);
    }
    
    public function warning(string $message, array $context = [], ?string $category = null): void
    {
        $this->log('WARNING', $message, $context, $category);
    }
    
    public function error(string $message, array $context = [], ?string $category = null): void
    {
        $this->log('ERROR', $message, $context, $category);
    }
    
    public function critical(string $message, array $context = [], ?string $category = null): void
    {
        $this->log('CRITICAL', $message, $context, $category);
    }
    
    /**
     * Log SQL queries for debugging
     */
    public function logQuery(string $sql, array $params = [], ?float $executionTime = null): void
    {
        $context = ['params' => $params];
        if ($executionTime !== null) {
            $context['execution_time_ms'] = round($executionTime * 1000, 2);
        }
        
        $this->debug("SQL Query: " . $sql, $context, 'DATABASE');
    }
    
    /**
     * Log API requests/responses
     */
    public function logApiCall(string $endpoint, array $request = [], array $response = []): void
    {
        $this->info("API Call: $endpoint", [
            'request' => $request,
            'response' => $response
        ], 'API');
    }
    
    /**
     * Get recent logs from database
     */
    public function getRecentLogs(int $limit = 100, ?string $level = null, ?string $category = null): array
    {
        try {
            $sql = "SELECT * FROM system_logs WHERE 1=1";
            $params = [];
            
            if ($level) {
                $sql .= " AND level = :level";
                $params[':level'] = $level;
            }
            
            if ($category) {
                $sql .= " AND category = :category";
                $params[':category'] = $category;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT :limit";
            
            $stmt = Database::getConnection()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $this->error("Failed to retrieve logs: " . $e->getMessage(), [], 'SYSTEM');
            return [];
        }
    }
    
    /**
     * Clear old logs
     */
    public function clearOldLogs(int $daysToKeep = 30): int
    {
        try {
            $sql = "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt = Database::execute($sql, [':days' => $daysToKeep]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            $this->error("Failed to clear old logs: " . $e->getMessage(), [], 'SYSTEM');
            return 0;
        }
    }
}

// Global helper functions for easy logging
function logDebug(string $message, array $context = [], ?string $category = null): void
{
    Logger::getInstance()->debug($message, $context, $category);
}

function logInfo(string $message, array $context = [], ?string $category = null): void
{
    Logger::getInstance()->info($message, $context, $category);
}

function logWarning(string $message, array $context = [], ?string $category = null): void
{
    Logger::getInstance()->warning($message, $context, $category);
}

function logError(string $message, array $context = [], ?string $category = null): void
{
    Logger::getInstance()->error($message, $context, $category);
}

function logCritical(string $message, array $context = [], ?string $category = null): void
{
    Logger::getInstance()->critical($message, $context, $category);
}