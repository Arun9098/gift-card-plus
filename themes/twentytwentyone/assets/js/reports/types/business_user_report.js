jQuery(document).ready(function($) {
    //console.log(ORDER_REPORT_VAL);
    const VAL     = BUSINESS_USER_REPORT_VAL;

    const table = $('#business_user_reportsTable').DataTable({
        columns: [
            { title: "User ID" },
            { title: "Status" },
            { title: "User Type" },
            { title: "First Name" },
            { title: "Surname" },
            { title: "Nickname/ Team Name" },
            { title: "Email" },
            { title: "Mobile" },
            { title: "Date of Birth" },
            { title: "State" },
            { title: "Business/ Consumer" },
            { title: "Business Name" },
            { title: "Business ID" },
            { title: "Business Billing Type" },
            { title: "Approved for Client Billing" },
            { title: "Business Float ID" },
            { title: "Business Website" },
            { title: "Business ABN" },
            { title: "Business Address Line 1" },
            { title: "Business Address Line 2" },
            { title: "Suburb" },
            { title: "State" },
            { title: "Country" },
            { title: "Post Code" },
            { title: "Business Currency" },
            { title: "User created date " },
            { title: "Float Balance" },
            { title: "Account Creation Type (Register/ Created by Admin)" },
            { title: "Time of Last Login" },
            { title: "Next Reminder Date" },
            { title: "Next Reminder" },
            { title: "Wishlist Items" },
            { title: "Wishlist updated date" },
            { title: "Email Preferences (Y/N)" },
            { title: "SMS Preferences (Y/N)" },
            { title: "Personalised Offers (Y/N)" },
            { title: "Events Celebrated " },
            { title: "Hobbies & Interests" },
            { title: "Cards in Wallet" },
            { title: "Card Name 1" },
            { title: "Card Number 1" },
            { title: "Card Denomination 1" },
            { title: "Card Status 1" },
            { title: "Card Name 2" },
            { title: "Card Number 2" },
            { title: "Card Denomination 2" },
            { title: "Card Status 2" },
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
                            return $('#business_user_reportsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
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
                            return $('#business_user_reportsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
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
                            return $('#business_user_reportsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
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
                            return $('#business_user_reportsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
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
                            return $('#business_user_reportsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#business_user_reportsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');
        
        const isDateColumn = index === 1;
        const isStatusColumn = index === 10;
    
        let inputField;
        //console.log(colSlug);
    
        if (1==2 && isDateColumn) {
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
    jQuery('#business_user_reportsTable thead').on('click', '.filter-icon', function (e) {
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

    jQuery('#business_user_reportsTable thead').on('change', '.status-filter', function () {
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
    jQuery('#business_user_reportsTable thead').on('keyup change', '.column-search', function () {
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
                    if (settings.nTable.id !== 'business_user_reportsTable') return true;
                
                    
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
    
        const isLastColumn = colIndex === $('#business_user_reportsTable thead th').length - 1;
    
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

    async function fetchUsers(page = 1, perPage = 50) {
        try {
            
            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });

            if (BUSINESS_USER_REPORT_VAL.permissions != 1) throw new Error('Not allowed');
            if (!response.ok) throw new Error('Failed to fetch Business Users');

            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error fetching Business Users:', error);
            return { users: [], total_users: 0 };
        }
    }

    async function load_usersForTable() {
        let page = 1;
        const perPage = 50;

        const firstPage = await fetchUsers(page, perPage);
        table.clear();
        table.rows.add(firstPage.users.map(ur => [
            ur.user_id,
            ur.status,
            ur.user_type,
            ur.first_name,
            ur.surname,
            ur.nickname_team_name,
            ur.email,
            ur.mobile,
            ur.date_of_birth,
            ur.state,
            ur.business_consumer,
            ur.business_name,
            ur.business_id,
            ur.business_billing_type,
            ur.approved_for_client_billing,
            ur.business_float_id,
            ur.business_website,
            ur.business_abn,
            ur.business_address_line_1,
            ur.business_address_line_2,
            ur.suburb,
            ur.state,
            ur.country,
            ur.post_code,
            ur.business_currency,
            ur.user_created_date,
            ur.float_balance,
            ur.account_creation_type,
            ur.time_of_last_login,
            ur.next_reminder_date,
            ur.next_reminder,
            ur.wishlist_items,
            ur.wishlist_updated_date,
            ur.email_preferences_y_n,
            ur.sms_preferences_y_n,
            ur.personalised_offers_y_n,
            ur.events_celebrated,
            ur.hobbies_interests,
            ur.cards_in_wallet,
            ur.card_name_1,
            ur.card_number_1,
            ur.card_denomination_1,
            ur.card_status_1,
            ur.card_name_2,
            ur.card_number_2,
            ur.card_denomination_2,
            ur.card_status_2,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_users / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchUsers(p, perPage);
            table.rows.add(pageData.users.map(ur => [
                ur.user_id,
                ur.status,
                ur.user_type,
                ur.first_name,
                ur.surname,
                ur.nickname_team_name,
                ur.email,
                ur.mobile,
                ur.date_of_birth,
                ur.state,
                ur.business_consumer,
                ur.business_name,
                ur.business_id,
                ur.business_billing_type,
                ur.approved_for_client_billing,
                ur.business_float_id,
                ur.business_website,
                ur.business_abn,
                ur.business_address_line_1,
                ur.business_address_line_2,
                ur.suburb,
                ur.state,
                ur.country,
                ur.post_code,
                ur.business_currency,
                ur.user_created_date,
                ur.float_balance,
                ur.account_creation_type,
                ur.time_of_last_login,
                ur.next_reminder_date,
                ur.next_reminder,
                ur.wishlist_items,
                ur.wishlist_updated_date,
                ur.email_preferences_y_n,
                ur.sms_preferences_y_n,
                ur.personalised_offers_y_n,
                ur.events_celebrated,
                ur.hobbies_interests,
                ur.cards_in_wallet,
                ur.card_name_1,
                ur.card_number_1,
                ur.card_denomination_1,
                ur.card_status_1,
                ur.card_name_2,
                ur.card_number_2,
                ur.card_denomination_2,
                ur.card_status_2,
            ])).draw(false);
        }
    }

    load_usersForTable();
});
