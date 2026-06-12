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
 * Output survey modal HTML in footer (hidden by default)
 */
function fml_survey_modal_output() {
    $settings = fml_analytics_get_settings();
    if (!$settings['survey_enabled']) {
        return;
    }

    // Don't show in admin
    if (is_admin()) {
        return;
    }

    // Check if logged-in user has dismissed
    if (is_user_logged_in()) {
        $dismissed = get_user_meta(get_current_user_id(), 'fml_survey_dismissed', true);
        if ($dismissed && (time() - (int) $dismissed) < (90 * DAY_IN_SECONDS)) {
            return;
        }
    }

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

    $how_found_options = [
        'search_engine' => 'Search Engine (Google, etc.)',
        'social_media'  => 'Social Media',
        'word_of_mouth' => 'Word of Mouth',
        'music_blog'    => 'Music Blog / Review',
        'nft_community' => 'NFT / Crypto Community',
        'other'         => 'Other',
    ];
    ?>
    <div id="fml-survey-modal" class="fml-survey-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="Feedback Survey">
        <div class="fml-survey-container">
            <button class="fml-survey-close" aria-label="Close survey">&times;</button>

            <!-- Step 1: NPS -->
            <div class="fml-survey-step" data-step="1">
                <h3>How likely are you to recommend Sync.Land?</h3>
                <p class="fml-survey-subtitle">0 = Not at all likely &nbsp;&bull;&nbsp; 10 = Extremely likely</p>
                <div class="fml-nps-buttons">
                    <?php for ($i = 0; $i <= 10; $i++): ?>
                        <button class="fml-nps-btn" data-score="<?php echo $i; ?>"><?php echo $i; ?></button>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Step 2: Use Case -->
            <div class="fml-survey-step" data-step="2" style="display:none;">
                <h3>What do you use Sync.Land for?</h3>
                <p class="fml-survey-subtitle">Select all that apply</p>
                <div class="fml-use-case-grid">
                    <?php foreach ($use_case_options as $value => $label): ?>
                        <label class="fml-checkbox-label">
                            <input type="checkbox" name="use_case" value="<?php echo esc_attr($value); ?>">
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="fml-survey-next">Next</button>
            </div>

            <!-- Step 3: Licensing Ease -->
            <div class="fml-survey-step" data-step="3" style="display:none;">
                <h3>How easy is the licensing process?</h3>
                <div class="fml-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button class="fml-star-btn" data-rating="<?php echo $i; ?>" aria-label="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">&#9733;</button>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Step 4: Feature Request -->
            <div class="fml-survey-step" data-step="4" style="display:none;">
                <h3>What feature would you most like to see?</h3>
                <textarea class="fml-survey-textarea" name="feature_request" placeholder="Tell us what would make Sync.Land better..." maxlength="5000"></textarea>
                <button class="fml-survey-next">Next</button>
            </div>

            <!-- Step 5: How Found Us -->
            <div class="fml-survey-step" data-step="5" style="display:none;">
                <h3>How did you find Sync.Land?</h3>
                <div class="fml-radio-group">
                    <?php foreach ($how_found_options as $value => $label): ?>
                        <label class="fml-radio-label">
                            <input type="radio" name="how_found_us" value="<?php echo esc_attr($value); ?>">
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="fml-survey-submit">Submit Feedback</button>
            </div>

            <!-- Thank You -->
            <div class="fml-survey-step fml-survey-thanks" data-step="done" style="display:none;">
                <h3>Thank you!</h3>
                <p>Your feedback helps us make Sync.Land better for everyone.</p>
            </div>

            <div class="fml-survey-footer">
                <label class="fml-checkbox-label fml-dont-show">
                    <input type="checkbox" id="fml-survey-dont-show">
                    <span>Don't show this again</span>
                </label>
                <span class="fml-survey-step-indicator"></span>
            </div>
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
    <form id="fml-survey-inline" class="fml-survey-inline" novalidate
          data-endpoint="<?php echo esc_attr( $rest_url ); ?>"
          data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <p class="fml-survey-inline-lede">
            Help shape Sync.Land. Every answer goes straight to the team — your feedback
            drives the catalog, pricing, and licensing experience.
        </p>

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

        <fieldset class="fml-survey-inline-q">
            <legend>What do you use Sync.Land for?</legend>
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

        <fieldset class="fml-survey-inline-q">
            <legend>How easy is the licensing process?</legend>
            <div class="fml-stars" role="radiogroup" aria-label="Licensing ease rating">
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                    <button type="button" class="fml-star-btn" data-rating="<?php echo (int) $i; ?>" aria-label="<?php echo (int) $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">&#9733;</button>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="licensing_ease">
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

        var npsBtns = form.querySelectorAll('.fml-nps-btn');
        var npsHidden = form.querySelector('input[name="nps_score"]');
        npsBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                npsBtns.forEach(function (x) { x.classList.remove('selected'); });
                b.classList.add('selected');
                npsHidden.value = b.getAttribute('data-score');
            });
        });

        var starBtns = form.querySelectorAll('.fml-star-btn');
        var starHidden = form.querySelector('input[name="licensing_ease"]');
        starBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                var rating = parseInt(b.getAttribute('data-rating'), 10);
                starBtns.forEach(function (x, idx) {
                    x.classList.toggle('selected', (idx + 1) <= rating);
                });
                starHidden.value = String(rating);
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var status = form.querySelector('.fml-survey-inline-status');
            var submitBtn = form.querySelector('.fml-survey-submit');

            if (!npsHidden.value) {
                status.textContent = 'Please give us an NPS score (0–10) so we know how to weight your feedback.';
                form.querySelector('.fml-nps-buttons').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            var useCases = Array.from(form.querySelectorAll('input[name="use_case[]"]:checked')).map(function (i) { return i.value; });
            var howFoundEl = form.querySelector('input[name="how_found_us"]:checked');
            var payload = {
                nps_score: parseInt(npsHidden.value, 10),
                use_case: useCases.join(','),
                licensing_ease: starHidden.value ? parseInt(starHidden.value, 10) : null,
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
