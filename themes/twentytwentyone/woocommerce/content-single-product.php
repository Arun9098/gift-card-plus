<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * Fully customized single product content template for Gift Cards Plus theme
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined('ABSPATH') || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action('woocommerce_before_single_product');

if (post_password_required()) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}

// Get product data
$product_id = $product->get_id();
$product_title = $product->get_name();
$product_price = $product->get_price_html();
$product_description = $product->get_description();
$product_short_description = $product->get_short_description();
$product_sku = $product->get_sku();

$buyer_upload   = get_field('buyer_upload', $product_id);
// echo '<pre>'; print_r($buyer_upload); echo '</pre>';
// exit;

$expiry_type   = get_field('gift_card_expiry_type', $product_id);
$expiry_date   = get_field('gift_card_expiry_date', $product_id);
$duration      = get_field('gift_card_expiry_duration', $product_id);
$unit          = get_field('gift_card_expiry_unit', $product_id);

$output = get_field('_expire_date', $product_id);

// if ($expiry_type === 'no_expiry') {
//     // Do nothing (empty)
//     $output = '';

// } elseif ($expiry_type === 'gift_set_date') {

//     if (!empty($expiry_date)) {
//         $output = 'Expires on: ' . $expiry_date;
//     }

// } elseif ($expiry_type === 'expiry_period_starts_on_purchase') {

//     if (!empty($duration) && !empty($unit)) {
//         $output = "Expires {$duration} {$unit} after purchase";
//     }

// } elseif ($expiry_type === 'expiry_period_starts_on_activation') {

//     if (!empty($duration) && !empty($unit)) {
//         $output = "Expires {$duration} {$unit} after activation";
//     }
// }

// Get product tags (Offers)
$product_tags = get_the_terms($product_id, 'product_tag');
$tag_names = [];
$tag_class = '';
if ($product_tags && !is_wp_error($product_tags)) {
	foreach ($product_tags as $tag) {
		$tag_names[] = $tag->name;
	}
	$lower_tags = array_map(function ($tag) {
		return strtolower(trim($tag));
	}, $tag_names);
	if (in_array('20% off', $lower_tags)) {
		$tag_class = 'off';
	}
	if (in_array('hot offer', $lower_tags)) {
		$tag_class = 'offer';
	}
}

// Get product icons (taxonomy 'icons') for display at top of card
$product_icons = get_the_terms($product_id, 'icons');
if (!is_array($product_icons)) {
	$product_icons = $product_icons && !is_wp_error($product_icons) ? $product_icons : array();
}

// Get offers associated with this product (offer post type)
$product_offer_ids = get_post_meta($product_id, '_product_offers', true);
$product_offer_titles = [];
$product_offers_data = [];
if (!empty($product_offer_ids) && is_array($product_offer_ids)) {
	$offer_posts = get_posts([
		'post_type' => 'offer',
		'post__in' => array_map('intval', $product_offer_ids),
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	foreach ($offer_posts as $offer_post) {
		$product_offer_titles[] = $offer_post->post_title;
		if (function_exists('get_offer_meta')) {
			$offer_meta = get_offer_meta($offer_post->ID);
			$product_offers_data[] = [
				'id' => $offer_post->ID,
				'title' => $offer_post->post_title,
				'description' => isset($offer_meta['description']) ? $offer_meta['description'] : '',
				'terms' => isset($offer_meta['terms']) ? $offer_meta['terms'] : '',
			];
		} else {
			$product_offers_data[] = [
				'id' => $offer_post->ID,
				'title' => $offer_post->post_title,
				'description' => get_post_meta($offer_post->ID, '_offer_description', true) ?: $offer_post->post_content,
				'terms' => get_post_meta($offer_post->ID, '_offer_terms', true),
			];
		}
	}
}

// Get gallery images
$gallery_ids = $product->get_gallery_image_ids();
$featured_image_id = $product->get_image_id();

// Get current user details for "Myself" option
$current_user_name = '';
$current_user_email = '';
$current_user_phone = '';
if (is_user_logged_in()) {
	$current_user = wp_get_current_user();
	$first_name = get_user_meta($current_user->ID, 'first_name', true);
	$last_name = get_user_meta($current_user->ID, 'last_name', true);
	$current_user_name = trim($first_name . ' ' . $last_name);
	if (empty($current_user_name)) {
		$current_user_name = $current_user->display_name ?: $current_user->user_login;
	}
	$current_user_email = $current_user->user_email;

	// Get phone number - check multiple possible meta keys
	$current_user_phone = get_user_meta($current_user->ID, 'mobile', true);
	if (empty($current_user_phone)) {
		$current_user_phone = get_user_meta($current_user->ID, 'billing_phone', true);
	}
	if (empty($current_user_phone)) {
		$current_user_phone = get_user_meta($current_user->ID, 'phone', true);
	}
}

// Get product denomination and price information
$denomination_type = get_field('denomination_type', $product_id);
if (empty($denomination_type)) {
	$denomination_type = get_post_meta($product_id, 'denomination_type', true);
}
$regular_price = $product->get_regular_price();
$sale_price = $product->get_sale_price();
$is_discounted = get_field('discounted_price_checkbox', $product_id);
$discounted_from = get_post_meta($product_id, '_discount_valid_from', true);
$discounted_to = get_post_meta($product_id, '_discount_valid_to', true);

// Check if discount is currently active
$discount_active = false;
if ($is_discounted && $is_discounted === 'Yes' && $sale_price) {
	$current_time = current_time('timestamp');
	$discount_from_ts = !empty($discounted_from) ? strtotime($discounted_from) : 0;
	$discount_to_ts = !empty($discounted_to) ? strtotime($discounted_to) : PHP_INT_MAX;

	if ($current_time >= $discount_from_ts && $current_time <= $discount_to_ts) {
		$discount_active = true;
	}
}

$display_price = $discount_active && $sale_price ? $sale_price : $regular_price;
$original_price = $regular_price;

$min_price = $regular_price;
$max_price = $regular_price;
$price_intervals = 1;
$original_min_price = $regular_price;
$original_max_price = $regular_price;

if ($denomination_type === 'fixed') {
	$min_price = $regular_price;
	$max_price = $regular_price;
	$price_intervals = 1;
	$original_min_price = $regular_price;
	$original_max_price = $regular_price;
} elseif ($denomination_type === 'variable') {
	// Get variable_range_from
	$min_price = get_field('variable_range_from', $product_id);
	if (empty($min_price)) {
		$min_price = get_post_meta($product_id, 'variable_range_from', true);
	}
	if (empty($min_price)) {
		$min_price = $regular_price;
	}

	// Get variable_range_to
	$max_price = get_field('variable_range_to', $product_id);
	if (empty($max_price)) {
		$max_price = get_post_meta($product_id, 'variable_range_to', true);
	}
	if (empty($max_price)) {
		$max_price = $regular_price;
	}

	// Get _reedem_at_intervals
	$price_intervals = get_field('_reedem_at_intervals', $product_id);
	if (empty($price_intervals)) {
		$price_intervals = get_post_meta($product_id, '_reedem_at_intervals', true);
	}
	if (empty($price_intervals)) {
		$price_intervals = 1;
	}
	$original_min_price = floatval($min_price);
	$original_max_price = floatval($max_price);
	// When within discounted date range: discounted range = lowest denomination discounted then ratio'd up (e.g. 10–1000 sell, 9.90 discount → 9.90–990)
	if ($discount_active && $sale_price !== null && $sale_price !== '') {
		$sale_price_float = floatval($sale_price);
		$min_price = $sale_price_float;
		$max_price = $original_min_price > 0 ? round(($original_max_price / $original_min_price) * $sale_price_float, 2) : $min_price;
		$max_price = max($min_price, $max_price);
	}
}

$product_image_url = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'medium') : wc_placeholder_img_src();

// Get SMS delivery cost from shipping class (used for SMS / Email+SMS delivery method)
$sms_delivery_cost = 1; // Default if no shipping class selected
$shipping_class_id = $product->get_shipping_class_id();
if ($shipping_class_id > 0) {
	$shipping_class_term = get_term($shipping_class_id, 'product_shipping_class');
	if ($shipping_class_term && !is_wp_error($shipping_class_term)) {
		$term_name = $shipping_class_term->name;
		if (preg_match('/\$(\d+\.?\d*)/', $term_name, $matches)) {
			$sms_delivery_cost = floatval($matches[1]);
		}
		if ($sms_delivery_cost == 0) {
			$term_meta_cost = get_term_meta($shipping_class_id, 'cost', true);
			if (!empty($term_meta_cost) && is_numeric($term_meta_cost)) {
				$sms_delivery_cost = floatval($term_meta_cost);
			}
		}
	}
}
if ($sms_delivery_cost <= 0 || !is_finite($sms_delivery_cost)) {
	$sms_delivery_cost = 1;
}

$parent_pro = get_post_meta($product_id, 'sku_type', true);

// Get current product SKU
// $product = wc_get_product($product_id);
$current_sku = $product ? $product->get_sku() : '';

// $current_sku = wc_get_product_id_by_sku($product_id);


$response = [];

$children = get_posts([
	'post_type' => 'product',
	'post_status' => 'publish',
	'posts_per_page' => -1,
	'fields' => 'ids',
	'meta_query' => [
		'relation' => 'AND',
		[
			'key' => 'parent_sku',
			'value' => $current_sku,
			'compare' => '='
		],
		[
			'key' => 'sku_type',
			'value' => 'child',
			'compare' => '='
		]
	],
]);


if (strtolower(trim($parent_pro)) === 'child') {

    // Step 3: Get parent_sku from current product
    $parent_sku = get_post_meta($product_id, 'parent_sku', true);

    if (!empty($parent_sku)) {

        // Step 4: Find parent product using SKU
        $parent_products = get_posts([
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => [
				'relation' => 'AND',
				[
					'key' => 'parent_sku',
					'value' => $parent_sku,
					'compare' => '='
				],
				[
					'key' => 'sku_type',
					'value' => 'child',
					'compare' => '='
				]
			],
        ]);

        if (!empty($parent_products)) {
			$children_ids = implode(',', $parent_products);
        }
    }
}

$is_child = false;

if(strtolower(trim($parent_pro)) !== 'parent'){
	$is_child = true;
}

$is_in_wishlist = false;
$wishlist_class = '';
$wishlist_title = 'Add to wishlist';
$heart_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

if (is_user_logged_in()) {
	$user_id = get_current_user_id();
	$user_wishlist = get_user_meta($user_id, 'user_wishlist', true);

	if (!is_array($user_wishlist)) {
		$user_wishlist = array();
	}
	$user_wishlist = array_filter(array_map('intval', $user_wishlist));

	$is_in_wishlist = in_array($product_id, $user_wishlist);
	$wishlist_class = $is_in_wishlist ? 'fill' : '';
	$wishlist_title = $is_in_wishlist ? 'Remove from wishlist' : 'Add to wishlist';
}

	
	

?>


<div id="product-<?php the_ID(); ?>" <?php wc_product_class('custom-single-product', $product); ?>>

	<!-- Message Display Container -->
	<div id="product-message-container" class="product-message-container" style="display: none;">
		<div class="product-message-content">
			<span class="product-message-text"></span>
			<button type="button" class="product-message-close" aria-label="Close message">&times;</button>
		</div>
	</div>

	<div class="single-product-header custom-product-wrapper">

		<!-- Left Side - Product Info -->
		<div class="custom-product-info product-top-left">

			<!-- Product Name -->
			<h1 class="custom-product-title"><?php echo esc_html($product_title); ?></h1>

			<!-- Icons and Offers -->
			<?php
			$show_offers_block = !empty($product_icons) || !empty($product_offer_titles);
			$offers_label = '';
			if (!empty($product_offer_titles)) {
				$offers_label = implode(', ', $product_offer_titles);
			}
			if ($show_offers_block): ?>
				<div class="custom-product-offers">
					<?php if (!empty($product_icons)): ?>
						<div class="custom-product-icons">
							<?php foreach ($product_icons as $icon):
								$icon_image_id = get_term_meta($icon->term_id, 'icon_image', true);
								$icon_image_url = $icon_image_id ? wp_get_attachment_image_url($icon_image_id, 'thumbnail') : '';
								$icon_color_raw = get_term_meta($icon->term_id, 'icon_color', true);
								$icon_color = $icon_color_raw ? sanitize_hex_color($icon_color_raw) : '';
								$circle_style = $icon_color ? 'background-color:' . $icon_color : '';
								?>
								<span
									class="custom-product-icon-circle<?php echo $circle_style ? '' : ' custom-product-icon-circle--default'; ?>"
									<?php echo $circle_style ? ' style="' . esc_attr($circle_style) . '"' : ''; ?>
									title="<?php echo esc_attr($icon->name); ?>">
									<?php if ($icon_image_url): ?>
										<img src="<?php echo esc_url($icon_image_url); ?>" alt="<?php echo esc_attr($icon->name); ?>"
											class="custom-product-icon-img" />
									<?php else: ?>
										<span class="custom-product-icon-text"><?php echo esc_html($icon->name); ?></span>
									<?php endif; ?>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<div class="custom-product-offers-label">
						<?php
						if (!empty($product_offers_data)):
							$offer_links = [];
							foreach ($product_offers_data as $idx => $offer_data):
								$offer_links[] = '<button type="button" class="product-offer-link" data-offer-id="' . esc_attr($offer_data['id']) . '" aria-haspopup="dialog" aria-expanded="false">' . esc_html($offer_data['title']) . '</button>';
							endforeach;
							echo implode(', ', $offer_links);
						else:
							echo esc_html($offers_label);
						endif;
						?>
					</div>
				</div>
				<?php

				// echo 'offer_data';
				// echo '<pre>'; print_r($offer_data); echo '</pre>';
				// exit;
				// Hidden content for each offer (used by modal)
				if (!empty($product_offers_data)):
					foreach ($product_offers_data as $offer_data):
						?>
						<div id="offer-content-<?php echo (int) $offer_data['id']; ?>" class="offer-modal-content-source"
							data-offer-id="<?php echo (int) $offer_data['id']; ?>"
							data-offer-title="<?php echo esc_attr($offer_data['title']); ?>" style="display: none;"
							aria-hidden="true">
							<div class="offer-detail-description"><?php echo wp_kses_post($offer_data['description'] ?: '—'); ?>
							</div>
							<div class="offer-detail-terms">
								<strong><?php esc_html_e('Terms &amp; Conditions', 'woocommerce'); ?></strong>
								<div class="offer-terms-text"><?php echo wp_kses_post($offer_data['terms'] ?: '—'); ?></div>
							</div>
						</div>
						<?php
					endforeach;
				endif;
				?>
				<!-- Offer details modal -->
				<div id="product-offer-modal" class="product-offer-modal" role="dialog" aria-modal="true"
					aria-labelledby="product-offer-modal-title" aria-hidden="true" style="display: none;">
					<div class="product-offer-modal-overlay"></div>
					<div class="product-offer-modal-content">
						<div class="product-offer-modal-header">
							<h2 id="product-offer-modal-title" class="product-offer-modal-title"></h2>
							<button type="button" class="product-offer-modal-close"
								aria-label="<?php esc_attr_e('Close', 'woocommerce'); ?>">&times;</button>
						</div>
						<div class="product-offer-modal-body">
							<div class="product-offer-modal-description"></div>
							<div class="product-offer-modal-terms"></div>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Description -->
			<?php if ($product_description || $product_short_description): ?>
				<div class="custom-product-description">
					<div class="custom-product-description-content">
						<?php if ($product_short_description): ?>
							<?php echo wp_kses_post($product_short_description); ?>
						<?php elseif ($product_description): ?>
							<?php echo wp_kses_post($product_description); ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="custom-product-expiry">
				<?php echo esc_html($output); ?>
			</div>

			<!-- SKU -->
			<?php if ($product_sku): ?>
				<div class="custom-product-sku">
					<div class="custom-product-sku-label"><?php esc_html_e('SKU: ', 'woocommerce'); ?></div>
					<div class="custom-product-sku-value"><?php echo esc_html($product_sku); ?></div>
				</div>
			<?php endif; ?>

			<!-- Add to Wishlist -->
			<?php if ($is_child): ?>

				<div class="custom-product-wishlist">
					<?php if (is_user_logged_in()): ?>
						<button class="custom-wishlist-button <?php echo esc_attr($wishlist_class); ?>"
							data-product-id="<?php echo esc_attr($product_id); ?>"
							title="<?php echo esc_attr($wishlist_title); ?>">
							<?php echo $heart_icon_svg; ?>
							<span><?php echo esc_html($wishlist_title); ?></span>
						</button>
					<?php else: ?>
						<a href="<?php echo esc_url(site_url('/user-login')); ?>" class="custom-wishlist-button"
							title="<?php esc_attr_e('Login to add to wishlist', 'woocommerce'); ?>">
							<?php echo $heart_icon_svg; ?>
							<span><?php esc_html_e('Add to Wishlist', 'woocommerce'); ?></span>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Add to Cart -->
			<div class="custom-product-cart">
				<?php
				/**
				 * Hook: woocommerce_single_product_summary.
				 *
				 * @hooked woocommerce_template_single_add_to_cart - 30
				 */
				// do_action( 'woocommerce_single_product_summary' );
				?>
			</div>

		</div>

		<!-- Right Side - Product Image -->
		<div class="custom-product-image-wrapper product-top-right">
			<div class="custom-product-main-image">
				<?php if ($featured_image_id): ?>
					<img id="main-product-image"
						src="<?php echo esc_url(wp_get_attachment_image_url($featured_image_id, 'full')); ?>"
						alt="<?php echo esc_attr($product_title); ?>">
				<?php else: ?>
					<img id="main-product-image" src="<?php echo esc_url(wc_placeholder_img_src()); ?>"
						alt="<?php echo esc_attr($product_title); ?>">
				<?php endif; ?>
			</div>


		</div>

	</div>

	<!-- Product Accordions -->
	<div class="custom-product-accordions">
		<!-- Choose your gift card -->
		<div class="custom-accordion-item active">
			<div class="custom-accordion-header">
				<h3 class="custom-accordion-title"> 
					<?php if ($is_child) : ?>
						<?php esc_html_e('1. Choose your gift card', 'woocommerce'); ?>
						<?php else : ?>
							<?php esc_html_e('Choose your gift card', 'woocommerce'); ?>
					<?php endif; ?>
				</h3>
				<span class="custom-accordion-icon">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
							stroke-linejoin="round" />
					</svg>
				</span>
			</div>

			<?php if($is_child) : ?>
				
				<div class="custom-accordion-content">
					<div class="custom-accordion-inner">
						<!-- Step Form Pattern -->
						<div class="step-form-pattern">
							<div class="step-form-container">
								<div class="step-item active" data-step="1">
									<div class="step-number">1</div>
									<div class="step-label"><?php esc_html_e('Choose your gift card', 'woocommerce'); ?>
									</div>
								</div>
								<div class="step-connector"></div>
								<div class="step-item" data-step="2">
									<div class="step-number">2</div>
									<div class="step-label"><?php esc_html_e('Personalise your order', 'woocommerce'); ?>
									</div>
								</div>
								<div class="step-connector"></div>
								<div class="step-item" data-step="3">
									<div class="step-number">3</div>
									<div class="step-label"><?php esc_html_e('Delivery', 'woocommerce'); ?></div>
								</div>
							</div>
						</div>
						<!-- Selected Product Display -->
						<div class="selected-gift-card-product">
							<div class="selected-product-row">

								<div class="selected-product-details pt-30">

									<!-- Quantity Selection -->
									<div class="quantity-selection">
										<label class="quantity-label">
											<?php esc_html_e('Qty', 'woocommerce'); ?>
										</label>

										<?php
										// Get quantity per transaction from SCF/custom field

										$transaction_limit = get_field('_quantity_per_transaction', $product_id);
										if ($transaction_limit === '' || $transaction_limit === null) {
											$transaction_limit = get_post_meta($product_id, '_quantity_per_transaction', true);
										}
										$quantity_limit = (int) $transaction_limit;

										$add_transaction_limit = get_field('add_transaction_limit_checkbox', $product_id);
										if ($add_transaction_limit === '' || $add_transaction_limit === null) {
											$add_transaction_limit = get_post_meta($product_id, 'add_transaction_limit_checkbox', true);
										}

										$add_transaction_limit = strtolower((string) $add_transaction_limit);
										$is_limit_enabled = in_array($add_transaction_limit, ['yes', '1', 'true'], true);

										
										if (!$is_limit_enabled) {

											$max_quantity = 10;

										} else {

											$max_quantity = ($quantity_limit > 0) ? $quantity_limit : 1;

											if (function_exists('WC') && WC()->cart) {

												$cart_qty_for_product = 0;

												foreach (WC()->cart->get_cart() as $cart_item) {
													if ((int) $cart_item['product_id'] === (int) $product_id) {
														$cart_qty_for_product += (int) $cart_item['quantity'];
													}
												}

												$max_quantity = max(0, $max_quantity - $cart_qty_for_product);
											}
										}

										$is_disabled = ($max_quantity <= 0);
										?>

										<select class="quantity-select" name="gift_card_quantity" id="gift_card_quantity"
											<?php echo $is_disabled ? 'disabled' : ''; ?>>

											<?php if ($is_disabled): ?>

												<option value="0" selected>
													<?php esc_html_e('Not available', 'woocommerce'); ?>
												</option>

											<?php else: ?>

												<?php for ($qty = 1; $qty <= $max_quantity; $qty++): ?>
													<option value="<?php echo esc_attr($qty); ?>" <?php selected($qty, 1); ?>>
														<?php echo esc_html($qty); ?>
													</option>
												<?php endfor; ?>

											<?php endif; ?>

										</select>
									</div>


									<!-- Price Selection - Dynamically loaded via AJAX (like manual order selected-product-container) -->

									<div class="price-selection">
										<label
											class="price-label"><?php esc_html_e('Choose Amount', 'woocommerce'); ?></label>
										<div id="price-selection-dynamic" class="amount-buttons-wrapper">
											<span
												class="price-loading"><?php esc_html_e('Loading prices...', 'woocommerce'); ?></span>
										</div>
										<div class="custom-amount-input-wrapper" style="display: none; margin-top: 12px;">
											<input type="number" class="custom-amount-input-field"
												placeholder="<?php esc_attr_e('Enter Amount', 'woocommerce'); ?>" min="0"
												max="0" step="1">
										</div>
										<div class="custom-amount-error"
											style="display: none; margin-top: 8px; color: #d32f2f; font-size: 14px;"></div>
									</div>

									<!-- Price Display -->
									<div class="product-price-wrapper">
										<p class="product-price-display">
											Price: <span class="price" id="dynamic-price-display">$0.00</span>
										</p>
									</div>
								</div>
							</div>
						</div>


						<?php
						$sibling_products = array_filter($parent_products ?? [], fn($id) => $id != $product_id);
						if (!empty($sibling_products)) { ?>
					    <div class="related-child-products">
					        <label class="price-label">Other Amounts Available</label>
					        <div id="related-products" class="amount-buttons-wrapper">
					            <?php
					                foreach ($sibling_products as $child_id) {
					                    $product = wc_get_product($child_id);
					                    if (!$product) continue;
					                    $price = get_post_meta($child_id, '_regular_price', true);
					                    $link = get_permalink($child_id);

					                    // Format price: remove .00 for whole numbers, keep decimals otherwise
					                    $price_num = floatval($price);
					                    if ($price_num == intval($price_num)) {
					                        $price_formatted = '$' . number_format($price_num, 0);
					                    } else {
					                        $price_formatted = '$' . number_format($price_num, 2);
					                    }
					                    ?>
					                    <button type="button" class="amount-btn sibling-nav-btn" data-href="<?php echo esc_url($link); ?>">
					                        <?php echo esc_html($price_formatted); ?>
					                    </button>
					                    <?php
					                }
					            ?>
					        </div>
					    </div>
						<?php } ?>
						<div class="accordion-update-btn-wrapper">
							<button type="button"
								class="accordion-update-btn"><?php esc_html_e('Continue', 'woocommerce'); ?></button>
						</div>
					</div>
				</div>

			<?php else : ?>

				<div class="custom-accordion-content">
					<div class="custom-accordion-inner">
						<!-- Selected Product Display -->
						<div class="selected-gift-card-product">
							<div class="selected-product-row">
								<div class="selected-product-details pt-30">
									<div class="product-selection">
										<label class="price-label">Choose Product</label>

										<div class="amount-buttons-wrapper">

											<?php 
											if (!empty($children)) {
												foreach ($children as $child_id) {
													$product = wc_get_product($child_id);

													if ($product) {
														$product_id = $product->get_id();



														$product_price = get_post_meta($product_id,'_regular_price',true);
														$product_name  = $product->get_name();
														$product_sku   = $product->get_sku();
														$product_image = wp_get_attachment_url($product->get_image_id());
														$product_url = get_permalink($child_id);

														?>

														<a href="<?= esc_url(get_permalink($child_id)); ?>"
															class="amount-btn"
															data-product-id="<?= esc_attr($child_id); ?>">

															<?= wc_price($product_price); ?>

														</a>

														<?php
													}
												}
											}
											?>

										</div>

										<div class="custom-amount-input-wrapper" style="display: none; margin-top: 12px;"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

			<?php endif; ?>

		</div>
	

		<?php if($is_child) : ?>
			<!-- Personalise your order -->
			<div class="custom-accordion-item">
				<div class="custom-accordion-header">
					<h3 class="custom-accordion-title"><?php esc_html_e('2. Personalise your order', 'woocommerce'); ?></h3>
					<span class="custom-accordion-icon">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" />
						</svg>
					</span>
				</div>
				<div class="custom-accordion-content">
					<div class="custom-accordion-inner">
						<!-- Step Form Pattern -->
						<div class="step-form-pattern">
							<div class="step-form-container">
								<div class="step-item active" data-step="1">
									<div class="step-number">1</div>
									<div class="step-label"><?php esc_html_e('Choose your gift card', 'woocommerce'); ?>
									</div>
								</div>
								<div class="step-connector"></div>
								<div class="step-item" data-step="2">
									<div class="step-number">2</div>
									<div class="step-label"><?php esc_html_e('Personalise your order', 'woocommerce'); ?>
									</div>
								</div>
								<div class="step-connector"></div>
								<div class="step-item" data-step="3">
									<div class="step-number">3</div>
									<div class="step-label"><?php esc_html_e('Delivery', 'woocommerce'); ?></div>
								</div>
							</div>
						</div>
						<!-- Recipient Type Selection -->
						<div class="recipient-type-selection pt-30">
							<div class="recipient-type-options">
								<label class="recipient-type-option">
									<input type="radio" name="recipient_type" value="myself" <?php echo is_user_logged_in() ? 'checked' : ''; ?>>
									<span class="option-text">
										<strong><?php esc_html_e('Myself', 'woocommerce'); ?></strong>
									</span>
								</label>
								<label class="recipient-type-option">
									<input type="radio" name="recipient_type" value="someone_else" <?php echo !is_user_logged_in() ? 'checked' : ''; ?>>
									<span class="option-text">
										<strong><?php esc_html_e('Someone else', 'woocommerce'); ?></strong>
									</span>
								</label>
							</div>
						</div>

						<!-- Price Display for Myself -->
						<div class="myself-price-display" id="myself-price-display" style="display: none;">
							<div class="price-display-wrapper">
								<label class="price-display-label"><?php esc_html_e('Price:', 'woocommerce'); ?></label>
								<div class="price-display-value" id="selected-price-display">
									<span class="selected-price-amount">$0.00</span>
								</div>
							</div>
						</div>

						<!-- Personalization Options (Only for Someone else) -->
						<div class="personalization-options" id="personalization-fields" style="display: none;">

							<div class="field-row">
								<div class="personalization-field col-6">
									<label
										class="personalization-label"><?php esc_html_e('To (recipient name):', 'woocommerce'); ?>
										<span class="required-asterisk">*</span></label>
									<input type="text" class="personalization-input" name="recipient_name"
										id="recipient_name" value="<?php echo esc_attr($current_user_name); ?>"
										placeholder="<?php esc_attr_e('Type recipient', 'woocommerce'); ?>" required>
								</div>

								<div class="personalization-field col-6">
									<label
										class="personalization-label"><?php esc_html_e('From (sender name):', 'woocommerce'); ?></label>
									<input type="text" class="personalization-input" name="sender_name" id="sender_name"
										placeholder="<?php esc_attr_e('Type sender name', 'woocommerce'); ?>">
								</div>
							</div>

							<div class="personalization-field">
								<label
									class="personalization-label"><?php esc_html_e('Add your personal message', 'woocommerce'); ?></label>
								<textarea class="personalization-textarea" name="gift_message" id="gift_message" rows="4"
									placeholder="<?php esc_attr_e('Type message', 'woocommerce'); ?>"></textarea>
							</div>

							<div class="personalization-field">
								<label
									class="personalization-label"><?php esc_html_e('Add animation/video message:', 'woocommerce'); ?></label>
								<div class="media-upload-options">
									<!-- <button type="button" class="media-upload-btn" id="upload-image-btn">
										<span>+</span>
										<span><?php //esc_html_e('Upload image', 'woocommerce'); ?></span>
									</button> -->
									<button type="button" class="media-upload-btn" id="upload-video-btn">
										<svg width="20" height="20" viewBox="0 0 20 20" fill="none"
											xmlns="http://www.w3.org/2000/svg">
											<path
												d="M8.8 13.9L13.47 10.4C13.74 10.2 13.74 9.8 13.47 9.6L8.8 6.1C8.47 5.85 8 6.09 8 6.5V13.5C8 13.91 8.47 14.15 8.8 13.9ZM10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM10 18C5.59 18 2 14.41 2 10C2 5.59 5.59 2 10 2C14.41 2 18 5.59 18 10C18 14.41 14.41 18 10 18Z"
												fill="black" />
										</svg>

										<span><?php esc_html_e('Upload video', 'woocommerce'); ?></span>
									</button>
									<button type="button" class="media-upload-btn" id="add-animation-btn">
										<span>+</span>
										<span><?php esc_html_e('Add an animation', 'woocommerce'); ?></span>
									</button>
								</div>
								<div id="media-upload-error" class="media-upload-error" style="display:none; color:#d32f2f; font-size:14px; margin-top:8px;"></div>
								<div id="media-upload-progress" class="media-upload-progress" style="display:none; margin-top:8px;">
									<div class="progress-bar-container" style="width:100%; height:8px; background:#e0e0e0; border-radius:4px; overflow:hidden;">
										<div id="media-upload-progress-bar" class="progress-bar" style="width:0%; height:100%; background:#4caf50; transition:width 0.2s;"></div>
									</div>
									<div id="media-upload-progress-text" style="font-size:12px; color:#666; margin-top:4px; text-align:center;">Uploading 0%</div>
								</div>
								<!-- Hidden file inputs for media uploads -->
								<input type="file" id="media-upload-image" accept="image/*" style="display:none;">
								<input type="file" id="media-upload-video" accept="video/*" style="display:none;">
								<input type="file" id="media-upload-animation" accept="image/gif,.gif"
									style="display:none;">
								<div class="media-upload-previews" id="media-upload-previews"
									style="display:none; margin-top:12px;">
									<div id="preview-image-container" class="media-preview-item" style="display:none;">
										<span
											class="media-preview-label"><?php esc_html_e('Image:', 'woocommerce'); ?></span>
										<img id="preview-image" src="" alt=""
											style="max-width:80px; max-height:60px; border-radius:4px;">
										<button type="button" class="remove-media-preview" data-type="image">×</button>
									</div>
									<div id="preview-video-container" class="media-preview-item" style="display:none;">
										<span
											class="media-preview-label"><?php esc_html_e('Video:', 'woocommerce'); ?></span>
										<span id="preview-video-name"></span>
										<button type="button" class="remove-media-preview" data-type="video">×</button>
									</div>
									<div id="preview-animation-container" class="media-preview-item" style="display:none;">
										<span
											class="media-preview-label"><?php esc_html_e('Animation:', 'woocommerce'); ?></span>
										<img id="preview-animation" src="" alt=""
											style="max-width:80px; max-height:60px; border-radius:4px;">
										<button type="button" class="remove-media-preview" data-type="animation">×</button>
									</div>
								</div>
							</div>

							<!-- Animation Selection Popup Modal -->
							<div id="animation-selection-modal" class="animation-selection-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
								<div class="animation-modal-content" style="background:#fff; border-radius:8px; max-width:600px; width:90%; max-height:80vh; overflow-y:auto; position:relative;">
									<div class="animation-modal-header" style="padding:16px 20px; border-bottom:1px solid #e0e0e0; display:flex; justify-content:space-between; align-items:center;">
										<h3 style="margin:0; font-size:18px;"><?php esc_html_e('Select Animation', 'woocommerce'); ?></h3>
										<button type="button" class="animation-modal-close" style="background:none; border:none; font-size:24px; cursor:pointer; padding:0; width:32px; height:32px; display:flex; align-items:center; justify-content:center;">&times;</button>
									</div>
									<div class="animation-modal-body" style="padding:20px;">
										<div id="animation-loading" style="text-align:center; padding:20px;">
											<p><?php esc_html_e('Loading animations...', 'woocommerce'); ?></p>
										</div>
										<div id="animation-grid" class="animation-grid" style="display:none; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:12px;">
											<!-- Animations loaded via AJAX -->
										</div>
										<div id="animation-empty" style="display:none; text-align:center; color:#666; padding:20px;">
											<p><?php esc_html_e('No animations available. Please contact administrator.', 'woocommerce'); ?></p>
										</div>
									</div>
									<div class="animation-modal-footer" style="padding:16px 20px; border-top:1px solid #e0e0e0; display:flex; justify-content:flex-end; gap:12px;">
										<button type="button" class="animation-modal-cancel" style="padding:8px 16px; border:1px solid #ccc; background:#fff; border-radius:4px; cursor:pointer;"><?php esc_html_e('Cancel', 'woocommerce'); ?></button>
										<button type="button" class="animation-modal-select" style="padding:8px 16px; border:none; background:#4caf50; color:#fff; border-radius:4px; cursor:pointer; opacity:0.6;" disabled><?php esc_html_e('Select Animation', 'woocommerce'); ?></button>
									</div>
								</div>
							</div>

							<div class="personalization-field card-upload">
								<label class="personalization-label">
									<?php esc_html_e('Select card design:', 'woocommerce'); ?>
									<!-- <span class="design-selected-icon" style="display: none;">✓</span> -->
								</label>
								<?php
								// Prefill card designs with product images:
								// - featured image first (if set)
								// - then gallery images
								// - if no featured image, use gallery images
								
								// Ensure product object is available
								global $product;
								if (!$product) {
									$product = wc_get_product(get_the_ID());
								}

								// Use a reliable product id for fetching images
								$card_design_product_id = 0;
								if (!empty($product_id)) {
									$card_design_product_id = absint($product_id);
								} elseif ($product) {
									$card_design_product_id = absint($product->get_id());
								} else {
									$card_design_product_id = absint(get_the_ID());
								}

								// Featured image (prefer WP featured image, then WC)
								$featured_full_url = '';
								$featured_image_id = get_post_thumbnail_id($card_design_product_id);
								if (empty($featured_image_id) && $product) {
									$featured_image_id = absint($product->get_image_id());
								}
								if (!empty($featured_image_id)) {
									$featured_full_url = wp_get_attachment_image_url($featured_image_id, 'full') ?: wp_get_attachment_url($featured_image_id);
								}

								// Gallery images (prefer WC, fallback to _product_image_gallery meta)
								$gallery_ids = $product ? $product->get_gallery_image_ids() : [];
								if (empty($gallery_ids)) {
									$gallery_ids_csv = get_post_meta($card_design_product_id, '_product_image_gallery', true);
									$gallery_ids = !empty($gallery_ids_csv) ? array_filter(array_map('absint', explode(',', $gallery_ids_csv))) : [];
								}

								$gallery_images = [];
								if (!empty($gallery_ids) && is_array($gallery_ids)) {
									foreach ($gallery_ids as $gallery_image_id) {
										$gallery_image_id = absint($gallery_image_id);
										if (empty($gallery_image_id)) {
											continue;
										}
										// Avoid duplicates (e.g., featured also in gallery)
										if (!empty($featured_image_id) && $gallery_image_id === absint($featured_image_id)) {
											continue;
										}
										$gallery_url = wp_get_attachment_image_url($gallery_image_id, 'full') ?: wp_get_attachment_url($gallery_image_id);
										if (empty($gallery_url)) {
											continue;
										}
										$gallery_images[] = $gallery_url;
									}
								}

								$has_featured = !empty($featured_full_url);
								$has_gallery = !empty($gallery_images);
								?>
								<div class="card-upload-row">
									<div class="card-design-options" id="card-design-options" style="display: flex;">
										<?php if ($has_featured): ?>
											<!-- Featured Image First -->
											<div class="card-design-item active" data-design="default-featured"
												data-image-url="<?php echo esc_url($featured_full_url); ?>" data-is-default="1">
												<div class="card-design-preview default-design">
													<img class="selected-design-image"
														src="<?php echo esc_url($featured_full_url); ?>"
														alt="<?php esc_attr_e('Featured Image', 'woocommerce'); ?>"
														style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
												</div>

												<svg xmlns="http://www.w3.org/2000/svg" class="check-icon" width="44"
													height="44" viewBox="0 0 44 44">
													<circle cx="22" cy="22" r="22" fill="#037847" />
													<path
														d="M11.2171 20.3927L17.561 26.7146L32.6634 11.6122C33.5195 10.7561 34.9024 10.7561 35.7585 11.6122C36.6146 12.4683 36.6146 13.8512 35.7585 14.7073L19.0976 31.3683C18.2634 32.2244 16.8585 32.2244 16.0024 31.3683L8.12195 23.4878C7.26585 22.6317 7.26585 21.2488 8.12195 20.3927C8.97805 19.5366 10.361 19.5366 11.2171 20.3927Z"
														fill="white" />
												</svg>
											</div>
										<?php endif; ?>

										<?php if ($buyer_upload == 'Yes' ): ?>

											<!-- Upload button - Always visible, positioned after featured image -->
											<div class="card-design-upload-start">
												<button type="button" class="card-design-upload-btn" id="start-upload-designs">
													<span>+</span>
													<span><?php esc_html_e('Upload card images', 'woocommerce'); ?></span>
												</button>
											</div>

										<?php endif; ?>


										<?php if ($has_gallery): ?>
											<!-- Gallery Images After Upload Button -->
											<?php foreach ($gallery_images as $index => $img_url): ?>
												<div class="card-design-item <?php echo (!$has_featured && $index === 0) ? 'active' : ''; ?>"
													data-design="<?php echo esc_attr('default-gallery-' . $index); ?>"
													data-image-url="<?php echo esc_url($img_url); ?>" data-is-default="1">
													<div class="card-design-preview default-design">
														<img class="selected-design-image" src="<?php echo esc_url($img_url); ?>"
															alt="<?php echo esc_attr(sprintf(__('Gallery Image %d', 'woocommerce'), $index + 1)); ?>"
															style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
													</div>
													<svg xmlns="http://www.w3.org/2000/svg" class="check-icon" width="44"
														height="44" viewBox="0 0 44 44">
														<circle cx="22" cy="22" r="22" fill="#037847" />
														<path
															d="M11.2171 20.3927L17.561 26.7146L32.6634 11.6122C33.5195 10.7561 34.9024 10.7561 35.7585 11.6122C36.6146 12.4683 36.6146 13.8512 35.7585 14.7073L19.0976 31.3683C18.2634 32.2244 16.8585 32.2244 16.0024 31.3683L8.12195 23.4878C7.26585 22.6317 7.26585 21.2488 8.12195 20.3927C8.97805 19.5366 10.361 19.5366 11.2171 20.3927Z"
															fill="white" />
													</svg>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>

									</div>
									<!-- Hidden file input for image upload (multiple) -->
									<input type="file" id="card-design-image-upload" accept="image/*" multiple
										style="display: none;">
								</div>
							</div>

							<div class="personalization-field flex-price">
								<label class="personalization-label"><?php esc_html_e('Price:', 'woocommerce'); ?></label>
								<div class="price-display-wrapper" id="someone-else-price-wrapper">
									<span class="selected-price-amount" id="someone-else-price-display">$0.00</span>
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>

			<!-- Delivery -->
			<div class="custom-accordion-item">
				<div class="custom-accordion-header">
					<h3 class="custom-accordion-title"><?php esc_html_e('3. Delivery', 'woocommerce'); ?></h3>
					<span class="custom-accordion-icon">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" />
						</svg>
					</span>
				</div>
				<div class="custom-accordion-content">
					<div class="custom-accordion-inner">
						<!-- Step Form Pattern -->
						<div class="step-form-pattern">
							<div class="step-form-container">
								<div class="step-item active" data-step="1">
									<div class="step-number">1</div>
									<div class="step-label"><?php esc_html_e('Choose your gift card', 'woocommerce'); ?>
									</div>
								</div>
								<div class="step-connector"></div>
								<div class="step-item" data-step="2">
									<div class="step-number">2</div>
									<div class="step-label"><?php esc_html_e('Personalise your order', 'woocommerce'); ?>
									</div>
								</div>
								<div class="step-connector"></div>
								<div class="step-item" data-step="3">
									<div class="step-number">3</div>
									<div class="step-label"><?php esc_html_e('Delivery', 'woocommerce'); ?></div>
								</div>
							</div>
						</div>
						<div class="delivery-section-wrapper pt-30">
							<!-- Left Side - Delivery Fields -->
							<div class="delivery-fields-left">
								<!-- How should we send it? -->
								<div class="delivery-field-group">
									<label class="delivery-field-label">
										<?php esc_html_e('How should we send it?', 'woocommerce'); ?> <span
											class="required-asterisk">*</span>
									</label>
									<div class="delivery-method-options">
										<label class="delivery-method-option">
											<input type="radio" name="delivery_method" value="email" required checked>
											<span class="option-text"><?php esc_html_e('Email', 'woocommerce'); ?></span>
										</label>
										<label class="delivery-method-option">
											<input type="radio" name="delivery_method" value="sms" required>
											<span class="option-text"><?php esc_html_e('SMS', 'woocommerce'); ?></span>
										</label>
										<label class="delivery-method-option">
											<input type="radio" name="delivery_method" value="email_sms" required>
											<span
												class="option-text"><?php esc_html_e('Email+SMS', 'woocommerce'); ?></span>
										</label>
									</div>
								</div>

								<!-- Email Field (shown by default when Email delivery is selected) -->
								<div class="delivery-field-group" id="delivery-email-field">
									<label class="delivery-field-label">
										<?php esc_html_e('Recepient Email', 'woocommerce'); ?> <span
											class="required-asterisk">*</span>
									</label>
									<input type="email" class="delivery-input" name="delivery_email" id="delivery_email"
										placeholder="<?php esc_attr_e('Type email', 'woocommerce'); ?>">
								</div>


								<!-- Mobile Number Field (Australian: +61 or 04 prefix) -->
								<div class="delivery-field-group" id="delivery-phone-field" style="display: none;">
									<label class="delivery-field-label">
										<?php esc_html_e('Mobile Number', 'woocommerce'); ?> <span
											class="required-asterisk">*</span></label>
									<input type="tel" class="delivery-input" name="delivery_number" id="delivery_number"
										placeholder="<?php esc_attr_e('e.g. +61 412 345 678 or 0412 345 678', 'woocommerce'); ?>"
										title="<?php esc_attr_e('Australian mobile: +61 or 04 prefix', 'woocommerce'); ?>">
									<span class="costs">SMS delivery costs <span class="del-cost"></span></span>
								</div>

								<!-- When should we send it? -->
								<div class="delivery-field-group">
									<label class="delivery-field-label">
										<?php esc_html_e('When should we send it?', 'woocommerce'); ?>
									</label>
									<div class="delivery-timing-options">
										<label class="delivery-timing-option">
											<input type="radio" name="delivery_timing" value="instant" checked>
											<span
												class="option-text"><?php esc_html_e('Send instantly', 'woocommerce'); ?></span>
										</label>
										<label class="delivery-timing-option">
											<input type="radio" name="delivery_timing" value="schedule">
											<span
												class="option-text"><?php esc_html_e('Schedule for later', 'woocommerce'); ?></span>
										</label>
									</div>
								</div>

								<!-- Scheduled Delivery Fields -->
								<div class="scheduled-delivery-fields" style="display: none;">
									<div class="schedule-fields-wrapper">
										<div class="schedule-fields-row">
											<!-- Date Input -->
											<div class="schedule-field date-field">
												<input type="date" class="schedule-input date-input" name="schedule_date"
													id="schedule_date" min="<?php echo esc_attr(date('Y-m-d')); ?>"
													style="cursor: pointer;">
												<svg class="schedule-icon calendar-icon" width="20" height="20"
													viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
													<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
													<line x1="16" y1="2" x2="16" y2="6"></line>
													<line x1="8" y1="2" x2="8" y2="6"></line>
													<line x1="3" y1="10" x2="21" y2="10"></line>
												</svg>
											</div>

											<!-- Time Input -->
											<div class="schedule-field time-field">
												<select class="schedule-input time-input" name="schedule_time"
													id="schedule_time">
													<option value="8:00 AM">8:00 AM</option>
													<option value="9:00 AM">9:00 AM</option>
													<option value="10:00 AM">10:00 AM</option>
													<option value="11:00 AM">11:00 AM</option>
													<option value="12:00 PM">12:00 PM</option>
													<option value="1:00 PM">1:00 PM</option>
													<option value="2:00 PM">2:00 PM</option>
													<option value="3:00 PM">3:00 PM</option>
													<option value="4:00 PM">4:00 PM</option>
													<option value="5:00 PM">5:00 PM</option>
													<option value="6:00 PM">6:00 PM</option>
													<option value="7:00 PM">7:00 PM</option>
													<option value="8:00 PM">8:00 PM</option>
												</select>
												<svg class="schedule-icon dropdown-icon" width="20" height="20"
													viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
													<polyline points="6 9 12 15 18 9"></polyline>
												</svg>
											</div>
										</div>

										<!-- Timezone Input -->
										<div class="schedule-fields-row">
											<div class="schedule-field timezone-field">
												<select class="schedule-input timezone-input" name="schedule_timezone"
													id="schedule_timezone">
													<option value="UTC+8">(UTC + 8:00) Western Australia</option>
													<option value="UTC+10" selected="selected">(UTC + 10:00) Eastern
														Australia</option>
													<option value="UTC+9:30">(UTC + 9:30) Central Australia</option>
													<option value="UTC+0">(UTC + 0:00) Greenwich Mean Time</option>
													<option value="UTC-5">(UTC - 5:00) Eastern Time (US)</option>
													<option value="UTC-8">(UTC - 8:00) Pacific Time (US)</option>
												</select>
												<svg class="schedule-icon dropdown-icon" width="20" height="20"
													viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
													<polyline points="6 9 12 15 18 9"></polyline>
												</svg>
											</div>
										</div>
									</div>
								</div>

								<!-- Price Display -->
								<div class="delivery-price-display">
									<label
										class="delivery-field-label"><?php esc_html_e('Price:', 'woocommerce'); ?></label>
									<div class="delivery-price-wrapper" id="delivery-price-display">
										<?php if ($discount_active && $sale_price && ($denomination_type === 'fixed' || $denomination_type === 'variable')): ?>
											<?php if ($denomination_type === 'variable'): ?>
												<span
													class="delivery-original-price">$<?php echo esc_html(number_format($original_min_price, 2)); ?>
													– $<?php echo esc_html(number_format($original_max_price, 2)); ?></span>
												<span
													class="delivery-discounted-price">$<?php echo esc_html(number_format($min_price, 2)); ?>
													– $<?php echo esc_html(number_format($max_price, 2)); ?></span>
											<?php else: ?>
												<span
													class="delivery-discounted-price">$<?php echo esc_html(number_format($sale_price, 2)); ?></span>
												<span
													class="delivery-original-price">$<?php echo esc_html(number_format($original_price, 2)); ?></span>
											<?php endif; ?>
										<?php elseif ($denomination_type === 'variable'): ?>
											<span
												class="delivery-regular-price">$<?php echo esc_html(number_format($min_price, 2)); ?>
												– $<?php echo esc_html(number_format($max_price, 2)); ?></span>
										<?php else: ?>
											<span
												class="delivery-regular-price">$<?php echo esc_html(number_format($regular_price, 2)); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<!-- Right Side - Product Image -->
							<div class="delivery-image-right">
								<div class="delivery-product-image-wrapper">
									<?php if ($featured_image_id): ?>
										<img src="<?php echo esc_url(wp_get_attachment_image_url($featured_image_id, 'full')); ?>"
											alt="<?php echo esc_attr($product_title); ?>">
									<?php else: ?>
										<img src="<?php echo esc_url(wc_placeholder_img_src()); ?>"
											alt="<?php echo esc_attr($product_title); ?>">
									<?php endif; ?>
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>
		<?php endif; ?>

	</div>


	<?php if($is_child) : ?>

		<!-- Selected Gift Card Details Section -->
		<div class="selected-gift-card-details-section" style="display: none;">
			<div class="selected-gift-card-details-container">
				<div class="gift-card-details-wrapper">
					<!-- Left Side - Gift Card Details -->
					<div class="gift-card-details-left">
						<h3 class="gift-card-details-title"><?php echo esc_html($product_title); ?> Gift Card <span
								class="gift-card-selected-price"></span></h3>


						<label
							class="gift-card-detail-label main-label"><?php esc_html_e('Your order details:', 'woocommerce'); ?></label>

						<div class="gift-card-detail-item">
							<label class="gift-card-detail-label"><?php esc_html_e('Recipient:', 'woocommerce'); ?></label>
							<div class="gift-card-detail-value" id="selected-recipient-name">-</div>
						</div>

						<div class="gift-card-detail-item">
							<label
								class="gift-card-detail-label"><?php esc_html_e('Mobile number:', 'woocommerce'); ?></label>
							<div class="gift-card-detail-value" id="selected-mobile-number">-</div>
						</div>

						<div class="gift-card-detail-item">
							<label class="gift-card-detail-label"><?php esc_html_e('Email:', 'woocommerce'); ?></label>
							<div class="gift-card-detail-value" id="selected-email">-</div>
						</div>

						<div class="gift-card-detail-item">
							<label class="gift-card-detail-label"><?php esc_html_e('Delivery:', 'woocommerce'); ?></label>
							<div class="gift-card-detail-value" id="selected-delivery">-</div>
						</div>


					</div>

					<!-- Right Side - Product Image -->
					<div class="gift-card-details-right">
						<div class="gift-card-product-image-wrapper">
							<img id="selected-gift-card-image" src="" alt="" style="display: none;">
							<div class="no-image-placeholder" id="no-image-placeholder">
								<?php esc_html_e('No gift card selected', 'woocommerce'); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="price-section">
					<div class="price-section-left">
						<button type="button" class="gift-card-preview-btn" id="gift-card-preview-btn"
							aria-label="<?php esc_attr_e('Preview order details', 'woocommerce'); ?>">
							<svg class="gift-card-preview-btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
								stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
								aria-hidden="true">
								<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
								</path>
								<polyline points="22,6 12,13 2,6"></polyline>
							</svg>
							<span><?php esc_html_e('Preview', 'woocommerce'); ?></span>
						</button>
						<div class="gift-card-price-display">
							<label class="gift-card-detail-label"><?php esc_html_e('Price:', 'woocommerce'); ?></label>
							<div class="gift-card-price-wrapper" id="gift-card-price-display">
								<?php if ($discount_active && $sale_price && ($denomination_type === 'fixed' || $denomination_type === 'variable')): ?>
									<?php if ($denomination_type === 'variable'): ?>
										<span
											class="gift-card-original-price">$<?php echo esc_html(number_format($original_min_price, 2)); ?>
											– $<?php echo esc_html(number_format($original_max_price, 2)); ?></span>
										<span
											class="gift-card-discounted-price">$<?php echo esc_html(number_format($min_price, 2)); ?>
											– $<?php echo esc_html(number_format($max_price, 2)); ?></span>
									<?php else: ?>
										<span
											class="gift-card-discounted-price">$<?php echo esc_html(number_format($sale_price, 2)); ?></span>
										<span
											class="gift-card-original-price">$<?php echo esc_html(number_format($original_price, 2)); ?></span>
									<?php endif; ?>
								<?php elseif ($denomination_type === 'variable'): ?>
									<span class="gift-card-regular-price">$<?php echo esc_html(number_format($min_price, 2)); ?>
										– $<?php echo esc_html(number_format($max_price, 2)); ?></span>
								<?php else: ?>
									<span
										class="gift-card-regular-price">$<?php echo esc_html(number_format($regular_price, 2)); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<div class="actions">
						<a href="<?php echo esc_url(home_url('/brands')); ?>"
							class="btn btn-primary gift-card-continue-btn btn-black-p2" style="place-content: center; font-family: 'Verdana'; font-size: 20px !important;" id="gift-card-continue-btn">
							<?php esc_html_e('Continue Shopping', 'woocommerce'); ?>
						</a>
						<form id="gift-card-buy-now-form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
							method="post" style="display:inline;">
							<input type="hidden" name="action" value="gc_buy_now_process" />
							<input type="hidden" name="_wpnonce"
								value="<?php echo esc_attr(wp_create_nonce('gc_buy_now')); ?>" />
							<input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" />
							<input type="hidden" name="quantity" id="gc_buy_now_quantity" value="" />
							<input type="hidden" name="gift_card_price" id="gc_buy_now_gift_card_price" value="" />
							<input type="hidden" name="recipient_name" id="gc_buy_now_recipient_name" value="" />
							<input type="hidden" name="recipient_email" id="gc_buy_now_recipient_email" value="" />
							<input type="hidden" name="sender_name" id="gc_buy_now_sender_name" value="" />
							<input type="hidden" name="mobile_number" id="gc_buy_now_mobile_number" value="" />
							<input type="hidden" name="delivery_email" id="gc_buy_now_delivery_email" value="" />
							<input type="hidden" name="delivery_method" id="gc_buy_now_delivery_method" value="" />
							<input type="hidden" name="delivery_timing" id="gc_buy_now_delivery_timing" value="" />
							<input type="hidden" name="gift_message" id="gc_buy_now_gift_message" value="" />
							<input type="hidden" name="card_design" id="gc_buy_now_card_design" value="" />
							<input type="hidden" name="gift_for" id="gc_buy_now_gift_for" value="" />
							<input type="hidden" name="schedule_date" id="gc_buy_now_schedule_date" value="" />
							<input type="hidden" name="schedule_time" id="gc_buy_now_schedule_time" value="" />
							<input type="hidden" name="schedule_timezone" id="gc_buy_now_schedule_timezone" value="" />
							<input type="hidden" name="schedule_datetime" id="gc_buy_now_schedule_datetime" value="" />
							<input type="hidden" name="email_animation" id="gc_buy_now_email_animation" value="" />
							<input type="hidden" name="video_message" id="gc_buy_now_video_message" value="" />
							<input type="hidden" name="image_message" id="gc_buy_now_image_message" value="" />
							<button type="button"
								class="btn gift-card-buy-btn btn-primary btn <?php //echo !is_user_logged_in() ? 'disabled' : ''; ?>"
								id="gift-card-buy-btn">
								Buy Now
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>

		<!-- Gift card order details preview modal -->
		<div id="gift-card-preview-modal" class="gift-card-preview-modal" role="dialog"
			aria-labelledby="gift-card-preview-modal-title" aria-modal="true" style="display: none;">
			<div class="gift-card-preview-modal-backdrop"></div>
			<div class="gift-card-preview-modal-content">
				<button type="button" class="gift-card-preview-modal-close"
					aria-label="<?php esc_attr_e('Close preview', 'woocommerce'); ?>">&times;</button>
				<h3 id="gift-card-preview-modal-title" class="gift-card-preview-modal-title">
					<?php esc_html_e('Your order details', 'woocommerce'); ?></h3>
				<div class="gift-card-preview-modal-body">
					<section class="gift-card-preview-section gift-card-preview-section-details">
						<div class="gift-card-preview-row">
							<span class="gift-card-preview-label"><?php esc_html_e('Sender', 'woocommerce'); ?></span>
							<span class="gift-card-preview-value" id="preview-sender-name">-</span>
						</div>
						<div class="gift-card-preview-row">
							<span class="gift-card-preview-label"><?php esc_html_e('Recipient', 'woocommerce'); ?></span>
							<span class="gift-card-preview-value" id="preview-recipient-name">-</span>
						</div>
						<div class="gift-card-preview-row">
							<span class="gift-card-preview-label"><?php esc_html_e('Email', 'woocommerce'); ?></span>
							<span class="gift-card-preview-value" id="preview-email">-</span>
						</div>
						<div class="gift-card-preview-row">
							<span
								class="gift-card-preview-label"><?php esc_html_e('Mobile number', 'woocommerce'); ?></span>
							<span class="gift-card-preview-value" id="preview-mobile-number">-</span>
						</div>
						<div class="gift-card-preview-row gift-card-preview-row-message">
							<span class="gift-card-preview-label"><?php esc_html_e('Gift message', 'woocommerce'); ?></span>
							<div class="gift-card-preview-value gift-card-preview-message" id="preview-gift-message">-</div>
						</div>
						<div class="gift-card-preview-row">
							<span class="gift-card-preview-label"><?php esc_html_e('Delivery', 'woocommerce'); ?></span>
							<span class="gift-card-preview-value" id="preview-delivery">-</span>
						</div>
						<div class="gift-card-preview-row preview-schedule-row" id="preview-schedule-row"
							style="display: none;">
							<span
								class="gift-card-preview-label"><?php esc_html_e('Scheduled for', 'woocommerce'); ?></span>
							<span class="gift-card-preview-value" id="preview-schedule">-</span>
						</div>
					</section>
					<section class="gift-card-preview-section gift-card-preview-section-media">
						<h4 class="gift-card-preview-section-title"><?php esc_html_e('Card & media', 'woocommerce'); ?></h4>
						<div class="gift-card-preview-media-grid">
							<div class="gift-card-preview-media-item">
								<span
									class="gift-card-preview-media-label"><?php esc_html_e('Card design', 'woocommerce'); ?></span>
								<div class="gift-card-preview-media-box">
									<img id="preview-modal-card-design" src="" alt="" class="gift-card-preview-thumb"
										style="display: none;">
									<span class="gift-card-preview-no-media" id="preview-modal-card-design-none">-</span>
								</div>
							</div>
							<div class="gift-card-preview-media-item">
								<span
									class="gift-card-preview-media-label"><?php esc_html_e('Animation', 'woocommerce'); ?></span>
								<div class="gift-card-preview-media-box">
									<img id="preview-modal-animation" src="" alt="" class="gift-card-preview-thumb"
										style="display: none;">
									<span class="gift-card-preview-no-media" id="preview-modal-animation-none">-</span>
								</div>
							</div>
							<div class="gift-card-preview-media-item">
								<span
									class="gift-card-preview-media-label"><?php esc_html_e('Video', 'woocommerce'); ?></span>
								<div class="gift-card-preview-media-box gift-card-preview-media-video">
									<video id="preview-modal-video-player" controls playsinline
										style="display: none; max-width: 100%; max-height: 200px;"></video>
									<span class="gift-card-preview-value gift-card-preview-video-fallback"
										id="preview-modal-video">-</span>
								</div>
							</div>
							<!-- <div class="gift-card-preview-media-item">
								<span class="gift-card-preview-media-label"><?php //esc_html_e('Image', 'woocommerce'); ?></span>
								<div class="gift-card-preview-media-box">
									<img id="preview-modal-image" src="" alt="" class="gift-card-preview-thumb" style="display: none;">
									<span class="gift-card-preview-no-media" id="preview-modal-image-none">-</span>
								</div>
							</div> -->
						</div>
					</section>
				</div>
			</div>
		</div>

		<!-- Checkout/Payment Section -->
		<div class="checkout-payment-section" style="display: none; margin-top: 40px;">
			<div class="checkout-payment-wrapper">
				<!-- Left Side - Order Summary -->
				<div class="checkout-order-summary">
					<h3 class="order-summary-title"><?php esc_html_e('Order Summary', 'woocommerce'); ?></h3>
					<div class="order-summary-item">
						<span class="order-summary-label"><?php esc_html_e('Subtotal:', 'woocommerce'); ?></span>
						<span class="order-summary-value" id="checkout-subtotal">$0.00</span>
					</div>
					<div class="order-summary-item order-summary-total">
						<span class="order-summary-label"><?php esc_html_e('TOTAL:', 'woocommerce'); ?></span>
						<span class="order-summary-value" id="checkout-total">$0.00</span>
					</div>
				</div>

				<!-- Right Side - Payment Form -->
				<div class="checkout-payment-form">
					<!-- Apple Pay Button -->
					<button type="button" class="apple-pay-button" id="apple-pay-btn">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
							<path
								d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z" />
						</svg>
						<?php esc_html_e('Pay', 'woocommerce'); ?>
					</button>

					<!-- Payment Form Fields -->
					<div class="payment-form-fields">
						<div class="payment-field-group">
							<label class="payment-field-label"><?php esc_html_e('Full name', 'woocommerce'); ?></label>
							<input type="text" class="payment-input" id="payment-full-name" name="payment_full_name"
								value="<?php echo is_user_logged_in() ? esc_attr($current_user_name) : ''; ?>"
								placeholder="<?php esc_attr_e('Enter full name', 'woocommerce'); ?>" autocomplete="off" required>
						</div>

						<div class="payment-field-group">
							<label class="payment-field-label"><?php esc_html_e('Country', 'woocommerce'); ?></label>
							<select class="payment-select" id="payment-country" name="payment_country" required>
								<option value=""><?php esc_html_e('Select country', 'woocommerce'); ?></option>
								<option value="US" selected><?php esc_html_e('United States', 'woocommerce'); ?></option>
								<option value="CA"><?php esc_html_e('Canada', 'woocommerce'); ?></option>
								<option value="GB"><?php esc_html_e('United Kingdom', 'woocommerce'); ?></option>
								<option value="AU"><?php esc_html_e('Australia', 'woocommerce'); ?></option>
								<!-- Add more countries as needed -->
							</select>
						</div>

						<div class="payment-field-group">
							<label class="payment-field-label"><?php esc_html_e('Address', 'woocommerce'); ?></label>
							<input type="text" class="payment-input" id="payment-address" name="payment_address"
								placeholder="<?php esc_attr_e('Street address', 'woocommerce'); ?>" autocomplete="off" required>
						</div>

						<!-- Payment Method Options -->
						<div class="payment-method-options">
							<label class="payment-method-option active" data-method="card">
								<input type="radio" name="payment_method" value="card" checked>
								<span class="payment-method-icon">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2">
										<rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
										<line x1="1" y1="10" x2="23" y2="10" />
									</svg>
								</span>
								<span class="payment-method-label"><?php esc_html_e('Card', 'woocommerce'); ?></span>
							</label>
							<label class="payment-method-option" data-method="afterpay">
								<input type="radio" name="payment_method" value="afterpay">
								<span class="payment-method-logo">Afterpay</span>
							</label>
							<label class="payment-method-option" data-method="klarna">
								<input type="radio" name="payment_method" value="klarna">
								<span class="payment-method-logo">Klarna</span>
							</label>
							<label class="payment-method-option" data-method="more">
								<input type="radio" name="payment_method" value="more">
								<span class="payment-method-icon">⋯</span>
							</label>
						</div>

						<!-- Card Payment Fields -->
						<div class="card-payment-fields" id="card-payment-fields">
							<div class="payment-field-group">
								<label
									class="payment-field-label"><?php esc_html_e('Card number', 'woocommerce'); ?></label>
								<input type="text" class="payment-input" id="payment-card-number" name="payment_card_number"
									placeholder="1234 1234 1234 1234" maxlength="19" autocomplete="off" required>
								<div class="card-brand-icons">
									<span class="card-icon">Visa</span>
									<span class="card-icon">Mastercard</span>
									<span class="card-icon">Amex</span>
									<span class="card-icon">Discover</span>
								</div>
							</div>

							<div class="payment-field-row">
								<div class="payment-field-group">
									<label
										class="payment-field-label"><?php esc_html_e('Expiry date', 'woocommerce'); ?></label>
									<input type="text" class="payment-input" id="payment-expiry" name="payment_expiry"
										placeholder="MM/YY" maxlength="5" autocomplete="off" required>
								</div>
								<div class="payment-field-group">
									<label
										class="payment-field-label"><?php esc_html_e('Security code', 'woocommerce'); ?></label>
									<input type="text" class="payment-input" id="payment-cvv" name="payment_cvv"
										placeholder="CVV" maxlength="4" autocomplete="off" required>
									<span class="cvv-icon">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2">
											<rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
											<line x1="1" y1="10" x2="23" y2="10" />
										</svg>
									</span>
								</div>
							</div>
						</div>

						<!-- Submit Order Button -->
						<button type="button" class="submit-order-button" id="submit-order-btn">
							<?php esc_html_e('Submit order', 'woocommerce'); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

	<?php endif; ?>

	<!-- Product Information Accordions -->
	<div class="product-info-accordions bottom-summury">
		<!-- Card Details Accordion -->
		<div class="product-info-accordion-item">
			<div class="product-info-accordion-header">
				<h3 class="product-info-accordion-title"><?php esc_html_e('Card details', 'woocommerce'); ?></h3>
				<span class="product-info-accordion-icon">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
							stroke-linejoin="round" />
					</svg>
				</span>
			</div>
			<div class="product-info-accordion-content">
				<div class="product-info-accordion-inner">
					<?php if ($product_description): ?>
						<?php echo wp_kses_post($product_description); ?>
					<?php elseif ($product_short_description): ?>
						<?php echo wp_kses_post($product_short_description); ?>
					<?php else: ?>
						<p><?php esc_html_e('No cards Details Present', 'woocommerce'); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php
		$terms_conditions = get_post_meta($product_id, 'terms_conditions', true);

		if (!empty(trim($terms_conditions))):
			?>

			<!-- Terms & Conditions Accordion -->
			<div id="terms-conditions" class="product-info-accordion-item">
				<div class="product-info-accordion-header">
					<h3 class="product-info-accordion-title">
						<?php esc_html_e('Terms & Conditions', 'woocommerce'); ?>
					</h3>
					<span class="product-info-accordion-icon">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" />
						</svg>
					</span>
				</div>
				<div class="product-info-accordion-content">
					<div class="product-info-accordion-inner">
						<?php echo wp_kses_post($terms_conditions); ?>
					</div>
				</div>
			</div>

		<?php endif; ?>


		<?php
		$how_to_use = get_post_meta($product_id, 'how_to_use', true);

		if (!empty(trim($how_to_use))):
			?>

			<!-- How to Use Accordion -->
			<div class="product-info-accordion-item">
				<div class="product-info-accordion-header">
					<h3 class="product-info-accordion-title">
						<?php esc_html_e('How to use', 'woocommerce'); ?>
					</h3>
					<span class="product-info-accordion-icon">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" />
						</svg>
					</span>
				</div>
				<div class="product-info-accordion-content">
					<div class="product-info-accordion-inner">
						<?php echo wp_kses_post($how_to_use); ?>
					</div>
				</div>
			</div>

		<?php endif; ?>


		<?php
		$eligible_retailers_terms = get_the_terms($product_id, 'eligible_retailers');

		if ($eligible_retailers_terms && !is_wp_error($eligible_retailers_terms)):
			?>

			<!-- Eligible Retailers Accordion -->
			<div class="product-info-accordion-item">
				<div class="product-info-accordion-header">
					<h3 class="product-info-accordion-title">
						<?php esc_html_e('Eligible retailers', 'woocommerce'); ?>
					</h3>
					<span class="product-info-accordion-icon">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" />
						</svg>
					</span>
				</div>
				<div class="product-info-accordion-content">
					<div class="product-info-accordion-inner">
						<p><?php esc_html_e('This gift card can be used at the following eligible retailers:', 'woocommerce'); ?>
						</p>
						<ul class="eligible-retailers-list">
							<?php foreach ($eligible_retailers_terms as $retailer): ?>
								<li><?php echo esc_html($retailer->name); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>

		<?php endif; ?>

	</div>


	<!-- Ideal For Section -->
	<?php if ($product_tags && !is_wp_error($product_tags) && !empty($product_tags)): ?>
		<div class="ideal-for-section">
			<label class="ideal-for-label"><?php esc_html_e('Ideal for:', 'woocommerce'); ?></label>
			<div class="ideal-for-tags">
				<?php foreach ($product_tags as $tag): ?>
					<span class="ideal-for-tag"><?php echo esc_html($tag->name); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="need-help ">
		<?php
		$templates = get_option('wpb_js_templates');

		if (!empty($templates)) {
			foreach ($templates as $template) {
				if (!empty($template['name']) && $template['name'] === 'template-need-help') {
					echo do_shortcode($template['template']);
					break; // stop once found
				}
			}
		}
		?>
	</div>

</div>

<?php
/**
 * Hook: woocommerce_after_single_product_summary.
 *
 * @hooked woocommerce_output_related_products - 20
 */
// do_action( 'woocommerce_after_single_product_summary' );
?>

<?php //do_action( 'woocommerce_after_single_product' ); ?>

<?php
// Transaction limits and current cart totals for this product (for client-side total value validation)
$transaction_limit_enabled = false;
$quantity_per_transaction_js = 0;
$total_value_per_transaction_js = 0.0;
$cart_qty_for_product_js = 0;
$cart_value_for_product_js = 0.0;
$add_tx_limit = get_field('add_transaction_limit_checkbox', $product_id);
if (empty($add_tx_limit)) {
	$add_tx_limit = get_post_meta($product_id, 'add_transaction_limit_checkbox', true);
}
if ($add_tx_limit === 'yes' || $add_tx_limit === 'Yes') {
	$transaction_limit_enabled = true;
	$qty_limit_val = get_field('_quantity_per_transaction', $product_id);
	if ($qty_limit_val === '' || $qty_limit_val === null) {
		$qty_limit_val = get_post_meta($product_id, '_quantity_per_transaction', true);
	}
	$quantity_per_transaction_js = intval($qty_limit_val);
	$val_limit = get_field('_total_value_per_transaction', $product_id);
	if ($val_limit === '' || $val_limit === null) {
		$val_limit = get_post_meta($product_id, '_total_value_per_transaction', true);
	}
	$total_value_per_transaction_js = floatval($val_limit);
}
if (function_exists('WC') && WC()->cart) {
	foreach (WC()->cart->get_cart() as $cart_item) {
		if (isset($cart_item['product_id']) && (int) $cart_item['product_id'] === (int) $product_id) {
			$cart_qty_for_product_js += (int) $cart_item['quantity'];
			$item_price = isset($cart_item['gift_card_price']) ? floatval($cart_item['gift_card_price']) : 0;
			$cart_value_for_product_js += (float) $cart_item['quantity'] * $item_price;
		}
	}
}
?>
<script>
	// Pass PHP variables to external JavaScript file
	var singleProductData = {
		ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
		uploadGiftCardNonce: '<?php echo esc_js(wp_create_nonce('gift_card_nonce')); ?>',
		gcCardDesignNonce: '<?php echo esc_js(wp_create_nonce('gc_save_card_design')); ?>',
		gcMediaUploadNonce: '<?php echo esc_js(wp_create_nonce('gc_media_upload')); ?>',
		priceOptionsNonce: '<?php echo esc_js(wp_create_nonce('get_price_options')); ?>',
		productId: <?php echo $product_id; ?>,
		productSku: '<?php echo esc_js($product_sku); ?>',
		productTitle: '<?php echo esc_js($product_title); ?>',
		productImageUrl: '<?php echo esc_js($product_image_url); ?>',
		discountedPrice: <?php echo ($discount_active && $sale_price && is_numeric($sale_price)) ? floatval($sale_price) : 'null'; ?>,
		originalPrice: <?php echo is_numeric($original_price) ? floatval($original_price) : 0; ?>,
		originalPriceForDisplay: <?php echo floatval($original_price); ?>,
		minPrice: <?php echo floatval($min_price); ?>,
		maxPrice: <?php echo floatval($max_price); ?>,
		priceIntervals: <?php echo floatval($price_intervals) ?: 1; ?>,
		denominationType: '<?php echo esc_js($denomination_type); ?>',
		discountActive: <?php echo ($discount_active && $sale_price) ? 'true' : 'false'; ?>,
		originalMinPrice: <?php echo isset($original_min_price) ? floatval($original_min_price) : 'null'; ?>,
		originalMaxPrice: <?php echo isset($original_max_price) ? floatval($original_max_price) : 'null'; ?>,
		isUserLoggedIn: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
		currentUserName: '<?php echo esc_js($current_user_name); ?>',
		currentUserEmail: '<?php echo esc_js($current_user_email); ?>',
		currentUserPhone: '<?php echo esc_js($current_user_phone); ?>',
		smsDeliveryCost: <?php echo floatval($sms_delivery_cost); ?>,
		transactionLimitEnabled: <?php echo $transaction_limit_enabled ? 'true' : 'false'; ?>,
		quantityPerTransaction: <?php echo (int) $quantity_per_transaction_js; ?>,
		totalValuePerTransaction: <?php echo floatval($total_value_per_transaction_js); ?>,
		cartQtyForProduct: <?php echo (int) $cart_qty_for_product_js; ?>,
		cartValueForProduct: <?php echo floatval($cart_value_for_product_js); ?>,
		brandsUrl: '<?php echo esc_url(home_url('/brands')); ?>'
	};
</script>