<?php
	function theme_generate_report_ajax() {
	    // PT-3.1: require login and admin capability before processing any report.
	    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	        wp_die( 'Access denied', '', 403 );
	    }

	    // Verify nonce
	    if (empty($_REQUEST['reports_export_nonce']) || !wp_verify_nonce($_REQUEST['reports_export_nonce'], 'reports_export')) {
	        wp_die('Security check failed', '', 403);
	    }


	    $report_type = isset($_REQUEST['report_type']) ? sanitize_text_field(wp_unslash($_REQUEST['report_type'])) : '';
	    $filename = $report_type . '-' . date('Y-m-d') . '.csv';


	    // Send headers for CSV download
	    header('Content-Type: text/csv; charset=utf-8');
	    header('Content-Disposition: attachment; filename=' . esc_attr($filename));


	    // Output handle
	    $output = fopen('php://output', 'w');


	    // Optional: add BOM for Excel compatibility
	    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));


	    // Generate CSV contents depending on the report type
	    switch ($report_type) {
	        case 'order_report':
	            // Header row
	            fputcsv($output, array(
	                'Order Date',
	                'Order Time',
	                'Order Name',
	                'User',
	                'Business Name',
	                'Business ID',
	                'Approved for Client Billing (Y/N)',
	                'Payment Type',
	                'Order Status',
	                'Invoice Number',
	                'Payment Status',
	                'Order Total ($)',
	                'Order Total Gift Cards ($)',
	                'Order Total Fulfilment ($)',
	                'Order Total Delivery Cost',
	                'Order Total GST',
	                'Campaign',
	                'Sender Profile',
	                'Client Reference',
	                'PO Number',
	                'Additional Client Reference',
	                'Order level activation expiry',
	                'Activation expiry set for this order'
	            ));
	        
	            // Get orders
	            $args = array(
	                'limit' => -1, // ⚠️ for large stores, paginate with 'paged' + 'posts_per_page'
	                'status' => array('wc-completed','wc-processing','wc-on-hold'), // adjust if needed
	            );
	            $orders = wc_get_orders($args);
	        
	            foreach ($orders as $order) {
	                /** @var WC_Order $order */
	        
	                $order_date = $order->get_date_created() ? $order->get_date_created()->date('d/m/Y') : '';
	                $order_time = $order->get_date_created() ? $order->get_date_created()->date('H:i:s') : '';
	        
	                $order_name = trim($order->get_formatted_billing_full_name());
	        
	                $user = $order->get_user_id() ? get_userdata($order->get_user_id())->user_email : 'Guest';
	        
	                $business_name = get_post_meta($order->get_id(), '_business_name', true);
	                if (!$business_name) $business_name = 'Consumer';
	                $business_id   = get_post_meta($order->get_id(), '_business_id', true);
	        
	                $approved_billing = get_post_meta($order->get_id(), '_approved_client_billing', true) ? 'Y' : 'N';
	        
	                $payment_type = $order->get_payment_method_title();
	        
	                $order_status   = wc_get_order_status_name($order->get_status());
	                $invoice_number = $order->get_meta('_invoice_number');
	                $payment_status = $order->is_paid() ? 'Paid' : 'Unpaid';
	        
	                $total      = $order->get_total();
	                $total_gc   = $order->get_meta('_order_total_giftcards');
	                $total_full = $order->get_meta('_order_total_fulfilment');
	                $delivery   = $order->get_shipping_total();
	                $gst        = $order->get_total_tax();
	        
	                $campaign        = $order->get_meta('_campaign');
	                $sender_profile  = $order->get_meta('_sender_profile');
	                $client_ref      = $order->get_meta('_client_reference');
	                $po_number       = $order->get_meta('_po_number');
	                $extra_client_ref= $order->get_meta('_additional_client_reference');
	        
	                $order_level_expiry = $order->get_meta('_order_level_expiry');
	                $activation_expiry  = $order->get_meta('_activation_expiry');
	        
	                fputcsv($output, array(
	                    $order_date,
	                    $order_time,
	                    $order_name,
	                    $user,
	                    $business_name,
	                    $business_id,
	                    $approved_billing,
	                    $payment_type,
	                    $order_status,
	                    $invoice_number,
	                    $payment_status,
	                    $total,
	                    $total_gc,
	                    $total_full,
	                    $delivery,
	                    $gst,
	                    $campaign,
	                    $sender_profile,
	                    $client_ref,
	                    $po_number,
	                    $extra_client_ref,
	                    $order_level_expiry,
	                    $activation_expiry,
	                ));
	            }
	            break;
	        


	        case 'supplier_order_report':
	            fputcsv($output, array('Supplier ID', 'Order ID', 'Date', 'Total'));
	            fputcsv($output, array('sample_supplier', 'sample_order', date('Y-m-d'), '0.00'));
	            break;


	        default:
	            fputcsv($output, array('Report Type', 'Note'));
	            fputcsv($output, array($report_type, 'No generator implemented yet. Add the SQL / WP_Query / WC calls in the switch-case.'));
	            break;
	    }


	    fclose($output);
	    exit;
	}
	add_action('wp_ajax_generate_report', 'theme_generate_report_ajax');
	add_action('wp_ajax_nopriv_generate_report', 'theme_generate_report_ajax');