<?php
/**
 * Email Settings Admin Page for Sync.Land
 *
 * Provides SMTP configuration (password or Google OAuth2),
 * notification toggles, and test email functionality.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register submenu page
add_action('admin_menu', 'fml_register_email_settings_page', 20);

function fml_register_email_settings_page() {
    add_submenu_page(
        'syncland',
        'Email Settings',
        'Email Settings',
        'manage_options',
        'syncland-email',
        'fml_email_settings_page'
    );
}

// Enqueue admin styles for this page
add_action('admin_enqueue_scripts', 'fml_email_admin_enqueue');

function fml_email_admin_enqueue($hook) {
    if ($hook !== 'sync-land_page_syncland-email') {
        return;
    }
    wp_enqueue_style(
        'fml-admin-email',
        get_stylesheet_directory_uri() . '/assets/css/admin-email.css',
        [],
        '1.0'
    );
}

// ──────────────────────────────────────────────────────────────
// OAuth2 callback handler — runs on admin_init before page render
// ──────────────────────────────────────────────────────────────
add_action('admin_init', 'fml_handle_oauth_callback');

function fml_handle_oauth_callback() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'syncland-email') {
        return;
    }
    if (!isset($_GET['code']) || !isset($_GET['state'])) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }

    // Verify state nonce
    if (!wp_verify_nonce($_GET['state'], 'fml_oauth_authorize')) {
        add_settings_error('fml_email', 'oauth_error', 'OAuth state verification failed.', 'error');
        return;
    }

    $settings      = get_option('fml_email_settings', []);
    $client_id     = $settings['oauth_client_id'] ?? '';
    $client_secret = defined('FML_OAUTH_CLIENT_SECRET')
                     ? FML_OAUTH_CLIENT_SECRET
                     : ($settings['oauth_client_secret'] ?? '');

    if (empty($client_id) || empty($client_secret)) {
        add_settings_error('fml_email', 'oauth_error', 'OAuth Client ID and Secret must be saved first.', 'error');
        return;
    }

    // Exchange authorization code for tokens
    $redirect_uri = admin_url('admin.php?page=syncland-email');
    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'body' => [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'code'          => sanitize_text_field($_GET['code']),
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirect_uri,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        add_settings_error('fml_email', 'oauth_error', 'Token exchange failed: ' . $response->get_error_message(), 'error');
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($body['error'])) {
        add_settings_error('fml_email', 'oauth_error', 'Google error: ' . ($body['error_description'] ?? $body['error']), 'error');
        return;
    }

    if (empty($body['refresh_token'])) {
        add_settings_error('fml_email', 'oauth_error', 'No refresh token received. Revoke access at myaccount.google.com/permissions and try again.', 'error');
        return;
    }

    // Save refresh token
    $settings['oauth_refresh_token'] = $body['refresh_token'];
    update_option('fml_email_settings', $settings);

    // Cache the access token
    if (!empty($body['access_token'])) {
        $expires_in = ($body['expires_in'] ?? 3600) - 60;
        set_transient('fml_google_access_token', $body['access_token'], $expires_in);
    }

    add_settings_error('fml_email', 'oauth_success', 'Google OAuth2 authorized successfully!', 'success');

    // Redirect to clean URL (remove code/state params)
    wp_safe_redirect(admin_url('admin.php?page=syncland-email&oauth=success'));
    exit;
}

// Handle form submissions
add_action('admin_init', 'fml_handle_email_settings_save');

function fml_handle_email_settings_save() {
    if (!isset($_POST['fml_email_settings_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['fml_email_settings_nonce'], 'fml_email_settings_save')) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = get_option('fml_email_settings', []);

    // SMTP settings
    $settings['smtp_host']       = sanitize_text_field($_POST['smtp_host'] ?? '');
    $settings['smtp_port']       = absint($_POST['smtp_port'] ?? 587);
    $settings['smtp_encryption'] = sanitize_text_field($_POST['smtp_encryption'] ?? 'tls');
    $settings['smtp_username']   = sanitize_text_field($_POST['smtp_username'] ?? '');
    $settings['smtp_auth_type']  = sanitize_text_field($_POST['smtp_auth_type'] ?? 'password');

    // Password auth — only update if a new value was provided
    $new_password = $_POST['smtp_password'] ?? '';
    if ($new_password !== '' && $new_password !== str_repeat("\xe2\x80\xa2", 8)) {
        $settings['smtp_password'] = $new_password;
    }

    // OAuth2 settings
    $settings['oauth_client_id'] = sanitize_text_field($_POST['oauth_client_id'] ?? '');
    $new_secret = $_POST['oauth_client_secret'] ?? '';
    if ($new_secret !== '' && $new_secret !== str_repeat("\xe2\x80\xa2", 8)) {
        $settings['oauth_client_secret'] = $new_secret;
    }

    $settings['from_email']  = sanitize_email($_POST['from_email'] ?? 'mc@sync.land');
    $settings['from_name']   = sanitize_text_field($_POST['from_name'] ?? 'Sync.Land');
    $settings['admin_email'] = sanitize_email($_POST['admin_email'] ?? get_option('admin_email'));

    // Notification toggles
    $notification_types = [
        'license_purchased',
        'license_ccby',
        'nft_complete',
        'nft_failed',
        'payment_failed',
        'album_submitted',
        'artist_created',
        'album_uploaded_user',
        'artist_profile_user',
    ];

    $toggles = [];
    foreach ($notification_types as $type) {
        $toggles[$type] = isset($_POST['notify_' . $type]);
    }
    $settings['notifications'] = $toggles;

    update_option('fml_email_settings', $settings);

    // Clear cached access token if OAuth settings changed
    delete_transient('fml_google_access_token');

    add_settings_error('fml_email', 'settings_saved', 'Email settings saved.', 'success');
}

// AJAX handler for test email
add_action('wp_ajax_fml_send_test_email', 'fml_ajax_send_test_email');

function fml_ajax_send_test_email() {
    check_ajax_referer('fml_test_email_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $to = sanitize_email($_POST['to'] ?? '');
    if (!is_email($to)) {
        wp_send_json_error('Invalid email address');
    }

    $result = fml_send_test_email($to);

    if ($result) {
        wp_send_json_success('Test email sent to ' . $to);
    } else {
        wp_send_json_error('Failed to send test email. Check your SMTP settings and server error log.');
    }
}

// AJAX handler for template preview
add_action('wp_ajax_fml_preview_email', 'fml_ajax_preview_email');

function fml_ajax_preview_email() {
    check_ajax_referer('fml_test_email_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $type = sanitize_text_field($_GET['type'] ?? '');

    $sample_data = [
        'song_name'   => 'Midnight Drive',
        'artist_name' => 'Luna Waves',
        'license_type' => 'non_exclusive',
        'amount'       => 4999,
        'currency'     => 'usd',
        'project_name' => 'Indie Film Score',
        'download_url' => home_url('/account/licenses/'),
        'pdf_url'      => home_url('/sample-license.pdf'),
        'asset_id'     => 'asset1abc123def456',
        'transaction_id' => 'tx789xyz000abc111',
        'license_id'   => '42',
        'error'        => 'NMKR API timeout after 30s',
        'album_name'   => 'Neon Horizons',
        'track_count'  => 8,
        'username'     => 'lunaartist',
    ];

    $content_map = [
        'license_purchased' => ['fn' => 'fml_email_content_license_purchased', 'title' => 'Your Sync License is Ready', 'cta' => home_url('/account/licenses/'), 'cta_text' => 'View Your Licenses'],
        'license_ccby'      => ['fn' => 'fml_email_content_license_ccby',      'title' => 'Your CC-BY License is Ready', 'cta' => home_url('/sample.pdf'), 'cta_text' => 'Download License PDF'],
        'nft_complete'       => ['fn' => 'fml_email_content_nft_complete',      'title' => 'Your License NFT Has Been Minted', 'cta' => 'https://cardanoscan.io/transaction/tx789xyz000abc111', 'cta_text' => 'View on Blockchain'],
        'nft_failed'         => ['fn' => 'fml_email_content_nft_failed',        'title' => 'NFT Minting Failed', 'cta' => admin_url('admin.php?page=syncland-nft-monitor'), 'cta_text' => 'Open NFT Monitor'],
        'payment_failed'     => ['fn' => 'fml_email_content_payment_failed',    'title' => 'Payment Could Not Be Processed', 'cta' => home_url('/cart/'), 'cta_text' => 'Try Again'],
        'album_submitted'    => ['fn' => 'fml_email_content_album_submitted',   'title' => 'New Album Submission', 'cta' => admin_url('edit.php?post_type=album'), 'cta_text' => 'Review in Admin'],
        'artist_created'     => ['fn' => 'fml_email_content_artist_created',    'title' => 'Artist Profile Created', 'cta' => admin_url('edit.php?post_type=artist'), 'cta_text' => 'View Artist'],
        'album_uploaded_user' => ['fn' => 'fml_email_content_album_uploaded_user', 'title' => 'Your Album is Live!', 'cta' => home_url('/account/artists'), 'cta_text' => 'Go to Artist Dashboard'],
        'artist_profile_user' => ['fn' => 'fml_email_content_artist_profile_user', 'title' => 'Welcome to Sync.Land!', 'cta' => home_url('/account/artists'), 'cta_text' => 'View Your Artist Page'],
        'test'               => ['fn' => 'fml_email_content_test',              'title' => 'Test Email', 'cta' => home_url('/'), 'cta_text' => 'Visit Sync.Land'],
    ];

    if (!isset($content_map[$type])) {
        wp_die('Unknown template type');
    }

    $cfg  = $content_map[$type];
    $body = call_user_func($cfg['fn'], $sample_data);
    echo fml_email_template($cfg['title'], $body, $cfg['cta'], $cfg['cta_text']);
    exit;
}

// AJAX handler for revoking OAuth
add_action('wp_ajax_fml_revoke_oauth', 'fml_ajax_revoke_oauth');

function fml_ajax_revoke_oauth() {
    check_ajax_referer('fml_test_email_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $settings = get_option('fml_email_settings', []);
    unset($settings['oauth_refresh_token']);
    update_option('fml_email_settings', $settings);
    delete_transient('fml_google_access_token');

    wp_send_json_success('OAuth authorization revoked.');
}

/**
 * Render the Email Settings page.
 */
function fml_email_settings_page() {
    $settings = get_option('fml_email_settings', []);
    $toggles  = $settings['notifications'] ?? [];

    // Defaults
    $smtp_host       = $settings['smtp_host'] ?? '';
    $smtp_port       = $settings['smtp_port'] ?? 587;
    $smtp_encryption = $settings['smtp_encryption'] ?? 'tls';
    $smtp_username   = $settings['smtp_username'] ?? '';
    $smtp_auth_type  = $settings['smtp_auth_type'] ?? 'password';
    $has_password    = !empty($settings['smtp_password']) || defined('FML_SMTP_PASSWORD');
    $from_email      = $settings['from_email'] ?? 'mc@sync.land';
    $from_name       = $settings['from_name'] ?? 'Sync.Land';
    $admin_email     = $settings['admin_email'] ?? get_option('admin_email');

    // OAuth2
    $oauth_client_id     = $settings['oauth_client_id'] ?? '';
    $has_client_secret   = !empty($settings['oauth_client_secret']) || defined('FML_OAUTH_CLIENT_SECRET');
    $has_refresh_token   = !empty($settings['oauth_refresh_token']);

    $notification_types = [
        'license_purchased' => 'License Purchased (to buyer)',
        'license_ccby'      => 'CC-BY License Generated (to requester)',
        'nft_complete'       => 'NFT Minting Complete (to license holder)',
        'nft_failed'         => 'NFT Minting Failed (to admin)',
        'payment_failed'     => 'Payment Failed (to buyer)',
        'album_submitted'    => 'Album Submitted (to admin)',
        'artist_created'     => 'Artist Created/Edited (to admin)',
        'album_uploaded_user' => 'Album Upload Confirmation (to user)',
        'artist_profile_user' => 'Artist Profile Confirmation (to user)',
    ];

    // Build OAuth authorize URL
    $oauth_redirect_uri = admin_url('admin.php?page=syncland-email');
    $oauth_state = wp_create_nonce('fml_oauth_authorize');
    $oauth_authorize_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $oauth_client_id,
        'redirect_uri'  => $oauth_redirect_uri,
        'response_type' => 'code',
        'scope'         => 'https://mail.google.com/',
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $oauth_state,
    ]);

    settings_errors('fml_email');

    // Show success message if redirected after OAuth
    if (isset($_GET['oauth']) && $_GET['oauth'] === 'success') {
        echo '<div class="notice notice-success"><p>Google OAuth2 authorized successfully!</p></div>';
    }
    ?>
    <div class="wrap fml-email-settings">
        <h1><span class="dashicons dashicons-email-alt"></span> Email Settings</h1>

        <form method="post" action="">
            <?php wp_nonce_field('fml_email_settings_save', 'fml_email_settings_nonce'); ?>

            <!-- SMTP Configuration -->
            <div class="fml-email-section">
                <h2>SMTP Configuration</h2>
                <p class="description">Configure outgoing email via SMTP. For Gmail / Google Workspace, use OAuth2 authentication.</p>

                <table class="form-table">
                    <tr>
                        <th><label for="smtp_host">SMTP Host</label></th>
                        <td><input type="text" id="smtp_host" name="smtp_host" value="<?php echo esc_attr($smtp_host); ?>" class="regular-text" placeholder="smtp.gmail.com"></td>
                    </tr>
                    <tr>
                        <th><label for="smtp_port">Port</label></th>
                        <td><input type="number" id="smtp_port" name="smtp_port" value="<?php echo esc_attr($smtp_port); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="smtp_encryption">Encryption</label></th>
                        <td>
                            <select id="smtp_encryption" name="smtp_encryption">
                                <option value="" <?php selected($smtp_encryption, ''); ?>>None</option>
                                <option value="tls" <?php selected($smtp_encryption, 'tls'); ?>>TLS</option>
                                <option value="ssl" <?php selected($smtp_encryption, 'ssl'); ?>>SSL</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="smtp_username">Username / Email</label></th>
                        <td><input type="text" id="smtp_username" name="smtp_username" value="<?php echo esc_attr($smtp_username); ?>" class="regular-text" placeholder="robot@sync.land"></td>
                    </tr>
                    <tr>
                        <th><label for="smtp_auth_type">Authentication</label></th>
                        <td>
                            <select id="smtp_auth_type" name="smtp_auth_type">
                                <option value="password" <?php selected($smtp_auth_type, 'password'); ?>>Password / App Password</option>
                                <option value="oauth2" <?php selected($smtp_auth_type, 'oauth2'); ?>>Google OAuth2</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- Password auth fields -->
                <div id="auth-password-section" style="<?php echo $smtp_auth_type === 'oauth2' ? 'display:none;' : ''; ?>">
                    <table class="form-table">
                        <tr>
                            <th><label for="smtp_password">Password</label></th>
                            <td>
                                <input type="password" id="smtp_password" name="smtp_password"
                                       value="<?php echo $has_password ? "\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2" : ''; ?>"
                                       class="regular-text"
                                       <?php echo defined('FML_SMTP_PASSWORD') ? 'disabled' : ''; ?>>
                                <?php if (defined('FML_SMTP_PASSWORD')): ?>
                                    <p class="description">Managed via <code>FML_SMTP_PASSWORD</code> in wp-config.php.</p>
                                <?php else: ?>
                                    <p class="description">For Gmail, use an <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a> or switch to OAuth2.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- OAuth2 auth fields -->
                <div id="auth-oauth2-section" style="<?php echo $smtp_auth_type !== 'oauth2' ? 'display:none;' : ''; ?>">
                    <div style="background: #f0f0f1; border-left: 4px solid #2271b1; padding: 12px 16px; margin: 15px 0;">
                        <strong>Setup:</strong> Create OAuth credentials in <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>.
                        <br>Set the <strong>Authorized redirect URI</strong> to: <code><?php echo esc_html($oauth_redirect_uri); ?></code>
                        <br>Enable the <strong>Gmail API</strong> in your project.
                    </div>

                    <table class="form-table">
                        <tr>
                            <th><label for="oauth_client_id">Client ID</label></th>
                            <td><input type="text" id="oauth_client_id" name="oauth_client_id" value="<?php echo esc_attr($oauth_client_id); ?>" class="large-text" placeholder="123456789-abc.apps.googleusercontent.com"></td>
                        </tr>
                        <tr>
                            <th><label for="oauth_client_secret">Client Secret</label></th>
                            <td>
                                <input type="password" id="oauth_client_secret" name="oauth_client_secret"
                                       value="<?php echo $has_client_secret ? "\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2\xe2\x80\xa2" : ''; ?>"
                                       class="regular-text"
                                       <?php echo defined('FML_OAUTH_CLIENT_SECRET') ? 'disabled' : ''; ?>>
                                <?php if (defined('FML_OAUTH_CLIENT_SECRET')): ?>
                                    <p class="description">Managed via <code>FML_OAUTH_CLIENT_SECRET</code> in wp-config.php.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Authorization Status</th>
                            <td>
                                <?php if ($has_refresh_token): ?>
                                    <span style="color: #00a32a; font-weight: 600;"><span class="dashicons dashicons-yes-alt"></span> Authorized</span>
                                    <button type="button" id="revoke-oauth" class="button button-link-delete" style="margin-left: 10px;">Revoke</button>
                                <?php else: ?>
                                    <span style="color: #d63638;"><span class="dashicons dashicons-warning"></span> Not authorized</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!$has_refresh_token && $oauth_client_id): ?>
                        <tr>
                            <th></th>
                            <td>
                                <p class="description">Save your Client ID and Secret first, then click below.</p>
                                <a href="<?php echo esc_url($oauth_authorize_url); ?>" class="button button-primary" style="margin-top: 5px;">
                                    <span class="dashicons dashicons-google" style="vertical-align: middle; margin-top: -2px;"></span>
                                    Authorize with Google
                                </a>
                            </td>
                        </tr>
                        <?php elseif (!$oauth_client_id): ?>
                        <tr>
                            <th></th>
                            <td><p class="description">Enter and save your Client ID and Secret, then authorize.</p></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- From / Admin fields (always visible) -->
                <table class="form-table">
                    <tr>
                        <th><label for="from_email">From Email</label></th>
                        <td><input type="email" id="from_email" name="from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="from_name">From Name</label></th>
                        <td><input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="admin_email">Admin Notification Email</label></th>
                        <td>
                            <input type="email" id="admin_email" name="admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text">
                            <p class="description">Receives album submissions, NFT failure alerts, etc.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Test Email -->
            <div class="fml-email-section">
                <h2>Send Test Email</h2>
                <p>
                    <input type="email" id="test-email-to" value="<?php echo esc_attr($admin_email); ?>" class="regular-text" placeholder="recipient@example.com">
                    <button type="button" id="send-test-email" class="button button-secondary">Send Test Email</button>
                    <span id="test-email-result" style="margin-left: 10px;"></span>
                </p>
            </div>

            <!-- Notification Toggles -->
            <div class="fml-email-section">
                <h2>Notification Toggles</h2>
                <p class="description">Enable or disable individual notification emails. All are enabled by default.</p>

                <table class="form-table">
                    <?php foreach ($notification_types as $key => $label): ?>
                        <tr>
                            <th><?php echo esc_html($label); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="notify_<?php echo esc_attr($key); ?>"
                                           <?php checked(!isset($toggles[$key]) || $toggles[$key]); ?>>
                                    Enabled
                                </label>
                                <a href="#" class="fml-preview-link" data-type="<?php echo esc_attr($key); ?>">Preview</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <?php submit_button('Save Email Settings'); ?>
        </form>
    </div>

    <script>
    jQuery(function($) {
        // Toggle auth sections
        $('#smtp_auth_type').on('change', function() {
            if ($(this).val() === 'oauth2') {
                $('#auth-password-section').hide();
                $('#auth-oauth2-section').show();
            } else {
                $('#auth-password-section').show();
                $('#auth-oauth2-section').hide();
            }
        });

        // Test email
        $('#send-test-email').on('click', function() {
            var btn = $(this);
            var to = $('#test-email-to').val();
            var result = $('#test-email-result');

            if (!to) { result.text('Enter an email address.').css('color', '#dc3545'); return; }

            btn.prop('disabled', true).text('Sending...');
            result.text('').css('color', '');

            $.post(ajaxurl, {
                action: 'fml_send_test_email',
                nonce: '<?php echo wp_create_nonce('fml_test_email_nonce'); ?>',
                to: to
            }, function(response) {
                btn.prop('disabled', false).text('Send Test Email');
                if (response.success) {
                    result.text(response.data).css('color', '#00a32a');
                } else {
                    result.text(response.data).css('color', '#dc3545');
                }
            }).fail(function() {
                btn.prop('disabled', false).text('Send Test Email');
                result.text('Request failed.').css('color', '#dc3545');
            });
        });

        // Revoke OAuth
        $('#revoke-oauth').on('click', function() {
            if (!confirm('Revoke Google OAuth2 authorization? You will need to re-authorize to send emails.')) return;
            var btn = $(this);
            btn.prop('disabled', true);
            $.post(ajaxurl, {
                action: 'fml_revoke_oauth',
                nonce: '<?php echo wp_create_nonce('fml_test_email_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                    btn.prop('disabled', false);
                }
            });
        });

        // Preview links
        $('.fml-preview-link').on('click', function(e) {
            e.preventDefault();
            var type = $(this).data('type');
            var url = ajaxurl + '?action=fml_preview_email&nonce=<?php echo wp_create_nonce('fml_test_email_nonce'); ?>&type=' + type;
            window.open(url, '_blank', 'width=700,height=800');
        });
    });
    </script>
    <?php
}
