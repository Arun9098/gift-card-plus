<?php
	add_shortcode('credit_card_payment_report', 'func_credit_card_payment_report');
	function func_credit_card_payment_report(){
		ob_start(); ?>
		<table class="credit_card_payment_reportTable" id="credit_card_payment_reportTable">
			<thead>
				<tr>
					<th data-head_slug="order_date">Order Date</th>
					<th data-head_slug="order_time">Order Time</th>
					<th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="order_name">Order Name</th>
					<th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="payment_method">Payment Method</th>
					<th data-head_slug="payment_method_title">Payment Method Title</th>
					<th data-head_slug="transaction_id">Transaction ID</th>
					<th data-head_slug="order_status">Order Status</th>
					<th data-head_slug="invoice_number">Invoice Number</th>
					<th data-head_slug="order_total">Order Total ($)</th>
					<th data-head_slug="gift_cards_total">Gift Cards Total ($)</th>
					<th data-head_slug="fulfilment_total">Fulfilment Total ($)</th>
					<th data-head_slug="delivery_total">Delivery Total ($)</th>
					<th data-head_slug="gst">GST</th>
					<th data-head_slug="campaign">Campaign</th>
					<th data-head_slug="po_number">PO Number</th>
					<th data-head_slug="client_reference">Client Reference</th>
					<th data-head_slug="sender_profile">Sender Profile</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>

		<?php return ob_get_clean();
	}
?>
