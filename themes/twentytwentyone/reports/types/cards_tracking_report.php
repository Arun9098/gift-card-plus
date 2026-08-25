<?php
	add_shortcode('cards_tracking_report', 'func_cards_tracking_report');
	function func_cards_tracking_report(){ 
		ob_start(); ?>
		<!-- <div id="loadingGiftcards" style="padding: 12px; background: #f0f0f0; border: 1px solid #ccc; margin-bottom: 10px; font-weight: bold;">
			Loading gift cards… Please wait.
		</div> -->
		<table class="cards_tracking_reportsTable" id="cards_tracking_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="order_date">Order Date</th>
	                <th data-head_slug="gift_card_name">Gift Card Name</th>
	                <th data-head_slug="gift_card_sku">Gift Card SKU</th>
	                <th data-head_slug="gift_card_denomination">Gift Card Denomination</th>
	                <th data-head_slug="gift_card_number">Gift Card Number</th>
	                <th data-head_slug="brand">Brand</th>
	                <th data-head_slug="order_no">Order Number</th>
	                <th data-head_slug="order_name">Order Name</th>
	                <th data-head_slug="order_status">Order Status</th>
	                <th data-head_slug="order_user">User</th>
	                <th data-head_slug="business_name">Business</th>
					<th data-head_slug="delivery_date">Delivery date</th>
					<th data-head_slug="delivery_time">Delivery Time</th>
					<th data-head_slug="delivery_method">Delivery Method</th>
					<th data-head_slug="delivery_email">Delivery email</th>
					<th data-head_slug="delivery_sms ">Delivery SMS </th>
					<th data-head_slug="card_status">Card  Status</th>
					<th data-head_slug="sender_profile">Sender Profile</th>
					<th data-head_slug="gift_card_activation_set_y_n">Gift Card Activation Set (Y/N)</th>
					<th data-head_slug="gift_card_activated_y_n">Gift Card Activated (Y/N)</th>
					<th data-head_slug="gift_card_expiry Date">Gift Card  Expiry Date</th>
					<th data-head_slug="gift_card_activation_type">Gift Card Activation Type</th>
					<th data-head_slug="gift_card_activation_expiry_date">Gift Card Activation Expiry Date</th>
					<th data-head_slug="campaign">Campaign</th>
					<th data-head_slug="supplier">Supplier</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>
		<?php return ob_get_clean();
	}

?>