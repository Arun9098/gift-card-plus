<?php
/**
 * Template Name: My Orders
 * 
 * This page displays all orders for the logged-in user
 */

// Check if user is logged in
if (!is_user_logged_in()) {
    // Redirect to login page
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header();

$user_id = get_current_user_id();
$orders = wc_get_orders([
    'customer' => $user_id,
    'limit' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    'return' => 'objects',
]);
$total_orders = count($orders);
?>

<div class="my-orders-page">
    <div class="container">
        <h1>My Orders</h1>
        <p class="track-card-notice">Cannot find a card you are looking for? You can <a href="<?php echo site_url('/track-card/');?>">track a card here</a> </p>

        
        <?php if ($total_orders > 0): ?>
            <!-- <div class="orders-summary">
                <div class="summary-item">
                    <span class="summary-label">Total Orders</span>
                    <span class="summary-value"><?php //echo esc_html($total_orders); ?></span>
                </div>
            </div> -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Search order ID, recipient, card">
            </div>
            <div class="orders-table-container">
                <table id="my-orders-table" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th style="display:none;">Timestamp</th>
                            <th>Order date</th>
                            <th>Order number</th>
                            <th>Recipient(s)</th>
                            <th style="min-width: 240px;">Gift cards</th>
                            <th>Qty</th>
                            <th>Order Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($orders as $order): 
                            $order_id = $order->get_id();
                            $order_number = $order->get_order_number();
                            $order_date = wc_format_datetime($order->get_date_created(), 'm/d/Y');
                            $order_status = $order->get_status();
                            $view_order_url = wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount'));
                            $order_received_url = add_query_arg('key', $order->get_order_key(), wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()));

                            // --- STEP 1: GROUP DATA ---
                            // Structure: [ 'recipient_key' => [ 'info' => '...', 'items' => [ 'card_name' => qty ] ] ]
                            $grouped_data = [];

                            foreach ($order->get_items() as $item) {
                                // 1. Get Recipient Info
                                $email = wc_get_order_item_meta($item->get_id(), '_recipient_email', true);
                                $gift_card_post_id = wc_get_order_item_meta($item->get_id(), '_gift_card_post_id', true);
                                
                                $phone = '';
                                if (!empty($gift_card_post_id)) {
                                    $phone = get_post_meta($gift_card_post_id, '_recipient_phone', true);
                                }

                                // 2. Format Phone (Your existing logic)
                                $formatted_phone = $phone;
                                if (!empty($phone)) {
                                    $clean_phone = preg_replace('/[^\d+]/', '', $phone);
                                    if (preg_match('/^\+?61(\d{9})$/', $clean_phone, $matches) || preg_match('/^(\d{9})$/', $clean_phone, $matches)) {
                                        $formatted_phone = '(+61) ' . substr($matches[1], 0, 3) . ' ' . substr($matches[1], 3, 3) . ' ' . substr($matches[1], 6, 3);
                                    }
                                }

                                // 3. Create Unique Recipient Key (Email + Phone)
                                $recipient_key = $email . '|' . $formatted_phone;

                                if (!isset($grouped_data[$recipient_key])) {
                                    $grouped_data[$recipient_key] = [
                                        'email' => $email,
                                        'phone' => $formatted_phone,
                                        'products' => []
                                    ];
                                }

                                // 4. Group Products (Clean Name)
                                $raw_name = $item->get_name();
                                $clean_name = preg_replace('/\s+\d+$/', '', trim($raw_name)); // Remove trailing numbers
                                
                                // --- NEW CHANGE START: Calculate Unit Price & Prepend ---
                                $line_total = $item->get_total(); // Total for this line (e.g. $20)
                                $item_qty   = $item->get_quantity(); // Qty for this line (e.g. 2)
                                
                                // Calculate single unit price (e.g. $10)
                                $unit_price = ($item_qty > 0) ? ($line_total / $item_qty) : 0;
                                
                                // Format price (strip_tags removes HTML to keep it simple text like "$10.00")
                                $price_text = strip_tags(wc_price($unit_price, array('currency' => $order->get_currency())));
                                
                                // Combine: "$10.00 Christmas Cheer Test"
                                $clean_name = $price_text . ' ' . $clean_name;
                                // --- NEW CHANGE END ---

                                $qty = $item->get_quantity();

                                if (isset($grouped_data[$recipient_key]['products'][$clean_name])) {
                                    $grouped_data[$recipient_key]['products'][$clean_name] += $qty;
                                } else {
                                    $grouped_data[$recipient_key]['products'][$clean_name] = $qty;
                                }
                            }

                            // --- STEP 2: BUILD VISUAL COLUMNS ---
                            // We build HTML for the 3 columns line-by-line to ensure they have matching heights
                            $col_recipient_html = '';
                            $col_giftcard_html = '';
                            $col_qty_html = '';

                            foreach ($grouped_data as $r_data) {
                                $is_first_product_for_recipient = true;

                                foreach ($r_data['products'] as $prod_name => $prod_qty) {
                                    
                                    // Column 1: Recipient (Only show details on the first row of this recipient)
                                    $col_recipient_html .= '<div class="line-item">';
                                    if ($is_first_product_for_recipient) {
                                        $col_recipient_html .= '<span class="rec-email">' . esc_html($r_data['email']) . '</span>';
                                        if (!empty($r_data['phone'])) {
                                            $col_recipient_html .= '<br><span class="rec-phone">' . esc_html($r_data['phone']) . '</span>';
                                        }
                                    } else {
                                        $col_recipient_html .= ''; 
                                    }
                                    $col_recipient_html .= '</div>';

                                    // Column 2: Gift Card Name
                                    $col_giftcard_html .= '<div class="line-item">';
                                    $col_giftcard_html .= esc_html($prod_name);
                                    $col_giftcard_html .= '</div>';

                                    // Column 3: Quantity
                                    $col_qty_html .= '<div class="line-item">';
                                    $col_qty_html .= esc_html($prod_qty);
                                    $col_qty_html .= '</div>';

                                    $is_first_product_for_recipient = false;
                                }
                            }
                        ?>
                            <tr>
                                 <td style="display:none;">
                                    <?php echo esc_html($order->get_date_created()->getTimestamp()); ?>
                                </td>
                                <td><?php echo esc_html($order_date); ?></td>
                                <td class="order-no"><a href="<?php echo esc_url($order_received_url); ?>"><?php echo esc_html($order_number); ?></a></td>
                                
                                <td class="col-aligned"><?php echo $col_recipient_html; ?></td>
                                <td class="col-aligned"><?php echo $col_giftcard_html; ?></td>
                                <td class="col-aligned"><?php echo $col_qty_html; ?></td>
                                
                                <td class="td-order-status">
                                    <span class="order-status status-<?php echo esc_attr($order_status); ?>">
                                        <?php
                                        if($order_status == 'completed'){
                                            $order_status = 'Complete';
                                        } 
                                        echo esc_html(wc_get_order_status_name($order_status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="order-actions">
                                        <a href="<?php echo esc_url($view_order_url); ?>" class="btn-view">Track</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-orders">
                <div class="no-orders-icon">📦</div>
                <h2>No Orders Yet</h2>
                <p>You haven't placed any orders yet.</p>
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="btn-shop">
                    Start Shopping
                </a>
            </div>
        <?php endif; ?>
        
        <?php
        // Allow WPBakery content to be displayed
        while (have_posts()) :
            the_post();
            the_content();
        endwhile;
        ?>
    </div>
</div>

<?php
get_footer();