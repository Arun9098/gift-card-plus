jQuery(document).ready(function($) {


    (function ($) {

        if ($('form.checkout').length && $('form.checkout').hasClass('processing')) {
            return;
        }

        if (window.wc && wc.checkout && wc.checkout.isProcessing) {
            return;
        }

    })(jQuery);

    // Theme-only params (cart_items, user_wishlist, ajax_url, nonce). Prefer theme_checkout_params
    // so we never overwrite WooCommerce's wc_checkout_params (checkout_url, wc_ajax_url, etc.).
    function getThemeCheckoutParams() {
        if (typeof theme_checkout_params !== 'undefined') return theme_checkout_params;
        if (typeof wc_checkout_params !== 'undefined') return wc_checkout_params;
        return {};
    }

    let blockCustomCheckoutJS = false;

    // Billing section is not rendered (removed by theme), so #billing_email does not exist in the DOM.
    // Stripe requires params.billing_details.email when the Payment Element has fields.billing_details.email = "never".
    // Ensure a hidden billing_email input exists so Stripe's createPaymentMethod() can read it and the form submits it.
    function ensureBillingEmailInput() {
        var $form = $('form.checkout');
        if (!$form.length) return null;
        var $el = $('#billing_email');
        if ($el.length) return $el;
        var $hidden = $('<input type="hidden" id="billing_email" name="billing_email" />');
        $form.append($hidden);
        return $hidden;
    }

    function getBillingEmailValue() {
        if ($('#payment_email').length && $('#payment_email').val()) {
            return $('#payment_email').val();
        }
        if (typeof wpApiSettings !== 'undefined' && wpApiSettings.user && wpApiSettings.user.email) {
            return wpApiSettings.user.email;
        }
        if (typeof theme_checkout_params !== 'undefined' && theme_checkout_params.customer_email) {
            return theme_checkout_params.customer_email;
        }
        return '';
    }
    function syncBillingEmailForStripe() {
        var $emailField = ensureBillingEmailInput();
        if (!$emailField) return;
        var email = getBillingEmailValue();
        if (email) {
            $emailField.val(email);
        }
    }

    // Hidden inputs for Stripe/WooCommerce (not WC checkout fields — keeps Stripe country dropdown on "auto").
    function ensureStripeCountryInputs() {
        var $form = $('form.checkout');
        if (!$form.length) return;
        if (!$('#billing_country').length) {
            $form.append('<input type="hidden" id="billing_country" name="billing_country" value="" />');
        }
        if (!$('#stripe_selected_country').length) {
            $form.append('<input type="hidden" id="stripe_selected_country" name="stripe_selected_country" value="" />');
        }
    }

    function getStripeCollectedCountry() {
        if (typeof window.gcpGetStripeBillingCountry === 'function') {
            var resolved = window.gcpGetStripeBillingCountry();
            if (resolved) return resolved;
        }
        if (window.__gcStripeCollectedCountry && /^[A-Z]{2}$/i.test(window.__gcStripeCollectedCountry)) {
            return window.__gcStripeCollectedCountry.toUpperCase();
        }
        var $billingCountry = $('#billing_country');
        if ($billingCountry.length && $billingCountry.val() && /^[A-Z]{2}$/i.test($billingCountry.val())) {
            return $billingCountry.val().toUpperCase();
        }
        return 'AU';
    }

    function syncBillingCountryForStripe() {
        ensureStripeCountryInputs();
        var country = getStripeCollectedCountry();
        if (!country) return;
        $('#billing_country, #stripe_selected_country').val(country);
        if (typeof window.gcpSyncStripeBillingCountry === 'function') {
            window.gcpSyncStripeBillingCountry(country);
        }
    }

    function syncStripeBillingFieldsForCheckout() {
        syncBillingEmailForStripe();
        syncBillingCountryForStripe();
    }

    jQuery(document).on('click', '#place_order', function () {
        syncStripeBillingFieldsForCheckout();
        blockCustomCheckoutJS = true;
    });
    if ($('form.checkout').length) {
        ensureBillingEmailInput();
        ensureStripeCountryInputs();
        syncStripeBillingFieldsForCheckout();
    }

    $(document).on('gc_stripe_billing_country_changed', function (event, country) {
        if (country && /^[A-Z]{2}$/i.test(country)) {
            ensureStripeCountryInputs();
            $('#billing_country, #stripe_selected_country').val(country.toUpperCase());
        }
    });


    var isPlacingOrder = false;

    jQuery(document).on('submit', 'form.checkout', function () {
        isPlacingOrder = true;
    });

    // Message display functions for checkout page
    function showCheckoutMessage(message, type) {
        type = type || 'error'; // 'error', 'success', 'info', 'warning'
        
        // Try to find existing message container, or create one
        var $container = $('#checkout-message-container');
        if ($container.length === 0) {
            // Create message container at the top of checkout form
            $container = $('<div id="checkout-message-container" class="checkout-message-container" style="display: none; margin-bottom: 20px; padding: 16px; border-radius: 4px; position: relative;">' +
                '<div class="checkout-message-content">' +
                '<span class="checkout-message-text"></span>' +
                '<button type="button" class="checkout-message-close" aria-label="Close message" style="position: absolute; top: 8px; right: 8px; background: none; border: none; font-size: 24px; cursor: pointer; color: inherit;">&times;</button>' +
                '</div>' +
                '</div>');
            
            // Insert at the top of checkout form or body
            var $checkoutForm = $('form.checkout, .woocommerce-checkout, body').first();
            if ($checkoutForm.length) {
                $checkoutForm.prepend($container);
            } else {
                $('body').prepend($container);
            }
        }
        
        var $content = $container.find('.checkout-message-content');
        var $text = $container.find('.checkout-message-text');
        
        // Remove existing type classes
        $container.removeClass('message-error message-success message-info message-warning');
        // Add new type class
        $container.addClass('message-' + type);
        
        // Set message text
        $text.text(message);
        
        // Show container
        $container.slideDown(300);
        
        // Auto-hide after 5 seconds for success/info, 7 seconds for errors
        var autoHideDelay = (type === 'success' || type === 'info') ? 5000 : 7000;
        setTimeout(function() {
            hideCheckoutMessage();
        }, autoHideDelay);
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $container.offset().top - 100
        }, 500);
    }
    
    function hideCheckoutMessage() {
        $('#checkout-message-container').slideUp(300);
    }
    
    // Close button handler
    $(document).on('click', '.checkout-message-close', function() {
        hideCheckoutMessage();
    });
    
    // Quantity selector functionality
    $(document).on('click', '.quantity-btn.minus', function(e) {
        e.preventDefault();
        var $input = $(this).closest('.quantity-selector-wrapper').find('.quantity-input');
        var currentVal = parseInt($input.val()) || 1;
        if (currentVal > 1) {
            $input.val(currentVal - 1);
            updateCartItem($input);
        }
    });

    $(document).on('click', '.quantity-btn.plus', function(e) {
        e.preventDefault();
        var $input = $(this).closest('.quantity-selector-wrapper').find('.quantity-input');
        var currentVal = parseInt($input.val()) || 1;
        $input.val(currentVal + 1);
        updateCartItem($input);
    });

        // Remove product from checkout (cross icon)
    $(document).on('click', '.order-item-remove', function(e) {
        console.log('Clicked.....');
        e.preventDefault();
        // if (isPlacingOrder) {
        //     console.log('isPlacingOrder Clicked.....');
        //     return;
        // }
        var $btn = $(this);
        var cartItemKey = $btn.data('cart-item-key');
        if (!cartItemKey) return;
        $btn.prop('disabled', true).addClass('loading');
        $.ajax({
            type: 'POST',
            url: getThemeCheckoutParams().ajax_url,
            dataType: 'json',
            data: {
                action: 'remove_checkout_item',
                cart_item_key: cartItemKey
            },
            success: function(response) {
                if (response && response.success) {
                    if (response.data && response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else {
                        window.location.reload();
                    }
                } else {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            },
            error: function() {
                $btn.prop('disabled', false).removeClass('loading');
            }
        });
    });

    function updateCartItem($input) {

        if (isPlacingOrder) {
            return; // 🚫 DO NOTHING during order submit
        }

        var cartItemKey = $input.data('key');
        var newQuantity = parseInt($input.val()) || 1;
        
        // Update cart via AJAX
        $.ajax({
            type: 'POST',
            url: getThemeCheckoutParams().ajax_url,
            dataType: 'json',
            data: {
                action: 'update_checkout_item_quantity',
                cart_item_key: cartItemKey,
                quantity: newQuantity
            },
            success: function (response) {
                if (response && response.success) {
                   if (!blockCustomCheckoutJS) {
    $('body').trigger('update_checkout');
}

                }
            }
        });
    }

    // Map custom payment fields to billing fields before submit
    $('form.checkout').on('checkout_place_order', function(e) {
        isPlacingOrder = true;

        console.log("working after click");
        var fullName = $('#payment_full_name').val();
        if (fullName) {
            var nameParts = fullName.split(' ', 2);
            if (nameParts.length >= 2) {
                if ($('#billing_first_name').length) {
                    $('#billing_first_name').val(nameParts[0]);
                }
                if ($('#billing_last_name').length) {
                    $('#billing_last_name').val(nameParts[1]);
                }
            } else {
                if ($('#billing_first_name').length) {
                    $('#billing_first_name').val(nameParts[0]);
                }
                if ($('#billing_last_name').length) {
                    $('#billing_last_name').val('');
                }
            }
        }
        
        var country = $('#payment_country').val() || getStripeCollectedCountry();
        if (country) {
            ensureStripeCountryInputs();
            $('#billing_country, #stripe_selected_country').val(country);
            if (typeof window.gcpSyncStripeBillingCountry === 'function') {
                window.gcpSyncStripeBillingCountry(country);
            }
        }

        var address = $('#payment_address').val();
        if (address && $('#billing_address_1').length) {
            $('#billing_address_1').val(address);
        }
        
        // Ensure billing_email/country are set before Stripe submit/createPaymentMethod run.
        syncStripeBillingFieldsForCheckout();
    });

    // Keep hidden #billing_email in sync with #payment_email (Stripe reads billing_email from the form)
    $(document).on('change input blur', '#payment_email', function() {
        syncBillingEmailForStripe();
    });

    // Update payment method selection styling
    $('#payment .payment_methods li').on('click', function() {
        $('#payment .payment_methods li').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
    });

    // Initialize selected payment method
    $('#payment .payment_methods li:has(input[type="radio"]:checked)').addClass('selected');

    // Cart items and wishlist from session (passed via wp_localize_script on page load — no AJAX)
    var _tp = getThemeCheckoutParams();
    var cartItemsMap = (_tp && _tp.cart_items) ? _tp.cart_items : [];
    var userWishlistArray = (_tp && _tp.user_wishlist) ? _tp.user_wishlist : [];

    // console.log("TEst : ",cartItemsMap);
    
    // Get cart items data (session-based; no AJAX fetch)
    function getCartItemsData() {
        return cartItemsMap;
    }
    
    // Get user wishlist array
    function getUserWishlist() {
        if (userWishlistArray !== null) {
            return userWishlistArray;
        }
        
        // Try to get wishlist from theme/WC params if available
        var _tp = getThemeCheckoutParams();
        if (_tp && _tp.user_wishlist) {
            userWishlistArray = _tp.user_wishlist;
            return userWishlistArray;
        }
        
        // Return empty array if not available
        userWishlistArray = [];
        return userWishlistArray;
    }
    
    // Check if product is in wishlist
    function isProductInWishlist(productId, variationId) {
        var wishlist = getUserWishlist();
        if (!wishlist || wishlist.length === 0) {
            return false;
        }
        
        // Convert to integers for comparison
        productId = parseInt(productId);
        variationId = variationId ? parseInt(variationId) : 0;
        
        // Check if variation_id is in wishlist (preferred for variable products)
        if (variationId > 0 && wishlist.indexOf(variationId) !== -1) {
            return true;
        }
        
        // Check if product_id is in wishlist
        if (wishlist.indexOf(productId) !== -1) {
            return true;
        }
        
        return false;
    }
    
    // Update wishlist array in memory after toggle
    function updateWishlistArray(productId, isInWishlist) {
        productId = parseInt(productId);
        var wishlist = getUserWishlist();
        
        if (isInWishlist) {
            // Add to wishlist if not already present
            if (wishlist.indexOf(productId) === -1) {
                wishlist.push(productId);
                userWishlistArray = wishlist;
            }
        } else {
            // Remove from wishlist
            var index = wishlist.indexOf(productId);
            if (index !== -1) {
                wishlist.splice(index, 1);
                userWishlistArray = wishlist;
            }
        }
    }
    
    // Update all instances of a product's wishlist button state
    function updateAllProductInstances(productId, variationId, isInWishlist) {
        productId = parseInt(productId);
        variationId = variationId ? parseInt(variationId) : 0;
        
        // The ID that was actually sent to server (what's stored in wishlist)
        // For variations, we use variation_id; for simple products, we use product_id
        var wishlistId = (variationId > 0) ? variationId : productId;
        
        // Find all wishlist buttons and check if they match this product
        $('.custom-wishlist-button').each(function() {
            var $button = $(this);
            var buttonProductId = parseInt($button.attr('data-product-id')) || 0;
            
            // Get variation ID from the order item if available
            var $orderItem = $button.closest('.wc-block-components-order-summary-item');
            var buttonVariationId = null;
            
            // Try to get variation ID from cart items by index
            if ($orderItem.length) {
                var index = $('.wc-block-components-order-summary-item').index($orderItem);
                var cartItems = getCartItemsData();
                if (cartItems && cartItems.length > index && cartItems[index]) {
                    buttonVariationId = cartItems[index].variation_id || null;
                    if (buttonVariationId) {
                        buttonVariationId = parseInt(buttonVariationId);
                    }
                }
            }
            
            // The ID that would be sent to server for this button (same logic as in click handler)
            var buttonWishlistId = (buttonVariationId && buttonVariationId > 0) ? buttonVariationId : buttonProductId;
            
            // Match by the exact ID that would be stored in wishlist
            // This ensures:
            // - All instances of the same variation get updated together
            // - All instances of the same simple product get updated together
            // - Different variations of the same product are treated separately
            var isMatch = (wishlistId === buttonWishlistId);
            
            // Update button state if it matches (class, title, aria-label)
            if (isMatch) {
                var newTitle = isInWishlist ? 'Remove from wishlist' : 'Add to wishlist';
                if (isInWishlist) {
                    $button.addClass('fill');
                } else {
                    $button.removeClass('fill');
                }
                $button.attr('title', newTitle).attr('aria-label', newTitle);
            }
        });
    }
    
    // Extract product ID from order summary item
    function extractProductId($orderItem, index) {
        // Try to get from data attribute first
        var productId = $orderItem.data('product-id') || $orderItem.attr('data-product-id');
        if (productId) return productId;
        
        // Try to get from cart items map by index
        var cartItems = getCartItemsData();
        if (cartItems && cartItems.length > index) {
            var cartItem = cartItems[index];
            if (cartItem && cartItem.product_id) {
                return cartItem.product_id;
            }
            if (cartItem && cartItem.variation_id && cartItem.variation_id > 0) {
                return cartItem.variation_id;
            }
        }
        
        // Try to match by product name from cart items
        var $productName = $orderItem.find('.wc-block-components-product-name');
        if ($productName.length && cartItems && cartItems.length > 0) {
            var productNameText = $productName.text().trim();
            for (var i = 0; i < cartItems.length; i++) {
                if (cartItems[i].product_name && cartItems[i].product_name.trim() === productNameText) {
                    if (cartItems[i].product_id) {
                        return cartItems[i].product_id;
                    }
                    if (cartItems[i].variation_id && cartItems[i].variation_id > 0) {
                        return cartItems[i].variation_id;
                    }
                }
            }
        }
        
        // Try to extract from product link
        var $link = $orderItem.find('.wc-block-components-product-name a');
        if ($link.length) {
            var href = $link.attr('href');
            if (href) {
                // Try to match product ID from URL (handle both ID and slug)
                var match = href.match(/[?&]product_id=(\d+)/i) || 
                           href.match(/\/product\/(\d+)/) ||
                           href.match(/\/p\/(\d+)/);
                if (match && match[1]) {
                    return match[1];
                }
                
                // If it's a slug, try to extract slug and we can look it up
                var slugMatch = href.match(/\/product\/([^\/\?]+)/);
                if (slugMatch && slugMatch[1]) {
                    // Store slug for potential later lookup
                    $orderItem.data('product-slug', slugMatch[1]);
                }
            }
        }
        
        // Try to get from image data attribute
        var $image = $orderItem.find('img');
        if ($image.length) {
            var imageProductId = $image.data('product-id');
            if (imageProductId) return imageProductId;
        }
        
        // Try to get from image src (might contain product ID in filename)
        if ($image.length) {
            var imageSrc = $image.attr('src') || $image.attr('data-src');
            if (imageSrc) {
                var srcMatch = imageSrc.match(/-(\d+)x(\d+)\./);
                // This won't give us product ID directly, so we'll skip it
            }
        }
        
        return '';
    }
    function applyGiftCardImages(cartItemsMap) {

        jQuery('.wc-block-components-order-summary-item').each(function () {

            const $item = jQuery(this);
            const productName = $item
                .find('.wc-block-components-product-name')
                .text()
                .trim();

            Object.values(cartItemsMap).forEach(item => {
                if (item.product_name === productName && item.selected_image) {

                    const $img = $item.find(
                        '.wc-block-components-order-summary-item__image img'
                    );

                    if ($img.length) {
                        $img.attr('src', item.selected_image);
                    }
                }
            });
        });
    }

    jQuery(document.body).on('updated_checkout', function () {
        if (isPlacingOrder) return;
        ensureBillingEmailInput();
        ensureStripeCountryInputs();
        syncStripeBillingFieldsForCheckout();
        applyGiftCardImages(cartItemsMap);
    });

    // Initialize checkout UI from session-based cart data (no AJAX)
    function initCheckoutFromSessionData() {
        applyGiftCardImages(cartItemsMap);
        updateButtonProductIds();
        addWishlistButtons();
        moveQuantityAfterWishlist();
        updateProductMetadata();
    }

    // Update existing buttons with product IDs from cart data
    function updateButtonProductIds() {
        if (!cartItemsMap || cartItemsMap.length === 0) return;
        
        $('.wc-block-components-order-summary-item').each(function(index) {
            var $orderItem = $(this);
            var $button = $orderItem.find('.custom-wishlist-button');
            
            if ($button.length) {
                var productId = extractProductId($orderItem, index);
                var variationId = null;
                
                if (cartItemsMap.length > index && cartItemsMap[index]) {
                    variationId = cartItemsMap[index].variation_id || null;
                }
                
                if (productId) {
                    $button.attr('data-product-id', productId);
                    
                    // Check and update wishlist status
                    var isInWishlist = isProductInWishlist(productId, variationId);
                    if (isInWishlist) {
                        $button.addClass('fill');
                    } else {
                        $button.removeClass('fill');
                    }
                }
            }
        });
    }

    // Convert quantity span to dropdown
    function convertQuantityToDropdown($quantityElement, $orderItem, index) {
        // console.log('[convertQuantityToDropdown] Converting quantity to dropdown for item #' + (index + 1));
        // console.log('[convertQuantityToDropdown] Element tag: ' + $quantityElement[0].tagName);
        // console.log('[convertQuantityToDropdown] Element classes: ' + $quantityElement.attr('class'));
        
        // Skip if already a dropdown
        if ($quantityElement.is('select') || $quantityElement.hasClass('quantity-dropdown')) {
            // console.log('[convertQuantityToDropdown] Already a dropdown, skipping');
            return $quantityElement;
        }
        
        // Get current quantity value
        var currentQuantity = 1;
        var $spanElement = $quantityElement.is('span') ? $quantityElement : $quantityElement.find('span').first();
        
        if ($spanElement.length > 0) {
            var quantityText = $spanElement.text().trim();
            currentQuantity = parseInt(quantityText) || 1;
            // console.log('[convertQuantityToDropdown] Current quantity from span: ' + currentQuantity);
        } else {
            // Try to get from the element's text
            var quantityText = $quantityElement.text().trim();
            currentQuantity = parseInt(quantityText) || 1;
            // console.log('[convertQuantityToDropdown] Current quantity from element text: ' + currentQuantity);
        }
        
        // Get cart item key - try to find it from the order item
        var cartItemKey = null;
        var $cartItemInput = $orderItem.find('input[data-key], input[name*="cart"][name*="key"]');
        if ($cartItemInput.length > 0) {
            cartItemKey = $cartItemInput.first().data('key') || $cartItemInput.first().attr('data-key');
        }
        
        // If not found, try to get from cart items data
        if (!cartItemKey) {
            var cartItems = getCartItemsData();
            if (cartItems && cartItems.length > index && cartItems[index]) {
                cartItemKey = cartItems[index].key || cartItems[index].cart_item_key;
            }
        }
        
        // Also try to get from WooCommerce cart data
        if (!cartItemKey && getThemeCheckoutParams().ajax_url) {
            // Try to extract from the order item's data attributes or structure
            var $itemData = $orderItem.find('[data-cart-item-key]');
            if ($itemData.length > 0) {
                cartItemKey = $itemData.first().data('cart-item-key');
            }
        }
        
        // console.log('[convertQuantityToDropdown] Cart item key: ' + cartItemKey);
        
        // Create dropdown select element
        var $select = $('<select>', {
            'class': 'quantity-dropdown wc-block-components-order-summary-item__quantity',
            'data-key': cartItemKey || '',
            'data-item-index': index
        });
        
        // Add options (1 to 20, or up to current quantity + 10)
        var maxQuantity = Math.max(currentQuantity + 10, 20);
        for (var i = 1; i <= maxQuantity; i++) {
            var $option = $('<option>', {
                value: i,
                text: i,
                selected: i === currentQuantity
            });
            $select.append($option);
        }
        
        // Preserve any existing classes and attributes
        var existingClasses = $quantityElement.attr('class');
        if (existingClasses) {
            $select.addClass(existingClasses);
        }
        
        // Replace the span/element with dropdown
        $quantityElement.replaceWith($select);
        // console.log('[convertQuantityToDropdown] Quantity span replaced with dropdown');
        
        // Add change event handler
        $select.on('change', function() {
            var newQuantity = parseInt($(this).val()) || 1;
            // console.log('[convertQuantityToDropdown] Quantity changed to: ' + newQuantity);
            
            if (cartItemKey) {
                updateCartItemQuantity(cartItemKey, newQuantity);
            } else {
                console.warn('[convertQuantityToDropdown] No cart item key found, cannot update quantity');
                // Try to trigger WooCommerce's update anyway
                if (!blockCustomCheckoutJS) {
    $('body').trigger('update_checkout');
}


            }
        });
        
        return $select;
    }
    
    // Update cart item quantity via AJAX
    function updateCartItemQuantity(cartItemKey, quantity) {
        var _tp = getThemeCheckoutParams();
        const ajaxUrl = _tp.ajax_url ? _tp.ajax_url : '/wp-admin/admin-ajax.php';

        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            dataType: 'json',
            data: {
                action: 'update_checkout_item_quantity',
                cart_item_key: cartItemKey,
                quantity: newQuantity,
                nonce: _tp.update_order_review_nonce
            },
            success: function(response) {
                if (response && response.success) {
                   if (!blockCustomCheckoutJS) {
    $('body').trigger('update_checkout');
}

                }
            },
            error: function (xhr) {
                console.error('Quantity update failed:', xhr.responseText);
            }
        });
    }

    // Update order summary image for a cart item (theme .order-summary-item and block .wc-block-components-order-summary-item)
    function setOrderSummaryImageForCartItem(cartItemKey, imageUrl) {
        if (!cartItemKey || !imageUrl) return;
        var $row = jQuery('.quantity-btn[data-key="' + cartItemKey + '"]').closest('.order-summary-item, .wc-block-components-order-summary-item');
        if ($row.length) {
            $row.find('.order-item-image img, .wc-block-components-order-summary-item__image img').attr('src', imageUrl);
        }
    }

    jQuery(document).on('click', '.gift-card-image-option', function () {

        const imageUrl   = jQuery(this).data('image');
        const cartItemKey = jQuery(this).data('cart-item-key');

        // Update preview (#selected-gift-card-image)
        jQuery('#selected-gift-card-image')
            .attr('src', imageUrl)
            .show();

        jQuery('#no-image-placeholder').hide();

        // Show same image in order summary (order-item-image / wc-block-components-order-summary-item__image)
        setOrderSummaryImageForCartItem(cartItemKey, imageUrl);

        // Save image on cart item (card_design + session) so email and persistence use it
        var _tpImg = getThemeCheckoutParams();
        const ajaxUrl = _tpImg.ajax_url ? _tpImg.ajax_url : '/wp-admin/admin-ajax.php';

        jQuery.ajax({
            type: 'POST',
            url: ajaxUrl,
            dataType: 'json',
            data: {
                action: 'save_selected_gift_card_image',
                image: imageUrl,
                cart_item_key: cartItemKey
            },
            success: function () {
                setOrderSummaryImageForCartItem(cartItemKey, imageUrl);
            }
        });

    });


    // Move quantity selector to appear where wishlist button is, and wishlist button after quantity
    function moveQuantityAfterWishlist() {
        // console.log('[moveQuantityAfterWishlist] Function called');
        var orderItemsCount = $('.wc-block-components-order-summary-item').length;
        // console.log('[moveQuantityAfterWishlist] Found ' + orderItemsCount + ' order summary items');
        
        if (orderItemsCount === 0) {
            // console.warn('[moveQuantityAfterWishlist] No order summary items found!');
            return;
        }
        
        $('.wc-block-components-order-summary-item').each(function(index) {
            // console.log('[moveQuantityAfterWishlist] Processing order item #' + (index + 1));
            var $orderItem = $(this);
            var $wishlistButton = $orderItem.find('.custom-wishlist-button');
            
            // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Wishlist button found: ' + ($wishlistButton.length > 0));
            
            if ($wishlistButton.length > 0) {
                // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Wishlist button parent class: ' + $wishlistButton.parent().attr('class'));
                // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Wishlist button HTML:', $wishlistButton[0].outerHTML);
                
                // Find quantity selector - check multiple possible locations and class names
                var $quantitySelector = null;
                
                // First, try to find it inside the image wrapper (most likely location based on user's description)
                var $imageWrapper = $orderItem.find('.wc-block-components-order-summary-item__image');
                // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Image wrapper found: ' + ($imageWrapper.length > 0));
                
                if ($imageWrapper.length > 0) {
                    // Look for WooCommerce quantity class first, then other selectors
                    $quantitySelector = $imageWrapper.find('.wc-block-components-order-summary-item__quantity, .quantity-selector-wrapper, .wc-block-components-quantity-selector, [class*="quantity"]');
                    // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity in image wrapper: ' + ($quantitySelector.length > 0));
                    if ($quantitySelector.length > 0) {
                        // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity selector classes: ' + $quantitySelector.attr('class'));
                    }
                }
                
                // If not found in image wrapper, search the entire order item
                if (!$quantitySelector || $quantitySelector.length === 0) {
                    // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Searching entire order item for quantity...');
                    // Prioritize WooCommerce's quantity class
                    $quantitySelector = $orderItem.find('.wc-block-components-order-summary-item__quantity, .quantity-selector-wrapper, .wc-block-components-quantity-selector, [class*="quantity-selector"]');
                    // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity found in order item: ' + ($quantitySelector.length > 0));
                    
                    // Debug: Log all elements with "quantity" in class name
                    var allQuantityElements = $orderItem.find('[class*="quantity"]');
                    // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - All elements with "quantity" in class: ' + allQuantityElements.length);
                    allQuantityElements.each(function(i) {
                        // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity element #' + (i + 1) + ' classes: ' + $(this).attr('class'));
                    });
                }
                
                // If quantity selector exists
                if ($quantitySelector && $quantitySelector.length > 0) {
                    // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity selector found!');
                    // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity selector current parent: ' + $quantitySelector.parent().attr('class'));
                    // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity selector HTML:', $quantitySelector[0].outerHTML.substring(0, 200));
                    
                    // Convert span to dropdown if it's a span element (before moving)
                    if ($quantitySelector.is('span') || $quantitySelector.find('span').length > 0) {
                        // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Converting quantity span to dropdown...');
                        $quantitySelector = convertQuantityToDropdown($quantitySelector, $orderItem, index);
                    }
                    
                    // Get the parent container of wishlist button to ensure proper placement
                    var $wishlistParent = $wishlistButton.parent();
                    
                    // Check if quantity is already where wishlist is (before wishlist)
                    var isBeforeWishlist = false;
                    if ($wishlistButton.prev().is($quantitySelector)) {
                        isBeforeWishlist = true;
                    }
                    
                    // Move quantity selector to where wishlist button is (before wishlist button)
                    if (!isBeforeWishlist) {
                        // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Moving quantity selector before wishlist button...');
                        
                        // Detach the quantity selector from its current location
                        $quantitySelector.detach();
                        // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity selector detached');
                        
                        // Ensure the quantity selector has proper display style and override plugin CSS
                        $quantitySelector.css({
                            'display': 'inline-flex',
                            'vertical-align': 'middle',
                            'margin-right': '8px',
                            'position': 'relative',
                            'right': 'auto',
                            'top': 'auto',
                            'transform': 'none',
                            'float': 'none'
                        });
                        
                        // Insert it before the wishlist button in the same parent
                        $wishlistButton.before($quantitySelector);
                        // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity selector inserted before wishlist button');
                    } else {
                        // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity already positioned before wishlist, no move needed');
                    }
                } else {
                    // console.warn('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Quantity selector NOT FOUND!');
                    // console.log('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Order item HTML structure:', $orderItem[0].outerHTML.substring(0, 500));
                }
            } else {
                // console.warn('[moveQuantityAfterWishlist] Item #' + (index + 1) + ' - Wishlist button NOT FOUND!');
            }
        });
        
        // console.log('[moveQuantityAfterWishlist] Function completed');
    }

    // Add video placeholder div to order summary item images
    function addVideoPlaceholder() {
        // console.log('[addVideoPlaceholder] Function called');
        var orderItemsCount = $('.wc-block-components-order-summary-item').length;
        // console.log('[addVideoPlaceholder] Found ' + orderItemsCount + ' order summary items');
        
        if (orderItemsCount === 0) {
            // console.warn('[addVideoPlaceholder] No order summary items found!');
            return;
        }
        
        $('.wc-block-components-order-summary-item').each(function(index) {
            var $orderItem = $(this);
            var $imageWrapper = $orderItem.find('.wc-block-components-order-summary-item__image');
            
            if ($imageWrapper.length > 0) {
                // Check if video placeholder already exists
                var $existingMedia = $imageWrapper.find('.order-item-media');
                
                if ($existingMedia.length === 0) {
                    // console.log('[addVideoPlaceholder] Adding video placeholder to item #' + (index + 1));
                    
                    // Create the video placeholder HTML
                    var videoPlaceholderHTML = '<div class="order-item-media">' +
                        '<div class="video-placeholder">' +
                        '<div class="video-play-icon">' +
                        '<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                        '<path d="M17.6 27.8L26.94 20.8C27.48 20.4 27.48 19.6 26.94 19.2L17.6 12.2C16.94 11.7 16 12.18 16 13V27C16 27.82 16.94 28.3 17.6 27.8ZM20 0C8.96 0 0 8.96 0 20C0 31.04 8.96 40 20 40C31.04 40 40 31.04 40 20C40 8.96 31.04 0 20 0ZM20 36C11.18 36 4 28.82 4 20C4 11.18 11.18 4 20 4C28.82 4 36 11.18 36 20C36 28.82 28.82 36 20 36Z" fill="#1D2D35"></path>' +
                        '</svg>' +
                        '</div>' +
                        '</div>' +
                        '<div class="media-actions">' +
                        '<a href="#" class="view-video-link">View video</a>' +
                        '<div class="thank-you-text">Thank you!</div>' +
                        '</div>' +
                        '</div>';
                    
                    // Append the video placeholder to the image wrapper
                    $imageWrapper.append(videoPlaceholderHTML);
                    // console.log('[addVideoPlaceholder] Video placeholder added to item #' + (index + 1));
                } else {
                    // console.log('[addVideoPlaceholder] Video placeholder already exists in item #' + (index + 1));
                }
            } else {
                // console.warn('[addVideoPlaceholder] Image wrapper not found in item #' + (index + 1));
            }
        });
        
        // console.log('[addVideoPlaceholder] Function completed');
    }

    // Add wishlist button to WooCommerce Blocks order summary items
    function addWishlistButtons() {
        // Find all order summary items
        $('.wc-block-components-order-summary-item').each(function(index) {
            var $orderItem = $(this);
            var $productName = $orderItem.find('.wc-block-components-product-name');
            
            if ($productName.length > 0) {
                // Check if button already exists to avoid duplicates
                if ($orderItem.find('.custom-wishlist-button').length === 0) {
                    // Get product ID and variation ID
                    var productId = extractProductId($orderItem, index);
                    var variationId = null;
                    
                    // Try to get variation ID from cart items
                    var cartItems = getCartItemsData();
                    if (cartItems && cartItems.length > index && cartItems[index]) {
                        variationId = cartItems[index].variation_id || null;
                    }
                    
                    // Check if product is in wishlist
                    var isInWishlist = isProductInWishlist(productId, variationId);
                    
                    // Build button class - add 'fill' if in wishlist
                    var buttonClass = 'custom-wishlist-button';
                    if (isInWishlist) {
                        buttonClass += ' fill';
                    }
                    
                    // Create wishlist button wrapper to ensure proper positioning
                    var $wishlistBtn = $('<button>', {
                        type: 'button',
                        class: buttonClass,
                        'data-product-id': productId,
                        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>'
                    });
                    
                    // Wrap product name and button together if needed, or insert after
                    // Check if product name is already wrapped in a flex container
                    if ($productName.parent().hasClass('wc-block-components-product-name-wrapper')) {
                        $productName.parent().append($wishlistBtn);
                    } else {
                        // Insert button after product name
                        $productName.after($wishlistBtn);
                    }
                } else {
                    // Update existing button's product ID and wishlist status if needed
                    var $button = $orderItem.find('.custom-wishlist-button');
                    var productId = extractProductId($orderItem, index);
                    var variationId = null;
                    
                    // Try to get variation ID from cart items
                    var cartItems = getCartItemsData();
                    if (cartItems && cartItems.length > index && cartItems[index]) {
                        variationId = cartItems[index].variation_id || null;
                    }
                    
                    if (productId) {
                        $button.attr('data-product-id', productId);
                        
                        // Check and update wishlist status
                        var isInWishlist = isProductInWishlist(productId, variationId);
                        if (isInWishlist) {
                            $button.addClass('fill');
                        } else {
                            $button.removeClass('fill');
                        }
                    }
                }
            }
        });
        
        // After adding wishlist buttons, move quantity selectors and convert to dropdowns
        // console.log('[addWishlistButtons] Calling moveQuantityAfterWishlist()...');
        moveQuantityAfterWishlist();
        
        // Also convert any remaining quantity spans to dropdowns
        convertAllQuantitySpansToDropdowns();
    }
    
    // Convert all quantity spans to dropdowns in order summary
    function convertAllQuantitySpansToDropdowns() {
        // console.log('[convertAllQuantitySpansToDropdowns] Converting all quantity spans to dropdowns...');
        
        $('.wc-block-components-order-summary-item').each(function(index) {
            var $orderItem = $(this);
            
            // Find quantity spans that haven't been converted yet
            var $quantitySpan = $orderItem.find('.wc-block-components-order-summary-item__quantity:not(.quantity-dropdown):not(select)');
            
            if ($quantitySpan.length > 0 && ($quantitySpan.is('span') || $quantitySpan.find('span').length > 0)) {
                // console.log('[convertAllQuantitySpansToDropdowns] Found quantity span in item #' + (index + 1));
                convertQuantityToDropdown($quantitySpan, $orderItem, index);
            }
        });
    }
    
    // Hide product metadata description and update metadata with From email and Message
    function updateProductMetadata() {
        // Skip if we're using the custom checkout template (not WooCommerce Blocks)
        // The custom template already displays the message in .order-item-details, so we don't need to add it via JavaScript
        // Check for the custom template structure: .checkout-order-summary with .order-item-details
        var isCustomTemplate = $('.checkout-order-summary .order-item-details').length > 0;
        
        // Only run on WooCommerce Blocks checkout, not custom template
        if (isCustomTemplate) {
            // Custom template is being used, skip JavaScript metadata update to avoid duplication
            return;
        }
        
        $('.wc-block-components-order-summary-item').each(function(index) {
            var $orderItem = $(this);
            
            // Hide the description element
            var $description = $orderItem.find('.wc-block-components-product-metadata__description');
            if ($description.length > 0) {
                $description.hide();
            }
            
            // Get cart items data to find all gift card metadata
            var cartItems = getCartItemsData();
            var giftMessage = '';
            var senderEmail = '';
            var senderName = '';
            var recipientName = '';
            var recipientEmail = '';
            var mobileNumber = '';
            
            if (cartItems && cartItems.length > index && cartItems[index]) {
                giftMessage = cartItems[index].gift_message || '';
                senderEmail = cartItems[index].sender_email || '';
                senderName = cartItems[index].sender_name || '';
                recipientName = cartItems[index].recipient_name || '';
                recipientEmail = cartItems[index].recipient_email || '';
                mobileNumber = cartItems[index].mobile_number || '';
            }
            
            // If sender email is not available from cart items, try to get from WordPress user
            if (!senderEmail) {
                if (typeof wpApiSettings !== 'undefined' && wpApiSettings.user && wpApiSettings.user.email) {
                    senderEmail = wpApiSettings.user.email;
                }
            }
            
            // Try to extract data from WooCommerce Blocks product details if not in cart items
            var $productDetails = $orderItem.find('.wc-block-components-product-details');
            if ($productDetails.length > 0) {
                // Extract recipient name
                if (!recipientName) {
                    var $recipientDetail = $productDetails.find('.wc-block-components-product-details__recipient');
                    if ($recipientDetail.length > 0) {
                        recipientName = $recipientDetail.find('.wc-block-components-product-details__value').text().trim();
                    }
                }
                
                // Extract sender name
                if (!senderName) {
                    var $fromDetail = $productDetails.find('.wc-block-components-product-details__from');
                    if ($fromDetail.length > 0) {
                        senderName = $fromDetail.find('.wc-block-components-product-details__value').text().trim();
                    }
                }
                
                // Extract email
                if (!recipientEmail) {
                    var $emailDetail = $productDetails.find('.wc-block-components-product-details__email');
                    if ($emailDetail.length > 0) {
                        recipientEmail = $emailDetail.find('.wc-block-components-product-details__value').text().trim();
                    }
                }
                
                // Extract mobile number
                if (!mobileNumber) {
                    var $mobileDetail = $productDetails.find('.wc-block-components-product-details__mobile-number');
                    if ($mobileDetail.length > 0) {
                        mobileNumber = $mobileDetail.find('.wc-block-components-product-details__value').text().trim();
                    }
                }
                
                // Extract message
                if (!giftMessage) {
                    var $messageDetail = $productDetails.find('.wc-block-components-product-details__message');
                    if ($messageDetail.length > 0) {
                        giftMessage = $messageDetail.find('.wc-block-components-product-details__value').text().trim();
                    }
                }
            }
            
            // Find or create the metadata container
            var $metadata = $orderItem.find('.wc-block-components-product-metadata');
            
            if ($metadata.length > 0) {
                // Hide the description element
                var $existingDescription = $metadata.find('.wc-block-components-product-metadata__description');
                if ($existingDescription.length > 0) {
                    $existingDescription.hide();
                }
                
                // Hide the existing product details list (we'll recreate it in the correct order)
                if ($productDetails.length > 0) {
                    $productDetails.hide();
                }
                
                // Remove any existing custom metadata we added (to avoid duplicates on re-runs)
                $metadata.find('.custom-metadata-from, .custom-metadata-recipient, .custom-metadata-email, .custom-metadata-phone, .custom-metadata-message').remove();
                
                // Create a container for our reorganized metadata
                var $customMetadataContainer = $metadata.find('.custom-metadata-container');
                if ($customMetadataContainer.length === 0) {
                    $customMetadataContainer = $('<div>', {
                        'class': 'custom-metadata-container'
                    });
                    $metadata.append($customMetadataContainer);
                } else {
                    $customMetadataContainer.empty();
                }
                
                // 1. Add "From" (sender name or email)
                if (senderName && senderName.trim() !== '') {
                    var $fromField = $('<div>', {
                        'class': 'custom-metadata-from wc-block-components-product-metadata__item',
                        html: '<span class="metadata-label">From:</span> <span class="metadata-value">' + $('<div>').text(senderName).html() + '</span>'
                    });
                    $customMetadataContainer.append($fromField);
                } else if (senderEmail && senderEmail.trim() !== '') {
                    var $fromField = $('<div>', {
                        'class': 'custom-metadata-from wc-block-components-product-metadata__item',
                        html: '<span class="metadata-label">From:</span> <span class="metadata-value">' + $('<div>').text(senderEmail).html() + '</span>'
                    });
                    $customMetadataContainer.append($fromField);
                }
                
                // 2. Add "Recipient"
                if (recipientName && recipientName.trim() !== '') {
                    var $recipientField = $('<div>', {
                        'class': 'custom-metadata-recipient wc-block-components-product-metadata__item',
                        html: '<span class="metadata-label">Recipient:</span> <span class="metadata-value">' + $('<div>').text(recipientName).html() + '</span>'
                    });
                    $customMetadataContainer.append($recipientField);
                }
                
                // 3. Add "Email" and/or "Phone" (show both if both are available)
                // Show Email if available
                if (recipientEmail && recipientEmail.trim() !== '' && recipientEmail !== '-') {
                    var $emailField = $('<div>', {
                        'class': 'custom-metadata-email wc-block-components-product-metadata__item',
                        html: '<span class="metadata-label">Email:</span> <span class="metadata-value">' + $('<div>').text(recipientEmail).html() + '</span>'
                    });
                    $customMetadataContainer.append($emailField);
                }
                
                // Show Phone if available (can be shown alongside email)
                if (mobileNumber && mobileNumber.trim() !== '' && mobileNumber !== '-') {
                    var $phoneField = $('<div>', {
                        'class': 'custom-metadata-phone wc-block-components-product-metadata__item',
                        html: '<span class="metadata-label">Phone:</span> <span class="metadata-value">' + $('<div>').text(mobileNumber).html() + '</span>'
                    });
                    $customMetadataContainer.append($phoneField);
                }
                
                // 4. Add "Message"
                if (giftMessage && giftMessage.trim() !== '') {
                    var $messageField = $('<div>', {
                        'class': 'custom-metadata-message wc-block-components-product-metadata__item',
                        html: '<span class="metadata-label">Message:</span> <span class="metadata-value">' + $('<div>').text(giftMessage).html() + '</span>'
                    });
                    $customMetadataContainer.append($messageField);
                }
            }

            // Inject "Total price for X item: $Y" into .wc-block-components-order-summary-item__description (and hide original so it doesn't show twice)
            var $descriptionDiv = $orderItem.find('.wc-block-components-order-summary-item__description');
            var $screenReaderSpan = $orderItem.find('span.screen-reader-text');
            if ($descriptionDiv.length && $screenReaderSpan.length) {
                var totalPriceText = $screenReaderSpan.text().trim();
                if (totalPriceText.indexOf('Total price for') === 0) {
                    $descriptionDiv.find('.order-summary-item-total-price-text').remove();
                    var $totalPriceEl = $('<span>', {
                        'class': 'order-summary-item-total-price-text',
                        'aria-hidden': 'true',
                        text: totalPriceText
                    });
                    $descriptionDiv.append($totalPriceEl);
                    $screenReaderSpan.addClass('order-summary-total-price-screen-reader-hidden').css('display', 'none');
                }
            }
        });
    }

    // Remove WooCommerce default order summary title and coupon form
    function removeWooCommerceDefaultElements() {
        // Remove order summary title
        $('.wc-block-components-checkout-order-summary__title-text').remove();
        
        // Remove coupon form block
        $('.wp-block-woocommerce-checkout-order-summary-coupon-form-block').remove();
        $('.wc-block-components-totals-coupon').remove();
    }
    
    // Delivery cost is included in product price on single product page; do not show as separate line
    function addDeliveryToTotals() {
        $('.wc-block-components-totals-wrapper .custom-delivery-row').remove();
    }
    
    // Get delivery cost from product ACF fields or from cart fees
    var cachedDeliveryCost = null;
    var deliveryCostCacheTime = null;
    
    function getDeliveryCost(callback) {
        // First, try to get from WooCommerce cart fees (if already added as fee)
        var $feeRow = $('.wc-block-components-totals-wrapper .wc-block-components-totals-fee, [class*="fee"]');
        var deliveryCostFromFee = 0;
        
        // Look for "Delivery" fee in the totals
        $feeRow.each(function() {
            var $fee = $(this);
            var feeLabel = $fee.find('.wc-block-components-totals-item__label, [class*="label"]').text().trim();
            if (feeLabel.toLowerCase().indexOf('delivery') !== -1) {
                var feeText = $fee.find('.wc-block-components-formatted-money-amount, [class*="formatted-money"]').text();
                var feeMatch = feeText.match(/[\d,]+\.?\d*/);
                if (feeMatch) {
                    deliveryCostFromFee = parseFloat(feeMatch[0].replace(/,/g, ''));
                }
            }
        });
        
        // If found in fees, use that value
        if (deliveryCostFromFee > 0) {
            if (callback) callback(deliveryCostFromFee);
            return deliveryCostFromFee;
        }
        
        // Return cached value if available and recent (within 2 seconds)
        var now = new Date().getTime();
        if (cachedDeliveryCost !== null && deliveryCostCacheTime && (now - deliveryCostCacheTime) < 2000) {
            if (callback) callback(cachedDeliveryCost);
            return cachedDeliveryCost;
        }
        
        // Fetch delivery cost from server via AJAX
        var _tpDel = getThemeCheckoutParams();
        var ajaxUrl = _tpDel.ajax_url ? _tpDel.ajax_url : '/wp-admin/admin-ajax.php';
        
        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            dataType: 'json',
            data: {
                action: 'get_cart_delivery_cost'
            },
            success: function(response) {
                var deliveryCost = 0;
                if (response && response.success && response.data && response.data.delivery_cost !== undefined) {
                    deliveryCost = parseFloat(response.data.delivery_cost) || 0;
                }
                
                // Cache the result
                cachedDeliveryCost = deliveryCost;
                deliveryCostCacheTime = new Date().getTime();
                
                if (callback) callback(deliveryCost);
            },
            error: function() {
                // On error, return 0
                cachedDeliveryCost = 0;
                deliveryCostCacheTime = new Date().getTime();
                if (callback) callback(0);
            }
        });
        
        // Return cached value immediately if available (may be stale)
        return cachedDeliveryCost !== null ? cachedDeliveryCost : 0;
    }
    
    // Format delivery price to match WooCommerce format
    function formatDeliveryPrice(price) {
        // Try to get format from existing price elements
        var $existingPrice = $('.wc-block-components-formatted-money-amount').first();
        if ($existingPrice.length > 0) {
            var existingText = $existingPrice.text();
            // Extract the format pattern
            var priceMatch = existingText.match(/[\d,]+\.?\d*/);
            if (priceMatch) {
                var formatted = price.toFixed(2);
                // Add thousand separators if needed
                if (formatted.length > 6) {
                    formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                // Use same currency symbol as existing prices
                var symbol = existingText.replace(/[\d,.\s]/g, '').charAt(0) || '$';
                return symbol + formatted;
            }
        }
        
        // Default format
        return '$' + price.toFixed(2);
    }
    
    // Update delivery value if row already exists
    function updateDeliveryValue($deliveryRow) {
        getDeliveryCost(function(deliveryCost) {
            var formattedPrice = formatDeliveryPrice(deliveryCost);
            $deliveryRow.find('.wc-block-components-formatted-money-amount').text(formattedPrice);
        });
    }
    
    // Initialize from session-based cart data (no AJAX fetch)
    initCheckoutFromSessionData();
    addVideoPlaceholder();
    removeWooCommerceDefaultElements();
    addDeliveryToTotals();
    
    // Ensure Stripe payment elements are visible (override inline display:none)
    function ensureStripeElementsVisible() {
        // Remove display:none from inline styles
        $('#wc-stripe-express-checkout-element, #wc-stripe-payment-element, #stripe-payment-element').each(function() {
            var $el = $(this);
            var currentStyle = $el.attr('style') || '';
            if (currentStyle.indexOf('display:none') !== -1 || currentStyle.indexOf('display: none') !== -1) {
                var newStyle = currentStyle.replace(/display\s*:\s*none[^;]*;?/gi, '').trim();
                $el.attr('style', newStyle);
                $el.css({
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1'
                });
            }
        });
        
        // Ensure Stripe payment method option is visible
        $('#payment .payment_method_stripe, #payment li.payment_method_stripe').css({
            'display': 'block',
            'visibility': 'visible'
        });
    }
    

     // Move Apple Pay / Google Pay (Stripe Express Checkout) into the blue payment box so they sit inside it
    function moveStripeExpressIntoPaymentBox() {
        var $payment = $('#payment');
        var $express = $('#wc-stripe-express-checkout-element');
        var $separator = $('#wc-stripe-express-checkout-button-separator');
        if ($payment.length && $express.length && !$express.closest('#payment').length) {
            $express.prependTo($payment);
            $express.removeClass('gc-stripe-express-empty').show();
        }
        if ($payment.length && $separator.length && !$separator.closest('#payment').length) {
            $separator.prependTo($payment);
        }
    }
    moveStripeExpressIntoPaymentBox();
    setTimeout(moveStripeExpressIntoPaymentBox, 100);
    setTimeout(moveStripeExpressIntoPaymentBox, 600);
    setTimeout(moveStripeExpressIntoPaymentBox, 1600);
    $(document.body).on('updated_checkout', function() {
        setTimeout(moveStripeExpressIntoPaymentBox, 200);
    });
    
    // Run immediately and after Stripe loads
    ensureStripeElementsVisible();
    setTimeout(ensureStripeElementsVisible, 500);
    setTimeout(ensureStripeElementsVisible, 1500);
    
    // // Hide empty Stripe Express Checkout placeholder (Apple Pay/Google Pay inject here; if empty, hide so it doesn't look broken)
    // function hideEmptyStripeExpressElement() {
    //     var $el = $('#wc-stripe-express-checkout-element');
    //     if ($el.length) {
    //         var hasContent = $el.find('iframe, button, [class*="stripe"], [id*="stripe"]').length > 0;
    //         if (!hasContent) {
    //             $el.addClass('gc-stripe-express-empty').hide();
    //         } else {
    //             $el.removeClass('gc-stripe-express-empty').show();
    //         }
    //     }
    //     var $sep = $('#wc-stripe-express-checkout-button-separator');
    //     if ($sep.length && $('#wc-stripe-express-checkout-element').find('iframe, button').length === 0) {
    //         $sep.hide();
    //     }
    // }
    // setTimeout(hideEmptyStripeExpressElement, 800);
    // setTimeout(hideEmptyStripeExpressElement, 2500);
    
    // // Also run when checkout updates
    // $(document.body).on('updated_checkout', function() {
    //     setTimeout(ensureStripeElementsVisible, 300);
    //     setTimeout(hideEmptyStripeExpressElement, 500);
    // });

    // Re-run when checkout updates (e.g. fragment refresh)
    // $(document.body).on('updated_checkout updated_wc_div', function() {
    //     setTimeout(function() {
    //         initCheckoutFromSessionData();
    //         addVideoPlaceholder();
    //         removeWooCommerceDefaultElements();
    //         addDeliveryToTotals();
    //     }, 300);
    // });

    // Also use MutationObserver for more reliable detection of DOM changes
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            var shouldUpdate = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    $(mutation.addedNodes).each(function() {
                        if ($(this).find('.wc-block-components-order-summary-item').length > 0 ||
                            $(this).hasClass('wc-block-components-order-summary-item')) {
                            shouldUpdate = true;
                            return false;
                        }
                    });
                }
            });
            if (shouldUpdate) {
                setTimeout(function() {
                    addWishlistButtons();
                    moveQuantityAfterWishlist();
                    addVideoPlaceholder();
                    updateProductMetadata();
                    removeWooCommerceDefaultElements();
                    addDeliveryToTotals();
                }, 200);
            }
        });

        // Observe the order summary container
        var orderSummaryContainer = document.querySelector('.wc-block-components-sidebar, .wc-block-checkout__sidebar');
        if (orderSummaryContainer) {
            observer.observe(orderSummaryContainer, {
                childList: true,
                subtree: true
            });
        }
    }

    // Handle wishlist button click
    $(document).on('click', '.custom-wishlist-button', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var productId = $btn.attr('data-product-id');
        var variationId = null;
        
        // Get variation ID from the order item
        var $orderItem = $btn.closest('.wc-block-components-order-summary-item');
        if ($orderItem.length) {
            var index = $('.wc-block-components-order-summary-item').index($orderItem);
            
            // Try to get variation ID from cart items
            var cartItems = getCartItemsData();
            if (cartItems && cartItems.length > index && cartItems[index]) {
                variationId = cartItems[index].variation_id || null;
            }
            
            // If product ID not found, try to extract it
            if (!productId) {
                productId = extractProductId($orderItem, index);
                if (productId) {
                    $btn.attr('data-product-id', productId);
                }
            }
        }
        
        if (!productId) {
            // console.error('Product ID not found for wishlist button.');
            showCheckoutMessage('Unable to find product information. Please refresh the page and try again.', 'error');
            return;
        }
        
        // Determine which ID to send to server (prefer variation_id if available)
        var idToSend = (variationId && variationId > 0) ? variationId : productId;
        
        // Disable all buttons for this product during request
        $('.custom-wishlist-button').each(function() {
            var $button = $(this);
            var btnProductId = parseInt($button.attr('data-product-id')) || 0;
            var btnVariationId = null;
            
            // Get variation ID for this button
            var $btnOrderItem = $button.closest('.wc-block-components-order-summary-item');
            if ($btnOrderItem.length) {
                var btnIndex = $('.wc-block-components-order-summary-item').index($btnOrderItem);
                var cartItems = getCartItemsData();
                if (cartItems && cartItems.length > btnIndex && cartItems[btnIndex]) {
                    btnVariationId = cartItems[btnIndex].variation_id || null;
                }
            }
            
            var btnIdToSend = (btnVariationId && btnVariationId > 0) ? btnVariationId : btnProductId;
            
            // Disable if it's the same product
            if (btnIdToSend === idToSend || btnProductId === parseInt(productId)) {
                $button.prop('disabled', true);
            }
        });
        
        // Get AJAX URL
        var _tpWish = getThemeCheckoutParams();
        var ajaxUrl = _tpWish.ajax_url ? _tpWish.ajax_url : '/wp-admin/admin-ajax.php';
        
        // Make AJAX call to toggle wishlist
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'gc_toggle_wishlist',
                product_id: parseInt(idToSend)
            },
            success: function(response) {
                // Re-enable all buttons
                $('.custom-wishlist-button').prop('disabled', false);
                
                // Check if response is valid JSON (in case it's a string)
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch(e) {
                        // console.error('JSON Parse Error:', e);
                        showCheckoutMessage('An error occurred. Please try again.', 'error');
                        return;
                    }
                }
                
                if (response.success) {
                    var isInWishlist = response.data.is_in_wishlist;
                    
                    // Update wishlist array in memory
                    updateWishlistArray(idToSend, isInWishlist);
                    // Also update by product_id if it's a variation
                    if (variationId && variationId > 0 && idToSend === variationId) {
                        // The server handles variations, but we also update product_id state
                        updateWishlistArray(productId, isInWishlist);
                    }
                    
                    // Update ALL instances of this product across all cart items
                    updateAllProductInstances(productId, variationId, isInWishlist);
                    
                    // Optional: Show notification or feedback
                    // console.log(response.data.message);
                } else {
                    // Handle error response
                    var errorMessage = (response.data && response.data.message) 
                        ? response.data.message 
                        : 'An error occurred. Please try again.';
                    showCheckoutMessage(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                // Re-enable all buttons on error
                $('.custom-wishlist-button').prop('disabled', false);
                // console.error('AJAX Error:', status, error);
                showCheckoutMessage('An error occurred. Please try again.', 'error');
            }
        });
    });
});