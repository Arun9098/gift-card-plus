<?php
/**
 * Functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

// This theme requires WordPress 5.3 or later.
if (version_compare($GLOBALS['wp_version'], '5.3', '<')) {
    require get_template_directory() . '/inc/back-compat.php';
}

// ---------------------------------------------------------------------------
// AJAX Security Helpers
// ---------------------------------------------------------------------------

/**
 * Abort AJAX request unless the current user has manage_options.
 */
function gcp_require_admin_ajax(): void {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized: admin access required.' ], 403 );
        wp_die();
    }
}

/**
 * Abort AJAX request unless the user is logged in.
 */
function gcp_require_logged_in_ajax(): void {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Unauthorized: login required.' ], 403 );
        wp_die();
    }
}

/**
 * Centralized AJAX security gate.
 *
 * Fires early on every admin-ajax.php request and blocks actions that require
 * authentication before the actual callback has a chance to run. This is a
 * defence-in-depth layer on top of the per-handler checks below.
 */
add_action( 'init', 'gcp_ajax_security_gate', 1 );
function gcp_ajax_security_gate(): void {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
        return;
    }

    $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
    if ( $action === '' ) {
        return;
    }

    // Actions that must only be called by a logged-in administrator.
    static $admin_only = [
        'create_new_user_with_all_details',
        'admin_reset_user_password',
        'send_user_password_reset_link',
        'get_user_profile_details',
        'save_user_profile_details',
        'fetch_users',
        'export_users',
        'fetch_all_filtered_users',
        'get_user_order_history',
        'get_user_track_cards',
        'export_track_cards',
        'get_user_balance',
        'get_business_profile_details',
        'save_business_profile_details',
        'get_recipient_users',
        'transfer_business_admin',
        'remove_recipient_from_business_user',
        'assign_recipient_to_business',
        'remove_recipient_from_business',
        'create_new_brand',
        'fetch_brand_details',
        'save_brand_changes',
        'fetch_brand_products',
        'get_products_for_brand_popup',
        'assign_products_to_brand',
        'export_brands_products',
        'et_get_email_templates',
        'et_save_email_template',
        'et_get_single_email_template',
        'et_send_test_email',
        'get_email_content',
        'search_product_by_name',
        'download_sku_list',
        'search_product_tags',
        'create_product',
        'create_product_new',
        'upload_csv_file_bulk',
        'upload_product_gallery_images',
        'upload_product_image_from_url',
        'upload_product_image_from_url_',
        'search_orders',
        'load_orders',
        'search_users',
        'search_product_by_sku',
        'download_specific_sku_list',
        'send_test_email',
        'send_test_text',
        'export_category_products',
        'create_new_product_category',
        'export_products_csv',
        'export_categories_csv',
        'custom_upload_csv_file',
        'custom_process_bulk_order_data',
        'get_product_images',
        'get_product_meta',
        'get_business_user_balance',
        'place_cod_order',
        'validate_product_details_bulk',
        'get_recipient_details_by_emails',
        'search_product_categories',
        'process_bulk_order_data',
        // custom-functions.php admin handlers
        'create_giftcard_products',
        'custom_upload_category_csv',
        'create_bulk_categories',
        'load_thumbnail_view',
        'load_thumbnail_view_review',
        'load_thumbnail_view_brand',
        'get_product_meta_for_form',
        'get_user_transactions',
        'get_all_brands',
        'get_all_pro_status',
        'get_all_businesses',
        'pdb_add_contact',
        'pdb_delete_contacts',
        'download_invoice',
        'create_event_from_contact',
        'delete_event_from_contact',
        'search_user_emails',
        'add_new_business_user',
        'get_contact_list_users_by_business',
        // Finding 3.6 — moved from $logged_in_required; only admins should write to business user records.
        'add_sender_to_business_user',
        'add_campaign_to_business_user',
    ];

    // Actions that require a logged-in user but not necessarily admin.
    static $logged_in_required = [
        'check_user_billing_approval',
        'check_user_type',
        'check_user_role',
        'save_user_preferences',
        'custom_save_preferences',
        'save_draft_order_with_recipients',
        'save_draft_order_customisation',
        'gc_toggle_wishlist',
        'save_recipients_to_user_acf',
        // Finding 3.6 — add_sender_to_business_user and add_campaign_to_business_user
        // moved to $admin_only above; non-admin users were able to write to business user records.
        'get_user_campaigns',
        'check_approved_billing',
    ];

    if ( in_array( $action, $admin_only, true ) ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
            exit;
        }
    } elseif ( in_array( $action, $logged_in_required, true ) ) {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
            exit;
        }
    }
}
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Finding 3.10 — Rate limiting helper
// Call at the top of any public endpoint. Blocks excessive requests per IP.
// $action : unique key for the endpoint  (e.g. 'fp_request_reset')
// $max    : max allowed requests
// $window : sliding window in seconds
// ---------------------------------------------------------------------------
function gcp_check_rate_limit( string $action, int $max, int $window ): void {
    $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
    $key = 'gcp_rl_' . $action . '_' . md5( $ip );

    $count = (int) get_transient( $key );
    if ( $count >= $max ) {
        wp_send_json_error( [ 'message' => 'Too many attempts. Please wait 15 minutes before trying again.' ], 429 );
        wp_die();
    }

    if ( $count === 0 ) {
        set_transient( $key, 1, $window );
    } else {
        set_transient( $key, $count + 1, $window );
    }
}

// ---------------------------------------------------------------------------
// Google reCAPTCHA v2 (Checkbox — "I'm not a robot")
// ---------------------------------------------------------------------------
define( 'GCP_RECAPTCHA_SITE_KEY',   '6LftSGktAAAAAKmTPyS03a1Pu3-z02iHrfmNa477' );
define( 'GCP_RECAPTCHA_SECRET_KEY', '6LftSGktAAAAADy2Mwk4Hhp8oRnvMhE92a3TbOH-' );

// Enqueue reCAPTCHA script in <head> so it is available before any inline template scripts run
add_action( 'wp_head', function () {
    if ( is_admin() ) return;
    echo '<script src="https://www.google.com/recaptcha/api.js"></script>';
} );

/**
 * Verify a reCAPTCHA v2 checkbox response server-side.
 * Returns true only if Google confirms the checkbox response is valid.
 */
function gcp_verify_recaptcha( string $token, string $action = '' ): bool {
    if ( empty( $token ) ) return false;

    $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => GCP_RECAPTCHA_SECRET_KEY,
            'response' => sanitize_text_field( $token ),
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ],
        'timeout' => 10,
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'reCAPTCHA wp_remote_post error: ' . $response->get_error_message() );
        return false;
    }

    $result = json_decode( wp_remote_retrieve_body( $response ), true );
    error_log( 'reCAPTCHA result: ' . print_r( $result, true ) );

    return ! empty( $result['success'] );
}

// ---------------------------------------------------------------------------
// Block unnecessary HTTP methods (DELETE, PUT, PATCH, TRACE, CONNECT)
// Allow these only on REST API endpoints where they are legitimately used.
// ---------------------------------------------------------------------------
add_action( 'init', function () {
    $method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' );
    $blocked = [ 'DELETE', 'PUT', 'PATCH', 'TRACE', 'CONNECT' ];

    if ( ! in_array( $method, $blocked, true ) ) {
        return;
    }

    // REST API legitimately uses DELETE/PUT/PATCH — leave those alone.
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $request_uri, '/wp-json/' ) !== false ) {
        return;
    }

    status_header( 405 );
    header( 'Allow: GET, POST, HEAD, OPTIONS' );
    exit( 'Method Not Allowed' );
}, 1 );

// ---------------------------------------------------------------------------
// Security Headers (Finding 3.11)
// ---------------------------------------------------------------------------
// CSP nonce — one random value per request, reused across header + script tags
// ---------------------------------------------------------------------------
function gcp_csp_nonce() {
    static $nonce = null;
    if ( null === $nonce ) {
        $nonce = base64_encode( random_bytes( 16 ) );
    }
    return $nonce;
}

// Add nonce attribute to every <script> tag enqueued via wp_enqueue_script()
add_filter( 'wp_script_attributes', function( $attributes ) {
    $attributes['nonce'] = gcp_csp_nonce();
    return $attributes;
} );

// Add nonce to inline <script> blocks output via wp_add_inline_script() (WP 6.3+)
add_filter( 'wp_inline_script_attributes', function( $attributes ) {
    $attributes['nonce'] = gcp_csp_nonce();
    return $attributes;
} );

// Output buffer: add nonce to ALL hardcoded inline <script> tags in template output
add_action( 'template_redirect', function () {
    ob_start( function ( $buffer ) {
        $nonce = esc_attr( gcp_csp_nonce() );
        // Only targets inline scripts (no src=) that don't already have a nonce
        return preg_replace(
            '/<script(?![^>]*\bnonce=)(?![^>]*\bsrc=)([^>]*)>/i',
            '<script nonce="' . $nonce . '"$1>',
            $buffer
        );
    } );
}, 1 );

// ---------------------------------------------------------------------------
add_action( 'send_headers', function () {
    if ( headers_sent() ) {
        return;
    }
    header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'X-XSS-Protection: 1; mode=block' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
    header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . gcp_csp_nonce() . "' *.stripe.com static.cloudflareinsights.com www.google.com www.gstatic.com *.googletagmanager.com *.google-analytics.com j.6sc.co cdn.jsdelivr.net cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' fonts.googleapis.com *.googletagmanager.com code.jquery.com cdnjs.cloudflare.com; font-src 'self' data: fonts.gstatic.com cdnjs.cloudflare.com; img-src 'self' data: blob: https:; connect-src 'self' *.stripe.com *.google-analytics.com *.googletagmanager.com *.google.com static.cloudflareinsights.com j.6sc.co wss:; frame-src 'self' www.google.com *.stripe.com player.vimeo.com forms.monday.com; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self' *.stripe.com;" );
} );
// ---------------------------------------------------------------------------
// Cookie SameSite hardening + PHPSESSID security flags
// ---------------------------------------------------------------------------
add_action( 'init', function () {
    if ( ! headers_sent() ) {
        ini_set( 'session.cookie_httponly', '1' );
        ini_set( 'session.cookie_secure', '1' );
        ini_set( 'session.cookie_samesite', 'Lax' );
    }
}, 1 );

add_action( 'send_headers', function () {
    if ( headers_sent() ) return;
    foreach ( headers_list() as $header ) {
        if ( stripos( $header, 'Set-Cookie:' ) === 0 && stripos( $header, 'SameSite' ) === false ) {
            $new = rtrim( $header ) . '; SameSite=Lax';
            header( $new, false );
        }
    }
} );
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Finding 3.4 — Block /wp/v2/users endpoint for non-admins
// Prevents username, email, and role enumeration via the REST API.
// ---------------------------------------------------------------------------
add_filter( 'rest_endpoints', function( $endpoints ) {
    if ( current_user_can( 'manage_options' ) ) {
        return $endpoints;
    }
    unset( $endpoints['/wp/v2/users'] );
    unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    return $endpoints;
} );
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Finding 3.10 — Rate limit native WordPress lostpassword endpoint
// Runs at priority 5, before the PT-3.12 redirect at priority 10, so a
// rate-limited request is hard-stopped before it can reach wp_safe_redirect.
// Matches the 5-per-15-min limit used by the custom fp_request_reset AJAX handler.
// ---------------------------------------------------------------------------
add_action( 'lostpassword_post', function( $errors, $user_data ) {
    $ip    = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
    $key   = 'gcp_rl_wp_lostpassword_' . md5( $ip );
    $count = (int) get_transient( $key );

    if ( $count >= 5 ) {
        wp_die(
            esc_html__( 'Too many password reset requests. Please try again in 15 minutes.', 'woocommerce' ),
            esc_html__( 'Too many requests', 'woocommerce' ),
            [ 'response' => 429 ]
        );
    }

    if ( $count === 0 ) {
        set_transient( $key, 1, 15 * MINUTE_IN_SECONDS );
    } else {
        set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
    }
}, 5, 2 );
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Finding 3.12 — Username Enumeration
// ---------------------------------------------------------------------------
// 1. Generic login error — prevents confirming whether a username exists.
add_filter( 'login_errors', function() {
    return 'The credentials you entered are incorrect. Please try again.';
} );

// 2. Block ?author=N redirect — redirecting to /author/username/ leaks usernames.
add_action( 'template_redirect', function() {
    if ( isset( $_GET['author'] ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Author pages are disabled.', '', [ 'response' => 403 ] );
    }
} );

// 3. Normalize lostpassword — redirect to confirmation page even for unknown emails,
//    covering both /wp-login.php?action=lostpassword and /my-account/lost-password/.
add_action( 'lostpassword_post', function( $errors, $user_data ) {
    if ( false === $user_data ) {
        wp_safe_redirect( site_url( 'wp-login.php?checkemail=confirm' ) );
        exit;
    }
}, 10, 2 );
// ---------------------------------------------------------------------------



if (!function_exists('twenty_twenty_one_setup')) {
    /**
     * Sets up theme defaults and registers support for various WordPress features.
     *
     * Note that this function is hooked into the after_setup_theme hook, which
     * runs before the init hook. The init hook is too late for some features, such
     * as indicating support for post thumbnails.
     *
     * @since Twenty Twenty-One 1.0
     *
     * @return void
     */
    function twenty_twenty_one_setup()
    {

        // Add default posts and comments RSS feed links to head.
        add_theme_support('automatic-feed-links');

        /*
         * Let WordPress manage the document title.
         * This theme does not use a hard-coded <title> tag in the document head,
         * WordPress will provide it for us.
         */
        add_theme_support('title-tag');

        /**
         * Add post-formats support.
         */
        add_theme_support(
            'post-formats',
            array(
                'link',
                'aside',
                'gallery',
                'image',
                'quote',
                'status',
                'video',
                'audio',
                'chat',
            )
        );

        /*
         * Enable support for Post Thumbnails on posts and pages.
         *
         * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
         */
        add_theme_support('post-thumbnails');
        set_post_thumbnail_size(1568, 9999);

        register_nav_menus(
            array(
                'primary' => esc_html__('Primary menu', 'twentytwentyone'),
                'footer' => esc_html__('Secondary menu', 'twentytwentyone'),
            )
        );

        /*
         * Switch default core markup for search form, comment form, and comments
         * to output valid HTML5.
         */
        add_theme_support(
            'html5',
            array(
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
                'style',
                'script',
                'navigation-widgets',
            )
        );

        /*
         * Add support for core custom logo.
         *
         * @link https://codex.wordpress.org/Theme_Logo
         */
        $logo_width = 300;
        $logo_height = 100;

        add_theme_support(
            'custom-logo',
            array(
                'height' => $logo_height,
                'width' => $logo_width,
                'flex-width' => true,
                'flex-height' => true,
                'unlink-homepage-logo' => true,
            )
        );

        // Add theme support for selective refresh for widgets.
        add_theme_support('customize-selective-refresh-widgets');

        // Add support for Block Styles.
        add_theme_support('wp-block-styles');

        // Add support for full and wide align images.
        add_theme_support('align-wide');

        // Add support for editor styles.
        add_theme_support('editor-styles');
        $background_color = get_theme_mod('background_color', 'D1E4DD');
        if (127 > Twenty_Twenty_One_Custom_Colors::get_relative_luminance_from_hex($background_color)) {
            add_theme_support('dark-editor-style');
        }

        $editor_stylesheet_path = './assets/css/style-editor.css';

        // Note, the is_IE global variable is defined by WordPress and is used
        // to detect if the current browser is internet explorer.
        global $is_IE;
        if ($is_IE) {
            $editor_stylesheet_path = './assets/css/ie-editor.css';
        }

        // Enqueue editor styles.
        add_editor_style($editor_stylesheet_path);

        // Add custom editor font sizes.
        add_theme_support(
            'editor-font-sizes',
            array(
                array(
                    'name' => esc_html__('Extra small', 'twentytwentyone'),
                    'shortName' => esc_html_x('XS', 'Font size', 'twentytwentyone'),
                    'size' => 16,
                    'slug' => 'extra-small',
                ),
                array(
                    'name' => esc_html__('Small', 'twentytwentyone'),
                    'shortName' => esc_html_x('S', 'Font size', 'twentytwentyone'),
                    'size' => 18,
                    'slug' => 'small',
                ),
                array(
                    'name' => esc_html__('Normal', 'twentytwentyone'),
                    'shortName' => esc_html_x('M', 'Font size', 'twentytwentyone'),
                    'size' => 20,
                    'slug' => 'normal',
                ),
                array(
                    'name' => esc_html__('Large', 'twentytwentyone'),
                    'shortName' => esc_html_x('L', 'Font size', 'twentytwentyone'),
                    'size' => 24,
                    'slug' => 'large',
                ),
                array(
                    'name' => esc_html__('Extra large', 'twentytwentyone'),
                    'shortName' => esc_html_x('XL', 'Font size', 'twentytwentyone'),
                    'size' => 40,
                    'slug' => 'extra-large',
                ),
                array(
                    'name' => esc_html__('Huge', 'twentytwentyone'),
                    'shortName' => esc_html_x('XXL', 'Font size', 'twentytwentyone'),
                    'size' => 96,
                    'slug' => 'huge',
                ),
                array(
                    'name' => esc_html__('Gigantic', 'twentytwentyone'),
                    'shortName' => esc_html_x('XXXL', 'Font size', 'twentytwentyone'),
                    'size' => 144,
                    'slug' => 'gigantic',
                ),
            )
        );

        // Custom background color.
        add_theme_support(
            'custom-background',
            array(
                'default-color' => 'd1e4dd',
            )
        );

        // Editor color palette.
        $black = '#000000';
        $dark_gray = '#28303D';
        $gray = '#39414D';
        $green = '#D1E4DD';
        $blue = '#D1DFE4';
        $purple = '#D1D1E4';
        $red = '#E4D1D1';
        $orange = '#E4DAD1';
        $yellow = '#EEEADD';
        $white = '#FFFFFF';

        add_theme_support(
            'editor-color-palette',
            array(
                array(
                    'name' => esc_html__('Black', 'twentytwentyone'),
                    'slug' => 'black',
                    'color' => $black,
                ),
                array(
                    'name' => esc_html__('Dark gray', 'twentytwentyone'),
                    'slug' => 'dark-gray',
                    'color' => $dark_gray,
                ),
                array(
                    'name' => esc_html__('Gray', 'twentytwentyone'),
                    'slug' => 'gray',
                    'color' => $gray,
                ),
                array(
                    'name' => esc_html__('Green', 'twentytwentyone'),
                    'slug' => 'green',
                    'color' => $green,
                ),
                array(
                    'name' => esc_html__('Blue', 'twentytwentyone'),
                    'slug' => 'blue',
                    'color' => $blue,
                ),
                array(
                    'name' => esc_html__('Purple', 'twentytwentyone'),
                    'slug' => 'purple',
                    'color' => $purple,
                ),
                array(
                    'name' => esc_html__('Red', 'twentytwentyone'),
                    'slug' => 'red',
                    'color' => $red,
                ),
                array(
                    'name' => esc_html__('Orange', 'twentytwentyone'),
                    'slug' => 'orange',
                    'color' => $orange,
                ),
                array(
                    'name' => esc_html__('Yellow', 'twentytwentyone'),
                    'slug' => 'yellow',
                    'color' => $yellow,
                ),
                array(
                    'name' => esc_html__('White', 'twentytwentyone'),
                    'slug' => 'white',
                    'color' => $white,
                ),
            )
        );

        add_theme_support(
            'editor-gradient-presets',
            array(
                array(
                    'name' => esc_html__('Purple to yellow', 'twentytwentyone'),
                    'gradient' => 'linear-gradient(160deg, ' . $purple . ' 0%, ' . $yellow . ' 100%)',
                    'slug' => 'purple-to-yellow',
                ),
                array(
                    'name' => esc_html__('Yellow to purple', 'twentytwentyone'),
                    'gradient' => 'linear-gradient(160deg, ' . $yellow . ' 0%, ' . $purple . ' 100%)',
                    'slug' => 'yellow-to-purple',
                ),
                array(
                    'name' => esc_html__('Green to yellow', 'twentytwentyone'),
                    'gradient' => 'linear-gradient(160deg, ' . $green . ' 0%, ' . $yellow . ' 100%)',
                    'slug' => 'green-to-yellow',
                ),
                array(
                    'name' => esc_html__('Yellow to green', 'twentytwentyone'),
                    'gradient' => 'linear-gradient(160deg, ' . $yellow . ' 0%, ' . $green . ' 100%)',
                    'slug' => 'yellow-to-green',
                ),
                array(
                    'name' => esc_html__('Red to yellow', 'twentytwentyone'),
                    'gradient' => 'linear-gradient(160deg, ' . $red . ' 0%, ' . $yellow . ' 100%)',
                    'slug' => 'red-to-yellow',
                ),
                array(
                    'name' => esc_html__('Yellow to red', 'twentytwentyone'),
                    'gradient' => 'linear-gradient(160deg, ' . $yellow . ' 0%, ' . $red . ' 100%)',
                    'slug' => 'yellow-to-red',
                ),
                array(
                    'name' => esc_html__('Purple to red', 'twentytwentyone'),
                    'gradient' => 'linear-gradient(160deg, ' . $purple . ' 0%, ' . $red . ' 100%)',
                    'slug' => 'purple-to-red',
                ),
                array(
                    'name' => esc_html__('Red to purple', 'twentytwentyone'),
                    'gradient' => 'linear-gradient(160deg, ' . $red . ' 0%, ' . $purple . ' 100%)',
                    'slug' => 'red-to-purple',
                ),
            )
        );

        /*
         * Adds starter content to highlight the theme on fresh sites.
         * This is done conditionally to avoid loading the starter content on every
         * page load, as it is a one-off operation only needed once in the customizer.
         */
        if (is_customize_preview()) {
            require get_template_directory() . '/inc/starter-content.php';
            add_theme_support('starter-content', twenty_twenty_one_get_starter_content());
        }

        // Add support for responsive embedded content.
        add_theme_support('responsive-embeds');

        // Add support for custom line height controls.
        add_theme_support('custom-line-height');

        // Add support for link color control.
        add_theme_support('link-color');

        // Add support for experimental cover block spacing.
        add_theme_support('custom-spacing');

        // Add support for custom units.
        // This was removed in WordPress 5.6 but is still required to properly support WP 5.5.
        add_theme_support('custom-units');

        // Remove feed icon link from legacy RSS widget.
        add_filter('rss_widget_feed_link', '__return_empty_string');
    }
}
add_action('after_setup_theme', 'twenty_twenty_one_setup');

/**
 * Registers widget area.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 *
 * @return void
 */
function twenty_twenty_one_widgets_init()
{

    register_sidebar(
        array(
            'name' => esc_html__('Footer', 'twentytwentyone'),
            'id' => 'sidebar-1',
            'description' => esc_html__('Add widgets here to appear in your footer.', 'twentytwentyone'),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget' => '</section>',
            'before_title' => '<h2 class="widget-title">',
            'after_title' => '</h2>',
        )
    );
}
add_action('widgets_init', 'twenty_twenty_one_widgets_init');

/**
 * Sets the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @global int $content_width Content width.
 *
 * @return void
 */
function twenty_twenty_one_content_width()
{
    // This variable is intended to be overruled from themes.
    // Open WPCS issue: {@link https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards/issues/1043}.
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $GLOBALS['content_width'] = apply_filters('twenty_twenty_one_content_width', 750);
}
add_action('after_setup_theme', 'twenty_twenty_one_content_width', 0);

/**
 * Enqueues scripts and styles.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @global bool       $is_IE
 * @global WP_Scripts $wp_scripts
 *
 * @return void
 */
function twenty_twenty_one_scripts()
{
    // Note, the is_IE global variable is defined by WordPress and is used
    // to detect if the current browser is internet explorer.
    global $is_IE, $wp_scripts;
    if ($is_IE) {
        // If IE 11 or below, use a flattened stylesheet with static values replacing CSS Variables.
        wp_enqueue_style('twenty-twenty-one-style', get_template_directory_uri() . '/assets/css/ie.css', array(), wp_get_theme()->get('Version'));
    } else {
        // If not IE, use the standard stylesheet.
        wp_enqueue_style('twenty-twenty-one-style', get_template_directory_uri() . '/style.css', array(), wp_get_theme()->get('Version'));
    }

    // RTL styles.
    wp_style_add_data('twenty-twenty-one-style', 'rtl', 'replace');

    // Print styles.
    wp_enqueue_style('twenty-twenty-one-print-style', get_template_directory_uri() . '/assets/css/print.css', array(), wp_get_theme()->get('Version'), 'print');



    // Threaded comment reply styles.
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }

    // Register the IE11 polyfill file.
    wp_register_script(
        'twenty-twenty-one-ie11-polyfills-asset',
        get_template_directory_uri() . '/assets/js/polyfills.js',
        array(),
        wp_get_theme()->get('Version'),
        array('in_footer' => true)
    );

    // Register the IE11 polyfill loader.
    wp_register_script(
        'twenty-twenty-one-ie11-polyfills',
        null,
        array(),
        wp_get_theme()->get('Version'),
        array('in_footer' => true)
    );
    wp_add_inline_script(
        'twenty-twenty-one-ie11-polyfills',
        wp_get_script_polyfill(
            $wp_scripts,
            array(
                'Element.prototype.matches && Element.prototype.closest && window.NodeList && NodeList.prototype.forEach' => 'twenty-twenty-one-ie11-polyfills-asset',
            )
        )
    );
    wp_enqueue_style(
        'main-css',
        get_template_directory_uri() . '/assets/css/main.css',
        array(),
        time() // Cache busting
    );
    // Main navigation scripts.
    if (has_nav_menu('primary')) {
        wp_enqueue_script(
            'twenty-twenty-one-primary-navigation-script',
            get_template_directory_uri() . '/assets/js/primary-navigation.js',
            array('twenty-twenty-one-ie11-polyfills'),
            wp_get_theme()->get('Version'),
            array(
                'in_footer' => false, // Because involves header.
                'strategy' => 'defer',
            )
        );
    }


    if ( function_exists('is_order_received_page') && is_order_received_page() ) {
        // Thank you page css
        wp_enqueue_style(
            'thankyou-page-css',
            get_template_directory_uri() . '/assets/css/thankyou-page.css',
            array(),
            time()
        );
    }
    // Responsive embeds script.
    wp_enqueue_script(
        'twenty-twenty-one-responsive-embeds-script',
        get_template_directory_uri() . '/assets/js/responsive-embeds.js',
        array('twenty-twenty-one-ie11-polyfills'),
        wp_get_theme()->get('Version'),
        array('in_footer' => true)
    );

    wp_enqueue_script(
        'custom-header-js',
        get_template_directory_uri() . '/assets/js/custom-header.js',
        array(),
        time(),
        array('in_footer' => true)
    );

    // Accordion toggle script - load after jQuery and potentially Visual Composer scripts
    $accordion_deps = array('jquery');
    // Try to add Visual Composer accordion script as dependency if it exists
    if (wp_script_is('vc_accordion_script', 'registered')) {
        $accordion_deps[] = 'vc_accordion_script';
    }
    wp_enqueue_script(
        'accordion-toggle-js',
        get_template_directory_uri() . '/assets/js/accordion-toggle.js',
        $accordion_deps,
        time(),
        array('in_footer' => true)
    );

    //Landing Page enqueue code 
    if (is_page_template('landing-page.php')) {

        //landing page js
        wp_enqueue_script(
            'landing-js',
            get_template_directory_uri() . '/assets/js/landing.js',
            array(),
            time(),
            array('in_footer' => true)
        );

        //landing page css
        wp_enqueue_style(
            'landing-css',
            get_template_directory_uri() . '/assets/css/landing.css',
            array(),
            time() // Cache busting
        );


    }

    $current_user = wp_get_current_user();
    $roles = (array) $current_user->roles;

    // if ( in_array( 'customer', $roles ) ) {
    // echo "User is a Customer";
    wp_enqueue_style('user-frontend-css', get_template_directory_uri() . '/assets/css/user-frontend.css', array(), time());
    // }


    if (is_shop()) {
        wp_enqueue_style(
            'shop-page-css',
            get_template_directory_uri() . '/assets/css/shop-page.css',
            array(),
            time() // Cache busting
        );
    }

    if (is_cart()) {
        wp_enqueue_style(
            'cart-page-css',
            get_template_directory_uri() . '/assets/css/cart-page.css',
            array(),
            time() // Cache busting
        );
    }
    if (is_page('create-product') || is_page('review-a-product')) {
        wp_enqueue_style(
            'create-product-css',
            get_template_directory_uri() . '/assets/css/create-product.css',
            array(),
            time() // Cache busting
        );


        wp_enqueue_script(
            'create-product-js',
            get_template_directory_uri() . '/assets/js/create-product.js',
            array(),
            time(),
            array('in_footer' => true)
        );

        wp_localize_script('create-product-js', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'serverSupplier' => $product_data['supplier'] ?? '',
            'nonce' => wp_create_nonce('upload_image_nonce1')
        ]);





        // $localized_data = [
        //     'isEditMode' => $edit_mode,
        //     'productId' => $product_id,
        //     'productData' => $product_data,
        // ];

        // Localizing the script to pass AJAX URL and nonce
        wp_localize_script(
            'create-product-js',
            'ajax_tags',
            // $localized_data,
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ajax_sku_nonce'),
            )
        );

    }

    if (is_page_template('page-my-orders.php')) {
        wp_enqueue_style(
            'my-orders-css',
            get_template_directory_uri() . '/assets/css/user-order.css',
            array(),
            time()
        );
    }

    if (is_wc_endpoint_url('view-order')) {
        wp_enqueue_style(
            'user-view-order-css',
            get_template_directory_uri() . '/assets/css/user-view-order.css',
            array(),
            time()
        );
    }

    if (get_page_template_slug(get_queried_object_id()) === 'brands-listing.php') {

        wp_enqueue_script('brands-js', get_template_directory_uri() . '/assets/js/brands.js', array(), time(), true);

        wp_localize_script('brands-js', 'brandsData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('brands_nonce'),
            'siteUrl' => home_url('/')
        ));
    }

    global $wp;
    if (
        is_page('brands') ||
        is_page('offers') ||
        is_product_category() ||
        is_tax( 'product_tag' ) ||
        is_tax('product_brand') ||
        get_page_template_slug(get_queried_object_id()) === 'page-brand.php' ||
        get_page_template_slug(get_queried_object_id()) === 'page-offers.php' ||
        (is_account_page() && isset($wp->query_vars['my-wishlist']))
    ) {

        wp_enqueue_style('user-brands-page-css', get_template_directory_uri() . '/assets/css/user-brands-page.css', array(), time());
        wp_enqueue_style('user-single-brands-page-css', get_template_directory_uri() . '/assets/css/user-single-brands-page.css', array(), time());
        wp_enqueue_style('user-single-category-page-css', get_template_directory_uri() . '/assets/css/user-single-category-page.css', array(), time());
        wp_enqueue_style('user-single-tag-page-css', get_template_directory_uri() . '/assets/css/user-single-tag-page.css', array(), time());

        wp_enqueue_script('user-brands-page', get_template_directory_uri() . '/assets/js/user-brands-page.js', array(), time(), true);
        wp_localize_script('user-brands-page','gcVars',array('brandsUrl' => site_url('/brands/')));

        wp_localize_script('user-brands-page', 'userBrandsData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('useer_brands_nonce')
        ));
    }

    if (is_page('offers')) {
        wp_enqueue_style('offers-page-css', get_template_directory_uri() . '/assets/css/offers-page.css', array(), time());
    }
    
    if (is_page('offers-list')) {
        wp_enqueue_style('offers-list-css', get_template_directory_uri() . '/assets/css/offers-list.css', array(), time());
    }


    wp_enqueue_script('jquery');
    wp_enqueue_media();
    wp_register_script('datatable-js', get_template_directory_uri() . '/assets/js/datatable.js', array('jquery'), time(), true);
    wp_enqueue_script('bulk-create-category-js', get_template_directory_uri() . '/assets/js/bulk-create-category.js', array('jquery'), time(), true);
    wp_enqueue_script('order-confirmation-js', get_template_directory_uri() . '/assets/js/order-confirmation.js', array('jquery', 'datatable-js'), time(), true);

    wp_localize_script('bulk-create-category-js', 'bulkCreateCategory', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bulk_create_category_nonce'),
        'siteUrl' => home_url('/'),
    ));
    wp_localize_script('bulk-create-category-js', 'categoryAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('category_nonce'),
    ));
    wp_enqueue_style(
        'bootstrap-min-css',
        get_template_directory_uri() . '/assets/css/bootstrap.min.css',
        array(),
        time() // Cache busting
    );
    wp_enqueue_script(
        'bootstrap-bundle-js',
        get_template_directory_uri() . '/assets/js/bootstrap-bundle.js',
        array(),
        time()
    );

    if (is_page('track-card')) {
        wp_enqueue_style(
            'track-card-css',
            get_template_directory_uri() . '/assets/css/track-a-card.css',
            array(),
            time() // Cache busting
        );
        wp_enqueue_script('track-card-js', get_template_directory_uri() . '/assets/js/track-a-card.js', array('jquery'), time(), true);

        wp_localize_script('track-card-js', 'trackcard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('resend_gift_card_nonce'),
        ));
    }

    if (is_page('faq')) {
        wp_enqueue_style(
            'faqs-css',
            get_template_directory_uri() . '/assets/css/faqs.css',
            array(),
            time() // Cache busting
        );
    }

    if (is_page('review-a-product')) {
        wp_enqueue_style(
            'review-a-product-css',
            get_template_directory_uri() . '/assets/css/review-product.css',
            array(),
            time() // Cache busting
        );
        wp_enqueue_script('review-product-js', get_template_directory_uri() . '/assets/js/review-product.js', array('jquery'), time(), true);

        wp_localize_script('review-product-js', 'reviewajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    
    


    if (is_page('my-orders')) {
        wp_enqueue_style('my-orders-css', get_template_directory_uri() . '/assets/css/my-orders.css', array(), time());
        wp_enqueue_script('my-orders-js', get_template_directory_uri() . '/assets/js/my-orders.js', array('jquery'), time(), true);
    }
    wp_enqueue_script('bulk-create-product-js', get_template_directory_uri() . '/assets/js/bulk-create-product.js', array('jquery', 'datatable-js'), time(), true);

    wp_localize_script('bulk-create-product-js', 'bulkCreateProduct', array(
        'ajaxurl' => admin_url('admin-ajax.php'), // WordPress AJAX URL
        'nonce' => wp_create_nonce('create_giftcard_products_nonce'), // Security nonce
        'siteUrl' => home_url('/'),
    ));

    wp_enqueue_script(
        'sortable-min-js',
        get_template_directory_uri() . '/assets/js/Sortable.min.js',
        array('jquery'),
        time(),
    );

    if (
        is_page('bulk-create-product') || is_page('create-category') || (is_page('all-products')) || (is_page('review-a-product')) || (is_page('order')) || is_wc_endpoint_url('order-received') || (is_page('users')) || (get_page_template_slug(get_queried_object_id()) === 'brands-listing.php'
            || is_page('my-orders') || is_page('offers-list') || is_page_template('page-offers-list.php'))
    ) {
        wp_enqueue_script('datatable-js', get_template_directory_uri() . '/assets/js/datatable.js', array('jquery'), time(), true);
        wp_enqueue_script('payment-and-confirmation-js', get_template_directory_uri() . '/assets/js/payment-and-confirmation.js', array('jquery'), time(), true);
        wp_localize_script('payment-and-confirmation-js', 'paymentAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('payment_nonce')
        ));

        wp_enqueue_style('datatable-css', get_template_directory_uri() . '/assets/css/datatable.css', array(), time());
    }


    // For select js
    wp_enqueue_script(
        'select-js',
        get_template_directory_uri() . '/assets/js/select2.min.js',
        array('jquery'),
        time(),
    );

    // For select css
    wp_enqueue_style(
        'select-css',
        get_template_directory_uri() . '/assets/css/select2.min.css',
        array(),
        time()
    );

    if (is_page('order') || is_page('manual-order')) {



        wp_enqueue_script(
            'delivery-method-js',
            get_template_directory_uri() . '/assets/js/delivery-method.js',
            array('jquery', 'wp-util'), // Added wp-util for AJAX
            time(),
            true // Load in footer
        );

        wp_localize_script(
            'delivery-method-js',
            'delivery_ajax', // Must match JS references
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('delivery_ajax_nonce'),
                'gc_nonce' => wp_create_nonce('gc_nonce'),
            )
        );

        // draft-order.js file does not exist — enqueue disabled to prevent 404
        // wp_enqueue_script(
        //     'draft-order-js',
        //     get_template_directory_uri() . '/assets/js/draft-order.js',
        //     array('jquery', 'wp-util'),
        //     time(),
        //     true
        // );
        // wp_localize_script(
        //     'draft-order-js',
        //     'draft_order_ajax',
        //     array(
        //         'ajax_url' => admin_url('admin-ajax.php'),
        //         'nonce' => wp_create_nonce('draft_order_ajax_nonce'),
        //     )
        // );

        wp_enqueue_style(
            'flatpickr-css',
            get_template_directory_uri() . '/assets/css/flatpickr.min.css',
            array(),
            time()
        );
        wp_enqueue_script(
            'flatpickr-js',
            get_template_directory_uri() . '/assets/js/flatpickr.js',
            array('jquery'),
            time(),
        );

        wp_enqueue_style(
            'manual-order-css',
            get_template_directory_uri() . '/assets/css/manual-order.css',
            array(),
            time()
        );
        wp_enqueue_style(
            'delivery-method',
            get_template_directory_uri() . '/assets/css/delivery-method.css',
            array(),
            time()
        );

    }

    /*This is the last styles please include Another css above this*/
    wp_enqueue_style(
        'twenty-twenty-one-theme-style',
        get_template_directory_uri() . '/assets/css/theme.css',
        array(),
        time()
    );

    // This custom.css for the developer
    wp_enqueue_style(
        'custom-css',
        get_template_directory_uri() . '/assets/css/custom.css',
        array(),
        time()
    );

    wp_enqueue_style(
        'twenty-twenty-one-brand-style',
        get_template_directory_uri() . '/assets/css/brandpage.css',
        array(),
        time()
    );

    global $wp;
    if (is_account_page() && isset($wp->query_vars['my-reminders'])) {
        wp_enqueue_style(
            'my-reminders-css',
            get_template_directory_uri() . '/assets/css/my-reminders.css',
            array(),
            wp_get_theme()->get('Version')
        );
    }
}
add_action('wp_enqueue_scripts', 'twenty_twenty_one_scripts');



/**
 * Enqueues block editor script.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @return void
 */
function twentytwentyone_block_editor_script()
{

    wp_enqueue_script('twentytwentyone-editor', get_theme_file_uri('/assets/js/editor.js'), array('wp-blocks', 'wp-dom'), wp_get_theme()->get('Version'), array('in_footer' => true));
}

add_action('enqueue_block_editor_assets', 'twentytwentyone_block_editor_script');

/**
 * Fixes skip link focus in IE11.
 *
 * This does not enqueue the script because it is tiny and because it is only for IE11,
 * thus it does not warrant having an entire dedicated blocking script being loaded.
 *
 * @since Twenty Twenty-One 1.0
 * @deprecated Twenty Twenty-One 1.9 Removed from wp_print_footer_scripts action.
 *
 * @link https://git.io/vWdr2
 */
function twenty_twenty_one_skip_link_focus_fix()
{

    // If SCRIPT_DEBUG is defined and true, print the unminified file.
    if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
        echo '<script>';
        include get_template_directory() . '/assets/js/skip-link-focus-fix.js';
        echo '</script>';
    } else {
        // The following is minified via `npx terser --compress --mangle -- assets/js/skip-link-focus-fix.js`.
        ?>
        <script>
            /(trident|msie)/i.test(navigator.userAgent) && document.getElementById && window.addEventListener && window.addEventListener("hashchange", (function () { var t, e = location.hash.substring(1); /^[A-z0-9_-]+$/.test(e) && (t = document.getElementById(e)) && (/^(?:a|select|input|button|textarea)$/i.test(t.tagName) || (t.tabIndex = -1), t.focus()) }), !1);
        </script>
        <?php
    }
}

/**
 * Enqueues non-latin language styles.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @return void
 */
function twenty_twenty_one_non_latin_languages()
{
    $custom_css = twenty_twenty_one_get_non_latin_css('front-end');

    if ($custom_css) {
        wp_add_inline_style('twenty-twenty-one-style', $custom_css);
    }
}
add_action('wp_enqueue_scripts', 'twenty_twenty_one_non_latin_languages');

// SVG Icons class.
require get_template_directory() . '/classes/class-twenty-twenty-one-svg-icons.php';

// Custom color classes.
require get_template_directory() . '/classes/class-twenty-twenty-one-custom-colors.php';
new Twenty_Twenty_One_Custom_Colors();

// Enhance the theme by hooking into WordPress.
require get_template_directory() . '/inc/template-functions.php';

// Menu functions and filters.
require get_template_directory() . '/inc/menu-functions.php';

// Custom template tags for the theme.
require get_template_directory() . '/inc/template-tags.php';

// Customizer additions.
require get_template_directory() . '/classes/class-twenty-twenty-one-customize.php';
new Twenty_Twenty_One_Customize();

// Block Patterns.
require get_template_directory() . '/inc/block-patterns.php';

// Block Styles.
require get_template_directory() . '/inc/block-styles.php';

// Dark Mode.
require_once get_template_directory() . '/classes/class-twenty-twenty-one-dark-mode.php';
//customisation
require_once get_template_directory() . '/customisation-function.php';

//delivery method functions
require_once get_template_directory() . '/delivery-method-functions.php';
// Load additional functions from a separate file
require_once get_template_directory() . '/custom-functions.php';
require_once get_template_directory() . '/contact-list-events-admin.php';
require_once get_template_directory() . '/inc/gc-pdf-functions.php';
require_once get_template_directory() . '/inc/single-order-functions.php';

require_once get_template_directory() . '/category-listing-function.php';
require_once get_template_directory() . '/product-listing-function.php';
require get_template_directory() . '/inc/export-order.php';
require get_template_directory() . '/inc/export-brands.php';
require_once get_template_directory() . '/reports/reports-functions.php';
require_once get_template_directory() . '/inc/gc_number_functions.php';
require_once get_template_directory() . '/inc/sms-functions.php';
require_once get_template_directory() . '/inc/invoice-pdf-functions.php';
require_once get_template_directory() . '/inc/order-spam-detection.php';


// require_once get_template_directory() . '/test-sms.php';



// require_once get_template_directory() . '/bulk-form.php';

new Twenty_Twenty_One_Dark_Mode();

/**
 * Enqueues scripts for the customizer preview.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @return void
 */
function twentytwentyone_customize_preview_init()
{
    wp_enqueue_script(
        'twentytwentyone-customize-helpers',
        get_theme_file_uri('/assets/js/customize-helpers.js'),
        array(),
        wp_get_theme()->get('Version'),
        array('in_footer' => true)
    );

    wp_enqueue_script(
        'twentytwentyone-customize-preview',
        get_theme_file_uri('/assets/js/customize-preview.js'),
        array('customize-preview', 'customize-selective-refresh', 'jquery', 'twentytwentyone-customize-helpers'),
        wp_get_theme()->get('Version'),
        array('in_footer' => true)
    );
}
add_action('customize_preview_init', 'twentytwentyone_customize_preview_init');

/**
 * Enqueues scripts for the customizer.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @return void
 */
function twentytwentyone_customize_controls_enqueue_scripts()
{

    wp_enqueue_script(
        'twentytwentyone-customize-helpers',
        get_theme_file_uri('/assets/js/customize-helpers.js'),
        array(),
        wp_get_theme()->get('Version'),
        array('in_footer' => true)
    );
}
add_action('customize_controls_enqueue_scripts', 'twentytwentyone_customize_controls_enqueue_scripts');

/**
 * Calculates classes for the main <html> element.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @return void
 */
function twentytwentyone_the_html_classes()
{
    /**
     * Filters the classes for the main <html> element.
     *
     * @since Twenty Twenty-One 1.0
     *
     * @param string The list of classes. Default empty string.
     */
    $classes = apply_filters('twentytwentyone_html_classes', '');
    if (!$classes) {
        return;
    }
    echo 'class="' . esc_attr($classes) . '"';
}

/**
 * Adds "is-IE" class to body if the user is on Internet Explorer.
 *
 * @since Twenty Twenty-One 1.0
 *
 * @return void
 */
function twentytwentyone_add_ie_class()
{

    ?>
    <script>
        if (-1 !== navigator.userAgent.indexOf('MSIE') || -1 !== navigator.appVersion.indexOf('Trident/')) {
            document.body.classList.add('is-IE');
        }
    </script>
    <?php
}
add_action('wp_footer', 'twentytwentyone_add_ie_class');

if (!function_exists('wp_get_list_item_separator')):
    /**
     * Retrieves the list item separator based on the locale.
     *
     * Added for backward compatibility to support pre-6.0.0 WordPress versions.
     *
     * @since 6.0.0
     */
    function wp_get_list_item_separator()
    {
        /* translators: Used between list items, there is a space after the comma. */
        return __(', ', 'twentytwentyone');
    }
endif;
// Hook into the 'init' action to register custom taxonomies.
add_action('init', 'register_brands_product_taxonomies', 0);

function register_brands_product_taxonomies()
{
    // Register 'Brands' taxonomy
    $labels_brands = array(
        'name' => _x('Brands', 'taxonomy general name', 'textdomain'),
        'singular_name' => _x('Brand', 'taxonomy singular name', 'textdomain'),
        'search_items' => __('Search Brands', 'textdomain'),
        'all_items' => __('All Brands', 'textdomain'),
        'parent_item' => __('Parent Brand', 'textdomain'),
        'parent_item_colon' => __('Parent Brand:', 'textdomain'),
        'edit_item' => __('Edit Brand', 'textdomain'),
        'update_item' => __('Update Brand', 'textdomain'),
        'add_new_item' => __('Add New Brand', 'textdomain'),
        'new_item_name' => __('New Brand Name', 'textdomain'),
        'menu_name' => __('Brands', 'textdomain'),
    );

    $args_brands = array(
        'hierarchical' => true, // Set to true to make it behave like categories.
        'labels' => $labels_brands,
        'show_ui' => true,
        'show_admin_column' => false, // Do not display in the product list.
        'query_var' => true,
        'rewrite' => array('slug' => 'brands'),
    );

    // register_taxonomy('brands', 'product', $args_brands);

    // Register 'Eligible Retailers' taxonomy
    $labels_retailers = array(
        'name' => _x('Eligible Retailers', 'taxonomy general name', 'textdomain'),
        'singular_name' => _x('Eligible Retailer', 'taxonomy singular name', 'textdomain'),
        'search_items' => __('Search Eligible Retailers', 'textdomain'),
        'all_items' => __('All Eligible Retailers', 'textdomain'),
        'parent_item' => __('Parent Retailer', 'textdomain'),
        'parent_item_colon' => __('Parent Retailer:', 'textdomain'),
        'edit_item' => __('Edit Retailer', 'textdomain'),
        'update_item' => __('Update Retailer', 'textdomain'),
        'add_new_item' => __('Add New Retailer', 'textdomain'),
        'new_item_name' => __('New Retailer Name', 'textdomain'),
        'menu_name' => __('Eligible Retailers', 'textdomain'),
    );

    $args_retailers = array(
        'hierarchical' => true, // Set to true to make it behave like categories.
        'labels' => $labels_retailers,
        'show_ui' => true,
        'publicly_queryable' => false,
        'show_admin_column' => false, // Do not display in the product list.
        'query_var' => true,
        'rewrite' => array('slug' => 'eligible-retailers'),
    );

    register_taxonomy('eligible_retailers', 'product', $args_retailers);


    // Register 'Gift Cards For' taxonomy
    $labels_gift_cards_for = array(
        'name'              => _x('Gift Cards For', 'taxonomy general name', 'textdomain'),
        'singular_name'     => _x('Gift Card For', 'taxonomy singular name', 'textdomain'),
        'search_items'      => __('Search Gift Cards For', 'textdomain'),
        'all_items'         => __('All Gift Cards For', 'textdomain'),
        'edit_item'         => __('Edit Gift Card For', 'textdomain'),
        'update_item'       => __('Update Gift Card For', 'textdomain'),
        'add_new_item'      => __('Add New Gift Card For', 'textdomain'),
        'new_item_name'     => __('New Gift Card For Name', 'textdomain'),
        'menu_name'         => __('Gift Cards For', 'textdomain'),
    );

    $args_gift_cards_for = array(
        'hierarchical'          => false,
        'labels'                => $labels_gift_cards_for,
        'show_ui'               => true,
        'publicly_queryable'    => false,
        'show_admin_column'     => false,
        'query_var'             => true,
        'rewrite'               => array('slug' => 'gift-cards-for'),
    );

    // register_taxonomy('gift_cards_for', 'product', $args_gift_cards_for);

    // Register 'Occasions' taxonomy
    $labels_occasions = array(
        'name'              => _x('Occasions', 'taxonomy general name', 'textdomain'),
        'singular_name'     => _x('Occasion', 'taxonomy singular name', 'textdomain'),
        'search_items'      => __('Search Occasions', 'textdomain'),
        'all_items'         => __('All Occasions', 'textdomain'),
        'edit_item'         => __('Edit Occasion', 'textdomain'),
        'update_item'       => __('Update Occasion', 'textdomain'),
        'add_new_item'      => __('Add New Occasion', 'textdomain'),
        'new_item_name'     => __('New Occasion Name', 'textdomain'),
        'menu_name'         => __('Occasions', 'textdomain'),
    );

    $args_occasions = array(
        'hierarchical'          => false,
        'labels'                => $labels_occasions,
        'show_ui'               => true,
        'publicly_queryable'    => false,
        'show_admin_column'     => false,
        'query_var'             => true,
        'rewrite'               => array('slug' => 'occasions'),
    );

    // register_taxonomy('occasions', 'product', $args_occasions);

}
add_action('init', 'register_icons_product_taxonomies', 0);

function register_icons_product_taxonomies()
{
    // Register 'Icons' taxonomy
    $labels_icons = array(
        'name' => _x('Icons', 'taxonomy general name', 'textdomain'),
        'singular_name' => _x('Icon', 'taxonomy singular name', 'textdomain'),
        'search_items' => __('Search Icons', 'textdomain'),
        'all_items' => __('All Icons', 'textdomain'),
        'parent_item' => __('Parent Icon', 'textdomain'),
        'parent_item_colon' => __('Parent Brand:', 'textdomain'),
        'edit_item' => __('Edit Icon', 'textdomain'),
        'update_item' => __('Update Icon', 'textdomain'),
        'add_new_item' => __('Add New Icon', 'textdomain'),
        'new_item_name' => __('New Icon Name', 'textdomain'),
        'menu_name' => __('Icons', 'textdomain'),
    );

    $args_icons = array(
        'hierarchical' => true, // Set to true to make it behave like categories.
        'labels' => $labels_icons,
        'show_ui' => true,
        'publicly_queryable' => false,
        'show_admin_column' => false, // Do not display in the product list.
        'query_var' => true,
        'rewrite' => array('slug' => 'icons'),
    );

    register_taxonomy('icons', 'product', $args_icons);
}

add_action('woocommerce_product_options_general_product_data', 'add_custom_fields_to_general_tab');
function add_custom_fields_to_general_tab()
{
    global $post;

    // Parent SKU
    // woocommerce_wp_text_input([
    //  'id' => '_parent_sku',
    //  'label' => __('Parent SKU', 'woocommerce'),
    //  'description' => __('Enter the Parent SKU for this product.', 'woocommerce'),
    //  'desc_tip' => true,
    // ]);

    // Cost Price
    woocommerce_wp_text_input([
        'id' => '_cost_price',
        'label' => __('Cost Price', 'woocommerce'),
        'description' => __('Enter the cost price for this product.', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
    ]);

    // Total Sell Price
    woocommerce_wp_text_input([
        'id' => '_total_sell_price',
        'label' => __('Total Sell Price', 'woocommerce'),
        'description' => __('Enter the total sell price for this product.', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
    ]);

    // Total Buy Price
    woocommerce_wp_text_input([
        'id' => '_total_buy_price',
        'label' => __('Total Buy Price', 'woocommerce'),
        'description' => __('Enter the total buy price for this product.', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
    ]);

    // Sell Price Fixed
    woocommerce_wp_text_input([
        'id' => '_sell_price_fixed',
        'label' => __('Sell Price Fixed', 'woocommerce'),
        'description' => __('Enter the Sell Price Fixed for this product.', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
    ]);

    // Total Buy Price Including GST
    woocommerce_wp_text_input([
        'id' => '_total_buy_price_gst',
        'label' => __('Total Buy Price (incl. GST)', 'woocommerce'),
        'description' => __('Enter the total buy price including GST.', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
    ]);

    // J&C Fulfillment Cost
    woocommerce_wp_text_input([
        'id' => 'j_a_c_fulfillment_cost',
        'label' => __('J&C Fulfilment Cost', 'woocommerce'),
        'description' => __('Enter the fulfillment cost for this product.', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
    ]);

    // Delivery Cost
    woocommerce_wp_text_input([
        'id' => '_delivery_cost',
        'label' => __('Delivery Cost', 'woocommerce'),
        'description' => __('Enter the delivery cost for this product.', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
    ]);

    // Delivery Cost
    woocommerce_wp_text_input([
        'id' => '_supplier_fullfillment_price',
        'label' => __('Supplier Fulfillment Price', 'woocommerce'),
        'description' => __('Enter the Supplier Fullillment Price for this product.', 'woocommerce'),
        'type' => 'number',
        'desc_tip' => true,
    ]);
}

add_action('woocommerce_product_options_advanced', 'add_fields_to_advanced_tab');
function add_fields_to_advanced_tab()
{
    global $post;

    // Supplier SKU
    woocommerce_wp_text_input([
        'id' => '_supplier_sku',
        'label' => __('Supplier SKU', 'woocommerce'),
        'description' => __('Enter the Supplier SKU for this product.', 'woocommerce'),
        'desc_tip' => true,
    ]);

    // GST
    woocommerce_wp_text_input([
        'id' => '_gst',
        'label' => __('GST', 'woocommerce'),
        'description' => __('Enter the GST for this product.', 'woocommerce'),
        'desc_tip' => true,
    ]);

    // Extra Header
    $extra_header_value = get_post_meta($post->ID, '_extra_header', true);
    $extra_header_label = get_post_meta($post->ID, 'label_extra_header', true) ?: __('Extra Header', 'woocommerce');

    echo '<div class="options_group">';
    echo '<p class="form-field"><label><strong>' . esc_html($extra_header_label) . '</strong></label></p>';
    echo '<style>
        #_extra_header {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            font-size: 14px;
            resize: vertical;
        }
        </style>';
    echo '<div class="custom-wp-editor" style="clear: both; margin-bottom: 20px;">';
    wp_editor(
        $extra_header_value,
        '_extra_header',
        [
            'textarea_name' => '_extra_header',
            'textarea_rows' => 10,
            'teeny' => true,
        ]
    );
    echo '</div></div>'; // Close .custom-wp-editor and .options_group
    echo '<hr class="solid" style="border-top: 3px solid #bbb; width: 1120px;">';


    // Margin
    woocommerce_wp_text_input([
        'id' => '_margin',
        'label' => __('Margin', 'woocommerce'),
        'description' => __('Enter the Margin for this product.', 'woocommerce'),
        'desc_tip' => true,
    ]);

    // Discount Margin
    woocommerce_wp_text_input([
        'id' => '_discount_margin',
        'label' => __('Discount Margin', 'woocommerce'),
        'description' => __('Enter the Discount Margin for this product.', 'woocommerce'),
        'desc_tip' => true,
    ]);

    // How To Use
    $how_to_use_value = get_post_meta($post->ID, 'how_to_use', true);
    $how_to_use_label = get_post_meta($post->ID, 'label_how_to_use', true) ?: __('How To Use', 'woocommerce');

    echo '<div class="options_group">';
    echo '<p class="form-field"><label><strong>' . esc_html($how_to_use_label) . '</strong></label></p>';
    echo '<style>
        #how_to_use {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            font-size: 14px;
            resize: vertical;
        }
        </style>';
    echo '<div class="custom-wp-editor" style="clear: both; margin-bottom: 20px;">';
    wp_editor(
        $how_to_use_value,
        'how_to_use',
        [
            'textarea_name' => 'how_to_use',
            'textarea_rows' => 10,
            'teeny' => true,
        ]
    );
    echo '</div></div>';
    echo '<hr class="solid" style="border-top: 3px solid #bbb; width: 1120px;">';


    // Terms & Conditions
    $terms_conditions_value = get_post_meta($post->ID, 'terms_conditions', true);
    $terms_conditions_label = get_post_meta($post->ID, 'label_terms_conditions', true) ?: __('Terms & Conditions', 'woocommerce');

    echo '<div class="options_group">';
    echo '<p class="form-field"><label><strong>' . esc_html($terms_conditions_label) . '</strong></label></p>';
    echo '<style>
        #terms_conditions {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            font-size: 14px;
            resize: vertical;
        }
        </style>';
    echo '<div class="custom-wp-editor" style="clear: both; margin-bottom: 20px;">';
    wp_editor(
        $terms_conditions_value,
        'terms_conditions',
        [
            'textarea_name' => 'terms_conditions',
            'textarea_rows' => 10,
            'teeny' => true,
        ]
    );
    echo '</div></div>';
    echo '<hr class="solid" style="border-top: 3px solid #bbb; width: 1120px;">';

    // Expiry Date/Time
    $_expire_date_value = get_post_meta($post->ID, '_expire_date', true);
    $_expire_date_label = get_post_meta($post->ID, 'label__expire_date', true) ?: __('Expiry Date/Time', 'woocommerce');

    echo '<div class="options_group">';
    echo '<p class="form-field"><label><strong>' . esc_html($_expire_date_label) . '</strong></label></p>';
    echo '<style>
        #_expire_date {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            font-size: 14px;
            resize: vertical;
        }
        </style>';
    echo '<div class="custom-wp-editor" style="clear: both; margin-bottom: 20px;">';
    wp_editor(
        $_expire_date_value,
        '_expire_date',
        [
            'textarea_name' => '_expire_date',
            'textarea_rows' => 10,
            'teeny' => true,
        ]
    );
    echo '</div></div>';
    echo '<hr class="solid" style="border-top: 3px solid #bbb; width: 1120px;">';

    // Discount Valid From
    echo '<div class="options_group">
        <p class="form-field _discount_valid_from">
            <label for="_discount_valid_from">' . __('Discount Valid From', 'woocommerce') . '</label>
            <input type="datetime-local" class="short" id="_discount_valid_from" name="_discount_valid_from" value="' . esc_attr(get_post_meta(get_the_ID(), '_discount_valid_from', true)) . '" />
        </p>
    </div>';

    // Discount Valid To
    echo '<div class="options_group">
        <p class="form-field _discount_valid_to">
            <label for="_discount_valid_to">' . __('Discount Valid To', 'woocommerce') . '</label>
            <input type="datetime-local" class="short" id="_discount_valid_to" name="_discount_valid_to" value="' . esc_attr(get_post_meta(get_the_ID(), '_discount_valid_to', true)) . '" />
        </p>
    </div>';

    // Onsite From
    echo '<div class="options_group">
        <p class="form-field _onsite_from">
            <label for="_onsite_from">' . __('Onsite From', 'woocommerce') . '</label>
            <input type="datetime-local" class="short" id="_onsite_from" name="_onsite_from" value="' . esc_attr(get_post_meta(get_the_ID(), '_onsite_from', true)) . '" />
        </p>
    </div>';

    // Onsite To
    echo '<div class="options_group">
        <p class="form-field _onsite_to">
            <label for="_onsite_to">' . __('Onsite To', 'woocommerce') . '</label>
            <input type="datetime-local" class="short" id="_onsite_to" name="_onsite_to" value="' . esc_attr(get_post_meta(get_the_ID(), '_onsite_to', true)) . '" />
        </p>
    </div>';
}


function custom_change_product_labels($translated_text, $text, $domain)
{
    global $post;

    if (is_admin() && $domain === 'woocommerce') {
        $product_id = $post ? $post->ID : 0;

        // Custom Short Description Label
        if ($text === 'Product short description') {
            $custom_short_label = get_post_meta($product_id, 'label_short_description', true);
            return !empty($custom_short_label) ? $custom_short_label : __('Product Short Description', 'woocommerce');
        }

        // Custom Long Description Label
        if ($text === 'Product description') {
            $custom_long_label = get_post_meta($product_id, 'label_long_description', true);
            return !empty($custom_long_label) ? $custom_long_label : __('Product Description', 'woocommerce');
        }
    }

    return $translated_text;
}
add_filter('gettext', 'custom_change_product_labels', 10, 3);

// Change WooCommerce cart notices and empty cart/basket messages
add_filter('gettext', function ($translated_text, $text, $domain) {
    if ($domain !== 'woocommerce') {
        return $translated_text;
    }
    if ($text === 'Cart updated.') {
        return 'Your cart has now been updated.';
    }
    // Empty cart/basket messages (cart page, block cart, mini cart)
    if ($text === 'Your cart is currently empty.' || $text === 'Your cart is currently empty!' || $text === 'No products in the cart.') {
        return 'There are currently no items in your cart';
    }
    return $translated_text;
}, 10, 3);

add_action('woocommerce_process_product_meta', 'save_custom_product_fields');
function save_custom_product_fields($post_id)
{
    $fields = [
        // '_parent_sku',
        '_supplier_sku',
        '_cost_price',
        '_total_sell_price',
        '_total_buy_price',
        '_total_buy_price_gst',
        '_gst',
        'j_a_c_fulfillment_cost',
        '_sell_price_fixed',
        '_supplier_fullfillment_price',
        '_delivery_cost',
        '_margin',
        '_discount_margin',
    ];

    if (isset($_POST['_expire_date'])) {
        update_post_meta($post_id, '_expire_date', sanitize_text_field($_POST['_expire_date']));
    }

    if (isset($_POST['_discount_valid_from'])) {
        update_post_meta($post_id, '_discount_valid_from', sanitize_text_field($_POST['_discount_valid_from']));
    }

    if (isset($_POST['_discount_valid_to'])) {
        update_post_meta($post_id, '_discount_valid_to', sanitize_text_field($_POST['_discount_valid_to']));
    }
    if (isset($_POST['_onsite_from'])) {
        update_post_meta($post_id, '_onsite_from', sanitize_text_field($_POST['_onsite_from']));
    }

    if (isset($_POST['_onsite_to'])) {
        update_post_meta($post_id, '_onsite_to', sanitize_text_field($_POST['_onsite_to']));
    }

    if (isset($_POST['_extra_header'])) {
        $extra_header_content = wp_kses_post($_POST['_extra_header']);
        update_post_meta($post_id, '_extra_header', $extra_header_content);
    }
    if (isset($_POST['how_to_use'])) {
        $how_to_use_content = wp_kses_post($_POST['how_to_use']);
        update_post_meta($post_id, 'how_to_use', $how_to_use_content);
    }
    if (isset($_POST['terms_conditions'])) {
        $terms_conditions_content = wp_kses_post($_POST['terms_conditions']);
        update_post_meta($post_id, 'terms_conditions', $terms_conditions_content);
    }

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}
function add_business_user_role()
{
    add_role(
        'business_user',
        'Business User',
        array(
            'read' => true,  // Can read content
            'edit_posts' => false,  // Cannot edit posts
            'delete_posts' => false,  // Cannot delete posts
            'publish_posts' => false,  // Cannot publish posts
            'upload_files' => true,  // Can upload files
        )
    );
}
add_action('init', 'add_business_user_role');

function create_supplier_user_role()
{
    add_role(
        'supplier', // Role slug
        'Supplier', // Display name for the role
        array(
            'read' => true, // Allow reading the dashboard
            'edit_posts' => false, // Do not allow editing posts
            'edit_pages' => true, // Allow editing pages
            'edit_others_posts' => false, // Do not allow editing others' posts
            'publish_posts' => false, // Do not allow publishing posts
            'delete_posts' => false, // Do not allow deleting posts
            'upload_files' => true, // Allow uploading files (for product images)
            'manage_options' => false, // Do not allow managing options
            'edit_product' => true, // Allow editing products (if using WooCommerce)
            'view_product' => true, // Allow viewing products
            // Add any custom capabilities here
        )
    );
}
add_action('init', 'create_supplier_user_role');


function register_parent_sku_taxonomy()
{

    $labels = array(

        'name' => _x('Parent SKUs', 'taxonomy general name', 'textdomain'),

        'singular_name' => _x('Parent SKU', 'taxonomy singular name', 'textdomain'),

        'search_items' => __('Search Parent SKUs', 'textdomain'),

        'all_items' => __('All Parent SKUs', 'textdomain'),

        'edit_item' => __('Edit Parent SKU', 'textdomain'),

        'update_item' => __('Update Parent SKU', 'textdomain'),

        'add_new_item' => __('Add New Parent SKU', 'textdomain'),

        'new_item_name' => __('New Parent SKU Name', 'textdomain'),

        'menu_name' => __('Parent SKUs', 'textdomain'),

    );

    $args = array(

        'hierarchical' => true,

        'labels' => $labels,

        'show_ui' => true,

        'show_admin_column' => true,

        'query_var' => true,

        'rewrite' => array('slug' => 'parent-sku'),

    );

    register_taxonomy('parent_sku', 'product', $args);

}

add_action('init', 'register_parent_sku_taxonomy');
// Handle product search by name
// AJAX: Search Product by Name
function handle_product_search_by_name()
{
    gcp_require_admin_ajax();
    global $wpdb;
    $product_name = sanitize_text_field($_POST['product_name']);
    $response = [];
    $is_parent = false;

    // Try parent products
    $parent_products = get_posts([
        'post_type' => 'product',
        'post_status' => 'publish',
        's' => $product_name,
        'meta_query' => [
            ['key' => 'sku_type', 'value' => 'Parent', 'compare' => '=']
        ],
        'fields' => 'ids'
    ]);

    if (!empty($parent_products)) {
        foreach ($parent_products as $parent_id) {
            $parent_sku = get_post_meta($parent_id, '_sku', true);
            $children = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'parent_sku',
                        'value' => $parent_sku,
                        'compare' => '='
                    ],
                    [
                        'key' => 'sku_type',
                        'value' => 'Child',
                        'compare' => '='
                    ]
                ],
                'fields' => 'ids',
                'posts_per_page' => -1
            ]);

            if (!empty($children)) {
                $is_parent = true;
                $children_data = array_map(function ($child_id) {
                    return [
                        'sku' => get_post_meta($child_id, '_sku', true),
                        'name' => get_the_title($child_id)
                    ];
                }, $children);

                $response = [
                    'is_parent' => true,
                    'name' => get_the_title($parent_id),
                    'image' => wp_get_attachment_url(get_post_thumbnail_id($parent_id)),
                    'children' => $children_data
                ];
                break;
            }
        }
    }

    // Fallback to individual product
    if (!$is_parent) {
        $products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            's' => $product_name,
            'posts_per_page' => 1
        ]);

        if (!empty($products)) {
            $product = $products[0];
            $response = [
                'is_parent' => false,
                'name' => $product->post_title,
                'sku' => get_post_meta($product->ID, '_sku', true),
                'image' => wp_get_attachment_url(get_post_thumbnail_id($product->ID))
            ];
        }
    }

    if (!empty($response)) {
        wp_send_json_success($response);
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_search_product_by_name', 'handle_product_search_by_name');

// AJAX: SKU List CSV Download
function handle_sku_list_download1()
{
    gcp_require_admin_ajax();
    global $wpdb;
    $product_name = isset($_GET['product_name']) ? sanitize_text_field($_GET['product_name']) : '';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sku_list.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Product ID', 'Product Name', 'SKU', 'Denomination']);

    if (!empty($product_name)) {
        // Search parent products
        $parent_products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            's' => $product_name,
            'meta_query' => [
                ['key' => 'sku_type', 'value' => 'Parent', 'compare' => '=']
            ],
            'fields' => 'ids'
        ]);

        $found_children = false;

        foreach ($parent_products as $parent_id) {
            $parent_sku = get_post_meta($parent_id, '_sku', true);
            $children = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'meta_query' => [
                    ['key' => 'parent_sku', 'value' => $parent_sku, 'compare' => '=']
                ],
                'fields' => 'ids',
                'posts_per_page' => -1
            ]);

            if (!empty($children)) {
                $found_children = true;
                foreach ($children as $child_id) {
                    $temp_d_type = get_post_meta($child_id, 'denomination_type', true);
                    $denomination_type = '';
                    if (is_array($temp_d_type)) {
                        $denomination_type = implode(', ', $temp_d_type);
                    } else {
                        $denomination_type = $temp_d_type;
                    }
                    fputcsv($output, [
                        $child_id,
                        get_the_title($child_id),
                        get_post_meta($child_id, '_sku', true),
                        $denomination_type
                    ]);
                }
            }
        }

        if (!$found_children) {
            // Individual product
            $products = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                's' => $product_name,
                'posts_per_page' => 1
            ]);

            foreach ($products as $product) {
                $temp_d_type = get_post_meta($product->ID, 'denomination_type', true);
                $denomination_type = '';
                if (is_array($temp_d_type)) {
                    $denomination_type = implode(', ', $temp_d_type);
                } else {
                    $denomination_type = $temp_d_type;
                }
                fputcsv($output, [
                    $product->ID,
                    $product->post_title,
                    get_post_meta($product->ID, '_sku', true),
                    $denomination_type
                ]);
            }
        }
    } else {
        // Download all (excluding parent sku_type)
        $all_products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => -1
        ]);

        foreach ($all_products as $product_id) {
            $sku = get_post_meta($product_id, '_sku', true);
            $sku_type = get_post_meta($product_id, 'sku_type', true);

            $temp_d_type = get_post_meta($product_id, 'denomination_type', true);
            $denomination_type = '';
            if (is_array($temp_d_type)) {
                $denomination_type = implode(', ', $temp_d_type);
            } else {
                $denomination_type = $temp_d_type;
            }
            /*$is_parent = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'parent_sku' AND meta_value = %s",
                $sku
            ));*/

            if ($sku_type != 'Parent') {
                fputcsv($output, [$product_id, get_the_title($product_id), $sku, $denomination_type]);
            }
        }
    }

    fclose($output);
    exit;
}
add_action('wp_ajax_download_sku_list', 'handle_sku_list_download1');

//Start Code of 6 feb
function enqueue_jquery_ui_styles()
{
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css');
}
add_action('wp_enqueue_scripts', 'enqueue_jquery_ui_styles');

function enqueue_ajax_script()
{

    wp_enqueue_script('jquery-ui-autocomplete'); // Ensure jQuery UI is loaded

    wp_enqueue_script('custom-ajax-js', get_template_directory_uri() . '/assets/js/custom-ajax.js', array('jquery'), time(), true);

    wp_localize_script('custom-ajax-js', 'ajax_sku', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('search_parent_sku_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_ajax_script');

// function enqueue_custom_scripts()
// {
//  wp_enqueue_script('image-ajax-js', get_template_directory_uri() . '/assets/js/image-ajax.js', array('jquery'), time(), true);

//  // Localize the script to include the 'ajaxurl' and other variables
//  wp_localize_script('image-ajax-js', 'ajax_image', admin_url('admin-ajax.php'));
// }
// add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');


function search_product_tags()
{
    gcp_require_admin_ajax();
    check_ajax_referer('ajax_sku_nonce', 'nonce');

    if (!isset($_POST['term'])) {
        wp_send_json_error(['message' => 'No search term provided']);
    }

    $search_term = sanitize_text_field($_POST['term']);

    $tags = get_terms([
        'taxonomy' => 'product_tag',
        'hide_empty' => false,
        'search' => $search_term
    ]);

    $results = [];
    if (!empty($tags) && !is_wp_error($tags)) {
        foreach ($tags as $tag) {
            $results[] = [
                'label' => esc_html($tag->name),
                'value' => esc_attr($tag->slug)
            ];
        }
    }

    wp_send_json($results);
}

add_action('wp_ajax_search_product_tags', 'search_product_tags');


add_action('wp_ajax_create_product', 'handle_create_product_ajax');


function handle_create_product_ajax()
{
    gcp_require_admin_ajax();
    // Verify the nonce if you're using it for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'create_product_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    // Check if gallery images are provided
    if (!empty($_FILES['gallery_images']['name'][0])) {
        $gallery_attachment_ids = [];  // Array to store image IDs

        // Loop through each uploaded image
        foreach ($_FILES['gallery_images']['name'] as $key => $image_name) {
            $file_data = [
                'name' => $image_name,
                'type' => $_FILES['gallery_images']['type'][$key],
                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$key],
                'error' => $_FILES['gallery_images']['error'][$key],
                'size' => $_FILES['gallery_images']['size'][$key],
            ];

            if (!function_exists('media_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }

            // Upload the image
            $attachment_id = media_handle_upload('gallery_images', 0, $file_data);

            if (!is_wp_error($attachment_id)) {
                $gallery_attachment_ids[] = $attachment_id;  // Store uploaded image ID
            }
        }

        // Handle the rest of your product creation logic, including creating the product
        // For example:
        $product = new WC_Product_Simple();
        $product->set_name('Your Product Name');
        // Set other product fields like price, SKU, etc.

        $product_id = $product->save();  // Save the product

        if ($product_id && !empty($gallery_attachment_ids)) {
            // Set the uploaded images as the product gallery
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_attachment_ids));
            wp_send_json_success(['product_id' => $product_id, 'message' => 'Product created successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to create product']);
        }
    } else {
        wp_send_json_error(['message' => 'No images uploaded']);
    }
}

// add_filter('acf/fields/post_object/result/key=field_67b420278fe0f', 'update_acf_post_object_field_choices', 10, 4);
function populate_display_on_choices($field)
{
    // Get all pages
    $pages = get_pages(array('sort_column' => 'menu_order', 'sort_order' => 'ASC'));

    // Clear existing choices
    $field['choices'] = array();

    // Populate choices with pages
    foreach ($pages as $page) {
        $field['choices'][$page->post_name] = $page->post_title;
    }

    return $field;
}
add_filter('acf/load_field/name=display_on', 'populate_display_on_choices');

add_action('save_post', function ($post_id) {
    // Skip autosave to prevent unnecessary overwrites
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Ensure the user has permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Make sure the field exists in the form submission
    if (isset($_POST['featured_placements'])) {
        $selected_value = sanitize_text_field($_POST['featured_placements']);

        // Save value into the ACF field 'display_on'
        update_field('display_on', $selected_value, $post_id);
    }
});

// function update_acf_post_object_field_choices($title, $post, $field, $post_id)
// {
//  if (is_admin() && get_post_type($post_id) === 'product') {
//      $sku_value = get_field('sku', $post->ID);
//      if (!$sku_value) {
//          $sku_value = get_post_meta($post->ID, '_sku', true);
//      }

//      if ($sku_value && !empty($sku_value)) {
//          $title .= ' --- ' . $sku_value;
//      }
//  }
//  return $title;
// }
// Apply filter to a specific ACF field

//END
add_action('wp_ajax_upload_csv_file_bulk', 'handle_csv_upload');
function handle_csv_upload()
{
    gcp_require_admin_ajax();
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $uploaded_file = $_FILES['file'];
        $file_tmp_name = $uploaded_file['tmp_name'];

        $timestamp = date('Y-m-d_H-i-s');
        $new_file_name = 'csv_' . $timestamp . '.csv';

        $upload_dir = WP_CONTENT_DIR . '/assets/csv/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $csv_data = parse_csv_file($destination);

            $template_headers = [
                'Client reference',
                'Original Order Date',
                'Recipient ID',
                'Recipient First Name',
                'Recipient Surname',
                'Delivery Method',
                'Recipient Email Address',
                'Recipient Phone Number',
                'Product Code',
                'Gift Card Name',
                'Gift Card Value',
                'Quantity',
                'Item PO Number',
                'Personalisation',
                'Subject Line',
                'Message',
                'Scheduled Delivery Date/Time'
            ];
            $cleaned_csv_headers = array_filter($csv_data['headers'], function ($header) {
                return trim($header) !== '';
            });
            // $csv_header_count = count($cleaned_csv_headers) - 1;
            // Check if first column is "No" (case-insensitive)
            $isNoColumn = strtolower(trim($cleaned_csv_headers[0])) === 'no';
            $csv_header_count = count($cleaned_csv_headers) - ($isNoColumn ? 1 : 0);

            $template_header_count = count($template_headers);
            if ($csv_header_count < $template_header_count) {
                // echo 'Count Is : '.$csv_header_count;
                // echo 'Required Count Is : '.$template_header_count;

                wp_send_json_error(['message' => 'Invalid File: CSV has an invalid count of headers, please download the template for reference.']);
            }

            $header_mapping = compare_headers($template_headers, $csv_data['headers']);
            $all_matched = !in_array('', $header_mapping);

            // if (!$all_matched) {
            //     wp_send_json_error(['message' => 'Invalid File. Headers do not match the required format.']);
            // }

            wp_send_json_success([
                'message' => 'File uploaded and parsed successfully!',
                'csv_data' => $csv_data,
                'template_headers' => $template_headers,
                'header_mapping' => $header_mapping,
                'all_matched' => $all_matched,
            ]);

        } else {
            wp_send_json_error(['message' => 'Error while uploading the file.']);
        }
    } else {
        wp_send_json_error(['message' => 'No file uploaded or there was an error.']);
    }
}


function parse_csv_file($file_path)
{
    $csv_data = [];
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        $csv_data['headers'] = fgetcsv($handle);

        $csv_data['data'] = [];
        while (($row = fgetcsv($handle)) !== FALSE) {
            // Check if all columns in the row are empty
            $is_empty_row = true;
            foreach ($row as $cell) {
                if (trim($cell) !== '') {
                    $is_empty_row = false;
                    break;
                }
            }

            // Only add row if it's not completely empty
            if (!$is_empty_row) {
                $csv_data['data'][] = $row;
            }
        }
        fclose($handle);
    }
    return $csv_data;
}

function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function compare_headers($template_headers, $csv_headers)
{
    $mapping = [];
    foreach ($template_headers as $template_header) {
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




// function enqueue_select2_script()
// {
//     wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css');
//     wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js', array('jquery'), null, true);
// }
// add_action('wp_enqueue_scripts', 'enqueue_select2_script');




function upload_product_gallery_images($product_id)
{
    gcp_require_admin_ajax();
    if (!isset($_FILES['images']) || !function_exists('wp_handle_upload')) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $uploaded_images = [];

    foreach ($_FILES['images']['name'] as $key => $value) {
        if ($_FILES['images']['error'][$key] == 0) {
            $file = [
                'name' => $_FILES['images']['name'][$key],
                'type' => $_FILES['images']['type'][$key],
                'tmp_name' => $_FILES['images']['tmp_name'][$key],
                'error' => $_FILES['images']['error'][$key],
                'size' => $_FILES['images']['size'][$key],
            ];

            $upload = wp_handle_upload($file, ['test_form' => false]);

            if (!isset($upload['error'])) {
                $attachment = [
                    'post_mime_type' => $upload['type'],
                    'post_title' => sanitize_file_name($file['name']),
                    'post_content' => '',
                    'post_status' => 'inherit',
                ];

                $attach_id = wp_insert_attachment($attachment, $upload['file'], $product_id);
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);

                $uploaded_images[] = $attach_id;
            }
        }
    }

    if (!empty($uploaded_images)) {
        $existing_gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $existing_gallery = !empty($existing_gallery) ? explode(',', $existing_gallery) : [];
        $existing_gallery = array_merge($existing_gallery, $uploaded_images);

        update_post_meta($product_id, '_product_image_gallery', implode(',', $existing_gallery));
    }
}

add_action('wp_ajax_upload_product_gallery_images', 'upload_product_gallery_images');

function upload_product_image_from_url()
{
    gcp_require_admin_ajax();
    if (!isset($_POST['image_url']) || empty($_POST['image_url'])) {
        wp_send_json_error("No URL provided.");
    }

    $image_url = esc_url_raw($_POST['image_url']);
    $product_id = get_the_ID(); // Get the correct product ID
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $media_id = media_sideload_image($image_url, $product_id, null, 'id');

    if (is_wp_error($media_id)) {
        wp_send_json_error("Error uploading image.");
    }

    update_post_meta($product_id, '_product_image_gallery', $media_id, true);

    wp_send_json_success([
        'id' => $media_id,
        'url' => wp_get_attachment_url($media_id),
    ]);
}
add_action('wp_ajax_upload_product_image_from_url', 'upload_product_image_from_url');
// function my_plugin_enqueue_scripts() {
//     wp_enqueue_script('my-custom-script', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), null, true);
//     wp_localize_script('my-custom-script', 'my_ajax_obj', array(
//         'nonce' => wp_create_nonce('wp_rest')
//     ));
// }
// add_action('wp_enqueue_scripts', 'my_plugin_enqueue_scripts');

function my_plugin_enqueue_scripts()
{
    // wp_enqueue_script('custom-upload-script', get_template_directory_uri() . '/js/upload.js', ['jquery'], null, true);
    wp_localize_script('custom-upload-script', 'my_ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
}
add_action('wp_enqueue_scripts', 'my_plugin_enqueue_scripts');
add_action('wp_ajax_create_product_new', 'handle_create_product');

function handle_create_product()
{
    gcp_require_admin_ajax();
    if (isset($_POST['form_data'])) {
        parse_str($_POST['form_data'], $form_data);
    }
    // echo "<pre>";
    // print_r($form_data);
    // echo "</pre>";
    // exit;

    if ( ! wp_verify_nonce( $_POST['upload_image_nonce'] ?? '', 'security' ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ] );
    }

    // Validate and sanitize product title
    $product_title = isset($_POST['gift_card_title']) ? sanitize_text_field($_POST['gift_card_title']) : 'Untitled Product';

    // Create product
    $product_data = [
        'post_title' => $product_title,
        'post_type' => 'product',
        'post_status' => 'publish',
    ];

    $product_id = wp_insert_post($product_data);

    if (!$product_id) {
        wp_send_json_error(['message' => 'Failed to create product']);
    }

    // Check if files are uploaded
    if (!empty($_FILES['product_images'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $gallery_images = [];

        foreach ($_FILES['product_images']['name'] as $key => $value) {
            if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['product_images']['name'][$key],
                    'type' => $_FILES['product_images']['type'][$key],
                    'tmp_name' => $_FILES['product_images']['tmp_name'][$key],
                    'error' => $_FILES['product_images']['error'][$key],
                    'size' => $_FILES['product_images']['size'][$key],
                ];

                // Upload the image
                $upload_id = media_handle_sideload($file, $product_id);
                if (!is_wp_error($upload_id)) {
                    $gallery_images[] = $upload_id;
                }
            }
        }

        // Update product gallery if images were uploaded
        if (!empty($gallery_images)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_images));
        }
    }

    // Return the new product ID
    wp_send_json_success(['product_id' => $product_id]);
}

function search_orders()
{
    gcp_require_admin_ajax();
    check_ajax_referer('search_orders_nonce', 'security');

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($query)) {
        $args = array(
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        );
        $orders = wc_get_orders($args);
    } else {
        if (is_numeric($query)) {
            $order_id = intval($query);
            $order = wc_get_order($order_id);

            if ($order) {
                $orders = [$order];
            } else {
                $total_query = floatval($query);
                $args = array(
                    'limit' => 10,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'return' => 'objects',
                    'meta_query' => array(
                        array(
                            'key' => '_order_total',
                            'value' => $total_query,
                            'compare' => '=',
                        ),
                    ),
                );
                $orders = wc_get_orders($args);
            }
        } else {
            $args = array(
                'limit' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'objects',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_billing_first_name',
                        'value' => $query,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => '_billing_last_name',
                        'value' => $query,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => '_billing_email',
                        'value' => $query,
                        'compare' => 'LIKE',
                    ),
                ),
            );
            $orders = wc_get_orders($args);
        }
    }

    if ($orders) {
        foreach ($orders as $order) {
            echo '<tr>';
            echo '<td>#' . esc_html($order->get_id()) . '</td>';
            echo '<td>' . esc_html(wc_format_datetime($order->get_date_created())) . '</td>';
            echo '<td>' . esc_html(get_post_meta($order->get_id(), 'client_reference', true) ?: '-') . '</td>';
            echo '<td>' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '</td>';
            echo '<td><span class="status-' . esc_attr($order->get_status()) . '">' . esc_html(ucfirst($order->get_status())) . '</span></td>';
            echo '<td>' . esc_html(get_post_meta($order->get_id(), 'invoice_number', true) ?: '-') . '</td>';
            echo '<td>AUD ' . esc_html($order->get_total()) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7">No orders found.</td></tr>';
    }

    wp_die();
}


add_action('wp_ajax_search_orders', 'search_orders');
function enqueue_manual_order_script()
{
    //if(is_page_template('manual-order.php')){
    wp_enqueue_script('order-export-script', get_template_directory_uri() . '/assets/js/export-orders.js', array('jquery'), time(), true);
    wp_enqueue_script('brands-export-script', get_template_directory_uri() . '/assets/js/export-brands.js', array('jquery'), time(), true);
    wp_localize_script('order-export-script', 'export_orders_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('export_orders_nonce')
    ));
    wp_localize_script('brands-export-script', 'export_brands_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('export_brands_nonce')
    ));
    //}
    global $wpdb;
    $mysql_time = $wpdb->get_var("SELECT DATE_FORMAT(NOW(), '%Y-%m-%dT%H:%i:%s')");
    // $mysql_time = $wpdb->get_var("SELECT DATE_FORMAT(CONVERT_TZ(NOW(), 'UTC', 'Australia/Sydney'), '%Y-%m-%dT%H:%i:%s')");

    wp_enqueue_script('manual-order', get_template_directory_uri() . '/assets/js/manual-order.js', array('jquery', 'datatable-js'), time(), true);
    wp_localize_script('manual-order', 'user_fetch_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'server_time' => $mysql_time,
        'siteUrl' => home_url('/'),
    ));

    wp_localize_script('manual-order', 'ajaxData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('search_orders_nonce')
    ));

    // Specific AJAX object for user search
    wp_localize_script('manual-order', 'userSearchAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('user_search_nonce')
    ));

    // Specific AJAX object for user search
    wp_localize_script('manual-order', 'validateGiftCard', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('validate_gift_card_nonce')
    ));
    wp_localize_script('manual-order', 'draft_order_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('draft_order_ajax_nonce'),
    ));
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css');

}
add_action('wp_enqueue_scripts', 'enqueue_manual_order_script');



// function enqueue_bulk_order_scripts() {
//     // Enqueue jQuery (if not already loaded by WordPress)

//     // Enqueue your custom script
//     wp_enqueue_script(
//         'bulk-order',
//         get_template_directory_uri() . '/assets/js/bulk-upload-order.js',
//         array('jquery'), // Dependencies
//         time(), // Version (use time() for development, replace with a static version for production)
//         true // Load in footer
//     );

//     // Localize script for AJAX URL
//     wp_localize_script(
//         'bulk-order',
//         'BulkAjax',
//         array(
//             'ajaxurl' => admin_url('admin-ajax.php'),
//         )
//     );
// }
// add_action('wp_enqueue_scripts', 'enqueue_bulk_order_scripts');
// function pre($val)
// {
//     echo '<pre>';
//     print_r($val);
//     echo '</pre>';
// }
// function get_woocommerce_product_suggestions() {
//     if (!current_user_can('manage_options')) {
//         wp_send_json_error('Unauthorized access');
//         wp_die();
//     }

//     $products = wc_get_products(array('limit' => -1));
//     $product_names = [];

//     foreach ($products as $product) {
//         $product_names[] = $product->get_name();
//     }

//     wp_send_json_success($product_names);
// }
// add_action('wp_ajax_get_woocommerce_product_suggestions', 'get_woocommerce_product_suggestions');
// function include_jquery_ui() {
//     wp_enqueue_script('jquery-ui-autocomplete');
// }
// add_action('wp_enqueue_scripts', 'include_jquery_ui');


function load_orders_ajax()
{
    gcp_require_admin_ajax();
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $orders_per_page = 3; // Updated to 3 per page
    $offset = ($page - 1) * $orders_per_page;

    $args = array(
        'limit' => $orders_per_page,
        'offset' => $offset,
        'orderby' => 'date',
        'order' => 'DESC',
        'return' => 'objects',
    );

    $orders = wc_get_orders($args);

    foreach ($orders as $order):
        ?>
        <tr>
            <td>#<?php echo esc_html($order->get_id()); ?></td>
            <td><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td>
            <td><?php echo esc_html(get_post_meta($order->get_id(), 'client_reference', true) ?: '-'); ?></td>
            <td><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></td>
            <td>
                <span class="status-<?php echo esc_attr($order->get_status()); ?>">
                    <?php echo esc_html(ucfirst($order->get_status())); ?>
                </span>
            </td>
            <td><?php echo esc_html(get_post_meta($order->get_id(), 'invoice_number', true) ?: '-'); ?></td>
            <td>AUD <?php echo esc_html($order->get_total()); ?></td>
        </tr>
        <?php
    endforeach;

    wp_die();
}

add_action('wp_ajax_load_orders', 'load_orders_ajax');

function search_users()
{
    gcp_require_admin_ajax();
    global $wpdb;

    $query = sanitize_text_field($_POST['query']);
    $business_user_id = intval($_POST['business_user_id']);

    $results = $wpdb->get_results($wpdb->prepare("
        SELECT u.ID, u.display_name, u.user_email, 
               first_name.meta_value AS first_name, 
               last_name.meta_value AS last_name, 
               phone.meta_value AS phone
        FROM $wpdb->users u
        LEFT JOIN $wpdb->usermeta AS first_name ON u.ID = first_name.user_id AND first_name.meta_key = 'first_name'
        LEFT JOIN $wpdb->usermeta AS last_name ON u.ID = last_name.user_id AND last_name.meta_key = 'last_name'
        LEFT JOIN $wpdb->usermeta AS phone ON u.ID = phone.user_id AND phone.meta_key = 'billing_phone'
        LEFT JOIN $wpdb->usermeta AS assigned ON u.ID = assigned.user_id AND assigned.meta_key = 'assigned_business_user'
        WHERE assigned.meta_value = %d
        AND (
            u.ID LIKE %s
            OR u.display_name LIKE %s
            OR u.user_email LIKE %s
            OR first_name.meta_value LIKE %s
            OR last_name.meta_value LIKE %s
            OR phone.meta_value LIKE %s
        )
    ", $business_user_id, "%$query%", "%$query%", "%$query%", "%$query%", "%$query%", "%$query%"));

    if ($results) {
        foreach ($results as $user) {
            echo "<li class='dropdown-item search-item' 
                      data-id='{$user->ID}' 
                      data-name='{$user->display_name}' 
                      data-firstname='{$user->first_name}'
                      data-lastname='{$user->last_name}'
                      data-email='{$user->user_email}' 
                      data-phone='{$user->phone}'>
                    {$user->first_name} {$user->last_name} - {$user->user_email}
                  </li>";
        }
    } else {
        echo "<li class='dropdown-item'>No results found.</li>";
    }

    wp_die();
}


add_action("wp_ajax_search_users", "search_users");

// Handle product search by SKU
function handle_product_search_by_sku()
{
    gcp_require_admin_ajax();
    global $wpdb;

    $sku = sanitize_text_field($_POST['sku']);

    // Fetch the product by SKU
    $query = $wpdb->prepare("
        SELECT p.ID as product_id, p.post_title as product_name, pm.meta_value as sku
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND pm.meta_key = '_sku'
        AND pm.meta_value = %s
    ", $sku);

    $product = $wpdb->get_row($query);

    if ($product) {
        // Check if the searched product SKU is used in any other product's parent_sku field
        $related_products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'parent_sku',
                    'value' => $product->sku,
                    'compare' => '='
                ]
            ],
            'fields' => 'ids'
        ]);

        $is_specific_product = !empty($related_products);

        $related_skus = [];
        if ($is_specific_product) {
            // Fetch SKUs of related products
            foreach ($related_products as $related_product_id) {
                $related_sku = get_post_meta($related_product_id, '_sku', true);
                if ($related_sku) {
                    $related_skus[] = $related_sku;
                }
            }
        }

        wp_send_json_success([
            'image' => wp_get_attachment_url(get_post_thumbnail_id($product->product_id)),
            'title' => $product->product_name,
            'card_id' => $product->sku,
            'is_specific_product' => $is_specific_product,
            'related_skus' => implode(', ', $related_skus)
        ]);
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_search_product_by_sku', 'handle_product_search_by_sku');
// Handle SKU list download
function handle_specific_sku_list_download()
{
    gcp_require_admin_ajax();
    global $wpdb;

    $sku = sanitize_text_field($_GET['sku']);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sku_list.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, ['Product ID', 'Product Name', 'SKU']);

    if ($sku) {
        // Fetch the searched product by SKU
        $query = $wpdb->prepare("
            SELECT p.ID as product_id, p.post_title as product_name, pm.meta_value as sku
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_sku'
            AND pm.meta_value = %s
        ", $sku);

        $product = $wpdb->get_row($query);

        if ($product) {
            // Check if the searched product SKU is used in any other product's parent_sku field
            $related_products = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => 'parent_sku',
                        'value' => $product->sku,
                        'compare' => '='
                    ]
                ],
                'fields' => 'ids'
            ]);

            if (!empty($related_products)) {
                // Only add child products (not the parent)
                foreach ($related_products as $related_product_id) {
                    $related_sku = get_post_meta($related_product_id, '_sku', true);
                    if ($related_sku) {
                        $related_product_name = get_the_title($related_product_id);
                        fputcsv($output, [$related_product_id, $related_product_name, $related_sku]);
                    }
                }
            } else {
                // For normal products (not parents)
                fputcsv($output, [$product->product_id, $product->product_name, $product->sku]);
            }
        }
    } else {
        // Download all child products (products that have a parent_sku value)
        $child_products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'parent_sku',
                    'value' => '',
                    'compare' => '!='
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ]);

        foreach ($child_products as $child_product_id) {
            $child_sku = get_post_meta($child_product_id, '_sku', true);
            if ($child_sku) {
                $child_product_name = get_the_title($child_product_id);
                fputcsv($output, [$child_product_id, $child_product_name, $child_sku]);
            }
        }
    }

    fclose($output);
    exit;
}
add_action('wp_ajax_download_specific_sku_list', 'handle_specific_sku_list_download');


function create_recipients_role()
{
    add_role(
        'recipients', // Role slug
        'Contact list user', // Display name
        array(
            'read' => true, // Allow reading posts
            'edit_posts' => false, // Disallow editing posts
            'delete_posts' => false, // Disallow deleting posts
            'publish_posts' => false, // Disallow publishing posts
            'upload_files' => false, // Disallow file uploads
        )
    );
}
add_action('init', 'create_recipients_role');


// Add custom field to user profile
// Add custom field to user profile
// 1. Enqueue Chosen library
function enqueue_admin_scripts($hook)
{
    if ('user-edit.php' === $hook || 'user-new.php' === $hook) {
        wp_enqueue_style('chosen-css', 'https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css');
        wp_enqueue_script('chosen-js', 'https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js', array('jquery'));
    }
}
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');

function get_all_business()
{
    $business_users = get_users(array(
        'role' => 'business_user',
        'orderby' => 'display_name',
        'order' => 'ASC',
        // remove 'fields' => array('ID') so WP_User objects are returned
    ));

    $ids = wp_list_pluck($business_users, 'ID');

    $business_user_array = array_combine(
        $ids,
        array_map(function ($id) {
            return get_user_meta($id, 'business_name', true);
        }, $ids)
    );

    return $business_user_array;
}

// 2. Modified custom field function
function add_recipients_custom_field($user)
{
    $business_users = array();
    $selected_business_users = array();
    $user_role = '';

    $business_users = get_users(array(
        'role' => 'business_user',
        'orderby' => 'display_name',
        'order' => 'ASC',
    ));

    $all_business = get_all_business();

    $selected_business_users = get_user_meta($user->ID, 'assigned_business_user', true);
    $user_role = isset($user->roles[0]) ? $user->roles[0] : '';

    /*if (isset($user) && $user->ID > 0 && !empty($user->ID)) {
        $selected_business_users = get_user_meta($user->ID, 'assigned_business_user', true);
    }*/
    ?>
    <table class="form-table">
        <?php
        $show_field_for_roles = ['external_business_admin', 'external_business_viewer', 'recipients'];
        ?>
        <tr id="business_user_dropdown" <?php echo in_array($user_role, $show_field_for_roles) ? '' : 'style="display:none;"'; ?>>
            <th><label for="assigned_business_user">Assign to Business Users</label></th>
            <td>
                <select name="assigned_business_user" id="assigned_business_user" class="chosen-select"
                    data-current-business="<?php echo $user->ID; ?>" style="width: 350px;">
                    <option value="Select a business user">Select a business user</option>
                    <?php if (isset($all_business) && !empty($all_business)) {
                        foreach ($all_business as $business_key => $business_user): ?>
                            <?php $selected = ($business_key == $selected_business_users) ? ' SELECTED' : ''; ?>
                            <option value="<?php echo $business_key; ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($business_user); ?>
                            </option>
                        <?php endforeach;
                    } ?>
                </select>
                <p class="description">Search and select business users</p>
            </td>
        </tr>
    </table>

    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('#assigned_business_user').chosen({
                width: '100%',
                search_contains: true,
                placeholder_text_multiple: 'Select business users',
                allow_single_deselect: true
            });

            // Role change handler
            $('#role').change(function () {
                const allowedRoles = ['external_business_admin', 'external_business_viewer', 'recipients'];
                if (allowedRoles.includes($(this).val())) {
                    $('#business_user_dropdown').show();
                    $('#assigned_business_user').trigger('chosen:updated');
                } else {
                    $('#business_user_dropdown').hide();
                }
            }).trigger('change');
        });
    </script>
    <?php
}
add_action('show_user_profile', 'add_recipients_custom_field');
add_action('edit_user_profile', 'add_recipients_custom_field');
add_action('user_new_form', 'add_recipients_custom_field');

// 3. Save function (remains the same)
function save_recipients_custom_field($user_id)
{
    if (!current_user_can('edit_user', $user_id))
        return;

    delete_user_meta($user_id, 'assigned_business_user');
    if (!empty($_POST['assigned_business_user'])) {
        $business_user_id = intval($_POST['assigned_business_user']);
        if ($business_user_id) {
            update_user_meta($user_id, 'assigned_business_user', $business_user_id);
        }
    }
}
add_action('personal_options_update', 'save_recipients_custom_field');
add_action('edit_user_profile_update', 'save_recipients_custom_field');
add_action('user_register', 'save_recipients_custom_field');




// Add Billing Phone Field to the "Add New User" Page
function add_billing_phone_field($user)
{
    $billing_phone = get_user_meta($user->ID, 'billing_phone', true);
    ?>
    <h3>Billing Information</h3>
    <table class="form-table">
        <tr>
            <th><label for="billing_phone">Billing Phone</label></th>
            <td>
                <input type="text" name="billing_phone" id="billing_phone" value="<?php echo esc_attr($billing_phone); ?>"
                    class="regular-text" /><br>
                <span class="description">Enter the user's billing phone number.</span>
            </td>
        </tr>
    </table>
    <?php
}
// add_action('user_new_form', 'add_billing_phone_field'); // Show on Add New User
// add_action('show_user_profile', 'add_billing_phone_field'); // Show on Profile Edit
// add_action('edit_user_profile', 'add_billing_phone_field'); // Show on Admin Edit User

// Save Billing Phone when User is Created or Updated
function save_billing_phone_field($user_id)
{
    if (isset($_POST['billing_phone'])) {
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['billing_phone']));
    }
}
// add_action('user_register', 'save_billing_phone_field'); // Save for new users
// add_action('personal_options_update', 'save_billing_phone_field'); // Save for profile update
// add_action('edit_user_profile_update', 'save_billing_phone_field'); // Save for admin edit user



function enqueue_custom_scripts_owl()
{
    $ajax_url  = admin_url('admin-ajax.php');
    $style_uri = get_stylesheet_directory_uri();

    wp_enqueue_script('jquery');


    // Enqueue Owl Carousel CSS
    wp_enqueue_style('giftcard-owl-carousel-css', get_template_directory_uri() . '/assets/css/owl-carousel.css', array(), time());

    // Enqueue Owl Carousel JS, depends on jQuery
    wp_enqueue_script('giftcard-owl-carousel-js', get_template_directory_uri() . '/assets/js/owl-carousel.js', array('jquery'), time(), true);


    wp_enqueue_script(
        'customisation-js',
        get_template_directory_uri() . '/assets/js/customisation.js',
        array('jquery'),
        time(),
        true
    );

    wp_enqueue_script(
        'order-summary-js',
        get_template_directory_uri() . '/assets/js/order-summary.js',
        array('jquery'),
        time(),
        true
    );

    // Commented on 20260126
    // wp_localize_script('customisation-js', 'image_personalisation', [
    //     'ajax_url' => admin_url('admin-ajax.php'),
    // ]);

    // wp_localize_script('customisation-js', 'gift_card_ajax', [
    //     'ajax_url' => admin_url('admin-ajax.php'),
    //     'nonce' => wp_create_nonce('gift_card_nonce')
    // ]);
    // // Localize script to pass AJAX URL and security nonce
    // wp_localize_script('customisation-js', 'customAjax', array(
    //     'ajaxurl' => admin_url('admin-ajax.php'), // WordPress AJAX URL
    //     'nonce' => wp_create_nonce('custom_ajax_nonce') // Security nonce
    // ));

    wp_localize_script(
        'customisation-js',
        'customisationData',
        [
            'ajaxUrl'  => $ajax_url,
            'styleUri' => $style_uri,
            'nonces'   => [
                'giftCard' => wp_create_nonce('gift_card_nonce'),
                'custom'   => wp_create_nonce('custom_ajax_nonce'),
            ],
        ]
    );

    // If order-summary.js also needs these globals, keep them available there too.
    wp_localize_script('order-summary-js', 'customAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('custom_ajax_nonce')
    ));
}

add_action('wp_enqueue_scripts', 'enqueue_custom_scripts_owl');
function send_test_email_callback()
{
    gcp_require_admin_ajax();
    check_ajax_referer( 'custom_ajax_nonce', 'nonce' );

    $checked_recipients = isset($_POST['checked_recipients']) ? $_POST['checked_recipients'] : array();
    $unchecked_recipients = isset($_POST['unchecked_recipients']) ? $_POST['unchecked_recipients'] : array();
    $subject = sanitize_text_field($_POST['subject']);
    $message = wp_kses_post( $_POST['message'] );
    $sender_name = sanitize_text_field($_POST['sender_name']);
    $sender_email = sanitize_email($_POST['sender_email']);

    if (empty($checked_recipients) && empty($unchecked_recipients)) {
        wp_send_json_error("No recipient data provided.");
    }

    if (!is_email($sender_email)) {
        wp_send_json_error("Invalid sender email.");
    }

    $admin_email = get_option('admin_email');

    // Process checked gift cards
    if (!empty($checked_recipients)) {
        $checked_subject = $subject;
        $checked_message = $message;
        send_email_to_admin($checked_recipients, $checked_subject, $checked_message, $sender_name, $sender_email, $admin_email);
    }

    // Process unchecked gift cards
    if (!empty($unchecked_recipients)) {
        $unchecked_subject = "You received gift cards";
        $unchecked_message = "Congo User!";
        send_email_to_admin($unchecked_recipients, $unchecked_subject, $unchecked_message, $sender_name, $sender_email, $admin_email);
    }

    wp_send_json_success("Emails sent successfully.");
}

function send_email_to_admin($recipients, $subject, $message, $sender_name, $sender_email, $admin_email)
{
    $email_content = "<p><strong>Sender:</strong> {$sender_name} (<a href='mailto:{$sender_email}'>{$sender_email}</a>)</p>";
    $email_content .= "<p>{$message}</p><br>";
    $email_content .= "<h3>Gift Card Distribution:</h3>";

    foreach ($recipients as $recipient) {
        $email = sanitize_email($recipient['email']);
        $gift_cards = $recipient['gift_cards'];

        $email_content .= "<h4>Recipient: {$email}</h4>";

        foreach ($gift_cards as $gift_card) {
            $price = sanitize_text_field($gift_card['price']);
            $image = esc_url($gift_card['image']);

            $email_content .= "<p><strong>Gift Card Price:</strong> {$price}</p>";
            $email_content .= "<p><img src='{$image}' style='max-width:200px; height:auto;'></p><hr>";
        }
    }

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        "From: {$sender_name} <{$sender_email}>"
    );

    wp_mail($admin_email, $subject, $email_content, $headers);
}

add_action('wp_ajax_send_test_email', 'send_test_email_callback');

function send_test_text_callback()
{
    gcp_require_admin_ajax();
    check_ajax_referer('custom_ajax_nonce', 'nonce');

    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $sender_name = isset($_POST['sender_name']) ? sanitize_text_field($_POST['sender_name']) : '';

    // echo'<pre>';
    // print_r($sender_name);
    // echo'</pre>';
    // exit;
    if (empty($phone)) {
        wp_send_json_error("Phone number is required.");
    }

    if (empty($message)) {
        wp_send_json_error("Message is required.");
    }

    if (!function_exists('send_sms_via_smsbroadcast')) {
        require_once get_template_directory() . '/inc/sms-functions.php';
    }

    // Remove double quotes and backward slashes from message
    $message = wp_strip_all_tags($message);
    $message = str_replace('"', '', $message);
    $message = str_replace('&quot;', '', $message);
    $message = wp_unslash($message);

    // Validate message length
    if (strlen($message) > 160) {
        wp_send_json_error("Message exceeds 160 characters. Please reduce the message length.");
    }

    // Send SMS to the provided phone number
    $sms_result = send_sms_via_smsbroadcast($phone, $message);

    if ($sms_result && isset($sms_result['success']) && $sms_result['success']) {
        wp_send_json_success("Test text sent successfully to " . $phone . ".");
    } else {
        $error_msg = isset($sms_result['message']) ? $sms_result['message'] : "Failed to send test text. Please check the phone number.";
        wp_send_json_error($error_msg);
    }
}
add_action('wp_ajax_send_test_text', 'send_test_text_callback');

add_action('wp_ajax_check_image_personalisation', 'check_image_personalisation');
add_action('wp_ajax_nopriv_check_image_personalisation', 'check_image_personalisation');
function check_image_personalisation()
{
    check_ajax_referer( 'custom_ajax_nonce', 'security' );

    if (!isset($_POST['sku'])) {
        wp_send_json_error(['message' => 'SKU not provided']);
    }

    $sku = sanitize_text_field($_POST['sku']);
    $product_id = wc_get_product_id_by_sku($sku);

    if (!$product_id) {
        wp_send_json_error(['message' => 'Invalid SKU']);
    }

    $image_personalisation = get_field('buyer_upload', $product_id);



    $is_checked = ($image_personalisation === 'Yes');
    $value = $is_checked ? 'Yes' : 'No';

    wp_send_json_success([
        'is_checked' => $is_checked,
        'value' => $value,
    ]);

    wp_send_json_success([
        'is_checked' => $is_checked,
        'value' => $image_personalisation,
    ]);
}




add_action('wp_ajax_upload_gift_card_image', 'upload_gift_card_image_callback');

function upload_gift_card_image_callback()
{
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Authentication required' ] );
    }

    if ( ! isset( $_FILES['file'] ) || ! check_ajax_referer( 'gift_card_nonce', '_wpnonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed' ] );
    }

    $allowed_mime_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
    $file_mime = mime_content_type( $_FILES['file']['tmp_name'] );
    if ( ! in_array( $file_mime, $allowed_mime_types, true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid file type' ] );
    }

    $file = $_FILES['file'];
    $filename = sanitize_file_name($file['name']);

    $existing = get_page_by_title($filename, OBJECT, 'attachment');

    if ($existing) {
        $url = wp_get_attachment_url($existing->ID);
        wp_send_json_success(['url' => $url]);
    }

    $upload = wp_handle_upload($file, ['test_form' => false]);

    if ($upload && !isset($upload['error'])) {
        $attachment_id = wp_insert_attachment([
            'guid' => $upload['url'],
            'post_mime_type' => $upload['type'],
            'post_title' => $filename,
            'post_content' => '',
            'post_status' => 'inherit'
        ], $upload['file']);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        wp_send_json_success(['url' => $upload['url']]);
    } else {
        wp_send_json_error(['message' => 'Upload failed']);
    }
}

add_action('wp_ajax_save_selected_gift_card_image', 'save_selected_gift_card_image');
add_action('wp_ajax_nopriv_save_selected_gift_card_image', 'save_selected_gift_card_image');

function save_selected_gift_card_image() {
    check_ajax_referer( 'gift_card_nonce', '_wpnonce' );

    if ( defined('DOING_AJAX') && DOING_AJAX ) {
        wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
    }

    if ( empty($_POST['image']) || empty($_POST['cart_item_key']) ) {
        wp_send_json_error(['message' => 'Missing data']);
    }

    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $image         = $_POST['image'];

    if ( strpos($image, 'data:image') !== 0 ) {
        $image = esc_url_raw($image);
    }

    if ( ! WC()->cart ) {
        wp_send_json_error(['message' => 'Cart not available']);
    }

    $cart = WC()->cart->get_cart();

    if ( isset($cart[$cart_item_key]) ) {
        WC()->cart->cart_contents[$cart_item_key]['selected_gift_card_image'] = $image;
        WC()->cart->cart_contents[$cart_item_key]['card_design']              = $image;
        WC()->cart->set_session();
        if ( WC()->session ) {
            WC()->session->set( 'gc_card_design_' . $cart_item_key, $image );
        }
    }

    wp_send_json_success();
    wp_die();
}



add_filter('woocommerce_add_cart_item_data', 'add_gift_card_image_to_cart', 10, 2);


function add_gift_card_image_to_cart($cart_item_data, $product_id) {
    // When card_design is already set (e.g. from single product AJAX add-to-cart), use it as-is.
    // Do not overwrite with session so each cart line keeps its own selection (or no image).
    if (array_key_exists('card_design', $cart_item_data)) {
        $cart_item_data['selected_gift_card_image'] = isset($cart_item_data['selected_gift_card_image'])
            ? $cart_item_data['selected_gift_card_image']
            : $cart_item_data['card_design'];
        return $cart_item_data;
    }
    $image = null;
    $images = WC()->session->get('gift_card_images', []);
    if (isset($images[$product_id])) {
        $image = $images[$product_id];
    }
    if (empty($image) && WC()->session) {
        $image = WC()->session->get('gc_card_design_' . $product_id, '');
    }
    if (!empty($image)) {
        $cart_item_data['selected_gift_card_image'] = $image;
        $cart_item_data['card_design'] = $image;
    }
    return $cart_item_data;
}


add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    $img = $cart_item['card_design'] ?? $cart_item['selected_gift_card_image'] ?? '';
    if (!empty($img)) {
        $src = (strpos($img, 'data:image') === 0) ? esc_attr($img) : esc_url($img);
        $item_data[] = [
            'name'  => __('Card Design'),
            'value' => '<img src="' . $src . '" style="max-width:60px;height:auto;" alt="" />'
        ];
    }
    return $item_data;
}, 10, 2);

add_filter('woocommerce_get_item_data', 'remove_giftcard_meta_from_review_table', 10, 2);
function remove_giftcard_meta_from_review_table($item_data, $cart_item) {

    if (is_checkout()) {
        return array(); // removes ALL variation/meta data in checkout review table
    }

    return $item_data;
}

//add_action('woocommerce_review_order_before_payment', 'custom_stripe_form_placement');

function custom_stripe_form_placement() {
    // Check if Stripe is enabled
    $gateways = WC()->payment_gateways->get_available_payment_gateways();
    if (isset($gateways['stripe'])) {
        echo '<div id="custom-stripe-container">';
        echo '<h3>Payment Details</h3>';
        // This triggers the Stripe Elements fields
        $gateways['stripe']->payment_fields(); 
        echo '</div>';
    }
}

add_action('wp_enqueue_scripts', function() {
    if (is_checkout()) {
        wp_enqueue_script('wc-stripe-elements');
    }
});
add_filter('wc_stripe_elements_styling', 'modify_stripe_element_styles');

function modify_stripe_element_styles($styles) {
    return array(
        'individual' => array(
            'base' => array(
                'color'          => '#32325d',
                'fontWeight'     => '500',
                'fontFamily'     => 'Roboto, Open Sans, Segoe UI, sans-serif',
                'fontSize'       => '16px',
                'fontSmoothing'  => 'antialiased',
                '::placeholder'  => array(
                    'color' => '#aab7c4',
                ),
            ),
            'invalid' => array(
                'color'    => '#fa755a',
                'iconColor' => '#fa755a',
            ),
        ),
    );
}






add_action('wp_ajax_get_recipient_details_by_emails', 'get_recipient_details_by_emails_callback');

function get_recipient_details_by_emails_callback()
{
    gcp_require_admin_ajax();
    global $wpdb;

    $recipient_ids = isset($_POST['recipient_ids']) ? (array) $_POST['recipient_ids'] : [];
    $recipient_emails = isset($_POST['recipient_emails']) ? (array) $_POST['recipient_emails'] : [];
    $recipient_products = isset($_POST['recipient_products']) ? (array) $_POST['recipient_products'] : [];
    $only = isset($_POST['only']) ? $_POST['only'] : 'not';
    $recipient_details_map = [];
    $product_errors = [];

    foreach ($recipient_emails as $key => $email) {
        $user = get_user_by('email', sanitize_email($email));
        $user_recipient_id = ($recipient_ids[$key]) ? $recipient_ids[$key] : 0;
        $user_by_id = get_user_by('ID', (int) $user_recipient_id);

        $sku = sanitize_text_field($recipient_products[$key]['sku']);
        $gift_card_name = sanitize_text_field($recipient_products[$key]['gift_card_name']);
        $gift_card_value = floatval($recipient_products[$key]['gift_card_value']);

        $product_id = wc_get_product_id_by_sku($sku);
        //$product_errors = wc_get_product_id_by_sku($sku);

        if (!$product_id) {
            $product_errors[$key][8] = [
                'colIndex' => 8,
                'field' => 'sku',
                'message' => 'Invalid Product Code/SKU'
            ];
        } else {
            $wc_product = wc_get_product($product_id);
            if (!$wc_product) {
                $product_errors[$key][8] = [
                    'colIndex' => 8,
                    'field' => 'sku',
                    'message' => 'Product not found'
                ];
                continue;
            }

            // Validate product name
            $expected_name = $wc_product->get_name();
            // echo '$expected_name: '.$expected_name;
            // echo '$expected_name: '.$gift_card_name;
            if ($gift_card_name !== $expected_name) {
                $product_errors[$key][9] = [
                    'colIndex' => 9,
                    'field' => 'gift_card_name',
                    'message' => 'Incorrect Product Name. Expected: ' . $expected_name
                ];
            }

            // Validate gift card value
            $expected_price = floatval($wc_product->get_price());
            if (abs($gift_card_value - $expected_price) > 0.01) { // Allow for floating point precision
                $product_errors[$key][10] = [
                    'colIndex' => 10,
                    'field' => 'gift_card_value',
                    'message' => 'Incorrect Gift Card Value.'
                ];
            }

        }

        /*if (!$user) {
            continue; // Skip if user not found
        }*/


        $cont_flag = 0;
        if (!$user) {
            $cont_flag++; // Skip if user not found
        }
        if (empty($user_by_id)) {
            $cont_flag++; // Skip if user not found
        }

        if ($cont_flag > 1) {
            $recipient_details_map[$key] = [];
            continue;
        }

        if ($cont_flag == 0) {
            $recipient_details_map[$key] = [
                'csv_user_id' => $user_recipient_id,
                'user_by_id' => $user_by_id->ID,
                'email_by_id' => $user_by_id->user_email,
                'user_id' => $user->ID,
                'first_name' => $user->first_name,
                'email' => $user->user_email,
                'phone' => get_user_meta($user->ID, 'mobile', true),
                'assigned_business_user' => get_user_meta($user->ID, 'assigned_business_user', true),
                'last_name' => get_user_meta($user->ID, 'last_name', true),
            ];
        } else if ($user && !$user_by_id) {
            $recipient_details_map[$key] = [
                'csv_user_id' => $user_recipient_id,
                'user_by_id' => 0,
                'email_by_id' => '',
                'user_id' => $user->ID,
                'first_name' => $user->first_name,
                'email' => $user->user_email,
                'phone' => get_user_meta($user->ID, 'mobile', true),
                'assigned_business_user' => get_user_meta($user->ID, 'assigned_business_user', true),
                'last_name' => get_user_meta($user->ID, 'last_name', true),
            ];
        } else if (!$user && $user_by_id) {
            $recipient_details_map[$key] = [
                'csv_user_id' => $user_recipient_id,
                'user_by_id' => $user_by_id->ID,
                'email_by_id' => $user_by_id->user_email,
                'user_id' => 0,
                'first_name' => '',
                'email' => sanitize_email($email),
                'phone' => '',
                'assigned_business_user' => '',
                'last_name' => ''
            ];
        }
    }
    wp_send_json_success([
        'data' => $recipient_details_map,
        'productData' => $product_errors
    ]);
    wp_die();
}


add_action('wp_ajax_validate_product_details_bulk', 'validate_product_details_bulk_callback');

function validate_product_details_bulk_callback()
{
    gcp_require_admin_ajax();
    global $wpdb;

    // Get CSV product data from the AJAX request
    $product_data = isset($_POST['product_data']) ? $_POST['product_data'] : [];
    $errors = [];

    foreach ($product_data as $index => $product) {
        $sku = sanitize_text_field($product['sku']);
        $gift_card_name = sanitize_text_field($product['gift_card_name']);
        $gift_card_value = floatval($product['gift_card_value']);

        // First check if SKU exists
        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            $errors[] = [
                'rowIndex' => (int) $product['rowIndex'],
                'field' => 'sku',
                'message' => 'Invalid Product Code/SKU'
            ];
            continue; // Skip further checks for this row if SKU is invalid
        }

        // Get product details
        $wc_product = wc_get_product($product_id);
        if (!$wc_product) {
            $errors[] = [
                'rowIndex' => (int) $product['rowIndex'],
                'field' => 'sku',
                'message' => 'Product not found'
            ];
            continue;
        }

        // Validate product name
        $expected_name = $wc_product->get_name();
        if ($gift_card_name !== $expected_name) {
            $errors[] = [
                'rowIndex' => (int) $product['rowIndex'],
                'field' => 'gift_card_name',
                'message' => 'Incorrect Product Name. Expected: ' . $expected_name
            ];
        }

        // Validate gift card value
        $expected_price = floatval($wc_product->get_price());
        if (abs($gift_card_value - $expected_price) > 0.01) { // Allow for floating point precision
            $errors[] = [
                'rowIndex' => (int) $product['rowIndex'],
                'field' => 'gift_card_value',
                'message' => 'Incorrect Gift Card Value.'
            ];
        }
    }

    wp_send_json_success([
        'errors' => $errors,
        'message' => count($errors) ? 'Validation completed with errors' : 'All products are valid'
    ]);
}

function create_jc_staff_role()
{
    // Get admin capabilities
    $admin_role = get_role('administrator');
    $admin_caps = $admin_role->capabilities;

    // Add the custom role if it doesn't exist
    if (!get_role('jc_staff')) {
        add_role('jc_staff', 'J&C staff user admin ', []);
    }

    // Assign all admin capabilities to jc_staff
    $jc_staff = get_role('jc_staff');
    if ($jc_staff && $admin_caps) {
        foreach ($admin_caps as $cap => $grant) {
            $jc_staff->add_cap($cap, $grant);
        }
    }
}
add_action('init', 'create_jc_staff_role');

function restrict_jc_staff_dashboard_access()
{
    if (current_user_can('jc_staff') && !defined('DOING_AJAX')) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('admin_init', 'restrict_jc_staff_dashboard_access');


function restrict_page_to_admins()
{
    if (is_page('manual-order') && is_page('brands-listing') && !current_user_can('administrator') && !current_user_can('jc_staff')) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('template_redirect', 'restrict_page_to_admins');


function search_product_categories()
{
    gcp_require_admin_ajax();
    // if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ajax-nonce')) {
    //     wp_send_json_error(['message' => 'Invalid nonce']);
    // }

    $term = sanitize_text_field($_POST['term']);

    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'name__like' => $term,
        'number' => 10
    ]);

    if (!empty($categories) && !is_wp_error($categories)) {
        $response = array_map(function ($category) {
            return [
                'id' => $category->term_id,
                'label' => $category->name
            ];
        }, $categories);

        wp_send_json_success($response);
    } else {
        wp_send_json_success([]);
    }
}
add_action('wp_ajax_search_product_categories', 'search_product_categories');



// Custom Statuses
// Register Custom Statuses
function register_custom_product_statuses()
{

    register_post_status('wc-deactivated', [
        'label' => _x('Deactivated', 'Product status', 'woocommerce'),
        'public' => true,
        'post_type' => ['product'],
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Deactivated <span class="count">(%s)</span>', 'Deactivated <span class="count">(%s)</span>', 'woocommerce'),
    ]);

    register_post_status('wc-closed', [
        'label' => _x('Closed', 'Product status', 'woocommerce'),
        'public' => true,
        'post_type' => ['product'],
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'woocommerce'),
    ]);

    register_post_status('wc-deleted', [
        'label' => _x('Deleted', 'Product status', 'woocommerce'),
        'public' => true,
        'post_type' => ['product'],
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Deleted <span class="count">(%s)</span>', 'Deleted <span class="count">(%s)</span>', 'woocommerce'),
    ]);
}
add_action('init', 'register_custom_product_statuses');
// Add Statuses to the Dropdown
// Add Custom Statuses to the Product Editor Dropdown
function add_custom_statuses_to_dropdown()
{
    global $post;
    if ($post->post_type === 'product') {
        $current_status = $post->post_status;
        $custom_statuses = array(
            'wc-deactivated' => 'Deactivated',
            'wc-closed' => 'Closed',
            'wc-deleted' => 'Deleted'
        );
        $statuses_json     = json_encode($custom_statuses);
        $current_status_js = esc_js($current_status);
        $current_label_js  = esc_js($custom_statuses[$current_status] ?? '');
        echo "<script>
        jQuery(document).ready(function($) {
            const statuses = {$statuses_json};
            const currentStatus = '{$current_status_js}';

            // Remove unwanted default statuses (e.g., 'Pending Review')
            $('select#post_status option[value=\"pending\"]').remove();

            // Add custom statuses to the dropdown
            $.each(statuses, function(value, label) {
                const sel = (value === currentStatus) ? ' selected=\"selected\"' : '';
                $('select#post_status').append('<option value=\"' + value + '\"' + sel + '>' + label + '</option>');
            });

            // On published products WP leaves #save-action empty (no #save-post inside it).
            // showNativeSaveDraft() injects the exact same markup WordPress uses for drafts.
            // hideNativeSaveDraft() removes it so the published state looks normal again.
            function showNativeSaveDraft(label) {
                if ($('#save-post').length) {
                    $('#save-post').val(label).closest('#save-action').show();
                    return;
                }
                var btn = $('<input type=\"submit\" name=\"save\" id=\"save-post\" value=\"' + label + '\" class=\"button\" /><span class=\"spinner\"></span>');
                $('#save-action').append(btn).show();
            }

            function hideNativeSaveDraft() {
                $('#save-action').hide();
            }

            function updatePublishButtons(selectedValue) {
                var publishBtn = $('#publish');

                if (selectedValue && statuses[selectedValue]) {
                    $('#post-status-display').text(statuses[selectedValue]);
                    publishBtn.val('Update');
                    hideNativeSaveDraft();
                } else if (selectedValue === 'draft') {
                    publishBtn.val('Publish');
                    showNativeSaveDraft('Save Draft');
                } else if (selectedValue === 'publish' || selectedValue === 'private') {
                    publishBtn.val('Update');
                    hideNativeSaveDraft();
                }
            }

            // Apply on page load
            updatePublishButtons(currentStatus);

            // Re-apply on dropdown change
            $('select#post_status').on('change', function() {
                updatePublishButtons($(this).val());
            });

            // OK button — setTimeout(0) runs after WP's own handler
            $(document).on('click', '#save-post-status', function() {
                setTimeout(function() {
                    updatePublishButtons($('select#post_status').val());
                }, 0);
            });

            // MutationObserver on #post-status-display — catches any WP-driven resets
            var displayEl = document.getElementById('post-status-display');
            if (displayEl && window.MutationObserver) {
                new MutationObserver(function() {
                    setTimeout(function() {
                        updatePublishButtons($('select#post_status').val());
                    }, 0);
                }).observe(displayEl, { childList: true, subtree: true, characterData: true });
            }
        });
        </script>";
    }
}
add_action('admin_footer-post.php', 'add_custom_statuses_to_dropdown');
add_action('admin_footer-post-new.php', 'add_custom_statuses_to_dropdown');

// Preserve custom product status on save — prevents WP from reverting to 'draft'
function preserve_custom_product_status($data, $postarr)
{
    $allowed = ['wc-awaiting-publishing', 'wc-deactivated', 'wc-closed', 'wc-deleted'];
    if (
        isset($postarr['post_type'], $postarr['post_status']) &&
        $postarr['post_type'] === 'product' &&
        in_array($postarr['post_status'], $allowed, true)
    ) {
        $data['post_status'] = $postarr['post_status'];
    }
    return $data;
}
add_filter('wp_insert_post_data', 'preserve_custom_product_status', 10, 2);
// Display Custom Statuses in the Products List
function display_custom_status_in_list($statuses)
{
    global $post;
    $custom_statuses = array(
        'wc-deactivated' => 'Deactivated',
        'wc-closed' => 'Closed',
        'wc-deleted' => 'Deleted'
    );
    if ($post->post_type === 'product' && isset($custom_statuses[$post->post_status])) {
        return array($custom_statuses[$post->post_status]);
    }
    return $statuses;
}
add_filter('display_post_states', 'display_custom_status_in_list');
// Save Custom Statuses
function save_custom_product_status($post_id)
{
    // Check if it's an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;

    // Ensure $_POST['post_type'] is set before checking
    if (!isset($_POST['post_type']) || $_POST['post_type'] !== 'product')
        return;

    $allowed_statuses = array('wc-awaiting-publishing', 'wc-deactivated', 'wc-closed', 'wc-deleted');

    // Ensure $_POST['post_status'] is set and valid
    if (isset($_POST['post_status']) && in_array($_POST['post_status'], $allowed_statuses)) {
        // Temporarily remove the hook to prevent infinite loop
        remove_action('save_post_product', 'save_custom_product_status', 10);

        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => sanitize_text_field($_POST['post_status'])
        ));

        // Re-add the hook
        add_action('save_post_product', 'save_custom_product_status', 10);
    }
}
add_action('save_post_product', 'save_custom_product_status');


// Prevent Redirect Loop
function prevent_redirect_loop($location, $post_id)
{
    $allowed_statuses = array('wc-awaiting-publishing', 'wc-deactivated', 'wc-closed', 'wc-deleted');
    if (isset($_POST['post_status']) && in_array($_POST['post_status'], $allowed_statuses)) {
        $location = add_query_arg('message', 1, get_edit_post_link($post_id, 'url'));
    }
    return $location;
}
add_filter('redirect_post_location', 'prevent_redirect_loop', 10, 2);
// Add Bulk Actions
// function add_custom_bulk_actions($bulk_actions) {
//     $bulk_actions['wc-awaiting-publishing'] = 'Mark as Awaiting Publishing';
//     $bulk_actions['wc-deactivated'] = 'Mark as Deactivated';
//     $bulk_actions['wc-closed'] = 'Mark as Closed';
//     $bulk_actions['wc-deleted'] = 'Mark as Deleted';
//     return $bulk_actions;
// }
// add_filter('bulk_actions-edit-product', 'add_custom_bulk_actions');

// Handle Bulk Actions
function handle_custom_bulk_actions($redirect_to, $action, $post_ids)
{
    $allowed_actions = array('wc-awaiting-publishing', 'wc-deactivated', 'wc-closed', 'wc-deleted');
    if (in_array($action, $allowed_actions)) {
        foreach ($post_ids as $post_id) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => $action
            ));
        }
        $redirect_to = add_query_arg('bulk_update_status', count($post_ids), $redirect_to);
    }
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-product', 'handle_custom_bulk_actions', 10, 3);


add_action('wp_ajax_process_bulk_order_data', 'process_bulk_order_data');

function process_bulk_order_data()
{
    gcp_require_admin_ajax();
    // Verify nonce for security
    if (!check_ajax_referer('bulk_order_nonce', 'security', false)) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    // Get the CSV data from the AJAX request
    $csv_data = json_decode(wp_unslash($_POST['csv_data']), true);
    $form_data = json_decode(wp_unslash($_POST['form_data']), true);

    if (empty($csv_data) || !isset($csv_data['headers']) || !isset($csv_data['data'])) {
        wp_send_json_error(['message' => 'Invalid CSV data.']);
    }


    $processed_form_data = [
        'activation_expiry_type' => $form_data['activation_expiry_type'] ?? '',
        'activation_expiry_date' => $form_data['activation_expiry_date'] ?? '',
        'activation_expiry_duration' => $form_data['activation_expiry_duration'] ?? '',
        'activation_expiry_unit' => $form_data['activation_expiry_unit'] ?? '',
        'sender_details' => $form_data['sender_details'] ?? '',
        'gift_card_image' => $form_data['gift_card_image'] ?? '',
        'apply_personalisation' => $form_data['apply_personalisation'] ?? '',
    ];


    $processed_data = [];
    // Process each row of the CSV data
    foreach ($csv_data['data'] as $row) {
        $first_name = $row[array_search('Recipient First Name', $csv_data['headers'])] ?? '';
        $client_reference = $row[array_search('Client reference', $csv_data['headers'])] ?? '';
        $original_o_date = $row[array_search('Original Order Date', $csv_data['headers'])] ?? '';
        $recipient_id = $row[array_search('Recipient ID', $csv_data['headers'])] ?? '';
        $quantity = $row[array_search('Quantity', $csv_data['headers'])] ?? '';
        $po_number = $row[array_search('Item PO Number', $csv_data['headers'])] ?? '';
        $personalisation = $row[array_search('Personalisation', $csv_data['headers'])] ?? '';
        $schedule_datetime = $row[array_search('Scheduled Delivery Date/Time', $csv_data['headers'])] ?? '';
        if (empty($schedule_datetime) || $schedule_datetime === '00-00-0000 00:00') {
            global $wpdb;
            $schedule_datetime = current_time('mysql');
        }
        $surname = $row[array_search('Recipient Surname', $csv_data['headers'])] ?? '';
        $email = $row[array_search('Recipient Email Address', $csv_data['headers'])] ?? '';
        $sku = $row[array_search('Product Code', $csv_data['headers'])] ?? '';
        $price = $row[array_search('Gift Card Value', $csv_data['headers'])] ?? '';
        $image_src = get_product_image_by_sku($sku);
        $message = $row[array_search('Message', $csv_data['headers'])] ?? '';
        $name = $row[array_search('Gift Card Name', $csv_data['headers'])] ?? '';
        $subject = $row[array_search('Subject Line', $csv_data['headers'])] ?? '';
        $textAnimation = '';
        $textMessage = '';
        $emailAnimation = '';
        $delivery_method = $row[array_search('Delivery Method', $csv_data['headers'])] ?? 'Email';
        $phone = $row[array_search('Recipient Phone Number', $csv_data['headers'])] ?? '';
        // $message = $row[array_search('Message', $csv_data['headers'])] ?? '';


        // Fetch the product image based on SKU (replace this with your logic)

        if ($email) {
            $processed_data[] = [
                'first_name' => $first_name,
                'client_reference' => $client_reference,
                'original_o_date' => $original_o_date,
                'recipient_id' => $recipient_id,
                'quantity' => $quantity,
                'po_number' => $po_number,
                'personalisation' => $personalisation,
                'schedule_datetime' => $schedule_datetime,
                'surname' => $surname,
                'email' => $email,
                'sku' => $sku,
                'price' => $price,
                'image' => $image_src,
                'message' => $message,
                'name' => $name,
                'subject' => $subject,
                'textAnimation' => $textAnimation,
                'text_message' => $textMessage,
                'emailAnimation' => $emailAnimation,
                'delivery_method' => $delivery_method,
                'phone' => $phone,
            ];
        }
    }


    if (empty($processed_data)) {
        wp_send_json_error(['message' => 'No valid data found.']);
    }
    // exit;

    wp_send_json_success([
        'form_data' => $processed_form_data,
        'rows' => $processed_data,
    ]);
}

// Helper function to fetch product image by SKU
function get_product_image_by_sku($sku)
{
    // Replace this with your logic to fetch the product image URL based on SKU
    $product_id = wc_get_product_id_by_sku($sku);
    if ($product_id) {
        return wp_get_attachment_url(get_post_thumbnail_id($product_id));
    }
    return '';
}



add_action('wp_ajax_upload_product_image_from_url', 'upload_product_image_from_url_');

function upload_product_image_from_url_()
{
    gcp_require_admin_ajax();
    if (!isset($_POST['urls']) || !isset($_POST['product_id'])) {
        wp_send_json_error(['error' => 'Missing parameters']);
    }

    $urls = json_decode(wp_unslash($_POST['urls']), true);
    $product_id = intval($_POST['product_id']);

    if (!$product_id || empty($urls)) {
        wp_send_json_error(['error' => 'Invalid data']);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $product_images = [];

    foreach ($urls as $url) {
        // Download the image to the uploads directory
        $tmp = download_url($url);

        if (is_wp_error($tmp)) {
            continue;
        }

        // Set up file array for WordPress upload
        $file_array = [
            'name' => basename($url),
            'tmp_name' => $tmp
        ];

        // Perform the upload
        $attachment_id = media_handle_sideload($file_array, 0);

        // Remove temporary file
        @unlink($tmp);

        if (!is_wp_error($attachment_id)) {
            $product_images[] = $attachment_id;
        }
    }

    if (!empty($product_images)) {
        update_post_meta($product_id, '_product_image_gallery', implode(',', $product_images));
        wp_send_json_success(['images' => $product_images]);
    } else {
        wp_send_json_error(['error' => 'No images uploaded']);
    }
}




// In functions.php or custom plugin file
// Updated AJAX handler in functions.php
add_action('wp_ajax_get_product_meta', 'get_product_meta_data');
function get_product_meta_data()
{
    gcp_require_admin_ajax();
    check_ajax_referer('delivery_ajax_nonce', 'security');

    $sku = sanitize_text_field($_POST['sku']);

    // Get product ID by SKU including variations
    $product_id = wc_get_product_id_by_sku($sku);

    // If not found, try searching in variations
    if (!$product_id) {
        $data_store = WC_Data_Store::load('product');
        $product_id = $data_store->get_product_id_by_sku($sku);
    }

    if (!$product_id) {
        wp_send_json_error(['message' => 'Product not found for SKU: ' . $sku]);
    }

    $product = wc_get_product($product_id);

    // Get meta values with validation
    $fulfillment = $product->get_meta('_supplier_fullfillment_price', true);
    $delivery = $product->get_meta('_delivery_cost', true);
    $gst = $product->get_meta('_gst', true);

    // Fallback to 0 if empty
    $fulfillment = is_numeric($fulfillment) ? (float) $fulfillment : 0;
    $delivery = is_numeric($delivery) ? (float) $delivery : 0;
    $gst = is_numeric($gst) ? (float) $gst : 0;

    wp_send_json_success([
        'fulfillment' => $fulfillment,
        'delivery' => $delivery,
        'gst' => $gst
    ]);
}


function generate_unique_gift_card_code()
{
    global $wpdb;

    do {
        $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_gift_card_number_enc' AND meta_value = %s",
                $code
            )
        );
    } while ($exists);

    return $code;
}

// add_filter('the_title', 'mask_gift_card_title_for_non_admin', 10, 2);
function mask_gift_card_title_for_non_admin($title, $post_id)
{
    // Only affect gift_card post type
    if (get_post_type($post_id) !== 'gift_card') {
        return $title;
    }

    // Show full title in admin edit screen
    if (is_admin() && get_current_screen() && get_current_screen()->base === 'post' && get_the_ID() === $post_id) {
        return $title;
    }

    // If the title is less than 4 chars, mask all of it
    if (strlen($title) <= 4) {
        return str_repeat('X', strlen($title));
    }

    // Replace first 4 characters with 'X'
    return str_repeat('X', 4) . substr($title, 4);
}
add_action('wp_ajax_get_business_user_balance', 'get_business_user_balance_callback');

function get_business_user_balance_callback()
{
    gcp_require_admin_ajax();
    $user_id = intval($_GET['user_id']);

    if (!$user_id || !get_userdata($user_id)) {
        wp_send_json_error(['message' => 'Invalid user ID']);
    }

    $balance = get_user_meta($user_id, 'float_balance', true);

    $balance = ($balance !== '' && $balance !== null) ? floatval($balance) : 0;
    // echo $balance;
    wp_send_json_success(['balance' => $balance]);
}

// function send_gift_card_email_to_recipient($recipient_email, $recipient_name, $gift_card_number, $price, $message, $sender_name, $sender_email) {
//     $subject = "You've received a gift card!";

//     $body = "
//         <p>Hi " . esc_html($recipient_name) . ",</p>
//         <p>You have received a gift card from <strong>" . esc_html($sender_name) . "</strong> (" . esc_html($sender_email) . ").</p>
//         <p><strong>Gift Card Code:</strong> " . esc_html($gift_card_number) . "</p>
//         <p><strong>Value:</strong> $" . number_format((float)$price, 2) . "</p>
//         <p><strong>Message:</strong> " . nl2br(esc_html($message)) . "</p>
//         <p>You can redeem this gift card online.</p>
//         <p>Best regards,<br>Gift Cards Plus</p>
//     ";

//     $headers = [
//         'Content-Type: text/html; charset=UTF-8',
//         'From: ' . esc_html($sender_name) . ' <' . sanitize_email($sender_email) . '>',
//     ];

//     wp_mail($recipient_email, $subject, $body, $headers);
// }

function send_combined_gift_cards_email_new($gcard)
{
    if (!empty($gcard)) {
        $sender_name = $gcard['sender_name'] ?: 'Gift Cards Plus';
        $sender_email = $gcard['sender_email'] ?: get_option('admin_email');

        $subject = "You've received gift card!";
        $body = "<p>Hi " . esc_html($gcard['recipient_name']) . ",</p>";
        $body .= "<p>You have received the following gift card(s) from <strong>" . esc_html($sender_name) . "</strong> (" . esc_html($sender_email) . "):</p>";

        $body .= "<hr>";

        if (!empty($gcard['image_url'])) {
            $body .= '<p><img src="' . esc_url($gcard['image_url']) . '" alt="Gift Card Image" style="max-width: 300px; height: auto;"></p>';
        }

        $body .= "<p><strong>Gift Card Code:</strong> " . esc_html($gcard['_gift_card_number_enc']) . "</p>";
        $body .= "<p><strong>Value:</strong> $" . number_format((float) $gcard['price'], 2) . "</p>";
        $body .= "<p><strong>Message:</strong> " . nl2br(esc_html($gcard['message'])) . "</p>";

        // Expiry Info
        // if (!empty($gcard['expiry_type'])) {
        //     $body .= "<p><strong>Activation Expiry Type:</strong> " . esc_html($gcard['expiry_type']) . "</p>";
        // }

        if (!empty($gcard['expiry_type']) && $gcard['expiry_type'] === 'Activated by a Set Date' && !empty($gcard['expiry_date'])) {
            $body .= "<p><strong>Activation Deadline:</strong> " . date('F j, Y', strtotime($gcard['expiry_date'])) . "</p>";
        }
        if (
            !empty($gcard['expiry_type']) &&
            $gcard['expiry_type'] === 'Activated within a Set Period' &&
            !empty($gcard['expiry_duration']) &&
            !empty($gcard['expiry_unit'])
        ) {
            $now = new DateTime();
            $unit = strtolower($gcard['expiry_unit']);
            $duration = intval($gcard['expiry_duration']);

            switch ($unit) {
                case 'days':
                    $now->modify("+{$duration} days");
                    break;
                case 'weeks':
                    $now->modify("+{$duration} weeks");
                    break;
                case 'months':
                    $now->modify("+{$duration} months");
                    break;
                case 'years':
                    $now->modify("+{$duration} years");
                    break;
            }

            $formatted_expiry_date = $now->format('F j, Y');
            $body .= "<p><strong>Activate Until:</strong> " . esc_html($formatted_expiry_date) . "</p>";
        }

        $body .= "<p>You can redeem these gift cards online.</p>";
        $body .= "<p>Best regards,<br>Gift Cards Plus</p>";

        $headers = [
            'Content-Type: text/html; charset=UTF-8\r\n',
            'From: ' . esc_html($sender_name) . ' <' . sanitize_email($sender_email) . '>\r\n',
        ];

        $sent = wp_mail($gcard['recipient_email'], $subject, $body, $headers);
        if ($sent) {
            // Email sent successfully
            update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Delivered');
        } else {
            // Email failed to send
            update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Failed');
        }
    }
}
// function send_combined_gift_cards_email($recipient_email, $recipient_name, $cards, $sender_name, $sender_email)
// {
//     $sender_name = $sender_name ?: 'Gift Cards Plus';
//     $sender_email = $sender_email ?: get_option('admin_email');

//     $subject = "You've received " . count($cards) . " gift card(s)!";
//     $body = "<p>Hi " . esc_html($recipient_name) . ",</p>";
//     $body .= "<p>You have received the following gift card(s) from <strong>" . esc_html($sender_name) . "</strong> (" . esc_html($sender_email) . "):</p>";

//     foreach ($cards as $card) {
//         $body .= "<hr>";

//         if (!empty($card['image_url'])) {
//             $body .= '<p><img src="' . esc_url($card['image_url']) . '" alt="Gift Card Image" style="max-width: 300px; height: auto;"></p>';
//         }

//         $body .= "<p><strong>Gift Card Code:</strong> " . esc_html($card['gift_card_number']) . "</p>";
//         $body .= "<p><strong>Value:</strong> $" . number_format((float) $card['price'], 2) . "</p>";
//         $body .= "<p><strong>Message:</strong> " . nl2br(esc_html($card['message'])) . "</p>";

//         // Expiry Info
//         // if (!empty($card['expiry_type'])) {
//         //     $body .= "<p><strong>Activation Expiry Type:</strong> " . esc_html($card['expiry_type']) . "</p>";
//         // }

//         if (!empty($card['expiry_type']) && $card['expiry_type'] === 'Activated by a Set Date' && !empty($card['expiry_date'])) {
//             $body .= "<p><strong>Activation Deadline:</strong> " . date('F j, Y', strtotime($card['expiry_date'])) . "</p>";
//         }
//         if (
//             !empty($card['expiry_type']) &&
//             $card['expiry_type'] === 'Activated within a Set Period' &&
//             !empty($card['expiry_duration']) &&
//             !empty($card['expiry_unit'])
//         ) {
//             $now = new DateTime();
//             $unit = strtolower($card['expiry_unit']);
//             $duration = intval($card['expiry_duration']);

//             switch ($unit) {
//                 case 'days':
//                     $now->modify("+{$duration} days");
//                     break;
//                 case 'weeks':
//                     $now->modify("+{$duration} weeks");
//                     break;
//                 case 'months':
//                     $now->modify("+{$duration} months");
//                     break;
//                 case 'years':
//                     $now->modify("+{$duration} years");
//                     break;
//             }

//             $formatted_expiry_date = $now->format('F j, Y');
//             $body .= "<p><strong>Activate Until:</strong> " . esc_html($formatted_expiry_date) . "</p>";
//         }

//     }



//     $body .= "<p>You can redeem these gift cards online.</p>";
//     $body .= "<p>Best regards,<br>Gift Cards Plus</p>";

//     $headers = [
//         'Content-Type: text/html; charset=UTF-8',
//         'From: ' . esc_html($sender_name) . ' <' . sanitize_email($sender_email) . '>',
//     ];

//     wp_mail($recipient_email, $subject, $body, $headers);
// }

/*add_action('admin_init', function () {
    // Only run for admins
    if (!current_user_can('manage_woocommerce')) return;

    $orders = wc_get_orders([
        'status' => 'any',
        'limit' => -1, // Get all orders — you can restrict by date or batch for safety
    ]);

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $user_id = $order->get_customer_id();
        $order->update_meta_data('_customer_user', $user_id);
        $order->save();


    }

    echo "✅ _customer_user meta updated.";
    exit;
});*/

if ( ! function_exists( 'gcp_get_gift_card_secret_key' ) ) {
    function gcp_get_gift_card_secret_key() {
        return function_exists( 'gcp_decrypt_option' ) ? gcp_decrypt_option( 'gcp_gift_card_secret_key' ) : '';
    }
}

/**
 * Encrypt gift card number using AES-256-CBC
 * Uses gcp_gift_card_secret_key option (encrypted in DB) for encryption key
 *
 * @param string $plainText The card number to encrypt
 * @return string Base64 encoded IV + encrypted data
 */
if (!function_exists('encrypt_giftcard_no')) {
    function encrypt_giftcard_no($plainText)
    {
        $secret = function_exists('gcp_get_gift_card_secret_key') ? gcp_get_gift_card_secret_key() : '';
        if ($secret === '') {
            throw new Exception('Gift card secret key is not configured.');
        }

        $key = hash('sha256', $secret, true);
        $iv = openssl_random_pseudo_bytes(16); // AES block size
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

        // Store IV + encrypted data together (Base64 for storage only)
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
if (!function_exists('decrypt_giftcard_no')) {
    function decrypt_giftcard_no($encryptedData)
    {
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

function place_cod_order_callback()
{
    gcp_require_admin_ajax();
    check_ajax_referer( 'delivery_ajax_nonce', 'security' );

    // Add error handling
    try {
        // CRITICAL: Prevent HTML output during AJAX (emails will still be sent normally)
        // WooCommerce emails are sent via PHPMailer, not by outputting HTML
        // But some custom templates or hooks might accidentally output HTML, so we capture it
        if (wp_doing_ajax()) {
            // Start output buffering to catch any accidental HTML output
            // This won't interfere with email sending - emails use PHPMailer, not output
            if (ob_get_level() == 0) {
                ob_start();
            }
        }

        // CRITICAL: Clean any existing output buffers first
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Start fresh buffer to catch any unexpected output
        ob_start();

        // Suppress any errors/warnings that might output
        @ini_set('display_errors', 0);
        @error_reporting(0);

        // Initialize scheduled_dates early
        $scheduled_dates = [];
        $recipient_gift_cards = [];
        $emails_by_date = [];
        $current_timestamp = current_time('timestamp');

        // AFTER (fixed)
        $raw_order_data = $_POST['order_data'] ?? '';
        if (is_array($raw_order_data)) {
            // Already parsed by PHP (form-encoded submission)
            $order_data = $raw_order_data;
        } else {
            // JSON string submission (SMS flow and others)
            $order_data = json_decode(wp_unslash($raw_order_data), true) ?? [];
        }
     
        $sku_counts = [];
        $sku_total_prices = [];
        $errors = [];

        // Calculate quantity and total price per SKU
        foreach ($order_data['recipients'] as $recipient) {
            foreach ($recipient['products'] as $product) {
                if (!empty($product['sku'])) {
                    $sku = $product['sku'];
                    $gc_price = $product['price'];
                    $quantity = $product['quantity'] ?? 1;

                    $sku_counts[$sku] = ($sku_counts[$sku] ?? 0) + $quantity;
                    $sku_total_prices[$sku] = ($sku_total_prices[$sku] ?? 0) + ($gc_price * $quantity);
                }
            }
        }

        // Check limits per SKU
        foreach ($sku_counts as $sku => $total_quantity) {
            $product_id = wc_get_product_id_by_sku($sku);
            if (!$product_id)
                continue;

            $product_name = get_the_title($product_id);
            $quantity_per_transaction = get_field('_quantity_per_transaction', $product_id);
            $total_value_per_transaction = get_field('_total_value_per_transaction', $product_id);
            $transaction_limit_checkbox = get_field('add_transaction_limit_checkbox', $product_id);

            if ($transaction_limit_checkbox === 'yes' || $transaction_limit_checkbox === 'Yes') {
                // Quantity limit check
                if ($total_quantity > $quantity_per_transaction) {
                    $errors[] = "Quantity limit exceeded for product : <strong>{$product_name}</strong> :  Ordered <strong>{$total_quantity}</strong>, Allowed <strong>{$quantity_per_transaction}</strong></br>";
                }

                // Total value limit check
                $total_price = $sku_total_prices[$sku];
                if ($total_price > $total_value_per_transaction) {
                    $errors[] = "Price limit exceeded for product : <strong>{$product_name}</strong> :  Total <strong>{$total_price}</strong>, Allowed <strong>{$total_value_per_transaction}</strong></br>";
                }
            }
        }

        if (!empty($errors)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $err_msg = implode("", $errors);
            wp_send_json_error(['message' => $err_msg, 'reason' => $err_msg]);
        }

        global $wpdb;
        $scheduled_dates = [];
        $i = 0;
        foreach ($order_data['scheduleData'] ?? [] as $schedule) {
            $j = 0;
            foreach ($schedule['giftCards'] ?? [] as $giftCard) {
                $scheduleDate = $giftCard['scheduleDate'] ?? '';
                if (!empty($scheduleDate)) {
                    $scheduled_dates[$i][$j] = sanitize_text_field($scheduleDate);
                    $order_data['recipients'][$i]['products'][$j]['scheduleDate'] = sanitize_text_field($scheduleDate);
                }
                $j++;
            }
            $i++;
        }

        if (empty($order_data)) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            wp_send_json_error(['message' => 'Order data is required.']);
        }

        // Use businessId from POST directly
        $business_user_id = absint($order_data['businessId']);

        if (!$business_user_id) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            wp_send_json_error(['message' => 'Invalid Business ID.']);
        }

        $business_user = get_user_by('ID', $business_user_id);

        if (!$business_user) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            wp_send_json_error(['message' => 'Business user not found.']);
        }

        $currenttime = current_time('timestamp');
        $month_year_key = date('m-Y', $currenttime);
        $monthly_limit_used = get_user_meta($business_user->ID, '_pl_' . $month_year_key, true) ?: 0;
        $temp_monthly_orders = get_user_meta($business_user->ID, '_pl_' . $month_year_key . '_orders', true) ?: '';
        $monthly_orders = array();
        $monthly_orders = array_values(array_filter(explode(',', $temp_monthly_orders)));

        $float_balance = (float) get_user_meta($business_user->ID, 'float_balance', true);
        $prepaid_limit = (float) get_user_meta($business_user->ID, 'prepaid_limit', true) ?: 0;
        $no_limit = ($prepaid_limit == 0);

        // Compute the authoritative order total server-side from the same per-SKU prices
        // already validated above — never trust the client-supplied 'total' field.
        $order_total = (float) array_sum( $sku_total_prices );
        $paymentMethod = trim($order_data['paymentMethod']);

        if ($paymentMethod === 'client-billing') {
            // Client Billing has no float account (per spec) — prepaid_limit IS the agreed
            // credit balance ("Balance = Prepaid Limit"). Orders are checked and later debited
            // directly against it, not against a separate monthly-reset allowance.
            if (!$no_limit && $order_total > $prepaid_limit) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                wp_send_json_error(['message' => 'This business has reached their prepaid limit balance please review before proceeding.']);
            }
        } else if ($paymentMethod === 'prepaid') {
            // Instant payment/Float: prepaid_limit here is a per-transaction spend cap
            // (0 = no limit), separate from and in addition to the float_balance check below.
            if (!$no_limit && $order_total > $prepaid_limit) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                wp_send_json_error(['message' => 'This order is greater than the prepaid transaction limit set for this business please review before proceeding.']);
            }

            if ($order_total > $float_balance) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                wp_send_json_error(['message' => 'This business has insufficient float balance please top up before proceeding.']);
            }
            // The actual deduction happens once, later, via log_float_transaction() after
            // the order is created — not here. (A duplicate manual deduction used to also
            // happen right after BHN confirmation, causing every prepaid order to debit
            // float_balance twice; removed.)
        } else {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            wp_send_json_error(['message' => 'Please select valid payment method.']);
        }

        // SEND order to BH
        $CLIENTPROGRAMID = function_exists('gcp_get_bhn_client_program_id') ? gcp_get_bhn_client_program_id() : '';

        // $bhi_output = array();

        // if (empty($CLIENTPROGRAMID) || $CLIENTPROGRAMID == '') {
        //     while (ob_get_level() > 0) {
        //         ob_end_clean();
        //     }
        //     wp_send_json_error(['message' => 'Client Program ID is required for BH Integration.']);
        // }
        // SEND order to BH (only if there are BHN products)
        $CLIENTPROGRAMID = function_exists('gcp_get_bhn_client_program_id') ? gcp_get_bhn_client_program_id() : '';

        $bhi_output = [
            'clientProgramNumber' => $CLIENTPROGRAMID,
            'paymentType' => 'DRAW_DOWN',
            'millisecondsToWait' => 15000,
            'orderDetails' => [],
            'returnCardNumberAndPIN' => "true",
        ];

        // Temporary map to group products by SKU
        $productsMap = [];
        $bhn_products = [];
        $non_bhn_products = [];

        foreach ($order_data['recipients'] as $recipient) {
           
            $fullName = trim($recipient['name'] ?? '');
            $firstname = trim($recipient['firstname'] ?? '');
            $lastname = trim($recipient['lastname'] ?? '');
            $email = $recipient['email'] ?? 'default@example.com';
            $phone = $recipient['phone'] ?? '000000000';

            $recipientInfo = [
                'id' => '',
                'firstName' => $firstname,
                'lastName' => $lastname,
                'email' => $email,
                'address' => [
                    'line1' => '',
                    'line2' => '',
                    'city' => '',
                    'postalCode' => '',
                    'country' => '',
                ],
            ];

            foreach ($recipient['products'] as $product) {
                $supplier_sku = $product_id ? get_post_meta($product_id, '_supplier_sku', true) : '';
                $sku = $product['sku'] ?? '';
                $contentProvider = !empty($supplier_sku) ? $supplier_sku : $sku;

                $bhnPro = isset($product['bhnPro']) ? filter_var($product['bhnPro'], FILTER_VALIDATE_BOOLEAN) : false;
                $price = $product['price'] ?? '0.00';
                $productsMap_key = $sku . '_' . $price;

                if (!isset($productsMap[$productsMap_key])) {
                    $productsMap[$productsMap_key] = [
                        'clientRefId' => 'CRI_' . uniqid(),
                        'quantity' => 0,
                        'amount' => $price,
                        'contentProvider' => $contentProvider,
                        'recipients' => [],
                        'is_bhn_product' => $bhnPro
                    ];
                }

                $productsMap[$productsMap_key]['recipients'][] = $recipientInfo;
                $productsMap[$productsMap_key]['quantity'] += 1;
            }
        }

        // Separate BHN and Non-BHN products
        foreach ($productsMap as $product) {
            if (!empty($product['is_bhn_product'])) {
                $bhn_products[] = $product;
            } else {
                $non_bhn_products[] = $product;
            }
        }

        

        

        // Convert map to indexed array for final output
        $bhi_output['orderDetails'] = array_values($bhn_products);
        $bhi_order_number = '';
        $bhi_order_transaction_id = '';
        $bhi_egift_order_details = array();
        $error_reason = '';

        // Only call Blackhawk API when the order has BHN products; skip for non-BHN-only orders
        if (!empty($bhn_products)) {
        $bhi_uniq_id = uniqid('SGB_');
        $response = bhi_submit_order($bhi_output, $bhi_uniq_id);


        $responseData = json_decode($response, true);
        $bhi_output_json = json_encode($bhi_output);

        // Extract a meaningful error message from BHN responses (including nested structures)
        $extract_bhn_error_message = function ($data, $depth = 0) use (&$extract_bhn_error_message) {
            if ($depth > 6) {
                return '';
            }
            $looks_like_id_token = function ($s) {
                $s = trim((string) $s);
                if ($s === '') {
                    return false;
                }
                // If it has whitespace, it's probably human text.
                if (preg_match('/\s/', $s)) {
                    return false;
                }
                // Long alphanumeric tokens (requestId/transactionId/orderNumber style)
                if (preg_match('/^[A-Za-z0-9_-]{16,}$/', $s)) {
                    return true;
                }
                // Hex-like hashes
                if (preg_match('/^[0-9a-f]{32,}$/i', $s)) {
                    return true;
                }
                // Base64-like blobs
                if (preg_match('/^[A-Za-z0-9+\/=]{24,}$/', $s)) {
                    return true;
                }
                // Long numeric ids
                if (preg_match('/^[0-9]{10,}$/', $s)) {
                    return true;
                }
                return false;
            };

            $is_human_error_string = function ($s) use ($looks_like_id_token) {
                $s = trim((string) $s);
                if ($s === '' || $s === '0') {
                    return false;
                }
                if ($looks_like_id_token($s)) {
                    return false;
                }
                // Prefer strings that look like sentences.
                if (preg_match('/\s/', $s)) {
                    return true;
                }
                if (preg_match('/[A-Za-z]/', $s) && preg_match('/[.,:;!?\-]/', $s)) {
                    return true;
                }
                // Allow short codes only as last resort (handled by caller).
                return true;
            };

            if (is_string($data)) {
                $candidate = trim($data);
                return $is_human_error_string($candidate) ? $candidate : '';
            }
            if (!is_array($data)) {
                return '';
            }

            $skip_keys = [
                'requestId',
                'transactionId',
                'orderNumber',
                'clientRefId',
                'clientReference',
                'clientProgramNumber',
                'merchantId',
                'id',
                'uuid',
                'traceId',
                'correlationId',
            ];

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
                    $candidate = trim($data[$key]);
                    if ($is_human_error_string($candidate)) {
                        return $candidate;
                    }
                }
            }

            // Common containers BHN may use
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

           
            foreach ($data as $k => $value) {
                if (is_string($k) && in_array($k, $skip_keys, true)) {
                    continue;
                }
                if (is_string($value)) {
                    $candidate = trim($value);
                    if ($is_human_error_string($candidate)) {
                        return $candidate;
                    }
                    continue;
                }
                $msg = $extract_bhn_error_message($value, $depth + 1);
                if ($msg !== '') {
                    return $msg;
                }
            }

            return '';
        };
       
        if (isset($responseData['success']) && $responseData['success'] === true) {
            $current_datetime = current_time('timestamp');
            $meta_key = 'egift_order_details_' . $responseData['orderNumber'];

            $bhi_egift_order_details = [
                'requestId' => $bhi_uniq_id,
                'transactionId' => $responseData['transactionId'],
                'orderNumber' => $responseData['orderNumber'],
                'requestData' => json_decode($bhi_output_json, true),
                'hitTime' => $current_datetime,
            ];

            update_user_meta(absint($order_data['businessId']), $meta_key, $bhi_egift_order_details);

            $bhi_order_number = 'Order Number: ' . $responseData['orderNumber'];
            $bhi_order_transaction_id = 'Transaction ID: ' . $responseData['transactionId'];
        } else {
            // Prefer exact error message from Blackhawk API (e.g. value restriction min/max)
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
                    $error_reason = is_string($responseData['errors'][0]) ? $responseData['errors'][0] : wp_json_encode($responseData['errors'][0]);
                }
            }

            // If top-level keys didn't contain a message, scan nested BHN response structures.
            if ($error_reason === '' && is_array($responseData)) {
                $deep_message = $extract_bhn_error_message($responseData);
                if ($deep_message !== '' && $deep_message !== '0') {
                    $error_reason = $deep_message;
                }
            }
            if ($error_reason === '' && is_array($responseData)) {
                $not_complete = (isset($responseData['percentComplete']) && (int) $responseData['percentComplete'] === 0)
                    || (isset($responseData['isCompleted']) && !$responseData['isCompleted'])
                    || (isset($responseData['success']) && !$responseData['success']);
                $error_reason = $not_complete ? 'Your Order Not Complete Error From BHN' : wp_json_encode($responseData);
            }
            if ($error_reason === '') {
                if ($responseData === null) {
                    // Non-JSON response (e.g. plain text "The service is not currently available" from server)
                    $raw = is_string($response) ? trim($response) : '';
                    $error_reason = $raw !== '' ? wp_strip_all_tags($raw) : 'Invalid response from Blackhawk. Please try again.';
                } else {
                    $error_reason = wp_json_encode($responseData);
                }
            }
        }

        // When Blackhawk rejects the order (e.g. price outside value restrictions), return the exact error to the user
        if ($error_reason !== '') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            wp_send_json_error([
                'message' => $error_reason,
                'reason'  => $error_reason,
            ]);
        }
        } // end if (!empty($bhn_products)) – only call BHN API when order has BHN products

        // float_balance is deducted exactly once, later, via log_float_transaction() after
        // the order is created — reached only once BHN has confirmed success (any BHN
        // rejection above calls wp_send_json_error(), which halts execution before this
        // point), so the "no deduction on a rejected order" guarantee still holds.

        $order_id_from_request = !empty($order_data['orderInfo']) ? intval($order_data['orderInfo']) : 0;
        $order = null;

        $placed_by = wp_get_current_user();
        $username = $placed_by->display_name;

        if ($order_id_from_request && wc_get_order($order_id_from_request)) {
            $order = wc_get_order($order_id_from_request);
            foreach ($order->get_items() as $item_id => $item) {
                $order->remove_item($item_id);
            }
            $order->save();
            $order->add_order_note("Order updated by {$username}");
        } else {
            $order = wc_create_order();
            $order->set_customer_id($business_user->ID);
            $order->add_order_note("New order created by {$username}");
        }


        // Mark as place_cod_order so status/completed hooks don't double-send gift card emails (persist now so hooks see it)
        $order->update_meta_data('_place_cod_order', '1');
        update_post_meta($order->get_id(), '_place_cod_order', '1');

        if ($bhi_order_number !== '') {
            $order->add_order_note($bhi_order_number);
        }
        $order->add_order_note($placed_by);
        if ($bhi_order_transaction_id !== '') {
            $order->add_order_note($bhi_order_transaction_id);
        }

        update_post_meta($order->get_id(), 'created_by', $username);
        update_post_meta($order->get_id(), '_customer_user', $business_user->ID);
        $order->update_meta_data('_customer_user', $business_user->ID);
        // If no sender name provided, use the customer's (order placer's) name
        $effective_sender_name = !empty(trim((string)($order_data['sender'] ?? '')))
            ? sanitize_text_field($order_data['sender'])
            : trim(get_user_meta($business_user->ID, 'first_name', true) . ' ' . get_user_meta($business_user->ID, 'last_name', true));
        if (empty($effective_sender_name)) {
            $effective_sender_name = $business_user->display_name ?? '';
        }
        $order->update_meta_data('_sender_name', $effective_sender_name);

        $invoice_number = 'INV-' . date('Ymd') . '-' . wp_rand(1000, 9999);
        $order->update_meta_data('_invoice_number', $invoice_number);

        $business_user = get_user_by('id', $business_user->ID);
        $business_user_name = $business_user->display_name;
        $business_user_email = $business_user->user_email;

        $all_gift_cards_to_send = [];

        foreach ($order_data['recipients'] as $recipient) {
            foreach ($recipient['products'] as $product) {
                $product_id = wc_get_product_id_by_sku($product['sku']);
                if (!$product_id) {
                    continue;
                }

                $wc_product = wc_get_product($product_id);
                $sku_type = get_post_meta($product_id, 'sku_type', true);
                $pro_parent_sku = get_post_meta($product_id, 'parent_sku', true);

                // Only use parent product title when sku_type is Child and parent_sku is set and valid.
                if (strtolower((string) $sku_type) === 'child' && !empty($pro_parent_sku)) {
                    $parent_pro_id = wc_get_product_id_by_sku($pro_parent_sku);
                    if ($parent_pro_id && $parent_pro_id != $product_id) {
                        $product['name'] = get_the_title($parent_pro_id);
                    } else {
                        $product['name'] = $wc_product->get_name();
                    }
                } else {
                    $product['name'] = $wc_product->get_name();
                }

                $unique_gift_card_number = generate_unique_gift_card_code();

                // Build SAFE & HUMAN-READABLE post title
                $product_name  = sanitize_text_field($product['name']);
                $price         = number_format((float) $product['price'], 2);
                $recipient_name = sanitize_text_field($recipient['name']);
                $letters = chr(rand(65, 90)) . chr(rand(65, 90)); // A–Z
                $numbers = rand(1000, 9999);

                $code = $letters . $numbers;

                $post_title = sprintf(
                    '%s $%s – %s (#GC-%s)',
                    $product_name,
                    $price,
                    $recipient_name,
                    $code
                );


                // Check if this is a BHN product
                $is_bhn_product = isset($product['bhnPro']) ? filter_var($product['bhnPro'], FILTER_VALIDATE_BOOLEAN) : false;

                // Encrypt the gift card number
                $encrypted_gift_card_number = '';
                $post_title_card_number = $unique_gift_card_number; // Default fallback

                if ($is_bhn_product) {
                    try {
                        $encrypted_gift_card_number = encrypt_giftcard_no($unique_gift_card_number);
                        $post_title_card_number = $encrypted_gift_card_number;
                    } catch (Exception $e) {
                        // Fallback to original number if encryption fails
                        $encrypted_gift_card_number = '';
                        $post_title_card_number = $unique_gift_card_number;
                    }
                } else {
                    try {
                        $encrypted_gift_card_number = encrypt_giftcard_no($unique_gift_card_number);
                        $post_title_card_number = $encrypted_gift_card_number;
                    } catch (Exception $e) {
                        // encryption failed
                    }
                }


                // DEBUG: print name used for gift card post title before creation (remove after debugging)
                // echo '<pre>GIFT CARD POST – place_cod_order_callback (BEFORE CREATE)' . "\n";
                // echo '  product_name (used in post title): ' . esc_html($product_name) . "\n";
                // echo '  post_title (full): ' . esc_html($post_title) . "\n";
                // echo '  product_id: ' . (int) $product_id . ', parent_pro_id: ' . (int) $parent_pro_id . "\n";
                // echo '  product[sku]: ' . esc_html($product['sku'] ?? '') . ', product[name] (from request/overwrite): ' . esc_html($product['name'] ?? '') . "\n";
                // echo '  parent_product_title (get_the_title(parent_pro_id)): ' . esc_html($parent_product_title ?? '') . "\n";
                // echo '</pre>';
                // exit;

                $gift_card_post_id = wp_insert_post([
                    'post_title' => $post_title,
                    'post_type' => 'gift_card',
                    'post_status' => 'publish',
                ]);

              
                // Get 'Is Gift Card Plus?' status value from the original Product ID
                $is_gc_plus = get_post_meta($product_id, 'is_it_gift_card_plus_product', true);

                // Use 'false' as default if meta doesn't exist
                $is_gc_plus_value = ($is_gc_plus === 'true') ? 'true' :'false';

                if (isset($product['scheduleDate'])) {
                    update_field('_scheduled_gift_card_delivery', $product['scheduleDate'], $gift_card_post_id);
                }

                // Store encrypted card number (for BHN products, this uses encrypt_giftcard_no format)
                if (!empty($encrypted_gift_card_number)) {
                    update_post_meta($gift_card_post_id, '_gift_card_number_enc', $encrypted_gift_card_number);
                } else {
                    // Fallback: if encryption failed, store original (not recommended but better than nothing)
                    update_post_meta($gift_card_post_id, '_gift_card_number_enc', $unique_gift_card_number);
                }
                update_post_meta($gift_card_post_id, '_recipient_name', $recipient['name']);
                update_post_meta($gift_card_post_id, '_recipient_email', $recipient['email']);
                update_post_meta($gift_card_post_id, '_gift_subject', $product['subject']);
                update_post_meta($gift_card_post_id, '_gift_message', $product['message']);
                update_post_meta($gift_card_post_id, '_recipient_phone', sanitize_text_field($recipient['phone']));
                update_post_meta($gift_card_post_id, '_delivery_method', $product['deliveryMethod']);
                update_post_meta($gift_card_post_id, '_product_sku', $product['sku']);
                update_post_meta($gift_card_post_id, '_price', $product['price']);
                update_post_meta($gift_card_post_id, '_sender_name', $effective_sender_name);
                update_post_meta($gift_card_post_id, '_sender_email', sanitize_email($order_data['senderEmail']));
                update_post_meta($gift_card_post_id, '_image_url', $product['image']);
                update_post_meta($gift_card_post_id, '_invoice_number', $invoice_number);
                update_post_meta($gift_card_post_id, '_order_id', $order->get_id());
                update_post_meta($gift_card_post_id, '_campaign', $order_data['campaignValue']);
                update_post_meta($gift_card_post_id, 'business_name_email', $business_user_email);
                update_post_meta($gift_card_post_id, 'business_name', $business_user_name);
                update_post_meta($gift_card_post_id, '_card_status', 'inactive');
                update_post_meta($gift_card_post_id, '_is_gc_plus_product', $is_gc_plus_value);
                if ( $is_gc_plus_value === 'true' ) { update_post_meta($gift_card_post_id, '_swap_available_amount', $product['price']); }

                // Handle activation expiry type
                $selected_expiry_type = $order_data['activationExpiryTypeValue'] ?? '';
                if ($selected_expiry_type === 'default') {
                    // Fetch the product's Activation Expiry Type value
                    $acf_value = get_field('activation_expiry_type', $product_id);
                    if (!empty($acf_value)) {
                        $selected_expiry_type = $acf_value;
                    } else {
                        // Fallback: try with underscore prefix (some ACF fields use this)
                        $acf_value = get_field('_activation_expiry_type', $product_id);
                        if (!empty($acf_value)) {
                            $selected_expiry_type = $acf_value;
                        }
                    }
                    
                    // If still empty or 'default', set a safe default
                    if (empty($selected_expiry_type) || $selected_expiry_type === 'default') {
                        $selected_expiry_type = 'no_activation_expiry';
                    }
                }

                $activation_type_labels = [
                    'no_activation_expiry' => 'No Activation Expiry',
                    'no_activation_needed' => 'No Activation Needed',
                    'activation_set_date' => 'Activated by a Set Date',
                    'set_period' => 'Activated within a Set Period',
                ];

                update_field('_activation_expiry_type', $selected_expiry_type, $gift_card_post_id);

                // No Activation Needed: card is usable in wallet immediately without customer activation
                if ( $selected_expiry_type === 'no_activation_needed' ) {
                    update_post_meta( $gift_card_post_id, '_card_status', 'active' );
                }

                // Helper function for date parsing (if not exists)
                if (!function_exists('parse_any_date_to_dt')) {
                    function parse_any_date_to_dt($date_str)
                    {
                        if (empty($date_str))
                            return false;
                        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
                        $formats = [
                            'Y-m-d\TH:i',
                            'Y-m-d H:i:s',
                            'd/m/Y g:i a',
                            'd/m/Y H:i',
                            'd/m/Y',
                            'Y-m-d',
                            'd-m-Y',
                        ];
                        foreach ($formats as $fmt) {
                            $dt = DateTime::createFromFormat($fmt, $date_str, $tz);
                            $errors = DateTime::getLastErrors();
                            if ($dt !== false && empty($errors['warning_count']) && empty($errors['error_count'])) {
                                $dt->setTimezone($tz);
                                return $dt;
                            }
                        }
                        try {
                            $dt = new DateTime($date_str, $tz);
                            return $dt;
                        } catch (Exception $e) {
                            return false;
                        }
                    }
                }

                $final_expiry_date = '';
                $expiry_date_raw = '';
                $save_calc = '';

                // Handle activation_set_date
                if ($selected_expiry_type === 'activation_set_date') {
                    $expiry_date_raw = !empty($order_data['activationExpiryDateValue'])
                        ? $order_data['activationExpiryDateValue']
                        : get_post_meta($product_id, '_activation_expiry_date', true);

                    // For child/variation products the activation date is on the parent — try both sources
                    if (empty($expiry_date_raw)) {
                        $activation_parent_id = !empty($parent_pro_id) ? $parent_pro_id
                            : ($wc_product instanceof WC_Product_Variation ? $wc_product->get_parent_id() : 0);
                        if ($activation_parent_id && $activation_parent_id !== $product_id) {
                            $expiry_date_raw = get_post_meta($activation_parent_id, '_activation_expiry_date', true);
                        }
                    }
                    if (!empty($expiry_date_raw)) {
                        $dt = parse_any_date_to_dt($expiry_date_raw);
                        if ($dt) {
                            $save_date = $dt->format('Y-m-d H:i:s');
                            update_post_meta($gift_card_post_id, '_activation_expiry_date', $save_date);
                            $order->update_meta_data('_activation_expiry_date', $save_date);
                            $final_expiry_date = $save_date;
                        } else {
                            update_post_meta($gift_card_post_id, '_activation_expiry_date', sanitize_text_field($expiry_date_raw));
                            $order->update_meta_data('_activation_expiry_date', sanitize_text_field($expiry_date_raw));
                            $final_expiry_date = $expiry_date_raw;
                        }
                        delete_field('_activation_expiry_duration', $gift_card_post_id);
                        delete_field('_activation_expiry_unit', $gift_card_post_id);
                    } else {
                        update_field('_activation_expiry_type', 'no_activation_expiry', $gift_card_post_id);
                    }
                }

                // Handle set_period
                if ($selected_expiry_type === 'set_period') {
                    $activation_parent_id = !empty($parent_pro_id) ? $parent_pro_id
                        : ($wc_product instanceof WC_Product_Variation ? $wc_product->get_parent_id() : 0);
                    $activation_source_id = ($activation_parent_id && $activation_parent_id !== $product_id)
                        ? $activation_parent_id : $product_id;

                    $duration = !empty($order_data['activationExpiryDurationValue'])
                        ? $order_data['activationExpiryDurationValue']
                        : (get_post_meta($product_id, '_activation_expiry_duration', true)
                            ?: get_post_meta($activation_source_id, '_activation_expiry_duration', true));
                    $unit = !empty($order_data['activationExpiryUnitValue'])
                        ? $order_data['activationExpiryUnitValue']
                        : (get_post_meta($product_id, '_activation_expiry_unit', true)
                            ?: get_post_meta($activation_source_id, '_activation_expiry_unit', true));

                    if (!empty($duration) && !empty($unit)) {
                        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
                        // Anchor to the scheduled delivery date (not order placement) per spec —
                        // falls back to now for instant delivery (no schedule date set).
                        $schedule_date_raw = isset($product['scheduleDate']) ? $product['scheduleDate'] : '';
                        $anchor_dt = !empty($schedule_date_raw) ? parse_any_date_to_dt($schedule_date_raw) : false;
                        $now = $anchor_dt instanceof DateTime ? $anchor_dt : new DateTime('now', $tz);
                        switch ($unit) {
                            case 'days':
                                $now->modify("+{$duration} days");
                                break;
                            case 'weeks':
                                $now->modify("+{$duration} weeks");
                                break;
                            case 'months':
                                $now->modify("+{$duration} months");
                                break;
                            case 'years':
                                $now->modify("+{$duration} years");
                                break;
                        }
                        $save_calc = $now->format('Y-m-d H:i:s');
                        $final_expiry_date = $save_calc;
                    }

                    if (!empty($duration)) {
                        update_field('_activation_expiry_duration', sanitize_text_field($duration), $gift_card_post_id);
                        $order->update_meta_data('_activation_expiry_duration', sanitize_text_field($duration));
                    }
                    if (!empty($unit)) {
                        update_field('_activation_expiry_unit', sanitize_text_field($unit), $gift_card_post_id);
                        $order->update_meta_data('_activation_expiry_unit', sanitize_text_field($unit));
                    }
                    if (!empty($save_calc)) {
                        update_post_meta($gift_card_post_id, '_activation_expiry_date', $save_calc);
                        $order->update_meta_data('_activation_expiry_date', $save_calc);
                    }
                }

                // Handle no_activation cases
                if (in_array($selected_expiry_type, ['no_activation_expiry', 'no_activation_needed'])) {
                    delete_field('_activation_expiry_date', $gift_card_post_id);
                    delete_field('_activation_expiry_duration', $gift_card_post_id);
                    delete_field('_activation_expiry_unit', $gift_card_post_id);
                    $final_expiry_date = 'N/A';
                }

                // Prepare final expiry date for sending
                $expiry_type_label = $activation_type_labels[$selected_expiry_type] ?? $selected_expiry_type;
                $final_for_email = '';
                if (!empty($final_expiry_date) && $final_expiry_date !== 'N/A') {
                    $dt_for_email = parse_any_date_to_dt($final_expiry_date);
                    if ($dt_for_email instanceof DateTime) {
                        $final_for_email = $dt_for_email->format('d/m/Y g:i a');
                    } else {
                        $final_for_email = $final_expiry_date;
                    }
                } else {
                    $final_for_email = '';
                }

                // --- Card usage expiry logic start ---
                // This determines when the card itself expires/funds are lost (Use Expiry)
                
                // 1. Get Product Usage Configuration
                $usage_type_config = get_post_meta($product_id, 'gift_card_expiry_type', true);
                
                // Defaults for Wallet CPT
                $wallet_usage_type = 'no_expiry';
                $usage_final_date  = '';
                $usage_duration    = '';
                $usage_unit        = '';

                if ($usage_type_config === 'gift_set_date') {
                    // Scenario: Fixed Date set by Admin
                    $wallet_usage_type = 'fixed_date';
                    $raw_usage_date    = get_post_meta($product_id, 'gift_card_expiry_date', true);
                    
                    if (!empty($raw_usage_date)) {
                        $usage_final_date = date('Y-m-d H:i:s', strtotime($raw_usage_date));
                    }

                } elseif ($usage_type_config === 'expiry_period_starts_on_purchase') {
                    // Scenario: Period starts on purchase
                    // CHANGE: Do NOT calculate date. Pass rules to CPT.
                    $wallet_usage_type = 'on_purchase'; 
                    $usage_duration    = get_post_meta($product_id, 'gift_card_expiry_duration', true);
                    $usage_unit        = get_post_meta($product_id, 'gift_card_expiry_unit', true);

                } elseif ($usage_type_config === 'expiry_period_starts_on_activation') {
                    // Scenario: Period starts on activation
                    // Pass rules to CPT.
                    $wallet_usage_type = 'on_activation';
                    $usage_duration    = get_post_meta($product_id, 'gift_card_expiry_duration', true);
                    $usage_unit        = get_post_meta($product_id, 'gift_card_expiry_unit', true);
                }

                // 2. Save Usage Meta to Wallet CPT
                update_post_meta($gift_card_post_id, '_expiry_type', $wallet_usage_type);

                // Clean up potential old data
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
                    // For 'Set Date', we have a concrete date.
                    update_post_meta($gift_card_post_id, '_expiry_date', $usage_final_date);
                    update_field('gift_card_expiry_date', $usage_final_date, $gift_card_post_id);
                }
                elseif ( $wallet_usage_type === 'on_purchase' && !empty($usage_duration) && !empty($usage_unit) ) {
                    // Calculate expiry date = post creation time + duration, and store it.
                    $creation_ts = get_post_time('U', false, $gift_card_post_id);
                    $expiry_ts   = strtotime("+{$usage_duration} {$usage_unit}", $creation_ts);
                    if ($expiry_ts !== false) {
                        $computed_expiry_date = date('Y-m-d H:i:s', $expiry_ts);
                        update_post_meta($gift_card_post_id, '_expiry_date', $computed_expiry_date);
                        update_field('gift_card_expiry_date', $computed_expiry_date, $gift_card_post_id);
                    }
                    update_post_meta($gift_card_post_id, '_expiry_duration', $usage_duration);
                    update_post_meta($gift_card_post_id, '_expiry_unit', $usage_unit);
                    update_field('gift_card_expiry_duration', $usage_duration, $gift_card_post_id);
                    update_field('gift_card_expiry_unit', $usage_unit, $gift_card_post_id);
                }
                elseif ( $wallet_usage_type === 'on_activation' ) {
                    // Store rules only; date is calculated when card is activated.
                    if (!empty($usage_duration)) {
                        update_post_meta($gift_card_post_id, '_expiry_duration', $usage_duration);
                        update_field('gift_card_expiry_duration', $usage_duration, $gift_card_post_id);
                    }
                    if (!empty($usage_unit)) {
                        update_post_meta($gift_card_post_id, '_expiry_unit', $usage_unit);
                        update_field('gift_card_expiry_unit', $usage_unit, $gift_card_post_id);
                    }
                }

                // --- Card usage expiry logic end ---

                $quantity = 1;
                // Safely validate and convert product price
                $product_price = floatval($product['price'] ?? 0);
                $product_price = (is_finite($product_price) && !is_nan($product_price) && $product_price >= 0) ? $product_price : 0;
                
                $item_id = $order->add_product($wc_product, $quantity);
                $order_item = $order->get_item($item_id);
                
                // Ensure quantity is valid
                if ($quantity <= 0) {
                    $quantity = 1;
                    $order_item->set_quantity($quantity);
                }
                
                $line_total = $product_price * $quantity;
                $order_item->set_subtotal($line_total);
                $order_item->set_total($line_total);
                $order_item->update_meta_data('_line_subtotal', $line_total);
                $order_item->update_meta_data('_line_total', $line_total);
                $order_item->update_meta_data('_line_price', $product_price);
                $order_item->save();

                wc_add_order_item_meta($item_id, '_recipient_name', $recipient['name']);
                wc_add_order_item_meta($item_id, '_recipient_email', $recipient['email']);
                wc_add_order_item_meta($item_id, '_gift_subject', $product['subject']);
                wc_add_order_item_meta($item_id, '_gift_message', $product['message']);
                wc_add_order_item_meta($item_id, '_delivery_method', $product['deliveryMethod']);
                wc_add_order_item_meta($item_id, '_gift_card_number_enc', $encrypted_gift_card_number);
                wc_add_order_item_meta($item_id, '_gift_card_name', $product['name']);
                wc_add_order_item_meta($item_id, '_gift_card_sku', $product['sku']);
                wc_add_order_item_meta($item_id, '_gift_card_price', $product['price']);
                wc_add_order_item_meta($item_id, '_gift_card_post_id', $gift_card_post_id);

                if (isset($product['emailAnimation']) && !empty($product['emailAnimation'])) {
                    wc_add_order_item_meta($item_id, 'gift_email_animation', esc_url_raw($product['emailAnimation']));
                }
                if (isset($product['image']) && !empty($product['image'])) {
                    wc_add_order_item_meta($item_id, '_gift_card_image', esc_url_raw($product['image']));
                }
                if (isset($product['scheduleDate'])) {
                    wc_add_order_item_meta($item_id, '_scheduled_date', $product['scheduleDate']);
                }
                
                // Save Activation Expiry Type to order item meta (if not already saved)
                if (!empty($expiry_type_label)) {
                    wc_add_order_item_meta($item_id, '_activation_expiry_type', $expiry_type_label);
                }
                
                // Save Activation Expiry Date to order item meta (formatted for email)
                if (!empty($final_for_email)) {
                    wc_add_order_item_meta($item_id, '_activation_expiry_date', $final_for_email);
                } elseif (!empty($final_expiry_date) && $final_expiry_date !== 'N/A') {
                    // Fallback: if final_for_email is empty but final_expiry_date exists, format it
                    $dt_fallback = parse_any_date_to_dt($final_expiry_date);
                    if ($dt_fallback instanceof DateTime) {
                        $formatted_fallback = $dt_fallback->format('d/m/Y g:i a');
                        wc_add_order_item_meta($item_id, '_activation_expiry_date', $formatted_fallback);
                    } else {
                        wc_add_order_item_meta($item_id, '_activation_expiry_date', $final_expiry_date);
                    }
                }

                $recipient_email = $recipient['email'];

                if (!isset($recipient_gift_cards[$recipient_email])) {
                    $recipient_gift_cards[$recipient_email] = [
                        'name' => $recipient['name'],
                        'sender_name' => $effective_sender_name,
                        'sender_email' => $order_data['senderEmail'],
                        'cards' => [],
                    ];
                }

                $temp_card_details = [
                    'recipient_email' => $recipient_email,
                    'gift_card_number' => $unique_gift_card_number,
                    'gift_card_name' => $product['name'],
                    'price' => $product['price'],
                    'subject' => $product['subject'],
                    'message' => (!empty($product['message'])) ? $product['message'] : '',
                    'emailAnimation' => $product['emailAnimation'],
                    'image_url' => $product['image'],
                    'gift_card_post_id' => $gift_card_post_id,
                    'scheduled_date' => (isset($product['scheduleDate'])) ? $product['scheduleDate'] : '',
                    'expiry_type' => $expiry_type_label,
                    'expiry_date' => get_field('_activation_expiry_date', $gift_card_post_id),
                    'expiry_duration' => get_field('_activation_expiry_duration', $gift_card_post_id),
                    'expiry_unit' => get_field('_activation_expiry_unit', $gift_card_post_id),
                    'name' => $recipient['name'],
                    'email' => $recipient_email,
                    'sender_name' => $effective_sender_name,
                    'sender_email' => $order_data['senderEmail'],
                    'business_user_name' => $business_user_name,
                    'business_user_email' => $business_user_email,
                    'delivery_method' => isset($product['deliveryMethod']) ? $product['deliveryMethod'] : 'Email',
                    'recipient_phone' => isset($recipient['phone']) ? $recipient['phone'] : '',
                ];

                $recipient_gift_cards[$recipient_email]['cards'][] = $temp_card_details;
                $all_gift_cards_to_send[] = $temp_card_details;
            }
        }

        // Handle BHN order status
        if (!empty($responseData['orderNumber'])) {

           
            $bhi_order_number = $responseData['orderNumber'];
            $bhi_order_status = fetchOrderStatus($bhi_order_number, $bhi_uniq_id);
            $bhi_order_data = fetchOtherOrderData($bhi_order_number);

            
            $mergedData = array_merge($bhi_order_status, $bhi_order_data);


            if ($bhi_order_status['orderStatus'] == 'Funding Hold' || $bhi_order_status['orderStatus'] == 'In Process') {
                $order->set_status('on-hold');
                $order->update_status('on-hold', 'BHN Funding Hold detected');
                $bh_status = true;
                // echo 'BH status as true';
            }
            // echo 'End Here';
            // exit;
            $order->save();

            $bhi_order_status_request_id = $bhi_order_status['requestId'];

            global $wpdb;
            $table_name = $wpdb->prefix . 'bhi_fetch_order_data';
            $woo_order_id = $order->get_id();

            $order_status_json = !empty($bhi_order_status) ? wp_json_encode($bhi_order_status) : null;
            $order_data_json = !empty($bhi_order_data) ? wp_json_encode($bhi_order_data) : null;
            $merged_json = (!empty($bhi_order_status) && !empty($bhi_order_data)) ? wp_json_encode($mergedData) : null;

            $wpdb->insert(
                $table_name,
                [
                    'order_number' => $bhi_order_number ?? null,
                    'woo_order_no' => $woo_order_id,
                    'request_id' => $bhi_order_status_request_id ?? null,
                    'order_status_response' => $order_status_json,
                    'order_data_response' => $order_data_json,
                    'merged_response' => $merged_json,
                    'api_hit_time' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

            if (!empty($bhi_order_status) && !empty($bhi_order_data)) {
                $mergedData['api_hit_time'] = current_time('Y-m-d H:i:s');
            }
        }

        // Save data in bhn_logs table
        $table_name = $wpdb->prefix . 'bhn_order_logs';
        $bhn_only_data = $order_data;

        foreach ($bhn_only_data['recipients'] as $r_key => $recipient) {
            $filtered_products = [];
            foreach ($recipient['products'] as $p_key => $product) {
                $is_bhn = isset($product['bhnPro']) ? filter_var($product['bhnPro'], FILTER_VALIDATE_BOOLEAN) : false;
                if ($is_bhn) {
                    $filtered_products[] = $product;
                }
            }
            if (empty($filtered_products)) {
                unset($bhn_only_data['recipients'][$r_key]);
            } else {
                $bhn_only_data['recipients'][$r_key]['products'] = $filtered_products;
            }
        }

        if (!empty($bhn_only_data['recipients'])) {
            $serialized_data = maybe_serialize($bhn_only_data);
            $wpdb->insert(
                $table_name,
                array(
                    'order_no' => (int) $order->get_id(),
                    'order_details' => $serialized_data,
                    'bhn_details' => wp_json_encode($mergedData ?? []),
                    'order_time' => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s')
            );
        }

        if ($order_data['paymentMethod'] === 'prepaid') {
            $order->set_payment_method('float_balance');
            $order->set_payment_method_title('Paid via Float Balance');
        } elseif ($order_data['paymentMethod'] === 'client-billing') {
            $order->set_payment_method('prepaid_credit');
            $order->set_payment_method_title('Paid via Client Billing');
        }

        // START LIMIT manage
        if (!$no_limit) {
            $monthly_limit_used = floatval($monthly_limit_used) + $order_total;
            update_user_meta($business_user->ID, '_pl_' . $month_year_key, $monthly_limit_used);
        }
        $monthly_orders[] = $order->get_id();
        update_user_meta($business_user->ID, '_pl_' . $month_year_key . '_orders', implode(',', $monthly_orders));
        // END LIMIT manage

        $latest_scheduled_timestamp = 0;
        foreach ($order_data['recipients'] as $recipient) {
            foreach ($recipient['products'] as $product) {
                if (!empty($product['scheduleDate'])) {
                    $timestamp = strtotime($product['scheduleDate']);
                    if ($timestamp > $latest_scheduled_timestamp) {
                        $latest_scheduled_timestamp = $timestamp;
                    }
                }
            }
        }

       

        // Update order status (emails will be sent automatically, but output will be captured)
        if (wp_doing_ajax()) {
            // Ensure we have output buffering active to catch any email template output
            if (ob_get_level() == 0) {
                ob_start();
            }
        }

        if ($latest_scheduled_timestamp > $current_timestamp) {
            $intended_order_status = 'processing';
            $order->set_status('processing');
            $order->update_status('processing', 'BHN Processing detected');
        } else if ($bh_status || $bh_status == 1 || $bh_status == 'true') {
            $intended_order_status = 'on-hold';
            $order->set_status('on-hold');
            $order->update_status('on-hold', 'BHN Funding Hold detected');
        } else {
            $intended_order_status = 'completed';
            $order->set_status('completed');
            $order->update_status('completed', 'Order Completed');
        }

        // Clean any output that may have been generated during status update (email templates)
        if (wp_doing_ajax()) {
            // Get and discard any captured output from email templates
            $captured_output = '';
            while (ob_get_level() > 0) {
                $buffer = ob_get_contents();
                if (!empty($buffer)) {
                    $captured_output .= $buffer;
                }
                ob_end_clean();
            }



            // Start fresh buffer for the JSON response
            ob_start();
        }

        $order_status = $order->get_status();
        // echo'Outside';
        // echo $order_status;

        if ($order_status === 'completed') {
            foreach ($all_gift_cards_to_send as $gift_card_details) {
                $gc_email_date_timestamp = strtotime($gift_card_details['scheduled_date']);
                $current_timestamp = current_time('timestamp');
                $gc_email_status = 'immediate';

                if (!empty($gift_card_details['scheduled_date']) && $gc_email_date_timestamp > $current_timestamp) {
                    $gc_email_status = 'schedule';
                    $hook_name = 'send_gift_cards_email_event';
                    $hook_args = [$gift_card_details];
                    $timestamp = strtotime($gift_card_details['scheduled_date']);

                    if (!wp_next_scheduled($hook_name, $hook_args)) {
                        update_post_meta($gift_card_details['gift_card_post_id'], '_gift_card_send', 'Ordered');
                        wp_schedule_single_event($timestamp, $hook_name, $hook_args);
                    }

                    $emails_by_date[$gc_email_status][] = $gift_card_details;
                } else {
                    update_post_meta($gift_card_details['gift_card_post_id'], '_gift_card_send', 'Instant');
                    $gc_email_status = 'immediate';
                    $emails_by_date[$gc_email_status][] = $gift_card_details;
                    // SMS is handled by send_blackhawk_gift_card_email_on_order → send_gift_cards_email_to_recipient
                    // via the woocommerce_order_status_completed hook. Calling send_instant_gift_card_sms here
                    // would send a duplicate SMS before the hook fires.
                }
            }
        } else {
            // Order is in processing (scheduled) — schedule the gift card email event for each card.
            // Pass only the gift_card_post_id as the cron arg (not the full array) to avoid the
            // wp_options row-size limit that silently prevents large serialized args from registering.
            // The full payload is stored in _scheduled_email_payload post meta and read back at fire time.
            foreach ($all_gift_cards_to_send as $gift_card_details) {
                $gc_email_date_timestamp = strtotime($gift_card_details['scheduled_date']);
                $current_timestamp = current_time('timestamp');

                if (!empty($gift_card_details['scheduled_date']) && $gc_email_date_timestamp > $current_timestamp) {
                    $gc_post_id = $gift_card_details['gift_card_post_id'];
                    update_post_meta($gc_post_id, '_scheduled_email_payload', $gift_card_details);
                    $hook_name = 'send_gift_cards_email_event';
                    $hook_args = [$gc_post_id];
                    if (!wp_next_scheduled($hook_name, $hook_args)) {
                        update_post_meta($gc_post_id, '_gift_card_send', 'Ordered');
                        wp_schedule_single_event($gc_email_date_timestamp, $hook_name, $hook_args);
                    }
                } else {
                    update_post_meta($gift_card_details['gift_card_post_id'], '_gift_card_send', 'Pending Order Completion');
                }
            }
        }

        // Set billing details
        $current_user = wp_get_current_user();
        $first_name = get_user_meta($business_user->ID, 'first_name', true);
        $last_name = get_user_meta($business_user->ID, 'last_name', true);
        $email = $business_user->user_email;
        $phone = get_user_meta($business_user->ID, 'billing_phone', true);
        $address_1 = get_user_meta($business_user->ID, 'billing_address_1', true);
        $address_2 = get_user_meta($business_user->ID, 'billing_address_2', true);
        $city = get_user_meta($business_user->ID, 'billing_city', true);
        $postcode = get_user_meta($business_user->ID, 'billing_postcode', true);
        $country = get_user_meta($business_user->ID, 'billing_country', true);
        $state = get_user_meta($business_user->ID, 'billing_state', true);

        $order->set_billing_first_name($first_name);
        $order->set_billing_last_name($last_name);
        $order->set_billing_email($email);
        $order->set_billing_phone($phone);
        $order->set_billing_address_1($address_1);
        $order->set_billing_address_2($address_2);
        $order->set_billing_city($city);
        $order->set_billing_postcode($postcode);
        $order->set_billing_country($country);
        $order->set_billing_state($state);

        $order->update_meta_data('_sender_name', $effective_sender_name);
        $order->update_meta_data('_sender_email', sanitize_email($order_data['senderEmail']));
        $order->update_meta_data('_gift_recipients', $order_data['recipients']);

        $send_sms = isset($order_data['sendSms']) ? sanitize_text_field($order_data['sendSms']) : 'no';
        $order->update_meta_data('_send_sms', $send_sms);
        
        if (!empty($order_data['invoiceDetails'])) {
            $invoice = $order_data['invoiceDetails'];
            $order->update_meta_data('_invoice_company_name', $invoice['companyName']);
            $order->update_meta_data('_invoice_abn', $invoice['abn']);
            $order->update_meta_data('_invoice_billing_address', $invoice['billingAddress']);
            $order->update_meta_data('_invoice_notes', $invoice['notes']);
        }

        $order->update_meta_data('_selected_activation_expiry_type', sanitize_text_field($order_data['activationExpiryTypeValue']));
        $order->update_meta_data('_business_name', sanitize_text_field($business_user->display_name));
        $order->update_meta_data('_campaign', sanitize_text_field($order_data['campaignValue']));
        $order->update_meta_data('_order_name', sanitize_text_field($order_data['orderName']));
        $order->update_meta_data('_po_number', sanitize_text_field($order_data['poNumber']));
        $order->update_meta_data('_additional_reference', sanitize_text_field($order_data['additionalReference']));
        $order->update_meta_data('_client_reference', sanitize_text_field($order_data['clientReference']));
        $order->update_meta_data('fullfillment_total', sanitize_text_field($order_data['fullfillmentTotal']));
        $order->update_meta_data('delivery_total', sanitize_text_field($order_data['deliveryTotal']));
        $order->update_meta_data('_order_subtotal', $order_data['subtotal']);
        $order->update_meta_data('_order_gst', $order_data['gst']);

        // Safely convert and validate fee amounts
        $fulfillment_amount = floatval(sanitize_text_field($order_data['fullfillmentTotal'] ?? 0));
        $delivery_amount = floatval(sanitize_text_field($order_data['deliveryTotal'] ?? 0));
        $gst_amount = floatval(sanitize_text_field($order_data['gst'] ?? 0));
        
        // Ensure all amounts are valid numbers (not NaN or infinite)
        $fulfillment_amount = (is_finite($fulfillment_amount) && !is_nan($fulfillment_amount)) ? $fulfillment_amount : 0;
        $delivery_amount = (is_finite($delivery_amount) && !is_nan($delivery_amount)) ? $delivery_amount : 0;
        $gst_amount = (is_finite($gst_amount) && !is_nan($gst_amount)) ? $gst_amount : 0;

        $fullfillment_fee = new WC_Order_Item_Fee();
        $fullfillment_fee->set_name('Fullfillment Cost');
        $fullfillment_fee->set_amount($fulfillment_amount);
        $fullfillment_fee->set_tax_class('');
        $fullfillment_fee->set_tax_status('none');
        $fullfillment_fee->set_total($fulfillment_amount);
        $order->add_item($fullfillment_fee);

        $delivery_fee = new WC_Order_Item_Fee();
        $delivery_fee->set_name('Delivery Cost');
        $delivery_fee->set_amount($delivery_amount);
        $delivery_fee->set_tax_class('');
        $delivery_fee->set_tax_status('none');
        $delivery_fee->set_total($delivery_amount);
        $order->add_item($delivery_fee);

        $gst_fee = new WC_Order_Item_Fee();
        $gst_fee->set_name('GST Cost');
        $gst_fee->set_amount($gst_amount);
        $gst_fee->set_tax_class('');
        $gst_fee->set_tax_status('none');
        $gst_fee->set_total($gst_amount);
        $order->add_item($gst_fee);

        // Save product details
        // Wrap in error handling to catch any division by zero errors
        try {
            if ($order && $order instanceof WC_Order) {
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
                update_post_meta($order->get_id(), "{$order_number}_customer__details", $o_user_meta);
            }
        } catch (DivisionByZeroError $e) {
            // Continue - product details are optional
        } catch (Exception $e) {
            // Continue - product details are optional
        } catch (Error $e) {
            // Continue - product details are optional
        }

        // Validate all order items have valid quantities before calculating totals
        foreach ($order->get_items() as $item_id => $item) {
            $item_quantity = absint($item->get_quantity());
            $item_price = floatval($item->get_total());
            $item_subtotal = floatval($item->get_subtotal());
            
            if ($item_quantity <= 0) {
                $item->set_quantity(1);
                $item->save();
            }

            if (!is_finite($item_price) || is_nan($item_price) || $item_price < 0) {
                $item->set_total(0);
                $item->save();
            }

            if (!is_finite($item_subtotal) || is_nan($item_subtotal) || $item_subtotal < 0) {
                $item->set_subtotal($item_price);
                $item->save();
            }
        }

        // Validate all fees have valid amounts
        foreach ($order->get_fees() as $fee_id => $fee) {
            $fee_total = floatval($fee->get_total());
            if (!is_finite($fee_total) || is_nan($fee_total)) {
                $fee->set_total(0);
                $fee->save();
            }
        }

        // SKIP calculate_totals() entirely to avoid division by zero errors
        // WooCommerce's calculate_totals() calls get_item_subtotal() which divides by quantity
        // This can cause division by zero if quantities are invalid
        // Instead, we'll manually set totals from the order_data we already have
        
        // Ensure all order items have proper subtotals set
        // The order subtotal is automatically calculated from item subtotals
        foreach ($order->get_items() as $item) {
            $item_total = floatval($item->get_total());
            $item_subtotal = floatval($item->get_subtotal());
            
            // If subtotal is not set or invalid, set it to match total
            if (!is_finite($item_subtotal) || is_nan($item_subtotal) || $item_subtotal <= 0) {
                if (is_finite($item_total) && !is_nan($item_total) && $item_total > 0) {
                    $item->set_subtotal($item_total);
                    $item->save();
                }
            }
        }
        
        // Calculate fees total
        $fees_total = 0;
        foreach ($order->get_fees() as $fee) {
            $fee_total = floatval($fee->get_total());
            if (is_finite($fee_total) && !is_nan($fee_total)) {
                $fees_total += $fee_total;
            }
        }
        
        // Set shipping total (if any)
        $shipping_total = 0;
        foreach ($order->get_shipping_methods() as $shipping) {
            $shipping_total += floatval($shipping->get_total());
        }
        $order->set_shipping_total($shipping_total);
        
        // Calculate discount total from order_data if available
        $discount_total = 0;
        if (isset($order_data['discountTotal']) && is_numeric($order_data['discountTotal'])) {
            $discount_total = floatval($order_data['discountTotal']);
        } else {
            // Calculate discount as difference between item subtotals and totals
            $items_subtotal = 0;
            $items_total = 0;
            foreach ($order->get_items() as $item) {
                $item_subtotal = floatval($item->get_subtotal());
                $item_total = floatval($item->get_total());
                if (is_finite($item_subtotal) && !is_nan($item_subtotal)) {
                    $items_subtotal += $item_subtotal;
                }
                if (is_finite($item_total) && !is_nan($item_total)) {
                    $items_total += $item_total;
                }
            }
            $discount_total = max(0, $items_subtotal - $items_total);
        }
        $order->set_discount_total($discount_total);
        
        // Set tax totals to 0 (since we're not calculating taxes)
        $order->set_cart_tax(0);
        $order->set_shipping_tax(0);
        
        // Set the final total from order_data (which already includes all fees, taxes, etc.)
        $final_total = floatval($order_data['total']);
        if (!is_finite($final_total) || is_nan($final_total) || $final_total < 0) {
            // Fallback: calculate manually from items, fees, shipping, and discount
            $items_total = 0;
            foreach ($order->get_items() as $item) {
                $item_total = floatval($item->get_total());
                if (is_finite($item_total) && !is_nan($item_total) && $item_total >= 0) {
                    $items_total += $item_total;
                }
            }
            $final_total = $items_total + $fees_total + $shipping_total - $discount_total;
        }
        $order->set_total($final_total);
        
        // Force intended status in DB — WooCommerce or hooks during save() can overwrite to "processing"
        if (!empty($intended_order_status)) {
            $post_status = (strpos($intended_order_status, 'wc-') === 0) ? $intended_order_status : 'wc-' . $intended_order_status;
            global $wpdb;
            // When HPOS (custom orders table) is enabled, WooCommerce reads status from wc_orders, not wp_posts — update the authoritative store
            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                $orders_table = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
                $wpdb->update(
                    $orders_table,
                    array('status' => $post_status),
                    array('id' => $order->get_id()),
                    array('%s'),
                    array('%d')
                );
            }
            $wpdb->update(
                $wpdb->posts,
                array('post_status' => $post_status),
                array('ID' => $order->get_id()),
                array('%s'),
                array('%d')
            );
            clean_post_cache($order->get_id());
            $order->set_status($intended_order_status);
        }


        // Save the order without triggering calculate_totals
        // Wrap in error handling in case hooks trigger division by zero
        try {
            $order->save();
        } catch (DivisionByZeroError $save_error) {
            // Try to save again with minimal hooks
            remove_all_actions('woocommerce_update_order');
            remove_all_actions('woocommerce_order_status_changed');
            $order->save();
        } catch (Exception $save_error) {
            // Continue anyway - order data is already set
        } catch (Error $save_error) {
            // Continue anyway - order data is already set
        }

        $order_data['invoice_number'] = $invoice_number;

        // Client Billing runs against the business's agreed prepaid_limit balance and never
        // touches float_balance (per spec: "Client Billing businesses will not have a float
        // account"). Only Instant payment/Float ('prepaid') orders debit the real float_balance.
        $order_balance_meta_key = ($paymentMethod === 'client-billing') ? 'prepaid_limit' : 'float_balance';
        $order_status_label     = ($paymentMethod === 'client-billing') ? 'Client Billing Confirmed' : $order->get_status();

        // Record the exact amount deducted so refund/cancel restoration credits back precisely
        // this amount, regardless of what the order's own total ($order->get_total(), which may
        // include client-supplied adjustments) later reads as.
        update_post_meta($order->get_id(), '_float_balance_deducted_amount', $order_total);
        update_post_meta($order->get_id(), '_float_balance_deducted_meta_key', $order_balance_meta_key);

        log_float_transaction(
            $business_user->ID,
            -$order_total,
            'debited',
            'Order placed: #' . $order->get_id(),
            $order->get_id(),
            $order_data['invoice_number'],
            $order_data['clientReference'],
            $order_status_label,
            $order_balance_meta_key
        );

        // Flatten scheduled_dates array if it's 2D
        $flat_scheduled_dates = [];
        if (is_array($scheduled_dates)) {
            foreach ($scheduled_dates as $date_group) {
                if (is_array($date_group)) {
                    $flat_scheduled_dates = array_merge($flat_scheduled_dates, array_values($date_group));
                } else {
                    $flat_scheduled_dates[] = $date_group;
                }
            }
            // Remove duplicates and empty values
            $flat_scheduled_dates = array_unique(array_filter($flat_scheduled_dates));
            $flat_scheduled_dates = array_values($flat_scheduled_dates); // Re-index
        }

        // CRITICAL: Clean ALL output buffers before sending response
        // This prevents HTML from email templates or other hooks from corrupting JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Prepare response data
        $redirect_url = $order->get_checkout_order_received_url();
        if (empty($redirect_url)) {
            // Fallback if URL is empty
            $redirect_url = wc_get_endpoint_url('order-received', $order->get_id(), wc_get_checkout_url());
        }

        $response_data = [
            'message' => 'Order placed successfully!',
            'order_id' => $order->get_id(),
            'redirect_url' => $redirect_url,
            'scheduled_dates' => $flat_scheduled_dates,
        ];

        // Use wp_send_json_success() instead of manually echoing JSON
        // This function properly handles output buffering, headers, and exits automatically
        wp_send_json_success($response_data);

    } catch (Exception $e) {
        // Ensure no output before sending error
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        wp_send_json_error([
            'message' => 'Order failed: ' . $e->getMessage(),
            'reason'  => $e->getMessage(),
        ]);
    } catch (Error $e) {
        // Ensure no output before sending error
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Check if it's a division by zero error and provide more helpful message
        $error_message = $e->getMessage();
        if (strpos($error_message, 'Division by zero') !== false) {
            $error_message = 'Division by zero error occurred. Please check that all order items have valid quantities and prices.';
        }
        
        wp_send_json_error([
            'message' => 'Order failed please try again later: ' . $error_message,
            'reason'  => $error_message,
        ]);
    }
}


add_action('wp_ajax_place_cod_order', 'place_cod_order_callback');
function send_instant_gift_card_sms($gc_id) {
    // Check if the required SMS broadcast function exists
    if (!function_exists('send_sms_via_smsbroadcast') || empty($gc_id)) {
        return;
    }

    // 1. Verify delivery method requires SMS
    $delivery_method = strtolower(trim(get_post_meta($gc_id, '_delivery_method', true)));
    if (empty($delivery_method) || strpos($delivery_method, 'sms') === false) {
        return; // Skip if SMS is not selected
    }

    // 2. Prevent duplicate texts for this specific gift card
    if (get_post_meta($gc_id, '_instant_sms_sent', true) === 'yes') {
        return;
    }

    // 3. Gather Recipient Phone Number
    $phone_number = get_post_meta($gc_id, '_recipient_phone', true) ?: get_post_meta($gc_id, 'mobile_number', true);
    if (empty($phone_number)) {
        return;
    }

    // 4. Gather Text Variables (Names & Titles)
    $recipient_name = get_post_meta($gc_id, '_recipient_name', true) ?: 'there';
    $gift_card_name = get_post_meta($gc_id, '_gift_card_title', true) ?: get_post_meta($gc_id, '_gift_card_name', true) ?: 'Gift Card';
    
    // Look up sender name with order fallback
    $sender_name    = get_post_meta($gc_id, '_sender_name', true);
    $assoc_order_id = get_post_meta($gc_id, '_order_id', true);
    if (empty($sender_name) && !empty($assoc_order_id)) {
        $order_obj = wc_get_order($assoc_order_id);
        if ($order_obj) {
            $sender_name = trim($order_obj->get_billing_first_name() . ' ' . $order_obj->get_billing_last_name());
        }
    }
    if (empty($sender_name)) {
        $sender_name = 'Gift Cards Plus';
    }


    // 6. Generate Secure Token & Wallet Link
    $recipient_email = get_post_meta($gc_id, '_recipient_email', true);
    if (empty($recipient_email) && !empty($assoc_order_id)) {
        $order_obj = isset($order_obj) ? $order_obj : wc_get_order($assoc_order_id);
        if ($order_obj) {
            $recipient_email = $order_obj->get_billing_email();
        }
    }

    $payload = array(
        'gc_id' => $gc_id,
        'email' => $recipient_email,
    );

    $payload_encoded = base64_encode(wp_json_encode($payload));
    $signature       = hash_hmac('sha256', $payload_encoded, wp_salt('auth'));
    $token           = $payload_encoded . '.' . $signature;

    $wallet_link = add_query_arg(
        array(
            'action' => 'gcp_add_to_wallet',
            'token'  => rawurlencode($token),
        ),
        home_url('/')
    );

    // Apply URL Shortener filter if available
    if (function_exists('gc_shorten_url_for_sms')) {
        $wallet_link = gc_shorten_url_for_sms($wallet_link);
    }

    // 7. Assemble final SMS message
    $sms_message = "Hi {$recipient_name}, you've got a {$gift_card_name} from {$sender_name}! Add it to your giftcards+ wallet: {$wallet_link}.";

    // 8. Fire SMS Broadcast
    $sms_result = send_sms_via_smsbroadcast($phone_number, $sms_message);

    if ($sms_result && isset($sms_result['success']) && $sms_result['success']) {
        update_post_meta($gc_id, '_instant_sms_sent', 'yes');
        update_post_meta($gc_id, '_gift_card_send', 'Delivered (SMS Instant)');
    }
}


// 3.8 — Cancel gift cards issued on a fully-refunded/cancelled order and restore the
// business user's float balance so prepaid funds are not permanently lost.
add_action( 'woocommerce_order_status_refunded',  'gcp_cancel_gift_cards_on_refund', 10, 1 );
add_action( 'woocommerce_order_status_cancelled', 'gcp_cancel_gift_cards_on_refund', 10, 1 );
add_action( 'woocommerce_order_status_reversed',  'gcp_cancel_gift_cards_on_refund', 10, 1 );
add_action( 'woocommerce_order_partially_refunded', 'gcp_restore_float_on_partial_refund', 10, 2 );
function gcp_cancel_gift_cards_on_refund( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    // Cancel all gift cards on this order.
    foreach ( $order->get_items() as $item ) {
        $gift_card_post_id = wc_get_order_item_meta( $item->get_id(), '_gift_card_post_id', true );
        if ( ! $gift_card_post_id ) {
            continue;
        }
        update_post_meta( (int) $gift_card_post_id, '_card_status',          'cancelled' );
        update_post_meta( (int) $gift_card_post_id, '_swap_available_amount', 0 );
        update_post_meta( (int) $gift_card_post_id, '_current_balance',       0 );
    }

    // Restore the business user's float balance if this order was paid via float_balance.
    // Guard against duplicate hook fires (order refunded + cancelled both fire this hook).
    if ( get_post_meta( $order_id, '_float_balance_restored', true ) === '1' ) {
        return;
    }

    $business_user_id = (int) $order->get_customer_id();
    if ( ! $business_user_id ) {
        return;
    }

    // Client Billing orders debit prepaid_limit at placement, not float_balance (see
    // place_cod_order_callback) — restore whichever balance was actually deducted.
    // Older orders placed before this distinction existed have no stored meta key, so
    // default to float_balance to preserve their original (pre-fix) restoration behavior.
    $balance_meta_key = get_post_meta( $order_id, '_float_balance_deducted_meta_key', true ) ?: 'float_balance';
    $status_label     = ($balance_meta_key === 'prepaid_limit') ? 'Client Billing Confirmed' : 'Complete';

    // Restore exactly what was deducted at placement time — not $order->get_total(), which
    // can differ from the deducted amount (e.g. client-supplied total adjustments).
    // For partial refunds, WooCommerce fires a separate woocommerce_order_partially_refunded
    // hook — full restoration here covers only fully refunded/cancelled/reversed orders.
    $deducted_amount = get_post_meta( $order_id, '_float_balance_deducted_amount', true );
    $order_total      = $deducted_amount !== '' ? (float) $deducted_amount : (float) $order->get_total();
    $current_balance  = (float) get_user_meta( $business_user_id, $balance_meta_key, true );

    log_float_transaction(
        $business_user_id,
        $order_total,
        'credited',
        'Order refunded/cancelled: #' . $order->get_id(),
        $order->get_id(),
        '',
        '',
        $status_label,
        $balance_meta_key
    );
    update_post_meta( $order_id, '_float_balance_restored', '1' );

    $order->add_order_note(
        sprintf(
            '%s restored: $%.2f credited back to user #%d (previous balance: $%.2f, new balance: $%.2f).',
            ($balance_meta_key === 'prepaid_limit') ? 'Prepaid limit balance' : 'Float balance',
            $order_total,
            $business_user_id,
            $current_balance,
            $current_balance + $order_total
        )
    );
}

// 3.8 — Restore the partial refund amount to the business user's float balance.
// WooCommerce fires woocommerce_order_partially_refunded with the refund object ID,
// not the refund amount, so we fetch the refund total from the refund object.
function gcp_restore_float_on_partial_refund( $order_id, $refund_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $refund = wc_get_order( $refund_id );
    if ( ! $refund ) {
        return;
    }

    // Refund totals are stored as negative values in WooCommerce.
    $refund_amount = abs( (float) $refund->get_total() );
    if ( $refund_amount <= 0 ) {
        return;
    }

    $business_user_id = (int) $order->get_customer_id();
    if ( ! $business_user_id ) {
        return;
    }

    // Restore to the same balance that was originally deducted at placement time
    // (float_balance for Instant/Float orders, prepaid_limit for Client Billing).
    $balance_meta_key = get_post_meta( $order_id, '_float_balance_deducted_meta_key', true ) ?: 'float_balance';
    $status_label     = ($balance_meta_key === 'prepaid_limit') ? 'Client Billing Confirmed' : 'Complete';
    $current_balance  = (float) get_user_meta( $business_user_id, $balance_meta_key, true );

    log_float_transaction(
        $business_user_id,
        $refund_amount,
        'credited',
        'Partial refund: #' . $order->get_id(),
        $order->get_id(),
        '',
        '',
        $status_label,
        $balance_meta_key
    );

    $order->add_order_note(
        sprintf(
            'Partial refund: $%.2f credited back to user #%d %s (previous: $%.2f, new: $%.2f). Refund ID: %d.',
            $refund_amount,
            $business_user_id,
            ($balance_meta_key === 'prepaid_limit') ? 'prepaid limit balance' : 'float balance',
            $current_balance,
            $current_balance + $refund_amount,
            $refund_id
        )
    );
}

//✅ Automatically send pending gift cards when order becomes 'completed'
// Priority 30 — runs after send_blackhawk_gift_card_email_on_order (priority 25) so the
// _gift_card_email_sent flag is already set before we reach this function.
add_action('woocommerce_order_status_completed', 'send_pending_gift_cards_after_completion', 30);
function send_pending_gift_cards_after_completion($order_id)
{
    // Manual/bulk order flow (place_cod_order) already triggers emails via send_blackhawk_gift_card_email_on_order.
    // Skip here to avoid sending the same gift card emails twice to the same recipient.
    if (get_post_meta($order_id, '_place_cod_order', true) === '1') {
        return;
    }

    // send_blackhawk_gift_card_email_on_order (priority 25) already handled this order.
    if (get_post_meta($order_id, '_gift_card_email_sent', true) === 'yes') {
        return;
    }
    $data_get_value = 'gc_order';
    order_complete($order_id, NULL, $data_get_value);
    // if (!$order)
    //     return;

    // $gift_cards = get_posts([
    //     'post_type' => 'gift_card',
    //     'post_status' => 'publish',
    //     'posts_per_page' => -1,
    //     'meta_query' => [
    //         [
    //             'key' => '_gift_card_send',
    //             'value' => 'Pending Order Completion',
    //             'compare' => '=',
    //         ],
    //     ],
    // ]);

    // foreach ($gift_cards as $gift_card) {

    //     $card_name = get_post_meta($gift_card->ID, '_product_sku', true);
    //     $order_id = get_post_meta($gift_card->ID, '_order_id', true);
    //     $meta_data = get_post_meta($order_id);
    //     $meta_key = $order_id . '_pro_details';
    //     $pro_data = get_post_meta($order_id, $meta_key, true);

    //     $details = [
    //         'recipient_name' => get_post_meta($gift_card->ID, '_recipient_name', true),
    //         'order_product_data' => $pro_data,
    //         'recipient_email' => get_post_meta($gift_card->ID, '_recipient_email', true),
    //         'amount' => get_post_meta($gift_card->ID, '_price', true),
    //         'card_number' => get_post_meta($gift_card->ID, '_invoice_number', true),
    //         '_activation_expiry_date' => get_post_meta($gift_card->ID, '_activation_expiry_date', true),
    //         'logo_giftcardplus' => wp_get_attachment_url('6230'),
    //         'logo_brand_main' => wp_get_attachment_url('5824'),
    //         'logo_brand_top' => wp_get_attachment_url('5108'),
    //         'logo_footer' => wp_get_attachment_url('5370'),
    //         'email_logo' => wp_get_attachment_url('5371'),
    //         'gift_card_id' => $gift_card->ID,
    //         'support_email' => 'support@giftcardsplus.com.au',
    //     ];

    // $logger->info("📦 Sending Gift Card #{$gift_card->ID}", $context);


    // send_giftcard_email_with_pdf($details, $details['recipient_email'], $order_id);
    // update_post_meta($gift_card->ID, '_gift_card_send', 'Sent After Completion');

    // $logger->info("Gift card sent for Gift Card ID {$gift_card->ID}", $context);

    // }

    // $logger->info("🎉 Finished sending all gift cards for Order ID {$order_id}", $context);


}


/**
 * Send SMS notification when order is completed/payment is complete
 * Sends SMS to recipients who have SMS delivery method selected
 */
// add_action('woocommerce_payment_complete', 'send_gift_card_sms_on_order_completion', 30, 1);
add_action('woocommerce_order_status_completed', 'send_gift_card_sms_on_order_completion', 30, 1);
add_action('woocommerce_order_status_processing', 'send_gift_card_sms_on_order_completion', 30, 1);

function send_gift_card_sms_on_order_completion($order_id) {
    // Check if SMS function exists
    if (!function_exists('send_sms_via_smsbroadcast')) {
        return;
    }

    // Manual order flow handles SMS via send_blackhawk_gift_card_email_on_order →
    // send_gift_cards_email_to_recipient. Skip here to avoid a duplicate SMS.
    if (get_post_meta($order_id, '_place_cod_order', true) === '1') {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    // Loop through order items
    foreach ($order->get_items() as $item_id => $item) {

        // CRITICAL FIX: Check if an SMS was already successfully sent for this specific item
        if (wc_get_order_item_meta($item_id, '_sms_sent', true) === 'yes') {
            continue; // Skip this item completely to avoid duplicate texts
        }
        // Get delivery method
        $delivery_method = wc_get_order_item_meta($item_id, '_delivery_method', true);
        
        // Skip if delivery method doesn't include SMS
        if (empty($delivery_method) || ($delivery_method !== 'sms' && $delivery_method !== 'email_sms')) {
            continue;
        }
        
        // Get recipient phone number
        $phone_number = wc_get_order_item_meta($item_id, '_recipient_phone', true);
        if (empty($phone_number)) {
            $phone_number = wc_get_order_item_meta($item_id, 'mobile_number', true);
        }
        
        if (empty($phone_number)) {
            continue;
        }
        
        // Get recipient name
        $recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
        if (empty($recipient_name)) {
            $recipient_name = 'there'; // Fallback
        }
        
        // Get sender name
        $sender_name = wc_get_order_item_meta($item_id, '_sender_name', true);
        if (empty($sender_name)) {
            // Try to get from order meta
            $sender_name = $order->get_meta('_sender_name', true);
            if (empty($sender_name)) {
                // Fallback to billing name
                $sender_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                if (empty($sender_name)) {
                    $sender_name = 'Gift Cards Plus';
                }
            }
        }
        
        // Get gift card name/title
        $gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_title', true);
        if (empty($gift_card_name)) {
            $gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_name', true);
        }
        if (empty($gift_card_name)) {
            $gift_card_name = $item->get_name(); // Fallback to product name
        }
        
        // Get gift card post ID for wallet link
        $gift_card_post_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);

        // send_gift_cards_email_to_recipient already sent the SMS and marked it Delivered
        if (!empty($gift_card_post_id)) {
            $gc_send_status = get_post_meta((int) $gift_card_post_id, '_gift_card_send', true);
            if (strpos((string) $gc_send_status, 'Delivered') !== false) {
                continue;
            }
        }

        // Get recipient email for wallet link
        $recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
        if (empty($recipient_email)) {
            $recipient_email = $order->get_billing_email();
        }
        
        // Get wallet code (security pin)
        $wallet_code = '1234'; // Default fallback
        if (!empty($gift_card_post_id)) {
            $wallet_code = get_post_meta($gift_card_post_id, 'gcard_security_pin', true);
            if (empty($wallet_code)) {
                $wallet_code = get_post_meta($gift_card_post_id, '_gcard_security_pin', true);
            }
            if (empty($wallet_code)) {
                $wallet_code = get_post_meta($gift_card_post_id, 'security_pin', true);
            }
            if (empty($wallet_code)) {
                $wallet_code = '1234'; // Fallback
            }
        }

        $payload = array(
            'gc_id' => $gift_card_post_id,
            'email' => $recipient_email,
        );

        $payload_encoded = base64_encode( wp_json_encode( $payload ) );

        $signature = hash_hmac(
            'sha256',
            $payload_encoded,
            wp_salt( 'auth' )
        );

        $token = $payload_encoded . '.' . $signature;

        $wallet_link = add_query_arg(
            array(
                'action' => 'gcp_add_to_wallet',
                'token'  => rawurlencode( $token ),
            ),
            home_url('/')
        );

        // Shorten URL for SMS
        if (function_exists('gc_shorten_url_for_sms')) {
            $wallet_link = gc_shorten_url_for_sms($wallet_link);
        }
        
        // Commented on 20260129
        // Build wallet link
        // $wallet_link = '';
        // if (!empty($gift_card_post_id) && !empty($recipient_email)) {
        //     $base_wallet_url = site_url('/');
        //     $wallet_link = add_query_arg(array(
        //         'action' => 'gcp_add_to_wallet',
        //         'gc_id' => $gift_card_post_id,
        //         'email' => $recipient_email
        //     ), $base_wallet_url);
            
        // }
        
        // Build SMS message
        $sms_message = "Hi {$recipient_name}, you've got a {$gift_card_name} from {$sender_name}!";
        
        if (!empty($wallet_link)) {
            $sms_message .= " Add it to your wallet here: {$wallet_link}";
        }
        
        $sms_message .= " Wallet Code: {$wallet_code}.";
        
        // Send SMS
        $sms_result = send_sms_via_smsbroadcast($phone_number, $sms_message);
        
        if ($sms_result && isset($sms_result['success']) && $sms_result['success']) {
            wc_update_order_item_meta($item_id, '_sms_sent', 'yes');
        }
    }
}

function order_complete($bhdata, $otherData, $data_get_value)
{

    if ($data_get_value == 'bhn_order') {
        // Merge both arrays
        $mergedata = array_merge($bhdata, $otherData);
        $order_id = $mergedata['order_id'];

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Business user lookup (same pattern as single-order-functions.php / place_cod_order_callback),
        // so BHN/Blackhawk orders also get the Havit/Gyprock brand banner instead of always falling
        // back to the cake banner.
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

        // Fetch pending gift cards
        $gift_cards = get_posts([
            'post_type' => 'gift_card',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_gift_card_send',
                    'value' => 'Pending Order Completion',
                    'compare' => '=',
                ],
            ],
        ]);

        foreach ($gift_cards as $gift_card) {

            $order_ids = get_post_meta($gift_card->ID, '_order_id', true);

            // Product details
            $meta_key = $order_id . '_pro_details';
            $pro_data = get_post_meta($order_id, $meta_key, true);

            // Fetch product brand from order items
            $brand_name     = '';
            $brand_logo_url = wp_get_attachment_url('5824');
            foreach ($order->get_items() as $item) {
                $product_id  = $item->get_product_id();
                $brand_terms = wp_get_post_terms($product_id, 'product_brand');
                if (!empty($brand_terms) && !is_wp_error($brand_terms)) {
                    $brand_name    = $brand_terms[0]->name;
                    $brand_logo_id = get_term_meta($brand_terms[0]->term_id, 'thumbnail_id', true);
                    if ($brand_logo_id) {
                        $brand_logo_url = wp_get_attachment_url($brand_logo_id);
                    }
                }
                break;
            }
            // Retrieve and decrypt card number from database
            $card_number = '';
            $encrypted_card_number = get_post_meta($gift_card->ID, '_bhn_card_number_enc', true);

            // If not found in gift card meta, try order meta
            if (empty($encrypted_card_number)) {
                $encrypted_card_number = get_post_meta($order_id, '_bhn_card_number_enc', true);
            }

            if (!empty($encrypted_card_number)) {
                // Load decryption functions if not already loaded
                if (!function_exists('decrypt_giftcard')) {
                    require_once(get_template_directory() . '/inc/gc_number_functions.php');
                }

                $encryption_key = function_exists('gcp_get_gift_card_encryption_key') ? gcp_get_gift_card_encryption_key() : ( defined('BHN_ENCRYPTION_SECRET') ? BHN_ENCRYPTION_SECRET : ( defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '' ) );

                try {
                    $card_number = decrypt_giftcard($encrypted_card_number, $encryption_key);
                } catch (Exception $e) {
                    // Fallback to original data if decryption fails
                    $card_number = $otherData['eGifts'][0]['cardNumber'] ?? '';
                }
            } else {
                // Fallback to original data if encrypted version doesn't exist
                $card_number = $otherData['eGifts'][0]['cardNumber'] ?? '';
            }

            // Build details array
            $details = [
                'recipient_name' => get_post_meta($gift_card->ID, '_recipient_name', true),
                'order_product_data' => $pro_data,
                'recipient_email' => get_post_meta($gift_card->ID, '_recipient_email', true),

                // BHN data
                'amount' => $otherData['eGifts'][0]['amount'] ?? '',
                'card_number' => $card_number,
                'pin' => $otherData['eGifts'][0]['pin'] ?? '',
                'url' => $otherData['eGifts'][0]['url'] ?? '',
                'contentProviderCode' => $otherData['eGifts'][0]['contentProviderCode'] ?? '',
                'activationAccountNumber' => $otherData['eGifts'][0]['activationAccountNumber'] ?? '',
                'brand_name' => $brand_name,
                'business_user_name' => $business_user_name,
                'business_user_email' => $business_user_email,

                // Template images
                'logo_giftcardplus' => wp_get_attachment_url('6230'),
                'logo_brand_main' => $brand_logo_url,
                'logo_brand_top' => wp_get_attachment_url('5108'),
                'logo_footer' => wp_get_attachment_url('5370'),
                'email_logo' => wp_get_attachment_url('5371'),
                'main_giftcardplus' => wp_get_attachment_url('6230'),

                // Gift card information
                'gift_card_id' => $gift_card->ID,
                'activation' => date('d/m/Y'),
                'expiry' => date('d/m/Y', strtotime('+1 year')),
                'card_name' => get_the_title($gift_card->ID),
                'support_email' => 'support@giftcardsplus.com.au',
            ];

            // VALIDATION
            if (empty($details['recipient_email'])) {
                continue;
            }

            if (empty($order_ids)) {
                continue;
            }

            $send_status = send_giftcard_email_with_pdf(
                $details,
                $details['recipient_email'],
                $order_ids
            );

            // Update meta
            update_post_meta($gift_card->ID, '_gift_card_send', 'Sent After Completion');
        }

    } else {

        $order_id = $bhdata;
        $current_order_id = $order_id; // Store the current order ID to avoid overwriting
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Query for gift cards from the current order only
        // This ensures we only process gift cards belonging to this specific order
        $gift_cards = get_posts([
            'post_type' => 'gift_card',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_order_id',
                    'value' => $current_order_id,
                    'compare' => '='
                ]
            ]
        ]);

        foreach ($gift_cards as $gift_card) {

            $card_name = get_post_meta($gift_card->ID, '_product_sku', true);
            $gift_card_order_id = get_post_meta($gift_card->ID, '_order_id', true);
            
            // Additional safety check: Only process gift cards that belong to the current order
            if ($gift_card_order_id != $current_order_id) {
                continue;
            }

            $gift_card_send_status = get_post_meta($gift_card->ID, '_gift_card_send', true);
            $allowed_statuses = ['Pending Order Completion', 'Instant', ''];
            if (!in_array($gift_card_send_status, $allowed_statuses)) {
                if ($gift_card_send_status === 'Sent After Completion') {
                    continue;
                }
            }
            
            // Use current_order_id to ensure we're working with the correct order
            $meta_data = get_post_meta($current_order_id);
            $meta_key = $current_order_id . '_pro_details';
            $pro_data = get_post_meta($current_order_id, $meta_key, true);

            $gcard_pin = get_post_meta($gift_card->ID, 'gcard_security_pin', true);
            if ( empty($gcard_pin) ) {
                $gcard_pin = str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
                update_post_meta($gift_card->ID, 'gcard_security_pin', $gcard_pin);
            }

            // Get encrypted gift card number
            $encrypted_card_number = get_post_meta($gift_card->ID, '_gift_card_number_enc', true);
            $decrypted_card_number = decrypt_giftcard_no($encrypted_card_number);

            
            // If encrypted card number exists, use it; otherwise fallback to invoice number
            if (!empty($decrypted_card_number)) {
                $card_number_for_details = $decrypted_card_number;
            } else {
                $card_number_for_details = get_post_meta($gift_card->ID, '_invoice_number', true);
            }
            // Fetch product brand for this gift card's order item
            $brand_name     = '';
            $brand_logo_url = wp_get_attachment_url('5824');
            foreach ($order->get_items() as $item) {
                $product_id  = $item->get_product_id();
                $brand_terms = wp_get_post_terms($product_id, 'product_brand');
                if (!empty($brand_terms) && !is_wp_error($brand_terms)) {
                    $brand_name    = $brand_terms[0]->name;
                    $brand_logo_id = get_term_meta($brand_terms[0]->term_id, 'thumbnail_id', true);
                    if ($brand_logo_id) {
                        $brand_logo_url = wp_get_attachment_url($brand_logo_id);
                    }
                }
                break;
            }
            $details = [
                'recipient_name' => get_post_meta($gift_card->ID, '_recipient_name', true),
                'order_product_data' => $pro_data,
                'recipient_email' => get_post_meta($gift_card->ID, '_recipient_email', true),
                'amount' => get_post_meta($gift_card->ID, '_price', true),
                'card_number' => $card_number_for_details,
                'card_pin' => $gcard_pin,
                '_activation_expiry_date' => get_post_meta($gift_card->ID, '_activation_expiry_date', true),
                'logo_giftcardplus' => wp_get_attachment_url('6230'),
                'logo_brand_main' => $brand_logo_url,
                'logo_brand_top' => wp_get_attachment_url('5108'),
                'logo_footer' => wp_get_attachment_url('5370'),
                'email_logo' => wp_get_attachment_url('5371'),
                'gift_card_id' => $gift_card->ID,
                'support_email' => 'support@giftcardsplus.com.au',
                'brand_name'  => $brand_name,
                'url'         => get_post_meta($gift_card->ID, '_bhn_egift_url', true) ?: '',
            ];

            send_giftcard_email_with_pdf($details, $details['recipient_email'], $current_order_id);
            update_post_meta($gift_card->ID, '_gift_card_send', 'Sent After Completion');

        }

    }

}


add_filter('woocommerce_email_attachments', function ($attachments, $email_id, $order, $email) {
    if ($email_id === 'customer_completed_order' && $order instanceof WC_Order) {
        $pdf_path = get_post_meta($order->get_id(), '_giftcard_pdf_path', true);
        if (!empty($pdf_path) && file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }
    }
    return $attachments;
}, 10, 4);

// Disable WooCommerce default completed order email — we send our own below.
add_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_false' );

// Keep order in processing if any item has a future scheduled delivery date.
add_filter( 'woocommerce_payment_complete_order_status', function( $status, $_order_id, $order ) {
    foreach ( $order->get_items() as $item_id => $item ) {
        // Single product page flow saves scheduled date as '_scheduled_date' on order item
        $scheduled_date = wc_get_order_item_meta( $item_id, '_scheduled_date', true );
        if ( empty( $scheduled_date ) ) {
            $scheduled_date = wc_get_order_item_meta( $item_id, 'schedule_date', true );
        }
        if ( ! empty( $scheduled_date ) && strtotime( $scheduled_date ) > current_time( 'timestamp' ) ) {
            return 'processing';
        }
    }
    return $status;
}, 10, 3 );

// Send custom completed order email from the 'complete-order' Email Template post.
add_action( 'woocommerce_order_status_completed', function( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    if ( ! function_exists( 'et_get_template_by_slug' ) ) return;

    // ── Determine banner color by user type ──────────────────────────────────
    $content_h_color = '#67D6C8';
    $order_user_id   = $order->get_user_id();
    if ( $order_user_id ) {
        $user = get_user_by( 'id', $order_user_id );
        if ( $user && in_array( 'business_user', (array) $user->roles ) ) {
            $content_h_color = '#14A0B4';
        }
    }

    // ── Build tokens ─────────────────────────────────────────────────────────
    $first_name    = $order->get_billing_first_name();
    $order_number  = $order->get_order_number();
    $meta_referral = $order->get_meta( '_client_reference' );
    $order_referral = ( ! empty( trim( $meta_referral ) ) )
        ? 'Your reference number for this is ' . $meta_referral . '.'
        : '';

    $delivery_dates = [];
    foreach ( $order->get_items() as $item_id => $item ) {
        $scheduled_date = wc_get_order_item_meta( $item_id, '_scheduled_date', true );
        if ( empty( $scheduled_date ) ) {
            $scheduled_date = wc_get_order_item_meta( $item_id, 'schedule_date', true );
        }
        if ( empty( $scheduled_date ) ) {
            $scheduled_date = wc_get_order_item_meta( $item_id, '_scheduled_gift_card_delivery', true );
        }
        if ( ! empty( $scheduled_date ) ) {
            $delivery_dates[] = strtotime( $scheduled_date );
        }
    }
    $min_date = ! empty( $delivery_dates ) ? date( 'd M Y', min( $delivery_dates ) ) : '';
    $max_date = ! empty( $delivery_dates ) ? date( 'd M Y', max( $delivery_dates ) ) : '';
    $scheduled_delivery_time = ( $min_date && $max_date && $min_date !== $max_date )
        ? $min_date . ' - ' . $max_date
        : $min_date;

    // ── Get template ─────────────────────────────────────────────────────────
    $tpl = et_get_template_by_slug( 'complete-order', [
        'first_name'              => $first_name,
        'order_number'            => $order_number,
        'order_referral'          => $order_referral,
        'scheduled_delivery_time' => $scheduled_delivery_time,
        'site_url'                => site_url(),
        'banner_color'            => $content_h_color,
    ] );

    if ( ! $tpl ) return;

    // Remove scheduled delivery sentence if no date set
    if ( empty( $scheduled_delivery_time ) ) {
        $tpl['body'] = preg_replace(
            '/<p[^>]*>Your digital gift card\(s\) are locked in for delivery at\s*\.\s*View.*?<\/p>/is',
            '',
            $tpl['body']
        );
    }

    // ── Attachments (PDF) ────────────────────────────────────────────────────
    $attachments = [];
    $pdf_path    = get_post_meta( $order_id, '_giftcard_pdf_path', true );
    if ( ! empty( $pdf_path ) && file_exists( $pdf_path ) ) {
        $attachments[] = $pdf_path;
    }

    wp_mail(
        $order->get_billing_email(),
        $tpl['subject'],
        $tpl['body'],
        $tpl['headers'],
        $attachments
    );
}, 10 );


// WB-3.12: PDF_check_native debug endpoint removed — was triggering unauthenticated email + PDF generation.


add_action('send_gift_cards_email_event', 'handle_send_gift_cards_email_event', 10, 2);
function handle_send_gift_cards_email_event($scheduled_card, $attachments = [])
{
    // Accept either a gift_card_post_id integer (new compact cron arg) or a full details array (legacy).
    if (is_numeric($scheduled_card)) {
        $payload = get_post_meta((int) $scheduled_card, '_scheduled_email_payload', true);
        if (!empty($payload) && is_array($payload)) {
            $scheduled_card = $payload;
        } else {
            return; // no payload saved, nothing to send
        }
    }

    if (empty($scheduled_card) || !is_array($scheduled_card)) {
        return;
    }



    //mail('aakif@elsner.com.au', 'SCHEDULED EMAIL EXECUTED', pr($scheduled_card['gift_card_number']));
    send_gift_cards_email_to_recipient($scheduled_card, $attachments);

    // Get order ID
    if (!empty($scheduled_card['order_id'])) {
        $order_id = $scheduled_card['order_id'];
    } elseif (!empty($scheduled_card['gift_card_post_id'])) {
        $order_id = get_post_meta($scheduled_card['gift_card_post_id'], '_order_id', true);
    } else {
        $order_id = null;
    }

    if (empty($order_id)) {
        return; // no order, nothing to log
    }

    // Fetch all gift cards for the order
    $gift_cards = get_posts([
        'post_type' => 'gift_card', // Change if different
        'meta_key' => '_order_id',
        'meta_value' => $order_id,
        'numberposts' => -1,
        'post_status' => 'any',
    ]);

    if (empty($gift_cards)) {
        return;
    }

    // Find the maximum scheduled date among all cards
    $max_date_timestamp = 0;
    foreach ($gift_cards as $gift_card) {
        $gc_scheduled_date = get_post_meta($gift_card->ID, '_scheduled_gift_card_delivery', true);
        if (!empty($gc_scheduled_date)) {
            $timestamp = strtotime($gc_scheduled_date);
            if ($timestamp > $max_date_timestamp) {
                $max_date_timestamp = $timestamp;
            }
        }
    }

    // If this triggered card is NOT the last scheduled one, skip logging
    $this_card_timestamp = !empty($scheduled_card['scheduled_date']) ? strtotime($scheduled_card['scheduled_date']) : 0;
    if ($this_card_timestamp < $max_date_timestamp) {
        return;
    }

    // At this point, we know this is the LAST scheduled card — mark order complete
    $order = wc_get_order($order_id);

    if ($order) {
        $order->update_status('completed');
    }
}

// ── Cron: auto-complete single product page orders whose scheduled date has passed ──
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'gcp_auto_complete_scheduled_orders' ) ) {
        wp_schedule_event( time(), 'hourly', 'gcp_auto_complete_scheduled_orders' );
    }
} );

add_action( 'gcp_auto_complete_scheduled_orders', 'gcp_run_auto_complete_scheduled_orders' );
function gcp_run_auto_complete_scheduled_orders() {
    $orders = wc_get_orders( [
        'status'  => 'processing',
        'limit'   => 100,
        'orderby' => 'date',
        'order'   => 'ASC',
    ] );

    $current_ts = current_time( 'timestamp' );

    foreach ( $orders as $order ) {
        $order_id = $order->get_id();

        if ( get_post_meta( $order_id, '_place_cod_order', true ) === '1' ) {
            // Manual order flow fallback: if the WP-Cron single event for send_gift_cards_email_event
            // was missed (e.g. no site traffic at the exact scheduled time), fire it now via do_action
            // so the email sends and handle_send_gift_cards_email_event marks the order completed.
            foreach ( $order->get_items() as $item_id => $item ) {
                $gc_post_id = wc_get_order_item_meta( $item_id, '_gift_card_post_id', true );
                if ( empty( $gc_post_id ) ) {
                    continue;
                }
                $send_status = get_post_meta( $gc_post_id, '_gift_card_send', true );
                if ( $send_status === 'Delivered' || $send_status === 'Sent After Completion' ) {
                    continue; // already sent
                }
                $scheduled_date = wc_get_order_item_meta( $item_id, '_scheduled_date', true );
                if ( empty( $scheduled_date ) || strtotime( $scheduled_date ) > $current_ts ) {
                    continue; // not yet due or no date
                }
                // Fire the event — handle_send_gift_cards_email_event reads _scheduled_email_payload
                do_action( 'send_gift_cards_email_event', (int) $gc_post_id );
            }
            continue;
        }

        // Single product page flow — check order item meta for scheduled date
        $latest_ts = 0;
        foreach ( $order->get_items() as $item_id => $item ) {
            $scheduled_date = wc_get_order_item_meta( $item_id, '_scheduled_date', true );
            if ( empty( $scheduled_date ) ) {
                $scheduled_date = wc_get_order_item_meta( $item_id, 'schedule_date', true );
            }
            if ( ! empty( $scheduled_date ) && strtotime( $scheduled_date ) > $latest_ts ) {
                $latest_ts = strtotime( $scheduled_date );
            }
        }

        // Only complete if scheduled date existed and has now passed
        if ( $latest_ts > 0 && $latest_ts <= $current_ts ) {
            $order->update_status( 'completed', 'Auto-completed: scheduled delivery date passed.' );
        }
    }
}




add_filter('woocommerce_order_received_verify_known_shoppers', '__return_false');



// Add custom order meta to admin
add_action('woocommerce_admin_order_data_after_billing_address', 'display_bhn_order_number_in_admin', 9, 1);
add_action('woocommerce_admin_order_data_after_billing_address', 'display_cod_order_details', 10, 1);



/**
 * Display Blackhawk (BHN) order number on the single order page in admin.
 * Fetches from order meta _bhn_order_number or from wp_bhi_fetch_order_data.order_number.
 */
function display_bhn_order_number_in_admin($order)
{
    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }
    $order_id = $order->get_id();
    $bhn_order_number = $order->get_meta('_bhn_order_number');
    if (empty($bhn_order_number)) {
        global $wpdb;
        $table = $wpdb->prefix . 'bhi_fetch_order_data';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT order_number FROM {$table} WHERE woo_order_no = %d LIMIT 1",
            $order_id
        ), ARRAY_A);
        $bhn_order_number = isset($row['order_number']) ? $row['order_number'] : '';
    }
    if ($bhn_order_number !== '') {
        echo '<div class="bhn-order-number" style="margin: 1em 0;">';
        echo '<p><strong>' . esc_html__('Blackhawk (BHN) Order Number:', 'woocommerce') . '</strong> ' . esc_html($bhn_order_number) . '</p>';
        echo '</div>';
    }
}

function display_cod_order_details($order)
{
    $sender_name = $order->get_meta('_sender_name');
    $sender_email = $order->get_meta('_sender_email');
    $order_type = $order->get_meta('_order_type');

    $recipients = $order->get_meta('_gift_recipients');
    $fullfillment_total = $order->get_meta('fullfillment_total');
    $delivery_total = $order->get_meta('delivery_total');


    // if ($gift_expiry_type === 'no_activation_expiry') {
    //     $gift_expiry_type = 'No Activation Expiry';
    // } elseif ($gift_expiry_type === 'delayed_activation') {
    //     $gift_expiry_type = 'Delayed Activation';
    // } elseif ($gift_expiry_type === 'custom_date') {
    //     $gift_expiry_type = 'Custom Date';
    // }

    echo '<h3>Gift Card Details</h3>';
    echo '<p><strong>Sender:</strong> ' . esc_html($sender_name) . '</p>';
    echo '<p><strong>Sender Email :</strong> ' . esc_html($sender_email) . '</p>';
    echo '<h3><strong>Order Type :</strong> ' . esc_html($order_type) . '</h3>';
    echo '<hr/><hr/>';


    $recipients_details = array();
    foreach ($order->get_items() as $item_id => $item) {
        $gift_card_number = $item->get_meta('_gift_card_number_enc');
        $gift_card_post_id = $item->get_meta('_gift_card_post_id');
        $edit_gift_card_post_id = site_url() . '/wp-admin/post.php?post=' . $gift_card_post_id . '&action=edit';
        $temp_str = '';
        $email = $item->get_meta('_recipient_email');

        $post_id = $gift_card_post_id;  // Assuming each item has a post_id linked to the order
        $phone = get_post_meta($post_id, '_recipient_phone', true);  // Get phone number from postmeta

        $key = '<strong>' . esc_html($item->get_meta('_recipient_name')) . '</strong> (' . ($email ? ' - ' . esc_html($email) : '') . ')' . ($phone ? ' / ' . esc_html($phone) : '');
        /*echo '<li>';
            echo '<strong>' . esc_html($item->get_meta('_recipient_name')) . '</strong> (' . esc_html($item->get_meta('_recipient_email')) . ')';
            echo '<ul>';*/
        $temp_str = '<li>';
        // $temp_str .= 'Gift Card No.: <a href="' . $edit_gift_card_post_id . '" target="_BLANK" aria-label="' . esc_html($gift_card_number) . '">' . esc_html($gift_card_number) . '</a>';
        $temp_str .= '<br>' . esc_html($item->get_meta('_gift_card_sku')) . ' - ' . wc_price($item->get_meta('_gift_card_price'));
        $temp_str .= '<br>Message: ' . esc_html($item->get_meta('gift_message'));
        $temp_str .= '<br>Subject: ' . esc_html($item->get_meta('gift_subject'));
        $temp_str .= '<br>Text Message: ' . esc_html($item->get_meta('gift_text_message'));
        $temp_str .= '<br>Delivery: ' . esc_html($item->get_meta('_delivery_method'));
        if ($phone) {
            $temp_str .= '<br>Phone Number: ' . esc_html($phone);
        }
        $temp_str .= '</li>';
        $recipients_details[$key][$gift_card_number] = $temp_str;
    }

    echo '<h2><strong>Recipients:</strong></h2>';
    echo '<ul>';
    $ind = 1;
    echo '<hr/><hr/>';
    foreach ($recipients_details as $key => $value) {
        echo '<li>';
        echo '<strong>' . $ind . ' ~ </strong>' . $key;
        echo '<ul>';
        echo implode('<hr/>', $value);
        echo '<ul>';
        echo '</li>';
        echo '<hr/><hr/>';
        $ind++;
    }
    echo '</ul>';



    /*if ($recipients) {
        echo '<h4>Recipients:</h4>';
        echo '<ul>';
        foreach ($recipients as $recipient) {
            echo '<li>';
            echo '<strong>' . esc_html($recipient['name']) . '</strong> (' . esc_html($recipient['email']) . ')';
            echo '<ul>';
            foreach ($recipient['products'] as $product) {
                echo '<li>';
                echo esc_html($product['sku']) . ' - ' . wc_price($product['price']);
                echo '<br>Message: ' . esc_html($product['message']);
                echo '<br>Delivery: ' . esc_html($product['deliveryMethod']);
                echo '</li>';
            }
            echo '</ul>';
            echo '</li>';
        }
        echo '</ul>';
    }*/

    $business_name = $order->get_meta('_business_name');
    $order_name = $order->get_meta('_order_name');
    $po_number = $order->get_meta('_po_number');
    $_additional_reference = $order->get_meta('_additional_reference');
    $_client_reference = $order->get_meta('_client_reference');
    $invoice_number = $order->get_meta('_invoice_number');
    $_campaign = $order->get_meta('_campaign');





    echo '<br/>';
    echo '<h2><strong>Business Details:</strong></h2>';
    echo '<hr/><hr/>';
    echo '<h2><strong>Invoice Number : </strong> ' . esc_html($invoice_number) . '</h2>';
    echo '<hr/>';


    /*if ($gift_card_number) {
        echo '<p><strong>Gift Card Number:</strong> ' . esc_html($gift_card_number) . '</p>';
    }*/
    echo '<h3>Business Name : ' . esc_html($business_name) . ' </h3>';
    echo '<h3>Campaign : ' . esc_html($_campaign) . ' </h3>';
    echo '<h3>Order Name : ' . esc_html($order_name) . ' </h3>';
    echo '<h3>PO Number : ' . esc_html($po_number) . ' </h3>';
    echo '<h3>Additional Reference : ' . esc_html($_additional_reference) . ' </h3>';
    echo '<h3>Client Reference : ' . esc_html($_client_reference) . ' </h3>';

    if ($gift_card_number) {
        // Get gift card post by gift card number
        $gift_card_post = get_posts([
            'post_type' => 'gift_card',
            'numberposts' => 1,
            'meta_key' => '_gift_card_number_enc',
            'meta_value' => $gift_card_number,
        ]);

        if ($gift_card_post) {
            $gift_card_id = $gift_card_post[0]->ID;

            $activation_type = get_field('_activation_expiry_type', $gift_card_id);
            $activation_date = get_field('_activation_expiry_date', $gift_card_id);
            $activation_duration = get_field('_activation_expiry_duration', $gift_card_id);
            $activation_unit = get_field('_activation_expiry_unit', $gift_card_id);

            //echo '<h4>Gift Card #: ' . esc_html($gift_card_number) . '</h4>';
            echo '<p><strong>Activation Expiry Type:</strong> ' . esc_html($activation_type) . '</p>';

            if ($activation_type === 'Activated by a Set Date' && !empty($activation_date)) {
                echo '<p><strong>Expiry Date:</strong> ' . esc_html(date_i18n('F j, Y g:i A', strtotime($activation_date))) . '</p>';
            } elseif ($activation_type === 'Activated within a Set Period') {
                if (!empty($activation_duration) && !empty($activation_unit)) {
                    echo '<p><strong>Activation Period:</strong> ' . esc_html($activation_duration . ' ' . $activation_unit) . '</p>';
                }
                if (!empty($activation_date)) {
                    echo '<p><strong>Calculated Expiry Date:</strong> ' . esc_html(date_i18n('F j, Y g:i A', strtotime($activation_date))) . '</p>';
                }
            }
        }

    }

    // Display invoice details if available
    $invoice_company = $order->get_meta('_invoice_company_name');
    if ($invoice_company) {
        echo '<h4>Invoice Details:</h4>';
        echo '<p><strong>Company:</strong> ' . esc_html($invoice_company) . '</p>';
        echo '<p><strong>ABN:</strong> ' . esc_html($order->get_meta('_invoice_abn')) . '</p>';
        echo '<p><strong>Billing Address:</strong> ' . nl2br(esc_html($order->get_meta('_invoice_billing_address'))) . '</p>';
        echo '<p><strong>Notes:</strong> ' . nl2br(esc_html($order->get_meta('_invoice_notes'))) . '</p>';
    }
}
// Register AJAX action for logged-in users
add_action('wp_ajax_export_products_csv', 'export_products_csv');


function export_products_csv()
{
    gcp_require_admin_ajax();
    check_ajax_referer( 'export_products_nonce', 'security' );

    // Sanitize POST values
    $p_name = isset($_POST['p_name']) ? sanitize_text_field($_POST['p_name']) : '';
    $p_denomination_type = isset($_POST['p_denomination_type']) ? sanitize_text_field($_POST['p_denomination_type']) : '';
    $p_denomination = isset($_POST['p_denomination']) ? (int) $_POST['p_denomination'] : 0;
    $p_status = isset($_POST['p_status']) ? sanitize_text_field($_POST['p_status']) : '';

    $pro_denomination_type = strtolower($p_denomination_type);
  
    // Convert comma-separated statuses to array
    $p_status_array = !empty($p_status) ? explode(',', strtolower($p_status)) : [];
   
    // Map front-end friendly statuses to real post statuses
    $status_map = array(
        'publish' => 'publish',
        'draft' => 'draft',
        'pending' => 'pending',
        'private' => 'private',
        'deactivated' => 'wc-deactivated',
    );

    // Ensure wc-deactivated is recognized globally
    add_filter('get_post_stati', function ($post_statuses) {
        $post_statuses['wc-deactivated'] = 'wc-deactivated';
        return $post_statuses;
    });

    // Inside your export function
    if (!empty($p_status_array)) {
        $p_status_array = array_map(function ($s) use ($status_map) {
            return $status_map[$s] ?? $s;
        }, $p_status_array);
    } else {
        // Fetch all statuses including custom
        $all_statuses = array_keys(get_post_stati());
        $p_status_array = array_diff($all_statuses, array('trash'));
    }


    // Security check
    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'create_giftcard_products_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Chunking
    $chunk_size = isset($_POST['chunk_size']) ? absint($_POST['chunk_size']) : 100;
    $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

    // Optional: export only selected product IDs (when user checks rows and clicks Export)
    $selected_ids = array();
    if (!empty($_POST['product_ids'])) {
        $selected_ids = array_map('absint', array_filter(array_map('trim', explode(',', sanitize_text_field($_POST['product_ids'])))));
    }

    if (!empty($selected_ids)) {
        // Export only selected products: take this chunk of IDs
        $chunk_ids = array_slice($selected_ids, $offset, $chunk_size);
        $total_products = count($selected_ids);
        $args = array(
            'post_type'      => 'product',
            'post__in'       => $chunk_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count($chunk_ids),
            'fields'         => 'ids',
            'post_status'    => 'any',
        );
        $products_query = new WP_Query($args);
    } else {
        // Export all (with filters): existing behavior
        // Build meta query
        $meta_query = array('relation' => 'AND');
        if (!empty($pro_denomination_type)) {
            $meta_query[] = array(
                'key' => 'denomination_type',
                'value' => $pro_denomination_type,
                'compare' => 'LIKE',
                'type' => 'CHAR',
            );
        }

        if (!empty($p_denomination)) {
            $meta_query[] = array(
                'key' => '_regular_price',
                'value' => (int) $p_denomination,
                'compare' => '=',
                'type' => 'NUMERIC',
            );
        }

        // Add wc-deactivated status temporarily if not already included
        add_filter('get_post_stati', function ($post_statuses) {
            $post_statuses['wc-deactivated'] = 'wc-deactivated';
            return $post_statuses;
        });

        // Prepare WP_Query args
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $chunk_size,
            'offset' => $offset,
            'fields' => 'ids',
            'post_status' => $p_status_array,
            'meta_query' => !empty($meta_query) ? $meta_query : '',
        );

        if (!empty($p_name)) {
            $args['s'] = $p_name;
        }

        $products_query = new WP_Query($args);
        $total_products = $products_query->found_posts;
    }
    if (empty($total_products)) {
        wp_send_json_error([
            'message' => 'No Products found to export.',
        ]);
        wp_die();
    }
    // CSV setup
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/products_export.csv';
    $output = fopen($file_path, $offset === 0 ? 'w' : 'a');

    if ($offset === 0) {
        fputcsv($output, array('Product ID', 'Product Name', 'Price', 'Status'));
    }

    // Loop through products and write to CSV
    foreach ($products_query->posts as $product_id) {
        $product = wc_get_product($product_id);
        $raw_status = $product->get_status();

        // Friendly status labels
        $status_label = match ($raw_status) {
            'publish' => 'Active',
            'draft' => 'Awaiting Approval',
            'wc-deactivated' => 'Deactivated',
            default => ucfirst($raw_status),
        };

        $data = array(
            $product_id,
            $product->get_name(),
            $product->get_price(),
            $status_label,
        );

        fputcsv($output, $data);
    }

    fclose($output);

    wp_send_json_success(array(
        'offset' => $offset + $chunk_size,
        'total_products' => $total_products,
        'file_url' => $upload_dir['url'] . '/products_export.csv',
    ));
}



add_action('wp_ajax_export_categories_csv', 'export_categories_csv');

function export_categories_csv()
{
    gcp_require_admin_ajax();
    check_ajax_referer('bulk_create_category_nonce', '_ajax_nonce');

    // Collect filters
    $cat_id = isset($_POST['cat_id']) ? sanitize_text_field($_POST['cat_id']) : '';
    $cat_name = isset($_POST['cat_name']) ? sanitize_text_field($_POST['cat_name']) : '';
    $cat_assigned = isset($_POST['cat_assigned']) ? sanitize_text_field($_POST['cat_assigned']) : '';
    $cat_description = isset($_POST['cat_description']) ? sanitize_text_field($_POST['cat_description']) : '';
    $cat_priority = isset($_POST['cat_priority']) ? sanitize_text_field($_POST['cat_priority']) : '';
    $cat_status = isset($_POST['cat_status']) ? sanitize_text_field($_POST['cat_status']) : '';
    $cat_statuses = array_filter(array_map('trim', explode(',', strtolower($cat_status))));

    // Fetch all categories
    $args = [
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ];
    $categories = get_terms($args);

    // ============ APPLY FILTERS ============
    if ($cat_id !== '') {
        $cat_id_str = (string) $cat_id;
        $categories = array_filter(
            $categories,
            fn($cat) =>
            strpos((string) $cat->term_id, $cat_id_str) !== false
        );
    }

    if ($cat_name !== '') {
        $search_name = strtolower($cat_name);
        $categories = array_filter(
            $categories,
            fn($cat) =>
            strpos(strtolower($cat->name), $search_name) !== false
        );
    }

    if ($cat_description !== '') {
        $search_desc = strtolower($cat_description);
        $categories = array_filter(
            $categories,
            fn($cat) =>
            strpos(strtolower($cat->description), $search_desc) !== false
        );
    }

    if ($cat_priority !== '') {
        $search_priority = (string) $cat_priority;
        $categories = array_filter($categories, function ($cat) use ($search_priority) {
            $term_priority = get_term_meta($cat->term_id, 'priority', true);
            return $term_priority !== '' && strpos((string) $term_priority, $search_priority) !== false;
        });
    }

    if (!empty($cat_statuses)) {
        $categories = array_filter($categories, function ($cat) use ($cat_statuses) {
            $term_status = strtolower((string) get_term_meta($cat->term_id, 'category_status', true));
            return in_array($term_status, $cat_statuses, true);
        });
    }

    if ($cat_assigned !== '') {
        $search_assigned = intval($cat_assigned);
        $categories = array_filter($categories, function ($cat) use ($search_assigned) {
            $product_query = new WP_Query([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => 'any',
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $cat->term_id,
                    ],
                ],
            ]);
            $assigned_count = $product_query->found_posts;
            return $search_assigned === 0 ? $assigned_count === 0 : $assigned_count === $search_assigned;
        });
    }

    // ✅ If no data to export
    if (empty($categories)) {
        wp_send_json_success([
            'empty_cat' => true,
            'message' => 'No data to export.'
        ]);
    }

    // ============ PREPARE EXPORT ============
    $upload_dir = wp_upload_dir();
    $export_dir = trailingslashit($upload_dir['basedir']) . 'category_exports/';
    $export_url = trailingslashit($upload_dir['baseurl']) . 'category_exports/';
    $filename = 'category_export.csv';
    $filepath = $export_dir . $filename;

    if (!file_exists($export_dir)) {
        wp_mkdir_p($export_dir);
    }

    // Create fresh file
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    $file = fopen($filepath, 'w');

    // Header row
    fputcsv($file, [
        'Category ID',
        'Category Name',
        'Slug',
        'Description',
        'Assigned Products Count',
        'Category Priority',
        'Status'
    ]);

    // Write category data
    foreach ($categories as $category) {
        $term_priority = get_term_meta($category->term_id, 'priority', true);
        $term_status = get_term_meta($category->term_id, 'category_status', true);

        $product_query = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'wc-deactivated'],
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category->term_id,
                ],
            ],
        ]);

        $assigned_count = $product_query->found_posts;

        fputcsv($file, [
            $category->term_id,
            $category->name,
            $category->slug,
            $category->description,
            $assigned_count,
            $term_priority,
            $term_status,
        ]);
    }

    fclose($file);

    wp_send_json_success([
        'message' => 'Export completed successfully.',
        'file_url' => $export_url . $filename,
        'empty_cat' => false
    ]);
}




function custom_handle_csv_upload()
{
    gcp_require_admin_ajax();
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $uploaded_file = $_FILES['file'];
        $file_tmp_name = $uploaded_file['tmp_name'];

        $timestamp = date('Y-m-d_H-i-s');
        $new_file_name = 'csv_' . $timestamp . '.csv';

        $upload_dir = WP_CONTENT_DIR . '/assets/csv/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $csv_data = custom_parse_csv_file($destination);

            $template_headers = [
                'NO',
                'Gift Card Title',
                'Parent/Child SKU',
                'Linked to Parent',
                'Parent SKU',
                'SKU',
                'Supplier SKU',
                'Brand',
                'Supplier',
                'Short Description',
                'Long Description',
                'Terms & Conditions',
                'How to Use',
                'Expiry Date/Time',
                'Gift Card Expiry Type',
                'Gift Card Expiry Date',
                'Gift Card Expiry Period',
                'Gift Card Activation Type',
                'Gift Card Activation Date',
                'Gift Card Activation Period',
                'Period Type',
                'Brand Image',
                'Card Image 1',
                'Card Image 2',
                'Card Image 3',
                'Card Image 4',
                'Card Image 5',
                'Denomination Type',
                'Cost Price',
                'Supplier Fullfillment Price',
                'GST',
                'GC + Fullfillment',
                'Preset Delivery Class',
                'Delivery Cost',
                'Discounted',
                'Discounted Price',
                'Discounted Valid From',
                'Discounted Valid To',
                'Icons',
                'Tags',
                'Categories',
                'Feature Placement',
                'Extra Header',
                'Add Stock Levels',
                'Stock Levels',
                'Transaction Limit',
                'QTY per Transaction',
                'Total Value',
                'Available for all users',
                'Always On',
                'Onsite From',
                'Onsite To'
            ];

            $header_mapping = custom_compare_headers($template_headers, $csv_data['headers']);
            $all_matched = !in_array('', $header_mapping);

            wp_send_json_success([
                'message' => 'File uploaded and parsed successfully!',
                'csv_data' => $csv_data,
                'template_headers' => $template_headers,
                'header_mapping' => $header_mapping,
                'all_matched' => $all_matched
            ]);
        } else {
            wp_send_json_error(['message' => 'Error while uploading the file.']);
        }
    } else {
        wp_send_json_error(['message' => 'No file uploaded or there was an error.']);
    }
}

function custom_parse_csv_file($file_path)
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

function custom_compare_headers($template_headers, $csv_headers)
{
    $mapping = [];
    $used_csv_headers = [];

    foreach ($template_headers as $template_header) {
        $found_match = '';

        foreach ($csv_headers as $index => $csv_header) {
            if (strcasecmp(trim($template_header), trim($csv_header)) === 0 && !in_array($index, $used_csv_headers)) {
                $found_match = $csv_header;
                $used_csv_headers[] = $index; // Mark this header as used
                break;
            }
        }

        $mapping[$template_header] = $found_match;
    }

    // 🔹 Add any extra CSV headers that are not in the template headers
    // foreach ($csv_headers as $csv_header) {
    //     if (!in_array($csv_header, $mapping)) {
    //         $mapping[$csv_header] = $csv_header;
    //     }
    // }

    return $mapping;
}

add_action('wp_ajax_custom_upload_csv_file', 'custom_handle_csv_upload');


// add_action('wp_ajax_custom_validate_product_details_bulk', 'custom_validate_product_details_bulk_callback');
// add_action('wp_ajax_nopriv_custom_validate_product_details_bulk', 'custom_validate_product_details_bulk_callback');

// function custom_validate_product_details_bulk_callback() {
//     global $wpdb;

//     // Get CSV product data from the AJAX request
//     $product_data = isset($_POST['product_data']) ? $_POST['product_data'] : [];

//     // Initialize response array
//     $invalid_products = [];

//     foreach ($product_data as $index => $product) {
//         $sku = sanitize_text_field($product['sku']);
//         $gift_card_name = sanitize_text_field($product['gift_card_name']);
//         $gift_card_value = floatval($product['gift_card_value']);

//         // Query WooCommerce for the product by SKU
//         $product_id = wc_get_product_id_by_sku($sku);
//         if (!$product_id) {
//             $invalid_products[] = ['rowIndex' => $index, 'error' => 'Invalid SKU'];
//             continue;
//         }

//         // Get product details
//         $wc_product = wc_get_product($product_id);
//         if (!$wc_product) {
//             $invalid_products[] = ['rowIndex' => $index, 'error' => 'Product not found'];
//             continue;
//         }

//         $expected_name = $wc_product->get_name();
//         $expected_price = floatval($wc_product->get_price());

//         // Validate product name
//         if ($gift_card_name !== $expected_name) {
//             $invalid_products[] = ['rowIndex' => $index, 'colIndex' => 'gift_card_name', 'error' => 'Incorrect Product Name'];
//         }

//         // Validate gift card value
//         if ($gift_card_value !== $expected_price) {
//             $invalid_products[] = ['rowIndex' => $index, 'colIndex' => 'gift_card_value', 'error' => 'Incorrect Gift Card Value'];
//         }
//     }

//     wp_send_json_success(['errors' => $invalid_products]);
// }
// add_action('wp_ajax_custom_get_recipient_details_bulk', 'custom_get_recipient_details_bulk_callback');
// add_action('wp_ajax_nopriv_custom_get_recipient_details_bulk', 'custom_get_recipient_details_bulk_callback');

// function custom_get_recipient_details_bulk_callback() {
//     global $wpdb;
//     $recipient_ids = isset($_POST['recipient_ids']) ? array_map('intval', $_POST['recipient_ids']) : [];
//     $recipient_details_map = [];

//     foreach ($recipient_ids as $recipient_id) {
//         $user = get_userdata($recipient_id);
//         if (!$user) {
//             continue; // Skip invalid user IDs
//         }

//         // Check if the user has the 'recipients' role
//         if (!in_array('recipients', $user->roles)) {
//             continue; // Skip users without the recipient role
//         }

//         // Fetch recipient details
//         $recipient_details_map[$recipient_id] = [
//             'first_name' => $user->first_name,
//             'email' => $user->user_email,
//             'phone' => get_user_meta($recipient_id, 'billing_phone', true),
//             'assigned_business_user' => get_user_meta($recipient_id, 'assigned_business_user', true)
//         ];
//     }

//     wp_send_json_success(['data' => $recipient_details_map]);
// }
add_action('wp_ajax_custom_process_bulk_order_data', 'custom_process_bulk_order_data');

function custom_process_bulk_order_data()
{
    gcp_require_admin_ajax();
    // Verify nonce for security
    if (!check_ajax_referer('bulk_order_nonce', 'security', false)) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
    }

    // Get the CSV data from the AJAX request
    $csv_data = json_decode(wp_unslash($_POST['csv_data']), true);

    if (empty($csv_data) || !isset($csv_data['headers']) || !isset($csv_data['data'])) {
        wp_send_json_error(['message' => 'Invalid CSV data.']);
    }

    $processed_data = [];

    // Process each row of the CSV data
    foreach ($csv_data['data'] as $row) {
        $first_name = $row[array_search('Recipient First Name', $csv_data['headers'])] ?? '';
        $surname = $row[array_search('Recipient Surname', $csv_data['headers'])] ?? '';
        $email = $row[array_search('Recipient Email Address', $csv_data['headers'])] ?? '';
        $sku = $row[array_search('Product Code', $csv_data['headers'])] ?? '';
        $price = $row[array_search('Gift Card Value', $csv_data['headers'])] ?? '';
        $message = $row[array_search('Message', $csv_data['headers'])] ?? '';
        $delivery_method = $row[array_search('Delivery Method', $csv_data['headers'])] ?? 'Email';


        // Fetch the product image based on SKU (replace this with your logic)
        $image_src = get_product_image_by_sku($sku);

        if ($image_src) {
            $processed_data[] = [
                'first_name' => $first_name,
                'surname' => $surname,
                'email' => $email,
                'sku' => $sku,
                'price' => $price,
                'image' => $image_src,
                'message' => $message,
                'delivery_method' => $delivery_method
            ];
        }
    }

    if (empty($processed_data)) {
        wp_send_json_error(['message' => 'No valid data found.']);
    }

    wp_send_json_success($processed_data);
}



function enqueue_user_listing_scripts()
{
    if (is_page_template('users.php') || is_page_template('reset-password-template.php')) {
        wp_enqueue_script('user-listing-js', get_template_directory_uri() . '/assets/js/user-page.js', ['jquery'], time(), true);

        // Localize ajax URL for use in JS
        wp_localize_script('user-listing-js', 'userListingData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'siteUrl' => home_url('/'),
            'nonce'    => wp_create_nonce('user_admin_nonce'),
            'createEventNonce' => wp_create_nonce('create_event_from_contact_nonce'),
            'deleteEventNonce' => wp_create_nonce('delete_event_from_contact_nonce'),
        ]);

        wp_localize_script('user-listing-js', 'userData', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);


        wp_enqueue_style('user-page-css', get_template_directory_uri() . '/assets/css/users-page.css', array(), time());

        // Dedicated stylesheet for the admin Contact List & Events reminders
        // section (adapted from my-reminders.css, scoped to .admin-reminders-wrapper).
        if (is_page_template('users.php')) {
            wp_enqueue_style('admin-reminder-css', get_template_directory_uri() . '/assets/css/admin-reminder.css', array(), time());
        }
    }
}
add_action('wp_enqueue_scripts', 'enqueue_user_listing_scripts');




function custom_user_extra_fields($user)
{
    $states = ['select', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT', 'Other'];
    ?>

    <h3> User page Information</h3>
    <table class="form-table">

        <!-- Nickname / Team Name -->
        <tr>
            <th><label for="nickname_team">Nickname / Team Name</label></th>
            <td>
                <input type="text" name="nickname_team" id="nickname_team"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'nickname_team', true)); ?>" class="regular-text" />
                <p class="description">Enter the user's nickname or team name</p>
            </td>
        </tr>

        <!-- Date of Birth (input type="date" requires Y-m-d) -->
        <?php
        $dob = get_user_meta($user->ID, 'dob', true);
        $dob_display = '';
        if (!empty($dob)) {
            // Already stored as Y-m-d (e.g. from registration datepicker)
            if (preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $dob)) {
                $dob_display = $dob;
            } else {
                // Stored as d/m/Y – convert to Y-m-d for the date input
                $dob_obj = date_create_from_format('d/m/Y', $dob);
                if ($dob_obj) {
                    $dob_display = $dob_obj->format('Y-m-d');
                } else {
                    $dob_display = $dob;
                }
            }
        }
        ?>
        <tr>
            <th><label for="dob">Date of Birth</label></th>
            <td>
                <input type="date" name="dob" id="dob" value="<?php echo esc_attr($dob_display); ?>" class="regular-text" />
                <p class="description">Format: YYYY-MM-DD (e.g. 2000-01-15)</p>
            </td>
        </tr>

        <!-- State -->
        <!-- <tr>
            <th><label for="state">State</label></th>
            <td>
                <select name="state" id="state">
                    <option value="">Select State</option>
                    <?php
                    // $states = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT', 'Other'];
                    // $selected = get_user_meta($user->ID, 'state', true);
                    // foreach ($states as $state) {
                    //     echo '<option value="' . esc_attr($state) . '" ' . selected($selected, $state, false) . '>' . esc_html($state) . '</option>';
                    // }
                    ?>
                </select>
            </td>
        </tr> -->


        <!-- Join Date -->
        <tr>
            <th><label for="join_date">Join Date</label></th>
            <td>
                <input type="date" name="join_date" id="join_date"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'join_date', true)); ?>" class="regular-text" />
                <p class="description">Format: dd/mm/yyyy</p>
            </td>
        </tr>

        <!-- Work Anniversary -->
        <tr>
            <th><label for="work_anniversary">Work Anniversary</label></th>
            <td>
                <input type="date" name="work_anniversary" id="work_anniversary"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'work_anniversary', true)); ?>"
                    class="regular-text" />
                <p class="description">Format: dd/mm/yyyy</p>
            </td>
        </tr>

        <!-- Mobile -->
        <tr>
            <th><label for="mobile">Mobile</label></th>
            <td>
                <input type="tel" name="mobile" id="mobile"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'mobile', true)); ?>" class="regular-text"
                    pattern="^(\+61|0)[0-9]{9}$" placeholder="e.g. 0412345678 or +61412345678" />
                <p class="description">Must be a valid Australian mobile number</p>
            </td>
        </tr>
    </table>

    <?php
}
add_action('show_user_profile', 'custom_user_extra_fields');
add_action('edit_user_profile', 'custom_user_extra_fields');
add_action('user_new_form', 'custom_user_extra_fields'); // Add New User

function save_custom_user_extra_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id))
        return;
    update_user_meta($user_id, 'nickname_team', isset($_POST['nickname_team']) ? sanitize_text_field($_POST['nickname_team']) : '');
    // Only update dob when present in POST (avoids overwriting with empty on user_register from registration flow)
    if (isset($_POST['dob'])) {
        $dob_raw = sanitize_text_field($_POST['dob']);
        $dob_stored = normalize_dob_to_ymd($dob_raw);
        if ($dob_stored !== false) {
            update_user_meta($user_id, 'dob', $dob_stored);
        } else {
            update_user_meta($user_id, 'dob', $dob_raw);
        }
    }
    if (isset($_POST['join_date'])) {
        update_user_meta($user_id, 'join_date', sanitize_text_field($_POST['join_date']));
    }
    if (isset($_POST['work_anniversary'])) {
        update_user_meta($user_id, 'work_anniversary', sanitize_text_field($_POST['work_anniversary']));
    }
    update_user_meta($user_id, 'mobile', isset($_POST['mobile']) ? sanitize_text_field($_POST['mobile']) : '');


    // Save state only if a real state is selected
    if (!empty($_POST['state']) && $_POST['state'] !== '') {
        update_user_meta($user_id, 'state', sanitize_text_field($_POST['state']));
    } else {
        delete_user_meta($user_id, 'state'); // remove old value if any
    }
}

add_action('personal_options_update', 'save_custom_user_extra_fields');
add_action('edit_user_profile_update', 'save_custom_user_extra_fields');
add_action('user_register', 'save_custom_user_extra_fields'); // New user save



function add_business_fields_to_user_profile($user)
{
    ?>
    <h3>Business Information</h3>
    <table class="form-table">
        <!-- Business Name -->
        <tr>
            <th><label for="business_name">Business Name</label></th>
            <td><input type="text" name="business_name" id="business_name" class="regular-text"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'business_name', true)); ?>" /></td>
        </tr>

        <!-- Float Balance -->
        <tr>
            <th><label for="float_balance">Float Balance</label></th>
            <td><input type="number" name="float_balance" id="float_balance" class="regular-text"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'float_balance', true)); ?>" required /></td>
        </tr>

        <tr>
            <th><label for="prepaid_credit_balance">Prepaid Credit</label></th>
            <td><input type="number" name="prepaid_credit_balance" id="prepaid_credit_balance" class="regular-text"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'prepaid_credit_balance', true)); ?>" required />
            </td>
        </tr>

        <!-- Prepaid Limit -->
        <tr>
            <th><label for="prepaid_limit">Prepaid Limit</label></th>
            <td><input type="number" name="prepaid_limit" id="prepaid_limit" class="regular-text"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'prepaid_limit', true)); ?>" required /></td>
        </tr>

        <!-- Prepaid Limit -->
        <tr>
            <th><label for="float_notification">Float Top up Notification</label></th>
            <td><input type="number" name="float_notification" id="float_notification" class="regular-text"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'float_notification', true)); ?>" required /></td>
        </tr>

        <!-- Business Website -->
        <tr>
            <th><label for="business_website">Business Website</label></th>
            <td><input type="url" name="business_website" id="business_website" class="regular-text"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'business_website', true)); ?>" /></td>
        </tr>

        <!-- Business ID -->
        <tr>
            <th><label for="business_id">Business ID</label></th>
            <td><input type="text" name="business_id" id="business_id" class="regular-text" required
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'business_id', true)); ?>" /></td>
        </tr>
        <tr>
            <th><label for="billing_details">Billing Details</label></th>
            <td><input type="text" name="billing_details" id="billing_details" class="regular-text" required
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_details', true)); ?>" /></td>
        </tr>

        <tr>
            <th><label for="billing_details_2">Billing Details 2</label></th>
            <td><input type="text" name="billing_details_2" id="billing_details_2" class="regular-text" required
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_details_2', true)); ?>" /></td>
        </tr>

        <!-- Approved for client billing -->
        <tr>
            <th><label for="approved_billing">Approved for Client Billing</label></th>
            <td><input type="checkbox" name="approved_billing" id="approved_billing" <?php checked(get_user_meta($user->ID, 'approved_billing', true), 'yes'); ?> value="yes" /></td>
        </tr>

        <!-- Business Float ID -->
        <tr>
            <th><label for="business_float_id">Business Float ID</label></th>
            <td><input type="text" name="business_float_id" id="business_float_id" class="regular-text"
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'business_float_id', true)); ?>" /></td>
        </tr>

        <!-- Business ABN -->
        <tr>
            <th><label for="business_abn">Business ABN</label></th>
            <td><input type="text" name="business_abn" id="business_abn" class="regular-text" required
                    value="<?php echo esc_attr(get_user_meta($user->ID, 'business_abn', true)); ?>" /></td>
        </tr>

        <!-- Business Currency -->
        <tr>
            <th><label for="business_currency">Business Currency</label></th>
            <td>
                <select name="business_currency" id="business_currency">
                    <?php
                    $current_currency = get_user_meta($user->ID, 'business_currency', true);
                    $currencies = ['AUD' => 'AUD', 'EUR' => 'EUR'];
                    foreach ($currencies as $value => $label) {
                        echo '<option value="' . $value . '" ' . selected($current_currency, $value, false) . '>' . $label . '</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>

        <!-- Address Fields -->
        <?php
        $address_fields = [
            'address_line1' => 'Address Line 1',
            'address_line2' => 'Address Line 2',
            'suburb' => 'Suburb',
            'state' => 'State',
            'country' => 'Country',
            'postcode' => 'Post Code',
        ];

        foreach ($address_fields as $meta_key => $label) {
            ?>
            <tr>
                <th><label for="<?php echo $meta_key; ?>"><?php echo $label; ?></label></th>
                <td><input type="text" name="<?php echo $meta_key; ?>" id="<?php echo $meta_key; ?>" class="regular-text"
                        required value="<?php echo esc_attr(get_user_meta($user->ID, $meta_key, true)); ?>" /></td>
            </tr>
            <?php
        }
        ?>
    </table>
    <?php
}
add_action('show_user_profile', 'add_business_fields_to_user_profile');
add_action('edit_user_profile', 'add_business_fields_to_user_profile');
add_action('user_new_form', 'add_business_fields_to_user_profile'); // Add New User


function save_business_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id))
        return;

    $user = get_userdata($user_id);

    $fields = [
        'business_name',
        'float_balance',
        'prepaid_credit_balance',
        'prepaid_limit',
        'float_notification',
        'business_website',
        'business_id',
        'billing_details',
        'billing_details_2',
        'approved_billing',
        'business_float_id',
        'business_abn',
        'address_line1',
        'address_line2',
        'business_currency',
        'suburb',
        'state',
        'country',
        'postcode'
    ];

    foreach ($fields as $field) {
        $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';

        if ($field === 'approved_billing') {
            update_user_meta($user_id, $field, $value === 'yes' ? 'yes' : 'no');
        } elseif ($field === 'float_balance') {
            $old_balance = (float) get_user_meta($user_id, $field, true);
            $new_balance = floatval($value);

            $changed_amount = $new_balance - $old_balance;

            if ($changed_amount !== 0.0) {
                // log_float_transaction() adds $changed_amount to the current float_balance
                // and writes the result itself — don't also call update_user_meta() here.
                log_float_transaction(
                    $user_id,
                    $changed_amount,
                    $changed_amount >= 0 ? 'credited' : 'debited',
                    'Admin profile update'
                );
            }
        } else {
            update_user_meta($user_id, $field, $value);
        }
    }
}
add_action('personal_options_update', 'save_business_fields');
add_action('edit_user_profile_update', 'save_business_fields');
add_action('user_register', 'save_business_fields'); // New user save

function fetch_users()
{
    gcp_require_admin_ajax();
    global $wpdb;

    // Get DataTable parameters
    $limit = isset($_POST['length']) ? intval($_POST['length']) : 5;
    $offset = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $searchQuery = isset($_POST['searchQuery']) ? sanitize_text_field($_POST['searchQuery']) : '';
    $userRole = isset($_POST['userRole']) ? sanitize_text_field($_POST['userRole']) : '';

    // Handle ordering
    $columns = ['user_id', 'first_name', 'last_name', 'email', 'role', 'business_name', 'details'];
    $orderby = 'ID';
    $order = 'ASC';
    $meta_key = '';

    $orderbyMap = [
        'user_id' => ['orderby' => 'ID'],
        'first_name' => ['orderby' => 'meta_value', 'meta_key' => 'first_name'],
        'last_name' => ['orderby' => 'meta_value', 'meta_key' => 'last_name'],
        'email' => ['orderby' => 'user_email'],
        'role' => ['orderby' => 'meta_value', 'meta_key' => 'wp_capabilities'],
        'business_name' => ['orderby' => 'meta_value', 'meta_key' => 'business_name']
    ];

    if (isset($_POST['order'][0])) {
        $orderColumnIndex = intval($_POST['order'][0]['column']);
        $orderDir = sanitize_text_field($_POST['order'][0]['dir']);
        $colKey = $columns[$orderColumnIndex] ?? '';

        if (isset($orderbyMap[$colKey])) {
            $orderby = $orderbyMap[$colKey]['orderby'];
            if (!empty($orderbyMap[$colKey]['meta_key'])) {
                $meta_key = $orderbyMap[$colKey]['meta_key'];
            }
        }
        $order = in_array(strtoupper($orderDir), ['ASC', 'DESC']) ? strtoupper($orderDir) : 'ASC';
    }

    // Prepare WP_User_Query args
    $args = array(
        'number' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
        'meta_query' => [],
    );

    if (!empty($meta_key)) {
        $args['meta_key'] = $meta_key;
    }

    // Search logic
    if (is_numeric($searchQuery)) {
        $args['include'] = array(intval($searchQuery));
    } elseif (!empty($searchQuery)) {
        $args['search'] = '*' . esc_attr($searchQuery) . '*';
        $args['search_columns'] = array('user_login', 'user_nicename', 'user_email');
    }

    // Filter by role
    if (!empty($userRole)) {
        $args['role'] = $userRole;
    }

    $user_query = new WP_User_Query($args);
    $users = $user_query->get_results();
    $total_users = $user_query->get_total();

    // Get role names
    $roles = wp_roles()->roles;
    $admin_user_id = get_current_user_id();
    $data = array();
    foreach ($users as $user) {
        $user_meta = get_user_meta($user->ID);
        $business_user_id = $user->ID;
        $business_name = ($user_meta['business_name'][0]) ? $user_meta['business_name'][0] : 'N/A';

        $role_slug = !empty($user->roles) ? $user->roles[0] : '';
        $role_display_name = '';
        if ($role_slug === 'administrator') {
            //$role_display_name = 'J&C Super admin';
            $role_display_name = 'GCP Super admin';
        } elseif (isset($roles[$role_slug])) {
            $role_display_name = $roles[$role_slug]['name'];
        } else {
            $role_display_name = ucfirst($role_slug);
        }

        if ($role_slug !== 'business_user') {
            $business_user_id = get_user_meta($user->ID, 'assigned_business_user', false);
            if ($business_user_id || (int) $business_user_id > 0) {
                $business_name = (get_user_meta($user->ID, 'business_name', true)) ? get_user_meta($user->ID, 'business_name', true) : 'N/A';
            } else {
                //$business_user_id = $user->ID;
            }
        }

        $data[] = array(
            "user_id" => $user->ID,
            "first_name" => isset($user_meta['first_name'][0]) ? $user_meta['first_name'][0] : '-',
            "last_name" => isset($user_meta['last_name'][0]) ? $user_meta['last_name'][0] : '-',
            "email" => $user->user_email,
            "role" => esc_html($role_display_name),
            "business_name" => $business_name,
            "business_user_id" => $business_user_id,
            "admin_user_id" => $admin_user_id,
            "details" => '<a href="' . get_edit_user_link($user->ID) . '" target="_blank">View</a>',
        );
    }

    echo json_encode(array(
        "draw" => intval($_POST['draw']),
        "recordsTotal" => $total_users,
        "recordsFiltered" => $total_users,
        "data" => $data,
    ));
    wp_die();
}
add_action('wp_ajax_fetch_users', 'fetch_users');



function export_users()
{
    gcp_require_admin_ajax();
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : [];
    $include_pii = isset($_POST['include_pii']) && $_POST['include_pii'] == 1;

    if (empty($user_ids)) {
        wp_die('No users selected');
    }

    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=exported_users.csv");
    header("Pragma: no-cache");
    header("Expires: 0");

    $output = fopen("php://output", "w");

    // Write headers
    if ($include_pii) {
        fputcsv($output, ['User ID', 'First Name', 'Last Name', 'Email', 'User Type', 'Business']);
    } else {
        fputcsv($output, ['User ID', 'User Type', 'Business']);
    }

    // Get all roles for easy lookup
    $roles = wp_roles()->roles;

    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        if (!$user)
            continue;

        $meta = get_user_meta($user_id);

        // Corrected Business Name check
        $business_name = (isset($meta['business_name'][0]) && !empty(trim($meta['business_name'][0]))) ? $meta['business_name'][0] : 'N/A';

        // Get role display name
        $role_slug = !empty($user->roles) ? $user->roles[0] : '';
        $role_display_name = '';
        if ($role_slug === 'administrator') {
            // $role_display_name = 'J&C Super admin';
            $role_display_name = 'GCP Super admin';
        } elseif (isset($roles[$role_slug])) {
            $role_display_name = $roles[$role_slug]['name'];
        } else {
            $role_display_name = ucfirst($role_slug); // fallback if role missing
        }

        // Export data with display name
        if ($include_pii) {
            $first_name = (isset($meta['first_name'][0]) && !empty(trim($meta['first_name'][0]))) ? $meta['first_name'][0] : '-';
            $last_name = (isset($meta['last_name'][0]) && !empty(trim($meta['last_name'][0]))) ? $meta['last_name'][0] : '-';
            fputcsv($output, [$user_id, $first_name, $last_name, $user->user_email, $role_display_name, $business_name]);
        } else {
            fputcsv($output, [$user_id, $role_display_name, $business_name]);
        }
    }

    fclose($output);
    exit;
}


add_action('wp_ajax_export_users', 'export_users');

function fetch_all_filtered_users()
{
    gcp_require_admin_ajax();
    global $wpdb;

    $searchQuery = isset($_POST['searchQuery']) ? sanitize_text_field($_POST['searchQuery']) : '';
    $userRole = isset($_POST['userRole']) ? sanitize_text_field($_POST['userRole']) : '';

    // Base query
    $query = "SELECT ID FROM {$wpdb->users} u 
              JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
              WHERE um.meta_key = 'wp_capabilities'";

    // Add user role filter if needed
    if (!empty($userRole)) {
        $query .= $wpdb->prepare( " AND um.meta_value LIKE %s", '%' . $wpdb->esc_like( $userRole ) . '%' );
    }

    // Add search query
    if (!empty($searchQuery)) {
        $like = '%' . $wpdb->esc_like( $searchQuery ) . '%';
        $query .= $wpdb->prepare( " AND (u.user_login LIKE %s OR u.user_email LIKE %s)", $like, $like );
    }

    // Execute the query
    $users = $wpdb->get_col($query);

    echo json_encode($users);
    wp_die();
}

add_action('wp_ajax_fetch_all_filtered_users', 'fetch_all_filtered_users');



add_action('wp_login', 'record_last_login', 10, 2);
function record_last_login($user_login, $user)
{
    update_user_meta($user->ID, 'last_login', current_time('Y-m-d'));
}

add_action('wp_ajax_get_user_profile_details', 'get_user_profile_details_callback');

function get_user_profile_details_callback()
{
    gcp_require_admin_ajax();
    $user_id = intval($_POST['user_id']);
    $user = get_user_by('ID', $user_id);


    if (!$user) {
        wp_send_json_error('User not found');
    }

    // Fetch role slug and readable role name
    $role_slug = $user->roles[0] ?? '';
    $editable_roles = get_editable_roles();
    $role_name = isset($editable_roles[$role_slug]['name']) ? $editable_roles[$role_slug]['name'] : ucfirst($role_slug);

    $user_meta = get_user_meta($user_id);

    $business_name = isset($user_meta['business_name'][0]) ? $user_meta['business_name'][0] : 'N/A';
 
    $float_balance = null;

    // $user_business = get_users([
    //     'meta_key'   => 'business_name',
    //     'meta_value' => $business_name,
    //     'number'     => 1,
    //     'fields'     => ['ID'],
    // ]);

    // $business_user_id = !empty($user_business) ? $user_business[0]->ID : 0;

    // $float_balance =  get_user_meta($business_user_id, 'float_balance', true);

    // $business_name = trim($business_name);
    $remaining_prepaid_limit = 0;

    if (!empty($business_name)) {
        $user_business = get_users([
            'number' => 1,
            'fields' => ['ID'],
            'role' => 'business_user',
            'meta_query' => [
                [
                    'key' => 'business_name',
                    'value' => $business_name,
                    'compare' => '='
                ]
            ]
        ]);

        $business_user_id = !empty($user_business) ? $user_business[0]->ID : 0;
        if ($business_user_id) {
            $float_balance = get_user_meta($business_user_id, 'float_balance', true);
            $prepaid_credit = get_user_meta($business_user_id, 'prepaid_credit_balance', true);

            // prepaid_limit is itself the live balance (Client Billing: "Balance = Prepaid
            // Limit"; Instant/Float: the per-transaction cap) — no monthly-usage adjustment.
            $remaining_prepaid_limit = (float) get_user_meta($business_user_id, 'prepaid_limit', true) ?: 0;
        }
    }
    

    $join_date = get_user_meta($user->ID, 'join_date', true);

    if (empty($join_date)) {
        $join_date = date('Y-m-d', strtotime($user->user_registered));
    }

    $user_data = [
        'user_id' => $user->ID,
        'business_user_id' => $business_user_id,
        'first_name' => get_user_meta($user->ID, 'first_name', true),
        'last_name' => get_user_meta($user->ID, 'last_name', true),
        'nickname_team' => get_user_meta($user->ID, 'nickname_team', true),
        'email' => $user->user_email,
        'mobile' => get_user_meta($user->ID, 'mobile', true),
        'dob' => get_user_meta($user->ID, 'dob', true),
        'state' => get_user_meta($user->ID, 'state', true),
        'join_date' => $join_date,
        'work_anniversary' => get_user_meta($user->ID, 'work_anniversary', true),
        'last_login' => get_user_meta($user->ID, 'last_login', true),
        'float_balance' => $float_balance,
        'prepaid_credit' => $remaining_prepaid_limit,
        'role' => $role_slug,
        'role_name' => $role_name,
        'business_name' => $business_name,
    ];

    wp_send_json($user_data);
}



add_action('wp_ajax_save_user_profile_details', 'save_user_profile_details');

function save_user_profile_details()
{
    gcp_require_admin_ajax();
    check_ajax_referer('user_admin_nonce', 'nonce');
    parse_str($_POST['data'], $form);
    $user_id = intval($form['user_id']);

    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    wp_update_user([
        'ID' => $user_id,
        'user_email' => sanitize_email($form['email']),
    ]);

    update_user_meta($user_id, 'first_name', sanitize_text_field($form['first_name']));
    update_user_meta($user_id, 'last_name', sanitize_text_field($form['last_name']));

    update_user_meta($user_id, 'nickname_team', sanitize_text_field($form['nickname_team']));
    update_user_meta($user_id, 'mobile', sanitize_text_field($form['mobile']));
    update_user_meta($user_id, 'dob', sanitize_text_field($form['dob']));
    update_user_meta($user_id, 'join_date', sanitize_text_field($form['join_date']));
    update_user_meta($user_id, 'work_anniversary', sanitize_text_field($form['work_anniversary']));

    if (!empty($form['state'])) {
        update_user_meta($user_id, 'state', sanitize_text_field($form['state']));
    } else {
        delete_user_meta($user_id, 'state');
    }

    wp_send_json_success(['message' => 'Profile updated successfully']);
}


add_action('wp_ajax_admin_reset_user_password', 'admin_reset_user_password_handler');

function admin_reset_user_password_handler()
{
    gcp_require_admin_ajax();
    check_ajax_referer('user_admin_nonce', 'nonce');
    if (!current_user_can('edit_users')) {
        wp_send_json_error('Permission denied.');
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';

    if (!$user_id || !$new_password) {
        wp_send_json_error('Missing user or password.');
    }

    wp_set_password($new_password, $user_id);

    // Optionally force logout by updating session tokens
    wp_destroy_current_session();
    wp_clear_auth_cookie();

    wp_send_json_success();
}


add_action('wp_ajax_send_user_password_reset_link', 'send_user_password_reset_link_handler');

function send_user_password_reset_link_handler()
{
    gcp_require_admin_ajax();
    if (!current_user_can('edit_users')) {
        wp_send_json_error('Permission denied.');
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $user = get_user_by('ID', $user_id);

    if (!$user) {
        wp_send_json_error('User not found.');
    }

    $reset_key = get_password_reset_key($user);
    if (is_wp_error($reset_key)) {
        wp_send_json_error('Could not generate reset key.');
    }

    $custom_reset_page_url = site_url("/reset-password");
    $reset_url = add_query_arg([
        'login' => rawurlencode($user->user_login),
        'key' => $reset_key
    ], $custom_reset_page_url);


    $subject = 'Password Reset Request';

    // HTML email message with a proper link
    $message = "
        <p>Hi {$user->display_name},</p>
        <p>A request was made to reset your password. Click the link below to set a new password:</p>
        <p><a href='{$reset_url}'>Set new Password</a></p>
        <p>If you did not request this, please ignore this email.</p>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    $mail_sent = wp_mail($user->user_email, $subject, $message, $headers);

    if (!$mail_sent) {
        wp_send_json_error('Failed to send email.');
    }

    wp_send_json_success();
}
add_action('wp_ajax_nopriv_process_custom_password_reset', 'process_custom_password_reset');
add_action('wp_ajax_process_custom_password_reset', 'process_custom_password_reset');

function process_custom_password_reset()
{
    $login = sanitize_user($_POST['login'] ?? '');
    $key = sanitize_text_field($_POST['key'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($login) || empty($key) || empty($new_password)) {
        wp_send_json_error('Missing fields.');
    }

    if ($new_password !== $confirm_password) {
        wp_send_json_error('Oops, passwords don’t match. Please try again.');
    }

    if (!validate_password_strength($new_password)) {
        wp_send_json_error('Password must be at least 12 characters and include an uppercase letter, lowercase letter, a number, and a special character.');
    }

    $user = check_password_reset_key($key, $login);

    if (is_wp_error($user)) {
        wp_send_json_error('Reset link expired or invalid.');
    }

    reset_password($user, $new_password);

    wp_send_json_success();
}

// Get Business Profile Details

add_action('wp_ajax_check_user_billing_approval', 'check_user_billing_approval_callback');
// PT-3.1: removed wp_ajax_nopriv hook — financial data must not be publicly accessible.
function check_user_billing_approval_callback()
{
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Authentication required', 403 );
    }

    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
    }

    // Only the account owner or an admin may query billing approval data.
    if ( (int) get_current_user_id() !== $user_id && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Access denied', 403 );
    }

    // Example: you may use your own logic for approval check
    $is_approved = get_user_meta($user_id, 'approved_billing', true);
    $prepaid_limit = (float) (get_user_meta($user_id, 'prepaid_limit', true) ?: 0);
    $float_balance = get_user_meta($user_id, 'float_balance', true);

    // Client Billing: prepaid_limit IS the live agreed credit balance — orders debit it
    // directly at placement, so it already reflects remaining balance with no further
    // adjustment needed ("Balance = Prepaid Limit" per spec).
    // Instant payment/Float: prepaid_limit is instead a per-transaction spend cap, unrelated
    // to a running monthly allowance, so "remaining" is simply the cap itself.
    $remaining_prepaid_limit = $prepaid_limit;

    wp_send_json_success([
        'is_approved' => $is_approved,
        'prepaid_limit' => $prepaid_limit ?: '0.00',
        'float_balance' => $float_balance,
        'remaining_prepaid_limit' => $remaining_prepaid_limit,
    ]);
}

add_action('wp_ajax_get_business_profile_details', 'get_business_profile_details_callback');
function get_business_profile_details_callback()
{
    gcp_require_admin_ajax();
    $user_id = intval($_POST['user_id']);
    $user = get_user_by('ID', $user_id);

    if (!$user) {
        wp_send_json_error('User not found');
    }

    $is_admin = current_user_can('manage_options'); // Check if current user is admin
    $is_business_user = current_user_can('business_user'); // Business user check

    // Add the business user to the list explicitly
    // if ($is_business_user && $viewed_user) {
    //     $display_roles = array_map(function ($role) use ($role_display_names) {
    //         return isset($role_display_names[$role]) ? $role_display_names[$role] : ucfirst(str_replace('_', ' ', $role));
    //     }, $viewed_user->roles);

    //     $recipients[] = [
    //         'id' => $viewed_user->ID,
    //         'name' => $viewed_user->display_name,
    //         'email' => $viewed_user->user_email,
    //         'avatar' => get_avatar_url($viewed_user->ID, ['size' => 48]),
    //         'role' => implode(', ', $display_roles),
    //         'assigned_business_ids' => [$viewed_user->ID], // Self-assigned for UI logic
    //     ];
    // }



    $business_fields = [
        'business_name',
        'float_balance',
        'business_website',
        'assigned_business_user',
        'business_id',
        'billing_details',
        'billing_details_2',
        'approved_billing',
        'business_float_id',
        'business_abn',
        'business_currency',
        'address_line1',
        'address_line2',
        'suburb',
        'state',
        'country',
        'postcode'
    ];

    // $business_data = ['user_id' => $user_id];
    $business_data = [
        'user_id' => $user_id,
        'is_admin' => filter_var($is_admin, FILTER_VALIDATE_BOOLEAN),
        'is_business_user' => filter_var($is_business_user, FILTER_VALIDATE_BOOLEAN),
    ];
    // echo '<pre>';
    // var_export($business_data); // This will show true/false instead of 1/""
    // echo '</pre>';

    foreach ($business_fields as $field) {
        $business_data[$field] = get_user_meta($user_id, $field, true);
    }

    $assigned_business_ids = get_user_meta($user_id, 'assigned_business_user', false);
    if ($assigned_business_ids && (int) $assigned_business_ids > 0) {
        $business_data['business_name'] = get_user_meta($user_id, 'business_name', true);
    }

    $user = get_userdata($user_id);
    if (!$user) {
        wp_send_json_error('User not found');
    }
    $user_roles = (array) $user->roles;
    $business_data['profile_business_user'] = in_array('business_user', $user_roles);

    wp_send_json($business_data);
}

// Save Business Profile Details
add_action('wp_ajax_save_business_profile_details', 'save_business_profile_details_callback');

function save_business_profile_details_callback()
{
    gcp_require_admin_ajax();
    check_ajax_referer('user_admin_nonce', 'nonce');
    parse_str($_POST['data'], $form);
    $user_id = intval($form['user_id']);

    if (!current_user_can('edit_user', $user_id)) {
        wp_send_json_error('Permission denied');
    }

    $fields_to_save = [
        'business_name',
        'business_website',
        'prepaid_limit',
        'business_id',
        'approved_billing',
        'billing_details',
        'billing_details_2',
        'business_float_id',
        'business_abn',
        'business_currency',
        'address_line1',
        'address_line2',
        'suburb',
        'state',
        'country',
        'postcode'
    ];

    foreach ($fields_to_save as $field) {
        $value = isset($form[$field]) ? sanitize_text_field($form[$field]) : '';

        if ($field === 'approved_billing') {
            update_user_meta($user_id, $field, $value === 'yes' ? 'yes' : 'no');
        } else {
            update_user_meta($user_id, $field, $value);
        }
    }

    wp_send_json_success(['message' => 'Business profile updated successfully']);
}


add_action('wp_ajax_check_user_type', 'check_user_type');
// PT-3.1: removed wp_ajax_nopriv hook — user role/email must not be publicly queryable.
function check_user_type()
{
    // PT-3.1: gate on the REQUESTING user, not the target user's role.
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Authentication required', 403 );
    }

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
    }

    $current_user_id = get_current_user_id();
    $is_admin        = current_user_can('administrator');
    $is_self         = ( $current_user_id === $user_id );

    // Only the account owner or an admin may query another user's type.
    if ( ! $is_admin && ! $is_self ) {
        wp_send_json_error( ['message' => 'You are not allowed'], 403 );
    }

    $user = get_userdata($user_id);
    if (!$user) {
        wp_send_json_error('User not found');
    }

    $user_roles       = (array) $user->roles;
    $is_business_user = in_array('business_user', $user_roles);
    $is_business_viewer = in_array('business_viewer', $user_roles);

    $business_data = [
        'email' => $user->user_email,
        'display_name' => $user->display_name,
    ];

    wp_send_json_success([
        'is_business_user' => $is_business_user,
        'is_business_viewer' => $is_business_viewer,
        'is_admin' => $is_admin,
        'business_data' => $business_data,
    ]);
}

add_action('wp_ajax_get_recipient_users', 'get_recipient_users');

function get_recipient_users()
{
    gcp_require_admin_ajax();
    $current_viewed_user_id = isset($_POST['current_viewed_user_id']) ? intval($_POST['current_viewed_user_id']) : 0;
    $current_actual_user_id = isset($_POST['current_actual_user_id']) ? intval($_POST['current_actual_user_id']) : 0;
    // $admin_user_id = isset($_POST['admin_user_id']) ? intval($_POST['admin_user_id']) : 0;

    $is_business_user = false;
    $viewed_business_user_id = 0;

    if ($current_actual_user_id) {
        $viewed_user = get_userdata($current_actual_user_id);
        $is_business_user = in_array('business_user', (array) $viewed_user->roles);
        //$viewed_business_user_id = $is_business_user ? $current_viewed_user_id : 0;
        $viewed_business_user_id = $current_viewed_user_id;
    }

    if (!$viewed_business_user_id) {
        //'is_business_user_viewed' => $is_business_user,
        wp_send_json_success([
            'is_business_user_viewed' => true,
            'viewed_business_user_id' => $viewed_business_user_id,
            'recipients' => []
        ]);
    }

    $role_display_names = [
        'external_business_admin' => 'Admin',
        'external_business_viewer' => 'Viewer',
    ];

    // Fetch users with roles recipients, external_business_admin, external_business_viewer
    $users = get_users([
        'role__in' => ['external_business_admin', 'external_business_viewer'],
        'number' => -1,
        'meta_query' => [
            [
                'key' => 'assigned_business_user',
                'value' => $viewed_business_user_id,
                'compare' => '='
            ]
        ]
    ]);

    $recipients = [];

    //This code for the Display business user itself in the list....
    if (!$is_business_user) {
        $business_user_data = get_userdata($current_viewed_user_id);
        $recipients[] = [
            'id' => $business_user_data->ID,
            'name' => $business_user_data->display_name,
            'email' => $business_user_data->user_email,
            'avatar' => get_avatar_url($business_user_data->ID, ['size' => 48]),
            'role' => 'Business User',
            'assigned_business_ids' => [$current_viewed_user_id],
        ];
    }

    foreach ($users as $user) {
        $assigned_business_ids = get_user_meta($user->ID, 'assigned_business_user', false);

        $assigned_business_ids = array_map('intval', $assigned_business_ids);

        /*
        // Check if current business user is assigned to this user
        if (!in_array($viewed_business_user_id, $assigned_business_ids)) {
            continue; // Skip users not assigned to this business user
        }*/

        // Map each role slug to display name, fallback to raw role if not mapped
        $display_roles = array_map(function ($role) use ($role_display_names) {
            return isset($role_display_names[$role]) ? $role_display_names[$role] : ucfirst(str_replace('_', ' ', $role));
        }, $user->roles);

        $recipients[] = [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => get_avatar_url($user->ID, ['size' => 48]),
            'role' => implode(', ', $display_roles),
            'assigned_business_ids' => $assigned_business_ids,
        ];
    }

    wp_send_json_success([
        'is_business_user_viewed' => $is_business_user,
        'viewed_business_user_id' => $viewed_business_user_id,
        'recipients' => $recipients
    ]);
}

// add_action('wp_ajax_transfer_business_admin', 'transfer_business_admin_callback');

add_action('wp_ajax_transfer_business_admin', 'transfer_business_admin_callback');

function transfer_business_admin_callback()
{
    gcp_require_admin_ajax();
    if (!current_user_can('administrator') && !current_user_can('external_business_admin')) {
        wp_send_json_error('Permission denied.');
    }

    $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
    $business_user_id = isset($_POST['business_user_id']) ? intval($_POST['business_user_id']) : 0;
    $transfer_role = isset($_POST['transfer_role']) ? $_POST['transfer_role'] : 'viewer';

    if (!$recipient_id || !$business_user_id) {
        wp_send_json_error('Invalid input.');
    }

    /*// Get all users assigned to this business
    $users = get_users([
        'meta_key' => 'assigned_business_user',
        'meta_value' => $business_user_id,
        'meta_compare' => '=',
        // 'fields' => ['ID', 'roles']
    ]);
    // echo '<pre>';
    // print_r($users);
    // echo '</pre>';
    // exit;

    $current_admin_id = null;

    foreach ($users as $user) {
        if (in_array('external_business_admin', $user->roles)) {
            $current_admin_id = $user->ID;
            break;
        }
    }

    // If no admin yet, just make the recipient the admin
    if (!$current_admin_id) {
        $recipient = get_userdata($recipient_id);
        if ($recipient) {
            $recipient->set_role('external_business_admin');
            wp_send_json_success('Recipient promoted to admin.');
        } else {
            wp_send_json_error('Recipient user not found.');
        }
    }

    // Swap roles if recipient is not already the admin
    if ($recipient_id == $current_admin_id) {
        wp_send_json_error('Recipient is already the admin.');
    }

    $recipient_user = get_userdata($recipient_id);
    $admin_user = get_userdata($current_admin_id);

    if (!$recipient_user || !$admin_user) {
        wp_send_json_error('One or both users not found.');
    }*/

    // Swap roles
    $recipient_user = get_userdata($recipient_id);
    if ($transfer_role == 'viewer') {
        $recipient_user->set_role('external_business_viewer');
    } else if ($transfer_role == 'admin') {
        $recipient_user->set_role('external_business_admin');
    } else {
        wp_send_json_error('Invalid input....');
    }

    wp_send_json_success('Roles successfully swapped.');
}

add_action('wp_ajax_remove_recipient_from_business_user', 'remove_recipient_from_business_user');

function remove_recipient_from_business_user()
{
    gcp_require_admin_ajax();
    $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
    $business_user_id = isset($_POST['business_user_id']) ? intval($_POST['business_user_id']) : 0;

    if (!$recipient_id || !$business_user_id) {
        wp_send_json_error('Invalid user IDs.');
    }

    // Get all assigned business IDs for this user
    $assigned = get_user_meta($recipient_id, 'assigned_business_user', false);

    if (empty($assigned)) {
        wp_send_json_error('No assigned businesses found.');
    }

    // Filter out the current business user
    $updated = array_filter($assigned, function ($id) use ($business_user_id) {
        return intval($id) !== $business_user_id;
    });

    // Remove all old entries and update with the new list
    delete_user_meta($recipient_id, 'assigned_business_user');
    delete_user_meta($recipient_id, 'business_name');

    foreach ($updated as $val) {
        add_user_meta($recipient_id, 'assigned_business_user', intval($val));
    }

    wp_send_json_success('Recipient removed from business.');
}


add_action('wp_ajax_assign_recipient_to_business', 'assign_recipient_to_business');

// Corrected assign_recipient_to_business function
function assign_recipient_to_business()
{
    gcp_require_admin_ajax();
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $business_user_id = intval($_POST['business_user_id'] ?? 0);

    if (!$recipient_id || !$business_user_id) {
        wp_send_json_error('Invalid user IDs');
    }

    // Get existing assignments
    $existing_assignments = get_user_meta($recipient_id, 'assigned_business_user', false);

    // Check if this specific business is already assigned
    if (!in_array($business_user_id, $existing_assignments)) {
        add_user_meta($recipient_id, 'assigned_business_user', $business_user_id);
    }

    wp_send_json_success();
}

add_action('wp_ajax_remove_recipient_from_business', 'remove_recipient_from_business');


// Corrected remove_recipient_from_business function
function remove_recipient_from_business()
{
    gcp_require_admin_ajax();
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $business_user_id = intval($_POST['business_user_id'] ?? 0);

    if (!$recipient_id || !$business_user_id) {
        wp_send_json_error('Invalid user IDs');
    }

    // Remove specific assignment
    delete_user_meta($recipient_id, 'assigned_business_user', $business_user_id);

    wp_send_json_success();
}

add_action('wp_ajax_get_user_order_history', 'get_user_order_history');

function get_user_order_history()
{
    gcp_require_admin_ajax();
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
    }

    // $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    // $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    // $paged = ($start / $length) + 1;
    $search_value = trim($_POST['custom_search'] ?? '');

    $all_orders_args = [
        'customer_id' => $user_id,
        'limit' => -1, // Get all for filtering
        'orderby' => 'date',
        'order' => 'DESC',
    ];
    $all_orders = wc_get_orders($all_orders_args);

    // Filter orders based on search
    $filtered_orders = array_filter($all_orders, function ($order) use ($search_value) {
        $user = $order->get_user();
        $user_name = $user ? $user->display_name : 'Guest';
        $order_id = $order->get_id();
        return empty($search_value) ||
            stripos((string) $order_id, $search_value) !== false ||
            stripos($user_name, $search_value) !== false;
    });

    // Pagination logic
    // $total_filtered = count($filtered_orders);
    // $paged_orders = array_slice($filtered_orders, $start, $length);

    $data = [];
    foreach ($filtered_orders as $order) {
        $order_id = $order->get_id();
        $order_date = $order->get_date_created() ? $order->get_date_created()->format('Y-m-d') : '';
        $status = $order->get_status();
        $user = $order->get_user();
        $user_name = $user ? $user->display_name : 'Guest';
        $invoice_number = $order->get_meta('_invoice_number');
        $order_name = $order->get_meta('_order_name');
        $_client_reference = $order->get_meta('_client_reference');
        $po_number = $order->get_meta('_po_number');

         if ($invoice_number) {
            $download_url = site_url('/wp-admin/admin-ajax.php?action=download_invoice&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce('download_invoice_' . $order_id));
            $invoice_html = '<a href="' . esc_url($download_url) . '" download>' . esc_html($invoice_number) . '</a>';
        } else {
            $invoice_html = '—';
        }

        $data[] = [
            'order_id' => '<a href="' . esc_url(wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order->get_order_key()) . '" target="_blank">#' . $order_id . '</a>',
            'order_date' => esc_html($order_date),
            'user_name' => esc_html($user_name),
            'order_status' => '<span>' . ucfirst($status) . '</span>',
            'order_name' => $order_name,
            'invoice_number' => $invoice_html,
            'payment_status' => $status === 'completed' ? 'Received' : 'Pending',
            'total' => '$' . number_format((float) $order->get_total(), 2),
            'campaign' => '—',
            'client_reference' => $_client_reference,
            'po_number' => $po_number,
            'track_cards' => '<a href="#" class="track-order-cards" data-order-id="' . $order_id . '" data-user-id="' . $user_id . '">View More</a>'
        ];
    }

    wp_send_json([
        'data' => $data,
    ]);
}



add_action('wp_ajax_get_user_track_cards', 'get_user_gift_cards_callback');

function get_user_gift_cards_callback()
{
    gcp_require_admin_ajax();
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    if (!$user_id) {
        wp_send_json_error(['message' => 'Missing user ID']);
    }

    $user = get_userdata($user_id);
    if (!$user) {
        wp_send_json_error(['message' => 'Invalid user']);
    }

    $user_email = $user->user_email;
    $user_display_name = $user->display_name;
    // echo '<pre>';
    // print_r($user_display_name);
    // echo '</pre>';

    // Base args
    $base_args = [
        'post_type' => 'gift_card',
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => [
            [
                'key' => 'business_name',
                'value' => $user_display_name,
                'compare' => 'LIKE'
            ]
        ],
        'fields' => 'ids',
        'posts_per_page' => -1
    ];

    // Get ALL matching post IDs (no pagination - fetch all cards)
    $all_query = new WP_Query($base_args);
    $all_ids = $all_query->posts;
    $total_records = count($all_ids);

    $cards = [];

    // Process all cards (no pagination limit)
    foreach ($all_ids as $post_id) {
        // $created = get_the_date('F j Y', $post_id); // Format: July 7 2025
        $created_ts = (int) get_post_time('U', true, $post_id);
        $created = get_the_date('F j Y', $post_id); // e.g. July 7 2025
        $sku = get_post_meta($post_id, '_product_sku', true);
        $product_id = wc_get_product_id_by_sku($sku);
        $product_title = $product_id ? get_the_title($product_id) : '';
        $price = get_post_meta($post_id, '_price', true);

        $type = trim($product_title . ' ' . $price);

        $card_number = get_post_meta($post_id, '_gift_card_number_enc', true);
        $masked_card_number = 'XXXX XXXX';
        
        if (!empty($post_id)) {
            $details_url = add_query_arg(
                ['card_id' => $post_id],
                home_url('/gift-card-detail/')
            );

            $masked_card_number = '<a href="' . esc_url($details_url) . '" target="_blank" style="text-decoration:underline; color:inherit;">XXXX XXXX</a>';
        }

        $order_id = get_post_meta($post_id, '_order_id', true);
        $order_key = '';
        $order_link = '';

        if ($order_id && is_numeric($order_id)) {
            $order = wc_get_order($order_id);
            if ($order && is_a($order, 'WC_Order')) {
                $order_key = $order->get_order_key();
                $order_link = wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order_key;
            }
        }

        $email_to = get_post_meta($post_id, '_recipient_email', true);
        $sms_to = get_post_meta($post_id, '_recipient_phone', true);
        $status = get_post_meta($post_id, '_gift_card_send', true);

        // echo '<pre>'; print_r($status); echo '</pre>';
        // exit;
        if($status == 'Instant'){
            $status = 'Delivered';
        } else if($status == 'Pending Order Completion'){
            $status = 'Pending';
        }
        // $status = get_post_meta($post_id);

        // Search filtering
        if (!empty($search)) {
            if (
                stripos($card_number, $search) === false &&
                stripos($email_to, $search) === false &&
                stripos($sms_to, $search) === false
            ) {
                continue;
            }
        }

        $cards[] = [
            'created' => $created,
            'created_sort' => $created_ts,
            'type' => ucfirst($type),
            'card_number' => $masked_card_number,
            'order_id' => $order_id,
            'order_link' => $order_link,
            'email' => $email_to,
            'sms' => $sms_to ?: '-',
            'status' => $status,
        ];
    }
    // echo '<pre>';
    // print_r($cards);
    // echo '</pre>';


    wp_send_json([
        'draw' => intval($_POST['draw']),
        'recordsTotal' => $total_records,
        'recordsFiltered' => $total_records, // Adjust if you want to filter by search
        'data' => array_values($cards),
    ]);
}

function process_card_item($item, $product, $order, $search)
{
    // $card_number = $product->get_sku() ?: $product->get_id();
    $recipient_email = $order->get_meta('_recipient_email');
    $recipient_phone = $order->get_meta('_recipient_phone');
    $card_number = $item->get_meta('_gift_card_number_enc'); // Or adjust meta key as needed

    return [
        'created' => $order->get_date_created()->date('Y-m-d H:i'),
        'type' => $product->get_name() . ' (' . wc_price($product->get_price()) . ')',
        'card_number' => $card_number,
        'email' => $recipient_email ?: 'N/A',
        'sms' => $recipient_phone ?: 'N/A',
        'status' => $product->get_status(),
        'order_id' => $order->get_id()
    ];
}

add_action('wp_ajax_export_track_cards', 'handle_track_cards_export');

function handle_track_cards_export()
{
    gcp_require_admin_ajax();
    // Validate & sanitize input
    $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    if (!$user_id || !($user = get_userdata($user_id))) {
        wp_die('Invalid user ID');
    }

    $user_display_name = $user->display_name;


    $args = [
        'post_type' => 'gift_card',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => [
            [
                'key' => 'business_name',
                'value' => $user_display_name,
                'compare' => 'LIKE'
            ]
        ]
    ];

    // Add order filter if given
    if ($order_id) {
        $args['meta_query'][] = [
            'key' => '_order_id',
            'value' => $order_id,
            'compare' => '='
        ];
    }

    $query = new WP_Query($args);
    $cards = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $created = get_the_date('F j Y', $post_id); // Format: July 7 2025

            $sku = get_post_meta($post_id, '_product_sku', true);
            $product_id = wc_get_product_id_by_sku($sku);
            $product_title = $product_id ? get_the_title($product_id) : '';
            $price = get_post_meta($post_id, '_price', true);
            $type = trim($product_title . ' ' . $price);

            $card_number = get_post_meta($post_id, '_gift_card_number_enc', true);
            $masked_card_number = 'XXXX XXXX'; // Match the table display
            $order_id_meta = get_post_meta($post_id, '_order_id', true);
            $email = get_post_meta($post_id, '_recipient_email', true);
            $sms = get_post_meta($post_id, '_recipient_phone', true);
            $status = get_post_meta($post_id, '_gift_card_send', true);
            
            // Apply same status normalization as main function
            if ($status == 'Instant') {
                $status = 'Delivered';
            } else if ($status == 'Pending Order Completion') {
                $status = 'Pending';
            }
            
            // Fallback if status is empty
            if (empty($status)) {
                $status = get_post_status($post_id);
            }

            // Apply search filter
            if (
                !empty($search) &&
                stripos($card_number, $search) === false &&
                stripos($email, $search) === false &&
                stripos($sms, $search) === false
            ) {
                continue;
            }

            $cards[] = [
                'created' => $created,
                'type' => ucfirst($type),
                'card_number' => $masked_card_number,
                'order_id' => $order_id_meta,
                'email' => $email ?: '-',
                'sms' => $sms ?: '-',
                'status' => $status ?: '-'
            ];
        }
        wp_reset_postdata();
    }

    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="track-cards-' . date('Ymd-His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Created Date', 'Card Type', 'Card Number', 'Email', 'SMS', 'Status', 'Order ID']);

    foreach ($cards as $card) {
        fputcsv($output, [
            $card['created'],
            $card['type'],
            $card['card_number'],
            $card['email'],
            $card['sms'],
            $card['status'],
            $card['order_id']
        ]);
    }

    fclose($output);
    exit;
}


function process_order_item($item, $order)
{
    $product = $item->get_product();
    $cards = array();

    // If product has multiple cards (e.g., quantity > 1)
    $qty = $item->get_quantity();
    for ($i = 0; $i < $qty; $i++) {
        $cards[] = array(
            'created' => $order->get_date_created()->date('Y-m-d H:i'),
            'type' => $product->get_name(),
            'card_number' => $product->get_sku() ?: $product->get_id() . '-' . ($i + 1),
            'email' => $order->get_meta('_recipient_email') ?: 'N/A',
            'sms' => $order->get_meta('_recipient_phone') ?: 'N/A',
            'status' => $product->get_status(),
            'order_id' => $order->get_id()
        );
    }

    return $cards;
}


// Add custom fields to product category
add_action('product_cat_add_form_fields', 'add_category_custom_fields');
add_action('product_cat_edit_form_fields', 'edit_category_custom_fields');

function add_category_custom_fields()
{

    ?>
    <div class="form-field">
        <label for="priority"><?php _e('Priority', 'text-domain'); ?></label>
        <input type="number" name="priority" id="priority" value="1" min="1">
        <p class="description"><?php _e('Lower numbers show first', 'text-domain'); ?></p>
    </div>

    <div class="form-field">
        <label for="category_status"><?php _e('Status', 'text-domain'); ?></label>
        <select name="category_status" id="category_status">
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="deactivated">Deactivated</option>
        </select>
        <p class="description"><?php _e('Category visibility status', 'text-domain'); ?></p>
    </div>

    <div class="form-field">
        <label for="category_icon"><?php _e('Icon Image', 'text-domain'); ?></label>
        <div class="image-preview-container" style="width: 150px;">
            <img src="" class="image-preview" style="max-width: 100%; display: none;">
            <input type="hidden" name="category_icon" id="category_icon" value="">
            <button type="button" class="upload-image-button button">Upload Icon</button>
            <p class="description">Square image (min 256x256px)</p>
        </div>
    </div>
    <div class="form-field">
        <label for="category_banner"><?php _e('Banner Image', 'text-domain'); ?></label>
        <div class="image-preview-container" style="width: 100%;">
            <img src="" class="image-preview" style="max-width: 100%; display: none;">
            <input type="hidden" name="category_banner" id="category_banner" value="">
            <button type="button" class="upload-image-button button">Upload Banner</button>
            <p class="description">Recommended size: 1406x3456px</p>
        </div>
    </div>
    <?php
}

function edit_category_custom_fields($term)
{
    $icon_id = get_term_meta($term->term_id, 'category_icon', true);
    $banner_id = get_term_meta($term->term_id, 'category_banner', true);
    $priority = get_term_meta($term->term_id, 'priority', true) ?: 1;
    $status = get_term_meta($term->term_id, 'category_status', true) ?: 'active';
    ?>

    <tr class="form-field">
        <th scope="row"><label for="priority"><?php _e('Priority', 'text-domain'); ?></label></th>
        <td>
            <input type="number" name="priority" id="priority" value="<?php echo esc_attr($priority); ?>" min="1">
            <p class="description"><?php _e('Lower numbers show first', 'text-domain'); ?></p>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="category_status"><?php _e('Status', 'text-domain'); ?></label></th>
        <td>
            <select name="category_status" id="category_status">
                <option value="active" <?php selected($status, 'active'); ?>>Active</option>
                <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                <option value="deactivated" <?php selected($status, 'deactivated'); ?>>Deactivated</option>
            </select>
            <p class="description"><?php _e('Category visibility status', 'text-domain'); ?></p>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="category_icon"><?php _e('Icon Image', 'text-domain'); ?></label></th>
        <td>
            <div class="image-preview-container">
                <img src="<?php echo $icon_id ? wp_get_attachment_url($icon_id) : ''; ?>" class="image-preview"
                    style="max-width: 150px; margin-bottom: 10px; <?php echo !$icon_id ? 'display:none;' : ''; ?>">
                <input type="hidden" name="category_icon" id="category_icon" value="<?php echo $icon_id; ?>">
                <button type="button" class="upload-image-button button">Upload/Change Icon</button>
                <button type="button" class="remove-image-button button"
                    style="<?php echo !$icon_id ? 'display:none;' : ''; ?>">Remove</button>
                <p class="description">Square image (min 256x256px)</p>
            </div>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="category_banner"><?php _e('Banner Image', 'text-domain'); ?></label></th>
        <td>
            <div class="image-preview-container">
                <img src="<?php echo $banner_id ? wp_get_attachment_url($banner_id) : ''; ?>" class="image-preview"
                    style="max-width: 300px; margin-bottom: 10px; <?php echo !$banner_id ? 'display:none;' : ''; ?>">
                <input type="hidden" name="category_banner" id="category_banner" value="<?php echo $banner_id; ?>">
                <button type="button" class="upload-image-button button">Upload/Change Banner</button>
                <button type="button" class="remove-image-button button"
                    style="<?php echo !$banner_id ? 'display:none;' : ''; ?>">Remove</button>
                <p class="description">Recommended size: 1406x3456px</p>
            </div>
        </td>
    </tr>
    <?php
}

// Save custom fields
add_action('created_product_cat', 'save_category_custom_fields');
add_action('edited_product_cat', 'save_category_custom_fields');

function save_category_custom_fields($term_id)
{
    // Save priority with default 1
    $priority = isset($_POST['priority']) ? absint($_POST['priority']) : 1;
    update_term_meta($term_id, 'priority', $priority);

    // Save status with validation
    $allowed_statuses = ['active', 'pending', 'deactivated'];
    $status = isset($_POST['category_status']) && in_array($_POST['category_status'], $allowed_statuses)
        ? sanitize_text_field($_POST['category_status'])
        : 'active';
    update_term_meta($term_id, 'category_status', $status);

    // Save images
    if (isset($_POST['category_icon'])) {
        update_term_meta($term_id, 'category_icon', absint($_POST['category_icon']));
    }
    if (isset($_POST['category_banner'])) {
        update_term_meta($term_id, 'category_banner', absint($_POST['category_banner']));
    }
}

// Enqueue media uploader
add_action('admin_enqueue_scripts', 'category_image_upload_scripts');
function category_image_upload_scripts($hook)
{
    wp_enqueue_media();
    wp_enqueue_script('category-image-upload', get_template_directory_uri() . '/assets/js/admin.js', array('jquery'), null, true);


    // Only load on product category add/edit pages
    $screen = get_current_screen();
    
    if (!$screen) return;
    
    // Load only on product_cat taxonomy pages
    $allowed_hooks = array('edit-tags.php', 'term.php');
    $allowed_screens = array('edit-product_cat', 'product_cat');
    
    if (
        in_array($hook, $allowed_hooks) && 
        in_array($screen->id, $allowed_screens)
    ) {
        wp_enqueue_media();
        wp_enqueue_script(
            'category-image-upload',
            get_template_directory_uri() . '/assets/js/admin.js',
            array('jquery'),
            null,
            true
        );
    }
}

add_action('wp_ajax_fetch_category_details', 'fetch_category_details');
add_action('wp_ajax_nopriv_fetch_category_details', 'fetch_category_details');

function fetch_category_details()
{
    gcp_require_logged_in_ajax();
    check_ajax_referer( 'category_nonce', 'nonce' );
    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $term = get_term($term_id, 'product_cat');

    if (!$term || is_wp_error($term)) {
        wp_send_json_error('Category not found');
    }

    // Get products
    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ]
    ]);

    $product_data = [];
    foreach ($products as $index => $product) {
        $wc_product = wc_get_product($product->ID);
        $image_id = $wc_product->get_image_id();
        $image_url = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');
        $product_data[] = [
            'rank' => $index + 1,
            'gift_card' => $wc_product->get_name(),
            'name' => $wc_product->get_name(),
            'image' => $image_url,
            'denomination_type' => $wc_product->get_meta('denomination_type') ?: 'N/A',
            'denomination' => $wc_product->get_price() ?: 'N/A',
            'status' => ucfirst($wc_product->get_status())
        ];
    }
    wp_send_json_success([
        'term_id' => $term->term_id,
        'name' => html_entity_decode($term->name),
        'description' => $term->description,
        'thumbnail' => wp_get_attachment_url(get_term_meta($term_id, 'thumbnail_id', true)),
        'thumbnail_id' => get_term_meta($term_id, 'thumbnail_id', true),
        'icon' => wp_get_attachment_url(get_term_meta($term_id, 'category_icon', true)),
        'icon_id' => get_term_meta($term_id, 'category_icon', true),
        'banner' => wp_get_attachment_url(get_term_meta($term_id, 'category_banner', true)),
        'banner_id' => get_term_meta($term_id, 'category_banner', true),
        'status' => get_term_meta($term_id, 'category_status', true) ?: 'active',
        'products' => $product_data,
        'priority' => get_term_meta($term_id, 'priority', true), // Corrected comma here
    ]);

}

// Save category data
add_action('wp_ajax_save_category_changes', 'save_category_changes');
add_action('wp_ajax_nopriv_save_category_changes', 'save_category_changes');

function save_category_changes()
{
    gcp_require_logged_in_ajax();
    check_ajax_referer( 'category_nonce', 'nonce' );
    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;

    if (!$term_id) {
        wp_send_json_error('Invalid term ID');
    }

    // Prepare term data (only description updates)
    $term_data = [];
    if (isset($_POST['description'])) {
        $term_data['description'] = sanitize_textarea_field($_POST['description']);
    }

    // Update term description if provided
    if (!empty($term_data)) {
        $term_data['term_id'] = $term_id;
        $term_data['taxonomy'] = 'product_cat';

        $result = wp_update_term($term_id, 'product_cat', $term_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
    }

    // Prepare meta data (status and images only)
    $meta_fields = [
        'thumbnail_id' => 'thumbnail_id',
        'icon_id' => 'category_icon',
        'banner_id' => 'category_banner',
        'status' => 'category_status'
    ];

    foreach ($meta_fields as $post_key => $meta_key) {
        if (isset($_POST[$post_key])) {
            // Special handling for status field
            if ($post_key === 'status') {
                $allowed_statuses = ['active', 'pending', 'deactivated'];
                $value = in_array($_POST[$post_key], $allowed_statuses)
                    ? sanitize_text_field($_POST[$post_key])
                    : 'active';
            }
            // Handle image IDs
            else {
                $value = (int) $_POST[$post_key];
            }

            update_term_meta($term_id, $meta_key, $value);
        }
    }

    // Get updated status directly from database
    $current_status = get_term_meta($term_id, 'category_status', true) ?: 'active';

    wp_send_json_success([
        'term_id' => $term_id,
        'description' => $term_data['description'] ?? '',
        'thumbnail' => isset($_POST['thumbnail_id']) ? wp_get_attachment_url((int) $_POST['thumbnail_id']) : '',
        'icon' => isset($_POST['icon_id']) ? wp_get_attachment_url((int) $_POST['icon_id']) : '',
        'banner' => isset($_POST['banner_id']) ? wp_get_attachment_url((int) $_POST['banner_id']) : '',
        'status' => $current_status
    ]);
}

// Add these to your existing PHP code
add_action('wp_ajax_get_products_for_popup', 'get_products_for_popup');
add_action('wp_ajax_nopriv_get_products_for_popup', 'get_products_for_popup');

function get_products_for_popup()
{
    gcp_require_logged_in_ajax();
    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;

    // Get all products
    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    $product_data = [];
    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        $image_id = $product->get_image_id();

        $product_data[] = [
            'id' => $product_id,
            'name' => $product->get_name(),
            'image' => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src()
        ];
    }

    // Get currently assigned products
    $assigned_products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ]
    ]);

    wp_send_json_success([
        'products' => $product_data,
        'assigned_products' => $assigned_products
    ]);
}

add_action('wp_ajax_assign_products_to_category', 'assign_products_to_category');
add_action('wp_ajax_nopriv_assign_products_to_category', 'assign_products_to_category');

function assign_products_to_category()
{
    gcp_require_logged_in_ajax();
    check_ajax_referer( 'category_nonce', 'nonce' );
    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];

    if (!$term_id) {
        wp_send_json_error('Invalid term ID');
    }

    // Get all products currently in this category
    $current_products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ]
    ]);

    // Remove all products from category first
    foreach ($current_products as $product_id) {
        wp_remove_object_terms($product_id, $term_id, 'product_cat');
    }

    $rows = get_field('sku_assigned_arr', 'product_cat_' . $term_id);
    if (!is_array($rows)) {
        $rows = [];
    }

    $temp_rows = array_column($rows, 'assigned_product');

    //delete_field('sku_assigned_arr', 'product_cat_' . $term_id);
    // Add selected products to category
    foreach ($product_ids as $product_id) {
        $temp_key = array_search($product_id, $temp_rows, true);

        if ($temp_key !== false) {
            unset($temp_rows[$temp_key]);
        } else {
            $rows[] = [
                'assigned_product' => $product_id
            ];
        }

        wp_set_object_terms($product_id, $term_id, 'product_cat', true);
    }

    if (!empty($temp_rows)) {
        foreach ($temp_rows as $trkey => $trvalue) {
            unset($rows[$trkey]);
        }
    }

    update_field('sku_assigned_arr', $rows, 'product_cat_' . $term_id);

    wp_send_json_success([
        'message' => 'Products assigned successfully',
        'count' => count($product_ids)
    ]);

}


add_action('wp_ajax_fetch_category_products', 'fetch_category_products');
add_action('wp_ajax_nopriv_fetch_category_products', 'fetch_category_products');

function fetch_category_products()
{
    gcp_require_logged_in_ajax();

    if ( ! check_ajax_referer( 'category_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
        wp_die();
    }

    $draw    = intval( $_POST['draw']   ?? 0 );
    $start   = intval( $_POST['start'] ?? 0 );
    $length  = intval( $_POST['length'] ?? 10 );
    $search  = sanitize_text_field( $_POST['search']['value'] ?? '' );
    $term_id = intval( $_POST['term_id'] ?? 0 );

    if ( $term_id <= 0 ) {
        wp_send_json( [ 'draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [] ] );
        wp_die();
    }

    // Read assigned product IDs from ACF repeater term meta (in assignment order = rank order)
    $row_count = (int) get_term_meta( $term_id, 'sku_assigned_arr', true );
    $assigned_ids = [];
    for ( $i = 0; $i < $row_count; $i++ ) {
        $pid = (int) get_term_meta( $term_id, "sku_assigned_arr_{$i}_assigned_product", true );
        if ( $pid > 0 ) {
            $assigned_ids[] = $pid;
        }
    }

    $total_records = count( $assigned_ids );

    // Apply search filter against product title
    if ( ! empty( $search ) ) {
        $assigned_ids = array_filter( $assigned_ids, function( $pid ) use ( $search ) {
            $title = get_the_title( $pid );
            return stripos( $title, $search ) !== false;
        } );
        $assigned_ids = array_values( $assigned_ids );
    }

    $filtered_records = count( $assigned_ids );

    // Paginate
    $paged_ids = array_slice( $assigned_ids, $start, $length );

    $data = [];
    foreach ( $paged_ids as $rank_index => $product_id ) {
        $wc_product = wc_get_product( $product_id );
        if ( ! $wc_product ) continue;
        $image_id = $wc_product->get_image_id();
        $data[] = [
            'rank'              => $start + $rank_index + 1,
            'image'             => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src(),
            'name'              => $wc_product->get_name(),
            'denomination_type' => $wc_product->get_meta( 'denomination_type' ) ?: 'N/A',
            'denomination'      => $wc_product->get_price() ?: 'N/A',
            'status'            => ucfirst( $wc_product->get_status() ),
        ];
    }

    wp_send_json( [
        'draw'            => $draw,
        'recordsTotal'    => $total_records,
        'recordsFiltered' => $filtered_records,
        'data'            => $data,
    ] );
}

add_action('wp_ajax_export_category_products', 'export_category_products');

function export_category_products()
{
    gcp_require_admin_ajax();
    // Verify nonce
    if (!wp_verify_nonce($_POST['security'], 'export_products_nonce')) {
        wp_die('Invalid nonce');
    }

    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    // Get products with same filters as DataTable
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ],
        's' => $search
    ];

    $products = get_posts($args);

    // CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="products_export.csv"');

    $output = fopen('php://output', 'w');

    // CSV header
    fputcsv($output, [
        'Rank',
        'Product Name',
        'Denomination Type',
        'Denomination',
        'Status'
    ]);

    // CSV data
    foreach ($products as $index => $product) {
        $wc_product = wc_get_product($product->ID);

        fputcsv($output, [
            $index + 1,
            $wc_product->get_name(),
            $wc_product->get_meta('denomination_type') ?: 'N/A',
            $wc_product->get_price() ?: 'N/A',
            ucfirst($wc_product->get_status())
        ]);
    }

    fclose($output);
    exit;
}
add_action('wp_ajax_create_new_product_category', 'handle_create_new_product_category');

function handle_create_new_product_category()
{
    gcp_require_admin_ajax();
    check_ajax_referer('bulk_create_category_nonce', 'nonce');
    // Check permissions
    if (!current_user_can('manage_product_terms')) {
        wp_send_json_error('You do not have sufficient permissions');
    }

    // Validate required fields
    if (empty($_POST['category_name'])) {
        wp_send_json_error('Category name is required');
    }
    if (empty($_POST['priority']) || !is_numeric($_POST['priority'])) {
        wp_send_json_error('Priority must be a number');
    }

    // Validate status
    // Validate status - CORRECTED LINE
    $allowed_statuses = ['active', 'pending', 'deactivated'];
    $status = isset($_POST['category_status']) && in_array($_POST['category_status'], $allowed_statuses) ? sanitize_text_field($_POST['category_status']) : 'active';

    // Create the category
    $result = wp_insert_term(
        sanitize_text_field($_POST['category_name']),
        'product_cat',
        array(
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
            'parent' => 0
        )
    );

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    $term_id = $result['term_id'];

    // Save all meta fields
    $meta_fields = [
        'category_status' => $status,
        'priority' => absint($_POST['priority']),
        'thumbnail_id' => isset($_POST['thumbnail_id']) ? absint($_POST['thumbnail_id']) : 0,
        'category_icon' => isset($_POST['icon_id']) ? absint($_POST['icon_id']) : 0,
        'category_banner' => isset($_POST['banner_id']) ? absint($_POST['banner_id']) : 0
    ];

    foreach ($meta_fields as $key => $value) {
        update_term_meta($term_id, $key, $value);
    }

    $rows = array();
    // Assign products if any were selected
    if (isset($_POST['product_ids']) && is_array($_POST['product_ids'])) {
        foreach ($_POST['product_ids'] as $product_id) {
            $product_id = absint($product_id);
            if ($product_id > 0) {
                wp_set_object_terms($product_id, $term_id, 'product_cat', true);
                $rows[] = [
                    'assigned_product' => $product_id
                ];
            }
        }
    }

    update_field('sku_assigned_arr', $rows, 'product_cat_' . $term_id);

    wp_send_json_success([
        'term_id' => $term_id,
        'name' => sanitize_text_field($_POST['category_name']),
        'priority' => $meta_fields['priority'],
        'status' => $status
    ]);
}

// Add this to your existing PHP code
add_action('wp_ajax_get_all_products_for_assignment', 'get_all_products_for_assignment');
add_action('wp_ajax_nopriv_get_all_products_for_assignment', 'get_all_products_for_assignment');

function get_all_products_for_assignment()
{
    gcp_require_logged_in_ajax();
    // Get all products
    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    $product_data = [];
    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        $image_id = $product->get_image_id();

        $product_data[] = [
            'id' => $product_id,
            'name' => $product->get_name(),
            'image' => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src()
        ];
    }

    wp_send_json_success([
        'products' => $product_data
    ]);
}

add_action('product_brand_add_form_fields', 'add_brand_status_field');
add_action('product_brand_edit_form_fields', 'edit_brand_status_field');

function add_brand_status_field()
{
    ?>
    <div class="form-field">
        <label for="brand_status"><?php _e('Status', 'text-domain'); ?></label>
        <select name="brand_status" id="brand_status">
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="deactivated">Deactivated</option>
            <option value="awaiting-publishing">Awaiting Publishing</option>
            <option value="closed">Closed</option>
        </select>
        <p class="description"><?php _e('Brand visibility status', 'text-domain'); ?></p>
    </div>
    <?php
}

function edit_brand_status_field($term)
{
    $status = get_term_meta($term->term_id, 'brand_status', true) ?: 'active';
    ?>
    <tr class="form-field">
        <th scope="row"><label for="brand_status"><?php _e('Status', 'text-domain'); ?></label></th>
        <td>
            <select name="brand_status" id="brand_status">
                <option value="active" <?php selected($status, 'active'); ?>>Active</option>
                <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                <option value="deactivated" <?php selected($status, 'deactivated'); ?>>Deactivated</option>
                <option value="awaiting-publishing" <?php selected($status, 'awaiting-publishing'); ?>>Awaiting Publishing
                </option>
                <option value="closed" <?php selected($status, 'closed'); ?>>Closed</option>
            </select>
            <p class="description"><?php _e('Brand visibility status', 'text-domain'); ?></p>
        </td>
    </tr>
    <?php
}
add_action('created_product_brand', 'save_brand_status_field');
add_action('edited_product_brand', 'save_brand_status_field');

function save_brand_status_field($term_id)
{
    $allowed_statuses = ['active', 'pending', 'deactivated', 'closed', 'awaiting-publishing'];
    $status = isset($_POST['brand_status']) && in_array($_POST['brand_status'], $allowed_statuses)
        ? sanitize_text_field($_POST['brand_status'])
        : 'active';
    update_term_meta($term_id, 'brand_status', $status);
}



// add_action('wp_ajax_export_brands', 'handle_brand_export');
// add_action('wp_ajax_nopriv_export_brands', 'handle_brand_export');

// function handle_brand_export()
// {

//     if (!current_user_can('manage_options')) {
//         wp_die('Unauthorized access');
//     }

//     $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
//     $view_type = isset($_POST['view']) ? sanitize_text_field($_POST['view']) : 'list';

//     // Query brands with search filter
//     $args = [
//         'taxonomy' => 'product_brand',
//         'hide_empty' => false,
//         'orderby' => 'name',
//         'name__like' => $search,
//     ];

//     // If search is numeric, check ID as well
//     if (is_numeric($search)) {
//         $args['include'] = [intval($search)];
//     }

//     $brands = get_terms($args);

//     // Prepare CSV output
//     header('Content-Type: text/csv');
//     header('Content-Disposition: attachment; filename="brands-export.csv"');

//     $output = fopen('php://output', 'w');

//     if ($view_type === 'list') {
//         // List view export
//         fputcsv($output, [
//             'ID',
//             'Brand Name',
//             'Assigned Products',
//             'Thumbnail URL',
//             'Status'
//         ]);

//         foreach ($brands as $brand) {
//             $thumbnail_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
//             $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
//             $status = get_term_meta($brand->term_id, 'brand_status', true);

//             fputcsv($output, [
//                 $brand->term_id,
//                 $brand->name,
//                 $brand->count,
//                 $thumbnail_url,
//                 $status
//             ]);
//         }
//     } else {
//         // Thumbnail view export
//         fputcsv($output, [
//             'Brand Name',
//             'Assigned Products',
//             'Thumbnail URL'
//         ]);

//         foreach ($brands as $brand) {
//             $thumbnail_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
//             $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';

//             fputcsv($output, [
//                 $brand->name,
//                 $brand->count,
//                 $thumbnail_url
//             ]);
//         }
//     }

//     fclose($output);
//     // exit;
// }

add_action('wp_ajax_create_new_brand', 'create_new_brand_callback');

function create_new_brand_callback()
{
    gcp_require_admin_ajax();
    check_ajax_referer('brands_nonce', 'nonce');
    $brand_name = sanitize_text_field($_POST['brand_name']);
    $brand_status = intval($_POST['brand_status']);

    if (empty($brand_name)) {
        wp_send_json_error('Brand name is required.');
    }

    if (empty($_FILES['brand_logo_file'])) {
        wp_send_json_error('Brand logo is required.');
    }

    $thumbnail_id = '';

    if (!empty($_FILES['brand_logo_file']['name'])) {
        global $wpdb;

        $file_name = sanitize_file_name($_FILES['brand_logo_file']['name']);

        // Look for existing attachment with same file name in _wp_attached_file
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT pm.post_id
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_wp_attached_file'
                 AND pm.meta_value LIKE %s
                 AND p.post_type = 'attachment'
                 LIMIT 1",
                '%' . $wpdb->esc_like($file_name) // match filename inside path
            )
        );

        if ($existing) {
            $thumbnail_id = $existing; // Reuse existing
        } else {
            // Upload new image
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $uploaded = media_handle_upload('brand_logo_file', 0);
            if (is_wp_error($uploaded)) {
                wp_send_json_error('Image upload failed: ' . $uploaded->get_error_message());
            } else {
                $thumbnail_id = $uploaded;
            }
        }
    }

    // Insert brand
    $term = wp_insert_term($brand_name, 'product_brand');
    if (is_wp_error($term)) {
        wp_send_json_error($term->get_error_message());
    }

    $term_id = $term['term_id'];

    if ($thumbnail_id) {
        update_term_meta($term_id, 'thumbnail_id', $thumbnail_id);
    }

    if ($brand_status) {
        wp_set_object_terms($term_id, [$brand_status], 'brand_status', false);
    }

    wp_send_json_success('Brand created.');
}


// Fetch Brand Details
add_action('wp_ajax_fetch_brand_details', 'fetch_brand_details');


function fetch_brand_details()
{
    gcp_require_admin_ajax();
    check_ajax_referer( 'category_nonce', 'nonce' );
    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $term = get_term($term_id, 'product_brand');

    if (!$term || is_wp_error($term)) {
        wp_send_json_error('Brand not found');
    }

    // Get products
    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'product_brand',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ]
    ]);

    $product_data = [];
    foreach ($products as $index => $product) {
        $wc_product = wc_get_product($product->ID);
        $image_id = $wc_product->get_image_id();
        $image_url = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');
        $product_data[] = [
            'rank' => $index + 1,
            'image' => $image_url,
            'name' => $wc_product->get_name(),
            'denomination_type' => $wc_product->get_meta('denomination_type') ?: 'N/A',
            'denomination' => $wc_product->get_price() ?: 'N/A',
            'status' => ucfirst($wc_product->get_status())
        ];
    }

    wp_send_json_success([
        'term_id' => $term->term_id,
        'name' => $term->name,
        'description' => $term->description,
        'thumbnail' => wp_get_attachment_url(get_term_meta($term_id, 'thumbnail_id', true)),
        'thumbnail_id' => get_term_meta($term_id, 'thumbnail_id', true),
        'status' => get_term_meta($term_id, 'brand_status', true) ?: 'active',
        'products' => $product_data
    ]);
}
add_action('wp_ajax_save_brand_changes', 'save_brand_changes');
function save_brand_changes()
{
    gcp_require_admin_ajax();
    check_ajax_referer('brands_nonce', 'nonce');
    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    if (!$term_id) {
        wp_send_json_error('Invalid brand ID');
    }

    // Update description
    if (isset($_POST['description'])) {
        $description = sanitize_textarea_field($_POST['description']);
        wp_update_term($term_id, 'product_brand', [
            'description' => $description
        ]);
    }

    // Update status
    if (isset($_POST['status'])) {
        update_term_meta($term_id, 'brand_status', sanitize_text_field($_POST['status']));
    }

    // Upload and handle brand logo image
    if (!empty($_FILES['brand_logo_file']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $uploaded = media_handle_upload('brand_logo_file', 0);

        if (is_wp_error($uploaded)) {
            wp_send_json_error('Image upload failed: ' . $uploaded->get_error_message());
        } else {
            update_term_meta($term_id, 'thumbnail_id', $uploaded);
        }
    } elseif (isset($_POST['brand_logo_id'])) {
        // Handle logo removal if no file is uploaded and the ID is 0
        $logo_id = (int) $_POST['brand_logo_id'];
        if ($logo_id > 0) {
            update_term_meta($term_id, 'thumbnail_id', $logo_id);
        } else {
            delete_term_meta($term_id, 'thumbnail_id');
        }
    }

    // Prepare the response
    $term = get_term($term_id);
    $logo_id = (int) get_term_meta($term_id, 'thumbnail_id', true);
    $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : wc_placeholder_img_src();

    $response = [
        'term_id' => $term_id,
        'name' => $term->name,
        'logo' => $logo_url,
        'status' => sanitize_text_field($_POST['status'] ?? ''),
        'count' => $term->count
    ];

    wp_send_json_success($response);
}


add_action('wp_ajax_fetch_brand_products', 'fetch_brand_products');

function fetch_brand_products()
{
    gcp_require_admin_ajax();
    global $wpdb;

    $term_id = intval($_POST['term_id']);
    $args = [
        'post_type' => 'product',
        'post_status' => 'any',
        'posts_per_page' => $_POST['length'],
        'offset' => $_POST['start'],
        's' => sanitize_text_field($_POST['search']['value']),
        'tax_query' => [
            [
                'taxonomy' => 'product_brand',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ]
    ];

    // Total records
    $total_products = get_terms([
        'taxonomy' => 'product_brand',
        'field' => 'term_id',
        'terms' => $term_id,
        'hide_empty' => false
    ]);

    $products = get_posts($args);

    $data = [];
    foreach ($products as $index => $product) {
        $wc_product = wc_get_product($product->ID);
        $data[] = [
            'rank' => $_POST['start'] + $index + 1,
            'image' => wp_get_attachment_url($wc_product->get_image_id()),
            'name' => $wc_product->get_name(),
            'denomination_type' => $wc_product->get_meta('denomination_type') ?: 'N/A',
            'denomination' => $wc_product->get_price(),
            'status' => $wc_product->get_status() === 'draft' ? 'Awaiting Publishing' : ucfirst($wc_product->get_status())
        ];
    }

    wp_send_json([
        'draw' => $_POST['draw'],
        'recordsTotal' => count($total_products),
        'recordsFiltered' => count($products),
        'data' => $data
    ]);
}

// Add to your theme's functions.php or plugin file
add_action('wp_ajax_get_products_for_brand_popup', 'get_products_for_brand_popup');

function get_products_for_brand_popup()
{
    gcp_require_admin_ajax();
    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;

    // Get all products
    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    $product_data = [];
    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        $image_id = $product->get_image_id();

        $product_data[] = [
            'id' => $product_id,
            'name' => $product->get_name(),
            'image' => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src()
        ];
    }

    // Get currently assigned products for brand
    $assigned_products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'product_brand',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ]
    ]);

    wp_send_json_success([
        'products' => $product_data,
        'assigned_products' => $assigned_products
    ]);
}

add_action('wp_ajax_assign_products_to_brand', 'assign_products_to_brand');

function assign_products_to_brand()
{
    gcp_require_admin_ajax();
    check_ajax_referer('brands_nonce', 'nonce');
    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $selected_product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];

    if (!$term_id) {
        wp_send_json_error('Invalid brand ID');
    }

    // Get currently assigned products
    $currently_assigned = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'product_brand',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ]
    ]);

    $currently_assigned = array_map('intval', $currently_assigned);

    // Products to unassign
    $to_unassign = array_diff($currently_assigned, $selected_product_ids);

    // Products to assign
    $to_assign = array_diff($selected_product_ids, $currently_assigned);

    foreach ($to_unassign as $product_id) {
        wp_remove_object_terms($product_id, $term_id, 'product_brand');
    }

    foreach ($to_assign as $product_id) {
        wp_set_object_terms($product_id, $term_id, 'product_brand', true);
    }

    wp_send_json_success([
        'count' => count($selected_product_ids)
    ]);
}


add_action('wp_ajax_export_brands_products', 'export_brands_products');

function export_brands_products()
{
    gcp_require_admin_ajax();


    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    // Get products with same filters as DataTable
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'product_brand',
                'field' => 'term_id',
                'terms' => $term_id
            ]
        ],
        's' => $search
    ];

    $products = get_posts($args);

    // CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="products_export.csv"');

    $output = fopen('php://output', 'w');

    // CSV header
    fputcsv($output, [
        'Rank',
        'Product Name',
        'Denomination Type',
        'price',
        'Status'
    ]);

    // CSV data
    foreach ($products as $index => $product) {
        $wc_product = wc_get_product($product->ID);

        fputcsv($output, [
            $index + 1,
            $wc_product->get_name(),
            $wc_product->get_meta('denomination_type') ?: 'N/A',
            $wc_product->get_price() ?: 'N/A',
            ucfirst($wc_product->get_status())
        ]);
    }

    fclose($output);
    exit;
}
// Enqueue necessary scripts and styles
function enqueue_email_logs_scripts()
{
    wp_enqueue_style('email-logs-css', get_template_directory_uri() . '/assets/css/email-logs.css');
    if (is_page('email-logs')) {
        wp_enqueue_script('email-logs-js', get_template_directory_uri() . '/assets/js/email-logs.js', array('jquery'), time(), true);
        wp_localize_script('email-logs-js', 'emailLogs', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_email_logs_scripts');
// AJAX handler for getting email content


add_action('wp_ajax_get_email_content', 'get_email_content_callback');

function get_email_content_callback()
{
    gcp_require_admin_ajax();

    if (!current_user_can('administrator')) {
        wp_send_json_error('Unauthorized');
    }

    if (empty($_POST['log_id'])) {
        wp_send_json_error('Log ID required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'email_log';
    $log_id = intval($_POST['log_id']);

    $email = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $log_id
    ));

    if (!$email) {
        wp_send_json_error('Email not found');
    }

    $response = array(
        'sent_at' => date('d-m-Y \a\t g:i A', strtotime($email->sent_date)),
        'to_email' => $email->to_email,
        'subject' => $email->subject,
        'message' => $email->message
    );

    wp_send_json_success($response);
}



// Enhance email logging with username

// 1. Register Custom Post Type for Email Templates
function et_register_email_template_cpt()
{
    $labels = [
        'name'               => 'Email Templates',
        'singular_name'      => 'Email Template',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Email Template',
        'edit_item'          => 'Edit Email Template',
        'new_item'           => 'New Email Template',
        'view_item'          => 'View Email Template',
        'search_items'       => 'Search Email Templates',
        'not_found'          => 'No email templates found',
        'not_found_in_trash' => 'No email templates found in Trash',
        'all_items'          => 'All Email Templates',
        'menu_name'          => 'Email Templates',
    ];

    register_post_type( 'email_template', [
        'labels'              => $labels,
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_admin_bar'   => true,
        'query_var'           => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-email-alt',
        'supports'            => [ 'title', 'editor', 'revisions' ],
    ] );
}
add_action( 'init', 'et_register_email_template_cpt' );

// 2. Add Custom Meta Fields (no ACF)
function et_add_email_template_meta_box()
{
    add_meta_box('et_email_meta', 'Email Settings', 'et_render_email_meta_box', 'email_template', 'normal', 'default');
}
add_action('add_meta_boxes', 'et_add_email_template_meta_box');

function et_render_email_meta_box($post)
{
    wp_nonce_field( 'et_save_email_meta', 'et_email_meta_nonce' );

    $sender_name  = get_post_meta($post->ID, '_et_sender_name', true);
    $sender_email = get_post_meta($post->ID, '_et_sender_email', true);
    $trigger      = get_post_meta($post->ID, '_et_trigger', true);
    $subject      = get_post_meta($post->ID, '_et_subject', true);

    ?>
    <style>
        .et-meta-field {
            margin-bottom: 15px;
        }

        .et-meta-field label {
            font-weight: bold;
            display: block;
        }
    </style>
    <div class="et-meta-field">
        <label>Sender Name:</label>
        <input type="text" name="et_sender_name" value="<?php echo esc_attr($sender_name); ?>" style="width: 100%;">
    </div>
    <div class="et-meta-field">
        <label>Sender Email:</label>
        <input type="email" name="et_sender_email" value="<?php echo esc_attr($sender_email); ?>" style="width: 100%;">
    </div>
    <div class="et-meta-field">
        <label>Email Subject:</label>
        <input type="text" name="et_subject" value="<?php echo esc_attr($subject); ?>" style="width: 100%;">
    </div>
    <div class="et-meta-field">
        <label>Email Trigger:</label>
        <select name="et_trigger" style="width: 100%;">
            <option value="">-- Select Trigger --</option>
            <option value="customer_new_account" <?php selected($trigger, 'customer_new_account'); ?>>User Registration
            </option>
            <option value="customer_processing_order" <?php selected($trigger, 'customer_processing_order'); ?>>Order
                Received</option>
            <option value="customer_completed_order" <?php selected($trigger, 'customer_completed_order'); ?>>Order
                Completed</option>
            <!-- You can expand with more trigger events -->
        </select>
    </div>
    <?php
}

function et_save_email_template_meta($post_id)
{
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! isset( $_POST['et_email_meta_nonce'] ) || ! wp_verify_nonce( $_POST['et_email_meta_nonce'], 'et_save_email_meta' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( get_post_type( $post_id ) !== 'email_template' ) return;

    if ( isset($_POST['et_sender_name']) ) {
        update_post_meta($post_id, '_et_sender_name', sanitize_text_field($_POST['et_sender_name']));
    }

    if ( isset($_POST['et_sender_email']) ) {
        update_post_meta($post_id, '_et_sender_email', sanitize_email($_POST['et_sender_email']));
    }

    if ( isset($_POST['et_trigger']) ) {
        update_post_meta($post_id, '_et_trigger', sanitize_text_field($_POST['et_trigger']));
    }

    if ( isset($_POST['et_subject']) ) {
        update_post_meta($post_id, '_et_subject', sanitize_text_field($_POST['et_subject']));
    }
}
add_action('save_post', 'et_save_email_template_meta');


function et_enqueue_email_settings_scripts()
{
    if (is_page_template('template-email-settings.php')) {
        wp_enqueue_script('jquery');
        wp_enqueue_editor();
        wp_enqueue_style('wp-editor'); // Editor styles
        wp_enqueue_script('et-email-settings', get_template_directory_uri() . '/assets/js/email-settings.js', ['jquery'], null, true);


        wp_localize_script('et-email-settings', 'et_email_settings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('et_ajax_nonce'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'et_enqueue_email_settings_scripts');


add_action('wp_ajax_et_get_email_templates', 'et_get_email_templates');

function et_get_email_templates()
{
    gcp_require_admin_ajax();

    $args = [
        'post_type' => 'email_template',
        'posts_per_page' => -1,
        'post_status' => 'publish', // Fetch only published
    ];
    $query = new WP_Query($args);

    $templates = [];
    while ($query->have_posts()) {
        $query->the_post();
        $templates[] = [
            'id' => get_the_ID(),
            'title' => get_the_title(),
            'sender_name' => get_post_meta(get_the_ID(), '_et_sender_name', true),
            'sender_email' => get_post_meta(get_the_ID(), '_et_sender_email', true),
            'trigger' => get_post_meta(get_the_ID(), '_et_trigger', true),
            'content' => apply_filters('the_content', get_post_field('post_content', get_the_ID())),
        ];
    }
    wp_reset_postdata();

    wp_send_json_success($templates);
}

add_action('wp_ajax_et_save_email_template', 'et_save_email_template');

function et_save_email_template()
{
    gcp_require_admin_ajax();
    check_ajax_referer('et_ajax_nonce', 'nonce');

    $post_id = intval($_POST['email_id']);
    if (get_post_type($post_id) !== 'email_template') {
        wp_send_json_error('Invalid email template');
    }

    wp_update_post([
        'ID' => $post_id,
        'post_content' => wp_kses_post($_POST['content']),
    ]);

    update_post_meta($post_id, '_et_sender_name', sanitize_text_field($_POST['sender_name']));
    update_post_meta($post_id, '_et_sender_email', sanitize_email($_POST['sender_email']));
    update_post_meta($post_id, '_et_trigger', sanitize_text_field($_POST['trigger']));
    update_post_meta($post_id, '_et_subject', sanitize_text_field($_POST['subject']));
    wp_send_json_success('Saved');
}

add_action('wp_ajax_et_get_single_email_template', 'et_get_single_email_template');

function et_get_single_email_template()
{
    gcp_require_admin_ajax();

    $post_id = intval($_POST['email_id']);
    if (get_post_type($post_id) !== 'email_template') {
        wp_send_json_error('Invalid template');
    }

    $template = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'subject' => get_post_meta($post_id, '_et_subject', true),
        'sender_name' => get_post_meta($post_id, '_et_sender_name', true),
        'sender_email' => get_post_meta($post_id, '_et_sender_email', true),
        'trigger' => get_post_meta($post_id, '_et_trigger', true),
        'content' => apply_filters('the_content', get_post_field('post_content', $post_id)),
    ];

    wp_send_json_success($template);
}

include get_template_directory() . '/wc-email-functions.php';
include get_template_directory() . '/inc/recipient-email-functions.php';

//*****************************//
// Home Page Custom Shortcodes //
//*****************************//
include get_template_directory() . '/templates/egiftcard-shortcode.php';
include get_template_directory() . '/templates/egiftcardchoice-shortcode.php';
include get_template_directory() . '/templates/giftoccasion.php';
include get_template_directory() . '/templates/toppicks-product-shortcode.php';
include get_template_directory() . '/templates/trending-product-shortcode.php';


add_action('init', 'maybe_set_email_templates_wc');
function maybe_set_email_templates_wc()
{
    if (false === get_transient('email_templates_wc')) {
        set_email_templates_wc();
    }
}

/**
 * Schedule a reminder email 7 days before a tribe_events post's start date.
 * Called directly after all post meta is saved — NOT via save_post hook,
 * because meta is written after wp_insert_post() so the hook fires too early.
 *
 * @param int    $post_id
 * @param int    $user_id
 * @param string $start_date  Optional. If empty, reads _EventStartDate meta.
 */
function gcp_schedule_reminder_email( $post_id, $user_id, $start_date = '' ) {
    if ( ! $start_date ) {
        $start_date = get_post_meta( $post_id, '_EventStartDate', true );
    }
    if ( ! $start_date || ! $user_id ) return;

    // Calculate timestamp for 7 days before the event start.
    $event_ts      = strtotime( $start_date );
    $remind_ts     = $event_ts - ( 7 * DAY_IN_SECONDS );
    $hook          = 'gcp_send_reminder_email';
    $args          = [ $post_id, (int) $user_id ];

    // Clear any previously scheduled reminder for this event before rescheduling.
    $existing = wp_next_scheduled( $hook, $args );
    if ( $existing ) {
        wp_unschedule_event( $existing, $hook, $args );
    }

    // Only schedule if the remind time is in the future.
    if ( $remind_ts > time() ) {
        wp_schedule_single_event( $remind_ts, $hook, $args );
    }
}

/**
 * Cron callback — sends the reminder email to the user.
 */
add_action( 'gcp_send_reminder_email', 'gcp_handle_reminder_email', 10, 2 );
function gcp_handle_reminder_email( $post_id, $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return;

    $event_title = get_the_title( $post_id );
    $first_name  = $user->first_name ?: $user->display_name;

    $tpl = et_get_template_by_slug( 'event-reminder', [
        'first_name' => $first_name,
        'event_name' => $event_title,
    ] );

    if ( $tpl ) {
        wp_mail( $user->user_email, $tpl['subject'], $tpl['body'], $tpl['headers'] );
    }
}

// Handle test email sending
add_action('wp_ajax_et_send_test_email', 'et_send_test_email');

function et_send_test_email()
{
    gcp_require_admin_ajax();
    check_ajax_referer('et_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $email = sanitize_email($_POST['email']);
    $template_id = intval($_POST['template_id']);
    $sender_email = sanitize_email($_POST['sender_email']);

    if (!is_email($sender_email)) {
        wp_send_json_error('Invalid sender email address');
    }

    if (!is_email($email)) {
        wp_send_json_error('Invalid email address');
    }

    // Get template data
    $template = get_post($template_id);
    if (!$template || $template->post_type !== 'email_template') {
        wp_send_json_error('Invalid template');
    }

    // Prepare email headers
    $sender_name  = sanitize_text_field( get_post_meta($template_id, '_et_sender_name', true) );
    $subject      = get_post_meta($template_id, '_et_subject', true);

    // MED-4: use phpmailer_init hook to set From safely — avoids header injection via raw header string
    $safe_sender_name  = $sender_name;
    $safe_sender_email = $sender_email;
    $set_from = function( $phpmailer ) use ( $safe_sender_name, $safe_sender_email ) {
        $phpmailer->From     = $safe_sender_email;
        $phpmailer->FromName = $safe_sender_name;
    };
    add_action( 'phpmailer_init', $set_from );

    $headers = [ 'Content-Type: text/html' ];

    // Get email content
    $content = apply_filters('the_content', $template->post_content);

    // Send email
    $result = wp_mail( $email, $subject, $content, $headers );

    remove_action( 'phpmailer_init', $set_from );

    if ($result) {
        wp_send_json_success(['message' => 'Email sent successfully']);
    } else {
        wp_send_json_error('Failed to send email');
    }
}

/**
 * Fetch an email_template post by its slug, replace {placeholder} tokens,
 * and return an array ready to pass to wp_mail().
 *
 * @param string $slug       Post slug (post_name) of the email_template.
 * @param array  $vars       Associative array of placeholder => value pairs.
 * @return array|false       ['subject', 'body', 'headers'] or false if not found.
 */

/**
 * Returns the shared email header (logo) + footer (need help + dark social bar)
 * wrapped around $inner_html.
 */
function et_email_wrapper( string $inner_html, string $site_url = '' ): string {
    if ( ! $site_url ) {
        $site_url = untrailingslashit( site_url() );
    }

    $header = '
<style>@import url(\'https://fonts.googleapis.com/css2?family=Ephesis&display=swap\');</style>
<div style="margin:0;padding:0;background-color:#f4f4f4;font-family:Verdana,Geneva,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ffffff;">
    <tr>
      <td align="center">

        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:4px;overflow:hidden;">

          <!-- Logo -->
          <tr>
            <td align="center" style="padding:28px 40px 20px;">
              <img src="' . $site_url . '/wp-content/uploads/giftcardsplus-logo.png" alt="giftcardsplus&#8482;" width="200" style="display:block;max-width:200px;margin:0 auto;" />
            </td>
          </tr>';

    $footer = '
          <tr>
            <td style="background-color:#ED018C;padding:32px 40px;">
              <p style="margin:0 0 6px;font-size:21px;line-height:130%;color:#ffffff;font-family:Verdana,Geneva,sans-serif;font-weight:400;letter-spacing:-2%;">Need a little <span style="font-family:Ephesis,Georgia,serif;font-size:38px;font-weight:400;letter-spacing:-0.02em;line-height:130%;vertical-align:middle;">help?</span></p>
              <p style="margin:0 0 20px;font-size:14px;line-height:145%;color:#ffffff;font-family:Verdana,Geneva,sans-serif;">The <strong>giftcards</strong><em>plus</em>&#8482; Australian based support team is available to help you personalise your experience or answer any questions.</p>
              <a href="' . $site_url . '/contact" style="display:inline-block;background-color:#ffffff;color:#ED018C;font-size:14px;font-family:Verdana,Geneva,sans-serif;font-weight:700;letter-spacing:0;text-transform:uppercase;text-decoration:none;padding:12px 24px;border-radius:12px 0;">CONTACT SUPPORT</a>
            </td>
          </tr>

          <!-- Dark footer -->
          <tr>
            <td style="background-color:#2D2D2D;padding:20px 40px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="vertical-align:middle;">
                    <p style="margin:0 0 4px;font-size:12px;line-height:100%;color:#ffffff;font-family:Verdana,Geneva,sans-serif;">&#169;2026 <strong>giftcards</strong><em>plus</em>&#8482; Pty Ltd. All Rights Reserved.</p>
                    <p style="margin:0;font-size:8px;line-height:100%;">
                      <a href="' . $site_url . '" style="color:#ffffff;text-decoration:underline;font-family:Verdana,Geneva,sans-serif;">giftcardsplus.com.au</a>
                      &nbsp;&nbsp;
                      <a href="' . $site_url . '/terms" style="color:#ffffff;text-decoration:underline;font-family:Verdana,Geneva,sans-serif;">T&amp;C\'s</a>
                    </p>
                  </td>
                  <td align="right" style="vertical-align:middle;white-space:nowrap;">
                    <a href="https://www.linkedin.com/company/giftcardsplus" style="display:inline-block;margin-left:8px;"><img src="' . $site_url . '/wp-content/uploads/icon-linkedin.png" alt="LinkedIn" width="20" height="20" style="display:block;" /></a>
                    <a href="https://www.instagram.com/giftcardsplus" style="display:inline-block;margin-left:8px;"><img src="' . $site_url . '/wp-content/uploads/icon-instagram.png" alt="Instagram" width="20" height="20" style="display:block;" /></a>
                    <a href="https://www.facebook.com/giftcardsplus" style="display:inline-block;margin-left:8px;"><img src="' . $site_url . '/wp-content/uploads/icon-facebook.png" alt="Facebook" width="20" height="20" style="display:block;" /></a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>
</div>';

    return $header . "\n" . $inner_html . "\n" . $footer;
}

function et_get_template_by_slug( string $slug, array $vars = [] ) {
    $post = get_page_by_path( $slug, OBJECT, 'email_template' );
    if ( ! $post ) {
        return false;
    }

    $subject      = get_post_meta( $post->ID, '_et_subject', true );
    $sender_name  = get_post_meta( $post->ID, '_et_sender_name', true );
    $sender_email = get_post_meta( $post->ID, '_et_sender_email', true );
    $body         = $post->post_content;
    // Strip wpautop-injected <p> tags that wrap <tr> blocks when saved via Visual editor
    $body = preg_replace( '/<p[^>]*>\s*(<tr[\s>])/i', '$1', $body );
    $body = preg_replace( '/(<\/tr>)\s*<\/p>/i', '$1', $body );
    $body = preg_replace( '/<p[^>]*>\s*(<td[\s>])/i', '$1', $body );
    $body = preg_replace( '/(<\/td>)\s*<\/p>/i', '$1', $body );
    $body = preg_replace( '/<p[^>]*>\s*(<table[\s>])/i', '$1', $body );
    $body = preg_replace( '/(<\/table>)\s*<\/p>/i', '$1', $body );

    // Fallback subjects if the Email Subject field is left blank in WP admin.
    if ( empty( $subject ) ) {
        $fallbacks = [
            'otp-verification'   => 'Your giftcardsplus login code is here',
            'register-otp'       => 'Verify your giftcardsplus account',
            'forgot-password'    => 'Reset your giftcardsplus password',
            'welcome-user'       => 'Welcome to giftcardsplus!',
            'event-reminder'     => 'You have {event_name} in one week! ✨',
            'plus-offer'         => 'A little treat, just for you 🎁',
        ];
        $subject = $fallbacks[ $slug ] ?? get_bloginfo( 'name' );
    }

    // Always inject {site_url} so image paths in templates don't need hardcoded domains.
    $vars['site_url'] = untrailingslashit( site_url() );

    // Normalise human-readable tokens that admins may type in the subject field.
    $token_aliases = [
        '{Event/reminder Name}' => '{event_name}',
        '{Event Name}'          => '{event_name}',
        '{Reminder Name}'       => '{event_name}',
        '{First Name}'          => '{first_name}',
        '{OTP}'                 => '{otp}',
        '{Reset URL}'           => '{reset_url}',
    ];
    $subject = str_replace( array_keys( $token_aliases ), array_values( $token_aliases ), $subject );

    // Replace {placeholder} tokens in both subject and body.
    foreach ( $vars as $key => $value ) {
        $token   = '{' . $key . '}';
        $subject = str_replace( $token, esc_html( $value ), $subject );
        $body    = str_replace( $token, $value, $body );
    }

    // Wrap body with shared header + footer.
    $body = et_email_wrapper( $body, $vars['site_url'] );

    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    if ( $sender_name && $sender_email ) {
        $headers[] = sprintf( 'From: %s <%s>', $sender_name, $sender_email );
    }

    return [
        'subject' => $subject,
        'body'    => $body,
        'headers' => $headers,
    ];
}


add_action('init', function () {
    global $wp_roles;

    if (!isset($wp_roles)) {
        $wp_roles = wp_roles();
    }

    // 'administrator' => 'J&C Super admin',
    $role_updates = [
        'administrator' => 'GCP Super admin',
        'business_user' => 'Business User',
        'recipients' => 'Contact list user',
        'jc_staff' => 'GCP staff user admin',
    ];

    foreach ($role_updates as $role_slug => $new_name) {
        if (isset($wp_roles->roles[$role_slug])) {
            $wp_roles->roles[$role_slug]['name'] = $new_name;
        }
        if (isset($wp_roles->role_names[$role_slug])) {
            $wp_roles->role_names[$role_slug] = $new_name;
        }
    }
});

// Hook into 'init' to safely create roles
add_action('init', function () {

    // Create External business user viewer role
    if (!get_role('external_business_viewer')) {
        add_role(
            'external_business_viewer', // Role slug
            'External business user viewer', // Display name
            [
                'read' => true,  // Can read content
                'edit_posts' => false, // Cannot edit posts
                'delete_posts' => false, // Cannot delete posts
                'publish_posts' => false, // Cannot publish posts
                'upload_files' => false, // Cannot upload files
            ]
        );
    }

    if (!get_role('external_business_admin')) {
        add_role(
            'external_business_admin', // Role slug
            'External business user admin', // Display name
            [
                'read' => true,  // Can read content
                'edit_posts' => false, // Cannot edit posts
                'delete_posts' => false, // Cannot delete posts
                'publish_posts' => false, // Cannot publish posts
                'upload_files' => false, // Cannot upload files
            ]
        );
    }
    // Create External customer consumer role
    if (!get_role('external_customer_consumer')) {
        add_role(
            'external_customer_consumer', // Role slug
            'External customer consumer', // Display name
            [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
                'upload_files' => false,
            ]
        );
    }
});

// Add this to your PHP code (in the template or functions.php)
add_action('wp_ajax_get_product_images', 'get_product_images_callback');

function get_product_images_callback()
{
    gcp_require_admin_ajax();
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $product = wc_get_product($product_id);

    $response = array(
        'success' => false,
        'data' => array(
            'images' => array(),
            'gallery' => array()
        )
    );

    if ($product) {
        $featured_image_id = $product->get_image_id();
        $gallery_image_ids = $product->get_gallery_image_ids();

        if ($featured_image_id) {
            $response['data']['images']['featured'] = array(
                array(
                    'id' => $featured_image_id,
                    'src' => wp_get_attachment_image_url($featured_image_id, 'full')
                )
            );
        }

        foreach ($gallery_image_ids as $image_id) {
            $response['data']['gallery'][] = array(
                'id' => $image_id,
                'src' => wp_get_attachment_image_url($image_id, 'full')
            );
        }

        $response['success'] = true;
    }

    wp_send_json($response);
}


function allow_all_image_types($mimes)
{
    // Add support for additional image types
    $mimes['svg'] = 'image/svg+xml';
    $mimes['webp'] = 'image/webp';
    $mimes['avif'] = 'image/avif';
    $mimes['heic'] = 'image/heic';
    $mimes['heif'] = 'image/heif';

    return $mimes;
}
add_filter('upload_mimes', 'allow_all_image_types');


add_action('wp_ajax_check_email_exists', 'check_email_exists_callback');

function check_email_exists_callback()
{
    gcp_require_admin_ajax();

    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

    if ( email_exists( $email ) ) {
        wp_send_json( array( 'exists' => true ) );
    } else {
        wp_send_json( array( 'exists' => false ) );
    }
}


add_action('wp_ajax_create_new_user_with_all_details', 'handle_create_new_user_with_all_details');

function handle_create_new_user_with_all_details()
{
    gcp_require_admin_ajax();
    check_ajax_referer('user_admin_nonce', 'nonce');

    $is_business = isset($_POST['is_business_user']) && $_POST['is_business_user'] === 'true';
    $data = $_POST['user_data'];

    // Validate required fields
    $errors = [];
    $required_fields = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'user_type' => 'User Type'
    ];

    foreach ($required_fields as $field => $name) {
        if (empty($data[$field])) {
            $errors[] = $name . ' is required';
        }
    }

    if (!is_email($data['email'])) {
        $errors[] = 'Invalid email address';
    }

    if (email_exists($data['email'])) {
        $errors[] = 'Email already in use';
    }

    // Additional validation for business users
    if ($is_business) {
        $business_required = [
            'business_name' => 'Business Name',
            'business_abn' => 'Business ABN'
        ];
        foreach ($business_required as $field => $name) {
            if (empty($data[$field])) {
                $errors[] = $name . ' is required for business users';
            }
        }
    }

    if (!empty($errors)) {
        wp_send_json_error(['message' => implode("\n", $errors)]);
    }

    // Prepare user data array with all fields
    $userdata = [
        'user_login' => $data['email'],
        'user_email' => $data['email'],
        'user_pass' => wp_generate_password(12, true),
        'first_name' => sanitize_text_field($data['first_name']),
        'last_name' => sanitize_text_field($data['last_name']),
        'role' => sanitize_text_field($data['user_type'])
    ];

    // Insert the user
    $user_id = wp_insert_user($userdata);

    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()]);
    }

    // Save additional user meta
    $user_meta_fields = [
        'nickname_team',
        'mobile',
        'dob',
        'state'
    ];

    foreach ($user_meta_fields as $field) {
        if (!empty($data[$field])) {
            update_user_meta($user_id, $field, sanitize_text_field($data[$field]));
        }
    }

    // Save business fields if needed
    if ($is_business) {
        $business_fields = [
            'business_name',
            'business_website',
            'business_id',
            'billing_details',
            'billing_details_2',
            'approved_billing',
            'business_float_id',
            'business_abn',
            'address_line1',
            'address_line2',
            'suburb',
            'state',
            'country',
            'postcode',
            'business_currency'
        ];

        foreach ($business_fields as $field) {
            $value = isset($data[$field]) ? sanitize_text_field($data[$field]) : '';

            if ($field === 'approved_billing') {
                update_user_meta($user_id, $field, $value === 'yes' ? 'yes' : 'no');
            } else {
                update_user_meta($user_id, $field, $value);
            }
        }
    }

    wp_send_json_success(['message' => 'User created successfully']);
}
//Add new user to the business popup Start

add_action('wp_ajax_search_user_emails', 'search_user_emails_callback');

// add_action('wp_ajax_nopriv_search_user_emails', 'search_user_emails_callback');
// add_action('wp_ajax_search_user_emails', 'search_user_emails_callback');

function search_user_emails_callback()
{
    gcp_require_admin_ajax();
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

    if (!$term) {
        wp_send_json([]);
        wp_die();
    }

    $args = [
        'search' => '*' . esc_attr($term) . '*',
        'search_columns' => ['user_email'],
        'role__in' => ['external_business_admin', 'external_business_viewer'],
        'number' => 10,
    ];

    $role_map = [
        'external_business_admin' => 'External Business Admin',
        'external_business_viewer' => 'External Business Viewer',
    ];

    $user_query = new WP_User_Query($args);
    $users_data = [];

    if (!empty($user_query->results)) {
        foreach ($user_query->results as $user) {
            $roles = $user->roles;
            $display_role = '';
            if (!empty($roles)) {
                foreach ($roles as $role) {
                    if (isset($role_map[$role])) {
                        $display_role = $role_map[$role];
                        break;
                    }
                }
            }

            // ✅ Fetch assigned business user display name
            $assigned_business_id = get_user_meta($user->ID, 'assigned_business_user', true);
            $business_name = '';
            if ($assigned_business_id) {
                $business_user = get_userdata($assigned_business_id);
                if ($business_user) {
                    $business_name = $business_user->display_name;
                }
            }

            $users_data[] = [
                'ID' => $user->ID,
                'user_email' => $user->user_email,
                'first_name' => get_user_meta($user->ID, 'first_name', true),
                'last_name' => get_user_meta($user->ID, 'last_name', true),
                'business_name' => $business_name,
                'role' => $display_role,
                'role_slug' => $roles[0] ?? '',
            ];
            // echo '<pre>';
            // print_r($users_data);
            // echo '</pre>';

        }
    }

    wp_send_json($users_data);
    wp_die();
}



add_action('wp_ajax_add_new_business_user', 'add_new_business_user_callback');

function add_new_business_user_callback()
{
    gcp_require_admin_ajax();
    $email = sanitize_email($_POST['email'] ?? '');
    $first = sanitize_text_field($_POST['first_name'] ?? '');
    $last = sanitize_text_field($_POST['last_name'] ?? '');
    $business = sanitize_text_field($_POST['business_name'] ?? '');
    $role = sanitize_text_field($_POST['role'] ?? '');

    if (empty($email) || empty($first) || empty($last) || empty($business) || empty($role)) {
        wp_send_json_error('All fields are required.');
    }

    if (!is_email($email)) {
        wp_send_json_error('Invalid email address.');
    }

    /*// Find business user by display name
    $matched_business_users = get_users([
        'role' => 'business_user',
        'search' => "*{$business}*",
        'search_columns' => ['display_name']
    ]);

    $business_user_id = null;
    if (!empty($matched_business_users)) {
        $business_user_id = $matched_business_users[0]->ID;
    }*/
    $business_user_id = (int) $business;
    $business_user_name = get_user_meta($business_user_id, 'business_name', true);
    $existing_user = get_user_by('email', $email);

    if ($existing_user) {
        $existing_user_id = $existing_user->ID;

        $assigned_business = get_user_meta($existing_user_id, 'assigned_business_user', true);
        if ($assigned_business && $assigned_business == $business_user_id) {
            wp_send_json_error('This user is already added to this business.');
        }

        if ($role === 'external_business_admin' && $business_user_id) {
            $existing_admins = get_users([
                'role' => 'external_business_admin',
                'meta_key' => 'assigned_business_user',
                'meta_value' => $business_user_id,
            ]);

            // if (!empty($existing_admins)) {
            //     wp_send_json_error('This business already has an external business admin.');
            // }
        }

        $existing_user->set_role($role);
        update_user_meta($existing_user_id, 'business_name', $business_user_name);

        if ($business_user_id) {
            update_user_meta($existing_user_id, 'assigned_business_user', $business_user_id);
        }

        wp_send_json_success([
            'message' => 'Existing user assigned to business.',
            'user_id' => $existing_user_id,
            'business_user_id' => $business_user_id,
            'existing_user' => true
        ]);
    }

    // Check for external admin existence before creating new one
    if ($role === 'external_business_admin' && $business_user_id) {
        $existing_admins = get_users([
            'role' => 'external_business_admin',
            'meta_key' => 'assigned_business_user',
            'meta_value' => $business_user_id,
        ]);

        // if (!empty($existing_admins)) {
        //     wp_send_json_error('This business already has an external business admin.');
        // }
    }

    $password = wp_generate_password();

    $user_id = wp_insert_user([
        'user_login' => $email,
        'user_email' => $email,
        'user_pass' => $password,
        'first_name' => $first,
        'last_name' => $last,
        'role' => $role
    ]);

    if (is_wp_error($user_id)) {
        wp_send_json_error($user_id->get_error_message());
    }

    update_user_meta($user_id, 'business_name', $business_user_name);

    if ($business_user_id) {
        update_user_meta($user_id, 'assigned_business_user', $business_user_id);
    }

    wp_send_json_success([
        'message' => 'New user created and assigned to business.',
        'user_id' => $user_id
    ]);
}

//Add new user to the business popup END

function register_gift_cards_post_type()
{
    // Register Custom Post Type
    $labels = array(
        'name' => _x('Gift Cards', 'Post Type General Name', 'textdomain'),
        'singular_name' => _x('Gift Card', 'Post Type Singular Name', 'textdomain'),
        'menu_name' => __('Gift Cards', 'textdomain'),
        'name_admin_bar' => __('Gift Card', 'textdomain'),
        'add_new_item' => __('Add New Gift Card', 'textdomain'),
        'edit_item' => __('Edit Gift Card', 'textdomain'),
        'new_item' => __('New Gift Card', 'textdomain'),
        'view_item' => __('View Gift Card', 'textdomain'),
        'all_items' => __('All Gift Cards', 'textdomain'),
    );

    $args = array(
        'label' => __('Gift Cards', 'textdomain'),
        'labels' => $labels,
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'public' => true,
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'show_in_menu' => true,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-tickets',
        'has_archive' => true,
        'rewrite' => array('slug' => 'gift-cards'),
        'show_in_rest' => true,
        'hierarchical' => false,
    );

    register_post_type('gift_card', $args);

    // Register Taxonomy
    $taxonomy_labels = array(
        'name' => _x('Categories', 'taxonomy general name', 'textdomain'),
        'singular_name' => _x('Category', 'taxonomy singular name', 'textdomain'),
        'search_items' => __('Search Categories', 'textdomain'),
        'all_items' => __('All Categories', 'textdomain'),
        'edit_item' => __('Edit Category', 'textdomain'),
        'update_item' => __('Update Category', 'textdomain'),
        'add_new_item' => __('Add New Category', 'textdomain'),
        'new_item_name' => __('New Category Name', 'textdomain'),
        'menu_name' => __('Category', 'textdomain'),
    );

    $taxonomy_args = array(
        'hierarchical' => true,
        'labels' => $taxonomy_labels,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'gift-card-category'),
    );

    register_taxonomy('gift_card_category', array('gift_card'), $taxonomy_args);
}
add_action('init', 'register_gift_cards_post_type');


// Remove Attributes tab from product data meta box
add_filter('woocommerce_product_data_tabs', 'remove_product_attributes_tab', 99);
function remove_product_attributes_tab($tabs)
{
    unset($tabs['attribute']);
    return $tabs;
}

// Remove Reviews/Comments meta box from product edit page
add_action('add_meta_boxes', 'remove_product_reviews_meta_box', 99);
function remove_product_reviews_meta_box()
{
    remove_meta_box('commentsdiv', 'product', 'normal');
    remove_meta_box('commentstatusdiv', 'product', 'normal');
    remove_meta_box('commentstatusdiv', 'product', 'side');
}

// Remove "Enable reviews" option from Advanced tab
add_action('admin_footer', 'remove_reviews_option_from_advanced_tab');
function remove_reviews_option_from_advanced_tab()
{
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'product') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Remove the reviews option group from Advanced tab
                $('.options_group.reviews').remove();
            });
        </script>
        <?php
    }
}

//add column for display recipient name

// Add new column for Gift Card Name
add_filter('manage_gift_card_posts_columns', 'add_gift_card_name_column');
function add_gift_card_name_column($columns)
{
    $new_columns = [];

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        if ($key === 'title') {
            $new_columns['recipient_name'] = __('Recipient Name', 'textdomain');
            $new_columns['gift_card_name'] = __('Gift Card Name', 'textdomain');
        }
    }

    return $new_columns;
}

// Show data in the custom columns
add_action('manage_gift_card_posts_custom_column', 'show_custom_gift_card_columns', 10, 2);
function show_custom_gift_card_columns($column, $post_id)
{
    if ($column === 'recipient_name') {
        $recipient = get_post_meta($post_id, '_recipient_name', true);
        echo esc_html($recipient ? $recipient : '—');
    }

    if ($column === 'gift_card_name') {
        $sku = get_post_meta($post_id, '_product_sku', true);
        echo esc_html($sku ? $sku : '—');
    }
}

// Register sortable columns
add_filter('manage_edit-gift_card_sortable_columns', 'make_gift_card_columns_sortable');
function make_gift_card_columns_sortable($columns)
{
    $columns['gift_card_name'] = 'gift_card_name';
    $columns['recipient_name'] = 'recipient_name';
    return $columns;
}

// Handle the custom column sorting logic
add_action('pre_get_posts', 'gift_card_columns_orderby');
function gift_card_columns_orderby($query)
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('post_type') === 'gift_card') {
        $orderby = $query->get('orderby');

        if ($orderby === 'gift_card_name') {
            $query->set('meta_key', '_product_sku');
            $query->set('orderby', 'meta_value');
        }

        if ($orderby === 'recipient_name') {
            $query->set('meta_key', '_recipient_name');
            $query->set('orderby', 'meta_value');
        }
    }
}



add_action('wp_ajax_download_invoice', 'download_invoice_callback');

function download_invoice_callback()
{
    gcp_require_admin_ajax();
    if (!isset($_GET['order_id'])) {
        wp_die('Invalid request.');
    }

    $order_id = intval($_GET['order_id']);
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'download_invoice_' . $order_id ) ) {
        wp_die('Security check failed.');
    }
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_die('Order not found.');
    }

    $invoice_number = $order->get_meta('_invoice_number');

    // Start output buffering
    ob_start();
    ?>
    <html>

    <head>
        <meta charset="utf-8">
        <title>Invoice - <?php echo esc_html($invoice_number); ?></title>
        <style>
            body {
                font-family: sans-serif;
                padding: 20px;
            }

            h2 {
                margin-bottom: 10px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            th,
            td {
                padding: 10px;
                border: 1px solid #ddd;
            }

            th {
                background-color: #f3f3f3;
            }
        </style>
    </head>

    <body>
        <h2>Invoice Number: <?php echo esc_html($invoice_number); ?></h2>
        <p><strong>Order ID:</strong> <?php echo esc_html($order->get_id()); ?></p>
        <p><strong>Date:</strong> <?php echo esc_html($order->get_date_created()->date('Y-m-d H:i')); ?></p>
        <p><strong>Customer:</strong>
            <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></p>
        <p><strong>Email:</strong> <?php echo esc_html($order->get_billing_email()); ?></p>

        <h3>Products:</h3>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Recipient</th>
                    <th>Gift Card Number</th>
                    <th>Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order->get_items() as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item->get_name()); ?></td>
                        <td><?php echo esc_html(wc_get_order_item_meta($item->get_id(), '_recipient_name')); ?></td>
                        <td><?php echo esc_html(wc_get_order_item_meta($item->get_id(), '_gift_card_number_enc')); ?></td>
                        <td><?php echo esc_html($item->get_quantity()); ?></td>
                        <td><?php echo wc_price($item->get_total()); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Totals:</h3>
        <p><strong>Subtotal:</strong> <?php echo wc_price($order->get_subtotal()); ?></p>
        <p><strong>GST:</strong> <?php echo wc_price($order->get_meta('_order_gst')); ?></p>
        <p><strong>Total:</strong> <?php echo wc_price($order->get_total()); ?></p>
    </body>

    </html>
    <?php

    $html = ob_get_clean();

    // Force download as .html file
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="invoice-' . $invoice_number . '.html"');
    echo $html;
    exit;
}
// add_action('wp_ajax_get_order_detail_html', 'load_order_detail_html_callback');

function load_order_detail_html_callback()
{
    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id = intval($_POST['order_id'] ?? 0);
    if (!$order_id) {
        wp_send_json_error('Invalid order ID');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    ob_start();
    ?>
    <h3>Order #<?php echo $order->get_id(); ?></h3>
    <p><strong>Status:</strong> <?php echo ucfirst($order->get_status()); ?></p>
    <p><strong>Date:</strong> <?php echo wc_format_datetime($order->get_date_created()); ?></p>
    <p><strong>Total:</strong> AUD <?php echo $order->get_total(); ?></p>
    <h4>Items:</h4>
    <ul>
        <?php foreach ($order->get_items() as $item): ?>
            <li><?php echo $item->get_name(); ?> × <?php echo $item->get_quantity(); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php
    echo ob_get_clean();
    wp_die();
}

add_action('template_redirect', function () {

    if (is_admin()) {
        return;
    }

    $admin_only_templates = array(
        'gift-card-form-2',
        'bulk-create-category',
        'bulk-create-product',
        'custom-reset-password',
        'template-email-logs',
        'template-email-settings',
        'invoice-display',
        'manual-order',
        'page-offers-list', // added back
        'product-categories',
        'review-products',
        'sms-settings',
        'supplier-login',
        'supplier-registration',
        'users',
        'reports',
        'product-listing',
        'product-listing-section',
        'brands-listing'
    );

    $template = get_page_template_slug();

    if (!$template) {
        return;
    }

    $slug = basename($template, '.php');

    if (
        in_array($slug, $admin_only_templates, true) &&
        !current_user_can('manage_options')
    ) {
        wp_die(
            __('You are not allowed to access this page.', 'textdomain'),
            __('Access Denied', 'textdomain'),
            array('response' => 403)
        );
    }
});

function rename_product_draft_status($statuses)
{
    global $post;
    if (isset($post) && $post->post_type === 'product' && isset($statuses['draft'])) {
        $statuses['draft'] = _x('Awaiting Publishing', 'post');
    }
    return $statuses;
}
add_filter('post_statuses', 'rename_product_draft_status');

function rename_product_status_labels($post_states)
{
    global $post;

    if ('product' !== $post->post_type) {
        return $post_states;
    }

    if ('draft' === $post->post_status) {
        $post_states['draft'] = 'Awaiting Publishing';
    }

    if ('publish' === $post->post_status) {
        $post_states['publish'] = 'Active';
    }

    return $post_states;
}
add_filter('display_post_states', 'rename_product_status_labels');


add_filter('wc_order_statuses', 'rename_woocommerce_order_statuses', 20);
function rename_woocommerce_order_statuses($order_statuses)
{
    $order_statuses['wc-pending'] = 'Draft';
    $order_statuses['wc-processing'] = 'Ordered';
    return $order_statuses;
}

// 1. Register custom statuses
function register_custom_order_statuses()
{
    register_post_status('wc-delivering', [
        'label' => _x('Delivering', 'Order status', 'woocommerce'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Delivering <span class="count">(%s)</span>', 'Delivering <span class="count">(%s)</span>', 'woocommerce'),
    ]);

    register_post_status('wc-delivered', [
        'label' => _x('Delivered', 'Order status', 'woocommerce'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Delivered <span class="count">(%s)</span>', 'Delivered <span class="count">(%s)</span>', 'woocommerce'),
    ]);

    register_post_status('wc-reversed', [
        'label' => _x('Reversed', 'Order status', 'woocommerce'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Reversed <span class="count">(%s)</span>', 'Reversed <span class="count">(%s)</span>', 'woocommerce'),
    ]);
}
add_action('init', 'register_custom_order_statuses');
function add_custom_order_statuses_to_wc($order_statuses)
{
    $new_statuses = array();

    // Insert after processing
    foreach ($order_statuses as $key => $status) {
        $new_statuses[$key] = $status;

        if ('wc-processing' === $key) {
            $new_statuses['wc-delivering'] = 'Delivering';
            $new_statuses['wc-delivered'] = 'Delivered';
            $new_statuses['wc-reversed'] = 'Reversed';
        }
    }

    return $new_statuses;
}
add_filter('wc_order_statuses', 'add_custom_order_statuses_to_wc');
function add_custom_statuses_to_bulk_actions($bulk_actions)
{
    $bulk_actions['mark_delivering'] = 'Change status to Delivering';
    $bulk_actions['mark_delivered'] = 'Change status to Delivered';
    $bulk_actions['mark_reversed'] = 'Change status to Reversed';
    return $bulk_actions;
}
add_filter('bulk_actions-edit-shop_order', 'add_custom_statuses_to_bulk_actions');
function handle_order_custom_bulk_actions($redirect_to, $action, $order_ids)
{
    $map = [
        'mark_delivering' => 'delivering',
        'mark_delivered'  => 'delivered',
        'mark_reversed'   => 'reversed',
    ];
    if (!isset($map[$action])) {
        return $redirect_to;
    }
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status($map[$action]);
        }
    }
    return add_query_arg('bulk_action_status', count($order_ids), $redirect_to);
}
add_filter('handle_bulk_actions-edit-shop_order', 'handle_order_custom_bulk_actions', 10, 3);

// add_action('save_post_product', 'check_onsite_dates_before_publish', 20, 3);
// function check_onsite_dates_before_publish($post_ID, $post, $update) {
//     // Avoid infinite loop
//     remove_action('save_post_product', 'check_onsite_dates_before_publish', 20);

//     // Get current status
//     $current_status = get_post_status($post_ID);

//     // Get meta fields
//     $onsite_from_raw = get_post_meta($post_ID, '_onsite_from', true);
//     $onsite_to_raw   = get_post_meta($post_ID, '_onsite_to', true);

//     if ($onsite_from_raw && $onsite_to_raw) {
//         // Convert meta values to timestamps
//         $onsite_from = strtotime($onsite_from_raw);
//         $onsite_to   = strtotime($onsite_to_raw);

//         // Current time in site's timezone
//         $now = current_time('timestamp');

//         if ($now >= $onsite_from && $now <= $onsite_to) {
//             // In range: publish if not already
//             if ($current_status !== 'publish') {
//                 wp_update_post([
//                     'ID' => $post_ID,
//                     'post_status' => 'publish'
//                 ]);
//             }
//         } else {
//             // Outside range: set to draft if published
//             if ($current_status === 'publish') {
//                 wp_update_post([
//                     'ID' => $post_ID,
//                     'post_status' => 'draft'
//                 ]);
//             }
//         }
//     }

//     // Reattach the hook
//     add_action('save_post_product', 'check_onsite_dates_before_publish', 20, 3);
// }
// add_action('publish_product_on_onsite_date', 'publish_scheduled_product');
// add_action('draft_product_on_onsite_date', 'draft_scheduled_product');
// function publish_scheduled_product($post_id) {
//     if (get_post_type($post_id) === 'product') {
//         wp_update_post([
//             'ID' => $post_id,
//             'post_status' => 'publish'
//         ]);
//         file_put_contents(WP_CONTENT_DIR . '/cron-log.txt', '[' . current_time('mysql') . "] Published Product ID: $post_id\n", FILE_APPEND);
//     }
// }

// function draft_scheduled_product($post_id) {
//     if (get_post_type($post_id) === 'product') {
//         wp_update_post([
//             'ID' => $post_id,
//             'post_status' => 'draft'
//         ]);
//         file_put_contents(WP_CONTENT_DIR . '/cron-log.txt', '[' . current_time('mysql') . "] Drafted Product ID: $post_id\n", FILE_APPEND);
//     }
// }

// //add_action('init', 'schedule_single_cron_for_onsite_products');
// function schedule_single_cron_for_onsite_products() {
//     $args = [
//         'post_type'      => 'product',
//         'post_status'    => ['publish', 'draft'],
//         'posts_per_page' => -1,
//         'meta_query'     => [
//             [
//                 'key'     => '_onsite_from',
//                 'compare' => 'EXISTS',
//             ],
//             [
//                 'key'     => '_onsite_to',
//                 'compare' => 'EXISTS',
//             ]
//         ]
//     ];

//     $query = new WP_Query($args);

//     if ($query->have_posts()) {
//         while ($query->have_posts()) {
//             $query->the_post();
//             $post_id = get_the_ID();

//             $onsite_from = get_post_meta($post_id, '_onsite_from', true);
//             $onsite_to   = get_post_meta($post_id, '_onsite_to', true);

//             $onsite_from_ts = strtotime($onsite_from);
//             $onsite_to_ts   = strtotime($onsite_to);

//             $current_timestamp = current_time('timestamp');

//             $post_status = 'draft';

//             if( !empty($onsite_from) || !empty($onsite_to) ){
//                 pr('--------------------');
//                 pr($post_id);
//                 pr('current_time: '.current_time('mysql'));
//                 pr('current_time ts: '.current_time('timestamp'));
//                 pr($onsite_from);
//                 pr($onsite_from_ts);

//                 pr($onsite_to);
//                 pr($onsite_to_ts);

//                 if ($onsite_from_ts && $onsite_from_ts > $current_timestamp && !wp_next_scheduled('publish_product_on_onsite_date', [$post_id])) {
//                     wp_schedule_single_event($onsite_from_ts, 'publish_product_on_onsite_date', [$post_id]);
//                 }

//                 if ($onsite_to_ts && $onsite_to_ts > time() && !wp_next_scheduled('draft_product_on_onsite_date', [$post_id])) {
//                     wp_schedule_single_event($onsite_to_ts, 'draft_product_on_onsite_date', [$post_id]);
//                 }
//             }

//             // Only schedule if date is in future
//             // if ($onsite_from_ts && $onsite_from_ts > time() && !wp_next_scheduled('publish_product_on_onsite_date', [$post_id])) {
//             //     wp_schedule_single_event($onsite_from_ts, 'publish_product_on_onsite_date', [$post_id]);
//             // }

//             // if ($onsite_to_ts && $onsite_to_ts > time() && !wp_next_scheduled('draft_product_on_onsite_date', [$post_id])) {
//             //     wp_schedule_single_event($onsite_to_ts, 'draft_product_on_onsite_date', [$post_id]);
//             // }
//         }
//         exit;

//         wp_reset_postdata();
//     }
// }

// function pr( $ch = '' ){
//     echo '<pre>';
//     print_r($ch);
//     echo '</pre>';
// }
add_action('save_post_product', 'schedule_cron_on_product_save', 9999, 3);
function schedule_cron_on_product_save($post_id, $post, $update)
{

    remove_action('save_post_product', 'schedule_cron_on_product_save', 9999);

    // Bail early if doing autosave, or if it's a revision
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (wp_is_post_revision($post_id))
        return;

    $always_on =  get_field('always_on', $post_id);
    
    if (isset($always_on) && strtolower($always_on) == 'no') {
        if (!isset($_POST['_onsite_from']) || !isset($_POST['_onsite_to']))
            return;
    }
    // pr($_POST);
    // exit;
    
    
    $regular_price = get_post_meta($post_id, '_regular_price', true);
 
    $variable_range_from = get_field('variable_range_from', $post_id);
    $variable_range_to = get_field('variable_range_to', $post_id);
    $reedem_at_intervals = get_field('_reedem_at_intervals', $post_id);
    $denomination_type = get_field('denomination_type', $post_id);
    $_quantity_per_transaction = get_field('_quantity_per_transaction', $post_id);
    $_total_value_per_transaction = get_field('_total_value_per_transaction', $post_id);
    $add_transaction_limit_checkbox = get_field('add_transaction_limit_checkbox', $post_id);
    $discounted_price_checkbox = get_field('discounted_price_checkbox', $post_id);
     


    // echo 'Data is : ';

    // echo '<pre>'; print_r($post_id); echo '</pre>';
    // echo '<pre>'; print_r($_POST); echo '</pre>';
    // echo '<pre>'; print_r($regular_price); echo '</pre>';
    // echo '<pre>'; print_r($variable_range_from); echo '</pre>';
    // echo '<pre>'; print_r($variable_range_to); echo '</pre>';
    // echo '<pre>'; print_r($reedem_at_intervals); echo '</pre>';
    // echo '<pre>'; print_r($denomination_type); echo '</pre>';
    // echo '<pre>'; print_r($_quantity_per_transaction); echo '</pre>';
    // echo '<pre>'; print_r($_total_value_per_transaction); echo '</pre>';
    // echo '<pre>'; print_r($add_transaction_limit_checkbox); echo '</pre>';
    // echo '<pre>'; print_r($discounted_price_checkbox); echo '</pre>';
    // echo '<pre>'; print_r($_POST['_onsite_to']); echo '</pre>';
    // echo '<pre>'; print_r($_POST['_onsite_from']); echo '</pre>';
    // echo '<pre>'; print_r($_POST['_regular_price']); echo '</pre>';
    // echo '<pre>'; print_r($_POST['_discount_valid_from']); echo '</pre>';
    // echo '<pre>'; print_r($_POST['_discount_valid_to']); echo '</pre>';
    // echo '<pre>'; print_r($_POST['_sale_price']); echo '</pre>';
    // exit;

    //Validation for the denomination types products
    if (strtolower($denomination_type) == 'fixed') {
        if ($regular_price == '' || empty($regular_price)) {
            wp_die(
                '<strong>Error:</strong> Please add Regular price for this product.',
                'Invalid Value',
                array(
                    'back_link' => true,
                )
            );
        }
    } else if (strtolower($denomination_type) == 'variable') {
        // echo 'variable';
        // exit;
        if (empty($variable_range_from) && empty($variable_range_to) && empty($reedem_at_intervals)) {
            wp_die(
                '<strong>Error:</strong> Please add Variable Range From, Variable Range To, and Redeem At Intervals properly.',
                'Invalid Value',
                array(
                    'back_link' => true,
                )
            );
        }
        $errors = [];

        if (empty($variable_range_from)) {
            $errors[] = 'Please add Variable Range From properly.';
        }
        if (empty($variable_range_to)) {
            $errors[] = 'Please add Variable Range To properly.';
        }
        if (empty($reedem_at_intervals)) {
            $errors[] = 'Please add Redeem At Intervals properly.';
        }

        if (!empty($errors)) {
            // wp_die(implode('<br>', $errors));
            wp_die(
                '<strong>Error:</br></strong> ' . implode('<br>', $errors),
                'Invalid Value',
                array(
                    'back_link' => true,
                )
            );
        }
    }
    $onsite_from = $_POST['_onsite_from'];
    $onsite_to = $_POST['_onsite_to'];

    $onsite_from_ts = strtotime($onsite_from);
    $onsite_to_ts = strtotime($onsite_to);


    if (strtolower($always_on) == 'no') {

        if (empty($onsite_from_ts) || empty($onsite_to_ts)) {
            wp_die(
                '<strong>Error:</strong> Please select "Onsite From" and "Onsite To" dates.',
                'Invalid Date Range',
                array(
                    'back_link' => true,
                )
            );

        }
        // echo '<pre>';
        // print_r($_POST);
        // print_r($onsite_from);
        // print_r($onsite_to);
        // echo '</pre>';
        // exit;
        if ($onsite_from_ts && $onsite_to_ts && $onsite_from_ts >= $onsite_to_ts) {
            // ❌ Stop execution and show notice
            wp_die(
                '<strong>Error:</strong> "Onsite From" date must be earlier than "Onsite To" date.',
                'Invalid Date Range',
                array(
                    'back_link' => true,
                )
            );
        }
    }


    // pr($_POST);
    // exit;

    //Add the Validation for the Transaction limit checkbox
    if (strtolower($add_transaction_limit_checkbox) == 'yes') {
        if (empty($_quantity_per_transaction) && empty($_total_value_per_transaction)) {
            // pr($_quantity_per_transaction);
            // pr($_total_value_per_transaction);
            // exit;
            wp_die(
                '<strong>Error:</strong> Please add Quantity Per transaction and Total Value Per transaction properly.',
                'Invalid Value',
                array(
                    'back_link' => true,
                )
            );
        } else if (empty($_total_value_per_transaction)) {
            wp_die(
                '<strong>Error:</strong> Please add Total Value Per transaction properly.',
                'Invalid Value',
                array(
                    'back_link' => true,
                )
            );
        } else if (empty($_quantity_per_transaction)) {
            wp_die(
                '<strong>Error:</strong> Please add Quantity Per transaction properly.',
                'Invalid Value',
                array(
                    'back_link' => true,
                )
            );
        }
    }
    $discounted_from = $_POST['_discount_valid_from'];
    $discounted_to = $_POST['_discount_valid_to'];
    // $sale_price = $_POST['_sale_price'];

    $discounted_from_ts = strtotime($discounted_from);
    $discounted_to_ts = strtotime($discounted_to);




    // 🚫 If Not setted the sale price for the product and if there is discounted is on, redirect with error
    if (strtolower($discounted_price_checkbox) == 'yes') {
        // pr('discounted_from_ts: '.$discounted_from);
        // pr('discounted_to_ts: '.$discounted_to);
        // pr('Value of Sale Price: '.strtolower($_POST['_sale_price']));
        // exit;

        if (empty($discounted_from_ts) || empty($discounted_to_ts)) {
            // echo '<pre>';
            // print_r($discounted_from_ts);
            // // print_r($discounted_to_ts);
            // echo '</pre>';
            // exit;
            // ❌ Stop execution and show notice
            wp_die(
                '<strong>Error:</strong> Please add Discounted dates properly.',
                'Invalid Date',
                array(
                    'back_link' => true,
                )
            );
        } else if ($discounted_from_ts && $discounted_to_ts && $discounted_from_ts >= $discounted_to_ts) {
            wp_die(
                '<strong>Error:</strong> "Discounted From" date must be earlier than "Discounted To" date.',
                'Invalid Date Range',
                array(
                    'back_link' => true,
                )
            );
        } else if (isset($sale_price) && $sale_price === '') {
            wp_die(
                '<strong>Error:</strong> Please Add Discounted Price for this product.',
                'Add Sale Price',
                array(
                    'back_link' => true,
                )
            );
        }
    }


    $current_timestamp = current_time('timestamp');

    $post_status = 'draft';

    $product = wc_get_product($post_id);

    if ($product && $product->get_stock_status() === 'outofstock') {
        remove_action('save_post_product', 'schedule_cron_on_product_save', 9999);

        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'wc-deactivated',
        ]);
    }

    if ($post_status !== 'wc-deactivated') {

        // echo 'inside this';
        // exit;

        if (!empty($onsite_from) || !empty($onsite_to)) {

            if ($onsite_from_ts <= $current_timestamp && $onsite_to_ts >= $current_timestamp) {
                $post_status = 'publish';
            }

            if ($onsite_from_ts > $current_timestamp) { //29-08 > 28-08
                $post_status = 'draft';
            }

            if ($onsite_to_ts < $current_timestamp) { //08-08 < 28 aug
                $post_status = 'wc-deactivated';
            }


            $post_args = array(
                'ID' => $post_id,
                'post_status' => $post_status,
            );
            wp_update_post($post_args);

            // Scheduled code for Activate - Publish
            if ($onsite_from_ts > $current_timestamp) {
                clear_schedule_event('activate_product_on_onsite', $post_id);
                wp_schedule_single_event($onsite_from_ts, 'activate_product_on_onsite', array($post_id));
            }

            // Scheduled code for De-Activate - Draft
            if ($onsite_to_ts > $current_timestamp) {
                clear_schedule_event('deactivate_product_on_onsite', $post_id);
                wp_schedule_single_event($onsite_to_ts, 'deactivate_product_on_onsite', array($post_id));
            }
        }
    }


    $discount_valid_from = $_POST['_discount_valid_from'];
    $discount_valid_to = $_POST['_discount_valid_to'];

    $timezone = new DateTimeZone(wc_timezone_string()); // Store timezone
    $dt_from = new DateTime($discount_valid_from, $timezone);
    $dt_to = new DateTime($discount_valid_to, $timezone);
    $dt_from->setTimezone(new DateTimeZone('UTC'));
    $dt_to->setTimezone(new DateTimeZone('UTC'));
    $discount_valid_from_ts = $dt_from->getTimestamp();
    $discount_valid_to_ts = $dt_to->getTimestamp();

    $sale_price = get_field('discounted_price', $post_id);

    if (isset($sale_price) && !empty($sale_price)) {
        //pr('1');
        if (!empty($discount_valid_from) || !empty($discount_valid_to)) {
            //pr('2');
            if ($discount_valid_from_ts <= current_time('timestamp') && $discount_valid_to_ts > current_time('timestamp')) {
                //pr('3');
                update_field('discounted_price_checkbox', 'Yes', $post_id);
                update_post_meta($post_id, '_sale_price', $sale_price);
                update_post_meta($post_id, '_sale_price_dates_from', $discount_valid_from_ts);
                update_post_meta($post_id, '_sale_price_dates_to', $discount_valid_to_ts);
            } else if ($discount_valid_from_ts > current_time('timestamp')) {
                //pr('4');
                update_field('discounted_price_checkbox', 'No', $post_id);
                delete_post_meta($post_id, '_sale_price');
                delete_post_meta($post_id, '_sale_price_dates_from');
                delete_post_meta($post_id, '_sale_price_dates_to');

                $regular_price = get_post_meta($post_id, '_regular_price', true);
                update_post_meta($post_id, '_price', $regular_price);

                clear_schedule_event('activate_product_on_SALE', $post_id);
                wp_schedule_single_event($discount_valid_from_ts, 'activate_product_on_SALE', array($post_id));
            }
            //pr('5');

            if ($discount_valid_to_ts <= current_time('timestamp')) {
                update_field('discounted_price_checkbox', 'No', $post_id);
                delete_post_meta($post_id, '_sale_price');
                delete_post_meta($post_id, '_sale_price_dates_from');
                delete_post_meta($post_id, '_sale_price_dates_to');

                $regular_price = get_post_meta($post_id, '_regular_price', true);
                update_post_meta($post_id, '_price', $regular_price);
            } else if ($discount_valid_to_ts > current_time('timestamp')) {
                clear_schedule_event('deactivate_product_on_SALE', $post_id);
                wp_schedule_single_event($discount_valid_to_ts, 'deactivate_product_on_SALE', array($post_id));
            }
        }
    } else {
        update_field('discounted_price_checkbox', 'No', $post_id);
        delete_post_meta($post_id, '_sale_price');
        delete_post_meta($post_id, '_sale_price_dates_from');
        delete_post_meta($post_id, '_sale_price_dates_to');
    }
  
}

//Discounted checkox set as no if discounted to date are matched
// add_action('disable_discount_field', function ($post_id) {
//     if (function_exists('update_field')) {
//         update_field('field_67f3a79417f64', 'No', $post_id); 
//     } else {
//         update_post_meta($post_id, 'field_67f3a79417f64', 'No');
//     }
// });

add_action('deactivate_product_on_onsite', 'handle_deactivate_product_on_onsite');
function handle_deactivate_product_on_onsite($post_id)
{
    // Perform your action (e.g., update status)
    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => 'wc-deactivated', // Example action
    ));
    // file_put_contents(WP_CONTENT_DIR . '/cron-log.txt', "[" . current_time('mysql') . "] Triggered handler for draft: $post_id\n", FILE_APPEND);

    // clear_schedule_event('deactivate_product_on_onsite', $post_id);
    clear_schedule_event($post_id, 'activate_product_on_onsite');

}

add_action('activate_product_on_onsite', 'handle_activate_product_on_onsite');
function handle_activate_product_on_onsite($post_id)
{
    // Perform your action (e.g., update status)
    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => 'publish', // Example action
    ));
    // file_put_contents(WP_CONTENT_DIR . '/cron-log.txt', "[" . current_time('mysql') . "] Triggered handler for publish: $post_id\n", FILE_APPEND);

    // clear_schedule_event('activate_product_on_onsite', $post_id);
    clear_schedule_event($post_id, 'activate_product_on_onsite');

}

add_action('deactivate_product_on_SALE', 'handle_deactivate_product_on_SALE');
function handle_deactivate_product_on_SALE($post_id)
{
    update_field('discounted_price_checkbox', 'No', $post_id);
    delete_post_meta($post_id, '_sale_price');
    delete_post_meta($post_id, '_sale_price_dates_from');
    delete_post_meta($post_id, '_sale_price_dates_to');
    // file_put_contents(WP_CONTENT_DIR . '/cron-log.txt', "[" . current_time('mysql') . "] Triggered handler for De-activate ON SALE: $post_id\n", FILE_APPEND);

    // clear_schedule_event('deactivate_product_on_onsite', $post_id);
    clear_schedule_event($post_id, 'activate_product_on_SALE');

}

add_action('activate_product_on_SALE', 'handle_activate_product_on_SALE');
function handle_activate_product_on_SALE($post_id)
{
    update_field('discounted_price_checkbox', 'Yes', $post_id);

    $discounted_price = get_field('discounted_price', $post_id);
    $discount_valid_from = get_post_meta($post_id, '_discount_valid_from', true);
    $discount_valid_to = get_post_meta($post_id, '_discount_valid_to', true);

    $discount_valid_from_ts = strtotime($discount_valid_from);
    $discount_valid_to_ts = strtotime($discount_valid_to);

    update_post_meta($post_id, '_sale_price', $discounted_price);
    update_post_meta($post_id, '_sale_price_dates_from', $discount_valid_from_ts);
    update_post_meta($post_id, '_sale_price_dates_to', $discount_valid_to_ts);
    // file_put_contents(WP_CONTENT_DIR . '/cron-log.txt', "[" . current_time('mysql') . "] Triggered handler for Activate ON SALE: $post_id\n", FILE_APPEND);

    // clear_schedule_event('activate_product_on_onsite', $post_id);
    clear_schedule_event($post_id, 'activate_product_on_SALE');

}

function clear_schedule_event($post_id, $event = '')
{
    if (!empty($event) && $post_id > 0) {
        wp_clear_scheduled_hook($event, array($post_id));
    }
}

add_action('admin_notices', 'show_onsite_date_error_notice');
function show_onsite_date_error_notice()
{
    if (!isset($_GET['onsite_date_error']))
        return;

    echo '<div class="notice notice-error is-dismissible">
        <p><strong>Error:</strong> "Onsite From" date must be earlier than "Onsite To" date. Post update was cancelled.</p>
    </div>';
}


//Code for the log transaction in the table in database.
function log_float_transaction($user_id, $changed_amount = 0, $balance_type = '', $reason = '', $order_id = null, $invoice = '', $reference = '', $status = 'Pending', $balance_meta_key = 'float_balance')
{
    global $wpdb;
    $table = $wpdb->prefix . 'user_float_transactions';
    $user_id = (int) $user_id;
    $changed_amount = (float) $changed_amount;
    $previous_balance = (float) get_user_meta($user_id, $balance_meta_key, true);
    $new_balance = $previous_balance + $changed_amount;
    // $balance_type = ($changed_amount >= 0) ? 'credited' : 'debited';
    $balance_type = !empty($balance_type) ? $balance_type : (($changed_amount >= 0) ? 'credited' : 'debited');
    $updated_by = (int) get_current_user_id();


    // truncate strings to match DB varchar lengths
    $reason = substr(sanitize_text_field($reason), 0, 50);
    $invoice = substr(sanitize_text_field($invoice), 0, 20);
    $reference = substr(sanitize_text_field($reference), 0, 20);
    $status = substr(sanitize_text_field($status), 0, 20);
    // $order_val = is_null($order_id) || $order_id === '' ? '' : (int) $order_id;
    $order_val = !empty($order_id) ? $order_id : '';



    // $previous_balance = (float) get_user_meta($user_id, 'float_balance', true);
    // $new_balance = $previous_balance + $changed_amount;
    // $balance_type = $changed_amount >= 0 ? 'credited' : 'debited';
    // $updated_by = get_current_user_id();

    $data = [
        'user_id' => $user_id,
        'previous_balance' => $previous_balance,
        'changed_amount' => $changed_amount,
        'new_balance' => $new_balance,
        'balance_type' => $balance_type,
        'reason' => $reason,
        'updated_by' => $updated_by,
        'created_at' => current_time('mysql'),
        'order' => $order_val,
        'invoice' => $invoice,
        'reference' => $reference,
        'status' => $status,
    ];

    $format = [
        '%d',
        '%f',
        '%f',
        '%f',
        '%s',
        '%s',
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
    ];

    $inserted = $wpdb->insert($table, $data, $format);

    if ($inserted === false) {
        return false;
    }

    // update the relevant balance meta only if we successfully inserted (optionally)
    if ($inserted !== false) {
        update_user_meta($user_id, $balance_meta_key, $new_balance);

        // Float top up notifications only apply to Instant payment/Float businesses'
        // real float_balance (per spec) — not to Client Billing's prepaid_limit.
        if ($balance_meta_key === 'float_balance') {
            gcp_maybe_send_float_top_up_notification($user_id, $previous_balance, $new_balance);
        }
    }

    return $inserted;
}

// Sends an admin notification email the moment float_balance drops to or below the
// business's configured "Float top up notification" threshold. Only fires once per
// dip below the threshold — resets once the balance is topped back above it — so a
// business sitting under the threshold doesn't get emailed on every subsequent order.
function gcp_maybe_send_float_top_up_notification($user_id, $previous_balance, $new_balance)
{
    $threshold = (float) (get_user_meta($user_id, 'float_notification', true) ?: 0);
    if ($threshold <= 0) {
        return;
    }

    $already_notified = get_user_meta($user_id, '_float_notification_sent', true) === '1';

    if ($new_balance > $threshold) {
        if ($already_notified) {
            delete_user_meta($user_id, '_float_notification_sent');
        }
        return;
    }

    if ($already_notified) {
        return;
    }

    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return;
    }

    $business_name = get_user_meta($user_id, 'business_name', true) ?: $user->display_name;
    $to = $user->user_email;
    $subject = sprintf('Float balance top up required — %s', $business_name);
    $message = sprintf(
        "Hi,\n\nThe float balance for %s has reached $%.2f, at or below your notification threshold of $%.2f.\n\nPlease top up your float balance to avoid interruptions to placing orders.\n\nThe Gift Cards Plus Team",
        $business_name,
        $new_balance,
        $threshold
    );

    wp_mail($to, $subject, $message);
    update_user_meta($user_id, '_float_notification_sent', '1');
}



// Removed: this duplicated save_business_fields(), which is also hooked to
// edit_user_profile_update and already logs float_balance changes correctly (as a
// delta). This function passed the absolute new balance as if it were a delta and
// in the wrong parameter slot, silently double-applying/corrupting float_balance
// on every admin profile save.


add_action('wp_ajax_save_float_billing', 'save_float_billing');
// PT-3.1: removed wp_ajax_nopriv hook — admin-only write operation.

function save_float_billing()
{
    $user_id = intval($_POST['user_id']);
    $payment_limit = $_POST['payment_limit'];
    $float_top_up = $_POST['float_top_up'];
    $approved_billing = $_POST['approved_billing'];
    // echo'<pre>';
    // print_r($approved_billing);
    // echo'</pre>';

    if (!$user_id || !current_user_can('edit_user', $user_id)) {
        wp_send_json_error('Unauthorized');
    }

    $previous_limit = (float) get_user_meta($user_id, 'prepaid_limit', true);

    // echo '<pre>';
    // print_r($balance);
    // print_r($user_id);
    // echo '</pre>';
    update_user_meta($user_id, 'prepaid_limit', $payment_limit);
    update_user_meta($user_id, 'float_notification', $float_top_up);
    update_user_meta($user_id, 'approved_billing', $approved_billing === 'yes' ? 'yes' : 'no');

    $approved_for_billing = get_user_meta($user_id, 'approved_billing', true);
    $prepaid_limit = (float) get_user_meta($user_id, 'prepaid_limit', true) ?: 0;

    // prepaid_limit is itself the live balance (Client Billing: "Balance = Prepaid Limit";
    // Instant/Float: the per-transaction cap) — no further monthly-usage adjustment needed.
    $balance = $prepaid_limit > -1 ? $prepaid_limit : 0;

    $changed_amount = $payment_limit - $previous_limit;
    // echo '<pre>';
    // echo 'payment_limit';
    // print_r($payment_limit);
    // echo 'previous_limit';
    // print_r($previous_limit);
    // echo 'changed_amount';
    // print_r($changed_amount);
    // echo '</pre>';

    if ($changed_amount != 0) {
        $balance_type = ($changed_amount >= 0) ? 'credited' : 'debited';
        $reason = 'Prepaid limit updated by admin';
        $order_id = null;
        $invoice = '';
        $reference = 'Float Billing Settings';
        $status = 'Completed';

        // This updates prepaid_limit, which is the business's live balance for Client
        // Billing accounts — log against that meta key, not float_balance.
        $inserted = log_float_transaction(
            $user_id,
            $changed_amount,
            $balance_type,
            $reason,
            $order_id,
            $invoice,
            $reference,
            $status,
            'prepaid_limit'
        );

        // If insertion failed, return an error in AJAX (optional)
        if ($inserted === false) {
            wp_send_json_error('Failed to log float transaction. Check debug log for details.');
        }
    }
    wp_send_json_success([
        'message' => 'Saved',
        'balance' => $balance,
        'approved_billing' => $approved_for_billing
    ]);
}

add_action('wp_ajax_get_contact_list_users_by_business', 'get_contact_list_users_by_business_callback');

function get_contact_list_users_by_business_callback()
{
    gcp_require_admin_ajax();
    $business_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (!$business_user_id) {
        wp_send_json_error(['message' => 'Invalid business user ID']);
    }
    // echo '<pre>';
    // print_r($business_user_id);
    // echo '</pre>';

    $args = [
        'meta_query' => [
            [
                'key' => 'assigned_business_user',
                'value' => $business_user_id,
                'compare' => '='
            ]
        ]
    ];

    $users = get_users($args);
    $data = [];


    foreach ($users as $user) {
        $business_user_id = get_user_meta($user->ID, 'assigned_business_user', true);
        $business_user = get_user_by('ID', $business_user_id);
        $business_name = $business_user ? $business_user->display_name : '';
        $mobile = get_user_meta($user->ID, 'mobile', true);

        $data[] = [
            'ID' => $user->ID,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'surname' => get_user_meta($user->ID, 'last_name', true),
            'nickname' => $user->nickname,
            'email' => $user->user_email,
            'mobile' => !empty($mobile) ? $mobile : '-',
            'business' => $business_name ?? '-',
            'dob' => !empty(get_user_meta($user->ID, 'dob', true)) ? get_user_meta($user->ID, 'dob', true) : '-',
        ];
    }

    wp_send_json_success($data);
}

add_action('wp_ajax_get_admin_reminders_section', 'handle_get_admin_reminders_section');

// Renders the admin-side "Contact List & Events" reminders section for the
// business user currently being viewed on users.php, reusing the same
// rendering logic as the customer-facing My Reminders page.
function handle_get_admin_reminders_section()
{
    gcp_require_admin_ajax();
    check_ajax_referer('user_admin_nonce', 'nonce');

    $business_user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    if (!$business_user_id || !get_userdata($business_user_id)) {
        wp_send_json_error(['message' => 'Invalid business user ID']);
    }

    $category_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
    $month = isset($_POST['month']) ? absint($_POST['month']) : null;
    $year = isset($_POST['year']) ? absint($_POST['year']) : null;

    if (!function_exists('render_admin_reminders_section_html')) {
        wp_send_json_error(['message' => 'Reminders section renderer not found.']);
    }

    $html = render_admin_reminders_section_html($business_user_id, $category_filter, $month, $year);

    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_admin_load_more_reminders_events', 'handle_admin_load_more_reminders_events');

// Paginated "Load More" variant of handle_get_admin_reminders_section() for the
// admin Contact List & Events tab — mirrors handle_load_more_reminders_events()
// but operates on the business user being viewed rather than the current user.
function handle_admin_load_more_reminders_events()
{
    gcp_require_admin_ajax();
    check_ajax_referer('user_admin_nonce', 'nonce');

    $business_user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    if (!$business_user_id || !get_userdata($business_user_id)) {
        wp_send_json_error(['message' => 'Invalid business user ID']);
    }

    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 4;
    $category_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';

    try {
        $today = date('Y-m-d');
        $far_future_date = date('Y-m-d', strtotime('+10 years'));

        if (!function_exists('get_user_reminders_events') || !function_exists('render_admin_occasion_card_html')) {
            wp_send_json_error(['message' => 'Function not found. Please refresh the page.']);
        }

        $occasions_events = get_user_reminders_events($business_user_id, $today, $far_future_date, $category_filter);
        if (!is_array($occasions_events)) {
            $occasions_events = [];
        }

        $occasions_events_display = [];
        foreach ($occasions_events as $event) {
            $event_date_utc = get_post_meta($event->ID, '_EventStartDateUTC', true);
            if (!$event_date_utc) {
                $event_date_utc = get_post_meta($event->ID, '_EventStartDate', true);
            }
            if (!$event_date_utc) {
                continue;
            }

            $days_until = get_days_until_event($event_date_utc);
            if ($days_until < 0) {
                continue;
            }
            $occasions_events_display[] = $event;
        }

        $total_events = count($occasions_events_display);
        $offset = ($page - 1) * $per_page;
        $events_to_display = array_slice($occasions_events_display, $offset, $per_page);
        $has_more = ($offset + $per_page) < $total_events;

        ob_start();
        foreach ($events_to_display as $event) {
            echo render_admin_occasion_card_html($event);
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'has_more' => $has_more,
            'total' => $total_events,
            'loaded' => $offset + count($events_to_display)
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'An error occurred. Please try again.']);
    } catch (Error $e) {
        wp_send_json_error(['message' => 'A fatal error occurred. Please try again.']);
    }
}

add_action('wp_ajax_export_contact_user_events', 'handle_contact_user_events_export');

// Exports the Contact List & Events (address book) table for a single business as
// a CSV, streamed directly rather than built in the browser — matches the same
// data/columns shown in get_contact_list_users_by_business_callback() above.
function handle_contact_user_events_export()
{
    gcp_require_admin_ajax();
    check_ajax_referer('user_admin_nonce', 'nonce');

    $business_user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    if (!$business_user_id || !get_userdata($business_user_id)) {
        wp_die('Invalid business user ID');
    }

    $users = get_users([
        'meta_query' => [
            [
                'key'     => 'assigned_business_user',
                'value'   => $business_user_id,
                'compare' => '=',
            ],
        ],
    ]);

    $rows = [];

    foreach ($users as $user) {
        $mobile = get_user_meta($user->ID, 'mobile', true);
        $dob    = get_user_meta($user->ID, 'dob', true);
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $surname    = get_user_meta($user->ID, 'last_name', true);

        // Apply the same search filter as the on-screen table (name, nickname, email, mobile).
        if ($search !== '') {
            $haystacks = [$first_name, $surname, $user->nickname, $user->user_email, $mobile];
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
            'id'         => $user->ID,
            'first_name' => $first_name,
            'surname'    => $surname,
            'nickname'   => $user->nickname,
            'email'      => $user->user_email,
            'mobile'     => $mobile !== '' ? $mobile : '-',
            'dob'        => $dob !== '' ? $dob : '-',
        ];
    }

    // Discard any stray output already buffered by earlier hooks in this request
    // (notices, whitespace, etc.) so nothing precedes the CSV content itself —
    // otherwise it lands as a blank/garbled first row before the header row.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contact-list-events-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'w');

    // Guard against formula injection (e.g. a name saved as "=cmd(...)") being
    // auto-executed when the file is opened in Excel/Sheets. Only "=" and "@"
    // are guarded — "+" and "-" are excluded because they're legitimate leading
    // characters for this data (e.g. "+61411111112" phone numbers).
    $csv_safe = function ($value) {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '@'], true)) {
            $value = "'" . $value;
        }
        return $value;
    };

    fputcsv($output, ['User ID', 'First Name', 'Surname', 'Nickname', 'Email', 'Mobile', 'Date of Birth']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $csv_safe($row['id']),
            $csv_safe($row['first_name']),
            $csv_safe($row['surname']),
            $csv_safe($row['nickname']),
            $csv_safe($row['email']),
            $csv_safe($row['mobile']),
            $csv_safe($row['dob']),
        ]);
    }

    fclose($output);
    exit;
}

add_action('save_post_product', 'schedule_discount_events', 10, 3);
function schedule_discount_events($post_id, $post, $update)
{
    // Avoid infinite loops
    remove_action('save_post_product', 'log_discount_debug_info', 10);

    // Bail on autosave or revision
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (wp_is_post_revision($post_id))
        return;

    // Get posted discount dates
    $valid_from = $_POST['_discount_valid_from'] ?? '';
    $valid_to = $_POST['_discount_valid_to'] ?? '';

    // Convert to timestamps
    $from_ts = strtotime($valid_from);
    $to_ts = strtotime($valid_to);
    $now_ts = current_time('timestamp');

    // Log to separate product file
    $upload_dir = WP_CONTENT_DIR . '/discount_logs';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = $upload_dir . "/discount_product_{$post_id}_" . date('Ymd_His') . ".txt";

    // Prepare debug content
    $content = "Product ID: {$post_id}\n";
    $content .= "Valid From: {$valid_from} ({$from_ts})\n";
    $content .= "Valid To: {$valid_to} ({$to_ts})\n";
    $content .= "Current Time: " . date('Y-m-d H:i:s', $now_ts) . " ({$now_ts})\n";

    // Write to product-specific file
    // file_put_contents($filename, $content);
}

add_action('custom_discount_start_event', 'handle_discount_start_event');
function handle_discount_start_event($product_id)
{
    wp_update_post([
        'ID' => $product_id,
        'post_status' => 'publish',
    ]);

    $log_dir = WP_CONTENT_DIR . '/discount_logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $filename = $log_dir . "/discount_product_{$product_id}_start_" . date('Ymd_His') . ".txt";
    $content = "Discount starts for the Product ({$product_id})";
    // file_put_contents($filename, $content);

    clear_schedule_event_for_discount('custom_discount_start_event', $product_id);
}

add_action('custom_discount_end_event', 'handle_discount_end_event');
function handle_discount_end_event($product_id)
{
    wp_update_post([
        'ID' => $product_id,
        'post_status' => 'draft',
    ]);

    $log_dir = WP_CONTENT_DIR . '/discount_logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    // Product-specific log
    $filename = $log_dir . "/discount_product_{$product_id}_end_" . date('Ymd_His') . ".txt";
    $content = "Discount ended for the Product ({$product_id})";
    // file_put_contents($filename, $content);

    // Global log file in wp-content folder
    $global_log_file = WP_CONTENT_DIR . '/Discounted_price.txt';
    $global_log_entry = "[" . current_time('mysql') . "] Triggered handler for draft: $product_id\n";
    file_put_contents($global_log_file, $global_log_entry, FILE_APPEND);

    clear_schedule_event_for_discount('custom_discount_end_event', $product_id);
}

function clear_schedule_event_for_discount($event = '', $post_id = 0)
{
    if (!empty($event) && $post_id > 0) {
        wp_clear_scheduled_hook($event, [$post_id]);
    }
}


function landing_widgets_init()
{
    register_sidebar([
        'name' => __('Landing Footer', 'yourtheme'),
        'id' => 'landing-footer',
        'before_widget' => '<div class="landing-footer-widget">',
        'after_widget' => '</div>',
        'before_title' => '<h4 class="widget-title">',
        'after_title' => '</h4>',
    ]);
}
add_action('widgets_init', 'landing_widgets_init');


// Add custom button to TinyMCE
add_filter('mce_buttons', 'my_add_media_button_to_toolbar', 20);
function my_add_media_button_to_toolbar($buttons)
{
    array_push($buttons, 'my_media_button');
    return $buttons;
}

// Register TinyMCE plugin for the button
add_filter('mce_external_plugins', 'my_register_media_button_plugin');
function my_register_media_button_plugin($plugins)
{
    $plugins['my_media_button'] = get_stylesheet_directory_uri() . '/assets/js/my-media-button.js';
    return $plugins;
}

// Script for the clear button in the safari date picker

function add_safari_datepicker_clear_script()
{
    ?>
    <script>
        (function ($) {
            // Detect Safari
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            if (!isSafari) {
                console.log('safari');
                return;
            }

            $(document).ready(function () {
                // Target all input[type="date"]
                $('#_discount_valid_from, #_discount_valid_to').each(function () {
                    const $dateInput = $(this);

                    // Attach focus event to show the clear button
                    $dateInput.on('focus', function () {
                        // Remove existing clear button to prevent duplicates
                        $(this).siblings('.datepicker-clear-btn').remove();

                        // Create Clear button
                        const $clearBtn = $('<button type="button" class="datepicker-clear-btn">Clear</button>');

                        // Style the button inside the date picker container
                        $clearBtn.css({
                            position: 'absolute',
                            right: '10px',
                            top: '50%',
                            transform: 'translateY(-50%)',
                            padding: '2px 6px',
                            fontSize: '12px',
                            cursor: 'pointer',
                            zIndex: 9999,
                            background: '#f5f5f5',
                            border: '1px solid #ccc',
                            borderRadius: '4px'
                        });

                        // Insert button after the input
                        $dateInput.after($clearBtn);

                        // Clear input on button click
                        $clearBtn.on('click', function (e) {
                            e.preventDefault();
                            $dateInput.val('').trigger('change');
                            $dateInput.trigger('change'); // optional, if you need change event
                        });
                    });

                    // Remove button on blur to keep it clean
                    $dateInput.on('blur', function () {
                        const $btn = $(this).siblings('.datepicker-clear-btn');
                        setTimeout(() => { $btn.remove(); }, 150); // slight delay to allow click
                    });
                });
            });
        })(jQuery);
    </script>
    <style>
        /* Optional: adjust input container to relative for proper positioning */
        input[type="date"] {
            position: relative;
        }
    </style>
    <?php
}
add_action('wp_footer', 'add_safari_datepicker_clear_script');

add_action('wp_ajax_get_user_balance', 'get_user_balance_callback');

function get_user_balance_callback()
{
    gcp_require_admin_ajax();
    $user_id = intval($_POST['user_id']);
    $type = sanitize_text_field($_POST['type']);
    $balance = '';

    if (!$user_id) {
        wp_send_json_error(['message' => 'Invalid user']);
    }

    // Example: fetch from user meta (adjust to your DB structure)
    if ($type === 'float_balance') {
        $balance = get_user_meta($user_id, 'float_balance', true);
    } else {
        // prepaid_limit is itself the live balance (Client Billing: "Balance = Prepaid
        // Limit"; Instant/Float: the per-transaction cap) — no monthly-usage adjustment.
        $prepaid_limit = (float) get_user_meta($user_id, 'prepaid_limit', true) ?: 0;

        if ($prepaid_limit > -1) {
            $balance = $prepaid_limit;
        }
    }

    // Default to 0 if empty
    $balance = $balance !== '' ? $balance : 0;
    // echo '<pre>';
    // print_r($balance);
    // print_r($user_id);
    // echo '</pre>';
    wp_send_json_success(['balance' => $balance]);
}

// Custom Registration AJAX Handlers

// 1. Create User Account
add_action('wp_ajax_nopriv_custom_create_user', 'custom_create_user_handler');
add_action('wp_ajax_custom_create_user', 'custom_create_user_handler');

function custom_create_user_handler()
{
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'custom_registration')) {
        wp_send_json_error('Security verification failed.');
    }

    // reCAPTCHA v2 verification
    $recaptcha_token = sanitize_text_field( $_POST['recaptcha_token'] ?? '' );
    if ( ! gcp_verify_recaptcha( $recaptcha_token, 'register' ) ) {
        wp_send_json_error( 'reCAPTCHA verification failed. Please try again.' );
        wp_die();
    }

    // Validate required fields
    $required_fields = ['first_name', 'email', 'mobile', 'password'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error(ucfirst(str_replace('_', ' ', $field)) . ' is required.');
        }
    }

    // Sanitize data
    $first_name = sanitize_text_field($_POST['first_name']);
    $surname = sanitize_text_field($_POST['surname']);
    $email = sanitize_email($_POST['email']);
    $mobile = sanitize_text_field($_POST['mobile']);
    $password = $_POST['password'];

    // Validate email
    if (!is_email($email)) {
        wp_send_json_error('Please enter a valid email address.');
    }

    // Validate Australian mobile: 04XXXXXXXX or 61XXXXXXXXX (digits only)
    $mobile_clean = preg_replace('/\D/', '', $mobile);
    if (!preg_match('/^04\d{8}$/', $mobile_clean) && !preg_match('/^614\d{8}$/', $mobile_clean)) {
        wp_send_json_error('Please enter a valid Australian mobile (04xx xxx xxx or 61 4xx xxx xxx).');
    }

    // Check if email already exists
    if (email_exists($email)) {
        wp_send_json_error('This email address is already registered.');
    }

    // Validate password strength
    if (!validate_password_strength($password)) {
        wp_send_json_error('Please ensure your password is strong and includes at least 12 characters with uppercase, lowercase, number and special character.');
    }

    // ✅ STORE DATA TEMPORARILY INSTEAD OF CREATING USER
    $pending_user_data = array(
        'first_name' => $first_name,
        'surname' => $surname,
        'email' => $email,
        'mobile' => $mobile,
        'password' => $password,
        'terms_accepted' => true,
        'terms_accepted_at' => current_time('mysql')
    );

    // Generate unique key for pending registration
    $pending_key = 'pending_user_' . md5($email . time());
    set_transient($pending_key, $pending_user_data, 30 * MINUTE_IN_SECONDS); // 30 minutes expiry

    // Generate and send OTP
    $otp_result = generate_and_send_otp($email, $pending_key, $first_name); // Modified function

    // echo '<pre>'; print_r($otp_result); echo '</pre>';
    // exit;
    if ($otp_result['success']) {
        wp_send_json_success([
            'message' => 'Verification code sent to your email.',
            'pending_key' => $pending_key // Send back to frontend
        ]);
    } else {
        // Clean up if OTP fails
        delete_transient($pending_key);
        wp_send_json_error('Failed to send giftcardsplus verification code: ' . $otp_result['message']);
    }

    // Create user
    /*$user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        wp_send_json_error($user_id->get_error_message());
    }
    // ✅ Set WooCommerce Customer role
    $user = new WP_User($user_id);
    $user->set_role('customer');

    // Update user meta
    update_user_meta($user_id, 'first_name', $first_name);
    update_user_meta($user_id, 'last_name', $surname);
    update_user_meta($user_id, 'billing_phone', $mobile);
    update_user_meta($user_id, 'mobile', $mobile);
    update_user_meta($user_id, 'account_status', 'unverified');
    update_user_meta($user_id, 'terms_accepted', true);
    update_user_meta($user_id, 'terms_accepted_at', current_time('mysql'));

    // Generate and send OTP
    $otp_result = generate_and_send_otp($user_id, $email);

    if ($otp_result['success']) {
        wp_send_json_success('Account created. OTP sent to your email.');
    } else {
        wp_send_json_error('Account created but failed to send OTP: ' . $otp_result['message']);
    }*/
}
// 2. Generate and Send OTP
function generate_and_send_otp($email, $pending_key = null, $first_name = '')
{
    // Normalize email to ensure consistent transient key
    $email = strtolower(trim($email));

    // Generate 6-digit OTP
    $otp_code = str_pad(wp_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_expiry = time() + 60; 


    // echo '<pre>'; print_r($otp_expiry); echo '</pre>';
    // exit;
    // Create a unique transient key for this user
    $otp_transient_key = 'otp_data_' . md5($email);

    // Prepare data
    $otp_data = array(
        'otp_code' => $otp_code,
        'otp_expiry' => $otp_expiry,
        'otp_attempts' => 0,
        'pending_key' => $pending_key
    );

    // Store OTP data
    set_transient($otp_transient_key, $otp_data, 5 * MINUTE_IN_SECONDS);

    // Use passed first_name, fall back to WP user lookup (e.g. resend flow).
    if ( empty( $first_name ) ) {
        $wp_user    = get_user_by( 'email', $email );
        $first_name = $wp_user ? $wp_user->first_name : '';
    }

    // Send OTP email — use the 'register-otp' email template if available.
    $tpl = et_get_template_by_slug( 'register-otp', [
        'otp'        => $otp_code,
        'first_name' => $first_name,
    ] );

    if ( $tpl ) {
        $subject = $tpl['subject'];
        $message = $tpl['body'];
        $headers = $tpl['headers'];
    } else {
        $subject = 'Your giftcardsplus verification code is here';
        $message = "
            <html>
            <head><title>Email Verification</title></head>
            <body>
                <h2>Email Verification</h2>
                <p>Your giftcardsplus verification code is here: <strong>{$otp_code}</strong></p>
                <p>This code will expire in 5 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
            </body>
            </html>
        ";
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    }

    $email_sent = wp_mail($email, $subject, $message, $headers);

    if ($email_sent) {
        return ['success' => true, 'message' => 'OTP sent successfully'];
    } else {
        // Clean up transient if email fails
        delete_transient($otp_transient_key);
        return ['success' => false, 'message' => 'Failed to send OTP email'];
    }
}

// 3. Verify OTP (Using Transient)
add_action('wp_ajax_nopriv_custom_verify_otp', 'custom_verify_otp_handler');
add_action('wp_ajax_custom_verify_otp', 'custom_verify_otp_handler');

function custom_verify_otp_handler()
{
    gcp_check_rate_limit( 'custom_verify_otp', 10, 15 * MINUTE_IN_SECONDS );
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'custom_registration')) {
        wp_send_json_error('Security verification failed.');
    }

    if (empty($_POST['otp_code']) || empty($_POST['email'])) {
        wp_send_json_error('OTP code and email are required.');
    }

    $otp_code = sanitize_text_field($_POST['otp_code']);
    $email = sanitize_email($_POST['email']);

    // Normalize email to lowercase to ensure consistent transient key
    $email = strtolower(trim($email));

    // Get OTP data using email
    $otp_transient_key = 'otp_data_' . md5($email);
    $otp_data = get_transient($otp_transient_key);
    // echo '<pre>'; print_r($otp_data); echo '</pre>';
    // exit;

    if (!$otp_data) {
        // Transient not found - this could mean it expired, was deleted, or never existed
        wp_send_json_error('OTP has expired or not found. Please request a new one.');
    }

    // Check expiry time first - but don't delete if it's just expired, let the user see the error
    if (time() > $otp_data['otp_expiry']) {
        // Only delete if this is an actual submission, not auto-validation
        $is_auto_validation = isset($_POST['is_auto_validation']) && $_POST['is_auto_validation'] === 'true';
        if (!$is_auto_validation) {
            delete_transient($otp_transient_key);
        }
        wp_send_json_error('OTP has expired or not found. Please request a new one.');
    }

    // Check if too many attempts (only for actual submissions, not auto-validation)
    $is_auto_validation = isset($_POST['is_auto_validation']) && $_POST['is_auto_validation'] === 'true';
    if (!$is_auto_validation && !empty($otp_data['otp_attempts']) && $otp_data['otp_attempts'] >= 5) {
        delete_transient($otp_transient_key);
        wp_send_json_error('Too many invalid OTP attempts. Please request a new OTP.');
    }

    // Validate OTP code (check if it matches)
    $otp_matches = ($otp_data['otp_code'] === $otp_code);

    // Validate OTP and expiry
    if ($otp_matches && time() <= $otp_data['otp_expiry']) {

        // Check if this is auto-validation - if so, just return success without creating user or deleting transient
        if ($is_auto_validation) {
            // Auto-validation: just confirm OTP is correct, don't create user or delete transient
            wp_send_json_success('OTP is valid');
        }

        // ✅ OTP is correct - NOW CREATE THE USER (only on actual form submission)
        $pending_key = $otp_data['pending_key'];
        $pending_data = get_transient($pending_key);

        if (!$pending_data) {
            wp_send_json_error('Registration session expired. Please restart registration.');
        }

        // Create user with pending data
        $user_id = wp_create_user($pending_data['email'], $pending_data['password'], $pending_data['email']);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        // ✅ Set WooCommerce Customer role
        $user = new WP_User($user_id);
        $user->set_role('customer');

        // Update user meta
        update_user_meta($user_id, 'first_name', $pending_data['first_name']);
        update_user_meta($user_id, 'last_name', $pending_data['surname']);
        update_user_meta($user_id, 'billing_phone', $pending_data['mobile']);
        update_user_meta($user_id, 'mobile', $pending_data['mobile']);
        update_user_meta($user_id, 'account_status', 'verified');
        update_user_meta($user_id, 'email_verified', true);
        update_user_meta($user_id, 'email_verified_at', current_time('mysql'));
        update_user_meta($user_id, 'terms_accepted', true);
        update_user_meta($user_id, 'terms_accepted_at', $pending_data['terms_accepted_at']);

        // Log the user in so Steps 3 and 4 AJAX calls pass authentication
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, false );

        // Clean up transients
        delete_transient($otp_transient_key);
        delete_transient($pending_key);

        // Send welcome email
        $tpl = et_get_template_by_slug( 'welcome-user', [
            'first_name' => $pending_data['first_name'],
        ] );
        if ( $tpl ) {
            wp_mail( $pending_data['email'], $tpl['subject'], $tpl['body'], $tpl['headers'] );
        }

        // Return a fresh nonce generated for the now-logged-in user so the JS
        // can replace the old (logged-out) nonce before Steps 3/4 fire.
        wp_send_json_success( array(
            'message'    => 'Email verified and account created successfully.',
            'new_nonce'  => wp_create_nonce( 'custom_registration' ),
        ) );
    } else {
        // ❌ Invalid OTP - only increment attempt count if this is NOT auto-validation
        // Make sure we have the is_auto_validation flag (it might not be set in the else block)
        if (!isset($is_auto_validation)) {
            $is_auto_validation = isset($_POST['is_auto_validation']) && $_POST['is_auto_validation'] === 'true';
        }

        if (!$is_auto_validation) {
            // Only increment attempts on actual form submission, not during auto-validation
            $otp_data['otp_attempts'] = isset($otp_data['otp_attempts']) ? $otp_data['otp_attempts'] + 1 : 1;
            // Calculate remaining time based on original expiry
            $time_until_expiry = $otp_data['otp_expiry'] - time();
            // Ensure transient lasts at least 60 seconds, but use original expiry if more time remains
            $remaining_time = max(60, $time_until_expiry);
            // Save the transient with updated attempts count, preserving the original expiry in the data
            set_transient($otp_transient_key, $otp_data, $remaining_time);
        }

        wp_send_json_error('This code is incorrect please try again or click resend code.');
    }
}


// 4. Resend OTP
add_action('wp_ajax_nopriv_custom_resend_otp', 'custom_resend_otp_handler');
add_action('wp_ajax_custom_resend_otp', 'custom_resend_otp_handler');

function custom_resend_otp_handler()
{
    gcp_check_rate_limit( 'custom_resend_otp', 5, 15 * MINUTE_IN_SECONDS );
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'custom_registration')) {
        wp_send_json_error('Security verification failed.');
    }

    if (empty($_POST['email'])) {
        wp_send_json_error('Email is required.');
    }

    $email = sanitize_email($_POST['email']);
    $pending_key = sanitize_text_field($_POST['pending_key']);

    // Verify pending data still exists
    $pending_data = get_transient($pending_key);
    if (!$pending_data) {
        wp_send_json_error('Registration session expired. Please restart registration.');
    }

    $otp_result = generate_and_send_otp($email, $pending_key, $pending_data['first_name'] ?? '');

    if ($otp_result['success']) {
        wp_send_json_success('New verification code sent to your email.');
    } else {
        wp_send_json_error('Failed to send verification code: ' . $otp_result['message']);
    }
}

// 5. Save Profile Details
add_action('wp_ajax_nopriv_custom_save_profile', 'custom_save_profile_handler');
add_action('wp_ajax_custom_save_profile', 'custom_save_profile_handler');

function custom_save_profile_handler()
{
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

    // Save date of birth (accepts Y-m-d, DD/MM/YYYY, or any parseable date from registration)
    if (isset($_POST['date_of_birth']) && trim((string) $_POST['date_of_birth']) !== '') {
        $dob = trim(sanitize_text_field($_POST['date_of_birth']));
        $dob_stored = normalize_dob_to_ymd($dob);
        if ($dob_stored && !validate_date_of_birth($dob_stored)) {
            $dob_stored = null;
        }
        if (!$dob_stored) {
            $dob_stored = parse_and_validate_dob_for_save($dob);
        }
        if ($dob_stored) {
            update_user_meta($user_id, 'dob', $dob_stored);
        }
    }

    // Save state
    if (!empty($_POST['state'])) {
        update_user_meta($user_id, 'billing_state', $_POST['state']);
        update_user_meta($user_id, 'state', $_POST['state']);
    }

    // Save marketing preferences
    update_user_meta($user_id, 'marketing_emails', !empty($_POST['marketing_emails']));
    update_user_meta($user_id, 'sms_notifications', !empty($_POST['sms_notifications']));

    wp_send_json_success('Profile details saved successfully.');
}

function validate_date_of_birth($dob)
{
    $dob = trim($dob);
    if (empty($dob)) {
        return false;
    }

    $date = null;
    // Accept Y-m-d (from datepicker / HTML date input)
    $pattern_ymd = '/^(19|20)\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/';
    if (preg_match($pattern_ymd, $dob)) {
        $date = date_create_from_format('Y-m-d', $dob);
    }
    // Accept DD/MM/YYYY (legacy / manual input)
    $pattern_dmy = '/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/(19|20)\d{2}$/';
    if (!$date && preg_match($pattern_dmy, $dob)) {
        $parts = explode('/', $dob);
        $date = date_create_from_format('Y-m-d', $parts[2] . '-' . $parts[1] . '-' . $parts[0]);
    }

    if (!$date || $date === false) {
        return false;
    }
    $today = date_create('today');

    return $date < $today;
}
/**
 * Try to parse any reasonable date string and return Y-m-d if valid and age 13–120.
 * Used when strict normalize_dob_to_ymd fails (e.g. locale formats from datepicker).
 */
function parse_and_validate_dob_for_save($dob)
{
    $dob = trim($dob);
    if (empty($dob)) {
        return false;
    }
    $date = null;
    $formats = array('Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y');
    foreach ($formats as $fmt) {
        $date = date_create_from_format($fmt, $dob);
        if ($date !== false) {
            break;
        }
    }
    if (!$date || $date === false) {
        $ts = strtotime($dob);
        if ($ts !== false) {
            $date = date_create_from_format('Y-m-d', date('Y-m-d', $ts));
        }
    }
    if (!$date || $date === false) {
        return false;
    }
    $today = date_create('today');
    // Only rule: birth date must be in the past
    if ($date >= $today) {
        return false;
    }
    return $date->format('Y-m-d');
}


// // Utility Functions
// function validate_password_strength($password) {
//     // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
//     $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
//     return preg_match($pattern, $password);
// }
// Utility Functions
function validate_password_strength($password)
{
    // At least 12 chars, one uppercase, one lowercase, one number, one special char
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/';
    return preg_match($pattern, $password);
}

/**
 * Normalize date of birth to Y-m-d for storage.
 * Accepts Y-m-d or DD/MM/YYYY. Returns Y-m-d or false.
 */
function normalize_dob_to_ymd($dob)
{
    $dob = trim($dob);
    if (empty($dob)) {
        return false;
    }
    $pattern_ymd = '/^(19|20)\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/';
    if (preg_match($pattern_ymd, $dob)) {
        return $dob;
    }
    $pattern_dmy = '/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/(19|20)\d{2}$/';
    if (preg_match($pattern_dmy, $dob)) {
        $parts = explode('/', $dob);
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return false;
}

/**************************************/
// User Redirect Custom Register Page //
/**************************************/
add_action('template_redirect', function () {
    if (is_account_page() && !is_user_logged_in()) {
        wp_redirect(site_url('/user-registration'));
        exit;
    }
});

// Enqueue scripts
function custom_registration_scripts()
{
    if (is_page('user-registration') || is_page('user-login')) {
        // Load core jQuery
        wp_enqueue_script('jquery');

        // ✅ Load jQuery UI (includes datepicker)
        wp_enqueue_script(
            'jquery-ui-datepicker'
        );

        // ✅ Load jQuery UI stylesheet
        wp_enqueue_style(
            'jquery-ui-css',
            'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
            array(),
            '1.13.2'
        );

        // ✅ Load your custom script
        wp_enqueue_script(
            'custom-registration',
            get_template_directory_uri() . '/assets/js/custom-registration.js',
            array('jquery', 'jquery-ui-datepicker'), // dependencies
            time(), // use current timestamp as version
            true
        );

        // ✅ Localize AJAX variables
        wp_localize_script('custom-registration', 'custom_registration_ajax', array(
            'ajax_url'            => admin_url('admin-ajax.php'),
            'nonce'               => wp_create_nonce('custom_registration'),
            'recaptcha_site_key'  => GCP_RECAPTCHA_SITE_KEY,
        ));
    }

    /****************************/
    // Home Page JS File Enqueue//
    /***************************/
    $restricted_templates = [
        'gift-card-form-2',
        'brand-listing',
        'bulk-create-category',
        'bulk-create-product',
        'custom-reset-password',
        'template-email-logs',
        'template-email-settings',
        'invoice-display',
        'manual-order',
        'product-categories',
        'review-products',
        'sms-settings',
        'supplier-login',
        'supplier-registration',
        'users',
        'reports',
        'product-listing',
        'product-listing-section'
        // 'brands-listing'
    ];
    $template = basename(get_page_template_slug(), '.php');
    // $page_id = 33;  // Page ID where you want to exclude the CSS/JS.

    if (!in_array($template, $restricted_templates)) {
        wp_enqueue_script(
            'home-page-js',
            get_template_directory_uri() . '/assets/js/home-page.js',
            array('jquery'),
            time(),
            true
        );

        wp_localize_script(
            'home-page-js',         // must match the enqueue handle
            'homePageData',         // name of the JS object in your script
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gc_nonce')
            )
        );

        wp_enqueue_style(
            'home-page',
            get_template_directory_uri() . '/assets/css/home-page.css',
            array(),
            time() // Cache busting
        );
    }




    wp_enqueue_style(
        'forntend-user',
        get_template_directory_uri() . '/assets/css/forntend-user.css',
        array(),
        time() // Cache busting
    );


}
add_action('wp_enqueue_scripts', 'custom_registration_scripts');


/**************************************************/
// Show Custom Meta Field In User Edit Admin Site //
/**************************************************/
// 1️⃣ Add custom fields to the user profile page
add_action('show_user_profile', 'add_custom_user_fields', 50);
add_action('edit_user_profile', 'add_custom_user_fields', 50);
function add_custom_user_fields($user)
{
    $account_status = get_user_meta($user->ID, 'account_status', true);
    $terms_accepted = get_user_meta($user->ID, 'terms_accepted', true);
    $terms_accepted_at = get_user_meta($user->ID, 'terms_accepted_at', true);
    $email_verified = get_user_meta($user->ID, 'email_verified', true);
    $marketing_emails = get_user_meta($user->ID, 'marketing_emails', true);
    $sms_notifications = get_user_meta($user->ID, 'sms_notifications', true);
    $user_hobbies = (array) get_user_meta($user->ID, 'hobbies', true);
    $user_events = (array) get_user_meta($user->ID, 'interested_events', true);
    $state = get_user_meta($user->ID, 'state', true); // ✅ Added

    $hobbies = [
        'reading' => 'Reading',
        'travel' => 'Travel',
        'sports' => 'Sports',
        'cooking' => 'Cooking',
        'gardening' => 'Gardening',
        'photography' => 'Photography',
        'music' => 'Music',
        'art' => 'Art & Crafts'
    ];

    $events = [
        'birthdays' => 'Birthdays',
        'weddings' => 'Weddings',
        'anniversaries' => 'Anniversaries',
        'christmas' => 'Christmas',
        'easter' => 'Easter',
        'valentines' => "Valentine's Day",
        'mothers_day' => "Mother's Day",
        'fathers_day' => "Father's Day"
    ];

    $states = [
        '' => 'Select State',
        'NSW' => 'New South Wales',
        'VIC' => 'Victoria',
        'QLD' => 'Queensland',
        'WA' => 'Western Australia',
        'SA' => 'South Australia',
        'TAS' => 'Tasmania',
        'ACT' => 'Australian Capital Territory',
        'NT' => 'Northern Territory',
        'other' => 'Other'
    ];
    ?>
    <h3>Extra User Information</h3>
    <table class="form-table">
        <tr>
            <th><label for="account_status">Account Status</label></th>
            <td>
                <select name="account_status" id="account_status">
                    <option value="verified" <?php selected($account_status, 'verified'); ?>>Verified</option>
                    <option value="unverified" <?php selected($account_status, 'unverified'); ?>>Unverified</option>
                </select>
            </td>
        </tr>

        <tr>
            <th><label for="terms_accepted">Terms Accepted</label></th>
            <td>
                <input type="checkbox" name="terms_accepted" value="1" <?php checked($terms_accepted, true); ?>> Yes <br>
                <small>Accepted at: <?php echo esc_html($terms_accepted_at); ?></small>
            </td>
        </tr>

        <tr>
            <th><label for="email_verified">Email Verified</label></th>
            <td>
                <input type="checkbox" name="email_verified" value="1" <?php checked($email_verified, true); ?>>
                Verified<br>
                <small>Verified at: <?php echo esc_html(get_user_meta($user->ID, 'email_verified_at', true)); ?></small>
            </td>
        </tr>

        <tr>
            <th><label for="marketing_emails">Marketing Emails</label></th>
            <td><input type="checkbox" name="marketing_emails" value="1" <?php checked($marketing_emails, true); ?>>
                Subscribe</td>
        </tr>

        <tr>
            <th><label for="sms_notifications">SMS Notifications</label></th>
            <td><input type="checkbox" name="sms_notifications" value="1" <?php checked($sms_notifications, true); ?>>
                Receive SMS</td>
        </tr>

        <tr>
            <th><label for="state">State</label></th>
            <td>
                <select id="state" name="state" style="width: 300px;">
                    <?php foreach ($states as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($state, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

        <tr>
            <th><label for="hobbies">Hobbies</label></th>
            <td>
                <select name="hobbies[]" id="hobbies" multiple class="select2-multi" style="width: 300px;">

                    <?php
                    if (have_rows('hobbies', 'option')):

                        while (have_rows('hobbies', 'option')):
                            the_row();
                            $hobby_label = get_sub_field('hobbie_name');
                            if ($hobby_label) {
                                $hobby_key = sanitize_title($hobby_label);
                                ?>

                                <option value="<?php echo esc_attr($hobby_key); ?>" <?php echo in_array($hobby_key, $user_hobbies) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($hobby_label); ?>
                                </option>

                                <?php
                            }
                        endwhile;

                    endif;
                    ?>

                </select>
            </td>
        </tr>


        <tr>
            <th><label for="interested_events">Interested Events</label></th>
            <td>
                <select name="interested_events[]" id="interested_events" multiple class="select2-multi" style="width: 300px;">

                    <?php
                    if (have_rows('events', 'option')):

                        while (have_rows('events', 'option')):
                            the_row();
                            $event_label = get_sub_field('event_name');
                            if ($event_label) {
                                $event_key = sanitize_title($event_label);
                                ?>

                                <option value="<?php echo esc_attr($event_key); ?>" <?php echo in_array($event_key, $user_events) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($event_label); ?>
                                </option>

                                <?php
                            }
                        endwhile;

                    endif;
                    ?>

                </select>
            </td>
        </tr>

    </table>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $(".select2-multi").select2({
                placeholder: "Select options",
                allowClear: true,
                width: "resolve"
            });
        });
    </script>
    <?php
}

// 2️⃣ Save custom fields
add_action('personal_options_update', 'save_custom_user_fields');
add_action('edit_user_profile_update', 'save_custom_user_fields');

function save_custom_user_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    update_user_meta($user_id, 'account_status', sanitize_text_field($_POST['account_status']));
    update_user_meta($user_id, 'terms_accepted', !empty($_POST['terms_accepted']));

    $email_verified = !empty($_POST['email_verified']);
    update_user_meta($user_id, 'email_verified', $email_verified);
    if ($email_verified) {
        update_user_meta($user_id, 'email_verified_at', current_time('mysql'));
    } else {
        delete_user_meta($user_id, 'email_verified_at');
    }

    update_user_meta($user_id, 'marketing_emails', !empty($_POST['marketing_emails']));
    update_user_meta($user_id, 'sms_notifications', !empty($_POST['sms_notifications']));

    update_user_meta($user_id, 'state', sanitize_text_field($_POST['state'])); // ✅ Added line

    update_user_meta($user_id, 'hobbies', isset($_POST['hobbies']) ? array_map('sanitize_text_field', $_POST['hobbies']) : []);
    update_user_meta($user_id, 'interested_events', isset($_POST['interested_events']) ? array_map('sanitize_text_field', $_POST['interested_events']) : []);
}


// 3️⃣ Enqueue Select2 in the admin (WordPress already includes it)
add_action('admin_enqueue_scripts', 'add_select2_for_user_profile');
function add_select2_for_user_profile($hook)
{
    // Only load on user edit or profile pages
    if (in_array($hook, ['user-edit.php', 'profile.php'])) {
        wp_enqueue_script('select2');
        wp_enqueue_style('select2');
    }
}

// AJAX: Search WooCommerce product categories by name
function ajax_instant_category_search()
{

    $keyword = sanitize_text_field($_GET['s']);

    $args = array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'parent' => 0,
        'search' => $keyword, // ✅ match category names
    );

    $categories = get_terms($args);

    if (!empty($categories) && !is_wp_error($categories)) {
        foreach ($categories as $cat) {
            $thumbnail_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
            $image_url = wp_get_attachment_url($thumbnail_id);

            ?>
            <div class="giftcard-category-item">
                <a href="<?php echo get_term_link($cat); ?>">
                    <div class="giftcard-category-icon">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($cat->name); ?>">
                    </div>
                    <p data-fulltext="<?php echo esc_attr($cat->name); ?>">
                    <?php //echo esc_html(wp_trim_words($cat->name, 3, '...')); ?>

                    <?php
                        $full_name    = $cat->name;
                        $display_name = gc_trim_chars( $full_name, 15, '...' ); // 25 chars max
                    ?>
                    <p data-fulltext="<?php echo esc_attr( $full_name ); ?>">
                        <?php echo esc_html( $display_name ); ?>
                    </p>
                </p>
                </a>
            </div>
            <?php
        }
    } else {
        echo "<p>No categories found.</p>";
    }

    wp_die();
}

add_action('wp_ajax_instant_category_search', 'ajax_instant_category_search');
add_action('wp_ajax_nopriv_instant_category_search', 'ajax_instant_category_search');

function mytheme_customize_register($wp_customize)
{

    $wp_customize->add_section('mytheme_social_icons', array(
        'title' => __('Social Icons', 'mytheme'),
        'description' => __('Add your social media links.', 'mytheme'),
        'priority' => 30,
    ));

    // Added linkedin here
    $socials = array(
        'facebook' => 'Facebook URL',
        'twitter' => 'Twitter URL',
        'instagram' => 'Instagram URL',
        'linkedin' => 'LinkedIn URL'
    );

    foreach ($socials as $key => $label) {
        $setting = "mytheme_{$key}_url";

        $wp_customize->add_setting($setting, array(
            'default' => '',
            'sanitize_callback' => 'esc_url_raw'
        ));

        $wp_customize->add_control($setting, array(
            'label' => __($label, 'mytheme'),
            'section' => 'mytheme_social_icons',
            'type' => 'url'
        ));
    }
}
add_action('customize_register', 'mytheme_customize_register');


// Add image upload field to Add New Product Tag form
add_action('product_tag_add_form_fields', 'add_product_tag_image_field');
function add_product_tag_image_field()
{
    ?>
    <div class="form-field term-group">
        <label for="tag-image-id"><?php _e('Tag Image', 'woocommerce'); ?></label>
        <input type="hidden" id="tag-image-id" name="tag-image-id" value="">
        <div id="tag-image-wrapper"></div>
        <p>
            <input type="button" class="button upload_tag_image_button"
                value="<?php _e('Upload/Add Image', 'woocommerce'); ?>" />
            <input type="button" class="button remove_tag_image_button"
                value="<?php _e('Remove Image', 'woocommerce'); ?>" />
        </p>
        <script>
            jQuery(document).ready(function ($) {
                var frame;
                $('.upload_tag_image_button').on('click', function (e) {
                    e.preventDefault();
                    if (frame) frame.open();
                    frame = wp.media({
                        title: '<?php _e('Select or Upload Tag Image', 'woocommerce'); ?>',
                        button: { text: '<?php _e('Use this image', 'woocommerce'); ?>' },
                        multiple: false
                    });
                    frame.on('select', function () {
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#tag-image-id').val(attachment.id);
                        $('#tag-image-wrapper').html('<img src="' + attachment.url + '" style="max-width:100px;"/>');
                    });
                    frame.open();
                });

                $('.remove_tag_image_button').on('click', function () {
                    $('#tag-image-id').val('');
                    $('#tag-image-wrapper').html('');
                });
            });
        </script>
    </div>
    <?php
}

// Save the uploaded image when creating a new tag
add_action('created_product_tag', 'save_product_tag_image_field', 10, 2);
function save_product_tag_image_field($term_id, $tt_id)
{
    if (isset($_POST['tag-image-id']) && $_POST['tag-image-id'] !== '') {
        add_term_meta($term_id, 'tag-image-id', absint($_POST['tag-image-id']), true);
    }
}

// Add image upload field to Edit Tag form
add_action('product_tag_edit_form_fields', 'edit_product_tag_image_field', 10, 2);
function edit_product_tag_image_field($term, $taxonomy)
{
    $image_id = get_term_meta($term->term_id, 'tag-image-id', true);
    $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="tag-image-id"><?php _e('Tag Image', 'woocommerce'); ?></label></th>
        <td>
            <input type="hidden" id="tag-image-id" name="tag-image-id" value="<?php echo esc_attr($image_id); ?>">
            <div id="tag-image-wrapper">
                <?php if ($image_url): ?>
                    <img src="<?php echo esc_url($image_url); ?>" style="max-width:100px;">
                <?php endif; ?>
            </div>
            <p>
                <input type="button" class="button upload_tag_image_button"
                    value="<?php _e('Upload/Add Image', 'woocommerce'); ?>" />
                <input type="button" class="button remove_tag_image_button"
                    value="<?php _e('Remove Image', 'woocommerce'); ?>" />
            </p>
        </td>
    </tr>
    <script>
        jQuery(document).ready(function ($) {
            var frame;
            $('.upload_tag_image_button').on('click', function (e) {
                e.preventDefault();
                if (frame) frame.open();
                frame = wp.media({
                    title: '<?php _e('Select or Upload Tag Image', 'woocommerce'); ?>',
                    button: { text: '<?php _e('Use this image', 'woocommerce'); ?>' },
                    multiple: false
                });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#tag-image-id').val(attachment.id);
                    $('#tag-image-wrapper').html('<img src="' + attachment.url + '" style="max-width:100px;"/>');
                });
                frame.open();
            });

            $('.remove_tag_image_button').on('click', function () {
                $('#tag-image-id').val('');
                $('#tag-image-wrapper').html('');
            });
        });
    </script>
    <?php
}

// Update tag image when editing
add_action('edited_product_tag', 'update_product_tag_image_field', 10, 2);
function update_product_tag_image_field($term_id, $tt_id)
{
    if (isset($_POST['tag-image-id']) && $_POST['tag-image-id'] !== '') {
        update_term_meta($term_id, 'tag-image-id', absint($_POST['tag-image-id']));
    } else {
        delete_term_meta($term_id, 'tag-image-id');
    }
}


/* ----------------------------------------------------
 * Add body class if logged-in user role is 'vis'
 * ---------------------------------------------------- */
function add_vis_role_body_class($classes)
{

    if (is_user_logged_in()) {
        $user = wp_get_current_user();

        // Check role
        if (in_array('customer', (array) $user->roles) || in_array('administrator', (array) $user->roles)) {
            $classes[] = 'body-user-login-front';
        } else {
            $classes[] = 'body-user-logout-front';
        }
    }
    if (!is_user_logged_in()) {
        $classes[] = 'body-user-logout-front';
    }
    return $classes;
}
add_filter('body_class', 'add_vis_role_body_class');


add_action('wp_ajax_gc_get_giftcards', 'gc_get_giftcards_ajax');
add_action('wp_ajax_nopriv_gc_get_giftcards', 'gc_get_giftcards_ajax');
function gc_get_giftcards_ajax()
{
    /*if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'gc_plus_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }*/
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';

    // Build base SQL: products that are published and have _sku
    $sql = "
        SELECT p.ID, p.post_title, pm_sku.meta_value AS sku
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_type ON pm_type.post_id = p.ID AND pm_type.meta_key = 'sku_type'
        INNER JOIN {$wpdb->postmeta} pm_sku ON pm_sku.post_id = p.ID AND pm_sku.meta_key = '_sku'
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
    ";

    $params = [];

    if ($q !== '') {
        // search by sku, title, brand term name
        // We'll left join term relationships/taxonomy tables to match brand name
        $search_like = '%' . $wpdb->esc_like($q) . '%';
        $sql = "
            SELECT DISTINCT p.ID, p.post_title, pm_sku.meta_value AS sku
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_sku ON pm_sku.post_id = p.ID AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_skutype ON pm_skutype.post_id = p.ID AND pm_skutype.meta_key = 'sku_type'
            LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (
                pm_sku.meta_value LIKE %s
                OR p.post_title LIKE %s
                OR t.name LIKE %s
            )
            AND tt.taxonomy = %s
        ";
        $params = [$search_like, $search_like, $search_like, 'product_brand'];
    } else {
        // No query: fetch a limited set (e.g. first 500) to avoid massive payload; admin may click Select All to get full
        $sql .= " LIMIT 500";
    }

    $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

    if (!$rows) {
        wp_send_json_success([]);
    }

    // Build nested structure by brand -> parent -> children and individuals
    $by_brand = [];

    foreach ($rows as $r) {
        $product_id = intval($r['ID']);
        $sku = $r['sku'];
        $title = $r['post_title'];
        // get sku_type and parent_sku
        $sku_type = get_post_meta($product_id, 'sku_type', true);
        $parent_sku = get_post_meta($product_id, 'parent_sku', true);
        // get brands
        $terms = wp_get_post_terms($product_id, 'product_brand');
        $brand_name = (!is_wp_error($terms) && !empty($terms)) ? $terms[0]->name : 'Unbranded';

        if (!isset($by_brand[$brand_name])) {
            $by_brand[$brand_name] = ['parents' => [], 'individuals' => []];
        }

        if (strtolower($sku_type) === 'child' && $parent_sku) {
            // attach under parent
            // find parent in brand->parents or create stub parent if not yet present
            $found = false;
            foreach ($by_brand[$brand_name]['parents'] as &$p) {
                if (isset($p['parent_sku']) && $p['parent_sku'] === $parent_sku) {
                    $p['children'][] = ['sku' => $sku, 'title' => $title, 'product_id' => $product_id];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // create parent stub with unknown title (we will attempt to find parent product)
                $parent_title = '';
                // attempt find parent product by parent_sku (meta)
                $parent_id = $wpdb->get_var($wpdb->prepare("
                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1
                ", $parent_sku));
                if ($parent_id) {
                    $parent_title = get_post_field('post_title', $parent_id);
                }
                $by_brand[$brand_name]['parents'][] = [
                    'parent_sku' => $parent_sku,
                    'title' => $parent_title ? $parent_title : $parent_sku,
                    'children' => [['sku' => $sku, 'title' => $title, 'product_id' => $product_id]]
                ];
            }
        } else {
            // Parent or Individual (treat Parent as parent entry with no children yet)
            if (strtolower($sku_type) === 'parent') {
                // parent entry
                $by_brand[$brand_name]['parents'][] = [
                    'parent_sku' => $sku,
                    'title' => $title,
                    'children' => []
                ];
            } else {
                // individual SKU
                $by_brand[$brand_name]['individuals'][] = ['sku' => $sku, 'title' => $title, 'product_id' => $product_id];
            }
        }
    }

    wp_send_json_success($by_brand);
}

// Save handler: Save eligible_gift_cards_json into post meta when product is saved
// add_action( 'save_post', 'gc_save_eligible_gift_cards_meta', 20, 2 );
function gc_save_eligible_gift_cards_meta($post_id, $post)
{
    // only for products and when user can edit
    if ($post->post_type !== 'product')
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    if (isset($_POST['eligible_gift_cards_json'])) {
        $raw = wp_unslash($_POST['eligible_gift_cards_json']);
        // validate JSON
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            // sanitize items: keep sku and rank and product_id/title/brand
            $san = array();
            foreach ($decoded as $it) {
                if (empty($it['sku']))
                    continue;
                $san[] = array(
                    'sku' => sanitize_text_field($it['sku']),
                    'rank' => isset($it['rank']) ? intval($it['rank']) : 0,
                    'title' => isset($it['title']) ? sanitize_text_field($it['title']) : '',
                    'brand' => isset($it['brand']) ? sanitize_text_field($it['brand']) : '',
                    'product_id' => isset($it['product_id']) ? intval($it['product_id']) : 0
                );
            }
            update_post_meta($post_id, 'eligible_gift_cards_json', wp_json_encode($san));
        } else {
            // if invalid JSON, delete meta to avoid storing wrong data
            delete_post_meta($post_id, 'eligible_gift_cards_json');
        }
    }
}


add_action('acf/save_post', 'save_eligible_gift_cards', 20);
function save_eligible_gift_cards($post_id)
{
    if (get_post_type($post_id) !== 'product')
        return;

    if (empty($_POST['eligible_gift_cards_json']))
        return;

    $arr = json_decode(wp_unslash($_POST['eligible_gift_cards_json']), true);
    if (!$arr)
        return;

    $ids = array_filter(array_column($arr, 'product_id'), function ($id) {
        return !empty($id) && is_numeric($id) && $id > 0;
    });

    $ids = array_map('intval', $ids);

    // Sync to ACF Post Object field
    update_field('field_6925a1a7c83de', $ids, $post_id);
}

// ---------------------------------------------------------------------------
// Sync eligible_gift_cards (ACF Post Object) → eligible_gift_cards_json
//
// When an admin adds/removes products via the ACF "Eligible Gift Cards" field
// directly in WP Admin, only the eligible_gift_cards meta is updated.
// fetch_swap_catalog() reads eligible_gift_cards_json, so without this sync
// the two keys get out of sync and newly added products never appear in swap.
//
// Runs at priority 30 (after save_eligible_gift_cards at 20) so we always
// have the final ACF value before building the JSON.
// ---------------------------------------------------------------------------
add_action( 'acf/save_post', 'gcp_sync_eligible_gift_cards_to_json', 30 );
function gcp_sync_eligible_gift_cards_to_json( $post_id ) {
    if ( get_post_type( $post_id ) !== 'product' ) {
        return;
    }

    // Read the ACF Post Object field — stored as serialized array of post IDs
    $acf_ids = get_post_meta( $post_id, 'eligible_gift_cards', true );
    if ( empty( $acf_ids ) ) {
        return;
    }

    if ( is_string( $acf_ids ) ) {
        $acf_ids = maybe_unserialize( $acf_ids );
    }

    if ( ! is_array( $acf_ids ) ) {
        return;
    }

    // Build JSON array matching the eligible_gift_cards_json structure
    $json_arr = [];
    foreach ( $acf_ids as $pid ) {
        $pid = intval( $pid );
        if ( $pid <= 0 ) {
            continue;
        }
        $product = wc_get_product( $pid );
        if ( ! $product ) {
            continue;
        }
        $json_arr[] = [
            'product_id' => $pid,
            'sku'        => $product->get_sku(),
        ];
    }

    update_post_meta( $post_id, 'eligible_gift_cards_json', wp_json_encode( $json_arr ) );
}

add_filter('wpb_disable_frontend', '__return_true');

add_filter('template_include', function ($template) {
    if (strpos($template, 'js_composer') !== false) {
        return get_template_directory() . '/page.php';
    }
    return $template;
}, 1000);

/**
 * AJAX handler to update reminders calendar without page reload
 */
add_action('wp_ajax_update_reminders_calendar', 'handle_update_reminders_calendar');
function handle_update_reminders_calendar()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'reminders_calendar_nonce')) {
        wp_send_json_error(['message' => 'Invalid security token']);
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'User not logged in']);
        return;
    }

    $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $category_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';

    // Calculate first and last day of the month for calendar
    $first_day_of_month = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
    $last_day_of_month = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

    // Get events for calendar (using the same function from my-reminders.php)
    // Query 1: Events with _gc_user_id matching current user
    $args1 = [
        'post_type' => 'tribe_events',
        'posts_per_page' => -1,
        'meta_key' => '_EventStartDate',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => '_gc_user_id',
                'value' => $user_id,
            ],
            [
                'key' => '_EventStartDate',
                'value' => [$first_day_of_month . ' 00:00:00', $last_day_of_month . ' 23:59:59'],
                'compare' => 'BETWEEN',
                'type' => 'DATETIME'
            ]
        ]
    ];

    $args2 = [
        'post_type' => 'tribe_events',
        'posts_per_page' => -1,
        'author' => $user_id,
        'meta_key' => '_EventStartDate',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => '_EventStartDate',
                'value' => [$first_day_of_month . ' 00:00:00', $last_day_of_month . ' 23:59:59'],
                'compare' => 'BETWEEN',
                'type' => 'DATETIME'
            ]
        ]
    ];

    // Query 3: Public holidays & religious holidays – show for all users regardless of author
    $args3 = [
        'post_type'        => 'tribe_events',
        'posts_per_page'   => -1,
        'suppress_filters' => true,
        'meta_key'         => '_EventStartDate',
        'orderby'          => 'meta_value',
        'order'            => 'ASC',
        'tax_query'        => [
            [
                'taxonomy' => 'tribe_events_cat',
                'field'    => 'slug',
                'terms'    => ['public-holidays', 'religious-holiday'],
            ],
        ],
        'meta_query'       => [
            [
                'key'     => '_EventStartDate',
                'value'   => [$first_day_of_month . ' 00:00:00', $last_day_of_month . ' 23:59:59'],
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            ],
        ],
    ];


    // Add category filter if provided
    if ($category_filter && $category_filter !== 'all') {
        $tax_query = [
            [
                'taxonomy' => 'tribe_events_cat',
                'field' => 'slug',
                'terms' => $category_filter,
            ]
        ];
        $args1['tax_query'] = $tax_query;
        $args2['tax_query'] = $tax_query;
    }

    // Get events from all three queries
    $events1 = get_posts($args1);
    $events2 = get_posts($args2);
    $events3 = get_posts($args3);

    // Merge and remove duplicates (user's events + public holidays for all users)
    $event_ids = [];
    $all_events = [];
    foreach (array_merge($events1, $events2, $events3) as $event) {
        if (!in_array($event->ID, $event_ids)) {
            $event_ids[] = $event->ID;
            $all_events[] = $event;
        }
    }

    // Sort by event date
    usort($all_events, function ($a, $b) {
        $date_a = get_post_meta($a->ID, '_EventStartDate', true);
        $date_b = get_post_meta($b->ID, '_EventStartDate', true);
        if (!$date_a)
            return 1;
        if (!$date_b)
            return -1;
        return strtotime($date_a) - strtotime($date_b);
    });

    // Create events array indexed by date (Y-m-d format); multi-day events appear on every day from start to end
    $events_by_date = [];
    foreach ($all_events as $event) {
        $event_start = get_post_meta($event->ID, '_EventStartDate', true);
        if (!$event_start) {
            $event_start = get_post_meta($event->ID, '_EventStartDateUTC', true);
        }
        if (!$event_start) continue;
        $event_end = get_post_meta($event->ID, '_EventEndDate', true);
        if (!$event_end) {
            $event_end = get_post_meta($event->ID, '_EventEndDateUTC', true);
        }
        if (!$event_end) {
            $event_end = $event_start;
        }
        $start_date_only = substr($event_start, 0, 10);
        $end_date_only = substr($event_end, 0, 10);
        if ($end_date_only < $start_date_only) {
            $end_date_only = $start_date_only;
        }
        $current = $start_date_only;
        while ($current <= $end_date_only) {
            if (!isset($events_by_date[$current])) {
                $events_by_date[$current] = [];
            }
            $events_by_date[$current][] = $event;
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
        }
    }

    // Generate calendar HTML
    $calendar_html = generate_reminders_calendar_html($month, $year, $events_by_date, $category_filter);

    wp_send_json_success(['html' => $calendar_html]);
}

/**
 * Generate calendar HTML (extracted from my-reminders.php for reuse)
 */
function generate_reminders_calendar_html($month, $year, $events_by_date, $category_filter = 'all')
{
    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $days_in_month = date('t', $first_day);
    $day_of_week = date('w', $first_day);
    $start_of_week = get_option('start_of_week', 0);

    $day_of_week = ($day_of_week - $start_of_week + 7) % 7;

    $month_name = date('F Y', $first_day);
    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month < 1) {
        $prev_month = 12;
        $prev_year--;
    }
    $next_month = $month + 1;
    $next_year = $year;
    if ($next_month > 12) {
        $next_month = 1;
        $next_year++;
    }

    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    if ($start_of_week == 1) {
        $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    }

    ob_start();
    ?>
    <div class="reminders-calendar-view">
        <div class="calendar-header">
            <a href="#" class="calendar-nav prev-month" data-month="<?php echo $prev_month; ?>"
                data-year="<?php echo $prev_year; ?>">‹</a>
            <h3 class="calendar-month-year"><?php echo esc_html($month_name); ?></h3>
            <a href="#" class="calendar-nav next-month" data-month="<?php echo $next_month; ?>"
                data-year="<?php echo $next_year; ?>">›</a>
        </div>
        <table class="reminders-calendar-table">
            <thead>
                <tr>
                    <?php foreach ($weekdays as $day): ?>
                        <th><?php echo esc_html($day); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php
                    for ($i = 0; $i < $day_of_week; $i++) {
                        echo '<td class="calendar-day empty"></td>';
                    }

                    $current_day = 1;
                    while ($current_day <= $days_in_month) {
                        $date_key = sprintf('%04d-%02d-%02d', $year, $month, $current_day);
                        $is_today = ($date_key == date('Y-m-d'));
                        $has_events = isset($events_by_date[$date_key]) && !empty($events_by_date[$date_key]);


                        // Check if any events on this date are in the future (not past)
                        $has_future_events = false;
                        $today = date('Y-m-d');
                        if ($has_events) {
                            foreach ($events_by_date[$date_key] as $event) {
                                $event_date_utc = get_post_meta($event->ID, '_EventStartDateUTC', true);
                                if (!$event_date_utc) {
                                    $event_date_utc = get_post_meta($event->ID, '_EventStartDate', true);
                                }
                                if ($event_date_utc) {
                                    $event_date_only = substr($event_date_utc, 0, 10);
                                    // Only show pink dot if event date is today or in the future
                                    if ($event_date_only >= $today) {
                                        $has_future_events = true;
                                        break;
                                    }
                                }
                            }
                        }
                        

                        $day_class = 'calendar-day';
                        if ($is_today) {
                            $day_class .= ' today';
                        }
                        // if ($has_events) {
                        // Only add has-events class if there are future events (not past events)
                        if ($has_future_events) {
                            $day_class .= ' has-events';
                        }

                        echo '<td class="' . esc_attr($day_class) . '" data-date="' . esc_attr($date_key) . '">';
                        echo '<span class="day-number">' . esc_html($current_day) . '</span>';
                        // if ($has_events) {
                        //     $event_count = count($events_by_date[$date_key]);
                        //     echo '<span class="event-indicator" title="' . esc_attr($event_count . ' event(s)') . '">' . esc_html($event_count) . '</span>';
                        // }
                        echo '</td>';

                        $current_day++;
                        $day_of_week++;

                        if ($day_of_week == 7) {
                            echo '</tr><tr>';
                            $day_of_week = 0;
                        }
                    }

                    while ($day_of_week < 7) {
                        echo '<td class="calendar-day empty"></td>';
                        $day_of_week++;
                    }
                    ?>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler to load more reminder events with pagination
 */
add_action('wp_ajax_load_more_reminders_events', 'handle_load_more_reminders_events');
function handle_load_more_reminders_events()
{
    // Verify nonce
    if (!isset($_POST['nonce'])) {
        wp_send_json_error(['message' => 'Security token missing. Please refresh the page and try again.']);
        wp_die();
    }

    if (!wp_verify_nonce($_POST['nonce'], 'load_more_reminders_nonce')) {
        wp_send_json_error(['message' => 'Invalid security token. Please refresh the page and try again.']);
        wp_die();
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'User not logged in']);
        return;
    }

    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
    $category_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
    
    try {
        // Get all upcoming events (same logic as my-reminders.php)
        $today = date('Y-m-d');
        $far_future_date = date('Y-m-d', strtotime('+10 years'));
        
        // Check if function exists (it should be defined below)
        if (!function_exists('get_user_reminders_events')) {
            wp_send_json_error(['message' => 'Function not found. Please refresh the page.']);
            return;
        }
        
        // Use the get_user_reminders_events function
        $occasions_events = get_user_reminders_events($user_id, $today, $far_future_date, $category_filter);
        
        if (!is_array($occasions_events)) {
            $occasions_events = [];
        }
    
    // Filter to only upcoming events (no past events)
    $occasions_events_display = [];
    foreach ($occasions_events as $event) {
        $event_date_utc = get_post_meta($event->ID, '_EventStartDateUTC', true);
        if (!$event_date_utc) {
            $event_date_utc = get_post_meta($event->ID, '_EventStartDate', true);
        }
        if (!$event_date_utc) continue;
        
        // Calculate days until event
        $date_only = substr($event_date_utc, 0, 10);
        $today_obj = new DateTime($today);
        $event_obj = new DateTime($date_only);
        $diff = $today_obj->diff($event_obj);
        $days_until = intval($diff->format('%r%a'));
        
        // Only include future events (today and onwards)
        if ($days_until < 0) continue;
        $occasions_events_display[] = $event;
    }
    
    // Pagination
    $total_events = count($occasions_events_display);
    $offset = ($page - 1) * $per_page;
    $events_to_display = array_slice($occasions_events_display, $offset, $per_page);
    $has_more = ($offset + $per_page) < $total_events;
    
    // Generate HTML for events
    ob_start();
    foreach ($events_to_display as $event) {
        // Use same rendering logic as my-reminders.php
        $event_start = get_post_meta($event->ID, '_EventStartDate', true);
        if (!$event_start) {
            $event_start = get_post_meta($event->ID, '_EventStartDateUTC', true);
        }
        if (!$event_start) continue;
        
        $event_end = get_post_meta($event->ID, '_EventEndDate', true);
        if (!$event_end) {
            $event_end = get_post_meta($event->ID, '_EventEndDateUTC', true);
        }
        if (!$event_end) {
            $event_end = $event_start;
        }
        
        $event_date_utc = get_post_meta($event->ID, '_EventStartDateUTC', true);
        if (!$event_date_utc) {
            $event_date_utc = $event_start;
        }
        
        // Calculate days until
        $date_only = substr($event_date_utc, 0, 10);
        $today_obj = new DateTime(date('Y-m-d'));
        $event_obj = new DateTime($date_only);
        $diff = $today_obj->diff($event_obj);
        $days_until = intval($diff->format('%r%a'));
        
        // Format date
        $date_only_start = substr($event_start, 0, 10);
        $date_parts = explode('-', $date_only_start);
        if (count($date_parts) === 3) {
            $year = intval($date_parts[0]);
            $month = intval($date_parts[1]);
            $day = intval($date_parts[2]);
            $date_obj = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
            $day_name = $date_obj->format('l');
            $month_name = $date_obj->format('F');
            
            $suffix = '';
            if ($day % 10 == 1 && $day % 100 != 11) {
                $suffix = 'st';
            } elseif ($day % 10 == 2 && $day % 100 != 12) {
                $suffix = 'nd';
            } elseif ($day % 10 == 3 && $day % 100 != 13) {
                $suffix = 'rd';
            } else {
                $suffix = 'th';
            }
            $formatted_date = $day_name . ', ' . $month_name . ' ' . $day . $suffix;
        } else {
            $formatted_date = date('F j, Y', strtotime($event_start));
        }
        
        // Get event type
        $categories = wp_get_post_terms($event->ID, 'tribe_events_cat', ['fields' => 'all']);
        $event_type = ['name' => 'Event', 'slug' => 'my-events'];
        if (!empty($categories)) {
            $category = $categories[0];
            $event_type = ['name' => $category->name, 'slug' => $category->slug];
        } else {
            $title = strtolower($event->post_title);
            if (strpos($title, 'birthday') !== false || strpos($title, 'birth day') !== false) {
                $event_type = ['name' => 'Birthday', 'slug' => 'birthdays'];
            } elseif (strpos($title, 'anniversary') !== false || strpos($title, 'work anniversary') !== false) {
                $event_type = ['name' => 'Work Anniversary', 'slug' => 'work-anniversaries'];
            }
        }
        
        $icon_class = in_array($event_type['slug'], ['birthdays', 'birthday']) ? 'cake-icon' : 'confetti-icon';
        $title = $event->post_title;
        
        // Extract person name and event type from title
        $person_name = '';
        $event_type_display = '';
        if (preg_match('/(\d+)(st|nd|rd|th)\s+(Work\s+)?Anniversary/i', $title, $matches)) {
            $event_type_display = $matches[1] . $matches[2] . ' Work Anniversary';
            $person_name = trim(str_replace($matches[0], '', $title));
        } elseif (preg_match('/(\d+)(st|nd|rd|th)\s+Birthday/i', $title, $matches)) {
            $event_type_display = $matches[1] . $matches[2] . ' Birthday';
            $person_name = trim(str_replace($matches[0], '', $title));
        } else {
            $person_name = $title;
            $event_type_display = $event_type['name'];
        }
        ?>
        <div class="occasion-card" data-event-id="<?php echo esc_attr($event->ID); ?>">
            <div class="icon-waraper">

                <div class="occasion-icon <?php echo esc_attr($icon_class); ?>">

                    <?php if ($icon_class === 'cake-icon') : ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M14.0781 14.7902H1.91836C1.51262 14.7902 1.18066 14.497 1.18066 14.1388V10.4875C1.18066 9.82884 1.78721 9.29688 2.52903 9.29688H13.4675C14.2134 9.29688 14.8159 9.83245 14.8159 10.4875V14.1388C14.8159 14.5007 14.4879 14.7902 14.0781 14.7902ZM2.53297 9.73106C2.06166 9.73106 1.67642 10.0712 1.67642 10.4874V14.1387C1.67642 14.2581 1.78708 14.3558 1.92233 14.3558H14.0821C14.2173 14.3558 14.328 14.2581 14.328 14.1387V10.4874C14.328 10.0712 13.9428 9.73106 13.4714 9.73106H2.53297Z" fill="#ED018C" />
                            <path d="M12.2913 11.9848C12.2257 11.9848 12.1642 11.963 12.1192 11.9196C11.7995 11.6374 11.3773 11.4854 10.9306 11.4854C10.4839 11.4854 10.0577 11.641 9.74209 11.9196C9.69701 11.9594 9.63144 11.9848 9.56996 11.9848C9.50849 11.9848 9.44291 11.963 9.39783 11.9196C9.07817 11.6374 8.65602 11.4854 8.2093 11.4854C7.76258 11.4854 7.33635 11.641 7.02077 11.9196C6.97569 11.9594 6.91012 11.9848 6.84864 11.9848C6.78307 11.9848 6.7216 11.963 6.67651 11.9196C6.02078 11.3406 4.9511 11.3406 4.2953 11.9196C4.25022 11.9594 4.18465 11.9848 4.12317 11.9848C4.0576 11.9848 3.99612 11.963 3.95104 11.9196C3.30351 11.3479 2.2789 11.3334 1.61096 11.887C1.51259 11.9703 1.35687 11.963 1.26259 11.8762C1.16831 11.7893 1.17652 11.6518 1.27488 11.5686C2.07815 10.9027 3.28716 10.8774 4.12319 11.4745C4.91416 10.91 6.05762 10.91 6.84853 11.4745C7.23377 11.1995 7.70919 11.0511 8.21328 11.0511C8.71737 11.0511 9.19279 11.1995 9.57803 11.4745C9.96327 11.1995 10.4387 11.0511 10.9428 11.0511C11.4469 11.0511 11.9223 11.1995 12.3075 11.4745C12.9797 10.9968 13.9346 10.9136 14.7051 11.2791C14.824 11.337 14.869 11.4673 14.8035 11.5722C14.7379 11.6771 14.5904 11.717 14.4715 11.6591C13.824 11.3478 13.0043 11.4564 12.4838 11.916C12.4223 11.963 12.3569 11.9848 12.2913 11.9848Z" fill="#ED018C" />
                            <path d="M12.9509 9.72644H3.04922C2.64348 9.72644 2.31152 9.43332 2.31152 9.07506V6.29214C2.31152 5.63352 2.91807 5.10156 3.65988 5.10156H12.3362C13.0821 5.10156 13.6845 5.63714 13.6845 6.29214V9.07506C13.6886 9.43332 13.3567 9.72644 12.9509 9.72644ZM3.66005 5.53594C3.18874 5.53594 2.8035 5.87609 2.8035 6.29225V9.07518C2.8035 9.19459 2.91416 9.29231 3.04941 9.29231H12.9511C13.0864 9.29231 13.197 9.1946 13.197 9.07518V6.29225C13.197 5.87609 12.8118 5.53594 12.3405 5.53594H3.66005Z" fill="#ED018C" />
                            <path d="M11.5533 7.4019C11.4877 7.4019 11.4262 7.38019 11.3812 7.33676C11.1271 7.11241 10.7869 6.98575 10.4262 6.98575C10.0656 6.98575 9.72542 7.10879 9.47131 7.33676C9.42623 7.37657 9.36066 7.4019 9.29918 7.4019C9.23771 7.4019 9.17213 7.38019 9.12705 7.33676C8.87296 7.11241 8.5328 6.98575 8.17213 6.98575C7.81147 6.98575 7.47133 7.10879 7.21722 7.33676C7.17213 7.37657 7.10656 7.4019 7.04508 7.4019C6.98361 7.4019 6.91804 7.38019 6.87295 7.33676C6.34427 6.86995 5.4877 6.86995 4.96312 7.33676C4.91803 7.37657 4.85246 7.4019 4.79099 7.4019C4.72951 7.4019 4.66394 7.38019 4.61886 7.33676C4.09836 6.87717 3.27461 6.86632 2.74181 7.30781C2.64345 7.39105 2.48772 7.38381 2.39345 7.29696C2.29917 7.21011 2.30738 7.0726 2.40574 6.98935C3.07786 6.43206 4.08606 6.40311 4.79514 6.89165C5.45908 6.4393 6.3894 6.4393 7.05339 6.89165C7.37716 6.6709 7.7706 6.55149 8.18453 6.55149C8.59845 6.55149 8.99191 6.67091 9.31566 6.89165C9.63944 6.6709 10.0288 6.55149 10.4468 6.55149C10.8607 6.55149 11.2542 6.67091 11.5779 6.89165C12.1476 6.50445 12.9386 6.4393 13.582 6.7469C13.7009 6.8048 13.746 6.93507 13.6804 7.04001C13.6148 7.14496 13.4673 7.18476 13.3484 7.12686C12.8279 6.87716 12.1722 6.96402 11.7501 7.33313C11.6804 7.38018 11.6189 7.4019 11.5533 7.4019Z" fill="#ED018C" />
                            <path d="M15.2623 15.9981H0.737698C0.33196 15.9981 0 15.705 0 15.3467V15.0029C0 14.6447 0.33196 14.3516 0.737698 14.3516H15.2623C15.668 14.3516 16 14.6447 16 15.0029V15.3467C16 15.705 15.668 15.9981 15.2623 15.9981ZM0.737698 14.7858C0.602452 14.7858 0.491791 14.8835 0.491791 15.0029V15.3467C0.491791 15.4661 0.602446 15.5639 0.737698 15.5639H15.2623C15.3975 15.5639 15.5082 15.4662 15.5082 15.3467V15.0029C15.5082 14.8835 15.3976 14.7858 15.2623 14.7858H0.737698Z" fill="#ED018C" />
                            <path d="M3.89337 5.54755C3.75812 5.54755 3.64746 5.44984 3.64746 5.33042V3.06869C3.64746 2.94927 3.75812 2.85156 3.89337 2.85156H5.22123C5.35647 2.85156 5.46713 2.94927 5.46713 3.06869V5.32319C5.46713 5.44261 5.35648 5.54032 5.22123 5.54032C5.08598 5.54032 4.97532 5.44262 4.97532 5.32319V3.28584H4.13925V5.33042C4.13925 5.45346 4.02862 5.54755 3.89337 5.54755Z" fill="#ED018C" />
                            <path d="M4.94276 3.28221H4.17227C4.07801 3.28221 3.99194 3.23516 3.95095 3.15917C3.42227 2.19658 3.59849 0.900965 4.36899 0.0796135C4.41407 0.0289502 4.48374 0 4.55751 0C4.63128 0 4.70096 0.0289502 4.74604 0.0796135C5.51653 0.901076 5.69275 2.19293 5.16407 3.15917C5.12309 3.23517 5.03703 3.28221 4.94276 3.28221ZM4.3321 2.84795H4.78291C5.10669 2.14229 5.00832 1.24121 4.55751 0.58974C4.10258 1.24111 4.00832 2.14581 4.3321 2.84795Z" fill="#ED018C" />
                            <path d="M12.107 5.54755C11.9717 5.54755 11.8611 5.44984 11.8611 5.33042V3.28584H11.025V5.32319C11.025 5.44261 10.9144 5.54032 10.7791 5.54032C10.6439 5.54032 10.5332 5.44262 10.5332 5.32319V3.06869C10.5332 2.94927 10.6439 2.85156 10.7791 2.85156H12.107C12.2422 2.85156 12.3529 2.94927 12.3529 3.06869V5.33042C12.3529 5.45346 12.2422 5.54755 12.107 5.54755Z" fill="#ED018C" />
                            <path d="M11.8275 3.28221H11.057C10.9628 3.28221 10.8767 3.23516 10.8357 3.15917C10.307 2.19658 10.4833 0.900965 11.2538 0.0796135C11.2988 0.0289502 11.3685 0 11.4423 0C11.516 0 11.5857 0.0289502 11.6308 0.0796135C12.4013 0.901076 12.5775 2.19293 12.0488 3.15917C12.0079 3.23517 11.9218 3.28221 11.8275 3.28221ZM11.2169 2.84795H11.6677C11.9915 2.14229 11.8931 1.24121 11.4423 0.58974C10.9873 1.24111 10.8931 2.14581 11.2169 2.84795Z" fill="#ED018C" />
                            <path d="M8.66461 5.54033C8.52936 5.54033 8.4187 5.44262 8.4187 5.32319V3.28584H7.58263V5.32319C7.58263 5.44261 7.47198 5.54033 7.33673 5.54033C7.20148 5.54033 7.09082 5.44262 7.09082 5.32319V3.06869C7.09082 2.94927 7.20148 2.85156 7.33673 2.85156H8.66459C8.79983 2.85156 8.91049 2.94927 8.91049 3.06869V5.32319C8.91049 5.44623 8.79986 5.54033 8.66461 5.54033Z" fill="#ED018C" />
                            <path d="M8.38515 3.28221H7.61465C7.52039 3.28221 7.43432 3.23516 7.39334 3.15917C6.86466 2.19658 7.04088 0.900965 7.81137 0.0796135C7.85645 0.0289502 7.92613 0 7.9999 0C8.07367 0 8.14334 0.0289502 8.18842 0.0796135C8.95892 0.901076 9.13513 2.19293 8.60645 3.15917C8.56547 3.23517 8.47941 3.28221 8.38515 3.28221ZM7.77448 2.84795H8.22529C8.54907 2.14229 8.4507 1.24121 7.99989 0.58974C7.54496 1.24111 7.45071 2.14581 7.77448 2.84795Z" fill="#ED018C" />
                        </svg>
                    <?php else : ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="15" viewBox="0 0 16 15" fill="none">
                            <path d="M15.8768 10.0951C15.2168 9.70365 14.4387 9.52435 13.6606 9.58294C12.4425 8.547 10.404 8.86164 9.3314 9.12766C9.04704 8.78253 8.72831 8.43156 8.37771 8.07764C8.64644 7.9751 8.92892 7.87901 9.22579 7.78935L9.22516 7.78994C9.76887 7.62764 10.3251 7.50635 10.8894 7.42607C11.4369 7.34813 11.9918 7.32411 12.5456 7.35399C13.0893 7.38739 13.6268 7.48407 14.1455 7.64228C14.1711 7.6499 14.1968 7.65635 14.223 7.66103C14.4393 7.70322 14.6636 7.66338 14.848 7.5497C15.0317 7.43544 15.1598 7.25732 15.2029 7.05458C15.2461 6.85242 15.2011 6.64148 15.0773 6.47039C14.9542 6.2993 14.763 6.18093 14.5461 6.14285C13.8918 6.0116 13.2218 5.9571 12.5531 5.98055C11.905 6.00691 11.2613 6.09656 10.6332 6.24833C10.0195 6.39657 9.41893 6.58993 8.83765 6.82666C8.43204 6.99308 8.0352 7.17882 7.65022 7.38449C7.22336 6.99836 6.79464 6.64268 6.37529 6.33155C6.99899 4.75008 6.82339 3.85412 6.48278 3.36316C6.48903 3.35261 6.49528 3.34148 6.50153 3.33093C7.26211 1.98384 5.56221 0.156287 5.48972 0.0794857C5.39785 -0.0177817 5.23973 -0.0271568 5.13662 0.0589774C5.03287 0.144525 5.02287 0.292771 5.11474 0.390032C5.13099 0.405266 6.55029 1.934 6.11718 2.99452C5.73346 2.72734 5.24098 2.63651 4.77788 2.74843C4.48728 2.82343 4.29791 2.98105 4.25854 3.17851C4.18417 3.55001 4.46915 3.94141 4.92162 4.08907C5.36284 4.22735 5.84969 4.10606 6.15967 3.78086C6.39592 4.28184 6.33091 5.05997 5.96405 6.03854C5.6997 5.85749 5.44096 5.69049 5.19597 5.55396C4.25977 5.03071 3.61981 4.92055 3.29288 5.227C3.21226 5.30552 3.15789 5.40454 3.13602 5.51177L0.0330012 14.1187C-0.0357451 14.3244 0.00425251 14.5488 0.140496 14.7228C0.276737 14.8969 0.493595 14.9994 0.723581 15C0.799202 15 0.874198 14.9889 0.946064 14.9678L10.133 12.0544C10.1374 12.0533 10.1399 12.0497 10.1443 12.0486V12.048C10.2487 12.0275 10.3455 11.9806 10.4236 11.912C10.7505 11.6056 10.633 11.005 10.0749 10.1273C9.9543 9.93748 9.81493 9.73943 9.6612 9.5361C10.5811 9.33102 12.0391 9.14703 13.0253 9.72068L13.0259 9.7201C12.7072 9.82205 12.4247 10.0037 12.2085 10.2463C11.9522 10.5551 11.9435 10.9014 12.1847 11.1275C12.3729 11.2916 12.6235 11.3795 12.8803 11.3701C13.2484 11.3701 13.6034 11.2389 13.8728 11.0039C14.1571 10.7525 14.2265 10.3529 14.0409 10.0306C14.6021 10.057 15.1465 10.2181 15.622 10.4988C15.7402 10.5598 15.8889 10.5217 15.9577 10.4127C16.0264 10.3031 15.9908 10.1631 15.8764 10.0951L15.8768 10.0951ZM5.08621 3.64678C4.87622 3.57822 4.72248 3.40361 4.74436 3.27939L4.74498 3.2788C4.86435 3.19501 5.01309 3.15576 5.16184 3.16923C5.34183 3.16923 5.51869 3.20966 5.67806 3.28818C5.74118 3.31865 5.80055 3.35556 5.85555 3.39717C5.62806 3.64267 5.35432 3.73408 5.08621 3.64678ZM9.03781 7.25733V7.25674C9.59591 7.02998 10.1715 6.84423 10.7603 6.70185C11.3552 6.55888 11.9652 6.47392 12.5789 6.44989C12.6776 6.44521 12.7776 6.44228 12.8801 6.44228C13.4076 6.44755 13.9325 6.50204 14.4482 6.60341C14.6244 6.63622 14.7394 6.79736 14.7038 6.96259C14.6688 7.12783 14.4975 7.23505 14.3213 7.20225L14.26 7.18643C13.712 7.02118 13.1445 6.92041 12.5696 6.88642C11.9827 6.85537 11.394 6.88115 10.8129 6.96318C10.2236 7.04756 9.64171 7.17471 9.07363 7.34464C8.75365 7.44073 8.44868 7.54444 8.15806 7.65636C8.43305 7.51807 8.7249 7.38565 9.03863 7.25674L9.03781 7.25733ZM3.32695 6.44989C3.7488 7.35166 4.74936 8.52818 5.82616 9.53784C6.0149 9.7148 6.20988 9.88941 6.40675 10.0593C4.58366 9.23198 3.47941 8.24701 2.90504 7.62L3.32695 6.44989ZM0.791421 14.5218C0.712676 14.5447 0.627063 14.5253 0.568314 14.4714C0.509568 14.4175 0.486444 14.3372 0.508944 14.2634L1.17703 12.4101C1.87699 12.8912 2.61445 13.3224 3.38314 13.7004L0.791421 14.5218ZM4.04179 13.4911C3.09309 13.0558 2.19006 12.5384 1.34387 11.9454L1.55198 11.3723C2.5813 12.1082 3.6981 12.7299 4.87995 13.2256L4.04179 13.4911ZM5.58795 13.0007H5.58858C4.2024 12.471 2.90122 11.7649 1.72129 10.9019L2.31876 9.24479C3.40869 10.2432 5.19987 11.4597 7.9597 12.2489L5.58795 13.0007ZM8.79464 11.9841C5.57227 11.1972 3.60439 9.81311 2.49758 8.74914L2.72006 8.13155C3.53876 8.96302 5.10552 10.2152 7.70168 11.0701C8.14166 11.3877 8.61726 11.6596 9.11973 11.881L8.79464 11.9841ZM10.0708 11.5804C10.0571 11.5915 10.0415 11.5997 10.0252 11.6044C10.0077 11.605 9.9902 11.6067 9.97333 11.6103L9.94833 11.6185C9.65648 11.6343 8.95338 11.3536 7.99661 10.6909H7.99598C7.35228 10.2391 6.74543 9.74343 6.18041 9.2073C4.31732 7.46112 3.57688 6.0913 3.60872 5.66995L3.6156 5.65061C3.61935 5.63479 3.62185 5.61897 3.62185 5.60315C3.62747 5.58674 3.63622 5.57209 3.64809 5.5592C3.68809 5.53283 3.73746 5.5217 3.78621 5.52697C3.96495 5.52697 4.32243 5.61135 4.94115 5.95706C5.23238 6.12464 5.51361 6.30569 5.78547 6.49965C5.75797 6.60922 5.81922 6.72173 5.93046 6.76508C5.96046 6.7768 5.99233 6.78324 6.02483 6.78324C6.0642 6.7809 6.10295 6.77035 6.1367 6.7516C6.48855 7.01586 6.84728 7.31586 7.20663 7.63637C7.19476 7.6434 7.18226 7.65043 7.17039 7.65746C6.99977 7.76059 6.9229 7.9563 6.9804 8.13794C7.03789 8.32016 7.21664 8.44497 7.41912 8.44439C7.49474 8.4438 7.56911 8.42681 7.63724 8.39517C7.71786 8.3565 7.8116 8.32193 7.8966 8.28443C8.23095 8.61548 8.54282 8.94655 8.81842 9.26999C8.71092 9.30339 8.64217 9.32682 8.62531 9.33327H8.62468C8.49594 9.37897 8.43095 9.51432 8.47969 9.63561C8.52844 9.7569 8.6728 9.81784 8.80217 9.77214C8.80779 9.7698 8.93653 9.72585 9.14152 9.66726C9.33027 9.90867 9.50462 10.1454 9.64649 10.3681C10.1727 11.1954 10.1308 11.5235 10.0708 11.5804ZM13.5194 10.6722C13.2588 10.9153 12.737 10.9821 12.5389 10.7964C12.4589 10.7214 12.5682 10.5767 12.6039 10.5339C12.8345 10.2966 13.1426 10.1366 13.4794 10.0798C13.6826 10.3077 13.6957 10.5069 13.5194 10.6722ZM9.07154 5.30251C9.16091 5.39685 9.28778 5.45427 9.42214 5.46131C9.43214 5.46131 9.44214 5.46248 9.45214 5.46248V5.46189C9.57651 5.46189 9.69712 5.41912 9.79025 5.34119C10.1277 5.06814 10.4396 4.76872 10.7214 4.44469C11.0071 4.11831 11.262 3.76968 11.4826 3.40171C11.7083 3.03491 11.9032 2.65228 12.0664 2.25735C12.2388 1.84894 12.3726 1.42766 12.4645 0.996991C12.4738 0.953632 12.4788 0.909685 12.4807 0.865153C12.497 0.403431 12.1114 0.0167107 11.6189 0.000885505C11.382 -0.00848963 11.1508 0.072957 10.9789 0.225889C10.8021 0.380571 10.7002 0.596214 10.6958 0.822972C10.6771 1.15989 10.6271 1.49506 10.5471 1.82377C10.4658 2.17007 10.3571 2.50933 10.2215 2.8398C10.0883 3.17321 9.92398 3.49489 9.73025 3.80134C9.53463 4.11364 9.30965 4.40897 9.05779 4.68377C8.89843 4.86482 8.90405 5.1279 9.07154 5.30251ZM10.1627 4.03453C10.3702 3.70699 10.5464 3.36245 10.6889 3.00561C10.8333 2.65403 10.9496 2.29251 11.0352 1.92396C11.1227 1.56242 11.1764 1.19504 11.1952 0.824725C11.2027 0.626093 11.3764 0.469055 11.5876 0.46847H11.6014C11.8183 0.475502 11.9876 0.646013 11.9814 0.848756C11.9801 0.868678 11.9776 0.888599 11.9739 0.907936C11.887 1.31165 11.7614 1.70658 11.5995 2.0892C11.4452 2.46245 11.2608 2.82339 11.0483 3.17027C10.8402 3.51656 10.6002 3.84469 10.3315 4.15115C10.0727 4.45349 9.77901 4.72773 9.45589 4.96912C9.72025 4.67732 9.9571 4.36502 10.1627 4.03453ZM8.15852 2.02765C8.4985 2.02765 8.80474 1.83605 8.93473 1.54132C9.06472 1.24718 8.99285 0.908487 8.75225 0.682898C8.51226 0.457895 8.15103 0.390514 7.83729 0.512391C7.52294 0.634268 7.31857 0.921387 7.31857 1.24014C7.3192 1.67491 7.6948 2.02707 8.15852 2.02765ZM8.15852 0.921387C8.29601 0.921387 8.42038 0.998731 8.47287 1.11826C8.52537 1.23721 8.49599 1.37432 8.39913 1.46573C8.30163 1.55714 8.15539 1.58409 8.0279 1.53487C7.90103 1.48565 7.81854 1.36904 7.81854 1.24015C7.81854 1.06377 7.97103 0.921387 8.15852 0.921387ZM14.2014 8.01724C13.9251 8.01724 13.6764 8.1731 13.5708 8.41216C13.4652 8.65124 13.5239 8.92663 13.7189 9.10943C13.9145 9.29226 14.2076 9.34732 14.4626 9.24772C14.7176 9.14869 14.8838 8.91549 14.8838 8.6565C14.8832 8.30375 14.5782 8.01782 14.2014 8.01724ZM14.2014 8.82818C14.1276 8.82818 14.0607 8.78658 14.0326 8.72271C14.0045 8.65884 14.0201 8.58501 14.072 8.53638C14.1239 8.48716 14.2026 8.47251 14.2707 8.49888C14.3389 8.52524 14.3832 8.58735 14.3832 8.6565C14.3832 8.75084 14.302 8.82759 14.2014 8.82818ZM12.4277 4.04161C12.0652 4.04161 11.739 4.24669 11.6003 4.56017C11.4615 4.87424 11.5384 5.23576 11.7946 5.47601C12.0509 5.71625 12.4358 5.78773 12.7708 5.65823C13.1058 5.52815 13.3239 5.22169 13.3239 4.88185C13.3239 4.41777 12.9227 4.04161 12.4277 4.04161ZM12.4277 5.25159C12.2677 5.25159 12.124 5.16135 12.0627 5.02308C12.0015 4.88421 12.0352 4.72483 12.1484 4.61877C12.2615 4.51331 12.4315 4.48108 12.5796 4.5385C12.7271 4.59592 12.8233 4.73128 12.8233 4.88128C12.8233 5.08578 12.6464 5.25159 12.4277 5.25159ZM13.2583 2.99106L13.417 3.0309V3.03149C13.7601 3.11879 14.0276 3.37016 14.1214 3.69126L14.1638 3.8395V3.84009C14.2045 3.98247 14.3426 4.0815 14.5001 4.0815C14.6569 4.0815 14.7951 3.98247 14.8357 3.84009L14.8794 3.69184C14.9726 3.37075 15.2407 3.11937 15.5831 3.03207L15.7413 2.99223H15.7419C15.8944 2.95414 16 2.82465 16 2.67698C16 2.52932 15.8944 2.39982 15.7419 2.36174L15.5838 2.3219H15.5831C15.2407 2.23459 14.9726 1.98322 14.8794 1.66213L14.8369 1.5133C14.7963 1.37033 14.6582 1.2713 14.5007 1.2713C14.3432 1.2713 14.2051 1.37032 14.1645 1.5133L14.122 1.66213C14.0289 1.98381 13.7608 2.23518 13.417 2.3219L13.2589 2.36174V2.36233C13.107 2.40041 13.0008 2.52932 13.0008 2.67698C13.0008 2.82523 13.107 2.95414 13.2589 2.99223L13.2583 2.99106ZM14.5001 2.04124C14.6463 2.31663 14.8844 2.53988 15.1782 2.67641C14.8844 2.81352 14.6463 3.03676 14.5007 3.31217C14.3545 3.03678 14.1164 2.81353 13.8226 2.67641C14.1163 2.53989 14.3538 2.31665 14.5001 2.04124ZM8.56171 3.31275C8.52984 3.34732 8.51672 3.39361 8.52734 3.43814L8.59296 3.7194H8.59234C8.63609 3.90807 8.58296 4.10436 8.4486 4.25085L8.24736 4.46999C8.21611 4.50456 8.20361 4.55085 8.21361 4.59597L8.27923 4.87956C8.32173 5.06765 8.26861 5.26395 8.13549 5.40985L7.80114 5.77314C7.71115 5.87158 7.55365 5.88271 7.44867 5.79834C7.3443 5.71396 7.33243 5.56572 7.42242 5.46787L7.75677 5.10399V5.10341C7.78802 5.06942 7.80114 5.02313 7.79052 4.9786L7.72489 4.69384C7.68177 4.50517 7.73552 4.30946 7.86864 4.16296L8.0705 3.94383C8.10174 3.90926 8.11424 3.86297 8.10425 3.81843L8.03862 3.53718V3.53777C7.9955 3.34909 8.04862 3.15222 8.18299 3.00572L8.51547 2.64419H8.51484C8.60546 2.54575 8.76295 2.5352 8.86794 2.61958C8.97231 2.70396 8.98418 2.8522 8.89419 2.95063L8.56171 3.31275Z" fill="#ED018C" />
                        </svg>
                    <?php endif; ?>

                </div>

                <div class="occasion-countdown">
                    <?php if ($days_until < 0) : ?>
                        <?php echo esc_html(abs($days_until)); ?> days ago
                    <?php elseif ($days_until == 0) : ?>
                        Today
                    <?php else : ?>
                        in <?php echo esc_html($days_until); ?> days
                    <?php endif; ?>
                </div>

            </div>

            <div class="occasion-content">
                <div class="occasion-date"><?php echo esc_html($formatted_date); ?></div>

                <div class="occasion-title">
                    <?php if (!empty($person_name) && $person_name !== $title) : ?>
                        <?php echo esc_html($person_name); ?>
                        <strong class="occasion-type"><?php echo esc_html($event_type_display); ?></strong>
                    <?php else : ?>
                        <strong class="occasion-type"><?php echo esc_html($title); ?></strong>
                    <?php endif; ?>
                </div>

                <a href="<?php echo esc_url(site_url('/shop')); ?>" class="shop-now-btn btn btn-white-p2">Shop now</a>
            </div>
        </div>
        <?php
    }
    $html = ob_get_clean();
    
    wp_send_json_success([
        'html' => $html,
        'has_more' => $has_more,
        'total' => $total_events,
        'loaded' => $offset + count($events_to_display)
    ]);
    
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'An error occurred. Please try again.']);
        wp_die();
    } catch (Error $e) {
        wp_send_json_error(['message' => 'A fatal error occurred. Please try again.']);
        wp_die();
    }
}

/**
 * Helper function to get user events for reminders (replicates logic from my-reminders.php)
 */
function get_user_reminders_events($user_id, $date_from = null, $date_to = null, $category_filter = null) {
    // Query 1: Events with _gc_user_id matching current user (only this user's events)
    $args1 = [
        'post_type'      => 'tribe_events',
        'posts_per_page' => -1,
        'meta_key'       => '_EventStartDateUTC',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query' => [
            [
                'key'   => '_gc_user_id',
                'value' => $user_id,
            ],
        ]
    ];
    
    // Query 2: Events created by current user (post_author)
    $args2 = [
        'post_type'      => 'tribe_events',
        'posts_per_page' => -1,
        'author'         => $user_id,
        'meta_key'       => '_EventStartDateUTC',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [],
    ];

    // Query 3: Public holidays & religious holidays – show for all users regardless of author
    $args3 = [
        'post_type'        => 'tribe_events',
        'posts_per_page'   => -1,
        'suppress_filters'  => true, // Prevent plugins from restricting to current user so all users see these events
        'meta_key'         => '_EventStartDateUTC',
        'orderby'          => 'meta_value',
        'order'            => 'ASC',
        'tax_query'        => [
            [
                'taxonomy' => 'tribe_events_cat',
                'field'    => 'slug',
                'terms'    => ['public-holidays', 'religious-holiday'],
            ],
        ],
        'meta_query'     => [],
    ];
    
    // Add date range filter if provided
    if ($date_from && $date_to) {
        $date_query = [
            'key'     => '_EventStartDateUTC',
            'value'   => [$date_from, $date_to],
            'compare' => 'BETWEEN',
            'type'    => 'DATE',
        ];
        $args1['meta_query'][] = $date_query;
        $args2['meta_query'][] = $date_query;
        $args3['meta_query'][] = $date_query;
    }
    
    // Get events from all three queries
    $events1 = get_posts($args1);
    $events2 = get_posts($args2);
    $events3 = get_posts($args3);
    
    // Merge and remove duplicates (user's events + public holidays for all users)
    $event_ids = [];
    $all_events = [];
    foreach (array_merge($events1, $events2, $events3) as $event) {
        if (!in_array($event->ID, $event_ids)) {
            $event_ids[] = $event->ID;
            $all_events[] = $event;
        }
    }
    
    // Apply category filter
    if ($category_filter && $category_filter !== 'all') {
        $filtered_events = [];
        foreach ($all_events as $event) {
            $matches_filter = false;
            
            $categories = wp_get_post_terms($event->ID, 'tribe_events_cat', ['fields' => 'all']);
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    if ($category->slug === $category_filter) {
                        $matches_filter = true;
                        break;
                    }
                }
            }
            
            if (!$matches_filter) {
                $title = strtolower($event->post_title);
                if ($category_filter === 'birthdays' && (strpos($title, 'birthday') !== false || strpos($title, 'birth day') !== false)) {
                    $matches_filter = true;
                } elseif ($category_filter === 'work-anniversaries' && (strpos($title, 'anniversary') !== false || strpos($title, 'work anniversary') !== false)) {
                    $matches_filter = true;
                } elseif ($category_filter === 'public-holidays' && (strpos($title, 'holiday') !== false || strpos($title, 'public holiday') !== false)) {
                    $matches_filter = true;
                } elseif ($category_filter === 'my-events' && $event->post_author == $user_id) {
                    $matches_filter = true;
                }
            }
            
            if ($matches_filter) {
                $filtered_events[] = $event;
            }
        }
        $all_events = $filtered_events;
    }
    
    // Sort by event date
    usort($all_events, function($a, $b) {
        $date_a = get_post_meta($a->ID, '_EventStartDateUTC', true);
        $date_b = get_post_meta($b->ID, '_EventStartDateUTC', true);
        if (!$date_a) return 1;
        if (!$date_b) return -1;
        return strtotime($date_a) - strtotime($date_b);
    });
    
    return $all_events;
}

/**
 * Helper function to calculate days until event
 * (Moved from my-reminders.php so it can be shared with the admin-side
 * Contact List & Events reminders section.)
 */
if (!function_exists('get_days_until_event')) {
function get_days_until_event($event_date)
{
    // Extract date only (YYYY-MM-DD) to avoid timezone issues
    $date_only = substr($event_date, 0, 10);

    // Get today's date in Y-m-d format
    $today = date('Y-m-d');

    // Create DateTime objects for comparison
    $today_obj = new DateTime($today);
    $event_obj = new DateTime($date_only);

    // Calculate difference in days
    $diff = $today_obj->diff($event_obj);
    $days = intval($diff->format('%r%a')); // %r gives sign, %a gives absolute days

    return $days;
}
}

/**
 * Helper function to format date nicely (e.g., "Monday, March 1st")
 * (Moved from my-reminders.php — see get_days_until_event() above.)
 */
if (!function_exists('format_event_date')) {
function format_event_date($event_date)
{
    // Extract date components directly from the date string to avoid timezone issues
    // The date is stored as 'YYYY-MM-DD HH:MM:SS' in UTC
    // Extract just the date part (YYYY-MM-DD) to avoid timezone conversion
    $date_only = substr($event_date, 0, 10); // Get 'YYYY-MM-DD' part

    // Parse the date components directly
    $date_parts = explode('-', $date_only);
    if (count($date_parts) !== 3) {
        // Fallback to strtotime if format is unexpected
        $timestamp = strtotime($event_date);
        $day_name = date('l', $timestamp);
        $month_name = date('F', $timestamp);
        $day = date('j', $timestamp);
    } else {
        $year = intval($date_parts[0]);
        $month = intval($date_parts[1]);
        $day = intval($date_parts[2]);

        // Create a DateTime object with just the date (no time) to get day name
        $date_obj = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        $day_name = $date_obj->format('l');
        $month_name = $date_obj->format('F');
    }

    // Add ordinal suffix
    $suffix = '';
    if ($day % 10 == 1 && $day % 100 != 11) {
        $suffix = 'st';
    } elseif ($day % 10 == 2 && $day % 100 != 12) {
        $suffix = 'nd';
    } elseif ($day % 10 == 3 && $day % 100 != 13) {
        $suffix = 'rd';
    } else {
        $suffix = 'th';
    }

    return $day_name . ', ' . $month_name . ' ' . $day . $suffix;
}
}

/**
 * Helper function to format event date range (start and end as defined on the event)
 * Returns e.g. "Monday, March 1st" for single-day, or "Monday, March 1st – Friday, March 5th" for multi-day.
 * (Moved from my-reminders.php — see get_days_until_event() above.)
 */
if (!function_exists('format_event_date_range')) {
function format_event_date_range($event_start, $event_end = null)
{
    if (empty($event_end) || substr($event_start, 0, 10) === substr($event_end, 0, 10)) {
        return format_event_date($event_start);
    }
    return format_event_date($event_start) . ' – ' . format_event_date($event_end);
}
}

/**
 * Helper function to get event type/category
 * (Moved from my-reminders.php — see get_days_until_event() above.)
 */
if (!function_exists('get_event_type')) {
function get_event_type($event)
{
    $categories = wp_get_post_terms($event->ID, 'tribe_events_cat', ['fields' => 'all']);

    if (!empty($categories)) {
        $category = $categories[0];
        return [
            'name' => $category->name,
            'slug' => $category->slug
        ];
    }

    // Try to detect from title
    $title = strtolower($event->post_title);
    if (strpos($title, 'birthday') !== false || strpos($title, 'birth day') !== false) {
        return ['name' => 'Birthday', 'slug' => 'birthdays'];
    }
    if (strpos($title, 'anniversary') !== false || strpos($title, 'work anniversary') !== false) {
        return ['name' => 'Work Anniversary', 'slug' => 'work-anniversaries'];
    }
    if (strpos($title, 'holiday') !== false || strpos($title, 'public holiday') !== false) {
        return ['name' => 'Public Holiday', 'slug' => 'public-holidays'];
    }

    return ['name' => 'Event', 'slug' => 'my-events'];
}
}

/**
 * Helper function to get event icon class based on type
 * (Moved from my-reminders.php — see get_days_until_event() above.)
 */
if (!function_exists('get_event_icon')) {
function get_event_icon($event_type_slug)
{
    if (in_array($event_type_slug, ['birthdays', 'birthday'])) {
        return 'cake-icon'; // Blue cake icon for birthdays
    }
    return 'confetti-icon'; // Pink confetti icon for other events
}
}



// If shop page than redirect to the home page
add_action( 'template_redirect', 'redirect_shop_to_home' );
function redirect_shop_to_home()
{
    // Check if WooCommerce is active and if we are on the shop page
    if (class_exists('WooCommerce') && is_shop()) {
        wp_redirect(home_url());
        exit;
    }
}

add_filter('render_block', function ($block_content, $block) {

    if (
        isset($block['blockName']) &&
        $block['blockName'] === 'woocommerce/checkout' &&
        function_exists('is_checkout') &&
        is_checkout() &&
        ! is_order_received_page()
    ) {

        $heading = '<div class="checkout-page-title"> 
                        <h3 class="custom-checkout-heading">Checkout</h3>
                        <p>Complete your gift card purchase</p>
                    </div>';

        $block_content = $heading . $block_content;
    }

    return $block_content;
}, 10, 2);




// 19 JAN Column added in the list of the product. Start

/* Add custom columns */
add_filter('manage_edit-product_columns', function ($columns) {
    $columns['_is_blackhawk_product'] = 'Is Blackhawk Product';
    $columns['supplier'] = 'Supplier';
    return $columns;
});

/* Render column values */
add_action('manage_product_posts_custom_column', function ($column, $post_id) {

    /* Blackhawk Product column */
    if ($column === '_is_blackhawk_product') {

        $value = get_post_meta($post_id, '_is_blackhawk_product', true);

        if (!empty($value) && strpos($value, 'yes_') === 0) {
            echo '<strong style="color:green;">Yes</strong>';
        } else {
            echo '<span style="color:#999;">No</span>';
        }
    }

    /* Supplier column (ACF User field) */
    if ($column === 'supplier') {

        $supplier = get_field('supplier', $post_id);

        if (!empty($supplier)) {

            // ACF User field can return ID or object
            if (is_numeric($supplier)) {
                $user = get_user_by('id', $supplier);
            } elseif (is_object($supplier)) {
                $user = $supplier;
            }

            if (!empty($user)) {
                echo esc_html($user->display_name);
            } else {
                echo '<span style="color:#999;">—</span>';
            }

        } else {
            echo '<span style="color:#999;">—</span>';
        }
    }

}, 10, 2);

//  Column styling 
add_action('admin_head', function () {
    ?>
    <style>
        .wp-list-table .column-_is_blackhawk_product {
            width: 140px;
            text-align: center;
            white-space: nowrap;
        }

        .wp-list-table .column-supplier {
            width: 180px;
            white-space: nowrap;
        }
    </style>
    <?php
});


/* Add Blackhawk Product filter dropdown */
add_action('restrict_manage_posts', function ($post_type) {

    if ($post_type !== 'product') {
        return;
    }

    $selected = $_GET['_is_blackhawk_product_filter'] ?? '';

    ?>
    <select name="_is_blackhawk_product_filter">
        <option value="">Blackhawk Product</option>
        <option value="yes" <?php selected($selected, 'yes'); ?>>Yes</option>
        <option value="no" <?php selected($selected, 'no'); ?>>No</option>
    </select>
    <?php
});

/* Apply Blackhawk Product filter */
add_action('pre_get_posts', function ($query) {

    if (
        !is_admin() ||
        !$query->is_main_query() ||
        empty($_GET['_is_blackhawk_product_filter'])
    ) {
        return;
    }

    if ($query->get('post_type') !== 'product') {
        return;
    }

    $filter = $_GET['_is_blackhawk_product_filter'];

    if ($filter === 'yes') {
        $query->set('meta_query', [
            [
                'key'     => '_is_blackhawk_product',
                'value'   => 'yes_',
                'compare' => 'LIKE',
            ]
        ]);
    }

    if ($filter === 'no') {
        $query->set('meta_query', [
            [
                'key'     => '_is_blackhawk_product',
                'compare' => 'NOT EXISTS',
            ]
        ]);
    }
});



// End



// Shorten the link code 21 jan 26

// add_filter('gc_short_url_domain', function() { 
//     return 'https://gc.ly'; // or your shorter domain
// });


/**
 * Load Custom Module: Admin Card Portal
 * Handles the custom admin screen for managing Gift Card details.
 */
$gcp_admin_card_portal_path = get_template_directory() . '/inc/modules/admin-card-portal/class-admin-card-portal.php';

if ( file_exists( $gcp_admin_card_portal_path ) ) {
    require_once $gcp_admin_card_portal_path;
}
add_filter( 'woocommerce_get_breadcrumb', function( $crumbs ) {
    $shop_url        = trailingslashit( get_permalink( wc_get_page_id( 'shop' ) ) );
    $all_gifts_url   = trailingslashit( home_url( '/product-category/all-gift-cards/' ) );
    $brands_url      = home_url( '/brands/' );
    foreach ( $crumbs as &$crumb ) {
        if ( ! isset( $crumb[1] ) ) continue;
        $crumb_url = trailingslashit( $crumb[1] );
        if ( $crumb_url === $shop_url || $crumb_url === $all_gifts_url ) {
            $crumb[1] = $brands_url;
        }
    }
    return $crumbs;
} );

add_filter( 'woocommerce_breadcrumb_defaults', 'custom_woocommerce_breadcrumb_separator' );

function custom_woocommerce_breadcrumb_separator( $defaults ) {

    $defaults['delimiter'] = '<span class="breadcrumb-separator">
        <svg width="21" height="24" viewBox="0 0 21 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7.9502 13.4746C7.9502 14.1764 8.20085 14.778 8.70215 15.2793C9.20345 15.776 9.80501 16.0244 10.5068 16.0244C11.2087 16.0244 11.8102 15.776 12.3115 15.2793C12.8083 14.778 13.0566 14.1764 13.0566 13.4746C13.0566 12.7728 12.8083 12.1735 12.3115 11.6768C11.8102 11.1755 11.2087 10.9248 10.5068 10.9248C9.80501 10.9248 9.20345 11.1755 8.70215 11.6768C8.20085 12.1735 7.9502 12.7728 7.9502 13.4746Z" fill="black" fill-opacity="0.6"></path>
        </svg>
    </span>';

    $defaults['wrap_before'] = '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb">
        <span class="breacrum-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <rect x="24" width="24" height="24" rx="12" transform="rotate(90 24 0)" fill="white"></rect>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M5.45703 12.0012C5.45703 11.8083 5.53367 11.6233 5.67007 11.4869C5.80647 11.3505 5.99147 11.2738 6.18438 11.2738L19.2765 11.2738C19.4694 11.2738 19.6544 11.3505 19.7908 11.4869C19.9272 11.6233 20.0039 12.0012 20.0039 12.0012C20.0039 12.1941 19.9272 12.3791 19.7908 12.5155C19.6544 12.6519 19.4694 12.7285 19.2765 12.7285L6.18438 12.7285C5.99147 12.7285 5.80647 12.6519 5.67007 12.5155C5.53367 12.3791 5.45703 12.1941 5.45703 12.0012Z" fill="black" fill-opacity="0.6"></path>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M4.21369 12.5154C4.14595 12.4479 4.09221 12.3676 4.05554 12.2792C4.01888 12.1909 4 12.0961 4 12.0005C4 11.9048 4.01888 11.8101 4.05554 11.7217C4.09221 11.6333 4.14595 11.5531 4.21369 11.4855L8.57773 7.12146C8.71431 6.98488 8.89955 6.90815 9.09269 6.90815C9.28584 6.90815 9.47107 6.98488 9.60765 7.12145C9.74423 7.25803 9.82095 7.44327 9.82095 7.63641C9.82095 7.82956 9.74423 8.0148 9.60765 8.15137L5.75711 12.0005L9.60765 15.8496C9.74423 15.9861 9.82096 16.1714 9.82096 16.3645C9.82096 16.5577 9.74423 16.7429 9.60765 16.8795C9.47108 17.016 9.28584 17.0928 9.09269 17.0928C8.89955 17.0928 8.71431 17.016 8.57774 16.8795L4.21369 12.5154Z" fill="black" fill-opacity="0.6"></path>
            </svg>
        </span>';

    return $defaults;
}

// Remove default combined output
remove_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review', 10 );
remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );

// Create custom hooks
add_action( 'woocommerce_checkout_order_review_table_only', 'woocommerce_order_review', 10 );
add_action( 'woocommerce_checkout_order_review_payment_only', 'woocommerce_checkout_payment', 20 );

// add class to the tag before UL START
add_action( 'woocommerce_before_shop_loop', 'add_custom_wrapper_before_products', 5 );
function add_custom_wrapper_before_products() {

    if ( is_tax( 'product_tag' ) ) {
        echo '<div class="custom-product-tag-wrapper">';
    }
}
add_action( 'woocommerce_after_shop_loop', 'close_custom_wrapper_after_products', 50 );
function close_custom_wrapper_after_products() {

    if ( is_tax( 'product_tag' ) ) {
        echo '</div>';
    }
// add class to the tag before UL END
}

/***********************************************/
// Cart Shop Button Redirection URL Change Hook//
/***********************************************/
add_filter( 'woocommerce_return_to_shop_redirect', 'custom_return_to_shop_url' );
function custom_return_to_shop_url() {
    return home_url(); // your custom URL
}


/**
 * Customize WooCommerce order item meta field labels for better readability
 */
add_filter('woocommerce_order_item_display_meta_key', 'customize_order_item_meta_labels', 10, 3);
function customize_order_item_meta_labels($display_key, $meta, $item) {
    // Map technical meta keys to user-friendly labels
    $label_map = array(
        '_delivery_method'      => 'Delivery Method',
        '_delivery_timing'      => 'Delivery Timing',
        'schedule_time'         => 'Scheduled Time',
        '_delivery_email'       => 'Delivery Email',
        '_recipient_phone'      => 'Recipient Phone',
        'mobile_number'         => 'Mobile Number',
        '_recipient_name'       => 'Recipient Name',
        '_recipient_email'      => 'Recipient Email',
        '_gift_card_image'      => 'Gift Card Image',
        '_gift_card_price'      => 'Gift Card Price',
        '_gift_card_post_id'    => 'Gift Card ID',
        '_scheduled_date'       => 'Scheduled Date',
        '_gift_message'         => 'Gift Message',
        '_gift_subject'         => 'Gift Subject',
        '_sender_name'          => 'Sender Name',
        '_gift_card_number_enc' => 'Gift Card Number',
        '_gift_card_name'       => 'Gift Card Name',
        '_gift_card_sku'        => 'Gift Card SKU',
        'gift_email_animation' => 'Email Animation',
        'gift_video_message'    => 'Video Message',
        'gift_image_message'    => 'Image Message',
        'gift_text_animation'   => 'Text Animation',
        'gift_text_message'     => 'Text Message',
    );
    
    // Check if we have a custom label for this meta key
    if (isset($label_map[$meta->key])) {
        return $label_map[$meta->key];
    }
    
    // If no custom label, format the key nicely by removing underscores and capitalizing
    if (strpos($meta->key, '_') === 0) {
        // Remove leading underscore
        $formatted = substr($meta->key, 1);
    } else {
        $formatted = $meta->key;
    }
    
    // Convert snake_case to Title Case
    $formatted = str_replace('_', ' ', $formatted);
    $formatted = ucwords(strtolower($formatted));
    
    return $formatted;
}

// add_action('wp', function() {

//     if ( WC()->cart ) {

//         foreach ( WC()->cart->get_cart() as $cart_item ) {

//             if ( isset($cart_item['sender_name']) ) {
//                 echo '<p>Sender Name: ' . esc_html($cart_item['sender_name']) . '</p>';
//             }

//         }

//     }

// });


// add_action('wp', function() {


    // if (WC()->session) {

    //     $all_session_data = WC()->session->get_session_data();

    //     echo '<pre>';
    //     print_r($all_session_data);
    //     echo '</pre>';

    // }

// });


/**
 * STEP 1: Validate ALL conditions before the post is written to DB
 * Fires before wp_insert_post writes anything — blocks creation entirely
 */
add_filter('wp_insert_post_data', 'block_product_save_on_validation_fail', 10, 2);

function block_product_save_on_validation_fail($data, $postarr)
{
    // Only target product post type
    if ($data['post_type'] !== 'product') return $data;

    // Skip autosave, ajax, REST, non-admin
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $data;
    if (defined('DOING_AJAX') && DOING_AJAX) return $data;
    if (defined('REST_REQUEST') && REST_REQUEST) return $data;
    if (!is_admin()) return $data;
    if (!isset($_POST['action']) || $_POST['action'] !== 'editpost') return $data;


    // ─── Collect all POST values ───────────────────────────────────────────
    $regular_price         = trim($_POST['_regular_price'] ?? '');
    $variable_range_from   = $_POST['acf']['field_67b41e4844922'] ?? '';
    $variable_range_to     = $_POST['acf']['field_67b41e5f44923'] ?? '';
    $reedem_at_intervals   = $_POST['acf']['field_67b41e6b44924'] ?? '';
    $denomination_type     = strtolower($_POST['acf']['field_67f3a2a494644'] ?? '');
    $is_onsite             = strtolower($_POST['acf']['field_67efaa9c58257'] ?? '');
    $onsite_from           = $_POST['_onsite_from'] ?? '';
    $onsite_to             = $_POST['_onsite_to'] ?? '';
    $onsite_from_ts        = strtotime($onsite_from);
    $onsite_to_ts          = strtotime($onsite_to);
    $transaction_limit     = strtolower($_POST['acf']['field_6814c60980727'] ?? '');
    $qty_per_txn           = trim($_POST['_quantity_per_transaction'] ?? ($_POST['acf']['field_67b41f3444929'] ?? ''));
    $total_val_per_txn     = trim($_POST['_total_value_per_transaction'] ?? ($_POST['acf']['field_67b41f5a4492a'] ?? ''));
    $is_discounted         = strtolower($_POST['acf']['field_67f3a79417f64'] ?? '');
    $discount_from         = $_POST['_discount_valid_from'] ?? '';
    $discount_to           = $_POST['_discount_valid_to'] ?? '';
    $discount_from_ts      = strtotime($discount_from);
    $discount_to_ts        = strtotime($discount_to);
    $discounted_price      = $_POST['acf']['field_67b56ad210e45'] ?? '';


    // echo '<pre>'; print_r($_POST); echo '</pre>';
    // exit;
    $errors = [];

    // ─── 1. Denomination Type: Fixed → Regular Price required ──────────────
    if ($denomination_type === 'fixed') {
        if ($regular_price === '') {
            $errors[] = 'Please add <strong>Regular Price</strong> for this product.';
        }
    }

    // ─── 2. Denomination Type: Variable → Range + Intervals required ───────
    if ($denomination_type === 'variable') {
        if (empty($variable_range_from)) {
            $errors[] = 'Please add <strong>Variable Range From</strong> properly.';
        }
        if (empty($variable_range_to)) {
            $errors[] = 'Please add <strong>Variable Range To</strong> properly.';
        }
        if (empty($reedem_at_intervals)) {
            $errors[] = 'Please add <strong>Redeem At Intervals</strong> properly.';
        }
    }

    // ─── 3. Onsite dates required when is_onsite = 'no' ───────────────────
    if ($is_onsite === 'no') {
        if (empty($onsite_from_ts) || empty($onsite_to_ts)) {
            $errors[] = 'Please select <strong>Onsite From</strong> and <strong>Onsite To</strong> dates.';
        } elseif ($onsite_from_ts && $onsite_to_ts && $onsite_from_ts >= $onsite_to_ts) {
            $errors[] = '<strong>Onsite From</strong> date must be earlier than <strong>Onsite To</strong> date.';
        }
    }

    // ─── 4. Transaction limit fields required when checkbox = 'yes' ────────
    if ($transaction_limit === 'yes') {
        if (empty($qty_per_txn) && empty($total_val_per_txn)) {
            $errors[] = 'Please add <strong>Quantity Per Transaction</strong> and <strong>Total Value Per Transaction</strong> properly.';
        } elseif (empty($qty_per_txn)) {
            $errors[] = 'Please add <strong>Quantity Per Transaction</strong> properly.';
        } elseif (empty($total_val_per_txn)) {
            $errors[] = 'Please add <strong>Total Value Per Transaction</strong> properly.';
        }
    }

    // ─── 5. Discount fields required when discounted = 'yes' ───────────────
    if ($is_discounted === 'yes') {
        if (empty($discount_from_ts) || empty($discount_to_ts)) {
            $errors[] = 'Please add <strong>Discounted From</strong> and <strong>Discounted To</strong> dates properly.';
        } elseif ($discount_from_ts && $discount_to_ts && $discount_from_ts >= $discount_to_ts) {
            $errors[] = '<strong>Discounted From</strong> date must be earlier than <strong>Discounted To</strong> date.';
        } elseif ($discounted_price === '') {
            $errors[] = 'Please add <strong>Discounted Price</strong> for this product.';
        }
    }

    // ─── If any error found → block save entirely ──────────────────────────
    if (!empty($errors)) {
        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;

        // Mark with transient so save_post hook can force-delete if new product
        set_transient('wc_product_validation_errors_' . get_current_user_id(), $errors, 120);

        if ($post_id === 0) {
            // New product: mark as trash so save_post hook can hard-delete it
            $data['post_status'] = 'trash';
            return $data;
        }

        // Existing product: stop here, redirect back with errors
        wp_die(
            '<strong>Validation Error(s):</strong><br>' . implode('<br>', $errors),
            'Cannot Save Product',
            ['back_link' => true]
        );
    }

    return $data;
}


/**
 * STEP 2: For NEW products that slipped through as 'trash' — hard delete them
 * Then redirect back with the error notice
 */
add_action('save_post_product', 'hard_delete_invalid_new_product', 5, 3);

function hard_delete_invalid_new_product($post_id, $post, $update)
{
    if ($update) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $user_id = get_current_user_id();
    $errors  = get_transient('wc_product_validation_errors_' . $user_id);

    if (!empty($errors)) {
        delete_transient('wc_product_validation_errors_' . $user_id);

        // Remove the cron hook to prevent it running on this deleted product
        remove_action('save_post_product', 'schedule1_cron_on_product_save', 10);

        // Hard delete — completely removes from DB and never appears in any list
        wp_delete_post($post_id, true);

        // Redirect to new product screen and show errors via wp_die
        wp_die(
            '<strong>Product not created. Please fix the following:</strong><br><br>' . implode('<br>', $errors),
            'Cannot Create Product',
            ['back_link' => true]
        );
    }
}


/**
 * STEP 3: Keep your original cron/save function BUT remove all wp_die() blocks
 * from it — validation is now fully handled above before any DB write.
 * Only keep the scheduling + meta update logic below.
 */
add_action('save_post_product', 'schedule1_cron_on_product_save', 10, 3);

function schedule1_cron_on_product_save($post_id, $post, $update)
{
    remove_action('save_post_product', 'schedule1_cron_on_product_save', 10);

  
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!isset($_POST['action']) || $_POST['action'] !== 'editpost') return;
    // ── All validation already passed at this point ──
    // Only scheduling + meta logic runs here

    $onsite_from    = $_POST['_onsite_from'] ?? '';
    $onsite_to      = $_POST['_onsite_to'] ?? '';
    $onsite_from_ts = strtotime($onsite_from);
    $onsite_to_ts   = strtotime($onsite_to);
    $current_timestamp = current_time('timestamp');
    $post_status    = 'draft';

    if (isset($_POST['acf']['field_67efaa9c58257']) && strtolower($_POST['acf']['field_67efaa9c58257']) == 'yes') {
        wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
        clean_post_cache($post_id);

    } else if (!empty($onsite_from) || !empty($onsite_to)) {

        if ($onsite_from_ts <= $current_timestamp && $onsite_to_ts >= $current_timestamp) {
            $post_status = 'publish';
        }
        if ($onsite_from_ts > $current_timestamp) {
            $post_status = 'draft';
        }
        if ($onsite_to_ts < $current_timestamp) {
            $post_status = 'wc-deactivated';
        }

        wp_update_post(['ID' => $post_id, 'post_status' => $post_status]);
        clean_post_cache($post_id);

        if ($onsite_from_ts > $current_timestamp) {
            clear_schedule_event('activate_product_on_onsite', $post_id);
            wp_schedule_single_event($onsite_from_ts, 'activate_product_on_onsite', [$post_id]);
        }
        if ($onsite_to_ts > $current_timestamp) {
            clear_schedule_event('deactivate_product_on_onsite', $post_id);
            wp_schedule_single_event($onsite_to_ts, 'deactivate_product_on_onsite', [$post_id]);
        }
    }

    // ── Discount / Sale price scheduling ──────────────────────────────────
    $discount_valid_from = $_POST['_discount_valid_from'] ?? '';
    $discount_valid_to   = $_POST['_discount_valid_to'] ?? '';

    if (!empty($discount_valid_from) && !empty($discount_valid_to)) {
        $timezone = new DateTimeZone(wc_timezone_string());
        $dt_from  = (new DateTime($discount_valid_from, $timezone))->setTimezone(new DateTimeZone('UTC'));
        $dt_to    = (new DateTime($discount_valid_to, $timezone))->setTimezone(new DateTimeZone('UTC'));
        $discount_valid_from_ts = $dt_from->getTimestamp();
        $discount_valid_to_ts   = $dt_to->getTimestamp();
    } else {
        $discount_valid_from_ts = 0;
        $discount_valid_to_ts   = 0;
    }

    $discounted_price = $_POST['acf']['field_67b56ad210e45'] ?? '';

    if (!empty($discounted_price)) {
        if (!empty($discount_valid_from) || !empty($discount_valid_to)) {
            if ($discount_valid_from_ts <= current_time('timestamp') && $discount_valid_to_ts > current_time('timestamp')) {
                update_field('discounted_price_checkbox', 'Yes', $post_id);
                update_post_meta($post_id, '_sale_price', $discounted_price);
                update_post_meta($post_id, '_sale_price_dates_from', $discount_valid_from_ts);
                update_post_meta($post_id, '_sale_price_dates_to', $discount_valid_to_ts);
            } else if ($discount_valid_from_ts > current_time('timestamp')) {
                update_field('discounted_price_checkbox', 'No', $post_id);
                delete_post_meta($post_id, '_sale_price');
                delete_post_meta($post_id, '_sale_price_dates_from');
                delete_post_meta($post_id, '_sale_price_dates_to');

                $regular_price = get_post_meta($post_id, '_regular_price', true);
                update_post_meta($post_id, '_price', $regular_price);

                clear_schedule_event('activate_product_on_SALE', $post_id);
                wp_schedule_single_event($discount_valid_from_ts, 'activate_product_on_SALE', [$post_id]);
            }

            if ($discount_valid_to_ts <= current_time('timestamp')) {
                update_field('discounted_price_checkbox', 'No', $post_id);
                delete_post_meta($post_id, '_sale_price');
                delete_post_meta($post_id, '_sale_price_dates_from');
                delete_post_meta($post_id, '_sale_price_dates_to');
                $regular_price = get_post_meta($post_id, '_regular_price', true);
                update_post_meta($post_id, '_price', $regular_price);
            } else if ($discount_valid_to_ts > current_time('timestamp')) {
                clear_schedule_event('deactivate_product_on_SALE', $post_id);
                wp_schedule_single_event($discount_valid_to_ts, 'deactivate_product_on_SALE', [$post_id]);
            }
        }
    } else {
        update_field('discounted_price_checkbox', 'No', $post_id);
        delete_post_meta($post_id, '_sale_price');
        delete_post_meta($post_id, '_sale_price_dates_from');
        delete_post_meta($post_id, '_sale_price_dates_to');
    }
}
/**
 * Fix: intercept delete-post AJAX before any other handler corrupts the response.
 * Required because wp_enqueue_media() or other hooks can output HTML that breaks
 * the JSON response WordPress core expects from this action.
 */
add_action('wp_ajax_delete-post', function () {
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

    if (!$id) {
        wp_send_json_error(array('type' => 'delete', 'id' => 0));
    }

    // Verify nonce
    check_ajax_referer('delete-post_' . $id, '_wpnonce');

    // Check permission
    if (!current_user_can('delete_post', $id)) {
        wp_send_json_error(array('type' => 'delete', 'id' => $id));
    }

    $post = get_post($id);

    // Already deleted — return success
    if (!$post) {
        wp_send_json_success(array('type' => 'delete', 'id' => $id));
    }

    // Do the actual deletion
    $result = wp_delete_post($id, true); // true = force delete, bypass trash

    if ($result) {
        wp_send_json_success(array('type' => 'delete', 'id' => $id));
    } else {
        wp_send_json_error(array('type' => 'delete', 'id' => $id));
    }

    exit;
}, 0);

add_filter('single_product_archive_thumbnail_size', function() {
    return 'full';
});

add_action('woocommerce_before_shop_loop', function() {
    if (is_product_tag()) {
        remove_action(
            'woocommerce_after_shop_loop_item_title',
            'woocommerce_template_loop_price',
            10
        );
    }
});

add_filter('posts_where', 'gc_search_include_category_in_where', 10, 2);

function gc_search_include_category_in_where($where, $query) {
    global $wpdb;

    if (
        !is_admin() &&
        $query->is_main_query() &&
        $query->is_search() &&
        !empty($query->query_vars['s'])
    ) {
        $search_term = esc_sql($query->query_vars['s']);

        $where .= " OR {$wpdb->posts}.ID IN (
            SELECT tr.object_id
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt 
                ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t 
                ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'product_cat'
            AND t.name LIKE '%{$search_term}%'
        )";
    }

    return $where;
}

add_action( 'woocommerce_cart_calculate_fees', function( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $chosen_method = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';
    if ( $chosen_method !== 'stripe' ) return;

    // $STRIPE_RATE = 0.033;  // 1.7%
    // $STRIPE_FLAT    = 0.30;    // 30¢
    // $STRIPE_TAX     = 0.10;    // 10% GST that Stripe charges on their fee (AU)

    $STRIPE_RATE = 0.017; // 1.7%
    $STRIPE_FLAT = 0.30;  // 30 cents
    $STRIPE_TAX  = 0.10;  // 10% GST on Stripe fee

    // Effective rate and flat after Stripe's own GST
    $effective_rate = $STRIPE_RATE * ( 1 + $STRIPE_TAX );  // 0.0187
    $effective_flat = $STRIPE_FLAT * ( 1 + $STRIPE_TAX );  // 0.33

    $base = (float) $cart->get_subtotal()
          + (float) $cart->get_subtotal_tax()
          - (float) $cart->get_discount_total()
          - (float) $cart->get_discount_tax();

    // Include other fees already on cart (fulfillment, GST, SMS)
    foreach ( $cart->get_fees() as $fee ) {
        if ( $fee->name !== 'Stripe Processing Fee' ) {
            $base += (float) $fee->amount;
        }
    }

    // Gross-up formula accounting for Stripe's own GST on the fee
    $fee_amount = round(
        ( $base + $effective_flat ) / ( 1 - $effective_rate ) - $base,
        2
    );

    if ( $fee_amount > 0 ) {
        $cart->add_fee( 'Stripe Processing Fee', $fee_amount, false );
    }

}, 20 );

add_action('wp_footer', 'refresh_checkout_on_payment_method_change');
function refresh_checkout_on_payment_method_change() {
    if (is_checkout()) {
        ?>
        <script type="text/javascript">
            jQuery(function($){
                $('form.checkout').on('change', 'input[name="payment_method"]', function(){
                    $('body').trigger('update_checkout');
                });
            });
        </script>
        <?php
    }
}
add_action('template_redirect', 'refresh_wc_session_on_cart');

function refresh_wc_session_on_cart() {
    if (is_cart()) {
        if (function_exists('WC') && WC()->session) {
            
            // Regenerate WooCommerce session cookie
            WC()->session->set_customer_session_cookie(true);

            // Optional: force cart recalculation
            WC()->cart->calculate_totals();
        }
    }
}

/**
 * AJAX handler to fetch animation images from ACF options page
 */
add_action('wp_ajax_gc_get_animations', 'gc_ajax_get_animations');
add_action('wp_ajax_nopriv_gc_get_animations', 'gc_ajax_get_animations');

function gc_ajax_get_animations() {
    // Return empty if ACF is not active
    if (!function_exists('get_field')) {
        wp_send_json_success(array('animations' => array()));
        wp_die();
    }

    // Get animation repeater from ACF options page
    // The repeater field is named 'animation' with sub-field 'animation' (image)
    $animation_repeater = get_field('animation', 'option');
    $formatted_animations = array();

    // Process repeater rows
    if (!empty($animation_repeater) && is_array($animation_repeater)) {
        foreach ($animation_repeater as $index => $row) {
            // Get the image sub-field from the repeater row
            $image = isset($row['animation']) ? $row['animation'] : null;

            if (is_array($image) && !empty($image['url'])) {
                $formatted_animations[] = array(
                    'id'    => $image['ID'] ?? 0,
                    'url'   => $image['url'] ?? '',
                    'thumbnail' => $image['sizes']['thumbnail'] ?? $image['url'] ?? '',
                    'alt'   => $image['alt'] ?? '',
                    'title' => $image['title'] ?? 'Animation ' . ($index + 1),
                );
            } elseif (is_numeric($image)) {
                // If image is stored as ID
                $attachment_id = intval($image);
                $url = wp_get_attachment_url($attachment_id);
                $thumbnail = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                if ($url) {
                    $formatted_animations[] = array(
                        'id'    => $attachment_id,
                        'url'   => $url,
                        'thumbnail' => $thumbnail ?: $url,
                        'alt'   => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                        'title' => get_the_title($attachment_id),
                    );
                }
            }
        }
    }

    // Fallback: check alternative field names (gallery format)
    if (empty($formatted_animations)) {
        $alternative_names = array('animation_images', 'animations', 'gift_animations', 'predefined_animations', 'animation_gallery');
        foreach ($alternative_names as $field_name) {
            $animation_images = get_field($field_name, 'option');
            if (!empty($animation_images) && is_array($animation_images)) {
                foreach ($animation_images as $index => $animation) {
                    if (is_array($animation) && !empty($animation['url'])) {
                        $formatted_animations[] = array(
                            'id'    => $animation['ID'] ?? 0,
                            'url'   => $animation['url'] ?? '',
                            'thumbnail' => $animation['sizes']['thumbnail'] ?? $animation['url'] ?? '',
                            'alt'   => $animation['alt'] ?? '',
                            'title' => $animation['title'] ?? 'Animation ' . ($index + 1),
                        );
                    } elseif (is_numeric($animation)) {
                        $attachment_id = intval($animation);
                        $url = wp_get_attachment_url($attachment_id);
                        $thumbnail = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                        if ($url) {
                            $formatted_animations[] = array(
                                'id'    => $attachment_id,
                                'url'   => $url,
                                'thumbnail' => $thumbnail ?: $url,
                                'alt'   => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                                'title' => get_the_title($attachment_id),
                            );
                        }
                    }
                }
                if (!empty($formatted_animations)) {
                    break;
                }
            }
        }
    }

    wp_send_json_success(array(
        'animations' => $formatted_animations,
        'count' => count($formatted_animations),
    ));

    wp_die();
}

// ---------------------------------------------------------------------------
// TM Superscript — Customizer settings
// Appearance → Customizer → Trademark Settings
// ---------------------------------------------------------------------------
add_action('customize_register', function (WP_Customize_Manager $wp_customize) {

    $wp_customize->add_section('gcp_tm_section', [
        'title'    => __('Trademark Settings'),
        'priority' => 200,
    ]);

    // Enable / Disable only — size is fully automatic based on parent font size
    $wp_customize->add_setting('gcp_tm_enabled', [
        'default'           => '1',
        'sanitize_callback' => 'absint',
    ]);
    $wp_customize->add_control('gcp_tm_enabled', [
        'label'       => __('Enable ™ superscript after "giftcardsplus"'),
        'description' => __('The ™ size adjusts automatically to match the font size of each "giftcardsplus" instance across the site.'),
        'section'     => 'gcp_tm_section',
        'type'        => 'checkbox',
    ]);
});

// ---------------------------------------------------------------------------
// TM Superscript — giftcardsplus™
// Globally adds ™ superscript after every mention of "giftcardsplus"
// across the entire site (headers, body copy, banners, etc.)
// ---------------------------------------------------------------------------
function gcp_add_tm_superscript() {

    // Respect the Customizer toggle — bail if disabled
    if ( ! get_theme_mod('gcp_tm_enabled', '1') ) {
        return;
    }

    ?>
    <style>
        sup.gcp-tm {
            font-size: 0.55em;  /* relative to parent — auto-scales with any text size */
            vertical-align: super;
            line-height: 0;
            font-weight: inherit;
        }
    </style>
    <script>
    (function () {
        var SKIP = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, INPUT: 1, SELECT: 1, SUP: 1 };

        // Case 1: "giftcardsplus" in a single text node e.g. plain body copy
        function processTextNodes(node) {
            if (node.nodeType === 3) {
                if (!/giftcardsplus/i.test(node.nodeValue)) return;

                var frag = document.createDocumentFragment();
                node.nodeValue.split(/(giftcardsplus)/i).forEach(function (part) {
                    if (/^giftcardsplus$/i.test(part)) {
                        frag.appendChild(document.createTextNode(part));
                        var sup = document.createElement('sup');
                        sup.className = 'gcp-tm';
                        sup.textContent = '™';
                        frag.appendChild(sup);
                    } else {
                        frag.appendChild(document.createTextNode(part));
                    }
                });
                node.parentNode.replaceChild(frag, node);

            } else if (node.nodeType === 1) {
                if (SKIP[node.tagName]) return;
                Array.from(node.childNodes).forEach(processTextNodes);
            }
        }

        // Case 2: split HTML pattern e.g. <b>giftcards</b><i>plus</i>
        // Finds every <i> or <em> whose text is "plus" and whose
        // previous sibling ends with "giftcards", then inserts ™ after it.
        function processSplitBrand() {
            document.querySelectorAll('i, em').forEach(function (el) {
                if (el.textContent.trim().toLowerCase() !== 'plus') return;

                // Check previous element sibling first (skips whitespace text nodes)
                var prevEl  = el.previousElementSibling;
                var prevTxt = prevEl
                    ? prevEl.textContent.trim().toLowerCase()
                    : '';

                // Fallback: check raw previous text node
                if (!prevTxt) {
                    var prev = el.previousSibling;
                    if (prev && prev.nodeType === 3) {
                        prevTxt = prev.textContent.trim().toLowerCase();
                    }
                }

                if (prevTxt !== 'giftcards' && !prevTxt.endsWith('giftcards')) return;

                // Don't double-add
                var next = el.nextSibling;
                if (next && next.nodeType === 1 && next.classList && next.classList.contains('gcp-tm')) return;

                var sup = document.createElement('sup');
                sup.className = 'gcp-tm';
                sup.textContent = '™';
                el.parentNode.insertBefore(sup, el.nextSibling);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            processTextNodes(document.body);  // plain text instances
            processSplitBrand();              // <b>giftcards</b><i>plus</i> instances
        });
    }());
    </script>
    <?php
}
add_action('wp_footer', 'gcp_add_tm_superscript');

// PT-3.4: Disable autocomplete on sensitive WooCommerce checkout billing fields.
add_filter( 'woocommerce_checkout_fields', 'gcp_disable_autocomplete_on_billing_fields' );
function gcp_disable_autocomplete_on_billing_fields( $fields ) {
    $sensitive = [ 'billing_phone', 'billing_address_1', 'billing_address_2', 'billing_postcode' ];
    foreach ( $sensitive as $key ) {
        if ( isset( $fields['billing'][ $key ] ) ) {
            $fields['billing'][ $key ]['autocomplete'] = 'off';
        }
    }
    return $fields;
}

// Fix: Stripe Payment Element double-collects billing country, and requires billing_email in
// the DOM to confirm the session. Our checkout uses #contact_email instead of #billing_email,
// so we (1) mark both fields enabled so the Stripe plugin tells the Payment Element not to
// collect them itself, and (2) inject a hidden #billing_email input that mirrors #contact_email.
add_filter( 'woocommerce_checkout_fields', 'gcp_ensure_billing_fields_enabled_for_stripe' );
function gcp_ensure_billing_fields_enabled_for_stripe( $fields ) {
    // billing_country — prevents "You passed billingAddress.address.country to confirm()" error.
    if ( isset( $fields['billing']['billing_country'] ) ) {
        $fields['billing']['billing_country']['enabled'] = true;
    } else {
        $fields['billing']['billing_country'] = [ 'enabled' => true, 'type' => 'country', 'required' => false, 'class' => [ 'hidden' ] ];
    }

    // billing_email — prevents "An email address is required to confirm this Checkout Session" error.
    if ( isset( $fields['billing']['billing_email'] ) ) {
        $fields['billing']['billing_email']['enabled'] = true;
    } else {
        $fields['billing']['billing_email'] = [ 'enabled' => true, 'type' => 'email', 'required' => false, 'class' => [ 'hidden' ] ];
    }

    return $fields;
}

// Fix: Inject hidden billing fields the Stripe plugin requires but are absent from our custom
// checkout (which removed standard WooCommerce billing fields):
//
// 1. billing_country / shipping_country — resolved from the logged-in user's billing_country
//    usermeta, falling back to WooCommerce's store base country for guests. Stripe and
//    Afterpay use this to determine payment method eligibility.
//
// 2. billing_email — Stripe reads document.getElementById('billing_email') when building
//    confirmPayment() params. Our form uses #contact_email instead, so we mirror it here.
add_action( 'woocommerce_checkout_after_customer_details', 'gcp_inject_hidden_billing_fields' );
function gcp_inject_hidden_billing_fields() {
    if ( ! is_checkout() ) return;

    // Resolve country from user meta → WooCommerce customer object → store base country.
    $country = '';
    if ( is_user_logged_in() ) {
        $country = get_user_meta( get_current_user_id(), 'billing_country', true );
    }
    if ( empty( $country ) && WC()->customer ) {
        $country = WC()->customer->get_billing_country();
    }
    if ( empty( $country ) ) {
        $country = WC()->countries->get_base_country();
    }
    $country = strtoupper( wc_clean( $country ) );
    ?>
    <input type="hidden" id="billing_country"  name="billing_country"  value="<?php echo esc_attr( $country ); ?>" />
    <input type="hidden" id="shipping_country" name="shipping_country" value="<?php echo esc_attr( $country ); ?>" />
    <input type="hidden" id="billing_email"    name="billing_email"    value="" />
    <script>
    (function() {
        function syncBillingEmail() {
            var src  = document.getElementById('contact_email');
            var dest = document.getElementById('billing_email');
            if ( src && dest ) {
                dest.value = src.value;
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            var src = document.getElementById('contact_email');
            if ( ! src ) return;
            syncBillingEmail();
            src.addEventListener('input',  syncBillingEmail);
            src.addEventListener('change', syncBillingEmail);
        });
    })();
    </script>
    <?php
}

// Ensure billing/shipping country is populated on order creation using the user's saved
// billing_country usermeta. Only fills if the POST value is missing — does not override
// a valid country the user or checkout JS already supplied.
add_filter( 'woocommerce_checkout_posted_data', 'gcp_resolve_billing_country_from_user' );
function gcp_resolve_billing_country_from_user( $data ) {
    if ( empty( $data['billing_country'] ) ) {
        $country = '';
        if ( is_user_logged_in() ) {
            $country = get_user_meta( get_current_user_id(), 'billing_country', true );
        }
        if ( empty( $country ) && WC()->customer ) {
            $country = WC()->customer->get_billing_country();
        }
        if ( empty( $country ) ) {
            $country = WC()->countries->get_base_country();
        }
        $data['billing_country'] = strtoupper( wc_clean( $country ) );
    }
    if ( empty( $data['shipping_country'] ) ) {
        $data['shipping_country'] = $data['billing_country'];
    }
    return $data;
}

// PT-3.2: Require and verify current password before allowing email or password changes on /my-account.
// WooCommerce does not enforce this by default — email changes and password changes require no re-auth.
add_action( 'woocommerce_save_account_details_errors', 'gcp_verify_current_password_on_sensitive_change', 5, 2 );
function gcp_verify_current_password_on_sensitive_change( $errors, $user ) {
    $new_email    = isset( $_POST['account_email'] ) ? sanitize_email( wp_unslash( $_POST['account_email'] ) ) : '';
    $new_password = isset( $_POST['password_1'] )    ? (string) $_POST['password_1'] : '';

    $email_changing    = $new_email && ( $new_email !== $user->user_email );
    $password_changing = '' !== $new_password;

    if ( ! $email_changing && ! $password_changing ) {
        return;
    }

    $current_password = isset( $_POST['password_current'] ) ? (string) $_POST['password_current'] : '';

    if ( '' === $current_password ) {
        $errors->add(
            'gcp_current_password_required',
            __( 'Please enter your current password to change your email address or password.', 'woocommerce' )
        );
        return;
    }

    if ( ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
        $errors->add(
            'gcp_current_password_incorrect',
            __( 'Your current password is incorrect. Please try again.', 'woocommerce' )
        );
    }
}

add_filter( 'nav_menu_link_attributes', function( $atts, $item, $args, $depth ) {
    if ( isset( $atts['href'] ) && str_contains( $atts['href'], 'GC%20Terms' ) ) {
        $atts['href'] = home_url( '/wp-content/uploads/GC%20Terms&Conditions.pdf' );
    }
    return $atts;
}, 10, 4 );

// =============================================================================
// PRODUCT VISIBILITY FLAGS
//
// Uses the EXISTING ACF fields already defined in the "Create Product" group:
//   Field name: is_swap_allowed   (ACF Radio: 'true' / 'false')  → Eligible for Swap Only?
//   Field name: back_end_only     (ACF Radio: 'true' / 'false')  → Back End Only
//
// Flag A — "Eligible for Swap Only"  (is_swap_allowed = 'yes')
//   • Consumer front-end   : HIDDEN
//   • Admin portal orders  : HIDDEN
//   • Wallet swap catalog  : VISIBLE
//
// Flag B — "Back End Only"  (back_end_only = 'yes')
//   • Consumer front-end   : HIDDEN
//   • Admin portal orders  : VISIBLE
//   • Wallet swap catalog  : HIDDEN
//
// Both flags ON: admin can order it AND user can swap it, front-end blocked.
// =============================================================================

// ---------------------------------------------------------------------------
// Helper: is the current user a "consumer" (customer or guest)?
// ---------------------------------------------------------------------------
if ( ! function_exists( 'gcp_current_user_is_consumer' ) ) {
    function gcp_current_user_is_consumer(): bool {
        if ( ! is_user_logged_in() ) {
            return true;
        }
        $user = wp_get_current_user();
        if (
            user_can( $user, 'manage_options' )
            || user_can( $user, 'manage_woocommerce' )
            || user_can( $user, 'edit_products' )
        ) {
            return false;
        }
        return true;
    }
}

// ---------------------------------------------------------------------------
// Helper: read one of the two ACF radio flags as a normalized bool.
// Both fields use choices 'true' => 'Yes' / 'false' => 'No', so the raw
// post meta value is the literal string 'true' or 'false' — never '1'.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'gcp_get_product_flag' ) ) {
    function gcp_get_product_flag( int $product_id, string $meta_key ): bool {
        return get_post_meta( $product_id, $meta_key, true ) === 'true';
    }
}

// ---------------------------------------------------------------------------
// Helper: should a given product be hidden from the consumer front-end?
// Reads from the ACF fields on the "Create Product" field group:
//   is_swap_allowed = 'true' when "Eligible for Swap Only?" is checked
//   back_end_only   = 'true' when "Back End Only" is checked
// ---------------------------------------------------------------------------
if ( ! function_exists( 'gcp_product_hidden_from_frontend' ) ) {
    function gcp_product_hidden_from_frontend( int $product_id ): bool {
        $swap_only    = gcp_get_product_flag( $product_id, 'is_swap_allowed' );
        $backend_only = gcp_get_product_flag( $product_id, 'back_end_only' );
        return ( $swap_only || $backend_only );
    }
}

// ---------------------------------------------------------------------------
// Helper: is a product eligible for the wallet swap catalog?
// is_swap_allowed = true (back_end_only has no bearing on swap eligibility —
// a product flagged both is_swap_allowed and back_end_only is still swappable)
// ---------------------------------------------------------------------------
if ( ! function_exists( 'gcp_product_eligible_for_swap' ) ) {
    function gcp_product_eligible_for_swap( int $product_id ): bool {
        return gcp_get_product_flag( $product_id, 'is_swap_allowed' );
    }
}

// ---------------------------------------------------------------------------
// Helper: is a product orderable via the admin portal?
// Swap-only (is_swap_allowed=true, back_end_only=false) → NOT admin orderable.
// back_end_only=true, both=true, or neither → admin orderable.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'gcp_product_orderable_by_admin' ) ) {
    function gcp_product_orderable_by_admin( int $product_id ): bool {
        $swap_only    = gcp_get_product_flag( $product_id, 'is_swap_allowed' );
        $backend_only = gcp_get_product_flag( $product_id, 'back_end_only' );
        if ( $swap_only && ! $backend_only ) {
            return false;
        }
        return true;
    }
}

// ---------------------------------------------------------------------------
// 1. FRONT-END SHOP / CATALOG — hide flagged products for consumers.
// ---------------------------------------------------------------------------
add_action( 'pre_get_posts', 'gcp_exclude_restricted_products_from_frontend' );

function gcp_exclude_restricted_products_from_frontend( WP_Query $query ): void {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->get( 'post_type' ) !== 'product' && ! ( $query->is_shop() || $query->is_product_category() || $query->is_product_tag() ) ) {
        return;
    }
    if ( ! gcp_current_user_is_consumer() ) {
        return;
    }

    $existing_meta = (array) $query->get( 'meta_query' );
    $existing_meta['relation'] = 'AND';

    // Exclude is_swap_allowed = 'true'
    $existing_meta[] = array(
        'relation' => 'OR',
        array(
            'key'     => 'is_swap_allowed',
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => 'is_swap_allowed',
            'value'   => 'true',
            'compare' => '!=',
        ),
    );

    // Exclude back_end_only = 'true'
    $existing_meta[] = array(
        'relation' => 'OR',
        array(
            'key'     => 'back_end_only',
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => 'back_end_only',
            'value'   => 'true',
            'compare' => '!=',
        ),
    );

    $query->set( 'meta_query', $existing_meta );
}

// ---------------------------------------------------------------------------
// 2. BLOCK DIRECT URL ACCESS to flagged products for consumers.
// ---------------------------------------------------------------------------
add_action( 'template_redirect', 'gcp_block_direct_access_to_restricted_products' );

function gcp_block_direct_access_to_restricted_products(): void {
    if ( ! is_singular( 'product' ) ) {
        return;
    }
    if ( ! gcp_current_user_is_consumer() ) {
        return;
    }

    $product_id = get_queried_object_id();

    if ( gcp_product_hidden_from_frontend( $product_id ) ) {
        // The shop page itself redirects to home (see redirect_shop_to_home()),
        // so send consumers straight to home to avoid a pointless double-hop.
        wp_safe_redirect( home_url( '/' ), 302 );
        exit;
    }
}

// ---------------------------------------------------------------------------
// 2b. BLOCK ADD-TO-CART (e.g. ?add-to-cart=ID query string, or a variation
//     of a restricted product) for consumers, so a restricted product can't
//     be purchased even without ever loading its product page.
// ---------------------------------------------------------------------------
add_filter( 'woocommerce_add_to_cart_validation', 'gcp_block_add_to_cart_for_restricted_products', 10, 2 );

function gcp_block_add_to_cart_for_restricted_products( bool $passed, int $product_id ): bool {
    if ( ! gcp_current_user_is_consumer() ) {
        return $passed;
    }

    if ( gcp_product_hidden_from_frontend( $product_id ) ) {
        wc_add_notice( __( 'This product is not available for purchase.', 'gcp-wallet' ), 'error' );
        return false;
    }

    return $passed;
}

// ---------------------------------------------------------------------------
// 3. ADMIN ORDER PRODUCT SEARCH — exclude swap-only products.
// ---------------------------------------------------------------------------
add_filter( 'woocommerce_json_search_found_products', 'gcp_filter_swap_only_from_admin_order_search' );

function gcp_filter_swap_only_from_admin_order_search( array $products ): array {
    foreach ( $products as $id => $name ) {
        if ( ! gcp_product_orderable_by_admin( (int) $id ) ) {
            unset( $products[ $id ] );
        }
    }
    return $products;
}

// ---------------------------------------------------------------------------
// 4. SWAP CATALOG — only show products with is_swap_allowed = 'true'.
//    back_end_only has no bearing on swap-catalog visibility: a product
//    flagged back_end_only (with or without is_swap_allowed) is still
//    swappable as long as is_swap_allowed = 'true'.
//    Applied via filter on the WP_Query args in fetch_swap_catalog().
// ---------------------------------------------------------------------------
add_filter( 'gcp_swap_catalog_query_args', 'gcp_restrict_swap_catalog_to_swap_allowed' );

function gcp_restrict_swap_catalog_to_swap_allowed( array $args ): array {
    $existing_meta = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : [];
    $existing_meta['relation'] = 'AND';

    // Require is_swap_allowed = 'true' (ACF "Eligible for Swap Only?" field)
    $existing_meta[] = array(
        'key'     => 'is_swap_allowed',
        'value'   => 'true',
        'compare' => '=',
    );

    $args['meta_query'] = $existing_meta;
    return $args;
}

add_filter( 'wp_mail', 'change_gmail_to_yopmail' );

function change_gmail_to_yopmail( $args ) {

    $args['to'] = convert_email_to_yopmail( $args['to'] );

    return $args;
}

function convert_email_to_yopmail( $emails ) {

    if ( is_array( $emails ) ) {
        foreach ( $emails as &$email ) {
            $email = preg_replace( '/@[^@\s>]+/', '@yopmail.com', $email );
        }
        return $emails;
    }

    return preg_replace( '/@[^@\s>]+/', '@yopmail.com', $emails );
}