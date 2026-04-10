<?php
/**
 * My Artists/Music Template - Enhanced Dark Mode Version
 * Displays user's artists and their albums
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();

// Get all artists for the logged in user
$paramsArtist = array(
    'where' => "t.post_author = '" . $current_user->ID . "' AND t.post_status = 'Publish'",
    "orderby" => "t.post_date DESC"
);

$artists = pods('artist', $paramsArtist);
$total_artists = $artists->total();

// Count stats
$total_albums = 0;
$total_songs = 0;
$total_licensed = 0;
?>

<style>
/* My Artists/Music Page - Dark Mode Styles */
.fml-artists-container {
    color: #e2e8f0;
}

/* Stats Cards */
.fml-artist-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.fml-stat-card {
    background: #252540;
    border: 1px solid #404060;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    display: block;
    text-decoration: none !important;
    color: inherit !important;
    transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
a.fml-stat-card:hover {
    transform: translateY(-2px);
    border-color: #E7565A;
    box-shadow: 0 4px 15px rgba(231, 86, 90, 0.2);
}

.fml-stat-card .stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #E7565A;
    display: block;
}

.fml-stat-card .stat-label {
    font-size: 0.85rem;
    color: #a0aec0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.fml-stat-card.albums .stat-number {
    color: #63b3ed;
}

.fml-stat-card.songs .stat-number {
    color: #48bb78;
}

/* Artist Card */
.fml-artist-card {
    background: #252540;
    border: 1px solid #404060;
    border-radius: 12px;
    margin-bottom: 25px;
    overflow: hidden;
}

.fml-artist-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: linear-gradient(135deg, #1e1e32 0%, #252540 100%);
    border-bottom: 1px solid #404060;
}

.fml-artist-image {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #E7565A;
    box-shadow: 0 4px 15px rgba(231, 86, 90, 0.3);
}

.fml-artist-image-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #404060;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #E7565A;
}

.fml-artist-image-placeholder i {
    font-size: 2.5rem;
    color: #718096;
}

.fml-artist-info {
    flex: 1;
}

.fml-artist-name {
    margin: 0 0 10px 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #e2e8f0;
}

.fml-artist-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Action Buttons */
.fml-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none !important;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.fml-action-btn.view {
    background: rgba(99, 179, 237, 0.2);
    color: #63b3ed !important;
    border: 1px solid rgba(99, 179, 237, 0.3);
}

.fml-action-btn.view:hover {
    background: #63b3ed;
    color: white !important;
    transform: translateY(-2px);
}

.fml-action-btn.edit {
    background: rgba(246, 224, 94, 0.2);
    color: #f6e05e !important;
    border: 1px solid rgba(246, 224, 94, 0.3);
}

.fml-action-btn.edit:hover {
    background: #f6e05e;
    color: #1a1a2e !important;
    transform: translateY(-2px);
}

.fml-action-btn.upload {
    background: rgba(72, 187, 120, 0.2);
    color: #48bb78 !important;
    border: 1px solid rgba(72, 187, 120, 0.3);
}

.fml-action-btn.upload:hover {
    background: #48bb78;
    color: white !important;
    transform: translateY(-2px);
}

.fml-action-btn.create {
    background: #E7565A;
    color: white !important;
}

.fml-action-btn.create:hover {
    background: #ff6b6f;
    transform: translateY(-2px);
}

/* Albums Section */
.fml-albums-section {
    padding: 25px;
}

.fml-albums-title {
    margin: 0 0 20px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #a0aec0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.fml-albums-title i {
    color: #E7565A;
}

/* Albums Table */
.fml-albums-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.fml-albums-table thead th {
    background: #1a1a2e;
    color: #a0aec0;
    padding: 12px 15px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    border-bottom: 1px solid #404060;
}

.fml-albums-table thead th:first-child {
    border-radius: 8px 0 0 0;
}

.fml-albums-table thead th:last-child {
    border-radius: 0 8px 0 0;
}

.fml-albums-table tbody tr {
    transition: background 0.2s ease;
}

.fml-albums-table tbody tr:hover {
    background: #1e1e32;
}

.fml-albums-table tbody td {
    padding: 15px;
    border-bottom: 1px solid #2d2d44;
    vertical-align: middle;
}

.fml-albums-table tbody tr:last-child td {
    border-bottom: none;
}

/* Album Row */
.fml-album-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.fml-album-cover {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.fml-album-cover-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background: #404060;
    display: flex;
    align-items: center;
    justify-content: center;
}

.fml-album-cover-placeholder i {
    font-size: 1.5rem;
    color: #718096;
}

.fml-album-title {
    font-weight: 600;
    color: #e2e8f0;
    margin: 0;
}

.fml-album-title a {
    color: #e2e8f0 !important;
    text-decoration: none;
}

.fml-album-title a:hover {
    color: #E7565A !important;
}

.fml-release-date {
    color: #a0aec0;
    font-size: 0.9rem;
}

/* No Albums State */
.fml-no-albums {
    text-align: center;
    padding: 30px;
    color: #718096;
}

.fml-no-albums i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

/* Upload Album Button */
.fml-upload-section {
    padding: 0 25px 25px 25px;
}

/* Empty State */
.fml-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #252540;
    border-radius: 12px;
    border: 1px solid #404060;
    margin-bottom: 30px;
}

.fml-empty-state i {
    font-size: 4rem;
    color: #404060;
    margin-bottom: 20px;
}

.fml-empty-state h3 {
    color: #e2e8f0;
    margin-bottom: 10px;
}

.fml-empty-state p {
    color: #a0aec0;
    margin-bottom: 20px;
}

/* Create Artist Section */
.fml-create-artist-section {
    text-align: center;
    padding: 30px;
    background: #252540;
    border: 2px dashed #404060;
    border-radius: 12px;
    margin-top: 20px;
}

.fml-create-artist-section p {
    color: #a0aec0;
    margin-bottom: 15px;
}

/* Song Count Badge */
.fml-song-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: rgba(72, 187, 120, 0.15);
    border-radius: 20px;
    font-size: 0.85rem;
    color: #48bb78;
}

.fml-stat-card.licensed .stat-number {
    color: #f6ad55;
}

/* Licensing Column */
.fml-licensing-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.fml-licensing-badge.commercial {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
}

.fml-licensing-badge.ccby-only {
    background: rgba(160, 174, 192, 0.15);
    color: #a0aec0;
}

/* Licensing Editor */
.fml-licensing-editor {
    display: none;
    background: #1a1a2e;
    border: 1px solid #6366f1;
    border-radius: 10px;
    padding: 20px;
    margin-top: 10px;
}

.fml-licensing-editor.active {
    display: block;
}

.fml-licensing-editor label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    color: #e2e8f0;
    margin-bottom: 10px;
}

.fml-licensing-editor input[type="checkbox"] {
    accent-color: #6366f1;
    width: 18px;
    height: 18px;
}

.fml-licensing-editor .fml-price-row {
    display: none;
    align-items: center;
    gap: 8px;
    margin: 10px 0 0 26px;
}

.fml-licensing-editor .fml-price-row.visible {
    display: flex;
}

.fml-licensing-editor .fml-price-row input[type="number"] {
    width: 100px;
    padding: 6px 10px;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 6px;
    background: rgba(0,0,0,0.4);
    color: #fff;
    font-size: 14px;
}

.fml-licensing-editor .fml-price-row input[type="number"]:focus {
    border-color: #6366f1;
    outline: none;
}

.fml-licensing-editor .fml-split-info {
    font-size: 12px;
    color: rgba(255,255,255,0.5);
    margin-left: 26px;
    margin-top: 4px;
}

.fml-licensing-editor .fml-editor-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

.fml-licensing-editor .fml-editor-actions button {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.fml-licensing-editor .fml-save-licensing {
    background: #6366f1;
    color: white;
}

.fml-licensing-editor .fml-save-licensing:hover {
    background: #818cf8;
}

.fml-licensing-editor .fml-cancel-licensing {
    background: rgba(255,255,255,0.1);
    color: #a0aec0;
}

.fml-licensing-editor .fml-cancel-licensing:hover {
    background: rgba(255,255,255,0.2);
}

.fml-licensing-editor .fml-save-status {
    font-size: 12px;
    margin-left: 10px;
    line-height: 32px;
}

.fml-action-btn.licensing {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8 !important;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.fml-action-btn.licensing:hover {
    background: #6366f1;
    color: white !important;
    transform: translateY(-2px);
}

/* Albums table scroll wrapper */
.fml-albums-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Responsive */
@media (max-width: 768px) {
    .fml-artists-container {
        margin-left: -15px;
        margin-right: -15px;
    }

    .fml-artist-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding: 0 10px;
        margin-bottom: 20px;
    }

    .fml-stat-card {
        padding: 14px 10px;
        border-radius: 8px;
    }

    .fml-stat-card .stat-number {
        font-size: 1.5rem;
    }

    .fml-artist-card {
        border-radius: 0;
        border-left: none;
        border-right: none;
        margin-bottom: 15px;
    }

    .fml-artist-header {
        flex-direction: column;
        text-align: center;
        padding: 20px 15px;
        gap: 12px;
    }

    .fml-artist-image,
    .fml-artist-image-placeholder {
        width: 70px;
        height: 70px;
    }

    .fml-artist-name {
        font-size: 1.2rem;
    }

    .fml-artist-meta {
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px !important;
    }

    .fml-artist-actions {
        justify-content: center;
    }

    .fml-action-btn span {
        display: none;
    }

    .fml-action-btn {
        padding: 10px;
    }

    .fml-albums-section {
        padding: 15px 0;
    }

    .fml-albums-title {
        padding: 0 15px;
        font-size: 1rem;
        margin-bottom: 12px;
    }

    .fml-albums-scroll {
        margin: 0;
        padding-bottom: 4px;
    }

    .fml-albums-table {
        min-width: 580px;
    }

    .fml-albums-table thead th {
        padding: 10px 12px;
        font-size: 0.7rem;
        white-space: nowrap;
    }

    .fml-albums-table tbody td {
        padding: 12px;
    }

    .fml-album-cover,
    .fml-album-cover-placeholder {
        width: 45px;
        height: 45px;
        border-radius: 6px;
    }

    .fml-album-info {
        gap: 10px;
    }

    .fml-album-title {
        font-size: 0.9rem;
    }

    .fml-licensing-row td {
        padding: 0 10px 10px !important;
    }

    .fml-licensing-editor {
        padding: 15px;
    }

    .fml-upload-section {
        padding: 0 15px 15px;
    }

    .fml-no-albums {
        padding: 20px 15px;
    }

    .fml-empty-state {
        border-radius: 0;
        padding: 40px 15px;
    }

    .fml-create-artist-section {
        margin: 0;
        border-radius: 0;
        border-left: none;
        border-right: none;
    }
}

@media (max-width: 400px) {
    .fml-artist-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 6px;
        padding: 0 6px;
    }

    .fml-stat-card {
        padding: 10px 6px;
    }

    .fml-stat-card .stat-number {
        font-size: 1.3rem;
    }

    .fml-stat-card .stat-label {
        font-size: 0.7rem;
    }

    .fml-song-count-badge {
        font-size: 0.75rem;
        padding: 3px 8px;
    }
}
</style>

<div class="fml-artists-container">
    <?php if ($total_artists > 0): ?>

    <?php
    // First pass to count albums and songs
    $artists_data = [];
    while ($artists->fetch()) {
        $artist_id = $artists->field('ID');
        $albums = $artists->field('albums');
        $album_count = is_array($albums) ? count($albums) : 0;
        $total_albums += $album_count;

        // Count songs in albums and collect song IDs for license counting
        $song_count = 0;
        $all_song_ids = [];
        if (!empty($albums) && is_array($albums)) {
            foreach ($albums as $album) {
                $album_pod = pods('album', $album['ID']);
                $songs = $album_pod->field('songs');
                if (!empty($songs) && is_array($songs)) {
                    $song_count += count($songs);
                    foreach ($songs as $song) {
                        $all_song_ids[] = is_array($song) ? $song['ID'] : intval($song);
                    }
                }
            }
        }
        $total_songs += $song_count;

        // Count licenses for this artist's songs
        $artist_licensed = 0;
        if (!empty($all_song_ids)) {
            $song_ids_str = implode(',', array_map('intval', $all_song_ids));
            $license_params = [
                'where' => "song.ID IN ({$song_ids_str})",
                'limit' => -1,
            ];
            $license_pods = pods('license', $license_params);
            $artist_licensed = $license_pods->total_found();
        }
        $total_licensed += $artist_licensed;

        $artists_data[] = [
            'id' => $artist_id,
            'name' => $artists->field('post_title'),
            'image' => $artists->display('profile_image'),
            'albums' => $albums,
            'album_count' => $album_count,
            'song_count' => $song_count,
            'licensed_count' => $artist_licensed
        ];
    }
    ?>

    <!-- Stats Cards -->
    <div class="fml-artist-stats">
        <a class="fml-stat-card" href="/account/artists" title="Your artists">
            <span class="stat-number"><?php echo $total_artists; ?></span>
            <span class="stat-label">Artists</span>
        </a>
        <a class="fml-stat-card albums" href="/account/artists#albums" title="Your albums">
            <span class="stat-number"><?php echo $total_albums; ?></span>
            <span class="stat-label">Albums</span>
        </a>
        <a class="fml-stat-card songs" href="/account/artists#albums" title="Your songs">
            <span class="stat-number"><?php echo $total_songs; ?></span>
            <span class="stat-label">Songs</span>
        </a>
        <a class="fml-stat-card licensed" href="/account/licenses" title="Times your songs were licensed by others">
            <span class="stat-number"><?php echo $total_licensed; ?></span>
            <span class="stat-label">Times Licensed</span>
        </a>
    </div>

    <!-- Artist Cards -->
    <?php foreach ($artists_data as $artist): ?>
    <div class="fml-artist-card">
        <div class="fml-artist-header">
            <?php if (!empty($artist['image'])): ?>
                <img src="<?php echo esc_url($artist['image']); ?>" alt="<?php echo esc_attr($artist['name']); ?>" class="fml-artist-image">
            <?php else: ?>
                <div class="fml-artist-image-placeholder">
                    <i class="fas fa-user-music"></i>
                </div>
            <?php endif; ?>

            <div class="fml-artist-info">
                <h2 class="fml-artist-name"><?php echo esc_html($artist['name']); ?></h2>
                <div class="fml-artist-meta" style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <span class="fml-song-count-badge">
                        <i class="fas fa-compact-disc"></i> <?php echo $artist['album_count']; ?> album<?php echo $artist['album_count'] !== 1 ? 's' : ''; ?>
                    </span>
                    <span class="fml-song-count-badge">
                        <i class="fas fa-music"></i> <?php echo $artist['song_count']; ?> song<?php echo $artist['song_count'] !== 1 ? 's' : ''; ?>
                    </span>
                    <span class="fml-song-count-badge" style="background: rgba(246,173,85,0.15); color: #f6ad55;">
                        <i class="fas fa-file-contract"></i> <?php echo $artist['licensed_count']; ?> licensed
                    </span>
                </div>
                <div class="fml-artist-actions">
                    <a href="<?php echo esc_url(get_permalink($artist['id'])); ?>" target="_blank" class="fml-action-btn view">
                        <i class="fas fa-eye"></i><span>View Page</span>
                    </a>
                    <a href="/account/artist-edit/?artist_edit_id=<?php echo $artist['id']; ?>" class="fml-action-btn edit">
                        <i class="fas fa-edit"></i><span>Edit Artist</span>
                    </a>
                    <a href="/my-account/album-upload-add-songs/?artist_id=<?php echo $artist['id']; ?>" class="fml-action-btn upload">
                        <i class="fas fa-upload"></i><span>Upload Music</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="fml-albums-section">
            <?php if (!empty($artist['albums']) && is_array($artist['albums'])): ?>
                <h3 class="fml-albums-title"><i class="fas fa-compact-disc"></i> Albums & Singles</h3>
                <div class="fml-albums-scroll">
                <table class="fml-albums-table">
                    <thead>
                        <tr>
                            <th>Album</th>
                            <th>Release Date</th>
                            <th>Songs</th>
                            <th>Licensing</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($artist['albums'] as $album):
                        $album_pod = pods('album', $album['ID']);
                        $cover_art = $album_pod->display('cover_art');
                        $release_date = $album_pod->display('release_date');
                        $album_songs = $album_pod->field('songs');
                        $album_song_count = is_array($album_songs) ? count($album_songs) : 0;
                        $album_commercial = get_post_meta($album['ID'], '_commercial_licensing', true);
                        $album_ccby_disabled = get_post_meta($album['ID'], '_ccby_disabled', true);
                        $album_ccby = !$album_ccby_disabled;
                        $album_price = get_post_meta($album['ID'], '_commercial_price', true);
                        $album_price_display = ($album_price !== '' && $album_price !== false) ? intval($album_price) : intval(get_option('fml_non_exclusive_license_price', 4900));
                    ?>
                        <tr>
                            <td>
                                <div class="fml-album-info">
                                    <?php if (!empty($cover_art)): ?>
                                        <img src="<?php echo esc_url($cover_art); ?>" alt="" class="fml-album-cover">
                                    <?php else: ?>
                                        <div class="fml-album-cover-placeholder">
                                            <i class="fas fa-compact-disc"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h4 class="fml-album-title">
                                        <a href="<?php echo esc_url(get_permalink($album['ID'])); ?>">
                                            <?php echo esc_html($album['post_title']); ?>
                                        </a>
                                    </h4>
                                </div>
                            </td>
                            <td>
                                <span class="fml-release-date">
                                    <?php echo !empty($release_date) ? esc_html($release_date) : '-'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="fml-song-count-badge">
                                    <i class="fas fa-music"></i> <?php echo $album_song_count; ?>
                                </span>
                            </td>
                            <td>
                                <span class="fml-licensing-badge <?php echo $album_commercial ? 'commercial' : 'ccby-only'; ?>" id="fml-badge-<?php echo $album['ID']; ?>">
                                    <?php if ($album_ccby && $album_commercial): ?>
                                        <i class="fab fa-creative-commons"></i> CC-BY + <i class="fas fa-dollar-sign"></i> $<?php echo number_format($album_price_display / 100, 2); ?>
                                    <?php elseif ($album_commercial): ?>
                                        <i class="fas fa-dollar-sign"></i> $<?php echo number_format($album_price_display / 100, 2); ?> only
                                    <?php else: ?>
                                        <i class="fab fa-creative-commons"></i> CC-BY Only
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="<?php echo esc_url(get_permalink($album['ID'])); ?>" target="_blank" class="fml-action-btn view" title="View Album">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="fml-action-btn licensing fml-toggle-licensing" data-album-id="<?php echo $album['ID']; ?>" title="Edit Licensing">
                                    <i class="fas fa-sliders-h"></i>
                                </button>
                            </td>
                        </tr>
                        <tr class="fml-licensing-row" id="fml-licensing-row-<?php echo $album['ID']; ?>" style="display: none;">
                            <td colspan="5" style="padding: 0 15px 15px;">
                                <div class="fml-licensing-editor active" id="fml-editor-<?php echo $album['ID']; ?>">
                                    <strong style="color: #e2e8f0; font-size: 14px;"><i class="fas fa-sliders-h" style="color: #6366f1;"></i> Licensing for <?php echo esc_html($album['post_title']); ?></strong>
                                    <div style="margin-top: 12px;">
                                        <label>
                                            <input type="checkbox" class="fml-ccby-toggle" data-album-id="<?php echo $album['ID']; ?>"
                                                   <?php checked($album_ccby); ?> style="accent-color: #48bb78;">
                                            CC-BY 4.0 (Free, MP3)
                                        </label>
                                        <label>
                                            <input type="checkbox" class="fml-commercial-toggle" data-album-id="<?php echo $album['ID']; ?>"
                                                   <?php checked($album_commercial); ?>>
                                            Commercial Sync (Paid, WAV) — film, TV, ads, games
                                        </label>
                                        <div class="fml-licensing-warning" id="fml-warning-<?php echo $album['ID']; ?>" style="display: none; font-size: 12px; color: #fc8181; margin: 4px 0 0 26px;">
                                            At least one license type must be enabled.
                                        </div>
                                        <div style="font-size: 12px; color: rgba(255,255,255,0.4); margin: 4px 0 0 26px;">
                                            Custom/exclusive deals are handled separately by the Awen team.
                                        </div>
                                        <div class="fml-price-row <?php echo $album_commercial ? 'visible' : ''; ?>" id="fml-price-row-<?php echo $album['ID']; ?>">
                                            <span style="color: rgba(255,255,255,0.7);">$</span>
                                            <input type="number" class="fml-commercial-price" data-album-id="<?php echo $album['ID']; ?>"
                                                   value="<?php echo number_format($album_price_display / 100, 2, '.', ''); ?>"
                                                   min="1" step="0.01">
                                            <span style="color: #a0aec0; font-size: 13px;">per song</span>
                                        </div>
                                        <div class="fml-split-info" id="fml-split-info-<?php echo $album['ID']; ?>">
                                            You receive 70% ($<?php echo number_format($album_price_display * 0.7 / 100, 2); ?> per license at current price)
                                        </div>
                                    </div>
                                    <div class="fml-editor-actions">
                                        <button type="button" class="fml-save-licensing" data-album-id="<?php echo $album['ID']; ?>">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                        <button type="button" class="fml-cancel-licensing" data-album-id="<?php echo $album['ID']; ?>">
                                            Cancel
                                        </button>
                                        <span class="fml-save-status" id="fml-status-<?php echo $album['ID']; ?>"></span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div><!-- .fml-albums-scroll -->
            <?php else: ?>
                <div class="fml-no-albums">
                    <i class="fas fa-compact-disc"></i>
                    <p>No albums uploaded yet</p>
                    <a href="/my-account/album-upload-add-songs/?artist_id=<?php echo $artist['id']; ?>" class="fml-action-btn upload">
                        <i class="fas fa-upload"></i><span>Upload First Album</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>

    <div class="fml-empty-state">
        <i class="fas fa-user-music"></i>
        <h3>No Artists Yet</h3>
        <p>To upload your music, first create an artist profile.</p>
        <a href="/my-account/artist-registration" class="fml-action-btn create">
            <i class="fas fa-plus"></i> Create Artist
        </a>
    </div>

    <?php endif; ?>

    <?php if ($total_artists > 0 && $total_artists <= 1): ?>
    <div class="fml-create-artist-section">
        <p>Want to add another artist?</p>
        <a href="/my-account/artist-registration" class="fml-action-btn create">
            <i class="fas fa-plus"></i> Create Artist
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = '<?php echo wp_create_nonce('fml_album_licensing'); ?>';

    // Toggle licensing editor row
    document.querySelectorAll('.fml-toggle-licensing').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var albumId = this.dataset.albumId;
            var row = document.getElementById('fml-licensing-row-' + albumId);
            row.style.display = row.style.display === 'none' ? '' : 'none';
        });
    });

    // Cancel button
    document.querySelectorAll('.fml-cancel-licensing').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('fml-licensing-row-' + this.dataset.albumId).style.display = 'none';
        });
    });

    // Validate at least one license type is checked
    function validateLicenseTypes(albumId) {
        var ccby = document.querySelector('.fml-ccby-toggle[data-album-id="' + albumId + '"]').checked;
        var commercial = document.querySelector('.fml-commercial-toggle[data-album-id="' + albumId + '"]').checked;
        var warning = document.getElementById('fml-warning-' + albumId);
        var saveBtn = document.querySelector('.fml-save-licensing[data-album-id="' + albumId + '"]');
        if (!ccby && !commercial) {
            warning.style.display = 'block';
            saveBtn.disabled = true;
        } else {
            warning.style.display = 'none';
            saveBtn.disabled = false;
        }
    }

    // CC-BY toggle
    document.querySelectorAll('.fml-ccby-toggle').forEach(function(cb) {
        cb.addEventListener('change', function() {
            validateLicenseTypes(this.dataset.albumId);
        });
    });

    // Commercial toggle → show/hide price row + validate
    document.querySelectorAll('.fml-commercial-toggle').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var albumId = this.dataset.albumId;
            var priceRow = document.getElementById('fml-price-row-' + albumId);
            priceRow.classList.toggle('visible', this.checked);
            validateLicenseTypes(albumId);
        });
    });

    // Price input → update split info
    document.querySelectorAll('.fml-commercial-price').forEach(function(input) {
        input.addEventListener('input', function() {
            var albumId = this.dataset.albumId;
            var price = parseFloat(this.value) || 0;
            document.getElementById('fml-split-info-' + albumId).textContent =
                'You receive 70% ($' + (price * 0.7).toFixed(2) + ' per license at current price)';
        });
    });

    // Save licensing
    document.querySelectorAll('.fml-save-licensing').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var albumId = this.dataset.albumId;
            var statusEl = document.getElementById('fml-status-' + albumId);
            var ccbyEnabled = document.querySelector('.fml-ccby-toggle[data-album-id="' + albumId + '"]').checked;
            var commercialEnabled = document.querySelector('.fml-commercial-toggle[data-album-id="' + albumId + '"]').checked;
            var priceInput = document.querySelector('.fml-commercial-price[data-album-id="' + albumId + '"]');
            var price = priceInput ? priceInput.value : '49.00';

            statusEl.textContent = 'Saving...';
            statusEl.style.color = '#a0aec0';
            btn.disabled = true;

            var formData = new FormData();
            formData.append('action', 'fml_save_album_licensing');
            formData.append('nonce', nonce);
            formData.append('album_id', albumId);
            formData.append('ccby_enabled', ccbyEnabled ? '1' : '');
            formData.append('commercial_licensing', commercialEnabled ? '1' : '');
            formData.append('commercial_price', price);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    btn.disabled = false;
                    if (resp.success) {
                        statusEl.textContent = 'Saved!';
                        statusEl.style.color = '#48bb78';

                        // Update the badge
                        var badge = document.getElementById('fml-badge-' + albumId);
                        if (ccbyEnabled && commercialEnabled) {
                            badge.className = 'fml-licensing-badge commercial';
                            badge.innerHTML = '<i class="fab fa-creative-commons"></i> CC-BY + <i class="fas fa-dollar-sign"></i> $' + parseFloat(price).toFixed(2);
                        } else if (commercialEnabled) {
                            badge.className = 'fml-licensing-badge commercial';
                            badge.innerHTML = '<i class="fas fa-dollar-sign"></i> $' + parseFloat(price).toFixed(2) + ' only';
                        } else {
                            badge.className = 'fml-licensing-badge ccby-only';
                            badge.innerHTML = '<i class="fab fa-creative-commons"></i> CC-BY Only';
                        }

                        setTimeout(function() {
                            document.getElementById('fml-licensing-row-' + albumId).style.display = 'none';
                            statusEl.textContent = '';
                        }, 1200);
                    } else {
                        statusEl.textContent = resp.data || 'Error saving.';
                        statusEl.style.color = '#fc8181';
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    statusEl.textContent = 'Network error.';
                    statusEl.style.color = '#fc8181';
                });
        });
    });
})();
</script>
