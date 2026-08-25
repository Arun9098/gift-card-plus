<?php

function get_order_report_types(){
    $report_types_arr = array();
    $report_types_arr = [
        "order_report"                        => "Order Report ✓",
        "cards_tracking_report"               => "Cards Tracking Report ✓",
        "supplier_order_report"               => "Supplier Order Report ✓",
        "supplier_billing_report"             => "Supplier Billing Report ✓",
        "float_balance_report"                => "Float Balance Report ✓",
        "float_order_report"                  => "Float Order Report ✓",
        "client_billing_order_report"         => "Client Billing Order Report ✓",
        "client_billing_balance_report"       => "Client Billing Balance Report ✓",
        "all_business_balance_report"         => "All Business Balance Report ✓",
        "billing_report"                      => "Billing Report ✓",
        "individual_business_balance_statement"=> "Individual Business Balance statement ✓",
        "credit_card_payment_report"          => "Credit Card Payment Report ✓",
        "refunds_cancellation_report"         => "Refunds/Cancellation Report ✓",
        "audit_report"                        => "Audit Report ✓",
        "activation_report"                   => "Activation Report ✓",
        "expiry_report"                       => "Expiry Report ✓",
        "product_listing_report_extract"      => "Product Listing Report/Extract ✓",
        "active_product_listing_report_extract" => "Active Product Listing Report/Extract ✓",
        "brand_listing_report"                => "Brand Listing Report ✓",
        "supplier_product_report"             => "Supplier Product Report ✓",
        "business_report"                     => "Business Report ✓",
        "user_report"                         => "User Report ✓",
        "business_user_report"                => "Business User Report ✓"
    ];

    return $report_types_arr;
}

function get_order_report_restURL( $key = 'order_report' ){
    $report_types_restURL = array();
    
    $report_types_restURL = [
        "order_report"                        => esc_url_raw( rest_url('custom-reports/v1/order_report') ),
        "cards_tracking_report"               => esc_url_raw( rest_url('custom-reports/v1/cards_tracking_report') ),
        "supplier_order_report"               => esc_url_raw( rest_url('custom-reports/v1/supplier_order_report') ),
        "supplier_billing_report"             => esc_url_raw( rest_url('custom-reports/v1/supplier_billing_report') ),
        "float_balance_report"                => esc_url_raw( rest_url('custom-reports/v1/float_balance_report') ),
        "float_order_report"                  => esc_url_raw( rest_url('custom-reports/v1/float_order_report') ),
        "client_billing_order_report"         => esc_url_raw( rest_url('custom-reports/v1/client_billing_order_report') ),
        "client_billing_balance_report"       => esc_url_raw( rest_url('custom-reports/v1/client_billing_balance_report') ),
        "all_business_balance_report"         => esc_url_raw( rest_url('custom-reports/v1/all_business_balance_report') ),
        "billing_report"                      => esc_url_raw( rest_url('custom-reports/v1/billing_report') ),
        "individual_business_balance_statement" => esc_url_raw( rest_url('custom-reports/v1/individual_business_balance_statement') ),
        "credit_card_payment_report"          => esc_url_raw( rest_url('custom-reports/v1/credit_card_payment_report') ),
        "refunds_cancellation_report"         => esc_url_raw( rest_url('custom-reports/v1/refunds_cancellation_report') ),
        "audit_report"                        => esc_url_raw( rest_url('custom-reports/v1/audit_report') ),
        "activation_report"                   => esc_url_raw( rest_url('custom-reports/v1/activation_report') ),
        "expiry_report"                       => esc_url_raw( rest_url('custom-reports/v1/expiry_report') ),
        "product_listing_report_extract"      => esc_url_raw( rest_url('custom-reports/v1/product_listing_report_extract') ),
        "active_product_listing_report_extract" => esc_url_raw( rest_url('custom-reports/v1/product_listing_report_extract') ),
        "brand_listing_report"                => esc_url_raw( rest_url('custom-reports/v1/brand_listing_report') ),
        "supplier_product_report"             => esc_url_raw( rest_url('custom-reports/v1/supplier_product_report') ),
        "business_report"                     => esc_url_raw( rest_url('custom-reports/v1/business_report') ),
        "user_report"                         => esc_url_raw( rest_url('custom-reports/v1/user_report') ),
        "business_user_report"                => esc_url_raw( rest_url('custom-reports/v1/user_report') ),
    ];

    return $report_types_restURL[$key];
}

add_filter('template_include', function($template) {
    if (is_page_template('reports.php')) {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to view this page.', 'giftcardsplus' ), 403 );
        }

        $report_types_arr = get_order_report_types();

        $report_type = 'order_report';
        if( isset($_GET['report_type']) && !empty($_GET['report_type']) && array_key_exists($_GET['report_type'], $report_types_arr) ){
            $report_type = $_GET['report_type'];
        }

        if (!empty($report_type) && array_key_exists($report_type, $report_types_arr)) {
            $report_file = get_template_directory() . '/reports/types/' . $report_type . '.php';
            if (file_exists($report_file)) {
                include $report_file;
            }
        }
    }
    return $template; // Always return the template
});

function theme_reports_enqueue_assets()
{
    if (is_page_template('reports.php')) {
        
        wp_enqueue_style('reports-css', get_template_directory_uri() . '/assets/css/reports/report.css', array(), time());
        wp_enqueue_style('datatable-css',get_template_directory_uri() . '/assets/css/datatable.css',array(),time());
        wp_enqueue_style('buttons-datatable-css','https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css',array(),time());

        wp_enqueue_script('reports-js', get_template_directory_uri() . '/assets/js/reports/report.js', array('jquery'), time() , true);
            // Localize variables for use in JS
        wp_localize_script('reports-js', 'reportsData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('reports_nonce'),
        ));
        wp_enqueue_script('datatable-js', get_template_directory_uri() . '/assets/js/datatable.js', array('jquery'), true);
        wp_enqueue_script('buttons-datatable-js', 'https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js', array('jquery'), true);
        wp_enqueue_script('jszip-min-js', 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', array('jquery'), true);
        wp_enqueue_script('buttons-export-js', 'https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js', array('jquery'), true);
        wp_enqueue_script('print-export-js', 'https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js', array('jquery'), true);

        // Provide some dynamic data to the JS if needed
        wp_localize_script('reports-js', 'ReportsExport', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reports_export'),
        ));

        $report_types_arr = get_order_report_types();
        $report_type = 'order_report';
        if( isset($_GET['report_type']) && !empty($_GET['report_type']) && array_key_exists($_GET['report_type'], $report_types_arr) ){
            $report_type = $_GET['report_type'];
        }

        $permissions = current_user_can( 'manage_options' );

        if (!empty($report_type) && array_key_exists($report_type, $report_types_arr)) {
            $report_js_file = get_template_directory_uri() . '/assets/js/reports/types/' . $report_type . '.js';

            $rest_url = '';

            if (!empty($report_js_file)) {
                wp_enqueue_script($report_type.'-js', $report_js_file, array('jquery'), time() , true);
                
                wp_localize_script($report_type.'-js', strtoupper($report_type.'_val'), array(
                    'ajax_url'       => admin_url('admin-ajax.php'),
                    'rest_url'       => esc_url_raw( get_order_report_restURL($report_type) ),
                    'nonce'          => wp_create_nonce('nonce_'.$report_type),
                    'wp_rest_nonce'  => wp_create_nonce('wp_rest'),
                    'permissions'    => (int)$permissions,
                ));
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'theme_reports_enqueue_assets');

//include get_template_directory() . '/reports/reports_ajax_functions.php';
include get_template_directory() . '/reports/reports_restAPI.php';

if (! function_exists('parse_any_date_to_dt')) {
    function parse_any_date_to_dt($date_str) {
        if (empty($date_str)) return false;

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