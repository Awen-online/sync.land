/**
 * Song Player Handler
 * Handles .song-play button clicks, displays pre-generated waveforms,
 * and integrates with Amplitude for playback
 */
(function() {
    'use strict';

    // Waveform colors
    var WAVEFORM_COLOR = 'rgba(255, 255, 255, 0.3)';
    var WAVEFORM_PROGRESS_COLOR = '#2F6ED3';
    var WAVEFORM_HEIGHT = 48;
    var WAVEFORM_BAR_WIDTH = 2;
    var WAVEFORM_BAR_GAP = 1;

    // Track state
    var currentPlayingSongId = null;
    var progressUpdateInterval = null;
    var waveformCache = {};
    var pendingWaveformLoads = {};

    /**
     * Initialize the song player handler
     */
    function init() {
        // Delegated click handler for all .song-play buttons
        document.addEventListener('click', function(e) {
            var playBtn = e.target.closest('.song-play');
            if (!playBtn) return;

            e.preventDefault();
            e.stopPropagation();

            playSong(playBtn);
        });

        // Handle waveform clicks for seeking
        document.addEventListener('click', function(e) {
            var waveform = e.target.closest('.song-waveform-canvas');
            if (!waveform) return;

            var rect = waveform.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var percentage = x / rect.width;

            seekToPercentage(percentage);
        });

        // Start progress update loop
        startProgressUpdater();

        // Load waveforms for visible songs
        loadVisibleWaveforms();

        console.log('Song Player: Initialized');
    }

    /**
     * Load waveforms for all visible song rows
     */
    function loadVisibleWaveforms() {
        var songRows = document.querySelectorAll('.song-row[data-song-id]');
        var songIds = [];

        songRows.forEach(function(row) {
            var songId = row.dataset.songId;
            if (songId && !waveformCache[songId] && !pendingWaveformLoads[songId]) {
                songIds.push(parseInt(songId));
                pendingWaveformLoads[songId] = true;
            }
        });

        if (songIds.length === 0) return;

        // Batch fetch waveforms
        fetch('/wp-json/FML/v1/waveforms', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ song_ids: songIds })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.waveforms) {
                Object.keys(data.waveforms).forEach(function(songId) {
                    var peaks = data.waveforms[songId];
                    if (peaks && peaks.length > 0) {
                        waveformCache[songId] = peaks;
                        renderWaveformForSong(songId, peaks);
                    }
                    delete pendingWaveformLoads[songId];
                });
            }
        })
        .catch(function(e) {
            console.error('Failed to load waveforms:', e);
            songIds.forEach(function(id) {
                delete pendingWaveformLoads[id];
            });
        });
    }

    /**
     * Render waveform for a specific song row
     */
    function renderWaveformForSong(songId, peaks) {
        var songRow = document.querySelector('.song-row[data-song-id="' + songId + '"]');
        if (!songRow) return;

        // Check if waveform container already exists
        var existing = songRow.querySelector('.song-waveform-container');
        if (existing) return;

        // Create waveform container
        var container = document.createElement('div');
        container.className = 'song-waveform-container';
        container.innerHTML =
            '<div class="waveform-time current-time">0:00</div>' +
            '<div class="waveform-canvas-wrapper">' +
                '<canvas class="song-waveform-canvas" data-song-id="' + songId + '"></canvas>' +
                '<div class="waveform-progress-overlay"></div>' +
            '</div>' +
            '<div class="waveform-time duration">--:--</div>';

        // Insert after song-info
        var songInfo = songRow.querySelector('.song-info');
        if (songInfo) {
            songInfo.parentNode.insertBefore(container, songInfo.nextSibling);
        } else {
            songRow.appendChild(container);
        }

        // Draw waveform on canvas
        var canvas = container.querySelector('.song-waveform-canvas');
        drawWaveform(canvas, peaks, 0);
    }

    /**
     * Draw waveform on canvas
     */
    function drawWaveform(canvas, peaks, progressPercent) {
        if (!canvas || !peaks || peaks.length === 0) return;

        var ctx = canvas.getContext('2d');
        var wrapper = canvas.parentElement;
        var width = wrapper.offsetWidth || 300;
        var height = WAVEFORM_HEIGHT;

        // Set canvas size
        canvas.width = width * window.devicePixelRatio;
        canvas.height = height * window.devicePixelRatio;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

        // Clear
        ctx.clearRect(0, 0, width, height);

        // Calculate bar dimensions
        var barCount = peaks.length;
        var totalBarWidth = WAVEFORM_BAR_WIDTH + WAVEFORM_BAR_GAP;
        var actualBarCount = Math.floor(width / totalBarWidth);
        var step = barCount / actualBarCount;

        var progressX = (progressPercent / 100) * width;

        // Draw bars
        for (var i = 0; i < actualBarCount; i++) {
            var peakIndex = Math.floor(i * step);
            var peak = peaks[peakIndex] || 0;
            var barHeight = Math.max(2, peak * (height - 4));
            var x = i * totalBarWidth;
            var y = (height - barHeight) / 2;

            // Choose color based on progress
            if (x < progressX) {
                ctx.fillStyle = WAVEFORM_PROGRESS_COLOR;
            } else {
                ctx.fillStyle = WAVEFORM_COLOR;
            }

            // Draw rounded bar
            roundedRect(ctx, x, y, WAVEFORM_BAR_WIDTH, barHeight, 1);
        }
    }

    /**
     * Draw rounded rectangle
     */
    function roundedRect(ctx, x, y, width, height, radius) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + height - radius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        ctx.lineTo(x + radius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();
        ctx.fill();
    }

    /**
     * Play a song from a .song-play button
     */
    function playSong(btn) {
        var audioSrc = btn.dataset.audiosrc;
        var songName = btn.dataset.songname || 'Unknown Song';
        var artistName = btn.dataset.artistname || 'Unknown Artist';
        var albumName = btn.dataset.albumname || '';
        var coverArt = btn.dataset.artsrc || '';
        var songId = btn.dataset.songid || '';
        var permalink = btn.dataset.permalink || '';
        var artistPermalink = btn.dataset.artistpermalink || '';
        var albumPermalink = btn.dataset.albumpermalink || '';

        if (!audioSrc) {
            console.error('Song Player: No audio source');
            return;
        }

        // Build song object for Amplitude
        var songObj = {
            name: decodeHtml(songName),
            artist: decodeHtml(artistName),
            album: decodeHtml(albumName),
            url: audioSrc,
            cover_art_url: coverArt,
            song_id: songId,
            permalink: permalink,
            artist_permalink: artistPermalink,
            album_permalink: albumPermalink
        };

        // Remove playing state from previous song
        var prevPlaying = document.querySelector('.song-row.is-playing');
        if (prevPlaying) {
            prevPlaying.classList.remove('is-playing');
        }

        // Mark current song as playing
        var songRow = btn.closest('.song-row');
        if (songRow) {
            songRow.classList.add('is-playing');
            currentPlayingSongId = songId;
        }

        // Play the song using Amplitude
        playWithAmplitude(songObj);
    }

    /**
     * Play song using Amplitude
     */
    function playWithAmplitude(songObj) {
        if (typeof Amplitude === 'undefined') {
            console.error('Song Player: Amplitude not loaded');
            return;
        }

        try {
            var currentSongs = Amplitude.getSongs() || [];
            var songIndex = -1;

            for (var i = 0; i < currentSongs.length; i++) {
                if (currentSongs[i].url === songObj.url) {
                    songIndex = i;
                    break;
                }
            }

            if (songIndex >= 0) {
                Amplitude.playSongAtIndex(songIndex);
            } else {
                if (currentSongs.length === 0) {
                    Amplitude.init({
                        songs: [songObj],
                        start_song: 0,
                        continue_next: false
                    });
                    setTimeout(function() {
                        Amplitude.play();
                    }, 100);
                } else {
                    Amplitude.addSong(songObj);
                    var newIndex = Amplitude.getSongs().length - 1;
                    Amplitude.playSongAtIndex(newIndex);
                }
            }

            setTimeout(function() {
                try { Amplitude.bindNewElements(); } catch(e) {}
            }, 150);

            if (typeof window.updatePlayerMeta === 'function') {
                setTimeout(function() {
                    try { window.updatePlayerMeta(); } catch(e) {}
                }, 200);
            }

        } catch (e) {
            console.error('Song Player: Error playing song', e);
        }
    }

    /**
     * Seek to percentage
     */
    function seekToPercentage(percentage) {
        if (typeof Amplitude === 'undefined') return;

        try {
            var audio = Amplitude.getAudio();
            if (audio && audio.duration) {
                audio.currentTime = percentage * audio.duration;
            }
        } catch(e) {}
    }

    /**
     * Start interval to update progress
     */
    function startProgressUpdater() {
        if (progressUpdateInterval) {
            clearInterval(progressUpdateInterval);
        }

        progressUpdateInterval = setInterval(function() {
            updatePlayingWaveform();
        }, 50);
    }

    /**
     * Update waveform for currently playing song
     */
    function updatePlayingWaveform() {
        if (typeof Amplitude === 'undefined') return;

        var playingRow = document.querySelector('.song-row.is-playing');
        if (!playingRow) return;

        var songId = playingRow.dataset.songId;
        var peaks = waveformCache[songId];
        if (!peaks) return;

        var container = playingRow.querySelector('.song-waveform-container');
        if (!container) return;

        try {
            var audio = Amplitude.getAudio();
            if (!audio) return;

            var currentTime = audio.currentTime || 0;
            var duration = audio.duration || 0;
            var percentage = duration > 0 ? (currentTime / duration) * 100 : 0;

            // Update waveform canvas
            var canvas = container.querySelector('.song-waveform-canvas');
            if (canvas) {
                drawWaveform(canvas, peaks, percentage);
            }

            // Update time displays
            var currentTimeEl = container.querySelector('.current-time');
            var durationEl = container.querySelector('.duration');

            if (currentTimeEl) {
                currentTimeEl.textContent = formatTime(currentTime);
            }
            if (durationEl && duration > 0) {
                durationEl.textContent = formatTime(duration);
            }

            // Check if song ended
            if (audio.paused && currentTime > 0 && currentTime >= duration - 0.1) {
                playingRow.classList.remove('is-playing');
                // Reset waveform progress
                drawWaveform(canvas, peaks, 0);
            }

        } catch(e) {}
    }

    /**
     * Format seconds to MM:SS
     */
    function formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) return '0:00';
        var mins = Math.floor(seconds / 60);
        var secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    /**
     * Decode HTML entities
     */
    function decodeHtml(str) {
        if (!str) return '';
        var txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    /**
     * Cleanup when navigating away
     */
    function cleanup() {
        var playingRows = document.querySelectorAll('.song-row.is-playing');
        playingRows.forEach(function(row) {
            row.classList.remove('is-playing');
        });
        currentPlayingSongId = null;
        waveformCache = {};
        pendingWaveformLoads = {};
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-initialize after PJAX navigation
    document.addEventListener('pjax:load', function() {
        cleanup();
        setTimeout(loadVisibleWaveforms, 100);
    });

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('pjax:complete', function() {
            cleanup();
            setTimeout(loadVisibleWaveforms, 100);
        });
    }

    // Handle window resize - redraw waveforms
    var resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            Object.keys(waveformCache).forEach(function(songId) {
                var canvas = document.querySelector('.song-waveform-canvas[data-song-id="' + songId + '"]');
                if (canvas) {
                    var playingRow = document.querySelector('.song-row.is-playing[data-song-id="' + songId + '"]');
                    var progress = 0;
                    if (playingRow && typeof Amplitude !== 'undefined') {
                        try {
                            progress = Amplitude.getSongPlayedPercentage() || 0;
                        } catch(e) {}
                    }
                    drawWaveform(canvas, waveformCache[songId], progress);
                }
            });
        }, 250);
    });

    // Expose globally
    window.FMLSongPlayer = {
        play: playSong,
        loadWaveforms: loadVisibleWaveforms,
        refreshWaveform: function(songId) {
            var peaks = waveformCache[songId];
            if (peaks) {
                renderWaveformForSong(songId, peaks);
            }
        }
    };

})();
