<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Template Name: Manual Order
 */


function enqueue_manual_orders_scripts()
{
    wp_enqueue_style('bootstrap-css', get_template_directory_uri() . '/assets/css/bootstrap.min.css', array(), time());
    wp_enqueue_script('bootstrap-js', get_template_directory_uri() . '/assets/js/bootstrap-bundle.js', array(), time(), true);
}
add_action('wp_enqueue_scripts', 'enqueue_manual_orders_scripts');
if (!class_exists('WooCommerce')) {
    exit;
}
?>
<?php
get_header();


global $wpdb;
$latest_order_id = $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->prefix}posts WHERE post_type = 'shop_order'");
$next_order_id = $latest_order_id ? $latest_order_id + 1 : 101;

$create_order = '';
$create_order_title = 'New Order';
$bulk_order_button_style = '';
$create_order_style = ' style="display: none;"';
$order_list_style = ' style="display:block"';
if (isset($_GET['create_order']) && !empty($_GET['create_order']) && ($_GET['create_order'] == 'manual' || $_GET['create_order'] == 'bulk')) {
    $create_order = $_GET['create_order'];
    $create_order_style = ' style="display:block"';
    $order_list_style = ' style="display:none"';

    if ($_GET['create_order'] == 'bulk') {
        $create_order_title = 'Bulk Order';
        $bulk_order_button_style = ' style="display:none"';
    } elseif ($create_order == 'manual') {
        $create_order_title = 'New Order';
        $bulk_order_button_style = ' style="display:block"'; // hide button for manual
    }
}

// Fetch Business Users & Their Sender Details
$business_users = get_users(array('role' => 'business_user'));
$business_user_data = [];
foreach ($business_users as $user) {
    $senders = get_field('sender_details', 'user_' . $user->ID);
    $business_name = get_field('business_name', 'user_' . $user->ID);

    $get_business_user_balance

    [$user->ID] = [
        'business_user_id' => $user->ID,
        'business_name' => $business_name ?: $user->display_name,
        'senders' => [],
    ];

    if ($senders) {
        foreach ($senders as $sender) {
            $business_user_data[$user->ID]['senders'][] = [
                'name' => esc_html($sender['sender_name']),
                'email' => esc_attr($sender['sender_email']),
            ];
        }
    }
}

$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$o_id = isset($_GET['o_id']) ? intval($_GET['o_id']) : '';
$o_date = isset($_GET['o_date']) ? sanitize_text_field($_GET['o_date']) : '';
$o_ref = isset($_GET['o_ref']) ? sanitize_text_field($_GET['o_ref']) : '';
$o_user = isset($_GET['o_user']) ? sanitize_text_field($_GET['o_user']) : '';
$o_status = isset($_GET['o_status']) ? sanitize_text_field($_GET['o_status']) : '';
$o_invoice = isset($_GET['o_invoice']) ? sanitize_text_field($_GET['o_invoice']) : '';
$o_total = isset($_GET['o_total']) ? floatval($_GET['o_total']) : '';

?>

<!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
<style>

</style>
<!-- <div class="header">
    <span class="username">
        <?php
        $current_user = wp_get_current_user();
        echo esc_html($current_user->display_name);
        ?>
    </span>
</div> -->
<div class="page-spacer-top" id="page-spacer-top"></div>
<div class="order-list-container container" <?= $order_list_style; ?>>
    <div class="page-title align-left">
        <h1>Your Orders</h1>
    </div>
    <div class="top-filter-block">
        <div class="search-container ">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="order-search" class="search-input"
                placeholder="Search card name, recipient name, email" value="<?= $search; ?>" />
            <?php if (isset($search) && !empty($search)) { ?>
                <button id="order-search-clear" class="btn btn-primary btn-md" type="button"
                    style="margin-left: 10px;">Clear</button>
            <?php } ?>
        </div>
        <!-- <button class="filter-btn">
                <img src="<?php //echo esc_url(content_url('uploads/2025/03/Filters-lines.jpg')); ?>"
                    alt="filter" width="16" height="16" />
                Filters
        </button> -->
        <div class="action-buttons">
            <input type="button" value="Export Orders" id="exportOrdersBtn"
                class="export-order-button btn btn-black-white btn-primary-white btn-primary btn-lg"></input>

            <a href="<?php echo site_url('order') . '/?create_order=manual'; ?>" aria-label="New Order">
                <input type="button" value="New Order" id="new-order-button"
                    class="new-order-button btn btn-black-white btn-primary-black btn-primary btn-lg"></input>
            </a>
        </div>
    </div>
    <div id="exportStatus" style="display:none;"></div>

    <?php if (isset($_GET['create_order']) && ($_GET['create_order'] == 'bulk' || $_GET['create_order'] == 'manual')) { ?>
        <script type="text/javascript">
            /*jQuery(window).on('beforeunload', function() {
                return "Refreshing will clear your progress. Do you want to continue?";
            });*/
            
            jQuery(function () {
                if( jQuery('#new-order-form-container').length ){
                    let skipBeforeUnload = false;

                    // --- Intercept F5 / Ctrl+R / Cmd+R ---
                    jQuery(document).on("keydown", function (e) {
                        if( jQuery('#new-order-form-container').hasClass('order_submitted') ){}
                        else if (
                            e.keyCode === 116 || // F5
                            (e.ctrlKey && e.keyCode === 82) || // Ctrl+R
                            (e.metaKey && e.keyCode === 82) // Cmd+R (Mac)
                        ) {
                            e.preventDefault(); // stop default refresh
                            if (confirm("Refreshing will clear your progress. Do you want to continue?")) {
                                skipBeforeUnload = true; // disable beforeunload just this once
                                location.reload();
                            }
                        }
                    });

                    // --- Toolbar reload / URL bar / tab close ---
                    jQuery(window).on("beforeunload", function () {
                        if( jQuery('#new-order-form-container').hasClass('order_submitted') ){}
                        else if (!skipBeforeUnload) {
                            return "Refreshing will clear your progress. Do you want to continue?";
                        }
                    });
                }
            });
        </script>
    <?php } ?>

    <script>
        jQuery(document).ready(function ($) {

            let businessUserData = <?php echo json_encode($business_user_data); ?>;
            let selectedSender = null;

            if ($('#order-form').length) {
                $('#business-user-dropdown').select2({
                    placeholder: "Select a Business",
                    allowClear: true,
                    dropdownParent: $('#order-form'),
                    width: '100%'
                });
                $('#business-user-dropdown').on('select2:open', function () {
                    setTimeout(() => {
                        const $searchField = $('.select2-container--open .select2-search__field');

                        // Avoid duplicate icons
                        if ($('.select2-container--open .search-icon').length === 0) {
                            // Wrap the search input in a container if not already wrapped
                            const $wrapper = $('<div class="search-input-wrapper" style="position: relative;"></div>');
                            $searchField.wrap($wrapper);

                            // Create the search icon
                            const $icon = $('<i class="fas fa-search search-icon"></i>').css({
                                position: 'absolute',
                                left: '8px',
                                top: '50%',
                                transform: 'translateY(-50%)',
                                color: '#999',
                                'pointer-events': 'none',
                                'font-size': '14px'
                            });

                            // Insert icon before input
                            $searchField.before($icon);

                            // Add padding to the input so text doesn’t overlap the icon
                            $searchField.css('padding-left', '28px');
                        }
                    }, 0); // Delay to make sure Select2 renders the search field
                });
            }


            // Modify the business user dropdown change handler to populate both sender dropdowns
            $('#CLOSE-business-user-dropdown').on('change', function () {
                let selectedUserId = $(this).val();
                let senderDropdown = $('#sender-dropdown');
                let selectSenderDropdown = $('#select-sender-dropdown');
                let activationSenderDropdown = $('#card-activation-form #sender-dropdown');

                // Show loading text
                senderDropdown.html('<option selected disabled>Loading...</option>');
                selectSenderDropdown.html('<option selected disabled>Loading...</option>');
                activationSenderDropdown.html('<option selected disabled>Loading...</option>');

                setTimeout(() => {
                    senderDropdown.empty().append('<option selected disabled>Select sender</option>');
                    selectSenderDropdown.empty().append('<option selected disabled>Select sender</option>');
                    activationSenderDropdown.empty().append('<option selected disabled>Select sender</option>');

                    if (selectedUserId && businessUserData[selectedUserId]) {
                        const senders = businessUserData[selectedUserId]['senders'];

                        senders.forEach((sender, index) => {
                            const optionHTML = `<option value="${sender.name}" data-email="${sender.email}">${sender.name}</option>`;
                            senderDropdown.append(optionHTML);
                            selectSenderDropdown.append(optionHTML);
                            activationSenderDropdown.append(optionHTML);
                        });

                        // ✅ Automatically select the first available sender
                        if (senders.length > 0) {
                            senderDropdown.val(senders[0].name).trigger('change');
                            selectSenderDropdown.val(senders[0].name).trigger('change');
                            activationSenderDropdown.val(senders[0].name).trigger('change');
                        }
                    }
                }, 300);


                // Fetch campaigns via AJAX
                fetch(userSearchAjax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'get_user_campaigns',
                        user_id: selectedUserId,
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        const campaignDropdown = document.getElementById("campaign-dropdown");
                        campaignDropdown.innerHTML = '<option disabled selected>Select campaign</option>';

                        if (data.success && Array.isArray(data.data.campaigns) && data.data.campaigns.length > 0) {
                            data.data.campaigns.forEach(campaign => {
                                const option = document.createElement("option");
                                option.value = campaign;
                                option.textContent = campaign;
                                campaignDropdown.appendChild(option);
                            });
                        } else {
                            const option = document.createElement("option");
                            option.disabled = true;
                            option.textContent = 'No campaigns found';
                            campaignDropdown.appendChild(option);
                        }
                    })
                    .catch(err => {
                        console.error("Error fetching campaigns", err);
                    });
            });

            // Sync selected sender between both dropdowns
            $('#CLOSE-sender-dropdown').on('change', function () {
                selectedSender = $(this).val();
                // Also update the display sender
                $('#display-sender').text(selectedSender);
            });

            $('#CLOSE-select-sender-dropdown').on('change', function () {
                selectedSender = $(this).val();
                $('#display-sender').text(selectedSender);
            });

            function debounce(func, wait) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }
            // let searchInput = document.getElementById("order-search");
            // if (searchInput) {
            //     searchInput.addEventListener("keyup", debounce(function () {
            //         let searchQuery = searchInput.value.trim();
            //         let data = new FormData();
            //         data.append("action", "search_orders");
            //         data.append("security", ajaxData.security);
            //         data.append("query", searchQuery);

            //         fetch(ajaxData.ajax_url, {
            //             method: "POST",
            //             body: data
            //         })
            //             .then(response => response.text())
            //             .then(data => {
            //                 document.getElementById("order-results").innerHTML = data;
            //             })
            //             .catch(error => console.error("Error:", error));
            //     }, 300));
            // }

            /*let newOrderButton = document.getElementById("new-order-button");
            let orderListContainer = document.querySelector(".order-list-container");
            let newOrderFormContainer = document.querySelector(".new-order-form-container");
            // Show new order form on button click
            newOrderButton.addEventListener("click", function () {
                orderListContainer.style.display = "none";
                newOrderFormContainer.style.display = "block";
            });*/
        });
    </script>

    <?php
    // Meta query
    $meta_query = [];

    // Initial Load of First Page Orders
    $args = array(
        'type' => 'shop_order',
        'limit' => -1,
        'offset' => 0,
        'orderby' => 'date',
        'order' => 'DESC',
        'return' => 'objects',
    );

    if ($search) {
        $meta_query[] = [
            'key' => '_gift_recipients',
            'value' => $search,
            'compare' => 'LIKE',
        ];
    }

    if (!empty($meta_query)) {
        $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
    }

    $orders = wc_get_orders($args);
    ?>

    <table class="order-table" id="order-table">
        <thead>
            <tr>
                <th data-head_slug="order_no">Order No</th>
                <th data-head_slug="order_date">Date</th>
                <th data-head_slug="order_name">Order Name</th>
                <th data-head_slug="order_client_reference">Client Reference</th>
                <th data-head_slug="order_User">User</th>
                <th data-head_slug="order_Status">Status</th>
                <th data-head_slug="order_Invoice">Invoice</th>
                <th data-head_slug="order_Total">Total</th>
            </tr>
        </thead>
        <tbody id="order-results">
            <?php foreach ($orders as $order):
                $user_id = $order->get_user_id();
                $user_name = esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                ?>
                <tr>
                    <td data-order="<?php echo esc_attr($order->get_id()); ?>">
                        <?php
                        $order_type = $order->get_meta('_order_type');
                        $order_id = $order->get_id();
                        $order_status = $order->get_status();

                        if ($order_status === 'pending') {
                            // Manual order redirect link
                            $order_edit_url = site_url('/order/') . '?create_order=' . $order_type . '&order_id=' . $order_id;
                            echo '<a href="' . esc_url($order_edit_url) . '">#' . esc_html($order_id) . '</a>';
                        } else {
                            // Default WooCommerce order received page
                            $default_url = wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order->get_order_key();
                            echo '<a href="' . esc_url($default_url) . '" target="_blank">#' . esc_html($order_id) . '</a>';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td>
                    <td><?php echo $order->get_meta('_order_name'); ?></td>
                    <td><?php
                    $client_reference = $order->get_meta('_client_reference');
                    echo esc_html($client_reference ?: '-'); ?>
                    </td>
                    <td>
                        <?php
                        $user = get_user_by('ID', $user_id);
                        $user_name = $user ? esc_html($user->display_name) : 'Guest';
                        ?>
                        <a href="<?php echo esc_url( home_url( '/users/?id=' . esc_attr($user_id) ) ); ?>"
                            class="user-link" target="_blank">
                            <?php echo $user_name; ?>
                        </a>
                        <input type="hidden" class="user-id" value="<?php echo esc_attr($user_id); ?>">
                    </td>
                    <?php
                    $status = $order->get_status();
                    $custom_class = $status === 'pending' ? 'draft' : $status;
                    $custom_label = $status === 'pending' ? 'Draft' : ucfirst($status);
                    ?>
                    <td class="status <?php echo esc_attr($custom_class); ?>">
                        <span><?php echo esc_html($custom_label); ?></span>
                    </td>
                    <td>
                        <?php
                        $order_id = $order->get_id();
                        $invoice_number = $order->get_meta('_invoice_number');

                        if ($invoice_number) {

                            $download_url = admin_url('admin-ajax.php?action=download_invoice&order_id=' . $order_id);

                            echo '<a href="' . esc_url($download_url) . '" download>' . esc_html($invoice_number) . '</a>';

                        } else {
                            echo '—';
                        }
                        ?>
                    </td>

                    <td data-order="<?= $order->get_total(); ?>">AUD <?php echo esc_html($order->get_total()); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- <div id="orderDetailSection" style="display:none;"></div> -->

    <!-- <div class="pagination">
        <a href="#" class="page-link prev disabled" data-page="prev">&lt;</a>

        <?php
        // $visible_pages = 4; // Number of visible pages before adding "..."
        // $total_orders = count(wc_get_orders(array('limit' => -1, 'return' => 'ids')));
        // $total_pages = ceil($total_orders / $orders_per_page);
        
        // if ($total_pages <= $visible_pages + 2) {
        //     for ($i = 1; $i <= $total_pages; $i++) {
        //         echo '<a href="#" class="page-link" data-page="' . $i . '">' . $i . '</a>';
        //     }
        // } else {
        //     echo '<a href="#" class="page-link" data-page="1">1</a>';
        //     echo '<a href="#" class="page-link" data-page="2">2</a>';
        //     echo '<a href="#" class="page-link" data-page="3">3</a>';
        //     echo '<a href="#" class="ellipsis">...</a>';
        //     echo '<a href="#" class="page-link" data-page="' . ($total_pages - 1) . '">' . ($total_pages - 1) . '</a>';
        //     echo '<a href="#" class="page-link" data-page="' . $total_pages . '">' . $total_pages . '</a>';
        // }
        ?>

        <a href="#" class="page-link next" data-page="next">&gt;</a>
    </div> -->


    <script>
        // jQuery(document).ready(function ($) {
        //     var currentPage = 1;
        //     var totalPages = <?php //echo $total_pages; 
        // ?>;

        //     function loadOrders(page) {
        //         $.ajax({
        //             type: 'POST',
        //             url: '<?php //echo admin_url("admin-ajax.php"); 
        // ?>',
        //             data: {
        //                 action: 'load_orders',
        //                 page: page
        //             },
        //             beforeSend: function () {
        //                 $('#order-results').html('<tr><td colspan="7">Loading...</td></tr>');
        //             },
        //             success: function (response) {
        //                 $('#order-results').html(response);
        //                 currentPage = page;
        //                 updatePagination();
        //             }
        //         });
        //     }

        //     function updatePagination() {
        //         $('.page-link').removeClass('active');
        //         $('.page-link[data-page="' + currentPage + '"]').addClass('active');

        //         $('.prev').toggleClass('disabled', currentPage === 1);
        //         $('.next').toggleClass('disabled', currentPage === totalPages);
        //     }

        //     $(document).on('click', '.page-link', function (e) {
        //         e.preventDefault();
        //         var page = $(this).data('page');

        //         if (page === "prev" && currentPage > 1) {
        //             loadOrders(currentPage - 1);
        //         } else if (page === "next" && currentPage < totalPages) {
        //             loadOrders(currentPage + 1);
        //         } else if (!isNaN(page)) {
        //             loadOrders(page);
        //         }
        //     });

        //     updatePagination();
        // });
    </script>
</div>
<?php
$order_id = null;
$order = null;
$recipients_data = [];

$order_status = 'create';

if (isset($_GET['order_id'])) {
    // sleep(15);
    $order_id = absint($_GET['order_id']);
    $order_status = 'update';
    $order = wc_get_order($order_id);

    $business_user_id = $order ? $order->get_customer_id() : '';
    $business_name = $order ? $order->get_meta('_business_name') : '';
    $sender_name = $order ? $order->get_meta('_sender_name') : '';
    $sender_email = $order ? $order->get_meta('_sender_email') : '';
    $campaign = $order ? $order->get_meta('_campaign') : '';
    $order_name = $order ? $order->get_meta('_order_name') : '';
    $po_number = $order ? $order->get_meta('_po_number') : '';
    $additional_reference = $order ? $order->get_meta('_additional_reference') : '';
    $client_reference = $order ? $order->get_meta('_client_reference') : '';
    $order_type = $order ? $order->get_meta('_order_type') : '';
    // echo '<pre>';
    // print_r($order_type);
    // // print_r($order);
    // echo '</pre>';

    $campaigns = [];
    if ($business_user_id) {
        $campaigns_raw = get_user_meta($business_user_id, '_campaign', true);

        // Ensure it's an array
        if (!is_array($campaigns_raw)) {
            if (!empty($campaigns_raw)) {
                // Possibly a comma-separated string
                $campaigns = array_map('trim', explode(',', $campaigns_raw));
            } else {
                $campaigns = [];
            }
        } else {
            $campaigns = array_map('trim', $campaigns_raw);
        }

        // Fallback to global campaigns if empty
        if (empty($campaigns)) {
            $global_campaigns = get_option('global_campaigns', []);
            $campaigns = is_array($global_campaigns) ? $global_campaigns : [];
        }
    }
    $recipients_raw = $order ? $order->get_meta('_recipients_details') : [];
    $recipients_arr_raw = $order ? $order->get_meta('_recipients_details_arr') : [];
    //pr($recipients_raw);
    //pr($recipients_arr_raw);

    if (!empty($recipients_arr_raw)) {
        foreach ($recipients_arr_raw as $key => $recipients_array) {
            // Get all gift card SKUs and prices
            $gift_cards_arr = [];
            foreach ($recipients_array['gift_cards'] as $gift_card) {
                $prod_id = $gift_card['product_id'];
                $gift_cards_arr[] = [
                    'prod_id' => $prod_id,
                    'name' => get_the_title($prod_id),
                    'img' => $gift_card['product_image'],
                    'sku' => $gift_card['sku'],
                    'price' => $gift_card['price'],
                    'gift_message' => $gift_card['gift_message'],
                    'gift_text_animation' => $gift_card['gift_text_animation'],
                    'gift_email_animation' => $gift_card['gift_email_animation'],
                    'gift_subject' => $gift_card['gift_subject'],
                    'gift_text_message' => $gift_card['gift_text_message'],
                    'selected' => isset($gift_card['selected']) ? (int)$gift_card['selected'] : 0,
                ];
            }

            $recipients_data[] = [
                'first_name' => $recipients_array['first_name'],
                'surname' => $recipients_array['surname'],
                'email' => $recipients_array['email'],
                'phone' => $recipients_array['phone'],
                'gift_cards' => $gift_cards_arr,
            ];
        }
        // if(isset($_GET['test'])){
        //     pr($recipients_data);
        //     pr($recipients_arr_raw);    
        // }
    }

    if (1 == 2 && !empty($recipients_raw)) {
        foreach ($recipients_raw as $key => $gift_cards) {
            // Parse recipient name and email from key
            preg_match('/<strong>(.*?)<\/strong> \((.*?)\)/', $key, $matches);
            $name = isset($matches[1]) ? $matches[1] : '';
            $email = isset($matches[2]) ? $matches[2] : '';
            $first_name = '';
            $surname = '';
            if (!empty($name)) {
                $name_parts = explode(' ', $name, 2);
                $first_name = $name_parts[0];
                $surname = isset($name_parts[1]) ? $name_parts[1] : '';
            }

            // Grab phone and message from one of the gift cards (assuming same across all)
            $phone = '';
            $message = '';
            $delivery_method = '';
            $card_data = reset($gift_cards); // Get first card
            if (preg_match('/Phone: (.*?)<br>/', $card_data, $phone_match)) {
                $phone = trim($phone_match[1]);
            }
            if (preg_match('/Message: (.*?)<br>/', $card_data, $msg_match)) {
                $message = trim($msg_match[1]);
            }
            if (preg_match('/Delivery: (.*?)<\/li>/', $card_data, $delivery_match)) {
                $delivery_method = trim($delivery_match[1]);
            }

            // Get all gift card SKUs and prices
            $gift_cards_arr = [];
            foreach ($gift_cards as $card_html) {
                if (preg_match('/<br>(.*?) - (.*?)<br>/', $card_html, $card_match)) {
                    $sku_price = explode(' - ', $card_match[0]);
                    if (!empty($sku_price[0]) && !empty($sku_price[1])) {
                        $prod_id = wc_get_product_id_by_sku($card_match[1]);
                        if (empty($prod_id) || $prod_id <= 0) {
                            $prod_id = get_product_id_by_sku_fallback($card_match[1]);
                        }
                        $gift_cards_arr[] = [
                            'prod_id' => $prod_id,
                            'name' => get_the_title($prod_id),
                            'img' => get_the_post_thumbnail_url($prod_id),
                            'sku' => strip_tags(trim($card_match[1])),
                            'price' => strip_tags(trim($card_match[2])),
                        ];
                    }
                }
            }

            $recipients_data[] = [
                'first_name' => $first_name,
                'surname' => $surname,
                'email' => $email,
                'phone' => $phone,
                'message' => $message,
                'delivery_method' => $delivery_method,
                'gift_cards' => $gift_cards_arr,
            ];
        }
    }

}

?>
<script>
    /*setTimeout(() => {
        jQuery('#business-user-dropdown').trigger('change');
        console.log('It is triggering...');
    }, 500);*/
</script>

<div id="new-order-form-container" class="new-order-form-container container" <?= $create_order_style; ?>
    data-order_type="<?= $create_order; ?>">
    <div class="container">
        <div id="new-order-form" class="order-form-container ">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-center flex-grow-1"><?= $create_order_title; ?></h2>
                <button type="button" class="btn btn-white btn-black-white btn-primary-white next-btn-bulk" id="bulk-next-step" data-type="bulk"
                    <?= $bulk_order_button_style; ?>
                    onclick="window.location.href='<?php echo esc_url(home_url('/order/?create_order=bulk')); ?>'">
                    Bulk Order Upload </button>
            </div>

            <div class="custom-from-section">
                <div class="md-form-section">
                    <form id="order-form">
                        <p id="error-message"></p>
                        <div class="form-group flex-row">
                            <div class="control-wrapper col">
                                <!-- <i class="fas fa-search search-icon"></i> -->
                                <label for="business-user-dropdown" class="label form-label required-field">
                                    Search for your business<span class="validate">*</span>
                                </label>
                                <div class="top-dropdown-block">
                                    <?php
                                    $has_business_id = !empty($business_user_id);
                                    ?>
                                    <select id="business-user-dropdown" name="business_user" class="form-select"
                                        required>
                                        <option value="" <?php echo !$has_business_id ? 'selected' : ''; ?>>Select a
                                            Business</option>
                                        <?php foreach ($business_users as $user):
                                            $biz_name = get_field('business_name', 'user_' . $user->ID);
                                            $label = $biz_name ?: $user->display_name;
                                            $selected = ($has_business_id && $business_user_id == $user->ID) ? 'selected' : '';
                                            ?>
                                            <option value="<?php echo esc_attr($user->ID); ?>"
                                                data-business-id="<?php echo esc_attr($user->ID); ?>" <?php echo $selected; ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback error-message">Please select a business</div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="form-group flex-row">
                        <div class="control-wrapper col">
                            <label class="label form-label required-field">Sender Details<span
                                    class="validate">*</span></label>
                            <div class="d-flex gap-2">
                                <select id="sender-dropdown" name="sender_details" class="form-select flex-box-1">
                                    <option value="" disabled <?php echo empty($sender_name) ? 'selected' : ''; ?>>
                                        Select sender</option>
                                    <?php
                                    $senders = get_field('sender_details', 'user_' . $business_user_id); // Using ACF repeater
                                    
                                    if (!empty($senders) && is_array($senders)) {
                                        foreach ($senders as $sender) {
                                            $name = isset($sender['sender_name']) ? $sender['sender_name'] : '';
                                            $email = isset($sender['sender_email']) ? $sender['sender_email'] : '';
                                            $selected = ($sender_name === $name) ? 'selected' : '';

                                            echo '<option value="' . esc_attr($name) . '" data-email="' . esc_attr($email) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                                        }
                                    }

                                    // Add fallback option if the selected sender is not in the list
                                    if (!empty($sender_name) && !empty($sender_email)) {
                                        $already_in_list = false;
                                        if (!empty($senders)) {
                                            foreach ($senders as $sender) {
                                                if (isset($sender['sender_name']) && $sender['sender_name'] === $sender_name) {
                                                    $already_in_list = true;
                                                    break;
                                                }
                                            }
                                        }

                                        if (!$already_in_list) {
                                            echo '<option value="' . esc_attr($sender_name) . '" data-email="' . esc_attr($sender_email) . '" selected>' . esc_html($sender_name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <button type="button" class="btn btn-blue" id="add-new-sender"><i
                                        class="fa-solid fa-plus"></i> <span class="btn-text">New</span></button>
                            </div>
                            <div class="invalid-sender-selection error-message" id="sender-error-message"
                                style="display:none;">Please select a
                                sender profile</div>
                        </div>
                    </div>
                    <div id="new-sender-input" class="inner-input-block" style="display: none;">
                        <div class="form-group flex-row">
                            <div class="control-wrapper col">
                                <input type="text" class="form-control mt-2" name="new_sender_name" id="new_sender_name"
                                    placeholder="Enter new sender Name">
                            </div>
                        </div>
                        <div class="form-group flex-row">
                            <div class="control-wrapper col d-flex align-items-center">
                                <div class="email-input-wrapper">
                                    <input type="text" class="form-control mt-2" name="new_sender_email"
                                        id="new_sender_email" placeholder="Enter email prefix (e.g., john)">
                                    <span class="email-domain">@delivery.giftcardsplus.com.au</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group flex-row">
                            <div class="control-wrapper col">
                                <button type="button" class="btn btn-blue mt-2"
                                    id="add-new-sender-to-business">Add</button>
                                <div id="sender-message" class="sender-message error-message" style="display:none;">
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="mb-3">
                        <div class="form-group flex-row">
                            <div class="control-wrapper col">
                                <label class="form-label">Select campaign</label>
                                <div class="d-flex gap-2">
                                    <select class="form-select flex-box-1" id="campaign-dropdown" name="campaign">
                                        <option value="" disabled <?php echo empty($campaign) ? 'selected' : ''; ?>>
                                            Select campaign</option>
                                        <?php
                                        $campaigns = get_field('add_campaign', 'user_' . $business_user_id); // ACF repeater field
                                        
                                        if (!empty($campaigns) && is_array($campaigns)) {
                                            foreach ($campaigns as $row) {
                                                $campaign = isset($row['campaign']) ? $row['campaign'] : '';
                                                $selected = ($selected_campaign === $campaign) ? 'selected' : '';

                                                echo '<option value="' . esc_attr($campaign) . '" ' . $selected . '>' . esc_html($campaign) . '</option>';
                                            }
                                        }

                                        // Add fallback option if the selected campaign is not in the list
                                        if (!empty($selected_campaign)) {
                                            $already_in_list = false;

                                            if (!empty($campaigns)) {
                                                foreach ($campaigns as $row) {
                                                    if (isset($row['campaign']) && $row['campaign'] === $selected_campaign) {
                                                        $already_in_list = true;
                                                        break;
                                                    }
                                                }
                                            }

                                            if (!$already_in_list) {
                                                echo '<option value="' . esc_attr($selected_campaign) . '" selected>' . esc_html($selected_campaign) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <button type="button" class="btn btn-blue" id="add-new-campaign"><i
                                            class="fa-solid fa-plus"></i> <span class="btn-text">New</span></button>
                                </div>
                            </div>
                        </div>
                        <div id="new-campaign-input" class="inner-input-block" style="display: none;">
                            <div class="form-group flex-row">
                                <div class="control-wrapper col">
                                    <input type="text" class="form-control mt-2" name="new_campaign_name"
                                        id="new_campaign_name" placeholder="Enter new campaign name">
                                </div>
                            </div>
                            <div class="form-group flex-row">
                                <div class="control-wrapper col">
                                    <button type="button" class="btn btn-blue"
                                        id="add-new-campaign-to-business">Add</button>
                                    <div class="campaign-message error-message"></div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="form-group flex-row">
                        <div class="control-wrapper col">
                            <label class="form-label label required-field">Order Name<span
                                    class="validate">*</span></label>
                            <input type="text" class="form-control" id="order-name"
                                value="<?php echo esc_attr($order_name); ?>" required>
                            <div class="invalid-feedback error-message order-name">Please enter an order name</div>
                        </div>
                        <!-- <div class="control-wrapper col">
                            <label class="form-label label">Order ID<span class="validate">*</span></label>
                            <input type="text" class="form-control readonly" id="order-id" value="<?php //echo $order_id ? esc_attr($order_id) : 'Auto-generated'; ?>" readonly>
                            <div class="invalid-feedback error-message">Order ID is required</div>
                        </div> -->
                    </div>

                    <div class="form-group flex-row">
                        <div class="control-wrapper col col-6">
                            <label class="form-label label required-field">Client Reference</label>
                            <input type="text" class="form-control" id="client-reference"
                                value="<?php echo esc_attr($client_reference); ?>">
                        </div>
                        <div class="control-wrapper col col-6">
                            <label class="form-label label">Related PO</label>
                            <input type="text" id="related-po" class="form-control related-po"
                                value="<?php echo esc_attr($po_number); ?>">
                        </div>
                    </div>

                    <div class="form-group flex-row">
                        <div class="control-wrapper col">
                            <label class="form-label">Additional Reference</label>
                            <input type="text" id="additional-reference" class="form-control additional-reference"
                                value="<?php echo esc_attr($additional_reference); ?>">
                        </div>
                    </div>


                    <!-- Buttons -->
                    <div class="page-bottom-toolbar">
                        <div class="right-block">
                            <div class="page-bottom-actions">
                                <button type="button" id="create-order-save-btn" data-action="save-draft"
                                    class="btn btn-white create-order-save-btn btn-black-white btn-primary-white" data-step="0"
                                    data-status="<?= $order_status; ?>" data-order-id="<?= $order_id; ?>">Save
                                    draft</button>
                                <button type="button" class="btn btn-primary next-btn create-order-next-btn btn-black-white btn-primary-black" id="next-step"
                                    data-edit_order="<?= $_GET['order_id']; ?>"
                                    data-type="<?= $create_order; ?>">Next</button>
                            </div>
                            <div id="save-create-draft-message" class="message-box" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="multi-step-form" class="d-none manual-order">
        <div class="multi-step-form-container manual-container">
            <div class="container">
                <div class="step-indicator text-step-indicator progress-container d-flex align-items-center">
                    <div class="step active-step <?php echo ($create_order == 'bulk') ? '' : 'back-to-recipient-form'; ?>"
                        data-type="<?= $create_order; ?>">Create Order</div>

                    <div class="step <?php echo ($create_order == 'bulk') ? 'back-to-customisation' : ''; ?>">
                        Personalisation</div>
                    <div class="step">Delivery</div>
                    <div class="step">Order Summary</div>
                    <div class="step">Payment and Confirmation</div>

                    <!-- <div class="step active-step back-to-recipient-form">Create Order</div>
                    <div class="step back-to-customisation">Personalisation</div>
                    <div class="step back-to-delivery-step">Delivery</div>
                    <div class="step back-to-order-summary">Order Summary</div>
                    <div class="step">Payment and Confirmation</div> -->
                </div>
            </div>
        </div>
        <div class="container"<?=$bulk_order_button_style;?>>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button type="button" class="arrow change__back_status" id="back-to-order-form">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none">
                        <path
                            d="M22.4598 8.95444H5.2559L12.772 2.32605C13.3727 1.79632 13.3727 0.927024 12.772 0.397296C12.1713 -0.132432 11.201 -0.132432 10.6004 0.397296L0.450505 9.34834C-0.150168 9.87807 -0.150168 10.7338 0.450505 11.2635L10.6004 20.2146C11.201 20.7443 12.1713 20.7443 12.772 20.2146C13.3727 19.6848 13.3727 18.8291 12.772 18.2994L5.2559 11.671H22.4598C23.3069 11.671 24 11.0598 24 10.3127C24 9.56567 23.3069 8.95444 22.4598 8.95444Z"
                            fill="black" />
                    </svg>
                </button>
                <button type="button" class="arrow" id="back-to-delivery-step" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none">
                        <path
                            d="M22.4598 8.95444H5.2559L12.772 2.32605C13.3727 1.79632 13.3727 0.927024 12.772 0.397296C12.1713 -0.132432 11.201 -0.132432 10.6004 0.397296L0.450505 9.34834C-0.150168 9.87807 -0.150168 10.7338 0.450505 11.2635L10.6004 20.2146C11.201 20.7443 12.1713 20.7443 12.772 20.2146C13.3727 19.6848 13.3727 18.8291 12.772 18.2994L5.2559 11.671H22.4598C23.3069 11.671 24 11.0598 24 10.3127C24 9.56567 23.3069 8.95444 22.4598 8.95444Z"
                            fill="black" />
                    </svg>
                </button>
                <button type="button" class="arrow" id="back-to-order-summary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none">
                        <path
                            d="M22.4598 8.95444H5.2559L12.772 2.32605C13.3727 1.79632 13.3727 0.927024 12.772 0.397296C12.1713 -0.132432 11.201 -0.132432 10.6004 0.397296L0.450505 9.34834C-0.150168 9.87807 -0.150168 10.7338 0.450505 11.2635L10.6004 20.2146C11.201 20.7443 12.1713 20.7443 12.772 20.2146C13.3727 19.6848 13.3727 18.8291 12.772 18.2994L5.2559 11.671H22.4598C23.3069 11.671 24 11.0598 24 10.3127C24 9.56567 23.3069 8.95444 22.4598 8.95444Z"
                            fill="black" />
                    </svg>
                </button>
            </div>

            <div class="order-info">
                <h2 class="order-title">Order Name: <span id="display-order-name" class="display-order-name"></span>
                </h2><br>
                <p class="text-muted">
                    Client Reference: <strong id="display-client-reference"></strong> <br>
                    <!-- Order Number: <strong id="display-order-id"></strong><br> -->
                    Sender: <strong id="display-sender"></strong>
                </p>
            </div>
            <div id="csv-preview-container">
                <div class="page-title align-left">
                    <h1 class="category-title">CSV Preview</h1>
                </div>
                <div class="custom-table-responsive">
                    <table id="csv-preview-table-manual" class="table">
                        <thead id="csv-preview-head">
                            <tr>
                                <th><input type="checkbox" id="select-all" class="custom-checkbox"></th>
                                <th>Recipient First Name*</th>
                                <th>Surname</th>
                                <th>Email</th>
                                <th>Phone Number</th>
                                <th>Gift Card</th>
                            </tr>
                        </thead>
                        <tbody id="csv-preview-body"></tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <div id="pagination-container">
                    <ul id="pagination" class="pagination"></ul>
                </div>
                <div class="page-bottom-toolbar">
                    <div class="right-block">
                        <div class="save-next-button-controls page-bottom-actions">
                            <button id="confirm-add" class="btn btn-primary">Confirm & Add</button>
                        </div>
                    </div>
                </div>

            </div>

            <div id="message-container">
                <p id="invalid-details-error-message custom-amount-error" style="color: red; display: none;"></p>
                <p id="success-message" style="color: green; display: none;"></p>
            </div>

            <div class="table-container cutsom-table-header">
                <div class="top-filter-block">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="user-search" class="form-control"
                            placeholder="Search name, ID, email, phone number">
                        <ul id="search-results" class="dropdown-menu"></ul>
                    </div>

                    <div class="action-buttons">
                        <!-- <button class="btn btn-outline-secondary filter-btn"> <img draggable="false" role="img" class="emoji"
                                alt="filter"
                                src="https://giftcardsplusd.wpenginepowered.com/wp-content/uploads/2025/03/Filters-lines.jpg"
                                width="16" height="16">Filters</button> -->
                        <button type="button" class="btn btn-black-white btn btn-white size-sm btn-black-white btn-primary-white" id="bulk-upload-order">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M9.16683 13.3359V6.54427L7.00016 8.71094L5.8335 7.5026L10.0002 3.33594L14.1668 7.5026L13.0002 8.71094L10.8335 6.54427V13.3359H9.16683ZM5.00016 16.6693C4.54183 16.6693 4.14947 16.5061 3.82308 16.1797C3.49669 15.8533 3.3335 15.4609 3.3335 15.0026V12.5026H5.00016V15.0026H15.0002V12.5026H16.6668V15.0026C16.6668 15.4609 16.5036 15.8533 16.1772 16.1797C15.8509 16.5061 15.4585 16.6693 15.0002 16.6693H5.00016Z"
                                    fill="#1D1B20" />
                            </svg>
                            Add Bulk
                        </button>
                        <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                        <button class="btn btn btn-white btn-black-white size-sm btn-black-white btn-primary-white" id="download-template">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
                                fill="none">
                                <path
                                    d="M6.66797 14.1667L10.0013 17.5M10.0013 17.5L13.3346 14.1667M10.0013 17.5V10M16.668 13.9524C17.6859 13.1117 18.3346 11.8399 18.3346 10.4167C18.3346 7.88536 16.2826 5.83333 13.7513 5.83333C13.5692 5.83333 13.3989 5.73833 13.3064 5.58145C12.2197 3.73736 10.2133 2.5 7.91797 2.5C4.46619 2.5 1.66797 5.29822 1.66797 8.75C1.66797 10.4718 2.36417 12.0309 3.49043 13.1613"
                                    stroke="#344054" stroke-width="1.66667" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg>
                            Download Template
                        </button>
                        <!-- <button class="btn btn-outline-secondary">Add New</button> -->
                        <button class="btn btn-new btn btn-blue size-sm add-new-recipient-btn"
                            id="add-new-recipient-btn">+ Add
                            New</button>
                    </div>
                </div>
                <div class="custom-table-responsive">
                    <table class="table table-transparent mt-3" id="recipient-table">
                        <thead>
                            <tr>
                                <th><span class="mine-icon-table">-</span></th>
                                <th>Recipient First Name<span class="validate">*</span></th>
                                <th>Recipient Surname</th>
                                <th>Email Address <span class="tooltip-icon">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="tooltip-text">
                                            Optional but you must add either an email address or phone number to
                                            continue
                                        </span>
                                    </span></th>
                                <th>Phone Number
                                    <span class="tooltip-icon rigth">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="tooltip-text">
                                            Additional SMS delivery fee of AUD $0.50 (inc. GST) per SMS will apply.
                                        </span>
                                    </span>
                                </th>
                                <th>Gift Card<span class="validate">*</span></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // pr($recipients_data);
                            if (!empty($recipients_data) && is_array($recipients_data)) {
                                foreach ($recipients_data as $rd_key => $rd_value) {
                                    ?>
                                    <tr class="editable-row">
                                        <td class="gift-card-checkbox-wrap">
                                            <input type="checkbox" checked="" id="select-gift-card" class="custom-checkbox">
                                        </td>
                                        <td><input type="text" class="form-control recipient-first-name invalid-field"
                                                name="recipient_firstname" placeholder="First Name"
                                                value="<?= $rd_value['first_name']; ?>"></td>
                                        <td><input type="text" class="form-control recipient-surname" name="recipient_surname"
                                                placeholder="Surname" value="<?= $rd_value['surname']; ?>"></td>
                                        <td><input type="email" class="form-control recipient-email" name="recipient_email"
                                                placeholder="Email" value="<?= $rd_value['email']; ?>"></td>
                                        <td><input type="text" class="form-control recipient-phone" name="recipient_phone"
                                                placeholder="Phone" value="<?= $rd_value['phone']; ?>"></td>
                                        <td class="gift-card-column">
                                            <div class="gift-table">
                                                <div class="gift-card-wrapper">
                                                    <?php foreach ($rd_value['gift_cards'] as $rd_gcard_key => $rd_gcard_value) { ?>
                                                        <?php
                                                            // Debug output
                                                            // echo '<pre>';
                                                            // print_r($rd_gcard_value);
                                                            // echo '</pre>';
                                                            $terms = get_the_terms( $rd_gcard_value['prod_id'], 'product_brand' );
                                    
                                                            $prod_brands = array();
                                                            if ( $terms && ! is_wp_error( $terms ) ) {
                                                                foreach ( $terms as $term ) {
                                                                    $prod_brands[] = $term->name;
                                                                }
                                                            }
                                                        ?>
                                                        <div class="gift-card-item" data-sku="<?= $rd_gcard_value['sku']; ?>"
                                                            data-prod_id="<?= $rd_gcard_value['prod_id']; ?>"
                                                            data-title="<?= $rd_gcard_value['name']; ?>"
                                                            data-message="<?= esc_attr($rd_gcard_value['gift_message'] ?? '') ?>"
                                                            data-text-animation="<?= esc_attr($rd_gcard_value['gift_text_animation'] ?? '') ?>"
                                                            data-personalisation=""
                                                            data-email-animation="<?= esc_attr($rd_gcard_value['gift_email_animation'] ?? '') ?>"
                                                            data-subject="<?= esc_attr($rd_gcard_value['gift_subject'] ?? '') ?>"
                                                            data-sender="<?= esc_attr($rd_gcard_value['gift_sender'] ?? '') ?>"
                                                            data-brands="<?= esc_attr(implode(', ', $prod_brands)) ?>"
                                                            data-text_message="<?= esc_attr($rd_gcard_value['gift_text_message'] ?? '') ?>"
                                                            data-personalised="<?= isset($rd_gcard_value['selected']) && $rd_gcard_value['selected'] ? 1 : 0; ?>">
                                                            <img src="<?= $rd_gcard_value['img']; ?>" class="gift-card-image"
                                                                alt="<?= $rd_gcard_value['name']; ?>">
                                                            <span class="gift-card-price"><?= '$' . $rd_gcard_value['price']; ?></span>
                                                            <button class="remove-gift-card">x</button>
                                                        </div>
                                                    <?php } ?>
                                                </div><button class="btn btn-outline-secondary gift-card-btn">+</button>
                                            </div>
                                        </td>
                                        <td class="action-menu">
                                            <button class="action-button">⋮</button>
                                            <div class="action-dropdown" style="display: none;">
                                                <button class="dropdown-item duplicate-recipient-data">Duplicate</button>
                                                <button class="dropdown-item delete-recipient">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="7" class="text-center">No recipients added.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div id="add-recipient-btn-wrap" class="add-recipient-btn-wrap center" style="justify-self: center">
                    <button class="btn btn-primary btn-outline-secondary btn-blue size-sm add-new-recipient-btn"
                        id="add-new-recipient-btn">+ Add
                        New</button>
                    <button class="btn btn-primary btn-success btn-save" id="btn-save"
                        style="display:none;">Save</button>
                </div>
            </div>
            <div id="invalid-recipients-error-message" style="color: #000000; display: none; margin-top: 24px;"><i
                    class="fa-solid fa-circle-info"></i></div>

            <div id="csv-error-message" class="text-danger" style="display: none;"></div>
            <div id="recipient-error-message" class="text-danger" style="display: none;"></div>
            <div id="recipient-email-phone-validate-message" class="text-danger" style="display: none;"></div>
            <div class="gift-card-container">
                <div class="gift-card-main-wrapper">
                    <h5>Gift Cards</h5>
                    <div class="top-filter-block">
                        <div class="search-bar search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="gift-card-search-pro" class="form-control" placeholder="Search">
                            <!-- <button class="btn btn-outline-secondary">Filters</button> -->
                        </div>
                    </div>
                    <div id="selected-product-container"></div> <!-- Selected product details will be displayed here -->

                    <div id="gift-card-container" class="gift-card-grid">
                        <!-- AJAX-loaded products will appear here -->
                    </div>

                    <div class="gift-card-pagination custom-pagination" id="gift-card-pagination">
                        <a href="#" class="prev-page page-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
                                fill="none">
                                <path
                                    d="M12.8416 6.175L11.6666 5L6.66663 10L11.6666 15L12.8416 13.825L9.02496 10L12.8416 6.175Z"
                                    fill="#2B2B2B" />
                            </svg>
                        </a>
                        <span class="current-page page-item">1</span>
                        <a href="#" class="next-page page-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
                                fill="none">
                                <path
                                    d="M8.08748 5L6.91248 6.175L10.7291 10L6.91248 13.825L8.08748 15L13.0875 10L8.08748 5Z"
                                    fill="#2B2B2B" />
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="page-bottom-toolbar">
                    <div class="right-block right">
                        <div class="save-next-button-controls page-bottom-actions" id="save-and-next-btn">
                            <button id="customisation-save-btn" data-action="save-draft"
                                class="btn btn-outline customisation-save-btn btn-black-white btn-primary-white" data-step="1"
                                data-status="<?= $order_status; ?>" data-order-id="<?= $order_id; ?>">Save</button>
                            <button class="btn btn-primary customisation-next-btn btn-black-white btn-primary-black"
                                data-edit_order="<?= $_GET['order_id']; ?>" id="customisation-next-btn">Next</button>
                        </div>
                        <div id="save-draft-message" class="message-box" style="display: none;"></div>
                    </div>
                </div>
            </div>

            <!-- <div id="customisation-error-message" class="custom-error-massage" style="display: none; margin-top: 10px;"></div> -->

            <div class="customisation-container" style="display: none;">
                <div class="personalise-title-container">
                    <h4 class="personalise-title-header">Personalise your order</h4>
                    <div class="button-group">
                        <button class="btn btn-white btn-black-white btn-primary-white size-sm" id="customisation-skip-btn">Skip</button>
                        <button class="btn btn-primary btn-black-white btn-primary-black size-sm" id="customisation-preview-btn">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                    </div>
                </div>
                <?php display_gift_card_customisation($order_status, $order_id); ?>
            </div>
            <div class="delivery-method-container" id="delivery-method-container" style="display: none;">
                <!-- <h3 class="delivery-method-header">Personalise your order</h3> -->
                <?php display_gift_card_delivery_method(); ?>
            </div>

            <div class="order-summary-container" id="order-summary-container" style="display: none;">
                <div class="summary-tob-block">
                    <h4 class="order-summary-title">Order Summary</h4>
                    <div class="summary-actions-top">
                        <button type="button" class="btn btn-white btn-black-white btn-primary-white size-sm" id="add-to-address-book"><i
                                class="fa-solid fa-plus"></i>Add to Address Book</button>
                    </div>
                </div>
                <div class="success-add-address-book mt-2"></div>
                <div class="custom-table-responsive">
                    <table class="order-summary-table">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Contact</th>
                                <th>Sender</th>
                                <th>Gift Card</th>
                                <th>Message</th>
                                <th>Delivery</th>
                                <th>Fulfillment Cost</th>
                                <th>Delivery Cost</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="order-summary-body">
                            <!-- Data will be populated dynamically -->
                        </tbody>
                        <tfoot id="order-summary-totals">
                            <!-- Totals will be added here -->
                        </tfoot>
                    </table>
                </div>
                <div class="page-bottom-toolbar">
                    <div class="right-block right order-summary-actions">
                        <div class="save-next-button-controls page-bottom-actions">
                            <button type="button" class="btn btn-primary btn-black-white btn-primary-black" id="confirm-to-payment">Confirm to
                                Payment</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="payment-confirmation-container" id="payment-confirmation-container" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <!-- <button type="button" class="btn btn-outline-secondary" id="back-to-order-summary">← Back to Order
                        Summary</button> -->
                </div>

                <!-- Order Summary Section -->
                <div class="order-summary-section mb-2 mb-md-5">
                    <div class="order-summary-tab-top">
                        <h4 class="section-title">Order Summary</h4>
                        <div class="top-summary-totals grand-total"> <span id="payment-total">TOTAL $</span></div>
                    </div>
                    <div class="order-summary-tab-content">
                        <div class="summary-totals">
                            <div class="d-flex justify-content-between">
                                <span>Subtotal:</span>
                                <span id="payment-subtotal">$0.00</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>GST:</span>
                                <span id="payment-gst">$0.00</span>
                            </div>
                            <div class="d-flex justify-content-between grand-total">
                                <span>TOTAL:</span>
                                <span id="order-payment-total">$0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Tabs -->
                <div class="payment-method-section mb-2 mb-md-5">
                    <h4 class="section-title">Payment Method</h4>
                    <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="credit-tab" data-bs-toggle="tab"
                                data-bs-target="#credit" type="button" role="tab">
                                <span class="icon">
                                    <svg width="30" height="30" viewBox="0 0 30 30" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M26.7376 6H3.34933C2.99146 6 2.64826 6.14535 2.39521 6.40407C2.14216 6.6628 2 7.0137 2 7.37959V23.0149C2 23.3808 2.14216 23.7317 2.39521 23.9905C2.64826 24.2492 2.99146 24.3945 3.34933 24.3945H26.7376C27.0955 24.3945 27.4387 24.2492 27.6917 23.9905C27.9448 23.7317 28.087 23.3808 28.087 23.0149V7.37959C28.087 7.0137 27.9448 6.6628 27.6917 6.40407C27.4387 6.14535 27.0955 6 26.7376 6ZM2.89955 10.5986H27.1874V11.5184H2.89955V10.5986ZM3.34933 6.91973H26.7376C26.8569 6.91973 26.9713 6.96818 27.0557 7.05442C27.14 7.14066 27.1874 7.25763 27.1874 7.37959V9.67891H2.89955V7.37959C2.89955 7.25763 2.94694 7.14066 3.03129 7.05442C3.11564 6.96818 3.23004 6.91973 3.34933 6.91973ZM26.7376 23.4748H3.34933C3.23004 23.4748 3.11564 23.4264 3.03129 23.3401C2.94694 23.2539 2.89955 23.1369 2.89955 23.0149V12.4381H27.1874V23.0149C27.1874 23.1369 27.14 23.2539 27.0557 23.3401C26.9713 23.4264 26.8569 23.4748 26.7376 23.4748Z"
                                            fill="#007AFF" />
                                        <path
                                            d="M10.5463 18.4141H5.14899C5.02971 18.4141 4.9153 18.4625 4.83095 18.5488C4.74661 18.635 4.69922 18.752 4.69922 18.8739C4.69922 18.9959 4.74661 19.1129 4.83095 19.1991C4.9153 19.2853 5.02971 19.3338 5.14899 19.3338H10.5463C10.6656 19.3338 10.78 19.2853 10.8643 19.1991C10.9487 19.1129 10.9961 18.9959 10.9961 18.8739C10.9961 18.752 10.9487 18.635 10.8643 18.5488C10.78 18.4625 10.6656 18.4141 10.5463 18.4141Z"
                                            fill="#007AFF" />
                                        <path
                                            d="M10.5463 20.7148H5.14899C5.02971 20.7148 4.9153 20.7633 4.83095 20.8495C4.74661 20.9358 4.69922 21.0527 4.69922 21.1747C4.69922 21.2967 4.74661 21.4136 4.83095 21.4999C4.9153 21.5861 5.02971 21.6346 5.14899 21.6346H10.5463C10.6656 21.6346 10.78 21.5861 10.8643 21.4999C10.9487 21.4136 10.9961 21.2967 10.9961 21.1747C10.9961 21.0527 10.9487 20.9358 10.8643 20.8495C10.78 20.7633 10.6656 20.7148 10.5463 20.7148Z"
                                            fill="#007AFF" />
                                        <path
                                            d="M23.5893 17.9571C23.2663 17.9587 22.9497 18.0492 22.6727 18.2192C22.3957 18.3891 22.1685 18.6321 22.0151 18.9228C21.8249 18.5625 21.5225 18.2774 21.1559 18.1126C20.7893 17.9478 20.3795 17.9128 19.9912 18.0131C19.6029 18.1134 19.2585 18.3433 19.0125 18.6663C18.7664 18.9893 18.6328 19.3871 18.6328 19.7965C18.6328 20.2059 18.7664 20.6037 19.0125 20.9267C19.2585 21.2497 19.6029 21.4796 19.9912 21.5799C20.3795 21.6802 20.7893 21.6452 21.1559 21.4804C21.5225 21.3157 21.8249 21.0305 22.0151 20.6702C22.1663 20.9567 22.3892 21.197 22.6609 21.3666C22.9327 21.5362 23.2436 21.629 23.5619 21.6356C23.8802 21.6421 24.1945 21.5622 24.4727 21.404C24.751 21.2458 24.9832 21.0149 25.1456 20.735C25.308 20.455 25.3949 20.136 25.3973 19.8105C25.3997 19.485 25.3176 19.1647 25.1594 18.8822C25.0012 18.5998 24.7724 18.3653 24.4966 18.2028C24.2208 18.0402 23.9077 17.9554 23.5893 17.9571ZM20.4409 20.7162C20.263 20.7162 20.089 20.6623 19.9411 20.5612C19.7932 20.4602 19.6779 20.3165 19.6098 20.1485C19.5417 19.9804 19.5239 19.7955 19.5586 19.6171C19.5933 19.4387 19.679 19.2748 19.8048 19.1462C19.9306 19.0175 20.0909 18.9299 20.2654 18.8945C20.4399 18.859 20.6208 18.8772 20.7851 18.9468C20.9495 19.0164 21.09 19.1343 21.1888 19.2855C21.2877 19.4368 21.3404 19.6146 21.3404 19.7965C21.3404 20.0404 21.2457 20.2744 21.077 20.4469C20.9083 20.6193 20.6795 20.7162 20.4409 20.7162ZM23.5893 20.7162C23.4114 20.7162 23.2375 20.6623 23.0895 20.5612C22.9416 20.4602 22.8263 20.3165 22.7582 20.1485C22.6901 19.9804 22.6723 19.7955 22.707 19.6171C22.7418 19.4387 22.8274 19.2748 22.9532 19.1462C23.079 19.0175 23.2393 18.9299 23.4138 18.8945C23.5883 18.859 23.7692 18.8772 23.9336 18.9468C24.0979 19.0164 24.2384 19.1343 24.3373 19.2855C24.4361 19.4368 24.4889 19.6146 24.4889 19.7965C24.4889 20.0404 24.3941 20.2744 24.2254 20.4469C24.0567 20.6193 23.8279 20.7162 23.5893 20.7162Z"
                                            fill="#007AFF" />
                                    </svg>
                                </span>
                                Credit
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank"
                                type="button" role="tab">
                                <span class="icon">
                                    <svg width="30" height="30" viewBox="0 0 30 30" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M14.4994 6.96484C13.6716 6.96484 13 7.62816 13 8.44571C13 9.26451 13.6716 9.92783 14.4994 9.92783C15.3284 9.92783 16 9.26451 16 8.44571C16 7.62816 15.3284 6.96484 14.4994 6.96484ZM13.9992 8.44571C13.9992 8.17361 14.2239 7.95166 14.4994 7.95166C14.7761 7.95166 14.9996 8.1736 14.9996 8.44571C14.9996 8.71906 14.7762 8.93976 14.4994 8.93976C14.2239 8.93976 13.9992 8.71907 13.9992 8.44571Z"
                                            fill="#007AFF" />
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M14.4998 13.875C14.7766 13.875 15.0001 14.0969 15.0001 14.369V14.8631H16.0005C16.276 14.8631 16.5007 15.0838 16.5007 15.3571C16.5007 15.6305 16.276 15.8512 16.0005 15.8512H15.0001V16.8393H15.5003C16.328 16.8393 16.9997 17.5026 16.9997 18.3201C16.9997 19.1389 16.328 19.8022 15.5003 19.8022H15.0001V20.295C15.0001 20.5684 14.7766 20.7891 14.4998 20.7891C14.2243 20.7891 13.9996 20.5684 13.9996 20.295V19.8022H13.0005C12.7237 19.8022 12.5002 19.5803 12.5002 19.3082C12.5002 19.0348 12.7237 18.8141 13.0005 18.8141H13.9996V17.8261H13.4994C12.6716 17.8261 12 17.1628 12 16.3452C12 15.5264 12.6716 14.8631 13.4994 14.8631H13.9996V14.369C13.9996 14.097 14.2243 13.875 14.4998 13.875ZM13.9996 15.8511H13.4994C13.2239 15.8511 13.0004 16.0718 13.0004 16.3452C13.0004 16.6173 13.2239 16.8392 13.4994 16.8392H13.9996V15.8511ZM15 17.826V18.8141H15.5002C15.7757 18.8141 16.0005 18.5934 16.0005 18.32C16.0005 18.0479 15.7758 17.826 15.5002 17.826L15 17.826Z"
                                            fill="#007AFF" />
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M14.311 4.03668C14.4329 3.98777 14.5688 3.98777 14.6894 4.03668L25.6904 8.48059C25.8783 8.55707 26.0014 8.73764 26.0014 8.93827V11.9012C26.0014 12.1746 25.7767 12.3953 25.5012 12.3953H23.0014V21.8083C23.8825 21.9437 24.6353 22.538 24.9553 23.3807L25.9697 26.049C26.0268 26.2007 26.0052 26.3712 25.9113 26.5041C25.8186 26.637 25.665 26.716 25.5012 26.716H3.50083C3.33578 26.716 3.18216 26.6371 3.08949 26.5041C2.99554 26.3712 2.97396 26.2007 3.03236 26.049L4.04549 23.3807C4.36542 22.538 5.11955 21.9437 6.00063 21.8083V12.3953H3.50083C3.22407 12.3953 3.00061 12.1746 3.00061 11.9012V8.93827C3.00061 8.73764 3.12249 8.55708 3.31038 8.48059L14.311 4.03668ZM20 12.3952H22.0009V21.778H20V12.3952ZM3.99971 11.4071V9.26919L14.5005 5.02718L25.0013 9.26919V11.4071H3.99971ZM19.0009 21.7783V12.3955H10.001V21.7783H19.0009ZM6.99956 12.3955H9.0004V21.7783H6.99956V12.3955ZM6.38636 22.7666C5.76046 22.7666 5.20059 23.1491 4.98222 23.7271L4.22175 25.7296H24.7787L24.0195 23.7271C23.7999 23.1491 23.24 22.7666 22.6141 22.7666H6.38636Z"
                                            fill="#007AFF" />
                                    </svg>
                                </span>
                                Bank Transfer
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link disabled" id="client-tab" data-bs-toggle="tab"
                                data-bs-target="#client" type="button" role="tab">
                                <span class="icon">
                                    <svg width="30" height="30" viewBox="0 0 30 30" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M14.9783 3C8.37372 3 3 8.38347 3 14.9992C3 21.6165 8.37372 27 14.9783 27C21.5828 27 26.9565 21.6165 26.9565 14.9992C26.9565 8.38347 21.5828 3 14.9783 3ZM14.9783 25.3819C9.26384 25.3819 4.6152 20.7237 4.6152 14.9992C4.6152 9.27467 9.26384 4.61813 14.9783 4.61813C20.6927 4.61813 25.3413 9.27467 25.3413 14.9992C25.3413 20.7237 20.6927 25.3819 14.9783 25.3819Z"
                                            fill="#007AFF" />
                                        <path
                                            d="M15.2626 14.3574C13.3754 13.9862 12.4866 13.7065 12.4866 12.7598C12.4866 12.0755 12.7823 11.6001 13.3903 11.3046C14.447 10.7926 16.2009 10.9918 17.1432 11.7345C17.4948 12.0126 18.0019 11.951 18.2771 11.5985C18.5532 11.2478 18.4925 10.739 18.1414 10.4641C17.4961 9.95579 16.6569 9.63205 15.7835 9.48859V8.05125C15.7835 7.60405 15.4223 7.24219 14.9759 7.24219C14.5295 7.24219 14.1683 7.60405 14.1683 8.05125V9.44192C13.6397 9.50245 13.1318 9.63205 12.6849 9.84939C11.5153 10.4182 10.8711 11.4515 10.8711 12.7598C10.8711 15.1409 13.2289 15.6057 14.9498 15.9451C16.807 16.3102 17.4663 16.5614 17.4663 17.2409C17.4663 17.9062 17.1863 18.3753 16.6108 18.6723C15.5967 19.2001 13.9003 19.0435 12.9034 18.3358C12.539 18.0798 12.0351 18.1635 11.778 18.5286C11.5201 18.8937 11.6052 19.3977 11.9696 19.6569C12.5888 20.0966 13.3647 20.3793 14.1683 20.5099V22.4443C14.1683 22.8915 14.5295 23.2534 14.9759 23.2534C15.4223 23.2534 15.7835 22.8915 15.7835 22.4443V20.5315C16.3468 20.4681 16.8914 20.3494 17.3529 20.1105C18.4672 19.5337 19.0815 18.5145 19.0815 17.2411C19.0821 15.1097 16.9646 14.6926 15.2626 14.3574Z"
                                            fill="#007AFF" />
                                    </svg>
                                </span>
                                Client Billing
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="prepaid-tab" data-bs-toggle="tab" data-bs-target="#prepaid"
                                type="button" role="tab">
                                <span class="icon">
                                    <svg width="30" height="30" viewBox="0 0 30 30" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M24.3028 19.94H20.7997C19.7844 19.94 18.9587 20.6789 18.9587 21.587C18.9587 22.4951 19.7847 23.234 20.7997 23.234H24.3028C25.3182 23.234 26.1438 22.4951 26.1438 21.587C26.1441 20.6789 25.3182 19.94 24.3028 19.94ZM24.3028 22.5206H20.7997C20.2243 22.5206 19.7562 22.1018 19.7562 21.587C19.7562 21.0722 20.2243 20.6534 20.7997 20.6534H24.3028C24.8783 20.6534 25.3463 21.0722 25.3463 21.587C25.3463 22.1018 24.8783 22.5206 24.3028 22.5206ZM3.63657 19.0248H11.0037C11.3692 19.0248 11.6663 18.7591 11.6663 18.4321V15.8269C11.6663 15.4999 11.3692 15.2342 11.0037 15.2342H3.63657C3.27109 15.2342 2.97402 15.4999 2.97402 15.8269V18.4321C2.97402 18.7588 3.27109 19.0248 3.63657 19.0248ZM3.77148 15.9476H10.8685V18.3111H3.77148V15.9476ZM13.5558 21.587C13.5558 21.7842 13.3771 21.9437 13.157 21.9437H8.88404C8.66362 21.9437 8.48531 21.7839 8.48531 21.587C8.48531 21.3901 8.66393 21.2303 8.88404 21.2303H13.157C13.3771 21.2303 13.5558 21.3901 13.5558 21.587ZM6.71905 21.587C6.71905 21.7842 6.54043 21.9437 6.32032 21.9437H3.37307C3.15264 21.9437 2.97434 21.7839 2.97434 21.587C2.97434 21.3901 3.15296 21.2303 3.37307 21.2303H6.32032C6.54043 21.2303 6.71905 21.3901 6.71905 21.587ZM17.031 21.587C17.031 21.7842 16.8523 21.9437 16.6322 21.9437H15.0655C14.8451 21.9437 14.6668 21.7839 14.6668 21.587C14.6668 21.3901 14.8454 21.2303 15.0655 21.2303H16.6322C16.8523 21.2303 17.031 21.3901 17.031 21.587ZM30 11.7909C30 8.04637 26.5948 5 22.4092 5C19.1481 5 16.3611 6.8493 15.2891 9.43695H2.73649C1.77909 9.43695 1 10.1339 1 10.9905V23.4465C1 24.303 1.77909 25 2.73649 25H26.3817C27.3391 25 28.1181 24.303 28.1181 23.4465V16.2615C29.289 15.0662 30 13.5016 30 11.7909ZM22.4095 5.71371C26.1552 5.71371 29.2025 8.43991 29.2025 11.7909C29.2025 15.1418 26.1552 17.868 22.4095 17.868C18.6639 17.868 15.6162 15.1418 15.6162 11.7909C15.6162 8.43991 18.6636 5.71371 22.4095 5.71371ZM14.8441 12.3453C14.8789 12.7293 14.9496 13.1044 15.0531 13.4679H1.79746V12.3453H14.8441ZM2.73649 10.1507H15.043C14.9106 10.6267 14.834 11.1225 14.821 11.6319H1.79746V10.9907C1.79746 10.5275 2.21868 10.1507 2.73649 10.1507ZM26.3813 24.2866H2.73649C2.21868 24.2866 1.79746 23.9097 1.79746 23.4465V14.1816H15.3046C16.388 16.75 19.1636 18.5817 22.4092 18.5817C24.2806 18.5817 25.9956 17.9726 27.3204 16.9645V23.4468C27.3204 23.9097 26.8991 24.2866 26.3813 24.2866ZM22.4095 16.7919C25.492 16.7919 27.9997 14.5485 27.9997 11.7909C27.9997 9.03321 25.492 6.7898 22.4095 6.7898C19.3271 6.7898 16.8191 9.03321 16.8191 11.7909C16.8191 14.5485 19.3271 16.7919 22.4095 16.7919ZM22.4095 7.50322C25.0521 7.50322 27.2022 9.42675 27.2022 11.7909C27.2022 14.155 25.0521 16.0785 22.4095 16.0785C19.767 16.0785 17.6169 14.1553 17.6169 11.7909C17.6169 9.42647 19.7666 7.50322 22.4095 7.50322ZM22.0105 12.111V13.9484C21.8829 13.9005 21.765 13.8297 21.6634 13.7379C21.4664 13.5597 21.3597 13.3288 21.3628 13.0879C21.3654 12.891 21.189 12.7293 20.9685 12.727C20.7481 12.7244 20.5676 12.8825 20.5651 13.0797C20.5594 13.5127 20.7478 13.9246 21.0959 14.2394C21.3514 14.4706 21.6672 14.6261 22.0102 14.6941V15.067C22.0102 15.2642 22.1888 15.4237 22.4089 15.4237C22.629 15.4237 22.8076 15.2639 22.8076 15.067V14.6944C23.6333 14.5309 24.2531 13.8711 24.2531 13.084C24.2531 12.64 24.0586 12.2229 23.7055 11.9096C23.4537 11.6863 23.144 11.5367 22.808 11.4715V9.63358C22.9356 9.68147 23.0534 9.7523 23.1551 9.8441C23.3521 10.0223 23.4588 10.2532 23.4556 10.4941C23.4531 10.691 23.6295 10.8528 23.8496 10.855H23.8543C24.0726 10.855 24.2505 10.6981 24.2531 10.5023C24.2588 10.0693 24.0703 9.65738 23.7223 9.3426C23.4667 9.11141 23.1509 8.95614 22.808 8.88786V8.51471C22.808 8.31751 22.6293 8.158 22.4092 8.158C22.1891 8.158 22.0105 8.3178 22.0105 8.51471V8.88729C21.1848 9.05077 20.5651 9.71065 20.5651 10.4977C20.5651 10.9417 20.7595 11.3588 21.1126 11.6721C21.3647 11.8954 21.6748 12.0459 22.0105 12.111ZM23.144 12.4162C23.3451 12.5944 23.4556 12.8315 23.4556 13.084C23.4556 13.4738 23.1877 13.8085 22.808 13.9493V12.2167C22.9318 12.2618 23.0455 12.3286 23.144 12.4162ZM22.0105 9.63273V11.3659C21.887 11.3202 21.773 11.2531 21.6745 11.1656C21.4734 10.9873 21.3628 10.7502 21.3628 10.4977C21.3628 10.1079 21.6308 9.77327 22.0105 9.63273Z"
                                            fill="#007AFF" />
                                    </svg>
                                </span>
                                Pre-Paid Credit
                            </button>
                        </li>
                        <!-- <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cod-tab" data-bs-toggle="tab" data-bs-target="#cod" type="button"
                                role="tab">
                                <span class="icon">
                                     <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M26.7376 6H3.34933C2.99146 6 2.64826 6.14535 2.39521 6.40407C2.14216 6.6628 2 7.0137 2 7.37959V23.0149C2 23.3808 2.14216 23.7317 2.39521 23.9905C2.64826 24.2492 2.99146 24.3945 3.34933 24.3945H26.7376C27.0955 24.3945 27.4387 24.2492 27.6917 23.9905C27.9448 23.7317 28.087 23.3808 28.087 23.0149V7.37959C28.087 7.0137 27.9448 6.6628 27.6917 6.40407C27.4387 6.14535 27.0955 6 26.7376 6ZM2.89955 10.5986H27.1874V11.5184H2.89955V10.5986ZM3.34933 6.91973H26.7376C26.8569 6.91973 26.9713 6.96818 27.0557 7.05442C27.14 7.14066 27.1874 7.25763 27.1874 7.37959V9.67891H2.89955V7.37959C2.89955 7.25763 2.94694 7.14066 3.03129 7.05442C3.11564 6.96818 3.23004 6.91973 3.34933 6.91973ZM26.7376 23.4748H3.34933C3.23004 23.4748 3.11564 23.4264 3.03129 23.3401C2.94694 23.2539 2.89955 23.1369 2.89955 23.0149V12.4381H27.1874V23.0149C27.1874 23.1369 27.14 23.2539 27.0557 23.3401C26.9713 23.4264 26.8569 23.4748 26.7376 23.4748Z" fill="#007AFF"/>
                                    <path d="M10.5463 18.4141H5.14899C5.02971 18.4141 4.9153 18.4625 4.83095 18.5488C4.74661 18.635 4.69922 18.752 4.69922 18.8739C4.69922 18.9959 4.74661 19.1129 4.83095 19.1991C4.9153 19.2853 5.02971 19.3338 5.14899 19.3338H10.5463C10.6656 19.3338 10.78 19.2853 10.8643 19.1991C10.9487 19.1129 10.9961 18.9959 10.9961 18.8739C10.9961 18.752 10.9487 18.635 10.8643 18.5488C10.78 18.4625 10.6656 18.4141 10.5463 18.4141Z" fill="#007AFF"/>
                                    <path d="M10.5463 20.7148H5.14899C5.02971 20.7148 4.9153 20.7633 4.83095 20.8495C4.74661 20.9358 4.69922 21.0527 4.69922 21.1747C4.69922 21.2967 4.74661 21.4136 4.83095 21.4999C4.9153 21.5861 5.02971 21.6346 5.14899 21.6346H10.5463C10.6656 21.6346 10.78 21.5861 10.8643 21.4999C10.9487 21.4136 10.9961 21.2967 10.9961 21.1747C10.9961 21.0527 10.9487 20.9358 10.8643 20.8495C10.78 20.7633 10.6656 20.7148 10.5463 20.7148Z" fill="#007AFF"/>
                                    <path d="M23.5893 17.9571C23.2663 17.9587 22.9497 18.0492 22.6727 18.2192C22.3957 18.3891 22.1685 18.6321 22.0151 18.9228C21.8249 18.5625 21.5225 18.2774 21.1559 18.1126C20.7893 17.9478 20.3795 17.9128 19.9912 18.0131C19.6029 18.1134 19.2585 18.3433 19.0125 18.6663C18.7664 18.9893 18.6328 19.3871 18.6328 19.7965C18.6328 20.2059 18.7664 20.6037 19.0125 20.9267C19.2585 21.2497 19.6029 21.4796 19.9912 21.5799C20.3795 21.6802 20.7893 21.6452 21.1559 21.4804C21.5225 21.3157 21.8249 21.0305 22.0151 20.6702C22.1663 20.9567 22.3892 21.197 22.6609 21.3666C22.9327 21.5362 23.2436 21.629 23.5619 21.6356C23.8802 21.6421 24.1945 21.5622 24.4727 21.404C24.751 21.2458 24.9832 21.0149 25.1456 20.735C25.308 20.455 25.3949 20.136 25.3973 19.8105C25.3997 19.485 25.3176 19.1647 25.1594 18.8822C25.0012 18.5998 24.7724 18.3653 24.4966 18.2028C24.2208 18.0402 23.9077 17.9554 23.5893 17.9571ZM20.4409 20.7162C20.263 20.7162 20.089 20.6623 19.9411 20.5612C19.7932 20.4602 19.6779 20.3165 19.6098 20.1485C19.5417 19.9804 19.5239 19.7955 19.5586 19.6171C19.5933 19.4387 19.679 19.2748 19.8048 19.1462C19.9306 19.0175 20.0909 18.9299 20.2654 18.8945C20.4399 18.859 20.6208 18.8772 20.7851 18.9468C20.9495 19.0164 21.09 19.1343 21.1888 19.2855C21.2877 19.4368 21.3404 19.6146 21.3404 19.7965C21.3404 20.0404 21.2457 20.2744 21.077 20.4469C20.9083 20.6193 20.6795 20.7162 20.4409 20.7162ZM23.5893 20.7162C23.4114 20.7162 23.2375 20.6623 23.0895 20.5612C22.9416 20.4602 22.8263 20.3165 22.7582 20.1485C22.6901 19.9804 22.6723 19.7955 22.707 19.6171C22.7418 19.4387 22.8274 19.2748 22.9532 19.1462C23.079 19.0175 23.2393 18.9299 23.4138 18.8945C23.5883 18.859 23.7692 18.8772 23.9336 18.9468C24.0979 19.0164 24.2384 19.1343 24.3373 19.2855C24.4361 19.4368 24.4889 19.6146 24.4889 19.7965C24.4889 20.0404 24.3941 20.2744 24.2254 20.4469C24.0567 20.6193 23.8279 20.7162 23.5893 20.7162Z" fill="#007AFF"/>
                                    </svg>
                                </span>
                                Cash on Delivery
                            </button>
                        </li> -->
                    </ul>

                    <div class="tab-content bg-white">
                        <!-- Credit Tab -->
                        <div class="tab-pane fade show active" id="credit" role="tabpanel">
                            <div class="tab-header-bar">
                                <span class="top-header-label">Or pay with Credit Card</span>
                            </div>
                            <div class="row">
                                <div class="form-group flex-row">
                                    <div class="control-wrapper col">
                                        <label class="label">Card Number</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="1234 5678 9012 3456">
                                            <span class="input-group-text"><i class="fab fa-cc-visa"></i> <i
                                                    class="fab fa-cc-mastercard ms-2"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group flex-row">
                                    <div class="control-wrapper col">
                                        <label class="label">Name on Card</label>
                                        <input type="text" class="form-control" placeholder="John Doe">
                                    </div>
                                </div>
                                <div class="form-group flex-row">
                                    <div class="control-wrapper col col-6">
                                        <label class="label">Expiry Date</label>
                                        <input type="text" class="form-control" placeholder="MM/YY">
                                    </div>
                                    <div class="control-wrapper col col-6">
                                        <label class="label">CVV</label>
                                        <input type="text" class="form-control" placeholder="123">
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-3 custom-pr-0">
                                <input class="form-check-input" type="checkbox" id="save-card">
                                <label class="form-check-label" for="save-card">
                                    Save card for future payments
                                </label>
                            </div>
                            <!-- <button class="btn btn-primary">Pay with Credit Card</button> -->
                        </div>

                        <!-- Bank Transfer Tab -->
                        <div class="tab-pane fade" id="bank" role="tabpanel">
                            <div class="bank-transfer-details">
                                <h2 class="pay-with-bank">Pay with Bank Transfer account </h2>
                                <div class="mb-3">
                                    <label class="form-label">Account Name</label>
                                    <input type="text" class="form-control" value="Commonwealth Bank" readonly>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">BSB</label>
                                        <input type="text" class="form-control" value="GiftCards Plus Pty Ltd" readonly>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" class="form-control" value="12345678" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reference</label>
                                    <div class="input-group">
                                        <input type="checkbox" class="form-control" id="bank-reference"
                                            value="Remember Bank Account Details">Remember Bank Account Details
                                        </checkbox>
                                    </div>
                                </div>
                                <!-- <button class="btn btn-primary">I've Made the Transfer</button> -->
                            </div>
                        </div>

                        <div class="tab-pane fade" id="client" role="tabpanel">
                            <div class="client-billing-details">
                                <h2 class="client-billing-header">
                                    Client Billing
                                </h2>
                                <div class="mb-3">
                                    <label class="form-label">Client PO</label>
                                    <input type="text" class="form-control client-po" placeholder="PO-12345">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Order Reference</label>
                                    <input type="text" class="form-control order-reference" placeholder="$10,000.00" readonly>
                                </div>
                                <!-- <button class="btn btn-primary">Confirm Client Billing</button> -->
                            </div>
                        </div>

                        <!-- Pre-Paid Credit Tab -->
                        <div class="tab-pane fade" id="prepaid" role="tabpanel">
                            <div class="prepaid-credit-details">
                                <!-- <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-circle me-2"></i> Your current balance will be used for
                                    this payment
                                </div> -->

                                <div class="row">
                                    <div class="col-md-8">
                                        <h2 class="pay-with-pre-paid-credit-header">Pay with Pre-Paid Credit</h2>
                                        <h5 id="pay-with-pre-paid-balance">Available balance: AUD <span>00.00</span>
                                        </h5>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="float-end btn-black-white btn-primary-white" id="add-pre-paid-credit">Top Up
                                            Now</button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Order Reference</label>
                                    <input type="text" class="form-control order_reference" id="order_reference"
                                        value="" placeholder="22 - 0">
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <h3 class="pay-with-pre-paid-credit-header" id="pay-with-pre-paid-remaining">
                                        Remaining Balance: AUD <span>00.00</span></h3>
                                </div>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-circle me-2"></i> Once an eGift card has been
                                    delivered, we cannot cancel or refund that eGift Card. See our <strong>Terms of
                                        Service.</strong>
                                </div>
                            </div>
                        </div>
                        <!-- <div class="tab-pane fade" id="cod" role="tabpanel">
                            <div class="cod-details">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> Pay when you receive the order
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">COD Instructions</label>
                                    <p>Your order will be prepared for delivery. Payment will be collected when the order is
                                        delivered to you.</p>
                                </div>
                                <button class="btn btn-primary" id="place-cod-order">Place COD Order</button>
                            </div>
                        </div> -->
                    </div>

                </div>

                <!-- Business Invoice Section -->
                <div class="business-invoice-section mb-2 mb-md-4">
                    <div class="business-invoice-tab-top">
                        <h4 class="section-title">Business Invoice</h4>
                    </div>
                    <div class="invoice-details mt-3" id="invoice-details">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" placeholder="Company Pty Ltd">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">ABN</label>
                                <input type="text" class="form-control" placeholder="12 345 678 901">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Billing Address</label>
                            <textarea class="form-control" rows="3"
                                placeholder="Street Address, City, State, Postcode"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes (Optional)</label>
                            <textarea class="form-control" rows="2"
                                placeholder="Special instructions for invoice"></textarea>
                        </div>
                        <div class="form-check form-switch mb-3 custom-pr-0">
                            <input class="form-check-input" type="checkbox" id="need-invoice" role="switch">
                            <label class="form-check-label" for="need-invoice">
                                I require a business invoice
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Place Order Button -->
                <div class="page-bottom-toolbar place-order-section">
                    <div class="right-block right">
                        <div id="order-error-message" style="margin-top: 10px;"></div>
                        <div class="page-bottom-actions">
                            <!-- <i class="fas fa-check-circle me-2"></i>Confirm and Place Order -->
                            <button type="button" class="btn btn-primary btn-lg btn-black-white btn-primary-black w-100" id="place-order-btn" data-status="<?= $order_status; ?>" data-order-id="<?= $order_id; ?>"> Place order
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div id="multi-step-form-bulk" class="d-none bulk-upload">
        <!-- Progress Bar -->
        <div class="multi-step-form-container bulk-container">
            <div class="container">
                <div class="text-step-indicator progress-container d-flex align-items-center">
                    <div class="step active-step back-to-recipient-form" data-type="bulk">Create Order</div>
                    <div class="step back-to-customisation">Personalisation</div>
                    <div class="step">Delivery</div>
                    <div class="step">Order Summary</div>
                    <div class="step">Payment and Confirmation</div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3 bulk-order-top">
                <button type="button" class="arrow" id="back-to-new-order-form">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none">
                        <path
                            d="M22.4598 8.95444H5.2559L12.772 2.32605C13.3727 1.79632 13.3727 0.927024 12.772 0.397296C12.1713 -0.132432 11.201 -0.132432 10.6004 0.397296L0.450505 9.34834C-0.150168 9.87807 -0.150168 10.7338 0.450505 11.2635L10.6004 20.2146C11.201 20.7443 12.1713 20.7443 12.772 20.2146C13.3727 19.6848 13.3727 18.8291 12.772 18.2994L5.2559 11.671H22.4598C23.3069 11.671 24 11.0598 24 10.3127C24 9.56567 23.3069 8.95444 22.4598 8.95444Z"
                            fill="black"></path>
                    </svg>
                </button>
            </div>
            <div class="order-info">
                <h2 class="order-title">Order Name: <span id="display-order-name"></span></h2>
                <p class="text-muted">
                    Client Reference: <strong id="display-client-reference"></strong> <br>
                    Order Number: <strong
                        id="display-order-id"><?php if (!empty($order_id)) {
                            echo $order_id;
                        } ?></strong> <br>
                    Sender: <strong id="display-sender"></strong>
                </p>
            </div>
        </div>
        <!-- Step 1 -->
        <div class="container">
            <div class="form-container bulk-add-container">
                <div class="page-title center">
                    <h1>Bulk Upload Orders</h1>
                </div>
                <div class="sm-container">
                    <form class="bulk-upload-card-activation-wrapper">
                        <div class="mb-3 step">
                            <div class="content-wrapper">
                                <h5 class="step-title">Step 1</h5>
                                <p>Download the rewards template and instructions <span
                                        class="text-danger"><strong>(Important!)</strong></span></p>
                            </div>
                            <button type="button" class="btn btn-blue btn-primary" id="download-bulk-template"><i
                                    class="fas fa-download"></i> Download template & instructions</button>
                        </div>
                        <div class="mb-3 step">
                            <div class="content-wrapper">
                                <h5 class="step-title">Step 2</h5>
                                <p>Open the downloaded Excel document, review the instructions and fill in the required
                                    columns (denoted with a <strong>*****</strong>)
                                    <br> What details do I need for the CSV?
                                <ul>
                                    <li> Recipient Name</li>
                                    <li> Recipient Email Address or Mobile Number</li>
                                    <li> Card Value</li>
                                    <li> Card Message (Optional)</li>
                                    <li> Card ID</li>
                                </ul>
                                </p>
                            </div>
                            <div class="flex-column button-wrapper">
                                <button type="button" class="btn  btn-white btn-outline-secondary"
                                    id="download-sku-list"><i class="fas fa-download"></i> Download SKU
                                    List</button>
                                <a href="#" class="view-card-sku" id="view-card-sku">View all Card ID SKU</a>
                            </div>
                        </div>
                        <div class="mb-3 step">
                            <div class="content-wrapper">
                                <h5 class="step-title">Step 3</h5>
                                <div class="step-description">
                                    Review the document and esnsure it matches the instructions.
                                </div>
                                <div class="add-bulk-cat-warning">
                                    <i class="fas fa-exclamation-circle"></i>Upload your modified Excel document. This
                                    will show you a preview of the rewards that will be created and allow you to confirm
                                    the changes
                                </div>
                            </div>
                            <!-- Upload Button -->
                            <button type="button" class="btn btn-blue   btn-primary" id="upload-file-btn"><i
                                    class="fas fa-upload"></i>Upload File</button>
                        </div>

                        <!-- Review Fields Interface (Hidden Initially) -->
                        <div id="review-fields " class="d-none step mt-3">
                            <div class="content-wrapper">
                                <h3 class="step-title">Review Fields</h3>
                                <p>The following fields have been matched based on your data. Please choose an input
                                    field and
                                    review to ensure these are correct.</p>
                            </div>
                            <button type="button" class="btn  btn-blue btn-outline-secondary" id="back-to-upload">Back
                                to Upload</button>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="d-flex justify-content-between mt-4">

                        </div>
                        <div class="page-bottom-toolbar">
                            <div class="right-block">
                                <div class="save-next-button-controls page-bottom-actions">
                                    <button type="button" class="btn btn-outline btn-black-white btn-primary-white">Save Draft</button>
                                    <button type="button" class="btn btn-primary btn-black-white btn-primary-black" id="next-step">Next</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Mapping Interface -->
<div class="modal fade" id="mapping-modal" tabindex="-1" aria-labelledby="mapping-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mapping-modal-label">Review and Map Headers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="mapping-interface">
                <!-- Dynamically generated mapping form -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="apply-mapping">Apply</button>
            </div>
        </div>
    </div>
</div>
<!-- Upload File Modal (Hidden Initially) -->
<div class="modal fade upalod-file-popup" id="file-upload-modal" tabindex="-1" aria-labelledby="file-upload-modal-label"
    aria-hidden="true">
    <div class="modal-dialog custom-popup">
        <div class="modal-content custom-main-modal">
            <div class="modal-header custom-modal-header">
                <h3 class="modal-title" id="file-upload-modal-label">Upload CSV File</h3>
                <button type="button" class="btn-close close-modal" data-bs-dismiss="modal" aria-label="Close"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body custom-modal-body">
                <div id="bulk-upload-area" class="upload-area" style="cursor: pointer;">
                    <span id="upload-btn" class="upload-btn">Upload,</span>
                    <strong>Select a CSV file to upload</strong>
                    <p>or drag and drop it here</p>
                </div>
                <input type="file" id="csv-file-input1" class="form-control" style="display: none;" accept=".csv">
                <small class="text-danger d-block mt-2" id="file-error-msg">⚠️ Please select a CSV file.</small>
                <!-- File Name Display -->
                <div class="file-bottom-block">
                    <button type="button" id="remove-selected-file" class="close-btn">×</button>
                    <div id="file-name-display" style="display: none;">
                        <strong>Selected File:</strong> <span id="selected-file-name"></span>
                    </div>
                    <div id="upload-progress" class="progress mt-2" style="display: none;">
                        <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                    </div>
                </div>
                <!-- Progress Bar -->
                <div id="upload-progress" class="progress mt-2" style="display: none;">
                    <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                </div>
            </div>
            <div class="modal-footer popup-footer">
                <button type="button" class="btn btn-white" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submit-file-upload">Upload File</button>
            </div>
        </div>
    </div>
</div>
<!-- CSV Preview Section (Initially Hidden) -->
<div id="csv-preview" class="container mt-4 d-none">
    <h3>Bulk Upload Orders</h3>
    <h5>CSV Preview</h5>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <button type="button" class="btn btn-outline-secondary" id="back-to-bulk-upload">← Back</button>
        <button type="button" class="btn btn-dark" id="next-button">Next</button>
    </div>
    <div class="product-management-header top-filter-block bulk-order-management-header">
        <div class="search-container">
            <select id="filter-by" class="form-select">
                <option value="all">All</option>
                <option value="errors">Errors</option>
                <option value="no-errors">No Errors</option>
            </select>
        </div>

        <div class="action-buttons">
            <button class="btn btn-white btn-danger" id="remove-error-lines">Remove error lines</button>
            <button class="btn btn-white btn-warning" id="edit-errors">Edit errors</button>
            <button class="btn btn-blue" id="download-resubmit">Download and Resubmit</button>
        </div>
    </div>
    <div class="mt-3 message-container">
        <span id="correct-rows-count" class="badge correct-rows-count" style="display:none;"></span>
        <span id="error-rows-count" class="badge error-rows-count" style="display:none;"></span>
    </div>
    <div style="overflow-x: auto;">
        <table class="table table-striped" id="csv-preview-table">
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
    <div id="pagination" class="mt-3"></div>
</div>
<?php
$order = wc_get_order($order_id);

$form_data = [];
if ($order) {
    $form_data = $order->get_meta('_form_data');
    $form_data = !empty($form_data) ? json_decode($form_data, true) : [];
}

// Helper function to safely echo values
function prefill_value($data, $key, $default = '')
{
    return isset($data[$key]) ? esc_attr($data[$key]) : $default;
}

// Helper for select dropdowns
function prefill_selected($data, $key, $value)
{
    return (isset($data[$key]) && $data[$key] === $value) ? 'selected' : '';
}

// Helper for checkboxes
function prefill_checked($data, $key)
{
    return (!empty($data[$key])) ? 'checked' : '';
}
// 🟢 Clear expiry values if "no_activation_needed" is set
if (!empty($form_data['activation_expiry_type']) && $form_data['activation_expiry_type'] === 'no_activation_needed') {
    $form_data['activation_expiry_date'] = '';
    $form_data['activation_expiry_duration'] = '';
    $form_data['activation_expiry_unit'] = '';
}
?>
<!-- Card Activation Form (Initially Hidden) -->
<div id="card-activation-form" class="container mt-4 d-none">
    <button type="button" class="btn btn-outline-secondary" id="back-to-csv-preview">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none"> 
            <path d="M22.4598 8.95444H5.2559L12.772 2.32605C13.3727 1.79632 13.3727 0.927024 12.772 0.397296C12.1713 -0.132432 11.201 -0.132432 10.6004 0.397296L0.450505 9.34834C-0.150168 9.87807 -0.150168 10.7338 0.450505 11.2635L10.6004 20.2146C11.201 20.7443 12.1713 20.7443 12.772 20.2146C13.3727 19.6848 13.3727 18.8291 12.772 18.2994L5.2559 11.671H22.4598C23.3069 11.671 24 11.0598 24 10.3127C24 9.56567 23.3069 8.95444 22.4598 8.95444Z" fill="black">
            </path>
        </svg>
    </button>
    <h3 class="personalise-title">Personalise Your Order</h3>

    <form id="bulk-card-activation-form">
    <div class="bulk-card-activation-wrapper">
            <h3 class="card-activation-title">Card Activation</h3>
            <div class="activation-expiry-wrapper">
                <label for="activation_expiry_type">Activation Expiry Type<span class="validate">*</span></label>
                <select id="bulk_activation_expiry_type" name="activation_expiry_type">
                    <option value="default" <?= prefill_selected($form_data, 'activation_expiry_type', 'default'); ?>>Default</option>
                    <option value="no_activation_expiry" <?= prefill_selected($form_data, 'activation_expiry_type', 'no_activation_expiry'); ?>>No Activation Expiry</option>
                    <option value="no_activation_needed" <?= prefill_selected($form_data, 'activation_expiry_type', 'no_activation_needed'); ?>>No Activation Needed</option>
                    <option value="activation_set_date" <?= prefill_selected($form_data, 'activation_expiry_type', 'activation_set_date'); ?>>Activated by a Set Date</option>
                    <option value="set_period" <?= prefill_selected($form_data, 'activation_expiry_type', 'set_period'); ?>>Activated within a Set Period</option>
                </select>

                <div id="bulk_activation_expiry_date_field"
                    style="<?= prefill_value($form_data, 'activation_expiry_type') === 'activation_set_date' ? '' : 'display:none;'; ?>">
                    <label for="activation_expiry_date">Activation Expiry Date</label>
                    <input type="datetime-local" id="activation-expiry-date" name="activation_expiry_date"
                        value="<?= prefill_value($form_data, 'activation_expiry_date'); ?>" min="2025-05-02">
                </div>

                <div id="bulk_activation_expiry_period_field"
                    style="<?= prefill_value($form_data, 'activation_expiry_type') === 'set_period' ? '' : 'display:none;'; ?>">
                    <label for="activation_expiry_duration">Activation Expiry Period</label>
                    <div class="expiry-input-group">
                        <input type="number" id="bulk_activation_expiry_duration" name="activation_expiry_duration"
                            min="1" value="<?= prefill_value($form_data, 'activation_expiry_duration'); ?>">
                        <select id="activation_expiry_unit" name="activation_expiry_unit">
                            <option value="days" <?= prefill_selected($form_data, 'activation_expiry_unit', 'days'); ?>>Days</option>
                            <option value="weeks" <?= prefill_selected($form_data, 'activation_expiry_unit', 'weeks'); ?>>Weeks</option>
                            <option value="months" <?= prefill_selected($form_data, 'activation_expiry_unit', 'months'); ?>>Months</option>
                            <option value="years" <?= prefill_selected($form_data, 'activation_expiry_unit', 'years'); ?>>Years</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label required-field">Sender Details</label>
            <div class="d-flex gap-2">
                <select id="sender-dropdown" class="bulk-sender-dropdown" name="sender_details" class="form-select">
                    <option disabled>Select sender</option>
                    <option value="yahoo" <?= prefill_selected($form_data, 'sender_details', 'yahoo'); ?>>Yahoo</option>
                    <option value="gmail" <?= prefill_selected($form_data, 'sender_details', 'gmail'); ?>>Gmail</option>
                </select>
                <div class="invalid-sender-selection" id="sender-error-message" style="display:none;">
                    Please select a sender profile
                </div>
            </div>
        </div>

        <div class="mb-3 image-upload-box">
            <div class="upload-box">
                <input type="hidden" id="brand_thumbnail_url" name="brand_thumbnail_url"
                    value="<?= prefill_value($form_data, 'brand_thumbnail_url'); ?>">
                <div class="upload-instructions">
                    <label for="gift-card-image" class="upload-click-area">
                        <div class="upload-icon">
                            <img draggable="false" role="img" class="emoji" alt="📁"
                                src="https://s.w.org/images/core/emoji/15.0.3/svg/1f4c1.svg">
                        </div>
                    </label>
                    <p>
                    <div>Upload Gift Card Image or</div>
                    <a href="#" class="upload-link"
                        onclick="event.preventDefault(); document.querySelector('.url-input-container').style.display = 'block';">
                        Create new design
                    </a>
                    </p>
                </div>
                <input type="file" id="gift-card-image" name="gift_card_image" accept="image/*" style="display: none;">
            </div>

            <!-- 📸 Image Preview container -->
            <div class="selected-design-card-preview">
                <?php if (!empty($form_data['gift_card_image'])): ?>
                    <img src="<?= esc_url($form_data['gift_card_image'][0]); ?>" alt="Gift Card Preview"
                        style="max-width:200px;">
                <?php endif; ?>
            </div>
        </div>
    </form>
    <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="apply-personalisation" name="apply_personalisation"
            <?= prefill_checked($form_data, 'apply_personalisation'); ?>>
        <label class="form-check-label" for="apply-personalisation">Apply personalisation to all items on this
            order</label>
    </div>
    <div class="d-flex action-buttons justify-content-between mt-4">
        <button type="button" class="btn btn-black-white btn-outline-secondary btn-save" data-action="save-draft"
            data-order-id="<?= $order_id; ?>" data-status="create"  data-step="0" id="save-draft-bulk-card-activation">Save Draft</button>
        <button type="button" class="btn btn-black-white btn-dark btn-next" id="next-step">Next</button>
    </div>
    <div id="save-draft-message-bulk" class="message-box" style="display: none; justify-self: right;"></div>
</div>





<!-- Link to open the popup -->


<!-- Popup Structure -->
<!-- Popup Structure -->
<div id="sku-popup" class="sku-popup">
    <div class="custom-popup">
        <div class="popup-content custom-main-modal">
            <div class="custom-modal-header">
                <h5>Card ID</h5>
                <span class="close-popup close-modal"><i class="fa-solid fa-xmark"></i></span>
            </div>
            <div class="custom-modal-header-body">
                <div class="top-filter-block">
                    <div class="search-bar search-container" style="position: relative;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="product-name-search" placeholder="Search Gift Card">
                        <span class="clear-search"
                            style="position: absolute; right: 16px; font-size: 30px; top: 50%; transform: translateY(-50%); cursor: pointer; display: none;">&times;</span>
                    </div>
                </div>
                <div class="product-details">
                    <h5 id="product-title"></h5>
                    <img id="product-image" src="" alt="Product Image" style="display: none;">
                    <p id="card-id"></p>
                    <p id="card-value-options" style="display: none;">
                        <strong>Card Value Options</strong><br>
                        <span class=small-text>Click on values to copy unique ID:</span><br>
                        <span id="related-skus"></span>
                    </p>
                    <div id="action-buttons" style="display: none;">
                        <button id="copy-id" class="link-text">Copy ID</button>
                    </div>
                    <p id="product-not-found" style="display: none; color: red;">Product not found</p>
                </div>
            </div>
            <div class="popup-footer center">
                <button id="download-sku-list1" class="btn btn-primary">Download All SKU List</button>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {


        // Define ajaxurl if not already defined
        if (typeof ajaxurl === 'undefined') {
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        }

        // Open popup and initialize state
        $('#view-card-sku').click(function (e) {
            e.preventDefault();
            $('#sku-popup').show();
            // Initialize with empty state
            clearProductDisplay();
            $('#download-sku-list1').text('Download All SKU List');
        });

        // Close popup
        $('.close-popup').click(function () {
            $('#sku-popup').hide();
        });

        // Show/hide clear button based on input
        $('#product-name-search').on('input', function () {
            if ($(this).val().length > 0) {
                $('.clear-search').show();
            } else {
                $('.clear-search').hide();
            }
        });

        // Clear search input
        $('.clear-search').click(function () {
            $('#product-name-search').val('').trigger('input');
            clearProductDisplay();
            $('#download-sku-list1').text('Download All SKU List');
        });

        // Search functionality by product name
        // Debounce helper
        function debounce(func, wait) {
            let timeout;
            return function () {
                const context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }

        // Product search handler
        $('#product-name-search').on('input', debounce(function () {
            const productName = $(this).val();

            if (productName) {
                $('#download-sku-list1').text('Download SKU List');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'search_product_by_name',
                        product_name: productName
                    },
                    success: function (response) {
                        if (response.success) {
                            const products = response.data;

                            $('#product-image').attr('src', products.image).show();
                            $('#product-title').text(products.name);

                            if (products.is_parent) {
                                $('#card-id').text('');
                                $('#card-value-options').show();
                                $('#related-skus').html(
                                    products.children.map(child => `<span class="sku-value">${child.sku}</span>`).join('<br>')
                                );
                                $('#copy-id').hide();
                            } else {
                                $('#card-id').text('Card ID: ' + products.sku);
                                $('#card-value-options').hide();
                                $('#copy-id').show();
                            }

                            $('#action-buttons').show();
                            $('#product-not-found').hide();
                        } else {
                            clearProductDisplay();
                            $('#product-not-found').show();
                        }
                    }
                });
            } else {
                clearProductDisplay();
                $('#download-sku-list1').text('Download All SKU List');
            }
        }, 300));

        // SKU List Download
        $('#download-sku-list1').on('click', function (e) {
            e.preventDefault();
            const productName = $('#product-name-search').val();
            let url = ajaxurl + '?action=download_sku_list';
            if (productName) {
                url += '&product_name=' + encodeURIComponent(productName);
            }

            const iframe = $('<iframe>', {
                src: url,
                style: 'display: none;'
            }).appendTo('body');

            setTimeout(() => iframe.remove(), 5000);
        });

        // Optional fallback (remove if unused)
        jQuery(document).on("click", "#download-sku-list", function (e) {
            e.preventDefault();
            const iframe = $("<iframe>", {
                src: ajaxurl + '?action=download_sku_list',
                style: "display: none;"
            }).appendTo("body");
        });

        // Clear product display function
        function clearProductDisplay() {
            $('#product-image').hide().attr('src', '');
            $('#product-title').text('');
            $('#card-id').text('');
            $('#card-value-options').hide();
            $('#related-skus').empty();
            $('#copy-id').hide();
            $('#action-buttons').hide();
        }

        // Copy ID functionality
        $('#copy-id').click(function () {
            var cardId = $('#card-id').text().replace('Card ID: ', '');
            navigator.clipboard.writeText(cardId).then(function () {
                var $copyButton = $('#copy-id');
                $copyButton.text('Copied');
                setTimeout(function () {
                    $copyButton.text('Copy ID');
                }, 2000);
            });
        });

        // Copy SKU functionality
        $(document).on('click', '.sku-value', function () {
            var sku = $(this).text();
            navigator.clipboard.writeText(sku).then(function () {
                var originalText = $(this).text();
                $(this).text('Copied!');
                setTimeout(() => {
                    $(this).text(originalText);
                }, 2000);
            }.bind(this));
        });

        // Download SKU List
        /*$('#download-sku-list1').on('click', function (e) {
            e.preventDefault();
            var productName = $('#product-name-search').val();
            var url = ajaxurl + '?action=download_sku_list';

            if (productName) {
                url += '&product_name=' + encodeURIComponent(productName);
            }

            var iframe = $('<iframe>', {
                src: url,
                style: 'display: none;'
            }).appendTo('body');

            setTimeout(function () {
                iframe.remove();
            }, 5000);
        });*/
    });
</script>

<?php get_footer(); ?>
<!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
<script>
    jQuery(document).ready(function ($) {
        let currentPage = 1;
        let totalPages = 1;
        let searchText = ''; // Store search input value

        function loadGiftCards(page, searchQuery = '') {
            $.ajax({
                type: 'POST',
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                data: {
                    action: 'gift_card_pagination',
                    security: '<?php echo wp_create_nonce("gc_nonce"); ?>',
                    page: page,
                    search: searchQuery // Pass search input to backend
                },
                beforeSend: function () {
                    $("#gift-card-container").html('<p>Loading...</p>');
                },
                success: function (response) {
                    let data = JSON.parse(response);
                    $("#gift-card-container").html(data.html);
                    $(".current-page").text(page);
                    currentPage = page;
                    totalPages = data.total_pages;

                    renderPaginations(currentPage, totalPages);


                    // Disable Prev button if on first page
                    $(".prev-page").toggleClass("disabled", currentPage === 1);
                    // Disable Next button if on last page
                    $(".next-page").toggleClass("disabled", currentPage >= totalPages);
                }
            });
        }
        // Click on page number
        $(document).on("click", ".page-num", function (e) {
            e.preventDefault();
            const page = parseInt($(this).text());
            if (!isNaN(page)) {
                loadGiftCards(page, searchText);
            }
        });

        // Click on Prev
        $(document).on("click", ".prev-page", function (e) {
            e.preventDefault();
            if (currentPage > 1) {
                loadGiftCards(currentPage - 1, searchText);
            }
        });

        // Click on Next
        $(document).on("click", ".next-page", function (e) {
            e.preventDefault();
            if (currentPage < totalPages) {
                loadGiftCards(currentPage + 1, searchText);
            }
        });

        function renderPaginations(current, total) {
            const container = $("#gift-card-pagination");
            container.empty();
            if (total <= 1) return;

            let html = '';

            // Prev button
            html += `<a href="#" class="page-nav prev-page ${current === 1 ? 'disabled' : ''}">&#8249;</a>`;

            if (total <= 3) {
                // Case: only 3 or fewer pages
                for (let i = 1; i <= total; i++) {
                    if (i === 2 && current > 1) {
                        html += `<span class="dots">...</span>`;
                    }
                    html += `<a href="#" class="page-num ${i === current ? 'active' : ''}">${i}</a>`;
                }
            } else {
                // Case: more than 3 pages (standard behavior)
                const maxPagesToShow = 5;
                let start = Math.max(1, current - 2);
                let end = Math.min(total, current + 2);

                if (current <= 3) {
                    start = 1;
                    end = Math.min(total, maxPagesToShow);
                } else if (current >= total - 2) {
                    end = total;
                    start = Math.max(1, total - (maxPagesToShow - 1));
                }

                if (start > 1) {
                    html += `<a href="#" class="page-num">1</a>`;
                    if (start > 2) html += `<span class="dots">...</span>`;
                }

                for (let i = start; i <= end; i++) {
                    html += `<a href="#" class="page-num ${i === current ? 'active' : ''}">${i}</a>`;
                }

                if (end < total) {
                    if (end < total - 1) html += `<span class="dots">...</span>`;
                    html += `<a href="#" class="page-num">${total}</a>`;
                }
            }

            // Next button
            html += `<a href="#" class="page-nav next-page ${current === total ? 'disabled' : ''}">&#8250;</a>`;

            container.html(html);
        }



        // Initial load
        loadGiftCards(currentPage);

        // Pagination: Next button
        // $(".next-page").click(function (e) {
        //     e.preventDefault();
        //     if (currentPage < totalPages) {
        //         loadGiftCards(currentPage + 1, searchText);
        //     }
        // });

        // // Pagination: Previous button
        // $(".prev-page").click(function (e) {
        //     e.preventDefault();
        //     if (currentPage > 1) {
        //         loadGiftCards(currentPage - 1, searchText);
        //     }
        // });

        // Search Functionality (Triggers new AJAX request)
        $("#gift-card-search-pro").on("keyup", function () {
            searchText = $(this).val().trim();
            loadGiftCards(1, searchText); // Reset to page 1 with search
        });
    });
</script>

<script>
    jQuery(document).ready(function ($) {
        // Define ajaxurl if not already defined


        // let businessUserData = <?php //echo !empty($business_user_data) ? wp_json_encode($business_user_data) : '{}'; 
        ?>;

        // $('#business-user-dropdown').on('change', function () {
        //     let userId = $(this).val();
        //     let senderDropdown = $('#sender-dropdown');
        //     senderDropdown.empty().append('<option selected disabled>Loading...</option>');

        //     setTimeout(() => {
        //         senderDropdown.empty().append('<option selected disabled>Select sender</option>');
        //         if (businessUserData[userId]) {
        //             businessUserData[userId].forEach(sender => {
        //                 senderDropdown.append(`<option value="${sender.name}">${sender.name}</option>`);
        //             });
        //         }
        //     }, 300);
        // });


        // Function to validate required fields before allowing Bulk Order Upload
        function validateForm() {
            let isValid = true;

            // Check if Business User is selected (updated for single select)
            const businessUserVal = $('#business-user-dropdown').val();
            if (!businessUserVal || businessUserVal === '') {
                console.log('Im inside the');
                $('#business-user-dropdown').addClass('is-invalid-field');
                // Also add invalid class to Select2 container
                $('#business-user-dropdown').next('.select2-container').find('.select2-selection').addClass('is-invalid-field');
                $('#business-user-dropdown').closest('.top-dropdown-block').find('.invalid-feedback').show();
                isValid = false;
            } else {
                // console.log('Im inside the else');

                $('#business-user-dropdown').removeClass('is-invalid-field');
                $('#business-user-dropdown').next('.select2-container').find('.select2-selection').removeClass('is-invalid-field');
                $('#business-user-dropdown').closest('.top-dropdown-block').find('.invalid-feedback').hide();
            }

            // Check if Sender Details are selected
            if ($('#sender-dropdown').val() === null || $('#sender-dropdown').val() === '') {
                console.log('Im inside the else 2');

                $('#sender-dropdown').addClass('is-invalid-field');
                $('.invalid-sender-selection .error-message').show(); // Show the error message
                isValid = false;
            } else {
                console.log('Im inside the else 3');

                $('#sender-dropdown').removeClass('is-invalid-field');
                // $('#sender-error-message').hide(); // Hide the error message
                $('.invalid-sender-selection .error-message').hide(); // Show the error message
            }

            // Check if Order Name is filled
            const orderNameVal = $('#order-name').val().trim();
            if (orderNameVal === '') {
                $('#order-name').addClass('is-invalid-field');
                isValid = false;
            } else {
                $('#order-name').removeClass('is-invalid-field');
            }

            // Check if Client Reference is filled
            // const clientRefVal = $('#client-reference').val().trim();
            // if (clientRefVal === '') {
            //     $('#client-reference').addClass('is-invalid-field');
            //     isValid = false;
            // } else {
            //     $('#client-reference').removeClass('is-invalid-field');
            // }

            return isValid;
        }


        $('#show-multistep-form').click(function () {
            // if (!validateForm()) {
            //     alert("Please fill all required fields before proceeding.");
            //     return;
            // }
            $('#display-order-name').text($('#order-name').val());
            $('#display-order-id').text($('#order-id').val());
            $('#display-client-reference').text($('#client-reference').val());
            $('#display-sender').text($('#sender-dropdown').val());

            $('#new-order-form').hide();
            $('#multi-step-form-bulk').removeClass('d-none');

        });

        $('#back-to-new-order-form').click(function () {
            $('#multi-step-form-bulk').addClass('d-none');
            $('#new-order-form').show();
        });

        jQuery(document).on("click", "#download-bulk-template", function (e) {
            // console.log('Clicked me');
            e.preventDefault();
            const csvUrl = "<?php echo esc_url(content_url('uploads/2025/09/Bulk_order_template_and_instructions._.xlsx')); ?>";
            const downloadLink = $("<a>")
                .attr("href", csvUrl)
                .attr("download", csvUrl.split('/').pop())
                .appendTo("body");

            downloadLink[0].click();
            downloadLink.remove();
        });

        var originalCsvData = {};
        var templateHeaders = [];
        var headerMapping = [];
        var rowsPerPage = 20;
        var currentPage = 1;
        var editedData = {};
        var editMode = false;
        var currentFilter = 'all';
        // Show modal
        $('#upload-file-btn').on('click', function () {
            $('#file-upload-modal').modal('show');
        });

        // Trigger file input when clicking on the upload area
        $('#bulk-upload-area').on('click', function () {
            $('#csv-file-input1').click();
            console.log('Clicked...');
        });

        // When a file is selected
        $('#csv-file-input1').on('change', function (e) {
            const file = e.target.files[0];
            if (file && file.name.endsWith('.csv')) {
                $('#selected-file-name').text(file.name);
                $('#file-name-display').show();
                $('#file-error-msg').text('');
            } else {
                $('#file-error-msg').text('⚠️ Please select a valid CSV file.');
                $('#csv-file-input1').val('');
                $('#file-name-display').hide();
            }
        });

        // Handle drag & drop
        $('#bulk-upload-area').on('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragging');
        });

        $('#bulk-upload-area').on('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragging');
        });

        $('#bulk-upload-area').on('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragging');

            const file = e.originalEvent.dataTransfer.files[0];
            if (file && file.name.endsWith('.csv')) {
                $('#csv-file-input1')[0].files = e.originalEvent.dataTransfer.files;
                $('#selected-file-name').text(file.name);
                $('#file-name-display').show();
                $('#file-error-msg').text('');
            } else {
                $('#file-error-msg').text('⚠️ Only CSV files are allowed.');
            }
        });

        // Remove selected file
        $('#remove-selected-file').on('click', function () {
            $('#csv-file-input1').val('');
            $('#file-name-display').hide();
        });

        // Submit file
        $('#close-submit-file-upload').on('click', function () {
            const file = $('#csv-file-input1')[0].files[0];
            if (!file) {
                $('#file-error-msg').text('⚠️ Please select a CSV file.');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'upload_csv_file_bulk');

            $('#upload-progress').css({ display: 'block' });
            $('#progress-bar').css('width', '0%').text('0%');

            $.ajax({
                url: ajaxData.ajax_url, // Use JS variable, not PHP echo here
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function (evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            $('#progress-bar').css('width', percentComplete + '%').text(percentComplete.toFixed(2) + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function (response) {
                    $('#upload-progress').hide();

                    if (response.success) {
                        $('#file-upload-modal').modal('hide');
                        originalCsvData = response.data.csv_data;
                        templateHeaders = response.data.template_headers;
                        headerMapping = response.data.header_mapping;
                        isCorrectedView = false;

                        if (response.data.all_matched) {
                            console.log('MAPPing');
                            applyMappingAndPreview(headerMapping);
                        } else {
                            showMappingInterface(headerMapping, templateHeaders, originalCsvData.headers);
                        }
                    } else {
                        $('#file-error-msg').text(response.data.message || '⚠️ Upload failed.');
                    }
                },
                error: function () {
                    $('#file-error-msg').text('⚠️ An error occurred during upload.');
                    $('#upload-progress').hide();
                }
            });
        });
        function showMappingInterface(headerMapping, templateHeaders, uploadedHeaders) {
            let mappingHtml = '';
            const mandatoryHeaders = [
                'Recipient First Name', 'Delivery Method', 'Recipient Email Address',
                'Recipient Phone Number', 'Product Code', 'Gift Card Name', 'Gift Card Value',
                'Quantity', 'Personalisation'
            ];

            // Check for empty headers
            const emptyHeaders = uploadedHeaders
                .map((header, index) => ({ header, index }))
                .filter(h => !h.header || h.header.trim() === '');

            if (emptyHeaders.length > 0) {
                const emptyColumns = emptyHeaders.map(h => `Column ${h.index + 1}`).join(', ');
                $('#mapping-interface').html(`
                    <div id="mandatory-warning" class="text-danger mb-3">
                        ⚠️ The following columns have no headers: ${emptyColumns}.<br>
                        Please update your CSV and ensure all columns have header names identical to the template file.
                    </div>
                `);
                $('#mapping-modal').modal('show');
                $('#apply-mapping').hide();

                return; // Stop further execution
            }
            $('#apply-mapping').show();
            $("#mandatory-warning").remove();

            // Generate the mapping interface
            templateHeaders.forEach((templateHeader) => {
                const preSelected = headerMapping[templateHeader] || '';
                const isMandatory = mandatoryHeaders.includes(templateHeader);
                const mandatoryClass = isMandatory && !preSelected ? 'text-danger' : '';

                mappingHtml += `
                    <div class="form-group">
                        <label class="${mandatoryClass}">${templateHeader}${isMandatory ? ' (mandatory)' : ''}</label>
                        <select class="form-control mapping-select" data-template="${templateHeader}">
                            <option value="">Select Header</option>
                                ${uploadedHeaders.map(header => {
                    const normalized = header.trim().toLowerCase();
                    const isDisabled = normalized === 'no' ? 'disabled' : '';
                    const isSelected = header === preSelected ? 'selected' : '';

                    console.log(`Rendering header: "${header}", Disabled: ${isDisabled !== ''}, Selected: ${isSelected !== ''}`);

                    return `<option value="${header}" ${isDisabled} ${isSelected} style="${isDisabled ? 'color: gray;' : ''}">${header}</option>`;
                }).join('')}
                        </select>
                    </div>
                `;
            });

            $('#mapping-interface').html(`<div id="mandatory-warning" class="text-danger mb-3"></div>` + mappingHtml);
            $('#mapping-modal').modal('show');

            // Dropdown logic
            function updateDropdownOptions() {
                $('.mapping-select').each(function () {
                    const currentSelect = $(this);
                    const currentSelectedHeader = currentSelect.val();

                    // currentSelect.find('option').prop('disabled', false);
                    currentSelect.find('option').each(function () {
                        const optionValue = $(this).val().trim().toLowerCase();
                        if (optionValue !== 'no') {
                            $(this).prop('disabled', false);
                        }
                    });
                    $('.mapping-select').not(currentSelect).each(function () {
                        const selectedHeader = $(this).val();
                        if (selectedHeader) {
                            currentSelect.find(`option[value="${selectedHeader}"]`).prop('disabled', true);
                        }
                    });
                });
            }

            $('.mapping-select').on('change', function () {
                updateDropdownOptions();
            });

            updateDropdownOptions();
        }


        $('#close-apply-mapping').on('click', function () {
            const updatedHeaders = [];
            const mandatoryHeaders = [
                'Recipient First Name', 'Delivery Method', 'Recipient Email Address',
                'Recipient Phone Number', 'Product Code', 'Gift Card Name', 'Gift Card Value',
                'Quantity', 'Personalisation'
            ];

            let missingMandatoryFields = [];
            let selectedValues = {}; // Track selected values to prevent duplicates

            $('.mapping-select').each(function () {
                const selectedHeader = $(this).val();
                const templateHeader = $(this).data('template');

                // Check for mandatory fields
                if (mandatoryHeaders.includes(templateHeader) && !selectedHeader) {
                    missingMandatoryFields.push(templateHeader);
                }

                // Check for duplicate selections
                if (selectedHeader && selectedValues[selectedHeader]) {
                    // If the value is already selected, clear the previous selection
                    $(`.mapping-select[data-template="${selectedValues[selectedHeader]}"]`).val('');
                    selectedValues[selectedHeader] = templateHeader; // Update the selected value
                } else if (selectedHeader) {
                    selectedValues[selectedHeader] = templateHeader; // Track the selected value
                }

                updatedHeaders.push({
                    template: templateHeader,
                    selected: selectedHeader
                });
            });

            // Show error if mandatory fields are missing
            if (missingMandatoryFields.length > 0) {
                $('#mandatory-warning').text(`⚠️ Mandatory fields missing: ${missingMandatoryFields.join(', ')}`);
                return;
            }

            // Apply the mapping and show the preview
            applyMappingAndPreview(Object.fromEntries(updatedHeaders.map(h => [h.template, h.selected])));
            $('#mapping-modal').modal('hide');
        });

        function validateScheduledDeliveryDates(csvData) {
            const errors = [];
            const columnIndex = csvData.headers.indexOf('Scheduled Delivery Date/Time');
            if (columnIndex === -1) return errors;

            // Parse server time and strip seconds/milliseconds
            const serverTimeString = user_fetch_ajax?.server_time || '';
            const serverDateRaw = new Date(serverTimeString.replace(' ', 'T'));
            const serverTime = new Date(
                serverDateRaw.getFullYear(),
                serverDateRaw.getMonth(),
                serverDateRaw.getDate(),
                serverDateRaw.getHours(),
                serverDateRaw.getMinutes()
            );

            // Minimum allowed = serverTime + 24 hours (to the minute)
            const minAllowedDate = new Date(serverTime.getTime() + 24 * 60 * 60 * 1000);

            csvData.data.forEach((row, rowIndex) => {
                let rawValue = row[columnIndex]?.trim();


                if (rawValue === '00-00-0000 00:00' || rawValue === '00/00/0000 00:00') {
                    return; // No error, no conversion
                }

                // Check if field is empty
                if (!rawValue) {
                    errors.push({
                        rowIndex,
                        colIndex: columnIndex,
                        error: 'Scheduled date/time is required'
                    });
                    return;
                }


                // Format: DD/MM/YYYY or DD-MM-YYYY + HH:mm
                const match = rawValue.match(/^(\d{2})[-\/](\d{2})[-\/](\d{4}) (\d{2}):(\d{2})$/);
                if (!match) {
                    errors.push({
                        rowIndex,
                        colIndex: columnIndex,
                        error: 'Invalid format (use DD/MM/YYYY HH:mm)'
                    });
                    return;
                }

                const [_, dd, mm, yyyy, hh, min] = match;
                const date = new Date(parseInt(yyyy), parseInt(mm) - 1, parseInt(dd), parseInt(hh), parseInt(min));

                // Normalize parsed date to minute-level precision
                const normalizedDate = new Date(date.getFullYear(), date.getMonth(), date.getDate(), date.getHours(), date.getMinutes());

                if (
                    isNaN(normalizedDate.getTime()) ||
                    normalizedDate.getFullYear() !== parseInt(yyyy) ||
                    normalizedDate.getMonth() !== parseInt(mm) - 1 ||
                    normalizedDate.getDate() !== parseInt(dd) ||
                    normalizedDate.getHours() !== parseInt(hh) ||
                    normalizedDate.getMinutes() !== parseInt(min)
                ) {
                    errors.push({
                        rowIndex,
                        colIndex: columnIndex,
                        error: 'Invalid date/time value'
                    });
                } else if (normalizedDate < minAllowedDate) {
                    errors.push({
                        rowIndex,
                        colIndex: columnIndex,
                        error: 'Date/time must be at least 24 hours from server time'
                    });
                }
            });

            return errors;
        }


        let recipientsData = {}; // Store recipient details fetched from the server

        // Function to fetch recipient details from the server
        function fetchRecipientDetailsByEmails(recipientIds, recipientEmails, csvData) {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'get_recipient_details_by_emails',
                    recipient_ids: recipientIds,
                    recipient_emails: recipientEmails
                },
                success: function (response) {
                    if (response.success && response.data) {
                        fetchedRecipientDetails = response.data.data;
                        console.log(fetchedRecipientDetails);
                        previewCSVData(csvData, 1);
                    } else {
                        console.warn("Recipient details fetch failed:", response);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("AJAX error fetching recipients:", error);
                }
            });
        }

        function fetchRecipientDetails_only(recipientIds, recipientEmails, csvData) {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'get_recipient_details_by_emails',
                    only: 'only',
                    recipient_ids: recipientIds,
                    recipient_emails: recipientEmails
                }
            });
        }


        // function validatePersonalization(csvData) {
        //     const invalidCells = [];
        //     const personalizationIndex = csvData.headers.indexOf('Personalisation');
        //     const deliveryMethodIndex = csvData.headers.indexOf('Delivery Method');
        //     const subjectLineIndex = csvData.headers.indexOf('Subject Line');
        //     const messageIndex = csvData.headers.indexOf('Message');

        //     if (personalizationIndex === -1 || deliveryMethodIndex === -1 || subjectLineIndex === -1 || messageIndex === -1) {
        //         return invalidCells; // Skip if any of the required columns are missing
        //     }

        //     csvData.data.forEach((row, rowIndex) => {
        //         const personalization = row[personalizationIndex];
        //         const deliveryMethod = row[deliveryMethodIndex];
        //         const subjectLine = row[subjectLineIndex];
        //         const message = row[messageIndex];

        //         if (personalization === 'Y' && deliveryMethod === 'Email') {
        //             if (!subjectLine || subjectLine.trim() === '') {
        //                 invalidCells.push({
        //                     rowIndex,
        //                     colIndex: subjectLineIndex
        //                 });
        //             }
        //             if (!message || message.trim() === '') {
        //                 invalidCells.push({
        //                     rowIndex,
        //                     colIndex: messageIndex
        //                 });
        //             }
        //         }
        //     });

        //     return invalidCells;
        // }

        function validatePersonalization(csvData) {
            const invalidCells = [];
            const colIndex = csvData.headers.indexOf('Personalisation');

            if (colIndex === -1) return [];

            csvData.data.forEach((row, rowIndex) => {
                const value = (row[colIndex] || '').trim().toLowerCase();

                if (value !== 'yes' && value !== 'no') {
                    invalidCells.push({
                        rowIndex,
                        colIndex,
                        error: 'Value must be Yes or No'
                    });
                }
            });

            return invalidCells;
        }

        // Function to validate recipient details against CSV data
        function validateRecipientDetails(csvData) {
            const invalidCells = [];

            if (typeof fetchedRecipientDetails !== 'object' || !fetchedRecipientDetails) {
                console.warn("No fetchedRecipientDetails available");
                return invalidCells;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const phoneRegex = /^(?:\+61\s?4\d{2}\s?\d{3}\s?\d{3}|04\d{2}\s?\d{3}\s?\d{3})$/;
            //console.log('phoneRegex111');
            //console.log(phoneRegex);
            const allowedDeliveryMethods = [
                'phone',
                'email',
                'email,phone',
                'email & phone',
                'download',
                'trigger client send'
            ];

            const idIndex = csvData.headers.indexOf('Recipient ID');
            const emailIndex = csvData.headers.indexOf('Recipient Email Address');
            const phoneIndex = csvData.headers.indexOf('Recipient Phone Number');
            const firstNameIndex = csvData.headers.indexOf('Recipient First Name');
            const lastNameIndex = csvData.headers.indexOf('Recipient Surname');
            const deliveryMethodIndex = csvData.headers.indexOf('Delivery Method');

            console.log('validateRecipientDetails: ', csvData);

            if (deliveryMethodIndex === -1 || emailIndex === -1) return invalidCells;

            console.log('validateRecipientDetails: ', csvData.data.length);
            // ✅ Use regular for-loop so we can use "continue"
            for (let rowIndex = 0; rowIndex < csvData.data.length; rowIndex++) {
                const row = csvData.data[rowIndex];
                const deliveryMethod = row[deliveryMethodIndex]?.trim();
                const lowerDeliveryMethod = deliveryMethod?.toLowerCase();
                const recipientEmail = row[emailIndex]?.trim()?.toLowerCase();
                const recipientID = parseInt(row[idIndex]?.trim());
                let recipientPhone = row[phoneIndex]?.trim()?.replace(/\s+/g, '');

                //console.log('recipientPhone::: ',recipientPhone);
                if (recipientPhone && (/^614\d{8}$/.test(recipientPhone) || /^61\d+$/.test(recipientPhone))) {
                    // Starts with 61 and digits but not properly formatted — e.g. missing space
                    // Still treat it as needing + prefix
                    recipientPhone = '+' + recipientPhone;
                    //console.log('IN recipientPhone::: ',recipientPhone);
                }
                const recipient = fetchedRecipientDetails[rowIndex];
                //const recipient = fetchRecipientDetails_only([recipientID], [recipientEmail], csvData);

                const checkEmail = lowerDeliveryMethod?.includes('email');
                const checkPhone = lowerDeliveryMethod?.includes('phone');

                // 🚫 Text-only validation for Delivery Method
                if (
                    deliveryMethod &&
                    !allowedDeliveryMethods.includes(deliveryMethod.toLowerCase())
                ) {
                    invalidCells.push({
                        rowIndex,
                        colIndex: deliveryMethodIndex,
                        error: 'Please enter a valid Delivery Method'
                    });
                    continue;
                }


                // === Email validation
                if (checkEmail && emailIndex !== -1) {
                    if (!recipientEmail || !emailRegex.test(recipientEmail)) {
                        invalidCells.push({
                            rowIndex,
                            colIndex: emailIndex,
                            error: !recipientEmail ? 'Email is required' : 'Invalid email format'
                        });
                    }
                }

                // === Phone validation
                if (checkPhone && phoneIndex !== -1) {
                    if (!recipientPhone) {
                        invalidCells.push({
                            rowIndex,
                            colIndex: phoneIndex,
                            error: 'Phone number is required'
                        });
                    } else if (!phoneRegex.test(recipientPhone)) {
                        invalidCells.push({
                            rowIndex,
                            colIndex: phoneIndex,
                            error: 'Invalid phone format'
                        });
                    } else if (recipient) {
                        const cleanRecipientPhone = recipient.phone?.replace(/\D/g, '') || '';
                        const cleanCsvPhone = recipientPhone.replace(/\D/g, '');

                        if (cleanCsvPhone !== cleanRecipientPhone) {
                            invalidCells.push({
                                rowIndex,
                                colIndex: phoneIndex,
                                error: 'Phone does not match recipient'
                            });
                        }
                    }
                }

                console.log('RIDDD: ', recipientID);
                console.log('REMIAL: ', recipientEmail);
                console.log('RrowIndex: ', rowIndex);
                console.log('RRecipient: ', recipient);
                console.log('RRfetchedRecipientDetails: ', recipient);

                fetchRecipientDetails_only([recipientID], [recipientEmail], csvData)
                    .then(function (response) {
                        // If your PHP uses `wp_send_json_success(['data' => $recipient_details_map])`
                        // Then access it like:
                        const recipient = response.data?.data?.[0] || null;

                        // If your PHP directly returns array with `return $recipient_details_map;`
                        // Then just:
                        // const recipient = response[0];

                        console.log("Recipient data:", recipient);
                        console.log("RRRresponse data:", response);
                        if (recipient) {
                            if (firstNameIndex !== -1) {
                                const csvFirstName = row[firstNameIndex]?.trim()?.toLowerCase();
                                const recipientFirstName = recipient.first_name?.trim()?.toLowerCase();
                                if (csvFirstName !== recipientFirstName) {
                                    invalidCells.push({
                                        rowIndex,
                                        colIndex: firstNameIndex,
                                        error: 'First name does not match recipient'
                                    });
                                }
                            }

                            if (lastNameIndex !== -1) {
                                const csvLastName = row[lastNameIndex]?.trim()?.toLowerCase();
                                const recipientLastName = recipient.last_name?.trim()?.toLowerCase();


                                console.log("csvLastName:", csvLastName);
                                console.log("RRRresponse recipientLastName:", recipientLastName);
                                if (csvLastName !== recipientLastName) {
                                    invalidCells.push({
                                        rowIndex,
                                        colIndex: lastNameIndex,
                                        error: 'Surname does not match recipient'
                                    });
                                }
                            }

                            console.log('ROW: ', row);

                            if (idIndex !== -1) {
                                const csvrecipientID = row[idIndex];

                                console.log('============++++========');
                                console.log(recipient.user_by_id);
                                console.log(csvrecipientID);


                                if ( parseInt(recipient.csv_user_id) > 0 && parseInt(recipient.user_by_id) !== parseInt(recipient.user_id)) {
                                    invalidCells.push({
                                        rowIndex,
                                        colIndex: idIndex,
                                        error: 'Recipient ID is not matched with Email.'
                                    });
                                }else if ( parseInt(recipient.user_by_id) <= 0 && parseInt(recipient.user_id) <= 0) {

                                } else if ( parseInt(recipient.user_by_id) <= 0 && parseInt(recipient.user_id) > 0) {

                                } else if (parseInt(recipient.user_by_id) !== parseInt(recipient.user_id)) {
                                    invalidCells.push({
                                        rowIndex,
                                        colIndex: idIndex,
                                        error: 'Recipient ID is not matched with Email.'
                                    });
                                } else if (parseInt(recipient.user_by_id) === parseInt(recipientID)) {
                                    /*recipient.user_by_id = recipient.user_id;
                                    fetchedRecipientDetails[recipientEmail]['user_by_id'] = recipient.user_id;

                                    recipient.email_by_id = recipient.email;
                                    fetchedRecipientDetails[recipientEmail]['email_by_id'] = recipient.email;*/
                                }
                            }

                            console.log('recipient: ', recipient);
                            console.log('fetchedRecipientDetails: ', fetchedRecipientDetails);
                            console.log('RrowIndex: ', rowIndex);

                            if (emailIndex !== -1) {
                                const csvrecipientEmail = recipient.email_by_id?.trim()?.toLowerCase();
                                const recipientEmail = recipient.email?.trim()?.toLowerCase();
                                console.log('====================');
                                console.log(recipient);
                                console.log(csvrecipientEmail);
                                console.log(recipientEmail);

                                console.log('++++');
                                console.log('rowIndex: ', rowIndex);
                                console.log('colIndex: ', emailIndex);
                                if ( parseInt(recipient.user_by_id) <= 0 && parseInt(recipient.user_id) <= 0 || ( parseInt(recipient.user_by_id) <= 0 && parseInt(recipient.user_id) > 0 ) ) {
                                } else if (csvrecipientEmail != recipientEmail) {
                                    console.log('TRUE');
                                    invalidCells.push({
                                        rowIndex,
                                        colIndex: emailIndex,
                                        error: 'Email does not match with recipient ID.'
                                    });
                                }
                            }
                        }
                    })
                    .catch(function (error) {
                        console.error("Error fetching recipient data:", error);
                    });

                // === First and Last Name validation (if recipient found)
                if (recipient) {
                    /*if (firstNameIndex !== -1) {
                        const csvFirstName = row[firstNameIndex]?.trim()?.toLowerCase();
                        const recipientFirstName = recipient.first_name?.trim()?.toLowerCase();
                        if (csvFirstName !== recipientFirstName) {
                            invalidCells.push({
                                rowIndex,
                                colIndex: firstNameIndex,
                                error: 'First name does not match recipient'
                            });
                        }
                    }

                    if (lastNameIndex !== -1) {
                        const csvLastName = row[lastNameIndex]?.trim()?.toLowerCase();
                        const recipientLastName = recipient.last_name?.trim()?.toLowerCase();
                        if (csvLastName !== recipientLastName) {
                            invalidCells.push({
                                rowIndex,
                                colIndex: lastNameIndex,
                                error: 'Surname does not match recipient'
                            });
                        }
                    }

                    console.log('ROW: ',row);

                    if (idIndex !== -1) {
                        const csvrecipientID = row[idIndex];

                        // if( recipient.user_by_id <= 0 ){
                        //     invalidCells.push({
                        //         rowIndex,
                        //         colIndex: idIndex,
                        //         error: 'Recipient ID is not found.'
                        //     });
                        // }else 
                        if (parseInt(recipient.user_by_id) !== parseInt(recipientID)) {
                            invalidCells.push({
                                rowIndex,
                                colIndex: idIndex,
                                error: 'Recipient ID is not matched with Email.'
                            });
                        }else if( parseInt(recipient.user_by_id) === parseInt(recipientID) ){
                            // recipient.user_by_id = recipient.user_id;
                            // fetchedRecipientDetails[recipientEmail]['user_by_id'] = recipient.user_id;

                            // recipient.email_by_id = recipient.email;
                            // fetchedRecipientDetails[recipientEmail]['email_by_id'] = recipient.email;
                        }
                    }

                    console.log('recipient: ',recipient);
                    console.log('fetchedRecipientDetails: ',fetchedRecipientDetails);
                    console.log('RrowIndex: ',rowIndex);

                    if (emailIndex !== -1) {
                        //const csvrecipientEmail = row[emailIndex]?.trim()?.toLowerCase();
                        const csvrecipientEmail = recipient.email?.trim()?.toLowerCase();
                        console.log('====================');
                        console.log(recipient.email);
                        console.log(recipientEmail);
                        
                        console.log('++++');
                        console.log('rowIndex: ',rowIndex);
                        console.log('colIndex: ',emailIndex);
                        if (csvrecipientEmail != recipientEmail) {
                            console.log('TRUE');
                            invalidCells.push({
                                rowIndex,
                                colIndex: emailIndex,
                                error: 'Email does not match with recipient ID.'
                            });
                        }
                    }*/
                }
            }

            // console.log('INVALID: ', invalidCells);

            return invalidCells;
        }


        function extractRecipientIds(csvData) {
            const ids = [];
            const idIndex = csvData.headers.indexOf('Recipient ID');
            if (idIndex === -1) return ids;

            csvData.data.forEach(row => {
                const id = row[idIndex]?.trim();
                if (id) {
                    ids.push(id);
                }
            });
            console.log('ids', ids);
            return ids;
        }

        // Function to extract recipient IDs from CSV data
        function extractRecipientEmails(csvData) {
            const emails = [];
            const emailIndex = csvData.headers.indexOf('Recipient Email Address');
            if (emailIndex === -1) return emails;

            csvData.data.forEach(row => {
                const email = row[emailIndex]?.trim();
                if (email) {
                    emails.push(email);
                }
            });
            console.log('emails', emails);
            return emails;
        }


        let mapping = {};

        function applyMappingAndPreview(mapping) {
            console.log('originalCsvData: ', originalCsvData);
            const updatedHeaders = templateHeaders;

            // Filter out non-mandatory fields that are not selected
            const filteredHeaders = updatedHeaders.filter(header => mapping[header] !== '');

            // Map the CSV data to the updated headers
            const updatedData = originalCsvData.data.map(row => {
                return filteredHeaders.map(templateHeader => {
                    const selectedHeader = mapping[templateHeader];
                    const indexInUploaded = originalCsvData.headers.indexOf(selectedHeader);
                    return indexInUploaded !== -1 ? row[indexInUploaded] : '';
                });
            });

            // Update the original CSV data with the filtered headers and mapped data
            originalCsvData.headers = filteredHeaders;
            originalCsvData.data = updatedData;

            // Extract recipient IDs from the CSV data
            const recipientIds = extractRecipientIds(originalCsvData);
            const recipientEmails = extractRecipientEmails(originalCsvData);

            // Fetch recipient details from the server
            fetchRecipientDetailsByEmails(recipientIds, recipientEmails, originalCsvData).then(response => {
                if (response.success) {
                    recipientsData = response.data.data;

                    // Use updated validation functions
                    const invalidRecipientCells = validateRecipientDetails(originalCsvData);
                    const invalidEmailCells = validateEmails(originalCsvData);
                    const invalidPhoneCells = validatePhoneNumbers(originalCsvData);
                    const invalidMandatoryCells = validateMandatoryFields(originalCsvData);

                    validateProductDetails(originalCsvData).then(invalidProductCells => {
                        const invalidCells = [
                            ...invalidEmailCells,
                            ...invalidPhoneCells,
                            ...invalidMandatoryCells,
                            ...invalidRecipientCells,
                            ...invalidProductCells
                        ];

                        // Set the current page to 1 and preview the CSV data with invalid cells highlighted
                        currentPage = 1;
                        previewCSVData(originalCsvData, currentPage, invalidCells, mapping);

                        // Update the UI to show the CSV preview and hide other forms
                        $('#csv-preview').removeClass('d-none').show();
                        $('#new-order-form').hide();
                        $('#multi-step-form-bulk').addClass('d-none');
                        // console.log('outside doccc');

                        // Update UI based on validation results
                        if (invalidCells.length === 0) {
                            // console.log('inside doccc');
                            // If no errors, hide error-related UI elements and update the Next button
                            currentFilter = 'all';
                            $('#filter-by').val('all').hide();
                            $('#edit-errors, #remove-error-lines, #download-resubmit').hide();
                            isCorrectedView = true;
                            $('#next-button').text('Confirm and Proceed →');
                        } else {
                            // If errors exist, show error-related UI elements
                            $('.correct-rows-count, .error-rows-count').show();
                        }
                    });
                } else {
                    // Log an error if fetching recipient details fails
                    console.error('Failed to fetch recipient details:', response.data);
                }
            }).catch(error => {
                // Log an error if the AJAX request fails
                console.error('Error fetching recipient details:', error);
            });
        }

        // Function to validate product details against SKU, product name, and product value
        function validateProductDetails(csvData) {
            const invalidCells = [];
            const productCodeIndex = csvData.headers.indexOf('Product Code');
            const giftCardNameIndex = csvData.headers.indexOf('Gift Card Name');
            const giftCardValueIndex = csvData.headers.indexOf('Gift Card Value');

            if (productCodeIndex === -1 || giftCardNameIndex === -1 || giftCardValueIndex === -1) {
                return Promise.resolve(invalidCells);
            }

            // Prepare product data for validation
            const productData = csvData.data.map((row, rowIndex) => ({
                sku: row[productCodeIndex],
                gift_card_name: row[giftCardNameIndex],
                gift_card_value: parseFloat(row[giftCardValueIndex]) || 0,
                rowIndex
            }));

            return fetchProductDetails(productData).then(response => {
                if (response.success) {
                    return response.data.errors.map(error => ({
                        rowIndex: error.rowIndex,
                        colIndex: error.field === 'sku' ? productCodeIndex : error.field === 'gift_card_name' ? giftCardNameIndex : giftCardValueIndex,
                        error: error.message,
                        field: error.field
                    }));
                }
                return [];
            });
        }

        function fetchProductDetails(productData) {
            return $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'validate_product_details_bulk',
                    product_data: productData
                },
                dataType: 'json'
            });
        }


        function previewCSVData(csvData, page, invalidCells = [], mapping = {}) {

            if (!csvData || !Array.isArray(csvData.headers)) {
                // console.error("CSV data structure is invalid. Headers or data are missing.");
                return;
            }

            const mappedHeaders = Object.keys(mapping);
            const mappedData = originalCsvData.data.map(row => {
                return mappedHeaders.map(header => {
                    const originalHeader = mapping[header];
                    const colIndex = originalCsvData.headers.indexOf(originalHeader);
                    return row[colIndex] || ''; // fallback in case of mismatch
                });
            });

            // const csvData = {
            //     headers: mappedHeaders,
            //     data: mappedData
            // };
            // Combine all validation errors
            const invalidEmailCells = validateEmails(csvData);
            const invalidMandatoryCells = validateMandatoryFields(csvData);
            const invalidScheduledDateCells = validateScheduledDeliveryDates(csvData);
            const invalidPersonalizationCells = validatePersonalization(csvData);
            const invalidRecipientCells = validateRecipientDetails(csvData);

            validateProductDetails(csvData).then(invalidProductCells => {
                // Merge all error types
                invalidCells = [
                    ...invalidCells,
                    ...invalidEmailCells,
                    ...invalidMandatoryCells,
                    ...invalidRecipientCells,
                    ...invalidPersonalizationCells,
                    ...invalidScheduledDateCells,
                    ...invalidProductCells
                ];

                // Group errors by row and determine which rows to show based on filter
                const errorsByRow = {};
                invalidCells.forEach(error => {
                    if (!errorsByRow[error.rowIndex]) {
                        errorsByRow[error.rowIndex] = [];
                    }
                    errorsByRow[error.rowIndex].push(error);
                });

                // Filter rows based on current filter setting
                const filteredRowIndices = [];
                csvData.data.forEach((row, rowIndex) => {
                    const isErrorRow = errorsByRow[rowIndex] && errorsByRow[rowIndex].length > 0;

                    if (currentFilter === 'all' ||
                        (currentFilter === 'errors' && isErrorRow) ||
                        (currentFilter === 'no-errors' && !isErrorRow)) {
                        filteredRowIndices.push(rowIndex);
                    }
                });

                // Calculate pagination based on filtered rows
                const totalRows = filteredRowIndices.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                const visibleRowIndices = filteredRowIndices.slice(start, end);

                // Build headers HTML
                let headersHtml = `<th>No.</th>` + csvData.headers.map(header =>
                    `<th>${header}</th>`
                ).join('');

                // Build rows HTML for visible rows only
                let rowsHtml = visibleRowIndices.map((originalRowIndex, displayIndex) => {
                    const row = csvData.data[originalRowIndex];
                    const rowErrors = errorsByRow[originalRowIndex] || [];
                    const isErrorRow = rowErrors.length > 0;

                    return `<tr class="${isErrorRow ? 'has-errors' : ''}">` +
                        `<td>${originalRowIndex + 1}</td>` + // Original row number
                        row.map((cell, colIndex) => {
                            const cellError = rowErrors.find(e => e.colIndex === colIndex);
                            const cellKey = `${originalRowIndex}-${colIndex}`;
                            let cellContent = editedData[cellKey] || cell;


                            const headerName = csvData.headers[colIndex];

                            // If column is 'Recipient Phone Number' and doesn't start with '+', prepend it
                            if (headerName === 'Recipient Phone Number' && cellContent && !cellContent.startsWith('+')) {
                                // console.log('cellContent: ', cellContent);
                                // console.log('cellContent check 61: ', /^61\d+$/.test(cellContent));
                                if (cellContent.startsWith('61')) {
                                    // Starts with 61 and digits but not properly formatted — e.g. missing space
                                    // Still treat it as needing + prefix
                                    cellContent = '+' + cellContent;
                                    // console.log('in cellContent: ', cellContent);
                                }
                                // Also update the underlying CSV data to reflect this change
                                csvData.data[originalRowIndex][colIndex] = cellContent;
                                originalCsvData.data[originalRowIndex][colIndex] = cellContent;
                            }

                            if (cellError) {
                                return `<td class="error-cell ${cellKey}" 
                                                data-error="${cellError.error || 'Invalid value'}"
                                                data-key="${cellKey}"
                                                contenteditable="${editMode}">
                                            ${cellContent}
                                        </td>`;
                            }
                            return `<td class="${cellKey}">${cellContent}</td>`;
                        }).join('') +
                        '</tr>';
                }).join('');

                // Update the table
                $('#close-csv-preview-table thead').html(`<tr>${headersHtml}</tr>`);
                $('#close-csv-preview-table tbody').html(rowsHtml);

                let emailKeyupTimeout;
                let idKeyupTimeout;
                $('#close-csv-preview-table tbody').on('blur', 'td[contenteditable="true"]', function () {
                    const cell = $(this);
                    const cellKey = cell.data('key');
                    const [rowIndexStr, colIndexStr] = cellKey.split('-').map(Number);
                    const rowIndex = parseInt(rowIndexStr);
                    const colIndex = parseInt(colIndexStr);
                    const newValue = cell.text().trim();
                    originalCsvData.data[rowIndex][colIndex] = newValue;

                    const headerName = originalCsvData.headers[colIndex];
                    console.log('cccccccccccccccccccccccc');
                    console.log('rowIndex: ', rowIndex);
                    console.log('colIndex: ', colIndex);
                    console.log('cellKey: ', cellKey);
                    console.log('csvData: ', originalCsvData);

                    /*console.log(csvData);
                    console.log(fetchedRecipientDetails);

                    if (headerName === 'Recipient ID') {
                        clearTimeout(idKeyupTimeout);
                        idKeyupTimeout = setTimeout(() => {
                            const id = parseInt(newValue);
                            if( id > 0 && id != fetchedRecipientDetails[user_id] ){

                            }
                        }, 500);    
                    }*/

                    let emailKeyupTimeout;
                    let idKeyupTimeout;

                    // Re-validate all errors (including recipient and product details)
                    const invalidRecipientCells = validateRecipientDetails(originalCsvData);
                    const invalidEmailCells = validateEmails(originalCsvData);
                    const invalidMandatoryCells = validateMandatoryFields(originalCsvData);
                    const invalidPersonalizationCells = validatePersonalization(originalCsvData);


                    validateProductDetails(originalCsvData).then(invalidProductCells => {
                        const invalidCells = [
                            ...invalidEmailCells,
                            ...invalidRecipientCells,
                            ...invalidMandatoryCells,
                            ...invalidPersonalizationCells,
                            ...invalidProductCells
                        ];

                        // Update the preview with the latest validation results
                        previewCSVData(originalCsvData, currentPage, invalidCells, mapping);
                    });



                    // Remove the editing class
                    $(this).removeClass('editing');


                    if (headerName === 'sRecipient Email Address') {
                        clearTimeout(emailKeyupTimeout);
                        emailKeyupTimeout = setTimeout(() => {
                            const email = newValue.toLowerCase();

                            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                                cell.addClass('error-cell').attr('data-error', 'Invalid email format');
                                return;
                            }

                            fetchRecipientDetailsByEmails(email).done(response => {
                                if (response.success && response.data.data[email]) {
                                    fetchedRecipientDetails[email] = response.data.data[email];
                                } else {
                                    delete fetchedRecipientDetails[email];
                                }

                                // Revalidate email and phone for this row
                                revalidateRow(rowIndex, csvData);
                            }).fail(() => {
                                console.warn('Failed to fetch recipient via keyup.');
                            });
                        }, 500);
                    }
                });




                // Update pagination
                if (totalPages <= 1) {
                    $('#csv-preview #pagination').html('');
                } else {
                    renderPagination(totalPages, page);
                }

                // Show message if no rows match filter
                if (filteredRowIndices.length === 0) {
                    const message = currentFilter === 'errors' ?
                        'No rows with errors found' :
                        'No rows without errors found please select another CSV file.';
                    $('#close-csv-preview-table tbody').html(
                        `<tr><td colspan="${csvData.headers.length + 1}" class="text-center">${message}</td></tr>`
                    );
                    jQuery('#next-button').hide();
                }

                // Update counts and UI
                updateRowCounts(csvData, invalidCells);
                updateNextButtonState(invalidCells);

                // ✅ Move button visibility logic here
                if (invalidCells.length === 0) {
                    currentFilter = 'all';
                    $('#filter-by').val('all').hide();
                    $('#edit-errors, #remove-error-lines, #download-resubmit').hide();
                    isCorrectedView = true;
                    console.log('In confirm');
                    $('#next-button').text('Confirm and Proceed →');
                } else {
                    $('#edit-errors, #remove-error-lines, #download-resubmit').show();
                    $('.correct-rows-count, .error-rows-count').show();
                }
            });
        }

        function revalidateRow(rowIndex, csvData) {
            const row = csvData.data[rowIndex];
            const deliveryMethodIndex = csvData.headers.indexOf('Delivery Method');
            const idIndex = csvData.headers.indexOf('Recipient ID');
            const emailIndex = csvData.headers.indexOf('Recipient Email Address');
            const phoneIndex = csvData.headers.indexOf('Recipient Phone Number');

            console.log('csvData::--- ', csvData);

            const deliveryMethod = row[deliveryMethodIndex]?.trim()?.toLowerCase();
            const recipientEmail = row[emailIndex]?.trim()?.toLowerCase();
            let recipientPhone = row[phoneIndex]?.trim()?.replace(/\s+/g, '') || '';

            //console.log('recipientPhone: ',recipientPhone);
            // Auto-add '+' if not present
            if (recipientPhone && !recipientPhone.startsWith('+')) {
                if (recipientPhone.startsWith('61')) {
                    // Starts with 61 and digits but not properly formatted — e.g. missing space
                    // Still treat it as needing + prefix
                    recipientPhone = '+' + recipientPhone;
                }
                csvData.data[rowIndex][phoneIndex] = recipientPhone;
                originalCsvData.data[rowIndex][phoneIndex] = recipientPhone;

                const phoneCell = $(`[data-key="${rowIndex}-${phoneIndex}"]`);
                phoneCell.text(recipientPhone);
            }

            const recipient = fetchedRecipientDetails[rowIndex];

            // === Email Cell
            const emailCell = $(`[data-key="${rowIndex}-${emailIndex}"]`);
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!recipientEmail || !emailRegex.test(recipientEmail)) {
                emailCell.addClass('error-cell').attr('data-error', 'Invalid email format');
            } else {
                emailCell.removeClass('error-cell').removeAttr('data-error');
            }

            // === Phone Cell
            const phoneCell = $(`[data-key="${rowIndex}-${phoneIndex}"]`);
            const phoneRegex = /^(?:\+61\s?4\d{2}\s?\d{3}\s?\d{3}|04\d{2}\s?\d{3}\s?\d{3})$/;
            const cleanPhone = recipientPhone.replace(/\D/g, '');
            let phoneError = null;

            if (recipientPhone && (/^614\d{8}$/.test(recipientPhone) || /^61\d+$/.test(recipientPhone))) {
                // Starts with 61 and digits but not properly formatted — e.g. missing space
                // Still treat it as needing + prefix
                recipientPhone = '+' + recipientPhone;
            }

            //console.log('phoneRegex222');
            //console.log(phoneRegex);
            if (deliveryMethod.includes('phone')) {
                if (!recipientPhone) {
                    phoneError = 'Phone number is required';
                } else if (!phoneRegex.test(recipientPhone)) {
                    phoneError = 'Invalid phone format';
                } else if (recipient) {
                    const cleanRecipientPhone = recipient.phone?.replace(/\D/g, '') || '';
                    if (cleanPhone !== cleanRecipientPhone) {
                        phoneError = 'Phone does not match recipient';
                    }
                }
            }

            if (phoneError) {
                phoneCell.addClass('error-cell').attr('data-error', phoneError);
            } else {
                phoneCell.removeClass('error-cell').removeAttr('data-error');
            }

            if (idIndex !== -1) {
                const csvrecipientID = row[idIndex];
                const recipientID = recipient.user_id;

                if (recipient.user_by_id <= 0) {
                    invalidCells.push({
                        rowIndex,
                        colIndex: idIndex,
                        error: 'Recipient ID is not found.'
                    });
                } else if (csvrecipientID !== recipientID && (parseInt(csvrecipientID) <=0 && recipientID <=0) ) {
                    invalidCells.push({
                        rowIndex,
                        colIndex: idIndex,
                        error: 'Recipient ID is not matched with Email.'
                    });
                }
            }

            if (emailIndex !== -1) {
                const csvrecipientEmail = row[emailIndex];
                const recipientEmail = recipient.email_by_id;
                if (csvrecipientEmail !== recipientEmail) {
                    emailCell.addClass('error-cell').attr('data-error', 'Invalid email format');
                    invalidCells.push({
                        rowIndex,
                        colIndex: emailIndex,
                        error: 'Email does not match with recipient ID.'
                    });
                }
            }

            // Recalculate counts
            const invalidCells = [
                ...validateEmails(csvData),
                ...validateMandatoryFields(csvData),
                ...validateRecipientDetails(csvData),
                ...validatePersonalization(csvData)
            ];
            updateRowCounts(csvData, invalidCells);
            updateNextButtonState(invalidCells);
        }



        // Function to update the next button state
        function updateNextButtonState(invalidCells) {
            if (invalidCells.length === 0) {
                $('#next-button').prop('disabled', false).css('opacity', 1);
                $('#edit-errors').hide();
            } else {
                $('#next-button').prop('disabled', true).css('opacity', 0.5);
                $('#edit-errors').show();
            }
        }

        // Function to validate email addresses
        function validateEmails(csvData) {
            let invalidCells = [];

            if (!csvData || !csvData.headers || !Array.isArray(csvData.headers)) {
                console.error("CSV data headers are missing or malformed");
                return [];
            }


            const emailIndex = csvData.headers.indexOf("Recipient Email Address");
            const deliveryMethodIndex = csvData.headers.indexOf("Delivery Method");


            if (emailIndex === -1 || deliveryMethodIndex === -1) {
                console.warn("Required headers not found");
                return [];
            }
            csvData.data.forEach((row, rowIndex) => {
                const deliveryMethod = row[deliveryMethodIndex];
                if (deliveryMethod !== 'Email') return;

                if (!/^\S+@\S+\.\S+$/.test(row[emailIndex])) {
                    invalidCells.push({
                        rowIndex,
                        colIndex: emailIndex
                    });
                }
            });
            return invalidCells;
        }

        function validatePhoneNumbers(csvData) {
            let invalidCells = [];
            const phoneIndex = csvData.headers.indexOf("Recipient Phone Number");
            const deliveryMethodIndex = csvData.headers.indexOf("Delivery Method");

            if (phoneIndex === -1 || deliveryMethodIndex === -1) return [];

            csvData.data.forEach((row, rowIndex) => {
                const deliveryMethod = row[deliveryMethodIndex];
                if (deliveryMethod !== 'SMS') return;

                // Add phone format validation here if needed
                if (!row[phoneIndex] || row[phoneIndex].trim() === '') {
                    invalidCells.push({
                        rowIndex,
                        colIndex: phoneIndex
                    });
                }
            });
            return invalidCells;
        }

        // Function to validate mandatory fields
        function validateMandatoryFields(csvData) {
            let invalidCells = [];
            const mandatoryHeaders = [
                'Recipient First Name', 'Delivery Method', 'Recipient Email Address',
                'Recipient Phone Number', 'Product Code', 'Gift Card Name', 'Gift Card Value',
                'Quantity', 'Personalisation'
            ];

            const deliveryMethodIndex = csvData.headers.indexOf('Delivery Method');
            const emailIndex = csvData.headers.indexOf('Recipient Email Address');
            const phoneIndex = csvData.headers.indexOf('Recipient Phone Number');

            csvData.data.forEach((row, rowIndex) => {
                const deliveryMethod = deliveryMethodIndex !== -1 ? row[deliveryMethodIndex] : '';

                mandatoryHeaders.forEach(header => {
                    const colIndex = csvData.headers.indexOf(header);
                    if (colIndex === -1) return;

                    // Skip validation based on delivery method
                    if (header === 'Recipient Email Address' && deliveryMethod !== 'Email') return;
                    if (header === 'Recipient Phone Number' && deliveryMethod !== 'SMS') return;

                    if (!row[colIndex] || row[colIndex].trim() === '') {
                        invalidCells.push({
                            rowIndex,
                            colIndex
                        });
                    }
                });
            });

            return invalidCells;
        }

        function updateRowCounts(csvData, invalidCells) {
            const cellMap = new Map();
            invalidCells.forEach(cell => {
                const key = `${cell.rowIndex}-${cell.colIndex}`;
                if (!cellMap.has(key)) {
                    cellMap.set(key, cell);
                }
            });

            const uniqueInvalidCells = Array.from(cellMap.values());
            const totalRows = csvData.data.length;


            // ✅ Prevent showing counts when no data exists
            if (totalRows === 0) {
                $('#correct-rows-count').hide().empty();
                $('#error-rows-count').hide().empty();
                return;
            }

            const errorRowsSet = new Set(uniqueInvalidCells.map(cell => cell.rowIndex));
            const errorRows = errorRowsSet.size;
            const correctRows = totalRows - errorRows;
            const totalFieldErrors = uniqueInvalidCells.length;

            const $correctBadge = $('#correct-rows-count');
            const $errorBadge = $('#error-rows-count');

            // Clear messages
            $correctBadge.hide().empty();
            $errorBadge.hide().empty();

            // Case 1: All lines have errors
            if (correctRows === 0 && errorRows > 0) {
                $errorBadge
                    .html(`✗ We found ${totalFieldErrors} ${totalFieldErrors === 1 ? 'error' : 'errors'} on ${errorRows} ${errorRows === 1 ? 'line' : 'lines'}.`)
                    .show();
                return; // Don’t show success message
            }

            // Case 2: Some lines correct, some lines have errors
            if (correctRows > 0 && errorRows > 0) {
                $correctBadge
                    .html(`✓ ${correctRows} ${correctRows === 1 ? 'line has' : 'lines have'} been uploaded successfully without errors.`)
                    .show();
                $errorBadge
                    .html(`✗ We found ${totalFieldErrors} ${totalFieldErrors === 1 ? 'error' : 'errors'} on ${errorRows} ${errorRows === 1 ? 'line' : 'lines'}.`)
                    .show();
                return;
            }

            // Case 3: All lines correct
            if (correctRows === totalRows) {
                $correctBadge
                    .html(`✓ All ${correctRows} ${correctRows === 1 ? 'line is' : 'lines are'} valid and uploaded successfully.`)
                    .show();
            }
        }

        // Function to render pagination
        // function renderPagination(totalPages, page) {
        //     let paginationHtml = '<ul class="pagination">';
        //     for (let i = 1; i <= totalPages; i++) {
        //         paginationHtml += `<li class="page-item ${i === page ? 'active' : ''}">
        //         <a href="#" class="page-link" data-page="${i}">${i}</a></li>`;
        //     }
        //     paginationHtml += '</ul>';
        //     $('#csv-preview #pagination').html(paginationHtml);
        // }

        // Event listener for updating edited cells
        $('#csv-preview-table').on('focus', 'td[contenteditable=true]', function () {
            // Highlight the cell being edited
            $(this).addClass('editing');
        });

        $('#csv-preview-table').on('blur', 'td[contenteditable=true]', function () {
            const key = $(this).data('key');
            const newValue = $(this).text().trim();

            // Update the edited data
            editedData[key] = newValue;

            const [rowIndex, colIndex] = key.split('-').map(Number);
            originalCsvData.data[rowIndex][colIndex] = newValue;

            console.log('newValue: ', newValue);

            // Re-validate all errors (including recipient and product details)
            const invalidEmailCells = validateEmails(originalCsvData);
            const invalidMandatoryCells = validateMandatoryFields(originalCsvData);
            const invalidPersonalizationCells = validatePersonalization(originalCsvData);
            const invalidRecipientCells = validateRecipientDetails(originalCsvData);

            let emailKeyupTimeout;
            let idKeyupTimeout;

            validateProductDetails(originalCsvData).then(invalidProductCells => {
                const invalidCells = [
                    ...invalidEmailCells,
                    ...invalidMandatoryCells,
                    ...invalidRecipientCells,
                    ...invalidPersonalizationCells,
                    ...invalidProductCells
                ];

                // Update the preview with the latest validation results
                previewCSVData(originalCsvData, currentPage, invalidCells, mapping);
            });

            if (colIndex === 0) {
                clearTimeout(idKeyupTimeout);
                idKeyupTimeout = setTimeout(() => {
                    const recipientID = parseInt(newValue);
                    const recipientEmail = $('#csv-preview-table td.' + rowIndex + '-6').text().trim().toLowerCase();
                    if (recipientID) {
                        fetchRecipientDetailsByEmails([recipientID], [recipientEmail], originalCsvData).done(response => {
                            if (response.success && response.data.data[rowIndex]) {
                                fetchedRecipientDetails[rowIndex] = response.data.data[rowIndex];
                            } else {
                                delete fetchedRecipientDetails[rowIndex];
                            }

                            // Revalidate email and phone for this row
                            revalidateRow(rowIndex, originalCsvData);
                        }).fail(() => {
                            console.warn('Failed to fetch recipient via keyup.');
                        });
                    }
                }, 500);
            }

            console.log('fetchedRecipientDetails', fetchedRecipientDetails);

            if (colIndex === 6) {
                clearTimeout(emailKeyupTimeout);
                emailKeyupTimeout = setTimeout(() => {
                    const email = newValue.toLowerCase();

                    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        cell.addClass('error-cell').attr('data-error', 'Invalid email format');
                        return;
                    }

                    const ID = parseInt($('#csv-preview-table td.' + rowIndex + '-2').text().trim());
                    console.log('eeeeeeeeeeeeeeeeeeeeeeeee');
                    console.log('ID: ', ID);
                    console.log('email: ', email);

                    // Make sure both ID and email exist before calling
                    if (email) {
                        fetchRecipientDetailsByEmails([ID], [email], originalCsvData).done(response => {
                            if (response.success && response.data.data[rowIndex]) {
                                fetchedRecipientDetails[rowIndex] = response.data.data[rowIndex];
                            } else {
                                delete fetchedRecipientDetails[rowIndex];
                            }

                            console.log('dddddddddddddddddddddddddd');
                            console.log('fetchedRecipientDetails::: ', fetchedRecipientDetails);
                            // Revalidate email and phone for this row
                            revalidateRow(rowIndex, originalCsvData);
                        }).fail(() => {
                            console.warn('Failed to fetch recipient via keyup.');
                        });
                    }
                }, 500);
            }

            // Remove the editing class
            $(this).removeClass('editing');
        });

        $('#csv-preview-table').on('keydown', 'td[contenteditable=true]', function (e) {
            // Allow the user to press Enter to finish editing
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).blur(); // Trigger the blur event to finish editing
            }
        });
        // Event listener for updating edited cells

        $('#CLOSE-remove-error-lines').on('click', function () {

            const $button = $(this);
            const originalText = $button.text();
            // Show loading text and disable the button
            $button.text('Removing...').prop('disabled', true);

            // Get ALL validation errors including product validation
            const invalidEmailCells = validateEmails(originalCsvData);
            const invalidMandatoryCells = validateMandatoryFields(originalCsvData);
            const invalidRecipientCells = validateRecipientDetails(originalCsvData);
            const invalidPersonalizationCells = validatePersonalization(originalCsvData);
            const invalidScheduleCells = validateScheduledDeliveryDates(originalCsvData); // ✅ ADD THIS


            // Wait for product validation to complete
            validateProductDetails(originalCsvData).then(invalidProductCells => {
                // Combine all error types
                const allInvalidCells = [
                    ...invalidEmailCells,
                    ...invalidMandatoryCells,
                    ...invalidRecipientCells,
                    ...invalidPersonalizationCells,
                    ...invalidScheduleCells,
                    ...invalidProductCells
                ];

                // Get unique row indexes with any errors
                const rowsToRemove = new Set(allInvalidCells.map(cell => cell.rowIndex));

                // Filter out ALL rows with ANY errors
                originalCsvData.data = originalCsvData.data.filter((row, index) =>
                    !rowsToRemove.has(index)
                );

                // Reset states and update preview
                editMode = false;
                $('#edit-errors').text('Edit Errors');
                currentPage = 1;

                // if (originalCsvData.data.length === 0 || allInvalidCells.length === 0) {
                //     console.log('-----------');
                //     $('#edit-errors').hide();
                // }

                previewCSVData(originalCsvData, currentPage, [], mapping);

                $button.text(originalText).prop('disabled', false);
            });
        });
        // Event listener for downloading and resubmitting

        $('#CLOSE-download-resubmit').on('click', function () {

            let csvContent = "data:text/csv;charset=utf-8," + [

                originalCsvData.headers.join(","),

                ...originalCsvData.data.map(e => e.join(","))

            ].join("\n");

            let encodedUri = encodeURI(csvContent);

            let link = document.createElement("a");

            link.setAttribute("href", encodedUri);

            link.setAttribute("download", "invalid_rows.csv");

            document.body.appendChild(link);

            link.click();

        });



        // Event listener for pagination

        $(document).on('click', '.page-link', function (e) {

            e.preventDefault();

            const page = parseInt($(this).data('page'));

            if (page !== currentPage) {

                currentPage = page;

                previewCSVData(originalCsvData, currentPage, [], mapping);

            }

        });



        // Event listener for filtering

        $('#filter-by').on('change', function () {
            currentFilter = $(this).val();
            currentPage = 1;

            const invalidEmailCells = validateEmails(originalCsvData);
            const invalidPhoneCells = validatePhoneNumbers(originalCsvData);
            const invalidMandatoryCells = validateMandatoryFields(originalCsvData);
            const invalidRecipientCells = validateRecipientDetails(originalCsvData);
            const invalidPersonalizationCells = validatePersonalization(originalCsvData);

            validateProductDetails(originalCsvData).then(invalidProductCells => {
                const invalidCells = [
                    ...invalidEmailCells,
                    ...invalidPhoneCells,
                    ...invalidMandatoryCells,
                    ...invalidRecipientCells,
                    ...invalidPersonalizationCells,
                    ...invalidProductCells
                ];

                previewCSVData(originalCsvData, currentPage, invalidCells, mapping);
            });
        });
        $('#CLOSE-edit-errors').on('click', function () {
            editMode = !editMode; // Toggle edit mode

            // Re-validate to maintain error highlighting
            const invalidCells = [
                ...validateEmails(originalCsvData),
                ...validateMandatoryFields(originalCsvData),
                ...validateRecipientDetails(originalCsvData),
                ...validatePersonalization(originalCsvData)
            ];

            // Update button text
            $(this).text(editMode ? 'Cancel Editing' : 'Edit Errors');

            // Apply edit mode to all error cells
            $('#csv-preview-table td.error-cell').each(function () {
                $(this).attr('contenteditable', editMode);
                if (editMode) {
                    $(this).css('background-color', '#fff3cd'); // Light yellow for editing
                } else {
                    $(this).css('background-color', ''); // Revert to normal error color
                }
            });
        });
        var currentStep = 'bulk-upload';


        // Event listener for the next button

        // Modify existing next button handler
        // Modify the next button handler to ensure proper state
        // Modify the next button handler for the bulk upload flow
        $('#CLOSE-next-button').on('click', function () {
            const invalidCells = [...validateEmails(originalCsvData), ...validateMandatoryFields(originalCsvData)];
            if (invalidCells.length === 0) {
                if (!isCorrectedView) {
                    // First click for corrected view (if needed)
                    currentFilter = 'all';
                    $('#filter-by').val('all').hide();
                    $('#edit-errors, #remove-error-lines, #download-resubmit, .correct-rows-count, .error-rows-count').hide();
                    previewCSVData(originalCsvData, 1);
                    isCorrectedView = true;
                    $(this).text('Confirm and Proceed →');
                } else {
                    // Proceed to activation
                    $('#csv-preview').addClass('d-none');
                    $('#card-activation-form').removeClass('d-none');
                    currentStep = 'card-activation';
                    $(this).hide();

                    // Prefill the sender dropdown in the activation form
                    if (selectedSender) {
                        $('#card-activation-form #sender-dropdown').val(selectedSender);
                    }

                    // Also prefill other fields that were passed from the order form
                    $('#display-order-name').text($('#order-name').val());
                    $('#display-order-id').text($('#order-id').val());
                    $('#display-client-reference').text($('#client-reference').val());
                }
            }
        });

        // Function to show the corrected CSV preview
        function showCorrectedCSVPreview(csvData, page) {
            const totalRows = csvData.data.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            let headersHtml = `<th>No.</th>` + csvData.headers.map(header => `<th>${header}</th>`).join('');
            let rowsHtml = csvData.data.slice(start, end).map((row, rowIndex) => {
                return `<tr>` +
                    `<td>${rowIndex + start + 1}</td>` + // Automatically generate row numbers
                    row.map((cell, colIndex) => {
                        let cellKey = `${rowIndex + start}-${colIndex}`;
                        let cellContent = editedData[cellKey] || cell;
                        return `<td class="${cellKey}">${cellContent}</td>`;
                    }).join('') + '</tr>';
            }).join('');

            $('#csv-preview-table thead').html(`<tr>${headersHtml}</tr>`);
            $('#csv-preview-table tbody').html(rowsHtml);

            // Hide pagination if there are fewer than 20 rows
            if (totalRows <= rowsPerPage) {
                $('#csv-preview #pagination').html('');
            } else {
                renderPagination(totalPages, page);
            }

            $('#csv-preview').removeClass('d-none');
        }

        function showCardActivationForm() {
            $('#csv-preview').addClass('d-none');
            $('#card-activation-form').removeClass('d-none');
        }
        // Event listener for the next step in the card activation form

        // Add these variables to track state
        var isCorrectedView = false;

        $('#CLOSE-card-activation-form #next-step').on('click', function () {
            console.log('Clicked');
            // console.log('Testing 0: Next button clicked');

            // console.log('currentStep:', currentStep);
            // console.log('originalCsvData:', originalCsvData);
            // console.log('originalCsvData.data:', originalCsvData?.data);

            if (currentStep === 'card-activation' && originalCsvData && originalCsvData.data.length > 0) {
                // console.log('Testing 1: Condition passed');

                const csvData = {
                    headers: originalCsvData.headers,
                    data: originalCsvData.data
                };

                // console.log('Testing 2: CSV data prepared:', csvData);
                $('<input>', {
                    type: 'hidden',
                    class: 'bulk-order-flow',
                    name: 'bulk_order_flow',
                    value: 'bulk-order-flow'
                }).appendTo('#card-activation-form');
                jQuery('#back-to-order-form').hide();
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    type: 'POST',
                    data: {
                        action: 'process_bulk_order_data',
                        csv_data: JSON.stringify(csvData),
                        security: '<?php echo wp_create_nonce("bulk_order_nonce"); ?>'
                    },
                    success: function (response) {
                        // console.log('Testing 3: AJAX success:', response);

                        if (response.success) {
                            // console.log('Testing 4: Response success');
                            // Hide the card activation form
                            $('#card-activation-form').addClass('d-none');

                            // Show the correct parent container based on the flow (manual or bulk)
                            if (currentStep === 'card-activation') {
                                $('#multi-step-form-bulk').addClass('d-none'); // hide bulk upload container
                                $('#csv-preview-container').addClass('d-none');
                                $('.table-container').addClass('d-none');
                                $('.table-container').addClass('d-none');
                                $('.gift-card-container').addClass('d-none');
                                $('#save-and-next-btn').addClass('d-none');
                                $('#multi-step-form').removeClass('d-none');
                                $('#multi-step-form').addClass('full-width');
                                // console.log('ashjhdjshjad'); // Show manual order container
                            }

                            // Show the customization form
                            $('.customisation-container').show();
                            // Update the step indicator
                            const activeStep = document.querySelector(".step.active-step");
                            // console.log('activeStep----', activeStep);
                            if (activeStep) activeStep.classList.remove("active-step");
                            const customizationStep = document.querySelector(".step-indicator .step:nth-child(2)");
                            if (customizationStep) customizationStep.classList.add("active-step");

                            // Reset "Personalise All" to unchecked
                            const personaliseAllCheckbox = document.getElementById("personalise-all");
                            if (personaliseAllCheckbox) personaliseAllCheckbox.checked = false;

                            // Trigger a custom event with the data
                            const event = new CustomEvent('bulkDataLoaded', {
                                detail: response.data
                            });
                            // console.log(event);
                            document.dispatchEvent(event);

                            // Explicitly show checkboxes after creation
                            document.querySelectorAll(".gift-card-checkbox input").forEach(checkbox => {
                                checkbox.style.display = "inline-block"; // Force visibility
                            });
                        } else {
                            // console.log('Testing 5: Response error:', response.data.message);
                            alert('Error processing bulk order data: ' + response.data);
                        }
                    },
                    error: function (xhr, status, error) {
                        // console.log('Testing 6: AJAX error:', error);
                        console.error('AJAX Error:', error);
                        alert('An error occurred while processing the bulk order data.');
                    }
                });
            } else {
                // console.log('Testing 7: Condition failed');
            }
        });
        // New function to reset CSV-related state
        function resetCsvState() {
            originalCsvData = {};
            templateHeaders = [];
            headerMapping = [];
            editedData = {};
            currentPage = 1;
            editMode = false;
            currentFilter = 'all';
            isCorrectedView = false;

            // Reset UI elements
            jQuery('#csv-file-input').val('');
            jQuery('#csv-preview-table thead').empty();
            jQuery('#csv-preview-table tbody').empty();
            jQuery('#filter-by').val('all').show();
            jQuery('#next-button').text('Next').show();
            console.log('Inside resetCsvState');
            jQuery('#edit-errors').text('Edit Errors');
            jQuery('#edit-errors, #remove-error-lines, #download-resubmit').show();
            jQuery('.correct-rows-count, .error-rows-count').empty();
            jQuery('#csv-preview #pagination').empty();
        }


        $('#back-to-csv-preview').on('click', function () {
            // console.log('Back button clicked'); // Debugging statement

            // Hide the card activation form
            $('#card-activation-form').addClass('d-none');

            // Show the CSV preview
            $('#csv-preview').removeClass('d-none');

            // Reset the "Next" button state
            $('#next-button').show().text('Confirm and Proceed →');
        });


        // Modify back button handlers
        jQuery('#back-to-bulk-upload').on('click', function () {
            jQuery('#edit-errors').text('Edit Errors');
            // console.log('CCCCCCCCCCCC');
            // Also reset edit mode if needed
            editMode = false;
            jQuery('#csv-preview-table td.error-cell').each(function () {
                jQuery(this).attr('contenteditable', false);
                jQuery(this).css('background-color', ''); // Revert to normal
            });

            jQuery('#csv-preview').addClass('d-none');
            setTimeout(() => {
                // console.log('jad');
                jQuery('#multi-step-form-bulk').removeClass('d-none');
            }, 300);
            jQuery('#page-spacer-top').hide();
            // setTimeout(() => {
                // console.log('jad2');
                jQuery('.bulk-add-container').show();
            // },300);
            currentStep = 'bulk-upload';
            resetCsvState();
        });

        $('#back-to-csv-preview').on('click', function () {
            $('#card-activation-form').addClass('d-none');
            $('#csv-preview').removeClass('d-none');
            currentStep = 'csv-preview';
        });

        var activationExpiryType = document.getElementById("bulk_activation_expiry_type");
        var activationExpiryDateField = document.getElementById("bulk_activation_expiry_date_field");
        var activationExpiryPeriodField = document.getElementById("bulk_activation_expiry_period_field");
        var activationExpiryDateInput = document.getElementById("bulk_activation_expiry_date");

        function toggleGiftExpiryFields() {
            var selectedValue = giftExpiryType.value;
            giftExpiryDateField.style.display = (selectedValue === "gift_set_date") ? "block" : "none";
            giftExpiryDurationField.style.display = (selectedValue === "purchase" || selectedValue === "activation") ? "block" : "none";
        }

        function toggleActivationExpiryFields() {
            var selectedValue = activationExpiryType.value;
            activationExpiryDateField.style.display = (selectedValue === "activation_set_date") ? "block" : "none";
            activationExpiryPeriodField.style.display = (selectedValue === "set_period") ? "block" : "none";
        }

        function setMinDate(input) {
            var today = new Date().toISOString().split("T")[0];
            input.setAttribute("min", today);
        }

        if (giftExpiryType) {
            giftExpiryType.addEventListener("change", toggleGiftExpiryFields);
            toggleGiftExpiryFields();
        }

        if (activationExpiryType) {
            activationExpiryType.addEventListener("change", toggleActivationExpiryFields);
            toggleActivationExpiryFields();
        }

        if (giftExpiryDateInput) {
            setMinDate(giftExpiryDateInput);
        }

        if (activationExpiryDateInput) {
            setMinDate(activationExpiryDateInput);
        }

        document.getElementById('gift-card-image').addEventListener('change', function (event) {
            const previewContainer = document.querySelector('.selected-design-card-preview');
            const file = event.target.files[0];

            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();

                reader.onload = function (e) {
                    // Clear previous preview
                    previewContainer.innerHTML = '';

                    // Create preview image
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Gift Card Preview';
                    img.style.maxWidth = '100%';
                    img.style.borderRadius = '8px';

                    previewContainer.appendChild(img);
                };

                reader.readAsDataURL(file);
            } else {
                previewContainer.innerHTML = '<p style="color:red;">Selected file is not an image.</p>';
            }
        });

        const dropzone = document.getElementById("gift-card-dropzone");
        const fileInput = document.getElementById("gift-card-image");

        if (dropzone && fileInput) {
        // Open file chooser on click
        dropzone.addEventListener("click", () => fileInput.click());

        // Optional: update text/icon when file is selected
        fileInput.addEventListener("change", () => {
            if (fileInput.files.length > 0) {
                dropzone.querySelector("p").innerHTML = `Selected: ${fileInput.files[0].name}`;
            }
        });

        // Drag & Drop functionality
        dropzone.addEventListener("dragover", (e) => {
            e.preventDefault();
            dropzone.classList.add("dragover");
        });

        dropzone.addEventListener("dragleave", () => {
            dropzone.classList.remove("dragover");
        });

        dropzone.addEventListener("drop", (e) => {
            e.preventDefault();
            dropzone.classList.remove("dragover");

            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                const event = new Event("change");
                fileInput.dispatchEvent(event);
            }
        });

        } // end if (dropzone && fileInput)

    });
</script>

<?php
include get_template_directory() . '/manual-order-js.php';