<?php
/**
 * Tag Coverage Admin Page
 *
 * Finds songs missing `genre` and/or `mood` taxonomy terms so they can be
 * backfilled. Goal is to reach 100% tag coverage across the song catalog.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register submenu under Sync.Land
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'syncland',
        'Tag Coverage',
        'Tag Coverage',
        'manage_options',
        'syncland-tag-coverage',
        'fml_tag_coverage_page'
    );
}, 20);

/**
 * Resolve an array of term inputs (IDs or names) into an array of term IDs.
 * Creates new terms if names don't exist.
 */
function fml_tag_coverage_resolve_terms(array $inputs, $taxonomy) {
    $ids = [];
    foreach ($inputs as $raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            continue;
        }

        // Numeric = existing term ID
        if (ctype_digit($raw)) {
            $term = get_term((int) $raw, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $ids[] = (int) $term->term_id;
            }
            continue;
        }

        // String = find or create by name
        $existing = get_term_by('name', $raw, $taxonomy);
        if ($existing) {
            $ids[] = (int) $existing->term_id;
            continue;
        }

        $inserted = wp_insert_term($raw, $taxonomy);
        if (!is_wp_error($inserted) && isset($inserted['term_id'])) {
            $ids[] = (int) $inserted['term_id'];
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Build a compact payload describing the next untagged song for the wizard.
 */
function fml_tag_coverage_song_payload($song_id) {
    $song_id = (int) $song_id;
    if (!$song_id) {
        return null;
    }

    $post = get_post($song_id);
    if (!$post || $post->post_type !== 'song') {
        return null;
    }

    $genres = wp_get_post_terms($song_id, 'genre', ['fields' => 'ids']);
    $moods  = wp_get_post_terms($song_id, 'mood',  ['fields' => 'ids']);

    return [
        'id'            => $song_id,
        'title'         => get_the_title($post),
        'edit_link'     => get_edit_post_link($song_id, 'raw'),
        'view_link'     => get_permalink($song_id),
        'current_genre' => is_wp_error($genres) ? [] : array_map('intval', $genres),
        'current_mood'  => is_wp_error($moods)  ? [] : array_map('intval', $moods),
    ];
}

/**
 * AJAX: save genre + mood terms for a song.
 * Returns updated counts and (optionally) the next untagged song payload.
 */
add_action('wp_ajax_fml_tag_coverage_save', function() {
    check_ajax_referer('fml_tag_coverage', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied'], 403);
    }

    $song_id = isset($_POST['song_id']) ? (int) $_POST['song_id'] : 0;
    if (!$song_id || get_post_type($song_id) !== 'song') {
        wp_send_json_error(['message' => 'Invalid song']);
    }

    $genres_in = isset($_POST['genres']) ? (array) wp_unslash($_POST['genres']) : [];
    $moods_in  = isset($_POST['moods'])  ? (array) wp_unslash($_POST['moods'])  : [];

    $genre_ids = fml_tag_coverage_resolve_terms($genres_in, 'genre');
    $mood_ids  = fml_tag_coverage_resolve_terms($moods_in,  'mood');

    wp_set_post_terms($song_id, $genre_ids, 'genre', false);
    wp_set_post_terms($song_id, $mood_ids,  'mood',  false);

    $filter = isset($_POST['filter']) ? sanitize_key($_POST['filter']) : 'missing_any';
    $allowed = ['missing_any', 'missing_both', 'missing_genre', 'missing_mood'];
    if (!in_array($filter, $allowed, true)) {
        $filter = 'missing_any';
    }

    // Find next untagged song matching the current filter (excluding the one we just tagged).
    $next = null;
    $next_query = new WP_Query(array_merge(
        [
            'post_type'      => 'song',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post__not_in'   => [$song_id],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ],
        (function($filter) {
            switch ($filter) {
                case 'missing_genre':
                    return ['tax_query' => [[
                        'taxonomy' => 'genre', 'operator' => 'NOT EXISTS',
                    ]]];
                case 'missing_mood':
                    return ['tax_query' => [[
                        'taxonomy' => 'mood', 'operator' => 'NOT EXISTS',
                    ]]];
                case 'missing_both':
                    return ['tax_query' => [
                        'relation' => 'AND',
                        ['taxonomy' => 'genre', 'operator' => 'NOT EXISTS'],
                        ['taxonomy' => 'mood',  'operator' => 'NOT EXISTS'],
                    ]];
                case 'missing_any':
                default:
                    return ['tax_query' => [
                        'relation' => 'OR',
                        ['taxonomy' => 'genre', 'operator' => 'NOT EXISTS'],
                        ['taxonomy' => 'mood',  'operator' => 'NOT EXISTS'],
                    ]];
            }
        })($filter)
    ));

    if (!empty($next_query->posts)) {
        $next = fml_tag_coverage_song_payload($next_query->posts[0]);
    }

    // Fresh terms for the just-saved row.
    $saved_genre_terms = wp_get_post_terms($song_id, 'genre');
    $saved_mood_terms  = wp_get_post_terms($song_id, 'mood');
    $saved_genre_terms = is_wp_error($saved_genre_terms) ? [] : $saved_genre_terms;
    $saved_mood_terms  = is_wp_error($saved_mood_terms)  ? [] : $saved_mood_terms;

    $map_term = function($t) {
        return ['id' => (int) $t->term_id, 'name' => $t->name];
    };

    wp_send_json_success([
        'song_id' => $song_id,
        'counts'  => fml_tag_coverage_counts(),
        'saved'   => [
            'genres' => array_map($map_term, $saved_genre_terms),
            'moods'  => array_map($map_term, $saved_mood_terms),
        ],
        'still_missing' => [
            'genre' => empty($saved_genre_terms),
            'mood'  => empty($saved_mood_terms),
        ],
        'next' => $next,
    ]);
});

/**
 * Query songs missing one or both of the genre/mood taxonomies.
 *
 * @param string $filter 'missing_both' | 'missing_genre' | 'missing_mood' | 'missing_any'
 * @param int    $paged
 * @param int    $per_page
 * @return WP_Query
 */
function fml_tag_coverage_query($filter = 'missing_any', $paged = 1, $per_page = 50) {
    $tax_query = ['relation' => 'AND'];

    switch ($filter) {
        case 'missing_genre':
            $tax_query[] = [
                'taxonomy' => 'genre',
                'operator' => 'NOT EXISTS',
            ];
            break;

        case 'missing_mood':
            $tax_query[] = [
                'taxonomy' => 'mood',
                'operator' => 'NOT EXISTS',
            ];
            break;

        case 'missing_both':
            $tax_query[] = [
                'taxonomy' => 'genre',
                'operator' => 'NOT EXISTS',
            ];
            $tax_query[] = [
                'taxonomy' => 'mood',
                'operator' => 'NOT EXISTS',
            ];
            break;

        case 'missing_any':
        default:
            $tax_query = [
                'relation' => 'OR',
                [
                    'taxonomy' => 'genre',
                    'operator' => 'NOT EXISTS',
                ],
                [
                    'taxonomy' => 'mood',
                    'operator' => 'NOT EXISTS',
                ],
            ];
            break;
    }

    return new WP_Query([
        'post_type'      => 'song',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'tax_query'      => $tax_query,
        'no_found_rows'  => false,
    ]);
}

/**
 * Get summary counts for the dashboard header.
 *
 * @return array{total:int, missing_genre:int, missing_mood:int, missing_both:int, missing_any:int, fully_tagged:int}
 */
function fml_tag_coverage_counts() {
    $total = (int) wp_count_posts('song')->publish;

    $count = function($filter) {
        $q = fml_tag_coverage_query($filter, 1, 1);
        return (int) $q->found_posts;
    };

    $missing_genre = $count('missing_genre');
    $missing_mood  = $count('missing_mood');
    $missing_both  = $count('missing_both');
    $missing_any   = $count('missing_any');

    return [
        'total'         => $total,
        'missing_genre' => $missing_genre,
        'missing_mood'  => $missing_mood,
        'missing_both'  => $missing_both,
        'missing_any'   => $missing_any,
        'fully_tagged'  => max(0, $total - $missing_any),
    ];
}

/**
 * Render the Tag Coverage admin page.
 */
function fml_tag_coverage_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to view this page.'));
    }

    $filter   = isset($_GET['filter']) ? sanitize_key($_GET['filter']) : 'missing_any';
    $paged    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $per_page = 50;

    $allowed_filters = ['missing_any', 'missing_both', 'missing_genre', 'missing_mood'];
    if (!in_array($filter, $allowed_filters, true)) {
        $filter = 'missing_any';
    }

    $counts    = fml_tag_coverage_counts();
    $query     = fml_tag_coverage_query($filter, $paged, $per_page);
    $coverage  = $counts['total'] > 0
        ? round(($counts['fully_tagged'] / $counts['total']) * 100, 1)
        : 0;

    $base_url = admin_url('admin.php?page=syncland-tag-coverage');

    $filter_labels = [
        'missing_any'   => 'Missing Any Tag',
        'missing_both'  => 'Missing Both',
        'missing_genre' => 'Missing Genre',
        'missing_mood'  => 'Missing Mood',
    ];
    ?>
    <div class="wrap">
        <h1>
            <span class="dashicons dashicons-tag" style="font-size: 30px; margin-right: 10px;"></span>
            Tag Coverage
        </h1>
        <p class="description">
            Find songs missing <code>genre</code> or <code>mood</code> taxonomy terms. Goal: 100% coverage.
        </p>

        <div style="display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0;">
            <div class="card" style="padding: 15px; text-align: center; min-width: 140px;">
                <div style="font-size: 28px; font-weight: bold; color: #2271b1;">
                    <?php echo number_format($counts['total']); ?>
                </div>
                <div style="color: #666;">Total Songs</div>
            </div>
            <div class="card" style="padding: 15px; text-align: center; min-width: 140px;">
                <div style="font-size: 28px; font-weight: bold; color: <?php echo $coverage >= 100 ? '#00a32a' : '#dba617'; ?>;">
                    <?php echo $coverage; ?>%
                </div>
                <div style="color: #666;">Fully Tagged</div>
                <div style="color: #888; font-size: 11px;">
                    <?php echo number_format($counts['fully_tagged']); ?> / <?php echo number_format($counts['total']); ?>
                </div>
            </div>
            <div class="card" style="padding: 15px; text-align: center; min-width: 140px;">
                <div style="font-size: 28px; font-weight: bold; color: #d63638;">
                    <?php echo number_format($counts['missing_genre']); ?>
                </div>
                <div style="color: #666;">Missing Genre</div>
            </div>
            <div class="card" style="padding: 15px; text-align: center; min-width: 140px;">
                <div style="font-size: 28px; font-weight: bold; color: #d63638;">
                    <?php echo number_format($counts['missing_mood']); ?>
                </div>
                <div style="color: #666;">Missing Mood</div>
            </div>
            <div class="card" style="padding: 15px; text-align: center; min-width: 140px;">
                <div style="font-size: 28px; font-weight: bold; color: #8c1d40;">
                    <?php echo number_format($counts['missing_both']); ?>
                </div>
                <div style="color: #666;">Missing Both</div>
            </div>
        </div>

        <h2 class="nav-tab-wrapper">
            <?php foreach ($filter_labels as $key => $label):
                $is_active = ($filter === $key);
                $url = add_query_arg(['filter' => $key, 'paged' => 1], $base_url);
                $class = 'nav-tab' . ($is_active ? ' nav-tab-active' : '');
                ?>
                <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($class); ?>">
                    <?php echo esc_html($label); ?>
                    <span style="opacity: 0.7;">(<?php echo number_format($counts[$key]); ?>)</span>
                </a>
            <?php endforeach; ?>
        </h2>

        <?php if (!$query->have_posts()): ?>
            <div class="notice notice-success" style="margin-top: 20px;">
                <p><strong>Nothing to see here!</strong> No songs match this filter.</p>
            </div>
        <?php else: ?>
            <p style="margin-top: 15px;">
                Showing <?php echo number_format($query->post_count); ?> of
                <?php echo number_format($query->found_posts); ?> songs.
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 35%;">Song</th>
                        <th style="width: 20%;">Artist</th>
                        <th style="width: 15%;">Genre</th>
                        <th style="width: 15%;">Mood</th>
                        <th style="width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($query->have_posts()): $query->the_post();
                    $song_id = get_the_ID();
                    $genres  = wp_get_post_terms($song_id, 'genre', ['fields' => 'names']);
                    $moods   = wp_get_post_terms($song_id, 'mood',  ['fields' => 'names']);

                    // Try to resolve artist display name via Pods relationship first,
                    // falling back to post_author display name.
                    $artist_label = '';
                    if (function_exists('pods')) {
                        $pod = pods('song', $song_id);
                        if ($pod && $pod->exists()) {
                            $artist_field = $pod->field('artist');
                            if (is_array($artist_field)) {
                                if (isset($artist_field['post_title'])) {
                                    $artist_label = $artist_field['post_title'];
                                } elseif (isset($artist_field[0]['post_title'])) {
                                    $artist_label = $artist_field[0]['post_title'];
                                }
                            } elseif (is_string($artist_field)) {
                                $artist_label = $artist_field;
                            }
                        }
                    }
                    if ($artist_label === '') {
                        $artist_label = get_the_author();
                    }

                    $edit_link = get_edit_post_link($song_id);
                    $view_link = get_permalink($song_id);
                    ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html(get_the_title() ?: '(no title)'); ?></a>
                            </strong>
                            <div style="color: #888; font-size: 11px;">ID: <?php echo (int) $song_id; ?></div>
                        </td>
                        <td><?php echo esc_html($artist_label); ?></td>
                        <td>
                            <?php if (empty($genres) || is_wp_error($genres)): ?>
                                <span style="color: #d63638;">— missing —</span>
                            <?php else: ?>
                                <?php echo esc_html(implode(', ', $genres)); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($moods) || is_wp_error($moods)): ?>
                                <span style="color: #d63638;">— missing —</span>
                            <?php else: ?>
                                <?php echo esc_html(implode(', ', $moods)); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-primary button-small fml-quick-tag"
                                    data-song-id="<?php echo (int) $song_id; ?>"
                                    data-song-title="<?php echo esc_attr(get_the_title()); ?>"
                                    data-current-genre="<?php echo esc_attr(wp_json_encode(wp_get_post_terms($song_id, 'genre', ['fields' => 'ids']) ?: [])); ?>"
                                    data-current-mood="<?php echo esc_attr(wp_json_encode(wp_get_post_terms($song_id, 'mood', ['fields' => 'ids']) ?: [])); ?>">
                                Quick Tag
                            </button>
                            <a href="<?php echo esc_url($edit_link); ?>" class="button button-small">Edit</a>
                        </td>
                    </tr>
                <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>

            <?php
            // Pagination
            $total_pages = (int) $query->max_num_pages;
            if ($total_pages > 1):
                $page_links = paginate_links([
                    'base'      => add_query_arg(['filter' => $filter, 'paged' => '%#%'], $base_url),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'type'      => 'plain',
                ]);
                ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages" style="margin: 15px 0;">
                        <?php echo $page_links; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php
    // Prepare all existing genre/mood terms for the wizard picker.
    $all_genres_raw = get_terms(['taxonomy' => 'genre', 'hide_empty' => false, 'orderby' => 'name']);
    $all_moods_raw  = get_terms(['taxonomy' => 'mood',  'hide_empty' => false, 'orderby' => 'name']);
    $all_genres = [];
    if (!is_wp_error($all_genres_raw)) {
        foreach ($all_genres_raw as $t) {
            $all_genres[] = ['id' => (int) $t->term_id, 'name' => $t->name];
        }
    }
    $all_moods = [];
    if (!is_wp_error($all_moods_raw)) {
        foreach ($all_moods_raw as $t) {
            $all_moods[] = ['id' => (int) $t->term_id, 'name' => $t->name];
        }
    }
    ?>

    <!-- Quick Tag Wizard Modal -->
    <div id="fml-tag-wizard" class="fml-tag-wizard" style="display:none;" aria-hidden="true">
        <div class="fml-tag-wizard__backdrop"></div>
        <div class="fml-tag-wizard__dialog" role="dialog" aria-labelledby="fml-tag-wizard-title">
            <div class="fml-tag-wizard__header">
                <h2 id="fml-tag-wizard-title">Quick Tag</h2>
                <button type="button" class="fml-tag-wizard__close" aria-label="Close">&times;</button>
            </div>

            <div class="fml-tag-wizard__body">
                <div class="fml-tag-wizard__song">
                    <strong class="fml-tag-wizard__song-title">—</strong>
                    <span class="fml-tag-wizard__song-id" style="color:#888; font-size:11px;"></span>
                </div>

                <fieldset class="fml-tag-wizard__fieldset">
                    <legend><span class="dashicons dashicons-format-audio"></span> Genre</legend>
                    <div class="fml-tag-wizard__chips" data-tax="genre"></div>
                    <input type="text" class="fml-tag-wizard__new" data-tax="genre"
                           placeholder="Add new genre(s), comma-separated">
                </fieldset>

                <fieldset class="fml-tag-wizard__fieldset">
                    <legend><span class="dashicons dashicons-heart"></span> Mood</legend>
                    <div class="fml-tag-wizard__chips" data-tax="mood"></div>
                    <input type="text" class="fml-tag-wizard__new" data-tax="mood"
                           placeholder="Add new mood(s), comma-separated">
                </fieldset>

                <div class="fml-tag-wizard__status" aria-live="polite"></div>
            </div>

            <div class="fml-tag-wizard__footer">
                <button type="button" class="button fml-tag-wizard__cancel">Cancel</button>
                <button type="button" class="button button-secondary fml-tag-wizard__save">Save</button>
                <button type="button" class="button button-primary fml-tag-wizard__save-next">Save &amp; Next &rarr;</button>
            </div>
        </div>
    </div>

    <style>
        .fml-tag-wizard { position: fixed; inset: 0; z-index: 160000; }
        .fml-tag-wizard__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.55); }
        .fml-tag-wizard__dialog {
            position: relative; max-width: 640px; margin: 8vh auto 0; background: #fff;
            border-radius: 6px; box-shadow: 0 12px 40px rgba(0,0,0,0.35); overflow: hidden;
            display: flex; flex-direction: column; max-height: 84vh;
        }
        .fml-tag-wizard__header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px; border-bottom: 1px solid #e0e0e0; background: #f6f7f7;
        }
        .fml-tag-wizard__header h2 { margin: 0; font-size: 17px; }
        .fml-tag-wizard__close {
            background: none; border: 0; font-size: 26px; line-height: 1;
            cursor: pointer; color: #666;
        }
        .fml-tag-wizard__close:hover { color: #000; }
        .fml-tag-wizard__body { padding: 18px 20px; overflow-y: auto; }
        .fml-tag-wizard__song { margin-bottom: 14px; }
        .fml-tag-wizard__song-title { font-size: 15px; }
        .fml-tag-wizard__fieldset {
            border: 1px solid #dcdcde; border-radius: 4px; padding: 12px 14px 14px;
            margin: 0 0 14px;
        }
        .fml-tag-wizard__fieldset legend {
            padding: 0 6px; font-weight: 600; color: #1d2327;
        }
        .fml-tag-wizard__fieldset legend .dashicons {
            vertical-align: middle; font-size: 16px; width: 16px; height: 16px;
        }
        .fml-tag-wizard__chips {
            display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px;
            max-height: 180px; overflow-y: auto;
        }
        .fml-tag-wizard__chip {
            display: inline-block; padding: 4px 10px; border-radius: 999px;
            border: 1px solid #c3c4c7; background: #fff; color: #1d2327;
            cursor: pointer; font-size: 12px; user-select: none; transition: all .08s;
        }
        .fml-tag-wizard__chip:hover { border-color: #2271b1; color: #2271b1; }
        .fml-tag-wizard__chip.is-selected {
            background: #2271b1; border-color: #2271b1; color: #fff;
        }
        .fml-tag-wizard__new {
            width: 100%; padding: 6px 10px;
        }
        .fml-tag-wizard__status { min-height: 18px; font-size: 12px; color: #646970; }
        .fml-tag-wizard__status.is-error { color: #d63638; }
        .fml-tag-wizard__status.is-success { color: #00a32a; }
        .fml-tag-wizard__footer {
            display: flex; justify-content: flex-end; gap: 8px;
            padding: 12px 20px; border-top: 1px solid #e0e0e0; background: #f6f7f7;
        }
        .fml-tag-wizard.is-busy .fml-tag-wizard__footer button { opacity: 0.6; pointer-events: none; }
        tr.fml-row-flash { animation: fmlRowFlash 1.2s ease-out; }
        @keyframes fmlRowFlash {
            0%   { background: #c5e1a5; }
            100% { background: transparent; }
        }
    </style>

    <script>
    (function() {
        var ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        var nonce = <?php echo wp_json_encode(wp_create_nonce('fml_tag_coverage')); ?>;
        var ALL_GENRES = <?php echo wp_json_encode($all_genres); ?>;
        var ALL_MOODS  = <?php echo wp_json_encode($all_moods); ?>;
        var CURRENT_FILTER = <?php echo wp_json_encode($filter); ?>;

        var modal = document.getElementById('fml-tag-wizard');
        if (!modal) return;

        var els = {
            title:   modal.querySelector('.fml-tag-wizard__song-title'),
            idLabel: modal.querySelector('.fml-tag-wizard__song-id'),
            genreChips: modal.querySelector('.fml-tag-wizard__chips[data-tax="genre"]'),
            moodChips:  modal.querySelector('.fml-tag-wizard__chips[data-tax="mood"]'),
            newGenre: modal.querySelector('.fml-tag-wizard__new[data-tax="genre"]'),
            newMood:  modal.querySelector('.fml-tag-wizard__new[data-tax="mood"]'),
            status:  modal.querySelector('.fml-tag-wizard__status'),
            save:    modal.querySelector('.fml-tag-wizard__save'),
            saveNext: modal.querySelector('.fml-tag-wizard__save-next'),
            cancel:  modal.querySelector('.fml-tag-wizard__cancel'),
            closeX:  modal.querySelector('.fml-tag-wizard__close'),
            backdrop: modal.querySelector('.fml-tag-wizard__backdrop'),
        };

        var state = {
            songId: 0,
            songTitle: '',
            selected: { genre: {}, mood: {} }, // keyed by id or name
        };

        function renderChips(container, allTerms, selectedMap) {
            container.innerHTML = '';
            allTerms.forEach(function(term) {
                var chip = document.createElement('span');
                chip.className = 'fml-tag-wizard__chip';
                chip.textContent = term.name;
                chip.dataset.termId = term.id;
                if (selectedMap[term.id]) {
                    chip.classList.add('is-selected');
                }
                chip.addEventListener('click', function() {
                    if (selectedMap[term.id]) {
                        delete selectedMap[term.id];
                        chip.classList.remove('is-selected');
                    } else {
                        selectedMap[term.id] = true;
                        chip.classList.add('is-selected');
                    }
                });
                container.appendChild(chip);
            });
        }

        function openModal(payload) {
            state.songId = payload.id;
            state.songTitle = payload.title || '(no title)';
            state.selected.genre = {};
            state.selected.mood = {};

            (payload.current_genre || []).forEach(function(id) { state.selected.genre[id] = true; });
            (payload.current_mood  || []).forEach(function(id) { state.selected.mood[id]  = true; });

            els.title.textContent = state.songTitle;
            els.idLabel.textContent = '  (ID: ' + state.songId + ')';
            els.newGenre.value = '';
            els.newMood.value  = '';
            els.status.textContent = '';
            els.status.className = 'fml-tag-wizard__status';

            renderChips(els.genreChips, ALL_GENRES, state.selected.genre);
            renderChips(els.moodChips,  ALL_MOODS,  state.selected.mood);

            modal.style.display = 'block';
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('is-busy');
        }

        function collectTerms(tax) {
            var ids = Object.keys(state.selected[tax]).map(function(k) { return parseInt(k, 10); })
                        .filter(function(n) { return !isNaN(n); });
            var newInput = (tax === 'genre' ? els.newGenre : els.newMood).value || '';
            var newNames = newInput.split(',').map(function(s) { return s.trim(); })
                            .filter(function(s) { return s.length > 0; });
            return ids.concat(newNames);
        }

        function save(advance) {
            var genres = collectTerms('genre');
            var moods  = collectTerms('mood');

            els.status.textContent = 'Saving...';
            els.status.className = 'fml-tag-wizard__status';
            modal.classList.add('is-busy');

            var body = new URLSearchParams();
            body.append('action', 'fml_tag_coverage_save');
            body.append('nonce', nonce);
            body.append('song_id', state.songId);
            body.append('filter', CURRENT_FILTER);
            genres.forEach(function(g) { body.append('genres[]', g); });
            moods.forEach(function(m) { body.append('moods[]', m); });

            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                modal.classList.remove('is-busy');
                if (!json || !json.success) {
                    els.status.textContent = (json && json.data && json.data.message) || 'Save failed';
                    els.status.className = 'fml-tag-wizard__status is-error';
                    return;
                }

                updateRowAfterSave(json.data);
                updateCounts(json.data.counts);

                if (advance && json.data.next) {
                    openModal(json.data.next);
                    els.status.textContent = 'Saved. Loaded next song.';
                    els.status.className = 'fml-tag-wizard__status is-success';
                } else if (advance && !json.data.next) {
                    closeModal();
                    alert('All done! No more songs matching this filter.');
                } else {
                    els.status.textContent = 'Saved.';
                    els.status.className = 'fml-tag-wizard__status is-success';
                    setTimeout(closeModal, 400);
                }
            })
            .catch(function(err) {
                modal.classList.remove('is-busy');
                els.status.textContent = 'Network error: ' + err.message;
                els.status.className = 'fml-tag-wizard__status is-error';
            });
        }

        function updateRowAfterSave(data) {
            var row = document.querySelector('tr[data-song-row="' + data.song_id + '"]');
            if (!row) return;

            // If the row no longer matches the current filter, remove it.
            var stillMissingGenre = data.still_missing.genre;
            var stillMissingMood  = data.still_missing.mood;
            var filterStillMatches = (function() {
                switch (CURRENT_FILTER) {
                    case 'missing_genre': return stillMissingGenre;
                    case 'missing_mood':  return stillMissingMood;
                    case 'missing_both':  return stillMissingGenre && stillMissingMood;
                    case 'missing_any':
                    default:              return stillMissingGenre || stillMissingMood;
                }
            })();

            if (!filterStillMatches) {
                row.classList.add('fml-row-flash');
                setTimeout(function() {
                    row.parentNode && row.parentNode.removeChild(row);
                }, 600);
                return;
            }

            // Otherwise just refresh the cells in place.
            var genreNames = data.saved.genres.map(function(t) { return t.name; });
            var moodNames  = data.saved.moods.map(function(t) { return t.name; });
            var cells = row.querySelectorAll('td');
            if (cells.length >= 4) {
                cells[2].innerHTML = genreNames.length
                    ? escapeHtml(genreNames.join(', '))
                    : '<span style="color:#d63638;">&mdash; missing &mdash;</span>';
                cells[3].innerHTML = moodNames.length
                    ? escapeHtml(moodNames.join(', '))
                    : '<span style="color:#d63638;">&mdash; missing &mdash;</span>';
            }
            var btn = row.querySelector('.fml-quick-tag');
            if (btn) {
                btn.dataset.currentGenre = JSON.stringify(data.saved.genres.map(function(t) { return t.id; }));
                btn.dataset.currentMood  = JSON.stringify(data.saved.moods.map(function(t) { return t.id; }));
            }

            // Merge any new terms (created via free text) into the local caches
            // so subsequent modal opens can see them.
            mergeIntoCache(ALL_GENRES, data.saved.genres);
            mergeIntoCache(ALL_MOODS,  data.saved.moods);

            row.classList.add('fml-row-flash');
        }

        function mergeIntoCache(cache, terms) {
            terms.forEach(function(t) {
                var exists = cache.some(function(c) { return c.id === t.id; });
                if (!exists) cache.push({ id: t.id, name: t.name });
            });
        }

        function updateCounts(counts) {
            if (!counts) return;
            // Update tab counts
            var tabMap = {
                'missing_any':   counts.missing_any,
                'missing_both':  counts.missing_both,
                'missing_genre': counts.missing_genre,
                'missing_mood':  counts.missing_mood,
            };
            document.querySelectorAll('.nav-tab').forEach(function(tab) {
                var href = tab.getAttribute('href') || '';
                Object.keys(tabMap).forEach(function(key) {
                    if (href.indexOf('filter=' + key) !== -1) {
                        var span = tab.querySelector('span');
                        if (span) span.textContent = '(' + new Intl.NumberFormat().format(tabMap[key]) + ')';
                    }
                });
            });
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function(m) {
                return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m];
            });
        }

        // Wire up row buttons
        document.querySelectorAll('.fml-quick-tag').forEach(function(btn) {
            // Attach a data-song-row to the parent tr for easy targeting.
            var tr = btn.closest('tr');
            if (tr) tr.setAttribute('data-song-row', btn.dataset.songId);

            btn.addEventListener('click', function() {
                var currentGenre = [];
                var currentMood = [];
                try { currentGenre = JSON.parse(btn.dataset.currentGenre || '[]'); } catch(e) {}
                try { currentMood  = JSON.parse(btn.dataset.currentMood  || '[]'); } catch(e) {}
                openModal({
                    id: parseInt(btn.dataset.songId, 10),
                    title: btn.dataset.songTitle,
                    current_genre: currentGenre,
                    current_mood: currentMood,
                });
            });
        });

        els.save.addEventListener('click', function() { save(false); });
        els.saveNext.addEventListener('click', function() { save(true); });
        els.cancel.addEventListener('click', closeModal);
        els.closeX.addEventListener('click', closeModal);
        els.backdrop.addEventListener('click', closeModal);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'block') closeModal();
        });
    })();
    </script>
    <?php
}
