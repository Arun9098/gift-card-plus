<?php
	add_shortcode('individual_business_balance_statement', 'func_individual_business_balance_statement');
	function func_individual_business_balance_statement(){
		ob_start(); ?>
		<table class="individual_business_balance_statementTable" id="individual_business_balance_statementTable">
			<thead>
				<tr>
					<th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="approved_for_client_billing">Approved for Client Billing</th>
					<th data-head_slug="business_billing_type">Business Billing Type</th>
					<th data-head_slug="date_time">Date/Time</th>
					<th data-head_slug="user">User</th>
					<th data-head_slug="balance_type">Balance Type</th>
					<th data-head_slug="action">Action</th>
					<th data-head_slug="business_float_id">Business Float ID</th>
					<th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="invoice_number">Invoice Number</th>
					<th data-head_slug="status">Status</th>
					<th data-head_slug="amount">Amount ($)</th>
					<th data-head_slug="reference">Reference</th>
					<th data-head_slug="balance">Balance ($)</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
		<?php return ob_get_clean();
	}
?>
