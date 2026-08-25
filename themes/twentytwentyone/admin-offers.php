<?php
/**
 * Offer Admin Functions
 * Handles offer post type registration and admin pages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register Offer Post Type
add_action('init', function () {
    register_post_type('offer', [
        'labels' => [
            'name' => 'Offers',
            'singular_name' => 'Offer',
            'add_new' => 'Add New Offer',
            'add_new_item' => 'Add New Offer',
            'edit_item' => 'Edit Offer',
            'new_item' => 'New Offer',
            'view_item' => 'View Offer',
            'search_items' => 'Search Offers',
            'not_found' => 'No offers found',
            'not_found_in_trash' => 'No offers found in trash',
        ],
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
});

// Add Admin Menu
add_action('admin_menu', function () {
    if (!current_user_can('administrator')) return;

    add_menu_page(
        'Offers',
        'Offers',
        'manage_options',
        'offers',
        'render_offers_list_page',
        'dashicons-megaphone',
        25
    );

    add_submenu_page(
        'offers',
        'Create Offer',
        'Create Offer',
        'manage_options',
        'create-offer',
        'render_offer_create_page_redirect'
    );
    
    // Edit page is handled via query parameter
});

// Handle form submissions (admin)
add_action('admin_post_save_offer', 'handle_offer_save');
add_action('admin_post_update_offer', 'handle_offer_update');
add_action('admin_post_delete_offer', 'handle_offer_delete');

// AJAX: Export offers as CSV
add_action('wp_ajax_export_offers', 'handle_export_offers');

// AJAX: Import offers from CSV
add_action('wp_ajax_import_offers_csv', 'handle_import_offers_csv');

function handle_import_offers_csv() {
    if (!current_user_can('manage_options') && !current_user_can('edit_offers')) {
        wp_send_json_error('Unauthorized.');
    }

    check_ajax_referer('import_offers_nonce', 'nonce');

    if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('No file uploaded or upload error.');
    }

    $file = $_FILES['csv_file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        wp_send_json_error('Invalid file type. Please upload a CSV file.');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        wp_send_json_error('Could not read the uploaded file.');
    }

    // Strip BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $raw_headers = fgetcsv($handle);
    if (!$raw_headers) {
        fclose($handle);
        wp_send_json_error('Empty or invalid CSV file.');
    }

    $headers = array_map(fn($h) => strtolower(trim($h)), $raw_headers);

    $col_map = [
        'offer id'               => 'offer_id',
        'offer title'            => 'offer_title',
        'offer description'      => 'offer_description',
        'status'                 => 'status',
        'start date'             => 'start_date',
        'end date'               => 'end_date',
        'rank'                   => 'rank',
        'sku assigned'           => 'sku_assigned',
        'product names assigned' => 'product_names',
        'user type'              => 'user_type',
        'link'                   => 'link',
    ];

    $indices = [];
    foreach ($headers as $i => $header) {
        if (isset($col_map[$header])) {
            $indices[$col_map[$header]] = $i;
        }
    }

    $status_map = [
        'active'      => 'publish',
        'published'   => 'publish',
        'pending'     => 'pending',
        'deactivated' => 'draft',
        'draft'       => 'draft',
    ];

    $audience_map = [
        'consumer'            => 'consumer',
        'business'            => 'business',
        'consumer & business' => 'both',
        'both'                => 'both',
        'all users'           => 'all',
        'all'                 => 'all',
        'registered'          => 'registered',
        'guest'               => 'guest',
    ];

    $created  = 0;
    $updated  = 0;
    $errors   = [];
    $row_num  = 1;

    while (($row = fgetcsv($handle)) !== false) {
        $row_num++;

        $get = function ($key) use ($row, $indices) {
            if (!isset($indices[$key], $row[$indices[$key]])) return '';
            return trim($row[$indices[$key]]);
        };

        $csv_offer_id = (int) $get('offer_id');
        $title        = $get('offer_title');
        $description  = $get('offer_description');
        $status_raw   = strtolower($get('status'));
        $start_date   = $get('start_date');
        $end_date     = $get('end_date');
        $rank         = (int) $get('rank');
        $sku_raw      = $get('sku_assigned');
        $user_type    = strtolower($get('user_type'));
        $link         = $get('link');

        if (empty($title)) {
            $errors[] = "Row {$row_num}: Offer Title is required, skipped.";
            continue;
        }

        $post_status = $status_map[$status_raw] ?? 'draft';

        $post_data = [
            'post_type'    => 'offer',
            'post_title'   => sanitize_text_field($title),
            'post_content' => wp_kses_post($description),
            'post_status'  => $post_status,
        ];

        $is_update = false;
        if ($csv_offer_id > 0) {
            $existing = get_post($csv_offer_id);
            if ($existing && $existing->post_type === 'offer') {
                $post_data['ID'] = $csv_offer_id;
                $result          = wp_update_post($post_data, true);
                $is_update       = true;
            } else {
                $result = wp_insert_post($post_data, true);
            }
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            $errors[] = "Row {$row_num}: " . $result->get_error_message();
            continue;
        }

        $post_id = (int) $result;

        // Description
        update_post_meta($post_id, '_offer_description', wp_kses_post($description));

        // Start date (dd/mm/YYYY → YYYY-MM-DD HH:MM:SS)
        if (!empty($start_date) && $start_date !== '-') {
            if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $start_date, $m)) {
                $start_date = "{$m[3]}-{$m[1]}-{$m[2]} 16:00:00";
            }
            update_post_meta($post_id, '_offer_start_date', sanitize_text_field($start_date));
        }

        // End date
        if (!empty($end_date) && $end_date !== '-') {
            if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $end_date, $m)) {
                $end_date = "{$m[3]}-{$m[1]}-{$m[2]} 21:00:00";
            }
            update_post_meta($post_id, '_offer_end_date', sanitize_text_field($end_date));
        }

        // Rank
        if ($rank > 0) {
            update_post_meta($post_id, '_offer_rank', $rank);
        }

        // Audience
        $audience = $audience_map[$user_type] ?? '';
        if ($audience !== '') {
            update_post_meta($post_id, '_offer_audience', $audience);
        }

        // Link → showcase type + wp_offer_links table
        if (!empty($link)) {
            $clean_link = esc_url_raw($link);
            update_post_meta($post_id, '_offer_showcase_type', 'link');
            update_post_meta($post_id, '_offer_link', $clean_link);

            global $wpdb;
            $table  = "{$wpdb->prefix}offer_links";
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE offer_id = %d AND offer_link = %s LIMIT 1",
                $post_id,
                $clean_link
            ));
            if (!$exists) {
                $wpdb->insert(
                    $table,
                    [
                        'offer_id'   => $post_id,
                        'offer_link' => $clean_link,
                        'is_used'    => 0,
                        'status'     => 'available',
                        'used_by'    => null,
                        'used_at'    => null,
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%s', '%d', '%s', null, null, '%s']
                );
            }
        }

        // Products — resolve by SKU (skip "All SKUs" / "-")
        if (!empty($sku_raw) && $sku_raw !== '-') {
            if (strtolower($sku_raw) === 'all skus') {
                update_post_meta($post_id, '_offer_all_products', 'yes');
            } else {
                $skus        = array_filter(array_map('trim', explode(',', $sku_raw)));
                $product_ids = [];
                foreach ($skus as $sku) {
                    $pid = wc_get_product_id_by_sku($sku);
                    if ($pid) {
                        $product_ids[] = $pid;
                    }
                }
                if (!empty($product_ids)) {
                    update_post_meta($post_id, '_offer_products', array_unique($product_ids));
                }
            }
        }

        $is_update ? $updated++ : $created++;
    }

    fclose($handle);

    wp_send_json_success([
        'created' => $created,
        'updated' => $updated,
        'errors'  => $errors,
    ]);
}

function handle_export_offers() {
    if (!current_user_can('manage_options') && !current_user_can('edit_offers')) {
        wp_die('Unauthorized', 403);
    }

    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

    $args = [
        'post_type' => 'offer',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'orderby' => 'date',
        'order' => 'DESC',
    ];
    if ($search !== '') {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);
    $offers = $query->posts;

    $current_time = current_time('mysql');

    // Clear any buffered output (theme HTML, whitespace) so the CSV is the only content
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="offers-export-' . date('Y-m-d-His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }

    // BOM + header row written as a single plain string so no fputcsv quoting wraps the first field
    fwrite($out, "\xEF\xBB\xBFOffer ID,Offer Title,Offer Description,Status,Start date,End date,Rank,SKU Assigned,Product Names Assigned,User Type,Link\n");

    $rank = 1;
    foreach ($offers as $offer) {
        $meta = get_offer_meta($offer->ID);

        $start_date = $meta['start_date'] ?? '';
        $end_date = $meta['end_date'] ?? '';
        $is_always_on = $meta['always_on'] ?? false;
        if ($is_always_on) {
            $status = 'Active';
        } elseif (!empty($start_date) && $current_time < $start_date) {
            $status = 'Pending';
        } elseif (!empty($end_date) && $current_time > $end_date) {
            $status = 'Deactivated';
        } elseif ((empty($start_date) || $current_time >= $start_date) && (empty($end_date) || $current_time <= $end_date)) {
            $status = 'Active';
        } else {
            $status = 'Pending';
        }

        $start_formatted = $start_date ? date('d/m/Y', strtotime($start_date)) : '-';
        $end_formatted = $end_date ? date('d/m/Y', strtotime($end_date)) : '-';

        $products = $meta['products'] ?? [];
        $all_products = $meta['all_products'] ?? false;
        if ($all_products) {
            $skus = 'All SKUs';
            $names = 'All Products';
        } elseif (empty($products) || !is_array($products)) {
            $skus = '-';
            $names = '-';
        } else {
            $skus_arr = [];
            $names_arr = [];
            foreach ($products as $pid) {
                $product = wc_get_product($pid);
                if ($product) {
                    $s = $product->get_sku();
                    if ($s) $skus_arr[] = $s;
                    $names_arr[] = $product->get_name();
                }
            }
            $skus = !empty($skus_arr) ? implode(', ', $skus_arr) : '-';
            $names = !empty($names_arr) ? implode(', ', $names_arr) : '-';
        }

        $audience = $meta['audience'] ?? '';
        $audience_map = [
            'consumer' => 'Consumer',
            'business' => 'Business',
            'both' => 'Consumer & Business',
            'consumer_business' => 'Consumer & Business',
        ];
        $user_type = $audience !== '' ? ($audience_map[strtolower($audience)] ?? ucfirst($audience)) : '-';

        $description = isset($meta['description']) ? wp_trim_words($meta['description'], 20) : '';
        $link = $meta['link'] ?? '';

        fputcsv($out, [
            $offer->ID,
            $offer->post_title,
            $description,
            $status,
            $start_formatted,
            $end_formatted,
            $rank,
            $skus,
            $names,
            $user_type,
            $link,
        ]);
        $rank++;
    }

    fclose($out);
    exit;
}
// Handle form submissions (frontend)
add_action('template_redirect', 'handle_frontend_offer_form');
add_action('template_redirect', 'handle_frontend_offer_delete');
function handle_frontend_offer_form() {
    if (!isset($_POST['action']) || !in_array($_POST['action'], ['save_offer_frontend', 'update_offer_frontend'])) {
        return;
    }
    
    if (!current_user_can('administrator')) {
        wp_die('You do not have permission to perform this action.');
    }
    

    
    check_admin_referer('offer_form_frontend_nonce', 'offer_nonce');
    
    $action = sanitize_text_field($_POST['action']);
    $redirect_url = '';
    
    if ($action === 'save_offer_frontend') {
        $result = handle_offer_save_frontend();
        $redirect_url = $result['redirect'];
    } elseif ($action === 'update_offer_frontend') {
        $result = handle_offer_update_frontend();
        $redirect_url = $result['redirect'];
    }
    
    if ($redirect_url) {
        wp_redirect($redirect_url);
        exit;
    }
}

function handle_offer_save_frontend() {
    // Determine status based on button clicked, ignore status dropdown

    $post_status = isset($_POST['offer_status']); // Default to draft


    if (isset($_POST['save_and_publish'])) {
        $post_status = 'publish';
    } elseif (isset($_POST['save_as_draft'])) {
        $post_status = 'draft';
    } else {
        // Enter key case (no button clicked)
        $post_status = $_POST['offer_status'] ?? 'draft';
    }

    $post_data = [
        'post_type' => 'offer',
        'post_title' => sanitize_text_field($_POST['offer_title'] ?? ''),
        'post_content' => wp_kses_post($_POST['offer_description'] ?? ''),
        'post_status' => $post_status,
    ];

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        // Store error message in transient
        $transient_key = 'offer_message_' . get_current_user_id() . '_' . time();
        set_transient($transient_key, [
            'type' => 'error',
            'message' => 'Error creating offer: ' . $post_id->get_error_message()
        ], 30);
        
        $create_page = get_page_by_path('create-offer');
        if ($create_page) {
            $redirect = add_query_arg('offer_msg', $transient_key, get_permalink($create_page->ID));
        } else {
            $redirect = add_query_arg('offer_msg', $transient_key, wp_get_referer() ?: home_url());
        }
        return ['redirect' => $redirect];
    }

    // Save meta fields
    save_offer_meta($post_id, $_POST);

    // Store success message in transient (expires in 30 seconds)
    $transient_key = 'offer_message_' . get_current_user_id() . '_' . time();
    set_transient($transient_key, [
        'type' => 'success',
        'message' => 'Offer created successfully!',
        'offer_id' => $post_id,
        'is_new' => true
    ], 30);

    // Redirect to same page without query params
    $create_page = get_page_by_path('create-offer');
    if ($create_page) {
        $redirect = get_permalink($create_page->ID);
    } else {
        $redirect = wp_get_referer() ?: home_url();
    }
    // Add transient key to URL so we can retrieve the message
    $redirect = add_query_arg('offer_msg', $transient_key, $redirect);
    return ['redirect' => $redirect];
}

function handle_offer_update_frontend() {

    $offer_id = intval($_POST['offer_id'] ?? 0);
    if (!$offer_id) {
        // Store error message in transient
        $transient_key = 'offer_message_' . get_current_user_id() . '_' . time();
        set_transient($transient_key, [
            'type' => 'error',
            'message' => 'Invalid offer ID'
        ], 30);
        
        $edit_page = get_page_by_path('edit-offer');
        if ($edit_page) {
            $redirect = add_query_arg('offer_msg', $transient_key, get_permalink($edit_page->ID));
        } else {
            $redirect = add_query_arg('offer_msg', $transient_key, wp_get_referer() ?: home_url());
        }
        return ['redirect' => $redirect];
    }

    // Determine status based on button clicked, ignore status dropdown completely
    // "Update" and "Save and Publish" buttons always save as published
    // "Save as Draft" button always saves as draft
    $post_status = isset($_POST['offer_status']);
    if (isset($_POST['update']) || isset($_POST['save_and_publish'])) {
        $post_status = 'publish';
    } elseif (isset($_POST['save_as_draft'])) {
        $post_status = 'draft';
    } else {
        // Enter key case (no button clicked)
        $post_status = $_POST['offer_status'] ?? 'draft';
    }
    // The status dropdown value is completely ignored - button action takes precedence

    $post_data = [
        'ID' => $offer_id,
        'post_title' => sanitize_text_field($_POST['offer_title'] ?? ''),
        'post_content' => wp_kses_post($_POST['offer_description'] ?? ''),
        'post_status' => $post_status,
    ];

    wp_update_post($post_data);

    // Save meta fields
    save_offer_meta($offer_id, $_POST);

    // Store success message in transient (expires in 30 seconds)
    $transient_key = 'offer_message_' . get_current_user_id() . '_' . time();
    set_transient($transient_key, [
        'type' => 'success',
        'message' => 'Offer updated successfully!',
        'offer_id' => $offer_id,
        'is_new' => false
    ], 30);

    // Redirect to same page without query params
    $edit_page = get_page_by_path('edit-offer');
    if ($edit_page) {
        $redirect = add_query_arg('offer_id', $offer_id, get_permalink($edit_page->ID));
    } else {
        $redirect = wp_get_referer() ?: home_url();
    }
    // Add transient key to URL so we can retrieve the message
    $redirect = add_query_arg('offer_msg', $transient_key, $redirect);
    return ['redirect' => $redirect];
}

// Handle offer deletion (admin)
function handle_offer_delete() {
    if (!current_user_can('administrator')) {
        wp_die('You do not have permission to perform this action.');
    }

    check_admin_referer('delete_offer_nonce');

    $offer_id = intval($_GET['offer_id'] ?? 0);
    if (!$offer_id) {
        wp_die('Invalid offer ID');
    }

    $offer = get_post($offer_id);
    if (!$offer || $offer->post_type !== 'offer') {
        wp_die('Offer not found');
    }

    // Delete the offer
    $deleted = wp_delete_post($offer_id, true);

    if ($deleted) {
        $redirect = admin_url('admin.php?page=offers&offer_deleted=1');
    } else {
        $redirect = admin_url('admin.php?page=offers&offer_error=' . urlencode('Failed to delete offer'));
    }

    wp_redirect($redirect);
    exit;
}

// Handle offer deletion (frontend)
function handle_frontend_offer_delete() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'delete_offer_frontend') {
        return;
    }
    
    if (!current_user_can('administrator')) {
        wp_die('You do not have permission to perform this action.');
    }
    
    check_admin_referer('delete_offer_frontend_nonce', 'nonce');
    
    $offer_id = intval($_GET['offer_id'] ?? 0);
    if (!$offer_id) {
        $redirect = add_query_arg('offer_error', urlencode('Invalid offer ID'), wp_get_referer());
        wp_redirect($redirect);
        exit;
    }

    $offer = get_post($offer_id);
    if (!$offer || $offer->post_type !== 'offer') {
        $redirect = add_query_arg('offer_error', urlencode('Offer not found'), wp_get_referer());
        wp_redirect($redirect);
        exit;
    }

    // Delete the offer
    $deleted = wp_delete_post($offer_id, true);

    if ($deleted) {
        $offers_page = get_page_by_path('offers');
        if ($offers_page) {
            $redirect = add_query_arg('offer_deleted', '1', get_permalink($offers_page->ID));
        } else {
            $redirect = add_query_arg('offer_deleted', '1', wp_get_referer());
        }
    } else {
        $redirect = add_query_arg('offer_error', urlencode('Failed to delete offer'), wp_get_referer());
    }

    wp_redirect($redirect);
    exit;
}

function handle_offer_save() {


    if (!current_user_can('administrator')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('offer_form_nonce');

    // Determine status based on button clicked, ignore status dropdown
    $post_status = isset($_POST['offer_status']);

    if (isset($_POST['save_and_publish'])) {
        $post_status = 'publish';
    } elseif (isset($_POST['save_as_draft'])) {
        $post_status = 'draft';
    } else {
        // Enter key case (no button clicked)
        $post_status = $_POST['offer_status'] ?? 'draft';
    }

    $post_data = [
        'post_type' => 'offer',
        'post_title' => sanitize_text_field($_POST['offer_title'] ?? ''),
        'post_content' => wp_kses_post($_POST['offer_description'] ?? ''),
        'post_status' => $post_status,
    ];

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        wp_die('Error creating offer: ' . $post_id->get_error_message());
    }

    // Save meta fields
    save_offer_meta($post_id, $_POST);

    $redirect_url = admin_url('admin.php?page=offers&action=edit&offer_id=' . $post_id);
    
    wp_redirect($redirect_url);
    exit;
}

function handle_offer_update() {
    if (!current_user_can('administrator')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('offer_form_nonce');

    $offer_id = intval($_POST['offer_id'] ?? 0);
    if (!$offer_id) {
        wp_die('Invalid offer ID');
    }

    // Determine status based on button clicked, ignore status dropdown completely
    // "Update" and "Save and Publish" buttons always save as published
    $post_status = isset($_POST['offer_status']);
    if (isset($_POST['update']) || isset($_POST['save_and_publish'])) {
        $post_status = 'publish';
    } elseif (isset($_POST['save_as_draft'])) {
        $post_status = 'draft';
    } else {
        // Enter key case (no button clicked)
        $post_status = $_POST['offer_status'] ?? 'draft';
    }
    // The status dropdown value is completely ignored - button action takes precedence

    $post_data = [
        'ID' => $offer_id,
        'post_title' => sanitize_text_field($_POST['offer_title'] ?? ''),
        'post_content' => wp_kses_post($_POST['offer_description'] ?? ''),
        'post_status' => $post_status,
    ];

    wp_update_post($post_data);

    // Save meta fields
    save_offer_meta($offer_id, $_POST);

    wp_redirect(admin_url('admin.php?page=offers&action=edit&offer_id=' . $offer_id));
    exit;
}

function save_offer_meta($post_id, $data) {
    // Offer Description (stored in post_content, but keeping for meta too)
    update_post_meta($post_id, '_offer_description', wp_kses_post($data['offer_description'] ?? ''));
    
    // Offer Image - Handle base64 data or existing image ID
    if (!empty($data['offer_image_data'])) {
        // Create attachment from base64 data
        $base64_data = trim($data['offer_image_data']);
        $filename = sanitize_file_name($data['offer_image_filename'] ?? 'offer-image.png');
        
        // Verify base64 data is valid (must start with data:image or be valid base64)
        if (!empty($base64_data) && (strpos($base64_data, 'data:image') === 0 || strlen($base64_data) > 100)) {
            // Clean and validate base64 data
            $base64_clean = preg_replace('#^data:image/\w+;base64,#i', '', $base64_data);
            $base64_clean = preg_replace('/\s+/', '', $base64_clean);
            
            // Check if base64 is properly padded (base64 strings should be divisible by 4)
            $padding = strlen($base64_clean) % 4;
            if ($padding > 0) {
                $base64_clean .= str_repeat('=', 4 - $padding);
            }
            
            // Reconstruct base64 data with proper padding
            if (strpos($base64_data, 'data:image') === 0) {
                $mime_match = preg_match('#^data:image/(\w+);base64,#i', $base64_data, $mime_matches);
                if ($mime_match) {
                    $base64_data = 'data:image/' . $mime_matches[1] . ';base64,' . $base64_clean;
                }
            } else {
                $base64_data = 'data:image/png;base64,' . $base64_clean;
            }
            
            $image_id = create_attachment_from_base64($base64_data, $filename, $post_id);
            if ($image_id && !is_wp_error($image_id) && is_numeric($image_id)) {
                // Verify the attachment was created successfully
                $attachment = get_post($image_id);
                if ($attachment && $attachment->post_type === 'attachment') {
                    update_post_meta($post_id, '_offer_image_id', intval($image_id));
                }
            }
        }
    } elseif (!empty($data['offer_image_id'])) {
        // Use existing image ID - validate it exists
        $image_id = intval($data['offer_image_id']);
        $attachment = get_post($image_id);
        if ($attachment && $attachment->post_type === 'attachment') {
            update_post_meta($post_id, '_offer_image_id', $image_id);
        } else {
            // Invalid image ID, clear it
            delete_post_meta($post_id, '_offer_image_id');
        }
    } else {
        // If no image data and no image ID, check if we should clear existing image
        // (This handles the case when user removes the image)
        if (isset($data['offer_image_id']) && empty($data['offer_image_id'])) {
            delete_post_meta($post_id, '_offer_image_id');
        }
    }
    
    // Offer Showcase Type
    update_post_meta($post_id, '_offer_showcase_type', sanitize_text_field($data['offer_showcase_type'] ?? ''));
    
    // Promo Code
    update_post_meta($post_id, '_offer_promo_code', sanitize_text_field($data['offer_promo_code'] ?? ''));
    
    // Link — handle single link OR bulk links array
    $bulk_links_raw = isset($data['offer_bulk_links']) && is_array($data['offer_bulk_links'])
        ? array_values(array_filter(array_map('esc_url_raw', $data['offer_bulk_links'])))
        : [];

    if (!empty($bulk_links_raw)) {
        // Bulk mode: store serialized array in _offer_link meta
        update_post_meta($post_id, '_offer_link', serialize($bulk_links_raw));
    } else {
        // Single link mode
        update_post_meta($post_id, '_offer_link', esc_url_raw($data['offer_link'] ?? ''));
    }
    
    // Terms & Conditions
    update_post_meta($post_id, '_offer_terms', wp_kses_post($data['offer_terms'] ?? ''));
    
    // Offer Flag
    update_post_meta($post_id, '_offer_flag', sanitize_text_field($data['offer_flag'] ?? ''));
    
    // Offer Tags - Handle both string (comma-separated) and array formats
    $tags = [];
    if (isset($data['offer_tags'])) {
        if (is_array($data['offer_tags'])) {
            $tags = array_map('sanitize_text_field', $data['offer_tags']);
        } else {
            // Handle comma-separated string
            $tags_string = sanitize_text_field($data['offer_tags']);
            if (!empty($tags_string)) {
                $tags = array_map('trim', explode(',', $tags_string));
                $tags = array_filter($tags); // Remove empty values
                $tags = array_map('sanitize_text_field', $tags);
            }
        }
    }
    update_post_meta($post_id, '_offer_tags', $tags);
    
    // Start Date - Convert from MM/DD/YYYY to YYYY-MM-DD
    if (!empty($data['offer_start_date'])) {
        $start_date = sanitize_text_field($data['offer_start_date']);
        $start_time = sanitize_text_field($data['offer_start_time'] ?? '16:00');
        
        // Convert date format if needed
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $start_date, $matches)) {
            $start_date = $matches[3] . '-' . $matches[1] . '-' . $matches[2];
        }
        
        $start_datetime = $start_date . ' ' . $start_time;
        update_post_meta($post_id, '_offer_start_date', $start_datetime);
    }
    
    // End Date - Convert from MM/DD/YYYY to YYYY-MM-DD
    if (!empty($data['offer_end_date'])) {
        $end_date = sanitize_text_field($data['offer_end_date']);
        $end_time = sanitize_text_field($data['offer_end_time'] ?? '21:00');
        
        // Convert date format if needed
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $end_date, $matches)) {
            $end_date = $matches[3] . '-' . $matches[1] . '-' . $matches[2];
        }
        
        $end_datetime = $end_date . ' ' . $end_time;
        update_post_meta($post_id, '_offer_end_date', $end_datetime);
    }
    
    // Always On
    update_post_meta($post_id, '_offer_always_on', isset($data['offer_always_on']) ? 'yes' : 'no');
    
    // Audience
    update_post_meta($post_id, '_offer_audience', sanitize_text_field($data['offer_audience'] ?? ''));

    // User Roles — empty array means no restriction (all roles allowed)
    $user_roles = isset($data['offer_user_roles']) && is_array($data['offer_user_roles'])
        ? array_map('sanitize_text_field', $data['offer_user_roles'])
        : [];
    update_post_meta($post_id, '_offer_user_roles', $user_roles);
    
    // Selected Products - Handle both array and single value
    $new_products = [];
    if (isset($data['offer_products'])) {
        if (is_array($data['offer_products'])) {
            $new_products = array_map('intval', $data['offer_products']);
            $new_products = array_filter($new_products); // Remove empty/zero values
            $new_products = array_unique($new_products); // Remove duplicate product IDs
            $new_products = array_values($new_products); // Re-index array
        } elseif (!empty($data['offer_products'])) {
            // Handle single product ID
            $new_products = [intval($data['offer_products'])];
        }
    }
    
    // Get previous products to update product meta
    $old_products = get_post_meta($post_id, '_offer_products', true) ?: [];
    if (!is_array($old_products)) {
        $old_products = [];
    }
    
    // Update offer meta
    update_post_meta($post_id, '_offer_products', $new_products);
    
    // Update product meta - remove offer from old products, add to new products
    $products_to_remove = array_diff($old_products, $new_products);
    $products_to_add = array_diff($new_products, $old_products);
    
    // Remove offer ID from products that are no longer in this offer
    foreach ($products_to_remove as $product_id) {
        $product_offers = get_post_meta($product_id, '_product_offers', true) ?: [];
        if (!is_array($product_offers)) {
            $product_offers = [];
        }
        $product_offers = array_diff($product_offers, [$post_id]);
        $product_offers = array_values(array_filter($product_offers)); // Re-index array
        if (empty($product_offers)) {
            delete_post_meta($product_id, '_product_offers');
        } else {
            update_post_meta($product_id, '_product_offers', $product_offers);
        }
    }
    
    // Add offer ID to products that are newly added to this offer
    foreach ($products_to_add as $product_id) {
        $product_offers = get_post_meta($product_id, '_product_offers', true) ?: [];
        if (!is_array($product_offers)) {
            $product_offers = [];
        }
        if (!in_array($post_id, $product_offers)) {
            $product_offers[] = $post_id;
            $product_offers = array_values(array_unique($product_offers)); // Remove duplicates and re-index
            update_post_meta($product_id, '_product_offers', $product_offers);
        }
    }
    
    // Available for all products
    update_post_meta($post_id, '_offer_all_products', isset($data['offer_all_products']) ? 'yes' : 'no');

    // Sync offer links to wp_offer_links table
    $showcase_type = sanitize_text_field($data['offer_showcase_type'] ?? '');

    if ($showcase_type === 'link') {
        global $wpdb;
        $table = "{$wpdb->prefix}offer_links";

        // Build the list of links to sync — bulk takes priority over single
        $links_to_sync = !empty($bulk_links_raw)
            ? $bulk_links_raw
            : array_filter([esc_url_raw($data['offer_link'] ?? '')]);

        foreach ($links_to_sync as $link) {
            if (empty($link)) continue;

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE offer_id = %d AND offer_link = %s LIMIT 1",
                $post_id,
                $link
            ));

            if (!$exists) {
                // New link — reset low-count notification so admin is alerted if needed
                delete_option('bhn_offer_links_low_notified_count');

                $wpdb->insert(
                    $table,
                    [
                        'offer_id'   => $post_id,
                        'offer_link' => $link,
                        'is_used'    => 0,
                        'status'     => 'available',
                        'used_by'    => null,
                        'used_at'    => null,
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%s', '%d', '%s', null, null, '%s']
                );
            }
        }
    }
}

function get_offer_meta($post_id) {
    $image_id = get_post_meta($post_id, '_offer_image_id', true);
    
    // Validate that the image attachment actually exists
    if (!empty($image_id)) {
        $attachment = get_post($image_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            // Image ID exists but attachment doesn't, clear it
            delete_post_meta($post_id, '_offer_image_id');
            $image_id = '';
        }
    }
    
    return [
        'description' => get_post_meta($post_id, '_offer_description', true) ?: get_post_field('post_content', $post_id),
        'image_id' => $image_id,
        'showcase_type' => get_post_meta($post_id, '_offer_showcase_type', true),
        'promo_code' => get_post_meta($post_id, '_offer_promo_code', true),
        'link'       => (function() use ($post_id) {
            $raw = get_post_meta($post_id, '_offer_link', true);
            return (is_serialized($raw) || is_array(maybe_unserialize($raw))) ? '' : $raw;
        })(),
        'bulk_links' => (function() use ($post_id) {
            $raw = get_post_meta($post_id, '_offer_link', true);
            if (!is_string($raw) || empty($raw)) return [];
            $decoded = maybe_unserialize($raw);
            return is_array($decoded) ? array_values(array_filter($decoded)) : [];
        })(),
        'terms' => get_post_meta($post_id, '_offer_terms', true),
        'flag' => get_post_meta($post_id, '_offer_flag', true),
        'tags' => get_post_meta($post_id, '_offer_tags', true) ?: [],
        'start_date' => get_post_meta($post_id, '_offer_start_date', true),
        'end_date' => get_post_meta($post_id, '_offer_end_date', true),
        'always_on' => get_post_meta($post_id, '_offer_always_on', true) === 'yes',
        'audience'   => get_post_meta($post_id, '_offer_audience', true),
        'user_roles' => get_post_meta($post_id, '_offer_user_roles', true) ?: [],
        'products'   => get_post_meta($post_id, '_offer_products', true) ?: [],
        'all_products' => get_post_meta($post_id, '_offer_all_products', true) === 'yes',
    ];
}

// Render Offers List Page
function render_offers_list_page() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    $offer_id = isset($_GET['offer_id']) ? intval($_GET['offer_id']) : 0;

    // If edit action, redirect to frontend edit page
    if ($action === 'edit' && $offer_id) {
        $edit_page = get_page_by_path('edit-offer');
        if ($edit_page) {
            $edit_url = add_query_arg('offer_id', $offer_id, get_permalink($edit_page->ID));
            wp_redirect($edit_url);
            exit;
        } else {
            // Fallback to admin if frontend page doesn't exist
            render_offer_edit_page($offer_id);
        }
    } else {
        render_offers_list();
    }
}

function render_offers_list() {
    // Include the template file
    $template_path = get_template_directory() . '/admin-templates/offers-list.php';
    if (file_exists($template_path)) {
        include $template_path;
    } else {
        // Fallback to old implementation if template doesn't exist
        $offers = get_posts([
            'post_type' => 'offer',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Offers</h1>
            <?php
            // Get frontend create page
            $create_page = get_page_by_path('create-offer');
            if ($create_page) {
                echo '<a href="' . esc_url(get_permalink($create_page->ID)) . '" class="page-title-action">Add New</a>';
            } else {
                echo '<a href="' . admin_url('admin.php?page=create-offer') . '" class="page-title-action">Add New</a>';
            }
            ?>
            <hr class="wp-header-end">
            
            <?php
            // Show success/error messages
            if (isset($_GET['offer_deleted']) && $_GET['offer_deleted'] == '1') {
                echo '<div class="notice notice-success is-dismissible"><p>Offer deleted successfully.</p></div>';
            }
            if (isset($_GET['offer_error'])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['offer_error'])) . '</p></div>';
            }
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($offers)): ?>
                        <tr>
                            <td colspan="5">No offers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($offers as $offer): ?>
                            <tr>
                                <td><?php echo $offer->ID; ?></td>
                                <td><strong><?php echo esc_html($offer->post_title); ?></strong></td>
                                <td><?php echo esc_html($offer->post_status); ?></td>
                                <td><?php echo get_the_date('', $offer->ID); ?></td>
                                <td>
                                    <?php
                                    // Get frontend edit page
                                    $edit_page = get_page_by_path('edit-offer');
                                    if ($edit_page) {
                                        $edit_url = add_query_arg('offer_id', $offer->ID, get_permalink($edit_page->ID));
                                        echo '<a href="' . esc_url($edit_url) . '">Edit</a> | ';
                                    } else {
                                        // Fallback to admin if frontend page doesn't exist
                                        echo '<a href="' . admin_url('admin.php?page=offers&action=edit&offer_id=' . $offer->ID) . '">Edit</a> | ';
                                    }
                                    
                                    // Delete link
                                    $delete_url = admin_url('admin-post.php?action=delete_offer&offer_id=' . $offer->ID);
                                    $delete_url = wp_nonce_url($delete_url, 'delete_offer_nonce');
                                    echo '<a href="' . esc_url($delete_url) . '" class="delete-offer-link" data-offer-id="' . esc_attr($offer->ID) . '" data-offer-title="' . esc_attr($offer->post_title) . '" onclick="return confirm(\'Are you sure you want to delete the offer \\\'' . esc_js($offer->post_title) . '\\\'? This action cannot be undone.\');">Delete</a>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Redirect to frontend create page
function render_offer_create_page_redirect() {
    $create_page = get_page_by_path('create-offer');
    if ($create_page) {
        wp_redirect(get_permalink($create_page->ID));
        exit;
    } else {
        // Fallback to admin if frontend page doesn't exist
        render_offer_create_page();
    }
}

// Render Create Offer Page (Admin fallback)
function render_offer_create_page() {
    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
    // wp_enqueue_script('offer-admin', get_template_directory_uri() . '/assets/js/offer-admin.js', ['jquery', 'jquery-ui-datepicker'], '1.0', true);
    wp_enqueue_style('offer-admin', get_template_directory_uri() . '/assets/css/offer-admin.css', [], '1.0');
    
    include get_template_directory() . '/admin-templates/offer-form.php';
}

// Render Edit Offer Page
function render_offer_edit_page($offer_id) {
    $offer = get_post($offer_id);
    if (!$offer || $offer->post_type !== 'offer') {
        wp_die('Offer not found');
    }

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
    // wp_enqueue_script('offer-admin', get_template_directory_uri() . '/assets/js/offer-admin.js', ['jquery', 'jquery-ui-datepicker'], '1.0', true);
    wp_enqueue_style('offer-admin', get_template_directory_uri() . '/assets/css/offer-admin.css', [], '1.0');
    
    $meta = get_offer_meta($offer_id);
    
    // Parse dates - Convert from YYYY-MM-DD to MM/DD/YYYY
    if (!empty($meta['start_date'])) {
        $start_parts = explode(' ', $meta['start_date']);
        $date_part = $start_parts[0];
        // Convert YYYY-MM-DD to MM/DD/YYYY
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $date_part, $matches)) {
            $date_part = $matches[2] . '/' . $matches[3] . '/' . $matches[1];
        }
        $meta['start_date_only'] = $date_part;
        $meta['start_time_only'] = isset($start_parts[1]) ? $start_parts[1] : '16:00';
    } else {
        $meta['start_date_only'] = '';
        $meta['start_time_only'] = '16:00';
    }
    if (!empty($meta['end_date'])) {
        $end_parts = explode(' ', $meta['end_date']);
        $date_part = $end_parts[0];
        // Convert YYYY-MM-DD to MM/DD/YYYY
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $date_part, $matches)) {
            $date_part = $matches[2] . '/' . $matches[3] . '/' . $matches[1];
        }
        $meta['end_date_only'] = $date_part;
        $meta['end_time_only'] = isset($end_parts[1]) ? $end_parts[1] : '21:00';
    } else {
        $meta['end_date_only'] = '';
        $meta['end_time_only'] = '21:00';
    }
    
    // Make variables available to template
    $GLOBALS['offer_id'] = $offer_id;
    $GLOBALS['offer'] = $offer;
    $GLOBALS['meta'] = $meta;
    
    include get_template_directory() . '/admin-templates/offer-form.php';
}

// AJAX handler for product search
add_action('wp_ajax_search_products', 'ajax_search_products');
function ajax_search_products() {
    check_ajax_referer('offer_ajax_nonce', 'nonce');
    
    $search = sanitize_text_field($_GET['search'] ?? '');
    $products = [];
    
    // If search is empty, return recent products (limit to 50)
    if (empty($search)) {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 50,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ];
    } else {
        // Search only in product title and SKU, not in content/excerpt
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 50,
            'post_status' => 'publish',
            // Don't use 's' parameter - we'll search manually
        ];
        
        // Add custom search filter for title and SKU only
        add_filter('posts_join', function($join) {
            global $wpdb;
            $join .= " LEFT JOIN {$wpdb->postmeta} pm_sku ON ({$wpdb->posts}.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku')";
            return $join;
        });
        
        add_filter('posts_where', function($where) use ($search) {
            global $wpdb;
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            // Search only in post_title and SKU, not in post_content or post_excerpt
            $where .= $wpdb->prepare(
                " AND (({$wpdb->posts}.post_title LIKE %s) OR (pm_sku.meta_value LIKE %s))",
                $search_like,
                $search_like
            );
            return $where;
        });
    }
    
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            if ($product) {
                $products[] = [
                    'id' => get_the_ID(),
                    'name' => get_the_title(),
                    'sku' => $product->get_sku() ?: 'N/A',
                    'price' => $product->get_price() ?: '0',
                ];
            }
        }
        wp_reset_postdata();
    }
    
    // Remove filters
    remove_all_filters('posts_join');
    remove_all_filters('posts_where');
    
    wp_send_json_success($products);
}

// AJAX handler for image upload
add_action('wp_ajax_upload_offer_image', 'ajax_upload_offer_image');
add_action('wp_ajax_nopriv_upload_offer_image', 'ajax_upload_offer_image');
function ajax_upload_offer_image() {
    // Check nonce (can be in POST or GET for FormData)
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_GET['nonce']) ? $_GET['nonce'] : '');
    if (empty($nonce) || !wp_verify_nonce($nonce, 'offer_ajax_nonce')) {
        wp_send_json_error(['message' => 'Invalid security token.']);
        return;
    }
    
    // Check user permissions
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'You do not have permission to upload images.']);
        return;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'No file uploaded.';
        if (isset($_FILES['file']['error'])) {
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_msg = 'File size exceeds limit.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_msg = 'File was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_msg = 'No file was uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_msg = 'Missing temporary folder.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_msg = 'Failed to write file to disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_msg = 'File upload stopped by extension.';
                    break;
            }
        }
        wp_send_json_error(['message' => $error_msg]);
        return;
    }
    
    // Require WordPress file handling functions
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    // Get the original filename before upload
    $original_filename = sanitize_file_name($_FILES['file']['name']);
    
    // Check if an attachment with the same filename already exists
    $existing_attachment_id = find_existing_attachment_by_filename($original_filename);
    if ($existing_attachment_id) {
        // Return existing attachment ID instead of creating a duplicate
        wp_send_json_success([
            'id' => $existing_attachment_id,
            'url' => wp_get_attachment_url($existing_attachment_id),
            'message' => 'Image already exists in media library. Using existing image.'
        ]);
        return;
    }
    
    // Use WordPress upload handler
    $upload_overrides = ['test_form' => false];
    $uploaded_file = wp_handle_upload($_FILES['file'], $upload_overrides);
    
    if (isset($uploaded_file['error'])) {
        wp_send_json_error(['message' => $uploaded_file['error']]);
        return;
    }
    
    // Create attachment
    $attachment = [
        'post_mime_type' => $uploaded_file['type'],
        'post_title' => sanitize_file_name(pathinfo($uploaded_file['file'], PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit',
    ];
    
    $attach_id = wp_insert_attachment($attachment, $uploaded_file['file']);
    
    if (is_wp_error($attach_id)) {
        @unlink($uploaded_file['file']); // Clean up file
        wp_send_json_error(['message' => 'Failed to create attachment: ' . $attach_id->get_error_message()]);
        return;
    }
    
    // Generate attachment metadata
    $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    wp_send_json_success([
        'id' => $attach_id,
        'url' => wp_get_attachment_url($attach_id),
        'message' => 'Image uploaded successfully.'
    ]);
}

// AJAX handler for image upload from URL
add_action('wp_ajax_upload_offer_image_url', 'ajax_upload_offer_image_url');
function ajax_upload_offer_image_url() {
    check_ajax_referer('offer_ajax_nonce', 'nonce');
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'You do not have permission to upload images.']);
        return;
    }
    
    $image_url = esc_url_raw($_POST['image_url'] ?? '');
    
    if (empty($image_url)) {
        wp_send_json_error(['message' => 'No image URL provided.']);
        return;
    }
    
    // Validate URL
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        wp_send_json_error(['message' => 'Invalid URL format.']);
        return;
    }
    
    // Check if URL is an image
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
    $url_path = parse_url($image_url, PHP_URL_PATH);
    $extension = strtolower(pathinfo($url_path, PATHINFO_EXTENSION));
    
    if (!in_array($extension, $image_extensions)) {
        wp_send_json_error(['message' => 'URL does not appear to be an image.']);
        return;
    }
    
    // Get the original filename from URL
    $original_filename = basename($url_path);
    
    // Check if an attachment with the same filename already exists
    $existing_attachment_id = find_existing_attachment_by_filename($original_filename);
    if ($existing_attachment_id) {
        // Return existing attachment ID instead of creating a duplicate
        wp_send_json_success([
            'id' => $existing_attachment_id,
            'url' => wp_get_attachment_url($existing_attachment_id),
            'message' => 'Image already exists in media library. Using existing image.'
        ]);
        return;
    }
    
    // Download the image
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $tmp = download_url($image_url);
    
    if (is_wp_error($tmp)) {
        wp_send_json_error(['message' => 'Error downloading image: ' . $tmp->get_error_message()]);
        return;
    }
    
    // Validate file type
    $file_type = wp_check_filetype(basename($url_path));
    $allowed_types = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
    
    if (!in_array($file_type['type'], $allowed_types)) {
        @unlink($tmp);
        wp_send_json_error(['message' => 'Invalid file type. Please use SVG, PNG, JPG, GIF or WEBP files only.']);
        return;
    }
    
    // Check file size (3MB)
    $file_size = filesize($tmp);
    if ($file_size > 3145728) {
        @unlink($tmp);
        wp_send_json_error(['message' => 'File size exceeds 3MB limit.']);
        return;
    }
    
    // Move file to uploads directory
    $file_array = [
        'name' => basename($url_path),
        'tmp_name' => $tmp
    ];
    
    $upload = wp_handle_sideload($file_array, ['test_form' => false]);
    
    if (isset($upload['error'])) {
        @unlink($tmp);
        wp_send_json_error(['message' => $upload['error']]);
        return;
    }
    
    // Create attachment
    $attachment = [
        'post_mime_type' => $upload['type'],
        'post_title' => sanitize_file_name(pathinfo($url_path, PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit'
    ];
    
    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    
    if (is_wp_error($attach_id)) {
        @unlink($upload['file']);
        wp_send_json_error(['message' => 'Error creating attachment.']);
        return;
    }
    
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    $image_url_result = wp_get_attachment_image_url($attach_id, 'full');
    
    wp_send_json_success([
        'id' => $attach_id,
        'url' => $image_url_result
    ]);
}

/**
 * Get all offers that contain a specific product
 * 
 * @param int $product_id Product ID
 * @return array Array of offer post objects
 */
function get_offers_for_product($product_id) {
    $offer_ids = get_post_meta($product_id, '_product_offers', true) ?: [];
    if (empty($offer_ids) || !is_array($offer_ids)) {
        return [];
    }
    
    $offers = get_posts([
        'post_type' => 'offer',
        'post__in' => array_map('intval', $offer_ids),
        'posts_per_page' => -1,
        'post_status' => 'any',
    ]);
    
    return $offers;
}

/**
 * Get all products in a specific offer
 * 
 * @param int $offer_id Offer ID
 * @return array Array of product IDs
 */
function get_products_in_offer($offer_id) {
    return get_post_meta($offer_id, '_offer_products', true) ?: [];
}

/**
 * Check if a product is in any offer
 * 
 * @param int $product_id Product ID
 * @return bool True if product is in at least one offer
 */
function is_product_in_offer($product_id) {
    $offers = get_post_meta($product_id, '_product_offers', true);
    return !empty($offers) && is_array($offers) && count($offers) > 0;
}

/**
 * Clean up product offers meta when an offer is deleted
 */
add_action('before_delete_post', 'cleanup_offer_product_meta');
function cleanup_offer_product_meta($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'offer') {
        return;
    }
    
    // Get all products in this offer
    $products = get_post_meta($post_id, '_offer_products', true) ?: [];
    if (empty($products) || !is_array($products)) {
        return;
    }
    
    // Remove this offer ID from all products
    foreach ($products as $product_id) {
        $product_offers = get_post_meta($product_id, '_product_offers', true) ?: [];
        if (!is_array($product_offers)) {
            $product_offers = [];
        }
        $product_offers = array_diff($product_offers, [$post_id]);
        $product_offers = array_values(array_filter($product_offers)); // Re-index array
        if (empty($product_offers)) {
            delete_post_meta($product_id, '_product_offers');
        } else {
            update_post_meta($product_id, '_product_offers', $product_offers);
        }
    }
}

/**
 * Find existing attachment by filename
 * This checks if an attachment with the same filename already exists in the media library
 * 
 * @param string $filename The filename to search for (e.g., 'test.png')
 * @return int|false Attachment ID if found, false otherwise
 */
function find_existing_attachment_by_filename($filename) {
    global $wpdb;
    
    if (empty($filename)) {
        return false;
    }
    
    // Sanitize filename for comparison
    $filename = sanitize_file_name($filename);
    $filename_lower = strtolower($filename);
    
    // Search for attachments where _wp_attached_file ends with the filename
    // WordPress stores paths like '2025/01/test.png', so we check if it ends with '/filename' or is exactly 'filename'
    // We use LIKE with a trailing pattern to match the exact filename at the end of the path
    $attachment_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
            WHERE p.post_type = 'attachment'
            AND (LOWER(pm.meta_value) = %s OR LOWER(pm.meta_value) LIKE %s)
            ORDER BY p.ID DESC
            LIMIT 1",
            $filename_lower,
            '%/' . $wpdb->esc_like($filename_lower)
        )
    );
    
    if ($attachment_id) {
        // Verify the attachment still exists
        $attachment = get_post($attachment_id);
        if ($attachment && $attachment->post_type === 'attachment') {
            return (int) $attachment_id;
        }
    }
    
    return false;
}

/**
 * Find existing attachment by file content hash
 * This checks if the same image content already exists in the media library
 * 
 * @param string $image_data The binary image data
 * @param string $mime_type The MIME type of the image
 * @return int|false Attachment ID if found, false otherwise
 */
function find_existing_attachment_by_content($image_data, $mime_type = '') {
    global $wpdb;
    
    if (empty($image_data) || strlen($image_data) < 100) {
        return false;
    }
    
    // Calculate MD5 hash of the image content
    $content_hash = md5($image_data);
    $file_size = strlen($image_data);
    
    // First, check if we've stored a hash for this content in a custom meta field
    // This is a quick check for attachments we've created with this system
    $attachment_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT pm.post_id 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_offer_image_hash'
            AND pm.meta_value = %s
            AND p.post_type = 'attachment'
            ORDER BY p.ID DESC
            LIMIT 1",
            $content_hash
        )
    );
    
    // If not found by stored hash, check existing attachments by comparing file content
    if (!$attachment_id) {
        // Get all image attachments with matching MIME type and similar file size
        // We check file size first as a quick filter, then compare hashes
        $attachments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, pm.meta_value as file_path
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type = %s
                ORDER BY p.ID DESC
                LIMIT 50",
                $mime_type ?: 'image/png'
            )
        );
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            if ($file_path && file_exists($file_path)) {
                // Quick check: compare file sizes first
                $existing_file_size = filesize($file_path);
                if ($existing_file_size === $file_size) {
                    // File sizes match, compare content hash
                    $existing_hash = md5_file($file_path);
                    if ($existing_hash === $content_hash) {
                        // Content matches! Store the hash for future quick lookups
                        update_post_meta($attachment->ID, '_offer_image_hash', $content_hash);
                        return (int) $attachment->ID;
                    }
                }
            }
        }
    } else {
        // Found by stored hash, verify file still exists
        $file_path = get_attached_file($attachment_id);
        if ($file_path && file_exists($file_path)) {
            return (int) $attachment_id;
        }
    }
    
    return false;
}

/**
 * Create WordPress attachment from base64 image data
 */
function create_attachment_from_base64($base64_data, $filename, $post_id = 0) {
    if (empty($base64_data)) {
        return false;
    }
    
    // Clean and validate base64 data
    $base64_data = trim($base64_data);
    
    // Extract MIME type from base64 data
    $mime_type = 'image/png'; // default
    $base64_content = $base64_data;
    
    if (preg_match('#^data:image/(\w+);base64,(.+)$#i', $base64_data, $matches)) {
        $image_format = strtolower($matches[1]);
        $base64_content = $matches[2];
        $mime_types = [
            'png' => 'image/png',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg+xml' => 'image/svg+xml',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ];
        if (isset($mime_types[$image_format])) {
            $mime_type = $mime_types[$image_format];
        }
    } else {
        // If no data URI prefix, assume PNG
        $base64_content = $base64_data;
    }
    
    // Clean base64 content (remove any whitespace/newlines that might have been added)
    $base64_content = preg_replace('/\s+/', '', $base64_content);
    
    // Decode base64 data (strict mode)
    $image_data = base64_decode($base64_content, true);
    
    if ($image_data === false) {
        // Try without strict mode as fallback
        $image_data = base64_decode($base64_content, false);
        if ($image_data === false || empty($image_data)) {
            return false; // Completely invalid base64
        }
    }
    
    if (strlen($image_data) < 100) {
        return false; // Too small to be a valid image
    }
    
    // Verify it's actually an image by checking file signature
    $is_valid_image = false;
    $image_signatures = [
        "\x89\x50\x4E\x47" => 'image/png', // PNG
        "\xFF\xD8\xFF" => 'image/jpeg', // JPEG
        "GIF87a" => 'image/gif', // GIF87a
        "GIF89a" => 'image/gif', // GIF89a
        '<svg' => 'image/svg+xml', // SVG
    ];
    
    foreach ($image_signatures as $signature => $detected_mime) {
        if (strpos($image_data, $signature) === 0) {
            $is_valid_image = true;
            // Use detected MIME type if it differs
            if ($detected_mime !== $mime_type && $detected_mime !== 'image/svg+xml') {
                $mime_type = $detected_mime;
            }
            break;
        }
    }
    
    // For SVG, check if it contains SVG tags
    if (!$is_valid_image && (strpos($image_data, '<svg') !== false || strpos($image_data, '<?xml') !== false)) {
        $is_valid_image = true;
        $mime_type = 'image/svg+xml';
    }
    
    if (!$is_valid_image) {
        return false; // Not a valid image file
    }
    
    // Determine file extension from MIME type
    $extensions = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp',
    ];
    $extension = $extensions[$mime_type] ?? 'png';
    
    // Sanitize filename
    $original_filename = sanitize_file_name($filename);
    if (empty($original_filename)) {
        $original_filename = 'offer-image-' . time() . '.' . $extension;
    } else {
        // Ensure filename has correct extension
        $file_info = pathinfo($original_filename);
        $current_ext = strtolower($file_info['extension'] ?? '');
        if (!in_array($current_ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'])) {
            $original_filename = ($file_info['filename'] ?? 'offer-image-' . time()) . '.' . $extension;
        } else {
            // Update extension if MIME type doesn't match
            if (($current_ext === 'jpg' || $current_ext === 'jpeg') && $mime_type !== 'image/jpeg') {
                $original_filename = ($file_info['filename'] ?? 'offer-image') . '.' . $extension;
            } elseif ($current_ext !== $extension && $mime_type !== 'image/jpeg') {
                $original_filename = ($file_info['filename'] ?? 'offer-image') . '.' . $extension;
            }
        }
    }
    
    // Check if an attachment with the same filename already exists
    // This prevents creating duplicates with suffixes like -1, -2, -3
    $existing_attachment_id = find_existing_attachment_by_filename($original_filename);
    if ($existing_attachment_id) {
        // Return existing attachment ID instead of creating a duplicate
        return $existing_attachment_id;
    }
    
    // Get upload directory (WordPress will use date-based subdirectory)
    $upload_dir = wp_upload_dir();
    if ($upload_dir['error']) {
        return false;
    }
    
    // Use WordPress date-based subdirectory structure (e.g., 2025/09/)
    // wp_upload_dir() already provides the correct path with date subdirectory
    // Note: wp_unique_filename will add -1, -2, etc. if file exists, but we've already checked above
    $unique_filename = wp_unique_filename($upload_dir['path'], $original_filename);
    $file_path = $upload_dir['path'] . '/' . $unique_filename;
    $filename = $unique_filename;
    
    // Ensure directory exists
    if (!file_exists($upload_dir['path'])) {
        wp_mkdir_p($upload_dir['path']);
    }
    
    // Save file with proper permissions (binary mode)
    $bytes_written = @file_put_contents($file_path, $image_data, LOCK_EX);
    if ($bytes_written === false || $bytes_written < 100) {
        return false;
    }
    
    // Set proper file permissions
    @chmod($file_path, 0644);
    
    // Verify file was saved correctly and is readable
    if (!file_exists($file_path) || filesize($file_path) < 100 || !is_readable($file_path)) {
        @unlink($file_path);
        return false;
    }
    
    // Double-check the file is a valid image by reading it back
    $verify_data = @file_get_contents($file_path);
    if ($verify_data !== $image_data || strlen($verify_data) !== strlen($image_data)) {
        @unlink($file_path);
        return false; // File corruption detected
    }
    
    // Create attachment using WordPress functions
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    // Prepare attachment data
    $attachment_title = sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME));
    
    $attachment = [
        'post_mime_type' => $mime_type,
        'post_title' => $attachment_title,
        'post_content' => '',
        'post_status' => 'inherit',
        'post_parent' => $post_id,
    ];
    
    // Ensure file path is absolute and normalized
    $file_path = wp_normalize_path($file_path);
    
    // Insert attachment - pass absolute file path
    $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
    
    if (is_wp_error($attach_id)) {
        @unlink($file_path);
        return false;
    }
    
    // Use WordPress function to properly update the attached file path
    // This converts absolute path to relative path correctly (e.g., 2025/12/filename.png)
    // This is critical for WordPress to generate the correct URL
    update_attached_file($attach_id, $file_path);
    
    // Store content hash for future duplicate detection
    $content_hash = md5($image_data);
    update_post_meta($attach_id, '_offer_image_hash', $content_hash);
    
    // Generate attachment metadata (creates thumbnails, etc.)
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    if (!empty($attach_data)) {
        wp_update_attachment_metadata($attach_id, $attach_data);
    }
    
    // Update the GUID with the correct URL (WordPress will generate it)
    $attachment_url = wp_get_attachment_url($attach_id);
    if ($attachment_url) {
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            ['guid' => $attachment_url],
            ['ID' => $attach_id],
            ['%s'],
            ['%d']
        );
    }
    
    return $attach_id;
}

// ============================================================
// Offer notification email on order processing / completion
// ============================================================

add_action('woocommerce_order_status_processing', 'bhn_send_offer_notification_on_order', 20, 1);
add_action('woocommerce_order_status_completed',  'bhn_send_offer_notification_on_order', 20, 1);

function bhn_send_offer_notification_on_order($order_id) {
    // Prevent duplicate sends across both hooks
    if (get_post_meta($order_id, '_offer_notification_email_sent', true) === 'yes') {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Collect product IDs and recipient emails per item.
    $product_ids     = [];
    $recipient_email = '';
    $recipient_name  = '';
    foreach ($order->get_items() as $item) {
        $pid = $item->get_product_id();
        if ($pid) {
            $product_ids[] = (int) $pid;
        }
        // Use the gift card recipient email if set, otherwise fall back to billing email.
        if ( empty( $recipient_email ) ) {
            $item_recipient = wc_get_order_item_meta( $item->get_id(), '_recipient_email', true );
            if ( ! empty( $item_recipient ) ) {
                $recipient_email = sanitize_email( $item_recipient );
                $recipient_name  = sanitize_text_field( wc_get_order_item_meta( $item->get_id(), '_recipient_name', true ) );
            }
        }
    }

    // Fall back to billing email/name if no recipient email found on items.
    if ( empty( $recipient_email ) ) {
        $recipient_email = $order->get_billing_email();
        $recipient_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    }

    if ( empty( $recipient_email ) || empty( $product_ids ) ) {
        return;
    }

    $customer_user_id = (int) $order->get_customer_id();
    $matched_offers   = bhn_find_active_offers_for_products($product_ids, $customer_user_id);

    if (empty($matched_offers)) {
        return;
    }

    // Send one email per offer so each gets its own CLAIM YOUR OFFER link.
    foreach ( $matched_offers as $single_offer ) {
        bhn_send_order_offer_notification_email( $recipient_email, $recipient_name, [ $single_offer ], $order_id );
    }

    // Mark each matched offer link as used in wp_offer_links
    $customer_user_id = (int) $order->get_customer_id(); // 0 for guests
    $used_at          = current_time('mysql');

    global $wpdb;
    $table = "{$wpdb->prefix}offer_links";

    foreach ($matched_offers as $offer) {
        $wpdb->update(
            $table,
            [
                'is_used' => 1,
                'status'  => 'used',
                'used_by' => $customer_user_id ?: null,
                'used_at' => $used_at,
            ],
            [
                'offer_id'   => $offer['offer_id'],
                'offer_link' => $offer['link'],
                'is_used'    => 0,
            ],
            ['%d', '%s', '%d', '%s'],
            ['%d', '%s', '%d']
        );
    }

    // Check remaining available links and notify admin if below threshold
    bhn_check_offer_links_threshold();

    update_post_meta($order_id, '_offer_notification_email_sent', 'yes');
}

/**
 * Count remaining available offer links globally.
 * If at or below the threshold, send a one-time warning email to the admin.
 * Threshold defaults to 5 and can be overridden via the filter
 * 'bhn_offer_links_low_threshold'.
 */
function bhn_check_offer_links_threshold() {
    $threshold = (int) apply_filters('bhn_offer_links_low_threshold', 5);

    global $wpdb;
    $table = "{$wpdb->prefix}offer_links";

    $remaining = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table} WHERE status = 'available' AND is_used = 0"
    );

    if ($remaining > $threshold) {
        return;
    }

    // Only notify when the count reaches a new low — prevents duplicate emails
    // at the same count while still firing again if it drops further.
    $last_notified_count = (int) get_option('bhn_offer_links_low_notified_count', PHP_INT_MAX);
    if ($remaining >= $last_notified_count) {
        return; // Already notified at this count or higher; count hasn't decreased further
    }

    // Gather per-offer breakdown for the email body
    $breakdown = $wpdb->get_results(
        "SELECT offer_id, COUNT(*) AS available_count
         FROM {$table}
         WHERE status = 'available' AND is_used = 0
         GROUP BY offer_id
         ORDER BY available_count ASC"
    );

    $rows = '';
    if (!empty($breakdown)) {
        foreach ($breakdown as $row) {
            $offer_id_int = (int) $row->offer_id;
            $offer_title  = esc_html(get_the_title($offer_id_int) ?: "Offer #{$offer_id_int}");
            $rows .= "<tr><td style='padding:6px 10px;border-bottom:1px solid #eee;'>{$offer_title} (ID: {$offer_id_int})</td><td style='padding:6px 10px;border-bottom:1px solid #eee;text-align:center;'>{$row->available_count}</td></tr>";
        }
    } else {
        $rows = "<tr><td colspan='2' style='padding:10px;'>No available offer links remaining.</td></tr>";
    }

    $admin_email    = get_option('admin_email');
    $site_name      = get_bloginfo('name');
    $escaped_site   = esc_html($site_name);
    $subject        = "[{$site_name}] Warning: Offer links are running low ({$remaining} remaining)";

    $message = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;'>"
        . "<h2 style='color:#b91c1c;'>Offer Links Running Low</h2>"
        . "<p>This is an automated warning from <strong>{$escaped_site}</strong>.</p>"
        . "<p>There are only <strong>{$remaining}</strong> available offer link(s) remaining (threshold: {$threshold}).</p>"
        . "<table style='width:100%;border-collapse:collapse;margin-top:15px;'>"
        . "<thead><tr>"
        . "<th style='padding:8px 10px;background:#f3f4f6;text-align:left;'>Offer</th>"
        . "<th style='padding:8px 10px;background:#f3f4f6;text-align:center;'>Available Links</th>"
        . "</tr></thead>"
        . "<tbody>{$rows}</tbody>"
        . "</table>"
        . "<p style='margin-top:20px;'>Please add more offer links to avoid disruption.</p>"
        . "<p>Thanks,<br><strong>{$escaped_site}</strong></p>"
        . "</body></html>";

    wp_mail($admin_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);

    update_option('bhn_offer_links_low_notified_count', $remaining);
}

/**
 * Find all published, active offers whose product scope covers at least one
 * of the given product IDs, and that have a link configured.
 *
 * @param int[] $product_ids
 * @return array<int, array{offer_id: int, title: string, link: string}>
 */
function bhn_find_active_offers_for_products(array $product_ids, int $customer_user_id = 0) {
    $offer_posts = get_posts([
        'post_type'      => 'offer',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    if (empty($offer_posts)) {
        return [];
    }

    global $wpdb;
    $offer_links_table = "{$wpdb->prefix}offer_links";

    // Resolve the customer's WordPress roles once (empty for guests)
    $customer_roles = [];
    if ($customer_user_id > 0) {
        $user = get_userdata($customer_user_id);
        if ($user) {
            $customer_roles = (array) $user->roles;
        }
    }

    $current_time = current_time('mysql');
    $matched      = [];

    foreach ($offer_posts as $offer_id) {
        $showcase_type = get_post_meta($offer_id, '_offer_showcase_type', true);

        // Must be a link-type offer.
        if ($showcase_type !== 'link') {
            continue;
        }

        // Fetch one available unused link from the wp_offer_links table for this offer.
        $offer_link = $wpdb->get_var($wpdb->prepare(
            "SELECT offer_link FROM {$offer_links_table} WHERE offer_id = %d AND status = 'available' AND is_used = 0 ORDER BY id ASC LIMIT 1",
            $offer_id
        ));

        if ( empty( $offer_link ) ) {
            continue;
        }

        // Check active date range
        $always_on = get_post_meta($offer_id, '_offer_always_on', true);
        if ($always_on !== 'yes') {
            $start_date = get_post_meta($offer_id, '_offer_start_date', true);
            $end_date   = get_post_meta($offer_id, '_offer_end_date', true);

            if (!empty($start_date) && $current_time < $start_date) {
                continue; // Not started yet
            }
            if (!empty($end_date) && $current_time > $end_date) {
                continue; // Expired
            }
        }

        // Check user-role restriction
        $allowed_roles = get_post_meta($offer_id, '_offer_user_roles', true);
        if (!empty($allowed_roles) && is_array($allowed_roles)) {
            // Offer is role-restricted; guests (no roles) and non-matching roles are skipped
            if (empty($customer_roles) || empty(array_intersect($customer_roles, $allowed_roles))) {
                continue;
            }
        }

        // Check product scope
        $all_products   = get_post_meta($offer_id, '_offer_all_products', true);
        $offer_products = get_post_meta($offer_id, '_offer_products', true);

        if ($all_products === 'yes') {
            // Applies to every product → always match
            $matched[$offer_id] = [
                'offer_id' => $offer_id,
                'title'    => get_the_title($offer_id),
                'link'     => $offer_link,
            ];
            continue;
        }

        if (is_array($offer_products) && !empty(array_intersect($product_ids, $offer_products))) {
            $matched[$offer_id] = [
                'offer_id' => $offer_id,
                'title'    => get_the_title($offer_id),
                'link'     => $offer_link,
            ];
        }
    }

    return array_values($matched);
}

/**
 * Send the offer notification email to the customer.
 */
function bhn_send_order_offer_notification_email($to, $name, array $offers, $order_id) {
    $site_name = get_bloginfo('name');

    // Build plain-text fallback list only (not used in template, only in fallback email).
    $offer_list_text = '';
    foreach ($offers as $offer) {
        $offer_list_text .= "{$offer['title']}: {$offer['link']}\n";
    }

    // Build order received URL and first offer URL for the CTA button.
    $order_url = wc_get_endpoint_url( 'order-received', $order_id, wc_get_checkout_url() ) . '?key=' . wc_get_order( $order_id )->get_order_key();
    $offer_url = ! empty( $offers[0]['link'] ) ? esc_url( $offers[0]['link'] ) : wc_get_checkout_url();

    // Try the 'plus-offer' email template first.
    $tpl = et_get_template_by_slug( 'plus-offer', [
        'first_name'   => $name,
        'order_number' => '<a href="' . esc_url( $order_url ) . '" style="color:#ED018C;text-decoration:underline;">' . esc_html( $order_id ) . '</a>',
        'offer_url'    => $offer_url,
        'site_name'    => $site_name,
    ] );

    if ( $tpl ) {
        wp_mail( $to, $tpl['subject'], $tpl['body'], $tpl['headers'] );
        return;
    }

    // Fallback: send inline HTML if the template post does not exist yet.
    $escaped_name      = esc_html($name);
    $escaped_order_id  = esc_html($order_id);
    $escaped_site_name = esc_html($site_name);
    $escaped_offer_url = esc_url($offer_url);

    $message = "<!DOCTYPE html><html><body style=\"font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;\">"
        . "<h2 style=\"color:#111;\">Hi {$escaped_name},</h2>"
        . "<p>Thank you for your recent purchase (Order #{$escaped_order_id})!</p>"
        . "<p>Great news — you have a special offer waiting for you:</p>"
        . "<p><a href=\"{$escaped_offer_url}\" style=\"color:#ED018C;font-weight:bold;\">Claim Your Offer</a></p>"
        . "<p>Thanks,<br><strong>{$escaped_site_name}</strong></p>"
        . "</body></html>";

    wp_mail( $to, 'You have a special offer on your recent purchase!', $message, ['Content-Type: text/html; charset=UTF-8'] );
}

