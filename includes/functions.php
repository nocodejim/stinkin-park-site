<?php
// includes/functions.php
require_once 'config.php';

/**
 * Establishes a connection to the database using PDO
 * 
 * @return PDO The database connection object
 * @throws PDOException if connection fails
 */
function get_db_connection(): PDO {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new PDOException("Failed to connect to the database. Please try again later.");
    }
}

/**
 * Execute a prepared SQL query with parameters
 * 
 * @param PDO $pdo The database connection
 * @param string $sql The SQL query to execute
 * @param array $params Parameters to bind to the query
 * @return PDOStatement The executed statement
 * @throws PDOException if query fails
 */
function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
        throw new PDOException("Database query failed. Please try again later.");
    }
}

/**
 * Get all tags from the database
 * 
 * @param PDO $pdo The database connection
 * @return array List of all tags with their properties
 */
function get_all_tags(PDO $pdo): array {
    return db_query($pdo, "SELECT * FROM tags ORDER BY category, name")->fetchAll();
}

/**
 * Get tag rules for a specific station
 * 
 * @param PDO $pdo The database connection
 * @param int $station_id The ID of the station
 * @return array Associative array of tag_id => requirement_type
 */
function get_station_tag_rules(PDO $pdo, int $station_id): array {
    $stmt = db_query($pdo, "SELECT tag_id, requirement_type FROM station_tags WHERE station_id = ?", [$station_id]);
    return array_column($stmt->fetchAll(), 'requirement_type', 'tag_id');
}

/**
 * Escape HTML entities in a string
 * 
 * @param string|null $string The string to escape
 * @return string The escaped string
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
