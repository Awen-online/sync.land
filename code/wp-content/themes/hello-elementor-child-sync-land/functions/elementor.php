<?php

/**
 * Ensure Genre and Mood taxonomies have archives enabled
 * This makes them appear in Elementor's Display Conditions
 */
add_action('init', function() {
    // Modify genre taxonomy to ensure it has archives
    $genre_args = [
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'genre', 'with_front' => false],
    ];

    // Only register if not already registered by Pods
    if (!taxonomy_exists('genre')) {
        register_taxonomy('genre', 'song', $genre_args);
    }

    // Modify mood taxonomy to ensure it has archives
    $mood_args = [
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'mood', 'with_front' => false],
    ];

    if (!taxonomy_exists('mood')) {
        register_taxonomy('mood', 'song', $mood_args);
    }
}, 5); // Run early, before Pods

/**
 * Force taxonomy archive settings after Pods registers them
 */
add_action('init', function() {
    global $wp_taxonomies;

    if (isset($wp_taxonomies['genre'])) {
        $wp_taxonomies['genre']->public = true;
        $wp_taxonomies['genre']->publicly_queryable = true;
        $wp_taxonomies['genre']->has_archive = true;
        $wp_taxonomies['genre']->show_in_nav_menus = true;
    }

    if (isset($wp_taxonomies['mood'])) {
        $wp_taxonomies['mood']->public = true;
        $wp_taxonomies['mood']->publicly_queryable = true;
        $wp_taxonomies['mood']->has_archive = true;
        $wp_taxonomies['mood']->show_in_nav_menus = true;
    }
}, 99); // Run late, after Pods

/**
 * Register Genre and Mood taxonomies with Elementor Theme Builder
 * Makes them available in Display Conditions
 */
add_action('elementor/theme/register_conditions', function($conditions_manager) {
    // Only proceed if Elementor Pro's condition base class exists
    if (!class_exists('\ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base')) {
        return;
    }

    // Get the archive condition category
    $archive = $conditions_manager->get_condition('archive');

    if ($archive) {
        // Include our custom conditions
        $conditions_file = get_stylesheet_directory() . '/functions/elementor-conditions.php';
        if (file_exists($conditions_file)) {
            require_once $conditions_file;

            // Only register if classes exist
            if (class_exists('FML_Genre_Archive_Condition')) {
                try {
                    $genre_condition = new FML_Genre_Archive_Condition();
                    $archive->register_sub_condition($genre_condition);
                } catch (Exception $e) {
                    // Silently fail
                }
            }

            if (class_exists('FML_Mood_Archive_Condition')) {
                try {
                    $mood_condition = new FML_Mood_Archive_Condition();
                    $archive->register_sub_condition($mood_condition);
                } catch (Exception $e) {
                    // Silently fail
                }
            }
        }
    }
}, 100);

/**
 * Add taxonomies to Elementor's supported taxonomies list
 */
add_filter('elementor/theme/conditions/taxonomy', function($taxonomies) {
    $taxonomies[] = 'genre';
    $taxonomies[] = 'mood';
    return $taxonomies;
});

/**
 * Make sure taxonomies show in Elementor's query controls
 */
add_filter('elementor_pro/query_control/get_taxonomies', function($taxonomies) {
    $taxonomies['genre'] = 'Genre';
    $taxonomies['mood'] = 'Mood';
    return $taxonomies;
});

function filter_albums_by_artist( $query ) {
    if ( isset( $query->query['post_type'] ) ) {
        // Dynamically get the current artist ID from the context (e.g., if you are on an artist archive page)
        if ( is_singular( 'artist' ) ) {
            $artist_id = get_the_ID(); // Get the current artist ID
        }

        // Query albums related to the specific artist
        if ( isset( $artist_id ) ) {
            $meta_query = array(
                array(
                    'key'     => 'artist', // Pods field for artist relationship
                    'value'   => $artist_id, // Artist ID dynamically set
                    'compare' => '='
                )
            );
            
            $query->set( 'meta_query', $meta_query );
        }
    }
}
add_action( 'elementor/query/albums_filter', 'filter_albums_by_artist' );

