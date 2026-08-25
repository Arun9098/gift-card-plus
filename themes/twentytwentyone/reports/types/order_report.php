<?php
	add_shortcode('order_report', 'func_order_report');
	function func_order_report(){ 
		ob_start(); ?>
		<table class="order_reportsTable" id="order_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="order_no">Order ID</th>
	                <th data-head_slug="order_date">Order Date</th>
	                <th data-head_slug="order_time">Order Time</th>
	                <th data-head_slug="order_name">Order Name</th>
	                <th data-head_slug="order_user">User</th>
	                <th data-head_slug="business_name">Business Name</th>
	                <th data-head_slug="business_id">Business ID</th>
	                <th data-head_slug="client_billing">Approved for Client Billing (Y/N)</th>
	                <th data-head_slug="payment_type">Payment Type</th>
	                <th data-head_slug="order_status">Order Status</th>
	                <th data-head_slug="invoice_number">Invoice Number</th>
	                <th data-head_slug="payment_status">Payment Status</th>
	                <th data-head_slug="order_total">Order Total</th>
	                <th data-head_slug="order_total_gift_cards">Order Total Gift Cards ($)</th>
	                <th data-head_slug="Order_total_fulfilment">Order Total Fulfilment ($)</th>
	                <th data-head_slug="order_total_delivery_cost">Order Total Delivery cost</th>
					<th data-head_slug="order_total_gst">Order Total GST</th>
					<th data-head_slug="campaign">Campaign</th>
					<th data-head_slug="sender_profile">Sender Profile</th>
					<th data-head_slug="client_reference">Client Reference</th>
					<th data-head_slug="po_number">PO Number</th>
					<th data-head_slug="additional_client_reference">Additional Client Reference</th>
					<th data-head_slug="order_level_activation_expiry">Order level activation expiry</th>
					<th data-head_slug="activation_expiry_set_for_this_order">Activation expiry set for this order</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>

		<?php return ob_get_clean();
	}

?>