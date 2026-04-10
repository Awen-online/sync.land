<?php
/**
 * Email Open & Click Tracking for Sync.Land
 *
 * Tracks bulk email opens (1px pixel) and clicks (link redirect).
 * Data stored in a custom DB table, stats shown in Bulk Email admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ──────────────────────────────────────────────────────────────
// Database table
// ──────────────────────────────────────────────────────────────

add_action('admin_init', 'fml_maybe_create_tracking_table');

function fml_maybe_create_tracking_table() {
    if (get_option('fml_email_tracking_db_version', 0) >= 1) {
        return;
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'fml_email_tracking';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        campaign_id varchar(32) NOT NULL,
        token varchar(32) NOT NULL,
        email varchar(255) NOT NULL,
        event_type varchar(10) NOT NULL,
        link_url text,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY token (token),
        KEY event_type (event_type)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('fml_email_tracking_db_version', 1);
}

// ──────────────────────────────────────────────────────────────
// REST endpoints (public — called from email clients)
// ──────────────────────────────────────────────────────────────

add_action('rest_api_init', function () {
    // Open tracking pixel
    register_rest_route('FML/v1', '/t/o/(?P<token>[a-zA-Z0-9]+)', [
        'methods'             => 'GET',
        'callback'            => 'fml_track_open',
        'permission_callback' => '__return_true',
    ]);

    // Click tracking redirect
    register_rest_route('FML/v1', '/t/c/(?P<token>[a-zA-Z0-9]+)', [
        'methods'             => 'GET',
        'callback'            => 'fml_track_click',
        'permission_callback' => '__return_true',
    ]);
});

function fml_track_open($request) {
    $token = sanitize_text_field($request['token']);
    fml_record_tracking_event($token, 'open');

    // 1x1 transparent GIF
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

function fml_track_click($request) {
    $token = sanitize_text_field($request['token']);
    $url   = esc_url_raw($request->get_param('u') ?? '');

    if (empty($url)) {
        $url = home_url('/');
    }

    fml_record_tracking_event($token, 'click', $url);

    wp_redirect($url);
    exit;
}

// ──────────────────────────────────────────────────────────────
// Core tracking functions
// ──────────────────────────────────────────────────────────────

function fml_record_tracking_event($token, $type, $link_url = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_email_tracking';

    // Look up the 'sent' record for this token
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT campaign_id, email FROM {$table} WHERE token = %s AND event_type = 'sent' LIMIT 1",
        $token
    ));

    if (!$record) {
        return;
    }

    // Deduplicate opens per campaign + email
    if ($type === 'open') {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %s AND email = %s AND event_type = 'open'",
            $record->campaign_id,
            $record->email
        ));
        if ($exists) {
            return;
        }
    }

    $wpdb->insert($table, [
        'campaign_id' => $record->campaign_id,
        'token'       => $token,
        'email'       => $record->email,
        'event_type'  => $type,
        'link_url'    => $link_url,
    ]);
}

/**
 * Create a tracking token and store a 'sent' record.
 */
function fml_create_tracking_token($campaign_id, $email) {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_email_tracking';
    $token = wp_generate_password(24, false);

    $wpdb->insert($table, [
        'campaign_id' => $campaign_id,
        'token'       => $token,
        'email'       => $email,
        'event_type'  => 'sent',
    ]);

    return $token;
}

/**
 * Generate a unique campaign ID.
 */
function fml_generate_campaign_id() {
    return 'c_' . wp_generate_password(12, false);
}

// ──────────────────────────────────────────────────────────────
// HTML manipulation helpers
// ──────────────────────────────────────────────────────────────

/**
 * Inject a 1px tracking pixel before </body>.
 */
function fml_inject_tracking_pixel($html, $token) {
    $pixel_url = rest_url("FML/v1/t/o/{$token}");
    $pixel     = '<img src="' . esc_url($pixel_url) . '" width="1" height="1" '
               . 'style="display:block;width:1px;height:1px;" alt="">';

    return str_replace('</body>', $pixel . '</body>', $html);
}

/**
 * Rewrite all <a href="..."> links to go through click tracker.
 * Skips tracking URLs and mailto: links.
 */
function fml_rewrite_links_for_tracking($html, $token) {
    $base = rest_url("FML/v1/t/c/{$token}");

    return preg_replace_callback(
        '/(<a\s[^>]*href=")([^"]+)(")/i',
        function ($matches) use ($base) {
            $url = $matches[2];
            // Skip tracking URLs, mailto, and anchors
            if (strpos($url, '/FML/v1/t/') !== false
                || strpos($url, 'mailto:') === 0
                || strpos($url, '#') === 0
            ) {
                return $matches[0];
            }
            $tracked = $base . '?u=' . urlencode($url);
            return $matches[1] . $tracked . $matches[3];
        },
        $html
    );
}

// ──────────────────────────────────────────────────────────────
// Stats queries
// ──────────────────────────────────────────────────────────────

/**
 * Get aggregate stats for a campaign.
 */
function fml_get_campaign_stats($campaign_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'fml_email_tracking';

    $sent = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %s AND event_type = 'sent'",
        $campaign_id
    ));

    $opens = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %s AND event_type = 'open'",
        $campaign_id
    ));

    $clicks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %s AND event_type = 'click'",
        $campaign_id
    ));

    return [
        'sent'       => $sent,
        'opens'      => $opens,
        'clicks'     => $clicks,
        'open_rate'  => $sent > 0 ? round(($opens / $sent) * 100, 1) : 0,
        'click_rate' => $sent > 0 ? round(($clicks / $sent) * 100, 1) : 0,
    ];
}
