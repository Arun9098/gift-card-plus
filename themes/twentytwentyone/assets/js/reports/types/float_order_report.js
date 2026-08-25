jQuery(document).ready(function ($) {
    jQuery.extend(jQuery.fn.dataTable.ext.type.order, {
        "date-dd-mm-yyyy-pre": function (dateStr) {
            if (!dateStr) return 0;
            const parts = dateStr.split('-');
            if (parts.length !== 3) return 0;
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const year = parseInt(parts[2], 10);
            const date = new Date(year, month, day);
            return date.getTime() || 0;
        }
    });
    const VAL     = FLOAT_ORDER_REPORT_VAL;


    const table = $('#float_order_reportsTable').DataTable({
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
        ],
        columnDefs: [
            { type: "date-dd-mm-yyyy", targets: 1 }
        ],
        pageLength: 25, // default rows per page
        lengthChange: true,   // Enable page length dropdown (default is true)
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],  // Dropdown options
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
                            return $('#float_order_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#float_order_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#float_order_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#float_order_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#float_order_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#float_order_reportsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');

        const isDateColumn = index === 1;
        const isStatusColumn = index === 10;
        const isTimeColumn = index === 2; //actual 3
        const isBusinessNameCol = index === 5;



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
        } else if (isTimeColumn) {
            inputField = `
                <div style="position:relative; margin-bottom:8px;">
                    <label>From:<br>
                        <input type="time" class="column-search time-only" data-col="${index}" 
                            style="width:calc(100% - 20px); padding:5px;">
                        <span class="clear-date remove-time" 
                            style="position:absolute; right:11px; top:0px; cursor:pointer; color:red;">✕</span>
                    </label>
                </div>
            `;
        } else if (isBusinessNameCol) {
            inputField = `
                <div class="checkbox-group" data-col="${index}">
                    <!-- Checkboxes will be populated via AJAX -->
                    <p style="margin:0; font-size:12px;">Loading Business Names...</p>
                </div>`;
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
    
    $(document).on('change', '#select-all-business-names', function() {
        var isChecked = $(this).is(':checked');
        console.log('"Select All" clicked. Checked:', isChecked);

        $('.col5-filter').prop('checked', isChecked).trigger('change');
        console.log('All individual checkboxes set to:', isChecked);
    });

    jQuery('#float_order_reportsTable thead').on('click', '.filter-icon', function (e) {
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

            if (colIndex === 5 && !filterBox.data('loaded')) {
                $.ajax({
                    url: reportsData.ajax_url,
                    method: 'GET',
                    dataType: 'json',
                    data: {
                    action: 'get_all_businesses'
                    },
                    success: function(response) {
                        console.log(response);
                        if (response && response.length) {

                            let checkboxes = `
                                <label style="display:block; margin-bottom:5px; font-weight:bold;">
                                    <input type="checkbox" id="select-all-business-names" class="col5-select-all"> Select All
                                </label>
                            `;
                            
                            checkboxes += response.map(business_names => `
                                <label style="display:block; margin-bottom:3px;">
                                    <input type="checkbox" name="business_name" class="col5-filter" value="${business_names}"> ${business_names}
                                </label>
                            `).join('');
                            filterBox.find('.checkbox-group').html(checkboxes);
                            filterBox.data('loaded', true);
                        }
                    },
                    error: function(err) {
                        console.error('Error fetching brands:', err);
                        filterBox.find('.checkbox-group').html('<p style="color:red;">Failed to load brands</p>');
                    }
                });
            }
        }

        // Prevent clicks inside from closing
        filterBox.off('click').on('click', function (e) {
            e.stopPropagation();
        });
    });

    jQuery('#float_order_reportsTable thead').on('change', '.col5-filter', function() {
        console.log('table works checkbox checking');

        //Uncheck the select all checkbox if any one of the uncheck
        var allChecked = $('.col5-filter').length === $('.col5-filter:checked').length;
        $('#select-all-business-names').prop('checked', allChecked);

        let selectedBusinessNames = jQuery('.col5-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex(jQuery(this).val().trim());
        }).get();
        
        console.log('selected names:', selectedBusinessNames);
        
        selectedBusinessNames = selectedBusinessNames.map(name => 
            name === 'KenBusiness Test' ? 'KenBusinessTest' : name
        );
        
        if (selectedBusinessNames.length) {
            let regex = '^(' + selectedBusinessNames.join('|') + ')$';
            table.column(5).search(regex, true, false).draw();
        } else {
            table.column(5).search('').draw();
        }
        
        // if (selectedBusinessName.length) {
        //     let regex = '^(' + selectedBusinessName.join('|') + ')$';
        //     table.column(5).search(regex, true, false).draw();
        // } else {
        //     table.column(5).search('').draw();
        // }
    });

    jQuery('#float_order_reportsTable thead').on('change', '.status-filter', function () {
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
    jQuery('#float_order_reportsTable thead').on('keyup change', '.column-search', function () {
        const colIndex = $(this).data('col');

        if (colIndex === 1) { // Date column: handle range filter
            // Find both From and To inputs for this date column
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const fromDate = $filterBox.find('.date-from').val();
            const toDate = $filterBox.find('.date-to').val();
            // console.log("From Date:", fromDate);
            // console.log("To Date:", toDate);
            // Build a custom filter that filters rows based on date range
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'dateRangeFilter');

            if (fromDate || toDate) {
                $.fn.dataTable.ext.search.push(function dateRangeFilter(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'float_order_reportsTable') return true;


                    const cellDateStr = data[colIndex];
                    if (!cellDateStr) return false;

                    // const cellDateRaw = new Date(cellDateStr);
                    const [day, month, year] = cellDateStr.split('-');
                    const cellDateRaw = new Date(`${year}-${month}-${day}`);

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

        if (colIndex === 2) {

            // console.log('222222');
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const selectedTime = $filterBox.find('.time-only').val();
            // console.log('selectedTime...', selectedTime);

            // Remove old filter for this column
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'exactTimeFilter12');

            if (selectedTime) {
                // console.log('innc on');

                $.fn.dataTable.ext.search.push(function exactTimeFilter12(settings, data, dataIndex) {
                    // console.log('innigkgukgyufgc on');
                    if (settings.nTable.id !== 'float_order_reportsTable') return true;

                    let cellValue = (data[colIndex] || '').trim();
                    if (!cellValue) return false;

                    // Normalize possible tab/space
                    cellValue = cellValue.replace(/\s+/g, ' ').trim();

                    const match = cellValue.match(/^(\d{1,2}):(\d{2}) ?(am|pm)$/i);
                    if (!match) return false;

                    const [, hourStr, minuteStr, ampm] = match;
                    let hours = parseInt(hourStr, 10);
                    const minutes = parseInt(minuteStr, 10);

                    // Convert to 24-hour
                    if (ampm.toLowerCase() === 'pm' && hours < 12) hours += 12;
                    if (ampm.toLowerCase() === 'am' && hours === 12) hours = 0;

                    const cellMinutes = hours * 60 + minutes;

                    // Convert input time (HH:mm)
                    const [inH, inM] = selectedTime.split(':').map(Number);
                    const inputMinutes = inH * 60 + inM;

                    // Allow small margin (±1 minute)
                    return Math.abs(cellMinutes - inputMinutes) <= 1;
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

        const isLastColumn = colIndex === $('#float_order_reportsTable thead th').length - 1;

        if (1 == 2 && isLastColumn && value) {
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

    // Clear the From date and reset table
    jQuery('.remove-date-from').on('click', function (e) {
        e.stopPropagation();
        const $input = jQuery(this).siblings('input.date-from');
        $input.val('');
        $input.trigger('change');
        table.draw();
    });

    // Clear the To date and reset table
    jQuery('.remove-date-to').on('click', function (e) {
        e.stopPropagation();
        const $input = jQuery(this).siblings('input.date-to');
        $input.val('');
        $input.trigger('change');
        table.draw();
    });

    jQuery('.remove-time').on('click', function (e) {
        e.stopPropagation();
        const $input = jQuery(this).siblings('input.time-only');
        $input.val('');
        $input.trigger('change');
        table.draw();
    });

    async function fetchFloatOrders(page = 1, perPage = 50) {
        try {
            
            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });

            if (FLOAT_ORDER_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
            if (!response.ok) throw new Error('Failed to fetch Float orders');

            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error fetching Float orders:', error);
            return { float_orders: [], total_float_orders: 0 };
        }
    }

    async function loadFloatOrdersForTable() {
        let page = 1;
        const perPage = 50;

        const firstPage = await fetchFloatOrders(page, perPage);
        table.clear();
        table.rows.add(firstPage.float_orders.map(forder => [
            forder.order_number,
            forder.order_date,
            forder.order_time,
            forder.order_name,
            forder.user,
            forder.business_name,
            forder.business_id,
            forder.business_float_id,
            forder.approved_for_client_billing_y_n,
            forder.billing_type,
            forder.order_status,
            forder.invoice_number,
            forder.payment_status,
            forder.total,
            forder.total_gift_cards,
            forder.total_fulfilment,
            forder.delivery_cost,
            forder.gst,
            forder.campaign,
            forder.sender_profile,
            forder.client_reference,
            forder.po_number,
            forder.additional_client_reference,
            forder.order_level_activation_expiry,
            forder.activation_expiry_set_for_this_order,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_float_orders / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchFloatOrders(p, perPage);
            table.rows.add(pageData.float_orders.map(forder => [
                forder.order_number,
                forder.order_date,
                forder.order_time,
                forder.order_name,
                forder.user,
                forder.business_name,
                forder.business_id,
                forder.business_float_id,
                forder.approved_for_client_billing_y_n,
                forder.billing_type,
                forder.order_status,
                forder.invoice_number,
                forder.payment_status,
                forder.total,
                forder.total_gift_cards,
                forder.total_fulfilment,
                forder.delivery_cost,
                forder.gst,
                forder.campaign,
                forder.sender_profile,
                forder.client_reference,
                forder.po_number,
                forder.additional_client_reference,
                forder.order_level_activation_expiry,
                forder.activation_expiry_set_for_this_order,
            ])).draw(false);
        }
    }

    loadFloatOrdersForTable();
});