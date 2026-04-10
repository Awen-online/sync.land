<?php
/**
 * [syncland_dashboard] shortcode controller.
 *
 * Replaces the User Registration [user_registration_my_account] shortcode for
 * the logged-in account experience. UR is still used for login form rendering,
 * edit-profile, and change-password (delegated back to UR when those endpoints
 * are active).
 *
 * Rewrite endpoints registered here: licenses, playlists, artists.
 * UR already registers: edit-profile, edit-password, user-logout.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* -----------------------------------------------------------------------
 * 1. Register custom rewrite endpoints so /account/licenses/ etc. work
 *    even after URCMA is deactivated.  Priority 10 (before URCMA's 21).
 * --------------------------------------------------------------------- */
add_action( 'init', function () {
    add_rewrite_endpoint( 'licenses',  EP_PAGES );
    add_rewrite_endpoint( 'playlists', EP_PAGES );
    add_rewrite_endpoint( 'artists',   EP_PAGES );
}, 10 );

/* Ensure WordPress treats these as valid query vars. */
add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'licenses';
    $vars[] = 'playlists';
    $vars[] = 'artists';
    return $vars;
} );

/* -----------------------------------------------------------------------
 * 2. Shortcode
 * --------------------------------------------------------------------- */
add_shortcode( 'syncland_dashboard', 'syncland_dashboard_shortcode' );

function syncland_dashboard_shortcode( $atts = [] ) {
    ob_start();

    /* ---- Logged-out: fall back to UR's login form ---- */
    if ( ! is_user_logged_in() ) {
        echo do_shortcode( '[user_registration_my_account]' );
        return ob_get_clean();
    }

    $current_tab = syncland_get_current_tab();

    /* ---- Logout ---- */
    if ( 'user-logout' === $current_tab ) {
        wp_logout();
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    $template_dir = get_stylesheet_directory() . '/templates/account';
    ?>
    <div class="syncland-dashboard">
        <?php
        /* Navigation */
        include $template_dir . '/navigation.php';
        ?>
        <div class="syncland-dashboard-content">
            <?php
            switch ( $current_tab ) {
                case 'licenses':
                    include $template_dir . '/licenses.php';
                    break;

                case 'playlists':
                    include $template_dir . '/playlists.php';
                    break;

                case 'artists':
                    include $template_dir . '/artists.php';
                    break;

                case 'edit-profile':
                case 'edit-password':
                    /*
                     * Delegate to UR for form rendering.  UR detects the
                     * endpoint query var and renders the correct form.
                     * We suppress UR's nav via CSS (see account CSS).
                     */
                    echo do_shortcode( '[user_registration_my_account]' );
                    break;

                default: // dashboard
                    include $template_dir . '/dashboard.php';
                    break;
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* -----------------------------------------------------------------------
 * 3. Tab detection helper
 * --------------------------------------------------------------------- */
function syncland_get_current_tab() {
    global $wp_query;

    $endpoints = [
        'licenses',
        'playlists',
        'artists',
        'edit-profile',
        'edit-password',
        'user-logout',
    ];

    foreach ( $endpoints as $ep ) {
        if ( isset( $wp_query->query_vars[ $ep ] ) ) {
            return $ep;
        }
    }

    return 'dashboard';
}
