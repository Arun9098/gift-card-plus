<?php
/* Template Name: Gift Card Form */
get_header();
?>


<?php
/**
 * Display a dropdown for any taxonomy.
 *
 * @param string $taxonomy The taxonomy slug (e.g., 'brands', 'product_cat', etc.).
 * @param string $label The label for the dropdown.
 * @param string $placeholder The placeholder option text.
 */

$edit_mode              = false;
$product_id             = 0;
$product_data           = array();
$update_product_flag    = false;
$saved_eligible         = '[]';

// Check if we're editing an existing product
if (isset($_GET['edit_product']) && !empty($_GET['edit_product'])) {
    $product_id = intval($_GET['edit_product']);
    $product    = wc_get_product($product_id);
    
    if ($product) {
        $edit_mode = true;
        $eligible_gift_cards = get_field('eligible_gift_cards', $product_id);

        // Gather all product data to pre-fill the form
        $product_data = array(
            'gift_card_title'                   => $product->get_name(),
            'sku'                               => $product->get_sku(),
            '_supplier_sku'                     => $product->get_meta('_supplier_sku'),
            'short_description'                 => $product->get_short_description(),
            'long_description'                  => $product->get_description(),
            'regular_price'                     => $product->get_regular_price(),
            'sale_price'                        => $product->get_sale_price(),
            '_sell_price_fixed'                 => $product->get_meta('_sell_price_fixed'),
            '_denomination_amount'              => $product->get_meta('_denomination_amount'),
            '_gst'                              => $product->get_meta('_gst'),
            'variable_range_from'               => $product->get_meta('variable_range_from'),
            'variable_range_to'                 => $product->get_meta('variable_range_to'),
            '_reedem_at_intervals'              => $product->get_meta('_reedem_at_intervals'),
            'margin_per'                        => $product->get_meta('margin_per'),
            'margin_currency'                   => $product->get_meta('margin_currency'),
            'sell_price_lowest_denomination'    => $product->get_meta('sell_price_lowest_denomination'),
            '_margin'                           => $product->get_meta('_margin'),
            '_discount_margin'                  => $product->get_meta('_discount_margin'),
            '_discount_valid_from'              => $product->get_meta('_discount_valid_from'),
            '_discount_valid_to'                => $product->get_meta('_discount_valid_to'),
            '_add_stock_level'                  => $product->get_stock_quantity(),
            '_quantity_per_transaction'         => $product->get_meta('_quantity_per_transaction'),
            '_total_value_per_transaction'      => $product->get_meta('_total_value_per_transaction'),
            'parent_sku'                        => $product->get_meta('parent_sku'),
            'sku_type'                          => $product->get_meta('sku_type'),
            '_cost_price'                       => $product->get_meta('_cost_price'),
            '_delivery_cost'                    => $product->get_meta('_delivery_cost'),
            'j_a_c_fulfillment_cost'            => $product->get_meta('j_a_c_fulfillment_cost'),
            '_total_sell_price'                 => $product->get_meta('_total_sell_price'),
            '_total_buy_price'                  => $product->get_meta('_total_buy_price'),
            '_total_buy_price_gst'              => $product->get_meta('_total_buy_price_gst'),
            '_supplier_fullfillment_price'      => $product->get_meta('_supplier_fullfillment_price'),
            'discounted_price'                  => get_field('discounted_price', $product_id),
            '_onsite_from'                      => $product->get_meta('_onsite_from'),
            '_onsite_to'                        => $product->get_meta('_onsite_to'),
            'activation_expire_date'            => $product->get_meta('activation_expire_date'),
            'gift_card_expiry_type'             => $product->get_meta('gift_card_expiry_type'),
            'supplier'                          => get_field('supplier', $product_id),
            '_supplier_id'                      => $product->get_meta('_supplier_id'),
            'gift_card_expiry_date'             => $product->get_meta('gift_card_expiry_date'),
            'gift_card_expiry_duration'         => $product->get_meta('gift_card_expiry_duration'),
            'terms_conditions'                  => $product->get_meta('terms_conditions'),
            'how_to_use'                        => $product->get_meta('how_to_use'),
            '_expire_date'                      => $product->get_meta('_expire_date'),
            '_extra_header'                     => $product->get_meta('_extra_header'),
            'product_status'                    => $product->get_status(),
            'buyer_upload'                      => $product->get_meta('buyer_upload'),
            'is_it_gift_card_plus_product'      => get_field('is_it_gift_card_plus_product', $product_id),
            'denomination_type'                 => get_field('denomination_type', $product_id),
            'is_swap_eligible'                  => get_post_meta($product_id, 'is_swap_allowed', true),
            'gift_card_expiry_unit'             => $product->get_meta('gift_card_expiry_unit'),
            'activation_expiry_type'            => get_field('activation_expiry_type', $product_id),
            'activation_expiry_date'            => $product->get_meta('activation_expiry_date'),
            'activation_expiry_duration'        => $product->get_meta('activation_expiry_duration'),
            'activation_expiry_unit'            => $product->get_meta('activation_expiry_unit'),
            'presetClasses'                     => $product->get_shipping_class(),
            'discounted_price_checkbox'         => get_field('discounted_price_checkbox', $product_id) ? 'Yes' : 'No',
            'add_stock_levels'                  => (get_field('add_stock_levels', $product_id) || $product->get_manage_stock()) ? 'Yes' : 'No',
            'add_transaction_limit_checkbox'    => get_field('add_transaction_limit_checkbox', $product_id) ? 'Yes' : 'No',
            'always_on'                         => get_field('always_on', $product_id) ?: 'No',
            'presetDeliveryClass'               => $product->get_meta('presetDeliveryClass'),
            'image_id'                          => $product->get_image_id(),
            'eligible_gift_cards'               => $eligible_gift_cards,
        );
        $eligible = $product_data['eligible_gift_cards'];
        if (!empty($eligible)) {

            $eligible_json = [];

            foreach ($eligible as $pid) {
                $prod = wc_get_product($pid);
                if (!$prod) continue;

                $eligible_json[] = [
                    'product_id' => $pid,
                    'sku'        => $prod->get_sku(),
                    'title'      => $prod->get_name(),
                    'rank'       => count($eligible_json) + 1
                ];
            }

            $saved_eligible = json_encode($eligible_json);
        } else {
            $saved_eligible = '[]';
        }
        // Get taxonomy terms
        $product_data['product_brand']      = wp_get_post_terms($product_id, 'product_brand', array('fields' => 'slugs'));
        $product_data['eligible_retailers'] = wp_get_post_terms($product_id, 'eligible_retailers', array('fields' => 'ids'));
        $product_data['product_cat']        = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        $product_data['product_tags']       = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'ids'));
        $product_data['icons']              = wp_get_post_terms($product_id, 'icons', array('fields' => 'names'));

        // Get featured placements
        $placements = get_field('featured_placements', $product_id);
        $product_data['featured_placements'] = !empty($placements) ? $placements : array();

        // Get shipping class
        $shipping_class = $product->get_shipping_class();
        if ($shipping_class) {
            $product_data['presetClasses'] = $shipping_class;
        }

        // Get images
        $gallery_ids = $product->get_gallery_image_ids();
        $product_data['product_images'] = $gallery_ids;

    }
}

function display_eligible_retailers_dropdown($selected_retailers = array())
{
    $taxonomy = 'eligible_retailers';
    $terms = get_terms([
        'taxonomy'      => $taxonomy,
        'hide_empty'    => false,
    ]);
    ?>

    <div class="form-group">
        <div class="control-wrapper multi-select-normal">
            <label class="label" for="<?php echo esc_attr($taxonomy); ?>">Eligible Retailers</label>
            <select id="<?php echo esc_attr($taxonomy); ?>" name="<?php echo esc_attr($taxonomy); ?>[]" multiple="multiple"
                style="width: 100%;">
                <?php
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $selected = in_array($term->term_id, (array) $selected_retailers) ? 'selected' : '';
                        echo '<option value="' . esc_attr($term->term_id) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
    </div>

    <?php
}

function display_brands_dropdown($selected_brand = '')
{
    $terms = get_terms([
        'taxonomy'      => 'product_brand',
        'hide_empty'    => false,
        'orderby'       => 'name',
    ]);

    if (!empty($terms) && !is_wp_error($terms)) {
        echo '<label class="label" for="product_brand-dropdown">Select Brand<span class="validate">*</span></label>';
        echo '<div style="display: flex; align-items: center;">';
        echo '<select name="product_brand[]" id="product_brand-dropdown" required>';
        echo '<option value="">Select Brand</option>';

        foreach ($terms as $term) {
            $thumbnail_id   = get_term_meta($term->term_id, 'thumbnail_id', true);
            $thumbnail_url  = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';

            $selected = (!empty($selected_brand) && in_array($term->slug, (array) $selected_brand)) ? 'selected' : '';

            echo sprintf(
                '<option value="%s" data-thumbnail="%s" %s>%s</option>',
                esc_attr($term->slug),
                esc_attr($thumbnail_url),
                $selected,
                esc_html($term->name)
            );
        }

        echo '</select>';
        echo '<button class="btn btn-icon btn-blue  btn-black-white" type="button" style="margin-left: 10px;" id="add-new-brand" title="Add New" onclick="window.open(\'' . esc_url(home_url('/brands-list/')) . '\', \'_blank\');">+</button>';
        echo '</div>';
    } else {
        echo 'No Product Brands available.';
    }
}
function display_category_input_field($selected_categories = array())
{
    $categories = get_terms([
        'taxonomy'      => 'product_cat',
        'hide_empty'    => false,
    ]);
    ?>
    <div class="form-group">
        <div class="control-wrapper multi-select-normal">
            <label class="label" for="product_cat">Categories</label>
            <select id="product_cat" name="product_cat[]" multiple="multiple" style="width: 100%;">
                <?php
                if (!is_wp_error($categories)) {
                    foreach ($categories as $category) {
                        $selected = in_array($category->term_id, (array) $selected_categories) ? 'selected' : '';
                        echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
    </div>
    <?php
}

function display_tags_input_field($selected_tags = array())
{
    // Get all WooCommerce product tags
    $tags = get_terms([
        'taxonomy' => 'product_tag',
        'hide_empty' => false,
    ]);

    ?>
    <div class="form-group">
        <div class="control-wrapper multi-select-badge">
            <label class="label" for="product_tags">Tags</label>
            <select id="product_tags" name="product_tags[]" multiple="multiple" style="width: 100%;">
                <?php
                if (!is_wp_error($tags)) {
                    foreach ($tags as $tag) {
                        $selected = in_array($tag->term_id, (array) $selected_tags) ? 'selected' : '';
                        echo '<option value="' . esc_attr($tag->term_id) . '" ' . $selected . '>' . esc_html($tag->name) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
    </div>
    <?php
}

function display_icons_input_field($selected_icons = array())
{
    // Get all terms from the 'icons' taxonomy
    $icons = get_terms([
        'taxonomy'      => 'icons',
        'hide_empty'    => false,
    ]);
    ?>
    <div class="form-group">
        <div class="control-wrapper multi-select-normal">
            <label class="label" for="icons-dropdown">Icons</label>
            <select id="icons-dropdown" name="icons[]" multiple="multiple" style="width: 100%;">
                <?php
                if (!is_wp_error($icons)) {
                    foreach ($icons as $icon) {
                        $selected = in_array($icon->name, (array) $selected_icons) ? 'selected' : '';
                        echo '<option value="' . esc_attr($icon->name) . '" ' . $selected . '>' . esc_html($icon->name) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
    </div>

    <?php
}

function get_existing_attachment_id_by_name($image_name) 
{
    global $wpdb;

    // Strip extension if needed
    $filename = pathinfo($image_name, PATHINFO_FILENAME);

    $filename = $sanitized_name = str_replace(' ', '-', $filename);

    // Search for attachments where the post_title OR guid (URL) contains the filename
    $query = $wpdb->prepare("
        SELECT ID 
        FROM $wpdb->posts 
        WHERE post_type = 'attachment' 
        AND (post_title = %s OR guid LIKE %s)
        LIMIT 1
    ", $filename, '%' . $wpdb->esc_like($image_name) . '%');

    $attachment_id = $wpdb->get_var($query);

    return $attachment_id ? (int) $attachment_id : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        $is_new_product = false;
    } else {
        $product = new WC_Product_Simple();
        $is_new_product = true;
    }

    if (isset($_POST['create_product']) || isset($_POST['save_step']) || (!empty($_POST['sku']))) {
        $update_product_flag = true;

        // Commented on 20251224
        // Assign values to the product
        // $product->set_name($data['gift_card_title']);

        // Sanitize input fields
        $fields = [
            'gift_card_title',
            'regular_price',
            'sale_price',
            '_sell_price_fixed',
            '_gst',
            'margin_currency',
            '_discount_valid_from',
            '_discount_valid_to',
            'parent_sku',
            '_discount_margin',
            'sku_type',
            '_supplier_sku',
            '_onsite_from',
            '_onsite_to',
            'activation_expire_date',
            'gift_card_expiry_type',
            'gift_card_expiry_date',
            'gift_card_expiry_duration'
        ];

        $number_fields = [
            'sell_price_lowest_denomination',
            'variable_range_from',
            'variable_range_to',
            '_denomination_amount',
            '_reedem_at_intervals',
            'margin_per',
            '_margin',
            '_quantity_per_transaction',
            '_total_value_per_transaction',
            '_cost_price',
            '_delivery_cost',
            'j_a_c_fulfillment_cost',
            '_total_sell_price',
            '_total_buy_price',
            '_total_buy_price_gst',
            '_supplier_fullfillment_price',
            'discounted_price',
            '_add_stock_level'
        ];

        $data = [];
        if (empty($product_id)) {

            if (isset($_POST['sku'])) {

                $sku = sanitize_text_field($_POST['sku']);

                // Check if SKU contains only letters and numbers
                if (!preg_match('/^[A-Za-z0-9_.-]*$/', $sku)) {
                    echo "<p style=''>Error: SKU can only contain letters, numbers, and underscores.</p>";
                    echo "<script>
                        setTimeout(function(){ window.location.href = window.location.href; }, 2000);
                    </script>";
                    return;
                }


                // Check if SKU is unique
                global $wpdb;
                $existing_product = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} 
                        WHERE post_type = 'product' 
                        AND post_status NOT IN ('trash', 'auto-draft') 
                        AND ID != %d 
                        AND ID IN (
                            SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s
                        )",
                        isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0,
                        $sku
                    )
                );

                if ($existing_product) {
                    echo "<p class='sku-error-field'>Error: SKU already exists.</p>";
                    // Commented on 20251224
                    // echo "<script>
                    //     setTimeout(function(){ window.location.href = window.location.href; }, 2000);
                    // </script>";
                    return; // Stop further execution
                }

                $data['sku'] = $sku;
            }
        } else {
            // If product ID is not empty (update), use submitted SKU without checks
            if (isset($_POST['sku'])) {
                $data['sku'] = sanitize_text_field($_POST['sku']);
            }
        }

        $all_fields = array_merge($fields, $number_fields);

        // Fields that allow HTML content
        $html_fields = ['long_description', 'short_description', '_extra_header', 'terms_conditions', 'how_to_use', '_expire_date'];

        foreach ($html_fields as $field) {
            if (isset($_POST[$field])) {
                $value = wp_kses_post($_POST[$field]); // sanitize HTML content
                $data[$field] = $value;

                update_post_meta($product_id, $field, $value);
            }
        }
   
        foreach ($all_fields as $field) {
            if (!in_array($field, $html_fields)) {
               if (in_array($field, $number_fields)) {
                    // Handle numeric values safely
                    $data[$field] = isset($_POST[$field]) && $_POST[$field] !== '' ? floatval($_POST[$field]) : '';
                } else {
                    // Normal text fields
                    $data[$field] = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
                }

                // Save to DB (skip _add_stock_level; WooCommerce stock is set via set_stock_quantity)
                if ($field !== '_add_stock_level') {
                    update_post_meta($product_id, $field, $data[$field]);
                }
            }
        }

        $checkbox_fields = [
            'discounted_price_checkbox' => 'discounted_price_checkbox',
            'presetDeliveryClass'       => 'preset_delivery_class',
            'add_stock_checkbox'        => 'add_stock_levels',
            'add_transaction_limit_checkbox' => 'add_transaction_limit_checkbox',
            'always_on'                 => 'always_on'
        ];

        foreach ($checkbox_fields as $field => $meta_key) {
            $value = (isset($_POST[$field]) && $_POST[$field] === 'on') ? 'Yes' : 'No';
            
            if (!empty($product_id)) {
                update_post_meta($product_id, $meta_key, $value);
            }
        
            if ($field === 'presetDeliveryClass') {
                if ($value === 'No') {
                    wp_set_post_terms($product_id, array(), 'product_shipping_class', false);
                    delete_post_meta($product_id, 'presetClasses');
                    clean_post_cache($product_id);
                    wc_delete_product_transients($product_id);
                }
            }
        }

        if ( isset( $_POST['is_it_gift_card_plus_product'] ) ) {
            $gc_plus_val = sanitize_text_field( $_POST['is_it_gift_card_plus_product'] );
            
            // Validate value is exactly 'true' or 'false'
            if ( in_array( $gc_plus_val, ['true', 'false'] ) ) {
                // Save Native Meta (Guaranteed to appear in DB)
                update_post_meta( $product_id, 'is_it_gift_card_plus_product', $gc_plus_val );

            }
        } else {
            // Optional: Default to 'false' if nothing was sent
            update_post_meta( $product_id, 'is_it_gift_card_plus_product', 'false' );
        }

        if ( isset( $_POST['is_swap_eligible'] ) ) {
            $swap_val = sanitize_text_field( $_POST['is_swap_eligible'] );
            
            if ( in_array( $swap_val, ['true', 'false'] ) ) {
                // 1. Save Native WP Meta (for fast queries)
                update_post_meta( $product_id, 'is_swap_allowed', $swap_val );
                
                // 2. Sync with ACF using the Key you provided
                update_field( 'field_6953ab618b5bd', $swap_val, $product_id ); 
            }
        } else {
            // Default to false if missing
            update_post_meta( $product_id, 'is_swap_allowed', 'false' );
            update_field( 'field_6953ab618b5bd', 'false', $product_id );
        }

        // Process array fields
        $data['supplier']           = isset($_POST['supplier']) ? intval($_POST['supplier']) : '';
        $data['product_brand']      = isset($_POST['product_brand']) ? array_map('sanitize_text_field', (array) $_POST['product_brand']) : [];
        $data['eligible_retailers'] = isset($_POST['eligible_retailers']) ? array_map('sanitize_text_field', (array) $_POST['eligible_retailers']) : [];
        $data['product_cat']        = isset($_POST['product_cat']) ? (is_array($_POST['product_cat']) ? $_POST['product_cat'] : explode(',', $_POST['product_cat'])) : [];
        $data['product_tags']       = isset($_POST['product_tags']) ? array_map('sanitize_text_field', (array) $_POST['product_tags']) : [];

        // Commented on 20251224
        // $placement_slugs = isset($_POST['featured_placements']) ? array_map('sanitize_text_field', (array) $_POST['featured_placements']) : [];
        $data['icons'] = isset($_POST['icons']) ? array_map('sanitize_text_field', (array) $_POST['icons']) : [];
        $success_flag = false; // Initialize success flag

        // Commented on 20251224
        // Check if SKU exists
        // if (!empty($data['sku'])) {

        // Commented on 20251224
        //     $error_message = 'Error: The SKU "' . esc_html($data['sku']) . '" is already assigned to another product. Please use a unique SKU.';
        // } else {
        $temp_redirect_flag = 0;
        if (empty($product_id)) {

            $product = new WC_Product_Simple();
            $product->set_name($data['gift_card_title']);
            $product->set_description($data['long_description']);
            $product->set_short_description($data['short_description']);
            $product->set_sku($data['sku']);
            // Commented on 20251224
            // $regular_price = !empty($_POST['discounted_price_checkbox']) && !empty($_POST['discounted_price'])
            //     ? $_POST['discounted_price']
            //     : (!empty($_POST['_sell_price_fixed']) ? $_POST['_sell_price_fixed'] : '');

            // Commented on 20251224
            // if (!empty($regular_price)) {
            //     $product->set_regular_price($regular_price);
            // }
            // // echo $regular_price;
            // if (!empty($data['sale_price'])) {
            //     $product->set_sale_price($data['sale_price']);
            // }

            $denomination_type      = isset($_POST['denomination_type']) ? strtolower(sanitize_text_field($_POST['denomination_type'])) : 'fixed';
            $sell_price_fixed       = isset($_POST['_sell_price_fixed']) && $_POST['_sell_price_fixed'] !== '' ? $_POST['_sell_price_fixed'] : '';
            $sell_price_lowest      = isset($_POST['sell_price_lowest_denomination']) && $_POST['sell_price_lowest_denomination'] !== '' ? $_POST['sell_price_lowest_denomination'] : '';
            $denomination_amount    = !empty($_POST['_denomination_amount']) ? $_POST['_denomination_amount'] : '';
            $discounted_price       = !empty($_POST['discounted_price']) ? $_POST['discounted_price'] : '';
            $discounted_active      = !empty($_POST['discounted_price_checkbox']); // true if checked

            // Variable = use Sell Price Lowest Denomination; Fixed = use Sell Price Fixed
            $regular_price_value = ($denomination_type === 'variable' && $sell_price_lowest !== '') ? $sell_price_lowest : $sell_price_fixed;
            if ($regular_price_value !== '') {
                if ($discounted_active && $discounted_price !== '') {
                    $product->set_regular_price($regular_price_value);
                    $product->set_sale_price($discounted_price);
                } else {
                    $product->set_regular_price($regular_price_value);
                    $product->set_sale_price('');
                }
            }

            foreach ($data as $key => $value) {
                if (!empty($value)) {
                    $product->update_meta_data($key, $value);
                }
            }

            // Stock management: sync with "Add Stock" checkbox and level
            if (!empty($_POST['add_stock_checkbox'])) {
                $product->set_manage_stock(true);
                $stock_qty = isset($data['_add_stock_level']) && $data['_add_stock_level'] !== '' ? (int) $data['_add_stock_level'] : 0;
                $product->set_stock_quantity($stock_qty);
            } else {
                $product->set_manage_stock(false);
            }


            // Commented on 20251224
            // Set product status
            // $product->set_status(isset($_POST['available_for_all_user']) ? 'publish' : 'draft');
            // $product->set_status(isset($_POST['product_status']) ? 'publish' : 'draft');
                       
            if (!empty($_POST['presetClasses'])) {
                $shipping_class_term = get_term_by('slug', sanitize_text_field($_POST['presetClasses']), 'product_shipping_class');
                $product->set_shipping_class_id($shipping_class_term ? $shipping_class_term->term_id : 0);
            }
            if (!empty($_POST['product_status'])) {
                $product_status = sanitize_text_field($_POST['product_status']);
                $product->set_status($product_status);
            } else {
                $product->set_status('draft');
            }

            // Save product
            $product_id = $product->save();

            if (!empty($_POST['eligible_gift_cards_json'])) {

                $raw_json = wp_unslash($_POST['eligible_gift_cards_json']);
                $arr = json_decode($raw_json, true);

                if (!empty($arr)) {

                    $clean = [];

                    foreach ($arr as $row) {

                        if (!empty($row['product_id']) && is_numeric($row['product_id'])) {
                            $pid = intval($row['product_id']);
                        } else {
                            if (empty($row['sku'])) continue;

                            $pid = wc_get_product_id_by_sku($row['sku']);
                            if (!$pid) continue;

                            if (get_post_type($pid) === 'product_variation') {
                                $pid = wp_get_post_parent_id($pid);
                            }
                        }

                        if ($pid && get_post_type($pid) === 'product') {
                            $clean[] = [
                                'product_id' => $pid,
                                'sku'        => get_post_meta($pid, '_sku', true)
                            ];
                        }
                    }

                    // Remove duplicates
                    $clean = array_values(array_unique($clean, SORT_REGULAR));

                    // ✅ SAVE JSON (THIS WAS MISSING)
                    update_post_meta($product_id, 'eligible_gift_cards_json', wp_json_encode($clean));
                }
            }


            if ( ! empty( $_POST['_discount_valid_from'] ) ) {
                $from_date_string = sanitize_text_field( $_POST['_discount_valid_from'] );
                // Use DateTime object to parse the date string and then get the timestamp
                $from_date = new DateTime( $from_date_string );
                update_post_meta( $product->get_id(), '_sale_price_dates_from', $from_date->getTimestamp() );
            }
            
            if ( ! empty( $_POST['_discount_valid_to'] ) ) {
                $to_date_string = sanitize_text_field( $_POST['_discount_valid_to'] );
                // Use DateTime object to parse the date string and then get the timestamp
                $to_date = new DateTime( $to_date_string );
                update_post_meta( $product->get_id(), '_sale_price_dates_to', $to_date->getTimestamp() );
            }
            

            foreach ($html_fields as $field) {
                if (isset($_POST[$field])) {
                    $value = wp_kses_post($_POST[$field]); // sanitize HTML content
                    $data[$field] = $value;
    
                    update_post_meta($product_id, $field, $value);
                }
            }

            foreach ($all_fields as $field) {
                if (!in_array($field, $html_fields)) {
                   if (in_array($field, $number_fields)) {
                        // Handle numeric values safely
                        $data[$field] = isset($_POST[$field]) && $_POST[$field] !== '' ? floatval($_POST[$field]) : '';
                    } else {
                        // Normal text fields
                        $data[$field] = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
                    }
    
                    // Save to DB
                    update_post_meta($product_id, $field, $data[$field]);
                }
            }

            foreach ($checkbox_fields as $field => $meta_key) {
                $value = (isset($_POST[$field]) && $_POST[$field] === 'on') ? 'Yes' : 'No';
                
                if (!empty($product_id)) {
                    update_post_meta($product_id, $meta_key, $value);
                }
            
                if ($field === 'presetDeliveryClass') {
                    if ($value === 'No') {
                        wp_set_post_terms($product_id, array(), 'product_shipping_class', false);
                        delete_post_meta($product_id, 'presetClasses');
                        clean_post_cache($product_id);
                        wc_delete_product_transients($product_id);
                    }
                }
            }

            if ( isset( $_POST['is_it_gift_card_plus_product'] ) ) {
                $gc_plus_val = sanitize_text_field( $_POST['is_it_gift_card_plus_product'] );
                
                // Validate value is exactly 'true' or 'false'
                if ( in_array( $gc_plus_val, ['true', 'false'] ) ) {
                    // Save Native Meta (Guaranteed to appear in DB)
                    update_post_meta( $product_id, 'is_it_gift_card_plus_product', $gc_plus_val );
                    update_field( 'field_694b815f0c14c', $gc_plus_val, $product_id ); 
                }
            } else {
                // Optional: Default to 'false' if nothing was sent
                update_post_meta( $product_id, 'is_it_gift_card_plus_product', 'false' );
                update_field( 'field_694b815f0c14c', 'false', $product_id );
            }

            if ( isset( $_POST['is_swap_eligible'] ) ) {
                $swap_val = sanitize_text_field( $_POST['is_swap_eligible'] );
                
                if ( in_array( $swap_val, ['true', 'false'] ) ) {
                    // 1. Save Native WP Meta (for fast queries)
                    update_post_meta( $product_id, 'is_swap_allowed', $swap_val );
                    
                    // 2. Sync with ACF using the Key you provided
                    update_field( 'field_6953ab618b5bd', $swap_val, $product_id ); 
                }
            } else {
                // Default to false if missing
                update_post_meta( $product_id, 'is_swap_allowed', 'false' );
                update_field( 'field_6953ab618b5bd', 'false', $product_id );
            }


            if (isset($_POST['buyer_upload']) && $_POST['buyer_upload'] === 'on') {
                // Commented on 20251224
                // update_field($product_id, 'presetDeliveryClass', 'Yes');
                update_field('buyer_upload', 'yes', $product_id);
                // Commented on 20251224
                // update_post_meta($product_id, 'preset_delivery_class', 'yes');
            } else {
                // Commented on 20251224
                // update_field($product_id, 'presetDeliveryClass', 'No');
                update_field('buyer_upload', 'no', $product_id);
                // Commented on 20251224
                // update_post_meta($product_id, 'preset_delivery_class', 'no');
            }


            // Commented on 20251224
            // if (isset($_POST['buyer_upload'])) {
            //     echo 'buyer_upload  value: ' . $_POST['buyer_upload'];
            // } else {
            //     echo 'buyer_upload  is not set';
            // }
                
            // Check if our checkbox is set in the form
            if (!empty($_POST['buyer_upload'])) {
                update_field('buyer_upload', 'Yes', $product_id);
                //update_post_meta('buyer_upload', ['Yes'], $product_id);
            } else {
                update_field('buyer_upload', 'No', $product_id);
                //update_post_meta('buyer_upload', ['No'], $product_id);
            }

            if (!empty($_POST['add_stock_checkbox'])) {
                update_field('add_stock_levels', 'Yes', $product_id);
            } else {
                update_field('add_stock_levels', 'No', $product_id);
            }

            if (!empty($data['supplier'])) {
                update_post_meta($product_id, 'supplier', $data['supplier']);
            }

            if (!empty($_POST['label_extra_header'])) {
                update_post_meta($product_id, 'label_extra_header', sanitize_text_field($_POST['label_extra_header']));
            }

            if (!empty($_POST['label_how_to_use'])) {
                update_post_meta($product_id, 'label_how_to_use', sanitize_text_field($_POST['label_how_to_use']));
            }

            if (!empty($_POST['label_terms_conditions'])) {
                update_post_meta($product_id, 'label_terms_conditions', sanitize_text_field($_POST['label_terms_conditions']));
            }

            if (!empty($_POST['label__expire_date'])) {
                update_post_meta($product_id, 'label__expire_date', sanitize_text_field($_POST['label__expire_date']));
            }

            if (!empty($_POST['label_short_description'])) {
                update_post_meta($product_id, 'label_short_description', sanitize_text_field($_POST['label_short_description']));
            }

            if (!empty($_POST['label_long_description'])) {
                update_post_meta($product_id, 'label_long_description', sanitize_text_field($_POST['label_long_description']));
            }
            if ($product_id) {

                $tag_ids = [];

                // Process product tags correctly
                foreach ($data['product_tags'] as $tag) {
                    if (!empty($tag)) {
                        if (is_numeric($tag)) {
                            $tag_ids[] = (int) $tag; // Tag ID provided
                        } else {
                            $existing_tag = get_term_by('name', $tag, 'product_tag'); // Fix taxonomy name

                            if ($existing_tag) {
                                $tag_ids[] = $existing_tag->term_id;
                            } else {
                                $new_tag = wp_insert_term($tag, 'product_tag'); // Fix taxonomy name

                                if (!is_wp_error($new_tag) && isset($new_tag['term_id'])) {
                                    $tag_ids[] = $new_tag['term_id'];
                                }
                            }
                        }
                    }
                }
                // Always save featured_placements (isset check handles both array and missing - empty array clears display_on)
                // $placement_slugs = isset($_POST['featured_placements']) ? array_map('sanitize_text_field', (array) $_POST['featured_placements']) : [];
                // $placement_slugs = array_filter($placement_slugs); // Remove empty values
                // $placement_string = implode(',', $placement_slugs);
                $placements = isset($_POST['featured_placements']) ? array_map('sanitize_text_field', (array) $_POST['featured_placements']) : [];
               // $placements = array_filter($placements); // Remove empty values
                update_field('featured_placements', $placements, $product_id);

                // Assign tags to the product
                if (!empty($tag_ids)) {
                    wp_set_object_terms($product_id, $tag_ids, 'product_tag'); // Fix taxonomy name
                }

                $taxonomies = ['icons'];
                $eligible_retailer = ['eligible_retailers'];

                $product_brands = ['product_brand'];
                foreach ($product_brands as $brands) {
                    if (!empty($data[$brands])) {
                        $term_names = [];

                        foreach ($data[$brands] as $term_name) {
                            $term = get_term_by('slug', $term_name, $brands);

                            if (!$term) {
                                $new_term = wp_insert_term(str_replace('-', ' ', $term_name), $taxonomy);
                                if (!is_wp_error($new_term)) {
                                    $term_names[] = $term_name;
                                }
                            } else {
                                $term_names[] = $term->name;
                            }
                        }

                        if (!empty($term_names)) {
                            wp_set_object_terms($product_id, $term_names, $brands);
                        }
                    }
                }


                foreach ($taxonomies as $taxonomy) {
                    if (!empty($data[$taxonomy])) {
                        $term_names = [];

                        foreach ($data[$taxonomy] as $term_name) {
                            $term = get_term_by('name', $term_name, $taxonomy);

                            if (!$term) {
                                $new_term = wp_insert_term($term_name, $taxonomy);
                                if (!is_wp_error($new_term)) {
                                    $term_names[] = $term_name;
                                }
                            } else {
                                $term_names[] = $term->name;
                            }
                        }

                        if (!empty($term_names)) {
                            wp_set_object_terms($product_id, $term_names, $taxonomy);
                        }
                    }
                }
                foreach ($eligible_retailer as $retailer) {
                    if (!empty($data[$retailer])) {
                        $term_names = [];

                        foreach ($data[$retailer] as $term_name) {
                            $term = get_term_by('id', $term_name, $retailer);

                            if (!$term) {
                                $new_term = wp_insert_term($term_name, $retailer);
                                if (!is_wp_error($new_term)) {
                                    $term_names[] = $term_name;
                                }
                            } else {
                                $term_names[] = $term->name;
                            }
                        }

                        if (!empty($term_names)) {
                            wp_set_object_terms($product_id, $term_names, $retailer);
                        }
                    }
                }
                $success_flag = true;
                // Commented on 20251224
                // $success_message = 'Congratulations! You have successfully created a new Gift Card Plus item. Click <a href="' . esc_url( home_url( '/all-products/' ) ) . '">here</a> to view all gift cards.! Product ID: ' . $product_id;

                if (!empty($data['product_cat'])) {
                    $term_ids = array();

                    foreach ($data['product_cat'] as $category) {
                        // Check if the category is numeric (existing category ID)
                        if (is_numeric($category)) {
                            $term_ids[] = intval($category);
                        } else {
                            // It's a new category name - create it
                            $new_term = wp_insert_term(
                                sanitize_text_field($category),
                                'product_cat'
                            );

                            if (!is_wp_error($new_term)) {
                                $term_ids[] = $new_term['term_id'];
                            } else {
                                // Handle error (possibly category already exists)
                                $existing_term = get_term_by('name', $category, 'product_cat');
                                if ($existing_term) {
                                    $term_ids[] = $existing_term->term_id;
                                }
                            }
                        }
                    }

                    if (!empty($term_ids)) {
                        wp_set_object_terms($product_id, $term_ids, 'product_cat');
                    }

                    foreach ($term_ids as $tid) {
                        $rows = get_field('sku_assigned_arr', 'product_cat_' . $tid);
                        if (!is_array($rows)) {
                            $rows = [];
                        }

                        $temp_rows = array_column($rows, 'assigned_product');

                        $already_exists = false;
                        if( !in_array($product_id, $temp_rows) ){
                            $rows[] = [
                                'assigned_product' => $product_id
                            ];
                        }
                        // Commented on 20251224
                        /*foreach ($rows as $row) {
                            if (!empty($row['assigned_product']) && intval($row['assigned_product']) === $product_id) {
                                $already_exists = true;
                                break;
                            }
                        }

                        // Commented on 20251224
                        if ($already_exists) {
                            continue; // Skip if product already assigned to category
                        }*/

                        // Update the repeater field in category
                        update_field('sku_assigned_arr', $rows, 'product_cat_' . $tid);
                    }
                }

                if (!empty($_POST['denomination_type'])) {
                    $denomination_values = $_POST['denomination_type'];

                    // Commented on 20251224
                    // Ensure it's an array
                    //$denomination_values = is_array($denomination_values) ? explode(',',$denomination_values) : str_val($denomination_values);

                    // Define allowed options
                    $allowed_denomination_types = ['variable', 'fixed'];

                    $sanitized_denomination_values = '';
                    if( in_array($denomination_values, $allowed_denomination_types) ){
                        $sanitized_denomination_values = $denomination_values;
                    }
                    // Commented on 20251224
                    // Sanitize and filter values
                    // $sanitized_denomination_values = array_filter(array_map('sanitize_text_field', $denomination_values), function ($value) use ($allowed_denomination_types) {
                    //     return in_array($value, $allowed_denomination_types);
                    // });

                    // Save the field only if we have valid values
                    if (!empty($sanitized_denomination_values)) {
                        update_field('denomination_type', $sanitized_denomination_values, $product_id);
                    }
                }

                // Define mappings for readable values
                $expiry_type_labels = [
                    'no_expiry'                          => 'No Expiry',
                    'gift_set_date'                      => 'Expires on a Set Date',
                    'expiry_period_starts_on_purchase'   => 'Expiry Period Starts on Purchase',   // CHANGED KEY
                    'expiry_period_starts_on_activation' => 'Expiry Period Starts on Activation', // CHANGED KEY
                ];

                $expiry_value = ''; // Initialize variable outside loop

                // Save Gift Card Expiry Type (Save the KEY, not the Label)
                if (!empty($_POST['gift_card_expiry_type'])) {
                    $expiry_value = sanitize_text_field($_POST['gift_card_expiry_type']);
                    
                    // Validate it's a valid key
                    if (array_key_exists($expiry_value, $expiry_type_labels)) {
                        // Save native meta AND ACF field for compatibility
                        update_post_meta($product_id, 'gift_card_expiry_type', $expiry_value);
                        update_field('gift_card_expiry_type', $expiry_value, $product_id);
                    }
                }

                // Save Gift Card Expiry Date (Logic uses the Key)
                if ($expiry_value === 'gift_set_date' && !empty($_POST['gift_card_expiry_date'])) {
                    update_field('gift_card_expiry_date', sanitize_text_field($_POST['gift_card_expiry_date']), $product_id);
                    delete_field('gift_card_expiry_duration', $product_id);
                    delete_field('gift_card_expiry_unit', $product_id);
                }

                // Save Duration/Unit (Logic uses the corrected Keys)
                if (($expiry_value === 'expiry_period_starts_on_purchase' || $expiry_value === 'expiry_period_starts_on_activation') && !empty($_POST['gift_card_expiry_duration']) && !empty($_POST['gift_card_expiry_unit'])) {
                    update_field('gift_card_expiry_duration', intval($_POST['gift_card_expiry_duration']), $product_id);
                    update_field('gift_card_expiry_unit', sanitize_text_field($_POST['gift_card_expiry_unit']), $product_id);
                    delete_field('gift_card_expiry_date', $product_id);
                }

                // Commented on 20260120
                // Save Gift Card Expiry Type
                // if (!empty($_POST['gift_card_expiry_type'])) {
                //     $expiry_value = $_POST['gift_card_expiry_type'];
                //     $formatted_expiry_value = isset($expiry_type_labels[$expiry_value]) ? $expiry_type_labels[$expiry_value] : $expiry_value;
                //     update_field('gift_card_expiry_type', $formatted_expiry_value, $product_id);
                // }

                // Commented on 20260120
                // // Save Gift Card Expiry Date
                // if ($expiry_value === 'gift_set_date' && !empty($_POST['gift_card_expiry_date'])) {
                //     update_field('gift_card_expiry_date', sanitize_text_field($_POST['gift_card_expiry_date']), $product_id);
                //     delete_field('gift_card_expiry_duration', $product_id);
                //     delete_field('gift_card_expiry_unit', $product_id);
                // }

                // Commented on 20260120
                // // Save expiry duration & unit if 'purchase' or 'activation' is selected
                // if (($expiry_value === 'purchase' || $expiry_value === 'activation') && !empty($_POST['gift_card_expiry_duration']) && !empty($_POST['gift_card_expiry_unit'])) {
                //     update_field('gift_card_expiry_duration', intval($_POST['gift_card_expiry_duration']), $product_id);
                //     update_field('gift_card_expiry_unit', sanitize_text_field($_POST['gift_card_expiry_unit']), $product_id);
                //     delete_field('gift_card_expiry_date', $product_id);
                // }
                // Define allowed values (optional, for security)
                $allowed_activation_types = [
                    'no_activation_expiry',
                    'no_activation_needed',
                    'activation_set_date',
                    'set_period',
                ];

                // Save Activation Expiry Type
                if (!empty($_POST['activation_expiry_type'])) {
                    $activation_value = sanitize_text_field($_POST['activation_expiry_type']);

                    // Save only if the value is allowed
                    if (in_array($activation_value, $allowed_activation_types)) {
                        update_field('activation_expiry_type', $activation_value, $product_id);
                    }
                }

                // Save Activation Expiry Date
                if (isset($activation_value) && $activation_value === 'activation_set_date' && !empty($_POST['activation_expiry_date'])) {
                    update_field('activation_expiry_date', sanitize_text_field($_POST['activation_expiry_date']), $product_id);
                    delete_field('activation_expiry_duration', $product_id);
                    delete_field('activation_expiry_unit', $product_id);
                }

                // Save Activation Expiry Period
                if (isset($activation_value) && $activation_value === 'set_period' && !empty($_POST['activation_expiry_duration'])) {
                    update_field('activation_expiry_duration', intval($_POST['activation_expiry_duration']), $product_id);
                    delete_field('activation_expiry_date', $product_id);
                }

                // Save Activation Expiry Unit
                if (isset($activation_value) && $activation_value === 'set_period' && !empty($_POST['activation_expiry_unit'])) {
                    update_field('activation_expiry_unit', sanitize_text_field($_POST['activation_expiry_unit']), $product_id);
                    delete_field('activation_expiry_date', $product_id);
                }




            } else {
                $error_message = 'Failed to create product. Please try again.';
            }

            // ===========================================

            // Process product images
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $product_images = [];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
            $max_size = 3 * 1024 * 1024; // 3MB
            
            $cover_image = '';
            $cover_image_link = true;
            $cover_image_existing = false;
            
            if (isset($_POST['cover-img']) && strpos($_POST['cover-img'], 'blob:') !== false) {
                $cover_image_link = false;
                $cover_image_existing = false;
            }

            if( isset($_POST['cover-img']) && (int)$_POST['cover-img'] > 0 ){
                $image_post = get_post($_POST['cover-img']);
                if ($image_post && $image_post->post_type === 'attachment') {
                    $cover_image_link = false;
                    $cover_image_existing = true;
                }
            }
            
            // Handle uploaded image files
            $temp_array = [];

            $index3 = 0;
            if( !empty($_POST['new_existingImages']) ){
                foreach ($_POST['new_existingImages'] as $image_ID) {
                    if( $index3 == 0 && $cover_image_existing ){
                        $cover_image = $image_ID;
                    }else{
                        $product_images[] = $image_ID;
                    }
                }
            }

            $index = 0;
            if (!empty($_FILES['product_images']['name'][0])) {
                foreach ($_FILES['product_images']['name'] as $key => $image_name) {
                    if (!empty($image_name)) {
                        
                        if (in_array($_FILES['product_images']['name'][$key], $temp_array)) {
                            continue;
                        }
                        
                        $temp_array[] = $_FILES['product_images']['name'][$key];

                        $file = [
                            'name'      => $_FILES['product_images']['name'][$key],
                            'type'      => $_FILES['product_images']['type'][$key],
                            'tmp_name'  => $_FILES['product_images']['tmp_name'][$key],
                            'error'     => $_FILES['product_images']['error'][$key],
                            'size'      => $_FILES['product_images']['size'][$key],
                        ];

                        // Validate file
                        if ($file['error'] !== UPLOAD_ERR_OK) {
                            continue;
                        }

                        if (!in_array($file['type'], $allowed_mime) || $file['size'] > $max_size) {
                            continue;
                        }

                        // Validate image content
                        try {
                            if (!in_array($file['type'], ['image/svg+xml', 'image/webp'])) {
                                $image_info = getimagesize($file['tmp_name']);
                                if (!$image_info || !in_array($image_info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) {
                                    throw new Exception('Invalid image file');
                                }
                            }
                        } catch (Exception $e) {
                            continue;
                        }

                        // Handle EXIF data quietly
                        if (function_exists('exif_read_data') && $file['type'] === 'image/jpeg') {
                            try {
                                // Suppress EXIF warnings
                                $exif = @exif_read_data($file['tmp_name']);
                            } catch (Exception $ex) {
                            }
                        }

                        $check_image = get_existing_attachment_id_by_name($file['name']);
                        
                        if(empty($check_image)){
                            // Handle upload with error suppression
                            $uploaded_file = @media_handle_sideload($file, 0);
                        }else{
                            // If image already exists, use the existing attachment ID
                            $uploaded_file = $check_image;
                        }

                        if (!is_wp_error($uploaded_file)) {
                            if( $index == 0 && !$cover_image_link && !$cover_image_existing ){
                                $cover_image = $uploaded_file;
                                $index++;
                            } else if (!is_wp_error($uploaded_file)) {
                                $product_images[] = $uploaded_file;
                            }
                        } else {
                        }
                    }
                }
            }else{
                $cover_image_link = true;
            }

            $index2 = 0;
            // Handle product_image_links (image URLs)
            if (!empty($_POST['product_image_links'])) {

                foreach ($_POST['product_image_links'] as $image_url) {
                    $image_url = esc_url(trim($image_url)); // Ensure the URL is sanitized

                    // Validate image URL format
                    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $file_name = basename($image_url);
                    $attachment_id = get_existing_attachment_id_by_name($file_name);

                    if( !$attachment_id ){
                        $file_array = array();
                        $file_array['name'] = basename($image_url);
                        $file_array['tmp_name'] = download_url($image_url);

                        if (is_wp_error($file_array['tmp_name'])) {
                            $error_message = $file_array['tmp_name']->get_error_message();
                        } else {

                            $attachment_id = media_handle_sideload($file_array, $product_id);
                            // pr($attachment_id);
                            if (is_wp_error($attachment_id)) {
                                $error_message = $attachment_id->get_error_message();
                            } else {
                                // Append to gallery
                                if( $index2 == 0 && $cover_image_link && !$cover_image_existing ){
                                    $cover_image = $attachment_id;
                                    $index2++;
                                } else{
                                    $product_images[] = $attachment_id;
                                }
                            }
                        }
                    }

                    // 4. If we have a valid ID (either new or existing), add it to the gallery
                    if ($attachment_id && !is_wp_error($attachment_id) && !in_array($attachment_id, $product_images)) {
                        if( $index2 == 0 && $cover_image_link && !$cover_image_existing ){
                            $cover_image = $attachment_id;
                            $index2++;
                        } else{
                            $product_images[] = $attachment_id;
                        }
                    }
                }
            }else{
                $cover_image_link = false;
            }

            $index3 = 0;
            if( !empty($_POST['new_existingImages']) ){
                foreach ($_POST['new_existingImages'] as $image_ID) {
                    if( $index3 == 0 && $cover_image_existing ){
                        $cover_image = $image_ID;
                        break;
                    }
                }
            }

            if( empty($cover_image) ){
                $cover_image = $product_images[0];
                unset($product_images[0]);
            }

            // Commented on 20251224
            /*set_post_thumbnail($product_id, $cover_image);
            exit;*/

            // Ensure images are correctly saved to the gallery
            if (!empty($product_images)) {
                $existing_gallery = get_post_meta($product_id, '_product_image_gallery', true);
                $existing_gallery = !empty($existing_gallery) ? explode(',', $existing_gallery) : [];
                // Remove duplicates and sanitize
                $product_images = array_merge($existing_gallery, $product_images);
                $product_images = array_unique(array_map('intval', $product_images)); // Filter duplicates
                update_post_meta($product_id, '_product_image_gallery', implode(',', $product_images));

            }

            // ===========================================
            if( !empty($cover_image) ){
                // Set as product featured image
                set_post_thumbnail($product_id, $cover_image);
            }

            // Process brand logo
            // Process brand logo upload
            if (!empty($_POST['brand_thumbnail_url']) && empty($_FILES['brand_logo']['name'])) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $image_url = esc_url_raw($_POST['brand_thumbnail_url']);

                // Check if image already exists in media library
                $existing_id = attachment_url_to_postid($image_url);
                $existing_attachment_id = get_existing_attachment_id_by_name($file_name);

                if ($existing_id) {
                    $uploaded_file_id = $existing_id;
                } else {
                    // Download the image to a temp file
                    $tmp = download_url($image_url);

                    if (is_wp_error($tmp)) {
                        return;
                    }

                    $file_array = [
                        'name' => basename($image_url),
                        'type' => mime_content_type($tmp),
                        'tmp_name' => $tmp,
                        'error' => 0,
                        'size' => filesize($tmp),
                    ];

                    $overrides = ['test_form' => false];
                    $check_image = get_existing_attachment_id_by_name($file_array['name']);

                    if(empty($check_image)){
                        $uploaded_file_id = media_handle_sideload($file_array, $product_id);
                    } else {
                        // If image already exists, use the existing attachment ID
                        $uploaded_file_id = $check_image;
                    }

                    // Clean up temp file if upload failed
                    if (is_wp_error($uploaded_file_id)) {
                        @unlink($tmp);
                        return;
                    }
                }

                // Save in product meta
                $product->update_meta_data('brand_logo', $uploaded_file_id);

                // Assign to brand if selected
                if (!empty($_POST['product_brand'][0])) {
                    $brand_slug = sanitize_text_field($_POST['product_brand'][0]);
                    $term = get_term_by('slug', $brand_slug, 'product_brand');

                    if ($term && !is_wp_error($term)) {
                        update_term_meta($term->term_id, 'thumbnail_id', $uploaded_file_id);
                        wp_set_object_terms($product_id, (int) $term->term_id, 'product_brand', false);
                    }
                }
            } else if (!empty($_FILES['brand_logo']['name'])) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $file = $_FILES['brand_logo'];

                $check_image = get_existing_attachment_id_by_name($file['name']);

                if(empty($check_image)){
                    $uploaded_file_id = media_handle_upload('brand_logo', $product_id);
                } else {
                    // If image already exists, use the existing attachment ID
                    $uploaded_file_id = $check_image;
                }

                if (is_wp_error($uploaded_file_id)) {
                    return;
                }

                // Commented on 20251224
                // Set as product featured image
                //set_post_thumbnail($product_id, $uploaded_file_id);

                // Save in product meta
                $product->update_meta_data('brand_logo', $uploaded_file_id);

                if (!empty($_POST['product_brand'][0])) {
                    $brand_slug = sanitize_text_field($_POST['product_brand'][0]);
                    $term = get_term_by('slug', $brand_slug, 'product_brand');

                    if ($term && !is_wp_error($term)) {
                        update_term_meta($term->term_id, 'thumbnail_id', $uploaded_file_id);
                        wp_set_object_terms($product_id, (int) $term->term_id, 'product_brand', false);
                    }
                }
            }

            // Optional: Process image from URL
            if (!empty($_POST['brand_thumbnail_url'])) {
                $image_url = esc_url_raw($_POST['brand_thumbnail_url']);
                $file_name = basename(parse_url($image_url, PHP_URL_PATH));
                $attach_id = false;
            
                $attach_id = get_existing_attachment_id_by_name($file_name);
            
                if (!$attach_id) {
                    $upload_dir = wp_upload_dir();
                    $file_path  = $upload_dir['path'] . '/' . $file_name;
            
                    $ch = curl_init($image_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    $image_data = curl_exec($ch);
                    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
            
                    if ($image_data && $http_code === 200) {
                        file_put_contents($file_path, $image_data);
            
                        $file_type = wp_check_filetype($file_name, null);
                        $attachment = [
                            'post_mime_type' => $file_type['type'],
                            'post_title'     => sanitize_file_name($file_name),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        ];
            
                        $new_attach_id = wp_insert_attachment($attachment, $file_path, $product_id);
            
                        if (!is_wp_error($new_attach_id)) {
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $attach_data = wp_generate_attachment_metadata($new_attach_id, $file_path);
                            wp_update_attachment_metadata($new_attach_id, $attach_data);
                            $attach_id = $new_attach_id; // Set attach_id to the newly created one
                        }
                    }
                }
            
                // 3. If we have a valid attachment ID (either existing or newly created)
                if ($attach_id) {
                    // Commented on 20251224
                    // Set as product featured image
                    //set_post_thumbnail($product_id, $attach_id);
            
                    // Save in meta field
                    $product->update_meta_data('brand_logo', $attach_id);
                }
            }

            if (!empty($data['sku_type']) && $data['sku_type'] == 'Child' ) {
                $existing_product_id = wc_get_product_id_by_sku($data['parent_sku']);
                update_field('parent_sku', $existing_product_id ?: '', $product_id);
                update_post_meta($product_id, 'parent_sku', $data['parent_sku']);
                update_post_meta($product_id, 'sku_type', $data['sku_type']);
            }else if( !empty($data['sku_type']) && $data['sku_type'] != 'Child'  ){
                update_field('parent_sku', '');
                update_post_meta($product_id, 'parent_sku', '');
                update_post_meta($product_id, 'sku_type', $data['sku_type']);
            }

            // Commented on 20251224
            // Process parent SKU
            // if (!empty($data['parent_sku'])) {

            // Commented on 20251224
            //     $existing_product_id = wc_get_product_id_by_sku($data['parent_sku']);
            //     update_field('parent_sku', $existing_product_id ?: '', $product_id);
            //     update_post_meta($product_id, 'parent_sku', $data['parent_sku']);
            //     update_post_meta($product_id, 'sku_type', $data['sku_type']);
            // }
        } else {

            // Commented on 20251224
            // if (!empty($_POST['gift_card_title'])) {
            //     // $product->set_name(sanitize_text_field($_POST['gift_card_title']));
            //     echo "<p style='color:green;'>Product has been " . ($is_new_product ? "created" : "updated") . ". Product ID: $product_id</p>";
            // }
            $product->set_name($data['gift_card_title']);
            $product->set_description($data['long_description']);
            $product->set_short_description($data['short_description']);
            $product->set_sku($data['sku']);
            // Commented on 20251224
            // $regular_price = !empty($_POST['discounted_price_checkbox']) && !empty($_POST['discounted_price']) ? $_POST['discounted_price'] : (!empty($_POST['_sell_price_fixed']) ? $_POST['_sell_price_fixed'] : '');

            // Commented on 20251224
            // if (!empty($regular_price)) {
            //     $product->set_regular_price($regular_price);
            // }

            // Commented on 20251224
            // // echo $regular_price;
            // if (!empty($data['sale_price'])) {
            //     $product->set_sale_price($data['sale_price']);
            // }

            // Commented on 20251224
            // foreach ($data as $key => $value) {
            //     if (!empty($value)) {
            //         $product->update_meta_data($key, $value);
            //     }
            // }

            // Get posted values
            $denomination_type      = isset($_POST['denomination_type']) ? strtolower(sanitize_text_field($_POST['denomination_type'])) : 'fixed';
            $sell_price_fixed       = isset($_POST['_sell_price_fixed']) && $_POST['_sell_price_fixed'] !== '' ? $_POST['_sell_price_fixed'] : '';
            $sell_price_lowest      = isset($_POST['sell_price_lowest_denomination']) && $_POST['sell_price_lowest_denomination'] !== '' ? $_POST['sell_price_lowest_denomination'] : '';
            $denomination_amount    = !empty($_POST['_denomination_amount']) ? $_POST['_denomination_amount'] : '';
            $discounted_price       = !empty($_POST['discounted_price']) ? $_POST['discounted_price'] : '';
            $discounted_active      = !empty($_POST['discounted_price_checkbox']); // true if checked

            // Variable = use Sell Price Lowest Denomination; Fixed = use Sell Price Fixed
            $regular_price_value = ($denomination_type === 'variable' && $sell_price_lowest !== '') ? $sell_price_lowest : $sell_price_fixed;
            if ($regular_price_value !== '') {
                if ($discounted_active && $discounted_price !== '') {
                    $product->set_regular_price($regular_price_value);
                    $product->set_sale_price($discounted_price);
                } else {
                    $product->set_regular_price($regular_price_value);
                    $product->set_sale_price('');
                }
            }

            // Stock management: sync with "Add Stock" checkbox and level
            if (!empty($_POST['add_stock_checkbox'])) {
                $product->set_manage_stock(true);
                $stock_qty = isset($data['_add_stock_level']) && $data['_add_stock_level'] !== '' ? (int) $data['_add_stock_level'] : 0;
                $product->set_stock_quantity($stock_qty);
            } else {
                $product->set_manage_stock(false);
            }



          

            // Commented on 20251224
            // Set product status
            // $product->set_status(isset($_POST['available_for_all_user']) ? 'publish' : 'draft');
            // $product->set_status(isset($_POST['product_status']) ? 'publish' : 'draft');


            // Commented on 20251224
            // if (isset($_POST['presetDeliveryClass'])) {
            //     update_post_meta($product_id, 'presetDeliveryClass', 'yes');
            // } else {
            //     update_post_meta($product_id, 'presetDeliveryClass', 'no');
            // }
            
            if (!empty($_POST['presetClasses'])) {
                $shipping_class_term = get_term_by('slug', sanitize_text_field($_POST['presetClasses']), 'product_shipping_class');
                $product->set_shipping_class_id($shipping_class_term ? $shipping_class_term->term_id : 0);
            }
            if (!empty($_POST['product_status'])) {
                $product_status = sanitize_text_field($_POST['product_status']);
                $product->set_status($product_status);
            } else {
                $product->set_status('draft');
            }

            $product_id = $product->save();
            // --- ADDED: Save 'Is it a Gift Card Plus Product?' for UPDATES ---
            if ( isset( $_POST['is_it_gift_card_plus_product'] ) ) {
                $gc_plus_val = sanitize_text_field( $_POST['is_it_gift_card_plus_product'] );
                
                // Validate value is exactly 'true' or 'false'
                if ( in_array( $gc_plus_val, ['true', 'false'] ) ) {
                    // Save Native Meta
                    update_post_meta( $product_id, 'is_it_gift_card_plus_product', $gc_plus_val );
                }
            } else {
                // If the radio/checkbox is unchecked or missing in the POST, set to false
                update_post_meta( $product_id, 'is_it_gift_card_plus_product', 'false' );
            }

            // Save 'Eligible for Swap?'
            if ( isset( $_POST['is_swap_eligible'] ) ) {
                $swap_val = sanitize_text_field( $_POST['is_swap_eligible'] );
                
                if ( in_array( $swap_val, ['true', 'false'] ) ) {
                    // 1. Save Native WP Meta (for fast queries)
                    update_post_meta( $product_id, 'is_swap_allowed', $swap_val );
                    
                    // 2. Sync with ACF using the Key you provided
                    update_field( 'field_6953ab618b5bd', $swap_val, $product_id ); 
                }
            } else {
                // Default to false if missing
                update_post_meta( $product_id, 'is_swap_allowed', 'false' );
                update_field( 'field_6953ab618b5bd', 'false', $product_id );
            }


         
            if (!empty($_POST['eligible_gift_cards_json'])) {

                $raw_json = wp_unslash($_POST['eligible_gift_cards_json']);
                $arr = json_decode($raw_json, true);

                if (!empty($arr)) {

                    $clean = [];

                    foreach ($arr as $row) {

                        if (!empty($row['product_id']) && is_numeric($row['product_id'])) {
                            $pid = intval($row['product_id']);
                        } else {
                            if (empty($row['sku'])) continue;

                            $pid = wc_get_product_id_by_sku($row['sku']);
                            if (!$pid) continue;

                            if (get_post_type($pid) === 'product_variation') {
                                $pid = wp_get_post_parent_id($pid);
                            }
                        }

                        if ($pid && get_post_type($pid) === 'product') {
                            $clean[] = [
                                'product_id' => $pid,
                                'sku'        => get_post_meta($pid, '_sku', true)
                            ];
                        }
                    }

                    // Remove duplicates
                    $clean = array_values(array_unique($clean, SORT_REGULAR));

                    // ✅ SAVE JSON (THIS WAS MISSING)
                    update_post_meta($product_id, 'eligible_gift_cards_json', wp_json_encode($clean));
                }
            }

            if (!empty($_POST['buyer_upload'])) {
                update_field('buyer_upload', 'Yes', $product_id);
            } else {
                update_field('buyer_upload', 'No', $product_id);
            }


            // Commented on 20251224
            // Check if our checkbox is set in the form
            // if (isset($_POST['buyer_upload'])) {
            //     update_field('buyer_upload', ['Yes'], $product_id);
            // } else {
            //     update_field('buyer_upload', ['No'], $product_id);
            // }

            if (!empty($data['supplier'])) {
                update_post_meta($product_id, 'supplier', $data['supplier']);
            }

            if (!empty($_POST['label_extra_header'])) {
                update_post_meta($product_id, 'label_extra_header', sanitize_text_field($_POST['label_extra_header']));
            }

            if (!empty($_POST['label_how_to_use'])) {
                update_post_meta($product_id, 'label_how_to_use', sanitize_text_field($_POST['label_how_to_use']));
            }

            if (!empty($_POST['label_terms_conditions'])) {
                update_post_meta($product_id, 'label_terms_conditions', sanitize_text_field($_POST['label_terms_conditions']));
            }

            if (!empty($_POST['label__expire_date'])) {
                update_post_meta($product_id, 'label__expire_date', sanitize_text_field($_POST['label__expire_date']));
            }

            if (!empty($_POST['label_short_description'])) {
                update_post_meta($product_id, 'label_short_description', sanitize_text_field($_POST['label_short_description']));
            }

            if (!empty($_POST['label_long_description'])) {
                update_post_meta($product_id, 'label_long_description', sanitize_text_field($_POST['label_long_description']));
            }
            if ($product_id) {

                $tag_ids = [];

                // Process product tags correctly
                foreach ($data['product_tags'] as $tag) {
                    if (!empty($tag)) {
                        if (is_numeric($tag)) {
                            $tag_ids[] = (int) $tag; // Tag ID provided
                        } else {
                            $existing_tag = get_term_by('name', $tag, 'product_tag'); // Fix taxonomy name

                            if ($existing_tag) {
                                $tag_ids[] = $existing_tag->term_id;
                            } else {
                                $new_tag = wp_insert_term($tag, 'product_tag'); // Fix taxonomy name

                                if (!is_wp_error($new_tag) && isset($new_tag['term_id'])) {
                                    $tag_ids[] = $new_tag['term_id'];
                                }
                            }
                        }
                    }
                }

                // Always save featured_placements (isset check handles both array and missing - empty array clears display_on)
                // $placement_slugs = isset($_POST['featured_placements']) ? array_map('sanitize_text_field', (array) $_POST['featured_placements']) : [];
                // $placement_slugs = array_filter($placement_slugs); // Remove empty values
                // $placement_string = implode(',', $placement_slugs);
                // update_field('display_on', $placement_string, $product_id);

                $placements = isset($_POST['featured_placements']) ? array_map('sanitize_text_field', (array) $_POST['featured_placements']) : [];
               // $placements = array_filter($placements); // Remove empty values
                update_field('featured_placements', $placements, $product_id);

                // Assign tags to the product
                if (!empty($tag_ids)) {
                    wp_set_object_terms($product_id, $tag_ids, 'product_tag'); // Fix taxonomy name
                }

                $taxonomies = ['icons'];
                $eligible_retailer = ['eligible_retailers'];

                $product_brands = ['product_brand'];
                foreach ($product_brands as $brands) {
                    if (!empty($data[$brands])) {
                        $term_names = [];

                        foreach ($data[$brands] as $term_name) {
                            $term = get_term_by('slug', $term_name, $brands);

                            if (!$term) {
                                $new_term = wp_insert_term(str_replace('-', ' ', $term_name), $taxonomy);
                                if (!is_wp_error($new_term)) {
                                    $term_names[] = $term_name;
                                }
                            } else {
                                $term_names[] = $term->name;
                            }
                        }

                        if (!empty($term_names)) {
                            wp_set_object_terms($product_id, $term_names, $brands);
                        }
                    }
                }

                foreach ($taxonomies as $taxonomy) {
                    if (!empty($data[$taxonomy])) {
                        $term_names = [];

                        foreach ($data[$taxonomy] as $term_name) {
                            $term = get_term_by('name', $term_name, $taxonomy);

                            if (!$term) {
                                $new_term = wp_insert_term($term_name, $taxonomy);
                                if (!is_wp_error($new_term)) {
                                    $term_names[] = $term_name;
                                }
                            } else {
                                $term_names[] = $term->name;
                            }
                        }

                        if (!empty($term_names)) {
                            wp_set_object_terms($product_id, $term_names, $taxonomy);
                        }
                    }
                }

                foreach ($eligible_retailer as $retailer) {
                    if (!empty($data[$retailer])) {
                        $term_names = [];

                        foreach ($data[$retailer] as $term_name) {
                            $term = get_term_by('id', $term_name, $retailer);

                            if (!$term) {
                                $new_term = wp_insert_term($term_name, $retailer);
                                if (!is_wp_error($new_term)) {
                                    $term_names[] = $term_name;
                                }
                            } else {
                                $term_names[] = $term->name;
                            }
                        }

                        if (!empty($term_names)) {
                            wp_set_object_terms($product_id, $term_names, $retailer);
                        }
                    }
                }

                $success_flag = true;
                // Commented on 20251224
                // $success_message = 'Congratulations! You have successfully created a new Gift Card Plus item. Click <a href="' . esc_url( home_url( '/all-products/' ) ) . '">here</a> to view all gift cards.! Product ID: ' . $product_id;

                if (!empty($data['product_cat'])) {
                    $term_ids = array();

                    foreach ($data['product_cat'] as $category) {
                        // Check if the category is numeric (existing category ID)
                        if (is_numeric($category)) {
                            $term_ids[] = intval($category);
                        } else {
                            // It's a new category name - create it
                            $new_term = wp_insert_term(
                                sanitize_text_field($category),
                                'product_cat'
                            );

                            if (!is_wp_error($new_term)) {
                                $term_ids[] = $new_term['term_id'];
                            } else {
                                // Handle error (possibly category already exists)
                                $existing_term = get_term_by('name', $category, 'product_cat');
                                if ($existing_term) {
                                    $term_ids[] = $existing_term->term_id;
                                }
                            }
                        }
                    }

                    if (!empty($term_ids)) {
                        wp_set_object_terms($product_id, $term_ids, 'product_cat');
                    }
                }
                
                // 1. Define Correct Keys (Matching your Swap Logic)
                $expiry_type_labels = [
                    'no_expiry'                          => 'No Expiry',
                    'gift_set_date'                      => 'Expires on a Set Date',
                    'expiry_period_starts_on_purchase'   => 'Expiry Period Starts on Purchase',   // CHANGED KEY
                    'expiry_period_starts_on_activation' => 'Expiry Period Starts on Activation', // CHANGED KEY
                ];

                $expiry_value = ''; // Initialize variable outside loop

                // 2. Save Gift Card Expiry Type (Save the KEY, not the Label)
                if (!empty($_POST['gift_card_expiry_type'])) {
                    $expiry_value = sanitize_text_field($_POST['gift_card_expiry_type']);
                    
                    // Validate it's a valid key
                    if (array_key_exists($expiry_value, $expiry_type_labels)) {
                        // Save native meta AND ACF field for compatibility
                        update_post_meta($product_id, 'gift_card_expiry_type', $expiry_value);
                        update_field('gift_card_expiry_type', $expiry_value, $product_id);
                    }
                }

                // 3. Save Gift Card Expiry Date (Logic uses the Key)
                if ($expiry_value === 'gift_set_date' && !empty($_POST['gift_card_expiry_date'])) {
                    update_field('gift_card_expiry_date', sanitize_text_field($_POST['gift_card_expiry_date']), $product_id);
                    delete_field('gift_card_expiry_duration', $product_id);
                    delete_field('gift_card_expiry_unit', $product_id);
                }

                // 4. Save Duration/Unit (Logic uses the corrected Keys)
                if (($expiry_value === 'expiry_period_starts_on_purchase' || $expiry_value === 'expiry_period_starts_on_activation') && !empty($_POST['gift_card_expiry_duration']) && !empty($_POST['gift_card_expiry_unit'])) {
                    update_field('gift_card_expiry_duration', intval($_POST['gift_card_expiry_duration']), $product_id);
                    update_field('gift_card_expiry_unit', sanitize_text_field($_POST['gift_card_expiry_unit']), $product_id);
                    delete_field('gift_card_expiry_date', $product_id);
                }

                // Commented on 20260120
                // Save Gift Card Expiry Type
                // if (!empty($_POST['gift_card_expiry_type'])) {
                //     $expiry_value = $_POST['gift_card_expiry_type'];
                //     $formatted_expiry_value = isset($expiry_type_labels[$expiry_value]) ? $expiry_type_labels[$expiry_value] : $expiry_value;
                //     update_field('gift_card_expiry_type', $formatted_expiry_value, $product_id);
                // }

                // Commented on 20260120
                // // Save Gift Card Expiry Date
                // if ($expiry_value === 'gift_set_date' && !empty($_POST['gift_card_expiry_date'])) {
                //     update_field('gift_card_expiry_date', sanitize_text_field($_POST['gift_card_expiry_date']), $product_id);
                //     delete_field('gift_card_expiry_duration', $product_id);
                //     delete_field('gift_card_expiry_unit', $product_id);
                // }

                // Commented on 20260120
                // // Save expiry duration & unit if 'purchase' or 'activation' is selected
                // if (($expiry_value === 'purchase' || $expiry_value === 'activation') && !empty($_POST['gift_card_expiry_duration']) && !empty($_POST['gift_card_expiry_unit'])) {
                //     update_field('gift_card_expiry_duration', intval($_POST['gift_card_expiry_duration']), $product_id);
                //     update_field('gift_card_expiry_unit', sanitize_text_field($_POST['gift_card_expiry_unit']), $product_id);
                //     delete_field('gift_card_expiry_date', $product_id);
                // }

                // Define allowed values (optional, for security)
                $allowed_activation_types = [
                    'no_activation_expiry',
                    'no_activation_needed',
                    'activation_set_date',
                    'set_period',
                ];

                if (!empty($_POST['denomination_type'])) {

                    // Commented on 20251224


                    $denomination_values = $_POST['denomination_type'];

                    // Ensure it's an array
                    $denomination_values = is_array($denomination_values) ? $denomination_values : [$denomination_values];

                    // Define allowed options
                    $allowed_denomination_types = ['variable', 'fixed'];

                    // Sanitize and filter values
                    $sanitized_denomination_values = array_filter(array_map('sanitize_text_field', $denomination_values), function ($value) use ($allowed_denomination_types) {
                        return in_array($value, $allowed_denomination_types);
                    });

                    // Save the field only if we have valid values
                    if (!empty($sanitized_denomination_values)) {
                        update_field('denomination_type', $sanitized_denomination_values, $product_id);
                    }
                }

                // Save Activation Expiry Type
                if (!empty($_POST['activation_expiry_type'])) {
                    $activation_value = sanitize_text_field($_POST['activation_expiry_type']);

                    // Save only if the value is allowed
                    if (in_array($activation_value, $allowed_activation_types)) {
                        update_field('activation_expiry_type', $activation_value, $product_id);
                    }
                }

                // Commented on 20251224
                // Save Activation Expiry Date
                // if (isset($activation_value) && $activation_value === 'activation_set_date' && !empty($_POST['activation_expiry_date'])) {
                //     update_field('activation_expiry_date', sanitize_text_field($_POST['activation_expiry_date']), $product_id);
                // }

                // // Save Activation Expiry Period
                // if (isset($activation_value) && $activation_value === 'set_period' && !empty($_POST['activation_expiry_duration'])) {
                //     update_field('activation_expiry_duration', intval($_POST['activation_expiry_duration']), $product_id);
                // }

                // // Save Activation Expiry Unit
                // if (isset($activation_value) && $activation_value === 'set_period' && !empty($_POST['activation_expiry_unit'])) {
                //     update_field('activation_expiry_unit', sanitize_text_field($_POST['activation_expiry_unit']), $product_id);
                // }


                 // Save Activation Expiry Date
                 if (isset($activation_value) && $activation_value === 'activation_set_date' && !empty($_POST['activation_expiry_date'])) {
                    update_field('activation_expiry_date', sanitize_text_field($_POST['activation_expiry_date']), $product_id);
                    delete_field('activation_expiry_duration', $product_id);
                    delete_field('activation_expiry_unit', $product_id);
                }

                // Save Activation Expiry Period
                if (isset($activation_value) && $activation_value === 'set_period' && !empty($_POST['activation_expiry_duration'])) {
                    update_field('activation_expiry_duration', intval($_POST['activation_expiry_duration']), $product_id);
                    delete_field('activation_expiry_date', $product_id);
                }

                // Save Activation Expiry Unit
                if (isset($activation_value) && $activation_value === 'set_period' && !empty($_POST['activation_expiry_unit'])) {
                    update_field('activation_expiry_unit', sanitize_text_field($_POST['activation_expiry_unit']), $product_id);
                    delete_field('activation_expiry_date', $product_id);
                }

                
            } else {
                $error_message = 'Failed to create product. Please try again.';
            }

            // ===========================================

            // Process product images
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $product_images = [];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            $cover_image = '';
            $cover_image_link = true;
            $cover_image_existing = false;
            
            if (isset($_POST['cover-img']) && strpos($_POST['cover-img'], 'blob:') !== false) {
                $cover_image_link = false;
                $cover_image_existing = false;
            }

            //pr('1111');
            if( isset($_POST['cover-img']) && (int)$_POST['cover-img'] > 0 ){
                //pr('2222');
                $image_post = get_post($_POST['cover-img']);
                if ($image_post && $image_post->post_type === 'attachment') {
                    //pr('333');
                    $cover_image_link = false;
                    $cover_image_existing = true;
                }
            }

            $temp_array2 = [];

            $index3 = 0;
            if( !empty($_POST['new_existingImages']) ){
                foreach ($_POST['new_existingImages'] as $image_ID) {
                    if( $index3 == 0 && $cover_image_existing ){
                        $cover_image = $image_ID;
                        $index3++;
                    }else{
                        $product_images[] = $image_ID;
                    }
                }
            }

            // Handle uploaded image files
            $index = 0;
            if (!empty($_FILES['product_images']['name'][0])) {
                foreach ($_FILES['product_images']['name'] as $key => $image_name) {
                    if (!empty($image_name)) {

                        if (in_array($_FILES['product_images']['name'][$key], $temp_array2)) {
                            continue;
                        }
                        
                        $temp_array2[] = $_FILES['product_images']['name'][$key];
                        
                        $file = [
                            'name' => $_FILES['product_images']['name'][$key],
                            'type' => $_FILES['product_images']['type'][$key],
                            'tmp_name' => $_FILES['product_images']['tmp_name'][$key],
                            'error' => $_FILES['product_images']['error'][$key],
                            'size' => $_FILES['product_images']['size'][$key],
                        ];

                        // Validate file
                        if ($file['error'] !== UPLOAD_ERR_OK) {
                            continue;
                        }

                        if (!in_array($file['type'], $allowed_mime) || $file['size'] > $max_size) {
                            continue;
                        }

                        // Validate image content
                        try {
                            $image_info = getimagesize($file['tmp_name']);
                            if (!$image_info || !in_array($image_info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) {
                                throw new Exception('Invalid image file');
                            }
                        } catch (Exception $e) {
                            continue;
                        }

                        // Handle EXIF data quietly
                        if (function_exists('exif_read_data') && $file['type'] === 'image/jpeg') {
                            try {
                                // Suppress EXIF warnings
                                $exif = @exif_read_data($file['tmp_name']);
                            } catch (Exception $ex) {
                            }
                        }

                        $check_image = get_existing_attachment_id_by_name($file['name']);

                        if(empty($check_image)){
                            // Handle upload with error suppression
                            $uploaded_file = @media_handle_sideload($file, 0);
                        } else {
                            // If image already exists, use the existing attachment ID
                            $uploaded_file = $check_image;
                        }

                        if (!is_wp_error($uploaded_file)) {
                            if( $index == 0 && !$cover_image_link && !$cover_image_existing ){
                                $cover_image = $uploaded_file;
                                $index++;
                            } else if (!is_wp_error($uploaded_file)) {
                                $product_images[] = $uploaded_file;
                            }
                        } else {
                        }
                    }
                }
            }else{
                $cover_image_link = true;
            }

            // Handle product_image_links (image URLs)
            $index2 = 0;
            if (!empty($_POST['product_image_links'])) {
                // Initialize an array to hold the attachment IDs for the gallery
                /*$product_images = get_post_meta($product_id, '_product_image_gallery', true);
                if (!is_array($product_images)) {
                    $product_images = [];
                }*/
            
                foreach ($_POST['product_image_links'] as $image_url) {
                    $image_url = esc_url(trim($image_url));
            
                    // Skip if the URL is invalid
                    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                        continue;
                    }
            
                    // 1. Get the filename from the URL
                    $file_name = basename($image_url);
                    
                    // 2. Check if an image with this filename already exists
                    $attachment_id = get_existing_attachment_id_by_name($file_name);
            
                    // 3. If it does NOT exist, download and upload it
                    if (!$attachment_id) {
                        $file_array = array();
                        $file_array['name'] = $file_name;
                        $file_array['tmp_name'] = download_url($image_url);
            
                        if (is_wp_error($file_array['tmp_name'])) {
                            $error_message = $file_array['tmp_name']->get_error_message();
                        } else {
                            // Sideload the image into the media library
                            $attachment_id = media_handle_sideload($file_array, $product_id);
            
                            if (is_wp_error($attachment_id)) {
                                $error_message = $attachment_id->get_error_message();
                            } else {
                                // Append to gallery
                                if( $index2 == 0 && $cover_image_link && !$cover_image_existing ){
                                    $cover_image = $attachment_id;
                                    $index2++;
                                } else{
                                    $product_images[] = $attachment_id;
                                }
                            }
                        }
                    } else {
                        // Optional: Log that an existing image was found and used
                    }

            
                    // 4. If we have a valid ID (either new or existing), add it to the gallery
                    if ($attachment_id && !is_wp_error($attachment_id) && !in_array($attachment_id, $product_images)) {
                        if( $index2 == 0 && $cover_image_link && !$cover_image_existing ){
                            $cover_image = $attachment_id;
                            $index2++;
                        } else{
                            $product_images[] = $attachment_id;
                        }
                    }
                }
                
                // After the loop, you would typically update the product gallery meta field
                // update_post_meta($product_id, '_product_image_gallery', implode(',', $product_images));
            }

            $index3 = 0;
            if( !empty($_POST['new_existingImages']) ){
                foreach ($_POST['new_existingImages'] as $image_ID) {
                    if( $index3 == 0 && $cover_image_existing ){
                        $cover_image = $image_ID;
                        break;
                    }
                }
            }

            if( empty($cover_image) ){
                $cover_image = $product_images[0];
                unset($product_images[0]);
            }

            // Ensure images are correctly saved to the gallery
            if (!empty($product_images)) {
                $existing_gallery = get_post_meta($product_id, '_product_image_gallery', true);
                $existing_gallery = !empty($existing_gallery) ? explode(',', $existing_gallery) : [];
                //$product_images = array_merge($existing_gallery, $product_images);
                $product_images = array_unique(array_map('intval', $product_images)); // Filter duplicates
                update_post_meta($product_id, '_product_image_gallery', implode(',', $product_images));

            }

            if( !empty($cover_image) ){
                // Set as product featured image
                set_post_thumbnail($product_id, $cover_image);
            }

            // ===========================================

            // Process brand logo
            // Process brand logo upload
            if (!empty($_POST['brand_thumbnail_url']) && empty($_FILES['brand_logo']['name'])) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $image_url = esc_url_raw($_POST['brand_thumbnail_url']);
                $file_name = basename($image_url);

                // Check if image already exists in media library
                //$existing_id = attachment_url_to_postid($image_url);
                $existing_id = get_existing_attachment_id_by_name($file_name);

                if ($existing_id) {
                    $uploaded_file_id = $existing_id;
                } else {
                    // Download the image to a temp file
                    $tmp = download_url($image_url);

                    if (is_wp_error($tmp)) {
                        return;
                    }

                    $file_array = [
                        'name' => basename($image_url),
                        'type' => mime_content_type($tmp),
                        'tmp_name' => $tmp,
                        'error' => 0,
                        'size' => filesize($tmp),
                    ];

                    $overrides = ['test_form' => false];
                    $uploaded_file_id = media_handle_sideload($file_array, $product_id);

                    // Clean up temp file if upload failed
                    if (is_wp_error($uploaded_file_id)) {
                        @unlink($tmp);
                        return;
                    }
                }

                // Set as product featured image
                //set_post_thumbnail($product_id, $uploaded_file_id);

                // Save in product meta
                $product->update_meta_data('brand_logo', $uploaded_file_id);

                // Assign to brand if selected
                if (!empty($_POST['product_brand'][0])) {
                    $brand_slug = sanitize_text_field($_POST['product_brand'][0]);
                    $term = get_term_by('slug', $brand_slug, 'product_brand');

                    if ($term && !is_wp_error($term)) {
                        update_term_meta($term->term_id, 'thumbnail_id', $uploaded_file_id);
                        wp_set_object_terms($product_id, (int) $term->term_id, 'product_brand', false);
                    }
                }
            } else if (!empty($_FILES['brand_logo']['name'])) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $file = $_FILES['brand_logo'];

                $check_image = get_existing_attachment_id_by_name($file['name']);

                if(empty($check_image)){
                    $uploaded_file_id = media_handle_upload('brand_logo', $product_id);
                } else {
                    // If image already exists, use the existing attachment ID
                    $uploaded_file_id = $check_image;
                }

                if (is_wp_error($uploaded_file_id)) {
                    return;
                }

                // Set as product featured image
                //set_post_thumbnail($product_id, $uploaded_file_id);

                // Save in product meta
                $product->update_meta_data('brand_logo', $uploaded_file_id);

                if (!empty($_POST['product_brand'][0])) {
                    $brand_slug = sanitize_text_field($_POST['product_brand'][0]);
                    $term = get_term_by('slug', $brand_slug, 'product_brand');

                    if ($term && !is_wp_error($term)) {
                        update_term_meta($term->term_id, 'thumbnail_id', $uploaded_file_id);
                        wp_set_object_terms($product_id, (int) $term->term_id, 'product_brand', false);
                    }
                }
            }


            // Optional: Process image from URL
            if (!empty($_POST['brand_thumbnail_url'])) {
                $image_url = esc_url_raw($_POST['brand_thumbnail_url']);
                $file_name = basename(parse_url($image_url, PHP_URL_PATH));
                $attach_id = false; // Initialize the variable
            
                // 1. Check if the image already exists using your function
                $attach_id = get_existing_attachment_id_by_name($file_name);
            
                // 2. If the image was NOT found, then download and upload it
                if (!$attach_id) {
                    // Download image data using cURL
                    $ch = curl_init($image_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    $image_data = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
            
                    // Proceed only if the download was successful
                    if ($image_data && $http_code === 200) {
                        $upload_dir = wp_upload_dir();
                        $file_path = $upload_dir['path'] . '/' . $file_name;
                        file_put_contents($file_path, $image_data);
            
                        // Prepare attachment data for the media library
                        $file_type = wp_check_filetype($file_name, null);
                        $attachment = [
                            'post_mime_type' => $file_type['type'],
                            'post_title'     => sanitize_file_name($file_name),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        ];
            
                        // Insert the attachment into the media library
                        $new_attach_id = wp_insert_attachment($attachment, $file_path, $product_id);
            
                        if (!is_wp_error($new_attach_id)) {
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $attach_data = wp_generate_attachment_metadata($new_attach_id, $file_path);
                            wp_update_attachment_metadata($new_attach_id, $attach_data);
                            
                            // Set our main variable to the ID of the newly created attachment
                            $attach_id = $new_attach_id;
                        }
                    }
                }
            
                // 3. If we have a valid ID (either existing or new), assign it to the product.
                if ($attach_id && !is_wp_error($attach_id)) {
                    // Set as the product's featured image
                    //set_post_thumbnail($product_id, $attach_id);
            
                    // Save the attachment ID in a custom meta field
                    $product->update_meta_data('brand_logo', $attach_id);
                }
            }

            if (!empty($data['sku_type']) && $data['sku_type'] == 'Child') {
                $existing_product_id = wc_get_product_id_by_sku($data['parent_sku']);
                update_field('parent_sku', $existing_product_id ?: '', $product_id);
                update_post_meta($product_id, 'parent_sku', $data['parent_sku']);
                update_post_meta($product_id, 'sku_type', $data['sku_type']);
            }else if( !empty($data['sku_type']) && $data['sku_type'] != 'Child'  ){
                update_field('parent_sku', '');
                update_post_meta($product_id, 'parent_sku', '');
                update_post_meta($product_id, 'sku_type', $data['sku_type']);
            }

            $temp_redirect_flag = 1;

        }

        $form_stop = false;

        $onsite_from = $_POST['_onsite_from'];
        $onsite_to = $_POST['_onsite_to'];

        $onsite_from_ts = strtotime($onsite_from);
        $onsite_to_ts = strtotime($onsite_to);
        $current_timestamp = current_time('timestamp');
       
        $product_status = isset($_POST['product_status']) 
            ? sanitize_text_field($_POST['product_status']) 
            : 'draft';

        wp_update_post([
            'ID' => $product_id,
            'post_status' => $product_status,
        ]);

        if ($product_status !== 'publish') {

            // ✅ Only cleanup
            clear_schedule_event('activate_product_on_onsite', $product_id);
            clear_schedule_event('deactivate_product_on_onsite', $product_id);

        } else {

            // ✅ ALL your existing logic must go inside this block

            $always_on = !empty($_POST['always_on']);

            if ($always_on) {

                wp_update_post([
                    'ID' => $product_id,
                    'post_status' => 'publish',
                ]);

            } else if (!empty($onsite_from) || !empty($onsite_to)) {

                $post_status = 'draft';

                if ($onsite_from_ts <= $current_timestamp && $onsite_to_ts >= $current_timestamp) {
                    $post_status = 'publish';
                }

                if ($onsite_from_ts > $current_timestamp) {
                    $post_status = 'draft';
                }

                if ($onsite_to_ts < $current_timestamp) {
                    $post_status = 'wc-deactivated';
                }

                wp_update_post([
                    'ID' => $product_id,
                    'post_status' => $post_status,
                ]);

                clear_schedule_event('activate_product_on_onsite', $product_id);
                clear_schedule_event('deactivate_product_on_onsite', $product_id);

                if ($onsite_from_ts > $current_timestamp) {
                    wp_schedule_single_event($onsite_from_ts, 'activate_product_on_onsite', [$product_id]);
                }

                if ($onsite_to_ts > $current_timestamp) {
                    wp_schedule_single_event($onsite_to_ts, 'deactivate_product_on_onsite', [$product_id]);
                }

            } else {

                // ✅ only publish if already ACTIVE
                wp_update_post([
                    'ID' => $product_id,
                    'post_status' => 'publish',
                ]);
            }
        }


        $discount_valid_from = isset( $_POST['_discount_valid_from'] ) ? sanitize_text_field( $_POST['_discount_valid_from'] ) : '';
        $discount_valid_to = isset( $_POST['_discount_valid_to'] ) ? sanitize_text_field( $_POST['_discount_valid_to'] ) : '';

        $timezone = new DateTimeZone( wc_timezone_string() ); // Store timezone

        $discount_valid_from_ts = 0;
        $discount_valid_to_ts = 0;

        if ( ! empty( $discount_valid_from ) ) {
            $dt_from = new DateTime( $discount_valid_from, $timezone );
            $dt_from->setTimezone( new DateTimeZone( 'UTC' ) );
            $discount_valid_from_ts = $dt_from->getTimestamp();
        }

        if ( ! empty( $discount_valid_to ) ) {
            $dt_to = new DateTime( $discount_valid_to, $timezone );
            $dt_to->setTimezone( new DateTimeZone( 'UTC' ) );
            $discount_valid_to_ts = $dt_to->getTimestamp();
        }

        $discount_flag = false;
        if( isset($_POST['discounted_price_checkbox']) && $_POST['discounted_price_checkbox'] ){
            if( isset($_POST['discounted_price']) && !empty($_POST['discounted_price']) ){
                $discounted_price = $_POST['discounted_price'];
                if( !empty($discount_valid_from) || !empty($discount_valid_to) ){
                    if( $discount_valid_from_ts <= $current_timestamp && $discount_valid_to_ts > $current_timestamp ){
                        update_field('discounted_price_checkbox', 'Yes', $product_id);
                        update_post_meta( $product_id, '_sale_price', $discounted_price );
                        update_post_meta( $product_id, '_sale_price_dates_from', $discount_valid_from_ts );
                        update_post_meta( $product_id, '_sale_price_dates_to', $discount_valid_to_ts );
                    }else if( $discount_valid_from_ts > $current_timestamp ){
                        update_field('discounted_price_checkbox', 'No', $product_id);
                        delete_post_meta( $product_id, '_sale_price' );
                        delete_post_meta( $product_id, '_sale_price_dates_from' );
                        delete_post_meta( $product_id, '_sale_price_dates_to' );
                        clear_schedule_event('activate_product_on_SALE', $product_id);
                        wp_schedule_single_event($discount_valid_from_ts, 'activate_product_on_SALE', array($product_id));
                    }

                    if( $discount_valid_to_ts <= $current_timestamp ){
                        update_field('discounted_price_checkbox', 'No', $product_id);
                        delete_post_meta( $product_id, '_sale_price' );
                        delete_post_meta( $product_id, '_sale_price_dates_from' );
                        delete_post_meta( $product_id, '_sale_price_dates_to' );
                    }else if( $discount_valid_to_ts > $current_timestamp ){
                        clear_schedule_event('deactivate_product_on_SALE', $product_id);
                        wp_schedule_single_event($discount_valid_to_ts, 'deactivate_product_on_SALE', array($product_id));
                    }
                }else{
                    update_field('discounted_price_checkbox', 'Yes', $product_id);
                    update_post_meta( $product_id, '_sale_price', $discounted_price );
                    delete_post_meta( $product_id, '_sale_price_dates_from' );
                    delete_post_meta( $product_id, '_sale_price_dates_to' );
                }
            }else{
                update_field('discounted_price_checkbox', 'No', $product_id);
                delete_post_meta( $product_id, '_sale_price' );
                delete_post_meta( $product_id, '_sale_price_dates_from' );
                delete_post_meta( $product_id, '_sale_price_dates_to' );
                clear_schedule_event('activate_product_on_SALE', $product_id);
            }
        }else{
            update_field('discounted_price_checkbox', 'No', $product_id);
            delete_post_meta( $product_id, '_sale_price' );
            delete_post_meta( $product_id, '_sale_price_dates_from' );
            delete_post_meta( $product_id, '_sale_price_dates_to' );
            clear_schedule_event('activate_product_on_SALE', $product_id);
        }

        // Commented on 20251224
        /*if( !$discount_flag ){
            update_field('discounted_price_checkbox', 'No', $product_id);
            delete_post_meta( $product_id, '_sale_price' );
            delete_post_meta( $product_id, '_sale_price_dates_from' );
            delete_post_meta( $product_id, '_sale_price_dates_to' );
            clear_schedule_event('activate_product_on_SALE', $product_id);
        }*/


        if( $temp_redirect_flag > 0 ){
            echo "<script>
                    /*setTimeout(function() {
                        window.location.href = '<?php echo esc_url(home_url('/review-a-product/')); ?>';
                    }, 3000);*/
                </script>";
        }
    }
    if ($product_id) {

        if ($is_new_product) {
            $success_message = 'Congratulations! You have successfully created a new Gift Card Plus item. Click <a href="' . esc_url( home_url( '/all-products/' ) ) . '">here</a> to view all gift cards.!';
        } else {
            $success_message = 'Product updated successfully!';
        }

        // Set the success flag and message
        $success_flag = true;

        // Commented on 20251224
        // echo "<div class='success-message'>$success_message</div>";
        // echo '<div class="success-message">' . esc_html($success_message) . '</div>';

        echo "<script>
            // Clear standard form fields
            document.getElementById('gift-card-form')?.reset();
    
            // Clear rich text editor content
            document.querySelectorAll('.rich-textarea').forEach(editor => {
                editor.innerHTML = '';
            });
    
            // Clear associated hidden inputs
            document.querySelectorAll('input[type=\"hidden\"]').forEach(input => {
                if (input.id.endsWith('_input')) {
                    input.value = '';
                }
            });
    
            // Optional: Scroll to top to show the success message
            window.scrollTo({ top: 0, behavior: 'smooth' });
    
            // Reload the page after 2 seconds
            setTimeout(function() {
                window.location.href = window.location.href;
            }, 2000);
        </script>";

        // Commented on 20251224
        // if ($is_new_product) {
        //     $success_message = 'Congratulations! You have successfully created a new Gift Card Plus item. Click <a href="' . esc_url( home_url( '/all-products/' ) ) . '">here</a> to view all gift cards.!';
        //     echo "<script>
        //         console.log('im working as Create mode');
        //         document.getElementById('gift-card-form')?.reset();
        //         document.querySelectorAll('.rich-textarea').forEach(editor => {
        //             editor.innerHTML = '';
        //         });
        //         document.querySelectorAll('input[type=\"hidden\"]').forEach(input => {
        //             if (input.id.endsWith('_input')) {
        //                 input.value = '';
        //             }
        //         });
        //         window.scrollTo({ top: 0, behavior: 'smooth' });
        //     </script>";
        // } else {
        //     $success_message = 'Product updated successfully!';
        //     // In edit mode, just scroll to top (optional) but don’t reset the form
        //     echo "<script>
        //     console.log('im working as Edit mode');
        //         window.scrollTo({ top: 0, behavior: 'smooth' });
        //     </script>";
        // }

        // Commented on 20251224
        // if ($is_new_product) {
        //     $success_message = 'Congratulations! You have successfully created a new Gift Card Plus item. Click <a href="' . esc_url( home_url( '/all-products/' ) ) . '">here</a> to view all gift cards.!';
        // } else {
        //     $success_message = 'Product updated successfully!';
        // }

        // Set the success flag and message
        $success_flag = true;

        // Commented on 20251224
        // echo "<div class='success-message'>$success_message</div>";
        // echo '<div class="success-message">' . esc_html($success_message) . '</div>';
        // echo '<script>
        //         document.addEventListener("DOMContentLoaded", function() {
        //             var form = document.getElementById("gift-card-form");
        //             if (form) {
        //                 form.reset();
        //             }
        //         });
        //     </script>';


        // Commented on 20251224
        // echo "<script>
        //     // Clear standard form fields
        //     document.getElementById('gift-card-form')?.reset();
    
        //     // Clear rich text editor content
        //     document.querySelectorAll('.rich-textarea').forEach(editor => {
        //         editor.innerHTML = '';
        //     });
    
        //     // Clear associated hidden inputs
        //     document.querySelectorAll('input[type=\"hidden\"]').forEach(input => {
        //         if (input.id.endsWith('_input')) {
        //             input.value = '';
        //         }
        //     });
    
        //     // Optional: Scroll to top to show the success message
        //     window.scrollTo({ top: 0, behavior: 'smooth' });
    
        //     // Reload the page after 2 seconds
        //     /*setTimeout(function() {
        //         window.location.href = window.location.href;
        //     }, 2000);*/
        // </script>";
    }
}

//pr($product_data);
$gc_form_status = '';
if( $update_product_flag ){
    $gc_form_status = ' style="display: none;"';
}
?>

<div class="gift-card-form-container <?php echo ($edit_mode) ? 'edit_mode' : 'create_mode'; ?>">
    <form id="gift-card-form" method="post" enctype="multipart/form-data" onsubmit="return validateAll();">
        <?php if ($edit_mode): ?>
            <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
        <?php endif; ?>
        <?php if (!empty($success_flag)): ?>
            <div id="success-message" style="display: block; color: green; font-weight: bold; margin-bottom: 15px;">
                <?php echo $edit_mode ? 'Product Updated Successfully!' : 'Congratulations! You have successfully created a new Gift Card Plus item. Click <a href="' . esc_url( home_url( '/all-products/' ) ) . '">here</a> to view all gift cards.!'; ?>
            </div>
            <?php
                // Commented on 20251224
                // if ($edit_mode && !empty($product_id)) {
                //     $redirect_url = home_url('/create-product/?edit_product=' . intval($product_id));
                // } else {
                //     $redirect_url = home_url('/create-product');
                // }
                // echo '<meta http-equiv="refresh" content="1;url=' . esc_url($redirect_url) . '">';
            ?>
        <?php endif; ?>

        <input type="hidden" id="form-submitted" value="<?php echo !empty($success_flag) ? '1' : ''; ?>">

        <div class="form-indicator form-steps"<?=$gc_form_status;?>>
            <div class="step-container">
                <div class="step active" data-step="1">1</div>
                <div class="line"></div>
            </div>
            <div class="step-container">
                <div class="step" data-step="2">2</div>
                <div class="line"></div>
            </div>
            <div class="step-container">
                <div class="step" data-step="3">3</div>
            </div>
        </div>
        <div class="top-form-title"<?=$gc_form_status;?>>
            <nav class="woocommerce-breadcrumb create-product" aria-label="Breadcrumb">
               <a href="<?php echo esc_url( wp_get_referer() ? wp_get_referer() : home_url('/') ); ?>" class="breacrum-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect x="24" width="24" height="24" rx="12" transform="rotate(90 24 0)" fill="white"></rect>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M5.45703 12.0012C5.45703 11.8083 5.53367 11.6233 5.67007 11.4869C5.80647 11.3505 5.99147 11.2738 6.18438 11.2738L19.2765 11.2738C19.4694 11.2738 19.6544 11.3505 19.7908 11.4869C19.9272 11.6233 20.0039 12.0012 20.0039 12.0012C20.0039 12.1941 19.9272 12.3791 19.7908 12.5155C19.6544 12.6519 19.4694 12.7285 19.2765 12.7285L6.18438 12.7285C5.99147 12.7285 5.80647 12.6519 5.67007 12.5155C5.53367 12.3791 5.45703 12.1941 5.45703 12.0012Z" fill="black" fill-opacity="0.6"></path>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M4.21369 12.5154C4.14595 12.4479 4.09221 12.3676 4.05554 12.2792C4.01888 12.1909 4 12.0961 4 12.0005C4 11.9048 4.01888 11.8101 4.05554 11.7217C4.09221 11.6333 4.14595 11.5531 4.21369 11.4855L8.57773 7.12146C8.71431 6.98488 8.89955 6.90815 9.09269 6.90815C9.28584 6.90815 9.47107 6.98488 9.60765 7.12145C9.74423 7.25803 9.82095 7.44327 9.82095 7.63641C9.82095 7.82956 9.74423 8.0148 9.60765 8.15137L5.75711 12.0005L9.60765 15.8496C9.74423 15.9861 9.82096 16.1714 9.82096 16.3645C9.82096 16.5577 9.74423 16.7429 9.60765 16.8795C9.47108 17.016 9.28584 17.0928 9.09269 17.0928C8.89955 17.0928 8.71431 17.016 8.57774 16.8795L4.21369 12.5154Z" fill="black" fill-opacity="0.6"></path>
                    </svg>
                </a> &nbsp <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span class="breadcrumb-separator">
                <svg width="21" height="24" viewBox="0 0 21 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7.9502 13.4746C7.9502 14.1764 8.20085 14.778 8.70215 15.2793C9.20345 15.776 9.80501 16.0244 10.5068 16.0244C11.2087 16.0244 11.8102 15.776 12.3115 15.2793C12.8083 14.778 13.0566 14.1764 13.0566 13.4746C13.0566 12.7728 12.8083 12.1735 12.3115 11.6768C11.8102 11.1755 11.2087 10.9248 10.5068 10.9248C9.80501 10.9248 9.20345 11.1755 8.70215 11.6768C8.20085 12.1735 7.9502 12.7728 7.9502 13.4746Z" fill="black" fill-opacity="0.6"></path>
                </svg>
            </span><a href="<?php echo esc_url(home_url('/all-products/')); ?>">All Gift Cards</a><span class="breadcrumb-separator">
                <svg width="21" height="24" viewBox="0 0 21 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7.9502 13.4746C7.9502 14.1764 8.20085 14.778 8.70215 15.2793C9.20345 15.776 9.80501 16.0244 10.5068 16.0244C11.2087 16.0244 11.8102 15.776 12.3115 15.2793C12.8083 14.778 13.0566 14.1764 13.0566 13.4746C13.0566 12.7728 12.8083 12.1735 12.3115 11.6768C11.8102 11.1755 11.2087 10.9248 10.5068 10.9248C9.80501 10.9248 9.20345 11.1755 8.70215 11.6768C8.20085 12.1735 7.9502 12.7728 7.9502 13.4746Z" fill="black" fill-opacity="0.6"></path>
                </svg>
            </span><?php echo $edit_mode ? 'Review a Product : ' . esc_html($product_data['gift_card_title']) . ' - ' . esc_html($product_data['sku']) : 'Create a Gift Card Product'; ?>
            </nav>
            <!-- <h2><?php //echo $edit_mode ? '<strong>Review a Product </strong> : ' . $product_data['gift_card_title'] . ' - ' . $product_data['sku'] : 'Create a Gift Card Product'; ?></h2> -->

            <div class="actions">
                <?php if (!$edit_mode): ?>
                    <button type="button" name="save_step" class="btn-black-white btn-black-white btn-primary-white btn btn-outline save_step">Save and Exit</button>
                <?php endif; ?>
            </div>
        </div>

        <?php ?>
        <div class="form-steps-container"<?=$gc_form_status;?>>
            <div class="form-step step-1" style="display: block;">
                <div class="form-group">
                    <div class="control-wrapper">
                        <label class="label" for="gift_card_image">Gift Card Image<span class="validate">*</span></label>
                        <div class="upload-container">

                            <div class="preview-grid">
                                <?php if ($edit_mode && (!empty($product_data['product_images']) || !empty($product_data['image_id']))): ?>
                                    <div id="preview-container" class="preview-container is-edit-mode">
                                        <?php if (isset($product_data['image_id']) && (int)$product_data['image_id'] > 0){ ?>
                                            <div class="preview-item" data-image-id="<?php echo esc_attr($product_data['image_id']); ?>">
                                                <div class="cover-label">Cover Image</div>
                                                <input type="hidden" name="cover-img" id="cover-img" value="<?=$product_data['image_id'];?>">
                                                <img class="preview-image" src="<?=wp_get_attachment_image_url($product_data['image_id'], 'full');?>" data-image-id="<?php echo esc_attr($product_data['image_id']); ?>">
                                                <input type="hidden" name="pg-img" value="<?=$product_data['image_id'];?>">
                                            </div>
                                        <?php } ?>
                                        <?php if ( !empty($product_data['product_images']) ){ ?>
                                            <?php foreach ($product_data['product_images'] as $index => $image_id): ?>
                                                <div class="preview-item" data-image-id="<?php echo esc_attr($image_id); ?>">
                                                    <img class="preview-image" src="<?=wp_get_attachment_image_url($image_id, 'full');?>" data-image-id="<?php echo esc_attr($image_id); ?>">
                                                    <input type="hidden" name="pg-img" value="<?=$image_id;?>">
                                                </div>
                                            <?php endforeach; ?>
                                        <?php } ?>
                                    </div>
                                <?php else: ?>
                                    <div id="preview-container" class="preview-container"></div>
                                <?php endif; ?>

                                <div id="upload-area" class="upload-area">
                                    <span id="upload-btn" class="upload-btn">Upload,</span>
                                    <span id="file-browse" class="file-browse">Link</span> or drag and drop
                                    <p>SVG, PNG, JPG, or GIF (max. 3MB)</p>
                                    <input id="file-input" name="product_images[]" type="file" multiple hidden
                                        accept="image/png, image/jpeg, image/gif, image/svg+xml" />
                                </div>
                            </div>

                            <p class="note">Please ensure your image is 600x379 pixels or less.</p>

                            <div id="link-input-area" class="link-input-area"
                                style="display: none; flex-direction:column;">
                                <input type="text" id="image-link" placeholder="Enter image URL" />
                                <div class="actions">
                                    <button class="btn btn-blue" id="add-link" type="button">Add</button>
                                    <button class="btn btn-blue" id="cancel-link"
                                        type="button">Cancel</button>
                                </div>
                            </div>


                            <div id="required-image-error" style="color:red; font-size:13px; display:none;">
                                Please upload an image or provide a link.
                            </div>
                            <div id="upload-error" style=" margin-top: 10px;"></div>
                        </div>
                    </div>
                    <div class="control-wrapper buyer-upload-checkbox">
                        <div class="form-check checkbox">
                            <?php $buyer_upload_value = $product_data['buyer_upload'] ?? 'Yes'; ?>
                            <input type="checkbox" id="buyer-upload" name="buyer_upload" value="Yes" <?php checked($buyer_upload_value, 'Yes'); ?> />
                            <label for="buyer-upload">Buyer allowed to upload</label>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="parent_sku_field">
                    <div class="control-wrapper" id="parent_sku_field_wrapper"
                        style="<?php echo (!empty($product_data['parent_sku']) && $product_data['sku_type'] == 'Child') ? 'display: block;' : 'display: none;'; ?>">
                        <label class="label" for="parent_sku">Parent SKU</label>
                        <input class="form-control" type="text" id="parent_sku" name="parent_sku" class="select2"
                            style="width: 100%;" placeholder="Start typing to search or create Parent SKU"
                            value="<?php echo esc_attr($product_data['parent_sku'] ?? ''); ?>" required/>
                    </div>
                    <div class="control-wrapper form-check checkbox mt-4" id="auto_populate_field_wrapper"
                        style="<?php echo (!empty($product_data['parent_sku']) && $product_data['sku_type'] == 'Child') ? 'display: block;' : 'display: none;'; ?>">
                        <input id="auto_populate_from_parent_sku" type="checkbox" name="auto_populate" <?php checked($product_data['auto_populate'] ?? '', 'on'); ?>>
                        <label for="auto_populate_from_parent_sku">
                            Auto Populate From Parent SKU
                            <span class="tooltip-icon">
                                <i class="fas fa-info-circle"></i>
                                <span class="tooltip-text">
                                    Tick to pre-fill fields from the Parent SKU, including Short and Long Description,
                                    T&Cs,
                                    How To
                                    Use and Expiry Date/Time.
                                </span>
                            </span>
                        </label>

                    </div>
                </div>

                <div class="form-group">
                    <div class="control-wrapper">
                        <label class="label" for="sku_type">SKU Type<span class="validate">*</span></label>
                        <div id="parent_sku_container">
                            <div class="form-check radio">
                                <input type="radio" id="sku_parent" name="sku_type" value="Parent" <?php checked($product_data['sku_type'] ?? '', 'Parent'); ?> required>
                                <label for="sku_parent">Parent</label>
                            </div>
                        </div>
                    </div>

                    <div class="control-wrapper" id="child_sku_container">
                        <div class="form-check radio">
                            <input type="radio" id="sku_child" name="sku_type" value="Child" <?php checked($product_data['sku_type'] ?? '', 'Child'); ?>>
                            <label for="sku_child">Child</label>
                        </div>
                    </div>

                    <div class="control-wrapper" id="individual_sku_container">
                        <div class="form-check radio">
                            <input type="radio" id="sku_individual" name="sku_type" value="Individual" <?php checked($product_data['sku_type'] ?? '', 'Individual'); ?>>
                            <label for="sku_individual">Individual</label>
                        </div>
                    </div>
                </div>
                <script>
                    // Commented on 20251224
                    // const nextBtn = document.getElementsByClassName('next-step')[0];
                    // nextBtn.addEventListener('click', () => {
                    //     const radios = document.querySelectorAll('input[name="sku_type"]');
                    //     const checked = Array.from(radios).some(r => r.checked);
                    //     console.log('Is any sku_type checked?', checked);
                    //     if (checked) {
                    //         alert('Valid! Moving to next step.');
                    //         // simulate navigation here
                    //     } else {
                    //         alert('Please select SKU Type');
                    //     }
                    // });            
                </script>


                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6">
                        <label class="label" for="sku">SKU<span class="validate">*</span></label>
                        <input class="form-control" type="text" id="sku" name="sku"
                            value="<?php echo esc_attr($product_data['sku'] ?? ''); ?>" required>
                        <div id="sku_validation_error" class="error-field"
                            style="color:red; font-size: 15px; display: none;">Only letters, numbers, '-','.' and
                            underscores '_' allowed. No spaces or other special characters.</div>
                        <div id="sku_error" class="error-field" style=" display: none;">SKU already exists.</div>
                    </div>
                    <div class="control-wrapper col col-6">
                        <label class="label" for="supplier_sku">Supplier SKU<span class="validate">*</span></label>
                        <input class="form-control" type="text" id="supplier_sku" name="_supplier_sku"
                            value="<?php echo esc_attr($product_data['_supplier_sku'] ?? ''); ?>" required>
                        <div id="supplier_sku_error" class="error-field"
                            style="color:red; font-size: 15px; display: none;">Only letters, numbers, '-','.' and
                            underscores '_' allowed. No spaces or other special characters.</div>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col">
                        <label class="label" for="gift_card_title">Gift Card Title<span class="validate">*</span></label>
                        <input class="form-control" type="text" name="gift_card_title" id="gift_card_title"
                            value="<?php echo esc_attr($product_data['gift_card_title'] ?? ''); ?>" required>

                    </div>
                </div>
                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6">
                        <?php display_brands_dropdown($product_data['product_brand'] ?? ''); ?>
                    </div>
                    <div class="control-wrapper col col-6">
                        <label class="label" for="supplier">Supplier<span class="validate">*</span></label>
                        <select id="supplier-dropdown" name="supplier" required>
                            <option value="">Select Supplier</option>
                            <?php
                            $user_query = new WP_User_Query(array('role' => 'supplier'));

                            // Get the selected supplier - check both ACF field and direct meta
                            $selected_supplier = isset($product_data['supplier']) ? $product_data['supplier'] :
                                (isset($product_data['_supplier_id']) ? $product_data['_supplier_id'] : '');

                            // Ensure it's an integer for comparison
                            $selected_supplier = intval($selected_supplier);

                            if (!empty($user_query->get_results())) {
                                foreach ($user_query->get_results() as $user) {
                                    $selected = selected($user->ID, $selected_supplier, false);
                                    echo sprintf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($user->ID),
                                        $selected,
                                        esc_html($user->display_name)
                                    );
                                }
                            } else {
                                echo '<option value="">No suppliers found</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>


                <div class="form-group flex-row short_description">
                    <div class="control-wrapper col">
                        <div class="editable-label">
                            <label class="label" for="short_description" contenteditable="false">Short
                                Description</label>
                            <span class="edit-icon icon icon-edit"></span>
                        </div>

                        <div class="text-rich-editor">
                            <input type="hidden" name="label_short_description" value="Short Description">
                            <div class="toolbar" data-target="short_description">

                                <button type="button" onclick="formatText('bold')"><span
                                        class="icon icon-bold"></span></button>
                                <button type="button" onclick="formatText('italic')"><span
                                        class="icon icon-italic"></span></button>
                                <button type="button" onclick="formatText('strikethrough')"><span
                                        class="icon icon-strike"></span></button>
                                <button type="button" class="insertLinkBtn"><span
                                        class="icon icon-link"></span></button>
                                <button type="button" onclick="formatText('formatBlock', 'h4')"><span
                                        class="icon icon-case"></span></button>
                                <button type="button" onclick="formatText('insertUnorderedList')"><span
                                        class="icon icon-un-list"></span></button>
                                <button type="button" onclick="formatText('insertOrderedList')"><span
                                        class="icon icon-list"></span></button>
                                <button type="button" onclick="formatText('justifyLeft')"><span
                                        class="icon icon-text-left"></span></button>
                                <button type="button" onclick="formatText('justifyCenter')"><span
                                        class="icon icon-text-center"></span></button>
                                <button type="button" onclick="formatText('justifyRight')"><span
                                        class="icon icon-text-right"></span></button>
                            </div>

                            <div contenteditable="true" class="rich-textarea" id="short_description">
                                <?php echo wp_kses_post($product_data['short_description'] ?? ''); ?>
                            </div>
                            <input type="hidden" name="short_description" id="short_description_input"
                                value="<?php echo esc_attr($product_data['short_description'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col">
                        <div class="editable-label">
                            <label class="label" for="long_description" contenteditable="false">Long Description</label>
                            <span class="edit-icon icon icon-edit"></span>
                        </div>

                        <div class="text-rich-editor long_description">
                            <input type="hidden" name="label_long_description" value="Long Description">

                            <div class="toolbar" data-target="long_description">
                                <button type="button" onclick="formatText('bold')"><span
                                        class="icon icon-bold"></span></button>
                                <button type="button" onclick="formatText('italic')"><span
                                        class="icon icon-italic"></span></button>
                                <button type="button" onclick="formatText('strikethrough')"><span
                                        class="icon icon-strike"></span></button>
                                <button type="button" class="insertLinkBtn"><span
                                        class="icon icon-link"></span></button>
                                <button type="button" onclick="formatText('formatBlock', 'h4')"><span
                                        class="icon icon-case"></span></button>
                                <button type="button" onclick="formatText('insertUnorderedList')"><span
                                        class="icon icon-un-list"></span></button>
                                <button type="button" onclick="formatText('insertOrderedList')"><span
                                        class="icon icon-list"></span></button>
                                <button type="button" onclick="formatText('justifyLeft')"><span
                                        class="icon icon-text-left"></span></button>
                                <button type="button" onclick="formatText('justifyCenter')"><span
                                        class="icon icon-text-center"></span></button>
                                <button type="button" onclick="formatText('justifyRight')"><span
                                        class="icon icon-text-right"></span></button>
                            </div>

                            <div contenteditable="true" class="rich-textarea" id="long_description"><?php
                            echo wp_kses_post($product_data['long_description'] ?? '');
                            ?></div>
                            <input type="hidden" name="long_description" id="long_description_input" value="<?php
                            echo esc_attr($product_data['long_description'] ?? '');
                            ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col">
                        <div class="editable-label">
                            <label class="label" for="terms_conditions" contenteditable="false">Terms &
                                Conditions</label>
                            <span class="edit-icon icon icon-edit"></span>
                        </div>

                        <div class="text-rich-editor long_description">
                            <input type="hidden" name="label_terms_conditions" value="Terms & Conditions">

                            <div class="toolbar" data-target="terms_conditions">
                                <button type="button" onclick="formatText('bold')"><span
                                        class="icon icon-bold"></span></button>
                                <button type="button" onclick="formatText('italic')"><span
                                        class="icon icon-italic"></span></button>
                                <button type="button" onclick="formatText('strikethrough')"><span
                                        class="icon icon-strike"></span></button>
                                <button type="button" class="insertLinkBtn"><span
                                        class="icon icon-link"></span></button>
                                <button type="button" onclick="formatText('formatBlock', 'h4')"><span
                                        class="icon icon-case"></span></button>
                                <button type="button" onclick="formatText('insertUnorderedList')"><span
                                        class="icon icon-un-list"></span></button>
                                <button type="button" onclick="formatText('insertOrderedList')"><span
                                        class="icon icon-list"></span></button>
                                <button type="button" onclick="formatText('justifyLeft')"><span
                                        class="icon icon-text-left"></span></button>
                                <button type="button" onclick="formatText('justifyCenter')"><span
                                        class="icon icon-text-center"></span></button>
                                <button type="button" onclick="formatText('justifyRight')"><span
                                        class="icon icon-text-right"></span></button>
                            </div>

                            <div contenteditable="true" class="rich-textarea" id="terms_conditions"><?php
                            echo wp_kses_post($product_data['terms_conditions'] ?? '');
                            ?></div>
                            <input type="hidden" name="terms_conditions" id="terms_conditions_input" value="<?php
                            echo esc_attr($product_data['terms_conditions'] ?? '');
                            ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col">
                        <div class="editable-label">
                            <label class="label" for="how_to_use" contenteditable="false">How To Use</label>
                            <span class="edit-icon icon icon-edit"></span>
                        </div>

                        <div class="text-rich-editor long_description">
                            <input type="hidden" name="label_how_to_use" value="How To Use">

                            <div class="toolbar" data-target="how_to_use">
                                <button type="button" onclick="formatText('bold')"><span
                                        class="icon icon-bold"></span></button>
                                <button type="button" onclick="formatText('italic')"><span
                                        class="icon icon-italic"></span></button>
                                <button type="button" onclick="formatText('strikethrough')"><span
                                        class="icon icon-strike"></span></button>
                                <button type="button" class="insertLinkBtn"><span
                                        class="icon icon-link"></span></button>
                                <button type="button" onclick="formatText('formatBlock', 'h4')"><span
                                        class="icon icon-case"></span></button>
                                <button type="button" onclick="formatText('insertUnorderedList')"><span
                                        class="icon icon-un-list"></span></button>
                                <button type="button" onclick="formatText('insertOrderedList')"><span
                                        class="icon icon-list"></span></button>
                                <button type="button" onclick="formatText('justifyLeft')"><span
                                        class="icon icon-text-left"></span></button>
                                <button type="button" onclick="formatText('justifyCenter')"><span
                                        class="icon icon-text-center"></span></button>
                                <button type="button" onclick="formatText('justifyRight')"><span
                                        class="icon icon-text-right"></span></button>
                            </div>

                            <div contenteditable="true" class="rich-textarea" id="how_to_use"><?php
                            echo wp_kses_post($product_data['how_to_use'] ?? '');
                            ?></div>
                            <input type="hidden" name="how_to_use" id="how_to_use_input" value="<?php
                            echo esc_attr($product_data['how_to_use'] ?? '');
                            ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col">
                        <div class="editable-label">
                            <label class="label" for="_expire_date" contenteditable="false">Expiry Date/Time</label>
                            <span class="edit-icon icon icon-edit"></span>
                        </div>

                        <div class="text-rich-editor expire_date">
                            <input type="hidden" name="label__expire_date" value="Expiry Date/Time">

                            <div class="toolbar" data-target="_expire_date">
                                <button type="button" onclick="formatText('bold')"><span
                                        class="icon icon-bold"></span></button>
                                <button type="button" onclick="formatText('italic')"><span
                                        class="icon icon-italic"></span></button>
                                <button type="button" onclick="formatText('strikethrough')"><span
                                        class="icon icon-strike"></span></button>
                                <button type="button" class="insertLinkBtn"><span
                                        class="icon icon-link"></span></button>
                                <button type="button" onclick="formatText('formatBlock', 'h4')"><span
                                        class="icon icon-case"></span></button>
                                <button type="button" onclick="formatText('insertUnorderedList')"><span
                                        class="icon icon-un-list"></span></button>
                                <button type="button" onclick="formatText('insertOrderedList')"><span
                                        class="icon icon-list"></span></button>
                                <button type="button" onclick="formatText('justifyLeft')"><span
                                        class="icon icon-text-left"></span></button>
                                <button type="button" onclick="formatText('justifyCenter')"><span
                                        class="icon icon-text-center"></span></button>
                                <button type="button" onclick="formatText('justifyRight')"><span
                                        class="icon icon-text-right"></span></button>
                            </div>

                            <div contenteditable="true" class="rich-textarea" id="_expire_date"><?php
                            echo wp_kses_post($product_data['_expire_date'] ?? '');
                            ?></div>
                            <input type="hidden" name="_expire_date" id="_expire_date_input" value="<?php
                            echo esc_attr($product_data['_expire_date'] ?? '');
                            ?>">
                        </div>
                    </div>
                </div>

                <?php
                // Commented on 20260120
                // Define mappings for readable values
                // $expiry_type_labels = [
                //     'no_expiry' => 'No Expiry',
                //     'gift_set_date' => 'Expires on a Set Date',
                //     'purchase' => 'Expiry Period Starts on Purchase',
                //     'activation' => 'Expiry Period Starts on Activation',
                // ];

                // Commented on 20260120
                // // Get the stored value
                // $stored_expiry_type = $product_data['gift_card_expiry_type'] ?? '';

                // Commented on 20260120
                // // Determine if we need to use the label or the key for selection
                // $selected_expiry_value = '';
                // if ($edit_mode && $stored_expiry_type) {
                //     // Check if the stored value is a label (from old data) or a key
                //     if (in_array($stored_expiry_type, $expiry_type_labels)) {
                //         // It's a label - find the corresponding key
                //         $selected_expiry_value = array_search($stored_expiry_type, $expiry_type_labels);
                //     } else {
                //         // It's already a key
                //         $selected_expiry_value = $stored_expiry_type;
                //     }
                // }

                // Define mappings (Must match the keys used in Saving Logic)
                $expiry_type_labels = [
                    'no_expiry'                          => 'No Expiry',
                    'gift_set_date'                      => 'Expires on a Set Date',
                    'expiry_period_starts_on_purchase'   => 'Expiry Period Starts on Purchase',
                    'expiry_period_starts_on_activation' => 'Expiry Period Starts on Activation',
                ];

                // Get the stored value (It should now be a Key)
                $selected_expiry_value = $product_data['gift_card_expiry_type'] ?? '';

                // Backward compatibility: If the DB currently holds a Label (e.g., "No Expiry"), convert it to Key
                if (!array_key_exists($selected_expiry_value, $expiry_type_labels) && in_array($selected_expiry_value, $expiry_type_labels)) {
                    $selected_expiry_value = array_search($selected_expiry_value, $expiry_type_labels);
                }
                ?>

                <div class="form-group flex-row gift-card-expiry-wrapper">
                    <div class="control-wrapper col col-6 expiry-fields">
                        <label class="label" for="gift_card_expiry_type">Gift Card Expiry Type<span class="validate">*</span></label>
                        <select id="gift_card_expiry_type" name="gift_card_expiry_type" required>
                            <option value="">-- Select Expiry Type --</option>
                            <?php foreach ($expiry_type_labels as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_expiry_value, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="control-wrapper col col-6 gift-expiry-date-field"
                        style="<?php echo ($selected_expiry_value === 'gift_set_date') ? 'display: block;' : 'display: none;'; ?>">
                        <label class="label" for="gift_card_expiry_date">Gift Card Expiry Date<span class="validate">*</span></label>
                        <input class="form-control" type="date" id="gift_card_expiry_date" name="gift_card_expiry_date"
                            value="<?php echo esc_attr($product_data['gift_card_expiry_date'] ?? ''); ?>">
                    </div>

                    <div class="control-wrapper col col-6 gift-expiry-duration-field"
                        style="<?php echo in_array($selected_expiry_value, ['expiry_period_starts_on_purchase', 'expiry_period_starts_on_activation']) ? 'display: block;' : 'display: none;'; ?>">
                        <label class="label" for="gift_card_expiry_duration">Expiry Duration<span class="validate">*</span></label>
                        <div class="expiry-input-group input-group">
                            <input type="number" id="gift_card_expiry_duration" name="gift_card_expiry_duration" min="1" value="<?php echo esc_attr($product_data['gift_card_expiry_duration'] ?? ''); ?>">
                            <select id="gift_card_expiry_unit" name="gift_card_expiry_unit">
                                <option value="days" <?php selected($product_data['gift_card_expiry_unit'] ?? '', 'days'); ?>>Days</option>
                                <option value="weeks" <?php selected($product_data['gift_card_expiry_unit'] ?? '', 'weeks'); ?>>Weeks</option>
                                <option value="months" <?php selected($product_data['gift_card_expiry_unit'] ?? '', 'months'); ?>>Months</option>
                                <option value="years" <?php selected($product_data['gift_card_expiry_unit'] ?? '', 'years'); ?>>Years</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group flex-row activation-expiry-wrapper">
                    <?php $selected_type = trim($product_data['activation_expiry_type'] ?? ''); ?>
                    <div class="control-wrapper col col-6">
                        <label class="label" for="activation_expiry_type">Activation Expiry Type<span class="validate">*</span></label>
                        <select id="activation_expiry_type" name="activation_expiry_type">
                            <option value="">-- Select Activation Expiry Type --</option>
                            <option value="no_activation_expiry" <?php selected($selected_type, 'no_activation_expiry'); ?>>No
                                Activation Expiry</option>
                            <option value="no_activation_needed" <?php selected($selected_type, 'no_activation_needed'); ?>>No
                                Activation Needed</option>
                            <option value="activation_set_date" <?php selected($selected_type, 'activation_set_date'); ?>>
                                Activated by a Set Date</option>
                            <option value="set_period" <?php selected($selected_type, 'set_period'); ?>>Activated within
                                a Set
                                Period</option>
                        </select>
                    </div>

                    <div class="control-wrapper col col-6" id="activation_expiry_date_field" style="display: none;">
                        <label class="label" for="activation_expiry_date">Activation Expiry Date<span class="validate">*</span></label>
                        <input class="form-control" type="date" id="activation_expiry_date"
                            name="activation_expiry_date"
                            value="<?php echo esc_attr($product_data['activation_expiry_date'] ?? ''); ?>">
                    </div>
                    <div class="control-wrapper col col-6" id="activation_expiry_period_field" style="display: none;">
                        <label class="label" for="activation_expiry_duration">Activation Expiry Period<span class="validate">*</span></label>
                        <div class="expiry-input-group input-group">
                            <input type="number" id="activation_expiry_duration" name="activation_expiry_duration"
                                min="1"
                                value="<?php echo esc_attr($product_data['activation_expiry_duration'] ?? ''); ?>">
                            <select id="activation_expiry_unit" name="activation_expiry_unit">
                                <option value="days" <?php selected($product_data['activation_expiry_unit'] ?? '', 'days'); ?>>Days</option>
                                <option value="weeks" <?php selected($product_data['activation_expiry_unit'] ?? '', 'weeks'); ?>>Weeks</option>
                                <option value="months" <?php selected($product_data['activation_expiry_unit'] ?? '', 'months'); ?>>Months</option>
                                <option value="years" <?php selected($product_data['activation_expiry_unit'] ?? '', 'years'); ?>>Years</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="image-upload-container" id="dropZone">
                        <label for="brand_logo_label">Brand Logo<span class="validate">*</span></label>
                        <div class="preview-grid brand-logo">
                            <!-- Preview container with existing image if in edit mode -->
                            <div id="brand_logo_preview" class="image-preview2">
                                <?php if ($edit_mode && !empty($product_data['brand_logo'])): ?>
                                    <div class="preview-item">
                                        <img src="<?php echo esc_url($product_data['brand_logo']); ?>" alt="Brand Logo">
                                        <button class="delete-btn">&times;</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="upload-box">
                                <input type="hidden" id="brand_thumbnail_url" name="brand_thumbnail_url"
                                    value="<?php echo isset($product_data['brand_logo']) ? esc_attr($product_data['brand_logo']) : ''; ?>">
                                <div class="upload-instructions">
                                    <label for="brand_logo" class="upload-click-area">
                                        <div class="upload-icon">📁</div>
                                        <div>Upload,</div>
                                    </label>
                                    <p>
                                        <a href="#" class="upload-link"
                                            onclick="event.preventDefault(); document.querySelector('.url-input-container').style.display = 'block';">
                                            Link
                                        </a> or drag and drop
                                    </p>
                                    <small>SVG, PNG, JPG or GIF (max. 3MB)</small>
                                </div>
                                <input type="file" id="brand_logo" name="brand_logo" accept="image/*"
                                    style="display: none;">
                            </div>
                        </div>

                        <div class="url-input-container" style="display: none;">
                            <input type="text" id="imageUrl" placeholder="Paste image URL here">
                            <button class="btn btn-primary size-sm" type="button" onclick="handleUrl()">Load from
                                URL</button>
                            <button class="btn btn-outline size-sm" type="button"
                                onclick="document.querySelector('.url-input-container').style.display = 'none';">Cancel</button>
                        </div>


                    </div>
                    <div id="image-error" style=" margin-top: 10px;"></div>
                </div>

                <div class="form-bottom center">
                    <button type="button" class="btn-black-white btn-primary-black next-step btn btn-primary">Continue</button>
                </div>
            </div>

            <div class="form-step step-2" style="display: none;">
                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6">
                        <label class="label" for="denomination_type">Denomination Type<span class="validate">*</span></label>
                        <select id="denomination_type-dropdown" name="denomination_type" required>
                            <option value="">Select Denomination Type</option>
                            <?php
                            $denomination_options = ['Variable', 'Fixed'];
                            

                            // Get the saved denomination type from product data
                            $selected_denomination_types = $product_data['denomination_type'] ?? '';

                            // If empty (new product), default to 'Fixed'
                            if (empty($selected_denomination_types)) {
                                $selected_denomination_types = ['Fixed'];
                            }
                            // $selected_denomination_types = (array) $selected_denomination_types;
                            $selected_denomination_types = array_map('strtolower', (array) $selected_denomination_types);


                            foreach ($denomination_options as $option) {
                                $option_value = strtolower($option); // lowercase for the value attribute
                                $selected = in_array($option_value, $selected_denomination_types) ? 'selected' : '';
                                echo "<option value='".strtolower($option_value)."' {$selected}>{$option}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group flex-row hide">
                    <div class="control-wrapper col col-6 dollar-sign" id="variable_range_from_wrapper" style="display: none;">
                        <label class="label" for="variable_range_from" class="dollar-sign">Variable Range From<span class="validate">*</span></label>
                        <input class="form-control" type="number" id="variable_range_from" name="variable_range_from"
                            min="0" value="<?php echo esc_attr($product_data['variable_range_from'] ?? ''); ?>"
                            required>
                        <div id="variable_range_from_error" style=" display: none;"></div>
                    </div>
                    <div class="control-wrapper col col-6 dollar-sign" id="variable_range_to_wrapper" style="display: none;">
                        <label class="label" for="variable_range_to" class="dollar-sign">Variable Range To<span class="validate">*</span></label>
                        <input class="form-control" type="number" id="variable_range_to" name="variable_range_to"
                            min="0" max="1000" value="<?php echo esc_attr($product_data['variable_range_to'] ?? ''); ?>"
                            required>
                        <div id="variable_range_to_error" style=" display: none;" class="error-field"></div>
                        <!-- <div id="variable_range_to_error" style=" display: none;">Value must be between 0 and 100000.</div> -->
                        <span id="_range_validation_error" style=" display: none;"></span>
                    </div>


                </div>

                <div class="form-group flex-row hide">
                    <div class="control-wrapper col col-6 dollar-sign" id="reedem_at_intervals_wrapper" style="display: none;">

                        <label class="label" for="_reedem_at_intervals" class="dollar-sign">Reedem At Intervals<span class="validate">*</span></label>
                        <input class="form-control" type="number" id="_reedem_at_intervals" name="_reedem_at_intervals"
                            min="0" value="<?php echo esc_attr($product_data['_reedem_at_intervals'] ?? ''); ?>" required>
                        <div id="_redeem_at_intervals_error" style=" display: none;"></div>

                    </div>

                    <div class="control-wrapper col col-6 dollar-sign" id="sell_price_lowest_denomination_wrapper"
                        style="display: none;">
                        <label class="label" for="sell_price_lowest_denomination" class="dollar-sign">Sell Price Lowest
                            Denomination <span class="validate">*</span>
                            <span class="tooltip-icon">
                                <i class="fas fa-info-circle"></i>
                                <span class="tooltip-text">
                                    Please use the sell price for the lowest denomination in this variable card range.
                                    All price points will be based on the lowest denomination.
                                </span>
                            </span>
                        </label>
                        <input class="form-control" type="number" id="sell_price_lowest_denomination"
                            name="sell_price_lowest_denomination" min="0"
                            value="<?php echo esc_attr($product_data['sell_price_lowest_denomination'] ?? ''); ?>"
                            required>
                        <div id="sell_price_lowest_denomination_error" style=" display: none;">Value must be between
                            0 and 100000.
                        </div>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="_sell_price_fixed" class="dollar-sign">Sell Price Fixed<span class="validate">*</span></label>
                        <input class="form-control" type="number" id="_sell_price_fixed" name="_sell_price_fixed"
                            min="0" value="<?php echo esc_attr($product_data['_sell_price_fixed'] ?? ''); ?>" required>
                        <div id="_sell_price_fixed_error" class="error-field" style=" display: none;">Value must be
                            between 0 and 100000.</div>
                        <p id="price_warning" style=" display: none;">Sell price cannot be less than the cost price.</p>
                    </div>
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="_denomination_amount" class="dollar-sign">Denomination Amount<span class="validate">*</span></label>
                        <input class="form-control" type="number" id="_denomination_amount" name="_denomination_amount"
                            min="0" value="<?php echo esc_attr($product_data['_denomination_amount'] ?? ''); ?>" required>
                        <div id="_denomination_amount_error" class="error-field" style=" display: none;">Value must be
                            between 0 and 100000.</div>
                        <!-- <p id="price_warning" style=" display: none;">Denomination Amount cannot be less than the cost price.</p> -->
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="cost_price" class="dollar-sign">Cost Price Lowest Denomination</label>
                        <input class="form-control" type="number" id="cost_price" name="_cost_price"
                            min="0" value="<?php echo esc_attr($product_data['_cost_price'] ?? ''); ?>">
                        <div id="cost_price_error" style=" display: none;" class="error-field">Value must be between 0
                            and 100000.</div>
                    </div>
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="_supplier_fullfillment_price" class="dollar-sign">Supplier Fullfillment Price</label>
                        <input class="form-control" type="number" id="_supplier_fullfillment_price"
                            name="_supplier_fullfillment_price" min="0"
                            value="<?php echo esc_attr($product_data['_supplier_fullfillment_price'] ?? ''); ?>">
                        <div id="_supplier_fullfillment_price_error" style=" display: none;" class="error-field">Value
                            must be between 0 and
                            100000.</div>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="_gst" class="dollar-sign">GST</label>
                        <input class="form-control" type="number" id="_gst" name="_gst"
                            min="0" value="<?php echo esc_attr($product_data['_gst'] ?? ''); ?>">
                        <div id="_gst_error" style=" display: none;" class="error-field">Value must be between 0 and
                            100.</div>
                    </div>
                </div>
                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="j_a_c_fulfillment_cost" class="dollar-sign">J&C Fulfillment
                            Cost</label>
                        <input class="form-control" type="number" id="j_a_c_fulfillment_cost"
                            name="j_a_c_fulfillment_cost" min="0"
                            value="<?php echo esc_attr($product_data['j_a_c_fulfillment_cost'] ?? ''); ?>">
                        <div id="j_a_c_fulfillment_cost_error" style=" display: none;" class="error-field">Value must be
                            between 0 and
                            100000.</div>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="total_sell_price" class="readonly dollar-sign"> Total Sell
                            Price</label>
                        <input class="form-control" type="number" id="total_sell_price" name="_total_sell_price"
                            value="<?php echo esc_attr($product_data['_total_sell_price'] ?? ''); ?>" readonly>
                    </div>
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="total_buy_price" class="dollar-sign readonly">Total Buy Price</label>
                        <input class="form-control" type="number" id="total_buy_price" name="_total_buy_price"
                            value="<?php echo esc_attr($product_data['_total_buy_price'] ?? ''); ?>" readonly>
                    </div>
                </div>

                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6">
                        <label class="label" for="margin_per" class="readonly">Margin %:</label>
                        <input class="form-control" type="number" id="margin_per" name="margin_per"
                            value="<?php echo esc_attr($product_data['margin_per'] ?? ''); ?>" readonly>
                    </div>
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="margin_currency" class="readonly dollar-sign">Margin $:</label>
                        <input class="form-control" type="number" id="margin_currency" name="margin_currency"
                            value="<?php echo esc_attr($product_data['margin_currency'] ?? ''); ?>" readonly>
                    </div>
                </div>


                <div class="form-group flex-row">
                    <div class="control-wrapper col col-6 dollar-sign">
                        <label class="label" for="total_buy_price_including_gst" class="readonly dollar-sign">Total Buy
                            Price Including GST</label>
                        <input class="form-control" type="number" id="total_buy_price_including_gst"
                            name="_total_buy_price_gst"
                            value="<?php echo esc_attr($product_data['_total_buy_price_gst'] ?? ''); ?>" readonly>
                    </div>
                </div>

                <!-- <label for="regular_price" class="dollar-sign">Regular Price *</label>
                <input type="number" step="0.01" id="regular_price" name="regular_price" required> -->


                <!-- <label for="sale_price">Sale Price</label>
                <input type="number" step="0.01" id="sale_price" name="sale_price"> -->

                <?php
                // Get the product's shipping class
                
                // Get saved values
                $discounted_value = get_post_meta($product_id, 'discounted_price_checkbox', true); // Yes / No
                $preset_delivery_value = get_post_meta($product_id, 'preset_delivery_class', true); // Yes / No

                $is_discounted_checked = ($discounted_value === 'Yes') ? true : false;
                $is_preset_checked = ($preset_delivery_value === 'Yes') ? true : false;

                $shipping_class_slug = $product_data['presetClasses'] ?? '';
                $delivery_cost = $product_data['_delivery_cost'] ?? '';
                
                // Commented on 20251224
                // Determine if checkbox should be checked
                // $is_preset_checked = !empty($shipping_class_slug);
                ?>

                <div class="form-group">
                    <div class="form-group">
                        <div class="form-check checkbox">
                            <input type="checkbox" id="presetDeliveryClass" name="presetDeliveryClass" value="on" <?php checked($is_preset_checked); ?>>  
                            <label for="presetDeliveryClass">Preset Delivery Class</label>
                        </div>
                    </div>

                    <div id="preset-delivery-fields"
                        style="<?php echo $is_preset_checked ? 'display: block;' : 'display: none;'; ?>">
                        <div class="form-group">
                            <div class="control-wrapper">
                                <select id="presetClasses" name="presetClasses">
                                    <option value="">Select Shipping Class</option>
                                    <?php
                                    // Fetch all shipping classes

                                    $shipping_classes = get_terms(array(
                                        'taxonomy' => 'product_shipping_class',
                                        'hide_empty' => false,
                                    ));

                                    if (!empty($shipping_classes) && !is_wp_error($shipping_classes)) {
                                        foreach ($shipping_classes as $shipping_class) {
                                            $selected = ($shipping_class->slug === $shipping_class_slug) ? 'selected' : '';

                                            echo '<option value="' . esc_attr($shipping_class->slug) . '" ' . $selected . '>';
                                            echo esc_html($shipping_class->name);
                                            echo '</option>';
                                        }
                                    } else {
                                        echo '<option value="">No shipping classes found</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="control-wrapper">
                                <label class="label" for="delivery_cost" class="dollar-sign">Delivery Cost</label>
                                <input class="form-control" type="number" id="delivery_cost" name="_delivery_cost"
                                    min="0" value="<?php echo esc_attr($delivery_cost); ?>">
                                <div id="delivery_cost_error" style=" display: none;" class="error-field">Value must be
                                    between 0 and 100000.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-bottom center">
                    <button type="button" class="next-step btn btn-primary btn-black-white btn-primary-black">Continue</button>
                </div>
            </div>

            <div class="form-step step-3" style="display: none;">

                <!-- //Discount Toggle Fields Starts-->
                <div class="form-group">
                    <div class="form-check checkbox"> <input type="checkbox" id="discounted_price_checkbox"
                            name="discounted_price_checkbox" value="on" <?php checked($is_discounted_checked); ?>> <label
                            for="discounted_price_checkbox">Add Discounts </label> </div>
                </div>
                <div class="form-group flex-row hide"> <label id="discounted_price_label" for="discounted_price"
                        class="dollar-sign control-wrapper col col-6"
                        style="<?php echo ($product_data['discounted_price_checkbox'] ?? 'No') === 'Yes' ? 'display: block;' : 'display: none;'; ?>">
                        <label class="label">Discounted Sell Price</label> <input class="form-control" type="number"
                            id="discounted_price" name="discounted_price" placeholder="Enter Discounted Price" min="0" max="1000000"
                            value="<?php echo esc_attr($product_data['discounted_price'] ?? ''); ?>">
                        <div id="discounted_price_error" style=" display: none;" class="error-field">Value must be between 0 and 100000.
                        </div>
                    </label> </div>
                <div class="discounted-container">
                    <div class="discounted-wrapper form-group flex-row hide"> 
                        <label id="_margin_label" for="_margin" class="dollar-sign control-wrapper col col-6" style="<?php echo ($product_data['discounted_price_checkbox'] ?? 'No') === 'Yes' ? 'display: block;' : 'display: none;'; ?>">
                            <label class="label">Margin</label> 
                                <input class="form-control" type="number" id="_margin" name="_margin" value="<?php echo esc_attr($product_data['_margin'] ?? ''); ?>" readonly> 
                        </label> 
                        <label id="_discount_margin_label" for="_discount_margin" class="dollar-sign control-wrapper col col-6" style="<?php echo ($product_data['discounted_price_checkbox'] ?? 'No') === 'Yes' ? 'display: block;' : 'display: none;'; ?>">
                            <label class="label">Discount Margin</label> 
                            <input class="form-control" type="number" id="_discount_margin_input" name="_discount_margin" max="100" value="<?php echo esc_attr($product_data['_discount_margin'] ?? ''); ?>" readonly>
                            <div id="_discount_margin_input_error" class="error-field" style=" display: none;">Value must be between 0 and 100.</div>
                        </label> 
                    </div>
                    <div class="discounted-wrapper form-group flex-row hide"> 
                        <label class="control-wrapper col col-6" id="discount_from_label" for="_discount_valid_from" style="<?php echo ($product_data['discounted_price_checkbox'] ?? 'No') === 'Yes' ? 'display: block;' : 'display: none;'; ?>"> Discount Valid From 
                            <input class="form-control" type="datetime-local" min="<?php echo date('Y-m-d\TH:i'); ?>" id="_discount_valid_from" name="_discount_valid_from" value="<?php echo esc_attr($product_data['_discount_valid_from'] ?? ''); ?>"> 
                        </label> 
                        <label class="control-wrapper col col-6" id="discount_to_label" for="_discount_valid_to" style="<?php echo ($product_data['discounted_price_checkbox'] ?? 'No') === 'Yes' ? 'display: block;' : 'display: none;'; ?>"> Discount Valid To 
                            <input class="form-control" type="datetime-local" min="<?php echo date('Y-m-d\TH:i'); ?>" id="_discount_valid_to" name="_discount_valid_to" value="<?php echo esc_attr($product_data['_discount_valid_to'] ?? ''); ?>">
                        </label>
                     </div>
                </div>

                <?php
                // Icons, Tags, and Categories
                display_icons_input_field($product_data['icons'] ?? []);
                display_tags_input_field($product_data['product_tags'] ?? []);
                display_category_input_field($product_data['product_cat'] ?? []);
                ?>

                <!-- Featured Placement: Simple Select2 (no custom checkboxes - avoids CSS/display issues) -->
                <div class="form-group">
                    <div class="control-wrapper multi-select-normal">
                        <label class="label" for="featured_placements">Featured Placement</label>
                        <select id="featured_placements" name="featured_placements[]" multiple="multiple" style="width: 100%;">
                            <?php
                            $selected_values = $product_data['featured_placements'] ?? [];

                            $choices = [
                                'create-product'     => 'Create Product',
                                'shop'               => 'Shop',
                                'cart'               => 'Cart',
                                'checkout'           => 'Checkout',
                                'my-account'         => 'My account',
                                'bulk-upload-order'  => 'Bulk Upload Order',
                                'manual-order'       => 'Manual Order',
                                'order'              => 'Order'
                            ];

                            foreach ($choices as $value => $label) {
                                $selected = in_array($value, (array) $selected_values) ? 'selected="selected"' : '';
                                echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <?php
                // Eligible Retailers
                display_eligible_retailers_dropdown($product_data['eligible_retailers'] ?? []);
                ?>

                <?php
                // -----------------------
                // Eligible Gift Cards UI
                // Paste this AFTER display_eligible_retailers_dropdown(...) and BEFORE the Extra Header block.
                // -----------------------

                // Commented on 20251224
                // Prepare current saved value (if any)
                // $saved_eligible = $product_data['eligible_gift_cards_json'] ?? '';
                // if ( is_array( $saved_eligible ) ) {
                //     $saved_eligible = wp_json_encode( $saved_eligible );
                // }
                // $nonce = wp_create_nonce( 'gc_plus_nonce' );
                ?>
                <!-- Eligible Gift Cards: START -->
                <div class="form-group" id="gc_eligible_field_wrap">
                    <label class="label">Eligible Gift Cards</label>

                    <div class="gc-controls" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                        <input type="text" id="gc_search" class="form-control" placeholder="Search SKU, Title or Brand" style="flex:1;" />
                        <!-- <button type="button" id="gc_select_all" class="button">Select All</button>
                        <button type="button" id="gc_deselect_all" class="button">Deselect All</button> -->

                        <label for="gc_csv_upload" class="button btn-black-white" style="margin-left:8px;cursor:pointer;">Bulk CSV</label>
                        <input id="gc_csv_upload" type="file" accept=".csv" style="display:none" />
                    </div>

                    <div id="gc_nested_container" style="border:1px solid #e1e1e1;padding:10px;border-radius:4px;max-height:300px;overflow:auto; display:none;">
                        <!-- Nested Brand -> Parent -> Child will render here -->
                        <div id="gc_loading" style="display:none;">Loading...</div>
                        <div id="gc_tree"></div>
                    </div>

                    <div id="gc_selected_section" style="margin-top:12px; display:none;">
                        <label class="label">Selected / Ranked SKUs</label>
                        <ul id="gc_selected_list" style="list-style:none;padding:8px;border:1px dashed #ccc;min-height:40px;"></ul>
                    </div>

                    <input type="hidden" id="eligible_gift_cards_json" name="eligible_gift_cards_json" value="<?php echo esc_attr( $saved_eligible ); ?>" />
                    <div id="gc_csv_error" style="display:none; color:#b30000; background:#ffe5e5; padding:10px; margin-bottom:15px; border:1px solid #b30000; border-radius:4px;"></div>
                    <div id="gc_error" class="error-field" style="display:none;color:#b30000;margin-top:6px;">This field is required for Supplier is J&C Supplier.</div>
                </div>

                <!-- Extra Header -->
                <div class="form-group">
                    <div class="control-wrapper col">
                        <div class="editable-label">
                            <label class="label" for="extra_header" contenteditable="false">Extra Header</label>
                            <span class="edit-icon icon icon-edit"></span>
                        </div>

                        <div class="text-rich-editor">
                            <input type="hidden" name="label_extra_header"
                                value="<?php echo esc_attr($product_data['label_extra_header'] ?? 'Extra Header'); ?>">

                            <div class="toolbar" data-target="extra_header">
                                <button type="button" onclick="formatText('bold')"><span
                                        class="icon icon-bold"></span></button>
                                <button type="button" onclick="formatText('italic')"><span
                                        class="icon icon-italic"></span></button>
                                <button type="button" onclick="formatText('strikethrough')"><span
                                        class="icon icon-strike"></span></button>
                                <button type="button" class="insertLinkBtn"><span
                                        class="icon icon-link"></span></button>
                                <button type="button" onclick="formatText('formatBlock', 'h4')"><span
                                        class="icon icon-case"></span></button>
                                <button type="button" onclick="formatText('insertUnorderedList')"><span
                                        class="icon icon-un-list"></span></button>
                                <button type="button" onclick="formatText('insertOrderedList')"><span
                                        class="icon icon-list"></span></button>
                                <button type="button" onclick="formatText('justifyLeft')"><span
                                        class="icon icon-text-left"></span></button>
                                <button type="button" onclick="formatText('justifyCenter')"><span
                                        class="icon icon-text-center"></span></button>
                                <button type="button" onclick="formatText('justifyRight')"><span
                                        class="icon icon-text-right"></span></button>
                            </div>

                            <div contenteditable="true" class="rich-textarea" id="extra_header">
                                <?php echo wp_kses_post($product_data['_extra_header'] ?? ''); ?>
                            </div>
                            <input type="hidden" name="_extra_header" id="extra_header_input"
                                value="<?php echo esc_attr($product_data['_extra_header'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        const form = document.getElementById("gift-card-form");

                        if (form) {
                            form.addEventListener("submit", function () {
                                // Sync all editors before submit
                                document.querySelectorAll(".rich-textarea").forEach(editor => {
                                    const hiddenInput = document.getElementById(editor.id + "_input");
                                    if (hiddenInput) {
                                        hiddenInput.value = editor.innerHTML;
                                    }
                                });
                            });
                        }
                    });

                    function formatText(command, value = null) {
                        const activeEditor = document.querySelector(".rich-textarea.active");
                        if (!activeEditor) return;

                        if (command === 'formatBlock' && value === 'h4') {
                            const selection = window.getSelection();
                            if (!selection.rangeCount) return;

                            const range = selection.getRangeAt(0);
                            const parent = range.commonAncestorContainer;
                            const h4 = getParentTag(parent, 'H4');

                            if (h4) {
                                const span = document.createElement('span');
                                span.innerHTML = h4.innerHTML;
                                h4.parentNode.replaceChild(span, h4);
                            } else {
                                document.execCommand(command, false, value);
                            }
                        } else {
                            document.execCommand(command, false, value);
                        }

                        updateHiddenInput(activeEditor.id);
                    }


                    function getParentTag(el, tagName) {
                        while (el) {
                            if (el.nodeType === 1 && el.tagName === tagName.toUpperCase()) return el;
                            el = el.parentNode;
                        }
                        return null;
                    }


                    function updateHiddenInput(editorId) {
                        const hiddenInput = document.getElementById(editorId + "_input");
                        if (hiddenInput) {
                            hiddenInput.value = document.getElementById(editorId).innerHTML;
                        }
                    }

                    // Initialize all rich text editors
                    function initializeEditors() {
                        document.querySelectorAll(".rich-textarea").forEach(editor => {
                            if (!editor.dataset.initialized) {
                                editor.addEventListener("input", function () {
                                    updateHiddenInput(this.id);
                                });

                                editor.addEventListener("focus", function () {
                                    document.querySelectorAll(".rich-textarea").forEach(el => el.classList.remove("active"));
                                    this.classList.add("active");
                                });

                                editor.dataset.initialized = "true"; // Mark as initialized
                            }
                        });
                        document.querySelectorAll(".insertLinkBtn").forEach(button => {
                            if (!button.dataset.initialized) {
                                button.addEventListener("click", function (e) {
                                    e.preventDefault();

                                    const selection = window.getSelection();
                                    if (!selection.rangeCount) return;

                                    const range = selection.getRangeAt(0);
                                    const node = selection.focusNode;

                                    const parentAnchor = getParentTag(node, 'a');

                                    if (parentAnchor) {
                                        // Already a link — remove it
                                        document.execCommand('unlink');
                                    } else {
                                        // Not a link — ask for URL
                                        let url = prompt("Enter the URL:", "https://");

                                        if (url && url !== "https://") {
                                            // ✅ Ensure absolute URL
                                            if (!/^https?:\/\//i.test(url)) {
                                                url = "https://" + url;
                                            }
                                            document.execCommand('createLink', false, url);
                                        }
                                    }
                                });

                                button.dataset.initialized = "true";
                            }
                        });
                    }
                    initializeEditors();

                    // Optional: Re-initialize if new editors are added dynamically
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.addedNodes.length) {
                                initializeEditors();
                            }
                        });
                    });

                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });

                </script>
                <?php
                $stock_levels = get_post_meta($product_id, 'add_stock_levels', true);
                $transaction_mimit = get_post_meta($product_id, 'add_transaction_limit_checkbox', true);
                ?>

                <div class="form-check checkbox mb-2">
                    <input type="checkbox" id="add_stock_checkbox" name="add_stock_checkbox" <?php checked($stock_levels ?? 'No', 'Yes'); ?>>
                    <label for="add_stock_checkbox">Add Stock</label>
                </div>
                <label class="form-group" id="_add_stock_level_label" for="_add_stock_level"
                    style="<?php echo ($product_data['add_stock_levels'] ?? $stock_levels ?? 'No') === 'Yes' ? 'display: block;' : 'display: none;'; ?>">
                    <div class="control-wrapper col col-6">
                        <!-- <label class="label">Add Stock Level</label> -->
                        <input class="form-control" type="number" id="_add_stock_level" name="_add_stock_level"
                            min="0" value="<?php echo esc_attr($product_data['_add_stock_level'] ?? ''); ?>"
                            placeholder="Add Stock Level">
                    </div>
                </label>

                <!-- Transaction Limits -->
                <div class="form-check checkbox mb-2">
                    <input type="checkbox" id="add_transaction_limit_checkbox" name="add_transaction_limit_checkbox"
                    <?php checked($transaction_mimit ?? 'No', 'Yes'); ?>>
                    <label for="add_transaction_limit_checkbox">
                        Add Transaction Limit
                    </label>
                </div>
                <div class="transaction-container form-group flex-row hide">
                    <label class="control-wrapper col col-6" id="_quantity_per_transaction_label"
                        for="_quantity_per_transaction"
                        style="<?php echo ($product_data['add_transaction_limit_checkbox'] ?? 'No') === 'Yes' ? 'display: block;' : 'display: none;'; ?>">
                        <label class="label">Quantity Per Transaction</label>
                        <input class="form-control" type="number" id="_quantity_per_transaction"
                            name="_quantity_per_transaction" min="0"
                            value="<?php echo esc_attr($product_data['_quantity_per_transaction'] ?? ''); ?>"
                            placeholder="Quantity Per Transaction">
                    </label>

                    <label class="control-wrapper col col-6" id="_total_value_per_transaction_label" class="dollar-sign"
                        for="_total_value_per_transaction"
                        style="<?php echo ($product_data['add_transaction_limit_checkbox'] ?? 'No') === 'Yes' ? 'display: block;' : 'display: none;'; ?>">
                        <label class="label">Total Value Per Transaction</label>
                        <input class="form-control" type="text" id="_total_value_per_transaction"
                            name="_total_value_per_transaction"
                            value="<?php echo esc_attr($product_data['_total_value_per_transaction'] ?? ''); ?>"
                            placeholder="Add Total Value Per Transaction">
                        <div id="_total_value_per_transaction_error" style=" display: none;">Value must be between 0 and
                            10000.
                        </div>
                    </label>
                </div>

                <!-- Always On -->
                <div class="form-group flex-wrap mb-2">
                    <?php
                    // If not in edit mode or always_on is not set, default to 'Yes'
                    $always_on_value = isset($product_data['always_on']) ? $product_data['always_on'] : 'Yes'; ?>

                    <div class="form-check checkbox">
                        <input type="checkbox" id="always_on" name="always_on" <?php checked($always_on_value, 'Yes'); ?>>
                        <label for="always_on">
                            Always On
                        </label>
                    </div>
                </div>
                <div class="always-on-container form-group flex-row">
                    <label class="control-wrapper col col-6" id="_onsite_from_label" for="_onsite_from"
                        style="<?php echo ($product_data['always_on'] ?? 'No') !== 'Yes' ? 'display: block;' : 'display: none;'; ?>">
                        <label class="label">Onsite From(Date/Time)</label>
                        <input class="form-control" min="<?php echo date('Y-m-d\TH:i'); ?>" type="datetime-local" id="_onsite_from" name="_onsite_from"
                            value="<?php echo esc_attr($product_data['_onsite_from'] ?? ''); ?>">
                    </label>

                    <label class="control-wrapper col col-6" id="_onsite_to_label" for="_onsite_to"
                        style="<?php echo ($product_data['always_on'] ?? 'No') !== 'Yes' ? 'display: block;' : 'display: none;'; ?>">
                        <label class="label">Onsite To(Date/Time)</label>
                        <input class="form-control" min="<?php echo date('Y-m-d\TH:i'); ?>" type="datetime-local" id="_onsite_to" name="_onsite_to"
                            value="<?php echo esc_attr($product_data['_onsite_to'] ?? ''); ?>">
                    </label>
                </div>
                <div class="form-group">
                    <div class="control-wrapper">
                        <label class="label" for="gc_plus_true">Is it a Gift Card Plus Product?</label>
                        
                        <?php 
                        // Get value, default to 'false' if empty (assuming 'false' is the default state)
                        $is_gc_plus = ! empty( $product_data['is_it_gift_card_plus_product'] ) ? $product_data['is_it_gift_card_plus_product'] : 'false'; 
                        ?>

                        <div style="display: flex; gap: 20px; margin-top: 5px;">
                            
                            <div class="form-check radio">
                                <input type="radio" 
                                        id="gc_plus_true" 
                                        name="is_it_gift_card_plus_product" 
                                        value="true" 
                                        <?php checked( $is_gc_plus, 'true' ); ?>>
                                <label for="gc_plus_true">Yes</label>
                            </div>

                            <div class="form-check radio">
                                <input type="radio" 
                                        id="gc_plus_false" 
                                        name="is_it_gift_card_plus_product" 
                                        value="false" 
                                        <?php checked( $is_gc_plus, 'false' ); ?>>
                                <label for="gc_plus_false">No</label>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="control-wrapper">
                        <label class="label">Eligible for Swap?</label>
                        
                        <?php 
                        // Get value, default to 'false' if empty
                        $is_swap = ! empty( $product_data['is_swap_eligible'] ) ? $product_data['is_swap_eligible'] : 'false'; 
                        ?>

                        <div style="display: flex; gap: 20px; margin-top: 5px;">
                            <div class="form-check radio">
                                <input type="radio" id="swap_true" name="is_swap_eligible" value="true" <?php checked( $is_swap, 'true' ); ?>>
                                <label for="swap_true">Yes</label>
                            </div>

                            <div class="form-check radio">
                                <input type="radio" id="swap_false" name="is_swap_eligible" value="false" <?php checked( $is_swap, 'false' ); ?>>
                                <label for="swap_false">No</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Status -->
                <div class="form-group flex-wrap">
                    <div class="control-wrapper col col-6">
                        <label class="label" for="product_status">Product Status</label>
                        <select name="product_status">
                            <option value="">Select Status</option>
                            <?php
                            $custom_statuses = [
                                'pending' => 'Pending',
                                'publish' => 'Active/Published',
                                'wc-awaiting-publishing' => 'Awaiting Publishing',
                                'wc-deactivated' => 'Deactivated',
                                'wc-closed' => 'Closed',
                                'wc-deleted' => 'Deleted'
                            ];

                            $current_status = $product_data['product_status'] ?? '';
                            foreach ($custom_statuses as $status_key => $status_label) {
                                $selected = ($status_key === $current_status) ? 'selected' : '';
                                echo '<option value="' . esc_attr($status_key) . '" ' . $selected . '>' . esc_html($status_label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <input id="upload_image_nonce" type="hidden" name="upload_image_nonce"
                    value="<?php echo wp_create_nonce('upload_image_nonce'); ?>">

                <div class="form-bottom center">
                    <button class="btn btn-primary btn-black-white btn-primary-black" type="submit" name="create_product" id="create-product">
                        <?php echo $edit_mode ? 'Update Product' : 'Preview and Publish'; ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
<style>
    input[readonly] {
        background-color: #f5f5f5;
        color: #666;
        cursor: not-allowed;
    }
    .top-form-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .top-form-title .woocommerce-breadcrumb {
        display: flex;
        align-items: center;
    }
    .top-form-title .woocommerce-breadcrumb .breacrum-icon,
    .top-form-title .woocommerce-breadcrumb a,
    .top-form-title .woocommerce-breadcrumb .breadcrumb-separator {
        display: inline-flex;
        align-items: center;
    }
    .top-form-title .actions {
        display: flex;
        align-items: center;
    }
    .woocommerce-breadcrumb.create-product{
        gap:5px;
    }
</style>

<?php get_footer(); ?>