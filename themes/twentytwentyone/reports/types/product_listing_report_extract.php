<?php
	add_shortcode('product_listing_report_extract', 'func_product_listing_report_extract');
	function func_product_listing_report_extract(){ 
		ob_start(); ?>
		<table class="product_listing_report_extractsTable" id="product_listing_report_extractsTable">
	        <thead>
	            <tr>
	                <th data-head_slug="product_id">Product ID</th>
					<th data-head_slug="product_status">Product Status</th>
					<th data-head_slug="live_on_site_y_n">Live on Site (Y/N)</th>
					<th data-head_slug="parent_or_child_sku">Parent or Child SKU</th>
					<th data-head_slug="linked_to_parent">Linked to Parent</th>
					<th data-head_slug="parent_sku">Parent SKU</th>
					<th data-head_slug="sku">SKU </th>
					<th data-head_slug="supplier_sku">Supplier SKU</th>
					<th data-head_slug="gift_card_title">Gift Card Title</th>
					<th data-head_slug="brand">Brand</th>
					<th data-head_slug="supplier">Supplier</th>
					<th data-head_slug="short_description">Short Description</th>
					<th data-head_slug="long_description">Long Description</th>
					<th data-head_slug="terms_&_conditions">Terms & Conditions</th>
					<th data-head_slug="how_to_use">How to Use</th>
					<th data-head_slug="expiry_date_time">Expiry Date/ Time</th>
					<th data-head_slug="gift_card_expiry_type">Gift Card Expiry Type</th>
					<th data-head_slug="gift_card_expiry_date">Gift Card Expiry Date</th>
					<th data-head_slug="gift_card_expiry_period">Gift Card Expiry period</th>
					<th data-head_slug="period_type">Period type </th>
					<th data-head_slug="gift_card_activation_type">Gift Card Activation Type</th>
					<th data-head_slug="gift_card_activation_date">Gift Card Activation Date</th>
					<th data-head_slug="gift_card_activation_period">Gift Card Activation period</th>
					<th data-head_slug="period_type">Period type</th>
					<th data-head_slug="denomination_type">Denomination Type</th>
					<th data-head_slug="denomination">Denomination</th>
					<th data-head_slug="cost_price">Cost Price</th>
					<th data-head_slug="supplier_fulfilment_price">Supplier Fulfilment Price</th>
					<th data-head_slug="gst">GST</th>
					<th data-head_slug="gc+_fulfilment_costs">GC+ Fulfilment Costs</th>
					<th data-head_slug="preset_delivery_class">Preset delivery class</th>
					<th data-head_slug="delivery_cost">Delivery Cost</th>
					<th data-head_slug="discounted_price">Discounted Price</th>
					<th data-head_slug="discounted_price">Discounted Price</th>
					<th data-head_slug="discount_valid_from">Discount Valid From</th>
					<th data-head_slug="discount_valid_to">Discount Valid To</th>
					<th data-head_slug="icons">Icons</th>
					<th data-head_slug="tags">Tags</th>
					<th data-head_slug="categories">Categories</th>
					<th data-head_slug="featured_placements">Featured Placements</th>
					<th data-head_slug="extra_header">Extra Header</th>
					<th data-head_slug="add_stock_levels">Add stock levels</th>
					<th data-head_slug="stock_levels">Stock levels</th>
					<th data-head_slug="add_transaction_limits">Add transaction limits</th>
					<th data-head_slug="qty_per_transaction">Qty per transaction</th>
					<th data-head_slug="total_value_per_transaction">Total value per transaction</th>
					<th data-head_slug="available_for_all_users">Available for all users</th>
					<th data-head_slug="always_on">Always on</th>
					<th data-head_slug="onsite_from__date_time">Onsite from (date/ time)</th>
					<th data-head_slug="onsite_to__date_time">Onsite to (date/ time)</th>
					<th data-head_slug="created_by_admin">Created by (Admin)</th>
					<th data-head_slug="last_updated_by_admin">Last Updated by (Admin)</th>
					<th data-head_slug="brand_image">Brand image</th>
					<th data-head_slug="card_image_1_cover_image">Card Image 1 (Cover image)</th>
					<th data-head_slug="card_images">Card Images</th>
	            </tr>
	        </thead>
	        <tbody></tbody>
	    </table>
		<?php return ob_get_clean();
	}

?>