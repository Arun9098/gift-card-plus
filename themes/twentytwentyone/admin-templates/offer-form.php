<?php
/**
 * Offer Form Template
 * Used for both create and edit
 */

$is_edit = (isset($offer_id) && $offer_id > 0) || (isset($GLOBALS['offer_id']) && $GLOBALS['offer_id'] > 0);
$offer_id = isset($offer_id) ? $offer_id : (isset($GLOBALS['offer_id']) ? $GLOBALS['offer_id'] : 0);
$offer = $is_edit ? (isset($GLOBALS['offer']) ? $GLOBALS['offer'] : get_post($offer_id)) : null;
$meta = $is_edit ? (isset($GLOBALS['meta']) ? $GLOBALS['meta'] : get_offer_meta($offer_id)) : [
    'description' => '',
    'image_id' => '',
    'showcase_type' => '',
    'promo_code' => '',
    'link' => '',
    'terms' => '',
    'flag' => '',
    'tags' => [],
    'start_date_only' => '',
    'start_time_only' => '16:00',
    'end_date_only' => '',
    'end_time_only' => '21:00',
    'always_on' => false,
    'audience' => '',
    'products' => [],
    'all_products' => false,
];

$offer_title = $is_edit ? $offer->post_title : '';
$offer_status = $is_edit ? $offer->post_status : 'draft';
$form_action = $is_edit ? 'update_offer' : 'save_offer';
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
?>
<div class="wrap offer-form-wrapper">
    <div class="offer-header">
        <div class="offer-header-left">
            <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
        </div>
        <div class="offer-header-right">
            <button type="submit" form="offer-form" name="save_as_draft" class="button button-secondary">Save as Draft</button>
            <?php if ($is_edit): ?>
                <button type="submit" form="offer-form" name="update" class="button button-primary">Update</button>
            <?php else: ?>
                <button type="submit" form="offer-form" name="save_and_publish" class="button button-primary">Save and Publish</button>
            <?php endif; ?>
        </div>
    </div>

    <form id="offer-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('offer_form_nonce'); ?>
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
                        <button type="button" class="button button-primary" id="upload-from-url">Submit</button>
                        <button type="button" class="button button-secondary" id="cancel-url-upload">Cancel</button>
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
                    <label for="offer_link">Add Link</label>
                    <input type="url" id="offer_link" name="offer_link" value="<?php echo esc_url($meta['link']); ?>" class="regular-text">
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
                        <button type="button" class="button" id="add-tag-btn">Add Tag</button>
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
                    <button type="button" class="button" id="add-bulk-btn">Add bulk</button>
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

<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
var nonce = '<?php echo wp_create_nonce('offer_ajax_nonce'); ?>';
var uploadNonce = '<?php echo wp_create_nonce('offer_ajax_nonce'); ?>';
</script>

