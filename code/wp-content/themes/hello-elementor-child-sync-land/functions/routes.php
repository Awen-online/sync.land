<?php
/**
 * Custom Routes and REST API Endpoints
 *
 * @package Sync.Land
 */

/**
 * License PDF Short URL Redirect
 * Handles /wp-json/FML/v1/l/{license_id} -> redirects to S3 PDF URL
 * Used in NFT metadata where CIP-25 has 64-byte limit per field
 */
add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/l/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'fml_license_pdf_redirect',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);
});

/**
 * Redirect to license PDF stored in S3
 *
 * @param WP_REST_Request $request
 * @return WP_Error|void
 */
function fml_license_pdf_redirect($request) {
    $license_id = intval($request['id']);

    // Get license from Pods
    $license_pod = pods('license', $license_id);

    if (!$license_pod || !$license_pod->exists()) {
        return new WP_Error('not_found', 'License not found', ['status' => 404]);
    }

    $license_url = $license_pod->field('license_url');

    if (empty($license_url)) {
        return new WP_Error('no_pdf', 'License PDF not available', ['status' => 404]);
    }

    // Redirect to the actual PDF
    wp_redirect($license_url, 301);
    exit;
}
