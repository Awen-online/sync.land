<?php
/**
 * Account Dashboard — welcome header + quick-action cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user_id   = get_current_user_id();
$user      = get_user_by( 'id', $user_id );
$is_artist = array_intersect( [ 'administrator', 'artist' ], (array) $user->roles );

/* Profile picture — handle attachment ID, URL, or Gravatar fallback */
$profile_picture_url = get_user_meta( $user_id, 'user_registration_profile_pic_url', true );
$image = '';

if ( ! empty( $profile_picture_url ) ) {
    if ( is_numeric( $profile_picture_url ) ) {
        $image = wp_get_attachment_image_url( (int) $profile_picture_url, 'thumbnail' );
    } else {
        $image = esc_url_raw( $profile_picture_url );
    }
}

if ( empty( $image ) ) {
    $image = get_avatar_url( $user_id, [ 'size' => 200 ] );
}

/* Display name */
$first = ucfirst( get_user_meta( $user_id, 'first_name', true ) );
$last  = ucfirst( get_user_meta( $user_id, 'last_name', true ) );
$name  = trim( "$first $last" ) ?: $user->display_name;
?>

<div class="syncland-welcome">
    <div class="syncland-welcome-avatar">
        <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="profile-preview">
    </div>
    <div class="syncland-welcome-text">
        <h2>Welcome, <?php echo esc_html( $user->display_name ); ?></h2>
    </div>
</div>

<div class="syncland-quick-actions">
    <?php if ( ! empty( $is_artist ) ) : ?>
    <a href="<?php echo esc_url( home_url( '/account/artists/' ) ); ?>" class="syncland-action-card">
        <i class="fas fa-music"></i>
        <strong>My Music</strong>
        <span>Manage artists, albums &amp; licensing</span>
    </a>
    <?php endif; ?>

    <a href="<?php echo esc_url( home_url( '/account/licenses/' ) ); ?>" class="syncland-action-card">
        <i class="fas fa-file-contract"></i>
        <strong>My Licenses</strong>
        <span>View license history &amp; downloads</span>
    </a>

    <a href="<?php echo esc_url( home_url( '/account/playlists/' ) ); ?>" class="syncland-action-card">
        <i class="fas fa-list"></i>
        <strong>Playlists</strong>
        <span>Create &amp; manage playlists</span>
    </a>

    <a href="<?php echo esc_url( home_url( '/account/edit-profile/' ) ); ?>" class="syncland-action-card">
        <i class="fas fa-user-edit"></i>
        <strong>Edit Profile</strong>
        <span>Update your account details</span>
    </a>

    <a href="<?php echo esc_url( home_url( '/songs/' ) ); ?>" class="syncland-action-card">
        <i class="fas fa-search"></i>
        <strong>Browse Music</strong>
        <span>Discover songs to license</span>
    </a>
</div>
