<?php
/**
 * Email Notification Functions for Sync.Land
 *
 * High-level send functions for each notification type.
 * Each checks if the notification type is enabled before sending.
 * Email failures are logged but never break the main flow.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Send an HTML email using wp_mail with per-call Content-Type header.
 *
 * @param string $to      Recipient email.
 * @param string $subject Email subject.
 * @param string $html    Full HTML body.
 * @return bool Whether wp_mail reported success.
 */
function fml_send_html_email($to, $subject, $html) {
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $settings = get_option('fml_email_settings', []);
    $from_email = $settings['from_email'] ?? 'mc@sync.land';
    $from_name  = $settings['from_name'] ?? 'Sync.Land';
    $headers[]  = "From: {$from_name} <{$from_email}>";

    return wp_mail($to, $subject, $html, $headers);
}

/**
 * Check whether a given notification type is enabled.
 *
 * @param string $type Notification key (e.g. 'license_purchased').
 * @return bool
 */
function fml_notification_enabled($type) {
    $settings = get_option('fml_email_settings', []);
    $toggles  = $settings['notifications'] ?? [];

    // All notifications default to enabled
    return !isset($toggles[$type]) || $toggles[$type];
}

/**
 * Get the configured admin notification email.
 */
function fml_get_admin_email() {
    $settings = get_option('fml_email_settings', []);
    return $settings['admin_email'] ?? get_option('admin_email');
}

// ──────────────────────────────────────────────────────────────
// Notification functions
// ──────────────────────────────────────────────────────────────

/**
 * Notify buyer after a successful license purchase.
 *
 * @param string $user_email Buyer email.
 * @param array  $song_data  Keys: song_name, artist_name
 * @param array  $license_data Keys: license_type, amount, currency, download_url, project_name
 */
function fml_notify_license_purchased($user_email, $song_data, $license_data) {
    if (!fml_notification_enabled('license_purchased')) return;

    try {
        $data = array_merge($song_data, $license_data);
        $body = fml_email_content_license_purchased($data);
        $html = fml_email_template(
            'Your Sync License is Ready',
            $body,
            $license_data['download_url'] ?? home_url('/account/licenses/'),
            'View Your Licenses'
        );

        fml_send_html_email(
            $user_email,
            'Your Sync.Land License for "' . ($song_data['song_name'] ?? 'your song') . '"',
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_license_purchased failed: ' . $e->getMessage());
    }
}

/**
 * Notify requester after CC-BY license PDF is generated.
 *
 * @param string $user_email Requester email.
 * @param array  $song_data  Keys: song_name, artist_name
 * @param string $pdf_url    URL to the generated PDF.
 */
function fml_notify_license_ccby($user_email, $song_data, $pdf_url) {
    if (!fml_notification_enabled('license_ccby')) return;

    try {
        $data = array_merge($song_data, ['pdf_url' => $pdf_url]);
        $body = fml_email_content_license_ccby($data);
        $html = fml_email_template(
            'Your CC-BY License is Ready',
            $body,
            $pdf_url,
            'Download License PDF'
        );

        fml_send_html_email(
            $user_email,
            'Your CC-BY License for "' . ($song_data['song_name'] ?? 'your song') . '"',
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_license_ccby failed: ' . $e->getMessage());
    }
}

/**
 * Notify license holder after successful NFT minting.
 *
 * @param string $user_email License holder email.
 * @param array  $song_data  Keys: song_name, artist_name
 * @param array  $nft_data   Keys: asset_id, transaction_id
 */
function fml_notify_nft_minting_complete($user_email, $song_data, $nft_data) {
    if (!fml_notification_enabled('nft_complete')) return;

    try {
        $data = array_merge($song_data, $nft_data);
        $body = fml_email_content_nft_complete($data);

        $explorer_url = '';
        if (!empty($nft_data['transaction_id'])) {
            $explorer_url = 'https://cardanoscan.io/transaction/' . $nft_data['transaction_id'];
        }

        $html = fml_email_template(
            'Your License NFT Has Been Minted',
            $body,
            $explorer_url ?: home_url('/account/licenses/'),
            $explorer_url ? 'View on Blockchain' : 'View Your Licenses'
        );

        fml_send_html_email(
            $user_email,
            'NFT Minted for "' . ($song_data['song_name'] ?? 'your song') . '"',
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_nft_minting_complete failed: ' . $e->getMessage());
    }
}

/**
 * Notify admin after NFT minting fails.
 *
 * @param string $admin_email Admin email.
 * @param array  $song_data   Keys: song_name, artist_name, license_id
 * @param string $error       Error message.
 */
function fml_notify_nft_minting_failed($admin_email, $song_data, $error) {
    if (!fml_notification_enabled('nft_failed')) return;

    try {
        $data = array_merge($song_data, ['error' => $error]);
        $body = fml_email_content_nft_failed($data);
        $html = fml_email_template(
            'NFT Minting Failed',
            $body,
            admin_url('admin.php?page=syncland-nft-monitor'),
            'Open NFT Monitor'
        );

        fml_send_html_email(
            $admin_email,
            'NFT Minting Failed — License #' . ($song_data['license_id'] ?? '?'),
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_nft_minting_failed failed: ' . $e->getMessage());
    }
}

/**
 * Notify buyer after a payment failure.
 *
 * @param string $user_email Buyer email.
 * @param array  $payment_data Keys: amount, currency, payment_id
 */
function fml_notify_payment_failed($user_email, $payment_data) {
    if (!fml_notification_enabled('payment_failed')) return;

    try {
        $body = fml_email_content_payment_failed($payment_data);
        $html = fml_email_template(
            'Payment Could Not Be Processed',
            $body,
            home_url('/cart/'),
            'Try Again'
        );

        fml_send_html_email(
            $user_email,
            'Sync.Land — Payment Failed',
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_payment_failed failed: ' . $e->getMessage());
    }
}

/**
 * Notify admin when a new album is submitted.
 *
 * @param string $admin_email Admin email.
 * @param array  $album_data  Keys: artist_name, album_name, track_count, username
 */
function fml_notify_album_submitted($admin_email, $album_data) {
    if (!fml_notification_enabled('album_submitted')) return;

    try {
        $body = fml_email_content_album_submitted($album_data);
        $html = fml_email_template(
            'New Release Submission',
            $body,
            admin_url('edit.php?post_type=album'),
            'Review in Admin'
        );

        fml_send_html_email(
            $admin_email,
            'New Release: "' . ($album_data['album_name'] ?? '') . '" by ' . ($album_data['artist_name'] ?? 'Unknown'),
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_album_submitted failed: ' . $e->getMessage());
    }
}

/**
 * Notify admin when an artist profile is created or edited.
 *
 * @param string $admin_email Admin email.
 * @param array  $artist_data Keys: artist_name, username, is_edit, artist_id
 */
function fml_notify_artist_created($admin_email, $artist_data) {
    if (!fml_notification_enabled('artist_created')) return;

    try {
        $is_edit = !empty($artist_data['is_edit']);
        $action  = $is_edit ? 'Updated' : 'Created';

        $body = fml_email_content_artist_created($artist_data);

        $cta_url = !empty($artist_data['artist_id'])
            ? get_permalink($artist_data['artist_id'])
            : admin_url('edit.php?post_type=artist');

        $html = fml_email_template(
            "Artist Profile {$action}",
            $body,
            $cta_url ?: admin_url('edit.php?post_type=artist'),
            'View Artist'
        );

        fml_send_html_email(
            $admin_email,
            "Artist {$action}: \"" . ($artist_data['artist_name'] ?? 'Unknown') . '"',
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_artist_created failed: ' . $e->getMessage());
    }
}

/**
 * Notify the user that their album was uploaded successfully.
 *
 * @param string $user_email User email.
 * @param array  $album_data Keys: artist_name, album_name, track_count
 */
function fml_notify_album_uploaded_user($user_email, $album_data) {
    if (!fml_notification_enabled('album_uploaded_user')) return;

    try {
        $body = fml_email_content_album_uploaded_user($album_data);
        $html = fml_email_template(
            'Your Music is Live!',
            $body,
            home_url('/account/artists'),
            'Go to Artist Dashboard'
        );

        fml_send_html_email(
            $user_email,
            'Upload Complete: "' . ($album_data['album_name'] ?? '') . '"',
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_album_uploaded_user failed: ' . $e->getMessage());
    }
}

/**
 * Notify the user that their artist profile was created or updated.
 *
 * @param string $user_email User email.
 * @param array  $artist_data Keys: artist_name, is_edit, artist_id
 */
function fml_notify_artist_profile_user($user_email, $artist_data) {
    if (!fml_notification_enabled('artist_profile_user')) return;

    try {
        $is_edit = !empty($artist_data['is_edit']);
        $action  = $is_edit ? 'Updated' : 'Created';

        $body = fml_email_content_artist_profile_user($artist_data);

        $cta_url = !empty($artist_data['artist_id'])
            ? get_permalink($artist_data['artist_id'])
            : home_url('/account/artists');

        $html = fml_email_template(
            $is_edit ? 'Artist Profile Updated' : 'Welcome to Sync.Land!',
            $body,
            $cta_url ?: home_url('/account/artists'),
            'View Your Artist Page'
        );

        fml_send_html_email(
            $user_email,
            "Artist Profile {$action}: \"" . ($artist_data['artist_name'] ?? '') . '"',
            $html
        );
    } catch (\Throwable $e) {
        error_log('fml_notify_artist_profile_user failed: ' . $e->getMessage());
    }
}

/**
 * Send a test email from the admin settings page.
 *
 * @param string $to Recipient email.
 * @return bool
 */
function fml_send_test_email($to) {
    try {
        $body = fml_email_content_test([]);
        $html = fml_email_template(
            'Test Email',
            $body,
            home_url('/'),
            'Visit Sync.Land'
        );

        return fml_send_html_email($to, 'Sync.Land — Test Email', $html);
    } catch (\Throwable $e) {
        error_log('fml_send_test_email failed: ' . $e->getMessage());
        return false;
    }
}
