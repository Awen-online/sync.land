<?php
/**
 * Songs Discovery Shortcode
 * Browse all songs with waveforms, filtering by genre/mood/BPM, sorting, and playback
 * Usage: [songs_discovery] on any page (intended for /songs)
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the songs-discover REST endpoint
 */
add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/songs-discover', [
        'methods'  => 'GET',
        'callback' => 'fml_songs_discover_callback',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'q' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'genre' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'mood' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'bpm_min' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'bpm_max' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'page' => [
                'required' => false,
                'default' => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ],
            'per_page' => [
                'required' => false,
                'default' => 20,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0 && intval($param) <= 50;
                }
            ],
            'orderby' => [
                'required' => false,
                'default' => 'date',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ]);
});

/**
 * Songs discover endpoint callback
 */
function fml_songs_discover_callback(WP_REST_Request $request) {
    $page     = max(1, intval($request->get_param('page') ?: 1));
    $per_page = min(50, max(1, intval($request->get_param('per_page') ?: 20)));
    $q        = sanitize_text_field($request->get_param('q') ?: '');
    $orderby  = sanitize_text_field($request->get_param('orderby') ?: 'date');

    $args = [
        'post_type'      => 'song',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    ];

    // Search
    if (!empty($q)) {
        $args['s'] = $q;
    }

    // Taxonomy filters
    $tax_query = [];
    $genres = $request->get_param('genre');
    if (!empty($genres)) {
        $tax_query[] = [
            'taxonomy' => 'genre',
            'field'    => 'term_id',
            'terms'    => array_map('intval', explode(',', $genres)),
        ];
    }
    $moods = $request->get_param('mood');
    if (!empty($moods)) {
        $tax_query[] = [
            'taxonomy' => 'mood',
            'field'    => 'term_id',
            'terms'    => array_map('intval', explode(',', $moods)),
        ];
    }
    if (!empty($tax_query)) {
        $tax_query['relation'] = 'AND';
        $args['tax_query'] = $tax_query;
    }

    // BPM range
    $bpm_min = $request->get_param('bpm_min');
    $bpm_max = $request->get_param('bpm_max');
    $meta_query = [];
    if ($bpm_min !== null && $bpm_min !== '') {
        $meta_query[] = ['key' => 'bpm', 'value' => intval($bpm_min), 'compare' => '>=', 'type' => 'NUMERIC'];
    }
    if ($bpm_max !== null && $bpm_max !== '') {
        $meta_query[] = ['key' => 'bpm', 'value' => intval($bpm_max), 'compare' => '<=', 'type' => 'NUMERIC'];
    }
    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    // Sorting
    switch ($orderby) {
        case 'title':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'title_desc':
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
            break;
        case 'bpm':
            $args['meta_key'] = 'bpm';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'ASC';
            break;
        case 'popular':
            $args['meta_key'] = 'gt_post_view_count';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
            break;
        case 'date_asc':
            $args['orderby'] = 'date';
            $args['order'] = 'ASC';
            break;
        case 'date':
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
    }

    $query = new WP_Query($args);
    $songs = [];
    $needs_artist_sort = ($orderby === 'artist');

    foreach ($query->posts as $post) {
        $pod = pods('song', $post->ID);

        // Artist
        $artist_data = $pod->field('artist');
        $artist_name = 'Unknown Artist';
        $artist_permalink = '';
        if (!empty($artist_data)) {
            $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
            $artist_pod = pods('artist', $artist_id);
            if ($artist_pod && $artist_pod->exists()) {
                $artist_name = $artist_pod->field('post_title');
                $artist_permalink = get_permalink($artist_id);
            }
        }

        // Album + cover art
        $album_data = $pod->field('album');
        $album_name = '';
        $album_permalink = '';
        $cover_art_url = '';
        if (!empty($album_data)) {
            $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
            $album_pod = pods('album', $album_id);
            if ($album_pod && $album_pod->exists()) {
                $album_name = $album_pod->field('post_title');
                $album_permalink = get_permalink($album_id);
                $cover_art_url = get_the_post_thumbnail_url($album_id, 'medium') ?: '';
            }
        }
        if (empty($cover_art_url)) {
            $cover_art_url = get_the_post_thumbnail_url($post->ID, 'medium') ?: '';
        }

        // Genres
        $genres_arr = [];
        $genre_terms = wp_get_post_terms($post->ID, 'genre', ['fields' => 'all']);
        if (!is_wp_error($genre_terms)) {
            foreach ($genre_terms as $t) {
                $genres_arr[] = ['name' => $t->name, 'slug' => $t->slug];
            }
        }

        // Moods
        $moods_arr = [];
        $mood_terms = wp_get_post_terms($post->ID, 'mood', ['fields' => 'all']);
        if (!is_wp_error($mood_terms)) {
            foreach ($mood_terms as $t) {
                $moods_arr[] = ['name' => $t->name, 'slug' => $t->slug];
            }
        }

        // BPM
        $bpm = $pod->field('bpm');
        if (is_array($bpm)) {
            $bpm = isset($bpm['value']) ? $bpm['value'] : (isset($bpm[0]) ? $bpm[0] : '');
        }

        // Duration
        $duration = $pod->field('duration');

        // Waveform peaks
        $waveform_peaks = get_post_meta($post->ID, '_waveform_peaks', true);

        $songs[] = [
            'id'               => $post->ID,
            'song_id'          => $post->ID,
            'name'             => $post->post_title,
            'url'              => $pod->field('audio_url') ?: '',
            'cover_art_url'    => $cover_art_url,
            'permalink'        => get_permalink($post->ID),
            'duration'         => $duration,
            'bpm'              => $bpm,
            'key'              => $pod->field('key'),
            'artist'           => $artist_name,
            'artist_permalink' => $artist_permalink,
            'album'            => $album_name,
            'album_permalink'  => $album_permalink,
            'genres'           => $genres_arr,
            'moods'            => $moods_arr,
            'waveform_peaks'   => $waveform_peaks ?: [],
        ];
    }

    // Post-query sort by artist name
    if ($needs_artist_sort) {
        usort($songs, function($a, $b) {
            return strcasecmp($a['artist'], $b['artist']);
        });
    }

    return fml_api_success([
        'songs' => $songs,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
        'page'  => $page,
    ]);
}

/**
 * Songs Discovery Shortcode
 */
function songs_discovery_shortcode($atts) {
    $atts = shortcode_atts([
        'per_page' => 20,
    ], $atts);

    $per_page = intval($atts['per_page']);

    // Assets enqueued globally in functions.php for PJAX support

    // Get all genre and mood terms for filter dropdowns
    $genre_terms = get_terms(['taxonomy' => 'genre', 'hide_empty' => true, 'orderby' => 'name']);
    $mood_terms = get_terms(['taxonomy' => 'mood', 'hide_empty' => true, 'orderby' => 'name']);

    $genres_list = [];
    if (!is_wp_error($genre_terms)) {
        foreach ($genre_terms as $t) {
            $genres_list[] = ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count];
        }
    }

    $moods_list = [];
    if (!is_wp_error($mood_terms)) {
        foreach ($mood_terms as $t) {
            $moods_list[] = ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count];
        }
    }

    // Get total song count
    $total_songs = wp_count_posts('song');
    $total_published = $total_songs->publish ?? 0;

    // Localize script data
    wp_localize_script('songs-discovery', 'songsDiscoveryData', [
        'restUrl'  => rest_url('FML/v1/songs-discover'),
        'nonce'    => wp_create_nonce('wp_rest'),
        'perPage'  => $per_page,
        'genres'   => $genres_list,
        'moods'    => $moods_list,
        'totalSongs' => $total_published,
    ]);

    ob_start();
    ?>
    <div id="songs-discovery-page" class="songs-discovery-page" data-per-page="<?php echo $per_page; ?>">

        <!-- Header -->
        <div class="songs-discovery-header">
            <div class="songs-discovery-header-content">
                <h1 class="songs-discovery-title">
                    <i class="fas fa-music"></i> Songs
                </h1>
                <p class="songs-discovery-subtitle">
                    Discover <span class="songs-total-count"><?php echo number_format($total_published); ?></span> tracks available for licensing
                </p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="songs-discovery-filters">
            <div class="songs-filter-search">
                <input type="text"
                       id="songs-search-input"
                       class="songs-search-input"
                       placeholder="Search songs, artists..."
                       autocomplete="off">
                <i class="fas fa-search songs-search-icon"></i>
            </div>

            <div class="songs-filter-group">
                <!-- Genre Multiselect -->
                <div class="songs-filter-multiselect" data-filter="genre">
                    <button type="button" class="songs-filter-btn">
                        <i class="fas fa-guitar"></i>
                        <span class="filter-label">Genre</span>
                        <span class="filter-count" style="display:none;"></span>
                        <i class="fas fa-chevron-down filter-arrow"></i>
                    </button>
                    <div class="songs-filter-dropdown">
                        <input type="text" class="filter-dropdown-search" placeholder="Search genres...">
                        <div class="filter-dropdown-list">
                            <!-- Populated by JS -->
                        </div>
                        <div class="filter-dropdown-actions">
                            <button type="button" class="filter-clear-btn">Clear</button>
                            <button type="button" class="filter-apply-btn">Apply</button>
                        </div>
                    </div>
                </div>

                <!-- Mood Multiselect -->
                <div class="songs-filter-multiselect" data-filter="mood">
                    <button type="button" class="songs-filter-btn">
                        <i class="fas fa-heart"></i>
                        <span class="filter-label">Mood</span>
                        <span class="filter-count" style="display:none;"></span>
                        <i class="fas fa-chevron-down filter-arrow"></i>
                    </button>
                    <div class="songs-filter-dropdown">
                        <input type="text" class="filter-dropdown-search" placeholder="Search moods...">
                        <div class="filter-dropdown-list">
                            <!-- Populated by JS -->
                        </div>
                        <div class="filter-dropdown-actions">
                            <button type="button" class="filter-clear-btn">Clear</button>
                            <button type="button" class="filter-apply-btn">Apply</button>
                        </div>
                    </div>
                </div>

                <!-- BPM Range -->
                <div class="songs-filter-bpm">
                    <span class="bpm-label"><i class="fas fa-drum"></i> BPM</span>
                    <input type="number" id="bpm-min" class="bpm-input" placeholder="Min" min="0" max="300">
                    <span class="bpm-sep">–</span>
                    <input type="number" id="bpm-max" class="bpm-input" placeholder="Max" min="0" max="300">
                </div>
            </div>

            <div class="songs-filter-actions">
                <!-- Sort -->
                <div class="songs-sort-wrap">
                    <label class="sort-label"><i class="fas fa-sort"></i> Sort</label>
                    <select id="songs-sort-select" class="songs-sort-select">
                        <option value="date">Newest First</option>
                        <option value="date_asc">Oldest First</option>
                        <option value="title">Title (A-Z)</option>
                        <option value="title_desc">Title (Z-A)</option>
                        <option value="artist">Artist (A-Z)</option>
                        <option value="bpm">BPM (Low-High)</option>
                        <option value="popular">Most Popular</option>
                    </select>
                </div>

                <!-- Play All -->
                <button type="button" id="songs-play-all" class="play-all-btn">
                    <i class="fas fa-play"></i> Play All
                </button>
            </div>
        </div>

        <!-- Active Filters -->
        <div id="songs-active-filters" class="songs-active-filters" style="display:none;">
            <!-- Active filter tags rendered by JS -->
        </div>

        <!-- Results Info -->
        <div class="songs-results-info">
            <span id="songs-results-count">Loading songs...</span>
        </div>

        <!-- Loading -->
        <div id="songs-loading" class="songs-loading">
            <div class="songs-loading-spinner"></div>
        </div>

        <!-- Songs Container -->
        <div id="songs-discovery-container" class="songs-discovery-container">
            <!-- Songs rendered by JS -->
        </div>

        <!-- Pagination -->
        <div id="songs-discovery-pagination" class="songs-discovery-pagination">
            <!-- Pagination rendered by JS -->
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('songs_discovery', 'songs_discovery_shortcode');
