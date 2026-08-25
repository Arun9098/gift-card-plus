<?php
/**
 * AJAX Handler: Admin Card Portal
 * Handles backend processing for portal actions (Resend, Swap, etc.)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Card_Portal_AJAX {

    public function init() {
        add_action( 'wp_ajax_gcp_portal_resend_card', array( $this, 'handle_resend_card' ) );

        // Send OTP Hook
        add_action( 'wp_ajax_gcp_portal_send_update_otp', array( $this, 'handle_send_update_otp' ) );

        add_action( 'wp_ajax_gcp_portal_verify_otp_and_update', array( $this, 'handle_verify_otp_and_update' ) );

        add_action( 'wp_ajax_gcp_portal_add_to_wallet', array( $this, 'handle_add_to_wallet' ) );

        add_action( 'wp_ajax_gcp_portal_search_users', array( $this, 'handle_search_users' ) );
        add_action( 'wp_ajax_gcp_portal_verify_wallet_otp_and_add', array( $this, 'handle_verify_wallet_otp_and_add' ) );
    }

    /**
     * Handle Resend Logic
     * Uses the existing 'send_gift_cards_email_to_recipient' function.
     */
    public function handle_resend_card() {
        // Security Check
        check_ajax_referer( 'gcp_admin_portal_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $card_id = isset( $_POST['card_id'] ) ? intval( $_POST['card_id'] ) : 0;
        
        if ( ! $card_id || get_post_type( $card_id ) !== 'gift_card' ) {
            wp_send_json_error( 'Invalid Gift Card ID.' );
        }

        // Prepare Data for the existing email function
        // We construct the $gcard array with all available meta from the CPT.
        // The existing function has fallbacks, but providing data here makes it faster/safer.
        
        $gcard = array(
            'gift_card_post_id' => $card_id,
            'recipient_email'   => get_post_meta( $card_id, '_recipient_email', true ),
            'recipient_name'    => get_post_meta( $card_id, '_recipient_name', true ),
            'recipient_phone'   => get_post_meta( $card_id, '_recipient_phone', true ),
            'sender_name'       => get_post_meta( $card_id, '_sender_name', true ),
            'sender_email'      => get_post_meta( $card_id, '_sender_email', true ),
            'delivery_method'   => get_post_meta( $card_id, '_delivery_method', true ),
            'message'           => get_post_meta( $card_id, '_gift_message', true ),
            'image_url'         => get_post_meta( $card_id, '_image_url', true ),
            'business_user_name'=> get_post_meta( $card_id, 'business_name', true ),
            'gift_card_name'    => get_the_title( $card_id ),
        );

        // Call the Function
        if ( function_exists( 'send_gift_cards_email_to_recipient' ) ) {
            
            // Pass empty array for attachments
            send_gift_cards_email_to_recipient( $gcard, array() );

            // Log the action
            $order_id = get_post_meta( $card_id, '_order_id', true );
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $order->add_order_note( sprintf( 'Gift Card #%d resent manually via Admin Portal.', $card_id ) );
                }
            }

            wp_send_json_success( 'Resend triggered successfully.' );

        } else {
            // Error fallback if the helper file isn't loaded
            wp_send_json_error( 'Error: Email function missing. Please contact developer.' );
        }
    }

    /**
     * Handle: Generate and Email OTP to Admin
     */
    public function handle_send_update_otp() {
        check_ajax_referer( 'gcp_admin_portal_nonce', 'nonce' );

        $user = wp_get_current_user();
        
        if ( ! $user || ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $admin_email = $user->user_email;
        $user_id     = $user->ID;

        // Generate OTP
        $otp_code   = rand( 100000, 999999 );
        
        // Store in Transient (Expires in 5 minutes)
        // Key is unique to this admin user
        $transient_key = 'gcp_admin_otp_' . $user_id;
        set_transient( $transient_key, $otp_code, 2 * MINUTE_IN_SECONDS );

        // Send Email
        $subject = 'Verification Code for Gift Card To Update Delievery Method';
        $message = "Your verification code is: {$otp_code}\n\nThis code expires in 2 minutes.";
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $sent = wp_mail( $admin_email, $subject, $message, $headers );

        if ( $sent ) {
            // Mask email for privacy display
            $parts = explode( '@', $admin_email );
            $masked_email = substr( $parts[0], 0, 1 ) . '***@' . $parts[1];

            wp_send_json_success( array( 
                'message' => 'OTP sent successfully.',
                'email'   => $masked_email 
            ));
        } else {
            wp_send_json_error( 'Failed to send OTP email. Please check server logs.' );
        }
    }

    /**
     * Handle: Verify OTP -> Update Data -> Resend Card (Email OR SMS)
     */
    public function handle_verify_otp_and_update() {
        check_ajax_referer( 'gcp_admin_portal_nonce', 'nonce' );

        $user = wp_get_current_user();
        if ( ! $user || ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $otp_user     = isset( $_POST['otp_code'] ) ? sanitize_text_field( $_POST['otp_code'] ) : '';
        $card_id      = isset( $_POST['card_id'] ) ? intval( $_POST['card_id'] ) : 0;
        $update_data  = isset( $_POST['update_data'] ) ? $_POST['update_data'] : array();

        // 1. Verify OTP
        $transient_key = 'gcp_admin_otp_' . $user->ID;
        $stored_otp    = get_transient( $transient_key );

        if ( empty( $otp_user ) ) {
            wp_send_json_error( 'Please enter the code.' );
        }
        if ( ! $stored_otp ) {
            wp_send_json_error( 'Code expired. Please request a new one.' );
        }
        if ( $stored_otp != $otp_user ) {
            wp_send_json_error( 'Invalid code. Please try again.' );
        }

        // 2. OTP Valid! Update Data
        $method = sanitize_text_field( $update_data['method'] ); // 'email' or 'sms'
        $value  = sanitize_text_field( $update_data['value'] );

        if ( $method === 'sms' ) {
            update_post_meta( $card_id, '_delivery_method', 'sms' );
            update_post_meta( $card_id, '_recipient_phone', $value );
        } else {
            update_post_meta( $card_id, '_delivery_method', 'email' );
            update_post_meta( $card_id, '_recipient_email', $value );
        }

        // 3. Log the Update
        $order_id = get_post_meta( $card_id, '_order_id', true );
        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->add_order_note( sprintf( 'Admin Portal: Verified OTP. Updated contact to %s and resent card via %s.', $value, strtoupper($method) ));
            }
        }

        // 4. Send the Card (SMS or Email)
        $sent_successfully = false;

        // --- FETCH CORRECT CARD TITLE FROM PRODUCT SKU ---
        $card_title = '';
        $product_sku = get_post_meta( $card_id, '_product_sku', true );      

        if ( ! empty( $product_sku ) ) {
            $product_id = wc_get_product_id_by_sku( $product_sku );
            if ( $product_id ) {
                $card_title = get_the_title( $product_id );
            }
        }

        // Fallback if SKU or Product not found
        if ( empty( $card_title ) ) {
            $card_title = get_the_title( $card_id );
        }

        $card_title = html_entity_decode( $card_title, ENT_QUOTES, 'UTF-8' );

        if ( $method === 'sms' ) {
            // --- SMS SENDING LOGIC ---
            
            // A. Gather Data
            $recipient_name = get_post_meta( $card_id, '_recipient_name', true ) ?: '';
            $sender_name    = get_post_meta( $card_id, '_sender_name', true ) ?: 'Gift Cards Plus';
            
            // Get Security PIN (Try multiple keys as per email function logic)
            $security_pin = get_post_meta( $card_id, 'gcard_security_pin', true ) ?: 
                            get_post_meta( $card_id, '_gcard_security_pin', true ) ?: 
                            get_post_meta( $card_id, 'security_pin', true ) ?: 
                            get_post_meta( $card_id, '_security_pin', true ) ?: 'XXXX';

            // Generate Wallet Link
            $recipient_email_for_link = get_post_meta( $card_id, '_recipient_email', true );

            $payload = array(
                'gc_id' => $card_id,
                'email' => $recipient_email,
            );

            $payload_encoded = base64_encode( wp_json_encode( $payload ) );

            $signature = hash_hmac(
                'sha256',
                $payload_encoded,
                wp_salt( 'auth' )
            );

            $token = $payload_encoded . '.' . $signature;

            $redeem_link = add_query_arg(
                array(
                    'action' => 'gcp_add_to_wallet',
                    'token'  => rawurlencode( $token ),
                ),
                home_url('/')
            );

            // Shorten URL if function exists (optional but recommended for SMS)
            if ( function_exists( 'gc_shorten_url_for_sms' ) ) {
                $redeem_link = gc_shorten_url_for_sms( $redeem_link );
            }

            // B. Construct Message
            // "Hi <RECIPIENT NAME>, you've got a <GIFT CARD NAME> from <_sender_name>! Add it to your wallet here: [Link]. Wallet Code: <gcard_security_pin>."
            $sms_message = sprintf(
                "Hi %s, you've got a %s from %s! Add it to your wallet here: %s. Wallet Code: %s.",
                $recipient_name,
                $card_title,
                $sender_name,
                $redeem_link,
                $security_pin
            );

            // C. Send
            if ( function_exists( 'send_sms_via_smsbroadcast' ) ) {
                $sms_result = send_sms_via_smsbroadcast( $value, $sms_message );
                
                if ( $sms_result && isset( $sms_result['success'] ) && $sms_result['success'] ) {
                    $sent_successfully = true;
                    update_post_meta( $card_id, '_gift_card_send', 'Delivered (SMS - Admin Resend)' );
                } else {
                    update_post_meta( $card_id, '_gift_card_send', 'Failed (SMS - Admin Resend)' );
                }
            } else {
                wp_send_json_error( 'SMS function missing.' );
            }
        } else {
            // --- EMAIL SENDING LOGIC (Reuse Existing) ---
            $gcard = array(
                'gift_card_post_id' => $card_id,
                'recipient_email'   => $value, // Use the updated value directly
                'recipient_name'    => get_post_meta( $card_id, '_recipient_name', true ),
                'recipient_phone'   => get_post_meta( $card_id, '_recipient_phone', true ),
                'sender_name'       => get_post_meta( $card_id, '_sender_name', true ),
                'sender_email'      => get_post_meta( $card_id, '_sender_email', true ),
                'delivery_method'   => 'email',
                'message'           => get_post_meta( $card_id, '_gift_message', true ),
                'image_url'         => get_post_meta( $card_id, '_image_url', true ),
                'business_user_name'=> get_post_meta( $card_id, 'business_name', true ),
                'gift_card_name'    => get_the_title( $card_id ),
            );

            if ( function_exists( 'send_gift_cards_email_to_recipient' ) ) {
                send_gift_cards_email_to_recipient( $gcard, array() );
                $sent_successfully = true;
                // Status update handled inside email function, but we can ensure it here too
                update_post_meta( $card_id, '_gift_card_send', 'Delivered (Email - Admin Resend)' );
            } else {
                wp_send_json_error( 'Email function missing.' );
            }
        }

        if ( $sent_successfully ) {
            // Delete OTP after successful use
            delete_transient( $transient_key );
            wp_send_json_success( 'Success' );
        } else {
            wp_send_json_error( 'Update saved, but sending failed.' );
        }
    }

    /**
     * Handle: Add Card to User Wallet
     */
    public function handle_add_to_wallet() {
        check_ajax_referer( 'gcp_admin_portal_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $card_id = isset( $_POST['card_id'] ) ? intval( $_POST['card_id'] ) : 0;
        $email   = isset( $_POST['target_email'] ) ? sanitize_email( $_POST['target_email'] ) : '';

        if ( ! $card_id || empty( $email ) ) {
            wp_send_json_error( 'Missing card ID or email.' );
        }

        // 1. Check if User Exists
        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            // Dynamic error message
            wp_send_json_error( sprintf( 'Failed: No giftcardsplus account under %s. Please ask them to sign up before adding this.', $email ) );
        }

        // 2. Link Card to User
        // We use the meta key `_wallet_user_id` which your Wallet plugin checks for ownership.
        $updated = update_post_meta( $card_id, '_wallet_user_id', $user->ID );

        if ( $updated === false && get_post_meta( $card_id, '_wallet_user_id', true ) == $user->ID ) {
             // Logic: If update returns false, it might mean the value was ALREADY set to this user.
             // We treat this as a success (idempotent).
             $updated = true;
        }

        if ( ! $updated ) {
            wp_send_json_error( 'Failed: Technical error. Please try again or raise to support.' );
        }

        // 3. Log Note
        $order_id = get_post_meta( $card_id, '_order_id', true );
        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->add_order_note( sprintf( 'Admin Portal: Linked Gift Card #%d to user ID %d (%s).', $card_id, $user->ID, $email ) );
            }
        }

        // 4. Check for Expiry
        $expiry_date_str = get_post_meta( $card_id, '_activation_expiry_date', true );
        $is_expired = false;
        
        if ( ! empty( $expiry_date_str ) ) {
            $expiry_ts = strtotime( $expiry_date_str );
            if ( $expiry_ts < current_time( 'timestamp' ) ) {
                $is_expired = true;
            }
        }

        // 5. Generate Wallet Link
        // Since we can't easily link to a "User's Wallet View" for the admin without complex logic,
        // we will link to the User Profile in Admin, or a frontend link if impersonation is available.
        // For now, let's point to the User Edit page in Admin as it allows inspecting the user.
        $wallet_url = get_edit_user_link( $user->ID );

        // Return Success Data
        wp_send_json_success( array(
            'message'     => 'Card added successfully.',
            'wallet_url'  => $wallet_url,
            'is_expired'  => $is_expired
        ));
    }

    /**
     * Handle: Search Users by Email (Autocomplete)
     */
    public function handle_search_users() {
        check_ajax_referer( 'gcp_admin_portal_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error();
        }

        $term = isset( $_GET['term'] ) ? sanitize_text_field( $_GET['term'] ) : '';

        if ( strlen( $term ) < 3 ) {
            wp_send_json_success( array() ); // Minimum 3 chars
        }

        // Search Users (Customers only)
        $user_query = new WP_User_Query( array(
            'search'         => '*' . $term . '*',
            'search_columns' => array( 'user_email', 'user_login' ),
            'role'           => 'customer', // Limit to consumers
            'number'         => 10,
            'fields'         => array( 'ID', 'user_email', 'display_name' ),
        ) );

        $results = array();
        if ( ! empty( $user_query->get_results() ) ) {
            foreach ( $user_query->get_results() as $user ) {
                $results[] = array(
                    'id'    => $user->ID,
                    'email' => $user->user_email,
                    'text'  => $user->user_email . ' (' . $user->display_name . ')'
                );
            }
        }

        wp_send_json_success( $results );
    }

    /**
     * Handle: Verify OTP -> Link Wallet
     */
    public function handle_verify_wallet_otp_and_add() {
        check_ajax_referer( 'gcp_admin_portal_nonce', 'nonce' );

        $user = wp_get_current_user();
        if ( ! $user || ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $otp_user  = isset( $_POST['otp_code'] ) ? sanitize_text_field( $_POST['otp_code'] ) : '';
        $card_id   = isset( $_POST['card_id'] ) ? intval( $_POST['card_id'] ) : 0;
        $email     = isset( $_POST['target_email'] ) ? sanitize_email( $_POST['target_email'] ) : '';

        // 1. Verify OTP
        $transient_key = 'gcp_admin_otp_' . $user->ID;
        $stored_otp    = get_transient( $transient_key );

        if ( empty( $otp_user ) || ! $stored_otp || $stored_otp != $otp_user ) {
            wp_send_json_error( 'Invalid or expired code.' );
        }

        // 2. Validate Target User
        if ( ! $card_id || empty( $email ) ) {
            wp_send_json_error( 'Missing details.' );
        }

        $target_user = get_user_by( 'email', $email );
        if ( ! $target_user ) {
            wp_send_json_error( sprintf( 'Failed: No giftcardsplus account under %s. Please ask them to sign up before adding this.', $email ) );
        }

        // 3. Link Card
        $updated = update_post_meta( $card_id, '_wallet_user_id', $target_user->ID );
        if ( $updated === false && get_post_meta( $card_id, '_wallet_user_id', true ) == $target_user->ID ) {
             $updated = true;
        }

        if ( ! $updated ) {
            wp_send_json_error( 'Failed: Technical error. Please try again or raise to support.' );
        }

        // 4. Log & Cleanup
        delete_transient( $transient_key );
        $order_id = get_post_meta( $card_id, '_order_id', true );
        if ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->add_order_note( sprintf( 'Admin Portal: Linked Gift Card #%d to user %s (verified via OTP).', $card_id, $email ) );
            }
        }

        // 5. Check Expiry
        $is_expired = false;
        $expiry_date_str = get_post_meta( $card_id, '_activation_expiry_date', true );
        if ( ! empty( $expiry_date_str ) && strtotime( $expiry_date_str ) < current_time( 'timestamp' ) ) {
            $is_expired = true;
        }

        wp_send_json_success( array(
            'message'     => 'Card added successfully.',
            'wallet_url'  => get_edit_user_link( $target_user->ID ),
            'is_expired'  => $is_expired
        ));
    }
}