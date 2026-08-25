<?php
/* Template Name: Review Products */
get_header();
?>

<div class="page-spacer-top"></div>
<div class="review-products-container container">

    <div class="page-title align-left">
        <h1>Products Awaiting Publication</h1>
    </div>

    <div class="top-filter-block product-management-header">
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Search product, brand">
        </div>

        <div class="action-buttons">
            <!-- <button class="filter-products action-button">
            <i class="fas fa-sliders-h"></i>
            Filters
        </button> -->
            <button id="bulk-edit-products" class="btn btn-white bulk-edit-products btn-black-white btn-primary-white action-button size-sm">
                Edit
            </button>
            <button class="export-products-csv action-button btn btn-white size-sm btn-black-white btn-primary-white">
                Export Products
            </button>
            <button class="create-new-product-btn action-button btn btn-blue size-sm " id="create-new-bulk-product">
                Create New Product
            </button>
            <div class="view-toggle">
                <button id="review-list-view-btn" class="view-icon active"><i class="fas fa-list"></i></button>
                <button id="review-thumbnail-view-btn" class="view-icon"><i class="fas fa-th"></i></button>
            </div>
        </div>
    </div>
    <div id="product-list-view-review">
        <div class="product-listing-section">
            <table class="review-product-table" data-no-init="true">
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
                            <span class="dashicons dashicons-filter filter-icon" data-column="product-name"></span>
                        </th>
                        <th data-head_slug="denomination_type">
                            Denomination Type
                            <span class="dashicons dashicons-filter filter-icon" data-column="denomination-type"></span>
                        </th>
                        <th data-head_slug="denomination">
                            Denomination
                            <span class="dashicons dashicons-filter filter-icon" data-column="denomination"></span>
                        </th>
                        <th data-head_slug="product_status">
                            Status
                            <span class="dashicons dashicons-filter filter-icon" data-column="status"></span>
                        </th>
                        <th>
                            Details
                            <span class="dashicons dashicons-filter filter-icon" data-column="details"></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $args = array(
                        'post_type'      => 'product',
                        'posts_per_page' => -1,
                        'post_status'    => 'draft',
                    );
                    $products = new WP_Query($args);

                    if ($products->have_posts()):
                        while ($products->have_posts()):
                            $products->the_post();
                            global $product;

                            // Get the product image
                            $product_image = get_the_post_thumbnail_url($product->get_id(), 'thumbnail');

                            // Get product price
                            $price = $product->get_price();

                            // Get product status
                            $status = $product->get_status(); // Get the raw product status (e.g., wc-deactivated)
                    
                            // Remove the 'wc-' prefix for custom status display
                            $clean_status = str_replace('wc-', '', $status);

                            $display_status = ucfirst($clean_status);
                            if ( $clean_status === 'draft' ) {
                                $display_status = 'Awaiting Approval';
                            }

                            $denomination_type = get_field('denomination_type', $product->get_id());

                            // Example fixed price or range (you could use a custom field for this)
                            $denomination = get_post_meta($product->get_id(), 'sell_price_lowest_denomination', true);

                            // Output the table row
                            ?>
                            <tr>
                                <td><input type="checkbox"></td>
                                <td class="image img-rounded">
                                    <span class="image-inner">
                                        <img src="<?php echo esc_url($product_image); ?>" alt="<?php the_title(); ?>"
                                        class="category-image">
                                    </span>
                                </td>
                                <td class="card-name"><?php the_title(); ?></td>
                                <td>
                                    <?php
                                        if ( ! empty( $denomination_type ) ) {
                                            if ( is_array( $denomination_type ) ) {
                                                echo esc_html( implode( ', ', $denomination_type ) );
                                            } else {
                                                echo esc_html( $denomination_type );
                                            }
                                        } else {
                                            echo 'Fixed';
                                        }
                                        ?>
                                </td>
                                <td><?php echo esc_html($denomination ? $denomination : esc_html($price)); ?></td>
                                <td class="status <?php echo esc_attr($clean_status); ?>">
                                    <span><?php echo esc_html(ucfirst($display_status)); ?></span>
                                </td>
                                <td><a href="<?php echo add_query_arg('edit_product', $product->get_id(), get_permalink(get_page_by_path('create-product'))); ?>"
                                        class="edit-product-btn" onclick="handleEditProductClick(event, this)">View/Edit</a></td>
                            </tr>
                            <?php
                        endwhile;
                        endif;

                    wp_reset_postdata();
                    ?>
                </tbody>
            </table>
            <div class="pagination-controls"></div>
        </div>
    </div>

    <div id="review-product-thumbnail-view" style="display: none;">
        <div id="review-thumbnail-grid-wrapper">
            <div id="review-thumbnail-grid" class="thumbnail-grid"></div>
        </div>
        <div id="review-thumbnail-pagination" class="pagination"></div>
    </div>



    <div class="page-bottom-toolbar">
        <div class="right-block">
            <div class="save-next-button-controls page-bottom-actions">
                <button class="pagination-button btn btn-white btn-black-white btn-primary-white">Save</button>
                <button class="pagination-button next btn btn-primary  btn-primary-black btn-black-white ">Next</button>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>