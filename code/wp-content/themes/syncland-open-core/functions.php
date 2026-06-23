<?php
/**
 * Sync.Land Open Core — theme entry point.
 *
 * This file wires up the curated open-source components published from the
 * Sync.Land marketplace. It is intentionally minimal: the production theme
 * also loads marketplace logic (Stripe checkout, NMKR minting, cart, admin
 * tooling, email, transcoder integration, etc.) which is not included in
 * this open-source release.
 *
 * For the full hosted marketplace see https://sync.land.
 * For the REST API surface see docs/api-spec.yaml.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Helpers shared by the API endpoints (small response wrappers used in lieu
 * of WP_REST_Response so the same call sites can be tested outside a full
 * REST context).
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'fml_api_success' ) ) {
	function fml_api_success( $data = [], $status = 200 ) {
		return new WP_REST_Response(
			[ 'success' => true, 'data' => $data ],
			$status
		);
	}
}

if ( ! function_exists( 'fml_api_error' ) ) {
	function fml_api_error( $message, $code = 'error', $status = 400, $data = [] ) {
		return new WP_REST_Response(
			[
				'success' => false,
				'error'   => [
					'code'    => $code,
					'message' => $message,
					'data'    => $data,
				],
			],
			$status
		);
	}
}

if ( ! function_exists( 'fml_get_client_ip' ) ) {
	function fml_get_client_ip() {
		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}
}

/* -------------------------------------------------------------------------
 * SEO — Schema.org structured data + dynamic per-CPT meta.
 * Hooks into The SEO Framework (autodescription) plugin.
 * ---------------------------------------------------------------------- */
require get_stylesheet_directory() . '/functions/seo/music-schema.php';
require get_stylesheet_directory() . '/functions/seo/dynamic-meta.php';

/* -------------------------------------------------------------------------
 * Analytics + user feedback survey.
 * - schema.php   creates the analytics events + survey response tables
 * - survey.php   renders the role-aware feedback banner and inline form
 * - api/analytics.php registers the REST endpoints used by both
 * ---------------------------------------------------------------------- */
require get_stylesheet_directory() . '/functions/analytics/schema.php';
require get_stylesheet_directory() . '/functions/analytics/survey.php';
require get_stylesheet_directory() . '/functions/api/analytics.php';

/* -------------------------------------------------------------------------
 * Public-read REST API.
 * security.php exposes the rate limiter, API-key authentication, and
 * shared helpers; the *.php files below register read-only endpoints.
 * Write endpoints (license generation, NFT minting, Stripe checkout) live
 * in the proprietary marketplace and are not included here.
 * ---------------------------------------------------------------------- */
require get_stylesheet_directory() . '/functions/api/security.php';
require get_stylesheet_directory() . '/functions/api/songs.php';
require get_stylesheet_directory() . '/functions/api/artists.php';
require get_stylesheet_directory() . '/functions/api/search.php';
