jQuery(document).ready(function($) {
    const VAL     = FLOAT_BALANCE_REPORT_VAL;

    const table = $('#float_balance_reportsTable').DataTable({
        columns: [
            { title: "Business Name" },
            { title: "Business ID" },
            { title: "Business Float ID" },
            { title: "Approved for Client Billing (Y/N)" },
            { title: "Business ABN" },
            { title: "Business Website" },
            { title: "Business Team Users IDs? " },
            { title: "Busineess Address Line 1" },
            { title: "Business Address Line 2" },
            { title: "Suburb" },
            { title: "State" },
            { title: "Country" },
            { title: "Postcode" },
            { title: "Business Currency" },
            { title: "Current Float Balance" },
            { title: "Top up notification amount" },
            { title: "Difference before topping up" },
        ],
        pageLength: 25, // default rows per page
        lengthChange: true,   // Enable page length dropdown (default is true)
        lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ],  // Dropdown options
        responsive: true,
        data: [],
        order: [[1, 'desc']],
        dom: 'lBfrtip',        // For buttons and length menu positioning with other controls
        buttons: [
            {
                extend: 'copy',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#float_balance_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#float_balance_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#float_balance_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#float_balance_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#float_balance_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#float_balance_reportsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');
     
        const isDateColumn = index === 1;
        const isStatusColumn = index === 9;
        const isBusinessNameCol = index === 0;
    
        let inputField;
        //console.log(colSlug);
    
        if (1==2 && isDateColumn) {
            console.log('colText....',colText);
            console.log('isDateColumn....',isDateColumn);
            // Two date inputs for From and To with clear 'X' button
            inputField = `
                <div style="position:relative; margin-bottom:4px;">
                    <label>From:<br>
                        <input type="date" class="column-search tetsttt date-from" data-col="${index}" style="width:calc(100% - 20px); padding:5px;">
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
        } else if (1==2 && isStatusColumn) {
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
        } else if (isBusinessNameCol) {
            inputField = `
                <div class="checkbox-group" data-col="${index}">
                    <!-- Checkboxes will be populated via AJAX -->
                    <p style="margin:0; font-size:12px;">Loading Business Names...</p>
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

    $(document).on('change', '#select-all-business-names', function() {
        var isChecked = $(this).is(':checked');
        console.log('"Select All" clicked. Checked:', isChecked);

        $('.col0-filter').prop('checked', isChecked).trigger('change');
        console.log('All individual checkboxes set to:', isChecked);
    });
    
    const filterBoxStates = {};
    jQuery('#float_balance_reportsTable thead').on('click', '.filter-icon', function (e) {
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
            
            if (colIndex === 0 && !filterBox.data('loaded')) {
                $.ajax({
                    url: reportsData.ajax_url,
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'get_all_businesses',
                        floatTable : 1,
                    },
                    success: function(response) {
                        console.log(response);
                        if (response && response.length) {

                            let checkboxes = `
                                <label style="display:block; margin-bottom:5px; font-weight:bold;">
                                    <input type="checkbox" id="select-all-business-names" class="col0-select-all"> Select All
                                </label>
                            `;
                            
                            checkboxes += response.map(business_names => `
                                <label style="display:block; margin-bottom:3px;">
                                    <input type="checkbox" name="business_name" class="col0-filter" value="${business_names}"> ${business_names}
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

    jQuery('#float_balance_reportsTable thead').on('change', '.col0-filter', function() {
        console.log('table works checkbox checking');

        //Uncheck the select all checkbox if any one of the uncheck
        var allChecked = $('.col0-filter').length === $('.col0-filter:checked').length;
        $('#select-all-business-names').prop('checked', allChecked);

        let selectedBusinessNames = jQuery('.col0-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex(jQuery(this).val().trim());
        }).get();
        
        console.log('selected names:', selectedBusinessNames);
        
        // selectedBusinessNames = selectedBusinessNames.map(name => 
        //     name === 'KenBusiness Test' ? 'KenBusinessTest' : name
        // );
        
        if (selectedBusinessNames.length) {
            let regex = '^(' + selectedBusinessNames.join('|') + ')$';
            table.column(0).search(regex, true, false).draw();
        } else {
            table.column(0).search('').draw();
        }
        
        // if (selectedBusinessName.length) {
        //     let regex = '^(' + selectedBusinessName.join('|') + ')$';
        //     table.column(0).search(regex, true, false).draw();
        // } else {
        //     table.column(0).search('').draw();
        // }
    });

    jQuery('#float_balance_reportsTable thead').on('change', '.status-filter', function () {
        let selectedStatuses = $('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex($(this).val()); // escape safely
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Draft|Processing|Completed)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            table.column(9).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            table.column(9).search('').draw();
        }
    });

    // Live filtering
    jQuery('#float_balance_reportsTable thead').on('keyup change', '.column-search', function () {
        const colIndex = $(this).data('col');
    
        if (1==2 && colIndex === 1) { // Date column: handle range filter
            // Find both From and To inputs for this date column
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const fromDate = $filterBox.find('.date-from').val();
            const toDate = $filterBox.find('.date-to').val();
    
            // Build a custom filter that filters rows based on date range
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'dateRangeFilter');
    
            if (fromDate || toDate) {
                $.fn.dataTable.ext.search.push(function dateRangeFilter(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'float_balance_reportsTable') return true;
                
                    
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
    
        const isLastColumn = colIndex === $('#float_balance_reportsTable thead th').length - 1;
    
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

    async function fetchFloatBuisnesses(page = 1, perPage = 50) {
        try {
            
            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });

            if (FLOAT_BALANCE_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
            if (!response.ok) throw new Error('Failed to fetch Float Businesses');

            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error fetching Float Businesses:', error);
            return { float_businesses: [], total_float_businesses: 0 };
        }
    }

    async function loadfloat_businessesForTable() {
        let page = 1;
        const perPage = 50;

        const firstPage = await fetchFloatBuisnesses(page, perPage);
        table.clear();
        table.rows.add(firstPage.float_businesses.map(fb => [
            fb.business_name,
            fb.business_id,
            fb.business_float_id,
            fb.approved_for_client_billing_y_n,
            fb.business_abn,
            fb.business_website,
            fb.business_team_users_ids,
            fb.busineess_address_line_1,
            fb.business_address_line_2,
            fb.suburb,
            fb.state,
            fb.country,
            fb.postcode,
            fb.business_currency,
            fb.current_float_balance,
            fb.top_up_notification_amount,
            fb.difference_before_topping_up,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_float_businesses / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchFloatBuisnesses(p, perPage);
            table.rows.add(pageData.float_businesses.map(fb => [
                fb.business_name,
                fb.business_id,
                fb.business_float_id,
                fb.approved_for_client_billing_y_n,
                fb.business_abn,
                fb.business_website,
                fb.business_team_users_ids,
                fb.busineess_address_line_1,
                fb.business_address_line_2,
                fb.suburb,
                fb.state,
                fb.country,
                fb.postcode,
                fb.business_currency,
                fb.current_float_balance,
                fb.top_up_notification_amount,
                fb.difference_before_topping_up,
            ])).draw(false);
        }
    }

    loadfloat_businessesForTable();
});
