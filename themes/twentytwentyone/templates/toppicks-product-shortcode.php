<?php
function top_picks_owl_carousel_shortcode()
{
    ob_start();

    // WP Query for products with a specific category or tag (You can modify this to fit your needs)
    // $args = array(
    //     'post_type' => 'product',
    //     'posts_per_page' => 8, // Number of products to display
    //     'orderby' => 'date',   // Order by the date of publication
    //     'order' => 'DESC',     // Latest first (descending order)
    //     'post_status' => 'publish', // Only fetch published products
    //     // 'tax_query' => array(
    //     //     array(
    //     //         'taxonomy' => 'product_tag', // You can also use 'product_cat' for categories
    //     //         'field'    => 'slug',
    //     //         'terms'    => 'top-picks', // Replace with your tag or category
    //     //         'operator' => 'IN',
    //     //     ),
    //     // ),
    // );

    // ── Display mode set in admin: Products → Gift Cards Data → Top Picks tab ──
    $top_picks_mode = get_option( 'gc_top_picks_mode', 'our_selection' );

    if ( $top_picks_mode === 'personal_favourites' ) {

        // ── MODE: Their Personal Favourites ──────────────────────────────────
        // Show the current user's last-purchased products (up to 8, most recent
        // first). Falls back to site-wide best sellers for guests or users who
        // have never placed an order.

        $current_user_id = get_current_user_id();
        $personal_ids     = [];

        if ( $current_user_id ) {
            $customer_orders = wc_get_orders( [
                'customer_id' => $current_user_id,
                'status'      => [ 'wc-completed', 'wc-processing' ],
                'limit'       => 20,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'return'      => 'objects',
            ] );

            $seen = [];
            foreach ( $customer_orders as $order ) {
                foreach ( $order->get_items() as $item ) {
                    $pid = (int) $item->get_product_id();
                    if ( $pid && ! isset( $seen[ $pid ] ) ) {
                        $seen[ $pid ]  = true;
                        $personal_ids[] = $pid;
                    }
                    if ( count( $personal_ids ) >= 8 ) {
                        break 2; // enough products found
                    }
                }
            }
        }

        if ( ! empty( $personal_ids ) ) {
            // User has purchase history — show those products
            $args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'post__in'       => $personal_ids,
                'orderby'        => 'post__in', // preserve recency order
            ];
        } else {
            // Guest or user with no order history → fall back to best sellers
            $args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 8,
                'meta_key'       => 'total_sales',
                'orderby'        => 'meta_value_num',
                'order'          => 'DESC',
            ];
        }

    } else {

        // ── MODE: Our Selection (admin-curated list) ──────────────────────────
        $top_picks_product_ids = get_option( 'gc_top_picks_products', [] );
        $top_picks_product_ids = is_array( $top_picks_product_ids )
            ? array_values( array_filter( array_map( 'intval', $top_picks_product_ids ) ) )
            : [];

        if ( ! empty( $top_picks_product_ids ) ) {
            $args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'post__in'       => $top_picks_product_ids,
                'orderby'        => 'post__in',
            ];
        } elseif ( is_page( 'brands' ) ) {
            $brand_terms = get_terms( [
                'taxonomy'   => 'product_brand',
                'hide_empty' => true,
            ] );
            $brand_slugs = wp_list_pluck( $brand_terms, 'slug' );
            $args = [
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_key'       => 'total_sales',
                'orderby'        => 'meta_value_num',
                'order'          => 'DESC',
                'tax_query'      => [
                    [
                        'taxonomy' => 'product_brand',
                        'field'    => 'slug',
                        'terms'    => $brand_slugs,
                    ],
                ],
            ];
        } else {
            // No products selected in admin yet — default fallback
            $args = [
                'post_type'      => 'product',
                'posts_per_page' => 8,
                'orderby'        => 'date',
                'order'          => 'ASC',
                'post_status'    => 'publish',
            ];
        }
    }

    $query = new WP_Query($args);


    if ($query->have_posts()) {
        echo '<div class="top-picks-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            global $product;

            $product_tags = get_the_terms(get_the_ID(), 'product_tag');
            $tag_names = [];

            if ($product_tags && !is_wp_error($product_tags)) {
                foreach ($product_tags as $tag) {
                    $tag_names[] = $tag->name;
                }
            }

            // Build separate tag elements: 20% off = yellow pill, Hot offer = magenta pill (per Figma)
            $tag_elements = array();
            foreach ($tag_names as $tag_name) {
                $tag_lower = strtolower(trim($tag_name));
                $modifier = '';
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




            // Split tags: first 3 visible, rest hidden behind ellipsis (same as trending now)
            $visible_tags = array_slice($tag_elements, 0, 3);
            $remaining_tags = array_slice($tag_elements, 3);
            $tags_visible = implode('', $visible_tags);
            $tags_remaining = implode('', $remaining_tags);
            // --- MODIFICATION END ---

?>

            <div class="card gc-card">
                <a href="<?php echo esc_url(get_permalink(get_the_ID())); ?>" class="product-link">
                    <?php if (has_post_thumbnail()): ?>
                        <div class="card-img">
                            <img src="<?php the_post_thumbnail_url('full'); ?>" alt="<?php the_title(); ?>">
                        </div>
                    <?php endif; ?>
                    <h4 class="gc-title"><?php the_title(); ?></h4>
                    <?php if (!empty($tag_elements)): ?>
                        <p class="gc-product-tags product-tags-wrap">
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
                    <?php endif; ?>
                    <?php
                    $product_icons = get_the_terms(get_the_ID(), 'icons');

                    if ($product_icons && !is_wp_error($product_icons)):

                        // Prepare icon elements
                        $icon_elements = array();

                        foreach ($product_icons as $icon) {
                            $icon_elements[] = '<span class="icon icon-' . esc_attr($icon->slug) . '" title="' . esc_attr($icon->name) . '">' . esc_html($icon->name) . '</span>';
                        }

                        // Split: first 2 visible, rest hidden
                        $visible_icons = array_slice($icon_elements, 0, 2);
                        $remaining_icons = array_slice($icon_elements, 2);

                        $icons_visible = implode('', $visible_icons);
                        $icons_remaining = implode('', $remaining_icons);
                    ?>
                        <p class="product-icons">
                            <?php echo $icons_visible; ?>

                            <?php if (!empty($remaining_icons)): ?>
                                <span class="icon icon--more" tabindex="0">
                                    &hellip;
                                </span>
                                <span class="icon-tooltip">
                                    <?php echo $icons_remaining; ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </a>
            </div>

    <?php
        }
        echo '</div>';
    } else {
        echo 'No top picks found.';
    }

    wp_reset_postdata();

    ?>


    <script>
        // jQuery(document).ready(function($) {
        //             setTimeout(function() {
        //                 $('.top-picks-carousel').owlCarousel({
        //                     items: 4,
        //                     loop: false,
        //                     margin: 14,
        //                     dots: false,
        //                     autoplay: false,
        //                     responsive: {
        //                         320: {
        //                             items: 1.3
        //                         },
        //                         600: {
        //                             items: 2
        //                         },
        //                         991: {
        //                             items: 3
        //                         },
        //                         1024: {
        //                             items: 4
        //                         },
        //                         1920: {
        //                             items: 4
        //                         },

        //                     },
        //                     nav: true,
        //                     navText: [
        //                         `<svg width="21" height="14" viewBox="0 0 21 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63335C1.89551 6.38219 1.99528 6.14133 2.17287 5.96373C2.35046 5.78614 2.59133 5.68637 2.84248 5.68637L19.888 5.68637C20.1391 5.68637 20.38 5.78614 20.5576 5.96373C20.7352 6.14132 20.835 6.38219 20.835 6.63334C20.835 6.88449 20.7352 7.12536 20.5576 7.30295C20.38 7.48054 20.1391 7.58031 19.888 7.58031L2.84248 7.58032C2.59133 7.58032 2.35046 7.48055 2.17287 7.30296C1.99528 7.12536 1.89551 6.8845 1.89551 6.63335Z" fill="black"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63335C1.89551 6.38219 1.99528 6.14133 2.17287 5.96373C2.35046 5.78614 2.59133 5.68637 2.84248 5.68637L19.888 5.68637C20.1391 5.68637 20.38 5.78614 20.5576 5.96373C20.7352 6.14132 20.835 6.38219 20.835 6.63334C20.835 6.88449 20.7352 7.12536 20.5576 7.30295C20.38 7.48054 20.1391 7.58031 19.888 7.58031L2.84248 7.58032C2.59133 7.58032 2.35046 7.48055 2.17287 7.30296C1.99528 7.12536 1.89551 6.8845 1.89551 6.63335Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63335C1.89551 6.38219 1.99528 6.14133 2.17287 5.96373C2.35046 5.78614 2.59133 5.68637 2.84248 5.68637L19.888 5.68637C20.1391 5.68637 20.38 5.78614 20.5576 5.96373C20.7352 6.14132 20.835 6.38219 20.835 6.63334C20.835 6.88449 20.7352 7.12536 20.5576 7.30295C20.38 7.48054 20.1391 7.58031 19.888 7.58031L2.84248 7.58032C2.59133 7.58032 2.35046 7.48055 2.17287 7.30296C1.99528 7.12536 1.89551 6.8845 1.89551 6.63335Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63335C1.89551 6.38219 1.99528 6.14133 2.17287 5.96373C2.35046 5.78614 2.59133 5.68637 2.84248 5.68637L19.888 5.68637C20.1391 5.68637 20.38 5.78614 20.5576 5.96373C20.7352 6.14132 20.835 6.38219 20.835 6.63334C20.835 6.88449 20.7352 7.12536 20.5576 7.30295C20.38 7.48054 20.1391 7.58031 19.888 7.58031L2.84248 7.58032C2.59133 7.58032 2.35046 7.48055 2.17287 7.30296C1.99528 7.12536 1.89551 6.8845 1.89551 6.63335Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63335C1.89551 6.38219 1.99528 6.14133 2.17287 5.96373C2.35046 5.78614 2.59133 5.68637 2.84248 5.68637L19.888 5.68637C20.1391 5.68637 20.38 5.78614 20.5576 5.96373C20.7352 6.14132 20.835 6.38219 20.835 6.63334C20.835 6.88449 20.7352 7.12536 20.5576 7.30295C20.38 7.48054 20.1391 7.58031 19.888 7.58031L2.84248 7.58032C2.59133 7.58032 2.35046 7.48055 2.17287 7.30296C1.99528 7.12536 1.89551 6.8845 1.89551 6.63335Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63335C1.89551 6.38219 1.99528 6.14133 2.17287 5.96373C2.35046 5.78614 2.59133 5.68637 2.84248 5.68637L19.888 5.68637C20.1391 5.68637 20.38 5.78614 20.5576 5.96373C20.7352 6.14132 20.835 6.38219 20.835 6.63334C20.835 6.88449 20.7352 7.12536 20.5576 7.30295C20.38 7.48054 20.1391 7.58031 19.888 7.58031L2.84248 7.58032C2.59133 7.58032 2.35046 7.48055 2.17287 7.30296C1.99528 7.12536 1.89551 6.8845 1.89551 6.63335Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63335C1.89551 6.38219 1.99528 6.14133 2.17287 5.96373C2.35046 5.78614 2.59133 5.68637 2.84248 5.68637L19.888 5.68637C20.1391 5.68637 20.38 5.78614 20.5576 5.96373C20.7352 6.14132 20.835 6.38219 20.835 6.63334C20.835 6.88449 20.7352 7.12536 20.5576 7.30295C20.38 7.48054 20.1391 7.58031 19.888 7.58031L2.84248 7.58032C2.59133 7.58032 2.35046 7.48055 2.17287 7.30296C1.99528 7.12536 1.89551 6.8845 1.89551 6.63335Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30046C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75456 1.44903e-06 6.63C1.42181e-06 6.50544 0.024575 6.38211 0.0723146 6.26706C0.120054 6.15201 0.190022 6.04751 0.27821 5.95955L5.96004 0.277714C6.13786 0.0998987 6.37903 3.15945e-06 6.6305 3.10449e-06C6.88197 3.04953e-06 7.12314 0.0998985 7.30095 0.277714C7.47877 0.45553 7.57867 0.6967 7.57867 0.948171C7.57867 1.19964 7.47877 1.44081 7.30095 1.61863L2.28768 6.63L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30046Z" fill="black"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30046C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75456 1.44903e-06 6.63C1.42181e-06 6.50544 0.024575 6.38211 0.0723146 6.26706C0.120054 6.15201 0.190022 6.04751 0.27821 5.95955L5.96004 0.277714C6.13786 0.0998987 6.37903 3.15945e-06 6.6305 3.10449e-06C6.88197 3.04953e-06 7.12314 0.0998985 7.30095 0.277714C7.47877 0.45553 7.57867 0.6967 7.57867 0.948171C7.57867 1.19964 7.47877 1.44081 7.30095 1.61863L2.28768 6.63L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30046Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30046C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75456 1.44903e-06 6.63C1.42181e-06 6.50544 0.024575 6.38211 0.0723146 6.26706C0.120054 6.15201 0.190022 6.04751 0.27821 5.95955L5.96004 0.277714C6.13786 0.0998987 6.37903 3.15945e-06 6.6305 3.10449e-06C6.88197 3.04953e-06 7.12314 0.0998985 7.30095 0.277714C7.47877 0.45553 7.57867 0.6967 7.57867 0.948171C7.57867 1.19964 7.47877 1.44081 7.30095 1.61863L2.28768 6.63L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30046Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30046C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75456 1.44903e-06 6.63C1.42181e-06 6.50544 0.024575 6.38211 0.0723146 6.26706C0.120054 6.15201 0.190022 6.04751 0.27821 5.95955L5.96004 0.277714C6.13786 0.0998987 6.37903 3.15945e-06 6.6305 3.10449e-06C6.88197 3.04953e-06 7.12314 0.0998985 7.30095 0.277714C7.47877 0.45553 7.57867 0.6967 7.57867 0.948171C7.57867 1.19964 7.47877 1.44081 7.30095 1.61863L2.28768 6.63L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30046Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30046C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75456 1.44903e-06 6.63C1.42181e-06 6.50544 0.024575 6.38211 0.0723146 6.26706C0.120054 6.15201 0.190022 6.04751 0.27821 5.95955L5.96004 0.277714C6.13786 0.0998987 6.37903 3.15945e-06 6.6305 3.10449e-06C6.88197 3.04953e-06 7.12314 0.0998985 7.30095 0.277714C7.47877 0.45553 7.57867 0.6967 7.57867 0.948171C7.57867 1.19964 7.47877 1.44081 7.30095 1.61863L2.28768 6.63L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30046Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30046C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75456 1.44903e-06 6.63C1.42181e-06 6.50544 0.024575 6.38211 0.0723146 6.26706C0.120054 6.15201 0.190022 6.04751 0.27821 5.95955L5.96004 0.277714C6.13786 0.0998987 6.37903 3.15945e-06 6.6305 3.10449e-06C6.88197 3.04953e-06 7.12314 0.0998985 7.30095 0.277714C7.47877 0.45553 7.57867 0.6967 7.57867 0.948171C7.57867 1.19964 7.47877 1.44081 7.30095 1.61863L2.28768 6.63L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30046Z" fill="black" fill-opacity="0.2"/>
        //                         <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30046C0.190022 7.2125 0.120054 7.108 0.0723148 6.99295C0.0245752 6.8779 1.47626e-06 6.75456 1.44903e-06 6.63C1.42181e-06 6.50544 0.024575 6.38211 0.0723146 6.26706C0.120054 6.15201 0.190022 6.04751 0.27821 5.95955L5.96004 0.277714C6.13786 0.0998987 6.37903 3.15945e-06 6.6305 3.10449e-06C6.88197 3.04953e-06 7.12314 0.0998985 7.30095 0.277714C7.47877 0.45553 7.57867 0.6967 7.57867 0.948171C7.57867 1.19964 7.47877 1.44081 7.30095 1.61863L2.28768 6.63L7.30095 11.6414C7.47877 11.8192 7.57867 12.0604 7.57867 12.3118C7.57867 12.5633 7.47877 12.8045 7.30096 12.9823C7.12314 13.1601 6.88197 13.26 6.6305 13.26C6.37903 13.26 6.13786 13.1601 5.96004 12.9823L0.27821 7.30046Z" fill="black" fill-opacity="0.2"/>
        //                         </svg>
        //                         `,
        //                         `<svg xmlns="http://www.w3.org/2000/svg" width="21" height="14" viewBox="0 0 21 14" fill="none">
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9395 6.62666C18.9395 6.87781 18.8397 7.11868 18.6621 7.29627C18.4845 7.47386 18.2436 7.57363 17.9925 7.57363L0.946985 7.57363C0.695833 7.57363 0.454966 7.47386 0.277374 7.29627C0.0997831 7.11868 1.35086e-05 6.87781 1.34756e-05 6.62666C1.34427e-05 6.37551 0.099783 6.13464 0.277374 5.95705C0.454966 5.77946 0.695833 5.67969 0.946985 5.67969L17.9925 5.67969C18.2436 5.67969 18.4845 5.77946 18.6621 5.95705C18.8397 6.13464 18.9395 6.37551 18.9395 6.62666Z" fill="black"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9395 6.62666C18.9395 6.87781 18.8397 7.11868 18.6621 7.29627C18.4845 7.47386 18.2436 7.57363 17.9925 7.57363L0.946985 7.57363C0.695833 7.57363 0.454966 7.47386 0.277374 7.29627C0.0997831 7.11868 1.35086e-05 6.87781 1.34756e-05 6.62666C1.34427e-05 6.37551 0.099783 6.13464 0.277374 5.95705C0.454966 5.77946 0.695833 5.67969 0.946985 5.67969L17.9925 5.67969C18.2436 5.67969 18.4845 5.77946 18.6621 5.95705C18.8397 6.13464 18.9395 6.37551 18.9395 6.62666Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9395 6.62666C18.9395 6.87781 18.8397 7.11868 18.6621 7.29627C18.4845 7.47386 18.2436 7.57363 17.9925 7.57363L0.946985 7.57363C0.695833 7.57363 0.454966 7.47386 0.277374 7.29627C0.0997831 7.11868 1.35086e-05 6.87781 1.34756e-05 6.62666C1.34427e-05 6.37551 0.099783 6.13464 0.277374 5.95705C0.454966 5.77946 0.695833 5.67969 0.946985 5.67969L17.9925 5.67969C18.2436 5.67969 18.4845 5.77946 18.6621 5.95705C18.8397 6.13464 18.9395 6.37551 18.9395 6.62666Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9395 6.62666C18.9395 6.87781 18.8397 7.11868 18.6621 7.29627C18.4845 7.47386 18.2436 7.57363 17.9925 7.57363L0.946985 7.57363C0.695833 7.57363 0.454966 7.47386 0.277374 7.29627C0.0997831 7.11868 1.35086e-05 6.87781 1.34756e-05 6.62666C1.34427e-05 6.37551 0.099783 6.13464 0.277374 5.95705C0.454966 5.77946 0.695833 5.67969 0.946985 5.67969L17.9925 5.67969C18.2436 5.67969 18.4845 5.77946 18.6621 5.95705C18.8397 6.13464 18.9395 6.37551 18.9395 6.62666Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9395 6.62666C18.9395 6.87781 18.8397 7.11868 18.6621 7.29627C18.4845 7.47386 18.2436 7.57363 17.9925 7.57363L0.946985 7.57363C0.695833 7.57363 0.454966 7.47386 0.277374 7.29627C0.0997831 7.11868 1.35086e-05 6.87781 1.34756e-05 6.62666C1.34427e-05 6.37551 0.099783 6.13464 0.277374 5.95705C0.454966 5.77946 0.695833 5.67969 0.946985 5.67969L17.9925 5.67969C18.2436 5.67969 18.4845 5.77946 18.6621 5.95705C18.8397 6.13464 18.9395 6.37551 18.9395 6.62666Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9395 6.62666C18.9395 6.87781 18.8397 7.11868 18.6621 7.29627C18.4845 7.47386 18.2436 7.57363 17.9925 7.57363L0.946985 7.57363C0.695833 7.57363 0.454966 7.47386 0.277374 7.29627C0.0997831 7.11868 1.35086e-05 6.87781 1.34756e-05 6.62666C1.34427e-05 6.37551 0.099783 6.13464 0.277374 5.95705C0.454966 5.77946 0.695833 5.67969 0.946985 5.67969L17.9925 5.67969C18.2436 5.67969 18.4845 5.77946 18.6621 5.95705C18.8397 6.13464 18.9395 6.37551 18.9395 6.62666Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9395 6.62666C18.9395 6.87781 18.8397 7.11868 18.6621 7.29627C18.4845 7.47386 18.2436 7.57363 17.9925 7.57363L0.946985 7.57363C0.695833 7.57363 0.454966 7.47386 0.277374 7.29627C0.0997831 7.11868 1.35086e-05 6.87781 1.34756e-05 6.62666C1.34427e-05 6.37551 0.099783 6.13464 0.277374 5.95705C0.454966 5.77946 0.695833 5.67969 0.946985 5.67969L17.9925 5.67969C18.2436 5.67969 18.4845 5.77946 18.6621 5.95705C18.8397 6.13464 18.9395 6.37551 18.9395 6.62666Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5568 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.835 6.50544 20.835 6.63C20.835 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5568 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5568 5.95954Z" fill="black"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5568 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.835 6.50544 20.835 6.63C20.835 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5568 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5568 5.95954Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5568 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.835 6.50544 20.835 6.63C20.835 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5568 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5568 5.95954Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5568 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.835 6.50544 20.835 6.63C20.835 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5568 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5568 5.95954Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5568 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.835 6.50544 20.835 6.63C20.835 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5568 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5568 5.95954Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5568 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.835 6.50544 20.835 6.63C20.835 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5568 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5568 5.95954Z" fill="black" fill-opacity="0.2"/>
        //                     <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5568 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.835 6.50544 20.835 6.63C20.835 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5568 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5568 5.95954Z" fill="black" fill-opacity="0.2"/>
        //                     </svg>`
        //                     ]
        //                 });
        //                  },1000);
        //         });


        jQuery(document).ready(function($) {

                // Top Picks Grid Horizontal Scroll
                const $topPicksGrid = $('.top-picks-grid');

                if ($topPicksGrid.length) {

                    // Mouse wheel horizontal scroll
                    $topPicksGrid.on('wheel', function(e) {
                        if (this.scrollWidth > this.clientWidth) {
                            e.preventDefault();
                            this.scrollLeft += e.originalEvent.deltaY;
                        }
                    });

                    // Drag Scroll
                    let isDown = false;
                    let startX;
                    let scrollLeft;

                    $topPicksGrid.on('mousedown', function(e) {
                        isDown = true;
                        $(this).addClass('is-dragging');
                        startX = e.pageX - this.offsetLeft;
                        scrollLeft = this.scrollLeft;
                    });

                    $topPicksGrid.on('mouseleave mouseup', function() {
                        isDown = false;
                        $(this).removeClass('is-dragging');
                    });

                    $topPicksGrid.on('mousemove', function(e) {
                        if (!isDown) return;
                        e.preventDefault();
                        const x = e.pageX - this.offsetLeft;
                        const walk = (x - startX) * 1.5;
                        this.scrollLeft = scrollLeft - walk;
                    });
                }

                // Product Tag Tooltip
                $('.product-tag--more').each(function() {

                    const $trigger = $(this);
                    const $tooltip = $trigger.next('.product-tag-tooltip');

                    $trigger.on('mouseenter focus', function() {
                        const rect = this.getBoundingClientRect();
                        const tooltipHeight = $tooltip.outerHeight(true) || 60;
                        $tooltip.css({
                            top: rect.top - tooltipHeight - 8,
                            left: rect.left - 20
                        }).addClass('active');
                    });

                    $trigger.on('mouseleave blur', function() {
                        $tooltip.removeClass('active');
                    });
                });

                // Product Icon Tooltip
                $('.icon--more').each(function() {

                    const $trigger = $(this);
                    const $tooltip = $trigger.next('.icon-tooltip');

                    $trigger.on('mouseenter focus', function() {
                        const rect = this.getBoundingClientRect();
                        const tooltipHeight = $tooltip.outerHeight(true) || 60;
                        $tooltip.css({
                            top: rect.top - tooltipHeight - 8,
                            left: rect.left - 20
                        }).addClass('active');
                    });

                    $trigger.on('mouseleave blur', function() {
                        $tooltip.removeClass('active');
                    });
                });

            });
            
    </script>
<?php

    return ob_get_clean();
}
add_shortcode('top_picks_carousel', 'top_picks_owl_carousel_shortcode');
