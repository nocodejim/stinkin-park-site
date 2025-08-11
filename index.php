<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Station.php';

use StinkinPark\Station;

$station = new Station();
$stations = $station->getAll(true); // Get only active stations
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stinkin' Park - Music Stations</title>
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
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .logo {
            font-size: 48px;
            font-weight: 900;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: -2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .tagline {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 30px;
        }
        
        .admin-link {
            display: inline-block;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .admin-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .stations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .station-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-decoration: none;
            color: white;
            display: block;
        }
        
        .station-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .station-header {
            padding: 30px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.5), rgba(118, 75, 162, 0.5));
            position: relative;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .station-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .station-description {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.5;
        }
        
        .station-footer {
            padding: 20px 30px;
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .song-count {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .play-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .play-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .no-stations {
            text-align: center;
            padding: 60px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            margin-top: 40px;
        }
        
        .no-stations h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .no-stations p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 30px;
        }
        
        .create-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 25px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .create-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        @media (max-width: 768px) {
            .logo {
                font-size: 36px;
            }
            
            .stations-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1 class="logo">Stinkin' Park</h1>
            <p class="tagline">🎵 Your Personal Music Universe 🎵</p>
            <a href="<?= BASE_URL ?>/admin/upload.php" class="admin-link">📋 Admin Panel</a>
        </header>

        <?php if (empty($stations)): ?>
        <div class="no-stations">
            <h2>🎸 No Stations Yet</h2>
            <p>Let's create your first music station!</p>
            <a href="<?= BASE_URL ?>/admin/stations.php" class="create-btn">Create Station</a>
        </div>
        <?php else: ?>
        <div class="stations-grid">
            <?php foreach ($stations as $stationData): 
                // Get song count for this station
                $songs = $station->getStationSongs($stationData['id']);
                $songCount = count($songs);
            ?>
            <a href="<?= BASE_URL ?>/stations/<?= htmlspecialchars($stationData['slug']) ?>" class="station-card">
                <div class="station-header">
                    <h2 class="station-name"><?= htmlspecialchars($stationData['name']) ?></h2>
                    <?php if ($stationData['description']): ?>
                    <p class="station-description"><?= htmlspecialchars($stationData['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="station-footer">
                    <span class="song-count"><?= $songCount ?> songs</span>
                    <div class="play-btn">
                        <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>