<?php
/**
 * Router Handler: Admin Card Portal (Frontend)
 * Intercepts frontend requests to render the Admin Portal interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Card_Portal_Router {

    /**
     * The slug of the WordPress page that acts as the entry point.
     * You must create a page in WP Admin with this slug (e.g., 'card-portal').
     */
    const PORTAL_PAGE_SLUG = 'gift-card-detail';

    /**
     * Initialize Hooks.
     */
    public function init() {
        // Intercept the page load
        add_action( 'template_redirect', array( $this, 'handle_portal_request' ) );
        
        // Load Assets only when on this page
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Main Router Logic.
     * Checks if we are on the portal page, verifies admin, and loads the view.
     */
    public function handle_portal_request() {
        // Check slug
        if ( ! is_page( self::PORTAL_PAGE_SLUG ) ) {
            return;
        }

        // Security Check (Admins/Shop Managers only)
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_redirect( home_url() );
            exit;
        }

        // Get Query Params (e.g., site.com/card-portal/?card_id=123)
        $card_id = isset( $_GET['card_id'] ) ? intval( $_GET['card_id'] ) : 0;

        // Render View
        $this->render_view( $card_id );
        exit;
    }

    /**
     * Load CSS/JS only on the portal page.
     */
    public function enqueue_assets() {
        if ( ! is_page( self::PORTAL_PAGE_SLUG ) ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style( 
            'gcp-admin-card-portal-css', 
            GCP_ADMIN_CARD_PORTAL_URL . 'assets/css/gcp-admin-card-portal.css',
            array(), 
            time() // GCP_ADMIN_CARD_PORTAL_VERSION 
        );

        // Enqueue JS
        wp_enqueue_script( 
            'gcp-admin-card-portal-js', 
            GCP_ADMIN_CARD_PORTAL_URL . 'assets/js/gcp-admin-card-portal.js',
            array( 'jquery' ), 
            time(), // GCP_ADMIN_CARD_PORTAL_VERSION, 
            true 
        );

        wp_localize_script( 'gcp-admin-card-portal-js', 'gcp_portal_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ), // Frontend AJAX still points here
            'nonce'    => wp_create_nonce( 'gcp_admin_portal_nonce' )
        ));
    }

    /**
     * View Controller.
     */
    private function render_view( $card_id ) {
        // Define variable to be used in the included file
        $current_card_id = $card_id;
        
        // Determine which view to load
        if ( $card_id > 0 ) {
            if ( get_post_type( $card_id ) !== 'gift_card' ) {
                $view_name = 'search'; 
                $error_message = 'Invalid Gift Card ID.';
            } else {
                $view_name = 'details';
            }
        } else {
            $view_name = 'search';
        }

        $file_path = GCP_ADMIN_CARD_PORTAL_PATH . 'views/html-view-' . $view_name . '.php';

        if ( file_exists( $file_path ) ) {
            include $file_path;
        } else {
            echo 'View not found.';
        }
    }
}