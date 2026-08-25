<?php
	add_shortcode('client_billing_order_report', 'func_client_billing_order_report');
	function func_client_billing_order_report(){ 
		ob_start(); ?>
		<table class="client_billing_order_reportsTable" id="client_billing_order_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="order_date">Order Date</th>
					<th data-head_slug="order_time">Order Time</th>
					<th data-head_slug="order_name">Order Name</th>
					<th data-head_slug="user">User</th>
					<th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="business_float_id">Business Float ID</th>
					<th data-head_slug="approved_for_client_billing_y_n">Approved for Client Billing (Y/N)</th>
					<th data-head_slug="billing_type">Billing Type</th>
					<th data-head_slug="order_status">Order Status</th>
					<th data-head_slug="invoice_number">Invoice Number</th>
					<th data-head_slug="payment_status">Payment Status</th>
					<th data-head_slug="total">Total ($)</th>
					<th data-head_slug="total_gift_cards">Total Gift Cards ($)</th>
					<th data-head_slug="total_fulfilment">Total Fulfilment ($)</th>
					<th data-head_slug="delivery_cost">Delivery cost</th>
					<th data-head_slug="gst">GST</th>
					<th data-head_slug="campaign">Campaign</th>
					<th data-head_slug="sender_profile">Sender Profile</th>
					<th data-head_slug="client_reference">Client Reference</th>
					<th data-head_slug="po_number">PO Number</th>
					<th data-head_slug="additional_client_reference">Additional Client Reference</th>
					<th data-head_slug="order_level_activation_expiry">Order level activation expiry</th>
					<th data-head_slug="activation_expiry_set_for_this_order">Activation expiry set for this order</th>
					<th data-head_slug="client_payment_due_date">Client Payment Due Date</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>
		<?php return ob_get_clean();
	}

?>