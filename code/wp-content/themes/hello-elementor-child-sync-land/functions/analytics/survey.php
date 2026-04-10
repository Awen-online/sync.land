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
