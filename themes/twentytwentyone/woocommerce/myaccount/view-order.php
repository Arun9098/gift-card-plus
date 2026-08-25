<?php
/**
 * View Order
 *
 * Shows the details of a particular order on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/view-order.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.0.0
 */

defined('ABSPATH') || exit;

// Safety check for order object
if (!$order) {
	return;
}

// Get order items
$order_items = $order->get_items();
$order_number = $order->get_order_number();
$order_date = wc_format_datetime($order->get_date_created(), 'd/m/Y');
$invoice_number = $order->get_meta('_invoice_number');
$sender_name = $order->get_meta('_sender_name');
if (empty($sender_name)) {
	$sender_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
}
if (empty($sender_name)) {
	$sender_name = 'Gift Cards Plus';
}

// Get order status
$order_status = $order->get_status();
$status_label = wc_get_order_status_name($order_status);
if ($order_status == 'completed') {
	$status_label = 'Delivered';
}

// Get delivery cost from order fee items
$delivery_cost_total = 0.00;
$fee_items = $order->get_fees();
foreach ($fee_items as $fee_item) {
	if (strtolower($fee_item->get_name()) === 'delivery cost') {
		$delivery_cost_total = floatval($fee_item->get_total());
		break;
	}
}

// Calculate total number of gift card items (excluding fee items)
$total_gift_card_items = 0;
foreach ($order_items as $item) {
	// Only count product items, not fee items
	if ($item->get_type() === 'line_item') {
		$total_gift_card_items += $item->get_quantity();
	}
}

// Calculate delivery cost per item
$delivery_cost_per_item = 0.00;
if ($total_gift_card_items > 0 && $delivery_cost_total > 0) {
	$delivery_cost_per_item = $delivery_cost_total / $total_gift_card_items;
}

?>
<div class="order-details-page-wrap">
	<div class="order-details-page">
		<div class="order-details-header">
			<h1>Order Details</h1>
			<button class="save-reminder-btn" onclick="window.location.href='/my-account/my-reminders'">Save a reminder</button>
		</div>

		<?php if (!empty($order_items)): ?>
			<?php foreach ($order_items as $item_id => $item):
				// Get item meta data
				$recipient_name = wc_get_order_item_meta($item_id, '_recipient_name', true);
				$recipient_email = wc_get_order_item_meta($item_id, '_recipient_email', true);
				$gift_message = wc_get_order_item_meta($item_id, '_gift_message', true);
				if (empty($gift_message)) {
					$gift_message = wc_get_order_item_meta($item_id, 'gift_message', true);
				}
				$gift_card_number = wc_get_order_item_meta($item_id, '_gift_card_number', true);
				$delivery_method = wc_get_order_item_meta($item_id, '_delivery_method', true);
				$gift_card_image = wc_get_order_item_meta($item_id, '_gift_card_image', true);
				$gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_name', true);
				$gift_card_price = wc_get_order_item_meta($item_id, '_gift_card_price', true);
				$gift_card_post_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);
				$video_message_url = wc_get_order_item_meta($item_id, 'gift_video_message', true);

				
				if (!empty($video_message_url) && is_string($video_message_url)) {
					$video_message_url = esc_url_raw($video_message_url);
				} else {
					$video_message_url = '';
				}
				// if (empty($video_message_url) || !filter_var($video_message_url, FILTER_VALIDATE_URL)) {
				// 	$video_message_url = '';
				// }

				// echo'<pre>';
				// print_r($video_message_url);
				// echo'</pre>';
				// exit;

				// Get product image if gift card image is not available
				if (empty($gift_card_image) && !empty($gift_card_post_id)) {
					$gift_card_image = get_the_post_thumbnail_url($gift_card_post_id, 'full');
				}

				// If still no image, try to get from product
				if (empty($gift_card_image)) {
					$product = $item->get_product();
					if ($product) {
						$gift_card_image = wp_get_attachment_image_url($product->get_image_id(), 'full');
					}
				}

				// Get gift card name from item name if not in meta
				if (empty($gift_card_name)) {
					$gift_card_name = $item->get_name();
					// Remove trailing price/numbers if present
					$gift_card_name = preg_replace('/\s+\$\d+\.?\d*\s*$/', '', $gift_card_name);
				}

				// Get price
				$item_price = $item->get_total();
				if (!empty($gift_card_price)) {
					$item_price = floatval($gift_card_price);
				}

				// Get quantity
				$item_qty = $item->get_quantity();

				// Calculate delivery cost for this item (per item cost * quantity)
				$delivery_cost = $delivery_cost_per_item * $item_qty;

				// Mask card number (show last 4 digits)
				$card_display = 'xxxx';
				if (!empty($gift_card_number) && strlen($gift_card_number) >= 4) {
					$card_display = 'xxxx ' . substr($gift_card_number, -4);
				}

				// Format delivery method
				$delivery_display = ucfirst($delivery_method ?: 'Instant');
				?>
				<div class="order-item-card">
					<div class="order-top-row">
						<div class="order-top-left">
							<div class="order-detail-row">
								<strong>Order #<?php echo esc_html($order_number); ?></strong>
							</div>
							<div class="order-detail-row">
								<span class="order-detail-label">Invoice:</span>
								<span class="order-detail-value"><?php echo esc_html($invoice_number ?: '-'); ?></span>
							</div>
							<div class="order-detail-row">
								<span class="order-detail-label">Date:</span>
								<span class="order-detail-value"><?php echo esc_html($order_date); ?></span>
							</div>
						</div>
						<div class="order-top-right">
							<div class="status-badge"><?php echo esc_html($status_label); ?></div>
						</div>
					</div>
					<div class="order-bottom-row">
						<div class="order-item-left">
							
							<div class="order-detail-row">
								<div class="order-item-title"><?php echo esc_html($gift_card_name); ?></div>
							</div>
							<div class="order-detail-row">
								<span class="order-detail-label">From:</span>
								<span class="order-detail-value"><?php echo esc_html($sender_name); ?></span>
							</div>
							<div class="order-detail-row">
								<span class="order-detail-label">Recipient:</span>
								<span class="order-detail-value"><?php echo esc_html($recipient_name ?: 'N/A'); ?></span>
							</div>
							<div class="order-detail-row">
								<span class="order-detail-label">Qty:</span>
								<span class="order-detail-value"><?php echo esc_html($item_qty); ?></span>
							</div>
							<?php if (!empty($gift_message)): ?>
								<div class="order-detail-row">
									<span class="order-detail-label">Message:</span>
									<span class="order-detail-value"><?php echo esc_html($gift_message); ?></span>
								</div>
							<?php endif; ?>
							<div class="order-detail-row">
								<span class="order-detail-label">Email:</span>
								<span class="order-detail-value"><?php echo esc_html($recipient_email ?: 'N/A'); ?></span>
							</div>
							<div class="order-detail-row">
								<span class="order-detail-label">Card number:</span>
								<span class="order-detail-value card-number-masked"><?php echo esc_html($card_display); ?></span>
							</div>
							<div class="order-detail-row">
								<span class="order-detail-label">Delivery:</span>
								<span class="order-detail-value"><?php echo esc_html($delivery_display); ?></span>
							</div>
							<div class="price-row">
								<div class="order-detail-row">
									<span class="order-detail-label">Price:</span>
									<span class="order-detail-value"><?php echo wc_price($item_price); ?></span>
								</div>
								<div class="order-detail-row">
									<span class="order-detail-label">Delivery:</span>
									<span class="order-detail-value"><?php echo wc_price($delivery_cost); ?></span>
								</div>
							</div>

							<div class="order-actions-buttons">
								<a href="<?php echo esc_url(add_query_arg(
									[
										'order_number'    => $order->get_id(),
										'recipient_email' => urlencode($order->get_billing_email())
									],home_url('/track-card/'))); ?>" class="btn-track-cards"> Track cards </a>
								<a href="<?php echo esc_url(add_query_arg('gc_reorder', $order->get_id(), wc_get_cart_url())); ?>" class="btn-reorder">Reorder</a>
							</div>
						</div>

						<div class="order-item-right">
							
							<div class="order-item-media">
								<?php if (!empty($gift_card_image)): ?>
									<img src="<?php echo esc_url($gift_card_image); ?>" alt="<?php echo esc_attr($gift_card_name); ?>"
										class="order-item-image">
								<?php else: ?>
									<div class="order-item-image"
										style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">
										No Image
									</div>
								<?php endif; ?>
							</div>
							<?php 
							
							if ($video_message_url) : ?>
							<div class="order-item-media order-item-media--video">
								<a href="<?php echo esc_url($video_message_url); ?>" target="_blank" rel="noopener noreferrer" class="video-placeholder video-placeholder--linked" aria-label="<?php esc_attr_e('View video', 'woocommerce'); ?>">
									<video class="pro-video-thumb" preload="metadata" muted playsinline>
										<source src="<?php echo esc_url($video_message_url); ?>" type="video/mp4">
									</video>
									<div class="video-play-icon" aria-hidden="true">
										<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M17.6 27.8L26.94 20.8C27.48 20.4 27.48 19.6 26.94 19.2L17.6 12.2C16.94 11.7 16 12.18 16 13V27C16 27.82 16.94 28.3 17.6 27.8ZM20 0C8.96 0 0 8.96 0 20C0 31.04 8.96 40 20 40C31.04 40 40 31.04 40 20C40 8.96 31.04 0 20 0ZM20 36C11.18 36 4 28.82 4 20C4 11.18 11.18 4 20 4C28.82 4 36 11.18 36 20C36 28.82 28.82 36 20 36Z" fill="#1D2D35"/>
										</svg>
									</div>
								</a>
								<div class="media-actions">
									<a href="<?php echo esc_url($video_message_url); ?>" target="_blank" rel="noopener noreferrer" class="view-video-link"><?php esc_html_e('View video', 'woocommerce'); ?></a>
									<?php if (!empty($gift_message)) : ?>
										<div class="thank-you-text"><?php echo esc_html(wp_trim_words($gift_message, 20, '…')); ?></div>
									<?php else : ?>
										<div class="thank-you-text"><?php esc_html_e('Thank you!', 'woocommerce'); ?></div>
									<?php endif; ?>
								</div>
							</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php else: ?>
			<p><?php esc_html_e('No order items found.', 'woocommerce'); ?></p>
		<?php endif; ?>

		<div class="order-total-items order-item-card">
			<div class="order-top-row">
				<div class="order-top-left">
					<h2 class="order-total-title">Order total</h2>
					<div class="order-total-info">
						<div>Order number #<?php echo esc_html($order_number); ?></div>
						<div>Invoice: <?php echo esc_html($invoice_number ?: '-'); ?></div>
						<div>Date: <?php echo esc_html($order_date); ?></div>
					</div>
				</div>
				<div class="order-top-right">
					<div class="order-total-status-badge status-badge"><?php echo esc_html($status_label); ?></div>
				</div>
			</div>

			

			<div class="order-total-items-list">
				<?php
				$order_subtotal = 0;
				foreach ($order_items as $item_id => $item):
					// Get item data for summary
					$gift_card_name = wc_get_order_item_meta($item_id, '_gift_card_name', true);
					$gift_card_price = wc_get_order_item_meta($item_id, '_gift_card_price', true);
					$gift_card_image = wc_get_order_item_meta($item_id, '_gift_card_image', true);
					$gift_card_post_id = wc_get_order_item_meta($item_id, '_gift_card_post_id', true);

					// Get product image if gift card image is not available
					if (empty($gift_card_image) && !empty($gift_card_post_id)) {
						$gift_card_image = get_the_post_thumbnail_url($gift_card_post_id, 'full');
					}

					// If still no image, try to get from product
					if (empty($gift_card_image)) {
						$product = $item->get_product();
						if ($product) {
							$gift_card_image = wp_get_attachment_image_url($product->get_image_id(), 'full');
						}
					}

					// Get gift card name from item name if not in meta
					if (empty($gift_card_name)) {
						$gift_card_name = $item->get_name();
						// Remove trailing price/numbers if present
						$gift_card_name = preg_replace('/\s+\$\d+\.?\d*\s*$/', '', $gift_card_name);
					}

					// Get price and quantity
					$item_price = $item->get_total();
					$unit_price = $item_price;
					if (!empty($gift_card_price)) {
						$unit_price = floatval($gift_card_price);
						$item_price = $unit_price * $item->get_quantity();
					}
					$item_qty = $item->get_quantity();
					$order_subtotal += $item_price;

					// Add price to gift card name (e.g., "Rebel Sports $25")
					$gift_card_name_with_price = $gift_card_name . ' $' . number_format($unit_price, 0);
					?>
					<div class="order-total-item-row">
						<div class="order-total-item-left">
							<div class="order-total-item-details">
								<div class="order-total-item-name"><?php echo esc_html($gift_card_name_with_price); ?></div>
								<div class="order-total-item-meta">Qty: <?php echo esc_html($item_qty); ?></div>
								<div class="order-total-item-subtotal">Sub-total: <?php echo wc_price($item_price); ?></div>
							</div>
						</div>
						<div class="order-total-item-right">
							<?php if (!empty($gift_card_image)): ?>
								<img src="<?php echo esc_url($gift_card_image); ?>" alt="<?php echo esc_attr($gift_card_name); ?>"
									class="order-total-item-image">
							<?php else: ?>
								<div class="order-total-item-image"
									style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; font-size: 10px;">
									No Image
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="payment-method-section">
				<div class="payment-method-title">Payment method</div>
				<div class="payment-method-details">
					<?php
					$payment_method_title = $order->get_payment_method_title();

					// Try to get card info using WooCommerce's built-in method
					$card_info = array();
					if (method_exists($order, 'get_payment_card_info')) {
						$card_info = $order->get_payment_card_info();
					}

					$card_last4 = '';
					$card_brand = '';

					if (!empty($card_info)) {
						$card_last4 = isset($card_info['last4']) ? $card_info['last4'] : '';
						$card_brand = isset($card_info['brand']) ? $card_info['brand'] : '';
					} else {
						// Fallback: Try to get from order meta
						$card_last4 = $order->get_meta('_card_last4');
						$card_brand = $order->get_meta('_card_brand');
					}

					if (!empty($card_last4) && strlen($card_last4) >= 4) {
						$card_display = 'xxxxx ' . $card_last4;
						if (!empty($card_brand)) {
							$card_display .= ' ' . strtoupper($card_brand);
						} elseif (strpos(strtolower($payment_method_title), 'mastercard') !== false) {
							$card_display .= ' MASTERCARD';
						} elseif (strpos(strtolower($payment_method_title), 'visa') !== false) {
							$card_display .= ' VISA';
						} elseif (strpos(strtolower($payment_method_title), 'amex') !== false || strpos(strtolower($payment_method_title), 'american express') !== false) {
							$card_display .= ' AMEX';
						}
						echo 'Card number: ' . esc_html($card_display);
					} else {
						echo esc_html($payment_method_title ?: 'N/A');
					}
					?>
				</div>
			</div>

			<div class="order-total-summary">
				<div class="order-total-summary-row total">
					<span>Total:</span>
					<span><?php echo wc_price($order->get_total()); ?></span>
				</div>
				<div class="order-total-summary-row">
					<span>Delivery:</span>
					<span><?php echo wc_price($delivery_cost_total); ?></span>
				</div>
			</div>

			<div class="order-total-actions">
				<a href="#" class="btn-track-cards">Track cards</a>
				<a href="<?php echo esc_url(add_query_arg('gc_reorder', $order->get_id(), wc_get_cart_url())); ?>" class="btn-reorder-total">Reorder</a>
			</div>
		</div>
	</div>
	<div class="need-help section-spacing">
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
// Display order notes if any
$notes = $order->get_customer_order_notes();
if ($notes): ?>
	<div class="order-notes-section" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
		<h2><?php esc_html_e('Order updates', 'woocommerce'); ?></h2>
		<ol class="woocommerce-OrderUpdates commentlist notes">
			<?php foreach ($notes as $note): ?>
				<li class="woocommerce-OrderUpdate comment note">
					<div class="woocommerce-OrderUpdate-inner comment_container">
						<div class="woocommerce-OrderUpdate-text comment-text">
							<p class="woocommerce-OrderUpdate-meta meta">
								<?php echo date_i18n(esc_html__('l jS \o\f F Y, h:ia', 'woocommerce'), strtotime($note->comment_date)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</p>
							<div class="woocommerce-OrderUpdate-description description">
								<?php echo wpautop(wptexturize($note->comment_content)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="clear"></div>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
<?php endif; ?>

<?php //do_action( 'woocommerce_view_order', $order_id ); ?>