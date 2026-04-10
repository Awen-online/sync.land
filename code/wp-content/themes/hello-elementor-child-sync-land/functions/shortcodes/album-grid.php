<?php
/**
 * Album Grid Shortcode
 *
 * Display albums in a grid or list format with play functionality, metadata, and genre/mood word clouds.
 * Usage: [album_grid artist_id="123"] or [album_grid] (auto-detects artist on artist pages)
 */

add_shortcode('album_grid', 'fml_album_grid_shortcode');

function fml_album_grid_shortcode($atts) {
    $atts = shortcode_atts([
        'artist_id' => '',
        'limit' => -1,
        'orderby' => 'release_date',
        'order' => 'DESC',
        'columns' => 3,
        'view' => 'grid',
        'show_sorting' => 'true',
        'show_word_cloud' => 'true',
    ], $atts);

    // Auto-detect artist ID if on artist page
    $artist_id = $atts['artist_id'];
    if (empty($artist_id)) {
        $post = get_post();
        if ($post && $post->post_type === 'artist') {
            $artist_id = $post->ID;
        }
    }

    if (empty($artist_id)) {
        return '<p class="album-grid-error">No artist specified.</p>';
    }

    // Get artist info
    $artist_pod = pods('artist', $artist_id);
    if (!$artist_pod->exists()) {
        return '<p class="album-grid-error">Artist not found.</p>';
    }

    $artist_name = $artist_pod->field('post_title');
    $artist_permalink = get_permalink($artist_id);

    // Get albums for this artist - fetch all for client-side sorting
    $album_params = [
        'where' => "artist.ID = " . intval($artist_id),
        'limit' => intval($atts['limit']),
        'orderby' => 'release_date.meta_value DESC',
    ];

    $albums_pod = pods('album', $album_params);

    if ($albums_pod->total() === 0) {
        return '<p class="album-grid-empty">No albums found.</p>';
    }

    // Enqueue styles and scripts
    wp_enqueue_style('album-grid-css', get_stylesheet_directory_uri() . '/assets/css/album-grid.css', [], '1.1.0');
    wp_enqueue_script('album-grid-js', get_stylesheet_directory_uri() . '/assets/js/album-grid.js', ['jquery'], '1.1.0', true);

    // Calculate total runtime for all albums
    $total_runtime_seconds = 0;
    $albums_data = [];

    // First pass: collect all album data
    while ($albums_pod->fetch()) {
        $album_id = $albums_pod->id();
        $album_data = fml_get_album_grid_data($album_id, $artist_id, $artist_name, $artist_permalink);
        $albums_data[] = $album_data;
        $total_runtime_seconds += $album_data['total_seconds'];
    }

    // Format total runtime
    $total_runtime = fml_format_duration($total_runtime_seconds);
    $total_albums = count($albums_data);
    $total_songs = array_sum(array_column($albums_data, 'song_count'));

    ob_start();
    ?>
    <?php if ($atts['show_sorting'] === 'true'): ?>
    <div class="fml-album-controls">
        <div class="fml-album-stats">
            <span><strong><?php echo $total_albums; ?></strong> albums</span>
            <span><strong><?php echo $total_songs; ?></strong> songs</span>
            <span><strong><?php echo esc_html($total_runtime); ?></strong> total</span>
        </div>
        <div class="fml-album-sorting">
            <div class="fml-sort-options">
                <label>Sort:</label>
                <select class="fml-sort-select">
                    <option value="date-desc">Newest First</option>
                    <option value="date-asc">Oldest First</option>
                    <option value="title-asc">A-Z</option>
                    <option value="title-desc">Z-A</option>
                    <option value="songs-desc">Most Songs</option>
                    <option value="duration-desc">Longest</option>
                </select>
            </div>
            <div class="fml-view-toggle">
                <button class="fml-view-btn <?php echo $atts['view'] === 'grid' ? 'active' : ''; ?>" data-view="grid" title="Grid View">
                    <i class="fas fa-th-large"></i>
                </button>
                <button class="fml-view-btn <?php echo $atts['view'] === 'list' ? 'active' : ''; ?>" data-view="list" title="List View">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="fml-album-grid <?php echo $atts['view'] === 'list' ? 'view-list' : 'view-grid columns-' . esc_attr($atts['columns']); ?>"
         data-artist-id="<?php echo esc_attr($artist_id); ?>"
         data-columns="<?php echo esc_attr($atts['columns']); ?>"
         data-view="<?php echo esc_attr($atts['view']); ?>"
         data-show-word-cloud="<?php echo esc_attr($atts['show_word_cloud']); ?>">
        <?php foreach ($albums_data as $album_data): ?>
        <div class="fml-album-card"
             data-album-id="<?php echo esc_attr($album_data['id']); ?>"
             data-title="<?php echo esc_attr(strtolower($album_data['title'])); ?>"
             data-date="<?php echo esc_attr($album_data['release_date']); ?>"
             data-songs="<?php echo esc_attr($album_data['song_count']); ?>"
             data-duration="<?php echo esc_attr($album_data['total_seconds']); ?>">
            <!-- Cover Art with Play Overlay -->
            <div class="fml-album-cover">
                <?php if ($album_data['cover_art']): ?>
                    <img src="<?php echo esc_url($album_data['cover_art']); ?>"
                         alt="<?php echo esc_attr($album_data['title']); ?>"
                         loading="lazy">
                <?php else: ?>
                    <div class="fml-album-cover-placeholder">
                        <i class="fas fa-music"></i>
                    </div>
                <?php endif; ?>

                <div class="fml-album-overlay">
                    <button class="fml-album-play-btn"
                            data-album-id="<?php echo esc_attr($album_data['id']); ?>"
                            title="Play Album">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
            </div>

            <!-- Album Info -->
            <div class="fml-album-info">
                <h3 class="fml-album-title">
                    <a href="<?php echo esc_url($album_data['permalink']); ?>">
                        <?php echo esc_html($album_data['title']); ?>
                    </a>
                </h3>

                <!-- Metadata Row -->
                <div class="fml-album-meta">
                    <span class="fml-album-meta-item" title="Songs">
                        <i class="fas fa-music"></i> <?php echo $album_data['song_count']; ?>
                    </span>
                    <?php if ($album_data['release_date']): ?>
                    <span class="fml-album-meta-item" title="Release Date">
                        <i class="fas fa-calendar"></i> <?php echo esc_html($album_data['release_year']); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($album_data['total_duration']): ?>
                    <span class="fml-album-meta-item" title="Duration">
                        <i class="fas fa-clock"></i> <?php echo esc_html($album_data['total_duration']); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Genre/Mood Word Cloud -->
                <?php if ($atts['show_word_cloud'] === 'true' && (!empty($album_data['genres']) || !empty($album_data['moods']))): ?>
                <div class="fml-album-tags">
                    <?php foreach ($album_data['genres'] as $genre): ?>
                    <a href="<?php echo esc_url(get_term_link($genre['term_id'], 'genre')); ?>"
                       class="fml-tag fml-tag-genre"
                       style="font-size: <?php echo fml_get_tag_size($genre['count'], $album_data['max_tag_count']); ?>px;">
                        <?php echo esc_html($genre['name']); ?>
                    </a>
                    <?php endforeach; ?>

                    <?php foreach ($album_data['moods'] as $mood): ?>
                    <a href="<?php echo esc_url(get_term_link($mood['term_id'], 'mood')); ?>"
                       class="fml-tag fml-tag-mood"
                       style="font-size: <?php echo fml_get_tag_size($mood['count'], $album_data['max_tag_count']); ?>px;">
                        <?php echo esc_html($mood['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Hidden song data for player -->
            <div class="fml-album-songs-data" style="display: none;">
                <?php foreach ($album_data['songs'] as $song): ?>
                <div class="song-play"
                     data-audiosrc="<?php echo esc_url($song['audio_url']); ?>"
                     data-songname="<?php echo esc_attr($song['title']); ?>"
                     data-artistname="<?php echo esc_attr($artist_name); ?>"
                     data-albumname="<?php echo esc_attr($album_data['title']); ?>"
                     data-artsrc="<?php echo esc_url($album_data['cover_art']); ?>"
                     data-songid="<?php echo esc_attr($song['id']); ?>"
                     data-permalink="<?php echo esc_url($song['permalink']); ?>"
                     data-artistpermalink="<?php echo esc_url($artist_permalink); ?>"
                     data-albumpermalink="<?php echo esc_url($album_data['permalink']); ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Format seconds into human readable duration
 */
function fml_format_duration($seconds) {
    if ($seconds <= 0) return '';

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    } else {
        return $minutes . ' min';
    }
}

/**
 * Get all data needed for an album card
 */
function fml_get_album_grid_data($album_id, $artist_id, $artist_name, $artist_permalink) {
    $album_pod = pods('album', $album_id);

    $data = [
        'id' => $album_id,
        'title' => $album_pod->field('post_title'),
        'permalink' => get_permalink($album_id),
        'cover_art' => get_the_post_thumbnail_url($album_id, 'medium_large'),
        'release_date' => $album_pod->field('release_date'),
        'release_year' => '',
        'song_count' => 0,
        'total_duration' => '',
        'total_seconds' => 0,
        'genres' => [],
        'moods' => [],
        'songs' => [],
        'first_song' => null,
        'max_tag_count' => 1,
    ];

    // Parse release year
    if ($data['release_date']) {
        $date = strtotime($data['release_date']);
        if ($date) {
            $data['release_year'] = date('Y', $date);
        }
    }

    // Get songs for this album
    $songs_pod = pods('song', [
        'where' => "album.ID = " . intval($album_id),
        'orderby' => 'track_number.meta_value+0 ASC',
        'limit' => -1,
    ]);

    $total_seconds = 0;
    $genre_counts = [];
    $mood_counts = [];

    if ($songs_pod->total() > 0) {
        $data['song_count'] = $songs_pod->total();

        while ($songs_pod->fetch()) {
            $song_id = $songs_pod->id();
            $audio_url = $songs_pod->field('audio_url');

            // Skip songs without audio
            if (empty($audio_url)) {
                $audio_url = $songs_pod->field('audio_url_lossless');
            }
            if (empty($audio_url)) {
                continue;
            }

            $song = [
                'id' => $song_id,
                'title' => $songs_pod->field('post_title'),
                'audio_url' => $audio_url,
                'permalink' => get_permalink($song_id),
                'duration' => $songs_pod->field('duration'),
            ];

            $data['songs'][] = $song;

            if (!$data['first_song']) {
                $data['first_song'] = $song;
            }

            // Add duration
            $duration = intval($songs_pod->field('duration'));
            if ($duration > 0) {
                $total_seconds += $duration;
            }

            // Collect genres
            $song_genres = wp_get_post_terms($song_id, 'genre', ['fields' => 'all']);
            if (!is_wp_error($song_genres)) {
                foreach ($song_genres as $term) {
                    if (!isset($genre_counts[$term->term_id])) {
                        $genre_counts[$term->term_id] = [
                            'term_id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                            'count' => 0,
                        ];
                    }
                    $genre_counts[$term->term_id]['count']++;
                }
            }

            // Collect moods
            $song_moods = wp_get_post_terms($song_id, 'mood', ['fields' => 'all']);
            if (!is_wp_error($song_moods)) {
                foreach ($song_moods as $term) {
                    if (!isset($mood_counts[$term->term_id])) {
                        $mood_counts[$term->term_id] = [
                            'term_id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                            'count' => 0,
                        ];
                    }
                    $mood_counts[$term->term_id]['count']++;
                }
            }
        }
    }

    // Store total seconds and format duration
    $data['total_seconds'] = $total_seconds;
    $data['total_duration'] = fml_format_duration($total_seconds);

    // Sort genres and moods by count, limit to top 5 each
    usort($genre_counts, fn($a, $b) => $b['count'] - $a['count']);
    usort($mood_counts, fn($a, $b) => $b['count'] - $a['count']);

    $data['genres'] = array_slice(array_values($genre_counts), 0, 5);
    $data['moods'] = array_slice(array_values($mood_counts), 0, 5);

    // Calculate max tag count for word cloud sizing
    $all_counts = array_merge(
        array_column($data['genres'], 'count'),
        array_column($data['moods'], 'count')
    );
    $data['max_tag_count'] = !empty($all_counts) ? max($all_counts) : 1;

    return $data;
}

/**
 * Calculate tag font size based on frequency
 */
function fml_get_tag_size($count, $max_count) {
    $min_size = 11;
    $max_size = 16;

    if ($max_count <= 1) {
        return $min_size;
    }

    $ratio = ($count - 1) / ($max_count - 1);
    return round($min_size + ($ratio * ($max_size - $min_size)));
}
