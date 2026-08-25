<?php

defined('ABSPATH') || exit;



$order_user_id = $order->get_user_id();

$is_customer_user = false;

// ✅ If user is logged OUT → always customer UI (guest)
if (!is_user_logged_in()) {

    $is_customer_user = true;

} else {

    // ✅ Logged-in user
    $current_user = wp_get_current_user();

    // ❌ Admin → show admin UI
    if (in_array('administrator', (array) $current_user->roles, true)) {
        $is_customer_user = false;
    } else {
        // ✅ All other users (customer, etc.)
        $is_customer_user = true;
    }
}

$order_user = $order_user_id ? get_user_by('id', $order_user_id) : null;

$is_order_by_customer = false;

if ($order_user && (in_array('customer', (array) $order_user->roles, true) || in_array('guest', (array) $order_user->roles, true))) {
    $is_order_by_customer = true;
}
$is_same_customer = false;
if (is_user_logged_in() && $order_user_id) {
    $current_user_id = get_current_user_id();
    if ((int) $current_user_id === (int) $order_user_id) {
        $is_same_customer = true;
    }
}
// if ($is_order_by_customer && !$is_same_customer) {
    ?>
    <!-- <div class="woocommerce-notices-wrapper">
        <div class="woocommerce-error">
            Please log in as the customer to view this order details.
        </div>
    </div> -->
    <?php
//     return;
// }

if ($order) {
    $order_id = $order->get_id();

    // Bust any cached order object before re-fetching. Stripe's maybe_process_upe_redirect()
    // on template_redirect may have just updated pending → completed earlier in this same
    // request, and with object caching (Redis/Memcached) active, wc_get_order() can still
    // return a stale cached copy instead of hitting the DB unless the cache is cleared first.
    if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
        wp_cache_delete($order_id, 'woocommerce-orders');
    } else {
        clean_post_cache($order_id);
    }
    $order = wc_get_order($order_id);

    $order_status = $order->get_status();
    if ($order_status == 'trash') {
        return;
    }


    // Show Blackhawk submission error if order was placed but BHN rejected it (e.g. price outside value restrictions)
    $bhn_order_error = $order->get_meta('_bhn_order_error');
    if (!empty($bhn_order_error)) {
        echo '<div class="woocommerce-notices-wrapper"><div class="woocommerce-error" role="alert">';
        echo esc_html__('Blackhawk was unable to process this order: ', 'woocommerce') . esc_html($bhn_order_error);
        echo '</div></div>';
    }

    $business_name = $order->get_meta('_business_name');

    $sender_name = $order->get_meta('_sender_name');
    $order_name = $order->get_meta('_order_name');
    $_po_number = $order->get_meta('_po_number');
    $_additional_reference = $order->get_meta('_additional_reference');
    $_client_reference = $order->get_meta('_client_reference');

    // Initialize values to collect across gift cards
    $gift_expiry_types      = [];
    $gift_expiry_raw_types  = [];
    $gift_expiry_dates      = [];
    $gift_expiry_durations  = [];
    $gift_card_expiry_dates = [];

    foreach ($order->get_items() as $item) {
        $item_id          = $item->get_id();
        $gift_card_number = $item->get_meta('_gift_card_number_enc');

        // Collect gift card post IDs to read activation expiry from.
        // For standard WooCommerce checkout orders the encrypted number is only on the
        // gift_card post itself (not on the order item), so fall back to _gift_card_post_ids
        // / _gift_card_post_id which are always written to the order item after creation.
        $gift_card_ids = [];

        if ($gift_card_number) {
            $found = get_posts([
                'post_type'   => 'gift_card',
                'numberposts' => -1,
                'meta_key'    => '_gift_card_number_enc',
                'meta_value'  => $gift_card_number,
                'fields'      => 'ids',
            ]);
            if (!empty($found)) {
                $gift_card_ids = $found;
            }
        }

        // Fallback: look up by _gift_card_post_ids / _gift_card_post_id on the order item
        if (empty($gift_card_ids)) {
            $post_ids = wc_get_order_item_meta($item_id, '_gift_card_post_ids', true);
            if (!is_array($post_ids) || empty($post_ids)) {
                $single = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
                $post_ids = !empty($single) ? [(int) $single] : [];
            }
            $gift_card_ids = array_filter(array_map('intval', (array) $post_ids));
        }

        foreach ($gift_card_ids as $gift_card_id) {
            $delivery_status = get_post_meta($gift_card_id, '_gift_card_send', true);
            $type     = get_field('_activation_expiry_type', $gift_card_id)     ?: get_post_meta($gift_card_id, '_activation_expiry_type', true);
            $date     = get_field('_activation_expiry_date', $gift_card_id)     ?: get_post_meta($gift_card_id, '_activation_expiry_date', true);
            $duration = get_field('_activation_expiry_duration', $gift_card_id) ?: get_post_meta($gift_card_id, '_activation_expiry_duration', true);
            $unit     = get_field('_activation_expiry_unit', $gift_card_id)     ?: get_post_meta($gift_card_id, '_activation_expiry_unit', true);

            $type_labels = [
                'activation_set_date'   => 'Expires on a Set Date',
                'set_period'            => 'Expires After a Period',
                'no_activation_expiry'  => 'No Expiry',
                'no_activation_needed'  => 'No Activation Needed',
            ];
            $type_display = isset($type_labels[$type]) ? $type_labels[$type] : $type;

            if ($type_display) {
                $gift_expiry_types[]     = $type_display;
                $gift_expiry_raw_types[] = $type;
            }
            if ($date)
                $gift_expiry_dates[] = $date;
            if ($duration || $unit) {
                $gift_expiry_durations[] = trim($duration . ' ' . $unit);
            }

            // Gift Card Expiry Date (usage expiry, stored as _expiry_date on the gift card post)
            $gc_expiry = get_post_meta($gift_card_id, '_expiry_date', true);
            if (!empty($gc_expiry)) {
                $gc_expiry_display = date('d/m/Y', strtotime($gc_expiry));
                $gift_card_expiry_dates[] = $gc_expiry_display;
            }
        }
    }

    // Remove duplicates
    $gift_expiry_types      = array_unique($gift_expiry_types);
    $gift_expiry_raw_types  = array_unique($gift_expiry_raw_types);
    $gift_expiry_dates      = array_unique($gift_expiry_dates);
    $gift_expiry_durations  = array_unique($gift_expiry_durations);
    $gift_card_expiry_dates = array_unique($gift_card_expiry_dates);

    $order_subtotal = $order->get_subtotal();

    $total_fulfillment = $order->get_meta('fullfillment_total');
    $delivery_total = $order->get_meta('delivery_total');
    $created_by = get_post_meta($order_id, 'created_by', true);
    $_campaign = $order->get_meta('_campaign');
    $delivery_cost = $order->get_meta('total_delivery');
    $gst = $order->get_meta('_order_gst');

    // Payment
    $payment_method  = $order->get_payment_method_title();
    $payment_details = $order->get_payment_method();
    $payment_status  = ucfirst($order->get_status());
    $invoice_number  = $order->get_meta('_invoice_number');

    $user = $order->get_user();
    $order_creator = $user ? $user->display_name : 'Guest';

    // Get payment card info for customer display
    $card_last4 = '';
    $card_brand = '';
    if (method_exists($order, 'get_payment_card_info')) {
        $card_info = $order->get_payment_card_info();
        if (!empty($card_info)) {
            $card_last4 = isset($card_info['last4']) ? $card_info['last4'] : '';
            $card_brand = isset($card_info['brand']) ? $card_info['brand'] : '';
        }
    }
    if (empty($card_last4)) {
        $card_last4 = $order->get_meta('_card_last4') ?: $order->get_meta('last4');
        $card_brand = $order->get_meta('_card_brand');
    }

    // Get delivery method from first item
    $delivery_method_display = 'Email delivery';
    $items = $order->get_items();
    if (!empty($items)) {
        $first_item = reset($items);
        $delivery_method = $first_item->get_meta('_delivery_method');
        if ($delivery_method) {
            $delivery_method_display = ucfirst(str_replace('_', ' ', $delivery_method)) . ' delivery';
        }
    }


    // Get sender name for gift cards
    $sender_name_display = $sender_name ?: $order_creator;

    // If customer user, show customer-friendly order confirmation
    if ($is_customer_user) {
        // Format payment method display
        $payment_display = $payment_method;
        if (!empty($card_last4) && strlen($card_last4) >= 4) {
            $payment_display = 'Credit Card: xxx' . $card_last4;
        }
        ?>
        <div class="page-spacer-top thank-you"></div>
        <div class="thank-you-wrapper customer-order-confirmation">
            <div class=" user-thankyou-page">
                <div class="order-confirmation-wrapper">
                    <div class="order-confirmation-title-wrap">
                        <div class="order-confirmed-icon"><svg xmlns="http://www.w3.org/2000/svg" width="50" height="50"
                                viewBox="0 0 50 50" fill="none">
                                <path
                                    d="M25 0C11.2 0 0 11.2 0 25C0 38.8 11.2 50 25 50C38.8 50 50 38.8 50 25C50 11.2 38.8 0 25 0ZM25 45C13.975 45 5 36.025 5 25C5 13.975 13.975 5 25 5C36.025 5 45 13.975 45 25C45 36.025 36.025 45 25 45ZM34.7 15.725L20 30.425L15.3 25.725C14.325 24.75 12.75 24.75 11.775 25.725C10.8 26.7 10.8 28.275 11.775 29.25L18.25 35.725C19.225 36.7 20.8 36.7 21.775 35.725L38.25 19.25C39.225 18.275 39.225 16.7 38.25 15.725C37.275 14.75 35.675 14.75 34.7 15.725Z"
                                    fill="#037847" />
                            </svg></div>
                        <h1 class="order-confirmed-title">Order Confirmed!</h1>
                    </div>
                    <div class="order-confirmation-wrap">
                        <!-- Order Confirmed Header -->
                        <div class="order-confirmed-header">
                            <div class="order-confirmed-info">
                                <p class="order-number"><strong>Order
                                        #<?php echo esc_html($order->get_order_number()); ?></strong></p>
                                <p class="invoice-number"> Invoice:<?php echo esc_html($invoice_number); ?>
                                </p>
                            </div>

                            <?php if ($order_status == 'completed') {
                                $order_status = "Completed";
                            } ?>
                            <span
                                class="order-status-badge status <?php echo $order_status; ?> order-<?php echo $order_status; ?>"><?php echo $order_status; ?></span>
                        </div>

                        <!-- Delivery Information -->
                        <div class="delivery-info">
                            <p class="delivery-text">
                                Your gift cards will be delivered to your chosen recipient via the selected method at the
                                scheduled time. We will send you another email once the delivery email has been sent.
                                <strong>Please note:</strong> You will not receive a copy of this gift card unless you have
                                chosen to deliver it yourself.
                            </p>
                            <p class="delivery-faq">
                                Have Questions? Check out our <a href="<?php echo esc_url(site_url('/faq')) ?>"
                                    class="faq-link">FAQ</a>
                            </p>
                        </div>

                        <!-- Summary Table -->
                        <div class="order-summary-section">
                            <div class="summary-item">
                                <div class="summary-label">Payment Method</div>
                                <div class="summary-value"><?php echo esc_html($payment_display); ?></div>
                            </div>

                            <div class="summary-item">
                                <div class="summary-label">Delivery</div>
                                <div class="summary-value"><?php echo esc_html($delivery_method_display); ?></div>
                            </div>

                            <div class="summary-item">
                                <div class="summary-label">Total Amount</div>
                                <div class="summary-value total-amount"><?php echo wc_price($order->get_total()); ?></div>
                            </div>
                        </div>

                        <!-- Gift Cards List -->
                        <div class="gift-cards-list">
                            <?php
                            foreach ($order->get_items() as $item) {

                                $product = $item->get_product();
                                $product_name = $item->get_name();
                                $recipient_name = $item->get_meta('_recipient_name');
                                $recipient_email = $item->get_meta('_recipient_email');
                                $recipient_phone = $item->get_meta('_recipient_phone');
                                $gift_message = $item->get_meta('_gift_message');
                                $item_total = $item->get_total();
                                $quantity = $item->get_quantity();

                                $single_price = $quantity > 0 ? $item_total / $quantity : 0;

                                // Get product image
                                $product_image = '';

                                if ($product && is_object($product) && method_exists($product, 'get_image_id')) {
                                    $image_id = $product->get_image_id();
                                    if ($image_id) {
                                        $product_image = wp_get_attachment_image_url($image_id, 'thumbnail');
                                    }
                                }

                                if (empty($product_image) && function_exists('wc_placeholder_img_src')) {
                                    $product_image = wc_placeholder_img_src();
                                }

                                // Override with gift card image if exists (URL or data URI from session upload)
                                $gift_card_image = $item->get_meta('_gift_card_image');
                                if ($gift_card_image) {
                                    $product_image = $gift_card_image;
                                }

                                // Safe img src: data URIs must use esc_attr (esc_url strips them)
                                $product_image_src = '';
                                if (!empty($product_image)) {
                                    $product_image_src = (strpos($product_image, 'data:image') === 0) ? esc_attr($product_image) : esc_url($product_image);
                                }

                                // 🔥 Repeat output per quantity
                                for ($i = 1; $i <= $quantity; $i++) {
                                    ?>
                                    <div class="gift-card-item">
                                        <div class="gift-card-image">
                                            <?php if (!empty($product_image_src)): ?>
                                                <img src="<?php echo $product_image_src; ?>" alt="<?php echo esc_attr($product_name); ?>"
                                                    class="gift-card-img">
                                            <?php else: ?>
                                                <div class="gift-card-placeholder">
                                                    <span class="placeholder-text">No Image</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="gift-card-content">
                                            <div class="gift-card-header">
                                                <h3 class="gift-card-title">
                                                    <?php echo esc_html($product_name); ?>
                                                </h3>
                                            </div>

                                            <div class="gift-card-details-wrapper">
                                                <div class="gift-card-details">

                                                    <div class="gift-card-info-row">
                                                        <span class="info-label">From:</span>
                                                        <span class="info-value"><?php echo esc_html($sender_name_display); ?></span>
                                                    </div>

                                                    <div class="gift-card-info-row">
                                                        <span class="info-label">Recipient:</span>
                                                        <span class="info-value"><?php echo esc_html($recipient_name); ?></span>
                                                    </div>


                                                    <?php if ($delivery_method == 'sms') { ?>
                                                        <div class="gift-card-info-row">
                                                            <span class="info-label">Phone:</span>
                                                            <span class="info-value"><?php echo esc_html($recipient_phone); ?></span>
                                                        </div>

                                                    <?php } else { ?>

                                                        <div class="gift-card-info-row">
                                                            <span class="info-label">Email:</span>
                                                            <span class="info-value"><?php echo esc_html($recipient_email); ?></span>
                                                        </div>
                                                    <?php } ?>

                                                    <?php if ($gift_message): ?>
                                                        <div class="gift-card-message">
                                                            <span class="info-label">Message:</span>
                                                            <span class="info-value"><?php echo nl2br(esc_html($gift_message)); ?></span>
                                                        </div>
                                                    <?php endif; ?>

                                                </div>

                                                <div class="gift-card-price-section">
                                                    <div class="gift-card-price-amount">
                                                        <?php echo wc_price($single_price); ?>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }

                            ?>
                        </div>

                        <!-- Action Buttons -->

                    </div>
                    <div class="order-actions">
                        <!-- <a href="<?php //echo esc_url(admin_url('admin-ajax.php?action=download_invoice&order_id=' . $order_id . '&preview=1')); ?>"
                            target="_blank" class="btn btn-white btn-preview-invoice">
                            Preview Invoice
                        </a> -->
                        <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=download_invoice&order_id=' . $order_id)); ?>"
                            target="_blank" download class="btn-outline-dark btn-white-p2 btn btn-download-invoice">
                            Download Invoice
                        </a>
                        <a href="<?php echo esc_url(site_url('/brands')); ?>" class="btn btn-black-p2 btn-primary btn-continue-shopping">
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="need-help section-spacing page-thankyou">
            <?php
            $templates = get_option('wpb_js_templates');

            if (!empty($templates)) {
                foreach ($templates as $template) {
                    if (!empty($template['name']) && $template['name'] === 'template-need-help') {
                        echo do_shortcode($template['template']);
                        break; // stop once found
                    }
                }
            }
            ?>
        </div>
        <?php
        return; // Exit early for customer users
    } else if (!$is_customer_user) {
        ?>
            <div class="page-spacer-top thank-you"></div>
            <div class="thank-you-wrapper">
                <div class="container">
                    <div class="order-confirmation-wrapper">

                        <div class="page-title align-left">
                            <h1 class="order-confirmation-title">Order Confirmation</h1>
                            <span
                                class="order-status-badge status <?php echo strtolower($payment_status); ?> order-<?php echo $payment_status; ?>">
                            <?php echo esc_html($payment_status); ?></span>
                        </div>
                        <div class="right-section">
                            <button class="btn btn-white view-orders btn-black-white btn-primary-white"
                                onclick="window.location.href='<?php echo site_url('order'); ?>';">View All Orders</button>
                            <button class="btn btn-primary new-order btn-black-white btn-primary-black"
                                onclick="window.location.href='<?php echo site_url('order/?create_order=manual'); ?>';">New
                                order</button>
                        </div>
                    </div>
                    <div class="order-confirmation-header">
                        <div class="row">
                            <div class="order-summary col-12 col-md-6 col-lg-5">
                                <p><strong>Order Placed By:</strong> <?= esc_html($created_by); ?></p>
                                <p><strong>Business Name:</strong> <?= esc_html($business_name); ?></p>
                                <p><strong>Campaign Name :</strong> <?= esc_html($_campaign); ?></p>
                                <p><strong>Order Created By:</strong> <?= esc_html($order_creator); ?></p>
                                <p><strong>Sender Profile:</strong> <?= esc_html($sender_name); ?></p>
                                <p><strong>Payment Status:</strong> <?php if ($payment_status === 'Completed') {
                                    echo "Payment complete";
                                } else {
                                    echo esc_html($payment_status);
                                } ?></p>
                                <p><strong>Payment Method:</strong> <?= esc_html($payment_method); ?></p>
                                <p><strong>Payment Details:</strong> <?= esc_html($payment_details); ?></p>
                            </div>

                            <div class="order-details col-12 col-md-6 col-lg-4">
                                <p><strong>Order Number : </strong> <?= $order->get_order_number(); ?></p>
                                <p><strong>Order Name : </strong> <?= esc_html($order_name); ?></p>
                                <p><strong>PO Number : </strong> <?= esc_html($_po_number); ?></p>
                                <p><strong>Client Reference : </strong> <?= esc_html($_client_reference); ?></p>
                                <p><strong>Additional Reference : </strong> <?= esc_html($_additional_reference); ?></p>
                                <?php
                                $selected_type = $order->get_meta('_selected_activation_expiry_type');
                                if ($selected_type === 'default') {
                                    // echo $selected_type;
                                    echo '<p><strong>Card Activation Expiry:</strong> Cards Default</p>';
                                } elseif (!empty($gift_expiry_types)) {
                                    echo '<p><strong>Card Activation Expiry:</strong> ' . esc_html(implode(', ', $gift_expiry_types)) . '</p>';
                                }
                                ?>


                                <?php
                                if ($selected_type !== 'default') {
                                    if (!empty($gift_expiry_dates)): ?>
                                        <p><strong>Activation Expiry Date(s):</strong> <?= esc_html(implode(', ', $gift_expiry_dates)); ?></p>
                                <?php endif;
                                } ?>


                                <?php
                                $has_set_period = in_array('set_period', $gift_expiry_raw_types, true)
                                    || $selected_type === 'set_period';
                                if ($has_set_period && !empty($gift_expiry_durations)): ?>
                                    <p><strong>Activation Expiry Duration(s):</strong> <?= esc_html(implode(', ', $gift_expiry_durations)); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($gift_card_expiry_dates)): ?>
                                    <p><strong>Gift Card Expiry Date:</strong> <?= esc_html(implode(', ', $gift_card_expiry_dates)); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="totals col-12 col-md-6 col-lg-3">
                                <p><strong>Total Gift Cards:</strong> <?= wc_price($order_subtotal); ?></p>
                                <p><strong>Total Fulfillment:</strong> <?= wc_price($total_fulfillment); ?></p>
                                <p><strong>Delivery Cost:</strong> <?= wc_price($delivery_total); ?></p>
                                <p><strong>GST:</strong> <?= wc_price($gst); ?></p>
                                <p><strong>Order Total:</strong> <?= wc_price($order->get_total()); ?></p>
                            </div>
                        </div>
                        <div class="invoice-top-block">
                            <h3><strong>Invoice Number : </strong><?php echo $invoice_number; ?></h3>
                            <div class="invoice-btn">
                                <a class="btn btn-blue size-sm"
                                    href="<?php echo esc_url(admin_url('admin-ajax.php?action=download_invoice&order_id=' . $order_id . '&preview=1')); ?>"
                                    target="_blank" style="margin-right: 10px;">
                                    Preview Invoice
                                </a>
                                <a class="btn btn-blue size-sm"
                                    href="<?php echo esc_url(admin_url('admin-ajax.php?action=download_invoice&order_id=' . $order_id)); ?>"
                                    target="_blank" download>
                                    Download Invoice
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="product-management-header top-filter-block">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" id="order-confirmation-search" placeholder="Search">
                        </div>

                        <div class="action-buttons">
                            <button id="export_csv_btn" class="btn btn-white btn-black-white btn-primary-white size-sm">
                                Export Lists
                            </button>
                            <button class="btn btn-blue size-sm btn-black-white " id="gcp-bulk-resend-btn">
                                Resend
                            </button>
                        </div>
                    </div>


                    <section class="giftcards">
                        <table id="order_confirmation" class="order_confirmation" style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>No</th>
                                    <th>Gift Card</th>
                                    <th>Recipient</th>
                                    <th>Contact</th>
                                    <th>Gift Card Number</th>
                                    <th>Message</th>
                                    <th>Value</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $items = $order->get_items();

                                $grouped_recipients = [];

                                // Group items by recipient (name + email)
                                foreach ($items as $item) {
                                    $recipient_name = $item->get_meta('_recipient_name');
                                    $recipient_email = $item->get_meta('_recipient_email');

                                    // DIRECTLY GET THE ID (No need to search database)
                                    $gift_card_id = $item->get_meta('_gift_card_post_id');

                                    // Fallback: If meta is missing (old orders), try search by number
                                    if (empty($gift_card_id)) {
                                        $enc_number = $item->get_meta('_gift_card_number_enc');
                                        if ($enc_number) {
                                            $found_posts = get_posts([
                                                'post_type' => 'gift_card',
                                                'meta_key' => '_gift_card_number_enc',
                                                'meta_value' => $enc_number,
                                                'posts_per_page' => 1,
                                                'fields' => 'ids'
                                            ]);
                                            if (!empty($found_posts)) {
                                                $gift_card_id = $found_posts[0];
                                            }
                                        }
                                    }

                                    $gift_card_number = $item->get_meta('_gift_card_number_enc');
                                    $decrypted_card_number = 'XXXX XXXX';


                                    $gift_message = $item->get_meta('_gift_message');
                                    $gift_status = $item->get_meta('gift_status') ?: 'Completed';
                                    $product = $item->get_product();
                                    $product_name = $item->get_name();


                                    $product_image = '';

                                    // get image safely
                                    if ($product && is_object($product)) {
                                        if (method_exists($product, 'get_image_id')) {
                                            $image_id = $product->get_image_id();
                                            if ($image_id) {
                                                $product_image = wp_get_attachment_image_url($image_id, 'thumbnail');
                                            }
                                        }
                                    }

                                    // fallback to WooCommerce placeholder if nothing found
                                    if (empty($product_image)) {
                                        if (function_exists('wc_placeholder_img_src')) {
                                            $product_image = wc_placeholder_img_src();
                                        } else {
                                            $product_image = ''; // harmless blank fallback
                                        }
                                    }

                                    $subtotal_raw = $item->get_subtotal();
                                    $total_raw = $item->get_total();

                                    $key = md5($recipient_name . '|' . $recipient_email);

                                    if (!isset($grouped_recipients[$key])) {
                                        $grouped_recipients[$key] = [
                                            'name' => $recipient_name,
                                            'email' => $recipient_email,
                                            'phone' => $item->get_meta('_recipient_phone'),
                                            'delivery_method' => $item->get_meta('_delivery_method') ?: 'email',
                                            'gift_cards' => []
                                        ];
                                    }

                                    $delivery_status = '';
                                    
                                    $delivery_status = '';

                                    if (!empty($gift_card_id)) {
                                        $delivery_status = get_post_meta($gift_card_id, '_gift_card_send', true);

                                        if ($delivery_status == "Instant") {
                                            $delivery_status = "Delivered";
                                        } else if ($delivery_status == "Pending Order Completion") {
                                            $delivery_status = "Ordered";
                                        }
                                    }


                                    $grouped_recipients[$key]['gift_cards'][] = [
                                        'gift_card_id' => $gift_card_id,
                                        'product_image' => $product_image,
                                        'product_name' => $product_name,
                                        '_gift_card_number_enc' => $decrypted_card_number,
                                        'gift_message' => $gift_message,
                                        'gift_status' => $gift_status,
                                        'delivery_status' => $delivery_status ?: 'Not Sentr',
                                        'subtotal' => $subtotal_raw,
                                        'total' => $total_raw,
                                        'recipient_phone' => $grouped_recipients[$key]['phone'],
                                        'delivery_method' => $grouped_recipients[$key]['delivery_method'],
                                    ];
                                }

                                $i = 1;
                                foreach ($grouped_recipients as $recipient) {
                                    $first_gc = reset($recipient['gift_cards']);
                                    echo '<tr'
                                        . ' data-card-id="' . esc_attr($first_gc['gift_card_id'] ?? '') . '"'
                                        . ' data-current-email="' . esc_attr($recipient['email']) . '"'
                                        . ' data-current-phone="' . esc_attr($recipient['phone']) . '"'
                                        . ' data-current-method="' . esc_attr($recipient['delivery_method']) . '"'
                                        . '>';
                                    echo '<td><input type="checkbox" name="select_row[]" value="' . $i . '" class="row-checkbox" /></td>';
                                    echo '<td>' . $i++ . '</td>';

                                    $recipient_gc = [];
                                    $total_cost = 0;
                                    $index = 0;

                                    foreach ($recipient['gift_cards'] as $gc_card) {

                                        // --- GENERATE DYNAMIC URL ---
                                        $card_link_html = $gc_card['product_name']; // Default to text
                        
                                        if (!empty($gc_card['gift_card_id'])) {
                                            $details_url = add_query_arg(
                                                ['card_id' => $gc_card['gift_card_id']],
                                                home_url('/gift-card-detail/')
                                            );

                                            // Link formatting
                                            $card_link_html = '<a href="' . esc_url($details_url) . '" style="text-decoration:underline; color:inherit;">' . esc_html($gc_card['product_name']) . '</a>';
                                        }

                                        // Wrap Image and Name in Anchor Tag
                                        $recipient_gc[$index]['product_image'] = '<img src="' . esc_url($gc_card['product_image']) . '" width="50" style="margin-right:5px; vertical-align:middle;" /> ' . $card_link_html;
                                        $number = $gc_card['_gift_card_number_enc'];
                                        $masked = str_repeat('X', 4) . substr($number, 4);
                                        $recipient_gc[$index]['_gift_card_number_enc'] = esc_html($masked);

                                        // $number = $gc_card['_gift_card_number_enc'];

                                        if (!empty($number)) {
                                            $masked = str_repeat('X', max(0, strlen($number) - 4)) . substr($number, -4);
                                        } else {
                                            $masked = 'XXXX XXXX';
                                        }

                                        // Create same URL
                                        $details_url = '';
                                        if (!empty($gc_card['gift_card_id'])) {
                                            $details_url = add_query_arg(
                                                ['card_id' => $gc_card['gift_card_id']],
                                                home_url('/gift-card-detail/')
                                            );
                                        }

                                        // Make masked number clickable
                                        if (!empty($details_url)) {
                                            $recipient_gc[$index]['_gift_card_number_enc'] =
                                                '<a href="' . esc_url($details_url) . '" style="text-decoration:underline; color:inherit;">' . esc_html($masked) . '</a>';
                                        } else {
                                            $recipient_gc[$index]['_gift_card_number_enc'] = esc_html($masked);
                                        }

                                        $recipient_gc[$index]['gift_message'] = nl2br(esc_html($gc_card['gift_message']));
                                        $recipient_gc[$index]['subtotal'] = '$' . $gc_card['subtotal'];

                                        $total_cost += floatval($gc_card['total']);
                                        $recipient_gc[0]['total'] = $total_cost;

                                        $temp_str = '<span class="status ' . esc_attr(strtolower($gc_card['delivery_status'])) . '">';
                                        $temp_str .= esc_html($gc_card['delivery_status']);
                                        $temp_str .= '</span><br>';
                                        $recipient_gc[$index]['delivery_status'] = $temp_str;
                                        $recipient_gc[$index]['gift_card_id'] = $gc_card['gift_card_id'];
                                        $recipient_gc[$index]['email'] = $recipient['email'];
                                        $recipient_gc[$index]['phone'] = $gc_card['recipient_phone'];
                                        $recipient_gc[$index]['method'] = $gc_card['delivery_method'];
                                        $index++;
                                    }

                                    $index = 0;
                                    if ($recipient_gc) { ?>
                                        <!-- <table class="gift_cards"> -->
                                        <?php
                                        $gd_count = count($recipient_gc) + 1;
                                        $temp_s = ' style="opacity:0;"';

                                        foreach ($recipient_gc as $gc_k => $gc_value) {
                                            if ($index > 0) {
                                                echo '</tr>';
                                                echo '<tr'
                                                    . ' data-card-id="' . esc_attr($gc_value['gift_card_id']) . '"'
                                                    . ' data-current-email="' . esc_attr($gc_value['email']) . '"'
                                                    . ' data-current-phone="' . esc_attr($gc_value['phone']) . '"'
                                                    . ' data-current-method="' . esc_attr($gc_value['method']) . '"'
                                                    . '>';
                                                echo '<td><input type="checkbox" name="select_row[]" value="' . $i . '" class="row-checkbox" /></td>';
                                                echo '<td>' . $i++ . '</td>';
                                            }
                                            ?>
                                            <!-- <tr> -->
                                            <td><?= $gc_value['product_image']; ?></td>
                                            <?php
                                            if ($index == 0) {
                                                echo '<td>' . esc_html($recipient['name']) . '</td>';
                                                echo '<td>' . esc_html($recipient['email']) . '</td>';
                                            } else {
                                                echo '<td' . $temp_s . '>' . esc_html($recipient['name']) . '</td>';
                                                echo '<td' . $temp_s . '>' . esc_html($recipient['email']) . '</td>';
                                            }
                                            ?>
                                            <td><?= $gc_value['_gift_card_number_enc']; ?></td>
                                            <td><?= $gc_value['gift_message']; ?></td>
                                            <td><?= $gc_value['subtotal']; ?></td>
                                        <?php if ($index == 0) { ?>
                                                <td><?= wc_price($gc_value['total']); ?></td>
                                        <?php } else { ?>
                                                <td style="opacity:0;"><?= wc_price($gc_value['total']); ?></td>
                                        <?php } ?>
                                            <?php
                                            // Remove HTML tags and whitespace
                                            $status_raw = strip_tags($gc_value['delivery_status']);
                                            $status_clean = trim($status_raw);

                                            // Use the original status directly
                                            $status_label = $status_clean;
                                            $status_class = strtolower($status_clean);
                                            ?>
                                            <td class="status <?= esc_attr($status_class); ?>">
                                                <span><?= esc_html($status_label); ?></span>
                                                <input type="hidden" class="gcp-btn-resend"
                                                    data-card-id="<?= esc_attr($gc_value['gift_card_id']); ?>"
                                                    data-current-email="<?= esc_attr($gc_value['email']); ?>"
                                                    data-current-phone="<?= esc_attr($gc_value['phone']); ?>"
                                                    data-current-method="<?= esc_attr($gc_value['method']); ?>"
                                                    style="display:block; margin-top:4px; font-size:11px; padding:2px 8px; cursor:pointer;"></input>
                                            </td>
                                        <?php $index++;
                                        } ?>
                                <?php }


                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
    <?php }
} else { ?>
    <p>Order not found.</p>
<?php } ?>

<?php if (!$is_customer_user): ?>
    <!-- ============================================================
     GCP RESEND MODAL
     Actions match class-admin-card-portal-ajax.php exactly.
     gcp_portal_vars is inlined here because this page does NOT
     go through the admin portal's wp_localize_script call.
     ============================================================ -->

    <div id="gcp-resend-modal" class="gcp-modal-overlay" style="display:none;">
        <div class="gcp-modal-content">
            <span class="gcp-modal-close">&times;</span>

            <!-- Step 1: Confirm -->
            <div id="gcp-view-confirm" class="gcp-modal-view">
                <h3>Are you sure you would like to resend this card to <span class="gcp-resend-email-target"
                        style="font-weight:600;"></span>?</h3>
                <div class="gcp-modal-actions">
                    <button id="gcp-confirm-resend-btn" class="btn btn-black">Yes</button>
                    <button id="gcp-trigger-update-view" class="btn-link">No, update delivery method</button>
                </div>
            </div>

            <!-- Step 2: Update delivery method -->
            <div id="gcp-view-update" class="gcp-modal-view" style="display:none; text-align:left;">
                <h3 style="margin-top:0;">Update the recipient delivery method</h3>
                <div class="gcp-form-group">
                    <label>Delivery Method</label>
                    <select id="gcp-update-method-select" class="gcp-input">
                        <option value="email">Email</option>
                        <option value="sms">Mobile (SMS)</option>
                    </select>
                </div>
                <div id="gcp-container-email" class="gcp-form-group" style="display:none;">
                    <label>New Email Address</label>
                    <input type="text" id="gcp-input-email" class="gcp-input" placeholder="name@example.com">
                    <p id="gcp-error-email" class="gcp-error-text"
                        style="display:none; color:red; font-size:12px; margin-top:5px;"></p>
                </div>
                <div id="gcp-container-mobile" class="gcp-form-group" style="display:none;">
                    <label>New Mobile Number</label>
                    <input type="text" id="gcp-input-mobile" class="gcp-input" placeholder="04XX XXX XXX">
                    <p id="gcp-error-mobile" class="gcp-error-text"
                        style="display:none; color:red; font-size:12px; margin-top:5px;"></p>
                </div>
                <div class="gcp-modal-actions">
                    <button id="gcp-submit-update-btn" class="btn btn-black" style="width:100%;">Update</button>
                </div>
            </div>

            <!-- Step 3: OTP -->
            <div id="gcp-view-otp" class="gcp-modal-view" style="display:none; text-align:center;">
                <h2 style="margin-top:0; font-size:24px; font-weight:bold; margin-bottom:10px;">Check your email</h2>
                <p style="font-size:14px; color:#333; margin-bottom:25px; line-height:1.5;">
                    We've sent you a one-time passcode. Please enter it below.
                </p>
                <div class="gcp-otp-wrapper" style="margin-bottom:25px;">
                    <div class="gcp-otp-inputs">
                        <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                        <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                        <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                        <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                        <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                        <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                    </div>
                    <p id="gcp-otp-error" class="gcp-error-text"
                        style="display:none; color:red; margin-top:10px; font-size:13px;"></p>
                </div>
                <div class="gcp-modal-actions">
                    <button id="gcp-verify-otp-btn" class="btn btn-black"
                        style="width:100%; height:48px; font-size:16px;">Submit</button>
                    <div style="margin-top:15px; font-size:13px; color:#666;">
                        <a href="#" id="gcp-resend-otp-link" style="color:#007bff; text-decoration:underline;">Resend
                            now</a>
                        <span id="gcp-otp-timer" style="display:none; color:#999; margin-left:5px;"></span>
                    </div>
                </div>
            </div>

            <!-- Step 4: Success -->
            <div id="gcp-view-success" class="gcp-modal-view" style="display:none; text-align:center;">
                <h2 style="font-size:24px; font-weight:bold; margin-bottom:10px;">Success</h2>
                <div style="margin:20px 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80" fill="none">
                        <path
                            d="M40 0C17.92 0 0 17.92 0 40C0 62.08 17.92 80 40 80C62.08 80 80 62.08 80 40C80 17.92 62.08 0 40 0ZM40 72C22.36 72 8 57.64 8 40C8 22.36 22.36 8 40 8C57.64 8 72 22.36 72 40C72 57.64 57.64 72 40 72ZM55.52 25.16L32 48.68L24.48 41.16C22.92 39.6 20.4 39.6 18.84 41.16C17.28 42.72 17.28 45.24 18.84 46.8L29.2 57.16C30.76 58.72 33.28 58.72 34.84 57.16L61.2 30.8C62.76 29.24 62.76 26.72 61.2 25.16C59.64 23.6 57.08 23.6 55.52 25.16Z"
                            fill="#67D6C8" />
                    </svg>
                </div>
                <p style="font-size:15px; line-height:1.5;">
                    This card has been resent to <span class="gcp-resend-email-target" style="font-weight:600;"></span>
                </p>
            </div>

        </div><!-- /.gcp-modal-content -->
    </div><!-- /#gcp-resend-modal -->

    <script>
        jQuery(document).ready(function ($) {

            /* ------------------------------------------------------------------
               Inline vars — replaces wp_localize_script which only runs on the
               admin portal page. All values are safe server-side PHP echoes.
            ------------------------------------------------------------------ */
            var gcpResend = {
                ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('gcp_admin_portal_nonce')); ?>'
            };

            /* ------------------------------------------------------------------
               State
            ------------------------------------------------------------------ */
            var otpTimerInterval;
            var currentCardData = {
                id: 0,
                method: 'email',
                pendingUpdate: {}
            };

            /* ------------------------------------------------------------------
               Helpers  (identical logic to gcp-admin-card-portal.js)
            ------------------------------------------------------------------ */
            function startOtpTimer(duration) {
                var timer = duration;
                var link = $('#gcp-resend-otp-link');
                var timerSpan = $('#gcp-otp-timer');

                link.css({ 'pointer-events': 'none', opacity: '0.5', 'text-decoration': 'none' });
                timerSpan.show();
                clearInterval(otpTimerInterval);

                otpTimerInterval = setInterval(function () {
                    var m = parseInt(timer / 60, 10);
                    var s = parseInt(timer % 60, 10);
                    m = m < 10 ? '0' + m : m;
                    s = s < 10 ? '0' + s : s;
                    timerSpan.text('(' + m + ':' + s + ')');
                    if (--timer < 0) {
                        clearInterval(otpTimerInterval);
                        link.css({ 'pointer-events': 'auto', opacity: '1', 'text-decoration': 'underline' });
                        timerSpan.hide();
                    }
                }, 1000);
            }

            function changeView(viewId) {
                $('.gcp-modal-view').hide();
                $('#gcp-view-' + viewId).fadeIn(200);
            }

            function closeModal() {
                $('#gcp-resend-modal').fadeOut(200, function () {
                    changeView('confirm');
                    $('#gcp-confirm-resend-btn').text('Yes').prop('disabled', false);
                    $('#gcp-submit-update-btn').text('Update').prop('disabled', false);
                    $('.gcp-error-text').hide();
                    $('#gcp-container-email, #gcp-container-mobile').hide();
                    $('.gcp-otp-digit').val('');
                    clearInterval(otpTimerInterval);
                });
            }

            function validateEmail(v) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
            }
            function validateAusMobile(v) {
                return /^(\+?61|0)4\d{8}$/.test(v.replace(/[\s\-]/g, ''));
            }

            /* sendOTP — action: gcp_portal_send_update_otp */
            function sendOTP() {
                var btn = $('#gcp-submit-update-btn');
                btn.data('orig', btn.text()).text('Sending OTP…').prop('disabled', true);

                $.ajax({
                    url: gcpResend.ajax_url,
                    type: 'POST',
                    data: { action: 'gcp_portal_send_update_otp', nonce: gcpResend.nonce },
                    success: function (res) {
                        if (res.success) {
                            changeView('otp');
                            startOtpTimer(120);
                            $('.gcp-otp-digit').first().focus();
                        } else {
                            alert(res.data || 'Failed to send OTP.');
                        }
                    },
                    error: function () { alert('System error sending OTP.'); },
                    complete: function () { btn.text(btn.data('orig')).prop('disabled', false); }
                });
            }

            /* ------------------------------------------------------------------
               Open modal — called by both per-row and bulk buttons
            ------------------------------------------------------------------ */
            function openModal(cardId, email, phone, method) {
                currentCardData.id = cardId;
                currentCardData.method = (method || 'email').toLowerCase().indexOf('sms') !== -1 ? 'sms' : 'email';

                var contact = currentCardData.method === 'sms' ? (phone || 'the recipient') : (email || 'the recipient');
                $('.gcp-resend-email-target').text(contact);
                $('#gcp-confirm-resend-btn').data('card-id', cardId);

                changeView('confirm');
                $('#gcp-resend-modal').fadeIn(200);
            }

            /* Per-row Resend buttons */
            $(document).on('click', '.gcp-btn-resend', function (e) {
                e.preventDefault();
                var b = $(this);
                openModal(b.data('card-id'), b.data('current-email'), b.data('current-phone'), b.data('current-method'));
            });

            /* Bulk Resend button — reads from the checked row's <tr> */
            $('#gcp-bulk-resend-btn').on('click', function (e) {
                e.preventDefault();
                var checked = $('.row-checkbox:checked').first();
                if (!checked.length) {
                    alert('Please select at least one row to resend.');
                    return;
                }
                var row = checked.closest('tr');
                openModal(
                    row.data('card-id'),
                    row.data('current-email'),
                    row.data('current-phone'),
                    row.data('current-method')
                );
            });

            /* ------------------------------------------------------------------
               Close
            ------------------------------------------------------------------ */
            $('#gcp-resend-modal').on('click', function (e) {
                if (e.target === this) { closeModal(); }
            });
            $(document).on('click', '.gcp-modal-close', function () { closeModal(); });
            $(document).keyup(function (e) {
                if (e.key === 'Escape' && $('#gcp-resend-modal').is(':visible')) { closeModal(); }
            });

            /* ------------------------------------------------------------------
               STEP 1 — Yes: straight resend, no OTP needed
               action: gcp_portal_resend_card
            ------------------------------------------------------------------ */
            $('#gcp-confirm-resend-btn').on('click', function (e) {
                e.preventDefault();
                var btn = $(this);
                var cardId = btn.data('card-id');
                btn.text('Sending…').prop('disabled', true);

                $.ajax({
                    url: gcpResend.ajax_url,
                    type: 'POST',
                    data: { action: 'gcp_portal_resend_card', card_id: cardId, nonce: gcpResend.nonce },
                    success: function (res) {
                        if (res.success) {
                            changeView('success');
                        } else {
                            alert('Error: ' + res.data);
                            btn.text('Yes').prop('disabled', false);
                        }
                    },
                    error: function () {
                        alert('System error. Please try again.');
                        btn.text('Yes').prop('disabled', false);
                    }
                });
            });

            /* ------------------------------------------------------------------
               STEP 1 → STEP 2 — No, update delivery method
            ------------------------------------------------------------------ */
            $('#gcp-trigger-update-view').on('click', function (e) {
                e.preventDefault();
                $('#gcp-update-method-select').val(currentCardData.method);
                $('.gcp-error-text').hide();
                $('#gcp-container-email, #gcp-container-mobile').hide();
                if (currentCardData.method === 'sms') {
                    $('#gcp-container-mobile').show();
                } else {
                    $('#gcp-container-email').show();
                }
                changeView('update');
            });

            /* Toggle email/SMS fields */
            $('#gcp-update-method-select').on('change', function () {
                var m = $(this).val();
                $('.gcp-error-text').hide();
                $('#gcp-container-email').toggle(m === 'email');
                $('#gcp-container-mobile').toggle(m === 'sms');
            });

            /* ------------------------------------------------------------------
               STEP 2 — Update: validate → sendOTP
            ------------------------------------------------------------------ */
            $('#gcp-submit-update-btn').on('click', function (e) {
                e.preventDefault();
                var method = $('#gcp-update-method-select').val();
                var value, errorBox;

                if (method === 'sms') {
                    value = $('#gcp-input-mobile').val().trim();
                    errorBox = $('#gcp-error-mobile');
                } else {
                    value = $('#gcp-input-email').val().trim();
                    errorBox = $('#gcp-error-email');
                }

                $('.gcp-error-text').hide();

                if (!value) {
                    errorBox.text('This field is required.').show(); return;
                }
                if (method === 'email' && !validateEmail(value)) {
                    errorBox.text('Please enter a valid email address.').show(); return;
                }
                if (method === 'sms' && !validateAusMobile(value)) {
                    errorBox.text('Please enter a valid Australian mobile number (e.g. 0412 345 678).').show(); return;
                }

                /* Store for OTP verification step — same shape as gcp-admin-card-portal.js */
                currentCardData.pendingUpdate = { method: method, value: value };
                sendOTP();
            });

            /* ------------------------------------------------------------------
               STEP 3 — OTP digit auto-advance & paste
               (identical to gcp-admin-card-portal.js)
            ------------------------------------------------------------------ */
            $(document).on('keyup', '.gcp-otp-digit', function (e) {
                var key = e.which || e.keyCode;
                var inputs = $('.gcp-otp-digit');
                var idx = inputs.index(this);
                if ((key === 8 || key === 46) && idx > 0 && !$(this).val()) {
                    inputs.eq(idx - 1).focus();
                    return;
                }
                if ($(this).val().length === 1 && idx < inputs.length - 1) {
                    inputs.eq(idx + 1).focus();
                }
                $('#gcp-otp-error').hide();
            });

            $(document).on('paste', '.gcp-otp-digit', function (e) {
                var digits = e.originalEvent.clipboardData.getData('text').replace(/\D/g, '').split('');
                if (digits.length) {
                    $('.gcp-otp-digit').each(function (i) { if (digits[i]) $(this).val(digits[i]); });
                    $('.gcp-otp-digit').eq(Math.min(digits.length, 5)).focus();
                    e.preventDefault();
                }
            });

            /* ------------------------------------------------------------------
               STEP 3 — Resend OTP link
               action: gcp_portal_send_update_otp  (same as first send)
            ------------------------------------------------------------------ */
            $('#gcp-resend-otp-link').on('click', function (e) {
                e.preventDefault();
                $.ajax({
                    url: gcpResend.ajax_url,
                    type: 'POST',
                    data: { action: 'gcp_portal_send_update_otp', nonce: gcpResend.nonce },
                    success: function (res) {
                        if (res.success) {
                            startOtpTimer(120);
                        } else {
                            alert(res.data || 'Failed to resend code.');
                        }
                    },
                    error: function () { alert('System error. Please try again.'); }
                });
            });

            /* ------------------------------------------------------------------
               STEP 3 — Verify OTP → update meta → resend card
               action: gcp_portal_verify_otp_and_update
               Sends: otp_code, card_id, update_data: { method, value }
               Handler saves new email/phone to post meta then resends.
            ------------------------------------------------------------------ */
            $('#gcp-verify-otp-btn').on('click', function (e) {
                e.preventDefault();
                var code = '';
                $('.gcp-otp-digit').each(function () { code += $(this).val(); });

                if (code.length < 6) {
                    $('#gcp-otp-error').text('Please enter the full 6-digit code.').show();
                    return;
                }

                var btn = $(this);
                btn.text('Verifying…').prop('disabled', true);

                $.ajax({
                    url: gcpResend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gcp_portal_verify_otp_and_update',
                        nonce: gcpResend.nonce,
                        otp_code: code,
                        card_id: currentCardData.id,
                        update_data: currentCardData.pendingUpdate
                    },
                    success: function (res) {
                        if (res.success) {
                            /* Show the new contact in the success message */
                            $('.gcp-resend-email-target').text(currentCardData.pendingUpdate.value);
                            changeView('success');
                        } else {
                            $('#gcp-otp-error').text(res.data).show();
                            btn.text('Submit').prop('disabled', false);
                        }
                    },
                    error: function () {
                        $('#gcp-otp-error').text('System error. Please try again.').show();
                        btn.text('Submit').prop('disabled', false);
                    }
                });
            });

        });
    </script>
<?php endif; ?>