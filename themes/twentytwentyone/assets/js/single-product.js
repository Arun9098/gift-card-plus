function autoSelectFirstAmountButton() {
	console.log('Auto selecting button...');

    var buttons = document.querySelectorAll('#price-selection-dynamic .amount-btn:not(.custom-amount-btn)');
    
    if (buttons.length === 1) {
        buttons[0].click();
    }
}

function formatPrice(value) {
    value = parseFloat(value);

    if (Number.isInteger(value)) {
        return value.toLocaleString('en-US'); // 10,000
    }

    return value
        .toFixed(2)                // 10000.50
        .replace(/\.00$/, '')      // remove .00 if needed
        .replace(/\B(?=(\d{3})+(?!\d))/g, ","); // add commas
}

var originalPriceHtml = '';
var isOriginalStored = false;

function storeOriginalPriceOnce() {
	var currentHtml = jQuery('.product-price-display').html();

    if (!currentHtml || currentHtml.includes('$0.00')) {
        return;
    }

    if (!isOriginalStored) {
        originalPriceHtml = currentHtml;
        isOriginalStored = true;
        console.log('✅ Stored REAL original:', originalPriceHtml);
    }
}

jQuery(document).ready(function ($) {


	jQuery(document).on('keypress', '.custom-amount-input-field', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			jQuery('.accordion-update-btn').trigger('click');
		}
	});
	
	// Message display functions - keep timeout ID so we can cancel when showing a new message
	var messageAutoHideTimeout = null;

	function showMessage(message, type) {
		type = type || 'error'; // 'error', 'success', 'info', 'warning'
		var $container = $('#product-message-container');
		var $content = $container.find('.product-message-content');
		var $text = $container.find('.product-message-text');

		// Cancel any existing auto-hide so the new message gets its full display time
		if (messageAutoHideTimeout) {
			clearTimeout(messageAutoHideTimeout);
			messageAutoHideTimeout = null;
		}

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
		messageAutoHideTimeout = setTimeout(function () {
			hideMessage();
			messageAutoHideTimeout = null;
		}, autoHideDelay);

		// Scroll to message
		$('html, body').animate({
			scrollTop: $container.offset().top - 100
		}, 500);
	}

	function hideMessage() {
		if (messageAutoHideTimeout) {
			clearTimeout(messageAutoHideTimeout);
			messageAutoHideTimeout = null;
		}
		$('#product-message-container').slideUp(300);
	}

	const container = document.querySelector("#price-selection-dynamic");
	const firstBtn = container?.querySelector(".amount-btn");

	if (firstBtn) {
		setTimeout(() => {
			firstBtn.dispatchEvent(new Event("click", { bubbles: true }));
		}, 500);
		console.log("Clicked properly");
	}


	function checkTransactionLimitsOnSelection(qty, amountPerUnit) {
		if (!singleProductData.transactionLimitEnabled) return true;
		var qtyNum = parseInt(qty, 10) || 1;
		var amountNum = parseFloat(amountPerUnit) || 0;
		var cartVal = parseFloat(singleProductData.cartValueForProduct) || 0;
		var cartQty = parseInt(singleProductData.cartQtyForProduct, 10) || 0;
		var valueLimit = parseFloat(singleProductData.totalValuePerTransaction) || 0;
		var qtyLimit = parseInt(singleProductData.quantityPerTransaction, 10) || 0;
		var newTotalValue = cartVal + (qtyNum * amountNum);
		var newTotalQty = cartQty + qtyNum;
		if (valueLimit > 0 && newTotalValue > valueLimit) {
			showMessage('Total value limit exceeded for this product. Your selection (Qty × Amount = $' + (qtyNum * amountNum).toFixed(2) + ') exceeds the limit of $' + valueLimit.toFixed(2) + ' per transaction.' + (cartVal > 0 ? ' You already have $' + cartVal.toFixed(2) + ' of this product in your cart.' : ''), 'error');
			return false;
		}
		if (qtyLimit > 0 && newTotalQty > qtyLimit) {
			showMessage('Quantity limit exceeded for this product. You have ' + cartQty + ' in cart; limit is ' + qtyLimit + ' per transaction.', 'error');
			return false;
		}
		return true;
	}

	// Check user-level transaction limit. Returns true if within limit or user not logged in; shows error and returns false otherwise.
	function checkUserTransactionLimit(qty, amountPerUnit) {
		// Skip if user is not logged in
		if (!singleProductData.isUserLoggedIn) {
			return true;
		}

		var qtyNum = parseInt(qty, 10) || 1;
		var amountNum = parseFloat(amountPerUnit) || 0;
		var itemTotal = qtyNum * amountNum;

		// AJAX call to validate user transaction limit
		var result = false;
		$.ajax({
			url: singleProductData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gc_validate_transaction_limit',
				product_id: singleProductData.productId,
				quantity: qtyNum,
				price: amountNum
			},
			async: false, // We need synchronous for validation
			success: function (response) {
				if (response.success) {
					result = true;
				} else {
					showMessage(response.data.message, 'error');
					result = false;
				}
			},
			error: function () {
				showMessage('Error validating transaction limit. Please try again.', 'error');
				result = false;
			}
		});

		return result;
	}

	// Offer details modal: open on offer link click, show details and terms & conditions
	(function () {
		var $modal = $('#product-offer-modal');
		var $title = $modal.find('.product-offer-modal-title');
		var $desc = $modal.find('.product-offer-modal-description');
		var $terms = $modal.find('.product-offer-modal-terms');
		$(document).on('click', '.product-offer-link', function (e) {
			e.preventDefault();
			var offerId = $(this).data('offer-id');
			var $source = $('#offer-content-' + offerId);
			if (!$source.length) return;
			$title.text($source.attr('data-offer-title') || '');
			$desc.html($source.find('.offer-detail-description').html() || '—');
			$terms.html($source.find('.offer-detail-terms').html() || '—');
			$modal.attr('aria-hidden', 'false').show();
			$modal.find('.product-offer-modal-content').focus();
		});
		function closeOfferModal() {
			$modal.attr('aria-hidden', 'true').hide();
		}
		$modal.find('.product-offer-modal-close, .product-offer-modal-overlay').on('click', closeOfferModal);
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $modal.is(':visible')) closeOfferModal();
		});
	})();
	function getNearestValidAmount(value, min, interval) {
		let remainder = (value - min) % interval;

		if (remainder === 0) return value;

		return value + (interval - remainder);
	}

	function generateNiceFixedValues(min, max, interval) {
		min = Math.round(min);
		max = Math.round(max);

		let values = [];

		if (min >= 5) {

			if (interval === 5 || interval === 1) {
				values = [min, 50, 100, 250, max];
			}

			if (interval === 2) {
				values = [min, 49, 99, 249, max];
			}

			values = values
				.filter(v => v >= min && v <= max)
				.filter((v, i, arr) => arr.indexOf(v) === i)
				.sort((a, b) => a - b);

			if (values.length < 5) {
				let step = (max - min) / 4;

				values = [min];

				for (let i = 1; i <= 3; i++) {
					let v = Math.round(min + (step * i));
					values.push(v);
				}

				values.push(max);
			}

			return values;
		}

		// fallback
		let step = (max - min) / 4;
		values = [min];

		for (let i = 1; i <= 3; i++) {
			values.push(Math.round(min + (step * i)));
		}

		values.push(max);

		return [...new Set(values)];
	}

	// Fetch price options dynamically (like manual order selected-product-container)
	function fetchAndRenderPriceOptions() {
		var $container = $('#price-selection-dynamic');
		if (!$container.length) return;
		$container.html('<span class="price-loading">Loading prices...</span>');
		$.ajax({
			url: singleProductData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'get_single_product_price_options',
				product_id: singleProductData.productId,
				nonce: singleProductData.priceOptionsNonce
			},
			success: function (response) {
				if (response.success && response.data) {
					var d = response.data;
					singleProductData.minPrice = parseFloat(d.min_price) || 0;
					singleProductData.maxPrice = parseFloat(d.max_price) || 0;
					singleProductData.priceIntervals = parseFloat(d.price_intervals) || 1;
					singleProductData.denominationType = d.denomination_type || 'fixed';
					if (d.discounted_price) {
						singleProductData.discountedPrice = parseFloat(d.discounted_price);
					}
					singleProductData.originalPrice = parseFloat(d.original_price) || 0;
					singleProductData.discountMultiplier = parseFloat(d.discount_multiplier) || 1;
					singleProductData.originalPriceForDisplay = (d.amount_options && d.amount_options[0] && d.amount_options[0].original_amount) ? parseFloat(d.amount_options[0].original_amount) : parseFloat(d.original_price) || 0;

					var html = '';
					if (d.amount_options && d.amount_options.length) {
						d.amount_options.forEach(function (opt) {
							var btnLabel = opt.label;
							var amt = parseFloat(opt.amount);
							var origAmt = opt.original_amount ? parseFloat(opt.original_amount) : 0;
							var btnHtml = '<button type="button" class="amount-btn" data-amount="' + amt + '"';
							if (d.discount_active && d.discounted_price) {
								btnHtml += ' data-discounted="' + amt + '"';
							}
							if (origAmt > 0) {
								btnHtml += ' data-original-amount="' + origAmt + '"';
							}
							if (d.discount_active) {
								btnHtml += ' data-discounted="' + amt + '"';
							}
							// Button label always shows the original (non-discounted) price;
							// the discounted price is only applied on click/add-to-cart via data-amount.
							var displayLabel = '$' + formatPrice(origAmt > 0 ? origAmt : parseFloat(opt.amount));
							btnHtml += ' data-product-id="' + (opt.product_id || d.product_id) + '"' +
							    ' data-sku="' + (opt.sku || d.product_sku) + '"' +
							    ' data-title="' + (opt.title || d.product_title).replace(/"/g, '&quot;') + '"' +
							    ' data-image="' + (opt.image || d.product_image) + '">' +
							    displayLabel + '</button>';
							html += btnHtml;
						});
						if (d.has_custom) {
							html += '<button type="button" class="amount-btn custom-amount-btn" data-amount="custom">Custom</button>';
						}
					} else {
						html = '<span class="price-unavailable">No price options available.</span>';
					}
					$container.html(html);



					// ── STEP BUTTONS ──
					if (d.denomination_type === 'variable' && d.amount_options && d.amount_options.length) {

						var rawMin = parseFloat(d.original_min_price || d.min_price);
						var rawMax = parseFloat(d.original_max_price || d.max_price);




						var ranges = generateNiceFixedValues(rawMin, rawMax, singleProductData.priceIntervals);

						// remove duplicates if any
						ranges = [...new Set(ranges)];

						var newHtml = '';

						ranges.forEach(function (finalAmt) {

							let discountedAmt = finalAmt;

							if (d.discount_active && d.original_min_price) {

								let ratio = d.min_price / d.original_min_price;

								if (finalAmt === rawMin) {
									// ✅ FIX FOR MIN
									discountedAmt = d.min_price;
								} else {
									discountedAmt = finalAmt * ratio;
								}
							}

							let validAmt = getNearestValidAmount(finalAmt, rawMin, singleProductData.priceIntervals);
							let validDiscountedAmt = validAmt;

							if (d.discount_active && d.original_min_price) {
								let ratio = d.min_price / d.original_min_price;
								validDiscountedAmt = validAmt * ratio;
							}


							newHtml += '<button type="button" class="amount-btn"' +
								' data-amount="' + validAmt + '"' +  
								' data-display="' + finalAmt + '"' + 
								' data-discounted="' + validDiscountedAmt + '"' +
								' data-product-id="' + d.product_id + '"' +
								' data-sku="' + d.product_sku + '"' +
								' data-title="' + (d.product_title || '').replace(/"/g, '&quot;') + '"' +
								' data-image="' + (d.product_image || '') + '">' +
								'$' + formatPrice(finalAmt)      
								'</button>';
						});

						if (d.has_custom) {
							newHtml += '<button type="button" class="amount-btn custom-amount-btn" data-amount="custom">Custom</button>';
						}

						$container.html(newHtml);
					}
					// ── END STEP BUTTONS ──

					// Sync discount/original range from server
					if (d.discount_active !== undefined) {
						singleProductData.discountActive = d.discount_active;
						$button = jQuery('.amount-btn');
						amount = $button.data('discounted');
					}
					if (d.original_min_price != null) {
						singleProductData.originalMinPrice = parseFloat(d.original_min_price);
					}
					if (d.original_max_price != null) {
						singleProductData.originalMaxPrice = parseFloat(d.original_max_price);
					}

					// ── PRICE DISPLAY (separate from button generation) ──
					var priceDisplay = '$0.00';

					if (d.discount_active && d.denomination_type === 'variable' && d.original_min_price != null && d.original_max_price != null) {

						var origMin = parseFloat(d.original_min_price);
						var origMax = parseFloat(d.original_max_price);

						var discMin = parseFloat(d.min_price);

						// ✅ correct ratio
						var ratio = discMin / origMin;

						// ✅ calculate properly
						var discMax = origMax * ratio;

						// 2 decimal formatting
						var roundedOrigMin = origMin.toFixed(2);
						var roundedOrigMax = origMax.toFixed(2);

						var roundedDiscMin = discMin.toFixed(2);
						var roundedDiscMax = discMax.toFixed(2);

						var strikeHTML = '$' + roundedOrigMin + ' – $' + roundedOrigMax;
						var discountHTML = '$' + roundedDiscMin + ' – $' + roundedDiscMax;

						priceDisplay = '<span class="discounted-price-pink 1">' + discountHTML + '</span>';
						priceDisplay += '<span class="price-strikethrough">' + strikeHTML + '</span> ';

					} else if (d.discount_active && d.discounted_price && d.amount_options && d.amount_options.length && d.denomination_type !== 'variable') {

						// Fixed + discount
						var opt0 = d.amount_options[0];

						var saleAmt = parseFloat(opt0.amount).toFixed(2);

						var orig = opt0.original_amount || d.original_price;
						orig = orig ? parseFloat(orig).toFixed(2) : '0.00';

						priceDisplay = '<span class="discounted-price-pink 2">$' + saleAmt + '</span>';

						if (parseFloat(orig) > 0) {
							priceDisplay += ' <span class="price-strikethrough">$' + orig + '</span>';
						}

					} else if (d.discounted_price && d.amount_options && d.amount_options.length) {

						var opt0 = d.amount_options[0];

						var saleAmt = parseFloat(opt0.amount).toFixed(2);

						var orig = opt0.original_amount || d.original_price;
						orig = orig ? parseFloat(orig).toFixed(2) : '0.00';

						priceDisplay = '<span class="discounted-price-pink 3">$' + saleAmt + '</span>';

						if (parseFloat(orig) > 0) {
							priceDisplay += ' <span class="price-strikethrough">$' + orig + '</span>';
						}

					} else if (d.denomination_type === 'variable') {

						// Variable no discount
						var dispMin = parseFloat(d.original_min_price || d.min_price).toFixed(2);
						var dispMax = parseFloat(d.original_max_price || d.max_price).toFixed(2);

						priceDisplay = '$' + dispMin + ' – $' + dispMax;

					} else if (d.amount_options && d.amount_options.length) {

						var opt = d.amount_options[0];

						var amt = parseFloat(opt.amount).toFixed(2);

						var origOpt = opt.original_amount
							? parseFloat(opt.original_amount).toFixed(2)
							: '0.00';

						priceDisplay = '$' + amt;

						if (parseFloat(origOpt) > 0) {
							priceDisplay += ' <span class="price-strikethrough 4">$' + origOpt + '</span>';
						}
					}

					$('#dynamic-price-display').html(priceDisplay);

					storeOriginalPriceOnce();

					autoSelectFirstAmountButton();
					// Custom amount input range
					var customMin = (d.denomination_type === 'variable' && d.original_min_price != null) ? parseFloat(d.original_min_price) : parseFloat(d.min_price);
					var customMax = (d.denomination_type === 'variable' && d.original_max_price != null) ? parseFloat(d.original_max_price) : parseFloat(d.max_price);
					$('.custom-amount-input-field').attr('min', customMin).attr('max', customMax).attr('step', d.price_intervals).attr('placeholder', 'Enter Amount ($' + formatPrice(customMin) + ' - $' + formatPrice(customMax) + ')');

				} else {
					$container.html('<span class="price-error">Unable to load prices. Please refresh the page.</span>');
				}
			},
			error: function () {
				$container.html('<span class="price-error">Unable to load prices. Please refresh the page.</span>');
			}
		});
	}

	// Close button handler
	$(document).on('click', '.product-message-close', function () {
		hideMessage();
	});

	// Fetch price options on load (dynamic like manual order selected-product-container)
	fetchAndRenderPriceOptions();

	// Thumbnail image switching
	$('.custom-product-thumbnail').on('click', function () {
		var imageUrl = $(this).data('image');
		if (imageUrl) {
			$('#main-product-image').attr('src', imageUrl);
			$('.custom-product-thumbnail').removeClass('active');
			$(this).addClass('active');
		}
	});

	// Wishlist toggle
	$('.custom-wishlist-button[data-product-id]').on('click', function (e) {
		e.preventDefault();

		var $button = $(this);
		var productId = $button.data('product-id');
		var originalHtml = $button.html();

		$button.prop('disabled', true);

		$.ajax({
			url: singleProductData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gc_toggle_wishlist',
				product_id: productId
			},
			success: function (response) {
				if (response.success) {
					if (response.data.is_in_wishlist) {
						$button.addClass('fill');
						$button.find('span').text('Remove from wishlist');
						$button.attr('title', 'Remove from wishlist');
					} else {
						$button.removeClass('fill');
						$button.find('span').text('Add to wishlist');
						$button.attr('title', 'Add to wishlist');
					}
				} else {
					showMessage(response.data.message || 'An error occurred', 'error');
				}
				$button.prop('disabled', false);
			},
			error: function () {
				showMessage('An error occurred. Please try again.', 'error');
				$button.prop('disabled', false);
			}
		});
	});

	// Accordion Toggle
	$('.custom-accordion-header').on('click', function () {
		var $item = $(this).closest('.custom-accordion-item');
		var $content = $item.find('.custom-accordion-content');
		var accordionTitle = $item.find('.custom-accordion-title').text().toLowerCase();

		// // When logged in + "Myself" selected: skip Step 3 (Delivery) - do not open the 3rd accordion (allow closing if already open)
		// if (accordionTitle.includes('delivery') && !$item.hasClass('active') && singleProductData.isUserLoggedIn) {
		// 	var recipientType = $('input[name="recipient_type"]:checked').val();
		// 	if (recipientType === 'myself') {
		// 		return;
		// 	}
		// }

		// Close all other accordions
		$('.custom-accordion-item').not($item).removeClass('active');
		$('.custom-accordion-item').not($item).find('.custom-accordion-content').css('max-height', '0');

		// Toggle current accordion
		if ($item.hasClass('active')) {
			$item.removeClass('active');
			$content.css('max-height', '0');
		} else {
			$item.addClass('active');
			// Set a very large max-height to accommodate all content dynamically
			// This prevents content from being cut off when fields are shown/hidden
			var scrollHeight = $content[0].scrollHeight;
			$content.css('max-height', Math.max(scrollHeight, 5000) + 'px');

			// Initialize delivery fields when delivery accordion opens
			if (accordionTitle.includes('delivery')) {
				setTimeout(function () {
					var initialMethod = $('input[name="delivery_method"]:checked').val();
					if (initialMethod) {
						$('input[name="delivery_method"]').filter('[value="' + initialMethod + '"]').trigger('change');
					} else {
						// If no method selected, check if "myself" is selected and auto-populate
						var recipientType = $('input[name="recipient_type"]:checked').val();
						if (recipientType === 'myself' && singleProductData.isUserLoggedIn) {
							// Auto-populate delivery email if email field is visible
							if ($('#delivery-email-field').is(':visible') && !$('#delivery_email').val()) {
								$('#delivery_email').val(singleProductData.currentUserEmail);
							}
							// Auto-populate delivery phone if phone field is visible
							if ($('#delivery-phone-field').is(':visible') && singleProductData.currentUserPhone && !$('#delivery_number').val()) {
								$('#delivery_number').val(singleProductData.currentUserPhone);
							}
						}
					}
				}, 100);
			}
		}
	});

	// Quantity Controls
	$('.quantity-minus').on('click', function () {
		var $input = $(this).siblings('.quantity-input');
		var currentVal = parseInt($input.val()) || 1;
		if (currentVal > 1) {
			$input.val(currentVal - 1);
		}
	});

	$('.quantity-plus').on('click', function () {
		var $input = $(this).siblings('.quantity-input');
		var currentVal = parseInt($input.val()) || 1;
		$input.val(currentVal + 1);
	});

	// Helper: Get SMS delivery cost to add when delivery method is sms or email_sms
	function getSmsDeliveryCostToAdd() {
		var method = $('input[name="delivery_method"]:checked').val();
		if (method === 'sms' || method === 'email_sms') {
			return parseFloat(singleProductData.smsDeliveryCost) || 1;
		}
		return 0;
	}



	// Helper: Update accordion 2 & 3 price displays (personalisation + delivery)
	function updateAccordion2And3PriceDisplays(unitPrice, originalPerUnit, quantity) {
		if (!unitPrice || unitPrice <= 0) return;
		quantity = quantity || 1;
		var totalPrice = unitPrice * quantity;
		var deliveryCostToAdd = getSmsDeliveryCostToAdd() * quantity;
		originalPerUnit = originalPerUnit || singleProductData.originalPriceForDisplay || singleProductData.originalPrice || 0;
		var originalTotal = originalPerUnit * quantity;
		// Only show strikethrough when product is actually discounted (Discounted selected and within valid date range)
		var showStrikethrough = singleProductData.discountActive && originalPerUnit > unitPrice;

		var priceHtml = '<span class="selected-price-amount">$' + totalPrice.toFixed(2) + '</span>';
		if (showStrikethrough) {
			priceHtml += ' <span class="price-strikethrough">$' + formatPrice(originalTotal) + '</span>';
		}
		$('#selected-price-display').html(priceHtml);
		$('#someone-else-price-wrapper').html(priceHtml);
		var deliveryTotalPrice = totalPrice + deliveryCostToAdd;
		var deliveryHtml = '<span class="delivery-discounted-price">$' + deliveryTotalPrice.toFixed(2) + '</span>';
		if (showStrikethrough) {
			deliveryHtml += ' <span class="price-strikethrough">$' + formatPrice(originalTotal) + '</span>';
		}
		$('#delivery-price-display').html(deliveryHtml);
	}

	// Sibling product navigation buttons
	$(document).on('click', '.sibling-nav-btn', function () {
		var href = $(this).data('href');
		if (href) window.location.href = href;
	});

	// Amount Button Click Handler (event delegation for dynamically loaded buttons)
	$(document).on('click', '.amount-btn', function () {
		var $button = $(this);
		if ($button.hasClass('sibling-nav-btn')) return;
		var rawAmount = $button.data('amount');
		var amount;
		if (rawAmount === 'custom') {
			$('.amount-btn').removeClass('active selected');
			$button.addClass('active selected');
			$('.custom-amount-input-wrapper').slideDown();
			// Optionally reset main price display to default when custom is selected
			resetMainPriceDisplayToDefault();
			return;
		}
		var amount = parseFloat(
			$button.data('discounted') ?? $button.data('amount')
		);

		if ($button.hasClass('active')) {
			$button.removeClass('active selected');
			$('.custom-amount-input-wrapper').slideUp();
			resetMainPriceDisplayToDefault();
			return;
		}

		$('.amount-btn').removeClass('active selected');

		var qty = parseInt($('#gift_card_quantity').val()) || 1;
		var unitPrice = parseFloat(amount);

		// ✅ always take original from rawAmount
		var originalAttr = $button.data('original-amount');

		var displayOrig = (originalAttr !== undefined && originalAttr !== '')
			? parseFloat(originalAttr)
			: parseFloat(rawAmount);

		// 👉 SAFETY (recommended)
		if (isNaN(unitPrice) || isNaN(displayOrig)) {
			console.error('Invalid price', { unitPrice, displayOrig });
			return;
		}

		if (!checkTransactionLimitsOnSelection(qty, unitPrice)) {
			$('.amount-btn').removeClass('active selected');
			return;
		}

		$button.addClass('active selected');
		$('.custom-amount-input-wrapper').slideUp();

		updateAccordion2And3PriceDisplays(unitPrice, displayOrig, qty);


		updateMainPriceDisplay(unitPrice, displayOrig);

	});

	function updateMainPriceDisplay(salePrice, originalPrice) {
		var $priceDisplay = $('.product-price-display');

		if (!$priceDisplay.length) return;

		var showStrikethrough = singleProductData.discountActive && originalPrice && originalPrice > salePrice;

		// Force 2 decimal places
		var formattedSale = '$' + parseFloat(salePrice).toFixed(2);

		var html = 'Price: <span class="discounted-price-pink 6">' + formattedSale + '</span>';

		if (showStrikethrough) {
			var formattedOrig = '$' + parseFloat(originalPrice).toFixed(2);
			html += ' <span class="price-strikethrough">' + formattedOrig + '</span>';
		}

		$priceDisplay.html(html);
	}

	/**
	 * Resets the main price display to the default value (as loaded on page init)
	 * This is called when a selected button is deselected or custom mode is entered.
	 */
	function resetMainPriceDisplayToDefault() {
		var $priceDisplay = $('.product-price-display');
		if (!$priceDisplay.length) return;

		if (originalPriceHtml) {
			$priceDisplay.html(originalPriceHtml);
		} else {
			console.warn('Default price not captured');
		}
	}



	// Price Selection - Fixed (Dropdown) - Keep for backward compatibility
	// Note: This handler doesn't add to cart - Update button handles that
	$('.gift-card-price-select').on('change', function () {
		var selectedValue = $(this).val();
		if (selectedValue === 'custom') {
			$('.custom-amount-input-wrapper').slideDown();
		} else {
			$('.custom-amount-input-wrapper').slideUp();
			// Don't add to cart here - wait for Update button
		}
	});

	// Add Custom Amount - just validate, don't add to cart yet
	// $('.btn-add-custom-amount').on('click', function () {
	// 	var $input = $('.custom-amount-input-field');
	// 	var $error = $('.custom-amount-error');
	// 	var customAmount = parseFloat($input.val());
	// 	var $customButton = $('.amount-btn.custom-amount-btn');

	// 	// Hide any previous errors
	// 	$error.hide().text('');

	// 	if (!customAmount || customAmount <= 0) {
	// 		$error.text('Please enter a valid amount').fadeIn(300);
	// 		return;
	// 	}

	// 	// Validate against min/max price
	// 	if (customAmount < singleProductData.minPrice || customAmount > singleProductData.maxPrice) {
	// 		$error.text('Amount must be between $' + singleProductData.minPrice.toFixed(2) + ' and $' + singleProductData.maxPrice.toFixed(2)).fadeIn(300);
	// 		return;
	// 	}

	// 	// Validate against intervals (only for variable type)
	// 	if (singleProductData.denominationType === 'variable' && singleProductData.priceIntervals > 0) {
	// 		var remainder = (customAmount - singleProductData.minPrice) % singleProductData.priceIntervals;
	// 		// Allow small floating point differences
	// 		if (remainder > 0.01 && Math.abs(remainder - singleProductData.priceIntervals) > 0.01) {
	// 			$error.text('Amount must be in increments of $' + singleProductData.priceIntervals.toFixed(2) + '. Please enter a valid amount.').fadeIn(300);
	// 			return;
	// 		}
	// 	}

	// 	// Validation passed - mark custom button as selected
	// 	// The amount will be added to cart when Update button is clicked
	// 	$error.hide().text('');
	// 	// Update accordion 2 & 3 price displays (custom amount has no original/strikethrough)
	// 	var qty = parseInt($('#gift_card_quantity').val()) || 1;
	// 	updateAccordion2And3PriceDisplays(customAmount, null, qty);
	// });

	// Clear error when user starts typing
	$('.custom-amount-input-field').on('input', function () {
		$('.custom-amount-error').hide().text('');
	});

	// When custom amount is entered, check transaction limits on blur
	$('.custom-amount-input-field').on('blur', function () {
		var customAmount = parseFloat($(this).val());
		if (!customAmount || customAmount <= 0) return;
		var qty = parseInt($('#gift_card_quantity').val(), 10) || 1;
		checkTransactionLimitsOnSelection(qty, customAmount);
	});

	// Price Selection - Variable (Input)
	// Note: This handler doesn't add to cart - Update button handles that
	$('.gift-card-price-input').on('blur', function () {
		var price = parseFloat($(this).val());
		if (price && price > 0) {
			// Don't add to cart here - wait for Update button
			// Just validate the input if needed
		}
	});

	// Single gift card selection (only one can be selected)
	var selectedGiftCard = null;
	var tempGiftCardSelection = null;


	// Function to add product to WooCommerce cart
	function addToWooCommerceCart(giftCardData, callback, shouldUpdate) {
		// Prepare cart item data with gift card meta
		var cartItemData = {
			action: 'gc_add_to_cart',
			product_id: giftCardData.product_id,
			quantity: giftCardData.quantity,
			gift_card_price: giftCardData.price,
			recipient_name: giftCardData.recipient_name,
			recipient_email: giftCardData.recipient_email,
			mobile_number: giftCardData.mobile_number,
			delivery_method: giftCardData.delivery_method || '',
			delivery_timing: giftCardData.delivery_timing || '',
			sender_name: giftCardData.sender_name || '',
			gift_message: giftCardData.gift_message || '',
			card_design: giftCardData.card_design || ''
		};


		// Include schedule date/time if scheduling is selected
		if (giftCardData.schedule_date) {
			cartItemData.schedule_date = giftCardData.schedule_date;
		}
		if (giftCardData.schedule_time) {
			cartItemData.schedule_time = giftCardData.schedule_time;
		}
		if (giftCardData.schedule_timezone) {
			cartItemData.schedule_timezone = giftCardData.schedule_timezone;
		}
		if (giftCardData.schedule_datetime) {
			cartItemData.schedule_datetime = giftCardData.schedule_datetime;
		}

		// Also include delivery_email if available
		if (giftCardData.delivery_email) {
			cartItemData.delivery_email = giftCardData.delivery_email;
		}

		// Only remove previous item if explicitly updating (shouldUpdate === true)
		// AND the giftCardData has a cart_item_key (meaning it's an update of existing item)
		// Otherwise, always add as a new cart item
		if (shouldUpdate === true && giftCardData.cart_item_key) {
			// Update existing item - remove old and add new
			removeFromWooCommerceCart(giftCardData.cart_item_key, function () {
				// After removal, add new item
				performAddToCart(cartItemData, callback);
			});
		} else {
			// Add as new item - don't remove anything
			// This includes when shouldUpdate is false, undefined, or when cart_item_key is null
			performAddToCart(cartItemData, callback);
		}
	}

	// Function to perform the actual add to cart AJAX call
	function performAddToCart(cartItemData, callback) {
		$.ajax({
			type: 'POST',
			url: singleProductData.ajaxUrl,
			data: cartItemData,
			success: function (response) {
				if (response && response.success) {
					// Get cart item key from response
					var cartItemKey = response.data && response.data.cart_item_key ? response.data.cart_item_key : null;

					if (callback) callback(cartItemKey);

					// Trigger cart update event if fragments are available
					if (response.data && response.data.fragments) {
						$(document.body).trigger('added_to_cart', [response.data.fragments, response.data.cart_hash, $('body')]);
					} else {
						// Fallback: trigger update cart fragments event
						$(document.body).trigger('wc_fragment_refresh');
					}
				} else {
					console.error('Error adding to cart:', response);
					showMessage((response && response.data && response.data.message) ? response.data.message : 'Failed to add to cart.', 'error');
					if (callback) callback(null);
				}
			},
			error: function (xhr, status, error) {
				console.error('AJAX error adding to cart:', error);
				var errMsg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'An error occurred while adding to cart. Please try again.';
				showMessage(errMsg, 'error');
				if (callback) callback(null);
			},
			dataType: 'json'
		});
	}

	// Function to remove item from cart
	function removeFromWooCommerceCart(cartItemKey, callback) {
		// Use WooCommerce AJAX if available, otherwise use custom handler
		var wcAjaxUrl = typeof wc_add_to_cart_params !== 'undefined'
			? wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_from_cart')
			: null;

		if (wcAjaxUrl) {
			$.ajax({
				type: 'POST',
				url: wcAjaxUrl,
				data: {
					cart_item_key: cartItemKey
				},
				success: function (response) {
					if (response && response.fragments) {
						$(document.body).trigger('removed_from_cart', [response.fragments, response.cart_hash, $('body')]);
					}
					if (callback) callback();
				},
				error: function () {
					if (callback) callback();
				},
				dataType: 'json'
			});
		} else {
			// Fallback: just call callback
			if (callback) callback();
		}
	}

	// Function to find cart item key by product ID
	function findCartItemKey(productId, callback) {
		// Get cart contents via AJAX
		$.ajax({
			type: 'POST',
			url: singleProductData.ajaxUrl,
			data: {
				action: 'get_cart_contents'
			},
			success: function (response) {
				if (response && response.success && response.data && response.data.cart_contents) {
					// Find cart item with matching product_id
					for (var key in response.data.cart_contents) {
						if (response.data.cart_contents[key].product_id == productId) {
							if (callback) callback(key);
							return;
						}
					}
				}
				if (callback) callback(null);
			},
			error: function () {
				if (callback) callback(null);
			},
			dataType: 'json'
		});
	}

	// Function to update cart item (used when updating fields of an already-added product)
	function updateWooCommerceCart(giftCardData, callback) {
		if (!selectedGiftCard || !selectedGiftCard.cart_item_key) {
			// If no cart item exists, add it as new
			addToWooCommerceCart(giftCardData, callback, false);
			return;
		}

		// Update existing item - remove old and add new with updated data
		// Pass shouldUpdate = true to indicate we're updating, not adding new
		addToWooCommerceCart(giftCardData, callback, true);
	}

	function addGiftCardToList($element, customPrice) {
		var productId = $element.data('product-id') || singleProductData.productId;
		var sku = $element.data('sku') || singleProductData.productSku;
		var title = $element.data('title') || singleProductData.productTitle;
		var image = $element.data('image') || singleProductData.productImageUrl;
		var price = customPrice || parseFloat($element.val()) || parseFloat($('.gift-card-price-input').val());

		if (!price || price <= 0) {
			return;
		}

		// Get quantity from quantity select dropdown
		var quantity = parseInt($('#gift_card_quantity').val()) || parseInt($('.quantity-select').val()) || parseInt($('.quantity-input').val()) || 1;

		// Get recipient details from personalization fields
		var recipientName = $('#recipient_name').val() || '-';
		var recipientEmail = $('#recipient_email').val() || $('#delivery_email').val() || '-';
		var mobileNumber = $('#recipient_phone').val() || $('#delivery_mobile').val() || '-';
		var deliveryMethod = $('input[name="delivery_method"]:checked').val() || 'email';
		var deliveryTiming = $('input[name="delivery_timing"]:checked').val() || 'instant';
		var deliveryText = '';

		if (deliveryMethod === 'email') {
			deliveryText = 'Email';
		} else if (deliveryMethod === 'sms') {
			deliveryText = 'SMS';
		} else if (deliveryMethod === 'email_sms') {
			deliveryText = 'Email+SMS';
		}

		if (deliveryTiming === 'schedule') {
			var scheduleDate = $('#schedule_date').val();
			var scheduleTime = $('#schedule_time').val();
			var scheduleTimezone = $('#schedule_timezone').val();
			if (scheduleDate && scheduleTime) {
				deliveryText += ' - Schedule: ' + scheduleDate + ' ' + scheduleTime + ' ' + (scheduleTimezone || '');
			} else {
				deliveryText += ' - Schedule for later';
			}
		} else {
			deliveryText += ' - Send instantly';
		}

		// Store single selected gift card
		selectedGiftCard = {
			product_id: productId,
			sku: sku,
			title: title,
			image: image,
			price: price,
			quantity: quantity,
			recipient_name: recipientName,
			recipient_email: recipientEmail,
			mobile_number: mobileNumber,
			delivery: deliveryText,
			cart_item_key: selectedGiftCard ? selectedGiftCard.cart_item_key : null
		};

		// Add to WooCommerce cart
		// addToWooCommerceCart(selectedGiftCard, function(cartItemKey) {
		// 	if (cartItemKey) {
		// 		selectedGiftCard.cart_item_key = cartItemKey;
		// 	}
		// });

		// updateGiftCardDisplay();

		// Update personalization price display
		// updatePersonalizationPriceDisplay();


		tempGiftCardSelection = {
			product_id: productId,
			sku: sku,
			title: title,
			image: image,
			price: price,
			quantity: quantity,
			recipient_name: recipientName,
			recipient_email: recipientEmail,
			mobile_number: mobileNumber,
			delivery: deliveryText
		};

		// Reset quantity (but don't reset if user wants to add more)
		// Only reset price selection
		$('.gift-card-price-select').val('');
		$('.gift-card-price-input').val('');
		$('.amount-btn').removeClass('active');
	}

	// Function to update gift card details display
	function updateGiftCardDisplay() {
		var $section = $('.selected-gift-card-details-section');

		if (!selectedGiftCard) {
			$section.hide();
			$('.gift-card-selected-price').text('');
			return;
		}

		// Update details
		$('#selected-recipient-name').text(selectedGiftCard.recipient_name);
		$('#selected-mobile-number').text(selectedGiftCard.mobile_number);
		$('#selected-email').text(selectedGiftCard.recipient_email);
		$('#selected-delivery').text(selectedGiftCard.delivery);

		// Update title with selected price (price × quantity) + delivery cost when SMS/Email+SMS
		if (selectedGiftCard.price) {
			var qty = selectedGiftCard.quantity || 1;
			var totalPrice = selectedGiftCard.price * qty;
			var deliveryCostToAdd = getSmsDeliveryCostToAdd() * qty;
			totalPrice += deliveryCostToAdd;
			$('.gift-card-selected-price').text('$' + formatPrice(totalPrice));
		} else {
			$('.gift-card-selected-price').html('');
		}

		// Update image: when "Myself" show default product image; when "Someone else" show selected card design or product image
		var recipientType = $('input[name="recipient_type"]:checked').val();
		var displayImage = '';
		if (recipientType === 'myself') {
			displayImage = singleProductData.productImageUrl || selectedGiftCard.image || '';
		} else {
			if (selectedGiftCard.card_design) {
				displayImage = selectedGiftCard.card_design;
			} else if (selectedGiftCard.image) {
				displayImage = selectedGiftCard.image;
			}
		}
		if (displayImage) {
			$('#selected-gift-card-image').attr('src', displayImage).attr('alt', selectedGiftCard.title || 'Gift Card').show();
			$('#no-image-placeholder').hide();
		} else {
			$('#selected-gift-card-image').hide();
			$('#no-image-placeholder').show();
		}

		// Update price display (show total: price × quantity + delivery cost when SMS/Email+SMS)
		var quantity = selectedGiftCard.quantity || 1;
		var totalPrice = (selectedGiftCard.price || singleProductData.originalPrice) * quantity;
		var deliveryCostToAdd = getSmsDeliveryCostToAdd() * quantity;
		totalPrice += deliveryCostToAdd;
		var originalPerUnit = selectedGiftCard.original_amount || singleProductData.originalPriceForDisplay || singleProductData.originalPrice;
		var originalTotal = originalPerUnit * quantity;
		// Only show strikethrough when product is actually discounted (Discounted selected and within valid date range)
		var showStrikethrough = singleProductData.discountActive && originalPerUnit > (selectedGiftCard.price || 0);


		var priceHtml = '<span class="gift-card-discounted-price">$' + totalPrice.toFixed(2) + '</span>';
		if (showStrikethrough) {
			priceHtml += ' <span class="price-strikethrough">$' + formatPrice(originalTotal) + '</span>';
		}

		$('#gift-card-price-display').html(priceHtml);

		$section.slideDown();
	}

	// Remove gift card
	$(document).on('click', '#remove-selected-gift-card', function () {
		if (selectedGiftCard && selectedGiftCard.cart_item_key) {
			// Remove from cart
			removeFromWooCommerceCart(selectedGiftCard.cart_item_key, function () {
				selectedGiftCard = null;
				updateGiftCardDisplay();
				updatePersonalizationPriceDisplay();
				$('.gift-card-price-select').val('');
				$('.gift-card-price-input').val('');
				$('.amount-btn').removeClass('active');
			});
		} else {
			selectedGiftCard = null;
			updateGiftCardDisplay();
			updatePersonalizationPriceDisplay();
			$('.gift-card-price-select').val('');
			$('.gift-card-price-input').val('');
			$('.amount-btn').removeClass('active');
		}
	});

	// Gift card Preview button: show modal with all selected order details (works for both Myself and Someone else)
	$(document).on('click', '#gift-card-preview-btn', function () {
		var recipientType = $('input[name="recipient_type"]:checked').val();
		var sender, recipient, email, mobile;
		if (recipientType === 'myself' && singleProductData.isUserLoggedIn) {
			sender = '-';
			recipient = singleProductData.currentUserName || '-';
			email = ($('#delivery_email').val() || '').trim() || singleProductData.currentUserEmail || '-';
			mobile = ($('#delivery_number').val() || '').trim() || singleProductData.currentUserPhone || '-';
		} else {
			sender = ($('#sender_name').val() || '').trim() || '-';
			recipient = ($('#recipient_name').val() || '').trim() || '-';
			email = ($('#delivery_email').val() || '').trim() || '-';
			mobile = ($('#delivery_number').val() || '').trim() || '-';
		}
		// When "Myself" is selected, do not show "Someone else" details (gift message, card & media)
		var giftMsg = (recipientType === 'myself') ? '-' : (($('#gift_message').val() || '').trim() || '-');
		// Build delivery text from current form (works for any delivery option)
		var deliveryMethod = $('input[name="delivery_method"]:checked').val() || 'email';
		var deliveryTiming = $('input[name="delivery_timing"]:checked').val() || 'instant';
		var deliveryText = '';
		if (deliveryMethod === 'email') deliveryText = 'Email';
		else if (deliveryMethod === 'sms') deliveryText = 'SMS';
		else if (deliveryMethod === 'email_sms') deliveryText = 'Email+SMS';
		if (deliveryTiming === 'schedule') {
			var scheduleDate = $('#schedule_date').val() || '';
			var scheduleTime = $('#schedule_time').val() || '';
			var scheduleTz = $('#schedule_timezone').val() || '';
			if (scheduleDate || scheduleTime) {
				deliveryText += ' - Schedule: ' + scheduleDate + (scheduleTime ? ' ' + scheduleTime : '') + (scheduleTz ? ' ' + scheduleTz : '');
			} else {
				deliveryText += ' - Schedule for later';
			}
		} else {
			deliveryText += ' - Send instantly';
		}
		if (!deliveryText) deliveryText = '-';

		$('#preview-sender-name').text(sender);
		$('#preview-recipient-name').text(recipient);
		$('#preview-email').text(email);
		$('#preview-mobile-number').text(mobile);
		$('#preview-gift-message').text(giftMsg);
		$('#preview-delivery').text(deliveryText);

		// Schedule (only if "Schedule for later" is selected)
		var deliveryTiming = $('input[name="delivery_timing"]:checked').val();
		var scheduleDate = $('#schedule_date').val() || '';
		var scheduleTime = $('#schedule_time').val() || '';
		var scheduleTz = $('#schedule_timezone').val() || '';
		if (deliveryTiming === 'schedule' && (scheduleDate || scheduleTime)) {
			var scheduleText = scheduleDate + (scheduleTime ? ' ' + scheduleTime : '') + (scheduleTz ? ' ' + scheduleTz : '');
			$('#preview-schedule').text(scheduleText.trim());
			$('#preview-schedule-row').show();
		} else {
			$('#preview-schedule-row').hide();
		}

		// Card & media: when "Myself" is selected, do not show Someone else personalization (card design, animation, video, image)
		if (recipientType === 'myself') {
			$('#preview-modal-card-design').attr('src', '').hide();
			$('#preview-modal-card-design-none').text('-').show();
			$('#preview-modal-animation').attr('src', '').hide();
			$('#preview-modal-animation-none').text('-').show();
			$('#preview-modal-video-player').attr('src', '').hide();
			$('.gift-card-preview-video-fallback, #preview-modal-video').text('-').show();
			$('#preview-modal-image').attr('src', '').hide();
			$('#preview-modal-image-none').text('-').show();
		} else {
			// Someone else: show selected card design and media
			var cardDesignSrc = (selectedGiftCard && selectedGiftCard.card_design) ? selectedGiftCard.card_design : ($('#selected-gift-card-image').attr('src') || '');
			if (cardDesignSrc) {
				$('#preview-modal-card-design').attr('src', cardDesignSrc).show();
				$('#preview-modal-card-design-none').hide();
			} else {
				$('#preview-modal-card-design').attr('src', '').hide();
				$('#preview-modal-card-design-none').text('-').show();
			}
			var animSrc = (typeof mediaMessageData !== 'undefined' && mediaMessageData.animation) ? mediaMessageData.animation : ($('#preview-animation').length && $('#preview-animation').attr('src')) ? $('#preview-animation').attr('src') : '';
			if (animSrc) {
				$('#preview-modal-animation').attr('src', animSrc).show();
				$('#preview-modal-animation-none').hide();
			} else {
				$('#preview-modal-animation').attr('src', '').hide();
				$('#preview-modal-animation-none').text('-').show();
			}
			var videoVal = (typeof mediaMessageData !== 'undefined' && mediaMessageData.video) ? mediaMessageData.video : '';
			var $videoPlayer = $('#preview-modal-video-player');
			var $videoFallback = $('.gift-card-preview-video-fallback, #preview-modal-video');
			if (videoVal && $videoPlayer.length) {
				$videoPlayer.attr('src', videoVal).show();
				$videoFallback.hide();
				$videoPlayer[0].load();
			} else {
				$videoPlayer.attr('src', '').hide();
				$videoFallback.text('-').show();
			}
			var imgSrc = (typeof mediaMessageData !== 'undefined' && mediaMessageData.image) ? mediaMessageData.image : ($('#preview-image').length && $('#preview-image').attr('src')) ? $('#preview-image').attr('src') : '';
			if (imgSrc) {
				$('#preview-modal-image').attr('src', imgSrc).show();
				$('#preview-modal-image-none').hide();
			} else {
				$('#preview-modal-image').attr('src', '').hide();
				$('#preview-modal-image-none').text('-').show();
			}
		}

		$('#gift-card-preview-modal').css('display', 'flex');
		$('body').addClass('gift-card-preview-modal-open');
	});

	function closeGiftCardPreviewModal() {
		var $videoPlayer = $('#preview-modal-video-player');
		if ($videoPlayer.length) {
			$videoPlayer[0].pause();
			$videoPlayer.attr('src', '');
		}
		$('#gift-card-preview-modal').hide();
		$('body').removeClass('gift-card-preview-modal-open');
	}

	$(document).on('click', '.gift-card-preview-modal-backdrop, .gift-card-preview-modal-close', closeGiftCardPreviewModal);

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && $('#gift-card-preview-modal').is(':visible')) {
			closeGiftCardPreviewModal();
		}
	});

	
	// Note: This only updates the display, NOT the cart. Cart is updated only when "Buy now" button is clicked.
	$('#recipient_name, #recipient_email, #recipient_phone, #delivery_email, #delivery_number, input[name="delivery_method"], input[name="delivery_timing"], #schedule_date, #schedule_time, #schedule_timezone').on('change', function () {
		if (selectedGiftCard) {
			selectedGiftCard.recipient_name = $('#recipient_name').val() || '-';
			selectedGiftCard.recipient_email = $('#recipient_email').val() || $('#delivery_email').val() || '-';
			selectedGiftCard.mobile_number = $('#recipient_phone').val() || $('#delivery_number').val() || '-';

			var deliveryMethod = $('input[name="delivery_method"]:checked').val() || 'email';
			var deliveryTiming = $('input[name="delivery_timing"]:checked').val() || 'instant';
			var deliveryText = '';

			if (deliveryMethod === 'email') {
				deliveryText = 'Email';
			} else if (deliveryMethod === 'sms') {
				deliveryText = 'SMS';
			} else if (deliveryMethod === 'email_sms') {
				deliveryText = 'Email+SMS';
			}

			if (deliveryTiming === 'schedule') {
				var scheduleDate = $('#schedule_date').val();
				var scheduleTime = $('#schedule_time').val();
				var scheduleTimezone = $('#schedule_timezone').val();
				if (scheduleDate && scheduleTime) {
					deliveryText += ' - Schedule: ' + scheduleDate + ' ' + scheduleTime + ' ' + (scheduleTimezone || '');
				} else {
					deliveryText += ' - Schedule for later';
				}
			} else {
				deliveryText += ' - Send instantly';
			}

			selectedGiftCard.delivery = deliveryText;

			// Only update display, NOT the cart - cart will be updated when "Buy now" button is clicked
			// Removed automatic cart update: updateWooCommerceCart()

			updateGiftCardDisplay();
			updatePersonalizationPriceDisplay();
		}
	});

	// Quantity change handler - update display and re-check transaction limits
	$('#gift_card_quantity').on('change', function () {
		var $sel = $('.amount-btn.selected, .amount-btn.active').first();
		if (!$sel.length) return;
		var qty = parseInt($(this).val(), 10) || 1;
		var amount = $sel.data('amount');
		var unitPrice = 0;
		if (amount === 'custom') {
			unitPrice = parseFloat($('.custom-amount-input-field').val()) || 0;
		} else {
			unitPrice = parseFloat(amount) || 0;
			if (singleProductData.denominationType === 'variable' && singleProductData.discountActive && singleProductData.discountedPrice && singleProductData.originalMinPrice > 0) {
				unitPrice = Math.round(unitPrice * (singleProductData.discountedPrice / singleProductData.originalMinPrice) * 100) / 100;
			}
		}
		if (unitPrice > 0) checkTransactionLimitsOnSelection(qty, unitPrice);
	});

	// Update SMS delivery cost display in .del-cost span (visible when sms or email_sms selected)
	function updateDelCostDisplay() {
		var method = $('input[name="delivery_method"]:checked').val();
		var cost = (method === 'sms' || method === 'email_sms') ? (parseFloat(singleProductData.smsDeliveryCost) || 1) : 0;
		$('.del-cost').text(cost > 0 ? '$' + cost.toFixed(2) : '');
	}

	// Update delivery price display when gift card is selected (accordion 3)
	function updateDeliveryPriceDisplay() {
		var totalPrice, originalPerUnit, originalTotal, showStrikethrough, priceHtml;
		var qty = selectedGiftCard ? (selectedGiftCard.quantity || 1) : 1;
		var deliveryCostToAdd = getSmsDeliveryCostToAdd() * qty;
		if (selectedGiftCard && selectedGiftCard.price) {
			totalPrice = selectedGiftCard.price * (selectedGiftCard.quantity || 1);
			originalPerUnit = selectedGiftCard.original_amount || singleProductData.originalPriceForDisplay || singleProductData.originalPrice;
			originalTotal = originalPerUnit * (selectedGiftCard.quantity || 1);
			// Only show strikethrough when product is actually discounted (Discounted selected and within valid date range)
			showStrikethrough = singleProductData.discountActive && originalPerUnit > (selectedGiftCard.price || 0);
			totalPrice += deliveryCostToAdd;
			priceHtml = '<span class="delivery-discounted-price">$' + totalPrice.toFixed(2) + '</span>';
			if (showStrikethrough) {
				priceHtml += ' <span class="price-strikethrough">$' + formatPrice(originalTotal) + '</span>';
			}
		} else {
			var discountedPrice = singleProductData.discountedPrice;
			var originalPrice = singleProductData.originalPriceForDisplay || singleProductData.originalPrice;
			totalPrice = (discountedPrice || originalPrice || 0) * qty + deliveryCostToAdd;
			// Only show strikethrough when product is actually discounted (Discounted selected and within valid date range)
			showStrikethrough = singleProductData.discountActive && discountedPrice && originalPrice > discountedPrice;
			priceHtml = '<span class="delivery-discounted-price">$' + totalPrice.toFixed(2) + '</span>';
			if (showStrikethrough) {
				priceHtml += ' <span class="price-strikethrough">$' + formatPrice((originalPrice * qty)) + '</span>';
			}
		}
		$('#delivery-price-display').html(priceHtml);
	}

	// Update delivery price when gift card is added
	var originalUpdateGiftCardDisplay = updateGiftCardDisplay;
	updateGiftCardDisplay = function () {
		originalUpdateGiftCardDisplay();
		updateDeliveryPriceDisplay();
	};

	// Initialize delivery price display and SMS delivery cost
	updateDeliveryPriceDisplay();
	updateDelCostDisplay();

	// Delivery method change handler
	$('input[name="delivery_method"]').on('change', function () {
		var method = $(this).val();
		var recipientType = $('input[name="recipient_type"]:checked').val();
		// Update SMS delivery cost display, delivery price, and price-section
		updateDelCostDisplay();
		updateDeliveryPriceDisplay();
		if (selectedGiftCard) {
			updateGiftCardDisplay();
		}

		// Show/hide email and mobile fields based on selection
		if (method === 'email') {
			$('#delivery-email-field').show();
			$('#delivery-phone-field').hide();
			$('#delivery_email').prop('required', true);
			$('#delivery_number').prop('required', false);

			// Auto-populate email if "myself" is selected and field is empty
			if (recipientType === 'myself' && singleProductData.isUserLoggedIn && !$('#delivery_email').val()) {
				$('#delivery_email').val(singleProductData.currentUserEmail);
			}
		} else if (method === 'sms') {
			$('#delivery-email-field').hide();
			$('#delivery-phone-field').show();
			$('#delivery_email').prop('required', false);
			$('#delivery_number').prop('required', true);

			// Auto-populate phone if "myself" is selected and field is empty
			if (recipientType === 'myself' && singleProductData.isUserLoggedIn && singleProductData.currentUserPhone && !$('#delivery_number').val()) {
				$('#delivery_number').val(singleProductData.currentUserPhone);
			}
		} else if (method === 'email_sms') {
			$('#delivery-email-field').show();
			$('#delivery-phone-field').show();
			$('#delivery_email').prop('required', true);
			$('#delivery_number').prop('required', true);

			// Auto-populate both fields if "myself" is selected and fields are empty
			if (recipientType === 'myself' && singleProductData.isUserLoggedIn) {
				if (!$('#delivery_email').val()) {
					$('#delivery_email').val(singleProductData.currentUserEmail);
				}
				if (singleProductData.currentUserPhone && !$('#delivery_number').val()) {
					$('#delivery_number').val(singleProductData.currentUserPhone);
				}
			}
		}
	});

	// Delivery timing change handler
	$('input[name="delivery_timing"]').on('change', function () {
		if ($(this).val() === 'schedule') {
			$('.scheduled-delivery-fields').slideDown();
			$('#schedule_date').prop('required', true);
			$('#schedule_time').prop('required', true);
			$('#schedule_timezone').prop('required', true);
		} else {
			$('.scheduled-delivery-fields').slideUp();
			$('#schedule_date').prop('required', false);
			$('#schedule_time').prop('required', false);
			$('#schedule_timezone').prop('required', false);
		}
	});

	// Handle click on calendar icon to trigger date picker
	$(document).on('click', '.calendar-icon', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $dateInput = $('#schedule_date');
		if ($dateInput.length) {
			$dateInput[0].focus();
			if (typeof $dateInput[0].showPicker === 'function') {
				try {
					$dateInput[0].showPicker();
				} catch (err) {
					$dateInput[0].click();
				}
			} else {
				$dateInput[0].click();
			}
		}
		return false;
	});

	// Initialize delivery fields visibility
	var initialMethod = $('input[name="delivery_method"]:checked').val();
	if (initialMethod) {
		$('input[name="delivery_method"]').filter('[value="' + initialMethod + '"]').trigger('change');
	}

	// On page load: if "Myself" is already selected, disable the email field
	if ($('input[name="recipient_type"]:checked').val() === 'myself') {
		$('#delivery_email').prop('disabled', true).prop('readonly', true);
		$('#delivery_number').prop('disabled', true).prop('readonly', true);
	}

	// Auto-advance only for Delivery step completion (mark step 3 complete). No auto-advance from Personalise
	// so the accordion does not close when the user is typing recipient name.
	function isDeliveryStepComplete() {
		var method = $('input[name="delivery_method"]:checked').val();
		if (!method) return false;
		if (method === 'email') return ($('#delivery_email').val() || '').trim().length > 0;
		if (method === 'sms') return ($('#delivery_number').val() || '').trim().length > 0;
		if (method === 'email_sms') return ($('#delivery_email').val() || '').trim().length > 0 && ($('#delivery_number').val() || '').trim().length > 0;
		return false;
	}
	var autoAdvanceDeliveryTimer;
	$('#delivery_email, #delivery_number, input[name="delivery_method"]').on('input change', function () {
		clearTimeout(autoAdvanceDeliveryTimer);
		var $deliveryAccordion = $('.custom-accordion-item').filter(function () { return $(this).find('.custom-accordion-title').text().toLowerCase().indexOf('delivery') >= 0; });
		if (!$deliveryAccordion.hasClass('active')) return;
		autoAdvanceDeliveryTimer = setTimeout(function () {
			if (isDeliveryStepComplete()) {
				updateActiveStep(3);
			}
		}, 600);
	});

	// Step form pattern - update active step
	function updateActiveStep(stepNumber) {
		$('.step-item').each(function () {
			var step = parseInt($(this).data('step'));
			$(this).removeClass('active completed');
			if (step < stepNumber) {
				$(this).addClass('completed');
			} else if (step === stepNumber) {
				$(this).addClass('active');
			}
		});

		// Update connectors
		$('.step-connector').each(function (index) {
			$(this).removeClass('completed');
			if (index < stepNumber - 1) {
				$(this).addClass('completed');
			}
		});
	}

	// Accordion open handler - update step
	$('.custom-accordion-header').on('click', function () {
		var $accordion = $(this).closest('.custom-accordion-item');
		var accordionIndex = $accordion.index('.custom-accordion-item') + 1;

		setTimeout(function () {
			if ($accordion.hasClass('active')) {
				updateActiveStep(accordionIndex);
			}
		}, 300);
	});

	// Initialize step 1 as active
	updateActiveStep(1);

	function openDeliveryAccordion() {
		var $accordions = $('.custom-product-accordions .custom-accordion-item');
		var $thirdAccordion = $accordions.eq(2);
		if (!$thirdAccordion.length) return;
		$accordions.removeClass('active');
		$accordions.find('.custom-accordion-content').css('max-height', '0');
		$thirdAccordion.addClass('active');
		var $content = $thirdAccordion.find('.custom-accordion-content');
		var scrollHeight = $content[0].scrollHeight;
		$content.css('max-height', Math.max(scrollHeight, 5000) + 'px');
		updateActiveStep(3);
		$('html, body').animate({
			scrollTop: $thirdAccordion.offset().top - 100
		}, 400);
	}


	// Recipient Type Toggle (Myself vs Someone else)
	$('input[name="recipient_type"]').on('change', function (e) {
		var recipientType = $(this).val();
		var $nameField = $('#recipient_name');
		var $emailField = $('#recipient_email');
		var $personalizationFields = $('#personalization-fields');
		var $myselfPriceDisplay = $('#myself-price-display');
		// var isUserInteraction = e.originalEvent != null;

		if (recipientType === 'myself') {
			// Auto-populate with current user details
			if (singleProductData.isUserLoggedIn) {
				var currentUser = {
					name: singleProductData.currentUserName,
					email: singleProductData.currentUserEmail,
					phone: singleProductData.currentUserPhone || ''
				};

				// Update recipient fields with current user info
				$nameField.val(currentUser.name);
				$emailField.val(currentUser.email);

				// Auto-populate delivery fields with current user details
				// Auto-populate delivery email and disable it (myself = no editing)
				$('#delivery_email').val(currentUser.email).prop('disabled', true).prop('readonly', true);

				// Auto-populate delivery phone number if available and disable it
				if (currentUser.phone) {
					$('#delivery_number').val(currentUser.phone).prop('disabled', true).prop('readonly', true);
				}

				// Hide personalization fields, show only price
				$personalizationFields.hide();
				$myselfPriceDisplay.show();

				// Update accordion height after showing/hiding fields
				setTimeout(function () {
					var $accordion = $personalizationFields.closest('.custom-accordion-item');
					var $content = $accordion.find('.custom-accordion-content');
					if ($accordion.hasClass('active')) {
						// Recalculate scrollHeight and set a large enough max-height
						var newHeight = $content[0].scrollHeight;
						$content.css('max-height', Math.max(newHeight, 2000) + 'px');
					}
				}, 100);

				// Update selected gift card if gift card is selected; keep card_design/image so switching back to Someone else shows it again
				if (selectedGiftCard) {
					selectedGiftCard.recipient_name = currentUser.name;
					selectedGiftCard.recipient_email = currentUser.email;
					updateGiftCardDisplay();
				}

				// Update price display if gift card is selected
				updatePersonalizationPriceDisplay();

			} else {
				// User not logged in: enable "Myself" option (same UI, no auto-populate)
				$personalizationFields.hide();
				$myselfPriceDisplay.show();
				setTimeout(function () {
					var $accordion = $personalizationFields.closest('.custom-accordion-item');
					var $content = $accordion.find('.custom-accordion-content');
					if ($accordion.hasClass('active')) {
						var newHeight = $content[0].scrollHeight;
						$content.css('max-height', Math.max(newHeight, 2000) + 'px');
					}
				}, 100);
				if (selectedGiftCard) {
					selectedGiftCard.recipient_name = $nameField.val() || '';
					selectedGiftCard.recipient_email = $emailField.val() || '';
					updateGiftCardDisplay();
				}
				updatePersonalizationPriceDisplay();
				// }

					// Whenever "Myself" is selected, move user to Delivery accordion.
					setTimeout(function () {
						openDeliveryAccordion();
					}, 200);
			}
		} else {
			// Someone else - show all personalization fields
			$myselfPriceDisplay.hide();
			$personalizationFields.css({
				'display': 'flex',
				'flex-direction': 'column',
				'visibility': 'visible',
				'opacity': '1'
			});

			// Allow manual entry
			$nameField.val('').prop('readonly', false).css('background-color', '#fff');
			$emailField.val('').prop('readonly', false).css('background-color', '#fff');

			// Clear delivery fields when switching to "someone else" (user should enter manually)
			// Only clear if they were auto-populated (check if they match current user's data)
			var currentDeliveryEmail = $('#delivery_email').val();
			var currentDeliveryPhone = $('#delivery_number').val();

			// Re-enable email and phone fields when switching to "Someone else"
			$('#delivery_email').prop('disabled', false).prop('readonly', false);
			$('#delivery_number').prop('disabled', false).prop('readonly', false);

			if (singleProductData.isUserLoggedIn) {
				if (currentDeliveryEmail === singleProductData.currentUserEmail) {
					$('#delivery_email').val('');
				}
				if (currentDeliveryPhone === singleProductData.currentUserPhone) {
					$('#delivery_number').val('');
				}
			}

			// Clear selected gift card details display when switching to "someone else"
			// Clear recipient name, email, mobile number, and delivery in the selected gift card details section
			$('#selected-recipient-name').text('-');
			$('#selected-email').text('-');
			$('#selected-mobile-number').text('-');
			$('#selected-delivery').text('-');

			// Clear selectedGiftCard recipient data; sync card design from green-ticked item so image shows correctly
			if (selectedGiftCard) {
				selectedGiftCard.recipient_name = '';
				selectedGiftCard.recipient_email = '';
				selectedGiftCard.mobile_number = '';
				selectedGiftCard.delivery = '';
				var $activeDesign = $('.card-design-item.active');
				if ($activeDesign.length) {
					var designUrl = $activeDesign.data('image-url');
					if (designUrl) {
						selectedGiftCard.card_design = designUrl;
						selectedGiftCard.image = designUrl;
					}
				}
				updateGiftCardDisplay();
			}

			// Update accordion height after showing/hiding fields
			setTimeout(function () {
				var $accordion = $personalizationFields.closest('.custom-accordion-item');
				var $content = $accordion.find('.custom-accordion-content');
				if ($accordion.hasClass('active')) {
					// Recalculate scrollHeight and set a large enough max-height
					var newHeight = $content[0].scrollHeight;
					$content.css('max-height', Math.max(newHeight, 2000) + 'px');
				}
			}, 100);

			// Update price display
			updatePersonalizationPriceDisplay();
		}
	});

	// Function to update price display in personalization section (accordion 2) and delivery (accordion 3)
	function updatePersonalizationPriceDisplay() {
		if (selectedGiftCard && selectedGiftCard.price) {
			updateAccordion2And3PriceDisplays(
				selectedGiftCard.price,
				selectedGiftCard.original_amount || null,
				selectedGiftCard.quantity || 1
			);
		} else {
			$('#selected-price-display').html('<span class="selected-price-amount">$0.00</span>');
			$('#someone-else-price-wrapper').html('<span class="selected-price-amount">$0.00</span>');
			$('#delivery-price-display').html('<span class="selected-price-amount">$0.00</span>');
		}
	}

	// Initialize on page load
	var initialRecipientType = $('input[name="recipient_type"]:checked').val();
	if (initialRecipientType) {
		// Trigger change to show/hide appropriate fields
		$('input[name="recipient_type"][value="' + initialRecipientType + '"]').trigger('change');
	} else {
		// Default to "Someone else" if nothing is selected
		var $someoneElse = $('input[name="recipient_type"][value="someone_else"]');
		if ($someoneElse.length) {
			$someoneElse.prop('checked', true).trigger('change');
		}
	}

	// Update Button Handler
	$('.accordion-update-btn').on('click', function () {
		var $accordion = $(this).closest('.custom-accordion-item');
		var accordionType = $accordion.find('.custom-accordion-title').text().toLowerCase();

		// Collect form data
		var formData = {};

		if (accordionType.includes('choose')) {
			formData.gift_card_type = $('input[name="gift_card_type"]:checked').val();
			var selectedAmount = $('.denomination-btn.active').data('amount');
			if (selectedAmount === 'custom') {
				formData.amount = $('.custom-amount-field').val();
			} else {
				formData.amount = selectedAmount;
			}
		} else if (accordionType.includes('personalise')) {
			formData.recipient_name = $('input[name="recipient_name"]').val();
			formData.recipient_email = $('input[name="recipient_email"]').val();
			formData.gift_message = $('textarea[name="gift_message"]').val();
			formData.gift_subject = $('input[name="gift_subject"]').val();
			formData.apply_personalisation = $('input[name="apply_personalisation"]').is(':checked') ? 1 : 0;
		} else if (accordionType.includes('delivery')) {
			formData.delivery_method = $('input[name="delivery_method"]:checked').val();
			if (formData.delivery_method === 'scheduled') {
				formData.delivery_datetime = $('input[name="delivery_datetime"]').val();
			}
		}

		// Only process "Choose your gift card" accordion - validate FIRST before showing success
		if (accordionType.includes('choose')) {
			// Get selected price button (use .active as fallback - both are set on click)
			var $allAmountBtns = $('.amount-btn').not('.custom-amount-btn');
			var $selectedAmountBtn = $('.amount-btn.selected, .amount-btn.active').first();

			if (!$selectedAmountBtn.length && $allAmountBtns.length === 1) {
				$allAmountBtns.first().trigger('click');
				$selectedAmountBtn = $('.amount-btn.selected, .amount-btn.active').first();
			}
			var selectedAmount = null;
			var customAmount = null;

			// Check if a price button is selected
			if ($selectedAmountBtn.length) {
				var amountData = $selectedAmountBtn.data('amount');

				if (amountData === 'custom') {

					var $customError = $('.custom-amount-error');
					$customError.hide().text('');

					customAmount = parseFloat($('.custom-amount-input-field').val());

					if (!customAmount || customAmount <= 0) {
						$customError.text('Please enter a valid amount').fadeIn(300);
						showMessage('Please enter a valid custom amount', 'error');
						return;
					}

					var rangeMin = (singleProductData.denominationType === 'variable' && singleProductData.originalMinPrice != null)
						? singleProductData.originalMinPrice
						: singleProductData.minPrice;

					var rangeMax = (singleProductData.denominationType === 'variable' && singleProductData.originalMaxPrice != null)
						? singleProductData.originalMaxPrice
						: singleProductData.maxPrice;

					if (customAmount < rangeMin || customAmount > rangeMax) {
						$customError.text('Amount must be between $' + formatPrice(rangeMin) + ' and $' + formatPrice(rangeMax)).fadeIn(300);
						showMessage('Amount must be between $' + formatPrice(rangeMin) + ' and $' + formatPrice(rangeMax), 'error');
						return;
					}

					if (singleProductData.denominationType === 'variable' && singleProductData.priceIntervals > 0) {
						var remainder = (customAmount - rangeMin) % singleProductData.priceIntervals;

						var isMinOrMax = Math.abs(customAmount - rangeMin) < 0.01 || Math.abs(customAmount - rangeMax) < 0.01;

						if (!isMinOrMax && remainder > 0.01 && Math.abs(remainder - singleProductData.priceIntervals) > 0.01) {
							$customError.text('Amount must be in increments of $' + formatPrice(singleProductData.priceIntervals) + '. Please enter a valid amount.').fadeIn(300);
							showMessage('Amount must be in increments of $' + formatPrice(singleProductData.priceIntervals) + '. Please enter a valid amount.', 'error');
							return;
						}
					}

					var originalPrice = customAmount; // ✅ user input = original
					var salePrice = customAmount;

					if (
						singleProductData.discountActive &&
						singleProductData.originalMinPrice &&
						singleProductData.minPrice &&
						singleProductData.originalMinPrice > 0
					) {
						var ratio = singleProductData.minPrice / singleProductData.originalMinPrice; // ✅ 9/10

						salePrice = originalPrice * ratio;
					}

					// rounding
					salePrice = parseFloat(salePrice.toFixed(2));
					// rounding
					originalPrice = parseFloat(originalPrice.toFixed(2));
					var html = 'Price: <span class="discounted-price-pink 6">$' + salePrice.toFixed(2) + '</span>';

					if (originalPrice > salePrice) {
						html += ' <span class="price-strikethrough">$' + formatPrice(originalPrice) + '</span>';
					}

					jQuery('.product-price-display').html(html);

					// console.log('salePrice', salePrice);
					// console.log('amount_options FULL', singleProductData);
					// console.log('originalPrice', originalPrice);

					selectedAmount = customAmount;
				} else {

					// Regular amount button selected - validate interval for variable products
					selectedAmount = parseFloat(amountData);
					if (singleProductData.denominationType === 'variable' && singleProductData.priceIntervals > 0) {
						var rangeMin = (singleProductData.originalMinPrice != null) ? singleProductData.originalMinPrice : singleProductData.minPrice;
						var rangeMax = (singleProductData.originalMaxPrice != null) ? singleProductData.originalMaxPrice : singleProductData.maxPrice;
						if (selectedAmount < rangeMin || selectedAmount > rangeMax) {
							showMessage('Amount must be between $' + formatPrice(rangeMin) + ' and $' + formatPrice(rangeMax), 'error');
							return;
						}
						var remainder = (selectedAmount - rangeMin) % singleProductData.priceIntervals;
						var validInterval = (Math.abs(remainder) < 0.01) || (Math.abs(remainder - singleProductData.priceIntervals) < 0.01);
						var isMinOrMax = Math.abs(selectedAmount - rangeMin) < 0.01 || Math.abs(selectedAmount - rangeMax) < 0.01;
						if (!isMinOrMax && !validInterval) {
							showMessage('Amount must be in increments of $' + formatPrice(singleProductData.priceIntervals) + '. Please select a valid amount.', 'error');
							return;
						}
					}
				}
			} else {
				// No price selected
				showMessage('Please select a price amount', 'error');
				return;
			}

			// Get current quantity from dropdown
			var currentQuantity = parseInt($('#gift_card_quantity').val()) || 1;

			if (!selectedAmount || selectedAmount <= 0) {
				showMessage('Please select a valid price amount', 'error');
				return;
			}

			// Validate transaction limits (total value and quantity per transaction) before proceeding
			if (!checkTransactionLimitsOnSelection(currentQuantity, selectedAmount)) {
				return;
			}

			// Get recipient details from personalization fields
			var recipientName = $('#recipient_name').val() || '-';
			var recipientEmail = $('#recipient_email').val() || $('#delivery_email').val() || '-';
			var mobileNumber = $('#recipient_phone').val() || $('#delivery_number').val() || '-';
			var deliveryMethod = $('input[name="delivery_method"]:checked').val() || 'email';
			var deliveryTiming = $('input[name="delivery_timing"]:checked').val() || 'instant';
			var deliveryText = '';

			if (deliveryMethod === 'email') {
				deliveryText = 'Email';
			} else if (deliveryMethod === 'sms') {
				deliveryText = 'SMS';
			} else if (deliveryMethod === 'email_sms') {
				deliveryText = 'Email+SMS';
			}

			if (deliveryTiming === 'schedule') {
				var scheduleDate = $('#schedule_date').val();
				var scheduleTime = $('#schedule_time').val();
				var scheduleTimezone = $('#schedule_timezone').val();
				if (scheduleDate && scheduleTime) {
					deliveryText += ' - Schedule: ' + scheduleDate + ' ' + scheduleTime + ' ' + (scheduleTimezone || '');
				} else {
					deliveryText += ' - Schedule for later';
				}
			} else {
				deliveryText += ' - Send instantly';
			}

			// Get product data from selected button or use defaults
			var productId = $selectedAmountBtn.data('product-id') || singleProductData.productId;
			var sku = $selectedAmountBtn.data('sku') || singleProductData.productSku;
			var title = $selectedAmountBtn.data('title') || singleProductData.productTitle;
			var image = $selectedAmountBtn.data('image') || singleProductData.productImageUrl;

			// When "Myself" is selected, do not use card design from personalization (do not change selected-design-image)
			var recipientTypeAtUpdate = $('input[name="recipient_type"]:checked').val();
			var selectedDesign = $('.card-design-item.active');
			var cardDesignImage = '';
			if (recipientTypeAtUpdate !== 'myself' && selectedDesign.length) {
				cardDesignImage = selectedDesign.data('image-url') || '';
			}
			var displayImage = cardDesignImage || image;

			// Create gift card selection (new product, so cart_item_key should be null)
			// NOTE: Do NOT add to cart here - only update display. Cart will be added when "Buy now" is clicked.
			var originalAmount = $selectedAmountBtn.data('original-amount');
			// Variable + discount: chosen amount is from original range; price to charge = amount * (discountedPrice / originalMinPrice)
			var effectivePrice = selectedAmount;
			var displayOriginalAmount = originalAmount ? parseFloat(originalAmount) : null;
			if (singleProductData.denominationType === 'variable' && singleProductData.discountActive && singleProductData.discountedPrice && singleProductData.originalMinPrice > 0) {
				effectivePrice = Math.round(selectedAmount * (singleProductData.discountedPrice / singleProductData.originalMinPrice) * 100) / 100;
				displayOriginalAmount = selectedAmount; // Strikethrough shows the sell-price amount
			}
			selectedGiftCard = {
				product_id: productId,
				sku: sku,
				title: title,
				image: displayImage, // Use card design only when Someone else; for Myself use product image only
				price: effectivePrice,
				original_amount: displayOriginalAmount, // For strikethrough in accordions 2 & 3
				quantity: currentQuantity,
				recipient_name: recipientName,
				recipient_email: recipientEmail,
				mobile_number: mobileNumber,
				delivery: deliveryText,
				card_design: cardDesignImage, // Empty when Myself so preview/summary don't show Someone else design
				cart_item_key: null  // New product selection, no cart item key yet - will be set when "Buy now" is clicked
			};

			// DO NOT add to cart here - only update the display
			// Cart will be added/updated only when user clicks "Buy now" button
			// Removed: addToWooCommerceCart() call

			updateGiftCardDisplay();
			updatePersonalizationPriceDisplay();

			// Clear custom amount input if it was used
			if (customAmount !== null) {
				$('.custom-amount-input-field').val('');
				$('.custom-amount-input-wrapper').slideUp();
			}


			// Update Section 1 "Choose your gift card" to show selected denomination (sell price) instead of full range
			var selPrice = selectedGiftCard.price;
			var selQty = selectedGiftCard.quantity || 1;
			var section1Total = selPrice * selQty;
			var section1Orig = selectedGiftCard.original_amount || singleProductData.originalPriceForDisplay || singleProductData.originalPrice;
			// Only show strikethrough when product is actually discounted (Discounted selected and within valid date range)
			var section1ShowStrike = singleProductData.discountActive && section1Orig > selPrice;
			var section1Html = '<span class="selected-price-amount">$' + formatPrice(section1Total) + '</span>';
			if (section1ShowStrike) {
				section1Html += ' <span class="price-strikethrough">$' + formatPrice((section1Orig * selQty)) + '</span>';
			}
			$('#dynamic-price-display').html(section1Html);

			// Show success message only after validation passes
			showUpdateSuccessMessage($accordion);
			// Automatically open the second accordion (Personalise your order)
			setTimeout(function () {
				var $accordions = $('.custom-product-accordions .custom-accordion-item');
				var $secondAccordion = $accordions.eq(1);
				if ($secondAccordion.length) {
					// Close all accordions
					$accordions.removeClass('active');
					$accordions.find('.custom-accordion-content').css('max-height', '0');
					// Open second accordion
					$secondAccordion.addClass('active');
					var $content = $secondAccordion.find('.custom-accordion-content');
					var scrollHeight = $content[0].scrollHeight;
					$content.css('max-height', Math.max(scrollHeight, 5000) + 'px');
					// Update step indicator
					updateActiveStep(2);
					// Scroll to second accordion so it's in view
					$('html, body').animate({
						scrollTop: $secondAccordion.offset().top - 100
					}, 400);
				}
			}, 350);
		} else if (accordionType.includes('personalise')) {

			// ❌ Step 1 must be completed first
			if (!selectedGiftCard || !selectedGiftCard.price || selectedGiftCard.price <= 0) {
				showMessage('Please complete Step 1: Choose your gift card first.', 'error');
				return;
			}

			// If recipient type = someone_else → validate name
			var recipientType = $('input[name="recipient_type"]:checked').val();

			if (recipientType === 'someone_else') {
				var recipientName = $('#recipient_name').val().trim();

				if (!recipientName) {
					showMessage('Please enter recipient name.', 'error');
					return;
				}
			}

			showUpdateSuccessMessage($accordion);

			// After Personalise: open Step 3 (Delivery) only when user is not logged in.
			// When logged in + "Myself" selected, skip Step 3 (do not open the 3rd accordion).
			setTimeout(function () {
				var recipientType = $('input[name="recipient_type"]:checked').val();
				var shouldOpenDelivery = !singleProductData.isUserLoggedIn || recipientType === 'someone_else';
				if (shouldOpenDelivery) {
					// var $accordions = $('.custom-product-accordions .custom-accordion-item');
					// var $thirdAccordion = $accordions.eq(2);
					// if ($thirdAccordion.length) {
					// 	$accordions.removeClass('active');
					// 	$accordions.find('.custom-accordion-content').css('max-height', '0');
					// 	$thirdAccordion.addClass('active');
					// 	var $content = $thirdAccordion.find('.custom-accordion-content');
					// 	var scrollHeight = $content[0].scrollHeight;
					// 	$content.css('max-height', Math.max(scrollHeight, 5000) + 'px');
					// 	updateActiveStep(3);
					// 	$('html, body').animate({
					// 		scrollTop: $thirdAccordion.offset().top - 100
					// 	}, 400);
					openDeliveryAccordion();
					// }
				}
			}, 350);

		} else if (accordionType.includes('delivery')) {

			// ❌ Step 1 must be completed first
			if (!selectedGiftCard || !selectedGiftCard.price || selectedGiftCard.price <= 0) {
				showMessage('Please complete Step 1: Choose your gift card first.', 'error');
				return;
			}

			// Validate delivery method
			var deliveryMethod = $('input[name="delivery_method"]:checked').val();

			if (!deliveryMethod) {
				showMessage('Please select delivery method.', 'error');
				return;
			}

			// Validate email if email selected
			if (deliveryMethod === 'email' || deliveryMethod === 'email_sms') {
				var email = $('#delivery_email').val().trim();
				if (!email) {
					showMessage('Please enter delivery email.', 'error');
					return;
				}
			}

			// Validate mobile if SMS selected (Australian: +61 or 04 prefix)
			if (deliveryMethod === 'sms' || deliveryMethod === 'email_sms') {
				var phone = $('#delivery_number').val().trim();
				if (!phone) {
					showMessage('Please enter mobile number.', 'error');
					return;
				}
				if (!isValidAustralianMobile(phone)) {
					showMessage('Please enter a valid Australian mobile number (e.g. +61 412 345 678 or 0412 345 678).', 'error');
					return;
				}
			}

			// Validate schedule date if scheduled
			var deliveryTiming = $('input[name="delivery_timing"]:checked').val();
			if (deliveryTiming === 'schedule') {
				var scheduleDate = $('#schedule_date').val();
				var scheduleTime = $('#schedule_time').val();

				if (!scheduleDate || !scheduleTime) {
					showMessage('Please select scheduled date and time.', 'error');
					return;
				}
			}

			showUpdateSuccessMessage($accordion);
		}

	});

	// Helper function to show update success message
	function showUpdateSuccessMessage($accordion) {
		// Remove any existing error messages first
		$('.update-message').remove();

		// Show success message on page - place it outside accordion content so it's always visible
		var $messageContainer = $('<div class="update-message" style="margin-top: 12px; padding: 12px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;"></div>');
		$messageContainer.text('Settings updated successfully!');

		// Place message after the accordion content, not inside it
		$accordion.find('.custom-accordion-content').after($messageContainer);
		$messageContainer.fadeIn(300);

		// Auto-hide message after 3 seconds
		setTimeout(function () {
			$messageContainer.fadeOut(300, function () {
				$(this).remove();
			});
		}, 3000);
	}

	// Buy Now Button Handler with Validation
	$('#gift-card-buy-btn').on('click', function (e) {
		e.preventDefault();

		// Remove any existing error messages
		$('.buy-now-error-message').remove();

		var errors = [];
		var firstErrorField = null;

		// Validation 1: Check if product is selected
		if (!selectedGiftCard || !selectedGiftCard.price || selectedGiftCard.price <= 0) {
			errors.push('Please select a gift card amount and click Update in the "Choose your gift card" section.');
			var $chooseAccordion = $('.custom-accordion-item').first();
			if (!firstErrorField) {
				firstErrorField = $chooseAccordion.find('.amount-btn').first();
				if (firstErrorField.length === 0) {
					firstErrorField = $chooseAccordion.find('.custom-accordion-header');
				}
			}
		}

		// Validation 2: Personalise your order accordion
		var recipientType = $('input[name="recipient_type"]:checked').val();
		if (recipientType === 'someone_else') {
			var recipientName = $('#recipient_name').val();
			if (!recipientName || recipientName.trim() === '') {
				errors.push('Recipient name is required in the "Personalise your order" section.');
				if (!firstErrorField) {
					firstErrorField = $('#recipient_name');
				}
			}
		}

		// Validation 3: Delivery accordion
		var deliveryMethod = $('input[name="delivery_method"]:checked').val();
		if (!deliveryMethod) {
			errors.push('Please select a delivery method in the "Delivery" section.');
			if (!firstErrorField) {
				firstErrorField = $('input[name="delivery_method"]').first();
			}
		} else {
			// Check email field if required
			if (deliveryMethod === 'email' || deliveryMethod === 'email_sms') {
				var deliveryEmail = $('#delivery_email').val();
				if (!deliveryEmail || deliveryEmail.trim() === '') {
					errors.push('Email is required for the selected delivery method.');
					if (!firstErrorField) {
						firstErrorField = $('#delivery_email');
					}
				} else if (!isValidEmail(deliveryEmail)) {
					errors.push('Please enter a valid email address.');
					if (!firstErrorField) {
						firstErrorField = $('#delivery_email');
					}
				}
			}

			// Check mobile number field if required (Australian: +61 or 04 prefix)
			if (deliveryMethod === 'sms' || deliveryMethod === 'email_sms') {
				var deliveryNumber = $('#delivery_number').val();
				if (!deliveryNumber || deliveryNumber.trim() === '') {
					errors.push('Mobile number is required for the selected delivery method.');
					if (!firstErrorField) {
						firstErrorField = $('#delivery_number');
					}
				} else if (!isValidAustralianMobile(deliveryNumber)) {
					errors.push('Please enter a valid Australian mobile number (e.g. +61 412 345 678 or 0412 345 678).');
					if (!firstErrorField) {
						firstErrorField = $('#delivery_number');
					}
				}
			}

			// Check scheduled delivery fields if scheduled
			var deliveryTiming = $('input[name="delivery_timing"]:checked').val();
			if (deliveryTiming === 'schedule') {
				var scheduleDate = $('#schedule_date').val();
				var scheduleTime = $('#schedule_time').val();
				var scheduleTimezone = $('#schedule_timezone').val();

				if (!scheduleDate || scheduleDate.trim() === '') {
					errors.push('Schedule date is required when scheduling delivery.');
					if (!firstErrorField) {
						firstErrorField = $('#schedule_date');
					}
				}

				if (!scheduleTime || scheduleTime.trim() === '') {
					errors.push('Schedule time is required when scheduling delivery.');
					if (!firstErrorField) {
						firstErrorField = $('#schedule_time');
					}
				}

				if (!scheduleTimezone || scheduleTimezone.trim() === '') {
					errors.push('Schedule timezone is required when scheduling delivery.');
					if (!firstErrorField) {
						firstErrorField = $('#schedule_timezone');
					}
				}
			}
		}

		// If there are errors, display them and scroll to first error
		if (errors.length > 0) {
			// Create error message element
			var errorMessage = '<div class="buy-now-error-message" style="margin-top: 16px; padding: 16px; background-color: #fee; color: #c33; border: 1px solid #fcc; border-radius: 4px; font-size: 14px;"><strong>Please fix the following errors:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px;">';
			errors.forEach(function (error) {
				errorMessage += '<li>' + error + '</li>';
			});
			errorMessage += '</ul></div>';

			// Insert error message after buy now button
			$('#gift-card-buy-now-form').after(errorMessage);

			// Open accordion if it's closed and scroll to first error field
			if (firstErrorField && firstErrorField.length) {
				var $accordion = firstErrorField.closest('.custom-accordion-item');
				if ($accordion.length) {
					// Open the accordion if closed
					if (!$accordion.hasClass('active')) {
						$accordion.find('.custom-accordion-header').trigger('click');
					}

					// Show hidden fields if needed (wait for accordion to open)
					setTimeout(function () {
						if (firstErrorField.is('#delivery_email') && $('#delivery-email-field').is(':hidden')) {
							$('#delivery-email-field').show();
						}
						if (firstErrorField.is('#delivery_number') && $('#delivery-phone-field').is(':hidden')) {
							$('#delivery-phone-field').show();
						}
						if (firstErrorField.is('#schedule_date') && $('.scheduled-delivery-fields').is(':hidden')) {
							$('.scheduled-delivery-fields').show();
							$('input[name="delivery_timing"][value="schedule"]').prop('checked', true).trigger('change');
						}
					}, 100);

					// Scroll to the error field
					setTimeout(function () {
						$('html, body').animate({
							scrollTop: firstErrorField.offset().top - 150
						}, 500);

						// Highlight the field
						firstErrorField.focus();
						firstErrorField.css('border-color', '#c33');
						setTimeout(function () {
							firstErrorField.css('border-color', '');
						}, 3000);
					}, 300);
				}
			} else {
				// Scroll to error message if we can't find the field
				setTimeout(function () {
					$('html, body').animate({
						scrollTop: $('.buy-now-error-message').offset().top - 100
					}, 500);
				}, 100);
			}

			return false;
		}

		// All validations passed - pass data via session: populate form and submit (no AJAX)
		if (selectedGiftCard) {
			// Update selectedGiftCard with latest values from form fields
			var recipientType = $('input[name="recipient_type"]:checked').val();
			if (recipientType === 'myself' && singleProductData.isUserLoggedIn) {
				selectedGiftCard.recipient_name = singleProductData.currentUserName;
				selectedGiftCard.recipient_email = singleProductData.currentUserEmail;
				selectedGiftCard.mobile_number = $('#delivery_number').val() || singleProductData.currentUserPhone || '-';
			} else {
				selectedGiftCard.recipient_name = $('#recipient_name').val() || '-';
				selectedGiftCard.recipient_email = $('#recipient_email').val() || $('#delivery_email').val() || '-';
				selectedGiftCard.mobile_number = $('#recipient_phone').val() || $('#delivery_number').val() || '-';
			}

			var currentQuantity = parseInt($('#gift_card_quantity').val()) || 1;
			selectedGiftCard.quantity = currentQuantity;

			var deliveryMethod = $('input[name="delivery_method"]:checked').val() || 'email';
			var deliveryTiming = $('input[name="delivery_timing"]:checked').val() || 'instant';
			var scheduleDate = $('#schedule_date').val() || '';
			var scheduleTime = $('#schedule_time').val() || '';
			var scheduleTimezone = $('#schedule_timezone').val() || '';
			var scheduleDateTime = '';
			if (deliveryTiming === 'schedule' && scheduleDate && scheduleTime) {
				scheduleDateTime = scheduleDate.trim() + ' ' + scheduleTime.trim();
				if (scheduleTimezone) {
					scheduleDateTime += ' ' + scheduleTimezone.trim();
				}
			}

			var deliveryEmail = $('#delivery_email').val();
			if (deliveryEmail) {
				selectedGiftCard.delivery_email = deliveryEmail;
			} else if (recipientType === 'myself' && singleProductData.isUserLoggedIn) {
				selectedGiftCard.delivery_email = singleProductData.currentUserEmail;
			}

			selectedGiftCard.sender_name = $('#sender_name').val() || '';
			selectedGiftCard.gift_message = $('#gift_message').val() || '';
			var selectedDesign = $('.card-design-item.active');
			selectedGiftCard.card_design = (selectedDesign.length && selectedDesign.data('image-url')) ? selectedDesign.data('image-url') : '';
			if (selectedGiftCard.card_design) {
				saveCardDesignToSession(selectedGiftCard.card_design);
			}
			var $buyBtn = $(this);
			$buyBtn.prop('disabled', true).text('Redirecting...');

			// Populate the session form and submit (server will store in session and redirect to checkout)
			var $form = $('#gift-card-buy-now-form');
			if ($form.length) {
				$form.find('#gc_buy_now_quantity').val(currentQuantity);
				$form.find('#gc_buy_now_gift_card_price').val(selectedGiftCard.price || '');
				$form.find('#gc_buy_now_recipient_name').val(selectedGiftCard.recipient_name || '');
				$form.find('#gc_buy_now_recipient_email').val(selectedGiftCard.recipient_email || '');
				$form.find('#gc_buy_now_sender_name').val(selectedGiftCard.sender_name || '');
				$form.find('#gc_buy_now_mobile_number').val(selectedGiftCard.mobile_number || '');
				$form.find('#gc_buy_now_delivery_email').val(selectedGiftCard.delivery_email || '');
				$form.find('#gc_buy_now_delivery_method').val(deliveryMethod || '');
				$form.find('#gc_buy_now_delivery_timing').val(deliveryTiming || '');
				$form.find('#gc_buy_now_gift_for').val(recipientType || '');
				$form.find('#gc_buy_now_gift_message').val(selectedGiftCard.gift_message || '');
				$form.find('#gc_buy_now_card_design').val(selectedGiftCard.card_design || '');
				$form.find('#gc_buy_now_schedule_date').val(scheduleDate);
				$form.find('#gc_buy_now_schedule_time').val(scheduleTime);
				$form.find('#gc_buy_now_schedule_timezone').val(scheduleTimezone);
				$form.find('#gc_buy_now_schedule_datetime').val(scheduleDateTime);
				// Media message fields (animation for email, video for attachment, image for message)
				// Use mediaMessageData first, then DOM fallbacks so video/animation are never lost
				var videoUrl = (typeof mediaMessageData !== 'undefined' && mediaMessageData.video) ? mediaMessageData.video : ($('#preview-modal-video-player').attr('src') || '').trim();
				var animationUrl = (typeof mediaMessageData !== 'undefined' && mediaMessageData.animation) ? mediaMessageData.animation : ($('#preview-animation').attr('src') || $('#preview-modal-animation').attr('src') || '').trim();
				var imageUrl = (typeof mediaMessageData !== 'undefined' && mediaMessageData.image) ? mediaMessageData.image : '';
				$form.find('#gc_buy_now_email_animation').val(animationUrl);
				$form.find('#gc_buy_now_video_message').val(videoUrl);
				$form.find('#gc_buy_now_image_message').val(imageUrl);
				// Ensure session has latest media URLs so server can use them if needed
				if (videoUrl && typeof saveMediaToSession === 'function') saveMediaToSession('video', videoUrl);
				if (animationUrl && typeof saveMediaToSession === 'function') saveMediaToSession('animation', animationUrl);
				$form.submit();
			} else {
				$buyBtn.prop('disabled', false).text('Buy now');
				showMessage('Form not found. Please try again.', 'error');
			}
		} else {
			showMessage('Please select a gift card first.', 'error');
		}
	});

	// Continue Shopping: add current gift card to cart then redirect to brands page
	$('#gift-card-continue-btn').on('click', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var brandsUrl = $btn.attr('href');
		if (!brandsUrl) brandsUrl = (typeof singleProductData !== 'undefined' && singleProductData.brandsUrl) ? singleProductData.brandsUrl : '/brands/';

		$('.buy-now-error-message').remove();
		var errors = [];
		var firstErrorField = null;

		if (!selectedGiftCard || !selectedGiftCard.price || selectedGiftCard.price <= 0) {
			errors.push('Please select a gift card amount and click Update in the "Choose your gift card" section.');
			var $chooseAccordion = $('.custom-accordion-item').first();
			if (!firstErrorField) firstErrorField = $chooseAccordion.find('.amount-btn').first().length ? $chooseAccordion.find('.amount-btn').first() : $chooseAccordion.find('.custom-accordion-header');
		}
		var recipientType = $('input[name="recipient_type"]:checked').val();
		if (recipientType === 'someone_else') {
			var recipientName = $('#recipient_name').val();
			if (!recipientName || recipientName.trim() === '') {
				errors.push('Recipient name is required in the "Personalise your order" section.');
				if (!firstErrorField) firstErrorField = $('#recipient_name');
			}
		}
		var deliveryMethod = $('input[name="delivery_method"]:checked').val();
		if (!deliveryMethod) {
			errors.push('Please select a delivery method in the "Delivery" section.');
			if (!firstErrorField) firstErrorField = $('input[name="delivery_method"]').first();
		} else {
			if (deliveryMethod === 'email' || deliveryMethod === 'email_sms') {
				var deliveryEmail = $('#delivery_email').val();
				if (!deliveryEmail || deliveryEmail.trim() === '') {
					errors.push('Email is required for the selected delivery method.');
					if (!firstErrorField) firstErrorField = $('#delivery_email');
				} else if (!isValidEmail(deliveryEmail)) {
					errors.push('Please enter a valid email address.');
					if (!firstErrorField) firstErrorField = $('#delivery_email');
				}
			}
			if (deliveryMethod === 'sms' || deliveryMethod === 'email_sms') {
				var deliveryNumber = $('#delivery_number').val();
				if (!deliveryNumber || deliveryNumber.trim() === '') {
					errors.push('Mobile number is required for the selected delivery method.');
					if (!firstErrorField) firstErrorField = $('#delivery_number');
				} else if (!isValidAustralianMobile(deliveryNumber)) {
					errors.push('Please enter a valid Australian mobile number (e.g. +61 412 345 678 or 0412 345 678).');
					if (!firstErrorField) firstErrorField = $('#delivery_number');
				}
			}
			var deliveryTiming = $('input[name="delivery_timing"]:checked').val();
			if (deliveryTiming === 'schedule') {
				var scheduleDate = $('#schedule_date').val();
				var scheduleTime = $('#schedule_time').val();
				var scheduleTimezone = $('#schedule_timezone').val();
				if (!scheduleDate || scheduleDate.trim() === '') { errors.push('Schedule date is required when scheduling delivery.'); if (!firstErrorField) firstErrorField = $('#schedule_date'); }
				if (!scheduleTime || scheduleTime.trim() === '') { errors.push('Schedule time is required when scheduling delivery.'); if (!firstErrorField) firstErrorField = $('#schedule_time'); }
				if (!scheduleTimezone || scheduleTimezone.trim() === '') { errors.push('Schedule timezone is required when scheduling delivery.'); if (!firstErrorField) firstErrorField = $('#schedule_timezone'); }
			}
		}

		if (errors.length > 0) {
			var errorMessage = '<div class="buy-now-error-message" style="margin-top: 16px; padding: 16px; background-color: #fee; color: #c33; border: 1px solid #fcc; border-radius: 4px; font-size: 14px;"><strong>Please fix the following errors:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px;">';
			errors.forEach(function (err) { errorMessage += '<li>' + err + '</li>'; });
			errorMessage += '</ul></div>';
			$('#gift-card-buy-now-form').after(errorMessage);
			if (firstErrorField && firstErrorField.length) {
				var $accordion = firstErrorField.closest('.custom-accordion-item');
				if ($accordion.length && !$accordion.hasClass('active')) $accordion.find('.custom-accordion-header').trigger('click');
				setTimeout(function () {
					if (firstErrorField.is('#delivery_email') && $('#delivery-email-field').is(':hidden')) $('#delivery-email-field').show();
					if (firstErrorField.is('#delivery_number') && $('#delivery-phone-field').is(':hidden')) $('#delivery-phone-field').show();
					if (firstErrorField.is('#schedule_date') && $('.scheduled-delivery-fields').is(':hidden')) { $('.scheduled-delivery-fields').show(); $('input[name="delivery_timing"][value="schedule"]').prop('checked', true).trigger('change'); }
					$('html, body').animate({ scrollTop: firstErrorField.offset().top - 150 }, 500);
					firstErrorField.focus();
				}, 300);
			}
			return;
		}

		// Sync form into selectedGiftCard (same as Buy Now)
		var currentQuantity = parseInt($('#gift_card_quantity').val(), 10) || 1;
		selectedGiftCard.quantity = currentQuantity;
		if (recipientType === 'myself' && typeof singleProductData !== 'undefined' && singleProductData.isUserLoggedIn) {
			selectedGiftCard.recipient_name = singleProductData.currentUserName;
			selectedGiftCard.recipient_email = singleProductData.currentUserEmail;
			selectedGiftCard.mobile_number = $('#delivery_number').val() || (singleProductData.currentUserPhone || '-');
		} else {
			selectedGiftCard.recipient_name = $('#recipient_name').val() || '-';
			selectedGiftCard.recipient_email = $('#recipient_email').val() || $('#delivery_email').val() || '-';
			selectedGiftCard.mobile_number = $('#recipient_phone').val() || $('#delivery_number').val() || '-';
		}
		selectedGiftCard.delivery_method = deliveryMethod || 'email';
		selectedGiftCard.delivery_timing = $('input[name="delivery_timing"]:checked').val() || 'instant';
		var scheduleDate = $('#schedule_date').val() || '';
		var scheduleTime = $('#schedule_time').val() || '';
		var scheduleTimezone = $('#schedule_timezone').val() || '';
		var scheduleDateTime = '';
		if (selectedGiftCard.delivery_timing === 'schedule' && scheduleDate && scheduleTime) {
			scheduleDateTime = scheduleDate.trim() + ' ' + scheduleTime.trim();
			if (scheduleTimezone) scheduleDateTime += ' ' + scheduleTimezone.trim();
		}
		selectedGiftCard.delivery_email = $('#delivery_email').val() || (recipientType === 'myself' && typeof singleProductData !== 'undefined' && singleProductData.isUserLoggedIn ? singleProductData.currentUserEmail : '');
		selectedGiftCard.sender_name = $('#sender_name').val() || '';
		selectedGiftCard.gift_message = $('#gift_message').val() || '';
		var selectedDesign = $('.card-design-item.active');
		selectedGiftCard.card_design = (selectedDesign.length && selectedDesign.data('image-url')) ? selectedDesign.data('image-url') : '';
		if (selectedGiftCard.card_design && typeof saveCardDesignToSession === 'function') saveCardDesignToSession(selectedGiftCard.card_design);
		if (typeof saveMediaToSession === 'function') {
			var videoUrl = (typeof mediaMessageData !== 'undefined' && mediaMessageData.video) ? mediaMessageData.video : ($('#preview-modal-video-player').attr('src') || '').trim();
			var animationUrl = (typeof mediaMessageData !== 'undefined' && mediaMessageData.animation) ? mediaMessageData.animation : ($('#preview-animation').attr('src') || $('#preview-modal-animation').attr('src') || '').trim();
			var imageUrl = (typeof mediaMessageData !== 'undefined' && mediaMessageData.image) ? mediaMessageData.image : '';
			if (videoUrl) saveMediaToSession('video', videoUrl);
			if (animationUrl) saveMediaToSession('animation', animationUrl);
			if (imageUrl) saveMediaToSession('image', imageUrl);
		}

		var giftCardData = {
			product_id: selectedGiftCard.product_id,
			quantity: selectedGiftCard.quantity,
			price: selectedGiftCard.price,
			recipient_name: selectedGiftCard.recipient_name,
			recipient_email: selectedGiftCard.recipient_email,
			mobile_number: selectedGiftCard.mobile_number,
			delivery_method: selectedGiftCard.delivery_method,
			delivery_timing: selectedGiftCard.delivery_timing,
			sender_name: selectedGiftCard.sender_name,
			gift_message: selectedGiftCard.gift_message,
			card_design: selectedGiftCard.card_design,
			delivery_email: selectedGiftCard.delivery_email
		};
		if (scheduleDate) giftCardData.schedule_date = scheduleDate;
		if (scheduleTime) giftCardData.schedule_time = scheduleTime;
		if (scheduleTimezone) giftCardData.schedule_timezone = scheduleTimezone;
		if (scheduleDateTime) giftCardData.schedule_datetime = scheduleDateTime;

		$btn.prop('disabled', true).text('Adding...');
		addToWooCommerceCart(giftCardData, function (cartItemKey) {
			if (cartItemKey !== null) {
				window.location = brandsUrl;
			} else {
				$btn.prop('disabled', false).text('Continue Shopping');
			}
		});
	});

	// Helper function to validate email
	function isValidEmail(email) {
		var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return emailRegex.test(email);
	}

	// Australian mobile: +61 4/5XXXXXXXX (9 digits) or 04XXXXXXXX (8 digits after 04)
	function isValidAustralianMobile(phone) {
		if (!phone || typeof phone !== 'string') return false;
		var normalized = phone.replace(/\s/g, '');
		return (/^\+61[45]\d{8}$/.test(normalized)) || (/^04\d{8}$/.test(normalized));
	}

	// Payment Method Change Handler
	$('input[name="payment_method"]').on('change', function () {
		var method = $(this).val();

		// Update active state
		$('.payment-method-option').removeClass('active');
		$(this).closest('.payment-method-option').addClass('active');

		// Show/hide card fields
		if (method === 'card') {
			$('#card-payment-fields').slideDown();
			$('#payment-card-number, #payment-expiry, #payment-cvv').prop('required', true);
		} else {
			$('#card-payment-fields').slideUp();
			$('#payment-card-number, #payment-expiry, #payment-cvv').prop('required', false);
		}
	});

	// Card Number Formatting
	$('#payment-card-number').on('input', function () {
		var value = $(this).val().replace(/\s/g, '');
		var formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
		$(this).val(formattedValue);
	});

	// Expiry Date Formatting
	$('#payment-expiry').on('input', function () {
		var value = $(this).val().replace(/\D/g, '');
		if (value.length >= 2) {
			value = value.substring(0, 2) + '/' + value.substring(2, 4);
		}
		$(this).val(value);
	});

	// CVV Formatting (numbers only)
	$('#payment-cvv').on('input', function () {
		$(this).val($(this).val().replace(/\D/g, ''));
	});

	// Submit Order Button
	$('#submit-order-btn').on('click', function () {
		// Validate form
		var isValid = true;
		var selectedMethod = $('input[name="payment_method"]:checked').val();

		// Validate required fields
		if (!$('#payment-full-name').val()) {
			isValid = false;
			$('#payment-full-name').addClass('error');
		} else {
			$('#payment-full-name').removeClass('error');
		}

		if (!$('#payment-country').val()) {
			isValid = false;
			$('#payment-country').addClass('error');
		} else {
			$('#payment-country').removeClass('error');
		}

		if (!$('#payment-address').val()) {
			isValid = false;
			$('#payment-address').addClass('error');
		} else {
			$('#payment-address').removeClass('error');
		}

		// Validate card fields if card is selected
		if (selectedMethod === 'card') {
			if (!$('#payment-card-number').val() || $('#payment-card-number').val().replace(/\s/g, '').length < 16) {
				isValid = false;
				$('#payment-card-number').addClass('error');
			} else {
				$('#payment-card-number').removeClass('error');
			}

			if (!$('#payment-expiry').val() || $('#payment-expiry').val().length < 5) {
				isValid = false;
				$('#payment-expiry').addClass('error');
			} else {
				$('#payment-expiry').removeClass('error');
			}

			if (!$('#payment-cvv').val() || $('#payment-cvv').val().length < 3) {
				isValid = false;
				$('#payment-cvv').addClass('error');
			} else {
				$('#payment-cvv').removeClass('error');
			}
		}

		if (isValid) {
			// Here you can add AJAX call to process the order
			showMessage('Order submitted successfully!', 'success');
			// Example AJAX call:
			// $.ajax({
			//     url: singleProductData.ajaxUrl,
			//     type: 'POST',
			//     data: {
			//         action: 'process_gift_card_order',
			//         gift_card_data: selectedGiftCard,
			//         payment_data: {
			//             full_name: $('#payment-full-name').val(),
			//             country: $('#payment-country').val(),
			//             address: $('#payment-address').val(),
			//             payment_method: selectedMethod,
			//             card_number: $('#payment-card-number').val(),
			//             expiry: $('#payment-expiry').val(),
			//             cvv: $('#payment-cvv').val()
			//         }
			//     },
			//     success: function(response) {
			//         // Handle success
			//     }
			// });
		} else {
			showMessage('Please fill in all required fields correctly.', 'error');
		}
	});

	// Store uploaded card images
	var uploadedCardImages = [];
	var imageCounter = 0;

	// Start upload button - trigger file input
	$('#start-upload-designs').on('click', function () {
		$('#card-design-image-upload').click();
	});

	// Save selected card design to session only (no media upload until order is placed)
	function saveCardDesignToSession(imageUrl) {
		if (!imageUrl || typeof singleProductData === 'undefined') return;
		var productId = singleProductData.productId;
		var nonce = singleProductData.gcCardDesignNonce || '';
		var ajaxUrl = singleProductData.ajaxUrl || '/wp-admin/admin-ajax.php';
		$.post(ajaxUrl, {
			action: 'gc_save_card_design_to_session',
			nonce: nonce,
			product_id: productId,
			image: imageUrl
		});
	}

	// Handle multiple image uploads - store as data URL in session only (no media upload)
	$('#card-design-image-upload').on('change', function (e) {
		var files = e.target.files;
		if (files && files.length > 0) {
			var $optionsContainer = $('#card-design-options');
			var $uploadButton = $('.card-design-upload-start');
			var totalFiles = files.length;
			var existingCount = $optionsContainer.find('.card-design-item').length;
			if (!$optionsContainer.is(':visible')) {
				$optionsContainer.show();
			}
			var $firstGalleryDefault = $optionsContainer.find('.card-design-item[data-design^="default-gallery-"]').first();
			// Create placeholders in selection order (1st selected = 1st in row, last = last) so order never changes
			var placeholders = [];
			for (var i = 0; i < totalFiles; i++) {
				imageCounter++;
				var designIndex = existingCount + i + 1;
				var $designItem = $('<div class="card-design-item uploaded-batch" data-design="image-' + designIndex + '" data-image-url="" data-upload-index="' + i + '">' +
					'<div class="card-design-preview default-design">' +
					'<img class="selected-design-image" alt="Card Design ' + designIndex + '" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">' +
					'<button type="button" class="remove-card-design-btn" title="Remove image">' +
					'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
					'<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>' +
					'</svg></button></div>' +
					'<svg xmlns="http://www.w3.org/2000/svg" class="check-icon" width="44" height="44" viewBox="0 0 44 44">' +
					'<circle cx="22" cy="22" r="22" fill="#037847"/>' +
					'<path d="M11.2171 20.3927L17.561 26.7146L32.6634 11.6122C33.5195 10.7561 34.9024 10.7561 35.7585 11.6122C36.6146 12.4683 36.6146 13.8512 35.7585 14.7073L19.0976 31.3683C18.2634 32.2244 16.8585 32.2244 16.0024 31.3683L8.12195 23.4878C7.26585 22.6317 7.26585 21.2488 8.12195 20.3927C8.97805 19.5366 10.361 19.5366 11.2171 20.3927Z" fill="white"/>' +
					'</svg></div>');
				if ($firstGalleryDefault.length) {
					$designItem.insertBefore($firstGalleryDefault);
				} else if ($uploadButton.length) {
					$designItem.insertAfter($uploadButton);
				} else {
					$optionsContainer.append($designItem);
				}
				placeholders.push($designItem);
			}
			var filesProcessed = 0;
			Array.from(files).forEach(function (file, fileIndex) {
				var reader = new FileReader();
				reader.onload = function (ev) {
					var imageUrl = ev.target.result;
					uploadedCardImages.push(imageUrl);
					var $designItem = placeholders[fileIndex];
					if ($designItem && $designItem.length) {
						$designItem.attr('data-image-url', imageUrl);
						$designItem.find('.selected-design-image').attr('src', imageUrl);
					}
					filesProcessed++;
					if (filesProcessed === totalFiles) {
						// Last image (last in row = last selected) gets check
						var $lastItem = placeholders[totalFiles - 1];
						if ($lastItem && $lastItem.length) {
							$optionsContainer.find('.card-design-item').removeClass('active');
							$lastItem.addClass('active');
							var lastImageUrl = $lastItem.attr('data-image-url');
							$('.design-selected-icon').show();
							if (selectedGiftCard && lastImageUrl) {
								selectedGiftCard.image = lastImageUrl;
								selectedGiftCard.card_design = lastImageUrl;
								if ($('input[name="recipient_type"]:checked').val() !== 'myself' && $('#selected-gift-card-image').length) {
									$('#selected-gift-card-image').attr('src', lastImageUrl).attr('alt', selectedGiftCard.title || 'Gift Card').show();
									$('#no-image-placeholder').hide();
								}
								saveCardDesignToSession(lastImageUrl);
							}
						}
						placeholders.forEach(function ($el) { $el.removeClass('uploaded-batch'); });
						$(e.target).val('');
					}
				};
				reader.readAsDataURL(file);
			});
		}
	});

	// Card Design Selection (delegated event for dynamically added items)
	$(document).on('click', '.card-design-item', function (e) {
		if ($(e.target).closest('.remove-card-design-btn').length) {
			return;
		}
		var $item = $(this);
		var selectedImageUrl = $item.data('image-url');
		$('.card-design-item').removeClass('active');
		$item.addClass('active');
		$('.design-selected-icon').show();
		if (selectedImageUrl && selectedGiftCard) {
			selectedGiftCard.image = selectedImageUrl;
			selectedGiftCard.card_design = selectedImageUrl;
			// Only update selected-gift-card-image when "Someone else" is selected
			if ($('input[name="recipient_type"]:checked').val() !== 'myself' && $('#selected-gift-card-image').length) {
				$('#selected-gift-card-image').attr('src', selectedImageUrl).attr('alt', selectedGiftCard.title || 'Gift Card').show();
				$('#no-image-placeholder').hide();
			}
			saveCardDesignToSession(selectedImageUrl);
		}
	});

	// Remove card design image
	$(document).on('click', '.remove-card-design-btn', function (e) {
		e.stopPropagation(); // Prevent triggering the card design selection

		var $item = $(this).closest('.card-design-item');
		var imageUrl = $item.data('image-url');
		var wasActive = $item.hasClass('active');

		// Remove from uploaded images array
		var index = uploadedCardImages.indexOf(imageUrl);
		if (index > -1) {
			uploadedCardImages.splice(index, 1);
		}

		// Remove the item
		$item.remove();

		// Check if this was a default image (from product featured/gallery)
		var isDefaultImage = $item.data('is-default') === 1 || $item.data('is-default') === '1';

		// If no images left, hide options container (upload button will still be visible if container is shown)
		var $optionsContainer = $('#card-design-options');
		var remainingItems = $optionsContainer.find('.card-design-item').length;

		if (remainingItems === 0) {
			// Keep container visible so the upload button remains available
			$optionsContainer.show();
			$('.design-selected-icon').hide();

			// Revert to original product image if the removed item was active
			if (wasActive && selectedGiftCard) {
				selectedGiftCard.card_design = '';
				saveCardDesignToSession('');
				var originalImage = singleProductData.productImageUrl || selectedGiftCard.image;
				selectedGiftCard.image = originalImage;
				if ($('input[name="recipient_type"]:checked').val() !== 'myself' && $('#selected-gift-card-image').length) {
					$('#selected-gift-card-image').attr('src', originalImage).attr('alt', selectedGiftCard.title || 'Gift Card').show();
					$('#no-image-placeholder').hide();
				}
			}
		} else {
			// If removed item was active, activate first remaining item
			if (wasActive) {
				var $firstItem = $optionsContainer.find('.card-design-item').first();
				if ($firstItem.length) {
					$firstItem.addClass('active');
					var newImageUrl = $firstItem.data('image-url');
					if (newImageUrl && selectedGiftCard) {
						selectedGiftCard.image = newImageUrl;
						selectedGiftCard.card_design = newImageUrl;
						if ($('input[name="recipient_type"]:checked').val() !== 'myself' && $('#selected-gift-card-image').length) {
							$('#selected-gift-card-image').attr('src', newImageUrl).attr('alt', selectedGiftCard.title || 'Gift Card').show();
							$('#no-image-placeholder').hide();
						}
						saveCardDesignToSession(newImageUrl);
					}
				} else {
					if (selectedGiftCard) {
						selectedGiftCard.card_design = '';
						saveCardDesignToSession('');
						var originalImage = singleProductData.productImageUrl || selectedGiftCard.image;
						selectedGiftCard.image = originalImage;
						if ($('input[name="recipient_type"]:checked').val() !== 'myself' && $('#selected-gift-card-image').length) {
							$('#selected-gift-card-image').attr('src', originalImage).attr('alt', selectedGiftCard.title || 'Gift Card').show();
							$('#no-image-placeholder').hide();
						}
					}
				}
			}
		}
	});

	// Media Upload - store uploaded URLs (for session + form)
	var mediaMessageData = { image: '', video: '', animation: '' };

	// Save media to session (for checkout flow)
	function saveMediaToSession(type, url) {
		if (!url || typeof singleProductData === 'undefined') return;
		var productId = singleProductData.productId;
		var nonce = singleProductData.gcMediaUploadNonce || '';
		var ajaxUrl = singleProductData.ajaxUrl || '/wp-admin/admin-ajax.php';
		$.post(ajaxUrl, {
			action: 'gc_save_media_message_to_session',
			nonce: nonce,
			product_id: productId,
			media_type: type,
			media_url: url
		});
	}

	// Upload media via AJAX
	function uploadMediaMessage(file, type, callback, onProgress) {
		var formData = new FormData();
		formData.append('action', 'gc_upload_media_message');
		formData.append('nonce', singleProductData.gcMediaUploadNonce || '');
		formData.append('product_id', singleProductData.productId || 0);
		formData.append('media_type', type);
		formData.append('media_file', file);

		$.ajax({
			url: singleProductData.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			xhr: function() {
				var xhr = new window.XMLHttpRequest();
				if (onProgress && xhr.upload) {
					xhr.upload.addEventListener('progress', function(evt) {
						if (evt.lengthComputable) {
							var percentComplete = Math.round((evt.loaded / evt.total) * 100);
							onProgress(percentComplete);
						}
					}, false);
				}
				return xhr;
			},
			success: function (response) {
				if (response && response.success && response.data && response.data.url) {
					if (callback) callback(response.data.url);
				} else {
					showMessage(response && response.data && response.data.message ? response.data.message : 'Upload failed.', 'error');
				}
			},
			error: function () {
				showMessage('Upload failed. Please try again.', 'error');
			},
			dataType: 'json'
		});
	}

	// Upload image button
	// $('#upload-image-btn').on('click', function() {
	// 	$('#media-upload-image').click();
	// });
	// $('#media-upload-image').on('change', function(e) {
	// 	var file = e.target.files && e.target.files[0];
	// 	if (!file) return;
	// 	if (!file.type.match(/^image\//)) {
	// 		showMessage('Please select an image file (JPG, PNG, etc.).', 'error');
	// 		$(this).val('');
	// 		return;
	// 	}
	// 	uploadMediaMessage(file, 'image', function(url) {
	// 		mediaMessageData.image = url;
	// 		$('#preview-image').attr('src', url);
	// 		$('#preview-image-container').show();
	// 		$('#media-upload-previews').show();
	// 		saveMediaToSession('image', url);
	// 	});
	// 	$(this).val('');
	// });

	// Upload video button
	$('#upload-video-btn').on('click', function () {
		$('#media-upload-video').click();
	});
	$('#media-upload-video').on('change', function (e) {
		var file = e.target.files && e.target.files[0];
		if (!file) return;
		if (!file.type.match(/^video\//)) {
			showMessage('Please select a video file.', 'error');
			$(this).val('');
			return;
		}
		var maxSize = 5 * 1024 * 1024; // 5MB
		if (file.size > maxSize) {
			$('#media-upload-error').text('Video file size must not exceed 5MB.').show();
			$(this).val('');
			return;
		}
		$('#media-upload-error').hide().text('');
		$('#media-upload-progress').show();
		$('#media-upload-progress-bar').css('width', '0%');
		$('#media-upload-progress-text').text('Uploading 0%');
		$('#media-upload-error').hide().text('');
		uploadMediaMessage(file, 'video', function (url) {
			mediaMessageData.video = url;
			$('#preview-video-name').text(file.name);
			$('#preview-video-container').show();
			$('#media-upload-previews').show();
			$('#media-upload-progress').hide();
			saveMediaToSession('video', url);
		}, function (percent) {
			$('#media-upload-progress-bar').css('width', percent + '%');
			$('#media-upload-progress-text').text('Uploading ' + percent + '%');
		});
		$(this).val('');
	});

	// Animation selection variables
	var selectedAnimationId = null;
	var selectedAnimationUrl = null;
	var animationsLoaded = false;

	// Function to load animations via AJAX
	function loadAnimations() {
		console.log('Loading animations...');
		$('#animation-loading').show();
		$('#animation-grid').hide().empty();
		$('#animation-empty').hide();

		$.ajax({
			url: singleProductData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gc_get_animations'
			},
			success: function (response) {
				console.log('Animations response:', response);
				$('#animation-loading').hide();

				if (response.success && response.data && response.data.animations && response.data.animations.length > 0) {
					var html = '';
					response.data.animations.forEach(function (anim, index) {
						if (!anim.url) return;
						html += '<div class="animation-item" data-animation-id="' + (anim.id || 0) + '" data-animation-url="' + anim.url + '" style="cursor:pointer; border:2px solid transparent; border-radius:8px; padding:8px; text-align:center; transition:border-color 0.2s;">' +
							'<img src="' + (anim.thumbnail || anim.url) + '" alt="' + (anim.alt || '') + '" style="max-width:100%; height:auto; border-radius:4px; max-height:100px; object-fit:cover;" onerror="this.style.display=\'none\'">' +
							'<p style="margin:8px 0 0; font-size:12px; color:#666; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + (anim.title || 'Animation ' + (index + 1)) + '</p>' +
							'</div>';
					});
					if (html) {
						$('#animation-grid').html(html).css('display', 'grid');
						animationsLoaded = true;
						console.log('Animations loaded:', response.data.animations.length);
					} else {
						$('#animation-empty').show();
						animationsLoaded = false;
					}
				} else {
					console.log('No animations found');
					$('#animation-empty').show();
					animationsLoaded = false;
				}
			},
			error: function (xhr, status, error) {
				console.error('Error loading animations:', error);
				$('#animation-loading').hide();
				$('#animation-empty').html('<p>Error loading animations. Please try again.</p>').show();
				animationsLoaded = false;
			}
		});
	}

	// Add animation (GIF) button - Open popup modal
	$('#add-animation-btn').on('click', function () {
		selectedAnimationId = null;
		selectedAnimationUrl = null;
		$('.animation-modal-select').prop('disabled', true).css('opacity', '0.6');
		$('#animation-selection-modal').css('display', 'flex');

		// Load animations if not already loaded
		if (!animationsLoaded) {
			loadAnimations();
		}
	});

	// Close animation modal
	$(document).on('click', '.animation-modal-close, .animation-modal-cancel', function () {
		$('#animation-selection-modal').hide();
		selectedAnimationId = null;
		selectedAnimationUrl = null;
	});

	// Close modal on overlay click
	$('#animation-selection-modal').on('click', function (e) {
		if (e.target === this) {
			$(this).hide();
			selectedAnimationId = null;
			selectedAnimationUrl = null;
		}
	});

	// Select animation from popup
	$(document).on('click', '.animation-item', function () {
		$('.animation-item').removeClass('selected').css('border-color', 'transparent');
		$(this).addClass('selected').css('border-color', '#4caf50');
		selectedAnimationId = $(this).data('animation-id');
		selectedAnimationUrl = $(this).data('animation-url');
		$('.animation-modal-select').prop('disabled', false).css('opacity', '1');
	});

	// Confirm animation selection
	$(document).on('click', '.animation-modal-select', function () {
		if (selectedAnimationId && selectedAnimationUrl) {
			mediaMessageData.animation = selectedAnimationUrl;
			$('#preview-animation').attr('src', selectedAnimationUrl);
			$('#preview-animation-container').show();
			$('#media-upload-previews').show();
			$('#animation-selection-modal').hide();
			// Save to session
			saveMediaToSession('animation', selectedAnimationUrl);
		}
	});
	$('#media-upload-animation').on('change', function (e) {
		var file = e.target.files && e.target.files[0];
		if (!file) return;
		if (!file.type.match(/image\/gif/)) {
			showMessage('Please select a GIF file.', 'error');
			$(this).val('');
			return;
		}
		uploadMediaMessage(file, 'animation', function (url) {
			mediaMessageData.animation = url;
			$('#preview-animation').attr('src', url);
			$('#preview-animation-container').show();
			$('#media-upload-previews').show();
			saveMediaToSession('animation', url);
		});
		$(this).val('');
	});

	// Remove media preview
	$(document).on('click', '.remove-media-preview', function () {
		var type = $(this).data('type');
		mediaMessageData[type] = '';
		if (type === 'image') {
			$('#preview-image').attr('src', '');
			$('#preview-image-container').hide();
		} else if (type === 'video') {
			$('#preview-video-name').text('');
			$('#preview-video-container').hide();
		} else if (type === 'animation') {
			$('#preview-animation').attr('src', '');
			$('#preview-animation-container').hide();
		}
		if (!mediaMessageData.image && !mediaMessageData.video && !mediaMessageData.animation) {
			$('#media-upload-previews').hide();
		}
		// Clear from session
		saveMediaToSession(type, '');
	});

	// Product Info Accordion Toggle
	// Initialize all accordions as closed
	$('.product-info-accordion-item').each(function () {
		var $item = $(this);
		var $content = $item.find('.product-info-accordion-content');
		$item.removeClass('active');
		$content.css('max-height', '0');
	});

	// Toggle accordion on header click
	$('.product-info-accordion-header').on('click', function () {
		var $item = $(this).closest('.product-info-accordion-item');
		var $content = $item.find('.product-info-accordion-content');
		var $icon = $(this).find('.product-info-accordion-icon');

		// Toggle current accordion
		if ($item.hasClass('active')) {
			// Close accordion
			$item.removeClass('active');
			$content.css('max-height', '0');
			$icon.css('transform', 'rotate(0deg)');
		} else {
			// Open accordion
			$item.addClass('active');
			$content.css('max-height', $content[0].scrollHeight + 'px');
			$icon.css('transform', 'rotate(180deg)');
		}
	});

	// If URL hash is #terms-conditions (e.g. from wallet "View Terms & Conditions"), open that accordion and scroll to it
	if (window.location.hash === '#terms-conditions') {
		var $termsItem = $('#terms-conditions');
		if ($termsItem.length) {
			var $content = $termsItem.find('.product-info-accordion-content');
			if ($content.length) {
				var $icon = $termsItem.find('.product-info-accordion-icon');
				$termsItem.addClass('active');
				$content.css('max-height', Math.max($content[0].scrollHeight, 500) + 'px');
				if ($icon.length) $icon.css('transform', 'rotate(180deg)');
			}
			setTimeout(function () {
				$('html, body').animate({ scrollTop: $termsItem.offset().top - 100 }, 400);
			}, 100);
		}
	}
});
