<?php
add_action('wp_ajax_export_orders_batch_stream', function () {
    check_ajax_referer('export_orders_nonce');

    $offset     = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit      = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
    $search     = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $o_id       = isset($_POST['o_id']) ? intval($_POST['o_id']) : '';
    $o_name       = isset($_POST['o_name']) ? sanitize_text_field($_POST['o_name']) : '';
    $o_date_from = isset($_POST['o_date_from']) ? sanitize_text_field($_POST['o_date_from']) : '';
    $o_date_to   = isset($_POST['o_date_to']) ? sanitize_text_field($_POST['o_date_to']) : '';
    $o_ref      = isset($_POST['o_ref']) ? sanitize_text_field($_POST['o_ref']) : '';
    $o_user     = isset($_POST['o_user']) ? sanitize_text_field($_POST['o_user']) : '';
    $o_status   = isset($_POST['o_status']) ? sanitize_text_field($_POST['o_status']) : '';
    $o_invoice  = isset($_POST['o_invoice']) ? sanitize_text_field($_POST['o_invoice']) : '';
    $o_total    = isset($_POST['o_total']) ? floatval($_POST['o_total']) : '';



    // Meta query
    $meta_query = [];
    $args = [
        'status'  => 'any',
        'limit'   => $limit,
        'offset'  => $offset,
        'return'  => 'ids',
    ];

   
    // if ($o_date_from || $o_date_to) {
    //     $args['date_created'] = [];

    //     if ($o_date_from) {
    //         $args['date_created']['after'] = new WC_DateTime($o_date_from . ' 00:00:00');
    //     }

    //     if ($o_date_to) {
    //         $args['date_created']['before'] = new WC_DateTime($o_date_to . ' 23:59:59');
    //     }
    // }
    if( $search ){
        $meta_query[] = [
            'key'     => '_gift_recipients',
            'value'   => $search,
            'compare' => 'LIKE',
        ];
    }

    // User filter
    if ($o_user) {
        // Lookup user ID from display_name
        $user = get_user_by('slug', sanitize_title($o_user));

        if (!$user) {
            $users = get_users([
                'search' => '*' . esc_attr($o_user) . '*',
                'search_columns' => ['display_name'],
                'number' => 1
            ]);
            $user = !empty($users) ? $users[0] : null;
        }

        if ($user) {
            $meta_query[] = [
                'key'     => '_customer_user',
                'value'   => $user->ID,
                'compare' => '='
            ];
        } else {
            // No match: prevent matches
            $meta_query[] = [
                'key'     => '_customer_user',
                'value'   => 0,
                'compare' => '='
            ];
        }
    }

    // Order status
    if ($o_status) {
        $temp_status = explode(',', strtolower($o_status));
        $args['status'] = $temp_status;
    }

    if ($o_name) {
        $meta_query[] = [
            'key'     => '_order_name',
            'value'   => $o_name,
            'compare' => 'LIKE',
        ];
    }

    if ($o_ref) {
        $meta_query[] = [
            'key'     => '_client_reference',
            'value'   => $o_ref,
            'compare' => 'LIKE',
        ];
    }

    if ($o_invoice) {
        $meta_query[] = [
            'key'     => '_invoice_number',
            'value'   => $o_invoice,
            'compare' => 'LIKE',
        ];
    }

    /*if ($o_total) {
        $meta_query[] = [
            'key'     => '_order_total',
            'value'   => $o_total,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }*/
    if ($o_total) {
        $args['total'] = $o_total; // This will do an exact match
    }

    if (!empty($meta_query)) {
        $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
    }

    if ($o_id) {
        $args['limit'] = -1; // get all orders (risky)
    }

    if ($o_date_from || $o_date_to) {

        /*$args['date_query'] = [
            [
                'after'     => $o_date_from ? $o_date_from . ' 00:00:00' : null,
                'before'    => $o_date_to ? $o_date_to . ' 23:59:59' : null,
                'inclusive' => true,
                'column'    => 'post_date', // or adjust for order table
            ]
        ];*/
        $date_created = [];
    
        if ($o_date_from) {
            $date_created['after'] = $o_date_from . ' 00:00:00';
        }
    
        if ($o_date_to) {
            $date_created['before'] = $o_date_to . ' 23:59:59';
        }

        $date_created['inclusive'] = true;
        $date_created['column'] = 'post_date';
    
        $args['date_query'] = $date_created;
        //$args['date_created'] = $date_created;
    }

    // Run main query
    $orders = wc_get_orders($args);

    // Apply manual ID filter last
    /*if ($o_id) {
        $orders = array_filter($orders, fn($order_id) => $order_id == $o_id);
    }*/

    // Partial Match for Order ID
    if ($o_id) {
        $o_id = (string) $o_id; // ensure it's treated as a string
        $orders = array_filter($orders, function($order_id) use ($o_id) {
            return strpos((string) $order_id, $o_id) !== false;
        });
    }
    // if (empty($orders)) {
    //     wp_send_json_error(['message' => 'No orders found matching the filters.']);
    //     wp_die();
    // }
    $rows = '';
    $headers = [
        'Order ID',
        'Date',
        'Order Name',
        'Client Reference',
        'User',
        'Status',
        'Invoice',
        'Total'
    ];
   
    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        $order_status = ($order->get_status() == 'pending') ? 'Draft' : ucwords($order->get_status());
        $row = [
            $order->get_id(), // o_id
            $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
            $order->get_meta('_order_name'), // o_ref
            $order->get_meta('_client_reference'), // o_ref
            $order->get_user() ? $order->get_user()->display_name : '', // o_user
            $order_status, // o_status
            $order->get_meta('_invoice_number'), // o_invoice
            $order->get_currency() . ' ' . wc_format_decimal($order->get_total(), 2)
        ];

        $rows .= '"' . implode('","', array_map('sanitize_csv_field', $row)) . "\"\n";
    }

    // Accurate count for pagination
    $count_args = $args;
    $count_args['limit'] = 1;
    $count_args['paginate'] = true;

    $query = new WC_Order_Query($count_args);
    $result = $query->get_orders();
    $total_orders = $result->total;

    // If filtering by ID manually, override total
    if ($o_id) {
        $total_orders = count($orders);
    }

    $done = ($offset + $limit) >= $total_orders;


    wp_send_json_success([
        'headers'       => $offset === 0 ? implode(',', $headers) : '',
        'rows'          => $rows,
        'args'          => $args,
        'orders'          => $orders,
        'done'          => $done,
        'total_orders'  => $total_orders,
    ]);
});

function sanitize_csv_field($value) {
    return str_replace('"', '""', $value); // Escape quotes
}
