jQuery(document).ready(function($) {
    //console.log(ORDER_REPORT_VAL);
    const VAL     = BILLING_REPORT_VAL;

    const table = $('#billing_reportsTable').DataTable({
        columns: [
            { title: "Order Number" },
            { title: "Order Date" },
            { title: "Order Time " },
            { title: "Month of Order" },
            { title: "Order Name" },
            { title: "User" },
            { title: "User Type" },
            { title: "Business Name" },
            { title: "Business ID" },
            { title: "Business Float ID" },
            { title: "Approved for Client Billing (Y/N)" },
            { title: "Payment  Type" },
            { title: "Order Status" },
            { title: "Invoice Number" },
            { title: "Payment Status" },
            { title: "Order Total ($)" },
            { title: "Order Total Gift Cards ($)" },
            { title: "Order Total Fulfilment ($)" },
            { title: "Order Total Delivery cost" },
            { title: "Order Total GST" },
            { title: "Order Total Supplier Buy Price" },
            { title: "Order Total Supplier Fulfiment Cost" },
            { title: "Order Total Supplier Delivery Cost" },
            { title: "Order Total Supplier GST Cost" },
            { title: "Gift Card Brand" },
            { title: "Gift Card Name" },
            { title: "Gift Card SKU" },
            { title: "Gift Card Supplier SKU" },
            { title: "Gift Card Number" },
            { title: "Card Type (Variable/ Fixed)" },
            { title: "Gift Card Denomination" },
            { title: "Gift Card Supplier " },
            { title: "Gift Card Set Sell Price" },
            { title: "Gift Card  Price" },
            { title: "Offer Price (Y/N)" },
            { title: "Gift Card Offer Price" },
            { title: "Gift Card Buy Price" },
            { title: "Gift Card Margin $" },
            { title: "Gift Card Margin %" },
            { title: "GC+ Gift Card Fulfilment Price" },
            { title: "Gift Card Supplier Fulfilment Cost" },
            { title: "Gift Cards+ Gift Card Delivery Price" },
            { title: "Gift Card Supplier Delivery Cost " },
            { title: "Gift Cards+ GST" },
            { title: "Supplier GST" },
            { title: "Gift Card Total Buy Price" },
            { title: "Gift Card Total Buy Price inc GST" },
            { title: "Gift Card Total Sell Price" },
            { title: "Gift Card Total Margin $" },
            { title: "Gift Card Total Margin %" },
            { title: "Gift Card Status" },
            { title: "Gift Card Delivery Date" },
            { title: "Campaign" },
            { title: "Sender Profile" },
            { title: "Client Reference" },
            { title: "PO Number" },
            { title: "Additional Client Reference" },
            { title: "Order level activation expiry" },
            { title: "Activation expiry set for this order" },
            { title: "Client Payment Due Date" },
        ],
        pageLength: 25, // default rows per page
        lengthChange: true,   // Enable page length dropdown (default is true)
        lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ],  // Dropdown options
        responsive: true,
        data: [],
        order: [[0, 'desc']],
        dom: 'lBfrtip',        // For buttons and length menu positioning with other controls
        buttons: [
            {
                extend: 'copy',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#billing_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'csv',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#billing_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#billing_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'pdf',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#billing_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#billing_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#billing_reportsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');
        
        const isDateColumn = index === 1;
        const isStatusColumn = index === 12;
    
        let inputField;
        //console.log(colSlug);
    
        if (isDateColumn) {
            // Two date inputs for From and To with clear 'X' button
            inputField = `
                <div style="position:relative; margin-bottom:4px;">
                    <label>From:<br>
                        <input type="date" class="column-search date-from" data-col="${index}" style="width:calc(100% - 20px); padding:5px;">
                        <span class="clear-date remove-date-from" style="position:absolute; right:11px; top:0px; cursor:pointer; color:red;">✕</span>
                    </label>
                </div>
                <div style="position:relative;">
                    <label>To:<br>
                        <input type="date" class="column-search date-to" data-col="${index}" style="width:calc(100% - 20px); padding:5px;">
                        <span class="clear-date remove-date-to" style="position:absolute; right:11px; top:0px; cursor:pointer; color:red;">✕</span>
                    </label>
                </div>
            `;
        } else if (isStatusColumn) {
            inputField = `<div class="status-checkboxes" data-col="${index}">
            <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="o_status" class="status-filter" value="Pending"> Pending
                    </label>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="o_status" class="status-filter" value="Processing"> Processing
                    </label>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="o_status" class="status-filter" value="Completed"> Completed
                    </label>
            </div>`;
        } else {
            inputField = `<input type="text" class="column-search" data-col="${index}" placeholder="Search..." style="width:100%; padding:5px;">`;
        }
    
        jQuery(this).html(`
            ${colText}
            <span class="filter-icon" data-col="${index}" style="cursor:pointer; width:16px; height:16px;">
               <i class="fa-solid fa-arrow-down"></i>
            </span>
            <div class="filter-box" data-head_slug="${colSlug}" data-col="${index}" style="display:none; background:white; border:1px solid #ccc; padding:5px; z-index:999;">
                ${inputField}
            </div>
        `);
    });

    const filterBoxStates = {};
    jQuery('#billing_reportsTable thead').on('click', '.filter-icon', function (e) {
        e.stopPropagation();
        const colIndex = jQuery(this).data('col');
        const filterBox = jQuery(`.filter-box[data-col="${colIndex}"]`);

        const isOpen = filterBoxStates[colIndex];

       
        if (isOpen) {
            filterBox.hide().removeClass('active_filter');
            filterBox.parent().removeClass('active_filter_wrapper');
            filterBoxStates[colIndex] = false;
        } else {
            filterBox.show().addClass('active_filter');
            filterBox.parent().addClass('active_filter_wrapper');
            filterBoxStates[colIndex] = true;
        }

        // Prevent clicks inside from closing
        filterBox.off('click').on('click', function (e) {
            e.stopPropagation();
        });
    });

    jQuery('#billing_reportsTable thead').on('change', '.status-filter', function () {
        let selectedStatuses = $('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex($(this).val()); // escape safely
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Draft|Processing|Completed)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            table.column(12).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            table.column(12).search('').draw();
        }
    });

    // Live filtering
    jQuery('#billing_reportsTable thead').on('keyup change', '.column-search', function () {
        const colIndex = $(this).data('col');
    
        if (colIndex === 1) { // Date column: handle range filter
            // Find both From and To inputs for this date column
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const fromDate = $filterBox.find('.date-from').val();
            const toDate = $filterBox.find('.date-to').val();
    
            // Build a custom filter that filters rows based on date range
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'dateRangeFilter');
    
            if (fromDate || toDate) {
                $.fn.dataTable.ext.search.push(function dateRangeFilter(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'billing_reportsTable') return true;
                
                    
                    const cellDateStr = data[colIndex];
                    if (!cellDateStr) return false;
    
                    const cellDateRaw = new Date(cellDateStr);
                    if (isNaN(cellDateRaw)) return false;
                    const cellDate = stripTime(cellDateRaw);
    
                    const from = fromDate ? stripTime(new Date(fromDate)) : null;
                    const to = toDate ? stripTime(new Date(toDate)) : null;
    
                    if (from && cellDate < from) return false;
                    if (to && cellDate > to) return false;
                    return true;
                });            
            }
    
            table.draw();
            return;
        }
        function stripTime(date) {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate());
        }
        // Other columns' filtering logic remains the same here
        let value = this.value;
    
        const isLastColumn = colIndex === $('#billing_reportsTable thead th').length - 1;
    
        if (1==2 && isLastColumn && value) {
            const escaped = $.fn.dataTable.util.escapeRegex(value);
            value = `^AUD ${escaped}(\\.00)?$`;
            table
                .column(colIndex)
                .search(value, true, false)
                .draw();
        } else {
            table
                .column(colIndex)
                .search(value)
                .draw();
        }
    });

    async function fetchOrders(page = 1, perPage = 50) {
        try {
            
            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });

            if (BILLING_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
            if (!response.ok) throw new Error('Failed to fetch orders');

            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error fetching orders:', error);
            return { orders: [], total_orders: 0 };
        }
    }

    async function loadOrdersForTable() {
        let page = 1;
        const perPage = 50;

        const firstPage = await fetchOrders(page, perPage);
        table.clear();
        table.rows.add(firstPage.orders.map(order => [
            order.order_number,
            order.order_date,
            order.order_time_,
            order.month_of_order,
            order.order_name,
            order.user,
            order.user_type,
            order.business_name,
            order.business_id,
            order.business_float_id,
            order.approved_for_client_billing_y_n,
            order.payment_type,
            order.order_status,
            order.invoice_number,
            order.payment_status,
            order.order_total,
            order.order_total_gift_cards,
            order.order_total_fulfilment,
            order.order_total_delivery_cost,
            order.order_total_gst,
            order.order_total_supplier_buy_price,
            order.order_total_supplier_fulfiment_cost,
            order.order_total_supplier_delivery_cost,
            order.order_total_supplier_gst_cost,
            order.gift_card_brand,
            order.gift_card_name,
            order.gift_card_sku,
            order.gift_card_supplier_sku,
            order.gift_card_number,
            order.card_type_variable_fixed,
            order.gift_card_denomination,
            order.gift_card_supplier_,
            order.gift_card_set_sell_price,
            order.gift_card_price,
            order.offer_price_y_n,
            order.gift_card_offer_price,
            order.gift_card_buy_price,
            order.gift_card_margin,
            order.gift_card_margin,
            order.gc_gift_card_fulfilment_price,
            order.gift_card_supplier_fulfilment_cost,
            order.gift_cards_gift_card_delivery_price,
            order.gift_card_supplier_delivery_cost_,
            order.gift_cards_gst,
            order.supplier_gst,
            order.gift_card_total_buy_price,
            order.gift_card_total_buy_price_inc_gst,
            order.gift_card_total_sell_price,
            order.gift_card_total_margin,
            order.gift_card_total_margin,
            order.gift_card_status,
            order.gift_card_delivery_date,
            order.campaign,
            order.sender_profile,
            order.client_reference,
            order.po_number,
            order.additional_client_reference,
            order.order_level_activation_expiry,
            order.activation_expiry_set_for_this_order,
            order.client_payment_due_date,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_orders / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchOrders(p, perPage);
            table.rows.add(pageData.orders.map(order => [
                order.order_number,
                order.order_date,
                order.order_time_,
                order.month_of_order,
                order.order_name,
                order.user,
                order.user_type,
                order.business_name,
                order.business_id,
                order.business_float_id,
                order.approved_for_client_billing_y_n,
                order.payment_type,
                order.order_status,
                order.invoice_number,
                order.payment_status,
                order.order_total,
                order.order_total_gift_cards,
                order.order_total_fulfilment,
                order.order_total_delivery_cost,
                order.order_total_gst,
                order.order_total_supplier_buy_price,
                order.order_total_supplier_fulfiment_cost,
                order.order_total_supplier_delivery_cost,
                order.order_total_supplier_gst_cost,
                order.gift_card_brand,
                order.gift_card_name,
                order.gift_card_sku,
                order.gift_card_supplier_sku,
                order.gift_card_number,
                order.card_type_variable_fixed,
                order.gift_card_denomination,
                order.gift_card_supplier_,
                order.gift_card_set_sell_price,
                order.gift_card_price,
                order.offer_price_y_n,
                order.gift_card_offer_price,
                order.gift_card_buy_price,
                order.gift_card_margin,
                order.gift_card_margin,
                order.gc_gift_card_fulfilment_price,
                order.gift_card_supplier_fulfilment_cost,
                order.gift_cards_gift_card_delivery_price,
                order.gift_card_supplier_delivery_cost_,
                order.gift_cards_gst,
                order.supplier_gst,
                order.gift_card_total_buy_price,
                order.gift_card_total_buy_price_inc_gst,
                order.gift_card_total_sell_price,
                order.gift_card_total_margin,
                order.gift_card_total_margin,
                order.gift_card_status,
                order.gift_card_delivery_date,
                order.campaign,
                order.sender_profile,
                order.client_reference,
                order.po_number,
                order.additional_client_reference,
                order.order_level_activation_expiry,
                order.activation_expiry_set_for_this_order,
                order.client_payment_due_date,
            ])).draw(false);
        }
    }

    loadOrdersForTable();
});