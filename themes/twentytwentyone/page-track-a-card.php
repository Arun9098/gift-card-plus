<?php
/**
 * Template Name: Track a Card
 * 
 * This page allows logged-in users to track their gift cards by card number
 */

// Check if user is logged in
if (!is_user_logged_in()) {
    // Redirect to login page
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header();

$user_id = get_current_user_id();
$recipient_email = isset($_GET['recipient_email']) ? sanitize_email($_GET['recipient_email']) : '';
$order_number = isset($_GET['order_number']) ? sanitize_text_field($_GET['order_number']) : '';
$gift_cards = [];
$error_message = '';
$success_message = '';

// If search is submitted (both recipient email and order number are required)
if (!empty($recipient_email) && !empty($order_number)) {
    // Validate email format
    if (!is_email($recipient_email)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Get all orders for the current user
        $user_orders = wc_get_orders([
            'customer' => $user_id,
            'limit' => -1,
            'return' => 'ids',
        ]);
        
        // If order number is provided, find the order ID (search all orders, not just user's)
        $target_order = null;
        $target_order_id = null;
        $filter_by_order = false;
        
        if (!empty($order_number)) {
            $filter_by_order = true;
            
            // Clean the search order number - remove # and whitespace
            $search_order_number = trim(str_replace('#', '', $order_number));
            // Extract numeric part for comparison
            $search_order_number_numeric = preg_replace('/\D/', '', $search_order_number);
            
            // First, try to find order in user's orders
            if (!empty($user_orders)) {
                foreach ($user_orders as $order_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $order_num = $order->get_order_number();
                        $order_num_clean = trim(str_replace('#', '', (string)$order_num));
                        $order_num_numeric = preg_replace('/\D/', '', $order_num_clean);
                        
                        // Try multiple matching strategies
                        $match_found = false;
                        
                        // 1. Exact string match (after cleaning)
                        if ($order_num_clean === $search_order_number) {
                            $match_found = true;
                        }
                        // 2. Numeric part match
                        elseif (!empty($search_order_number_numeric) && $order_num_numeric === $search_order_number_numeric) {
                            $match_found = true;
                        }
                        // 3. Original comparison
                        elseif ($order_num == $order_number || (string)$order_num === (string)$order_number) {
                            $match_found = true;
                        }
                        // 4. Match with order ID
                        elseif ($order_id == $order_number || (string)$order_id === (string)$order_number || 
                                $order_id == $search_order_number || (string)$order_id === $search_order_number_numeric) {
                            $match_found = true;
                        }
                        
                        if ($match_found) {
                            $target_order = $order;
                            $target_order_id = $order_id;
                            break;
                        }
                    }
                }
            }
            
        }
        
        // Proceed with search if we have user orders OR if order number was found
        if (!empty($user_orders) || $target_order_id) {
            
            // Proceed with search
            if (true) {
                // Build meta query based on whether order number is provided
                $meta_query = [];
                
                if ($filter_by_order && $target_order_id) {
                    // Filter by both order ID and recipient email
                    $meta_query = [
                        'relation' => 'AND',
                        [
                            'key' => '_order_id',
                            'value' => $target_order_id,
                            'compare' => '=',
                        ],
                        [
                            'key' => '_recipient_email',
                            'value' => $recipient_email,
                            'compare' => '=',
                        ],
                    ];
                } else {
                    // Only filter by recipient email (search across all user orders)
                    $meta_query = [
                        'relation' => 'AND',
                        [
                            'key' => '_order_id',
                            'value' => $user_orders,
                            'compare' => 'IN',
                        ],
                        [
                            'key' => '_recipient_email',
                            'value' => $recipient_email,
                            'compare' => '=',
                        ],
                    ];
                }

                // Search strategy: If no order number, search by email first, then verify order belongs to user
                // If order number provided, search by both order and email
                $gift_cards_query = [];
                $recipient_email_clean = strtolower(trim($recipient_email));
                
               /* echo '<pre>';
                echo '=== DEBUG: Search Parameters ===' . "\n";
                echo 'Recipient Email (clean): ' . $recipient_email_clean . "\n";
                echo 'Filter by Order: ' . ($filter_by_order ? 'Yes' : 'No') . "\n";
                echo 'Target Order ID: ' . ($target_order_id ? $target_order_id : 'None') . "\n";
                echo 'User Orders Count: ' . count($user_orders) . "\n";
                echo 'User Orders: ' . print_r($user_orders, true) . "\n";
                echo '</pre>';*/
                
                if ($filter_by_order && $target_order_id) {
                    // Specific order: Search gift cards for this order and email
                    $all_gift_cards = get_posts([
                        'post_type' => 'gift_card',
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'meta_query' => [
                            'relation' => 'AND',
                            [
                                'key' => '_order_id',
                                'value' => $target_order_id,
                                'compare' => '=',
                            ],
                            [
                                'key' => '_recipient_email',
                                'value' => $recipient_email,
                                'compare' => '=',
                            ],
                        ],
                    ]);
                    
                    // echo '<pre>';
                    // echo '=== DEBUG: Filtered by Order ===' . "\n";
                    // echo 'Target Order ID: ' . $target_order_id . "\n";
                    // echo 'Gift Cards Found for Order + Email: ' . count($all_gift_cards) . "\n";
                    // echo '</pre>';
                    
                    // Add all matching gift cards (already filtered by email in query)
                    foreach ($all_gift_cards as $gift_card_post) {
                        $recipient_email_meta = get_post_meta($gift_card_post->ID, '_recipient_email', true);
                        if (!empty($recipient_email_meta)) {
                            $recipient_email_meta_clean = strtolower(trim($recipient_email_meta));
                            if ($recipient_email_meta_clean === $recipient_email_clean) {
                                $gift_cards_query[] = $gift_card_post;
                            }
                        }
                    }
                    
                    // Also try case-insensitive search if exact match didn't work
                    if (empty($gift_cards_query)) {
                        $all_gift_cards_by_order = get_posts([
                            'post_type' => 'gift_card',
                            'post_status' => 'publish',
                            'posts_per_page' => -1,
                            'meta_query' => [
                                [
                                    'key' => '_order_id',
                                    'value' => $target_order_id,
                                    'compare' => '=',
                                ],
                            ],
                        ]);
                        
                        foreach ($all_gift_cards_by_order as $gift_card_post) {
                            $recipient_email_meta = get_post_meta($gift_card_post->ID, '_recipient_email', true);
                            if (!empty($recipient_email_meta)) {
                                $recipient_email_meta_clean = strtolower(trim($recipient_email_meta));
                                if ($recipient_email_meta_clean === $recipient_email_clean) {
                                    $gift_cards_query[] = $gift_card_post;
                                }
                            }
                        }
                    }
                } else {
                    // No order number: restrict search to gift cards within the current user's own orders
                    if (!empty($user_orders)) {
                        $all_gift_cards_by_email = get_posts([
                            'post_type' => 'gift_card',
                            'post_status' => 'publish',
                            'posts_per_page' => -1,
                            'meta_query' => [
                                'relation' => 'AND',
                                [
                                    'key' => '_recipient_email',
                                    'value' => $recipient_email,
                                    'compare' => '=',
                                ],
                                [
                                    'key' => '_order_id',
                                    'value' => $user_orders,
                                    'compare' => 'IN',
                                ],
                            ],
                        ]);

                        foreach ($all_gift_cards_by_email as $gift_card_post) {
                            $gift_card_recipient_email = get_post_meta($gift_card_post->ID, '_recipient_email', true);
                            if (!empty($gift_card_recipient_email)) {
                                $gift_card_recipient_email_clean = strtolower(trim($gift_card_recipient_email));
                                if ($gift_card_recipient_email_clean === $recipient_email_clean) {
                                    $gift_cards_query[] = $gift_card_post;
                                }
                            }
                        }
                    }
                }
                
                /*echo '<pre>';
                echo '=== DEBUG: Gift Cards Query Results ===' . "\n";
                echo 'Gift Cards Found: ' . count($gift_cards_query) . "\n";
                if (!empty($gift_cards_query)) {
                    foreach ($gift_cards_query as $gc) {
                        echo 'Gift Card ID: ' . (is_object($gc) ? $gc->ID : $gc) . "\n";
                    }
                }
                echo '</pre>';*/
                
                // Alternative: Search through order items if gift_card post type doesn't have results
                $order_items_gift_cards = [];
                $orders_to_search = $filter_by_order && $target_order ? [$target_order] : [];
                
                // If no order specified, get all user orders
                if (empty($orders_to_search)) {
                    foreach ($user_orders as $order_id) {
                        $order = wc_get_order($order_id);
                        if ($order) {
                            $orders_to_search[] = $order;
                        }
                    }
                }
                
                /*echo '<pre>';
                echo '=== DEBUG: Order Items Search ===' . "\n";
                echo 'Orders to Search: ' . count($orders_to_search) . "\n";
                echo '</pre>';*/
                
                // Always search through order items as well (in case gift cards aren't in post type)
                foreach ($orders_to_search as $order) {
                    foreach ($order->get_items() as $item) {
                        $item_recipient_email = wc_get_order_item_meta($item->get_id(), '_recipient_email', true);
                        if (!empty($item_recipient_email)) {
                            $item_recipient_email_clean = strtolower(trim($item_recipient_email));
                            if ($item_recipient_email_clean === $recipient_email_clean) {
                                // Found matching order item, now get gift card post ID
                                $gift_card_post_id = wc_get_order_item_meta($item->get_id(), '_gift_card_post_id', true);
                                if ($gift_card_post_id) {
                                    $gift_card_post = get_post($gift_card_post_id);
                                    if ($gift_card_post && $gift_card_post->post_type === 'gift_card') {
                                        // Check if already added
                                        $already_added = false;
                                        foreach ($gift_cards_query as $existing) {
                                            if (is_object($existing) && $existing->ID == $gift_card_post->ID) {
                                                $already_added = true;
                                                break;
                                            }
                                        }
                                        if (!$already_added) {
                                            $gift_cards_query[] = $gift_card_post;
                                        }
                                    }
                                } else {
                                    // No gift_card post, get data directly from order item
                                    // Check if already added to avoid duplicates
                                    $already_added = false;
                                    foreach ($order_items_gift_cards as $existing) {
                                        if ($existing['item_id'] == $item->get_id() && $existing['order_id'] == $order->get_id()) {
                                            $already_added = true;
                                            break;
                                        }
                                    }
                                    if (!$already_added) {
                                        $order_items_gift_cards[] = [
                                            'item_id' => $item->get_id(),
                                            'order_id' => $order->get_id(),
                                            'order' => $order,
                                            'card_number' => wc_get_order_item_meta($item->get_id(), '_gift_card_number', true),
                                            'recipient_name' => wc_get_order_item_meta($item->get_id(), '_recipient_name', true),
                                            'recipient_email' => $item_recipient_email,
                                            'product_title' => $item->get_name(),
                                            'price' => $item->get_total(),
                                            'sku' => wc_get_order_item_meta($item->get_id(), '_gift_card_sku', true),
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Process gift cards from gift_card post type
                if (!empty($gift_cards_query)) {
                    foreach ($gift_cards_query as $gift_card_post) {
                        $order_id = get_post_meta($gift_card_post->ID, '_order_id', true);
                        $order = $order_id ? wc_get_order($order_id) : null;
                        
                        $card_number = get_post_meta($gift_card_post->ID, '_gift_card_number', true);
                        $sku = get_post_meta($gift_card_post->ID, '_product_sku', true);
                        $product_id = wc_get_product_id_by_sku($sku);
                        $product_title = $product_id ? get_the_title($product_id) : '';
                        $price = get_post_meta($gift_card_post->ID, '_price', true);
                        $recipient_name = get_post_meta($gift_card_post->ID, '_recipient_name', true);
                        $recipient_email_meta = get_post_meta($gift_card_post->ID, '_recipient_email', true);
                        
                        // Get scheduled date (delivery date)
                        $scheduled_date = get_post_meta($gift_card_post->ID, '_scheduled_date', true);
                        if (empty($scheduled_date)) {
                            // Try to get from order item
                            if ($order) {
                                foreach ($order->get_items() as $item) {
                                    $item_gift_card_post_id = wc_get_order_item_meta($item->get_id(), '_gift_card_post_id', true);
                                    if ($item_gift_card_post_id == $gift_card_post->ID) {
                                        $scheduled_date = wc_get_order_item_meta($item->get_id(), '_scheduled_date', true);
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Get raw status
                        $raw_status = get_post_meta($gift_card_post->ID, '_gift_card_send', true);
                        
                        // Map status for customer view
                        $customer_status = $raw_status;
                        
                        // Map "Sent After Completion" to "Delivered" for customer view
                        if ($raw_status === 'Sent After Completion') {
                            $customer_status = 'Delivered';
                        }
                        
                        // Map internal statuses to "Opened"
                        $internal_statuses_to_opened = ['Activated', 'Swapped', 'Cancelled', 'Activation Expired', 'Expired'];
                        if (in_array($raw_status, $internal_statuses_to_opened)) {
                            $customer_status = 'Opened';
                        }
                        
                        // Only show allowed statuses
                        $allowed_statuses = ['Draft', 'Ordered', 'Processing', 'On Hold', 'Delivered', 'Opened', 'Bounced', 'Failed'];
                        if (!in_array($customer_status, $allowed_statuses) && !empty($raw_status)) {
                            $customer_status = 'Processing'; // Default fallback
                        }
                        
                        $gift_cards[] = [
                            'id' => $gift_card_post->ID,
                            'card_number' => $card_number,
                            'order_id' => $order_id,
                            'order_number' => $order ? $order->get_order_number() : '',
                            'order_date' => $order ? wc_format_datetime($order->get_date_created()) : '',
                            'product_title' => $product_title,
                            'price' => $price,
                            'status' => $customer_status ?: 'Processing',
                            'raw_status' => $raw_status,
                            'created_date' => get_the_date('F j, Y', $gift_card_post->ID),
                            'recipient_name' => $recipient_name,
                            'recipient_email' => $recipient_email_meta,
                            'scheduled_date' => $scheduled_date,
                            'delivery_date' => $scheduled_date ? date('F j, Y', strtotime($scheduled_date)) : ($order ? wc_format_datetime($order->get_date_created(), 'F j, Y') : ''),
                        ];
                    }
                }
                
                // Process gift cards from order items (when no gift_card post exists)
                if (!empty($order_items_gift_cards)) {
                    foreach ($order_items_gift_cards as $item_card) {
                        $card_number = $item_card['card_number'];
                        $product_id = !empty($item_card['sku']) ? wc_get_product_id_by_sku($item_card['sku']) : null;
                        $product_title = $product_id ? get_the_title($product_id) : ($item_card['product_title'] ?: 'N/A');
                        $order = $item_card['order'];
                        
                        // Get scheduled date from order item
                        $scheduled_date = wc_get_order_item_meta($item_card['item_id'], '_scheduled_date', true);
                        
                        // Map status for customer view
                        $customer_status = 'Processing'; // Default for order items
                        
                        $gift_cards[] = [
                            'id' => $item_card['item_id'],
                            'card_number' => $card_number,
                            'order_id' => $item_card['order_id'],
                            'order_number' => $order->get_order_number(),
                            'order_date' => wc_format_datetime($order->get_date_created()),
                            'product_title' => $product_title,
                            'price' => $item_card['price'],
                            'status' => $customer_status,
                            'raw_status' => '',
                            'created_date' => wc_format_datetime($order->get_date_created(), 'F j, Y'),
                            'recipient_name' => $item_card['recipient_name'],
                            'recipient_email' => $item_card['recipient_email'],
                            'scheduled_date' => $scheduled_date,
                            'delivery_date' => $scheduled_date ? date('F j, Y', strtotime($scheduled_date)) : wc_format_datetime($order->get_date_created(), 'F j, Y'),
                        ];
                    }
                }
                
               /* echo '<pre>';
                echo '=== DEBUG: Final Results ===' . "\n";
                echo 'Total Gift Cards Found: ' . count($gift_cards) . "\n";
                echo 'Gift Cards from Post Type: ' . count($gift_cards_query) . "\n";
                echo 'Gift Cards from Order Items: ' . count($order_items_gift_cards) . "\n";
                if (!empty($gift_cards)) {
                    echo 'Gift Card Details:' . "\n";
                    foreach ($gift_cards as $index => $card) {
                        echo '  Card #' . ($index + 1) . ': ID=' . (isset($card['id']) ? $card['id'] : 'N/A') . ', Order=' . (isset($card['order_id']) ? $card['order_id'] : 'N/A') . "\n";
                    }
                }
                echo '</pre>';*/
                
                if (empty($gift_cards)) {
                    // Provide more helpful error message
                    if ($filter_by_order) {
                        $error_message = 'No gift cards found for recipient email "' . esc_html($recipient_email) . '" in order #' . esc_html($order_number) . '.';
                    } else {
                        $error_message = 'No gift cards found for recipient email "' . esc_html($recipient_email) . '" in your orders.';
                    }
                }
            }
        } else {
            $error_message = 'You have no orders yet.';
        }
    }
} elseif (!empty($recipient_email) || !empty($order_number)) {
    $error_message = 'Please enter both recipient email and order number to search.';
}
?>



<div class="track-card-page">
    <div class="container-sm">
        <h1><?php the_title(); ?></h1>
        <p class="help-text"><?php the_field('track_card_content');?></p>
        <div class="search-form-container">
            <form method="GET" action="" class="search-form">
                <div class="form-group">
                    <label for="recipient_email">Recipient Email</label>
                    <input 
                        type="email" 
                        id="recipient_email" 
                        name="recipient_email" 
                        value="<?php echo esc_attr($recipient_email); ?>" 
                        placeholder="Enter recipient email address"
                        required
                    />
                </div>
                <div class="form-group">
                    <label for="order_number">Order Number</label>
                    <input 
                        type="text" 
                        id="order_number" 
                        name="order_number" 
                        value="<?php echo esc_attr($order_number); ?>" 
                        placeholder="Enter order number"
                        required
                    />
                </div>
                <button type="submit" class="btn-search">Search</button>
            </form>
            
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success-message">
                <?php echo esc_html($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($gift_cards)): 
            // Group gift cards by order
            $grouped_by_order = [];
            foreach ($gift_cards as $card) {
                $order_key = $card['order_id'] . '_' . $card['order_number'];
                if (!isset($grouped_by_order[$order_key])) {
                    $grouped_by_order[$order_key] = [
                        'order_id' => $card['order_id'],
                        'order_number' => $card['order_number'],
                        'order_date' => $card['order_date'],
                        'cards' => []
                    ];
                }
                $grouped_by_order[$order_key]['cards'][] = $card;
            }
        ?>
         </div>
            <div class="gift-cards-results">
                <div class="container">
                <?php if ($filter_by_order && !empty($order_number)): ?>
                    <div class="flex-heading">
                        <h2 style="margin: 0;">Results for: Order <?php echo esc_html($order_number); ?></h2>
                        <button type="button" class="btn-white-p2 btn-outline-dark btn-resend-all btn btn-outline" 
                                data-all-gift-card-ids="<?php echo esc_attr(implode(',', array_column($gift_cards, 'id'))); ?>"
                                data-recipient-email="<?php echo esc_attr($recipient_email); ?>">
                            Resend all cards
                        </button>
                    </div>
                <?php else: ?>
                    <h2>Search Results</h2>
                <?php endif; ?>
                
                <?php foreach ($grouped_by_order as $order_group): ?>
                    <div class="order-group">
                        <h3 class="order-group-title">Order #<?php echo esc_html($order_group['order_number']); ?></h3>
                        <div class="table-overflow">
                            <table class="gift-cards-table table-style1">
                                <thead>
                                    <tr>
                                        <th>Delivery Date</th>
                                        <th>Recipients</th>
                                        <th>Gift Card(s)</th>
                                        <th>Card Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Display each gift card on its own row
                                    foreach ($order_group['cards'] as $card): 
                                        $status = $card['status'] ?: 'Processing';
                                        $status_class = strtolower(str_replace(' ', '-', $status));
                                    ?>
                                        <tr>
                                            <td><?php echo esc_html($card['delivery_date'] ?: 'N/A'); ?></td>
                                            <td>
                                                <div class="recipient-info">
                                                    <p style="margin:0"><strong><?php echo esc_html($card['recipient_name'] ?: 'N/A'); ?></strong></p>
                                                    <span class="recipient-email"><?php echo esc_html($card['recipient_email']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="gift-card-info">
                                                    <span class="card-details"><?php echo esc_html($card['product_title']); ?> - $<?php echo esc_html($card['price']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="gift-card-status status-<?php echo esc_attr($status_class); ?>">
                                                    <span><?php echo esc_html($status); ?></span>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn-resend btn underline" 
                                                        data-gift-card-ids="<?php echo esc_attr($card['id']); ?>" 
                                                        data-recipient-email="<?php echo esc_attr($card['recipient_email']); ?>"
                                                        data-recipient-name="<?php echo esc_attr($card['recipient_name']); ?>"
                                                        data-order-id="<?php echo esc_attr($order_group['order_id']); ?>">
                                                    Resend
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php elseif (!empty($recipient_email) && !empty($order_number)): ?>
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <p>No gift cards found matching your search.</p>
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

<!-- Resend Modal -->
<div id="resendModal" class="resend-modal">
    <div class="resend-modal-content">
        <div class="resend-modal-header">
            <h3>Resend Gift Card</h3>
            <button type="button" class="resend-modal-close">&times;</button>
        </div>
        <div class="resend-modal-body">
            <div id="resendMessage"></div>
            <div class="resend-content">
                <p>Are you sure you want to resend the gift card to:</p>
                <div class="recipient-email-display" id="resendRecipientEmail"></div>
                <p>This will send the gift card details to the recipient's email address.</p>
            </div>
        </div>
        <div class="resend-modal-footer">
            <button type="button" class="btn-resend-cancel">Cancel</button>
            <button type="button" class="btn-resend-confirm" id="confirmResendBtn">Resend</button>
        </div>
    </div>
</div>



<?php
get_footer();


?>
<script>
    jQuery(document).ready(function($) {
    let currentGiftCardIds = '';
    let currentRecipientEmail = '';
    
    // Open modal when Resend button is clicked
    $('.btn-resend').on('click', function() {
        currentGiftCardIds = $(this).data('gift-card-ids');
        currentRecipientEmail = $(this).data('recipient-email');
        const recipientName = $(this).data('recipient-name') || '';
        
        $('#resendRecipientEmail').html(
            '<strong>' + (recipientName ? recipientName + '<br>' : '') + '</strong>' + 
            currentRecipientEmail
        );
        $('#resendMessage').empty();
        $('#resendModal').addClass('active');
    });
    
    // Close modal
    $('.resend-modal-close, .btn-resend-cancel').on('click', function() {
        $('#resendModal').removeClass('active');
        $('#resendMessage').empty();
        // Reset button states
        $('#confirmResendBtn').prop('disabled', false).text('Resend').show();
        $('.btn-resend-cancel').text('Cancel');
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('resend-modal')) {
            $('#resendModal').removeClass('active');
            $('#resendMessage').empty();
        }
    });
    
    // Confirm resend
    $('#confirmResendBtn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Sending...');
        $('#resendMessage').empty();
        
        $.ajax({
            url: trackcard.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'resend_gift_card',
                gift_card_ids: currentGiftCardIds,
                recipient_email: currentRecipientEmail,
                nonce: trackcard.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message with checkmark icon
                    $('#resendMessage').html(`
                        <div class="resend-success-message" style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 24px;">
                                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 0C8.96 0 0 8.96 0 20C0 31.04 8.96 40 20 40C31.04 40 40 31.04 40 20C40 8.96 31.04 0 20 0ZM20 36C11.18 36 4 28.82 4 20C4 11.18 11.18 4 20 4C28.82 4 36 11.18 36 20C36 28.82 28.82 36 20 36ZM27.76 12.58L16 24.34L12.24 20.58C11.46 19.8 10.2 19.8 9.42 20.58C8.64 21.36 8.64 22.62 9.42 23.4L14.6 28.58C15.38 29.36 16.64 29.36 17.42 28.58L30.6 15.4C31.38 14.62 31.38 13.36 30.6 12.58C29.82 11.8 28.54 11.8 27.76 12.58Z"
                                        fill="#037847"/>
                                </svg>
                            </span>
                            <div>
                                <p>Gift card sent successfully!</p>
                                ${response?.data?.message || 'The gift card has been resent to the recipient.'}
                            </div>
                        </div>
                    `);

                    // Hide the confirm button and show close button
                    $btn.hide();
                    $('.btn-resend-cancel').text('Close').show();
                    
                    // Don't refresh the page - let user close manually
                } else {
                    $('#resendMessage').html(
                        '<div class="resend-error-message">' + 
                        (response.data || 'Failed to resend gift card. Please try again.') + 
                        '</div>'
                    );
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                let errorMessage = 'An error occurred. Please try again.';
                
                // Try to parse response if it's JSON
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    // If not JSON, use default message
                    if (xhr.responseText) {
                        errorMessage = 'Server error: ' + xhr.responseText.substring(0, 100);
                    }
                }
                
                $('#resendMessage').html(
                    '<div class="resend-error-message">' + errorMessage + '</div>'
                );
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    
    // Open modal when Resend button is clicked
    $('.btn-resend').on('click', function() {
        currentGiftCardIds = $(this).data('gift-card-ids');
        currentRecipientEmail = $(this).data('recipient-email');
        const recipientName = $(this).data('recipient-name') || '';
        
        $('#resendRecipientEmail').html(
            '<strong>' + (recipientName ? recipientName + '<br>' : '') + '</strong>' + 
            currentRecipientEmail
        );
        $('#resendMessage').empty();
        $('#resendModal').addClass('active');
    });
    
    // Open modal when Resend All button is clicked
    $('.btn-resend-all').on('click', function() {
        currentGiftCardIds = $(this).data('all-gift-card-ids');
        currentRecipientEmail = $(this).data('recipient-email');
        
        $('#resendRecipientEmail').html(
            '<strong>All Gift Cards</strong><br>' + 
            currentRecipientEmail
        );
        $('#resendMessage').html(
            '<p style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px;">This will resend all gift cards from this order to the recipient.</p>'
        );
        $('#resendModal').addClass('active');
    });
    
    // Close modal
    $('.resend-modal-close, .btn-resend-cancel').on('click', function() {
        $('#resendModal').removeClass('active');
        $('#resendMessage').empty();
        // Reset button states
        $('#confirmResendBtn').prop('disabled', false).text('Resend').show();
        $('.btn-resend-cancel').text('Cancel');
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('resend-modal')) {
            $('#resendModal').removeClass('active');
            $('#resendMessage').empty();
            // Reset button states
            $('#confirmResendBtn').prop('disabled', false).text('Resend').show();
            $('.btn-resend-cancel').text('Cancel');
        }
    });
    
    // Confirm resend
    $('#confirmResendBtn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Sending...');
        $('#resendMessage').empty();
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'resend_gift_card',
                gift_card_ids: currentGiftCardIds,
                recipient_email: currentRecipientEmail,
                nonce: '<?php echo wp_create_nonce('resend_gift_card_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message with checkmark icon
                    $('#resendMessage').html(`
                        <div class="resend-success-message" style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 24px;">
                                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 0C8.96 0 0 8.96 0 20C0 31.04 8.96 40 20 40C31.04 40 40 31.04 40 20C40 8.96 31.04 0 20 0ZM20 36C11.18 36 4 28.82 4 20C4 11.18 11.18 4 20 4C28.82 4 36 11.18 36 20C36 28.82 28.82 36 20 36ZM27.76 12.58L16 24.34L12.24 20.58C11.46 19.8 10.2 19.8 9.42 20.58C8.64 21.36 8.64 22.62 9.42 23.4L14.6 28.58C15.38 29.36 16.64 29.36 17.42 28.58L30.6 15.4C31.38 14.62 31.38 13.36 30.6 12.58C29.82 11.8 28.54 11.8 27.76 12.58Z"
                                        fill="#037847"/>
                                </svg>
                            </span>
                            <div>
                                <p>Gift card sent successfully!</p>
                                ${response?.data?.message || 'The gift card has been resent to the recipient.'}
                            </div>
                        </div>
                    `);
                    // Hide the confirm button and show close button
                    $btn.hide();
                    $('.btn-resend-cancel').text('Close').show();
                    
                    // Don't refresh the page - let user close manually
                } else {
                    $('#resendMessage').html(
                        '<div class="resend-error-message">' + 
                        (response.data || 'Failed to resend gift card. Please try again.') + 
                        '</div>'
                    );
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                let errorMessage = 'An error occurred. Please try again.';
                
                // Try to parse response if it's JSON
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    // If not JSON, use default message
                    if (xhr.responseText) {
                        errorMessage = 'Server error: ' + xhr.responseText.substring(0, 100);
                    }
                }
                
                $('#resendMessage').html(
                    '<div class="resend-error-message">' + errorMessage + '</div>'
                );
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
});

</script>