jQuery(document).ready(function($) {
    

    // Store current filter values
    let currentFilters = {
        search: '',
        giftcards_for: [],
        occasion: [],
        sort: ''
    };
    
    // Helper function to update currentFilters from DOM
    function updateFiltersFromDOM() {
        // Get search term from DOM (handled by home-page.js, but we need it for filters)
        currentFilters.search = jQuery('#gc-search').val().trim() || '';
        
        // Get filter values from DOM (handles both single and multiple selects)
        // Only set values if they are not empty
        let giftcardsForVal = jQuery('#gc-giftcards-for').val();
        if (Array.isArray(giftcardsForVal)) {
            // Filter out empty values from array
            giftcardsForVal = giftcardsForVal.filter(val => val && val.trim() !== '');
            currentFilters.giftcards_for = giftcardsForVal.length > 0 ? giftcardsForVal : [];
        } else {
            // For single select, only set if value is not empty
            currentFilters.giftcards_for = (giftcardsForVal && giftcardsForVal.trim() !== '') ? giftcardsForVal : [];
        }
        
        let occasionVal = jQuery('#gc-occasion').val();
        if (Array.isArray(occasionVal)) {
            // Filter out empty values from array
            occasionVal = occasionVal.filter(val => val && val.trim() !== '');
            currentFilters.occasion = occasionVal.length > 0 ? occasionVal : [];
        } else {
            // For single select, only set if value is not empty
            currentFilters.occasion = (occasionVal && occasionVal.trim() !== '') ? occasionVal : [];
        }
        
        currentFilters.sort = jQuery('#gc-sort').val() || '';
    }
    
    // Helper function to prepare filter data for AJAX
    function prepareFilterData() {
        let data = {};
        
        // Handle giftcards_for - ensure it's an array
        if (Array.isArray(currentFilters.giftcards_for) && currentFilters.giftcards_for.length > 0) {
            data.giftcards_for = currentFilters.giftcards_for;
        } else if (!Array.isArray(currentFilters.giftcards_for) && currentFilters.giftcards_for) {
            data.giftcards_for = [currentFilters.giftcards_for];
        } else {
            data.giftcards_for = [];
        }
        
        // Handle occasion - ensure it's an array
        if (Array.isArray(currentFilters.occasion) && currentFilters.occasion.length > 0) {
            data.occasion = currentFilters.occasion;
        } else if (!Array.isArray(currentFilters.occasion) && currentFilters.occasion) {
            data.occasion = [currentFilters.occasion];
        } else {
            data.occasion = [];
        }
        
        return data;
    }
    
    // Function to load products with filters
    // options.silentRefresh: when true, do not replace content with Loading/error (used after wishlist remove)
    function loadProductsWithFilters(resetOffset = true, options = {}) {
        let silentRefresh = !!options.silentRefresh;
        // Always refresh filter values from DOM before loading
        updateFiltersFromDOM();
        
        let section = jQuery('#gc-section');
        let button = jQuery('.load-more');
        // Use section data when Load more button is not present (e.g. wishlist with one page)
        let perpage = parseInt(button.attr('data-perpage') || section.attr('data-gc-perpage'), 10) || 16;
        let offset = resetOffset ? 0 : parseInt(button.attr('data-offset'), 10) || 0;
        let isWishlistPage  = (button.length && button.attr('data-wishlist-page') === '1') || section.attr('data-gc-wishlist-page') === '1';
        let isOffersPage    = (button.length && button.attr('data-offers-page') === '1') || section.attr('data-gc-offers-page') === '1';
        let isCategoryPage  = (button.length && button.attr('data-category-page') === '1') || section.attr('data-gc-category-page') === '1';
        let categoryId      = parseInt(button.attr('data-category-id') || section.attr('data-gc-category-id'), 10) || 0;

        
        // Reset button state
        button.removeClass('disabled');
        button.prop('disabled', false);
        button.css({
            'pointer-events': 'auto',
            'opacity': '1',
            'cursor': 'pointer'
        });
        button.text('Load more');
        
        // Prepare filter data
        let filterData = prepareFilterData();
        
        $.ajax({
            url: userBrandsData.ajax_url,
            type: 'POST',
            dataType: 'json',
            traditional: true, // Use traditional serialization for arrays
            data: {
                action: 'gc_load_more',
                offset: offset,
                perpage: perpage,
                search: currentFilters.search,
                giftcards_for: filterData.giftcards_for,
                occasion: filterData.occasion,
                sort: currentFilters.sort,
                wishlist_page: isWishlistPage ? '1' : '0',
                offers_page: isOffersPage ? '1' : '0',
                category_page: isCategoryPage ? '1' : '0',
                category_id: categoryId
            },
            beforeSend: function(){
                if (!silentRefresh) {
                    jQuery('#gc-carousel .gc-slide').html('<div style="text-align:center; padding:20px;">Loading...</div>');
                }
            },
            success: function(response) {
                if(response.html && response.html.trim() !== '') {
                    jQuery('#gc-carousel .gc-slide').html(response.html);
                    
                    // Update offset
                    button.attr('data-offset', perpage);
                    
                    // Check if there are more posts
                    if(response.has_more) {
                        button.text('Load more');
                        button.show();
                    } else {
                        button.hide();
                    }
                } else {
                    if (!silentRefresh) {
                        jQuery('#gc-carousel .gc-slide').html('<div style="text-align:center; padding:20px;">No products found.</div>');
                    }
                    button.hide();
                }
            },
            error: function() {
                if (!silentRefresh) {
                    jQuery('#gc-carousel .gc-slide').html('<div style="text-align:center; padding:20px; color:red;">An error occurred. Please try again.</div>');
                } else {
                    button.hide();
                }
            }
        });
    }

        // Predictive search: suggestions dropdown for #gc-search
    (function() {
        var suggestTimeout;
        var suggestMinLen = 2;
        var suggestDebounceMs = 280;

        function hideSuggestions() {
            jQuery('#gc-search-suggestions').hide().empty();
        }

        function showSuggestions(products, terms) {
            var $el = jQuery('#gc-search-suggestions');
            if (!products.length && !terms.length) {
                $el.hide().empty();
                return;
            }
            var html = '';
            if (products.length) {
                html += '<div class="gc-suggestions-section gc-suggestions-products"><div class="gc-suggestions-label">Gift cards</div><ul role="list">';
                products.forEach(function(p) {
                    html += '<li role="option"><a href="' + (p.url || '#') + '" class="gc-suggestion-item gc-suggestion-product" data-title="' + (p.title || '').replace(/"/g, '&quot;') + '">' + (p.title || '') + (p.price ? ' <span class="gc-suggestion-price">' + p.price + '</span>' : '') + '</a></li>';
                });
                html += '</ul></div>';
            }
            if (terms.length) {
                html += '<div class="gc-suggestions-section gc-suggestions-terms"><div class="gc-suggestions-label">Categories &amp; brands</div><ul role="list">';
                terms.forEach(function(t) {
                    html += '<li role="option"><a href="' + (t.url || '#') + '" class="gc-suggestion-item gc-suggestion-term" data-title="' + (t.name || '').replace(/"/g, '&quot;') + '">' + (t.name || '') + '</a></li>';
                });
                html += '</ul></div>';
            }
            $el.html(html).show();
        }

        function fetchSuggestions(q) {
            if (!q || q.length < suggestMinLen) {
                hideSuggestions();
                return;
            }
            var ajaxUrl = (typeof userBrandsData !== 'undefined' && userBrandsData.ajax_url) ? userBrandsData.ajax_url : '/wp-admin/admin-ajax.php';
            jQuery.get(ajaxUrl, { action: 'gc_search_suggestions', q: q }, function(res) {
                if (res && res.success && res.data) {
                    showSuggestions(res.data.products || [], res.data.terms || []);
                } else {
                    hideSuggestions();
                }
            }).fail(hideSuggestions);
        }

        jQuery(document).on('input', '#gc-search', function() {
            var q = jQuery(this).val().trim();
            clearTimeout(suggestTimeout);
            if (q.length < suggestMinLen) {
                hideSuggestions();
                return;
            }
            suggestTimeout = setTimeout(function() { fetchSuggestions(q); }, suggestDebounceMs);
        });

        jQuery(document).on('click', '#gc-search-suggestions .gc-suggestion-item', function(e) {
            e.preventDefault();
            var title = jQuery(this).data('title');
            if (title !== undefined && title !== '') {
                jQuery('#gc-search').val(title);
                hideSuggestions();
                loadProductsWithFilters(true);
            }
        });

        jQuery(document).on('focus', '#gc-search', function() {
            var q = jQuery(this).val().trim();
            if (q.length >= suggestMinLen) {
                fetchSuggestions(q);
            }
        });

        jQuery(document).on('click', function(e) {
            if (!jQuery(e.target).closest('.gc-search-wrap').length) {
                hideSuggestions();
            }
        });

        jQuery(document).on('keydown', '#gc-search', function(e) {
            if (e.key === 'Escape') {
                hideSuggestions();
            }
        });
    })();
    
    // Handle filter changes (delegated so it also works when the block is injected/rendered later, e.g. offers page)
    jQuery(document).on('change', '.gc-filter-select', function() {
        // Always reset to the first page when filters change
        jQuery('.load-more').attr('data-offset', 0);
        loadProductsWithFilters(true);
    });
    
    // Load more button handler
    jQuery('.load-more').on('click', function(e){
        e.preventDefault();

        let button  = jQuery(this);
        
        // Check if button is already disabled
        if(button.hasClass('disabled') || button.prop('disabled')) {
            return false;
        }
        
        // Refresh filters from DOM before loading more
        updateFiltersFromDOM();
        
        let offset  = parseInt(button.attr('data-offset'));
        let perpage = parseInt(button.attr('data-perpage'));
        let isWishlistPage = button.attr('data-wishlist-page') === '1';
        let isOffersPage   = button.attr('data-offers-page') === '1';
        let isCategoryPage = button.attr('data-category-page') === '1';
        let categoryId     = parseInt(button.attr('data-category-id'), 10) || 0;

        // Prepare filter data
        let filterData = prepareFilterData();

        $.ajax({
            url: userBrandsData.ajax_url,
            type: 'POST',
            dataType: 'json',
            traditional: true, // Use traditional serialization for arrays
            data: {
                action: 'gc_load_more',
                offset: offset,
                perpage: perpage,
                search: currentFilters.search,
                giftcards_for: filterData.giftcards_for,
                occasion: filterData.occasion,
                sort: currentFilters.sort,
                wishlist_page: isWishlistPage ? '1' : '0',
                offers_page: isOffersPage ? '1' : '0',
                category_page: isCategoryPage ? '1' : '0',
                category_id: categoryId
            },
            beforeSend: function(){
                button.text('Loading...');
            },
            success: function(response) {
                if(response.html && response.html.trim() !== '') {
                    jQuery('#gc-carousel .gc-slide').append(response.html);

                    // Increase offset for next request
                    button.attr('data-offset', offset + perpage);

                    // Check if there are more posts
                    if(response.has_more) {
                        button.text('Load more');
                    } else {
                        button.text('You`ve now viewed all our cards');
                        button.addClass('disabled');
                        button.prop('disabled', true);
                        button.css({
                            'pointer-events': 'none',
                            'opacity': '0.6',
                            'cursor': 'not-allowed'
                        });
                    }
                } else {
                    button.text('You`ve now viewed all our cards');
                    button.addClass('disabled');
                    button.prop('disabled', true);
                    button.css({
                        'pointer-events': 'none',
                        'opacity': '0.6',
                        'cursor': 'not-allowed'
                    });
                }
            },
            error: function() {
                button.text('Load more');
                alert('An error occurred. Please try again.');
            }
        });

    });


    //This code for the wishlist functionality
    var isBrandsPage = jQuery('body').hasClass('page-brands') || window.location.href.indexOf('/brands') !== -1;
    var isWishlistPage = window.location.href.indexOf('my-wishlist') !== -1;
    var isOfferPage = window.location.href.indexOf('offers') !== -1;
    
    // Only run wishlist functionality on brands page OR wishlist page
    if (!isBrandsPage && !isWishlistPage && !isOfferPage) {
        return; // Exit if not on brands page or wishlist page
    }
    

    // If wishlist page renders with no cards, ensure "Load more" is hidden.
    // (This can happen after dynamic removals or when the backend returns an empty state.)
    if (isWishlistPage) {
        var initialCards = jQuery('#gc-carousel .gc-card').length;
        if (initialCards === 0) {
            jQuery('.load-more').hide();
        }
    }
    // Add notification container to body if it doesn't exist
    if (jQuery('body').find('.gc-wishlist-notification').length === 0) {
        jQuery('body').append('<div class="gc-wishlist-notification"><div class="notification-content"><span class="notification-message"></span><button class="notification-close">&times;</button></div></div>');
    }
    
    // Function to show notification
    function showWishlistNotification(message, type) {
        var notification = jQuery('.gc-wishlist-notification');
        var messageSpan = notification.find('.notification-message');
        
        // Remove existing classes
        notification.removeClass('success error show hide');
        
        // Add new class and message
        notification.addClass(type + ' show');
        messageSpan.text(message);
        
        // Auto hide after 3 seconds
        setTimeout(function() {
            notification.addClass('hide');
            setTimeout(function() {
                notification.removeClass('show hide');
            }, 300);
        }, 3000);
    }
 
    
    // Close button handler
    jQuery(document).on('click', '.notification-close', function() {
        var notification = jQuery('.gc-wishlist-notification');
        notification.addClass('hide');
        setTimeout(function() {
            notification.removeClass('show hide');
        }, 300);
    });
    


    const brandsUrl = "<?php echo esc_url( site_url('/brands/') ); ?>";

    // Wishlist toggle handler
    jQuery(document).on('click', '.gc-wishlist-btn', function(e) {
        console.log('clicked');
        e.preventDefault();
        e.stopPropagation();
        
        let button = jQuery(this);
        let productId = button.data('product-id');
        
        if (!productId) {
            showWishlistNotification('Invalid product. Please try again.', 'error');
            return;
        }
        
        // Check if we're on the wishlist page
        let isWishlistPage = button.data('wishlist-page') === '1' || button.data('wishlist-page') === 1;
        // Fallback to URL check
        if (!isWishlistPage && window.location.href.indexOf('my-wishlist') !== -1) {
            isWishlistPage = true;
        }
        
        // Get AJAX URL
        let ajaxUrl = (typeof userBrandsData !== 'undefined' && userBrandsData.ajax_url) 
            ? userBrandsData.ajax_url 
            : '/wp-admin/admin-ajax.php';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'gc_toggle_wishlist',
                product_id: productId
            },
            beforeSend: function() {
                button.css('opacity', '0.5');
                button.prop('disabled', true);
            },
            success: function(response) {
                button.css('opacity', '1');
                button.prop('disabled', false);
                
                // Check if response is valid JSON
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch(e) {
                        showWishlistNotification('Invalid response from server.', 'error');
                        console.error('JSON Parse Error:', e);
                        return;
                    }
                }


                
                if (response.success) {
                    // If on wishlist page and product was removed, remove the card from DOM
                    if (isWishlistPage && response.data.action === 'removed') {
                        // Find the parent card and remove it
                        let productCard = button.closest('.gc-card');
                        if (productCard.length) {
                            // Add fade out animation
                            productCard.fadeOut(300, function() {
                                jQuery(this).remove();
                                
                                let remainingCards = jQuery('.gc-card').length;
                                let loadMoreBtn = jQuery('.load-more');

                                if (remainingCards === 0) {
                                    // Show empty wishlist message
                                    let emptyMessage = '<div style="text-align: center; padding: 40px; grid-column: 1 / -1;"><p style="font-size: 18px; color: #666; margin-bottom: 20px;">Your wishlist is feeling a little lonely! <br> Explore our gift cards and save the ones you love for later.</p><a href=" ' +  gcVars.brandsUrl + ' " class="button vc_general vc_btn3" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px;">Browse gift cards</a></div>';
                                    jQuery('.gc-slide').html(emptyMessage);
                                    loadMoreBtn.hide();
                                } else {
                                    // Refresh list from server so the product that was on page 2 appears (e.g. at position 12).
                                    // silentRefresh: do not replace content with Loading/error — keep current block if request fails
                                    loadProductsWithFilters(true, { silentRefresh: true });
                                }
                            });
                        }
                        showWishlistNotification('Product removed from wishlist.', 'success');
                    } else {
                        // Update button state (for brands page or when adding to wishlist)
                        if (response.data.is_in_wishlist) {
                            button.addClass('fill');
                            button.attr('title', 'Remove from wishlist');
                            showWishlistNotification('Product added to wishlist!', 'success');
                        } else {
                            button.removeClass('fill');
                            button.attr('title', 'Add to wishlist');
                            showWishlistNotification('Product removed from wishlist.', 'success');
                        }
                    }
                } else {
                    showWishlistNotification(response.data.message || 'An error occurred. Please try again.', 'error');
                }
            },
            error: function(xhr, status, error) {
                button.css('opacity', '1');
                button.prop('disabled', false);
                
                // Try to parse error response
                let errorMessage = 'Unable to update wishlist. Please try again.';
                if (xhr.responseText) {
                    // Check if response contains HTML (error page)
                    if (xhr.responseText.indexOf('<') !== -1) {
                        errorMessage = 'Server error occurred. Please check console for details.';
                        console.error('Server returned HTML instead of JSON:', xhr.responseText.substring(0, 200));
                    } else {
                        try {
                            let errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.data && errorResponse.data.message) {
                                errorMessage = errorResponse.data.message;
                            }
                        } catch(e) {
                            // Not JSON, use default message
                        }
                    }
                }
                
                showWishlistNotification(errorMessage, 'error');
                console.error('Wishlist AJAX error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText.substring(0, 500)
                });
            }
        });
    });
});