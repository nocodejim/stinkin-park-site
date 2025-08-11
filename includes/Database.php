<?php
declare(strict_types=1);

namespace StinkinPark;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];
        
    /**
     * Initialize database configuration
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }
        
    /**
     * Get database connection (singleton pattern)
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            if (empty(self::$config)) {
                throw new Exception("Database not configured. Call Database::init() first.");
            }
                        try {
                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=utf8mb4",
                    self::$config['host'],
                    self::$config['database']
                );
                                self::$connection = new PDO(
                    $dsn,
                    self::$config['username'],
                    self::$config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]
                );
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Unable to connect to database");
            }
        }
                return self::$connection;
    }
        
    /**
     * Execute a query with automatic error handling
     */
    public static function execute(string $sql, array $params = []): \PDOStatement
    {
        $pdo = self::getConnection();
                try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Database query failed");
        }
    }
        
    /**
     * Get last insert ID
     */
    public static function lastInsertId(): int
    {
        return (int) self::getConnection()->lastInsertId();
    }
        
    /**
     * Begin transaction
     */
    public static function beginTransaction(): void
    {
        self::getConnection()->beginTransaction();
    }
        
    /**
     * Commit transaction
     */
    public static function commit(): void
    {
        self::getConnection()->commit();
    }
        
    /**
     * Rollback transaction
     */
    public static function rollback(): void
    {
        self::getConnection()->rollBack();
    }
}
