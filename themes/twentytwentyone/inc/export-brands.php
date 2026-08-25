<?php
add_action('wp_ajax_export_brands_batch_stream', function () {
    check_ajax_referer('export_brands_nonce');

    // 🔑 Use $_POST instead of only $_REQUEST (works for GET + POST)
    $offset     = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit      = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
    // $search     = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $b_id       = isset($_POST['b_id']) ? intval($_POST['b_id']) : '';
    $b_name     = isset($_POST['b_name']) ? sanitize_text_field($_POST['b_name']) : '';
    $b_assign   = isset($_POST['b_assign']) ? sanitize_text_field($_POST['b_assign']) : '';
    $b_status   = isset($_POST['b_status']) ? sanitize_text_field($_POST['b_status']) : '';
    $b_status_array = !empty($b_status) ? explode(',', strtolower($b_status)) : [];
    $b_name_array = !empty($b_name) ? explode(',', strtolower($b_name)) : [];
    
    
    // echo '<pre>';
    // print_r($b_name_array);
    // echo '</pre>';
    // wp_die();
    
    // pr($b_status);
    // exit;
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $view_type = isset($_POST['view']) ? sanitize_text_field($_POST['view']) : 'list';

    if (is_numeric($search)) {
        $args['include'] = [intval($search)];
    }

    // Query brands with search filter
    $args = [
        'taxonomy' => 'product_brand',
        'hide_empty' => false,
        'orderby' => 'name',
    ];


    if ($b_id) {
        $args['include'] = [$b_id];
    }
    if (!empty($b_name_array)) {
        $args['name'] = $b_name_array; // accepts array of names
    }
     
 
    
    
    // if ($b_name) {
    //     $args['name__like'] = $b_name;
    // }

//    if ($b_name) {
//         $args['name__like'] = $b_name;
//     }

    if (!empty($b_status_array)) {
        $meta_query[] = [
            'key'     => 'brand_status',
            'value'   => $b_status_array,
            'compare' => 'IN',
        ];
        $args['meta_query'] = $meta_query;
    }
    
    // if (!empty($b_name_array)) {
    //     $meta_query[] = [
    //         'key'     => 'brand_status',
    //         'value'   => $b_name_array,
    //         'compare' => 'IN',
    //     ];
    //     $args['meta_query'] = $meta_query;
    // }
    $brands = get_terms($args);
    // echo '<pre>';
    // print_r($brands);
    // echo '</pre>';
    // wp_die();
    if( $b_assign == 0 || $b_assign > 0 ){
        $min_count = (int)$b_assign; // set minimum number of products
        if ($min_count >= 0) {
            $brands = array_filter($brands, function($brand) use ($min_count) {
                return $brand->count == $min_count;
            });
        }
    }

    if (empty($brands)) {
        header('Content-Type: text/plain');
        echo 'No brands found to export.';
        wp_die();
    }
    // if (!empty($meta_query)) {
    //     $args['meta_query'] = $meta_query;
    // }
    
    // $brands = get_terms($args);
    if (empty($brands)) {
        // Return plain text error (not CSV headers!)
        header('Content-Type: text/plain');
        echo 'No brands found to export.';
        wp_die();
    }
    // echo '<pre>';
    // print_r($args);
    // echo '</pre>';
    // Prepare CSV output
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="brands-export.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Brand Name', 'Assigned Products', 'Thumbnail URL', 'Status'    ]);
    foreach ($brands as $brand) {
        $thumbnail_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
        $status = get_term_meta($brand->term_id, 'brand_status', true);

        fputcsv($output, [
            $brand->term_id,
            $brand->name,
            $brand->count,
            $thumbnail_url,
            $status
        ]);
    }
    
    fclose($output);
    ob_end_flush();
    exit;
});

// function sanitize_csv_field_brands($value) {
//     return str_replace('"', '""', $value); // Escape quotes
// }
