// assets/js/player.js
class MusicPlayer {
    constructor() {
        this.audio = document.getElementById('audio-player');
        this.stationSlug = document.body.dataset.stationSlug;
        this.autoPlayRequested = document.body.dataset.autoPlay === 'true';
        
        this.playlist = [];
        this.currentIndex = 0;
        this.isPlaying = false;

        // UI Elements
        this.loadingMessage = document.getElementById('loading-message');
        this.playerUI = document.getElementById('player-ui');
        this.errorMessage = document.getElementById('error-message');
        this.currentTitleEl = document.getElementById('current-title');
        this.currentArtistEl = document.getElementById('current-artist');
        this.playlistEl = document.getElementById('playlist');
        this.playPauseBtn = document.getElementById('play-pause-btn');
        this.volumeSlider = document.getElementById('volume-slider');

        this.init();
    }

    async init() {
        this.setupEventListeners();
        if (this.stationSlug) {
            await this.loadStation();
        }
    }

    setupEventListeners() {
        this.playPauseBtn.addEventListener('click', () => this.togglePlay());
        document.getElementById('next-btn').addEventListener('click', () => this.playNext(true));
        document.getElementById('prev-btn').addEventListener('click', () => this.playPrevious(true));
        this.volumeSlider.addEventListener('input', (e) => this.audio.volume = parseFloat(e.target.value));
        
        // Auto-continue when track ends
        this.audio.addEventListener('ended', () => this.playNext(true)); 
        this.audio.addEventListener('play', () => {
            this.updatePlayPauseButton();
        });
        this.audio.addEventListener('pause', () => {
            this.updatePlayPauseButton();
        });
        this.audio.addEventListener('error', (e) => this.handleAudioError(e));
    }

    async loadStation() {
        this.setLoading(true);
        try {
            const response = await fetch(`/api/station.php?slug=${encodeURIComponent(this.stationSlug)}`);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to load station data');
            }

            if (data.playlist.length === 0) {
                this.setLoading(false, false, true);
                return;
            }

            this.playlist = data.playlist;
            this.renderPlaylist();
            await this.loadSong(0);
            this.setLoading(false);

            if (this.autoPlayRequested) {
                this.attemptAutoPlay();
            }
        } catch (error) {
            this.displayError('Unable to load station data. Please try again.');
            this.setLoading(false, true);
        }
    }

    attemptAutoPlay() {
        this.audio.play().catch(error => {
            console.warn("Autoplay prevented by browser policy.", error);
            this.displayError("Autoplay blocked. Click 'Play' to start.");
            this.updatePlayPauseButton(); 
        });
    }

    renderPlaylist() {
        this.playlistEl.innerHTML = '';
        this.playlist.forEach((song, index) => {
            const li = document.createElement('li');
            li.textContent = `${song.title} - ${song.artist}`;
            li.addEventListener('click', () => this.playFromPlaylist(index));
            this.playlistEl.appendChild(li);
        });
    }

    async loadSong(index) {
        this.currentIndex = index;
        const song = this.playlist[index];
        if (!song) {
            this.currentTitleEl.textContent = 'No song selected';
            this.currentArtistEl.textContent = 'Stinkin\' Park';
            document.title = 'Stinkin\' Park';
            this.audio.src = '';
            this.updatePlayPauseButton();
            return;
        }

        this.audio.src = `/audio/${encodeURIComponent(song.filename)}`;
        this.currentTitleEl.textContent = song.title;
        this.currentArtistEl.textContent = song.artist || 'Stinkin\' Park';
        document.title = `${song.title} | Stinkin' Park`;
        this.highlightCurrentTrack();
    }

    async playFromPlaylist(index) {
        if (this.currentIndex !== index || this.audio.paused) {
            await this.loadSong(index);
            this.play();
        }
    }

    async togglePlay() {
        if (this.isPlaying) {
            this.audio.pause();
        } else {
            this.play();
        }
    }

    async play() {
        try {
            await this.audio.play();
        } catch (error) {
            console.error("Playback failed:", error);
            this.displayError("Playback failed. Browser may require user interaction.");
        }
    }

    async playNext(forcePlay = false) {
        if (this.playlist.length === 0) return;
        const nextIndex = (this.currentIndex + 1) % this.playlist.length;
        await this.loadSong(nextIndex);
        if (this.isPlaying || forcePlay) this.play();
    }

    async playPrevious(forcePlay = false) {
        if (this.playlist.length === 0) return;
        const prevIndex = (this.currentIndex - 1 + this.playlist.length) % this.playlist.length;
        await this.loadSong(prevIndex);
        if (this.isPlaying || forcePlay) this.play();
    }

    highlightCurrentTrack() {
        const items = this.playlistEl.querySelectorAll('li');
        items.forEach((item, index) => {
            if (index === this.currentIndex) {
                item.classList.add('active');
                item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }

    updatePlayPauseButton() {
        this.isPlaying = !this.audio.paused;
        this.playPauseBtn.textContent = this.isPlaying ? '⏸️' : '▶️';
    }

    handleAudioError(event) {
        console.error('Audio playback error:', event.target.error);
        let errorMessage = 'Playback error.';
        switch (event.target.error.code) {
            case event.target.error.MEDIA_ERR_ABORTED:
                errorMessage = 'Playback was aborted.';
                break;
            case event.target.error.MEDIA_ERR_NETWORK:
                errorMessage = 'Network error occurred.';
                break;
            case event.target.error.MEDIA_ERR_DECODE:
                errorMessage = 'Audio decoding failed.';
                break;
            case event.target.error.MEDIA_ERR_SRC_NOT_SUPPORTED:
                errorMessage = 'Audio format not supported.';
                break;
            default:
                errorMessage = 'An unknown error occurred.';
        }
        this.displayError(errorMessage + ' Skipping.');
        setTimeout(() => this.playNext(true), 2000);
    }

    displayError(message) {
        this.errorMessage.textContent = message;
        this.errorMessage.style.display = 'block';
        setTimeout(() => {
            this.errorMessage.style.display = 'none';
            this.errorMessage.textContent = '';
        }, 5000);
    }

    setLoading(isLoading, hasError = false, isEmpty = false) {
        if (hasError || isEmpty) {
            this.loadingMessage.style.display = 'block';
            this.playerUI.style.display = 'none';
            if (hasError) this.loadingMessage.textContent = "Error loading station. Please check the slug or try again.";
            if (isEmpty) this.loadingMessage.textContent = "This station currently has no songs matching the criteria.";
            return;
        }
        this.loadingMessage.style.display = isLoading ? 'block' : 'none';
        this.playerUI.style.display = isLoading ? 'none' : 'block';
        if (!isLoading) {
            this.errorMessage.style.display = 'none';
            this.errorMessage.textContent = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('audio-player')) {
        new MusicPlayer();
    }
});
