jQuery(document).ready(function($) {
    //console.log(ORDER_REPORT_VAL);
    const VAL     = CLIENT_BILLING_ORDER_REPORT_VAL;

    const table = $('#client_billing_order_reportsTable').DataTable({
        columns: [
            { title: "Order Number" },
            { title: "Order Date" },
            { title: "Order Time" },
            { title: "Order Name" },
            { title: "User" },
            { title: "Business Name" },
            { title: "Business ID" },
            { title: "Business Float ID" },
            { title: "Approved for Client Billing (Y/N)" },
            { title: "Billing Type" },
            { title: "Order Status" },
            { title: "Invoice Number" },
            { title: "Payment Status" },
            { title: "Total ($)" },
            { title: "Total Gift Cards ($)" },
            { title: "Total Fulfilment ($)" },
            { title: "Delivery cost" },
            { title: "GST" },
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
                            return $('#client_billing_order_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#client_billing_order_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#client_billing_order_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#client_billing_order_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#client_billing_order_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#client_billing_order_reportsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');
        
        const isDateColumn = index === 1;
        const isStatusColumn = index === 10;
    
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
    jQuery('#client_billing_order_reportsTable thead').on('click', '.filter-icon', function (e) {
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

    jQuery('#client_billing_order_reportsTable thead').on('change', '.status-filter', function () {
        let selectedStatuses = $('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex($(this).val()); // escape safely
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Draft|Processing|Completed)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            table.column(10).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            table.column(10).search('').draw();
        }
    });

    // Live filtering
    jQuery('#client_billing_order_reportsTable thead').on('keyup change', '.column-search', function () {
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
                    if (settings.nTable.id !== 'client_billing_order_reportsTable') return true;
                
                    
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
    
        const isLastColumn = colIndex === $('#client_billing_order_reportsTable thead th').length - 1;
    
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

    async function fetchClientBillingOrders(page = 1, perPage = 50) {
        try {
            
            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });

            if (CLIENT_BILLING_ORDER_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
            if (!response.ok) throw new Error('Failed to fetch Client Billing orders');

            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error fetching Client Billing orders:', error);
            return { client_billing_orders: [], total_client_billing_orders: 0 };
        }
    }

    async function loadClientBillingOrdersForTable() {
        let page = 1;
        const perPage = 50;

        const firstPage = await fetchClientBillingOrders(page, perPage);
        table.clear();
        table.rows.add(firstPage.client_billing_orders.map(cborder => [
            cborder.order_number,
            cborder.order_date,
            cborder.order_time,
            cborder.order_name,
            cborder.user,
            cborder.business_name,
            cborder.business_id,
            cborder.business_float_id,
            cborder.approved_for_client_billing_y_n,
            cborder.billing_type,
            cborder.order_status,
            cborder.invoice_number,
            cborder.payment_status,
            cborder.total,
            cborder.total_gift_cards,
            cborder.total_fulfilment,
            cborder.delivery_cost,
            cborder.gst,
            cborder.campaign,
            cborder.sender_profile,
            cborder.client_reference,
            cborder.po_number,
            cborder.additional_client_reference,
            cborder.order_level_activation_expiry,
            cborder.activation_expiry_set_for_this_order,
            cborder.client_payment_due_date,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_client_billing_orders / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchClientBillingOrders(p, perPage);
            table.rows.add(pageData.client_billing_orders.map(cborder => [
                cborder.order_number,
                cborder.order_date,
                cborder.order_time,
                cborder.order_name,
                cborder.user,
                cborder.business_name,
                cborder.business_id,
                cborder.business_float_id,
                cborder.approved_for_client_billing_y_n,
                cborder.billing_type,
                cborder.order_status,
                cborder.invoice_number,
                cborder.payment_status,
                cborder.total,
                cborder.total_gift_cards,
                cborder.total_fulfilment,
                cborder.delivery_cost,
                cborder.gst,
                cborder.campaign,
                cborder.sender_profile,
                cborder.client_reference,
                cborder.po_number,
                cborder.additional_client_reference,
                cborder.order_level_activation_expiry,
                cborder.activation_expiry_set_for_this_order,
                cborder.client_payment_due_date,
            ])).draw(false);
        }
    }

    loadClientBillingOrdersForTable();
});