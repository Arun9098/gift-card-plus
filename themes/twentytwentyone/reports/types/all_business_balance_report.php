<?php
	add_shortcode('all_business_balance_report', 'func_all_business_balance_report');
	function func_all_business_balance_report(){ 
		ob_start(); ?>
		<table class="all_business_balance_reportsTable" id="all_business_balance_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="business_float_id">Business Float ID</th>
					<th data-head_slug="approved_for_client_billing_y_n">Approved for Client Billing (Y/N)</th>
					<th data-head_slug="business_abn">Business ABN</th>
					<th data-head_slug="business_website">Business Website</th>
					<th data-head_slug="business_team_users_ids">Business Team Users IDs? </th>
					<th data-head_slug="busineess_address_line_1">Busineess Address Line 1</th>
					<th data-head_slug="business_address_line_2">Business Address Line 2</th>
					<th data-head_slug="suburb">Suburb</th>
					<th data-head_slug="state">State</th>
					<th data-head_slug="country">Country</th>
					<th data-head_slug="postcode">Postcode</th>
					<th data-head_slug="business_currency">Business Currency</th>
					<th data-head_slug="balance">Balance</th>
					<th data-head_slug="prepaid_limit">Prepaid Limit</th>
					<th data-head_slug="top_up_notification_amount">Top up notification amount</th>
					<th data-head_slug="difference_before_topping_up">Difference before topping up</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>
		<?php return ob_get_clean();
	}

?>