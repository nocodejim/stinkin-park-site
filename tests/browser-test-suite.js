// Stinkin' Park Browser Test Suite
// Run this in your browser console on the actual player page
// This will test every aspect of the player and identify exactly where it's failing

(function() {
    'use strict';

    // Configuration - Update these if needed
    const CONFIG = {
        baseUrl: window.location.origin + '/radio',
        testStation: 'test-station',
        verbose: true
    };

    // Test results storage
    const results = {
        passed: [],
        failed: [],
        warnings: []
    };

    // Enhanced console logger
    const TestLogger = {
        group(name) {
            console.group(`🧪 ${name}`);
        },

        endGroup() {
            console.groupEnd();
        },

        success(msg, data) {
            console.log(`✅ ${msg}`, data || '');
            results.passed.push(msg);
        },

        error(msg, data) {
            console.error(`❌ ${msg}`, data || '');
            results.failed.push({ msg, data });
        },

        warning(msg, data) {
            console.warn(`⚠️ ${msg}`, data || '');
            results.warnings.push({ msg, data });
        },

        info(msg, data) {
            if (CONFIG.verbose) {
                console.log(`ℹ️ ${msg}`, data || '');
            }
        },

        debug(msg, data) {
            if (CONFIG.verbose) {
                console.log(`🔍 ${msg}`, data || '');
            }
        }
    };

    // Test 1: Check page elements
    async function testPageElements() {
        TestLogger.group('Page Elements Test');

        const elementsToCheck = [
            { id: 'audio-player', name: 'Audio element', critical: true },
            { id: 'play-btn', name: 'Play button', critical: true },
            { id: 'next-btn', name: 'Next button', critical: true },
            { id: 'prev-btn', name: 'Previous button', critical: true },
            { id: 'song-title', name: 'Song title display', critical: true },
            { id: 'playlist-content', name: 'Playlist container', critical: true },
            { id: 'shuffle-btn', name: 'Shuffle button', critical: false },
            { id: 'status-container', name: 'Status container', critical: false }
        ];

        let allCriticalPresent = true;

        for (const elem of elementsToCheck) {
            const element = document.getElementById(elem.id);
            if (element) {
                TestLogger.success(`Found: ${elem.name}`);
            } else {
                if (elem.critical) {
                    TestLogger.error(`Missing: ${elem.name} (ID: ${elem.id})`);
                    allCriticalPresent = false;
                } else {
                    TestLogger.warning(`Missing: ${elem.name} (ID: ${elem.id})`);
                }
            }
        }

        TestLogger.endGroup();
        return allCriticalPresent;
    }

    // Test 2: Check global variables
    async function testGlobalVariables() {
        TestLogger.group('Global Variables Test');

        // Check for player instance
        if (window.player) {
            TestLogger.success('Player instance found', window.player);

            // Check player properties
            const properties = ['audio', 'playlist', 'currentIndex', 'stationSlug'];
            for (const prop of properties) {
                if (window.player[prop] !== undefined) {
                    TestLogger.success(`Player.${prop} exists`, window.player[prop]);
                } else {
                    TestLogger.warning(`Player.${prop} is undefined`);
                }
            }

            // Check if playlist has songs
            if (window.player.playlist && Array.isArray(window.player.playlist)) {
                TestLogger.info(`Playlist has ${window.player.playlist.length} songs`);
                if (window.player.playlist.length > 0) {
                    TestLogger.success('Playlist populated');
                } else {
                    TestLogger.error('Playlist is empty');
                }
            }
        } else {
            TestLogger.error('No player instance found on window object');
            TestLogger.info('Checking for StationPlayer class...');

            if (typeof StationPlayer !== 'undefined') {
                TestLogger.success('StationPlayer class exists');
                TestLogger.info('Player might not be initialized yet');
            } else {
                TestLogger.error('StationPlayer class not found');
            }
        }

        // Check for BASE_URL
        if (typeof BASE_URL !== 'undefined') {
            TestLogger.success('BASE_URL defined', BASE_URL);
        } else {
            TestLogger.error('BASE_URL not defined');
        }

        TestLogger.endGroup();
        return window.player !== undefined;
    }

    // Test 3: Network requests
    async function testNetworkRequests() {
        TestLogger.group('Network Requests Test');

        const slug = CONFIG.testStation;
        const apiUrl = `${CONFIG.baseUrl}/api/station.php?slug=${slug}`;

        TestLogger.info('Testing API endpoint', apiUrl);

        try {
            // Test with fetch
            const startTime = performance.now();
            const response = await fetch(apiUrl);
            const endTime = performance.now();
            const responseTime = Math.round(endTime - startTime);

            TestLogger.info(`Response time: ${responseTime}ms`);

            if (response.ok) {
                TestLogger.success('API responded with 200 OK');

                // Get response text first
                const text = await response.text();
                TestLogger.debug('Response length', text.length + ' characters');

                // Try to parse JSON
                try {
                    const data = JSON.parse(text);
                    TestLogger.success('JSON parsed successfully');

                    // Validate structure
                    if (data.success === false) {
                        TestLogger.error('API returned success: false', data.error);
                    } else {
                        if (data.songs) {
                            TestLogger.success(`Found ${data.songs.length} songs in response`);

                            // Check first song structure
                            if (data.songs.length > 0) {
                                const firstSong = data.songs[0];
                                const requiredFields = ['id', 'title', 'filename'];
                                for (const field of requiredFields) {
                                    if (firstSong[field]) {
                                        TestLogger.success(`First song has ${field}: ${firstSong[field]}`);
                                    } else {
                                        TestLogger.error(`First song missing ${field}`);
                                    }
                                }
                            }
                        } else {
                            TestLogger.error('No songs array in response');
                        }

                        if (data.station) {
                            TestLogger.success('Station info present', data.station.name);
                        }
                    }

                    window.testAPIData = data; // Store for other tests

                } catch (parseError) {
                    TestLogger.error('JSON parse failed', parseError.message);
                    TestLogger.error('First 500 chars of response:', text.substring(0, 500));

                    // Check if it might be HTML error page
                    if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                        TestLogger.error('Response appears to be HTML, not JSON');
                    }
                }
            } else {
                TestLogger.error(`API responded with ${response.status} ${response.statusText}`);
            }

        } catch (error) {
            TestLogger.error('Fetch failed completely', error.message);
            TestLogger.info('This could indicate CORS issues or network problems');
        }

        TestLogger.endGroup();
        return true;
    }

    // Test 4: Audio capabilities
    async function testAudioCapabilities() {
        TestLogger.group('Audio Capabilities Test');

        const audio = document.getElementById('audio-player');

        if (!audio) {
            TestLogger.error('No audio element found');
            TestLogger.endGroup();
            return false;
        }

        // Test audio format support
        const formats = [
            { type: 'audio/mpeg', name: 'MP3' },
            { type: 'audio/ogg', name: 'OGG' },
            { type: 'audio/wav', name: 'WAV' }
        ];

        for (const format of formats) {
            const canPlay = audio.canPlayType(format.type);
            if (canPlay === 'probably' || canPlay === 'maybe') {
                TestLogger.success(`${format.name} supported: ${canPlay}`);
            } else {
                TestLogger.warning(`${format.name} not supported`);
            }
        }

        // Check audio properties
        TestLogger.info('Audio element properties:', {
            paused: audio.paused,
            muted: audio.muted,
            volume: audio.volume,
            src: audio.src || 'none',
            readyState: audio.readyState,
            networkState: audio.networkState,
            error: audio.error
        });

        // Check for audio error
        if (audio.error) {
            const errorTypes = {
                1: 'MEDIA_ERR_ABORTED',
                2: 'MEDIA_ERR_NETWORK',
                3: 'MEDIA_ERR_DECODE',
                4: 'MEDIA_ERR_SRC_NOT_SUPPORTED'
            };
            TestLogger.error(`Audio has error: ${errorTypes[audio.error.code]}`, audio.error.message);
        }

        // Test loading a song if we have data
        if (window.testAPIData && window.testAPIData.songs && window.testAPIData.songs.length > 0) {
            TestLogger.info('Testing audio loading with first song...');

            const firstSong = window.testAPIData.songs[0];
            const audioUrl = `${CONFIG.baseUrl}/audio/${encodeURIComponent(firstSong.filename)}`;

            TestLogger.debug('Testing audio URL', audioUrl);

            // Set up promise to wait for events
            const loadPromise = new Promise((resolve) => {
                let resolved = false;

                const handleLoad = () => {
                    if (!resolved) {
                        TestLogger.success('Audio load event fired');
                        resolved = true;
                        resolve(true);
                    }
                };

                const handleError = (e) => {
                    if (!resolved) {
                        TestLogger.error('Audio error event fired', {
                            error: audio.error,
                            event: e
                        });
                        resolved = true;
                        resolve(false);
                    }
                };

                audio.addEventListener('loadstart', handleLoad, { once: true });
                audio.addEventListener('error', handleError, { once: true });

                // Timeout after 5 seconds
                setTimeout(() => {
                    if (!resolved) {
                        TestLogger.warning('Audio load timed out after 5 seconds');
                        resolved = true;
                        resolve(false);
                    }
                }, 5000);
            });

            audio.src = audioUrl;
            audio.load();

            await loadPromise;
        }

        TestLogger.endGroup();
        return true;
    }

    // Test 5: Event listeners
    async function testEventListeners() {
        TestLogger.group('Event Listeners Test');

        const audio = document.getElementById('audio-player');
        const playBtn = document.getElementById('play-btn');

        if (!audio || !playBtn) {
            TestLogger.error('Required elements not found');
            TestLogger.endGroup();
            return false;
        }

        // Check if event listeners are attached
        // We'll trigger events and see if they work

        TestLogger.info('Testing play button click...');

        // Store original state
        const wasPaused = audio.paused;

        // Simulate click
        playBtn.click();

        // Wait a moment
        await new Promise(r => setTimeout(r, 100));

        // Check if state changed
        if (wasPaused !== audio.paused) {
            TestLogger.success('Play button click changed audio state');
        } else {
            TestLogger.warning('Play button click did not change audio state');

            // Check if button is disabled
            if (playBtn.disabled) {
                TestLogger.info('Play button is disabled - might be waiting for content to load');
            }
        }

        TestLogger.endGroup();
        return true;
    }

    // Test 6: Console errors
    async function testConsoleErrors() {
        TestLogger.group('Console Errors Check');

        // Intercept console.error temporarily
        const originalError = console.error;
        const errors = [];

        console.error = function(...args) {
            errors.push(args);
            originalError.apply(console, args);
        };

        // Wait a moment to catch any async errors
        await new Promise(r => setTimeout(r, 1000));

        // Restore original console.error
        console.error = originalError;

        if (errors.length > 0) {
            TestLogger.error(`Found ${errors.length} console errors:`, errors);
        } else {
            TestLogger.success('No console errors detected');
        }

        TestLogger.endGroup();
        return errors.length === 0;
    }

    // Test 7: Try to manually initialize player
    async function testManualInitialization() {
        TestLogger.group('Manual Player Initialization Test');

        if (typeof StationPlayer === 'undefined') {
            TestLogger.error('StationPlayer class not available');
            TestLogger.endGroup();
            return false;
        }

        TestLogger.info('Attempting to create new player instance...');

        try {
            const testPlayer = new StationPlayer();
            TestLogger.success('Player instance created successfully');

            // Try to load station
            TestLogger.info('Attempting to load station...');
            await testPlayer.loadStation(CONFIG.testStation);

            TestLogger.success('Station loaded successfully');
            TestLogger.info('Playlist length:', testPlayer.playlist.length);

            window.testPlayer = testPlayer; // Store for debugging

        } catch (error) {
            TestLogger.error('Failed to initialize player', error.message);
            TestLogger.debug('Error stack:', error.stack);
        }

        TestLogger.endGroup();
        return true;
    }

    // Main test runner
    async function runAllTests() {
        console.clear();
        console.log('%c🎵 Stinkin\' Park Player Test Suite 🎵', 'font-size: 20px; color: #667eea; font-weight: bold');
        console.log('=====================================');

        // Run tests
        await testPageElements();
        await testGlobalVariables();
        await testNetworkRequests();
        await testAudioCapabilities();
        await testEventListeners();
        await testConsoleErrors();
        await testManualInitialization();

        // Summary
        console.log('=====================================');
        console.log('%c📊 Test Summary', 'font-size: 16px; font-weight: bold');
        console.log(`✅ Passed: ${results.passed.length}`);
        console.log(`❌ Failed: ${results.failed.length}`);
        console.log(`⚠️ Warnings: ${results.warnings.length}`);

        if (results.failed.length > 0) {
            console.log('\n%c❌ Failed Tests:', 'color: red; font-weight: bold');
            results.failed.forEach(f => console.error(`  - ${f.msg}`, f.data || ''));
        }

        if (results.warnings.length > 0) {
            console.log('\n%c⚠️ Warnings:', 'color: orange; font-weight: bold');
            results.warnings.forEach(w => console.warn(`  - ${w.msg}`, w.data || ''));
        }

        // Diagnostic recommendations
        console.log('\n%c🔧 Diagnostic Recommendations:', 'font-size: 14px; font-weight: bold');

        if (results.failed.some(f => f.msg.includes('player instance'))) {
            console.log('• The player is not initializing. Check if DOMContentLoaded event is firing.');
            console.log('• Try running: document.readyState');
            console.log('• Try manually creating player: new StationPlayer()');
        }

        if (results.failed.some(f => f.msg.includes('API'))) {
            console.log('• API requests are failing. Check network tab for details.');
            console.log('• Verify the API URL is correct: ' + CONFIG.baseUrl + '/api/station.php');
        }

        if (results.failed.some(f => f.msg.includes('audio'))) {
            console.log('• Audio element issues detected. Check if audio files exist.');
            console.log('• Verify audio file paths are correct.');
        }

        // Store results globally for inspection
        window.testResults = results;
        window.testConfig = CONFIG;

        console.log('\n✨ Test complete. Check window.testResults for detailed results.');

        return results;
    }

    // Auto-run
    runAllTests();
})();
