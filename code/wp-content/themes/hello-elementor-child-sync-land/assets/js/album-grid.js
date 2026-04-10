/**
 * Album Grid JavaScript
 * Handles play album, sorting, and view switching
 */

(function($) {
    'use strict';

    /**
     * Play entire album - queues all songs and starts playback
     */
    $(document).on('click', '.fml-album-play-btn, .fml-list-play-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $card = $(this).closest('.fml-album-card');
        const $songData = $card.find('.fml-album-songs-data .song-play');

        if ($songData.length === 0) {
            console.warn('No songs found for this album');
            return;
        }

        // Build song array
        const songs = [];
        $songData.each(function() {
            const $el = $(this);
            const songObj = {
                name: $el.data('songname'),
                artist: $el.data('artistname'),
                album: $el.data('albumname'),
                url: $el.data('audiosrc'),
                cover_art_url: $el.data('artsrc'),
                song_id: $el.data('songid'),
                permalink: $el.data('permalink'),
                artist_permalink: $el.data('artistpermalink'),
                album_permalink: $el.data('albumpermalink')
            };

            // Only add if has valid URL
            if (songObj.url) {
                songs.push(songObj);
            }
        });

        if (songs.length === 0) {
            console.warn('No valid songs to play');
            return;
        }

        // Initialize Amplitude with all album songs and autoplay
        if (typeof Amplitude !== 'undefined') {
            try {
                // Stop current playback first
                Amplitude.stop();

                // Small delay to ensure clean state
                setTimeout(function() {
                    Amplitude.init({
                        songs: songs,
                        autoplay: true,
                        callbacks: {
                            song_change: function() {
                                if (typeof window.updatePlayerMeta === 'function') {
                                    window.updatePlayerMeta();
                                }
                                if (typeof window.updateLicenseButton === 'function') {
                                    window.updateLicenseButton();
                                }
                            }
                        }
                    });

                    // Force play after init
                    setTimeout(function() {
                        Amplitude.play();
                    }, 100);

                    // Save to localStorage
                    localStorage.setItem('fml_queue', JSON.stringify(songs));

                    // Reinitialize audio analyser if available
                    if (typeof window.FMLReinitAudioAnalyser === 'function') {
                        setTimeout(window.FMLReinitAudioAnalyser, 200);
                    }

                }, 50);

                // Visual feedback
                const $btn = $(e.currentTarget);
                $btn.find('i').removeClass('fa-play').addClass('fa-volume-up');
                setTimeout(() => {
                    $btn.find('i').removeClass('fa-volume-up').addClass('fa-play');
                }, 2000);

            } catch (err) {
                console.error('Error initializing Amplitude:', err);
            }
        } else {
            console.warn('Amplitude not loaded');
        }
    });

    /**
     * Sorting functionality
     */
    $(document).on('change', '.fml-sort-select', function() {
        const sortValue = $(this).val();
        const $grid = $(this).closest('.fml-album-controls').next('.fml-album-grid');
        const $cards = $grid.find('.fml-album-card');

        // Sort cards
        const sorted = $cards.sort(function(a, b) {
            const $a = $(a);
            const $b = $(b);

            switch (sortValue) {
                case 'date-desc':
                    return ($b.data('date') || '').localeCompare($a.data('date') || '');
                case 'date-asc':
                    return ($a.data('date') || '').localeCompare($b.data('date') || '');
                case 'title-asc':
                    return ($a.data('title') || '').localeCompare($b.data('title') || '');
                case 'title-desc':
                    return ($b.data('title') || '').localeCompare($a.data('title') || '');
                case 'songs-desc':
                    return (parseInt($b.data('songs')) || 0) - (parseInt($a.data('songs')) || 0);
                case 'duration-desc':
                    return (parseInt($b.data('duration')) || 0) - (parseInt($a.data('duration')) || 0);
                default:
                    return 0;
            }
        });

        // Re-append in new order
        $grid.append(sorted);
    });

    /**
     * View toggle (grid/list)
     */
    $(document).on('click', '.fml-view-btn', function() {
        const $btn = $(this);
        const view = $btn.data('view');
        const $controls = $btn.closest('.fml-album-controls');
        const $grid = $controls.next('.fml-album-grid');
        const columns = $grid.data('columns') || 3;

        // Update button states
        $controls.find('.fml-view-btn').removeClass('active');
        $btn.addClass('active');

        // Update grid classes
        if (view === 'list') {
            $grid.removeClass('view-grid columns-2 columns-3 columns-4').addClass('view-list');

            // Add list play buttons if not present
            $grid.find('.fml-album-card').each(function() {
                const $card = $(this);
                if ($card.find('.fml-list-play-btn').length === 0) {
                    $card.append('<button class="fml-list-play-btn" title="Play Album"><i class="fas fa-play"></i></button>');
                }
            });
        } else {
            $grid.removeClass('view-list').addClass('view-grid columns-' + columns);

            // Remove list play buttons
            $grid.find('.fml-list-play-btn').remove();
        }

        // Save preference
        localStorage.setItem('fml_album_view', view);
    });

    /**
     * Click on album cover goes to album page
     */
    $(document).on('click', '.fml-album-cover', function(e) {
        // Only if not clicking buttons
        if ($(e.target).closest('button').length === 0) {
            const $card = $(this).closest('.fml-album-card');
            const albumLink = $card.find('.fml-album-title a').attr('href');
            if (albumLink) {
                window.location.href = albumLink;
            }
        }
    });

    /**
     * Restore view preference on page load
     */
    $(document).ready(function() {
        const savedView = localStorage.getItem('fml_album_view');
        if (savedView) {
            $('.fml-view-btn[data-view="' + savedView + '"]').click();
        }
    });

    /**
     * Keyboard accessibility
     */
    $(document).on('keydown', '.fml-album-play-btn, .fml-list-play-btn', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).click();
        }
    });

})(jQuery);
