jQuery(document).ready(function ($) {

    // Category carousel — init here to avoid CSP inline-script violations
    var $carousel = $('.categories.owl-carousel');
    if ($carousel.length && typeof $.fn.owlCarousel !== 'undefined') {
        $carousel.owlCarousel({
            loop: true,
            margin: 40,
            nav: true,
            dots: false,
            autoplay: true,
            autoplayTimeout: 3000,
            autoplayHoverPause: true,
            responsive: {
                0:    { items: 2 },
                600:  { items: 3 },
                1000: { items: 5 }
            },
            navText: [
                '<i class="fa fa-chevron-left"></i>',
                '<i class="fa fa-chevron-right"></i>'
            ]
        });
        $(window).on('load resize', function () {
            $carousel.trigger('refresh.owl.carousel');
        });
    }

    
    // const thumbView = document.getElementById('product-thumbnail-view');
    // if (thumbView) {
    //     setTimeout(() => {
    //         console.log('jgfyjfyd');
    //         thumbView.style.display = 'none';
    //     }, 1000);   
    // }

    // Redirect handlers
    const bulkCreateProductURL = (typeof bulkCreateProduct !== 'undefined' && bulkCreateProduct.siteUrl) ? bulkCreateProduct.siteUrl.replace(/\/$/, '') + '/bulk-create-product/?bulk-create-products=true' : '/bulk-create-product/?bulk-create-products=true';

    // Handle menu click and store flag
    $(".nav-bulk-create-product").on("click", function (e) {
        e.preventDefault();
        sessionStorage.setItem("triggerBulkAddProducts", "1");
        window.location.href = bulkCreateProductURL;
    });

    // On page load, trigger button only if redirected from nav
    $(document).ready(function () {
        if (sessionStorage.getItem("triggerBulkAddProducts") === "1" && window.location.pathname === "/bulk-create-product/") {
            setTimeout(() => {
                $("#bulk-add-products").trigger("click");
                sessionStorage.removeItem("triggerBulkAddProducts");
            }, 200);
        }
    });


    const createNewBulkProduct = document.getElementById('create-new-bulk-product');
    if (createNewBulkProduct) {
        createNewBulkProduct.addEventListener('click', function () {
            window.location.href = (typeof bulkCreateProduct !== 'undefined' && bulkCreateProduct.siteUrl) ? bulkCreateProduct.siteUrl.replace(/\/$/, '') + '/create-product/' : '/create-product/';
        });
    }
    const urlParams = new URLSearchParams(window.location.search);

    // Hide the Add button if URL has ?bulk-edit-products
    if (urlParams.has('bulk-edit-products')) {
        $('#bulk-add-products').hide();
        setTimeout(() => {
            // console.log('Im inside create product bulk');
            $('#bulk-edit-products').trigger('click');
        }, 100);
    }

    // Hide the Edit button if URL has ?bulk-create-product
    if (urlParams.has('bulk-create-products')) {
        $('#bulk-edit-products').hide();
        setTimeout(() => {
            const thumbView = document.getElementById('product-thumbnail-view');
            thumbView.style.display = 'none';
            console.log('HIIIIIIIIIIIIIIIIIIIIs');
        }, 999);
        setTimeout(() => {
            // console.log('Im inside create product bulk');
            $('#bulk-add-products').trigger('click');
        }, 100);
    }


    if (!$.fn.DataTable) return;

    $.fn.DataTable.ext.pager.numbers_length = 5;

    // Custom ordering: data-sort ASC, missing last
    $.fn.dataTable.ext.order['dom-data-sort-null-last'] = function (settings, col) {
        return this.api()
            .column(col, { order: 'index' })
            .nodes()
            .map(function (td) {
                let val = $(td).attr('data-sort');
                val = parseInt(val, 10);
    
                // Treat undefined, empty, or -1 as missing → push to bottom
                if (isNaN(val) || val < 0) {
                    return Number.MAX_SAFE_INTEGER;
                }
                return val; // valid number
            });
    };

    var productTable;

    productTable = $('.product-table').DataTable({
        dom: '<"top">rt<"bottom"lip>',
        paging: true,
        searching: true,
        ordering: true,
        responsive: true,
        scrollX: false,
        pageLength: 25,
        lengthMenu: [5, 25, 50, 100],
        pagingType: "full_numbers",
        language: {
            search: "",
            searchPlaceholder: "Search products...",
            paginate: {
                previous: "‹",
                next: "›",
                first: "«",
                last: "»"
            }
        },
        columnDefs: [
            { orderable: false, targets: [0, 1, 6] },
            { searchable: false, targets: [0, 1, 6] }
        ],
        order: [],
        drawCallback: function () {
            var pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
            var pageInfo = this.api().page.info();
            var currentPage = pageInfo.page + 1;
            var totalPages = pageInfo.pages;

            pagination.find('.ellipsis').remove(); // Remove old ellipses

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
            const params = new URLSearchParams(window.location.search);
            if (params.get('bulk-edit-products') === 'true' || params.get('bulk-create-products') === 'true') {
                const container = document.getElementById('product-list-view');
                if (container) {
                    // console.log('sjhsjfgsdjf');
                    container.style.display = 'none';
                }
    
                const tableWrapper = $(this).closest('.dataTables_wrapper');
                if (tableWrapper.length) {
                    // console.log('sjhsjfgsdjf 222');
                    tableWrapper.hide();
                }
            }
        }
    });

    // Filter code START
    $('.product-table thead th').each(function (index) {
        const colText = $(this).text().trim();
        const isCheckboxCol = index === 0; 
        const isStatusCol = index === 5;
        const isDropdownCol = index === 3;
        // const isDetailsCol = index === 6;  
        const isLastCol = index === $('.product-table thead th').length - 1;
        const isFirstCol = index === 0;
        console.log('isFirstCol....',isFirstCol);
        const colSlug = $(this).data('head_slug');


        // console.log('colSlug isssss : ',colSlug);
        let inputField = '';
    
        if (isDropdownCol) {
            inputField = `
                <select class="column-search" data-col="${index}" style="width:100%; padding:5px;">
                    <option value="">All</option>
                    <option value="Fixed">Fixed</option>
                    <option value="Variable">Variable</option>
                </select>
            `;
        } else if (isStatusCol) {
            inputField = `
                <div class="checkbox-group" data-col="${index}">
                    <p style="margin:0; font-size:12px;">Loading product statuses...</p>
                </div>`;
        } else {
            inputField = `<input type="text" class="column-search" data-col="${index}" 
                placeholder="Search..." style="width:100%; padding:5px;">`;
        }

        let iconHtml = '';
        if (index > 1 && !isLastCol) {
            iconHtml = `
                <i class="fa-solid fa-arrow-down col-icon-${index}"></i>
                <i class="dashicons dashicons-filter"></i>
            `;
        }
    
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

    const productFilterBoxStates = {};
    $('.product-table thead').on('click', '.filter-icon', function(e) {
        e.stopPropagation();
        const colIndex = $(this).data('col');
        const filterBox = $(`.product-table thead .filter-box[data-col="${colIndex}"]`);
        const isOpen = productFilterBoxStates[colIndex];
        // console.log('colIndex....',colIndex);
        if (colIndex === 1){
            return;
        }
        if (isOpen) {
            filterBox.hide().removeClass('active_filter');
            filterBox.parent().removeClass('active_filter_wrapper');
            productFilterBoxStates[colIndex] = false;
        } else {
            filterBox.show().addClass('active_filter');
            filterBox.parent().addClass('active_filter_wrapper');
            productFilterBoxStates[colIndex] = true;

            // AJAX for Brand Name column only
            if (colIndex === 5 && !filterBox.data('loaded')) {
                // console.log('in column 5');
                $.ajax({
                    url: bulkCreateProduct.ajaxurl,
                    method: 'GET',
                    dataType: 'json',
                    data: {
                    action: 'get_all_pro_status'
                    },
                    success: function(response) {
                        // console.log('response,....',response);
                        if (response && response.success) {
                            let checkboxes = '';
                            // Iterate over object keys and labels
                            // console.log('response.data,....',response.data);

                            $.each(response.data, function(key, label) {
                                if (key === 'publish') {
                                    label = 'Active';
                                }
                                if (key === 'draft') {
                                    label = 'Awaiting Approval';
                                }
                                if (key === 'wc-deactivated') {
                                    label = 'Deactivated';
                                    key = 'deactivated';
                                }
                                checkboxes += `
                                    <label style="display:block; margin-bottom:3px;">
                                        <input type="checkbox" name="p_status" class="col5-filter" value="${key}"> ${label}
                                    </label>
                                `;
                            });
                            filterBox.find('.checkbox-group').html(checkboxes);
                            filterBox.data('loaded', true);
                        }
                    },
                    error: function(err) {
                        console.error('Error fetching product status:', err);
                        filterBox.find('.checkbox-group').html('<p style="color:red;">Failed to load product status</p>');
                    }
                });
            }
        }
        // Prevent clicks inside filter box from closing
        filterBox.off('click').on('click', function(e) {
            e.stopPropagation();
        });
    });

    $('.product-table thead').on('keyup change', '.column-search', function () {
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
                    if (settings.nTable.id !== 'product-table') return true;
                
                    
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
    
            productTable.draw();
            return;
        }
        function stripTime(date) {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate());
        }


        const searchValue = this.value;
        // orderTable.draw();
        // console.log('searchValue',searchValue);
        // console.log('colIndex',colIndex);

        if (productTable) { // make sure it's initialized
            productTable.column(colIndex).search(searchValue).draw();
        }
    });


    //Review a product table JS of datatable
    // =============================================
    // Review Product Table - DataTable Init
    // =============================================
    var reviewProductTable;

    if ($('.review-product-table').length) {

        // Destroy any existing DataTable instance first
        if ($.fn.DataTable.isDataTable('.review-product-table')) {
            $('.review-product-table').DataTable().destroy();
        }

        reviewProductTable = $('.review-product-table').DataTable({
            dom: '<"top">rt<"bottom"lip>',
            paging: true,
            searching: true,
            ordering: true,
            responsive: true,
            scrollX: false,
            pageLength: 25,
            lengthMenu: [5, 25, 50, 100],
            pagingType: "full_numbers",
            language: {
                search: "",
                searchPlaceholder: "Search products...",
                paginate: {
                    previous: "‹",
                    next: "›",
                    first: "«",
                    last: "»"
                },
                emptyTable: "No products awaiting publication."  // ← clean empty message
            },
            columnDefs: [
                { orderable: false, targets: [0, 1, 6] },
                { searchable: false, targets: [0, 1, 6] }
            ],
            order: [],
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
            }
        });

        // =============================================
        // Review Product Table - Column Filters
        // =============================================
        $('.review-product-table thead th').each(function (index) {
            const colText = $(this).text().trim();
            const isDropdownCol = index === 3; // Denomination Type
            const isStatusCol   = index === 5; // Status
            const isLastCol     = index === $('.review-product-table thead th').length - 1;
            const colSlug       = $(this).data('head_slug');

            let inputField = '';

            if (isDropdownCol) {
                inputField = `
                    <select class="review-column-search" data-col="${index}" style="width:100%; padding:5px;">
                        <option value="">All</option>
                        <option value="Fixed">Fixed</option>
                        <option value="Variable">Variable</option>
                    </select>
                `;
            } else if (isStatusCol) {
                inputField = `
                    <div class="review-checkbox-group" data-col="${index}">
                        <label style="display:block; margin-bottom:3px;">
                            <input type="checkbox" name="review_p_status" class="review-col5-filter" value="draft"> Awaiting Approval
                        </label>
                    </div>
                `;
            } else {
                inputField = `<input type="text" class="review-column-search" data-col="${index}" 
                    placeholder="Search..." style="width:100%; padding:5px;">`;
            }

            let iconHtml = '';
            if (index > 1 && !isLastCol) {
                iconHtml = `
                    <i class="fa-solid fa-arrow-down col-icon-${index}"></i>
                    <i class="dashicons dashicons-filter"></i>
                `;
            }

            $(this).html(`
                ${colText}
                ${iconHtml ? `<span class="review-filter-icon" data-col="${index}" style="cursor:pointer; width:16px; height:16px;">${iconHtml}</span>` : ''}
                ${inputField ? `
                    <div class="review-filter-box" data-head_slug="${colSlug}" data-col="${index}" 
                        style="display:none; background:white; border:1px solid #ccc; padding:5px; z-index:999;">
                        ${inputField}
                    </div>` : ''}
            `);
        });

        // Filter box toggle
        const reviewFilterBoxStates = {};
        $('.review-product-table thead').on('click', '.review-filter-icon', function (e) {
            e.stopPropagation();
            const colIndex = $(this).data('col');
            if (colIndex === 1) return;

            const filterBox = $(`.review-product-table thead .review-filter-box[data-col="${colIndex}"]`);
            const isOpen = reviewFilterBoxStates[colIndex];

            if (isOpen) {
                filterBox.hide().removeClass('active_filter');
                filterBox.parent().removeClass('active_filter_wrapper');
                reviewFilterBoxStates[colIndex] = false;
            } else {
                filterBox.show().addClass('active_filter');
                filterBox.parent().addClass('active_filter_wrapper');
                reviewFilterBoxStates[colIndex] = true;
            }

            filterBox.off('click').on('click', function (e) {
                e.stopPropagation();
            });
        });

        // Text/select search
        $('.review-product-table thead').on('keyup change', '.review-column-search', function () {
            const colIndex = $(this).data('col');
            reviewProductTable.column(colIndex).search($(this).val()).draw();
        });

        // Status checkbox filter
        $('.review-product-table thead').on('change', '.review-col5-filter', function () {
            const checked = [];
            $('.review-col5-filter:checked').each(function () {
                checked.push($(this).val());
            });

            if (checked.length === 0) {
                reviewProductTable.column(5).search('').draw();
            } else {
                // Use regex OR match for multiple checked statuses
                reviewProductTable.column(5).search(checked.join('|'), true, false).draw();
            }
        });

        // Close filter boxes on outside click
        $(document).on('click', function () {
            $('.review-filter-box').hide().removeClass('active_filter');
            $('.review-product-table thead th').removeClass('active_filter_wrapper');
            Object.keys(reviewFilterBoxStates).forEach(k => reviewFilterBoxStates[k] = false);
        });
    }

    // Remove any previous custom filters first
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(fn => fn.name !== 'statusFilter');

    function statusFilter(settings, data, dataIndex) {
        if (settings.nTable !== productTable.table().node()) return true; // only this table

        let selectedProductStatus = $('.col5-filter:checked').map(function() {
            return $(this).val(); // get the checkbox value (publish, draft, etc.)
        }).get();

        if (!selectedProductStatus.length) return true; // no filter selected

        let rowNode = productTable.row(dataIndex).node();
        let rowStatus = $(rowNode).find('td.status').data('status'); // read real status

        // console.log(selectedProductStatus);
        return selectedProductStatus.includes(rowStatus);
    }
    // Give a name property so we can remove it later if needed
    statusFilter.name = 'statusFilter';
    $.fn.dataTable.ext.search.push(statusFilter);

    // Trigger redraw on checkbox change
    $('.product-table thead').on('change', '.col5-filter', function() {
        productTable.draw();
    });

    // Filter code END


    $('.search-input').on('keyup', function () {
        var value = $(this).val().toLowerCase();
        let visibleCount = 0;
    
        // Filter DataTable
        if ($('.product-table').length > 0) {
            $('.product-table').DataTable().search(value).draw();
        }
    
        // Filter Thumbnail Grid
        $('#thumbnail-grid .thumbnail-item').each(function () {
            var title = $(this).data('title')?.toLowerCase() || '';
            if (title.includes(value)) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });
    
        // Show/hide "No Results"
        if (visibleCount === 0) {
            if ($('#thumbnail-grid .no-results').length === 0) {
                $('#thumbnail-grid').append('<div class="no-results" style="text-align:center; width:100%; padding:20px;">No products found.</div>');
            }
            $('#thumbnail-pagination').hide(); // 👈 hide pagination if nothing is visible
        } else {
            $('#thumbnail-grid .no-results').remove();
            $('#thumbnail-pagination').show(); // 👈 show pagination if items are visible
        }
    });
    
    
    
    // Select/deselect all checkboxes when the header checkbox is clicked
    $('th input[type="checkbox"]').on('click', function () {
        var isChecked = $(this).prop('checked'); // Get the state of the header checkbox
        $('.product-table tbody input[type="checkbox"]').prop('checked', isChecked); // Set the state of all row checkboxes
    });

    // Ensure the header checkbox reflects the state of the row checkboxes
    $('.product-table tbody').on('change', 'input[type="checkbox"]', function () {
        var totalCheckboxes = $('.product-table tbody input[type="checkbox"]').length;
        var checkedCheckboxes = $('.product-table tbody input[type="checkbox"]:checked').length;

        // If all checkboxes are checked, check the header checkbox, otherwise uncheck it
        $('th input[type="checkbox"]').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
    // Open media uploader
    $('.voucher_category_upload').click(function (e) {
        e.preventDefault();
        var mediaUploader = wp.media({
            title: 'Choose an Image',
            button: { text: 'Use this Image' },
            multiple: false
        });

        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#voucher_category_image').val(attachment.id);
            $('#voucher_category_image_preview').html('<img src="' + attachment.url + '" style="max-width:100px; height:auto;">');
        });

        mediaUploader.open();
    });

    // Remove the image
    $('.voucher_category_remove').click(function (e) {
        e.preventDefault();
        $('#voucher_category_image').val('');
        $('#voucher_category_image_preview').html('');
    });

    // export products CSV code ----------------- START
    // function triggerDownload(url, filename) {
    //     const link = document.createElement('a');
    //     link.href = url;
    //     link.download = filename || 'products_export.csv'; // force download
    //     document.body.appendChild(link);
    //     link.click();
    //     document.body.removeChild(link);
    // }
    
    // $('.export-products-csv').on('click', function () {
    //     var button = $(this);
    //     button.prop('disabled', true);
    //     button.text('Exporting...');
    //     // AJAX request to handle export
    //     $.ajax({
    //         url: bulkCreateProduct.ajaxurl,
    //         type: 'POST',
    //         data: {
    //             action: 'export_products_csv',
    //             chunk_size: 100,
    //             offset: 0,
    //             _ajax_nonce: bulkCreateProduct.nonce,
    //         },
    //         success: function (response) {
    //             if (response.success) {
    //                 // Handle chunk export and call next chunk
    //                 if (response.data.offset < response.data.total_products) {
    //                     exportNextChunk(response.data.offset);
    //                 } else {
    //                     // Final export complete, now trigger download
    //                     triggerDownload(response.data.file_url, 'products_export.csv');
    //                     button.prop('disabled', false);
    //                     button.text('Export Completed');

    //                     setTimeout(function () {
    //                         button.text('Export Products');
    //                     }, 2000);
    //                 }
    //             } else {
    //                 alert('Error during export: ' + response.data.message);
    //                 button.prop('disabled', false);
    //                 button.text('Export Products');
    //             }
    //         }
    //     });

    //     // Function to export the next chunk
    //     function exportNextChunk(offset) {
    //         $.ajax({
    //             url: bulkCreateProduct.ajaxurl,
    //             type: 'POST',
    //             data: {
    //                 action: 'export_products_csv',
    //                 chunk_size: 100,
    //                 offset: offset,
    //                 _ajax_nonce: bulkCreateProduct.nonce,
    //             },
    //             success: function (response) {
    //                 if (response.success) {
    //                     if (response.data.offset < response.data.total_products) {
    //                         exportNextChunk(response.data.offset);
    //                     } else {
    //                         triggerDownload(response.data.file_url, 'products_export.csv');
    //                         button.prop('disabled', false);
    //                         button.text('Export Completed');

    //                         setTimeout(function () {
    //                             button.text('Export Products');
    //                         }, 2000);
    //                     }
    //                 } else {
    //                     alert('Error during export: ' + response.data.message);
    //                     button.prop('disabled', false);
    //                     button.text('Export Products');
    //                 }
    //             }
    //         });
    //     }
    // });

    // export products CSV code ----------------- END

    // Hide and show on click of bulk create
    $(document).on("click", "#bulk-add-products", function (e) {
        e.preventDefault();
        const currentUrl = new URL(window.location);
        const searchParams = currentUrl.searchParams;
    
        if (!searchParams.has('bulk-create-products')) {
            searchParams.delete('bulk-edit-products');
            searchParams.set('bulk-create-products', 'true');
            window.location.replace(currentUrl.toString());
        }
    });
    
    $(document).on("click", "#bulk-edit-products", function (e) {
        e.preventDefault();
        const currentUrl = new URL(window.location);
        const searchParams = currentUrl.searchParams;
    
        if (!searchParams.has('bulk-edit-products')) {
            searchParams.delete('bulk-create-products');
            searchParams.set('bulk-edit-products', 'true');
            window.location.replace(currentUrl.toString());
        }
    });

    $("#download-product-template").on("click", function (e) {
        e.preventDefault();
        const csvUrl = (typeof bulkCreateProduct !== 'undefined' && bulkCreateProduct.siteUrl) ? bulkCreateProduct.siteUrl.replace(/\/$/, '') + '/wp-content/uploads/2025/08/Bulk-Create-Products.csv' : '/wp-content/uploads/2025/08/Bulk-Create-Products.csv';
        const downloadLink = $("<a>")
            .attr("href", csvUrl)
            .attr("download", csvUrl.split('/').pop())
            .appendTo("body");

        downloadLink[0].click();
        downloadLink.remove();
    });


    // Upload csv file code Start =======================

    let originalCsvData = { headers: [], data: [] };
    let templateHeaders = [];
    let headerMapping = {};

    
    //-----------

    const uploadArea = $('#upload-area');
    const fileInput = $('#csv-file-input1');

    uploadArea.on('click', function() {
        fileInput.trigger('click');
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
    $('#upload-product-csv-btn').on('click', function () {
        $('#file-upload-modal').modal('show');
    });

    $('#submit-file-upload').on('click', function () {
        const file = $('#csv-file-input1')[0].files[0];
        if (!file) {
            $('#file-error-msg').text('⚠️ Please select a CSV file.');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'custom_upload_csv_file');

        $('#upload-progress').show();
        $('#progress-bar').css('width', '0%').text('0%');

        $.ajax({
            url: bulkCreateProduct.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function () {
                let xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (event) {
                    if (event.lengthComputable) {
                        let percentComplete = Math.round((event.loaded / event.total) * 100);
                        $('#progress-bar').css('width', percentComplete + '%').text(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                if (response.success) {
                    originalCsvData = response.data.csv_data;
                    templateHeaders = response.data.template_headers;
                    headerMapping = response.data.header_mapping;

                    const mandatoryHeaders = [
                        'Gift Card Titsle', 'Parent/Child SKU', 'Linked to Parent', 'SKU',
                        'Brand', 'Supplier', 'Gift Card Expiry Type', 'Gift Card Activation Type',
                        'Denomination Type', 'Cost Price', 'Discounted Price', 'Stock Levels', 'Transaction Limit'
                    ];
                    let allHeadersMatched = mandatoryHeaders.every(header => headerMapping[header]);

                    if (allHeadersMatched) {
                        showFinalPreview();
                    } else {
                        showCsvPreview();
                    }
                } else {
                    $('#file-error-msg').text(response.data.message);
                }
                $('#file-upload-modal').modal('hide');
            },
            complete: function () {
                $('#upload-progress').hide();
            },
            error: function () {
                $('#file-error-msg').text('⚠️ An error occurred during upload.');
            }
        });
    });
    function removeUploadBlock() {
        // Reset file input
        const fileInput = document.getElementById('csv-file-input1');
        fileInput.value = '';
    
        // Hide filename display
        document.getElementById('file-name-display').style.display = 'none';
    
        // Reset progress bar
        const progressBar = document.getElementById('progress-bar');
        const uploadProgress = document.getElementById('upload-progress');
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        uploadProgress.style.display = 'none';
    
        // Optionally reset error message
        document.getElementById('file-error-msg').style.display = 'none';
    }
    $('#remove-selected-file').on('click', function () {
        removeUploadBlock();
        // console.log('jkakdjadasuda--');
    });
    function showCsvPreview() {
        let previewHtml = ' <div id="row-count" class="row-summary" style="text-align: right; font-weight: bold; margin-bottom: 10px;"></div><div class="table-csv-preview-wrapper"><div class="csvPreviewTable-table"><table id="csvPreviewTable" class="table table-bordered display"><thead><tr>';

        // ✅ Headers show karega
        originalCsvData.headers.forEach(header => {
            previewHtml += `<th>${header}</th>`;
        });
        previewHtml += '</tr></thead><tbody>';

        // ✅ Data show karega
        originalCsvData.data.forEach(row => {
            previewHtml += '<tr>';
            row.forEach(cell => {
                previewHtml += `<td>${cell}</td>`;
            });
            previewHtml += '</tr>';
        });

        previewHtml += '</tbody></table></div></div>';
        $('#csv-preview').html(previewHtml).removeClass('d-none');
        $('.pre-preview-section').show();
        $('#match-headers-btn').show();
        $('.bulk-add-container').hide();

        // ✅ Initialize DataTable Start Here
        $('#csvPreviewTable').DataTable({
            dom: 'rt<"bottom"lp><"clear">',
            paging: true,
            pagingType: "full_numbers",
            pageLength: 10,
            scrollX: true,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            responsive: true,
            language: {
                search: "",
                searchPlaceholder: "Search...",
                lengthMenu: "Show _MENU_ entries",
                zeroRecords: "No matching records found",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)",
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
                            $(this).hide(); // Hide middle pages
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
        

        // ✅ Initialize DataTable End Here

        const totalRows = originalCsvData.data.length;
        $('#row-count').html(`
            <div class='validate-rows-message left-align'>
                ✅ ${totalRows} New products have been uploaded. Please review.
            </div>
        `);
    }

    $('#match-headers-btn').on('click', function () {
        showMappingInterface();
    });

    function showMappingInterface() {
        let mappingHtml = '';

        templateHeaders.forEach(header => {
            const selectedHeader = headerMapping[header] || '';
            const isMandatory = ['Gift Card Title', 'SKU', 'Parent/Child SKU'].includes(header);

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

        $('#mapping-interface').html(mappingHtml);
        $('#mapping-modal').modal('show');
        updateDisabledOptions();

    }

    $('#mapping-interface').on('change', '.mapping-select', function () {
        const templateHeader = $(this).data('template');
        const selectedHeader = $(this).val();
        headerMapping[templateHeader] = selectedHeader;

        // Hide error message if a valid selection is made
        if (selectedHeader) {
            $(this).siblings('.error-msg').hide();
        }
        updateDisabledOptions();

    });

    function updateDisabledOptions() {
        let selectedValues = [];

        // Get all selected values
        $('.mapping-select').each(function () {
            let selectedValue = $(this).val();
            if (selectedValue) {
                selectedValues.push(selectedValue);
            }
        });

        // Loop through all dropdowns and disable the selected values
        $('.mapping-select').each(function () {
            let currentDropdown = $(this);
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
    $('#apply-mapping').on('click', function () {
        let missingMandatoryFields = [];

        const mandatoryHeaders = ['Gift Card Title', 'SKU', 'Parent/Child SKU'];

        templateHeaders.forEach(header => {
            const selectedHeader = headerMapping[header];

            if (mandatoryHeaders.includes(header)) {
                if (!selectedHeader || selectedHeader.trim() === '') {
                    missingMandatoryFields.push(header);
                }
            }
            // No validation for optional fields
        });

        // Hide all previous error messages
        $('.mapping-select').siblings('.error-msg').hide();

        // Show error messages only for missing mandatory fields
        $('.mapping-select').each(function () {
            const templateHeader = $(this).data('template');
            const selectedHeader = $(this).val();

            if (missingMandatoryFields.includes(templateHeader)) {
                $(this).siblings('.error-msg').text("⚠️ Please select header for mandatory fields").show();
            }
        });

        // Show general error message at the top
        if (missingMandatoryFields.length > 0) {
            let errorMessage = `<p><strong>Missing Required Fields:</strong> ${missingMandatoryFields.join(', ')}</p>`;

            if ($('#mapping-error-msg').length === 0) {
                $('#modal-content').prepend(`<div id="mapping-error-msg" class="alert alert-danger">${errorMessage}</div>`);
            } else {
                $('#mapping-error-msg').html(errorMessage).show();
            }

            return;
        } else {
            $('#mapping-error-msg').hide();
        }

        // ✅ All good – apply mapping
        applyMappingAndPreview(headerMapping);
        $('#mapping-modal').modal('hide');
        $('#csv-preview').hide();
        $('.pre-preview-section').hide();
    });


    $('#bulk-upload-preview-btn').prop('disabled', true); // Disable initially

    function applyMappingAndPreview(mapping) {
        const selectedHeaders = Object.keys(mapping).filter(templateHeader => mapping[templateHeader] !== '');

        // ✅ Save template headers instead of actual CSV headers
        originalCsvData.filteredHeaders = selectedHeaders;

        const updatedData = originalCsvData.data.map(row => {
            return selectedHeaders.map(templateHeader => {
                const originalHeader = mapping[templateHeader];
                const index = originalCsvData.headers.indexOf(originalHeader);
                return index !== -1 ? row[index] : '';
            });
        });
        const mandatoryFields = [
            'NO', 'Gift Card Title', 'Parent/Child SKU', 'Linked to Parent', 'Parent SKU', 'SKU',
            'Supplier SKU', 'Brand', 'Supplier', 'Short Description', 'Long Description',
            'Terms & Conditions', 'How to Use', 'Expiry Date/Time', 'Gift Card Expiry Type',
            'Gift Card Expiry Date', 'Gift Card Expiry Period', 'Gift Card Activation Type',
            'Gift Card Activation Date', 'Gift Card Activation Period', 'Period Type',
            'Brand Image', 'Card Image 1', 'Card Image 2', 'Card Image 3', 'Card Image 4',
            'Card Image 5', 'Denomination Type', 'Cost Price', 'Supplier Fullfillment Price',
            'GST', 'GC + Fullfillment', 'Preset Delivery Class', 'Delivery Cost', 'Discounted',
            'Discounted Price', 'Discounted Valid From', 'Discounted Valid To',
            'Icons', 'Tags', 'Categories', 'Feature Placement', 'Extra Header',
            'Add Stock Levels', 'Stock Levels', 'Transaction Limit', 'QTY per Transaction',
            'Total Value', 'Available for all users', 'Always On', 'Onsite From', 'Onsite To'
        ];
        originalCsvData.data = updatedData;
        const fieldValidations = {
            'Gift Card Title': /^[a-zA-Z0-9]+$/,
            'Parent/Child SKU': /^[a-zA-Z0-9]+$/,
            'Parent SKU': /^[a-zA-Z0-9_]+$/,
            'SKU': /^[a-zA-Z0-9]+$/,
            'Supplier SKU': /^[a-zA-Z0-9]+$/,
            'Linked to Parent': /^[a-zA-Z0-9\-_/]+$/,
            'Terms & Conditions': /^[\w\s.,;:'"!?()&%-]+$/,
            'How to Use': /^[\w\s.,;:'"!?()&%-]+$/,
            'Short Description': /^[\w\s.,;:'"!?()&%-]+$/,
            'Long Description': /^[\w\s.,;:'"!?()&%-]+$/,
            'Supplier': /^[a-zA-Z0-9 &()\-]+$/,
            'Brand': /^[a-zA-Z0-9 &(),\-]+$/,
            'Expiry Date/Time': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Gift Card Expiry Period': /^[0-9]+$/,
            'Gift Card Activation Period': /^[0-9]+$/,
            'Cost Price': /^[0-9]+(\.[0-9]+)?$/,
            'Supplier Fullfillment Price': /^[0-9]+(\.[0-9]+)?$/,
            'GST': /^[0-9]+(\.[0-9]+)?$/,
            'GC + Fullfillment': /^[0-9]+(\.[0-9]+)?$/,
            'Delivery Cost': /^[0-9]+(\.[0-9]+)?$/,
            'Discounted': /^[0-9]+(\.[0-9]+)?$/,
            'Discounted Price': /^[0-9]+(\.[0-9]+)?$/,
            'Add Stock Levels': /^[a-zA-Z ]+$/,
            'Stock Levels': /^[0-9]+$/,
            'QTY per Transaction': /^[a-zA-Z0-9 ]+$/,
            'Total Value': /^[0-9]+(\.[0-9]+)?$/,
            'Gift Card Expiry Type': /^[a-zA-Z ]+$/,
            'Gift Card Expiry Date': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Gift Card Activation Date': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Discounted Valid From': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Discounted Valid To': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Onsite From': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Onsite To': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Brand Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 1': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 2': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 3': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 4': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 5': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Preset Delivery Class': /^[a-zA-Z0-9 ]+$/,
            'Icons': /^[a-zA-Z0-9 &(),\-]+$/,
            'Tags': /^[a-zA-Z0-9 &(),\-]+$/,
            'Categories': /^[a-zA-Z0-9 &(),\-]+$/,
            'Feature Placement': /^[a-zA-Z0-9 ]+$/,
            'Extra Header': /^[a-zA-Z0-9 ]+$/,
            'Transaction Limit': /^[a-zA-Z ]+$/,
            'Available for all users': /^[a-zA-Z ]+$/,
            'Always On': /^[a-zA-Z ]+$/,
            'Period Type': /^[a-zA-Z ]+$/,
            'Denomination Type': /^[a-zA-Z ]+$/,
            'Gift Card Activation Type': /^[a-zA-Z ]+$/,
        };

        // Detect errors (only mandatory)
        errorRowIndexes = [];
        updatedData.forEach((row, rowIndex) => {
            const hasError = row.some((cell, index) => {
                const header = selectedHeaders[index];
                const isMandatory = mandatoryFields.includes(header);
                const regex = fieldValidations[header];
                // console.log('header -->>>>', header);
                // console.log('isMandatory -->>>>', isMandatory);
                // console.log('regex -->>>>', regex);

                // const isDateField = dateFields.includes(header);

                if (isMandatory && (!cell || cell.trim() === '')) {
                    return true;
                }

                if (cell.trim() !== '' && regex && !regex.test(cell.trim())) {
                    return true;
                }

                return false;
            });


            if (hasError) errorRowIndexes.push(rowIndex);
        });

        const totalRows = updatedData.length;
        const errorRows = errorRowIndexes.length;
        const validRows = totalRows - errorRows;

        if (errorRows === 0) {
            showFinalPreview();
        } else {
            $('#preview-section').show();
            $('#row-count-summary').html(`
                <div class='validate-rows-message custom-message'>
                ✅ ${validRows} Lines have been uploaded successfully without errors. <br> ❌ We found errors on <strong> ${errorRows} lines </strong>
            </div>`);
            renderCsvPreview();
        }

        $('#mapping-modal').modal('hide');
    }



    function renderCsvPreview() {
        const headersHtml = `<tr><th>Row</th>${originalCsvData.filteredHeaders.map(h => `<th>${h}</th>`).join('')}</tr>`;
        $('#csv-preview-table thead').html(headersHtml);

        let rowsHtml = '';
        const fieldValidations = {
            'Gift Card Title': /^[a-zA-Z0-9]+$/,
            'Parent/Child SKU': /^[a-zA-Z0-9]+$/,
            'Parent SKU': /^[a-zA-Z0-9_]+$/,
            'SKU': /^[a-zA-Z0-9]+$/,
            'Supplier SKU': /^[a-zA-Z0-9]+$/,
            'Linked to Parent': /^[a-zA-Z0-9\-_/]+$/,
            'Terms & Conditions': /^[\w\s.,;:'"!?()&%-]+$/,
            'How to Use': /^[\w\s.,;:'"!?()&%-]+$/,
            'Short Description': /^[\w\s.,;:'"!?()&%-]+$/,
            'Long Description': /^[\w\s.,;:'"!?()&%-]+$/,
            'Supplier': /^[a-zA-Z0-9 &()\-]+$/,
            'Brand': /^[a-zA-Z0-9 &(),\-]+$/,
            'Expiry Date/Time': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Gift Card Expiry Period': /^[0-9]+$/,
            'Gift Card Activation Period': /^[0-9]+$/,
            'Cost Price': /^[0-9]+(\.[0-9]+)?$/,
            'Supplier Fullfillment Price': /^[0-9]+(\.[0-9]+)?$/,
            'GST': /^[0-9]+(\.[0-9]+)?$/,
            'GC + Fullfillment': /^[0-9]+(\.[0-9]+)?$/,
            'Delivery Cost': /^[0-9]+(\.[0-9]+)?$/,
            'Discounted': /^[0-9]+(\.[0-9]+)?$/,
            'Discounted Price': /^[0-9]+(\.[0-9]+)?$/,
            'Add Stock Levels': /^[a-zA-Z ]+$/,
            'Stock Levels': /^[0-9]+$/,
            'QTY per Transaction': /^[a-zA-Z0-9 ]+$/,
            'Total Value': /^[0-9]+(\.[0-9]+)?$/,
            'Gift Card Expiry Type': /^[a-zA-Z ]+$/,
            'Gift Card Expiry Date': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Gift Card Activation Date': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Discounted Valid From': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Discounted Valid To': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Onsite From': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Onsite To': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
            'Brand Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 1': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 2': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 3': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 4': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Card Image 5': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
            'Preset Delivery Class': /^[a-zA-Z0-9 ]+$/,
            'Icons': /^[a-zA-Z0-9 &(),\-]+$/,
            'Tags': /^[a-zA-Z0-9 &(),\-]+$/,
            'Categories': /^[a-zA-Z0-9 &(),\-]+$/,
            'Feature Placement': /^[a-zA-Z0-9 ]+$/,
            'Extra Header': /^[a-zA-Z0-9 ]+$/,
            'Transaction Limit': /^[a-zA-Z ]+$/,
            'Available for all users': /^[a-zA-Z ]+$/,
            'Always On': /^[a-zA-Z ]+$/,
            'Period Type': /^[a-zA-Z ]+$/,
            'Denomination Type': /^[a-zA-Z ]+$/,
            'Gift Card Activation Type': /^[a-zA-Z ]+$/,
        };

        originalCsvData.data.forEach((row, rowIndex) => {
            const hasError = errorRowIndexes.includes(rowIndex);
            const rowHtml = row.map((cell, cellIndex) => {
                const isMandatory = [
                    'NO', 'Gift Card Title', 'Parent/Child SKU', 'Linked to Parent', 'Parent SKU', 'SKU',
                    'Supplier SKU', 'Brand', 'Supplier', 'Short Description', 'Long Description',
                    'Terms & Conditions', 'How to Use', 'Expiry Date/Time', 'Gift Card Expiry Type',
                    'Gift Card Expiry Date', 'Gift Card Expiry Period', 'Gift Card Activation Type',
                    'Gift Card Activation Date', 'Gift Card Activation Period', 'Period Type',
                    'Brand Image', 'Card Image 1', 'Card Image 2', 'Card Image 3', 'Card Image 4',
                    'Card Image 5', 'Denomination Type', 'Cost Price', 'Supplier Fullfillment Price',
                    'GST', 'GC + Fullfillment', 'Preset Delivery Class', 'Delivery Cost', 'Discounted',
                    'Discounted Price', 'Discounted Valid From', 'Discounted Valid To',
                    'Icons', 'Tags', 'Categories', 'Feature Placement', 'Extra Header',
                    'Add Stock Levels', 'Stock Levels', 'Transaction Limit', 'QTY per Transaction',
                    'Total Value', 'Available for all users', 'Always On', 'Onsite From', 'Onsite To'
                ].includes(originalCsvData.filteredHeaders[cellIndex]);
                const header = originalCsvData.filteredHeaders[cellIndex];
                let isError = false;
                const regex = fieldValidations[header];
                if (isMandatory && (!cell || cell.trim() === '')) {
                    isError = true;
                } else if (cell.trim() !== '' && regex && !regex.test(cell.trim())) {
                    isError = true;
                }
                return `<td contenteditable="false" class="${isError ? 'empty-field' : ''}">${cell}</td>`;
            }).join('');

            rowsHtml += `<tr data-original-index="${rowIndex}" ${hasError ? '' : 'style="display: none;"'}>
                <td>${rowIndex + 1}</td>
                ${rowHtml}
            </tr>`;
        });

        $('#csv-preview-table tbody').html(rowsHtml);
        $('#csv-preview-table').DataTable({
            paging: true,
            ordering: true,
            scrollX: true,
            pageLength: 10,
            responsive: true,
            dom: 'tip'
        });
        $('#match-headers-btn').hide();


        $('.empty-field').on('input', function () {
            if ($(this).text().trim() !== '') {
                $(this).removeClass('empty-field').css({ 'background-color': '', 'outline': '' });
            }
            checkForRemainingErrors();
        });
    }


    function showFinalPreview() {
        if (!originalCsvData.headers || originalCsvData.headers.length === 0) {
            alert('⚠️ CSV headers were missing or invalid. Using default headers instead.');
        }

        const headers = originalCsvData.filteredHeaders || []; // fallback-safe
        const headersHtml = headers.map(h => `<th>${h}</th>`).join('');
        $('#final-preview-table thead').html(`<tr><th>Row</th>${headersHtml}</tr>`);

        const rowsHtml = originalCsvData.data.map((row, rowIndex) => {
            return `<tr><td>${rowIndex + 1}</td>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`;
        }).join('');

        $('#final-preview-table tbody').html(rowsHtml);

        $('#preview-section').hide();
        $('#file-upload-section').hide();
        $('#mapping-section').hide();
        $('#match-headers-btn').hide();
        $('.bulk-add-container').hide();
        $('#final-preview-section').show();
        // ✅ Initialize DataTable


        //-----------    END

        $('#final-preview-table').DataTable({
            dom: 'rt<"bottom"lp><"clear">',
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            paging: true,
            pagingType: "full_numbers",
            responsive: true,
            scrollX: true,
            language: {
                search: "",
                searchPlaceholder: "Search...",
                lengthMenu: "Show _MENU_ entries",
                zeroRecords: "No matching records found",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)",
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
        
                // Remove old ellipsis
                pagination.find('.ellipsis').remove();
        
                if (totalPages > 7) {
                    pagination.find('.paginate_button').each(function () {
                        var pageNum = parseInt($(this).text(), 10);
                        if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                            $(this).hide(); // Hide middle pages except current
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
        //-----------  END
    }



    // Function to check if there are still errors
    function checkForRemainingErrors() {
        let hasErrors = $('.empty-field').length > 0;

        if (hasErrors) {
            $('#bulk-upload-preview-btn').prop('disabled', true);
        } else {
            $('#bulk-upload-preview-btn').prop('disabled', false);
        }
    }

    $('#bulk-upload-preview-btn').on('click', function () {
        // ✅ Update originalCsvData with edited values
        $('#csv-preview-table tbody tr').each(function (rowIndex) {
            $(this).find('td').each(function (cellIndex) {
                if (cellIndex > 0) { // Skip first column (Row Number)
                    originalCsvData.data[rowIndex][cellIndex - 1] = $(this).text().trim();
                }
            });
        });

        // ✅ Copy headers from existing preview
        let headersHtml = $('#csv-preview-table thead').html();
        $('#final-preview-table thead').html(headersHtml);

        // ✅ Restore All Rows (Hidden + Error Rows) with Updated Data
        let rowsHtml = '';
        originalCsvData.data.forEach((row, rowIndex) => {
            rowsHtml += `<tr>
                <td>${rowIndex + 1}</td>
                ${row.map(cell => `<td>${cell || ''}</td>`).join('')}
            </tr>`;
        });

        // ✅ Set final preview table
        $('#final-preview-table tbody').html(rowsHtml);

        // ✅ Hide the current preview section
        $('#preview-section').hide();

        // ✅ Show the final preview section
        $('#final-preview-section').show();
    });

    $('#final-submit-btn').on('click', function () {
        alert('Final Data Submitted!'); // 🚀 Yaha API call ya backend save ka code likho
    });



    $('#edit-errors').on('click', function () {
        // Make all error cells editable and highlight them
        $('.empty-field').each(function () {
            $(this).attr('contenteditable', 'true').css({
                'background-color': '#ffcccc',
                'outline': '1px solid red'
            });
        });

        // Allow tabbing through error fields only
        $('.empty-field').on('keydown', function (e) {
            if (e.key === "Tab") {
                e.preventDefault();
                let next = $('.empty-field').eq($('.empty-field').index(this) + 1);
                if (next.length) next.focus();
            }
        });

        // Validate only on blur — keep cell always editable
        $('.empty-field').on('blur', function () {
            const $cell = $(this);
            const text = $cell.text().trim();
            const header = originalCsvData.filteredHeaders[$cell.index() - 1]; // Adjust index if needed

            const rules = {
                'Gift Card Title': /^[a-zA-Z0-9]+$/,
                'Parent/Child SKU': /^[a-zA-Z0-9]+$/,
                'Parent SKU': /^[a-zA-Z0-9_]+$/,
                'SKU': /^[a-zA-Z0-9]+$/,
                'Supplier SKU': /^[a-zA-Z0-9]+$/,
                'Linked to Parent': /^[a-zA-Z0-9\-_/]+$/,
                'Terms & Conditions': /^[\w\s.,;:'"!?()&%-]+$/,
                'How to Use': /^[\w\s.,;:'"!?()&%-]+$/,
                'Short Description': /^[\w\s.,;:'"!?()&%-]+$/,
                'Long Description': /^[\w\s.,;:'"!?()&%-]+$/,
                'Supplier': /^[a-zA-Z0-9 &()\-]+$/,
                'Brand': /^[a-zA-Z0-9 &(),\-]+$/,
                'Expiry Date/Time': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
                'Gift Card Expiry Period': /^[0-9]+$/,
                'Gift Card Activation Period': /^[0-9]+$/,
                'Cost Price': /^[0-9]+(\.[0-9]+)?$/,
                'Supplier Fullfillment Price': /^[0-9]+(\.[0-9]+)?$/,
                'GST': /^[0-9]+(\.[0-9]+)?$/,
                'GC + Fullfillment': /^[0-9]+(\.[0-9]+)?$/,
                'Delivery Cost': /^[0-9]+(\.[0-9]+)?$/,
                'Discounted': /^[0-9]+(\.[0-9]+)?$/,
                'Discounted Price': /^[0-9]+(\.[0-9]+)?$/,
                'Add Stock Levels': /^[a-zA-Z ]+$/,
                'Stock Levels': /^[0-9]+$/,
                'QTY per Transaction': /^[a-zA-Z0-9 ]+$/,
                'Total Value': /^[0-9]+(\.[0-9]+)?$/,
                'Gift Card Expiry Type': /^[a-zA-Z ]+$/,
                'Gift Card Expiry Date': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
                'Gift Card Activation Date': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
                'Discounted Valid From': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
                'Discounted Valid To': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
                'Onsite From': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
                'Onsite To': /^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,
                'Brand Image': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
                'Card Image 1': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
                'Card Image 2': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
                'Card Image 3': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
                'Card Image 4': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
                'Card Image 5': /\.(jpeg|jpg|gif|png|webp|svg|bmp)$/i,
                'Preset Delivery Class': /^[a-zA-Z0-9 ]+$/,
                'Icons': /^[a-zA-Z0-9 &(),\-]+$/,
                'Tags': /^[a-zA-Z0-9 &(),\-]+$/,
                'Categories': /^[a-zA-Z0-9 &(),\-]+$/,
                'Feature Placement': /^[a-zA-Z0-9 ]+$/,
                'Extra Header': /^[a-zA-Z0-9 ]+$/,
                'Transaction Limit': /^[a-zA-Z ]+$/,
                'Available for all users': /^[a-zA-Z ]+$/,
                'Always On': /^[a-zA-Z ]+$/,
                'Period Type': /^[a-zA-Z ]+$/,
                'Denomination Type': /^[a-zA-Z ]+$/,
                'Gift Card Activation Type': /^[a-zA-Z ]+$/,
            };

            const regex = rules[header];

            if (regex) {
                if (text === '' || !regex.test(text)) {
                    $cell.addClass('empty-field').css({
                        'background-color': '#ffcccc',
                        'outline': '1px solid red'
                    });
                } else {
                    $cell.removeClass('empty-field').css({
                        'background-color': '',
                        'outline': ''
                    });
                }
            } else {
                if (text === '') {
                    $cell.addClass('empty-field').css({
                        'background-color': '#ffcccc',
                        'outline': '1px solid red'
                    });
                } else {
                    $cell.removeClass('empty-field').css({
                        'background-color': '',
                        'outline': ''
                    });
                }
            }

            checkForRemainingErrors();
        });
    });



    $('#remove-error-lines').on('click', function () {
        let removedCount = 0;
    
        $('#csv-preview-table tbody tr').each(function () {
            if ($(this).find('.empty-field').length > 0) {
                $(this).remove();
                removedCount++;
            }
        });
    
        if (removedCount > 0) {
            const message = `Please select a valid CSV file. ${removedCount} row(s) contain errors.`;
            $('#success-and-error-message')
                .html(`<span style="color: red;">${message}</span>`)
                .css('display', 'block');
    
            // Scroll to the message
            $('html, body').animate({
                scrollTop: $('#success-and-error-message').offset().top - 100
            }, 500);
        } else {
            $('#success-and-error-message')
                .html(`<span style="color: green;">No error rows found. CSV looks good!</span>`)
                .css('display', 'block');
    
            $('html, body').animate({
                scrollTop: $('#success-and-error-message').offset().top - 100
            }, 500);
        }
    });
    

    // 🔵 Download and Resubmit (CSV with Highlighted Errors)
    $('.bulk-create-products #download-resubmit').on('click', function () {
        let csvContent = "data:text/csv;charset=utf-8,";
        let rows = [];

        // Get headers
        let headers = [];
        $('#csv-preview-table thead th').each(function () {
            headers.push($(this).text());
        });
        rows.push(headers.join(',')); // Add headers to CSV

        // Get table data with error markers
        $('#csv-preview-table tbody tr').each(function () {
            let row = [];
            $(this).find('td').each(function () {
                let cellText = $(this).text().trim();
                if ($(this).hasClass('empty-field')) {
                    cellText = "**ERROR** " + cellText; // Mark errors in CSV
                }
                row.push(cellText);
            });
            rows.push(row.join(','));
        });

        csvContent += rows.join('\n'); // Combine all rows
        let encodedUri = encodeURI(csvContent);

        // Create a temporary download link
        let link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "error_highlighted_data.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    $('#final-create-btn').on('click', function () {
        const $btn = $(this);
        const $text = $btn.find('.btn-text');
        const $spinner = $btn.find('.spinner-border');
        const urlParams = new URLSearchParams(window.location.search);
        const isEditMode = urlParams.has('bulk-edit-products');
        const headers = originalCsvData.filteredHeaders;
        const dataToSend = originalCsvData.data.map(row => {
            const item = {};
            headers.forEach((header, index) => {
                item[header] = row[index];
            });
            return item;
        });

        // console.log(dataToSend);

        // Disable button and show spinner
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $text.text('Processing...');

        $.ajax({
            url: bulkCreateProduct.ajaxurl,
            type: 'POST',
            data: {
                action: 'create_giftcard_products',
                products: JSON.stringify(dataToSend),
                edit_mode: isEditMode ? 1 : 0,
                security: bulkCreateProduct.nonce,
            },
            success: function (response) {
                if (response.success) {
                    const skipped = response.data.skipped || [];
                    if (skipped.length > 0) {
                        console.warn('Skipped SKUs (already exist):', skipped);

                        const warningMessage = `
                            <span style="color: red;">
                                Some Products are skipped because they already exist:<br>
                                <strong>${skipped.join(', ')}</strong>
                            </span>
                        `;
                        $('#success-and-error-message').html(warningMessage).css('display', 'block');
                        $('html, body').animate({scrollTop: $('#success-and-error-message').offset().top - 100 }, 500);
                    }
                    // $('#success-and-error-message').html(html).css('display', 'block');
                    $('#success-message').text(response.data.message).show();
                    // setTimeout(function () {
                    //     location.reload();
                    // }, 3000);
                } else {
                    $('#success-message').text('Something went wrong!').css('color', 'red').show();
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', xhr.responseText);
                $('#success-message').text('AJAX error occurred. Check console for details.').css('color', 'red').show();
            },            
            complete: function () {
                // Re-enable button & hide spinner if needed before reload
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $text.text('Submit');
            }
        });
    });
});
document.addEventListener("DOMContentLoaded", function () {
 
    const url = window.location.href;

    const createStep = document.querySelector(".step-create-product");
    const editStep = document.querySelector(".step-edit-product");
    const editStepTitle = document.querySelector(".bulk-edit-title");
    const addStepTitle = document.querySelector(".bulk-add-title");


    // Hide both by default
    if (createStep) createStep.style.display = "none";
    if (editStep) editStep.style.display = "none";
    if (editStepTitle) editStepTitle.style.display = "none";
    if (addStepTitle) addStepTitle.style.display = "none";


    // Show based on URL
    if (url.includes("bulk-create-products=")) {
        if (createStep) createStep.style.display = "block";
        if (addStepTitle) addStepTitle.style.display = "block";
    
    } else if (url.includes("bulk-edit-products=")) {
        if (editStep) editStep.style.display = "block";
        if (editStepTitle) editStepTitle.style.display = "block";
    }
});