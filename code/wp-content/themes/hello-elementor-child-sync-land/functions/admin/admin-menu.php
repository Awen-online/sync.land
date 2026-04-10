<?php
/**
 * Sync.Land Admin Menu
 * Creates a unified admin menu for all Sync.Land custom pages
 */

// Register the main Sync.Land admin menu
add_action('admin_menu', 'fml_register_admin_menu', 5);

function fml_register_admin_menu() {
    // Main menu item
    add_menu_page(
        'Sync.Land',           // Page title
        'Sync.Land',           // Menu title
        'manage_options',      // Capability
        'syncland',            // Menu slug
        'fml_admin_dashboard', // Callback function
        'dashicons-format-audio', // Icon
        30                     // Position
    );

    // Dashboard submenu (same as main menu)
    add_submenu_page(
        'syncland',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'syncland',
        'fml_admin_dashboard'
    );
}

/**
 * Admin Dashboard page
 */
function fml_admin_dashboard() {
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-format-audio" style="font-size: 30px; margin-right: 10px;"></span> Sync.Land Admin</h1>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">

            <div class="card" style="padding: 20px;">
                <h2><span class="dashicons dashicons-chart-area"></span> Generate Waveforms</h2>
                <p>Generate waveform visualizations for all songs. These are displayed on genre/mood pages.</p>
                <a href="<?php echo admin_url('admin.php?page=syncland-waveforms'); ?>" class="button button-primary">Generate Waveforms</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h2><span class="dashicons dashicons-money-alt"></span> Licensing</h2>
                <p>Manage song licensing, Stripe integration, and license generation settings.</p>
                <a href="<?php echo admin_url('admin.php?page=syncland-licensing'); ?>" class="button button-primary">Manage Licensing</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h2><span class="dashicons dashicons-admin-network"></span> NFT Monitor</h2>
                <p>Monitor NFT minting status, webhooks, and blockchain transactions.</p>
                <a href="<?php echo admin_url('admin.php?page=syncland-nft-monitor'); ?>" class="button button-primary">NFT Monitor</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h2><span class="dashicons dashicons-admin-keys"></span> API Keys</h2>
                <p>Manage API keys for external integrations and third-party access.</p>
                <a href="<?php echo admin_url('admin.php?page=syncland-api-keys'); ?>" class="button button-primary">Manage API Keys</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h2><span class="dashicons dashicons-chart-bar"></span> Analytics & Feedback</h2>
                <p>Track user behavior, music plays, conversion funnels, and collect survey feedback.</p>
                <a href="<?php echo admin_url('admin.php?page=syncland-analytics'); ?>" class="button button-primary">View Analytics</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h2><span class="dashicons dashicons-email-alt"></span> Email Settings</h2>
                <p>Configure SMTP, notification templates, and toggle email alerts for purchases, NFTs, and submissions.</p>
                <a href="<?php echo admin_url('admin.php?page=syncland-email'); ?>" class="button button-primary">Email Settings</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h2><span class="dashicons dashicons-megaphone"></span> Bulk Email</h2>
                <p>Send branded announcements and updates to all users or specific audiences.</p>
                <a href="<?php echo admin_url('admin.php?page=syncland-bulk-email'); ?>" class="button button-primary">Bulk Email</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h2><span class="dashicons dashicons-tag"></span> Tag Coverage</h2>
                <p>Find songs missing <code>genre</code> or <code>mood</code> tags and backfill them.</p>
                <a href="<?php echo admin_url('admin.php?page=syncland-tag-coverage'); ?>" class="button button-primary">Tag Coverage</a>
            </div>

        </div>

        <div style="margin-top: 30px;">
            <h2>Quick Stats</h2>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <?php
                $song_count = wp_count_posts('song')->publish;
                $artist_count = wp_count_posts('artist')->publish;
                $album_count = wp_count_posts('album')->publish;

                // Count songs with waveforms
                $waveform_count = 0;
                $songs_with_waveforms = get_posts([
                    'post_type' => 'song',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'meta_query' => [
                        [
                            'key' => '_waveform_peaks',
                            'compare' => 'EXISTS',
                        ],
                        [
                            'key' => '_waveform_peaks',
                            'value' => '',
                            'compare' => '!=',
                        ],
                    ],
                ]);
                $waveform_count = count($songs_with_waveforms);
                ?>
                <div class="card" style="padding: 15px; text-align: center; min-width: 120px;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format($song_count); ?></div>
                    <div style="color: #666;">Songs</div>
                </div>
                <div class="card" style="padding: 15px; text-align: center; min-width: 120px;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format($artist_count); ?></div>
                    <div style="color: #666;">Artists</div>
                </div>
                <div class="card" style="padding: 15px; text-align: center; min-width: 120px;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format($album_count); ?></div>
                    <div style="color: #666;">Albums</div>
                </div>
                <div class="card" style="padding: 15px; text-align: center; min-width: 120px;">
                    <div style="font-size: 32px; font-weight: bold; color: <?php echo $waveform_count < $song_count ? '#dba617' : '#00a32a'; ?>;">
                        <?php echo number_format($waveform_count); ?>/<?php echo number_format($song_count); ?>
                    </div>
                    <div style="color: #666;">Waveforms</div>
                </div>
                <?php
                // Tag coverage stat (fully tagged = has both genre and mood)
                $tag_count = 0;
                if (function_exists('fml_tag_coverage_counts')) {
                    $tag_stats = fml_tag_coverage_counts();
                    $tag_count = $tag_stats['fully_tagged'];
                }
                ?>
                <div class="card" style="padding: 15px; text-align: center; min-width: 120px;">
                    <div style="font-size: 32px; font-weight: bold; color: <?php echo $tag_count < $song_count ? '#dba617' : '#00a32a'; ?>;">
                        <?php echo number_format($tag_count); ?>/<?php echo number_format($song_count); ?>
                    </div>
                    <div style="color: #666;">Tagged</div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
