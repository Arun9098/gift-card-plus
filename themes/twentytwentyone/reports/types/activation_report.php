<?php
	add_shortcode('activation_report', 'func_activation_report');
	function func_activation_report(){ 
		ob_start(); ?>
		<table class="activation_reportsTable" id="activation_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="order_created_date">Order created date</th>
					<th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="order_status">Order Status</th>
					<th data-head_slug="user">User</th>
					<th data-head_slug="business">Business</th>
					<th data-head_slug="card_name">Card Name</th>
					<th data-head_slug="card_denomination">Card Denomination</th>
					<th data-head_slug="card_number">Card Number</th>
					<th data-head_slug="card_status">Card  Status</th>
					<th data-head_slug="delivery_date">Delivery date</th>
					<th data-head_slug="delivery_time">Delivery Time</th>
					<th data-head_slug="delivery_method">Delivery Method</th>
					<th data-head_slug="delivery_email">Delivery email</th>
					<th data-head_slug="delivery_sms">Delivery SMS</th>
					<th data-head_slug="recipient_email">Recipient email</th>
					<th data-head_slug="recipient_mobile">Recipient Mobile</th>
					<th data-head_slug="activation_required_y_n">Activation Required (Y/N)</th>
					<th data-head_slug="activation_expiry_date">Activation Expiry Date</th>
					<th data-head_slug="activated_y_n">Activated (Y/N)</th>
					<th data-head_slug="date_activated">Date Activated</th>
					<th data-head_slug="activation_date_missed_y_n">Activation Date Missed (Y/N)</th>
					<th data-head_slug="card_expiry_date">Card Expiry Date</th>
					<th data-head_slug="supplier">Supplier</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>
		<?php return ob_get_clean();
	}

?>