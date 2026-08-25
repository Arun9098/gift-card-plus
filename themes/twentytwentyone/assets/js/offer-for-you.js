
jQuery(document).ready(function ($) {

    if (typeof bhnOffer === 'undefined') return;

    $(document).on('click', '.gcpw-see-code', function () {

        var $btn = $(this);
        var type = $btn.data('showcase-type');
        var value = $btn.data('showcase');

        var originalText = $btn.text();

        var $popup = $('#offer-popup');
        var $text = $('#popup-content-text');
        var $copyBtn = $('#copy-btn');

        // RESET
        $text.html('');
        $copyBtn.hide();

        // ================================
        // ✅ CASE 1: COPY LINK DIRECTLY
        // ================================
        if (type === 'link') {

            var offerTitle = $btn.closest('.offer-card').find('.offer-title').text().trim();

            navigator.clipboard.writeText(value).then(function () {

                // Change button text feedback
                $btn.text('Copied');
                setTimeout(function () {
                    $btn.text(originalText);
                }, 1500);

                // Send email via AJAX
                $.post(
                    bhnOffer.ajaxurl,
                    {
                        action: 'bhn_send_offer_link_email',
                        nonce: bhnOffer.nonce,
                        link: value,
                        offer_title: offerTitle,
                    },
                    function (response) {
                        if (response.success) {
                            var $successMsg = $('<div class="gcpw-email-success-msg" style="display:none;color:#22a722;font-size:13px;margin-bottom:6px;">Link sent to your email!</div>');
                            $btn.before($successMsg);
                            $successMsg.fadeIn(300);
                            setTimeout(function () {
                                $successMsg.fadeOut(300, function () {
                                    $(this).remove();
                                });
                            }, 5000);
                        } else {
                            console.warn('Email failed:', response.data);
                        }
                    }
                );
            });

            return;
        }

        // ================================
        // ✅ CASE 2: PROMO CODE → POPUP
        // ================================
        if (type === 'promo_code' || type === 'copy') {

            $('#popup-title').text('Promo Code');

            $text.html('<strong>' + value + '</strong>');
            $copyBtn.show().data('copy', value);

            $popup.fadeIn();
        }

        // ================================
        // ✅ OTHER CASE
        // ================================
        else {

            $('#popup-title').text('Offer Details');
            $text.html(value);
            $popup.fadeIn();
        }
    });

    // Close popup (X button)
    $(document).on('click', '.close-popup', function () {
        $('#offer-popup').fadeOut();
    });


    // Close popup on outside click
    $(document).on('click', '#offer-popup', function (e) {
        if ($(e.target).is('#offer-popup')) {
            $(this).fadeOut();
        }
    });


    // Copy button
    $(document).on('click', '#copy-btn', function () {
        var text = $(this).data('copy');
        var $msg = $('#copy-message');
        var $btn = $(this);

        navigator.clipboard.writeText(text).then(function () {

            $msg.text('Copied to clipboard!').fadeIn();

            // Optional button feedback
            $btn.text('Copied');

            setTimeout(function () {
                $msg.fadeOut();
                $btn.text('Copy');
            }, 2000);

        });
    });
    var carouselId = bhnOffer.carouselId;
    var offerCount = parseInt(bhnOffer.offerCount);

    var $carousel = $('#' + carouselId);
    var carouselItems = parseInt(bhnOffer.carouselItems);

    var shouldLoop = offerCount > carouselItems;

    // Store original items HTML BEFORE carousel initialization
    var originalItems = $carousel.html();

    // Function to initialize carousel
    function initCarousel() {
        if (typeof $.fn.owlCarousel === 'undefined') {
            console.log('Waiting for Owl Carousel to load...');
            setTimeout(initCarousel, 100);
            return;
        }

        if ($carousel.length && $carousel.find('.item').length > 0) {
            // Check if already initialized
            if ($carousel.hasClass('owl-loaded')) {
                return;
            }

            $carousel.owlCarousel({
                items: carouselItems,
                loop: shouldLoop,
                margin: 20,
                dots: false,
                nav: false,
                autoplay: bhnOffer.autoplay === 'true',
                autoplayTimeout: 3000,
                autoplayHoverPause: true,
                responsive: {
                    0: {
                        items: 1
                    },
                    600: {
                        items: 2
                    },
                    768: {
                        items: 2
                    },
                    992: {
                        items: 3
                    },
                    1200: {
                        items: carouselItems
                    }
                }
            });

            // Custom navigation buttons
            $('#offers-prev-btn-' + carouselId).off('click').on('click', function (e) {
                e.preventDefault();
                $carousel.trigger('prev.owl.carousel');
            });

            $('#offers-next-btn-' + carouselId).off('click').on('click', function (e) {
                e.preventDefault();
                $carousel.trigger('next.owl.carousel');
            });
        }
    }

    // Function to filter and rebuild carousel
    function filterCarousel(searchTerm) {
        if (!$carousel.length) return;

        var searchLower = searchTerm.toLowerCase().trim();
        var filteredItems = '';

        // Parse original items to filter
        var $tempContainer = $('<div>').html(originalItems);

        if (searchLower === '') {
            // Restore original items
            filteredItems = originalItems;
        } else {
            // Filter items based on search term
            $tempContainer.find('.item').each(function () {
                var $item = $(this);
                // Get title from visible text (h3.offer-title) - this is more reliable
                var offerTitle = ($item.find('.offer-title').text() || '').toLowerCase().trim();
                // Also try to get from data attribute as fallback
                var offerTitleData = $item.find('.offer-card').attr('data-offer-title') || '';
                if (offerTitleData) {
                    offerTitle = offerTitleData.toLowerCase();
                }

                var offerDescription = ($item.find('.offer-description').text() || '').toLowerCase();
                var offerTag = ($item.find('.offer-tag').text() || '').toLowerCase();

                // Check if search term matches title, description, or tag
                if ((offerTitle && offerTitle.indexOf(searchLower) !== -1) ||
                    (offerDescription && offerDescription.indexOf(searchLower) !== -1) ||
                    (offerTag && offerTag.indexOf(searchLower) !== -1)) {
                    filteredItems += this.outerHTML;
                }
            });
        }

        // Destroy existing carousel if initialized
        if ($carousel.hasClass('owl-loaded')) {
            $carousel.trigger('destroy.owl.carousel');
            $carousel.removeClass('owl-loaded');
        }

        // Update carousel with filtered items
        if (filteredItems) {
            $carousel.html(filteredItems);

            // Reinitialize carousel with filtered/restored items
            var filteredCount = $carousel.find('.item').length;
            if (filteredCount > 0) {
                // Update loop setting based on filtered count
                shouldLoop = filteredCount > carouselItems;
                initCarousel();
            } else {
                // Show message if no results
                $carousel.html('<div class="item"><div class="offer-card"><p style="text-align: center; padding: 20px;">No offers found matching your search.</p></div></div>');
            }
        }
    }

    // Initialize on document ready
    initCarousel();

    // Also try on window load as fallback
    $(window).on('load', function () {
        if (!$carousel.hasClass('owl-loaded')) {
            initCarousel();
        }
    });

    // Search functionality with debounce
    var $searchInput = $('#offers-search-input-' + carouselId);
    var $clearBtn = $('#offers-search-clear-' + carouselId);
    var $searchIcon = $searchInput.closest('.offers-search').find('.search-icon');
    var searchTimeout;

    function toggleClearButton() {
        if ($searchInput.val().trim() !== '') {
            $clearBtn.show();
            $searchIcon.hide();
        } else {
            $clearBtn.hide();
            $searchIcon.show();
        }
    }

    $searchInput.on('keyup input', function () {
        var searchTerm = $(this).val();
        toggleClearButton();

        // Clear previous timeout
        clearTimeout(searchTimeout);

        // Debounce search to avoid too many reinitializations
        searchTimeout = setTimeout(function () {
            filterCarousel(searchTerm);
        }, 300);
    });

    // Clear search button click
    $clearBtn.on('click', function () {
        $searchInput.val('');
        $clearBtn.hide();
        filterCarousel('');
        $searchInput.focus();
    });

    // Clear search on escape key
    $searchInput.on('keydown', function (e) {
        if (e.key === 'Escape') {
            $(this).val('');
            $clearBtn.hide();
            filterCarousel('');
        }
    });

    toggleClearButton();
});
