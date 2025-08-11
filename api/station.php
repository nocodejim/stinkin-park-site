<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Station.php';

use StinkinPark\Station;

try {
    // Get station slug from request
    $slug = $_GET['slug'] ?? null;
    
    if (!$slug) {
        throw new Exception("Station slug required");
    }
    
    $station = new Station();
    $stationData = $station->getBySlug($slug);
    
    if (!$stationData) {
        http_response_code(404);
        echo json_encode(['error' => 'Station not found']);
        exit;
    }
    
    // Get songs for this station
    $songs = $station->getStationSongs($stationData['id']);
    
    // Format response
    $response = [
        'station' => [
            'id' => $stationData['id'],
            'name' => $stationData['name'],
            'description' => $stationData['description'],
            'background_video' => $stationData['background_video'],
            'background_image' => $stationData['background_image']
        ],
        'songs' => array_map(function($song) {
            return [
                'id' => $song['id'],
                'title' => $song['title'],
                'filename' => $song['filename'],
                'duration' => $song['duration']
            ];
        }, $songs),
        'total_songs' => count($songs)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
