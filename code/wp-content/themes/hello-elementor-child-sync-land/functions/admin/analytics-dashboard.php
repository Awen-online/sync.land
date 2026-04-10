<?php
/**
 * Analytics & Feedback Admin Dashboard
 *
 * Admin page with 4 tabs: Overview, Events, Survey, Settings.
 * Submenu under the existing 'syncland' menu.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin submenu
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'syncland',
        'Analytics & Feedback',
        'Analytics',
        'manage_options',
        'syncland-analytics',
        'fml_analytics_dashboard_page'
    );
}, 15);

/**
 * Enqueue admin assets on analytics page only
 */
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'syncland-analytics') === false) return;

    wp_enqueue_style('fml-admin-analytics', get_stylesheet_directory_uri() . '/assets/css/admin-analytics.css', [], '1.0');

    // Chart.js for the overview chart
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js', [], null, true);
});

/**
 * Handle settings save and purge actions
 */
function fml_analytics_handle_admin_actions() {
    if (!current_user_can('manage_options')) return;

    // Save settings
    if (isset($_POST['fml_analytics_save_settings']) && check_admin_referer('fml_analytics_settings_action')) {
        $settings = [
            'tracking_enabled'     => !empty($_POST['tracking_enabled']),
            'survey_enabled'       => !empty($_POST['survey_enabled']),
            'survey_visit_count'   => max(1, intval($_POST['survey_visit_count'] ?? 3)),
            'survey_time_on_site'  => max(30, intval($_POST['survey_time_on_site'] ?? 300)),
            'survey_post_licensing' => !empty($_POST['survey_post_licensing']),
            'data_retention_days'  => max(30, intval($_POST['data_retention_days'] ?? 365)),
        ];
        update_option('fml_analytics_settings', $settings);
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    // Purge data
    if (isset($_POST['fml_analytics_purge']) && check_admin_referer('fml_analytics_purge_action')) {
        fml_analytics_purge_all();
        echo '<div class="notice notice-success"><p>All analytics data has been purged.</p></div>';
    }
}

/**
 * Main dashboard page
 */
function fml_analytics_dashboard_page() {
    fml_analytics_handle_admin_actions();

    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    $page_url = admin_url('admin.php?page=syncland-analytics');
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-chart-bar" style="font-size: 28px; margin-right: 8px;"></span> Analytics & Feedback</h1>

        <div class="fml-analytics-tabs">
            <a href="<?php echo esc_url($page_url . '&tab=overview'); ?>" class="fml-analytics-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="<?php echo esc_url($page_url . '&tab=events'); ?>" class="fml-analytics-tab <?php echo $active_tab === 'events' ? 'active' : ''; ?>">Events</a>
            <a href="<?php echo esc_url($page_url . '&tab=survey'); ?>" class="fml-analytics-tab <?php echo $active_tab === 'survey' ? 'active' : ''; ?>">Survey</a>
            <a href="<?php echo esc_url($page_url . '&tab=settings'); ?>" class="fml-analytics-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>

        <?php
        switch ($active_tab) {
            case 'events':
                fml_analytics_tab_events();
                break;
            case 'survey':
                fml_analytics_tab_survey();
                break;
            case 'settings':
                fml_analytics_tab_settings();
                break;
            default:
                fml_analytics_tab_overview();
                break;
        }
        ?>
    </div>
    <?php
}

/**
 * ============================================================================
 * TAB: OVERVIEW
 * ============================================================================
 */
function fml_analytics_tab_overview() {
    $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
    ?>
    <div class="fml-analytics-panel active">

        <!-- Stat Cards -->
        <div class="fml-stat-cards">
            <div class="fml-stat-card">
                <div class="fml-stat-value"><?php echo number_format(fml_analytics_count_events('today')); ?></div>
                <div class="fml-stat-label">Events Today</div>
            </div>
            <div class="fml-stat-card">
                <div class="fml-stat-value"><?php echo number_format(fml_analytics_count_events('week')); ?></div>
                <div class="fml-stat-label">Events This Week</div>
            </div>
            <div class="fml-stat-card">
                <div class="fml-stat-value"><?php echo number_format(fml_analytics_count_events('month')); ?></div>
                <div class="fml-stat-label">Events This Month</div>
            </div>
            <div class="fml-stat-card">
                <div class="fml-stat-value"><?php echo number_format(fml_analytics_count_sessions('month')); ?></div>
                <div class="fml-stat-label">Unique Sessions (30d)</div>
            </div>
            <?php
            $nps = fml_analytics_nps_stats();
            if ($nps['nps'] !== null):
            ?>
            <div class="fml-stat-card">
                <div class="fml-stat-value"><?php echo $nps['nps']; ?></div>
                <div class="fml-stat-label">NPS Score</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Songs -->
        <?php $top_songs = fml_analytics_top_songs(5, $period); ?>
        <?php if (!empty($top_songs)): ?>
        <div class="fml-top-songs">
            <h3>Top 5 Songs Played (<?php echo esc_html($period); ?>)</h3>
            <table>
                <thead>
                    <tr><th>#</th><th>Song</th><th>Artist</th><th>Plays</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($top_songs as $i => $song): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo esc_html($song->song_name ?: '(unknown)'); ?></td>
                        <td><?php echo esc_html($song->artist ?: '—'); ?></td>
                        <td><strong><?php echo number_format($song->play_count); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Conversion Funnel -->
        <?php
        $funnel = fml_analytics_conversion_funnel($period);
        $funnel_labels = [
            'song_play'          => 'Song Play',
            'license_modal_open' => 'License Modal',
            'add_to_cart'        => 'Add to Cart',
            'checkout_start'     => 'Checkout Start',
        ];
        $funnel_max = max(1, max($funnel));
        ?>
        <div class="fml-funnel">
            <h3>Conversion Funnel (<?php echo esc_html($period); ?>)</h3>
            <?php
            $prev_count = null;
            foreach ($funnel as $step => $count):
                $pct = round(($count / $funnel_max) * 100);
                $dropoff = '';
                if ($prev_count !== null && $prev_count > 0) {
                    $drop_pct = round((1 - ($count / $prev_count)) * 100);
                    $dropoff = '-' . $drop_pct . '%';
                }
                $prev_count = $count;
            ?>
            <div class="fml-funnel-step">
                <div class="fml-funnel-label"><?php echo esc_html($funnel_labels[$step] ?? $step); ?></div>
                <div class="fml-funnel-bar-wrap">
                    <div class="fml-funnel-bar" style="width: <?php echo $pct; ?>%;"></div>
                </div>
                <div class="fml-funnel-count"><?php echo number_format($count); ?></div>
                <div class="fml-funnel-dropoff"><?php echo $dropoff; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Daily Events Chart -->
        <?php $daily = fml_analytics_daily_counts(30); ?>
        <div class="fml-chart-container">
            <h3>Daily Events (Last 30 Days)</h3>
            <canvas id="fml-daily-chart"></canvas>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') return;
            var ctx = document.getElementById('fml-daily-chart');
            if (!ctx) return;

            var data = <?php echo wp_json_encode(array_map(function($row) {
                return ['date' => $row->event_date, 'count' => (int) $row->event_count];
            }, $daily)); ?>;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(function(d) { return d.date; }),
                    datasets: [{
                        label: 'Events',
                        data: data.map(function(d) { return d.count; }),
                        backgroundColor: 'rgba(34, 113, 177, 0.7)',
                        borderRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxRotation: 45 } }
                    }
                }
            });
        });
        </script>
    </div>
    <?php
}

/**
 * ============================================================================
 * TAB: EVENTS
 * ============================================================================
 */
function fml_analytics_tab_events() {
    $args = [
        'page'       => max(1, intval($_GET['paged'] ?? 1)),
        'per_page'   => 50,
        'event_type' => isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : null,
        'date_from'  => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : null,
        'date_to'    => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : null,
        'session_id' => isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : null,
    ];
    $result = fml_analytics_get_events($args);
    $base_url = admin_url('admin.php?page=syncland-analytics&tab=events');

    // Get distinct event types for filter
    global $wpdb;
    $event_types = $wpdb->get_col("SELECT DISTINCT event_type FROM {$wpdb->prefix}fml_analytics_events ORDER BY event_type");
    ?>
    <div class="fml-analytics-panel active">

        <!-- Filters -->
        <form method="get" class="fml-filters">
            <input type="hidden" name="page" value="syncland-analytics">
            <input type="hidden" name="tab" value="events">

            <label>Type:
                <select name="event_type">
                    <option value="">All</option>
                    <?php foreach ($event_types as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($args['event_type'], $type); ?>><?php echo esc_html($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>From: <input type="date" name="date_from" value="<?php echo esc_attr($args['date_from'] ?? ''); ?>"></label>
            <label>To: <input type="date" name="date_to" value="<?php echo esc_attr($args['date_to'] ?? ''); ?>"></label>
            <label>Session: <input type="text" name="session_id" value="<?php echo esc_attr($args['session_id'] ?? ''); ?>" placeholder="Session ID" style="width: 200px;"></label>

            <button type="submit" class="button">Filter</button>
            <a href="<?php echo esc_url($base_url); ?>" class="button">Reset</a>

            <?php
            $export_params = array_filter([
                'type'      => 'events',
                'date_from' => $args['date_from'],
                'date_to'   => $args['date_to'],
            ]);
            ?>
            <a href="<?php echo esc_url(rest_url('FML/v1/analytics/export?' . http_build_query($export_params))); ?>&_wpnonce=<?php echo wp_create_nonce('wp_rest'); ?>" class="button" target="_blank">Export CSV</a>
        </form>

        <p>Showing <?php echo number_format($result['total']); ?> events (page <?php echo $result['page']; ?> of <?php echo $result['total_pages']; ?>)</p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Time</th>
                    <th>Event</th>
                    <th>Session</th>
                    <th>User</th>
                    <th>Data</th>
                    <th>Page</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['events'])): ?>
                    <tr><td colspan="7">No events found.</td></tr>
                <?php else: ?>
                    <?php foreach ($result['events'] as $event): ?>
                    <tr>
                        <td><?php echo esc_html($event->id); ?></td>
                        <td><?php echo esc_html($event->created_at); ?></td>
                        <td><code><?php echo esc_html($event->event_type); ?></code></td>
                        <td>
                            <a href="<?php echo esc_url($base_url . '&session_id=' . urlencode($event->session_id)); ?>">
                                <?php echo esc_html(substr($event->session_id, 0, 8)); ?>...
                            </a>
                        </td>
                        <td><?php echo $event->user_id ? esc_html($event->user_id) : '—'; ?></td>
                        <td>
                            <?php if ($event->event_data): ?>
                                <details><summary>JSON</summary><pre style="max-width:300px;overflow:auto;font-size:11px;"><?php echo esc_html($event->event_data); ?></pre></details>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($event->page_url); ?>">
                            <?php echo esc_html($event->page_url ?: '—'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                $pagination_args = array_filter([
                    'page'       => 'syncland-analytics',
                    'tab'        => 'events',
                    'event_type' => $args['event_type'],
                    'date_from'  => $args['date_from'],
                    'date_to'    => $args['date_to'],
                    'session_id' => $args['session_id'],
                ]);
                echo paginate_links([
                    'base'    => add_query_arg('paged', '%#%', admin_url('admin.php')),
                    'format'  => '',
                    'current' => $result['page'],
                    'total'   => $result['total_pages'],
                    'add_args' => $pagination_args,
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * ============================================================================
 * TAB: SURVEY
 * ============================================================================
 */
function fml_analytics_tab_survey() {
    $nps = fml_analytics_nps_stats();
    $use_cases = fml_analytics_use_case_breakdown();
    $responses = fml_analytics_get_survey_responses(max(1, intval($_GET['paged'] ?? 1)));
    $max_usecase = !empty($use_cases) ? max($use_cases) : 1;
    ?>
    <div class="fml-analytics-panel active">

        <!-- NPS Score -->
        <div class="fml-nps-display">
            <div>NPS Score</div>
            <div class="fml-nps-score"><?php echo $nps['nps'] !== null ? $nps['nps'] : '—'; ?></div>
            <div style="font-size: 13px; color: #646970;">Average: <?php echo $nps['average'] !== null ? $nps['average'] . '/10' : 'No data'; ?> (<?php echo $nps['total']; ?> responses)</div>
            <?php if ($nps['total'] > 0): ?>
            <div class="fml-nps-breakdown">
                <div class="fml-nps-group fml-nps-promoters">
                    <div class="count"><?php echo $nps['promoters']; ?></div>
                    <div class="label">Promoters (9-10)</div>
                </div>
                <div class="fml-nps-group fml-nps-passives">
                    <div class="count"><?php echo $nps['passives']; ?></div>
                    <div class="label">Passives (7-8)</div>
                </div>
                <div class="fml-nps-group fml-nps-detractors">
                    <div class="count"><?php echo $nps['detractors']; ?></div>
                    <div class="label">Detractors (0-6)</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Use Case Breakdown -->
        <?php if (!empty($use_cases)): ?>
        <div class="fml-usecase-bars">
            <h3>Use Case Breakdown</h3>
            <?php foreach ($use_cases as $case => $count): ?>
            <div class="fml-usecase-bar-row">
                <div class="fml-usecase-label"><?php echo esc_html(ucwords(str_replace('_', ' ', $case))); ?></div>
                <div class="fml-usecase-bar-wrap">
                    <div class="fml-usecase-bar" style="width: <?php echo round(($count / $max_usecase) * 100); ?>%;"></div>
                </div>
                <div class="fml-usecase-count"><?php echo $count; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Feature Requests -->
        <?php
        global $wpdb;
        $feature_requests = $wpdb->get_results(
            "SELECT feature_request, created_at, nps_score FROM {$wpdb->prefix}fml_survey_responses
             WHERE feature_request IS NOT NULL AND feature_request != ''
             ORDER BY created_at DESC LIMIT 20"
        );
        if (!empty($feature_requests)):
        ?>
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:24px;">
            <h3 style="margin:0 0 12px;">Recent Feature Requests</h3>
            <table class="widefat striped">
                <thead>
                    <tr><th>Date</th><th>NPS</th><th>Request</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($feature_requests as $req): ?>
                    <tr>
                        <td><?php echo esc_html($req->created_at); ?></td>
                        <td><?php echo $req->nps_score !== null ? esc_html($req->nps_score) : '—'; ?></td>
                        <td><?php echo esc_html($req->feature_request); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- All Responses -->
        <h3>All Responses (<?php echo $responses['total']; ?>)</h3>
        <?php
        $export_url = rest_url('FML/v1/analytics/export?type=survey') . '&_wpnonce=' . wp_create_nonce('wp_rest');
        ?>
        <p><a href="<?php echo esc_url($export_url); ?>" class="button" target="_blank">Export Survey CSV</a></p>

        <table class="widefat striped">
            <thead>
                <tr><th>ID</th><th>Date</th><th>NPS</th><th>Use Case</th><th>Ease</th><th>How Found</th><th>Trigger</th></tr>
            </thead>
            <tbody>
                <?php if (empty($responses['responses'])): ?>
                    <tr><td colspan="7">No survey responses yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($responses['responses'] as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r->id); ?></td>
                        <td><?php echo esc_html($r->created_at); ?></td>
                        <td><?php echo $r->nps_score !== null ? esc_html($r->nps_score) : '—'; ?></td>
                        <td><?php echo esc_html($r->use_case ?: '—'); ?></td>
                        <td><?php echo $r->licensing_ease !== null ? str_repeat('&#9733;', $r->licensing_ease) : '—'; ?></td>
                        <td><?php echo esc_html($r->how_found_us ?: '—'); ?></td>
                        <td><?php echo esc_html($r->trigger_type ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($responses['total_pages'] > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base'    => add_query_arg('paged', '%#%', admin_url('admin.php')),
                    'format'  => '',
                    'current' => $responses['page'],
                    'total'   => $responses['total_pages'],
                    'add_args' => ['page' => 'syncland-analytics', 'tab' => 'survey'],
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * ============================================================================
 * TAB: SETTINGS
 * ============================================================================
 */
function fml_analytics_tab_settings() {
    $settings = fml_analytics_get_settings();
    ?>
    <div class="fml-analytics-panel active">
        <form method="post" class="fml-settings-form">
            <?php wp_nonce_field('fml_analytics_settings_action'); ?>

            <table class="form-table">
                <tr>
                    <th>Event Tracking</th>
                    <td>
                        <label>
                            <input type="checkbox" name="tracking_enabled" value="1" <?php checked($settings['tracking_enabled']); ?>>
                            Enable event tracking
                        </label>
                        <p class="description">Tracks page views, song plays, cart actions, and other events.</p>
                    </td>
                </tr>
                <tr>
                    <th>Survey</th>
                    <td>
                        <label>
                            <input type="checkbox" name="survey_enabled" value="1" <?php checked($settings['survey_enabled']); ?>>
                            Enable feedback survey
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Survey: Visit Count Trigger</th>
                    <td>
                        <input type="number" name="survey_visit_count" value="<?php echo esc_attr($settings['survey_visit_count']); ?>" min="1" max="100" style="width:80px;">
                        <p class="description">Show survey after this many page views.</p>
                    </td>
                </tr>
                <tr>
                    <th>Survey: Time on Site Trigger</th>
                    <td>
                        <input type="number" name="survey_time_on_site" value="<?php echo esc_attr($settings['survey_time_on_site']); ?>" min="30" max="3600" style="width:80px;"> seconds
                        <p class="description">Show survey after this many seconds on site.</p>
                    </td>
                </tr>
                <tr>
                    <th>Survey: Post-Licensing</th>
                    <td>
                        <label>
                            <input type="checkbox" name="survey_post_licensing" value="1" <?php checked($settings['survey_post_licensing']); ?>>
                            Show survey after successful checkout
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Data Retention</th>
                    <td>
                        <input type="number" name="data_retention_days" value="<?php echo esc_attr($settings['data_retention_days']); ?>" min="30" max="3650" style="width:80px;"> days
                        <p class="description">Events and survey responses older than this are automatically deleted. IPs are anonymized after 30 days regardless.</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="fml_analytics_save_settings" class="button-primary" value="Save Settings">
            </p>
        </form>

        <!-- Purge Section -->
        <div class="fml-purge-section">
            <h3>Danger Zone: Purge All Data</h3>
            <p>This permanently deletes <strong>all</strong> analytics events and survey responses. This action cannot be undone.</p>
            <form method="post" onsubmit="return confirm('Are you absolutely sure? This will delete ALL analytics data permanently.');">
                <?php wp_nonce_field('fml_analytics_purge_action'); ?>
                <input type="submit" name="fml_analytics_purge" class="button button-secondary" value="Purge All Analytics Data" style="color: #d63638; border-color: #d63638;">
            </form>
        </div>
    </div>
    <?php
}
