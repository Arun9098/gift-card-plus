<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/*
Template Name:  Bulk Create Product
*/

// Initialize display variables
$display_status = '';
$display_status2 = ' style="display: none;"';
$display_status_title = 'Bulk Add Products';
$display_status_button_title = 'Create Products';

// Check URL parameters
if ((isset($_GET['bulk-edit-products']) && $_GET['bulk-edit-products'] == 'true') || 
    (isset($_GET['bulk-create-products']) && $_GET['bulk-create-products'] == 'true')) {
    $display_status = ' style="display: none;"';
    $display_status2 = '';
}

if (isset($_GET['bulk-edit-products']) && $_GET['bulk-edit-products'] == 'true') {
    $display_status_title = 'Bulk Edit Products';
    $display_status_button_title = 'Update Products';
}

$selected_cat = '';
if( isset($_GET['cat']) && !empty($_GET['cat']) ){
    $selected_cat = $_GET['cat'];
}

get_header();
?>
<div class="page-spacer-top"></div>
<div class="product-container container">
    <?php
    // Get the Vouchers parent category
    $voucher_category = get_term_by('name', 'Vouchers', 'product_cat');
    // echo '<pre>';
    // print_r($voucher_category);
    // echo '</pre>';
    // exit;
    // if ($voucher_category && !is_wp_error($voucher_category)):
        // Get child categories of Vouchers
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            // 'parent' => $voucher_category->term_id,
            'meta_key' => 'priority',
            'orderby' => 'meta_value_num',
            'order' => 'ASC'
        ]);

        if (!empty($categories) && !is_wp_error($categories)): ?>
            <div class="top-title-actions voucher-categories-header"<?=$display_status;?>>
                <h3 class="view-all-pro-title">View All Products</h3>
                <a href="<?=site_url('all-products');?>"
                    class="btn view-all-categories-btn btn-black-white btn-primary-white">
                    View All
                </a>
            </div>
            <div class="top-header-carousel categories owl-carousel" <?=$display_status;?>>
                <?php foreach ($categories as $category):
                    $thumbnail_id = get_term_meta($category->term_id, 'category_icon', true);
                    $category_image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
                    
                    $category_icon = $category_image ? '<img src="' . esc_url($category_image) . '" alt="' . esc_attr($category->name) . '" class="category-image">' : '<span class="category-placeholder" aria-label="' . esc_attr($category->name) . '">' . esc_html($category->name) . '</span>';
                    
                    $status = get_term_meta($category->term_id, 'category_status', true) ?: 'active';
                    $status_class = $status;
                    $status_text = ucfirst($status);

                    $selected_cat_class = '';
                    /*if( $selected_cat == $category->slug ){
                        $selected_cat_class = ' selected_cat';
                    }*/

                    $selected_cat_slugs = array();
                    if( isset($_REQUEST['cat']) && !empty($_REQUEST['cat']) ){
                        $selected_cat_slugs = explode(',', $_REQUEST['cat']);
                    }

                    /*echo '<pre>';
                    print_r($selected_cat_slugs);
                    echo '</pre>';*/

                    $temp_key = array_search($category->slug, $selected_cat_slugs);

                    if ($temp_key !== false) {
                        $selected_cat_class = ' selected_cat';
                        unset($selected_cat_slugs[$temp_key]);
                        // Optional: reindex if needed
                        $selected_cat_slugs = array_values($selected_cat_slugs);
                    }else{
                        $selected_cat_slugs[] = $category->slug;
                    }

                    $final_selected_cat_slugs = array_unique(array_filter($selected_cat_slugs, function($value) {
                        return trim($value) !== '';
                    }));


                    /*echo '<pre>';
                    print_r($selected_cat_slugs);
                    echo '</pre>';*/

                    $selected_cat_link = esc_url(site_url('all-products'));
                    if( !empty($final_selected_cat_slugs) ){
                        $selected_cat_link = esc_url(site_url('all-products').'?cat='.implode(',', $final_selected_cat_slugs));
                    }
                    
                    /*if( $category->count > 0 ){
                    }*/
                ?>
                    <div class="category-item card-category<?=$selected_cat_class;?>">
                        <a href="<?=$selected_cat_link;?>" class="category-link">
                            <span class="category-icon"><?php echo $category_icon; ?></span>
                            <span class="category-name"><?php echo esc_html($category->name); ?></span>
                            <span class="cc status <?php echo esc_attr($status_class); ?>"><span><?php echo esc_html($status_text); ?></span></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    <!-- <?php //endif; ?> -->


    <div class="product-management-header top-filter-block all-product-page"<?=$display_status;?>>
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Search product, brand">
        </div>

        <div class="action-buttons">
            <!-- <button class="filter-products action-button">
            <i class="fas fa-sliders-h"></i>
            Filters
        </button> -->
            <button id="bulk-edit-products" class="bulk-edit-products btn-black-white btn-primary-white action-button btn btn-white size-sm">
                Bulk Edit Products
            </button>
            <button id="bulk-add-products" class="bulk-add-products btn-black-white btn-primary-white action-button btn btn-white size-sm">
                Bulk Add Products
            </button>
            <button class="export-products-csv btn-black-white btn-primary-white action-button btn btn-white size-sm">
                Export Products
            </button>
            <button class="create-new-product-btn action-button btn btn-blue size-sm" id="create-new-bulk-product">
                Create New Product
            </button>
            <div class="view-toggle">
                <button id="list-view-btn" class="view-icon active"><i class="fas fa-list"></i></button>
                <button id="thumbnail-view-btn" class="view-icon"><i class="fas fa-th"></i></button>
            </div>

        </div>
    </div>

    <div id="product-list-view" <?=$display_status;?>>
        <?php product_listing_function(); ?>
    </div>
    <!-- Thumbnail View (Hidden by default, won't interfere with other layout) -->
    <div class="grid-block-wrapper" id="product-thumbnail-view" style="display: none;"<?=$display_status;?>>
        <div id="thumbnail-grid-wrapper">
            <div id="thumbnail-grid" class="thumbnail-grid"></div>
        </div>
        <div id="thumbnail-pagination" class="pagination"></div>
    </div>


    <div class="page-bottom-toolbar"<?=$display_status;?>>
        <div class="right-block">
            <div class="save-next-button-controls page-bottom-actions">
                <button class="pagination-button btn btn-outline btn-black-white btn-primary-white">Save</button>
                <button class="pagination-button next btn btn-primary btn-black-white btn-primary-black">Next</button>
            </div>
        </div>
    </div>

    <!-- <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script> -->

    <div class="bulk-add-container bulk-create-product"<?=$display_status2;?>>
        <div class="sm-container">
             <!-- Single title div that changes content -->
            <div class="title">
                <div class="page-title center">
                    <h1><?=$display_status_title;?></h1>
                </div>
            </div>
            

            <!-- Step 1 -->
            <div class="step">
                <div class="content-wrapper">
                    <div class="step-title">Step 1</div>
                    <div class="step-description">
                        Download the existing range template <span class="important">(Important!)</span>
                    </div>
                </div>
                <button id="download-product-template" class="btn btn-blue">
                    <i class="fas fa-download"></i> Download Template
                </button>
            </div>

            <!-- Step 2 -->
            <div class="step step-create-product">
                <div class="content-wrapper">
                    <div class="create-product-step-title step-title">Step 2</div>
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
            </div>
            <div class="step step-edit-product">
                <div class="content-wrapper">
                    <div class="edit-product-step-title step-title">Step 2</div>
                    <div class="csv-details">
                        <ul>
                            <li>Open the downloaded Excel document and fill in the required columns (denoted with a *).
                            </li>
                            <li>Please delete any lines of categories that you have not updated. And re-upload with only
                                the lines
                                you wish to change</li>
                            <li>Please ensure you have populated all the required columns (denoted with a
                                <strong>*</strong>).
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="step">
                <div class="content-wrapper">
                    <div class="step-title">Step 3</div>
                    <div class="step-description">
                        Upload your modified CSV with the new product range. This will show you a preview of the
                        products that will
                        be created and allow you to confirm the changes.
                    </div>
                    <div class="warning add-bulk-cat-warning">
                        <i class="fas fa-exclamation-circle"></i> It can take several minutes to process large orders.
                        Please be
                        patient.
                    </div>
                </div>
                <button id="upload-product-csv-btn" class="btn upload-btn btn-blue">
                    <i class="fas fa-upload"></i> Upload File
                </button>
            </div>

            <!-- Footer Buttons -->
            <!-- <div class="footer-buttons">
            <div class="btn secondary-btn">Save Draft</div>
            <div class="btn primary-btn">Next</div>
        </div> -->
        </div>
    </div>
    <!-- For Popup -->

    <div class="modal fade" id="file-upload-modal" tabindex="-1" aria-labelledby="file-upload-modal-label"
        aria-hidden="true" style="display: none;">
        <div class="modal-dialog custom-popup">
            <div class="modal-content  custom-main-modal">
                <div class="modal-header custom-modal-header">
                    <h5 class="modal-title" id="file-upload-modal-label">Upload CSV File</h5>
                    <button type="button" class="btn-close close-modal" data-bs-dismiss="modal" aria-label="Close"> <i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="modal-body custom-modal-body">
                    <div id="upload-area" class="upload-area" style="cursor: pointer;">
                        <span id="upload-btn" class="upload-btn">Upload,</span>
                        <strong>Select a CSV file to upload</strong>
                        <p>or drag and drop it here</p>
                    </div>

                    <!-- Hidden file input moved outside -->
                    <input id="csv-file-input1" name="file_upload" type="file" multiple accept=".csv" style="display: none;">

                    <small class="text-danger d-block mt-2" id="file-error-msg">⚠️ Please select a CSV file.</small>
                    <div class="file-bottom-block">
                        <button type="button" id="remove-selected-file" class="close-btn"><i class="fa-solid fa-xmark"></i></button>
                        <div id="file-name-display" style="display: none;">
                            <strong>Selected File:</strong> <span id="selected-file-name"></span>
                        </div>
                        <div id="upload-progress" class="progress mt-2" style="display: none;">
                            <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                        </div>
                    </div>
                </div>


                <div class="modal-footer popup-footer center">
                    <button type="button" class="btn btn-secondary btn btn-white btn-black-white btn-primary-white"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-black-white btn-primary-black" id="submit-file-upload">Upload
                        File</button>
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
                    <p class="text-muted">The following fields have been matched based on your data. Please choose an
                        input
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
                                 
                                </tr>
                            </thead>
                            <tbody id="mapping-interface">
                                <!-- Dynamically generated rows will be added here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-black-white btn-primary-white" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-black-white btn-primary-black" id="apply-mapping">Save and Continue</button>
                </div>
            </div>
        </div>
    </div>

    <div id="mandatory-warning" class="mb-3"></div>
    <div id="validation-results" class="mb-3"></div>
    <div class="bulk-edit-product-preview-wrapper">
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
        <div class="next-save-btn-wrapper">
       
            <button type="button" class="btn btn-dark next-btn" id="match-headers-btn" style="display: none;">Next</button>
        </div>

    </div>

    <div id="preview-section" class="preview-container bulk-create-products" style="display: none;">
        <button class="btn btn-danger" id="remove-error-lines">Remove error lines</button>
        <button class="btn btn-warning" id="edit-errors">Edit errors</button>
        <button class="btn btn-primary" id="download-resubmit">Download and Resubmit</button>
        <div style="overflow-x: auto;">
            <div id="row-count-summary" class="row-summary custom-table-scroll"
                style="text-align: right; font-weight: bold; margin-bottom: 10px;"></div>
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
</div>

<script>
    jQuery(document).ready(function($) {
      
        let currentPage = 1;
        let currentSearch = ''; // track current search keyword

        const $listView = $('#product-list-view');
        const $thumbnailView = $('#product-thumbnail-view');
        const $listBtn = $('#list-view-btn');
        const $thumbBtn = $('#thumbnail-view-btn');
        const $grid = $('#thumbnail-grid');
        const $pagination = $('#thumbnail-pagination');
        const $searchInput = $('.all-product-page .search-input');

        function loadThumbnails(page = 1, search = '') {
            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                method: 'POST',
                data: {
                    action: 'load_thumbnail_view',
                    page: page,
                    search: search, // send search term
                    cat: '<?=$selected_cat;?>'
                },
                beforeSend: function () {
                    $grid.html('<div class="text-center">Loading...</div>');
                },
                success: function (response) {
                    $grid.html(response.html);
                    // FIX: Show pagination only if it's returned
                    if (response.pagination && response.pagination.trim() !== '') {
                        $pagination.html(response.pagination).show();
                    } else {
                        $pagination.empty().hide();
                    }

                    currentPage = page;
                    currentSearch = search;
                }
            });
        }

        // Toggle Views
        $listBtn.on('click', function () {
            $listBtn.addClass('active');
            $thumbBtn.removeClass('active');
            $thumbnailView.hide();
            $listView.show();
        });

        $thumbBtn.on('click', function () {
            $thumbBtn.addClass('active');
            $listBtn.removeClass('active');
            $listView.hide();
            $thumbnailView.show();
            const search = $searchInput.val().trim();
            loadThumbnails(1, search);
        });

        // Handle pagination
        $(document).on('click', '.pagination button[data-page]', function () {
            const page = parseInt($(this).data('page'));
            loadThumbnails(page, currentSearch); // reuse search
        });

        // Live search handler
        $searchInput.on('keyup', function () {
            const search = $(this).val().trim();
            if ($thumbBtn.hasClass('active')) {
                loadThumbnails(1, search);
            }
        });
    });
</script>

<?php get_footer(); ?>