<?php
/**
 * Artist Create/Edit Form Shortcode
 *
 * Replaces Gravity Form 8 + Pods GF Addon.
 * Usage: [fml_artist_form]
 *
 * Create mode: /my-account/artist-registration
 * Edit mode:   /account/artist-edit/?artist_edit_id=123
 */

if (!defined('ABSPATH')) {
    exit;
}

function fml_artist_form_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to manage artist profiles.</p>';
    }

    $current_user = wp_get_current_user();
    $user_id = get_current_user_id();

    // Determine create vs edit
    $edit_id = isset($_GET['artist_edit_id']) ? absint($_GET['artist_edit_id']) : 0;
    $is_edit = $edit_id > 0;

    // Verify ownership for edits
    if ($is_edit) {
        $artist_pod = pods('artist', $edit_id);
        if (!$artist_pod->exists()) {
            return '<p>Artist not found.</p>';
        }
        $post_author = absint($artist_pod->field('post_author'));
        if ($post_author !== $user_id && !current_user_can('manage_options')) {
            return '<p>You do not have permission to edit this artist.</p>';
        }
    }

    ob_start();

    // Handle form submission
    if (isset($_POST['fml_artist_form_submit'])) {
        $result = fml_process_artist_form($is_edit, $edit_id, $user_id, $current_user);
        if ($result['success']) {
            ?>
            <div class="fml-form-success">
                <i class="fas fa-check-circle" style="color: #28a745; font-size: 40px; margin-bottom: 10px;"></i>
                <h2>Artist Profile <?php echo $is_edit ? 'Updated' : 'Created'; ?> Successfully!</h2>
                <p><?php echo esc_html($result['artist_name']); ?></p>
                <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo esc_url(get_permalink($result['artist_id'])); ?>" class="button" style="background-color: #6366f1;">View Artist Page</a>
                    <a href="<?php echo esc_url(home_url('/account/artists')); ?>" class="button" style="background-color: #007bff;">Artist Dashboard</a>
                    <?php if (!$is_edit): ?>
                    <a href="<?php echo esc_url(home_url('/my-account/album-upload-add-songs/?artist_id=' . $result['artist_id'])); ?>" class="button" style="background-color: #28a745;">Upload Music</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        } else {
            echo '<div class="fml-form-error">';
            echo '<i class="fas fa-exclamation-circle"></i> ' . esc_html($result['error']);
            echo '</div>';
        }
    }

    // Load existing data for edit mode
    $data = [
        'artist_name' => '',
        'bio' => '',
        'website' => '',
        'paypal_email' => '',
        'spotify' => '',
        'apple' => '',
        'bandcamp' => '',
        'instagram' => '',
        'youtube' => '',
        'twitter' => '',
        'facebook' => '',
        'soundcloud' => '',
        'twitch' => '',
    ];

    if ($is_edit) {
        $p = pods('artist', $edit_id);
        $data['artist_name'] = $p->field('artist_name') ?: $p->field('post_title');
        $data['bio']         = $p->field('post_content');
        $data['website']     = $p->field('website');
        $data['paypal_email'] = $p->field('paypal_email');
        $data['spotify']     = $p->field('spotify');
        $data['apple']       = $p->field('apple');
        $data['bandcamp']    = $p->field('bandcamp');
        $data['instagram']   = $p->field('instagram');
        $data['youtube']     = $p->field('youtube');
        $data['twitter']     = $p->field('twitter');
        $data['facebook']    = $p->field('facebook');
        $data['soundcloud']  = $p->field('soundcloud');
        $data['twitch']      = $p->field('twitch');
    }

    // If form was submitted but failed validation, repopulate from POST
    if (isset($_POST['fml_artist_form_submit'])) {
        foreach ($data as $key => $val) {
            if ($key === 'bio') {
                $data[$key] = sanitize_textarea_field($_POST['bio'] ?? $val);
            } else {
                $data[$key] = sanitize_text_field($_POST[$key] ?? $val);
            }
        }
    }

    ?>
    <div class="fml-artist-form">
        <h1><?php echo $is_edit ? 'Edit Artist Profile' : 'Create Artist Profile'; ?></h1>
        <a href="<?php echo esc_url(home_url('/account/artists')); ?>" style="color: #6366f1;">
            <i class="fas fa-arrow-left"></i> Back to Artists
        </a>

        <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
            <?php wp_nonce_field('fml_artist_form_action', 'fml_artist_form_nonce'); ?>

            <div class="fml-field">
                <label for="artist_name" class="required">Artist / Band Name</label>
                <input type="text" id="artist_name" name="artist_name" value="<?php echo esc_attr($data['artist_name']); ?>" required placeholder="Enter artist or band name">
            </div>

            <div class="fml-field">
                <label for="bio">Biography</label>
                <textarea id="bio" name="bio" rows="5" placeholder="Tell us about the artist..."><?php echo esc_textarea($data['bio']); ?></textarea>
            </div>

            <div class="fml-field">
                <label for="profile_image"><?php echo $is_edit ? 'Update Profile Image' : 'Profile Image'; ?></label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*" class="fml-file-input">
                <div class="fml-help">Square image recommended, at least 500x500px.</div>
                <?php
                if ($is_edit) {
                    $existing_image = pods('artist', $edit_id)->display('profile_image');
                    if ($existing_image) {
                        echo '<img src="' . esc_url($existing_image) . '" alt="Current profile image" class="fml-image-preview">';
                    }
                }
                ?>
            </div>

            <div class="fml-field">
                <label for="website">Website</label>
                <input type="url" id="website" name="website" value="<?php echo esc_attr($data['website']); ?>" placeholder="https://yoursite.com">
            </div>

            <div class="fml-field">
                <label for="paypal_email">PayPal Email</label>
                <input type="email" id="paypal_email" name="paypal_email" value="<?php echo esc_attr($data['paypal_email']); ?>" placeholder="donations@example.com">
                <div class="fml-help">For receiving tips and donations.</div>
            </div>

            <div class="fml-section">
                <h3><i class="fas fa-share-alt"></i> Social Media Links</h3>
                <div class="fml-socials-grid">
                    <?php
                    $socials = [
                        'spotify'    => ['Spotify',    'fab fa-spotify',    'https://open.spotify.com/artist/...'],
                        'apple'      => ['Apple Music', 'fab fa-apple',     'https://music.apple.com/artist/...'],
                        'youtube'    => ['YouTube',    'fab fa-youtube',    'https://youtube.com/@...'],
                        'instagram'  => ['Instagram',  'fab fa-instagram',  'https://instagram.com/...'],
                        'twitter'    => ['X / Twitter', 'fab fa-x-twitter', 'https://x.com/...'],
                        'facebook'   => ['Facebook',   'fab fa-facebook',   'https://facebook.com/...'],
                        'soundcloud' => ['SoundCloud', 'fab fa-soundcloud', 'https://soundcloud.com/...'],
                        'bandcamp'   => ['Bandcamp',   'fab fa-bandcamp',   'https://...bandcamp.com'],
                        'twitch'     => ['Twitch',     'fab fa-twitch',     'https://twitch.tv/...'],
                    ];
                    foreach ($socials as $key => $info):
                    ?>
                        <div class="fml-field fml-social-field">
                            <label for="<?php echo $key; ?>"><i class="<?php echo $info[1]; ?>"></i> <?php echo $info[0]; ?></label>
                            <input type="url" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo esc_attr($data[$key]); ?>" placeholder="<?php echo esc_attr($info[2]); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <input type="hidden" name="fml_artist_form_submit" value="1">
            <button type="submit" class="fml-submit">
                <i class="fas fa-<?php echo $is_edit ? 'save' : 'plus-circle'; ?>"></i>
                <?php echo $is_edit ? 'Save Changes' : 'Create Artist Profile'; ?>
            </button>
        </form>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('fml_artist_form', 'fml_artist_form_shortcode');

/**
 * Process artist form submission.
 */
function fml_process_artist_form($is_edit, $edit_id, $user_id, $current_user) {
    // Verify nonce
    if (!isset($_POST['fml_artist_form_nonce']) || !wp_verify_nonce($_POST['fml_artist_form_nonce'], 'fml_artist_form_action')) {
        return ['success' => false, 'error' => 'Security check failed.'];
    }

    $artist_name = sanitize_text_field($_POST['artist_name'] ?? '');
    if (empty($artist_name)) {
        return ['success' => false, 'error' => 'Artist name is required.'];
    }

    $pod_data = [
        'post_title'   => $artist_name,
        'artist_name'  => $artist_name,
        'post_content' => sanitize_textarea_field($_POST['bio'] ?? ''),
        'website'      => esc_url_raw($_POST['website'] ?? ''),
        'paypal_email' => sanitize_email($_POST['paypal_email'] ?? ''),
        'spotify'      => esc_url_raw($_POST['spotify'] ?? ''),
        'apple'        => esc_url_raw($_POST['apple'] ?? ''),
        'bandcamp'     => esc_url_raw($_POST['bandcamp'] ?? ''),
        'instagram'    => esc_url_raw($_POST['instagram'] ?? ''),
        'youtube'      => esc_url_raw($_POST['youtube'] ?? ''),
        'twitter'      => esc_url_raw($_POST['twitter'] ?? ''),
        'facebook'     => esc_url_raw($_POST['facebook'] ?? ''),
        'soundcloud'   => esc_url_raw($_POST['soundcloud'] ?? ''),
        'twitch'       => esc_url_raw($_POST['twitch'] ?? ''),
    ];

    $pod = pods('artist');

    if ($is_edit) {
        $pod->save($pod_data, null, $edit_id);
        $artist_id = $edit_id;
    } else {
        $pod_data['post_status'] = 'publish';
        $pod_data['post_author'] = $user_id;
        $artist_id = $pod->add($pod_data);
        if (!$artist_id) {
            return ['success' => false, 'error' => 'Failed to create artist profile. Please try again.'];
        }
        wp_update_post(['ID' => $artist_id, 'post_status' => 'publish']);
    }

    // Handle profile image upload
    if (!empty($_FILES['profile_image']['tmp_name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('profile_image', $artist_id);
        if (!is_wp_error($attachment_id)) {
            $pod->save(['profile_image' => $attachment_id], null, $artist_id);
            set_post_thumbnail($artist_id, $attachment_id);
        } else {
            error_log('Artist profile image upload failed: ' . $attachment_id->get_error_message());
        }
    }

    // Send notifications
    if (function_exists('fml_notify_artist_created')) {
        fml_notify_artist_created(fml_get_admin_email(), [
            'artist_name' => $artist_name,
            'username'    => $current_user->user_login,
            'is_edit'     => $is_edit,
            'artist_id'   => $artist_id,
        ]);
    }

    if (function_exists('fml_notify_artist_profile_user') && !empty($current_user->user_email)) {
        fml_notify_artist_profile_user($current_user->user_email, [
            'artist_name' => $artist_name,
            'is_edit'     => $is_edit,
            'artist_id'   => $artist_id,
        ]);
    }

    return ['success' => true, 'artist_id' => $artist_id, 'artist_name' => $artist_name];
}
