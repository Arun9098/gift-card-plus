jQuery(document).ready(function ($) {
    let client_billing_approval = 0;

    jQuery('.nextTabBtn').on('click', function () {
        const tabContents = $('.tab-content');
        const currentTab = tabContents.filter(':visible').first(); // more robust than css('display')
    
        let currentIndex = tabContents.index(currentTab);
        let nextTab;
    
        // Find the next tab content that is not disabled (check both content AND its tab button)
        do {
            currentIndex++;
            nextTab = tabContents.eq(currentIndex);
    
            if (!nextTab.length) break;
    
            const nextTabId = nextTab.attr('id');
            const tabButton = $('[data-target="' + nextTabId + '"]');
    
            const contentDisabled = nextTab.hasClass('disable-for-user') || nextTab.prop('disabled') === true;
            const buttonDisabled = tabButton.hasClass('disable-for-user') || tabButton.prop('disabled') === true;
    
            if (contentDisabled || buttonDisabled) {
                nextTab = $();
            } else {
                break;
            }
        } while (currentIndex < tabContents.length);
    
        if (nextTab && nextTab.length) {
            const nextTabId = nextTab.attr('id');
            const tabButton = $('[data-target="' + nextTabId + '"]');

            if (tabButton.length && !tabButton.hasClass('disable-for-user') && !tabButton.prop('disabled')) {
                tabButton.trigger('click');
            } else {
                // fallback: manually switch if button not found
                switchToTab(nextTabId);
            }
        }
    });
    

    function switchToTab(target) {
        $('.user-detail-tab').removeClass('active-tab');
        $(`.user-detail-tab[data-target="${target}"]`).addClass('active-tab');

        $('.tab-content').hide();
        $(`#${target}`).show();

        if (jQuery('#approved_billing').prop('checked')) {
            // console.log('checkeddddd');
            client_billing_approval = 1;
        }else{
            // console.log('uncheckedd');
            client_billing_approval = 0;
        }

        // if (currentBusinessUserId) {
        //     if (target === 'orderHistoryContent') {
        //         $('.float-billing-extra-btn').hide();
        //         loadOrderHistory(currentBusinessUserId);
        //         orderHistoryLoadedUser = currentBusinessUserId;
        //     }
        // }
     
        // Handle order history tab directly without triggering click
        if (target === 'orderHistoryContent') {
            $('.float-billing-extra-btn').hide();
            loadOrderHistory(currentBusinessUserId);
        }

        
        if (currentUserId) {
            if (target === 'trackCardsContent') {
                // console.log('Inside the trackCardsContent');
                loadTrackCards(currentUserId);
                trackCardsLoadedUser = currentUserId;
            }
            if (target === 'userWalletContent') {
                loadUserWallet(currentUserId);
            }
        }
        toggleFloatBillingExtraBtn(parseInt(client_billing_approval));
    }

    function toggleFloatBillingExtraBtn(client_billing_approval = 0) {
        const isFloatBillingTabActive = $('.user-detail-tab.active-tab[data-target="floatBillingContent"]').length > 0;

        if (isFloatBillingTabActive && parseInt(client_billing_approval) <= 0) {
            // console.log('Im insid IF');
            $('.float-billing-extra-btn').show();
        } else {
            // console.log('Im insid Else');
            $('.float-billing-extra-btn').hide();
        }
    }

    const selectedUsers = new Set();
    let selectAllState = false;

    /*const table = $('#userTable').DataTable({
        processing: true,
        searching: true,
        serverSide: true,
        responsive: true,
        scrollX: true,
        pageLength: 5,
        lengthMenu: [5, 10, 25, 50],
        dom: 'rt<"bottom"lip>',
        pagingType: "full_numbers", // <<-- Added
        language: {
            search: "",
            searchPlaceholder: "Search users...",
            paginate: {
                previous: "‹",
                next: "›",
                first: "«",
                last: "»"
            }
        },
        drawCallback: function () {
            let api = this.api();
            const pageRows = api.rows({ page: 'current' }).nodes().to$();

            // Handle checkbox sync
            if ($("#selectAllUsers").length === 0) {
                $("#userTable thead th").eq(0).html('<input type="checkbox" id="selectAllUsers">');
            }

            let allChecked = true;
            pageRows.find('.user-checkbox').each(function () {
                const userId = $(this).val();
                const isSelected = selectedUsers.has(userId);

                $(this).prop("checked", isSelected);
                if (!isSelected) {
                    allChecked = false;
                }
            });

            $('#selectAllUsers').prop('checked', allChecked);

            // Enhanced pagination with ellipses
            var pagination = $('#userTable').closest('.dataTables_wrapper').find('.dataTables_paginate');
            var pageInfo = api.page.info();
            var currentPage = pageInfo.page + 1;
            var totalPages = pageInfo.pages;

            pagination.find('.ellipsis').remove(); // remove previous ellipsis

            if (totalPages > 7) {
                pagination.find('.paginate_button').each(function () {
                    var pageNum = parseInt($(this).text(), 10);
                    if (!isNaN(pageNum)) {
                        if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                            $(this).hide(); // Hide middle pages
                        }
                    }
                });

                if (currentPage < totalPages - 2) {
                    $('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                }
                if (currentPage > 3) {
                    $('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                }
            }
        }
    });*/


    //Search bar for recipient add Start here
    const recipientSearchInput = document.getElementById('recipientSearchInput');
    if (recipientSearchInput) {
        recipientSearchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            const recipients = document.querySelectorAll('.recipient-item');
            recipients.forEach(function (recipient) {
                const name = recipient.querySelector('.recipient-name').textContent.toLowerCase();
                const email = recipient.querySelector('.recipient-email').textContent.toLowerCase();
                const matches = name.includes(searchTerm) || email.includes(searchTerm);
                recipient.style.display = matches ? 'flex' : 'none';
            });
        });
    }

    $(document).on("change", ".user-checkbox", function () {
        const userId = $(this).val();
        if ($(this).prop("checked")) {
            selectedUsers.add(userId);
        } else {
            selectedUsers.delete(userId);
            selectAllState = false;
            $("#selectAllUsers").prop("checked", false);
        }
    });

    // Export button click
    $('#exportTable').click(function () {
        if (selectedUsers.size === 0) {
            $('.select-user-message').text("Please select users to export.").show();
            return;
        } else {
            $('.select-user-message').hide(); // Hide any previous message if users are selected
        }

        const rect = this.getBoundingClientRect();
        const popupHeight = $('#exportOptionsPopup').outerHeight();
        $('#exportOptionsPopup').css({
            position: 'fixed',
            top: rect.top - popupHeight - 10,
            left: rect.left
        }).fadeIn(200);
    });


    // Export actions
    function exportUsers(includePII) {
        const selectedArray = Array.from(selectedUsers);
        $.post(userListingData.ajax_url, {
            action: "export_users",
            user_ids: selectedArray,
            include_pii: includePII ? 1 : 0
        }, function (response) {
            const blob = new Blob([response], { type: "text/csv" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "exported_users.csv";
            link.click();
        });

        $('#exportOptionsPopup').fadeOut(200);
    }

    $('#exportWithPII').click(() => exportUsers(true));
    $('#exportWithoutPII').click(() => exportUsers(false));

    // Hide popup on outside click
    $(document).click(function (e) {
        if (!$(e.target).closest('#exportOptionsPopup, #exportTable').length) {
            $('#exportOptionsPopup').fadeOut(200);
        }
    });
    let currentUserId = null;
    let orderHistoryLoadedUser = null;
    let trackCardsLoadedUser = null; // Add this line

    const urlParams = new URLSearchParams(window.location.search);
    const userIdFromURL = urlParams.get('id');

    if (userIdFromURL) {
        // Simulate a click on view-user-details with the user ID
        const $tempViewBtn = $('<button class="view-user-details" style="display:none;" data-user-id="' + userIdFromURL + '"></button>');
        $('body').append($tempViewBtn);
        $tempViewBtn.trigger('click');
    }

    function renderRecipientAvatars() {
        const recipientElements = document.querySelectorAll('.recipient-item .recipient-name');
        const teamUsersContainer = document.querySelector('.team-users-scroll');
    
        if (!recipientElements.length || !teamUsersContainer) {
            console.warn('No recipients or container found.');
            return;
        }
    
        // Remove all existing avatars except the "Add" button
        const existingAvatars = teamUsersContainer.querySelectorAll('.team-user-avatar');
        existingAvatars.forEach(avatar => avatar.remove());
    
        recipientElements.forEach(recipient => {
            const fullName = recipient.textContent.trim();
            const nameParts = fullName.split(' ');
    
            let initials = '';
            if (nameParts.length >= 2) {
                initials = `${nameParts[0].charAt(0)}${nameParts[1].charAt(0)}`;
            } else if (nameParts.length === 1) {
                initials = nameParts[0].charAt(0);
            }
    
            const avatarDiv = document.createElement('div');
            avatarDiv.classList.add('team-user-avatar', 'initials-avatar');
            avatarDiv.textContent = initials.toUpperCase();
    
            const addButton = document.getElementById('showRecipientsBtn');
            if (addButton) {
                teamUsersContainer.insertBefore(avatarDiv, addButton);
            }
        });
    }

    let currentBusinessUserId = null;
    // let currentAdminUserId = null;

    $('#userDetailSection').on('click', '#back_to_users_list', function (e) {
        e.preventDefault();
        e.stopPropagation();

        $('.user-list-container, .save-next-buttons').show();
        $('#userTable_wrapper, #brandsSearchInput, #userRoleFilter, #exportTable, #createUserBtn, #saveUserBtn, #nextStepBtn').closest('div').show();
        $('#userListingTitle').show();

        // Show detail container
        $('#userDetailSection').hide();
    });

    function updateBalance() {
        const isChecked = $('#approved_billing').is(':checked');
        const userId = currentBusinessUserId; // Pass your business user ID from PHP
        $.ajax({
            url: userListingData.ajax_url, // localized AJAX URL
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_user_balance',
                user_id: userId,
                type: isChecked ? 'prepaid_limit' : 'float_balance'
            },
            beforeSend: function () {
                $('.float_balance .amount').text('Loading...');
            },
            success: function (response) {
                if (response.success) {
                    //jQuery('span.ttype').remove();
                    /*if( isChecked ){
                        jQuery('#payment-limit-display').append(' <span class="ttype">(Monthly)</span>');
                    }else{
                        jQuery('#payment-limit-display').append(' <span class="ttype">(Per Transaction)</span>');
                    }*/
                    $('.float_balance .amount').text('$' + response.data.balance);
                } else {
                    $('.float_balance .amount').text(' ');
                }
            },
            error: function () {
                $('.float_balance .amount').text(' ');
            }
        });
    }

    // Run on page load to set default balance
    updateBalance();

    // function toggleBalances() {
    //     var $amount = jQuery('.float_balance .amount');
    //     var $float  = $amount.find('.float-balance');
    //     var $pre    = $amount.find('.prepaid-credit');
    
    //     // Debug logs
    //     console.log('Float:', $float.length, 'Prepaid:', $pre.length, 'Checkbox checked:', jQuery('#approved_billing').prop('checked'));
    
    //     // Hide both first
    //     $float.add($pre).hide();
    
    //     // Show based on checkbox state
    //     if (jQuery('#approved_billing').prop('checked')) {
    //         $pre.show();
    //     } else {
    //         $float.show();
    //     }
    
    //     // If neither is visible, fallback to $0
    //     if ($amount.find(':visible').length === 0) {
    //         $amount.text('$0');
    //     }
    // }
    // toggleBalances();


    var orderHistoryTable; // define outside the function

    function loadOrderHistory(userId) {
        // console.log('loadOrderHistory.....',userId);

        if (!userId) {
            console.warn('User ID missing, skipping DataTable init');
            return;
        }
        orderHistoryTable = $('#order-history-table').DataTable({
            destroy: true,
            processing: true,
            serverSide: false,
            responsive: true,
            scrollX: false,
            ajax: {
                url: userListingData.ajax_url,
                type: 'POST',
                dataSrc: 'data',
                data: function (d) {
                    d.action = 'get_user_order_history';
                    d.user_id = userId;
                    d.custom_search = $('#order-history-search').val(); // Send custom search input
                }
            },
            columns: [
                { data: 'order_id' },
                { data: 'order_date' },
                { data: 'order_name' },
                { data: 'user_name' },
                { data: 'order_status' },
                { data: 'invoice_number' },
                { data: 'payment_status' },
                { data: 'total' },
                { data: 'campaign' },
                { data: 'client_reference' },
                { data: 'po_number' },
                { data: 'track_cards' }
            ],
            dom: 'rt<"bottom"lip>',
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50],
            language: {
                search: "Search orders:",
                lengthMenu: "Show _MENU_ orders per page",
                info: "Showing _START_ to _END_ of _TOTAL_ orders",
                paginate: {
                    previous: "«",
                    next: "»"
                }
            }
        });
    }
    function disableTabs() {
        const tabs = [
            '#tabBusinessProfile',
            // 'button[data-target="orderHistoryContent"]',
            'button[data-target="floatBillingContent"]',
            // 'button[data-target="trackCardsContent"]'
        ];
    
        tabs.forEach(selector => {
            const $el = $(selector);
            // Remove the class first if it exists
            if ($el.hasClass('disable-for-user')) {
                $el.removeClass('disable-for-user').prop('disabled', false);
            }
            // Then disable and add class
            $el.prop('disabled', true).addClass('disable-for-user');
        });
    }
    $(document).on('click', '.view-user-details', function (e) {
        // console.log('1');
        // setTimeout(() => {
        //     toggleBalances();
        // }, 2000);
        setTimeout(() => {
            // console.log('Triggering #showRecipientsBtn click');
            const showRecipientsBtn = document.getElementById('showRecipientsBtn');
            if (showRecipientsBtn) {
                showRecipientsBtn.click();
            }
        }, 500);

        // Hide the recipient list after 2 seconds
        setTimeout(() => {
            // console.log('Hiding #recipientList');
            const recipientList = document.getElementById('recipientList');
            if (recipientList) {
                recipientList.style.display = 'none';
            }
        }, 1000);

        // Run once after initial load
        setTimeout(renderRecipientAvatars, 2000);
        
        
        e.preventDefault();
        e.stopPropagation(); // Add this to prevent any parent handlers

        const userId = $(this).data('user-id');

         // Make the AJAX request to WordPress
        $.ajax({
            url: userListingData.ajax_url,
            method: 'POST',
            data: {
                action: 'check_user_role',
                user_id: userId
            },
            success: function(response) {
                const hasAllowedRole = response.data.has_allowed_role;
                console.log('Has allowed role:', hasAllowedRole);
        
                if (!hasAllowedRole) {
                    disableTabs(); // disable tabs only if user does NOT have allowed roles
                } else {
                    // If user has allowed roles, remove any previous disabled state
                    const tabs = [
                        '#tabBusinessProfile',
                        // 'button[data-target="orderHistoryContent"]',
                        'button[data-target="floatBillingContent"]',
                        // 'button[data-target="trackCardsContent"]'
                    ];
                    tabs.forEach(selector => {
                        $(selector).prop('disabled', false).removeClass('disable-for-user');
                    });
                }
                
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
            }
        });
        
        const businessUserId = $(this).data('business-user-id');
        // console.log('1.5.2',businessUserId);

        const adminUserId = $(this).data('admin-user-id');

        // console.log('userId=================', userId);
        const $commLink = $('.comm-link');
        const baseUrl = 'email-logs/?user_id='; // Replace with your actual base URL
        $commLink.attr('href', baseUrl + userId);
        // console.log('userId=================', $commLink);

        currentUserId = userId;
        currentBusinessUserId = businessUserId;
        currentAdminUserId = adminUserId;

        orderHistoryLoadedUser = null;
        trackCardsLoadedUser = null;
        
        $('.user-detail-tab').on('click', function () {
            // console.log('2');
            $('.user-detail-tab').removeClass('active-tab');
            $(this).addClass('active-tab');
    
            $('.tab-content').hide();
            const target = $(this).data('target');
            $(`#${target}`).show();
            // let currentBusinessUserId = jQuery('.view-user-details').data('business-user-id');
            // console.log('2.5',currentBusinessUserId);
    
            if (currentBusinessUserId) {
                // console.log('3');
                // Always load order history when tab is clicked
                if (target === 'orderHistoryContent') {
                    $('.float-billing-extra-btn').hide();
                    loadOrderHistory(currentBusinessUserId);
                    orderHistoryLoadedUser = currentBusinessUserId;
                }
            } else if(!currentBusinessUserId){
                currentBusinessUserId = currentUserId;
                // console.log('3');
                // console.log('3.5',currentUserId);
                // Always load order history when tab is clicked
                if (target === 'orderHistoryContent') {
                    $('.float-billing-extra-btn').hide();
                    loadOrderHistory(currentBusinessUserId);
                    orderHistoryLoadedUser = currentBusinessUserId;
                }
                // console.log('in inside thwe log......');
            }
            if (currentUserId) {
                // console.log('4');
                // Always load track cards when tab is clicked
                if (target === 'trackCardsContent') {
                    loadTrackCards(currentUserId);
                    // console.log('currentUserIdcurrentUserId,',currentUserId);
                    trackCardsLoadedUser = currentUserId;
                }
            }
        });

        // let orderHistoryTable;

        // 🔍 Custom search by Order ID or User Name
        $('#order-history-search').on('keyup', function () {
            if (orderHistoryTable) {
                orderHistoryTable.ajax.reload();
            }
        });
        // Order history tab filteration code START here --------------------
        
        $('#order-history-table thead th').each(function (index) {
            const colText = $(this).text().trim();
            const isCheckboxCol = index === 0; 
            const isDateColumn = index === 1;
            const isStatusCol = index === 4;
            const isPaymentStatusCol = index === 6;
            const isDetailsCol = index === 6;  
            const isLastCol = index === $('#order-history-table thead th').length - 1; // Last column
            const colSlug = $(this).data('head_slug');

    
            // console.log('colSlug isssss : ',colSlug);
            let inputField = '';
        
            if (isStatusCol) {
                inputField = `
                    <div class="status-checkboxes" data-col="${index}">
                        <label style="display:block; margin-bottom:3px;">
                            <input type="checkbox" name="o_status" class="status-filter" value="Pending"> Pending
                        </label>
                        <label style="display:block; margin-bottom:3px;">
                            <input type="checkbox" name="o_status" class="status-filter" value="Completed"> Completed
                        </label>
                    </div>
                `;
            } else if(isPaymentStatusCol){
                inputField = `
                    <div class="status-checkboxes" data-col="${index}">
                        <label style="display:block; margin-bottom:3px;">
                            <input type="checkbox" name="o_payment" class="status-filter" value="Pending"> Pending
                        </label>
                        <label style="display:block; margin-bottom:3px;">
                            <input type="checkbox" name="o_payment" class="status-filter" value="Received"> Received
                        </label>
                    </div>
                `;
            } else if (isDateColumn) {
                // Two date inputs for From and To with clear 'X' button
                inputField = `
                    <div style="position:relative; margin-bottom:4px;">
                        <label>From:<br>
                            <input type="date" class="column-search date-from" data-col="${index}" style="width:calc(100% - 20px); padding:5px;">
                            <span class="clear-date remove-date-from" style="position:absolute; right:11px; top:0px; cursor:pointer; color:red;">&#x2715;</span>
                        </label>
                    </div>
                    <div style="position:relative;">
                        <label>To:<br>
                            <input type="date" class="column-search date-to" data-col="${index}" style="width:calc(100% - 20px); padding:5px;">
                            <span class="clear-date remove-date-to" style="position:absolute; right:11px; top:0px; cursor:pointer; color:red;">&#x2715;</span>
                        </label>
                    </div>
                `;
            } else {
                inputField = `<input type="text" class="column-search" data-col="${index}" 
                    placeholder="Search..." style="width:100%; padding:5px;">`;
            }

            // Decide which icons to show
            let iconHtml = '';
            if (isCheckboxCol) {
                // First column → only down arrow
                iconHtml = `<i class="fa-solid fa-arrow-down"></i>`;
            } else if (!isLastCol) {
                // Middle columns → both icons
                iconHtml = `
                    <i class="fa-solid fa-arrow-down"></i>
                    <i class="dashicons dashicons-filter"></i>
                `;
            }
        
            // ✅ Inject directly into TH, not into dataTables_sizing
            $(this).html(`
                ${colText}
                ${iconHtml ? `<span class="filter-icon" data-col="${index}" style="cursor:pointer; width:16px; height:16px;">${iconHtml}</span>` : ''}
                ${inputField ? `
                    <div class="filter-box" data-head_slug="${colSlug}" data-col="${index}" 
                        style="display:none; background:white; border:1px solid #ccc; padding:5px; z-index:999;">
                        ${inputField}
                    </div>` : ''}
            `);
        });
        jQuery('.remove-date-from').on('click', function(e) {
            e.stopPropagation();
            const $input = jQuery(this).siblings('input.date-from');
            $input.val('');
            $input.trigger('change');
            orderTable.draw();
        });
        // Clear the To date and reset table
        jQuery('.remove-date-to').on('click', function(e) {
            e.stopPropagation();
            const $input = jQuery(this).siblings('input.date-to');
            $input.val('');
            $input.trigger('change');
            orderTable.draw();
        });
        const ordersFilterBoxStates = {};
        $('#order-history-table thead').on('click', '.filter-icon', function(e) {
            e.stopPropagation();
            const colIndex = $(this).data('col');
            const filterBox = $(`#order-history-table thead .filter-box[data-col="${colIndex}"]`);
            const isOpen = ordersFilterBoxStates[colIndex];
    
            if (isOpen) {
                filterBox.hide().removeClass('active_filter');
                filterBox.parent().removeClass('active_filter_wrapper');
                ordersFilterBoxStates[colIndex] = false;
            } else {
                filterBox.show().addClass('active_filter');
                filterBox.parent().addClass('active_filter_wrapper');
                ordersFilterBoxStates[colIndex] = true;
            }
            // Prevent clicks inside filter box from closing
            filterBox.off('click').on('click', function(e) {
                e.stopPropagation();
            });
        });

        $('#order-history-table thead').on('keyup change', '.column-search', function () {
            const colIndex = jQuery(this).data('col');

            // For date field filter
            if (colIndex === 1) { // Date column: handle range filter
                // Find both From and To inputs for this date column
                const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);
                const fromDate = $filterBox.find('.date-from').val();
                const toDate = $filterBox.find('.date-to').val();
        
                // Build a custom filter that filters rows based on date range
                $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter((fn) => fn.name !== 'dateRangeFilter');
        
                if (fromDate || toDate) {
                    $.fn.dataTable.ext.search.push(function dateRangeFilter(settings, data, dataIndex) {
                        if (settings.nTable.id !== 'order-history-table') return true;
                    
                        
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
        
                orderHistoryTable.draw();
                return;
            }
            function stripTime(date) {
                return new Date(date.getFullYear(), date.getMonth(), date.getDate());
            }


            const searchValue = this.value;
            // orderTable.draw();
            // console.log('searchValue',searchValue);
            // console.log('colIndex',colIndex);
    
            if (orderHistoryTable) { // make sure it's initialized
                orderHistoryTable.column(colIndex).search(searchValue).draw();
            }
        });

        $('#order-history-table thead').on('change', '.status-filter', function () {
            let colIndex = $(this).closest('.status-checkboxes').data('col');

            // Collect all checked values for that column
            let selectedValues = $(`.status-checkboxes[data-col="${colIndex}"] .status-filter:checked`)
                .map(function () {
                    return $.fn.dataTable.util.escapeRegex($(this).val());
                }).get();
        
            if (selectedValues.length) {
                // Build regex like: ^(Pending|Completed)$
                let regex = '^(' + selectedValues.join('|') + ')$';
                orderHistoryTable.column(colIndex).search(regex, true, false).draw();
            } else {
                // Reset when nothing is checked
                orderHistoryTable.column(colIndex).search('').draw();
            }
        });
        // Order history tab filteration code END here --------------------

        // Hide user list section
        $('.user-list-container, .save-next-buttons').hide();
        $('#userTable_wrapper, #brandsSearchInput, #userRoleFilter, #exportTable, #createUserBtn, #saveUserBtn, #nextStepBtn').closest('div').hide();
        $('#userListingTitle').hide();

        // Show detail container
        $('#userDetailSection').show();

        // Set active tab
        $('.user-detail-tab').removeClass('active-tab');
        $('#tabUserProfile').addClass('active-tab');
        $('.tab-content').hide();
        $('#userProfileContent').show();
        $('.float-billing-extra-btn').hide();

        // Hide forms while loading
        $('#userProfileForm, #businessProfileForm').hide();
        $('#userProfileContent').append('<div class="loading-user-details" id="loadingUserData" style="padding: 2rem; text-align:center;">Loading user details...</div>');
        $('#businessProfileContent').append('<div class="loading-user-details" id="loadingBusinessData" style="padding: 2rem; text-align:center;">Loading business details...</div>');

        // First check if this is a business user
        $.ajax({
            url: userListingData.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'check_user_type',
                user_id: userId
            },
            success: function (response) {
                // if (response.success && (response.data.is_business_user || response.data.is_admin)) {
                if (response.success) {
                    // console.log('From here it is called ');
                    // window.currentlyViewedBusinessUserId = userId;
                    // window.currentlyViewedBusinessUserData = response.data.business_data || null;
                    // if (response.data.is_business_user === true || response.data.is_admin === true) {
                        // console.log('Business user confirmed:', response.data);
                        $('#tabBusinessProfile').show();
                        $('.float-billing-extra-btn').hide();
                    // } else {
                    //     $('#tabBusinessProfile').hide();
                    //     $('#businessProfileContent').remove();
                    // }
                } else {
                    $('#tabBusinessProfile').off('click').on('click', function (e) {
                        e.preventDefault();

                        // Set active tab styles (if necessary)
                        $('.user-detail-tab').removeClass('active-tab');
                        $(this).addClass('active-tab');

                        // Hide other content sections
                        $('.tab-content').hide();

                        // Show message in businessProfileContent
                        $('#businessProfileContent').html('<div style="padding:1rem; color:red;">You are not allowed to access the Business Profile tab.</div>').show();
                    });
                }
                // Continue with loading user details
                // loadUserDetails(userId);
            }
        });
        console.log('userId',userId);
        // Fetch user data
        $.ajax({
            url: userListingData.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'get_user_profile_details',
                user_id: userId
            },
            success: function (user) {
                // console.log('user........',user.business_user_id);
                // console.log(user);
                if (!user || !user.user_id) {
                    $('#userProfileContent').html('<div class="error-message" style="color: red; text-align: center;">Failed to load user details.</div>');
                    return;
                }

                // Populate the user form
                const $form = $('#userProfileForm');
                const roleName = user.role_name || user.role || 'Unknown';
           
                $form.find('[name="user_id"]').val(user.user_id);
                $form.find('[name="business_user_id"]').val(businessUserId);
                $('#user_id_display').val(user.user_id);
                $form.find('[name="first_name"]').val(user.first_name);
                $form.find('[name="last_name"]').val(user.last_name);
                $form.find('[name="nickname_team"]').val(user.nickname_team);
                $form.find('[name="email"]').val(user.email);
                $form.find('[name="mobile"]').val(user.mobile);
                $form.find('[name="dob"]').val(user.dob);
                $form.find('[name="state"]').val(user.state);
                $form.find('[name="join_date"]').val(user.join_date);
                $form.find('[name="work_anniversary"]').val(user.work_anniversary);
                $form.find('[name="user_type"]').val(roleName);
                const $lastLoginInput = $form.find('[name="last_login_date"]');
                if (user.last_login) {
                    $lastLoginInput.attr('type', 'date').val(user.last_login);
                } else {
                    $lastLoginInput.attr('type', 'text').val('Not Logged In');
                }



                // console.log(user.first_name);

                if (user.business_name) {
                    // console.log(user.business_name);
                    // console.log(user.first_name);
                    $('.user-business-name').html('<strong>' + user.business_name + '</strong>: <span class="user-name">  ' + user.first_name + ' ' + user.last_name + '</span>');
                } else {
                    $('.user-business-name').html('<strong>' + user.first_name + ' ' + user.last_name + '</strong>');
                }

                // console.log('userrrrrrrrr.....',user);
                let balanceText = '';

                if (user.float_balance) {
                    // console.log('float_balance');
                    balanceText += '<span class="float-balance" style="display:none">$' + user.float_balance + '</span> ';
                }
                
                if (user.prepaid_credit) {
                    // console.log('prepaid_credit');
                    balanceText += '<span class="prepaid-credit" style="display:none">$' + user.prepaid_credit + '</span>';
                }
                
                if (balanceText === '') {
                    // console.log('elsee.....');
                    balanceText = '$0';
                }

                setTimeout(() => {
                    // console.log('float_balance...');
                    $('.float_balance .amount').html(balanceText);
                }, 200);
                
                setTimeout(() => {
                    // console.log('after resonse');
                    updateBalance();
                }, 2000);

                // Remove loader and show form
                $('#loadingUserData').remove();
                $form.show();

                // Now load business data
                loadBusinessProfileData(businessUserId);
            },
            error: function () {
                $('#userProfileContent').html('<div class="error-message" style="color: red; text-align: center;">Error loading user details.</div>');
            }
        });
    });

    //  Reset Password (Set New Password directly)
    $('#resetPasswordBtn').on('click', function () {
        const userId = $('#user_id_hidden').val();
        const newPassword = $('[name="new_password"]').val();
        const $messageDiv = $('.reset-password-success-message');
        $messageDiv.text('').removeClass('success error');

        if (!newPassword || newPassword.length < 6) {
            $messageDiv
                .text('Password must be at least 6 characters.')
                .addClass('error');
            return;
        }

        $.post(userListingData.ajax_url, {
            action: 'admin_reset_user_password',
            user_id: userId,
            new_password: newPassword,
            nonce: userListingData.nonce
        }, function (res) {
            if (res.success) {
                $messageDiv
                    .text('Password reset successfully!')
                    .addClass('success');
            } else {
                $messageDiv
                    .text(res.data || 'An error occurred while resetting the password.')
                    .addClass('error');
            }
        });
    });

    // 2. Send Reset Password Link
    $('#sendResetLink').on('click', function (e) {
        e.preventDefault();
        const userId = $('#user_id_hidden').val();
        const $messageDiv = $('.reset-password-success-message');
        $messageDiv.text('').removeClass('success error');

        $.post(userListingData.ajax_url, {
            action: 'send_user_password_reset_link',
            user_id: userId
        }, function (res) {
            if (res.success) {
                $messageDiv
                    .text('Reset link sent to user email.')
                    .addClass('success');
            } else {
                $messageDiv
                    .text(res.data || 'An error occurred while sending the reset link.')
                    .addClass('error');
            }
        });
    });
    //Add new user to the business popup Start-------------

    const addBtn = document.querySelector('.add-user-button');
    const modal = document.getElementById('addRecipientModal');
    const closeBtn = document.querySelector('.close-recipient-modal');
    const submitBtn = document.getElementById('submitRecipientEmail');
    const emailInput = document.getElementById('recipientEmail');
    // const emailInput = document.getElementById('recipientEmail');
    const suggestionsBox = document.getElementById('emailSuggestions');

    const statusSpan = document.getElementById('emailStatus');
    // const suggestionsBox = document.getElementById('emailSuggestions');

    if (addBtn) {
        addBtn.addEventListener('click', () => {
            modal.style.display = 'block';
            jQuery('.add-user-in-business').hide();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
            clearForm();
        });
    }

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            clearForm();
        }
    });

    // submitBtn.addEventListener('click', () => {
    // const email = emailInput.value.trim();
    // if (!email) {
    //     alert("Please enter an email address.");
    //     return;
    // }
    // console.log("Submitted Email:", email);
    // modal.style.display = 'none';
    // clearForm();
    // });

    // Clear form fields and UI
    function clearForm() {
        // Clear email input and related UI
        document.getElementById('recipientEmail').value = '';
        const statusSpan = document.getElementById('emailStatus');
        statusSpan.textContent = '';
        statusSpan.className = 'email-status';

        const suggestionsBox = document.getElementById('emailSuggestions');
        suggestionsBox.style.display = 'none';
        suggestionsBox.innerHTML = '';

        // Clear other inputs inside user-details-fields
        document.getElementById('recipientFirstName').value = '';
        document.getElementById('recipientLastName').value = '';
        // document.getElementById('recipientUserID').value = '';
        document.getElementById('recipientBusiness').value = '';
        document.getElementById('recipientRole').value = '';

        // Hide the entire container with all user detail fields
        const userDetailsFields = document.querySelector('.user-details-fields');
        if (userDetailsFields) {
            userDetailsFields.style.display = 'none';
        }

        // Also hide the Add New User button, if you want
        const addNewUserBtn = document.getElementById('addNewUserBtn');
        if (addNewUserBtn) {
            addNewUserBtn.style.display = 'none';
        }
    }


    // Fetch users on keyup (debounce recommended for production)
    jQuery('.user-details-fields').hide();


    emailInput.addEventListener('keyup', function () {
        const email = this.value.trim();
        const addNewUserBtn = document.getElementById('addNewUserBtn');
        const statusSpan = document.getElementById('emailStatus');


        // Clear fields
        document.getElementById('recipientFirstName').value = '';
        document.getElementById('recipientLastName').value = '';
        // document.getElementById('recipientUserID').value = '';
        document.getElementById('recipientBusiness').value = '';
        document.getElementById('recipientRole').value = '';
        jQuery('.user-details-fields').hide();
        addNewUserBtn.style.display = 'none'; // Hide add button initially

        if (email.length === 0) {
            suggestionsBox.style.display = 'none';
            statusSpan.textContent = '';
            // jQuery('.user-details-fields').hide();
            statusSpan.className = 'email-status';
            return;
        }

        statusSpan.className = 'email-status loading';
        statusSpan.textContent = 'Searching...';

        fetch(`/wp-admin/admin-ajax.php?action=search_user_emails&term=${encodeURIComponent(email)}`)
            .then(res => res.json())
            .then(users => {
                suggestionsBox.innerHTML = '';
                if (users.length > 0) {
                    users.forEach(user => {
                        // console.log(user);
                        const li = document.createElement('li');
                        li.textContent = `${user.user_email} (${user.first_name} ${user.last_name})`;
                        li.dataset.email = user.user_email;
                        li.dataset.firstname = user.first_name;
                        li.dataset.lastname = user.last_name;
                        li.dataset.userid = user.ID;
                        li.dataset.business = user.business_name;
                        li.dataset.roleslug = user.role_slug;
                        suggestionsBox.appendChild(li);
                    });
                    suggestionsBox.style.display = 'block';
                    statusSpan.className = 'email-status success';
                    statusSpan.textContent = `${users.length} user(s) found`;
                    addNewUserBtn.style.display = 'none';
                } else {
                    suggestionsBox.style.display = 'none';
                    statusSpan.className = 'email-status error';
                    statusSpan.textContent = 'No users found';
                    addNewUserBtn.style.display = 'inline-block'; // Show Add button
                }
            })
            .catch(() => {
                suggestionsBox.style.display = 'none';
                statusSpan.textContent = 'Error fetching';
                statusSpan.className = 'email-status error';
                addNewUserBtn.style.display = 'none';
            });
    });

    addNewUserBtn.addEventListener('click', () => {
        // Pre-fill only the email field
        const email = emailInput.value.trim();

        document.getElementById('recipientFirstName').value = '';
        document.getElementById('recipientLastName').value = '';
        // document.getElementById('recipientUserID').value = '';

        let businessID = parseInt(jQuery('#businessProfileForm input[name="business_user_id"]').val());
        if( jQuery('#businessProfileForm input[name="profile_business_user"]').val() == 'true' ){
            businessID = parseInt(jQuery('#businessProfileForm input[name="user_id"]').val());
        }

        const businessInput = document.getElementById('recipientBusiness');
        businessInput.value = '';
        businessInput.removeAttribute('readonly'); // Make it editable
        businessInput.classList.remove('readonly'); // Remove styling class if needed

        if( businessID > 0 ){
            jQuery('.user-details-fields #recipientBusiness').val(businessID).trigger('change');
        }
        document.getElementById('recipientRole').value = '';
        emailInput.value = email;

        jQuery('.user-details-fields').show();
        document.getElementById('emailStatus').textContent = 'Enter details for new user';
        document.getElementById('emailStatus').className = 'email-status info';
        setTimeout(() => {
            jQuery('#addNewUserBtn').hide();
        }, 50);    });

    // Handle suggestion click
    // Handle suggestion click
    suggestionsBox.addEventListener('click', function (e) {
        if (e.target.tagName === 'LI') {
            const li = e.target;
            emailInput.value = li.dataset.email;
            document.getElementById('recipientFirstName').value = li.dataset.firstname;
            document.getElementById('recipientLastName').value = li.dataset.lastname;
            // document.getElementById('recipientUserID').value = li.dataset.userid;
            document.getElementById('recipientRole').value = li.dataset.roleslug;
            
            // console.log('recipientBusiness',li.dataset.business);
            const businessInput = document.getElementById('recipientBusiness');
            businessInput.value = li.dataset.business;

            let businessID = parseInt(jQuery('#businessProfileForm input[name="business_user_id"]').val());
            if( jQuery('#businessProfileForm input[name="profile_business_user"]').val() == 'true' ){
                businessID = parseInt(jQuery('#businessProfileForm input[name="user_id"]').val());
            }

            // console.log('businessID: ',businessID);
            if( businessID > 0 ){
            // console.log('businessID: ADDED');
                jQuery('.user-details-fields #recipientBusiness').val(businessID).trigger('change');
            }

            // ✅ Only make readonly if there's a business name
            if (li.dataset.business && li.dataset.business.trim() !== '') {
                businessInput.setAttribute('readonly', true);
                businessInput.classList.add('readonly');
            }

            suggestionsBox.style.display = 'none';
            jQuery('.user-details-fields').show();
            document.getElementById('emailStatus').textContent = 'User selected';
            document.getElementById('emailStatus').className = 'email-status success';
        }
    });



    // var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";

    submitBtn.addEventListener('click', () => {
        const email = emailInput.value.trim();
        const firstName = document.getElementById('recipientFirstName').value.trim();
        const lastName = document.getElementById('recipientLastName').value.trim();
        const business = document.getElementById('recipientBusiness').value.trim();
        const role = document.getElementById('recipientRole').value.trim();
        const messageContainer = document.querySelector('.add-user-in-business');
        const modal = document.querySelector('#recipientModal'); // Make sure your modal has this ID

        // Clear previous message
        messageContainer.innerHTML = '';
        messageContainer.classList.remove('error-message', 'success-message');
        messageContainer.style.display = 'block';

        if (!firstName || !lastName || !business || !role || !email) {
            messageContainer.innerHTML = 'Error: All fields are required.';
            messageContainer.classList.add('error-message');
            return;
        }

        if( email && !isValidEmail(email) ){
            messageContainer.innerHTML = 'Error: Email is not valid.';
            messageContainer.classList.add('error-message');
            return;
        }
        
        const data = {
            action: 'add_new_business_user',
            email,
            first_name: firstName,
            last_name: lastName,
            business_name: business,
            role
        };

        fetch(userListingData.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    const msg = response.data?.existing_user
                        ? 'User assigned to the business.'
                        : 'New user created successfully.';

                    messageContainer.innerHTML = msg;
                    messageContainer.classList.add('success-message');

                    setTimeout(() => {
                        const addRecipientModal = document.getElementById('addRecipientModal');
                        if (addRecipientModal) {
                            addRecipientModal.style.display = 'none';
                            setTimeout(() => {
                                // console.log('jabskjdakdsadlkgailugsluaigdlai....');
                                renderRecipientAvatars();
                            }, 2000);
                        }
                    }, 2000);

                    setTimeout(() => {
                        messageContainer.style.display = 'none';
                    }, 2000);

                    setTimeout(() => {
                        clearForm();
                    }, 2000);
                    $('#showRecipientsBtn').trigger('click');
                } else {
                    const errorMsg = typeof response.data === 'string'
                        ? response.data
                        : 'Something went wrong.';
                    messageContainer.innerHTML = 'Error: ' + errorMsg;
                    messageContainer.classList.add('error-message');
                }
            })
            .catch(err => {
                console.error('AJAX error:', err);
                messageContainer.innerHTML = 'Error: Something went wrong.';
                messageContainer.classList.add('error-message');
            });
    });

    //Add new user to the business popup END-----------

    function loadBusinessProfileData(userId) {
        // console.log('loadBusinessProfileData is loaded..',userId);
        $.ajax({
            url: userListingData.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'get_business_profile_details',
                user_id: userId
            },
            success: function (businessData) {
                const $form = $('#businessProfileForm');

                if (businessData.is_admin || businessData.is_business_user) {
                    // User is admin or business user: fields should be editable
                    // console.log('This user CAN edit fields.');
                
                    // Remove not-admin class and all restrictions
                    $form.find('input, select, textarea').each(function () {
                        $(this).removeClass('not-admin').removeAttr('readonly').prop('disabled', false);
                    });
                    $('.add-user-button').removeClass('not-admin').prop('disabled', false);
                    $('#approved_billing').removeClass('not-admin').prop('disabled', false);
                
                } else {
                    // User is not admin nor business user: fields should NOT be editable
                    // console.log('This user CANNOT edit fields.');
                
                    $form.find('input, select, textarea').each(function () {
                        if ($(this).is('select')) {
                            $(this).addClass('not-admin').prop('disabled', true);
                        } else {
                            $(this).addClass('not-admin').attr('readonly', true);
                        }
                    });
                    $('.add-user-button').addClass('not-admin').prop('disabled', true);
                    $('#approved_billing').addClass('not-admin').prop('disabled', true);
                }
                
                // console.log('businessData',businessData);
                $form.find('[name="user_id"]').val(userId);
                $form.find('[name="business_user_id"]').val(businessData.assigned_business_user);
                $form.find('[name="profile_business_user"]').val(businessData.profile_business_user);

                // Populate business fields
                $form.find('[name="business_name"]').val(businessData.business_name || '');
                $form.find('[name="float_balance"]').val(businessData.float_balance || '');
                $form.find('[name="business_website"]').val(businessData.business_website || '');
                $form.find('[name="billing_details"]').val(businessData.billing_details || '');
                $form.find('[name="billing_details_2"]').val(businessData.billing_details_2 || '');
                $form.find('[name="business_id"]').val(businessData.business_id || '');
                $form.find('[name="approved_billing"]').prop('checked', businessData.approved_billing === 'yes');
                $form.find('[name="business_float_id"]').val(businessData.business_float_id || '');
                $form.find('[name="business_abn"]').val(businessData.business_abn || '');
                $form.find('[name="business_currency"]').val(businessData.business_currency || 'AUD');

                // Address fields
                $form.find('[name="address_line1"]').val(businessData.address_line1 || '');
                $form.find('[name="address_line2"]').val(businessData.address_line2 || '');
                $form.find('[name="suburb"]').val(businessData.suburb || '');
                $form.find('[name="state"]').val(businessData.state || '');
                $form.find('[name="country"]').val(businessData.country || '');
                $form.find('[name="postcode"]').val(businessData.postcode || '');

                // Remove loader and show form
                $('#loadingBusinessData').remove();
                $form.show();
            },
            error: function () {
                $('#loadingBusinessData').html('<div class="error-message" style="color: red; text-align: center;">Error loading business details.</div>');
            }
        });
    }

    $('#showRecipientsBtn').on('click', function () {
        $.ajax({
            url: userListingData.ajax_url,
            type: 'POST',
            data: {
                action: 'get_recipient_users',
                current_viewed_user_id: currentBusinessUserId,
                current_actual_user_id: currentUserId,
            },
            success: function (response) {
                if (!response.success) {
                    $('#recipientList').hide();
                    alert('Failed to load recipients');
                    return;
                }

                const container = $('#recipientList .recipient-list');
                container.empty();
                $('#recipientList').show();

                const currentBusinessId = response.data.viewed_business_user_id;
                const hasBusiness = currentBusinessId > 0;
                const recipientMap = new Map();

                // Process all recipients
                // console.log('response.data.recipients: ',response.data.recipients);
                response.data.recipients.forEach(user => {
                    // console.log('currentBusinessUserId: ',currentBusinessUserId);
                    const assigned = hasBusiness ?
                        user.assigned_business_ids.includes(currentBusinessId) :
                        false;

                    // console.log('assigned: ',assigned);

                    if (!recipientMap.has(user.id)) {
                        recipientMap.set(user.id, {
                            ...user,
                            assigned: assigned
                        });
                    }
                });

                const sortedRecipients = Array.from(recipientMap.values()).sort((a, b) => {
                    if (hasBusiness) {
                        const getPriority = (user) => {
                            if (user.role.includes('Business User')) return 0;
                            if (user.role.includes('Admin')) return 1;
                            return 2;
                        };

                        const priorityA = getPriority(a);
                        const priorityB = getPriority(b);

                        if (priorityA !== priorityB) {
                            return priorityA - priorityB;
                        }

                        // Optional: fallback to sorting alphabetically by name or email
                        return a.name.localeCompare(b.name);
                    }
                    return 0;
                });


                // Create containers
                const assignedContainer = $('<div class="assigned-group">');
                const unassignedContainer = $('<div class="unassigned-group">');

                sortedRecipients.forEach(user => {
                    var toggle_button_title = 'Make Viewer';
                    var toggle_button_data = 'viewer';
                    if (user.role == 'Viewer') {
                        toggle_button_title = 'Make Admin';
                        toggle_button_data = 'admin';
                    }

                    var user_role = user.role;
                    var user_role_class = '';
                    if( user.role == 'Recipients' ){
                        user_role = 'Recipient';
                    }else if (user.role == "Business User") {
                        user_role = 'Business';
                        user_role_class = ' business_user';
                    }
                    const item = $(`
                    <div class="recipient-item ${user.assigned ? 'assigned-recipient' : ''}${user_role_class}" 
                         data-user-id="${user.id}">
                         <div class="user-icon">
                             <img src="${user.avatar}" class="recipient-avatar">
                                <strong class="recipient-name">${user.name}</strong>
                            </div>
                        <div class="recipient-info">
                            <span class="recipient-email">${user.email}</span>
                        </div>
                        <span class="recipient-role">${user_role}</span>
                        ${hasBusiness ? `
                        <div class="recipient-options">
                            <button class="recipient-options-toggle" type="button">⋮</button>
                            ${user.role != "Business User" ? 
                            `<div class="recipient-options-menu">
                                ${user.assigned
                                ? '<button class="recipient-remove" type="button">Remove</button>'
                                : '<button class="recipient-add" type="button">Add</button>'}
                                <button class="recipient-transfer" data-transfer_role="${toggle_button_data}" type="button">${toggle_button_title}</button>
                            </div>` : ''}

                        </div>`  : ''}
                    </div>
                `);

                    if (hasBusiness && user.assigned) {
                        assignedContainer.append(item);
                    } else {
                        unassignedContainer.append(item);
                    }
                });

                // Add section labels if viewing business user
                if (hasBusiness) {
                    container.append(
                        $('<div class="section-label"></div>'),
                        assignedContainer,
                        $('<div class="section-label"></div>'),
                        unassignedContainer
                    );
                } else {
                    container.html(`
                    <div class="non-business-message">
                     No team members are associated with this business.
                    </div>
                `);
                }
                let businessUserId = '' ;
                let userId = '' ;

                if(userId.length > 0 || businessUserId.length > 0  ){
                    if (parseInt(userId) != parseInt(businessUserId)) {
                        // console.log('INs');
                        $('#businessProfileForm').find('.add-user-button, .recipient-options-toggle').prop('disabled', true);
                        $('#businessProfileForm a').css({
                            'pointer-events': 'none',
                            'cursor': 'not-allowed'
                        });
                    } else {
                        // console.log('ELSEs');
                        $('#businessProfileForm').find('.add-user-button, .recipient-options-toggle').prop('disabled', false);
                        $('#businessProfileForm a').css({
                            'pointer-events': 'all',
                            'cursor': 'pointer'
                        });
                    }
                }

                // Event handlers only for business users
                if (hasBusiness) {
                    container.off('click').on('click', '.recipient-options-toggle', function (e) {
                        e.stopPropagation();
                        const $menu = $(this).next('.recipient-options-menu');
                        // Close other menus and toggle this one
                        $('.recipient-options-menu').not($menu).hide();
                        $menu.toggle();
                    });


                    container.on('click', '.recipient-transfer', function (e) {
                        e.stopPropagation();
                        const $item = $(this).closest('.recipient-item');
                        const recipientId = $item.data('user-id');
                        const transfer_role = jQuery(this).data('transfer_role');

                        $.post(userListingData.ajax_url, {
                            action: 'transfer_business_admin',
                            recipient_id: recipientId,
                            business_user_id: currentBusinessId,
                            transfer_role: transfer_role
                        }, (res) => {
                            const $message = $('.transfer-profile-message');
                            if (res.success) {
                                $message.show();
                                $message.text('Profile transferred successfully.').removeClass('error').addClass('success');
                                $('#showRecipientsBtn').trigger('click');
                            } else {
                                $message.show();
                                $message.text('Error: ' + res.data).removeClass('success').addClass('error');
                            }
                        });
                    });

                    container.on('click', '.recipient-remove', function (e) {
                        e.stopPropagation();
                        const $item = $(this).closest('.recipient-item');
                        const recipientId = $item.data('user-id');

                        if (!confirm("Are you sure you want to remove this recipient from the business?")) return;

                        $.post(userListingData.ajax_url, {
                            action: 'remove_recipient_from_business_user',
                            recipient_id: recipientId,
                            business_user_id: currentBusinessId
                        }, function (res) {
                            const $message = $('.transfer-profile-message');
                            if (res.success) {
                                const $message = $('.transfer-profile-message');
                                // ✅ Remove from DOM immediately
                                $item.remove();
                                $message.show()
                                    .text('Recipient removed successfully.')
                                    .removeClass('error')
                                    .addClass('success');

                                setTimeout(() => {
                                    renderRecipientAvatars();
                                }, 200);
                            } else {
                                $message.show()
                                    .text('Error: ' + res.data)
                                    .removeClass('success')
                                    .addClass('error');
                            }
                        });
                    });

                }
            }
        });
    });

    // Close menus when clicking outside
    $(document).on('click', () => $('.recipient-options-menu').hide());

    // Save business and user details
    $('#saveUserDetailsBtn, #saveBusinessDetailsBtn').on('click', function (e) {

        e.preventDefault();

        const $btn = $(this);
        const $tabContent = $btn.closest('.tab-content');
        const $form = $tabContent.find('form');
        const formId = $form.attr('id');
        const $message = $tabContent.find('.form-message');

        // Clear previous messages and errors
        $message.hide().removeClass('success error').empty();
        $form.find('.error-field').removeClass('error-field');
        $form.find('.field-error-message').remove();

        // 🔍 Manual validation
        let isValid = true;
        let $firstInvalidField = null;

        $form.find('[required]:visible').each(function () {
            const $input = $(this);
            const value = $input.val().trim();
            const name = $input.attr('name');

            // Clear any existing error messages
            $input.next('.field-error-message').remove();

            // Validation for empty required field
            if (!value) {
                // console.log('Empty required field:', name || $input.attr('id'));
                isValid = false;
                $input.addClass('error-field');

                if (!$firstInvalidField) {
                    $firstInvalidField = $input;
                }

                $input.after('<div class="field-error-message" style="color: red; font-size: 13px;">This field is required</div>');
                return;
            }

            // Additional check for mobile pattern
            if (name === 'mobile') {
                // const mobilePattern = /^\+61\d{9}$/; // +61 followed by exactly 9 digits

                //const mobilePattern = /^(?:\+61|0)4\d{8}$/; // +61 or 04 followed by 8 digits
                //const mobilePattern = /^(?:\+61\s?4\d{2}\s?\d{3}\s?\d{3}|04\d{2}\s?\d{3}\s?\d{3})$/;

                // console.log('mobilePattern');
                // console.log(mobilePattern);
                if (!isValidMobile(value)) {
                    // console.log('Invalid mobile number:', value);
                    isValid = false;
                    $input.addClass('error-field');

                    if (!$firstInvalidField) {
                        $firstInvalidField = $input;
                    }

                    $input.after('<div class="field-error-message" style="color: red; font-size: 13px;">Mobile must be in format +61XXXXXXXXX</div>');
                }
            }
        });

        if (!isValid) {
            $message
                .addClass('error')
                .html('Please fill all required fields correctly.')
                .fadeIn();

            $('html, body').animate({
                scrollTop: $firstInvalidField.offset().top - 100
            }, 500);

            return;
        }

        // ✅ Proceed if valid
        const formData = $form.serialize();
        const action = formId === 'userProfileForm'
            ? 'save_user_profile_details'
            : 'save_business_profile_details';

        $.post(userListingData.ajax_url, {
            action: action,
            data: formData,
            nonce: userListingData.nonce
        }).done(function (response) {
            if (response.success && response.data.message) {
                $message
                    .addClass('success')
                    .html(response.data.message)
                    .fadeIn();
                setTimeout(() => $message.fadeOut(), 5000);
            } else {
                $message
                    .addClass('error')
                    .html('Something went wrong.')
                    .fadeIn();
            }
        }).fail(function (error) {
            const errorMessage = error.responseJSON?.data || 'An unknown error occurred.';
            $message
                .addClass('error')
                .html('Error: ' + errorMessage)
                .fadeIn();
        });
    });

    const $content = jQuery('#floatBillingContent');
    jQuery('.float-billing-extra-btn').hide();
    jQuery('.float-top-up-wrapper').hide();

    jQuery('#approved_billing').change(function() {
        // setTimeout(() => {
            updateBalance();
        // }, 2000);
        if (jQuery(this).is(':checked')) {
            // console.log('checkeddddd');

            // console.log('approved_billing is checked');
            // jQuery('.float_balance .amount .float-balance').hide();
            // jQuery('.float_balance .amount .prepaid-credit').show();
            client_billing_approval = 1;
        }else if(!jQuery(this).prop('checked')){
            // console.log('uncheckeddddd');

            // console.log('approved_billing is not');
            // jQuery('.float_balance .amount .float-balance').show();
            // jQuery('.float_balance .amount .prepaid-credit').hide();
            client_billing_approval = 0;
        }
        // const isChecked = $(this).is(':checked');
        // const userId = currentBusinessUserId; // pass the business user ID from PHP

        // $.ajax({
        //     url: userListingData.ajax_url, // wp_localize_script provides this
        //     type: 'POST',
        //     dataType: 'json',
        //     data: {
        //         action: 'get_user_balance',
        //         user_id: userId,
        //         type: isChecked ? 'float_balance' : 'prepaid_limit'
        //     },
        //     beforeSend: function () {
        //         $('.float_balance .amount').text('Loading...');
        //     },
        //     success: function (response) {
        //         if (response.success) {
        //             $('.float_balance .amount').text('$' + response.data.balance);
        //         } else {
        //             $('.float_balance .amount').text('Error');
        //         }
        //     },
        //     error: function () {
        //         $('.float_balance .amount').text('Error');
        //     }
        // });        
        
        // toggleBalances();

        if( parseInt(client_billing_approval) > 0 ){
            // console.log('display from here');
            $content.find('.billing-type-label').text('Client Billing');

            jQuery('.float-billing-extra-btn').hide();
            jQuery('.float-top-up-wrapper').hide();

            const $limit = $('#payment-limit-display');
            const text = $limit.text();

            // Append (Per Transaction) only if it doesn't already exist
            /*if (!text.includes('(Per Transaction)')) {
                $limit.append(' (Per Transaction)');
            }*/
            // jQuery('#payment-limit-display').append('(Per Transaction)')
        } else {
            $content.find('.billing-type-label').text('Instant payment/ float');

            jQuery('.float-billing-extra-btn').show();
            jQuery('.float-top-up-wrapper').show();
            const $limit = $('#payment-limit-display');
            const text = $limit.text();

            // Append (Monthly) only if it doesn't already exist
            /*if (!text.includes('(Monthly)')) {
                $limit.append(' (Monthly)');
            }*/
        }
    });

    $('.user-detail-tab').on('click', function () {

        const target = $(this).data('target');
        // if (target === 'floatBillingContent') {
        //     console.log('Using business user ID for float billing:', currentBusinessUserId);
        // }
        jQuery('span.ttype').remove();

        jQuery('.float-billing-extra-btn').hide();
        jQuery('.float-top-up-wrapper').hide();
        
        if (jQuery('#approved_billing').prop('checked')) {
            client_billing_approval = 1;
        }else{
            client_billing_approval = 0;
        }

        switchToTab(target);
        $('.tab-content').hide();
        $('#' + target).show();
        $('.user-detail-tab').removeClass('active-tab');
        $(this).addClass('active-tab');

        if (target === 'floatBillingContent') {
            // console.log('currentBusinessUserId',currentBusinessUserId);
            // console.log('target',target);
            // console.log('Inside Float');
            const userId = $('#user_id_hidden').val();
            const $content = $('#floatBillingContent');

            // Clear any existing message
            $content.find('.float-billing-info').remove();

            $.ajax({
                url: userListingData.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_user_billing_approval',
                    user_id: currentBusinessUserId
                },
                success: function (response) {
                    const isApproved = response.data.is_approved === 'yes';
                    const prepaidLimit = response.data.prepaid_limit || 0;
                    // console.log('isApproved isssssssssss',isApproved);
                    // console.log('client_billing_approval: ',client_billing_approval);
                    //if (isApproved) {
                    if (parseInt(client_billing_approval) > 0) { //approved for client billing means not a float balance
                        $content.find('.float-billing-header, .float-billing-table-wrapper, .float-billing-footer').show();
                        $content.find('.billing-type-label').text('Client Billing');

                        // Show and hide button
                        $('.float-billing-extra-btn').hide();
                        $('.float-top-up-wrapper').hide();

                        // Set prepaid limit values
                        $('#payment-limit-display').text(`$${parseFloat(prepaidLimit).toFixed(2)}`);
                        // jQuery(' <span class="ttype"> (Monthly)</span>').insertAfter(jQuery('#payment-limit-input'));;
                        // jQuery('#edit-payment-limit').attr('ttype', 'Monthly');

                        // $('#payment-limit-input').val(parseFloat(prepaidLimit).toFixed(2));

                        $('#edit-payment-limit').attr('ttype', 'Monthly');
                        $('#payment-limit-input').val(parseFloat(prepaidLimit).toFixed(2));

                        if ($('#payment-limit-input').next('.ttype').length === 0) {
                            $('<span class="ttype"> (Monthly)</span>').insertAfter($('#payment-limit-input'));
                        } else {
                            const text = $('#payment-limit-input').next('.ttype').text().trim();
                            if (text !== '(Monthly)') {
                                $('#payment-limit-input').next('.ttype').text(' (Monthly)');
                            }
                        }
                    } else { //Not approved for client billing float balance
                        $content.find('.billing-type-label').text('Instant payment/ float');

                        $('.float-billing-extra-btn').show();
                        $('.float-top-up-wrapper').show();

                        $('#payment-limit-display').text(`$${parseFloat(prepaidLimit).toFixed(2)}`);
                        jQuery(' <span class="ttype"> (Per Transaction)</span>').insertAfter(jQuery('#payment-limit-input'));
                        jQuery('#edit-payment-limit').attr('ttype', 'Per Transaction');

                        $('#payment-limit-input').val(parseFloat(prepaidLimit).toFixed(2));
                        // $content.append(`
                        //     <div class="float-billing-info" style="margin-top: 10px;">
                        //         <h3>Billing Type: Instant Payment / Float</h3>
                        //     </div>
                        // `);
                    }
                },
                error: function () {
                    alert('Failed to load billing info.');
                }
            });

            // Make sure page numbers length matches brand table style
           


            function extractNumber(value) {
                const number = parseFloat((value || '').replace(/[^0-9.]/g, ''));
                return isNaN(number) ? '' : number.toFixed(2);
            }

            $(document).on('click', '#edit-float-top-up', function () {
                const value = extractNumber($('#float-top-up-display').text().trim());
                $('#float-top-up-input').val(value).removeClass('hidden').focus();
                $('#float-top-up-display').addClass('hidden');
            });

            $(document).on('blur', '#float-top-up-input', function () {
                const newVal = extractNumber($(this).val());
                const valueToShow = newVal || extractNumber($('#float-top-up-display').text());
                $('#float-top-up-display').text(`$${valueToShow}`).removeClass('hidden');
                $(this).addClass('hidden');
            });

            $(document).on('click', '#edit-payment-limit', function () {
                const value = extractNumber($('#payment-limit-display').text().trim());
                $('#payment-limit-input').val(value).removeClass('hidden').focus();
                $('#payment-limit-display').addClass('hidden');
            });

            $(document).on('blur', '#payment-limit-input', function () {
                const newVal = extractNumber($(this).val());
                const valueToShow = newVal || extractNumber($('#payment-limit-display').text());
                $('#payment-limit-display').text(`$${valueToShow}`).removeClass('hidden');
                $(this).addClass('hidden');
            });
        }

        function fetchTransactions(currentBusinessUserId) {
            // console.log('First load');
            $.ajax({
                url: userListingData.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_user_transactions',
                    user_id: currentBusinessUserId,
                },
                success: function(response) {
                    if (response.success) {

                        if ($.fn.DataTable.isDataTable('#float-billing-table')) {
                            $('#float-billing-table').DataTable().clear().destroy();
                        }
                        
                        $('#float-billing-body').html(response.data.html);
        
                        setTimeout(() => {
                            $('#float-billing-table').DataTable({
                                dom: '<"top">rt<"bottom"lip>',
                                pageLength: 5,
                                lengthChange: true,
                                lengthMenu: [5, 25, 50, 100],
                                order: [[0, 'desc']],
                                paging: true,
                                pagingType: "full_numbers",
                                responsive: true,
                                scrollX: true,
                                columnDefs: [
                                    { orderable: false, targets: [] },
                                    { searchable: false, targets: [] }
                                ],
                                language: {
                                    search: "",
                                    searchPlaceholder: "Search...",
                                    lengthMenu: "Show _MENU_ entries",
                                    zeroRecords: "No matching transactions found",
                                    info: "Showing _START_ to _END_ of _TOTAL_ transactions",
                                    infoEmpty: "Showing 0 to 0 of 0 transactions",
                                    infoFiltered: "(filtered from _MAX_ total transactions)",
                                    paginate: {
                                        previous: "‹",
                                        next: "›",
                                        first: "«",
                                        last: "»"
                                    }
                                },
                                drawCallback: function () {
                                    var pagination = jQuery(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
                                    var pageInfo = this.api().page.info();
                                    var currentPage = pageInfo.page + 1;
                                    var totalPages = pageInfo.pages;
    
                                    // Remove old ellipses
                                    pagination.find('.ellipsis').remove();
    
                                    if (totalPages > 7) {
                                        pagination.find('.paginate_button').each(function () {
                                            var pageNum = parseInt(jQuery(this).text(), 10);
                                            if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                                                jQuery(this).hide(); // Hide middle pages
                                            }
                                        });
    
                                        if (currentPage < totalPages - 2) {
                                            jQuery('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                                        }
    
                                        if (currentPage > 3) {
                                            jQuery('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                                        }
                                    }
                                },
                                initComplete: function () {
                                    jQuery('.dataTables_length select').addClass('results-per-page');
                                }
                            });
                        }, 300);
                    } else {
                        $('#float-billing-body').html('<tr><td colspan="9">No transactions found.</td></tr>');
                    }
                }
            });
        }
    
        const userId = $('#user_id_hidden').val();
        // console.log('currentBusinessUserId user ID : ',currentBusinessUserId);
        fetchTransactions(currentBusinessUserId);

        //SAVE CODE OR THE PREPAID LIMIT AND FLOAT TOPUP BALANCE
        $(document).off('click', '#saveFloatBilling').on('click', '#saveFloatBilling', function () {
            let paymentLimit = $('#payment-limit-input').val();
            // console.log('paymentLimit',paymentLimit);
            let floatTopUp = $('#float-top-up-input').val();
            let userId = $('#float_user_id').val(); // Optional if needed
            let approved_billing = jQuery('#approved_billing').is(':checked') ? 'yes' : 'no';
            let balance_display = jQuery('#payment-limit-display').text();
    
            // Clear old message
            $('.float-billing-tab-message').hide().removeClass('success error').text('');
            $.ajax({
                url: userListingData.ajax_url,
                method: 'POST',
                data: {
                    action: 'save_float_billing',
                    payment_limit: paymentLimit,
                    float_top_up: floatTopUp,
                    user_id: currentBusinessUserId,
                    approved_billing: approved_billing
                },
                success: function(response) {
                    if (response.success) {
                        // console.log('response.....',response);
                        // console.log('response balance.....',response.data.balance);
                        // console.log('response balance.....',response);
                        if(response.data.approved_billing.toLowerCase() === 'yes'){
                            jQuery('.float_balance .amount').text('$' + response.data.balance);
                        }
                        jQuery('.float-billing-tab-message')
                        .addClass('success')
                        .text('Float billing settings saved successfully.')
                        .fadeIn();
                        fetchTransactions(currentBusinessUserId);

                    } else {
                        $('.float-billing-tab-message')
                            .addClass('error')
                            .text('Failed to save: ' + response.data)
                            .fadeIn();
                    }
                },
                error: function() {
                    $('.float-billing-tab-message')
                        .addClass('error')
                        .text('Something went wrong while saving.')
                        .fadeIn();
                }
            });
        });
    });

    $('.user-detail-tab').on('click', function () {
        const target = $(this).data('target');
        switchToTab(target);
        $('.tab-content').hide();
        $('#' + target).show();
        $('.user-detail-tab').removeClass('active-tab');
        $(this).addClass('active-tab');
    
        if (target === 'contactListandEventContent') {
            const businessName = $('.user-name').text().trim();
    
            if (businessName) {
                const formattedName = `${businessName}'s Address Book`;
                $('.user-address-book-name').text(formattedName);
            } else {
                $('.user-address-book-name').text("Address Book");
            }
            if (currentBusinessUserId) {
                // If DataTable already exists, destroy it
                if ($.fn.DataTable.isDataTable('#contact-user-events')) {
                    contactUserEventsTable.destroy();
                    $('#contact-user-events tbody').empty();
                }
    
                // Fetch and populate the table
                contactUserEventsTable = $('#contact-user-events').DataTable({
                    destroy: true,
                    processing: true,
                    serverSide: false,
                    dom: '<"top">rt<"bottom"lip>',
                    pageLength: 5,
                    lengthMenu: [5, 25, 50, 100],
                    order: [[1, 'asc']], // Order by first_name
                    paging: true,
                    pagingType: "full_numbers",
                    responsive: true,
                    scrollX: true,
                    columnDefs: [
                        { orderable: false, targets: [] }, // Update if needed
                        { searchable: false, targets: [0] }
                    ],
                    language: {
                        search: "",
                        searchPlaceholder: "Search by name or ID...",
                        lengthMenu: "Show _MENU_ entries",
                        zeroRecords: "No matching users found",
                        info: "Showing _START_ to _END_ of _TOTAL_ users",
                        infoEmpty: "Showing 0 to 0 of 0 users",
                        infoFiltered: "(filtered from _MAX_ total users)",
                        paginate: {
                            previous: "‹",
                            next: "›",
                            first: "«",
                            last: "»"
                        }
                    },
                    ajax: {
                        url: userListingData.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'get_contact_list_users_by_business',
                            user_id: currentBusinessUserId
                        },
                        dataSrc: function (res) {
                            if (res.success) return res.data;
                            return [];
                        }
                    },
                    columns: [
                        { data: 'ID' },
                        { data: 'first_name' },
                        { data: 'surname' },
                        { data: 'nickname' },
                        { data: 'email' },
                        { data: 'mobile' },
                        { data: 'business' },
                        { data: 'dob' }
                    ],
                    drawCallback: function () {
                        const pagination = jQuery(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
                        const pageInfo = this.api().page.info();
                        const currentPage = pageInfo.page + 1;
                        const totalPages = pageInfo.pages;
                
                        // Remove existing ellipses
                        pagination.find('.ellipsis').remove();
                
                        if (totalPages > 7) {
                            pagination.find('.paginate_button').each(function () {
                                const pageNum = parseInt(jQuery(this).text(), 10);
                                if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                                    jQuery(this).hide();
                                }
                            });
                
                            if (currentPage < totalPages - 2) {
                                jQuery('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                            }
                
                            if (currentPage > 3) {
                                jQuery('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                            }
                        }
                    },
                    initComplete: function () {
                        jQuery('.dataTables_length select').addClass('results-per-page');
                    }
                });
            }

            if (currentBusinessUserId) {
                loadAdminRemindersSection(currentBusinessUserId, 'all');
            }
        }
    });

    // Loads the admin-side Contact List & Events reminders section (calendar +
    // upcoming occasions) for the business user currently being viewed. Mirrors
    // the fetchTransactions() pattern above: users.php has no server-side
    // knowledge of which business user is selected, so the fragment is always
    // fetched and injected client-side. Uses a flag to prevent loading multiple
    // times for the same business user to avoid duplicate event creation.
    let remindersLoadState = {};
    function loadAdminRemindersSection(businessUserId, type, month, year) {
        // Create a unique key for this reminder load state
        const stateKey = businessUserId + '_' + (type || 'all') + '_' + (month || 'current') + '_' + (year || 'current');

        // Skip if already loading or loaded
        if (remindersLoadState[stateKey] === 'loading') {
            return;
        }

        remindersLoadState[stateKey] = 'loading';

        $.ajax({
            url: userListingData.ajax_url,
            type: 'POST',
            data: {
                action: 'get_admin_reminders_section',
                user_id: businessUserId,
                type: type || 'all',
                month: month,
                year: year,
                nonce: userListingData.nonce
            },
            success: function (response) {
                if (response.success && response.data && response.data.html) {
                    $('#contact-reminders-container').html(response.data.html);
                    remindersLoadState[stateKey] = 'loaded';
                } else {
                    $('#contact-reminders-container').html('<p>Unable to load reminders.</p>');
                    remindersLoadState[stateKey] = 'failed';
                }
            },
            error: function () {
                $('#contact-reminders-container').html('<p>Unable to load reminders.</p>');
                remindersLoadState[stateKey] = 'failed';
            }
        });
    }

    // Sidebar toggle functionality for the Contact List & Events section
    $(document).on('click', '#sidebar-toggle-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $sidebar = $('#reminders-sidebar');
        const $toggleBtn = $(this);

        if ($sidebar.hasClass('sidebar-visible')) {
            // Hide sidebar
            $sidebar.slideUp(300, function() {
                $sidebar.removeClass('sidebar-visible');
                $toggleBtn.attr('title', 'Show Sidebar');
                $toggleBtn.addClass('sidebar-hidden');
            });
        } else {
            // Show sidebar
            $sidebar.addClass('sidebar-visible').slideDown(300, function() {
                $toggleBtn.attr('title', 'Hide Sidebar');
                $toggleBtn.removeClass('sidebar-hidden');
            });
        }
    });

    // Calendar month navigation for the admin reminders section — scoped to
    // #contact-reminders-container since its contents are injected dynamically.
    $(document).on('click', '#contact-reminders-container .calendar-nav.prev-month, #contact-reminders-container .calendar-nav.next-month', function (e) {
        e.preventDefault();
        var $wrapper = $('#contact-reminders-container .admin-reminders-wrapper');
        var month = $(this).data('month');
        var year = $(this).data('year');
        var type = $wrapper.data('type') || 'all';

        $('#contact-reminders-container .reminders-calendar-view').addClass('loading');
        loadAdminRemindersSection(currentBusinessUserId, type, month, year);
    });

    // Category filter clicks for the admin reminders section.
    $(document).on('click', '#contact-reminders-container .event-filters a', function (e) {
        e.preventDefault();
        var $wrapper = $('#contact-reminders-container .admin-reminders-wrapper');
        var type = $(this).data('type') || 'all';
        var month = $wrapper.data('month');
        var year = $wrapper.data('year');

        loadAdminRemindersSection(currentBusinessUserId, type, month, year);
    });

    // "Load More" pagination for the admin reminders section's occasions grid.
    $(document).on('click', '#contact-reminders-container .load-more-events-btn', function (e) {
        e.preventDefault();
        var $button = $(this);
        var currentPage = parseInt($button.data('page')) || 1;
        var nextPage = currentPage + 1;
        var perPage = parseInt($button.data('per-page')) || 4;
        var eventType = $button.data('type') || 'all';

        $button.prop('disabled', true);
        var originalText = $button.html();
        $button.html('Loading...');

        $.ajax({
            url: userListingData.ajax_url,
            type: 'POST',
            data: {
                action: 'admin_load_more_reminders_events',
                user_id: currentBusinessUserId,
                page: nextPage,
                per_page: perPage,
                type: eventType,
                nonce: userListingData.nonce
            },
            success: function (response) {
                if (response.success && response.data) {
                    if (response.data.html) {
                        $('#admin-occasions-grid-container').append(response.data.html);
                    }

                    if (response.data.has_more) {
                        var remaining = response.data.total - response.data.loaded;
                        var nextBatch = Math.min(perPage, remaining);
                        $button.data('page', nextPage);
                        $button.html('Load More Events (' + nextBatch + ' of ' + remaining + ' remaining)');
                        $button.prop('disabled', false);
                    } else {
                        $button.closest('.load-more-events-container').fadeOut(300, function () {
                            $(this).remove();
                        });
                    }
                } else {
                    alert('Error loading more events. Please try again.');
                    $button.prop('disabled', false);
                    $button.html(originalText);
                }
            },
            error: function () {
                alert('Error loading more events. Please try again.');
                $button.prop('disabled', false);
                $button.html(originalText);
            }
        });
    });

    // Reminder bell toggle for the admin reminders section — same create/delete
    // flow as the customer-facing My Reminders page, with the currently viewed
    // business user's ID sent along so the server attributes the event to them
    // instead of the logged-in admin.
    $(document).on('click', '#contact-reminders-container .reminder-bell-button', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $button = $(this);
        var recordId = $button.data('record-id');
        var isActive = $button.hasClass('reminder-active');

        if (!recordId || !currentBusinessUserId) {
            return;
        }

        $button.prop('disabled', true);
        var originalHtml = $button.html();

        var action = isActive ? 'delete_event_from_contact' : 'create_event_from_contact';
        var nonce = isActive ? userListingData.deleteEventNonce : userListingData.createEventNonce;

        $.ajax({
            url: userListingData.ajax_url,
            type: 'POST',
            data: {
                action: action,
                record_id: recordId,
                business_user_id: currentBusinessUserId,
                security: nonce
            },
            success: function (response) {
                if (response.success) {
                    if (isActive) {
                        $button.removeClass('reminder-active').addClass('reminder-inactive');
                        $button.attr('data-reminder-status', 'no');
                        $button.attr('title', 'Reminder disabled');
                        $button.attr('aria-label', 'Reminder disabled');
                        $button.html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');
                    } else {
                        $button.removeClass('reminder-inactive').addClass('reminder-active');
                        $button.attr('data-reminder-status', 'yes');
                        $button.attr('title', 'Reminder enabled');
                        $button.attr('aria-label', 'Reminder enabled');
                        $button.html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" fill="#ff69b4" stroke="#ff69b4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21a2 2 0 0 1-3.46 0" fill="#ff69b4" stroke="#ff69b4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');
                    }
                    $button.prop('disabled', false);
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Error processing request. Please try again.');
                    $button.prop('disabled', false);
                    $button.html(originalHtml);
                }
            },
            error: function () {
                alert('Error processing request. Please try again.');
                $button.prop('disabled', false);
                $button.html(originalHtml);
            }
        });
    });

    // Export the Contact List & Events table (address book) for the currently
    // viewed business as a CSV. Streamed server-side so large lists don't need
    // to be built/held in the browser, and so the export always reflects the
    // full dataset rather than just the current DataTables page.
    $('#export-contact-user-events').on('click', function () {
        if (!currentBusinessUserId) {
            return;
        }

        const params = {
            action: 'export_contact_user_events',
            user_id: currentBusinessUserId,
            search: $('#contact-user-events_filter input').val() || '',
            nonce: userListingData.nonce
        };

        window.location.href = `${userListingData.ajax_url}?${new URLSearchParams(params)}`;
    });

    // Export the Float & Billing statement table for the currently viewed
    // business as a CSV. Streamed server-side so the export reflects the full
    // transaction history, not just the current DataTables page.
    $('#export-float-billing-controls').on('click', function () {
        if (!currentBusinessUserId) {
            return;
        }

        const params = {
            action: 'export_float_billing_transactions',
            user_id: currentBusinessUserId,
            search: $('#float-billing-search').val() || '',
            nonce: userListingData.nonce
        };

        window.location.href = `${userListingData.ajax_url}?${new URLSearchParams(params)}`;
    });

    // Modified showRecipientsBtn handler


    // 📤 Export filtered rows only
    // $('#export-order-history').on('click', function () {
    //     if (!orderHistoryTable) return; // Safety check

    //     const filteredData = orderHistoryTable.rows({ search: 'applied' }).data().toArray();

    //     if (filteredData.length === 0) {
    //         alert('No data to export.');
    //         return;
    //     }

    //     const csvHeaders = [
    //         'Order ID', 'Order Name', 'Order Date', 'Client Reference', 'User',
    //         'Order Status', 'Invoice No.', 'Payment Status',
    //         'Total ($)', 'Campaign', 'PO No.', 'Track Cards'
    //     ];

    //     const rows = filteredData.map(row => [
    //         $(row.order_id).text(),
    //         row.order_name,
    //         row.order_date,
    //         row.client_reference,
    //         row.user_name,
    //         $('<div>').html(row.order_status).text(),
    //         $(row.invoice_number).text(),
    //         row.payment_status,
    //         row.total,
    //         row.campaign,
    //         row.po_number,
    //         $(row.track_cards).text()
    //     ]);

    //     const csv = [csvHeaders.join(','), ...rows.map(r => r.join(','))].join('\n');
    //     const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    //     const url = URL.createObjectURL(blob);

    //     const link = document.createElement("a");
    //     link.setAttribute("href", url);
    //     link.setAttribute("download", "order_history.csv");
    //     document.body.appendChild(link);
    //     link.click();
    //     document.body.removeChild(link);
    // });


    $('#export-order-history').on('click', function () {

        let o_number = '';
        // let o_date = '';
        let o_name = '';
        let o_user = '';
        let o_status = '';
        let o_invoice = '';
        let o_payment = '';
        let o_total = '';
        let o_campaign = '';
        let o_client_ref = '';
        let o_po = '';
        o_date = { from: '', to: '' }; // Initialize as object
    
        // Collect filters
        $('#order-history-table_wrapper #order-history-table thead th .filter-box.active_filter').each(function () {
            var $this = jQuery(this);
            var temp = $this.data('head_slug');
            var inputVal = $this.find('input').val();
            // console.log('bnamme........');
            
            if (temp == 'order_status') {
                inputVal = $('input[name="o_status"]:checked').map(function() {
                    return $(this).val();
                }).get().join(',');
            }
            if (temp == 'order_payment') {
                inputVal = $('input[name="o_payment"]:checked').map(function() {
                    return $(this).val();
                }).get().join(',');
            }
            if (temp == 'order_date') {
                const fromVal = $this.find('input.date-from').val() || '';
                const toVal = $this.find('input.date-to').val() || '';
                o_date = { from: fromVal, to: toVal };
            }
            // if (temp == 'brand_name') {
            //     inputVal = $('input[name="o_name"]:checked').map(function() {
            //         return $(this).val();
            //     }).get().join(',');
            // }
            if (temp == 'order_number') o_number = inputVal;
            // if (temp == 'order_date') o_date = inputVal;
            if (temp == 'order_name') o_name = inputVal;
            if (temp == 'order_user') o_user = inputVal;
            if (temp == 'order_status') o_status = inputVal;
            if (temp == 'order_invoice') o_invoice = inputVal;
            if (temp == 'order_payment') o_payment = inputVal;
            if (temp == 'order_total') o_total = inputVal;
            if (temp == 'order_campaign') o_campaign = inputVal;
            if (temp == 'client_reference') o_client_ref = inputVal;
            if (temp == 'order_po') o_po = inputVal;
            // console.log("Column:", temp, " → Value:", inputVal);
            // if (temp == 'order_status') b_status = inputVal;
            // if (temp == 'brand_name') b_name = inputVal;
            // console.log('bnamme........',b_name);
           

        });
        // if (o_number) console.log('Order Number:', o_number);
        // if (o_date.from) console.log('Order Date From :', o_date.from);
        // if (o_date.to) console.log('Order Date To :', o_date.to);
        // if (o_name) console.log('Order Name:', o_name);
        // if (o_user) console.log('Order User:', o_user);
        // if (o_status) console.log('Order Status:', o_status);
        // if (o_invoice) console.log('Order Invoice:', o_invoice);
        // if (o_payment) console.log('Order Payment:', o_payment);
        // if (o_total) console.log('Order Total:', o_total);
        // if (o_campaign) console.log('Order Campaign:', o_campaign);
        // if (o_client_ref) console.log('Client Reference:', o_client_ref);
        // if (o_po) console.log('Order PO:', o_po);
        
        


        // console.log('currentBusinessUserId',currentBusinessUserId);
        const userId = currentBusinessUserId; // or currentUserId
        // console.log('currentBusinessUserId',userId);
        $.ajax({
            url: userListingData.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                o_number: o_number,
                o_date_from: o_date.from,
                o_date_to: o_date.to,     
                o_name: o_name,
                o_user: o_user,
                o_status: o_status,
                o_invoice: o_invoice,
                o_payment: o_payment,
                o_total: o_total,
                o_campaign: o_campaign,
                o_client_ref: o_client_ref,
                o_po: o_po,
                action: 'get_bu_user_order_history',
                user_id: userId,
                export_all: true // you can check this on server to disable pagination
            },
            success: function(response) {
                if (!response.data || response.data.length === 0) {
                    $('.export-list-message').html('<span class="error-message">No data to export.</span>').show();
                    return;
                } else {
                    // Clear any previous message if data exists
                    $('.export-list-message').html('').hide();
                }
    
                const csvHeaders = [
                    'Order ID', 'Order Date', 'Order Name', 'User',
                    'Order Status', 'Invoice No.', 'Payment Status',
                    'Total ($)', 'Campaign',  'Client Reference' , 'PO No.'
                ];

                // Helper to escape CSV values
                function escapeCsv(value) {
                    if (value == null) value = '';
                    value = value.toString().replace(/"/g, '""'); // escape double quotes
                    return `"${value}"`;
                }
    
                const rows = response.data.map(row =>
                    [
                        row.order_id,
                        row.order_date,
                        row.order_name,
                        row.user_name,
                        row.order_status,
                        row.invoice_number,
                        row.payment_status,
                        row.total,
                        row.campaign,
                        row.client_reference,
                        row.po_number
                    ].map(escapeCsv) // escape each value
                );
    
                const csv = [csvHeaders.map(escapeCsv).join(','), ...rows.map(r => r.join(','))].join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
    
    
                const link = document.createElement("a");
                link.setAttribute("href", url);
                link.setAttribute("download", "order_history.csv");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        });
    });
    


    // Search functionality
    // $('#track-cards-search').on('keyup', function () {
    //     trackCardsTable.ajax.reload();
    // });

    $('#export-track-cards').on('click', function () {
        const params = {
            action: 'export_track_cards',
            user_id: currentUserId,
            order_id: currentTrackCardsOrderId, // From track link click
            search: $('#track-cards-search').val(),
            security: userListingData.nonce
        };
        window.location.href = `${userListingData.ajax_url}?${new URLSearchParams(params)}`;
    });

    let currentTrackCardsOrderId = null; // Track current order ID filter

    $(document).on('click', '.track-order-cards', function (e) {
        e.preventDefault();
        currentTrackCardsOrderId = $(this).data('order-id');
        const orderId = $(this).data('order-id');
        const userId = $(this).data('user-id');

        // Switch to Track Cards tab
        $('.user-detail-tab').removeClass('active-tab');
        $('[data-target="trackCardsContent"]').addClass('active-tab');
        $('.tab-content').hide();
        $('#trackCardsContent').show();

        // Destroy existing table if exists
        // if ($.fn.DataTable.isDataTable('#trackCardsTable')) {
        //     $('#trackCardsTable').DataTable().destroy();
        // }

        // Initialize filtered table
        // $('#trackCardsTable').DataTable({
        //     processing: true,
        //     serverSide: true,
        //     responsive: true,
        //     scrollX: true,
        //     ajax: {
        //         url: userListingData.ajax_url,
        //         type: 'POST',
        //         data: {
        //             action: 'get_user_track_cards',
        //             user_id: userId,
        //             order_id: orderId
        //         }
        //     },
        //     columns: [
        //         { data: 'created' },
        //         { data: 'type' },
        //         { data: 'card_number' },
        //         { data: 'email' },
        //         { data: 'sms' },
        //         { data: 'status' },
        //         {
        //             data: 'order_id',
        //             render: function (data) {
        //                 return `#${data}`;
        //             }
        //         }
        //     ],
        //     pageLength: 50,
        //     lengthMenu: [5, 10, 25, 50],
        //     dom: 'rt<"bottom"lip>',
        //     responsive: true
        // });
    });

    // When clicking track cards tab directly
    $('[data-target="trackCardsContent"]').on('click', function () {
        currentTrackCardsOrderId = null;
    });

    // Modify the loadTrackCards function to accept orderId parameter
    function loadTrackCards(userId, orderId = null) {
        currentTrackCardsOrderId = orderId;
        let trackCardsTable;

        if ($.fn.DataTable.isDataTable('#trackCardsTable')) {
            $('#trackCardsTable').DataTable().destroy();
        }
        $('#track-cards-search').on('keyup', function () {
            trackCardsTable.search(this.value).draw();
        });

        trackCardsTable = $('#trackCardsTable').DataTable({
            destroy: true,
            processing: true,
            serverSide: false,
            responsive: true,
            scrollX: false,
            pagingType: "full_numbers",
            dom: 'rt<"bottom"lip>',
            order: [[0, 'desc']],
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            language: {
                paginate: {
                    previous: "‹",
                    next: "›",
                    first: "",
                    last: ""
                }
            },
            drawCallback: function () {
                var pagination = $('#trackCardsTable').closest('.dataTables_wrapper').find('.dataTables_paginate');
                var pageInfo = this.api().page.info();
                var currentPage = pageInfo.page + 1;
                var totalPages = pageInfo.pages;

                pagination.find('.ellipsis').remove();

                if (totalPages > 7) {
                    pagination.find('.paginate_button').each(function () {
                        var pageNum = parseInt($(this).text(), 10);
                        if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                            $(this).hide();
                        }
                    });

                    if (currentPage < totalPages - 2) {
                        $('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                    }

                    if (currentPage > 3) {
                        $('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                    }
                }

                pagination.find('.first, .last').hide();
            },
            ajax: {
                url: userListingData.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = 'get_user_track_cards';
                    d.user_id = userId;
                    d.search = $('#track-cards-search').val();
                    if (orderId) d.order_id = orderId;
                }
            },
            columns: [
                {
                    data: 'created',
                    render: function (data, type, row) {
                        // Sort using numeric timestamp provided by backend
                        if (type === 'sort' || type === 'type') {
                            return row.created_sort || 0;
                        }
                        return data || '';
                    }
                },
                { data: 'type' },
                { data: 'card_number',
                    orderable: false,
                    render: function (data) {
                        return data; // allow HTML (anchor tag)
                    }
                },
                {
                    data: null,
                    render: function (data, type, row) {
                        if (!row.order_id || !row.order_link) return '-';
                        return `<a href="${row.order_link}" target="_blank">#${row.order_id}</a>`;
                    }
                },
                { data: 'email' },
                { data: 'sms' },
                {
                    data: 'status',
                    render: function (data) {
                        const statusMap = {
                            'wc-deactivated': 'Deactivated',
                            'wc-completed': 'Completed',
                            'wc-processing': 'Processing',
                            'wc-pending': 'Pending',
                        };
                        const readable = statusMap[data] || data.replace(/^wc-/, '').replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        return `<span>${readable}</span>`;
                    },
                    createdCell: function (td, cellData) {
                        const statusMap = {
                            'wc-deactivated': 'Deactivated',
                            'wc-completed': 'Completed',
                            'wc-processing': 'Processing',
                            'wc-pending': 'Pending',
                        };
                        const readable = statusMap[cellData] || cellData.replace(/^wc-/, '').replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        const className = readable.toLowerCase().replace(/\s+/g, '-');
                        $(td).addClass(`status ${className}`);
                    }
                }
            ]
        });

        trackCardsLoadedUser = userId;
    }

    const brandsSearchInput = document.getElementById("brandsSearchInput");
    const clearSearch = document.getElementById("clearSearch");
    const userTable = $('#userTable').DataTable({
        processing: true,
        searching: true,
        responsive: true,
        scrollX: false,
        pageLength: 25,
        lengthMenu: [5, 10, 25, 50],
        dom: 'rt<"bottom"lip>',
        pagingType: "full_numbers",
        language: {
            search: "",
            searchPlaceholder: "Search users...",
            paginate: {
                previous: "‹",
                next: "›",
                first: "«",
                last: "»"
            }
        },
        columnDefs: [
            { orderable: false, targets: [0, -1] } // 0 = first col, -1 = last col
        ],
        order: [[1, 'desc']],
        drawCallback: function () {
            let api = this.api();
            const pageRows = api.rows({ page: 'current' }).nodes().to$();

            // Handle checkbox sync
            if ($("#selectAllUsers").length === 0) {
                $("#userTable thead th").eq(0).html('<input type="checkbox" id="selectAllUsers">');
            }

            let allChecked = true;
            pageRows.find('.user-checkbox').each(function () {
                const userId = $(this).val();
                const isSelected = selectedUsers.has(userId);

                $(this).prop("checked", isSelected);
                if (!isSelected) {
                    allChecked = false;
                }
            });

            $('#selectAllUsers').prop('checked', allChecked);
        }
    });

    // User table Filetr functionality START -----------

    // Build dynamic filters
    $('#userTable thead th').each(function (index) {

        const colText = $(this).text().trim();
        const isCheckboxCol = index === 0; 
        const isDetailsCol = index === $('#userTable thead th').length - 1;
        const isRoleCol = colText === 'User Type';
        const isBusinessCol = colText === 'Business';
        let inputField = '';

        if (isCheckboxCol || isDetailsCol) return; // skip first & last column

        if (isRoleCol) {
            // Role checkboxes placeholder
            inputField = `
                <div class="checkbox-group" data-col="${index}">
                    <p style="margin:0; font-size:12px;">Loading user types...</p>
                </div>
            `;
        } else if (isBusinessCol) {
            // Business dropdown
            inputField = `
                <select class="column-business-filter column-search" data-col="${index}"
                    style="width:100%; padding:5px;">
                    <option value="">All Businesses</option>
                </select>
            `;
        } else {
            // Text input for other columns
            inputField = `
                <input type="text" class="column-search" data-col="${index}"
                    placeholder="Search..." style="width:100%; padding:5px;">
            `;
        }

        // Add icon and filter box
        let iconHtml = `
            <i class="fa-solid fa-arrow-down"></i>
            <i class="dashicons dashicons-filter"></i>
        `;
        
        $(this).html(`
            ${colText}
            <span class="filter-icon" data-col="${index}" style="cursor:pointer;">${iconHtml}</span>
            <div class="filter-box" data-col="${index}" 
                style="display:none; background:white; border:1px solid #ccc; padding:5px; z-index:999;">
                ${inputField}
            </div>
        `);
    });

    const userFilterBoxStates = {};

    // Toggle filter boxes
    $('#userTable thead').on('click', '.filter-icon', function (e) {
        var ajaxurl = (typeof userListingData !== 'undefined' && userListingData.ajax_url) ? userListingData.ajax_url : (typeof userData !== 'undefined' && userData.ajax_url ? userData.ajax_url : '');
        e.stopPropagation();
        const colIndex = $(this).data('col');
        const filterBox = $(`#userTable thead .filter-box[data-col="${colIndex}"]`);
        const isOpen = userFilterBoxStates[colIndex];

        if (isOpen) {
            filterBox.hide();
            userFilterBoxStates[colIndex] = false;
        } else {
            // $('.filter-box').hide(); // close others
            filterBox.show();
            userFilterBoxStates[colIndex] = true;

            // Load roles (user types) dynamically
            if (filterBox.find('.checkbox-group').length && !filterBox.data('loaded')) {
                $.ajax({
                    url: ajaxurl,
                    method: 'GET',
                    dataType: 'json',
                    data: { action: 'get_all_user_roles' },
                    success: function (res) {
                        // console.log('res....',res);
                        if (res && res.success && res.data) {
                            let checkboxes = '';
                            $.each(res.data, function (slug, name) {
                                checkboxes += `
                                    <label style="display:block; margin-bottom:3px;">
                                        <input type="checkbox" name="u_role" class="status-filter user-role-filter" value="${name}">
                                        ${name}
                                    </label>
                                `;
                            });
                            filterBox.find('.checkbox-group').html(checkboxes);
                            filterBox.data('loaded', true);
                        } else {
                            filterBox.find('.checkbox-group').html('<p>No roles found</p>');
                        }
                    },
                    error: function () {
                        filterBox.find('.checkbox-group').html('<p style="color:red;">Failed to load roles</p>');
                    }
                });
            }

            // Load business names dynamically
            if (filterBox.find('.column-business-filter').length && !filterBox.data('loaded')) {
                $.ajax({
                    url: ajaxurl,
                    method: 'GET',
                    dataType: 'json',
                    data: { action: 'get_all_business_names' },
                    success: function (res) {
                        if (res && res.success && res.data) {
                            const $select = filterBox.find('.column-business-filter');
                            $.each(res.data, function (i, business) {
                                $select.append(`<option value="${business}">${business}</option>`);
                            });
                            filterBox.data('loaded', true);
                        }
                    },
                    error: function () {
                        filterBox.find('.column-business-filter').html('<option value="">Failed to load</option>');
                    }
                });
            }
        }

        // Prevent closing when clicking inside box
        filterBox.off('click').on('click', function (e) {
            e.stopPropagation();
        });
    });

    // Text filters (User ID, Name, Email)
    $('#userTable thead').on('keyup change', '.column-search[type="text"]', function () {
        const colIndex = $(this).data('col');
        userTable.column(colIndex).search(this.value).draw();
    });

    // Checkbox filters (User Type)
    $('#userTable thead').on('change', '.user-role-filter', function () {        
        const selectedRoles = $('.user-role-filter:checked').map(function () {
            return $(this).val();
        }).get();
        
        // console.log('selectedRoles....',selectedRoles);
        if (selectedRoles.length === 0) {
            userTable.column(5).search('').draw();
        } else {
            const exactRegex = '^(' + selectedRoles.map(r => $.fn.dataTable.util.escapeRegex(r)).join('|') + ')$';
            userTable.column(5).search(exactRegex, true, false).draw();
        }
    });

    // Business dropdown filter
    $('#userTable thead').on('change', '.column-business-filter', function () {
        const colIndex = $(this).data('col');
        const selectedValue = $(this).val() || '';
        userTable.column(colIndex).search(selectedValue).draw();
    });
    // User table Filetr functionality END -----------

    // Assuming "User Type" is column index 5 (0-based)
    $('#userRoleFilter').on("change", function () {
        var roleValue = $(this).val();
        userTable
            .column(5)
            .search(roleValue ? '^' + roleValue + '$' : '', true, false) // regex, exact match
            .draw();
    });

    $(document).on("change", "#selectAllUsers", function () {
        const isChecked = $(this).prop("checked");

        // Select only current page's rows
        userTable.rows({ page: 'current' }).every(function () {
            const $row = $(this.node());
            const checkbox = $row.find('.user-checkbox');
            const userId = checkbox.val();

            checkbox.prop("checked", isChecked);

            if (isChecked) {
                selectedUsers.add(userId);
            } else {
                selectedUsers.delete(userId);
            }
        });
    });

    // Show/hide clear button
    brandsSearchInput.addEventListener("input", () => {
        clearSearch.style.display = brandsSearchInput.value ? "block" : "none";
    });

    // Trigger global DataTables search on keyup
    brandsSearchInput.addEventListener("keyup", function () {
        // console.log('This value is : ',this.value);
        userTable.search(this.value).draw();
    });

    clearSearch.addEventListener("click", () => {
        brandsSearchInput.value = "";
        clearSearch.style.display = "none";
        brandsSearchInput.dispatchEvent(new Event("input"));
        brandsSearchInput.focus();
        userTable.search("").draw();
    });


    // Handle Create New User button click
    $('#createUser').on('click', function () {
        $('.user-list-container, .filter-container, .save-next-buttons').hide();
        $('#createUserFormContainer').show();
        $('#createUserForm')[0].reset();
        $('#businessFormContainer').hide();
        $('#nextToBusinessForm').hide();
        $('.error-field').removeClass('error-field'); // Clear previous errors
    });

    // Show/hide Next button based on user type
    $('select[name="user_type"]').on('change', function () {
        if ($(this).val() === 'business_user') {
            $('#saveNewUser').hide();
            $('#nextToBusinessForm').show();
        } else {
            $('#saveNewUser').show();
            $('#nextToBusinessForm').hide();
        }
    });

    $('#nextToBusinessForm').on('click', function (e) {
        e.preventDefault();
        $('.error-message').remove();
        $('.field-error-message').remove();
        $('.error-field').removeClass('error-field');
        $('.new-user-message').hide().text('');
        $('.server-error-messages').hide().text('');

        const $btn = $(this);
        const originalText = $btn.text();
        // Clear previous errors and messages in the user form

        // Validate the user form inputs
        let isValid = true;
        $('#createUserForm :input[required]').each(function () {
            const field = $(this);
            const fieldName = field.attr('name');
            let fieldLabel = '';

            // Map proper labels per field
            switch (fieldName) {
                case 'first_name':
                    fieldLabel = 'User First Name';
                    break;
                case 'last_name':
                    fieldLabel = 'User Surname';
                    break;
                case 'email':
                    fieldLabel = 'Email';
                    break;
                case 'mobile':
                    fieldLabel = 'Mobile';
                    break;
                default:
                    // Try to get the label text near the input
                    const label = $(`label[for="${field.attr('id')}"]`).text().trim();
                    fieldLabel = label || field.attr('placeholder') || fieldName;
            }

            if (!field.val().trim()) {
                field.addClass('error-field');
                field.after(`<div class="error-message" style="color: red; font-size: 13px;">${fieldLabel} is required</div>`);
                isValid = false;
            }
        });


        // Validate email format
        const emailField = $('#createUserForm [name="email"]');
        const emailVal = emailField.val().trim();
        if (emailVal && !isValidEmail(emailVal)) {
            emailField.addClass('error-field');
            emailField.after('<div class="error-message" style="color: red; font-size: 13px;">Invalid email format</div>');
            isValid = false;
        }

        // Validate mobile format
        const mobileField = $('#createUserForm [name="mobile"]');
        const mobileVal = mobileField.val().trim();
        if (mobileVal && !isValidMobile(mobileVal)) {
            mobileField.addClass('error-field');
            mobileField.after('<div class="error-message" style="color: red; font-size: 13px;">Invalid mobile number</div>');
            isValid = false;
        }

        if (!isValid) {
            // Scroll to the first error field
            const firstError = $('.error-field').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
                firstError.focus();
            }
            return;
        }
        $btn.prop('disabled', true).html('Loading...');

        // AJAX call to check if email exists
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: {
                action: 'check_email_exists',
                email: emailVal
            },
            success: function (response) {
                if (response.exists) {
                    emailField.addClass('error-field');
                    emailField.after('<div class="error-message" style="color: red; font-size: 13px;">This email already exists. Please use a different one.</div>');
                    $('html, body').animate({
                        scrollTop: emailField.offset().top - 100
                    }, 500);
                    emailField.focus();
                } else {
                    // Proceed to business form
                    $('#createUserFormContainer').hide();
                    $('#businessFormContainer').show();
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function () {
                $('.server-error-messages').text('Something went wrong while checking the email.').show();
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Handle form submission (both cases)
    $('#saveNewUser, #saveBusinessDetailsBtn1').on('click', function (e) {
        e.preventDefault();
        
        if(user_create_form_validator()){

            var isBusinessUser = $('select[name="user_type"]').val() === 'business_user';

            // Collect all form data
            let formData = $('#createUserForm').serializeArray();
            if (isBusinessUser) {
                formData = formData.concat($('#businessProfileForm1').serializeArray());
            }

            // Convert to object and handle checkbox
            const data = {};
            $.each(formData, function () {
                data[this.name] = this.value;
            });

            if (isBusinessUser) {
                data['approved_billing'] = $('#approved_billing').is(':checked') ? 'yes' : 'no';
            }

            const $btn = $(this);
            $btn.prop('disabled', true).text('Creating...');

            $.post(userListingData.ajax_url, {
                action: 'create_new_user_with_all_details',
                user_data: data,
                is_business_user: isBusinessUser,
                nonce: userListingData.nonce,
            }).done(function (response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    // Clear previous server errors
                    $('.server-error-messages').empty().hide();
                    // If errors come in one string with newlines, split to array
                    let errors = response.data.message;
                    if (typeof errors === 'string') {
                        errors = errors.split('\n').filter(Boolean);
                    }

                    // Show errors in the error container
                    if (errors.length) {
                        let $container = $('.server-error-messages');
                        errors.forEach(function (err) {
                            $container.append('<div>' + err + '</div>');
                        });
                        $container.show();
                        $('html, body').animate({
                            scrollTop: $container.offset().top - 100 // adjust offset if needed
                        }, 500);
                    } else {
                        $('.server-error-messages').text('User creation failed').show();
                        $('html, body').animate({
                            scrollTop: $('.server-error-messages').offset().top - 100
                        }, 500);
                    }
                }
            }).fail(function (error) {
                alert('Error: ' + (error.responseJSON.data || 'Request failed'));
            }).always(function () {
                $btn.prop('disabled', false).text('Save & Exit');
            });
        }
    });

    $('#createUserForm input,#createUserForm select, #businessProfileForm1 input,#businessProfileForm1 select').on('keyup', function (e) {
        // console.log('Keyup event triggered');
        e.preventDefault();
        user_create_form_validator();
    });

    function user_create_form_validator(){
        $('.error-message').remove();

        // Clear previous errors
        $('.error-field').removeClass('error-field');
        $('.field-error-message').remove(); // Remove previous inline error messages

        // Validate main form first
        let isValid = true;
        $('#createUserForm :input[required]').each(function () {
            if (!$(this).val()) {
                $(this).addClass('error-field');
                // Add error message after this field if not already present
                if ($(this).next('.field-error-message').length === 0) {
                    $(this).after('<div class="field-error-message">This field is required</div>');
                }
                isValid = false;
            }
        });

        // Validate email format
        const emailField = $('#createUserForm [name="email"]');
        if (emailField.val() && !isValidEmail(emailField.val())) {
            emailField.addClass('error-field');
            if (emailField.next('.field-error-message').length === 0) {
                emailField.after('<div class="field-error-message">Please enter a valid email address</div>');
            }
            isValid = false;
        }

        // Validate mobile (required and format)
        const mobileField = $('#createUserForm [name="mobile"]');
        if (!mobileField.val()) {
            mobileField.addClass('error-field');
            if (mobileField.next('.field-error-message').length === 0) {
                mobileField.after('<div class="field-error-message">Mobile number is required</div>');
            }
            isValid = false;
        } else if (!isValidMobile(mobileField.val())) {
            mobileField.addClass('error-field');
            if (mobileField.next('.field-error-message').length === 0) {
                mobileField.after('<div class="field-error-message">Please enter a valid mobile number</div>');
            }
            isValid = false;
        }

        // Validate mobile format
        // const mobileField = $('#createUserForm [name="mobile"]');
        // if (mobileField.val() && !isValidMobile(mobileField.val())) {
        //     mobileField.addClass('error-field');
        //     isValid = false;
        // }

        if (!isValid) {
            $('.new-user-message')
                .text('Please fill all required user fields with valid data')
                .show();
            return;
        }
        var isBusinessUser = $('select[name="user_type"]').val() === 'business_user';

        // For business users, validate business form
        if (isBusinessUser) {
            let businessValid = true;
            $('#businessProfileForm1 :input[required]').each(function () {
                if (!$(this).val()) {
                    $(this).addClass('error-field');
                    businessValid = false;
                }
            });

            const businessNameField = $('#new_business_name');
            if (!businessNameField.val()) {
                businessNameField.addClass('error-field');
                if (businessNameField.next('.field-error-message').length === 0) {
                    businessNameField.after('<div class="field-error-message">Business Name is required</div>');
                }
                businessValid = false;
            }



            const businessIdField = $('input[id="new_business_id"]');
            if (!businessIdField.val()) {
                businessIdField.addClass('error-field');
                if (businessIdField.next('.field-error-message').length === 0) {
                    businessIdField.after('<div class="field-error-message">Business ID is required</div>');
                }
                businessValid = false;
            }

            const addressLine1Field = $('input[id="new_address_line1"]');
            if (!addressLine1Field.val()) {
                addressLine1Field.addClass('error-field');
                if (addressLine1Field.next('.field-error-message').length === 0) {
                    addressLine1Field.after('<div class="field-error-message">Business Address Line 1 is required</div>');
                }
                businessValid = false;
            }

            // const addressLine2Field = $('input[id="new_address_line2"]');
            // if (!addressLine2Field.val()) {
            //     addressLine2Field.addClass('error-field');
            //     if (addressLine2Field.next('.field-error-message').length === 0) {
            //         addressLine2Field.after('<div class="field-error-message">Business Address Line 2 is required</div>');
            //     }
            //     businessValid = false;
            // }

            const suburbField = $('input[id="new_suburb"]');
            if (!suburbField.val()) {
                suburbField.addClass('error-field');
                if (suburbField.next('.field-error-message').length === 0) {
                    suburbField.after('<div class="field-error-message">Suburb is required</div>');
                }
                businessValid = false;
            }

            const stateField = $('input[id="new_state"]');
            if (!stateField.val()) {
                stateField.addClass('error-field');
                if (stateField.next('.field-error-message').length === 0) {
                    stateField.after('<div class="field-error-message">State is required</div>');
                }
                businessValid = false;
            }

            const countryField = $('input[id="new_country"]');
            if (!countryField.val()) {
                countryField.addClass('error-field');
                if (countryField.next('.field-error-message').length === 0) {
                    countryField.after('<div class="field-error-message">Country is required</div>');
                }
                businessValid = false;
            }

            const postcodeField = $('input[id="new_postcode"]');
            if (!postcodeField.val()) {
                // console.log('this called');
                postcodeField.addClass('error-field');
                if (postcodeField.next('.field-error-message').length === 0) {
                    postcodeField.after('<div class="field-error-message">Postcode is required</div>');
                }
                businessValid = false;
            }

            // Validate Business ABN
            const businessAbnField = $('[id="new_business_abn"]');
            if (!businessAbnField.val()) {
                businessAbnField.addClass('error-field');
                if (businessAbnField.next('.field-error-message').length === 0) {
                    businessAbnField.after('<div class="field-error-message">Business ABN is required</div>');
                }
                businessValid = false;
            }

            if (!businessValid) {
                // Remove alert here
                $('.server-error-messages')
                    .text('Please fill all required business fields')
                    .show();
                return;
            }
        }
        return isValid;

    }

    // Helper functions for validation
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    function isValidMobile(mobile) {
        //const regex = /^(?:\+61|0)4\d{8}$/;
        const regex = /^(?:\+61\s?4\d{2}\s?\d{3}\s?\d{3}|04\d{2}\s?\d{3}\s?\d{3})$/;
        return regex.test(mobile);
    }

});


document.addEventListener("DOMContentLoaded", function () {
    const menuItems = document.querySelectorAll('#menu-item-1061 ul.sub-menu > li');
    const dropdown = document.getElementById('userRoleFilter');

    /*if (!menuItems.length || !dropdown) return;

    // Slug mapping: role => url slug
    const slugMap = {
        'recipients': 'contact_list_user'
        // Add more here if needed
    };

    // Reverse mapping: url slug => role
    const reverseSlugMap = {};
    for (const key in slugMap) {
        reverseSlugMap[slugMap[key]] = key;
    }

    // Menu click handler
    menuItems.forEach(menuItem => {
        const link = menuItem.querySelector('a[href="#"]');
        if (!link) return;

        link.addEventListener('click', function (e) {
            e.preventDefault();

            // Remove active from all and add to clicked
            menuItems.forEach(item => item.classList.remove('active'));
            menuItem.classList.add('active');

            // Extract class matching a dropdown value
            const classes = Array.from(menuItem.classList);
            const roleClass = classes.find(cls => dropdown.querySelector(`option[value="${cls}"]`));

            if (roleClass) {
                const urlSlug = slugMap[roleClass] || roleClass;
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('role', urlSlug);
                window.location.href = newUrl.toString();
            }
        });
    });*/

    // Handle dropdown pre-select on load
    const urlParams = new URLSearchParams(window.location.search);
    let selectedRole = urlParams.get('role');
    //console.log('selectedRole: ',selectedRole);

    if (selectedRole) {
        const option = Array.from(dropdown.options).find(opt => opt.value === selectedRole);
        // console.log('option: ',option);
        // console.log('dropdown.options: ',dropdown.options);
        if (option) {
            dropdown.value = selectedRole;

            // delay and then trigger click (or change, depending on your need)
            setTimeout(() => {
                dropdown.click();   // if UI depends on click
                // OR if you want change event:
                dropdown.dispatchEvent(new Event('change'));
            }, 100); // adjust delay as needed
        }
        
        /*// Translate slug to original role key if needed
        if (reverseSlugMap[selectedRole]) {
            selectedRole = reverseSlugMap[selectedRole];
        }

        dropdown.value = selectedRole;
        $('#userRoleFilter').trigger('change');

        const activeItem = document.querySelector(`#menu-item-1061 li.${selectedRole}`);
        if (activeItem) {
            activeItem.classList.add('active');
        }*/
    }
});

jQuery(document).ready(function ($) {
    const urlParams = new URLSearchParams(window.location.search);
    const userIdFromURL = urlParams.get('id');

    if (userIdFromURL) {
        // Simulate a click on view-user-details with the user ID
        const $tempViewBtn = $('<button class="view-user-details" style="display:none;" data-user-id="' + userIdFromURL + '"></button>');
        $('body').append($tempViewBtn);
        $tempViewBtn.trigger('click');
    }
});
