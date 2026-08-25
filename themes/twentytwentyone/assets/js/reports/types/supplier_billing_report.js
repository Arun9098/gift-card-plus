jQuery(document).ready(function($) {
    jQuery.extend(jQuery.fn.dataTable.ext.type.order, {
        "date-dd-mm-yyyy-pre": function (dateStr) {
            if (!dateStr) return 0;
            const parts = dateStr.split('-');
            // Validate parts length (avoid NaN)
            if (parts.length !== 3) return 0;
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1; // months are 0-based
            const year = parseInt(parts[2], 10);
            const date = new Date(year, month, day);
            return date.getTime() || 0;
        }
    });
    //console.log(ORDER_REPORT_VAL);
    const VAL     = SUPPLIER_BILLING_REPORT_VAL;

    const table = $('#supplier_billing_reportsTable').DataTable({
        columns: [
            { title: "Supplier" },
            { title: "Order Number" },
            { title: "Supplier PO" },
            { title: "Order Date" },
            { title: "Order Status" },
            { title: "Gift Card Name" },
            { title: "Gift Card SKU" },
            { title: "Suppliier Gift Card SKU" },
            { title: "Gift Card Number" },
            { title: "Card Type (Variable/ Fixed)" },
            { title: "Gift Card Denomination" },
            { title: "Gift Card Cost Price" },
            { title: "Gift Card Supplier Fulfilment cost" },
            { title: "Gift Card Supplier Delivery cost" },
            { title: "Total Gift Card Buy Price" },
            { title: "GST" },
            { title: "Card Status" },
            { title: "Order Total Supplier Buy Price" },
            { title: "Order Total Supplier Fulfilment Price" },
            { title: "Order Total Supplier Delivery cost" },
            { title: "Order Total Supplier GST" },
            { title: "Order Total Supplier costs" },
            { title: "Supplier Payment Due" },
        ],
        columnDefs: [
            { type: "date-dd-mm-yyyy", targets: 3 }
        ],
        pageLength: 25, // default rows per page
        processing: true,  // enables the built-in “Processing…” element
        language: {
            processing: '<div class="dt-loader">Loading data, please wait...</div>'
        },
        lengthChange: true,   // Enable page length dropdown (default is true)
        lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ],  // Dropdown options
        responsive: true,
        data: [],
        order: [[1, 'desc']],  // Sort by Order Number so multi-card orders sit in adjacent rows (needed for the merge below)
        dom: 'lBfrtip',        // For buttons and length menu positioning with other controls
        buttons: [
            {
                extend: 'copy',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#supplier_billing_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#supplier_billing_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#supplier_billing_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#supplier_billing_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#supplier_billing_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#supplier_billing_reportsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');
        
        const isDateColumn = index === 3;
        const isStatusColumn = index === 4;
        const isSupplierColumn = index === 0;

        let inputField;
        //console.log(colSlug);

        if (isSupplierColumn) {
            // Populated once order data has loaded — see populateSupplierFilterCheckboxes().
            inputField = `<div class="supplier-checkboxes" data-col="${index}">
                <span class="supplier-filter-loading">Loading suppliers...</span>
            </div>`;
        } else if (isDateColumn) {
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
            <span class="filter-header-text">${colText}</span>
            <span class="filter-icon" data-col="${index}" style="cursor:pointer; width:16px; height:16px;">
               <i class="fa-solid fa-arrow-down"></i>
            </span>
            <div class="filter-box" data-head_slug="${colSlug}" data-col="${index}" style="display:none; background:white; border:1px solid #ccc; padding:5px; z-index:999;">
                ${inputField}
            </div>
        `);
    });

    const filterBoxStates = {};
    jQuery('#supplier_billing_reportsTable thead').on('click', '.filter-icon', function (e) {
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

    jQuery('#supplier_billing_reportsTable thead').on('change', '.status-filter', function () {
        let selectedStatuses = $('.status-filter:checked').map(function () {
            return jQuery.fn.dataTable.util.escapeRegex($(this).val()); // escape safely
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Draft|Processing|Completed)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            table.column(4).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            table.column(4).search('').draw();
        }
    });

    jQuery('#supplier_billing_reportsTable thead').on('change', '.supplier-filter', function () {
        let selectedSuppliers = $('.supplier-filter:checked').map(function () {
            return jQuery.fn.dataTable.util.escapeRegex($(this).val());
        }).get();

        if (selectedSuppliers.length) {
            // Build regex like: ^(BHN|GiftCardsPlus)$
            let regex = '^(' + selectedSuppliers.join('|') + ')$';
            table.column(0).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            table.column(0).search('').draw();
        }
    });

    // When an order has more than one gift card, its rows are adjacent (table is sorted by
    // Order Number). Visually merge the Order Number and Order Date cells across those rows
    // via rowspan, so the order-level info only shows once instead of repeating per card.
    // Called once after the full dataset has loaded (NOT on every draw/filter/page change) —
    // operates across ALL rows (not just the current page) via { page: 'all' }.
    function mergeOrderNumberAndDateCells(api) {
        const ORDER_NUMBER_COL = 1;
        const ORDER_DATE_COL = 3;
        const rows = api.rows({ page: 'all' }).nodes();
        const orderNumberData = api.column(ORDER_NUMBER_COL, { page: 'all' }).data();

        let spanStartRowIndex = 0;

        for (let i = 0; i <= orderNumberData.length; i++) {
            const isLastRow = i === orderNumberData.length;
            const sameAsPrevious = !isLastRow && i > 0 && orderNumberData[i] === orderNumberData[spanStartRowIndex];

            if (!sameAsPrevious) {
                const spanLength = i - spanStartRowIndex;

                if (spanLength > 1) {
                    const $firstRowCells = $(rows[spanStartRowIndex]).find('td');
                    $firstRowCells.eq(ORDER_NUMBER_COL).attr('rowspan', spanLength);
                    $firstRowCells.eq(ORDER_DATE_COL).attr('rowspan', spanLength);

                    for (let r = spanStartRowIndex + 1; r < i; r++) {
                        $(rows[r]).find('td').eq(ORDER_NUMBER_COL).remove();
                        $(rows[r]).find('td').eq(ORDER_DATE_COL - 1).remove();
                    }
                }

                spanStartRowIndex = i;
            }
        }
    }

    // Build the supplier checkbox list from the distinct supplier values actually present
    // in the loaded table data (suppliers vary per site, unlike the fixed order statuses).
    function populateSupplierFilterCheckboxes() {
        const $container = jQuery('.supplier-checkboxes[data-col="0"]');
        if (!$container.length) return;

        const suppliers = new Set();
        table.column(0).data().each(function (value) {
            const trimmed = (value || '').toString().trim();
            if (trimmed) suppliers.add(trimmed);
        });

        const sortedSuppliers = Array.from(suppliers).sort(function (a, b) {
            return a.localeCompare(b);
        });

        if (!sortedSuppliers.length) {
            $container.html('<span class="supplier-filter-empty">No suppliers found</span>');
            return;
        }

        const checkboxesHtml = sortedSuppliers.map(function (supplierName) {
            // Escape only for the HTML attribute — the label text is inserted raw since it's
            // rendered as HTML text content, not re-decoded, so it must not be double-escaped.
            const escapedAttr = jQuery('<div>').text(supplierName).html();
            return `<label style="display:block; margin-bottom:3px;">
                <input type="checkbox" name="supplier_name" class="supplier-filter" value="${supplierName}"> ${supplierName}
            </label>`;
        }).join('');

        $container.html(checkboxesHtml);
    }

    // Live filtering
    jQuery('#supplier_billing_reportsTable thead').on('keyup change', '.column-search', function () {
        const colIndex = $(this).data('col');
    
        if (colIndex === 3) { // Order Date column (Y-m-d)
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const fromDate = $filterBox.find('.date-from').val();
            const toDate = $filterBox.find('.date-to').val();
        
            // Remove previous date filter
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'dateRangeFilter');
        
            if (fromDate || toDate) {
                $.fn.dataTable.ext.search.push(function dateRangeFilter(settings, data) {
                    if (settings.nTable.id !== 'supplier_billing_reportsTable') return true;
        
                    const cellDateStr = data[colIndex];
                    if (!cellDateStr) return false;
        
                    // ✅ Parse Y-m-d (e.g., 2025-11-03)
                    const parts = cellDateStr.split('-');
                    if (parts.length !== 3) return false;
                    const [year, month, day] = parts.map(p => parseInt(p, 10));
        
                    // Invalid date check
                    if (isNaN(year) || isNaN(month) || isNaN(day)) return false;
        
                    const cellDate = new Date(year, month - 1, day);
                    const from = fromDate ? new Date(fromDate) : null;
                    const to = toDate ? new Date(toDate) : null;
        
                    // Normalize to midnight (strip time)
                    const strip = d => new Date(d.getFullYear(), d.getMonth(), d.getDate());
                    const cell = strip(cellDate);
                    const fromD = from ? strip(from) : null;
                    const toD = to ? strip(to) : null;
        
                    if (fromD && cell < fromD) return false;
                    if (toD && cell > toD) return false;
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
    
        const isLastColumn = colIndex === $('#supplier_billing_reportsTable thead th').length - 1;
    
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

            if (SUPPLIER_BILLING_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
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
            order.supplier,
            order.order_number,
            order.supplier_po,
            order.order_date,
            order.order_status,
            order.gift_card_name,
            order.gift_card_sku,
            order.suppliier_gift_card_sku,
            order.gift_card_number,
            order.card_type_variable_fixed,
            order.gift_card_denomination,
            order.gift_card_cost_price,
            order.gift_card_supplier_fulfilment_cost,
            order.gift_card_supplier_delivery_cost,
            order.total_gift_card_buy_price,
            order.gst,
            order.card_status,
            order.order_total_supplier_buy_price,
            order.order_total_supplier_fulfilment_price,
            order.order_total_supplier_delivery_cost,
            order.order_total_supplier_gst,
            order.order_total_supplier_costs,
            order.supplier_payment_due,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_orders / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchOrders(p, perPage);
            table.rows.add(pageData.orders.map(order => [
                order.supplier,
                order.order_number,
                order.supplier_po,
                order.order_date,
                order.order_status,
                order.gift_card_name,
                order.gift_card_sku,
                order.suppliier_gift_card_sku,
                order.gift_card_number,
                order.card_type_variable_fixed,
                order.gift_card_denomination,
                order.gift_card_cost_price,
                order.gift_card_supplier_fulfilment_cost,
                order.gift_card_supplier_delivery_cost,
                order.total_gift_card_buy_price,
                order.gst,
                order.card_status,
                order.order_total_supplier_buy_price,
                order.order_total_supplier_fulfilment_price,
                order.order_total_supplier_delivery_cost,
                order.order_total_supplier_gst,
                order.order_total_supplier_costs,
                order.supplier_payment_due,
            ])).draw(false);
        }

        populateSupplierFilterCheckboxes();
        mergeOrderNumberAndDateCells(table);
    }

    loadOrdersForTable();
});