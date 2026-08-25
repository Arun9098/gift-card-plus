<?php
    add_action('rest_api_init', function() {
            register_rest_route('custom-reports/v1', '/order_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_order_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/cards_tracking_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_cards_tracking_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/float_balance_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_float_balance_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/client_billing_balance_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_client_billing_balance_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/all_business_balance_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_all_business_balance_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/float_order_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_float_order_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/client_billing_order_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_client_billing_order_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/user_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_user_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/activation_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_activation_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/expiry_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_expiry_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/product_listing_report_extract', [
                'methods'  => 'GET',
                'callback' => 'custom_get_product_listing_report_extract_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/billing_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_billing_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/supplier_order_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_supplier_order_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/supplier_billing_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_supplier_billing_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/individual_business_balance_statement', [
                'methods'  => 'GET',
                'callback' => 'custom_get_individual_business_balance_statement_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/credit_card_payment_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_credit_card_payment_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/refunds_cancellation_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_refunds_cancellation_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/audit_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_audit_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/brand_listing_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_brand_listing_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/supplier_product_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_supplier_product_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);

            register_rest_route('custom-reports/v1', '/business_report', [
                'methods'  => 'GET',
                'callback' => 'custom_get_business_report_paginated',
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ]);
    });

    function custom_get_order_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));
        //$permissions = current_user_can( 'manage_options' );

        //$nonce = $request->get_header('X-WP-Nonce') ?? $request->get_param('nonce');

        // Verify nonce
        /*if ( $permissions != 1 ) {
            return new WP_REST_Response( [ 'message' => 'Invalid nonce '.$permissions ], 403 );
        }*/

        $args = [
            'limit'  => $per_page,
            'page'   => $page,
            'orderby'=> 'date',
            'order'  => 'DESC',
        ];

        $orders = wc_get_orders($args);

        $data = [];
        foreach ($orders as $order) {
            
            $customer_id = $order->get_customer_id();
            // $client_billing = (get_user_meta($customer_id, 'approved_billing', true) == 'yes') ? 'Y' : 'N';
            $order_status = '<span class="'.$order->get_status().'">'.ucfirst($order->get_status()).'</span>';
            $activation_expiry_type = $order->get_meta('_selected_activation_expiry_type');

            if($activation_expiry_type == 'set_period'){
                $activation_expiry_type = 'Set Period';
            } else if($activation_expiry_type == 'default'){
                $activation_expiry_type = 'Default';
            } else if($activation_expiry_type == 'no_activation_expiry'){
                $activation_expiry_type = 'No Activation Expiry';
            } else if($activation_expiry_type == 'no_activation_needed'){
                $activation_expiry_type = 'No Activation Needed';
            }


            // $activation_expiry = $order->get_meta('_activation_expiry_date');
            
            // if(empty($activation_expiry) || $activation_expiry == ''){
            //     $activation_expiry = 'No';
            // }


            $order_id = $order->get_id();
            $order_number = $order->get_order_number();
            $serialized_customer_data = get_post_meta($order_id, "{$order_number}_customer_details", true);
            $serialized_pro_data = get_post_meta($order_id, "{$order_number}_pro_details", true);
            $customer_data = maybe_unserialize($serialized_customer_data);
            $pro_data = maybe_unserialize($serialized_pro_data);

            $order_date_obj = $order->get_date_created();
            $order_date = $order_date_obj ? $order_date_obj->getTimestamp() : time();
            $activation_expiry = ''; // Default empty

            // Check for activation expiry logic
            if (!empty($pro_data) && is_array($pro_data)) {
                foreach ($pro_data as $product) {
                    if (!isset($product['all_fields']['activation_expiry_type'])) {
                        continue;
                    }
                    
                    $type = $product['all_fields']['activation_expiry_type'];

                    if (in_array($type, ['no_activation_needed', 'no_activation_expiry'])) {
                        $activation_expiry = 'No Activation Expiry';
                    } elseif ($type === 'activation_set_date' && !empty($product['all_fields']['activation_expiry_date'])) {
                        $activation_expiry = $product['all_fields']['activation_expiry_date'];
                    }

                    // 3️⃣ If it's "set_period", calculate dynamically
                    elseif ($type === 'set_period') {
                        
                        $duration = (int) ($product['all_fields']['activation_expiry_duration'] ?? 0);
                        $unit = strtolower($product['all_fields']['activation_expiry_unit'] ?? '');
                        
                        if ($duration > 0 && $unit) {
                            $expiry_timestamp = match ($unit) {
                                'days', 'day' => strtotime("+{$duration} days", $order_date),
                                'weeks', 'week' => strtotime("+{$duration} weeks", $order_date),
                                'months', 'month' => strtotime("+{$duration} months", $order_date),
                                'years', 'year' => strtotime("+{$duration} years", $order_date),
                                default => null,
                            };

                            if ($expiry_timestamp) {
                                $activation_expiry = date('d-m-Y H:i:s', $expiry_timestamp);
                            }
                        }
                    }
                }
            }

            if (empty($activation_expiry)) {
                $activation_expiry = 'No';
            }
            


            if (!empty($customer_data) && isset($customer_data['approved_billing'][0]) && $customer_data['approved_billing'][0] !== '') {
                $approved_billing_value = $customer_data['approved_billing'][0];
            } elseif (!empty($pro_data) && isset($pro_data['approved_billing'][0]) && $pro_data['approved_billing'][0] !== '') {
                $approved_billing_value = $pro_data['approved_billing'][0];
            } else {
                $approved_billing_value = get_user_meta($customer_id, 'approved_billing', true);
            }

            $client_billing = ($approved_billing_value === 'yes') ? 'Y' : 'N';



            // echo 'Order Details </br>';

            // Map product ID => denomination_type
            $approved_billing = [];
            if (!empty($customer_data) && is_array($customer_data)) {
                foreach ($customer_data as $cdata) {
                    // Check for both 'product_id' or 'id'
                    $cid = $cdata['product_id'] ?? $cdata['id'] ?? 0;
                    if ($cid) {
                        $approved_billing[$cid] = $cdata['all_fields']['approved_billing'] ?? '';
                    }
                }
            }
         


            // Sum item subtotals only — excludes Stripe fees, fulfilment, and delivery.
            $gift_card_total = 0.0;
            foreach ( $order->get_items() as $item ) {
                $gift_card_total += (float) $item->get_subtotal();
            }
            $data[] = [
                'id'           => $order->get_id(),
                'order_date' => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y') : '',
                'order_time' => $order->get_date_created() ? $order->get_date_created()->date('H:i:s') : '',
                'order_name' => $order->get_meta('_order_name') ? $order->get_meta('_order_name') : '',
                'user'         => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                'billing'      => [
                    'first_name' => $order->get_billing_first_name(),
                    'last_name'  => $order->get_billing_last_name(),
                ],
                'business_name'     => $order->get_meta('_business_name'),
                'business_id'       => $customer_id,
                'business_float_id' => get_user_meta($customer_id, 'business_float_id', true),
                'client_billing'    => $client_billing,
                'payment_type'      => $order->get_payment_method_title(),
                'order_status'      => $order_status,
                'invoice_number'    => $order->get_meta('_invoice_number'),
                'payment_status'    => (ucfirst($order->get_status()) === 'Completed') ? '<span class="'.$order->get_status().'">Payment complete</span>' : $order_status,
                'order_total'       => wc_price($order->get_total()),
                'order_total_gift_cards'  =>  wc_price($gift_card_total),
                'Order_total_fulfilment'  => wc_price($order->get_meta('fullfillment_total')),
                'order_total_delivery_cost' => wc_price($order->get_meta('delivery_total')),
                'order_total_gst' => wc_price($order->get_meta('_order_gst')),
                'campaign' => $order->get_meta('_campaign'),
                'sender_profile' => $order->get_meta('_sender_name').' ('.$order->get_meta('_sender_email').')',
                'client_reference' => $order->get_meta('_client_reference'),
                'po_number' => $order->get_meta('_po_number'),
                'additional_client_reference' => $order->get_meta('_additional_reference'),
                'order_level_activation_expiry' => $activation_expiry_type,
                'activation_expiry_set_for_this_order' => $activation_expiry,
            ];
        }

        $total_args = [
            'return' => 'ids',
            'orderby'=> 'date',
            'order'  => 'DESC',
            'limit'   => -1,
        ];

        $total_orders = count( wc_get_orders( $total_args ) );

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_orders'  => $total_orders,
            'orders'        => $data,
        ]);
    }

    function custom_get_cards_tracking_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'post_type'      => 'gift_card',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query($args);

        $data = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Assuming order ID is saved as post meta with key '_order_id', adjust if different
                $order_number = get_post_meta($post_id, '_order_id', true);
                $order = wc_get_order($order_number);
                
                if( !$order ){
                    continue;
                }

                $temp_o_status = $order->get_status();
                if(  $temp_o_status == 'trash' ){
                    continue;
                }
                $order_date = $order->get_date_created()->date('d-m-Y');
                $order_name = $order->get_meta('_order_name');
                $order_status = '<span class="'.$order->get_status().'">'.ucfirst($order->get_status()).'</span>';
                $gift_card_sku = get_post_meta($post_id, '_product_sku', true);
                
                $business_name = get_post_meta($post_id, 'business_name', true);
                $product_id = wc_get_product_id_by_sku( get_post_meta($post_id, '_product_sku', true) );
                $gift_card_product = wc_get_product($product_id);
                $gift_card_price = $gift_card_product ? $gift_card_product->get_regular_price() : '';

                $gift_card_name = get_the_title($product_id);
                $o_id = $order->get_id();
                

                $order_number = $order->get_order_number();
                $serialized_data = get_post_meta($o_id, "{$order_number}_pro_details", true);
                $products_data = maybe_unserialize($serialized_data);
                $gift_card_name = '';
                $parent_sku = '';
                $brand = '';
                $supplier = '';


                // Actual per-card computed dates (set at order time on the gift_card post itself),
                // not the static product-level fields — those are only populated for fixed-date
                // expiry/activation types and are blank for duration-based ("on purchase"/"set period") types.
                $gift_card_expiry_date = get_field('gift_card_expiry_date', $post_id);
                if (empty($gift_card_expiry_date)) {
                    $gift_card_expiry_date = get_post_meta($post_id, '_expiry_date', true);
                }
                $gift_card_activation_expiry_date = get_post_meta($post_id, '_activation_expiry_date', true);

                if (!empty($products_data) && is_array($products_data)) {
                    foreach ($products_data as $prod) {
                        $sku = $prod['all_fields']['_sku'] ?? '';
                        if ($sku === $gift_card_sku) {
                            $parent_sku = $prod['all_fields']['parent_sku'] ?? '';
                            $brand_data = $prod['all_fields']['product_brand'] ?? '';
                            $supplier = $prod['all_fields']['supplier'] ?? '';
                            if (!empty($brand_data)) {
                                $unserialized = maybe_unserialize($brand_data);
                                $brand_slugs = is_array($unserialized) ? $unserialized : [$unserialized];
                
                                $brand_names = [];
                                foreach ($brand_slugs as $slug) {
                                    $term = get_term_by('slug', $slug, 'product_brand');
                                    if ($term && !is_wp_error($term)) {
                                        $brand_names[] = $term->name;
                                    } else {
                                        // fallback: keep slug if no term found
                                        $brand_names[] = $slug;
                                    }
                                }
                                $brand = implode(', ', $brand_names);
                            }
                            break;
                        }
                    }
                }
                // Prefer the supplier set directly on the product — the order-snapshot lookup
                // above only finds it if the order's {order_number}_pro_details snapshot exists
                // and contains a matching SKU, which isn't always the case (e.g. orders placed
                // before that snapshot was saved for all order flows).
                if (empty($supplier)) {
                    $supplier = get_post_meta($product_id, 'supplier', true);
                }

                $supplier_user = get_user_by('ID', $supplier);

                $supplier_user_name = '';

                if ($supplier_user && !is_wp_error($supplier_user)) {
                    $supplier_user_name = $supplier_user->display_name;
                } else {
                    $supplier_user_name = 'N/A';
                }


                // if($gift_card_activation_expiry_type == 'gift_set_date'){
                //     $gift_card_activation_expiry_type = 'Set Date';
                // } else if($gift_card_activation_expiry_type = 'no_expiry'){
                //     $gift_card_activation_expiry_type = 'No Expiry';
                // } else if($gift_card_activation_expiry_type = 'expiry_period_starts_on_purchase'){
                //     $gift_card_activation_expiry_type = 'Expiry Period Starts on Purchase';
                // } else if($gift_card_activation_expiry_type = 'expiry_period_starts_on_activation'){
                //     $gift_card_activation_expiry_type = 'Expiry Period Starts on Activation';
                // }

                // If parent_sku exists, get parent name
                if (!empty($parent_sku)) {
                    $parent_id = wc_get_product_id_by_sku($parent_sku);
                    if ($parent_id) {
                        $gift_card_name = get_the_title($parent_id);
                    } else {
                        // fallback if parent product not found
                        $gift_card_name = get_the_title($product_id);
                    }
                } else {
                    // fallback if no parent_sku defined
                    $parent_sku = $gift_card_sku;
                    $gift_card_name = get_the_title($product_id);
                }
                $order_customer_id = $order->get_customer_id();
                $order_user_id = get_user_by( 'id', $order_customer_id );
                $order_customer_id = $order->get_customer_id();

                $order_user_id = get_user_by('id', $order_customer_id);

                $order_customer_name = 'Guest User';

                if ($order_user_id && !is_wp_error($order_user_id)) {
                    $order_customer_name = $order_user_id->display_name;
                }
                // $supplier_id = get_post_meta($product_id, 'supplier', true);
                // $supplier = get_user_meta($supplier_id, 'first_name', true).' '.get_user_meta($supplier_id, 'last_name', true);
                $delivery_date = '';
                $delivery_time = '';
                if( get_post_meta($post_id, '_scheduled_gift_card_delivery', true) ){
                    $temp_date_time = explode(' ', get_post_meta($post_id, '_scheduled_gift_card_delivery', true));
                    $delivery_date = $temp_date_time[0];
                    $delivery_time = $temp_date_time[1].' '.$temp_date_time[2];
                }

                if(($delivery_date == '' || empty($delivery_date)) || ($delivery_time == '' || empty($delivery_time))){
                    $delivery_date = 'Instant';
                    $delivery_time = 'Instant';
                }
                $activation_expiry_type = get_post_meta($post_id, '_activation_expiry_type', true);
                $activation_set_y_n = 'Y';
                $activated_y_n = 'N';
                $gift_card_activated = 'N';
                
                if( !empty($activation_expiry_type) && $activation_expiry_type == 'no_activation_expiry' || $activation_expiry_type == 'no_activation_needed' ){
                    $activation_set_y_n = 'N';
                }

                if($activation_expiry_type == 'no_activation_expiry'){
                    $activation_expiry_type = 'No Activation Expiry';
                } else if($activation_expiry_type == 'no_activation_needed'){
                    $activation_expiry_type = 'No Activation Needed';
                } else if($activation_expiry_type == 'set_period'){
                    $activation_expiry_type = 'Set Period';
                }

                // $current_datetime = current_time('mysql');

                // if( !empty($activation_expiry_type) && $activation_expiry_type != 'no_activation_expiry' ){
                //     $activation_expiry_date = get_post_meta($post_id, '_activation_expiry_date', true);
                // }

                $delivery_sms = '';
                $temp_delivery_sms = get_post_meta($post_id, '_recipient_phone', true);
                if( !empty($temp_delivery_sms) && $temp_delivery_sms != 'undefined' ){
                    $delivery_sms = $temp_delivery_sms;
                }

                if($delivery_sms == '' || empty($delivery_sms)){
                    $delivery_sms = 'N/A';
                }

                $gift_card_number_enc = get_post_meta($post_id, '_gift_card_number_enc', true);
                $gift_card_number = '';

                if( !empty($gift_card_number_enc) ){
                    $gift_card_number = '';
                    try {

                        if (!empty($gift_card_number_enc)) {

                            $gift_card_number = decrypt_giftcard_no( $gift_card_number_enc );

                        } else {

                            $gift_card_number = get_post_meta(
                                $post_id,
                                '_gift_card_number',
                                true
                            );
                        }

                    } catch (Exception $e) {

                        // Prevent API crash
                        $gift_card_number = 'Invalid / Corrupted Card';
                    }
                }else{
                    $gift_card_number = get_post_meta(
                        $post_id,
                        '_gift_card_number',
                        true
                    );

                    if (!empty($gift_card_number)) {

                        update_post_meta(
                            $post_id,
                            '_gift_card_number_enc',
                            encrypt_giftcard_no($gift_card_number)
                        );
                    }
                }
                // $brands = wp_get_post_terms($product_id, 'product_brand', ['fields' => 'names']);
                $data[] = [
                    'order_date' => $order_date,
                    'gift_card_name' => $gift_card_name,
                    'gift_card_sku' => $parent_sku,
                    'gift_card_denomination' => '$' . $gift_card_price,
                    'gift_card_number' => !empty($gift_card_number) ? 'XXXX XXXX' : '',
                    'brand' => $brand,
                    'order_no' => $order_number,
                    'order_name' => $order_name,
                    'order_status' => $order_status,
                    'order_user' => $order_customer_name,
                    'business_name' => $business_name,
                    'delivery_date' => $delivery_date,
                    'delivery_time' => $delivery_time,
                    'delivery_method' => get_post_meta($post_id, '_delivery_method', true),
                    'delivery_email' => get_post_meta($post_id, '_recipient_email', true),
                    'delivery_sms' => $delivery_sms,
                    'card_status' => get_post_meta($post_id, '_gift_card_send', true),
                    'sender_profile' => get_post_meta($post_id, '_sender_name', true).' ('.get_post_meta($post_id, '_sender_email', true).')',
                    'gift_card_activation_set_y_n' => $activation_set_y_n,
                    'gift_card_activated_y_n' => 'N',
                    'gift_card_expiry_date' => $gift_card_expiry_date,
                    'gift_card_activation_type' => $activation_expiry_type,
                    'gift_card_activation_expiry_date' => $gift_card_activation_expiry_date,
                    'campaign' => get_post_meta($post_id, '_campaign', true),
                    'supplier' => $supplier_user_name,
                ];
            }
            wp_reset_postdata();
        }

        // Get total count of GiftCard posts
        $total_giftcards = $query->found_posts;

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_giftcards' => $total_giftcards,
            'giftcards'     => $data,
        ]);
    }

    function custom_get_float_balance_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'role'     => 'business_user',
            'number'   => $per_page,
            'paged'    => $page,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key'     => 'approved_billing',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'approved_billing',
                    'value'   => 'no',
                    'compare' => '=',
                ],
            ],
        ];

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_float_businesses = $user_query->get_total();
        $data = [];

        foreach ($users as $user) {
            $user_id = $user->ID;
            // $business_team_users_ids = get_user_meta($user_id, 'business_team_users_ids', true);
            // if (is_array($business_team_users_ids)) {
            //     $business_team_users_ids = implode(',', $business_team_users_ids);
            // }

            $user_team_query = new WP_User_Query(array(
                'meta_key' => 'assigned_business_user',
                'meta_value' => $user_id,
            ));
            $team_users = $user_team_query->get_results();
            $team_user_ids = wp_list_pluck($team_users, 'ID');

            $data[] =[
                'business_name' =>  $user->display_name,
                'business_id' => $user_id,
                'business_float_id' => get_user_meta($user_id, 'business_float_id', true),
                'approved_for_client_billing_y_n' => get_user_meta($user_id, 'approved_billing', true) === 'yes' ? 'Y' : 'N',
                'business_abn' => get_user_meta($user_id, 'business_abn', true),
                'business_website' => get_user_meta($user_id, 'business_website', true),
                'business_team_users_ids' => implode(', ', $team_user_ids),
                'busineess_address_line_1'      => get_user_meta($user_id, 'address_line1', true),
                'business_address_line_2'       => get_user_meta($user_id, 'address_line2', true),
                'suburb'                       => get_user_meta($user_id, 'suburb', true),
                'state'                        => get_user_meta($user_id, 'state', true),
                'country'                      => get_user_meta($user_id, 'country', true),
                'postcode'                     => get_user_meta($user_id, 'postcode', true),
                'business_currency'            => get_user_meta($user_id, 'business_currency', true),
                'current_float_balance' => get_user_meta($user_id, 'float_balance', true),
                'top_up_notification_amount' => '',
                'difference_before_topping_up' => '',
            ];
        }

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_float_businesses' => $total_float_businesses,
            'float_businesses'     => $data,
        ]);
    }

    function custom_get_client_billing_balance_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'role'     => 'business_user',
            'number'   => $per_page,
            'paged'    => $page,
            'meta_query' => [
                [
                    'key'     => 'approved_billing',
                    'value'   => 'yes',
                    'compare' => '=',
                ],
            ],
        ];

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_client_billing_businesses = $user_query->get_total();
        $data = [];

        foreach ($users as $user) {
            $user_id = $user->ID;
            $user_team_query = new WP_User_Query(array(
                'meta_key' => 'assigned_business_user',
                'meta_value' => $user_id,
            ));
            $team_users = $user_team_query->get_results();
            $team_user_ids = wp_list_pluck($team_users, 'ID');

            $data[] =[
                'business_name' => get_user_meta($user_id, 'business_name', true),
                'business_id' => $user_id,
                'business_float_id' => get_user_meta($user_id, 'business_float_id', true),
                'approved_for_client_billing_y_n' => get_user_meta($user_id, 'approved_billing', true) === 'yes' ? 'Y' : 'N',
                'business_abn' => get_user_meta($user_id, 'business_abn', true),
                'business_website' => get_user_meta($user_id, 'business_website', true),
                'business_team_users_ids' => implode(', ', $team_user_ids),
                'busineess_address_line_1'      => get_user_meta($user_id, 'address_line1', true),
                'business_address_line_2'       => get_user_meta($user_id, 'address_line2', true),
                'suburb'                       => get_user_meta($user_id, 'suburb', true),
                'state'                        => get_user_meta($user_id, 'state', true),
                'country'                      => get_user_meta($user_id, 'country', true),
                'postcode'                     => get_user_meta($user_id, 'postcode', true),
                'business_currency'            => get_user_meta($user_id, 'business_currency', true),
                'balance' => '',
                'prepaid_limit' => wc_price(get_user_meta($user_id, 'prepaid_limit', true)),
            ];
        }

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_client_billing_businesses' => $total_client_billing_businesses,
            'client_billing_businesses'     => $data,
        ]);
    }

    function custom_get_all_business_balance_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'role'     => 'business_user',
            'number'   => $per_page,
            'paged'    => $page,
        ];

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_all_businesses = $user_query->get_total();
        $data = [];

        foreach ($users as $user) {
            $user_id = $user->ID;
            // $business_team_users_ids = get_user_meta($user_id, 'business_team_users_ids', true);
            // if (is_array($business_team_users_ids)) {
            //     $business_team_users_ids = implode(',', $business_team_users_ids);
            // }

            $user_team_query = new WP_User_Query(array(
                'meta_key' => 'assigned_business_user',
                'meta_value' => $user_id,
            ));
            $team_users = $user_team_query->get_results();
            $team_user_ids = wp_list_pluck($team_users, 'ID');

            $balance = get_user_meta($user_id, 'approved_billing', true) === 'yes' ? '' : get_user_meta($user_id, 'float_balance', true);

            $data[] =[
                'business_name' => get_user_meta($user_id, 'business_name', true),
                'business_id' => $user_id,
                'business_float_id' => get_user_meta($user_id, 'business_float_id', true),
                'approved_for_client_billing_y_n' => get_user_meta($user_id, 'approved_billing', true) === 'yes' ? 'Y' : 'N',
                'business_abn' => get_user_meta($user_id, 'business_abn', true),
                'business_website' => get_user_meta($user_id, 'business_website', true),
                'business_team_users_ids' => implode(', ', $team_user_ids),
                'busineess_address_line_1'      => get_user_meta($user_id, 'address_line1', true),
                'business_address_line_2'       => get_user_meta($user_id, 'address_line2', true),
                'suburb'                       => get_user_meta($user_id, 'suburb', true),
                'state'                        => get_user_meta($user_id, 'state', true),
                'country'                      => get_user_meta($user_id, 'country', true),
                'postcode'                     => get_user_meta($user_id, 'postcode', true),
                'business_currency'            => get_user_meta($user_id, 'business_currency', true),
                'balance' => wc_price($balance),
                'prepaid_limit' => wc_price(get_user_meta($user_id, 'prepaid_limit', true)),
                'top_up_notification_amount' => '',
                'difference_before_topping_up' => '',
            ];
        }

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_all_businesses' => $total_all_businesses,
            'all_businesses'     => $data,
        ]);
    }

    function custom_get_float_order_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));
        //$permissions = current_user_can( 'manage_options' );

        //$nonce = $request->get_header('X-WP-Nonce') ?? $request->get_param('nonce');

        // Verify nonce
        /*if ( $permissions != 1 ) {
            return new WP_REST_Response( [ 'message' => 'Invalid nonce '.$permissions ], 403 );
        }*/

        $args = [
            'limit'   => $per_page,
            'page'    => $page,
            'orderby' => 'date',
            'order'   => 'DESC',
            'payment_method'    => 'float_balance'
        ];

        $orders = wc_get_orders($args);

        $data = [];
        foreach ($orders as $order) {
            $customer_id = $order->get_customer_id();
            $client_billing = (get_user_meta($customer_id, 'approved_billing', true) == 'yes') ? 'Y' : 'N';
            $order_status = ucfirst($order->get_status());


            $activation_expiry_type = $order->get_meta('_selected_activation_expiry_type');

            if($activation_expiry_type == 'set_period'){
                $activation_expiry_type = 'Set Period';
            } else if($activation_expiry_type == 'default'){
                $activation_expiry_type = 'Default';
            } else if($activation_expiry_type == 'no_activation_expiry'){
                $activation_expiry_type = 'No Activation Expiry';
            } else if($activation_expiry_type == 'no_activation_needed'){
                $activation_expiry_type = 'No Activation Needed';
            }
            
            $order_id = $order->get_id();
            $order_number = $order->get_order_number();
            $serialized_data = get_post_meta($order_id, "{$order_number}_pro_details", true);
            $products_data = maybe_unserialize($serialized_data);


            
            $order_date_obj = $order->get_date_created();
            $order_date = $order_date_obj ? $order_date_obj->getTimestamp() : time();
            $activation_expiry = ''; // Default empty

            // Check for activation expiry logic
            if (!empty($products_data) && is_array($products_data)) {
                foreach ($products_data as $product) {
                    if (!isset($product['all_fields']['activation_expiry_type'])) {
                        continue;
                    }
                    
                    $type = $product['all_fields']['activation_expiry_type'];

                    if (in_array($type, ['no_activation_needed', 'no_activation_expiry'])) {
                        $activation_expiry = 'No Activation Expiry';
                    } elseif ($type === 'activation_set_date' && !empty($product['all_fields']['activation_expiry_date'])) {
                        $activation_expiry = $product['all_fields']['activation_expiry_date'];
                    }

                    // 3️⃣ If it's "set_period", calculate dynamically
                    elseif ($type === 'set_period') {
                        
                        $duration = (int) ($product['all_fields']['activation_expiry_duration'] ?? 0);
                        $unit = strtolower($product['all_fields']['activation_expiry_unit'] ?? '');
                        
                        if ($duration > 0 && $unit) {
                            $expiry_timestamp = match ($unit) {
                                'days', 'day' => strtotime("+{$duration} days", $order_date),
                                'weeks', 'week' => strtotime("+{$duration} weeks", $order_date),
                                'months', 'month' => strtotime("+{$duration} months", $order_date),
                                'years', 'year' => strtotime("+{$duration} years", $order_date),
                                default => null,
                            };

                            if ($expiry_timestamp) {
                                $activation_expiry = date('d/m/Y H:i:s', $expiry_timestamp);
                            }
                        }
                    }
                }
            }

            if (empty($activation_expiry)) {
                $activation_expiry = 'No';
            }

            $_gift_card_send = $order->get_meta('_gift_card_send');
            // $activation_expiry = $order->get_meta('_activation_expiry_date');


            $calculated_subtotal = 0.0;
            foreach ( $order->get_items() as $item ) {
                $calculated_subtotal += (float) $item->get_subtotal(); // or get_total() if you want post-discount
            }
            $data[] = [
                'order_number' => $order->get_id(),
                'order_date' => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y') : '',
                'order_time' => $order->get_date_created() ? $order->get_date_created()->date('h:i A') : '',
                'order_name' => $order->get_meta('_order_name') ? $order->get_meta('_order_name') : '',
                'user' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                'business_name' => $order->get_meta('_business_name'),
                'business_id' => $customer_id,
                'business_float_id' => get_user_meta($customer_id, 'business_float_id', true),
                'approved_for_client_billing_y_n' => $client_billing,
                'billing_type' => $order->get_payment_method_title(),
                'order_status' => $order_status,
                'invoice_number' => $order->get_meta('_invoice_number'),
                'payment_status' => $_gift_card_send,
                'total' => wc_price($order->get_total()),
                'total_gift_cards' => wc_price($calculated_subtotal),
                'total_fulfilment' => wc_price($order->get_meta('fullfillment_total')),
                'delivery_cost' => wc_price($order->get_meta('delivery_total')),
                'gst' => wc_price($order->get_meta('_order_gst')),
                'campaign' => $order->get_meta('_campaign'),
                'sender_profile' => $order->get_meta('_sender_name').' ('.$order->get_meta('_sender_email').')',
                'client_reference' => $order->get_meta('_client_reference'),
                'po_number' => $order->get_meta('_po_number'),
                'additional_client_reference' => $order->get_meta('_additional_reference'),
                'order_level_activation_expiry' => $activation_expiry_type,
                'activation_expiry_set_for_this_order' => $activation_expiry
            ];
        }

        $total_args = [
            'return' => 'ids',
            'orderby'=> 'date',
            'order'  => 'DESC',
            'limit'   => -1,
            'payment_method'    => 'float_balance'
        ];

        $total_float_orders = count( wc_get_orders( $total_args ) ); // all statuses

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_float_orders'  => $total_float_orders,
            'float_orders'        => $data,
        ]);
    }

    function custom_get_client_billing_order_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));
        //$permissions = current_user_can( 'manage_options' );

        //$nonce = $request->get_header('X-WP-Nonce') ?? $request->get_param('nonce');

        // Verify nonce
        /*if ( $permissions != 1 ) {
            return new WP_REST_Response( [ 'message' => 'Invalid nonce '.$permissions ], 403 );
        }*/

        $args = [
            'limit'   => $per_page,
            'page'    => $page,
            'orderby' => 'date',
            'order'   => 'DESC',
            'payment_method'    => 'prepaid_credit'
        ];

        $orders = wc_get_orders($args);

        $data = [];
        foreach ($orders as $order) {
            $customer_id = $order->get_customer_id();
            $client_billing = (get_user_meta($customer_id, 'approved_billing', true) == 'yes') ? 'Y' : 'N';
            $order_status = ucfirst($order->get_status());

            $activation_expiry_type = $order->get_meta('_selected_activation_expiry_type');
            $activation_expiry = $order->get_meta('_activation_expiry_date');

            $data[] = [
                'order_number' => $order->get_id(),
                'order_date' => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y') : '',
                'order_time' => $order->get_date_created() ? $order->get_date_created()->date('H:i:s') : '',
                'order_name' => $order->get_meta('_order_name') ? $order->get_meta('_order_name') : '',
                'user' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                'business_name' => $order->get_meta('_business_name'),
                'business_id' => $customer_id,
                'business_float_id' => get_user_meta($customer_id, 'business_float_id', true),
                'approved_for_client_billing_y_n' => $client_billing,
                'billing_type' => '',
                'order_status' => $order_status,
                'invoice_number' => $order->get_meta('_invoice_number'),
                'payment_status' => '',
                'total' => wc_price($order->get_total()),
                'total_gift_cards' => wc_price($order->get_meta('_order_subtotal')),
                'total_fulfilment' => wc_price($order->get_meta('fullfillment_total')),
                'delivery_cost' => wc_price($order->get_meta('delivery_total')),
                'gst' => wc_price($order->get_meta('_order_gst')),
                'campaign' => $order->get_meta('_campaign'),
                'sender_profile' => $order->get_meta('_sender_name').' ('.$order->get_meta('_sender_email').')',
                'client_reference' => $order->get_meta('_client_reference'),
                'po_number' => $order->get_meta('_po_number'),
                'additional_client_reference' => $order->get_meta('_client_reference'),
                'order_level_activation_expiry' => $activation_expiry_type,
                'activation_expiry_set_for_this_order' => $activation_expiry,
                'client_payment_due_date' => '',
            ];
        }

        $total_args = [
            'return' => 'ids',
            'orderby'=> 'date',
            'order'  => 'DESC',
            'limit'   => -1,
            'payment_method' => 'prepaid_credit'
        ];

        $total_client_billing_orders = count( wc_get_orders( $total_args ) ); // all statuses

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_client_billing_orders'  => $total_client_billing_orders,
            'client_billing_orders'        => $data,
        ]);
    }

    function custom_get_user_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));
        $type = $request->get_param('type') ? $request->get_param('type') : '';

        $args = [
            'number'   => $per_page,
            'paged'    => $page
        ];

        if( !empty($type) && $type == 'business' ){
            $args['role'] = 'business_user';
        }

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_users = $user_query->get_total();
        $data = [];

        foreach ($users as $user) {
            $user_id = $user->ID;
            // $user_team_query = new WP_User_Query(array(
            //     'meta_key' => 'assigned_business_user',
            //     'meta_value' => $user_id,
            // ));
            // $team_users = $user_team_query->get_results();
            // $team_user_ids = wp_list_pluck($team_users, 'ID');

            $user_roles = (array) $user->roles;
            $temp_user_type = '';
            if( in_array('business_user', $user_roles) ){
                $temp_user_type = 'Business';
            }else if( in_array('recipients', $user_roles) ){
                $temp_user_type = 'Consumer';
            }

            $data[] =[
                'user_id' => $user_id,
                'status' => '',
                'user_type' => '',
                'first_name' => get_user_meta($user_id, 'first_name', true),
                'surname' => get_user_meta($user_id, 'last_name', true),
                'nickname_team_name' => $user->display_name,
                'email' => $user->user_email,
                'mobile' => get_user_meta($user_id, 'mobile', true),
                'date_of_birth' => get_user_meta($user_id, 'dob', true),
                'state' => get_user_meta($user_id, 'state', true),
                'business_consumer' => $temp_user_type,
                'business_name' => get_user_meta($user_id, 'business_name', true),
                'business_id' => get_user_meta($user_id, 'business_id', true),
                'business_billing_type' => '',
                'approved_for_client_billing' => get_user_meta($user_id, 'approved_billing', true) === 'yes' ? 'Y' : 'N',
                'business_float_id' => get_user_meta($user_id, 'business_float_id', true),
                'business_website' => get_user_meta($user_id, 'business_website', true),
                'business_abn' => get_user_meta($user_id, 'business_abn', true),
                'business_address_line_1' => get_user_meta($user_id, 'address_line1', true),
                'business_address_line_2' => get_user_meta($user_id, 'address_line2', true),
                'suburb' => get_user_meta($user_id, 'suburb', true),
                'state' => get_user_meta($user_id, 'state', true),
                'country' => get_user_meta($user_id, 'country', true),
                'post_code' => get_user_meta($user_id, 'postcode', true),
                'business_currency' => get_user_meta($user_id, 'business_currency', true),
                'user_created_date' => '',
                'float_balance' => get_user_meta($user_id, 'float_balance', true),
                'account_creation_type' => '',
                'time_of_last_login' => '',
                'next_reminder_date' => '',
                'next_reminder' => '',
                'wishlist_items' => '',
                'wishlist_updated_date' => '',
                'email_preferences_y_n' => '',
                'sms_preferences_y_n' => '',
                'personalised_offers_y_n' => '',
                'events_celebrated' => '',
                'hobbies_interests' => '',
                'cards_in_wallet' => '',
                'card_name_1' => '',
                'card_number_1' => '',
                'card_denomination_1' => '',
                'card_status_1' => '',
                'card_name_2' => '',
                'card_number_2' => '',
                'card_denomination_2' => '',
                'card_status_2' => '',
            ];
        }

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_users' => $total_users,
            'users'     => $data,
        ]);
    }

    function custom_get_activation_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'post_type'      => 'gift_card',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query($args);

        $data = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Assuming order ID is saved as post meta with key '_order_id', adjust if different
                $order_number = get_post_meta($post_id, '_order_id', true);
                $order = wc_get_order($order_number);
                if (!$order) {
                    continue;
                }
                $order_date = $order->get_date_created()->date('d-m-Y');
                $order_name = get_the_title($order_number);
                $order_status = '<span class="'.$order->get_status().'">'.ucfirst($order->get_status()).'</span>';
                $gift_card_sku = get_post_meta($post_id, '_product_sku', true);
                $business_name = get_post_meta($post_id, 'business_name', true);

                $product_id = wc_get_product_id_by_sku( $gift_card_sku );
                $supplier_id = get_post_meta($product_id, 'supplier', true);
                $supplier = get_user_meta($supplier_id, 'first_name', true).' '.get_user_meta($supplier_id, 'last_name', true);

                $activation_expiry_type = $order->get_meta('_selected_activation_expiry_type');
                $activation_expiry = $order->get_meta('_activation_expiry_date');

                $delivery_date = '';
                $delivery_time = '';
                if( get_post_meta($post_id, '_scheduled_gift_card_delivery', true) ){
                    $temp_date_time = explode(' ', get_post_meta($post_id, '_scheduled_gift_card_delivery', true));
                    $delivery_date = $temp_date_time[0];
                    $delivery_time = $temp_date_time[1].' '.$temp_date_time[2];
                }

                $activation_expiry_type = get_post_meta($post_id, '_activation_expiry_type', true);
                $activation_expiry_date = '';
                $activation_set_y_n = 'Y';
                $activated_y_n = 'N';
                
                if( !empty($activation_expiry_type) && $activation_expiry_type == 'no_activation_expiry' ){
                    $activation_set_y_n = 'N';
                }

                $current_datetime = current_time('mysql');

                if( !empty($activation_expiry_type) && $activation_expiry_type != 'no_activation_expiry' ){
                    $activation_expiry_date = get_post_meta($post_id, '_activation_expiry_date', true);
                }

                $data[] = [
                    'order_created_date' => $order->get_date_created()->date('d-m-Y'),
                    'order_number' => $order_number,
                    'order_status' => $order_status,
                    'user' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                    'business' => $business_name,
                    'card_name' => get_the_title(),
                    'card_denomination' => '',
                    'card_number' => '',
                    'card_status' => get_post_meta($post_id, '_gift_card_send', true),
                    'delivery_date' => $delivery_date,
                    'delivery_time' => $delivery_time,
                    'delivery_method' => get_post_meta($post_id, '_delivery_method', true),
                    'delivery_email' => '',
                    'delivery_sms' => '',
                    'recipient_email' => (get_post_meta($post_id, '_recipient_email', true)) ? get_post_meta($post_id, '_recipient_email', true) : '',
                    'recipient_mobile' => (get_post_meta($post_id, '_recipient_phone', true) && get_post_meta($post_id, '_recipient_phone', true) != 'undefined') ? get_post_meta($post_id, '_recipient_phone', true) : '',
                    'activation_required_y_n' => $activation_set_y_n,
                    'activation_expiry_date' => $activation_expiry_date,
                    'activated_y_n' => '',
                    'date_activated' => '',
                    'activation_date_missed_y_n' => '',
                    'card_expiry_date' => '',
                    'supplier' => $supplier,
                ];
            }
            wp_reset_postdata();
        }

        // Get total count of GiftCard posts
        $total_giftcards = $query->found_posts;


        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_giftcards' => $total_giftcards,
            'giftcards'     => $data,
        ]);
    }

    function custom_get_expiry_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'post_type'      => 'gift_card',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query($args);

        $data = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Assuming order ID is saved as post meta with key '_order_id', adjust if different
                $order_number = get_post_meta($post_id, '_order_id', true);
                $order = wc_get_order($order_number);
                if (!$order) {
                    continue;
                }
                $order_date = $order->get_date_created()->date('d-m-Y');
                $order_name = get_the_title($order_number);
                $order_status = '<span class="'.$order->get_status().'">'.ucfirst($order->get_status()).'</span>';
                $gift_card_sku = get_post_meta($post_id, '_product_sku', true);
                $business_name = get_post_meta($post_id, 'business_name', true);

                $product_id = wc_get_product_id_by_sku( $gift_card_sku );
                $supplier_id = get_post_meta($product_id, 'supplier', true);
                $supplier = get_user_meta($supplier_id, 'first_name', true).' '.get_user_meta($supplier_id, 'last_name', true);

                $activation_expiry_type = $order->get_meta('_selected_activation_expiry_type');
                $activation_expiry = $order->get_meta('_activation_expiry_date');

                $delivery_date = '';
                $delivery_time = '';
                if( get_post_meta($post_id, '_scheduled_gift_card_delivery', true) ){
                    $temp_date_time = explode(' ', get_post_meta($post_id, '_scheduled_gift_card_delivery', true));
                    $delivery_date = $temp_date_time[0];
                    $delivery_time = $temp_date_time[1].' '.$temp_date_time[2];
                }

                $activation_expiry_type = get_post_meta($post_id, '_activation_expiry_type', true);
                $activation_expiry_date = '';
                $activation_set_y_n = 'Y';
                $activated_y_n = 'N';
                
                if( !empty($activation_expiry_type) && $activation_expiry_type == 'no_activation_expiry' ){
                    $activation_set_y_n = 'N';
                }

                $current_datetime = current_time('mysql');

                if( !empty($activation_expiry_type) && $activation_expiry_type != 'no_activation_expiry' ){
                    $activation_expiry_date = get_post_meta($post_id, '_activation_expiry_date', true);
                }

                $data[] = [
                    'order_created_date' => $order->get_date_created()->date('d-m-Y'),
                    'order_number' => $order_number,
                    'order_status' => $order_status,
                    'user' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                    'business' => $business_name,
                    'card_name' => get_the_title(),
                    'card_denomination' => '',
                    'card_number' => '',
                    'card_status' => get_post_meta($post_id, '_gift_card_send', true),
                    'delivery_date' => $delivery_date,
                    'delivery_time' => $delivery_time,
                    'delivery_method' => get_post_meta($post_id, '_delivery_method', true),
                    'delivery_email' => '',
                    'delivery_sms' => '',
                    'recipient_email' => get_post_meta($post_id, '_recipient_email', true),
                    'recipient_mobile' => get_post_meta($post_id, '_recipient_phone', true),
                    'activation_required_y_n' => $activation_set_y_n,
                    'activation_expiry_date' => $activation_expiry_date,
                    'activated_y_n' => '',
                    'date_activated' => '',
                    'user_that_activated' => '',
                    'activation_date_missed_y_n' => '',
                    'card_expiry_date' => '',
                    'expiry_date_past_y_n' => '',
                    'expired' => '',
                    'supplier' => $supplier,
                    'admin_activated_y_n' => '',
                    'admin_user_activated' => '',
                ];
            }
            wp_reset_postdata();
        }

        // Get total count of GiftCard posts
        $total_giftcards = $query->found_posts;

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_giftcards' => $total_giftcards,
            'giftcards'     => $data,
        ]);
    }

    function custom_get_product_listing_report_extract_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));
        $status = $request->get_param('status') ?? 'any';

        $product_all_status = array(
            'publish' => 'Active',
            'draft'   => 'Awaiting Publishing',
            'wc-deactivated' => 'Deactivated',
        );

        $post_status = array();
        if( $status == 'active' ){
            $post_status = ['publish'];
        }else{
            $post_status = ['publish', 'draft', 'pending', 'wc-deactivated'];
        }

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => $post_status,
        ];

        $query = new WP_Query($args);
        $products = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                $sku_type = get_field('sku_type', $product_id);
                $parent_sku = '';
                $product_sku = $product->get_sku();
                $link_parent = '';

                if( $sku_type == 'Child' ){
                    $parent_sku = get_field('parent_sku', $product_id);
                    if( !empty($parent_sku) ){
                        $link_parent = '✓';
                    }
                }

                $brands = wp_get_post_terms($product_id, 'product_brand', ['fields' => 'names']);
                $brands_image = array();

                foreach ($brands as $brand) {
                    $thumbnail_id = get_term_meta($brand->term_id, 'thumbnail_id', true);

                    if ($thumbnail_id) {
                        $image_src = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
                        if (!empty($image_src[0])) {
                            $brand_image[] = $image_src[0];
                        }
                    }
                }
                $icons = wp_get_post_terms($product_id, 'icons', ['fields' => 'names']);
                $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
                $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);

                $featured_image = wp_get_attachment_url(get_post_thumbnail_id($product_id));
                $gallery_ids = $product->get_gallery_image_ids();
                $gallery_images = [];
                foreach ($gallery_ids as $key => $value){
                    $gallery_images[$i] = isset($value) ? wp_get_attachment_url($value) : '';
                }

                $prod_status = get_post_status($product_id);

                $products[] = [
                    'product_id' => $product_id,
                    'product_status' => isset($product_all_status[$prod_status]) ? $product_all_status[$prod_status] : $prod_status,
                    'live_on_site_y_n' => ($prod_status == 'publish') ? 'Y' : 'N' ,
                    'parent_or_child_sku' => $sku_type,
                    'linked_to_parent' => $link_parent,
                    'parent_sku' => $parent_sku,
                    'sku' => $product_sku,
                    'supplier_sku' => get_post_meta($product_id, '_supplier_sku', true),
                    'gift_card_title' => get_the_title(),
                    'brand' => implode(', ', $brands),
                    'supplier' => get_post_meta($product_id, 'supplier', true),
                    'short_description' => apply_filters('woocommerce_short_description', get_post_field('post_excerpt', $product_id)),
                    'long_description' => apply_filters('the_content', get_post_field('post_content', $product_id)),
                    'terms_conditions' => get_post_meta($product_id, 'terms_conditions', true),
                    'how_to_use' => get_post_meta($product_id, 'how_to_use', true),
                    'expiry_date_time' => get_post_meta($product_id, '_expire_date', true),
                    'gift_card_expiry_type' => get_field('gift_card_expiry_type', $product_id),
                    'gift_card_expiry_date' => get_field('gift_card_expiry_date', $product_id),
                    'gift_card_expiry_period' => get_field('gift_card_expiry_duration', $product_id),
                    'period_type' => get_field('gift_card_expiry_unit', $product_id),
                    'gift_card_activation_type' => get_field('activation_expiry_type', $product_id),
                    'gift_card_activation_date' => get_field('activation_expiry_date', $product_id),
                    'gift_card_activation_period' => get_field('activation_expiry_duration', $product_id),
                    'period_type' => get_field('gift_card_expiry_unit', $product_id),
                    'denomination_type' => get_field('denomination_type', $product_id),
                    'denomination' => get_field('sell_price_lowest_denomination', $product_id),
                    'cost_price' => get_post_meta($product_id, '_cost_price', true),
                    'supplier_fulfilment_price' => get_post_meta($product_id, '_supplier_fullfillment_price', true),
                    'gst' => get_post_meta($product_id, '_gst', true),
                    'gc_fulfilment_costs' => get_post_meta($product_id, 'j_a_c_fulfillment_cost', true),
                    'preset_delivery_class' => get_field('preset_delivery_class', $product_id),
                    'delivery_cost' => get_post_meta($product_id, '_delivery_cost', true),
                    'discounted_price' => get_field('discounted_price', $product_id),
                    'discounted_price' => get_field('discounted_price', $product_id),
                    'discount_valid_from' => get_post_meta($product_id, '_discount_valid_from', true),
                    'discount_valid_to' => get_post_meta($product_id, '_discount_valid_from', true),
                    'icons' => implode(', ', $icons),
                    'tags' => implode(', ', $tags),
                    'categories' => implode(', ', $categories),
                    'featured_placements' => get_field('display_on', $product_id),
                    'extra_header' => get_post_meta($product_id, '_extra_header', true),
                    'add_stock_levels' => get_field('add_stock_levels', $product_id),
                    'stock_levels' => $product->get_stock_status(),
                    'add_transaction_limits' => get_field('add_transaction_limit_checkbox', $product_id),
                    'qty_per_transaction' => get_field('_quantity_per_transaction', $product_id),
                    'total_value_per_transaction' => get_field('_total_value_per_transaction', $product_id),
                    'available_for_all_users' => get_field('available_for_all_users', $product_id),
                    'always_on' => get_field('always_on', $product_id),
                    'onsite_from__date_time' => get_post_meta($product_id, '_onsite_from', true),
                    'onsite_to__date_time' => get_post_meta($product_id, '_onsite_to', true),
                    'created_by_admin' => '',
                    'last_updated_by_admin' => '',
                    'brand_image' => implode(', ', $brands_image),
                    'card_image_1_cover_image' => $featured_image,
                    'card_images' => implode(', ', $gallery_images),
                ];
            }
            wp_reset_postdata();
        }

        // Get total count of GiftCard posts
        $total_products = $query->found_posts;

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_products' => $total_products,
            'products'     => $products,
        ]);
    }

    function custom_get_billing_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        // PT-3.9: Enforce date range to prevent full-history resource exhaustion.
        $raw_from = sanitize_text_field( $request->get_param('date_from') ?? '' );
        $raw_to   = sanitize_text_field( $request->get_param('date_to')   ?? '' );

        $fmt     = 'Y-m-d';
        $dt_from = $raw_from ? DateTime::createFromFormat( $fmt, $raw_from ) : false;
        $dt_to   = $raw_to   ? DateTime::createFromFormat( $fmt, $raw_to )   : false;

        if ( ! $dt_from || ! $dt_to || $dt_from > $dt_to ) {
            $dt_to   = new DateTime( 'today' );
            $dt_from = ( new DateTime( 'today' ) )->modify( '-30 days' );
        }

        if ( $dt_from->diff( $dt_to )->days > 90 ) {
            $dt_from = clone $dt_to;
            $dt_from->modify( '-90 days' );
        }

        $date_range = strtotime( $dt_from->format( 'Y-m-d' ) . ' 00:00:00' )
            . '...'
            . strtotime( $dt_to->format( 'Y-m-d' ) . ' 23:59:59' );

        $args = [
            'limit'        => $per_page,
            'page'         => $page,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'date_created' => $date_range,
        ];

        $orders = wc_get_orders($args);

        $data = [];
        foreach ($orders as $order) {
            $customer_id = $order->get_customer_id();
            $client_billing = (get_user_meta($customer_id, 'approved_billing', true) == 'yes') ? 'Y' : 'N';
            $order_status = '<span class="'.$order->get_status().'">'.ucfirst($order->get_status()).'</span>';

            $activation_expiry_type = $order->get_meta('_selected_activation_expiry_type');
            $activation_expiry = $order->get_meta('_activation_expiry_date');

            $last_oid = 0;
            $last_gid = 0;
            $temp_fullfillment_price = 0;

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product) continue;

                $supplier_id = $product->get_meta('supplier');
                $supplier = get_user_meta($supplier_id, 'first_name', true).' '.get_user_meta($supplier_id, 'last_name', true);
                
                $supplier_fullfillment_price = $product->get_meta('_supplier_fullfillment_price');
                $gc_fullfillment_price = $product->get_meta('j_a_c_fulfillment_cost');
                $gc_delivery_price = $product->get_meta('_delivery_cost');
                $gst = $product->get_meta('_gst');
                
                $total_buy_price = $product->get_meta('_total_buy_price');
                $total_buy_price_gst = $product->get_meta('_total_buy_price_gst');

                $gift_card_id = $item->get_meta('_gift_card_post_id');
                $gift_card_delivery_date = get_post_meta($gift_card_id, '_scheduled_gift_card_delivery', true) ?? '';

                $brands = wp_get_post_terms($product->get_id(), 'product_brand', ['fields' => 'names']);

                $gift_card_status = get_post_meta($gift_card_id, '_gift_card_send', true) ?? '';

                $gift_card_number_enc = get_post_meta($gift_card_id, '_gift_card_number_enc', true);
                if( !empty($gift_card_number_enc) ){
                    try {
                        $gift_card_number = decrypt_giftcard_no($gift_card_number_enc);
                    } catch (Exception $e) {
                        $gift_card_number = 'Invalid / Corrupted Card';
                    }
                }else{
                    $gift_card_number = get_post_meta($gift_card_id, '_gift_card_number', true);
                }

                if( (float)$supplier_fullfillment_price > 0 ){
                    $temp_fullfillment_price = $temp_fullfillment_price + $supplier_fullfillment_price;
                }

                if( $last_oid == $order->get_id() ){
                    if( $last_gid > 0 ){
                        $data[$last_oid.'_'.$last_gid]['order_total_supplier_fulfiment_cost'] = wc_price($temp_fullfillment_price);
                    }    
                }
                $last_oid = $order->get_id();
                $last_gid = $gift_card_id;

                $data[$order->get_id().'_'.$gift_card_id] = [
                    'order_number' => $order->get_id(),
                    'order_date' => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y') : '',
                    'order_time_' => $order->get_date_created() ? $order->get_date_created()->date('H:i:s') : '',
                    'month_of_order' => $order->get_date_created() ? $order->get_date_created()->date('m') : '',
                    'order_name' => $order->get_meta('_order_name') ? $order->get_meta('_order_name') : '',
                    'user' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                    'user_type' => '',
                    'business_name' => $order->get_meta('_business_name'),
                    'business_id' => $customer_id,
                    'business_float_id' => get_user_meta($customer_id, 'business_float_id', true),
                    'approved_for_client_billing_y_n' => get_user_meta($customer_id, 'approved_billing', true) === 'yes' ? 'Y' : 'N',
                    'payment_type' => $order->get_payment_method_title(),
                    'order_status' => $order_status,
                    'invoice_number' => $order->get_meta('_invoice_number'),
                    'payment_status' => '',
                    'order_total' => wc_price($order->get_total()),
                    'order_total_gift_cards' => wc_price($order->get_meta('_order_subtotal')),
                    'order_total_fulfilment' => wc_price($order->get_meta('fullfillment_total')),
                    'order_total_delivery_cost' => wc_price($order->get_meta('delivery_total')),
                    'order_total_gst' => wc_price($order->get_meta('_order_gst')),
                    'order_total_supplier_buy_price' => '',
                    'order_total_supplier_fulfiment_cost' => wc_price($temp_fullfillment_price),
                    'order_total_supplier_delivery_cost' => '',
                    'order_total_supplier_gst_cost' => '',
                    
                    'gift_card_brand' => implode(',', $brands),
                    'gift_card_name' => $item->get_meta('_gift_card_name'),
                    'gift_card_sku' => $item->get_meta('_gift_card_sku'),
                    'gift_card_supplier_sku' => $product->get_meta('_supplier_sku'),
                    'gift_card_number' => !empty($gift_card_number) ? 'XXXX XXXX' : '',
                    'card_type_variable_fixed' => $product->get_meta('denomination_type'),
                    'gift_card_denomination' => '',
                    'gift_card_supplier_' => $supplier,
                    'gift_card_set_sell_price' => '',
                    'gift_card_price' => '',
                    'offer_price_y_n' => '',
                    'gift_card_offer_price' => '',
                    'gift_card_buy_price' => '',
                    'gift_card_margin' => '',
                    'gift_card_margin' => '',
                    'gc_gift_card_fulfilment_price' => wc_price($gc_fullfillment_price),
                    'gift_card_supplier_fulfilment_cost' => wc_price($supplier_fullfillment_price),
                    'gift_cards_gift_card_delivery_price' => wc_price($gc_delivery_price),
                    'gift_card_supplier_delivery_cost_' => '',
                    'gift_cards_gst' => wc_price($gst),
                    'supplier_gst' => '',
                    'gift_card_total_buy_price' => wc_price($total_buy_price),
                    'gift_card_total_buy_price_inc_gst' => wc_price($total_buy_price_gst),
                    'gift_card_total_sell_price' => '',
                    'gift_card_total_margin' => '',
                    'gift_card_total_margin' => '',
                    'gift_card_status' => '',
                    'gift_card_delivery_date' => $gift_card_delivery_date,

                    'campaign' => $order->get_meta('_campaign'),
                    'sender_profile' => $order->get_meta('_sender_name').' '.$order->get_meta('_sender_email'),
                    'client_reference' => $order->get_meta('_client_reference'),
                    'po_number' => $order->get_meta('_po_number'),
                    'additional_client_reference' => $order->get_meta('_additional_reference'),
                    'order_level_activation_expiry' => $activation_expiry_type,
                    'activation_expiry_set_for_this_order' => $activation_expiry,
                    'client_payment_due_date' => '',
                ];
            }
        }

        $total_args = [
            'return'       => 'ids',
            'orderby'      => 'date',
            'order'        => 'DESC',
            'limit'        => -1,
            'date_created' => $date_range,
        ];

        $total_orders = count( wc_get_orders( $total_args ) );

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_orders'  => $total_orders,
            'orders'        => array_values($data),
        ]);
    }

    // =========================================================================
    // Individual Business Balance Statement
    // =========================================================================
    function custom_get_individual_business_balance_statement_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $business_users = get_users([
            'role__in' => ['business_user', 'external_business_admin'],
            'fields'   => 'all',
        ]);

        $all_orders = [];

        foreach ($business_users as $user) {
            $user_id           = $user->ID;
            $business_name     = get_user_meta($user_id, 'business_name', true) ?: $user->display_name;
            $business_float_id = get_user_meta($user_id, 'business_float_id', true);
            $approved_billing  = get_user_meta($user_id, 'approved_billing', true) === 'yes' ? 'Y' : 'N';
            $billing_type      = get_user_meta($user_id, 'billing_type', true);

            $user_orders = wc_get_orders([
                'customer_id' => $user_id,
                'limit'       => -1,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ]);

            foreach ($user_orders as $order) {
                $all_orders[] = [
                    'business_name'               => $business_name,
                    'business_id'                 => $user_id,
                    'approved_for_client_billing' => $approved_billing,
                    'business_billing_type'       => $billing_type,
                    'date_time'                   => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y H:i:s') : '',
                    'user'                        => $user->display_name,
                    'balance_type'                => $order->get_meta('_balance_type'),
                    'action'                      => $order->get_meta('_action_type'),
                    'business_float_id'           => $business_float_id,
                    'order_number'                => $order->get_order_number(),
                    'invoice_number'              => $order->get_meta('_invoice_number'),
                    'status'                      => ucfirst($order->get_status()),
                    'amount'                      => number_format((float) $order->get_total(), 2, '.', ''),
                    'reference'                   => $order->get_meta('_client_reference'),
                    'balance'                     => $order->get_meta('_float_balance_after'),
                ];
            }
        }

        $total_orders = count($all_orders);
        $offset       = ($page - 1) * $per_page;
        $data         = array_slice($all_orders, $offset, $per_page);

        $business_name     = $user ? (get_user_meta($user_id, 'business_name', true) ?: $user->display_name) : '';
        $business_float_id = get_user_meta($user_id, 'business_float_id', true);
        $approved_billing  = get_user_meta($user_id, 'approved_billing', true) === 'yes' ? 'Y' : 'N';
        $billing_type      = get_user_meta($user_id, 'billing_type', true);

        foreach ($orders as $order) {
            $data[] = [
                'business_name'              => $business_name,
                'business_id'                => $user_id,
                'approved_for_client_billing' => $approved_billing,
                'business_billing_type'      => $billing_type,
                'date_time'                  => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y H:i:s') : '',
                'user'                       => $user ? $user->display_name : '',
                'balance_type'               => $order->get_meta('_balance_type'),
                'action'                     => $order->get_meta('_action_type'),
                'business_float_id'          => $business_float_id,
                'order_number'               => $order->get_order_number(),
                'invoice_number'             => $order->get_meta('_invoice_number'),
                'status'                     => ucfirst($order->get_status()),
                'amount'                     => number_format((float) $order->get_total(), 2, '.', ''),
                'reference'                  => $order->get_meta('_client_reference'),
                'balance'                    => $order->get_meta('_float_balance_after'),
            ];
        }

        return rest_ensure_response([
            'page'         => $page,
            'per_page'     => $per_page,
            'total_orders' => $total_orders,
            'orders'       => $data,
        ]);
    }

    // =========================================================================
    // Credit Card Payment Report
    // =========================================================================
    function custom_get_credit_card_payment_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $credit_card_gateways = ['stripe', 'stripe_cc', 'woocommerce_payments', 'square_credit_card', 'paypal', 'braintree_credit_card', 'authorizenet_aim', 'eway', 'paymentexpress_pxpay', 'payu'];

        $args = [
            'limit'          => $per_page,
            'page'           => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'payment_method' => $credit_card_gateways,
        ];

        $orders = wc_get_orders($args);
        $data   = [];

        foreach ($orders as $order) {
            $customer_id     = $order->get_customer_id();
            $gift_card_total = (float) $order->get_total() - (float) $order->get_meta('fullfillment_total') - (float) $order->get_meta('delivery_total');
            if ($gift_card_total < 0) {
                $gift_card_total = (float) $order->get_total();
            }

            $data[] = [
                'order_date'           => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y') : '',
                'order_time'           => $order->get_date_created() ? $order->get_date_created()->date('H:i:s') : '',
                'order_number'         => $order->get_order_number(),
                'order_name'           => $order->get_meta('_order_name'),
                'business_name'        => $order->get_meta('_business_name'),
                'business_id'          => $customer_id,
                'payment_method'       => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title(),
                'transaction_id'       => $order->get_transaction_id(),
                'order_status'         => '<span class="' . $order->get_status() . '">' . ucfirst($order->get_status()) . '</span>',
                'invoice_number'       => $order->get_meta('_invoice_number'),
                'order_total'          => wc_price($order->get_total()),
                'gift_cards_total'     => wc_price($gift_card_total),
                'fulfilment_total'     => wc_price($order->get_meta('fullfillment_total')),
                'delivery_total'       => wc_price($order->get_meta('delivery_total')),
                'gst'                  => wc_price($order->get_meta('_order_gst')),
                'campaign'             => $order->get_meta('_campaign'),
                'po_number'            => $order->get_meta('_po_number'),
                'client_reference'     => $order->get_meta('_client_reference'),
                'sender_profile'       => $order->get_meta('_sender_name') . ' (' . $order->get_meta('_sender_email') . ')',
            ];
        }

        $total_args   = ['return' => 'ids', 'limit' => -1, 'payment_method' => $credit_card_gateways];
        $total_orders = count(wc_get_orders($total_args));

        return rest_ensure_response([
            'page'         => $page,
            'per_page'     => $per_page,
            'total_orders' => $total_orders,
            'orders'       => $data,
        ]);
    }

    // =========================================================================
    // Refunds / Cancellation Report
    // =========================================================================
    function custom_get_refunds_cancellation_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'limit'   => $per_page,
            'page'    => $page,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => ['refunded', 'cancelled'],
        ];

        $orders = wc_get_orders($args);
        $data   = [];

        foreach ($orders as $order) {
            $customer_id  = $order->get_customer_id();
            $order_status = $order->get_status();

            $refunds        = $order->get_refunds();
            $total_refunded = 0;
            $refund_reason  = '';
            $refund_date    = '';
            foreach ($refunds as $refund) {
                $total_refunded += abs((float) $refund->get_amount());
                if (empty($refund_reason) && $refund->get_reason()) {
                    $refund_reason = $refund->get_reason();
                }
                if (empty($refund_date) && $refund->get_date_created()) {
                    $refund_date = $refund->get_date_created()->date('d-m-Y H:i:s');
                }
            }

            $gift_card_total = (float) $order->get_total() - (float) $order->get_meta('fullfillment_total') - (float) $order->get_meta('delivery_total');
            if ($gift_card_total < 0) {
                $gift_card_total = (float) $order->get_total();
            }

            $data[] = [
                'order_date'       => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y') : '',
                'order_time'       => $order->get_date_created() ? $order->get_date_created()->date('H:i:s') : '',
                'order_number'     => $order->get_order_number(),
                'order_name'       => $order->get_meta('_order_name'),
                'business_name'    => $order->get_meta('_business_name'),
                'business_id'      => $customer_id,
                'order_status'     => '<span class="' . $order_status . '">' . ucfirst($order_status) . '</span>',
                'payment_method'   => $order->get_payment_method_title(),
                'invoice_number'   => $order->get_meta('_invoice_number'),
                'order_total'      => wc_price($order->get_total()),
                'gift_cards_total' => wc_price($gift_card_total),
                'total_refunded'   => wc_price($total_refunded),
                'refund_reason'    => $refund_reason,
                'refund_date'      => $refund_date,
                'gst'              => wc_price($order->get_meta('_order_gst')),
                'campaign'         => $order->get_meta('_campaign'),
                'po_number'        => $order->get_meta('_po_number'),
                'client_reference' => $order->get_meta('_client_reference'),
                'sender_profile'   => $order->get_meta('_sender_name') . ' (' . $order->get_meta('_sender_email') . ')',
            ];
        }

        $total_args   = ['return' => 'ids', 'limit' => -1, 'status' => ['refunded', 'cancelled']];
        $total_orders = count(wc_get_orders($total_args));

        return rest_ensure_response([
            'page'         => $page,
            'per_page'     => $per_page,
            'total_orders' => $total_orders,
            'orders'       => $data,
        ]);
    }

    // =========================================================================
    // Audit Report
    // =========================================================================
    function custom_get_audit_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'post_type'      => 'gift_card',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query($args);
        $data  = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id  = get_the_ID();
                $order_id = get_post_meta($post_id, '_order_id', true);
                $order    = $order_id ? wc_get_order($order_id) : null;

                if (!$order) {
                    continue;
                }

                $customer_id  = $order->get_customer_id();
                $order_status = $order->get_status();
                $card_status  = get_post_meta($post_id, '_gift_card_send', true);

                $delivery_date = 'Instant';
                $delivery_time = 'Instant';
                $scheduled     = get_post_meta($post_id, '_scheduled_gift_card_delivery', true);
                if (!empty($scheduled)) {
                    $parts         = explode(' ', $scheduled);
                    $delivery_date = $parts[0] ?? 'Instant';
                    $delivery_time = isset($parts[1], $parts[2]) ? $parts[1] . ' ' . $parts[2] : ($parts[1] ?? 'Instant');
                }

                $data[] = [
                    'order_date'             => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y') : '',
                    'order_time'             => $order->get_date_created() ? $order->get_date_created()->date('H:i:s') : '',
                    'order_number'           => $order->get_order_number(),
                    'order_name'             => $order->get_meta('_order_name'),
                    'business_name'          => get_post_meta($post_id, 'business_name', true),
                    'business_id'            => $customer_id,
                    'order_status'           => '<span class="' . $order_status . '">' . ucfirst($order_status) . '</span>',
                    'gift_card_post_id'      => $post_id,
                    'gift_card_name'         => get_post_meta($post_id, '_gift_card_name', true),
                    'gift_card_sku'          => get_post_meta($post_id, '_product_sku', true),
                    'gift_card_denomination' => wc_price(get_post_meta($post_id, '_price', true)),
                    'gift_card_status'       => $card_status,
                    'delivery_method'        => get_post_meta($post_id, '_delivery_method', true),
                    'delivery_email'         => get_post_meta($post_id, '_recipient_email', true),
                    'delivery_date'          => $delivery_date,
                    'delivery_time'          => $delivery_time,
                    'sender_name'            => get_post_meta($post_id, '_sender_name', true),
                    'sender_email'           => get_post_meta($post_id, '_sender_email', true),
                    'recipient_name'         => get_post_meta($post_id, '_recipient_name', true),
                    'payment_method'         => $order->get_payment_method_title(),
                    'invoice_number'         => $order->get_meta('_invoice_number'),
                    'campaign'               => $order->get_meta('_campaign'),
                    'activation_expiry_type' => get_post_meta($post_id, '_activation_expiry_type', true),
                    'activation_expiry_date' => get_post_meta($post_id, '_activation_expiry_date', true),
                    'gift_card_expiry_date'  => get_post_meta($post_id, '_gift_card_expiry_date', true),
                ];
            }
            wp_reset_postdata();
        }

        return rest_ensure_response([
            'page'             => $page,
            'per_page'         => $per_page,
            'total_gift_cards' => $query->found_posts,
            'records'          => $data,
        ]);
    }

    // =========================================================================
    // Brand Listing Report
    // =========================================================================
    function custom_get_brand_listing_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'taxonomy'   => 'product_brand',
            'hide_empty' => false,
            'number'     => $per_page,
            'offset'     => ($page - 1) * $per_page,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ];

        $terms       = get_terms($args);
        $total_terms = (int) wp_count_terms(['taxonomy' => 'product_brand', 'hide_empty' => false]);
        $data        = [];

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $active_args  = [
                    'post_type'   => 'product',
                    'post_status' => 'publish',
                    'tax_query'   => [[
                        'taxonomy' => 'product_brand',
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ]],
                    'fields'      => 'ids',
                    'numberposts' => -1,
                ];
                $active_count = count(get_posts($active_args));

                $supplier_id   = get_term_meta($term->term_id, 'supplier', true);
                $supplier_name = '';
                if ($supplier_id) {
                    $supplier_user = get_user_by('ID', $supplier_id);
                    $supplier_name = $supplier_user ? $supplier_user->display_name : '';
                }

                $thumbnail_id  = get_term_meta($term->term_id, 'thumbnail_id', true);
                $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';

                $data[] = [
                    'brand_id'          => $term->term_id,
                    'brand_name'        => $term->name,
                    'brand_slug'        => $term->slug,
                    'brand_description' => $term->description,
                    'total_products'    => $term->count,
                    'active_products'   => $active_count,
                    'supplier'          => $supplier_name,
                    'brand_thumbnail'   => $thumbnail_url,
                ];
            }
        }

        return rest_ensure_response([
            'page'         => $page,
            'per_page'     => $per_page,
            'total_brands' => $total_terms,
            'brands'       => $data,
        ]);
    }

    // =========================================================================
    // Supplier Product Report
    // =========================================================================
    function custom_get_supplier_product_report_paginated(WP_REST_Request $request) {
        $page               = max(1, intval($request->get_param('page') ?? 1));
        $per_page           = max(1, intval($request->get_param('per_page') ?? 50));
        $supplier_id_filter = intval($request->get_param('supplier_id') ?? 0);

        $meta_query = $supplier_id_filter > 0
            ? [['key' => 'supplier', 'value' => $supplier_id_filter]]
            : [['key' => 'supplier', 'compare' => 'EXISTS']];

        $args = [
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => $meta_query,
        ];

        $query = new WP_Query($args);
        $data  = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $product = wc_get_product($post_id);

                if (!$product) {
                    continue;
                }

                $supplier_id   = $product->get_meta('supplier');
                $supplier_name = '';
                if ($supplier_id) {
                    $supplier_user = get_user_by('ID', $supplier_id);
                    $supplier_name = $supplier_user ? $supplier_user->display_name : '';
                }

                $brands      = wp_get_post_terms($post_id, 'product_brand', ['fields' => 'names']);
                $brand_names = !is_wp_error($brands) ? implode(', ', $brands) : '';

                $min_price         = $product->get_meta('variable_range_from') ?: $product->get_regular_price();
                $max_price         = $product->get_meta('variable_range_to') ?: $product->get_regular_price();
                $denomination_type = $product->get_meta('denomination_type') ?: 'fixed';

                $data[] = [
                    'product_id'           => $post_id,
                    'product_name'         => $product->get_name(),
                    'product_sku'          => $product->get_sku(),
                    'product_status'       => $product->get_status(),
                    'supplier_id'          => $supplier_id,
                    'supplier_name'        => $supplier_name,
                    'brand'                => $brand_names,
                    'denomination_type'    => $denomination_type,
                    'min_price'            => wc_price($min_price),
                    'max_price'            => wc_price($max_price),
                    'buy_price'            => wc_price($product->get_meta('_total_buy_price')),
                    'fulfilment_cost'      => wc_price($product->get_meta('j_a_c_fulfillment_cost')),
                    'delivery_cost'        => wc_price($product->get_meta('_delivery_cost')),
                    'gst'                  => wc_price($product->get_meta('_gst')),
                    'supplier_sku'         => $product->get_meta('_supplier_sku'),
                    'parent_sku'           => $product->get_meta('parent_sku'),
                    'redemption_info'      => $product->get_meta('redemptionInfo'),
                    'is_blackhawk_product' => !empty($product->get_meta('_is_blackhawk_product')) ? 'Y' : 'N',
                ];
            }
            wp_reset_postdata();
        }

        return rest_ensure_response([
            'page'           => $page,
            'per_page'       => $per_page,
            'total_products' => $query->found_posts,
            'products'       => $data,
        ]);
    }

    // =========================================================================
    // Business Report
    // =========================================================================
    function custom_get_business_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $user_query       = new WP_User_Query(['role' => 'business_user', 'number' => $per_page, 'paged' => $page]);
        $users            = $user_query->get_results();
        $total_businesses = $user_query->get_total();
        $data             = [];

        foreach ($users as $user) {
            $user_id = $user->ID;

            $order_ids    = wc_get_orders(['customer_id' => $user_id, 'return' => 'ids', 'limit' => -1, 'status' => ['completed', 'processing']]);
            $total_orders = count($order_ids);
            $total_spend  = 0.0;
            foreach ($order_ids as $oid) {
                $o = wc_get_order($oid);
                if ($o) {
                    $total_spend += (float) $o->get_total();
                }
            }

            $team_query    = new WP_User_Query(['meta_key' => 'assigned_business_user', 'meta_value' => $user_id]);
            $team_user_ids = wp_list_pluck($team_query->get_results(), 'ID');

            $data[] = [
                'business_id'                     => $user_id,
                'business_name'                   => get_user_meta($user_id, 'business_name', true) ?: $user->display_name,
                'business_float_id'               => get_user_meta($user_id, 'business_float_id', true),
                'approved_for_client_billing_y_n' => get_user_meta($user_id, 'approved_billing', true) === 'yes' ? 'Y' : 'N',
                'billing_type'                    => get_user_meta($user_id, 'approved_billing', true) === 'yes' ? 'Client Billing' : 'Float',
                'business_abn'                    => get_user_meta($user_id, 'business_abn', true),
                'business_website'                => get_user_meta($user_id, 'business_website', true),
                'email'                           => $user->user_email,
                'mobile'                          => get_user_meta($user_id, 'mobile', true),
                'address_line_1'                  => get_user_meta($user_id, 'address_line1', true),
                'address_line_2'                  => get_user_meta($user_id, 'address_line2', true),
                'suburb'                          => get_user_meta($user_id, 'suburb', true),
                'state'                           => get_user_meta($user_id, 'state', true),
                'country'                         => get_user_meta($user_id, 'country', true),
                'postcode'                        => get_user_meta($user_id, 'postcode', true),
                'business_currency'               => get_user_meta($user_id, 'business_currency', true),
                'float_balance'                   => wc_price(get_user_meta($user_id, 'float_balance', true)),
                'prepaid_limit'                   => wc_price(get_user_meta($user_id, 'prepaid_limit', true)),
                'team_user_ids'                   => implode(', ', $team_user_ids),
                'total_orders'                    => $total_orders,
                'total_spend'                     => wc_price($total_spend),
                'account_created_date'            => $user->user_registered ? date('d-m-Y', strtotime($user->user_registered)) : '',
            ];
        }

        return rest_ensure_response([
            'page'             => $page,
            'per_page'         => $per_page,
            'total_businesses' => $total_businesses,
            'businesses'       => $data,
        ]);
    }

    function custom_get_supplier_order_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));

        $args = [
            'limit'  => $per_page,
            'page'   => $page,
            'orderby'=> 'date',
            'order'  => 'DESC',
        ];

        $orders = wc_get_orders($args);

        $data = [];
        foreach ($orders as $order) {
            $customer_id = $order->get_customer_id();
            $client_billing = (get_user_meta($customer_id, 'approved_billing', true) == 'yes') ? 'Y' : 'N';
            $order_status = '<span class="'.$order->get_status().'">'.ucfirst($order->get_status()).'</span>';

            $order_id = $order->get_id();
            $order_number = $order->get_order_number();
            $serialized_data = get_post_meta($order_id, "{$order_number}_pro_details", true);
            $products_data = maybe_unserialize($serialized_data);

            // Map product ID => denomination_type
            $denomination_map = [];
            $total_buy_price_map = [];
            $supplier_fullfillment_price_map = [];
            $supplier_delivery_map = [];
            $supplier_gst_map = [];
            if (!empty($products_data) && is_array($products_data)) {
                foreach ($products_data as $prod) {
                    // Check for both 'product_id' or 'id'
                    $pid = $prod['product_id'] ?? $prod['id'] ?? 0;
                    if ($pid) {
                        $denomination_map[$pid] = $prod['all_fields']['denomination_type'] ?? '';
                        $total_buy_price_map[$pid] = $prod['all_fields']['_total_buy_price'] ?? '';
                        $supplier_fullfillment_price_map[$pid] = $prod['all_fields']['_supplier_fullfillment_price'] ?? '';
                        $supplier_delivery_map[$pid] = $prod['all_fields']['_delivery_cost'] ?? '';
                        $supplier_gst_map[$pid] = $prod['all_fields']['_gst'] ?? '';
                    }
                }
            }


            $activation_expiry_type = $order->get_meta('_selected_activation_expiry_type');
            $activation_expiry = $order->get_meta('_activation_expiry_date');

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product) continue;

                $supplier_id = $product->get_meta('supplier');

                // Child SKUs often don't have their own supplier set — fall back to the parent product's.
                if ( empty( $supplier_id ) ) {
                    $parent_sku_for_supplier = $product->get_meta('parent_sku');
                    if ( ! empty( $parent_sku_for_supplier ) ) {
                        $parent_product_id_for_supplier = wc_get_product_id_by_sku( $parent_sku_for_supplier );
                        if ( $parent_product_id_for_supplier ) {
                            $supplier_id = get_post_meta( $parent_product_id_for_supplier, 'supplier', true );
                        }
                    }
                }

                if( $supplier_id ){

                    $product_id = $item->get_product_id();
                    $denomination_type = $denomination_map[$product_id] ?? '';
                    $total_buy_price = $total_buy_price_map[$product_id] ?? '';
                    $supplier_fullfillment_price = $supplier_fullfillment_price_map[$product_id] ?? '';
                    $supplier_delivery = $supplier_delivery_map[$product_id] ?? '';
                    $supplier_gst = $supplier_gst_map[$product_id] ?? '';

                    $supplier = get_user_meta($supplier_id, 'first_name', true).' '.get_user_meta($supplier_id, 'last_name', true);
                    $supplier_po = (get_user_meta($supplier_id, 'postcode', true)) ? get_user_meta($supplier_id, 'postcode', true) : '';
                    
                    // $supplier_fullfillment_price = $product->get_meta('_supplier_fullfillment_price');
                    // $gc_fullfillment_price = $product->get_meta('j_a_c_fulfillment_cost');
                    // $gc_delivery_price = $product->get_meta('_delivery_cost');
                    // $gst = $product->get_meta('_gst');
                    
                    // $total_buy_price = $product->get_meta('_total_buy_price');
                    // $total_buy_price_gst = $product->get_meta('_total_buy_price_gst');

                    $gift_card_id = $item->get_meta('_gift_card_post_id');
                    $gift_card_delivery_date = get_post_meta($gift_card_id, '_scheduled_gift_card_delivery', true) ?? '';
                    $gift_card_status = get_post_meta($gift_card_id, '_gift_card_send', true) ? get_post_meta($gift_card_id, '_gift_card_send', true) : 'Draft';

                    $gift_card_number_enc = get_post_meta($gift_card_id, '_gift_card_number_enc', true);
                    $gift_card_number = '';

                    if( !empty($gift_card_number_enc) ){
                        try {
                            $gift_card_number = decrypt_giftcard_no($gift_card_number_enc);
                        } catch (Exception $e) {
                            $gift_card_number = 'Invalid / Corrupted Card';
                        }
                    }else{
                        $gift_card_number = get_post_meta($gift_card_id, '_gift_card_number', true);
                        try {
                            update_post_meta($gift_card_id, '_gift_card_number_enc', encrypt_giftcard_no($gift_card_number));
                        } catch (Exception $e) {
                            // Encryption unavailable — leave _gift_card_number_enc unset, next run will retry.
                        }
                    }

                    $data[] = [
                        'supplier' => $supplier,
                        'order_number' => $order->get_id(),
                        'supplier_po' => $supplier_po,
                        'order_date' => $order->get_date_created()->date('Y-m-d'),
                        'order_status' => $order_status,
                        'card_name' => $item->get_meta('_gift_card_name'),
                        'card_number' => !empty($gift_card_number) ? 'XXXX XXXX' : '',
                        'card_type_variable_fixed' => $denomination_type,
                        'denomination' => $item->get_total(),
                        'card_buy_price' => wc_price($total_buy_price),
                        'card_supplier_fulfilment_cost' => wc_price($supplier_fullfillment_price),
                        'card_supplier_delivery_cost' => wc_price($supplier_delivery),
                        'gst' => wc_price($supplier_gst),
                        'card_status' => $gift_card_status,
                    ];
                }
            }
        }

        $total_args = [
            'return' => 'ids',
            'orderby'=> 'date',
            'order'  => 'DESC',
            'limit'   => -1,
        ];

        $total_orders = count( wc_get_orders( $total_args ) );

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_orders'  => $total_orders,
            'orders'        => $data,
        ]);
    }

    function custom_get_supplier_billing_report_paginated(WP_REST_Request $request) {
        $page     = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(200, max(1, intval($request->get_param('per_page') ?? 50)));
        
        $args = [
            'limit'  => $per_page,
            'page'   => $page,
            'orderby'=> 'date',
            'order'  => 'DESC',
        ];

        $orders = wc_get_orders($args);

        $data = [];
        foreach ($orders as $order) {

            $order_id = $order->get_id();
            $order_number = $order->get_order_number();

            $serialized_customer_data = get_post_meta($order_id, "{$order_number}_customer_details", true);
            $serialized_pro_data = get_post_meta($order_id, "{$order_number}_pro_details", true);
            $customer_data = maybe_unserialize($serialized_customer_data);
            $pro_data = maybe_unserialize($serialized_pro_data);
            $customer_id = $order->get_customer_id();
            $client_billing = (get_user_meta($customer_id, 'approved_billing', true) == 'yes') ? 'Y' : 'N';
            $order_status = '<span class="'.$order->get_status().'">'.ucfirst($order->get_status()).'</span>';

            $activation_expiry_type = $order->get_meta('_selected_activation_expiry_type');
            $activation_expiry = $order->get_meta('_activation_expiry_date');

            $last_oid = 0;
            $last_gid = 0;
            // $temp_fullfillment_price = 0;
            // $temp_fulfilment_price = 0;
            // $temp_delivery_cost = 0;


            $temp_supplier_fulfilment_total = 0;
            $temp_supplier_buy_total = 0;
            $temp_delivery_total = 0;
            $temp_supplier_gst = 0;

            // Per-product field map (keyed by product ID) so each line item's row shows ITS OWN
            // product's fields, instead of always reading index [0] (the first product in the order).
            $pro_fields_by_product_id = [];

            if (is_array($pro_data) && !empty($pro_data)) {
                foreach ($pro_data as $pro_item) {
                    $fields = $pro_item['all_fields'] ?? [];
                    $pid = $pro_item['product_id'] ?? $pro_item['id'] ?? 0;
                    if ($pid) {
                        $pro_fields_by_product_id[$pid] = $fields;
                    }

                    if (isset($fields['_supplier_fullfillment_price'])) {
                        $temp_supplier_fulfilment_total += (float) $fields['_supplier_fullfillment_price'];
                    }

                    if (isset($fields['_total_buy_price'])) {
                        $temp_supplier_buy_total += (float) $fields['_total_buy_price'];
                    }

                    if (isset($fields['_delivery_cost'])) {
                        $temp_delivery_total += (float) $fields['_delivery_cost'];
                    }

                    if (isset($fields['_gst'])) {
                        $temp_supplier_gst += (float) $fields['_gst'];
                    }
                }
            }

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product) continue;

                $supplier_id = $product->get_meta('supplier');

                // Child SKUs often don't have their own supplier set — fall back to the parent product's.
                if ( empty( $supplier_id ) ) {
                    $parent_sku_for_supplier = $product->get_meta('parent_sku');
                    if ( ! empty( $parent_sku_for_supplier ) ) {
                        $parent_product_id_for_supplier = wc_get_product_id_by_sku( $parent_sku_for_supplier );
                        if ( $parent_product_id_for_supplier ) {
                            $supplier_id = get_post_meta( $parent_product_id_for_supplier, 'supplier', true );
                        }
                    }
                }

                if( $supplier_id ){
                    $supplier = get_user_meta($supplier_id, 'first_name', true).' '.get_user_meta($supplier_id, 'last_name', true);
                    $supplier_po = (get_user_meta($supplier_id, 'postcode', true)) ? get_user_meta($supplier_id, 'postcode', true) : '';

                    $product_id_for_item = $item->get_product_id();
                    $item_fields = $pro_fields_by_product_id[$product_id_for_item] ?? [];

                    $gift_card_id = $item->get_meta('_gift_card_post_id');
                    $gift_card_delivery_date = get_post_meta($gift_card_id, '_scheduled_gift_card_delivery', true) ?: '';

                    // '_gift_card_send' is a free-text delivery-attempt log (e.g. "Delivered", "Failed",
                    // "Instant"), not the spec's card-status enum — fall back to 'Draft' when unset/falsy
                    // (e.g. boolean false) rather than showing raw junk values.
                    $raw_card_status = get_post_meta($gift_card_id, '_gift_card_send', true);
                    $gift_card_status = (!empty($raw_card_status) && is_string($raw_card_status)) ? $raw_card_status : 'Draft';

                    $gift_card_number_enc = get_post_meta($gift_card_id, '_gift_card_number_enc', true);
                    if( !empty($gift_card_number_enc) ){
                        try {
                            $gift_card_number = decrypt_giftcard_no($gift_card_number_enc);
                        } catch (Exception $e) {
                            $gift_card_number = 'Invalid / Corrupted Card';
                        }
                    }else{
                        $gift_card_number = get_post_meta($gift_card_id, '_gift_card_number', true);
                    }

                    // Gift card name/SKU: prefer the order item's own meta, fall back to the
                    // linked gift_card post, then the product itself — item meta isn't always
                    // populated depending on which order flow created the item.
                    $gift_card_name = $item->get_meta('_gift_card_name');
                    if (empty($gift_card_name)) {
                        $gift_card_name = get_post_meta($gift_card_id, '_gift_card_name', true) ?: $product->get_name();
                    }
                    $gift_card_sku = $item->get_meta('_gift_card_sku');
                    if (empty($gift_card_sku)) {
                        $gift_card_sku = get_post_meta($gift_card_id, '_product_sku', true) ?: $product->get_sku();
                    }

                    // Denomination = this card's own face value/subtotal, NOT the whole order total
                    // (which may include multiple cards, fulfilment fees, delivery, etc.).
                    $gift_card_denomination = $item->get_subtotal();

                    // Per-item costs come from the matching product's own snapshot fields —
                    // NOT hardcoded index [0], which always read the order's first product
                    // regardless of which item this row is actually for.
                    $item_buy_price = isset($item_fields['_total_buy_price']) ? (float) $item_fields['_total_buy_price'] : 0;
                    $item_fulfilment_price = isset($item_fields['_supplier_fullfillment_price']) ? (float) $item_fields['_supplier_fullfillment_price'] : 0;
                    $item_gst = isset($item_fields['_gst']) ? (float) $item_fields['_gst'] : 0;

                    // Delivery cost: $1 per spec when this item's delivery method is SMS;
                    // otherwise use the snapshot's delivery cost field if present.
                    $item_delivery_method = $item->get_meta('_delivery_method');
                    if ($item_delivery_method === 'sms') {
                        $item_delivery_price = 1.00;
                    } else {
                        $item_delivery_price = isset($item_fields['_delivery_cost']) ? (float) $item_fields['_delivery_cost'] : 0;
                    }

                    $item_total_buy_price = $item_buy_price + $item_fulfilment_price + $item_delivery_price + $item_gst;

                    $last_oid = $order->get_id();
                    $last_gid = $gift_card_id;

                    $total_supplier_costs = $temp_supplier_buy_total + $temp_supplier_fulfilment_total + $temp_delivery_total + $temp_supplier_gst;

                    $data[$order->get_id().'_'.$gift_card_id] = [
                        'supplier' => $supplier,
                        'order_number' => $order->get_id(),
                        'supplier_po' => $supplier_po,
                        'order_date' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : '',
                        'order_status' => $order_status,
                        'gift_card_name' => $gift_card_name,
                        'gift_card_sku' => $gift_card_sku,
                        'suppliier_gift_card_sku' => $product->get_meta('_supplier_sku'),
                        'gift_card_number' => !empty($gift_card_number) ? 'XXXX XXXX' : '',
                        'card_type_variable_fixed' => $item_fields['denomination_type'] ?? '',
                        'gift_card_denomination' => wc_price($gift_card_denomination),
                        'gift_card_cost_price' => wc_price($item_buy_price),
                        'gift_card_supplier_fulfilment_cost' => wc_price($item_fulfilment_price),
                        'gift_card_supplier_delivery_cost' => wc_price($item_delivery_price),
                        'total_gift_card_buy_price' => wc_price($item_total_buy_price),
                        'gst' => wc_price($item_gst),
                        'card_status' => $gift_card_status,
                        'order_total_supplier_buy_price' => wc_price($temp_supplier_buy_total),
                        'order_total_supplier_fulfilment_price' => wc_price($temp_supplier_fulfilment_total),
                        'order_total_supplier_delivery_cost' => wc_price($temp_delivery_total),
                        'order_total_supplier_gst' => wc_price($temp_supplier_gst),
                        'order_total_supplier_costs' => wc_price($total_supplier_costs),
                        'supplier_payment_due' => '',
                    ];
                }
            }
        }

        $total_args = [
            'return' => 'ids',
            'orderby'=> 'date',
            'order'  => 'DESC',
            'limit'   => -1,
        ];

        $total_orders = count( wc_get_orders( $total_args ) );

        return rest_ensure_response([
            'page'          => $page,
            'per_page'      => $per_page,
            'total_orders'  => $total_orders,
            'orders'        => array_values($data),
        ]);
    }