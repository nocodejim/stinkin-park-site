<?php
declare(strict_types=1);

// Enable all error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for testing

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Station.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Station;
use StinkinPark\Logger;

$logger = Logger::getInstance();
$debugInfo = [];
$startTime = microtime(true);

try {
    // Log API request start
    $logger->info("Station API request started", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'query_params' => $_GET
    ], 'API');
    
    $debugInfo[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'API request initiated',
        'params' => $_GET
    ];

    // Get station slug from request
    $slug = $_GET['slug'] ?? null;
    
    $logger->debug("Processing station request", ['slug' => $slug], 'API');
    $debugInfo[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Slug parameter extracted',
        'slug' => $slug
    ];
    
    if (!$slug) {
        $logger->warning("Station slug not provided in request", [], 'API');
        throw new Exception("Station slug required");
    }
    
    // Validate slug format
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $logger->warning("Invalid slug format", ['slug' => $slug], 'API');
        throw new Exception("Invalid station slug format");
    }
    
    // Initialize Station class
    $station = new Station();
    $logger->debug("Station class initialized", [], 'API');
    
    // Fetch station data
    $logger->debug("Fetching station data", ['slug' => $slug], 'API');
    $stationData = $station->getBySlug($slug);
    
    $debugInfo[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Station data fetched',
        'found' => $stationData ? 'yes' : 'no',
        'station_id' => $stationData['id'] ?? null
    ];

    if (!$stationData) {
        $logger->warning("Station not found", ['slug' => $slug], 'API');
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Station not found',
            'slug' => $slug,
            'debug' => $debugInfo,
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $logger->info("Station found", [
        'station_id' => $stationData['id'],
        'station_name' => $stationData['name']
    ], 'API');
    
    // Get songs for this station
    $logger->debug("Fetching songs for station", ['station_id' => $stationData['id']], 'API');
    
    $songs = $station->getStationSongs($stationData['id']);
    
    $debugInfo[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Songs fetched',
        'count' => count($songs),
        'first_song' => $songs[0]['title'] ?? 'none'
    ];
    
    $logger->info("Songs retrieved", [
        'station_id' => $stationData['id'],
        'song_count' => count($songs)
    ], 'API');

    // Build response
    $response = [
        'success' => true,
        'station' => [
            'id' => $stationData['id'],
            'name' => $stationData['name'],
            'slug' => $stationData['slug'],
            'description' => $stationData['description'],
            'background_video' => $stationData['background_video'],
            'background_image' => $stationData['background_image'],
            'active' => (bool) $stationData['active'],
            'tag_rules' => $stationData['tag_rules'] ?? []
        ],
        'songs' => array_map(function($song) {
            return [
                'id' => (int) $song['id'],
                'title' => $song['title'],
                'filename' => $song['filename'],
                'duration' => $song['duration'] ? (int) $song['duration'] : null,
                'play_count' => (int) $song['play_count']
            ];
        }, $songs),
        'metadata' => [
            'total_songs' => count($songs),
            'station_created' => $stationData['created_at'],
            'station_updated' => $stationData['updated_at']
        ],
        'debug' => $debugInfo,
        'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
    ];
    
    // Log successful response
    $logger->info("Station API response successful", [
        'station_id' => $stationData['id'],
        'song_count' => count($songs),
        'execution_time_ms' => $response['execution_time_ms']
    ], 'API');
    
    // Output response
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $logger->error("Station API error", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], 'API');
    
    $debugInfo[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Exception occurred',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debugInfo,
        'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
    ], JSON_PRETTY_PRINT);
}