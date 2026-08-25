<?php
	add_shortcode('billing_report', 'func_billing_report');
	function func_billing_report(){ 
		ob_start(); ?>
		<table class="billing_reportsTable" id="billing_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="order_date">Order Date</th>
					<th data-head_slug="order_time_">Order Time </th>
					<th data-head_slug="month_of_order">Month of Order</th>
					<th data-head_slug="order_name">Order Name</th>
					<th data-head_slug="user">User</th>
					<th data-head_slug="user_type">User Type</th>
					<th data-head_slug="business_name">Business Name</th>
					<th data-head_slug="business_id">Business ID</th>
					<th data-head_slug="business_float_id">Business Float ID</th>
					<th data-head_slug="approved_for_client_billing_y_n">Approved for Client Billing (Y/N)</th>
					<th data-head_slug="payment_type">Payment  Type</th>
					<th data-head_slug="order_status">Order Status</th>
					<th data-head_slug="invoice_number">Invoice Number</th>
					<th data-head_slug="payment_status">Payment Status</th>
					<th data-head_slug="order_total">Order Total ($)</th>
					<th data-head_slug="order_total_gift_cards">Order Total Gift Cards ($)</th>
					<th data-head_slug="order_total_fulfilment">Order Total Fulfilment ($)</th>
					<th data-head_slug="order_total_delivery_cost">Order Total Delivery cost</th>
					<th data-head_slug="order_total_gst">Order Total GST</th>
					<th data-head_slug="order_total_supplier_buy_price">Order Total Supplier Buy Price</th>
					<th data-head_slug="order_total_supplier_fulfiment_cost">Order Total Supplier Fulfiment Cost</th>
					<th data-head_slug="order_total_supplier_delivery_cost">Order Total Supplier Delivery Cost</th>
					<th data-head_slug="order_total_supplier_gst_cost">Order Total Supplier GST Cost</th>
					<th data-head_slug="gift_card_brand">Gift Card Brand</th>
					<th data-head_slug="gift_card_name">Gift Card Name</th>
					<th data-head_slug="gift_card_sku">Gift Card SKU</th>
					<th data-head_slug="gift_card_supplier_sku">Gift Card Supplier SKU</th>
					<th data-head_slug="gift_card_number">Gift Card Number</th>
					<th data-head_slug="card_type_variable_fixed">Card Type (Variable/ Fixed)</th>
					<th data-head_slug="gift_card_denomination">Gift Card Denomination</th>
					<th data-head_slug="gift_card_supplier_">Gift Card Supplier </th>
					<th data-head_slug="gift_card_set_sell_price">Gift Card Set Sell Price</th>
					<th data-head_slug="gift_card_price">Gift Card  Price</th>
					<th data-head_slug="offer_price_y_n">Offer Price (Y/N)</th>
					<th data-head_slug="gift_card_offer_price">Gift Card Offer Price</th>
					<th data-head_slug="gift_card_buy_price">Gift Card Buy Price</th>
					<th data-head_slug="gift_card_margin">Gift Card Margin $</th>
					<th data-head_slug="gift_card_margin">Gift Card Margin %</th>
					<th data-head_slug="gc_gift_card_fulfilment_price">GC+ Gift Card Fulfilment Price</th>
					<th data-head_slug="gift_card_supplier_fulfilment_cost">Gift Card Supplier Fulfilment Cost</th>
					<th data-head_slug="gift_cards_gift_card_delivery_price">Gift Cards+ Gift Card Delivery Price</th>
					<th data-head_slug="gift_card_supplier_delivery_cost_">Gift Card Supplier Delivery Cost </th>
					<th data-head_slug="gift_cards_gst">Gift Cards+ GST</th>
					<th data-head_slug="supplier_gst">Supplier GST</th>
					<th data-head_slug="gift_card_total_buy_price">Gift Card Total Buy Price</th>
					<th data-head_slug="gift_card_total_buy_price_inc_gst">Gift Card Total Buy Price inc GST</th>
					<th data-head_slug="gift_card_total_sell_price">Gift Card Total Sell Price</th>
					<th data-head_slug="gift_card_total_margin">Gift Card Total Margin $</th>
					<th data-head_slug="gift_card_total_margin">Gift Card Total Margin %</th>
					<th data-head_slug="gift_card_status">Gift Card Status</th>
					<th data-head_slug="gift_card_delivery_date">Gift Card Delivery Date</th>
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