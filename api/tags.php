<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Database;
use StinkinPark\Logger;

$logger = Logger::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $response = ['success' => false];
    
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';
            
            switch ($action) {
                case 'list':
                    $category = $_GET['category'] ?? '';
                    $withUsage = $_GET['with_usage'] ?? false;
                    
                    if ($withUsage) {
                        $sql = "
                            SELECT 
                                t.*,
                                COUNT(st.song_id) as usage_count,
                                GROUP_CONCAT(DISTINCT s.title ORDER BY s.title SEPARATOR ', ') as sample_songs
                            FROM tags t
                            LEFT JOIN song_tags st ON t.id = st.tag_id
                            LEFT JOIN songs s ON st.song_id = s.id
                        ";
                        
                        $params = [];
                        if ($category) {
                            $sql .= " WHERE t.category = ?";
                            $params[] = $category;
                        }
                        
                        $sql .= " GROUP BY t.id ORDER BY t.category, t.display_order, t.name";
                        
                        $tags = Database::execute($sql, $params)->fetchAll();
                    } else {
                        $sql = "SELECT * FROM tags";
                        $params = [];
                        
                        if ($category) {
                            $sql .= " WHERE category = ?";
                            $params[] = $category;
                        }
                        
                        $sql .= " ORDER BY category, display_order, name";
                        
                        $tags = Database::execute($sql, $params)->fetchAll();
                    }
                    
                    $response = [
                        'success' => true,
                        'tags' => $tags
                    ];
                    break;
                    
                case 'categories':
                    $categories = Database::execute("
                        SELECT 
                            category,
                            COUNT(*) as tag_count,
                            SUM((SELECT COUNT(*) FROM song_tags WHERE tag_id = tags.id)) as total_usage
                        FROM tags 
                        GROUP BY category 
                        ORDER BY category
                    ")->fetchAll();
                    
                    $response = [
                        'success' => true,
                        'categories' => $categories
                    ];
                    break;
                    
                case 'stats':
                    $stats = [
                        'total_tags' => Database::execute("SELECT COUNT(*) as count FROM tags")->fetch()['count'],
                        'categories' => Database::execute("SELECT COUNT(DISTINCT category) as count FROM tags")->fetch()['count'],
                        'most_used' => Database::execute("
                            SELECT t.name, COUNT(st.song_id) as usage_count
                            FROM tags t
                            LEFT JOIN song_tags st ON t.id = st.tag_id
                            GROUP BY t.id
                            ORDER BY usage_count DESC
                            LIMIT 5
                        ")->fetchAll(),
                        'unused_tags' => Database::execute("
                            SELECT COUNT(*) as count
                            FROM tags t
                            LEFT JOIN song_tags st ON t.id = st.tag_id
                            WHERE st.tag_id IS NULL
                        ")->fetch()['count']
                    ];
                    
                    $response = [
                        'success' => true,
                        'statistics' => $stats
                    ];
                    break;
                    
                case 'search':
                    $query = $_GET['query'] ?? '';
                    if (empty($query)) {
                        throw new Exception('Search query required');
                    }
                    
                    $tags = Database::execute("
                        SELECT * FROM tags 
                        WHERE name LIKE ? OR slug LIKE ?
                        ORDER BY name
                        LIMIT 20
                    ", ["%$query%", "%$query%"])->fetchAll();
                    
                    $response = [
                        'success' => true,
                        'tags' => $tags
                    ];
                    break;
                    
                default:
                    throw new Exception('Unknown action');
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            
            switch ($action) {
                case 'create':
                    $name = trim($input['name'] ?? '');
                    $category = trim($input['category'] ?? '');
                    $displayOrder = (int)($input['display_order'] ?? 0);
                    
                    if (empty($name) || empty($category)) {
                        throw new Exception('Name and category are required');
                    }
                    
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
                    
                    $sql = "INSERT INTO tags (name, category, slug, display_order) VALUES (?, ?, ?, ?)";
                    Database::execute($sql, [$name, $category, $slug, $displayOrder]);
                    
                    $tagId = Database::lastInsertId();
                    
                    $response = [
                        'success' => true,
                        'tag_id' => $tagId,
                        'message' => 'Tag created successfully'
                    ];
                    break;
                    
                case 'bulk_reorder':
                    $updates = $input['updates'] ?? [];
                    
                    if (empty($updates)) {
                        throw new Exception('No updates specified');
                    }
                    
                    Database::beginTransaction();
                    
                    foreach ($updates as $update) {
                        Database::execute("UPDATE tags SET display_order = ? WHERE id = ?", [
                            (int)$update['order'],
                            (int)$update['id']
                        ]);
                    }
                    
                    Database::commit();
                    
                    $response = [
                        'success' => true,
                        'message' => 'Tag order updated successfully'
                    ];
                    break;
                    
                case 'merge':
                    $sourceTagId = (int)($input['source_tag_id'] ?? 0);
                    $targetTagId = (int)($input['target_tag_id'] ?? 0);
                    
                    if ($sourceTagId === 0 || $targetTagId === 0) {
                        throw new Exception('Source and target tag IDs required');
                    }
                    
                    if ($sourceTagId === $targetTagId) {
                        throw new Exception('Cannot merge tag with itself');
                    }
                    
                    Database::beginTransaction();
                    
                    // Update all song_tags references
                    Database::execute("UPDATE IGNORE song_tags SET tag_id = ? WHERE tag_id = ?", [$targetTagId, $sourceTagId]);
                    
                    // Delete any duplicate relationships
                    Database::execute("DELETE FROM song_tags WHERE tag_id = ?", [$sourceTagId]);
                    
                    // Delete the source tag
                    Database::execute("DELETE FROM tags WHERE id = ?", [$sourceTagId]);
                    
                    Database::commit();
                    
                    $response = [
                        'success' => true,
                        'message' => 'Tags merged successfully'
                    ];
                    break;
                    
                default:
                    throw new Exception('Unknown action');
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
            
            if ($id === 0) {
                throw new Exception('Tag ID required');
            }
            
            $name = trim($input['name'] ?? '');
            $category = trim($input['category'] ?? '');
            $displayOrder = (int)($input['display_order'] ?? 0);
            
            if (empty($name) || empty($category)) {
                throw new Exception('Name and category are required');
            }
            
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            
            Database::execute("UPDATE tags SET name = ?, category = ?, slug = ?, display_order = ? WHERE id = ?", [
                $name, $category, $slug, $displayOrder, $id
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Tag updated successfully'
            ];
            break;
            
        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id === 0) {
                throw new Exception('Tag ID required');
            }
            
            // Check if tag is in use
            $usageCount = Database::execute("SELECT COUNT(*) as count FROM song_tags WHERE tag_id = ?", [$id])->fetch();
            
            if ($usageCount['count'] > 0) {
                throw new Exception("Cannot delete tag: it is used by {$usageCount['count']} song(s)");
            }
            
            Database::execute("DELETE FROM tags WHERE id = ?", [$id]);
            
            $response = [
                'success' => true,
                'message' => 'Tag deleted successfully'
            ];
            break;
            
        default:
            http_response_code(405);
            throw new Exception('Method not allowed');
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    $logger->error('Tags API error', [
        'method' => $method,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 'API');
}
