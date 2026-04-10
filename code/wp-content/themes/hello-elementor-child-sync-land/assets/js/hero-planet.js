/**
 * Hero Planet Controller
 *
 * Controls the hero planet display with random song metadata overlay.
 * Integrates with the inner-planet.js Three.js visualization and the FML music player.
 */

(function() {
    'use strict';

    // Store current song data for each hero planet instance
    const instances = new Map();

    // Track if we've attached audio element listeners
    let audioListenersAttached = false;
    // Track which audio element we attached to — detect Amplitude.init() replacements
    let knownAudioElement = null;

    /**
     * Initialize a hero planet instance
     */
    function initHeroPlanet(wrapper) {
        const instanceId = wrapper.id;

        // Ensure wrapper has an ID
        if (!instanceId) {
            wrapper.id = 'hero-planet-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        }

        const canvas = wrapper.querySelector('.hero-planet-canvas');
        const loading = wrapper.querySelector('.hero-planet-loading');
        const content = wrapper.querySelector('.hero-planet-content');
        const playBtn = wrapper.querySelector('.hero-planet-play-btn');
        const refreshBtn = wrapper.querySelector('.hero-planet-refresh-btn');

        // Skip if missing required elements
        if (!canvas || !loading || !content || !playBtn) {
            console.error('Hero Planet: Missing required elements');
            return;
        }

        // Get filter options from data attributes
        const genre = wrapper.dataset.genre || '';
        const mood = wrapper.dataset.mood || '';

        // Mark as initialized
        wrapper.dataset.initialized = 'true';

        // Store instance data
        instances.set(wrapper.id, {
            wrapper,
            canvas,
            loading,
            content,
            playBtn,
            refreshBtn,
            genre,
            mood,
            currentSong: null,
            planetInstance: null,
            songReady: false
        });

        // Use the wrapper's ID (which is now guaranteed to exist)
        var finalInstanceId = wrapper.id;

        // Disable play button until song is loaded
        playBtn.style.opacity = '0.5';
        playBtn.style.cursor = 'wait';

        // Initialize the 3D planet
        initPlanet(finalInstanceId);

        // Load initial random song
        loadRandomSong(finalInstanceId);

        // Set up event listeners
        playBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var inst = instances.get(finalInstanceId);
            if (!inst || !inst.songReady) {
                console.log('Hero Planet: Song not ready yet');
                return;
            }

            togglePlay(finalInstanceId);
        });

        if (refreshBtn) {
            refreshBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                loadRandomSong(finalInstanceId);
            });
        }
    }

    /**
     * Initialize the 3D planet visualization
     */
    function initPlanet(instanceId) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        // Wait for innerPlanetModule to be available
        if (typeof window.innerPlanetModule === 'undefined') {
            setTimeout(function() { initPlanet(instanceId); }, 100);
            return;
        }

        // Create the planet in the canvas container
        instance.planetInstance = window.innerPlanetModule.createInnerPlanet(instance.canvas);
    }

    /**
     * Load a random song from the API
     */
    function loadRandomSong(instanceId) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        // Show loading state and disable play button
        instance.loading.style.display = 'flex';
        instance.content.style.display = 'none';
        instance.songReady = false;
        instance.playBtn.style.opacity = '0.5';
        instance.playBtn.style.cursor = 'wait';

        // Build API URL with optional filters
        let apiUrl = '/wp-json/FML/v1/songs/random';
        const params = new URLSearchParams();

        if (instance.genre) {
            params.append('genre', instance.genre);
        }
        if (instance.mood) {
            params.append('mood', instance.mood);
        }

        // Cache-bust to ensure truly random results on every call
        params.append('_t', Date.now() + Math.random().toString(36).slice(2));

        const queryString = params.toString();
        if (queryString) {
            apiUrl += '?' + queryString;
        }

        // Fetch random song
        fetch(apiUrl)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch song');
                }
                return response.json();
            })
            .then(function(data) {
                // API returns { success: true, song: {...} }
                if (data.success && data.song) {
                    displaySong(instanceId, data.song);
                } else {
                    showError(instanceId, 'No songs available');
                }
            })
            .catch(function(error) {
                console.error('Hero Planet: Error loading song', error);
                showError(instanceId, 'Failed to load song');
            });
    }

    /**
     * Display song metadata in the overlay
     */
    function displaySong(instanceId, song) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        // Store current song
        instance.currentSong = song;

        // Update metadata display
        const titleEl = instance.wrapper.querySelector('.hero-planet-title');
        const artistEl = instance.wrapper.querySelector('.hero-planet-artist');
        const tagsEl = instance.wrapper.querySelector('.hero-planet-tags');
        const albumArtEl = instance.wrapper.querySelector('.hero-planet-album-art');

        // Get song name (handle if object) and decode HTML entities
        var songName = song.name;
        if (typeof songName === 'object' && songName !== null) {
            songName = songName.name || songName.post_title || 'Unknown Song';
        }
        songName = decodeHtml(songName || 'Unknown Song');

        // Get artist name (handle if object) and decode HTML entities
        var artistName = song.artist_name;
        if (typeof artistName === 'object' && artistName !== null) {
            artistName = artistName.name || artistName.post_title || 'Unknown Artist';
        }
        artistName = decodeHtml(artistName || 'Unknown Artist');

        // Display album art
        if (albumArtEl) {
            if (song.cover_art_url) {
                albumArtEl.src = song.cover_art_url;
                albumArtEl.style.display = 'block';
            } else {
                albumArtEl.style.display = 'none';
            }
        }

        // Set title with link to song page
        if (song.permalink) {
            titleEl.innerHTML = '<a href="' + escHtml(song.permalink) + '">' + escHtml(songName) + '</a>';
        } else {
            titleEl.textContent = songName;
        }

        // Set artist with link to artist page
        if (song.artist_permalink) {
            artistEl.innerHTML = '<a href="' + escHtml(song.artist_permalink) + '">' + escHtml(artistName) + '</a>';
        } else {
            artistEl.textContent = artistName;
        }

        // Build tags
        tagsEl.innerHTML = '';

        // Genres - array of objects with name and permalink
        if (song.genres && Array.isArray(song.genres)) {
            song.genres.forEach(function(g) {
                if (g && g.name) {
                    var tag = document.createElement('span');
                    tag.className = 'hero-planet-tag hero-planet-tag-genre';

                    if (g.permalink) {
                        var link = document.createElement('a');
                        link.href = g.permalink;
                        link.textContent = g.name;
                        tag.appendChild(link);
                    } else {
                        tag.textContent = g.name;
                    }

                    tagsEl.appendChild(tag);
                }
            });
        }

        // Moods - array of objects with name and permalink
        if (song.moods && Array.isArray(song.moods)) {
            song.moods.forEach(function(m) {
                if (m && m.name) {
                    var tag = document.createElement('span');
                    tag.className = 'hero-planet-tag hero-planet-tag-mood';

                    if (m.permalink) {
                        var link = document.createElement('a');
                        link.href = m.permalink;
                        link.textContent = m.name;
                        tag.appendChild(link);
                    } else {
                        tag.textContent = m.name;
                    }

                    tagsEl.appendChild(tag);
                }
            });
        }

        // BPM
        if (song.bpm) {
            var bpmVal = song.bpm;
            // Handle if bpm is somehow still an object
            if (typeof bpmVal === 'object') {
                bpmVal = bpmVal.value || bpmVal.name || '';
            }
            if (bpmVal) {
                var tag = document.createElement('span');
                tag.className = 'hero-planet-tag hero-planet-tag-bpm';
                tag.textContent = bpmVal + ' BPM';
                tagsEl.appendChild(tag);
            }
        }

        // Hide loading, show content
        instance.loading.style.display = 'none';
        instance.content.style.display = 'flex';

        // Mark song as ready and enable play button only if we have an audio URL
        if (song.audio_url) {
            instance.songReady = true;
            instance.playBtn.style.opacity = '1';
            instance.playBtn.style.cursor = 'pointer';

            // Dispatch event so other components (like music player) can sync
            dispatchSongLoadedEvent(song);
        } else {
            instance.songReady = false;
            instance.playBtn.style.opacity = '0.5';
            instance.playBtn.style.cursor = 'not-allowed';
        }
    }

    /**
     * Show error state
     */
    function showError(instanceId, message) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        instance.loading.style.display = 'none';
        instance.content.style.display = 'flex';

        const titleEl = instance.wrapper.querySelector('.hero-planet-title');
        const artistEl = instance.wrapper.querySelector('.hero-planet-artist');
        const tagsEl = instance.wrapper.querySelector('.hero-planet-tags');

        titleEl.textContent = message || 'Error';
        artistEl.textContent = 'Click refresh to try again';
        tagsEl.innerHTML = '';
    }

    /**
     * Check if audio is currently playing
     */
    function isAudioPlaying() {
        if (typeof Amplitude === 'undefined') return false;
        try {
            var audio = Amplitude.getAudio();
            return audio && !audio.paused;
        } catch(e) {
            return false;
        }
    }

    /**
     * Toggle play/pause for the current song
     */
    function togglePlay(instanceId) {
        const instance = instances.get(instanceId);
        if (!instance) {
            console.error('Hero Planet: No instance');
            return;
        }

        // Check if song is loaded
        if (!instance.currentSong) {
            console.log('Hero Planet: Song not loaded yet, waiting...');
            return;
        }

        // Check if audio URL exists
        if (!instance.currentSong.audio_url) {
            console.error('Hero Planet: No audio URL for song');
            return;
        }

        // Check if our song is currently loaded in Amplitude
        // Normalize URLs to avoid http/https mismatch breaking the comparison
        var ourSongLoaded = false;
        try {
            var activeMeta = Amplitude.getActiveSongMetadata();
            if (activeMeta) {
                var activeUrl = (activeMeta.url || '').replace(/^https?:/, '').replace(/\/$/, '');
                var ourUrl   = (instance.currentSong.audio_url || '').replace(/^https?:/, '').replace(/\/$/, '');
                ourSongLoaded = activeUrl && activeUrl === ourUrl;
            }
        } catch(e) {}

        var currentlyPlaying = isAudioPlaying();

        if (currentlyPlaying && ourSongLoaded) {
            // Our song is playing — pause it; update UI immediately, don't wait for audio event
            updatePlayButton(instanceId, false);
            syncMainPlayerButtons(false);
            if (typeof Amplitude !== 'undefined') {
                Amplitude.pause();
                try { Amplitude.bindNewElements(); } catch(e) {}
            }
        } else if (!currentlyPlaying && ourSongLoaded) {
            // Our song is loaded but paused — resume; update UI immediately
            updatePlayButton(instanceId, true);
            syncMainPlayerButtons(true);
            if (typeof Amplitude !== 'undefined') {
                Amplitude.play();
                try { Amplitude.bindNewElements(); } catch(e) {}
            }
        } else {
            // Different song or no song loaded — load and play ours
            playSong(instanceId);
        }
    }

    /**
     * Play the current song using FMLPlaySongAtTop
     */
    function playSong(instanceId) {
        const instance = instances.get(instanceId);
        if (!instance || !instance.currentSong) {
            console.error('Hero Planet: No instance or current song');
            return;
        }

        const song = instance.currentSong;

        // Build song object for the player - decode HTML entities
        const songObj = {
            name: decodeHtml(song.name || ''),
            artist: decodeHtml(song.artist_name || ''),
            album: decodeHtml(song.album_name || ''),
            url: song.audio_url || '',
            cover_art_url: song.cover_art_url || '',
            song_id: song.id,
            permalink: song.permalink || '',
            artist_permalink: song.artist_permalink || '',
            album_permalink: song.album_permalink || ''
        };

        // Check if audio URL exists
        if (!songObj.url) {
            console.error('Hero Planet: No audio URL for song');
            return;
        }

        // Use the shared play function from music-player.js
        if (typeof window.FMLPlaySongAtTop === 'function') {
            window.FMLPlaySongAtTop(songObj);

            // FMLPlaySongAtTop reinitializes Amplitude, so we need to reattach listeners
            // and ensure Amplitude's element bindings are updated
            setTimeout(function() {
                reattachAudioListeners();

                // Rebind all Amplitude elements to ensure main player buttons sync
                if (typeof Amplitude !== 'undefined') {
                    try {
                        Amplitude.bindNewElements();
                    } catch(e) {}

                    // Amplitude may not autoplay due to browser restrictions
                    // Try to explicitly play after reattaching listeners
                    try {
                        Amplitude.play();
                    } catch(e) {
                        console.error('Hero Planet: Error calling Amplitude.play()', e);
                    }

                    // Rebind again after play to ensure state is correct
                    setTimeout(function() {
                        try { Amplitude.bindNewElements(); } catch(e) {}
                    }, 100);
                }
            }, 150);
        } else {
            console.error('Hero Planet: FMLPlaySongAtTop not available');
        }
    }

    /**
     * Update play button icon
     */
    function updatePlayButton(instanceId, isPlaying) {
        const instance = instances.get(instanceId);
        if (!instance) return;

        const btn = instance.playBtn;
        if (isPlaying) {
            // Show pause icon
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';
            btn.setAttribute('aria-label', 'Pause song');
        } else {
            // Show play icon
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
            btn.setAttribute('aria-label', 'Play song');
        }
    }

    /**
     * Sync all play buttons with current audio state
     */
    function syncAllPlayButtons() {
        var isPlaying = isAudioPlaying();
        instances.forEach(function(instance, id) {
            updatePlayButton(id, isPlaying);
        });

        // Also sync the main music player's play/pause buttons
        syncMainPlayerButtons(isPlaying);
    }

    /**
     * Sync the main music player's play/pause buttons
     * These use Amplitude's CSS class system
     */
    function syncMainPlayerButtons(isPlaying) {
        // Amplitude uses amplitude-playing/amplitude-paused classes on amplitude-play-pause elements
        var playPauseElements = document.querySelectorAll('.amplitude-play-pause');
        playPauseElements.forEach(function(el) {
            if (isPlaying) {
                el.classList.remove('amplitude-paused');
                el.classList.add('amplitude-playing');
            } else {
                el.classList.remove('amplitude-playing');
                el.classList.add('amplitude-paused');
            }
        });
    }

    /**
     * Attach listeners to the actual audio element for reliable state sync
     */
    function attachAudioListeners() {
        if (audioListenersAttached) return;

        function tryAttach() {
            if (typeof Amplitude === 'undefined') {
                setTimeout(tryAttach, 200);
                return;
            }

            try {
                var audio = Amplitude.getAudio();
                if (!audio) {
                    setTimeout(tryAttach, 200);
                    return;
                }

                // Listen to native audio events - these are the most reliable
                audio.addEventListener('play', function() {
                    instances.forEach(function(instance, id) {
                        updatePlayButton(id, true);
                    });
                    syncMainPlayerButtons(true);
                });

                audio.addEventListener('playing', function() {
                    instances.forEach(function(instance, id) {
                        updatePlayButton(id, true);
                    });
                    syncMainPlayerButtons(true);
                });

                audio.addEventListener('pause', function() {
                    instances.forEach(function(instance, id) {
                        updatePlayButton(id, false);
                    });
                    syncMainPlayerButtons(false);
                });

                audio.addEventListener('ended', function() {
                    instances.forEach(function(instance, id) {
                        updatePlayButton(id, false);
                    });
                    syncMainPlayerButtons(false);
                });

                knownAudioElement = audio;
                audioListenersAttached = true;
                console.log('Hero Planet: Audio element listeners attached');

                // Sync initial state
                syncAllPlayButtons();
            } catch(e) {
                setTimeout(tryAttach, 200);
            }
        }

        tryAttach();
    }

    /**
     * Re-attach audio listeners after Amplitude reinitializes
     * This is needed because FMLPlaySongAtTop calls Amplitude.init() which creates a new audio element
     */
    function reattachAudioListeners() {
        audioListenersAttached = false;
        attachAudioListeners();
    }

    // Start trying to attach audio listeners
    attachAudioListeners();

    // Detect when Amplitude.init() creates a new audio element (happens on every FMLPlaySongAtTop call)
    // by comparing the element reference, not just the boolean flag
    setInterval(function() {
        if (typeof Amplitude !== 'undefined') {
            try {
                var audio = Amplitude.getAudio();
                if (audio && (audio !== knownAudioElement || !audioListenersAttached)) {
                    audioListenersAttached = false;
                    attachAudioListeners();
                }
            } catch(e) {}
        }
    }, 500);

    /**
     * Escape HTML to prevent XSS
     */
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Decode HTML entities (e.g., &amp; -> &)
     */
    function decodeHtml(str) {
        if (!str) return '';
        var txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    /**
     * Initialize all hero planets on the page
     */
    function init() {
        const wrappers = document.querySelectorAll('.hero-planet-wrapper');
        wrappers.forEach(function(wrapper) {
            // Skip if already initialized
            if (wrapper.dataset.initialized === 'true') {
                return;
            }
            initHeroPlanet(wrapper);
        });
    }

    /**
     * Reinitialize hero planets after PJAX navigation
     * Clears old instances for wrappers that no longer exist
     */
    function reinitAfterPjax() {
        // Clean up instances for removed elements
        instances.forEach(function(instance, id) {
            if (!document.getElementById(id)) {
                instances.delete(id);
            }
        });

        // Reset initialized flag for any new hero planet wrappers
        var wrappers = document.querySelectorAll('.hero-planet-wrapper');
        wrappers.forEach(function(wrapper) {
            // Check if this wrapper is already in our instances map
            if (!instances.has(wrapper.id)) {
                wrapper.dataset.initialized = '';
            }
        });

        // Initialize any new hero planets
        init();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Handle PJAX navigation
    document.addEventListener('pjax:load', reinitAfterPjax);

    // jQuery PJAX event
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('pjax:complete', reinitAfterPjax);
    }

    // Expose for external use if needed
    window.HeroPlanet = {
        refresh: function(instanceId) {
            loadRandomSong(instanceId);
        },
        getInstance: function(instanceId) {
            return instances.get(instanceId);
        },
        // Get the current random song from the first hero planet instance
        getCurrentSong: function() {
            // Find the first instance with a loaded song
            var song = null;
            instances.forEach(function(instance) {
                if (!song && instance.currentSong) {
                    song = instance.currentSong;
                }
            });
            return song;
        },
        // Get the current song formatted for Amplitude player
        getCurrentSongForPlayer: function() {
            var song = this.getCurrentSong();
            if (!song || !song.audio_url) return null;

            return {
                name: song.name || '',
                artist: song.artist_name || '',
                album: song.album_name || '',
                url: song.audio_url || '',
                cover_art_url: song.cover_art_url || '',
                song_id: song.id,
                permalink: song.permalink || '',
                artist_permalink: song.artist_permalink || '',
                album_permalink: song.album_permalink || ''
            };
        },
        // Check if a hero planet instance is ready
        isReady: function() {
            var ready = false;
            instances.forEach(function(instance) {
                if (instance.songReady) {
                    ready = true;
                }
            });
            return ready;
        }
    };

    /**
     * Dispatch event when song is loaded so other components can react
     */
    function dispatchSongLoadedEvent(song) {
        if (song) {
            var event = new CustomEvent('heroPlanetSongLoaded', {
                detail: {
                    song: song,
                    songForPlayer: {
                        name: song.name || '',
                        artist: song.artist_name || '',
                        album: song.album_name || '',
                        url: song.audio_url || '',
                        cover_art_url: song.cover_art_url || '',
                        song_id: song.id,
                        permalink: song.permalink || '',
                        artist_permalink: song.artist_permalink || '',
                        album_permalink: song.album_permalink || ''
                    }
                }
            });
            document.dispatchEvent(event);
        }
    }

    /**
     * Initialize the music player with hero planet's random song if player is empty
     * This syncs the player with the hero planet's random song
     */
    function initPlayerWithHeroPlanetSong() {
        // Wait for both Amplitude and a song to be ready
        if (typeof Amplitude === 'undefined') {
            setTimeout(initPlayerWithHeroPlanetSong, 200);
            return;
        }

        // Check if player already has songs loaded
        try {
            var currentSongs = Amplitude.getSongs() || [];
            if (currentSongs.length > 0) {
                // Player already has songs, don't override
                return;
            }
        } catch (e) {
            // Amplitude may not be fully initialized yet
        }

        // Check if hero planet has a song ready
        var heroPlanetSong = window.HeroPlanet.getCurrentSongForPlayer();
        if (heroPlanetSong && heroPlanetSong.url) {
            // Load the hero planet song into the player (but don't auto-play)
            try {
                Amplitude.init({
                    songs: [heroPlanetSong],
                    start_song: 0,
                    autoplay: false,
                    preload: "metadata",
                    callbacks: window.FMLAmplitudeCallbacks || {}
                });

                // Set crossOrigin for audio analysis
                try {
                    var audio = Amplitude.getAudio();
                    if (audio) audio.crossOrigin = "anonymous";
                } catch(e) {}

                Amplitude.bindNewElements();

                // Update player display and license button
                if (typeof window.updatePlayerMeta === 'function') {
                    try { window.updatePlayerMeta(); } catch(e) {}
                }
                if (typeof window.updateLicenseButton === 'function') {
                    try { window.updateLicenseButton(); } catch(e) {}
                }

                // Retry after Amplitude has fully settled
                setTimeout(function() {
                    if (typeof window.updateLicenseButton === 'function') {
                        try { window.updateLicenseButton(); } catch(e) {}
                    }
                    if (typeof window.updatePlayerMeta === 'function') {
                        try { window.updatePlayerMeta(); } catch(e) {}
                    }
                }, 500);

                // Init audio analyser for visualizer readiness
                setTimeout(function() {
                    if (window.FMLReinitAudioAnalyser) {
                        window.FMLReinitAudioAnalyser();
                    }
                }, 200);
            } catch (e) {
                console.log('Hero Planet: Could not initialize player with random song', e);
            }
        }
    }

    // Try to sync player with hero planet song after a delay
    // This allows hero planet to load its random song first
    setTimeout(initPlayerWithHeroPlanetSong, 2000);

})();
