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
 * Check if an order contains any BHN/Blackhawk products.
 *
 * A product is considered BHN if it has `_is_blackhawk_product` meta truthy.
 *
 * @param WC_Order $order
 * @return bool
 */
function gc_order_has_blackhawk_products($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return false;
    }
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) {
            continue;
        }
        $is_blackhawk = get_post_meta($product->get_id(), '_is_blackhawk_product', true);
        if (!empty($is_blackhawk)) {
            return true;
        }
    }
    return false;
}

/**
 * Build BHN order details array from cart (same structure as from order).
 * Used to pre-submit to Blackhawk at checkout so we can block order creation on failure.
 *
 * @return array Array of product entries for bhi_submit_order orderDetails.
 */
function gc_build_bhn_products_from_cart() {
    $bhn_products = [];
    $productsMap = [];
    if (!function_exists('WC') || !WC()->cart) {
        return $bhn_products;
    }
    $billing_first = function_exists('WC') && WC()->customer ? WC()->customer->get_billing_first_name() : '';
    $billing_last = function_exists('WC') && WC()->customer ? WC()->customer->get_billing_last_name() : '';
    $billing_email = function_exists('WC') && WC()->customer ? WC()->customer->get_billing_email() : '';
    $billing_name = trim($billing_first . ' ' . $billing_last);
    if (empty($billing_name) && function_exists('WC') && WC()->checkout()) {
        $posted = WC()->checkout()->get_posted_data();
        $billing_name = trim((isset($posted['billing_first_name']) ? $posted['billing_first_name'] : '') . ' ' . (isset($posted['billing_last_name']) ? $posted['billing_last_name'] : ''));
        if (empty($billing_email) && !empty($posted['billing_email'])) {
            $billing_email = $posted['billing_email'];
        }
    }
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = isset($cart_item['product_id']) ? (int) $cart_item['product_id'] : 0;
        if (!$product_id) {
            continue;
        }
        $product = isset($cart_item['data']) && is_object($cart_item['data']) ? $cart_item['data'] : wc_get_product($product_id);
        if (!$product || !is_a($product, 'WC_Product')) {
            continue;
        }
        $is_blackhawk = get_post_meta($product->get_id(), '_is_blackhawk_product', true);
        if (empty($is_blackhawk)) {
            continue;
        }
        $recipient_name = isset($cart_item['recipient_name']) ? trim((string) $cart_item['recipient_name']) : '';
        if ($recipient_name === '') {
            $recipient_name = $billing_name;
        }
        $recipient_email = isset($cart_item['recipient_email']) ? sanitize_email($cart_item['recipient_email']) : '';
        if (empty($recipient_email)) {
            $recipient_email = isset($cart_item['delivery_email']) ? sanitize_email($cart_item['delivery_email']) : '';
        }
        if (empty($recipient_email)) {
            $recipient_email = $billing_email;
        }
        $recipient_phone = isset($cart_item['recipient_phone']) ? $cart_item['recipient_phone'] : (isset($cart_item['mobile_number']) ? $cart_item['mobile_number'] : '');
        if (empty($recipient_phone)) {
            $recipient_phone = '000000000';
        }
        $name_parts = explode(' ', $recipient_name, 2);
        $firstname = !empty($name_parts[0]) ? $name_parts[0] : '';
        $lastname = !empty($name_parts[1]) ? $name_parts[1] : '';
        if (empty($lastname)) {
            $lastname = $firstname;
            $firstname = '';
        }
        $sku = $product->get_sku();
        $price = isset($cart_item['gift_card_price']) && floatval($cart_item['gift_card_price']) > 0
            ? $cart_item['gift_card_price']
            : (method_exists($product, 'get_price') ? $product->get_price() : 0);
        $price = (float) $price;
        $qty = max(1, (int) (isset($cart_item['quantity']) ? $cart_item['quantity'] : 1));
        $productsMap_key = $sku . '_' . $price;
        if (!isset($productsMap[$productsMap_key])) {
            $productsMap[$productsMap_key] = [
                'clientRefId' => 'CRI_' . uniqid(),
                'quantity' => 0,
                'amount' => $price,
                'contentProvider' => $sku,
                'recipients' => [],
            ];
        }
        $recipientInfo = [
            'id' => '',
            'firstName' => $firstname,
            'lastName' => $lastname,
            'email' => $recipient_email,
            'address' => ['line1' => '', 'line2' => '', 'city' => '', 'postalCode' => '', 'country' => ''],
        ];
        for ($i = 0; $i < $qty; $i++) {
            $productsMap[$productsMap_key]['recipients'][] = $recipientInfo;
        }
        $productsMap[$productsMap_key]['quantity'] += $qty;
    }
    return array_values($productsMap);
}

/**
 * Extract a user-facing error message from Blackhawk API response.
 *
 * @param array|null $responseData Decoded JSON response from bhi_submit_order.
 * @return string Error message string.
 */
function gc_extract_bhn_error_message($responseData) {
    $extract = function ($data, $depth = 0) use (&$extract) {
        if ($depth > 6) {
            return '';
        }
        if (is_string($data)) {
            return trim($data);
        }
        if (!is_array($data)) {
            return '';
        }
        $preferred_keys = ['message', 'error', 'errorDescription', 'error_message', 'errorMessage', 'statusMessage', 'faultstring', 'faultString', 'reason', 'description', 'detail', 'details'];
        foreach ($preferred_keys as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return trim($data[$key]);
            }
        }
        $container_keys = ['errors', 'validationErrors', 'violations', 'fault', 'orderDetails', 'orderDetail', 'orderDetailResponses', 'eGifts', 'items'];
        foreach ($container_keys as $key) {
            if (!empty($data[$key])) {
                $msg = $extract($data[$key], $depth + 1);
                if ($msg !== '') {
                    return $msg;
                }
            }
        }
        foreach ($data as $value) {
            $msg = $extract($value, $depth + 1);
            if ($msg !== '') {
                return $msg;
            }
        }
        return '';
    };
    $error_reason = '';
    if (!empty($responseData['message'])) {
        $error_reason = $responseData['message'];
    } elseif (!empty($responseData['error'])) {
        $error_reason = $responseData['error'];
    } elseif (!empty($responseData['errorDescription'])) {
        $error_reason = $responseData['errorDescription'];
    } elseif (!empty($responseData['errors']) && is_array($responseData['errors'])) {
        $messages = array_column($responseData['errors'], 'message');
        $error_reason = implode(', ', array_filter($messages));
        if ($error_reason === '' && !empty($responseData['errors'])) {
            $first = $responseData['errors'][0];
            $error_reason = is_string($first) ? $first : wp_json_encode($first);
        }
    }
    if ($error_reason === '' && is_array($responseData)) {
        $not_complete = (isset($responseData['percentComplete']) && (int) $responseData['percentComplete'] === 0)
            || (isset($responseData['isCompleted']) && !$responseData['isCompleted'])
            || (isset($responseData['success']) && !$responseData['success']);
        $error_reason = $not_complete ? __('Your order could not be completed. Please try again.', 'woocommerce') : wp_json_encode($responseData);
    }
    if ($error_reason === '') {
        if ($responseData === null || !is_array($responseData)) {
            $error_reason = __('Invalid response from Blackhawk. Please try again.', 'woocommerce');
        } else {
            $error_reason = wp_json_encode($responseData);
        }
    }
    return $error_reason;
}


function gc_bhn_order_is_actually_complete($order) {
    $order_id = $order->get_id();
    $bhn_order_number = $order->get_meta('_bhn_order_number');
    $bhn_request_id = $order->get_meta('_bhn_request_id');

    if (!empty($bhn_order_number) && function_exists('fetchOrderStatus')) {
        $bhi_order_status = fetchOrderStatus($bhn_order_number, $bhn_request_id);

        if (!empty($bhi_order_status) && isset($bhi_order_status['orderStatus'])) {
            return $bhi_order_status['orderStatus'] === 'Complete';
        }

        // Live call reached BHN but returned no usable status — fall through to
        // the local proxy rather than assuming failure.
    }

    // No BHN order number recorded, or the live call failed/was unavailable —
    // fall back to whether we already have real card data on file for this
    // order, which only ever gets written after BHN genuinely completed it.
    return (bool) get_post_meta($order_id, '_bhn_card_number_enc', true);
}

function gc_maybe_set_wc_status_for_non_bhn_order($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }

    if (gc_order_has_blackhawk_products($order)) {
        if (is_admin() && $order->get_status() === 'completed' && !gc_bhn_order_is_actually_complete($order)) {
            $order->set_status('processing');
            $order->add_order_note('Reverted to Ordered: Blackhawk has not confirmed this order as Complete yet.');
            $order->save();
        }
        return;
    }

    if (is_admin()) {
        return;
    }

    if ($order->needs_payment()) {
        return;
    }

    if ($order->has_status(['cancelled', 'refunded', 'failed', 'trash'])) {
        return;
    }

    $order_id = $order->get_id();
    $default_next = 'completed';
    $next_status = apply_filters('woocommerce_payment_complete_order_status', $default_next, $order_id, $order);

    if (!empty($next_status) && $order->get_status() !== $next_status) {
        // No note to customer; just normalize status (e.g. after payment on frontend).
        $order->set_status($next_status);
        $order->save();
    }

}

function gc_maybe_add_gst_and_fulfillment_to_order($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }

    $fulfillment_total = 0.0;
    $gst_total = 0.0;
    $delivery_total = 0.0;

    foreach ($order->get_items() as $item) {

        if (!is_a($item, 'WC_Order_Item_Product')) {
            continue;
        }

        $product = $item->get_product();
        if (!$product) {
            continue;
        }

        $qty = $item->get_quantity();
        if ($qty <= 0) {
            continue;
        }

        // ✅ Fulfillment
        $fulfillment = $product->get_meta('j_a_c_fulfillment_cost', true);
        if (empty($fulfillment) || !is_numeric($fulfillment)) {
            $fulfillment = $product->get_meta('_supplier_fullfillment_price', true);
        }

        // ✅ GST
        $gst = $product->get_meta('_gst', true);

        // ✅ Delivery Method (IMPORTANT)
        $delivery_method = $item->get_meta('_delivery_method'); // make sure this meta exists

        // sanitize
        $fulfillment = is_numeric($fulfillment) ? (float) $fulfillment : 0;
        $gst = is_numeric($gst) ? (float) $gst : 0;

        $fulfillment_total += $fulfillment * $qty;
        $gst_total += $gst * $qty;

        // ✅ ONLY if delivery method = sms
        if ($delivery_method === 'sms') {
            $delivery_total += 1 * $qty; // or your SMS cost logic if needed
        }
    }

    // ✅ Save meta
    $order->update_meta_data('fullfillment_total', $fulfillment_total);
    $order->update_meta_data('_order_gst', $gst_total);

    // ✅ ONLY save delivery_total if sms exists
    if ($delivery_total > 0) {
        $order->update_meta_data('delivery_total', $delivery_total);
    }

    // ✅ Add fees (ONLY fulfillment + GST)
    $has_fulfillment_fee = false;
    $has_gst_fee = false;

    foreach ($order->get_items('fee') as $fee_item) {
        if ($fee_item->get_name() === 'Fullfillment Cost') {
            $has_fulfillment_fee = true;
        }
        if ($fee_item->get_name() === 'GST Cost') {
            $has_gst_fee = true;
        }
    }

    if ($fulfillment_total > 0 && !$has_fulfillment_fee) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('Fullfillment Cost');
        $fee->set_total($fulfillment_total);
        $order->add_item($fee);
    }

    if ($gst_total > 0 && !$has_gst_fee) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('GST Cost');
        $fee->set_total($gst_total);
        $order->add_item($fee);
    }

    $order->calculate_totals(false);
    $order->save();
}

/**
 * Save a full product/customer details snapshot for the order, matching the
 * "{order_number}_pro_details" / "{order_number}_customer_details" meta keys
 * written by place_cod_order_callback() for manual/COD orders, so reports that
 * read these keys (reports_restAPI.php) also work for standard checkout orders.
 *
 * @param WC_Order $order
 */
function gc_save_pro_and_customer_details_snapshot($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }

    try {
        $order_number = $order->get_order_number();
        $o_customer_id = $order->get_customer_id();
        $o_user_meta = get_user_meta($o_customer_id);
        $all_product_details = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product_meta = get_post_meta($product_id);

            foreach ($product_meta as $key => &$value) {
                if (is_array($value) && count($value) === 1) {
                    $value = $value[0];
                }
            }
            unset($value);

            $acf_fields = function_exists('get_fields') ? get_fields($product_id) : [];
            $merged_fields = array_merge($product_meta, (array) $acf_fields);

            // Safely get quantity to prevent division by zero
            $item_quantity = absint($item->get_quantity());
            $item_quantity = ($item_quantity > 0) ? $item_quantity : 1;
            $item_total = floatval($item->get_total());

            // Calculate price per unit safely - ensure no division by zero
            if ($item_quantity > 0 && is_finite($item_total) && !is_nan($item_total)) {
                $price_per_unit = $item_total / $item_quantity;
            } else {
                $price_per_unit = $item_total; // Use total as price if quantity is invalid
            }

            $all_product_details[] = [
                'product_id' => $product_id,
                'product_name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $price_per_unit,
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'all_fields' => $merged_fields,
            ];
        }

        $serialized_data = maybe_serialize($all_product_details);
        update_post_meta($order->get_id(), "{$order_number}_pro_details", $serialized_data);
        update_post_meta($order->get_id(), "{$order_number}_customer_details", $o_user_meta);
    } catch (DivisionByZeroError $e) {
        // Continue - product details are optional
    } catch (Exception $e) {
        // Continue - product details are optional
    } catch (Error $e) {
        // Continue - product details are optional
    }
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

    // echo '<pre>'; print_r($gift_card_post_id); echo '</pre>';
    // exit;
    
    if (is_wp_error($gift_card_post_id)) {
        $logger->error("Failed to create gift card post: " . $gift_card_post_id->get_error_message(), $context);
        return $gift_card_post_id;
    }
    
    // Get 'Is Gift Card Plus?' status value from the product
    $is_gc_plus = get_post_meta($product_id, 'is_it_gift_card_plus_product', true);
    $is_gc_plus_value = ($is_gc_plus === 'true' || $is_gc_plus === '1') ? true : false;
    
    // echo 'yyy';
    // echo '<pre>';
    // print_r($args);
    // echo '</pre>';
    // exit;
    // Save scheduled delivery date if provided (exactly like place_cod_order)
    if (isset($args['schedule_date'])) {
        update_field('_scheduled_gift_card_delivery', $args['schedule_date'], $gift_card_post_id);
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
    
    // Generate and save wallet code (security pin) - 4 digit code
    $gcard_security_pin = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    update_post_meta($gift_card_post_id, 'gcard_security_pin', $gcard_security_pin);
    
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
            $activation_expiry_type = $acf_value; // Update variable for later use
        } else {
            // Default
            update_field('_activation_expiry_type', 'no_activation_expiry', $gift_card_post_id);
            $activation_expiry_type = 'no_activation_expiry'; // Update variable for later use
        }
    }
    
    // Get the saved activation expiry type to ensure we have the correct value
    $saved_activation_expiry_type = get_field('_activation_expiry_type', $gift_card_post_id);
    if (empty($saved_activation_expiry_type)) {
        $saved_activation_expiry_type = $activation_expiry_type;
    }

    // No Activation Needed: card is usable in wallet immediately without customer activation
    if ( $saved_activation_expiry_type === 'no_activation_needed' ) {
        update_post_meta( $gift_card_post_id, '_card_status', 'active' );
    }
    
    // Handle activation expiry date
    // If type is 'set_period', calculate the date from duration and unit
    if ($saved_activation_expiry_type === 'set_period') {
        // Get duration and unit if not provided
        if (empty($activation_expiry_duration)) {
            $activation_expiry_duration = get_field('activation_expiry_duration', $product_id);
            if (empty($activation_expiry_duration)) {
                $activation_expiry_duration = get_field('_activation_expiry_duration', $product_id);
            }
        }
        if (empty($activation_expiry_unit)) {
            $activation_expiry_unit = get_field('activation_expiry_unit', $product_id);
            if (empty($activation_expiry_unit)) {
                $activation_expiry_unit = get_field('_activation_expiry_unit', $product_id);
            }
        }

        // Calculate activation expiry date from duration + unit, anchored to the scheduled
        // delivery date (not order placement) per spec — falls back to now for instant delivery.
        if (!empty($activation_expiry_duration) && !empty($activation_expiry_unit)) {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
            $anchor_ts = !empty($schedule_date) ? strtotime($schedule_date) : false;
            $now = $anchor_ts !== false ? new DateTime('@' . $anchor_ts) : new DateTime('now', $tz);
            $now->setTimezone($tz);
            switch ($activation_expiry_unit) {
                case 'days':
                    $now->modify("+{$activation_expiry_duration} days");
                    break;
                case 'weeks':
                    $now->modify("+{$activation_expiry_duration} weeks");
                    break;
                case 'months':
                    $now->modify("+{$activation_expiry_duration} months");
                    break;
                case 'years':
                    $now->modify("+{$activation_expiry_duration} years");
                    break;
            }
            $calculated_date = $now->format('Y-m-d H:i:s');
            update_field('field_685396d283d59', $calculated_date, $gift_card_post_id);
            update_post_meta($gift_card_post_id, '_activation_expiry_date', $calculated_date);
            $activation_expiry_date = $calculated_date;
        }

        // Save duration and unit
        if (!empty($activation_expiry_duration)) {
            update_field('_activation_expiry_duration', sanitize_text_field($activation_expiry_duration), $gift_card_post_id);
        }
        if (!empty($activation_expiry_unit)) {
            update_field('_activation_expiry_unit', sanitize_text_field($activation_expiry_unit), $gift_card_post_id);
        }

    } elseif ($saved_activation_expiry_type === 'activation_set_date') {
        // Date is set on the product (or its parent). Read it — caller rarely passes it.
        if (empty($activation_expiry_date)) {
            // Build lookup list: child → WC native parent → custom parent_sku parent
            $lookup_ids = [$product_id];

            $wc_parent_id = $product->get_parent_id();
            if ($wc_parent_id && $wc_parent_id !== $product_id) {
                $lookup_ids[] = $wc_parent_id;
            }

            $custom_parent_sku = get_post_meta($product_id, 'parent_sku', true);
            if (!empty($custom_parent_sku)) {
                $custom_parent_id = wc_get_product_id_by_sku($custom_parent_sku);
                if ($custom_parent_id && $custom_parent_id !== $product_id && !in_array($custom_parent_id, $lookup_ids)) {
                    $lookup_ids[] = $custom_parent_id;
                }
            }

            foreach ($lookup_ids as $lid) {
                $v = get_field('activation_expiry_date', $lid);
                if (!empty($v)) { $activation_expiry_date = $v; break; }
                $v = get_post_meta($lid, 'activation_expiry_date', true);
                if (!empty($v)) { $activation_expiry_date = $v; break; }
            }
        }

        if (!empty($activation_expiry_date)) {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
            $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'd/m/Y g:i a', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d', 'd-m-Y'];
            $save_date = sanitize_text_field($activation_expiry_date);
            foreach ($formats as $fmt) {
                $dt = DateTime::createFromFormat($fmt, $activation_expiry_date, $tz);
                $errs = DateTime::getLastErrors();
                if ($dt !== false && empty($errs['warning_count']) && empty($errs['error_count'])) {
                    $save_date = $dt->format('Y-m-d H:i:s');
                    break;
                }
            }
            // Use ACF field key — update_field('_activation_expiry_date') fails silently on gift_card posts
            update_field('field_685396d283d59', $save_date, $gift_card_post_id);
            update_post_meta($gift_card_post_id, '_activation_expiry_date', $save_date);
        }

    } elseif (!empty($activation_expiry_date)) {
        // For any other type with a provided date
        update_field('field_685396d283d59', $activation_expiry_date, $gift_card_post_id);
        update_post_meta($gift_card_post_id, '_activation_expiry_date', $activation_expiry_date);
    }
    
    // Handle activation expiry duration and unit (for non-set_period cases)
    if ($saved_activation_expiry_type !== 'set_period') {
        if (!empty($activation_expiry_duration)) {
            update_field('_activation_expiry_duration', $activation_expiry_duration, $gift_card_post_id);
        }
        if (!empty($activation_expiry_unit)) {
            update_field('_activation_expiry_unit', $activation_expiry_unit, $gift_card_post_id);
        }
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

    // Mirror onto the gift_card post's ACF "Gift Card Expiry" fields (gift_card_expiry_type/
    // _date/_duration/_unit) so reports reading these fields directly are populated too.
    update_field('gift_card_expiry_type', $usage_type_config, $gift_card_post_id);
    delete_field('gift_card_expiry_date', $gift_card_post_id);
    delete_field('gift_card_expiry_duration', $gift_card_post_id);
    delete_field('gift_card_expiry_unit', $gift_card_post_id);

    if ($wallet_usage_type === 'fixed_date' && !empty($usage_final_date)) {
        update_post_meta($gift_card_post_id, '_expiry_date', $usage_final_date);
        update_field('gift_card_expiry_date', $usage_final_date, $gift_card_post_id);
    } elseif (in_array($wallet_usage_type, ['on_purchase', 'on_activation'])) {
        if (!empty($usage_duration)) {
            update_post_meta($gift_card_post_id, '_expiry_duration', $usage_duration);
            update_field('gift_card_expiry_duration', $usage_duration, $gift_card_post_id);
        }
        if (!empty($usage_unit)) {
            update_post_meta($gift_card_post_id, '_expiry_unit', $usage_unit);
            update_field('gift_card_expiry_unit', $usage_unit, $gift_card_post_id);
        }
    }

    $logger->info("Created gift card post ID: {$gift_card_post_id} for order {$order_id}, item {$item_id}", $context);
    
    return $gift_card_post_id;
}

/**
 * Send email with PDF attachment for all products when order is placed/completed
 * Creates gift card posts and sends email using send_gift_cards_email_to_recipient
 */
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

    // Business user lookup (used for the brand banner in the recipient email — Havit/Gyprock
    // get their own banner regardless of product type, everyone else gets the default banner).
    // Computed once here so every gcard array built below (immediate-send and scheduled) has it.
    $business_user_id    = $order->get_user_id();
    $business_user_name  = '';
    $business_user_email = '';
    if ($business_user_id > 0) {
        $business_user = get_userdata($business_user_id);
        if ($business_user) {
            $business_user_name  = $business_user->display_name;
            $business_user_email = $business_user->user_email;
        }
    }

    // For NON-BHN orders: normalize status to WooCommerce default (processing/completed) dynamically.
    // Do this early so it runs even if we return later (e.g. email already sent).
    gc_maybe_set_wc_status_for_non_bhn_order($order);
    
    // Calculate and add GST and fulfillment costs to order (for normal checkout orders)
    // This reads product meta and adds fees + order meta, matching place_cod_order behavior
    gc_maybe_add_gst_and_fulfillment_to_order($order);

    // Save the same "{order_number}_pro_details" / "{order_number}_customer_details"
    // snapshot that place_cod_order_callback() saves for manual orders, so reports
    // that read these keys also work for standard checkout orders.
    gc_save_pro_and_customer_details_snapshot($order);

    // Prevent duplicate emails - check if email already sent for this order
    // $email_sent_flag = get_post_meta($order_id, '_gift_card_email_sent', true);
    // if ($email_sent_flag === 'yes') {
    //     $logger->info("Email already sent for order {$order_id}, skipping", $context);
    //     return;
    // }

    $email_sent_flag = get_post_meta($order_id, '_gift_card_email_sent', true);
    if ($email_sent_flag === 'yes') {
        $logger->info("Email already sent for order {$order_id}, skipping", $context);
        return;
    }
    // Set flag immediately to block the other 2 hooks from also running
    update_post_meta($order_id, '_gift_card_email_sent', 'yes');
    
    // Check if BHN integration function exists
    $bhn_sent_flag = get_post_meta($order_id, '_bhn_order_sent', true);
    if ($bhn_sent_flag !== 'yes' && function_exists('bhi_submit_order')) {
        // Collect Blackhawk products and send to BHN
        $bhn_products = [];
        $productsMap = [];
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            
            // Check if product is a Blackhawk product
            $is_blackhawk = get_post_meta($product->get_id(), '_is_blackhawk_product', true);
            if (empty($is_blackhawk)) {
                continue;
            }
            
            // Get recipient information
            $recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
            if (empty($recipient_name)) {
                $recipient_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            }
            
            // Split name into first and last
            $name_parts = explode(' ', $recipient_name, 2);
            $firstname = !empty($name_parts[0]) ? $name_parts[0] : '';
            $lastname = !empty($name_parts[1]) ? $name_parts[1] : '';
            if (empty($lastname)) {
                $lastname = $firstname;
                $firstname = '';
            }
            
            $recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
            if (empty($recipient_email)) {
                $recipient_email = wc_get_order_item_meta($item_id, '_delivery_email', true);
            }
            if (empty($recipient_email)) {
                $recipient_email = $order->get_billing_email();
            }
            
            $recipient_phone = wc_get_order_item_meta($item_id, '_recipient_phone', true);
            if (empty($recipient_phone)) {
                $recipient_phone = wc_get_order_item_meta($item_id, 'mobile_number', true);
            }
            if (empty($recipient_phone)) {
                $recipient_phone = '000000000';
            }
            
            $sku = $product->get_sku();
            $price = wc_get_order_item_meta($item_id, '_gift_card_price', true);
            if (empty($price)) {
                $price = $item->get_total();
            }
            $productsMap_key = $sku . '_' . $price;
            
            if (!isset($productsMap[$productsMap_key])) {
                $productsMap[$productsMap_key] = [
                    'clientRefId' => 'CRI_' . uniqid(),
                    'quantity' => 0,
                    'amount' => $price,
                    'contentProvider' => $sku,
                    'recipients' => [],
                ];
            }
            
            $recipientInfo = [
                'id' => '',
                'firstName' => $firstname,
                'lastName' => $lastname,
                'email' => $recipient_email,
                'address' => [
                    'line1' => '',
                    'line2' => '',
                    'city' => '',
                    'postalCode' => '',
                    'country' => '',
                ],
            ];
            
            $productsMap[$productsMap_key]['recipients'][] = $recipientInfo;
            $productsMap[$productsMap_key]['quantity'] += 1;
        }
        
        // Convert map to indexed array
        $bhn_products = array_values($productsMap);
        
        // Send to BHN if there are Blackhawk products
        if (!empty($bhn_products)) {
            // Resolve Client Program ID — constant takes precedence, then WC settings option
            $CLIENTPROGRAMID = function_exists('gcp_get_bhn_client_program_id') ? gcp_get_bhn_client_program_id() : '';
            
            if (!empty($CLIENTPROGRAMID)) {
                $bhi_uniq_id = uniqid('SGB_');
                $responseData = null;
                $bhi_output_for_request_data = null;
                $preorder_raw = $order->get_meta('_bhn_preorder_response');
                if (!empty($preorder_raw)) {
                    $preorder = json_decode($preorder_raw, true);
                    if (!empty($preorder['response']) && isset($preorder['response']['success']) && $preorder['response']['success'] === true) {
                        $responseData = $preorder['response'];
                        $bhi_uniq_id = isset($preorder['request_id']) ? $preorder['request_id'] : $bhi_uniq_id;
                        $bhi_output_for_request_data = isset($preorder['request']) && is_array($preorder['request']) ? $preorder['request'] : null;
                        $order->delete_meta_data('_bhn_preorder_response');
                        $order->save();
                    }
                }
                if ($responseData === null) {
                    $bhi_output = [
                        'clientProgramNumber' => $CLIENTPROGRAMID,
                        'paymentType' => 'DRAW_DOWN',
                        'millisecondsToWait' => 15000,
                        'orderDetails' => $bhn_products,
                        'returnCardNumberAndPIN' => "true",
                    ];
                    $response = bhi_submit_order($bhi_output, $bhi_uniq_id);
                    $responseData = json_decode($response, true);
                    $bhi_output_for_request_data = $bhi_output;
                }
                
                if (isset($responseData['success']) && $responseData['success'] === true) {
                    $current_datetime = current_time('timestamp');
                    $meta_key = 'egift_order_details_' . $responseData['orderNumber'];
                    
                    $bhi_egift_order_details = [
                        'requestId' => $bhi_uniq_id,
                        'transactionId' => $responseData['transactionId'],
                        'orderNumber' => $responseData['orderNumber'],
                        'requestData' => $bhi_output_for_request_data,
                        'hitTime' => $current_datetime,
                    ];
                    
                    // Save to order customer (if available) or order meta
                    $customer_id = $order->get_customer_id();
                    if ($customer_id) {
                        update_user_meta($customer_id, $meta_key, $bhi_egift_order_details);
                    }
                    
                    // Save BHN order details to order meta
                    $order->update_meta_data('_bhn_order_number', $responseData['orderNumber']);
                    $order->update_meta_data('_bhn_transaction_id', $responseData['transactionId']);
                    $order->update_meta_data('_bhn_request_id', $bhi_uniq_id);
                    $order->update_meta_data('_bhn_order_details', $bhi_egift_order_details);
                    
                    // Handle BHN order status (check for Funding Hold)
                    $bhi_order_number = $responseData['orderNumber'];
                    $bhi_order_status = [];
                    $bhi_order_data = [];
                    
                    if (function_exists('fetchOrderStatus')) {
                        $bhi_order_status = fetchOrderStatus($bhi_order_number, $bhi_uniq_id);
                    }
                    
                    if (function_exists('fetchOtherOrderData')) {
                        $bhi_order_data = fetchOtherOrderData($bhi_order_number);
                    }
                    
                    $mergedData = array_merge($bhi_order_status, $bhi_order_data);
                    
                    // Check for Funding Hold or In Process status
                    if (!empty($bhi_order_status) && isset($bhi_order_status['orderStatus'])) {
                        if ($bhi_order_status['orderStatus'] == 'Funding Hold' || $bhi_order_status['orderStatus'] == 'In Process') {
                            $order->set_status('on-hold');
                            $order->update_status('on-hold', 'BHN Funding Hold detected');
                            $logger->info("Order {$order_id} set to on-hold due to BHN Funding Hold or In Process status", $context);
                        }
                    }
                    
                    $order->save();
                    
                    // Save order status and data to database table
                    if (!empty($bhi_order_number)) {
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'bhi_fetch_order_data';
                        
                        $bhi_order_status_request_id = isset($bhi_order_status['requestId']) ? $bhi_order_status['requestId'] : null;
                        $order_status_json = !empty($bhi_order_status) ? wp_json_encode($bhi_order_status) : null;
                        $order_data_json = !empty($bhi_order_data) ? wp_json_encode($bhi_order_data) : null;
                        $merged_json = (!empty($bhi_order_status) && !empty($bhi_order_data)) ? wp_json_encode($mergedData) : null;
                        
                        $wpdb->insert(
                            $table_name,
                            [
                                'order_number' => $bhi_order_number,
                                'woo_order_no' => $order_id,
                                'request_id' => $bhi_order_status_request_id,
                                'order_status_response' => $order_status_json,
                                'order_data_response' => $order_data_json,
                                'merged_response' => $merged_json,
                                'api_hit_time' => current_time('mysql'),
                            ],
                            ['%s', '%d', '%s', '%s', '%s', '%s']
                        );
                        
                        if (!empty($bhi_order_status) && !empty($bhi_order_data)) {
                            $mergedData['api_hit_time'] = current_time('Y-m-d H:i:s');
                        }
                    }
                    
                    // Save data in bhn_order_logs table (same as place_cod_order)
                    global $wpdb;
                    $bhn_logs_table = $wpdb->prefix . 'bhn_order_logs';
                    
                    // Build order_data structure similar to place_cod_order format
                    $order_data_for_logs = [
                        'recipients' => [],
                    ];
                    
                    // Build recipients array from order items
                    foreach ($order->get_items() as $item_id => $item) {
                        $product = $item->get_product();
                        if (!$product) {
                            continue;
                        }
                        
                        // Check if product is a Blackhawk product
                        $is_blackhawk = get_post_meta($product->get_id(), '_is_blackhawk_product', true);
                        if (empty($is_blackhawk)) {
                            continue;
                        }
                        
                        // Get recipient information
                        $recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
                        if (empty($recipient_name)) {
                            $recipient_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                        }
                        
                        $recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
                        if (empty($recipient_email)) {
                            $recipient_email = wc_get_order_item_meta($item_id, '_delivery_email', true);
                        }
                        if (empty($recipient_email)) {
                            $recipient_email = $order->get_billing_email();
                        }
                        
                        $recipient_phone = wc_get_order_item_meta($item_id, '_recipient_phone', true);
                        if (empty($recipient_phone)) {
                            $recipient_phone = wc_get_order_item_meta($item_id, 'mobile_number', true);
                        }
                        if (empty($recipient_phone)) {
                            $recipient_phone = '000000000';
                        }
                        
                        // Get product details
                        $sku = $product->get_sku();
                        $price = wc_get_order_item_meta($item_id, '_gift_card_price', true);
                        if (empty($price)) {
                            $price = $item->get_total();
                        }
                        
                        $gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_title', true);
                        if (empty($gift_card_name)) {
                            $gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_name', true);
                        }
                        if (empty($gift_card_name)) {
                            $gift_card_name = $item->get_name();
                        }
                        
                        // Build product data structure
                        $product_data = [
                            'productId' => $product->get_id(),
                            'productName' => $gift_card_name,
                            'sku' => $sku,
                            'price' => floatval($price),
                            'quantity' => intval($item->get_quantity()),
                            'bhnPro' => true, // This is a Blackhawk product
                        ];
                        
                        // Find or create recipient entry
                        $recipient_found = false;
                        foreach ($order_data_for_logs['recipients'] as $r_key => $recipient) {
                            if ($recipient['email'] === $recipient_email) {
                                // Add product to existing recipient
                                $order_data_for_logs['recipients'][$r_key]['products'][] = $product_data;
                                $recipient_found = true;
                                break;
                            }
                        }
                        
                        if (!$recipient_found) {
                            // Create new recipient entry
                            $order_data_for_logs['recipients'][] = [
                                'name' => $recipient_name,
                                'email' => $recipient_email,
                                'phone' => $recipient_phone,
                                'products' => [$product_data],
                            ];
                        }
                    }
                    
                    // Only save if there are Blackhawk products (recipients with products)
                    if (!empty($order_data_for_logs['recipients'])) {
                        // Filter to only include recipients with products (same as place_cod_order)
                        foreach ($order_data_for_logs['recipients'] as $r_key => $recipient) {
                            if (empty($recipient['products'])) {
                                unset($order_data_for_logs['recipients'][$r_key]);
                            }
                        }
                        
                        if (!empty($order_data_for_logs['recipients'])) {
                            $serialized_order_details = maybe_serialize($order_data_for_logs);
                            $bhn_details_json = wp_json_encode($mergedData ?? []);
                            
                            $wpdb->insert(
                                $bhn_logs_table,
                                [
                                    'order_no' => (int) $order_id,
                                    'order_details' => $serialized_order_details,
                                    'bhn_details' => $bhn_details_json,
                                    'order_time' => current_time('mysql'),
                                ],
                                ['%d', '%s', '%s', '%s']
                            );
                            
                            $logger->info("Saved order data to bhn_order_logs table for order {$order_id}", $context);
                        }
                    }
                    
                    // Fetch card numbers from BHN API
                    if (!empty($bhi_order_data) && is_array($bhi_order_data) && 
                        isset($bhi_order_data['eGifts']) && 
                        is_array($bhi_order_data['eGifts']) && 
                        !empty($bhi_order_data['eGifts'])) {
                        
                        // Update gift card posts with BHN card numbers
                        $egift_index = 0;
                        foreach ($order->get_items() as $item_id => $item) {
                            $product = $item->get_product();
                            if (!$product) {
                                continue;
                            }
                            
                            // Check if product is a Blackhawk product
                            $is_blackhawk = get_post_meta($product->get_id(), '_is_blackhawk_product', true);
                            if (empty($is_blackhawk)) {
                                continue;
                            }
                            
                            // Get gift card post ID for this item
                            $gift_card_post_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
                            
                            if (!empty($gift_card_post_id) && isset($bhi_order_data['eGifts'][$egift_index])) {
                                $egift = $bhi_order_data['eGifts'][$egift_index];
                                $bhn_card_number = isset($egift['cardNumber']) ? sanitize_text_field($egift['cardNumber']) : '';
                                $bhn_pin = isset($egift['pin']) ? sanitize_text_field($egift['pin']) : '';
                                
                                if (!empty($bhn_card_number)) {
                                    // Encrypt the BHN card number
                                    if (function_exists('encrypt_giftcard_no')) {
                                        try {
                                            $encrypted_bhn_card_number = encrypt_giftcard_no($bhn_card_number);
                                            // Update gift card post with encrypted BHN card number
                                            update_post_meta($gift_card_post_id, '_gift_card_number_enc', $encrypted_bhn_card_number);
                                            update_post_meta($gift_card_post_id, '_bhn_card_number_enc', $encrypted_bhn_card_number);
                                            // Also update order item meta
                                            wc_update_order_item_meta($item_id, '_gift_card_number_enc', $encrypted_bhn_card_number);
                                            
                                            // Save PIN if available
                                            if (!empty($bhn_pin)) {
                                                update_post_meta($gift_card_post_id, '_bhn_pin', $bhn_pin);
                                            }
                                            
                                            $logger->info("Updated gift card post {$gift_card_post_id} with BHN card number for order {$order_id}, item {$item_id}", $context);
                                        } catch (Exception $e) {
                                            $logger->error("Failed to encrypt BHN card number for gift card post {$gift_card_post_id}: " . $e->getMessage(), $context);
                                        }
                                    } else {
                                        // Fallback: save unencrypted (not recommended but better than nothing)
                                        update_post_meta($gift_card_post_id, '_gift_card_number_enc', $bhn_card_number);
                                        update_post_meta($gift_card_post_id, '_bhn_card_number_enc', $bhn_card_number);
                                        wc_update_order_item_meta($item_id, '_gift_card_number_enc', $bhn_card_number);
                                        $logger->warning("encrypt_giftcard_no function not available. Saved unencrypted BHN card number for gift card post {$gift_card_post_id}", $context);
                                    }
                                }
                                
                                $egift_index++;
                            }
                        }
                    } else {
                        $logger->warning("Card numbers not available yet from BHN API for order {$order_id}. Will be fetched later via webhook.", $context);
                        // Mark for later retrieval
                        update_post_meta($order_id, '_bhn_card_number_pending', $bhi_order_number);
                    }
                    
                    // Add order notes
                    $order->add_order_note('BHN Order Number: ' . $responseData['orderNumber']);
                    $order->add_order_note('BHN Transaction ID: ' . $responseData['transactionId']);
                    
                    $logger->info("Successfully sent Blackhawk products to BHN for order {$order_id}. Order Number: {$responseData['orderNumber']}", $context);
                } else {
                    // Use exact error message from Blackhawk API (e.g. value restriction min/max)
                    $error_reason = '';
                    // Extract a meaningful error message from BHN responses (including nested structures)
                    $extract_bhn_error_message = function ($data, $depth = 0) use (&$extract_bhn_error_message) {
                        if ($depth > 6) {
                            return '';
                        }
                        if (is_string($data)) {
                            return trim($data);
                        }
                        if (!is_array($data)) {
                            return '';
                        }

                        $preferred_keys = [
                            'message',
                            'error',
                            'errorDescription',
                            'error_message',
                            'errorMessage',
                            'statusMessage',
                            'faultstring',
                            'faultString',
                            'reason',
                            'description',
                            'detail',
                            'details',
                        ];

                        foreach ($preferred_keys as $key) {
                            if (!empty($data[$key]) && is_string($data[$key])) {
                                return trim($data[$key]);
                            }
                        }

                        $container_keys = [
                            'errors',
                            'validationErrors',
                            'violations',
                            'fault',
                            'orderDetails',
                            'orderDetail',
                            'orderDetailResponses',
                            'eGifts',
                            'items',
                        ];

                        foreach ($container_keys as $key) {
                            if (!empty($data[$key])) {
                                $msg = $extract_bhn_error_message($data[$key], $depth + 1);
                                if ($msg !== '') {
                                    return $msg;
                                }
                            }
                        }

                        foreach ($data as $value) {
                            $msg = $extract_bhn_error_message($value, $depth + 1);
                            if ($msg !== '') {
                                return $msg;
                            }
                        }

                        return '';
                    };
                    if (!empty($responseData['message'])) {
                        $error_reason = $responseData['message'];
                    } elseif (!empty($responseData['error'])) {
                        $error_reason = $responseData['error'];
                    } elseif (!empty($responseData['errorDescription'])) {
                        $error_reason = $responseData['errorDescription'];
                    } elseif (!empty($responseData['errors']) && is_array($responseData['errors'])) {
                        $messages = array_column($responseData['errors'], 'message');
                        $error_reason = implode(', ', array_filter($messages));
                        if ($error_reason === '' && !empty($responseData['errors'])) {
                            $first = $responseData['errors'][0];
                            $error_reason = is_string($first) ? $first : wp_json_encode($first);
                        }
                    }
                    if ($error_reason === '' && is_array($responseData)) {
                        
                        $not_complete = (isset($responseData['percentComplete']) && (int) $responseData['percentComplete'] === 0)
                            || (isset($responseData['isCompleted']) && !$responseData['isCompleted'])
                            || (isset($responseData['success']) && !$responseData['success']);
                        $error_reason = $not_complete ? 'Your Order Not Complete Error From BHN' : wp_json_encode($responseData);
                    }
                    if ($error_reason === '') {
                        $error_reason = $responseData === null ? 'Invalid response from Blackhawk.' : wp_json_encode($responseData);
                    }
                    
                    $logger->error("Failed to send Blackhawk products to BHN for order {$order_id}. Error: {$error_reason}", $context);
                    $logger->error("Blackhawk full response for order {$order_id}: " . wp_json_encode($responseData), $context);
                    $order->add_order_note('BHN Order Failed: ' . $error_reason);
                    $order->update_meta_data('_bhn_order_error', $error_reason);
                    $order->save();
                }
            } else {
                $logger->warning("BLACKHAWK_INTEGRATION_CLIENTPROGRAMID not defined. Skipping BHN integration for order {$order_id}", $context);
            }
            
            // Mark BHN order as sent (regardless of success/failure or CLIENTPROGRAMID)
            update_post_meta($order_id, '_bhn_order_sent', 'yes');
        }
    }
    
    // Refresh order object to get latest status (especially after BHN integration)
    $order = wc_get_order($order_id);
    if (!$order) {
        $logger->error("Order not found after refresh: {$order_id}", $context);
        return;
    }
    
    // First, create gift card posts for all items (regardless of order status)
    // Create one gift card post per product quantity (single product page / checkout flow)
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) {
            continue;
        }
        
        $item_qty = max(1, (int) $item->get_quantity());
        $existing_ids = wc_get_order_item_meta($item_id, '_gift_card_post_ids', true);
        if (!is_array($existing_ids)) {
            $existing_ids = array();
        }
        $single_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
        if (!empty($single_id) && empty($existing_ids)) {
            $existing_ids = array($single_id);
        }
        
        // If we already have the correct number of gift card posts for this item, skip
        if (count($existing_ids) >= $item_qty) {
            continue;
        }
        
        // Need to create (or add) gift card posts for this item
        {
            // Get recipient information
            $recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
            if (empty($recipient_name)) {
                $recipient_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            }
            
            $recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
            if (empty($recipient_email)) {
                $recipient_email = wc_get_order_item_meta($item_id, '_delivery_email', true);
            }
            if (empty($recipient_email)) {
                $recipient_email = $order->get_billing_email();
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
            
            $line_total = wc_get_order_item_meta($item_id, '_gift_card_price', true);
            if (empty($line_total) || !is_numeric($line_total)) {
                $line_total = $item->get_total();
            }
            $unit_price = ($item_qty > 0 && is_numeric($line_total)) ? (float) $line_total / $item_qty : (float) $line_total;
            
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
            
            // Get scheduled date from order item meta (same as place_cod_order)
            // In place_cod_order, scheduleDate is saved as '_scheduled_date' in order item meta
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
            
            $created_ids = array();
            for ($q = count($existing_ids); $q < $item_qty; $q++) {
                $unique_gift_card_number = generate_unique_gift_card_code();
                $encrypted_gift_card_number = '';
                if (function_exists('encrypt_giftcard_no')) {
                    try {
                        $encrypted_gift_card_number = encrypt_giftcard_no($unique_gift_card_number);
                    } catch (Exception $e) {
                        $logger->error("Failed to encrypt gift card number: " . $e->getMessage(), $context);
                        $encrypted_gift_card_number = $unique_gift_card_number;
                    }
                } else {
                    $encrypted_gift_card_number = $unique_gift_card_number;
                }
                
                $gift_card_post_id = create_gift_card_post_for_order_item([
                    'order_id' => $order_id,
                    'item_id' => $item_id,
                    'product' => $product,
                    'recipient_name' => $recipient_name,
                    'recipient_email' => $recipient_email,
                    'recipient_phone' => $recipient_phone,
                    'sender_name' => $sender_name,
                    'sender_email' => $sender_email,
                    'gift_card_number' => $encrypted_gift_card_number,
                    'gift_card_name' => $gift_card_name,
                    'gift_card_sku' => $gift_card_sku,
                    'price' => $unit_price,
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
                
                $created_ids[] = $gift_card_post_id;
                $logger->info("Created gift card post ID: {$gift_card_post_id} for order {$order_id}, item {$item_id} (qty " . ($q + 1) . "/{$item_qty})", $context);

                $delivery_option = wc_get_order_item_meta($item_id, 'delivery_option', true);

                // fallback (if saved with underscore)
                if (empty($delivery_option)) {
                    $delivery_option = wc_get_order_item_meta($item_id, '_delivery_option', true);
                }

                if ($delivery_option === 'myself') {

                    $wallet_user_id = $order->get_customer_id();

                    if ($wallet_user_id) {

                        update_post_meta($gift_card_post_id, '_wallet_user_id', $wallet_user_id);

                        update_field('Status', 'Active', $gift_card_post_id);

                        $usage_expiry_type = get_post_meta($gift_card_post_id, '_expiry_type', true);
                        $usage_expiry_date = get_post_meta($gift_card_post_id, '_expiry_date', true);

                        if ($usage_expiry_type === 'on_purchase' && empty($usage_expiry_date)) {

                            $usage_duration = get_post_meta($gift_card_post_id, '_expiry_duration', true);
                            $usage_unit     = get_post_meta($gift_card_post_id, '_expiry_unit', true);

                            if (!empty($usage_duration) && !empty($usage_unit)) {

                                $purchase_ts = current_time('timestamp');

                                $computed_expiry = date(
                                    'Y-m-d H:i:s',
                                    strtotime('+' . (int)$usage_duration . ' ' . $usage_unit, $purchase_ts)
                                );

                                update_post_meta($gift_card_post_id, '_expiry_date', $computed_expiry);
                                update_field('gift_card_expiry_date', $computed_expiry, $gift_card_post_id);
                            }
                        }

                        $logger->info("Auto-added gift card {$gift_card_post_id} to wallet for user {$wallet_user_id}", $context);
                    }
                }
            }
            
            if (!empty($created_ids)) {
                $all_ids = array_merge($existing_ids, $created_ids);
                wc_update_order_item_meta($item_id, '_gift_card_post_ids', $all_ids);
                wc_update_order_item_meta($item_id, '_gift_card_post_id', $all_ids[0]);
            }
        }
    }
    
    // Build gift card details array for scheduling emails (similar to place_cod_order)
    $all_gift_cards_to_send = array();
    $current_timestamp = current_time('timestamp');
    
    foreach ($order->get_items() as $item_id => $item) {
        $post_ids = wc_get_order_item_meta($item_id, '_gift_card_post_ids', true);
        if (!is_array($post_ids) || empty($post_ids)) {
            $single_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
            $post_ids = !empty($single_id) ? array($single_id) : array();
        }
        if (empty($post_ids)) {
            continue;
        }
        
        // Get scheduled date from order item meta
        $schedule_date = wc_get_order_item_meta($item_id, '_scheduled_date', true);
        
        // If no scheduled_date, check delivery_timing and convert it
        if (empty($schedule_date)) {
            $delivery_timing = wc_get_order_item_meta($item_id, '_delivery_timing', true);
            if (!empty($delivery_timing) && $delivery_timing !== 'immediate' && $delivery_timing !== 'now') {
                $schedule_date = $delivery_timing;
                wc_update_order_item_meta($item_id, '_scheduled_date', $schedule_date);
            }
        }
        
        $recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
        if (empty($recipient_email)) {
            $recipient_email = wc_get_order_item_meta($item_id, '_delivery_email', true);
        }
        if (empty($recipient_email)) {
            $recipient_email = $order->get_billing_email();
        }
        if (empty($recipient_email)) {
            continue;
        }
        
        $recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
        $gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_title', true);
        if (empty($gift_card_name)) {
            $gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_name', true);
        }
        if (empty($gift_card_name)) {
            $gift_card_name = $item->get_name();
        }
        
        $sender_name = wc_get_order_item_meta($item_id, '_sender_name', true);
        if (empty($sender_name)) {
            $sender_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }
        $sender_email = $order->get_billing_email();
        // $business_user_id / $business_user_name / $business_user_email computed once,
        // function-wide, right after $order is loaded — see top of send_blackhawk_gift_card_email_on_order().

        $gift_message = wc_get_order_item_meta($item_id, '_gift_message', true);
        $email_animation = wc_get_order_item_meta($item_id, 'gift_email_animation', true);
        if (empty($email_animation)) {
            $email_animation = wc_get_order_item_meta($item_id, '_gift_email_animation', true);
        }
        $image_url = wc_get_order_item_meta($item_id, '_gift_card_image', true);
        $delivery_method = wc_get_order_item_meta($item_id, '_delivery_method', true);
        if (empty($delivery_method)) {
            $delivery_method = 'Email';
        }
        $recipient_phone = wc_get_order_item_meta($item_id, '_recipient_phone', true);
        if (empty($recipient_phone)) {
            $recipient_phone = wc_get_order_item_meta($item_id, 'mobile_number', true);
        }
        $expiry_type = wc_get_order_item_meta($item_id, '_activation_expiry_type', true);
        
        foreach ($post_ids as $gift_card_post_id) {
            $gift_card_number = get_post_meta($gift_card_post_id, '_gift_card_number_enc', true);
            if (empty($gift_card_number)) {
                $gift_card_number = wc_get_order_item_meta($item_id, '_gift_card_number_enc', true);
            }
            $price = get_post_meta($gift_card_post_id, '_price', true);
            if (empty($price) || !is_numeric($price)) {
                $price = wc_get_order_item_meta($item_id, '_gift_card_price', true);
            }
            if (empty($price) || !is_numeric($price)) {
                $price = $item->get_total();
            }
            
            $expiry_date = get_field('_activation_expiry_date', $gift_card_post_id);
            $expiry_duration = get_field('_activation_expiry_duration', $gift_card_post_id);
            $expiry_unit = get_field('_activation_expiry_unit', $gift_card_post_id);
            
            $gift_card_details = array(
                'gift_card_post_id' => $gift_card_post_id,
                'scheduled_date' => $schedule_date,
                'recipient_email' => $recipient_email,
                'recipient_name' => $recipient_name,
                'name' => $recipient_name,
                'email' => $recipient_email,
                '_gift_card_number_enc' => $gift_card_number,
                'gift_card_number' => $gift_card_number,
                'gift_card_name' => $gift_card_name,
                'price' => $price,
                'message' => $gift_message,
                'emailAnimation' => $email_animation,
                'image_url' => $image_url,
                'sender_name' => $sender_name,
                'sender_email' => $sender_email,
                'business_user_name' => $business_user_name,
                'business_user_email' => $business_user_email,
                'delivery_method' => $delivery_method,
                'recipient_phone' => $recipient_phone,
                'expiry_type' => $expiry_type,
                'expiry_date' => $expiry_date,
                'expiry_duration' => $expiry_duration,
                'expiry_unit' => $expiry_unit,
                'order_id' => $order_id,
                'item_id' => $item_id,
            );
            
            $all_gift_cards_to_send[] = $gift_card_details;
        }
    }
    
    // Schedule emails for gift cards with future scheduled dates (similar to place_cod_order)
    if (!empty($all_gift_cards_to_send)) {
        foreach ($all_gift_cards_to_send as $gift_card_details) {
            $gc_email_date_timestamp = !empty($gift_card_details['scheduled_date']) ? strtotime($gift_card_details['scheduled_date']) : 0;
            $gc_email_status = 'immediate';
            
            if (!empty($gift_card_details['scheduled_date']) && $gc_email_date_timestamp > $current_timestamp) {
                $gc_email_status = 'schedule';
                $hook_name = 'send_gift_cards_email_event';
                $hook_args = [$gift_card_details];
                $timestamp = $gc_email_date_timestamp;
                
                if (!wp_next_scheduled($hook_name, $hook_args)) {
                    update_post_meta($gift_card_details['gift_card_post_id'], '_gift_card_send', 'Ordered');
                    wp_schedule_single_event($timestamp, $hook_name, $hook_args);
                    $logger->info("Scheduled email for gift card post {$gift_card_details['gift_card_post_id']} for date: {$gift_card_details['scheduled_date']}", $context);
                } else {
                    $logger->info("Email already scheduled for gift card post {$gift_card_details['gift_card_post_id']}", $context);
                }
            } else {
                // Immediate send - mark as Instant (will be sent in the email loop below)
                update_post_meta($gift_card_details['gift_card_post_id'], '_gift_card_send', 'Instant');
                $gc_email_status = 'immediate';
            }
        }
    }
    
    // Refresh order object again to ensure we have the latest status after post creation
    $order = wc_get_order($order_id);
    if (!$order) {
        $logger->error("Order not found after post creation: {$order_id}", $context);
        return;
    }
    
    // Check order status - don't send emails if order is on-hold (Funding Hold)
    // Use both order object and database to ensure we get the correct status
    $order_status = $order->get_status();
    $db_order_status = get_post_status($order_id);
    
    // Normalize status (remove 'wc-' prefix if present)
    if (strpos($db_order_status, 'wc-') === 0) {
        $db_order_status = substr($db_order_status, 3);
    }
    
    $logger->info("Checking order status for order {$order_id}: order->get_status() = {$order_status}, get_post_status() = {$db_order_status}", $context);
    
    // Check both status values - if either is 'on-hold', skip emails
    if ($order_status === 'on-hold' || $db_order_status === 'on-hold') {
        $logger->info("Order {$order_id} is on-hold (BHN Funding Hold). Skipping email sending. Emails will be sent when order is completed.", $context);
        
        // Mark gift cards as pending for all items
        foreach ($order->get_items() as $item_id => $item) {
            $post_ids = wc_get_order_item_meta($item_id, '_gift_card_post_ids', true);
            if (!is_array($post_ids) || empty($post_ids)) {
                $single_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
                $post_ids = !empty($single_id) ? array($single_id) : array();
            }
            foreach ($post_ids as $gift_card_post_id) {
                if (!empty($gift_card_post_id)) {
                    update_post_meta($gift_card_post_id, '_gift_card_send', 'Pending Order Completion');
                }
            }
        }
        
        return; // Exit function - don't send emails
    }
    
    // Loop through order items for email sending (one email per gift card post = per quantity)
    $email_sent = false;
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) {
            continue;
        }
        
        $post_ids = wc_get_order_item_meta($item_id, '_gift_card_post_ids', true);
        if (!is_array($post_ids) || empty($post_ids)) {
            $single_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
            $post_ids = !empty($single_id) ? array($single_id) : array();
        }
        if (empty($post_ids)) {
            $logger->warning("Gift card post not found for order item {$item_id} in order {$order_id}. Skipping email.", $context);
            continue;
        }
        
        // Get recipient/item-level data once per item
        $recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
        if (empty($recipient_email)) {
            $recipient_email = wc_get_order_item_meta($item_id, '_delivery_email', true);
        }
        if (empty($recipient_email)) {
            $recipient_email = $order->get_billing_email();
        }
        if (empty($recipient_email)) {
            $logger->warning("No recipient email found for order item {$item_id} in order {$order_id}", $context);
            continue;
        }
        
        $recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
        if (empty($recipient_name)) {
            $recipient_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }
        $recipient_phone = wc_get_order_item_meta($item_id, '_recipient_phone', true);
        if (empty($recipient_phone)) {
            $recipient_phone = wc_get_order_item_meta($item_id, 'mobile_number', true);
        }
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
        $product_id = $product->get_id();
        
        foreach ($post_ids as $gift_card_post_id) {
            if (empty($gift_card_post_id)) {
                continue;
            }
            
            $logger->info("Processing gift card post {$gift_card_post_id} (order {$order_id}, item {$item_id})", $context);
            
            $gift_card_send_status = get_post_meta($gift_card_post_id, '_gift_card_send', true);
            if ($gift_card_send_status === 'Ordered') {
                $schedule_date = wc_get_order_item_meta($item_id, '_scheduled_date', true);
                $logger->info("Email is scheduled for gift card post {$gift_card_post_id}. Skipping immediate send.", $context);
                continue;
            }
            
            $price = get_post_meta($gift_card_post_id, '_price', true);
            if (empty($price) || !is_numeric($price)) {
                $price = wc_get_order_item_meta($item_id, '_gift_card_price', true);
            }
            if (empty($price) || !is_numeric($price)) {
                $price = $item->get_total();
            }
            
            $gift_card_number = get_post_meta($gift_card_post_id, '_gift_card_number_enc', true);
            if (empty($gift_card_number)) {
                $gift_card_number = wc_get_order_item_meta($item_id, '_gift_card_number_enc', true);
            }
        
        // Get activation date - check multiple sources (order item meta, gift card post, product)
        $activation_date = wc_get_order_item_meta($item_id, '_activation_expiry_date', true);
        
        // If not in order item meta, check gift card post
        if (empty($activation_date) && !empty($gift_card_post_id)) {
            $activation_date = get_field('_activation_expiry_date', $gift_card_post_id);
            if (empty($activation_date)) {
                $activation_date = get_field('activation_expiry_date', $gift_card_post_id);
            }
            if (empty($activation_date)) {
                $activation_date = get_post_meta($gift_card_post_id, '_activation_expiry_date', true);
            }
        }
        
        // If still empty, try product ACF field
        if (empty($activation_date)) {
            $activation_date = get_field('activation_expiry_date', $product_id);
            if (empty($activation_date)) {
                $activation_date = get_field('_activation_expiry_date', $product_id);
            }
        }
        
        // Format activation date for display (similar to place_cod_order)
        $formatted_activation_date = '';
        if (!empty($activation_date)) {
            // Check if function exists to format date
            if (function_exists('gc_format_activation_expiry_date_for_email')) {
                $formatted_activation_date = gc_format_activation_expiry_date_for_email($activation_date);
            } else {
                // Fallback: try to format the date
                $dt = null;
                if (is_numeric($activation_date)) {
                    $dt = new DateTime('@' . intval($activation_date));
                    $dt->setTimezone(wp_timezone());
                } elseif (is_string($activation_date)) {
                    $normalized = str_replace('T', ' ', $activation_date);
                    $formats = ['d/m/Y g:i a', 'd/m/Y g:i A', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
                    foreach ($formats as $fmt) {
                        $tmp = DateTime::createFromFormat($fmt, $normalized, wp_timezone());
                        if ($tmp instanceof DateTime) {
                            $errors = DateTime::getLastErrors();
                            if (empty($errors['warning_count']) && empty($errors['error_count'])) {
                                $dt = $tmp;
                                break;
                            }
                        }
                    }
                    if (!$dt) {
                        try {
                            $dt = new DateTime($normalized, wp_timezone());
                        } catch (Exception $e) {
                            // Keep original value if parsing fails
                        }
                    }
                }
                $formatted_activation_date = ($dt instanceof DateTime) ? $dt->format('d/m/Y g:i a') : $activation_date;
            }
        }
        
        // Get PIN from gift card post
        $gcard_pin = '';
        if (!empty($gift_card_post_id)) {
            $gcard_pin = get_post_meta($gift_card_post_id, 'gcard_security_pin', true);
            if (empty($gcard_pin)) {
                $gcard_pin = get_post_meta($gift_card_post_id, '_gcard_security_pin', true);
            }
            if (empty($gcard_pin)) {
                $gcard_pin = get_post_meta($gift_card_post_id, 'security_pin', true);
            }
        }
        
        // Get/Calculate gift card expiry date (card usage expiry)
        $gift_card_expiry_date = '';
        if (!empty($gift_card_post_id)) {
            // Check if fixed date exists
            $expiry_date = get_post_meta($gift_card_post_id, '_expiry_date', true);
            if (!empty($expiry_date)) {
                $gift_card_expiry_date = $expiry_date;
            } else {
                // Check if duration-based expiry exists
                $expiry_duration = get_post_meta($gift_card_post_id, '_expiry_duration', true);
                $expiry_unit = get_post_meta($gift_card_post_id, '_expiry_unit', true);
                $expiry_type = get_post_meta($gift_card_post_id, '_expiry_type', true);
                
                if (!empty($expiry_duration) && !empty($expiry_unit)) {
                    // Calculate expiry date based on type
                    if ($expiry_type === 'on_purchase' || empty($expiry_type)) {
                        // Calculate from purchase date (gift card post creation date)
                        $post_date = get_post_time('U', true, $gift_card_post_id);
                        if ($post_date) {
                            $expiry_dt = new DateTime('@' . $post_date, wp_timezone());
                            switch ($expiry_unit) {
                                case 'days':
                                    $expiry_dt->modify("+{$expiry_duration} days");
                                    break;
                                case 'weeks':
                                    $expiry_dt->modify("+{$expiry_duration} weeks");
                                    break;
                                case 'months':
                                    $expiry_dt->modify("+{$expiry_duration} months");
                                    break;
                                case 'years':
                                    $expiry_dt->modify("+{$expiry_duration} years");
                                    break;
                            }
                            $gift_card_expiry_date = $expiry_dt->format('Y-m-d H:i:s');
                        }
                    }
                    // Note: 'on_activation' expiry is calculated when card is activated, not here
                }
            }
        }
        
        // Format expiry date for display
        $formatted_expiry_date = '';
        if (!empty($gift_card_expiry_date)) {
            if (function_exists('gc_format_activation_expiry_date_for_email')) {
                $formatted_expiry_date = gc_format_activation_expiry_date_for_email($gift_card_expiry_date);
            } else {
                // Fallback formatting
                try {
                    $expiry_dt = new DateTime($gift_card_expiry_date, wp_timezone());
                    $formatted_expiry_date = $expiry_dt->format('d/m/Y g:i a');
                } catch (Exception $e) {
                    $formatted_expiry_date = $gift_card_expiry_date;
                }
            }
        }
        
        // Selected/customer-chosen image (for PDF and email) – from item meta or product image
        $selected_image_url = wc_get_order_item_meta($item_id, '_gift_card_image', true);
        if (empty($selected_image_url)) {
            $product_image_id = $product->get_image_id();
            if ($product_image_id) {
                $selected_image_url = wp_get_attachment_image_url($product_image_id, 'full');
            }
        }

        // Build order_product_data array (required for PDF generation)
        // This must be a serialized array with product_name as the first element
        // One email per gift card post, so quantity in PDF is 1
        $order_product_data = array(
            array(
                'product_name' => $gift_card_name,
                'product_id' => $product->get_id(),
                'product_sku' => $product->get_sku(),
                'price' => $price,
                'quantity' => 1,
                'gift_card_image' => $selected_image_url,
                'image_url' => $selected_image_url,
                'product_image' => $selected_image_url ?: wp_get_attachment_image_url($product->get_image_id(), 'full'),
                'all_fields' => array(
                    'activation_expiry_date' => $formatted_activation_date,
                    'gift_card_expiry_date' => $formatted_expiry_date,
                    'gift_card_image' => $selected_image_url,
                    'image_url' => $selected_image_url,
                ),
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

        // Business user (drives the brand banner in the recipient email — Havit/Gyprock get
        // their own banner regardless of product type; see send_gift_cards_email_to_recipient()).
        $gcard_data['business_user_name'] = $business_user_name;
        $gcard_data['business_user_email'] = $business_user_email;

        // Gift message
        $gcard_data['message'] = wc_get_order_item_meta($item_id, '_gift_message', true);
        if (empty($gcard_data['message'])) {
            $gcard_data['message'] = wc_get_order_item_meta($item_id, 'gift_message', true);
        }
        
        // Image URL (selected design or product image – used in PDF and email)
        $gcard_data['image_url'] = $selected_image_url;
        if (empty($gcard_data['image_url'])) {
            $product_image_id = $product->get_image_id();
            if ($product_image_id) {
                $gcard_data['image_url'] = wp_get_attachment_image_url($product_image_id, 'full');
            }
        }
        
        // Email animation (GIF)
        $gcard_data['emailAnimation'] = wc_get_order_item_meta($item_id, 'gift_email_animation', true);
        if (empty($gcard_data['emailAnimation'])) {
            $gcard_data['emailAnimation'] = wc_get_order_item_meta($item_id, '_gift_email_animation', true);
        }
        

        // Video message URL (for email attachment)
        $gcard_data['video_attachment_url'] = wc_get_order_item_meta($item_id, 'gift_video_message', true);
        if (empty($gcard_data['video_attachment_url'])) {
            $gcard_data['video_attachment_url'] = wc_get_order_item_meta($item_id, '_gift_video_message', true);
        }

        // Image message URL (for email display)
        $gcard_data['image_message_url'] = wc_get_order_item_meta($item_id, 'gift_image_message', true);
       
        // CRITICAL: order_product_data must be serialized for PDF generation
        $gcard_data['order_product_data'] = serialize($order_product_data);
        
        // Additional fields
        $gcard_data['amount'] = $gcard_data['price'];
        
        // Decrypt the card number for PDF display
        $decrypted_card_number = $gift_card_number;
        if (function_exists('decrypt_giftcard_no') && !empty($gift_card_number)) {
            try {
                $decrypted_card_number = decrypt_giftcard_no($gift_card_number);
            } catch (Exception $e) {
                // If decryption fails, use encrypted version as fallback
                $decrypted_card_number = $gift_card_number;
            }
        }
        $gcard_data['card_number'] = $decrypted_card_number;
        
        // PIN for PDF
        $gcard_data['card_pin'] = $gcard_pin;
        $gcard_data['pin'] = $gcard_pin; // Fallback key
        
        // Activation date for PDF (use formatted version)
        $gcard_data['activation'] = $formatted_activation_date;
        
        // Expiry date for PDF (use formatted version)
        $gcard_data['expiry'] = $formatted_expiry_date;
        
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
            $logger->info("Sending email for gift card {$gift_card_post_id} to {$recipient_email} for order {$order_id}", $context);
            send_giftcard_email_with_pdf($gcard_data, $recipient_email, $order_id);
            $logger->info("Email sent successfully for gift card post {$gift_card_post_id} in order {$order_id}", $context);
            $email_sent = true;
        } catch (Exception $e) {
            $logger->error("Error sending email for gift card post {$gift_card_post_id} in order {$order_id}: " . $e->getMessage(), $context);
        } catch (Error $e) {
            $logger->error("Fatal error sending email for gift card post {$gift_card_post_id} in order {$order_id}: " . $e->getMessage(), $context);
        }
        } // end foreach ($post_ids as $gift_card_post_id)
    }
    
    // Mark email as sent to prevent duplicates
    if ($email_sent) {
        update_post_meta($order_id, '_gift_card_email_sent', 'yes');
    }
}
