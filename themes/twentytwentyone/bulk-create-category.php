<?php
/*
Template Name: Bulk Create Category
*/
get_header();

$display_status = '';
$list_status = 'list_true';
if( (isset($_GET['bulk-edit-category']) && $_GET['bulk-edit-category'] == 'true') || (isset($_GET['bulk-create-category']) && $_GET['bulk-create-category'] == 'true') ){
    $display_status = ' style="display: none;"';
    $list_status = 'list_false';
}
// $display_status_cat = ' style="display: block;"';
$display_title         = '';
$display_listing       = '';
$display_header        = '';
$display_create_view   = 'style="display:none;"';

if(isset($_GET['create-category']) && $_GET['create-category'] == 'true'){
    // Hide title, listing, header
    // $display_title   = 'style="display:none;"';
    // $display_listing = 'style="display:none;"';
    // $display_header  = 'style="display:none;"';

    // // Show create view
    // $display_create_view = 'style="display:block;"';
    $list_status = 'list_create_cat';

    ?>
    <script type="text/javascript">
        jQuery(window).load(function(){     
            jQuery('#create-new-category').trigger('click');
        });
    </script>
    <?php
}


?>

<div class="page-spacer-top"></div>
<div class="product-container container">
    <div class="page-title align-left"<?=$display_status;?>>
        <h1 class="category-title" <?=$display_title;?>>All Categories</h1>
    </div>

    <div class="category-management-header top-filter-block" <?=$display_header;?> <?=$display_status;?>>
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <!-- <input type="text" class="search-input" placeholder="Search category"> -->
            <input type="text" class="cat-search-input" placeholder="Search" value="<?=$search;?>">
            <!-- <button type="button" class="cat-search-clear" style="display:none;">Clear</button> -->
        </div>

        <div class="action-buttons">
            <!-- <button class="filter-category action-button">
                <i class="fas fa-sliders-h"></i>
                Filters
            </button> -->
            <button id="bulk-edit-category" class="bulk-edit-category btn-black-white btn-primary-white action-button btn btn-white size-sm">
                Bulk Edit category
            </button>
            <button id="bulk-add-category" class="bulk-add-category action-button btn btn-white size-sm btn-black-white btn-primary-white">
                Bulk Add category
            </button>
            <button id="export-category-csv" class="export-category-csv action-button btn btn-white size-sm btn-black-white btn-primary-white">
                Export Category
            </button>
            <button class="create-new-category-btn action-button btn btn-blue" id="create-new-category">
                Create New category
            </button>
            <div class="view-toggle-buttons">
                <button id="list-view-btn" class="view-btn active">
                    <i class="fas fa-list"></i>
                </button>
                <button id="thumbnail-view-btn" class="view-btn">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>

        </div>
    </div>
    <?php 
        $display_status = "";

        // Check if cookies exist
        if (!empty($_COOKIE['currentView']) || !empty($_COOKIE['currentSearch'])) {
            // echo 'hiiiiiii';
            // exit;
            $list_status .= ' '.$_COOKIE['currentView'];
            $display_status = ' style="display:none;"';
        }
    ?>
    <div class="category-second-list-container <?=$list_status;?>"<?=$display_status;?>>
        <?php category_listing_section(); ?>
    </div>

    <?php 
    $display_status2 = ' style="display: none;"';
    $display_status_title = 'Bulk Add Categories';
    $display_status_button_title = 'Create Categories';
    if( (isset($_GET['bulk-edit-category']) && $_GET['bulk-edit-category'] == 'true') || (isset($_GET['bulk-create-category']) && $_GET['bulk-create-category'] == 'true') ){
        $display_status2 = '';
    }
    if( isset($_GET['bulk-edit-category']) && $_GET['bulk-edit-category'] == 'true' ){
        $display_status_title = 'Bulk Edit Categories';
        $display_status2 = '';
        $display_status_button_title = 'Update Categories';
    } 
    ?>
    <div class="bulk-add-container"<?=$display_status2;?>>
        <div class="title">
            <div class="page-title center">
                <h1><?=$display_status_title;?></h1>
            </div>
        </div>
        <!-- <div class="title bulk-add-category-title">
            <div class="page-title center">
                <h1>Bulk Add Categories</h1>
            </div>
        </div>
        <div class="title bulk-edit-category-title">
           <div class="page-title center">
                <h1>Bulk Edit Categories</h1>
            </div>
        </div> -->
        <div class="sm-container">    
            <!-- Step 1 -->
            <div class="step">
                <div class="content-wrapper">
                    <div class="step-title">Step 1</div>
                    <div class="step-description">
                        Download the category template <span class="important">(Important!)</span>
                    </div>
                </div>
                <button id="download-category-template" class="btn btn-blue">
                    <i class="fas fa-download"></i> Download Template
                </button>
            </div>

            <!-- Step 2 -->
            <div class="step step-create">
                <div class="content-wrapper">
                    <div class="add-step-title step-title">Step 2</div>
                    <div class="step-description">
                        Open the downloaded Excel document and fill in the required columns (denoted with a *).
                    </div>
                    <div class="sub-heading">What details do I need for the CSV?</div>
                    <div class="csv-details">
                        <div>1. Category Name *</div>
                        <div>2. Description *</div>
                        <div>3. Priority</div>
                        <div>4. Icon Image</div>
                        <div>5. Banner Image</div>
                        <div>6. Thumbnail Image</div>
                        <div>7. Status</div>
                        <div>8. SKU's Assigned</div>
                    </div>
                </div>
            </div>
            <div class="step step-edit">
                <div class="content-wrapper">
                    <div class="edit-step-title step-title">Step 2</div>
                    <div class="csv-details">
                        <ul>
                            <li>Open the downloaded Excel document and edit the cells for the products you wish to update.</li>
                            <li>Please delete any lines of categories that you have not updated. And re-upload with only the lines you wish to change</li>
                            <li>Please ensure you have populated all the required columns (denoted with a <strong>*</strong>).</li>
                            <li>Upload your modified CSV with the new categories. This will show you a preview of the categories that will be created and allow you to confirm the changes.</li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- Step 3 -->
            <div class="step">
                <div class="content-wrapper">
                    <div class="step-title">Step 3</div>
                    <div class="step-description">
                        Upload your modified CSV with the new categories. This will show you a preview of the categories that will be created and allow you to confirm the changes.
                    </div>
                    <div class="add-bulk-cat-warning">
                    <i class="fas fa-exclamation-circle"></i> It can take several minutes to process large numbers of categories. Please be patient.
                </div>
                </div>
                <button id="upload-category-csv-btn" class="btn btn-blue upload-btn">
                    <i class="fas fa-upload"></i> Upload File
                </button>
             
            </div>
        </div>
    </div>

    <!-- File Upload Modal -->
    <div class="modal fade " id="file-upload-modal" tabindex="-1" aria-labelledby="file-upload-modal-label"
        aria-hidden="true" style="display: none;">
        <div class="modal-dialog custom-popup ">
            <div class="modal-content custom-main-modal">
                <div class="modal-header custom-modal-header">
                    <h3 class="modal-title" id="file-upload-modal-label">Upload CSV File</h3>
                    <button type="button" class="btn-close close-modal" data-bs-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="modal-body custom-modal-body">
                    <div id="upload-area" class="upload-area" style="cursor: pointer;">
                        <span id="upload-btn" class="upload-btn">Upload,</span>
                        <strong>Select a CSV file to upload</strong>
                        <p>or drag and drop it here</p>
                    </div>

                    <!-- Hidden file input moved outside -->
                    <input id="csv-file-input" name="file_upload" type="file" multiple accept=".csv" style="display: none;">

                    <small class="text-danger d-block mt-2" id="file-error-msg">⚠️ Please select a CSV file.</small>

                    <div id="file-name-display" class="mt-2" style="display: none;">
                        <strong>Selected File:</strong> <span id="selected-file-name"></span>
                    </div>

                    <div id="upload-progress" class="progress mt-2" style="display: none;">
                        <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                    </div>
                </div>
                <div class="modal-footer popup-footer center">
                    <button type="button" class="btn btn-white btn-black-white btn-primary-white" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-black-white btn-primary-black" id="submit-category-file-upload">Upload File</button>
                </div>
            </div>
        </div>
    </div>

    <!-- For Mapping modal field -->
    <div class="modal fade " id="mapping-modal" tabindex="-1" aria-labelledby="mapping-modal-label" aria-hidden="true">
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
                    <button type="button" class="btn btn-primary" id="apply-csv-mapping">Save and Continue</button>
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

    <!-- Final Preview Section -->
    <div class="preview-header" style="display: none;">
            <button class="btn btn-white remove-error-btn" id="remove-error-lines">Remove error lines</button>
            <button class="btn btn-white edit-error-btn" id="edit-csv-errors">Edit errors</button>
            <button class="btn btn-blue" id="download-resubmit">Download and Resubmit</button>
    </div>
    <!-- Preview Section -->
    <div class="cat-message"></div>
    <button class="btn btn-primary" id="bulk-upload-cat-preview-btn" style="display: none;" disabled>Next</button>
    <div id="final-preview-section" class="preview-container csv-preview" style="display: none;">
        <div id="row-count-summary" class="row-summary"></div>
        <div style="overflow-x: auto; width: 100%;">
            <table id="cat-csv-preview-table">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>


    <div id="final-cat-preview-section" class="csvFinalPreviewTable d-none">
        <h3>Final Data Preview</h3>
    
        <div style="overflow-x: auto;">
            <table id="final-cat-preview-table">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <button class="btn btn-success" id="final-create-category-btn" name="final-create-btn" style="display: none;">
            <span class="btn-text"><?=$display_status_button_title;?></span>
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
    </button>

    <p id="success-message" style="color: green; display: none;"></p>
</div>
<?php get_footer(); ?>