<?php
function gc_products_shortcode($atts = array())
{
    // Shortcode attributes with default posts_per_page
    $atts = shortcode_atts(
        array(
            'posts_per_page'   => 16,  // default value if not provided in shortcode
            'gc-giftcards-for' => '0', // do not show "gift cards for" filter by default
            'gc-occasion'      => '0', // do not show "occasion" filter by default
            'gc-sort'          => '0', // do not show "sort" filter by default
            'gc-filter-by'     => '0', // do not show "sort" filter by default
            'category_id'      => '0', // show products from a specific category (term_id)
        ),
        $atts,
        'gc_products'
    );

    // Sanitize and normalize posts_per_page
    $post_per_page = intval($atts['posts_per_page']);
    if ($post_per_page <= 0) {
        $post_per_page = 16;
    }

    // Visibility flags for filters from shortcode attributes
    $show_gc_giftcards_for  = !empty($atts['gc-giftcards-for']) && $atts['gc-giftcards-for'] === '1';
    $show_gc_occasion       = !empty($atts['gc-occasion'])      && $atts['gc-occasion']      === '1';
    $show_gc_sort           = !empty($atts['gc-sort'])          && $atts['gc-sort']          === '1';
    $show_gc_filter_by      = !empty($atts['gc-filter-by'])     && $atts['gc-filter-by']     === '1';
    $shortcode_category_id  = intval($atts['category_id']);

    ob_start(); ?>

    <?php
    $is_brands_page = false;
    $is_wishlist_page = false;
    $is_category_page = false;
    $is_single_brand_page = false;
    $is_home_page = false;
    $is_offers_page = false;
    $wishlist_product_ids = array();

    if (is_page('brands')) {
        $is_brands_page = true;
    }

    // Check if we're on the home page
    if (is_front_page() || is_home()) {
        $is_home_page = true;
    }

    if (is_product_category()) {
        $is_category_page = true;
    }

    if (is_tax('product_brand')) {
        $is_single_brand_page = true;
    }
    // Check if we're on the My Wishlist page
    global $wp;
    if ((is_account_page() && isset($wp->query_vars['my-wishlist']))) {
        $is_wishlist_page = true;
        // Get current user's wishlist
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $wishlist = get_user_meta($user_id, 'user_wishlist', true);

            if (is_array($wishlist)) {
                $wishlist_product_ids = array_filter(array_map('intval', $wishlist));
            }
        }
    }

    if (is_page('offers')) {
        $is_offers_page = true;
        // Get current user's wishlist
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $wishlist = get_user_meta($user_id, 'user_wishlist', true);

            if (is_array($wishlist)) {
                $wishlist_product_ids = array_filter(array_map('intval', $wishlist));
            }
        }
    }

    // If on home page and user is logged in, don't display this section
    // Show it for logged out users on all pages
    if ($is_home_page && is_user_logged_in()) {
        return '';
    }
    ?>

    <?php if ($is_wishlist_page): ?>

        <section class="my-wishlist-wrapper">
            <div class="banner-left">
                <h2 class="h1">Make their everyday great</h2>
            </div>

            <div class="hero-image banner-right">
                <img src="<?php echo site_url(); ?>/wp-content/uploads/2025/12/Frame-48.png" alt="Gift Envelope Image">
            </div>
        </section>
        <h2 class="my-wishlist-page-title">
            <span class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 52 52" fill="none">
                    <g clip-path="url(#clip0_8003_8043)">
                        <path
                            d="M28.9251 43.6164C27.2784 45.1114 24.7434 45.1114 23.0968 43.5947L22.8584 43.3781C11.4834 33.0864 4.05175 26.3481 4.33342 17.9414C4.46342 14.2581 6.34842 10.7264 9.40342 8.64642C15.1234 4.74642 22.1868 6.56642 26.0001 11.0298C29.8134 6.56642 36.8768 4.72475 42.5967 8.64642C45.6517 10.7264 47.5368 14.2581 47.6667 17.9414C47.9701 26.3481 40.5168 33.0864 29.1418 43.4214L28.9251 43.6164Z"
                            fill="#ED018C" />
                    </g>
                    <defs>
                        <clipPath id="clip0_8003_8043">
                            <rect width="52" height="52" fill="white" />
                        </clipPath>
                    </defs>
                </svg>
            </span>
            My Wishlist
        </h2>
        <h4 class="my-wishlist-page-subtitle">All Cards</h4>

    <?php endif; ?>
    <?php if ($is_category_page): ?>
        <h4 class="cat-single-page-subtitle">All Cards</h4>
    <?php endif; ?>
    <?php
    $section_classes = [];

    if ($is_category_page) {
        $section_classes[] = 'category-page-gc';
    }

    if ($is_single_brand_page) {
        $section_classes[] = 'single-brand-page-gc';
    }
    ?>
    <?php if ($is_offers_page): ?>
        <h4 class="offer-single-page-subtitle">All Plus Offers</h4>
    <?php endif; ?>

    <?php
    $current_category_id = 0;
    if ($is_category_page) {
        $queried = get_queried_object();
        if ($queried && !is_wp_error($queried)) {
            $current_category_id = $queried->term_id;
        }
    }
    ?>
    <section id="gc-section" class="gc-section <?php echo esc_attr(implode(' ', $section_classes)); ?>"
        data-gc-wishlist-page="<?php echo $is_wishlist_page ? '1' : '0'; ?>"
        data-gc-offers-page="<?php echo $is_offers_page ? '1' : '0'; ?>"
        data-gc-category-page="<?php echo $is_category_page ? '1' : '0'; ?>"
        data-gc-category-id="<?php echo (int) $current_category_id; ?>"
        data-gc-brands-page="<?php echo $is_brands_page ? '1' : '0'; ?>"
        data-gc-perpage="<?php echo (int) $post_per_page; ?>">
        <?php if ($is_brands_page): ?>
            <div class="brand-page-search-wrapper">
            <?php endif; ?>

            <?php if ($is_wishlist_page || $is_offers_page): ?>
                <div class="wishlist-page-gc-wrapper">
                <?php endif; ?>
                <div class="brand-page-search-wrapper">
                    <?php if ($is_single_brand_page): ?>
                        <div class="brand-page-search-wrapper">
                        <?php endif; ?>
                        <div class="search gc-search-wrap">
                        <span class="fake-placeholder">Search <strong>giftcards</strong><em>plus</em> cards</span>  
                        <input type="text" class="egift-search <?php echo $is_brands_page ? 'brand-page-search' : 'search-product'; ?> <?php echo $is_wishlist_page ? 'wishlist-page-search' : 'search-product'; ?> " id="gc-search" autocomplete="off" placeholder=" " aria-label="Search giftcardsplus cards by name, brand, tag, description, denomination or category">
                                <span class="clear-search" id="egift-clear-search">×</span>
                            <div id="gc-search-suggestions" class="gc-search-suggestions" style="display:none;" role="listbox" aria-label="Search suggestions"></div>
                            <button type="submit" class="search-submit">
                                <span class="search-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none">
                                        <path
                                            d="M21.8189 23.6359L16.6812 18.4895C15.8734 19.1907 14.9309 19.7369 13.8539 20.128C12.7768 20.5191 11.6324 20.7147 10.4207 20.7147C7.51259 20.7147 5.04878 19.7032 3.02927 17.6803C1.00976 15.6574 0 13.2164 0 10.3573C0 7.49828 1.00976 5.05729 3.02927 3.03438C5.04878 1.01146 7.49913 0 10.3803 0C13.2345 0 15.6647 1.01146 17.6707 3.03438C19.6768 5.05729 20.6798 7.49828 20.6798 10.3573C20.6798 11.5171 20.4913 12.6365 20.1143 13.7154C19.7374 14.7943 19.1719 15.8057 18.418 16.7498L23.6365 21.8962C23.8788 22.1119 24 22.3884 24 22.7256C24 23.0627 23.8654 23.3662 23.5961 23.6359C23.3538 23.8786 23.0576 24 22.7075 24C22.3575 24 22.0613 23.8786 21.8189 23.6359ZM10.3803 18.2872C12.5614 18.2872 14.4193 17.5117 15.9542 15.9608C17.489 14.4099 18.2564 12.5421 18.2564 10.3573C18.2564 8.17259 17.489 6.30476 15.9542 4.75386C14.4193 3.20295 12.5614 2.4275 10.3803 2.4275C8.1723 2.4275 6.29415 3.20295 4.74586 4.75386C3.19756 6.30476 2.42342 8.17259 2.42342 10.3573C2.42342 12.5421 3.19756 14.4099 4.74586 15.9608C6.29415 17.5117 8.1723 18.2872 10.3803 18.2872Z"
                                            fill="#202224"></path>
                                    </svg>
                                </span>
                            </button>

                        </div>
                        <?php
                        if ($is_brands_page || $is_wishlist_page || $is_category_page || $is_single_brand_page || $is_offers_page): ?>

                            <div class="gc-filters">
                                <?php
                                // Fetch all product tags - use ordered function for gc-giftcards-for
                                $all_tags = get_terms(array(
                                    'taxonomy' => 'product_tag',
                                    'hide_empty' => false,
                                    'orderby' => 'name',
                                    'order' => 'ASC'
                                ));

                                // Get ordered tags for gc-giftcards-for dropdown
                                $ordered_tags = function_exists('gc_get_ordered_giftcards_for_tags') 
                                    ? gc_get_ordered_giftcards_for_tags() 
                                    : $all_tags;
                                
                                // Get ordered tags for gc-occasion dropdown
                                $ordered_occasion_tags = function_exists('gc_get_ordered_occasion_tags') 
                                    ? gc_get_ordered_occasion_tags() 
                                    : $all_tags;
                                // Check if we got tags successfully
                                if (!empty($all_tags) && !is_wp_error($all_tags)):
                                    ?>

                                    <?php if ($show_gc_giftcards_for): ?>
                                        <select class="gc-filter-select" id="gc-giftcards-for">
                                            <option value="">Gift cards for</option>
                                            <?php foreach ($ordered_tags as $tag): ?>
                                                <option value="<?php echo esc_attr($tag->slug); ?>"><?php echo esc_html($tag->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>

                                    <?php if ($show_gc_occasion): ?>
                                        <select class="gc-filter-select" id="gc-occasion">
                                            <option value="">Occasion</option>
                                            <?php foreach ($ordered_occasion_tags as $tag): ?>
                                                <option value="<?php echo esc_attr($tag->slug); ?>"><?php echo esc_html($tag->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>

                                <?php else: ?>
                                <?php endif; ?>

                                <?php if ($show_gc_sort): ?>
                                    <select class="gc-filter-select" id="gc-sort">
                                        <option value="" selected>Sort by</option>
                                        <option value="ranked">Ranked</option>
                                        <option value="most_popular">Most popular</option>
                                        <option value="a_z">A-Z</option>
                                        <option value="z_a">Z-A</option>
                                        <option value="price_low_high">Price low to high</option>
                                        <option value="price_high_low">Price high to low</option>
                                        <option value="most_viewed">Most viewed</option>
                                    </select>
                                <?php endif; ?>

                                <?php if ($show_gc_filter_by): ?>
                                    <select class="gc-filter-select" id="gc-filter-by">
                                        <option value="">Filter by</option>
                                        <option value="newest">Newest</option>
                                        <option value="bestselling">Best Selling</option>
                                        <option value="price_low_high">Price: Low → High</option>
                                        <option value="price_high_low">Price: High → Low</option>
                                    </select>
                                <?php endif; ?>

                            </div>

                            <?php if ($is_single_brand_page): ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_wishlist_page || $is_offers_page): ?>
                    </div> <!-- END .brand-page-search-wrapper -->
                <?php endif; ?>

                <?php if ($is_brands_page): ?>
                </div> <!-- END .brand-page-search-wrapper -->
            <?php endif; ?>
            <?php
                        endif; ?>


        <?php if ($is_wishlist_page || $is_offers_page): ?>
            <div class="wishlist-card-wrapper">
            <?php endif; ?>
            <div id="gc-carousel" class="gc-carousel">

                <?php
                $args = array(
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'orderby' => 'menu_order',
                    'order' => 'ASC', // default: ranked
                    'posts_per_page' => $post_per_page
                );

                // Resolve which term_id to use for category filtering
                $active_category_term_id = 0;
                if ($is_category_page) {
                    $current_category = get_queried_object();
                    if ($current_category && !is_wp_error($current_category)) {
                        $active_category_term_id = (int) $current_category->term_id;
                    }
                } elseif ($shortcode_category_id > 0) {
                    $active_category_term_id = $shortcode_category_id;
                }

                if ($active_category_term_id > 0) {
                    // ACF stores repeater rows in term meta as:
                    //   sku_assigned_arr                   = "4"  (row count)
                    //   sku_assigned_arr_0_assigned_product = ID
                    //   sku_assigned_arr_1_assigned_product = ID  ... etc.
                    // get_field() only returns "4" here (raw count) because ACF field group
                    // may not be loaded on frontend — read term meta directly instead.
                    $row_count   = (int) get_term_meta($active_category_term_id, 'sku_assigned_arr', true);
                    $ordered_ids = [];

                    for ($i = 0; $i < $row_count; $i++) {
                        $pid = (int) get_term_meta(
                            $active_category_term_id,
                            "sku_assigned_arr_{$i}_assigned_product",
                            true
                        );
                        if ($pid > 0) {
                            $ordered_ids[] = $pid;
                        }
                    }

                    if (!empty($ordered_ids)) {
                        // post__in + orderby=post__in preserves the exact ACF-saved order
                        $args['post__in']       = $ordered_ids;
                        $args['orderby']        = 'post__in';
                        $args['posts_per_page'] = count($ordered_ids);
                        unset($args['order']);
                    } else {
                        // No assigned products — fall back to filtering by taxonomy
                        $args['tax_query'] = array(
                            array(
                                'taxonomy' => 'product_cat',
                                'field'    => 'term_id',
                                'terms'    => $active_category_term_id,
                            ),
                        );
                    }
                }

                // If we're on a single brand archive page, limit products to that brand
                if ($is_single_brand_page) {
                    $current_brand = get_queried_object();
                    if ($current_brand && !is_wp_error($current_brand)) {
                        $args['tax_query'] = array(
                            array(
                                'taxonomy' => 'product_brand',
                                'field' => 'term_id',
                                'terms' => $current_brand->term_id,
                            ),
                        );
                    }
                }

                // If on wishlist page, only show wishlist products
                if ($is_wishlist_page && !empty($wishlist_product_ids)) {
                    $args['post__in'] = $wishlist_product_ids;
                    $args['orderby'] = 'post__in'; // Maintain wishlist order
                } else if ($is_wishlist_page && empty($wishlist_product_ids)) {
                    // If wishlist is empty, show no products
                    $args['post__in'] = array(0); // Force no results
                }
                // OFFERS PAGE → fetch products with specific tags
                if ($is_offers_page) {
                    $args['tax_query'][] = array(
                        'taxonomy' => 'product_tag',
                        'field' => 'slug',
                        'terms' => array('20-off', 'hot-offer'),
                        'operator' => 'IN',
                    );
                }
                $loop = new WP_Query($args);

                echo '<div class="gc-slide">';

                if ($loop->have_posts()) {
                    while ($loop->have_posts()) {
                        $loop->the_post();
                        global $product;
                        $tag_data = gc_get_product_tags(get_the_ID());
                        $product_id = get_the_ID();

                        // Get wishlist status - ONLY for wishlist page (NOT brands page)
                        $wishlist_class = '';
                        $wishlist_title = 'Add to wishlist';
                        $heart_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

                        // Get user's wishlist if logged in and on wishlist page
                        if (($is_wishlist_page && is_user_logged_in()) || $is_offers_page) {
                            $user_id = get_current_user_id();
                            $user_wishlist = get_user_meta($user_id, 'user_wishlist', true);

                            if (!is_array($user_wishlist)) {
                                $user_wishlist = array();
                            }
                            $user_wishlist = array_filter(array_map('intval', $user_wishlist));

                            // Check if product is in wishlist
                            $is_in_wishlist = in_array($product_id, $user_wishlist);

                            // Show wishlist icon ONLY on wishlist page (NOT brands page)
                            // Always use unfilled icon, add fill class when in wishlist
                            if ($is_in_wishlist) {
                                $wishlist_class = 'fill';
                                $wishlist_title = 'Remove from wishlist';
                            } else {
                                $wishlist_class = '';
                                $wishlist_title = 'Add to wishlist';
                            }
                        }

                        echo '<a href="' . esc_url(get_permalink($product_id)) . '"  class="product-card-link" style="text-decoration:none;color:inherit;display:block;">';

                        echo '<div class="gc-card" style="position:relative;">';

                        echo '<div class="gc-img">' . $product->get_image('full') . '</div>';
                        echo '<p class="gc-title">' . esc_html(get_the_title()) . '</p>';

                        // Tags: separate pill per tag (20% off = yellow, Hot offer = pink), parent class gc-product-tags
                         if ($is_brands_page || $is_offers_page || $is_home_page) {
                            $tags = wp_get_post_terms($product_id, 'product_tag');
                            $tag_elements = array();
                            $hidden_tags = array();

                            if ($tags && !is_wp_error($tags)) {
                                foreach ($tags as $index => $t) {
                                    $tag_lower = strtolower(trim($t->name));

                                    $modifier = (strpos($tag_lower, '20% off') !== false)
                                        ? 'product-tag--off'
                                        : ((strpos($tag_lower, 'hot offer') !== false)
                                            ? 'product-tag--offer'
                                            : 'product-tag--default');

                                    $tag_html = '<span class="product-tag ' . esc_attr($modifier) . '">' . esc_html($t->name) . '</span>';

                                    if ($index < 3) {
                                        $tag_elements[] = $tag_html; // first 3 visible
                                    } else {
                                        $hidden_tags[] = $tag_html; // rest hidden
                                    }
                                }
                            }

                            if (!empty($tag_elements)) {
                                echo '<p class="gc-product-tags product-tags-wrap product-tags-wrap--brands">';

                                // Show first 3 tags
                                echo implode('', $tag_elements);

                                // If more than 3, add ellipsis
                                if (!empty($hidden_tags)) {
                                    echo '<span class="product-tag-more">...</span><span class="product-tag-hidden">' . implode('', $hidden_tags) . '</span>';
                                }

                                echo '</p>';
                            }
                         }

                        // Wishlist button
                        if (($is_wishlist_page && is_user_logged_in()) || $is_offers_page) {
                            echo '<button 
                                    class="gc-wishlist-btn icon-wishlist-page ' . esc_attr($wishlist_class) . '"
                                    data-product-id="' . esc_attr($product_id) . '"
                                    title="' . esc_attr($wishlist_title) . '">
                                    ' . $heart_icon_svg . '
                                </button>';
                        }

                        echo '</div></a>';
                    }
                } else {
                    if ($is_wishlist_page) {
                        echo '<div style="text-align: center; padding: 40px; grid-column: 1 / -1;">
                                <p style="font-size: 18px; color: #666; margin-bottom: 20px;">Your wishlist is feeling a little lonely! <br> Explore our gift cards and save the ones you love for later.</p>
                                <a href="' . esc_url(site_url('/brands/')) . '" class="button vc_general vc_btn3" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px;">Browse gift cards</a>
                            </div>';
                    } else {
                        echo '<div class="gc-no-products" style="text-align:center; padding: 40px; grid-column: 1 / -1;">
                                <p style="font-size: 18px; color: #666;">No Products Found</p>
                            </div>';
                    }
                }

                echo '</div>';

                wp_reset_postdata();
                ?>
            </div>
            <?php if ($is_wishlist_page || $is_offers_page): ?>
            </div>
        <?php endif; ?>
        <?php
        // Show "Load more" only when there are additional pages of results.
        // This prevents the button from appearing on empty wishlist/no-results views.
        $is_listing_context = ($is_brands_page || $is_wishlist_page || $is_offers_page || $is_category_page || $is_single_brand_page);
        $should_show_load_more = false;
        if ($is_listing_context) {
            $should_show_load_more = !empty($loop) && ($loop instanceof WP_Query) && ($loop->max_num_pages > 1);
        }
        ?>
        <?php if ($should_show_load_more || !$is_listing_context): ?>
            <div class="action-block align-center">
                <?php if ($should_show_load_more) { ?>
                    <a href="#" class="btn btn-primary size-lg load-more btn-black-p2" data-offset="<?php echo $post_per_page; ?>"
                        data-perpage="<?php echo $post_per_page; ?>"
                        data-wishlist-page="<?php echo ($is_wishlist_page) ? '1' : '0'; ?>"
                        data-offers-page="<?php echo $is_offers_page ? '1' : '0'; ?>"
                        data-category-page="<?php echo $is_category_page ? '1' : '0'; ?>"
                        data-category-id="<?php echo (int) $current_category_id; ?>"
                        data-brands-page="<?php echo $is_brands_page ? '1' : '0'; ?>"> Load more </a>
                <?php } else { ?>
                    <!-- <a href="<?php //echo esc_url(site_url('/brands')); ?>" class="btn btn-primary size-lg">Shop Gift Cards</a> -->
                <?php } ?>
            </div>
        <?php endif; ?>

    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('gc_products', 'gc_products_shortcode');

function gc_get_product_tags($product_id)
{
    $tags = wp_get_post_terms($product_id, 'product_tag');

    if (empty($tags) || is_wp_error($tags)) {
        return [
            'names' => '',
            'classes' => ''
        ];
    }

    $tag_names = wp_list_pluck($tags, 'name');
    $display_names = implode(', ', $tag_names);

    $classes = [];

    // Loop tags and check flexible match
    foreach ($tag_names as $tag) {
        $tag_lower = strtolower(trim(html_entity_decode($tag)));

        if (strpos($tag_lower, '20% off') !== false) {
            $classes[] = 'off';
        }

        if (strpos($tag_lower, 'hot offer') !== false) {
            $classes[] = 'offer';
        }
    }

    return [
        'names' => $display_names,
        'classes' => implode(' ', array_unique($classes))
    ];
}
