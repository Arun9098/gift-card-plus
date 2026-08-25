<?php
/*
Template Name: product-listing
*/
get_header();
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></script> -->




<div class="bulk-create-header">
    <?php if (is_user_logged_in()):
        $current_user = wp_get_current_user(); ?>
        <div class="admin-profile">
            👤 <?php echo esc_html($current_user->display_name); ?>
        </div>
    <?php endif; ?>
</div>

<?php


$categories = get_terms([
    'taxonomy' => 'voucher_category',
    'hide_empty' => false,
]);

if (!empty($categories) && !is_wp_error($categories)): ?>
    <h3 class="view-all-pro-title">View All Products</h3>
    <div class="categories owl-carousel">
        <?php foreach ($categories as $category):
            $image_id = get_term_meta($category->term_id, 'voucher_category_image', true);
            $category_image = $image_id ? wp_get_attachment_url($image_id) : '';

            $category_icon = $category_image
                ? '<img src="' . esc_url($category_image) . '" alt="' . esc_attr($category->name) . '" class="category-image">'
                : '📌';


            $is_active = true;
            $status_class = $is_active ? 'active' : 'deactivated';
            $status_text = $is_active ? 'Active' : 'Deactivated';
            ?>
            <div class="category-item">
                <button class="category <?php echo esc_attr($status_class); ?>">
                    <span class="category-icon"><?php echo $category_icon; ?></span>
                    <span class="category-name"><?php echo esc_html($category->name); ?></span>
                    <span class="status"><?php echo esc_html($status_text); ?></span>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Initialize Owl Carousel -->
    <script>
        jQuery(document).ready(function ($) {
            $('.categories.owl-carousel').owlCarousel({
                loop: true,
                margin: 10,
                nav: true,
                dots: false,
                autoplay: true,
                autoplayTimeout: 3000,
                responsive: {
                    0: { items: 1 },
                    600: { items: 3 },
                    1000: { items: 5 }
                }
            });
        });
    </script>
<?php endif; ?>


<div class="product-management-header">
    <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="search-input" placeholder="Search product, brand">
    </div>

    <div class="action-buttons">
        <!-- <button class="filter-products action-button">
            <i class="fas fa-sliders-h"></i>
            Filters
        </button> -->
        <button id="bulk-edit-products" class="bulk-edit-products action-button">
            <i class="fa-solid fa-pen"></i>
            Bulk Edit Products
        </button>
        <button id="bulk-add-products" class="bulk-add-products action-button">
            <i class="fas fa-layer-group"></i>
            Bulk Add Products
        </button>
        <button class="export-products-csv action-button">
            <i class="fas fa-file-export"></i>
            Export Products
        </button>
        <button class="create-new-product-btn action-button" id="create-new-bulk-product">
            <i class="fas fa-plus"></i>
            Create New Product
        </button>
    </div>
</div>
<div class="product-listing-section">
    <table class="product-table">
        <thead>
            <tr>
                <th><input type="checkbox"></th>
                <th>Gift Card</th>
                <th>Product Name</th>
                <th>Denomination Type</th>
                <th>Denomination</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Query for WooCommerce products (adjust taxonomy if necessary)
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,  // Display 2 products per page
                'post_status' => 'publish',
                // 'paged' => get_query_var('paged', 1), // Get the current page
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

                    // Get the denomination type (you can use a custom field or category for this)
                    $denomination_type = get_field('denomination_type', $product->get_id());

                    // Example fixed price or range (you could use a custom field for this)
                    $denomination = get_post_meta($product->get_id(), 'sell_price_lowest_denomination', true);

                    // Output the table row
                    ?>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><img src="<?php echo esc_url($product_image); ?>" alt="<?php the_title(); ?>"
                                class="category-image"></td>
                        <td><?php the_title(); ?></td>
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
                        </td>                        <td><?php echo esc_html($denomination ? $denomination : esc_html($price)); ?></td>
                        <td class="status <?php echo esc_attr($clean_status); ?>">
                            <?php echo esc_html(ucfirst($clean_status)); ?>
                        </td>
                        <td><a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>">View/Edit</a></td>
                    </tr>
                    <?php
                endwhile;
            else:
                echo '<tr><td colspan="7">No products found.</td></tr>';
            endif;

            wp_reset_postdata();
            ?>
        </tbody>
    </table>
    <div class="pagination-controls"></div>
</div>

<div class="save-next-button-controls">
    <button class="pagination-button">Save</button>
    <button class="pagination-button next">Next</button>
</div>
<!-- <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script> -->

<div class="bulk-add-container" style="display: none;">
    <div class="title">Bulk Add Products</div>

    <!-- Step 1 -->
    <div class="step">
        <div class="step-title">Step 1</div>
        <div class="step-description">
            Download the existing range template <span class="important">(Important!)</span>
        </div>
        <button id="download-product-template" class="btn primary-btn">
            <i class="fas fa-download"></i> Download Template
        </button>

    </div>

    <!-- Step 2 -->
    <div class="step">
        <div class="step-title">Step 2</div>
        <div class="step-description">
            Open the downloaded Excel document and fill in the required columns (denoted with a *).
        </div>
        <div class="sub-heading">What details do I need for the CSV?</div>
        <div class="csv-details">
            <div>1. Parent or Child SKU</div>
            <div>2. Parent Link</div>
            <div>3. SKU</div>
            <div>4. Gift Card Title</div>
            <div>5. Brand</div>
            <div>6. Supplier</div>
            <div>7. Gift Card Expiry Type</div>
            <div>8. Gift Card Activation Type</div>
            <div>9. Denomination Type</div>
            <div>10. Cost Price</div>
            <div>11. Discounted Price</div>
            <div>12. Stock Levels</div>
            <div>13. Transaction Limits</div>
        </div>
    </div>

    <!-- Step 3 -->
    <div class="step">
        <div class="step-title">Step 3</div>
        <div class="step-description">
            Upload your modified CSV with the new product range. This will show you a preview of the products that will
            be created and allow you to confirm the changes.
        </div>
        <button id="upload-product-csv-btn" class="btn upload-btn">
            <i class="fas fa-upload"></i> Upload File
        </button>
        <div class="warning">
            <i class="fas fa-exclamation-circle"></i> It can take several minutes to process large orders. Please be
            patient.
        </div>
    </div>

    <!-- Footer Buttons -->
    <!-- <div class="footer-buttons">
        <div class="btn secondary-btn">Save Draft</div>
        <div class="btn primary-btn">Next</div>
    </div> -->
</div>
<!-- For Popup -->

<div class="modal fade" id="file-upload-modal" tabindex="-1" aria-labelledby="file-upload-modal-label"
    aria-hidden="true" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="file-upload-modal-label">Upload CSV File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="file" id="csv-file-input1" class="form-control" accept=".csv">
                <small class="text-danger d-block mt-2" id="file-error-msg">⚠️ Please select a CSV file.</small>
                <!-- File Name Display -->
                <div id="file-name-display" class="mt-2" style="display: none;">
                    <strong>Selected File:</strong> <span id="selected-file-name"></span>
                </div>
                <!-- Progress Bar -->
                <div id="upload-progress" class="progress mt-2" style="display: none;">
                    <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submit-file-upload">Upload File</button>
            </div>
        </div>
    </div>
</div>

<!-- For Mapping modal field -->
<div class="modal fade" id="mapping-modal" tabindex="-1" aria-labelledby="mapping-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <!-- Increased width -->
        <div id="modal-content" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Review Fields</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">The following fields have been matched based on your data. Please choose an input
                    field and review to ensure these are correct.</p>
                <hr>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th class="text-center">→</th>
                                <th>Input Field</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="mapping-interface">
                            <!-- Dynamically generated rows will be added here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="apply-mapping">Save and Continue</button>
            </div>
        </div>
    </div>
</div>

<div id="mandatory-warning" class="mb-3"></div>
<div id="validation-results" class="mb-3"></div>
<div style="overflow-x: auto;">

    <div id="csv-preview" class="container mt-4 d-none">
        <h3>Bulk Upload Orders</h3>
        <h5>CSV Preview</h5>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button type="button" class="btn btn-outline-secondary" id="back-to-bulk-upload">← Back</button>
            <button type="button" class="btn btn-dark" id="next-button">Next</button>
        </div>
        <div class="d-flex justify-content-between mt-3">
            <select id="filter-by" class="form-select">
                <option value="all">All</option>
                <option value="errors">Errors</option>
                <option value="no-errors">No Errors</option>
            </select>
        </div>
        <div class="mt-3">
            <span id="correct-rows-count" class="badge bg-success"></span>
            <span id="error-rows-count" class="badge bg-danger"></span>
        </div>
        <div id="pagination" class="mt-3"></div>
    </div>
    <button type="button" class="btn btn-dark" id="match-headers-btn" style="display: none;">Next</button>

</div>

<div id="preview-section" class="preview-container" style="display: none;">
    <button class="btn btn-danger" id="remove-error-lines">Remove error lines</button>
    <button class="btn btn-warning" id="edit-errors">Edit errors</button>
    <button class="btn btn-primary" id="download-resubmit">Download and Resubmit</button>
    <div style="overflow-x: auto;">
    <div id="row-count-summary" class="row-summary" style="text-align: right; font-weight: bold; margin-bottom: 10px;"></div>
        <table id="csv-preview-table">
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
    <button class="btn btn-primary" id="bulk-upload-preview-btn" disabled>Next</button>
</div>
<div id="final-preview-section" class="csvFinalPreviewTable" style="display: none;">
    <h3>Final Data Preview</h3>
    <div style="overflow-x: auto;">
        <table id="final-preview-table">
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
    <button class="btn btn-success" id="final-create-btn" name="final-create-btn">
        <span class="btn-text">Submit</span>
        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
    </button>
</div>
<p id="success-message" style="color: green; display: none;"></p>
<p id="success-and-error-message" style="color: green; display: none;"></p>




<?php get_footer(); ?>