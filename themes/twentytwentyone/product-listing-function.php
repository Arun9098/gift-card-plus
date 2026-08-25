<?php
function product_listing_function()
{ ?>
<!-- This section is called for the listing of the product in Product Listing Page -->
    <div class="product-listing-section">
        <div id="export-product-message"></div>
        <table class="product-table">
            <thead>
                <tr>
                    <th>
                        <input type="checkbox">
                    </th>
                    <th data-head_slug="gift_card">
                        Gift Card
                    </th>
                    <th data-head_slug="product_name">
                        Product Name
                        <span class="dashicons dashicons-filter" data-column="product-name"></span>
                    </th>
                    <th data-head_slug="denomination_type">
                        Denomination Type
                        <span class="dashicons dashicons-filter" data-column="denomination-type"></span>
                    </th>
                    <th data-head_slug="denomination">
                        Denomination
                        <span class="dashicons dashicons-filter" data-column="denomination"></span>
                    </th>
                    <th data-head_slug="status">
                        Status
                        <span class="dashicons dashicons-filter" data-column="status"></span>
                    </th>
                    <th>
                        Details
                        <span class="dashicons dashicons-filter" data-column="details"></span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    // 'meta_key'       => 'rank',
                    // 'orderby'        => 'meta_value_num',
                    // 'order'          => 'DESC',
                    'post_status' => array(
                        'publish',
                        'draft',
                        'pending',
                        'wc-pending',
                        'wc-processing',
                        'wc-on-hold',
                        'wc-completed',
                        'wc-cancelled',
                        'wc-refunded',
                        'wc-failed',
                        'wc-deactivated',
                        'wc-awaiting-publishing',
                        'wc-closed',
                        'wc-deleted'
                    ),
                );

                $selected_cat = isset($_REQUEST['cat']) ? sanitize_text_field($_REQUEST['cat']) : '';
                if( !empty($selected_cat) ){
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_cat', // name of your taxonomy
                            'field'    => 'slug',             // or 'term_id' or 'name'
                            'terms'    => array_filter(explode(",", $selected_cat)),       // example term slug or array of slugs
                        )
                    );
                }

                $products = new WP_Query($args);

                if ($products->have_posts()):
                    while ($products->have_posts()):
                        $products->the_post();
                        global $product;

                        // Get the product image
                        $product_image = get_the_post_thumbnail_url($product->get_id(), 'thumbnail');

                        // Get product price
                        $price = $product->get_price();
                        $rank_value = get_field('rank', get_the_ID()); // ACF field 'rank'
                        //$rank_value = (int) get_field('rank', get_the_ID());
                        // asort($rank_value); // lowest to highest


                        // Get product status
                        $status = $product->get_status(); // Get the raw product status (e.g., wc-deactivated)
            
                        // Remove the 'wc-' prefix for custom status display
                        $clean_status = str_replace('wc-', '', $status);

                        $temp_denomination_type = get_field('denomination_type', $product->get_id());
                        $denomination_type = '';

                        if( is_array($temp_denomination_type) ){
                            $denomination_type = explode(',', $temp_denomination_type);
                        }else{
                            $denomination_type = $temp_denomination_type;
                        }

                        // Example fixed price or range (you could use a custom field for this)
                        $denomination = get_post_meta($product->get_id(), '_regular_price', true);

                        // Output the table row
                        ?>
                        <tr data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                            <td><input type="checkbox"></td>
                            <td data-sort="<?php echo esc_attr($rank_value); ?>" class="image img-rounded"><a href="<?php echo esc_url(site_url('/create-product/?edit_product=' . $product->get_id())); ?>" class="edit-product-btn" onclick="handleEditProductClick(event, this)"><span class="image-inner"><img src="<?php echo esc_url($product_image); ?>" alt="<?php the_title(); ?>"
                                    class="category-image"></span></a></td>
                            <td class="card-name"><?php the_title(); ?></td>
                            <td class="cat-pros">
                                <?php
                                echo !empty($denomination_type) ? esc_html(ucfirst($denomination_type)) : 'Fixed';
                                ?>
                            </td>  
                            <td><?php echo esc_html($denomination ? $denomination : ' - '); ?></td>
                            <td data-status="<?php echo esc_attr($clean_status); ?>" class="status <?php echo esc_attr($clean_status); ?>">
                            <span>
                                <?php
                                    $display_status = ucfirst($clean_status); // fallback
                                    if ( $status === 'publish' ) {
                                        $display_status = 'Active';
                                    } elseif ( $status === 'draft' ) {
                                        $display_status = 'Awaiting Approval';
                                    }
                                    echo esc_html($display_status);
                                ?>
                            </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(site_url('/create-product/?edit_product=' . $product->get_id())); ?>" class="edit-product-btn" onclick="handleEditProductClick(event, this)">View/Edit</a>
                            </td>
                        </tr>
                        <?php
                    endwhile;
                else:
                    echo '<tr>
                        <td colspan="7" style="text-align:center;">No products found.</td>
                        <td style="display:none;"></td>
                        <td style="display:none;"></td>
                        <td style="display:none;"></td>
                        <td style="display:none;"></td>
                        <td style="display:none;"></td>
                        <td style="display:none;"></td>
                    </tr>';
                endif;

                wp_reset_postdata();
                ?>
            </tbody>
        </table>
        <div class="pagination-controls"></div>
    </div><?php }