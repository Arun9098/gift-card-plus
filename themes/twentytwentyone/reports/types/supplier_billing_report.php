<?php
	add_shortcode('supplier_billing_report', 'func_supplier_billing_report');
	function func_supplier_billing_report(){ 
		ob_start(); ?>
		<table class="supplier_billing_reportsTable" id="supplier_billing_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="supplier">Supplier</th>
					<th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="supplier_po">Supplier PO</th>
					<th data-head_slug="order_date">Order Date</th>
					<th data-head_slug="order_status">Order Status</th>
					<th data-head_slug="gift_card_name">Gift Card Name</th>
					<th data-head_slug="gift_card_sku">Gift Card SKU</th>
					<th data-head_slug="suppliier_gift_card_sku">Suppliier Gift Card SKU</th>
					<th data-head_slug="gift_card_number">Gift Card Number</th>
					<th data-head_slug="card_type_variable_fixed">Card Type (Variable/ Fixed)</th>
					<th data-head_slug="gift_card_denomination">Gift Card Denomination</th>
					<th data-head_slug="gift_card_buy_price">Gift Card Buy Price</th>
					<th data-head_slug="gift_card_supplier_fulfilment_cost">Gift Card Supplier Fulfilment cost</th>
					<th data-head_slug="gift_card_supplier_delivery_cost">Gift Card Supplier Delivery cost</th>
					<th data-head_slug="total_gift_card_buy_price">Total Gift Card Buy Price</th>
					<th data-head_slug="gst">GST</th>
					<th data-head_slug="card_status">Card Status</th>
					<th data-head_slug="order_total_supplier_buy_price">Order Total Supplier Buy Price</th>
					<th data-head_slug="order_total_supplier_fulfilment_price">Order Total Supplier Fulfilment Price</th>
					<th data-head_slug="order_total_supplier_delivery_cost">Order Total Supplier Delivery cost</th>
					<th data-head_slug="order_total_supplier_gst">Order Total Supplier GST</th>
					<th data-head_slug="order_total_supplier_costs">Order Total Supplier costs</th>
					<th data-head_slug="supplier_payment_due">Supplier Payment Due</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>
		<?php return ob_get_clean();
	}

?>