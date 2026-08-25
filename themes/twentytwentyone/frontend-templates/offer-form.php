<?php
/**
 * Frontend Offer Form Template
 * Used for both create and edit on frontend
 */

$is_edit = isset($GLOBALS['is_edit']) ? $GLOBALS['is_edit'] : false;
$offer_id = isset($GLOBALS['offer_id']) ? $GLOBALS['offer_id'] : 0;
$offer = isset($GLOBALS['offer']) ? $GLOBALS['offer'] : null;
$meta = isset($GLOBALS['meta']) ? $GLOBALS['meta'] : [
    'description' => '',
    'image_id' => '',
    'showcase_type' => '',
    'promo_code' => '',
    'link' => '',
    'bulk_links' => [],
    'terms' => '',
    'flag' => '',
    'tags' => [],
    'start_date_only' => '',
    'start_time_only' => '16:00',
    'end_date_only' => '',
    'end_time_only' => '21:00',
    'always_on' => false,
    'audience' => '',
    'user_roles' => [],
    'products' => [],
    'all_products' => false,
];

$offer_title = $is_edit && $offer ? $offer->post_title : '';
$offer_status = $is_edit && $offer ? $offer->post_status : 'draft';
// echo '<pre>'; print_r($offer_status); echo '</pre>';
// exit;
$form_action = $is_edit ? 'update_offer_frontend' : 'save_offer_frontend';
$page_title = $is_edit ? 'View/Edit Offer #' . $offer_id : 'Create a New Offer';

// Get image URL if exists
$image_url = '';
if (!empty($meta['image_id'])) {
    // Validate that the attachment exists before trying to get URL
    $attachment = get_post($meta['image_id']);
    if ($attachment && $attachment->post_type === 'attachment') {
        $image_url = wp_get_attachment_image_url($meta['image_id'], 'full');
        // Fallback to attachment URL if image URL is not available
        if (!$image_url) {
            $image_url = wp_get_attachment_url($meta['image_id']);
        }
    }
}

// Get selected products details
$selected_products_data = [];
if (!empty($meta['products']) && is_array($meta['products'])) {
    foreach ($meta['products'] as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $selected_products_data[] = [
                'id' => $product_id,
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_price(),
            ];
        }
    }
}

// Handle form submission messages from transient
$success_message = '';
$error_message = '';
$new_offer_id = 0;

// Check for message in transient
if (isset($_GET['offer_msg'])) {
    $transient_key = sanitize_text_field($_GET['offer_msg']);
    $message_data = get_transient($transient_key);
    
    if ($message_data) {
        if ($message_data['type'] === 'success') {
            $success_message = $message_data['message'];
            if ($message_data['is_new']) {
                $new_offer_id = $message_data['offer_id'];
            }
        } else {
            $error_message = $message_data['message'];
        }
        // Delete transient after reading
        delete_transient($transient_key);
        // Clean URL by removing offer_msg parameter (using JavaScript)
    }
}

// Fallback to old method for backward compatibility
if (empty($success_message) && empty($error_message)) {
    if (isset($_GET['offer_created']) && $_GET['offer_created'] == '1') {
        $success_message = 'Offer created successfully!';
    } elseif (isset($_GET['offer_saved']) && $_GET['offer_saved'] == '1') {
        $success_message = 'Offer updated successfully!';
    }
    if (isset($_GET['offer_error'])) {
        $error_message = sanitize_text_field($_GET['offer_error']);
    }
}

// If _offer_link was saved as a serialized array (bulk links), expand it back
if (!empty($meta['link']) && is_serialized($meta['link'])) {
    $unserialized = maybe_unserialize($meta['link']);
    if (is_array($unserialized)) {
        $meta['bulk_links'] = array_values(array_filter($unserialized, 'strlen'));
        $meta['link'] = '';
    }
}
?>
<div class="offer-form-wrapper-frontend">
    <?php if ($success_message): ?>
        <div class="offer-message offer-success" id="offer-success-message">
            <p><?php echo esc_html($success_message); ?></p>
            <?php if ($new_offer_id > 0): ?>
                <?php
                // For new offers, "Continue Editing" should go to edit page
                $edit_page = get_page_by_path('edit-offer');
                if ($edit_page) {
                    $edit_url = add_query_arg('offer_id', $new_offer_id, get_permalink($edit_page->ID));
                    echo '<a href="' . esc_url($edit_url) . '" class="btn btn-black-white btn-primary-white">Continue Editing</a>';
                }
                ?>
            <?php else: ?>
                <a href="<?php echo esc_url(wp_get_referer() ?: get_permalink()); ?>" class="btn btn-black-white btn-primary-white">Continue Editing</a>
            <?php endif; ?>
            <?php
            $offers_page = get_page_by_path('offers');
            if ($offers_page) {
                echo '<a href="' . esc_url(get_permalink($offers_page->ID)) . '" class="btn btn-black-white btn-primary-black">View All Offers</a>';
            }
            ?>
        </div>
        <script>
        // Clean URL by removing offer_msg parameter without page reload
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location);
            if (url.searchParams.has('offer_msg')) {
                url.searchParams.delete('offer_msg');
                window.history.replaceState({}, '', url);
            }
        }
        </script>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="offer-message offer-error">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="offer-header">
        <div class="offer-header-left">
            <h1><?php echo esc_html($page_title); ?></h1>
        </div>
        <div class="offer-header-right">
            <button type="submit" form="offer-form" name="save_as_draft" value="1" class="btn btn-black-white btn-primary-white btn-secondary">Save as Draft</button>
            <?php if ($is_edit): ?>
                <button type="submit" form="offer-form" name="update" value="1" class="btn btn-black-white btn-primary-white btn-primary">Save and Publish</button>
            <?php else: ?>
                <button type="submit" form="offer-form" name="save_and_publish" value="1" class="btn btn-black-white btn-primary-black btn-primary">Save and Publish</button>
            <?php endif; ?>
        </div>
    </div>

    <form id="offer-form" method="post" action="">
        <?php wp_nonce_field('offer_form_frontend_nonce', 'offer_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr($form_action); ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="offer_id" value="<?php echo esc_attr($offer_id); ?>">
        <?php endif; ?>

        <div class="offer-form-content">
            <!-- Offer Details Section -->
            <div class="offer-section">
                <h2>Offer Details</h2>
                
                <div class="form-field">
                    <label for="offer_title">Offer Title</label>
                    <input type="text" id="offer_title" name="offer_title" value="<?php echo esc_attr($offer_title); ?>" class="regular-text" required>
                </div>

                <div class="form-field">
                    <label for="offer_description">Offer Description</label>
                    <textarea id="offer_description" name="offer_description" rows="10" class="large-text"><?php echo esc_textarea($meta['description']); ?></textarea>
                </div>

                <div class="form-field">
                    <label>Upload offer image</label>
                    <div class="image-upload-box" id="image-upload-box">
                        <?php if ($image_url): ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="Offer Image" class="uploaded-image">
                            <button type="button" class="remove-image" id="remove-image">×</button>
                        <?php else: ?>
                            <div class="upload-placeholder" id="upload-placeholder">
                                <span class="upload-icon">📄</span>
                                <p>Drag and drop image here</p>
                                <p class="upload-hint">SVG, PNG, JPG or GIF (max. 3MB)</p>
                                <p class="upload-link-text"><a href="#" id="upload-link-trigger">Link</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="url-upload-section" id="url-upload-section" style="display: none; margin-top: 15px;">
                        <input type="url" id="image-url-input" class="url-input" placeholder="https://example.com/image.jpg">
                        <button type="button" class="btn btn-primary" id="upload-from-url">Submit</button>
                        <button type="button" class="btn btn-secondary" id="cancel-url-upload">Cancel</button>
                        <div id="url-upload-status" class="url-upload-status"></div>
                    </div>
                    <input type="hidden" id="offer_image_id" name="offer_image_id" value="<?php echo esc_attr($meta['image_id']); ?>">
                    <input type="hidden" id="offer_image_data" name="offer_image_data" value="">
                    <input type="hidden" id="offer_image_filename" name="offer_image_filename" value="">
                </div>

                <div class="form-field">
                    <label for="offer_showcase_type">Offer Showcase</label>
                    <select id="offer_showcase_type" name="offer_showcase_type" class="regular-text">
                        <option value="">Copy, Promo code, Link</option>
                        <option value="promo_code" <?php selected($meta['showcase_type'], 'promo_code'); ?>>Promo Code</option>
                        <option value="link" <?php selected($meta['showcase_type'], 'link'); ?>>Link</option>
                        <option value="copy" <?php selected($meta['showcase_type'], 'copy'); ?>>Copy</option>
                    </select>
                </div>

                <div class="form-field" id="promo-code-field" style="display: <?php echo $meta['showcase_type'] === 'promo_code' ? 'block' : 'none'; ?>;">
                    <label for="offer_promo_code">Add Promo Code</label>
                    <input type="text" id="offer_promo_code" name="offer_promo_code" value="<?php echo esc_attr($meta['promo_code']); ?>" class="regular-text">
                </div>

                <div class="form-field" id="link-field" style="display: <?php echo $meta['showcase_type'] === 'link' ? 'block' : 'none'; ?>;">
                    <div class="link-field-header">
                        <label for="offer_link">Add Link</label>
                        <button type="button" id="add-bulk-links-btn" class="btn btn-black-white btn-primary-black">+ Add Bulk Offer Links</button>
                    </div>

                    <!-- Single link (shown by default) -->
                    <div id="single-link-wrapper">
                        <input type="url" id="offer_link" name="offer_link" value="<?php echo esc_url($meta['link']); ?>" class="regular-text" placeholder="https://example.com/offer">
                    </div>

                    <!-- Hidden CSV file input -->
                    <input type="file" id="bulk-links-csv-input" accept=".csv" style="display: none;">

                    <!-- CSV upload hint -->
                    <div id="bulk-links-csv-hint" style="display: none; margin-top: 8px; font-size: 13px; color: #666;">
                        CSV must have a column named <strong>link</strong> or <strong>url</strong>, or place links in the first column.
                        <a href="#" id="bulk-links-csv-download-sample">Download sample CSV</a>
                    </div>

                    <!-- Bulk links list (populated after CSV import or on edit load) -->
                    <div id="bulk-links-wrapper" style="display: <?php echo !empty($meta['bulk_links']) ? 'block' : 'none'; ?>; margin-top: 12px;">
                        <div id="bulk-links-list">
                            <?php
                            $bulk_links = is_array($meta['bulk_links']) ? $meta['bulk_links'] : [];
                            foreach ($bulk_links as $index => $bulk_link):
                            ?>
                                <div class="bulk-link-row" data-index="<?php echo $index; ?>">
                                    <span class="bulk-link-index"><?php echo $index + 1; ?></span>
                                    <input type="url" name="offer_bulk_links[]" value="<?php echo esc_url($bulk_link); ?>" class="regular-text bulk-link-input" placeholder="https://example.com/offer">
                                    <button type="button" class="btn-remove-bulk-link" title="Remove">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bulk-links-footer">
                            <button type="button" id="add-another-link-btn" class="btn btn-black-white btn-primary-white">+ Add Row</button>
                            <button type="button" id="re-upload-csv-btn" class="btn btn-black-white btn-primary-white">↑ Re-upload CSV</button>
                            <span id="bulk-links-count" class="bulk-links-count"></span>
                        </div>
                    </div>

                    <!-- CSV parse error message -->
                    <div id="bulk-links-error" style="display: none; margin-top: 8px; color: #e53e3e; font-size: 13px;"></div>
                </div>

                <div class="form-field">
                    <label for="offer_terms">Offer Terms & Conditions</label>
                    <textarea id="offer_terms" name="offer_terms" rows="5" class="large-text"><?php echo esc_textarea($meta['terms']); ?></textarea>
                </div>

                <div class="form-field">
                    <label for="offer_flag">Offer Flag</label>
                    <input type="text" id="offer_flag" name="offer_flag" value="<?php echo esc_attr($meta['flag']); ?>" class="regular-text" placeholder="Top offer, limited edition">
                </div>

                <div class="form-field">
                    <label for="offer_tags">Offer Tag</label>
                    <div class="tags-container">
                        <div class="tags-list" id="tags-list">
                            <?php foreach ($meta['tags'] as $tag): ?>
                                <span class="tag-pill">
                                    <?php echo esc_html($tag); ?>
                                    <span class="tag-remove" data-tag="<?php echo esc_attr($tag); ?>">×</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" id="offer_tag_input" class="regular-text" placeholder="Add tag">
                        <button type="button" class="btn btn-black-white btn-primary-black" id="add-tag-btn">Add Tag</button>
                    </div>
                    <input type="hidden" id="offer_tags" name="offer_tags" value="<?php echo esc_attr(implode(',', $meta['tags'])); ?>">
                </div>

                <div class="form-field-row">
                    <div class="form-field">
                        <label for="offer_start_date">Offer Start Date</label>
                        <div class="datetime-wrapper">
                            <input type="text" id="offer_start_date" name="offer_start_date" value="<?php echo esc_attr($meta['start_date_only']); ?>" class="datepicker regular-text" placeholder="MM/DD/YYYY" readonly>
                            <span class="datetime-separator">at</span>
                            <input type="time" id="offer_start_time" name="offer_start_time" value="<?php echo esc_attr($meta['start_time_only']); ?>" class="time-input">
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="offer_end_date">Offer End Date</label>
                        <div class="datetime-wrapper">
                            <input type="text" id="offer_end_date" name="offer_end_date" value="<?php echo esc_attr($meta['end_date_only']); ?>" class="datepicker regular-text" placeholder="MM/DD/YYYY" readonly>
                            <span class="datetime-separator">at</span>
                            <input type="time" id="offer_end_time" name="offer_end_time" value="<?php echo esc_attr($meta['end_time_only']); ?>" class="time-input">
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label>
                        <input type="checkbox" id="offer_always_on" name="offer_always_on" value="1" <?php checked($meta['always_on']); ?>>
                        Always on
                    </label>
                </div>

                <div class="form-field">
                    <label for="offer_audience">Offer Audience</label>
                    <select id="offer_audience" name="offer_audience" class="regular-text">
                        <option value="">User Type</option>
                        <option value="all" <?php selected($meta['audience'], 'all'); ?>>All Users</option>
                        <option value="registered" <?php selected($meta['audience'], 'registered'); ?>>Registered Users</option>
                        <option value="guest" <?php selected($meta['audience'], 'guest'); ?>>Guest Users</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Offer User Roles</label>
                    <div class="offer-roles-checkboxes">
                        <?php
                        $all_roles     = wp_roles()->get_names();
                        $selected_roles = is_array($meta['user_roles']) ? $meta['user_roles'] : [];
                        foreach ($all_roles as $role_slug => $role_name):
                        ?>
                            <label class="offer-role-label">
                                <input type="checkbox"
                                       name="offer_user_roles[]"
                                       value="<?php echo esc_attr($role_slug); ?>"
                                       <?php checked(in_array($role_slug, $selected_roles, true)); ?>>
                                <?php echo esc_html(translate_user_role($role_name)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="offer-roles-hint">Leave all unchecked to allow every role.</p>
                </div>

                <div class="form-field">
                    <label for="offer_status">Status</label>
                    <select id="offer_status" name="offer_status" class="regular-text">
                        <option value="draft" <?php selected($offer_status, 'draft'); ?>>Draft</option>
                        <option value="pending" <?php selected($offer_status, 'pending'); ?>>Pending</option>
                        <option value="publish" <?php selected($offer_status, 'publish'); ?>>Published</option>
                    </select>
                </div>
            </div>

            <!-- Add Products Section -->
            <div class="offer-section">
                <h2>Add Products</h2>
                
                <div class="products-search-container">
                    <div class="search-wrapper">
                        <input type="text" id="product-search" class="regular-text" placeholder="Search product title, SKU, brand or category">
                        <span class="search-icon">🔍</span>
                    </div>
                    <button type="button" class="btn btn-black-white btn-primary-black" id="add-bulk-btn">Add bulk</button>
                </div>

                <div class="form-field">
                    <label>
                        <input type="checkbox" id="offer_all_products" name="offer_all_products" value="1" <?php checked($meta['all_products']); ?>>
                        Available for all products
                    </label>
                </div>

                <div class="products-list-container">
                    <!-- Selected Products Section -->
                    <div class="selected-products-section">
                        <h3>Selected Products</h3>
                        <div id="selected-products-list" class="selected-products-list">
                            <?php if ($is_edit && !empty($selected_products_data)): ?>
                                <?php foreach ($selected_products_data as $product): ?>
                                    <div class="selected-product-item" data-product-id="<?php echo esc_attr($product['id']); ?>">
                                        <input type="hidden" name="offer_products[]" value="<?php echo esc_attr($product['id']); ?>">
                                        <div class="product-info">
                                            <span class="product-name"><?php echo esc_html($product['name']); ?></span>
                                            <span class="product-details">SKU: <?php echo esc_html($product['sku'] ?: '-'); ?> | Price: $<?php echo esc_html($product['price'] ?: '0'); ?></span>
                                        </div>
                                        <button type="button" class="btn-remove-product" data-product-id="<?php echo esc_attr($product['id']); ?>" title="Remove product">×</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-products-message">No products selected. Search and add products below.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Search Results Section -->
                    <div class="search-results-section">
                        <h3>Search Products</h3>
                        <div id="products-list" class="products-list">
                            <p class="search-hint">Click on the search field or start typing to search for products</p>
                        </div>
                    </div>

                    <!-- Products Table (for edit mode) -->
                    <?php if ($is_edit && !empty($selected_products_data)): ?>
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th>In Offer</th>
                                    <th>Name</th>
                                    <th>SKU Assigned</th>
                                    <th>Denomination</th>
                                </tr>
                            </thead>
                            <tbody id="products-table-body">
                                <?php foreach ($selected_products_data as $product): ?>
                                    <tr data-product-id="<?php echo esc_attr($product['id']); ?>">
                                        <td>
                                            <input type="checkbox" value="<?php echo esc_attr($product['id']); ?>" checked disabled>
                                        </td>
                                        <td>
                                            <span class="product-status">Active</span>
                                            <?php echo esc_html($product['name']); ?>
                                        </td>
                                        <td><?php echo esc_html($product['sku'] ?: '-'); ?></td>
                                        <td>$<?php echo esc_html($product['price'] ?: '0'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.link-field-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.link-field-header label { margin-bottom: 0; }

.bulk-link-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.bulk-link-index {
    flex-shrink: 0;
    min-width: 24px;
    font-size: 12px;
    color: #888;
    text-align: right;
}
.bulk-link-row .regular-text { flex: 1; }

.btn-remove-bulk-link {
    flex-shrink: 0;
    background: #e53e3e;
    color: #fff;
    border: none;
    border-radius: 4px;
    width: 30px;
    height: 30px;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
}
.btn-remove-bulk-link:hover { background: #c53030; }

.bulk-links-footer {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}
.bulk-links-count {
    font-size: 12px;
    color: #666;
    margin-left: auto;
}
</style>

<script>
/* Variables used by offer-frontend.js */
var ajaxurl     = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
var nonce       = '<?php echo esc_js(wp_create_nonce('offer_ajax_nonce')); ?>';
var uploadNonce = '<?php echo esc_js(wp_create_nonce('offer_ajax_nonce')); ?>';
var offerBulkLinks = <?php echo wp_json_encode(array_values(array_filter(is_array($meta['bulk_links']) ? $meta['bulk_links'] : [], 'strlen'))); ?>;
</script>

