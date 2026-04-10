<?php
/**
 * Analytics REST API Endpoints
 *
 * Handles event ingestion, survey submission, admin queries, and CSV export.
 * Namespace: FML/v1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function() {

    // POST /analytics/events — Batch event ingestion from frontend JS
    register_rest_route('FML/v1', '/analytics/events', [
        'methods'  => 'POST',
        'callback' => 'fml_api_analytics_events_submit',
        'permission_callback' => 'fml_permission_public_rate_limited',
    ]);

    // POST /analytics/survey — Survey submission
    register_rest_route('FML/v1', '/analytics/survey', [
        'methods'  => 'POST',
        'callback' => 'fml_api_analytics_survey_submit',
        'permission_callback' => '__return_true',
    ]);

    // GET /analytics/events — Admin event query (paginated)
    register_rest_route('FML/v1', '/analytics/events', [
        'methods'  => 'GET',
        'callback' => 'fml_api_analytics_events_query',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    // GET /analytics/stats — Aggregated dashboard stats
    register_rest_route('FML/v1', '/analytics/stats', [
        'methods'  => 'GET',
        'callback' => 'fml_api_analytics_stats',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    // GET /analytics/survey-results — NPS and use case breakdown
    register_rest_route('FML/v1', '/analytics/survey-results', [
        'methods'  => 'GET',
        'callback' => 'fml_api_analytics_survey_results',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);

    // GET /analytics/export — CSV export (events or survey)
    register_rest_route('FML/v1', '/analytics/export', [
        'methods'  => 'GET',
        'callback' => 'fml_api_analytics_export',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
});

/**
 * POST /analytics/events — Batch event ingestion
 */
function fml_api_analytics_events_submit(WP_REST_Request $request) {
    $settings = fml_analytics_get_settings();
    if (!$settings['tracking_enabled']) {
        return fml_api_success(['recorded' => 0, 'message' => 'Tracking disabled']);
    }

    $body = $request->get_json_params();
    $events = isset($body['events']) ? $body['events'] : [];

    if (!is_array($events) || empty($events)) {
        return fml_api_error('No events provided', 'invalid_events', 400);
    }

    // Cap batch size at 50
    $events = array_slice($events, 0, 50);

    $session_id = isset($body['session_id']) ? sanitize_text_field($body['session_id']) : fml_analytics_get_session_id();
    // Prefer server-side auth; fall back to client-provided user_id
    // (set via wp_localize_script during authenticated page render)
    $user_id = is_user_logged_in() ? get_current_user_id() : null;
    if (!$user_id && !empty($body['user_id'])) {
        $user_id = absint($body['user_id']);
    }

    $count = fml_analytics_record_batch($events, $session_id, $user_id);

    return fml_api_success(['recorded' => $count]);
}

/**
 * POST /analytics/survey — Survey submission
 */
function fml_api_analytics_survey_submit(WP_REST_Request $request) {
    // Simple rate limit: max 1 survey per session per hour
    $session_id = isset($_COOKIE['fml_cart_session']) ? sanitize_text_field($_COOKIE['fml_cart_session']) : '';
    if (empty($session_id)) {
        $session_id = 'anon_' . fml_get_client_ip();
    }

    $rate_key = 'fml_survey_rate_' . md5($session_id);
    if (get_transient($rate_key)) {
        return fml_api_error('Survey already submitted recently', 'rate_limited', 429);
    }

    $settings = fml_analytics_get_settings();
    if (!$settings['survey_enabled']) {
        return fml_api_error('Survey is currently disabled', 'survey_disabled', 403);
    }

    $body = $request->get_json_params();

    global $wpdb;
    $table = $wpdb->prefix . 'fml_survey_responses';

    $nps_score = isset($body['nps_score']) ? intval($body['nps_score']) : null;
    if ($nps_score !== null && ($nps_score < 0 || $nps_score > 10)) {
        $nps_score = null;
    }

    $licensing_ease = isset($body['licensing_ease']) ? intval($body['licensing_ease']) : null;
    if ($licensing_ease !== null && ($licensing_ease < 1 || $licensing_ease > 5)) {
        $licensing_ease = null;
    }

    $use_case = '';
    if (isset($body['use_case']) && is_array($body['use_case'])) {
        $use_case = implode(',', array_map('sanitize_text_field', array_slice($body['use_case'], 0, 10)));
    } elseif (isset($body['use_case'])) {
        $use_case = sanitize_text_field(substr($body['use_case'], 0, 255));
    }

    $insert_data = [
        'session_id'     => $session_id,
        'user_id'        => is_user_logged_in() ? get_current_user_id() : null,
        'nps_score'      => $nps_score,
        'use_case'       => $use_case ?: null,
        'licensing_ease'  => $licensing_ease,
        'feature_request' => isset($body['feature_request']) ? sanitize_textarea_field(substr($body['feature_request'], 0, 5000)) : null,
        'how_found_us'   => isset($body['how_found_us']) ? sanitize_text_field(substr($body['how_found_us'], 0, 100)) : null,
        'trigger_type'   => isset($body['trigger_type']) ? sanitize_text_field(substr($body['trigger_type'], 0, 50)) : null,
        'page_url'       => isset($body['page_url']) ? esc_url_raw(substr($body['page_url'], 0, 2048)) : null,
        'ip_address'     => fml_get_client_ip(),
    ];

    $result = $wpdb->insert($table, $insert_data);

    if ($result === false) {
        return fml_api_error('Failed to save survey response', 'db_error', 500);
    }

    // Set rate limit: 1 hour
    set_transient($rate_key, 1, HOUR_IN_SECONDS);

    // Also mark dismissed for logged-in users
    if (is_user_logged_in()) {
        update_user_meta(get_current_user_id(), 'fml_survey_dismissed', time());
    }

    return fml_api_success(['message' => 'Survey response recorded']);
}

/**
 * GET /analytics/events — Admin event query
 */
function fml_api_analytics_events_query(WP_REST_Request $request) {
    $args = [
        'per_page'   => min(100, max(1, intval($request->get_param('per_page') ?: 50))),
        'page'       => max(1, intval($request->get_param('page') ?: 1)),
        'event_type' => $request->get_param('event_type') ?: null,
        'date_from'  => $request->get_param('date_from') ?: null,
        'date_to'    => $request->get_param('date_to') ?: null,
        'user_id'    => $request->get_param('user_id') ?: null,
        'session_id' => $request->get_param('session_id') ?: null,
    ];

    $result = fml_analytics_get_events($args);
    return fml_api_success($result);
}

/**
 * GET /analytics/stats — Aggregated dashboard stats
 */
function fml_api_analytics_stats(WP_REST_Request $request) {
    $period = $request->get_param('period') ?: 'month';

    return fml_api_success([
        'events_today'   => fml_analytics_count_events('today'),
        'events_week'    => fml_analytics_count_events('week'),
        'events_month'   => fml_analytics_count_events('month'),
        'sessions_today' => fml_analytics_count_sessions('today'),
        'sessions_week'  => fml_analytics_count_sessions('week'),
        'sessions_month' => fml_analytics_count_sessions('month'),
        'top_songs'      => fml_analytics_top_songs(5, $period),
        'funnel'         => fml_analytics_conversion_funnel($period),
        'daily_counts'   => fml_analytics_daily_counts(30),
    ]);
}

/**
 * GET /analytics/survey-results — NPS and breakdown
 */
function fml_api_analytics_survey_results(WP_REST_Request $request) {
    return fml_api_success([
        'nps'       => fml_analytics_nps_stats(),
        'use_cases' => fml_analytics_use_case_breakdown(),
        'responses' => fml_analytics_get_survey_responses(
            max(1, intval($request->get_param('page') ?: 1)),
            min(100, max(1, intval($request->get_param('per_page') ?: 50)))
        ),
    ]);
}

/**
 * GET /analytics/export — CSV export
 */
function fml_api_analytics_export(WP_REST_Request $request) {
    global $wpdb;

    $type = $request->get_param('type') ?: 'events';
    $date_from = $request->get_param('date_from');
    $date_to = $request->get_param('date_to');

    if ($type === 'survey') {
        $table = $wpdb->prefix . 'fml_survey_responses';
        $columns = ['id', 'session_id', 'user_id', 'nps_score', 'use_case', 'licensing_ease', 'feature_request', 'how_found_us', 'trigger_type', 'page_url', 'created_at'];
    } else {
        $table = $wpdb->prefix . 'fml_analytics_events';
        $columns = ['id', 'session_id', 'user_id', 'event_type', 'event_data', 'page_url', 'referrer', 'created_at'];
    }

    $where = '1=1';
    $params = [];
    if ($date_from) {
        $where .= ' AND created_at >= %s';
        $params[] = sanitize_text_field($date_from) . ' 00:00:00';
    }
    if ($date_to) {
        $where .= ' AND created_at <= %s';
        $params[] = sanitize_text_field($date_to) . ' 23:59:59';
    }

    $query = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT 10000";
    $rows = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A) : $wpdb->get_results($query, ARRAY_A);

    // Build CSV
    $csv = implode(',', $columns) . "\n";
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $col) {
            $val = isset($row[$col]) ? $row[$col] : '';
            // Escape CSV values
            $val = str_replace('"', '""', $val);
            $line[] = '"' . $val . '"';
        }
        $csv .= implode(',', $line) . "\n";
    }

    return new WP_REST_Response([
        'success'  => true,
        'filename' => "fml-{$type}-export-" . date('Y-m-d') . '.csv',
        'csv'      => $csv,
        'count'    => count($rows),
    ]);
}
