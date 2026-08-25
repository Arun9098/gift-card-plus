<?php
/**
 * Template Name: Brand listing 
 */
get_header();
wp_enqueue_media();
// wp_enqueue_script('jquery-dataTables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js');
// wp_enqueue_style('jquery-dataTables-css', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css');
?>
<div class="page-spacer-top"></div>
<section class="brands-list-section">
    <div class="container">
        <div class="brand-management-wrap">
            <div class="brand-management-header">
                <div class="brand-management-title">
                    <h1 class="wp-heading-inline small-h2">View All Brands </h1>
                </div>
                <div class="header-actions top-filter-block">
                    <div class="search-control">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="brand-search" class="search-input" placeholder="Search brands...">
                        <!-- <button class="button button-small filter-btn"><i class="fas fa-sliders-h"></i>Filters</button> -->
                    </div>
                    <div class="action-buttons">
                        <button id="bulk-edit-brands"
                            class="btn btn-white bulk-edit-brands action-button size-sm btn-black-white btn-primary-white">Edit</button>
                        <button class="btn button-small btn-white export-btn size-sm btn-black-white btn-primary-white" id="export-brands">Export</button>
                        <button id="create-new-brand" class="btn button-small button-primary btn-blue size-sm">Create
                            New Brand</button>
                        <div class="view-toggle">
                            <button id="list-view-btn" class="view-btn active filter-btn">
                                <i class="fas fa-list"></i>
                            </button>
                            <button id="thumbnail-view-btn" class="view-btn">
                                <i class="fas fa-th-large"></i>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
            <div id="brand-edit-error" class="error-message" style="color: red; margin-top: 10px;"></div>
            <div class="csv-response"></div>
            <div class="brand-listing-section">
                <div id="brand-list-view">
                    <table id="brands-table" class="brand-table display">
                        <thead>
                            <tr>
                                <th> <input type="checkbox" id="select-all" class="custom-checkbox"> </th>
                                <th data-head_slug="gift_card">Brand logo</th>
                                <th data-head_slug="gift_id">ID</th>
                                <th data-head_slug="brand_name">Brand Name</th>
                                <th data-head_slug="assigned">Assigned</th>
                                <th data-head_slug="brand_status">Status</th>
                                <th data-head_slug="details">Details</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $brands = get_terms([
                                'taxonomy' => 'product_brand',
                                'hide_empty' => false,
                                'orderby' => 'name',
                            ]);

                            if (!empty($brands) && !is_wp_error($brands)) {
                                foreach ($brands as $brand) {
                                    $thumbnail_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
                                    $brand_image = '';

                                    if ($thumbnail_id) {
                                        $image_src = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
                                        if (!empty($image_src[0])) {
                                            $brand_image = $image_src[0];
                                        }
                                    }

                                    $status = get_term_meta($brand->term_id, 'brand_status', true) ?: 'active';
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="brand-checkbox custom-checkbox"></td>
                                        <td class="image img-rounded">
                                            <span class="image-inner">
                                                <?php if (!empty($brand_image)): ?>
                                                    <img src="<?php echo esc_url($brand_image); ?>"
                                                        alt="<?php echo esc_attr($brand->name); ?>" class="brand-thumbnail"
                                                        width="40">
                                                <?php else: ?>
                                                    <!-- Empty cell or you can add a placeholder icon if needed -->
                                                    <img src="<?php echo esc_url(wc_placeholder_img_src()); ?>" alt="No image"
                                                        width="40">
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($brand->term_id); ?></td>
                                        <td class="card-name"><?php echo esc_html($brand->name); ?></td>
                                        <td><?php echo esc_html($brand->count); ?></td>
                                        <td class="status <?php echo esc_attr(strtolower($status)); ?>">
                                            <span><?php echo esc_html(ucwords(str_replace('-', ' ', $status))); ?></span>
                                        </td>
                                        <td>
                                            <a href="#" class="edit-brand"
                                                data-term-id="<?php echo esc_attr($brand->term_id); ?>">
                                                View/Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php }
                            } else {
                                echo '<tr><td colspan="7">No brands found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                    <div class="page-bottom-toolbar">
                        <div class="right-block">
                            <div class="save-next-button-controls">
                                <button class="pagination-button btn btn-white btn-black-white btn-primary-white">Save</button>
                                <button class="pagination-button next btn btn-primary btn-black-white btn-primary-black">Next</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thumbnail View (hidden by default) -->
                <div id="brand-thumbnail-view" style="display: none;">
                    <div class="brand-thumbnail-grid">
                        <?php
                        if (!empty($brands) && !is_wp_error($brands)) {
                            foreach ($brands as $brand) {
                                $thumbnail_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
                                $brand_image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : wc_placeholder_img_src();
                                ?>
                                <div class="brand-card">
                                    <img src="<?php echo esc_url($brand_image); ?>" alt="<?php echo esc_attr($brand->name); ?>">
                                    <h3 data-title="<?php echo esc_html($brand->name); ?>"><?php echo esc_html($brand->name); ?>
                                    </h3>
                                    <!-- <a href="#" class="edit-brand" data-term-id="<?php echo esc_attr($brand->term_id); ?>">
                                View/Edit
                            </a> -->
                                </div>
                            <?php }
                        } else {
                            echo '<p>No brands found</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <div id="create-brand-modal" class="brand-modal-overlay custom-popup" style="display:none;">
                <div class="brand-modal custom-main-modal">
                    <div class="brand-modal-header custom-modal-header">
                        <h2>Create New Brand</h2>
                        <span class="close-modal">&times;</span>
                    </div>
                    <div class="brand-modal-body custom-modal-header-body">
                        <form id="create-brand-form">
                            <div class="form-group">
                                <label for="brand_name">Brand Name</label>
                                <input type="text" id="brand_name" name="brand_name" required>
                            </div>
                            <div class="form-group">
                                <div class="image-upload-block">
                                    <label for="brand_logo_file">Brand Logo</label>
                                    <span class="size-guide">Square image at least 256x256 pixels</span>
                                    <div class="image-upload-item">


                                        <!-- Hidden input to store uploaded file URL or ID if needed -->
                                        <input type="hidden" id="brand_logo" name="brand_logo">
                                        <input type="file" id="brand_logo_file" accept="image/*" style="display: none;">
                                        <button type="button" class="upload-logo-btn image-inner"><img
                                                src="<?php echo get_template_directory_uri(); ?>/assets/images/placeholder-image.png"
                                                alt="Thumbnail preview" /></button>
                                        <img id="brand-logo-preview" src="<?php echo wc_placeholder_img_src(); ?>"
                                            alt="Brand Logo" class="preview-image image-inner">
                                    </div>
                                </div>
                            </div>
                            <div class="popup-footer center">
                                <div class="form-actions">
                                    <button type="submit" class="button btn btn-primary">Create Brand</button>
                                </div>
                            </div>
                        </form>
                        <div class="creat-brand-success"></div>
                    </div>
                </div>
            </div>
            <!-- <div class="brand-edit-view" style="display: none;"></div> -->
            <div class="justify-content-between align-items-center mb-3 back-to-brandlist-wrapper" style="display:none;">
                <a href="javascript:void(0);" type="button" class="back-to-brandlist" id="back-to-brandlist">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none">
                        <path d="M22.4598 8.95444H5.2559L12.772 2.32605C13.3727 1.79632 13.3727 0.927024 12.772 0.397296C12.1713 -0.132432 11.201 -0.132432 10.6004 0.397296L0.450505 9.34834C-0.150168 9.87807 -0.150168 10.7338 0.450505 11.2635L10.6004 20.2146C11.201 20.7443 12.1713 20.7443 12.772 20.2146C13.3727 19.6848 13.3727 18.8291 12.772 18.2994L5.2559 11.671H22.4598C23.3069 11.671 24 11.0598 24 10.3127C24 9.56567 23.3069 8.95444 22.4598 8.95444Z" fill="black"></path>
                    </svg>
                </a>
            </div>
            <form id="brand-edit-form" class="brand-edit-form" style="display:none;">
                <input type="hidden" name="term_id" id="edit-brand-id"></input>
                <div class="brand-edit-view" style="display: none;">
                    <label for="brand-name-display">Brand: </label>
                    <label id="brand-name-display" class="brand-name-text"></label>

                    <div class="form-group edit-form-status-label">
                        <select name="status" id="brand-status" class="regular-text">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="deactivated">Deactivated</option>
                            <option value="awaiting-publishing">Awaiting Publishing</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    <!-- <div class="edit-header">
                        <div class="header-info">
                            <div class="brand-meta">
                                <span class="brand-id">ID: <strong id="display-brand-id"></strong></span>
                                <span class="status-indicator">Status: <span class="status-badge" id="display-brand-status"></span></span>
                            </div>
                        </div>
                    </div> -->
                </div>
                <div class="edit-header">
                    <div class="header-info">
                        <div class="brand-meta">
                            <span class="brand-id">ID: <span id="display-brand-id"></span></span>
                        </div>
                    </div>
                </div>

                <div class="image-upload-section">
                    <div class="image-upload-group">
                        <div class="form-group">
                            <div class="flex-flow">
                                <label>Brand Logo</label>
                                <p class="description">Recommended size: 500x500px</p>
                            </div>
                            <div class="image-preview-container">
                                <div class="button-group">
                                    <input type="file" id="thumbnail-upload" class="image-upload-input"
                                        data-target="thumbnail" accept="image/*" style="display: none;">
                                    <button type="button" class="button small upload-image-button placeholder-image"
                                        data-target="thumbnail"> <img
                                            src="<?php echo get_template_directory_uri(); ?>/assets/images/placeholder-image.png"
                                            alt="Thumbnail preview" />
                                    </button>
                                    <input type="hidden" name="brand_logo_id" id="brand_logo_id">
                                </div>
                                <div class="image-preview-inner">
                                    <img id="thumbnail-preview" src="" class="upload-preview" style="display: none;">
                                    <button type="button" class="button small remove-image-button"
                                        data-target="thumbnail" style="display: none;">X</button>
                                </div>


                            </div>
                        </div>
                    </div>
                </div>
                <!-- <div class="form-actions">
                <button type="submit" class="button primary">Save Changes</button></div> -->
            </form>


            <!-- <div class="form-section">
            <div class="form-group">
                <label for="brand-description">Description</label>
                <textarea name="description" id="brand-description" rows="3" class="large-text"></textarea>
            </div> -->

            <!-- <label>Brand Name: <span id="brand-name-display" class="brand-name-text"></span></label> -->

            <div class="brand-edit-view" style="display: none;">
                <!-- Update the image upload section -->
                <div class="products-section">
                    <div class="page-title align-left">
                        <h1>Product for this Brand</h1>
                    </div>
                    <div class="section-header top-filter-block">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="brand-search" class="search-input" placeholder="Search brands...">
                            <!-- <button class="button button-small filter-btn"><i class="fas fa-sliders-h"></i>Filters</button> -->
                        </div>
                        <div class="action-buttons">
                            <button type="button" class="button small export-btn btn btn-black-white btn-primary-white btn-white"
                                id="export-brands-product">Export</button>
                            <button type="button" class="button small primary add-product-btn btn btn-blue">Add
                                Products</button>
                            <div class="error-fetching-products"></div>
                        </div>
                    </div>

                    <table id="brand-product-assigned-table" class="products-data-table">
                        <thead>
                            <tr>
                                <th>
                                    Rank
                                    <span class="dashicons dashicons-filter filter-icon" data-column="rank"></span>
                                </th>
                                <th>
                                    Image
                                    <span class="dashicons dashicons-filter filter-icon" data-column="image"></span>
                                </th>
                                <th>
                                    Product Name
                                    <span class="dashicons dashicons-filter filter-icon"
                                        data-column="product-name"></span>
                                </th>
                                <th>
                                    Denomination Type
                                    <span class="dashicons dashicons-filter filter-icon"
                                        data-column="denomination-type"></span>
                                </th>
                                <th>
                                    Price
                                    <span class="dashicons dashicons-filter filter-icon" data-column="price"></span>
                                </th>
                                <th>
                                    Status
                                    <span class="dashicons dashicons-filter filter-icon" data-column="status"></span>
                                </th>
                            </tr>
                        </thead>

                        <tbody><!-- AJAX populated --></tbody>
                    </table>
                    <div class="dataTables-info"></div>
                </div>
                <div class="success-brand-message"></div>
                <div class="page-bottom-toolbar">
                    <div class="form-actions right-block">
                        <button type="submit" id="save-brand-button" class="button primary btn btn-primary btn-black-white btn-primary-black">Save
                            Changes</button>
                    </div>
                </div>
            </div>

            <!-- Add this just before the closing </div> of your category-edit-view -->
            <div id="add-products-popup" class="popup-overlay custom-popup" style="display: none;">
                <div class="custom-main-modal">
                    <div class="popup-header custom-modal-header">
                        <h3>Add New Products</h3>
                        <button type="button" class="close-popup close-modal">&times;</button>
                    </div>

                    <div class="popup-body custom-modal-header-body">
                        <div class="search-products form-group">
                            <input type="text" id="product-search" placeholder="Search products..."
                                class="regular-text">
                        </div>

                        <div class="products-list-container">
                            <ul id="products-list">
                                <!-- Products will be loaded here via AJAX -->
                            </ul>
                        </div>
                    </div>

                    <div class="popup-footer center">
                        <div class="form-actions">
                            <button type="button" id="assign-products" class="button btn btn-primary">Add
                                Products</button>
                            <div class="added-product-message"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    /* Brand Edit View Styles */


    .brand-edit-view .edit-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #ddd;
    }

    div#brand-product-assigned-table_filter,
    .brands-list-section .edit-header .header-info {
        display: block;
    }

    .brand-meta {
        display: flex;
        gap: 2rem;
        align-items: center;
    }

    .brand-id strong {
        color: #2271b1;
    }

    .creat-brand-success.success {
        color: green;
        margin-top: 10px;
    }

    .creat-brand-success.error {
        color: red;
        margin-top: 10px;
    }

    .error-fetching-products {
        color: red;
        margin-top: 10px;
        font-weight: bold;
    }

    .added-product-message.success {
        color: green;
        margin-top: 10px;
    }

    .added-product-message.error {
        color: red;
        margin-top: 10px;
    }

    .success-brand-message.success {
        color: green;
        margin-top: 10px;
    }

    .success-brand-message.error {
        color: red;
        margin-top: 10px;
    }


    .status-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 0.9em;
        text-transform: capitalize;
    }



    .brand-edit-form .form-section {
        margin-bottom: 2rem;
        padding: 1rem;
        background: #f9f9f9;
        border-radius: 4px;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .large-text {
        width: 100%;
        max-width: 600px;
        min-height: 100px;
    }

    .image-upload-group {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
    }

    .image-preview-container {
        margin-top: 0.5rem;
    }

    .button-group {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .products-section {
        margin-top: 2rem;
    }

    .products-data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .products-data-table th {
        background: #f6f7f7;
        padding: 12px;
        text-align: left;
        border-bottom: 2px solid #dcdcde;
    }

    .products-data-table td {
        padding: 12px;
        border-bottom: 1px solid #dcdcde;
        vertical-align: middle;
    }

    .form-actions {
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid #ddd;
        text-align: right;
    }

    .form-actions {}

    /* Make action buttons smaller */
    .brand-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .form-group input[type="text"],
    .form-group select {
        width: 100%;
        padding: 8px 10px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }


    .preview-image {
        width: 60px;
        height: 60px;
        object-fit: contain;
        border: 1px solid #ccc;
        border-radius: 4px;
        background: #fff;
    }

    .form-actions {
        text-align: center;
    }

    /* .view-toggle {
            display: flex;
            gap: 8px;
            align-items: center;
        } */

    /* .view-btn {
            padding: 6px 12px;
            border: 1px solid #ccc;
            background: #f1f1f1;
            cursor: pointer;
            border-radius: 4px;
        } */

    /* .view-btn.active {
            background: #2271b1;
            color: white;
            border-color: #2271b1;
        } */

    .brand-thumbnail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .brand-card {
        background: #f9f9f9;
        border: 1px solid #ddd;
        padding: 16px;
        border-radius: 6px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .brand-card img {
        width: 100%;
        height: 100px;
        object-fit: contain;
        margin-bottom: 10px;
    }

    .brand-card h3 {
        margin: 8px 0;
        font-size: 16px;
    }

    .brand-card .edit-brand {
        color: #2271b1;
        text-decoration: none;
        font-weight: 500;
    }

    .brand-management-wrap {
        padding: 20px;
        background: #fff;
        margin: 20px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .search-control .filter-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 5px;
        height: 44px;
        border-radius: 8px;
    }

    .search-control {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-grow: 1;
        max-width: 500px;
        position: relative;
        width: 300px;
    }


    .search-input {
        width: 100% !important;
        padding: 10px 15px 10px 35px !important;
        border: 1px solid #e0e0e0 !important;
        font-size: 14px !important;
        transition: border-color 0.3s !important;
        height: 44px;
    }

    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9e9e9e;
        font-size: 14px;
    }

    /* .brand-table {
            border-collapse: collapse;
            margin: 15px 0;
        }

        .brand-table th {
            background: #f6f7f7;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dcdcde;
        }

        .brand-table td {
            padding: 12px;
            border-bottom: 1px solid #dcdcde;
            vertical-align: middle;
        } */

    .brand-thumbnail {
        border-radius: 3px;
        object-fit: cover;
        height: 40px;
        width: 40px;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_info {
        margin: 15px 0;
        font-size: 13px;
    }

    .dataTables_wrapper .dataTables_paginate {
        margin: 15px 0;
    }

    .results-per-page {
        margin-left: 8px;
        padding: 4px 8px;
        border-radius: 3px;
    }

    .edit-brand {
        color: #2271b1;
        text-decoration: none;
    }

    .edit-brand:hover {
        color: #135e96;
    }

    .brand-checkbox {
        margin: 0 8px;
    }

    .popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    }



    .popup-header {
        padding: 15px 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .popup-header h3 {
        margin: 0;
    }

    .close-popup {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }

    .popup-body {
        padding: 20px;
        flex-grow: 1;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .search-products {
        margin-bottom: 15px;
    }

    .products-list-container {
        flex-grow: 1;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    #products-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    #products-list li {
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
    }

    #products-list li:last-child {
        border-bottom: none;
    }

    #products-list li input[type="checkbox"] {
        margin-right: 10px;
    }

    .product-image {
        width: 40px;
        height: 40px;
        object-fit: cover;
        margin-right: 10px;
        border-radius: 3px;
    }

    /* Add to your existing CSS */
    .products-assignment-section {
        margin-top: 30px;
        border-top: 1px solid #eee;
        padding-top: 20px;
    }

    .products-assignment-section h3 {
        margin-bottom: 15px;
    }
</style>

<?php get_footer(); ?>