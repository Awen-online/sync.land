<?php
/**
 * Core Analytics Functions
 *
 * Provides event recording, session management, query helpers, and cron cleanup.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * DEFAULT SETTINGS
 * ============================================================================
 */

function fml_analytics_get_settings() {
    return wp_parse_args(get_option('fml_analytics_settings', []), [
        'tracking_enabled'    => true,
        'survey_enabled'      => true,
        'survey_visit_count'  => 3,
        'survey_time_on_site' => 300,
        'survey_post_licensing' => true,
        'data_retention_days' => 365,
    ]);
}

/**
 * ============================================================================
 * SESSION MANAGEMENT
 * ============================================================================
 */

/**
 * Get the current session ID (reuses fml_cart_session cookie)
 */
function fml_analytics_get_session_id() {
    if (isset($_COOKIE['fml_cart_session'])) {
        return sanitize_text_field($_COOKIE['fml_cart_session']);
    }

    // Generate a new session if cart hasn't set one
    $session_id = wp_generate_uuid4();
    setcookie('fml_cart_session', $session_id, time() + (7 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    $_COOKIE['fml_cart_session'] = $session_id;

    return $session_id;
}

/**
 * ============================================================================
 * EVENT RECORDING
 * ============================================================================
 */

/**
 * Record a single analytics event
 */
function fml_analytics_record_event($event_type, $event_data = [], $extra = []) {
    global $wpdb;

    $settings = fml_analytics_get_settings();
    if (!$settings['tracking_enabled']) {
        return false;
    }

    $table = $wpdb->prefix . 'fml_analytics_events';

    $insert = [
        'session_id' => isset($extra['session_id']) ? $extra['session_id'] : fml_analytics_get_session_id(),
        'user_id'    => isset($extra['user_id']) ? absint($extra['user_id']) : (is_user_logged_in() ? get_current_user_id() : null),
        'event_type' => sanitize_text_field(substr($event_type, 0, 50)),
        'event_data' => !empty($event_data) ? wp_json_encode($event_data) : null,
        'page_url'   => isset($extra['page_url']) ? esc_url_raw(substr($extra['page_url'], 0, 2048)) : null,
        'referrer'   => isset($extra['referrer']) ? esc_url_raw(substr($extra['referrer'], 0, 2048)) : null,
        'user_agent' => isset($extra['user_agent']) ? sanitize_text_field(substr($extra['user_agent'], 0, 512)) : null,
        'ip_address' => isset($extra['ip_address']) ? sanitize_text_field($extra['ip_address']) : fml_get_client_ip(),
    ];

    $formats = ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

    // Handle null user_id
    if ($insert['user_id'] === null) {
        $formats[1] = null;
    }

    return $wpdb->insert($table, $insert);
}

/**
 * Record a batch of events (from frontend JS flush)
 */
function fml_analytics_record_batch($events, $session_id, $user_id = null) {
    $count = 0;
    foreach ($events as $event) {
        $event_type = isset($event['type']) ? $event['type'] : '';
        $event_data = isset($event['data']) ? $event['data'] : [];
        if (empty($event_type)) continue;

        $extra = [
            'session_id' => $session_id,
            'user_id'    => $user_id,
            'page_url'   => isset($event['page_url']) ? $event['page_url'] : null,
            'referrer'   => isset($event['referrer']) ? $event['referrer'] : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            'ip_address' => fml_get_client_ip(),
        ];

        if (fml_analytics_record_event($event_type, $event_data, $extra)) {
            $count++;
        }
    }
    return $count;
}

/**
 * ============================================================================
 * QUERY HELPERS
 * ============================================================================
 */

/**
 * Get event count for a given period
 */
function fml_analytics_count_events($period = 'today', $event_type = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_analytics_events';

    $where = '1=1';
    $params = [];

    switch ($period) {
        case 'today':
            $where .= ' AND DATE(created_at) = CURDATE()';
            break;
        case 'week':
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        case 'all':
            break;
    }

    if ($event_type) {
        $where .= $wpdb->prepare(' AND event_type = %s', $event_type);
    }

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
}

/**
 * Get unique session count for a given period
 */
function fml_analytics_count_sessions($period = 'today') {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_analytics_events';

    $where = '1=1';
    switch ($period) {
        case 'today':
            $where .= ' AND DATE(created_at) = CURDATE()';
            break;
        case 'week':
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
    }

    return (int) $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE {$where}");
}

/**
 * Get top played songs
 */
function fml_analytics_top_songs($limit = 5, $period = 'month') {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_analytics_events';

    $where = "event_type = 'song_play'";
    switch ($period) {
        case 'today':
            $where .= ' AND DATE(created_at) = CURDATE()';
            break;
        case 'week':
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.song_id')) as song_id,
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.song_name')) as song_name,
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.artist')) as artist,
                COUNT(*) as play_count
         FROM {$table}
         WHERE {$where}
         GROUP BY song_id, song_name, artist
         ORDER BY play_count DESC
         LIMIT %d",
        $limit
    ));
}

/**
 * Get conversion funnel data
 */
function fml_analytics_conversion_funnel($period = 'month') {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_analytics_events';

    $where = '1=1';
    switch ($period) {
        case 'today':
            $where .= ' AND DATE(created_at) = CURDATE()';
            break;
        case 'week':
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
    }

    $steps = ['song_play', 'license_modal_open', 'add_to_cart', 'checkout_start'];
    $funnel = [];

    foreach ($steps as $step) {
        $funnel[$step] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE {$where} AND event_type = '{$step}'"
        );
    }

    return $funnel;
}

/**
 * Get daily event counts for charting
 */
function fml_analytics_daily_counts($days = 30) {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_analytics_events';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(created_at) as event_date, COUNT(*) as event_count
         FROM {$table}
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
         GROUP BY DATE(created_at)
         ORDER BY event_date ASC",
        $days
    ));
}

/**
 * Get paginated events for admin table
 */
function fml_analytics_get_events($args = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_analytics_events';

    $defaults = [
        'per_page'   => 50,
        'page'       => 1,
        'event_type' => null,
        'date_from'  => null,
        'date_to'    => null,
        'user_id'    => null,
        'session_id' => null,
    ];
    $args = wp_parse_args($args, $defaults);

    $where = '1=1';
    $params = [];

    if ($args['event_type']) {
        $where .= ' AND event_type = %s';
        $params[] = $args['event_type'];
    }
    if ($args['date_from']) {
        $where .= ' AND created_at >= %s';
        $params[] = $args['date_from'] . ' 00:00:00';
    }
    if ($args['date_to']) {
        $where .= ' AND created_at <= %s';
        $params[] = $args['date_to'] . ' 23:59:59';
    }
    if ($args['user_id']) {
        $where .= ' AND user_id = %d';
        $params[] = $args['user_id'];
    }
    if ($args['session_id']) {
        $where .= ' AND session_id = %s';
        $params[] = $args['session_id'];
    }

    $offset = ($args['page'] - 1) * $args['per_page'];

    $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
    $data_query = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

    $params_with_limit = array_merge($params, [$args['per_page'], $offset]);

    $total = !empty($params)
        ? (int) $wpdb->get_var($wpdb->prepare($count_query, $params))
        : (int) $wpdb->get_var($count_query);

    $events = !empty($params_with_limit)
        ? $wpdb->get_results($wpdb->prepare($data_query, $params_with_limit))
        : $wpdb->get_results($wpdb->prepare($data_query, [$args['per_page'], $offset]));

    return [
        'events'     => $events,
        'total'      => $total,
        'page'       => $args['page'],
        'per_page'   => $args['per_page'],
        'total_pages' => ceil($total / $args['per_page']),
    ];
}

/**
 * ============================================================================
 * SURVEY QUERY HELPERS
 * ============================================================================
 */

/**
 * Get NPS statistics
 */
function fml_analytics_nps_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_survey_responses';

    $avg = $wpdb->get_var("SELECT AVG(nps_score) FROM {$table} WHERE nps_score IS NOT NULL");
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE nps_score IS NOT NULL");

    // NPS distribution
    $distribution = $wpdb->get_results(
        "SELECT nps_score, COUNT(*) as count FROM {$table}
         WHERE nps_score IS NOT NULL
         GROUP BY nps_score ORDER BY nps_score ASC"
    );

    // Promoters (9-10), Passives (7-8), Detractors (0-6)
    $promoters = 0;
    $passives = 0;
    $detractors = 0;
    foreach ($distribution as $row) {
        if ($row->nps_score >= 9) $promoters += $row->count;
        elseif ($row->nps_score >= 7) $passives += $row->count;
        else $detractors += $row->count;
    }

    $nps = $total > 0 ? round((($promoters - $detractors) / $total) * 100) : null;

    return [
        'average'      => $avg !== null ? round((float) $avg, 1) : null,
        'total'        => $total,
        'nps'          => $nps,
        'promoters'    => $promoters,
        'passives'     => $passives,
        'detractors'   => $detractors,
        'distribution' => $distribution,
    ];
}

/**
 * Get use case breakdown
 */
function fml_analytics_use_case_breakdown() {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_survey_responses';

    $rows = $wpdb->get_col("SELECT use_case FROM {$table} WHERE use_case IS NOT NULL AND use_case != ''");
    $counts = [];

    foreach ($rows as $row) {
        $cases = array_map('trim', explode(',', $row));
        foreach ($cases as $case) {
            if (!empty($case)) {
                $counts[$case] = isset($counts[$case]) ? $counts[$case] + 1 : 1;
            }
        }
    }

    arsort($counts);
    return $counts;
}

/**
 * Get survey responses (paginated)
 */
function fml_analytics_get_survey_responses($page = 1, $per_page = 50) {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_survey_responses';

    $offset = ($page - 1) * $per_page;
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $responses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    return [
        'responses'   => $responses,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page,
        'total_pages' => ceil($total / $per_page),
    ];
}

/**
 * ============================================================================
 * CRON: DAILY CLEANUP
 * ============================================================================
 */

/**
 * Schedule daily cleanup cron
 */
function fml_analytics_schedule_cron() {
    if (!wp_next_scheduled('fml_analytics_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'fml_analytics_daily_cleanup');
    }
}
add_action('admin_init', 'fml_analytics_schedule_cron');

/**
 * Daily cleanup: anonymize old IPs and purge expired data
 */
function fml_analytics_daily_cleanup_handler() {
    global $wpdb;

    $settings = fml_analytics_get_settings();
    $retention_days = max(30, (int) $settings['data_retention_days']);

    $events_table = $wpdb->prefix . 'fml_analytics_events';
    $survey_table = $wpdb->prefix . 'fml_survey_responses';

    // Anonymize IPs older than 30 days
    $wpdb->query(
        "UPDATE {$events_table}
         SET ip_address = '0.0.0.0'
         WHERE ip_address != '0.0.0.0'
         AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );

    $wpdb->query(
        "UPDATE {$survey_table}
         SET ip_address = '0.0.0.0'
         WHERE ip_address != '0.0.0.0'
         AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );

    // Purge events older than retention period
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$events_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $retention_days
    ));

    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$survey_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $retention_days
    ));
}
add_action('fml_analytics_daily_cleanup', 'fml_analytics_daily_cleanup_handler');

/**
 * Deactivation: clear scheduled cron
 */
function fml_analytics_deactivate_cron() {
    $timestamp = wp_next_scheduled('fml_analytics_daily_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'fml_analytics_daily_cleanup');
    }
}
add_action('switch_theme', 'fml_analytics_deactivate_cron');

/**
 * Manual purge all analytics data
 */
function fml_analytics_purge_all() {
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}fml_analytics_events");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}fml_survey_responses");
}
