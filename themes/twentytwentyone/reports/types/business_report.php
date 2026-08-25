<?php
	add_shortcode('business_report', 'func_business_report');
	function func_business_report(){
		ob_start(); ?>
		<table class="business_reportTable" id="business_reportTable">
			<thead>
				<tr>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_float_id">Float ID</th>
					<th data-head_slug="approved_for_client_billing_y_n">Client Billing (Y/N)</th>
					<th data-head_slug="billing_type">Billing Type</th>
					<th data-head_slug="business_abn">ABN</th>
					<th data-head_slug="business_website">Website</th>
					<th data-head_slug="email">Email</th>
					<th data-head_slug="mobile">Mobile</th>
					<th data-head_slug="address_line_1">Address Line 1</th>
					<th data-head_slug="address_line_2">Address Line 2</th>
					<th data-head_slug="suburb">Suburb</th>
					<th data-head_slug="state">State</th>
					<th data-head_slug="country">Country</th>
					<th data-head_slug="postcode">Postcode</th>
					<th data-head_slug="business_currency">Currency</th>
					<th data-head_slug="float_balance">Float Balance</th>
					<th data-head_slug="prepaid_limit">Prepaid Limit</th>
					<th data-head_slug="team_user_ids">Team User IDs</th>
					<th data-head_slug="total_orders">Total Orders</th>
					<th data-head_slug="total_spend">Total Spend ($)</th>
					<th data-head_slug="account_created_date">Account Created Date</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>

		<?php return ob_get_clean();
	}
?>
