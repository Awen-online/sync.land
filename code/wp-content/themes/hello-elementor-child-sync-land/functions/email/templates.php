<?php
/**
 * HTML Email Templates for Sync.Land
 *
 * Provides a branded HTML wrapper and per-notification content builders.
 * All styles are inline for email client compatibility.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wrap content in the Sync.Land branded HTML email template.
 *
 * @param string $title     Email heading displayed in the body.
 * @param string $body_html Inner HTML content.
 * @param string $cta_url   Optional call-to-action URL.
 * @param string $cta_text  Optional call-to-action button label.
 * @return string Complete HTML email.
 */
function fml_email_template($title, $body_html, $cta_url = '', $cta_text = '') {
    $cta_block = '';
    if ($cta_url && $cta_text) {
        $cta_url = esc_url($cta_url);
        $cta_text = esc_html($cta_text);
        $cta_block = '
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 30px auto;">
                <tr>
                    <td style="border-radius: 8px; background: #6366f1;">
                        <a href="' . $cta_url . '" target="_blank"
                           style="display: inline-block; padding: 14px 32px; font-size: 16px;
                                  color: #ffffff; text-decoration: none; font-weight: 600;
                                  font-family: Arial, sans-serif;">
                            ' . $cta_text . '
                        </a>
                    </td>
                </tr>
            </table>';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($title) . '</title>
    <style>
        @media only screen and (max-width: 620px) {
            .fml-email-outer { padding: 10px 0 !important; }
            .fml-email-inner { width: 100% !important; max-width: 100% !important; }
            .fml-email-content { padding: 24px 16px !important; border-radius: 0 !important; }
            .fml-email-header { padding: 16px 0 20px !important; }
            .fml-email-footer { padding: 20px 16px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #0a0a0f; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
           style="background-color: #0a0a0f;">
        <tr>
            <td class="fml-email-outer" style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                       width="600" class="fml-email-inner" style="margin: 0 auto; max-width: 600px; width: 100%;">

                    <!-- Header -->
                    <tr>
                        <td class="fml-email-header" style="text-align: center; padding: 20px 0 30px;">
                            <img src="https://www.sync.land/wp-content/uploads/2024/06/SYNC.LAND_.jpg"
                                 alt="Sync.Land" width="220"
                                 style="display: inline-block; max-width: 220px; height: auto;">
                        </td>
                    </tr>

                    <!-- Content Card -->
                    <tr>
                        <td class="fml-email-content" style="background-color: #1a1a2e; border-radius: 12px;
                                   padding: 40px 35px; border: 1px solid rgba(99,102,241,0.2);">

                            <h1 style="margin: 0 0 25px; font-size: 22px; color: #ffffff;
                                       font-weight: 600; line-height: 1.3;">
                                ' . esc_html($title) . '
                            </h1>

                            <div style="color: #c4c4d4; font-size: 15px; line-height: 1.7;">
                                ' . $body_html . '
                            </div>

                            ' . $cta_block . '
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="fml-email-footer" style="text-align: center; padding: 30px 20px; color: #666680;
                                   font-size: 12px; line-height: 1.6;">
                            <p style="margin: 0 0 8px;">
                                &copy; ' . date('Y') . ' Sync.Land &mdash; Music Licensing Platform
                            </p>
                            <p style="margin: 0 0 8px;">
                                <a href="' . esc_url(home_url('/')) . '" style="color: #6366f1; text-decoration: none;">
                                    sync.land
                                </a>
                            </p>
                            <p style="margin: 0; color: #555570; font-size: 11px;">
                                Sync.Land is owned and operated by <a href="https://awen.online" style="color: #666680; text-decoration: underline;">Awen LLC</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

// ──────────────────────────────────────────────────────────────
// Per-notification content builders
// ──────────────────────────────────────────────────────────────

/**
 * @param array $data Keys: song_name, artist_name, license_type, amount, currency, download_url, project_name
 */
function fml_email_content_license_purchased($data) {
    $currency_symbol = strtoupper($data['currency'] ?? 'USD') === 'USD' ? '$' : strtoupper($data['currency']) . ' ';
    $amount = number_format(($data['amount'] ?? 0) / 100, 2);

    $html  = '<p>Your sync license purchase has been processed.</p>';
    $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 20px 0;">';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3; width: 120px;">Song</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['artist_name'] ?? '') . ' &mdash; ' . esc_html($data['song_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">License</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html(ucfirst(str_replace('_', ' ', $data['license_type'] ?? 'Non-Exclusive'))) . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Project</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['project_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Amount</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($currency_symbol . $amount) . '</td></tr>';
    $html .= '</table>';

    return $html;
}

/**
 * @param array $data Keys: song_name, artist_name, pdf_url
 */
function fml_email_content_license_ccby($data) {
    $html  = '<p>Your Creative Commons Attribution 4.0 (CC-BY) license has been generated.</p>';
    $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 20px 0;">';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3; width: 120px;">Song</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['artist_name'] ?? '') . ' &mdash; ' . esc_html($data['song_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">License</td><td style="padding: 8px 0; color: #ffffff;">CC-BY 4.0 International</td></tr>';
    $html .= '</table>';
    $html .= '<p style="color: #9999b3; font-size: 13px;">You are free to share and adapt this work for any purpose, even commercially, as long as you give appropriate credit.</p>';

    return $html;
}

/**
 * @param array $data Keys: song_name, artist_name, asset_id, transaction_id
 */
function fml_email_content_nft_complete($data) {
    $html  = '<p>Your license NFT has been minted successfully on the Cardano blockchain.</p>';
    $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 20px 0;">';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3; width: 120px;">Song</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['artist_name'] ?? '') . ' &mdash; ' . esc_html($data['song_name'] ?? '') . '</td></tr>';
    if (!empty($data['asset_id'])) {
        $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Asset ID</td><td style="padding: 8px 0; color: #ffffff; word-break: break-all;">' . esc_html($data['asset_id']) . '</td></tr>';
    }
    if (!empty($data['transaction_id'])) {
        $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Transaction</td><td style="padding: 8px 0; color: #ffffff; word-break: break-all;">' . esc_html($data['transaction_id']) . '</td></tr>';
    }
    $html .= '</table>';

    return $html;
}

/**
 * @param array $data Keys: song_name, artist_name, license_id, error
 */
function fml_email_content_nft_failed($data) {
    $html  = '<p>NFT minting failed for a license. Manual intervention may be required.</p>';
    $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 20px 0;">';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3; width: 120px;">Song</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['artist_name'] ?? '') . ' &mdash; ' . esc_html($data['song_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">License ID</td><td style="padding: 8px 0; color: #ffffff;">#' . esc_html($data['license_id'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Error</td><td style="padding: 8px 0; color: #ef4444;">' . esc_html($data['error'] ?? 'Unknown error') . '</td></tr>';
    $html .= '</table>';
    $html .= '<p style="color: #9999b3; font-size: 13px;">Check the NFT Monitor dashboard for more details.</p>';

    return $html;
}

/**
 * @param array $data Keys: amount, currency, payment_id
 */
function fml_email_content_payment_failed($data) {
    $currency_symbol = strtoupper($data['currency'] ?? 'USD') === 'USD' ? '$' : strtoupper($data['currency']) . ' ';
    $amount = '';
    if (!empty($data['amount'])) {
        $amount = $currency_symbol . number_format($data['amount'] / 100, 2);
    }

    $html  = '<p>We were unable to process your payment.</p>';
    if ($amount) {
        $html .= '<p style="color: #ffffff;">Amount: ' . esc_html($amount) . '</p>';
    }
    $html .= '<p>Please check your payment method and try again. If the problem persists, contact us for assistance.</p>';

    return $html;
}

/**
 * @param array $data Keys: artist_name, album_name, track_count, username
 */
function fml_email_content_album_submitted($data) {
    $html  = '<p>A new release has been submitted and is ready for review.</p>';
    $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 20px 0;">';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3; width: 120px;">Artist</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['artist_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Release</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['album_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Tracks</td><td style="padding: 8px 0; color: #ffffff;">' . intval($data['track_count'] ?? 0) . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Submitted by</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['username'] ?? '') . '</td></tr>';
    $html .= '</table>';

    return $html;
}

/**
 * @param array $data Keys: artist_name, username, is_edit
 */
function fml_email_content_artist_created($data) {
    $is_edit = !empty($data['is_edit']);
    $action = $is_edit ? 'updated' : 'created';

    $html  = '<p>An artist profile has been ' . $action . '.</p>';
    $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 20px 0;">';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3; width: 120px;">Artist</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['artist_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Action</td><td style="padding: 8px 0; color: #ffffff;">' . ucfirst($action) . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">By</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['username'] ?? '') . '</td></tr>';
    $html .= '</table>';

    return $html;
}

/**
 * User-facing: upload confirmation.
 * @param array $data Keys: artist_name, album_name, track_count
 */
function fml_email_content_album_uploaded_user($data) {
    $html  = '<p>Your music has been uploaded successfully and is now live on Sync.Land.</p>';
    $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 20px 0;">';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3; width: 120px;">Release</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['album_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Artist</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['artist_name'] ?? '') . '</td></tr>';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3;">Tracks</td><td style="padding: 8px 0; color: #ffffff;">' . intval($data['track_count'] ?? 0) . '</td></tr>';
    $html .= '</table>';
    $html .= '<p>Your music is now available for licensing. We\'ll notify you when someone licenses one of your tracks.</p>';

    return $html;
}

/**
 * User-facing: artist profile confirmation.
 * @param array $data Keys: artist_name, is_edit
 */
function fml_email_content_artist_profile_user($data) {
    $is_edit = !empty($data['is_edit']);

    if ($is_edit) {
        $html = '<p>Your artist profile has been updated successfully.</p>';
    } else {
        $html  = '<p>Welcome to Sync.Land! Your artist profile has been created.</p>';
        $html .= '<p>You can now upload albums and start licensing your music.</p>';
    }

    $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 20px 0;">';
    $html .= '<tr><td style="padding: 8px 0; color: #9999b3; width: 120px;">Artist</td><td style="padding: 8px 0; color: #ffffff;">' . esc_html($data['artist_name'] ?? '') . '</td></tr>';
    $html .= '</table>';

    return $html;
}

/**
 * @param array $data (unused — simple confirmation message)
 */
function fml_email_content_test($data) {
    $html  = '<p>This is a test email from your Sync.Land notification system.</p>';
    $html .= '<p>If you received this message, your email configuration is working correctly.</p>';
    $html .= '<p style="color: #9999b3; font-size: 13px;">Sent at ' . esc_html(current_time('Y-m-d H:i:s')) . ' (server time)</p>';

    return $html;
}
