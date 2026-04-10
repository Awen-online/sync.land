<?php
/**
 * NMKR NFT Minting Integration for Sync.Land
 *
 * Handles NFT minting via NMKR API with support for:
 * - Preprod (test) and Mainnet (live) environments
 * - API keys managed in WordPress Admin > Settings > Sync.Land Licensing
 * - Backwards compatible with wp-config.php constants
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * NMKR API KEY MANAGEMENT
 * ============================================================================
 */

/**
 * Get current NMKR mode (preprod or mainnet)
 */
function fml_get_nmkr_mode() {
    return get_option('fml_nmkr_mode', 'preprod');
}

/**
 * Check if NMKR is in mainnet (live) mode
 */
function fml_nmkr_is_mainnet() {
    return fml_get_nmkr_mode() === 'mainnet';
}

/**
 * Get NMKR API URL based on current mode
 */
function fml_get_nmkr_api_url() {
    $mode = fml_get_nmkr_mode();

    if ($mode === 'mainnet') {
        $url = get_option('fml_nmkr_mainnet_api_url', 'https://studio-api.nmkr.io');
    } else {
        $url = get_option('fml_nmkr_preprod_api_url', 'https://studio-api.preprod.nmkr.io');
    }

    // Fallback to constant if option is empty
    if (empty($url) && defined('FML_NMKR_API_URL')) {
        $url = FML_NMKR_API_URL;
    }

    return rtrim($url, '/');
}

/**
 * Get active NMKR API key based on current mode
 */
function fml_get_nmkr_api_key() {
    $mode = fml_get_nmkr_mode();

    if ($mode === 'mainnet') {
        $key = get_option('fml_nmkr_mainnet_api_key', '');
    } else {
        $key = get_option('fml_nmkr_preprod_api_key', '');
    }

    // Fallback to constant if option is empty
    if (empty($key) && defined('FML_NMKR_API_KEY')) {
        $key = FML_NMKR_API_KEY;
    }

    return $key;
}

/**
 * Get active NMKR project UID based on current mode
 */
function fml_get_nmkr_project_uid() {
    $mode = fml_get_nmkr_mode();

    if ($mode === 'mainnet') {
        $uid = get_option('fml_nmkr_mainnet_project_uid', '');
    } else {
        $uid = get_option('fml_nmkr_preprod_project_uid', '');
    }

    // Fallback to constant if option is empty
    if (empty($uid) && defined('FML_NMKR_PROJECT_UID')) {
        $uid = FML_NMKR_PROJECT_UID;
    }

    return $uid;
}

/**
 * Get active NMKR policy ID based on current mode
 */
function fml_get_nmkr_policy_id() {
    $mode = fml_get_nmkr_mode();

    if ($mode === 'mainnet') {
        $id = get_option('fml_nmkr_mainnet_policy_id', '');
    } else {
        $id = get_option('fml_nmkr_preprod_policy_id', '');
    }

    // Fallback to constant if option is empty
    if (empty($id) && defined('FML_NMKR_POLICY_ID')) {
        $id = FML_NMKR_POLICY_ID;
    }

    return $id;
}

/**
 * Check if NMKR is properly configured
 */
function fml_nmkr_is_configured() {
    $api_key = fml_get_nmkr_api_key();
    $project_uid = fml_get_nmkr_project_uid();
    return !empty($api_key) && !empty($project_uid);
}

/**
 * Get NMKR Mint Coupon Balance
 * Mint coupons are required for manual minting via API
 */
function fml_get_nmkr_mint_coupon_balance() {
    $api_key = fml_get_nmkr_api_key();
    $api_url = fml_get_nmkr_api_url();

    if (empty($api_key)) {
        return ['success' => false, 'error' => 'API key not configured'];
    }

    $response = wp_remote_get($api_url . '/v2/GetMintCouponBalance', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Accept' => 'application/json'
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($http_code !== 200) {
        return ['success' => false, 'error' => "HTTP {$http_code}", 'response' => $body];
    }

    // Response should contain the balance
    return [
        'success' => true,
        'balance' => $body['mintCoupons'] ?? $body['balance'] ?? $body,
        'raw' => $body
    ];
}

/**
 * Get NMKR Project Details
 * Returns project info including policy lock date, NFT counts, etc.
 */
function fml_get_nmkr_project_details() {
    $api_key = fml_get_nmkr_api_key();
    $api_url = fml_get_nmkr_api_url();
    $project_uid = fml_get_nmkr_project_uid();

    if (empty($api_key) || empty($project_uid)) {
        return ['success' => false, 'error' => 'NMKR not configured'];
    }

    $response = wp_remote_get($api_url . '/v2/GetProjectDetails/' . $project_uid, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Accept' => 'application/json'
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($http_code !== 200) {
        // If 404, try to list projects to help debug
        if ($http_code === 404) {
            return [
                'success' => false,
                'error' => "Project not found (UID: {$project_uid})",
                'configured_uid' => $project_uid
            ];
        }
        return ['success' => false, 'error' => "HTTP {$http_code}", 'response' => $body];
    }

    // Check if policy is locked
    $policy_locked = false;
    $policy_lock_date = null;
    if (!empty($body['policyExpires'])) {
        $policy_lock_date = $body['policyExpires'];
        $lock_time = strtotime($policy_lock_date);
        if ($lock_time && $lock_time < time()) {
            $policy_locked = true;
        }
    }

    return [
        'success' => true,
        'project_name' => $body['projectname'] ?? 'Unknown',
        'policy_id' => $body['policyId'] ?? '',
        'policy_locked' => $policy_locked,
        'policy_lock_date' => $policy_lock_date,
        'nft_counts' => $body['nftCounts'] ?? [],
        'address' => $body['address'] ?? '',
        'raw' => $body
    ];
}

/**
 * List all NMKR projects for this account
 */
function fml_list_nmkr_projects() {
    $api_key = fml_get_nmkr_api_key();
    $api_url = fml_get_nmkr_api_url();

    if (empty($api_key)) {
        return ['success' => false, 'error' => 'API key not configured'];
    }

    $response = wp_remote_get($api_url . '/v2/ListProjects/50/1', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Accept' => 'application/json'
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($http_code !== 200) {
        return ['success' => false, 'error' => "HTTP {$http_code}", 'response' => $body];
    }

    return [
        'success' => true,
        'projects' => $body
    ];
}

/**
 * Get NMKR credentials safely (updated to use new helper functions)
 */
function fml_get_nmkr_credentials() {
    $missing = [];

    $api_key = fml_get_nmkr_api_key();
    $project_uid = fml_get_nmkr_project_uid();
    $policy_id = fml_get_nmkr_policy_id();
    $api_url = fml_get_nmkr_api_url();

    if (empty($api_key)) $missing[] = 'API Key';
    if (empty($project_uid)) $missing[] = 'Project UID';
    if (empty($policy_id)) $missing[] = 'Policy ID';
    if (empty($api_url)) $missing[] = 'API URL';

    if (!empty($missing)) {
        return [
            'success' => false,
            'error' => 'Missing NMKR configuration: ' . implode(', ', $missing),
            'mode' => fml_get_nmkr_mode()
        ];
    }

    return [
        'success' => true,
        'api_key' => $api_key,
        'project_uid' => $project_uid,
        'policy_id' => $policy_id,
        'api_url' => $api_url,
        'mode' => fml_get_nmkr_mode()
    ];
}

/**
 * Verify NMKR connection by making a test API call
 */
function fml_verify_nmkr_connection($api_key = null, $api_url = null, $project_uid = null) {
    if ($api_key === null) {
        $api_key = fml_get_nmkr_api_key();
    }
    if ($api_url === null) {
        $api_url = fml_get_nmkr_api_url();
    }
    if ($project_uid === null) {
        $project_uid = fml_get_nmkr_project_uid();
    }

    if (empty($api_key)) {
        return [
            'success' => false,
            'error' => 'No API key provided'
        ];
    }

    // Use GetCounts with project UID if available, otherwise try GetWalletValidationAddress
    if (!empty($project_uid)) {
        $endpoint = $api_url . '/v2/GetCounts/' . $project_uid;
    } else {
        // Fallback endpoint that doesn't require project UID
        $endpoint = $api_url . '/v2/GetWalletValidationAddress';
    }

    $response = wp_remote_get($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'timeout' => 20,
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error' => 'Connection error: ' . $response->get_error_message()
        ];
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);
    $body = json_decode($raw_body, true);

    // Log for debugging
    error_log("NMKR API Verify - Endpoint: {$endpoint}");
    error_log("NMKR API Verify - Status: {$status_code}");
    error_log("NMKR API Verify - Response: " . substr($raw_body, 0, 500));

    if ($status_code === 200) {
        // Determine if preprod or mainnet based on URL
        $is_preprod = strpos($api_url, 'preprod') !== false;

        return [
            'success' => true,
            'mode' => $is_preprod ? 'preprod' : 'mainnet',
            'message' => 'Connected successfully' . ($is_preprod ? ' (Preprod/Test)' : ' (Mainnet/Live)')
        ];
    } else {
        // Try to get a meaningful error message
        $error_message = 'HTTP ' . $status_code;
        if (is_array($body)) {
            if (isset($body['message'])) {
                $error_message = $body['message'];
            } elseif (isset($body['error'])) {
                $error_message = $body['error'];
            } elseif (isset($body['title'])) {
                $error_message = $body['title'];
            }
        } elseif ($status_code === 401) {
            $error_message = 'Unauthorized - check your API key';
        } elseif ($status_code === 403) {
            $error_message = 'Forbidden - API key may not have required permissions';
        } elseif ($status_code === 404) {
            $error_message = 'Endpoint not found - check Project UID';
        }

        return [
            'success' => false,
            'error' => $error_message,
            'http_code' => $status_code,
            'debug' => substr($raw_body, 0, 200)
        ];
    }
}

/**
 * AJAX handler for NMKR connection verification
 */
add_action('wp_ajax_fml_verify_nmkr', 'fml_ajax_verify_nmkr_connection');

function fml_ajax_verify_nmkr_connection() {
    check_ajax_referer('fml_licensing_settings', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $key_type = sanitize_text_field($_POST['key_type'] ?? 'current');

    // Determine which credentials to test
    if ($key_type === 'preprod') {
        $api_key = sanitize_text_field($_POST['preprod_api_key'] ?? '');
        $api_url = 'https://studio-api.preprod.nmkr.io';
        $project_uid = sanitize_text_field($_POST['preprod_project_uid'] ?? get_option('fml_nmkr_preprod_project_uid', ''));
    } elseif ($key_type === 'mainnet') {
        $api_key = sanitize_text_field($_POST['mainnet_api_key'] ?? '');
        $api_url = 'https://studio-api.nmkr.io';
        $project_uid = sanitize_text_field($_POST['mainnet_project_uid'] ?? get_option('fml_nmkr_mainnet_project_uid', ''));
    } else {
        $api_key = fml_get_nmkr_api_key();
        $api_url = fml_get_nmkr_api_url();
        $project_uid = fml_get_nmkr_project_uid();
    }

    if (empty($api_key)) {
        wp_send_json_error(['message' => 'No API key provided']);
    }

    $result = fml_verify_nmkr_connection($api_key, $api_url, $project_uid);

    if ($result['success']) {
        wp_send_json_success([
            'message' => $result['message'],
            'mode' => $result['mode']
        ]);
    } else {
        // Include debug info if available
        $error_msg = $result['error'];
        if (isset($result['debug']) && !empty($result['debug'])) {
            error_log("NMKR Verify Debug: " . $result['debug']);
        }
        wp_send_json_error(['message' => $error_msg]);
    }
}

/**
 * AJAX handler for loading NMKR projects list
 */
add_action('wp_ajax_fml_load_nmkr_projects', 'fml_ajax_load_nmkr_projects');

function fml_ajax_load_nmkr_projects() {
    check_ajax_referer('fml_licensing_settings', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => 'Unauthorized']);
    }

    $env = sanitize_text_field($_POST['env'] ?? 'preprod');
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');

    if (empty($api_key)) {
        wp_send_json_error(['error' => 'No API key provided']);
    }

    // Determine API URL based on environment
    $api_url = ($env === 'mainnet')
        ? 'https://studio-api.nmkr.io'
        : 'https://studio-api.preprod.nmkr.io';

    // Call ListProjects endpoint
    $response = wp_remote_get($api_url . '/v2/ListProjects/50/1', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Accept' => 'application/json'
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['error' => $response->get_error_message()]);
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($http_code !== 200) {
        wp_send_json_error(['error' => "HTTP {$http_code}: " . (is_array($body) ? json_encode($body) : $body)]);
    }

    if (empty($body) || !is_array($body)) {
        wp_send_json_error(['error' => 'No projects found in your NMKR account']);
    }

    wp_send_json_success(['projects' => $body]);
}

/**
 * ============================================================================
 * LEGACY AJAX HANDLERS
 * ============================================================================
 */

add_action('wp_ajax_mint_nft', 'mint_nft_callback');
add_action('wp_ajax_nopriv_mint_nft', 'mint_nft_callback'); // Allow non-logged-in users

function mint_nft_callback() {
    check_ajax_referer('mint_nft_nonce', 'nonce');

    // Step 1: Check NMKR credentials
    $creds = fml_get_nmkr_credentials();
    if (!$creds['success']) {
        wp_send_json_error(['message' => $creds['error']]);
        return;
    }

    // Step 2: Get post info
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error(['message' => 'No post ID provided']);
        return;
    }

    $title = sanitize_text_field(get_the_title($post_id));
    $description = sanitize_text_field(get_post_field('post_content', $post_id));

    // Step 3: Determine image URL
    $image_url = get_the_post_thumbnail_url($post_id, 'full');

    // Fallback if missing or not publicly accessible
    if (empty($image_url) || !fml_url_is_accessible($image_url)) {
        $image_url = 'https://www.sync.land/wp-content/uploads/2024/06/cropped-SyncLand-Logo-optimized-150x150.png';
        error_log("Post $post_id missing or inaccessible thumbnail. Using default image: $image_url");
    } else {
        error_log("Post $post_id thumbnail URL: $image_url");
    }

    // Step 4: Build metadata (simplified example)
    $token_name = uniqid('sync-');

    $metadata = [
        '721' => [
            $creds['policy_id'] => [
                $token_name => [
                    'name' => $title,
                    'image' => $image_url,
                    'mediaType' => 'image/png',
                    'description' => [$description],
                ]
            ],
            'version' => '1.0'
        ]
    ];

    // Step 5: Prepare NMKR payload
    $data = [
        'nftName' => $token_name,
        'contentType' => 'image/png',
        'fileAsUrl' => $image_url,
        'previewFileAsUrl' => $image_url, // REQUIRED by NMKR
        'metadata' => $metadata
    ];

    error_log("Sending NFT payload to NMKR: " . json_encode($data));

    // Step 6: Call NMKR API
    $ch = curl_init($creds['api_url'] . "/v2/UploadNft/{$creds['project_uid']}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $creds['api_key'],
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200 || $http_code == 201) {
        wp_send_json_success(['message' => 'NFT uploaded successfully', 'data' => json_decode($response, true)]);
    } else {
        wp_send_json_error([
            'message' => 'Upload failed',
            'http_code' => $http_code,
            'response' => $response,
            'curl_error' => $curl_error
        ]);
    }
}

/**
 * Helper function to check if a URL is publicly accessible
 */
function fml_url_is_accessible($url) {
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '200') !== false;
}




add_shortcode('nmkr_mint', function(){
    return '<form id="nft-form">
        <input type="hidden" name="post_id" value="'.get_the_ID().'">
        <button type="button" id="mint-nft-btn">Mint NFT</button>
    </form>';
});


add_shortcode('nmkr_pay', function() {
    ob_start();
    ?>
    <img src="https://studio.nmkr.io/images/buttons/paybutton_1_1.svg" onclick="javascript:openPaymentWindow()">

    <script type="text/javascript">
        function openPaymentWindow() {
            const paymentUrl = "https://pay.preprod.nmkr.io/?p=ba6643e3b89a49859d837366969a524d&c=1";

            // Specify the popup width and height
            const popupWidth = 500;
            const popupHeight = 700;

            // Calculate the center of the screen
            const left = window.top.outerWidth / 2 + window.top.screenX - ( popupWidth / 2);
            const top = window.top.outerHeight / 2 + window.top.screenY - ( popupHeight / 2);

            const popup =  window.open(paymentUrl, "NFT-MAKER PRO Payment Gateway",  `popup=1, location=1, width=${popupWidth}, height=${popupHeight}, left=${left}, top=${top}`);

            // Show dim background
            document.body.style = "background: rgba(0, 0, 0, 0.5)";

            // Continuously check whether the popup has been closed
            const backgroundCheck = setInterval(function () {
                if(popup.closed) {
                    clearInterval(backgroundCheck);

                    console.log("Popup closed");

                    // Remove dim background
                    document.body.style = "";
                }
            }, 1000);
        }
    </script>


    <?php
    return ob_get_clean();
});


/**
 * ============================================================================
 * LICENSE NFT MINTING
 * ============================================================================
 * Mint a license as an NFT with CIP-25 compliant metadata
 */

/**
 * Mint a license as an NFT
 *
 * This function now delegates to the enhanced IPFS-enabled minting function.
 *
 * @param int    $license_id     The license post ID
 * @param string $wallet_address The recipient's Cardano wallet address
 * @return array Result with success status and data/error
 */
function fml_mint_license_nft($license_id, $wallet_address = '') {
    // Delegate to the enhanced IPFS-enabled minting function
    return fml_mint_license_nft_with_ipfs($license_id, $wallet_address);
}

/**
 * AJAX handler for minting license NFT
 */
add_action('wp_ajax_mint_license_nft', 'fml_mint_license_nft_ajax');

function fml_mint_license_nft_ajax() {
    check_ajax_referer('mint_license_nft_nonce', 'nonce');

    $license_id = isset($_POST['license_id']) ? intval($_POST['license_id']) : 0;
    $wallet_address = isset($_POST['wallet_address']) ? sanitize_text_field($_POST['wallet_address']) : '';

    if (!$license_id) {
        wp_send_json_error(['message' => 'No license ID provided']);
        return;
    }

    // Validate wallet address format
    if (!empty($wallet_address) && function_exists('fml_validate_cardano_address') && !fml_validate_cardano_address($wallet_address)) {
        wp_send_json_error(['message' => 'Invalid Cardano wallet address format']);
        return;
    }

    $result = fml_mint_license_nft($license_id, $wallet_address);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/**
 * REST API endpoint for minting license NFT
 */
add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/licenses/(?P<id>\d+)/mint-nft', [
        'methods' => 'POST',
        'callback' => 'fml_mint_license_nft_rest',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'wallet_address' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ]);
});

function fml_mint_license_nft_rest(WP_REST_Request $request) {
    $license_id = $request->get_param('id');
    $wallet_address = $request->get_param('wallet_address') ?? '';

    // Validate user owns this license
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return new WP_REST_Response(['success' => false, 'error' => 'License not found'], 404);
    }

    $license_user = $license_pod->field('user');
    $license_user_id = is_array($license_user) ? $license_user['ID'] : $license_user;

    if ($license_user_id != get_current_user_id() && !current_user_can('manage_options')) {
        return new WP_REST_Response(['success' => false, 'error' => 'Unauthorized'], 403);
    }

    $result = fml_mint_license_nft($license_id, $wallet_address);

    $status = $result['success'] ? 200 : 500;
    return new WP_REST_Response($result, $status);
}

/**
 * Get NFT status for a license
 */
add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/licenses/(?P<id>\d+)/nft-status', [
        'methods' => 'GET',
        'callback' => 'fml_get_license_nft_status',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);
});

function fml_get_license_nft_status(WP_REST_Request $request) {
    $license_id = $request->get_param('id');

    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return new WP_REST_Response(['success' => false, 'error' => 'License not found'], 404);
    }

    return new WP_REST_Response([
        'success' => true,
        'data' => [
            'license_id' => $license_id,
            'nft_status' => $license_pod->field('nft_status') ?: 'none',
            'nft_asset_id' => $license_pod->field('nft_asset_id') ?: null,
            'nft_transaction_hash' => $license_pod->field('nft_transaction_hash') ?: null,
            'nft_minted_at' => $license_pod->field('nft_minted_at') ?: null,
            'nft_ipfs_hash' => $license_pod->field('nft_ipfs_hash') ?: null,
            'nft_policy_id' => $license_pod->field('nft_policy_id') ?: null,
            'nft_asset_name' => $license_pod->field('nft_asset_name') ?: null,
            'wallet_address' => $license_pod->field('wallet_address') ?: null
        ]
    ], 200);
}


/**
 * ============================================================================
 * IPFS UPLOAD FOR LICENSE PDFs
 * ============================================================================
 */

/**
 * Upload a license PDF to IPFS
 *
 * Tries multiple IPFS services:
 * 1. Pinata (free tier available)
 * 2. Web3.Storage (if configured)
 * 3. NFT.Storage (if configured)
 *
 * @param string $pdf_url The URL of the license PDF to upload
 * @param string $filename The filename to use on IPFS
 * @return array Result with IPFS hash or error
 */
function fml_upload_license_pdf_to_ipfs($pdf_url, $filename = '') {
    error_log("=== IPFS Upload Starting ===");
    error_log("PDF URL: {$pdf_url}");

    // Download PDF content first
    error_log("Downloading PDF from S3...");
    $pdf_response = wp_remote_get($pdf_url, ['timeout' => 30, 'sslverify' => false]);
    if (is_wp_error($pdf_response)) {
        error_log("IPFS Upload failed: Could not download PDF - " . $pdf_response->get_error_message());
        return ['success' => false, 'error' => 'Failed to download PDF: ' . $pdf_response->get_error_message()];
    }

    $download_code = wp_remote_retrieve_response_code($pdf_response);
    error_log("PDF download response: HTTP {$download_code}");

    if ($download_code !== 200) {
        error_log("IPFS Upload failed: PDF download returned HTTP {$download_code}");
        return ['success' => false, 'error' => "PDF download failed with HTTP {$download_code}"];
    }

    $pdf_content = wp_remote_retrieve_body($pdf_response);
    if (empty($pdf_content)) {
        error_log("IPFS Upload failed: PDF content is empty");
        return ['success' => false, 'error' => 'PDF content is empty'];
    }

    $pdf_size = strlen($pdf_content);
    error_log("PDF downloaded successfully. Size: {$pdf_size} bytes");

    // Check if PDF is too large
    $max_size = 10 * 1024 * 1024; // 10MB limit
    if ($pdf_size > $max_size) {
        error_log("IPFS Upload failed: PDF too large ({$pdf_size} bytes > {$max_size})");
        return ['success' => false, 'error' => 'PDF file too large for IPFS upload'];
    }

    // Generate filename if not provided
    if (empty($filename)) {
        $filename = 'license_' . time() . '.pdf';
    }

    // Try Pinata first (most reliable free option)
    $pinata_jwt = get_option('fml_pinata_jwt', '');
    if (!empty($pinata_jwt)) {
        $result = fml_upload_to_pinata($pdf_content, $filename, $pinata_jwt);
        if ($result['success']) {
            return $result;
        }
        error_log("Pinata upload failed: " . ($result['error'] ?? 'Unknown error'));
    } else {
        error_log("Pinata JWT not configured, skipping Pinata");
    }

    // No IPFS service configured or Pinata failed
    error_log("=== IPFS Upload FAILED - no working IPFS service ===");
    return [
        'success' => false,
        'error' => 'No IPFS service configured. Add Pinata JWT in Settings > Sync.Land Licensing.',
        'suggestion' => 'Get a free Pinata account at https://pinata.cloud'
    ];
}

/**
 * Upload to Pinata IPFS
 */
function fml_upload_to_pinata($file_content, $filename, $jwt) {
    error_log("Uploading to Pinata...");

    $boundary = wp_generate_password(24, false);

    // Build multipart body
    $body = '';
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
    $body .= "Content-Type: application/pdf\r\n\r\n";
    $body .= $file_content . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $response = wp_remote_post('https://api.pinata.cloud/pinning/pinFileToIPFS', [
        'headers' => [
            'Authorization' => 'Bearer ' . $jwt,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        ],
        'body' => $body,
        'timeout' => 120
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    error_log("Pinata response: HTTP {$http_code}");
    error_log("Pinata body: " . json_encode($body));

    if ($http_code == 200 && !empty($body['IpfsHash'])) {
        $ipfs_hash = $body['IpfsHash'];
        error_log("=== Pinata IPFS Upload SUCCESS: ipfs://{$ipfs_hash} ===");
        return [
            'success' => true,
            'ipfs_hash' => $ipfs_hash,
            'ipfs_url' => 'ipfs://' . $ipfs_hash,
            'gateway_url' => 'https://gateway.pinata.cloud/ipfs/' . $ipfs_hash
        ];
    }

    return [
        'success' => false,
        'error' => $body['error']['message'] ?? $body['message'] ?? "HTTP {$http_code}",
        'response' => $body
    ];
}



/**
 * ============================================================================
 * ENHANCED LICENSE NFT MINTING WITH IPFS
 * ============================================================================
 */

/**
 * Mint a license as an NFT with IPFS-hosted PDF
 *
 * @param int    $license_id     The license post ID
 * @param string $wallet_address The recipient's Cardano wallet address
 * @return array Result with success status and data/error
 */
/**
 * Helper to get a single value from a Pods field (handles arrays)
 */
function fml_get_pod_value($pod, $field) {
    $value = $pod->field($field);
    if (is_array($value)) {
        return $value[0] ?? '';
    }
    return $value ?: '';
}

/**
 * Truncate string to fit CIP-25 metadata limit (64 bytes)
 * Also removes any characters that might cause issues
 */
function fml_truncate_metadata_string($string, $max_bytes = 64) {
    // Remove any control characters
    $string = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);

    // Truncate to max bytes (accounting for UTF-8 multi-byte chars)
    if (strlen($string) > $max_bytes) {
        $string = mb_substr($string, 0, $max_bytes - 3, 'UTF-8') . '...';
    }

    return $string;
}

/**
 * Split a long string into an array of 64-byte chunks for CIP-25 compliance.
 * If the string fits in 64 bytes, returns it as-is (string).
 * If it exceeds 64 bytes, returns an array of chunks.
 */
function fml_cip25_string($string, $max_bytes = 64) {
    $string = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);

    if (strlen($string) <= $max_bytes) {
        return $string;
    }

    // Split into chunks of max_bytes, respecting UTF-8 boundaries
    $chunks = [];
    while (strlen($string) > 0) {
        if (strlen($string) <= $max_bytes) {
            $chunks[] = $string;
            break;
        }
        // Find the longest UTF-8-safe substring that fits
        $chunk = mb_strcut($string, 0, $max_bytes, 'UTF-8');
        $chunks[] = $chunk;
        $string = substr($string, strlen($chunk));
    }

    return $chunks;
}

function fml_mint_license_nft_with_ipfs($license_id, $wallet_address = '') {
    error_log("=== Starting NFT mint for license #{$license_id} ===");

    // Validate license exists
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        error_log("NFT mint failed: License #{$license_id} not found");
        return ['success' => false, 'error' => 'License not found'];
    }

    // Handle wallet address - could be passed in or from database
    if (empty($wallet_address)) {
        $wallet_address = fml_get_pod_value($license_pod, 'wallet_address');
    }
    // Handle if it's still an array
    if (is_array($wallet_address)) {
        $wallet_address = $wallet_address[0] ?? '';
    }

    if (empty($wallet_address)) {
        error_log("NFT mint failed: No wallet address provided for license #{$license_id}");
        return ['success' => false, 'error' => 'No wallet address provided'];
    }

    error_log("Wallet address: {$wallet_address}");

    // Check NMKR credentials
    $creds = fml_get_nmkr_credentials();
    if (!$creds['success']) {
        error_log("NFT mint failed: " . $creds['error']);
        return ['success' => false, 'error' => $creds['error']];
    }

    error_log("NMKR Mode: " . $creds['mode']);
    error_log("NMKR API URL: " . $creds['api_url']);

    // Check project status and policy lock
    $project_details = fml_get_nmkr_project_details();
    if ($project_details['success']) {
        error_log("NMKR Project: " . $project_details['project_name']);
        if ($project_details['policy_locked']) {
            error_log("NFT mint failed: Policy is LOCKED (expired: " . $project_details['policy_lock_date'] . ")");
            return [
                'success' => false,
                'error' => 'NMKR policy is locked (expired ' . $project_details['policy_lock_date'] . '). Create a new project with an open policy.'
            ];
        }
        if ($project_details['policy_lock_date']) {
            error_log("Policy lock date: " . $project_details['policy_lock_date']);
        }
    } else {
        error_log("Warning: Could not check project details: " . ($project_details['error'] ?? 'unknown error'));
    }

    // Check mint coupon balance before attempting to mint
    $coupon_balance = fml_get_nmkr_mint_coupon_balance();
    if ($coupon_balance['success']) {
        // Extract balance from NMKR response (mintCouponBalanceCardano field)
        $raw = $coupon_balance['raw'] ?? $coupon_balance['balance'];
        if (is_array($raw)) {
            $balance = $raw['mintCouponBalanceCardano'] ?? $raw['mintCoupons'] ?? $raw['balance'] ?? 0;
        } else {
            $balance = is_numeric($raw) ? $raw : 0;
        }
        error_log("NMKR Mint Coupon Balance: " . $balance);
        if ($balance < 1) {
            error_log("NFT mint failed: No mint coupons available. Balance: " . $balance);
            return [
                'success' => false,
                'error' => 'No mint coupons available in NMKR account. Please purchase mint coupons at https://studio' . ($creds['mode'] === 'preprod' ? '.preprod' : '') . '.nmkr.io'
            ];
        }
    } else {
        error_log("Warning: Could not check mint coupon balance: " . ($coupon_balance['error'] ?? 'unknown error'));
        // Continue anyway - the mint will fail if no coupons, but we'll get a more specific error
    }

    // Get license data - handle arrays from Pods
    $license_url = fml_get_pod_value($license_pod, 'license_url');

    // Validate license URL exists and is accessible
    if (empty($license_url)) {
        error_log("NFT mint failed: No license_url for license #{$license_id}");
        return ['success' => false, 'error' => 'License PDF URL not found - license may not have been generated yet'];
    }

    error_log("License URL: {$license_url}");
    $licensor = $license_pod->field('licensor');
    $project = $license_pod->field('project');
    $datetime = $license_pod->field('datetime');
    $legal_name = $license_pod->field('legal_name');
    $license_type = $license_pod->field('license_type') ?: 'cc_by';

    // Get related song data
    $song_data = $license_pod->field('song');
    if (empty($song_data)) {
        return ['success' => false, 'error' => 'No song associated with license'];
    }

    $song_id = is_array($song_data) ? $song_data['ID'] : $song_data;
    $song_pod = pods('song', $song_id);

    if (!$song_pod || !$song_pod->exists()) {
        return ['success' => false, 'error' => 'Associated song not found'];
    }

    $song_title = $song_pod->field('post_title');

    // Get artist from song
    $artist_data = $song_pod->field('artist');
    $artist_name = 'Unknown Artist';
    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
        }
    }

    // Use short URL for metadata (CIP-25 has 64-byte limit per field)
    // The full PDF will be attached as a subfile - NMKR handles IPFS pinning automatically
    // REST endpoint redirects to actual S3 PDF URL (~40 chars, fits 64-byte limit)
    $license_pdf_short_url = "https://sync.land/wp-json/FML/v1/l/{$license_id}";
    error_log("License PDF short URL for metadata: {$license_pdf_short_url}");
    error_log("Full PDF URL (will be attached as subfile): {$license_url}");

    // Get song image for NFT visual
    $album_data = $song_pod->field('album');
    $song_image = '';
    if (!empty($album_data)) {
        $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
        $song_image = get_the_post_thumbnail_url($album_id, 'full');
    }

    // Fallback to default image - MUST be a publicly accessible URL
    $default_image = 'https://www.sync.land/wp-content/uploads/2024/06/cropped-SyncLand-Logo-optimized-150x150.png';

    if (empty($song_image)) {
        $song_image = $default_image;
        error_log("Using default image - no album art found");
    }

    // Check if image URL is local (NMKR can't access it)
    if (strpos($song_image, '.local') !== false || strpos($song_image, 'localhost') !== false) {
        error_log("Song image is local URL, using default: {$song_image}");
        $song_image = $default_image;
    }

    error_log("Preview image URL: {$song_image}");

    // Generate unique token name - must be short (Cardano has 32 byte limit)
    // Format: SL_{license_id}_{short_timestamp}
    $short_time = base_convert(time(), 10, 36); // Convert timestamp to base36 (shorter)
    $token_name = 'SL' . $license_id . '_' . $short_time;

    error_log("Token name: {$token_name} (length: " . strlen($token_name) . " chars)");

    // Format datetime for display
    $issue_date = !empty($datetime) ? date('Y-m-d', strtotime($datetime)) : date('Y-m-d');

    // Determine license type label — match cart labels
    $license_type_label = ($license_type === 'non_exclusive') ? 'Commercial License' : 'CC-BY 4.0';

    // Check if license URL is accessible from the internet (not localhost)
    if (strpos($license_url, 'localhost') !== false || strpos($license_url, '.local') !== false || strpos($license_url, '127.0.0.1') !== false) {
        error_log("NFT mint warning: License URL appears to be a local URL that NMKR cannot access: {$license_url}");
        return ['success' => false, 'error' => 'License PDF is on localhost - NMKR cannot access local URLs. License URL: ' . $license_url];
    }

    // Upload license PDF to IPFS via Pinata so we have the hash for metadata
    $ipfs_hash = '';
    $ipfs_url = '';
    $pdf_filename = sanitize_file_name("SyncLicense_{$artist_name}_{$song_title}_{$license_id}.pdf");
    $ipfs_result = fml_upload_license_pdf_to_ipfs($license_url, $pdf_filename);
    if ($ipfs_result['success']) {
        $ipfs_hash = $ipfs_result['ipfs_hash'];
        $ipfs_url = $ipfs_result['ipfs_url']; // ipfs://Qm...
        error_log("IPFS upload successful: {$ipfs_url}");
    } else {
        error_log("IPFS upload failed, will use S3 URL as fallback: " . ($ipfs_result['error'] ?? 'unknown'));
    }

    // Get additional license data for metadata
    // Licensor = artist/owner of the song, Licensee = person obtaining the license
    $licensor_name = fml_get_pod_value($license_pod, 'licensor') ?: $artist_name;
    $legal_name_value = $legal_name ?: 'Licensee';

    // Build CIP-25 compliant metadata with per-license details
    // All string values must be <=64 bytes. Use fml_cip25_string() for fields
    // that may exceed 64 bytes — it returns an array of chunks per CIP-25 spec.
    $nft_name = fml_truncate_metadata_string($song_title . ' - ' . $artist_name);

    // Use IPFS URL for license if available, otherwise fall back to S3 URL
    $license_file_src = $ipfs_url ?: $license_url;

    // Determine image mime type from file extension
    $image_ext = strtolower(pathinfo(parse_url($song_image, PHP_URL_PATH), PATHINFO_EXTENSION));
    $image_mimetype = 'image/png';
    if ($image_ext === 'jpg' || $image_ext === 'jpeg') {
        $image_mimetype = 'image/jpeg';
    } elseif ($image_ext === 'gif') {
        $image_mimetype = 'image/gif';
    } elseif ($image_ext === 'webp') {
        $image_mimetype = 'image/webp';
    }

    $token_metadata = [
        // Required CIP-25 fields
        'name' => $nft_name,
        'image' => fml_cip25_string($song_image),
        'mediaType' => $image_mimetype,

        // Files array with license PDF
        'files' => [
            [
                'name' => fml_truncate_metadata_string("License PDF"),
                'src' => fml_cip25_string($license_file_src),
                'mediaType' => 'application/pdf'
            ]
        ],

        // Description — no license type here, it has its own field
        'description' => [
            fml_truncate_metadata_string("Music sync license for '{$song_title}' by {$artist_name}."),
            "Verified on Cardano blockchain via Sync.Land"
        ],

        // License-specific metadata
        'License Type' => fml_truncate_metadata_string($license_type_label),
        'License URL' => fml_cip25_string($license_file_src),
        'Issue Date' => $issue_date,

        // Song/Artist info
        'Title' => fml_truncate_metadata_string($song_title),
        'Artist' => fml_truncate_metadata_string($artist_name),
        'Composer' => fml_truncate_metadata_string($artist_name),
        'Publisher' => fml_truncate_metadata_string($artist_name),

        // Parties — Licensor is the artist/owner, Licensee is the person getting the license
        'Licensor' => fml_truncate_metadata_string($artist_name),
        'Licensee' => fml_truncate_metadata_string($legal_name_value),

        // Terms
        'Territory' => 'Worldwide',
        'Term' => 'Perpetual',
        'Composition/Recording' => 'Master Recording and Sync',

        // Marketplace info
        'Marketplace' => 'Sync.Land',
        'Marketplace URL' => 'https://sync.land',
        'Marketplace Owner' => 'Awen LLC',
    ];

    // Only add optional fields if they have values
    if (!empty($project)) {
        $token_metadata['Project'] = fml_truncate_metadata_string($project);
    }

    $metadata = [
        '721' => [
            $creds['policy_id'] => [
                $token_name => $token_metadata
            ],
            'version' => '1.0'
        ]
    ];

    // ========================================
    // STEP 1: Upload NFT to NMKR Project
    // ========================================
    $upload_url = $creds['api_url'] . '/v2/UploadNft/' . $creds['project_uid'];

    error_log("Attempting to download image for Base64 encoding: " . $song_image);

    // Download image and convert to Base64 (more reliable than URL for NMKR)
    $image_base64 = null;
    $image_response = wp_remote_get($song_image, ['timeout' => 30, 'sslverify' => false]);

    if (!is_wp_error($image_response) && wp_remote_retrieve_response_code($image_response) === 200) {
        $image_data = wp_remote_retrieve_body($image_response);
        if (!empty($image_data)) {
            $image_base64 = base64_encode($image_data);
            error_log("Image downloaded and encoded. Base64 length: " . strlen($image_base64));
        }
    } else {
        $error_msg = is_wp_error($image_response) ? $image_response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($image_response);
        error_log("Failed to download image: " . $error_msg);
    }

    // Build the previewImageNft object
    $preview_image_data = [
        'mimetype' => $image_mimetype,
        'displayname' => 'License Preview'
    ];

    // Use Base64 if we have it, otherwise fall back to URL
    if ($image_base64) {
        $preview_image_data['fileFromBase64'] = $image_base64;
        error_log("Using Base64 encoded image for NMKR upload");
    } else {
        $preview_image_data['fileFromUrl'] = $song_image;
        error_log("Using URL for NMKR upload (Base64 fallback failed): " . $song_image);
    }

    $display_name = fml_truncate_metadata_string("Sync License: {$artist_name} - {$song_title}");

    // Use metadataOverride with full CIP-25 metadata structure
    // NMKR expects metadataOverride as a JSON string, not a nested object
    $upload_data = [
        'tokenname' => $token_name,
        'displayname' => $display_name,
        'metadataOverride' => json_encode($metadata),
        'previewImageNft' => $preview_image_data,
        // Attach license PDF as subfile - NMKR will pin it to IPFS automatically
        'subfiles' => [
            [
                'subfile' => [
                    'mimetype' => 'application/pdf',
                    'fileFromUrl' => $license_url
                ],
                'description' => "Sync License Agreement #{$license_id}"
            ]
        ]
    ];

    error_log("Using metadataOverride with CIP-25 structure. Token: {$token_name}, Artist: {$artist_name}");
    error_log("License PDF short URL in metadata: {$license_pdf_short_url}");
    error_log("License PDF subfile (full S3 URL): {$license_url}");

    // Log the full upload data for debugging
    error_log("=== NMKR Upload Data ===");
    $debug_data = $upload_data;
    if (isset($debug_data['previewImageNft']['fileFromBase64'])) {
        $debug_data['previewImageNft']['fileFromBase64'] = '[BASE64_' . strlen($upload_data['previewImageNft']['fileFromBase64']) . '_chars]';
    }
    error_log("Token Name: {$token_name}");
    error_log("Display Name: {$display_name}");
    error_log("Metadata Override: " . json_encode($upload_data['metadataOverride'] ?? [], JSON_PRETTY_PRINT));
    error_log("API URL: {$upload_url}");

    // Upload the NFT
    $upload_response = wp_remote_post($upload_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($upload_data),
        'timeout' => 120
    ]);

    if (is_wp_error($upload_response)) {
        $license_pod->save(['nft_status' => 'failed']);
        $error_msg = 'NFT upload failed: ' . $upload_response->get_error_message();
        error_log("NMKR Upload failed: " . $upload_response->get_error_message());
        // Update queue to failed
        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'failed', $error_msg);
        }
        return ['success' => false, 'error' => $error_msg];
    }

    $upload_http_code = wp_remote_retrieve_response_code($upload_response);
    $upload_body = json_decode(wp_remote_retrieve_body($upload_response), true);

    error_log("NMKR Upload Response Code: {$upload_http_code}");
    error_log("NMKR Upload Response: " . json_encode($upload_body));

    if ($upload_http_code != 200 && $upload_http_code != 201) {
        $license_pod->save(['nft_status' => 'failed']);
        $error_msg = "Upload failed HTTP {$upload_http_code}";
        if (isset($upload_body['message'])) $error_msg .= ": " . $upload_body['message'];
        elseif (isset($upload_body['errorMessage'])) $error_msg .= ": " . $upload_body['errorMessage'];

        // Update queue to failed
        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'failed', $error_msg);
        }
        return ['success' => false, 'error' => $error_msg, 'response' => $upload_body];
    }

    // Get the NFT UID from upload response
    $nft_uid = $upload_body['nftUid'] ?? $upload_body['nftId'] ?? null;
    if (empty($nft_uid)) {
        $license_pod->save(['nft_status' => 'failed']);
        error_log("NMKR Upload succeeded but no nftUid in response: " . json_encode($upload_body));
        // Update queue to failed
        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'failed', 'Upload succeeded but no NFT UID returned');
        }
        return ['success' => false, 'error' => 'Upload succeeded but no NFT UID returned', 'response' => $upload_body];
    }

    error_log("NFT uploaded successfully. NFT UID: {$nft_uid}");

    // Check NFT details from NMKR to see if there are any issues
    $nft_details_url = $creds['api_url'] . '/v2/GetNftDetails/' . $creds['project_uid'] . '/' . $nft_uid;
    $nft_details_response = wp_remote_get($nft_details_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Accept' => 'application/json'
        ],
        'timeout' => 30
    ]);

    if (!is_wp_error($nft_details_response)) {
        $nft_details = json_decode(wp_remote_retrieve_body($nft_details_response), true);
        error_log("=== NFT Details from NMKR ===");
        error_log("Full details: " . json_encode($nft_details));
        error_log("State: " . ($nft_details['state'] ?? 'unknown'));
        error_log("Blocked: " . (isset($nft_details['blocked']) ? ($nft_details['blocked'] ? 'true' : 'false') : 'unknown'));
        error_log("Error: " . ($nft_details['error'] ?? $nft_details['errorMessage'] ?? $nft_details['blockedReason'] ?? 'none'));

        // If there's an error or it's blocked, return that
        if (!empty($nft_details['error']) || !empty($nft_details['errorMessage'])) {
            $nft_error = $nft_details['error'] ?? $nft_details['errorMessage'];
            error_log("NFT has error in NMKR: {$nft_error}");
            $license_pod->save(['nft_status' => 'failed']);
            if (function_exists('fml_update_nft_queue_item')) {
                fml_update_nft_queue_item($license_id, 'failed', "NMKR error: {$nft_error}");
            }
            return ['success' => false, 'error' => "NMKR NFT error: {$nft_error}", 'nft_details' => $nft_details];
        }

        if (isset($nft_details['blocked']) && $nft_details['blocked'] === true) {
            $block_reason = $nft_details['blockedReason'] ?? $nft_details['blockReason'] ?? 'Unknown reason';
            error_log("NFT is blocked in NMKR. Reason: {$block_reason}");
            error_log("Full NFT details: " . json_encode($nft_details));
            $license_pod->save(['nft_status' => 'failed']);
            if (function_exists('fml_update_nft_queue_item')) {
                fml_update_nft_queue_item($license_id, 'failed', "NFT blocked: {$block_reason}");
            }
            return ['success' => false, 'error' => "NFT blocked by NMKR: {$block_reason}", 'nft_details' => $nft_details];
        }
    } else {
        error_log("Failed to get NFT details: " . $nft_details_response->get_error_message());
    }

    // ========================================
    // STEP 2: Mint and Send the NFT
    // ========================================
    // NMKR API: POST /v2/MintAndSendSpecific/{projectUid}/{nftUid}/{tokencount}/{receiverAddress}
    $mint_url = $creds['api_url'] . '/v2/MintAndSendSpecific/' . $creds['project_uid'] . '/' . $nft_uid . '/1/' . urlencode($wallet_address);

    fml_nft_log('info', "Starting NMKR mint request", [
        'license_id' => $license_id,
        'nft_uid' => $nft_uid,
        'wallet_address' => $wallet_address,
        'mint_url' => $mint_url
    ]);
    error_log("=== NMKR Mint Request ===");
    error_log("Mint URL: {$mint_url}");

    // Try with no body first (some NMKR endpoints don't expect a body)
    $mint_response = wp_remote_request($mint_url, [
        'method' => 'POST',
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Accept' => 'application/json'
        ],
        'timeout' => 120
    ]);

    // If 405, try GET method as some NMKR endpoints use GET
    $mint_http_code = wp_remote_retrieve_response_code($mint_response);
    if ($mint_http_code == 405) {
        fml_nft_log('debug', "POST returned 405, trying GET method", ['license_id' => $license_id]);
        error_log("POST returned 405, trying GET method...");
        $mint_response = wp_remote_get($mint_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $creds['api_key'],
                'Accept' => 'application/json'
            ],
            'timeout' => 120
        ]);
    }

    if (is_wp_error($mint_response)) {
        $error_msg = 'Mint request failed: ' . $mint_response->get_error_message();
        fml_nft_log('error', "NMKR mint request failed", [
            'license_id' => $license_id,
            'nft_uid' => $nft_uid,
            'error' => $mint_response->get_error_message()
        ]);
        fml_store_nft_error($license_id, 'mint_request_failed', $error_msg, [
            'nft_uid' => $nft_uid,
            'wallet_address' => $wallet_address
        ]);
        $license_pod->save(['nft_mint_status' => 'failed']);
        error_log("NMKR Mint failed: " . $mint_response->get_error_message());
        // Update queue to failed
        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'failed', $error_msg);
        }
        return ['success' => false, 'error' => $error_msg];
    }

    $mint_http_code = wp_remote_retrieve_response_code($mint_response);
    $mint_body = json_decode(wp_remote_retrieve_body($mint_response), true);

    fml_nft_log('info', "NMKR mint response received", [
        'license_id' => $license_id,
        'http_code' => $mint_http_code,
        'response' => $mint_body
    ]);
    error_log("NMKR Mint Response Code: {$mint_http_code}");
    error_log("NMKR Mint Response: " . json_encode($mint_body));

    if ($mint_http_code == 200 || $mint_http_code == 201) {
        // IMPORTANT: Check if NMKR actually minted the NFT or just reserved it
        // NMKR returns 200 OK even when just reserving - we need to check the response
        $nft_actually_minted = false;
        $nft_state = 'unknown';
        $tx_hash = null;

        if (isset($mint_body['sendedNft']) && is_array($mint_body['sendedNft']) && !empty($mint_body['sendedNft'])) {
            $sent_nft = $mint_body['sendedNft'][0];
            $nft_actually_minted = isset($sent_nft['minted']) && $sent_nft['minted'] === true;
            $nft_state = $sent_nft['state'] ?? 'unknown';
            $tx_hash = $sent_nft['initialMintTxHash'] ?? $mint_body['txHash'] ?? $mint_body['transactionId'] ?? null;

            error_log("NMKR Response Analysis - minted: " . ($nft_actually_minted ? 'true' : 'false') . ", state: {$nft_state}");
        }

        // If NFT was reserved but not minted, mark as processing/pending
        if (!$nft_actually_minted && in_array($nft_state, ['reserved', 'pending', 'soldalienpending', 'unknown'])) {
            fml_nft_log('info', "NFT reserved, awaiting NMKR processing", [
                'license_id' => $license_id,
                'nft_uid' => $nft_uid,
                'state' => $nft_state,
                'wallet_address' => $wallet_address
            ]);
            error_log("=== NFT Reserved but not minted yet - state: {$nft_state} ===");
            error_log("NFT will be minted asynchronously by NMKR. Status set to 'processing'.");

            // Schedule a follow-up check in 2 minutes to capture early errors
            wp_schedule_single_event(time() + 120, 'fml_check_single_nft_status', [$license_id, $nft_uid]);

            $save_data = [
                'nft_status' => 'processing',  // Minting in progress at NMKR
                'nft_asset_id' => $nft_uid,
                'nft_transaction_hash' => $tx_hash ?? '',  // May be available even before minted=true
                'nft_policy_id' => $creds['policy_id'],
                'nft_asset_name' => $token_name,
                'wallet_address' => $wallet_address,
            ];
            if (!empty($ipfs_hash)) {
                $save_data['nft_ipfs_hash'] = $ipfs_hash;
            }
            $license_pod->save($save_data);

            // Update queue to processing (not completed yet)
            if (function_exists('fml_update_nft_queue_item')) {
                fml_update_nft_queue_item($license_id, 'processing', "NMKR state: {$nft_state} - awaiting blockchain confirmation");
            }

            // Log to monitoring
            if (function_exists('fml_log_event')) {
                fml_log_event('nft', "License #{$license_id} - NFT reserved, awaiting mint", [
                    'nft_uid' => $nft_uid,
                    'nmkr_state' => $nft_state,
                    'minted' => $nft_actually_minted
                ], 'info');
            }

            return [
                'success' => true,
                'partial' => true,
                'message' => 'NFT reserved and queued for minting. Awaiting blockchain confirmation.',
                'data' => [
                    'nft_uid' => $nft_uid,
                    'license_id' => $license_id,
                    'nft_status' => 'processing',
                    'nmkr_state' => $nft_state
                ]
            ];
        }

        // Check if IPFS upload failed (but NFT was minted)
        $ipfs_failed = empty($ipfs_hash);

        if ($ipfs_failed && $nft_actually_minted) {
            // IPFS upload failed but NFT was minted
            error_log("=== NFT Minted but IPFS upload failed - marking as ipfs_pending ===");

            $license_pod->save([
                'nft_status' => 'ipfs_pending',
                'nft_asset_id' => $nft_uid,
                'nft_transaction_hash' => $tx_hash ?? '',
                'nft_policy_id' => $creds['policy_id'],
                'nft_asset_name' => $token_name,
                'wallet_address' => $wallet_address
            ]);

            if (function_exists('fml_update_nft_queue_item')) {
                fml_update_nft_queue_item($license_id, 'ipfs_pending', 'IPFS upload failed - will retry');
            }

            if (function_exists('fml_log_event')) {
                fml_log_event('nft', "License #{$license_id} - NFT minted, IPFS pending", [
                    'nft_uid' => $nft_uid,
                    'tx_hash' => $tx_hash ?? 'pending'
                ], 'warning');
            }

            return [
                'success' => true,
                'partial' => true,
                'message' => 'NFT minted successfully. IPFS upload will retry automatically.',
                'data' => [
                    'nft_uid' => $nft_uid,
                    'license_id' => $license_id,
                    'nft_status' => 'ipfs_pending'
                ]
            ];
        }

        // NFT was actually minted on the blockchain
        $minted_data = [
            'nft_status' => 'minted',
            'nft_asset_id' => $nft_uid,
            'nft_transaction_hash' => $tx_hash ?? '',
            'nft_minted_at' => current_time('mysql'),
            'nft_policy_id' => $creds['policy_id'],
            'nft_asset_name' => $token_name,
            'wallet_address' => $wallet_address
        ];
        if (!empty($ipfs_hash)) {
            $minted_data['nft_ipfs_hash'] = $ipfs_hash;
        }
        $license_pod->save($minted_data);

        // Update queue to completed
        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'completed');
        }

        error_log("=== NFT Minted Successfully! ===");
        error_log("NFT UID: {$nft_uid}");
        error_log("TX Hash: " . ($tx_hash ?? 'pending'));
        error_log("IPFS Hash: {$ipfs_hash}");
        error_log("NMKR State: {$nft_state}");

        // Log success to monitoring
        if (function_exists('fml_log_event')) {
            fml_log_event('nft', "License #{$license_id} NFT minted successfully", [
                'nft_uid' => $nft_uid,
                'tx_hash' => $tx_hash ?? 'pending',
                'ipfs_hash' => $ipfs_hash,
                'nmkr_state' => $nft_state
            ], 'success');
        }

        return [
            'success' => true,
            'message' => 'License NFT minted successfully',
            'data' => [
                'nft_uid' => $nft_uid,
                'transaction_hash' => $tx_hash,
                'token_name' => $token_name,
                'license_id' => $license_id,
                'ipfs_hash' => $ipfs_hash,
                'policy_id' => $creds['policy_id']
            ]
        ];
    } else {
        // Update license status to failed
        $license_pod->save(['nft_status' => 'failed']);

        // Build detailed error message
        $error_detail = "Mint failed HTTP {$mint_http_code}";
        if (is_array($mint_body)) {
            if (isset($mint_body['message'])) {
                $error_detail .= ": " . $mint_body['message'];
            } elseif (isset($mint_body['errorMessage'])) {
                $error_detail .= ": " . $mint_body['errorMessage'];
            } elseif (isset($mint_body['error'])) {
                $error_detail .= ": " . $mint_body['error'];
            }
        }

        error_log("NMKR Mint failed: {$error_detail}");

        // Update queue to failed
        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'failed', $error_detail);
        }

        // Log to monitoring
        if (function_exists('fml_log_event')) {
            fml_log_event('nft', "License #{$license_id} mint failed", [
                'error' => $error_detail,
                'http_code' => $mint_http_code
            ], 'error');
        }

        return [
            'success' => false,
            'error' => $error_detail,
            'http_code' => $mint_http_code,
            'response' => $mint_body
        ];
    }
}


/**
 * ============================================================================
 * LICENSE VERIFICATION ENDPOINT
 * ============================================================================
 */

add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/licenses/(?P<id>\d+)/verify', [
        'methods' => 'GET',
        'callback' => 'fml_verify_license_nft',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);
});

/**
 * Verify NFT status for a license
 */
function fml_verify_license_nft(WP_REST_Request $request) {
    $license_id = $request->get_param('id');

    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return new WP_REST_Response(['success' => false, 'error' => 'License not found'], 404);
    }

    $nft_status = $license_pod->field('nft_status') ?: 'none';
    $nft_transaction_hash = $license_pod->field('nft_transaction_hash');
    $nft_ipfs_hash = $license_pod->field('nft_ipfs_hash');
    $nft_policy_id = $license_pod->field('nft_policy_id');
    $nft_asset_name = $license_pod->field('nft_asset_name');
    $license_type = $license_pod->field('license_type') ?: 'cc_by';

    // Determine verification status
    $is_verified = ($nft_status === 'minted' && !empty($nft_transaction_hash));

    // Build verification response
    $verification = [
        'license_id' => intval($license_id),
        'license_type' => $license_type,
        'license_type_label' => ($license_type === 'non_exclusive') ? 'Commercial License' : 'CC-BY 4.0',
        'nft_verified' => $is_verified,
        'nft_status' => $nft_status,
        'verification_badge' => $is_verified ? 'NFT Verified' : 'Standard License'
    ];

    if ($is_verified) {
        $verification['blockchain'] = [
            'network' => 'Cardano',
            'transaction_hash' => $nft_transaction_hash,
            'policy_id' => $nft_policy_id,
            'asset_name' => $nft_asset_name,
            'explorer_url' => (fml_nmkr_is_mainnet() ? 'https://cardanoscan.io' : 'https://preprod.cardanoscan.io') . "/transaction/{$nft_transaction_hash}"
        ];

        if ($nft_ipfs_hash) {
            $verification['ipfs'] = [
                'hash' => $nft_ipfs_hash,
                'url' => "ipfs://{$nft_ipfs_hash}",
                'gateway_url' => "https://ipfs.io/ipfs/{$nft_ipfs_hash}"
            ];
        }
    }

    // Add license details
    $song_data = $license_pod->field('song');
    if (!empty($song_data)) {
        $song_id = is_array($song_data) ? $song_data['ID'] : $song_data;
        $song_pod = pods('song', $song_id);
        if ($song_pod && $song_pod->exists()) {
            $verification['song'] = [
                'id' => $song_id,
                'title' => $song_pod->field('post_title')
            ];

            $artist_data = $song_pod->field('artist');
            if (!empty($artist_data)) {
                $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
                $artist_pod = pods('artist', $artist_id);
                if ($artist_pod && $artist_pod->exists()) {
                    $verification['artist'] = [
                        'id' => $artist_id,
                        'name' => $artist_pod->field('post_title')
                    ];
                }
            }
        }
    }

    $verification['licensee'] = $license_pod->field('legal_name') ?: $license_pod->field('licensor');
    $verification['issue_date'] = $license_pod->field('datetime');
    $verification['license_url'] = $license_pod->field('license_url');

    return new WP_REST_Response([
        'success' => true,
        'data' => $verification
    ], 200);
}


/**
 * ============================================================================
 * NFT MINTING RETRY LOGIC
 * ============================================================================
 */

/**
 * Retry failed NFT minting
 */
function fml_retry_failed_nft_minting($license_id, $force = false) {
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return ['success' => false, 'error' => 'License not found'];
    }

    $nft_status = $license_pod->field('nft_status');
    // Handle array nft_status
    if (is_array($nft_status)) {
        $nft_status = $nft_status[0] ?? '';
    }
    error_log("Retry NFT minting for license #{$license_id} - Current status: '{$nft_status}'");

    // Allow retry if status is failed, pending, ipfs_pending, empty, or force is true
    if (!$force && $nft_status === 'minted') {
        return ['success' => false, 'error' => 'License NFT already minted'];
    }

    // Allowed retry statuses
    $retryable_statuses = ['failed', 'pending', 'ipfs_pending', 'processing', ''];
    if (!$force && !in_array($nft_status, $retryable_statuses)) {
        return ['success' => false, 'error' => "Cannot retry NFT with status: {$nft_status}"];
    }

    $wallet_address = $license_pod->field('wallet_address');
    // Handle array wallet_address
    if (is_array($wallet_address)) {
        $wallet_address = $wallet_address[0] ?? '';
    }
    if (empty($wallet_address)) {
        return ['success' => false, 'error' => "No wallet address on record. Current nft_status: '{$nft_status}'"];
    }

    // Update status to pending
    $license_pod->save(['nft_status' => 'pending']);

    // Also update the queue if it exists
    if (function_exists('fml_update_nft_queue_item')) {
        fml_update_nft_queue_item($license_id, 'processing');
    }

    // Attempt minting with IPFS
    $result = fml_mint_license_nft_with_ipfs($license_id, $wallet_address);

    // Update the queue with the result
    if (function_exists('fml_update_nft_queue_item')) {
        if ($result['success']) {
            fml_update_nft_queue_item($license_id, 'completed');
        } else {
            fml_update_nft_queue_item($license_id, 'failed', $result['error'] ?? 'Unknown error');
        }
    }

    return $result;
}

/**
 * Force retry NFT minting with a new wallet address
 */
function fml_force_retry_nft_minting($license_id, $wallet_address) {
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return ['success' => false, 'error' => 'License not found'];
    }

    // Save the new wallet address
    $license_pod->save([
        'nft_status' => 'pending',
        'wallet_address' => $wallet_address
    ]);

    // Add to queue
    if (function_exists('fml_add_to_nft_queue')) {
        fml_add_to_nft_queue($license_id, $wallet_address, 'high');
    }

    // Attempt minting
    return fml_mint_license_nft_with_ipfs($license_id, $wallet_address);
}

/**
 * Admin action to retry NFT minting
 */
add_action('wp_ajax_fml_retry_nft_minting', 'fml_admin_retry_nft_minting');
function fml_admin_retry_nft_minting() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $license_id = intval($_POST['license_id'] ?? 0);
    if (!$license_id) {
        wp_send_json_error(['message' => 'License ID required']);
        return;
    }

    $result = fml_retry_failed_nft_minting($license_id);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}


/**
 * ============================================================================
 * AUTOMATIC IPFS RETRY FOR PENDING LICENSES
 * ============================================================================
 */

/**
 * Schedule IPFS retry cron job
 */
add_action('init', function() {
    if (!wp_next_scheduled('fml_retry_ipfs_pending_licenses')) {
        wp_schedule_event(time(), 'hourly', 'fml_retry_ipfs_pending_licenses');
    }
});

/**
 * Process IPFS pending licenses
 */
add_action('fml_retry_ipfs_pending_licenses', 'fml_process_ipfs_pending_licenses');

function fml_process_ipfs_pending_licenses() {
    error_log("=== Running IPFS pending retry cron ===");

    // Find licenses with ipfs_pending status
    $params = [
        'where' => "nft_status.meta_value = 'ipfs_pending'",
        'limit' => 5 // Process max 5 at a time to avoid timeout
    ];

    $licenses = pods('license', $params);
    $count = 0;

    while ($licenses->fetch()) {
        $license_id = $licenses->field('ID');
        $wallet_address = $licenses->field('wallet_address');

        if (is_array($wallet_address)) {
            $wallet_address = $wallet_address[0] ?? '';
        }

        if (empty($wallet_address)) {
            error_log("IPFS retry: License #{$license_id} has no wallet address, skipping");
            continue;
        }

        error_log("IPFS retry: Attempting license #{$license_id}");

        // Update status to processing
        $license_pod = pods('license', $license_id);
        $license_pod->save(['nft_status' => 'processing']);

        // Retry the minting
        $result = fml_mint_license_nft_with_ipfs($license_id, $wallet_address);

        if ($result['success'] && empty($result['partial'])) {
            error_log("IPFS retry: License #{$license_id} succeeded");
            $count++;
        } else {
            error_log("IPFS retry: License #{$license_id} still pending - " . ($result['error'] ?? 'partial success'));
        }
    }

    error_log("=== IPFS pending retry cron complete: {$count} licenses processed ===");
}

/**
 * Manually trigger IPFS retry (for admin use)
 */
add_action('wp_ajax_fml_retry_all_ipfs_pending', 'fml_admin_retry_all_ipfs_pending');

function fml_admin_retry_all_ipfs_pending() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    fml_process_ipfs_pending_licenses();
    wp_send_json_success(['message' => 'IPFS retry process triggered']);
}


/**
 * ============================================================================
 * NFT STATUS POLLING - Check NMKR for processing NFTs
 * ============================================================================
 */

/**
 * Schedule NFT status polling cron job
 */
add_action('init', function() {
    if (!wp_next_scheduled('fml_poll_processing_nfts')) {
        wp_schedule_event(time(), 'every_5_minutes', 'fml_poll_processing_nfts');
    }
});

// Add custom cron schedule for every 5 minutes
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['every_5_minutes'])) {
        $schedules['every_5_minutes'] = [
            'interval' => 300, // 5 minutes
            'display' => 'Every 5 Minutes'
        ];
    }
    return $schedules;
});

/**
 * Poll NMKR for NFT status updates
 */
add_action('fml_poll_processing_nfts', 'fml_check_processing_nfts');

function fml_check_processing_nfts() {
    error_log("=== Polling NMKR for processing NFT status ===");

    // Get NMKR credentials
    $creds = fml_get_nmkr_credentials();
    if (!$creds['success']) {
        error_log("NFT polling: No NMKR credentials configured");
        return;
    }

    // Find licenses with non-terminal NFT statuses that need polling
    $args = [
        'post_type' => 'license',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'meta_query' => [
            [
                'key' => 'nft_status',
                'value' => ['processing', 'pending', 'ipfs_pending'],
                'compare' => 'IN'
            ]
        ]
    ];

    $query = new WP_Query($args);
    $processed = 0;

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $license_id = get_the_ID();

            $license_pod = pods('license', $license_id);
            if (!$license_pod || !$license_pod->exists()) continue;

            $nft_uid = fml_get_pod_value($license_pod, 'nft_asset_id');
            if (empty($nft_uid)) {
                error_log("NFT polling: License #{$license_id} has no nft_asset_id, skipping");
                continue;
            }

            // Poll NMKR for this NFT's status
            $result = fml_poll_nft_status($license_id, $nft_uid, $creds);
            if ($result['updated']) {
                $processed++;
            }
        }
    }

    wp_reset_postdata();

    error_log("=== NFT polling complete: {$processed} licenses updated ===");
}

/**
 * Poll NMKR for a specific NFT's status
 *
 * Note: GetNftDetails returns 404 for NFTs in "reserved" state.
 * We need to check the sold/reserved NFTs list instead, or use GetNftDetailsByTokenname.
 */
function fml_poll_nft_status($license_id, $nft_uid, $creds) {
    error_log("Polling NMKR for NFT: {$nft_uid} (License #{$license_id})");

    // Get the token name from the license to use as fallback
    $license_pod = pods('license', $license_id);
    $token_name = '';
    if ($license_pod && $license_pod->exists()) {
        $token_name = fml_get_pod_value($license_pod, 'nft_asset_name');
    }

    // First try GetNftDetails - works for minted NFTs
    $api_url = $creds['api_url'] . '/v2/GetNftDetails/' . $creds['project_uid'] . '/' . $nft_uid;

    $response = wp_remote_get($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Accept' => 'application/json'
        ],
        'timeout' => 30
    ]);

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // If 404, the NFT might still be in "reserved" state (not yet minted)
    // Try checking by token name instead
    if ($http_code === 404 && !empty($token_name)) {
        error_log("GetNftDetails returned 404, trying GetNftDetailsByTokenname for: {$token_name}");

        $alt_url = $creds['api_url'] . '/v2/GetNftDetailsByTokenname/' . $creds['project_uid'] . '/' . urlencode($token_name);
        $alt_response = wp_remote_get($alt_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $creds['api_key'],
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);

        $alt_http_code = wp_remote_retrieve_response_code($alt_response);
        $alt_body = json_decode(wp_remote_retrieve_body($alt_response), true);

        if ($alt_http_code === 200 && !empty($alt_body)) {
            error_log("GetNftDetailsByTokenname succeeded");
            $http_code = $alt_http_code;
            $body = $alt_body;
        } else {
            error_log("GetNftDetailsByTokenname also failed: HTTP {$alt_http_code}");

            // NFT is likely still pending in NMKR's queue
            // Check the reserved count to see if there are pending NFTs
            $counts_url = $creds['api_url'] . '/v2/GetCounts/' . $creds['project_uid'];
            $counts_response = wp_remote_get($counts_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $creds['api_key'],
                    'Accept' => 'application/json'
                ],
                'timeout' => 15
            ]);
            $counts_body = json_decode(wp_remote_retrieve_body($counts_response), true);

            if (!empty($counts_body['nftCounts']['reserved']) && $counts_body['nftCounts']['reserved'] > 0) {
                error_log("Project has {$counts_body['nftCounts']['reserved']} reserved NFTs - this NFT is likely still pending");
                return ['updated' => false, 'status' => 'pending_in_queue', 'reserved_count' => $counts_body['nftCounts']['reserved']];
            }

            // If no reserved NFTs, mark as potential issue
            error_log("NFT not found and no reserved NFTs in project - marking for investigation");
            return ['updated' => false, 'error' => 'NFT not found in NMKR', 'needs_investigation' => true];
        }
    }

    if (is_wp_error($response)) {
        error_log("NFT polling error for #{$license_id}: " . $response->get_error_message());
        return ['updated' => false, 'error' => $response->get_error_message()];
    }

    error_log("NMKR NFT Details Response (#{$license_id}): HTTP {$http_code}");
    error_log("NMKR NFT State: " . json_encode([
        'state' => $body['state'] ?? 'unknown',
        'minted' => $body['minted'] ?? false,
        'blocked' => $body['blocked'] ?? false,
        'error' => $body['error'] ?? $body['errorMessage'] ?? $body['blockedReason'] ?? null
    ]));

    if ($http_code !== 200) {
        error_log("NFT polling: Non-200 response for #{$license_id}");
        return ['updated' => false, 'error' => "HTTP {$http_code}"];
    }

    $license_pod = pods('license', $license_id);
    $nft_state = $body['state'] ?? 'unknown';
    $is_minted = isset($body['minted']) && $body['minted'] === true;
    $is_blocked = isset($body['blocked']) && $body['blocked'] === true;
    $is_error = !empty($body['error']) || !empty($body['errorMessage']);
    $tx_hash = $body['initialMintTxHash'] ?? $body['transactionHash'] ?? null;
    $asset_id = $body['assetId'] ?? null;
    $fingerprint = $body['fingerprint'] ?? null;

    // Check if NFT was successfully minted
    if ($is_minted && $nft_state === 'sold') {
        error_log("NFT #{$nft_uid} is now MINTED! Updating license #{$license_id}");

        $license_pod->save([
            'nft_status' => 'minted',
            'nft_transaction_hash' => $tx_hash ?? '',
            'nft_minted_at' => current_time('mysql')
        ]);

        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'completed');
        }

        if (function_exists('fml_log_event')) {
            fml_log_event('nft', "License #{$license_id} NFT confirmed minted via polling", [
                'nft_uid' => $nft_uid,
                'tx_hash' => $tx_hash,
                'asset_id' => $asset_id
            ], 'success');
        }

        return ['updated' => true, 'status' => 'minted'];
    }

    // Check if NFT is blocked or errored
    if ($is_blocked || $is_error || $nft_state === 'error') {
        $error_msg = $body['error'] ?? $body['errorMessage'] ?? $body['blockedReason'] ?? 'Unknown NMKR error';

        // Analyze potential causes
        $error_analysis = [];
        if (empty($body['receiveraddress'])) {
            $error_analysis[] = 'Receiver address is empty - wallet address may not have been passed correctly';
        }
        if (!empty($body['blocked'])) {
            $error_analysis[] = 'NFT is blocked by NMKR';
        }

        $full_error = $error_msg;
        if (!empty($error_analysis)) {
            $full_error .= ' | Analysis: ' . implode('; ', $error_analysis);
        }

        fml_nft_log('error', "NFT #{$nft_uid} has ERROR/BLOCKED", [
            'license_id' => $license_id,
            'state' => $nft_state,
            'error_msg' => $error_msg,
            'analysis' => $error_analysis,
            'receiver_address' => $body['receiveraddress'] ?? 'NOT SET',
            'full_nmkr_response' => $body
        ]);

        // Store detailed error info
        fml_store_nft_error($license_id, 'nmkr_error', $full_error, [
            'nft_uid' => $nft_uid,
            'nmkr_state' => $nft_state,
            'nmkr_error' => $error_msg,
            'receiver_address' => $body['receiveraddress'] ?? null,
            'fingerprint' => $body['fingerprint'] ?? null,
            'nmkr_response' => $body
        ]);

        $license_pod->save([
            'nft_mint_status' => 'failed'
        ]);

        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'failed', "NMKR: {$full_error}");
        }

        if (function_exists('fml_log_event')) {
            fml_log_event('nft', "License #{$license_id} NFT minting failed", [
                'nft_uid' => $nft_uid,
                'error' => $full_error,
                'state' => $nft_state
            ], 'error');
        }

        return ['updated' => true, 'status' => 'failed', 'error' => $full_error, 'analysis' => $error_analysis];
    }

    // Still processing - check if it's been too long
    $license_updated = get_post_modified_time('U', true, $license_id);
    $time_since_update = time() - $license_updated;
    $hours_elapsed = round($time_since_update / 3600, 2);

    // After 24 hours, auto-fail the NFT (NMKR should have processed it by now)
    if ($time_since_update > 86400) { // 24 hours
        error_log("NFT #{$nft_uid} has been processing for {$hours_elapsed} hours - auto-failing");

        $license_pod->save([
            'nft_status' => 'failed'
        ]);

        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'failed', "Timeout: NFT did not mint within 24 hours");
        }

        if (function_exists('fml_log_event')) {
            fml_log_event('nft', "License #{$license_id} NFT auto-failed after 24 hour timeout", [
                'nft_uid' => $nft_uid,
                'state' => $nft_state,
                'hours_elapsed' => $hours_elapsed
            ], 'error');
        }

        return ['updated' => true, 'status' => 'failed', 'error' => 'Timeout after 24 hours'];
    }

    // After 1 hour, log a warning but don't fail yet
    if ($time_since_update > 3600) { // More than 1 hour
        error_log("NFT #{$nft_uid} has been processing for {$hours_elapsed} hours - still waiting");

        if (function_exists('fml_log_event')) {
            fml_log_event('nft', "License #{$license_id} NFT processing delayed", [
                'nft_uid' => $nft_uid,
                'state' => $nft_state,
                'hours_elapsed' => $hours_elapsed
            ], 'warning');
        }
    }

    // Still processing, no update needed
    error_log("NFT #{$nft_uid} still processing (state: {$nft_state}, elapsed: {$hours_elapsed}h)");
    return ['updated' => false, 'status' => 'processing', 'state' => $nft_state, 'hours_elapsed' => $hours_elapsed];
}

/**
 * ============================================================================
 * ENHANCED NFT LOGGING AND ERROR HANDLING
 * ============================================================================
 */

/**
 * Log NFT events to a dedicated log file
 *
 * @param string $level   Log level: 'info', 'warning', 'error', 'debug'
 * @param string $message Log message
 * @param array  $context Additional context data
 */
function fml_nft_log($level, $message, $context = []) {
    $log_dir = WP_CONTENT_DIR . '/nft-logs';

    // Create log directory if it doesn't exist
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
        // Add .htaccess to protect logs
        file_put_contents($log_dir . '/.htaccess', 'Deny from all');
    }

    $log_file = $log_dir . '/nft-' . date('Y-m-d') . '.log';

    $timestamp = date('Y-m-d H:i:s');
    $level_upper = strtoupper($level);
    $context_json = !empty($context) ? ' ' . json_encode($context) : '';

    $log_entry = "[{$timestamp}] [{$level_upper}] {$message}{$context_json}\n";

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

    // Also log to PHP error log for critical errors
    if ($level === 'error') {
        error_log("NFT ERROR: {$message}" . $context_json);
    }
}

/**
 * Get detailed NFT status from NMKR using GetNftDetailsById
 * This endpoint provides more detailed error information
 *
 * @param string $nft_uid NMKR NFT UID
 * @param array  $creds   NMKR credentials
 * @return array NFT details or error
 */
function fml_get_nmkr_nft_details($nft_uid, $creds = null) {
    if (!$creds) {
        $creds = [
            'api_key' => fml_get_nmkr_api_key(),
            'api_url' => fml_get_nmkr_api_url(),
            'project_uid' => fml_get_nmkr_project_uid()
        ];
    }

    $url = $creds['api_url'] . '/v2/GetNftDetailsById/' . $nft_uid;

    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Accept' => 'application/json'
        ],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error' => $response->get_error_message()
        ];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => $data['errorMessage'] ?? "HTTP {$http_code}",
            'http_code' => $http_code,
            'raw_response' => $body
        ];
    }

    return [
        'success' => true,
        'data' => $data,
        'state' => $data['state'] ?? 'unknown',
        'minted' => $data['minted'] ?? false,
        'fingerprint' => $data['fingerprint'] ?? null,
        'tx_hash' => $data['initialminttxhash'] ?? null,
        'receiver_address' => $data['receiveraddress'] ?? null
    ];
}

/**
 * Store detailed NFT error info in license meta
 *
 * @param int    $license_id License post ID
 * @param string $error_type Error type identifier
 * @param string $message    Human-readable error message
 * @param array  $details    Full error details from NMKR
 */
function fml_store_nft_error($license_id, $error_type, $message, $details = []) {
    $error_data = [
        'type' => $error_type,
        'message' => $message,
        'timestamp' => current_time('mysql'),
        'timestamp_utc' => gmdate('Y-m-d H:i:s'),
        'details' => $details
    ];

    // Store in post meta
    update_post_meta($license_id, '_nft_last_error', $error_data);

    // Append to error history (keep last 10 errors)
    $error_history = get_post_meta($license_id, '_nft_error_history', true);
    if (!is_array($error_history)) {
        $error_history = [];
    }
    array_unshift($error_history, $error_data);
    $error_history = array_slice($error_history, 0, 10);
    update_post_meta($license_id, '_nft_error_history', $error_history);

    // Log to NFT log file
    fml_nft_log('error', "License #{$license_id}: {$message}", [
        'error_type' => $error_type,
        'nft_uid' => $details['nft_uid'] ?? null,
        'nmkr_state' => $details['nmkr_state'] ?? null,
        'nmkr_response' => $details['nmkr_response'] ?? null
    ]);
}

/**
 * Check NFT status and capture detailed error information
 * Enhanced version that queries NMKR for detailed status
 *
 * @param int    $license_id License post ID
 * @param string $nft_uid    NMKR NFT UID
 * @return array Status result with details
 */
function fml_check_nft_status_detailed($license_id, $nft_uid) {
    fml_nft_log('info', "Checking detailed status for License #{$license_id}", ['nft_uid' => $nft_uid]);

    $creds = [
        'api_key' => fml_get_nmkr_api_key(),
        'api_url' => fml_get_nmkr_api_url(),
        'project_uid' => fml_get_nmkr_project_uid()
    ];

    // Get detailed NFT info
    $nft_info = fml_get_nmkr_nft_details($nft_uid, $creds);

    if (!$nft_info['success']) {
        fml_nft_log('error', "Failed to get NFT details for License #{$license_id}", [
            'nft_uid' => $nft_uid,
            'error' => $nft_info['error']
        ]);
        return $nft_info;
    }

    $data = $nft_info['data'];
    $state = $data['state'] ?? 'unknown';
    $minted = $data['minted'] ?? false;

    fml_nft_log('info', "NFT status for License #{$license_id}", [
        'state' => $state,
        'minted' => $minted,
        'fingerprint' => $data['fingerprint'] ?? null,
        'receiver' => $data['receiveraddress'] ?? null
    ]);

    // Handle different states
    if ($state === 'error') {
        // Capture full error details
        $error_details = [
            'nft_uid' => $nft_uid,
            'nmkr_state' => $state,
            'nmkr_response' => $data,
            'receiver_address' => $data['receiveraddress'] ?? 'NOT SET',
            'fingerprint' => $data['fingerprint'] ?? null,
            'policy_id' => $data['policyid'] ?? null,
            'asset_id' => $data['assetid'] ?? null
        ];

        // Try to determine error cause
        $error_cause = 'Unknown NMKR error';
        if (empty($data['receiveraddress'])) {
            $error_cause = 'Receiver address not set - wallet address may not have been passed correctly';
        }

        fml_store_nft_error($license_id, 'nmkr_mint_error', $error_cause, $error_details);

        // Update license status
        $license_pod = pods('license', $license_id);
        if ($license_pod && $license_pod->exists()) {
            $license_pod->save(['nft_mint_status' => 'failed']);
        }

        return [
            'success' => false,
            'status' => 'failed',
            'state' => $state,
            'error' => $error_cause,
            'details' => $error_details
        ];
    }

    if ($minted && $state === 'sold') {
        fml_nft_log('info', "NFT successfully minted for License #{$license_id}", [
            'fingerprint' => $data['fingerprint'],
            'tx_hash' => $data['initialminttxhash']
        ]);

        return [
            'success' => true,
            'status' => 'minted',
            'state' => $state,
            'fingerprint' => $data['fingerprint'],
            'tx_hash' => $data['initialminttxhash']
        ];
    }

    // Still processing
    return [
        'success' => true,
        'status' => 'processing',
        'state' => $state,
        'minted' => $minted
    ];
}

/**
 * Get NMKR project wallet balance
 * Useful for debugging funding issues
 *
 * @return array Wallet info including balance
 */
function fml_get_nmkr_wallet_balance() {
    $creds = [
        'api_key' => fml_get_nmkr_api_key(),
        'api_url' => fml_get_nmkr_api_url(),
        'project_uid' => fml_get_nmkr_project_uid()
    ];

    $url = $creds['api_url'] . '/v2/GetWalletValidationAddress/' . $creds['project_uid'];

    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Accept' => 'application/json'
        ],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $lovelace = $body['lovelace'] ?? 0;
    $ada = $lovelace / 1000000;

    return [
        'success' => true,
        'address' => $body['address'] ?? null,
        'lovelace' => $lovelace,
        'ada' => $ada,
        'sufficient' => $ada >= 5 // Need at least 5 ADA for minting
    ];
}

/**
 * Get NMKR project error summary
 * Returns counts of NFTs in various states
 *
 * @return array Project NFT counts
 */
function fml_get_nmkr_error_summary() {
    $creds = [
        'api_key' => fml_get_nmkr_api_key(),
        'api_url' => fml_get_nmkr_api_url(),
        'project_uid' => fml_get_nmkr_project_uid()
    ];

    $url = $creds['api_url'] . '/v2/GetCounts/' . $creds['project_uid'];

    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Accept' => 'application/json'
        ],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return [
        'success' => true,
        'total' => $body['nftTotal'] ?? 0,
        'sold' => $body['sold'] ?? 0,
        'free' => $body['free'] ?? 0,
        'reserved' => $body['reserved'] ?? 0,
        'error' => $body['error'] ?? 0,
        'blocked' => $body['blocked'] ?? 0
    ];
}

/**
 * Scheduled action to check a single NFT status
 * This captures errors quickly after a mint attempt
 */
add_action('fml_check_single_nft_status', 'fml_handle_single_nft_check', 10, 2);

function fml_handle_single_nft_check($license_id, $nft_uid) {
    fml_nft_log('info', "Scheduled NFT status check triggered", [
        'license_id' => $license_id,
        'nft_uid' => $nft_uid
    ]);

    $result = fml_check_nft_status_detailed($license_id, $nft_uid);

    if ($result['status'] === 'failed') {
        fml_nft_log('error', "Scheduled check found NFT in error state", [
            'license_id' => $license_id,
            'nft_uid' => $nft_uid,
            'error' => $result['error'] ?? 'Unknown',
            'details' => $result['details'] ?? []
        ]);
    } elseif ($result['status'] === 'minted') {
        fml_nft_log('info', "Scheduled check confirmed NFT minted", [
            'license_id' => $license_id,
            'nft_uid' => $nft_uid,
            'fingerprint' => $result['fingerprint'] ?? null
        ]);
    } else {
        // Still processing - schedule another check in 5 minutes
        fml_nft_log('debug', "NFT still processing, scheduling follow-up check", [
            'license_id' => $license_id,
            'state' => $result['state'] ?? 'unknown'
        ]);
        wp_schedule_single_event(time() + 300, 'fml_check_single_nft_status', [$license_id, $nft_uid]);
    }
}

/**
 * Manually poll all processing NFTs (for admin use)
 */
add_action('wp_ajax_fml_poll_nft_status', 'fml_admin_poll_nft_status');

function fml_admin_poll_nft_status() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    fml_check_processing_nfts();
    wp_send_json_success(['message' => 'NFT status polling triggered']);
}

/**
 * AJAX endpoint to get NFT error details for a license
 */
add_action('wp_ajax_fml_get_nft_errors', 'fml_admin_get_nft_errors');

function fml_admin_get_nft_errors() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $license_id = isset($_GET['license_id']) ? intval($_GET['license_id']) : 0;

    if (!$license_id) {
        wp_send_json_error(['message' => 'License ID required']);
        return;
    }

    $last_error = get_post_meta($license_id, '_nft_last_error', true);
    $error_history = get_post_meta($license_id, '_nft_error_history', true);

    // Get NFT UID from license
    $license_pod = pods('license', $license_id);
    $nft_uid = null;
    if ($license_pod && $license_pod->exists()) {
        $nft_uid = fml_get_pod_value($license_pod, 'nft_asset_id');
    }

    // If we have an NFT UID, get current NMKR status
    $nmkr_status = null;
    if ($nft_uid) {
        $nmkr_status = fml_get_nmkr_nft_details($nft_uid);
    }

    wp_send_json_success([
        'license_id' => $license_id,
        'nft_uid' => $nft_uid,
        'last_error' => $last_error,
        'error_history' => $error_history ?: [],
        'nmkr_current_status' => $nmkr_status
    ]);
}

/**
 * AJAX endpoint to get NMKR project status/health
 */
add_action('wp_ajax_fml_get_nmkr_health', 'fml_admin_get_nmkr_health');

function fml_admin_get_nmkr_health() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $wallet = fml_get_nmkr_wallet_balance();
    $counts = fml_get_nmkr_error_summary();
    $coupons = fml_check_nmkr_connection();

    wp_send_json_success([
        'mode' => fml_get_nmkr_mode(),
        'wallet' => $wallet,
        'nft_counts' => $counts,
        'coupons' => $coupons['data']['free_coupons'] ?? null,
        'api_connected' => $coupons['success'] ?? false
    ]);
}

/**
 * AJAX endpoint to view recent NFT log entries
 */
add_action('wp_ajax_fml_get_nft_logs', 'fml_admin_get_nft_logs');

function fml_admin_get_nft_logs() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
    $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

    $log_file = WP_CONTENT_DIR . '/nft-logs/nft-' . $date . '.log';

    if (!file_exists($log_file)) {
        wp_send_json_success([
            'log_file' => $log_file,
            'exists' => false,
            'entries' => []
        ]);
        return;
    }

    // Read last N lines
    $file_lines = file($log_file);
    $entries = array_slice($file_lines, -$lines);

    wp_send_json_success([
        'log_file' => $log_file,
        'exists' => true,
        'total_lines' => count($file_lines),
        'entries' => $entries
    ]);
}

/**
 * AJAX endpoint to retry a failed NFT mint
 */
add_action('wp_ajax_fml_retry_nft_mint', 'fml_admin_retry_nft_mint');

function fml_admin_retry_nft_mint() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $license_id = isset($_POST['license_id']) ? intval($_POST['license_id']) : 0;

    if (!$license_id) {
        wp_send_json_error(['message' => 'License ID required']);
        return;
    }

    fml_nft_log('info', "Admin triggered NFT retry", ['license_id' => $license_id]);

    // Clear previous error state
    delete_post_meta($license_id, '_nft_last_error');

    // Get wallet address from license
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        wp_send_json_error(['message' => 'License not found']);
        return;
    }

    $wallet_address = fml_get_pod_value($license_pod, 'wallet_address');

    // Attempt mint
    $result = fml_mint_license_nft_with_ipfs($license_id, $wallet_address);

    if ($result['success']) {
        wp_send_json_success([
            'message' => 'NFT mint retry initiated',
            'result' => $result
        ]);
    } else {
        wp_send_json_error([
            'message' => 'NFT mint retry failed',
            'result' => $result
        ]);
    }
}