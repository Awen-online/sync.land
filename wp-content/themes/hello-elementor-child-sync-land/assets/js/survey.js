/**
 * Sync.Land Survey Modal
 *
 * Evaluates trigger conditions and manages the multi-step survey flow.
 * Config provided via wp_localize_script -> window.FMLSurveyConfig
 */
(function() {
    'use strict';

    var config = window.FMLSurveyConfig || {};
    var modal = null;
    var currentStep = 1;
    var surveyData = {};

    // =========================================================================
    // Trigger evaluation
    // =========================================================================

    function isDismissed() {
        var dismissed = localStorage.getItem('fml_survey_dismissed');
        if (!dismissed) return false;
        var dismissedTime = parseInt(dismissed, 10);
        var ninetyDays = 90 * 24 * 60 * 60 * 1000;
        return (Date.now() - dismissedTime) < ninetyDays;
    }

    function markDismissed() {
        localStorage.setItem('fml_survey_dismissed', Date.now().toString());
    }

    function checkVisitCount() {
        var threshold = config.visit_count || 3;
        var count = parseInt(localStorage.getItem('fml_visit_count') || '0', 10) + 1;
        localStorage.setItem('fml_visit_count', count.toString());
        return count >= threshold;
    }

    function checkTimeOnSite() {
        var threshold = (config.time_on_site || 300) * 1000;
        setTimeout(function() {
            if (!isDismissed()) {
                showSurvey('time_on_site');
            }
        }, threshold);
    }

    function checkManualTrigger() {
        var trigger = document.getElementById('fml-survey-trigger');
        return trigger && trigger.getAttribute('data-trigger') === 'manual';
    }

    function checkPostLicensing() {
        // Check if we're on the checkout success page
        return config.post_licensing && window.location.search.indexOf('checkout=success') !== -1;
    }

    function evaluateTriggers() {
        if (isDismissed()) return;

        // Manual trigger always wins
        if (checkManualTrigger()) {
            showSurvey('manual');
            return;
        }

        // Post-licensing
        if (checkPostLicensing()) {
            showSurvey('post_licensing');
            return;
        }

        // Visit count
        if (checkVisitCount()) {
            showSurvey('visit_count');
            return;
        }

        // Time on site (sets a delayed trigger)
        checkTimeOnSite();
    }

    // =========================================================================
    // Modal management
    // =========================================================================

    function showSurvey(triggerType) {
        modal = document.getElementById('fml-survey-modal');
        if (!modal) return;

        surveyData.trigger_type = triggerType;
        surveyData.page_url = window.location.href;

        modal.style.display = 'flex';
        updateStepIndicator();

        // Track analytics event
        if (window.FMLAnalytics) {
            window.FMLAnalytics.track('survey_shown', { trigger: triggerType });
        }
    }

    function hideSurvey() {
        if (modal) {
            modal.style.display = 'none';
        }

        // Always mark dismissed on close so it doesn't re-appear every visit
        markDismissed();
    }

    function goToStep(step) {
        if (!modal) return;
        var steps = modal.querySelectorAll('.fml-survey-step');
        steps.forEach(function(s) { s.style.display = 'none'; });

        var target = modal.querySelector('[data-step="' + step + '"]');
        if (target) {
            target.style.display = 'block';
            currentStep = step;
            updateStepIndicator();
        }
    }

    function updateStepIndicator() {
        if (!modal) return;
        var indicator = modal.querySelector('.fml-survey-step-indicator');
        if (indicator && typeof currentStep === 'number') {
            indicator.textContent = 'Step ' + currentStep + ' of 5';
        } else if (indicator) {
            indicator.textContent = '';
        }
    }

    // =========================================================================
    // Submit
    // =========================================================================

    function submitSurvey() {
        var payload = JSON.stringify(surveyData);

        fetch(config.api_url + '/analytics/survey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce || ''
            },
            body: payload
        }).catch(function() {});

        // Show thank you
        goToStep('done');
        markDismissed();

        // Auto-close after 2s
        setTimeout(hideSurvey, 2000);
    }

    // =========================================================================
    // Event handlers
    // =========================================================================

    function init() {
        modal = document.getElementById('fml-survey-modal');
        if (!modal) return;

        // Close button
        var closeBtn = modal.querySelector('.fml-survey-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', hideSurvey);
        }

        // Overlay click to close
        modal.addEventListener('click', function(e) {
            if (e.target === modal) hideSurvey();
        });

        // Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                hideSurvey();
            }
        });

        // NPS buttons
        var npsButtons = modal.querySelectorAll('.fml-nps-btn');
        npsButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Remove active from siblings
                npsButtons.forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                surveyData.nps_score = parseInt(this.getAttribute('data-score'), 10);
                // Auto-advance after short delay
                setTimeout(function() { goToStep(2); }, 300);
            });
        });

        // Star rating buttons
        var starButtons = modal.querySelectorAll('.fml-star-btn');
        starButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var rating = parseInt(this.getAttribute('data-rating'), 10);
                surveyData.licensing_ease = rating;
                // Highlight stars up to selected
                starButtons.forEach(function(s) {
                    var r = parseInt(s.getAttribute('data-rating'), 10);
                    s.classList.toggle('active', r <= rating);
                });
                // Auto-advance
                setTimeout(function() { goToStep(4); }, 300);
            });
        });

        // Next buttons
        var nextButtons = modal.querySelectorAll('.fml-survey-next');
        nextButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var step = this.closest('.fml-survey-step');
                var stepNum = parseInt(step.getAttribute('data-step'), 10);

                // Collect data for current step
                if (stepNum === 2) {
                    var checked = step.querySelectorAll('input[name="use_case"]:checked');
                    surveyData.use_case = [];
                    checked.forEach(function(cb) { surveyData.use_case.push(cb.value); });
                }
                if (stepNum === 4) {
                    var textarea = step.querySelector('textarea[name="feature_request"]');
                    if (textarea) surveyData.feature_request = textarea.value;
                }

                goToStep(stepNum + 1);
            });
        });

        // Submit button
        var submitBtn = modal.querySelector('.fml-survey-submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                // Collect final step data
                var step = this.closest('.fml-survey-step');
                var selected = step.querySelector('input[name="how_found_us"]:checked');
                if (selected) surveyData.how_found_us = selected.value;
                submitSurvey();
            });
        }

        // Evaluate triggers
        evaluateTriggers();
    }

    // DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-evaluate on PJAX navigation
    document.addEventListener('pjax:load', function() {
        setTimeout(function() {
            if (checkManualTrigger() && !isDismissed()) {
                showSurvey('manual');
            }
            if (checkPostLicensing() && !isDismissed()) {
                showSurvey('post_licensing');
            }
        }, 500);
    });

})();
