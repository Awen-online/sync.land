<?php
/**
 * Song Search REST Endpoint
 *
 * GET /wp-json/FML/v1/song-search?q=term
 */

add_action( 'rest_api_init', function () {
  register_rest_route( 'FML/v1', '/song-search', array(
    'methods' => 'GET',
    'callback' => 'get_songs',
  ) );
} );

function get_songs(){
     header("Content-Type: application/json; charset=utf-8");
//    echo "test";
    
//    $_POST = json_decode(file_get_contents("php://input"), true);
//    print_r($_POST);
//    print_r($_REQUEST);
    
    $nonce = check_ajax_referer( 'wp_rest', '_wpnonce' );
    
    
//    $output = "YERS";
    //authenticate (check if user is logged in)
    //echo "nonce: ".$nonce;
    
    if($nonce){
        if(isset($_GET['q'])){
            global $wpdb;
            $q = sanitize_text_field($_GET['q']);
            // Use wpdb->esc_like to prevent SQL injection in LIKE queries
            $escaped_q = $wpdb->esc_like($q);
            // Here's how to use find()
            $params = array(
                'limit' => 5,
                'where' => $wpdb->prepare("t.post_title LIKE %s", '%' . $escaped_q . '%')
            );
            $songs = pods("song", $params);
            $songObj = array($songs->export());

           
            $success = true;
        }else{
            $success = false;
            $error = "Input issue";
        }
    }else{
        $success = false;
        $error="Nonce issue.";
    }
    
    
    if ($success) {
	$output = array("success" => true, "message" => "Success!", "songs" => $songObj );
    } else {
        $output = array("success" => false, "error" => "Failure! ".$error);
    }
    
    echo json_encode($output);
}