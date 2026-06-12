<?php
/**
 * [syncland_home_v2] — Conversion-tuned homepage.
 *
 * Sections (top-down):
 *   1. Hero — tagline + dual CTA (licensee primary, artist secondary)
 *   2. Trust strip — real licensee project names
 *   3. Stats bar — real artist/album/song counts
 *   4. How it works — 3 steps
 *   5. Featured artists grid — top-by-song-count
 *   6. Planet discovery — wraps the [hero_planet] without its own lede
 *   7. License tiers — CC-BY vs Commercial
 *   8. FAQ — common questions
 *
 * Data is pulled live from the DB so the page reflects real catalog state.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fml_syncland_home_v2_shortcode() {
    ob_start();
    ?>
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="/wp-content/uploads/elementor/google-fonts/fonts/redditsans-eyq3mafoxq1t_-etdn7ekqnre5y.woff2">
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="/wp-content/plugins/elementor/assets/lib/eicons/fonts/eicons.woff2?5.49.0">

    <div class="syncland-home-v2">

        <?php /* ============================================================
               SECTION 1 — HERO
               ============================================================ */ ?>
        <section class="sh-hero">
            <div class="sh-hero-inner">
                <img class="sh-hero-logo"
                     src="/wp-content/uploads/2026/03/sync.land-logo_transparent-1.png"
                     alt="Sync.Land"
                     width="2560" height="563"
                     fetchpriority="high">
                <p class="sh-eyebrow">Music Licensing for Storytellers</p>
                <h1 class="sh-headline">
                    Original music for film, games &amp; podcasts.<br>
                    <span class="sh-headline-accent">Licensed in one click.</span>
                </h1>
                <p class="sh-subhead">
                    Browse independent artists. Pay once, walk away with a license PDF
                    and an on-chain NFT receipt. Use the track forever.
                </p>
                <div class="sh-cta-row">
                    <a href="/songs" class="sh-cta sh-cta-primary">
                        <svg class="sh-cta-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M21 6.5l-9 5.25L3 6.5V18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.5zM12 9.75L20.5 5h-17L12 9.75z"/>
                        </svg>
                        <span class="sh-cta-main">Find Music to License</span>
                        <span class="sh-cta-tags">CC-BY · Commercial · NFT receipt</span>
                    </a>
                    <a href="/account" class="sh-cta sh-cta-secondary">
                        <svg class="sh-cta-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 3a3 3 0 0 0-3 3v6a3 3 0 1 0 6 0V6a3 3 0 0 0-3-3zm5 9a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V22h2v-3.08A7 7 0 0 0 19 12h-2z"/>
                        </svg>
                        <span class="sh-cta-main">Upload Your Music</span>
                        <span class="sh-cta-tags">Reach filmmakers · Tip jar · NFT proof</span>
                    </a>
                </div>
            </div>
        </section>

        <?php /* ============================================================
               SECTION 1.5 — PLANET DISCOVERY (moved up under the hero)
               ============================================================ */ ?>
        <section class="sh-planet sh-planet--compact">
            <div class="sh-planet-wrap">
                <?php echo do_shortcode( '[hero_planet hide_lede="1"]' ); ?>
            </div>
        </section>

        <?php /* ============================================================
               SECTION 2 — TRUST STRIP (real licensees)
               ============================================================ */ ?>
        <?php
        global $wpdb;
        $real_projects = $wpdb->get_col( "
            SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type='license' AND p.post_status='publish'
              AND pm.meta_key='project'
              AND pm.meta_value NOT IN ('test','tastadt','ddg','Test Projeect','Twsd','Project Name','TEST !!!!','Awesome book, read worthy!','Forever ?','Tikit','')
              AND pm.meta_value IS NOT NULL
            ORDER BY pm.meta_value
        " );
        if ( ! empty( $real_projects ) ): ?>
        <section class="sh-trust">
            <p class="sh-trust-label">Sync.Land music has been licensed by creators at</p>
            <ul class="sh-trust-list">
                <?php foreach ( $real_projects as $proj ): ?>
                    <li><?php echo esc_html( $proj ); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php /* ============================================================
               SECTION 3 — STATS BAR
               ============================================================ */ ?>
        <?php
        $stat_artists  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='artist' AND post_status='publish'" );
        $stat_albums   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='album' AND post_status='publish'" );
        $stat_songs    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='song' AND post_status='publish'" );
        $stat_licenses = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='license' AND post_status='publish'" );
        ?>
        <section class="sh-stats">
            <div class="sh-stats-grid">
                <div class="sh-stat"><span class="sh-stat-n"><?php echo number_format( $stat_artists ); ?></span><span class="sh-stat-label">independent artists</span></div>
                <div class="sh-stat"><span class="sh-stat-n"><?php echo number_format( $stat_songs ); ?></span><span class="sh-stat-label">songs to license</span></div>
                <div class="sh-stat"><span class="sh-stat-n"><?php echo number_format( $stat_albums ); ?></span><span class="sh-stat-label">albums &amp; EPs</span></div>
                <div class="sh-stat"><span class="sh-stat-n"><?php echo number_format( $stat_licenses ); ?></span><span class="sh-stat-label">licenses issued on-chain</span></div>
            </div>
        </section>

        <?php /* ============================================================
               SECTION 4 — HOW IT WORKS (3 steps)
               ============================================================ */ ?>
        <section class="sh-how">
            <h2 class="sh-section-title">How licensing works</h2>
            <p class="sh-section-sub">Three steps. Most users finish in under 5 minutes.</p>
            <div class="sh-how-grid">
                <div class="sh-how-step">
                    <div class="sh-how-num">1</div>
                    <h3>Browse the catalog</h3>
                    <p>Filter by mood, genre, BPM, and instrumental vs vocal. Hit play before you commit — every song streams full-length.</p>
                </div>
                <div class="sh-how-step">
                    <div class="sh-how-num">2</div>
                    <h3>Pick a license</h3>
                    <p>Creative Commons (free, attribution) for community work. Commercial sync ($49+) for monetized projects — covers YouTube, podcasts, ads, games.</p>
                </div>
                <div class="sh-how-step">
                    <div class="sh-how-num">3</div>
                    <h3>Use it forever</h3>
                    <p>Stripe checkout. Instant PDF license + downloadable MP3/WAV. An NFT receipt mints to Cardano so the license is provable on-chain, not buried in an inbox.</p>
                </div>
            </div>
        </section>

        <?php /* ============================================================
               SECTION 5 — FEATURED ARTISTS
               ============================================================ */ ?>
        <?php
        $featured_artist_ids = array( 8475, 10366, 7860, 10464, 8747, 10425, 10276 );
        $featured = $wpdb->get_results( "
            SELECT a.ID, a.post_title, COUNT(s.ID) AS song_count
            FROM {$wpdb->posts} a
            LEFT JOIN {$wpdb->postmeta} sm ON sm.meta_key='artist' AND sm.meta_value=a.ID
            LEFT JOIN {$wpdb->posts} s ON s.ID=sm.post_id AND s.post_type='song' AND s.post_status='publish'
            WHERE a.ID IN (" . implode( ',', array_map( 'intval', $featured_artist_ids ) ) . ")
              AND a.post_status='publish'
            GROUP BY a.ID
            ORDER BY song_count DESC
        " );
        ?>
        <section class="sh-featured">
            <h2 class="sh-section-title">Featured artists</h2>
            <p class="sh-section-sub">A sample of the catalog. Every song below is licensable today.</p>
            <div class="sh-featured-grid">
                <?php foreach ( $featured as $artist ):
                    $thumb = '';
                    $profile_image_raw = get_post_meta( $artist->ID, 'profile_image', true );
                    if ( is_array( $profile_image_raw ) && ! empty( $profile_image_raw['ID'] ) ) {
                        $thumb = wp_get_attachment_image_url( (int) $profile_image_raw['ID'], 'medium' );
                    } elseif ( is_numeric( $profile_image_raw ) && (int) $profile_image_raw > 0 ) {
                        $thumb = wp_get_attachment_image_url( (int) $profile_image_raw, 'medium' );
                    }
                    $permalink = get_permalink( $artist->ID );
                ?>
                <a href="<?php echo esc_url( $permalink ); ?>" class="sh-featured-card">
                    <?php if ( $thumb ): ?>
                        <div class="sh-featured-img" style="background-image:url('<?php echo esc_url( $thumb ); ?>');"></div>
                    <?php else: ?>
                        <div class="sh-featured-img sh-featured-img-placeholder">♪</div>
                    <?php endif; ?>
                    <div class="sh-featured-meta">
                        <h3><?php echo esc_html( $artist->post_title ); ?></h3>
                        <p><?php echo (int) $artist->song_count; ?> songs</p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <p class="sh-featured-foot"><a href="/artists" class="sh-link-arrow">Browse all <?php echo number_format( $stat_artists ); ?> artists →</a></p>
        </section>

        <?php /* ============================================================
               SECTION 7 — LICENSE TIERS
               ============================================================ */ ?>
        <section class="sh-tiers">
            <h2 class="sh-section-title">Licensing tiers</h2>
            <p class="sh-section-sub">Two options. Both ship with a Cardano NFT receipt.</p>
            <div class="sh-tiers-grid">
                <div class="sh-tier sh-tier-free">
                    <h3>Creative Commons (CC-BY 4.0)</h3>
                    <p class="sh-tier-price">Free</p>
                    <ul>
                        <li>Attribution required</li>
                        <li>Use in any project, commercial or not</li>
                        <li>Instant download + signed license PDF</li>
                        <li>NFT receipt minted on Cardano</li>
                    </ul>
                    <a href="/songs" class="sh-tier-cta">Browse CC-BY catalog</a>
                </div>
                <div class="sh-tier sh-tier-paid">
                    <span class="sh-tier-badge">Most used</span>
                    <h3>Commercial Sync</h3>
                    <p class="sh-tier-price">From $49</p>
                    <ul>
                        <li>No attribution required</li>
                        <li>Cleared for YouTube monetization, ads, paid games</li>
                        <li>Per-project license, valid forever</li>
                        <li>NFT receipt + PDF + stems where available</li>
                    </ul>
                    <a href="/songs?commercial=1" class="sh-tier-cta">Browse commercial catalog</a>
                </div>
            </div>
        </section>

        <?php /* ============================================================
               SECTION 8 — FAQ
               ============================================================ */ ?>
        <section class="sh-faq">
            <h2 class="sh-section-title">Common questions</h2>
            <div class="sh-faq-list">
                <details>
                    <summary>Can I use this music on monetized YouTube videos?</summary>
                    <p>Yes — Commercial Sync covers monetized YouTube, ads, podcasts, paid games, and films. CC-BY 4.0 also covers monetized use, but requires you to credit the artist in your description.</p>
                </details>
                <details>
                    <summary>What is the NFT receipt actually for?</summary>
                    <p>Every license mints as a CIP-25 token on the Cardano blockchain. It's a tamper-proof proof-of-license: if a platform ever flags your video, you can prove you licensed the track without digging through email or PDF archives. The token contains the song, artist, license type, and date — anyone can verify on cardanoscan.io.</p>
                </details>
                <details>
                    <summary>Do I need a Cardano wallet to license a song?</summary>
                    <p>No. You can license with just a credit card via Stripe. The NFT mints into a Sync.Land custody address by default and is available to download whenever you want to claim it.</p>
                </details>
                <details>
                    <summary>How do I, as an artist, get paid?</summary>
                    <p>Sign up, upload your music, and add a payout method (PayPal or tip-jar email). When someone licenses your song, the revenue clears to you minus the platform fee. CC-BY tracks earn through tip-jar links since the license itself is free.</p>
                </details>
                <details>
                    <summary>Where does the music come from?</summary>
                    <p>Sync.Land is artist-direct. Independent musicians upload their own catalog and set their own licensing terms. No major-label clearance, no middlemen.</p>
                </details>
                <details>
                    <summary>Can I license stems or instrumentals?</summary>
                    <p>Many tracks are tagged as instrumental — filter the catalog by the "Instrumental" tag. Stems are available for some Commercial Sync licenses on a per-artist basis.</p>
                </details>
            </div>
        </section>

        <?php /* ============================================================
               FINAL CTA STRIP
               ============================================================ */ ?>
        <section class="sh-end-cta">
            <h2>Ready to license?</h2>
            <div class="sh-cta-row sh-cta-row--end">
                <a href="/songs" class="sh-cta sh-cta-primary">
                    <span class="sh-cta-main">Find Music to License</span>
                </a>
                <a href="/account" class="sh-cta sh-cta-secondary">
                    <span class="sh-cta-main">Upload Your Music</span>
                </a>
            </div>
        </section>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'syncland_home_v2', 'fml_syncland_home_v2_shortcode' );
