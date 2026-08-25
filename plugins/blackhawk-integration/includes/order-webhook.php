<?php

/**
 * Store an encrypted BHN gift card number on all gift_card posts for an order.
 *
 * @param int    $order_id               WooCommerce order ID.
 * @param string $encrypted_card_number  Already-encrypted card number.
 * @param string $egift_url              Optional BHN eGift URL.
 */
function bhn_store_encrypted_card_number(int $order_id, string $encrypted_card_number, string $egift_url = ''): void {
    $gift_cards = get_posts([
        'post_type'      => 'gift_card',
        'post_status'    => 'publish',
        'posts_per_page' => 500,
        'no_found_rows'  => true,
        'fields'         => 'ids',
        'meta_query'     => [[
            'key'     => '_order_id',
            'value'   => $order_id,
            'compare' => '=',
        ]],
    ]);
    foreach ($gift_cards as $gc_id) {
        update_post_meta($gc_id, '_bhn_card_number_enc', $encrypted_card_number);
        if ($egift_url) {
            update_post_meta($gc_id, '_bhn_egift_url', $egift_url);
        }
    }
    update_post_meta($order_id, '_bhn_card_number_enc', $encrypted_card_number);
    if ($egift_url) {
        update_post_meta($order_id, '_bhn_egift_url', $egift_url);
    }
}

add_action('rest_api_init', function () {
    register_rest_route('blackhawk/v1', '/order-status', [
        'methods'             => 'POST',
        'callback' => 'bhi_handle_order_status_webhook',
        'permission_callback' => 'bhi_verify_blackhawk_api_key',
    ]);
});


function bhn_wc_log( $message, $level = 'info' ) {
    if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->log( $level, $message, [ 'source' => 'bhn-webhook' ] );
    }
    error_log( '[BHN Webhook] ' . $message );
}

/**
 * Pull HTTP Basic Auth credentials off the request.
 * Falls back to parsing the Authorization header directly, since
 * PHP_AUTH_USER/PHP_AUTH_PW aren't always populated (e.g. some
 * PHP-FPM/CGI setups) unless the server explicitly forwards them.
 *
 * @return array{0: string, 1: string} [username, password]
 */
function bhi_get_basic_auth_credentials(WP_REST_Request $request): array {
    if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
        return [ $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ?? '' ];
    }

    $auth_header = $request->get_header( 'authorization' );
    if ( $auth_header && stripos( $auth_header, 'basic ' ) === 0 ) {
        $decoded = base64_decode( substr( $auth_header, 6 ), true );
        if ( $decoded !== false && strpos( $decoded, ':' ) !== false ) {
            return explode( ':', $decoded, 2 );
        }
    }

    return [ '', '' ];
}

function bhi_verify_blackhawk_api_key(WP_REST_Request $request) {
    $api_key    = function_exists( 'gcp_get_bhn_webhook_api_key' ) ? gcp_get_bhn_webhook_api_key() : '';
    $basic_user = function_exists( 'gcp_get_bhn_webhook_basic_auth_user' ) ? gcp_get_bhn_webhook_basic_auth_user() : '';
    $basic_pass = function_exists( 'gcp_get_bhn_webhook_basic_auth_pass' ) ? gcp_get_bhn_webhook_basic_auth_pass() : '';

    if ( $api_key === '' && ( $basic_user === '' || $basic_pass === '' ) ) {
        bhn_wc_log( 'AUTH FAILED: No webhook credentials (API key or Basic Auth) are configured in DB.', 'error' );
        return new WP_Error( 'misconfigured', 'Webhook credentials are not configured.', [ 'status' => 500 ] );
    }

    // Blackhawk authenticates via HTTP Basic Auth (mandatory on their end).
    if ( $basic_user !== '' && $basic_pass !== '' ) {
        [ $provided_user, $provided_pass ] = bhi_get_basic_auth_credentials( $request );

        if ( $provided_user !== '' || $provided_pass !== '' ) {
            if ( hash_equals( $basic_user, $provided_user ) && hash_equals( $basic_pass, $provided_pass ) ) {
                bhn_wc_log( 'AUTH OK (Basic Auth).' );
                return true;
            }

            $user_match = hash_equals( $basic_user, $provided_user );
            $pass_match = hash_equals( $basic_pass, $provided_pass );

            bhn_wc_log( sprintf(
                'AUTH FAILED: Invalid Basic Auth credentials provided. Username %s (received: "%s"). Password %s (received length: %d, expected length: %d).',
                $user_match ? 'matched' : 'did NOT match',
                $provided_user,
                $pass_match ? 'matched' : 'did NOT match',
                strlen( $provided_pass ),
                strlen( $basic_pass )
            ), 'error' );
            return new WP_Error( 'invalid_basic_auth', 'Invalid Basic Auth credentials', [ 'status' => 403 ] );
        }
    }

    // Fall back to X-API-KEY for other callers/testing.
    if ( $api_key !== '' ) {
        $provided_key = $request->get_header( 'x-api-key' );

        if ( empty( $provided_key ) ) {
            bhn_wc_log( 'AUTH FAILED: Missing Basic Auth and X-API-KEY header.', 'error' );
            return new WP_Error( 'missing_credentials', 'Missing Basic Auth credentials or X-API-KEY header', [ 'status' => 401 ] );
        }

        if ( ! hash_equals( $api_key, $provided_key ) ) {
            bhn_wc_log( sprintf(
                'AUTH FAILED: Invalid X-API-KEY provided (received length: %d, expected length: %d).',
                strlen( $provided_key ),
                strlen( $api_key )
            ), 'error' );
            return new WP_Error( 'invalid_api_key', 'Invalid X-API-KEY', [ 'status' => 403 ] );
        }

        bhn_wc_log( 'AUTH OK (X-API-KEY).' );
        return true;
    }

    bhn_wc_log( 'AUTH FAILED: Missing Basic Auth credentials.', 'error' );
    return new WP_Error( 'missing_credentials', 'Missing Basic Auth credentials', [ 'status' => 401 ] );
}

if ( ! function_exists( 'encrypt_giftcard_no' ) ) {
function encrypt_giftcard_no($plainText) {
    $secret = function_exists('gcp_get_gift_card_secret_key') ? gcp_get_gift_card_secret_key() : '';
    if ($secret === '') {
        throw new Exception('Gift card secret key is not configured.');
    }

    $key = hash('sha256', $secret, true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt(
        $plainText,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted === false) {
        throw new Exception('Failed to encrypt gift card number.');
    }

    return base64_encode($iv . $encrypted);
}
}

/**
 * Decrypt gift card number using AES-256-CBC
 * Uses gcp_gift_card_secret_key option (encrypted in DB) for decryption key
 * 
 * @param string $encryptedData Base64 encoded IV + encrypted data
 * @return string The decrypted card number
 */
if ( ! function_exists( 'decrypt_giftcard_no' ) ) {
function decrypt_giftcard_no($encryptedData) {
    $secret = function_exists('gcp_get_gift_card_secret_key') ? gcp_get_gift_card_secret_key() : '';
    if ($secret === '') {
        throw new Exception('Gift card secret key is not configured.');
    }

    $key = hash('sha256', $secret, true);
    $data = base64_decode($encryptedData);

    if ($data === false || strlen($data) < 16) {
        throw new Exception('Invalid encrypted data format.');
    }

    $iv = substr($data, 0, 16);
    $cipherText = substr($data, 16);

    $decrypted = openssl_decrypt(
        $cipherText,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($decrypted === false) {
        throw new Exception('Failed to decrypt gift card number.');
    }

    return $decrypted;
}
}
if ( ! function_exists( 'bhn_get_product_brand' ) ) {
function bhn_get_product_brand($product_id) {
    $terms = wp_get_post_terms($product_id, 'product_brand');
    if (!empty($terms) && !is_wp_error($terms)) {
        return $terms[0]->name;
    }
    return '';
}
}



function bhi_handle_order_status_webhook(WP_REST_Request $request) {
    global $wpdb;

    $data = $request->get_json_params();

    bhn_wc_log( 'Hit at ' . current_time('Y-m-d H:i:s') . ' | IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
    bhn_wc_log( 'Payload keys: ' . implode( ', ', array_keys( (array) $data ) ) . ' | orderNumber: ' . sanitize_text_field( $data['orderNumber'] ?? '' ) . ' | orderStatus: ' . sanitize_text_field( $data['orderStatus'] ?? '' ) );

    $table_name = $wpdb->prefix . 'bhn_order_data_webhook';

    $message_type     = sanitize_text_field($data['messageType'] ?? '');
    $order_number     = sanitize_text_field($data['orderNumber'] ?? '');
    $request_id       = sanitize_text_field( $data['requestId'] ?? '' );
    $order_status     = sanitize_text_field($data['orderStatus'] ?? '');

    bhn_wc_log( "Order: {$order_number} | Status: {$order_status} | RequestId: {$request_id} | MessageType: {$message_type}" );
    $number_of_cards  = intval($data['numberOfCards'] ?? 0);
    $date_submitted   = !empty($data['dateSubmitted']) ? date('Y-m-d H:i:s', strtotime($data['dateSubmitted'])) : null;
    $event_timestamp  = !empty($data['eventTimestamp']) ? date('Y-m-d H:i:s', strtotime($data['eventTimestamp'])) : null;
    $order_error_list = !empty($data['orderErrorList']) ? wp_json_encode($data['orderErrorList']) : null;

    $wpdb->insert($table_name, [
        'message_type'     => $message_type,
        'order_number'     => $order_number,
        'request_id'       => $request_id,
        'order_status'     => $order_status,
        'number_of_cards'  => $number_of_cards,
        'date_submitted'   => $date_submitted,
        'event_timestamp'  => $event_timestamp,
        'order_error_list' => $order_error_list,
        'created_at'       => current_time('mysql'),
    ]);

    // Extract and encrypt cardNumber from webhook response if available
    // BHN webhook response structure:
    // {
    //   "orderNumber": "405506751",
    //   "eGifts": [{
    //     "cardNumber": "BTBJQTFWVFHJFCBV",
    //     ...
    //   }]
    // }
    if (!empty($data['eGifts']) && 
        is_array($data['eGifts']) && 
        !empty($data['eGifts'][0]) && 
        !empty($data['eGifts'][0]['cardNumber'])) {
        
        $card_number = sanitize_text_field($data['eGifts'][0]['cardNumber']);

        try {
            $encrypted_card_number = encrypt_giftcard_no($card_number);

            if (!empty($order_number)) {
                $order_id = $wpdb->get_var($wpdb->prepare("
                    SELECT woo_order_no FROM {$wpdb->prefix}bhi_fetch_order_data
                    WHERE order_number = %s
                    LIMIT 1
                ", $order_number));

                if ($order_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        bhn_store_encrypted_card_number($order_id, $encrypted_card_number);
                        $order->add_order_note('Card number received from BHN webhook and encrypted.');
                    }
                }
            }
        } catch (Exception $e) {
            if (isset($order) && $order) {
                $order->add_order_note('ERROR: Gift card encryption failed. Manual intervention required. ' . $e->getMessage());
            }
        }
    }

    if (!empty($order_number)) {
        $order_id = $wpdb->get_var($wpdb->prepare("
            SELECT woo_order_no FROM {$wpdb->prefix}bhi_fetch_order_data
            WHERE order_number = %s
            LIMIT 1
        ", $order_number));
    
        if ($order_id) {
            $order = wc_get_order($order_id);

            if ($order) {
                $pending_order_number = get_post_meta($order_id, '_bhn_card_number_pending', true);
                if (!empty($pending_order_number)) {
                    $retry_otherData = fetchOtherOrderData($pending_order_number);

                    if (!empty($retry_otherData) && is_array($retry_otherData) &&
                        isset($retry_otherData['eGifts']) &&
                        is_array($retry_otherData['eGifts']) &&
                        !empty($retry_otherData['eGifts'][0]) &&
                        !empty($retry_otherData['eGifts'][0]['cardNumber'])) {

                        $retry_card_number = $retry_otherData['eGifts'][0]['cardNumber'];
                        try {
                            $encrypted_card_number = encrypt_giftcard_no($retry_card_number);
                            bhn_store_encrypted_card_number($order_id, $encrypted_card_number);
                            delete_post_meta($order_id, '_bhn_card_number_pending');
                            delete_post_meta($order_id, '_bhn_card_number_last_check');
                            $order->add_order_note('Card number successfully retrieved and encrypted on retry.');
                        } catch (Exception $e) {
                            $order->add_order_note('ERROR: Gift card encryption failed on retry. Manual intervention required. ' . $e->getMessage());
                        }
                    } else {
                        update_post_meta($order_id, '_bhn_card_number_last_check', current_time('mysql'));
                    }
                }
                
                switch ($order_status) {
                    case 'Complete':
                        
                        $order->update_status('completed', 'Order completed via BHN webhook.');
                        $otherData = fetchOtherOrderData($data['orderNumber']);
                        $order_number_from_api = $data['orderNumber'] ?? '';

                        if (!empty($otherData) && is_array($otherData) &&
                            isset($otherData['eGifts']) &&
                            is_array($otherData['eGifts']) &&
                            !empty($otherData['eGifts'][0]) &&
                            !empty($otherData['eGifts'][0]['cardNumber'])) {

                            $card_number   = $otherData['eGifts'][0]['cardNumber'];
                            $bhn_egift_url = $otherData['eGifts'][0]['url'] ?? '';
                            try {
                                $encrypted_card_number = encrypt_giftcard_no($card_number);
                                bhn_store_encrypted_card_number($order_id, $encrypted_card_number, $bhn_egift_url);
                                delete_post_meta($order_id, '_bhn_card_number_pending');
                            } catch (Exception $e) {
                                $order->add_order_note('ERROR: Gift card encryption failed. Manual intervention required. ' . $e->getMessage());
                            }
                        } else {
                            update_post_meta($order_id, '_bhn_card_number_pending', $order_number_from_api);
                            update_post_meta($order_id, '_bhn_card_number_last_check', current_time('mysql'));
                            $order->add_order_note("Card number not available yet from Blackhawk. Will retry on next webhook call.");
                        }

                        $data_get_value = 'bhn_order';
                        $data['order_id'] = $order_id;
                        if (is_array($otherData)) {
                            order_complete($data, $otherData, $data_get_value);
                        }
                        break;
                    case 'Funding Hold':
                    case 'In Process':
                        $order->update_status('on-hold', 'BHN Funding Hold detected.');
                        break;
                    case 'Failed':
                    case 'Error':
                        $order->update_status('failed', 'BHN Funding Failed detected.');
                        break;
                    default:
                        $order->add_order_note('Blackhawk updated status: ' . sanitize_text_field($order_status));
                        break;
                }

                $order->add_order_note('Blackhawk webhook received. Request ID: ' . sanitize_text_field($request_id));
            }
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Webhook processed successfully',
    ], 200);
}