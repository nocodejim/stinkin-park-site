<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Station.php';

use StinkinPark\Station;

// Get station from URL path
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = parse_url(BASE_URL, PHP_URL_PATH);
if (substr($requestUri, 0, strlen($basePath)) == $basePath) {
    $requestUri = substr($requestUri, strlen($basePath));
}
$pathParts = explode('/', trim($requestUri, '/'));
$slug = end($pathParts);

if (empty($slug) || $slug === 'stations') {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$station = new Station();
$stationData = $station->getBySlug($slug);

if (!$stationData) {
    header('HTTP/1.0 404 Not Found');
    echo "<h1>Station not found</h1>";
    echo "<p>The station '$slug' does not exist.</p>";
    echo "<a href='" . BASE_URL . "/'>Return to stations</a>";
    exit;
}

// Get initial song count for this station
$songs = $station->getStationSongs($stationData['id']);
$songCount = count($songs);
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
        
        /* Audio Element - Visible for debugging */
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
        
        .control-btn:hover {
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
        
        .control-btn.main:hover {
            transform: scale(1.05);
        }
        
        .control-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Mobile Responsive */
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="nav-header">
        <a href="<?= BASE_URL ?>/">← Back to Stations</a>
    </nav>

    <!-- Background Media -->
    <?php if ($stationData['background_video'] || $stationData['background_image']): ?>
    <div class="background-media">
        <?php if ($stationData['background_video']): ?>
            <video autoplay muted loop playsinline>
                <source src="<?= BASE_URL ?>/assets/media/<?= htmlspecialchars($stationData['background_video']) ?>" type="video/mp4">
            </video>
        <?php elseif ($stationData['background_image']): ?>
            <img src="<?= BASE_URL ?>/assets/media/<?= htmlspecialchars($stationData['background_image']) ?>" alt="">
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Player -->
    <div class="player-container">
        <div class="station-header">
            <h1 class="station-name"><?= htmlspecialchars($stationData['name']) ?></h1>
            <?php if ($stationData['description']): ?>
            <p class="station-description"><?= htmlspecialchars($stationData['description']) ?></p>
            <?php endif; ?>
            <p class="station-stats">Station has <?= $songCount ?> songs</p>
        </div>

        <div id="status-container"></div>

        <div class="now-playing">
            <div class="now-playing-label">Now Playing</div>
            <div class="song-title" id="song-title">Loading station...</div>
            <div class="song-artist">Stinkin' Park</div>
        </div>

        <!-- HTML5 Audio Element (visible for debugging) -->
        <audio id="audio-player" controls preload="metadata"></audio>

        <div class="controls">
            <button class="control-btn" id="prev-btn" title="Previous" disabled>
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                </svg>
            </button>
            
            <button class="control-btn main" id="play-btn" title="Play/Pause" disabled>
                <svg width="30" height="30" fill="currentColor" viewBox="0 0 24 24" id="play-icon">
                    <path d="M8 5v14l11-7z"/>
                </svg>
                <svg width="30" height="30" fill="currentColor" viewBox="0 0 24 24" id="pause-icon" style="display:none;">
                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                </svg>
            </button>
            
            <button class="control-btn" id="next-btn" title="Next" disabled>
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                </svg>
            </button>
        </div>

        <div class="playlist" id="playlist">
            <div class="playlist-header">
                <span class="playlist-title">Playlist</span>
                <button class="shuffle-btn" id="shuffle-btn">🔀 Shuffle</button>
            </div>
            <div id="playlist-content">
                <div class="loading">Loading playlist...</div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?= BASE_URL ?>';

        class StationPlayer {
            constructor() {
                this.audio = document.getElementById('audio-player');
                this.stationSlug = '<?= $slug ?>';
                this.playlist = [];
                this.currentIndex = 0;
                
                // Bind controls
                this.playBtn = document.getElementById('play-btn');
                this.prevBtn = document.getElementById('prev-btn');
                this.nextBtn = document.getElementById('next-btn');
                this.shuffleBtn = document.getElementById('shuffle-btn');
                
                this.initializeControls();
                this.loadStation();
            }
            
            showStatus(message, type = 'info') {
                const container = document.getElementById('status-container');
                container.innerHTML = `<div class="status-message ${type}">${message}</div>`;
                if (type !== 'error') {
                    setTimeout(() => {
                        container.innerHTML = '';
                    }, 3000);
                }
            }
            
            initializeControls() {
                // Play/Pause button
                this.playBtn.addEventListener('click', () => {
                    if (this.audio.paused) {
                        this.audio.play();
                    } else {
                        this.audio.pause();
                    }
                });
                
                // Update UI when audio plays/pauses
                this.audio.addEventListener('play', () => {
                    document.getElementById('play-icon').style.display = 'none';
                    document.getElementById('pause-icon').style.display = 'block';
                });
                
                this.audio.addEventListener('pause', () => {
                    document.getElementById('play-icon').style.display = 'block';
                    document.getElementById('pause-icon').style.display = 'none';
                });
                
                // Next/Previous buttons
                this.nextBtn.addEventListener('click', () => this.playNext());
                this.prevBtn.addEventListener('click', () => this.playPrevious());
                
                // Shuffle button
                this.shuffleBtn.addEventListener('click', () => {
                    this.shufflePlaylist();
                    this.showStatus('Playlist shuffled!', 'info');
                });
                
                // Auto-play next song when current ends
                this.audio.addEventListener('ended', () => this.playNext());
                
                // Handle audio errors
                this.audio.addEventListener('error', (e) => {
                    console.error('Audio error:', e);
                    this.showStatus('Error loading audio file. Skipping to next track...', 'error');
                    setTimeout(() => this.playNext(), 2000);
                });
            }
            
            async loadStation() {
                try {
                    this.showStatus('Loading station...', 'loading');
                    
                    const response = await fetch(`${BASE_URL}/api/station.php?slug=${this.stationSlug}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (!data.songs || data.songs.length === 0) {
                        this.showStatus('No songs found for this station', 'error');
                        document.getElementById('playlist-content').innerHTML = 
                            '<div class="loading">No songs available</div>';
                        return;
                    }
                    
                    this.playlist = data.songs;
                    this.shufflePlaylist();
                    this.renderPlaylist();
                    
                    // Load first song
                    this.loadSong(0);
                    
                    // Enable controls
                    this.playBtn.disabled = false;
                    this.nextBtn.disabled = false;
                    this.prevBtn.disabled = false;
                    
                    // Auto-play after short delay
                    setTimeout(() => {
                        this.audio.play().catch(e => {
                            console.log('Auto-play prevented by browser:', e);
                            this.showStatus('Click play to start', 'info');
                        });
                    }, 500);
                    
                } catch (error) {
                    console.error('Failed to load station:', error);
                    this.showStatus('Failed to load station: ' + error.message, 'error');
                    document.getElementById('playlist-content').innerHTML = 
                        '<div class="loading">Failed to load playlist</div>';
                }
            }
            
            shufflePlaylist() {
                // Fisher-Yates shuffle
                for (let i = this.playlist.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [this.playlist[i], this.playlist[j]] = [this.playlist[j], this.playlist[i]];
                }
                this.renderPlaylist();
                if (this.playlist.length > 0) {
                    this.loadSong(0);
                }
            }
            
            renderPlaylist() {
                const container = document.getElementById('playlist-content');
                
                if (this.playlist.length === 0) {
                    container.innerHTML = '<div class="loading">No songs available</div>';
                    return;
                }
                
                container.innerHTML = this.playlist.map((song, index) => `
                    <div class="playlist-item ${index === this.currentIndex ? 'active' : ''}" 
                         data-index="${index}">
                        <span class="playlist-item-number">${index + 1}</span>
                        <span class="playlist-item-title">${this.escapeHtml(song.title)}</span>
                    </div>
                `).join('');
                
                // Add click handlers to playlist items
                container.querySelectorAll('.playlist-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const index = parseInt(item.dataset.index);
                        this.loadSong(index);
                        this.audio.play();
                    });
                });
            }
            
            loadSong(index) {
                if (index < 0 || index >= this.playlist.length) {
                    console.error('Invalid song index:', index);
                    return;
                }
                
                const song = this.playlist[index];
                this.currentIndex = index;
                
                // Update audio source
                const audioUrl = `${BASE_URL}/audio/${encodeURIComponent(song.filename)}`;
                console.log('Loading song:', song.title, 'from', audioUrl);
                this.audio.src = audioUrl;
                
                // Update UI
                document.getElementById('song-title').textContent = song.title;
                