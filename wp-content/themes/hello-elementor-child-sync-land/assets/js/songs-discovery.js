/**
 * Songs Discovery Page
 * Client-side fetching, rendering, filtering, waveform drawing, and player integration
 */
(function() {
    'use strict';

    var config = null;
    var state = {};

    // DOM references
    var page, container, loading, pagination, resultsCount;
    var searchInput, sortSelect, playAllBtn;
    var activeFiltersEl;
    var debounceTimer = null;

    function resetState() {
        state = {
            page: 1,
            perPage: (config && config.perPage) || 20,
            query: '',
            genres: [],
            moods: [],
            bpmMin: '',
            bpmMax: '',
            orderby: 'date',
            songs: [],
            total: 0
        };
    }

    function init() {
        page = document.getElementById('songs-discovery-page');
        if (!page) return;
        if (page.dataset.initialized === 'true') return;

        // Re-read config each time — PJAX may have injected a fresh inline script
        config = window.songsDiscoveryData;
        if (!config) {
            // Config not available — build a minimal config from the REST API
            config = {
                restUrl: '/wp-json/FML/v1/songs-discover',
                nonce: '',
                perPage: 20,
                genres: [],
                moods: []
            };
            // Fetch genre/mood terms for filters
            fetch('/wp-json/FML/v1/songs-discover?per_page=1')
                .then(function() {
                    // If the endpoint works, we're good — filters just won't have terms
                    // until a full page load populates songsDiscoveryData
                })
                .catch(function() {});
        }

        page.dataset.initialized = 'true';
        resetState();

        container = document.getElementById('songs-discovery-container');
        loading = document.getElementById('songs-loading');
        pagination = document.getElementById('songs-discovery-pagination');
        resultsCount = document.getElementById('songs-results-count');
        searchInput = document.getElementById('songs-search-input');
        sortSelect = document.getElementById('songs-sort-select');
        playAllBtn = document.getElementById('songs-play-all');
        activeFiltersEl = document.getElementById('songs-active-filters');

        if (!container || !loading) return;

        buildFilterDropdowns();
        bindEvents();
        fetchSongs();
    }

    // ================================================================
    // FILTER DROPDOWNS
    // ================================================================

    function buildFilterDropdowns() {
        var genreDropdown = page.querySelector('[data-filter="genre"] .filter-dropdown-list');
        var moodDropdown = page.querySelector('[data-filter="mood"] .filter-dropdown-list');

        if (genreDropdown && config.genres.length) {
            genreDropdown.innerHTML = config.genres.map(function(g) {
                return '<label class="filter-dropdown-item" data-name="' + escapeAttr(g.name.toLowerCase()) + '">' +
                    '<input type="checkbox" value="' + g.id + '">' +
                    '<span>' + escapeHtml(g.name) + '</span>' +
                    '<span class="term-count">' + g.count + '</span>' +
                '</label>';
            }).join('');
        }

        if (moodDropdown && config.moods.length) {
            moodDropdown.innerHTML = config.moods.map(function(m) {
                return '<label class="filter-dropdown-item" data-name="' + escapeAttr(m.name.toLowerCase()) + '">' +
                    '<input type="checkbox" value="' + m.id + '">' +
                    '<span>' + escapeHtml(m.name) + '</span>' +
                    '<span class="term-count">' + m.count + '</span>' +
                '</label>';
            }).join('');
        }
    }

    // ================================================================
    // EVENT BINDING
    // ================================================================

    function bindEvents() {
        // Search
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                var val = searchInput.value.trim();
                if (val.length >= 2 || val.length === 0) {
                    state.query = val;
                    state.page = 1;
                    fetchSongs();
                }
            }, 300);
        });

        // Sort
        sortSelect.addEventListener('change', function() {
            state.orderby = sortSelect.value;
            state.page = 1;
            fetchSongs();
        });

        // Play All
        if (playAllBtn) {
            playAllBtn.addEventListener('click', playAll);
        }

        // BPM inputs
        var bpmMin = document.getElementById('bpm-min');
        var bpmMax = document.getElementById('bpm-max');
        if (bpmMin) {
            bpmMin.addEventListener('change', function() {
                state.bpmMin = bpmMin.value;
                state.page = 1;
                fetchSongs();
                renderActiveFilters();
            });
        }
        if (bpmMax) {
            bpmMax.addEventListener('change', function() {
                state.bpmMax = bpmMax.value;
                state.page = 1;
                fetchSongs();
                renderActiveFilters();
            });
        }

        // Multiselect dropdowns
        page.querySelectorAll('.songs-filter-multiselect').forEach(function(ms) {
            var filterType = ms.dataset.filter;
            var btn = ms.querySelector('.songs-filter-btn');
            var dropdown = ms.querySelector('.songs-filter-dropdown');
            var searchEl = ms.querySelector('.filter-dropdown-search');
            var applyBtn = ms.querySelector('.filter-apply-btn');
            var clearBtn = ms.querySelector('.filter-clear-btn');

            // Toggle open
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = ms.classList.contains('open');
                closeAllDropdowns();
                if (!isOpen) {
                    ms.classList.add('open');
                    if (searchEl) searchEl.focus();
                }
            });

            // Prevent dropdown clicks from closing
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Search within dropdown
            if (searchEl) {
                searchEl.addEventListener('input', function() {
                    var q = searchEl.value.toLowerCase();
                    ms.querySelectorAll('.filter-dropdown-item').forEach(function(item) {
                        var name = item.dataset.name || '';
                        item.classList.toggle('hidden', q && name.indexOf(q) === -1);
                    });
                });
            }

            // Apply
            applyBtn.addEventListener('click', function() {
                var selected = [];
                ms.querySelectorAll('.filter-dropdown-item input:checked').forEach(function(cb) {
                    selected.push(parseInt(cb.value));
                });

                if (filterType === 'genre') {
                    state.genres = selected;
                } else {
                    state.moods = selected;
                }

                updateFilterButton(ms, selected.length);
                ms.classList.remove('open');
                state.page = 1;
                fetchSongs();
                renderActiveFilters();
            });

            // Clear
            clearBtn.addEventListener('click', function() {
                ms.querySelectorAll('.filter-dropdown-item input').forEach(function(cb) {
                    cb.checked = false;
                });
                if (filterType === 'genre') {
                    state.genres = [];
                } else {
                    state.moods = [];
                }
                updateFilterButton(ms, 0);
                ms.classList.remove('open');
                state.page = 1;
                fetchSongs();
                renderActiveFilters();
            });
        });

        // Close dropdowns on outside click (use named function to prevent stacking)
        document.removeEventListener('click', closeAllDropdowns);
        document.addEventListener('click', closeAllDropdowns);

        // Song row play click (event delegation)
        container.addEventListener('click', function(e) {
            var playBtn = e.target.closest('.sd-play-btn');
            if (playBtn) {
                e.preventDefault();
                var idx = parseInt(playBtn.dataset.index);
                if (state.songs[idx]) {
                    playSong(state.songs[idx]);
                }
            }
        });
    }

    function closeAllDropdowns() {
        if (!page) return;
        page.querySelectorAll('.songs-filter-multiselect.open').forEach(function(ms) {
            ms.classList.remove('open');
        });
    }

    function updateFilterButton(ms, count) {
        var btn = ms.querySelector('.songs-filter-btn');
        var countEl = btn.querySelector('.filter-count');

        if (count > 0) {
            btn.classList.add('has-selection');
            countEl.textContent = count;
            countEl.style.display = '';
        } else {
            btn.classList.remove('has-selection');
            countEl.style.display = 'none';
        }
    }

    // ================================================================
    // ACTIVE FILTERS
    // ================================================================

    function renderActiveFilters() {
        var tags = [];

        // Genre tags
        state.genres.forEach(function(id) {
            var term = findTerm(config.genres, id);
            if (term) {
                tags.push('<span class="active-filter-tag genre-tag" data-type="genre" data-id="' + id + '">' +
                    escapeHtml(term.name) +
                    ' <i class="fas fa-times remove-filter"></i></span>');
            }
        });

        // Mood tags
        state.moods.forEach(function(id) {
            var term = findTerm(config.moods, id);
            if (term) {
                tags.push('<span class="active-filter-tag mood-tag" data-type="mood" data-id="' + id + '">' +
                    escapeHtml(term.name) +
                    ' <i class="fas fa-times remove-filter"></i></span>');
            }
        });

        // BPM tag
        if (state.bpmMin || state.bpmMax) {
            var bpmText = 'BPM: ' + (state.bpmMin || '0') + ' – ' + (state.bpmMax || '∞');
            tags.push('<span class="active-filter-tag bpm-tag" data-type="bpm">' +
                escapeHtml(bpmText) +
                ' <i class="fas fa-times remove-filter"></i></span>');
        }

        if (tags.length > 0) {
            activeFiltersEl.innerHTML = tags.join('');
            activeFiltersEl.style.display = '';

            // Bind remove clicks
            activeFiltersEl.querySelectorAll('.active-filter-tag').forEach(function(tag) {
                tag.addEventListener('click', function() {
                    var type = tag.dataset.type;
                    var id = parseInt(tag.dataset.id);

                    if (type === 'genre') {
                        state.genres = state.genres.filter(function(g) { return g !== id; });
                        syncCheckboxes('genre', state.genres);
                        updateFilterButton(page.querySelector('[data-filter="genre"]'), state.genres.length);
                    } else if (type === 'mood') {
                        state.moods = state.moods.filter(function(m) { return m !== id; });
                        syncCheckboxes('mood', state.moods);
                        updateFilterButton(page.querySelector('[data-filter="mood"]'), state.moods.length);
                    } else if (type === 'bpm') {
                        state.bpmMin = '';
                        state.bpmMax = '';
                        document.getElementById('bpm-min').value = '';
                        document.getElementById('bpm-max').value = '';
                    }

                    state.page = 1;
                    fetchSongs();
                    renderActiveFilters();
                });
            });
        } else {
            activeFiltersEl.style.display = 'none';
            activeFiltersEl.innerHTML = '';
        }
    }

    function syncCheckboxes(filterType, selectedIds) {
        var ms = page.querySelector('[data-filter="' + filterType + '"]');
        if (!ms) return;
        ms.querySelectorAll('.filter-dropdown-item input').forEach(function(cb) {
            cb.checked = selectedIds.indexOf(parseInt(cb.value)) !== -1;
        });
    }

    function findTerm(list, id) {
        for (var i = 0; i < list.length; i++) {
            if (list[i].id === id) return list[i];
        }
        return null;
    }

    // ================================================================
    // DATA FETCHING
    // ================================================================

    function fetchSongs() {
        loading.style.display = 'flex';
        container.style.opacity = '0.5';

        var params = new URLSearchParams({
            page: state.page,
            per_page: state.perPage,
            orderby: state.orderby
        });

        if (state.query) params.set('q', state.query);
        if (state.genres.length) params.set('genre', state.genres.join(','));
        if (state.moods.length) params.set('mood', state.moods.join(','));
        if (state.bpmMin) params.set('bpm_min', state.bpmMin);
        if (state.bpmMax) params.set('bpm_max', state.bpmMax);

        var url = (config.restUrl || '/wp-json/FML/v1/songs-discover') + '?' + params.toString();
        var headers = {};
        if (config.nonce) {
            headers['X-WP-Nonce'] = config.nonce;
        }

        fetch(url, { headers: headers })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                throw new Error(data.message || 'API error');
            }
            state.songs = data.songs || [];
            state.total = data.total || 0;
            renderSongs(state.songs);
            renderPagination(data.total, data.pages, data.page);
            updateResultsCount(state.songs.length, data.total, data.page, state.perPage);
        })
        .catch(function(err) {
            console.error('Songs Discovery: Error loading songs:', err);
            container.innerHTML = '<p class="songs-discovery-error">Error loading songs. Please try again.</p>';
            resultsCount.textContent = 'Error loading songs';
        })
        .finally(function() {
            loading.style.display = 'none';
            container.style.opacity = '1';
        });
    }

    function updateResultsCount(shown, total, currentPage, perPage) {
        var start = (currentPage - 1) * perPage + 1;
        var end = start + shown - 1;
        if (total === 0) {
            resultsCount.textContent = 'No songs found';
        } else {
            resultsCount.textContent = 'Showing ' + start + '–' + end + ' of ' + total.toLocaleString() + ' songs';
        }
    }

    // ================================================================
    // RENDERING
    // ================================================================

    function renderSongs(songs) {
        if (!songs || songs.length === 0) {
            container.innerHTML = '<p class="songs-discovery-no-results"><i class="fas fa-search"></i> No songs found. Try adjusting your filters.</p>';
            return;
        }

        var html = songs.map(function(song, idx) {
            var genreTags = (song.genres || []).map(function(g) {
                return '<a href="/genre/' + escapeAttr(g.slug) + '/" class="sd-tag genre-tag">' + escapeHtml(g.name) + '</a>';
            }).join('');
            var moodTags = (song.moods || []).map(function(m) {
                return '<a href="/mood/' + escapeAttr(m.slug) + '/" class="sd-tag mood-tag">' + escapeHtml(m.name) + '</a>';
            }).join('');

            var durationStr = formatDuration(song.duration);
            var bpmStr = song.bpm ? song.bpm : '—';
            var keyStr = song.key ? song.key : '—';

            var hasWaveform = song.waveform_peaks && song.waveform_peaks.length > 0;

            return '<div class="sd-song-row" data-song-id="' + song.id + '">' +
                '<button type="button" class="sd-play-btn" data-index="' + idx + '">' +
                    '<div class="sd-cover" style="background-image: url(\'' + escapeAttr(song.cover_art_url) + '\')">' +
                        '<i class="fas fa-play"></i>' +
                    '</div>' +
                '</button>' +
                (hasWaveform ? '<div class="sd-waveform-wrap"><canvas class="sd-waveform-canvas" data-index="' + idx + '" width="440" height="80"></canvas></div>' : '') +
                '<div class="sd-song-info">' +
                    '<a href="' + escapeAttr(song.permalink) + '" class="sd-song-title">' + escapeHtml(song.name) + '</a>' +
                    '<div class="sd-song-meta">' +
                        '<a href="' + escapeAttr(song.artist_permalink) + '" class="sd-artist">' + escapeHtml(song.artist) + '</a>' +
                        (song.album ? '<span class="sd-meta-sep">·</span><a href="' + escapeAttr(song.album_permalink) + '" class="sd-album">' + escapeHtml(song.album) + '</a>' : '') +
                    '</div>' +
                    '<div class="sd-tags">' + genreTags + moodTags + '</div>' +
                '</div>' +
                '<div class="sd-details">' +
                    '<div class="sd-detail"><span class="sd-detail-value">' + escapeHtml(bpmStr) + '</span><span class="sd-detail-label">BPM</span></div>' +
                    '<div class="sd-detail"><span class="sd-detail-value">' + escapeHtml(keyStr) + '</span><span class="sd-detail-label">Key</span></div>' +
                    '<div class="sd-detail"><span class="sd-detail-value">' + escapeHtml(durationStr) + '</span><span class="sd-detail-label">Time</span></div>' +
                '</div>' +
                '<div class="sd-actions">' +
                    '<a href="' + escapeAttr(song.permalink) + '" class="sd-license-btn" title="License & Download">' +
                        '<i class="fas fa-download"></i>' +
                    '</a>' +
                '</div>' +
            '</div>';
        }).join('');

        container.innerHTML = html;

        // Draw waveforms after DOM insert
        requestAnimationFrame(function() {
            songs.forEach(function(song, idx) {
                if (song.waveform_peaks && song.waveform_peaks.length > 0) {
                    var canvas = container.querySelector('.sd-waveform-canvas[data-index="' + idx + '"]');
                    if (canvas) {
                        renderWaveform(canvas, song.waveform_peaks);
                    }
                }
            });
        });
    }

    // ================================================================
    // WAVEFORM
    // ================================================================

    function renderWaveform(canvas, peaks) {
        var ctx = canvas.getContext('2d');
        var dpr = window.devicePixelRatio || 1;

        // Set canvas resolution for sharp rendering
        var displayWidth = canvas.clientWidth;
        var displayHeight = canvas.clientHeight;
        canvas.width = displayWidth * dpr;
        canvas.height = displayHeight * dpr;
        ctx.scale(dpr, dpr);

        var width = displayWidth;
        var height = displayHeight;
        var numBars = peaks.length;
        var barWidth = Math.max(1, (width / numBars) - 1);
        var gap = 1;

        ctx.clearRect(0, 0, width, height);

        for (var i = 0; i < numBars; i++) {
            var peak = parseFloat(peaks[i]) || 0;
            var barHeight = Math.max(2, peak * height * 0.85);
            var x = i * (barWidth + gap);
            var y = (height - barHeight) / 2;

            // Gradient: hot pink to blue
            var ratio = i / numBars;
            var r = Math.round(226 - ratio * (226 - 47));
            var g = Math.round(55 + ratio * (110 - 55));
            var b = Math.round(178 + ratio * (211 - 178));

            ctx.fillStyle = 'rgba(' + r + ',' + g + ',' + b + ', 0.7)';
            ctx.fillRect(x, y, barWidth, barHeight);
        }
    }

    // ================================================================
    // PAGINATION
    // ================================================================

    function renderPagination(total, pages, current) {
        if (pages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        var html = '<div class="pagination-inner">';

        if (current > 1) {
            html += '<button type="button" class="page-btn" data-page="' + (current - 1) + '"><i class="fas fa-chevron-left"></i></button>';
        }

        var maxVisible = 5;
        var start = Math.max(1, current - Math.floor(maxVisible / 2));
        var end = Math.min(pages, start + maxVisible - 1);

        if (end - start < maxVisible - 1) {
            start = Math.max(1, end - maxVisible + 1);
        }

        if (start > 1) {
            html += '<button type="button" class="page-btn" data-page="1">1</button>';
            if (start > 2) html += '<span class="page-ellipsis">...</span>';
        }

        for (var i = start; i <= end; i++) {
            html += '<button type="button" class="page-btn ' + (i === current ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        if (end < pages) {
            if (end < pages - 1) html += '<span class="page-ellipsis">...</span>';
            html += '<button type="button" class="page-btn" data-page="' + pages + '">' + pages + '</button>';
        }

        if (current < pages) {
            html += '<button type="button" class="page-btn" data-page="' + (current + 1) + '"><i class="fas fa-chevron-right"></i></button>';
        }

        html += '</div>';
        pagination.innerHTML = html;

        pagination.querySelectorAll('.page-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                state.page = parseInt(this.dataset.page);
                fetchSongs();
                page.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    // ================================================================
    // PLAYER INTEGRATION
    // ================================================================

    function playSong(song) {
        var songObj = {
            name: song.name,
            artist: song.artist,
            album: song.album || '',
            url: song.url,
            cover_art_url: song.cover_art_url,
            song_id: song.song_id || song.id,
            permalink: song.permalink,
            artist_permalink: song.artist_permalink,
            album_permalink: song.album_permalink
        };

        if (typeof window.FMLPlaySongAtTop === 'function') {
            window.FMLPlaySongAtTop(songObj);
            // Explicit play() needed — autoplay in Amplitude.init() can be blocked
            // by Chrome's autoplay policy when crossOrigin reset triggers source reload
            setTimeout(function() {
                try { Amplitude.play(); } catch(e) {}
            }, 150);
        } else if (typeof Amplitude !== 'undefined') {
            try {
                Amplitude.stop();
                setTimeout(function() {
                    Amplitude.init({
                        songs: [songObj],
                        autoplay: true,
                        callbacks: {
                            song_change: function() {
                                if (typeof window.updatePlayerMeta === 'function') window.updatePlayerMeta();
                                if (typeof window.updateLicenseButton === 'function') window.updateLicenseButton();
                            }
                        }
                    });
                    setTimeout(function() { Amplitude.play(); }, 100);
                    localStorage.setItem('fml_queue', JSON.stringify([songObj]));
                    if (typeof window.FMLReinitAudioAnalyser === 'function') {
                        setTimeout(window.FMLReinitAudioAnalyser, 200);
                    }
                }, 50);
            } catch (err) {
                console.error('Songs Discovery: Error playing song:', err);
            }
        }
    }

    function playAll() {
        if (state.songs.length === 0) return;

        var songObjs = state.songs.filter(function(s) { return s.url; }).map(function(song) {
            return {
                name: song.name,
                artist: song.artist,
                album: song.album || '',
                url: song.url,
                cover_art_url: song.cover_art_url,
                song_id: song.song_id || song.id,
                permalink: song.permalink,
                artist_permalink: song.artist_permalink,
                album_permalink: song.album_permalink
            };
        });

        if (songObjs.length === 0) return;

        if (typeof Amplitude !== 'undefined') {
            try {
                Amplitude.stop();
                setTimeout(function() {
                    Amplitude.init({
                        songs: songObjs,
                        autoplay: true,
                        callbacks: {
                            song_change: function() {
                                if (typeof window.updatePlayerMeta === 'function') window.updatePlayerMeta();
                                if (typeof window.updateLicenseButton === 'function') window.updateLicenseButton();
                            }
                        }
                    });
                    setTimeout(function() { Amplitude.play(); }, 100);
                    localStorage.setItem('fml_queue', JSON.stringify(songObjs));
                    if (typeof window.FMLReinitAudioAnalyser === 'function') {
                        setTimeout(window.FMLReinitAudioAnalyser, 200);
                    }
                }, 50);
            } catch (err) {
                console.error('Songs Discovery: Error playing all:', err);
            }
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    function formatDuration(seconds) {
        if (!seconds || seconds === '0' || isNaN(seconds)) return '—';
        seconds = parseInt(seconds);
        if (seconds <= 0) return '—';
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ================================================================
    // INITIALIZATION — always register PJAX listeners
    // ================================================================

    // Try to initialize immediately (works on direct page load)
    init();

    // PJAX support — always registered regardless of initial config
    document.addEventListener('pjax:load', function() {
        var p = document.getElementById('songs-discovery-page');
        if (p) {
            p.dataset.initialized = '';
            init();
        }
    });

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('pjax:complete', function() {
            var p = document.getElementById('songs-discovery-page');
            if (p) {
                p.dataset.initialized = '';
                init();
            }
        });
    }
})();
