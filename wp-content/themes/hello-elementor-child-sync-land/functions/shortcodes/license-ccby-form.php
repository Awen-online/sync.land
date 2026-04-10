<?php
/**
 * CC-BY License Request Form Shortcode
 *
 * Replaces Gravity Form 14.
 * Usage: [fml_ccby_license_form]
 *
 * Collects license details, generates PDF via PDF_license_generator(),
 * and optionally triggers NFT minting.
 */

if (!defined('ABSPATH')) {
    exit;
}

function fml_ccby_license_form_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to request a license. <a href="' . esc_url(wp_login_url(get_permalink())) . '">Log in</a></p>';
    }

    $current_user = wp_get_current_user();

    // Check for pre-selected song from URL
    $preselected_song_id = isset($_GET['song_id']) ? absint($_GET['song_id']) : 0;

    ob_start();

    // Handle form submission
    if (isset($_POST['fml_ccby_form_submit'])) {
        $result = fml_process_ccby_form($current_user);
        if ($result['success']) {
            ?>
            <div class="fml-form-success" style="text-align: center; padding: 30px; background: rgba(40,167,69,0.15); border: 1px solid rgba(40,167,69,0.3); border-radius: 12px; margin: 20px 0;">
                <i class="fas fa-check-circle" style="color: #28a745; font-size: 40px; margin-bottom: 10px;"></i>
                <h2>Your License Has Been Generated!</h2>
                <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo esc_url($result['url']); ?>" class="button" style="display: inline-block; padding: 10px 20px; background-color: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                        <i class="fa fa-download"></i> Download License PDF
                    </a>
                    <a href="<?php echo esc_url(home_url('/account/my-licenses')); ?>" class="button" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">
                        <i class="fa fa-files"></i> View License History
                    </a>
                </div>
                <?php if (!empty($result['nft_status'])): ?>
                    <?php if ($result['nft_status'] === 'minted'): ?>
                        <p style="margin-top: 15px; color: #28a745;"><i class="fa fa-check-circle"></i> NFT minted successfully!</p>
                    <?php elseif ($result['nft_status'] === 'pending'): ?>
                        <p style="margin-top: 15px; color: #ffc107;"><i class="fa fa-clock"></i> NFT minting in progress...</p>
                    <?php elseif ($result['nft_status'] === 'failed'): ?>
                        <p style="margin-top: 15px; color: #dc3545;"><i class="fa fa-exclamation-circle"></i> NFT minting failed. You can retry from your license history.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        } else {
            echo '<div class="fml-form-error" style="padding: 15px; background: rgba(220,53,69,0.15); border: 1px solid rgba(220,53,69,0.3); border-radius: 8px; margin-bottom: 20px; color: #ef4444;">';
            echo '<i class="fas fa-exclamation-circle"></i> ' . esc_html($result['error']);
            echo '</div>';
        }
    }

    // Build song list for dropdown
    $songs_pod = pods('song', [
        'limit'   => -1,
        'orderby' => 'post_title ASC',
        'where'   => 'post_status = "publish"',
    ]);

    ?>
    <style>
        .fml-ccby-form { color: rgba(255,255,255,0.9); max-width: 700px; margin: 0 auto; }
        .fml-ccby-form h1 { color: #fff; text-align: center; margin-bottom: 10px; }
        .fml-ccby-form .fml-subtitle { text-align: center; color: rgba(255,255,255,0.6); margin-bottom: 30px; font-size: 14px; }
        .fml-ccby-form .fml-field { margin-bottom: 18px; }
        .fml-ccby-form label { display: block; margin-bottom: 5px; font-weight: 500; color: rgba(255,255,255,0.9); font-size: 14px; }
        .fml-ccby-form .required::after { content: " *"; color: #dc3545; }
        .fml-ccby-form input[type="text"],
        .fml-ccby-form input[type="date"],
        .fml-ccby-form textarea,
        .fml-ccby-form select {
            width: 100%; padding: 12px 15px; border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px; font-size: 14px; background: rgba(0,0,0,0.5); color: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .fml-ccby-form input::placeholder, .fml-ccby-form textarea::placeholder { color: rgba(255,255,255,0.4); }
        .fml-ccby-form input:focus, .fml-ccby-form textarea:focus, .fml-ccby-form select:focus {
            outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.3);
        }
        .fml-ccby-form select option { background-color: #1a1a1a; color: #fff; }
        .fml-ccby-form .fml-help { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 4px; }
        .fml-ccby-form .fml-nft-section {
            margin-top: 25px; padding: 20px; background: rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.2); border-radius: 10px;
        }
        .fml-ccby-form .fml-nft-section h3 { color: #fff; margin-top: 0; margin-bottom: 10px; font-size: 16px; }
        .fml-ccby-form .fml-checkbox-label {
            display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 400;
        }
        .fml-ccby-form .fml-checkbox-label input[type="checkbox"] { accent-color: #6366f1; width: 18px; height: 18px; }
        .fml-ccby-form #wallet_address_field { display: none; margin-top: 12px; }
        .fml-ccby-form .fml-submit {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none;
            padding: 15px 40px; border-radius: 30px; font-size: 16px; font-weight: 600;
            cursor: pointer; transition: all 0.3s ease; margin-top: 25px; display: block; width: 100%;
        }
        .fml-ccby-form .fml-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
        .fml-ccby-form .fml-terms {
            margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.3);
            border-radius: 8px; font-size: 13px; color: rgba(255,255,255,0.6); line-height: 1.6;
        }
    </style>

    <div class="fml-ccby-form">
        <h1>Request CC-BY 4.0 License</h1>
        <p class="fml-subtitle">Free Creative Commons Attribution license for personal and commercial use</p>

        <form method="post" id="fml-ccby-form">
            <?php wp_nonce_field('fml_ccby_form_action', 'fml_ccby_form_nonce'); ?>

            <div class="fml-field">
                <label for="song_id" class="required">Song</label>
                <select id="song_id" name="song_id" required>
                    <option value="">-- Select a song --</option>
                    <?php
                    if ($songs_pod->total() > 0) {
                        while ($songs_pod->fetch()) {
                            $sid = $songs_pod->id();
                            $song_title = $songs_pod->display('post_title');
                            $artist_title = $songs_pod->display('artist.post_title');
                            $label = $artist_title ? "{$artist_title} — {$song_title}" : $song_title;
                            $selected = ($sid == $preselected_song_id) ? 'selected' : '';
                            echo '<option value="' . esc_attr($sid) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="fml-field">
                <label for="licensor" class="required">Your Name / Organization</label>
                <input type="text" id="licensor" name="licensor" required
                       value="<?php echo esc_attr($_POST['licensor'] ?? $current_user->display_name); ?>"
                       placeholder="Name or organization requesting the license">
            </div>

            <div class="fml-field">
                <label for="legal_name" class="required">Legal Name</label>
                <input type="text" id="legal_name" name="legal_name" required
                       value="<?php echo esc_attr($_POST['legal_name'] ?? ''); ?>"
                       placeholder="Full legal name for the license document">
            </div>

            <div class="fml-field">
                <label for="project_name" class="required">Project Name</label>
                <input type="text" id="project_name" name="project_name" required
                       value="<?php echo esc_attr($_POST['project_name'] ?? ''); ?>"
                       placeholder="Name of the project this music will be used in">
            </div>

            <div class="fml-field">
                <label for="usage_description">Description of Usage</label>
                <textarea id="usage_description" name="usage_description" rows="3"
                          placeholder="How will you use this music? (e.g., background music for YouTube video)"><?php echo esc_textarea($_POST['usage_description'] ?? ''); ?></textarea>
            </div>

            <div class="fml-field">
                <label for="license_date" class="required">Date</label>
                <input type="date" id="license_date" name="license_date" required
                       value="<?php echo esc_attr($_POST['license_date'] ?? date('Y-m-d')); ?>">
            </div>

            <div class="fml-nft-section">
                <h3><i class="fas fa-cube"></i> Blockchain Verification (Optional)</h3>
                <p class="fml-help" style="margin-bottom: 12px;">Mint this license as an NFT on the Cardano blockchain for permanent, verifiable proof of licensing.</p>
                <label class="fml-checkbox-label">
                    <input type="checkbox" id="mint_nft" name="mint_nft" value="1"
                           <?php checked(!empty($_POST['mint_nft'])); ?>
                           onchange="document.getElementById('wallet_address_field').style.display = this.checked ? 'block' : 'none';">
                    Mint as NFT (free)
                </label>
                <div id="wallet_address_field" <?php echo !empty($_POST['mint_nft']) ? 'style="display:block;"' : ''; ?>>
                    <label for="wallet_address">Cardano Wallet Address</label>
                    <input type="text" id="wallet_address" name="wallet_address"
                           value="<?php echo esc_attr($_POST['wallet_address'] ?? ''); ?>"
                           placeholder="addr1...">
                    <div class="fml-help">Your Cardano wallet address to receive the NFT.</div>
                </div>
            </div>

            <div class="fml-terms">
                <strong>CC-BY 4.0 License Terms:</strong> You are free to share and adapt this work for any purpose,
                even commercially, as long as you give appropriate credit to the artist, provide a link to the license,
                and indicate if changes were made.
                <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" style="color: #6366f1;">Read full license</a>
            </div>

            <input type="hidden" name="fml_ccby_form_submit" value="1">
            <button type="submit" class="fml-submit">
                <i class="fas fa-file-pdf"></i> Generate License
            </button>
        </form>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('fml_ccby_license_form', 'fml_ccby_license_form_shortcode');

/**
 * Process CC-BY license form submission.
 */
function fml_process_ccby_form($current_user) {
    // Verify nonce
    if (!isset($_POST['fml_ccby_form_nonce']) || !wp_verify_nonce($_POST['fml_ccby_form_nonce'], 'fml_ccby_form_action')) {
        return ['success' => false, 'error' => 'Security check failed.'];
    }

    $song_id          = absint($_POST['song_id'] ?? 0);
    $licensor         = sanitize_text_field($_POST['licensor'] ?? '');
    $legal_name       = sanitize_text_field($_POST['legal_name'] ?? '');
    $project_name     = sanitize_text_field($_POST['project_name'] ?? '');
    $usage_description = sanitize_textarea_field($_POST['usage_description'] ?? '');
    $license_date     = sanitize_text_field($_POST['license_date'] ?? '');
    $mint_nft         = !empty($_POST['mint_nft']);
    $wallet_address   = sanitize_text_field($_POST['wallet_address'] ?? '');

    // Validate required fields
    if (!$song_id || empty($licensor) || empty($project_name) || empty($legal_name)) {
        return ['success' => false, 'error' => 'Please fill in all required fields.'];
    }

    // Validate song exists
    $song_pod = pods('song', $song_id);
    if (!$song_pod->exists()) {
        return ['success' => false, 'error' => 'Selected song not found.'];
    }

    // Validate wallet address if minting NFT
    if ($mint_nft && empty($wallet_address)) {
        return ['success' => false, 'error' => 'Please enter a Cardano wallet address for NFT minting.'];
    }

    // Log the submission
    error_log("CC-BY license request: Song ID: {$song_id}, Licensor: {$licensor}, Project: {$project_name}, Mint NFT: " . ($mint_nft ? 'yes' : 'no'));

    // Call the existing PDF generator (already decoupled from GF)
    if (!function_exists('PDF_license_generator')) {
        return ['success' => false, 'error' => 'License generator is not available. Please contact support.'];
    }

    $result = PDF_license_generator(
        $song_id,
        $licensor,
        $project_name,
        $license_date,
        $usage_description,
        $legal_name,
        '',           // signature image
        $mint_nft ? 'Yes' : '',
        $wallet_address
    );

    if (!is_array($result) || empty($result['success'])) {
        $error_msg = isset($result['error']) ? $result['error'] : 'Unknown error generating license.';
        return ['success' => false, 'error' => $error_msg];
    }

    // Send email notification
    if (function_exists('fml_notify_license_ccby') && !empty($current_user->user_email)) {
        $song_name   = $song_pod->display('post_title');
        $artist_name = $song_pod->display('artist.post_title');
        fml_notify_license_ccby($current_user->user_email, [
            'song_name'   => $song_name,
            'artist_name' => $artist_name,
        ], $result['url'] ?? '');
    }

    return [
        'success'    => true,
        'url'        => $result['url'] ?? '',
        'license_id' => $result['license_id'] ?? null,
        'nft_status' => $result['nft_status'] ?? null,
    ];
}
