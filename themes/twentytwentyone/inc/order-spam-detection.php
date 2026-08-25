<?php
/**
 * GiftCardsPlus — Order Spam Detection
 *
 * Detects abnormal behaviour: rapid checkout attempts, order flooding,
 * and repeated failed payments. Logs all events and alerts the admin.
 *
 * HOW TO INSTALL:
 * Add this single line to functions.php (after the security helpers section):
 *
 *   require_once get_template_directory() . '/inc/order-spam-detection.php';
 *
 * Then place this file at:
 *   /wp-content/themes/<your-theme>/inc/order-spam-detection.php
 *
 * CUSTOMISING THRESHOLDS (add to functions.php):
 *   add_filter( 'gcp_spam_thresholds', function( $t ) {
 *       $t['orders_per_ip_1hour'] = 5;
 *       return $t;
 *   });
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Thresholds ───────────────────────────────────────────────────────────────

function gcp_spam_get_thresholds(): array {
    return apply_filters( 'gcp_spam_thresholds', [
        'checkout_attempts_per_ip_30min' => 20,
        'orders_per_ip_1hour'            => 20,
        'orders_per_email_24hour'        => 25,
        'failed_payments_per_ip_1hour'   => 25,
        'alert_email'                    => get_option( 'admin_email' ),
        'alert_cooldown_minutes'         => 60,
    ] );
}

// ─── Database Table ───────────────────────────────────────────────────────────

function gcp_spam_create_table(): void {
    global $wpdb;
    $table           = $wpdb->prefix . 'gcp_spam_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type  VARCHAR(50)     NOT NULL,
        ip_address  VARCHAR(45)     NOT NULL DEFAULT '',
        email       VARCHAR(255)    NOT NULL DEFAULT '',
        user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        order_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
        details     TEXT,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY event_type (event_type),
        KEY ip_address (ip_address),
        KEY email      (email),
        KEY created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'gcp_spam_db_version', '1.0' );
}

add_action( 'init', function () {
    if ( get_option( 'gcp_spam_db_version' ) !== '1.0' ) {
        gcp_spam_create_table();
    }
} );

// ─── Helpers ──────────────────────────────────────────────────────────────────

function gcp_spam_get_ip(): string {
    foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) {
            return sanitize_text_field( trim( explode( ',', $_SERVER[ $key ] )[0] ) );
        }
    }
    return '';
}

function gcp_spam_log_event( string $event_type, array $data = [] ): void {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'gcp_spam_log',
        [
            'event_type' => $event_type,
            'ip_address' => $data['ip']       ?? gcp_spam_get_ip(),
            'email'      => $data['email']    ?? '',
            'user_id'    => $data['user_id']  ?? get_current_user_id(),
            'order_id'   => $data['order_id'] ?? 0,
            'details'    => isset( $data['details'] ) ? wp_json_encode( $data['details'] ) : '',
            'created_at' => current_time( 'mysql' ),
        ],
        [ '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
    );
}

/**
 * Increment a rolling-window counter stored as a transient.
 * Returns the new count.
 */
function gcp_spam_increment_counter( string $key, int $window_seconds ): int {
    $transient_key = 'gcp_sp_' . md5( $key );
    $count         = (int) get_transient( $transient_key );
    $count++;
    set_transient( $transient_key, $count, $window_seconds );
    return $count;
}

function gcp_spam_get_count( string $key ): int {
    return (int) get_transient( 'gcp_sp_' . md5( $key ) );
}

/**
 * Send an admin alert email, throttled by a cooldown transient.
 */
function gcp_spam_send_alert( string $subject, string $message, string $dedup_key ): void {
    $thresholds   = gcp_spam_get_thresholds();
    $cooldown_key = 'gcp_sp_alert_' . md5( $dedup_key );

    if ( get_transient( $cooldown_key ) ) {
        return;
    }

    set_transient( $cooldown_key, 1, $thresholds['alert_cooldown_minutes'] * MINUTE_IN_SECONDS );

    $site     = get_bloginfo( 'name' );
    $log_url  = admin_url( 'admin.php?page=gcp-spam-log' );
    $body     = "Automated alert from {$site}.\n\n{$message}\n\nTime: " . current_time( 'mysql' ) . "\nLog: {$log_url}";

    wp_mail( $thresholds['alert_email'], "[{$site}] {$subject}", $body );
}

// ─── Hook 1: Checkout attempt ─────────────────────────────────────────────────

add_action( 'woocommerce_checkout_process', 'gcp_spam_check_checkout' );

function gcp_spam_check_checkout(): void {
    $ip         = gcp_spam_get_ip();
    $email      = sanitize_email( $_POST['billing_email'] ?? '' );
    $thresholds = gcp_spam_get_thresholds();

    // Track attempt
    $attempts = gcp_spam_increment_counter( "checkout_attempt_ip_{$ip}", 30 * MINUTE_IN_SECONDS );

    // Rule 1 — too many checkout attempts from this IP
    if ( $attempts > $thresholds['checkout_attempts_per_ip_30min'] ) {
        gcp_spam_log_event( 'checkout_blocked', [
            'ip'      => $ip,
            'email'   => $email,
            'details' => [ 'reason' => 'checkout_attempts_exceeded', 'count' => $attempts ],
        ] );
        gcp_spam_send_alert(
            'Checkout Spam Detected',
            "IP {$ip} made {$attempts} checkout attempts in 30 min.\nEmail: {$email}",
            "checkout_ip_{$ip}"
        );
        wc_add_notice( __( 'Too many checkout attempts. Please wait before trying again.', 'woocommerce' ), 'error' );
        return;
    }

    // Rule 2 — too many orders from this IP in the last hour
    $ip_orders = gcp_spam_get_count( "orders_ip_{$ip}" );
    if ( $ip_orders >= $thresholds['orders_per_ip_1hour'] ) {
        gcp_spam_log_event( 'checkout_blocked', [
            'ip'      => $ip,
            'email'   => $email,
            'details' => [ 'reason' => 'orders_per_ip_exceeded', 'count' => $ip_orders ],
        ] );
        gcp_spam_send_alert(
            'Order Spam Detected — IP',
            "IP {$ip} placed {$ip_orders} orders in the last hour.\nEmail: {$email}",
            "orders_ip_{$ip}"
        );
        wc_add_notice( __( 'You have reached the maximum number of orders allowed. Please try again later.', 'woocommerce' ), 'error' );
        return;
    }

    // Rule 3 — too many orders from this email in last 24 hours
    if ( $email ) {
        $email_orders = gcp_spam_get_count( "orders_email_{$email}" );
        if ( $email_orders >= $thresholds['orders_per_email_24hour'] ) {
            gcp_spam_log_event( 'checkout_blocked', [
                'ip'      => $ip,
                'email'   => $email,
                'details' => [ 'reason' => 'orders_per_email_exceeded', 'count' => $email_orders ],
            ] );
            gcp_spam_send_alert(
                'Order Spam Detected — Email',
                "Email {$email} placed {$email_orders} orders in 24 hours.\nIP: {$ip}",
                "orders_email_{$email}"
            );
            wc_add_notice( __( 'You have reached the maximum number of orders allowed. Please try again later.', 'woocommerce' ), 'error' );
            return;
        }
    }

    // Rule 4 — too many failed payments from this IP
    $failed = gcp_spam_get_count( "failed_payment_ip_{$ip}" );
    if ( $failed >= $thresholds['failed_payments_per_ip_1hour'] ) {
        gcp_spam_log_event( 'checkout_blocked', [
            'ip'      => $ip,
            'email'   => $email,
            'details' => [ 'reason' => 'failed_payments_exceeded', 'count' => $failed ],
        ] );
        gcp_spam_send_alert(
            'Failed Payment Spam Detected',
            "IP {$ip} had {$failed} failed payments in 1 hour.\nEmail: {$email}",
            "failed_ip_{$ip}"
        );
        wc_add_notice( __( 'Multiple failed payment attempts detected. Please contact support.', 'woocommerce' ), 'error' );
        return;
    }
}

// ─── Hook 2: Order placed successfully ───────────────────────────────────────

add_action( 'woocommerce_checkout_order_processed', 'gcp_spam_on_order_placed', 10, 3 );

function gcp_spam_on_order_placed( $order_id, $posted_data, $order ): void {
    $ip    = gcp_spam_get_ip();
    $email = $order->get_billing_email();

    gcp_spam_increment_counter( "orders_ip_{$ip}", HOUR_IN_SECONDS );
    if ( $email ) {
        gcp_spam_increment_counter( "orders_email_{$email}", DAY_IN_SECONDS );
    }

    gcp_spam_log_event( 'order_placed', [
        'ip'       => $ip,
        'email'    => $email,
        'user_id'  => $order->get_user_id(),
        'order_id' => $order_id,
        'details'  => [
            'ip_orders_this_hour'  => gcp_spam_get_count( "orders_ip_{$ip}" ),
            'email_orders_24h'     => $email ? gcp_spam_get_count( "orders_email_{$email}" ) : 0,
        ],
    ] );
}

// ─── Hook 3: Payment failed ───────────────────────────────────────────────────

add_action( 'woocommerce_order_status_failed', 'gcp_spam_on_payment_failed' );

function gcp_spam_on_payment_failed( $order_id ): void {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $ip           = gcp_spam_get_ip();
    $email        = $order->get_billing_email();
    $failed_count = gcp_spam_increment_counter( "failed_payment_ip_{$ip}", HOUR_IN_SECONDS );

    gcp_spam_log_event( 'payment_failed', [
        'ip'       => $ip,
        'email'    => $email,
        'user_id'  => $order->get_user_id(),
        'order_id' => $order_id,
        'details'  => [ 'failed_this_hour' => $failed_count ],
    ] );

    $thresholds = gcp_spam_get_thresholds();
    if ( $failed_count >= $thresholds['failed_payments_per_ip_1hour'] ) {
        gcp_spam_send_alert(
            'High Failed Payment Rate',
            "IP {$ip} reached {$failed_count} failed payments in 1 hour.\nEmail: {$email}\nOrder: #{$order_id}",
            "failed_ip_{$ip}"
        );
    }
}

// ─── Admin: Log Viewer ────────────────────────────────────────────────────────

add_action( 'admin_menu', 'gcp_spam_admin_menu' );

function gcp_spam_admin_menu(): void {
    add_submenu_page(
        'woocommerce',
        'Spam Detection Log',
        'Spam Detection',
        'manage_options',
        'gcp-spam-log',
        'gcp_spam_admin_page'
    );
}

function gcp_spam_admin_page(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'gcp_spam_log';

    // Actions
    if ( isset( $_POST['gcp_clear_old'] ) && check_admin_referer( 'gcp_spam_clear_old' ) ) {
        $wpdb->query( "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)" );
        echo '<div class="notice notice-success"><p>Entries older than 30 days removed.</p></div>';
    }
    if ( isset( $_POST['gcp_clear_log'] ) && check_admin_referer( 'gcp_spam_clear_all' ) ) {
        $wpdb->query( "TRUNCATE TABLE {$table}" );
        echo '<div class="notice notice-success"><p>Log cleared.</p></div>';
    }

    $thresholds   = gcp_spam_get_thresholds();
    $per_page     = 50;
    $current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    $offset       = ( $current_page - 1 ) * $per_page;
    $filter_type  = sanitize_text_field( $_GET['event_type'] ?? '' );
    $filter_ip    = sanitize_text_field( $_GET['ip'] ?? '' );

    $where = 'WHERE 1=1';
    if ( $filter_type ) $where .= $wpdb->prepare( ' AND event_type = %s', $filter_type );
    if ( $filter_ip )   $where .= $wpdb->prepare( ' AND ip_address = %s', $filter_ip );

    $total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
    $rows        = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}" );
    $total_pages = (int) ceil( $total / $per_page );

    // Summary stats
    $blocked_today  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE event_type = 'checkout_blocked' AND created_at >= CURDATE()" );
    $failed_today   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE event_type = 'payment_failed'   AND created_at >= CURDATE()" );
    $orders_today   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE event_type = 'order_placed'     AND created_at >= CURDATE()" );
    $top_ip         = $wpdb->get_row( "SELECT ip_address, COUNT(*) as cnt FROM {$table} WHERE event_type = 'checkout_blocked' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY ip_address ORDER BY cnt DESC LIMIT 1" );
    ?>
    <div class="wrap">
        <h1>Spam Detection Log</h1>

        <!-- Summary Cards -->
        <div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">
            <?php foreach ( [
                [ 'Blocked Today',         $blocked_today,  '#d63638' ],
                [ 'Failed Payments Today', $failed_today,   '#f0b849' ],
                [ 'Orders Logged Today',   $orders_today,   '#00a32a' ],
                [ 'Top Blocked IP (24h)',  $top_ip ? "{$top_ip->ip_address} ({$top_ip->cnt}×)" : '—', '#2271b1' ],
            ] as [ $label, $value, $color ] ): ?>
                <div style="background:#fff;border:1px solid #ccd0d4;border-top:4px solid <?php echo $color; ?>;padding:16px 20px;border-radius:4px;min-width:160px;flex:1;">
                    <div style="font-size:26px;font-weight:700;color:<?php echo $color; ?>;"><?php echo esc_html( $value ); ?></div>
                    <div style="font-size:13px;color:#666;margin-top:4px;"><?php echo esc_html( $label ); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Active Thresholds -->
        <div style="background:#fff;border:1px solid #ccd0d4;padding:14px 20px;margin-bottom:20px;border-radius:4px;font-size:13px;">
            <strong>Active Thresholds —</strong>
            &nbsp;Checkout attempts: <strong><?php echo $thresholds['checkout_attempts_per_ip_30min']; ?>/30 min per IP</strong>
            &nbsp;·&nbsp; Orders: <strong><?php echo $thresholds['orders_per_ip_1hour']; ?>/hr per IP</strong>
            &nbsp;·&nbsp; <strong><?php echo $thresholds['orders_per_email_24hour']; ?>/24 h per email</strong>
            &nbsp;·&nbsp; Failed payments: <strong><?php echo $thresholds['failed_payments_per_ip_1hour']; ?>/hr per IP</strong>
            &nbsp;&nbsp;<span style="color:#999;">Override via <code>gcp_spam_thresholds</code> filter.</span>
        </div>

        <!-- Filters -->
        <form method="get" style="margin-bottom:16px;display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="page" value="gcp-spam-log">
            <select name="event_type">
                <option value="">All Event Types</option>
                <?php foreach ( [ 'checkout_blocked' => 'Checkout Blocked', 'order_placed' => 'Order Placed', 'payment_failed' => 'Payment Failed' ] as $val => $lbl ): ?>
                    <option value="<?php echo $val; ?>" <?php selected( $filter_type, $val ); ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="ip" placeholder="Filter by IP" value="<?php echo esc_attr( $filter_ip ); ?>" style="width:150px;">
            <button type="submit" class="button">Filter</button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=gcp-spam-log' ) ); ?>" class="button">Reset</a>
            <span style="margin-left:auto;color:#666;font-size:13px;"><?php echo $total; ?> total entries</span>
        </form>

        <!-- Log Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th style="width:160px;">Event</th>
                    <th style="width:130px;">IP Address</th>
                    <th>Email</th>
                    <th style="width:70px;">Order</th>
                    <th>Details</th>
                    <th style="width:155px;">Date / Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rows ) ): ?>
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:#666;">No log entries found.</td></tr>
                <?php else: ?>
                    <?php
                    $badge = [
                        'checkout_blocked' => [ '#d63638', 'Checkout Blocked' ],
                        'payment_failed'   => [ '#f0b849', 'Payment Failed'   ],
                        'order_placed'     => [ '#00a32a', 'Order Placed'     ],
                    ];
                    foreach ( $rows as $row ):
                        [ $color, $label ] = $badge[ $row->event_type ] ?? [ '#999', $row->event_type ];
                        $details = $row->details ? json_decode( $row->details, true ) : [];
                    ?>
                    <tr>
                        <td><?php echo (int) $row->id; ?></td>
                        <td>
                            <span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:700;background:<?php echo $color; ?>;color:#fff;">
                                <?php echo esc_html( $label ); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=gcp-spam-log&ip=' . urlencode( $row->ip_address ) ) ); ?>">
                                <?php echo esc_html( $row->ip_address ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $row->email ?: '—' ); ?></td>
                        <td>
                            <?php if ( $row->order_id ): ?>
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row->order_id . '&action=edit' ) ); ?>">#<?php echo (int) $row->order_id; ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:#555;">
                            <?php if ( ! empty( $details ) ) {
                                foreach ( $details as $k => $v ) {
                                    echo '<b>' . esc_html( $k ) . ':</b> ' . esc_html( $v ) . ' &nbsp;';
                                }
                            } ?>
                        </td>
                        <td style="font-size:12px;"><?php echo esc_html( $row->created_at ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ): ?>
            <div class="tablenav bottom" style="margin-top:12px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $total; ?> items</span>
                    <?php echo paginate_links( [
                        'base'    => admin_url( 'admin.php?page=gcp-spam-log' ) . '%_%',
                        'format'  => '&paged=%#%',
                        'current' => $current_page,
                        'total'   => $total_pages,
                    ] ); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Maintenance Actions -->
        <div style="display:flex;gap:12px;margin-top:24px;">
            <form method="post">
                <?php wp_nonce_field( 'gcp_spam_clear_old' ); ?>
                <button type="submit" name="gcp_clear_old" class="button"
                    onclick="return confirm('Remove all entries older than 30 days?')">
                    Clear Old Entries (&gt;30 days)
                </button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'gcp_spam_clear_all' ); ?>
                <button type="submit" name="gcp_clear_log" class="button button-link-delete"
                    onclick="return confirm('Clear the entire log? This cannot be undone.')">
                    Clear Entire Log
                </button>
            </form>
        </div>
    </div>
    <?php
}
