<?php
	add_shortcode('supplier_product_report', 'func_supplier_product_report');
	function func_supplier_product_report(){
		ob_start(); ?>
		<table class="supplier_product_reportTable" id="supplier_product_reportTable">
			<thead>
				<tr>
					<th data-head_slug="product_id">Product ID</th>
					<th data-head_slug="product_name">Product Name</th>
					<th data-head_slug="product_sku">SKU</th>
					<th data-head_slug="product_status">Status</th>
					<th data-head_slug="supplier_id">Supplier ID</th>
					<th data-head_slug="supplier_name">Supplier Name</th>
					<th data-head_slug="brand">Brand</th>
					<th data-head_slug="denomination_type">Denomination Type</th>
					<th data-head_slug="min_price">Min Price ($)</th>
					<th data-head_slug="max_price">Max Price ($)</th>
					<th data-head_slug="buy_price">Buy Price ($)</th>
					<th data-head_slug="fulfilment_cost">Fulfilment Cost ($)</th>
					<th data-head_slug="delivery_cost">Delivery Cost ($)</th>
					<th data-head_slug="gst">GST</th>
					<th data-head_slug="supplier_sku">Supplier SKU</th>
					<th data-head_slug="parent_sku">Parent SKU</th>
					<th data-head_slug="redemption_info">Redemption Info</th>
					<th data-head_slug="is_blackhawk_product">Blackhawk (Y/N)</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>

		<?php return ob_get_clean();
	}
?>
