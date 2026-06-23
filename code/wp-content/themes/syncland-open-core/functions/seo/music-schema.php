<?php
/**
 * Music Schema.org Structured Data
 *
 * Outputs MusicRecording, MusicAlbum, and MusicGroup JSON-LD
 * by hooking into The SEO Framework's schema graph.
 */

if (!defined('ABSPATH')) exit;

add_filter('the_seo_framework_schema_graph_data', 'fml_music_schema_graph', 10, 2);

/**
 * Append music-specific schema entities to TSF's JSON-LD graph
 */
function fml_music_schema_graph($graph, $args) {
    if (!is_singular()) return $graph;

    $post = get_post();
    if (!$post) return $graph;

    switch ($post->post_type) {
        case 'song':
            $entity = fml_schema_music_recording($post);
            if ($entity) $graph[] = $entity;
            break;
        case 'album':
            $entity = fml_schema_music_album($post);
            if ($entity) $graph[] = $entity;
            break;
        case 'artist':
            $entity = fml_schema_music_group($post);
            if ($entity) $graph[] = $entity;
            break;
    }

    return $graph;
}

/**
 * MusicRecording schema for individual songs
 */
function fml_schema_music_recording($post) {
    $pod = pods('song', $post->ID);
    if (!$pod || !$pod->exists()) return null;

    $schema = [
        '@type' => 'MusicRecording',
        '@id'   => get_permalink($post->ID) . '#musicrecording',
        'name'  => $post->post_title,
        'url'   => get_permalink($post->ID),
    ];

    // Duration in ISO 8601
    $duration = $pod->field('duration');
    if ($duration && is_numeric($duration) && intval($duration) > 0) {
        $secs = intval($duration);
        $mins = floor($secs / 60);
        $rem  = $secs % 60;
        $schema['duration'] = sprintf('PT%dM%dS', $mins, $rem);
    }

    // Audio URL
    $audio_url = $pod->field('audio_url');
    if ($audio_url) {
        $schema['audio'] = [
            '@type'      => 'AudioObject',
            'contentUrl' => $audio_url,
            'encodingFormat' => 'audio/mpeg',
        ];
    }

    // BPM
    $bpm = $pod->field('bpm');
    if (is_array($bpm)) {
        $bpm = isset($bpm['value']) ? $bpm['value'] : (isset($bpm[0]) ? $bpm[0] : '');
    }
    if ($bpm && is_numeric($bpm)) {
        $schema['additionalProperty'] = [
            '@type' => 'PropertyValue',
            'name'  => 'BPM',
            'value' => $bpm,
        ];
    }

    // Musical key
    $key = $pod->field('key');
    if ($key) {
        $schema['musicalKey'] = $key;
    }

    // Artist (byArtist)
    $artist_data = $pod->field('artist');
    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $schema['byArtist'] = [
                '@type' => 'MusicGroup',
                '@id'   => get_permalink($artist_id) . '#musicgroup',
                'name'  => $artist_pod->field('post_title'),
                'url'   => get_permalink($artist_id),
            ];
        }
    }

    // Album (inAlbum)
    $album_data = $pod->field('album');
    if (!empty($album_data)) {
        $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
        $album_pod = pods('album', $album_id);
        if ($album_pod && $album_pod->exists()) {
            $schema['inAlbum'] = [
                '@type' => 'MusicAlbum',
                '@id'   => get_permalink($album_id) . '#musicalbum',
                'name'  => $album_pod->field('post_title'),
                'url'   => get_permalink($album_id),
            ];

            // Cover art from album
            $cover = get_the_post_thumbnail_url($album_id, 'large');
            if ($cover) {
                $schema['image'] = $cover;
            }
        }
    }

    // Fallback image from song
    if (empty($schema['image'])) {
        $cover = get_the_post_thumbnail_url($post->ID, 'large');
        if ($cover) {
            $schema['image'] = $cover;
        }
    }

    // Genre
    $genres = wp_get_post_terms($post->ID, 'genre', ['fields' => 'names']);
    if (!is_wp_error($genres) && !empty($genres)) {
        $schema['genre'] = count($genres) === 1 ? $genres[0] : $genres;
    }

    // Date published
    $schema['datePublished'] = get_the_date('c', $post->ID);

    // Licensing
    $schema['license'] = 'https://creativecommons.org/licenses/by/4.0/';
    $schema['acquireLicensePage'] = get_permalink($post->ID);

    return $schema;
}

/**
 * MusicAlbum schema for albums
 */
function fml_schema_music_album($post) {
    $pod = pods('album', $post->ID);
    if (!$pod || !$pod->exists()) return null;

    $schema = [
        '@type' => 'MusicAlbum',
        '@id'   => get_permalink($post->ID) . '#musicalbum',
        'name'  => $post->post_title,
        'url'   => get_permalink($post->ID),
    ];

    // Cover art
    $cover = get_the_post_thumbnail_url($post->ID, 'large');
    if ($cover) {
        $schema['image'] = $cover;
    }

    // Release date
    $release_date = $pod->field('release_date');
    if ($release_date) {
        $ts = strtotime($release_date);
        if ($ts) {
            $schema['datePublished'] = date('Y-m-d', $ts);
        }
    }

    // Artist
    $artist_data = $pod->field('artist');
    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $schema['byArtist'] = [
                '@type' => 'MusicGroup',
                '@id'   => get_permalink($artist_id) . '#musicgroup',
                'name'  => $artist_pod->field('post_title'),
                'url'   => get_permalink($artist_id),
            ];
        }
    }

    // Tracks
    $songs_pod = pods('song', [
        'where' => 'album.ID = ' . intval($post->ID),
        'orderby' => 'track_number.meta_value+0 ASC',
        'limit' => -1,
    ]);

    if ($songs_pod->total() > 0) {
        $schema['numTracks'] = $songs_pod->total();
        $tracks = [];
        while ($songs_pod->fetch()) {
            $track = [
                '@type' => 'MusicRecording',
                '@id'   => get_permalink($songs_pod->id()) . '#musicrecording',
                'name'  => $songs_pod->field('post_title'),
                'url'   => get_permalink($songs_pod->id()),
            ];

            $dur = $songs_pod->field('duration');
            if ($dur && is_numeric($dur) && intval($dur) > 0) {
                $secs = intval($dur);
                $track['duration'] = sprintf('PT%dM%dS', floor($secs / 60), $secs % 60);
            }

            $tracks[] = $track;
        }
        $schema['track'] = $tracks;
    }

    // Genres (aggregated from songs)
    $genre_names = [];
    $song_ids = get_posts([
        'post_type' => 'song',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [],
        'meta_query' => [],
        'no_found_rows' => true,
    ]);
    // Get genres from album's songs via Pods relationship
    if (!empty($songs_pod) && $songs_pod->total() > 0) {
        $songs_pod->reset();
        while ($songs_pod->fetch()) {
            $terms = wp_get_post_terms($songs_pod->id(), 'genre', ['fields' => 'names']);
            if (!is_wp_error($terms)) {
                $genre_names = array_merge($genre_names, $terms);
            }
        }
        $genre_names = array_unique($genre_names);
    }
    if (!empty($genre_names)) {
        $schema['genre'] = count($genre_names) === 1 ? reset($genre_names) : array_values($genre_names);
    }

    return $schema;
}

/**
 * MusicGroup schema for artists
 */
function fml_schema_music_group($post) {
    $pod = pods('artist', $post->ID);
    if (!$pod || !$pod->exists()) return null;

    $schema = [
        '@type' => 'MusicGroup',
        '@id'   => get_permalink($post->ID) . '#musicgroup',
        'name'  => $post->post_title,
        'url'   => get_permalink($post->ID),
    ];

    // Artist image
    $image = get_the_post_thumbnail_url($post->ID, 'large');
    if ($image) {
        $schema['image'] = $image;
    }

    // Description
    $description = $post->post_excerpt ?: wp_trim_words(strip_tags($post->post_content), 30);
    if ($description) {
        $schema['description'] = $description;
    }

    // Social / sameAs links
    $same_as = [];
    $social_fields = ['website', 'apple_music', 'bandcamp', 'facebook', 'instagram', 'soundcloud', 'spotify', 'youtube'];
    foreach ($social_fields as $field) {
        $url = $pod->field($field);
        if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
            $same_as[] = $url;
        }
    }
    if (!empty($same_as)) {
        $schema['sameAs'] = $same_as;
    }

    // Albums
    $albums_pod = pods('album', [
        'where' => 'artist.ID = ' . intval($post->ID),
        'orderby' => 'release_date.meta_value DESC',
        'limit' => -1,
    ]);

    if ($albums_pod->total() > 0) {
        $albums = [];
        while ($albums_pod->fetch()) {
            $album_schema = [
                '@type' => 'MusicAlbum',
                '@id'   => get_permalink($albums_pod->id()) . '#musicalbum',
                'name'  => $albums_pod->field('post_title'),
                'url'   => get_permalink($albums_pod->id()),
            ];
            $release = $albums_pod->field('release_date');
            if ($release) {
                $ts = strtotime($release);
                if ($ts) $album_schema['datePublished'] = date('Y-m-d', $ts);
            }
            $albums[] = $album_schema;
        }
        $schema['album'] = $albums;
    }

    // Genres (aggregated from songs)
    $songs = get_posts([
        'post_type' => 'song',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            ['key' => 'artist', 'value' => $post->ID, 'compare' => '='],
        ],
    ]);
    // Pods stores relationships differently, use Pods query
    $artist_songs = pods('song', [
        'where' => 'artist.ID = ' . intval($post->ID),
        'limit' => -1,
    ]);
    $genre_names = [];
    if ($artist_songs->total() > 0) {
        while ($artist_songs->fetch()) {
            $terms = wp_get_post_terms($artist_songs->id(), 'genre', ['fields' => 'names']);
            if (!is_wp_error($terms)) {
                $genre_names = array_merge($genre_names, $terms);
            }
        }
        $genre_names = array_unique($genre_names);
    }
    if (!empty($genre_names)) {
        $schema['genre'] = count($genre_names) === 1 ? reset($genre_names) : array_values($genre_names);
    }

    return $schema;
}
