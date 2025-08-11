<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Station.php';

use StinkinPark\Station;

// Get station from URL
$slug = basename($_SERVER['REQUEST_URI']);
if (empty($slug) || $slug === 'stations') {
    header('Location: ' . BASE_URL . '/stations'); // Redirect if no specific station is requested
    exit;
}

$station = new Station();
$stationData = $station->getBySlug($slug);

if (!$stationData) {
    header('HTTP/1.0 404 Not Found');
    echo "Station not found";
    exit;
}
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
            background: #000;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
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
            opacity: 0.4;
        }
        
        /* Player Container */
        .player-container {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
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
        }
        
        .song-artist {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        /* Progress Bar */
        .progress-container {
            margin-bottom: 20px;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
            cursor: pointer;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.1s;
        }
        
        .time-display {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
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
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            padding: 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .control-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .control-btn.main {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
        }
        
        .control-btn.main:hover {
            transform: scale(1.05);
        }
        
        /* Volume Control */
        .volume-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .volume-slider {
            width: 100px;
            -webkit-appearance: none;
            appearance: none;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            outline: none;
        }
        
        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 14px;
            height: 14px;
            background: white;
            border-radius: 50%;
            cursor: pointer;
        }
        
        /* Playlist */
        .playlist {
            max-height: 200px;
            overflow-y: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .playlist-item {
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .playlist-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .playlist-item.active {
            background: rgba(102, 126, 234, 0.2);
        }
        
        .playlist-item-title {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }
        
        .playlist-item-duration {
            color: rgba(255, 255, 255, 0.4);
            font-size: 12px;
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
    <!-- Background Media -->
    <div class="background-media">
        <?php if ($stationData['background_video']): ?>
            <video autoplay muted loop playsinline>
                <source src="<?= BASE_URL ?>/assets/media/<?= htmlspecialchars($stationData['background_video']) ?>" type="video/mp4">
            </video>
        <?php elseif ($stationData['background_image']): ?>
            <img src="<?= BASE_URL ?>/assets/media/<?= htmlspecialchars($stationData['background_image']) ?>" alt="">
        <?php endif; ?>
    </div>

    <!-- Player -->
    <div class="player-container">
        <div class="station-header">
            <h1 class="station-name"><?= htmlspecialchars($stationData['name']) ?></h1>
            <?php if ($stationData['description']): ?>
            <p class="station-description"><?= htmlspecialchars($stationData['description']) ?></p>
            <?php endif; ?>
        </div>

        <div class="now-playing">
            <div class="now-playing-label">Now Playing</div>
            <div class="song-title" id="song-title">Loading...</div>
            <div class="song-artist">Stinkin' Park</div>
        </div>

        <div class="progress-container">
            <div class="progress-bar" id="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            <div class="time-display">
                <span id="current-time">0:00</span>
                <span id="total-time">0:00</span>
            </div>
        </div>

        <div class="controls">
            <button class="control-btn" id="prev-btn" title="Previous">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                </svg>
            </button>
            
            <button class="control-btn main" id="play-btn" title="Play/Pause">
                <svg width="30" height="30" fill="currentColor" viewBox="0 0 24 24" id="play-icon">
                    <path d="M8 5v14l11-7z"/>
                </svg>
                <svg width="30" height="30" fill="currentColor" viewBox="0 0 24 24" id="pause-icon" style="display:none;">
                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                </svg>
            </button>
            
            <button class="control-btn" id="next-btn" title="Next">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                </svg>
            </button>
        </div>

        <div class="volume-control">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
            </svg>
            <input type="range" class="volume-slider" id="volume-slider" min="0" max="100" value="70">
        </div>

        <div class="playlist" id="playlist">
            <div class="loading">Loading playlist...</div>
        </div>
    </div>

    <!-- Audio Element -->
    <audio id="audio-player" preload="metadata"></audio>

    <script>
        class StationPlayer {
            constructor() {
                this.audio = document.getElementById('audio-player');
                this.stationSlug = '<?= $slug ?>';
                this.playlist = [];
                this.currentIndex = 0;
                this.isPlaying = false;
                
                this.initializeControls();
                this.loadStation();
            }
            
            initializeControls() {
                // Play/Pause
                document.getElementById('play-btn').addEventListener('click', () => this.togglePlay());
                
                // Next/Previous
                document.getElementById('next-btn').addEventListener('click', () => this.playNext());
                document.getElementById('prev-btn').addEventListener('click', () => this.playPrevious());
                
                // Volume
                const volumeSlider = document.getElementById('volume-slider');
                volumeSlider.addEventListener('input', (e) => {
                    this.audio.volume = e.target.value / 100;
                });
                
                // Progress bar click
                document.getElementById('progress-bar').addEventListener('click', (e) => {
                    const rect = e.currentTarget.getBoundingClientRect();
                    const percent = (e.clientX - rect.left) / rect.width;
                    this.audio.currentTime = percent * this.audio.duration;
                });
                
                // Audio events
                this.audio.addEventListener('timeupdate', () => this.updateProgress());
                this.audio.addEventListener('ended', () => this.playNext());
                this.audio.addEventListener('loadedmetadata', () => this.updateTimeDisplay());
            }
            
            async loadStation() {
                try {
                    const response = await fetch(`<?= BASE_URL ?>/api/station.php?slug=${this.stationSlug}`);
                    const data = await response.json();
                    
                    this.playlist = data.songs;
                    this.shufflePlaylist();
                    this.renderPlaylist();
                    
                    if (this.playlist.length > 0) {
                        this.loadSong(0);
                        // Auto-play on load
                        setTimeout(() => this.play(), 500);
                    }
                } catch (error) {
                    console.error('Failed to load station:', error);
                    document.getElementById('playlist').innerHTML = 
                        '<div class="loading">Failed to load playlist</div>';
                }
            }
            
            shufflePlaylist() {
                for (let i = this.playlist.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [this.playlist[i], this.playlist[j]] = [this.playlist[j], this.playlist[i]];
                }
            }
            
            renderPlaylist() {
                const container = document.getElementById('playlist');
                
                if (this.playlist.length === 0) {
                    container.innerHTML = '<div class="loading">No songs available</div>';
                    return;
                }
                
                container.innerHTML = this.playlist.map((song, index) => `
                    <div class="playlist-item ${index === 0 ? 'active' : ''}" 
                         data-index="${index}">
                        <span class="playlist-item-title">${this.escapeHtml(song.title)}</span>
                        <span class="playlist-item-duration">
                        </span>
                    </div>
                `).join('');
                
                // Add click handlers
                container.querySelectorAll('.playlist-item').forEach(item => {
                    item.addEventListener('click', () => {
                        this.play();
                    });
                });
            }
            
            loadSong(index) {
                const song = this.playlist[index];
                if (!song) return;
                
                this.currentIndex = index;
                this.audio.src = `<?= BASE_URL ?>/audio/${encodeURIComponent(song.filename)}`;
                
                // Update UI
                document.getElementById('song-title').textContent = song.title;
                
                // Update playlist active state
                document.querySelectorAll('.playlist-item').forEach((item, i) => {
                    item.classList.toggle('active', i === index);
                });
            }
            
            togglePlay() {
                if (this.isPlaying) {
                    this.pause();
                } else {
                    this.play();
                }
            }
            
            play() {
                this.audio.play();
                this.isPlaying = true;
                document.getElementById('play-icon').style.display = 'none';
                document.getElementById('pause-icon').style.display = 'block';
            }
            
            pause() {
                this.audio.pause();
                this.isPlaying = false;
                document.getElementById('play-icon').style.display = 'block';
                document.getElementById('pause-icon').style.display = 'none';
            }
            
            playNext() {
                this.currentIndex = (this.currentIndex + 1) % this.playlist.length;
                this.loadSong(this.currentIndex);
                if (this.isPlaying) this.play();
            }
            
            playPrevious() {
                this.currentIndex = this.currentIndex === 0 ? 
                    this.playlist.length - 1 : this.currentIndex - 1;
                this.loadSong(this.currentIndex);
                if (this.isPlaying) this.play();
            }
            
            updateProgress() {
                const percent = (this.audio.currentTime / this.audio.duration) * 100;
                document.getElementById('progress-fill').style.width = percent + '%';
                
                document.getElementById('current-time').textContent = 
                    this.formatTime(this.audio.currentTime);
                document.getElementById('total-time').textContent = 
                    this.formatTime(this.audio.duration);
            }
            
            formatTime(seconds) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = Math.floor(seconds % 60);
                return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
            }
            
            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }
        
        // Initialize player
        const player = new StationPlayer();
    </script>
</body>
</html>
