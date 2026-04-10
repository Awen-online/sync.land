<?php
/**
 * [song_upload_form] shortcode — Album Upload Wizard.
 *
 * 4-step wizard: Album Info → Licensing → Songs → Review & Submit.
 * Server-side processing handles album + song creation via Pods.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function song_upload_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'artist_id' => '' ], $atts );

    $artistID = ! empty( $atts['artist_id'] )
        ? sanitize_text_field( $atts['artist_id'] )
        : ( isset( $_GET['artist_id'] ) ? sanitize_text_field( $_GET['artist_id'] ) : '' );

    $artistPod = pods( 'artist', $artistID );

    if ( ! $artistPod->exists() ) {
        return '<div class="upload-page-wrap"><div class="song-upload-notice">'
            . '<i class="fas fa-exclamation-circle"></i>'
            . '<h2>Artist Not Found</h2>'
            . '<p>We couldn\'t find the artist you\'re looking for. Please go back and try again.</p>'
            . '<a href="' . esc_url( home_url( '/account/artists' ) ) . '" class="button">Go to Artist Dashboard</a>'
            . '</div></div>';
    }
    if ( ! is_user_logged_in() ) {
        return '<div class="upload-page-wrap"><div class="song-upload-notice">'
            . '<i class="fas fa-lock"></i>'
            . '<h2>Log In to Upload Music</h2>'
            . '<p>You need an account to upload songs. Log in or create one to get started.</p>'
            . '<a href="' . esc_url( home_url( '/account' ) ) . '" class="button">Log In</a>'
            . '</div></div>';
    }

    ob_start();

    /* =========================================================================
       Form Processing (POST)
       ========================================================================= */

    if ( isset( $_POST['rightsholder'] ) && isset( $_POST['termsandcopyright'] ) ) {
        // Nonce check
        if ( ! isset( $_POST['song_upload_nonce'] ) || ! wp_verify_nonce( $_POST['song_upload_nonce'], 'song_upload_action' ) ) {
            return '<p>Security check failed.</p>';
        }

        $albumTitle  = sanitize_text_field( $_POST['album-title'] );
        $albumDesc   = sanitize_textarea_field( $_POST['albumdescription'] );
        $releasedate = sanitize_text_field( $_POST['releasedate'] );
        $albumart    = $_FILES['albumart'];
        $userIP      = $_SERVER['REMOTE_ADDR'];
        $contentID   = sanitize_text_field( $_POST['youtube-contentID'] );
        $distros     = isset( $_POST['distros'] ) && is_array( $_POST['distros'] )
            ? implode( ',', array_map( 'sanitize_text_field', $_POST['distros'] ) ) : '';
        $artist_id   = sanitize_text_field( $_POST['artistid'] );

        // Create album
        $albumPod    = pods( 'album' );
        $new_album_id = $albumPod->add( [
            'post_title'   => $albumTitle,
            'album_name'   => $albumTitle,
            'post_content' => $albumDesc,
            'user_ip'      => $userIP,
            'artist'       => $artist_id,
            'distros'      => $distros,
            'content_id'   => $contentID,
            'release_date' => $releasedate,
        ] );

        // Licensing meta
        $ccby_enabled       = ! empty( $_POST['ccby_enabled'] );
        $commercial_enabled = ! empty( $_POST['commercial_licensing'] );
        if ( ! $ccby_enabled && ! $commercial_enabled ) {
            $ccby_enabled = true;
        }
        update_post_meta( $new_album_id, '_ccby_disabled', $ccby_enabled ? '' : '1' );
        update_post_meta( $new_album_id, '_commercial_licensing', $commercial_enabled ? '1' : '' );
        if ( $commercial_enabled && ! empty( $_POST['commercial_price'] ) ) {
            $price_cents = intval( floatval( $_POST['commercial_price'] ) * 100 );
            if ( $price_cents > 0 ) {
                update_post_meta( $new_album_id, '_commercial_price', $price_cents );
            }
        }

        // Album art
        if ( ! empty( $albumart['tmp_name'] ) && ! empty( $albumart['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attachment_id = media_handle_upload( 'albumart', $new_album_id );
            if ( is_wp_error( $attachment_id ) ) {
                error_log( 'Album art upload failed: ' . $attachment_id->get_error_message() );
                return '<p>Error uploading album art: ' . esc_html( $attachment_id->get_error_message() ) . '</p>';
            }
            $albumPod->save( [ 'cover_art' => $attachment_id ], null, $new_album_id );
            set_post_thumbnail( $new_album_id, $attachment_id );
        } else {
            return '<p>Error: No album art uploaded.</p>';
        }

        // Create songs
        $songPod     = pods( 'song' );
        $numoftracks = (int) $_POST['numberoftracks'];
        for ( $i = 1; $i <= $numoftracks; $i++ ) {
            $wavormp3 = fml_ends_with( $_POST[ 'awslink' . $i ], '.wav' ) ? 'audio_url_lossless' : 'audio_url';

            // Mood/genre come as arrays from checkboxes — sanitize each ID
            $mood_raw  = isset( $_POST[ 'mood' . $i ] )   ? $_POST[ 'mood' . $i ]   : [];
            $genre_raw = isset( $_POST[ 'genres' . $i ] ) ? $_POST[ 'genres' . $i ] : [];
            $mood_ids  = is_array( $mood_raw )  ? array_map( 'absint', $mood_raw )  : [ absint( $mood_raw ) ];
            $genre_ids = is_array( $genre_raw ) ? array_map( 'absint', $genre_raw ) : [ absint( $genre_raw ) ];

            $songPod->add( [
                'post_title'   => sanitize_text_field( $_POST[ 'title' . $i ] ),
                'artist'       => $artist_id,
                'album'        => $new_album_id,
                'track_number' => $i,
                $wavormp3      => esc_url_raw( $_POST[ 'awslink' . $i ] ),
                'explicit'     => sanitize_text_field( $_POST[ 'explicit' . $i ] ),
                'instrumental' => sanitize_text_field( $_POST[ 'instrumental' . $i ] ),
                'bpm'          => sanitize_text_field( $_POST[ 'bpm' . $i ] ),
                'mood'         => $mood_ids,
                'genre'        => $genre_ids,
                'user_ip'      => $userIP,
            ] );
        }

        // Publish
        wp_update_post( [
            'ID'          => $new_album_id,
            'post_status' => 'publish',
        ] );

        // Email notifications
        $current_user      = wp_get_current_user();
        $artist_display    = $artistPod->display( 'artist_name' );

        fml_notify_album_submitted( fml_get_admin_email(), [
            'artist_name' => $artist_display,
            'album_name'  => $albumTitle,
            'track_count' => $numoftracks,
            'username'    => $current_user->user_login,
        ] );

        if ( $current_user->user_email ) {
            fml_notify_album_uploaded_user( $current_user->user_email, [
                'artist_name' => $artist_display,
                'album_name'  => $albumTitle,
                'track_count' => $numoftracks,
            ] );
        }

        ?>
        <div class="upload-page-wrap">
            <div class="song-upload-success">
                <i class="fas fa-check-circle"></i>
                <h2>Upload Complete!</h2>
                <p>"<?php echo esc_html( $albumTitle ); ?>" (<?php echo $numoftracks; ?> <?php echo $numoftracks === 1 ? 'track' : 'tracks'; ?>) has been submitted.</p>
                <a href="<?php echo esc_url( home_url( '/account/artists' ) ); ?>" class="button">
                    Go to Artist Dashboard
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* =========================================================================
       Enqueue Assets
       ========================================================================= */

    wp_enqueue_style(
        'song-upload-styles',
        get_stylesheet_directory_uri() . '/assets/css/song-upload-styles.css',
        [],
        '2.0'
    );
    wp_enqueue_script(
        'simple-upload',
        get_stylesheet_directory_uri() . '/assets/js/simpleUpload.js',
        [ 'jquery' ],
        '1.3',
        true
    );
    wp_enqueue_script(
        'song-upload-wizard',
        get_stylesheet_directory_uri() . '/assets/js/song-upload-wizard.js',
        [ 'jquery', 'simple-upload' ],
        '2.0',
        true
    );

    // Artist ID passed via data attribute on the form (works with PJAX)

    /* =========================================================================
       Load taxonomy data
       ========================================================================= */

    $genrePod = pods( 'genre', [ 'limit' => -1 ] );
    $moodPod  = pods( 'mood',  [ 'limit' => -1 ] );

    $artist_name = esc_html( $artistPod->display( 'artist_name' ) );
    ?>

    <div class="upload-page-wrap">
    <div class="upload-header">
        <h1>Upload Music &mdash; <?php echo $artist_name; ?></h1>
        <p class="step-subtitle">Singles, EPs, or full albums &mdash; all welcome.</p>
        <a class="fml-return-link" href="<?php echo esc_url( home_url( '/account/artists' ) ); ?>">
            <i class="fas fa-arrow-left"></i> Return to Account
        </a>
    </div>

    <form id="songs-upload-form" class="fml-form" method="post" enctype="multipart/form-data" data-artist-id="<?php echo esc_attr( $artistID ); ?>">

        <!-- ================================================================
             Progress Bar
             ================================================================ -->
        <ol class="wizard-progress">
            <li data-step="1"><span class="step-number">1</span><span class="step-label">Release Info</span></li>
            <li data-step="2"><span class="step-number">2</span><span class="step-label">Licensing</span></li>
            <li data-step="3"><span class="step-number">3</span><span class="step-label">Songs</span></li>
            <li data-step="4"><span class="step-number">4</span><span class="step-label">Review</span></li>
        </ol>

        <!-- ================================================================
             Step 1 — Album Info
             ================================================================ -->
        <div id="wizard-step-1" class="wizard-step active">
            <h2>Release Details</h2>
            <p class="step-subtitle">Works for singles, EPs, and albums.</p>

            <div class="form-row-2col">
                <div class="form-field">
                    <label class="required" for="album-title">Release Title</label>
                    <input type="text" id="album-title" name="album-title" placeholder="Song or album name" required>
                </div>
                <div class="form-field">
                    <label class="required" for="releasedate">Release Date</label>
                    <input type="date" id="releasedate" name="releasedate" required>
                </div>
            </div>

            <div class="form-field">
                <label for="albumdescription">Description</label>
                <textarea id="albumdescription" name="albumdescription" rows="3" placeholder="Describe your release..."></textarea>
            </div>

            <div class="form-field">
                <label class="required">Cover Art</label>
                <div class="album-art-field">
                    <div class="art-upload-area">
                        <input type="file" id="albumart" name="albumart" accept="image/*" class="file-input" required>
                        <div class="field-hint">Square image, 1000px &ndash; 5000px.</div>
                    </div>
                    <div class="album-art-preview">
                        <i class="fas fa-image placeholder-icon"></i>
                        <img id="blah" src="#" alt="Cover art preview">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-share-alt"></i> Distribution</h3>
                <p style="margin-bottom:12px;font-size:14px;color:rgba(255,255,255,0.6);">This music has also been submitted on...</p>
                <div class="distro-buttons">
                    <?php
                    $distros = [ 'distrokid', 'tunecore', 'cdbaby', 'amuse', 'songtradr', 'ditto', 'landr', 'other' ];
                    foreach ( $distros as $d ) {
                        printf(
                            '<label class="distrobtn"><input type="checkbox" name="distros[]" value="%s">%s</label>',
                            esc_attr( $d ),
                            esc_html( ucfirst( $d ) )
                        );
                    }
                    ?>
                </div>
            </div>

            <div class="form-field">
                <label class="required">
                    This music is currently in the
                    <a href="https://en.wikipedia.org/wiki/Content_ID_(system)" target="_blank">YouTube Content ID</a> system.
                </label>
                <div class="radio-group">
                    <label><input type="radio" name="youtube-contentID" value="Yes" required> Yes</label>
                    <label><input type="radio" name="youtube-contentID" value="No" required> No</label>
                    <label><input type="radio" name="youtube-contentID" value="I Don't Know" required> I Don't Know</label>
                </div>
            </div>

            <div class="wizard-nav">
                <span></span>
                <button type="button" class="wizard-btn wizard-btn-next">
                    Next: Licensing <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- ================================================================
             Step 2 — Licensing
             ================================================================ -->
        <div id="wizard-step-2" class="wizard-step">
            <h2>Licensing</h2>
            <p class="step-subtitle">Choose how your music can be licensed.</p>

            <label class="license-option is-active">
                <input type="checkbox" name="ccby_enabled" value="1" id="ccby-toggle" checked>
                <div class="license-info">
                    <h4>CC-BY 4.0 (Free, MP3)</h4>
                    <p>Anyone can use your music with credit. Great for exposure and community.</p>
                </div>
            </label>

            <label class="license-option">
                <input type="checkbox" name="commercial_licensing" value="1" id="commercial-licensing-toggle">
                <div class="license-info">
                    <h4>Commercial Sync (Paid, WAV)</h4>
                    <p>Paid license for film, TV, ads, and games. Revenue split: you receive 70%.</p>
                </div>
            </label>

            <div class="licensing-warning">At least one license type must be enabled.</div>

            <div class="commercial-pricing">
                <label for="commercial_price">Price per song (USD)</label>
                <div class="price-input-row">
                    <span>$</span>
                    <input type="number" id="commercial_price" name="commercial_price" value="49.00" min="1" step="0.01">
                </div>
                <div class="field-hint">Default: $49.00 per song. You receive 70% ($34.30 at default price).</div>
            </div>

            <div class="wizard-nav">
                <button type="button" class="wizard-btn wizard-btn-back">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="wizard-btn wizard-btn-next">
                    Next: Songs <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- ================================================================
             Step 3 — Songs
             ================================================================ -->
        <div id="wizard-step-3" class="wizard-step">
            <h2>Songs</h2>
            <p class="step-subtitle">Upload your tracks and tag them.</p>

            <div class="songs-count-selector form-field">
                <label class="required" for="songs-number">How many tracks?</label>
                <div class="field-hint" style="margin-bottom:8px;">1 for a single, 2+ for an EP or album.</div>
                <select id="songs-number" required>
                    <option value="">-- Select --</option>
                    <?php for ( $i = 1; $i <= 20; $i++ ) : ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div id="songs-upload"></div>

            <!-- Song template (hidden, cloned by JS) -->
            <div class="song-template">
                <div class="tracknumber-div">TRACK <span class="tracknumber-value"></span></div>
                <input type="hidden" name="tracknumber" readonly>

                <div class="form-field">
                    <label class="mp3Label required">Audio File (MP3 or WAV)</label>
                    <input type="file" name="file" class="file-input" accept=".mp3,.wav,audio/mpeg,audio/wav">
                    <button type="button" class="uploadbtn">Upload File</button>
                </div>
                <div class="filename"></div>
                <div class="progress-container">
                    <div class="progressBar"></div>
                    <div class="progress"></div>
                </div>

                <div class="form-field">
                    <label class="required">Song Title</label>
                    <input type="text" name="title__IDX__" required>
                </div>

                <div class="form-row-2col">
                    <div class="form-field">
                        <label>BPM</label>
                        <input type="number" name="bpm__IDX__" placeholder="e.g. 120">
                    </div>
                    <div class="form-field">
                        <label class="required">Explicit?</label>
                        <div class="radio-group">
                            <label><input type="radio" name="explicit__IDX__" value="1" required> Yes</label>
                            <label><input type="radio" name="explicit__IDX__" value="0" required> No</label>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label class="required">Instrumental?</label>
                    <div class="radio-group">
                        <label><input type="radio" name="instrumental__IDX__" value="1" required> Yes</label>
                        <label><input type="radio" name="instrumental__IDX__" value="0" required> No</label>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-heart"></i> Mood <span style="font-weight:400;font-size:12px;color:rgba(255,255,255,0.4);">(pick 1-3)</span></h3>
                    <div class="tag-grid">
                        <?php
                        if ( $moodPod->total() > 0 ) {
                            $moodPod->reset();
                            while ( $moodPod->fetch() ) {
                                printf(
                                    '<label class="tag-pill mood-pill"><input class="mood-checkbox" type="checkbox" name="mood__IDX__[]" value="%s">%s</label>',
                                    esc_attr( $moodPod->id() ),
                                    esc_html( $moodPod->display( 'title' ) )
                                );
                            }
                        }
                        ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-guitar"></i> Genre <span style="font-weight:400;font-size:12px;color:rgba(255,255,255,0.4);">(pick 1-3)</span></h3>
                    <div class="tag-grid">
                        <?php
                        if ( $genrePod->total() > 0 ) {
                            $genrePod->reset();
                            while ( $genrePod->fetch() ) {
                                printf(
                                    '<label class="tag-pill genre-pill"><input class="genre-checkbox" type="checkbox" name="genres__IDX__[]" value="%s">%s</label>',
                                    esc_attr( $genrePod->id() ),
                                    esc_html( $genrePod->display( 'title' ) )
                                );
                            }
                        }
                        ?>
                    </div>
                </div>

                <input class="awslink" type="hidden" name="awslink__IDX__">
            </div>
            <!-- /song-template -->

            <div class="wizard-nav">
                <button type="button" class="wizard-btn wizard-btn-back">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="wizard-btn wizard-btn-next">
                    Next: Review <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- ================================================================
             Step 4 — Review & Submit
             ================================================================ -->
        <div id="wizard-step-4" class="wizard-step">
            <h2>Review & Submit</h2>
            <p class="step-subtitle">Double-check everything before submitting.</p>

            <div id="review-content"></div>

            <div class="terms-group">
                <label>
                    <input type="checkbox" name="rightsholder" required>
                    I am the rights-holder of these songs and/or authorized to license them.
                </label>
                <label>
                    <input type="checkbox" name="termsandcopyright" required>
                    I have read and agree to the
                    <a href="/terms-of-use-copyright-policy/" target="_blank">Terms of Use and Copyright Policy</a>.
                </label>
            </div>

            <input class="numberoftracks" type="hidden" name="numberoftracks">
            <input type="hidden" name="artistid" value="<?php echo esc_attr( $artistID ); ?>">
            <?php wp_nonce_field( 'song_upload_action', 'song_upload_nonce' ); ?>

            <div class="wizard-nav">
                <button type="button" class="wizard-btn wizard-btn-back">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="wizard-btn wizard-btn-submit">
                    <i class="fas fa-check"></i> Submit Release
                </button>
            </div>

            <div class="draft-bar">
                <button type="button" class="fml-btn-secondary" id="fml-save-draft">Save Draft</button>
                <button type="button" class="fml-btn-secondary" id="fml-clear-draft">Clear Draft</button>
                <span class="fml-save-status" id="fml-save-status" aria-live="polite"></span>
            </div>
        </div>

    </form>
    </div><!-- /.upload-page-wrap -->

    <?php
    return ob_get_clean();
}
add_shortcode( 'song_upload_form', 'song_upload_shortcode' );

/* Helper: check string suffix */
function fml_ends_with( $haystack, $needle ) {
    $length = strlen( $needle );
    if ( 0 === $length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
}

/**
 * Insert an attachment from a URL.
 */
function crb_insert_attachment_from_url( $url, $post_id = null ) {
    if ( ! class_exists( 'WP_Http' ) ) {
        include_once ABSPATH . WPINC . '/class-http.php';
    }

    $http     = new WP_Http();
    $response = $http->request( $url );
    if ( 200 !== (int) $response['response']['code'] ) {
        return false;
    }

    $upload = wp_upload_bits( basename( $url ), null, $response['body'] );
    if ( ! empty( $upload['error'] ) ) {
        return false;
    }

    $file_path        = $upload['file'];
    $file_name        = basename( $file_path );
    $file_type        = wp_check_filetype( $file_name, null );
    $attachment_title  = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
    $wp_upload_dir     = wp_upload_dir();

    $attach_id = wp_insert_attachment( [
        'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
        'post_mime_type' => $file_type['type'],
        'post_title'     => $attachment_title,
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $file_path, $post_id );

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    return $attach_id;
}
