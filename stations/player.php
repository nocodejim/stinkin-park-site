<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Station.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Station;
use StinkinPark\Logger;

$logger = Logger::getInstance();

// Get station from URL path
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = parse_url(BASE_URL, PHP_URL_PATH);
if (substr($requestUri, 0, strlen($basePath)) == $basePath) {
    $requestUri = substr($requestUri, strlen($basePath));
}
$pathParts = explode('/', trim($requestUri, '/'));
$slug = end($pathParts);

$logger->info("Player page requested", [
    'request_uri' => $_SERVER['REQUEST_URI'],
    'base_path' => $basePath,
    'slug' => $slug
], 'PLAYER');

if (empty($slug) || $slug === 'stations') {
    $logger->warning("Invalid or empty slug, redirecting to home", ['slug' => $slug], 'PLAYER');
    header('Location: ' . BASE_URL . '/');
    exit;
}

$station = new Station();
$stationData = $station->getBySlug($slug);

if (!$stationData) {
    $logger->error("Station not found", ['slug' => $slug], 'PLAYER');
    header('HTTP/1.0 404 Not Found');
    echo "<h1>Station not found</h1>";
    echo "<p>The station '$slug' does not exist.</p>";
    echo "<a href='" . BASE_URL . "/'>Return to stations</a>";
    exit;
}

$logger->info("Station loaded for player", [
    'station_id' => $stationData['id'],
    'station_name' => $stationData['name']
], 'PLAYER');

// Get initial song count for this station
$songs = $station->getStationSongs($stationData['id']);
$songCount = count($songs);

$logger->info("Initial songs loaded", ['count' => $songCount], 'PLAYER');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($stationData['name']) ?> - Stinkin' Park</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* Debug Console */
        .debug-console {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            max-height: 200px;
            background: rgba(0, 0, 0, 0.9);
            border-top: 2px solid #667eea;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            z-index: 10000;
            transition: all 0.3s;
        }
        
        .debug-console.collapsed {
            max-height: 30px;
        }
        
        .debug-header {
            background: #667eea;
            padding: 5px 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .debug-content {
            padding: 10px;
            line-height: 1.4;
        }
        
        .debug-log {
            margin: 2px 0;
            padding: 2px 5px;
            border-left: 3px solid #444;
        }
        
        .debug-log.info { border-color: #2196F3; }
        .debug-log.success { border-color: #4CAF50; }
        .debug-log.warning { border-color: #FF9800; }
        .debug-log.error { border-color: #F44336; }
        
        /* Background Media */
        .background-media {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .background-media video,
        .background-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.3;
            filter: blur(5px);
        }
        
        /* Navigation */
        .nav-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            z-index: 100;
        }
        
        .nav-header a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .nav-header a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Player Container */
        .player-container {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 60px;
            margin-bottom: 250px; /* Space for debug console */
        }
        
        .station-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .station-name {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .station-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .station-stats {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Now Playing */
        .now-playing {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .now-playing-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 10px;
        }
        
        .song-title {
            font-size: 20px;
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
            min-height: 28px;
        }
        
        .song-artist {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        /* Audio Element */
        #audio-player {
            width: 100%;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        /* Controls */
        .controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .control-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            padding: 12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .control-btn:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        
        .control-btn.main {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
        }
        
        .control-btn.main:hover:not(:disabled) {
            transform: scale(1.05);
        }
        
        .control-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Status Messages */
        .status-message {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .status-message.error {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid rgba(255, 0, 0, 0.3);
        }
        
        .status-message.loading {
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.3);
        }
        
        /* Playlist */
        .playlist {
            max-height: 300px;
            overflow-y: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .playlist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 0 10px;
        }
        
        .playlist-title {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .shuffle-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.7);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .shuffle-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .playlist-item {
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .playlist-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .playlist-item.active {
            background: rgba(102, 126, 234, 0.2);
            border-left: 3px solid #667eea;
        }
        
        .playlist-item-number {
            color: rgba(255, 255, 255, 0.4);
            font-size: 12px;
            margin-right: 10px;
            min-width: 20px;
        }
        
        .playlist-item-title {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            flex: 1;
        }
        
        .playlist-item.active .playlist-item-title {
            color: white;
            font-weight: 600;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: rgba(255, 255, 255, 0.5);
        }
        
        @media (max-width: 600px) {
            .player-container {
                padding: 30px 20px;
            }
            
            .station-name {
                font-size: 24px;
            }
            
            .song-title {
                font-size: 18px;
            }
            
            .controls {
                gap: 15px;
            }
            
            .control-btn.main {
                width: 50px;
                height: 50px;
            }
        }
        