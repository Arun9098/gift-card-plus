<?php
function trending_now_owl_carousel_shortcode()
{
    ob_start();

    $is_brands_page = false;
    $is_category_page = false;
    $is_single_brand_page = false;
    $term = null;
    $category_id = null;
    $category_slug = null;
    $category_name = null;
    $category_desc = null;
    $thumb_id = null;
    $thumb_url = null;

    if (is_page('brands')) {
        $is_brands_page = true;
    }

    if (is_tax('product_brand')) {
        $is_single_brand_page = true;
    }
    // Check if we're on a product category page
    if (is_product_category()) {
        $is_category_page = true;
        $term = get_queried_object();

        if ($term && isset($term->taxonomy) && $term->taxonomy === 'product_cat') {
            $category_id = $term->term_id;
            $category_slug = $term->slug;
            $category_name = $term->name;
            $category_desc = term_description($category_id, 'product_cat');

            // Category thumbnail (used as hero image)
            $thumb_id = get_term_meta($category_id, 'thumbnail_id', true);
            $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';
        }
    }

    // Get current user's wishlist if logged in AND on brands page
    $user_wishlist = array();
    if ($is_brands_page) {
        $user_id = get_current_user_id();
        $user_wishlist = get_user_meta($user_id, 'user_wishlist', true);
        if (!is_array($user_wishlist)) {
            $user_wishlist = array();
        }
    }

    // ── Display mode set in admin: Products → Gift Cards Data → Trending Now tab ──
    $trending_mode = get_option('gc_trending_mode', 'our_selection');

    // ── Helper: build a category tax_query clause ──
    $category_tax_query = array();
    if ($is_category_page && $category_id) {
        $category_tax_query = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category_id,
                'operator' => 'IN',
            ),
        );
    }

    if ($trending_mode === 'personal_favourites') {

        // ── MODE: Their Personal Favourites ──────────────────────────────────
        // Show the current user's last-purchased products (up to 8, most recent
        // first). Falls back to site-wide best sellers for guests or users who
        // have never placed an order.

        $user_id          = get_current_user_id();
        $personal_ids     = array();

        if ($user_id) {
            // Fetch the 20 most-recent completed/processing orders for this user
            // and collect the product IDs they contain, preserving recency order.
            $recent_orders = wc_get_orders(array(
                'customer_id' => $user_id,
                'status'      => array('wc-completed', 'wc-processing'),
                'limit'       => 20,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'return'      => 'objects',
            ));

            $seen = array();
            foreach ($recent_orders as $order) {
                foreach ($order->get_items() as $item) {
                    $pid = (int) $item->get_product_id();
                    if ($pid && !isset($seen[$pid])) {
                        $seen[$pid]   = true;
                        $personal_ids[] = $pid;
                    }
                    if (count($personal_ids) >= 8) {
                        break 2; // enough products found
                    }
                }
            }
        }

        if (!empty($personal_ids)) {
            // User has purchase history — show those products
            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'post__in'       => $personal_ids,
                'orderby'        => 'post__in', // preserve recency order
            );
            if (!empty($category_tax_query)) {
                $args['tax_query'] = $category_tax_query;
            }
        } else {
            // Guest or user with no order history → fall back to best sellers
            // (ordered by total_sales meta, descending)
            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 8,
                'meta_key'       => 'total_sales',
                'orderby'        => 'meta_value_num',
                'order'          => 'DESC',
            );
            if (!empty($category_tax_query)) {
                $args['tax_query'] = $category_tax_query;
            }
        }

    } else {

        // ── MODE: Our Selection (admin-curated list) ──────────────────────────
        $trending_product_ids = get_option('gc_trending_products', array());
        $trending_product_ids = is_array($trending_product_ids)
            ? array_filter(array_map('intval', $trending_product_ids))
            : array();

        if (!empty($trending_product_ids)) {
            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'post__in'       => $trending_product_ids,
                'orderby'        => 'post__in',
            );
            if (!empty($category_tax_query)) {
                $args['tax_query'] = $category_tax_query;
            }
        } else {
            // No products selected in admin yet — default fallback
            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 8,
                'orderby'        => 'date',
                'order'          => 'ASC',
            );
            if (!empty($category_tax_query)) {
                $args['tax_query'] = $category_tax_query;
            }
        }
    }

    $query = new WP_Query($args);

    // Count total products
    $product_count = $query->post_count;

    // Check if we should skip carousel initialization (category page with only 1 product)
    $skip_carousel = ($is_category_page && $product_count === 1);

    if ($query->have_posts()) {
        // Use different class if skipping carousel
        $carousel_class = $skip_carousel ? 'trending-carousel-static' : 'owl-carousel owl-theme trending-carousel';
        echo '<div class="trending-grid">';
        // $slideIndex = 0;
        while ($query->have_posts()) {

            $query->the_post();
            global $product;
            $product_id = get_the_ID();

            $product_tags = get_the_terms($product_id, 'product_tag');
            $tag_names = [];

            if ($product_tags && !is_wp_error($product_tags)) {
                foreach ($product_tags as $tag) {
                    $tag_names[] = $tag->name;
                }
            }

            // Separate span per tag: 20% off = yellow (.product-tag--off), Hot offer = pink (.product-tag--offer)
            $tag_elements = array();
            foreach ($tag_names as $tag_name) {
                $tag_lower = strtolower(trim($tag_name));
                if (strpos($tag_lower, '20% off') !== false) {
                    $modifier = 'product-tag--off';
                } elseif (strpos($tag_lower, 'hot offer') !== false) {
                    $modifier = 'product-tag--offer';
                } else {
                    $modifier = 'product-tag--default';
                }
                // $tag_elements[] = '<span class="product-tag ' . esc_attr($modifier) . '">' . esc_html($tag_name) . '</span>';
                $tag_elements[] = '<span class="product-tag ' . esc_attr($modifier) . '" title="' . esc_attr($tag_name) . '">' . esc_html($tag_name) . '</span>';
            }

            // Split tags: first 3 visible, rest hidden behind ellipsis
            $visible_tags = array_slice($tag_elements, 0, 3);
            $remaining_tags = array_slice($tag_elements, 3);
            $tags_visible = implode('', $visible_tags);
            $tags_remaining = implode('', $remaining_tags);

            // Check if product is in wishlist (only if brands page)
            $is_in_wishlist = false;
            $wishlist_class = '';
            $heart_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

            if ($is_brands_page && is_user_logged_in()) {
                $is_in_wishlist = in_array($product_id, $user_wishlist);
                // Always use unfilled icon, add fill class when in wishlist
                $wishlist_class = $is_in_wishlist ? 'fill' : '';
            }
            ?>
            <div class="card gc-card">
                <a href="<?php echo esc_url(get_permalink($product_id)); ?>" class="product-card-link"
                    style="text-decoration: none; color: inherit; display: block;">
                    <div class="" style="position: relative;">
                        <?php if (has_post_thumbnail()): ?>
                            <div class="card-img gc-img">
                                <img src="<?php the_post_thumbnail_url('full'); ?>" alt="<?php the_title(); ?>">
                            </div>
                            <?php
                        endif; ?>

                        <h4 class="gc-title">
                            <?php the_title(); ?>
                        </h4>

                        <?php
                        $brand_tag_class = ($is_brands_page && is_user_logged_in()) ? 'product-tags-wrap--brands' : '';

                        // echo '<pre>';
                        // print_r($tag_elements);
                        // echo '</pre>';
                        // exit;
                        if (!empty($tag_elements)):
                            ?>
                            <p class="gc-product-tags product-tags-wrap <?php echo esc_attr($brand_tag_class); ?>">
                                <?php echo $tags_visible; ?>
                                <?php if (!empty($remaining_tags)): ?>
                                    <span class="product-tag product-tag--more" tabindex="0">
                                        &hellip;
                                    </span>
                                    <span class="product-tag-tooltip">
                                            <?php echo $tags_remaining; ?>
                                        </span>
                                <?php endif; ?>
                            </p>
                            <?php
                        endif; ?>

                        <?php
                            $product_icons = get_the_terms(get_the_ID(), 'icons');

                            if ($product_icons && !is_wp_error($product_icons)) :

                                // Prepare icon elements
                                $icon_elements = array();

                                foreach ($product_icons as $icon) {
                                    $icon_elements[] = '<span class="icon icon-' . esc_attr($icon->slug) . '" title="' . esc_attr($icon->name) . '">' . esc_html($icon->name) . '</span>';
                                }

                                // Split: first 2 visible, rest hidden
                                $visible_icons   = array_slice($icon_elements, 0, 2);
                                $remaining_icons = array_slice($icon_elements, 2);

                                $icons_visible   = implode('', $visible_icons);
                                $icons_remaining = implode('', $remaining_icons);
                                ?>
                                <p class="product-icons">
                                    <?php echo $icons_visible; ?>

                                    <?php if (!empty($remaining_icons)) : ?>
                                        <span class="icon icon--more" tabindex="0">
                                            &hellip;
                                        </span>
                                        <span class="icon-tooltip">
                                            <?php echo $icons_remaining; ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                        <?php endif; ?>    
                    </div>
                </a>
                <?php if ($is_brands_page && is_user_logged_in()): ?>
                    <button class="gc-wishlist-btn icon-brand-page bottom <?php echo $wishlist_class; ?>"
                        data-product-id="<?php echo $product_id; ?>"
                        title="<?php echo $is_in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                        <?php echo $heart_icon_svg; ?>
                    </button>
                    <?php
                endif; ?>
            </div>
            <?php

            // $slideIndex++;
        }
        echo '</div>';


        if ($is_single_brand_page): ?>
            <div class="action-block align-center"><button class="button btn size-lg see-all-btn">See all</button></div>
            <?php
        endif; ?>

        <?php
    } else {
        echo 'No cards found.';
    }

    wp_reset_postdata();
    ?>
    <script>
        // jQuery(document).ready(function ($) {

        //     setTimeout(function () {
        //         var el = jQuery('.trending-carousel');


        //         var productCount = <?php echo $product_count; ?>;
        //         var skipCarousel = <?php echo $skip_carousel ? 'true' : 'false'; ?>;

        //         if (!skipCarousel) {
        //             var carousel;
        //             var carouselOptions = {
        //                 margin: 20,
        //                 nav: true,
        //                 dots: true,
        //                 slideBy: 'page',
        //                 nav: productCount > 4,
        //                 navText: [
        //                     `<svg width="21" height="14" viewBox="0 0 21 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63335C1.89551 6.38219 1.99528 6.14133 2.17287 5.96373C2.35046 5.78614 2.59133 5.68637 2.84248 5.68637L19.888 5.68637C20.1391 5.68637 20.38 5.78614 20.5576 5.96373C20.7352 6.14132 20.835 6.38219 20.835 6.63334C20.835 6.88449 20.7352 7.12536 20.5576 7.30295C20.38 7.48054 20.1391 7.58031 19.888 7.58031L2.84248 7.58032C2.59133 7.58032 2.35046 7.48055 2.17287 7.30296C1.99528 7.12536 1.89551 6.8845 1.89551 6.63335Z" fill="black"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30046C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75456 1.44903e-06 6.63C1.42181e-06 6.50544 0.024575 6.38211 0.0723146 6.26706C0.120054 6.15201 0.190022 6.04751 0.27821 5.95955L5.96004 0.277714C6.13786 0.0998987 6.37903 3.15945e-06 6.6305 3.10449e-06C6.88197 3.04953e-06 7.12314 0.0998985 7.30095 0.277714C7.47877 0.45553 7.57867 0.6967 7.57867 0.948171C7.57867 1.19964 7.47877 1.44081 7.30095 1.61863L2.28768 6.63L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30046Z" fill="black"/>
        //                     </svg>`,
        //                     `<svg xmlns="http://www.w3.org/2000/svg" width="21" height="14" viewBox="0 0 21 14" fill="none">
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9395 6.62666C18.9395 6.87781 18.8397 7.11868 18.6621 7.29627C18.4845 7.47386 18.2436 7.57363 17.9925 7.57363L0.946985 7.57363C0.695833 7.57363 0.454966 7.47386 0.277374 7.29627C0.0997831 7.11868 1.35086e-05 6.87781 1.34756e-05 6.62666C1.34427e-05 6.37551 0.099783 6.13464 0.277374 5.95705C0.454966 5.77946 0.695833 5.67969 0.946985 5.67969L17.9925 5.67969C18.2436 5.67969 18.4845 5.77946 18.6621 5.95705C18.8397 6.13464 18.9395 6.37551 18.9395 6.62666Z" fill="black"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5568 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.835 6.50544 20.835 6.63C20.835 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5568 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5568 5.95954Z" fill="black"/>
        //                         </svg>`
        //                 ],
        //                 responsive: {
        //                     0: {
        //                         items: 1.3,
        //                     },
        //                     600: {
        //                         items: 2
        //                     },
        //                     991: {
        //                         items: 3
        //                     },
        //                     1024: {
        //                         items: 4
        //                     },

        //                 },
        //             };
        //             console.log('row slider')
        //         }


        //         //Taken from Owl Carousel so we calculate width the same way
        //         var viewport = function () {
        //             var width;
        //             if (carouselOptions.responsiveBaseElement && carouselOptions.responsiveBaseElement !== window) {
        //                 width = $(carouselOptions.responsiveBaseElement).width();
        //             } else if (window.innerWidth) {
        //                 width = window.innerWidth;
        //             } else if (document.documentElement && document.documentElement.clientWidth) {
        //                 width = document.documentElement.clientWidth;
        //             } else {
        //                 console.warn('Can not detect viewport width.');
        //             }
        //             return width;
        //         };

        //         var severalRows = false;
        //         var orderedBreakpoints = [];
        //         for (var breakpoint in carouselOptions.responsive) {
        //             if (carouselOptions.responsive[breakpoint].rows > 1) {
        //                 severalRows = true;
        //             }
        //             orderedBreakpoints.push(parseInt(breakpoint));
        //         }

        //         //Custom logic is active if carousel is set up to have more than one row for some given window width
        //         if (severalRows) {
        //             orderedBreakpoints.sort(function (a, b) {
        //                 return b - a;
        //             });
        //             var slides = el.find('[data-slide-index]');
        //             var slidesNb = slides.length;
        //             if (slidesNb > 0) {
        //                 var rowsNb;
        //                 var previousRowsNb = undefined;
        //                 var colsNb;
        //                 var previousColsNb = undefined;

        //                 //Calculates number of rows and cols based on current window width
        //                 var updateRowsColsNb = function () {
        //                     var width = viewport();
        //                     for (var i = 0; i < orderedBreakpoints.length; i++) {
        //                         var breakpoint = orderedBreakpoints[i];
        //                         if (width >= breakpoint || i == (orderedBreakpoints.length - 1)) {
        //                             var breakpointSettings = carouselOptions.responsive['' + breakpoint];
        //                             rowsNb = breakpointSettings.rows;
        //                             colsNb = breakpointSettings.items;
        //                             break;
        //                         }
        //                     }
        //                 };

        //                 var updateCarousel = function () {
        //                     updateRowsColsNb();

        //                     //Carousel is recalculated if and only if a change in number of columns/rows is requested
        //                     if (rowsNb != previousRowsNb || colsNb != previousColsNb) {
        //                         var reInit = false;
        //                         if (carousel) {
        //                             //Destroy existing carousel if any, and set html markup back to its initial state
        //                             carousel.trigger('destroy.owl.carousel');
        //                             carousel = undefined;
        //                             slides = el.find('[data-slide-index]').detach().appendTo(el);
        //                             el.find('.fake-col-wrapper').remove();
        //                             reInit = true;
        //                         }


        //                         //This is the only real 'smart' part of the algorithm

        //                         //First calculate the number of needed columns for the whole carousel
        //                         var perPage = rowsNb * colsNb;
        //                         var pageIndex = Math.floor(slidesNb / perPage);
        //                         var fakeColsNb = pageIndex * colsNb + (slidesNb >= (pageIndex * perPage + colsNb) ? colsNb : (slidesNb % colsNb));

        //                         //Then populate with needed html markup
        //                         var count = 0;
        //                         for (var i = 0; i < fakeColsNb; i++) {
        //                             //For each column, create a new wrapper div
        //                             var fakeCol = $('<div class="fake-col-wrapper"></div>').appendTo(el);
        //                             for (var j = 0; j < rowsNb; j++) {
        //                                 //For each row in said column, calculate which slide should be present
        //                                 var index = Math.floor(count / perPage) * perPage + (i % colsNb) + j * colsNb;
        //                                 if (index < slidesNb) {
        //                                     //If said slide exists, move it under wrapper div
        //                                     slides.filter('[data-slide-index=' + index + ']').detach().appendTo(fakeCol);
        //                                 }
        //                                 count++;
        //                             }
        //                         }
        //                         //end of 'smart' part

        //                         previousRowsNb = rowsNb;
        //                         previousColsNb = colsNb;

        //                         if (reInit) {
        //                             //re-init carousel with new markup
        //                             carousel = el.owlCarousel(carouselOptions);
        //                         }
        //                     }
        //                 };

        //                 //Trigger possible update when window size changes
        //                 jQuery(window).on('resize', updateCarousel);

        //                 //We need to execute the algorithm once before first init in any case
        //                 updateCarousel();
        //             }
        //         }

        //         //init
        //         carousel = el.owlCarousel(carouselOptions);
        //     }, 1000);
        // });


jQuery(document).ready(function ($) {

    /* =========================================
       MULTIPLE HORIZONTAL DRAG SCROLL SUPPORT
    ========================================= */

    $('.trending-grid').each(function () {

        const slider = this;

        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {

            isDown = true;

            slider.classList.add('dragging');

            startX = e.pageX - slider.offsetLeft;

            scrollLeft = slider.scrollLeft;
        });

        slider.addEventListener('mouseleave', () => {

            isDown = false;

            slider.classList.remove('dragging');
        });

        slider.addEventListener('mouseup', () => {

            isDown = false;

            slider.classList.remove('dragging');
        });

        slider.addEventListener('mousemove', (e) => {

            if (!isDown) return;

            e.preventDefault();

            const x = e.pageX - slider.offsetLeft;

            const walk = (x - startX) * 2;

            slider.scrollLeft = scrollLeft - walk;
        });

    });


    /* =========================================
       PRODUCT TAG TOOLTIP
    ========================================= */

    $('.product-tag--more').each(function () {

        const $moreBtn = $(this);

        const $tooltip = $moreBtn.next('.product-tag-tooltip');

        if (!$tooltip.length) return;

        function updateTooltipPosition() {

            const rect = $moreBtn[0].getBoundingClientRect();

            const tooltipWidth = $tooltip.outerWidth();

            let left = rect.left;

            let top = rect.bottom + 12;

            if (left + tooltipWidth > window.innerWidth - 20) {

                left = window.innerWidth - tooltipWidth - 20;
            }

            $tooltip.css({
                position: 'fixed',
                top: top + 'px',
                left: left + 'px',
                zIndex: 999999
            });
        }

        $moreBtn.on('mouseenter focus', function () {

            updateTooltipPosition();

            $tooltip.stop(true, true).fadeIn(150);
        });

        $moreBtn.on('mouseleave blur', function () {

            setTimeout(function () {

                if (!$tooltip.is(':hover')) {

                    $tooltip.stop(true, true).fadeOut(150);
                }

            }, 100);

        });

        $tooltip.on('mouseleave', function () {

            $tooltip.stop(true, true).fadeOut(150);
        });

        $(window).on('resize scroll', updateTooltipPosition);

    });


    /* =========================================
       ICON TOOLTIP
    ========================================= */

    $('.icon--more').each(function () {

        const $moreBtn = $(this);

        const $tooltip = $moreBtn.next('.icon-tooltip');

        if (!$tooltip.length) return;

        function updateTooltipPosition() {

            const rect = $moreBtn[0].getBoundingClientRect();

            const tooltipWidth = $tooltip.outerWidth();

            let left = rect.left;

            let top = rect.bottom + 12;

            if (left + tooltipWidth > window.innerWidth - 20) {

                left = window.innerWidth - tooltipWidth - 20;
            }

            $tooltip.css({
                position: 'fixed',
                top: top + 'px',
                left: left + 'px',
                zIndex: 999999
            });
        }

        $moreBtn.on('mouseenter focus', function () {

            updateTooltipPosition();

            $tooltip.stop(true, true).fadeIn(150);
        });

        $moreBtn.on('mouseleave blur', function () {

            setTimeout(function () {

                if (!$tooltip.is(':hover')) {

                    $tooltip.stop(true, true).fadeOut(150);
                }

            }, 100);

        });

        $tooltip.on('mouseleave', function () {

            $tooltip.stop(true, true).fadeOut(150);
        });

        $(window).on('resize scroll', updateTooltipPosition);

    });

});
    </script>
    
    <?php

    return ob_get_clean();
}
add_shortcode('trending_now_carousel', 'trending_now_owl_carousel_shortcode');
?>