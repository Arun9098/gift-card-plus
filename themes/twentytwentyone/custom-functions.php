<?php
add_filter('show_admin_bar', '__return_false');

/**
 * Add global transaction limit field to WooCommerce advanced settings
 */

add_filter('woocommerce_general_settings', 'gc_add_global_transaction_limit_setting');

function gc_add_global_transaction_limit_setting($settings) {
    $new_settings = array();
    
    foreach ($settings as $setting) {
        $new_settings[] = $setting;
        
        // Add global limit setting after currency options
        if (isset($setting['id']) && $setting['id'] === 'woocommerce_currency_pos') {
            $new_settings[] = array(
                'title'    => __('Global Transaction Limit', 'woocommerce'),
                'desc'     => __('Set a global daily transaction limit for all users. If set, this takes priority over individual user limits.', 'woocommerce'),
                'id'       => 'gc_global_transaction_limit',
                'css'      => 'min-width:150px;',
                'default'  => '',
                'type'     => 'number',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                )
            );
        }
    }
    
    return $new_settings;
}

/**
 * Get global transaction limit
 * @return float Global limit amount or 0 if not set
 */

function gc_get_global_transaction_limit() {
    $global_limit = get_option('gc_global_transaction_limit', 0);
    return floatval($global_limit);
}

/**
 * Calculate total amount spent across all users today (daily cumulative)
 * @return float Total amount spent today across all users
 */

function gc_get_site_daily_spent() {
    global $wpdb;
    $today     = wp_date('Y-m-d');
    $cache_key = 'gc_site_daily_spent_' . $today;
    $cached    = wp_cache_get($cache_key, 'gc_limits');
    if ($cached !== false) return (float) $cached;

    $total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(total_amount), 0)
         FROM {$wpdb->prefix}wc_orders
         WHERE status IN ('wc-completed','wc-processing')
           AND DATE(date_created_gmt) = %s",
        $today
    ));

    wp_cache_set($cache_key, $total, 'gc_limits', 300);
    return $total;
}

function gc_get_user_daily_spent($user_id) {
    if (!$user_id) return 0;
    global $wpdb;
    $today     = wp_date('Y-m-d');
    $cache_key = 'gc_daily_spent_' . $user_id . '_' . $today;
    $cached    = wp_cache_get($cache_key, 'gc_limits');
    if ($cached !== false) return (float) $cached;

    $total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(total_amount), 0)
         FROM {$wpdb->prefix}wc_orders
         WHERE customer_id = %d
           AND status IN ('wc-completed','wc-processing')
           AND DATE(date_created_gmt) = %s",
        $user_id, $today
    ));

    wp_cache_set($cache_key, $total, 'gc_limits', 300);
    return $total;
}

function gc_get_guest_email_daily_spent( $email ) {
    if ( ! $email ) return 0;
    global $wpdb;
    $today     = wp_date('Y-m-d');
    $cache_key = 'gc_guest_spent_' . md5($email) . '_' . $today;
    $cached    = wp_cache_get($cache_key, 'gc_limits');
    if ($cached !== false) return (float) $cached;

    $total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(total_amount), 0)
         FROM {$wpdb->prefix}wc_orders
         WHERE billing_email = %s
           AND status IN ('wc-completed','wc-processing')
           AND DATE(date_created_gmt) = %s",
        sanitize_email($email), $today
    ));

    wp_cache_set($cache_key, $total, 'gc_limits', 300);
    return $total;
}

/**
 * Count total number of orders placed today across all users
 * @return int Total number of orders today
 */

function gc_get_user_daily_order_count($user_id) {
    if (!$user_id) return 0;
    global $wpdb;
    $today = wp_date('Y-m-d');

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$wpdb->prefix}wc_orders
         WHERE customer_id = %d
           AND status IN ('wc-completed','wc-processing')
           AND DATE(date_created_gmt) = %s",
        $user_id, $today
    ));
}

/**
 * Get daily transaction limits (hardcoded as per requirements)
 * @return array Daily limits configuration
 */

function gc_get_daily_limits() {
    return array(
        'user_daily_limit' => 9999.99,               // $9,999.99 per day per user
        'site_daily_order_limit' => 1000,             // 1,000 orders per day
        'max_individual_order' => 9999.99,            // $9,999.99 per individual order
    );
}

/**
 * Comprehensive transaction limit validation before checkout
 * Blocks order placement if any limit is exceeded
 */

add_action('woocommerce_checkout_process', 'gc_validate_comprehensive_transaction_limits', 8);

function gc_validate_comprehensive_transaction_limits() {

    $current_cart_total = WC()->cart->get_total('edit');
    // $user_id = $user_id ? get_current_user_id() : $_POST['contact_email'];

    $user_id = get_current_user_id();

    if (!$user_id && !empty($_POST['contact_email'])) {
        $email = sanitize_email($_POST['contact_email']);

        $user = get_user_by('email', $email);

        if ($user) {
            $user_id = $user->ID;
        }
    }

    // GLOBAL LIMIT CHECK (MAIN FIX)
    $global_limit = gc_get_global_transaction_limit();


    $user_limit_exceed = false;

    if ($global_limit > 0) {

        // $user_id = get_current_user_id();

        if ($user_id) {
            // USER-BASED LIMIT
            $user_daily_spent = gc_get_user_daily_spent($user_id);
            $new_total = $user_daily_spent + $current_cart_total;

        } else {
            // GUEST → fallback to site-wide
            $site_daily_spent = gc_get_site_daily_spent();
            $new_total = $site_daily_spent + $current_cart_total;
        }

        // if ($new_total > $global_limit) {
        //     if(current_user_can('manage_options')){
        //         wc_add_notice(
        //             sprintf(
        //                 __('The website transaction limits of %s have been reached please contact IT support', 'woocommerce'),
        //                 wc_price($global_limit)
        //             ),
        //             'error'
        //         );
        //         return;
        //     }
        // }

        $daily_order_count = gc_get_user_daily_order_count($user_id);
        $limits = gc_get_daily_limits();


        // $user_id = get_current_user_id();

        if ($user_id) {

            // Logged-in user

            if ($daily_order_count >= $limits['site_daily_order_limit']) {
                wc_add_notice(
                    sprintf(
                        __('Daily order limit reached. There have been %1$s orders today, but the limit is %2$s.', 'woocommerce'),
                        $daily_order_count,
                        $limits['site_daily_order_limit']
                    ),
                    'error'
                );
                return;
            }
            $user_daily_spent = gc_get_user_daily_spent($user_id);
            $new_total = $user_daily_spent + $current_cart_total;



            if ($new_total > $global_limit) {
                gc_send_limit_exceeded_attempt_email($user_id, $user_daily_spent, $global_limit, $current_cart_total);

                wc_add_notice(
                    sprintf(
                        __('The website transaction limits of %s have been reached please contact IT support', 'woocommerce'),
                        wc_price($user_daily_spent),
                        wc_price($global_limit)
                    ),
                    'error'
                );
                return;
            }

        } else {

            $entered_email = !empty($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';

            $guest_user = get_user_by('email', $entered_email);
            $guest_user_id = $guest_user ? $guest_user->ID : 0;

            // If user exists → use user spent
            if ($guest_user_id) {
                $guest_spent = gc_get_user_daily_spent($guest_user_id);
            } else {
                // Unknown email → per-email daily total (VULN-013)
                $guest_spent = gc_get_guest_email_daily_spent( $entered_email );
            }

            $new_total = $guest_spent + $current_cart_total;

            if ($new_total > $global_limit) {

                // Pass email ALSO (IMPORTANT CHANGE)
                gc_send_limit_exceeded_attempt_email(
                    $guest_user_id,
                    $guest_spent,
                    $global_limit,
                    $current_cart_total,
                    $entered_email
                );

                wc_add_notice(
                    __('Apologies, we are experiencing a technical error please contact customer support or try again later', 'woocommerce'),
                    'error'
                );
                return;
            }
        }
        
        
    }
    
    // 4. Validate site-wide daily total limit
    $site_daily_spent = gc_get_site_daily_spent();
    $new_site_daily_total = $site_daily_spent + $current_cart_total;
    
    
    // Check for threshold notifications (80% and 90%)
    $user_id = get_current_user_id();
    $user_spent = gc_get_user_daily_spent($user_id);

    gc_check_and_send_threshold_notifications($user_spent, $current_cart_total, $limits, $user_id);
}

function gc_send_limit_exceeded_attempt_email($user_id, $current_total, $limit, $attempt_amount, $email = '') {

    $admin_emails = array_filter( array_map( 'sanitize_email', explode( ',', get_option( 'gcp_alert_emails', get_option( 'admin_email', '' ) ) ) ) );

    $user_info  = '';
    $user_email = '';

    if ($user_id) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            $user_email = $user->user_email;
            $user_info  = "User ID: {$user_id}\nUser Email: {$user_email}\n\n";
        }
    } elseif ($email) {
        $user_email = $email;
        $user_info  = "Guest Email: {$email}\n\n";
    } else {
        $user_info = "Guest User (No Email Provided)\n\n";
    }

    $subject = 'User Attempted Order After Reaching Daily Limit';

    $message = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            
            <h2 style="color: #d9534f;">Daily Limit Exceeded Attempt</h2>
            
            <p>A user attempted to place an order after reaching the daily transaction limit.</p>
            
            <table style="border-collapse: collapse; width: 100%; max-width: 500px;">
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>User ID</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . ($user_id ?: 'Guest') . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>User Email</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $user_email ?: $email ?: 'N/A' ) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Attempted Amount</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . wc_price($attempt_amount) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Current Spending</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . wc_price($current_total) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Daily Limit</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . wc_price($limit) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Date</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . wp_date('Y-m-d H:i:s') . '</td>
                </tr>
            </table>

            <p style="margin-top: 15px;">This is an automated notification.</p>
        </div>';

    wp_mail($admin_emails, $subject, $message);
}

/**
 * Check threshold levels and send email notifications
 * @param float $current_spent Current amount spent today
 * @param float $pending_amount Amount about to be spent
 * @param array $limits Daily limits configuration
 */

function gc_check_and_send_threshold_notifications($current_spent, $pending_amount, $limits, $user_id = 0) {
    if (!$user_id) return;

    $global_limit = gc_get_global_transaction_limit();
    if ($global_limit <= 0) return;

    $new_total = $current_spent + $pending_amount;

    // Only trigger when USER reaches limit
    if ($new_total >= $global_limit) {

        // Prevent duplicate email per user per day
        $today = wp_date('Y-m-d');
        $transient_key = 'user_limit_' . $user_id . '_' . $today;

        if (!get_transient($transient_key)) {

            // Pass 1 as threshold (100%)
            gc_send_threshold_notification(1, $new_total, $global_limit, $user_id);

            set_transient($transient_key, true, DAY_IN_SECONDS);
        }
    }
}

/**
 * Send threshold notification email
 * @param float $threshold Threshold percentage (0.8 or 0.9)
 * @param float $current_total Current total spending
 * @param float $daily_limit Daily spending limit
 */

function gc_send_threshold_notification($threshold, $current_total, $daily_limit, $user_id = 0) {

    $threshold_percent = ($threshold * 100) . '%';
    $admin_email = get_option('admin_email');

    $user_info = '';
    $user_email = '';

    if ($user_id) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            $user_email = $user->user_email;
            $user_info = "User ID: {$user_id}\nUser Email: {$user_email}\n\n";
        }
    }

    // Detect if FULL LIMIT reached
    $is_limit_reached = ($current_total >= $daily_limit);

    if ($is_limit_reached) {
        $subject = 'Daily Transaction Limit Reached by User';
    } else {
        $subject = sprintf('Transaction Limit Threshold Alert: %s Reached', $threshold_percent);
    }

    $message = sprintf(
        "%s" .
        "Transaction alert:\n\n" .
        "Threshold: %s of daily limit\n" .
        "Current spending: %s\n" .
        "Daily limit: %s\n" .
        "Remaining: %s\n\n" .
        "Date: %s\n\n" .
        "This is an automated notification.",
        $user_info,
        $threshold_percent,
        wc_price($current_total),
        wc_price($daily_limit),
        wc_price($daily_limit - $current_total),
        wp_date('Y-m-d H:i:s')
    );

    wp_mail($admin_email, $subject, $message);
}

/**
 * AJAX handler to validate transaction limit before adding to cart
 */

add_action('wp_ajax_gc_validate_transaction_limit', 'gc_ajax_validate_transaction_limit');

function gc_ajax_validate_transaction_limit() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Authentication required.' ] );
    }
    check_ajax_referer( 'gc_validate_nonce', 'nonce' );

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    
    if (!$product_id || !$quantity || !$price) {
        wp_send_json_error(array('message' => 'Invalid parameters'));
        wp_die();
    }
    
    $item_total = $price * $quantity;
    $user_id = get_current_user_id();
    
   
    
    // Get user-specific limits
    $limits = gc_get_daily_limits();
    
    // 1. Validate maximum individual order limit
    if ($item_total > $limits['max_individual_order']) {
        wp_send_json_error(array(
            'message' => sprintf(
                __('Maximum individual order limit exceeded. Your item total is %1$s, but the maximum allowed per order is %2$s.', 'woocommerce'),
                wc_price($item_total),
                wc_price($limits['max_individual_order'])
            ),
            'limit_type' => 'individual_order'
        ));
    }
    
    // 2. Validate site-wide daily order count limit
    $user_id = get_current_user_id();

    $daily_order_count = gc_get_user_daily_order_count($user_id);
    if ($daily_order_count >= $limits['site_daily_order_limit']) {
        wp_send_json_error(array(
            'message' => sprintf(
                __('Daily order limit reached. The site has processed %1$d orders today, but the daily limit is %2$d.', 'woocommerce'),
                $daily_order_count,
                $limits['site_daily_order_limit']
            ),
            'limit_type' => 'daily_order_count'
        ));
    }
    
    // 3. Validate site-wide daily total limit
    $site_daily_spent = gc_get_site_daily_spent();
    $new_site_daily_total = $site_daily_spent + $item_total;
     
    // All validations passed
    wp_send_json_success(array(
        'valid' => true,
        'site_daily_spent' => $site_daily_spent,
        'site_daily_limit' => $limits['site_daily_total_limit'],
        'new_site_total' => $new_site_daily_total
    ));
    
    wp_die();
}

/**
 * Transaction monitoring for anomaly detection
 * Monitors for abnormal patterns that might indicate hacking
 */

add_action('woocommerce_payment_complete', 'gc_monitor_transaction_anomalies', 10);
add_action('woocommerce_order_status_completed', 'gc_monitor_transaction_anomalies', 10);

function gc_monitor_transaction_anomalies($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $order_total = $order->get_total();
    $customer_id = $order->get_customer_id();
    $today = wp_date('Y-m-d');

    // Check for abnormal patterns

    // 1. Check for unusually high number of small transactions from same user
    if ($customer_id) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total
             FROM {$wpdb->prefix}wc_orders
             WHERE customer_id = %d
               AND status IN ('wc-completed','wc-processing')
               AND DATE(date_created_gmt) = %s
               AND total_amount <= 50",
            $customer_id,
            $today
        ) );
        $small_transactions  = (int) $row->cnt;
        $total_small_amount  = (float) $row->total;

        // Alert if more than 20 small transactions in a day
        if ($small_transactions > 20) {
            gc_send_anomaly_alert('high_frequency_small_transactions', array(
                'user_id'               => $customer_id,
                'small_transaction_count' => $small_transactions,
                'total_small_amount'    => $total_small_amount,
                'order_id'              => $order_id,
            ));
        }
    }

    // 2. Check for sudden spike in overall transaction volume
    global $wpdb;
    $today_order_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders
         WHERE status IN ('wc-completed','wc-processing')
           AND DATE(date_created_gmt) = %s",
        $today
    ) );
    $yesterday_order_count = gc_get_yesterday_order_count();
    
    // Alert if today's order count is 3x yesterday's count (and today has more than 100 orders)
    if ($today_order_count > 100 && $today_order_count > ($yesterday_order_count * 3)) {
        gc_send_anomaly_alert('order_volume_spike', array(
            'today_orders' => $today_order_count,
            'yesterday_orders' => $yesterday_order_count,
            'spike_ratio' => round($today_order_count / max($yesterday_order_count, 1), 2)
        ));
    }
    
    // 3. Check for unusual order amounts (round numbers that might indicate testing)
    if ($order_total == 1000 || $order_total == 5000 || $order_total == 9999.99) {
        gc_send_anomaly_alert('suspicious_order_amount', array(
            'order_id' => $order_id,
            'order_total' => $order_total,
            'customer_id' => $customer_id
        ));
    }
}

/**
 * Get yesterday's order count for comparison
 */

function gc_get_yesterday_order_count() {
    global $wpdb;
    $yesterday = wp_date('Y-m-d', strtotime('-1 day'));
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders
         WHERE status IN ('wc-completed','wc-processing')
           AND DATE(date_created_gmt) = %s",
        $yesterday
    ) );
}

/**
 * Send anomaly alert email
 */

function gc_send_anomaly_alert($anomaly_type, $data) {
    $admin_email = get_option('admin_email');
    
    switch ($anomaly_type) {
        case 'high_frequency_small_transactions':
            $subject = 'Security Alert: High Frequency Small Transactions Detected';
            $message = sprintf(
                "Security anomaly detected:\n\n" .
                "Type: High frequency small transactions\n" .
                "User ID: %d\n" .
                "Small transaction count: %d\n" .
                "Total small amount: %s\n" .
                "Latest order: %d\n\n" .
                "This pattern may indicate automated testing or fraudulent activity.\n\n" .
                "Date: %s",
                $data['user_id'],
                $data['small_transaction_count'],
                wc_price($data['total_small_amount']),
                $data['order_id'],
                wp_date('Y-m-d H:i:s')
            );
            break;
            
        case 'order_volume_spike':
            $subject = 'Security Alert: Unusual Order Volume Spike';
            $message = sprintf(
                "Security anomaly detected:\n\n" .
                "Type: Order volume spike\n" .
                "Today's orders: %d\n" .
                "Yesterday's orders: %d\n" .
                "Spike ratio: %dx\n\n" .
                "This sudden increase may indicate automated activity.\n\n" .
                "Date: %s",
                $data['today_orders'],
                $data['yesterday_orders'],
                $data['spike_ratio'],
                wp_date('Y-m-d H:i:s')
            );
            break;
            
        case 'suspicious_order_amount':
            $subject = 'Security Alert: Suspicious Order Amount';
            $message = sprintf(
                "Security anomaly detected:\n\n" .
                "Type: Suspicious round number amount\n" .
                "Order ID: %d\n" .
                "Order total: %s\n" .
                "Customer ID: %s\n\n" .
                "Round number amounts may indicate testing activity.\n\n" .
                "Date: %s",
                $data['order_id'],
                wc_price($data['order_total']),
                $data['customer_id'] ?: 'Guest',
                wp_date('Y-m-d H:i:s')
            );
            break;
            
        default:
            return;
    }
    
    wp_mail($admin_email, $subject, $message);
}


/**
 * Hide out-of-stock, no-price, and Parent products from ALL product listings (one hook, applies everywhere).
 * - Out-of-stock products are excluded.
 * - Products with no _price (empty or not set) are excluded.
 * - Products with sku_type = Parent are excluded (Parent products are not shown in shop/search/shortcodes).
 * - WooCommerce shortcodes ([products], [recent_products], etc.), custom shortcodes ([gc_products], etc.), load more AJAX, widgets.
 */

add_action('pre_get_posts', 'gc_hide_out_of_stock_from_all_product_listings', 9999);

function gc_hide_out_of_stock_from_all_product_listings($q)
{
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }
    $post_type = $q->get('post_type');
    
    $is_product = (
        is_shop()
        || is_product_category()
        || is_product_tag()
        || is_search()
        || $post_type === 'product'
        || (is_array($post_type) && in_array('product', $post_type, true))
    );

    if (!$is_product) {
        return;
    }

    $meta_query = $q->get('meta_query');
    if (!is_array($meta_query)) {
        $meta_query = array();
    }

    $stock_clause = array(
        'key'     => '_stock_status',
        'value'   => 'outofstock',
        'compare' => '!=',
    );

    $price_clauses = array(
        array('key' => '_price', 'compare' => 'EXISTS'),
        array('key' => '_price', 'value' => '', 'compare' => '!='),
    );

    $has_relation = isset($meta_query['relation']);
    $has_stock = false;
    $has_price = false;

    // $apply_stock_filter = !current_user_can('administrator');
    $apply_stock_filter = !current_user_can('manage_woocommerce');


    foreach ($meta_query as $clause) {
        if (is_array($clause) && isset($clause['key'])) {
            if ($clause['key'] === '_stock_status') {
                $has_stock = true;
            }
            if ($clause['key'] === '_price') {
                $has_price = true;
            }
        }
    }

    $added = false;

    if ($apply_stock_filter && !$has_stock) {
        if (!$has_relation) {
            $meta_query['relation'] = 'AND';
        }
        $meta_query[] = $stock_clause;
        $added = true;
    }

    if (!$has_price) {
        if (!$has_relation && !$added) {
            $meta_query['relation'] = 'AND';
        }
        $meta_query[] = $price_clauses[0];
        $meta_query[] = $price_clauses[1];
        $added = true;
    }

    if ($added) {
        $q->set('meta_query', $meta_query);
    }

    // keep your SQL validation (parent must have child)
    if (!has_filter('posts_where', 'gc_validate_parent_has_child_sql')) {
        add_filter('posts_where', 'gc_validate_parent_has_child_sql', 10, 2);
    }

}
function gc_validate_parent_has_child_sql($where, $query)
{
    global $wpdb;

    if (is_admin() && !wp_doing_ajax()) return $where;
    if (!$query->is_main_query()) return $where;

    $where .= " AND (
        -- NOT a parent
        NOT EXISTS (
            SELECT 1 
            FROM {$wpdb->postmeta} pm1
            WHERE pm1.post_id = {$wpdb->posts}.ID
            AND pm1.meta_key = 'sku_type'
            AND pm1.meta_value = 'Parent'
        )

        OR EXISTS (
            SELECT 1
            FROM {$wpdb->postmeta} parent_sku_pm
            WHERE parent_sku_pm.post_id = {$wpdb->posts}.ID
            AND parent_sku_pm.meta_key = '_sku'
            AND EXISTS (
                SELECT 1
                FROM {$wpdb->posts} child
                INNER JOIN {$wpdb->postmeta} child_parent_pm 
                    ON child_parent_pm.post_id = child.ID
                INNER JOIN {$wpdb->postmeta} child_type_pm 
                    ON child_type_pm.post_id = child.ID
                WHERE child_parent_pm.meta_key = 'parent_sku'
                AND child_parent_pm.meta_value = parent_sku_pm.meta_value
                AND child_type_pm.meta_key = 'sku_type'
                AND child_type_pm.meta_value = 'child'
                AND child.post_type = 'product'
                AND child.post_status = 'publish'
            )
        )
    )";

    return $where;
}

/**
 * Hide Parent and no-price products from catalog visibility.
 * - Parent products (sku_type = Parent) are not visible in the catalog.
 * - Products with no price are not visible.
 */

add_filter('woocommerce_product_is_visible', 'gc_product_visibility_by_price_and_parent', 10, 2);
function gc_product_visibility_by_price_and_parent($visible, $product_id)
{
    $product = wc_get_product($product_id);
    if (!$product || !is_a($product, 'WC_Product')) {
        return $visible;
    }
    $price = $product->get_price('');
    $has_price = ($price !== '' && $price !== null && is_numeric($price));
    if (!$has_price) {
        return false;
    }
    $sku_type = $product->get_meta('sku_type', true);
    if (strtolower((string) $sku_type) === 'parent') {
        return false;
    }
    return $visible;
}

/**
 * Return HTML for header cart icon (link + count). Used in header and cart fragments.
 */

function gc_header_cart_link_html() {
    $count = 0;
    $url = '#';
    if (function_exists('WC') && WC()->cart && is_object(WC()->cart)) {
        $count = WC()->cart->get_cart_contents_count();
        $url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : $url;
    }
    $cart_img = get_template_directory_uri() . '/assets/images/cart_header.png';
    ob_start();
    ?>
    <a href="<?php echo esc_url($url); ?>" class="icon-btn header-cart-link" aria-label="<?php echo esc_attr(sprintf(__('Cart (%d items)', 'twentytwentyone'), $count)); ?>">
        <span class="header-cart-icon-wrap">
            <img src="<?php echo esc_url($cart_img); ?>" alt="Cart" class="profile-pic">
            <?php if ($count > 0) : ?>
                <span class="header-cart-count"><?php echo esc_html($count); ?></span>
            <?php endif; ?>
        </span>
    </a>
    <?php
    return ob_get_clean();
}

/**
 * Add header cart fragment so count updates via AJAX when cart changes.
 */

add_filter('woocommerce_add_to_cart_fragments', 'gc_header_cart_fragment');
function gc_header_cart_fragment($fragments) {
	$fragments['.header-cart-fragment'] = '<div class="header-cart-fragment">' . gc_header_cart_link_html() . '</div>';
	return $fragments;
}

/**
 * Restrict single product page: Parent and no-price products are not accessible.
 * Redirects to shop page when user tries to view such a product directly.
 */

add_action('template_redirect', 'gc_restrict_single_product_parent_and_no_price', 5);
function gc_restrict_single_product_parent_and_no_price()
{
    if (!is_singular('product')) {
        return;
    }
    $product = wc_get_product(get_queried_object_id());
    if (!$product || !is_a($product, 'WC_Product')) {
        return;
    }
    
    // $sku_type = $product->get_meta('sku_type', true);
    // // Allow admin to view everything
    // if (!current_user_can('manage_options')){
    //     if (strtolower((string) $sku_type) === 'parent') {
    //             wp_safe_redirect(wc_get_page_permalink('shop'), 302);
    //     }   
    // }

   
    $price = $product->get_price('');
    $has_price = ($price !== '' && $price !== null && is_numeric($price));
    if (!$has_price) {
        wp_safe_redirect(wc_get_page_permalink('shop'), 302);
        exit;
    }
}

/**
 * Exclude out-of-stock, Parent, and no-price products from main search results (header search form).
 * When the search query returns multiple post types, only product IDs that are out of stock,
 * sku_type = Parent, or have no price are excluded; other post types are unchanged.
 */

// add_action('pre_get_posts', 'gc_exclude_out_of_stock_from_search', 9998);

// function gc_exclude_out_of_stock_from_search($q)
// {
//     if (is_admin() && !wp_doing_ajax()) {
//         return;
//     }
//     if (!$q->is_search() || !$q->is_main_query()) {
//         return;
//     }
//     global $wpdb;
//     $exclude_subquery = "
//         ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_stock_status' AND meta_value = 'outofstock' )
//         UNION
//         ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'sku_type' AND meta_value = 'Parent' )
//         UNION
//         ( SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = 'product' AND p.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_price' ) )
//         UNION
//         ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_price' AND ( meta_value IS NULL OR meta_value = '' ) )
//     ";
//     $where = " AND ( {$wpdb->posts}.post_type != 'product' OR {$wpdb->posts}.ID NOT IN ( {$exclude_subquery} ) ) ";
//     $callback = null;
//     $callback = function ($sql) use ($where, &$callback) {
//         if ($callback) {
//             remove_filter('posts_where', $callback, 10);
//         }
//         return $sql . $where;
//     };
//     add_filter('posts_where', $callback, 10, 1);
// }

/**
 * Create and maintain the eGift Card search logs table.
 * Logs all searches (submitted + suggestion requests) for reporting.
 */


const GC_SEARCH_LOGS_DB_VERSION = 2;
add_action('init', 'gc_maybe_create_search_logs_table', 1);
function gc_maybe_create_search_logs_table() {
    $current = (int) get_option('gc_search_logs_db_version', 0);
    if ($current >= GC_SEARCH_LOGS_DB_VERSION) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'gc_search_logs';
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    if ($current === 1) {
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) ) {
            return;
        }
        if ($wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->esc_like($table) . "'") === $table
            && $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'page_name'") !== 'page_name') {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN page_name varchar(100) NOT NULL DEFAULT '' AFTER context");
        }
        update_option('gc_search_logs_db_version', GC_SEARCH_LOGS_DB_VERSION);
        return;
    }
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        search_term varchar(500) NOT NULL DEFAULT '',
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        ip varchar(45) NOT NULL DEFAULT '',
        context varchar(50) NOT NULL DEFAULT 'search',
        page_name varchar(100) NOT NULL DEFAULT '',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY search_term (search_term(100)),
        KEY created_at (created_at),
        KEY context (context),
        KEY page_name (page_name)
    ) {$charset};";
    dbDelta($sql);
    update_option('gc_search_logs_db_version', GC_SEARCH_LOGS_DB_VERSION);
}

function gc_log_search($search_term, $context = 'search', $page_name = '') {
    $search_term = trim($search_term);
    if ($search_term === '') {
        return;
    }
    if (defined('GC_SEARCH_LOGS_DISABLED') && GC_SEARCH_LOGS_DISABLED) {
        return;
    }
    if (!apply_filters('gc_log_search_enabled', get_option('gc_search_logs_enabled', 0))) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'gc_search_logs';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return;
    }
    $user_id = get_current_user_id();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $context = in_array($context, array('search', 'suggestion'), true) ? $context : 'search';
    $page_name = substr(sanitize_text_field($page_name), 0, 100);
    $cols = array(
        'search_term' => substr($search_term, 0, 500),
        'user_id'     => $user_id,
        'ip'          => substr($ip, 0, 45),
        'context'     => $context,
    );
    $formats = array('%s', '%d', '%s', '%s');
    if ($wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'page_name'") === 'page_name') {
        $cols['page_name'] = $page_name;
        $formats[] = '%s';
    }
    $wpdb->insert($table, $cols, $formats);
}

add_action('wp_ajax_create_giftcard_products', 'handle_create_giftcard_products');

function handle_create_giftcard_products()
{
    gcp_require_admin_ajax();

    check_ajax_referer('create_giftcard_products_nonce', 'security');

    $products = isset($_POST['products']) ? json_decode(wp_unslash($_POST['products']), true) : [];
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] == 1;


    if (empty($products)) {
        wp_send_json_error('No products data received.');
    }

    // Field key => [meta_key, type]
    $field_meta_map = [
        'SKU' => ['_sku', 'text'],
        'Price' => ['_price', 'number'],
        'Gift Card Title' => ['post_title', 'text'],
        'Parent/Child SKU' => ['parent_sku', 'text'],
        'Long Description' => ['post_content', 'html'],
        // 'Short Description' => ['post_excerpt', 'text'],
        'Linked to Parent' => ['_linked_to_parent', 'text'],
        'Parent SKU' => ['parent_sku', 'text'],
        'Supplier SKU' => ['_supplier_sku', 'text'],
        'Brand' => ['product_brand', 'taxonomy'],
        'Supplier' => ['supplier', 'text'],
        'Terms & Conditions' => ['terms_conditions', 'html'],
        'How to Use' => ['how_to_use', 'html'],
        'Expiry Date/Time' => ['_expire_date', 'date'],
        'Gift Card Expiry Type' => ['gift_card_expiry_type', 'text'],
        'Gift Card Expiry Date' => ['gift_card_expiry_date', 'date'],
        'Gift Card Expiry Period' => ['gift_card_expiry_duration', 'number'],
        'Gift Card Activation Type' => ['activation_expiry_type', 'text'],
        'Gift Card Activation Date' => ['activation_expiry_date', 'date'],
        'Gift Card Activation Period' => ['activation_expiry_duration', 'number'],
        'Period Type' => ['activation_expiry_unit', 'text'],
        // 'Brand Image' => ['_brand_image', 'image'],
        'Denomination Type' => ['denomination_type', 'text'],
        'Cost Price' => ['_cost_price', 'number'],
        'Supplier Fullfillment Price' => ['_supplier_fullfillment_price', 'number'],
        'GST' => ['_gst', 'number'],
        'GC + Fullfillment' => ['_gc_fullfillment', 'number'],
        'Preset Delivery Class' => ['_delivery_class', 'text'],
        'Delivery Cost' => ['_delivery_cost', 'number'],
        'Discounted Price' => ['discounted_price', 'number'],
        'Discounted' => ['discounted', 'checkbox'],
        'Discounted Valid From' => ['_discount_valid_from', 'date'],
        'Discounted Valid To' => ['_discount_valid_to', 'date'],
        'Icons' => ['icons', 'taxonomy'],
        'Tags' => ['product_tag', 'taxonomy'],
        'Categories' => ['product_cat', 'taxonomy'],
        'Feature Placement' => ['display_on', 'text'],
        'Extra Header' => ['_extra_header', 'text'],
        'Add Stock Levels' => ['_manage_stock', 'checkbox'],
        'Stock Levels' => ['_stock', 'number'],
        'Transaction Limit' => ['transaction_limit', 'checkbox'],
        'QTY per Transaction' => ['_quantity_per_transaction', 'number'],
        'Total Value' => ['_total_value_per_transaction', 'number'],
        'Available for all users' => ['available_for_all_users', 'checkbox'],
        'Always On' => ['always_on', 'checkbox'],
        'Onsite From' => ['_onsite_from', 'date'],
        'Onsite To' => ['_onsite_to', 'date'],
    ];
    $img_arrr = [
        'Card Image 1' => ['_card_image_1', 'url'],
        'Card Image 2' => ['_card_image_2', 'url'],
        'Card Image 3' => ['_card_image_3', 'url'],
        'Card Image 4' => ['_card_image_4', 'url'],
        'Card Image 5' => ['_card_image_5', 'url'],
    ];



    $skipped_products = [];

    foreach ($products as $product_data) {
        $sku = $product_data['SKU'] ?? '';
        $post_id = null;

        if ($edit_mode && $sku) {
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare("
                SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = '_sku'
                AND pm.meta_value = %s
                AND p.post_type = 'product'
                AND p.post_status != 'trash'
                LIMIT 1
            ", $sku));

        }



        $post_data = [
            'post_type' => 'product',
            'post_status' => 'publish',
        ];

        if (!empty($product_data['Gift Card Title'])) {
            $post_data['post_title'] = sanitize_text_field($product_data['Gift Card Title']);
        }

        if (!empty($product_data['Long Description'])) {
            $post_data['post_content'] = wp_kses_post($product_data['Long Description']);
        }
        if (!empty($product_data['Short Description'])) {
            $post_data['post_excerpt'] = sanitize_text_field($product_data['Short Description']);
        }
        if ($post_id) {
            if ($edit_mode) {
                $post_data['ID'] = $post_id;
                wp_update_post($post_data);
            } else {
                continue;
            }
        } else {
            if (!$edit_mode) {
                global $wpdb;
                $existing_id = $wpdb->get_var($wpdb->prepare("
                    SELECT p.ID FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE pm.meta_key = '_sku'
                    AND pm.meta_value = %s
                    AND p.post_type = 'product'
                    AND p.post_status != 'trash'
                    LIMIT 1
                ", $sku));

                if ($existing_id) {
                    $skipped_products[] = $sku;
                    continue;
                }

                $post_id = wp_insert_post($post_data);
                if (is_wp_error($post_id))
                    continue;
                wp_set_object_terms($post_id, 'simple', 'product_type');
            } else {
                // Skip this product in edit mode if it doesn't exist
                continue;
            }
        }


        // Save remaining fields
        foreach ($product_data as $field => $value) {
            if (!isset($field_meta_map[$field]))
                continue;

            [$meta_key, $type] = $field_meta_map[$field];

            if (in_array($meta_key, ['post_title', 'post_content']))
                continue;

            // Handle types
            switch ($type) {
                case 'number':
                    $cleaned = is_numeric($value) ? floatval($value) : '';
                    break;
                case 'checkbox':
                    $cleaned = ($value === 'Yes' || $value === '1' || strtolower($value) === 'true') ? 'Yes' : 'No';
                    break;
                case 'date':
                    $timestamp = strtotime($value);
                    if ($timestamp) {
                        $cleaned = wp_date('Y-m-d\TH:i', $timestamp);
                    } else {
                        $cleaned = '';
                    }
                    break;
                case 'image':
                    $cleaned = esc_url_raw($value);
                    break;
                case 'comma_list':
                    $cleaned = array_map('trim', explode(',', $value));
                    update_post_meta($post_id, $meta_key, $cleaned);
                    continue 2;
                case 'taxonomy':
                    $terms = array_map('trim', explode(',', $value));

                    if ($meta_key === 'product_cat') {
                        $cat_term_ids = array();
                        foreach ($terms as $term_name) {
                            $category = $term_name;
                            if (is_numeric($category)) {
                                $cat_term_ids[] = intval($category);
                            } else {
                                // It's a new category name - create it
                                $new_term = wp_insert_term(
                                    sanitize_text_field($category),
                                    'product_cat'
                                );

                                if (!is_wp_error($new_term)) {
                                    $cat_term_ids[] = $new_term['term_id'];
                                } else {
                                    // Handle error (possibly category already exists)
                                    $existing_term = get_term_by('name', $category, 'product_cat');
                                    if ($existing_term) {
                                        $cat_term_ids[] = $existing_term->term_id;
                                    }
                                }
                            }
                        }

                        if (!empty($cat_term_ids)) {
                            wp_set_object_terms($post_id, $cat_term_ids, 'product_cat');
                        }

                        foreach ($cat_term_ids as $tid) {
                            $rows = get_field('sku_assigned_arr', 'product_cat_' . $term_id);
                            if (!is_array($rows)) {
                                $rows = [];
                            }

                            $already_exists = false;
                            foreach ($rows as $row) {
                                if (!empty($row['assigned_product']) && intval($row['assigned_product']) === $post_id) {
                                    $already_exists = true;
                                    break;
                                }
                            }

                            if ($already_exists) {
                                continue; // Skip if product already assigned to category
                            }

                            // Add new row
                            $rows[] = [
                                'assigned_product' => $post_id
                            ];

                            // Update the repeater field in category
                            update_field('sku_assigned_arr', $rows, 'product_cat_' . $tid);
                        }
                    }

                    // Handle featured image for product_brand (Brand)
                    if ($meta_key === 'product_brand' && !empty($product_data['Brand Image'])) {
                        foreach ($terms as $term_name) {
                            $term = get_term_by('name', $term_name, 'product_brand');
                            if ($term) {
                                $image_url = esc_url_raw($product_data['Brand Image']);

                                // Check if already has a thumbnail image
                                $existing_id = get_term_meta($term->term_id, 'thumbnail_id', true);

                                if (!$existing_id) {
                                    $image_id = get_existing_attachment_id($image_url);

                                    if (!$image_id) {
                                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                                        require_once(ABSPATH . 'wp-admin/includes/file.php');
                                        require_once(ABSPATH . 'wp-admin/includes/media.php');

                                        // Upload the image
                                        $media = media_sideload_image($image_url, 0, '', 'src');

                                        // Get attachment ID from URL
                                        $image_id = get_existing_attachment_id($image_url);
                                    }

                                    if ($image_id) {
                                        update_term_meta($term->term_id, 'thumbnail_id', $image_id);
                                    }

                                    wp_set_object_terms($post_id, $term->term_id, $meta_key);
                                }
                            }
                        }
                    }
                    continue 2;
                case 'html':
                    $cleaned = wp_kses_post($value);
                    break;
                case 'text':
                default:
                    $cleaned = sanitize_text_field($value);
                    break;
            }

            update_post_meta($post_id, $meta_key, $cleaned);
            if ($meta_key === '_stock') {
                update_post_meta($post_id, '_manage_stock', 'yes');
                update_post_meta($post_id, '_stock_status', $cleaned > 0 ? 'instock' : 'outofstock');
            }
            if (!empty($product_data['Parent SKU'])) {
                $parent_sku = sanitize_text_field($product_data['Parent SKU']);

                $parent_id = $wpdb->get_var($wpdb->prepare("
                    SELECT p.ID FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE pm.meta_key = '_sku'
                    AND pm.meta_value = %s
                    AND p.post_type = 'product'
                    AND p.post_status != 'trash'
                    LIMIT 1
                ", $parent_sku));

                if ($parent_id) {
                    $post_data['post_parent'] = $parent_id;
                }
            }

            // Handle Shipping Class
            if ($meta_key === '_delivery_class') {
                $shipping_class_name = sanitize_text_field($value);
                if (!empty($shipping_class_name)) {
                    // Check if the shipping class exists
                    $term = get_term_by('name', $shipping_class_name, 'product_shipping_class');
                    if (!$term) {
                        // Create it if it doesn't exist
                        $term = wp_insert_term($shipping_class_name, 'product_shipping_class');
                        if (is_wp_error($term))
                            continue;
                        $term_id = $term['term_id'];
                    } else {
                        $term_id = $term->term_id;
                    }

                    // Assign the shipping class to the product
                    wp_set_object_terms($post_id, intval($term_id), 'product_shipping_class');
                }
                continue;
            }
        }
        $gallery_ids = [];

        foreach ($img_arrr as $field => [$meta_key, $type]) {
            if (!empty($product_data[$field])) {
                $image_url = esc_url_raw($product_data[$field]);

                // Try to get existing attachment ID
                $attachment_id = get_existing_attachment_id($image_url);

                // If not found, upload the image
                if (!$attachment_id) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    media_sideload_image($image_url, $post_id, '', 'src');
                    $attachment_id = get_existing_attachment_id($image_url);
                }

                if ($attachment_id) {
                    update_post_meta($post_id, $meta_key, $image_url);
                    $gallery_ids[] = $attachment_id;
                }
            }
        }


        update_post_meta($post_id, '_product_image_gallery', implode(',', $gallery_ids));
        // Set Discounted Price as Regular Price
        if (!empty($product_data['Discounted Price']) && is_numeric($product_data['Discounted Price'])) {
            $discounted_price = floatval($product_data['Discounted Price']);
            update_post_meta($post_id, '_regular_price', $discounted_price);
            update_post_meta($post_id, '_price', $discounted_price);
        }

    }

    $message = $edit_mode ? 'Products Updated Successfully!' : 'Products Created Successfully!';
    $response = ['message' => $message];

    if (!empty($skipped_products)) {
        $response['skipped'] = $skipped_products;
        $response['warning'] = 'Some products were skipped because they already exist (based on SKU).';
    }

    wp_send_json_success($response);

}
function process_gallery_property_image($image_url)
{
    if (empty($image_url))
        return;

    $image_id = get_existing_attachment_id($image_url);

    if ($image_id === false) {
        $image_id = media_sideload_image($image_url, '', 'id');
    }

    return $image_id;
}

function get_existing_attachment_id($url)
{
    global $wpdb;
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];

    if (strpos($url, $base_url) === false) {
        return false;
    }

    $path = str_replace($base_url . '/', '', $url);
    $attachment = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE guid LIKE %s AND post_type = 'attachment'",
        '%' . $wpdb->esc_like($path) . '%'
    ));

    return $attachment ? (int) $attachment : false;
}

function custom_register_menus()
{
    register_nav_menu('admin-header-menu', __('Admin Header Menu'));
    register_nav_menu('suppliers-header-menu', __('Suppliers Header Menu'));
    register_nav_menu('logged-out-header-menu', __('Logged Out User Header Menu'));
    register_nav_menu('front-end-header-menu', __('Front End Menu (User Header)'));
    register_nav_menu('front-end-logout-header-menu', __('Front End Menu Logout (User Header)'));
    register_nav_menu('footer-left-menu', __('Footer Left Menu'));
    register_nav_menu('footer-middle-menu', __('Footer Middle Menu'));
    register_nav_menu('footer-right-menu', __('Footer Right Menu'));
}

add_action('init', 'custom_register_menus');



function enqueue_bulk_category_scripts()
{

    global $wp;

    // Enqueue on main My Account page ONLY
    // if ( is_account_page() && ! is_wc_endpoint_url()  
    //     && isset( $wp->query_vars['my-preferences'] ) 
    //     && isset( $wp->query_vars['my-wishlist'] ) 
    //     && isset( $wp->query_vars['my-wallet'] ) 
    //     && isset( $wp->query_vars['my-reminders'] )) {

    wp_enqueue_script(
        'my-account-js',
        get_template_directory_uri() . '/assets/js/my-account/my-account.js',
        array('jquery'),
        time(),
        true
    );
    wp_enqueue_style('my-account-css', get_template_directory_uri() . '/assets/css/my-account.css', array(), time());
    // }

    // Enqueue ONLY on My Preferences endpoint
    if (isset($wp->query_vars['my-preferences'])) {

        // echo "XXXXXXXXXXXXXXyy";  // <-- THIS WILL WORK NOW

        wp_enqueue_script(
            'my-preferences-js',
            get_template_directory_uri() . '/assets/js/my-account/my-preferences.js',
            array('jquery'),
            time(),
            true
        );

        wp_localize_script('my-preferences-js', 'my_pref_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('save_pref_nonce')
        ]);

    }

    if (is_page('create-category')) {
        wp_enqueue_style('datatable-css', get_template_directory_uri() . '/assets/css/datatable.css', array(), time());
        wp_enqueue_script('jquery');
        wp_enqueue_script('datatable-js', get_template_directory_uri() . '/assets/js/datatable.js', array('jquery'), true);

        // wp_enqueue_script('bulk-category-js', get_template_directory_uri() . '/assets/js/bulk-category.js', array('jquery', 'datatables-js'), '1.0.0', true);
        wp_enqueue_style(
            ' bulk-create-category-css',
            get_template_directory_uri() . '/assets/css/bulk-create-category.css',
            array(),
            time()
        );
        wp_enqueue_script('bulk-create-category-js', get_template_directory_uri() . '/assets/js/bulk-create-category.js', array('jquery'), time(), true);
        wp_localize_script('bulk-create-category-js', 'bulkCreateCategory', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bulk_create_category_nonce'),
            'siteUrl' => home_url('/'),
        ));
    }

    if (is_product()) {
        wp_enqueue_style('single-product-css', get_template_directory_uri() . '/assets/css/single-product.css', array(), time());
        wp_enqueue_script('single-product-js', get_template_directory_uri() . '/assets/js/single-product.js', array('jquery'), time(), true);
    }
   

}
add_action('wp_enqueue_scripts', 'enqueue_bulk_category_scripts');

// Handle category CSV upload ================================================== Start

add_action('wp_ajax_custom_upload_category_csv', 'handle_category_csv_upload');
function handle_category_csv_upload()
{
    gcp_require_admin_ajax();
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $uploaded_file = $_FILES['file'];
        $file_tmp_name = $uploaded_file['tmp_name'];

        $timestamp = wp_date('Y-m-d_H-i-s');
        $new_file_name = 'csv_' . $timestamp . '.csv';

        $upload_dir = WP_CONTENT_DIR . '/assets/csv/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $csv_data = parse_csv_file_cat($destination);

            $csv_template_headers = ['No', 'Category Name', 'Description', 'Priority', 'Icon Image', 'Banner Image', 'Thumbnail Image', 'Status', 'SKU\'s Assigned'];

            $header_mappings = compare_headers_cat($csv_template_headers, $csv_data['headers']);
            $all_matched = !in_array('', $header_mappings);

            wp_send_json_success([
                'message' => 'File uploaded and parsed successfully!',
                'csv_data' => $csv_data,
                'csv_template_headers' => $csv_template_headers,
                'header_mappings' => $header_mappings,
                'all_matched' => $all_matched
            ]);
        } else {
            wp_send_json_error(['message' => 'Error while uploading the file.']);
        }
    } else {
        wp_send_json_error(['message' => 'No file uploaded or there was an error.']);
    }
}

function parse_csv_file_cat($file_path)
{
    $csv_data = [];
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        $csv_data['headers'] = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            $csv_data['data'][] = $row;
        }
        fclose($handle);
    }
    return $csv_data;
}
add_action('wp_ajax_check_sku_exists', 'check_sku_exists_callback');
add_action('wp_ajax_nopriv_check_sku_exists', 'check_sku_exists_callback');

function check_sku_exists_callback()
{
    check_ajax_referer( 'gc_nonce', 'security' );
    $sku = sanitize_text_field($_GET['sku']);
    $exists = wc_get_product_id_by_sku($sku) ? true : false;
    wp_send_json(['exists' => $exists]);
}

function validate_email_cat($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function compare_headers_cat($csv_template_headers, $csv_headers)
{
    $mapping = [];
    foreach ($csv_template_headers as $template_header) {
        $found_match = '';
        foreach ($csv_headers as $csv_header) {
            if (preg_match_all('/\\b' . preg_quote($template_header, '/') . '\\b/i', $csv_header)) {
                $found_match = $csv_header;
                break;
            }
        }
        $mapping[$template_header] = $found_match;
    }
    return $mapping;
}




// Handle category CSV upload ================================================== END

// Create bulk categories
add_action('wp_ajax_create_bulk_categories', 'create_bulk_categories');

function create_bulk_categories()
{
    gcp_require_admin_ajax();
    check_ajax_referer('bulk_create_category_nonce', 'security');

    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => 'You do not have permission to manage categories']);
    }

    $categories = json_decode(wp_unslash($_POST['categories']), true);
    if (empty($categories)) {
        wp_send_json_error(['message' => 'No categories data received']);
    }

    $edit_mode_cat = isset($_POST['edit_mode']) && $_POST['edit_mode'] == 1;

    $results = [];
    foreach ($categories as $category_data) {
        $cat_id = (int) $category_data['No'];
        unset($category_data['No']);

        // Extract the correct columns based on your CSV structure
        $name = sanitize_text_field($category_data['Category Name']);
        $slug = sanitize_title($name); // Using 'Category Name' as slug
        $description = sanitize_text_field($category_data['Description'] ?? '');
        $priority = sanitize_text_field($category_data['Priority'] ?? '');
        $icon_url = esc_url_raw($category_data['Icon Image'] ?? '');
        $banner_url = esc_url_raw($category_data['Banner Image'] ?? '');
        $thumbnail_url = esc_url_raw($category_data['Thumbnail Image'] ?? '');
        $status = strtolower($category_data['Status'] ?? 'active');
        $skus = $category_data['SKU\'s Assigned'] ?? '';


        if (empty($name)) {
            $results[] = [
                'name' => $name,
                'status' => 'failed',
                'message' => 'Missing required field: Category Name'
            ];
            continue;
        }

        //$existing_term = term_exists($slug, 'product_cat');
        $existing_term = term_exists($cat_id, 'product_cat');

        if ($edit_mode_cat) {
            // Edit mode: only update if exists
            if (!$existing_term) {
                $results[] = [
                    'cat_id' => $cat_id,
                    'name' => $name,
                    'status' => 'skipped',
                    'message' => 'Category does not exist, skipped in edit mode'
                ];
                continue;
            }

            $term_id = is_array($existing_term) ? $existing_term['term_id'] : $existing_term;

            /*wp_update_term($term_id, 'product_cat', [
                'name' => $name,
                'description' => $description,
                'slug' => $slug
            ]);*/
            wp_update_term($term_id, 'product_cat', [
                'name' => $name,
                'description' => $description,
            ]);

        } else {
            // Not in edit mode: only create if not exists
            // if ($existing_term) {
            //     $results[] = [
            //         'name' => $name,
            //         'status' => 'skipped',
            //         'message' => 'Category already exists, skipped in create mode'
            //     ];
            //     continue;
            // }

            // Always try to create with a unique slug
            $unique_slug = generate_unique_slug($name, 'product_cat');

            $term = wp_insert_term($name, 'product_cat', [
                'description' => $description,
                'slug' => $unique_slug
            ]);


            if (is_wp_error($term)) {
                $results[] = [
                    'name' => $name,
                    'status' => 'failed',
                    'message' => $term->get_error_message()
                ];
                continue;
            }

            $term_id = $term['term_id'];
        }

        // Save or update meta fields
        if (!empty($priority))
            update_term_meta($term_id, 'priority', $priority);
        if (!empty($status))
            update_term_meta($term_id, 'category_status', $status);

        $rows = array();
        delete_field('sku_assigned_arr', 'product_cat_' . $term_id);

        if (!empty($skus)) {
            update_term_meta($term_id, 'skus_assigned', $skus);


            $skus_arr = explode(',', $skus);

            foreach ($skus_arr as $cat_sku_assigned) {
                $product_id = wc_get_product_id_by_sku($cat_sku_assigned);

                if ($product_id) {
                    $current_terms = wp_get_object_terms($product_id, 'product_cat', ['fields' => 'ids']);
                    if (!in_array((int) $term_id, $current_terms, true)) {
                        $rows[] = [
                            'assigned_product' => $product_id
                        ];
                        wp_set_object_terms($product_id, (int) $term_id, 'product_cat', true);
                    }
                }

            }
        }
        update_field('sku_assigned_arr', $rows, 'product_cat_' . $term_id);

        // Save or update images
        if (!empty($thumbnail_url)) {
            $thumb_id = process_single_property_image($thumbnail_url);
            if ($thumb_id)
                update_term_meta($term_id, 'thumbnail_id', $thumb_id);
        }

        if (!empty($icon_url)) {
            $icon_id = process_single_property_image($icon_url);

            // pre($icon_id);
            if ($icon_id)
                update_term_meta($term_id, 'category_icon', $icon_id);
        }

        if (!empty($banner_url)) {
            $banner_id = process_single_property_image($banner_url);
            if ($banner_id)
                update_term_meta($term_id, 'category_banner', $banner_id);
        }

        $results[] = [
            'name' => $name,
            'status' => 'success',
            'message' => $edit_mode_cat ? 'Category updated successfully' : 'Category created successfully',
            'term_id' => $term_id ?? null
        ];
    }

    // Summary message
    $success_count = count(array_filter($results, fn($r) => $r['status'] === 'success'));
    $skipped = array_filter($results, fn($r) => $r['status'] === 'skipped');
    $skipped_names = array_map(fn($r) => $r['name'], $skipped);

    $message = sprintf(
        'Processed %d categories. Success: %d, Skipped: %d.',
        count($results),
        $success_count,
        count($skipped)
    );

    if (!empty($skipped_names) && !$edit_mode_cat) {
        $message .= ' These Categories already exist: ' . implode(', ', $skipped_names) . '.';
    } elseif (!empty($skipped_names)) {
        $message .= ' Skipped: ' . implode(', ', $skipped_names) . '.';
    }

    wp_send_json_success([
        'message' => $message,
        'results' => $results
    ]);
}
function generate_unique_slug($base_slug, $taxonomy)
{
    $slug = sanitize_title($base_slug);
    $i = 1;
    $unique_slug = $slug;

    while (term_exists($unique_slug, $taxonomy)) {
        $unique_slug = $slug . '-' . $i;
        $i++;
    }

    return $unique_slug;
}

function process_single_property_image($image_url)
{
    if (empty($image_url)) {
        return false;
    }


    $image_id = get_existing_attachment_id_url($image_url);
    // primt_r($image_id);

    if ($image_id === false) {
        $image_id = media_sideload_image($image_url, '', '', 'id');
        if (is_wp_error($image_id)) {
            return false;
        }
        // return $image_id;
    }
    return $image_id ?: false;

}

function get_existing_attachment_id_url($image_url)
{
    global $wpdb;

    $filename = pathinfo($image_url, PATHINFO_FILENAME);

    $attachment_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND (post_title = %s OR guid = %s) LIMIT 1",
            $filename,
            $image_url
        )
    );


    return $attachment_id ? (int) $attachment_id : false;
}


// add_action('wp_ajax_create_bulk_categories', 'handle_bulk_create_categories');

// function handle_bulk_create_categories() {
//     // Verify nonce
//     if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bulk_create_category_nonce')) {
//         wp_send_json_error(array('message' => 'Security check failed'));
//     }

//     // Get the categories data from the AJAX request
//     $categories_data = isset($_POST['categories']) ? json_decode(wp_unslash($_POST['categories']), true) : null;

//     if (empty($categories_data)) {
//         wp_send_json_error(array('message' => 'No categories data received'));
//     }

//     // Process categories data
//     foreach ($categories_data as $category) {
//         // Example: Process each category
//         $category_name = isset($category['Category Name']) ? sanitize_text_field($category['Category Name']) : '';
//         if ($category_name) {
//             // Create category or update based on your logic
//         }
//     }

//     // Return success
//     wp_send_json_success(array('message' => 'Categories created successfully'));
// }

// Helper function to upload image from URL
// function upload_image_from_url($image_url) {
//     require_once(ABSPATH . 'wp-admin/includes/image.php');
//     require_once(ABSPATH . 'wp-admin/includes/file.php');
//     require_once(ABSPATH . 'wp-admin/includes/media.php');

//     $tmp = download_url($image_url);
//     if (is_wp_error($tmp)) {
//         return false;
//     }

//     $file_array = [
//         'name' => basename($image_url),
//         'tmp_name' => $tmp
//     ];

//     $id = media_handle_sideload($file_array, 0);
//     if (is_wp_error($id)) {
//         @unlink($file_array['tmp_name']);
//         return false;
//     }

//     return $id;
// }

add_action('wp_ajax_load_thumbnail_view', 'load_thumbnail_view_ajax');
function load_thumbnail_view_ajax()
{
    gcp_require_admin_ajax();
    $paged = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $per_page = 30;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $selected_cat = isset($_POST['cat']) ? sanitize_text_field($_POST['cat']) : '';

    $args = [
        'post_type' => 'product',
        'posts_per_page' => $per_page,
        'paged' => $paged,
    ];

    if (isset($selected_cat) && !empty($selected_cat)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat', // name of your taxonomy
                'field' => 'slug',             // or 'term_id' or 'name'
                'terms' => array_filter(explode(",", $selected_cat)),       // example term slug or array of slugs
            )
        );
    }

    if (!empty($search)) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);
    $html = '';

    if ($query->have_posts()) {
        foreach ($query->posts as $product) {
            $thumb = get_the_post_thumbnail_url($product->ID, 'medium') ?: 'https://via.placeholder.com/150';
            $edit_url = esc_url(site_url('/create-product/?edit_product=' . $product->ID));

            $title = esc_attr(get_the_title($product->ID));
            $html .= '<div class="thumbnail-item" data-title="' . $title . '">';
            $html .= '<a href="' . $edit_url . '" target="_blank">';
            $html .= '<div class="image"><img src="' . esc_url($thumb) . '" alt=""></div>';
            $html .= '<div class="card-hover-name">' . esc_html(get_the_title($product->ID)) . '</div>';
            $html .= '</a>';
            $html .= '</div>';
        }
    } else {
        $html .= '<p>No Cards found.</p>';
    }

    // Pagination code...
    $total_pages = $query->max_num_pages;
    $pagination = '';

    if ($total_pages > 1) {
        $pagination .= '<div class="pagination">';

        if ($paged > 1) {
            $pagination .= '<button data-page="' . ($paged - 1) . '">‹</button>';
        }

        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == 1 || $i == $total_pages || ($i >= $paged - 1 && $i <= $paged + 1)) {
                $active = ($i == $paged) ? 'active' : '';
                $pagination .= "<button class='{$active}' data-page='{$i}'>{$i}</button>";
            } elseif (!str_contains($pagination, 'dots')) {
                $pagination .= '<span class="dots">…</span>';
            }
        }

        if ($paged < $total_pages) {
            $pagination .= '<button data-page="' . ($paged + 1) . '">›</button>';
        }

        $pagination .= '</div>';
    }

    wp_send_json([
        'html' => $html,
        'pagination' => $pagination
    ]);
}



add_action('wp_ajax_load_thumbnail_view_review', 'load_thumbnail_view_review');

function load_thumbnail_view_review()
{
    gcp_require_admin_ajax();
    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = 30;

    $args = [
        'post_type' => 'product',
        'post_status' => 'draft',
        'posts_per_page' => $per_page,
        'paged' => $paged,
    ];

    $query = new WP_Query($args);
    $html = '';

    if ($query->have_posts()) {
        foreach ($query->posts as $product) {
            $thumb = get_the_post_thumbnail_url($product->ID, 'medium') ?: 'https://via.placeholder.com/150';
            $title = esc_html(get_the_title($product->ID));
            $html .= '<div class="thumbnail-item">';
            $html .= '<div class="image"><img src="' . esc_url($thumb) . '" alt="' . $title . '"></div>';
            $html .= '<p class="name">' . $title . '</p>';
            $html .= '</div>';
        }
    } else {
        $html = '<p>No Cards found.</p>';
    }

    // Pagination
    $total_pages = $query->max_num_pages;
    $pagination = '';
    if ($total_pages > 1) {
        for ($i = 1; $i <= $total_pages; $i++) {
            $active = ($i == $paged) ? 'active' : '';
            $pagination .= "<button class='{$active}' data-page='{$i}'>{$i}</button>";
        }
    }

    wp_send_json_success([
        'html' => $html,
        'pagination' => $pagination
    ]);
}


add_action('wp_ajax_load_thumbnail_view_brand', 'load_thumbnail_view_brand');

function load_thumbnail_view_brand()
{
    gcp_require_admin_ajax();
    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = 30;

    $args = [
        'post_type' => 'product',
        'post_status' => 'draft',
        'posts_per_page' => $per_page,
        'paged' => $paged,
    ];

    $query = new WP_Query($args);
    $html = '';

    if ($query->have_posts()) {
        foreach ($query->posts as $product) {
            $thumb = get_the_post_thumbnail_url($product->ID, 'medium') ?: 'https://via.placeholder.com/150';
            $title = esc_html(get_the_title($product->ID));
            $html .= '<div class="thumbnail-item">';
            $html .= '<div class="image"><img src="' . esc_url($thumb) . '" alt="' . $title . '"></div>';
            $html .= '<p class="name">' . $title . '</p>';
            $html .= '</div>';
        }
    } else {
        $html = '<p>No Cards found.</p>';
    }

    // Pagination
    $total_pages = $query->max_num_pages;
    $pagination = '';
    if ($total_pages > 1) {
        for ($i = 1; $i <= $total_pages; $i++) {
            $active = ($i == $paged) ? 'active' : '';
            $pagination .= "<button class='{$active}' data-page='{$i}'>{$i}</button>";
        }
    }

    wp_send_json_success([
        'html' => $html,
        'pagination' => $pagination
    ]);
}



add_action('wp_ajax_get_product_meta_for_form', 'get_product_meta_for_form');

function get_product_meta_for_form()
{
    gcp_require_admin_ajax();
    $product_id = intval($_POST['product_id'] ?? 0);
    if (!$product_id)
        wp_send_json_error('Invalid product ID');

    $product = wc_get_product($product_id);
    if (!$product)
        wp_send_json_error('Product not found');

    $product_images = [];

    // Fetch gallery image IDs using WordPress native post meta
    $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
    $gallery_ids = !empty($gallery_ids) ? explode(',', $gallery_ids) : [];

    // If gallery is empty, fallback to featured image
    if (empty($gallery_ids)) {
        $featured_id = get_post_thumbnail_id($product_id);
        if ($featured_id) {
            $product_images[] = wp_get_attachment_url($featured_id);
        }
    } else {
        foreach ($gallery_ids as $id) {
            $url = wp_get_attachment_url($id);
            if ($url) {
                $product_images[] = $url;
            }
        }
    }

    // Get brand logo
    $brand_logo = '';
    $brands = wp_get_post_terms($product_id, 'product_brand');
    if (!empty($brands) && !is_wp_error($brands)) {
        $brand = $brands[0];
        $thumbnail_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
        if ($thumbnail_id) {
            $brand_logo = wp_get_attachment_url($thumbnail_id);
        }
    }

    // Get parent_sku ID and format display string
    $parent_sku_id = get_post_meta($product_id, 'parent_sku', true);
    $parent_sku_display = '';
    if (!empty($parent_sku_id)) {
        $parent_product = wc_get_product($parent_sku_id);
        if ($parent_product) {
            $sku = $parent_product->get_sku();
            $title = $parent_product->get_name();
            $parent_sku_display = $title . ' --- ' . $sku;
        }
    }
    // Get taxonomy terms
    $product_brand_terms = wp_get_post_terms($product_id, 'product_brand', ['fields' => 'names']);
    $product_brand_cat = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);

    $icon_terms = wp_get_post_terms($product_id, 'icons', ['fields' => 'names']);
    $tag_terms = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
    $eligible_retailer_terms = wp_get_post_terms($product_id, 'eligible_retailers', ['fields' => 'names']);

    $data = [
        'sku' => $product->get_sku(),
        'title' => $product->get_name(),
        'parent_sku' => $parent_sku_display,
        'buyer_upload' => get_post_meta($product_id, 'buyer_upload', true),
        'supplier_sku' => get_post_meta($product_id, '_supplier_sku', true),
        'product_brand' => $product_brand_terms,
        'icons' => $icon_terms,
        'product_categories' => $product_brand_cat,
        'tags' => $tag_terms,
        'eligible_retailers' => $eligible_retailer_terms,
        'supplier' => get_post_meta($product_id, 'supplier', true),
        'short_description' => $product->get_short_description(),
        'long_description' => $product->get_description(),
        'terms_conditions' => get_post_meta($product_id, 'terms_conditions', true),
        'how_to_use' => get_post_meta($product_id, 'how_to_use', true),
        'expiry_datetime' => get_post_meta($product_id, '_expire_date', true),
        'gift_card_expiry_type' => get_post_meta($product_id, 'gift_card_expiry_type', true),
        'gift_card_expiry_date' => get_post_meta($product_id, 'gift_card_expiry_date', true),
        'gift_card_expiry_duration' => get_post_meta($product_id, 'gift_card_expiry_duration', true),
        'gift_card_expiry_unit' => get_post_meta($product_id, 'gift_card_expiry_unit', true),
        'expiry_date' => get_post_meta($product_id, 'expiry_date', true),
        'activation_expiry_type' => get_post_meta($product_id, 'activation_expiry_type', true),
        'activation_expiry_date' => get_post_meta($product_id, 'activation_expiry_date', true),
        'activation_expiry_duration' => get_post_meta($product_id, 'activation_expiry_duration', true),
        'activation_expiry_unit' => get_post_meta($product_id, 'activation_expiry_unit', true),
        'allow_upload' => get_post_meta($product_id, 'allow_upload', true),
        'auto_populate' => get_post_meta($product_id, 'auto_populate', true),
        'denomination_type' => get_post_meta($product_id, 'denomination_type', true),
        '_sell_price_fixed' => get_post_meta($product_id, '_sell_price_fixed', true),
        '_cost_price' => get_post_meta($product_id, '_cost_price', true),
        '_supplier_fullfillment_price' => get_post_meta($product_id, '_supplier_fullfillment_price', true),
        'gst' => get_post_meta($product_id, '_gst', true),
        'j_a_c_fulfillment_cost' => get_post_meta($product_id, 'j_a_c_fulfillment_cost', true),
        '_total_sell_price' => get_post_meta($product_id, '_total_sell_price', true),
        '_total_buy_price' => get_post_meta($product_id, '_total_sell_price', true),
        '_total_buy_price_gst' => get_post_meta($product_id, '_total_buy_price_gst', true),
        'margin_per' => get_post_meta($product_id, 'margin_per', true),
        'margin_currency' => get_post_meta($product_id, 'margin_currency', true),
        'preset_delivery_class' => get_post_meta($product_id, 'preset_delivery_class', true),
        '_shipping_class' => get_post_meta($product_id, '_shipping_class', true),
        'delivery_cost' => get_post_meta($product_id, '_delivery_cost', true),
        'variable_range_from' => get_post_meta($product_id, 'variable_range_from', true),
        'variable_range_to' => get_post_meta($product_id, 'variable_range_to', true),
        '_reedem_at_intervals' => get_post_meta($product_id, '_reedem_at_intervals', true),
        'sell_price_lowest_denomination' => get_post_meta($product_id, 'sell_price_lowest_denomination', true),
        'discounted' => get_post_meta($product_id, 'discounted', true),
        'discounted_price' => get_post_meta($product_id, 'discounted_price', true),
        '_margin' => get_post_meta($product_id, '_margin', true),
        '_discount_valid_from' => get_post_meta($product_id, '_discount_valid_from', true),
        '_discount_valid_to' => get_post_meta($product_id, '_discount_valid_to', true),
        '_discount_margin' => get_post_meta($product_id, '_discount_margin', true),
        'display_on' => get_post_meta($product_id, 'display_on', true),
        '_extra_header' => get_post_meta($product_id, '_extra_header', true),
        '_add_stock_level' => get_post_meta($product_id, '_stock', true),
        'transaction_limit' => get_post_meta($product_id, 'transaction_limit', true),
        '_quantity_per_transaction' => get_post_meta($product_id, '_quantity_per_transaction', true),
        '_total_value_per_transaction' => get_post_meta($product_id, '_total_value_per_transaction', true),
        'always_on' => get_post_meta($product_id, 'always_on', true),
        '_onsite_from' => get_post_meta($product_id, '_onsite_from', true),
        '_onsite_to' => get_post_meta($product_id, '_onsite_to', true),
        'product_status' => get_post_status($product_id),
        'image_url' => wp_get_attachment_url($product->get_image_id()), // image for preview
        'sku_type' => get_post_meta($product_id, 'sku_type', true),
        'product_images' => $product_images,
        'brand_logo' => $brand_logo,
    ];

    wp_send_json_success($data);
}
add_action('wp_ajax_get_product_details', 'get_product_details_callback');
add_action('wp_ajax_nopriv_get_product_details', 'get_product_details_callback');
function get_product_details_callback()
{
    check_ajax_referer('search_parent_sku_nonce', 'nonce');

    $product_id = intval($_POST['product_id']);

    if ($product_id > 0) {
        $product = wc_get_product($product_id);
        if ($product) {

            $_expire_date = get_post_meta($product_id, '_expire_date', true);
            $_expire_date = !empty($_expire_date) ? $_expire_date : '';

            $_onsite_from = get_post_meta($product_id, '_onsite_from', true);
            $_onsite_to = get_post_meta($product_id, '_onsite_to', true);
            $always_on = empty($_onsite_from) && empty($_onsite_to);

            $shipping_class_id = $product->get_shipping_class_id();
            $shipping_class_term = get_term($shipping_class_id, 'product_shipping_class');
            $preset_delivery_class = !is_wp_error($shipping_class_term) && $shipping_class_term ? $shipping_class_term->slug : '';
            $delivery_cost = get_post_meta($product_id, '_delivery_cost', true);



            $product_details = array(
                'short_description' => $product->get_short_description(),
                'long_description' => $product->get_description(),
                'terms_conditions' => get_post_meta($product_id, 'terms_conditions', true),
                'how_to_use' => get_post_meta($product_id, 'how_to_use', true),
                '_expire_date' => $_expire_date,
                'preset_delivery_class' => $preset_delivery_class,
                '_onsite_from' => $_onsite_from,
                '_onsite_to' => $_onsite_to,
                'always_on' => $always_on,
                'delivery_cost' => $delivery_cost
            );

            // Save the SKU to ACF field
            // update_field('field_67a4c43f56c47', $parent_sku, $product_id);

            wp_send_json_success($product_details);
        }
    }

    wp_send_json_error("Invalid Product ID");
}

function search_product_sku_callback()
{
    check_ajax_referer('search_parent_sku_nonce', 'nonce');

    $search_term = sanitize_text_field($_POST['search_term']);

    $args = array(
        'post_type' => 'product',
        'post_status' => 'any',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_sku',
                'value' => $search_term,
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'sku_type',
                'value' => 'Parent',
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);
    $results = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            if ($product) {
                // $parent_sku = get_post_meta($product->get_id(), 'parent_sku', true);
                $parent_sku = $product->get_sku();


                $results[] = array(
                    'id' => $product->get_id(),
                    'sku' => $parent_sku,
                    'name' => $product->get_name()
                );
            }
        }
        wp_reset_postdata();
        wp_send_json_success($results);
    } else {
        wp_send_json_success(array()); // no results
    }
}
add_action('wp_ajax_search_product_sku', 'search_product_sku_callback');
add_action('wp_ajax_nopriv_search_product_sku', 'search_product_sku_callback');
function gift_card_pagination()
{
    check_ajax_referer( 'gc_nonce', 'security' );
    global $wpdb;

    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $posts_per_page = 30; // Adjust based on needs

    // Base query arguments
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'sku_type',
                'value' => 'Child',
                'compare' => '!=',
            ),
        ),
    );


    // Filter to search by title only
    if (!empty($search)) {
        add_filter('posts_where', function ($where) use ($search, $wpdb) {
            return $where . $wpdb->prepare(" AND {$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        });
    }

    $products = new WP_Query($args);
    $total_pages = $products->max_num_pages; // Get total pages based on filtered results

    // echo'<pre>';
    // echo'</pre>';

    ob_start(); // Start output buffering

    if ($products->have_posts()):
        while ($products->have_posts()):
            $products->the_post();
            $product_id = get_the_ID();
            $image_url = get_the_post_thumbnail_url($product_id, 'medium');
            $title = get_the_title();
            $sku = get_post_meta($product_id, '_sku', true);
            $sku_type = get_post_meta($product_id, 'sku_type', true);
            $description = strip_tags(get_the_excerpt());
            $denomination_type = get_field('denomination_type', $product_id);
            $activation_expiry_type = get_field('activation_expiry_type', $product_id);
            $j_a_c_fulfillment_cost = get_field('j_a_c_fulfillment_cost', $product_id);
            $is_discounted = get_field('discounted_price_checkbox', $product_id);
            $discounted_from = get_post_meta($product_id, '_discount_valid_from', true);
            $discounted_to = get_post_meta($product_id, '_discount_valid_to', true);
            $sale_price = get_post_meta($product_id, '_sale_price', true);
            $regular_price = get_post_meta($product_id, '_regular_price', true);
            $is_blackhawk_product = get_post_meta($product_id, '_is_blackhawk_product', true);

            // $denomination_type = get_field('denomination_type', $product_id);
            if ($denomination_type === 'fixed') {
                $price = get_post_meta($product_id, '_regular_price', true);
                $min_price = $price;
                $max_price = $price;
                $is_unavailable = (!is_numeric($price) || $price <= 0);

            } elseif ($denomination_type === 'variable') {
                $min_price = get_field('variable_range_from', $product_id);
                $max_price = get_field('variable_range_to', $product_id);
                $_reedem_at_intervals = get_field('_reedem_at_intervals', $product_id);

                $is_unavailable = (
                    !is_numeric($min_price) || $min_price <= 0 ||
                    !is_numeric($max_price) || $max_price <= 0 ||
                    !is_numeric($_reedem_at_intervals) || $_reedem_at_intervals <= 0
                );

            } else {
                $min_price = '';
                $max_price = '';
                $_reedem_at_intervals = '';
                $is_unavailable = true;
            }

            $terms = get_the_terms($product_id, 'product_brand');
            $prod_brands = array();
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $prod_brands[] = $term->name;
                }
            }

            /* Get CHILD SKU's (if this is parent) */
            $has_child = '';
            $child_div = '';

            if ($sku_type == 'Parent') {
                $child_args = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key' => 'sku_type',
                            'value' => 'Child',
                            'compare' => '=',
                        ),
                        array(
                            'key' => 'parent_sku',
                            'value' => $sku,
                            'compare' => '=',
                        ),
                    ),
                );
                $child_products = new WP_Query($child_args);

                if ($child_products->have_posts()) {
                    $has_child = ' has_child';
                    $child_div = '<div class="child_gift_cards" style="display: none;">';
                    while ($child_products->have_posts()):
                        $child_products->the_post();

                        $child_product_id = get_the_ID();
                        $child_image_url = get_the_post_thumbnail_url($child_product_id, 'medium');
                        $child_title = get_the_title();
                        $child_sku = get_post_meta($child_product_id, '_sku', true);
                        $child_description = strip_tags(get_the_excerpt());
                        $child_denomination_type = get_field('denomination_type', $child_product_id);
                        $child_activation_expiry_type = get_field('activation_expiry_type', $child_product_id);
                        $child_j_a_c_fulfillment_cost = get_field('j_a_c_fulfillment_cost', $child_product_id);
                        $child_is_discounted = get_field('discounted_price_checkbox', $child_product_id);
                        $child_discounted_from = get_post_meta($child_product_id, '_discount_valid_from', true);
                        $child_discounted_to = get_post_meta($child_product_id, '_discount_valid_to', true);
                        $child_sale_price = get_post_meta($child_product_id, '_sale_price', true);
                        $child_regular_price = get_post_meta($child_product_id, '_regular_price', true);

                        // $child_denomination_type = get_field('denomination_type', $child_product_id);
                        if ($child_denomination_type === 'fixed') {
                            $child_price = get_post_meta($child_product_id, '_regular_price', true);
                            $child_min_price = $child_price;
                            $child_max_price = $child_price;
                            $child_is_unavailable = (!is_numeric($child_price) || $child_price <= 0);

                        } elseif ($child_denomination_type === 'variable') {
                            $child_min_price = get_field('variable_range_from', $child_product_id);
                            $child_max_price = get_field('variable_range_to', $child_product_id);
                            $child__reedem_at_intervals = get_field('_reedem_at_intervals', $child_product_id);

                            $child_is_unavailable = (
                                !is_numeric($child_min_price) || $child_min_price <= 0 ||
                                !is_numeric($child_max_price) || $child_max_price <= 0 ||
                                !is_numeric($child__reedem_at_intervals) || $child__reedem_at_intervals <= 0
                            );

                        } else {
                            $child_min_price = '';
                            $child_max_price = '';
                            $child__reedem_at_intervals = '';
                            $child_is_unavailable = true;
                        }

                        $child_terms = get_the_terms($child_product_id, 'product_brand');
                        $child_prod_brands = array();
                        if ($child_terms && !is_wp_error($child_terms)) {
                            foreach ($child_terms as $child_term) {
                                $child_prod_brands[] = $child_term->name;
                            }
                        }

                        $child_div .= '<div class="gift-card-child-products' . $child_has_child . '" data-id="' . esc_attr($child_product_id) . '"
                                data-title="' . esc_attr($child_title) . '" data-image="' . esc_url($child_image_url) . '"
                                data-sku="' . esc_attr($child_sku) . '" data-price="' . esc_attr($child_price) . '"
                                data-description="' . esc_attr($child_description) . '" data-min-price="' . esc_attr($child_min_price) . '"
                                data-max-price="' . esc_attr($child_max_price) . '"
                                data-intervals="' . esc_attr($child__reedem_at_intervals) . '"
                                data-fullfillment-cost="' . esc_attr($child_j_a_c_fulfillment_cost) . '"
                                data-unavailable="' . esc_attr($child_is_unavailable ? 'true' : 'false') . '"
                                data-regular-price="' . esc_attr($child_regular_price) . '"
                                data-sale-price="' . esc_attr($child_sale_price) . '"
                                data-is-discounted="' . esc_attr($child_is_discounted) . '"
                                data-discounted-from="' . esc_attr($child_discounted_from . 'Z') . '"
                                data-discounted-to="' . esc_attr($child_discounted_to . 'Z') . '"
                                data-activation-expiry-type="' . esc_attr($child_activation_expiry_type) . '"
                                data-brands="' . esc_attr(implode(', ', $child_prod_brands)) . '"
                                data-image="' . esc_url($child_image_url) . '"
                                data-is-blackhawk-product="' . esc_attr($is_blackhawk_product) . '"
                                data-denomination-type="' . esc_attr($child_denomination_type) . '">
                            </div>';

                    endwhile;
                    $child_div .= '</div>';
                } else {
                    continue;
                }
            }
            // $is_unavailable = (empty($price) || (empty($min_price) && $denomination_type === 'Fixed') || empty($max_price));

            ?>
            <div class="gift-card-products<?php echo $has_child; ?>" data-id="<?php echo esc_attr($product_id); ?>"
                data-title="<?php echo esc_attr($title); ?>" data-image="<?php echo esc_url($image_url); ?>"
                data-sku="<?php echo esc_attr($sku); ?>" data-price="<?php echo esc_attr($price); ?>"
                data-description="<?php echo esc_attr($description); ?>" data-min-price="<?php echo esc_attr($min_price); ?>"
                data-max-price="<?php echo esc_attr($max_price); ?>" data-intervals="<?php echo esc_attr($_reedem_at_intervals); ?>"
                data-fullfillment-cost="<?php echo esc_attr($j_a_c_fulfillment_cost); ?>"
                data-unavailable="<?php echo esc_attr($is_unavailable ? 'true' : 'false'); ?>"
                data-regular-price="<?php echo esc_attr($regular_price); ?>" data-sale-price="<?php echo esc_attr($sale_price); ?>"
                data-is-discounted="<?php echo esc_attr($is_discounted); ?>"
                data-discounted-from="<?php echo esc_attr($discounted_from . 'Z'); ?>"
                data-discounted-to="<?php echo esc_attr($discounted_to . 'Z'); ?>"
                data-activation-expiry-type="<?php echo esc_attr($activation_expiry_type); ?>"
                data-brands="<?php echo esc_attr(implode(', ', $prod_brands)); ?>"
                data-is-blackhawk-product='<?php echo esc_attr($is_blackhawk_product ?? ''); ?>'
                data-denomination-type="<?php echo esc_attr($denomination_type); ?>">
                <div class="gift-card-image" style="background-image: url('<?php echo esc_url($image_url); ?>');"></div>
                <h6 class="gift-card-title"><?php echo esc_html($title); ?></h6>
                <?php if (!empty($has_child) && $has_child == ' has_child') {
                    echo $child_div;
                } ?>
            </div>
            <?php
        endwhile;
        wp_reset_postdata();
    else:
        echo "<p>No gift cards found.</p>";
    endif;

    $html = ob_get_clean(); // Get buffered content and clear buffer

    echo json_encode([
        'html' => $html,
        'total_pages' => $total_pages // Send total pages count to JavaScript
    ]);

    exit();
}

add_action('wp_ajax_gift_card_pagination', 'gift_card_pagination');
add_action('wp_ajax_nopriv_gift_card_pagination', 'gift_card_pagination');


add_action('wp_ajax_save_recipients_to_user_acf', 'create_recipients_as_users_callback');

function create_recipients_as_users_callback()
{
    gcp_require_logged_in_ajax();
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    $recipients_raw = $_POST['recipients'] ?? '[]';
    $recipients = json_decode(wp_unslash($recipients_raw), true);

    if (!$user_id || empty($recipients)) {
        wp_send_json_error(['message' => 'Missing business user or recipient data.']);
    }

    // Define all business-related fields to copy
    $business_fields = [
        'business_name',
        'float_balance',
        'business_website',
        'business_id',
        'billing_details',
        'billing_details_2',
        'approved_for_client_billing', // Checkbox
        'business_float_id',
        'business_abn',
        'business_currency',
        'address_line1',
        'address_line2',
        'suburb',
        'state',
        'country',
        'postcode',
    ];

    // Fetch business user details from ACF
    $business_data = [];
    foreach ($business_fields as $field_key) {
        $value = get_field($field_key, 'user_' . $user_id);
        if ($value !== false && $value !== null) {
            $business_data[$field_key] = $value;
        }
    }

    // Fallback for business name if not set
    if (empty($business_data['business_name'])) {
        $business_user = get_userdata($user_id);
        if ($business_user) {
            $business_data['business_name'] = $business_user->display_name;
        }
    }

    $created = [];
    $skipped = [];

    foreach ($recipients as $recipient) {
        $email = sanitize_email($recipient['contact']);
        $first_name = sanitize_text_field($recipient['recipient']);

        if (!is_email($email)) {
            $skipped[] = "$first_name (Invalid email)";
            continue;
        }

        if (email_exists($email)) {
            $skipped[] = "$first_name ($email already exists)";
            continue;
        }

        // Generate username from email
        $base_username = sanitize_user(current(explode('@', $email)), true);
        $username = $base_username;
        $suffix = 1;

        while (username_exists($username)) {
            $username = $base_username . $suffix;
            $suffix++;
        }

        $password = wp_generate_password();
        $new_user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($new_user_id)) {
            $skipped[] = "$first_name (Error: " . $new_user_id->get_error_message() . ")";
            continue;
        }

        wp_update_user([
            'ID' => $new_user_id,
            'first_name' => $first_name,
        ]);

        // Set role to 'recipients'
        $new_user = new WP_User($new_user_id);
        $new_user->set_role('recipients');

        // Save business info to recipient user
        foreach ($business_data as $field_key => $value) {
            update_field($field_key, $value, 'user_' . $new_user_id);
        }
        update_field('assigned_business_user', $user_id, 'user_' . $new_user_id);

        $created[] = "$first_name ($email)";
    }

    $message = count($created) > 0
        ? count($created) . ' recipient(s) created successfully.'
        : 'No new recipients were created.';

    wp_send_json_success([
        'message' => $message,
        'created' => $created,
        'skipped' => $skipped,
    ]);
}




// Add address book calback code END Here
add_action('wp_ajax_add_sender_to_business_user', 'add_sender_to_business_user_callback');

function add_sender_to_business_user_callback()
{
    gcp_require_admin_ajax();
    $user_id = intval($_POST['user_id']);
    $sender_name = sanitize_text_field($_POST['sender_name']);
    $sender_email = sanitize_email($_POST['sender_email']);
    // $sender_campaign = sanitize_email($_POST['sender_campaign']);

    if (!$user_id || empty($sender_name) || empty($sender_email)) {
        wp_send_json_error(['message' => 'Missing required fields.']);
    }

    // Get current repeater data
    $existing_senders = get_field('sender_details', 'user_' . $user_id) ?: [];

    // $existing_sender_campaign = get_field('add_campaign', 'user_' . $user_id) ?: [];
    foreach ($existing_senders as $sender) {
        if (isset($sender['sender_email']) && strtolower($sender['sender_email']) === strtolower($sender_email)) {
            wp_send_json_error(['message' => 'Sender with this email already exists.']);
        }
    }


    // Append new recipient
    $existing_senders[] = [
        'sender_name' => $sender_name,
        'sender_email' => $sender_email,
    ];

    // $existing_sender_campaign[] = [
    //     'campaign' => $sender_campaign,
    // ];

    update_field('sender_details', $existing_senders, 'user_' . $user_id);
    // update_field('sender_details', $existing_sender, 'user_' . $user_id);


    wp_send_json_success(['message' => 'Sender added successfully', 'senderName' => $sender_name, 'senderEmail' => $sender_email,]);
}


add_action('wp_ajax_add_campaign_to_business_user', 'add_campaign_to_business_user_callback');

function add_campaign_to_business_user_callback()
{
    gcp_require_admin_ajax();
    $user_id = intval($_POST['user_id']);

    $campaign = sanitize_text_field($_POST['sender_campaign']);


    if (!$user_id || empty($campaign)) {
        wp_send_json_error(['message' => 'Missing required fields.']);
    }

    $existing_campaigns = get_field('add_campaign', 'user_' . $user_id);
    if (!is_array($existing_campaigns)) {
        $existing_campaigns = [];
    }

    // Check if campaign already exists (case-insensitive)
    foreach ($existing_campaigns as $existing) {
        if (isset($existing['campaign']) && strtolower($existing['campaign']) === strtolower($campaign)) {
            wp_send_json_error(['message' => 'Campaign with this name already exists.']);
        }
    }


    $existing_campaigns[] = ['campaign' => $campaign];
    update_field('add_campaign', $existing_campaigns, 'user_' . $user_id);

    wp_send_json_success(['message' => 'Campaign saved successfully', 'campaign' => $campaign,]);
}
add_action('wp_ajax_get_user_campaigns', 'get_user_campaigns_callback');


function get_user_campaigns_callback()
{
    gcp_require_logged_in_ajax();
    $user_id = intval($_POST['user_id']);
    $campaigns = [];

    if (!$user_id) {
        wp_send_json_error(['message' => 'User ID missing.']);
    }

    $repeater = get_field('add_campaign', 'user_' . $user_id);
    if (!empty($repeater) && is_array($repeater)) {
        foreach ($repeater as $row) {
            if (!empty($row['campaign'])) {
                $campaigns[] = esc_html($row['campaign']);
            }
        }
    }

    wp_send_json_success(['campaigns' => $campaigns]);
}


add_action('wp_ajax_download_product_categories_csv_ajax', 'download_product_categories_csv_ajax');
// PT-3.1: removed wp_ajax_nopriv hook — admin-only CSV export.

function download_product_categories_csv_ajax()
{
    if ( ! current_user_can( 'manage_options' ) ) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }

    // Verify nonce
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bulk_create_category_nonce')) {
        http_response_code(403);
        echo 'Invalid security token.';
        exit;
    }

    // Get product categories
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);

    if (is_wp_error($categories)) {
        http_response_code(500);
        echo 'Error fetching categories.';
        exit;
    }

    // Clean output buffer to prevent whitespace or extra content
    // Clean all buffers completely
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="product_categories.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Category Name', 'Description', 'Priority', 'Icon Image', 'Banner Image', 'Thumbnail Image', 'Status', "SKU's Assigned"]);
    $i = 1;
    foreach ($categories as $cat) {
        $priority = get_term_meta($cat->term_id, 'priority', true);
        $category_status = get_term_meta($cat->term_id, 'category_status', true);

        // Get all products under this category
        $product_ids = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $cat->term_id,
                ],
            ],
        ]);

        $skus_assigned = [];

        foreach ($product_ids as $product_id) {
            $sku = get_post_meta($product_id, '_sku', true);
            if ($sku) {
                $skus_assigned[] = $sku;
            }
        }

        $skus_assigned_string = implode(',', $skus_assigned);

        // Image URLs
        $thumb_url = wp_get_attachment_url(get_term_meta($cat->term_id, 'thumbnail_id', true));
        $icon_url = wp_get_attachment_url(get_term_meta($cat->term_id, 'category_icon', true));
        $banner_url = wp_get_attachment_url(get_term_meta($cat->term_id, 'category_banner', true));

        fputcsv($output, [
            $cat->term_id,
            $cat->name,
            $cat->description,
            $priority,
            $icon_url,
            $banner_url,
            $thumb_url,
            $category_status,
            $skus_assigned_string,
        ]);
    }
    fclose($output);
    exit;
}

function get_product_id_by_sku_fallback($sku)
{
    global $wpdb;
    $raw_sku = html_entity_decode($sku);
    $prepared_sql = $wpdb->prepare("
        SELECT post_id FROM $wpdb->postmeta 
        WHERE meta_key = '_sku' AND (meta_value = %s OR meta_value = %s)
        LIMIT 1
    ", $sku, $raw_sku);

    $product_id = $wpdb->get_var($prepared_sql);

    return $product_id ? absint($product_id) : 0;
}

add_action('wp_ajax_save_draft_order_with_recipients', 'handle_save_draft_order');

function handle_save_draft_order()
{
    gcp_require_logged_in_ajax();
    check_ajax_referer( 'draft_order_ajax_nonce', 'nonce' );
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    // Decode the JSON data (some callers send JSON string, others may send array)
    $recipients = [];
    if (isset($_POST['recipients'])) {
        if (is_array($_POST['recipients'])) {
            $recipients = $_POST['recipients'];
        } else {
            $decoded = json_decode(wp_unslash((string) $_POST['recipients']), true);
            $recipients = is_array($decoded) ? $decoded : [];
        }
    }
    $csv_data = isset($_POST['csv_data']) ? json_decode(wp_unslash($_POST['csv_data']), true) : [];
    $form_data = isset($_POST['form_data']) ? json_decode(wp_unslash($_POST['form_data']), true) : [];
    $business_details = isset($_POST['business_details']) ? json_decode(wp_unslash($_POST['business_details']), true) : [];
    // Default to step 0 if not provided (prevents false "No recipients found" failures)
    $current_step = isset($_POST['current_step']) ? sanitize_text_field($_POST['current_step']) : '0';
    $personalise_all_checkbox = isset($_POST['personaliseAllCheckbox']) ? sanitize_text_field($_POST['personaliseAllCheckbox']) : 'no';
    $sender_name = isset($_POST['sender_name']) ? sanitize_text_field($_POST['sender_name']) : '';
    $sender_email = isset($_POST['sender_email']) ? sanitize_email($_POST['sender_email']) : '';
    $status = isset($_POST['status']) ? sanitize_email($_POST['status']) : '';
    $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : '';

    // echo'<pre>';
    // echo'</pre>';
    if (empty($recipients) && (string) $current_step !== '0') {
        wp_send_json_error(['message' => 'No recipients found.']);
    }



    //$order = wc_create_order(['status' => 'draft']);

    // Check if order exists
    if ($order_id && ($order = wc_get_order($order_id)) && is_a($order, 'WC_Order')) {
        $is_update = true;

        // Optional: remove existing line items
        foreach ($order->get_items('line_item') as $item_id => $item) {
            $order->remove_item($item_id);
        }

    } else {
        // Create new draft order
        $order = wc_create_order(['status' => 'draft']);
        $is_update = false;
    }

    $order->update_meta_data('_current_step', (int) $current_step);

    if (!empty($business_details['business_id'])) {
        $business_user_id = absint($business_details['business_id']);
        $order->set_customer_id($business_user_id);
        // update_post_meta($order->get_id(),'_customer_user',$business_user_id);
        if (!$order->get_meta('_customer_user')) {
            $order->update_meta_data('_customer_user', $business_user_id);
        }
        // $order->update_meta_data('_customer_user', $business_user_id);
        // pr($business_user_id);
        $user = get_userdata($business_user_id);
        if ($user) {
            $order->set_billing_first_name($user->first_name);
            $order->set_billing_last_name($user->last_name);
            $order->set_billing_email($user->user_email);
        }
    }

    if (!empty($business_details['order_type'])) {
        $order->update_meta_data('_order_type', sanitize_text_field($business_details['order_type']));
    }


    $order_summary_notes = [];
    $recipients_details = array();
    $recipients_details_arr = array();

    $i = 0;
    //pr($recipients);
    foreach ($recipients as $index => $recipient) {
        $first_name = sanitize_text_field($recipient['first_name']);
        $surname = isset($recipient['surname']) ? sanitize_text_field($recipient['surname']) : '';
        $email = isset($recipient['email']) ? sanitize_email($recipient['email']) : '';
        $phone = isset($recipient['phone']) ? $recipient['phone'] : '';
        $delivery_method = isset($recipient['delivery_method']) ? sanitize_text_field($recipient['delivery_method']) : '';

        // Skip if both email and phone are empty
        if (empty($email) && empty($phone)) {
            continue;
        }

        $full_name = trim($first_name . ' ' . $surname);

        $recipient_note = "Recipient #" . ($index + 1) . ":\n";
        $recipient_note .= "- Name: $full_name\n";
        if (!empty($email)) {
            $recipient_note .= "- Email: $email\n";
        }
        if (!empty($phone)) {
            $recipient_note .= "- Phone: $phone\n";
        }
        $recipient_note .= "- Gift Cards:\n";

        // Make sure gift cards exist and is an array
        $gift_cards = isset($recipient['gift_cards']) && is_array($recipient['gift_cards']) ? $recipient['gift_cards'] : [];


        $recipients_details_arr[$i]['first_name'] = $first_name;
        $recipients_details_arr[$i]['surname'] = $surname;
        $recipients_details_arr[$i]['email'] = $email;
        $recipients_details_arr[$i]['phone'] = $phone;

        // Remove all existing line items

        $j = 0;
        foreach ($gift_cards as $gift_card) {


            // Skip if gift card data is malformed
            if (!isset($gift_card['sku']) || !isset($gift_card['price'])) {
                continue;
            }

            $product = wc_get_product(wc_get_product_id_by_sku($gift_card['sku']));
            if (!$product)
                continue;

            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity(1);
            $item->set_total(floatval($gift_card['price'])); // Ensure correct pricing
            // pr($personalise_all_checkbox);
            // Generate a unique gift card number
            $unique_gift_card_number = generate_unique_gift_card_code();

            // Encrypt gift card number before saving to order item meta (match place order behavior)
            $encrypted_gift_card_number = $unique_gift_card_number; // fallback if encryption unavailable/fails
            if (function_exists('encrypt_giftcard_no')) {
                try {
                    $maybe_encrypted = encrypt_giftcard_no($unique_gift_card_number);
                    if (!empty($maybe_encrypted)) {
                        $encrypted_gift_card_number = $maybe_encrypted;
                    }
                } catch (Exception $e) {
                    // keep fallback
                }
            }
            $gift_card_selected = ($personalise_all_checkbox === 'yes') ? 1 : (isset($gift_card['selected']) ? (int) $gift_card['selected'] : 0);

            // Add item meta
            $item->add_meta_data('_recipient_name', $full_name);
            $item->add_meta_data('_recipient_email', $email);
            $item->add_meta_data('_recipient_phone', $phone);
            $item->add_meta_data('_delivery_method', $delivery_method);
            // $item->add_meta_data('_gift_card_number', $unique_gift_card_number);
            $item->add_meta_data('_gift_card_number_enc', $encrypted_gift_card_number);
            $item->add_meta_data('_gift_card_title', sanitize_text_field($gift_card['title']));
            $item->add_meta_data('_gift_card_sku', sanitize_text_field($gift_card['sku']));
            $item->add_meta_data('_gift_card_price', floatval($gift_card['price']));
            $item->add_meta_data('gift_message', sanitize_text_field($gift_card['gift_message']));
            $item->add_meta_data('gift_subject', sanitize_text_field($gift_card['gift_subject']));
            $item->add_meta_data('gift_text_animation', esc_url_raw($gift_card['gift_text_animation']));
            $item->add_meta_data('gift_email_animation', esc_url_raw($gift_card['gift_email_animation']));
            $item->add_meta_data('gift_text_message', sanitize_text_field($gift_card['gift_text_message']));
            $item->add_meta_data('_gift_card_image', esc_url_raw($gift_card['image']));
            $item->update_meta_data('_personalise_all_checkbox', $personalise_all_checkbox);
            $item->update_meta_data('_gift_card_selected', $gift_card_selected);


            // Add item to order
            $order->add_item($item);
            $item->save();

            // Create the display format
            $key = '<strong>' . esc_html($full_name) . '</strong> (' . esc_html($email) . ')';
            $edit_link = admin_url('post.php?post=' . $order->get_id() . '&action=edit');
            $temp_str = '<li>';
            $temp_str .= '<br>' . esc_html($gift_card['sku']) . ' - ' . wc_price($gift_card['price']);
            $temp_str .= '<br>Delivery: ' . esc_html($delivery_method);
            $temp_str .= '</li>';

            $recipients_details_arr[$i]['gift_cards'][$j]['sku'] = $gift_card['sku'];
            $recipients_details_arr[$i]['gift_cards'][$j]['price'] = $gift_card['price'];
            $recipients_details_arr[$i]['gift_cards'][$j]['gift_message'] = sanitize_text_field($gift_card['gift_message']);
            $recipients_details_arr[$i]['gift_cards'][$j]['gift_text_animation'] = sanitize_text_field($gift_card['gift_text_animation']);
            $recipients_details_arr[$i]['gift_cards'][$j]['gift_email_animation'] = sanitize_text_field($gift_card['gift_email_animation']);
            $recipients_details_arr[$i]['gift_cards'][$j]['gift_subject'] = sanitize_text_field($gift_card['gift_subject']);
            $recipients_details_arr[$i]['gift_cards'][$j]['gift_text_message'] = sanitize_text_field($gift_card['gift_text_message']);
            $recipients_details_arr[$i]['gift_cards'][$j]['_gift_card_selected'] = $gift_card_selected;

            $recipients_details_arr[$i]['gift_cards'][$j]['product_id'] = $product->get_id();
            $recipients_details_arr[$i]['gift_cards'][$j]['product_image'] = wp_get_attachment_url($product->get_image_id());
            $recipients_details_arr[$i]['gift_cards'][$j]['selected'] = $gift_card_selected;


            $recipients_details[$key][$unique_gift_card_number] = $temp_str;

            // Add gift card line to note
            $title = isset($gift_card['title']) ? $gift_card['title'] : 'Untitled Gift Card';
            $recipient_note .= "  • {$title} - \${$gift_card['price']} (SKU: {$gift_card['sku']})\n";
            $j++;
        }

        $order_summary_notes[] = $recipient_note;
        $i++;
    }
    //pr($recipients_details_arr);

    // Add business details to order meta
    if (!empty($business_details)) {
        $order->update_meta_data('_business_name', sanitize_text_field($business_details['business_name']));
        // If no sender name provided, use the customer's (order placer's) name
        $sender_name_val = !empty(trim((string)($business_details['sender_name'] ?? '')))
            ? sanitize_text_field($business_details['sender_name'])
            : trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($sender_name_val) && $order->get_customer_id()) {
            $customer = get_user_by('id', $order->get_customer_id());
            if ($customer && !empty(trim($customer->display_name ?? ''))) {
                $sender_name_val = trim($customer->display_name);
            }
        }
        $order->update_meta_data('_sender_name', $sender_name_val ?: '');
        $order->update_meta_data('_sender_email', sanitize_text_field($business_details['sender_email']));
        $order->update_meta_data('_campaign', sanitize_text_field($business_details['campaign']));
        $order->update_meta_data('_order_name', sanitize_text_field($business_details['order_name']));
        $order->update_meta_data('_po_number', sanitize_text_field($business_details['po_number']));
        $order->update_meta_data('_additional_reference', sanitize_text_field($business_details['additional_reference']));
        $order->update_meta_data('_client_reference', sanitize_text_field($business_details['client_reference']));
    }

    // Save the raw CSV data
    if (!empty($csv_data)) {
        $order->update_meta_data('_csv_data', wp_json_encode($csv_data));
    }

    // Save the raw form data
    if (!empty($form_data)) {
        $order->update_meta_data('_form_data', wp_json_encode($form_data));
    }

    // Add sender information
    // $order->update_meta_data('_sender_name', $sender_name);
    // $order->update_meta_data('_sender_email', $sender_email);

    // Add order note with summary
    if (!empty($order_summary_notes)) {
        $full_note = "Draft Order Summary:\n" . implode("\n", $order_summary_notes);
        $order->add_order_note($full_note, 0); // private note
    }

    // Save recipients details in a format that matches the display function
    $order->update_meta_data('_recipients_details', $recipients_details);
    $order->update_meta_data('_recipients_details_arr', $recipients_details_arr);

    $order->calculate_totals();
    $order->save();

    wp_send_json_success([
        'order_id' => $order->get_id(),
        'is_update' => $is_update,
        'order_type' => $is_update ? 'update' : 'create',
        'redirect_url' => get_edit_post_link($order->get_id())
    ]);
}
add_action('wp_ajax_save_draft_order_customisation', 'handle_save_custom_draft_order_customisation');

function handle_save_custom_draft_order_customisation()
{
    gcp_require_logged_in_ajax();
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    $recipients = isset($_POST['recipients']) ? json_decode(wp_unslash($_POST['recipients']), true) : [];
    $business_details = isset($_POST['business_details']) ? json_decode(wp_unslash($_POST['business_details']), true) : [];
    $current_step = isset($_POST['current_step']) ? sanitize_text_field($_POST['current_step']) : '';
    $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : '';

    if (empty($recipients)) {
        wp_send_json_error(['message' => 'No recipients found.']);
    }

    if ($order_id && ($order = wc_get_order($order_id)) && is_a($order, 'WC_Order')) {
        $is_update = true;

        foreach ($order->get_items('line_item') as $item_id => $item) {
            $order->remove_item($item_id);
        }
    } else {
        $order = wc_create_order(['status' => 'draft']);
        $is_update = false;
    }

    $order->update_meta_data('_current_step', (int) $current_step);

    // Business user assignment
    // if (!empty($business_details['business_id'])) {
    //  $order->set_customer_id(absint($business_details['business_id']));
    // }


    if (!empty($business_details['business_id'])) {


        $business_user_id = absint($business_details['business_id']);
        $order->set_customer_id($business_user_id);
    }

    // Order meta
    if (!empty($business_details)) {
        foreach ($business_details as $key => $value) {
            $order->update_meta_data('_' . $key, sanitize_text_field($value));
        }
    }

    $order_summary_notes = [];
    $recipients_details = [];
    $recipients_details_arr = [];

    // pr($recipients);
    $i = 0;
    foreach ($recipients as $index => $recipient) {
        $first_name = sanitize_text_field($recipient['first_name']);
        $surname = sanitize_text_field($recipient['surname'] ?? '');
        $email = sanitize_email($recipient['email'] ?? '');
        $phone = sanitize_text_field($recipient['phone'] ?? '');
        $gift_message = sanitize_text_field($recipient['gift_message'] ?? '');
        $delivery_method = sanitize_text_field($recipient['delivery_method'] ?? '');

        if (empty($email) && empty($phone)) {
            continue;
        }

        $full_name = trim($first_name . ' ' . $surname);
        $recipient_note = "Recipient #" . ($index + 1) . ":\n";
        $recipient_note .= "- Name: $full_name\n";
        if (!empty($email)) {
            $recipient_note .= "- Email: $email\n";
        }
        if (!empty($phone)) {
            $recipient_note .= "- Phone: $phone\n";
        }
        $recipient_note .= "- Gift Cards:\n";

        $gift_cards = is_array($recipient['gift_cards']) ? $recipient['gift_cards'] : [];
        $recipients_details_arr[$i] = [
            'first_name' => $first_name,
            'surname' => $surname,
            'email' => $email,
            'phone' => $phone,
            'gift_cards' => []
        ];

        $j = 0;
        foreach ($gift_cards as $card) {
            if (empty($card['sku']) || !isset($card['price']))
                continue;

            $product = wc_get_product(wc_get_product_id_by_sku($card['sku']));
            if (!$product)
                continue;

            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity(1);
            $item->set_total(floatval($card['price']));

            // Generate and add gift card number
            // $unique_gift_card_number = generate_unique_gift_card_code();
            $individual_message = isset($card['message']) ? sanitize_text_field($card['message']) : $gift_message;

            // Add item meta
            $item->add_meta_data('_recipient_name', $full_name);
            $item->add_meta_data('_recipient_email', $email);
            $item->add_meta_data('_recipient_phone', $phone);
            $item->add_meta_data('_gift_message', $individual_message);
            $item->add_meta_data('_delivery_method', $delivery_method);
            $item->add_meta_data('_gift_card_title', sanitize_text_field($card['title']));
            $item->add_meta_data('_gift_card_sku', sanitize_text_field($card['sku']));
            $item->add_meta_data('_gift_card_price', floatval($card['price']));
            $item->add_meta_data('_gift_card_image', esc_url_raw($card['image']));

            $order->add_item($item);
            $item->save();

            // Store structured data
            $recipients_details_arr[$i]['gift_cards'][$j] = [
                'sku' => $card['sku'],
                'price' => $card['price'],
                'product_id' => $product->get_id(),
                'product_image' => wp_get_attachment_url($product->get_image_id()),
                'message' => $individual_message,
            ];

            // Display summary (use $j instead of unique number to prevent overwrite)
            $key = '<strong>' . esc_html($full_name) . '</strong> (' . esc_html($email) . ')';
            if (!isset($recipients_details[$key])) {
                $recipients_details[$key] = [];
            }
            $temp_str = '<li>';
            $temp_str .= '<br>' . esc_html($card['sku']) . ' - ' . wc_price($card['price']);
            $temp_str .= '<br>Message: ' . esc_html($individual_message);
            $temp_str .= '<br>Delivery: ' . esc_html($delivery_method);
            $temp_str .= '</li>';

            $recipients_details[$key][] = $temp_str;



            // Note for order
            $title = isset($card['title']) ? $card['title'] : 'Untitled Gift Card';
            $recipient_note .= "  • {$title} - \${$card['price']} (SKU: {$card['sku']}) - Message: {$individual_message}\n";

            $j++;
        }

        $order_summary_notes[] = $recipient_note;
        $i++;

    }

    if (!empty($order_summary_notes)) {
        $order->add_order_note("Draft Order Summary:\n" . implode("\n", $order_summary_notes), 0);
    }
    // echo'<pre>';
    // echo'</pre>';

    $order->update_meta_data('_recipients_details', $recipients_details);
    $order->update_meta_data('_recipients_details_arr', $recipients_details_arr);

    $order->calculate_totals();
    $order->save();

    wp_send_json_success([
        'order_id' => $order->get_id(),
        'is_update' => $is_update,
    ]);
}

add_action('wp_ajax_check_approved_billing', 'check_approved_billing_callback');

function check_approved_billing_callback()
{
    gcp_require_logged_in_ajax();
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (!$user_id) {
        wp_send_json_error(['message' => 'User ID missing']);
    }

    // Only allow users to check their own billing status, or admins to check any.
    if ( get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $approved = get_user_meta($user_id, 'approved_billing', true);
    // echo $approved;
    wp_send_json_success(['approved' => ($approved === 'yes')]);
}

//Date time code Start

//Date time code End


add_action('save_post_product', 'update_category_acf_on_product_save', 20, 3);
function update_category_acf_on_product_save($post_id, $post, $update)
{
    // Avoid infinite loops / autosaves / revisions
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    // Get all categories assigned to this product
    $categories = wp_get_post_terms($post_id, 'product_cat');
    if (empty($categories) || is_wp_error($categories)) {
        return;
    }

    foreach ($categories as $cat) {
        $term_id = $cat->term_id;

        // Get current repeater rows
        $rows = get_field('sku_assigned_arr', 'product_cat_' . $term_id);
        if (!is_array($rows)) {
            $rows = [];
        }

        $temp_rows = array_column($rows, 'assigned_product');
        if (!in_array($post_id, $temp_rows)) {
            $rows[] = [
                'assigned_product' => $post_id
            ];
        }

        /*// Check if product already exists in repeater
        $already_exists = false;
        foreach ($rows as $row) {
            if (!empty($row['assigned_product']) && intval($row['assigned_product']) === $post_id) {
                $already_exists = true;
                break;
            }
        }

        if ($already_exists) {
            continue; // Skip if product already assigned to category
        }*/

        /*// Find the max rank
        $max_rank = 0;
        foreach ($rows as $row) {
            if (!empty($row['rank']) && intval($row['rank']) > $max_rank) {
                $max_rank = intval($row['rank']);
            }
        }

        // Add new row
        $rows[] = [
            'rank'             => $max_rank + 1,
            'assigned_product' => $post_id
        ];
        */

        // Update the repeater field in category
        update_field('sku_assigned_arr', $rows, 'product_cat_' . $term_id);
    }
}

add_action('edited_product_cat', 'on_product_category_updated', 10, 2);
function on_product_category_updated($term_id, $tt_id)
{
    // Runs when a product category is edited in admin or programmatically
    // $term_id = term ID
    // $tt_id = term taxonomy ID

    // Example: Get the products assigned to this category
    $rows = get_field('sku_assigned_arr', 'product_cat_' . $term_id); // 1,2
    $temp_rows = array();
    if (!empty($rows) && is_array($rows)) {
        $temp_rows = array_unique(array_column($rows, 'assigned_product')); // 1,2
    }

    $products = get_posts([ // 3,4
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id,
            ]
        ]
    ]);

    foreach ($products as $product_id) {
        wp_remove_object_terms($product_id, $term_id, 'product_cat');
    }


    /*echo '<pre>';

    $rows = array();
    if (!empty($temp_rows)) {
        foreach ($temp_rows as $key => $value) {
            $rows[] = [
                'assigned_product' => $value
            ];
            wp_set_object_terms($value, $term_id, 'product_cat', true);
        }
    }

    delete_field('sku_assigned_arr', 'product_cat_' . $term_id);

    /*echo '<pre>';

    if (!empty($rows)) {
        update_field('sku_assigned_arr', $rows, 'product_cat_' . $term_id);
    }

    /*foreach ($products as $product_id) {
        $temp_key = array_search($product_id, $temp_rows, true);
        if ($temp_key !== false) {
            unset($temp_rows[$temp_key]);
        }
    }

    if( !empty($temp_rows) ){
        foreach ($temp_rows as $trkey => $trvalue) {
            wp_remove_object_terms($trvalue, $term_id, 'product_cat');
        }
    }*/
}

define('SALE_EXPIRY_HOOK', 'my_product_sale_expiry_hook');

function schedule_single_sale_expiry_event($product_id)
{

    // Get the product object.
    $product = wc_get_product($product_id);
    if (!$product) {
        return;
    }

    $args = array('product_id' => $product_id);
    if (wp_next_scheduled(SALE_EXPIRY_HOOK, $args)) {
        wp_clear_scheduled_hook(SALE_EXPIRY_HOOK, $args);
    }

    // Get the sale end time string from product meta.
    $sale_end_utc_string = $product->get_meta('_discount_valid_to');

    // If the sale end time is set and is in the future...
    if (!empty($sale_end_utc_string)) {

        $sale_end_timestamp = strtotime($sale_end_utc_string);
        $current_utc_timestamp = current_time('timestamp', true);

        // Schedule the event only if the end time is in the future.
        if ($sale_end_timestamp > $current_utc_timestamp) {
            wp_schedule_single_event($sale_end_timestamp, SALE_EXPIRY_HOOK, $args);
        }
    }
}

//add_action('woocommerce_new_product', 'schedule_single_sale_expiry_event');
//add_action('woocommerce_update_product', 'schedule_single_sale_expiry_event');

function giftcard_expire_single_product_sale($product_id)
{

    $product = wc_get_product($product_id);

    // Double-check that the product exists and is still on sale.
    if ($product && $product->is_on_sale()) {
        // Remove the sale price and sale dates.
        $product->set_sale_price('');
        $product->set_date_on_sale_from('');
        $product->set_date_on_sale_to('');
        $product->save();

        // Also set the ACF field to "no"
        if (function_exists('update_field')) {
            update_field('field_67f3a79417f64', 'No', $product_id);
        } else {
            update_post_meta($product_id, 'field_67f3a79417f64', 'No');
        }
        clear_schedule_event($product_id, SALE_EXPIRY_HOOK);
    }
}

add_action(SALE_EXPIRY_HOOK, 'giftcard_expire_single_product_sale');


/*
a,b,continue

$car = explode(',', string)
$cat_rows = array();
$max_rank = 0;
delete_field('sku_assigned_arr', 'product_cat_' . $term_id);
foreach ($variable as $key => $value) {
    $prod_id = get_id_by_sku($value);
    $cat_rows[] = [
        'rank'             => $max_rank + 1,
        'assigned_product' => $prod_id
    ];
}

if( !empty($cat_rows) ){
    update_field('sku_assigned_arr', $rows, 'product_cat_' . $term_id);
}*/

// Add this in your plugin or theme's functions.php

add_action('wp_ajax_get_all_brands', 'fetch_all_brands_callback');

function fetch_all_brands_callback()
{
    gcp_require_admin_ajax();
    global $wpdb;

    $brands = get_terms([
        'taxonomy' => 'product_brand',
        'hide_empty' => false,
    ]);

    $brand_names = [];
    if (!is_wp_error($brands)) {
        foreach ($brands as $brand) {
            $brand_names[] = $brand->name;
        }
    }

    // Return JSON response
    wp_send_json($brand_names);
    wp_die();
}
// add_action('wp_ajax_get_bu_user_order_history', 'get_bu_user_order_history_callback');
add_action('wp_ajax_get_bu_user_order_history', 'get_user_order_history_callback');
function get_user_order_history_callback()
{
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $export_all = isset($_POST['export_all']) && $_POST['export_all'] == true;

    $o_number = isset($_POST['o_number']) ? sanitize_text_field($_POST['o_number']) : '';
    // $o_date     = isset($_POST['o_date']) ? sanitize_text_field($_POST['o_date']) : '';  
    $o_name = isset($_POST['o_name']) ? sanitize_text_field($_POST['o_name']) : '';
    $o_user = isset($_POST['o_user']) ? sanitize_text_field($_POST['o_user']) : '';
    $o_status = isset($_POST['o_status']) ? sanitize_text_field($_POST['o_status']) : '';
    $o_invoice = isset($_POST['o_invoice']) ? sanitize_text_field($_POST['o_invoice']) : '';
    $o_payment = isset($_POST['o_payment']) ? sanitize_text_field($_POST['o_payment']) : '';
    $o_total = isset($_POST['o_total']) ? sanitize_text_field($_POST['o_total']) : '';
    $o_campaign = isset($_POST['o_campaign']) ? sanitize_text_field($_POST['o_campaign']) : '';
    $o_client_ref = isset($_POST['o_client_ref']) ? sanitize_text_field($_POST['o_client_ref']) : '';
    $o_po = isset($_POST['o_po']) ? sanitize_text_field($_POST['o_po']) : '';
    $o_date_from = isset($_POST['o_date_from']) ? sanitize_text_field($_POST['o_date_from']) : '';
    $o_date_to = isset($_POST['o_date_to']) ? sanitize_text_field($_POST['o_date_to']) : '';

    // $o_status   = isset($_POST['o_status']) ? sanitize_text_field($_POST['o_status']) : '';
    $o_status_array = !empty($o_status) ? explode(',', strtolower($o_status)) : [];


    if (!$user_id) {
        wp_send_json(['data' => []]);
    }


    // Get orders for this user
    $args = [
        'limit' => $export_all ? -1 : 10, // -1 for all orders
        'customer' => $user_id,
        'orderby' => 'date',
        'order' => 'DESC',
    ];


    // Add status filter if provided
    if (!empty($o_status_array)) {
        $args['status'] = $o_status_array;
    }

    if ($o_date_from || $o_date_to) {
        $date_query = [
            'inclusive' => true,
            'column' => 'post_date'
        ];

        if ($o_date_from)
            $date_query['after'] = $o_date_from . ' 00:00:00';
        if ($o_date_to)
            $date_query['before'] = $o_date_to . ' 23:59:59';

        $args['date_query'] = [$date_query];
    }



    // For pagination if not exporting all
    if (!$export_all && isset($_POST['start'], $_POST['length'])) {
        $args['offset'] = intval($_POST['start']);
        $args['limit'] = intval($_POST['length']);
    }

    $orders = wc_get_orders($args);

    $data = [];

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $order_name = $order->get_meta('_order_name');
        $invoice = $order->get_meta('_invoice_number');
        $campaign = $order->get_meta('_campaign');
        $po_number = $order->get_meta('_po_number');
        // Get the actual WP user
        $user_id = $order->get_user_id();
        $user_obj = $user_id ? get_userdata($user_id) : null;
        $user_name = $user_obj ? $user_obj->display_name : 'Guest';
        $client_ref = $order->get_meta('_client_reference');

        // Apply text-based filters manually (like SQL LIKE)
        if ($o_number && stripos((string) $order_id, $o_number) === false)
            continue;
        if ($o_name && stripos((string) $order_name, $o_name) === false)
            continue;
        if ($o_user && stripos($user_name, $o_user) === false)
            continue;
        if ($o_invoice && stripos((string) $invoice, $o_invoice) === false)
            continue;
        if ($o_payment && stripos((string) $order->get_status(), $o_payment) === false)
            continue;
        if ($o_total && stripos((string) $order->get_total(), $o_total) === false)
            continue;
        if ($o_campaign && stripos((string) $campaign, $o_campaign) === false)
            continue;
        if ($o_client_ref && stripos((string) $client_ref, $o_client_ref) === false)
            continue;
        if ($o_po && stripos((string) $po_number, $o_po) === false)
            continue;

        $data[] = [
            'order_id' => $order_id,
            'order_date' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
            'order_name' => $order_name,
            'user_name' => $user_name,
            'order_status' => wc_get_order_status_name($order->get_status()),
            'invoice_number' => $invoice,
            'payment_status' => $order->get_status(),
            'total' => $order->get_total(),
            'campaign' => $campaign,
            'client_reference' => $client_ref,
            'po_number' => $po_number,
        ];
    }

    wp_send_json(['data' => $data]);
}

// Register the AJAX actions for logged-in and logged-out users
add_action('wp_ajax_get_all_pro_status', 'get_all_pro_status_callback');

function get_all_pro_status_callback()
{
    gcp_require_admin_ajax();
    global $wp_post_statuses;

    if (empty($wp_post_statuses)) {
        $wp_post_statuses = get_post_stati(array(), 'objects');
    }

    $status_list = array();

    foreach ($wp_post_statuses as $status => $obj) {
        if (in_array($status, array('publish', 'wc-deactivated', 'draft', 'pending', 'closed'))) {
            $status_list[$status] = $obj->label;
        }
    }

    wp_send_json_success($status_list);
    wp_die();
}

add_action('wp_ajax_get_all_user_roles', function () {
    // Get all WordPress roles
    $roles = wp_roles()->roles;
    $exclude = ['editor', 'author', 'contributor', 'subscriber', 'customer', 'shop_manager'];

    // Prepare an array of role slug => role name
    $all_roles = [];
    foreach ($roles as $slug => $details) {
        if (!in_array($slug, $exclude)) {
            $all_roles[$slug] = $details['name'];
        }
    }
    wp_send_json_success($all_roles);
});


// Get all unique business names from usermeta
add_action('wp_ajax_get_all_business_names', function () {
    global $wpdb;
    $results = $wpdb->get_col("
        SELECT DISTINCT meta_value 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'business_name' 
        AND meta_value != '' 
        ORDER BY meta_value ASC
    ");
    wp_send_json_success($results);
});
add_action('wp_ajax_check_user_role', 'check_user_role_callback');

function check_user_role_callback()
{
    gcp_require_logged_in_ajax();
    if (isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);

        if ($user) {
            $user_roles = $user->roles; // array of all roles

            // Allowed roles
            $allowed_roles = [
                'business_user',
                'external_business_admin',
                'external_business_viewer',
                'administrator'
            ];

            // Check if user has any allowed role
            $has_allowed_role = false;
            foreach ($user_roles as $role) {
                if (in_array($role, $allowed_roles)) {
                    $has_allowed_role = true;
                    break;
                }
            }

            // Send true/false in response
            wp_send_json_success(['has_allowed_role' => $has_allowed_role]);
        } else {
            wp_send_json_error(['message' => 'User not found']);
        }
    } else {
        wp_send_json_error(['message' => 'No user ID provided']);
    }

    wp_die();
}

add_action('wp_ajax_get_all_businesses', 'get_all_businesses_callback');

function get_all_businesses_callback()
{
    gcp_require_admin_ajax();
    // Security check (optional)
    // check_ajax_referer('your_nonce_name', 'nonce', false);
    $float_table = isset($_GET['floatTable']) ? intval($_GET['floatTable']) : 0;

    $args = array(
        'role' => 'business_user',
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => array('display_name', 'ID'),
    );

    if (!empty($float_table) && intval($float_table) === 1) {
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => 'approved_billing',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => 'approved_billing',
                'value' => 'no',
                'compare' => '=',
            ),
        );
    }


    $users = get_users($args);
    $business_names = array();

    if (!empty($users)) {
        foreach ($users as $user) {
            $business_names[] = $user->display_name;
        }
    }
    wp_send_json($business_names);
}


add_action('wp_ajax_check_bhn_product', 'check_bhn_product_callback');
add_action('wp_ajax_nopriv_check_bhn_product', 'check_bhn_product_callback');

function check_bhn_product_callback()
{
    check_ajax_referer( 'gc_nonce', 'security' );
    $checkBhnSkus = isset($_POST['checkBhnSkus']) ? (array) $_POST['checkBhnSkus'] : [];



    if (empty($checkBhnSkus)) {
        wp_send_json_error(['message' => 'SKU missing']);
    }

    $results = [];

    foreach ($checkBhnSkus as $sku) {
        $sku = sanitize_text_field($sku);
        $product_id = wc_get_product_id_by_sku($sku);

        if (!$product_id) {
            $results[] = [
                'sku' => $sku,
                'bhn_pro' => false,
                'message' => 'No product found for this SKU',
            ];
            continue;
        }

        $meta_value = get_post_meta($product_id, '_is_blackhawk_product', true);
        $expected_value = 'yes_' . $product_id;

        $results[] = [
            'sku' => $sku,
            'product_id' => $product_id,
            'bhn_pro' => ($meta_value === $expected_value),
        ];
    }

    wp_send_json_success($results);
}

add_action('wp_ajax_get_user_transactions', 'get_user_transactions');

function get_user_transactions()
{
    gcp_require_admin_ajax();
    global $wpdb;

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $table = $wpdb->prefix . 'user_float_transactions';

    // Build the query and store it as a string
    $sql = $wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
        $user_id
    );

    // Log the exact SQL query

    // Run the query
    $results = $wpdb->get_results($sql);

    // Log the result count and data


    // pr($results);
    // (Optional) Debug output to browser during development

    ob_start();

    if ($results) {

        foreach ($results as $txn) {

            $created_at = $txn->created_at; // Usually in Y-m-d H:i:s
            $display_date = wp_date('d/m/Y H:i', strtotime($created_at));
            echo '<tr>';
            echo '<td data-order="' . esc_attr(wp_date('Y-m-d H:i:s', strtotime($created_at))) . '">' . esc_html($display_date) . '</td>';
            echo '<td class="' . esc_attr(strtolower($txn->balance_type)) . '"><span>' . esc_html($txn->balance_type) . '</span></td>';
            echo '<td>' . esc_html($txn->reason) . '</td>';
            echo '<td>' . esc_html($txn->order) . '</td>';
            echo '<td>' . esc_html($txn->invoice) . '</td>';
            echo '<td class="status ' . esc_attr(strtolower($txn->status)) . '"><span class="status-label status-' . esc_attr(strtolower($txn->status)) . '">' . esc_html($txn->status) . '</span></td>';
            echo '<td>' . esc_html($txn->changed_amount) . '</td>';
            echo '<td>' . esc_html($txn->reference) . '</td>';
            echo '<td>' . esc_html($txn->new_balance) . '</td>';
            echo '</tr>';
        }
    }

    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'user_id' => $user_id,
    ]);
}

add_action('wp_ajax_export_float_billing_transactions', 'handle_float_billing_export');

// Exports the Float & Billing statement table (wp_user_float_transactions) for a
// single business user as a CSV, streamed directly — same data source and column
// mapping as get_user_transactions() above, which renders the on-screen table.
function handle_float_billing_export()
{
    gcp_require_admin_ajax();
    check_ajax_referer('user_admin_nonce', 'nonce');

    global $wpdb;
    $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    if (!$user_id || !get_userdata($user_id)) {
        wp_die('Invalid user ID');
    }

    $table = $wpdb->prefix . 'user_float_transactions';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
        $user_id
    ));

    $rows = [];
    foreach ($results as $txn) {
        // Apply the same search filter as the on-screen table (balance type,
        // action/reason, order, invoice, status, reference).
        if ($search !== '') {
            $haystacks = [$txn->balance_type, $txn->reason, $txn->order, $txn->invoice, $txn->status, $txn->reference];
            $matched = false;
            foreach ($haystacks as $haystack) {
                if (stripos((string) $haystack, $search) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
        }

        $rows[] = [
            'date_time'      => wp_date('d/m/Y H:i', strtotime($txn->created_at)),
            'balance_type'   => $txn->balance_type,
            'action'         => $txn->reason,
            'order'          => $txn->order,
            'invoice'        => $txn->invoice,
            'status'         => $txn->status,
            'amount'         => $txn->changed_amount,
            'reference'      => $txn->reference,
            'balance'        => $txn->new_balance,
        ];
    }

    // Discard any stray output already buffered by earlier hooks in this request
    // so nothing precedes the CSV content itself.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="float-billing-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'w');

    // Guard against formula injection (e.g. a reference saved as "=cmd(...)")
    // being auto-executed when the file is opened in Excel/Sheets.
    $csv_safe = function ($value) {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '@'], true)) {
            $value = "'" . $value;
        }
        return $value;
    };

    fputcsv($output, ['Date/Time', 'Balance Type', 'Action', 'Order', 'Invoice', 'Status', 'Amount', 'Reference', 'Balance']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $csv_safe($row['date_time']),
            $csv_safe($row['balance_type']),
            $csv_safe($row['action']),
            $csv_safe($row['order']),
            $csv_safe($row['invoice']),
            $csv_safe($row['status']),
            $csv_safe($row['amount']),
            $csv_safe($row['reference']),
            $csv_safe($row['balance']),
        ]);
    }

    fclose($output);
    exit;
}


function gc_search_products()
{
    global $wpdb;

    check_ajax_referer('gc_nonce', 'security');

    $query = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';

    // Get filter values (for brands page)
    $giftcards_for = isset($_POST['giftcards_for']) ? $_POST['giftcards_for'] : array();
    $occasion = isset($_POST['occasion']) ? $_POST['occasion'] : array();
    $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : '';

    // Convert to arrays if they're strings
    if (!is_array($giftcards_for) && !empty($giftcards_for)) {
        $giftcards_for = array($giftcards_for);
    }
    if (!is_array($occasion) && !empty($occasion)) {
        $occasion = array($occasion);
    }

    // Remove empty values
    $giftcards_for = array_filter(array_map('sanitize_text_field', (array) $giftcards_for));
    $occasion = array_filter(array_map('sanitize_text_field', (array) $occasion));

    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 8,
        'meta_query' => array(
            array(
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => '!=',
            ),
        ),
    );

    // Build taxonomy query for filters
    $tax_query = array('relation' => 'AND');

    if (!empty($giftcards_for)) {
        $tax_query[] = array(
            'taxonomy' => 'product_tag',
            'field' => 'slug',
            'terms' => $giftcards_for,
            'operator' => 'IN',
        );
    }

    if (!empty($occasion)) {
        $tax_query[] = array(
            'taxonomy' => 'product_tag',
            'field' => 'slug',
            'terms' => $occasion,
            'operator' => 'IN',
        );
    }

    // Add tax_query if we have filters
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    // Search: gift card name, brand, tag, description, denomination, category
    if (!empty($query)) {
        $search_like = '%' . $wpdb->esc_like($query) . '%';
        $callback = null;
        $callback = function ($where) use ($search_like, $wpdb, &$callback) {
            if ($callback) {
                remove_filter('posts_where', $callback, 10);
            }
            return $where . gc_product_search_where_clause($search_like, $wpdb);
        };
        add_filter('posts_where', $callback, 10, 1);
    }

    // Handle sorting
    if (!empty($sort)) {
        switch ($sort) {
            case 'ranked':
                $args['orderby'] = 'menu_order';
                $args['order'] = 'ASC';
                break;

            case 'most_popular':
            case 'bestselling':
                $args['meta_key'] = 'total_sales';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;

            case 'a_z':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;

            case 'z_a':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;

            case 'price_low_high':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'ASC';
                break;

            case 'price_high_low':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'DESC';
                break;

            case 'most_viewed':
                $args['meta_key'] = 'post_views_count';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;

            case 'newest':
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;

            default:
                $args['orderby'] = 'menu_order';
                $args['order'] = 'ASC';
                break;
        }
    } else {
        $args['orderby'] = 'menu_order';
        $args['order'] = 'ASC';
    }

    $loop = new WP_Query($args);

    // Remove the filter after query to avoid affecting other queries
    if (!empty($query)) {
        remove_all_filters('posts_where');
    }

    echo '<div class="gc-slide">';

    if ($loop->have_posts()) {
        while ($loop->have_posts()) {
            $loop->the_post();
            global $product;
            $product_id = get_the_ID();


            $tags = wp_get_post_terms(get_the_ID(), 'product_tag');

            $tag_elements = array();
            $hidden_tags  = array();

            if ($tags && !is_wp_error($tags)) {
                foreach ($tags as $index => $t) {

                    $tag_lower = strtolower(trim($t->name));

                    $modifier = (strpos($tag_lower, '20% off') !== false)
                        ? 'product-tag--off'
                        : ((strpos($tag_lower, 'hot offer') !== false)
                            ? 'product-tag--offer'
                            : 'product-tag--default');

                    $tag_html = '<span class="product-tag ' . esc_attr($modifier) . '">' . esc_html($t->name) . '</span>';

                    if ($index < 3) {
                        $tag_elements[] = $tag_html;
                    } else {
                        $hidden_tags[] = $tag_html;
                    }
                }
            }

            $tags_html = '';

            if (!empty($tag_elements)) {
                $tags_html .= '<p class="gc-product-tags product-tags-wrap">';
                $tags_html .= implode('', $tag_elements);

                if (!empty($hidden_tags)) {
                    $tags_html .= '<span class="product-tag-more">...</span>';
                    $tags_html .= '<span class="product-tag-hidden">' . implode('', $hidden_tags) . '</span>';
                }

                $tags_html .= '</p>';
            }

            echo '<a href="' . esc_url(get_permalink($product_id)) . '"  class="product-card-link" style="text-decoration:none;color:inherit;display:block;">
                    <div class="gc-card">
                        <div class="gc-img">' . $product->get_image() . '</div>
                        <p class="gc-title">' . get_the_title() . '</p>
                        ' . $tags_html . '
                    </div>
                </a>   
            ';
        }
    } else {
            echo '<p>No Cards found.</p>';
    }

    echo '</div>';

    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_gc_search_products', 'gc_search_products');
add_action('wp_ajax_nopriv_gc_search_products', 'gc_search_products');



/**
 * Predictive search suggestions for eGift Cards (name, brand, tag, description, denomination, category).
 * Returns products and optionally term names for dropdown suggestions.
 */
function gc_search_suggestions_handler() {
    $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    if (strlen($query) < 2) {
        wp_send_json_success(array('products' => array(), 'terms' => array()));
    }
    $page_name = 'other';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $ref = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        if (strpos($ref, 'my-wishlist') !== false) {
            $page_name = 'wishlist';
        } elseif (strpos($ref, '/offers') !== false) {
            $page_name = 'offers';
        } elseif (strpos($ref, '/product-category/') !== false) {
            $page_name = 'category';
        } elseif (strpos($ref, '/brands') !== false && strpos($ref, '/product-brand/') === false) {
            $page_name = 'brands';
        }
    }
    gc_log_search($query, 'suggestion', $page_name);
    global $wpdb;
    $search_like = '%' . $wpdb->esc_like($query) . '%';
    $limit = 10;

    $args = array(
        'post_type'   => 'product',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'fields'      => 'ids',
        'meta_query'  => array(
            array('key' => '_stock_status', 'value' => 'outofstock', 'compare' => '!='),
        ),
    );
    $callback = null;
    $callback = function ($where) use ($search_like, $wpdb, &$callback) {
        if ($callback) {
            remove_filter('posts_where', $callback, 10);
        }
        return $where . gc_product_search_where_clause($search_like, $wpdb);
    };
    add_filter('posts_where', $callback, 10, 1);
    $q = new WP_Query($args);
    remove_all_filters('posts_where');
    $product_ids = $q->have_posts() ? $q->posts : array();
    wp_reset_postdata();

    $products = array();
    foreach (array_slice($product_ids, 0, $limit) as $pid) {
        $product = wc_get_product($pid);
        if (!$product) {
            continue;
        }
        $products[] = array(
            'id'    => $pid,
            'title' => $product->get_name(),
            'url'   => get_permalink($pid),
            'price' => $product->get_price_html(),
        );
    }

    $terms = array();
    $term_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT t.term_id FROM {$wpdb->terms} t " .
        "INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id " .
        "WHERE tt.taxonomy IN ('product_cat', 'product_tag', 'product_brand' ,'icons') AND t.name LIKE %s " .
        "LIMIT 5",
        $search_like
    ));
    if (!empty($term_ids)) {
        foreach ($term_ids as $tid) {
            $term = get_term($tid);
            if ($term && !is_wp_error($term)) {
                $link = get_term_link($term);
                $terms[] = array('name' => $term->name, 'url' => is_wp_error($link) ? '' : $link);
            }
        }
    }

    wp_send_json_success(array('products' => $products, 'terms' => $terms));
}
add_action('wp_ajax_gc_search_suggestions', 'gc_search_suggestions_handler');
add_action('wp_ajax_nopriv_gc_search_suggestions', 'gc_search_suggestions_handler');


/**
 * Admin: Search logs menu and page (view + export CSV).
 */
add_action('admin_menu', 'gc_search_logs_admin_menu');
function gc_search_logs_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __('eGift Card Search Logs', 'twentytwentyone'),
        __('Search Logs', 'twentytwentyone'),
        'manage_woocommerce',
        'gc-search-logs',
        'gc_search_logs_admin_page'
    );
}
add_action('admin_post_gc_export_search_logs', 'gc_export_search_logs_csv');
function gc_export_search_logs_csv() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to export search logs.', 'twentytwentyone'));
    }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'gc_export_search_logs')) {
        wp_die(esc_html__('Invalid request.', 'twentytwentyone'));
    }
    global $wpdb;
    $table = $wpdb->prefix . 'gc_search_logs';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        wp_die(esc_html__('Search logs table not found.', 'twentytwentyone'));
    }
    $rows = $wpdb->get_results("SELECT id, search_term, user_id, ip, context, page_name, created_at FROM {$table} ORDER BY created_at DESC", ARRAY_A);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gc-search-logs-' . wp_date('Y-m-d-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('ID', 'Search term', 'User ID', 'IP', 'Context', 'Page', 'Date'));
    foreach ($rows as $row) {
        fputcsv($out, array($row['id'], $row['search_term'], $row['user_id'], $row['ip'], $row['context'], isset($row['page_name']) ? $row['page_name'] : '', $row['created_at']));
    }
    fclose($out);
    exit;
}
function gc_search_logs_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'gc_search_logs';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        echo '<div class="wrap"><p>' . esc_html__('Search logs table not found. Save a search on the front end to create it.', 'twentytwentyone') . '</p></div>';
        return;
    }
    if (isset($_POST['gc_search_logs_save']) && current_user_can('manage_woocommerce') && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'gc_search_logs_settings')) {
        $enabled = !empty($_POST['gc_search_logs_enabled']) ? 1 : 0;
        update_option('gc_search_logs_enabled', $enabled);
        echo '<div class="notice notice-success"><p>' . esc_html($enabled ? __('Search logging enabled.', 'twentytwentyone') : __('Search logging disabled.', 'twentytwentyone')) . '</p></div>';
    }
    $logging_enabled = (int) get_option('gc_search_logs_enabled', 0);
    $per_page = 50;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT id, search_term, user_id, ip, context, page_name, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ), ARRAY_A);
    $export_url = add_query_arg(array('action' => 'gc_export_search_logs'), admin_url('admin-post.php'));
    $export_url = wp_nonce_url($export_url, 'gc_export_search_logs');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('eGift Card Search Logs', 'twentytwentyone'); ?></h1></br>
        <form method="post" action="" style="margin-bottom: 20px;">
            <?php wp_nonce_field('gc_search_logs_settings', '_wpnonce'); ?>
            <label><input type="checkbox" name="gc_search_logs_enabled" value="1" <?php checked($logging_enabled, 1); ?> /> <?php esc_html_e('Save search terms to database', 'twentytwentyone'); ?></label>
            <input type="submit" name="gc_search_logs_save" class="button button-secondary" value="<?php esc_attr_e('Save', 'twentytwentyone'); ?>" />
        </form>
        <p>
            <a href="<?php echo esc_url($export_url); ?>" class="button button-primary"><?php esc_html_e('Export CSV', 'twentytwentyone'); ?></a>
        </p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'twentytwentyone'); ?></th>
                    <th><?php esc_html_e('Search term', 'twentytwentyone'); ?></th>
                    <th><?php esc_html_e('User ID', 'twentytwentyone'); ?></th>
                    <th><?php esc_html_e('IP', 'twentytwentyone'); ?></th>
                    <th><?php esc_html_e('Context', 'twentytwentyone'); ?></th>
                    <th><?php esc_html_e('Page', 'twentytwentyone'); ?></th>
                    <th><?php esc_html_e('Date', 'twentytwentyone'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)) : ?>
                    <tr><td colspan="7"><?php esc_html_e('No search logs yet.', 'twentytwentyone'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($items as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row['id']); ?></td>
                            <td><?php echo esc_html($row['search_term']); ?></td>
                            <td><?php echo esc_html($row['user_id']); ?></td>
                            <td><?php echo esc_html($row['ip']); ?></td>
                            <td><?php echo esc_html($row['context']); ?></td>
                            <td><?php echo esc_html(isset($row['page_name']) ? $row['page_name'] : ''); ?></td>
                            <td><?php echo esc_html($row['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        if ($total > $per_page) {
            $total_pages = ceil($total / $per_page);
            echo '<p class="tablenav"><span class="displaying-num">' . esc_html(sprintf(__('%s items', 'twentytwentyone'), number_format_i18n($total))) . '</span>';
            echo ' &nbsp; ';
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i === $page) {
                    echo '<span class="pagination-links current">' . (int) $i . '</span> ';
                } else {
                    echo '<a class="pagination-links" href="' . esc_url(add_query_arg('paged', $i)) . '">' . (int) $i . '</a> ';
                }
            }
            echo '</p>';
        }
        ?>
    </div>
    <?php
}

// Resolve SKUs (CSV upload): receive list of skus and optional rank; return resolved objects
add_action('wp_ajax_gc_resolve_skus', 'gc_resolve_skus_ajax');
add_action('wp_ajax_nopriv_gc_resolve_skus', 'gc_resolve_skus_ajax');
function gc_resolve_skus_ajax()
{

    if (ob_get_length()) {
        ob_clean();
    }

    if (empty($_POST['items'])) {
        wp_send_json_error('No items provided');
    }

    $items = json_decode(wp_unslash($_POST['items']), true);

    global $wpdb;
    $resolved = [];

    $parent_skus = [];   // store parent SKUs
    $unpublished_skus = [];  // Product exists but not published
    $missing_skus = [];  // store missing SKUs

    foreach ($items as $it) {
        $sku = sanitize_text_field($it['sku'] ?? '');
        $rank = intval($it['rank'] ?? null);

        if (!$sku)
            continue;

        // Check SKU exists
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE pm.meta_key = '_sku'
               AND pm.meta_value = %s
               AND p.post_type = 'product'
             LIMIT 1",
            $sku
        ));


        if (!$product_id) {
            $missing_skus[] = $sku;
            continue;
        }

        $status = get_post_status($product_id);
        if ($status !== 'publish') {
            $unpublished_skus[] = $sku;
            continue;
        }

        $sku_type = strtolower(get_field('sku_type', $product_id));

        $title = get_post_field('post_title', $product_id);
        $terms = wp_get_post_terms($product_id, 'product_brand');
        $brand = (!empty($terms) && !is_wp_error($terms)) ? $terms[0]->name : '';

        $resolved[] = [
            'sku' => $sku,
            'title' => $title,
            'brand' => $brand,
            'product_id' => intval($product_id),
            'rank' => $rank,
            'is_parent' => ($sku_type === 'parent')
        ];

        continue;
    }

    $invalid_messages = [];
    if (!empty($missing_skus)) {
        $invalid_messages[] =
            "SKU(s) <strong>" . implode(', ', $missing_skus) . "</strong> do NOT exist.";
    }

    if (!empty($unpublished_skus)) {
        $invalid_messages[] =
            "SKU(s) <strong>" . implode(', ', $unpublished_skus) . "</strong> exist but are NOT published.";
    }

    if (!empty($parent_skus)) {
        $invalid_messages[] =
            "SKU(s) <strong>" . implode(', ', $parent_skus) . "</strong> are PARENT cards and were skipped.";
    }


    wp_send_json_success([
        'valid' => $resolved,
        'invalid' => $invalid_messages
    ]);

    wp_die();

}

/**
 * Register custom My Account endpoints
 */
function gc_custom_register_myaccount_endpoints()
{

    add_rewrite_endpoint('my-wallet', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('my-preferences', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('my-wishlist', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('my-reminders', EP_ROOT | EP_PAGES);
}
add_action('init', 'gc_custom_register_myaccount_endpoints');

/**
 * Load templates for custom endpoints
 */
add_action('woocommerce_account_my-wallet_endpoint', function () {
    wc_get_template('myaccount/my-wallet.php');
});

add_action('woocommerce_account_my-preferences_endpoint', function () {
    wc_get_template('myaccount/my-preferences.php');
});

add_action('woocommerce_account_my-wishlist_endpoint', function () {
    wc_get_template('myaccount/my-wishlist.php');
});

add_action('woocommerce_account_my-reminders_endpoint', function () {
    wc_get_template('myaccount/my-reminders.php');
});

/**
 * Add title before My Account navigation
 */
add_action('woocommerce_before_account_navigation', function () {
    global $wp;

    // Check if we're on the view-order page
    $is_view_order_page = isset($wp->query_vars['view-order']);

    // If on view-order page, don't show title/content
    if ($is_view_order_page) {
        return;
    }
    $my_account_url = trim(wc_get_page_permalink('myaccount'), '/');
    $current_url = trim(home_url(add_query_arg([], $_SERVER['REQUEST_URI'])), '/');

    $is_main_account_page = ($current_url === $my_account_url);

    if ($is_main_account_page) {
        echo '<h2 class="myaccount-page-title">My Account</h2>';
        echo '<p class="myaccount-page-content">Manage your account preferences & settings</p>';
    } else {
        echo '<h2 class="myaccount-page-title">Account Settings</h2>';
        echo '<p class="myaccount-page-content">Manage your account preferences & settings</p>';
    }

});



/**
 * Recalculate profile completion when user is created or updated
 */

// 1. When user registers
add_action('user_register', 'recalc_profile_completion_on_user_save');

// 2. When user updates own profile
add_action('personal_options_update', 'recalc_profile_completion_on_user_save');

// 3. When admin updates user's profile
add_action('edit_user_profile_update', 'recalc_profile_completion_on_user_save');
add_action('updated_user_meta', 'recalc_profile_completion_on_user_save');


function recalc_profile_completion_on_user_save($user_id)
{

    if (empty($user_id))
        return;

    $user = get_userdata($user_id);

    /*
    |--------------------------------------------------------------------------
    | CHECKLIST LOGIC (MUST match frontend)
    |--------------------------------------------------------------------------
    */

    // 1. BASIC DETAILS COMPLETED
    $basic_details_done = (
        !empty($user->first_name) &&
        !empty($user->last_name)
    );

    // 2. EMAIL VERIFIED (email exists)
    $email_verified = !empty($user->user_email);



    // // 4. PREFERENCES SAVED
    // $events = get_user_meta($user_id, 'interested_events', true);
    // $hobbies = get_user_meta($user_id, 'hobbies', true);

    // if (is_string($events))  $events  = maybe_unserialize($events);
    // if (is_string($hobbies)) $hobbies = maybe_unserialize($hobbies);

    // $preferences_saved = (
    //     (!empty($events) && is_array($events)) ||
    //     (!empty($hobbies) && is_array($hobbies))
    // );

    // 5. PROFILE PICTURE UPLOADED
    // $profile_picture = get_user_meta($user_id, 'profile_picture', true);
    $phone_added = get_user_meta($user_id, 'mobile', true);
    // $profile_picture_done = !empty($profile_picture);

    /*
    |--------------------------------------------------------------------------
    | CALCULATE PERCENTAGE
    |--------------------------------------------------------------------------
    */
    $checks = [
        $basic_details_done,
        $email_verified,
        $phone_added,

    ];

    $total = count($checks);
    $completed = count(array_filter($checks));

    $percent = ($total > 0) ? floor(($completed / $total) * 100) : 0;

    update_user_meta($user_id, 'profile_completion_percent', $percent);
}


// 6. Save Preferences
add_action('wp_ajax_custom_save_preferences', 'custom_save_preferences_handler');

function custom_save_preferences_handler()
{
    gcp_require_logged_in_ajax();
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'custom_registration')) {
        wp_send_json_error('Security verification failed.');
    }

    if (empty($_POST['email'])) {
        wp_send_json_error('Email is required.');
    }


    $email = sanitize_email($_POST['email']);
    $user = get_user_by('email', $email);

    if (!$user) {
        wp_send_json_error('User not found.');
    }

    $user_id = $user->ID;


    // Save events
    if (!empty($_POST['events'])) {
        $events = array_map('sanitize_text_field', $_POST['events']);
        update_user_meta($user_id, 'interested_events', $events);
    }

    // Save hobbies
    if (!empty($_POST['hobbies'])) {
        $hobbies = array_map('sanitize_text_field', $_POST['hobbies']);
        update_user_meta($user_id, 'hobbies', $hobbies);
    }

    recalc_profile_completion_on_user_save($user_id);

    // Mark registration as complete
    update_user_meta($user_id, 'registration_complete', true);
    update_user_meta($user_id, 'registration_completed_at', current_time('mysql'));

    // Log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    wp_send_json_success('Preferences saved successfully. Registration complete!');
}


/**
 * Step 1: Handle login + send OTP
 */
add_action('wp_ajax_send_login_otp', 'handle_send_login_otp');
add_action('wp_ajax_nopriv_send_login_otp', 'handle_send_login_otp');

function handle_send_login_otp()
{
    gcp_check_rate_limit( 'send_login_otp', 5, 15 * MINUTE_IN_SECONDS );

    // Resend from the OTP screen carries the prior otp_token instead of a fresh
    // reCAPTCHA response — no checkbox is shown there. A live transient for that
    // token proves this is a continuation of an already-verified login attempt.
    $prior_otp_token = sanitize_text_field( $_POST['prior_otp_token'] ?? '' );

    if ( $prior_otp_token !== '' ) {
        if ( get_transient( 'otp_tok_' . $prior_otp_token ) === false ) {
            wp_send_json_error( [ 'message' => 'Your session has expired. Please refresh the page and try again.' ] );
            wp_die();
        }
    } else {
        // Initial submit from the sign-in form — reCAPTCHA v2 verification required.
        $recaptcha_token = sanitize_text_field( $_POST['recaptcha_token'] ?? '' );
        if ( ! gcp_verify_recaptcha( $recaptcha_token, 'login' ) ) {
            wp_send_json_error( [ 'message' => 'We could not verify your request. Please refresh the page and try again. If the problem persists, try a different browser or disable any VPN.' ] );
            wp_die();
        }
    }

    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'] ?? '';

    $user = get_user_by('email', $email);
    if (!$user || !wp_check_password($password, $user->data->user_pass, $user->ID)) {
        wp_send_json_error(['message' => 'Email or password do not match, please try again']);
    }
    $user_id = $user->ID;

    // Generate cryptographically secure OTP and opaque session token
    $otp_code   = random_int( 100000, 999999 );
    $otp_token  = bin2hex( random_bytes( 16 ) );
    $otp_expiry = time() + (1 * MINUTE_IN_SECONDS);

    // Keyed by opaque token — user_id never touches the client
    $transient_key = 'otp_tok_' . $otp_token;

    $otp_data = array(
        'user_id'      => $user_id,
        'otp_code'     => $otp_code,
        'otp_expiry'   => $otp_expiry,
        'otp_attempts' => 0,
    );

    set_transient( $transient_key, $otp_data, 1 * MINUTE_IN_SECONDS );

    // Send OTP email — use the 'otp-verification' email template if available.
    $tpl = et_get_template_by_slug( 'login-otp-verification', [
        'otp'        => $otp_code,
        'first_name' => $user->first_name,
    ] );

    if ( $tpl ) {
        wp_mail( $email, $tpl['subject'], $tpl['body'], $tpl['headers'] );
    } else {
        wp_mail( $email, 'Your giftcardsplus login code is here', "Your OTP Code is: $otp_code\n\nThis code expires in 1 minute." );
    }

    // Return OTP form HTML
    ob_start(); ?>

    <div class="step-heading">
        <h2 class="custom-heading">Check your email</h2>

        <p class="custom-text">
            We’ve sent you a one-time passcode. Please enter it below.
            <!-- <strong><?php //echo esc_html($email); ?></strong> -->
        </p>
    </div>

    <div class="otp-container">
        <div class="enter-otp-wrap">
            <input type="hidden" name="otp_token" id="otp_token" value="<?php echo esc_attr( $otp_token ); ?>">
            <div id="otp_inputs" class="otp-inputs">
                <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
                <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
                <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
                <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
                <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
                <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
            </div>
            <div class="error-message has-error" id="error-msg"></div>
        </div>
        <div class="verify-btn-wrap">
            <button id="verify_otp_btn" class="custom-login-btn btn-full-width btn btn-primary btn-black-p2">Submit</button></br></br>
            <button id="resend_otp" class="custom-resend-btn custom-link btn-full-width btn btn-primary btn-black-p2">Resend <span
                    id="otp_timer">1:00</span></button>
            <p class="custom-text contact-txt" style="display : none;">
                Didn’t receive your code? Check your details or contact our team for help.
                <a href="<?php echo site_url('/contact-us/'); ?>" class="custom-link" id="contact_team"
                    style="pointer-events:none; opacity:0.5;">contact our team for help. </a>
            </p>
        </div>
    </div>



    <style>
        /* .otp-inputs {
                    display: flex;
                    justify-content: center;
                    gap: 10px;
                    margin: 15px 0;
                }

                .otp-digit {
                    width: 45px;
                    height: 55px;
                    text-align: center;
                    font-size: 22px;
                    border: 2px solid #ccc;
                    border-radius: 8px;
                    outline: none;
                }

                .otp-digit:focus {
                    border-color: #000;
                    box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);
                }

                .otp-inputs {
                    display: flex;
                    justify-content: center;
                    gap: 10px;
                    margin: 15px 0;
                }

                .otp-digit {
                    width: 45px;
                    height: 55px;
                    text-align: center;
                    font-size: 22px;
                    border: 2px solid #ccc;
                    border-radius: 8px;
                    outline: none;
                    transition: all 0.2s ease-in-out;
                }

                .otp-digit:focus {
                    border-color: #000;
                    box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);
                } */
    </style>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

/**
 * Step 2: Verify OTP and login user
 */
add_action('wp_ajax_verify_login_otp', 'handle_verify_login_otp');
add_action('wp_ajax_nopriv_verify_login_otp', 'handle_verify_login_otp');

function handle_verify_login_otp()
{
    gcp_check_rate_limit( 'verify_login_otp', 10, 15 * MINUTE_IN_SECONDS );

    $otp_token = sanitize_text_field( $_POST['otp_token'] ?? '' );
    $otp_code  = sanitize_text_field( $_POST['otp_code'] ?? '' );

    if ( empty( $otp_token ) ) {
        wp_send_json_error( [ 'message' => 'Invalid session. Please log in again.' ] );
    }

    // Check if this is auto-validation (for visual feedback only, doesn't consume attempts)
    $is_auto_validation = isset($_POST['is_auto_validation']) && $_POST['is_auto_validation'] === 'true';

    $transient_key = 'otp_tok_' . $otp_token;
    $otp_data = get_transient($transient_key);

    if (!$otp_data) {
        wp_send_json_error(['message' => 'OTP expired. Please log in again.']);
    }

    // Check expiry
    if (time() > $otp_data['otp_expiry']) {
        // Only delete transient if this is an actual submission, not auto-validation
        if (!$is_auto_validation) {
            delete_transient($transient_key);
        }
        wp_send_json_error(['message' => 'OTP expired. Please request a new one.']);
    }

    // Check if too many attempts (only for actual submissions, not auto-validation)
    if (!$is_auto_validation && !empty($otp_data['otp_attempts']) && $otp_data['otp_attempts'] >= 3) {
        delete_transient($transient_key);
        wp_send_json_error(['message' => 'Too many invalid attempts. Please log in again.']);
    }

    // Validate OTP - check if it matches first
    if ($otp_code == $otp_data['otp_code']) {
        // OTP is correct
        if ($is_auto_validation) {
            // For auto-validation, just return success for visual feedback
            // Don't log in user or delete transient yet
            wp_send_json_success(['message' => 'OTP is valid']);
        } else {
            // For actual submission, delete transient and log in user
            $user_id = intval( $otp_data['user_id'] );
            delete_transient($transient_key);
            wp_set_auth_cookie($user_id);
            wp_send_json_success(['redirect' => home_url('/')]);
        }
    } else {
        // OTP is incorrect
        if (!$is_auto_validation) {
            // Only increment attempt count if this is NOT auto-validation
            $otp_data['otp_attempts'] = isset($otp_data['otp_attempts']) ? $otp_data['otp_attempts'] + 1 : 1;
            $remaining_time = max(60, $otp_data['otp_expiry'] - time());
            set_transient($transient_key, $otp_data, $remaining_time);
        }
        // For auto-validation, just return error for visual feedback without consuming attempts
        wp_send_json_error(['message' => 'This code is incorrect please try again or click resend code.']);
    }
}


/**
 * AJAX — Password Reset Request (5-minute link)
 */
add_action("wp_ajax_fp_request_reset", "fp_request_reset");
add_action("wp_ajax_nopriv_fp_request_reset", "fp_request_reset");

/**
 * Transient key used to prove a given email already passed reCAPTCHA on the
 * initial forgot-password request, so the "Check your inbox" resend button
 * doesn't need to show its own checkbox.
 */
function fp_verified_email_transient_key( string $email ): string {
    return 'fp_verified_' . md5( strtolower( trim( $email ) ) );
}

function fp_request_reset()
{
    gcp_check_rate_limit( 'fp_request_reset', 5, 15 * MINUTE_IN_SECONDS );

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (empty($email)) {
        wp_send_json(["status" => "error", "message" => "Please enter your email."]);
    }

    $resend_proof = sanitize_text_field( $_POST['resend_proof'] ?? '' );
    $verified_key = fp_verified_email_transient_key( $email );

    if ( $resend_proof !== '' ) {
        // Resend from the "Check your inbox" screen — no reCAPTCHA shown;
        // require proof that this exact email already passed reCAPTCHA moments ago.
        if ( $resend_proof !== get_transient( $verified_key ) ) {
            wp_send_json( [ 'status' => 'error', 'message' => 'Your session has expired. Please go back and try again.' ] );
            wp_die();
        }
    } else {
        // Initial request — reCAPTCHA v2 verification required.
        $recaptcha_token = sanitize_text_field( $_POST['recaptcha_token'] ?? '' );
        if ( ! gcp_verify_recaptcha( $recaptcha_token, 'forgot_password' ) ) {
            wp_send_json( [ 'status' => 'error', 'message' => 'reCAPTCHA verification failed. Please try again.' ] );
            wp_die();
        }

        // Issue short-lived proof so the resend button can skip reCAPTCHA.
        $resend_proof = wp_generate_password( 32, false );
        set_transient( $verified_key, $resend_proof, 10 * MINUTE_IN_SECONDS );
    }

    if (!email_exists($email)) {
        // PT-3.12: Always return "sent" — never reveal whether an account exists.
        wp_send_json(["status" => "sent", "resend_proof" => $resend_proof]);
        wp_die();
    }

    $user = get_user_by("email", $email);
    $reset_key = get_password_reset_key($user);
    $issued_at = time();

    update_user_meta($user->ID, 'fp_reset_key_time', $issued_at);

    $reset_url = add_query_arg([
        "fp_reset" => 1,
        "key" => $reset_key,
        "login" => $user->user_email,
    ], site_url("/forget-password"));

    $tpl = et_get_template_by_slug( 'forgot-password', [
        'first_name' => $user->first_name,
        'reset_url'  => $reset_url,
    ] );

    if ( $tpl ) {
        wp_mail( $user->user_email, $tpl['subject'], $tpl['body'], $tpl['headers'] );
    } else {
        wp_mail( $user->user_email, 'Reset your giftcardsplus password', "Click the link to reset your password (valid for 5 minutes): " . $reset_url );
    }

    wp_send_json(["status" => "sent", "resend_proof" => $resend_proof]);
}

/**
 * AJAX — Final Password Reset
 */
add_action("wp_ajax_fp_do_reset_password", "fp_do_reset_password");
add_action("wp_ajax_nopriv_fp_do_reset_password", "fp_do_reset_password");

function fp_do_reset_password()
{
    gcp_check_rate_limit( 'fp_do_reset_password', 5, 15 * MINUTE_IN_SECONDS );
    $pass1 = sanitize_text_field($_POST['pass1']);
    $pass2 = sanitize_text_field($_POST['pass2']);
    $key = sanitize_text_field($_POST['key']);
    $login = sanitize_text_field($_POST['login']);

    if ($pass1 !== $pass2) {
        wp_send_json(["status" => "error", "message" => "Oops, passwords don’t match. Please try again."]);
    }

    if (!validate_password_strength($pass1)) {
        wp_send_json(["status" => "error", "message" => "Please ensure your password is strong and includes at least 12 characters with uppercase, lowercase, number and special character."]);
    }

    $user = get_user_by("email", $login);
    if (!$user) {
        wp_send_json(["status" => "error", "message" => "Invalid user."]);
    }

    $login = $user->user_login;

    $issued_at = get_user_meta($user->ID, 'fp_reset_key_time', true);
    if (!$issued_at || (time() - $issued_at) > 300) { // 300 seconds = 5 minutes
        wp_send_json(["status" => "error", "message" => "This reset link has expired."]);
    }

    $check = check_password_reset_key($key, $login);
    if (is_wp_error($check)) {
        wp_send_json(["status" => "error", "message" => "Invalid reset key."]);
    }

    reset_password($user, $pass1);
    delete_user_meta($user->ID, 'fp_reset_key_time');
    set_transient('fp_password_reset_msg', 'true', 60);
    wp_send_json(["status" => "success", "message" => "Your password has been reset. Please sign in below."]);
}


add_action('wp_ajax_save_user_preferences', 'save_user_preferences');

function save_user_preferences()
{
    gcp_require_logged_in_ajax();
    check_ajax_referer('save_pref_nonce', 'security');

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    $user_id = get_current_user_id();

    // Save hobbies
    if (!empty($_POST['hobbies'])) {
        update_user_meta($user_id, 'hobbies', array_map('sanitize_text_field', $_POST['hobbies']));
    } else {
        update_user_meta($user_id, 'hobbies', []);
    }


    // Save events
    if (!empty($_POST['events'])) {
        update_user_meta($user_id, 'interested_events', array_map('sanitize_text_field', $_POST['events']));
    } else {
        update_user_meta($user_id, 'interested_events', []);
    }

    // Save marketing preferences
    update_user_meta($user_id, 'marketing_emails', intval($_POST['marketing_email']));
    update_user_meta($user_id, 'sms_notifications', intval($_POST['marketing_sms']));

    wp_send_json_success('Preferences Saved');
}


/**
 * Build SQL WHERE fragment for product search: gift card name, brand, tag, description, denomination, category.
 * Used by gc_load_more_handler and gc_search_products.
 *
 * @param string $search_like Escaped LIKE value (e.g. '%term%').
 * @param \wpdb  $wpdb       WordPress DB instance.
 * @return string The AND ( ... ) clause for posts_where.
 */
function gc_product_search_where_clause($search_like, $wpdb) {
    $terms_subquery = "SELECT tr.object_id FROM {$wpdb->term_relationships} tr " .
        "INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id " .
        "INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id " .
        "WHERE tt.taxonomy IN ('product_cat', 'product_tag', 'product_brand', 'icons') AND t.name LIKE %s";
    $meta_subquery  = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_price', 'variable_range_from', 'variable_range_to', 'denomination_type', '_sku') AND meta_value LIKE %s";
    $clause = " AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s " .
        "OR {$wpdb->posts}.ID IN ({$terms_subquery}) OR {$wpdb->posts}.ID IN ({$meta_subquery}) )";
    return $wpdb->prepare($clause, $search_like, $search_like, $search_like, $search_like, $search_like);
}


// Hide WPBakery frontend editor link for non-admin users
add_filter('vc_is_inline', function ($state) {
    if (!current_useavailabler_can('administrator')) {
        return false; // Disable frontend editor for non-admins
    }
    return $state;
});

add_action('wp_ajax_gc_load_more', 'gc_load_more_handler');
add_action('wp_ajax_nopriv_gc_load_more', 'gc_load_more_handler');

function gc_load_more_handler()
{
    global $wpdb;

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $per_page = isset($_POST['perpage']) ? intval($_POST['perpage']) : 16;

    // Check if this is a wishlist page request
    $is_wishlist_page = isset($_POST['wishlist_page']) && $_POST['wishlist_page'] == '1';
    $is_offers_page = !empty($_POST['offers_page']);
    // Category page: prefer POST from frontend (is_tax() is false in AJAX context); fallback to referrer
    $is_category_page = !empty($_POST['category_page']) && $_POST['category_page'] == '1';
    if ( !$is_category_page && !empty( $_SERVER['HTTP_REFERER'] ) ) {
        $referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
        $is_category_page = ( strpos( $referer, '/product-category/' ) !== false );
    }

    // Resolve category term ID for filtering
    $category_id = 0;
    if ( $is_category_page ) {
        $category_id = !empty( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
        // Fallback: derive from HTTP_REFERER if JS did not send category_id
        if ( ! $category_id && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
            if ( preg_match( '#/product-category/([^/?#]+)#', $referer, $m ) ) {
                $cat = get_term_by( 'slug', $m[1], 'product_cat' );
                if ( $cat && ! is_wp_error( $cat ) ) {
                    $category_id = $cat->term_id;
                }
            }
        }
    }

    // Brands page: for product-tag search expansion (only on /brands/ page)
    $is_brands_page = !empty($_POST['brands_page']) && $_POST['brands_page'] == '1';
    if (!$is_brands_page && !empty($_SERVER['HTTP_REFERER'])) {
        $ref = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        $is_brands_page = (strpos($ref, '/brands') !== false && strpos($ref, '/product-brand/') === false);
    }

    $wishlist_product_ids = array();

    // Get wishlist products if on wishlist page
    if (($is_wishlist_page && is_user_logged_in()) || $is_offers_page) {
        $user_id = get_current_user_id();
        $wishlist = get_user_meta($user_id, 'user_wishlist', true);

        if (is_array($wishlist)) {
            $wishlist_product_ids = array_filter(array_map('intval', $wishlist));
        }
    }

    // Get filter values
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $giftcards_for = isset($_POST['giftcards_for']) ? $_POST['giftcards_for'] : array();
    $occasion = isset($_POST['occasion']) ? $_POST['occasion'] : array();
    $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : '';

    // Convert to arrays if they're strings (for backward compatibility)
    if (!is_array($giftcards_for) && !empty($giftcards_for)) {
        $giftcards_for = array($giftcards_for);
    }
    if (!is_array($occasion) && !empty($occasion)) {
        $occasion = array($occasion);
    }

    // Remove empty values
    $giftcards_for = array_filter(array_map('sanitize_text_field', (array) $giftcards_for));
    $occasion = array_filter(array_map('sanitize_text_field', (array) $occasion));

    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'offset' => $offset,
    );

    // Build taxonomy query for filters
    $tax_query = array('relation' => 'AND');

    if (!empty($giftcards_for)) {
        $tax_query[] = array(
            'taxonomy' => 'product_tag',
            'field' => 'slug',
            'terms' => $giftcards_for,
            'operator' => 'IN',
        );
    }

    if (!empty($occasion)) {
        $tax_query[] = array(
            'taxonomy' => 'product_tag',
            'field' => 'slug',
            'terms' => $occasion,
            'operator' => 'IN',
        );
    }

    if ($is_offers_page && empty($giftcards_for) && empty($occasion)) {
        $tax_query[] = array(
            'taxonomy' => 'product_tag',
            'field' => 'slug',
            'terms' => array('20-off', 'hot-offer'),
            'operator' => 'IN',
        );
    }

    if ( $is_category_page && $category_id ) {
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $category_id,
        );
    }

    // Wishlist page: only show products that are IN the wishlist. Filters must narrow within that set.
    if ($is_wishlist_page) {
        if (empty($wishlist_product_ids)) {
            $args['post__in'] = array(0); // Force no results
        } elseif (count($tax_query) > 1) {
            // Filter only within wishlist: get IDs that are in wishlist AND match the taxonomy filters
            $filter_args = array(
                'post_type'       => 'product',
                'post_status'      => 'publish',
                'post__in'         => $wishlist_product_ids,
                'fields'           => 'ids',
                'posts_per_page'   => -1,
                'tax_query'        => $tax_query,
                'orderby'          => 'post__in',
            );
            $filtered_ids = get_posts($filter_args);
            $args['post__in'] = !empty($filtered_ids) ? array_map('intval', $filtered_ids) : array(0);
            $args['orderby'] = 'post__in';
            // Do not add tax_query to main $args — we already applied it above
        } else {
            $args['post__in'] = $wishlist_product_ids;
            $args['orderby'] = 'post__in';
        }
    } else {
        // Non-wishlist: add tax_query when we have filters
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }
    }

    // Search: gift card name, brand name, gift card tag, description, denomination, category name
    if (!empty($search)) {
        $page_name = 'other';
        if ($is_wishlist_page) {
            $page_name = 'wishlist';
        } elseif ($is_offers_page) {
            $page_name = 'offers';
        } elseif ($is_category_page) {
            $page_name = 'category';
        } elseif ($is_brands_page) {
            $page_name = 'brands';
        }
        gc_log_search($search, 'search', $page_name);
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $callback = null;
        $callback = function ($where) use ($search_like, $wpdb, &$callback) {
            if ($callback) {
                remove_filter('posts_where', $callback, 10);
            }
            return $where . gc_product_search_where_clause($search_like, $wpdb);
        };
        add_filter('posts_where', $callback, 10, 1);
    }

    // Apply sort: on wishlist page use post__in order only when no sort selected; otherwise apply selected sort
    if (!$is_wishlist_page || empty($wishlist_product_ids) || !empty($sort)) {
        switch ($sort) {
            case 'ranked':
                $args['orderby'] = 'menu_order';
                $args['order'] = 'ASC';
                break;

            case 'most_popular':
            case 'bestselling':
                $args['meta_key'] = 'total_sales';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;

            case 'a_z':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;

            case 'z_a':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;

            case 'price_low_high':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'ASC';
                break;

            case 'price_high_low':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'DESC';
                break;

            case 'most_viewed':
                $args['meta_key'] = 'post_views_count';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;

            case 'newest':
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;

            default:
                // No sort or default: on wishlist keep post__in order; others use ranked (menu_order)
                if (!$is_wishlist_page || empty($wishlist_product_ids)) {
                    $args['orderby'] = 'menu_order';
                    $args['order'] = 'ASC';
                }
                break;
        }
    }

    $loop = new WP_Query($args);

    // Remove the filter after query to avoid affecting other queries
    if (!empty($search)) {
        remove_all_filters('posts_where');
    }

    $html = '';
    $has_more = false;

    if ($loop->have_posts()) {
        while ($loop->have_posts()) {
            $loop->the_post();
            global $product;
            $tag_data = gc_get_product_tags(get_the_ID());
            $product_id = get_the_ID();
            $product_link =  esc_url(get_permalink($product_id));

            // Get wishlist status - ONLY for wishlist page
            $wishlist_class = '';
            $wishlist_title = 'Add to wishlist';
            $heart_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

            // Get user's wishlist if logged in and on wishlist page
            if (($is_wishlist_page && is_user_logged_in()) || $is_offers_page) {
                $user_id = get_current_user_id();
                $user_wishlist = get_user_meta($user_id, 'user_wishlist', true);

                if (!is_array($user_wishlist)) {
                    $user_wishlist = array();
                }
                $user_wishlist = array_filter(array_map('intval', $user_wishlist));

                // Check if product is in wishlist
                $is_in_wishlist = in_array($product_id, $user_wishlist);

                // Always use unfilled icon, add fill class when in wishlist
                if ($is_in_wishlist) {
                    $wishlist_class = 'fill';
                    $wishlist_title = 'Remove from wishlist';
                } else {
                    $wishlist_class = '';
                    $wishlist_title = 'Add to wishlist';
                }
            }

            $html .= '
            <a href=" ' . $product_link . ' " class="product-card-link"
                    style="text-decoration: none; color: inherit; display: block;">
                <div class="gc-card" style="position: relative;">
                    <div class="gc-img">' . $product->get_image() . '</div>
                    <p class="gc-title">' . get_the_title() . '</p>';

            // Show product tags only when NOT on wishlist page and NOT on product category page (separate spans: 20% off = yellow, Hot offer = pink)
            if (!$is_wishlist_page && !$is_category_page) {

                $tags = wp_get_post_terms($product_id, 'product_tag');
                $tag_elements = array();
                $hidden_tags = array();

                if ($tags && !is_wp_error($tags)) {
                    foreach ($tags as $index => $t) {

                        $tag_lower = strtolower(trim($t->name));

                        $modifier = (strpos($tag_lower, '20% off') !== false)
                            ? 'product-tag--off'
                            : ((strpos($tag_lower, 'hot offer') !== false)
                                ? 'product-tag--offer'
                                : 'product-tag--default');

                        $tag_html = '<span class="product-tag ' . esc_attr($modifier) . '">' . esc_html($t->name) . '</span>';

                        if ($index < 3) {
                            $tag_elements[] = $tag_html; // first 3 visible
                        } else {
                            $hidden_tags[] = $tag_html; // rest hidden
                        }
                    }
                }

                if (!empty($tag_elements)) {
                    $html .= '<p class="gc-product-tags product-tags-wrap">';

                    // First 3 tags
                    $html .= implode('', $tag_elements);

                    // Extra tags
                    if (!empty($hidden_tags)) {
                        $html .= '<span class="product-tag-more">...</span>
                                <span class="product-tag-hidden">' . implode('', $hidden_tags) . '</span>';
                    }

                    $html .= '</p>';
                }
            }
            // Show wishlist button on wishlist page
            if (($is_wishlist_page && is_user_logged_in()) || $is_offers_page) {
                $html .= '<button class="gc-wishlist-btn ' . $wishlist_class . '" data-product-id="' . $product_id . '" title="' . esc_attr($wishlist_title) . '">' . $heart_icon_svg . '</button>';
            }

            $html .= '</div>';
        }

        // Check if there are more posts available
        $has_more = ($offset + $per_page) < $loop->found_posts;
    } else {
        if ($is_wishlist_page) {
            // Wishlist actually empty → show empty message + Browse. Filter returned no match → show No Cards found.
            if (empty($wishlist_product_ids)) {
                $html = '<div style="text-align: center; padding: 40px; grid-column: 1 / -1;">
                    <p style="font-size: 18px; color: #666; margin-bottom: 20px;">Your wishlist is feeling a little lonely! <br> Explore our gift cards and save the ones you love for later.</p>
                    <a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="button vc_general vc_btn3" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px;">Browse gift cards</a>
                </div>';
            } else {
                $html = '<div style="text-align: center; padding: 40px; grid-column: 1 / -1;">
                    <p style="font-size: 18px; color: #666;">No Cards found.</p>
                </div>';
            }
        }
    }

    wp_reset_postdata();

    // Return JSON response with HTML and has_more flag
    wp_send_json(array(
        'html' => $html,
        'has_more' => $has_more
    ));

    exit;
}





add_action('wp_ajax_gc_toggle_wishlist', 'gc_toggle_wishlist_handler');
add_action('wp_ajax_nopriv_gc_toggle_wishlist', 'gc_toggle_wishlist_handler');

function gc_toggle_wishlist_handler()
{
    // Prevent any output before JSON
    ob_clean();

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Please login to add items to wishlist.'));
        exit;
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error(array('message' => 'Invalid product ID.'));
        exit;
    }

    $user_id = get_current_user_id();
    $wishlist = get_user_meta($user_id, 'user_wishlist', true);

    if (!is_array($wishlist)) {
        $wishlist = array();
    }

    // Check if product is already in wishlist
    $product_index = array_search($product_id, $wishlist);

    if ($product_index !== false) {
        // Remove from wishlist
        unset($wishlist[$product_index]);
        $wishlist = array_values($wishlist); // Re-index array
        $action = 'removed';
        $is_in_wishlist = false;
    } else {
        // Add to wishlist
        $wishlist[] = $product_id;
        $action = 'added';
        $is_in_wishlist = true;
    }

    // Update user meta
    update_user_meta($user_id, 'user_wishlist', $wishlist);
    update_user_meta($user_id, 'wishlist_updated_date', current_time('mysql'));

    wp_send_json_success(array(
        'action' => $action,
        'is_in_wishlist' => $is_in_wishlist,
        'message' => $action === 'added' ? 'Added to wishlist' : 'Removed from wishlist'
    ));
    exit;
}

// AJAX handler for fetching product price options (single product page - like manual order selected-product-container)
add_action('wp_ajax_get_single_product_price_options', 'get_single_product_price_options_handler');
add_action('wp_ajax_nopriv_get_single_product_price_options', 'get_single_product_price_options_handler');

function get_single_product_price_options_handler()
{
    if ( ! check_ajax_referer( 'get_price_options', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
        exit;
    }

    if (ob_get_level()) {
        ob_clean();
    }

    if (!class_exists('WooCommerce')) {
        wp_send_json_error(array('message' => 'WooCommerce is not active.'));
        exit;
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error(array('message' => 'Invalid product ID.'));
        exit;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(array('message' => 'Product not found.'));
        exit;
    }

    $sku = $product->get_sku();
    $sku_type = get_post_meta($product_id, 'sku_type', true);
    $denomination_type = get_field('denomination_type', $product_id);
    if (empty($denomination_type)) {
        $denomination_type = get_post_meta($product_id, 'denomination_type', true);
    }
    $regular_price = floatval($product->get_regular_price());
    $sale_price = $product->get_sale_price() ? floatval($product->get_sale_price()) : null;
    $is_discounted = get_field('discounted_price_checkbox', $product_id);
    $discounted_from = get_post_meta($product_id, '_discount_valid_from', true);
    $discounted_to = get_post_meta($product_id, '_discount_valid_to', true);

    // First priority: sale/discounted price. If not, use denomination prices as-is.
    $discount_active = false;
    if ($is_discounted && $is_discounted === 'Yes' && $sale_price) {
        $current_time = current_time('timestamp');
        $discount_from_ts = !empty($discounted_from) ? strtotime($discounted_from) : 0;
        $discount_to_ts = !empty($discounted_to) ? strtotime($discounted_to) : PHP_INT_MAX;
        if ($current_time >= $discount_from_ts && $current_time <= $discount_to_ts) {
            $discount_active = true;
        }
    }

    $use_discounted_price = ($discount_active && $sale_price);

    $min_price = $regular_price;
    $max_price = $regular_price;
    $price_intervals = 1;
    $amount_options = array();
    $has_children = false;
    $children = array();

    $original_min_price = null;
    $original_max_price = null;

    if ($denomination_type === 'variable') {
        $min_price = get_field('variable_range_from', $product_id);
        if (empty($min_price)) {
            $min_price = get_post_meta($product_id, 'variable_range_from', true);
        }
        $min_price = floatval($min_price ?: $regular_price);
        $max_price = get_field('variable_range_to', $product_id);
        if (empty($max_price)) {
            $max_price = get_post_meta($product_id, 'variable_range_to', true);
        }
        $max_price = floatval($max_price ?: $regular_price);
        $price_intervals = get_field('_reedem_at_intervals', $product_id);
        if (empty($price_intervals)) {
            $price_intervals = get_post_meta($product_id, '_reedem_at_intervals', true);
        }
        $price_intervals = floatval($price_intervals ?: 1);

        $original_min_price = $min_price;
        $original_max_price = $max_price;

        // When within discounted date range: apply discount to range → new min = discounted price, new max = original max - discounted price (e.g. 10–1000, discount 8 → 8–992)
        if ($use_discounted_price && $sale_price > 0) {
            $sale_price_float = floatval($sale_price);
            $min_price = $sale_price_float;
            $max_price = max($min_price, $original_max_price - $sale_price_float);
        }
        // Build exactly 5 amount options: first = min, last = max, middle 3 evenly spaced and snapped to valid intervals
        $range = $max_price - $min_price;
        $interval = $price_intervals;
        $snap_to_interval = function ($raw) use ($min_price, $max_price, $interval) {
            if ($interval <= 0) {
                return round($raw, 2);
            }
            $steps = round(($raw - $min_price) / $interval);
            $snapped = $min_price + $steps * $interval;
            $snapped = round($snapped, 2);
            if ($snapped < $min_price) {
                $snapped = $min_price;
            }
            if ($snapped > $max_price) {
                $snapped = $max_price;
            }
            return $snapped;
        };
        $amounts = array(
            round($min_price, 2),
            $snap_to_interval($min_price + $range * 1/4),
            $snap_to_interval($min_price + $range * 2/4),
            $snap_to_interval($min_price + $range * 3/4),
            round($max_price, 2),
        );
        $amounts = array_values(array_unique($amounts));
        sort($amounts, SORT_NUMERIC);
        $amounts = array_slice($amounts, 0, 5);
        $product_image = wp_get_attachment_image_url($product->get_image_id(), 'medium') ?: wc_placeholder_img_src();
        $product_title = $product->get_name();
        foreach ($amounts as $amt) {
            $amount_options[] = array(
                'amount'     => $amt,
                'label'      => '$' . number_format($amt, 2),
                'product_id' => $product_id,
                'sku'        => $sku,
                'title'      => $product_title,
                'image'      => $product_image,
            );
        }
    } elseif ($denomination_type === 'fixed') {
        if ($sku_type === 'Parent') {
            $child_args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => array(
                    'relation' => 'AND',
                    array('key' => 'sku_type', 'value' => 'Child', 'compare' => '='),
                    array('key' => 'parent_sku', 'value' => $sku, 'compare' => '='),
                ),
            );
            $child_query = new WP_Query($child_args);
            if ($child_query->have_posts()) {
                $has_children = true;
                while ($child_query->have_posts()) {
                    $child_query->the_post();
                    $child_id = get_the_ID();
                    $child_product = wc_get_product($child_id);
                    $child_sku = get_post_meta($child_id, '_sku', true);
                    $child_denom = get_field('denomination_type', $child_id) ?: get_post_meta($child_id, 'denomination_type', true);
                    $child_reg = floatval(get_post_meta($child_id, '_regular_price', true));
                    $child_sale = get_post_meta($child_id, '_sale_price', true);
                    $child_sale = $child_sale ? floatval($child_sale) : null;
                    $child_disc = get_field('discounted_price_checkbox', $child_id);
                    $child_from = get_post_meta($child_id, '_discount_valid_from', true);
                    $child_to = get_post_meta($child_id, '_discount_valid_to', true);
                    // First priority: discounted. Else use denomination as-is.
                    $child_disc_active = false;
                    if ($child_disc === 'Yes' && $child_sale && $child_from && $child_to) {
                        $ct = current_time('timestamp');
                        if ($ct >= strtotime($child_from) && $ct <= strtotime($child_to)) {
                            $child_disc_active = true;
                        }
                    }
                    $display_price = $child_disc_active && $child_sale ? $child_sale : $child_reg;
                    if ($display_price > 0) {
                        $label = '$' . number_format($display_price, 2);
                        $child_opt = array(
                            'amount' => $display_price,
                            'label' => $label,
                            'product_id' => $child_id,
                            'sku' => $child_sku,
                            'title' => $product->get_name(),
                            'image' => wp_get_attachment_image_url($child_product->get_image_id(), 'medium') ?: wc_placeholder_img_src(),
                        );
                        if ($child_disc_active && $child_sale) {
                            $child_opt['original_amount'] = $child_reg;
                        }
                        $children[] = $child_opt;
                    }
                }
                wp_reset_postdata();
            }
        }
        if (!$has_children || empty($children)) {
            // First priority: discounted. Else use denomination as-is.
            $display_price = $use_discounted_price ? $sale_price : $regular_price;
            if ($display_price > 0) {
                $label = '$' . number_format($display_price, 2);
                $opt = array('amount' => $display_price, 'label' => $label, 'product_id' => $product_id, 'sku' => $sku, 'title' => $product->get_name(), 'image' => wp_get_attachment_image_url($product->get_image_id(), 'medium') ?: wc_placeholder_img_src());
                if ($use_discounted_price) {
                    $opt['original_amount'] = $regular_price;
                }
                $amount_options[] = $opt;
            }
        } else {
            $amount_options = $children;
        }
    } else {
        // First priority: discounted. Else use denomination as-is.
        $display_price = $use_discounted_price ? $sale_price : $regular_price;
        if ($display_price > 0) {
            $opt = array('amount' => $display_price, 'label' => '$' . number_format($display_price, 2), 'product_id' => $product_id, 'sku' => $sku, 'title' => $product->get_name(), 'image' => wp_get_attachment_image_url($product->get_image_id(), 'medium') ?: wc_placeholder_img_src());
            if ($use_discounted_price) {
                $opt['original_amount'] = $regular_price;
            }
            $amount_options[] = $opt;
        }
    }

    $discount_multiplier = 1;
    // Only needed when variable WITHOUT discount (for custom amount). When discounted, we show only sale price.

    // Variable (with or without discount) allows custom amount in range
    $has_custom = ($denomination_type === 'variable');

    wp_send_json_success(array(
        'denomination_type' => $denomination_type ?: 'fixed',
        'min_price' => $min_price,
        'max_price' => $max_price,
        'price_intervals' => $price_intervals,
        'amount_options' => $amount_options,
        'has_custom' => $has_custom,
        'product_id' => $product_id,
        'product_sku' => $sku,
        'product_title' => $product->get_name(),
        'product_image' => wp_get_attachment_image_url($product->get_image_id(), 'medium') ?: wc_placeholder_img_src(),
        'discounted_price' => $use_discounted_price ? $sale_price : null,
        'original_price' => $regular_price,
        'discount_multiplier' => $discount_multiplier,
        'discount_active' => $discount_active,
        'original_min_price' => $original_min_price,
        'original_max_price' => $original_max_price,
    ));
    exit;
}

// Save selected card design to session only (no media upload until order is placed)
add_action('wp_ajax_gc_save_card_design_to_session', 'gc_save_card_design_to_session_handler');
add_action('wp_ajax_nopriv_gc_save_card_design_to_session', 'gc_save_card_design_to_session_handler');
function gc_save_card_design_to_session_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'gc_save_card_design')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $image = isset($_POST['image']) ? $_POST['image'] : '';
    if (!$product_id) {
        wp_send_json_error(['message' => 'Missing product_id']);
    }
    if (!function_exists('WC') || !WC()->session) {
        wp_send_json_error(['message' => 'Session not available']);
    }
    if (is_string($image) && (strpos($image, 'data:image') === 0 || strpos($image, 'http') === 0)) {
        WC()->session->set('gc_card_design_' . $product_id, $image);
        $images = WC()->session->get('gift_card_images', []);
        $images[$product_id] = $image;
        WC()->session->set('gift_card_images', $images);
    } else {
        WC()->session->set('gc_card_design_' . $product_id, '');
        $images = WC()->session->get('gift_card_images', []);
        unset($images[$product_id]);
        WC()->session->set('gift_card_images', $images);
    }
    wp_send_json_success();
}


// Upload media (video, gif, image) for animation/video message - returns URL
add_action('wp_ajax_gc_upload_media_message', 'gc_upload_media_message_handler');
add_action('wp_ajax_nopriv_gc_upload_media_message', 'gc_upload_media_message_handler');
function gc_upload_media_message_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'gc_media_upload')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    $media_type = isset($_POST['media_type']) ? sanitize_text_field($_POST['media_type']) : '';
    if (!in_array($media_type, ['image', 'video', 'animation'], true)) {
        wp_send_json_error(['message' => 'Invalid media type']);
    }
    if (empty($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'No file uploaded or upload error']);
    }
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $file = $_FILES['media_file'];
    $allowed = [
        'image' => ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'],
        'video' => ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg', 'mov' => 'video/quicktime'],
        'animation' => ['gif' => 'image/gif'],
    ];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$media_type][$ext])) {
        wp_send_json_error(['message' => 'Invalid file type for ' . $media_type]);
    }
    $upload = wp_handle_upload($file, ['test_form' => false]);
    if (isset($upload['error'])) {
        wp_send_json_error(['message' => $upload['error']]);
    }
    $url = isset($upload['url']) ? $upload['url'] : '';
    if (empty($url)) {
        wp_send_json_error(['message' => 'Upload failed']);
    }
    wp_send_json_success(['url' => $url]);
}

// Save media message URL to session (for checkout flow)
add_action('wp_ajax_gc_save_media_message_to_session', 'gc_save_media_message_to_session_handler');
add_action('wp_ajax_nopriv_gc_save_media_message_to_session', 'gc_save_media_message_to_session_handler');
function gc_save_media_message_to_session_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'gc_media_upload')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $media_type = isset($_POST['media_type']) ? sanitize_text_field($_POST['media_type']) : '';
    $media_url = isset($_POST['media_url']) ? esc_url_raw($_POST['media_url']) : '';
    if (!$product_id || !in_array($media_type, ['image', 'video', 'animation'], true)) {
        wp_send_json_error(['message' => 'Invalid parameters']);
    }
    if (!function_exists('WC') || !WC()->session) {
        wp_send_json_error(['message' => 'Session not available']);
    }
    $key = 'gc_media_' . $media_type . '_' . $product_id;
    if (!empty($media_url)) {
        WC()->session->set($key, $media_url);
    } else {
        WC()->session->__unset($key);
    }
    wp_send_json_success();
}

// Parse and sanitize gift card POST/session data into cart_item_data (shared for AJAX and session flow)
function gc_parse_gift_card_request_data($source) {
    $get_val = function ($key, $default = '') use ($source) {
        return isset($source[$key]) ? $source[$key] : $default;
    };
    $product_id = intval($get_val('product_id', 0));
    $quantity = intval($get_val('quantity', 1));
    $gift_card_price = floatval($get_val('gift_card_price', 0));
    $recipient_name = sanitize_text_field($get_val('recipient_name', ''));
    $recipient_email = sanitize_email($get_val('recipient_email', ''));
    $sender_name = sanitize_text_field($get_val('sender_name', ''));
    $mobile_number = sanitize_text_field($get_val('mobile_number', ''));
    $delivery_email = sanitize_email($get_val('delivery_email', ''));
    $delivery_method = sanitize_text_field($get_val('delivery_method', ''));
    $delivery_timing = sanitize_text_field($get_val('delivery_timing', ''));
    $gift_for = sanitize_text_field($get_val('gift_for', ''));
    $gift_message = sanitize_textarea_field($get_val('gift_message', ''));
    // Card design: use request value only when request explicitly sends card_design (e.g. single product form).
    // Do NOT fall back to session when the form sent card_design empty—otherwise a second add-to-cart without
    // selecting an image would incorrectly reuse the previous session image for the new cart line.
    $card_design_raw = $get_val('card_design', '');
    $card_design = '';
    if (!empty($card_design_raw) && is_string($card_design_raw)) {
        if (strpos($card_design_raw, 'data:image') === 0) {
            $card_design = $card_design_raw; // Keep data URI (will be stripped if too long for storage)
        } else {
            $card_design = $card_design_raw;
        }
    }
    $card_design_sent_in_request = array_key_exists('card_design', $source);
    if (empty($card_design) && !$card_design_sent_in_request && $product_id && function_exists('WC') && WC()->session) {
        $from_session = WC()->session->get('gc_card_design_' . $product_id, '');
        if (!empty($from_session) && is_string($from_session)) {
            $card_design = (strpos($from_session, 'data:image') === 0) ? $from_session : esc_url_raw($from_session);
        }
    }
    $schedule_date = sanitize_text_field($get_val('schedule_date', ''));
    $schedule_time = sanitize_text_field($get_val('schedule_time', ''));
    $schedule_timezone = sanitize_text_field($get_val('schedule_timezone', ''));
    $schedule_datetime = sanitize_text_field($get_val('schedule_datetime', ''));
    if (empty($schedule_datetime) && !empty($schedule_date) && !empty($schedule_time)) {
        $schedule_datetime = trim($schedule_date . ' ' . $schedule_time);
        if (!empty($schedule_timezone)) {
            $schedule_datetime .= ' ' . $schedule_timezone;
        }
    }
    // Media message: email_animation (GIF for email), video_message (video attachment), image_message (image for message)
    $email_animation = esc_url_raw($get_val('email_animation', ''));
    $video_message = esc_url_raw($get_val('video_message', ''));
    $image_message = esc_url_raw($get_val('image_message', ''));
    $email_animation_sent_in_request = array_key_exists('email_animation', $source);
    $video_message_sent_in_request = array_key_exists('video_message', $source);
    $image_message_sent_in_request = array_key_exists('image_message', $source);
    if (empty($email_animation) && !$email_animation_sent_in_request && $product_id && function_exists('WC') && WC()->session) {
        $email_animation = esc_url_raw(WC()->session->get('gc_media_animation_' . $product_id, ''));
    }
    if (empty($video_message) && !$video_message_sent_in_request && $product_id && function_exists('WC') && WC()->session) {
        $video_message = esc_url_raw(WC()->session->get('gc_media_video_' . $product_id, ''));
    }
    if (empty($image_message) && !$image_message_sent_in_request && $product_id && function_exists('WC') && WC()->session) {
        $image_message = esc_url_raw(WC()->session->get('gc_media_image_' . $product_id, ''));
    }
    $cart_item_data = array(
        'gift_card_price' => $gift_card_price,
        'recipient_name' => $recipient_name,
        'recipient_email' => $recipient_email,
        'sender_name' => $sender_name,
        'mobile_number' => $mobile_number,
        'delivery_email' => $delivery_email,
        'delivery_method' => $delivery_method,
        'delivery_timing' => $delivery_timing,
        'gift_message' => $gift_message,
        'card_design' => $card_design,
        'delivery_option' => $gift_for,
        'schedule_date' => $schedule_date,
        'schedule_time' => $schedule_time,
        'schedule_timezone' => $schedule_timezone,
        'schedule_datetime' => $schedule_datetime,
        'email_animation' => $email_animation,
        'video_message' => $video_message,
        'image_message' => $image_message
    );
    return array('product_id' => $product_id, 'quantity' => $quantity, 'gift_card_price' => $gift_card_price, 'cart_item_data' => $cart_item_data);
}

// Add gift card to cart from parsed data (used by AJAX handler and session consumer)
function gc_add_gift_card_to_cart_with_data($product_id, $quantity, $cart_item_data) {
    if (!WC()->cart || !is_object(WC()->cart) || !method_exists(WC()->cart, 'add_to_cart')) {
        return false;
    }
    WC()->cart->get_cart_from_session();
    $gift_card_price = isset($cart_item_data['gift_card_price']) ? floatval($cart_item_data['gift_card_price']) : 0;
    $gift_card_config_string = sprintf(
        '%s|%s|%s|%s|%s|%s|%s|%s|%d',
        $gift_card_price,
        isset($cart_item_data['recipient_name']) ? $cart_item_data['recipient_name'] : '',
        isset($cart_item_data['recipient_email']) ? $cart_item_data['recipient_email'] : '',
        isset($cart_item_data['mobile_number']) ? $cart_item_data['mobile_number'] : '',
        isset($cart_item_data['delivery_method']) ? $cart_item_data['delivery_method'] : '',
        isset($cart_item_data['delivery_timing']) ? $cart_item_data['delivery_timing'] : '',
        isset($cart_item_data['delivery_option']) ? $cart_item_data['delivery_option'] : '',
        isset($cart_item_data['sender_name']) ? $cart_item_data['sender_name'] : '',
        isset($cart_item_data['delivery_email']) ? $cart_item_data['delivery_email'] : '',
        $quantity
    );
    $cart_item_data['gift_card_unique_id'] = md5($gift_card_config_string . time() . wp_rand(1000, 9999));
    $cart_item_data['gift_card_config_hash'] = md5($gift_card_config_string);
    // Ensure selected_gift_card_image is set for checkout display (same as card_design); keep per-item.
    if (!empty($cart_item_data['card_design'])) {
        $cart_item_data['selected_gift_card_image'] = $cart_item_data['card_design'];
    } else {
        $cart_item_data['selected_gift_card_image'] = '';
    }
    $new_cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
    if (!$new_cart_item_key) {
        return false;
    }
    $cart_item = WC()->cart->get_cart_item($new_cart_item_key);
    if (!$cart_item) {
        return false;
    }
    if ($gift_card_price > 0 && isset($cart_item['data'])) {
        $cart_item['data']->set_price($gift_card_price);
    }
    WC()->cart->set_session();
    WC()->cart->maybe_set_cart_cookies();
    WC()->cart->calculate_totals();
    WC()->cart->set_session();
    return $new_cart_item_key;
}

// Session-based Buy Now: save gift card data to session and redirect to checkout (no AJAX)
add_action('wp_ajax_gc_buy_now_process', 'gc_buy_now_process_handler');
add_action('wp_ajax_nopriv_gc_buy_now_process', 'gc_buy_now_process_handler');

function gc_buy_now_process_handler() {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gc_buy_now')) {
        wp_safe_redirect(wc_get_page_permalink('shop'));
        exit;
    }
    if (!class_exists('WooCommerce') || !function_exists('WC')) {
        wp_safe_redirect(wc_get_page_permalink('shop'));
        exit;
    }
    // Ensure session and cart are initialized (required for guest users in admin-ajax context)
    if (!WC()->session && function_exists('wc_load_cart')) {
        wc_load_cart();
    }
    if (!WC()->session) {
        wp_safe_redirect(wc_get_page_permalink('shop'));
        exit;
    }
    $parsed = gc_parse_gift_card_request_data($_POST);
    $product_id = $parsed['product_id'];
    if (!$product_id || !wc_get_product($product_id)) {
        wp_safe_redirect(wc_get_page_permalink('shop'));
        exit;
    }
    // Add gift card to cart directly so it persists for logged-in and guest users
    $quantity = isset($parsed['quantity']) ? intval($parsed['quantity']) : 1;
    $cart_item_data = $parsed['cart_item_data'];

    $key = gc_add_gift_card_to_cart_with_data($product_id, $quantity, $cart_item_data);
    if ($key) {
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
    wc_add_notice(__('Unable to add product to cart. Please try again.'), 'error');
    wp_safe_redirect(get_permalink($product_id));
    exit;
}

// On cart/checkout load: add pending gift card from session to cart (session-based data flow)
add_action('template_redirect', 'gc_process_pending_buy_now_from_session', 5);

function gc_process_pending_buy_now_from_session() {
    if (!function_exists('WC') || !WC()->session) {
        return;
    }
    if (!is_cart() && !is_checkout()) {
        return;
    }
    $pending = WC()->session->get('gc_pending_buy_now');
    if (empty($pending) || empty($pending['product_id']) || empty($pending['cart_item_data'])) {
        return;
    }
    $product_id = intval($pending['product_id']);
    $quantity = isset($pending['quantity']) ? intval($pending['quantity']) : 1;
    $cart_item_data = $pending['cart_item_data'];
    if (!wc_get_product($product_id)) {
        WC()->session->__unset('gc_pending_buy_now');
        return;
    }
    $key = gc_add_gift_card_to_cart_with_data($product_id, $quantity, $cart_item_data);
    if ($key) {
        WC()->session->__unset('gc_pending_buy_now');
    }
}


/**
 * Reorder: add all items from a past order to the cart and redirect to cart.
 * Triggered by cart or any page with ?gc_reorder=ORDER_ID (e.g. from view-order Reorder button).
 */
add_action('template_redirect', 'gc_handle_reorder_request', 4);

function gc_handle_reorder_request() {
    if (!isset($_GET['gc_reorder']) || !is_numeric($_GET['gc_reorder'])) {
        return;
    }
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $order_id = (int) $_GET['gc_reorder'];
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    if (!current_user_can('view_order', $order_id)) {
        return;
    }

    WC()->cart->empty_cart();
    $items_added = 0;

    foreach ($order->get_items() as $item_id => $item) {
        if ($item->get_type() !== 'line_item') {
            continue;
        }
        $product = $item->get_product();
        if (!$product || !$product->is_purchasable()) {
            continue;
        }

        $product_id = $product->get_id();
        $quantity = (int) $item->get_quantity();
        if ($quantity < 1) {
            continue;
        }

        $gift_card_price = floatval(wc_get_order_item_meta($item_id, '_gift_card_price', true));
        if ($gift_card_price <= 0) {
            $gift_card_price = (float) $item->get_total();
            if ($quantity > 0) {
                $gift_card_price = $gift_card_price / $quantity;
            }
        }

        $recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
        $recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
        $gift_message = wc_get_order_item_meta($item_id, '_gift_message', true);
        if (empty($gift_message)) {
            $gift_message = wc_get_order_item_meta($item_id, 'gift_message', true);
        }
        $delivery_method = wc_get_order_item_meta($item_id, '_delivery_method', true);
        $gift_card_image = wc_get_order_item_meta($item_id, '_gift_card_image', true);
        $sender_name = wc_get_order_item_meta($item_id, '_sender_name', true);
        if (empty($sender_name)) {
            $sender_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }
        $mobile_number = wc_get_order_item_meta($item_id, '_recipient_phone', true);
        if (empty($mobile_number)) {
            $mobile_number = wc_get_order_item_meta($item_id, 'mobile_number', true);
        }
        $delivery_email = wc_get_order_item_meta($item_id, '_delivery_email', true);
        $delivery_timing = wc_get_order_item_meta($item_id, '_delivery_timing', true);
        $delivery_option = wc_get_order_item_meta($item_id, '_delivery_option', true);
        $scheduled_date = wc_get_order_item_meta($item_id, '_scheduled_date', true);
        $schedule_date = wc_get_order_item_meta($item_id, 'schedule_date', true);
        $schedule_time = wc_get_order_item_meta($item_id, 'schedule_time', true);
        $schedule_timezone = wc_get_order_item_meta($item_id, 'schedule_timezone', true);
        $email_animation = wc_get_order_item_meta($item_id, 'gift_email_animation', true);
        $video_message = wc_get_order_item_meta($item_id, 'gift_video_message', true);
        $image_message = wc_get_order_item_meta($item_id, 'gift_image_message', true);

        $cart_item_data = array(
            'gift_card_price' => $gift_card_price,
            'recipient_name' => is_string($recipient_name) ? $recipient_name : '',
            'recipient_email' => is_string($recipient_email) ? $recipient_email : '',
            'sender_name' => is_string($sender_name) ? $sender_name : '',
            'mobile_number' => is_string($mobile_number) ? $mobile_number : '',
            'delivery_email' => is_string($delivery_email) ? $delivery_email : '',
            'delivery_method' => is_string($delivery_method) ? $delivery_method : '',
            'delivery_timing' => is_string($delivery_timing) ? $delivery_timing : '',
            'delivery_option' => is_string($delivery_option) ? $delivery_option : '',
            'gift_message' => is_string($gift_message) ? $gift_message : '',
            'card_design' => is_string($gift_card_image) ? $gift_card_image : '',
            'schedule_date' => is_string($schedule_date) ? $schedule_date : '',
            'schedule_time' => is_string($schedule_time) ? $schedule_time : '',
            'schedule_timezone' => is_string($schedule_timezone) ? $schedule_timezone : '',
            'schedule_datetime' => is_string($scheduled_date) ? $scheduled_date : '',
            'email_animation' => is_string($email_animation) ? $email_animation : '',
            'video_message' => is_string($video_message) ? $video_message : '',
            'image_message' => is_string($image_message) ? $image_message : '',
        );

        $key = gc_add_gift_card_to_cart_with_data($product_id, $quantity, $cart_item_data);
        if ($key) {
            $items_added++;
        } else {
            WC()->cart->add_to_cart($product_id, $quantity);
            $items_added++;
        }
    }

    $redirect_url = remove_query_arg('gc_reorder', wc_get_cart_url());
    if ($items_added > 0) {
        wc_add_notice(sprintf(__('%d item(s) from your order have been added to the cart. You can edit details and proceed to checkout.', 'woocommerce'), $items_added), 'success');
    }
    wp_safe_redirect($redirect_url);
    exit;
}

/**
 * Cart page: confirm before removing an item (product-remove click).
 */
add_action('wp_footer', 'cart_product_remove_confirm_script');
function cart_product_remove_confirm_script() {
    if (!function_exists('is_cart') || !is_cart()) {
        return;
    }
    ?>
    <script>
    (function() {
        function attachCartRemoveConfirm() {
            document.querySelectorAll('.product-remove a.remove').forEach(function(removeLink) {
                if (removeLink.dataset.confirmBound) return;
                removeLink.dataset.confirmBound = '1';
                removeLink.addEventListener('click', function(e) {
                    // Stop WooCommerce AJAX handler from firing immediately
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    var row = removeLink.closest('tr');
                    var nameCell = row ? row.querySelector('.product-name') : null;
                    var productName = nameCell ? nameCell.textContent.trim().replace(/\s+/g, ' ').substring(0, 80) : 'this item';
                    var recipient = '';
                    if (row) {
                        var recipientEl = row.querySelector('.cart-item-recipient-email, .recipient-email');
                        if (recipientEl) recipient = ' Recipient: ' + recipientEl.textContent.trim();
                    }
                    if (confirm('Are you sure you want to delete ' + productName + recipient + ' from your cart?')) {
                        // User confirmed — navigate to the remove URL directly
                        window.location.href = removeLink.href;
                    }
                    // User cancelled — do nothing, item stays in cart
                }, true); // capture phase — runs before WooCommerce handlers
            });
        }

        document.addEventListener('DOMContentLoaded', attachCartRemoveConfirm);

        // Re-attach after WooCommerce updates cart via AJAX
        jQuery(document.body).on('updated_cart_totals wc_fragments_refreshed', attachCartRemoveConfirm);
    })();
    </script>
    <?php
}


// AJAX handler for adding gift card to cart with custom meta (still used by "Add to cart" and other flows)
add_action('wp_ajax_gc_add_to_cart', 'gc_add_to_cart_handler');
add_action('wp_ajax_nopriv_gc_add_to_cart', 'gc_add_to_cart_handler');

function gc_add_to_cart_handler()
{
    // Prevent any output before JSON
    if (ob_get_level()) {
        ob_clean();
    }

    if (!class_exists('WooCommerce')) {
        wp_send_json_error(array('message' => 'WooCommerce is not active.'));
        exit;
    }

    if (!function_exists('WC')) {
        wp_send_json_error(array('message' => 'WooCommerce is not initialized.'));
        exit;
    }

    $parsed = gc_parse_gift_card_request_data($_POST);
    $product_id = $parsed['product_id'];
    $quantity = $parsed['quantity'];
    $cart_item_data = $parsed['cart_item_data'];



    if (!$product_id) {
        wp_send_json_error(array('message' => 'Invalid product ID.'));
        exit;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(array('message' => 'Product not found.'));
        exit;
    }

    if (!WC()->cart) {
        wp_send_json_error(array('message' => 'Cart is not initialized.'));
        exit;
    }

    // Validate variable product amount is within range and valid interval before adding to cart
    $gift_card_price = isset($cart_item_data['gift_card_price']) ? floatval($cart_item_data['gift_card_price']) : 0;
    if ($gift_card_price > 0) {
        $denomination_type = get_field('denomination_type', $product_id);
        if (empty($denomination_type)) {
            $denomination_type = get_post_meta($product_id, 'denomination_type', true);
        }
        if ($denomination_type === 'variable') {

            $min_price = floatval(get_field('variable_range_from', $product_id) ?: get_post_meta($product_id, 'variable_range_from', true));
            $max_price = floatval(get_field('variable_range_to', $product_id) ?: get_post_meta($product_id, 'variable_range_to', true));

            // WooCommerce sale adjustment (FIX for discount issue)
            $regular_price = (float) $product->get_regular_price();
            $sale_price    = (float) $product->get_sale_price();

            if ($product->is_on_sale() && $sale_price > 0) {

                // original values lo (IMPORTANT)
                $original_min = floatval(get_field('variable_range_from', $product_id) ?: get_post_meta($product_id, 'variable_range_from', true));
                $original_max = floatval(get_field('variable_range_to', $product_id) ?: get_post_meta($product_id, 'variable_range_to', true));

                // ratio JS jaisa (FIXED)
                $discount_ratio = $gift_card_price / $original_min;

                // apply correctly
                $min_price = $original_min * $discount_ratio;
                $max_price = $original_max * $discount_ratio;
            }

            // ----------------------------
            // VALIDATION (NO HTML OUTPUT)
            // ----------------------------
            if ($gift_card_price < $min_price || $gift_card_price > $max_price) {

                wp_send_json_error(array(
                    'message' => sprintf(
                        'Amount must be between %s and %s. You selected %s.',
                        number_format($min_price, 2),
                        number_format($max_price, 2),
                        number_format($gift_card_price, 2)
                    )
                ));

                exit;
            }

            // ----------------------------
            // INTERVAL CHECK (CLEAN FIX)
            // ----------------------------
            $price_intervals = get_field('_reedem_at_intervals', $product_id);
            if (empty($price_intervals)) {
                $price_intervals = get_post_meta($product_id, '_reedem_at_intervals', true);
            }

            $price_intervals = floatval($price_intervals ?: 0);

            if ($price_intervals > 0) {
                $remainder = fmod($gift_card_price - $min_price, $price_intervals);
                $tolerance = 0.01;
                $valid_interval = ($remainder >= -$tolerance && $remainder <= $tolerance)
                    || ($remainder >= $price_intervals - $tolerance && $remainder <= $price_intervals + $tolerance);
                if (!$valid_interval) {
                    wp_send_json_error(array(
                        'message' => sprintf(
                            __('Amount must be in increments of %s. Please enter a valid amount.', 'woocommerce'),
                            wc_price($price_intervals)
                        )
                    ));
                    exit;
                }
            }
        }
    }

    // Validate Quantity per Transaction and Total Value per Transaction (when limits are enabled)
    $transaction_limit_checkbox = get_field('add_transaction_limit_checkbox', $product_id);
    if (empty($transaction_limit_checkbox)) {
        $transaction_limit_checkbox = get_post_meta($product_id, 'add_transaction_limit_checkbox', true);
    }
    if ($transaction_limit_checkbox === 'yes' || $transaction_limit_checkbox === 'Yes') {
        $quantity_limit = get_field('_quantity_per_transaction', $product_id);
        if ($quantity_limit === '' || $quantity_limit === null) {
            $quantity_limit = get_post_meta($product_id, '_quantity_per_transaction', true);
        }
        $quantity_limit = intval($quantity_limit);
        $value_limit = get_field('_total_value_per_transaction', $product_id);
        if ($value_limit === '' || $value_limit === null) {
            $value_limit = get_post_meta($product_id, '_total_value_per_transaction', true);
        }
        $value_limit = floatval($value_limit);

        if ($quantity_limit > 0 || $value_limit > 0) {
            $existing_quantity = 0;
            $existing_value = 0.0;
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (isset($cart_item['product_id']) && (int) $cart_item['product_id'] === (int) $product_id) {
                    $existing_quantity += (int) $cart_item['quantity'];
                    $item_price = isset($cart_item['gift_card_price']) ? floatval($cart_item['gift_card_price']) : 0;
                    $existing_value += (float) $cart_item['quantity'] * $item_price;
                }
            }
            $new_total_qty = $existing_quantity + $quantity;
            $new_total_value = $existing_value + ($quantity * $gift_card_price);

            if ($quantity_limit > 0 && $new_total_qty > $quantity_limit) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Quantity limit exceeded for this product. You have %d in cart; limit is %d per transaction.', 'woocommerce'),
                        $existing_quantity,
                        $quantity_limit
                    )
                ));
                exit;
            }
            if ($value_limit > 0 && $new_total_value > $value_limit) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Total value limit exceeded for this product. Current total in cart: %s; limit is %s per transaction.', 'woocommerce'),
                        wc_price($existing_value),
                        wc_price($value_limit)
                    )
                ));
                exit;
            }
        }
    }

    try {
        $new_cart_item_key = gc_add_gift_card_to_cart_with_data($product_id, $quantity, $cart_item_data);
        if (!$new_cart_item_key) {
            wp_send_json_error(array('message' => 'Failed to add product to cart.'));
            exit;
        }
        $cart_count = WC()->cart->get_cart_contents_count();
        if ($cart_count <= 0) {
            wp_send_json_error(array('message' => 'Cart is empty after processing.'));
            exit;
        }
        $fragments = apply_filters('woocommerce_add_to_cart_fragments', array());
        $cart_hash = WC()->cart->get_cart_hash();
        $cart_total = WC()->cart->get_cart_total();
        wp_send_json_success(array(
            'cart_item_key' => $new_cart_item_key,
            'fragments' => $fragments,
            'cart_hash' => $cart_hash,
            'cart_count' => $cart_count,
            'cart_total' => $cart_total,
            'message' => 'Product added to cart successfully.'
        ));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'An error occurred while adding product to cart: ' . $e->getMessage()));
    } catch (Error $e) {
        wp_send_json_error(array('message' => 'A fatal error occurred while adding product to cart.'));
    }
    exit;
}

// Display gift card data in cart and checkout
add_filter('woocommerce_get_item_data', 'display_gift_card_data_in_cart', 10, 2);
function display_gift_card_data_in_cart($item_data, $cart_item)
{
    // Check if this is a gift card item
    if (isset($cart_item['gift_card_price'])) {
        // Add recipient name
        if (!empty($cart_item['recipient_name'])) {
            $item_data[] = array(
                'key' => __('Recipient', 'woocommerce'),
                'value' => wc_clean($cart_item['recipient_name'])
            );
        }

        // Add sender name
        if (!empty($cart_item['sender_name'])) {
            $item_data[] = array(
                'key' => __('From', 'woocommerce'),
                'value' => wc_clean($cart_item['sender_name'])
            );
        }

        // Add recipient email
        if (!empty($cart_item['recipient_email'])) {
            $item_data[] = array(
                'key' => __('Email', 'woocommerce'),
                'value' => wc_clean($cart_item['recipient_email'])
            );
        }

        // Add mobile number
        if (!empty($cart_item['mobile_number'])) {
            $item_data[] = array(
                'key' => __('Mobile Number', 'woocommerce'),
                'value' => wc_clean($cart_item['mobile_number'])
            );
        }

        // Add delivery method
        if (!empty($cart_item['delivery_method'])) {
            $item_data[] = array(
                'key' => __('Delivery', 'woocommerce'),
                'value' => wc_clean($cart_item['delivery_method'])
            );
        }

        // Add gift message
        if (!empty($cart_item['gift_message'])) {
            $item_data[] = array(
                'key' => __('Message', 'woocommerce'),
                'value' => wp_kses_post($cart_item['gift_message'])
            );
        }
    }

    return $item_data;
}

// Hook to set custom price for gift card items in cart
add_action('woocommerce_before_calculate_totals', 'set_gift_card_custom_price', 10, 1);

function set_gift_card_custom_price($cart)
{
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['gift_card_price']) && $cart_item['gift_card_price'] > 0) {
            $selected_price = floatval($cart_item['gift_card_price']);
            $cart_item['data']->set_price($selected_price);
            // Display only the selected price (no "Previous price" / "Discounted price" in checkout)
            $cart_item['data']->set_regular_price($selected_price);
            $cart_item['data']->set_sale_price('');
        }
    }
}
/**
 * Fetch Blackhawk catalog and return valueRestrictions (min/max) for a product by SKU (contentProviderCode).
 * Uses the same catalog API as the Blackhawk Integration plugin. Caches catalog for 1 hour.
 *
 * @param string $sku Product SKU (contentProviderCode).
 * @return array|null ['minimum' => float, 'maximum' => float] or null if not found/API unavailable.
 */
function gc_get_blackhawk_catalog_value_restrictions($sku) {
    if (empty($sku) || !is_string($sku)) {
        return null;
    }
    if (!defined('BLACKHAWK_INTEGRATION_API_URL')) {
        return null;
    }
    $client_program_id = function_exists('gcp_get_bhn_client_program_id') ? gcp_get_bhn_client_program_id() : '';
    $cache_key = 'gc_bhn_catalog_' . md5($client_program_id);
    $catalog = get_transient($cache_key);
    if (false === $catalog) {
        $url = BLACKHAWK_INTEGRATION_API_URL . 'rewardsCatalogProcessing/v1/clientProgram/byKey?clientProgramId=' . urlencode($client_program_id);
        $request_id = uniqid('cat_');
        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'RequestId: ' . $request_id,
                'MerchantId: ' . ( function_exists( 'gcp_get_bhn_merchant_id' ) ? gcp_get_bhn_merchant_id() : '' ),
            ],
        ];
        if (defined('BLACKHAWK_INTEGRATION_SSLCERT') && defined('BLACKHAWK_INTEGRATION_SSLCERTTYPE')) {
            $opts[CURLOPT_SSLCERT]     = BLACKHAWK_INTEGRATION_SSLCERT;
            $opts[CURLOPT_SSLCERTTYPE] = BLACKHAWK_INTEGRATION_SSLCERTTYPE;
            $opts[CURLOPT_SSLCERTPASSWD] = function_exists( 'gcp_get_bhn_ssl_cert_password' ) ? gcp_get_bhn_ssl_cert_password() : '';
        }
        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err || $response === false) {
            return null;
        }
        $data = is_string($response) ? json_decode($response, true) : $response;
        $catalog = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
        set_transient($cache_key, $catalog, HOUR_IN_SECONDS);
    }
    if (empty($catalog) || !is_array($catalog)) {
        return null;
    }
    foreach ($catalog as $product) {
        $code = isset($product['contentProviderCode']) ? $product['contentProviderCode'] : '';
        if ((string) $code === (string) $sku) {
            $min = isset($product['valueRestrictions']['minimum']) ? floatval($product['valueRestrictions']['minimum']) : 0;
            $max = isset($product['valueRestrictions']['maximum']) ? floatval($product['valueRestrictions']['maximum']) : 0;
            return ['minimum' => $min, 'maximum' => $max];
        }
    }
    return null;
}

/**
 * Get the first Blackhawk product's name and SKU from the cart (for error messages).
 *
 * @return array|null ['name' => string, 'sku' => string] or null.
 */
function gc_get_first_blackhawk_cart_product_name_and_sku() {
    if (!function_exists('WC') || !WC()->cart) {
        return null;
    }
    foreach (WC()->cart->get_cart() as $cart_item) {
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
        $name = $product->get_name();
        $sku = $product->get_sku();
        return ['name' => $name ?: __('Gift card', 'woocommerce'), 'sku' => $sku ?: ''];
    }
    return null;
}

/**
 * Pre-submit Blackhawk order at checkout. If BHN rejects, block order and show error on checkout.
 * If BHN accepts, store response in session so send_blackhawk_gift_card_email_on_order can use it (no second API call).
 */
add_action('woocommerce_checkout_process', 'gc_blackhawk_presubmit_on_checkout', 10);

function gc_blackhawk_presubmit_on_checkout() {
    if (!function_exists('WC') || !WC()->cart || !WC()->session) {
        return;
    }

    $existing_errors = function_exists('wc_get_notices') ? wc_get_notices('error') : [];

    if (!empty($existing_errors)) {
        return;
    }
    if (!function_exists('gc_build_bhn_products_from_cart') || !function_exists('gc_extract_bhn_error_message')) {
        return;
    }
    $bhn_products = gc_build_bhn_products_from_cart();
    if (empty($bhn_products)) {
        return;
    }
    $CLIENTPROGRAMID = function_exists('gcp_get_bhn_client_program_id') ? gcp_get_bhn_client_program_id() : '';
    if (empty($CLIENTPROGRAMID) || !function_exists('bhi_submit_order')) {
        return;
    }
    $bhi_output = [
        'clientProgramNumber' => $CLIENTPROGRAMID,
        'paymentType' => 'DRAW_DOWN',
        'millisecondsToWait' => 15000,
        'orderDetails' => $bhn_products,
        'returnCardNumberAndPIN' => 'true',
    ];
    $bhi_uniq_id = uniqid('SGB_');
    $responseData = null;
    try {
        $response = bhi_submit_order($bhi_output, $bhi_uniq_id);
        $responseData = is_string($response) ? json_decode($response, true) : $response;
    } catch (Throwable $e) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $ctx = ['source' => 'blackhawk-checkout'];
            $logger->error('Blackhawk presubmit exception: ' . $e->getMessage(), $ctx);
            $logger->error('Blackhawk presubmit exception trace: ' . $e->getTraceAsString(), $ctx);
        }
        $responseData = null;
    }
    if (isset($responseData['success']) && $responseData['success'] === true) {
        WC()->session->set('bhn_preorder_response', wp_json_encode([
            'response' => $responseData,
            'request_id' => $bhi_uniq_id,
            'request' => $bhi_output,
        ]));
        return;
    }
    $error_reason = gc_extract_bhn_error_message($responseData);
    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $ctx = ['source' => 'blackhawk-checkout'];
        $logger->error('Blackhawk presubmit failed. Extracted error: ' . $error_reason, $ctx);
        $logger->error('Blackhawk presubmit full response: ' . wp_json_encode($responseData), $ctx);
    }
    $msg = __('Blackhawk was unable to process this order: ', 'woocommerce') . $error_reason;
    $is_minmax_error = (stripos($error_reason, 'minimum') !== false || stripos($error_reason, 'maximum') !== false
        || stripos($error_reason, 'Funding amount') !== false || stripos($error_reason, 'less than') !== false
        || stripos($error_reason, 'more than') !== false || stripos($error_reason, 'value restriction') !== false);
    if ($is_minmax_error && function_exists('gc_get_first_blackhawk_cart_product_name_and_sku') && function_exists('gc_get_blackhawk_catalog_value_restrictions')) {
        $first_bhn = gc_get_first_blackhawk_cart_product_name_and_sku();
        if (!empty($first_bhn['sku'])) {
            $restrictions = gc_get_blackhawk_catalog_value_restrictions($first_bhn['sku']);
            if (!empty($restrictions) && (floatval($restrictions['minimum']) > 0 || floatval($restrictions['maximum']) > 0)) {
                $min = floatval($restrictions['minimum']);
                $max = floatval($restrictions['maximum']);
                $product_name = wp_strip_all_tags($first_bhn['name']);
                $msg = sprintf(
                    __('Blackhawk rejected the selected amount for "%1$s". Amount must be between %2$s and %3$s.', 'woocommerce'),
                    $product_name,
                    wc_price($min),
                    wc_price($max)
                );
            }
        }
    }
    wc_add_notice($msg, 'error');
}

/**
 * Copy pre-submitted BHN response from session to order so send_blackhawk_gift_card_email_on_order can use it.
 */
add_action('woocommerce_checkout_order_processed', 'gc_copy_bhn_preorder_response_to_order', 15, 2);

function gc_copy_bhn_preorder_response_to_order($order_id, $posted_data) {
    if (!function_exists('WC') || !WC()->session || !$order_id) {
        return;
    }
    $raw = WC()->session->get('bhn_preorder_response', '');
    if ($raw === '') {
        return;
    }
    $order = wc_get_order($order_id);
    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }
    $order->update_meta_data('_bhn_preorder_response', $raw);
    $order->save();
    WC()->session->__unset('bhn_preorder_response');
}
/**
 * Block checkout if a Blackhawk gift card amount is outside BHN valueRestrictions.
 * This prevents the order from being placed and shows the error on the checkout page.
 */
add_action('woocommerce_checkout_process', 'gc_validate_blackhawk_amount_range_on_checkout', 5);


function gc_validate_blackhawk_amount_range_on_checkout()
{
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        $product_id = isset($cart_item['product_id']) ? (int) $cart_item['product_id'] : 0;
        $variation_id = isset($cart_item['variation_id']) ? (int) $cart_item['variation_id'] : 0;
        if (!$product_id) {
            continue;
        }

        $is_blackhawk = get_post_meta($product_id, '_is_blackhawk_product', true);
        // Only treat as Blackhawk when explicitly marked yes (plugin uses "yes_<productId>").
        $is_blackhawk_yes = ($is_blackhawk === 'yes') || (is_string($is_blackhawk) && strpos($is_blackhawk, 'yes_') === 0);
        if (!$is_blackhawk_yes) {
            continue;
        }

        // Selected amount for variable denomination products is stored in cart item data
        $amount = 0.0;
        if (isset($cart_item['gift_card_price']) && floatval($cart_item['gift_card_price']) > 0) {
            $amount = (float) $cart_item['gift_card_price'];
        } elseif (isset($cart_item['data']) && is_object($cart_item['data']) && method_exists($cart_item['data'], 'get_price')) {
            $amount = (float) $cart_item['data']->get_price();
        }

        // Fetch min/max from Blackhawk get catalog API (not from database)
        $product = isset($cart_item['data']) && is_object($cart_item['data']) ? $cart_item['data'] : wc_get_product($product_id);
        $sku = $product && is_a($product, 'WC_Product') ? $product->get_sku() : '';
        if (empty($sku) || !function_exists('gc_get_blackhawk_catalog_value_restrictions')) {
            continue;
        }
        $restrictions = gc_get_blackhawk_catalog_value_restrictions($sku);
        if (empty($restrictions) || !is_array($restrictions)) {
            continue;
        }
        $min = isset($restrictions['minimum']) ? (float) $restrictions['minimum'] : 0.0;
        $max = isset($restrictions['maximum']) ? (float) $restrictions['maximum'] : 0.0;

        if ($min <= 0 && $max <= 0) {
            continue;
        }

        if (($min > 0 && $amount < $min) || ($max > 0 && $amount > $max)) {
            $product_name = '';
            if (isset($cart_item['data']) && is_object($cart_item['data']) && method_exists($cart_item['data'], 'get_name')) {
                $product_name = $cart_item['data']->get_name();
            } else {
                $product_name = get_the_title($product_id);
            }

            $msg = sprintf(
                __('Blackhawk rejected the selected amount for "%1$s". Amount must be between %2$s and %3$s.', 'woocommerce'),
                wp_strip_all_tags((string) $product_name),
                wc_price($min),
                wc_price($max)
            );
            wc_add_notice($msg, 'error');
        }
    }
}

/**
 * Build per-product quantity and value totals from current cart (for transaction limit validation).
 *
 * @return array [ product_id => [ 'quantity' => int, 'value' => float, 'items' => [ cart_item_key => [ 'qty' => int, 'price' => float ], ... ] ], ... ]
 */
function gc_get_cart_totals_by_product() {
    if (!function_exists('WC') || !WC()->cart) {
        return array();
    }
    $by_product = array();
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = isset($cart_item['product_id']) ? (int) $cart_item['product_id'] : 0;
        if (!$product_id) {
            continue;
        }
        if (!isset($by_product[$product_id])) {
            $by_product[$product_id] = array('quantity' => 0, 'value' => 0.0, 'items' => array());
        }
        $qty = (int) $cart_item['quantity'];
        $price = isset($cart_item['gift_card_price']) ? floatval($cart_item['gift_card_price']) : 0;
        if ($price <= 0 && isset($cart_item['data']) && is_object($cart_item['data']) && method_exists($cart_item['data'], 'get_price')) {
            $price = (float) $cart_item['data']->get_price();
        }
        $by_product[$product_id]['quantity'] += $qty;
        $by_product[$product_id]['value'] += $qty * $price;
        $by_product[$product_id]['items'][$cart_item_key] = array('qty' => $qty, 'price' => $price);
    }
    return $by_product;
}

/**
 * Validate Quantity per Transaction and Total Value per Transaction on cart.
 * Runs when cart is displayed/updated. Adds error notices and enforces limits by reducing quantities if exceeded.
 */
add_action('woocommerce_check_cart_items', 'gc_validate_transaction_limits_on_cart', 20);

function gc_validate_transaction_limits_on_cart() {
    if (!function_exists('WC') || !WC()->cart || !is_cart()) {
        return;
    }

    $by_product = gc_get_cart_totals_by_product();

    foreach ($by_product as $product_id => $totals) {
        $transaction_limit_checkbox = get_field('add_transaction_limit_checkbox', $product_id);
        if (empty($transaction_limit_checkbox)) {
            $transaction_limit_checkbox = get_post_meta($product_id, 'add_transaction_limit_checkbox', true);
        }
        if ($transaction_limit_checkbox !== 'yes' && $transaction_limit_checkbox !== 'Yes') {
            continue;
        }

        $quantity_limit = get_field('_quantity_per_transaction', $product_id);
        if ($quantity_limit === '' || $quantity_limit === null) {
            $quantity_limit = get_post_meta($product_id, '_quantity_per_transaction', true);
        }
        $quantity_limit = intval($quantity_limit);

        $value_limit = get_field('_total_value_per_transaction', $product_id);
        if ($value_limit === '' || $value_limit === null) {
            $value_limit = get_post_meta($product_id, '_total_value_per_transaction', true);
        }
        $value_limit = floatval($value_limit);

        $product_name = get_the_title($product_id);
        $product_name = wp_strip_all_tags((string) $product_name);

        $needs_qty_reduce = $quantity_limit > 0 && $totals['quantity'] > $quantity_limit;
        $needs_value_reduce = $value_limit > 0 && $totals['value'] > $value_limit;

        if ($needs_qty_reduce) {
            wc_add_notice(
                sprintf(
                    __('Quantity limit exceeded for "%1$s". You add %2$d in your cart; the limit is %3$d per transaction. Quantity has been adjusted.', 'woocommerce'),
                    $product_name,
                    $totals['quantity'],
                    $quantity_limit
                ),
                'error'
            );
            gc_enforce_cart_quantity_limit_for_product($product_id, $totals['items'], $quantity_limit);
            // Re-read cart for this product after quantity enforcement so value check uses current state
            $by_product = gc_get_cart_totals_by_product();
            if (isset($by_product[$product_id])) {
                $totals = $by_product[$product_id];
                $needs_value_reduce = $value_limit > 0 && $totals['value'] > $value_limit;
            }
        }

        if ($needs_value_reduce) {
            wc_add_notice(
                sprintf(
                    __('Total value limit exceeded for "%1$s". Total in your cart is %2$s; the limit is %3$s per transaction. Quantity has been adjusted.', 'woocommerce'),
                    $product_name,
                    wc_price($totals['value']),
                    wc_price($value_limit)
                ),
                'error'
            );
            gc_enforce_cart_value_limit_for_product($product_id, $totals['items'], $value_limit);
        }
    }
}

/**
 * Set cart item quantities for a product so total quantity does not exceed limit.
 */
function gc_enforce_cart_quantity_limit_for_product($product_id, $items, $quantity_limit) {
    if (!function_exists('WC') || !WC()->cart || $quantity_limit <= 0 || empty($items)) {
        return;
    }
    $remaining = $quantity_limit;
    foreach ($items as $cart_item_key => $item) {
        $new_qty = min((int) $item['qty'], max(0, $remaining));
        if ($new_qty !== (int) $item['qty']) {
            WC()->cart->set_quantity($cart_item_key, $new_qty, true);
        }
        $remaining -= $new_qty;
    }
}

/**
 * Reduce cart item quantities for a product until total value does not exceed limit.
 */
function gc_enforce_cart_value_limit_for_product($product_id, $items, $value_limit) {
    if (!function_exists('WC') || !WC()->cart || $value_limit <= 0 || empty($items)) {
        return;
    }
    $total_value = 0.0;
    foreach ($items as $cart_item_key => $item) {
        $total_value += (float) $item['qty'] * (float) $item['price'];
    }
    if ($total_value <= $value_limit) {
        return;
    }
    $to_reduce_value = $total_value - $value_limit;
    foreach ($items as $cart_item_key => $item) {
        if ($to_reduce_value <= 0) {
            break;
        }
        $price = (float) $item['price'];
        $qty = (int) $item['qty'];
        if ($price <= 0 || $qty <= 0) {
            continue;
        }
        $value_of_one = $price;
        $units_to_remove = (int) ceil($to_reduce_value / $value_of_one);
        $units_to_remove = min($units_to_remove, $qty);
        if ($units_to_remove > 0) {
            $new_qty = $qty - $units_to_remove;
            WC()->cart->set_quantity($cart_item_key, max(0, $new_qty), true);
            $to_reduce_value -= $units_to_remove * $value_of_one;
        }
    }
}

/**
 * Validate Quantity per Transaction and Total Value per Transaction at checkout.
 * Blocks order placement if any product in the cart exceeds its transaction limits.
 */
add_action('woocommerce_checkout_process', 'gc_validate_transaction_limits_on_checkout', 6);

function gc_validate_transaction_limits_on_checkout()
{
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $by_product = gc_get_cart_totals_by_product();

    foreach ($by_product as $product_id => $totals) {
        $transaction_limit_checkbox = get_field('add_transaction_limit_checkbox', $product_id);
        if (empty($transaction_limit_checkbox)) {
            $transaction_limit_checkbox = get_post_meta($product_id, 'add_transaction_limit_checkbox', true);
        }
        if ($transaction_limit_checkbox !== 'yes' && $transaction_limit_checkbox !== 'Yes') {
            continue;
        }

        $quantity_limit = get_field('_quantity_per_transaction', $product_id);
        if ($quantity_limit === '' || $quantity_limit === null) {
            $quantity_limit = get_post_meta($product_id, '_quantity_per_transaction', true);
        }
        $quantity_limit = intval($quantity_limit);

        $value_limit = get_field('_total_value_per_transaction', $product_id);
        if ($value_limit === '' || $value_limit === null) {
            $value_limit = get_post_meta($product_id, '_total_value_per_transaction', true);
        }
        $value_limit = floatval($value_limit);

        $product_name = get_the_title($product_id);
        $product_name = wp_strip_all_tags((string) $product_name);

        if ($quantity_limit > 0 && $totals['quantity'] > $quantity_limit) {
            wc_add_notice(
                sprintf(
                    __('Quantity limit exceeded for "%1$s". You have %2$d in your order; the limit is %3$d per transaction.', 'woocommerce'),
                    $product_name,
                    $totals['quantity'],
                    $quantity_limit
                ),
                'error'
            );
        }
        if ($value_limit > 0 && $totals['value'] > $value_limit) {
            wc_add_notice(
                sprintf(
                    __('Total value limit exceeded for "%1$s". Total in your order is %2$s; the limit is %3$s per transaction.', 'woocommerce'),
                    $product_name,
                    wc_price($totals['value']),
                    wc_price($value_limit)
                ),
                'error'
            );
        }
    }
}

// Ensure gift card cart items show only selected price in Blocks checkout (no regular/sale split)
add_filter('woocommerce_cart_item_product', 'gift_card_cart_item_single_price_display', 10, 3);

function gift_card_cart_item_single_price_display($product, $cart_item, $cart_item_key)
{
    if (!isset($cart_item['gift_card_price']) || floatval($cart_item['gift_card_price']) <= 0) {
        return $product;
    }
    $selected_price = floatval($cart_item['gift_card_price']);
    $product->set_price($selected_price);
    $product->set_regular_price($selected_price);
    $product->set_sale_price('');
    return $product;
}
// AJAX handler for getting cart contents
add_action('wp_ajax_get_cart_contents', 'get_cart_contents_handler');
add_action('wp_ajax_nopriv_get_cart_contents', 'get_cart_contents_handler');

function get_cart_contents_handler()
{
    ob_clean();

    if (!class_exists('WooCommerce') || !WC()->cart) {
        wp_send_json_error(array('message' => 'Cart is not available.'));
        exit;
    }

    $cart_contents = array();
    foreach (WC()->cart->get_cart() as $key => $cart_item) {
        $cart_contents[$key] = array(
            'product_id' => $cart_item['product_id'],
            'quantity' => $cart_item['quantity'],
            'gift_card_price' => isset($cart_item['gift_card_price']) ? $cart_item['gift_card_price'] : null
        );
    }

    wp_send_json_success(array('cart_contents' => $cart_contents));
    exit;
}

// Register post type offers

require_once get_template_directory() . '/admin-offers.php';
include get_template_directory() . '/templates/offer-for-you.php';

// Blackhawk API Test Matrix (admin page)
    // require_once get_template_directory() . '/api-test-matrix.php';



// AJAX handler for adding new contact in Participants Database
add_action('wp_ajax_pdb_add_contact', 'pdb_add_contact_handler');

// AJAX handler for deleting contacts from Participants Database
add_action('wp_ajax_pdb_delete_contacts', 'pdb_delete_contacts_handler');

function pdb_add_contact_handler()
{
    gcp_require_admin_ajax();
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pdb_add_contact')) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in to add contacts.'));
        return;
    }

    // Check if Participants Database is active
    if (!class_exists('Participants_Db')) {
        wp_send_json_error(array('message' => 'Participants Database plugin is not active.'));
        return;
    }

    // Sanitize and validate input data
    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? sanitize_text_field($_POST['middle_name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? sanitize_text_field($_POST['date_of_birth']) : '';
    $reminder = isset($_POST['reminder']) && $_POST['reminder'] === 'Yes' ? 'Yes' : '';

    // Validate required fields
    if (empty($first_name)) {
        wp_send_json_error(array('message' => 'First Name is required.'));
        return;
    }

    if (empty($last_name)) {
        wp_send_json_error(array('message' => 'Surname is required.'));
        return;
    }

    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array('message' => 'Valid email address is required.'));
        return;
    }

    // Get current user ID
    $current_user_id = get_current_user_id();

    // Prepare data array for Participants Database
    $participant_data = array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'middle_name' => $middle_name,
        'email' => $email,
        'phone' => $phone,
        'date_of_birth' => $date_of_birth,
        'reminder' => $reminder,
    );

    // Add user ID field - check if field exists, if not we'll store it via a different method
    // First, try to add it as a regular field
    $user_id_field_name = 'created_by_user_id';

    // Check if the field exists in Participants Database
    if (PDb_Form_Field_Def::is_field($user_id_field_name)) {
        $participant_data[$user_id_field_name] = $current_user_id;
    }

    // Remove empty fields (but keep user_id even if it's 0, though that shouldn't happen)
    $participant_data = array_filter($participant_data, function ($value) {
        return $value !== '';
    });

    // Create the participant record
    $record_id = Participants_Db::write_participant($participant_data, '', 'Add Contact Form');

    // If the field doesn't exist, store user ID in a custom way
    // We'll use the database directly to add a column if needed, or use a meta approach
    if ($record_id && is_numeric($record_id)) {
        // Store user ID directly in database if field doesn't exist
        if (!PDb_Form_Field_Def::is_field($user_id_field_name)) {
            global $wpdb;
            $table_name = Participants_Db::$participants_table;

            // Check if column exists, if not add it
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                $user_id_field_name
            ));

            if (empty($column_exists)) {
                if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ||
                     ! preg_match( '/^[a-zA-Z0-9_]+$/', $user_id_field_name ) ) {
                    return;
                }
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN {$user_id_field_name} INT(11) NULL DEFAULT NULL");
            }

            // Update the record with user ID
            $wpdb->update(
                $table_name,
                array($user_id_field_name => $current_user_id),
                array('id' => $record_id),
                array('%d'),
                array('%d')
            );
        }

        wp_send_json_success(array(
            'message' => 'Contact added successfully!',
            'record_id' => $record_id
        ));
    } else {
        // Check for validation errors
        if (is_object(Participants_Db::$validation_errors) && Participants_Db::$validation_errors->errors_exist()) {
            $errors = Participants_Db::$validation_errors->get_validation_errors();
            $error_message = 'Validation error: ';
            foreach ($errors as $field => $error) {
                if (is_object($error)) {
                    $error_message .= $error->error_message() . ' ';
                } else {
                    $error_message .= $error . ' ';
                }
            }
            wp_send_json_error(array('message' => trim($error_message)));
        } else {
            wp_send_json_error(array('message' => 'Failed to add contact. Please try again.'));
        }
    }
}

/**
 * Disable server-side pagination for custom template to allow DataTables to handle pagination
 * This modifies the list_limit attribute to -1 (show all records) when using default-custom template
 * This ensures all records are loaded so DataTables can paginate them client-side
 */
add_action('pdb-shortcode_set', 'pdb_disable_pagination_for_datatables', 10, 1);
function pdb_disable_pagination_for_datatables($shortcode_object)
{
    // Check if this is a list shortcode and using the custom template
    if (
        is_object($shortcode_object) &&
        isset($shortcode_object->shortcode_atts) &&
        isset($shortcode_object->shortcode_atts['template']) &&
        $shortcode_object->shortcode_atts['template'] === 'default-custom'
    ) {

        // Set list_limit to -1 to disable server-side pagination and load all records
        // This allows DataTables to handle pagination client-side
        $shortcode_object->shortcode_atts['list_limit'] = '-1';
    }
}

/**
 * Fallback: Modify pagination configuration to set very high limit
 * This runs after _set_list_limit() and ensures all records are loaded
 */
add_filter('pagination_configuration', 'pdb_set_high_pagination_limit_for_datatables', 10, 1);
function pdb_set_high_pagination_limit_for_datatables($config)
{
    if (class_exists('PDb_List')) {
        try {
            $reflection = new ReflectionClass('PDb_List');
            if ($reflection->hasStaticProperty('instance')) {
                $instance_prop = $reflection->getStaticProperty('instance');
                if (
                    is_object($instance_prop) &&
                    isset($instance_prop->shortcode_atts['template']) &&
                    $instance_prop->shortcode_atts['template'] === 'default-custom'
                ) {

                    // Set a very high limit to effectively disable server-side pagination
                    $config['size'] = 999999;
                }
            }
        } catch (Exception $e) {
            // Silently fail
        }
    }
    return $config;
}

/**
 * Filter Participants Database list query to show only participants created by current user
 * Uses the action hook to modify the query object before the query is built
 */
add_action('pdb-list_query_object', 'pdb_filter_list_by_user_id', 10, 1);
function pdb_filter_list_by_user_id($list_query)
{
    // Only apply filter if user is logged in
    if (!is_user_logged_in()) {
        return;
    }

    // Get current user ID
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return;
    }

    // Field name used to store user ID
    $user_id_field_name = 'created_by_user_id';

    // Check if the field/column exists
    $field_exists = false;
    global $wpdb;
    $table_name = Participants_Db::$participants_table;

    // First check if column exists in database directly (more reliable)
    $column_check = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        $table_name,
        $user_id_field_name
    ) );

    if ($column_check > 0) {
        $field_exists = true;
    } elseif (class_exists('PDb_Form_Field_Def') && PDb_Form_Field_Def::is_field($user_id_field_name)) {
        $field_exists = true;
    }

    if (!$field_exists) {
        // Field/column doesn't exist yet, don't filter (show all records for backward compatibility)
        return;
    }

    // Add filter using the query object's method
    // We'll modify the SQL directly via the filter hook
    add_filter('pdb-list_query', function ($query) use ($user_id_field_name, $current_user_id) {
        // Escape the field name for SQL
        $escaped_field = esc_sql($user_id_field_name);
        $user_id = intval($current_user_id);

        // Build the filter condition - show only records created by current user
        // For backward compatibility during transition, also show records where user_id is NULL
        // (existing records created before this feature was implemented)
        $filter_condition = "(`{$escaped_field}` = {$user_id} OR `{$escaped_field}` IS NULL)";

        // Check if query already contains our filter (prevent double filtering)
        if (stripos($query, $escaped_field) !== false) {
            return $query;
        }

        // Simple and robust approach: append condition to WHERE or add WHERE
        // Find WHERE keyword position
        $where_pos = stripos($query, ' WHERE ');

        if ($where_pos !== false) {
            // WHERE exists - find where to insert (before ORDER BY, GROUP BY, LIMIT, or end)
            $after_where = substr($query, $where_pos + 7); // +7 for " WHERE "

            // Find positions of ORDER BY, GROUP BY, LIMIT
            $order_pos = stripos($after_where, ' ORDER BY ');
            $group_pos = stripos($after_where, ' GROUP BY ');
            $limit_pos = stripos($after_where, ' LIMIT ');

            // Find the earliest position
            $insert_pos = false;
            if ($order_pos !== false)
                $insert_pos = ($insert_pos === false) ? $order_pos : min($insert_pos, $order_pos);
            if ($group_pos !== false)
                $insert_pos = ($insert_pos === false) ? $group_pos : min($insert_pos, $group_pos);
            if ($limit_pos !== false)
                $insert_pos = ($insert_pos === false) ? $limit_pos : min($insert_pos, $limit_pos);

            if ($insert_pos !== false) {
                // Insert before ORDER/GROUP/LIMIT
                $before = substr($query, 0, $where_pos + 7 + $insert_pos);
                $after = substr($query, $where_pos + 7 + $insert_pos);
                $query = rtrim($before) . ' AND ' . $filter_condition . $after;
            } else {
                // No ORDER/GROUP/LIMIT, append at end
                $query = rtrim($query) . ' AND ' . $filter_condition;
            }
        } else {
            // No WHERE - find where to insert (before ORDER BY, GROUP BY, LIMIT, or end)
            $order_pos = stripos($query, ' ORDER BY ');
            $group_pos = stripos($query, ' GROUP BY ');
            $limit_pos = stripos($query, ' LIMIT ');

            // Find the earliest position
            $insert_pos = false;
            if ($order_pos !== false)
                $insert_pos = ($insert_pos === false) ? $order_pos : min($insert_pos, $order_pos);
            if ($group_pos !== false)
                $insert_pos = ($insert_pos === false) ? $group_pos : min($insert_pos, $group_pos);
            if ($limit_pos !== false)
                $insert_pos = ($insert_pos === false) ? $limit_pos : min($insert_pos, $limit_pos);

            if ($insert_pos !== false) {
                // Insert before ORDER/GROUP/LIMIT
                $before = substr($query, 0, $insert_pos);
                $after = substr($query, $insert_pos);
                $query = rtrim($before) . ' WHERE ' . $filter_condition . $after;
            } else {
                // No ORDER/GROUP/LIMIT, append WHERE at end
                $query = rtrim($query) . ' WHERE ' . $filter_condition;
            }
        }

        return $query;
    }, 20); // Higher priority to run after other filters
}

function pdb_delete_contacts_handler()
{
    gcp_require_admin_ajax();
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pdb_delete_contacts')) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
        return;
    }

    // Check if Participants Database is active
    if (!class_exists('Participants_Db')) {
        wp_send_json_error(array('message' => 'Participants Database plugin is not active.'));
        return;
    }

    // Get record IDs to delete
    $record_ids = isset($_POST['record_ids']) ? $_POST['record_ids'] : array();

    if (empty($record_ids) || !is_array($record_ids)) {
        wp_send_json_error(array('message' => 'No records selected for deletion.'));
        return;
    }

    // Sanitize record IDs
    $record_ids = array_map('intval', $record_ids);
    $record_ids = array_filter($record_ids, function ($id) {
        return $id > 0;
    });

    if (empty($record_ids)) {
        wp_send_json_error(array('message' => 'Invalid record IDs.'));
        return;
    }

    global $wpdb;
    $table_name = Participants_Db::$participants_table;

    // Prepare the SQL query
    $placeholders = implode(',', array_fill(0, count($record_ids), '%d'));
    $sql = "DELETE FROM {$table_name} WHERE id IN ({$placeholders})";

    // Execute the delete query
    $result = $wpdb->query($wpdb->prepare($sql, $record_ids));

    if ($result !== false && $result > 0) {
        // Clear cache if the class exists
        if (class_exists('PDb_Participant_Cache')) {
            PDb_Participant_Cache::make_all_stale();
        }

        wp_send_json_success(array(
            'message' => sprintf(
                _n(
                    '%d contact deleted successfully.',
                    '%d contacts deleted successfully.',
                    $result,
                    'textdomain'
                ),
                $result
            ),
            'deleted_count' => $result
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete contacts. Please try again.'));
    }
}
// AJAX handler for resending gift cards
function resend_gift_card_ajax_handler()
{
    // Prevent any output before JSON response
    if (ob_get_level()) {
        ob_clean();
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'resend_gift_card_nonce')) {
        wp_send_json_error('Security check failed. Please refresh the page and try again.');
        return;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to resend gift cards.');
        return;
    }

    $current_user_id    = get_current_user_id();
    $current_user_data  = get_userdata( $current_user_id );
    $current_user_email = $current_user_data ? strtolower( trim( $current_user_data->user_email ) ) : '';

    $gift_card_ids = isset($_POST['gift_card_ids']) ? sanitize_text_field($_POST['gift_card_ids']) : '';
    $recipient_email = isset($_POST['recipient_email']) ? sanitize_email($_POST['recipient_email']) : '';

    if (empty($gift_card_ids) || empty($recipient_email)) {
        wp_send_json_error('Missing required parameters.');
    }

    // Handle both single ID and comma-separated IDs
    $gift_card_id_array = explode(',', $gift_card_ids);
    $gift_card_id_array = array_map('trim', $gift_card_id_array); // Remove whitespace
    $gift_card_id_array = array_map('intval', $gift_card_id_array);
    $gift_card_id_array = array_filter($gift_card_id_array); // Remove empty values

    if (empty($gift_card_id_array)) {
        wp_send_json_error('Invalid gift card IDs.');
    }

    // Get gift card data and resend
    $sent_count = 0;
    $failed_count = 0;
    $errors = [];

    foreach ($gift_card_id_array as $gift_card_id) {
        if (empty($gift_card_id) || $gift_card_id <= 0) {
            continue; // Skip invalid IDs
        }
        // Check if it's a gift_card post type
        $gift_card_post = get_post($gift_card_id);

        if ($gift_card_post && $gift_card_post->post_type === 'gift_card') {
            // Get order ID
            $order_id = get_post_meta($gift_card_id, '_order_id', true);
            $order = $order_id ? wc_get_order($order_id) : null;

            // Ownership verification: order must belong to current user, or recipient email must match current user
            $order_owner_id        = $order ? (int) $order->get_customer_id() : 0;
            $gc_recipient          = strtolower( trim( get_post_meta( $gift_card_id, '_recipient_email', true ) ) );
            $order_belongs_to_user = ( $order_owner_id > 0 && $order_owner_id === $current_user_id );
            $recipient_is_user     = ( ! empty( $current_user_email ) && $gc_recipient === $current_user_email );
            if ( ! $order_belongs_to_user && ! $recipient_is_user ) {
                $errors[]     = 'Gift card ID ' . $gift_card_id . ': Access denied.';
                $failed_count++;
                continue;
            }

            if ($order) {
                // Find the order item that matches this gift card
                $order_item = null;
                $gift_card_sku = get_post_meta($gift_card_id, '_product_sku', true);

                foreach ($order->get_items() as $item) {
                    $item_gift_card_post_id = wc_get_order_item_meta($item->get_id(), '_gift_card_post_id', true);
                    $item_recipient_email = wc_get_order_item_meta($item->get_id(), '_recipient_email', true);
                    $item_sku = wc_get_order_item_meta($item->get_id(), '_gift_card_sku', true);

                    // Match by gift card post ID, recipient email, or SKU
                    if (
                        $item_gift_card_post_id == $gift_card_id ||
                        ($item_recipient_email === $recipient_email && ($item_sku === $gift_card_sku || empty($gift_card_sku)))
                    ) {
                        $order_item = $item;
                        break;
                    }
                }

                // Get product data from order (matching original send logic)
                $meta_key = $order_id . '_pro_details';
                $pro_data = get_post_meta($order_id, $meta_key, true);

                // Get gift card name from order item or product
                $gift_card_name = '';
                if ($order_item) {
                    $gift_card_name = wc_get_order_item_meta($order_item->get_id(), '_gift_card_name', true);
                    if (empty($gift_card_name)) {
                        $gift_card_name = $order_item->get_name();
                    }
                }
                if (empty($gift_card_name)) {
                    $product_sku = get_post_meta($gift_card_id, '_product_sku', true);
                    if ($product_sku) {
                        $product_id = wc_get_product_id_by_sku($product_sku);
                        if ($product_id) {
                            $gift_card_name = get_the_title($product_id);
                        }
                    }
                }

                // Get gift card image from order item or gift card post
                $gift_card_image = '';
                if ($order_item) {
                    $gift_card_image = wc_get_order_item_meta($order_item->get_id(), '_gift_card_image', true);
                }
                if (empty($gift_card_image)) {
                    $gift_card_image = get_post_meta($gift_card_id, '_image_url', true);
                }
                if (empty($gift_card_image) && $product_sku) {
                    $product_id = wc_get_product_id_by_sku($product_sku);
                    if ($product_id) {
                        $product = wc_get_product($product_id);
                        if ($product) {
                            $image_id = $product->get_image_id();
                            if ($image_id) {
                                $gift_card_image = wp_get_attachment_url($image_id);
                            }
                        }
                    }
                }

                // Get sender info from order
                $sender_name = $order->get_meta('_sender_name', true);
                if (empty($sender_name)) {
                    $sender_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                }
                if (empty($sender_name)) {
                    $sender_name = 'Gift Cards Plus';
                }

                $sender_email = $order->get_meta('_sender_email', true);
                if (empty($sender_email)) {
                    $sender_email = $order->get_billing_email();
                }
                if (empty($sender_email)) {
                    $sender_email = get_option('admin_email');
                }

                // Get card PIN (matching original send logic)
                $gcard_pin = get_post_meta($gift_card_id, 'gcard_security_pin', true);
                if (empty($gcard_pin)) {
                    $gcard_pin = str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
                    update_post_meta($gift_card_id, 'gcard_security_pin', $gcard_pin);
                }

                // Get card number - try to decrypt from encrypted version first, then fallback
                $card_number = '';
                $encrypted_card_number = get_post_meta($gift_card_id, '_gift_card_number_enc', true);

                if (!empty($encrypted_card_number)) {
                    // Load decryption functions if not already loaded
                    // if (!function_exists('decrypt_giftcard_no')) {
                    //     require_once(get_template_directory() . '/inc/gc_number_functions.php');
                    // }

                    // Get encryption key
                    // $encryption_key = defined('GIFTCARD_ENCRYPTION_KEY') ? GIFTCARD_ENCRYPTION_KEY : (defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : 'default-encryption-key-change-me');

                    // try {
                    //     $card_number = decrypt_giftcard_no($encrypted_card_number);
                    // } catch (Exception $e) {
                    //     // If decryption fails, try fallback
                    // $card_number = get_post_meta($gift_card_id, '_gift_card_number_enc', true);

                    // Prefer the newer AES decrypt function (matches encrypt_giftcard_no())
                    if (function_exists('decrypt_giftcard_no')) {
                        try {
                            $card_number = decrypt_giftcard_no($encrypted_card_number);
                        } catch (Exception $e) {
                            // fall through to legacy decrypt below
                        }
                    }

                    // Legacy fallback decrypt (older encryption implementation)
                    if (empty($card_number)) {
                        // Load legacy decryption functions if not already loaded
                        if (!function_exists('decrypt_giftcard')) {
                            require_once(get_template_directory() . '/inc/gc_number_functions.php');
                        }

                        // Get legacy encryption key
                        $encryption_key = function_exists('gcp_get_gift_card_encryption_key') ? gcp_get_gift_card_encryption_key() : ( defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '' );

                        try {
                            $card_number = decrypt_giftcard($encrypted_card_number, $encryption_key);
                        } catch (Exception $e) {
                            // If decryption fails, try plaintext fallback
                            $card_number = get_post_meta($gift_card_id, '_gift_card_number', true);
                        }
                    }
                } else {
                    // Try plain text version
                    $card_number = get_post_meta($gift_card_id, '_gift_card_number', true);
                }

                // Final fallback to invoice number (as used in original send for gc_order)
                if (empty($card_number)) {
                    $card_number = get_post_meta($gift_card_id, '_invoice_number', true);
                }

                // Get amount/price
                $amount = get_post_meta($gift_card_id, '_price', true);
                if (empty($amount) && $order_item) {
                    $amount = wc_get_order_item_meta($order_item->get_id(), '_gift_card_price', true);
                }
                if (empty($amount)) {
                    $amount = $order_item ? $order_item->get_total() : '0.00';
                }

                $admin_email = get_option('admin_email');

                // Prepare gift card data for resending with all required fields
                $gift_card_data = [
                    'gift_card_post_id' => $gift_card_id,
                    'recipient_name' => get_post_meta($gift_card_id, '_recipient_name', true),
                    'recipient_email' => $recipient_email,
                    'order_product_data' => $pro_data,
                    'gift_card_name' => $gift_card_name,
                    'image_url' => $gift_card_image,
                    'price' => $amount,
                    'amount' => $amount,
                    'card_number' => $card_number,
                    'gift_card_number' => $card_number,
                    'card_pin' => $gcard_pin,
                    'sender_name' => $sender_name,
                    'sender_email' => $sender_email,
                    '_activation_expiry_date' => get_post_meta($gift_card_id, '_activation_expiry_date', true),
                    'logo_giftcardplus' => wp_get_attachment_url('6230'),
                    'logo_brand_main' => wp_get_attachment_url('5824'),
                    'logo_brand_top' => wp_get_attachment_url('5108'),
                    'logo_footer' => wp_get_attachment_url('5370'),
                    'email_logo' => wp_get_attachment_url('5371'),
                    'gift_card_id' => $gift_card_id,
                    'support_email' => $admin_email,
                ];

                // Include the email sending function
                if (!function_exists('send_giftcard_email_with_pdf')) {
                    require_once(get_template_directory() . '/inc/gc-pdf-functions.php');
                }

                // Resend using the same function as original send
                try {
                    $result = send_giftcard_email_with_pdf($gift_card_data, $recipient_email, $order_id);

                    // Update status to match original send (line 5176 in functions.php)
                    update_post_meta($gift_card_id, '_gift_card_send', 'Sent After Completion');
                    $sent_count++;
                } catch (Exception $e) {
                    $errors[] = 'Gift card ID ' . $gift_card_id . ': ' . $e->getMessage();
                    $failed_count++;
                } catch (Error $e) {
                    $errors[] = 'Gift card ID ' . $gift_card_id . ': ' . $e->getMessage();
                    $failed_count++;
                }
            } else {
                $errors[] = 'Gift card ID ' . $gift_card_id . ': Order not found';
                $failed_count++;
            }
        } else {
            $errors[] = 'Gift card ID ' . $gift_card_id . ': Invalid gift card post';
            $failed_count++;
        }
    }

    if ($sent_count > 0) {
        $message = $sent_count . ' gift card(s) resent successfully to ' . $recipient_email . '.';
        if ($failed_count > 0) {
            $message .= ' (' . $failed_count . ' failed)';
        }
        wp_send_json_success([
            'message' => $message,
            'sent_count' => $sent_count,
            'failed_count' => $failed_count
        ]);
    } else {
        $error_msg = 'Failed to resend gift card(s).';
        if (!empty($errors)) {
            $error_msg .= ' Errors: ' . implode('; ', $errors);
        }
        wp_send_json_error($error_msg);
    }
}
add_action('wp_ajax_resend_gift_card', 'resend_gift_card_ajax_handler');

add_action('wp_ajax_get_business_user_paid_balance', 'get_business_user_paid_balance_callback');

function get_business_user_paid_balance_callback()
{
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        return;
    }
    $user_id = intval($_GET['user_id']);

    if (!$user_id || !get_userdata($user_id)) {
        wp_send_json_error(['message' => 'Invalid user ID']);
    }

    $balance = get_user_meta($user_id, 'float_balance', true);
    $balance = ($balance !== '' && $balance !== null) ? floatval($balance) : 0;
    // echo $balance;
    wp_send_json_success(['balance' => $balance]);
}

// FAQ Search Bar Shortcode
function faq_search_bar_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'placeholder' => 'Search FAQs...',
        'class' => '',
    ), $atts);

    $unique_id = 'faq-search-' . uniqid();

    ob_start();
    ?>
    <div class="faq-search-container <?php echo esc_attr($atts['class']); ?>" id="<?php echo esc_attr($unique_id); ?>">
        <div class="faq-search-wrapper">
            <input type="text" class="faq-search-input" id="<?php echo esc_attr($unique_id); ?>-input"
                placeholder="<?php echo esc_attr($atts['placeholder']); ?>" autocomplete="off" />
                <span class="faq-clear-search" id="<?php echo esc_attr($unique_id); ?>-clear" style="display: none;">&times;</span>
            <span class="search-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M21.8189 23.6359L16.6812 18.4895C15.8734 19.1907 14.9309 19.7369 13.8539 20.128C12.7768 20.5191 11.6324 20.7147 10.4207 20.7147C7.51259 20.7147 5.04878 19.7032 3.02927 17.6803C1.00976 15.6574 0 13.2164 0 10.3573C0 7.49828 1.00976 5.05729 3.02927 3.03438C5.04878 1.01146 7.49913 0 10.3803 0C13.2345 0 15.6647 1.01146 17.6707 3.03438C19.6768 5.05729 20.6798 7.49828 20.6798 10.3573C20.6798 11.5171 20.4913 12.6365 20.1143 13.7154C19.7374 14.7943 19.1719 15.8057 18.418 16.7498L23.6365 21.8962C23.8788 22.1119 24 22.3884 24 22.7256C24 23.0627 23.8654 23.3662 23.5961 23.6359C23.3538 23.8786 23.0576 24 22.7075 24C22.3575 24 22.0613 23.8786 21.8189 23.6359ZM10.3803 18.2872C12.5614 18.2872 14.4193 17.5117 15.9542 15.9608C17.489 14.4099 18.2564 12.5421 18.2564 10.3573C18.2564 8.17259 17.489 6.30476 15.9542 4.75386C14.4193 3.20295 12.5614 2.4275 10.3803 2.4275C8.1723 2.4275 6.29415 3.20295 4.74586 4.75386C3.19756 6.30476 2.42342 8.17259 2.42342 10.3573C2.42342 12.5421 3.19756 14.4099 4.74586 15.9608C6.29415 17.5117 8.1723 18.2872 10.3803 18.2872Z"
                        fill="#202224"></path>
                </svg>
            </span>
        </div>
        <div class="faq-search-results-info" id="<?php echo esc_attr($unique_id); ?>-info" style="display: none;">
            <span class="faq-results-count"></span>
        </div>
    </div>


    <script>
        (function ($) {
            'use strict';

            // Wait for DOM and WPBakery to fully load
            $(document).ready(function () {
                setTimeout(function () {
                    var searchInput = $('#<?php echo esc_js($unique_id); ?>-input');
                    var clearButton = $('#<?php echo esc_js($unique_id); ?>-clear');
                    var resultsInfo = $('#<?php echo esc_js($unique_id); ?>-info');
                    var resultsCount = resultsInfo.find('.faq-results-count');

                     // Function to handle clear button visibility
                    function handleClearButtonVisibility() {
                        if (searchInput.val().trim().length > 0) {
                            clearButton[0].style.setProperty('display', 'block', 'important');
                            clearButton.addClass('show');
                        } else {
                            clearButton[0].style.setProperty('display', 'none', 'important');
                            clearButton.removeClass('show');
                        }
                    }

                    // Clear button click handler
                    clearButton.on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        searchInput.val('').focus();
                        handleClearButtonVisibility();
                        searchFAQs('');
                    });

                    // Function to get all text from an element (including hidden content)
                    function getAllText($element) {
                        if (!$element || $element.length === 0) return '';
                        var text = '';
                        // Clone to avoid modifying original
                        var $clone = $element.clone();
                        // Remove script and style tags
                        $clone.find('script, style').remove();
                        // Get all text
                        text = $clone.text();
                        return text.replace(/\s+/g, ' ').trim().toLowerCase();
                    }

                    // Function to search through accordion items
                    function searchFAQs(searchTerm) {
                        var searchLower = searchTerm.toLowerCase().trim();
                        var matchCount = 0;
                        var totalItems = 0;

                        // Search through WPBakery accordion panels (vc_tta_accordion)
                        $('.vc_tta-panel').each(function () {
                            totalItems++;
                            var $panel = $(this);
                            var itemText = '';

                            // Get title text - try multiple ways
                            var titleText = '';
                            // Try finding title link first
                            var $titleLink = $panel.find('.vc_tta-panel-title a, .vc_tta-panel-heading a').first();
                            if ($titleLink.length) {
                                titleText = $titleLink.text().trim();
                            } else {
                                // Try title element
                                var $title = $panel.find('.vc_tta-panel-title, .vc_tta-panel-heading, .vc_tta-title-text').first();
                                if ($title.length) {
                                    titleText = $title.text().trim();
                                }
                            }

                            if (titleText) {
                                itemText += ' ' + titleText.toLowerCase();
                            }

                            // Get content text from panel body - try to get even if hidden
                            var $content = $panel.find('.vc_tta-panel-body, .vc_tta-panel-body-inner').first();
                            if ($content.length) {
                                var contentText = getAllText($content);
                                if (contentText) {
                                    itemText += ' ' + contentText;
                                }
                            }

                            // If still no text, get all text from panel (excluding title to avoid duplication)
                            if (!itemText || itemText.trim() === '') {
                                var allPanelText = getAllText($panel);
                                itemText = allPanelText;
                            }

                            // Clean up itemText
                            itemText = itemText.replace(/\s+/g, ' ').trim();

                            // Check if search term matches
                            var matches = searchTerm === '' || (itemText && itemText.indexOf(searchLower) !== -1);

                            if (matches) {
                                $panel.removeClass('faq-item-hidden');
                                $panel.css({
                                    'display': '',
                                    'visibility': 'visible'
                                });
                                matchCount++;
                            } else {
                                $panel.addClass('faq-item-hidden');
                                $panel.css({
                                    'display': 'none',
                                    'visibility': 'hidden'
                                });
                            }
                        });

                        // Also search through legacy toggle elements
                        $('.vc_toggle, .wpb_toggle').each(function () {
                            totalItems++;
                            var $item = $(this);
                            var itemText = '';

                            // Get title
                            var $title = $item.find('.vc_toggle_title, .wpb_toggle_title, h3, h4, h5').first();
                            if ($title.length) {
                                itemText += ' ' + $title.text().trim().toLowerCase();
                            }

                            // Get content
                            var $content = $item.find('.vc_toggle_content, .wpb_toggle_content').first();
                            if ($content.length) {
                                var contentText = getAllText($content);
                                if (contentText) {
                                    itemText += ' ' + contentText;
                                }
                            }

                            // If no specific elements, get all text
                            if (!itemText || itemText.trim() === '') {
                                itemText = getAllText($item);
                            }

                            // Clean up
                            itemText = itemText.replace(/\s+/g, ' ').trim();

                            // Check match
                            var matches = searchTerm === '' || (itemText && itemText.indexOf(searchLower) !== -1);

                            if (matches) {
                                $item.removeClass('faq-item-hidden');
                                $item.css({
                                    'display': '',
                                    'visibility': 'visible'
                                });
                                matchCount++;
                            } else {
                                $item.addClass('faq-item-hidden');
                                $item.css({
                                    'display': 'none',
                                    'visibility': 'hidden'
                                });
                            }
                        });

                        // Hide subheadings (accordion containers) that have no visible panels
                       $('.vc_tta-container').each(function() {

    var $container = $(this);
    var $wrap = $container.closest('.home-accordion-wrap');
    var $heading = $wrap.find('.faq-head').first();

    var hasVisiblePanel = false;

    $container.find('.vc_tta-panel, .vc_toggle, .wpb_toggle').each(function() {
        if (!$(this).hasClass('faq-item-hidden')) {
            hasVisiblePanel = true;
            return false;
        }
    });

    if (searchTerm.trim() !== '') {

        if (!hasVisiblePanel) {
            $container.addClass('faq-section-hidden');
            $heading.addClass('faq-heading-hidden');   // THIS is the fix
        } else {
            $container.removeClass('faq-section-hidden');
            $heading.removeClass('faq-heading-hidden');
        }

    } else {
        // search cleared
        $container.removeClass('faq-section-hidden');
        $heading.removeClass('faq-heading-hidden');
    }

});

                        // Update results info
                        if (searchTerm.trim() !== '') {
                            resultsCount.text(matchCount + ' result' + (matchCount !== 1 ? 's' : '') + ' found');
                            resultsInfo.show();

                             // Hide all FAQ section headings if 0 results found
                           /* if (matchCount === 0) {
                                // Hide all FAQ headings
                                $('.faq-head, .wpb_text_column.faq-head, .home-accordion-wrap .wpb_text_column.faq-head').each(function() {
                                    var $heading = $(this);
                                    $heading.addClass('faq-heading-hidden');
                                });
                                
                                // Hide entire row wrappers that only contain headings
                                $('.home-accordion-wrap').each(function() {
                                    var $row = $(this);
                                    var $headings = $row.find('.faq-head, .wpb_text_column.faq-head');
                                    var $accordions = $row.find('.vc_tta-container');
                                    var hasVisibleContent = false;
                                    
                                    // Check if any accordion container is visible
                                    $accordions.each(function() {
                                        var $accordion = $(this);
                                        if (!$accordion.hasClass('faq-section-hidden') && 
                                            $accordion.css('display') !== 'none' && 
                                            $accordion.css('visibility') !== 'hidden') {
                                            hasVisibleContent = true;
                                            return false; // Break loop
                                        }
                                    });
                                    
                                    // If no visible content and this row has headings, hide the row
                                    if (!hasVisibleContent && $headings.length > 0) {
                                        $row.addClass('faq-row-hidden');
                                        $row.css({
                                            'display': 'none',
                                            'visibility': 'hidden'
                                        });
                                    }
                                });
                            } else {
                                // Show headings when there are results
                                $('.faq-head, .wpb_text_column.faq-head, .home-accordion-wrap .wpb_text_column.faq-head').each(function() {
                                    var $heading = $(this);
                                    $heading.removeClass('faq-heading-hidden');
                                });
                                
                                // Show row wrappers
                                $('.home-accordion-wrap').each(function() {
                                    var $row = $(this);
                                    var $accordions = $row.find('.vc_tta-container');
                                    var hasVisibleContent = false;
                                    
                                    // Check if any accordion container is visible
                                    $accordions.each(function() {
                                        var $accordion = $(this);
                                        if (!$accordion.hasClass('faq-section-hidden') && 
                                            $accordion.css('display') !== 'none' && 
                                            $accordion.css('visibility') !== 'hidden') {
                                            hasVisibleContent = true;
                                            return false; // Break loop
                                        }
                                    });
                                    
                                    // Show row if it has visible content
                                    if (hasVisibleContent) {
                                        $row.removeClass('faq-row-hidden');
                                        $row.css({
                                            'display': '',
                                            'visibility': 'visible'
                                        });
                                    }
                                });
                            }*/
                        } else {
                            resultsInfo.hide();
                            // Show all FAQ headings when search is cleared
                            $('.faq-head, .wpb_text_column.faq-head, .home-accordion-wrap .wpb_text_column.faq-head').each(function() {
                                var $heading = $(this);
                                $heading.removeClass('faq-heading-hidden');
                            });
                            
                            // Show all row wrappers when search is cleared
                            $('.home-accordion-wrap').each(function() {
                                var $row = $(this);
                                $row.removeClass('faq-row-hidden');
                                $row.css({
                                    'display': '',
                                    'visibility': 'visible'
                                });
                            });
                        }
                        // Update clear button visibility
                        handleClearButtonVisibility();
                    }

                    // Search on input with debounce
                    var searchTimeout;
                    searchInput.on('input keyup', function () {
                        /// Update clear button visibility immediately
                        handleClearButtonVisibility();

                        clearTimeout(searchTimeout);
                        var $input = $(this);
                        searchTimeout = setTimeout(function () {
                            var searchTerm = $input.val();
                            searchFAQs(searchTerm);
                        }, 100);
                    });

                    // Clear search on escape
                    searchInput.on('keydown', function (e) {
                        if (e.key === 'Escape' || e.keyCode === 27) {
                            $(this).val('');
                            handleClearButtonVisibility();
                            searchFAQs('');
                        }
                    });

                    // Initial call to ensure all items are visible
                    searchFAQs('');
                    handleClearButtonVisibility();
                }, 1000);
            });
        })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('faq_search', 'faq_search_bar_shortcode');

// Remove billing address and contact information from checkout using WooCommerce hooks

// Method 1: Remove billing fields via woocommerce_checkout_fields filter (Traditional Checkout)
add_filter('woocommerce_checkout_fields', 'remove_billing_contact_fields_from_checkout');
function remove_billing_contact_fields_from_checkout($fields)
{
    // Remove entire billing section
    unset($fields['billing']);

    // Remove shipping section if not needed
    // unset($fields['shipping']);

    return $fields;
}

// Method 2: Remove billing section rendering via action hook (Traditional Checkout)
add_action('woocommerce_checkout_init', 'remove_checkout_billing_section');
function remove_checkout_billing_section()
{
    remove_action('woocommerce_checkout_billing', array(WC()->checkout(), 'checkout_form_billing'));
}

// Method 3: Remove customer details section wrapper (Traditional Checkout)
add_action('woocommerce_checkout_init', 'remove_customer_details_actions');
function remove_customer_details_actions()
{
    remove_action('woocommerce_checkout_billing', array(WC()->checkout(), 'checkout_form_billing'));
    remove_action('woocommerce_checkout_shipping', array(WC()->checkout(), 'checkout_form_shipping'));
}

// Method 4: Remove checkout blocks via render_block filter (WooCommerce Blocks Checkout)
add_filter('render_block', 'remove_checkout_contact_billing_blocks', 10, 2);
function remove_checkout_contact_billing_blocks($block_content, $block)
{
    if (!is_checkout()) {
        return $block_content;
    }

    // Blocks to remove
    $block_names_to_remove = array(
        'woocommerce/checkout-contact-information-block',
        'woocommerce/checkout-billing-address-block',
        'woocommerce/checkout-order-summary-coupon-form-block'
    );

    if (isset($block['blockName']) && in_array($block['blockName'], $block_names_to_remove)) {
        return ''; // Return empty string to remove the block completely
    }

    return $block_content;
}

// Map custom payment fields to billing fields on checkout (runs before validation so billing_email is set)
add_action('woocommerce_checkout_process', 'process_custom_payment_fields', 5);
function process_custom_payment_fields()
{
    if (!empty($_POST['payment_full_name'])) {
        $name_parts = explode(' ', sanitize_text_field($_POST['payment_full_name']), 2);
        if (count($name_parts) >= 2) {
            $_POST['billing_first_name'] = $name_parts[0];
            $_POST['billing_last_name'] = $name_parts[1];
        } else {
            $_POST['billing_first_name'] = $name_parts[0];
            $_POST['billing_last_name'] = '';
        }
    }

    if (!empty($_POST['payment_country'])) {
        $_POST['billing_country'] = sanitize_text_field($_POST['payment_country']);
        // Set state to default if not provided
        if (empty($_POST['billing_state'])) {
            $countries = WC()->countries->get_states($_POST['billing_country']);
            if (!empty($countries)) {
                $_POST['billing_state'] = key($countries);
            }
        }
    }

    if (!empty($_POST['payment_address'])) {
        $_POST['billing_address_1'] = sanitize_text_field($_POST['payment_address']);
    }

    // Ensure email is set when billing is not displayed: contact_email (theme), logged-in user, payment_email, or session
    if (empty($_POST['billing_email'])) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $_POST['billing_email'] = $user->user_email;
        } elseif (!empty($_POST['contact_email']) && is_email($_POST['contact_email'])) {
            $_POST['billing_email'] = sanitize_email($_POST['contact_email']);
        } elseif (!empty($_POST['payment_email'])) {
            $_POST['billing_email'] = sanitize_email($_POST['payment_email']);
        } elseif (function_exists('WC') && WC()->customer && WC()->customer->get_email()) {
            $_POST['billing_email'] = WC()->customer->get_email();
        } else {
            // Fallback so validation does not fail (order email set later in ensure_order_billing_email)
            $_POST['billing_email'] = sanitize_email(get_option('admin_email'));
        }
    }

    // Set default city and postcode if not provided (required by some payment gateways)
    if (empty($_POST['billing_city'])) {
        $_POST['billing_city'] = '';
    }
    if (empty($_POST['billing_postcode'])) {
        $_POST['billing_postcode'] = '';
    }



    if (!is_user_logged_in() && !empty($_POST['contact_email']) && is_email($_POST['contact_email'])) {
        $_POST['contact_email'] = sanitize_email($_POST['contact_email']);
        $_POST['createaccount'] = 1;
    }


}

// T&C compulsory: block checkout if Terms and Conditions are not accepted. Marketing remains optional.
add_action('woocommerce_checkout_process', 'gc_validate_accept_terms_on_checkout', 10);
function gc_validate_accept_terms_on_checkout() {

    
    if(!is_user_logged_in()){
        if (empty($_POST['contact_email']) || !is_email($_POST['contact_email'])) {
            wc_add_notice(
                __('Please enter your email address to continue.', 'woocommerce'),
                'error'
            );
        } else if (empty($_POST['contact_phone']) && !is_email($_POST['contact_phone'])) {
            wc_add_notice(
                __('You must enter phone number to continue.', 'woocommerce'),
                'error'
            );
            
        } else if (empty($_POST['accept_terms']) || (isset($_POST['accept_terms']) && $_POST['accept_terms'] !== '1')) {
            wc_add_notice(
                __('You must accept the Terms and Conditions to continue.', 'woocommerce'),
                'error'
            );
        }
    }
}

// Ensure billing_email field exists but is not required when billing is hidden (avoids "required field" error)
add_filter('woocommerce_checkout_fields', 'ensure_checkout_email_field', 99);
function ensure_checkout_email_field($fields)
{
    if (!isset($fields['billing']['billing_email'])) {
        $fields['billing']['billing_email'] = array(
            'type'     => 'email',
            'label'    => __('Email address', 'woocommerce'),
            'required' => false, // Not required so validation passes when field is hidden; value set in process_custom_payment_fields
            'class'    => array('form-row-wide', 'hidden-field'),
            'priority' => 25,
        );
    } else {
        $fields['billing']['billing_email']['required'] = false;
        $fields['billing']['billing_email']['class'][]   = 'hidden-field';
    }

    // Guest checkout: account is created automatically with auto-generated password — do not require account_password.
    if (isset($fields['account']['account_password'])) {
        $fields['account']['account_password']['required'] = false;
    }
    return $fields;
}

// Ensure the order has a billing email when it is created (fallback if posted value was empty)
add_action('woocommerce_checkout_create_order', 'ensure_order_billing_email', 10, 2);
function ensure_order_billing_email($order, $data)
{
    if ($order && ('' === $order->get_billing_email() || !is_email($order->get_billing_email()))) {
        if (is_user_logged_in()) {
            $order->set_billing_email(wp_get_current_user()->user_email);
        } elseif (!empty($data['billing_email']) && is_email($data['billing_email'])) {
            $order->set_billing_email($data['billing_email']);
        } else {
            $order->set_billing_email(sanitize_email(get_option('admin_email')));
        }
    }
}
// Register guest user role (used for accounts created at checkout with contact_email + password).
add_action('init', 'register_guest_user_role', 20);
function register_guest_user_role()
{
    if (get_role('guest')) {
        return;
    }
    add_role(
        'guest',
        __('Guest', 'twentytwentyone'),
        array(
            'read' => true,
        )
    );
}

// When a user is created at checkout (contact_email + password / create account), assign guest role instead of customer.
add_filter('woocommerce_new_customer_data', 'set_checkout_created_user_role_to_guest', 10, 1);
function set_checkout_created_user_role_to_guest($customer_data)
{
    if (!isset($customer_data['role']) || $customer_data['role'] !== 'customer') {
        return $customer_data;
    }
    // Only switch to guest when account is created during checkout (createaccount checked, email from contact_email or billing).
    $is_checkout_create_account = !empty($_POST['createaccount'])
        && (!empty($_POST['contact_email']) || !empty($_POST['billing_email']));
    if ($is_checkout_create_account) {
        $customer_data['role'] = 'guest';
    }
    return $customer_data;
}


// Do not keep guest users logged in after placing an order — log them out immediately after checkout.
add_action('woocommerce_checkout_order_processed', 'logout_guest_user_after_checkout', 999, 2);
function logout_guest_user_after_checkout($order_id, $posted_data)
{
    $order = $order_id && function_exists('wc_get_order') ? wc_get_order($order_id) : null;
    if (!$order) {
        return;
    }
    $customer_id = $order->get_customer_id();
    if (!$customer_id) {
        return;
    }
    $user = get_userdata($customer_id);
    if (!$user || empty($user->roles) || !in_array('guest', (array) $user->roles, true)) {
        return;
    }
    wp_clear_auth_cookie();
    wp_set_current_user(0);
}

// Disable WooCommerce Blocks checkout to use traditional template
add_filter('woocommerce_has_block_template', '__return_false', 10, 2);

// Force use of traditional checkout template
add_filter('woocommerce_is_block_template', '__return_false', 10);


/**
 * Force classic Cart/Checkout shortcodes on their pages.
 *
 * This ensures cart/checkout rendering uses WooCommerce's classic shortcode templates
 * (and therefore the theme overrides like `woocommerce/checkout/form-checkout.php`)
 * instead of relying on the Cart/Checkout blocks in the page editor.
 *
 * Note: This does NOT affect order-pay / order-received endpoints.
 */
add_filter('the_content', function ($content) {
    if (is_admin() || wp_doing_ajax()) {
        return $content;
    }

    // Cart page: force shortcode output.
    if (function_exists('is_cart') && is_cart()) {
        // Avoid double-render if shortcode already present.
        if (has_shortcode($content, 'woocommerce_cart')) {
            return $content;
        }
        return do_shortcode('[woocommerce_cart]');
    }

    // Checkout page: force shortcode output, but don't override order-pay / order-received endpoints.
    if (function_exists('is_checkout') && is_checkout()) {
        if (function_exists('is_wc_endpoint_url') && (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received'))) {
            return $content;
        }
        if (has_shortcode($content, 'woocommerce_checkout')) {
            return $content;
        }
        return do_shortcode('[woocommerce_checkout]');
    }

    return $content;
}, 1);

// Enqueue checkout CSS and JS
add_action('wp_enqueue_scripts', 'enqueue_checkout_assets', 99);
function enqueue_checkout_assets()
{
    if (is_checkout()) {
        $checkout_css = get_template_directory() . '/assets/css/checkout.css';
        wp_enqueue_style(
            'checkout-css',
            get_template_directory_uri() . '/assets/css/checkout.css',
            array(),
            file_exists($checkout_css) ? filemtime($checkout_css) : null
        );

        wp_enqueue_script(
            'checkout-js',
            get_template_directory_uri() . '/assets/js/checkout.js',
            array('jquery', 'wc-checkout'),
            time(),
            true
        );

        // Localize script with WooCommerce checkout params and cart items
        $cart_items = array();
        $user_wishlist = array();

        // Get user wishlist if logged in
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $wishlist = get_user_meta($user_id, 'user_wishlist', true);
            if (is_array($wishlist)) {
                $user_wishlist = array_filter(array_map('intval', $wishlist));
            }
        }

        if (function_exists('WC') && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $_product = $cart_item['data'];
                if ($_product && $_product->exists()) {
                    $product_id = $cart_item['product_id'];
                    $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;

                    // Use variation_id if available, otherwise use product_id
                    $check_id = ($variation_id > 0) ? $variation_id : $product_id;

                    // Check if product is in wishlist
                    $is_in_wishlist = in_array($check_id, $user_wishlist) || in_array($product_id, $user_wishlist);

                    // Gift card image: cart item meta or session (session-based, no AJAX)
                    $selected_image = '';
                    if (!empty($cart_item['card_design']) && is_string($cart_item['card_design'])) {
                        $selected_image = $cart_item['card_design'];
                    } elseif (WC()->session) {
                        $session_design = WC()->session->get('gc_card_design_' . $cart_item_key);
                        if (!empty($session_design)) {
                            $selected_image = $session_design;
                        }
                    }
                    if (empty($selected_image)) {
                        $image_id = $_product->get_image_id();
                        $selected_image = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
                    }

                    $cart_items[] = array(
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'product_name' => $_product->get_name(),
                        'cart_item_key' => $cart_item_key,
                        'key' => $cart_item_key,
                        'is_in_wishlist' => $is_in_wishlist,
                        'selected_image' => $selected_image,
                        'gift_message' => isset($cart_item['gift_message']) ? $cart_item['gift_message'] : '',
                        'recipient_name' => isset($cart_item['recipient_name']) ? $cart_item['recipient_name'] : '',
                        'recipient_email' => isset($cart_item['recipient_email']) ? $cart_item['recipient_email'] : '',
                        'mobile_number' => isset($cart_item['mobile_number']) ? $cart_item['mobile_number'] : '',
                        'sender_name' => isset($cart_item['sender_name']) ? $cart_item['sender_name'] : '',
                        'sender_email' => (is_user_logged_in() && function_exists('wp_get_current_user')) ? wp_get_current_user()->user_email : ''
                    );
                }
            }
        }

        // Use a separate global so we don't overwrite WooCommerce's wc_checkout_params
        // (which has checkout_url, wc_ajax_url, etc.). Overwriting caused "Unexpected token '<'"
        // when placing an order because core checkout received HTML instead of JSON.
        $customer_email = '';
        if (function_exists('WC') && WC()->customer) {
            $customer_email = WC()->customer->get_email();
        }
        if (empty($customer_email) && is_user_logged_in()) {
            $customer_email = wp_get_current_user()->user_email;
        }
        wp_localize_script('checkout-js', 'theme_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'update_order_review_nonce' => wp_create_nonce('update-order-review'),
            'cart_items' => $cart_items,
            'user_wishlist' => $user_wishlist,
            'customer_email' => $customer_email,
        ));
    }
}

// Calculate and add delivery cost, fulfillment cost, and GST as fees to cart
add_action('woocommerce_cart_calculate_fees', 'add_delivery_cost_as_fee', 10, 1);
function add_delivery_cost_as_fee($cart)
{
    // Safety checks
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Skip during add-to-cart AJAX to prevent interference with cart persistence
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'gc_add_to_cart') {
            return;
        }
    }

    if (!$cart || $cart->is_empty()) {
        return;
    }

    try {
        // Calculate totals for delivery, fulfillment, GST, and shipping class cost
        $total_delivery_cost = 0;
        $total_fulfillment_cost = 0;
        $total_gst = 0;
        $total_shipping_class_cost = 0;

        $cart_items = $cart->get_cart();
        if (empty($cart_items) || !is_array($cart_items)) {
            return;
        }

        foreach ($cart_items as $cart_item_key => $cart_item) {
            if (empty($cart_item['product_id']) || empty($cart_item['quantity'])) {
                continue;
            }

            $product_id = absint($cart_item['product_id']);
            $quantity = absint($cart_item['quantity']);

            if ($product_id <= 0 || $quantity <= 0) {
                continue;
            }

            // Get product object
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            // Get delivery cost from ACF field 'delivery_cost'
            $delivery_cost = 0;
            if (function_exists('get_field')) {
                $delivery_cost = get_field('delivery_cost', $product_id);
            }

            // Fallback to product meta if ACF field doesn't exist
            if (empty($delivery_cost) || !is_numeric($delivery_cost)) {
                $delivery_cost = get_post_meta($product_id, '_delivery_cost', true);
            }

            // Get fulfillment cost from product meta (j_a_c_fulfillment_cost - J&C Fulfillment Cost)
            $fulfillment_cost = get_post_meta($product_id, 'j_a_c_fulfillment_cost', true);
            // Fallback to _supplier_fullfillment_price if j_a_c_fulfillment_cost is empty
            if (empty($fulfillment_cost) || !is_numeric($fulfillment_cost)) {
                $fulfillment_cost = get_post_meta($product_id, '_supplier_fullfillment_price', true);
            }
            if (empty($fulfillment_cost) || !is_numeric($fulfillment_cost)) {
                $fulfillment_cost = 0;
            }

            // Get GST from product meta
            $gst = get_post_meta($product_id, '_gst', true);
            if (empty($gst) || !is_numeric($gst)) {
                $gst = 0;
            }
            // Get shipping class cost (SMS delivery) - only for items with delivery_method sms or email_sms; default 1 when no shipping class
            $shipping_class_cost = 0;
            $item_delivery_method = isset($cart_item['delivery_method']) ? $cart_item['delivery_method'] : '';
            if (in_array($item_delivery_method, array('sms', 'email_sms'), true)) {
                $shipping_class_id = $product->get_shipping_class_id();
                if ($shipping_class_id > 0) {
                    $shipping_class_term = get_term($shipping_class_id, 'product_shipping_class');
                    if ($shipping_class_term && !is_wp_error($shipping_class_term)) {
                        $term_name = $shipping_class_term->name;
                        if (preg_match('/\$(\d+\.?\d*)/', $term_name, $matches)) {
                            $shipping_class_cost = floatval($matches[1]);
                        }
                        if ($shipping_class_cost == 0) {
                            $term_meta_cost = get_term_meta($shipping_class_id, 'cost', true);
                            if (!empty($term_meta_cost) && is_numeric($term_meta_cost)) {
                                $shipping_class_cost = floatval($term_meta_cost);
                            }
                        }
                    }
                }
                if ($shipping_class_cost <= 0 || !is_finite($shipping_class_cost)) {
                    $shipping_class_cost = 1; // Default SMS delivery cost when no shipping class
                }
            }

            // Convert to float and multiply by quantity
            $delivery_cost = is_numeric($delivery_cost) ? (float) $delivery_cost : 0;
            $fulfillment_cost = is_numeric($fulfillment_cost) ? (float) $fulfillment_cost : 0;
            $gst = is_numeric($gst) ? (float) $gst : 0;
            $shipping_class_cost = is_numeric($shipping_class_cost) ? (float) $shipping_class_cost : 0;

            // Ensure values are finite and valid
            if (!is_finite($delivery_cost) || is_nan($delivery_cost)) {
                $delivery_cost = 0;
            }
            if (!is_finite($fulfillment_cost) || is_nan($fulfillment_cost)) {
                $fulfillment_cost = 0;
            }
            if (!is_finite($gst) || is_nan($gst)) {
                $gst = 0;
            }
            if (!is_finite($shipping_class_cost) || is_nan($shipping_class_cost)) {
                $shipping_class_cost = 0;
            }

            $total_delivery_cost += $delivery_cost * $quantity;
            $total_fulfillment_cost += $fulfillment_cost * $quantity;
            $total_gst += $gst * $quantity;
            $total_shipping_class_cost += $shipping_class_cost * $quantity;
        }

        // Ensure totals are finite and valid
        if (!is_finite($total_delivery_cost) || is_nan($total_delivery_cost)) {
            $total_delivery_cost = 0;
        }
        if (!is_finite($total_fulfillment_cost) || is_nan($total_fulfillment_cost)) {
            $total_fulfillment_cost = 0;
        }
        if (!is_finite($total_gst) || is_nan($total_gst)) {
            $total_gst = 0;
        }
        if (!is_finite($total_shipping_class_cost) || is_nan($total_shipping_class_cost)) {
            $total_shipping_class_cost = 0;
        }

        // Get existing fees
        $existing_fees = $cart->get_fees();
        if (!is_array($existing_fees)) {
            $existing_fees = array();
        }

        // Check if our fees exist and if amounts need updating
        $needs_update = false;
        $existing_delivery_amount = 0;
        $existing_fulfillment_amount = 0;
        $existing_gst_amount = 0;
        $existing_shipping_class_amount = 0;

        foreach ($existing_fees as $fee_key => $fee) {
            if (!is_object($fee) || empty($fee->name)) {
                continue;
            }

            $fee_name = $fee->name;
            $fee_amount = isset($fee->amount) ? (float) $fee->amount : 0;

            if ($fee_name === 'Delivery' || $fee_name === 'Delivery Cost') {
                $existing_delivery_amount = $fee_amount;
                if (abs($fee_amount - $total_delivery_cost) > 0.01) {
                    $needs_update = true;
                }
            } elseif ($fee_name === 'Fullfillment Cost' || $fee_name === 'Fulfillment Cost') {
                $existing_fulfillment_amount = $fee_amount;
                if (abs($fee_amount - $total_fulfillment_cost) > 0.01) {
                    $needs_update = true;
                }
            } elseif ($fee_name === 'GST' || $fee_name === 'GST Cost') {
                $existing_gst_amount = $fee_amount;
                if (abs($fee_amount - $total_gst) > 0.01) {
                    $needs_update = true;
                }
            } elseif ($fee_name === 'Shipping Class Cost' || $fee_name === 'Shipping Cost') {
                $existing_shipping_class_amount = $fee_amount;
                if (abs($fee_amount - $total_shipping_class_cost) > 0.01) {
                    $needs_update = true;
                }
            }
        }

        // Only update if amounts changed or fees don't exist
        $delivery_needs_add = ($total_delivery_cost > 0) && (abs($existing_delivery_amount - $total_delivery_cost) > 0.01);
        $fulfillment_needs_add = ($total_fulfillment_cost > 0) && (abs($existing_fulfillment_amount - $total_fulfillment_cost) > 0.01);
        $gst_needs_add = ($total_gst > 0) && (abs($existing_gst_amount - $total_gst) > 0.01);
        $shipping_class_needs_add = ($total_shipping_class_cost > 0) && (abs($existing_shipping_class_amount - $total_shipping_class_cost) > 0.01);

        // If no updates needed and all fees exist, return early
        if (
            !$needs_update &&
            ($total_delivery_cost == 0 || $existing_delivery_amount > 0) &&
            ($total_fulfillment_cost == 0 || $existing_fulfillment_amount > 0) &&
            ($total_gst == 0 || $existing_gst_amount > 0) &&
            ($total_shipping_class_cost == 0 || $existing_shipping_class_amount > 0)
        ) {
            return;
        }

        // Store other fees (not ours) temporarily
        $other_fees = array();
        foreach ($existing_fees as $other_fee_key => $other_fee) {
            if (!is_object($other_fee) || empty($other_fee->name)) {
                continue;
            }

            $fee_name = $other_fee->name;
            if (
                $fee_name !== 'Delivery' && $fee_name !== 'Delivery Cost' &&
                $fee_name !== 'Fullfillment Cost' && $fee_name !== 'Fulfillment Cost' &&
                $fee_name !== 'GST' && $fee_name !== 'GST Cost' &&
                $fee_name !== 'Shipping Class Cost' && $fee_name !== 'Shipping Cost'
            ) {
                $other_fees[] = array(
                    'name' => $other_fee->name,
                    'amount' => isset($other_fee->amount) ? (float) $other_fee->amount : 0,
                    'taxable' => isset($other_fee->taxable) ? $other_fee->taxable : false,
                    'tax_class' => isset($other_fee->tax_class) ? $other_fee->tax_class : '',
                );
            }
        }

        // Only remove and re-add fees if update is needed
        if ($needs_update || $delivery_needs_add || $fulfillment_needs_add || $gst_needs_add || $shipping_class_needs_add) {
            // Remove all fees and re-add (to update amounts)
            $cart->fees_api()->remove_all_fees();

            // Re-add other fees first
            foreach ($other_fees as $fee_data) {
                if (!empty($fee_data['name']) && is_finite($fee_data['amount']) && !is_nan($fee_data['amount'])) {
                    $cart->add_fee($fee_data['name'], $fee_data['amount'], $fee_data['taxable'], $fee_data['tax_class']);
                }
            }

            // Order: Shipping Class Cost, Fullfillment Cost, GST Cost (Delivery Cost not added)
            if ($total_shipping_class_cost > 0) {
                $cart->add_fee('Shipping Class Cost', $total_shipping_class_cost, false);
            }

            if ($total_fulfillment_cost > 0) {
                $cart->add_fee('Fullfillment Cost', $total_fulfillment_cost, false);
            }

            if ($total_gst > 0) {
                $cart->add_fee('GST Cost', $total_gst, false);
            }
        }
    } catch (Exception $e) {
        // Log error but don't break the cart
        return;
    } catch (Error $e) {
        // Log fatal error but don't break the cart
        return;
    }
}
/**
 * Add Stripe processing fee (1.7% + $0.30) when Stripe is selected.
 *
 * Note: On checkout, the selected gateway is often sent via AJAX (`payment_method`)
 * before `chosen_payment_method` is persisted to the session. We check both.
 */
// add_action('woocommerce_cart_calculate_fees', 'gc_add_stripe_processing_fee', 9999, 1);

// function gc_add_stripe_processing_fee($cart)
// {
//     if (is_admin() && !defined('DOING_AJAX')) return;
//     if (!$cart || $cart->is_empty()) return;

//     // Don't block checkout AJAX (this was breaking it)
//     // REMOVE THIS:
//     // if (!is_checkout() && !defined('DOING_AJAX')) return;

//     // Detect payment method
//     $chosen_method = WC()->session->get('chosen_payment_method');
//     if (isset($_POST['payment_method'])) {
//         $chosen_method = sanitize_text_field($_POST['payment_method']);
//     }

//     // Remove existing Stripe fee
//     foreach ($cart->get_fees() as $key => $fee) {
//         if (stripos($fee->name, 'stripe processing fee') !== false) {
//             unset($cart->fees_api()->fees[$key]);
//         }
//     }

//     // Stop if not Stripe
//     if ($chosen_method !== 'stripe') return;

//     // Correct base (this works reliably)
//     $base  = (float) $cart->get_cart_contents_total();
//     $base += (float) $cart->get_cart_contents_tax();
//     $base += (float) $cart->get_shipping_total();
//     $base += (float) $cart->get_shipping_tax();
//     $base -= (float) $cart->get_discount_total();
//     $base -= (float) $cart->get_discount_tax();

//     if ($base <= 0) return;

//     // Stripe fee (per transaction)
//     $fee_amount = ($base * 0.017) + 0.30;

//     // Round properly
//     $fee_amount = round($fee_amount, wc_get_price_decimals());

//     if ($fee_amount <= 0) return;

//     // Add fee
//     $cart->add_fee('Stripe Processing Fee', $fee_amount, false);
// }

// AJAX handler to get total delivery cost from cart items
add_action('wp_ajax_get_cart_delivery_cost', 'get_cart_delivery_cost');
add_action('wp_ajax_nopriv_get_cart_delivery_cost', 'get_cart_delivery_cost');
function get_cart_delivery_cost()
{
    if (!function_exists('WC') || WC()->cart->is_empty()) {
        wp_send_json_success(array('delivery_cost' => 0));
        return;
    }

    $total_delivery_cost = 0;

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];

        // Get delivery cost from ACF field 'delivery_cost'
        $delivery_cost = 0;
        if (function_exists('get_field')) {
            $delivery_cost = get_field('delivery_cost', $product_id);
        }

        // Fallback to product meta if ACF field doesn't exist
        if (empty($delivery_cost) || !is_numeric($delivery_cost)) {
            $delivery_cost = get_post_meta($product_id, '_delivery_cost', true);
        }

        // Convert to float and multiply by quantity
        $delivery_cost = is_numeric($delivery_cost) ? (float) $delivery_cost : 0;
        $total_delivery_cost += $delivery_cost * $quantity;
    }

    wp_send_json_success(array('delivery_cost' => $total_delivery_cost));
}

// AJAX handler for updating cart item quantity on checkout
add_action('wp_ajax_update_checkout_item_quantity', 'update_checkout_item_quantity');
add_action('wp_ajax_nopriv_update_checkout_item_quantity', 'update_checkout_item_quantity');
function update_checkout_item_quantity()
{

    // PREVENT HTML OUTPUT DURING AJAX
    if ( defined('DOING_AJAX') && DOING_AJAX ) {
        wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
    }

    // (optional but recommended)
    if ( ! WC()->cart ) {
        wp_send_json_error(['message' => 'Cart not initialized']);
    }
    
    if ( empty($_POST['cart_item_key']) || empty($_POST['quantity']) ) {
        wp_send_json_error(['message' => 'Invalid parameters']);
    }

    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $quantity      = max(1, intval($_POST['quantity']));

    if ( ! WC()->cart ) {
        wp_send_json_error(['message' => 'Cart not available']);
    }

    WC()->cart->set_quantity($cart_item_key, $quantity, true);

    wp_send_json_success([
        'message'    => 'Quantity updated',
        'cart_total' => WC()->cart->get_cart_total()
    ]);

    // wp_die();
}

// AJAX handler to remove a cart item from checkout
add_action('wp_ajax_remove_checkout_item', 'remove_checkout_item');
add_action('wp_ajax_nopriv_remove_checkout_item', 'remove_checkout_item');
function remove_checkout_item()
{
    if (defined('DOING_AJAX') && DOING_AJAX) {
        wc_maybe_define_constant('WOOCOMMERCE_CART', true);
    }
    if (!WC()->cart) {
        wp_send_json_error(['message' => 'Cart not initialized']);
    }
    if (empty($_POST['cart_item_key'])) {
        wp_send_json_error(['message' => 'Invalid parameters']);
    }
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    WC()->cart->remove_cart_item($cart_item_key);
    wp_send_json_success([
        'message'   => 'Item removed',
        'checkout_url' => wc_get_checkout_url(),
    ]);
}

add_action('init', function () {

    if (!isset($_GET['export_blackhawk_all_meta'])) {
        return;
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=blackhawk-products-all-meta.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Product ID',
        'Product Name',
        'SKU',
        'Meta Key',
        'Meta Value'
    ]);

    $products = wc_get_products([
        'limit' => -1,
        'status' => ['publish', 'draft'],
        'meta_query' => [
            [
                'key' => '_is_blackhawk_product',
                'value' => 'yes_',
                'compare' => 'LIKE',
            ]
        ],
    ]);

    foreach ($products as $product) {

        $meta_data = get_post_meta($product->get_id());

        foreach ($meta_data as $meta_key => $meta_values) {

            foreach ($meta_values as $meta_value) {

                // Convert arrays/objects to JSON
                if (is_array($meta_value) || is_object($meta_value)) {
                    $meta_value = wp_json_encode($meta_value);
                }

                fputcsv($output, [
                    $product->get_id(),
                    $product->get_name(),
                    $product->get_sku(),
                    $meta_key,
                    $meta_value,
                ]);
            }
        }
    }

    fclose($output);
    exit;
});

/**
 * Save order product data and invoice number when order is placed
 * This function captures all product data and saves invoice number and other meta data
 * 
 * @param int $order_id Order ID
 * @param array $posted_data Posted data from checkout (optional)
 */
function save_order_product_data_and_meta($order_id, $posted_data = array())
{
    // Get the order object
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }


    // Save Contact Information checkboxes (T&C and Promotions) to order meta when we have checkout posted data.
    if (!empty($posted_data)) {
        $order->update_meta_data('_accept_terms', isset($_POST['accept_terms']) ? 1 : 0);
        $order->update_meta_data('_marketing_optin', isset($_POST['marketing_optin']) ? 1 : 0);
    }

    // Check if this order is placed by a customer user
    $customer_id = $order->get_customer_id();
    $is_customer_user = false;

    $customer_like_roles = array('customer', 'guest');
    // First, check the order's customer ID
    if ($customer_id) {
        $user = get_userdata($customer_id);
        if ($user && !empty($user->roles)) {
            if (!empty(array_intersect($customer_like_roles, (array) $user->roles))) {
                $is_customer_user = true;
            }
        }
    }

    // Fallback: Check current logged-in user if order customer ID check didn't work
    if (!$is_customer_user && is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if ($current_user && !empty($current_user->roles)) {
            if (!empty(array_intersect($customer_like_roles, (array) $current_user->roles))) {
                $is_customer_user = true;
            }
        }
    }

    // Allow admin-placed orders (e.g. from single product page) to also create gift card posts
    // and save activation expiry data, not just customer/guest orders.

    // If order has no sender name, use the customer's (order placer's) name
    $order_sender_name = $order->get_meta('_sender_name', true);
    if (empty(trim((string) $order_sender_name))) {
        $order_sender_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($order_sender_name) && $order->get_customer_id()) {
            $customer = get_user_by('id', $order->get_customer_id());
            if ($customer && !empty(trim($customer->display_name ?? ''))) {
                $order_sender_name = trim($customer->display_name);
            }
        }
        if (!empty($order_sender_name)) {
            $order->update_meta_data('_sender_name', $order_sender_name);
            $order->save();
        }
    }

    // Generate invoice number if it doesn't exist
    $invoice_number = $order->get_meta('_invoice_number');
    if (empty($invoice_number)) {
        $invoice_number = 'INV-' . wp_date('Ymd') . '-' . wp_rand(1000, 9999);
        $order->update_meta_data('_invoice_number', $invoice_number);
    }

    // Initialize array to store all product data
    $all_product_data = array();

    // Loop through all order items
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();

        if (!$product) {
            continue;
        }

        // Get product basic information
        $product_id = $product->get_id();
        $product_data = array(
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'product_sku' => $product->get_sku(),
            'product_type' => $product->get_type(),
            'product_price' => $product->get_price(),
            'product_regular_price' => $product->get_regular_price(),
            'product_sale_price' => $product->get_sale_price(),
            'product_stock_status' => $product->get_stock_status(),
            'product_stock_quantity' => $product->get_stock_quantity(),
            'product_image_id' => $product->get_image_id(),
            'product_image_url' => wp_get_attachment_image_url($product->get_image_id(), 'full'),
            'product_thumbnail_url' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
            'product_categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')),
            'product_tags' => wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names')),
            'product_brand' => '',
            'item_quantity' => $item->get_quantity(),
            'item_subtotal' => $item->get_subtotal(),
            'item_total' => $item->get_total(),
            'item_tax' => $item->get_subtotal_tax(),
        );

        // Get product brand if exists
        $brand_terms = wp_get_post_terms($product_id, 'product_brand');
        if (!empty($brand_terms) && !is_wp_error($brand_terms)) {
            $product_data['product_brand'] = $brand_terms[0]->name;
        }

        // Get all product meta data
        $product_meta = get_post_meta($product_id);
        $product_data['product_meta'] = array();
        foreach ($product_meta as $meta_key => $meta_value) {
            // Skip internal WordPress meta
            if (strpos($meta_key, '_edit_') === 0 || strpos($meta_key, '_wp_') === 0) {
                continue;
            }
            // Unserialize if needed
            if (is_array($meta_value) && count($meta_value) === 1) {
                $product_data['product_meta'][$meta_key] = maybe_unserialize($meta_value[0]);
            } else {
                $product_data['product_meta'][$meta_key] = $meta_value;
            }
        }

        // Get all item meta data (gift card specific data)
        $item_meta = $item->get_meta_data();
        $product_data['item_meta'] = array();
        foreach ($item_meta as $meta) {
            $product_data['item_meta'][$meta->key] = $meta->value;
        }

        // Extract delivery method option from delivery-method-options (selected on single product page)
        $delivery_method = $item->get_meta('_delivery_method');
        if (empty($delivery_method)) {
            // Try alternative meta key
            $delivery_method = $item->get_meta('delivery_method');
        }

        // Format delivery method for display
        $delivery_method_display = '';
        if (!empty($delivery_method)) {
            switch ($delivery_method) {
                case 'email':
                    $delivery_method_display = 'Email';
                    break;
                case 'sms':
                    $delivery_method_display = 'SMS';
                    break;
                case 'email_sms':
                    $delivery_method_display = 'Email + SMS';
                    break;
                default:
                    $delivery_method_display = ucfirst(str_replace('_', ' ', $delivery_method));
                    break;
            }
        }

        // Save delivery method option to product data
        $product_data['delivery_method_option'] = $delivery_method;
        $product_data['delivery_method_display'] = $delivery_method_display;

        // Also get delivery timing if available
        $delivery_timing = $item->get_meta('_delivery_timing');
        if (empty($delivery_timing)) {
            $delivery_timing = $item->get_meta('delivery_timing');
        }
        $product_data['delivery_timing'] = $delivery_timing;

        // Get delivery email and mobile number if available
        $delivery_email = $item->get_meta('_delivery_email');
        if (empty($delivery_email)) {
            $delivery_email = $item->get_meta('delivery_email');
        }
        $product_data['delivery_email'] = $delivery_email;

        $mobile_number = $item->get_meta('_recipient_phone');
        if (empty($mobile_number)) {
            $mobile_number = $item->get_meta('mobile_number');
        }
        $product_data['mobile_number'] = $mobile_number;

        // Get ACF fields if ACF is active
        if (function_exists('get_fields')) {
            $acf_fields = get_fields($product_id);
            if ($acf_fields) {
                $product_data['acf_fields'] = $acf_fields;
            }
        }

        // Store product data with item ID as key
        $all_product_data[$item_id] = $product_data;
    }

    // Save all product data to order meta
    if (!empty($all_product_data)) {
        $order->update_meta_data('_order_product_data', $all_product_data);
        $order->update_meta_data('_order_product_data_saved_at', current_time('mysql'));
    }

    // Extract and save delivery method options summary at order level
    $delivery_methods_summary = array();
    foreach ($all_product_data as $item_id => $product_data) {
        if (!empty($product_data['delivery_method_option'])) {
            $method = $product_data['delivery_method_option'];
            if (!isset($delivery_methods_summary[$method])) {
                $delivery_methods_summary[$method] = array(
                    'method' => $method,
                    'display' => $product_data['delivery_method_display'],
                    'count' => 0,
                    'items' => array()
                );
            }
            $delivery_methods_summary[$method]['count']++;
            $delivery_methods_summary[$method]['items'][] = array(
                'item_id' => $item_id,
                'product_name' => $product_data['product_name'],
                'quantity' => $product_data['item_quantity'],
                'delivery_timing' => $product_data['delivery_timing'] ?? '',
                'delivery_option' => $product_data['delivery_option'] ?? '',
                'delivery_email' => $product_data['delivery_email'] ?? '',
                'mobile_number' => $product_data['mobile_number'] ?? '',
            );
        }
    }

    // Save delivery methods summary to order meta
    if (!empty($delivery_methods_summary)) {
        $order->update_meta_data('_order_delivery_methods_summary', $delivery_methods_summary);

        // Also save a simple list of unique delivery methods
        $unique_delivery_methods = array_keys($delivery_methods_summary);
        $order->update_meta_data('_order_delivery_methods', $unique_delivery_methods);
    }

    // Calculate fees total and tax manually (methods don't exist on order object)
    $fees_total = 0;
    $fees_tax = 0;
    foreach ($order->get_fees() as $fee) {
        $fees_total += floatval($fee->get_total());
        $fees_tax += floatval($fee->get_total_tax());
    }

    // Save order totals breakdown
    $order_totals = array(
        'subtotal' => $order->get_subtotal(),
        'total' => $order->get_total(),
        'total_tax' => $order->get_total_tax(),
        'shipping_total' => $order->get_shipping_total(),
        'shipping_tax' => $order->get_shipping_tax(),
        'discount_total' => $order->get_discount_total(),
        'discount_tax' => $order->get_discount_tax(),
        'fee_total' => $fees_total,
        'fee_tax' => $fees_tax,
    );
    $order->update_meta_data('_order_totals_breakdown', $order_totals);

    // Save payment information
    $payment_info = array(
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        'transaction_id' => $order->get_transaction_id(),
        'date_paid' => $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d H:i:s') : '',
    );

    // Try to get card info
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

    if (!empty($card_last4)) {
        $payment_info['card_last4'] = $card_last4;
        $payment_info['card_brand'] = $card_brand;
    }

    $order->update_meta_data('_order_payment_info', $payment_info);

    // Save customer information
    $customer_info = array(
        'customer_id' => $order->get_customer_id(),
        'customer_email' => $order->get_billing_email(),
        'customer_phone' => $order->get_billing_phone(),
        'billing_first_name' => $order->get_billing_first_name(),
        'billing_last_name' => $order->get_billing_last_name(),
        'billing_company' => $order->get_billing_company(),
        'billing_address_1' => $order->get_billing_address_1(),
        'billing_address_2' => $order->get_billing_address_2(),
        'billing_city' => $order->get_billing_city(),
        'billing_state' => $order->get_billing_state(),
        'billing_postcode' => $order->get_billing_postcode(),
        'billing_country' => $order->get_billing_country(),
        'accept_terms' => (int) $order->get_meta('_accept_terms', true),
        'marketing_optin' => (int) $order->get_meta('_marketing_optin', true),
    );
    $order->update_meta_data('_order_customer_info', $customer_info);

    // Save order creation details
    $order_creation_info = array(
        'created_via' => $order->get_created_via(),
        'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
        'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->date('Y-m-d H:i:s') : '',
        'order_status' => $order->get_status(),
        'order_currency' => $order->get_currency(),
    );
    $order->update_meta_data('_order_creation_info', $order_creation_info);

    // Save the order with all meta data
    $order->save();

}

/**
 * Transfer cart item data (delivery method, etc.) to order item meta
 * This ensures delivery method selected on single product page is saved to order
 */

/**
 * Upload data URI (base64) image to media library when order is placed. Returns attachment URL or false.
 */
function gc_upload_data_uri_to_media($data_uri) {
    if (empty($data_uri) || strpos($data_uri, 'data:image') !== 0) {
        return false;
    }
    if (!preg_match('#^data:image/(\w+);base64,(.+)$#is', $data_uri, $m)) {
        return false;
    }
    $ext = strtolower($m[1]);
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $ext = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? $ext : 'png';
    $raw = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
    if ($raw === false || strlen($raw) < 100) {
        return false;
    }
    $upload = wp_upload_bits('gift-card-design-' . time() . '-' . wp_rand(100, 999) . '.' . $ext, null, $raw);
    if ($upload['error'] || empty($upload['file'])) {
        return false;
    }
    $file_path = $upload['file'];
    $mime = wp_check_filetype($file_path, null)['type'] ?: 'image/png';
    $attachment = [
        'post_mime_type' => $mime,
        'post_title'     => 'Gift card design ' . time(),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $attach_id = wp_insert_attachment($attachment, $file_path, 0);
    if (is_wp_error($attach_id) || !$attach_id) {
        @unlink($file_path);
        return false;
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_generate_attachment_metadata($attach_id, $file_path);
    return wp_get_attachment_url($attach_id);
}


add_action('woocommerce_checkout_create_order_line_item', 'transfer_cart_item_data_to_order_item', 10, 4);
function transfer_cart_item_data_to_order_item($item, $cart_item_key, $values, $order)
{

    if (isset($values['delivery_option'])) {
        $item->add_meta_data('delivery_option', $values['delivery_option'], true);
    }
    // Check if cart item has delivery method data
    if (!empty($values['delivery_method'])) {
        $item->add_meta_data('_delivery_method', sanitize_text_field($values['delivery_method']));
    }

    // Transfer other delivery-related data
    if (!empty($values['delivery_timing'])) {
        $delivery_timing = sanitize_text_field($values['delivery_timing']);
        $item->add_meta_data('_delivery_timing', $delivery_timing);
    }
    
    // Transfer other delivery-related data
    if (!empty($values['delivery_option'])) {
        $delivery_option = sanitize_text_field($values['delivery_option']);
        $item->add_meta_data('_delivery_option', $delivery_option);
    }

    // Transfer schedule date/time fields if they exist (same as place_cod_order saves scheduleDate as _scheduled_date)
    // Priority: schedule_datetime > schedule_date + schedule_time > schedule_date only
    if (!empty($values['schedule_datetime'])) {
        // schedule_datetime is the combined date/time string (preferred)
        $schedule_datetime = sanitize_text_field($values['schedule_datetime']);
        $item->add_meta_data('_scheduled_date', $schedule_datetime);
    } else {
        $schedule_date = '';
        if (!empty($values['schedule_date'])) {
            $schedule_date = sanitize_text_field($values['schedule_date']);
            $item->add_meta_data('schedule_date', $schedule_date);
        }

        if (!empty($values['schedule_time'])) {
            $schedule_time = sanitize_text_field($values['schedule_time']);
            $item->add_meta_data('schedule_time', $schedule_time);

            // Combine date and time if both exist
            if (!empty($schedule_date)) {
                $schedule_datetime = trim($schedule_date . ' ' . $schedule_time);
                if (!empty($values['schedule_timezone'])) {
                    $schedule_timezone = sanitize_text_field($values['schedule_timezone']);
                    $item->add_meta_data('schedule_timezone', $schedule_timezone);
                    $schedule_datetime .= ' ' . $schedule_timezone;
                }
                $item->add_meta_data('_scheduled_date', $schedule_datetime);
            }
        } elseif (!empty($schedule_date)) {
            // If only date exists, save it as scheduled_date
            $item->add_meta_data('_scheduled_date', $schedule_date);
        }
    }

    // Also check if delivery_timing contains a date/time string (not just 'schedule' or 'instant')
    if (!empty($values['delivery_timing'])) {
        $delivery_timing = sanitize_text_field($values['delivery_timing']);
        // Check if delivery_timing is a date/time string (not just 'schedule', 'instant', etc.)
        if (
            $delivery_timing !== 'immediate' &&
            $delivery_timing !== 'now' &&
            $delivery_timing !== 'instant' &&
            $delivery_timing !== 'schedule'
        ) {
            // Try to parse as date/time
            $test_timestamp = strtotime($delivery_timing);
            if ($test_timestamp !== false && $test_timestamp > 0) {
                // It's a valid date/time, save as scheduled_date
                if (empty($schedule_date)) {
                    $item->add_meta_data('_scheduled_date', $delivery_timing);
                }
            }
        }
    }

    if (!empty($values['delivery_email'])) {
        $item->add_meta_data('_delivery_email', sanitize_email($values['delivery_email']));
    }

    if (!empty($values['mobile_number'])) {
        $item->add_meta_data('_recipient_phone', sanitize_text_field($values['mobile_number']));
    }

    if (!empty($values['mobile_number'])) {
        $item->add_meta_data('mobile_number', sanitize_text_field($values['mobile_number']));
    }

    // Transfer recipient data
    if (!empty($values['recipient_name'])) {
        $item->add_meta_data('_recipient_name', sanitize_text_field($values['recipient_name']));
    }

    if (!empty($values['recipient_email'])) {
        $item->add_meta_data('_recipient_email', sanitize_email($values['recipient_email']));
    }

    // If no sender name provided, use the customer's (order placer's) name
    $sender_name = !empty(trim((string)($values['sender_name'] ?? '')))
        ? sanitize_text_field($values['sender_name'])
        : trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    if (empty($sender_name) && $order->get_customer_id()) {
        $customer = get_user_by('id', $order->get_customer_id());
        if ($customer && !empty(trim($customer->display_name ?? ''))) {
            $sender_name = trim($customer->display_name);
        }
    }
    if (!empty($sender_name)) {
        $item->add_meta_data('_sender_name', $sender_name);
    }

    if (!empty($values['gift_message'])) {
        $item->add_meta_data('_gift_message', sanitize_textarea_field($values['gift_message']));
    }

    if (!empty($values['card_design'])) {
        $img_val = $values['card_design'];
        if (strpos($img_val, 'data:image') === 0) {
            $uploaded_url = gc_upload_data_uri_to_media($img_val);
            $img_val = ($uploaded_url !== false) ? $uploaded_url : $img_val;
        } else {
            $img_val = esc_url_raw($img_val);
        }
        $item->add_meta_data('_gift_card_image', $img_val);
    }
    // Transfer media message: animation (GIF) for email embed, video for email attachment, image for message
    $product_id = isset($values['product_id']) ? (int) $values['product_id'] : 0;
    $email_animation = isset($values['email_animation']) ? $values['email_animation'] : '';
    $video_message   = isset($values['video_message']) ? $values['video_message'] : '';
    $image_message   = isset($values['image_message']) ? $values['image_message'] : '';
    // Fallback to session if cart item missed them (e.g. form submit timing)
    if ($product_id && function_exists('WC') && WC()->session) {
        if (empty($email_animation)) {
            $email_animation = WC()->session->get('gc_media_animation_' . $product_id, '');
        }
        if (empty($video_message)) {
            $video_message = WC()->session->get('gc_media_video_' . $product_id, '');
        }
        if (empty($image_message)) {
            $image_message = WC()->session->get('gc_media_image_' . $product_id, '');
        }
    }
    if (!empty($email_animation)) {
        $item->add_meta_data('gift_email_animation', esc_url_raw($email_animation));
    }
    if (!empty($video_message)) {
        $item->add_meta_data('gift_video_message', esc_url_raw($video_message));
    }
    if (!empty($image_message)) {
        $item->add_meta_data('gift_image_message', esc_url_raw($image_message));
    }

    // Transfer gift card price if available
    if (!empty($values['gift_card_price'])) {
        $item->add_meta_data('_gift_card_price', floatval($values['gift_card_price']));
    }

    // Transfer activation expiry fields from cart item data, falling back to product meta.
    // These are never stored in the cart session for standard single-product-page orders,
    // so without the fallback the gift card post gets no activation expiry data.
    $pid = isset($values['product_id']) ? (int) $values['product_id'] : 0;

    $act_type = !empty($values['activation_expiry_type'])
        ? sanitize_text_field($values['activation_expiry_type'])
        : ( $pid ? (get_field('activation_expiry_type', $pid) ?: get_post_meta($pid, 'activation_expiry_type', true)) : '' );

    $act_date = !empty($values['activation_expiry_date'])
        ? sanitize_text_field($values['activation_expiry_date'])
        : ( $pid ? (get_field('activation_expiry_date', $pid) ?: get_post_meta($pid, 'activation_expiry_date', true)) : '' );

    $act_duration = !empty($values['activation_expiry_duration'])
        ? sanitize_text_field($values['activation_expiry_duration'])
        : ( $pid ? (get_field('activation_expiry_duration', $pid) ?: get_post_meta($pid, 'activation_expiry_duration', true)) : '' );

    $act_unit = !empty($values['activation_expiry_unit'])
        ? sanitize_text_field($values['activation_expiry_unit'])
        : ( $pid ? (get_field('activation_expiry_unit', $pid) ?: get_post_meta($pid, 'activation_expiry_unit', true)) : '' );

    if (!empty($act_type)) {
        $item->add_meta_data('_activation_expiry_type', $act_type, true);
    }
    if (!empty($act_date)) {
        $item->add_meta_data('_activation_expiry_date', $act_date, true);
    }
    if (!empty($act_duration)) {
        $item->add_meta_data('_activation_expiry_duration', $act_duration, true);
    }
    if (!empty($act_unit)) {
        $item->add_meta_data('_activation_expiry_unit', $act_unit, true);
    }
}

// Hook into order processing when order is placed via checkout
add_action('woocommerce_checkout_order_processed', 'save_order_product_data_and_meta', 20, 2);

// Hook into payment complete to ensure data is saved even if order was created differently
add_action('woocommerce_payment_complete', 'save_order_product_data_and_meta', 20, 1);

// Also hook into order status change to processing/completed to catch any missed orders
add_action('woocommerce_order_status_processing', 'save_order_product_data_and_meta', 20, 1);
add_action('woocommerce_order_status_completed', 'save_order_product_data_and_meta', 20, 1);


/**
 * Use selected gift card image in cart and mini-cart (from session / cart item, no media upload until order).
 */
add_filter('woocommerce_cart_item_thumbnail', 'cart_use_selected_gift_card_image', 10, 3);
function cart_use_selected_gift_card_image($image, $cart_item, $cart_item_key) {
    $img = $cart_item['card_design'] ?? $cart_item['selected_gift_card_image'] ?? '';
    if (empty($img) || !is_string($img)) {
        return $image;
    }
    $src = (strpos($img, 'data:image') === 0) ? esc_attr($img) : esc_url($img);
    $product_name = isset($cart_item['data']) && is_object($cart_item['data']) ? $cart_item['data']->get_name() : '';
    return '<img src="' . $src . '" alt="' . esc_attr($product_name) . '" style="max-width:100%; height:auto; display:block;" />';
}
/**
 * Use selected gift card image in order completion (and other) emails instead of product image.
 */
add_filter('woocommerce_order_item_thumbnail', 'email_use_selected_gift_card_image', 10, 2);
function email_use_selected_gift_card_image($image, $item)
{
    if (!$item || !is_a($item, 'WC_Order_Item_Product')) {
        return $image;
    }
    $gift_url = $item->get_meta('_gift_card_image', true);
    if (empty($gift_url) || !filter_var($gift_url, FILTER_VALIDATE_URL)) {
        return $image;
    }
    $image_size = wc_get_image_size('thumbnail');
    $w          = isset($image_size['width']) ? (int) $image_size['width'] : 100;
    $h          = isset($image_size['height']) ? (int) $image_size['height'] : 100;
    return '<img src="' . esc_url($gift_url) . '" alt="' . esc_attr($item->get_name()) . '" width="' . esc_attr($w) . '" height="' . esc_attr($h) . '" style="max-width:100%; height:auto; display:block; border:0; outline:0;" />';
}

/**
 * Register AJAX handlers for downloading invoice PDF
 * Using priority 5 to run before the old HTML invoice function
 */
add_action('wp_ajax_download_invoice', 'download_invoice_pdf_callback', 5);

/**
 * Register AJAX handler for creating event from contact reminder button
 */
add_action('wp_ajax_create_event_from_contact', 'handle_create_event_from_contact');

/**
 * Register AJAX handler for deleting event from contact reminder button
 */
add_action('wp_ajax_delete_event_from_contact', 'handle_delete_event_from_contact');

/**
 * Hook into Participants Database update to sync events with reminder field
 * Hook into both update actions to ensure it fires
 */
add_action('pdb-after_submit_update', 'sync_event_with_reminder_field', 10, 1);
add_action('pdb-after_submit_add', 'sync_event_with_reminder_field', 10, 1);

/**
 * Also hook into the write_participant function to catch direct updates
 */
add_filter('pdb-before_submit_update', 'check_reminder_field_before_update', 10, 1);
add_filter('pdb-before_submit_add', 'check_reminder_field_before_update', 10, 1);

/**
 * Hook into admin_init to check for reminder field changes on admin save
 */
add_action('admin_init', 'check_participant_reminder_on_admin_save', 999);

function check_participant_reminder_on_admin_save()
{
    // Only run on Participants Database admin pages
    if (!isset($_GET['page']) || strpos($_GET['page'], 'participants-database') === false) {
        return;
    }

    // Check if we're on the edit participant page
    if (isset($_GET['page']) && $_GET['page'] === 'participants-database-edit_participant') {
        // Check if form was submitted (POST request with action)
        if (isset($_POST['action']) && ($_POST['action'] === 'update' || $_POST['action'] === 'insert')) {
            // Get record ID
            $record_id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

            if ($record_id > 0) {

                // Check if reminder field is in POST data
                $reminder_field = 'reminder';
                if (!isset($_POST['reminder']) && isset($_POST['Reminder'])) {
                    $reminder_field = 'Reminder';
                }

                if (isset($_POST[$reminder_field])) {
                }

                // Schedule sync after page processing completes
                // Use a later priority to ensure database update is complete
                add_action('shutdown', function () use ($record_id) {
                    sync_event_for_record_id($record_id);
                }, 999);
            }
        }
    }
}

/**
 * Check reminder field before update and sync after
 */
function check_reminder_field_before_update($post_data)
{
    // Store the reminder value to check after update
    if (isset($post_data['id']) && $post_data['id'] > 0) {
        $record_id = intval($post_data['id']);

        // Get current reminder value
        $reminder_field = 'reminder';
        if (!isset($post_data['reminder']) && isset($post_data['Reminder'])) {
            $reminder_field = 'Reminder';
        }

        // Schedule sync after update completes
        add_action('pdb-after_submit_update', function ($participant) use ($record_id) {
            // Small delay to ensure database is updated
            add_action('shutdown', function () use ($record_id) {
                sync_event_for_record_id($record_id);
            }, 999);
        }, 999, 1);
    }

    return $post_data;
}

/**
 * Sync event for a specific record ID (called after database update)
 */
function sync_event_for_record_id($record_id)
{
    if (!class_exists('Participants_Db')) {
        return;
    }

    try {
        $participant = Participants_Db::get_participant($record_id);

        if (!$participant || !is_array($participant)) {
            return;
        }

        // Call the sync function
        sync_event_with_reminder_field($participant);

    } catch (Exception $e) {
    }
}

/**
 * Sync event with reminder field when participant is updated
 */
function sync_event_with_reminder_field($participant)
{
    // Add logging to debug

    if (!is_array($participant) || !isset($participant['id'])) {
        return;
    }

    $record_id = intval($participant['id']);

    // Check reminder field value
    $reminder_field = 'reminder';
    if (!isset($participant['reminder']) && isset($participant['Reminder'])) {
        $reminder_field = 'Reminder';
    }

    $reminder_value = isset($participant[$reminder_field]) ? $participant[$reminder_field] : '';

    // Handle serialized array (checkbox fields are stored as serialized arrays)
    if (is_string($reminder_value) && (substr($reminder_value, 0, 2) === 'a:' || substr($reminder_value, 0, 2) === 'O:')) {
        $unserialized = maybe_unserialize($reminder_value);
        if (is_array($unserialized)) {
            $reminder_value = !empty($unserialized) ? reset($unserialized) : '';
        }
    }

    // Handle array directly (if already unserialized)
    if (is_array($reminder_value)) {
        $reminder_value = !empty($reminder_value) ? reset($reminder_value) : '';
    }

    // Check if reminder is enabled
    $reminder_value_clean = is_string($reminder_value) ? strtolower(trim($reminder_value)) : '';
    $is_reminder_enabled = (
        $reminder_value_clean === 'yes' ||
        $reminder_value === '1' ||
        $reminder_value === 1 ||
        $reminder_value === true ||
        (is_array($reminder_value) && !empty($reminder_value))
    );


    // Find all users who have events for this participant, or check if there's a user_id field
    // First, try to get user_id from participant record if it exists
    $participant_user_id = null;
    if (isset($participant['user_id'])) {
        $participant_user_id = intval($participant['user_id']);
    } elseif (isset($participant['user-id'])) {
        $participant_user_id = intval($participant['user-id']);
    }

    // Get all existing events for this record (across all users)
    $args = [
        'post_type' => 'tribe_events',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_pdb_record_id',
                'value' => $record_id,
                'compare' => '='
            ]
        ]
    ];

    $existing_events = get_posts($args);
    $event_exists = !empty($existing_events);

    // Get unique user IDs from existing events
    $user_ids_with_events = [];
    foreach ($existing_events as $event) {
        $user_ids_with_events[] = $event->post_author;
    }
    $user_ids_with_events = array_unique($user_ids_with_events);

    // Determine which users should have events for this participant
    $user_ids_to_sync = [];
    $current_user_id = get_current_user_id();

    // ALWAYS use current logged-in user when updating from admin panel
    // Check if we're in admin context (either is_admin() or checking the request)
    $is_admin_update = is_admin() || (defined('WP_ADMIN') && WP_ADMIN) || (isset($_GET['page']) && strpos($_GET['page'], 'participants-database') !== false);

    if ($is_admin_update && $current_user_id > 0) {
        // Admin update - ONLY create for the current logged-in user
        $user_ids_to_sync = [$current_user_id];
    } elseif ($participant_user_id && $participant_user_id > 0) {
        // Frontend update - If participant has a user_id field, use that
        $user_ids_to_sync[] = $participant_user_id;
    } elseif (!empty($user_ids_with_events)) {
        // Frontend update - Use existing event owners (users who already have events for this participant)
        $user_ids_to_sync = $user_ids_with_events;
    } elseif ($current_user_id > 0) {
        // Frontend update - use current logged-in user
        $user_ids_to_sync[] = $current_user_id;
    } else {
        // No user context available, skip
        return;
    }


    // Sync for each user
    foreach ($user_ids_to_sync as $user_id) {
        if (!$user_id || $user_id <= 0) {
            continue;
        }

        // Check if this user has an event for this record
        $user_has_event = false;
        foreach ($existing_events as $event) {
            if ($event->post_author == $user_id) {
                $user_has_event = true;
                break;
            }
        }


        // Sync: If reminder is enabled but no event exists for this user, create one
        if ($is_reminder_enabled && !$user_has_event) {
            $event_id = create_event_for_participant($record_id, $user_id);
            if ($event_id) {
            } else {
            }
        }
        // Sync: If reminder is disabled but event exists for this user, delete it
        elseif (!$is_reminder_enabled && $user_has_event) {
            // Delete events for this user
            foreach ($existing_events as $event) {
                if ($event->post_author == $user_id) {
                    $deleted = wp_delete_post($event->ID, true); // Force delete
                }
            }
        }
    }

}

/**
 * Create event for a participant (helper function extracted from handle_create_event_from_contact)
 */
function create_event_for_participant($record_id, $user_id)
{
    if (!class_exists('Participants_Db')) {
        return false;
    }

    try {
        $participant = Participants_Db::get_participant($record_id);

        if (!$participant || !is_array($participant)) {
            return false;
        }

        // Get contact information
        $first_name = isset($participant['first_name']) ? sanitize_text_field($participant['first_name']) : '';
        $last_name = isset($participant['last_name']) ? sanitize_text_field($participant['last_name']) : '';
        $middle_name = isset($participant['middle_name']) ? sanitize_text_field($participant['middle_name']) : '';
        $date_of_birth_raw = isset($participant['date_of_birth']) ? $participant['date_of_birth'] : '';

        // Build full name
        $full_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
        if (empty($full_name)) {
            $full_name = 'Contact #' . $record_id;
        }

        // Determine event type and date
        $event_date = null;
        $event_title = '';
        $event_category = 'my-events';

        if (!empty($date_of_birth_raw)) {
            try {
                // Handle different date_of_birth formats
                $birthday = null;

                // Check if it's a Unix timestamp (numeric)
                if (is_numeric($date_of_birth_raw)) {
                    $timestamp = intval($date_of_birth_raw);
                    // Check if it's a reasonable timestamp (not just a year)
                    if ($timestamp > 1000000000) {
                        // Unix timestamp (seconds since epoch)
                        // Use WordPress timezone to extract date components, matching how Participants DB displays dates
                        $wp_timezone = wp_timezone();
                        $birthday_dt = new DateTime();
                        $birthday_dt->setTimestamp($timestamp);
                        $birthday_dt->setTimezone($wp_timezone);
                        // Extract date components in WordPress timezone
                        $birth_year = intval($birthday_dt->format('Y'));
                        $birth_month = intval($birthday_dt->format('m'));
                        $birth_day = intval($birthday_dt->format('d'));
                        // Create a new DateTime with just the date (no time component)
                        $birthday = new DateTime(sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day));
                    } elseif ($timestamp > 1900 && $timestamp < 2100) {
                        // Just a year, use January 1st of that year
                        $birthday = new DateTime($timestamp . '-01-01');
                    }
                } else {
                    // Try to parse as date string
                    $birthday = new DateTime($date_of_birth_raw);
                }

                if ($birthday) {
                    // It's a birthday - calculate next birthday
                    // Get date components to avoid timezone issues
                    $birth_year = intval($birthday->format('Y'));
                    $birth_month = intval($birthday->format('m'));
                    $birth_day = intval($birthday->format('d'));

                    $today = new DateTime();
                    $today_year = intval($today->format('Y'));
                    $today_month = intval($today->format('m'));
                    $today_day = intval($today->format('d'));

                    // Create this year's birthday date
                    $this_year_birthday = new DateTime(sprintf('%04d-%02d-%02d', $today_year, $birth_month, $birth_day));

                    // If birthday already passed this year, use next year
                    if ($this_year_birthday < $today) {
                        $next_birthday = new DateTime(sprintf('%04d-%02d-%02d', $today_year + 1, $birth_month, $birth_day));
                    } else {
                        $next_birthday = $this_year_birthday;
                    }

                    // Calculate age — only valid if birth year is in the past.
                    $age = $next_birthday->format('Y') - $birth_year;

                    $event_date = $next_birthday->format('Y-m-d');
                    if ( $age > 0 ) {
                        $suffix = $age == 1 ? 'st' : ( $age == 2 ? 'nd' : ( $age == 3 ? 'rd' : 'th' ) );
                        $event_title = $full_name . ' ' . $age . $suffix . ' Birthday';
                    } else {
                        $event_title = $full_name . '\'s Birthday';
                    }
                    $event_category = 'birthdays';

                } else {
                    throw new Exception('Could not parse date_of_birth: ' . $date_of_birth_raw);
                }
            } catch (Exception $e) {
                $event_date = wp_date('Y-m-d');
                $event_title = $full_name . ' Reminder';
                $event_category = 'my-events';
            }
        } else {
            $event_date = date('Y-m-d');
            $event_title = $full_name . ' Reminder';
            $event_category = 'my-events';
        }

        // Create the event post
        $post_data = [
            'post_title' => $event_title,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'tribe_events',
            'post_author' => $user_id,
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id) || !$post_id || $post_id === 0) {
            return false;
        }

        // Save event dates - all-day event
        $formatted_start = $event_date . ' 00:00:00';
        $formatted_end = $event_date . ' 23:59:59';

        // Get timezone
        $timezone_string = wp_timezone_string();

        // Save event dates
        update_post_meta($post_id, '_EventStartDate', $formatted_start);
        update_post_meta($post_id, '_EventEndDate', $formatted_end);
        update_post_meta($post_id, '_EventTimezone', $timezone_string);
        update_post_meta($post_id, '_EventAllDay', 'yes');

        // Calculate and save UTC dates
        $timezone = new DateTimeZone($timezone_string);
        try {
            $start_dt = new DateTime($formatted_start, $timezone);
            $end_dt = new DateTime($formatted_end, $timezone);

            $utc_timezone = new DateTimeZone('UTC');
            $start_dt->setTimezone($utc_timezone);
            $end_dt->setTimezone($utc_timezone);

            update_post_meta($post_id, '_EventStartDateUTC', $start_dt->format('Y-m-d H:i:s'));
            update_post_meta($post_id, '_EventEndDateUTC', $end_dt->format('Y-m-d H:i:s'));
        } catch (Exception $e) {
            update_post_meta($post_id, '_EventStartDateUTC', $formatted_start);
            update_post_meta($post_id, '_EventEndDateUTC', $formatted_end);
        }

        // Save EventDuration
        $start_timestamp = strtotime($formatted_start);
        $end_timestamp = strtotime($formatted_end);
        if ($start_timestamp && $end_timestamp) {
            $duration = $end_timestamp - $start_timestamp;
            update_post_meta($post_id, '_EventDuration', $duration);
        }

        // Save category
        if ($event_category === 'birthdays') {
            wp_set_object_terms($post_id, 'birthdays', 'tribe_events_cat');
        }

        // Save user ID and record ID
        update_post_meta($post_id, '_gc_user_id', $user_id);
        update_post_meta($post_id, '_pdb_record_id', $record_id);

        return $post_id;

    } catch (Exception $e) {
        return false;
    }
}

/**
 * Sync all reminders with events on page load (for my-reminders page)
 * Only runs once per page load using transients
 */
add_action('wp', 'sync_all_reminders_with_events');

function sync_all_reminders_with_events()
{
    // Only run on my-reminders page and for logged-in users
    if (!is_user_logged_in() || !is_account_page()) {
        return;
    }

    // Check if we're on the my-reminders endpoint
    global $wp;
    if (!isset($wp->query_vars['my-reminders'])) {
        return;
    }

    $user_id = get_current_user_id();
    $transient_key = 'sync_reminders_' . $user_id;

    // Only sync once per hour per user to avoid performance issues
    if (get_transient($transient_key)) {
        return;
    }

    // Set transient for 1 hour
    set_transient($transient_key, true, HOUR_IN_SECONDS);

    if (!class_exists('Participants_Db')) {
        return;
    }

    try {
        // Get all events created by this user with _pdb_record_id
        $args = [
            'post_type' => 'tribe_events',
            'posts_per_page' => -1,
            'author' => $user_id,
            'meta_query' => [
                [
                    'key' => '_pdb_record_id',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        $user_events = get_posts($args);

        // Get all record IDs that have events
        $record_ids_with_events = [];
        foreach ($user_events as $event) {
            $record_id = get_post_meta($event->ID, '_pdb_record_id', true);
            if ($record_id) {
                $record_ids_with_events[] = intval($record_id);
            }
        }

        // Get all participants with reminder enabled
        global $wpdb;

        if (method_exists('Participants_Db', 'participants_table')) {
            $table_name = Participants_Db::participants_table();
        } else {
            $table_name = Participants_Db::$participants_table;
        }

        if (empty($table_name)) {
            return;
        }

        // Check which reminder field name exists
        $reminder_field = 'reminder';
        $field_check = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'Reminder'",
            $table_name
        ) );

        if ($field_check > 0) {
            $reminder_field = 'Reminder';
        }

        // Get all records where reminder is enabled
        $sql = $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE ({$reminder_field} = %s OR {$reminder_field} = %s)",
            'Yes',
            '1'
        );

        $record_ids_with_reminder = $wpdb->get_col($sql);

        if (empty($record_ids_with_reminder)) {
            return;
        }

        // For each record with reminder enabled, check if event exists
        foreach ($record_ids_with_reminder as $record_id) {
            $record_id = intval($record_id);

            // Skip if event already exists
            if (in_array($record_id, $record_ids_with_events)) {
                continue;
            }

            // Create event for this record
            create_event_for_participant($record_id, $user_id);
        }

    } catch (Exception $e) {
    }
}

/**
 * Handle AJAX request to create event from contact data
 */
function handle_create_event_from_contact()
{
    gcp_require_admin_ajax();
    try {
        // Verify nonce
        if (!check_ajax_referer('create_event_from_contact_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to create events.']);
            return;
        }

        $user_id = get_current_user_id();
        $is_admin_target = false;
        if (current_user_can('manage_options') && !empty($_POST['business_user_id'])) {
            $target_user_id = intval($_POST['business_user_id']);
            if (get_userdata($target_user_id)) {
                $user_id = $target_user_id;
                $is_admin_target = true;
            }
        }
        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;

        if (!$record_id) {
            wp_send_json_error(['message' => 'Invalid record ID.']);
            return;
        }

        // Get participant data
        if (!class_exists('Participants_Db')) {
            wp_send_json_error(['message' => 'Participants Database plugin not found.']);
            return;
        }

        try {
            $participant = Participants_Db::get_participant($record_id);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error retrieving contact data: ' . $e->getMessage()]);
            return;
        }

        if (!$participant || !is_array($participant)) {
            wp_send_json_error(['message' => 'Contact not found.']);
            return;
        }

        // Get contact information
        $first_name = isset($participant['first_name']) ? sanitize_text_field($participant['first_name']) : '';
        $last_name = isset($participant['last_name']) ? sanitize_text_field($participant['last_name']) : '';
        $middle_name = isset($participant['middle_name']) ? sanitize_text_field($participant['middle_name']) : '';
        $date_of_birth_raw = isset($participant['date_of_birth']) ? $participant['date_of_birth'] : '';

        // Build full name
        $full_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
        if (empty($full_name)) {
            $full_name = 'Contact #' . $record_id;
        }

        // Determine event type and date
        $event_date = null;
        $event_title = '';
        $event_category = 'my-events';

        if (!empty($date_of_birth_raw)) {
            try {
                // Handle different date_of_birth formats
                $birthday = null;

                // Check if it's a Unix timestamp (numeric)
                if (is_numeric($date_of_birth_raw)) {
                    $timestamp = intval($date_of_birth_raw);
                    // Check if it's a reasonable timestamp (not just a year)
                    if ($timestamp > 1000000000) {
                        // Unix timestamp (seconds since epoch)
                        // Use WordPress timezone to extract date components, matching how Participants DB displays dates
                        $wp_timezone = wp_timezone();
                        $birthday_dt = new DateTime();
                        $birthday_dt->setTimestamp($timestamp);
                        $birthday_dt->setTimezone($wp_timezone);
                        // Extract date components in WordPress timezone
                        $birth_year = intval($birthday_dt->format('Y'));
                        $birth_month = intval($birthday_dt->format('m'));
                        $birth_day = intval($birthday_dt->format('d'));
                        // Create a new DateTime with just the date (no time component)
                        $birthday = new DateTime(sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day));
                    } elseif ($timestamp > 1900 && $timestamp < 2100) {
                        // Just a year, use January 1st of that year
                        $birthday = new DateTime($timestamp . '-01-01');
                    }
                } else {
                    // Try to parse as date string
                    $birthday = new DateTime($date_of_birth_raw);
                }

                if ($birthday) {
                    // It's a birthday - calculate next birthday
                    // Get date components to avoid timezone issues
                    $birth_year = intval($birthday->format('Y'));
                    $birth_month = intval($birthday->format('m'));
                    $birth_day = intval($birthday->format('d'));

                    $today = new DateTime();
                    $today_year = intval($today->format('Y'));
                    $today_month = intval($today->format('m'));
                    $today_day = intval($today->format('d'));

                    // Create this year's birthday date
                    $this_year_birthday = new DateTime(sprintf('%04d-%02d-%02d', $today_year, $birth_month, $birth_day));

                    // If birthday already passed this year, use next year
                    if ($this_year_birthday < $today) {
                        $next_birthday = new DateTime(sprintf('%04d-%02d-%02d', $today_year + 1, $birth_month, $birth_day));
                    } else {
                        $next_birthday = $this_year_birthday;
                    }

                    // Calculate age — only valid if birth year is in the past.
                    $age = $next_birthday->format('Y') - $birth_year;

                    $event_date = $next_birthday->format('Y-m-d');
                    if ( $age > 0 ) {
                        $suffix = $age == 1 ? 'st' : ( $age == 2 ? 'nd' : ( $age == 3 ? 'rd' : 'th' ) );
                        $event_title = $full_name . ' ' . $age . $suffix . ' Birthday';
                    } else {
                        $event_title = $full_name . '\'s Birthday';
                    }
                    $event_category = 'birthdays';
                } else {
                    throw new Exception('Could not parse date_of_birth: ' . $date_of_birth_raw);
                }
            } catch (Exception $e) {
                // Fallback to generic event
                $event_date = wp_date('Y-m-d');
                $event_title = $full_name . ' Reminder';
                $event_category = 'my-events';
            }
        } else {
            // No date of birth - create a generic event for today
            $event_date = date('Y-m-d');
            $event_title = $full_name . ' Reminder';
            $event_category = 'my-events';
        }

        // Create the event post
        try {
            $post_data = [
                'post_title' => $event_title,
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'tribe_events',
                'post_author' => $user_id,
            ];

            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => 'Error creating event: ' . $post_id->get_error_message()]);
                return;
            }

            if (!$post_id || $post_id === 0) {
                wp_send_json_error(['message' => 'Failed to create event. Please try again.']);
                return;
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error creating event: ' . $e->getMessage()]);
            return;
        }

        // Save event dates - all-day event
        $formatted_start = $event_date . ' 00:00:00';
        $formatted_end = $event_date . ' 23:59:59';

        // Get timezone
        $timezone_string = wp_timezone_string();

        // Save event dates
        update_post_meta($post_id, '_EventStartDate', $formatted_start);
        update_post_meta($post_id, '_EventEndDate', $formatted_end);
        update_post_meta($post_id, '_EventTimezone', $timezone_string);
        update_post_meta($post_id, '_EventAllDay', 'yes');

        // Calculate and save UTC dates
        $timezone = new DateTimeZone($timezone_string);
        try {
            $start_dt = new DateTime($formatted_start, $timezone);
            $end_dt = new DateTime($formatted_end, $timezone);

            $utc_timezone = new DateTimeZone('UTC');
            $start_dt->setTimezone($utc_timezone);
            $end_dt->setTimezone($utc_timezone);

            update_post_meta($post_id, '_EventStartDateUTC', $start_dt->format('Y-m-d H:i:s'));
            update_post_meta($post_id, '_EventEndDateUTC', $end_dt->format('Y-m-d H:i:s'));
        } catch (Exception $e) {
            // Fallback
            update_post_meta($post_id, '_EventStartDateUTC', $formatted_start);
            update_post_meta($post_id, '_EventEndDateUTC', $formatted_end);
        }

        // Save EventDuration
        $start_timestamp = strtotime($formatted_start);
        $end_timestamp = strtotime($formatted_end);
        if ($start_timestamp && $end_timestamp) {
            $duration = $end_timestamp - $start_timestamp;
            update_post_meta($post_id, '_EventDuration', $duration);
        }

        // Save category
        if ($event_category === 'birthdays') {
            wp_set_object_terms($post_id, 'birthdays', 'tribe_events_cat');
        }

        // Save user ID for reminders page filtering
        update_post_meta($post_id, '_gc_user_id', $user_id);

        // Audit trail: record which admin created this event on the business
        // user's behalf, when created via the admin Contact List & Events tab.
        if ($is_admin_target) {
            update_post_meta($post_id, '_gc_last_modified_by_admin', get_current_user_id());
        }

        // Save record ID to link event to contact
        update_post_meta($post_id, '_pdb_record_id', $record_id);

        // Update the reminder field in Participants Database to 'Yes' or '1'
        try {
            // Try to use the Participants Database API first
            if (method_exists('Participants_Db', 'write_participant')) {
                // Get current participant data
                $current_data = Participants_Db::get_participant($record_id);

                if ($current_data && is_array($current_data)) {
                    // Check field name - try both 'reminder' and 'Reminder'
                    $reminder_field = 'reminder';
                    if (!isset($current_data['reminder']) && isset($current_data['Reminder'])) {
                        $reminder_field = 'Reminder';
                    }

                    // Check field type to determine the correct value
                    $reminder_value = 'Yes'; // Default value

                    // Try to get field definition to check if it's a checkbox
                    if (class_exists('PDb_Form_Field_Def') && PDb_Form_Field_Def::is_field($reminder_field)) {
                        $field_def = new PDb_Form_Field_Def($reminder_field);

                        // If it's a checkbox, get the checked value (first option)
                        if ($field_def->form_element() === 'checkbox') {
                            $options = $field_def->option_values();
                            if (!empty($options)) {
                                // Get the first option value (this is the "checked" value)
                                $reminder_value = reset($options);
                            } else {
                                // Default checkbox checked value
                                $reminder_value = 'Yes';
                            }
                        }
                    }

                    // Prepare update data
                    $update_data = [
                        'id' => $record_id,
                        $reminder_field => $reminder_value
                    ];

                    // Use Participants Database API to update
                    $result = Participants_Db::write_participant($update_data, $record_id, 'Event reminder created');

                    if (!$result) {
                        // Fallback to direct database update
                        throw new Exception('API update failed, trying direct update');
                    }
                } else {
                    throw new Exception('Could not retrieve participant data');
                }
            } else {
                throw new Exception('Participants_Db::write_participant method not available');
            }
        } catch (Exception $e) {
            // Fallback to direct database update
            try {
                global $wpdb;

                // Use the participants_table method if available, otherwise use the property
                if (method_exists('Participants_Db', 'participants_table')) {
                    $table_name = Participants_Db::participants_table();
                } else {
                    $table_name = Participants_Db::$participants_table;
                }

                if (empty($table_name)) {
                } else {
                    // Try both field names
                    $reminder_field = 'reminder';

                    // Check if 'Reminder' field exists
                    $field_check = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'Reminder'",
                        $table_name
                    ));

                    if ($field_check > 0) {
                        $reminder_field = 'Reminder';
                    }

                    $update_result = $wpdb->update(
                        $table_name,
                        [$reminder_field => 'Yes'],
                        ['id' => $record_id],
                        ['%s'],
                        ['%d']
                    );

                    if ($update_result === false) {
                    }
                }
            } catch (Exception $e2) {
                // Continue anyway - event was created successfully
            }
        }

        // Schedule reminder email 7 days before event — called here because
        // meta is saved above and save_post fires before meta exists.
        gcp_schedule_reminder_email( $post_id, $user_id, $formatted_start );

        wp_send_json_success([
            'message' => 'Event created successfully!',
            'post_id' => $post_id,
            'event_title' => $event_title,
            'event_date' => $event_date
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'An unexpected error occurred: ' . $e->getMessage()]);
    } catch (Error $e) {
        wp_send_json_error(['message' => 'An unexpected error occurred: ' . $e->getMessage()]);
    }
}

/**
 * Handle AJAX request to delete event from contact reminder button
 */
function handle_delete_event_from_contact()
{
    gcp_require_admin_ajax();
    try {
        // Verify nonce
        if (!check_ajax_referer('delete_event_from_contact_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to delete events.']);
            return;
        }

        $user_id = get_current_user_id();
        if (current_user_can('manage_options') && !empty($_POST['business_user_id'])) {
            $target_user_id = intval($_POST['business_user_id']);
            if (get_userdata($target_user_id)) {
                $user_id = $target_user_id;
            }
        }
        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;

        if (!$record_id) {
            wp_send_json_error(['message' => 'Invalid record ID.']);
            return;
        }

        // Find events associated with this record_id
        $args = [
            'post_type' => 'tribe_events',
            'posts_per_page' => -1,
            'author' => $user_id, // Only events created by current user
            'meta_query' => [
                [
                    'key' => '_pdb_record_id',
                    'value' => $record_id,
                    'compare' => '='
                ]
            ]
        ];

        $events = get_posts($args);

        if (empty($events)) {
            // If no events found by record_id, try to find by contact name (fallback)
            if (!class_exists('Participants_Db')) {
                wp_send_json_error(['message' => 'Participants Database plugin not found.']);
                return;
            }

            try {
                $participant = Participants_Db::get_participant($record_id);

                if ($participant && is_array($participant)) {
                    $first_name = isset($participant['first_name']) ? sanitize_text_field($participant['first_name']) : '';
                    $last_name = isset($participant['last_name']) ? sanitize_text_field($participant['last_name']) : '';
                    $middle_name = isset($participant['middle_name']) ? sanitize_text_field($participant['middle_name']) : '';

                    $full_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);

                    if (!empty($full_name)) {
                        // Search for events with this name in the title
                        $args = [
                            'post_type' => 'tribe_events',
                            'posts_per_page' => -1,
                            'author' => $user_id,
                            's' => $full_name, // Search in title
                        ];

                        $events = get_posts($args);
                    }
                }
            } catch (Exception $e) {
            }
        }

        $deleted_count = 0;
        $errors = [];

        // Delete all found events
        foreach ($events as $event) {
            // Double check the event belongs to the current user
            if ($event->post_author != $user_id) {
                continue;
            }

            $result = wp_delete_post($event->ID, true); // true = force delete (skip trash)

            if ($result) {
                $deleted_count++;
            } else {
                $errors[] = 'Failed to delete event ID: ' . $event->ID;
            }
        }

        if ($deleted_count === 0 && empty($events)) {
            wp_send_json_error(['message' => 'No events found to delete for this contact.']);
            return;
        }

        // Update the reminder field in Participants Database to 'No' or empty
        try {
            // Try to use the Participants Database API first
            if (method_exists('Participants_Db', 'write_participant')) {
                // Get current participant data
                $current_data = Participants_Db::get_participant($record_id);

                if ($current_data && is_array($current_data)) {
                    // Check field name - try both 'reminder' and 'Reminder'
                    $reminder_field = 'reminder';
                    if (!isset($current_data['reminder']) && isset($current_data['Reminder'])) {
                        $reminder_field = 'Reminder';
                    }

                    // Check field type to determine the correct unchecked value
                    $reminder_value = ''; // Default empty value

                    // Try to get field definition to check if it's a checkbox
                    if (class_exists('PDb_Form_Field_Def') && PDb_Form_Field_Def::is_field($reminder_field)) {
                        $field_def = new PDb_Form_Field_Def($reminder_field);

                        // If it's a checkbox, get the unchecked value (second option if exists, otherwise empty)
                        if ($field_def->form_element() === 'checkbox') {
                            $options = $field_def->option_values();
                            if (count($options) > 1) {
                                // Get the second option value (this is typically the "unchecked" value)
                                $options_array = array_values($options);
                                $reminder_value = isset($options_array[1]) ? $options_array[1] : '';
                            } else {
                                // If only one option, empty means unchecked
                                $reminder_value = '';
                            }
                        }
                    }

                    // Prepare update data
                    $update_data = [
                        'id' => $record_id,
                        $reminder_field => $reminder_value
                    ];

                    // Use Participants Database API to update
                    $result = Participants_Db::write_participant($update_data, $record_id, 'Event reminder deleted');

                    if (!$result) {
                        // Fallback to direct database update
                        throw new Exception('API update failed, trying direct update');
                    }
                } else {
                    throw new Exception('Could not retrieve participant data');
                }
            } else {
                throw new Exception('Participants_Db::write_participant method not available');
            }
        } catch (Exception $e) {
            // Fallback to direct database update
            try {
                global $wpdb;

                // Use the participants_table method if available, otherwise use the property
                if (method_exists('Participants_Db', 'participants_table')) {
                    $table_name = Participants_Db::participants_table();
                } else {
                    $table_name = Participants_Db::$participants_table;
                }

                if (!empty($table_name)) {
                    // Try both field names
                    $reminder_field = 'reminder';

                    // Check if 'Reminder' field exists
                    $field_check = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'Reminder'",
                        $table_name
                    ));

                    if ($field_check > 0) {
                        $reminder_field = 'Reminder';
                    }

                    $update_result = $wpdb->update(
                        $table_name,
                        [$reminder_field => ''],
                        ['id' => $record_id],
                        ['%s'],
                        ['%d']
                    );

                    if ($update_result === false) {
                    }
                }
            } catch (Exception $e2) {
                // Continue anyway - events were deleted
            }
        }

        $message = $deleted_count > 0
            ? sprintf('Successfully deleted %d event(s).', $deleted_count)
            : 'Events deleted, but some errors occurred.';

        if (!empty($errors)) {
            $message .= ' Errors: ' . implode(', ', $errors);
        }

        wp_send_json_success([
            'message' => $message,
            'deleted_count' => $deleted_count
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'An unexpected error occurred: ' . $e->getMessage()]);
    } catch (Error $e) {
        wp_send_json_error(['message' => 'An unexpected error occurred: ' . $e->getMessage()]);
    }
}




/**
 * Add admin menu page for Gift Cards Data under WooCommerce Products
 */
add_action('admin_menu', 'gc_giftcards_admin_menu');

function gc_giftcards_admin_menu() {
    // Add as submenu under WooCommerce Products
    // Use edit_products capability (WooCommerce standard for product pages)
    add_submenu_page(
        'edit.php?post_type=product', // Parent slug (WooCommerce Products)
        'Gift Cards Data',            // Page title
        'Gift Cards Data',            // Menu title
        'edit_products',              // Capability - WooCommerce standard
        'gc-giftcards-data',          // Menu slug
        'gc_giftcards_filter_admin_page' // Callback function
    );
}

/**
 * Simple admin page to manage gc-giftcards-for and gc-occasion filter options with drag & drop
 */
function gc_giftcards_filter_admin_page() {
    // Check user capabilities - allow edit_products (WooCommerce) or manage_options
    if (!current_user_can('edit_products') && !current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get active tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'giftcards-for';
    if (!in_array($active_tab, array('giftcards-for', 'occasion', 'gift-item-slider', 'top-picks', 'trending-products'))) {
        $active_tab = 'giftcards-for';
    }

    // Handle trending mode toggle save
    if (isset($_POST['save_trending_mode']) && $active_tab === 'trending-products') {
        check_admin_referer('gc_save_trending_mode_nonce');
        $trending_mode = (isset($_POST['trending_mode']) && $_POST['trending_mode'] === 'personal_favourites') ? 'personal_favourites' : 'our_selection';
        update_option('gc_trending_mode', $trending_mode);
        echo '<div class="notice notice-success is-dismissible"><p>Trending Now display mode saved!</p></div>';
    }

    // Handle top picks mode toggle save
    if (isset($_POST['save_top_picks_mode']) && $active_tab === 'top-picks') {
        check_admin_referer('gc_save_top_picks_mode_nonce');
        $top_picks_mode = (isset($_POST['top_picks_mode']) && $_POST['top_picks_mode'] === 'personal_favourites') ? 'personal_favourites' : 'our_selection';
        update_option('gc_top_picks_mode', $top_picks_mode);
        echo '<div class="notice notice-success is-dismissible"><p>Top Picks display mode saved!</p></div>';
    }

    // Handle gift item slider tag selection save
    if (isset($_POST['save_gift_item_slider_tags']) && $active_tab === 'gift-item-slider') {
        $selected_tags = isset($_POST['gift_item_slider_tags']) ? array_map('intval', $_POST['gift_item_slider_tags']) : array();
        update_option('gc_gift_item_slider_selected_tags', $selected_tags);
        echo '<div class="notice notice-success"><p>Gift Item Slider tags saved successfully!</p></div>';
    }

    // Handle form submission - add new tag
    if (isset($_POST['add_tag']) && !empty($_POST['tag_name'])) {
        $tag_name = sanitize_text_field($_POST['tag_name']);
        $tag_slug = sanitize_title($tag_name);
        
        // Check if tag already exists
        $existing_tag = get_term_by('slug', $tag_slug, 'product_tag');
        if (!$existing_tag) {
            $result = wp_insert_term($tag_name, 'product_tag', array('slug' => $tag_slug));
            if (!is_wp_error($result)) {
                echo '<div class="notice notice-success"><p>Tag "' . esc_html($tag_name) . '" added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error adding tag: ' . esc_html($result->get_error_message()) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-warning"><p>Tag "' . esc_html($tag_name) . '" already exists!</p></div>';
        }
    }

    // Handle tag deletion
    if (isset($_POST['delete_tag']) && !empty($_POST['tag_id'])) {
        $tag_id = intval($_POST['tag_id']);
        $result = wp_delete_term($tag_id, 'product_tag');
        if (!is_wp_error($result) && $result) {
            echo '<div class="notice notice-success"><p>Tag deleted successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error deleting tag.</p></div>';
        }
    }

    // Enqueue jQuery UI Sortable
    wp_enqueue_script('jquery-ui-sortable');

    // Tag-based tabs: get saved order and all tags
    $option_key = $active_tab === 'occasion' ? 'gc_occasion_order' : 'gc_giftcards_for_order';
    $saved_order = get_option($option_key, array());
    
    $all_tags = array();
    $filter_label = $active_tab === 'occasion' ? 'Occasion' : 'Gift Cards For';
    $filter_id = $active_tab === 'occasion' ? 'gc-occasion' : 'gc-giftcards-for';

    if ($active_tab === 'giftcards-for' || $active_tab === 'occasion') {
        // Always get ALL product tags
        $all_tags = get_terms(array(
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        // Sort tags by saved order (if exists), then add any new tags at the end
        if (!is_wp_error($all_tags) && !empty($all_tags)) {
            $tag_map = array();
            foreach ($all_tags as $tag) {
                $tag_map[$tag->term_id] = $tag;
            }
            $ordered_tags = array();
            if (!empty($saved_order)) {
                foreach ($saved_order as $tag_id) {
                    if (isset($tag_map[$tag_id])) {
                        $ordered_tags[] = $tag_map[$tag_id];
                        unset($tag_map[$tag_id]);
                    }
                }
            }
            foreach ($tag_map as $tag) {
                $ordered_tags[] = $tag;
            }
            $all_tags = $ordered_tags;
        }
    }
    // Product-based tabs: get saved product IDs
    $top_picks_product_ids = get_option('gc_top_picks_products', array());
    $trending_product_ids = get_option('gc_trending_products', array());
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Tabs -->
        <h2 class="nav-tab-wrapper" style="margin: 20px 0 0 0;">
            <a href="<?php echo admin_url('edit.php?post_type=product&page=gc-giftcards-data&tab=giftcards-for'); ?>" class="nav-tab <?php echo $active_tab === 'giftcards-for' ? 'nav-tab-active' : ''; ?>">
                Gift Cards For (Filter)
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=product&page=gc-giftcards-data&tab=occasion'); ?>" class="nav-tab <?php echo $active_tab === 'occasion' ? 'nav-tab-active' : ''; ?>">
                Occasion (Filter)
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=product&page=gc-giftcards-data&tab=gift-item-slider'); ?>" class="nav-tab <?php echo $active_tab === 'gift-item-slider' ? 'nav-tab-active' : ''; ?>">
                The Perfect Gift (Slider)
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=product&page=gc-giftcards-data&tab=top-picks'); ?>" class="nav-tab <?php echo $active_tab === 'top-picks' ? 'nav-tab-active' : ''; ?>">
                Top Picks/ Hot Offers (Slider)
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=product&page=gc-giftcards-data&tab=trending-products'); ?>" class="nav-tab <?php echo $active_tab === 'trending-products' ? 'nav-tab-active' : ''; ?>">
                Trending Now/ Best Sellers (Slider)
            </a>
        </h2>
        
        <?php if ($active_tab === 'gift-item-slider'): ?>
            <!-- Gift Item Slider Tag Selection Tab -->
            <?php
            $saved_gift_item_slider_tags = get_option('gc_gift_item_slider_selected_tags', array());
            $all_tags_for_gift_item_slider = get_terms(array(
                'taxonomy' => 'product_tag',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            ));
            ?>
            <div class="gc-admin-slider-section" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
                <h2 style="margin-top: 0;">Select Tags for Gift Item Slider</h2>
                <p class="description">Select the tags whose products will be displayed in the Gift Item Slider on the frontend.</p>
                
                <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label>Available Tags</label>
                            </th>
                            <td>
                                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                    <?php if (!empty($all_tags_for_gift_item_slider) && !is_wp_error($all_tags_for_gift_item_slider)): ?>
                                        <?php foreach ($all_tags_for_gift_item_slider as $tag): ?>
                                            <label style="display: block; padding: 8px; margin: 5px 0; background: #fff; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">
                                                <input type="checkbox" name="gift_item_slider_tags[]" value="<?php echo esc_attr($tag->term_id); ?>" 
                                                    <?php checked(in_array($tag->term_id, $saved_gift_item_slider_tags)); ?>>
                                                <strong><?php echo esc_html($tag->name); ?></strong>
                                                <span style="color: #666; font-size: 12px; margin-left: 10px;">
                                                    (<?php echo esc_html($tag->count); ?> products)
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p>No tags available.</p>
                                    <?php endif; ?>
                                </div>
                                <p class="description">Check the tags you want to include in the Gift Item Slider. Products with these tags will be displayed.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="save_gift_item_slider_tags" class="button button-primary" value="Save Gift Item Slider Tags">
                    </p>
                </form>
            </div>

            <!-- Preview Section -->
            <div class="gc-admin-preview" style="margin: 20px 0; padding: 15px; background: #f0f6fc; border: 1px solid #c3d4e6; border-radius: 4px;">
                <h3 style="margin-top: 0;">Selected Tags</h3>
                <?php if (!empty($saved_gift_item_slider_tags)): ?>
                    <?php
                    $selected_tag_objects = get_terms(array(
                        'taxonomy' => 'product_tag',
                        'include' => $saved_gift_item_slider_tags,
                        'hide_empty' => false
                    ));
                    ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php if (!is_wp_error($selected_tag_objects) && !empty($selected_tag_objects)): ?>
                            <?php foreach ($selected_tag_objects as $tag): ?>
                                <span style="background: #2271b1; color: #fff; padding: 8px 15px; border-radius: 4px; font-size: 14px;">
                                    <?php echo esc_html($tag->name); ?> (<?php echo esc_html($tag->count); ?>)
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <p style="margin-top: 15px;">
                        <strong>Shortcode to display slider:</strong> 
                        <code style="background: #fff; padding: 5px 10px; border-radius: 3px; display: inline-block; margin-left: 10px;">
                            [gc_gift_item_slider]
                        </code>
                    </p>
                <?php else: ?>
                    <p style="color: #999;">No tags selected yet. Select tags above and save.</p>
                <?php endif; ?>
            </div>
            <?php elseif ($active_tab === 'top-picks' || $active_tab === 'trending-products'): ?>
            <?php
            $product_tab_label = $active_tab === 'top-picks' ? 'Top Picks' : 'Trending Now';
            $product_option_ids = $active_tab === 'top-picks' ? $top_picks_product_ids : $trending_product_ids;
            $published_products = get_posts(array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ));
            $selected_product_ids = is_array($product_option_ids) ? $product_option_ids : array();
            $product_map = array();
            foreach ($published_products as $p) {
                $product_map[$p->ID] = $p;
            }
            $available_products = array();
            $selected_products_ordered = array();
            foreach ($published_products as $p) {
                if (!in_array($p->ID, $selected_product_ids)) {
                    $available_products[] = $p;
                }
            }
            foreach ($selected_product_ids as $pid) {
                if (isset($product_map[$pid])) {
                    $selected_products_ordered[] = $product_map[$pid];
                }
            }

            // Trending mode (only relevant for trending-products tab)
            $trending_mode = get_option('gc_trending_mode', 'our_selection');

            // Top picks mode (only relevant for top-picks tab)
            $top_picks_mode = get_option('gc_top_picks_mode', 'our_selection');
            ?>

            <?php if ($active_tab === 'top-picks'): ?>
            <!-- Top Picks / Hot Offers Display Mode Toggle -->
            <div style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
                <h2 style="margin-top: 0;">Display Mode</h2>
                <p class="description" style="margin-bottom: 15px;">
                    Choose how the <strong>Top Picks / Hot Offers</strong> section displays products on the frontend.
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('gc_save_top_picks_mode_nonce'); ?>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">

                        <!-- Option 1: Our Selection -->
                        <label for="top_picks_mode_our_selection" style="
                            display: flex; align-items: flex-start; gap: 14px;
                            padding: 16px 20px; border-radius: 6px; cursor: pointer;
                            border: 2px solid <?php echo $top_picks_mode === 'our_selection' ? '#2271b1' : '#ddd'; ?>;
                            background: <?php echo $top_picks_mode === 'our_selection' ? '#f0f6fc' : '#f9f9f9'; ?>;
                            flex: 1; min-width: 220px; transition: border-color .2s, background .2s;
                        " class="gc-top-picks-mode-label">
                            <input type="radio" id="top_picks_mode_our_selection" name="top_picks_mode" value="our_selection"
                                <?php checked($top_picks_mode, 'our_selection'); ?>
                                style="margin-top: 3px; accent-color: #2271b1; width: 16px; height: 16px; flex-shrink: 0;">
                            <div>
                                <strong style="font-size: 15px; color: #1d2327; display: block; margin-bottom: 4px;">Our Selection</strong>
                                <span style="color: #646970; font-size: 13px;">
                                    Displays the products you manually select and arrange below. You have full control over which products appear.
                                </span>
                            </div>
                        </label>

                        <!-- Option 2: Their Personal Favourites -->
                        <label for="top_picks_mode_personal_favourites" style="
                            display: flex; align-items: flex-start; gap: 14px;
                            padding: 16px 20px; border-radius: 6px; cursor: pointer;
                            border: 2px solid <?php echo $top_picks_mode === 'personal_favourites' ? '#00a32a' : '#ddd'; ?>;
                            background: <?php echo $top_picks_mode === 'personal_favourites' ? '#f0fdf4' : '#f9f9f9'; ?>;
                            flex: 1; min-width: 220px; transition: border-color .2s, background .2s;
                        " class="gc-top-picks-mode-label">
                            <input type="radio" id="top_picks_mode_personal_favourites" name="top_picks_mode" value="personal_favourites"
                                <?php checked($top_picks_mode, 'personal_favourites'); ?>
                                style="margin-top: 3px; accent-color: #00a32a; width: 16px; height: 16px; flex-shrink: 0;">
                            <div>
                                <strong style="font-size: 15px; color: #1d2327; display: block; margin-bottom: 4px;">Their Personal Favourites (Purchase History)</strong>
                                <span style="color: #646970; font-size: 13px;">
                                    Automatically shows each logged-in user their recently purchased products. If no purchase history exists, falls back to the website best sellers.
                                </span>
                            </div>
                        </label>

                    </div>

                    <?php if ($top_picks_mode === 'personal_favourites'): ?>
                    <div style="padding: 10px 14px; background: #f0fdf4; border-left: 4px solid #00a32a; border-radius: 3px; margin-bottom: 12px; font-size: 13px; color: #1a4731;">
                        <strong>Active:</strong> Frontend will show each user their own last-purchased products. Guests and users with no history will see the site-wide best sellers automatically.
                    </div>
                    <?php elseif ($top_picks_mode === 'our_selection'): ?>
                    <div style="padding: 10px 14px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 3px; margin-bottom: 12px; font-size: 13px; color: #1a3550;">
                        <strong>Active:</strong> Frontend will display the manually selected products configured below.
                    </div>
                    <?php endif; ?>

                    <p class="submit" style="margin: 0; padding: 0;">
                        <input type="submit" name="save_top_picks_mode" class="button button-primary" value="Save Display Mode">
                    </p>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Highlight the selected card on radio change
                $('input[name="top_picks_mode"]').on('change', function() {
                    var $labels = $('.gc-top-picks-mode-label');
                    $labels.each(function() {
                        var $radio = $(this).find('input[type="radio"]');
                        var isOur = $radio.val() === 'our_selection';
                        if ($radio.is(':checked')) {
                            $(this).css({
                                'border-color': isOur ? '#2271b1' : '#00a32a',
                                'background': isOur ? '#f0f6fc' : '#f0fdf4'
                            });
                        } else {
                            $(this).css({ 'border-color': '#ddd', 'background': '#f9f9f9' });
                        }
                    });
                });
            });
            </script>
            <?php endif; ?>

            <?php if ($active_tab === 'trending-products'): ?>
            <!-- Trending Now Display Mode Toggle -->
            <div style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
                <h2 style="margin-top: 0;">Display Mode</h2>
                <p class="description" style="margin-bottom: 15px;">
                    Choose how the <strong>Trending Now / Best Sellers</strong> section displays products on the frontend.
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('gc_save_trending_mode_nonce'); ?>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">

                        <!-- Option 1: Our Selection -->
                        <label for="mode_our_selection" style="
                            display: flex; align-items: flex-start; gap: 14px;
                            padding: 16px 20px; border-radius: 6px; cursor: pointer;
                            border: 2px solid <?php echo $trending_mode === 'our_selection' ? '#2271b1' : '#ddd'; ?>;
                            background: <?php echo $trending_mode === 'our_selection' ? '#f0f6fc' : '#f9f9f9'; ?>;
                            flex: 1; min-width: 220px; transition: border-color .2s, background .2s;
                        " class="gc-mode-label">
                            <input type="radio" id="mode_our_selection" name="trending_mode" value="our_selection"
                                <?php checked($trending_mode, 'our_selection'); ?>
                                style="margin-top: 3px; accent-color: #2271b1; width: 16px; height: 16px; flex-shrink: 0;">
                            <div>
                                <strong style="font-size: 15px; color: #1d2327; display: block; margin-bottom: 4px;">Our Selection</strong>
                                <span style="color: #646970; font-size: 13px;">
                                    Displays the products you manually select and arrange below. You have full control over which products appear.
                                </span>
                            </div>
                        </label>

                        <!-- Option 2: Their Personal Favourites -->
                        <label for="mode_personal_favourites" style="
                            display: flex; align-items: flex-start; gap: 14px;
                            padding: 16px 20px; border-radius: 6px; cursor: pointer;
                            border: 2px solid <?php echo $trending_mode === 'personal_favourites' ? '#00a32a' : '#ddd'; ?>;
                            background: <?php echo $trending_mode === 'personal_favourites' ? '#f0fdf4' : '#f9f9f9'; ?>;
                            flex: 1; min-width: 220px; transition: border-color .2s, background .2s;
                        " class="gc-mode-label">
                            <input type="radio" id="mode_personal_favourites" name="trending_mode" value="personal_favourites"
                                <?php checked($trending_mode, 'personal_favourites'); ?>
                                style="margin-top: 3px; accent-color: #00a32a; width: 16px; height: 16px; flex-shrink: 0;">
                            <div>
                                <strong style="font-size: 15px; color: #1d2327; display: block; margin-bottom: 4px;">Their Personal Favourites (Purchase History)</strong>
                                <span style="color: #646970; font-size: 13px;">
                                    Automatically shows each logged-in user their recently purchased products. If no purchase history exists, falls back to the website best sellers.
                                </span>
                            </div>
                        </label>

                    </div>

                    <?php if ($trending_mode === 'personal_favourites'): ?>
                    <div style="padding: 10px 14px; background: #f0fdf4; border-left: 4px solid #00a32a; border-radius: 3px; margin-bottom: 12px; font-size: 13px; color: #1a4731;">
                        <strong>Active:</strong> Frontend will show each user their own last-purchased products. Guests and users with no history will see the site-wide best sellers automatically.
                    </div>
                    <?php elseif ($trending_mode === 'our_selection'): ?>
                    <div style="padding: 10px 14px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 3px; margin-bottom: 12px; font-size: 13px; color: #1a3550;">
                        <strong>Active:</strong> Frontend will display the manually selected products configured below.
                    </div>
                    <?php endif; ?>

                    <p class="submit" style="margin: 0; padding: 0;">
                        <input type="submit" name="save_trending_mode" class="button button-primary" value="Save Display Mode">
                    </p>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Highlight the selected card on radio change
                $('input[name="trending_mode"]').on('change', function() {
                    var $labels = $('.gc-mode-label');
                    $labels.each(function() {
                        var $radio = $(this).find('input[type="radio"]');
                        var isOur = $radio.val() === 'our_selection';
                        if ($radio.is(':checked')) {
                            $(this).css({
                                'border-color': isOur ? '#2271b1' : '#00a32a',
                                'background': isOur ? '#f0f6fc' : '#f0fdf4'
                            });
                        } else {
                            $(this).css({ 'border-color': '#ddd', 'background': '#f9f9f9' });
                        }
                    });
                });
            });
            </script>
            <?php endif; ?>

            <div class="gc-admin-products-section" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
                <h2 style="margin-top: 0;">Select &amp; Arrange Products for <?php echo esc_html($product_tab_label); ?></h2>
                <p class="description">Drag published products from the left column to the right to add them. Reorder in the right column to set display order.</p>
                <?php if ($active_tab === 'trending-products' && $trending_mode === 'personal_favourites'): ?>
                <div style="margin: 10px 0; padding: 10px 14px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 3px; font-size: 13px; color: #664d03;">
                    <strong>Note:</strong> Display mode is currently set to <em>Their Personal Favourites</em>. The products selected below will not appear on the frontend — switch to <em>Our Selection</em> above to use this list.
                </div>
                <?php endif; ?>
                <?php if ($active_tab === 'top-picks' && $top_picks_mode === 'personal_favourites'): ?>
                <div style="margin: 10px 0; padding: 10px 14px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 3px; font-size: 13px; color: #664d03;">
                    <strong>Note:</strong> Display mode is currently set to <em>Their Personal Favourites</em>. The products selected below will not appear on the frontend — switch to <em>Our Selection</em> above to use this list.
                </div>
                <?php endif; ?>
                <div id="gc-products-save-message" style="display: none; margin: 10px 0; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 4px;"></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div style="border: 2px solid #ddd; border-radius: 4px; padding: 15px; background: #f9f9f9;">
                        <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #ddd;">All Published Products</h3>
                        <ul id="gc-products-available" class="gc-products-sortable-list" style="list-style: none; padding: 0; margin: 0; min-height: 200px;">
                            <?php foreach ($available_products as $p): ?>
                                <li data-product-id="<?php echo esc_attr($p->ID); ?>" class="gc-product-item" style="padding: 10px; margin: 8px 0; background: #fff; border: 1px solid #ddd; border-radius: 4px; cursor: move; display: flex; align-items: center;">
                                    <span class="dashicons dashicons-menu-alt" style="color: #999; margin-right: 10px;"></span>
                                    <strong><?php echo esc_html($p->post_title); ?></strong>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($available_products)): ?>
                                <li style="padding: 20px; text-align: center; color: #999; font-style: italic;">All products have been added</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div style="border: 2px solid #2271b1; border-radius: 4px; padding: 15px; background: #f0f6fc;">
                        <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #2271b1; color: #2271b1;">Selected for <?php echo esc_html($product_tab_label); ?></h3>
                        <ul id="gc-products-selected" class="gc-products-sortable-list" style="list-style: none; padding: 0; margin: 0; min-height: 200px;">
                            <?php foreach ($selected_products_ordered as $p): ?>
                                <li data-product-id="<?php echo esc_attr($p->ID); ?>" class="gc-product-item" style="padding: 10px; margin: 8px 0; background: #fff; border: 1px solid #2271b1; border-radius: 4px; cursor: move; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; flex: 1;">
                                        <span class="dashicons dashicons-menu-alt" style="color: #2271b1; margin-right: 10px;"></span>
                                        <strong style="color: #2271b1;"><?php echo esc_html($p->post_title); ?></strong>
                                    </div>
                                    <button type="button" class="button button-small gc-remove-product-btn" data-product-id="<?php echo esc_attr($p->ID); ?>" title="Remove"><span class="dashicons dashicons-dismiss"></span></button>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($selected_products_ordered)): ?>
                                <li style="padding: 20px; text-align: center; color: #999; font-style: italic; border: 2px dashed #ddd;">Drag products here</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <style>
                .gc-product-item.ui-sortable-helper { box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 1000; }
                .gc-products-sortable-list.ui-sortable-placeholder { background: #e0e0e0; border: 2px dashed #999; height: 50px; margin: 8px 0; visibility: visible !important; }
            </style>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var filterType = '<?php echo esc_js($active_tab); ?>';
                    var $available = $('#gc-products-available');
                    var $selected = $('#gc-products-selected');
                    function saveSelectedProducts() {
                        var ids = [];
                        $selected.find('li.gc-product-item').each(function() { ids.push(parseInt($(this).data('product-id'), 10)); });
                        $('#gc-products-save-message').hide();
                        $.post(ajaxurl, {
                            action: 'gc_save_products_order',
                            filter_type: filterType,
                            product_ids: ids,
                            nonce: '<?php echo esc_js(wp_create_nonce('gc_save_products_order_nonce')); ?>'
                        }, function(res) {
                            if (res.success) {
                                $('#gc-products-save-message').css({ background: '#d4edda', borderColor: '#c3e6cb', color: '#155724' }).html('Products saved successfully.').fadeIn().delay(3000).fadeOut();
                            } else {
                                $('#gc-products-save-message').css({ background: '#f8d7da', borderColor: '#f5c6cb', color: '#721c24' }).html('Error: ' + (res.data || 'Unknown')).fadeIn().delay(5000).fadeOut();
                            }
                        }).fail(function() {
                            $('#gc-products-save-message').css({ background: '#f8d7da', borderColor: '#f5c6cb', color: '#721c24' }).html('Error saving. Please try again.').fadeIn().delay(5000).fadeOut();
                        });
                    }
                    if ($available.length && $selected.length) {
                        $available.sortable({
                            connectWith: '#gc-products-selected',
                            items: 'li.gc-product-item',
                            placeholder: 'ui-sortable-placeholder',
                            receive: function(e, ui) {
                                ui.item.css({ 'border-color': '#ddd', 'background': '#fff', 'justify-content': '' });
                                ui.item.find('strong').css('color', '');
                                ui.item.find('.dashicons-menu-alt').css('color', '#999');
                                ui.item.find('.gc-remove-product-btn').remove();
                                var $inner = ui.item.find('div');
                                if ($inner.length) {
                                    $inner.replaceWith($inner.contents());
                                }
                                saveSelectedProducts();
                            }
                        });
                        $selected.sortable({
                            connectWith: '#gc-products-available',
                            items: 'li.gc-product-item',
                            placeholder: 'ui-sortable-placeholder',
                            receive: function(e, ui) {
                                ui.item.css({ 'border-color': '#2271b1', 'background': '#fff', 'justify-content': 'space-between' });
                                ui.item.find('strong').css('color', '#2271b1');
                                ui.item.find('.dashicons-menu-alt').css('color', '#2271b1');
                                if (!ui.item.find('.gc-remove-product-btn').length) {
                                    if (!ui.item.find('div').length) {
                                        ui.item.wrapInner('<div style="display:flex;align-items:center;flex:1;"></div>');
                                    }
                                    ui.item.append('<button type="button" class="button button-small gc-remove-product-btn" data-product-id="' + ui.item.data('product-id') + '" title="Remove"><span class="dashicons dashicons-dismiss"></span></button>');
                                }
                                saveSelectedProducts();
                            },
                            update: function() { saveSelectedProducts(); },
                            remove: function(e, ui) {
                                ui.item.css({ 'border-color': '#ddd', 'background': '#fff' });
                                ui.item.find('strong').css('color', '');
                                ui.item.find('.dashicons-menu-alt').css('color', '#999');
                                ui.item.find('.gc-remove-product-btn').remove();
                                saveSelectedProducts();
                            }
                        });
                    }
                    $(document).on('click', '.gc-remove-product-btn', function(e) {
                        e.preventDefault();
                        var $item = $(this).closest('li.gc-product-item');
                        $item.css({ 'border-color': '#ddd', 'background': '#fff' });
                        $item.find('strong').css('color', '');
                        $item.find('.dashicons-menu-alt').css('color', '#999');
                        $item.find('.gc-remove-product-btn').remove();
                        $item.appendTo($available);
                        saveSelectedProducts();
                    });
                });
            </script>
        <?php else: ?>
            <p class="description" style="margin-top: 15px;">
                <strong>Important:</strong> All tags are displayed below. Drag and drop to select and reorder which tags will appear on the frontend. 
                <br>Only the tags you drag/reorder will be displayed on the frontend filter dropdown.
            </p>

            <!-- Add New Tag Form -->
            <div class="gc-admin-add-tag" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
                <h2 style="margin-top: 0;">Add New Filter Option</h2>
        <?php endif; ?>

        <?php if ($active_tab === 'giftcards-for' || $active_tab === 'occasion'): ?>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tag_name">Tag Name</label>
                        </th>
                        <td>
                            <input type="text" name="tag_name" id="tag_name" class="regular-text" placeholder="e.g., Birthday, Anniversary" required>
                            <p class="description">Enter the name for the new filter option</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="add_tag" class="button button-primary" value="Add Tag">
                </p>
            </form>
        </div>

        <!-- Success/Error Messages -->
        <div id="gc-save-order-message" style="display: none; margin: 10px 0; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 4px;">
            Order saved successfully!
        </div>

        <!-- Two Column Drag & Drop Interface -->
        <div class="gc-admin-tags-list" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
            <h2 style="margin-top: 0;">
                Filter Options 
                <span style="font-size: 14px; font-weight: normal; color: #666; margin-left: 10px;">
                    <span class="dashicons dashicons-move" style="font-size: 16px; vertical-align: middle;"></span>
                    Drag tags from left column to right column to add them to frontend
                </span>
            </h2>
            <p style="margin: 10px 0; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;">
                <strong>Instructions:</strong> Drag tags from "All Tags" column to "Selected Tags for Frontend" column. Only tags in the right column will appear on the frontend filter dropdown.
            </p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Left Column: All Tags -->
                <div style="border: 2px solid #ddd; border-radius: 4px; padding: 15px; background: #f9f9f9;">
                    <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #ddd;">
                        All Tags
                        <span style="font-size: 12px; font-weight: normal; color: #666;">(Drag to right to add)</span>
                    </h3>
                    <?php 
                    // Get selected tag IDs
                    $selected_tag_ids = $saved_order;
                    // Get tags NOT in selected list
                    $available_tags = array();
                    if (!empty($all_tags) && !is_wp_error($all_tags)) {
                        foreach ($all_tags as $tag) {
                            if (!in_array($tag->term_id, $selected_tag_ids)) {
                                $available_tags[] = $tag;
                            }
                        }
                    }
                    ?>
                    <ul id="gc-tags-available" class="gc-sortable-list" style="list-style: none; padding: 0; margin: 0; min-height: 200px;">
                        <?php if (!empty($available_tags)): ?>
                            <?php foreach ($available_tags as $tag): ?>
                                <li data-tag-id="<?php echo esc_attr($tag->term_id); ?>" class="gc-tag-item" style="padding: 10px; margin: 8px 0; background: #fff; border: 1px solid #ddd; border-radius: 4px; cursor: move; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; flex: 1;">
                                        <span class="dashicons dashicons-menu-alt" style="color: #999; margin-right: 10px; font-size: 18px;"></span>
                                        <strong style="font-size: 14px; margin-right: 15px;"><?php echo esc_html($tag->name); ?></strong>
                                        <span style="color: #666; font-size: 12px;">(<?php echo esc_html($tag->count); ?> products)</span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="padding: 20px; text-align: center; color: #999; font-style: italic;">
                                All tags have been added to frontend
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Right Column: Selected Tags for Frontend -->
                <div style="border: 2px solid #2271b1; border-radius: 4px; padding: 15px; background: #f0f6fc;">
                    <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #2271b1; color: #2271b1;">
                        Selected Tags for Frontend
                        <span style="font-size: 12px; font-weight: normal; color: #666;">(Drag to reorder, drag left to remove)</span>
                    </h3>
                    <?php 
                    // Get selected tags in saved order
                    $selected_tags = array();
                    if (!empty($saved_order) && !empty($all_tags) && !is_wp_error($all_tags)) {
                        $tag_map = array();
                        foreach ($all_tags as $tag) {
                            $tag_map[$tag->term_id] = $tag;
                        }
                        foreach ($saved_order as $tag_id) {
                            if (isset($tag_map[$tag_id])) {
                                $selected_tags[] = $tag_map[$tag_id];
                            }
                        }
                    }
                    ?>
                    <ul id="gc-tags-selected" class="gc-sortable-list" style="list-style: none; padding: 0; margin: 0; min-height: 200px;">
                        <?php if (!empty($selected_tags)): ?>
                            <?php foreach ($selected_tags as $tag): ?>
                                <li data-tag-id="<?php echo esc_attr($tag->term_id); ?>" class="gc-tag-item" style="padding: 10px; margin: 8px 0; background: #fff; border: 1px solid #2271b1; border-radius: 4px; cursor: move; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; flex: 1;">
                                        <span class="dashicons dashicons-menu-alt" style="color: #2271b1; margin-right: 10px; font-size: 18px;"></span>
                                        <strong style="font-size: 14px; margin-right: 15px; color: #2271b1;"><?php echo esc_html($tag->name); ?></strong>
                                        <span style="color: #666; font-size: 12px;">(<?php echo esc_html($tag->count); ?> products)</span>
                                    </div>
                                    <button type="button" class="button button-small remove-tag-btn" data-tag-id="<?php echo esc_attr($tag->term_id); ?>" style="margin-left: 10px;" title="Remove from frontend">
                                        <span class="dashicons dashicons-dismiss" style="font-size: 16px;"></span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="padding: 20px; text-align: center; color: #999; font-style: italic; border: 2px dashed #ddd;">
                                Drag tags from left column to add them here
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

            <!-- Preview Section -->
            <div class="gc-admin-preview" style="margin: 20px 0; padding: 15px; background: #f0f6fc; border: 1px solid #c3d4e6; border-radius: 4px;">
                <h3 style="margin-top: 0;">Frontend Preview</h3>
                <p style="margin-bottom: 10px;">This is how the filter will appear on the frontend (only tags you drag/reorder will show):</p>
                <?php
                // Get ordered tags for preview (only tags in saved order)
                $preview_tags = $active_tab === 'occasion' 
                    ? gc_get_ordered_occasion_tags() 
                    : gc_get_ordered_giftcards_for_tags();
                ?>
                <select class="gc-filter-select" id="<?php echo esc_attr($filter_id); ?>" style="min-width: 250px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value=""><?php echo esc_html($filter_label); ?></option>
                    <?php if (!empty($preview_tags) && !is_wp_error($preview_tags)): ?>
                        <?php foreach ($preview_tags as $tag): ?>
                            <option value="<?php echo esc_attr($tag->slug); ?>"><?php echo esc_html($tag->name); ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No tags selected yet. Drag tags above to add them to frontend.</option>
                    <?php endif; ?>
                </select>
                <?php if (empty($preview_tags) || is_wp_error($preview_tags) || empty($preview_tags)): ?>
                    <p style="margin-top: 10px; color: #d63638; font-size: 12px;">
                        <strong>Note:</strong> Drag and reorder tags above to make them appear on the frontend.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .gc-admin-add-tag,
        .gc-admin-tags-list,
        .gc-admin-preview {
            border-radius: 4px;
        }
        .gc-sortable-list {
            min-height: 200px;
        }
        .gc-tag-item {
            transition: background-color 0.2s, transform 0.2s;
        }
        .gc-tag-item:hover {
            background: #f0f0f1 !important;
            transform: translateX(5px);
        }
        .gc-tag-item.ui-sortable-helper {
            background: #fff !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border-color: #2271b1 !important;
            z-index: 1000;
        }
        .gc-sortable-list.ui-sortable-placeholder {
            background: #e0e0e0;
            border: 2px dashed #999;
            visibility: visible !important;
            height: 50px;
            margin: 8px 0;
        }
        .remove-tag-btn {
            cursor: pointer;
        }
        .remove-tag-btn:hover {
            color: #d63638;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var $available = $('#gc-tags-available');
        var $selected = $('#gc-tags-selected');
        var filterType = '<?php echo esc_js($active_tab); ?>';
        
        // Make both columns sortable and connect them
        if ($available.length && $selected.length) {
            $available.sortable({
                connectWith: '#gc-tags-selected',
                items: 'li.gc-tag-item',
                placeholder: 'ui-sortable-placeholder',
                receive: function(event, ui) {
                    // Tag moved to selected - update styling
                    ui.item.css({
                        'border-color': '#2271b1',
                        'background': '#fff'
                    });
                    ui.item.find('strong').css('color', '#2271b1');
                    ui.item.find('.dashicons-menu-alt').css('color', '#2271b1');
                    // Remove remove button if exists, add it
                    if (!ui.item.find('.remove-tag-btn').length) {
                        ui.item.append('<button type="button" class="button button-small remove-tag-btn" data-tag-id="' + ui.item.data('tag-id') + '" style="margin-left: 10px;" title="Remove from frontend"><span class="dashicons dashicons-dismiss" style="font-size: 16px;"></span></button>');
                    }
                    saveSelectedTags();
                }
            });
            
            $selected.sortable({
                connectWith: '#gc-tags-available',
                items: 'li.gc-tag-item',
                placeholder: 'ui-sortable-placeholder',
                receive: function(event, ui) {
                    // Tag moved from available - update styling
                    ui.item.css({
                        'border-color': '#2271b1',
                        'background': '#fff'
                    });
                    ui.item.find('strong').css('color', '#2271b1');
                    ui.item.find('.dashicons-menu-alt').css('color', '#2271b1');
                    // Add remove button
                    if (!ui.item.find('.remove-tag-btn').length) {
                        ui.item.append('<button type="button" class="button button-small remove-tag-btn" data-tag-id="' + ui.item.data('tag-id') + '" style="margin-left: 10px;" title="Remove from frontend"><span class="dashicons dashicons-dismiss" style="font-size: 16px;"></span></button>');
                    }
                    saveSelectedTags();
                },
                update: function(event, ui) {
                    // Tag reordered within selected - save order
                    saveSelectedTags();
                },
                remove: function(event, ui) {
                    // Tag moved back to available - update styling
                    ui.item.css({
                        'border-color': '#ddd',
                        'background': '#fff'
                    });
                    ui.item.find('strong').css('color', '');
                    ui.item.find('.dashicons-menu-alt').css('color', '#999');
                    ui.item.find('.remove-tag-btn').remove();
                    saveSelectedTags();
                }
            });
        }
        
        // Handle remove button clicks
        $(document).on('click', '.remove-tag-btn', function(e) {
            e.preventDefault();
            var $item = $(this).closest('li');
            var tagId = $item.data('tag-id');
            var tagName = $item.find('strong').text();
            
            // Move back to available column
            $item.css({
                'border-color': '#ddd',
                'background': '#fff'
            });
            $item.find('strong').css('color', '');
            $item.find('.dashicons-menu-alt').css('color', '#999');
            $item.find('.remove-tag-btn').remove();
            $item.appendTo($available);
            
            saveSelectedTags();
        });
        
        // Function to save selected tags
        function saveSelectedTags() {
            var tagIds = [];
            $selected.find('li.gc-tag-item').each(function() {
                tagIds.push(parseInt($(this).data('tag-id')));
            });

            // Hide previous message
            $('#gc-save-order-message').hide();
            
            // Save order via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gc_save_filter_order',
                    tag_ids: tagIds,
                    filter_type: filterType,
                    nonce: '<?php echo wp_create_nonce('gc_save_filter_order_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#gc-save-order-message')
                            .removeClass('error')
                            .css({
                                'background': '#d4edda',
                                'border-color': '#c3e6cb',
                                'color': '#155724'
                            })
                            .html('Tags saved successfully! Only tags in right column will appear on frontend.')
                            .fadeIn()
                            .delay(3000)
                            .fadeOut();
                        
                        // Reload page to update preview and column states
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $('#gc-save-order-message')
                            .removeClass('updated')
                            .addClass('error')
                            .css({
                                'background': '#f8d7da',
                                'border-color': '#f5c6cb',
                                'color': '#721c24'
                            })
                            .html('Error saving: ' + (response.data || 'Unknown error'))
                            .fadeIn()
                            .delay(5000)
                            .fadeOut();
                    }
                },
                error: function() {
                    $('#gc-save-order-message')
                        .removeClass('updated')
                        .addClass('error')
                        .css({
                            'background': '#f8d7da',
                            'border-color': '#f5c6cb',
                            'color': '#721c24'
                        })
                        .html('Error saving. Please try again.')
                        .fadeIn()
                        .delay(5000)
                        .fadeOut();
                }
            });
        }
    });
    </script>
    <?php
}

/**
 * AJAX handler to save filter order
 */
add_action('wp_ajax_gc_save_filter_order', 'gc_save_filter_order_handler');

function gc_save_filter_order_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gc_save_filter_order_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    // Get tag IDs
    $tag_ids = isset($_POST['tag_ids']) ? array_map('intval', $_POST['tag_ids']) : array();

    if (empty($tag_ids)) {
        wp_send_json_error('No tag IDs provided');
    }

    // Get filter type
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : 'giftcards-for';
    $option_key = $filter_type === 'occasion' ? 'gc_occasion_order' : 'gc_giftcards_for_order';

    // Save order to options
    update_option($option_key, $tag_ids);

    wp_send_json_success('Order saved successfully');
}


/**
 * AJAX handler to save Top Picks / Trending Now product order
 */
add_action('wp_ajax_gc_save_products_order', 'gc_save_products_order_handler');

function gc_save_products_order_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gc_save_products_order_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    if (!current_user_can('edit_products') && !current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : '';
    if (!in_array($filter_type, array('top-picks', 'trending-products'))) {
        wp_send_json_error('Invalid filter type');
    }
    $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array) $_POST['product_ids']) : array();
    $product_ids = array_filter($product_ids);
    $option_key = $filter_type === 'top-picks' ? 'gc_top_picks_products' : 'gc_trending_products';
    update_option($option_key, $product_ids);
    wp_send_json_success('Products saved successfully');
}

/**
 * Get ordered tags for frontend display - Gift Cards For
 */
function gc_get_ordered_giftcards_for_tags() {
    $saved_order = get_option('gc_giftcards_for_order', array());
    return gc_get_ordered_tags_by_option($saved_order);
}

/**
 * Get ordered tags for frontend display - Occasion
 */
function gc_get_ordered_occasion_tags() {
    $saved_order = get_option('gc_occasion_order', array());
    return gc_get_ordered_tags_by_option($saved_order);
}

/**
 * Helper function to get ordered tags by saved order option
 * Returns ONLY tags that are in the saved order (as selected in admin right column)
 * If no order exists yet, returns empty array (no tags to display)
 */
function gc_get_ordered_tags_by_option($saved_order) {
    if (empty($saved_order) || !is_array($saved_order)) {
        // If no saved order exists, return empty array (no tags selected)
        return array();
    }

    // Get tags in saved order ONLY
    $tags = get_terms(array(
        'taxonomy' => 'product_tag',
        'hide_empty' => false,
        'orderby' => 'include',
        'include' => $saved_order,
        'order' => 'ASC'
    ));

    if (is_wp_error($tags) || empty($tags)) {
        return array();
    }

    // Sort by saved order - return ONLY tags in the saved order
    $tag_map = array();
    foreach ($tags as $tag) {
        $tag_map[$tag->term_id] = $tag;
    }
    
    $ordered_tags = array();
    foreach ($saved_order as $tag_id) {
        if (isset($tag_map[$tag_id])) {
            $ordered_tags[] = $tag_map[$tag_id];
        }
    }

    // Return ONLY the tags in saved order (no additional tags)
    return $ordered_tags;
}

/**
 * Shortcode to display Gift Item Slider
 * Usage: [gc_gift_item_slider]
 */
add_shortcode('gc_gift_item_slider', 'gc_gift_item_slider_shortcode');

function gc_gift_item_slider_shortcode($atts = array()) {
    $atts = shortcode_atts(array(
        'items' => 4, // Number of items to show
        'autoplay' => 'true',
        'loop' => 'true',
        'dots' => 'true',
        'nav' => 'true',
    ), $atts, 'gc_gift_item_slider');

    // Get selected tags from admin
    $selected_tag_ids = get_option('gc_gift_item_slider_selected_tags', array());
    
    if (empty($selected_tag_ids)) {
        return '<p>No tags selected for Gift Item Slider. Please configure in admin panel.</p>';
    }
   // Get tag objects in the order they were selected
    $tags = get_terms(array(
        'taxonomy' => 'product_tag',
        'include' => $selected_tag_ids,
        'hide_empty' => false,
        'orderby' => 'include'
    ));

    if (empty($tags) || is_wp_error($tags)) {
        return '<p>No valid tags found for Gift Item Slider.</p>';
    }

    // Sort tags to match the selected order
    $tag_map = array();
    foreach ($tags as $tag) {
        $tag_map[$tag->term_id] = $tag;
    }
    
    $ordered_tags = array();
    foreach ($selected_tag_ids as $tag_id) {
        if (isset($tag_map[$tag_id])) {
            $ordered_tags[] = $tag_map[$tag_id];
        }
    }
    
    $tags = $ordered_tags;

    ob_start();
    $unique_id = 'gc-gift-item-slider-' . uniqid();
    ?>
    <div class="gc-gift-item-slider-wrapper" style="margin: 30px 0;">
        <div class="owl-carousel gc-gift-item-slider" id="<?php echo esc_attr($unique_id); ?>">
            <?php foreach ($tags as $tag): 
                $tag_link = get_term_link($tag, 'product_tag');
                if (is_wp_error($tag_link)) {
                    $tag_link = '#';
                }
                 // Get tag image
                $image_id = get_term_meta($tag->term_id, 'tag-image-id', true);
                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src();
            ?>
                <div class="item">
                    <a href="<?php echo esc_url($tag_link); ?>" class="tag-card-link" style="text-decoration: none; color: inherit; display: block;">
                        <div class="card gc-tag-card" style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.3s; text-align: center; display: flex; flex-direction: column;">
                            <?php if ($image_url): ?>
                                <div class="gc-tag-image" style="width: 100%; height: 200px; overflow: hidden; background: #f5f5f5;">
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($tag->name); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php endif; ?>
                            <div style="padding: 20px;">
                                <h4 class="gc-tag-title" style="margin: 0 0 10px 0; font-size: 18px; font-weight: 600; color: #333;">
                                    <?php echo esc_html($tag->name); ?>
                                </h4>
                                
                                <p style="margin: 0; font-size: 14px; color: #666;">
                                    <?php 
                                    $product_count = $tag->count;
                                    echo sprintf(
                                        _n('%d product', '%d products', $product_count, 'textdomain'),
                                        $product_count
                                    );
                                    ?>
                                </p>
                                
                                <?php if (!empty($tag->description)): ?>
                                    <p style="margin: 10px 0 0 0; font-size: 12px; color: #999;">
                                        <?php echo esc_html(wp_trim_words($tag->description, 15)); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var $slider = $('#<?php echo esc_js($unique_id); ?>');
        
        if ($slider.length && typeof $.fn.owlCarousel !== 'undefined') {
            $slider.owlCarousel({
                items: <?php echo intval($atts['items']); ?>,
                loop: <?php echo $atts['loop'] === 'true' ? 'true' : 'false'; ?>,
                margin: 20,
                dots: <?php echo $atts['dots'] === 'true' ? 'true' : 'false'; ?>,
                nav: <?php echo $atts['nav'] === 'true' ? 'true' : 'false'; ?>,
                autoplay: <?php echo $atts['autoplay'] === 'true' ? 'true' : 'false'; ?>,
                autoplayTimeout: 3000,
                autoplayHoverPause: true,
                responsive: {
                    0: {
                        items: 1
                    },
                    600: {
                        items: 2
                    },
                    768: {
                        items: 3
                    },
                    992: {
                        items: <?php echo intval($atts['items']); ?>
                    }
                }
            });
        } else if ($slider.length) {
            // Wait for Owl Carousel to load
            var checkOwl = setInterval(function() {
                if (typeof $.fn.owlCarousel !== 'undefined') {
                    clearInterval(checkOwl);
                    $slider.owlCarousel({
                        items: <?php echo intval($atts['items']); ?>,
                        loop: <?php echo $atts['loop'] === 'true' ? 'true' : 'false'; ?>,
                        margin: 20,
                        dots: <?php echo $atts['dots'] === 'true' ? 'true' : 'false'; ?>,
                        nav: <?php echo $atts['nav'] === 'true' ? 'true' : 'false'; ?>,
                        autoplay: <?php echo $atts['autoplay'] === 'true' ? 'true' : 'false'; ?>,
                        autoplayTimeout: 3000,
                        autoplayHoverPause: true,
                        responsive: {
                            0: { items: 1 },
                            600: { items: 2 },
                            768: { items: 3 },
                            992: { items: <?php echo intval($atts['items']); ?> }
                        }
                    });
                }
            }, 100);
        }
    });
    </script>

    <style>
        .gc-gift-item-slider-wrapper {
            padding: 20px 0;
        }
        .gc-gift-item-slider .item {
            padding: 0 10px;
        }
        .gc-gift-item-slider .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .gc-gift-item-slider .owl-nav {
            text-align: center;
            margin-top: 20px;
        }
        .gc-gift-item-slider .owl-nav button {
            background: #2271b1;
            color: #fff;
            border: none;
            padding: 10px 15px;
            margin: 0 5px;
            border-radius: 4px;
            cursor: pointer;
        }
        .gc-gift-item-slider .owl-nav button:hover {
            background: #135e96;
        }
        .gc-gift-item-slider .owl-dots {
            text-align: center;
            margin-top: 20px;
        }
        .gc-gift-item-slider .owl-dots button {
            background: #ddd;
            border: none;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin: 0 5px;
            cursor: pointer;
        }
        .gc-gift-item-slider .owl-dots button.active {
            background: #2271b1;
        }
    </style>
    <?php
    
    wp_reset_postdata();
    return ob_get_clean();
}

/**
 * Admin page callback to display gift cards data with ACF fields and drag & drop
 */
function gc_giftcards_admin_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Enqueue jQuery UI Sortable
    wp_enqueue_script('jquery-ui-sortable');

    // Get selected tag filter
    $selected_tag = isset($_GET['gc_tag_filter']) ? sanitize_text_field($_GET['gc_tag_filter']) : '';

    // Get all product tags for filter dropdown
    $all_tags = get_terms(array(
        'taxonomy' => 'product_tag',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));

    // Build query args
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    );

    // Add taxonomy filter if tag is selected
    if (!empty($selected_tag)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_tag',
                'field' => 'slug',
                'terms' => $selected_tag,
                'operator' => 'IN',
            )
        );
    }

    // Get products
    $products_query = new WP_Query($args);
    $products = $products_query->posts;

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="gc-admin-filter-section" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <form method="get" action="">
                <input type="hidden" name="page" value="gc-giftcards-data">
                <label for="gc_tag_filter" style="font-weight: 600; margin-right: 10px;">Filter by Gift Cards For Tag:</label>
                <select name="gc_tag_filter" id="gc_tag_filter" style="min-width: 250px; padding: 5px;">
                    <option value="">All Tags</option>
                    <?php
                    if (!empty($all_tags) && !is_wp_error($all_tags)) {
                        foreach ($all_tags as $tag) {
                            $selected = ($selected_tag === $tag->slug) ? 'selected' : '';
                            echo '<option value="' . esc_attr($tag->slug) . '" ' . $selected . '>' . esc_html($tag->name) . ' (' . $tag->count . ')</option>';
                        }
                    }
                    ?>
                </select>
                <input type="submit" class="button button-primary" value="Filter" style="margin-left: 10px;">
                <?php if (!empty($selected_tag)): ?>
                    <a href="<?php echo admin_url('admin.php?page=gc-giftcards-data'); ?>" class="button" style="margin-left: 10px;">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="gc-admin-stats" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <p style="margin: 0; font-size: 14px;">
                <strong>Total Products Found:</strong> <?php echo number_format(count($products)); ?>
                <?php if (!empty($selected_tag)): ?>
                    <span style="margin-left: 20px;">
                        <strong>Filtered by:</strong> 
                        <?php
                        $tag_obj = get_term_by('slug', $selected_tag, 'product_tag');
                        echo $tag_obj ? esc_html($tag_obj->name) : esc_html($selected_tag);
                        ?>
                    </span>
                <?php endif; ?>
                <span style="margin-left: 20px; color: #2271b1;">
                    <span class="dashicons dashicons-move" style="font-size: 16px; vertical-align: middle;"></span>
                    <strong>Drag rows to reorder</strong>
                </span>
            </p>
        </div>

        <div id="gc-save-order-message" style="display: none; margin: 10px 0; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 4px;">
            Order saved successfully!
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list gc-sortable-table" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 3%;"><span class="dashicons dashicons-move"></span></th>
                    <th style="width: 4%;">ID</th>
                    <th style="width: 12%;">Product Name</th>
                    <th style="width: 8%;">SKU</th>
                    <th style="width: 8%;">Price</th>
                    <th style="width: 15%;">Gift Cards For Tags</th>
                    <th style="width: 10%;">Brand</th>
                    <th style="width: 8%;">Supplier (ACF)</th>
                    <th style="width: 8%;">Denomination (ACF)</th>
                    <th style="width: 8%;">Discounted Price (ACF)</th>
                    <th style="width: 6%;">Status</th>
                    <th style="width: 6%;">Actions</th>
                </tr>
            </thead>
            <tbody id="gc-products-sortable">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $product_obj = wc_get_product($product->ID);
                        if (!$product_obj) continue;

                        // Get product tags
                        $product_tags = wp_get_post_terms($product->ID, 'product_tag');
                        $tag_names = array();
                        if (!empty($product_tags) && !is_wp_error($product_tags)) {
                            $tag_names = wp_list_pluck($product_tags, 'name');
                        }

                        // Get brand
                        $brand_terms = wp_get_post_terms($product->ID, 'product_brand');
                        $brand_name = '';
                        if (!empty($brand_terms) && !is_wp_error($brand_terms)) {
                            $brand_name = $brand_terms[0]->name;
                        }

                        // Get ACF fields
                        $supplier = '';
                        if (function_exists('get_field')) {
                            $supplier_field = get_field('supplier', $product->ID);
                            if (is_numeric($supplier_field)) {
                                $supplier_user = get_user_by('id', $supplier_field);
                                $supplier = $supplier_user ? $supplier_user->display_name : '';
                            } elseif (is_object($supplier_field)) {
                                $supplier = $supplier_field->display_name;
                            } elseif (is_string($supplier_field)) {
                                $supplier = $supplier_field;
                            }
                        }

                        $denomination_type = function_exists('get_field') ? get_field('denomination_type', $product->ID) : '';
                        $discounted_price = function_exists('get_field') ? get_field('discounted_price', $product->ID) : '';
                        $supplier_sku = get_post_meta($product->ID, '_supplier_sku', true);

                        $sku = $product_obj->get_sku();
                        $price = $product_obj->get_price_html();
                        $status = $product_obj->get_status();
                        $menu_order = $product->menu_order;
                        ?>
                        <tr data-product-id="<?php echo esc_attr($product->ID); ?>" data-menu-order="<?php echo esc_attr($menu_order); ?>">
                            <td class="gc-drag-handle" style="cursor: move; text-align: center; color: #999;">
                                <span class="dashicons dashicons-menu-alt"></span>
                            </td>
                            <td><?php echo esc_html($product->ID); ?></td>
                            <td>
                                <strong>
                                    <a href="<?php echo get_edit_post_link($product->ID); ?>" target="_blank">
                                        <?php echo esc_html($product->post_title); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html($sku ? $sku : '—'); ?></td>
                            <td><?php echo $price ? $price : '—'; ?></td>
                            <td>
                                <?php if (!empty($tag_names)): ?>
                                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                        <?php foreach ($tag_names as $tag_name): ?>
                                            <span style="background: #2271b1; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                                <?php echo esc_html($tag_name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($brand_name ? $brand_name : '—'); ?></td>
                            <td>
                                <?php if (!empty($supplier)): ?>
                                    <span style="background: #00a32a; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                        <?php echo esc_html($supplier); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($denomination_type)): ?>
                                    <span style="background: #d63638; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: capitalize;">
                                        <?php echo esc_html($denomination_type); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($discounted_price)): ?>
                                    <strong style="color: #d63638;"><?php echo wc_price($discounted_price); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 11px; background: <?php echo $status === 'publish' ? '#00a32a' : '#dba617'; ?>; color: #fff;">
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_post_link($product->ID); ?>" class="button button-small" target="_blank">Edit</a>
                                <a href="<?php echo get_permalink($product->ID); ?>" class="button button-small" target="_blank">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 30px; color: #999;">
                            <p>No products found<?php echo !empty($selected_tag) ? ' with the selected tag.' : '.'; ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (count($products) > 50): ?>
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                <p style="margin: 0;">
                    <strong>Note:</strong> Showing all <?php echo number_format(count($products)); ?> products. 
                    Consider using the filter above to narrow down results.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .gc-admin-filter-section,
        .gc-admin-stats {
            border-radius: 4px;
        }
        .wp-list-table th {
            font-weight: 600;
        }
        .wp-list-table td {
            vertical-align: middle;
        }
        .gc-sortable-table tbody tr {
            cursor: move;
        }
        .gc-sortable-table tbody tr.ui-sortable-helper {
            background: #f0f0f1;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .gc-drag-handle {
            cursor: grab !important;
        }
        .gc-drag-handle:active {
            cursor: grabbing !important;
        }
        .gc-drag-handle .dashicons {
            font-size: 18px;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var $sortable = $('#gc-products-sortable');
        
        if ($sortable.length && $sortable.children('tr').length > 1) {
            $sortable.sortable({
                handle: '.gc-drag-handle',
                axis: 'y',
                opacity: 0.6,
                cursor: 'move',
                update: function(event, ui) {
                    var productIds = [];
                    $sortable.find('tr').each(function() {
                        productIds.push($(this).data('product-id'));
                    });

                    // Show loading
                    $('#gc-save-order-message').hide();
                    
                    // Save order via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gc_save_product_order',
                            product_ids: productIds,
                            nonce: '<?php echo wp_create_nonce('gc_save_order_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#gc-save-order-message')
                                    .removeClass('error')
                                    .addClass('updated')
                                    .css({
                                        'background': '#d4edda',
                                        'border-color': '#c3e6cb',
                                        'color': '#155724'
                                    })
                                    .html('Order saved successfully!')
                                    .fadeIn()
                                    .delay(3000)
                                    .fadeOut();
                                
                                // Update menu_order data attributes
                                $sortable.find('tr').each(function(index) {
                                    $(this).attr('data-menu-order', index);
                                });
                            } else {
                                $('#gc-save-order-message')
                                    .removeClass('updated')
                                    .addClass('error')
                                    .css({
                                        'background': '#f8d7da',
                                        'border-color': '#f5c6cb',
                                        'color': '#721c24'
                                    })
                                    .html('Error saving order: ' + (response.data || 'Unknown error'))
                                    .fadeIn()
                                    .delay(5000)
                                    .fadeOut();
                            }
                        },
                        error: function() {
                            $('#gc-save-order-message')
                                .removeClass('updated')
                                .addClass('error')
                                .css({
                                    'background': '#f8d7da',
                                    'border-color': '#f5c6cb',
                                    'color': '#721c24'
                                })
                                .html('Error saving order. Please try again.')
                                .fadeIn()
                                .delay(5000)
                                .fadeOut();
                        }
                    });
                }
            });
        }
    });
    </script>
    <?php
}

/**
 * AJAX handler to save product order
 */
add_action('wp_ajax_gc_save_product_order', 'gc_save_product_order_handler');

function gc_save_product_order_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gc_save_order_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    // Get product IDs
    $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();

    if (empty($product_ids)) {
        wp_send_json_error('No product IDs provided');
    }

    // Update menu_order for each product
    global $wpdb;
    foreach ($product_ids as $order => $product_id) {
        $wpdb->update(
            $wpdb->posts,
            array('menu_order' => $order),
            array('ID' => $product_id),
            array('%d'),
            array('%d')
        );
        
        // Clear cache
        clean_post_cache($product_id);
    }

    wp_send_json_success('Order saved successfully');
}

add_action( 'wp_ajax_bhn_send_offer_link_email', 'bhn_send_offer_link_email' );
function bhn_send_offer_link_email() {
    check_ajax_referer( 'bhn_offer_link_nonce', 'nonce' );

    $current_user = wp_get_current_user();
    if ( ! $current_user->ID ) {
        wp_send_json_error( 'Not logged in.' );
    }

    $link     = isset( $_POST['link'] ) ? esc_url_raw( $_POST['link'] ) : '';
    $offer_title = isset( $_POST['offer_title'] ) ? sanitize_text_field( $_POST['offer_title'] ) : 'Offer';

    if ( empty( $link ) ) {
        wp_send_json_error( 'No link provided.' );
    }

    $to      = $current_user->user_email;
    $subject = 'Your offer link: ' . $offer_title;
    $message = 'Hi ' . $current_user->display_name . ',<br><br>'
             . 'Here is your offer link for <strong>' . esc_html( $offer_title ) . '</strong>:<br><br>'
             . '<a href="' . esc_url( $link ) . '">' . esc_url( $link ) . '</a><br><br>'
             . 'Thanks,<br>' . get_bloginfo( 'name' );
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    $sent = wp_mail( $to, $subject, $message, $headers );
    $sent ? wp_send_json_success() : wp_send_json_error( 'Email failed to send.' );
}

/**
 * DEBUG: Single product order email + video attachment trace
 * Visit any page as admin with ?gc_debug_email=ORDER_ID in the URL
 * Example: https://yoursite.com/?gc_debug_email=1234
 */
// add_action('wp', function () {
//     if (!isset($_GET['gc_debug_email']) || !current_user_can('manage_options')) {
//         return;
//     }

//     $order_id = intval($_GET['gc_debug_email']);
//     $log = [];
//     $log[] = '=== GC EMAIL + VIDEO DEBUG ===';
//     $log[] = 'Order ID: ' . $order_id;
//     $log[] = 'Time: ' . current_time('mysql');

//     // ── STEP 1: Order exists? ──────────────────────────────────────────────
//     $order = wc_get_order($order_id);
//     if (!$order) {
//         $log[] = '[FAIL] STEP 1: Order not found';
//         gc_debug_print($log);
//         return;
//     }
//     $log[] = '[OK] STEP 1: Order found - Status: ' . $order->get_status();

//     // ── STEP 2: Duplicate email flag ───────────────────────────────────────
//     $email_sent_flag = get_post_meta($order_id, '_gift_card_email_sent', true);
//     $log[] = '[INFO] STEP 2: _gift_card_email_sent = "' . $email_sent_flag . '"';
//     if ($email_sent_flag === 'yes') {
//         $log[] = '[WARN] STEP 2: Email already sent flag is YES - this is why emails are being skipped or triggered on re-run';
//     } else {
//         $log[] = '[OK] STEP 2: Email flag not set yet';
//     }

//     // ── STEP 3: Which hooks fire send_blackhawk_gift_card_email_on_order ──
//     $log[] = '[INFO] STEP 3: Checking hooks that trigger send_blackhawk_gift_card_email_on_order...';
//     $hooks = [
//         'woocommerce_payment_complete',
//         'woocommerce_order_status_completed',
//         'woocommerce_order_status_processing',
//     ];
//     foreach ($hooks as $hook) {
//         $priority = has_action($hook, 'send_blackhawk_gift_card_email_on_order');
//         if ($priority !== false) {
//             $log[] = '[HOOK] ' . $hook . ' => priority ' . $priority;
//         } else {
//             $log[] = '[HOOK] ' . $hook . ' => NOT registered';
//         }
//     }
//     $log[] = '[INFO] STEP 3: All 3 hooks fire on different order status transitions - this causes multiple sends if order moves through multiple statuses (e.g. pending → processing → completed)';

//     // ── STEP 4: Order items ────────────────────────────────────────────────
//     $items = $order->get_items();
//     $log[] = '[INFO] STEP 4: Order has ' . count($items) . ' item(s)';

//     foreach ($items as $item_id => $item) {
//         $log[] = '--- Item ID: ' . $item_id . ' | ' . $item->get_name() . ' ---';

//         // ── STEP 5: Meta values on order item ─────────────────────────────
//         $log[] = '[INFO] STEP 5: Reading order item meta...';

//         $meta_keys = [
//             '_recipient_name',
//             '_recipient_email',
//             '_delivery_email',
//             '_recipient_phone',
//             'mobile_number',
//             '_gift_card_name',
//             '_gift_card_title',
//             '_gift_card_sku',
//             '_gift_card_price',
//             '_gift_card_image',
//             'gift_email_animation',
//             '_gift_email_animation',
//             'gift_video_message',
//             'gift_image_message',
//             '_delivery_method',
//             '_delivery_timing',
//             '_scheduled_date',
//             '_sender_name',
//             '_gift_message',
//             'gift_message',
//             '_gift_card_post_id',
//             '_gift_card_post_ids',
//             '_gift_card_number_enc',
//             '_activation_expiry_type',
//             '_activation_expiry_date',
//         ];

//         foreach ($meta_keys as $key) {
//             $val = wc_get_order_item_meta($item_id, $key, true);
//             $display = $val;
//             if (is_array($val)) {
//                 $display = json_encode($val);
//             }
//             $status = (!empty($val)) ? '[OK]  ' : '[MISS]';
//             $log[] = $status . ' ' . $key . ' = ' . (empty($val) ? '(empty)' : $display);
//         }

//         // ── STEP 6: Video URL specifically ────────────────────────────────
//         $log[] = '[INFO] STEP 6: Video attachment check...';
//         $video_url = wc_get_order_item_meta($item_id, 'gift_video_message', true);
//         if (empty($video_url)) {
//             $log[] = '[FAIL] STEP 6: gift_video_message is EMPTY - video was never saved to order item meta';
//             $log[] = '       Check that single-product.js is submitting gc_buy_now_video_message';
//             $log[] = '       Check that save_order_product_data_and_meta() is running and saving the value';
//         } else {
//             $log[] = '[OK]   STEP 6: gift_video_message URL = ' . $video_url;

//             // ── STEP 7: URL → file path conversion ────────────────────────
//             $log[] = '[INFO] STEP 7: Converting URL to file path...';
//             $upload_dir = wp_upload_dir();
//             $log[] = '       upload baseurl = ' . $upload_dir['baseurl'];
//             $log[] = '       upload basedir = ' . $upload_dir['basedir'];
//             $video_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $video_url);
//             $log[] = '       resolved path = ' . $video_path;

//             if (file_exists($video_path)) {
//                 $log[] = '[OK]   STEP 7: File EXISTS on disk - size: ' . filesize($video_path) . ' bytes';
//             } else {
//                 $log[] = '[FAIL] STEP 7: File NOT FOUND on disk at resolved path';
//                 $log[] = '       This means the URL/path conversion is wrong OR file was deleted';
//             }
//         }

//         // ── STEP 8: Gift card post IDs ────────────────────────────────────
//         $log[] = '[INFO] STEP 8: Checking gift card post IDs...';
//         $post_ids = wc_get_order_item_meta($item_id, '_gift_card_post_ids', true);
//         $single_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
//         $log[] = '       _gift_card_post_ids = ' . json_encode($post_ids);
//         $log[] = '       _gift_card_post_id  = ' . $single_id;

//         if (!is_array($post_ids) || empty($post_ids)) {
//             $post_ids = !empty($single_id) ? [$single_id] : [];
//         }

//         if (empty($post_ids)) {
//             $log[] = '[FAIL] STEP 8: No gift card post IDs found - gift card post was never created';
//         } else {
//             $log[] = '[OK]   STEP 8: ' . count($post_ids) . ' gift card post(s) found';

//             foreach ($post_ids as $gc_post_id) {
//                 $log[] = '  → Gift Card Post ID: ' . $gc_post_id;
//                 $send_status = get_post_meta($gc_post_id, '_gift_card_send', true);
//                 $log[] = '    _gift_card_send = "' . $send_status . '"';

//                 // ── STEP 9: send_giftcard_email_with_pdf signature ────────
//                 $log[] = '[INFO] STEP 9: Checking send_giftcard_email_with_pdf function signature...';
//                 if (function_exists('send_giftcard_email_with_pdf')) {
//                     $rf = new ReflectionFunction('send_giftcard_email_with_pdf');
//                     $params = [];
//                     foreach ($rf->getParameters() as $p) {
//                         $params[] = '$' . $p->getName() . ($p->isOptional() ? ' (optional)' : ' (required)');
//                     }
//                     $log[] = '[OK]   STEP 9: Function exists. Params: ' . implode(', ', $params);
//                     $param_count = $rf->getNumberOfParameters();
//                     if ($param_count < 4) {
//                         $log[] = '[FAIL] STEP 9: Function only has ' . $param_count . ' param(s) - does NOT accept $attachments yet';
//                         $log[] = '       You must add $attachments = [] as 4th param and pass it to wp_mail()';
//                     } else {
//                         $log[] = '[OK]   STEP 9: Function has ' . $param_count . ' params - should support $attachments';
//                     }
//                 } else {
//                     $log[] = '[FAIL] STEP 9: send_giftcard_email_with_pdf() does NOT exist';
//                 }
//             }
//         }
//     }

//     // ── STEP 10: Why is email firing 3 times? ─────────────────────────────
//     $log[] = '';
//     $log[] = '[INFO] STEP 10: Diagnosing triple-fire...';
//     $log[] = '       The function is hooked to 3 separate WooCommerce actions:';
//     $log[] = '         1. woocommerce_payment_complete';
//     $log[] = '         2. woocommerce_order_status_completed';
//     $log[] = '         3. woocommerce_order_status_processing';
//     $log[] = '       If order moves: pending → processing → completed, hooks 3 then 2 fire.';
//     $log[] = '       If payment also fires hook 1, that is 3 total.';
//     $log[] = '       The _gift_card_email_sent flag SHOULD prevent duplicates but only if';
//     $log[] = '       it is set BEFORE the next hook fires (race condition on fast transitions).';

//     $bhn_sent = get_post_meta($order_id, '_bhn_order_sent', true);
//     $log[] = '       _bhn_order_sent = "' . $bhn_sent . '"';
//     $log[] = '       _gift_card_email_sent = "' . $email_sent_flag . '"';

//     if ($email_sent_flag !== 'yes') {
//         $log[] = '[WARN] STEP 10: Email flag not set - if 3 hooks fire close together, all 3 will pass the flag check';
//     }

//     // ── STEP 11: Session data check ───────────────────────────────────────
//     $log[] = '';
//     $log[] = '[INFO] STEP 11: Checking WooCommerce session for media (only works during active session)...';
//     if (function_exists('WC') && WC()->session) {
//         $product_ids_to_check = [];
//         foreach ($order->get_items() as $item) {
//             $p = $item->get_product();
//             if ($p) $product_ids_to_check[] = $p->get_id();
//         }
//         foreach ($product_ids_to_check as $pid) {
//             $sess_video = WC()->session->get('gc_media_video_' . $pid, '');
//             $sess_anim  = WC()->session->get('gc_media_animation_' . $pid, '');
//             $log[] = '  Product ' . $pid . ': session video = "' . $sess_video . '"';
//             $log[] = '  Product ' . $pid . ': session animation = "' . $sess_anim . '"';
//         }
//     } else {
//         $log[] = '[INFO] STEP 11: No active WC session (expected when running as admin debug)';
//     }

//     $log[] = '';
//     $log[] = '=== END DEBUG ===';

//     gc_debug_print($log);
// });

function gc_debug_print(array $lines) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<title>GC Email Debug</title>';
    echo '<style>
        body { font-family: monospace; font-size: 13px; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .ok   { color: #6a9955; }
        .fail { color: #f44747; font-weight: bold; }
        .warn { color: #ce9178; }
        .info { color: #569cd6; }
        .hook { color: #c586c0; }
        pre   { margin: 2px 0; }
    </style></head><body>';
    foreach ($lines as $line) {
        $class = 'info';
        if (strpos($line, '[OK]') !== false)   $class = 'ok';
        if (strpos($line, '[FAIL]') !== false) $class = 'fail';
        if (strpos($line, '[WARN]') !== false) $class = 'warn';
        if (strpos($line, '[HOOK]') !== false) $class = 'hook';
        echo '<pre class="' . $class . '">' . htmlspecialchars($line) . '</pre>';
    }
    echo '</body></html>';
    exit;
}