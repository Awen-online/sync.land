<?php
/**
 * Waveform Generator
 * Generates and stores waveform data for songs
 * Uses client-side Web Audio API via admin AJAX
 */

// Register REST API endpoints for waveform operations
add_action('rest_api_init', function() {
    // Save waveform data for a song
    register_rest_route('FML/v1', '/waveform/save', [
        'methods' => 'POST',
        'callback' => 'fml_save_waveform',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
        'args' => [
            'song_id' => ['type' => 'integer', 'required' => true],
            'peaks' => ['type' => 'array', 'required' => true],
        ],
    ]);

    // Get waveform data for a song
    register_rest_route('FML/v1', '/waveform/(?P<song_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'fml_get_waveform',
        'permission_callback' => '__return_true',
    ]);

    // Get songs needing waveform generation
    register_rest_route('FML/v1', '/waveform/pending', [
        'methods' => 'GET',
        'callback' => 'fml_get_pending_waveforms',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);

    // Batch get waveforms for multiple songs
    register_rest_route('FML/v1', '/waveforms', [
        'methods' => 'POST',
        'callback' => 'fml_get_batch_waveforms',
        'permission_callback' => '__return_true',
        'args' => [
            'song_ids' => ['type' => 'array', 'required' => true],
        ],
    ]);
});

/**
 * Save waveform peaks data for a song
 */
function fml_save_waveform($request) {
    $song_id = intval($request->get_param('song_id'));
    $peaks = $request->get_param('peaks');

    if (!$song_id || !is_array($peaks)) {
        return new WP_Error('invalid_data', 'Invalid song ID or peaks data', ['status' => 400]);
    }

    // Validate song exists
    $song = get_post($song_id);
    if (!$song || $song->post_type !== 'song') {
        return new WP_Error('invalid_song', 'Song not found', ['status' => 404]);
    }

    // Normalize peaks to 0-1 range and limit to 200 points for efficiency
    $normalized_peaks = fml_normalize_peaks($peaks, 200);

    // Save to post meta
    update_post_meta($song_id, '_waveform_peaks', $normalized_peaks);
    update_post_meta($song_id, '_waveform_generated', current_time('mysql'));

    return [
        'success' => true,
        'song_id' => $song_id,
        'peaks_count' => count($normalized_peaks),
    ];
}

/**
 * Get waveform data for a song
 */
function fml_get_waveform($request) {
    $song_id = intval($request->get_param('song_id'));

    $peaks = get_post_meta($song_id, '_waveform_peaks', true);

    if (!$peaks || !is_array($peaks)) {
        return [
            'song_id' => $song_id,
            'peaks' => null,
            'generated' => false,
        ];
    }

    return [
        'song_id' => $song_id,
        'peaks' => $peaks,
        'generated' => true,
    ];
}

/**
 * Get batch waveforms for multiple songs
 */
function fml_get_batch_waveforms($request) {
    $song_ids = $request->get_param('song_ids');

    if (!is_array($song_ids)) {
        return new WP_Error('invalid_data', 'song_ids must be an array', ['status' => 400]);
    }

    $waveforms = [];
    foreach ($song_ids as $song_id) {
        $song_id = intval($song_id);
        $peaks = get_post_meta($song_id, '_waveform_peaks', true);
        $waveforms[$song_id] = $peaks ?: null;
    }

    return [
        'waveforms' => $waveforms,
    ];
}

/**
 * Get songs that need waveform generation
 */
function fml_get_pending_waveforms($request) {
    $per_page = intval($request->get_param('per_page')) ?: 50;

    // Get total published songs count (same method as admin dashboard)
    $total_songs = intval(wp_count_posts('song')->publish);

    // Get all published song IDs
    $all_song_ids = get_posts([
        'post_type' => 'song',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    $songs_pending = [];
    $total_completed = 0;

    // Check each song for waveform data
    foreach ($all_song_ids as $song_id) {
        $peaks = get_post_meta($song_id, '_waveform_peaks', true);

        if ($peaks && is_array($peaks) && !empty($peaks)) {
            $total_completed++;
        } else {
            // This song needs waveform generation - get audio URL via Pods
            $song_pod = pods('song', $song_id);
            $audio_url = $song_pod->field('audio_url');

            if ($audio_url) {
                $songs_pending[] = [
                    'id' => $song_id,
                    'title' => get_the_title($song_id),
                    'audio_url' => $audio_url,
                ];
            }
        }
    }

    $total_pending = count($songs_pending);

    // Limit the returned songs to per_page
    $songs_to_return = array_slice($songs_pending, 0, $per_page);

    return [
        'songs' => $songs_to_return,
        'total_songs' => $total_songs,
        'total_pending' => $total_pending,
        'total_completed' => $total_completed,
        'returned' => count($songs_to_return),
    ];
}

/**
 * Normalize peaks array to consistent range and count
 */
function fml_normalize_peaks($peaks, $target_count = 200) {
    if (empty($peaks)) return [];

    $count = count($peaks);

    // Find max value for normalization
    $max = max(array_map('abs', $peaks));
    if ($max == 0) $max = 1;

    // Resample to target count
    $result = [];
    $step = $count / $target_count;

    for ($i = 0; $i < $target_count; $i++) {
        $pos = $i * $step;
        $index = floor($pos);

        // Get value at this position (or interpolate)
        if ($index < $count) {
            $value = abs($peaks[$index]) / $max;
        } else {
            $value = 0;
        }

        // Clamp to 0-1
        $result[] = round(min(1, max(0, $value)), 3);
    }

    return $result;
}

/**
 * Add admin page for waveform generation
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'syncland',
        'Generate Waveforms',
        'Generate Waveforms',
        'manage_options',
        'syncland-waveforms',
        'fml_waveform_admin_page'
    );
}, 20);

/**
 * Admin page for batch waveform generation
 */
function fml_waveform_admin_page() {
    // Get REST API settings
    $rest_url = esc_url_raw(rest_url('FML/v1/'));
    $rest_nonce = wp_create_nonce('wp_rest');
    ?>
    <div class="wrap">
        <h1>Generate Song Waveforms</h1>
        <p>This tool generates waveform visualizations for all songs. Waveforms are generated client-side using the Web Audio API and saved to the database.</p>

        <div id="waveform-generator-status">
            <p><strong>Status:</strong> <span id="wg-status">Checking...</span></p>
            <p><strong>Total Songs:</strong> <span id="wg-total-songs">-</span></p>
            <p><strong>Waveforms Generated:</strong> <span id="wg-completed">-</span></p>
            <p><strong>Songs Pending:</strong> <span id="wg-pending">-</span></p>
            <p><strong>Session Progress:</strong> <span id="wg-progress">0</span> processed this session</p>
        </div>

        <div id="waveform-generator-controls" style="margin-top: 20px;">
            <button id="wg-start" class="button button-primary" disabled>Start Generation</button>
            <button id="wg-stop" class="button" disabled>Stop</button>
        </div>

        <div id="waveform-generator-log" style="margin-top: 20px; max-height: 400px; overflow-y: auto; background: #f0f0f0; padding: 10px; font-family: monospace; font-size: 12px;">
        </div>
    </div>

    <script>
    (function() {
        var isRunning = false;
        var shouldStop = false;
        var processed = 0;
        var restUrl = '<?php echo $rest_url; ?>';
        var wpNonce = '<?php echo $rest_nonce; ?>';

        var statusEl = document.getElementById('wg-status');
        var totalSongsEl = document.getElementById('wg-total-songs');
        var completedEl = document.getElementById('wg-completed');
        var pendingEl = document.getElementById('wg-pending');
        var progressEl = document.getElementById('wg-progress');
        var logEl = document.getElementById('waveform-generator-log');
        var startBtn = document.getElementById('wg-start');
        var stopBtn = document.getElementById('wg-stop');

        function log(msg) {
            var time = new Date().toLocaleTimeString();
            logEl.innerHTML += '[' + time + '] ' + msg + '<br>';
            logEl.scrollTop = logEl.scrollHeight;
        }

        function updateStatus() {
            fetch(restUrl + 'waveform/pending', {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': wpNonce }
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    totalSongsEl.textContent = data.total_songs || 0;

                    var percent = data.total_songs > 0 ? Math.round((data.total_completed / data.total_songs) * 100) : 0;
                    completedEl.textContent = (data.total_completed || 0) + ' / ' + (data.total_songs || 0) + ' (' + percent + '%)';
                    pendingEl.textContent = data.total_pending || 0;

                    if (data.total_songs === 0) {
                        statusEl.textContent = 'No songs found';
                        startBtn.disabled = true;
                    } else if (data.total_pending > 0) {
                        statusEl.textContent = 'Ready - ' + data.total_pending + ' songs need waveforms';
                        startBtn.disabled = false;
                    } else {
                        statusEl.textContent = 'Complete - All ' + data.total_songs + ' songs have waveforms';
                        startBtn.disabled = true;
                    }
                })
                .catch(function(e) {
                    statusEl.textContent = 'Error checking status';
                    log('Error: ' + e.message);
                });
        }

        function generateWaveform(song) {
            return new Promise(function(resolve, reject) {
                log('Processing: ' + song.title + ' (ID: ' + song.id + ')');

                // Create audio context for analysis
                var audioContext = new (window.AudioContext || window.webkitAudioContext)();

                fetch(song.audio_url)
                    .then(function(response) {
                        if (!response.ok) throw new Error('Failed to fetch audio');
                        return response.arrayBuffer();
                    })
                    .then(function(arrayBuffer) {
                        return audioContext.decodeAudioData(arrayBuffer);
                    })
                    .then(function(audioBuffer) {
                        // Get channel data
                        var channelData = audioBuffer.getChannelData(0);
                        var samples = channelData.length;

                        // Generate peaks (200 points)
                        var peakCount = 200;
                        var blockSize = Math.floor(samples / peakCount);
                        var peaks = [];

                        for (var i = 0; i < peakCount; i++) {
                            var start = i * blockSize;
                            var end = start + blockSize;
                            var max = 0;

                            for (var j = start; j < end && j < samples; j++) {
                                var val = Math.abs(channelData[j]);
                                if (val > max) max = val;
                            }

                            peaks.push(max);
                        }

                        audioContext.close();

                        // Save to server
                        return fetch(restUrl + 'waveform/save', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpNonce
                            },
                            body: JSON.stringify({
                                song_id: song.id,
                                peaks: peaks
                            })
                        });
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            log('✓ Generated waveform for: ' + song.title);
                            resolve(true);
                        } else {
                            throw new Error(data.message || 'Save failed');
                        }
                    })
                    .catch(function(e) {
                        log('✗ Error for ' + song.title + ': ' + e.message);
                        audioContext.close().catch(function() {});
                        resolve(false); // Continue with next song
                    });
            });
        }

        async function processAll() {
            isRunning = true;
            shouldStop = false;
            startBtn.disabled = true;
            stopBtn.disabled = false;
            statusEl.textContent = 'Running...';
            processed = 0;
            progressEl.textContent = '0';

            log('Starting waveform generation...');

            while (!shouldStop) {
                // Get next batch of pending songs
                var response = await fetch(restUrl + 'waveform/pending?per_page=10', {
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': wpNonce }
                });
                var data = await response.json();

                if (data.songs.length === 0) {
                    log('All songs processed!');
                    break;
                }

                for (var i = 0; i < data.songs.length && !shouldStop; i++) {
                    await generateWaveform(data.songs[i]);
                    processed++;
                    progressEl.textContent = processed;

                    // Small delay between songs
                    await new Promise(function(r) { setTimeout(r, 100); });
                }
            }

            isRunning = false;
            startBtn.disabled = false;
            stopBtn.disabled = true;
            statusEl.textContent = shouldStop ? 'Stopped' : 'Complete';
            updateStatus();
        }

        startBtn.addEventListener('click', function() {
            processAll();
        });

        stopBtn.addEventListener('click', function() {
            shouldStop = true;
            log('Stopping...');
        });

        // Initial status check
        updateStatus();
    })();
    </script>
    <?php
}
