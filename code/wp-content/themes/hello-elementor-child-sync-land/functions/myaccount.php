<?php
/**
 * Misc User Registration helper shortcodes.
 *
 * The main account experience is now handled by [syncland_dashboard]
 * in functions/account/account-dashboard.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Shortcode to display User Registration breadcrumbs
add_shortcode( 'user_registration_breadcrumbs', 'user_registration_breadcrumbs_shortcode' );
function user_registration_breadcrumbs_shortcode() {
    ob_start();
    if ( function_exists( 'ur_breadcrumb' ) ) {
        ur_breadcrumb();
    }
    return ob_get_clean();
}

// Shortcode for logout URL
add_shortcode( 'ur_logout', function () {
    return function_exists( 'ur_logout_url' ) ? ur_logout_url() : wp_logout_url( home_url() );
} );
