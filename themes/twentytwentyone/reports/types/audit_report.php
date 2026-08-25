<?php
	add_shortcode('audit_report', 'func_audit_report');
	function func_audit_report(){
		ob_start(); ?>
		<table class="audit_reportTable" id="audit_reportTable">
			<thead>
				<tr>
					<th data-head_slug="order_date">Order Date</th>
					<th data-head_slug="order_time">Order Time</th>
					<th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="order_name">Order Name</th>
					<th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="order_status">Order Status</th>
					<th data-head_slug="gift_card_post_id">Gift Card Post ID</th>
					<th data-head_slug="gift_card_name">Gift Card Name</th>
					<th data-head_slug="gift_card_sku">Gift Card SKU</th>
					<th data-head_slug="gift_card_denomination">Denomination</th>
					<th data-head_slug="gift_card_status">Card Status</th>
					<th data-head_slug="delivery_method">Delivery Method</th>
					<th data-head_slug="delivery_email">Delivery Email</th>
					<th data-head_slug="delivery_date">Delivery Date</th>
					<th data-head_slug="delivery_time">Delivery Time</th>
					<th data-head_slug="sender_name">Sender Name</th>
					<th data-head_slug="sender_email">Sender Email</th>
					<th data-head_slug="recipient_name">Recipient Name</th>
					<th data-head_slug="payment_method">Payment Method</th>
					<th data-head_slug="invoice_number">Invoice Number</th>
					<th data-head_slug="campaign">Campaign</th>
					<th data-head_slug="activation_expiry_type">Activation Expiry Type</th>
					<th data-head_slug="activation_expiry_date">Activation Expiry Date</th>
					<th data-head_slug="gift_card_expiry_date">Gift Card Expiry Date</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>

		<?php return ob_get_clean();
	}
?>
