<?php
/**
 * Dynamic SEO meta for song / album / artist CPTs.
 *
 * Generates titles, descriptions, and og:image fallbacks automatically
 * from the Pods relationships (song -> album -> artist) so every public
 * post in those types gets license-intent SEO copy without per-post work.
 *
 * Title format examples:
 *   Song:   {song} by {artist} — License Music for Film & Games | Sync.Land
 *   Album:  {album} by {artist} — Album | Sync.Land Music Licensing
 *   Artist: {artist} — Music by {artist} on Sync.Land
 *
 * Per-post overrides are honored: if SEO Framework already has a manual
 * title or description set for the post, the override wins.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class FML_Dynamic_Meta {

    const SUPPORTED_TYPES = [ 'song', 'album', 'artist' ];

    public static function init() {
        add_filter( 'pre_get_document_title', [ __CLASS__, 'filter_pre_document_title' ], 20 );
        add_filter( 'document_title_parts',   [ __CLASS__, 'filter_document_title_parts' ], 20 );

        add_filter( 'the_seo_framework_generated_description', [ __CLASS__, 'sf_filter_description' ], 20, 3 );
        add_filter( 'the_seo_framework_description_excerpt',   [ __CLASS__, 'sf_filter_description' ], 20, 3 );
        add_filter( 'the_seo_framework_title_from_generation', [ __CLASS__, 'sf_filter_title' ], 20, 2 );

        add_filter( 'get_post_metadata', [ __CLASS__, 'filter_description_meta' ], 10, 4 );
    }

    private static function get_supported_type( $post_id = 0 ) {
        if ( ! $post_id ) {
            $post_id = get_queried_object_id();
        }
        if ( ! $post_id || ! is_singular( self::SUPPORTED_TYPES ) ) {
            return '';
        }
        $type = get_post_type( $post_id );
        return in_array( $type, self::SUPPORTED_TYPES, true ) ? $type : '';
    }

    private static function resolve_id_from_pods_field( $field_value ) {
        if ( empty( $field_value ) ) return 0;
        if ( is_array( $field_value ) ) {
            if ( isset( $field_value['ID'] ) ) return (int) $field_value['ID'];
            $first = reset( $field_value );
            if ( is_array( $first ) && isset( $first['ID'] ) ) return (int) $first['ID'];
            return (int) $first;
        }
        if ( is_object( $field_value ) && isset( $field_value->ID ) ) return (int) $field_value->ID;
        return (int) $field_value;
    }

    private static function resolve_artist_id( $post_id, $type ) {
        if ( ! function_exists( 'pods' ) ) return 0;
        if ( $type === 'artist' ) return $post_id;

        $pod = pods( $type, $post_id );
        if ( ! $pod || ! $pod->exists() ) return 0;

        if ( $type === 'album' ) {
            return self::resolve_id_from_pods_field( $pod->field( 'artist' ) );
        }
        // Song: try direct, then via album
        $direct = self::resolve_id_from_pods_field( $pod->field( 'artist' ) );
        if ( $direct ) return $direct;
        $album_id = self::resolve_id_from_pods_field( $pod->field( 'album' ) );
        if ( ! $album_id ) return 0;
        $album_pod = pods( 'album', $album_id );
        if ( ! $album_pod || ! $album_pod->exists() ) return 0;
        return self::resolve_id_from_pods_field( $album_pod->field( 'artist' ) );
    }

    private static function resolve_album_id( $song_id ) {
        if ( ! function_exists( 'pods' ) ) return 0;
        $pod = pods( 'song', $song_id );
        if ( ! $pod || ! $pod->exists() ) return 0;
        return self::resolve_id_from_pods_field( $pod->field( 'album' ) );
    }

    public static function build_title( $post_id, $type ) {
        $post_title = get_the_title( $post_id );
        if ( ! $post_title ) return '';

        $artist_id   = self::resolve_artist_id( $post_id, $type );
        $artist_name = $artist_id ? get_the_title( $artist_id ) : '';

        switch ( $type ) {
            case 'song':
                if ( $artist_name ) {
                    return sprintf( '%s by %s — License Music for Film & Games', $post_title, $artist_name );
                }
                return sprintf( '%s — License Music for Film & Games', $post_title );
            case 'album':
                if ( $artist_name ) {
                    return sprintf( '%s by %s — Album Licensing', $post_title, $artist_name );
                }
                return sprintf( '%s — Album Licensing', $post_title );
            case 'artist':
                return sprintf( '%s — Music for Film & Games', $post_title );
        }
        return '';
    }

    public static function build_description( $post_id, $type ) {
        $post_title = get_the_title( $post_id );
        if ( ! $post_title ) return '';

        switch ( $type ) {
            case 'song':
                $album_id    = self::resolve_album_id( $post_id );
                $album_name  = $album_id ? get_the_title( $album_id ) : '';
                $artist_id   = self::resolve_artist_id( $post_id, 'song' );
                $artist_name = $artist_id ? get_the_title( $artist_id ) : '';

                if ( $artist_name && $album_name ) {
                    return sprintf(
                        'Stream and license "%s" by %s from the album "%s". CC-BY 4.0 or commercial sync license available on Sync.Land.',
                        $post_title, $artist_name, $album_name
                    );
                }
                if ( $artist_name ) {
                    return sprintf(
                        'Stream and license "%s" by %s. CC-BY 4.0 or commercial sync license available on Sync.Land.',
                        $post_title, $artist_name
                    );
                }
                return sprintf(
                    'Stream and license "%s" on Sync.Land. CC-BY 4.0 or commercial sync license available.',
                    $post_title
                );

            case 'album':
                $content = get_post_field( 'post_content', $post_id );
                if ( $content ) {
                    return self::clip_description( $content );
                }
                $artist_id   = self::resolve_artist_id( $post_id, 'album' );
                $artist_name = $artist_id ? get_the_title( $artist_id ) : '';
                if ( $artist_name ) {
                    return sprintf(
                        'Listen to and license the album "%s" by %s on Sync.Land. CC-BY 4.0 and commercial sync licensing available.',
                        $post_title, $artist_name
                    );
                }
                return sprintf(
                    'Listen to and license the album "%s" on Sync.Land. CC-BY 4.0 and commercial sync licensing available.',
                    $post_title
                );

            case 'artist':
                $about = get_post_meta( $post_id, 'about', true );
                if ( $about ) return self::clip_description( $about );
                $content = get_post_field( 'post_content', $post_id );
                if ( $content ) return self::clip_description( $content );
                return sprintf(
                    'Browse and license music by %s on Sync.Land. CC-BY 4.0 and commercial sync licenses available for film, game, and creative projects.',
                    $post_title
                );
        }
        return '';
    }

    private static function clip_description( $text ) {
        $clean = wp_strip_all_tags( $text );
        $clean = preg_replace( '/\s+/', ' ', $clean );
        $clean = trim( $clean );
        if ( function_exists( 'mb_substr' ) && mb_strlen( $clean, 'UTF-8' ) > 158 ) {
            $clean = mb_substr( $clean, 0, 155, 'UTF-8' ) . '…';
        } elseif ( strlen( $clean ) > 158 ) {
            $clean = substr( $clean, 0, 155 ) . '...';
        }
        return $clean;
    }

    public static function filter_pre_document_title( $title ) {
        $type = self::get_supported_type();
        if ( ! $type ) return $title;
        $post_id = get_queried_object_id();
        if ( self::has_manual_title( $post_id ) ) return $title;
        $built = self::build_title( $post_id, $type );
        return $built ?: $title;
    }

    public static function filter_document_title_parts( $parts ) {
        $type = self::get_supported_type();
        if ( ! $type ) return $parts;
        $post_id = get_queried_object_id();
        if ( self::has_manual_title( $post_id ) ) return $parts;
        $built = self::build_title( $post_id, $type );
        if ( ! $built ) return $parts;
        return [ 'title' => $built ];
    }

    public static function sf_filter_title( $title, $args = null ) {
        $post_id = self::extract_post_id_from_args( $args );
        if ( ! $post_id ) return $title;
        $type = get_post_type( $post_id );
        if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) return $title;
        if ( self::has_manual_title( $post_id ) ) return $title;
        $built = self::build_title( $post_id, $type );
        return $built ?: $title;
    }

    public static function sf_filter_description( $description, $args = null ) {
        $post_id = self::extract_post_id_from_args( $args );
        if ( ! $post_id ) return $description;
        $type = get_post_type( $post_id );
        if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) return $description;
        if ( self::has_manual_description( $post_id ) ) return $description;
        $built = self::build_description( $post_id, $type );
        return $built ?: $description;
    }

    private static function extract_post_id_from_args( $args ) {
        if ( is_array( $args ) ) {
            if ( isset( $args['id'] ) )      return (int) $args['id'];
            if ( isset( $args['post_id'] ) ) return (int) $args['post_id'];
        }
        if ( is_object( $args ) ) {
            if ( isset( $args->id ) )      return (int) $args->id;
            if ( isset( $args->post_id ) ) return (int) $args->post_id;
        }
        if ( is_numeric( $args ) ) return (int) $args;
        return (int) get_queried_object_id();
    }

    private static function has_manual_title( $post_id ) {
        global $wpdb;
        $v = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
            $post_id, '_genesis_title'
        ) );
        return ! empty( $v );
    }

    private static function has_manual_description( $post_id ) {
        global $wpdb;
        $v = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
            $post_id, '_genesis_description'
        ) );
        return ! empty( $v );
    }

    public static function filter_description_meta( $value, $object_id, $meta_key, $single ) {
        if ( $meta_key !== '_genesis_description' ) return $value;
        if ( $value !== null ) return $value;
        $type = get_post_type( $object_id );
        if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) return $value;
        $generated = self::build_description( $object_id, $type );
        if ( ! $generated ) return $value;
        return $single ? $generated : [ $generated ];
    }
}

FML_Dynamic_Meta::init();
