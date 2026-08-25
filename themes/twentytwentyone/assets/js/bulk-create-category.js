jQuery(document).ready(function ($) {

    const rules = {
        'Category Name': /^[a-zA-Z0-9 ]+$/,                         // Text + Numbers + Space
        'Description': /[\s\S]*/,                                   // Anything allowed
        'Priority': /^[0-9]+$/,                                     // Numbers only
        'Icon Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,        // Image URL
        'Banner Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,      // Image URL
        'Thumbnail Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,   // Image URL
        'Status': /^[a-zA-Z]+$/,                                    // Text only
        "SKU\'s Assigned": /^[a-zA-Z0-9]+$/                          // Text + Numbers (no space)
    };

    setTimeout(() => {
        const urlParams = new URLSearchParams(window.location.search);
    
        if (urlParams.has('bulk-edit-category')) {
            jQuery('#bulk-add-category').hide();
            jQuery('#bulk-edit-category').trigger('click');
        }
    
        // Hide the Edit button if URL has ?bulk-create-product
        if (urlParams.has('bulk-create-category')) {
            jQuery('#bulk-edit-category').hide();
            jQuery('#bulk-add-category').trigger('click');
        }
    }, 100);
    // Global variables
    let originalCsvData = { headers: [], data: [] };
    let templateHeaders = [];
    let headerMapping = {};
    let errorRowIndexes = [];

    if( jQuery('#voucher-category-table').length ){
        $.fn.DataTable.ext.pager.numbers_length = 5;
        var voucherCategoryTable = $('#voucher-category-table').DataTable({
            dom: '<"top">rt<"bottom"lip>',
            pageLength: 10,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [[2, "desc"]],
            responsive: true,
            scrollX: false,
            paging: true,
            pagingType: "full_numbers",
            responsive: true,
            lengthMenu: [5, 10, 25, 50],
            columnDefs: [
                { orderable: false, targets: [0, 1,9] }
            ],
            language: {
                paginate: {
                    previous: "‹",
                    next: "›",
                    first: "«",
                    last: "»"
                },
                lengthMenu: "Show _MENU_ entries"
            },
            drawCallback: function () {
                var pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
                var pageInfo = this.api().page.info();
                var currentPage = pageInfo.page + 1;
                var totalPages = pageInfo.pages;

                // Remove old ellipses
                pagination.find('.ellipsis').remove();

                if (totalPages > 7) {
                    pagination.find('.paginate_button').each(function () {
                        var pageNum = parseInt($(this).text(), 10);
                        if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                            $(this).hide(); // Hide middle pages
                        }
                    });

                    // Insert ellipsis before the last page
                    if (currentPage < totalPages - 2) {
                        $('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                    }

                    // Insert ellipsis after the first page
                    if (currentPage > 3) {
                        $('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                    }
                }
            },
            initComplete: function () {
                // No length menu, so no need to add class here; remove if you want
            }
        });

    }
    // Category table header filter functionality End -----------------
    
    jQuery('#voucher-category-table thead th').each(function (index) {
        const colText = jQuery(this).text().trim();
        const isStatusCol = index === 8;   // status column
        const colSlug = jQuery(this).data('head_slug');
    
        // console.log('colSlug is : ', colSlug);
        // console.log('colText is : ', colText);
    
        let inputField = '';
    
        if (isStatusCol) {
            // Status column checkboxes
            inputField = `
                <div class="status-checkboxes" data-col="${index}">
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="c_status" class="status-filter" value="Active"> Active
                    </label>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="c_status" class="status-filter" value="Pending"> Pending
                    </label>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="c_status" class="status-filter" value="Deactivated"> Deactivated
                    </label>
                </div>
            `;
        } else {
            // all other columns: simple input
            inputField = `<input type="text" class="column-search" data-col="${index}" 
                placeholder="Search..." style="width:100%; padding:5px;">`;
        }
    
        // Inject into TH
        jQuery(this).html(`
            ${colText}
            ${(index > 1 && index !== 7 && index !== 9) ? `
                <span class="filter-icon" data-col="${index}" style="cursor:pointer; width:16px; height:16px;">
                    <i class="fa-solid fa-arrow-down"></i>
                    <i class="dashicons dashicons-filter"></i>
                </span>` : ''}
            ${inputField ? `
                <div class="filter-box" data-head_slug="${colSlug}" data-col="${index}" 
                    style="display:none; background:white; border:1px solid #ccc; padding:5px; z-index:999;">
                    ${inputField}
                </div>` : ''}
        `);
    });
    

    const categoryFilterBoxStates = {};

    jQuery('#voucher-category-table thead').on('click', '.filter-icon', function(e) {
        e.stopPropagation();
        const colIndex = jQuery(this).data('col');
        const filterBox = jQuery(`#voucher-category-table thead .filter-box[data-col="${colIndex}"]`);
        const isOpen = categoryFilterBoxStates[colIndex];
        // console.log('colIndex.......',colIndex);
        if(colIndex === 1 ){
            return;
        }
        if (isOpen) {
            filterBox.hide().removeClass('active_filter');
            filterBox.parent().removeClass('active_filter_wrapper');
            categoryFilterBoxStates[colIndex] = false;
        } else {
            filterBox.show().addClass('active_filter');
            filterBox.parent().addClass('active_filter_wrapper');
            categoryFilterBoxStates[colIndex] = true;

        }
        // Prevent clicks inside filter box from closing
        filterBox.off('click').on('click', function(e) {
            e.stopPropagation();
        });
    });

    jQuery('#voucher-category-table thead').on('keyup change', '.column-search', function () {
        const colIndex = jQuery(this).data('col');
        const searchValue = this.value;
        // console.log('Search in index...',colIndex);
        
        voucherCategoryTable.column(colIndex).search(searchValue);
        
        voucherCategoryTable.draw();
    });

    jQuery('#voucher-category-table thead').on('change', '.status-filter', function () {
        // console.log('XYZ');
        let selectedStatuses = jQuery('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex(jQuery(this).val());
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Active|Inactive)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            voucherCategoryTable.column(8).search(regex, true, false).draw();
        } else {
            // Reset when nothing checked
            voucherCategoryTable.column(8).search('').draw();
        }
    });


    // Category table header filter functionality End -----------------



    const $orderSearchInput = jQuery('.cat-search-input');
    const orderSearchInputValue = $orderSearchInput.val() ? $orderSearchInput.val().trim() : '';
    let $clearBtn = jQuery('#cat-search-clear'); // button should have unique ID

    if (orderSearchInputValue) {
        if ($clearBtn.length === 0) {
            $clearBtn = jQuery('<button>')
                .attr({
                    id: 'cat-search-clear',
                    class: 'btn btn-primary btn-md',
                    type: 'button'
                })
                .text('Reset')
                .css({ marginLeft: '10px' });

            $orderSearchInput.after($clearBtn);
        }
    } else {
        $clearBtn.remove();
    }

       
    $('.search-container').on('click', '#cat-search-clear', function () {
        // console.log('clear');
        window.location.href = window.location.pathname;
    });

    $('.search-container .cat-search-input').on('keyup', function () {
        //orderTable.search(this.value).draw();
        // console.log('SEarching');
        const $input = $(this);
        const value = $input.val().trim();

        // Check if the submit button already exists
        let $submitBtn = $('#cat-order-search-submit');
    
        if (value) {
            // If button doesn't exist, append it
            if ($submitBtn.length === 0) {
                $submitBtn = $('<button>')
                    .attr({
                        id: 'cat-order-search-submit',
                        class: 'btn btn-primary btn-md',
                        type: 'button'
                    })
                    .text('Search')
                    .css({ marginLeft: '10px' });
    
                $input.after($submitBtn);
                // Attach click event when creating button
                $submitBtn.on('click', function () {
                    const searchValue = $input.val().trim();
                    if (searchValue) {
                        const newUrl = window.location.pathname + '?search=' + encodeURIComponent(searchValue);
                        window.location.href = newUrl;
                    }
                });
            }
        } else {
            // Remove the button if input is empty
            $submitBtn.remove();
        }
    });
    // const searchInput = document.querySelector(".search-input");
    // const table = document.getElementById("voucher-category-table");
    // if( jQuery('tbody tr').length ){
    //     const rows = table.querySelectorAll("tbody tr");
    

    //     searchInput.addEventListener("input", function () {
    //         const query = searchInput.value.toLowerCase();
    
    //         rows.forEach(function (row) {
    //             const rowText = row.textContent.toLowerCase();
    //             if (rowText.includes(query)) {
    //                 row.style.display = "";
    //             } else {
    //                 row.style.display = "none";
    //             }
    //         });
    //     });
    // }
    
    // const searchInput = document.querySelector(".search-input");
    // const table = document.getElementById("voucher-category-table");
    // const rows = table.querySelectorAll("tbody tr");

    // searchInput.addEventListener("input", function () {
    //     const query = this.value.toLowerCase();

    //     rows.forEach(function (row) {
    //         const rowText = row.textContent.toLowerCase();
    //         row.style.display = rowText.includes(query) ? "" : "none";
    //     });
    // });
    function triggerDownload(url, filename) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename || 'products_export.csv'; // force download
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // export Category CSV code ----------------- START

    jQuery('.export-category-csv').on('click', function () {
        var button = jQuery(this);
        button.prop('disabled', true);
        button.text('Exporting...');
    
        let cat_id = '';
        let cat_name = '';
        let cat_assigned = '';
        let cat_description = '';
        let cat_priority = '';
        let cat_status = '';
    
        // Collect filter values
        jQuery('.category-listing-section #voucher-category-table thead th .filter-box.active_filter').each(function () {
            var $this = jQuery(this);
            var temp = $this.data('head_slug');
            var inputVal = '';
    
            if (temp === 'cat_status') {
                inputVal = $this.find('input[name="c_status"]:checked')
                    .map(function () { return jQuery(this).val(); })
                    .get()
                    .join(',');
                cat_status = inputVal;
            } else if ($this.find('input').length) {
                inputVal = $this.find('input').val();
            } else if ($this.find('select').length) {
                inputVal = $this.find('select').val();
            }
    
            if (temp === 'cat_id') cat_id = inputVal;
            if (temp === 'cat_name') cat_name = inputVal;
            if (temp === 'cat_assigned') cat_assigned = inputVal;
            if (temp === 'cat_description') cat_description = inputVal;
            if (temp === 'cat_priority') cat_priority = inputVal;
        });
    
        // Clear message area
        jQuery('#export-category-message').empty().hide();
    
        // Single AJAX call (no chunk logic)
        jQuery.ajax({
            url: bulkCreateCategory.ajaxurl,
            type: 'POST',
            data: {
                action: 'export_categories_csv',
                cat_id: cat_id,
                cat_name: cat_name,
                cat_assigned: cat_assigned,
                cat_description: cat_description,
                cat_priority: cat_priority,
                cat_status: cat_status,
                _ajax_nonce: bulkCreateCategory.nonce,
            },
            success: function (response) {
                if (response.success) {
                    // console.log(response);
    
                    // No categories found
                    if (response.data.empty_cat) {
                        jQuery('#export-category-message')
                            .text(response.data.message || 'No categories found for the given filters.')
                            .css({
                                'color': 'red',
                                'font-weight': 'bold',
                                'margin-top': '10px'
                            })
                            .show();
    
                        button.prop('disabled', false);
                        button.text('Export Category');
                        return;
                    }
    
                    // Download file directly
                    if (response.data.file_url) {
                        triggerDownload(response.data.file_url, 'category_export.csv');
                        button.text('Export Completed');
                        setTimeout(() => button.text('Export Category'), 2000);
                    } else {
                        jQuery('#export-category-message')
                            .text('Export completed, but no file URL found.')
                            .css({
                                'color': 'red',
                                'font-weight': 'bold',
                                'margin-top': '10px'
                            })
                            .show();
                    }
    
                } else {
                    jQuery('#export-category-message')
                        .text(response.data.message || 'Export failed. Please try again.')
                        .css({
                            'color': 'red',
                            'font-weight': 'bold',
                            'margin-top': '10px'
                        })
                        .show();
                }
    
                button.prop('disabled', false);
            },
            error: function () {
                jQuery('#export-category-message')
                    .text('An unexpected error occurred during export.')
                    .css({
                        'color': 'red',
                        'font-weight': 'bold',
                        'margin-top': '10px'
                    })
                    .show();
    
                button.prop('disabled', false);
                button.text('Export Category');
            }
        });
    
        // Trigger file download
        function triggerDownload(url, filename) {
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    });
    
    // export Category CSV code ----------------- END

    
    jQuery('.export-products-csv').on('click', function () {
        // console.log('this export-products-csv clicked');
        var button = jQuery(this);
        // button.prop('disabled', true);
        // button.text('Exporting...');
        // let p_id = '';
        let p_name = '';
        let p_denomination_type = '';
        let p_denomination = '';
        let p_status = '';
        
        $('.product-listing-section .product-table thead th .filter-box.active_filter').each(function () {
            var $this = $(this);
            var temp = $this.data('head_slug'); // e.g., "status", "product_name", etc.
            var inputVal = '';
        
            if (temp === 'status') { // <-- must match filter-box head slug
                inputVal = $this.find('input[name="p_status"]:checked')
                    .map(function() { return $(this).val(); })
                    .get()
                    .join(',');
                p_status = inputVal;
            } else if ($this.find('input').length) {
                inputVal = $this.find('input').val();
            } else if ($this.find('select').length) {
                inputVal = $this.find('select').val();
            }
        
            if (temp === 'product_name') p_name = inputVal;
            if (temp === 'denomination_type') p_denomination_type = inputVal;
            if (temp === 'denomination') p_denomination = inputVal;
        
            // console.log('p_status:', p_status);
        });
        $('#export-product-message').empty().hide();

        // If any product rows are checked, export only those; otherwise export all (with filters)
        var productIds = '';
        var $checkedRows = $('.product-listing-section .product-table tbody input[type="checkbox"]:checked').closest('tr[data-product-id]');
        if ($checkedRows.length > 0) {
            productIds = $checkedRows.map(function () { return $(this).data('product-id'); }).get().join(',');
        }

        var exportData = {
            action: 'export_products_csv',
            chunk_size: 100,
            offset: 0,
            p_name: p_name,
            p_denomination_type: p_denomination_type,
            p_denomination: p_denomination,
            p_status: p_status,
            _ajax_nonce: bulkCreateProduct.nonce,
        };
        if (productIds) {
            // console.log('productIds',productIds);
            exportData.product_ids = productIds;
        }

        // AJAX request to handle export
        $.ajax({
            url: bulkCreateProduct.ajaxurl,
            type: 'POST',
            data: exportData,
            success: function (response) {
                if (response.success) {
                    // Handle chunk export and call next chunk
                    if (response.data.offset < response.data.total_products) {
                        exportNextChunk(response.data.offset, productIds);
                    } else {
                        // Final export complete, now trigger download
                        // window.location.href = response.data.file_url;
                        triggerDownload(response.data.file_url, 'products_export.csv');
                        button.prop('disabled', false);
                        button.text('Export Completed');

                        setTimeout(function () {
                            button.text('Export Products');
                        }, 2000);
                    }
                } else {
                    $('#export-product-message')
                    .text(response.data.message)
                    .css({
                        'color': 'red',
                        'font-weight': 'bold',
                        'margin-top': '10px'
                    })
                    .show();
            
                    button.prop('disabled', false);
                    button.text('Export Products');
                }
            }
        });

        // Function to export the next chunk
        function exportNextChunk(offset, productIdsChunk) {
            var chunkData = {
                action: 'export_products_csv',
                chunk_size: 100,
                offset: offset,
                _ajax_nonce: bulkCreateProduct.nonce,
            };
            if (productIdsChunk) {
                chunkData.product_ids = productIdsChunk;
            }
            $.ajax({
                url: bulkCreateProduct.ajaxurl,
                type: 'POST',
                data: chunkData,
                success: function (response) {
                    if (response.success) {
                        if (response.data.offset < response.data.total_products) {
                            exportNextChunk(response.data.offset, productIdsChunk);
                        } else {
                            // window.location.href = response.data.file_url;
                            triggerDownload(response.data.file_url, 'products_export.csv');
                            button.prop('disabled', false);
                            button.text('Export Completed');

                            setTimeout(function () {
                                button.text('Export Products');
                            }, 2000);
                        }
                    } else {
                        $('#export-product-message')
                        .text(response.data.message)
                        .css({
                            'color': 'red',
                            'font-weight': 'bold',
                            'margin-top': '10px'
                        })
                        .show();
                
                        button.prop('disabled', false);
                        button.text('Export Products');
                    }
                }
            });
        }
    });
    jQuery(document).on("click", "#bulk-add-category", function (e) {
        e.preventDefault();

        const currentUrl = new URL(window.location);
        const searchParams = currentUrl.searchParams;

        if (!searchParams.has('bulk-create-category')) {
            // Clean up other param
            searchParams.delete('bulk-edit-category');
            searchParams.set('bulk-create-category', 'true');
            window.location.replace(currentUrl.toString());
        }
        //console.log(jQuery(".categories").length); // Check if element exists
        /*jQuery(".admin-profile").hide();
        jQuery(".category-title").hide();
        jQuery(".bulk-add-container").show();
        jQuery(".category-thumbnail-grid").hide();
        jQuery(".view-all-pro-title").hide();

        jQuery(".category-management-header, .category-listing-section, .save-next-button-controls, .categories").hide();
        jQuery(".categories").attr("style", "display: none !important;"); // Force hide*/
    });

    jQuery(document).on("click", "#bulk-edit-category", function (e) {
        e.preventDefault();

        const currentUrl = new URL(window.location);
        const searchParams = currentUrl.searchParams;

        if (!searchParams.has('bulk-edit-category')) {
            // Clean up other param
            searchParams.delete('bulk-create-category');
            searchParams.set('bulk-edit-category', 'true');
            window.location.replace(currentUrl.toString());
        }
        /*jQuery(".bulk-add-container").show();
        jQuery(".category-thumbnail-grid").hide();
        jQuery(".admin-profile").hide();
        jQuery(".category-title").hide();
        jQuery(".view-all-pro-title").hide();
        jQuery(".category-management-header, .category-listing-section, .save-next-button-controls, .categories").hide();
        jQuery(".categories").attr("style", "display: none !important;"); // Force hide*/
    });

    // Download category template
    jQuery('#download-category-template').on('click', function (e) {
        e.preventDefault();

        jQuery.ajax({
            url: bulkCreateCategory.ajaxurl, // or localized script var
            method: 'POST',
            data: {
                action: 'download_product_categories_csv_ajax',
                security: bulkCreateCategory.nonce
            },
            xhrFields: {
                responseType: 'blob' // Important to handle binary content
            },
            success: function (data, status, xhr) {
                const filename = 'product_categories.csv';

                const blob = new Blob([data], { type: 'text/csv' });
                const link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            },
            error: function (xhr, status, error) {
                // console.log('Error downloading CSV: ' + error);
                // console.log(error);
                // console.log(status);
                // console.log(xhr);
            }
        });

        /*const csvUrl = (typeof bulkCreateCategory !== 'undefined' && bulkCreateCategory.siteUrl) ? bulkCreateCategory.siteUrl.replace(/\/$/, '') + '/wp-content/uploads/2025/06/category-template.csv' : '/wp-content/uploads/2025/06/category-template.csv';
        const downloadLink = jQuery("<a>")
            .attr("href", csvUrl)
            .attr("download", "category-template.csv")
            .appendTo("body");

        downloadLink[0].click();
        downloadLink.remove();*/
    });

    // Upload CSV file
    jQuery('#upload-category-csv-btn').on('click', function () {
        jQuery('#file-upload-modal').modal('show');
    });

    let catIsLoading = false;
    let currentPage = 1;
    let currentSearch = '';

    // function debounce(fn, delay) {
    //     let t;
    //     return function (...args) {
    //         clearTimeout(t);
    //         t = setTimeout(() => fn.apply(this, args), delay);
    //     };
    // }
    

    function loadCategories(paged = 1, search = '') {
        if (catIsLoading) return;
        catIsLoading = true;
    
        jQuery('.thumbnail-wrapper').html('<p class="loading-state">Loading…</p>');
        // jQuery('#cat-pagination').html('<p class="loading-state">Loading…</p>');
    
        jQuery.ajax({
        url: customAjax.ajaxurl,
        method: 'POST',
        data: {
            action: 'load_gift_card_categories',
            nonce: customAjax.nonce,
            paged: paged,
            search: search
        },
        success: function (response) {
            if (response.success) {
            jQuery('.thumbnail-wrapper').html(response.data.content);
            renderPagination(response.data.total_pages, paged);
            // do NOT auto-show the grid if you only want it on explicit toggle
            currentPage = paged;
            } else {
            jQuery('.thumbnail-wrapper').html('<p>No categories found.</p>');
            jQuery('#cat-pagination').html('');
            }
        },
        error: function () {
            jQuery('.thumbnail-wrapper').html('<p>Error loading categories.</p>');
            jQuery('#cat-pagination').html('');
        },
        complete: function () {
            catIsLoading = false;
        }
        });
    }
    
    function renderPagination(totalPages, currentPage) {
        if (!totalPages || totalPages <= 1) {
        jQuery('#cat-pagination').html('');
        return;
        }
    
        let html = '';
    
        if (currentPage > 1) {
        html += '<button type="button" class="cat-page-btn prev-btn" data-page="' + (currentPage - 1) + '">‹</button> ';
        }
    
        for (let i = 1; i <= totalPages; i++) {
        html += '<button type="button" class="cat-page-btn' + (i === currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button> ';
        }
    
        if (currentPage < totalPages) {
        html += '<button type="button" class="cat-page-btn next-btn" data-page="' + (currentPage + 1) + '">›</button>';
        }
    
        jQuery('#cat-pagination').html(html);
    }
    
    // initial load (optional; remove if you only want to load on toggle)
    loadCategories(1);
    
    // persist search across pagination
    jQuery(document).on('click', '.cat-page-btn', function (e) {
        e.preventDefault();
        const page = parseInt(jQuery(this).data('page'), 10);
        if (!isNaN(page) && page > 0) {
        loadCategories(page, currentSearch); // 👈 keep currentSearch
        }
    });

    // Function to set cookie
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "")  + expires + "; path=/";
    }

    // Back to list handling
    jQuery(document).on('click', '.back-to-categorylist', function () {
        var savedView = getCookie("currentView");

        jQuery('.category-listing-section').hide();
        jQuery('.category-thumbnail-grid').hide();

        if (savedView === "thumbnail") {
            jQuery('#thumbnail-view-btn').trigger('click');
        }else{
            jQuery('#list-view-btn').trigger('click');
        }
        jQuery('.category-management-header').show();
        jQuery('.category-title').show();
        jQuery('.category-edit-view').hide();
        jQuery('.back-to-categorylist-wrapper').hide();
    });

    // Function to get cookie
    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return null;
    }

    $('#list-view-btn').on('click', function () {
        setCookie("currentView", "list", 7);
        setCookie("currentSearch", $('.cat-search-input').val(), 7);
        $(this).addClass('active');
        $('#thumbnail-view-btn').removeClass('active');
        $('.category-listing-section').show();
        $('.category-thumbnail-grid').hide();
        jQuery('.category-second-list-container').removeClass('thumbnail');
        if( !jQuery('.category-second-list-container').hasClass('list') )
            jQuery('.category-second-list-container').addClass('list');
    });

    $('#thumbnail-view-btn').on('click', function () {
        // console.log('aygiudygiagiugi8at78weiatt8 cli');
        currentSearch = $('.cat-search-input').val() ? $('.cat-search-input').val().trim() : '';
        // console.log('currentSearch: ',currentSearch);
        
        loadCategories(1, currentSearch);
        setCookie("currentView", "thumbnail", 7);
        setCookie("currentSearch", currentSearch, 7);
        $(this).addClass('active');
        $('#list-view-btn').removeClass('active');
        $('.category-listing-section').hide();
        $('.category-thumbnail-grid').show();
        jQuery('.category-second-list-container').removeClass('list');
        if( !jQuery('.category-second-list-container').hasClass('thumbnail') )
            jQuery('.category-second-list-container').addClass('thumbnail');
    });

    var savedView = getCookie("currentView");

    jQuery('.category-second-list-container').hide();
    // Buttons to disable/enable
    const buttonsToToggle = [
        '#bulk-edit-category',
        '#bulk-add-category',
        '.export-category-csv',
        '#create-new-category'
    ];


    if (jQuery('.category-second-list-container').length) {
        buttonsToToggle.forEach(btn => $(btn).prop('disabled', true));
    }
    // Disable buttons before slideDown
    setTimeout(() => {
        if (savedView === "thumbnail") {
            $('#thumbnail-view-btn').trigger('click');
        }else{
            $('#list-view-btn').trigger('click');
        }
        // jQuery('.category-second-list-container.list_true').slideDown();
        jQuery('.category-second-list-container.list_true').slideDown(400, function() {
            // After slideDown completes, enable buttons
            buttonsToToggle.forEach(btn => $(btn).prop('disabled', false));
        });
        jQuery('.category-second-list-container.list_create_cat .category-listing-section').hide();
        jQuery('.category-second-list-container.list_create_cat .category-thumbnail-grid').hide();
        // jQuery('.category-second-list-container.list_create_cat').slideDown();
        jQuery('.category-second-list-container.list_create_cat').slideDown(400, function() {
            // If you want, you can also enable buttons here
            buttonsToToggle.forEach(btn => $(btn).prop('disabled', false));
        });
    }, 1000);

    // search input (debounced)
    // jQuery(document).on('input', '.cat-search-input', debounce(function () {
    //     currentSearch = jQuery(this).val().trim();
    //     loadCategories(1, currentSearch);
    // }, 300));
    
    
    const uploadArea = $('#upload-area');
    const fileInput = $('#csv-file-input');

    uploadArea.on('click', function() {
        fileInput.trigger('click');
        // console.log('Clicked... in bulk');
    });

    fileInput.on('change', function() {
        const files = this.files;
        if (files.length) {
            const file = files[0];
            if (!file.name.endsWith('.csv')) {
                $('#file-error-msg').text('⚠️ Please select a valid CSV file.');
                fileInput.val('');
                $('#file-name-display').hide();
                return;
            }
            $('#file-error-msg').text('');
            $('#selected-file-name').text(file.name);
            $('#file-name-display').show();
        } else {
            $('#file-name-display').hide();
        }
    });



    //-----------
    
    jQuery('#submit-category-file-upload').on('click', function () {
        const file = jQuery('#csv-file-input')[0].files[0];
        if (!file) {
            jQuery('#file-error-msg').text('⚠️ Please select a CSV file.');
            return;
        }
    
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'custom_upload_category_csv');
        formData.append('security', bulkCreateCategory.nonce);
    
        jQuery('#upload-progress').show();
        jQuery('#progress-bar').css('width', '0%').text('0%');
    
        $.ajax({
            url: bulkCreateCategory.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function () {
                let xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (event) {
                    if (event.lengthComputable) {
                        let percentComplete = Math.round((event.loaded / event.total) * 100);
                        jQuery('#progress-bar').css('width', percentComplete + '%').text(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                if (response.success) {
                    originalCsvData = response.data.csv_data;
                    templateHeaders = response.data.csv_template_headers;
                    headerMapping = response.data.header_mappings;
    
                    const mandatoryHeaders = ['No','Category Name', 'Description', 'Priority', 'Icon Image', 
                                            'Banner Image', 'Thumbnail Image', 'Status', 'SKU\'s Assigned'];
                    let allHeadersMatched = mandatoryHeaders.every(header => headerMapping[header]);
    
                    var mappedData = mappedData || {};

                    if (!allHeadersMatched) {
                        showCategoryMappingInterface();
                    } else if (hasValidMandatoryData(mappedData)) {
                        showFinalPreview();
                    } else {
                        //showCategoryMappingInterface();
                        applyCategoryMappingAndPreview(headerMapping);
                    }

                } else {
                    jQuery('#file-error-msg').text(response.data.message);
                }
                jQuery('#file-upload-modal').modal('hide');
            },
            complete: function () {
                jQuery('#upload-progress').hide();
            },
            error: function () {
                jQuery('#file-error-msg').text('⚠️ An error occurred during upload.');
            }
        });
    });

    const mandatoryHeaders = ['Category Name', 'Priority', 'Icon Image', 'Status'];

    function hasValidMandatoryData(mappedData) {
        for (let header of mandatoryHeaders) {
            if (!mappedData[header] || !Array.isArray(mappedData[header])) {
                return false; // Missing column
            }
            for (let cellValue of mappedData[header]) {
                let value = (cellValue || '').toString().trim();

                // Check empty
                if (!value) {
                    return false;
                }

                // Example regex validation per field
                if (header === 'Priority' && !/^\d+$/.test(value)) { // number only
                    return false;
                }
                if (header === 'Icon Image' && !/^https?:\/\/.+\.(jpg|jpeg|png|gif)$/i.test(value)) { // valid image URL
                    return false;
                }
                if (header === 'Status' && !/^(active|inactive)$/i.test(value)) { // example status rule
                    return false;
                }
            }
        }
        return true;
    }
    
    // function showCsvPreview() {
    //     let previewHtml = '<div id="row-count" class="row-summary" style="text-align: right; font-weight: bold; margin-bottom: 10px;"></div><table id="catCsvPreviewTable" class="table table-bordered display"><thead><tr>';
    
    //     originalCsvData.headers.forEach(header => {
    //         previewHtml += `<th>${header}</th>`;
    //     });
    //     previewHtml += '</tr></thead><tbody>';
    
    //     originalCsvData.data.forEach(row => {
    //         previewHtml += '<tr>';
    //         row.forEach(cell => {
    //             previewHtml += `<td>${cell}</td>`;
    //         });
    //         previewHtml += '</tr>';
    //     });
    
    //     previewHtml += '</tbody></table>';
    //     jQuery('#csv-preview').html(previewHtml).removeClass('d-none');
    //     jQuery('.pre-preview-section').show();
    //     jQuery('#match-headers-btn').show();
    //     jQuery('.bulk-add-container').hide();
    
    //     jQuery('#catCsvPreviewTable').DataTable({
    //         paging: true,
    //         ordering: true,
    //         pageLength: 10,
    //         responsive: true,
    //         dom: 'tip'
    //     });
    
    //     const totalRows = originalCsvData.data.length;
    //     jQuery('#row-count').html(`
    //         <div class='validate-rows-message left-align'>
    //             ✅ ${totalRows} New categories have been uploaded. Please review.
    //         </div>
    //     `);
    // }
    
    jQuery('#match-headers-btn').on('click', function () {
        showCategoryMappingInterface();
    });

    // Mapping Interface for Categories
    function showCategoryMappingInterface() {

        // console.log("🔍 Showing mapping interface...");
        // console.log("templateHeaders:", templateHeaders);
        // console.log("originalCsvData:", originalCsvData);
        // console.log("originalCsvData.headers:", originalCsvData?.headers);
        // console.log("headerMapping:", headerMapping);
    
        if (!templateHeaders || !originalCsvData || !originalCsvData.headers) {
            console.error('❌ Required data is missing. Cannot display header mappings.');
            return;
        }
    
        let mappingHtml = '';
    
        templateHeaders.forEach(header => {
            const selectedHeader = headerMapping[header] || '';
            const isMandatory = ['No','Category Name', 'Description', 'Priority', 'Icon Image',  
                                 'Banner Image', 'Thumbnail Image', 'Status', 'SKU\'s Assigned'].includes(header);
    
            mappingHtml += `
                <tr>
                    <td>${header}</td>
                    <td class="text-center">→</td>
                    <td>
                        <select class="form-select mapping-select" data-template="${header}">
                            <option value="">Select...</option>
                            ${originalCsvData.headers.map(csvHeader =>
                                `<option value="${csvHeader}" ${csvHeader === selectedHeader ? 'selected' : ''}>${csvHeader}</option>`
                            ).join('')}
                        </select>
                        <div class="error-msg text-danger mt-1" style="display:none;">⚠️ This field is required</div>
                    </td>
                    <td class="${isMandatory ? 'mandatory' : 'optional'}">${isMandatory ? '(Mandatory)' : '(Optional)'}</td>
                </tr>
            `;
        });
    
        jQuery('#mapping-interface').html(mappingHtml);
        jQuery('#mapping-modal').modal('show');
        updateDisabledOptions();
    }
    jQuery('#mapping-interface').on('change', '.mapping-select', function () {
        const templateHeader = jQuery(this).data('template');
        const selectedHeader = jQuery(this).val();
        headerMapping[templateHeader] = selectedHeader;

        // Hide error message if a valid selection is made
        if (selectedHeader) {
            jQuery(this).siblings('.error-msg').hide();
        }
        updateDisabledOptions();

    });

    function updateDisabledOptions() {
        let selectedValues = [];

        // Get all selected values
        jQuery('.mapping-select').each(function () {
            let selectedValue = jQuery(this).val();
            if (selectedValue) {
                selectedValues.push(selectedValue);
            }
        });

        // Loop through all dropdowns and disable the selected values
        jQuery('.mapping-select').each(function () {
            let currentDropdown = jQuery(this);
            let currentSelected = currentDropdown.val();

            // Remove all disabled attributes first
            currentDropdown.find('option').prop('disabled', false);

            // Disable options that are already selected in other dropdowns
            selectedValues.forEach(value => {
                if (value !== currentSelected) {
                    currentDropdown.find(`option[value="${value}"]`).prop('disabled', true);
                }
            });
        });
    }
    jQuery('#apply-csv-mapping').on('click', function () {
        let missingMandatoryFields = [];
        const mandatoryHeaders = ['No','Category Name', 'Description', 'Priority', 'Icon Image',
                                  'Banner Image', 'Thumbnail Image', 'Status', 'SKU\'s Assigned'];
    
        templateHeaders.forEach(header => {
            const selectedHeader = headerMapping[header];
            if (mandatoryHeaders.includes(header) && (!selectedHeader || selectedHeader.trim() === '')) {
                missingMandatoryFields.push(header);
            }
        });
    
        jQuery('.mapping-select').siblings('.error-msg').hide();
    
        if (missingMandatoryFields.length > 0) {
            jQuery('.mapping-select').each(function () {
                const templateHeader = jQuery(this).data('template');
                if (missingMandatoryFields.includes(templateHeader)) {
                    jQuery(this).siblings('.error-msg').show();
                }
            });
    
            let errorMessage = `<p><strong>Missing Required Fields:</strong> ${missingMandatoryFields.join(', ')}</p>`;
            if (jQuery('#mapping-error-msg').length === 0) {
                jQuery('#modal-content').prepend(`<div id="mapping-error-msg" class="alert alert-danger">${errorMessage}</div>`);
            } else {
                jQuery('#mapping-error-msg').html(errorMessage).show();
            }
            return;
        }
    
        applyCategoryMappingAndPreview(headerMapping);
        jQuery('#mapping-modal').modal('hide');
        jQuery('#csv-preview').hide();
    });
    
    function applyCategoryMappingAndPreview(mapping) {
        // console.log('this runss.....');

        const selectedHeaders = Object.keys(mapping).filter(templateHeader => mapping[templateHeader] !== '');
        
        originalCsvData.filteredHeaders = selectedHeaders;
    
        // Safety check for originalCsvData.data
        if (!Array.isArray(originalCsvData.data) || originalCsvData.data.length === 0) {
            originalCsvData.mappedData = [];
            errorRowIndexes = [];
    
            jQuery('#preview-section').show();
            jQuery('#row-count-summary').html(`
                <div class='validate-rows-message left'>
                    ⚠️ No data rows found in the CSV file.
                </div>`);
            renderCategoryPreview();
            jQuery('.bulk-add-container').hide();
            return;
        }

        const rules = {
            'Category Name': /^[a-zA-Z0-9 ]+$/,                         // Text + Numbers + Space
            'Description': /[\s\S]*/,                                   // Anything allowed
            'Priority': /^[0-9]+$/,                                     // Numbers only
            'Icon Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,        // Image URL
            'Banner Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,      // Image URL
            'Thumbnail Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,   // Image URL
            'Status': /^[a-zA-Z]+$/,                                    // Text only
        };
    
        originalCsvData.mappedData = originalCsvData.data.map(row => {
            return selectedHeaders.map(templateHeader => {
                const originalHeader = mapping[templateHeader];
                const index = originalCsvData.headers.indexOf(originalHeader);
                return index !== -1 ? row[index] : '';
            });
        });
    
        // Validate against the MAPPED data
        const mandatoryFields = ['Category Name', 'Priority', 'Icon Image', 'Status'];
        errorRowIndexes = [];
    

        originalCsvData.mappedData.forEach((row, rowIndex) => {
            const hasError = row.some((cell, index) => {
                const header = selectedHeaders[index];
                const regex = rules[header];
                if(!regex){
                    // console.log('mandatoryFields',mandatoryFields);
                    return mandatoryFields.includes(header) && (!cell || cell.trim() === '');
                }else{
                    // console.log('mandatoryFields else',mandatoryFields);
                    return (
                        (mandatoryFields.includes(header) && (!cell || cell.trim() === '')) ||
                        (cell !== '' && !regex.test(cell))
                    );
                }
            });
            if (hasError) {
                errorRowIndexes.push(rowIndex);
            }
        });
        //console.log('errorRowIndexes: ',errorRowIndexes);
    
        const totalRows = originalCsvData.mappedData.length;
        const errorRows = errorRowIndexes.length;
        const validRows = totalRows - errorRows;
        const siteUrl = window.location.origin; 

        if (errorRows === 0) {
            showFinalPreview();
        } else {
            jQuery('#preview-section').show();
            jQuery('#row-count-summary').html(`
                <div class='validate-rows-message left'>
                    <img src="${siteUrl}/wp-content/uploads/2025/10/Check-circle.svg" alt="Check icon"></img> <span class="valid-rows"> <strong>${validRows}</strong> Lines have been uploaded successfully without errors.</span><br>
                    <img src="${siteUrl}/wp-content/uploads/2025/10/Error.svg" alt="Error icon"></img> <span class="invalid-rows"> We found errors on <strong>${errorRows}</strong> lines</span><br>
                </div>`);
            renderCategoryPreview();
            jQuery('.bulk-add-container').hide();
        }
    }
    
    
    function renderCategoryPreview() {
        // Only show mapped headers, no extra "Row" column
        const headersHtml = `<tr>${originalCsvData.filteredHeaders.map(h => `<th>${h}</th>`).join('')}</tr>`;
        jQuery('#cat-csv-preview-table thead').html(headersHtml);
    
        let rowsHtml = '';
    
        originalCsvData.mappedData.forEach((row, rowIndex) => {
            const hasError = errorRowIndexes.includes(rowIndex);
            const rowHtml = row.map((cell, cellIndex) => {
                const header = originalCsvData.filteredHeaders[cellIndex];
                const regex = rules[header];
                //const isMandatory = ['No','Category Name', 'Description', 'Priority', 'Icon Image', 'Banner Image', 'Thumbnail Image', 'Status', 'SKU\'s Assigned'].includes(header);
                const isMandatory = ['Category Name', 'Priority', 'Icon Image', 'Status'].includes(header);
                const isEmpty = !cell || cell.trim() === '';

                // console.log('==================');
                // console.log('header: ',header);
                // console.log('rules[header]: ',rules[header]);
                if(!regex){
                    return `<td contenteditable="false" class="${isMandatory && isEmpty ? 'empty-field' : ''}">${cell}</td>`;
                }else{
                    // console.log('regex.test(cell): ',regex.test(cell));
                    return `<td contenteditable="false" class="${isMandatory && (isEmpty || !regex.test(cell)) ? 'empty-field' : ''}">${cell}</td>`;
                }
                
                return `<td contenteditable="false" class="${isMandatory && isEmpty ? 'empty-field' : ''}">${cell}</td>`;
            }).join('');
    
            rowsHtml += `<tr data-original-index="${rowIndex}" ${hasError ? '' : 'style="display: none;"'}>
                ${rowHtml}
            </tr>`;
        });
    
        jQuery('#cat-csv-preview-table tbody').html(rowsHtml);
    
        // Reset DataTable instance
        if ($.fn.DataTable.isDataTable('#cat-csv-preview-table')) {
            jQuery('#cat-csv-preview-table').DataTable().destroy();
        }
        jQuery('#final-preview-section').show();
        // jQuery('.csv-preview').show();
        jQuery('#match-headers-btn').hide();
        jQuery('.preview-header').show();

        
        // jQuery('#cat-csv-preview-table').DataTable({
        //     paging: true,
        //     ordering: true,
        //     pageLength: 10,
        //     responsive: true,
        //     dom: 'tip'
        // });
    }
    
    
    function showFinalPreview() {
        let previewHtml = '<div id="row-count" class="row-summary" style="text-align: right; font-weight: bold; margin-bottom: 10px;"></div><table id="csvFinalPreviewTable" class="table table-bordered display"><thead><tr>';
    
        // Use mapped headers (template headers)
        originalCsvData.headers.forEach(header => {
            previewHtml += `<th>${header}</th>`;
        });
        previewHtml += '</tr></thead><tbody>';
    
        // Use mapped data
        originalCsvData.data.forEach(row => {
            previewHtml += '<tr>';
            row.forEach(cell => {
                previewHtml += `<td>${cell}</td>`;
            });
            previewHtml += '</tr>';
        });
    
        previewHtml += '</tbody></table>';
        jQuery('#final-cat-preview-section').html(previewHtml).removeClass('d-none');
        jQuery('#final-preview-section').hide();
        jQuery('#final-cat-preview-section').show();
        jQuery('.bulk-add-container').hide();
        jQuery('#match-headers-btn').hide();
        jQuery('.preview-header').hide();
        const totalRows = originalCsvData.data.length;

        // 👇 Show/hide final button based on rows
        if (totalRows === 0) {
            jQuery('#final-create-category-btn').hide();
        } else {
            jQuery('#final-create-category-btn').show();
        }
        
        // jQuery('#final-create-category-btn').show();
        jQuery('#csvFinalPreviewTable').DataTable({
            // paging: true,
            // ordering: true,
            // pageLength: 10,
            // responsive: true,
            // scrollX: true,
            // dom: 'tip'
            processing: true,
            searching: true,
            responsive: true,
            scrollX: true,
            pageLength: 5,
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
                const pageInfo = api.page.info();
        
                // ✅ Hide pagination when only one page
                if (pageInfo.pages <= 1) {
                    $(api.table().container()).find('.dataTables_paginate').hide();
                } else {
                    $(api.table().container()).find('.dataTables_paginate').show();
                }
        
                // Handle checkbox sync
                const pageRows = api.rows({ page: 'current' }).nodes().to$();
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
        // jQuery('#final-cat-preview-table tbody').empty(); // Clear previous rows

        jQuery('#row-count').html(`
            <div class='validate-rows-message left-align'>
                ✅ ${totalRows} categories are ready to be submitted.
            </div>
        `);
    }
    

    function checkForRemainingErrors() {
        let hasErrors = jQuery('.empty-field').length > 0;
        //console.log('hasErrors',hasErrors);
        if (hasErrors) {
            jQuery('#bulk-upload-cat-preview-btn').prop('disabled', true);
            jQuery('#bulk-upload-cat-preview-btn').hide();
            jQuery('.page-template-bulk-create-category .preview-header').show();
        } else {
            jQuery('#bulk-upload-cat-preview-btn').prop('disabled', false);
            jQuery('#bulk-upload-cat-preview-btn').show();
            jQuery('.page-template-bulk-create-category .preview-header').hide();
        }

        const selectedHeaders = Object.keys(headerMapping).filter(templateHeader => headerMapping[templateHeader] !== '');
        const mandatoryFields = ['Category Name', 'Priority', 'Icon Image', 'Status'];
        errorRowIndexes = [];

        originalCsvData.mappedData.forEach((row, rowIndex) => {
            const hasError = row.some((cell, index) => {
                const header = selectedHeaders[index];
                const regex = rules[header];
                if (!regex) {
                    return mandatoryFields.includes(header) && (!cell || cell.trim() === '');
                }else{
                    return mandatoryFields.includes(header) && ((!cell || cell.trim() === '') || !regex.test(cell));
                }
            });
            if (hasError) {
                errorRowIndexes.push(rowIndex);
            }
        });
        //console.log('errorRowIndexes: ',errorRowIndexes);
    
        const totalRows = originalCsvData.mappedData.length;
        const errorRows = errorRowIndexes.length;
        const validRows = totalRows - errorRows;

        //console.log('errorRows: ',errorRows);
    
        if (errorRows === 0) {
            if( jQuery('#final-cat-preview-section #row-count').length ){}else{
                jQuery('<div id="row-count" class="row-summary" style="text-align: right; font-weight: bold; margin-bottom: 10px;"></div>').insertAfter('#final-cat-preview-section h3');
            }
            jQuery('#row-count-summary').html('');
            jQuery('#row-count').html(`
                <div class='validate-rows-message left-align'>
                    ✅ ${totalRows} categories are ready to be submitted.
                </div>
            `);
        } else {
            jQuery('#preview-section').show();
            jQuery('#row-count').remove('');
            jQuery('#row-count-summary').html(`
                <div class='validate-rows-message left'>
                    ✅ ${validRows} valid rows <br> 
                    ❌ ${errorRows} rows with errors
                </div>`);
            //renderCategoryPreview();
            jQuery('.bulk-add-container').hide();
        }
    }

    jQuery('#bulk-upload-cat-preview-btn').on('click', function () {
    
        // Ensure originalCsvData and its mappedData property exist
        if (!originalCsvData) {
            console.error('originalCsvData is not defined');
            return;
        }
    
        if (!originalCsvData.mappedData) {
            originalCsvData.mappedData = [];
        }
    
        // Loop through each row in the preview table
        jQuery('#cat-csv-preview-table tbody tr').each(function (rowIndex) {
            // console.log('inside it Preview Button Clicked');

            // Make sure the row in the array exists
            if (!originalCsvData.mappedData[rowIndex]) {
                originalCsvData.mappedData[rowIndex] = [];
            }
    
            jQuery(this).find('td').each(function (cellIndex) {
                let cellText;
    
                // If there's an input, get its value
                const input = jQuery(this).find('input');
                if (input.length) {
                    cellText = input.val().trim();
                } else {
                    // Otherwise get the text
                    cellText = jQuery(this).text().trim();
                }
    
                originalCsvData.mappedData[rowIndex][cellIndex] = cellText;
            });
        });
    
        // Copy the headers to the final preview table
        const headersHtml = jQuery('#cat-csv-preview-table thead').html();
        jQuery('#final-cat-preview-table thead').html(headersHtml);
    
        // Build new rows for the final preview
        let rowsHtml = '';
        originalCsvData.mappedData.forEach((row) => {
            rowsHtml += `<tr>${row.map(cell => `<td>${cell || ''}</td>`).join('')}</tr>`;
        });
    
        // Set final preview table body
        jQuery('#final-cat-preview-table tbody').html(rowsHtml);
        let cdt = jQuery('#final-cat-preview-table').DataTable({
            paging: true,
            ordering: true,
            pageLength: 10,
            lengthChange: true,
            lengthMenu: [5, 10, 25, 50, 100],
            paging: true,
            pagingType: "full_numbers",
            responsive: false,
            scrollX: true,
            language: {
                paginate: {
                    previous: "‹",
                    next: "›",
                    first: "«",
                    last: "»"
                }
            },
            dom: 'tip'
        });
        // Toggle views
        jQuery('.preview-header').hide();
        jQuery('#final-cat-preview-section').show();
        jQuery('#final-preview-section').hide();
        jQuery('#final-create-category-btn').show();
        jQuery('#bulk-upload-cat-preview-btn').hide();
        jQuery('.csvFinalPreviewTable').removeClass('d-none');
        cdt.columns.adjust();

    });
    

    jQuery('#edit-csv-errors').on('click', function () {

        const $btn = jQuery(this);
        const isEditing = $btn.hasClass('editing');
        if (isEditing) {
            // TURN OFF edit mode
            $btn.removeClass('editing').addClass('not-editing').text('Edit Errors');
    
            jQuery('.empty-field').each(function () {
                jQuery(this).attr('contenteditable', 'false').css({
                    'background-color': '#ED1E0926', 
                    'outline': 'none'
                });
            });
        } else {
            // TURN ON edit mode
            $btn.removeClass('not-editing').addClass('editing').text('Cancel Editing');
    
            jQuery('.empty-field').each(function () {
                jQuery(this).attr('contenteditable', 'true').css({
                    'background-color': '#fff3cd', 
                    'outline': '1px solid red'
                });
            });
        }

        // if ($btn.hasClass('editing')) {
        //     $btn.removeClass('editing').addClass('not-editing');
        //     $btn.text('Edit errors');
        // } else {
        //     $btn.removeClass('not-editing').addClass('editing');
        //     $btn.text('Cancel Editing');
        // }
        // jQuery('.empty-field').each(function () {
        //     jQuery(this).attr('contenteditable', 'true').css({
        //         'background-color': '#fff3cd',
        //         'outline': '1px solid red'
        //     });
        // });
    
        // Allow tabbing through error fields only
        jQuery('.empty-field').on('keydown', function (e) {
            if (e.key === "Tab") {
                e.preventDefault();
                let next = jQuery('.empty-field').eq(jQuery('.empty-field').index(this) + 1);
                if (next.length) next.focus();
            }
        });
    
        // Validate only on blur
        jQuery('.empty-field').on('blur', function () {
            const $cell = jQuery(this);
            const text = $cell.text().trim();
            //console.log($cell.index());
            const header = originalCsvData.filteredHeaders[$cell.index()]; // Adjust if first column was removed
    
            // Define regex rules for category fields
            const rules = {
                'Category Name': /^[a-zA-Z0-9 ]+$/,                         // Text + Numbers + Space
                'Description': /[\s\S]*/,                                   // Anything allowed
                'Priority': /^[0-9]+$/,                                     // Numbers only
                'Icon Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,        // Image URL
                'Banner Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,      // Image URL
                'Thumbnail Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,   // Image URL
                'Status': /^[a-zA-Z]+$/,                                    // Text only
                "SKU\'s Assigned": /^[a-zA-Z0-9]+$/                          // Text + Numbers (no space)
            };

            /*console.log('originalCsvData: ',originalCsvData);
            console.log('cell: ',$cell);
            console.log('cell Index: ',$cell.index());
            console.log('row Index: ',$cell.parent('tr').attr('data-original-index'));*/

            originalCsvData.mappedData[$cell.parent('tr').attr('data-original-index')][$cell.index()] = text;
            originalCsvData.data[$cell.parent('tr').attr('data-original-index')][$cell.index()] = text;
            //console.log('originalCsvData: ',originalCsvData);
            // const regex = rules[header];
    
            // if (regex) {
            //     if (text === '' || !regex.test(text)) {
            //         $cell.addClass('empty-field').css({
            //             'background-color': '#ffcccc',
            //             'outline': '1px solid red'
            //         });
            //     } else {
            //         $cell.removeClass('empty-field').css({
            //             'background-color': '',
            //             'outline': ''
            //         });
            //     }
            // } else {
            //     if (text === '') {
            //         $cell.addClass('empty-field').css({
            //             'background-color': '#ffcccc',
            //             'outline': '1px solid red'
            //         });
            //     } else {
            //         $cell.removeClass('empty-field').css({
            //             'background-color': '',
            //             'outline': ''
            //         });
            //     }
            // }

            const regex = rules[header];
            const isEmpty = text === '';
            const isInvalid = regex && !regex.test(text);

            if (isEmpty || isInvalid) {
                $cell.addClass('empty-field').css({
                    'background-color': '#ffcccc',
                    'outline': '1px solid red'
                });
            } else {
                $cell.removeClass('empty-field').css({
                    'background-color': isEditing ? '#fff3cd' : '#ED1E0926',
                    'outline': isEditing ? '1px solid red' : 'none'
                });
            }

            checkForRemainingErrors();
        });
    });
    
    // Final create categories
    jQuery('#final-create-category-btn').on('click', function () {
        const $btn = jQuery(this);
        const $text = $btn.find('.btn-text');
        const $spinner = $btn.find('.spinner-border');
        const urlParams = new URLSearchParams(window.location.search);
        const isEditMode = urlParams.has('bulk-edit-category');
    
        // Collect data from first table
        const finalPreviewCategoryData1 = [];
        jQuery('#final-cat-preview-table tbody tr').each(function () {
            const row = {};
            jQuery(this).find('td').each(function (index) {
                if ( !isEditMode && index === 0) return;
                const header = jQuery('#final-cat-preview-table thead th').eq(index).text().trim();
                const value = jQuery(this).text().trim();
                if (header !== '') {
                    row[header] = value;
                }
            });
            finalPreviewCategoryData1.push(row);
        });
    
        // Collect data from second table
        const customHeaders = ['No', 'Category Name', 'Description', 'Priority', 'Icon Image', 'Banner Image', 'Thumbnail Image', 'Status', 'SKU\'s Assigned'];

        const finalPreviewCategoryData2 = [];
        jQuery('#csvFinalPreviewTable tbody tr').each(function () {
            const row = {};
            jQuery(this).find('td').each(function (index) {
                if ( !isEditMode && index === 0) return;
                const header = customHeaders[index];
                const value = jQuery(this).text().trim();
                if (header !== '') {
                    row[header] = value;
                }
            });
            finalPreviewCategoryData2.push(row);
        });
    
        // Combine both datasets
        const combinedCategoryData = [...finalPreviewCategoryData1, ...finalPreviewCategoryData2];
    
        // Debug
        // console.log('Combined Data:', combinedCategoryData);
    
        if (combinedCategoryData.length === 0) {
            alert('No data available to create categories.');
            return;
        }
    
        // Disable button and show spinner while processing
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        var $button_text = $text.text();
        $text.text('Processing...');
    
        $.ajax({
            url: bulkCreateCategory.ajaxurl,
            type: 'POST',
            data: {
                action: 'create_bulk_categories',
                edit_mode: isEditMode ? 1 : 0,
                categories: JSON.stringify(combinedCategoryData),
                security: bulkCreateCategory.nonce
            },
            success: function (response) {
                if (response.success) {
                    jQuery('#success-message').text(response.data.message).show();
                    setTimeout(() => {
                        window.location.href = (typeof bulkCreateCategory !== 'undefined' && bulkCreateCategory.siteUrl) ? bulkCreateCategory.siteUrl.replace(/\/$/, '') + '/create-category/' : '/create-category/';
                    }, 3000);
                } else {
                    jQuery('#success-message').text('Error: ' + response.data.message).css('color', 'yellow').show();
                }
            },
            error: function () {
                jQuery('#success-message').text('AJAX error occurred.').css('color', 'green').show();
            },
            complete: function () {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                // console.log('$button_text: ', $button_text);
                $text.text($button_text);
            }
        });
    });
    
    
    
    

    // Remove error lines
    jQuery('#remove-error-lines').on('click', function () {
        let removedCount = 0;
        const newData = [];

        //console.log(')))))))))))))))))))))');
        //console.log(originalCsvData);
    
        originalCsvData.data.forEach((row, index) => {
            if (!errorRowIndexes.includes(index)) {
                newData.push(row);
            } else {
                removedCount++;
            }
        });
    
        originalCsvData.data = newData;
        applyCategoryMappingAndPreview(headerMapping);

    
        // Show message in #cat-message instead of alert
        jQuery('#cat-message').html(`
            <div class="alert alert-info">
                ✅ ${removedCount} error ${removedCount === 1 ? 'line was' : 'lines were'} removed successfully.
            </div>
        `);
    
        // showFinalPreview();
    });
    

    // Download and resubmit
    jQuery('.product-container .preview-header #download-resubmit').on('click', function () {
        let csvContent = "data:text/csv;charset=utf-8,";
        let rows = [];

        // Headers
        rows.push(originalCsvData.headers.join(','));

        // Data with error markers
        originalCsvData.data.forEach((row, rowIndex) => {
            const markedRow = row.map((cell, cellIndex) => {
                const header = originalCsvData.headers[cellIndex];
                const isMandatory = ['Category Name', 'Priority', 'Icon Image', 'Status'].includes(header);
                const isEmpty = !cell || cell.trim() === '';
                
                return isMandatory && isEmpty ? "**ERROR** " + cell : cell;
            });
            rows.push(markedRow.join(','));
        });

        csvContent += rows.join('\n');
        let encodedUri = encodeURI(csvContent);

        let link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "category-upload-with-errors.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});
document.addEventListener("DOMContentLoaded", function () {
    const url = window.location.href;

    const createStep = document.querySelector(".step-create");
    const editStep = document.querySelector(".step-edit");
    const addStepTitle = document.querySelector(".bulk-add-category-title");
    const editStepTitle = document.querySelector(".bulk-edit-category-title");


    // Hide both by default
    if (createStep) createStep.style.display = "none";
    if (editStep) editStep.style.display = "none";
    if (editStepTitle) editStepTitle.style.display = "none";
    if (addStepTitle) addStepTitle.style.display = "none";


    // Show based on URL
    if (url.includes("bulk-create-category=")) {
        if (createStep) createStep.style.display = "block";
        if (addStepTitle) addStepTitle.style.display = "block";
    } else if (url.includes("bulk-edit-category=")) {
        if (editStep) editStep.style.display = "block";
        if (editStepTitle) editStepTitle.style.display = "block";
    }
});