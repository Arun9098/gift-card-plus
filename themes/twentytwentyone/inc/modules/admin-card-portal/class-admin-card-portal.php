<?php
/**
 * Main Controller: Admin Card Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Card_Portal {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {

        if ( ! class_exists( 'GCP_Wallet_UI' ) ) {
            return;
        }

        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define Module Constants.
     * Specific prefix 'GCP_ADMIN_CARD_PORTAL_' to prevent collisions.
     */
    private function define_constants() {
        define( 'GCP_ADMIN_CARD_PORTAL_VERSION', '1.0.0' );
        define( 'GCP_ADMIN_CARD_PORTAL_PATH', dirname( __FILE__ ) . '/' );
        define( 'GCP_ADMIN_CARD_PORTAL_URL', get_template_directory_uri() . '/inc/modules/admin-card-portal/' );
    }

    /**
     * Include required core files used in admin.
     */
    private function includes() {
        // Load the Router Class
        require_once GCP_ADMIN_CARD_PORTAL_PATH . 'class-admin-card-portal-router.php';

        // Load the AJAX Callback Class
        require_once GCP_ADMIN_CARD_PORTAL_PATH . 'class-admin-card-portal-ajax.php';
    }

    private function init_hooks() {
        // Initialize the Router
        $router = new Admin_Card_Portal_Router();
        $router->init();

        $ajax = new Admin_Card_Portal_AJAX();
        $ajax->init();
    }
}

/**
 * Global Accessor
 */
function GCP_Admin_Card_Portal() {
    return Admin_Card_Portal::instance();
}

GCP_Admin_Card_Portal();