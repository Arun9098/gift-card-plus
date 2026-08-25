<?php
/**
 * Plugin Name: BlackHawk Integration
 * Description: BlackHawk Network gift card integration for WooCommerce.
 * Version: 1.2
 * Author: Elsner Technologies Pvt Ltd
 * Author URI: https://elsner.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: blackhawk_integration
 */

if ( ! defined( 'WPINC' ) ) die;
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BLACKHAWK_INTEGRATION_WITH_WOOCOMMERCE_VERSION', '1.2.0' );
define( 'BLACKHAWK_INTEGRATION_DIR_PATH', plugin_dir_path( __FILE__ ) );

// -------------------------------------------------------------------------
// CREDENTIALS — all sensitive values must be defined in wp-config.php.
// Non-sensitive fallbacks are provided for URL, IDs, cert path/type only.
// -------------------------------------------------------------------------
if ( ! defined( 'BLACKHAWK_INTEGRATION_API_URL' ) ) {
    define( 'BLACKHAWK_INTEGRATION_API_URL', 'https://apipp.blackhawknetwork.com/' );
}
if ( ! defined( 'BLACKHAWK_INTEGRATION_SSLCERT' ) ) {
    define( 'BLACKHAWK_INTEGRATION_SSLCERT', WP_PLUGIN_DIR . '/blackhawk-integration/SSLCERT/JC-HMP-PreProd-API-Integration.p12' );
}
if ( ! defined( 'BLACKHAWK_INTEGRATION_SSLCERTTYPE' ) ) {
    define( 'BLACKHAWK_INTEGRATION_SSLCERTTYPE', 'P12' );
}
// ── Blackhawk sends HTTP Basic Auth on the order-status webhook. WordPress core's
// Application Passwords feature intercepts Basic Auth on REST requests before our
// permission_callback runs and rejects unknown usernames with its own 401 — disable
// it so our webhook's own Basic Auth check in order-webhook.php gets a chance to run. ──
add_filter( 'wp_is_application_passwords_available', '__return_false' );

// ── Credential helpers (read from options table, encrypted by gcp-migrate-secrets.php) ──

if ( ! function_exists( 'gcp_decrypt_option' ) ) {
    function gcp_decrypt_option( $option_key ) {
        $encrypted = get_option( $option_key, '' );
        $enc_secret = defined( 'BHN_ENCRYPTION_SECRET' ) ? BHN_ENCRYPTION_SECRET : ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '' );
        if ( $encrypted === '' || $enc_secret === '' ) {
            return '';
        }
        $key  = hash( 'sha256', $enc_secret, true );
        $data = base64_decode( $encrypted, true );
        if ( $data === false || strlen( $data ) < 16 ) return '';
        $iv  = substr( $data, 0, 16 );
        $ct  = substr( $data, 16 );
        $dec = openssl_decrypt( $ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return $dec !== false ? $dec : '';
    }
}

if ( ! function_exists( 'gcp_get_bhn_ssl_cert_password' ) ) {
    function gcp_get_bhn_ssl_cert_password() {
        return gcp_decrypt_option( 'gcp_bhn_ssl_cert_password' );
    }
}

if ( ! function_exists( 'gcp_get_bhn_webhook_api_key' ) ) {
    function gcp_get_bhn_webhook_api_key() {
        return gcp_decrypt_option( 'gcp_bhn_webhook_api_key' );
    }
}

if ( ! function_exists( 'gcp_get_bhn_webhook_basic_auth_user' ) ) {
    function gcp_get_bhn_webhook_basic_auth_user() {
        return gcp_decrypt_option( 'gcp_bhn_webhook_basic_auth_user' );
    }
}

if ( ! function_exists( 'gcp_get_bhn_webhook_basic_auth_pass' ) ) {
    function gcp_get_bhn_webhook_basic_auth_pass() {
        return gcp_decrypt_option( 'gcp_bhn_webhook_basic_auth_pass' );
    }
}

if ( ! function_exists( 'gcp_get_bhn_client_program_id' ) ) {
    function gcp_get_bhn_client_program_id() {
        return gcp_decrypt_option( 'gcp_bhn_client_program_id' );
    }
}

if ( ! function_exists( 'gcp_get_bhn_merchant_id' ) ) {
    function gcp_get_bhn_merchant_id() {
        return gcp_decrypt_option( 'gcp_bhn_merchant_id' );
    }
}

if ( ! function_exists( 'gcp_get_gift_card_secret_key' ) ) {
    function gcp_get_gift_card_secret_key() {
        return gcp_decrypt_option( 'gcp_gift_card_secret_key' );
    }
}

if ( ! function_exists( 'gcp_get_gift_card_encryption_key' ) ) {
    function gcp_get_gift_card_encryption_key() {
        return gcp_decrypt_option( 'gcp_gift_card_encryption_key' );
    }
}

// -------------------------------------------------------------------------
// Activation / Deactivation
// -------------------------------------------------------------------------
register_activation_hook( __FILE__, 'blackhawk_integration_activation' );
function blackhawk_integration_activation() {
    wp_clear_scheduled_hook( 'scheduled_blackhawk_integration_catalogue' );
    // Schedule daily at 02:00 AM UTC (off-peak for BHN API).
    $first_run = strtotime( 'tomorrow 02:00:00 UTC' );
    wp_schedule_event( $first_run, 'daily', 'scheduled_blackhawk_integration_catalogue' );

    // Create the cron log DB table on activation.
    bhn_create_cron_log_table();
}

register_deactivation_hook( __FILE__, 'blackhawk_integration_deactivation' );
function blackhawk_integration_deactivation() {
    wp_clear_scheduled_hook( 'scheduled_blackhawk_integration_catalogue' );
}

// -------------------------------------------------------------------------
// Create cron log table (also called on init as safety net).
// -------------------------------------------------------------------------
function bhn_create_cron_log_table() {
    global $wpdb;
    $table    = $wpdb->prefix . 'bhn_cron_log';
    $charset  = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        run_type      VARCHAR(20)  NOT NULL DEFAULT 'cron',
        triggered_at  DATETIME     NOT NULL,
        finished_at   DATETIME     DEFAULT NULL,
        status        VARCHAR(20)  NOT NULL DEFAULT 'started',
        products_created INT(11)   NOT NULL DEFAULT 0,
        products_updated INT(11)   NOT NULL DEFAULT 0,
        products_failed  INT(11)   NOT NULL DEFAULT 0,
        next_run_at   DATETIME     DEFAULT NULL,
        notes         TEXT         DEFAULT NULL,
        PRIMARY KEY (id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

add_action( 'init', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'bhn_cron_log';
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
        bhn_create_cron_log_table();
    }
});

// -------------------------------------------------------------------------
// Cron scheduling
// -------------------------------------------------------------------------
add_action( 'init', 'blackhawk_integration_schedule' );
function blackhawk_integration_schedule() {
    if ( ! wp_next_scheduled( 'scheduled_blackhawk_integration_catalogue' ) ) {
        $first_run = strtotime( 'tomorrow 02:00:00 UTC' );
        wp_schedule_event( $first_run, 'daily', 'scheduled_blackhawk_integration_catalogue' );
    }
}

// -------------------------------------------------------------------------
// Cron hook
// -------------------------------------------------------------------------
add_action( 'scheduled_blackhawk_integration_catalogue', 'blackhawk_integration_catalogue_cron' );
function blackhawk_integration_catalogue_cron() {
    blackhawk_run_catalog_sync( 'cron' );
}

// -------------------------------------------------------------------------
// Admin menu page — shows cron log table
// -------------------------------------------------------------------------
add_action( 'admin_menu', 'bhn_register_admin_menu' );
function bhn_register_admin_menu() {
    add_menu_page(
        'BlackHawk Integration',
        'BlackHawk',
        'manage_options',
        'bhn-integration',
        'bhn_admin_page',
        'dashicons-tickets-alt',
        56
    );
}

function bhn_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'bhn_cron_log';

    // Next scheduled run.
    $next_cron     = wp_next_scheduled( 'scheduled_blackhawk_integration_catalogue' );
    $next_cron_str = $next_cron ? get_date_from_gmt( date( 'Y-m-d H:i:s', $next_cron ), 'Y-m-d H:i:s' ) . ' (site time)' : '<strong style="color:red;">NOT SCHEDULED</strong>';

    $per_page = 50;
    $paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    $offset   = ( $paged - 1 ) * $per_page;
    $rows     = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ) );

    echo '<div class="wrap">';
    echo '<h1>BlackHawk Integration — Cron Log</h1>';

    echo '<table class="widefat" style="margin-bottom:20px;width:auto;">';
    echo '<tr><th style="padding:8px 16px;">Next scheduled run</th><td style="padding:8px 16px;">' . $next_cron_str . '</td></tr>';
    $last_api = get_option( 'blackhawk_integration_last_api_call', [] );
    if ( $last_api ) {
        echo '<tr><th style="padding:8px 16px;">Last API call summary</th><td style="padding:8px 16px;"><pre style="margin:0;">' . esc_html( print_r( $last_api, true ) ) . '</pre></td></tr>';
    }
    echo '</table>';

    if ( empty( $rows ) ) {
        echo '<p>No cron runs recorded yet. The table will populate after the first sync.</p>';
    } else {
        echo '<table class="widefat striped">';
        echo '<thead><tr>
            <th>#</th>
            <th>Type</th>
            <th>Triggered at</th>
            <th>Finished at</th>
            <th>Status</th>
            <th>Created</th>
            <th>Updated</th>
            <th>Failed</th>
            <th>Next run scheduled</th>
            <th>Notes</th>
        </tr></thead><tbody>';

        foreach ( $rows as $row ) {
            $status_color = $row->status === 'success' ? 'green' : ( $row->status === 'failed' ? 'red' : 'orange' );
            echo '<tr>';
            echo '<td>' . intval( $row->id ) . '</td>';
            echo '<td>' . esc_html( $row->run_type ) . '</td>';
            echo '<td>' . esc_html( $row->triggered_at ) . '</td>';
            echo '<td>' . esc_html( $row->finished_at ?? '—' ) . '</td>';
            echo '<td><strong style="color:' . $status_color . ';">' . esc_html( strtoupper( $row->status ) ) . '</strong></td>';
            echo '<td>' . intval( $row->products_created ) . '</td>';
            echo '<td>' . intval( $row->products_updated ) . '</td>';
            echo '<td>' . intval( $row->products_failed ) . '</td>';
            echo '<td>' . esc_html( $row->next_run_at ?? '—' ) . '</td>';
            echo '<td>' . esc_html( $row->notes ?? '' ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';
}

// -------------------------------------------------------------------------
// Include sub-modules
// -------------------------------------------------------------------------
include( BLACKHAWK_INTEGRATION_DIR_PATH . 'includes/catalog.php' );
include( BLACKHAWK_INTEGRATION_DIR_PATH . 'includes/order.php' );
include( BLACKHAWK_INTEGRATION_DIR_PATH . 'includes/order-webhook.php' );

// -------------------------------------------------------------------------
// Helper: prepare API order payload
// -------------------------------------------------------------------------
function bhi_prepare_api_data( $order ) {
    $client_program_number = gcp_get_bhn_client_program_id();
    $items = [];

    foreach ( $order->get_items() as $item ) {
        $product       = $item->get_product();
        $sku           = $product->get_sku() ? $product->get_sku() : 'Fallback_Sku_' . $product->get_id();
        $custom_amount = $item->get_meta( 'Gift Card Amount', true );
        $amount        = $custom_amount ? floatval( $custom_amount ) : '';
        $recipients    = $item->get_meta( 'Recipients' );

        if ( ! is_array( $recipients ) || empty( $recipients ) ) {
            $recipients = [[
                'recipient_id'         => uniqid( 'REC_' ),
                'recipient_first_name' => 'Default',
                'recipient_last_name'  => 'Recipient',
                'email'                => 'default@example.com',
                'address_line1'        => 'Default Line 1',
                'address_line2'        => 'Default Line 2',
                'city'                 => 'Default City',
                'postal_code'          => '0000',
                'country'              => 'Default Country',
            ]];
        }

        $item_entry = [
            'clientRefId'     => uniqid( 'CRI_' ),
            'quantity'        => $item->get_quantity(),
            'amount'          => $amount,
            'contentProvider' => $sku,
            'recipients'      => [],
        ];

        foreach ( $recipients as $recipient ) {
            $item_entry['recipients'][] = [
                'id'        => sanitize_text_field( $recipient['recipient_id'] ),
                'firstName' => sanitize_text_field( $recipient['recipient_first_name'] ),
                'lastName'  => sanitize_text_field( $recipient['recipient_last_name'] ),
                'email'     => sanitize_email( $recipient['email'] ),
                'address'   => [
                    'line1'      => sanitize_text_field( $recipient['address_line1'] ),
                    'line2'      => sanitize_text_field( $recipient['address_line2'] ),
                    'city'       => sanitize_text_field( $recipient['city'] ),
                    'postalCode' => sanitize_text_field( $recipient['postal_code'] ),
                    'country'    => sanitize_text_field( $recipient['country'] ),
                ],
            ];
        }

        $items[] = $item_entry;
    }

    return [
        'clientProgramNumber' => $client_program_number,
        'paymentType'         => 'DRAW_DOWN',
        'millisecondsToWait'  => 15000,
        'orderDetails'        => $items,
    ];
}
