<?php
/**
 * Account tab navigation.
 *
 * Expects $current_tab to be set by the controller (syncland_dashboard_shortcode).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$account_url = home_url( '/account/' );

$tabs = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon'  => 'fas fa-tachometer-alt',
        'url'   => $account_url,
    ],
    'licenses' => [
        'label' => 'Licenses',
        'icon'  => 'fas fa-file-contract',
        'url'   => $account_url . 'licenses/',
    ],
    'playlists' => [
        'label' => 'Playlists',
        'icon'  => 'fas fa-list',
        'url'   => $account_url . 'playlists/',
    ],
];

$tabs['artists'] = [
    'label' => 'Music',
    'icon'  => 'fas fa-music',
    'url'   => $account_url . 'artists/',
];

$tabs['edit-profile'] = [
    'label' => 'Edit Profile',
    'icon'  => 'fas fa-user-edit',
    'url'   => $account_url . 'edit-profile/',
];

$tabs['user-logout'] = [
    'label' => 'Log Out',
    'icon'  => 'fas fa-sign-out-alt',
    'url'   => wp_logout_url( home_url() ),
];
?>

<nav class="syncland-nav" aria-label="Account navigation">
    <ul class="syncland-nav-tabs">
        <?php foreach ( $tabs as $key => $tab ) :
            $active = ( $key === $current_tab ) ? ' syncland-nav-active' : '';
        ?>
            <li class="syncland-nav-item<?php echo esc_attr( $active ); ?>">
                <a href="<?php echo esc_url( $tab['url'] ); ?>" class="syncland-nav-link">
                    <i class="<?php echo esc_attr( $tab['icon'] ); ?>"></i>
                    <span><?php echo esc_html( $tab['label'] ); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
