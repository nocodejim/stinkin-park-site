<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Station.php';

use StinkinPark\Station;

$debug = [];
try {
    $debug[] = "API: station.php script started.";

    // Get station slug from request
    $slug = $_GET['slug'] ?? null;
    $debug[] = "API: Requested slug: " . ($slug ?? 'Not provided');
    
    if (!$slug) {
        throw new Exception("Station slug required");
    }
    
    $station = new Station();
    $stationData = $station->getBySlug($slug);
    $debug[] = "API: Fetched station data: " . json_encode($stationData);

    if (!$stationData) {
        http_response_code(404);
        $debug[] = "API: Station not found for slug: " . $slug;
        echo json_encode(['error' => 'Station not found', 'debug' => $debug]);
        exit;
    }
    
    // Get songs for this station
    $songs = $station->getStationSongs($stationData['id']);
    $debug[] = "API: Fetched " . count($songs) . " songs for station ID " . $stationData['id'];

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
    
    $response['debug'] = $debug;
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    $debug[] = "API: An exception occurred: " . $e->getMessage();
    $debug[] = "API: Stack trace: " . $e->getTraceAsString();
    echo json_encode(['error' => $e->getMessage(), 'debug' => $debug]);
}
