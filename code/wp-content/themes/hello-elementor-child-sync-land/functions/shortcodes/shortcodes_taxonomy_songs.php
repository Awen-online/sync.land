<?php
/**
 * Taxonomy Songs Shortcode
 * Displays all songs within a genre or mood taxonomy with stats, search, and sorting
 * Usage: [taxonomy_songs] - auto-detects current taxonomy page
 * Usage: [taxonomy_songs taxonomy="genre" term="blues"] - explicit
 */

function display_taxonomy_songs($atts) {
    $atts = shortcode_atts([
        'taxonomy' => '',
        'term' => '',
        'per_page' => 20,
    ], $atts);

    // Auto-detect taxonomy and term from current page
    $taxonomy = $atts['taxonomy'];
    $term_slug = $atts['term'];

    if (empty($taxonomy) || empty($term_slug)) {
        $queried_object = get_queried_object();
        if ($queried_object && is_a($queried_object, 'WP_Term')) {
            $taxonomy = $queried_object->taxonomy;
            $term_slug = $queried_object->slug;
        }
    }

    if (empty($taxonomy) || empty($term_slug)) {
        return '<p class="taxonomy-error">Unable to detect taxonomy. Please specify taxonomy and term attributes.</p>';
    }

    // Get term info
    $term = get_term_by('slug', $term_slug, $taxonomy);
    if (!$term) {
        return '<p class="taxonomy-error">Taxonomy term not found.</p>';
    }

    // Get stats - with fallback if function not ready
    $stats = ['songs' => 0, 'artists' => 0, 'albums' => 0, 'top_moods' => [], 'top_genres' => []];
    if (function_exists('fml_get_taxonomy_stats')) {
        $stats = fml_get_taxonomy_stats($taxonomy, $term->term_id);
    }

    // Get a sample of album covers for the header
    $header_images = [];
    if (function_exists('fml_get_taxonomy_header_images')) {
        $header_images = fml_get_taxonomy_header_images($taxonomy, $term->term_id, 6);
    }

    $per_page = intval($atts['per_page']);
    $taxonomy_label = $taxonomy === 'genre' ? 'Genre' : 'Mood';
    $taxonomy_icon = $taxonomy === 'genre' ? 'fa-guitar' : 'fa-heart';
    $accent_color = $taxonomy === 'genre' ? '#5dade2' : '#f5b041';

    ob_start();
    ?>
    <div id="taxonomy-songs-page"
         class="taxonomy-songs-page <?php echo esc_attr($taxonomy); ?>-page"
         data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
         data-term="<?php echo esc_attr($term_slug); ?>"
         data-per-page="<?php echo $per_page; ?>">

        <!-- Header with Images -->
        <div class="taxonomy-header">
            <div class="taxonomy-header-images">
                <?php foreach ($header_images as $img): ?>
                    <div class="header-image-item">
                        <img src="<?php echo esc_url($img); ?>" alt="" loading="lazy">
                    </div>
                <?php endforeach; ?>
                <div class="header-overlay"></div>
            </div>
            <div class="taxonomy-header-content">
                <div class="taxonomy-badge">
                    <i class="fas <?php echo $taxonomy_icon; ?>"></i>
                    <?php echo esc_html($taxonomy_label); ?>
                </div>
                <h1 class="taxonomy-title"><?php echo esc_html($term->name); ?></h1>
                <?php if ($term->description): ?>
                    <p class="taxonomy-description"><?php echo esc_html($term->description); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="taxonomy-stats">
            <div class="stat-item">
                <i class="fas fa-music"></i>
                <span class="stat-value"><?php echo number_format($stats['songs']); ?></span>
                <span class="stat-label">Songs</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-user-circle"></i>
                <span class="stat-value"><?php echo number_format($stats['artists']); ?></span>
                <span class="stat-label">Artists</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-compact-disc"></i>
                <span class="stat-value"><?php echo number_format($stats['albums']); ?></span>
                <span class="stat-label">Albums</span>
            </div>
            <?php if ($taxonomy === 'genre' && !empty($stats['top_moods'])): ?>
                <div class="stat-item stat-tags">
                    <i class="fas fa-heart"></i>
                    <span class="stat-label">Top Moods:</span>
                    <span class="stat-tags-list">
                        <?php foreach (array_slice($stats['top_moods'], 0, 3) as $mood): ?>
                            <a href="/mood/<?php echo esc_attr($mood['slug']); ?>/" class="stat-tag mood-tag"><?php echo esc_html($mood['name']); ?></a>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php elseif ($taxonomy === 'mood' && !empty($stats['top_genres'])): ?>
                <div class="stat-item stat-tags">
                    <i class="fas fa-guitar"></i>
                    <span class="stat-label">Top Genres:</span>
                    <span class="stat-tags-list">
                        <?php foreach (array_slice($stats['top_genres'], 0, 3) as $genre): ?>
                            <a href="/genre/<?php echo esc_attr($genre['slug']); ?>/" class="stat-tag genre-tag"><?php echo esc_html($genre['name']); ?></a>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Controls -->
        <div class="taxonomy-controls">
            <div class="taxonomy-search-wrap">
                <input type="text"
                       id="taxonomy-search"
                       class="taxonomy-search-input"
                       placeholder="Search songs..."
                       autocomplete="off">
                <i class="fas fa-search taxonomy-search-icon"></i>
            </div>

            <div class="taxonomy-sort-wrap">
                <label class="sort-label"><i class="fas fa-sort"></i> Sort</label>
                <select id="taxonomy-sort" class="taxonomy-sort-select">
                    <option value="title">Title (A-Z)</option>
                    <option value="title_desc">Title (Z-A)</option>
                    <option value="date">Newest First</option>
                    <option value="date_asc">Oldest First</option>
                    <option value="artist">Artist (A-Z)</option>
                    <option value="popular">Most Popular</option>
                </select>
            </div>

            <button type="button" id="play-all-taxonomy" class="play-all-btn">
                <i class="fas fa-play"></i> Play All
            </button>
        </div>

        <!-- Results Count -->
        <div class="taxonomy-results-info">
            <span id="taxonomy-results-count">Loading songs...</span>
        </div>

        <!-- Loading -->
        <div id="taxonomy-loading" class="taxonomy-loading">
            <div class="taxonomy-loading-spinner"></div>
        </div>

        <!-- Songs Container -->
        <div id="taxonomy-songs-container" class="taxonomy-songs-container">
            <!-- Songs loaded via JS -->
        </div>

        <!-- Pagination -->
        <div id="taxonomy-pagination" class="taxonomy-pagination">
            <!-- Pagination loaded via JS -->
        </div>
    </div>

    <script>
    (function() {
        // Unique key to prevent double-init
        var initKey = 'taxonomySongsInit_' + Date.now();

        function initTaxonomySongs() {
            var page = document.getElementById('taxonomy-songs-page');
            if (!page) return;

            // Prevent double initialization
            if (page.dataset.initialized === 'true') return;
            page.dataset.initialized = 'true';

            var taxonomy = page.dataset.taxonomy;
            var term = page.dataset.term;
            var perPage = parseInt(page.dataset.perPage) || 20;
            var currentPage = 1;
            var debounceTimer = null;
            var allSongs = [];

            var container = document.getElementById('taxonomy-songs-container');
            var loading = document.getElementById('taxonomy-loading');
            var pagination = document.getElementById('taxonomy-pagination');
            var resultsCount = document.getElementById('taxonomy-results-count');
            var searchInput = document.getElementById('taxonomy-search');
            var sortSelect = document.getElementById('taxonomy-sort');
            var playAllBtn = document.getElementById('play-all-taxonomy');

            if (!container || !loading || !searchInput || !sortSelect) {
                console.error('Taxonomy songs: Missing required elements');
                return;
            }

            // Search with debounce
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    currentPage = 1;
                    loadSongs();
                }, 300);
            });

            // Sort change
            sortSelect.addEventListener('change', function() {
                currentPage = 1;
                loadSongs();
            });

            // Play all button
            if (playAllBtn) {
                playAllBtn.addEventListener('click', function() {
                    if (allSongs.length > 0 && typeof window.playMultipleSongs === 'function') {
                        window.playMultipleSongs(allSongs);
                    } else if (allSongs.length > 0) {
                        var firstPlayBtn = container.querySelector('.song-play');
                        if (firstPlayBtn) firstPlayBtn.click();
                    }
                });
            }

            function loadSongs() {
                loading.style.display = 'flex';
                container.style.opacity = '0.5';

                var params = new URLSearchParams({
                    taxonomy: taxonomy,
                    term: term,
                    page: currentPage,
                    per_page: perPage,
                    orderby: sortSelect.value
                });

                if (searchInput.value) params.set('q', searchInput.value);

                fetch('/wp-json/FML/v1/taxonomy-songs?' + params.toString())
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.error) {
                            throw new Error(data.message || 'API error');
                        }
                        allSongs = data.songs || [];
                        renderSongs(data.songs);
                        renderPagination(data.total, data.pages, data.page);
                        resultsCount.textContent = 'Showing ' + data.songs.length + ' of ' + data.total + ' songs';
                    })
                    .catch(function(err) {
                        console.error('Error loading songs:', err);
                        container.innerHTML = '<p class="taxonomy-error">Error loading songs. Please try again.</p>';
                        resultsCount.textContent = 'Error loading songs';
                    })
                    .finally(function() {
                        loading.style.display = 'none';
                        container.style.opacity = '1';
                    });
            }

            function escapeHtml(str) {
                if (!str) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            function renderSongs(songs) {
                if (!songs || songs.length === 0) {
                    container.innerHTML = '<p class="taxonomy-no-results">No songs found.</p>';
                    return;
                }

                var html = songs.map(function(song) {
                    var genreTags = (song.genres || []).map(function(g) {
                        return '<a href="/genre/' + escapeHtml(g.slug) + '/" class="song-tag genre-tag">' + escapeHtml(g.name) + '</a>';
                    }).join('');
                    var moodTags = (song.moods || []).map(function(m) {
                        return '<a href="/mood/' + escapeHtml(m.slug) + '/" class="song-tag mood-tag">' + escapeHtml(m.name) + '</a>';
                    }).join('');

                    return '<div class="song-row" data-song-id="' + song.id + '">' +
                        '<button type="button" class="song-play"' +
                            ' data-audiosrc="' + escapeHtml(song.audio_url) + '"' +
                            ' data-songname="' + escapeHtml(song.name) + '"' +
                            ' data-artistname="' + escapeHtml(song.artist_name) + '"' +
                            ' data-albumname="' + escapeHtml(song.album_name) + '"' +
                            ' data-artsrc="' + escapeHtml(song.cover_art) + '"' +
                            ' data-songid="' + song.id + '"' +
                            ' data-permalink="' + escapeHtml(song.permalink) + '"' +
                            ' data-artistpermalink="' + escapeHtml(song.artist_permalink) + '"' +
                            ' data-albumpermalink="' + escapeHtml(song.album_permalink) + '">' +
                            '<div class="song-cover" style="background-image: url(\'' + escapeHtml(song.cover_art) + '\')">' +
                                '<i class="fas fa-play"></i>' +
                            '</div>' +
                        '</button>' +
                        '<div class="song-info">' +
                            '<a href="' + escapeHtml(song.permalink) + '" class="song-title">' + escapeHtml(song.name) + '</a>' +
                            '<div class="song-meta">' +
                                '<a href="' + escapeHtml(song.artist_permalink) + '" class="song-artist">' + escapeHtml(song.artist_name) + '</a>' +
                                (song.album_name ? '<span class="song-meta-sep">•</span><a href="' + escapeHtml(song.album_permalink) + '" class="song-album">' + escapeHtml(song.album_name) + '</a>' : '') +
                            '</div>' +
                            '<div class="song-tags">' + genreTags + moodTags + '</div>' +
                        '</div>' +
                        '<div class="song-actions">' +
                            '<a href="' + escapeHtml(song.permalink) + '" class="action-btn download-btn" title="License & Download">' +
                                '<i class="fas fa-download"></i>' +
                            '</a>' +
                        '</div>' +
                    '</div>';
                }).join('');

                container.innerHTML = html;
            }

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
                        currentPage = parseInt(this.dataset.page);
                        loadSongs();
                        page.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                });
            }

            // Initial load
            loadSongs();
        }

        // Initialize immediately
        initTaxonomySongs();

        // Also listen for PJAX navigation events
        document.addEventListener('pjax:load', function() {
            // Reset initialized flag on new page load via PJAX
            var page = document.getElementById('taxonomy-songs-page');
            if (page) {
                page.dataset.initialized = '';
                initTaxonomySongs();
            }
        });

        // jQuery PJAX event
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('pjax:complete', function() {
                var page = document.getElementById('taxonomy-songs-page');
                if (page) {
                    page.dataset.initialized = '';
                    initTaxonomySongs();
                }
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('taxonomy_songs', 'display_taxonomy_songs');

/**
 * Get stats for a taxonomy term
 */
function fml_get_taxonomy_stats($taxonomy, $term_id) {
    $cache_key = "taxonomy_stats_{$taxonomy}_{$term_id}";
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    // Get all songs with this term
    $songs = get_posts([
        'post_type' => 'song',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => $taxonomy,
                'terms' => $term_id,
            ]
        ]
    ]);

    $artists = [];
    $albums = [];
    $related_terms = [];

    // The "other" taxonomy
    $other_taxonomy = $taxonomy === 'genre' ? 'mood' : 'genre';

    foreach ($songs as $song_id) {
        // Get artist
        $song_pod = pods('song', $song_id);
        $artist_data = $song_pod->field('artist');
        if (!empty($artist_data)) {
            $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
            $artists[$artist_id] = true;
        }

        // Get album
        $album_data = $song_pod->field('album');
        if (!empty($album_data)) {
            $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
            $albums[$album_id] = true;
        }

        // Get related terms (moods if genre page, genres if mood page)
        $terms = wp_get_post_terms($song_id, $other_taxonomy);
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                if (!isset($related_terms[$t->term_id])) {
                    $related_terms[$t->term_id] = [
                        'id' => $t->term_id,
                        'name' => $t->name,
                        'slug' => $t->slug,
                        'count' => 0
                    ];
                }
                $related_terms[$t->term_id]['count']++;
            }
        }
    }

    // Sort related terms by count
    usort($related_terms, fn($a, $b) => $b['count'] - $a['count']);

    $stats = [
        'songs' => count($songs),
        'artists' => count($artists),
        'albums' => count($albums),
    ];

    if ($taxonomy === 'genre') {
        $stats['top_moods'] = array_values($related_terms);
    } else {
        $stats['top_genres'] = array_values($related_terms);
    }

    set_transient($cache_key, $stats, HOUR_IN_SECONDS);

    return $stats;
}

/**
 * Get header images for taxonomy page (album covers)
 */
function fml_get_taxonomy_header_images($taxonomy, $term_id, $count = 6) {
    $cache_key = "taxonomy_images_{$taxonomy}_{$term_id}";
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $songs = get_posts([
        'post_type' => 'song',
        'posts_per_page' => 50,
        'orderby' => 'rand',
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => $taxonomy,
                'terms' => $term_id,
            ]
        ]
    ]);

    $images = [];
    $seen_albums = [];

    foreach ($songs as $song_id) {
        if (count($images) >= $count) break;

        $song_pod = pods('song', $song_id);
        $album_data = $song_pod->field('album');

        if (!empty($album_data)) {
            $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;

            // Avoid duplicate album covers
            if (isset($seen_albums[$album_id])) continue;
            $seen_albums[$album_id] = true;

            $cover = get_the_post_thumbnail_url($album_id, 'medium');
            if ($cover) {
                $images[] = $cover;
            }
        }
    }

    set_transient($cache_key, $images, HOUR_IN_SECONDS);

    return $images;
}
