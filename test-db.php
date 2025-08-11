<?php
require_once __DIR__ . '/config/database.php';
use StinkinPark\Database;

try {
    // Test connection
    $pdo = Database::getConnection();
    echo "✓ Database connected successfully<br>\n";
        
    // Test tables exist
    $tables = Database::execute("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Found " . count($tables) . " tables<br>\n";
        
    // Test tag count
    $tagCount = Database::execute("SELECT COUNT(*) as count FROM tags")->fetch();
    echo "✓ Loaded " . $tagCount['count'] . " tags<br>\n";
        
    echo "<br><strong>Database ready for use!</strong>";
    } catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
