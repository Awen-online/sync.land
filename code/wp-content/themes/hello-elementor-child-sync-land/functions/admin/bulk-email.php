<?php
/**
 * Bulk Email Admin Page for Sync.Land
 *
 * Send branded HTML emails to all users or filtered groups.
 * Uses the existing fml_email_template() wrapper and SMTP config.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'fml_register_bulk_email_page', 25);

function fml_register_bulk_email_page() {
    add_submenu_page(
        'syncland',
        'Bulk Email',
        'Bulk Email',
        'manage_options',
        'syncland-bulk-email',
        'fml_bulk_email_page'
    );
}

add_action('admin_enqueue_scripts', 'fml_bulk_email_enqueue');

function fml_bulk_email_enqueue($hook) {
    if ($hook !== 'sync-land_page_syncland-bulk-email') {
        return;
    }
    wp_enqueue_style(
        'fml-admin-email',
        get_stylesheet_directory_uri() . '/assets/css/admin-email.css',
        [],
        '1.0'
    );
    wp_enqueue_editor();
}

// ──────────────────────────────────────────────────────────────
// Draft management
// ──────────────────────────────────────────────────────────────

function fml_get_email_drafts() {
    $drafts = get_option('fml_email_drafts', []);
    $version = get_option('fml_email_drafts_version', 0);
    if (empty($drafts) || $version < 6) {
        // Seed / update default draft (v6: account URL fix)
        $drafts = [
            'site-relaunch' => [
                'name'    => 'Site Relaunch Announcement',
                'subject' => 'Sync.Land is Back &mdash; New Ways to Earn From Your Music',
                'body'    => '<p>Hey {{display_name}},</p>

<p>Great news &mdash; <strong>Sync.Land is back online</strong> and we&rsquo;ve been building powerful new tools to help independent artists like you monetize your music.</p>

<div style="text-align: center; margin: 30px 0;">
    <img src="https://www.sync.land/wp-content/themes/hello-elementor-child-sync-land/assets/images/email-soundwave.svg" alt="~" width="200" style="display: inline-block; max-width: 200px; height: auto;">
</div>

<p style="text-align: center; margin: 0 0 5px; color: #9999b3; font-size: 13px;">Upload your tracks and start earning</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 35px;">
    <tr>
        <td style="border-radius: 8px; background: #6366f1;">
            <a href="https://www.sync.land/account/" target="_blank" style="display: inline-block; padding: 16px 40px; font-size: 17px; color: #ffffff; text-decoration: none; font-weight: 700; font-family: Arial, sans-serif;">Log In &amp; Upload Your Music</a>
        </td>
    </tr>
</table>

<h2 style="color: #ffffff; font-size: 18px; margin: 30px 0 20px; font-weight: 600;">What&rsquo;s New</h2>

<!-- 1. Artist Dashboard -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 0 0 12px;">
    <tr>
        <td style="padding: 15px 20px; background-color: #16162e; border-left: 3px solid #6366f1; border-radius: 0 8px 8px 0;">
            <strong style="color: #6366f1;">Your Artist Dashboard</strong><br>
            <span>Your new home base on Sync.Land. Upload albums, manage licensing options per release, set your own pricing, and track how many times your songs have been licensed &mdash; all in one place.</span>
        </td>
    </tr>
</table>

<!-- 2. Licensing & Revenue (combined) -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 0 0 12px;">
    <tr>
        <td style="padding: 15px 20px; background-color: #16162e; border-left: 3px solid #818cf8; border-radius: 0 8px 8px 0;">
            <strong style="color: #818cf8;">Three-Tier Licensing &mdash; You Keep 70%</strong><br>
            <span>Choose how your music gets licensed. Offer <strong style="color: #ffffff;">CC-BY</strong> (free, MP3, credit required) for maximum exposure, <strong style="color: #ffffff;">Commercial Sync</strong> (paid, WAV, full industry rights) for revenue, or <strong style="color: #ffffff;">Custom</strong> licenses for major placements negotiated by the Awen team. You set the price per album and keep <strong style="color: #ffffff;">70% of every sale</strong>.</span>
        </td>
    </tr>
</table>

<div style="text-align: center; margin: 20px 0 25px;">
    <img src="https://www.sync.land/wp-content/themes/hello-elementor-child-sync-land/assets/images/email-tiers.svg?v=3" alt="CC-BY | Commercial Sync | Custom" width="360" style="display: inline-block; max-width: 100%; height: auto;">
</div>

<!-- 3. NFT-Verified Licenses / Cardano -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 0 0 12px;">
    <tr>
        <td style="padding: 20px; background-color: #16162e; border-left: 3px solid #6366f1; border-radius: 0 8px 8px 0;">
            <strong style="color: #6366f1;">Blockchain-Verified Licenses</strong><br>
            <span>Every license on Sync.Land can be minted as an <strong style="color: #ffffff;">NFT on the Cardano blockchain</strong> &mdash; creating an immutable, verifiable record of the licensing agreement. Both artists and licensees get blockchain-backed proof of ownership. No middlemen, no disputes, just a permanent on-chain record.</span>
            <div style="text-align: center; margin-top: 18px;">
                <span style="color: #9999b3; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Powered by</span><br>
                <img src="https://www.sync.land/wp-content/themes/hello-elementor-child-sync-land/assets/images/cardano-logo-white.svg" alt="Cardano" width="140" style="display: inline-block; max-width: 140px; height: auto; margin-top: 8px;">
            </div>
        </td>
    </tr>
</table>

<p style="margin-top: 30px;">The more music we have on day one, the better the platform will be for everyone. <a href="https://www.sync.land/account/" target="_blank" style="color: #6366f1; text-decoration: underline; font-weight: 600;">Log in and upload your tracks</a> to get started.</p>

<p>We&rsquo;d also love your feedback on the new features &mdash; your early participation makes a huge difference.</p>',
                'cta_url'  => 'https://www.sync.land/contact-us/submit-feedback/',
                'cta_text' => 'Share Your Feedback',
            ],
        ];
        update_option('fml_email_drafts', $drafts);
        update_option('fml_email_drafts_version', 6);
    }
    return $drafts;
}

// ──────────────────────────────────────────────────────────────
// AJAX: Save draft
// ──────────────────────────────────────────────────────────────
add_action('wp_ajax_fml_save_email_draft', 'fml_ajax_save_email_draft');

function fml_ajax_save_email_draft() {
    check_ajax_referer('fml_bulk_email_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $drafts = get_option('fml_email_drafts', []);
    $slug = sanitize_key($_POST['draft_slug'] ?? 'custom-' . time());

    $drafts[$slug] = [
        'name'     => sanitize_text_field($_POST['draft_name'] ?? 'Untitled'),
        'subject'  => sanitize_text_field($_POST['subject'] ?? ''),
        'body'     => wp_kses_post($_POST['body'] ?? ''),
        'cta_url'  => esc_url_raw($_POST['cta_url'] ?? ''),
        'cta_text' => sanitize_text_field($_POST['cta_text'] ?? ''),
    ];

    update_option('fml_email_drafts', $drafts);
    wp_send_json_success(['slug' => $slug, 'message' => 'Draft saved.']);
}

// ──────────────────────────────────────────────────────────────
// AJAX: Delete draft
// ──────────────────────────────────────────────────────────────
add_action('wp_ajax_fml_delete_email_draft', 'fml_ajax_delete_email_draft');

function fml_ajax_delete_email_draft() {
    check_ajax_referer('fml_bulk_email_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $slug = sanitize_key($_POST['draft_slug'] ?? '');
    if (empty($slug)) {
        wp_send_json_error('No draft specified.');
    }

    $drafts = get_option('fml_email_drafts', []);
    unset($drafts[$slug]);
    update_option('fml_email_drafts', $drafts);

    wp_send_json_success('Draft deleted.');
}

// ──────────────────────────────────────────────────────────────
// AJAX: Preview email
// ──────────────────────────────────────────────────────────────
add_action('wp_ajax_fml_preview_bulk_email', 'fml_ajax_preview_bulk_email');

function fml_ajax_preview_bulk_email() {
    check_ajax_referer('fml_bulk_email_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $subject  = sanitize_text_field($_POST['subject'] ?? 'Preview');
    $body     = wp_kses_post($_POST['body'] ?? '');
    $cta_url  = esc_url($_POST['cta_url'] ?? '');
    $cta_text = sanitize_text_field($_POST['cta_text'] ?? '');

    // Replace merge tags with sample data
    $body = str_replace(
        ['{{display_name}}', '{{user_email}}', '{{first_name}}'],
        ['Jane Artist', 'jane@example.com', 'Jane'],
        $body
    );

    echo fml_email_template($subject, $body, $cta_url, $cta_text);
    exit;
}

// ──────────────────────────────────────────────────────────────
// AJAX: Send bulk email
// ──────────────────────────────────────────────────────────────
add_action('wp_ajax_fml_send_bulk_email', 'fml_ajax_send_bulk_email');

function fml_ajax_send_bulk_email() {
    check_ajax_referer('fml_bulk_email_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $subject   = sanitize_text_field($_POST['subject'] ?? '');
    $body      = wp_kses_post($_POST['body'] ?? '');
    $cta_url   = esc_url_raw($_POST['cta_url'] ?? '');
    $cta_text  = sanitize_text_field($_POST['cta_text'] ?? '');
    $audience  = sanitize_text_field($_POST['audience'] ?? 'all');
    $test_only = !empty($_POST['test_only']);
    $test_to   = sanitize_email($_POST['test_to'] ?? '');

    if (empty($subject) || empty($body)) {
        wp_send_json_error('Subject and body are required.');
    }

    // Test send — single email
    if ($test_only) {
        if (!is_email($test_to)) {
            wp_send_json_error('Invalid test email address.');
        }

        $personalized = str_replace(
            ['{{display_name}}', '{{user_email}}', '{{first_name}}'],
            ['Test User', $test_to, 'Test'],
            $body
        );

        $html = fml_email_template($subject, $personalized, $cta_url, $cta_text);
        $result = fml_send_html_email($test_to, $subject, $html);

        if ($result) {
            wp_send_json_success(['sent' => 1, 'failed' => 0, 'message' => "Test email sent to {$test_to}."]);
        } else {
            wp_send_json_error('Failed to send test email.');
        }
        return;
    }

    // Build user query
    $args = ['fields' => ['ID', 'user_email', 'display_name']];

    switch ($audience) {
        case 'artists':
            // Users who have authored at least one 'artist' CPT
            $artist_authors = get_posts([
                'post_type'   => 'artist',
                'post_status' => 'publish',
                'fields'      => 'ids',
                'numberposts' => -1,
            ]);
            $author_ids = array_unique(array_map(function ($id) {
                return get_post_field('post_author', $id);
            }, $artist_authors));
            if (empty($author_ids)) {
                wp_send_json_success(['sent' => 0, 'failed' => 0, 'message' => 'No artist users found.']);
                return;
            }
            $args['include'] = $author_ids;
            break;

        case 'subscribers':
            $args['role'] = 'subscriber';
            break;

        case 'all':
        default:
            $args['role__not_in'] = ['administrator'];
            break;
    }

    $users = get_users($args);

    if (empty($users)) {
        wp_send_json_success(['sent' => 0, 'failed' => 0, 'message' => 'No users found for this audience.']);
        return;
    }

    $sent = 0;
    $failed = 0;
    $campaign_id = fml_generate_campaign_id();

    foreach ($users as $user) {
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $personalized = str_replace(
            ['{{display_name}}', '{{user_email}}', '{{first_name}}'],
            [$user->display_name, $user->user_email, $first_name ?: $user->display_name],
            $body
        );

        $html = fml_email_template($subject, $personalized, $cta_url, $cta_text);

        // Add open/click tracking
        $token = fml_create_tracking_token($campaign_id, $user->user_email);
        $html  = fml_rewrite_links_for_tracking($html, $token);
        $html  = fml_inject_tracking_pixel($html, $token);

        try {
            $result = fml_send_html_email($user->user_email, $subject, $html);
            if ($result) {
                $sent++;
            } else {
                $failed++;
                error_log("FML Bulk Email: Failed to send to {$user->user_email}");
            }
        } catch (Exception $e) {
            $failed++;
            error_log("FML Bulk Email: Exception sending to {$user->user_email}: " . $e->getMessage());
        }

        // Small delay to avoid rate limiting
        if ($sent % 10 === 0) {
            usleep(500000); // 0.5s every 10 emails
        }
    }

    // Log the send
    $log = get_option('fml_bulk_email_log', []);
    $log[] = [
        'date'        => current_time('Y-m-d H:i:s'),
        'subject'     => $subject,
        'audience'    => $audience,
        'sent'        => $sent,
        'failed'      => $failed,
        'campaign_id' => $campaign_id,
    ];
    // Keep last 50 entries
    if (count($log) > 50) {
        $log = array_slice($log, -50);
    }
    update_option('fml_bulk_email_log', $log);

    wp_send_json_success([
        'sent'    => $sent,
        'failed'  => $failed,
        'message' => "Sent {$sent} emails" . ($failed ? ", {$failed} failed" : '') . '.',
    ]);
}

// ──────────────────────────────────────────────────────────────
// Admin page render
// ──────────────────────────────────────────────────────────────

function fml_bulk_email_page() {
    $drafts = fml_get_email_drafts();
    $current_slug = $_GET['draft'] ?? array_key_first($drafts);
    $draft = $drafts[$current_slug] ?? reset($drafts);
    if ($current_slug === null) {
        $current_slug = array_key_first($drafts);
    }

    $settings   = get_option('fml_email_settings', []);
    $admin_email = $settings['admin_email'] ?? get_option('admin_email');

    // User counts
    $all_count = count(get_users(['fields' => 'ID', 'role__not_in' => ['administrator']]));
    $sub_count = count(get_users(['fields' => 'ID', 'role' => 'subscriber']));
    $artist_posts = get_posts(['post_type' => 'artist', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => -1]);
    $artist_count = count(array_unique(array_map(function ($id) { return get_post_field('post_author', $id); }, $artist_posts)));

    // Send log
    $log = get_option('fml_bulk_email_log', []);
    $log = array_reverse($log);

    $nonce = wp_create_nonce('fml_bulk_email_nonce');
    ?>
    <div class="wrap fml-email-settings">
        <h1><span class="dashicons dashicons-megaphone"></span> Bulk Email</h1>

        <!-- Draft selector -->
        <div class="fml-email-section" style="padding: 15px 20px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <label for="draft-selector"><strong>Draft:</strong></label>
                <select id="draft-selector">
                    <?php foreach ($drafts as $slug => $d): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, $current_slug); ?>
                                data-subject="<?php echo esc_attr($d['subject']); ?>"
                                data-body="<?php echo esc_attr($d['body']); ?>"
                                data-cta-url="<?php echo esc_attr($d['cta_url']); ?>"
                                data-cta-text="<?php echo esc_attr($d['cta_text']); ?>"
                                data-name="<?php echo esc_attr($d['name']); ?>">
                            <?php echo esc_html($d['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="new-draft-btn" class="button">+ New Draft</button>
                <button type="button" id="delete-draft-btn" class="button button-link-delete">Delete</button>
            </div>
        </div>

        <!-- Compose -->
        <div class="fml-email-section">
            <h2>Compose</h2>
            <p class="description">Available merge tags: <code>{{display_name}}</code>, <code>{{first_name}}</code>, <code>{{user_email}}</code></p>

            <table class="form-table">
                <tr>
                    <th><label for="draft-name">Draft Name</label></th>
                    <td><input type="text" id="draft-name" class="regular-text" value="<?php echo esc_attr($draft['name']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="email-subject">Subject Line</label></th>
                    <td><input type="text" id="email-subject" class="large-text" value="<?php echo esc_attr($draft['subject']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="email-body">Body</label></th>
                    <td>
                        <?php
                        wp_editor($draft['body'], 'email-body', [
                            'textarea_rows' => 14,
                            'media_buttons' => false,
                            'teeny'         => false,
                            'quicktags'     => true,
                        ]);
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="cta-url">CTA Button URL</label></th>
                    <td><input type="url" id="cta-url" class="large-text" value="<?php echo esc_attr($draft['cta_url']); ?>" placeholder="https://www.sync.land/contact-us/submit-feedback/"></td>
                </tr>
                <tr>
                    <th><label for="cta-text">CTA Button Text</label></th>
                    <td><input type="text" id="cta-text" class="regular-text" value="<?php echo esc_attr($draft['cta_text']); ?>" placeholder="Give Feedback"></td>
                </tr>
            </table>

            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button type="button" id="save-draft-btn" class="button button-secondary">Save Draft</button>
                <button type="button" id="preview-btn" class="button">Preview Email</button>
            </div>
            <span id="draft-status" style="margin-left: 10px;"></span>
        </div>

        <!-- Audience & Send -->
        <div class="fml-email-section">
            <h2>Audience & Send</h2>

            <table class="form-table">
                <tr>
                    <th>Audience</th>
                    <td>
                        <select id="audience">
                            <option value="all">All non-admin users (<?php echo $all_count; ?>)</option>
                            <option value="artists">Artists only (<?php echo $artist_count; ?>)</option>
                            <option value="subscribers">Subscribers only (<?php echo $sub_count; ?>)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Test First</th>
                    <td>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="email" id="test-email" class="regular-text" value="<?php echo esc_attr($admin_email); ?>" placeholder="your@email.com">
                            <button type="button" id="send-test-btn" class="button">Send Test</button>
                        </div>
                        <span id="test-result" style="margin-left: 5px;"></span>
                    </td>
                </tr>
            </table>

            <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <strong>Warning:</strong> Sending bulk email cannot be undone. Always send a test first.
            </div>

            <div style="margin-top: 15px;">
                <button type="button" id="send-bulk-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-email" style="vertical-align: middle; margin-top: -2px;"></span>
                    Send to All Selected Users
                </button>
                <span id="send-result" style="display: block; margin-top: 10px; font-weight: 600;"></span>
            </div>
        </div>

        <!-- Send History -->
        <?php if (!empty($log)): ?>
        <div class="fml-email-section">
            <h2>Send History</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Subject</th>
                        <th>Audience</th>
                        <th>Sent</th>
                        <th>Opens</th>
                        <th>Clicks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($log, 0, 15) as $entry):
                        $stats = null;
                        if (!empty($entry['campaign_id'])) {
                            $stats = fml_get_campaign_stats($entry['campaign_id']);
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html($entry['date']); ?></td>
                            <td><?php echo esc_html($entry['subject']); ?></td>
                            <td><?php echo esc_html($entry['audience']); ?></td>
                            <td><?php echo intval($entry['sent']); ?><?php if ($entry['failed'] ?? 0): ?> <span style="color: #dc3545;">(<?php echo intval($entry['failed']); ?> failed)</span><?php endif; ?></td>
                            <td>
                                <?php if ($stats): ?>
                                    <?php echo $stats['opens']; ?> <span style="color: #9999b3;">(<?php echo $stats['open_rate']; ?>%)</span>
                                <?php else: ?>
                                    <span style="color: #9999b3;">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($stats): ?>
                                    <?php echo $stats['clicks']; ?> <span style="color: #9999b3;">(<?php echo $stats['click_rate']; ?>%)</span>
                                <?php else: ?>
                                    <span style="color: #9999b3;">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(function($) {
        var nonce = '<?php echo $nonce; ?>';
        var currentSlug = '<?php echo esc_js($current_slug); ?>';

        function getBody() {
            // wp_editor may use TinyMCE or textarea
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email-body')) {
                return tinyMCE.get('email-body').getContent();
            }
            return $('#email-body').val();
        }

        // Draft selector
        $('#draft-selector').on('change', function() {
            var opt = $(this).find(':selected');
            currentSlug = opt.val();
            $('#draft-name').val(opt.data('name'));
            $('#email-subject').val(opt.data('subject'));
            $('#cta-url').val(opt.data('cta-url'));
            $('#cta-text').val(opt.data('cta-text'));
            // Set body in editor
            var body = opt.data('body');
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email-body')) {
                tinyMCE.get('email-body').setContent(body);
            } else {
                $('#email-body').val(body);
            }
        });

        // New draft
        $('#new-draft-btn').on('click', function() {
            var name = prompt('Draft name:');
            if (!name) return;
            currentSlug = 'custom-' + Date.now();
            $('#draft-selector').append('<option value="' + currentSlug + '" data-name="' + name + '" data-subject="" data-body="" data-cta-url="" data-cta-text="">' + name + '</option>');
            $('#draft-selector').val(currentSlug).trigger('change');
            $('#draft-name').val(name);
        });

        // Delete draft
        $('#delete-draft-btn').on('click', function() {
            if (!confirm('Delete this draft?')) return;
            $.post(ajaxurl, { action: 'fml_delete_email_draft', nonce: nonce, draft_slug: currentSlug }, function(r) {
                if (r.success) location.reload();
                else alert(r.data);
            });
        });

        // Save draft
        $('#save-draft-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Saving...');
            $.post(ajaxurl, {
                action: 'fml_save_email_draft',
                nonce: nonce,
                draft_slug: currentSlug,
                draft_name: $('#draft-name').val(),
                subject: $('#email-subject').val(),
                body: getBody(),
                cta_url: $('#cta-url').val(),
                cta_text: $('#cta-text').val()
            }, function(r) {
                btn.prop('disabled', false).text('Save Draft');
                $('#draft-status').text(r.success ? 'Saved!' : r.data).css('color', r.success ? '#00a32a' : '#dc3545');
                if (r.success) {
                    currentSlug = r.data.slug;
                    setTimeout(function() { $('#draft-status').text(''); }, 3000);
                }
            });
        });

        // Preview
        $('#preview-btn').on('click', function() {
            var form = $('<form method="post" target="_blank"></form>').attr('action', ajaxurl);
            var fields = {
                action: 'fml_preview_bulk_email',
                nonce: nonce,
                subject: $('#email-subject').val(),
                body: getBody(),
                cta_url: $('#cta-url').val(),
                cta_text: $('#cta-text').val()
            };
            $.each(fields, function(name, val) {
                form.append($('<input type="hidden">').attr('name', name).val(val));
            });
            $('body').append(form);
            form.submit();
            form.remove();
        });

        // Test send
        $('#send-test-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Sending...');
            $('#test-result').text('');
            $.post(ajaxurl, {
                action: 'fml_send_bulk_email',
                nonce: nonce,
                subject: $('#email-subject').val(),
                body: getBody(),
                cta_url: $('#cta-url').val(),
                cta_text: $('#cta-text').val(),
                test_only: 1,
                test_to: $('#test-email').val()
            }, function(r) {
                btn.prop('disabled', false).text('Send Test');
                if (r.success) {
                    $('#test-result').text(r.data.message).css('color', '#00a32a');
                } else {
                    $('#test-result').text(r.data).css('color', '#dc3545');
                }
            }).fail(function() {
                btn.prop('disabled', false).text('Send Test');
                $('#test-result').text('Request failed.').css('color', '#dc3545');
            });
        });

        // Bulk send
        $('#send-bulk-btn').on('click', function() {
            var audience = $('#audience option:selected').text();
            if (!confirm('Send "' + $('#email-subject').val() + '" to ' + audience + '?\n\nThis cannot be undone.')) return;

            var btn = $(this);
            btn.prop('disabled', true).text('Sending...');
            $('#send-result').text('');

            $.post(ajaxurl, {
                action: 'fml_send_bulk_email',
                nonce: nonce,
                subject: $('#email-subject').val(),
                body: getBody(),
                cta_url: $('#cta-url').val(),
                cta_text: $('#cta-text').val(),
                audience: $('#audience').val()
            }, function(r) {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-email" style="vertical-align: middle; margin-top: -2px;"></span> Send to All Selected Users');
                if (r.success) {
                    $('#send-result').text(r.data.message).css('color', '#00a32a');
                } else {
                    $('#send-result').text(r.data).css('color', '#dc3545');
                }
            }).fail(function() {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-email" style="vertical-align: middle; margin-top: -2px;"></span> Send to All Selected Users');
                $('#send-result').text('Request failed.').css('color', '#dc3545');
            });
        });
    });
    </script>
    <?php
}
