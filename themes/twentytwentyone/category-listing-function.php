<?php

function my_ajax_load_gift_card_categories()
{
    
    check_ajax_referer('custom_ajax_nonce', 'nonce');

    $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
    $per_page = 30;
    $offset = ($paged - 1) * $per_page;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    // Base args
    $args = [
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'number' => $per_page,
        'offset' => $offset,
        'orderby' => 'name',
        'order' => 'ASC',
    ];

    if ($search !== '') {
        $args['search'] = $search; // 👈 search by term name
    }

    $child_categories_thumbnail = get_terms($args);

    // Compute total for pagination (respecting search)
    $count_args = [
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'fields' => 'ids',
    ];
    if ($search !== '') {
        $count_args['search'] = $search;
    }
    $matching_ids = get_terms($count_args);
    $total_terms = is_wp_error($matching_ids) ? 0 : count($matching_ids);
    $total_pages = ($per_page > 0) ? (int) ceil($total_terms / $per_page) : 1;

    if (!empty($child_categories_thumbnail) && !is_wp_error($child_categories_thumbnail)) {
        ob_start();
        foreach ($child_categories_thumbnail as $category) {
            $thumbnail_id = get_term_meta($category->term_id, 'category_icon', true);
            $category_image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
            $status = get_term_meta($category->term_id, 'category_status', true) ?: 'active';
            $status_class = $status === 'active' ? 'active' : 'deactivated';
            $status_text = ucfirst($status);

            $term_link = get_term_link($category);
            if (is_wp_error($term_link)) {
                continue; // skip invalid terms
            }
            ?>
            <a href="#" class="category-link card-category <?php echo esc_attr($status_class); ?>" data-term-id="<?php echo esc_attr($category->term_id); ?>">
                <div class="category-thumb-card">
                    <div class="thumb-category-icon">
                        <?php if ($category_image): ?>
                            <img class="category-has-image" src="<?php echo esc_url($category_image); ?>"
                                alt="<?php echo esc_attr($category->name); ?>">
                        <?php else: ?>
                            <div class="no-image category-no-image">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="category-name"><?php echo esc_html($category->name); ?></div>
                    <div class="category-thumb-status status <?php echo strtolower($status_text); ?>">
                        <span><?php echo $status_text; ?></span>
                    </div>
                </div>
            </a>
            <?php
        }
        $content = ob_get_clean();

        wp_send_json_success([
            'content' => $content,
            'total_pages' => $total_pages,
        ]);
    } else {
        wp_send_json_success([ // still success, but empty set
            'content' => '<p>No categories found.</p>',
            'total_pages' => 0,
        ]);
    }

    wp_die();
}
add_action('wp_ajax_load_gift_card_categories', 'my_ajax_load_gift_card_categories');
add_action('wp_ajax_nopriv_load_gift_card_categories', 'my_ajax_load_gift_card_categories');

function category_listing_section()
{
    wp_enqueue_media();

    // $display_status_cat = '';
    $display_title = '';
    $display_listing = '';
    $display_header = '';
    $display_create_view = 'style="display:none;"';

    if (isset($_GET['create-category']) && $_GET['create-category'] == 'true') {
        // Hide title, listing, header
        $display_title = 'style="display:none;"';
        $display_listing = 'style="display:none;"';
        $display_header = 'style="display:none;"';

        // Show create view
        $display_create_view = 'style="display:block;"';
    }



    ?>

    <div class="category-listing-section" <?= $display_listing; ?>>
        <div id="export-category-message" class="export-category-message"></div>
        <table id="voucher-category-table" class="category-table display">
            <thead>
                <tr>
                    <th><input type="checkbox" class="custom-checkbox"></th>
                    <th>Icon</th>
                    <th data-head_slug="cat_id">ID</th>
                    <th data-head_slug="cat_name">Category Name</th>
                    <th data-head_slug="cat_assigned">Assigned</th>
                    <th data-head_slug="cat_description">Description</th>
                    <th data-head_slug="cat_priority">Priority</th>
                    <th>Images</th>
                    <th data-head_slug="cat_status">Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // $voucher_category = get_term_by('name', 'Vouchers', 'product_cat');
            
                // $paged        = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                // $per_page     = 6;
                // $offset       = ($paged - 1) * $per_page;
                // $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
            
                // $child_categories = get_terms([
                //     'taxonomy' => 'product_cat',
                //     'hide_empty' => false,
                //     // 'parent' => $voucher_category->term_id,
                // ]);
            
                // if ($search_query) {
                //     $args['search'] = $search_query;
                // }
            
                // $child_categories = get_terms($args);
            


                $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $per_page = 10; // number of items per page
                $offset = ($paged - 1) * $per_page;

                $args = [
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    // 'number'     => $per_page,
                    // 'offset'     => $offset,
                    'orderby' => 'date',
                    'order' => 'DESC',
                ];

                if (!empty($_GET['search'])) {
                    $args['search'] = sanitize_text_field($_GET['search']);
                }
                // echo '<pre>';
                // print_r($_GET['search']);
                // echo '</pre>';
                $child_categories = get_terms($args);
                $total_categories = wp_count_terms([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                ]);
                $total_pages = ceil($total_categories / $per_page);


                // Debug
                // echo '<pre>';
                // print_r($child_categories);
                // echo '</pre>';
            
                // $child_categories_thumbnail = get_terms([
                //     'taxonomy' => 'product_cat',
                //     'hide_empty' => false,
                //     // 'parent' => $voucher_category->term_id,
                //     'number'     => $per_page,
                //     'order'    => 'ASC',
                //     'offset'     => $offset,
                // ]);
            
                if (!is_wp_error($child_categories)) {
                    foreach ($child_categories as $category) {
                        $thumbnail_id = get_term_meta($category->term_id, 'category_icon', true);
                        $category_image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
                        $description = term_description($category->term_id, 'product_cat');
                        $priority = get_term_meta($category->term_id, 'priority', true); // Example custom field
                        $status = get_term_meta($category->term_id, 'category_status', true);
                        $status_label = ucfirst($status);

                        // Count products from ACF sku_assigned_arr repeater (not WC taxonomy)
                        $assigned_count = (int) get_term_meta( $category->term_id, 'sku_assigned_arr', true );
                        ?>
                        <tr>
                            <td><input type="checkbox" class="custom-checkbox"></td>
                            <td class="icon-image">
                                <?php if ($category_image): ?>
                                    <span class="image-inner"><img src="<?php echo esc_url($category_image); ?>" alt="Icon"
                                            class="category-icon" width="32"></span>
                                <?php else: ?>
                                    <span class="no-image"><?php echo esc_html($category->name); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($category->term_id); ?></td>
                            <td><?php echo esc_html($category->name); ?></td>
                            <td><?php echo esc_html($assigned_count); ?></td> <!-- ✅ Assigned count shown here -->
                            <td><?php echo !empty($description) ? wp_kses_post($description) : ''; ?></td>
                            <td><?php echo !empty($priority) ? esc_html($priority) : ''; ?></td>
                            <td class="icon-image">
                                <?php if ($category_image): ?>
                                    <span class="image-inner"><img src="<?php echo esc_url($category_image); ?>" alt="Image"
                                            class="category-image" width="50"></span>
                                <?php else: ?>
                                    <span class="no-image">No image</span>
                                <?php endif; ?>
                            </td>
                            <td class="status <?php echo esc_attr($status); ?>"><span><?php echo esc_html($status_label); ?></span>
                            </td>
                            <td>
                                <a href="#" class="edit-category link-underline black" data-term-id="<?php echo esc_attr($category->term_id); ?>">View/Edit</a>
                            </td>
                        </tr>
                    <?php }
                } else {
                    echo '<tr><td colspan="10">No child categories found under Voucher.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <div class="category-thumbnail-grid" style="display: none;">
        <div class="thumbnail-wrapper card-grid" aria-live="polite"></div>
        <div id="cat-pagination" class="pagination" aria-live="polite"></div>
    </div>

    <div class="justify-content-between align-items-center mb-3 back-to-categorylist-wrapper" style="display: none;">
        <a href="javascript:void(0);" type="button" class="back-to-categorylist" id="back-to-categorylist">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none">
                <path d="M22.4598 8.95444H5.2559L12.772 2.32605C13.3727 1.79632 13.3727 0.927024 12.772 0.397296C12.1713 -0.132432 11.201 -0.132432 10.6004 0.397296L0.450505 9.34834C-0.150168 9.87807 -0.150168 10.7338 0.450505 11.2635L10.6004 20.2146C11.201 20.7443 12.1713 20.7443 12.772 20.2146C13.3727 19.6848 13.3727 18.8291 12.772 18.2994L5.2559 11.671H22.4598C23.3069 11.671 24 11.0598 24 10.3127C24 9.56567 23.3069 8.95444 22.4598 8.95444Z" fill="black"></path>
            </svg>
        </a>
    </div>

    <div class="category-edit-view" style="display: none;">
        <div class="category-header">
            <h2>Category:<span id="category-name-display" class="category-name-text"></span></h2>
            <span class="status-indicator status status-outline"><span class="" id="display-category-status"></span></span>
        </div>
        <div class="edit-header">
            <div class="header-info">
                <div class="category-meta">
                    <span class="category-id">ID: <strong id="display-category-id"></strong></span>
                </div>
            </div>
        </div>

        <form id="category-edit-form" class="category-edit-form">
            <input type="hidden" name="term_id" id="edit-term-id">

            <div class="form-group">
                <label for="category-status">Status</label>
                <select name="status" id="category-status" class="regular-text">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="deactivated">Deactivated</option>
                </select>
            </div>

            <div class="category-edit-card">
                <div class="form-section">
                    <div class="col left-content">
                        <h3>Description</h3>
                        <p>Add a sentence that explains what this category is about.</p>
                    </div>
                    <div class="col right-content">
                        <div class="form-group">
                            <div class="control-wrapper">
                                <label class="sr-only" for="category-description">Description</label>
                                <textarea class="form-control" name="description" id="category-description" rows="3"
                                    class="large-text"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="col left-content">
                        <h3>Thumbnails</h3>
                        <p>Square image at least 100x100 pixels</p>
                    </div>
                    <div class="col right-content">
                        <div class="image-preview-container image-thumbnail">
                            <div class="image-preview-placeholder">
                                <img src="<?php echo get_template_directory_uri(); ?>/assets/images/add-image-place-sm.png"
                                    alt="placeholder">
                                <img id="thumbnail-preview" src="" class="upload-preview" style="display: none;">
                            </div>
                            <div class="button-group">
                                <button type="button" class="button small upload-image-button" data-target="thumbnail">
                                    Upload Thumbnail
                                </button>
                                <button type="button" class="button small remove-image-button" data-target="thumbnail"
                                    style="display: none;">
                                    Remove
                                </button>
                                <input type="hidden" name="thumbnail_id" id="thumbnail-id">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="col left-content">
                        <h3>Icon Image</h3>
                        <p>Square image at least 256x256 pixels</p>
                    </div>
                    <div class="col right-content">
                        <div class="image-preview-container image-icon">
                            <div class="image-preview-placeholder">
                                <img src="<?php echo get_template_directory_uri(); ?>/assets/images/add-image-place-md.png"
                                    alt="placeholder">
                                <img id="icon-preview" src="" class="upload-preview" style="display: none;">
                            </div>
                            <div class="button-group">
                                <button type="button" class="button small upload-image-button" data-target="icon">
                                    Upload Icon
                                </button>
                                <button type="button" class="button small remove-image-button" data-target="icon"
                                    style="display: none;">
                                    Remove
                                </button>
                                <input type="hidden" name="icon_id" id="icon-id">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="col left-content">
                        <h3>Banner Image</h3>
                        <p>At least 1406x3456 pixels</p>
                    </div>
                    <div class="col right-content">
                        <div class="image-preview-container image-banner">
                            <div class="image-preview-placeholder">
                                <img src="<?php echo get_template_directory_uri(); ?>/assets/images/banner-placeholder.png"
                                    alt="placeholder">
                                <div class="image-banner-preview">
                                    <img id="banner-preview" src="" class="upload-preview" style="display: none;">
                                </div>
                            </div>
                            <div class="button-group">
                                <button type="button" class="button small upload-image-button" data-target="banner">
                                    Upload Banner
                                </button>
                                <button type="button" class="button small remove-image-button" data-target="banner"
                                    style="display: none;">
                                    Remove
                                </button>
                                <input type="hidden" name="banner_id" id="banner-id">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="products-section">
                <div class="section-header page-title align-left">
                    <h3>Products Assigned </h3>
                </div>
                <div class="top-filter-block">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Search">
                    </div>
                    <div class="table-controls action-buttons">
                        <button type="button" class="button small export-btn btn btn-white btn-black-white btn-primary-white">Export Products</button>
                        <button type="button" class="button small primary add-product-btn btn btn-blue">Add New Products</button>
                    </div>
                </div>

                <table id="product-assigned-table" class="products-data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Gift Card</th>
                            <th>Product Name</th>
                            <th>Denomination Type</th>
                            <th>Denomination</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody><!-- AJAX populated --></tbody>
                </table>
                <div class="dataTables-info"></div>
            </div>
            <div class="category-message"></div>
            <div class="page-bottom-toolbar">
                <div class="right-block">
                    <div class="save-next-button-controls page-bottom-actions form-actions">
                        <button class="pagination-button btn btn-primary btn-black-white btn-primary-black">Save Changes</button>
                    </div>
                </div>
            </div>
            <!--         
        <div class="form-actions">
            <button type="submit" class="button primary">Save Changes</button>
        </div> -->
        </form>
    </div>

    <script>
        jQuery(document).ready(function ($) {
            jQuery('.search-input').on('keyup', function () {
                var value = jQuery(this).val().toLowerCase();

                // Filter DataTable
                if (jQuery('.products-data-table').length > 0) {
                    jQuery('.products-data-table').DataTable().search(value).draw();
                }

            });
        });
    </script>


    <!-- Add this just before the closing </div> of your category-edit-view -->
    <div id="add-products-popup" class="popup-overlay custom-popup" style="display: none;">
        <div class="popup-content custom-main-modal">
            <div class="popup-header custom-popup-header">
                <h2 class="title-center">Add New Products</h2>
                <button type="button" class="close-popup close-modal">&times;</button>
            </div>

            <div class="popup-body custom-modal-header-body">
                <div class="search-products form-group round-search-box">
                    <div class="control-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input class="form-control" type="text" id="product-search" placeholder="Search products..."
                            class="regular-text">
                    </div>
                </div>

                <div class="products-list-container search-list-container">
                    <ul id="products-list" class="product-check-list check-list">
                        <!-- Products will be loaded here via AJAX -->
                    </ul>
                </div>
            </div>

            <div class="popup-footer custom-modal-footer center">
                <button type="button" id="assign-products" class="btn btn-primary btn-black-white btn-primary-black">Add Products</button>
            </div>
        </div>
    </div>

    <!-- Create New Category Form -->
    <div class="category-create-view" <?= $display_create_view; ?>>
        <div class="cat-response-message"></div>
        <div class="page-title align-left">
            <h2>Create New Category</h2>
        </div>
        <div class="add-new-category">
            <form id="category-create-form" class="category-edit-form">

                <div class="card-large-white">
                    <div class="form-section form-group">
                        <div class="col left-content">
                            <label class="label" for="category-name">Category Name<span class="validate">*</span></label>
                        </div>
                        <div class="col right-content">
                            <input class="form-control" type="text" name="category_name" id="category-name"
                                class="regular-text">
                        </div>
                    </div>

                    <div class="form-section form-group">
                        <div class="col left-content">
                            <label for="category-description-new">Description</label>
                        </div>
                        <div class="col right-content">
                            <textarea class="form-control" name="description" id="category-description-new" rows="3"
                                class="large-text"></textarea>
                        </div>
                    </div>

                    <div class="form-section form-group">
                        <div class="col left-content">
                            <label class="label" for="category-priority">Priority<span class="validate">*</span></label>
                            <p class="description">Lower numbers show first</p>
                        </div>
                        <div class="col right-content">
                            <input class="form-control" type="number" name="priority" id="category-priority" class="small-text" min="1" value="1">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="col left-content">
                            <h3>Thumbnails</h3>
                            <p>Square image at least 100x100 pixels</p>
                        </div>
                        <div class="col right-content">
                            <div class="image-preview-container image-thumbnail">
                                <div class="image-preview-placeholder">
                                    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/add-image-place-sm.png"
                                        alt="placeholder">
                                    <img id="create-thumbnail-preview" src="" class="upload-preview" style="display: none;">
                                </div>
                                <div class="button-group">
                                    <button type="button" class="button small upload-image-button1"
                                        data-target="create-thumbnail">
                                        Upload Thumbnail
                                    </button>
                                    <button type="button" class="button small remove-image-button"
                                        data-target="create-thumbnail" style="display: none;">
                                        Remove
                                    </button>
                                    <input type="hidden" name="thumbnail_id" id="create-thumbnail-id">
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="col left-content label">
                            <h3>Icon Image<span class="validate">*</span></h3>
                            <p>Square image at least 256x256 pixels</p>
                        </div>
                        <div class="col right-content">
                            <div class="image-preview-container image-icon">
                                <div class="image-preview-placeholder">
                                    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/add-image-place-md.png"
                                        alt="placeholder">
                                    <img id="create-icon-preview" src="" class="upload-preview" style="display: none;">
                                </div>
                                <div class="button-group">
                                    <button type="button" class="button small upload-image-button1"
                                        data-target="create-icon">
                                        Upload Icon
                                    </button>
                                    <button type="button" class="button small remove-image-button" data-target="create-icon"
                                        style="display: none;">
                                        Remove
                                    </button>
                                    <input type="hidden" name="icon_id" id="create-icon-id">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="col left-content">
                            <h3>Banner Image</h3>
                            <p>At least 1406x3456 pixels</p>
                        </div>
                        <div class="col right-content">
                            <div class="image-preview-container image-banner">
                                <div class="image-preview-placeholder">
                                    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/banner-placeholder.png"
                                        alt="placeholder">
                                    <img id="create-banner-preview" src="" class="upload-preview" style="display: none;">
                                </div>
                                <div class="button-group">
                                    <button type="button" class="button small upload-image-button1"
                                        data-target="create-banner">
                                        Upload Banner
                                    </button>
                                    <button type="button" class="button small remove-image-button"
                                        data-target="create-banner" style="display: none;">
                                        Remove
                                    </button>
                                    <input type="hidden" name="banner_id" id="create-banner-id">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section form-group">
                        <div class="col left-content">
                            <label class="label" for="category-status">Status<span class="validate">*</span></label>
                        </div>
                        <div class="col right-content control-wrapper">
                            <select name="category_status" id="category-status-new" class="regular-text">
                                <option value="active" selected>Active</option>
                                <option value="pending">Pending</option>
                                <option value="deactivated">Deactivated</option>
                            </select>
                        </div>
                    </div>


                </div>

                <div class="products-assignment-section">
                    <h3>Assign Products</h3>
                    <div class="search-products form-group round-search-box">
                        <div class="control-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input class="form-control" type="text" id="create-product-search"
                                placeholder="Search products..." class="regular-text">
                        </div>
                    </div>

                    <div class="products-list-container search-list-container">
                        <ul id="create-products-list" class="product-check-list check-list">
                            <!-- Products will be loaded here via AJAX -->
                        </ul>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-black-white btn-primary-black">Create Category</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        jQuery(document).ready(function ($) {

            let productTable = null;

            // Edit Category Click
            jQuery('.category-second-list-container').on('click', '.edit-category, .category-link', function (e) {
                console.log('Cliekced...');
                // e.preventDefault();
                jQuery('.category-listing-section').hide();
                jQuery('.category-thumbnail-grid').hide();
                jQuery('.category-management-header').hide();
                jQuery('.category-title').hide();
                jQuery('.category-edit-view').show();
                jQuery('.back-to-categorylist-wrapper').show();

                let termId = jQuery(this).data('term-id');
                $.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: 'POST',
                    data: {
                        action: 'fetch_category_details',
                        term_id: termId,
                        nonce: categoryAjax.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            // Update basic fields
                            jQuery('#edit-term-id').val(response.data.term_id);
                            jQuery('#display-category-id').text(response.data.term_id);
                            jQuery('#category-name-display').text(response.data.name); // Display as text
                            jQuery('#category-description').val(response.data.description);
                            jQuery('#category-status').val(response.data.status);
                            jQuery('#category-priority').val(response.data.priority);

                            // Update status badge
                            jQuery('#display-category-status')
                                .text(response.data.status.charAt(0).toUpperCase() + response.data.status.slice(1))
                                .removeClass().addClass('status-badge ' + response.data.status);
                            jQuery('#display-category-status').parent().addClass('status ' + response.data.status);;

                            // Update images
                            updateImagePreview('thumbnail', response.data.thumbnail, response.data.thumbnail_id);
                            updateImagePreview('icon', response.data.icon, response.data.icon_id);
                            updateImagePreview('banner', response.data.banner, response.data.banner_id);

                            // Initialize/reinitialize DataTable
                            if (productTable) {
                                productTable.destroy();
                            }

                            // Replace the existing DataTable initialization with:
                            productTable = jQuery('#product-assigned-table').DataTable({
                                processing: true,
                                serverSide: true,
                                ajax: {
                                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                                    type: 'POST',
                                    data: {
                                        action: 'fetch_category_products',
                                        term_id: termId,
                                        nonce: categoryAjax.nonce
                                    }
                                },
                                columns: [
                                    { data: 'rank', className: 'dt-center' },
                                    {
                                        data: 'image',
                                        render: function (data, type, row) {
                                            return `<div class="image img-rounded"><span class="image-inner"><img src="${data}"></span></div>`;
                                        }
                                    },
                                    {
                                        data: 'name',
                                        render: function (data) {
                                            return `<div class="card-name"><span class="">${data}</span></div>`;
                                        }
                                    },
                                    { data: 'denomination_type', className: 'dt-center' },
                                    { data: 'denomination', className: 'dt-center' },
                                    {
                                        data: 'status',
                                        className: 'dt-center',
                                        render: function (data) {
                                            return `<div class="status ${data.toLowerCase()}"><span class="">${data}</span></div>`;
                                        }
                                    }
                                ],
                                paging: true,
                                pageLength: 10,
                                dom: '<"top"f>rt<"bottom"lip>',
                                responsive: true,
                                scrollX: true,
                                language: {
                                    search: "Search products:",
                                    lengthMenu: "Show _MENU_ products per page",
                                    info: "Showing _START_ to _END_ of _TOTAL_ products",
                                    paginate: {
                                        previous: "&laquo;",
                                        next: "&raquo;"
                                    }
                                }
                            });
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('AJAX Error: ' + error);
                    }
                });
            });

            // Helper function to update image previews
            function updateImagePreview(target, url, id) {
                const preview = jQuery(`#${target}-preview`);
                const removeBtn = jQuery(`button.remove-image-button[data-target="${target}"]`);

                if (url && url !== '') {
                    preview.attr('src', url).show();
                    jQuery(`#${target}-id`).val(id);
                    removeBtn.show();
                } else {
                    preview.hide().attr('src', '');
                    jQuery(`#${target}-id`).val('');
                    removeBtn.hide();
                }
            }

            // Save Changes Handler - Updated
            jQuery('#category-edit-form').on('submit', function (e) {
                e.preventDefault();

                const formData = jQuery(this).serializeArray();
                const termId = jQuery('#edit-term-id').val();

                // Show loading indicator
                const submitBtn = jQuery(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).text('Saving...');

                $.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: 'POST',
                    data: {
                        action: 'save_category_changes',
                        nonce: categoryAjax.nonce,
                        term_id: termId,
                        category_name: jQuery('#category-name').val(),
                        description: jQuery('#category-description').val(),
                        status: jQuery('#category-status').val(),
                        thumbnail_id: jQuery('#thumbnail-id').val(),
                        icon_id: jQuery('#icon-id').val(),
                        banner_id: jQuery('#banner-id').val(),
                    },
                    success: function (response) {
                        submitBtn.prop('disabled', false).text('Save Changes');

                        if (response.success) {
                            // Update the list view dynamically
                            updateCategoryRowInList(response.data);

                            jQuery('.category-message')
                                .html('<div class="alert alert-success">Category updated successfully!</div>')
                                .fadeIn();
                        } else {
                            jQuery('.category-message')
                                .html('<div class="alert alert-danger">Error: ' + response.data + '</div>')
                                .fadeIn();
                        }
                        setTimeout(function () {
                            jQuery('.category-message').fadeOut();
                        }, 5000);
                    },
                    error: function (xhr, status, error) {
                        jQuery('.category-message')
                            .html('<div class="alert alert-danger">AJAX Error: ' + error + '</div>')
                            .fadeIn();

                        setTimeout(function () {
                            jQuery('.category-message').fadeOut();
                        }, 5000);
                    }
                });
            });

            // Helper function to update the list view row
            function updateCategoryRowInList(data) {
                const row = jQuery(`#voucher-category-table tbody tr td:nth-child(3):contains("${data.term_id}")`).closest('tr');

                if (row.length) {
                    // Update basic info
                    row.find('td:nth-child(4)').text(data.name); // Category Name
                    row.find('td:nth-child(6)').text(data.description || 'No description'); // Description
                    row.find('td.status').text(data.status.charAt(0).toUpperCase() + data.status.slice(1))
                        .removeClass().addClass('status ' + data.status);

                    // Update images
                    if (data.thumbnail) {
                        row.find('.category-icon img').attr('src', data.thumbnail);
                        row.find('.category-image img').attr('src', data.thumbnail);
                    }

                    // Update the edit link data if needed
                    row.find('.edit-category').data('term-id', data.term_id);
                }
            }

            // Handle header checkbox click
            jQuery('#voucher-category-table thead').on('click', '.custom-checkbox', function () {
                var isChecked = jQuery(this).prop('checked');

                // Get all rows on current page and check/uncheck their checkboxes
                jQuery('#voucher-category-table').DataTable().rows({ page: 'current' }).nodes().tojQuery().find('input.custom-checkbox').prop('checked', isChecked);
            });

            // Uncheck header checkbox if any row checkbox is unchecked
            jQuery('#voucher-category-table tbody').on('change', 'input.custom-checkbox', function () {
                var totalCheckboxes = jQuery('#voucher-category-table').DataTable().rows({ page: 'current' }).nodes().tojQuery().find('input.custom-checkbox').length;
                var checkedCheckboxes = jQuery('#voucher-category-table').DataTable().rows({ page: 'current' }).nodes().tojQuery().find('input.custom-checkbox:checked').length;

                jQuery('#voucher-category-table thead .custom-checkbox').prop('checked', totalCheckboxes === checkedCheckboxes);
            });

            // Image Upload Handling
            jQuery('.upload-image-button').on('click', function () {
                const target = jQuery(this).data('target');
                const frame = wp.media({
                    title: 'Select or Upload Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });

                frame.on('select', function () {
                    const attachment = frame.state().get('selection').first().toJSON();
                    jQuery(`#${target}-preview`).attr('src', attachment.url).show();
                    jQuery(`#${target}-id`).val(attachment.id);
                    jQuery(`button.remove-image-button[data-target="${target}"]`).show();
                });

                frame.open();
            });

            // Remove Image Handling
            jQuery('.remove-image-button').on('click', function () {
                const target = jQuery(this).data('target');
                jQuery(`#${target}-preview`).attr('src', '').hide();
                jQuery(`#${target}-id`).val('');
                jQuery(this).hide();
            });

            // Navigation
            jQuery('.back-to-list, .cancel-changes').on('click', function () {
                jQuery('.category-edit-view').hide();
                jQuery('.category-listing-section, .category-management-header').show();
            });

            jQuery(document).on('click', '.add-product-btn', function () {

                const termId = jQuery('#edit-term-id').val();
                if (!termId) {
                    alert('No category selected!');
                    return;
                }
                showAddProductsPopup(termId);
            });
            // Add these new functions to your existing JavaScript
            function showAddProductsPopup(termId) {
                jQuery('#add-products-popup').show();
                loadProductsForPopup(termId);
            }

            function loadProductsForPopup(termId) {
                $.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: 'POST',
                    data: {
                        action: 'get_products_for_popup',
                        term_id: termId
                    },
                    beforeSend: function () {
                        jQuery('#products-list').html('<li class="loading">Loading products...</li>');
                    },
                    success: function (response) {
                        if (response.success) {
                            renderProductsList(response.data.products, response.data.assigned_products);
                        } else {
                            jQuery('#products-list').html('<li class="error">Error loading products</li>');
                        }
                    },
                    error: function () {
                        jQuery('#products-list').html('<li class="error">Error loading products</li>');
                    }
                });
            }

            function renderProductsList(products, assignedProducts) {
                const $list = jQuery('#products-list').empty();

                if (products.length === 0) {
                    $list.append('<li>No products found</li>');
                    return;
                }

                products.forEach(product => {
                    const isAssigned = assignedProducts.includes(product.id);
                    $list.append(`
            <li>
                <div class="form-group">
                    <div class="form-check checkbox">
                        <input type="checkbox" id="product-${product.id}" value="${product.id}" ${isAssigned ? 'checked' : ''}>
                        <label for="product-${product.id}"> <img src="${product.image}" class="product-image" alt="${product.name}"> ${product.name}</label>
                    </div>
                </div>
            </li>
        `);
                });
            }



            jQuery('.close-popup, .popup-overlay').on('click', function (e) {
                if (e.target === this) {
                    jQuery('#add-products-popup').hide();
                }
            });

            jQuery('#assign-products').on('click', function () {
                const termId = jQuery('#edit-term-id').val();
                const productIds = [];

                jQuery('#products-list input[type="checkbox"]:checked').each(function () {
                    productIds.push(jQuery(this).val());
                });

                $.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: 'POST',
                    data: {
                        action: 'assign_products_to_category',
                        term_id: termId,
                        product_ids: productIds,
                        nonce: categoryAjax.nonce
                    },
                    beforeSend: function () {
                        jQuery('#assign-products').prop('disabled', true).text('Assigning...');
                    },
                    success: function (response) {
                        jQuery('#assign-products').prop('disabled', false).text('Add Products');

                        if (response.success) {
                            jQuery('#add-products-popup').hide();
                            // Refresh the products table
                            if (productTable) {
                                productTable.destroy();
                            }
                            initializeProductTable(termId); // You'll need to create this function
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function () {
                        jQuery('#assign-products').prop('disabled', false).text('Add Products');
                        alert('Error assigning products');
                    }
                });
            });
            function initializeProductTable(termId) {
                if ($.fn.DataTable.isDataTable('#product-assigned-table')) {
                    jQuery('#product-assigned-table').DataTable().destroy();
                }

                productTable = jQuery('#product-assigned-table').DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    scrollX: true,
                    ajax: {
                        url: "<?php echo admin_url('admin-ajax.php'); ?>",
                        type: 'POST',
                        data: {
                            action: 'fetch_category_products',
                            term_id: termId,
                            nonce: categoryAjax.nonce
                        }
                    },
                    columns: [
                        {
                            data: 'rank',
                            className: 'dt-center',
                            orderable: false // Disable sorting for rank column
                        },
                        {
                            data: 'image',
                            render: function (data) {
                                return `<img src="${data}" style="max-width:50px;max-height:50px;">`;
                            },
                            orderable: false
                        },
                        { data: 'name' },
                        { data: 'denomination_type', className: 'dt-center' },
                        {
                            data: 'denomination',
                            className: 'dt-right',
                            render: function (data) {
                                return data ? `$${parseFloat(data).toFixed(2)}` : 'N/A';
                            }
                        },
                        {
                            data: 'status',
                            className: 'dt-center',
                            render: function (data) {
                                return `<div class="status ${data.toLowerCase()}"><span class="">${data}</span></div>`;
                            }
                        }
                    ],
                    paging: true,
                    pageLength: 10,
                    dom: '<"top"f>rt<"bottom"lip>',
                    responsive: true,
                    order: [[2, 'asc']], // Default sort by product name
                    language: {
                        search: "Search products:",
                        lengthMenu: "Show _MENU_ products per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ products",
                        paginate: {
                            previous: "&laquo;",
                            next: "&raquo;"
                        }
                    }
                });
            }


            // Add this to your existing JavaScript
            jQuery('.export-btn').on('click', function () {
                // Get current DataTable parameters
                const termId = jQuery('#edit-term-id').val();
                const searchValue = productTable.search();

                // Create temporary form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = "<?php echo admin_url('admin-ajax.php'); ?>";

                // Add parameters
                const params = {
                    action: 'export_category_products',
                    term_id: termId,
                    search: searchValue,
                    security: "<?php echo wp_create_nonce('export_products_nonce'); ?>"
                };

                for (const key in params) {
                    if (params.hasOwnProperty(key)) {
                        const hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = key;
                        hiddenField.value = params[key];
                        form.appendChild(hiddenField);
                    }
                }

                document.body.appendChild(form);
                form.submit();
            });
            // Add search functionality
            jQuery('#product-search').on('input', function () {
                const searchTerm = jQuery(this).val().toLowerCase();
                jQuery('#products-list li').each(function () {
                    const text = jQuery(this).text().toLowerCase();
                    jQuery(this).toggle(text.includes(searchTerm));
                });
            });
            // Load products for create form
            function loadProductsForCreateForm() {
                $.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: 'POST',
                    data: {
                        action: 'get_all_products_for_assignment'
                    },
                    beforeSend: function () {
                        jQuery('#create-products-list').html('<li class="loading">Loading products...</li>');
                    },
                    success: function (response) {
                        if (response.success) {
                            renderCreateProductsList(response.data.products);
                        } else {
                            jQuery('#create-products-list').html('<li class="error">Error loading products</li>');
                        }
                    },
                    error: function () {
                        jQuery('#create-products-list').html('<li class="error">Error loading products</li>');
                    }
                });
            }

            // Render products list for create form
            function renderCreateProductsList(products) {
                const $list = jQuery('#create-products-list').empty();

                if (products.length === 0) {
                    $list.append('<li>No products found</li>');
                    return;
                }

                products.forEach(product => {
                    $list.append(`
            <li>
                <div class="form-check checkbox">
                     <input type="checkbox" id="create-product-${product.id}" value="${product.id}">
                    <label for="create-product-${product.id}"><img src="${product.image}" class="product-image" alt="${product.name}"> ${product.name}</label>
                </div>
            </li>
        `);
                });
            }

            // Search functionality for create form
            jQuery('#create-product-search').on('input', function () {
                const searchTerm = jQuery(this).val().toLowerCase();
                jQuery('#create-products-list li').each(function () {
                    const text = jQuery(this).text().toLowerCase();
                    jQuery(this).toggle(text.includes(searchTerm));
                });
            });
            jQuery('#create-new-category').on('click', function () {
                jQuery('.category-title').hide();
                jQuery('.category-listing-section, .category-thumbnail-grid, .category-management-header').hide();
                jQuery('.category-create-view').show();
                loadProductsForCreateForm(); // Load products when form is shown

            });

            // Cancel Creation
            jQuery('.cancel-create').on('click', function () {
                jQuery('.category-create-view').hide();
                if (jQuery('.category-second-list-container').hasClass('list')) {
                    jQuery('.category-listing-section, .category-management-header').show();
                }
                if (jQuery('.category-second-list-container').hasClass('thumbnail')) {
                    jQuery('.category-thumbnail-grid').show();
                }
                jQuery('#category-create-form')[0].reset();
                jQuery('.upload-preview').hide().attr('src', '');
                jQuery('.remove-image-button').hide();
            });

            // Handle image upload for create form
            jQuery(document).on('click', '.category-create-view .upload-image-button1', function (e) {
                e.preventDefault();
                const target = jQuery(this).data('target');
                const frame = wp.media({
                    title: 'Select or Upload Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });

                // When an image is selected
                frame.on('select', function () {
                    const attachment = frame.state().get('selection').first().toJSON();
                    jQuery(`#${target}-preview`).attr('src', attachment.url).show();
                    jQuery(`#${target}-id`).val(attachment.id);
                    jQuery(`.remove-image-button[data-target="${target}"]`).show();
                    frame.close(); // Close the media frame after selection
                });

                frame.open();
            });

            // Handle image removal for create form
            jQuery(document).on('click', '.category-create-view .remove-image-button', function (e) {
                e.preventDefault();
                const target = jQuery(this).data('target');
                jQuery(`#${target}-preview`).attr('src', '').hide();
                jQuery(`#${target}-id`).val('');
                jQuery(this).hide();
            });
            jQuery('#category-create-form').on('submit', function (e) {
                console.log('1');
                e.preventDefault();

                // Clear previous messages
                jQuery('.cat-response-message').html('').removeClass('success-message error-message alert alert-danger');

                // Collect field values
                const categoryName = jQuery('#category-name').val().trim();
                const priority     = jQuery('#category-priority').val().trim();
                const iconId       = jQuery('#create-icon-id').val().trim();
                const status       = jQuery('#category-status-new').val().trim();

                // Validation checks
                let errors = [];
                if (!categoryName) errors.push('Category Name is required.');
                if (!priority) errors.push('Priority is required.');
                if (!iconId) errors.push('Icon Image is required.');
                if (!status) errors.push('Status is required.');

                if (errors.length > 0) {
                    const errorHtml = errors.map(err => `<div>${err}</div>`).join('');
                    jQuery('.cat-response-message')
                        .addClass('alert alert-danger')
                        .html(errorHtml)
                        .fadeIn();

                    jQuery('html, body').animate({
                        scrollTop: jQuery('.cat-response-message').offset().top - 100
                    }, 600);

                    return false; // Stop form submit
                }

                // Collect selected products
                const productIds = [];
                jQuery('#create-products-list input[type="checkbox"]:checked').each(function () {
                    productIds.push(jQuery(this).val());
                });

                // Collect all form data including images
                var formData = {
                    action: 'create_new_product_category',
                    nonce: "<?php echo wp_create_nonce('bulk_create_category_nonce'); ?>",
                    category_name: categoryName,
                    description: jQuery('#category-description-new').val(),
                    category_status: status,
                    priority: priority,
                    thumbnail_id: jQuery('#create-thumbnail-id').val(),
                    icon_id: iconId,
                    banner_id: jQuery('#create-banner-id').val(),
                    product_ids: productIds
                };

                var submitBtn = jQuery(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).text('Creating...');

                $.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: 'POST',
                    data: formData,
                    success: function (response) {
                        submitBtn.prop('disabled', false).text('Create Category');
                        if (response.success) {
                            let successMessage = jQuery('.cat-response-message');

                            successMessage
                                .removeClass('alert alert-danger')
                                .addClass('success-message')
                                .html('Category "' + response.data.name + '" created successfully!')
                                .get(0)
                                .scrollIntoView({ behavior: 'smooth', block: 'center' });

                            setTimeout(function () {
                                window.location.reload();
                            }, 2000);

                        } else {
                            jQuery('.cat-response-message')
                                .addClass('error-message alert alert-danger')
                                .html('Error: ' + response.data)
                                .get(0)
                                .scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                });
            });

        });
    </script>


<?php }