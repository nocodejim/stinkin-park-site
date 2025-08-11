<?php
// station.php
require_once 'includes/functions.php';

// Get the slug (Assumes URL rewriting e.g., /stations/rock -> station.php?slug=rock)
$slug = $_GET['slug'] ?? null;

if (!$slug) {
    header("Location: /"); // Redirect to home or a default station
    exit;
}

try {
    $pdo = get_db_connection();
    $sql = "SELECT * FROM stations WHERE slug = ? AND active = TRUE";
    $stmt = db_query($pdo, $sql, [$slug]);
    $station = $stmt->fetch();

    if (!$station) {
        http_response_code(404);
        echo "<h1>404 Not Found</h1><p>The requested station could not be found or is currently inactive.</p>";
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "<h1>500 Internal Server Error</h1><p>A problem occurred while loading the station data. Please try again later.</p>";
    error_log("Error loading station.php for slug: " . $slug . " Error: " . $e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($station['name']); ?> | Stinkin' Park</title>
    <link rel="stylesheet" href="/assets/css/player.css">
</head>
<body>
    <div id="background-container">
        <?php if ($station['background_type'] !== 'default' && $station['background_file']): ?>
            <?php if ($station['background_type'] === 'video'): ?>
                <video class="station-background" autoplay muted loop playsinline>
                    <source src="/assets/media/<?php echo e($station['background_file']); ?>" type="video/mp4">
                </video>
            <?php else: ?>
                <img class="station-background" src="/assets/media/<?php echo e($station['background_file']); ?>" alt="Station Background">
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <header>
        <nav>
            <a href="/">← Home</a>
            <span><?php echo e($station['name']); ?></span>
        </nav>
    </header>

    <main class="player-container">
        <div id="loading-message">Loading <?php echo e($station['name']); ?>...</div>
        <div id="error-message"></div>

        <div id="player-ui" style="display: none;">
            <div class="player-interface">
                <h1 id="current-title">Loading...</h1>
                <p id="current-artist">Stinkin' Park</p>
                
                <audio id="audio-player"></audio>
                
                <div class="controls">
                    <button id="prev-btn" title="Previous">⏮️</button>
                    <button id="play-pause-btn" title="Play/Pause">▶️</button>
                    <button id="next-btn" title="Next">⏭️</button>
                    <div class="volume-control">
                        <label for="volume-slider">🔊</label>
                        <input type="range" id="volume-slider" min="0" max="1" step="0.1" value="1">
                    </div>
                </div>
            </div>

            <div id="playlist-container">
                <h2>Playlist</h2>
                <ul id="playlist"></ul>
            </div>
        </div>
    </main>
    <script src="/assets/js/player.js"></script>
</body>
</html>
