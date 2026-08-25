jQuery(document).ready(function($) {

    const VAL     = CARDS_TRACKING_REPORT_VAL;

    // Extend DataTables to sort dd-mm-yyyy formatted dates correctly
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

    const table = $('#cards_tracking_reportsTable').DataTable({
        columns: [
            { title: "Order Date" },
            { title: "Gift Card Name" },
            { title: "Gift Card SKU" },
            { title: "Gift Card Denomination" },
            { title: "Gift Card Number" },
            { title: "Brand" },
            { title: "Order Number" },
            { title: "Order Name" },
            { title: "Order Status" },
            { title: "User" },
            { title: "Business" },
            { title: "Delivery date" },
            { title: "Delivery Time" },
            { title: "Delivery Method" },
            { title: "Delivery email" },
            { title: "Delivery SMS " },
            { title: "Card  Status" },
            { title: "Sender Profile" },
            { title: "Gift Card Activation Set (Y/N)" },
            { title: "Gift Card Activated (Y/N)" },
            { title: "Gift Card  Expiry Date" },
            { title: "Gift Card Activation Type" },
            { title: "Gift Card Activation Expiry Date" },
            { title: "Campaign" },
            { title: "Supplier" },
        ],
        columnDefs: [
            { type: "date-dd-mm-yyyy", targets: 0 } 
        ],
        pageLength: 25, 
        lengthChange: true,
        lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ],  
        responsive: true,
        data: [],
        order: [[0, 'desc']],
        dom: 'lBfrtip',  
        buttons: [
            {
                extend: 'copy',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        header: function (data, columnIdx) {
                            return $('#cards_tracking_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#cards_tracking_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#cards_tracking_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#cards_tracking_reportsTable thead th').eq(columnIdx).text().trim();
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
                            return $('#cards_tracking_reportsTable thead th').eq(columnIdx).text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#cards_tracking_reportsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');
        
        const isDateColumn = index === 0;
        const isSingleDateColumn = (index === 20 || index === 22);
        const isStatusColumn = index === 8;
        const isTimeColumn = index === 12; //actual 13
        const isOnlyDateColumn = index === 11;
    
        let inputField;
        //console.log(colSlug);
    
        if (isDateColumn) {
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
        } else if (isSingleDateColumn) {
            inputField = `
               <div style="position:relative; margin-bottom:4px;">
                    <label>Select Date<br>
                        <input type="datetime-local" class="column-search single-date" data-col="${index}" 
                            style="width:calc(100% - 20px); padding:5px;">
                        <span class="clear-date remove-single-date" 
                            style="position:absolute; right:11px; top:0px; cursor:pointer; color:red;">✕</span>
                    </label>
                </div>
            `;
        } else if (isOnlyDateColumn) {
            inputField = `
               <div style="position:relative; margin-bottom:4px;">
                    <label>Select Date<br>
                        <input type="date" class="column-search date-only" data-col="${index}" 
                            style="width:calc(100% - 20px); padding:5px;">
                        <span class="clear-date remove-only-date" 
                            style="position:absolute; right:11px; top:0px; cursor:pointer; color:red;">✕</span>
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
    jQuery('#cards_tracking_reportsTable thead').on('click', '.filter-icon', function (e) {
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


    jQuery('#cards_tracking_reportsTable thead').on('change', '.status-filter', function () {
        let selectedStatuses = $('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex($(this).val()); // escape safely
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Draft|Processing|Completed)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            table.column(8).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            table.column(8).search('').draw();
        }
    });

    // Live filtering
    jQuery('#cards_tracking_reportsTable thead').on('keyup change', '.column-search', function () {
        const colIndex = $(this).data('col');
    
        if (colIndex === 0) { 
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const fromDate = $filterBox.find('.date-from').val();
            const toDate = $filterBox.find('.date-to').val();
            console.log("From Date:", fromDate);
            console.log("To Date:", toDate);
            // Build a custom filter that filters rows based on date range
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'dateRangeFilter');
    
            if (fromDate || toDate) {
                $.fn.dataTable.ext.search.push(function dateRangeFilter(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'cards_tracking_reportsTable') return true;
                
                    
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
        
        if (colIndex === 20) { 
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const selectedDateTime = $filterBox.find('.date-on').val();
        
            // console.log("Searching exact DateTime:", selectedDateTime);
        
            // Remove old filter for this column
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'exactDateTimeFilter20');
        
            if (selectedDateTime) {
                $.fn.dataTable.ext.search.push(function exactDateTimeFilter20(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'cards_tracking_reportsTable') return true;
        
                    let cellValue = (data[colIndex] || '').trim();
                    if (!cellValue) return false;
        
                    // Handle possible tab or double space at the end
                    cellValue = cellValue.replace(/\s+/g, ' ').trim();
        
                    // Example cellValue: "13/11/2025 3:03 pm"
                    const match = cellValue.match(/^(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2}):(\d{2}) ?(am|pm)$/i);
                    if (!match) return false;
        
                    const [, day, month, year, hourStr, minuteStr, ampm] = match;
                    let hours = parseInt(hourStr, 10);
                    const minutes = parseInt(minuteStr, 10);
        
                    // Convert to 24-hour format
                    if (ampm.toLowerCase() === 'pm' && hours < 12) hours += 12;
                    if (ampm.toLowerCase() === 'am' && hours === 12) hours = 0;
        
                    const cellDate = new Date(`${year}-${month}-${day}T${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`);
                    const inputDate = new Date(selectedDateTime);
        
                    if (isNaN(cellDate) || isNaN(inputDate)) return false;
        
                    // Compare to the minute (ignore seconds)
                    const diff = Math.abs(cellDate.getTime() - inputDate.getTime());
                    return diff < 60000;
                });
            }
        
            table.draw();
            return;
        }

        if (colIndex === 22) {
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const selectedDateTime = $filterBox.find('.date-on').val();
        
            // console.log("Searching exact DateTime:", selectedDateTime);
        
            // Remove old filter for this column
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'exactDateTimeFilter20');
        
            if (selectedDateTime) {
                $.fn.dataTable.ext.search.push(function exactDateTimeFilter20(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'cards_tracking_reportsTable') return true;
        
                    let cellValue = (data[colIndex] || '').trim();
                    if (!cellValue) return false;
        
                    cellValue = cellValue.replace(/\s+/g, ' ').trim();
        
                    const match = cellValue.match(/^(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2}):(\d{2}) ?(am|pm)$/i);
                    if (!match) return false;
        
                    const [, day, month, year, hourStr, minuteStr, ampm] = match;
                    let hours = parseInt(hourStr, 10);
                    const minutes = parseInt(minuteStr, 10);
        
                    // Convert to 24-hour format
                    if (ampm.toLowerCase() === 'pm' && hours < 12) hours += 12;
                    if (ampm.toLowerCase() === 'am' && hours === 12) hours = 0;
        
                    const cellDate = new Date(`${year}-${month}-${day}T${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`);
                    const inputDate = new Date(selectedDateTime);
        
                    if (isNaN(cellDate) || isNaN(inputDate)) return false;
        
                    // Compare to the minute (ignore seconds)
                    const diff = Math.abs(cellDate.getTime() - inputDate.getTime());
                    return diff < 60000;
                });
            }
        
            table.draw();
            return;
        }

        if (colIndex === 11) { 
            // console.log('1111111');
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const selectedDate = $filterBox.find('.date-on').val(); // This is a date input (type="date")
        
            // Remove old filter for this column
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(
                (fn) => fn.name !== 'exactDateFilter11'
            );
        
            if (selectedDate) {
                $.fn.dataTable.ext.search.push(function exactDateFilter11(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'cards_tracking_reportsTable') return true;
        
                    const cellValue = (data[colIndex] || '').trim();
                    if (!cellValue) return false;
        
                    // Cell format: "2025-09-11"
                    // Input format: "2025-09-11"
                    const cellDate = new Date(cellValue);
                    const inputDate = new Date(selectedDate);
        
                    if (isNaN(cellDate) || isNaN(inputDate)) return false;
        
                    // Compare only by date (ignore time)
                    return (
                        cellDate.getFullYear() === inputDate.getFullYear() &&
                        cellDate.getMonth() === inputDate.getMonth() &&
                        cellDate.getDate() === inputDate.getDate()
                    );
                });
            }
        
            table.draw();
            return;
        }
        
        if (colIndex === 12) {
            console.log('12121212');
            
            const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
            const selectedTime = $filterBox.find('.time-only').val();
            console.log('selectedTime...',selectedTime);
        
            // Remove old filter for this column
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'exactTimeFilter12');
        
            if (selectedTime) {
                console.log('innc on');

                $.fn.dataTable.ext.search.push(function exactTimeFilter12(settings, data, dataIndex) {
                    console.log('innigkgukgyufgc on');
                    if (settings.nTable.id !== 'cards_tracking_reportsTable') return true;
        
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
    
        const isLastColumn = colIndex === $('#cards_tracking_reportsTable thead th').length - 1;
    
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

    // Clear the From date and reset table
    jQuery('.remove-date-from').on('click', function(e) {
        e.stopPropagation();
        const $input = jQuery(this).siblings('input.date-from');
        $input.val('');
        $input.trigger('change');
        table.draw();
    });

    // Clear the To date and reset table
    jQuery('.remove-date-to').on('click', function(e) {
        e.stopPropagation();
        const $input = jQuery(this).siblings('input.date-to');
        $input.val('');
        $input.trigger('change');
        table.draw();
    });

    jQuery('.remove-single-date').on('click', function(e) {
        e.stopPropagation();
        const $input = jQuery(this).siblings('input.single-date');
        $input.val('');
        $input.trigger('change');
        table.draw();
    });
    
    jQuery('.remove-only-date').on('click', function(e) {
        e.stopPropagation();
        const $input = jQuery(this).siblings('input.date-only');
        $input.val('');
        $input.trigger('change');
        table.draw();
    });
    
    jQuery('.remove-time').on('click', function(e) {
        e.stopPropagation();
        const $input = jQuery(this).siblings('input.time-only');
        $input.val('');
        $input.trigger('change');
        table.draw();
    });

    async function fetchGiftCards(page = 1, perPage = 50) {
        try {
            
            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });

            if (CARDS_TRACKING_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
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
            gc.order_date,
            gc.gift_card_name,
            gc.gift_card_sku,
            gc.gift_card_denomination,
            gc.gift_card_number,
            gc.brand,
            gc.order_no,
            gc.order_name,
            gc.order_status,
            gc.order_user,
            gc.business_name,
            gc.delivery_date,
            gc.delivery_time,
            gc.delivery_method,
            gc.delivery_email,
            gc.delivery_sms,
            gc.card_status,
            gc.sender_profile,
            gc.gift_card_activation_set_y_n,
            gc.gift_card_activated_y_n,
            gc.gift_card_expiry_date,
            gc.gift_card_activation_type,
            gc.gift_card_activation_expiry_date,
            gc.campaign,
            gc.supplier,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_giftcards / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchGiftCards(p, perPage);
            table.rows.add(pageData.giftcards.map(gc => [
                gc.order_date,
                gc.gift_card_name,
                gc.gift_card_sku,
                gc.gift_card_denomination,
                gc.gift_card_number,
                gc.brand,
                gc.order_no,
                gc.order_name,
                gc.order_status,
                gc.order_user,
                gc.business_name,
                gc.delivery_date,
                gc.delivery_time,
                gc.delivery_method,
                gc.delivery_email,
                gc.delivery_sms,
                gc.card_status,
                gc.sender_profile,
                gc.gift_card_activation_set_y_n,
                gc.gift_card_activated_y_n,
                gc.gift_card_expiry_date,
                gc.gift_card_activation_type,
                gc.gift_card_activation_expiry_date,
                gc.campaign,
                gc.supplier,
            ])).draw(false);
        }
    }

    loadGiftCardsForTable();
});
