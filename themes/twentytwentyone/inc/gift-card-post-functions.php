<?php
/**
 * Gift Card Post Creation Functions
 * 
 * This file contains functions for creating gift card posts when orders are placed.
 * Used by both place_cod_order and Blackhawk email functions.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create a gift card post for an order item
 * 
 * @param array $args {
 *     @type int $order_id Order ID
 *     @type int $item_id Order item ID
 *     @type object $item WC_Order_Item_Product object
 *     @type object $product WC_Product object
 *     @type string $recipient_name Recipient name
 *     @type string $recipient_email Recipient email
 *     @type string $recipient_phone Recipient phone (optional)
 *     @type string $sender_name Sender name (optional)
 *     @type string $sender_email Sender email (optional)
 *     @type string $gift_card_number Gift card number (encrypted)
 *     @type string $gift_card_name Gift card/product name
 *     @type string $gift_card_sku Product SKU
 *     @type float $price Gift card price
 *     @type string $delivery_method Delivery method (optional)
 *     @type string $gift_message Gift message (optional)
 *     @type string $image_url Gift card image URL (optional)
 *     @type string $email_animation Email animation URL (optional)
 *     @type string $schedule_date Scheduled delivery date (optional)
 *     @type string $invoice_number Invoice number (optional)
 *     @type string $campaign Campaign value (optional)
 *     @type string $activation_expiry_type Activation expiry type (optional)
 *     @type string $activation_expiry_date Activation expiry date (optional)
 *     @type string $activation_expiry_duration Activation expiry duration (optional)
 *     @type string $activation_expiry_unit Activation expiry unit (optional)
 * }
 * 
 * @return int|WP_Error Gift card post ID on success, WP_Error on failure
 */
function create_gift_card_post_for_order_item($args) {
    $logger = wc_get_logger();
    $context = ['source' => 'gift-card-post-creation'];
    
    // Required fields
    $required_fields = ['order_id', 'item_id', 'product', 'recipient_name', 'recipient_email', 'gift_card_number', 'gift_card_name', 'gift_card_sku', 'price'];
    foreach ($required_fields as $field) {
        if (empty($args[$field])) {
            $logger->error("Missing required field: {$field}", $context);
            return new WP_Error('missing_field', "Missing required field: {$field}");
        }
    }
    
    $order_id = $args['order_id'];
    $item_id = $args['item_id'];
    $product = $args['product'];
    $product_id = $product->get_id();
    
    // Get order to access additional data
    $order = wc_get_order($order_id);
    if (!$order) {
        $logger->error("Order not found: {$order_id}", $context);
        return new WP_Error('order_not_found', "Order not found: {$order_id}");
    }
    
    // Extract arguments with defaults
    $recipient_name = sanitize_text_field($args['recipient_name']);
    $recipient_email = sanitize_email($args['recipient_email']);
    $recipient_phone = isset($args['recipient_phone']) ? sanitize_text_field($args['recipient_phone']) : '';
    $sender_name = isset($args['sender_name']) ? sanitize_text_field($args['sender_name']) : '';
    $sender_email = isset($args['sender_email']) ? sanitize_email($args['sender_email']) : '';
    $gift_card_number = $args['gift_card_number'];
    $gift_card_name = sanitize_text_field($args['gift_card_name']);
    $gift_card_sku = sanitize_text_field($args['gift_card_sku']);
    $price = floatval($args['price']);
    $delivery_method = isset($args['delivery_method']) ? sanitize_text_field($args['delivery_method']) : '';
    $gift_message = isset($args['gift_message']) ? sanitize_textarea_field($args['gift_message']) : '';
    $image_url = isset($args['image_url']) ? esc_url_raw($args['image_url']) : '';
    $email_animation = isset($args['email_animation']) ? esc_url_raw($args['email_animation']) : '';
    $schedule_date = isset($args['schedule_date']) ? sanitize_text_field($args['schedule_date']) : '';
    $invoice_number = isset($args['invoice_number']) ? sanitize_text_field($args['invoice_number']) : '';
    $campaign = isset($args['campaign']) ? sanitize_text_field($args['campaign']) : '';
    $activation_expiry_type = isset($args['activation_expiry_type']) ? sanitize_text_field($args['activation_expiry_type']) : '';
    $activation_expiry_date = isset($args['activation_expiry_date']) ? sanitize_text_field($args['activation_expiry_date']) : '';
    $activation_expiry_duration = isset($args['activation_expiry_duration']) ? sanitize_text_field($args['activation_expiry_duration']) : '';
    $activation_expiry_unit = isset($args['activation_expiry_unit']) ? sanitize_text_field($args['activation_expiry_unit']) : '';
    
    // Generate invoice number if not provided
    if (empty($invoice_number)) {
        $invoice_number = 'INV-' . date('Ymd') . '-' . wp_rand(1000, 9999);
    }
    
    // Build post title
    $price_formatted = number_format($price, 2);
    $letters = chr(rand(65, 90)) . chr(rand(65, 90)); // A–Z
    $numbers = rand(1000, 9999);
    $code = $letters . $numbers;
    
    $post_title = sprintf(
        '%s $%s – %s (#GC-%s)',
        $gift_card_name,
        $price_formatted,
        $recipient_name,
        $code
    );
    
    // Create gift card post
    $gift_card_post_id = wp_insert_post([
        'post_title' => $post_title,
        'post_type' => 'gift_card',
        'post_status' => 'publish',
    ]);

    
    if (is_wp_error($gift_card_post_id)) {
        $logger->error("Failed to create gift card post: " . $gift_card_post_id->get_error_message(), $context);
        return $gift_card_post_id;
    }
    
    // Get 'Is Gift Card Plus?' status value from the product
    $is_gc_plus = get_post_meta($product_id, 'is_it_gift_card_plus_product', true);
    $is_gc_plus_value = ($is_gc_plus === 'true' || $is_gc_plus === '1') ? true : false;
    
    // Save scheduled delivery date if provided
    if (!empty($schedule_date)) {
        update_field('_scheduled_gift_card_delivery', $schedule_date, $gift_card_post_id);
    }
    
    // Save gift card meta data
    update_post_meta($gift_card_post_id, '_gift_card_number_enc', $gift_card_number);
    update_post_meta($gift_card_post_id, '_recipient_name', $recipient_name);
    update_post_meta($gift_card_post_id, '_recipient_email', $recipient_email);
    update_post_meta($gift_card_post_id, '_recipient_phone', $recipient_phone);
    update_post_meta($gift_card_post_id, '_delivery_method', $delivery_method);
    update_post_meta($gift_card_post_id, '_product_sku', $gift_card_sku);
    update_post_meta($gift_card_post_id, '_price', $price);
    update_post_meta($gift_card_post_id, '_sender_name', $sender_name);
    update_post_meta($gift_card_post_id, '_sender_email', $sender_email);
    update_post_meta($gift_card_post_id, '_gift_message', $gift_message);
    update_post_meta($gift_card_post_id, '_image_url', $image_url);
    update_post_meta($gift_card_post_id, '_invoice_number', $invoice_number);
    update_post_meta($gift_card_post_id, '_order_id', $order_id);
    update_post_meta($gift_card_post_id, '_campaign', $campaign);
    update_post_meta($gift_card_post_id, '_card_status', 'inactive');
    update_post_meta($gift_card_post_id, '_is_gc_plus_product', $is_gc_plus_value);
    
    // Get business user info if available
    $customer_id = $order->get_customer_id();
    if ($customer_id) {
        $business_user = get_user_by('ID', $customer_id);
        if ($business_user) {
            update_post_meta($gift_card_post_id, 'business_name_email', $business_user->user_email);
            update_post_meta($gift_card_post_id, 'business_name', $business_user->display_name);
        }
    }
    
    // Handle activation expiry type
    if (!empty($activation_expiry_type)) {
        update_field('_activation_expiry_type', $activation_expiry_type, $gift_card_post_id);
    } else {
        // Try to get from product
        $acf_value = get_field('activation_expiry_type', $product_id);
        if (empty($acf_value)) {
            $acf_value = get_field('_activation_expiry_type', $product_id);
        }
        if (!empty($acf_value)) {
            update_field('_activation_expiry_type', $acf_value, $gift_card_post_id);
        } else {
            // Default
            update_field('_activation_expiry_type', 'no_activation_expiry', $gift_card_post_id);
        }
    }
    

    // No Activation Needed: card is usable in wallet immediately without customer activation
    $saved_activation_type = get_field( '_activation_expiry_type', $gift_card_post_id );
    if ( $saved_activation_type === 'no_activation_needed' ) {
        update_post_meta( $gift_card_post_id, '_card_status', 'active' );
    }
    
    // Handle activation expiry date
    if (!empty($activation_expiry_date)) {
        // Some installations store the ACF field with/without a leading underscore.
        update_field('_activation_expiry_date', $activation_expiry_date, $gift_card_post_id);
        update_field('activation_expiry_date', $activation_expiry_date, $gift_card_post_id);
        update_post_meta($gift_card_post_id, '_activation_expiry_date', $activation_expiry_date);
        update_post_meta($gift_card_post_id, 'activation_expiry_date', $activation_expiry_date);
    }
    
    // Handle activation expiry duration and unit
    if (!empty($activation_expiry_duration)) {
        update_field('_activation_expiry_duration', $activation_expiry_duration, $gift_card_post_id);
    }
    if (!empty($activation_expiry_unit)) {
        update_field('_activation_expiry_unit', $activation_expiry_unit, $gift_card_post_id);
    }
    
    // Handle card usage expiry (from product settings)
    $usage_type_config = get_post_meta($product_id, 'gift_card_expiry_type', true);
    $wallet_usage_type = 'no_expiry';
    $usage_final_date = '';
    $usage_duration = '';
    $usage_unit = '';
    
    if ($usage_type_config === 'gift_set_date') {
        $wallet_usage_type = 'fixed_date';
        $raw_usage_date = get_post_meta($product_id, 'gift_card_expiry_date', true);
        if (!empty($raw_usage_date)) {
            $usage_final_date = date('Y-m-d H:i:s', strtotime($raw_usage_date));
        }
    } elseif ($usage_type_config === 'expiry_period_starts_on_purchase') {
        $wallet_usage_type = 'on_purchase';
        $usage_duration = get_post_meta($product_id, 'gift_card_expiry_duration', true);
        $usage_unit = get_post_meta($product_id, 'gift_card_expiry_unit', true);
    } elseif ($usage_type_config === 'expiry_period_starts_on_activation') {
        $wallet_usage_type = 'on_activation';
        $usage_duration = get_post_meta($product_id, 'gift_card_expiry_duration', true);
        $usage_unit = get_post_meta($product_id, 'gift_card_expiry_unit', true);
    }
    
    // Save usage expiry meta
    update_post_meta($gift_card_post_id, '_expiry_type', $wallet_usage_type);
    delete_post_meta($gift_card_post_id, '_expiry_date');
    delete_post_meta($gift_card_post_id, '_expiry_duration');
    delete_post_meta($gift_card_post_id, '_expiry_unit');
    
    if ($wallet_usage_type === 'fixed_date' && !empty($usage_final_date)) {
        update_post_meta($gift_card_post_id, '_expiry_date', $usage_final_date);
    } elseif ($wallet_usage_type === 'on_purchase' && !empty($usage_duration) && !empty($usage_unit)) {
        // Calculate expiry date = post creation time + duration, and store it.
        $creation_ts = get_post_time('U', false, $gift_card_post_id);
        $expiry_ts  = strtotime("+{$usage_duration} {$usage_unit}", $creation_ts);
        if ($expiry_ts !== false) {
            update_post_meta($gift_card_post_id, '_expiry_date', date('Y-m-d H:i:s', $expiry_ts));
        }
        update_post_meta($gift_card_post_id, '_expiry_duration', $usage_duration);
        update_post_meta($gift_card_post_id, '_expiry_unit', $usage_unit);
    } elseif ($wallet_usage_type === 'on_activation') {
        if (!empty($usage_duration)) {
            update_post_meta($gift_card_post_id, '_expiry_duration', $usage_duration);
        }
        if (!empty($usage_unit)) {
            update_post_meta($gift_card_post_id, '_expiry_unit', $usage_unit);
        }
    }
    
    $logger->info("Created gift card post ID: {$gift_card_post_id} for order {$order_id}, item {$item_id}", $context);
    
    return $gift_card_post_id;
}

/**
 * Send email with PDF attachment for Blackhawk products when order is placed/completed
 * Checks if products are Blackhawk products and sends email using send_gift_cards_email_to_recipient
 */
add_action('woocommerce_payment_complete', 'send_blackhawk_gift_card_email_on_order', 25, 1);
add_action('woocommerce_order_status_completed', 'send_blackhawk_gift_card_email_on_order', 25, 1);
add_action('woocommerce_order_status_processing', 'send_blackhawk_gift_card_email_on_order', 25, 1);

function send_blackhawk_gift_card_email_on_order($order_id) {
    // Check if required functions exist
    if (!function_exists('send_gift_cards_email_to_recipient') || !function_exists('send_giftcard_email_with_pdf')) {
        return;
    }
    
    $logger = wc_get_logger();
    $context = ['source' => 'blackhawk-email'];
    
    $order = wc_get_order($order_id);
    if (!$order) {
        $logger->error("Order not found: {$order_id}", $context);
        return;
    }
    
    // Prevent duplicate emails - check if email already sent for this order
    $email_sent_flag = get_post_meta($order_id, '_blackhawk_email_sent', true);
    if ($email_sent_flag === 'yes') {
        $logger->info("Email already sent for order {$order_id}, skipping", $context);
        return;
    }
    
    // Loop through order items
    $email_sent = false;
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) {
            continue;
        }
        
        // Check if product is a Blackhawk product
        $is_blackhawk = get_post_meta($product->get_id(), '_is_blackhawk_product', true);
        if (empty($is_blackhawk)) {
            // Not a Blackhawk product, skip
            continue;
        }
        
        $logger->info("Found Blackhawk product in order {$order_id}, item {$item_id}", $context);
        
        // Get recipient email (required for sending email)
        $recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
        if (empty($recipient_email)) {
            // Try delivery email as fallback
            $recipient_email = wc_get_order_item_meta($item_id, '_delivery_email', true);
        }
        if (empty($recipient_email)) {
            // Fallback to order billing email
            $recipient_email = $order->get_billing_email();
        }
        
        if (empty($recipient_email)) {
            $logger->warning("No recipient email found for order item {$item_id} in order {$order_id}", $context);
            continue;
        }
        
        // Get recipient information
        $recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
        if (empty($recipient_name)) {
            $recipient_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }
        
        $recipient_phone = wc_get_order_item_meta($item_id, '_recipient_phone', true);
        if (empty($recipient_phone)) {
            $recipient_phone = wc_get_order_item_meta($item_id, 'mobile_number', true);
        }
        
        // Get gift card name/title
        $gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_title', true);
        if (empty($gift_card_name)) {
            $gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_name', true);
        }
        if (empty($gift_card_name)) {
            $gift_card_name = $item->get_name();
        }
        
        $gift_card_sku = wc_get_order_item_meta($item_id, '_gift_card_sku', true);
        if (empty($gift_card_sku)) {
            $gift_card_sku = $product->get_sku();
        }
        
        $price = wc_get_order_item_meta($item_id, '_gift_card_price', true);
        if (empty($price)) {
            $price = $item->get_total();
        }
        
        // Check if gift card post already exists
        $gift_card_post_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
        $gift_card_number = wc_get_order_item_meta($item_id, '_gift_card_number_enc', true);
        
        // If gift card post doesn't exist, create it
        if (empty($gift_card_post_id)) {
            // Generate gift card number if not exists
            if (empty($gift_card_number)) {
                $unique_gift_card_number = generate_unique_gift_card_code();
                
                // Encrypt the gift card number
                $encrypted_gift_card_number = '';
                if (function_exists('encrypt_giftcard_no')) {
                    try {
                        $encrypted_gift_card_number = encrypt_giftcard_no($unique_gift_card_number);
                    } catch (Exception $e) {
                        $logger->error("Failed to encrypt gift card number: " . $e->getMessage(), $context);
                        $encrypted_gift_card_number = $unique_gift_card_number; // Fallback
                    }
                } else {
                    $encrypted_gift_card_number = $unique_gift_card_number;
                }
                
                $gift_card_number = $encrypted_gift_card_number;
                
                // Update order item meta with gift card number
                wc_update_order_item_meta($item_id, '_gift_card_number_enc', $gift_card_number);
            }
            
            // Get sender information
            $sender_name = wc_get_order_item_meta($item_id, '_sender_name', true);
            if (empty($sender_name)) {
                $sender_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            }
            
            $sender_email = wc_get_order_item_meta($item_id, '_sender_email', true);
            if (empty($sender_email)) {
                $sender_email = $order->get_billing_email();
            }
            
            // Get delivery method
            $delivery_method = wc_get_order_item_meta($item_id, '_delivery_method', true);
            
            // Get gift message
            $gift_message = wc_get_order_item_meta($item_id, '_gift_message', true);
            if (empty($gift_message)) {
                $gift_message = wc_get_order_item_meta($item_id, 'gift_message', true);
            }
            
            // Get image URL
            $image_url = wc_get_order_item_meta($item_id, '_gift_card_image', true);
            if (empty($image_url)) {
                $product_image_id = $product->get_image_id();
                if ($product_image_id) {
                    $image_url = wp_get_attachment_image_url($product_image_id, 'full');
                }
            }
            
            // Get email animation
            $email_animation = wc_get_order_item_meta($item_id, 'gift_email_animation', true);
            if (empty($email_animation)) {
                $email_animation = wc_get_order_item_meta($item_id, '_gift_email_animation', true);
            }
            
            // Get scheduled date if exists
            $schedule_date = wc_get_order_item_meta($item_id, '_scheduled_date', true);
            
            // Get invoice number
            $invoice_number = $order->get_meta('_invoice_number', true);
            if (empty($invoice_number)) {
                $invoice_number = 'INV-' . date('Ymd') . '-' . wp_rand(1000, 9999);
                $order->update_meta_data('_invoice_number', $invoice_number);
                $order->save();
            }
            
            // Get activation expiry data from order item meta
            $activation_expiry_type = wc_get_order_item_meta($item_id, '_activation_expiry_type', true);
            $activation_expiry_date = wc_get_order_item_meta($item_id, '_activation_expiry_date', true);
            $activation_expiry_duration = wc_get_order_item_meta($item_id, '_activation_expiry_duration', true);
            $activation_expiry_unit = wc_get_order_item_meta($item_id, '_activation_expiry_unit', true);
            
            // Create gift card post
            $gift_card_post_id = create_gift_card_post_for_order_item([
                'order_id' => $order_id,
                'item_id' => $item_id,
                'product' => $product,
                'recipient_name' => $recipient_name,
                'recipient_email' => $recipient_email,
                'recipient_phone' => $recipient_phone,
                'sender_name' => $sender_name,
                'sender_email' => $sender_email,
                'gift_card_number' => $gift_card_number,
                'gift_card_name' => $gift_card_name,
                'gift_card_sku' => $gift_card_sku,
                'price' => $price,
                'delivery_method' => $delivery_method,
                'gift_message' => $gift_message,
                'image_url' => $image_url,
                'email_animation' => $email_animation,
                'schedule_date' => $schedule_date,
                'invoice_number' => $invoice_number,
                'campaign' => $order->get_meta('_campaign', true),
                'activation_expiry_type' => $activation_expiry_type,
                'activation_expiry_date' => $activation_expiry_date,
                'activation_expiry_duration' => $activation_expiry_duration,
                'activation_expiry_unit' => $activation_expiry_unit,
            ]);
            
            if (is_wp_error($gift_card_post_id)) {
                $logger->error("Failed to create gift card post: " . $gift_card_post_id->get_error_message(), $context);
                continue;
            }
            
            // Update order item meta with gift card post ID
            wc_update_order_item_meta($item_id, '_gift_card_post_id', $gift_card_post_id);
            
            $logger->info("Created gift card post ID: {$gift_card_post_id} for order {$order_id}, item {$item_id}", $context);
        }
        
        // Build order_product_data array (required for PDF generation)
        // This must be a serialized array with product_name as the first element
        $order_product_data = array(
            array(
                'product_name' => $gift_card_name,
                'product_id' => $product->get_id(),
                'product_sku' => $product->get_sku(),
                'price' => $price,
                'quantity' => $item->get_quantity(),
            )
        );
        
        // Build gift card data array for email function
        $gcard_data = array();
        
        // Recipient information
        $gcard_data['recipient_name'] = $recipient_name;
        $gcard_data['recipient_email'] = $recipient_email;
        
        // Gift card details
        $gcard_data['gift_card_name'] = $gift_card_name;
        $gcard_data['gift_card_sku'] = $gift_card_sku;
        $gcard_data['price'] = $price;
        
        // Gift card number
        $gcard_data['gift_card_number'] = $gift_card_number;
        
        // Gift card post ID
        $gcard_data['gift_card_post_id'] = $gift_card_post_id;
        
        // Sender information
        $gcard_data['sender_name'] = wc_get_order_item_meta($item_id, '_sender_name', true);
        if (empty($gcard_data['sender_name'])) {
            $gcard_data['sender_name'] = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }
        
        // Gift message
        $gcard_data['message'] = wc_get_order_item_meta($item_id, '_gift_message', true);
        if (empty($gcard_data['message'])) {
            $gcard_data['message'] = wc_get_order_item_meta($item_id, 'gift_message', true);
        }
        
        // Image URL
        $gcard_data['image_url'] = wc_get_order_item_meta($item_id, '_gift_card_image', true);
        if (empty($gcard_data['image_url'])) {
            // Fallback to product image
            $product_image_id = $product->get_image_id();
            if ($product_image_id) {
                $gcard_data['image_url'] = wp_get_attachment_image_url($product_image_id, 'full');
            }
        }
        
        // Email animation
        $gcard_data['emailAnimation'] = wc_get_order_item_meta($item_id, 'gift_email_animation', true);
        if (empty($gcard_data['emailAnimation'])) {
            $gcard_data['emailAnimation'] = wc_get_order_item_meta($item_id, '_gift_email_animation', true);
        }

        // Video message attachment
        $gcard_data['video_attachment_url'] = wc_get_order_item_meta($item_id, 'gift_video_message', true);
        if (empty($gcard_data['video_attachment_url'])) {
            $gcard_data['video_attachment_url'] = wc_get_order_item_meta($item_id, '_gift_video_message', true);
        }
        
        // CRITICAL: order_product_data must be serialized for PDF generation
        $gcard_data['order_product_data'] = serialize($order_product_data);
        
        // Additional fields
        $gcard_data['amount'] = $gcard_data['price'];
        $gcard_data['card_number'] = $gcard_data['gift_card_number'];
        
        // Logo URLs (required for PDF)
        $gcard_data['logo_giftcardplus'] = wp_get_attachment_url('6230');
        $gcard_data['logo_brand_main'] = wp_get_attachment_url('5824');
        $gcard_data['logo_brand_top'] = wp_get_attachment_url('5108');
        $gcard_data['logo_footer'] = wp_get_attachment_url('5370');
        $gcard_data['email_logo'] = wp_get_attachment_url('5371');
        
        // Support email
        $gcard_data['support_email'] = 'support@giftcardsplus.com.au';
        
        // Send email with PDF using the existing function
        try {
            $logger->info("Sending email for Blackhawk product to {$recipient_email} for order {$order_id}", $context);
            send_giftcard_email_with_pdf($gcard_data, $recipient_email, $order_id);
            $logger->info("Email sent successfully for order item {$item_id} in order {$order_id}", $context);
            $email_sent = true;
        } catch (Exception $e) {
            $logger->error("Error sending email for order item {$item_id} in order {$order_id}: " . $e->getMessage(), $context);
        } catch (Error $e) {
            $logger->error("Fatal error sending email for order item {$item_id} in order {$order_id}: " . $e->getMessage(), $context);
        }
    }
    
    // Mark email as sent to prevent duplicates
    if ($email_sent) {
        update_post_meta($order_id, '_blackhawk_email_sent', 'yes');
    }
}
