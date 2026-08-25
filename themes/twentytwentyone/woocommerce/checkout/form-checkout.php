<?php
/**
 * Checkout Form Template (classic/shortcode checkout)
 *
 * This file is used when the checkout page is built with the classic
 * [woocommerce_checkout] shortcode. WooCommerce loads it via
 * wc_get_template( 'checkout/form-checkout.php' ) from the checkout shortcode.
 *
 * If your checkout page uses the WooCommerce "Checkout" block instead of the
 * shortcode, this template is NOT used — the block renders its own layout.
 * To use this template: set the checkout page content to the shortcode
 * [woocommerce_checkout] (or ensure block checkout is disabled via
 * woocommerce_has_block_template / woocommerce_is_block_template filters).
 */
if (!defined('ABSPATH')) {
    exit;
}

$checkout = WC()->checkout();

if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('You must be logged in to checkout.', 'woocommerce')));
    return;
}
remove_action('woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10);
do_action('woocommerce_before_checkout_form', $checkout);
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
    <?php
    // 1) WooCommerce notices (validation errors, etc.) – appears at top of form
    if (function_exists('woocommerce_output_all_notices')) {
        echo '<div class="woocommerce-checkout-notices-wrapper" style="margin-bottom: 1.5em;">';
        woocommerce_output_all_notices();
        echo '</div>';
    }
    // 2) Fallback: show Blackhawk amount error from transient so it is always visible
    $bhn_transient_key = 'gc_bhn_checkout_validation_error_' . (is_user_logged_in() ? get_current_user_id() : 0);
    if (function_exists('get_transient') && function_exists('delete_transient')) {
        $bhn_error = get_transient($bhn_transient_key);
        if (!empty($bhn_error)) {
            delete_transient($bhn_transient_key);
            echo '<div class="woocommerce-error woocommerce-checkout-bhn-error" role="alert" style="margin-bottom: 1.5em; padding: 1em; border: 1px solid #e2401c; background: #f8d7da; color: #721c24; border-radius: 4px;">';
            echo esc_html($bhn_error);
            echo '</div>';
        }
    }
    ?>
    <?php if ($checkout->get_checkout_fields()) : ?>
        <?php do_action('woocommerce_checkout_before_customer_details'); ?>
        
        <!-- Hide default customer details section -->
        <div class="col2-set" id="customer_details">
            <div class="col-1">
                <?php do_action('woocommerce_checkout_billing'); ?>
            </div>
            <div class="col-2">
                <?php do_action('woocommerce_checkout_shipping'); ?>
            </div>
        </div>
        
        <?php do_action('woocommerce_checkout_after_customer_details'); ?>
    <?php endif; ?>

    <div class="checkout-wrapper">
        <!-- Left Column: Order Summary -->
        <div class="checkout-order-summary">
            <div class="order-summary-wrap">
                <h2 class="order-summary-title">Your Order</h2>
                
                <div class="order-summary-items">
                    <?php
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                        
                        if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key)) {
                            $product_id = $cart_item['product_id'];
                            $product_name = $_product->get_name();
                            $quantity = $cart_item['quantity'];

                            // Wishlist state for this product (same logic as other pages)
                            $wishlist_in_checkout = false;
                            $wishlist_btn_title = __('Add to wishlist', 'woocommerce');
                            if (is_user_logged_in()) {
                                $user_wishlist = get_user_meta(get_current_user_id(), 'user_wishlist', true);
                                if (!is_array($user_wishlist)) {
                                    $user_wishlist = array();
                                }
                                $user_wishlist = array_filter(array_map('intval', $user_wishlist));
                                $wishlist_in_checkout = in_array($product_id, $user_wishlist);
                                $wishlist_btn_title = $wishlist_in_checkout ? __('Remove from wishlist', 'woocommerce') : __('Add to wishlist', 'woocommerce');
                            }
                            
                            
                            // Get gift card metadata
                            $recipient_name = isset($cart_item['recipient_name']) ? $cart_item['recipient_name'] : '';
                            $sender_name = isset($cart_item['sender_name']) ? $cart_item['sender_name'] : '';
                            $recipient_email = isset($cart_item['delivery_email']) ? $cart_item['delivery_email'] : '';
                            $recipient_phone = isset($cart_item['mobile_number']) ? $cart_item['mobile_number'] : '';
                            $gift_message = isset($cart_item['gift_message']) ? $cart_item['gift_message'] : '';
                            $video_message = isset($cart_item['video_message']) ? $cart_item['video_message'] : '';
                            $delivery_method = isset($cart_item['delivery_method']) ? $cart_item['delivery_method'] : '';
                            
                            // Try to get card design from cart item or session (set at add-to-cart)
                            $image_url = '';

                            // Helper: sanitize image URL for img src (allow data: URIs for uploaded images)
                            $sanitize_img_src = function ($val) {
                                if (empty($val) || !is_string($val)) return '';
                                if (strpos($val, 'data:image') === 0) {
                                    return esc_attr($val);
                                }
                                return esc_url($val);
                            };

                            // Use only this cart item's image (card_design / selected_gift_card_image).
                            // Do not use session so each line shows its own selection; no image = product image.
                            $raw_design = $cart_item['card_design'] ?? $cart_item['selected_gift_card_image'] ?? '';
                            if (!empty($raw_design) && is_string($raw_design)) {
                                $image_url = $sanitize_img_src($raw_design);
                            }

                            // echo '<pre>';
                            // print_r ($cart_item['video_message']); 
                            // echo '</pre>';
                            // exit;
                            if (empty($image_url)) {
                                $image_id = $_product->get_image_id();
                                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src('medium');
                            }
                            
                            // Get price (use custom price if available)
                            $item_price = isset($cart_item['gift_card_price']) ? floatval($cart_item['gift_card_price']) : $_product->get_price();
                            $item_price = (float) $item_price;
                            $sms_cost = 0;
                            // Add SMS delivery cost to displayed price when delivery method is SMS or Email+SMS (same as single product page)
                            if ( in_array( $delivery_method, array( 'sms', 'email_sms' ), true ) ) {
                                $sms_cost = 1.0;
                                $shipping_class_id = $_product->get_shipping_class_id();
                                if ( $shipping_class_id > 0 ) {
                                    $shipping_class_term = get_term( $shipping_class_id, 'product_shipping_class' );
                                    if ( $shipping_class_term && ! is_wp_error( $shipping_class_term ) ) {
                                        $term_name = $shipping_class_term->name;
                                        if ( preg_match( '/\$(\d+\.?\d*)/', $term_name, $matches ) ) {
                                            $sms_cost = (float) $matches[1];
                                        }
                                        if ( $sms_cost <= 0 ) {
                                            $term_meta = get_term_meta( $shipping_class_id, 'cost', true );
                                            if ( ! empty( $term_meta ) && is_numeric( $term_meta ) ) {
                                                $sms_cost = (float) $term_meta;
                                            }
                                        }
                                    }
                                }
                                if ( $sms_cost <= 0 || ! is_finite( $sms_cost ) ) {
                                    $sms_cost = 1.0;
                                }
                                $item_price += $sms_cost;
                            }
                            $line_total = $item_price * $quantity;
                            ?>
                            <div class="order-summary-item">

                                <button type="button" class="order-item-remove" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>" title="<?php esc_attr_e('Remove from order', 'woocommerce'); ?>" aria-label="<?php esc_attr_e('Remove from order', 'woocommerce'); ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M3 6H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M19 6L18 20C17.9435 20.5523 17.5523 21 17 21H7C6.44772 21 6.0565 20.5523 6 20L5 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </button>
                                <div class="order-item-header">
                                        <div class="order-item-image wc-block-components-order-summary-item__image<?php echo $video_message ? ' has-video' : ''; ?>">
                                        <img src="<?php echo $image_url; /* Already sanitized by sanitize_img_src */ ?>" alt="<?php echo esc_attr($product_name); ?>" class="order-item-img">
                                        <?php if ($video_message): ?>
                                            <div class="order-item-media pro-video">
                                                <a href="<?php echo esc_url($video_message); ?>" target="_blank" rel="noopener noreferrer" class="video-placeholder" aria-label="<?php esc_attr_e('View video', 'woocommerce'); ?>">
                                                    <video class="pro-video-thumb" preload="metadata" muted playsinline>
                                                        <source src="<?php echo esc_url($video_message); ?>" type="video/mp4">
                                                    </video>
                                                    <div class="video-play-icon" aria-hidden="true">
                                                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M17.6 27.8L26.94 20.8C27.48 20.4 27.48 19.6 26.94 19.2L17.6 12.2C16.94 11.7 16 12.18 16 13V27C16 27.82 16.94 28.3 17.6 27.8ZM20 0C8.96 0 0 8.96 0 20C0 31.04 8.96 40 20 40C31.04 40 40 31.04 40 20C40 8.96 31.04 0 20 0ZM20 36C11.18 36 4 28.82 4 20C4 11.18 11.18 4 20 4C28.82 4 36 11.18 36 20C36 28.82 28.82 36 20 36Z" fill="#1D2D35"/>
                                                        </svg>
                                                    </div>
                                                </a>
                                                <div class="media-actions">
                                                    <a href="<?php echo esc_url($video_message); ?>" target="_blank" rel="noopener noreferrer" class="view-video-link"><?php esc_html_e('View video', 'woocommerce'); ?></a>
                                                    <?php esc_html_e('Thank you!', 'woocommerce'); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-item-info">
                                        <div class="order-item-quantity-name">
                                             <a href="<?php echo get_permalink( $product_id ); ?>">
                                                <span class="order-item-name"><?php echo esc_html($product_name); ?></span>
                                            </a>
                                        </div>
                                        <div class="order-item-price">
                                            <div class="order-item-pricing">
                                                <span class="order-item-price"><?php echo wc_price($line_total); ?></span>
                                                <?php if ( $sms_cost > 0 ) : ?>
                                                <span class="order-item-sms-included"><?php echo esc_html__( 'Includes', 'woocommerce' ); ?> <?php echo wc_price( $sms_cost * $quantity ); ?> <?php echo esc_html__( 'SMS delivery Cost', 'woocommerce' ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="order-item-controls">
                                            <div class="quantity-selector-wrapper">
                                                <select class="quantity-dropdown" disabled>
                                                    <option value="<?php echo esc_attr($quantity); ?>" selected>
                                                        <?php echo esc_html($quantity); ?>
                                                    </option>
                                                </select>
                                            </div>

                                            <button type="button" class="order-item-favorite custom-wishlist-button <?php echo $wishlist_in_checkout ? 'fill' : ''; ?>" data-product-id="<?php echo esc_attr($product_id); ?>" title="<?php echo esc_attr($wishlist_btn_title); ?>" aria-label="<?php echo esc_attr($wishlist_btn_title); ?>">
                                                <svg width="20" height="18" viewBox="0 0 20 18" fill="none">
                                                    <path d="M10 17.5L8.55 16.1C3.4 11.55 0 8.65 0 5C0 2.25 2.25 0 5 0C6.8 0 8.45 0.9 10 2.35C11.55 0.9 13.2 0 15 0C17.75 0 20 2.25 20 5C20 8.65 16.6 11.55 11.45 16.1L10 17.5Z" fill="currentColor"/>
                                                </svg>
                                            </button>

                                        </div>

                                        <?php if ($recipient_name || $sender_name || $recipient_email || $gift_message): ?>
                                            <div class="order-item-details">
                                                <?php if ($sender_name): ?>
                                                <div class="order-detail-row">
                                                    <span class="detail-label">From:</span>
                                                    <span class="detail-value"><?php echo esc_html($sender_name); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($recipient_name): ?>
                                                <div class="order-detail-row">
                                                    <span class="detail-label">Recipient:</span>
                                                    <span class="detail-value"><?php echo esc_html($recipient_name); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($recipient_email): ?>
                                                <div class="order-detail-row">
                                                    <span class="detail-label">Email:</span>
                                                    <span class="detail-value"><?php echo esc_html($recipient_email); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($recipient_phone): ?>
                                                <div class="order-detail-row">
                                                    <span class="detail-label">Phone:</span>
                                                    <span class="detail-value"><?php echo esc_html($recipient_phone); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($gift_message): ?>
                                                <div class="order-detail-row">
                                                    <span class="detail-label">Message:</span>
                                                    <span class="detail-value"><?php echo esc_html($gift_message); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                    </div>
                                </div>
                                
                                
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
                
                <?php
                do_action('woocommerce_checkout_before_order_review_heading');
                ?>

                <?php 
                $total_fulfillment_cost  = 0;
                $total_delivery_cost     = 0;
                $total_gst_cost          = 0;
                $total_sms_delivery_cost = 0;
                $total_stripe_processing_fee = 0.0;

                if ( WC()->cart && ! WC()->cart->is_empty() ) {

                    foreach ( WC()->cart->get_cart() as $cart_item ) {

                        $product_id = ! empty( $cart_item['variation_id'] ) 
                            ? $cart_item['variation_id'] 
                            : $cart_item['product_id'];

                        $quantity = $cart_item['quantity'];
                        $delivery_method = isset( $cart_item['delivery_method'] ) ? $cart_item['delivery_method'] : '';
                        
                        $fulfillment_price  = get_post_meta( $product_id, 'j_a_c_fulfillment_cost', true );
                        $gst_price          = get_post_meta( $product_id, '_gst', true );

                        
                        $total_fulfillment_cost += (float) $fulfillment_price * $quantity;
                        $total_gst_cost         += (float) $gst_price * $quantity;
                        // SMS delivery cost: from product shipping class (or default 1) when delivery method is SMS or Email+SMS (same logic as single product page)
                        if ( in_array( $delivery_method, array( 'sms', 'email_sms' ), true ) ) {
                            $product = isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ? $cart_item['data'] : wc_get_product( $product_id );
                            $sms_cost = 1.0;
                            if ( $product && is_callable( array( $product, 'get_shipping_class_id' ) ) ) {
                                $shipping_class_id = $product->get_shipping_class_id();
                                if ( $shipping_class_id > 0 ) {
                                    $shipping_class_term = get_term( (int) $shipping_class_id, 'product_shipping_class' );
                                    if ( $shipping_class_term && ! is_wp_error( $shipping_class_term ) ) {
                                        $term_name = $shipping_class_term->name;
                                        if ( preg_match( '/\$(\d+\.?\d*)/', $term_name, $matches ) ) {
                                            $sms_cost = (float) $matches[1];
                                        }
                                        if ( $sms_cost <= 0 ) {
                                            $term_meta = get_term_meta( $shipping_class_id, 'cost', true );
                                            if ( ! empty( $term_meta ) && is_numeric( $term_meta ) ) {
                                                $sms_cost = (float) $term_meta;
                                            }
                                        }
                                    }
                                }
                            }
                            if ( $sms_cost <= 0 || ! is_finite( $sms_cost ) ) {
                                $sms_cost = 1.0;
                            }
                            $total_sms_delivery_cost += $sms_cost * $quantity;
                        }
                    }


                   // ─── Stripe fee calculation (gross-up so YOU receive the full amount) ──────
                    // $STRIPE_RATE = 0.033; // 1.7%
                    // $STRIPE_FLAT    = 0.30;   // 30¢
                    // $STRIPE_TAX     = 0.10;   // 10% GST that Stripe charges on their fee (AU)

                    $STRIPE_RATE = 0.017; // 1.7%
                    $STRIPE_FLAT = 0.30;  // 30 cents
                    $STRIPE_TAX  = 0.10;  // 10% GST on Stripe fee

                    // Effective rate and flat after Stripe's own GST
                    $effective_rate = $STRIPE_RATE * ( 1 + $STRIPE_TAX );  // 0.0187
                    $effective_flat = $STRIPE_FLAT * ( 1 + $STRIPE_TAX );  // 0.33

                    // Base amount Stripe will actually process
                    // (cart total + fulfillment + GST + SMS — everything except the fee itself)
                    $stripe_base = (float) WC()->cart->get_cart_contents_total()
                                 + (float) WC()->cart->get_cart_contents_tax()
                                 - (float) WC()->cart->get_discount_total()
                                 - (float) WC()->cart->get_discount_tax()
                                 + $total_fulfillment_cost
                                 + $total_gst_cost
                                 + $total_sms_delivery_cost;

                    // Gross-up formula accounting for Stripe's own GST on the fee
                    $total_stripe_processing_fee = round(
                        ( $stripe_base + $effective_flat ) / ( 1 - $effective_rate ) - $stripe_base,
                        2
                    );
                    // Cart base value passed to JS for dynamic fee recalculation on payment method switch
                    $js_cart_base  = (float) WC()->cart->get_cart_contents_total();
                    $js_cart_base += (float) WC()->cart->get_cart_contents_tax();
                    $js_cart_base -= (float) WC()->cart->get_discount_total();
                    $js_cart_base -= (float) WC()->cart->get_discount_tax();
                }
                ?>
                <?php
                // Subtotal for display: cart subtotal + SMS delivery cost (so Subtotal includes SMS when applicable)
                $display_subtotal = (float) WC()->cart->get_subtotal() + $total_sms_delivery_cost;
                // echo 'display_subtotal';
                // echo '<pre>';
                // print_r($total_sms_delivery_cost);
                // echo '</pre>';
                // exit;
                ?>
                
                <div class="order-summary-totals">
                    <div class="total-row">
                        <span class="total-label">Subtotal:</span>
                        <span class="total-value"><?php echo wc_price( $display_subtotal ); ?></span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Fullfillment Cost:</span>
                        <span class="total-value">
                            <?php echo wc_price( $total_fulfillment_cost ); ?>
                        </span>
                    </div>

                    <div class="total-row">
                        <span class="total-label">GST:</span>
                        <span class="total-value">
                            <?php echo wc_price( $total_gst_cost ); ?>
                        </span>
                    </div>
                       <?php if ( $total_stripe_processing_fee > 0 ) : ?>
                        <div class="total-row">
                            <span class="total-label"><?php echo esc_html__( 'Stripe Processing Fees', 'woocommerce' ); ?></span>
                            <span class="total-value"><?php echo wc_price( $total_stripe_processing_fee ); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="total-row order-total-row">
                        <span class="total-label">TOTAL:</span>
                        <span class="total-value"><?php echo wc_price( $stripe_base + $total_stripe_processing_fee ); ?></span>
                    </div>
                </div>
                <div class="custom-review-table">
                    <?php do_action( 'woocommerce_checkout_order_review_table_only' ); ?>
                </div>
            </div>

            <div class="payment-wrap">
                <!-- Contact Information (above order_review, inside payment-wrap) -->
                <?php if (!is_user_logged_in()){ ?>
                        <?php
                    $prefill_contact_email = '';
                    $prefill_contact_phone = '';
                    if ( WC()->cart && ! WC()->cart->is_empty() ) {
                        foreach ( WC()->cart->get_cart() as $cart_item ) {
                            if ( empty( $prefill_contact_email ) ) {
                                if ( ! empty( $cart_item['delivery_email'] ) ) {
                                    $prefill_contact_email = sanitize_email( $cart_item['delivery_email'] );
                                } elseif ( ! empty( $cart_item['recipient_email'] ) ) {
                                    $prefill_contact_email = sanitize_email( $cart_item['recipient_email'] );
                                }
                            }
                            if ( empty( $prefill_contact_phone ) && ! empty( $cart_item['mobile_number'] ) ) {
                                $prefill_contact_phone = sanitize_text_field( $cart_item['mobile_number'] );
                            }
                            if ( ! empty( $prefill_contact_email ) && ! empty( $prefill_contact_phone ) ) {
                                break;
                            }
                        }
                    }

                    $contact_email_value = $checkout->get_value( 'contact_email' );
                    if ( empty( $contact_email_value ) && ! empty( $prefill_contact_email ) ) {
                        $contact_email_value = $prefill_contact_email;
                    }

                    $contact_phone_value = $checkout->get_value( 'contact_phone' );
                    if ( empty( $contact_phone_value ) && ! empty( $prefill_contact_phone ) ) {
                        $contact_phone_value = $prefill_contact_phone;
                    }
                    ?>
                    <div class="checkout-contact-information">
                        <h3 class="contact-information-heading"><?php esc_html_e( 'Contact Information', 'woocommerce' ); ?></h3>
                        <div class="contact-information-fields">
                            <p class="form-row form-row-wide" id="contact_email_field">
                                <label for="contact_email"><?php esc_html_e( 'Email', 'woocommerce' ); ?> <span class="required">*</span></label>
                                <input type="email" class="input-text" name="contact_email" id="contact_email" placeholder="<?php esc_attr_e( 'Email', 'woocommerce' ); ?>" value="<?php echo esc_attr( $contact_email_value ); ?>" />
                            </p>
                            <p class="form-row form-row-wide" id="contact_phone_field">
                                <label for="contact_phone"><?php esc_html_e( 'Phone Number', 'woocommerce' ); ?> <span class="required">*</span></label>
                                <input type="tel" class="input-text" name="contact_phone" id="contact_phone" placeholder="<?php esc_attr_e( 'Phone number', 'woocommerce' ); ?>" pattern="^\+61\d{8,9}$" value="<?php echo esc_attr( $contact_phone_value ); ?>" />
                            </p>
                            <p class="form-row form-row-wide terms-checkbox">
                                <label class="checkbox">
                                    <input type="checkbox" name="accept_terms" id="accept_terms" value="1" <?php checked( $checkout->get_value( 'accept_terms' ), 1 ); ?> required="required" />
                                    <span><?php esc_html_e( 'I accept the Terms and Conditions and agree to giftcardsplus collecting my personal information (PI) pursuant to our Privacy Policy', 'woocommerce' ); ?> <span class="required">*</span></span>
                                </label>
                            </p>
                            <p class="form-row form-row-wide marketing-checkbox">
                                <label class="checkbox">
                                    <input type="checkbox" name="marketing_optin" id="marketing_optin" value="1" <?php checked( $checkout->get_value( 'marketing_optin' ), 0 ); ?> />
                                    <span><?php esc_html_e( 'Don\'t miss out! How would you like to hear about our offers and promotions', 'woocommerce' ); ?></span>
                                </label>
                            </p>
                        </div>
                    </div>
                <?php } ?>
                <div id="order_review" class="woocommerce-checkout-review-order">
                    <?php do_action( 'woocommerce_checkout_order_review_payment_only' ); ?>
                </div>
            </div>
            
            <?php do_action('woocommerce_checkout_after_order_review'); ?>
        </div>
        
        <!-- Right Column: Payment Form -->
        
    </div>
     <!-- Stripe fee dynamic show/hide based on selected payment method -->
    <script>
        jQuery(document).ready(function($) {
            var cartBase = <?php echo json_encode( round( $js_cart_base, 2 ) ); ?>;
            var priceDec = <?php echo intval( wc_get_price_decimals() ); ?>;
            var currSymbol = '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>';

            function calcStripeFee(base) {
                return Math.round(((base * 0.017) + 0.30) * Math.pow(10, priceDec)) / Math.pow(10, priceDec);
            }

            function formatFee(amount) {
                return currSymbol + amount.toFixed(priceDec);
            }

            function updateStripeFeeRow(paymentMethod) {
                var $row = $('.stripe-fee-row');
                if (paymentMethod === 'stripe' && cartBase > 0) {
                    var fee = calcStripeFee(cartBase);
                    $row.find('.stripe-fee-amount').html(formatFee(fee));
                    $row.show();
                } else {
                    $row.hide();
                }
            }

            // On payment method change
            $('form.checkout').on('change', 'input[name="payment_method"]', function() {
                updateStripeFeeRow($(this).val());
            });

            // After WooCommerce AJAX checkout update
            $(document.body).on('updated_checkout', function() {
                var chosen = $('input[name="payment_method"]:checked').val() || '';
                updateStripeFeeRow(chosen);
            });

            // On page load
            var initialMethod = $('input[name="payment_method"]:checked').val() || '';
            updateStripeFeeRow(initialMethod);
        });
    </script>

</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>