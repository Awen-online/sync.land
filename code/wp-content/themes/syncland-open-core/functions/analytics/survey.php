<?php
/**
 * Survey System
 *
 * Provides the survey modal HTML output in wp_footer, shortcode for manual
 * trigger, and configuration for trigger conditions.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output a slim dismissable survey banner at the bottom of the viewport.
 * Trigger logic lives in survey.js; this just outputs hidden markup that
 * survey.js reveals when the trigger conditions are met.
 */
function fml_survey_modal_output() {
    $settings = fml_analytics_get_settings();
    if (!$settings['survey_enabled']) {
        return;
    }
    if (is_admin()) {
        return;
    }

    // Logged-in dismissal carries server-side too (keeps logic consistent
    // across devices for the same user).
    if (is_user_logged_in()) {
        $dismissed = get_user_meta(get_current_user_id(), 'fml_survey_dismissed', true);
        if ($dismissed && (time() - (int) $dismissed) < (90 * DAY_IN_SECONDS)) {
            return;
        }
    }

    $survey_url = home_url('/contact-us/submit-feedback/');
    ?>
    <div id="fml-survey-banner" class="fml-survey-banner" hidden role="region" aria-label="Feedback survey invitation">
        <div class="fml-survey-banner-inner">
            <span class="fml-survey-banner-emoji" aria-hidden="true">📣</span>
            <span class="fml-survey-banner-text">Help shape Sync.Land — 2-minute feedback survey</span>
            <a class="fml-survey-banner-cta" href="<?php echo esc_url( $survey_url ); ?>">Take the survey →</a>
            <button class="fml-survey-banner-close" type="button" aria-label="Dismiss feedback survey">&times;</button>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'fml_survey_modal_output', 99);

/**
 * [fml_survey] shortcode — forces survey display on a page
 */
function fml_survey_shortcode($atts) {
    // The shortcode just sets a flag that survey.js checks to force display
    return '<div id="fml-survey-trigger" data-trigger="manual" style="display:none;"></div>';
}
add_shortcode('fml_survey', 'fml_survey_shortcode');

/**
 * [fml_survey_inline] shortcode — full inline survey form (no modal).
 * Used on /contact-us/submit-feedback/ so the page actually shows content
 * instead of just popping a modal on an otherwise-empty page.
 *
 * Submits to the same POST /wp-json/FML/v1/analytics/survey endpoint the modal
 * version uses, so responses still land in wp_*_fml_survey_responses.
 */
function fml_survey_inline_shortcode($atts) {
    $role_options = [
        'licensee' => 'I want to license music (filmmaker, podcaster, gamer, brand, etc.)',
        'artist'   => 'I make music and want to list it for licensing',
        'both'     => 'A bit of both',
    ];
    $use_case_options = [
        'youtube_content'   => 'YouTube / Video Content',
        'podcast'           => 'Podcast / Audio',
        'film_documentary'  => 'Film / Documentary',
        'commercial_ad'     => 'Commercial / Advertising',
        'social_media'      => 'Social Media Content',
        'gaming'            => 'Gaming / Streaming',
        'personal_project'  => 'Personal Project',
        'corporate'         => 'Corporate / Presentation',
        'other'             => 'Other',
    ];
    $artist_focus_options = [
        'sync_income'      => 'Earn passive income from sync placements',
        'reach_creators'   => 'Reach filmmakers / game devs / podcasters',
        'nft_certs'        => 'Mint NFT certificates for my songs',
        'longterm_catalog' => 'Get my music into a catalog long-term',
        'cc_by_share'      => 'Share work openly under Creative Commons',
        'test_platform'    => 'Test the platform before committing',
        'other'            => 'Other',
    ];
    $how_found_options = [
        'search_engine' => 'Search Engine (Google, etc.)',
        'social_media'  => 'Social Media',
        'word_of_mouth' => 'Word of Mouth',
        'music_blog'    => 'Music Blog / Review',
        'nft_community' => 'NFT / Crypto Community',
        'other'         => 'Other',
    ];
    $rest_url = esc_url_raw( rest_url( 'FML/v1/analytics/survey' ) );
    $nonce    = wp_create_nonce( 'wp_rest' );

    ob_start();
    ?>
    <form id="fml-survey-inline" class="fml-survey-inline" data-role="" novalidate
          data-endpoint="<?php echo esc_attr( $rest_url ); ?>"
          data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <p class="fml-survey-inline-lede">
            Help shape Sync.Land. Every answer goes straight to the team — your feedback
            drives the catalog, pricing, and listing experience for both sides of the marketplace.
        </p>

        <fieldset class="fml-survey-inline-q">
            <legend>First, who are you on Sync.Land?</legend>
            <div class="fml-radio-group">
                <?php foreach ( $role_options as $value => $label ) : ?>
                    <label class="fml-radio-label">
                        <input type="radio" name="role" value="<?php echo esc_attr( $value ); ?>">
                        <span><?php echo esc_html( $label ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="fml-survey-inline-q">
            <legend>How likely are you to recommend Sync.Land?</legend>
            <p class="fml-survey-subtitle">0 = Not at all likely &nbsp;&bull;&nbsp; 10 = Extremely likely</p>
            <div class="fml-nps-buttons" role="radiogroup" aria-label="NPS score">
                <?php for ( $i = 0; $i <= 10; $i++ ) : ?>
                    <button type="button" class="fml-nps-btn" data-score="<?php echo (int) $i; ?>" aria-label="<?php echo (int) $i; ?>"><?php echo (int) $i; ?></button>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="nps_score" required>
        </fieldset>

        <fieldset class="fml-survey-inline-q fml-q-licensee" hidden>
            <legend>What do you want to license music for?</legend>
            <p class="fml-survey-subtitle">Select all that apply</p>
            <div class="fml-use-case-grid">
                <?php foreach ( $use_case_options as $value => $label ) : ?>
                    <label class="fml-checkbox-label">
                        <input type="checkbox" name="use_case[]" value="<?php echo esc_attr( $value ); ?>">
                        <span><?php echo esc_html( $label ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="fml-survey-inline-q fml-q-licensee" hidden>
            <legend>How easy does the licensing process look (or feel, if you've tried it)?</legend>
            <div class="fml-stars" role="radiogroup" aria-label="Licensing ease rating">
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                    <button type="button" class="fml-star-btn" data-rating="<?php echo (int) $i; ?>" data-target="licensing_ease" aria-label="<?php echo (int) $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">&#9733;</button>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="licensing_ease">
        </fieldset>

        <fieldset class="fml-survey-inline-q fml-q-artist" hidden>
            <legend>What matters most to you about listing your music here?</legend>
            <p class="fml-survey-subtitle">Select all that apply</p>
            <div class="fml-use-case-grid">
                <?php foreach ( $artist_focus_options as $value => $label ) : ?>
                    <label class="fml-checkbox-label">
                        <input type="checkbox" name="artist_focus[]" value="<?php echo esc_attr( $value ); ?>">
                        <span><?php echo esc_html( $label ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="fml-survey-inline-q fml-q-artist" hidden>
            <legend>How easy does the upload / listing process look (or feel, if you've tried it)?</legend>
            <div class="fml-stars" role="radiogroup" aria-label="Upload ease rating">
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                    <button type="button" class="fml-star-btn" data-rating="<?php echo (int) $i; ?>" data-target="upload_ease" aria-label="<?php echo (int) $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">&#9733;</button>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="upload_ease">
        </fieldset>

        <fieldset class="fml-survey-inline-q">
            <legend>What feature would you most like to see?</legend>
            <textarea class="fml-survey-textarea" name="feature_request"
                      placeholder="Tell us what would make Sync.Land better..."
                      maxlength="5000" rows="4"></textarea>
        </fieldset>

        <fieldset class="fml-survey-inline-q">
            <legend>How did you find Sync.Land?</legend>
            <div class="fml-radio-group">
                <?php foreach ( $how_found_options as $value => $label ) : ?>
                    <label class="fml-radio-label">
                        <input type="radio" name="how_found_us" value="<?php echo esc_attr( $value ); ?>">
                        <span><?php echo esc_html( $label ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="fml-survey-inline-submit-row">
            <button type="submit" class="fml-survey-submit">Submit Feedback</button>
            <span class="fml-survey-inline-status" aria-live="polite"></span>
        </div>
    </form>

    <div id="fml-survey-inline-thanks" class="fml-survey-inline-thanks" hidden>
        <h3>Thank you!</h3>
        <p>Your feedback helps us make Sync.Land better for everyone. The team reviews every response.</p>
    </div>

    <script>
    (function () {
        var form = document.getElementById('fml-survey-inline');
        if (!form) return;

        var roleRadios = form.querySelectorAll('input[name="role"]');
        var licenseeQs = form.querySelectorAll('.fml-q-licensee');
        var artistQs   = form.querySelectorAll('.fml-q-artist');
        function showForRole(role) {
            var showLic = (role === 'licensee' || role === 'both');
            var showArt = (role === 'artist' || role === 'both');
            licenseeQs.forEach(function (el) { el.hidden = !showLic; });
            artistQs.forEach(function (el) { el.hidden = !showArt; });
            form.setAttribute('data-role', role || '');
        }
        roleRadios.forEach(function (r) {
            r.addEventListener('change', function () { showForRole(r.value); });
        });

        var npsBtns = form.querySelectorAll('.fml-nps-btn');
        var npsHidden = form.querySelector('input[name="nps_score"]');
        npsBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                npsBtns.forEach(function (x) { x.classList.remove('selected'); });
                b.classList.add('selected');
                npsHidden.value = b.getAttribute('data-score');
            });
        });

        // Star groups — one set per fieldset, each set targets its own hidden input via data-target
        form.querySelectorAll('.fml-stars').forEach(function (group) {
            var btns = group.querySelectorAll('.fml-star-btn');
            var targetName = btns[0] && btns[0].getAttribute('data-target');
            if (!targetName) return;
            var hidden = form.querySelector('input[name="' + targetName + '"]');
            btns.forEach(function (b) {
                b.addEventListener('click', function () {
                    var rating = parseInt(b.getAttribute('data-rating'), 10);
                    btns.forEach(function (x, idx) {
                        x.classList.toggle('selected', (idx + 1) <= rating);
                    });
                    if (hidden) hidden.value = String(rating);
                });
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var status = form.querySelector('.fml-survey-inline-status');
            var submitBtn = form.querySelector('.fml-survey-submit');

            var roleEl = form.querySelector('input[name="role"]:checked');
            if (!roleEl) {
                status.textContent = 'Please tell us which side of the marketplace you\'re on — it changes which questions matter.';
                form.querySelector('input[name="role"]').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (!npsHidden.value) {
                status.textContent = 'Please give us an NPS score (0–10) so we know how to weight your feedback.';
                form.querySelector('.fml-nps-buttons').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            var role = roleEl.value;
            var includeLic = (role === 'licensee' || role === 'both');
            var includeArt = (role === 'artist'   || role === 'both');

            var useCases     = includeLic ? Array.from(form.querySelectorAll('input[name="use_case[]"]:checked')).map(function (i) { return i.value; }) : [];
            var artistFocus  = includeArt ? Array.from(form.querySelectorAll('input[name="artist_focus[]"]:checked')).map(function (i) { return i.value; }) : [];
            var licEaseVal   = form.querySelector('input[name="licensing_ease"]').value;
            var upEaseVal    = form.querySelector('input[name="upload_ease"]').value;
            var howFoundEl   = form.querySelector('input[name="how_found_us"]:checked');
            var payload = {
                role: role,
                nps_score: parseInt(npsHidden.value, 10),
                use_case: useCases.join(','),
                artist_focus: artistFocus.join(','),
                licensing_ease: (includeLic && licEaseVal) ? parseInt(licEaseVal, 10) : null,
                upload_ease:    (includeArt && upEaseVal)  ? parseInt(upEaseVal, 10)  : null,
                feature_request: (form.querySelector('textarea[name="feature_request"]').value || '').trim(),
                how_found_us: howFoundEl ? howFoundEl.value : '',
                trigger_type: 'inline_page'
            };

            submitBtn.disabled = true;
            status.textContent = 'Sending…';

            fetch(form.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': form.dataset.nonce
                },
                body: JSON.stringify(payload)
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                form.hidden = true;
                document.getElementById('fml-survey-inline-thanks').hidden = false;
            }).catch(function (err) {
                submitBtn.disabled = false;
                status.textContent = 'Sorry, that didn\'t go through. Please try again or email cullah@awen.online.';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'fml_survey_inline', 'fml_survey_inline_shortcode' );
