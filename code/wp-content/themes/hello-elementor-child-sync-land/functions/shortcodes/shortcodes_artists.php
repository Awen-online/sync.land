<?php
/**
 * Artist Directory Shortcode
 * Replaces pods_datatable with a searchable, filterable directory
 * Supports gallery and list views
 */

/**
 * Main artist directory shortcode
 * Usage: [artist_directory view="gallery" per_page="20"]
 */
function display_artist_directory($atts) {
    $atts = shortcode_atts([
        'view' => 'gallery', // gallery or list
        'per_page' => 20,
    ], $atts);

    $default_view = esc_attr($atts['view']);
    $per_page = intval($atts['per_page']);

    // Get initial data - check if function exists first
    $genres = [];
    $moods = [];
    if (function_exists('fml_get_artist_filters')) {
        $filters_response = fml_get_artist_filters(new WP_REST_Request());
        $genres = $filters_response['genres'] ?? [];
        $moods = $filters_response['moods'] ?? [];
    }

    ob_start();
    ?>
    <div id="artist-directory" class="artist-directory" data-per-page="<?php echo $per_page; ?>">
        <!-- Controls Bar -->
        <div class="artist-directory-controls">
            <!-- Search -->
            <div class="artist-search-wrap">
                <input type="text"
                       id="artist-search"
                       class="artist-search-input"
                       placeholder="Search artists..."
                       autocomplete="off">
                <i class="fas fa-search artist-search-icon"></i>
            </div>

            <!-- Filters -->
            <div class="artist-filters">
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-guitar"></i> Genre</label>
                    <div class="multiselect" id="genre-multiselect">
                        <button type="button" class="multiselect-toggle">
                            <span class="multiselect-text">All Genres</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="multiselect-dropdown">
                            <div class="multiselect-search">
                                <input type="text" placeholder="Search genres..." class="multiselect-search-input">
                            </div>
                            <div class="multiselect-options">
                                <?php foreach ($genres as $genre): ?>
                                    <div class="multiselect-option" data-value="<?php echo esc_attr($genre['slug']); ?>">
                                        <input type="checkbox" value="<?php echo esc_attr($genre['slug']); ?>">
                                        <span class="option-text"><?php echo esc_html($genre['name']); ?></span>
                                        <span class="option-count"><?php echo $genre['count']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="multiselect-actions">
                                <button type="button" class="multiselect-clear">Clear</button>
                                <button type="button" class="multiselect-apply">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-heart"></i> Mood</label>
                    <div class="multiselect" id="mood-multiselect">
                        <button type="button" class="multiselect-toggle">
                            <span class="multiselect-text">All Moods</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="multiselect-dropdown">
                            <div class="multiselect-search">
                                <input type="text" placeholder="Search moods..." class="multiselect-search-input">
                            </div>
                            <div class="multiselect-options">
                                <?php foreach ($moods as $mood): ?>
                                    <div class="multiselect-option" data-value="<?php echo esc_attr($mood['slug']); ?>">
                                        <input type="checkbox" value="<?php echo esc_attr($mood['slug']); ?>">
                                        <span class="option-text"><?php echo esc_html($mood['name']); ?></span>
                                        <span class="option-count"><?php echo $mood['count']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="multiselect-actions">
                                <button type="button" class="multiselect-clear">Clear</button>
                                <button type="button" class="multiselect-apply">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-sort"></i> Sort</label>
                    <select id="artist-sort" class="artist-filter-select">
                        <option value="name">Name (A-Z)</option>
                        <option value="date">Newest First</option>
                        <option value="songs">Most Songs</option>
                        <option value="albums">Most Albums</option>
                    </select>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="artist-view-toggle">
                <button type="button"
                        class="view-toggle-btn <?php echo $default_view === 'gallery' ? 'active' : ''; ?>"
                        data-view="gallery"
                        title="Gallery View">
                    <i class="fas fa-th-large"></i>
                </button>
                <button type="button"
                        class="view-toggle-btn <?php echo $default_view === 'list' ? 'active' : ''; ?>"
                        data-view="list"
                        title="List View">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>

        <!-- Active Filters Display -->
        <div id="artist-active-filters" class="artist-active-filters" style="display: none;">
            <span class="active-filters-label">Active filters:</span>
            <div class="active-filters-tags"></div>
            <button type="button" class="clear-all-filters">Clear All</button>
        </div>

        <!-- Results Count -->
        <div class="artist-results-info">
            <span id="artist-results-count">Loading artists...</span>
        </div>

        <!-- Loading Indicator -->
        <div id="artist-loading" class="artist-loading">
            <div class="artist-loading-spinner"></div>
        </div>

        <!-- Artist Grid/List Container -->
        <div id="artist-container" class="artist-container <?php echo $default_view; ?>-view">
            <!-- Artists will be loaded here via JS -->
        </div>

        <!-- Pagination -->
        <div id="artist-pagination" class="artist-pagination">
            <!-- Pagination will be loaded here via JS -->
        </div>
    </div>

    <script>
    (function() {
        const directory = document.getElementById('artist-directory');
        if (!directory) return;

        const perPage = parseInt(directory.dataset.perPage) || 20;
        let currentPage = 1;
        let currentView = '<?php echo $default_view; ?>';
        let debounceTimer = null;

        const container = document.getElementById('artist-container');
        const loading = document.getElementById('artist-loading');
        const pagination = document.getElementById('artist-pagination');
        const resultsCount = document.getElementById('artist-results-count');
        const activeFiltersContainer = document.getElementById('artist-active-filters');
        const activeFiltersTags = activeFiltersContainer.querySelector('.active-filters-tags');

        const searchInput = document.getElementById('artist-search');
        const sortSelect = document.getElementById('artist-sort');

        // Multiselect state
        const selectedGenres = new Set();
        const selectedMoods = new Set();

        // Initialize multiselects
        function initMultiselect(id, selectedSet, label) {
            const multiselect = document.getElementById(id);
            const toggle = multiselect.querySelector('.multiselect-toggle');
            const dropdown = multiselect.querySelector('.multiselect-dropdown');
            const textEl = multiselect.querySelector('.multiselect-text');
            const searchInput = multiselect.querySelector('.multiselect-search-input');
            const options = multiselect.querySelectorAll('.multiselect-option');
            const clearBtn = multiselect.querySelector('.multiselect-clear');
            const applyBtn = multiselect.querySelector('.multiselect-apply');

            // Toggle dropdown
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                // Close other multiselects
                document.querySelectorAll('.multiselect.open').forEach(ms => {
                    if (ms !== multiselect) ms.classList.remove('open');
                });
                multiselect.classList.toggle('open');
                if (multiselect.classList.contains('open')) {
                    searchInput.focus();
                }
            });

            // Search filtering
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                options.forEach(opt => {
                    const text = opt.querySelector('.option-text').textContent.toLowerCase();
                    opt.style.display = text.includes(query) ? '' : 'none';
                });
            });

            // Option selection - clicking anywhere on the row toggles the checkbox
            options.forEach(opt => {
                const checkbox = opt.querySelector('input[type="checkbox"]');

                opt.addEventListener('click', function(e) {
                    // If clicking directly on checkbox, it already toggled
                    if (e.target !== checkbox) {
                        checkbox.checked = !checkbox.checked;
                    }

                    // Update selected set
                    if (checkbox.checked) {
                        selectedSet.add(checkbox.value);
                    } else {
                        selectedSet.delete(checkbox.value);
                    }
                    updateToggleText();
                });
            });

            // Clear button
            clearBtn.addEventListener('click', function() {
                selectedSet.clear();
                options.forEach(opt => {
                    opt.querySelector('input[type="checkbox"]').checked = false;
                });
                updateToggleText();
            });

            // Apply button
            applyBtn.addEventListener('click', function() {
                multiselect.classList.remove('open');
                currentPage = 1;
                loadArtists();
            });

            function updateToggleText() {
                if (selectedSet.size === 0) {
                    textEl.textContent = 'All ' + label;
                    textEl.classList.remove('has-selection');
                } else if (selectedSet.size === 1) {
                    const selectedOpt = multiselect.querySelector(`input[value="${[...selectedSet][0]}"]`);
                    textEl.textContent = selectedOpt.parentElement.querySelector('.option-text').textContent;
                    textEl.classList.add('has-selection');
                } else {
                    textEl.textContent = selectedSet.size + ' ' + label + ' selected';
                    textEl.classList.add('has-selection');
                }
            }

            return { updateToggleText, clearSelection: () => {
                selectedSet.clear();
                options.forEach(opt => opt.querySelector('input[type="checkbox"]').checked = false);
                updateToggleText();
            }};
        }

        const genreMultiselect = initMultiselect('genre-multiselect', selectedGenres, 'Genres');
        const moodMultiselect = initMultiselect('mood-multiselect', selectedMoods, 'Moods');

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.multiselect')) {
                document.querySelectorAll('.multiselect.open').forEach(ms => ms.classList.remove('open'));
            }
        });

        // View toggle buttons
        document.querySelectorAll('.view-toggle-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                currentView = this.dataset.view;
                document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                container.className = 'artist-container ' + currentView + '-view';
            });
        });

        // Search with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                loadArtists();
            }, 300);
        });

        // Sort change
        sortSelect.addEventListener('change', function() {
            currentPage = 1;
            loadArtists();
        });

        // Clear all filters
        activeFiltersContainer.querySelector('.clear-all-filters').addEventListener('click', function() {
            searchInput.value = '';
            genreMultiselect.clearSelection();
            moodMultiselect.clearSelection();
            sortSelect.value = 'name';
            currentPage = 1;
            loadArtists();
        });

        function updateActiveFilters() {
            const filters = [];

            if (searchInput.value) {
                filters.push({ type: 'search', label: 'Search: ' + searchInput.value, value: searchInput.value });
            }

            selectedGenres.forEach(slug => {
                const opt = document.querySelector(`#genre-multiselect input[value="${slug}"]`);
                if (opt) {
                    const name = opt.parentElement.querySelector('.option-text').textContent;
                    filters.push({ type: 'genre', label: 'Genre: ' + name, value: slug });
                }
            });

            selectedMoods.forEach(slug => {
                const opt = document.querySelector(`#mood-multiselect input[value="${slug}"]`);
                if (opt) {
                    const name = opt.parentElement.querySelector('.option-text').textContent;
                    filters.push({ type: 'mood', label: 'Mood: ' + name, value: slug });
                }
            });

            if (filters.length > 0) {
                activeFiltersContainer.style.display = 'flex';
                activeFiltersTags.innerHTML = filters.map(f =>
                    `<span class="filter-tag" data-type="${f.type}" data-value="${f.value}">${f.label} <button type="button" class="remove-filter">&times;</button></span>`
                ).join('');

                // Add remove handlers
                activeFiltersTags.querySelectorAll('.remove-filter').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const tag = this.parentElement;
                        const type = tag.dataset.type;
                        const value = tag.dataset.value;

                        if (type === 'search') {
                            searchInput.value = '';
                        } else if (type === 'genre') {
                            selectedGenres.delete(value);
                            const checkbox = document.querySelector(`#genre-multiselect input[value="${value}"]`);
                            if (checkbox) checkbox.checked = false;
                            genreMultiselect.updateToggleText();
                        } else if (type === 'mood') {
                            selectedMoods.delete(value);
                            const checkbox = document.querySelector(`#mood-multiselect input[value="${value}"]`);
                            if (checkbox) checkbox.checked = false;
                            moodMultiselect.updateToggleText();
                        }
                        currentPage = 1;
                        loadArtists();
                    });
                });
            } else {
                activeFiltersContainer.style.display = 'none';
            }
        }

        function loadArtists() {
            loading.style.display = 'flex';
            container.style.opacity = '0.5';

            const params = new URLSearchParams({
                page: currentPage,
                per_page: perPage,
                orderby: sortSelect.value
            });

            if (searchInput.value) params.set('q', searchInput.value);
            if (selectedGenres.size > 0) params.set('genre', [...selectedGenres].join(','));
            if (selectedMoods.size > 0) params.set('mood', [...selectedMoods].join(','));

            fetch('/wp-json/FML/v1/artists?' + params.toString())
                .then(res => res.json())
                .then(data => {
                    renderArtists(data.artists);
                    renderPagination(data.total, data.pages, data.page);
                    resultsCount.textContent = `Showing ${data.artists.length} of ${data.total} artists`;
                    updateActiveFilters();
                })
                .catch(err => {
                    console.error('Error loading artists:', err);
                    container.innerHTML = '<p class="artist-error">Error loading artists. Please try again.</p>';
                })
                .finally(() => {
                    loading.style.display = 'none';
                    container.style.opacity = '1';
                });
        }

        function renderArtists(artists) {
            if (artists.length === 0) {
                container.innerHTML = '<p class="artist-no-results">No artists found matching your criteria.</p>';
                return;
            }

            container.innerHTML = artists.map(artist => {
                const genreTags = artist.genres.length > 0
                    ? `<div class="tag-group genre-group"><span class="tag-label"><i class="fas fa-guitar"></i> Genres:</span>${artist.genres.map(g => `<a href="/genre/${g.slug}/" class="artist-tag genre-tag" onclick="event.stopPropagation();">${g.name}</a>`).join('')}</div>`
                    : '';
                const moodTags = artist.moods.length > 0
                    ? `<div class="tag-group mood-group"><span class="tag-label"><i class="fas fa-heart"></i> Moods:</span>${artist.moods.map(m => `<a href="/mood/${m.slug}/" class="artist-tag mood-tag" onclick="event.stopPropagation();">${m.name}</a>`).join('')}</div>`
                    : '';

                const imageHtml = artist.image
                    ? `<img src="${artist.image}" alt="${artist.name}" loading="lazy">`
                    : `<div class="artist-no-image"><i class="fas fa-user"></i></div>`;

                return `
                    <div class="artist-card" data-id="${artist.id}">
                        <a href="${artist.permalink}" class="artist-card-link">
                            <div class="artist-image">
                                ${imageHtml}
                                <div class="artist-overlay">
                                    <h3 class="artist-name">${artist.name}</h3>
                                    <div class="artist-meta">
                                        <span class="meta-item" title="Albums">
                                            <i class="fas fa-compact-disc"></i> ${artist.albums}
                                        </span>
                                        <span class="meta-item" title="Songs">
                                            <i class="fas fa-music"></i> ${artist.songs}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="artist-info">
                                <div class="artist-meta-date">
                                    <i class="fas fa-calendar"></i> Joined ${artist.date_joined_formatted}
                                </div>
                                <div class="artist-tags">
                                    ${genreTags}${moodTags}
                                </div>
                            </div>
                        </a>
                    </div>
                `;
            }).join('');
        }

        function renderPagination(total, pages, current) {
            if (pages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let html = '<div class="pagination-inner">';

            // Previous button
            if (current > 1) {
                html += `<button type="button" class="page-btn" data-page="${current - 1}"><i class="fas fa-chevron-left"></i></button>`;
            }

            // Page numbers
            const maxVisible = 5;
            let start = Math.max(1, current - Math.floor(maxVisible / 2));
            let end = Math.min(pages, start + maxVisible - 1);

            if (end - start < maxVisible - 1) {
                start = Math.max(1, end - maxVisible + 1);
            }

            if (start > 1) {
                html += `<button type="button" class="page-btn" data-page="1">1</button>`;
                if (start > 2) html += '<span class="page-ellipsis">...</span>';
            }

            for (let i = start; i <= end; i++) {
                html += `<button type="button" class="page-btn ${i === current ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }

            if (end < pages) {
                if (end < pages - 1) html += '<span class="page-ellipsis">...</span>';
                html += `<button type="button" class="page-btn" data-page="${pages}">${pages}</button>`;
            }

            // Next button
            if (current < pages) {
                html += `<button type="button" class="page-btn" data-page="${current + 1}"><i class="fas fa-chevron-right"></i></button>`;
            }

            html += '</div>';
            pagination.innerHTML = html;

            // Add click handlers
            pagination.querySelectorAll('.page-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentPage = parseInt(this.dataset.page);
                    loadArtists();
                    directory.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        }

        // Initial load
        loadArtists();
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('artist_directory', 'display_artist_directory');

/**
 * Legacy shortcode - redirect to new one
 */
function display_pods_datatable() {
    return display_artist_directory(['view' => 'list']);
}
add_shortcode('pods_datatable', 'display_pods_datatable');
