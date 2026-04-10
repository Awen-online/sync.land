<?php
/**
 * SMTP Configuration for Sync.Land
 *
 * Hooks into phpmailer_init to configure SMTP with either password or OAuth2.
 * Supports Google Workspace OAuth2 (XOAUTH2) for Gmail SMTP.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OAuth2 token provider for PHPMailer XOAUTH2.
 *
 * PHPMailer calls getOauth64() during SMTP AUTH.
 * We refresh the access token via Google's token endpoint and return
 * the base64-encoded XOAUTH2 string.
 *
 * WordPress's bundled PHPMailer may not include the OAuthTokenProvider
 * interface, so we don't declare `implements` — we just provide the
 * getOauth64() method that PHPMailer calls at runtime.
 */
class FML_Google_OAuth_Provider {
    private $email;
    private $client_id;
    private $client_secret;
    private $refresh_token;

    public function __construct($email, $client_id, $client_secret, $refresh_token) {
        $this->email          = $email;
        $this->client_id      = $client_id;
        $this->client_secret  = $client_secret;
        $this->refresh_token  = $refresh_token;
    }

    /**
     * Get the base64-encoded XOAUTH2 token string for SMTP AUTH.
     */
    public function getOauth64() {
        $access_token = $this->get_access_token();
        if (empty($access_token)) {
            return '';
        }

        return base64_encode(
            "user=" . $this->email . "\1auth=Bearer " . $access_token . "\1\1"
        );
    }

    /**
     * Get a valid access token, using cached transient or refreshing.
     */
    private function get_access_token() {
        $cached = get_transient('fml_google_access_token');
        if ($cached) {
            return $cached;
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type'    => 'refresh_token',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('FML OAuth: Token refresh failed - ' . $response->get_error_message());
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            error_log('FML OAuth: No access token in response - ' . wp_remote_retrieve_body($response));
            return '';
        }

        // Cache for slightly less than the expiry (default 3600s)
        $expires_in = ($body['expires_in'] ?? 3600) - 60;
        set_transient('fml_google_access_token', $body['access_token'], $expires_in);

        return $body['access_token'];
    }
}

add_action('phpmailer_init', 'fml_configure_smtp');

function fml_configure_smtp($phpmailer) {
    $settings = get_option('fml_email_settings', []);

    if (empty($settings['smtp_host'])) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = $settings['smtp_host'];
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = $settings['smtp_port'] ?? 587;
    $phpmailer->SMTPSecure = $settings['smtp_encryption'] ?? 'tls';
    $phpmailer->Username   = $settings['smtp_username'] ?? '';
    $phpmailer->From       = $settings['from_email'] ?? 'mc@sync.land';
    $phpmailer->FromName   = $settings['from_name'] ?? 'Sync.Land';

    $auth_type = $settings['smtp_auth_type'] ?? 'password';

    if ($auth_type === 'oauth2' && !empty($settings['oauth_refresh_token'])) {
        $client_id     = $settings['oauth_client_id'] ?? '';
        $client_secret = defined('FML_OAUTH_CLIENT_SECRET')
                         ? FML_OAUTH_CLIENT_SECRET
                         : ($settings['oauth_client_secret'] ?? '');

        $provider = new FML_Google_OAuth_Provider(
            $phpmailer->Username,
            $client_id,
            $client_secret,
            $settings['oauth_refresh_token']
        );

        $phpmailer->AuthType = 'XOAUTH2';

        // WordPress's PHPMailer may not include the OAuthTokenProvider interface,
        // so we set the protected 'oauth' property via Reflection instead of setOAuth().
        $ref = new ReflectionProperty(get_class($phpmailer), 'oauth');
        $ref->setAccessible(true);
        $ref->setValue($phpmailer, $provider);
    } else {
        $phpmailer->Password = defined('FML_SMTP_PASSWORD')
                               ? FML_SMTP_PASSWORD
                               : ($settings['smtp_password'] ?? '');
    }
}
