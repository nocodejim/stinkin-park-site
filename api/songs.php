<?php
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
