<?php
/**
 * Artist Directory API
 * Handles AJAX filtering and search for artist directory
 */

add_action('rest_api_init', function () {
    // Get songs by taxonomy (for genre/mood pages)
    register_rest_route('FML/v1', '/taxonomy-songs', [
        'methods' => 'GET',
        'callback' => 'fml_get_taxonomy_songs',
        'permission_callback' => '__return_true',
        'args' => [
            'taxonomy' => ['type' => 'string', 'required' => true],
            'term' => ['type' => 'string', 'required' => true],
            'q' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'page' => ['type' => 'integer', 'default' => 1],
            'per_page' => ['type' => 'integer', 'default' => 20],
            'orderby' => ['type' => 'string', 'default' => 'title'],
        ],
    ]);
    // Get artists with filtering
    register_rest_route('FML/v1', '/artists', [
        'methods' => 'GET',
        'callback' => 'fml_get_artists',
        'permission_callback' => '__return_true',
        'args' => [
            'q' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'genre' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'mood' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'page' => ['type' => 'integer', 'default' => 1],
            'per_page' => ['type' => 'integer', 'default' => 20],
            'orderby' => ['type' => 'string', 'default' => 'name'],
        ],
    ]);

    // Get all genres/moods that have artists
    register_rest_route('FML/v1', '/artists/filters', [
        'methods' => 'GET',
        'callback' => 'fml_get_artist_filters',
        'permission_callback' => '__return_true',
    ]);

    // Rebuild artist index
    register_rest_route('FML/v1', '/artists/reindex', [
        'methods' => 'POST',
        'callback' => 'fml_reindex_artists',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
});

/**
 * Get aggregated genres/moods for an artist from their songs
 */
function fml_get_artist_taxonomies($artist_id) {
    // Check cache first
    $cache_key = 'artist_taxonomies_' . $artist_id;
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $genres = [];
    $moods = [];

    // Get all songs by this artist using Pods
    $song_pods = pods('song', [
        'where' => 'artist.ID = ' . intval($artist_id),
        'limit' => -1,
    ]);

    $song_count = 0;

    if ($song_pods->total() > 0) {
        while ($song_pods->fetch()) {
            $song_count++;
            $song_id = $song_pods->id();

            // Get genres
            $song_genres = wp_get_post_terms($song_id, 'genre', ['fields' => 'all']);
            if (!is_wp_error($song_genres)) {
                foreach ($song_genres as $term) {
                    if (!isset($genres[$term->term_id])) {
                        $genres[$term->term_id] = [
                            'id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                            'count' => 0
                        ];
                    }
                    $genres[$term->term_id]['count']++;
                }
            }

            // Get moods
            $song_moods = wp_get_post_terms($song_id, 'mood', ['fields' => 'all']);
            if (!is_wp_error($song_moods)) {
                foreach ($song_moods as $term) {
                    if (!isset($moods[$term->term_id])) {
                        $moods[$term->term_id] = [
                            'id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                            'count' => 0
                        ];
                    }
                    $moods[$term->term_id]['count']++;
                }
            }
        }
    }

    // Sort by count descending
    usort($genres, fn($a, $b) => $b['count'] - $a['count']);
    usort($moods, fn($a, $b) => $b['count'] - $a['count']);

    $result = [
        'genres' => array_values($genres),
        'moods' => array_values($moods),
        'song_count' => $song_count
    ];

    // Cache for 1 hour
    set_transient($cache_key, $result, HOUR_IN_SECONDS);

    return $result;
}

/**
 * Get artists with filtering
 */
function fml_get_artists($request) {
    $q = $request->get_param('q');
    $genre = $request->get_param('genre');
    $mood = $request->get_param('mood');
    $page = max(1, intval($request->get_param('page')));
    $per_page = min(100, max(1, intval($request->get_param('per_page'))));
    $orderby = $request->get_param('orderby');

    // Build query args
    $args = [
        'post_type' => 'artist',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $page,
    ];

    // Search by name
    if (!empty($q)) {
        $args['s'] = $q;
    }

    // Order
    switch ($orderby) {
        case 'date':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        case 'songs':
            // Will sort after fetch
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'albums':
            // Will sort after fetch
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        default:
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
    }

    // If filtering by genre or mood, we need to find artists who have songs with those taxonomies
    $artist_ids_filter = null;

    // Parse comma-separated values for multi-select
    $genres = !empty($genre) ? array_map('trim', explode(',', $genre)) : [];
    $moods = !empty($mood) ? array_map('trim', explode(',', $mood)) : [];

    if (!empty($genres) || !empty($moods)) {
        // Build tax_query for songs
        $song_args = [
            'post_type' => 'song',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => ['relation' => 'AND'],
        ];

        if (!empty($genres)) {
            $song_args['tax_query'][] = [
                'taxonomy' => 'genre',
                'field' => 'slug',
                'terms' => $genres,
                'operator' => 'IN' // Match ANY of the selected genres
            ];
        }

        if (!empty($moods)) {
            $song_args['tax_query'][] = [
                'taxonomy' => 'mood',
                'field' => 'slug',
                'terms' => $moods,
                'operator' => 'IN' // Match ANY of the selected moods
            ];
        }

        $filtered_songs = get_posts($song_args);

        // Get unique artist IDs from these songs using Pods
        $artist_ids_filter = [];
        foreach ($filtered_songs as $song_id) {
            $song_pod = pods('song', $song_id);
            $artist_data = $song_pod->field('artist');
            if (!empty($artist_data)) {
                $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
                if ($artist_id) {
                    $artist_ids_filter[] = intval($artist_id);
                }
            }
        }
        $artist_ids_filter = array_unique($artist_ids_filter);

        if (empty($artist_ids_filter)) {
            return [
                'artists' => [],
                'total' => 0,
                'pages' => 0,
                'page' => $page,
            ];
        }

        $args['post__in'] = $artist_ids_filter;
    }

    $query = new WP_Query($args);
    $artists = [];

    foreach ($query->posts as $post) {
        $artist_id = $post->ID;
        $taxonomies = fml_get_artist_taxonomies($artist_id);

        // Get artist data using Pods
        $artist_pod = pods('artist', $artist_id);

        // Get profile image
        $profile_image = $artist_pod->field('profile_image');
        $image_url = '';
        if ($profile_image) {
            $image_id = is_array($profile_image) ? $profile_image['ID'] : $profile_image;
            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'medium');
            }
        }

        // Get albums
        $albums = $artist_pod->field('albums');
        $album_count = 0;
        if (is_array($albums)) {
            $album_count = count($albums);
        } elseif ($albums && is_object($albums)) {
            $album_count = 1;
        }

        $artists[] = [
            'id' => $artist_id,
            'name' => $post->post_title,
            'permalink' => get_permalink($artist_id),
            'image' => $image_url ?: '',
            'date_joined' => $post->post_date,
            'date_joined_formatted' => date('F j, Y', strtotime($post->post_date)),
            'albums' => $album_count,
            'songs' => $taxonomies['song_count'],
            'genres' => array_slice($taxonomies['genres'], 0, 5), // Top 5 genres
            'moods' => array_slice($taxonomies['moods'], 0, 5), // Top 5 moods
        ];
    }

    // Sort by songs or albums if needed
    if ($orderby === 'songs') {
        usort($artists, fn($a, $b) => $b['songs'] - $a['songs']);
    } elseif ($orderby === 'albums') {
        usort($artists, fn($a, $b) => $b['albums'] - $a['albums']);
    }

    return [
        'artists' => $artists,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
        'page' => $page,
    ];
}

/**
 * Get all available genre/mood filters
 */
function fml_get_artist_filters($request) {
    // Check cache
    $cache_key = 'artist_directory_filters';
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    // Get all genres with songs
    $genres = get_terms([
        'taxonomy' => 'genre',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC',
    ]);

    $genre_list = [];
    if (!is_wp_error($genres)) {
        foreach ($genres as $term) {
            $genre_list[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count,
            ];
        }
    }

    // Get all moods with songs
    $moods = get_terms([
        'taxonomy' => 'mood',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC',
    ]);

    $mood_list = [];
    if (!is_wp_error($moods)) {
        foreach ($moods as $term) {
            $mood_list[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count,
            ];
        }
    }

    $result = [
        'genres' => $genre_list,
        'moods' => $mood_list,
    ];

    // Cache for 1 hour
    set_transient($cache_key, $result, HOUR_IN_SECONDS);

    return $result;
}

/**
 * Clear artist taxonomy cache when a song is saved
 */
function fml_clear_artist_cache_on_song_save($post_id, $post, $update) {
    if ($post->post_type !== 'song') {
        return;
    }

    // Use Pods to get the artist relationship
    $song_pod = pods('song', $post_id);
    $artist_data = $song_pod->field('artist');

    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        if ($artist_id) {
            delete_transient('artist_taxonomies_' . $artist_id);
        }
    }

    // Also clear the filters cache
    delete_transient('artist_directory_filters');
}
add_action('save_post', 'fml_clear_artist_cache_on_song_save', 10, 3);

/**
 * Reindex all artists (admin only)
 */
function fml_reindex_artists($request) {
    // Clear all artist taxonomy caches
    global $wpdb;

    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_artist_taxonomies_%'"
    );
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_artist_taxonomies_%'"
    );

    delete_transient('artist_directory_filters');

    return ['success' => true, 'message' => 'Artist index cleared. Caches will rebuild on next request.'];
}

/**
 * Get songs by taxonomy term
 */
function fml_get_taxonomy_songs($request) {
    $taxonomy = $request->get_param('taxonomy');
    $term_slug = $request->get_param('term');
    $q = $request->get_param('q');
    $page = max(1, intval($request->get_param('page')));
    $per_page = min(100, max(1, intval($request->get_param('per_page'))));
    $orderby = $request->get_param('orderby');

    // Validate taxonomy
    if (!in_array($taxonomy, ['genre', 'mood'])) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy', ['status' => 400]);
    }

    // Get term
    $term = get_term_by('slug', $term_slug, $taxonomy);
    if (!$term) {
        return new WP_Error('term_not_found', 'Term not found', ['status' => 404]);
    }

    // Build query
    $args = [
        'post_type' => 'song',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'tax_query' => [
            [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term->term_id,
            ]
        ]
    ];

    // Search
    if (!empty($q)) {
        $args['s'] = $q;
    }

    // Sorting
    switch ($orderby) {
        case 'title_desc':
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
            break;
        case 'date':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        case 'date_asc':
            $args['orderby'] = 'date';
            $args['order'] = 'ASC';
            break;
        case 'artist':
            // Will sort after fetch
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'popular':
            $args['meta_key'] = 'gt_post_view_count';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
            break;
        default:
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
    }

    $query = new WP_Query($args);
    $songs = [];

    foreach ($query->posts as $post) {
        $song_id = $post->ID;
        $song_pod = pods('song', $song_id);

        // Get artist info
        $artist_name = 'Unknown Artist';
        $artist_permalink = '';
        $artist_data = $song_pod->field('artist');
        if (!empty($artist_data)) {
            $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
            $artist_pod = pods('artist', $artist_id);
            if ($artist_pod && $artist_pod->exists()) {
                $artist_name = $artist_pod->field('post_title');
                $artist_permalink = get_permalink($artist_id);
            }
        }

        // Get album info
        $album_name = '';
        $album_permalink = '';
        $cover_art = '';
        $album_data = $song_pod->field('album');
        if (!empty($album_data)) {
            $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
            $album_pod = pods('album', $album_id);
            if ($album_pod && $album_pod->exists()) {
                $album_name = $album_pod->field('post_title');
                $album_permalink = get_permalink($album_id);
                $cover_art = get_the_post_thumbnail_url($album_id, 'thumbnail') ?: '';
            }
        }

        // Get genres and moods
        $genres = [];
        $song_genres = wp_get_post_terms($song_id, 'genre');
        if (!is_wp_error($song_genres)) {
            foreach ($song_genres as $g) {
                $genres[] = ['name' => $g->name, 'slug' => $g->slug];
            }
        }

        $moods = [];
        $song_moods = wp_get_post_terms($song_id, 'mood');
        if (!is_wp_error($song_moods)) {
            foreach ($song_moods as $m) {
                $moods[] = ['name' => $m->name, 'slug' => $m->slug];
            }
        }

        $songs[] = [
            'id' => $song_id,
            'name' => $post->post_title,
            'permalink' => get_permalink($song_id),
            'audio_url' => $song_pod->field('audio_url'),
            'cover_art' => $cover_art,
            'artist_name' => $artist_name,
            'artist_permalink' => $artist_permalink,
            'album_name' => $album_name,
            'album_permalink' => $album_permalink,
            'genres' => $genres,
            'moods' => $moods,
        ];
    }

    // Sort by artist if needed
    if ($orderby === 'artist') {
        usort($songs, fn($a, $b) => strcasecmp($a['artist_name'], $b['artist_name']));
    }

    return [
        'songs' => $songs,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
        'page' => $page,
    ];
}
