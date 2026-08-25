<?php
	add_shortcode('supplier_order_report', 'func_supplier_order_report');
	function func_supplier_order_report(){ 
		ob_start(); ?>
		<table class="supplier_order_reportsTable" id="supplier_order_reportsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="supplier">Supplier</th>
					<th data-head_slug="order_number">Order Number</th>
					<th data-head_slug="supplier_po">Supplier PO</th>
					<th data-head_slug="order_date">Order Date</th>
					<th data-head_slug="order_status">Order Status</th>
					<th data-head_slug="card_name">Card Name</th>
					<th data-head_slug="card_number">Card Number</th>
					<th data-head_slug="card_type_variable_fixed">Card Type (Variable/ Fixed)</th>
					<th data-head_slug="denomination">Denomination</th>
					<th data-head_slug="card_buy_price">Card Buy Price</th>
					<th data-head_slug="card_supplier_fulfilment_cost">Card Supplier Fulfilment cost</th>
					<th data-head_slug="card_supplier_delivery_cost">Card Supplier Delivery cost</th>
					<th data-head_slug="gst">GST</th>
					<th data-head_slug="card_status">Card Status</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>
		<?php return ob_get_clean();
	}

?>