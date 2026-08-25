jQuery(document).ready(function($) {
    //console.log(ORDER_REPORT_VAL);
    const VAL     = ACTIVATION_REPORT_VAL;

    const table = $('#activation_reportsTable').DataTable({
        columns: [
            { title: "Order created date" },
            { title: "Order Number" },
            { title: "Order Status" },
            { title: "User" },
            { title: "Business" },
            { title: "Card Name" },
            { title: "Card Denomination" },
            { title: "Card Number" },
            { title: "Card  Status" },
            { title: "Delivery date" },
            { title: "Delivery Time" },
            { title: "Delivery Method" },
            { title: "Delivery email" },
            { title: "Delivery SMS" },
            { title: "Recipient email" },
            { title: "Recipient Mobile" },
            { title: "Activation Required (Y/N)" },
            { title: "Activation Expiry Date" },
            { title: "Activated (Y/N)" },
            { title: "Date Activated" },
            { title: "Activation Date Missed (Y/N)" },
            { title: "Card Expiry Date" },
            { title: "Supplier" },
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
                            return $('#activation_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#activation_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#activation_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#activation_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#activation_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#activation_reportsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');
        
        const isDateColumn = index === 0;
        const isStatusColumn = index === 2;
    
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
    jQuery('#activation_reportsTable thead').on('click', '.filter-icon', function (e) {
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

    jQuery('#activation_reportsTable thead').on('change', '.status-filter', function () {
        let selectedStatuses = $('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex($(this).val()); // escape safely
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Draft|Processing|Completed)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            table.column(2).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            table.column(2).search('').draw();
        }
    });

    // Live filtering
    jQuery('#activation_reportsTable thead').on('keyup change', '.column-search', function () {
        const colIndex = $(this).data('col');
    
        if (colIndex === 0) { // Date column: handle range filter
            // Find both From and To inputs for this date column
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const fromDate = $filterBox.find('.date-from').val();
            const toDate = $filterBox.find('.date-to').val();
    
            // Build a custom filter that filters rows based on date range
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'dateRangeFilter');
    
            if (fromDate || toDate) {
                $.fn.dataTable.ext.search.push(function dateRangeFilter(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'activation_reportsTable') return true;
                
                    
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
    
        const isLastColumn = colIndex === $('#activation_reportsTable thead th').length - 1;
    
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

    async function fetchGiftCards(page = 1, perPage = 50) {
        try {

            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });
            if (ACTIVATION_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
            if (!response.ok) throw new Error('Failed to fetch giftcards');

            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error fetching giftcards:', error);
            return { giftcards: [], total_giftcards: 0 };
        }
    }

    async function loadGiftCardsForTable() {
        let page = 1;
        const perPage = 50;

        const firstPage = await fetchGiftCards(page, perPage);
        table.clear();
        table.rows.add(firstPage.giftcards.map(gc => [
            gc.order_created_date,
            gc.order_number,
            gc.order_status,
            gc.user,
            gc.business,
            gc.card_name,
            gc.card_denomination,
            gc.card_number,
            gc.card_status,
            gc.delivery_date,
            gc.delivery_time,
            gc.delivery_method,
            gc.delivery_email,
            gc.delivery_sms,
            gc.recipient_email,
            gc.recipient_mobile,
            gc.activation_required_y_n,
            gc.activation_expiry_date,
            gc.activated_y_n,
            gc.date_activated,
            gc.activation_date_missed_y_n,
            gc.card_expiry_date,
            gc.supplier,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_giftcards / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchGiftCards(p, perPage);
            table.rows.add(pageData.giftcards.map(gc => [
                gc.order_created_date,
                gc.order_number,
                gc.order_status,
                gc.user,
                gc.business,
                gc.card_name,
                gc.card_denomination,
                gc.card_number,
                gc.card_status,
                gc.delivery_date,
                gc.delivery_time,
                gc.delivery_method,
                gc.delivery_email,
                gc.delivery_sms,
                gc.recipient_email,
                gc.recipient_mobile,
                gc.activation_required_y_n,
                gc.activation_expiry_date,
                gc.activated_y_n,
                gc.date_activated,
                gc.activation_date_missed_y_n,
                gc.card_expiry_date,
                gc.supplier,
            ])).draw(false);
        }
    }

    loadGiftCardsForTable();
});
