
jQuery(document).ready(function ($) {

    const orderPageURL = (typeof user_fetch_ajax !== 'undefined' && user_fetch_ajax.siteUrl) ? user_fetch_ajax.siteUrl.replace(/\/$/, '') + '/order/' : '/order/';


    // Set a flag in sessionStorage when nav item is clicked
    /*$(".bulk-create-an-order, .create-an-order").on("click", function (e) {
        e.preventDefault();
        sessionStorage.setItem("triggerNewOrder", "1"); // Set flag
        window.location.href = orderPageURL;
    });

    // On Order Page: Check if flag is set
    if (window.location.pathname === "/order/" && sessionStorage.getItem("triggerNewOrder") === "1") {
        setTimeout(function () {
            $("#new-order-button").trigger("click");
            sessionStorage.removeItem("triggerNewOrder"); // Clear flag after click
        }, 200);
    }*/
    const toggleBtn = document.getElementById('add-new-sender');
    const addNewCampaignBtn = document.getElementById('add-new-campaign');

    const senderForm = document.getElementById('new-sender-input');
    const campaignForm = document.getElementById('new-campaign-input');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const icon = this.querySelector('i');
            const text = this.querySelector('.btn-text');

            if (senderForm.style.display === 'none' || senderForm.style.display === '') {
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-xmark');
                text.textContent = 'Cancel';
            } else {
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-plus');
                text.textContent = 'New';
            }
        });
    }

    if (addNewCampaignBtn) {
        addNewCampaignBtn.addEventListener('click', function () {
            const icon = this.querySelector('i');
            const text = this.querySelector('.btn-text');

            if (campaignForm.style.display === 'none' || campaignForm.style.display === '') {
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-xmark');
                text.textContent = 'Cancel';
            } else {
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-plus');
                text.textContent = 'New';
            }
        });
    }


    // Order Filter Code START From Here   ---------------------

    // Handle "Select All"
    

    // Use event delegation for dynamically generated buttons
    // $('#order-table').on('click', '.select-all-data-filter', function (e) {
    //     e.preventDefault();
    //     const filterBox = $(this).closest('.filter-box');
    //     filterBox.find('.filter-check').each(function () {
    //         if (!$(this).is(':checked')) {
    //             $(this).prop('checked', true).trigger('change');
    //         }
    //     });
    // });

    // $('#order-table').on('click', '.clear-all-data-filter', function (e) {
    //     e.preventDefault();
    //     const filterBox = $(this).closest('.filter-box');
    //     filterBox.find('.filter-check').each(function () {
    //         if ($(this).is(':checked')) {
    //             $(this).prop('checked', false).trigger('change');
    //         }
    //     });
    // });

    // Attach click to each existing clear-date icon
    // jQuery('.remove-date-from').on('click', function(e) {
    //     jQuery('.column-search.date-from').val('');
    // });

    // // Attach click to each existing clear-date icon
    // jQuery('.remove-date-to').on('click', function(e) {
    //     jQuery('.column-search.date-to').val('');
    // });

    // Clear the date input when 'X' is clicked
    // $('#order-table thead').on('click', '.clear-date', function(e) {
    //     e.stopPropagation();
    //     console.log('hiiii');
    //     const $input = $(this).siblings('input'); // get the related date input
    //     $input.val(''); // clear the date
    //     $input.trigger('change'); // trigger change to re-filter the table
    // });
     
    // Hide all boxes only on clicking outside any icon or box
    /*$(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-box, .filter-icon').length) {
            $('.filter-box').hide();
            Object.keys(filterBoxStates).forEach(key => filterBoxStates[key] = false);
        }
    });*/

    /*jQuery('.order-list-container .search-container').css('display', 'flex');
    jQuery('.order-list-container .search-container').css('flex', 'auto');
    jQuery('.order-list-container .search-container').css('align-items', 'center');*/
    
    // Append search icon and filter box to each header
    $('#order-table thead th').each(function (index) {
        const colText = $(this).text();
        const colSlug = $(this).data('head_slug');
        
        const isDateColumn = index === 1;
        const isStatusColumn = index === 5;
    
        let inputField;
    
        if (isDateColumn) {
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
        } else if (isStatusColumn) {
            inputField = `<div class="status-checkboxes" data-col="${index}">
            <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="o_status" class="status-filter" value="Draft"> Draft
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
    
        $(this).html(`
            ${colText}
            <span class="filter-icon" data-col="${index}" style="cursor:pointer; width:16px; height:16px;">
               <i class="fa-solid fa-arrow-down"></i>
            </span>
            <div class="filter-box" data-head_slug="${colSlug}" data-col="${index}" style="display:none; background:white; border:1px solid #ccc; padding:5px; z-index:999;">
                ${inputField}
            </div>
        `);
    });

    // Clear the From date and reset table
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

    // Track open/closed state for each filter box
    const filterBoxStates = {};

    // Toggle individual filter box on icon click
    $('#order-table thead').on('click', '.filter-icon', function (e) {
        e.stopPropagation();
        const colIndex = $(this).data('col');
        const filterBox = $(`.filter-box[data-col="${colIndex}"]`);

        const isOpen = filterBoxStates[colIndex];

       
        if (isOpen) {
            filterBox.hide().removeClass('active_filter');
            filterBox.parent().removeClass('active_filter_wrapper');
            filterBoxStates[colIndex] = false;
        } else {
            filterBox.show().addClass('active_filter');
            filterBox.parent().addClass('active_filter_wrapper');
            filterBoxStates[colIndex] = true;

            // ✅ If it's Status column → generate checkboxes dynamically
            if (colIndex === 5) {
                // let statuses = orderTable
                //     .column(colIndex, { search: 'applied' })
                //     .data()
                //     .toArray();

                // let uniqueStatuses = [...new Set(
                //     statuses
                //         .map(s => s.replace(/<[^>]*>/g, '').trim()) // remove HTML tags and trim
                //         .filter(s => s !== "")
                // )];

                // let html = uniqueStatuses.map(s => `
                //     <label style="display:block; margin-bottom:3px;">
                //         <input type="checkbox" name="o_status" class="status-filter" value="${s}"> ${s}
                //     </label>
                // `).join('');

                // let html = `
                //     <label style="display:block; margin-bottom:3px;">
                //         <input type="checkbox" name="o_status" class="status-filter" value="Draft"> Draft
                //     </label>
                //     <label style="display:block; margin-bottom:3px;">
                //         <input type="checkbox" name="o_status" class="status-filter" value="Processing"> Processing
                //     </label>
                //     <label style="display:block; margin-bottom:3px;">
                //         <input type="checkbox" name="o_status" class="status-filter" value="Completed"> Completed
                //     </label>
                // `;

               // filterBox.find('.status-checkboxes').html(html);

                //console.log("Unique Statuses:", uniqueStatuses);
            }
        }

        // Prevent clicks inside from closing
        filterBox.off('click').on('click', function (e) {
            e.stopPropagation();
        });
    });

    // 🔹 Apply filter when checkboxes are changed
    // 🔹 Apply filter when checkboxes are changed
    $('#order-table thead').on('change', '.status-filter', function () {
        let selectedStatuses = $('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex($(this).val()); // escape safely
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Draft|Processing|Completed)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            orderTable.column(5).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            orderTable.column(5).search('').draw();
        }
    });

    // Live filtering
    $('#order-table thead').on('keyup change', '.column-search', function () {
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
                    if (settings.nTable.id !== 'order-table') return true;
                
                    
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
    
            orderTable.draw();
            return;
        }
        function stripTime(date) {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate());
        }
        // Other columns' filtering logic remains the same here
        let value = this.value;
    
        const isLastColumn = colIndex === $('#order-table thead th').length - 1;
    
        if (isLastColumn && value) {
            const escaped = $.fn.dataTable.util.escapeRegex(value);
            value = `^AUD ${escaped}(\\.00)?$`;
            orderTable
                .column(colIndex)
                .search(value, true, false)
                .draw();
        } else {
            orderTable
                .column(colIndex)
                .search(value)
                .draw();
        }
    });

    // Order Filter Code END From Here ----------------
    var orderTable;
    if ($('#order-table').length && $.fn.DataTable) orderTable = $('#order-table').DataTable({
        dom: '<"top">rt<"bottom"lip>',
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'desc']],
        paging: true,
        responsive: true,
        scrollX: true,
        pagingType: "full_numbers",
        responsive: true,
        // ordering: false, // ✅ disables all sorting
        // columnDefs: [
        //     { orderable: false, targets: [0, 4, 5] },
        //     { searchable: false, targets: [5] }
        // ],
        language: {
            search: "",
            searchPlaceholder: "Search orders...",
            lengthMenu: "Show _MENU_ entries",
            zeroRecords: "No matching orders found",
            info: "Showing _START_ to _END_ of _TOTAL_ orders",
            infoEmpty: "Showing 0 to 0 of 0 orders",
            infoFiltered: "(filtered from _MAX_ total orders)",
            paginate: {
                previous: "‹",
                next: "›",
                first: "«",
                last: "»"
            }
        },
        drawCallback: function () {
            var pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
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
        },
        initComplete: function () {
            $('.dataTables_length select').addClass('results-per-page');
        }
    });

    // Ordr table search and export functionality Start here
    
    const $orderSearchInput = $('.order-list-container #order-search');
    const $orderSearchInputvalue = ($('.order-list-container #order-search').val() || '').trim();
    let $clearBtn = $('#order-search-clear');
    if ($orderSearchInputvalue) {
        // If button doesn't exist, append it
        if ($clearBtn.length === 0) {
            $clearBtn = $('<button>')
                .attr({
                    id: 'order-search-clear',
                    class: 'btn btn-primary btn-md',
                    type: 'button'
                })
                .text('Reset')
                .css({ marginLeft: '10px' });

            $orderSearchInput.after($clearBtn);
        }
    } else {
        // Remove the button if input is empty
        $clearBtn.remove();
    }

    $('.order-list-container #order-search').on('keyup', function () {
        //orderTable.search(this.value).draw();
        
        const $input = $(this);
        const value = $input.val().trim();

        // Check if the submit button already exists
        let $submitBtn = $('#order-search-submit');

        if (value) {
            // If button doesn't exist, append it
            if ($submitBtn.length === 0) {
                $submitBtn = $('<button>')
                    .attr({
                        id: 'order-search-submit',
                        class: 'btn btn-primary btn-md',
                        type: 'button'
                    })
                    .text('Search')
                    .css({ marginLeft: '10px' });

                $input.after($submitBtn);
            }
        } else {
            // Remove the button if input is empty
            $submitBtn.remove();
        }
    });
    
    $('.order-list-container').on('click', '#order-search-clear', function () {
        window.location.href = window.location.pathname;
    });

    $('.order-list-container').on('click', '#order-search-submit', function () {
        let o_search = $('#order-search').val().trim();
        let url_params = '';

        console.log('Start');
        if( o_search && o_search != undefined && o_search != '' && o_search != ' ' ){
            console.log('Start IF');
            url_params = '?search='+ encodeURIComponent(o_search);
            $('#order-table_wrapper .dataTables_scrollHead .order-table thead th .filter-box.active_filter').each(function (index) {
                let $this = $(this);
                let slug = $this.data('head_slug');
                let inputVal = $this.find('input').val().trim();

                if (!inputVal) return;

                /*console.log('Index:', index);
                console.log('Slug:', temp);
                console.log('Input Value:', inputVal);*/

                switch (slug) {
                    case 'order_no':
                        url_params += '&o_id=' + encodeURIComponent(inputVal);
                        break;
                    case 'order_date':
                        url_params += '&o_date=' + encodeURIComponent(inputVal);
                        break;
                    case 'order_client_reference':
                        url_params += '&o_ref=' + encodeURIComponent(inputVal);
                        break;
                    case 'order_User':
                        url_params += '&o_user=' + encodeURIComponent(inputVal);
                        break;
                    case 'order_Status':
                        url_params += '&o_status=' + encodeURIComponent(inputVal);
                        break;
                    case 'order_Invoice':
                        url_params += '&o_invoice=' + encodeURIComponent(inputVal);
                        break;
                    case 'order_Total':
                        url_params += '&o_total=' + encodeURIComponent(inputVal);
                        break;
                }
            });

            console.log('url_params:', url_params);

            // ✅ Redirect to same page with parameters
            window.location.href = window.location.pathname + url_params;
        }
    });

    // Ordr table search and export functionality End here

    const addNewSenderBtn = document.getElementById("add-new-sender");
    if (addNewSenderBtn) {
        addNewSenderBtn.addEventListener("click", function () {
            const inputContainer = document.getElementById("new-sender-input");
            if (inputContainer) inputContainer.style.display = inputContainer.style.display === "none" ? "block" : "none";
        });
    }
    const addNewCampaignBtn2 = document.getElementById("add-new-campaign");
    if (addNewCampaignBtn2) {
        addNewCampaignBtn2.addEventListener("click", function () {
            const inputCampaignContainer = document.getElementById("new-campaign-input");
            if (inputCampaignContainer) inputCampaignContainer.style.display = inputCampaignContainer.style.display === "none" ? "block" : "none";
        });
    }

    const emailPrefixInput = document.getElementById("new_sender_email");

    if (emailPrefixInput) {

    // Prevent typing '@' and '.'
    emailPrefixInput.addEventListener("input", function () {
        this.value = this.value.replace(/[@.]/g, '');
    });

    const addSenderToBusinessBtn = document.getElementById("add-new-sender-to-business");
    if (addSenderToBusinessBtn) addSenderToBusinessBtn.addEventListener("click", function () {
        const userId = document.getElementById("business-user-dropdown").value;
        const senderName = document.getElementById("new_sender_name").value.trim();
        const senderEmailInput = emailPrefixInput.value.trim();
        const messageDiv = document.getElementById("sender-message");

        // Reset message
        messageDiv.textContent = '';
        messageDiv.className = 'sender-message error-message';
        messageDiv.style.display = 'none';

        // Simple email validation regex
        // const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        // Validation checks
        if (!userId) {
            messageDiv.textContent = "Please select a business user.";
            messageDiv.style.display = 'block';
            return;
        }

        if (senderName === '') {
            messageDiv.textContent = "Sender name is required.";
            messageDiv.style.display = 'block';
            return;
        }

        // Prevent any '@' or '.' at submit time (safety net)
        if (senderEmailInput === '' || /[@.]/.test(senderEmailInput)) {
            messageDiv.textContent = "Enter only the email prefix (no '@' or '.').";
            messageDiv.style.display = 'block';
            return;
        }

        const senderEmail = senderEmailInput + '@delivery.giftcardsplus.com.au';


        fetch(ajaxData.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'add_sender_to_business_user',
                user_id: userId,
                sender_name: senderName,
                sender_email: senderEmail,
            })
        })
            .then(response => response.json())
            .then(data => {
                messageDiv.style.display = 'block';
                if (data.success) {
                    messageDiv.textContent = "Sender added successfully!";
                    messageDiv.classList.remove('error-message');
                    messageDiv.classList.add('success-message');
            
                    // Clear input fields
                    document.getElementById("new_sender_name").value = '';
                    document.getElementById("new_sender_email").value = '';
            
                    let senderName = data.data.senderName;
                    let senderEmail = data.data.senderEmail;
            
                    let optionElement = `<option selected value="${senderName}" data-email="${senderEmail}">${senderName}</option>`;
            
                    let senderDropdown = $('#sender-dropdown');
                    let selectSenderDropdown = $('#select-sender-dropdown');
            
                    // If sender not in dropdown, append it
                    if (senderDropdown.find(`option[value="${senderName}"]`).length === 0) {
                        senderDropdown.append(optionElement);
                    }
                    if (selectSenderDropdown.find(`option[value="${senderName}"]`).length === 0) {
                        selectSenderDropdown.append(optionElement);
                    }
            
                    // Set selected value and trigger change
                    senderDropdown.val(senderName).trigger('change');
                    selectSenderDropdown.val(senderName).trigger('change');
            
                    // Optional: Update display
                    $('#display-sender').text(senderName);
            
                } else {
                    messageDiv.textContent = "Error: " + (data.data.message || "Something went wrong.");
                    messageDiv.classList.add('error-message');
                }
            })
            .catch(error => {
                messageDiv.textContent = "Unexpected error occurred.";
                messageDiv.classList.add('error-message');
                messageDiv.style.display = 'block';
                console.error("Fetch error:", error);
            });
    });

    } // end if (emailPrefixInput)

    let isSyncingSender = false;

    $('#sender-dropdown').on('change', function () {
        if (isSyncingSender) return;
        isSyncingSender = true;
    
        const selectedVal = $(this).val();
        $('#select-sender-dropdown').val(selectedVal).trigger('change');
        $('#display-sender').text(selectedVal);
    
        isSyncingSender = false;
    });
    
    $('#select-sender-dropdown').on('change', function () {
        if (isSyncingSender) return;
        isSyncingSender = true;
    
        const selectedVal = $(this).val();
        $('#sender-dropdown').val(selectedVal).trigger('change');
        $('#display-sender').text(selectedVal);
    
        isSyncingSender = false;
    });
    
    //==============================

    const addCampaignToBusinessBtn = document.getElementById("add-new-campaign-to-business");
    if (addCampaignToBusinessBtn) addCampaignToBusinessBtn.addEventListener("click", function () {
        const userId = document.getElementById("business-user-dropdown").value;
        // const senderName = document.getElementById("new_sender_name").value;
        // const senderEmail = document.getElementById("new_sender_email").value;
        const messageDiv = document.querySelector(".campaign-message");
        const campaign = document.getElementById("new_campaign_name").value;

        messageDiv.textContent = '';
        messageDiv.className = 'campaign-message'; // Reset classes

        // Simple email validation regex
        // const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        // Validation checks
        if (!userId) {
            messageDiv.textContent = "Please select a business user.";
            messageDiv.classList.add('error');
            return;
        }

        // if (senderName === '') {
        //     messageDiv.textContent = "Sender name is required.";
        //     messageDiv.classList.add('error');
        //     return;
        // }

        // if (!emailRegex.test(senderEmail)) {
        //     messageDiv.textContent = "Please enter a valid email address.";
        //     messageDiv.classList.add('error');
        //     return;
        // }

        fetch(ajaxData.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'add_campaign_to_business_user',
                user_id: userId,
                sender_campaign: campaign,
            })
        })
            .then(response => response.json())
            .then(data => {
                messageDiv.textContent = '';
                messageDiv.className = 'campaign-message'; // reset class

                if (data.success) {
                    messageDiv.textContent = "Campaign saved successfully!";
                    messageDiv.classList.add('success');

                    document.getElementById("new_campaign_name").value = '';
                    // Append the new campaign to the dropdown
                    const campaignDropdown = document.getElementById("campaign-dropdown");
                    const newCampaignValue = campaign;

                    // Check if it already exists (optional safeguard)
                    const exists = Array.from(campaignDropdown.options).some(
                        option => option.value.toLowerCase() === newCampaignValue.toLowerCase()
                    );

                    if (!exists) {
                        const newOption = document.createElement("option");
                        newOption.value = newCampaignValue;
                        newOption.text = newCampaignValue;
                        newOption.selected = true; // auto-select newly added
                        campaignDropdown.appendChild(newOption);
                    } else {
                        campaignDropdown.value = newCampaignValue;
                    }
                } else {
                    messageDiv.textContent = "Error: " + (data.data.message || "Something went wrong.");
                    messageDiv.classList.add('error');
                }
            })
            .catch(error => {
                messageDiv.textContent = "Unexpected error occurred.";
                messageDiv.classList.add('error');
                console.error("Fetch error:", error);
            });
    });

    //=======================
    function checkAllFieldsValid() {
        const isBusinessUserValid = jQuery('#business-user-dropdown').val() !== null;
        // const isOrderIdValid = jQuery('#order-id').val().trim() !== '';
        const isSenderValid = jQuery('#sender-dropdown').val() !== null;
        const isOrderNameValid = jQuery('#order-name').val().trim() !== '';
    
        if (isBusinessUserValid && isSenderValid && isOrderNameValid) {
            const errorEl = document.getElementById("error-message");
            errorEl.textContent = "";
            errorEl.style.color = "";
        }
    }

    jQuery('#business-user-dropdown').on('change', function () {
        if (jQuery(this).val()) {
            jQuery(this).removeClass('is-invalid-field');
            jQuery(this).closest('.top-dropdown-block').find('.invalid-feedback.error-message').hide();
            checkAllFieldsValid();
        }
    });
    
    jQuery('#sender-dropdown').on('change', function () {
        if (jQuery(this).val()) {
            jQuery(this).removeClass('is-invalid-field');
            jQuery('#sender-error-message').hide();
            checkAllFieldsValid();
        }
    });
    
    jQuery('#order-name').on('input', function () {
        if (jQuery(this).val().trim() !== '') {
            jQuery(this).removeClass('is-invalid-field');
            jQuery(this).siblings('.invalid-feedback').hide();
            checkAllFieldsValid();
        }
    });
    
    // jQuery('#order-id').on('input', function () {
    //     if (jQuery(this).val().trim() !== '') {
    //         jQuery(this).removeClass('is-invalid-field');
    //         jQuery(this).siblings('.invalid-feedback').hide();
    //         checkAllFieldsValid();
    //     }
    // });
    

    function validateForm() {
        let isValid = true;

        // Check if Business User is selected
        const businessUserVal = $('#business-user-dropdown').val();
        if (!businessUserVal || businessUserVal === '') {
            jQuery('#business-user-dropdown').addClass('is-invalid-field');
            jQuery('#business-user-dropdown').closest('.top-dropdown-block').find('.invalid-feedback.error-message').show();
            isValid = false;
        } else {
            jQuery('#business-user-dropdown').removeClass('is-invalid-field');
            jQuery('#business-user-dropdown').closest('.top-dropdown-block').find('.invalid-feedback.error-message').hide();
        }


        // if (jQuery('#order-id').val().trim() === '') {
        //     jQuery('#order-id').addClass('is-invalid-field');
        //     jQuery('#order-id').siblings('.invalid-feedback').show();
        //     isValid = false;
        // } else {
        //     jQuery('#order-id').removeClass('is-invalid-field');
        //     jQuery('#order-id').siblings('.invalid-feedback').hide();
        // }

        // Check if Sender Details are selected
        if (jQuery('#sender-dropdown').val() === null) {
            jQuery('#sender-dropdown').addClass('is-invalid-field');
            $('#sender-error-message').show(); // Show the error message
            isValid = false;
        } else {
            jQuery('#sender-dropdown').removeClass('is-invalid-field');
            $('#sender-error-message').hide(); // Hide the error message
        }

        // Check if Order Name is filled
        if (jQuery('#order-name').val().trim() === '') {
            jQuery('#order-name').addClass('is-invalid-field');
            jQuery('#order-name').siblings('.invalid-feedback').show();
            isValid = false;
        } else {
            jQuery('#order-name').removeClass('is-invalid-field');
            jQuery('#order-name').siblings('.invalid-feedback').hide();
        }


        // Check if Client Reference is filled
        // if (jQuery('#client-reference').val().trim() === '') {
        //     jQuery('#client-reference').addClass('is-invalid-field');
        //     isValid = false;
        // } else {
        //     jQuery('#client-reference').removeClass('is-invalid-field');
        // }

        return isValid;
    }

    jQuery('.next-btn').click(function () {

        // console.log('Cligcytycjg');

        $('#multi-step-form').addClass('full-width');
        $('#page-spacer-top').hide();
        $('#back-to-order-summary').hide();


        if (!validateForm()) {
            console.log('This is clicked');
            document.getElementById("error-message").textContent = "Please fill all required fields before proceeding.";
            return;
        }
        document.getElementById("error-message").textContent = "";

        jQuery('#display-order-name').text(jQuery('#order-name').val());
        // jQuery('#display-order-id').text(jQuery('#order-id').val());
        jQuery('#display-client-reference').text(jQuery('#client-reference').val());
        jQuery('#display-sender').text(jQuery('#sender-dropdown').val());

        var type = jQuery(this).attr('data-type');
        jQuery('#new-order-form').hide();
        console.log('type is ..',type);
        if (type == 'bulk') {
            var edit_order_id = parseInt(jQuery(this).data('edit_order'));
            if( edit_order_id > 0 && jQuery('#multi-step-form .gift-card-container #customisation-next-btn').length ){
                jQuery('#multi-step-form .gift-card-container #customisation-next-btn').trigger('click');
                jQuery('#multi-step-form.manual-order').removeClass('d-none');
                jQuery('#multi-step-form-bulk').addClass('d-none');
                jQuery('#multi-step-form .step-indicator .back-to-customisation').trigger('click');
                setTimeout(() => {
                    console.log('im working after that');
                    jQuery('#multi-step-form .customisation-container .customisation-wrapper #personalise-all').prop('checked', false).trigger('change');
                    jQuery('#multi-step-form .customisation-container .gift-card-slider .gift-card-slide #gift-card-1').prop('checked', true).trigger('change');

                    console.log('#delivery-next-btn');
                    jQuery('.customisation-container #delivery-next-btn').trigger('click');
                    jQuery('.step.back-to-customisation').css('pointer-events', 'none');

                },200);
                setTimeout(() => {
                    jQuery('#multi-step-form > .container').slideDown();
                },300);
                jQuery('#back-to-recipient-form').attr('id', 'back-to-order-form');
            }else{
                if (jQuery('#multi-step-form.manual-order').hasClass('d-none')) {
                } else {
                    jQuery('#multi-step-form.manual-order').addClass('d-none');
                }
                jQuery('#multi-step-form-bulk').removeClass('d-none');

                jQuery('#multi-step-form-bulk #display-order-name').text(jQuery('#order-name').val());
                // jQuery('#multi-step-form-bulk #display-order-id').text(jQuery('#order-id').val());
                jQuery('#multi-step-form-bulk #display-client-reference').text(jQuery('#client-reference').val());
                jQuery('#multi-step-form-bulk #display-sender').text(jQuery('#sender-dropdown').val());
            }
        } else {
            if (jQuery('#multi-step-form-bulk').hasClass('d-none')) {
            } else {
                jQuery('#multi-step-form-bulk').addClass('d-none');
            }
            jQuery('#multi-step-form.manual-order').removeClass('d-none');
        }

        if( jQuery('.new-order-form-container').data('order_type') == 'manual' ){
            const count = document.querySelectorAll('.editable-row').length;
            if( count <= 0 ){
                jQuery('#add-recipient-btn-wrap #add-new-recipient-btn').trigger('click');
            }
        }
    });
    
    jQuery('#create-order-save-btn').on('click', function (e) {
        if (!validateForm()) {
            // console.log('This is clickedddd');
            document.getElementById("error-message").textContent = "Please fill all required fields before proceeding.";
            return;
        } else{
            const action = jQuery(this).data("action"); // or e.currentTarget.dataset.action
            const currentStep = e.currentTarget.getAttribute("data-step");
            const status = e.currentTarget.getAttribute("data-status");
            const order_id = e.currentTarget.getAttribute("data-order-id");

            if (action === "save-draft") {
                const btn = e.currentTarget;
                if (btn && btn.id === 'create-order-save-btn') {
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    btn.classList.add('btn-disabled');
                }

                const nextStepBtn = document.querySelector('.create-order-next-btn');
                if (nextStepBtn) {
                    nextStepBtn.disabled = true;
                    nextStepBtn.setAttribute('aria-busy', 'true');
                    nextStepBtn.classList.add('btn-disabled');
                }

                const recipients = [];

                jQuery('#recipient-table tbody tr.editable-row').each(function () {
                    const row = jQuery(this);
                    const recipient = {
                        first_name: row.find('.recipient-first-name').val(),
                        surname: row.find('.recipient-surname').val(),
                        email: row.find('.recipient-email').val(),
                        phone: row.find('.recipient-phone').val(),
                        gift_cards: [],
                        gift_message: row.find('.gift-message').val(),
                        delivery_method: row.find('.delivery-method').val()
                    };
            
                    row.find('.gift-card-item').each(function () {
                        const card = jQuery(this);
                        recipient.gift_cards.push({
                            sku: card.data('sku'),
                            title: card.data('title'),
                            price: parseFloat(card.find('.gift-card-price').text().replace('$', '')),
                            image: card.find('.gift-card-image').attr('src')
                        });
                    });
            
                    recipients.push(recipient);
                });
            
                const businessDetails = {
                    business_id: jQuery('#business-user-dropdown').val(),
                    order_type: jQuery('#new-order-form-container').data('order_type'),
                    business_name: jQuery('#business-user-dropdown option:selected').text().trim(),
                    sender_name: jQuery('#sender-dropdown option:selected').text(),
                    sender_email: jQuery('#sender-dropdown option:selected').data('email'),
                    campaign: jQuery('#campaign-dropdown').val(),
                    order_name: jQuery('#order-name').val(),
                    po_number: jQuery('#related-po').val(),
                    additional_reference: jQuery('#additional-reference').val(),
                    client_reference: jQuery('#client-reference').val(),
                };
                // console.log('businessDesdsdstails--------- : ',businessDetails);
                console.log('call from here');
                jQuery.ajax({
                    url: draft_order_ajax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'save_draft_order_with_recipients',
                        nonce: draft_order_ajax.nonce,
                        recipients: JSON.stringify(recipients),
                        current_step: currentStep,
                        business_details: JSON.stringify(businessDetails),
                        sender_name: jQuery('#sender-name').val(),
                        sender_email: jQuery('#sender-email').val(),
                        status: status,
                        order_id: order_id,
                    },
                    success: function (response) {
                        const messageBox = document.getElementById("save-create-draft-message");
                        messageBox.classList.remove('success-message', 'error-message');
                        if (response.success) {
                           
                            if( response.data.is_update ){
                                messageBox.textContent = 'Order #'+response.data.order_id+' updated successfully...';
                            }else{
                                messageBox.textContent = 'Order #'+response.data.order_id+'  created successfully...';                                        
                            }
                            messageBox.classList.add('success-message');
                            messageBox.style.display = "block";

                            const newOrderId = response.data.order_id;
                            const ordrUpdate = response.data.is_update;

                            btn.setAttribute('data-order-id', newOrderId);
                            if (ordrUpdate === false) {
                                btn.setAttribute('data-status', 'update');
                            }

                            // const saveBtn = document.getElementById('delivery-save-btn');
                            // if (saveBtn) {
                            //     saveBtn.setAttribute('data-order_id', newOrderId);
                            // }

                            ['customisation-save-btn', 'delivery-save-btn', 'place-order-btn'].forEach(id => {
                                const el = document.getElementById(id);
                                if (el) el.setAttribute('data-order-id', newOrderId);
                                el.setAttribute('data-status', 'update');
                            });
                            
                            setTimeout(() => {
                                messageBox.classList.remove('success-message', 'error-message');
                                messageBox.textContent = "";
                                btn.disabled = false;
                                nextStepBtn.disabled = false;
                                btn.setAttribute('aria-busy', 'false');
                                nextStepBtn.setAttribute('aria-busy', 'false');
                                btn.classList.remove('btn-disabled');
                                nextStepBtn.classList.remove('btn-disabled');
                            }, 3000);
                    
                            // setTimeout(() => {
                            //     var order_page_url = window.location.origin+'/order';
                            //     window.location.href = order_page_url;
                            // }, 3000);                        
                        } else {
                            if (btn && btn.id === 'create-order-save-btn') {
                                btn.disabled = false;
                                nextStepBtn.disabled = false;
                                btn.removeAttribute('aria-busy');
                                nextStepBtn.removeAttribute('aria-busy');
                                btn.classList.remove('btn-disabled');
                                nextStepBtn.classList.remove('btn-disabled');
                            }
                            messageBox.textContent = 'Error: ' + response.data.message;
                            messageBox.classList.add('error-message');
                            messageBox.style.display = "block";
                        }
                    },
                    error: function () {
                        if (btn && btn.id === 'create-order-save-btn') {
                            btn.disabled = false;
                            nextStepBtn.disabled = false;
                            btn.removeAttribute('aria-busy');
                            btn.classList.remove('btn-disabled');
                            nextStepBtn.removeAttribute('aria-busy');
                            nextStepBtn.classList.remove('btn-disabled');
                        }
                        const messageBox = document.getElementById("save-draft-message");
                        if(messageBox.length){
                            messageBox.classList.remove('success-message', 'error-message');
                            messageBox.textContent = 'Unexpected error occurred.';
                            messageBox.classList.add('error-message');
                            messageBox.style.display = "block";    
                        }
                    }
                });
            
                return;
            }

            // console.log('Form is valid, proceed to save');
        }
        e.preventDefault();
    }); 
    jQuery("#download-template").on("click", function (e) {
        e.preventDefault();
        const csvUrl = (typeof user_fetch_ajax !== 'undefined' && user_fetch_ajax.siteUrl) ? user_fetch_ajax.siteUrl.replace(/\/$/, '') + '/wp-content/uploads/Demo_recipient_details.csv' : '/wp-content/uploads/Demo_recipient_details.csv';
        const downloadLink = jQuery("<a>")
            .attr("href", csvUrl)
            .attr("download", csvUrl.split('/').pop())
            .appendTo("body");

        downloadLink[0].click();
        downloadLink.remove();
    });
    jQuery('#back-to-order-form').click(function () {
        jQuery('#multi-step-form').addClass('d-none');
        jQuery('#new-order-form').show();
    });
});
// Works inside jQuery(document).ready(function ($) { ... });


jQuery(document).ready(function ($) {
    // function formatAustralianPhoneNumber(value) {
    //     // Remove all characters except digits
    //     let digits = value.replace(/\D/g, '');

    //     // Remove leading 0 if present
    //     if (digits.startsWith('04')) {
    //         digits = digits.substring(1); // convert 04xx to 4xx
    //     }

    //     if (digits.startsWith('4')) {
    //         digits = '61' + digits; // convert 4xx to 614xx...
    //     }

    //     if (!digits.startsWith('61')) {
    //         digits = '61' + digits;
    //     }

    //     // Final format: +61 4XX XXX XXX
    //     if (digits.length >= 11) {
    //         return `+${digits.slice(0, 2)} ${digits.slice(2, 5)} ${digits.slice(5, 8)} ${digits.slice(8, 11)}`;
    //     }

    //     return '+' + digits;
    // }

    window.validateInputField = function ($input) {
        const val = $input.val().trim();
        const name = $input.attr("name");
        let error = "";

        const $row = $input.closest("tr");
        const emailVal = $row.find('[name="recipient_email"]').val().trim();
        let phoneVal = $row.find('[name="recipient_phone"]').val().trim();

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const phoneRegex = /^(?:\+61\s?4\d{2}\s?\d{3}\s?\d{3}|04\d{2}\s?\d{3}\s?\d{3})$/;

        // Allow digits, + and space only
        // jQuery(document).on('blur', '.recipient-phone', function () {
        //     const formatted = formatAustralianPhoneNumber(jQuery(this).val());
        //     jQuery(this).val(formatted);
        // });

        if (phoneVal.startsWith('61')) {
            phoneVal = '+' + phoneVal;                                
        }

        const emailValid = emailRegex.test(emailVal);
        const phoneValid = phoneRegex.test(phoneVal);

        jQuery("#recipient-email-phone-validate-message").hide().text('');
        $input.next('.validation-error').remove();


        // Validate first name
        if (name === "recipient_firstname") {
            if (!val) {
                error = "This field is required.";
            }
        }

        // Validate email & phone
        if (name === "recipient_email" || name === "recipient_phone") {
            if (!emailVal && !phoneVal) {
                const errorMsg = "Please provide an email address or mobile number for Gift Card delivery to each recipient.";
                jQuery("#recipient-email-phone-validate-message").text(errorMsg).show();
            
                const $emailInput = $row.find('[name="recipient_email"]');
                const $phoneInput = $row.find('[name="recipient_phone"]');
            
                $emailInput.addClass("invalid-field");
                $phoneInput.addClass("invalid-field");
            
                // Scroll to the first input (email)
                if ($emailInput.length) {
                    $emailInput.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            
                return errorMsg;
            } else {
                // Check specific email validity
                if (emailVal && !emailValid && name === "recipient_email") {
                    error = "Enter a valid email address.";
                }

                // Check specific phone validity
                if (phoneVal && !phoneValid && name === "recipient_phone") {
                    error = "Enter a valid phone number starting with +614 followed by 8 digits.";
                }
            }
        }

        // Add error styling and inline message
        if (error && error !== "Please provide an email address or mobile number for Gift Card delivery to each recipient.") {
            $input.addClass("invalid-field");
            $input.after(`<div class="validation-error" style="color:red; font-size: 12px; margin-top: 4px;">${error}</div>`);
            $input.attr("title", error);

            if (!$input.data('scrolled')) {
                $input[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $input.data('scrolled', true);
            }
        } else {
            $input.removeClass("invalid-field");
            $input.removeAttr("title");
        }

        return error;
    };

});

jQuery(document).ready(function ($) {
    // User search with AJAX
    // jQuery('#user-search').on('keyup', function () {
    //     var searchText = jQuery(this).val().toLowerCase();

    //     jQuery('#recipient-table tbody tr').each(function () {
    //         var rowText = jQuery(this).text().toLowerCase();

    //         if (rowText.includes(searchText)) {
    //             jQuery(this).show();
    //         } else {
    //             jQuery(this).hide();
    //         }
    //     });
    // });



    // CSV CODE START HERE ================================================================================

    // Toggle all checkboxes on header checkbox change
    $(document).on('change', '#select-all', function () {
        let checked = $(this).is(':checked');

        // Only check checkboxes that are visible (i.e., on the current page)
        $('#csv-preview-body .row-checkbox').each(function () {
            $(this).prop('checked', checked).trigger('change');
        });
    });
    function updateSelectAllCheckbox() {
        const visibleCheckboxes = $('#csv-preview-body .row-checkbox');
        const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.filter(':checked').length === visibleCheckboxes.length;
        $('#select-all').prop('checked', allChecked);
    }

    let csvData = []; // Store CSV data
    let invalidFields = new Set();
    let checkedRows = {}; // Store checked row states
    let selectedGiftCards = {}; // Store selected gift cards per row
    let editedFields = {}; // Store manually edited email & phone fields

    const requiredHeaders = ["Recipient First Name*", "Surname", "Email", "Phone Number", "Gift Card"];

    const rowsPerPage = 5; // Rows per page
    let currentPage = 1;

    jQuery('#csv-preview-container').hide();
    jQuery('#pagination-container').hide();
    jQuery('#confirm-add').prop('disabled', true);

    // Handle CSV upload
    jQuery('#bulk-upload-order').on('click', function () {
        jQuery('#csv-file-input').val('');
        jQuery('#csv-file-input').click();
    });
    // Function to display error message
    function showCsvError(message) {
        jQuery("#csv-error-message").text(message).show();

        // Hide the error message after 4 seconds (optional)
        setTimeout(() => {
            jQuery("#csv-error-message").fadeOut();
        }, 4000);
    }

    jQuery('#csv-file-input').on('change', function (event) {
        let file = event.target.files[0];
        if (!file) return;
        if (file.type !== "text/csv" && !file.name.endsWith(".csv")) {
            showCsvError("Please upload a valid CSV file.");
            return;
        }

        let reader = new FileReader();
        reader.onload = function (e) {
            let csvContent = e.target.result.trim();
            let rows = csvContent.split("\n").map(row => row.trim()).filter(row => row);
            if (rows.length < 2) {
                showCsvError("CSV file is empty or missing data.");
                return;
            }
            let errorMessageElement = jQuery('#csv-error-message');
            let headers = rows[0].split(",").map(header => header.trim());
            if (!arraysEqual(headers, requiredHeaders)) {
                jQuery("#invalid-details-error-message").text("Invalid CSV file. Headers must be: " + requiredHeaders.join(", ")).show();
                setTimeout(() => {
                    jQuery("#invalid-details-error-message").fadeOut();
                }, 4000); // Hide error message after 5 seconds
                return;
            }
            else {
                errorMessageElement.hide(); // Hide the error message if valid
            }

            jQuery('#csv-preview-body').empty();
            // csvData = [];
            invalidFields.clear();
            checkedRows = {}; // Reset checked state
            editedFields = {}; // Reset edited fields
            selectedGiftCards = {}; // Reset gift card selections
            currentPage = 1; // ✅ Reset pagination to page 1
            rows.slice(1).forEach((row, rowIndex) => {
                let columns = row.split(",").map(col => col.trim());
                if (columns.length !== 5) return;

                let [firstName, surname, email, phone, giftCard] = columns;

                // Validate first name
                if (!firstName) {
                    invalidFields.add(`${rowIndex}-0`);
                } else {
                    invalidFields.delete(`${rowIndex}-0`);
                }

                // Validate email and phone
                let isValidEmail = validateEmail(email);
                //let isValidPhone = /^\+61\d{10}$/.test(phone);
                let isValidPhone = validatePhone(phone);

                // let phoneIsNumeric = isNumeric(phone);

                // If both are empty or invalid
                // if (!email && !phone) {
                //     invalidFields.add(`${rowIndex}-2`);
                //     invalidFields.add(`${rowIndex}-3`);
                // } else {
                //     // Email validation
                //     if (email && !isValidEmail) {
                //         invalidFields.add(`${rowIndex}-2`);
                //     } else {
                //         invalidFields.delete(`${rowIndex}-2`);
                //     }

                //     // Phone validation
                //     if (phone && !isValidPhone) {
                //         invalidFields.add(`${rowIndex}-3`);
                //     } else {
                //         invalidFields.delete(`${rowIndex}-3`);
                //     }
                // }

                if (!email && !phone) {
                    // Both empty → both invalid
                    invalidFields.add(`${rowIndex}-2`);
                    invalidFields.add(`${rowIndex}-3`);
                } else if (isValidEmail && !isValidPhone && phone) {
                    // Email valid, phone invalid format
                    invalidFields.delete(`${rowIndex}-2`);
                    invalidFields.add(`${rowIndex}-3`);
                } else if (isValidPhone && !isValidEmail && email) {
                    // Phone valid, email invalid format
                    invalidFields.delete(`${rowIndex}-3`);
                    invalidFields.add(`${rowIndex}-2`);
                } else {
                    // At least one valid (and the other empty is fine)
                    if (isValidEmail) invalidFields.delete(`${rowIndex}-2`);
                    if (isValidPhone) invalidFields.delete(`${rowIndex}-3`);
                }
                
                csvData.push(columns);
            });


            updatePagination();
            updateConfirmButtonState();

            if (csvData.length > 0) {
                jQuery('#csv-preview-container').show();
                jQuery('#recipient-email-phone-validate-message').hide();
                jQuery('#pagination-container').show();
            }
        };

        reader.readAsText(file);
    });

    // ✅ Function to enable/disable confirm button
    function updateConfirmButtonState() {
        // Get all checked row indexes
        let checkedRowIndexes = Object.keys(checkedRows).filter(rowIndex => checkedRows[rowIndex]);

        // Disable if no rows are checked
        if (checkedRowIndexes.length === 0) {
            jQuery('#confirm-add').prop('disabled', true);
            return;
        }

        // Check if every checked row is valid
        let allCheckedRowsValid = checkedRowIndexes.every(rowIndex => {
            // Validate first name (not empty)
            let firstNameValid = !invalidFields.has(`${rowIndex}-0`);

            // Validate email and phone - at least one valid
            let emailValid = !invalidFields.has(`${rowIndex}-2`);
            let phoneValid = !invalidFields.has(`${rowIndex}-3`);
            let emailOrPhoneValid = emailValid || phoneValid;

            return firstNameValid && emailOrPhoneValid;
        });

        // Enable or disable confirm button based on all rows validity
        jQuery('#confirm-add').prop('disabled', !allCheckedRowsValid);
    }

    // Function to display paginated data
    function isNumeric(str) {
        return /^\d*$/.test(str); // Only digits allowed, empty string allowed here (empty handled elsewhere)
    }

    function displayPage(page) {
        jQuery('#csv-preview-body').empty();

        jQuery('.table-container').hide();
        jQuery('#customisation-next-btn').hide();

        let start = (page - 1) * rowsPerPage;
        let end = start + rowsPerPage;
        let pageData = csvData.slice(start, end);

        pageData.forEach((columns, rowIndex) => {
            let globalRowIndex = start + rowIndex;
            let isChecked = checkedRows[globalRowIndex] ? 'checked' : ''; // Restore checked state

            let emailValue = editedFields[`${globalRowIndex}-2`] || columns[2] || ''; // Restore edited email
            let phoneValue = editedFields[`${globalRowIndex}-3`] || columns[3] || ''; // Restore edited phone

            if ( phoneValue != '' &&  phoneValue.startsWith('61') ) {
                phoneValue = '+' + phoneValue;
            }
            if ( phoneValue != '' &&  phoneValue.startsWith('4') ) {
                phoneValue = '0' + phoneValue;
            }

            let isValidEmail = validateEmail(emailValue);
            let isValidPhone = validatePhone(phoneValue);
            //let phoneIsCorrectFormat = /^\+61\d{10}$/.test(phoneValue);


            let emailEditable = 'contenteditable="false"';
            let phoneEditable = 'contenteditable="false"';
            let emailClass = '';
            let phoneClass = '';
            let emailError = '';
            let phoneError = '';

            // Always validate phone and email separately for errors
            if (!isValidEmail && !isValidPhone) {
                // Both invalid → mark both error and editable
                emailClass = 'error';
                phoneClass = 'error';
                emailError = 'Invalid Email';
                phoneError = 'Invalid Phone';
                emailEditable = 'contenteditable="true"';
                phoneEditable = 'contenteditable="true"';
            } else if( emailValue != '' && phoneValue != '' ){
                // If email valid but phone invalid or non-numeric → mark phone error/editable
                if (isValidEmail && !isValidPhone) {
                    phoneClass = 'error';
                    phoneError = 'Invalid Phone';
                    phoneEditable = 'contenteditable="true"';
                }
                // If phone valid but email invalid → mark email error/editable
                if (isValidPhone && !isValidEmail) {
                    emailClass = 'error';
                    emailError = 'Invalid Email...';
                    emailEditable = 'contenteditable="true"';
                }
                // If both valid → no error
                // If one valid and the other empty → no error on empty
            }else if( emailValue != '' || phoneValue != '' ){ 
                if( emailValue != '' ){
                    if(!isValidEmail){
                        emailClass = 'error';
                        emailError = 'Invalid Email...';
                        emailEditable = 'contenteditable="true"';
                    }
                }else if(  phoneValue != '' ){
                    if( !isValidPhone ){
                        phoneClass = 'error';
                        phoneError = 'Invalid Phone';
                        phoneEditable = 'contenteditable="true"';
                    }
                }
            }

            // First name validation
            let firstNameValue = editedFields[`${globalRowIndex}-0`] || columns[0] || '';
            let isValidFirstName = firstNameValue.trim() !== '';
            let firstNameClass = isValidFirstName ? '' : 'error';
            let firstNameEditable = isValidFirstName ? 'contenteditable="false"' : 'contenteditable="true"';

            let giftCardHTML = (selectedGiftCards[globalRowIndex] || []).join("");

            jQuery('#csv-preview-body').append(`
                <tr data-row="${globalRowIndex}">
                    <td><input type="checkbox" class="row-checkbox custom-checkbox" data-row="${globalRowIndex}" ${isChecked}></td>
                    <td ${firstNameEditable} class="editable ${firstNameClass}" data-row="${globalRowIndex}" data-col="0">${firstNameValue}</td>
                    <td>${columns[1] || ''}</td>
                    <td ${emailEditable} class="editable ${emailClass}" data-row="${globalRowIndex}" data-col="2" data-error="${emailError}">${emailValue}</td>
                    <td ${phoneEditable} class="editable ${phoneClass}" data-row="${globalRowIndex}" data-col="3" data-error="${phoneError}">${phoneValue}</td>
                    <td class="gift-card-column">
                        <div class="gift-table">
                            <div class="gift-table-wrapper">
                                ${giftCardHTML} 
                            </div>
                            <button class="btn btn-outline-secondary gift-card-btn">+</button>
                        </div>
                    </td>
                </tr>
            `);
        });

        updateSelectAllCheckbox();
        updateConfirmButtonState();
    }

    jQuery(document).on("input", ".editable[data-col='0']", function () {
        let rowIndex = jQuery(this).data("row");
        let colIndex = 0;
        let value = jQuery(this).text().trim();

        editedFields[`${rowIndex}-${colIndex}`] = value;

        if (value === '') {
            invalidFields.add(`${rowIndex}-0`);
            jQuery(this).addClass("error");
        } else {
            invalidFields.delete(`${rowIndex}-0`);
            jQuery(this).removeClass("error");
        }
        updateConfirmButtonState();
    });

    // ✅ Store checkbox state when checked/unchecked
    $(document).on('change', '.row-checkbox', function () {
        // Only check among visible checkboxes
        let visibleCheckboxes = $('#csv-preview-body .row-checkbox');
        let allChecked = visibleCheckboxes.length === visibleCheckboxes.filter(':checked').length;
        $('#select-all').prop('checked', allChecked);

        let rowIndex = $(this).data('row');
        checkedRows[rowIndex] = $(this).is(':checked'); // persist the state
        updateConfirmButtonState();
    });

    jQuery(document).on("input", ".editable", function () {
        let rowIndex = jQuery(this).data("row");
        let colIndex = jQuery(this).data("col");
        let value = jQuery(this).text().trim();
    
        editedFields[`${rowIndex}-${colIndex}`] = value;
    
        if (colIndex == 2 || colIndex == 3) {
            // Always pull latest values for both fields
            let email = editedFields[`${rowIndex}-2`] || jQuery(`[data-row="${rowIndex}"][data-col="2"]`).text().trim();
            let phone = editedFields[`${rowIndex}-3`] || jQuery(`[data-row="${rowIndex}"][data-col="3"]`).text().trim();
    
            let isValidEmail = validateEmail(email);
            let isValidPhone = validatePhone(phone);
    
            // Reset both first
            invalidFields.delete(`${rowIndex}-2`);
            invalidFields.delete(`${rowIndex}-3`);
            jQuery(`[data-row="${rowIndex}"][data-col="2"]`).removeClass("error").removeAttr("data-error");
            jQuery(`[data-row="${rowIndex}"][data-col="3"]`).removeClass("error").removeAttr("data-error");
    
            if (!email && !phone) {
                // Both empty
                invalidFields.add(`${rowIndex}-2`);
                invalidFields.add(`${rowIndex}-3`);
                jQuery(`[data-row="${rowIndex}"][data-col="2"]`).addClass("error").attr("data-error", "Email or Phone required");
                jQuery(`[data-row="${rowIndex}"][data-col="3"]`).addClass("error").attr("data-error", "Email or Phone required");
            } else {
                if (email && !isValidEmail) {
                    invalidFields.add(`${rowIndex}-2`);
                    jQuery(`[data-row="${rowIndex}"][data-col="2"]`).addClass("error").attr("data-error", "Invalid Email Format");
                }
                if (phone && !isValidPhone) {
                    invalidFields.add(`${rowIndex}-3`);
                    jQuery(`[data-row="${rowIndex}"][data-col="3"]`).addClass("error").attr("data-error", "Invalid Phone Format");
                }
            }
        }
    
        // ✅ First Name Validation (unchanged)
        if (colIndex == 0) {
            if (value === '') {
                invalidFields.add(`${rowIndex}-0`);
                jQuery(this).addClass("error");
            } else {
                invalidFields.delete(`${rowIndex}-0`);
                jQuery(this).removeClass("error");
            }
        }
    
        updateConfirmButtonState();
    });
    

    function updatePagination() {
        let totalPages = Math.ceil(csvData.length / rowsPerPage);
        let paginationHTML = `<ul class="pagination custom-pagination">`;

        // Previous Button
        paginationHTML += `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a href="#" class="page-link prev" data-page="${currentPage - 1}">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M12.8416 6.175L11.6666 5L6.66663 10L11.6666 15L12.8416 13.825L9.02496 10L12.8416 6.175Z" fill="#2B2B2B"></path>
                            </svg>
                </a>
            </li>
        `;

        // Pages Logic
        const maxVisible = 5;
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) {
                paginationHTML += generatePageItem(i, currentPage);
            }
        } else {
            if (currentPage <= 4) {
                for (let i = 1; i <= 5; i++) {
                    paginationHTML += generatePageItem(i, currentPage);
                }
                paginationHTML += `<li class="dots">...</li>`;
                paginationHTML += generatePageItem(totalPages, currentPage);
            } else if (currentPage >= totalPages - 3) {
                paginationHTML += generatePageItem(1, currentPage);
                paginationHTML += `<li class="dots">...</li>`;
                for (let i = totalPages - 4; i <= totalPages; i++) {
                    paginationHTML += generatePageItem(i, currentPage);
                }
            } else {
                paginationHTML += generatePageItem(1, currentPage);
                paginationHTML += `<li class="dots">...</li>`;
                for (let i = currentPage - 1; i <= currentPage + 1; i++) {
                    paginationHTML += generatePageItem(i, currentPage);
                }
                paginationHTML += `<li class="dots">...</li>`;
                paginationHTML += generatePageItem(totalPages, currentPage);
            }
        }

        // Next Button
        paginationHTML += `
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a href="#" class="page-link next page-item" data-page="${currentPage + 1}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M8.08748 5L6.91248 6.175L10.7291 10L6.91248 13.825L8.08748 15L13.0875 10L8.08748 5Z" fill="#2B2B2B"></path>
                    </svg>
                </a>
            </li>
        `;

        paginationHTML += `</ul>`;

        jQuery('#pagination-container').html(paginationHTML);

        jQuery('.page-link').on('click', function (e) {
            e.preventDefault();
            const page = parseInt(jQuery(this).data('page'));
            if (!isNaN(page) && page >= 1 && page <= totalPages) {
                currentPage = page;
                displayPage(currentPage);
                updatePagination();
            }
        });

        displayPage(currentPage);
    }

    function generatePageItem(page, current) {
        return `
            <li class="page-item">
                <a href="#" class="page-link ${page === current ? 'active' : ''}" data-page="${page}">${page}</a>
            </li>
        `;
    }

    // Utility functions
    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function validatePhone(phone) {
        const regex = /^(?:\+614\d{8}|04\d{8})$/; 
        if (phone.startsWith('61')) {
            phone = '+' + phone;                                
        }
        return regex.test(phone);
        //return /^\+61\d{9}$/.test(phone);
    }

    function arraysEqual(arr1, arr2) {
        return JSON.stringify(arr1) === JSON.stringify(arr2);
    }
    // ✅ Handle Confirm & Add button click
    jQuery('#confirm-add').on('click', function () {
        jQuery('#customisation-next-btn').show();
        jQuery("#invalid-details-error-message").hide(); // Hide previous errors
        jQuery("#success-message").hide(); // Hide previous success messages
        let addedData = [];
        let hasInvalidRow = false;

        Object.keys(checkedRows).forEach(rowIndex => {
            if (checkedRows[rowIndex]) {
                let firstName = editedFields[`${rowIndex}-0`] || csvData[rowIndex][0] || '';
                let surname = csvData[rowIndex][1] || '';
                let email = editedFields[`${rowIndex}-2`] || csvData[rowIndex][2] || '';
                let rawPhone = editedFields[`${rowIndex}-3`] || csvData[rowIndex][3] || '';
                // let phone = rawPhone.replace(/\D/g, ''); // <-- Remove non-numeric chars here
                let phone = rawPhone.trim(); // keep as-is

                // let giftCard = selectedGiftCards[rowIndex] || csvData[rowIndex][4] || '<button class="btn btn-outline-secondary gift-card-btn">+</button>';

                // Ensure email and phone are valid
                if (!validateEmail(email) && !validatePhone(phone)) {
                    //console.log('babdas===');
                    hasInvalidRow = true;
                    return; // Skip adding this row
                }
                // Ensure email and phone are valid before adding
                // if (!validateEmail(email) || !validatePhone(phone)) return;
                // ✅ Check if a gift card is selected
                let giftCardContent = selectedGiftCards[rowIndex] ? selectedGiftCards[rowIndex].join('') + '<button class="btn btn-outline-secondary gift-card-btn">+</button>' : '<button class="btn btn-outline-secondary gift-card-btn">+</button>';

                addedData.push([firstName, surname, email, phone, giftCardContent]);
            }
        });
        if (hasInvalidRow) {
            jQuery("#invalid-details-error-message").text("Please add valid details.").show();
            setTimeout(() => {
                jQuery("#invalid-details-error-message").fadeOut();
            }, 5000); // Hide invalid-details-error message after 5 seconds
            return;
        }

        if (addedData.length === 0) {
            jQuery("#invalid-details-error-message").text("No valid recipients selected.").show();
            setTimeout(() => {
                jQuery("#invalid-details-error-message").fadeOut();
            }, 5000); // Hide error message after 5 seconds
            return;
        }

        // ✅ Remove "No recipients added." message if present
        let recipientTableBody = jQuery('#recipient-table tbody');
        if (recipientTableBody.find('tr').length === 1 && recipientTableBody.find('td').length === 1) {
            recipientTableBody.empty(); // Clear placeholder row
        }

        let $firstRow = recipientTableBody.find("tr.editable-row").first();
        if ($firstRow.length) {
            // console.log('111111111111111111111');
            let fNameVal = $firstRow.find(".recipient-first-name").val()?.trim();
            let lNameVal = $firstRow.find(".recipient-surname").val()?.trim();
            let emailVal = $firstRow.find(".recipient-email").val()?.trim();
            let phoneVal = $firstRow.find(".recipient-phone").val()?.trim();

            if (!fNameVal && !lNameVal && !emailVal && !phoneVal) {
                $firstRow.remove(); // Remove empty row
            }
        }


        // ✅ Append selected data to the recipient table
        addedData.forEach(row => {
            recipientTableBody.append(`
            <tr class="editable-row">
                <td class="gift-card-checkbox-wrap">
                    <input type="checkbox" checked id="select-gift-card" class="custom-checkbox">
                </td>
                <td><input type="text" class="form-control recipient-first-name" name="recipient_firstname" placeholder="First Name" value="${row[0]}"></td>
                <td><input type="text" class="form-control recipient-surname" name="recipient_surname" placeholder="Surname" value="${row[1]}"></td>
                <td><input type="email" class="form-control recipient-email" name="recipient_email" placeholder="Email" value="${row[2]}"></td>
                <td><input type="text" class="form-control recipient-phone" name="recipient_phone" placeholder="Phone" value="${row[3]}"></td>
                <td class="gift-card-column"><div class="gift-table">${row[4]}</div></td>
                <td class="action-menu">
                    <button class="action-button">
                        &#x22EE; <!-- Three-dot (ellipsis) icon -->
                    </button>
                    <div class="action-dropdown">
                        <button class="dropdown-item duplicate-recipient-data">Duplicate</button>
                        <button class="dropdown-item delete-recipient">Delete</button>
                    </div>
                </td>              
            </tr>
        `);
        });
        jQuery('.table-container').show();

        // ✅ Reset the CSV Preview Table
        jQuery('#csv-preview-body').empty();  // Clear table body
        jQuery('#csv-preview-container').hide();  // Hide preview container
        jQuery('#pagination-container').hide();  // Hide pagination
        jQuery('#confirm-add').prop('disabled', true); // Disable button
        csvData = []; // Reset CSV data
        checkedRows = {}; // Reset checked rows
        selectedGiftCards = {}; // Reset selected gift cards
        editedFields = {}; // Reset manually edited fields
        invalidFields.clear(); // Clear validation errors
        jQuery("#success-message").text("Recipients added successfully!").show();
        setTimeout(() => {
            jQuery("#success-message").fadeOut();
        }, 5000); // Hide success message after 5 seconds
        jQuery('.row-checkbox').prop('checked', false);
        updateConfirmButtonState();

    });

    // CSV CODE END HERE ================================================================================

    jQuery(document).on("click", ".gift-card-btn", function (e) {

        // console.log('Clicked');
        e.preventDefault();

        // ✅ Get the current row
        const $row = jQuery(this).closest('tr.editable-row');

        // ✅ Check the checkbox in this row
        $row.find('input.custom-checkbox').prop('checked', true);

        // Scroll to the search input
        $('html, body').animate({
            scrollTop: $('#gift-card-search-pro').offset().top - 100 // Adjust offset if needed
        }, 600, function () {
            // Add highlight effect
            $('#gift-card-search-pro')
                .css({
                    'box-shadow': '0 0 10px 2px #007bff',
                    'transition': 'box-shadow 0.5s ease-in-out'
                });

            // Remove the highlight after a short time
            setTimeout(function () {
                $('#gift-card-search-pro').css('box-shadow', '');
            }, 1500);
        });
    });

    // // Add user from search results to the table
    jQuery("#user-search").on("keyup", function () {
        let query = jQuery(this).val().trim();
        let businessUserId = jQuery("#business-user-dropdown").val(); // selected user ID
    
        if (query.length < 1) {
            jQuery("#search-results").html("").hide();
            return;
        }
    
        jQuery.ajax({
            url: userSearchAjax.ajax_url,
            type: "POST",
            data: {
                action: "search_users",
                query: query,
                business_user_id: businessUserId,
                security: userSearchAjax.security
            },
            success: function (response) {
                if (response.trim() !== "") {
                    jQuery("#search-results").html(response).show();
                } else {
                    jQuery("#search-results").html("<li class='dropdown-item'>No results found.</li>").show();
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
            }
        });
    });
    
    // Add new recipient row with input fields
    jQuery(document).on('keyup', '.recipient-first-name, .recipient-surname, .recipient-email, .recipient-phone', function () {
        validateInputField(jQuery(this));
    });


    jQuery(document).on("click", ".search-item", function () {
        let userId = jQuery(this).data("id");
        let firstName = jQuery(this).data("firstname");
        let lastName = jQuery(this).data("lastname");
        let email = jQuery(this).data("email");
        let phone = jQuery(this).data("phone");
        let $newRow = jQuery(`<tr class="editable-row">
                            <td class="gift-card-checkbox-wrap">
                                <input type="checkbox" checked id="select-gift-card" class="custom-checkbox">
                            </td>
                            <td class="edit-input"><input type="text" class="form-control recipient-first-name" name="recipient_firstname" placeholder="First Name" value="${firstName}"></td>
                            <td class="edit-input"><input type="text" class="form-control recipient-surname" name="recipient_surname" placeholder="Surname" value="${lastName}"></td>
                            <td class="edit-input"><input type="email" class="form-control recipient-email" name="recipient_email" placeholder="Email" value="${email}"></td>
                            <td class="edit-input"><input type="text" class="form-control recipient-phone" name="recipient_phone" placeholder="Phone" value="${phone}"></td>
                            <td class="gift-card-column">
                                <div class="gift-table">
                                <button class="btn btn-outline-secondary gift-card-btn">+</button>
                                </div>
                            </td>
                            <td class="action-menu">
                                <div class="action-wrapper">
                                    <button class="action-button">&#x22EE;</button>
                                    <div class="action-dropdown">
                                        <button class="dropdown-item duplicate-recipient-data">Duplicate</button>
                                        <button class="dropdown-item delete-recipient">Delete</button>
                                    </div>
                                </div>
                            </td>
                        </tr>`);

        let tableBody = jQuery("#recipient-table tbody");

        let $firstRow = tableBody.find("tr.editable-row").first();
        if ($firstRow.length) {
            let fNameVal = $firstRow.find(".recipient-first-name").val()?.trim();
            let lNameVal = $firstRow.find(".recipient-surname").val()?.trim();
            let emailVal = $firstRow.find(".recipient-email").val()?.trim();
            let phoneVal = $firstRow.find(".recipient-phone").val()?.trim();

            if (!fNameVal && !lNameVal && !emailVal && !phoneVal) {
                $firstRow.remove(); // Remove empty row
            }
        }

        tableBody.find("tr:contains('No recipients added.')").remove();
        tableBody.append($newRow);

        jQuery("#search-results").html("").hide();
        jQuery("#user-search").val("");

        let hasError = false;

        $newRow.find("input").each(function () {
            // console.log('inidedbagiab');
            const error = validateInputField(jQuery(this));
            if (error) {
                hasError = true;
            }
        });
        fieldsValidFlag = !hasError;
        const errorMessageEl = document.getElementById("customisation-error-message");
        if (errorMessageEl) {
            errorMessageEl.style.display = fieldsValidFlag ? "none" : "block";
        }
        return fieldsValidFlag;

    });


    // Validate input fields before saving
    // function validateRow(row) {
    //     let isValid = true;
    //     row.find("input").each(function () {
    //         let input = jQuery(this);
    //         let value = input.val().trim();
    //         let errorSpan = input.siblings(".error-message");

    //         // Clear previous errors
    //         errorSpan.text("");

    //         if (input.attr("name") === "recipient_email") {
    //             let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    //             if (!emailRegex.test(value)) {
    //                 errorSpan.text("Enter a valid email.");
    //                 isValid = false;
    //             }
    //         } else if (input.attr("name") === "recipient_phone") {
    //             let phoneRegex = /^[0-9]{10,}$/; // At least 10 digits
    //             if (!phoneRegex.test(value)) {
    //                 errorSpan.text("Enter a valid phone number (10+ digits).");
    //                 isValid = false;
    //             }
    //         } else if (!value) {
    //             errorSpan.text("This field is required.");
    //             isValid = false;
    //         }
    //     });

    //     return isValid;
    // }




    // Save recipient details into table after validation
    // jQuery("#btn-save").on("click", function () {
    //     let allValid = true;
    //     let recipientTableBody = jQuery("#recipient-table tbody");
    //     recipientTableBody.find("tr:contains('No recipients added.')").remove();

    //     jQuery(".editable-row").each(function () {
    //         let row = jQuery(this);

    //         if (!validateRow(row)) {
    //             allValid = false;
    //             return;
    //         }

    //         let firstName = row.find("input[name='recipient_firstname']").val();
    //         let surname = row.find("input[name='recipient_surname']").val();
    //         let email = row.find("input[name='recipient_email']").val();
    //         let phone = row.find("input[name='recipient_phone']").val();

    //         let giftCardData = row.find(".gift-card-column").html();

    //         row.replaceWith(`
    //         <tr>
    //             <td class="remove-row">
    //                 <input type="checkbox" checked id="select-all-rows">
    //             </td>
    //             <td><input type="text" class="form-control first-name" value="${firstName}"></td>
    //             <td><input type="text" class="form-control surname" value="${surname}"></td>
    //             <td><input type="email" class="form-control email" value="${email}"></td>
    //             <td><input type="text" class="form-control phone" value="${phone}"></td>
    //             <td class="gift-card-column">
    //                 ${giftCardData}
    //             </td>
    //             <td class="action-menu">
    //                 <button class="action-button">
    //                     &#x22EE; <!-- Three-dot (ellipsis) icon -->
    //                 </button>
    //                 <div class="action-dropdown">
    //                     <button class="dropdown-item duplicate-recipient-data">Duplicate</button>
    //                     <button class="dropdown-item delete-recipient">Delete</button>
    //                 </div>
    //             </td>  
    //         </tr>
    //         `);

    //     });

    //     if (allValid) {
    //         jQuery("#btn-save").hide();
    //     }
    // });

    jQuery(document).on("click", ".remove-recipient", function () {
        jQuery(this).closest("tr").remove();

        // If no rows left, show "No recipients added" message
        let tableBody = jQuery("#recipient-table tbody");
        if (tableBody.children().length === 0) {
            tableBody.append(`<tr><td colspan="6" class="text-center">No recipients added.</td></tr>`);
        }
    });

    document.addEventListener("click", function (e) {
        // Handle button click
        if (e.target.classList.contains("action-button")) {
            e.stopPropagation();

            // Hide all dropdowns first
            var dropdowns = document.getElementsByClassName("action-dropdown");
            for (var i = 0; i < dropdowns.length; i++) {
                dropdowns[i].style.display = "none";
            }

            // Show the related dropdown
            var dropdown = e.target.nextElementSibling;
            if (dropdown && dropdown.classList.contains("action-dropdown")) {
                dropdown.style.display = "block";
            }
        } else {
            // If clicked outside, hide all dropdowns
            var dropdowns = document.getElementsByClassName("action-dropdown");
            for (var i = 0; i < dropdowns.length; i++) {
                dropdowns[i].style.display = "none";
            }
        }
    });




    jQuery(document).on("input", "input", function () {
        jQuery(this).siblings(".invalid-details-error-message").text("");
    });
    //JS for duplicate and delete particular row
    jQuery(document).on("click", ".duplicate-recipient-data", function () {
        let row = jQuery(this).closest("tr").clone(); // Clone the row
        jQuery(this).closest("tr").after(row); // Insert the duplicate row after the current one
    });

    jQuery(document).on("click", ".delete-recipient", function () {
        jQuery(this).closest("tr").remove(); // Remove the row on delete
        // Check if there are any rows left in the table body
        if (jQuery("#recipient-table tbody tr").length === 0) {
            jQuery("#recipient-table tbody").append(`
                <tr class="no-recipient-message">
                    <td colspan="7" class="text-center">No recipients found.</td>
                </tr>
            `);
        }
    });
    jQuery(document).on("click", "#duplicate-recipient-data", function () {
        let originalRow = jQuery(this).closest("tr");
        let clonedRow = originalRow.clone();

        clonedRow.find("input").each(function () {
            let input = jQuery(this);
            let nameAttr = input.attr("name");
            if (nameAttr) {
                input.attr("name", nameAttr + "_copy");
            }
        });

        originalRow.after(clonedRow);
    });

    let selectedRows = []; // Store all selected rows
    let lastSelectedRow = null; // Track last selected recipient row
    let recipientSelected = false; // Track if recipient was selected

    jQuery(document).on("click", ".gift-card-btn", function () {

        let giftCardContainer = jQuery(".gift-card-container.mt-4");

        jQuery(".gift-card-message").remove();
        let message = jQuery("<p class='gift-card-message'>Please Select Gift Card</p>");
        giftCardContainer.prepend(message);

        giftCardContainer.addClass("highlight-selection");
        message.fadeIn();

        // setTimeout(function () {
        //     giftCardContainer.removeClass("highlight-selection");
        //     message.fadeOut(function () {
        //         jQuery(this).remove();
        //     });
        // }, 3000);
        let giftCardColumn = jQuery(this).closest("td");
        let rowIndex = jQuery(this).closest("tr").data("row");
        // jQuery(".gift-card-column").removeClass("highlight-column");

        giftCardColumn.addClass("highlight-column");

        // Store this row in the selectedRows array if not already added
        if (!selectedRows.includes(rowIndex)) {
            selectedRows.push(rowIndex);
        }
        jQuery("#selected-product-container").data("targetColumn", giftCardColumn);
        jQuery("#selected-product-container").data("targetRow", rowIndex);

        jQuery(".gift-card-container").show();
        lastSelectedRow = rowIndex;
        recipientSelected = true;

    });

    function formatPrice(val) {
        const n = Number(val);
        return Number.isFinite(n) ? n.toFixed(2) : String(val ?? '');
    }
    
    // const regularTxt = formatPrice(regularPrice);
    // const saleTxt    = formatPrice(salePrice);
    

    // Toggle options
    // Toggle dropdown
    jQuery(document).on("click", ".custom-dropdown-wrapper .selected-option", function(e){
        e.stopPropagation();
        const $wrapper = jQuery(this).closest(".custom-dropdown-wrapper");
        $wrapper.find(".dropdown-options").toggle();
    });

    // Select option
    jQuery(document).on("click", ".custom-dropdown-wrapper .dropdown-option", function(){
        jQuery('.custom-amount-input-wrapper').slideUp();
        const $option = jQuery(this);
        const value = $option.data("value");
        const $wrapper = $option.closest(".custom-dropdown-wrapper");
        const child_id = jQuery(this).data('id');


        // Update custom UI
        $wrapper.find(".selected-option").text($option.text());
        $wrapper.find(".dropdown-options").hide();

        if( child_id > 0 ){
            const child_type = jQuery(this).data('type');


            if( child_type == 'variable' ){
                const c_title = jQuery(this).data('title');
                const c_denomination = jQuery(this).data('denomination');
                const c_discounted = jQuery(this).data('discounted');
                const c_image = jQuery(this).data('image');
                const c_min_price = jQuery(this).data('min-price');
                const c_min_price_old = jQuery(this).data('min-price-old');
                const c_max_price = jQuery(this).data('max-price');
                const c_intervals = jQuery(this).data('intervals');
                const c_brands = jQuery(this).data('brands');
                const c_id = jQuery(this).data('id');
                const c_sku = jQuery(this).data('sku');
                const c_sell_price = jQuery(this).data('sale-price');
                const child_minPriceTxt = formatPrice(c_min_price_old);


                jQuery('.custom-amount-input-wrapper input').attr('placeholder','Enter Amount ($'+c_min_price+' - $'+c_max_price+')');

                jQuery('.custom-amount-input-wrapper input').attr('min',c_min_price);
                jQuery('.custom-amount-input-wrapper input').attr('max',c_max_price);
                jQuery('.custom-amount-input-wrapper input').attr('step',c_intervals);
                // jQuery('.custom-amount-input-wrapper input').attr('denomination',c_denomination);
                jQuery('.custom-amount-input-wrapper input').attr('data-denomination',c_denomination);
                jQuery('.custom-amount-input-wrapper input').attr('data-discounted',c_discounted);

                jQuery('.custom-amount-input-wrapper input').attr('data-title',c_title);
                jQuery('.custom-amount-input-wrapper input').attr('data-sku',c_sku);
                jQuery('.custom-amount-input-wrapper input').attr('data-image',c_image);
                jQuery('.custom-amount-input-wrapper input').attr('data-min-price',c_min_price);
                jQuery('.custom-amount-input-wrapper input').attr('data-max-price',c_max_price);
                jQuery('.custom-amount-input-wrapper input').attr('data-intervals',c_intervals);
                jQuery('.custom-amount-input-wrapper input').attr('data-brands',c_brands);
                jQuery('.custom-amount-input-wrapper input').attr('data-id',c_id);
                jQuery('.styled-placeholder').remove();

                let labelText = '';

                if (c_min_price_old && c_min_price_old > 0 && c_min_price_old != c_min_price) {
                    labelText = `Enter Amount (<span class='highlight'>$${child_minPriceTxt}</span> → $${c_min_price} - $${c_max_price})`;
                } else {
                    // labelText = `Enter Amount (<span class="highlight">$${child_minPriceTxt}</span> → $${c_sell_price} - $${c_max_price})`;
                    labelText = `Enter Amount ($${c_min_price} - $${c_max_price})`;
                }

                jQuery('.custom-amount-input-wrapper input').after(
                    `<label class='styled-placeholder'>${labelText}</label>`
                );
                
                jQuery('.custom-amount-input-wrapper').slideDown();
            }else{
                // Update hidden select to trigger your existing add-to-table logic
                const $hiddenSelect = $wrapper.find("select.gift-card-price-dropdown."+child_id);
                $hiddenSelect.val(value).trigger("change");
            }
        }else{

            // Update hidden select to trigger your existing add-to-table logic
            const $hiddenSelect = $wrapper.find("select.gift-card-price-dropdown");
            $hiddenSelect.val(value).trigger("change");
        }
    });

    /*

    placeholder="Enter Amount (${child_minPrice} - ${child_maxPrice})"
    min="${child_minPrice}" 
    max="${child_maxPrice}" 
    step="${child_priceIntervals}"
    data-title="${child_productTitle}"
    data-image="${child_productImage}"
    data-min-price="${child_minPrice}"
    data-max-price="${child_maxPrice}"
    data-brands="${child_brands}"
    data-intervals="${child_priceIntervals}"
    */

    // Close dropdown if clicked outside
    jQuery(document).on("click", function(){
        jQuery(".custom-dropdown-wrapper .dropdown-options").hide();
    });




    // ✅ Select gift card product
    jQuery(document).on("click", ".gift-card-products", function () {
        // console.log('insiode product');
        let denominationType = jQuery(this).data("denomination-type");
        let price = jQuery(this).data("price");
        let id = jQuery(this).data("id");
        let activationExpiryType = jQuery(this).data("activation-expiry-type");
        let isDiscounted = jQuery(this).data("is-discounted");
        let discountedFrom = jQuery(this).data("discounted-from");
        let discountedTo = jQuery(this).data("discounted-to");
        let salePrice = jQuery(this).data("sale-price");
        let regularPrice = jQuery(this).data("regular-price");
        let minPrice = parseFloat(jQuery(this).data("min-price"));
        let maxPrice = parseFloat(jQuery(this).data("max-price"));
        let priceIntervals = parseFloat(jQuery(this).data("intervals"));
        let productTitle = jQuery(this).data("title");
        let productImage = jQuery(this).data("image");
        let productSKU = jQuery(this).data("sku");
        let productDescription = jQuery(this).data("description");
        let unavailablePrice = jQuery(this).data("unavailable");
        let fullfillmentCost = jQuery(this).data("fullfillment-cost");
        let brands = jQuery(this).data("brands");
        let BHNpro = jQuery(this).data("is-blackhawk-product");
        let has_child = 0;

        // Fulfilment cost HTML
        let fulfilmentHTML = '';
        // Build input HTML based on denomination type
        let valueInputHTML = '';
        // Price display
        let priceDisplay = '';
        let child_dropdown_options = '';
            
        
        jQuery("#selected-product-container").html("");
        
        if( jQuery(this).hasClass('has_child') ){
            valueInputHTML += `<div class="custom-dropdown-wrapper">`;
                jQuery(this).find('.child_gift_cards .gift-card-child-products').each(function(index, element) {
                    let $child_errors = 0;
                    let $child_div = jQuery(this);
                    let child_valueInputHTML = '';
                    
                    let parent_productTitle = $child_div.closest('.gift-card-products.has_child').data('title');
                    // console.log('Parent Title -->:', parent_productTitle);

                    // your existing child data
                    // let child_productSKU = $child_div.data("sku");
                
                    // console.log('Child SKU -->:', child_productSKU);


                    let child_denominationType = $child_div.data("denomination-type");
                    let child_price = $child_div.data("price");
                    let child_id = $child_div.data("id");
                    let child_activationExpiryType = $child_div.data("activation-expiry-type");
                    let child_isDiscounted = $child_div.data("is-discounted");
                    let child_discountedFrom = $child_div.data("discounted-from");
                    let child_discountedTo = $child_div.data("discounted-to");
                    let child_salePrice = $child_div.data("sale-price");
                    let child_regularPrice = $child_div.data("regular-price");
                    let child_minPrice = parseFloat($child_div.data("min-price"));
                    let child_maxPrice = parseFloat($child_div.data("max-price"));
                    let child_priceIntervals = parseFloat($child_div.data("intervals"));
                    let child_productTitle = parent_productTitle;
                    let child_productImage = $child_div.data("image");
                    let child_productSKU = $child_div.data("sku");
                    let child_productDescription = $child_div.data("description");
                    let child_unavailablePrice = $child_div.data("unavailable");
                    let child_fullfillmentCost = $child_div.data("fullfillment-cost");
                    let child_brands = $child_div.data("brands");
                    let child_BHNpro = $child_div.data("is-blackhawk-product");


                    // console.log('Test Data ' + child_BHNpro);


                    // Check for fixed denomination availability
                    if (child_denominationType.toLowerCase() === "fixed") {
                        if (!child_price || child_price === "0" || child_price === "$0.00") {
                            jQuery("#selected-product-container").html(`
                                <div class="alert alert-danger unavailable-message">
                                    <strong>Sorry!</strong> This gift card is currently unavailable and cannot be selected. Please contact support.
                                </div>
                            `);
                            
                            $child_errors = 1;
                        }
                    }
                  
                    // Check for variable denomination validity
                    if (child_denominationType.toLowerCase() === "variable") {
                        if (
                            isNaN(child_minPrice) || child_minPrice <= 0 ||
                            isNaN(child_maxPrice) || child_maxPrice <= 0 ||
                            isNaN(child_priceIntervals) || child_priceIntervals <= 0
                        ) {
                            jQuery("#selected-product-container").html(`
                                <div class="alert alert-warning unavailable-message">
                                    <strong>Configuration Error:</strong> This variable gift card is missing valid price range or interval settings. Please contact support.
                                </div>
                            `);
                            $child_errors = 1;
                        }
                    }
                
                    /*if (child_fullfillmentCost && parseFloat(child_fullfillmentCost) > 0) {
                        fulfilmentHTML = `
                            <div class="card-fulfilment">
                                <h4>Card Fulfilment : $${parseFloat(child_fullfillmentCost).toFixed(2)}</h4>
                            </div>`;
                    }*/
                
                    // Price display
                    let child_priceDisplay = '';
                    if (child_denominationType.toLowerCase() === "fixed") {
                        child_priceDisplay = `<p class="product-price"> $${parseFloat(child_price).toFixed(2)}</p>`;
                    } else if (child_denominationType.toLowerCase() === "variable") {
                        child_priceDisplay = `<p class="product-price"> $${parseFloat(child_minPrice).toFixed(2)} – $${parseFloat(child_maxPrice).toFixed(2)}</p>`;
                    }
                
                    let child_discountedFromDate = child_discountedFrom ? child_discountedFrom.replace(" ", "T") : null;
                    let child_discountedToDate   = child_discountedTo ? child_discountedTo.replace(" ", "T") : null;
                    let child_currentDateTime    = user_fetch_ajax.server_time;
                    // console.log("Discounted From:", discountedFromDate);
                    // console.log("Discounted To:", discountedToDate);
                    // console.log("-X-x-x-x-x-x-x:");
                    // console.log("MySQL Current Time:", currentDateTime);

                    const child_regularTxt = formatPrice(child_regularPrice);
                    const child_minPriceTxt = formatPrice(child_minPrice);
                    const child_saleTxt    = formatPrice(child_salePrice);
                    // const regularStriked = strikethrough(regularTxt);
                    // First check if product is discounted
                    if ( child_isDiscounted && child_isDiscounted.toLowerCase() === "yes" ) {
                        if(child_discountedFromDate && child_discountedToDate && child_currentDateTime >= child_discountedFromDate && child_currentDateTime <= child_discountedToDate ){
                            // Check sale price
                            if (child_salePrice) {
                                if (child_denominationType.toLowerCase() === "fixed") {
                                    console.log('12');
                                    child_dropdown_options += `<div class="dropdown-option" data-sku="${child_productSKU}" data-id="${child_id}" data-value="${child_saleTxt}">
                                        <span class="regular-price">$${child_regularTxt}</span> → 
                                        <span class="sale-price">$${child_saleTxt}</span>
                                    </div>`;

                                    child_valueInputHTML += `
                                        <select class="gift-card-price-dropdown ${child_id}" 
                                            data-sku="${child_productSKU}" 
                                            data-title="${child_productTitle}" 
                                            data-denomination="${child_denominationType}" 
                                            data-image="${child_productImage}" 
                                            data-regular-price="${child_regularTxt}" 
                                            data-brands="${child_brands}" 
                                            data-bhn-pro="${child_BHNpro}"
                                            data-sale-price="${child_saleTxt}" style="display: none;">
                                            <option value="">Select Amount</option>
                                            <option value="${child_saleTxt}" class="sale-price-option" 
                                                data-title="${child_productTitle}" 
                                                data-image="${child_productImage}" 
                                                data-regular-price="${child_regularTxt}" 
                                                data-brands="${child_brands}" 
                                                data-bhn-pro="${child_BHNpro}"
                                                data-sale-price="${child_saleTxt}">
                                                $${child_regularTxt} → $${child_saleTxt}
                                            </option>
                                        </select>`;
                                
                                        $temp = `
                                        <div class="custom-dropdown">
                                            <div class="selected-option">Select Amount</div>
                                            <div class="dropdown-options">
                                                <div data-value=""> Select Amount </div>
                                                <div class="dropdown-option" data-sku="${child_productSKU}"
                                                data-denomination="${child_denominationType}" 
                                                data-id="${child_id}" data-value="${child_saleTxt}">
                                                    <span class="regular-price">$${child_regularTxt}</span> → 
                                                    <span class="sale-price">$${child_saleTxt}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    `;
                                } else if (child_denominationType.toLowerCase() === "variable") {
                                    console.log('13');



                                    child_dropdown_options += `<div class="dropdown-option" data-type="variable" data-id="${child_id}" data-sale-price="${child_saleTxt}" data-sku="${child_productSKU}" value="${child_maxPrice}" data-title="${child_productTitle}" data-image="${child_productImage}"
                                            data-denomination="${child_denominationType}" data-min-price="${child_minPrice}"  data-min-price-old="${child_minPrice}" data-discounted="${child_salePrice}"
                                            data-max-price="${child_maxPrice}" data-intervals="${child_priceIntervals}" data-brands="${child_brands}" data-bhn-pro="${child_BHNpro}">
                                        <span>$${child_salePrice} - $${child_maxPrice}</span>
                                    </div>`;

                                    // console.log('salePrice variable....');

                                    //     child_valueInputHTML = `
                                    //     <input type="number" 
                                    //         class="custom-amount-input form-control" 
                                    //         placeholder="Enter Amount ($${child_salePrice} - $${child_maxPrice})"
                                    //         min="${child_salePrice}" 
                                    //         max="${child_maxPrice}" 
                                    //         step="${child_priceIntervals}"
                                    //         data-sku="${child_productSKU}" 
                                    //         data-title="${child_productTitle}"
                                    //         data-image="${child_productImage}"
                                    //         data-min-price="${child_salePrice}"
                                    //         data-max-price="${child_maxPrice}"
                                    //         data-brands="${child_brands}"
                                    //         data-intervals="${child_priceIntervals}">
                                    //         <label class="styled-placeholder">
                                    //             Enter Amount (<span class="highlight">$${child_minPriceTxt}</span> → $${child_salePrice} - $${child_maxPrice})
                                    //         </label>
                                    //     <br>
                                    //     <button class="btn btn-primary add-custom-gift-card size-sm">Add Card</button>
                                    // `;
                                }
                            } else {
                                // console.log('inside salePrice else');
                        
                                jQuery("#selected-product-container").html(`
                                    <div class="alert alert-danger discounted-error">
                                        <strong>Sorry!</strong> This discounted gift card does not have a sale price set. Please contact support.
                                    </div>
                                `);
                                $child_errors = 1;
                            }
                        } else{
                            // console.log('inside child_denominationType Else No discounted configuration');
                            // console.log('discountedFromDate',discountedFromDate);
                            // console.log('discountedToDate',discountedToDate);
                            // console.log('currentDateTime',currentDateTime);

                            jQuery("#selected-product-container").html(`
                                <div class="alert alert-danger discounted-error">
                                    <strong>Sorry!</strong> Discount Dates configuration missing. Please contact support.
                                </div>
                            `);
                            $child_errors = 1;
                        }
                    } else if (child_isDiscounted && child_isDiscounted.toLowerCase() === "no") {
                        // console.log('inside isDiscounted No',child_denominationType);

                        // Check denomination type
                        if (child_denominationType.toLowerCase() === "fixed") {
                            // console.log('inside denominationType fixed',child_denominationType);
                            // console.log('WWWWWWWWWXXXX');


                            let child_options = "";
                            if (child_minPrice === child_maxPrice) {
                                child_options = `<option value="${child_minPrice}">${child_minPrice}</option>`;

                                child_dropdown_options += `<div class="dropdown-option" data-sku="${child_productSKU}" data-id="${child_id}" data-value="${child_minPrice}">
                                        <span>$${child_minPrice}</span>
                                    </div>`;
                            } else {
                                let child_lastAdded = 0;
                                for (let i = child_minPrice; i <= child_maxPrice; i += child_priceIntervals) {
                                    i = Math.round(i * 100) / 100;
                                    child_options += `<option value="${child_i}">${child_i}</option>`;
                                    child_lastAdded = i;
                                }
                                if (Math.round(child_lastAdded * 100) / 100 < Math.round(child_maxPrice * 100) / 100) {
                                    child_options += `<option value="${child_maxPrice}" data-sku="${child_productSKU}" data-title="${child_productTitle}" data-image="${child_productImage}"
                                    data-min-price="${child_minPrice}" data-max-price="${child_maxPrice}" data-intervals="${child_priceIntervals}" data-brands="${child_brands}" data-bhn-pro="${child_BHNpro}">${child_maxPrice}</option>`;

                                    child_dropdown_options += `<div class="dropdown-option" data-denomination="${child_denominationType}" data-sku="${child_productSKU}" data-id="${child_id}" value="${child_maxPrice}" data-title="${child_productTitle}" data-image="${child_productImage}"
                                    data-min-price="${child_minPrice}" data-max-price="${child_maxPrice}" data-intervals="${child_priceIntervals}" data-brands="${child_brands}" data-bhn-pro="${child_BHNpro}">
                                        <span>$${child_maxPrice}</span>
                                    </div>`;
                                }

                                child_options += `<option value="custom" data-sku="${child_productSKU}" data-title="${child_productTitle}" data-image="${child_productImage}"
                                    data-min-price="${child_minPrice}" data-max-price="${child_maxPrice}" data-denomination="${child_denominationType}" data-intervals="${child_priceIntervals}" data-brands="${child_brands}" data-bhn-pro="${child_BHNpro}">Custom Amount</option>`;
                            }

                            child_valueInputHTML += `
                                <select class="gift-card-price-dropdown ${child_id}" data-sku="${child_productSKU}" data-title="${child_productTitle}" data-image="${child_productImage}"
                                    data-min-price="${child_minPrice}" data-max-price="${child_maxPrice}" data-denomination="${child_denominationType}" data-intervals="${child_priceIntervals}" data-brands="${child_brands}" data-bhn-pro="${child_BHNpro}" style="display: none;">
                                    <option value="">Select Amount</option>
                                    ${child_options}
                                </select>
                            `;
                        } else if (child_denominationType.toLowerCase() === "variable") {
                            console.log('WWWWWWWWW');

                            child_dropdown_options += `<div class="dropdown-option" data-type="variable" data-id="${child_id}" data-sku="${child_productSKU}" value="${child_maxPrice}" data-title="${child_productTitle}" data-image="${child_productImage}"
                                    data-min-price="${child_minPrice}" data-max-price="${child_maxPrice}" data-denomination="${child_denominationType}" data-intervals="${child_priceIntervals}" data-brands="${child_brands}" data-bhn-pro="${child_BHNpro}">
                                        <span>$${child_minPrice} - $${child_maxPrice}</span>
                            </div>`;

                            /*child_valueInputHTML += `
                                <input type="number" 
                                    class="custom-amount-input form-control ${child_id}" 
                                    placeholder="Enter Amount (${child_minPrice} - ${child_maxPrice})"
                                    min="${child_minPrice}" 
                                    max="${child_maxPrice}" 
                                    step="${child_priceIntervals}"
                                    data-title="${child_productTitle}"
                                    data-image="${child_productImage}"
                                    data-min-price="${child_minPrice}"
                                    data-max-price="${child_maxPrice}"
                                    data-brands="${child_brands}"
                                    data-bhn-pro="${child_BHNpro}"
                                    data-intervals="${child_priceIntervals}">
                                <br>
                                <button class="btn btn-primary add-custom-gift-card size-sm">Add Card</button>
                            `;*/
                        }
                    } else {
                        // console.log('inside denominationType Else No',child_isDiscounted);

                        jQuery("#selected-product-container").html(`
                            <div class="alert alert-danger discounted-error">
                                <strong>Sorry!</strong> Discount configuration missing. Please contact support.
                            </div>
                        `);
                        $child_errors = 1;
                    }

                    // console.log('child_valueInputHTML: ', child_valueInputHTML);

                    if( $child_errors > 0 ){
                        child_valueInputHTML = '';
                    }
                    if( $child_errors <= 0 ){
                        valueInputHTML += child_valueInputHTML;
                    }
                    child_valueInputHTML = '';
                });

                valueInputHTML += `
                <!-- Custom dropdown for UI -->
                <div class="custom-dropdown">
                    <div class="selected-option">Select Amount</div>
                    <div class="dropdown-options">
                        <div data-value=""> Select Amount </div>
                        ${child_dropdown_options}
                    </div>
                </div>`;
                valueInputHTML += `
                    <div class="custom-amount-input-wrapper" style="display: none;">
                    <br/>
                        <input type="number" 
                            class="custom-amount-input form-control">
                        <br>
                        <button class="btn btn-primary add-custom-gift-card size-sm btn-black-white btn-primary-black">Add Card</button>
                    </div>
                `;
            valueInputHTML += `</div>`;
            // console.log('valueInputHTML: ',valueInputHTML);
        } else {
            // console.log('price',price);
            // console.log('denominationType',denominationType);
            // Check for fixed denomination availability
            if (denominationType.toLowerCase() === "fixed") {
                if (!price || price === "0" || price === "$0.00") {
                    jQuery("#selected-product-container").html(`
                        <div class="alert alert-danger unavailable-message">
                            <strong>Sorry!</strong> This gift card is currently unavailable and cannot be selected. Please contact support.
                        </div>
                    `);
                    return;
                }
            }
          
            // Check for variable denomination validity
            if (denominationType.toLowerCase() === "variable") {
                if (
                    isNaN(minPrice) || minPrice <= 0 ||
                    isNaN(maxPrice) || maxPrice <= 0 ||
                    isNaN(priceIntervals) || priceIntervals <= 0
                ) {
                    jQuery("#selected-product-container").html(`
                        <div class="alert alert-warning unavailable-message">
                            <strong>Configuration Error:</strong> This variable gift card is missing valid price range or interval settings. Please contact support.
                        </div>
                    `);
                    return;
                }
            }
        
            if (fullfillmentCost && parseFloat(fullfillmentCost) > 0) {
                fulfilmentHTML = `
                    <div class="card-fulfilment">
                        <h4>Card Fulfilment : $${parseFloat(fullfillmentCost).toFixed(2)}</h4>
                    </div>`;
            }
        
            if (denominationType.toLowerCase() === "fixed") {
                priceDisplay = `<p class="product-price"> $${parseFloat(price).toFixed(2)}</p>`;
            } else if (denominationType.toLowerCase() === "variable") {
                priceDisplay = `<p class="product-price"> $${parseFloat(minPrice).toFixed(2)} – $${parseFloat(maxPrice).toFixed(2)}</p>`;
            }
        
            // console.log('----------------');
            // console.log('discountedFrom',discountedFrom)
            // console.log('discountedTo',discountedTo)
            // console.log('----------------');

            let discountedFromDate = discountedFrom ? discountedFrom.replace(" ", "T") : null;
            let discountedToDate   = discountedTo ? discountedTo.replace(" ", "T") : null;
            let currentDateTime    = user_fetch_ajax.server_time;
            // console.log("Discounted From:", discountedFromDate);
            // console.log("Discounted To:", discountedToDate);
            // console.log("-X-x-x-x-x-x-x:");
            // console.log("MySQL Current Time:", currentDateTime);

            const regularTxt = formatPrice(regularPrice);
            const minPriceTxt = formatPrice(minPrice);
            const saleTxt    = formatPrice(salePrice);
            // const regularStriked = strikethrough(regularTxt);
            // First check if product is discounted
            if ( isDiscounted && isDiscounted.toLowerCase() === "yes" ) {
                if(discountedFromDate && discountedToDate && currentDateTime >= discountedFromDate && currentDateTime <= discountedToDate ){
                    // Check sale price
                    if (salePrice) {
                        if (denominationType.toLowerCase() === "fixed") {
                            valueInputHTML = `
                            <div class="custom-dropdown-wrapper">
                                <!-- Hidden select to keep existing JS working -->
                                <select class="gift-card-price-dropdown d-none" 
                                    data-sku="${productSKU}" 
                                    data-title="${productTitle}" 
                                    data-denomination="${denominationType}"
                                    data-image="${productImage}" 
                                    data-regular-price="${regularTxt}" 
                                    data-brands="${brands}" 
                                    data-sale-price="${saleTxt}">
                                    <option value="">Select Amount</option>
                                    <option value="${saleTxt}" class="sale-price-option" 
                                        data-regular-price="${regularTxt}" 
                                        data-brands="${brands}" 
                                        data-sale-price="${saleTxt}">
                                        $${regularTxt} → $${saleTxt}
                                    </option>
                                </select>
                        
                                <!-- Custom dropdown for UI -->
                                <div class="custom-dropdown">
                                    <div class="selected-option">Select Amount</div>
                                    <div class="dropdown-options">
                                        <div data-value=""> Select Amount </div>
                                        <div class="dropdown-option" 
                                        data-denomination="${denominationType}"
                                        data-sku="${productSKU}" data-value="${saleTxt}">
                                            <span class="regular-price">$${regularTxt}</span> → 
                                            <span class="sale-price">$${saleTxt}</span>
                                        </div>
                                    </div>
                                </div>
                        
                                <br><br>
                                <div class="custom-amount-container" style="display: none;">
                                    <input type="number" class="custom-amount-input form-control ccc" placeholder="Enter Amount">
                                    <button class="btn btn-primary add-custom-gift-card size-sm btn-black-white btn-primary-black">Add Card</button>
                                </div>
                            </div>
                            `;
                        } else if (denominationType.toLowerCase() === "variable") {

                            valueInputHTML = `
                            <input type="number" 
                                class="custom-amount-input form-control  aaa" 
                                placeholder="Enter Amount($${minPriceTxt} - $${maxPrice})"
                                data-discounted="${salePrice}"
                                min="${minPrice}" 
                                max="${maxPrice}" 
                                data-denomination="${denominationType}"
                                step="${priceIntervals}"
                                data-sku="${productSKU}" 
                                data-title="${productTitle}"
                                data-image="${productImage}"
                                data-min-price="${minPrice}"
                                data-max-price="${maxPrice}"
                                data-brands="${brands}"
                                data-intervals="${priceIntervals}">
                                <label class="styled-placeholder">
                                    Enter Amount (<span class="highlight">$${minPriceTxt}</span> → $${salePrice} - $${maxPrice})
                                </label>
                            <br>
                            <button class="btn btn-primary add-custom-gift-card size-sm btn-black-white btn-primary-black">Add Card</button>
                        `;
                        }
                    } else {
                
                        jQuery("#selected-product-container").html(`
                            <div class="alert alert-danger discounted-error">
                                <strong>Sorry!</strong> This discounted gift card does not have a sale price set. Please contact support.
                            </div>
                        `);
                        return;
                    }
                } else{

                    jQuery("#selected-product-container").html(`
                        <div class="alert alert-danger discounted-error">
                            <strong>Sorry!</strong> Discount Dates configuration missing. Please contact support.
                        </div>
                    `);
                    return;
                }
            } else if (isDiscounted && isDiscounted.toLowerCase() === "no") {

                // Check denomination type
                if (denominationType.toLowerCase() === "fixed") {

                    let options = "";
                    if (minPrice === maxPrice) {
                        options = `<option value="${minPrice}">$${minPrice}</option>`;
                    } else {
                        let lastAdded = 0;
                        for (let i = minPrice; i <= maxPrice; i += priceIntervals) {
                            i = Math.round(i * 100) / 100;
                            options += `<option value="${i}">${i}</option>`;
                            lastAdded = i;
                        }
                        if (Math.round(lastAdded * 100) / 100 < Math.round(maxPrice * 100) / 100) {
                            options += `<option value="${maxPrice}" data-sku="${productSKU}" data-title="${productTitle}" data-image="${productImage}"
                            data-min-price="${minPrice}" data-max-price="${maxPrice}" data-intervals="${priceIntervals}" data-brands="${brands}">${maxPrice}</option>`;
                        }
                        options += `<option value="custom" data-sku="${productSKU}" data-title="${productTitle}" data-image="${productImage}"
                            data-min-price="${minPrice}" data-max-price="${maxPrice}" data-intervals="${priceIntervals}" data-brands="${brands}">Custom Amount</option>`;
                    }

                    valueInputHTML = `
                        <select class="gift-card-price-dropdown" data-sku="${productSKU}" data-denomination="${denominationType}" data-title="${productTitle}" data-image="${productImage}"
                            data-min-price="${minPrice}" data-max-price="${maxPrice}" data-intervals="${priceIntervals}" data-brands="${brands}">
                            <option value="">Select Amount</option>
                            ${options}
                        </select>
                        <br><br>
                        <div class="custom-amount-container" style="display: none;">
                            <input type="number" class=" dd" placeholder="Enter Amount">
                            <button class="btn btn-primary add-custom-gift-card size-sm btn-black-white btn-primary-black">Add Card</button>
                        </div>
                    `;
                } else if (denominationType.toLowerCase() === "variable") {

                    valueInputHTML = `
                        <input type="number" 
                            class="custom-amount-input form-control bbb" 
                            placeholder="Enter Amount (${minPrice} - ${maxPrice})"
                            data-discounted="${salePrice}"
                            min="${minPrice}" 
                            max="${maxPrice}" 
                            step="${priceIntervals}"
                            data-sku="${productSKU}" 
                            data-denomination="${denominationType}"
                            data-title="${productTitle}"
                            data-image="${productImage}"
                            data-min-price="${minPrice}"
                            data-max-price="${maxPrice}"
                            data-brands="${brands}"
                            data-intervals="${priceIntervals}">
                        <br>
                        <button class="btn btn-primary add-custom-gift-card size-sm btn-black-white btn-primary-black">Add Card</button>
                    `;
                }
            } else {
                // console.log('inside denominationType Else No',isDiscounted);

                jQuery("#selected-product-container").html(`
                    <div class="alert alert-danger discounted-error">
                        <strong>Sorry!</strong> Discount configuration missing. Please contact support.
                    </div>
                `);
                return;
            }
        }


    
        
    
        let selectedProductHTML = `
            <div class="remove-selected-product">    
                <button class="btn-remove-selected-product" id="remove-selected-product">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <p class="custom-amount-error" style="display: none;">
                <i class="fa-solid fa-circle-info"></i>
                The value entered is invalid. Please enter a value in ${priceIntervals} intervals within the range of ${minPrice} - ${maxPrice}.
            </p>
            <div class="row selected-product d-flex align-items-start">
                <div class="col-12 col-md-5 product-image">
                    <img src="${productImage}" class="img-fluid rounded" alt="${productTitle}" width="100">
                </div>    
                <div class="col-12 col-md-7 product-details-wrapper">
                    <div class="product-top-block">
                        <div class="product-heading">       
                            <h4 class="product-title">${productTitle}</h4>
                            <p class="product-sku"><strong>SKU: ${productSKU}</strong></p>
                        </div>
                        <a class="link-btn" target="_blank" href="${(typeof user_fetch_ajax !== 'undefined' && user_fetch_ajax.siteUrl ? user_fetch_ajax.siteUrl.replace(/\/$/, '') : '') + '/create-product/?edit_product=' + id}">
                            <i class="fa-solid fa-pen"></i><span class="view-details-text">View details</span>
                        </a>
                    </div>
                    <p>${productDescription}</p>
                    <p><strong>Value</strong></p>
                    ${valueInputHTML}
                    ${fulfilmentHTML}
                </div>
                ${priceDisplay}
            </div>
        `;
    
        if (!activationExpiryType) {
            jQuery("#selected-product-container").html(`
                <div class="alert alert-warning unavailable-message">
                    <strong>Configuration Error:</strong> This gift card is missing Activation Expiry type. Please contact support.
                </div>
            `);
        } else {
            jQuery("#selected-product-container").html(selectedProductHTML);
        }
    });
    

    // Handle adding selected gift card to **all** highlighted rows
    jQuery(document).on("change", ".gift-card-price-dropdown", function () {
        let selectedPrice = jQuery(this).val();
        let productTitle = jQuery(this).data("title");
        let productImage = jQuery(this).data("image");
        let denomination = jQuery(this).data("denomination");
        let minPrice = parseFloat(jQuery(this).data("min-price"));
        let maxPrice = parseFloat(jQuery(this).data("max-price"));
        let brands = jQuery(this).data("brands");
        let BHNpro = jQuery(this).data("bhn-pro");

        let productSKU = jQuery("#selected-product-container")
            .find("p strong:contains('SKU')")
            .parent().text().replace("SKU:", "").trim();

        if( jQuery(this).data("sku") ){
            // productSKU = jQuery(this).data("sku").trim();
            productSKU = jQuery(this).attr("data-sku").trim();
        }

        let checkedRows = jQuery(".gift-card-checkbox-wrap input[type='checkbox']:checked, #csv-preview-body .row-checkbox:checked").closest("tr");

        if (checkedRows.length === 0) {
            showError("Select Recipient To Add Gift Card");
            jQuery(this).val('');
            return;
        }

        if (selectedPrice === "custom") {
            jQuery(".custom-amount-container").show();
            return;
        } else {
            jQuery(".custom-amount-container").hide();
        }

        if (selectedPrice === "unavailable") {
            showError("This gift card cannot be added to the highlighted column.");
            jQuery(this).val('');
            return;
        }

        checkedRows.each(function () {
            let $row = jQuery(this);
            let rowIndex = $row.data("row");
            $row.find('.invalid-message').html('');

            let giftCardWrapper = $row.find(".gift-card-wrapper");

            // Create wrapper if not present
            if (giftCardWrapper.length === 0) {
                giftCardWrapper = $('<div class="gift-card-wrapper"></div>');
                $row.find(".gift-card-btn").before(giftCardWrapper);
            }
            console.log('1');
            // Build the gift card item
            let selectedGiftCardItem = `
                <div class="gift-card-item" data-sku="${productSKU}" data-prod_id="" data-message="" data-denomination="${denomination}" data-text-animation="" data-email-animation="" data-subject="" data-sender="" data-text_message="" data-title="${productTitle}" data-bhn-pro="${BHNpro}" data-brands="${brands}">
                    <img src="${productImage}" class="gift-card-image" alt="${productTitle}">
                    <span class="gift-card-price">$${selectedPrice}</span>
                    <button class="remove-gift-card">x</button>
                </div>
            `;

            // Add gift card item to the wrapper
            giftCardWrapper.append(selectedGiftCardItem);

            // Store gift card
            if (!selectedGiftCards[rowIndex]) {
                selectedGiftCards[rowIndex] = [];
            }
            selectedGiftCards[rowIndex].push(selectedGiftCardItem);

            $row.removeClass("highlight-column");
        });

        // Show success message (remove old first)
        jQuery(".gift-card-global-success").remove();

        let $target = jQuery(".custom-dropdown");
        if ($target.length === 0) {
            $target = jQuery(".gift-card-price-dropdown");
        }
       
        // Insert after whichever exists
        if ($target.length) {
            if( jQuery('.gift-card-global-success.text-success').length == 0 ){
                // console.log('1');
                $target.after(`
                    <div class="gift-card-global-success text-success" style="margin-top: 8px;">
                        Gift card added successfully ✅
                    </div>
                `);

            }
            setTimeout(() => {
                jQuery(".gift-card-global-success").fadeOut(500, function () {
                    jQuery(this).remove();
                });
            }, 2000);
        }

        // Animate scroll
        // $('html, body').animate({
        //     scrollTop: $row.find(".gift-card-global-success").offset().top - 100
        // }, 500);

        // Global cleanup
        jQuery('.gift-card-price-dropdown').val('');
        // Reset all custom dropdowns back to default label
        jQuery('.custom-dropdown .selected-option').text('Select Amount');

        let giftMessage = document.querySelector(".gift-card-message");
        if (giftMessage) giftMessage.remove();
        jQuery(".gift-card-container.mt-4").removeClass("highlight-selection");

        checkedRows.each(function () {
            const rowIndex = jQuery(this).data("row");
            selectedRows = selectedRows.filter(row => row.data("row") !== rowIndex);
        });

        // Remove success messages after delay
        setTimeout(() => {
            jQuery(".gift-card-success-msg").fadeOut(500, function () {
                jQuery(this).remove();
            });
        }, 1500);
    });

    // Handle custom amount input and add to the targeted row
    jQuery(document).on("click", ".add-custom-gift-card", function () {

        
        let $container = jQuery("#selected-product-container");
        let $dropdown = $container.find(".gift-card-price-dropdown");
        let $customInput = $container.find(".custom-amount-input");
        let denomination = $customInput.data("denomination");
        // let denomination = $customInput.data("denomination");
        console.log('$denomination...',denomination);
        let customAmount = parseFloat($customInput.val());
        let productTitle, productImage, minPrice, maxPrice, interval;
    
        let productSKU = $container.find("p strong:contains('SKU')").parent().text().replace("SKU:", "").trim();
        
        if ($dropdown.length && 1==2) {
            // Fixed product — get data from dropdown
            productTitle = $dropdown.data("title");
            productImage = $dropdown.data("image");
            minPrice = parseFloat($dropdown.data("min-price"));
            maxPrice = parseFloat($dropdown.data("max-price"));
            interval = parseFloat($dropdown.data("intervals"));
        } else {
            // console.log('hideen',discountedHidden);
            // Variable product — get data from the custom input attributes
            productTitle = $customInput.data("title");
            productSKU = $customInput.data("sku");
            productImage = $customInput.data("image");
            minPrice = parseFloat($customInput.attr("min"));
            discountedHidden = $customInput.data("discounted");
            maxPrice = parseFloat($customInput.attr("max"));
            interval = parseFloat($customInput.attr("step"));
        }
    
    
        const $error = jQuery(".custom-amount-error");
        const $input = $customInput;
    
        const isValidInterval = Math.abs((customAmount - minPrice) % interval) < 0.000001;
        const isExactMax = Math.abs(customAmount - maxPrice) < 0.000001;
    
        if (
            isNaN(customAmount) ||
            isNaN(minPrice) ||
            isNaN(maxPrice) ||
            isNaN(interval) ||
            customAmount < minPrice ||
            customAmount > maxPrice ||
            (!isValidInterval && !isExactMax)
        ) {
            $error.html(
                `<i class="fa-solid fa-circle-info"></i> Please enter a value in intervals of $${interval} in between the range of $${minPrice} and $${maxPrice}.`
            ).show();
            // $input.addClass("error");
            return;
        } else {
            $error.hide();
            // $input.removeClass("error");
        }
    
        let checkedRows = jQuery(".gift-card-checkbox-wrap input[type='checkbox']:checked").closest("tr");
       


        if (customAmount && checkedRows.length > 0) {
            checkedRows.each(function () {
                let $row = jQuery(this);
                let rowIndex = $row.data("row");
                $row.find('.invalid-message').html('');
    
                let giftCardWrapper = $row.find(".gift-card-wrapper");
                if (giftCardWrapper.length === 0) {
                    giftCardWrapper = $('<div class="gift-card-wrapper"></div>');
                    $row.find(".gift-card-btn").before(giftCardWrapper);
                }

                let discountPercent = (minPrice - discountedHidden) / minPrice;
                console.log('discountPercent',discountPercent);
                let calculatedDiscountPrice = customAmount - (customAmount * discountPercent);
                console.log('calculatedDiscountPrice',calculatedDiscountPrice);
                calculatedDiscountPrice = calculatedDiscountPrice.toFixed(2);
                // console.log('calculatedDiscountPrice To fixed',calculatedDiscountPrice);
    
                // console.log('1');

                let selectedGiftCardItem = `
                    <div class="gift-card-item" data-sku="${productSKU}" data-discount="${calculatedDiscountPrice}" data-prod_id="" data-denomination="${denomination}" data-message="" data-text-animation="" data-email-animation="" data-subject="" data-sender="" data-text_message="" data-title="${productTitle}">
                        <img src="${productImage}" class="gift-card-image" alt="${productTitle}">
                        <span class="gift-card-price">$${customAmount}</span>
                        <button class="remove-gift-card">x</button>
                    </div>
                `;
    
                giftCardWrapper.append(selectedGiftCardItem);
    
                $row.removeClass("highlight-column");
    
                if (!selectedGiftCards[rowIndex]) {
                    selectedGiftCards[rowIndex] = [];
                }
                selectedGiftCards[rowIndex].push(selectedGiftCardItem);
            });
    
            // Show success message
            jQuery(".gift-card-global-success").remove();
    
            // Success message position: after dropdown if exists, otherwise after input
            if ($dropdown.length && 1==2) {
                if( jQuery('.gift-card-global-success.text-success').length == 0 ){
                    console.log('2');
                    $dropdown.after(`
                        <div class="gift-card-global-success text-success" style="margin-top: 8px;">
                            Gift card added successfully ✅
                        </div>
                    `);
                }
            } else {
                if( jQuery('.gift-card-global-success.text-success').length == 0 ){
                    console.log('3');
                    $customInput.after(`
                        <div class="gift-card-global-success text-success" style="margin-top: 8px;">
                            Gift card added successfully ✅
                        </div>
                    `);
                }
            }
    
            setTimeout(() => {
                jQuery(".gift-card-global-success").fadeOut(500, function () {
                    jQuery(this).remove();
                });
            }, 2000);
    
            // Reset UI
            if ($dropdown.length) {
                $dropdown.val('');
                $container.find(".custom-amount-container").hide();
            }
            $customInput.val('');
    
            recipientSelected = false;
    
            checkedRows.each(function () {
                let rowIndex = jQuery(this).data("row");
                selectedRows = selectedRows.filter(row => row.data("row") !== rowIndex);
            });
    
            setTimeout(() => {
                jQuery(".gift-card-success-msg").fadeOut(500, function () {
                    jQuery(this).remove();
                });
            }, 1500);
        }else if( checkedRows.length <= 0 ){
            showError("Select Recipient To Add Gift Card");
            jQuery(this).val('');
            return;
        }
    });
    



    jQuery(document).on("click", function (e) {
        if (
            jQuery(e.target).closest(".search-bar, .gift-card-container, .gift-card-btn, .gift-card-price-dropdown, .gift-card-products, .add-custom-gift-card, .custom-amount-input").length
        ) {
            return;
        }
        selectedRows = [];
        jQuery(".gift-card-column").removeClass("highlight-column");
        $test = document.querySelector(".gift-card-message");
        if ($test) {
            document.querySelector(".gift-card-message").remove();
        }


    });

    function showError(message) {
        let errorElement = jQuery("#recipient-error-message");

        errorElement.text(message).show();

        jQuery('html, body').animate({
            scrollTop: errorElement.offset().top - 100
        }, 500);

        setTimeout(function () {
            errorElement.fadeOut();
        }, 3000);
    }

    // ✅ Remove selected gift card
    jQuery(document).on("click", ".remove-gift-card", function (event) {
        event.stopPropagation();
        
        let $giftCardItem = jQuery(this).closest(".gift-card-item");
        let $wrapper = $giftCardItem.closest(".gift-card-wrapper");
        let rowIndex = jQuery(this).closest("tr").data("row");
    
        let itemIndex = $giftCardItem.index();
        $giftCardItem.remove();
    
        if (selectedGiftCards[rowIndex]) {
            selectedGiftCards[rowIndex].splice(itemIndex, 1);
            if (selectedGiftCards[rowIndex].length === 0) {
                delete selectedGiftCards[rowIndex];
            }
        }
    
        if ($wrapper.find(".gift-card-item").length === 0) {
            $wrapper.remove();
        }
    });
    

    jQuery(document).on("click", "#remove-selected-product", function () {
        jQuery("#selected-product-container").html("");
    });
});

let fieldsValidFlag = false;

jQuery(document).ready(function () {
    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    function validatePhone(phone) {
        const regex = /^(?:\+61\s?4\d{2}\s?\d{3}\s?\d{3}|04\d{2}\s?\d{3}\s?\d{3})$/;
        if (phone.startsWith('61')) {
            phone = '+' + phone;                                
        }
        return regex.test(phone);
        //return /^\+61\d{9}$/.test(phone);
    }
    jQuery(".add-new-recipient-btn").on("click", function () {
        jQuery('.selected-product').hide();

        // Append new editable row
        jQuery("#recipient-table tbody").append(`
                <tr class="editable-row">
                    <td class="gift-card-checkbox-wrap">
                        <input type="checkbox" checked id="select-gift-card" class="custom-checkbox">
                    </td>
                    <td><input type="text" class="form-control recipient-first-name" name="recipient_firstname" placeholder="First Name"></td>
                    <td><input type="text" class="form-control recipient-surname" name="recipient_surname" placeholder="Surname"></td>
                    <td><input type="email" class="form-control recipient-email" name="recipient_email" placeholder="Email"></td>
                    <td><input type="text" class="form-control recipient-phone" name="recipient_phone" placeholder="Phone"></td>
                    <td class="gift-card-column">
                        <div class="gift-table">
                            <button class="btn btn-outline-secondary gift-card-btn">+</button>
                        </div>
                    </td>
                    <td class="action-menu">
                        <button class="action-button">&#x22EE;</button>
                        <div class="action-dropdown">
                            <button class="dropdown-item duplicate-recipient-data">Duplicate</button>
                            <button class="dropdown-item delete-recipient">Delete</button>
                        </div>
                    </td>  
                </tr>
            `);
        validateAllRecipientRows();
        let tableBody = jQuery("#recipient-table tbody");
        tableBody.find("tr:contains('No recipients added.')").remove();
        const invalidRecipients = document.getElementById("invalid-recipients-error-message");
        if (invalidRecipients) invalidRecipients.style.display = "none";
    });

    function validateAllRecipientRows() {
        let scrolled = false;
        let hasInvalidFields = false;
        let emailPhoneMissing = false;
        
        // Clear global message first
        jQuery("#recipient-email-phone-validate-message").hide().text('');

        jQuery('#recipient-table tbody .editable-row').each(function () {
            const $row = jQuery(this);

            // Remove previous inline validation messages
            $row.find('.validation-error').remove();
            $row.find('.invalid-field').removeClass('invalid-field');

            const firstName = $row.find('.recipient-first-name').val().trim();
            const email = $row.find('.recipient-email').val().trim();
            const phone = $row.find('.recipient-phone').val().trim();

            // const emailValid = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/.test(email);
            // const phoneValid = /^\+61\d{9}$/.test(phone);

            const emailValid = validateEmail(email);
            const phoneValid = validatePhone(phone);

            if (!firstName) {
                showInlineError($row.find('.recipient-first-name'), 'Please enter First Name');
            }

            // ✅ Require at least email or phone
            if (!email && !phone || ((email && phone) && (!emailValid || !phoneValid))) {
                console.log('INVALID');
                emailPhoneMissing = true;
                hasInvalidFields = true;
            } else {
                console.log('VALID');
                if (email && !emailValid) {
                    showInlineError($row.find('.recipient-email'), 'Please enter valid Email');
                    hasInvalidFields = true;
                }
                if (phone && !phoneValid) {
                    showInlineError($row.find('.recipient-phone'), 'Phone must start with +61 and contain exactly 9 digits');
                    hasInvalidFields = true;
                }
            }

            function showInlineError($input, message) {
                $input.addClass('invalid-field');
                $input.after(`<div class="validation-error" style="color:red; font-size: 12px; margin-top: 4px;">${message}</div>`);
                if (!scrolled) {
                    $input[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    scrolled = true;
                }
            }
        });

        // ✅ Show global message if email & phone are both missing in any row
        if (emailPhoneMissing) {
            jQuery("#recipient-email-phone-validate-message")
                .text("Please provide an email address or mobile number for Gift Card delivery to each recipient.")
                .show();
        }

        const errorMessageEl = document.getElementById("customisation-error-message");
        if (errorMessageEl) {
            errorMessageEl.style.display = hasInvalidFields ? "block" : "none";
        }

        return !hasInvalidFields;
    }

    // Allow only digits, +, and space in phone
    // jQuery(document).on('input', '.recipient-phone', function () {
    //     this.value = this.value.replace(/[^\d+\s]/g, '');
    // });

    // // Format phone input on blur
    // function formatAustralianPhoneNumber(value) {
    //     let digits = value.replace(/\D/g, '');

    //     if (digits.startsWith('04')) {
    //         digits = digits.substring(1);
    //     }

    //     if (digits.startsWith('4')) {
    //         digits = '61' + digits;
    //     }

    //     if (!digits.startsWith('61')) {
    //         digits = '61' + digits;
    //     }

    //     if (digits.length >= 11) {
    //         return `+${digits.slice(0, 2)} ${digits.slice(2, 5)} ${digits.slice(5, 8)} ${digits.slice(8, 11)}`;
    //     }

    //     return '+' + digits;
    // }

    // jQuery(document).on('blur', '.recipient-phone', function () {
    //     const formatted = formatAustralianPhoneNumber(jQuery(this).val());
    //     jQuery(this).val(formatted);
    // });


    // ✅ Real-time validation as user types
    jQuery(document).on('keyup', '.recipient-first-name, .recipient-surname, .recipient-email, .recipient-phone', function () {
        validateAllRecipientRows();
    });
    // jQuery(document).on('keyup', '.recipient-first-name, .recipient-surname, .recipient-email, .recipient-phone', function () {
    //     var $row = jQuery(this).closest('tr');  // Find the row of the field being edited
    //     let isValidfields = true; // Assume valid unless proven otherwise

    //     // Validate First Name
    //     var firstName = $row.find('.recipient-first-name').val();
    //     if (!firstName) {
    //         $row.find('.recipient-first-name').addClass('invalid-field');
    //         isValidfields = false;
    //     } else {
    //         $row.find('.recipient-first-name').removeClass('invalid-field');
    //     }

    //     // Validate Surname
    //     var surname = $row.find('.recipient-surname').val();
    //     if (!surname) {
    //         $row.find('.recipient-surname').addClass('invalid-field');
    //         isValidfields = false;
    //     } else {
    //         $row.find('.recipient-surname').removeClass('invalid-field');
    //     }

    //     // Validate Email
    //     var email = $row.find('.recipient-email').val();
    //     var emailPattern = /^[a-zA-Z0-9._%+-]{1,}[a-zA-Z0-9.-]*@[a-zA-Z0-9.-]{1,}\.[a-zA-Z]{2,4}$/; // Simplified email pattern for a@a.c format
    //     if (!emailPattern.test(email)) {
    //         $row.find('.recipient-email').addClass('invalid-field');
    //         isValidfields = false;
    //     } else {
    //         $row.find('.recipient-email').removeClass('invalid-field');
    //     }

    //     // Validate Phone Number
    //     var phone = $row.find('.recipient-phone').val();
    //     var phonePattern = /^[0-9]{10}$/; // Example: 10-digit phone number
    //     if (!phonePattern.test(phone)) {
    //         $row.find('.recipient-phone').addClass('invalid-field');
    //         isValidfields = false;
    //     } else {
    //         $row.find('.recipient-phone').removeClass('invalid-field');
    //     }
    //     fieldsValidFlag = isValidfields;
    //     const errorMessageEl = document.getElementById("customisation-error-message");
    //     if (fieldsValidFlag && errorMessageEl) {
    //         errorMessageEl.style.display = "none";
    //     }
    // });
});



// let isValid = true;

// jQuery('#recipient-table .gift-card-column').each(function () {
//     if (jQuery(this).find('.gift-card-item').length === 0) {
//         console.log('Invalid');
//         isValid = false;
//     } else {
//         console.log('Valid');
//     }
// });

// console.log('Final Validation Result:', isValid ? 'All Valid' : 'Some Invalid');
// $(document).on('click', '.order-link', function (e) {
//     e.preventDefault();

//     const orderId = $(this).data('order-id');

//     // Optional: Show loading animation or section
//     $('#orderDetailSection').html('<p>Loading order details...</p>').show();

//     $.ajax({
//         url: userListingData.ajax_url,
//         method: 'POST',
//         dataType: 'html',
//         data: {
//             action: 'get_order_detail_html',
//             order_id: orderId
//         },
//         success: function (response) {
//             $('#orderDetailSection').html(response);
//         },
//         error: function () {
//             $('#orderDetailSection').html('<p style="color:red;">Failed to load order details.</p>');
//         }
//     });
// });
