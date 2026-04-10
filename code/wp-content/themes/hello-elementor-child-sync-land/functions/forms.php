<?php

//add author to gravity forms
add_filter( 'gform_field_value_author_email', 'populate_post_author_email' );
function populate_post_author_email( $value ) {
    global $post;
 
    $author_email = get_the_author_meta( 'email', $post->post_author );
 
    return $author_email;
}

/**
 * Override the item ID that is edited by the Gravity Form when using a Pods feed.
 *
 * @param int    $edit_id  Edit ID.
 * @param string $pod_name Pod name.
 * @param int    $form_id  GF Form ID.
 * @param array  $feed     GF Form feed array.
 * @param array  $form     GF Form array.
 * @param array  $options  Pods GF options.
 * @param Pods   $pod      Pods object.
 */
function my_custom_pods_gf_edit_id( $edit_id, $pod_name, $form_id, $feed, $form, $options, $pod ) {
	// Only change the edit_id if this is for the form "artist ID".
	if ( 8 !== (int) $form_id ) {
		return $edit_id;
	}
//	
        $artistID = $_GET['artist_edit_id'];
        $currentUserID = get_current_user_id();
	// Check access rights, adjust this as needed.
        $userHasArtist = doesUserHaveArtist($artistID, $currentUserID);
        
        //if administrator
        if(is_user_logged_in() && current_user_can( 'edit_posts' )){
            //echo "isadmin";
            add_filter( 'pods_gf_addon_prepopulate', 'my_custom_pods_gf_prepopulate', 10, 7 );
            return absint($artistID);
        }else{
            //if the user isn't logged in or is not the owner
            if ( ! is_user_logged_in() || !$userHasArtist ) {
                    return $edit_id;
            }
            // Check if the edit_id passed into the URL was set.
            if ( ! isset( $artistID ) ) {
                    return $edit_id;
            }
            // Force the edit_id to one from the URL.
            $edit_id = absint( $artistID );
            // Let's add the filter so we tell Pods to prepopulate the form with this item's data.
            add_filter( 'pods_gf_addon_prepopulate', 'my_custom_pods_gf_prepopulate', 10, 7 );
            return $edit_id;
        }
}
add_filter( 'pods_gf_addon_edit_id', 'my_custom_pods_gf_edit_id', 10, 7 );
/**
 * Override whether to prepopulate the form wit the item being edited by the Gravity Form when using a Pods feed.
 *
 * @param bool   $prepopulate Whether to prepopulate or not.
 * @param string $pod_name    Pod name.
 * @param int    $form_id     GF Form ID.
 * @param array  $feed        GF Form feed array.
 * @param array  $form        GF Form array.
 * @param array  $options     Pods GF options.
 * @param Pods   $pod         Pods object.
 */
function my_custom_pods_gf_prepopulate( $prepopulate, $pod_name, $form_id, $feed, $form, $options, $pod ) {
	// We added this filter when checking if they can edit, so we can trust this filter context.
	// Always prepopulate the form with the item we are editing.
	return true;
}
function doesUserHaveArtist($artistID, $userID){
    $artistPod = pods("artist", $artistID);
    $artistPostAuthor = $artistPod->display("post_author");

//    echo $artistPostAuthor;
//    echo $userID;

    return ( absint($artistPostAuthor) == absint($userID));
}

/**
 * Send admin notification when an artist profile is created or edited via Gravity Form 8.
 */
add_action('gform_after_submission_8', 'fml_handle_artist_form_submission', 10, 2);

function fml_handle_artist_form_submission($entry, $form) {
    if (!function_exists('fml_notify_artist_created')) {
        return;
    }

    $is_edit = isset($_GET['artist_edit_id']) && !empty($_GET['artist_edit_id']);
    $current_user = wp_get_current_user();

    // The Pods GF addon creates/updates the artist post — get the artist name from the entry.
    // Gravity Form field IDs may vary; try common approaches to get the artist name.
    $artist_name = '';

    // Try to get artist name from the created/edited post
    if ($is_edit) {
        $artist_id = absint($_GET['artist_edit_id']);
        $artist_name = get_the_title($artist_id);
    } else {
        // For new artist, get from the Pods GF addon's created post ID
        $created_post_id = gform_get_meta($entry['id'], 'pods_post_id');
        if ($created_post_id) {
            $artist_name = get_the_title($created_post_id);
        }
    }

    // Fallback: try to get artist name from form field (field 1 is often the name)
    if (empty($artist_name)) {
        $artist_name = rgar($entry, '1') ?: rgar($entry, '2') ?: 'Unknown';
    }

    $artist_id_for_link = $is_edit
        ? absint($_GET['artist_edit_id'])
        : (gform_get_meta($entry['id'], 'pods_post_id') ?: 0);

    // Admin notification
    fml_notify_artist_created(fml_get_admin_email(), [
        'artist_name' => $artist_name,
        'username'    => $current_user->user_login ?? '',
        'is_edit'     => $is_edit,
        'artist_id'   => $artist_id_for_link,
    ]);

    // User confirmation
    if (!empty($current_user->user_email) && function_exists('fml_notify_artist_profile_user')) {
        fml_notify_artist_profile_user($current_user->user_email, [
            'artist_name' => $artist_name,
            'is_edit'     => $is_edit,
            'artist_id'   => $artist_id_for_link,
        ]);
    }
}


//MY ACCOUNT
//echo $_SERVER["DOCUMENT_ROOT"]."/wp-content/plugins/user-registration/includes/functions-ur-template.php";
// include_once $_SERVER["DOCUMENT_ROOT"]."/wp-content/plugins/user-registration-pro/includes/functions-ur-template.php";
// include_once UR_ABSPATH . 'includes/functions-ur-notice.php';
// add_shortcode('account_dashboard', 'account_dashboard');
// function account_dashboard(){

//     require_once get_stylesheet_directory()."/user-registration/myaccount/dashboard.php";
    
// }
// add_shortcode('account_password_change', 'account_password_change');
// function account_password_change(){

//     //require_once get_stylesheet_directory()."/user-registration/myaccount/form-edit-password.php";
//     // Collect notices before output.
   
//     UR_Shortcode_My_Account::edit_account();
    
// }
// add_shortcode('account_artists', 'account_artists');
// function account_artists(){

//     require_once get_stylesheet_directory()."/user-registration/myaccount/artists.php";
    
// }
// add_shortcode('account_login', 'account_login');
// function account_login(){

//     //UR_Shortcode_Login::get($null);
//     require_once get_stylesheet_directory()."/user-registration/myaccount/form-login.php";
    
// }
//add_shortcode('account_lost_password', 'account_lost_password');
//function account_lost_password(){
//
//    //require get_stylesheet_directory()."/user-registration/myaccount/form-lost-password.php";
//    // Collect notices before output.
//
//    UR_Shortcode_My_Account::lost_password();
//    
// //}
// add_shortcode('account_edit_profile', 'account_edit_profile');
// function account_edit_profile(){

//     //require get_stylesheet_directory()."/user-registration/myaccount/form-edit-profile.php";
    
//     UR_Shortcode_My_Account::edit_profile();
	
    
// }

add_shortcode('get_user_image_url', 'get_user_image_url');
function get_user_image_url(){
    echo "<img src='".get_user_meta( get_current_user_id(), 'user_registration_profile_pic_url', true )."' />";
}

add_shortcode('account_logout', 'account_logout');
function account_logout(){
	echo sprintf( __( '<div class="aligncenter">Are you sure you want to log out?&nbsp;<a href="%s"><br /><button class="button danger" style="background-color: #d9534f;">Confirm and log out</button></a></div>', 'user-registration' ), ur_logout_url() );
}
