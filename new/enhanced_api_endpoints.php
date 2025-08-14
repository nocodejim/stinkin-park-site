<?php
// api/songs.php - Enhanced song management API
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Song.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Database;
use StinkinPark\Song;
use StinkinPark\Logger;

$logger = Logger::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $song = new Song();
    $response = ['success' => false];
    
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';
            
            switch ($action) {
                case 'list':
                    $limit = (int)($_GET['limit'] ?? 50);
                    $offset = (int)($_GET['offset'] ?? 0);
                    $search = $_GET['search'] ?? '';
                    $tag = $_GET['tag'] ?? '';
                    $status = $_GET['status'] ?? '';
                    
                    $songs = $song->getFilteredSongs($search, $tag, $status, $limit, $offset);
                    $total = $song->getFilteredCount($search, $tag, $status);
                    
                    $response = [
                        'success' => true,
                        'songs' => $songs,
                        'pagination' => [
                            'total' => $total,
                            'limit' => $limit,
                            'offset' => $offset,
                            'has_more' => ($offset + $limit) < $total
                        ]
                    ];
                    break;
                    
                case 'get':
                    $id = (int)($_GET['id'] ?? 0);
                    if ($id === 0) {
                        throw new Exception('Song ID required');
                    }
                    
                    $songData = $song->getById($id);
                    if (!$songData) {
                        http_response_code(404);
                        throw new Exception('Song not found');
                    }
                    
                    $response = [
                        'success' => true,
                        'song' => $songData
                    ];
                    break;
                    
                case 'stats':
                    $stats = $song->getStatistics();
                    $response = [
                        'success' => true,
                        'statistics' => $stats
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
                case 'bulk_update':
                    $songIds = $input['song_ids'] ?? [];
                    $updates = $input['updates'] ?? [];
                    
                    if (empty($songIds)) {
                        throw new Exception('No songs specified');
                    }
                    
                    $result = $song->bulkUpdate($songIds, $updates);
                    
                    $response = [
                        'success' => true,
                        'updated_count' => $result['updated_count'],
                        'message' => "Updated {$result['updated_count']} songs"
                    ];
                    break;
                    
                case 'bulk_delete':
                    $songIds = $input['song_ids'] ?? [];
                    
                    if (empty($songIds)) {
                        throw new Exception('No songs specified');
                    }
                    
                    $result = $song->bulkDelete($songIds);
                    
                    $response = [
                        'success' => true,
                        'deleted_count' => $result['deleted_count'],
                        'message' => "Deleted {$result['deleted_count']} songs"
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
                throw new Exception('Song ID required');
            }
            
            $updated = $song->update($id, $input);
            
            if (!$updated) {
                throw new Exception('Failed to update song');
            }
            
            $response = [
                'success' => true,
                'message' => 'Song updated successfully'
            ];
            break;
            
        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id === 0) {
                throw new Exception('Song ID required');
            }
            
            $deleted = $song->delete($id);
            
            if (!$deleted) {
                throw new Exception('Failed to delete song');
            }
            
            $response = [
                'success' => true,
                'message' => 'Song deleted successfully'
            ];
            break;
            
        default:
            http_response_code(405);
            throw new Exception('Method not allowed');
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    $logger->error('Songs API error', [
        'method' => $method,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 'API');
}

// Add enhanced Song methods to includes/Song.php:

/*
public function getFilteredSongs(string $search = '', string $tag = '', string $status = '', int $limit = 50, int $offset = 0): array
{
    $sql = "
        SELECT 
            s.*,
            GROUP_CONCAT(DISTINCT t.name ORDER BY t.category, t.name SEPARATOR ', ') as tags,
            GROUP_CONCAT(DISTINCT t.id) as tag_ids
        FROM songs s
        LEFT JOIN song_tags st ON s.id = st.song_id
        LEFT JOIN tags t ON st.tag_id = t.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND s.title LIKE ?";
        $params[] = "%$search%";
    }
    
    if (!empty($tag)) {
        $sql .= " AND s.id IN (
            SELECT DISTINCT song_id 
            FROM song_tags st2 
            JOIN tags t2 ON st2.tag_id = t2.id 
            WHERE t2.name = ?
        )";
        $params[] = $tag;
    }
    
    if ($status === 'active') {
        $sql .= " AND s.active = 1";
    } elseif ($status === 'inactive') {
        $sql .= " AND s.active = 0";
    }
    
    $sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    return Database::execute($sql, $params)->fetchAll();
}

public function getFilteredCount(string $search = '', string $tag = '', string $status = ''): int
{
    $sql = "SELECT COUNT(DISTINCT s.id) as total FROM songs s";
    $params = [];
    
    $conditions = [];
    
    if (!empty($search)) {
        $conditions[] = "s.title LIKE ?";
        $params[] = "%$search%";
    }
    
    if (!empty($tag)) {
        $sql .= " JOIN song_tags st ON s.id = st.song_id JOIN tags t ON st.tag_id = t.id";
        $conditions[] = "t.name = ?";
        $params[] = $tag;
    }
    
    if ($status === 'active') {
        $conditions[] = "s.active = 1";
    } elseif ($status === 'inactive') {
        $conditions[] = "s.active = 0";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    return (int) Database::execute($sql, $params)->fetch()['total'];
}

public function getStatistics(): array
{
    $stats = [];
    
    // Basic counts
    $stats['total_songs'] = Database::execute("SELECT COUNT(*) as count FROM songs")->fetch()['count'];
    $stats['active_songs'] = Database::execute("SELECT COUNT(*) as count FROM songs WHERE active = 1")->fetch()['count'];
    $stats['total_plays'] = Database::execute("SELECT SUM(play_count) as total FROM songs")->fetch()['total'] ?? 0;
    
    // Duration statistics
    $durationStats = Database::execute("
        SELECT 
            AVG(duration) as avg_duration,
            MIN(duration) as min_duration,
            MAX(duration) as max_duration,
            SUM(duration) as total_duration
        FROM songs 
        WHERE duration IS NOT NULL
    ")->fetch();
    
    $stats['duration'] = $durationStats;
    
    // File size statistics
    $sizeStats = Database::execute("
        SELECT 
            AVG(file_size) as avg_size,
            MIN(file_size) as min_size,
            MAX(file_size) as max_size,
            SUM(file_size) as total_size
        FROM songs 
        WHERE file_size IS NOT NULL
    ")->fetch();
    
    $stats['file_size'] = $sizeStats;
    
    // Top tags
    $topTags = Database::execute("
        SELECT t.name, COUNT(st.song_id) as usage_count
        FROM tags t
        JOIN song_tags st ON t.id = st.tag_id
        GROUP BY t.id
        ORDER BY usage_count DESC
        LIMIT 10
    ")->fetchAll();
    
    $stats['top_tags'] = $topTags;
    
    // Recent uploads
    $recentUploads = Database::execute("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM songs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ")->fetchAll();
    
    $stats['recent_uploads'] = $recentUploads;
    
    return $stats;
}

public function bulkUpdate(array $songIds, array $updates): array
{
    Database::beginTransaction();
    
    try {
        $updateCount = 0;
        
        foreach ($songIds as $songId) {
            $songData = [];
            
            if (isset($updates['active'])) {
                $songData['active'] = (int)$updates['active'];
            }
            
            if (isset($updates['title_prefix'])) {
                $currentSong = $this->getById($songId);
                $songData['title'] = $updates['title_prefix'] . $currentSong['title'];
            }
            
            if (isset($updates['title_suffix'])) {
                $currentSong = $this->getById($songId);
                $songData['title'] = $currentSong['title'] . $updates['title_suffix'];
            }
            
            if (!empty($songData)) {
                $this->update($songId, $songData);
                $updateCount++;
            }
            
            // Handle tag operations
            if (!empty($updates['add_tags'])) {
                $currentSong = $this->getById($songId);
                $currentTags = !empty($currentSong['tag_ids']) ? explode(',', $currentSong['tag_ids']) : [];
                $newTags = array_unique(array_merge($currentTags, $updates['add_tags']));
                $this->attachTags($songId, array_map('intval', $newTags));
            }
            
            if (!empty($updates['remove_tags'])) {
                $currentSong = $this->getById($songId);
                $currentTags = !empty($currentSong['tag_ids']) ? explode(',', $currentSong['tag_ids']) : [];
                $newTags = array_diff($currentTags, $updates['remove_tags']);
                $this->attachTags($songId, array_map('intval', $newTags));
            }
            
            if (!empty($updates['replace_tags'])) {
                $this->attachTags($songId, array_map('intval', $updates['replace_tags']));
            }
        }
        
        Database::commit();
        
        return ['updated_count' => $updateCount];
        
    } catch (Exception $e) {
        Database::rollback();
        throw $e;
    }
}

public function bulkDelete(array $songIds): array
{
    $deleteCount = 0;
    
    foreach ($songIds as $songId) {
        if ($this->delete($songId)) {
            $deleteCount++;
        }
    }
    
    return ['deleted_count' => $deleteCount];
}
*/

?>

<?php
// api/tags.php - Comprehensive tag management API
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
?>