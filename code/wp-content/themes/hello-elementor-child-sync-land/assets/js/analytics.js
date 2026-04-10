/**
 * Sync.Land Analytics Tracker
 *
 * Tracks user behavior events and bridges to GA4.
 * Config provided via wp_localize_script -> window.FMLAnalyticsConfig
 */
(function() {
    'use strict';

    var config = window.FMLAnalyticsConfig || {};
    if (!config.api_url) return;

    var queue = [];
    var FLUSH_INTERVAL = 30000; // 30 seconds
    var pageLoadTime = Date.now();
    var currentPageUrl = window.location.href;
    var currentReferrer = document.referrer;

    // =========================================================================
    // Core API
    // =========================================================================

    function track(eventType, eventData) {
        eventData = eventData || {};
        queue.push({
            type: eventType,
            data: eventData,
            page_url: currentPageUrl,
            referrer: currentReferrer,
            ts: Date.now()
        });

        // GA4 bridge
        if (typeof gtag === 'function') {
            gtag('event', eventType, eventData);
        }
    }

    function flush() {
        if (queue.length === 0) return;

        var payload = JSON.stringify({
            events: queue.slice(),
            session_id: config.session_id || '',
            user_id: config.user_id || 0
        });
        queue = [];

        // Prefer sendBeacon for reliability (page unload)
        if (navigator.sendBeacon) {
            var blob = new Blob([payload], { type: 'application/json' });
            var sent = navigator.sendBeacon(config.api_url + '/analytics/events', blob);
            if (sent) return;
        }

        // Fallback to fetch
        fetch(config.api_url + '/analytics/events', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce || ''
            },
            body: payload,
            keepalive: true
        }).catch(function() {});
    }

    // =========================================================================
    // Auto-tracked events
    // =========================================================================

    // Page view
    function trackPageView() {
        currentPageUrl = window.location.href;
        currentReferrer = document.referrer;
        pageLoadTime = Date.now();
        track('page_view', {
            url: currentPageUrl,
            referrer: currentReferrer
        });
    }

    // Time on page (on unload)
    function trackTimeOnPage() {
        var seconds = Math.round((Date.now() - pageLoadTime) / 1000);
        if (seconds > 1) {
            track('time_on_page', {
                seconds: seconds,
                url: currentPageUrl
            });
        }
    }

    // Search query (debounced)
    function debounce(fn, delay) {
        var timer;
        return function() {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function() { fn.apply(ctx, args); }, delay);
        };
    }

    var trackSearch = debounce(function(query) {
        if (query && query.length >= 2) {
            track('search_query', { query_text: query });
        }
    }, 1000);

    // =========================================================================
    // Music player hooks
    // =========================================================================

    function attachMusicPlayerListeners() {
        // Amplitude.js audio element
        try {
            if (typeof Amplitude !== 'undefined' && Amplitude.getAudio) {
                var audio = Amplitude.getAudio();
                if (audio && !audio._fmlTracked) {
                    audio._fmlTracked = true;

                    audio.addEventListener('play', function() {
                        var meta = {};
                        try { meta = Amplitude.getActiveSongMetadata(); } catch(e) {}
                        track('song_play', {
                            song_id: meta.song_id || meta.id || '',
                            song_name: meta.name || meta.song_name || '',
                            artist: meta.artist || ''
                        });
                    });

                    audio.addEventListener('pause', function() {
                        var meta = {};
                        try { meta = Amplitude.getActiveSongMetadata(); } catch(e) {}
                        track('song_pause', {
                            song_id: meta.song_id || meta.id || '',
                            seconds_played: Math.round(audio.currentTime || 0)
                        });
                    });

                    audio.addEventListener('ended', function() {
                        var meta = {};
                        try { meta = Amplitude.getActiveSongMetadata(); } catch(e) {}
                        track('song_complete', {
                            song_id: meta.song_id || meta.id || '',
                            song_name: meta.name || meta.song_name || ''
                        });
                    });
                }
            }
        } catch(e) {}
    }

    // =========================================================================
    // Click-based event delegation
    // =========================================================================

    function handleClicks(e) {
        var target = e.target;

        // Walk up to 5 levels for matching
        for (var i = 0; i < 5 && target && target !== document; i++, target = target.parentElement) {
            // Queue add
            if (target.matches && target.matches('.song-add-queue')) {
                var songId = target.getAttribute('data-song-id') || '';
                track('song_queue_add', { song_id: songId });
                return;
            }

            // License modal open
            if (target.id === 'license-button' || (target.matches && target.matches('#license-button'))) {
                track('license_modal_open', {
                    song_id: target.getAttribute('data-song-id') || ''
                });
                return;
            }

            // Add to cart
            if (target.matches && target.matches('.fml-add-to-cart-btn')) {
                track('add_to_cart', {
                    song_id: target.getAttribute('data-song-id') || '',
                    license_type: target.getAttribute('data-license-type') || ''
                });
                return;
            }

            // Checkout start
            if (target.id === 'fml-checkout-btn' || (target.matches && target.matches('#fml-checkout-btn'))) {
                track('checkout_start', {
                    item_count: target.getAttribute('data-item-count') || ''
                });
                return;
            }

            // Planet play
            if (target.matches && target.matches('.hero-planet-play-btn')) {
                track('planet_play', {
                    song_id: target.getAttribute('data-song-id') || ''
                });
                return;
            }

            // Planet refresh
            if (target.matches && target.matches('.hero-planet-refresh-btn')) {
                track('planet_refresh', {});
                return;
            }

            // Visualizer toggle
            if (target.id === 'toggle-visualizer' || (target.matches && target.matches('#toggle-visualizer'))) {
                track('visualizer_toggle', {
                    state: target.classList.contains('active') ? 'off' : 'on'
                });
                return;
            }
        }
    }

    // =========================================================================
    // Search input tracking
    // =========================================================================

    function attachSearchListener() {
        var searchInputs = document.querySelectorAll('#fml-search-input, .fml-search-input, input[name="fml_search"]');
        searchInputs.forEach(function(input) {
            if (input._fmlTracked) return;
            input._fmlTracked = true;
            input.addEventListener('input', function() {
                trackSearch(this.value);
            });
        });
    }

    // =========================================================================
    // Initialization
    // =========================================================================

    function init() {
        trackPageView();
        attachMusicPlayerListeners();
        attachSearchListener();
        document.addEventListener('click', handleClicks, true);
    }

    // DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Flush on interval
    setInterval(flush, FLUSH_INTERVAL);

    // Flush on page hide / unload
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            trackTimeOnPage();
            flush();
        }
    });

    window.addEventListener('beforeunload', function() {
        trackTimeOnPage();
        flush();
    });

    // PJAX support: re-track on navigation
    document.addEventListener('pjax:load', function() {
        // Flush previous page events
        trackTimeOnPage();
        flush();

        // Re-init for new page
        currentReferrer = currentPageUrl;
        currentPageUrl = window.location.href;
        pageLoadTime = Date.now();

        trackPageView();

        // Re-attach after Amplitude reinitializes
        setTimeout(attachMusicPlayerListeners, 500);
        setTimeout(attachMusicPlayerListeners, 2000);
        attachSearchListener();
    });

    // Expose for manual tracking from other scripts
    window.FMLAnalytics = {
        track: track,
        flush: flush
    };

})();
