<?php
	add_shortcode('brand_listing_report', 'func_brand_listing_report');
	function func_brand_listing_report(){
		ob_start(); ?>
		<table class="brand_listing_reportTable" id="brand_listing_reportTable">
			<thead>
				<tr>
					<th data-head_slug="brand_id">Brand ID</th>
					<th data-head_slug="brand_name">Brand Name</th>
					<th data-head_slug="brand_slug">Brand Slug</th>
					<th data-head_slug="brand_description">Description</th>
					<th data-head_slug="total_products">Total Products</th>
					<th data-head_slug="active_products">Active Products</th>
					<th data-head_slug="supplier">Supplier</th>
					<th data-head_slug="brand_thumbnail">Thumbnail URL</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>

		<?php return ob_get_clean();
	}
?>
