<?php
	add_shortcode('refunds_cancellation_report', 'func_refunds_cancellation_report');
	function func_refunds_cancellation_report(){
		ob_start(); ?>
		<table class="refunds_cancellation_reportTable" id="refunds_cancellation_reportTable">
			<thead>
				<tr>
					<th data-head_slug="order_date">Order Date</th>
					<th data-head_slug="order_time">Order Time</th>
					<th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="order_name">Order Name</th>
					<th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="order_status">Order Status</th>
					<th data-head_slug="payment_method">Payment Method</th>
					<th data-head_slug="invoice_number">Invoice Number</th>
					<th data-head_slug="order_total">Order Total ($)</th>
					<th data-head_slug="gift_cards_total">Gift Cards Total ($)</th>
					<th data-head_slug="total_refunded">Total Refunded ($)</th>
					<th data-head_slug="refund_reason">Refund Reason</th>
					<th data-head_slug="refund_date">Refund Date</th>
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
