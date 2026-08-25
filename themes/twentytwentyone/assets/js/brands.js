

// Global click handler
/*jQuery(document).on('click', '.nav-view-all-brands', function (e) {
    e.preventDefault();
    window.location.href = '/brands-list/?openBrandModal=1';
});*/
jQuery(document).ready(function ($) {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'add') {
        const interval = setInterval(function() {
            if (jQuery('#create-new-brand').length) {
                jQuery('#create-new-brand').trigger('click');
                clearInterval(interval);
            }
        }, 200);
    }
    // console.log('I am inside the page load....');

    //----------------------------- 

    // const $listView = jQuery('#brand-list-view');
    // const $thumbnailView = jQuery('#brand-product-thumbnail-view');
    // const $listBtn = jQuery('#brand-list-view-btn');
    // const $thumbBtn = jQuery('#brand-thumbnail-view-btn');
    // const $grid = jQuery('#brand-thumbnail-grid');
    // const $pagination = jQuery('#brand-thumbnail-pagination');

    // let currentPage = 1;

    // // Default view
    // $thumbnailView.hide();
    // $listView.show();

    // // List View
    // $listBtn.on('click', function () {
    //     $listBtn.addClass('active');
    //     $thumbBtn.removeClass('active');
    //     $thumbnailView.hide();
    //     $listView.show();
    // });

    // // Thumbnail View
    // $thumbBtn.on('click', function () {
    //     $thumbBtn.addClass('active');
    //     $listBtn.removeClass('active');
    //     $listView.hide();
    //     $thumbnailView.show();
    //     loadThumbnails(1);
    // });

    // // AJAX Loader
    // function loadThumbnails(page = 1) {
    //     $.ajax({
    //         url: "<?php echo admin_url('admin-ajax.php'); ?>",
    //         method: 'POST',
    //         data: {
    //             action: 'load_thumbnail_view_brand',
    //             page: page
    //         },
    //         beforeSend: function () {
    //             $grid.html('<p>Loading...</p>');
    //         },
    //         success: function (response) {
    //             if (response.success) {
    //                 $grid.html(response.data.html);
    //                 $pagination.html(response.data.pagination);
    //                 currentPage = page;
    //             } else {
    //                 $grid.html('<p>Error loading thumbnails.</p>');
    //             }
    //         },
    //         error: function () {
    //             $grid.html('<p>AJAX request failed.</p>');
    //         }
    //     });
    // }

    // // Pagination click
    // jQuery(document).on('click', '.pagination button[data-page]', function () {
    //     const page = parseInt(jQuery(this).data('page'));
    //     loadThumbnails(page);
    // });

    //-----------------------------

    jQuery('.search-input').on('keyup', function () {
        var value = jQuery(this).val().toLowerCase();
        jQuery('#brand-product-assigned-table tbody tr').filter(function () {
            jQuery(this).toggle(jQuery(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    jQuery(document).ready(function ($) {
        // console.log('jkabskjvda');
        jQuery('#brand-status').on('change', function () {
            var selectedClass = jQuery(this).val(); // Get selected option value
            var form = jQuery('#brand-status');

            // Remove any existing status-related class
            form.removeClass('active pending deactivated closed awaiting-publishing');

            // Add the selected class
            form.addClass(selectedClass);
        });

        // Trigger on page load (in case there's a preselected value)
        jQuery('#brand-status').trigger('change');
    });


    // Brands table header filter functionality Start -----------------

    // Append filter icon + filter box into each <th>
    jQuery('#brands-table thead th').each(function (index) {
        const colText = jQuery(this).text().trim();
        const isCheckboxCol = index === 0; 
        // const isFirstCol = index === 0; 
        const isStatusCol = index === 5;   
        const isBrandNameCol = index === 3;   
        const isDetailsCol = index === 6;  
        const colSlug = jQuery(this).data('head_slug');

        console.log('colSlug is : ',colSlug);
        console.log('colText is : ',colText);
        let inputField = '';
    
        if (isStatusCol) {
            inputField = `
                <div class="status-checkboxes" data-col="${index}">
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="b_status" class="status-filter" value="Active"> Active
                    </label>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="b_status" class="status-filter" value="Pending"> Pending
                    </label>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="b_status" class="status-filter" value="Deactivated"> Deactivated
                    </label>
                </div>
            `;
        } else if (isBrandNameCol) {
            inputField = `
                <div class="checkbox-group" data-col="${index}">
                    <!-- Checkboxes will be populated via AJAX -->
                    <p style="margin:0; font-size:12px;">Loading brands...</p>
                </div>`;
        } else if (!isCheckboxCol && !isDetailsCol) {
            inputField = `<input type="text" class="column-search" data-col="${index}" 
                placeholder="Search..." style="width:100%; padding:5px;">`;
        }
    
        // ✅ Inject directly into TH, not into dataTables_sizing
        jQuery(this).html(`
            ${colText}
            ${(index > 1 && index !== 6 && !isCheckboxCol) ? `
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
    
    // Track open/closed state for each filter box
    const brandFilterBoxStates = {};

    $(document).on('change', '#select-all-brands', function() {
        var isChecked = $(this).is(':checked');
        console.log('"Select All" clicked. Checked:', isChecked);

        $('.col3-filter').prop('checked', isChecked).trigger('change');
        console.log('All individual checkboxes set to:', isChecked);
    });

    // Toggle individual filter box on filter icon click
    jQuery('.brand-table thead').on('click', '.filter-icon', function(e) {
        e.stopPropagation();
        const colIndex = jQuery(this).data('col');
        const filterBox = jQuery(`.brand-table thead .filter-box[data-col="${colIndex}"]`);
        const isOpen = brandFilterBoxStates[colIndex];
        console.log('colIndex.......',colIndex);
        if(colIndex === 1 ){
            return;
        }
        if (isOpen) {
            filterBox.hide().removeClass('active_filter');
            filterBox.parent().removeClass('active_filter_wrapper');
            brandFilterBoxStates[colIndex] = false;
        } else {
            filterBox.show().addClass('active_filter');
            filterBox.parent().addClass('active_filter_wrapper');
            brandFilterBoxStates[colIndex] = true;

            // AJAX for Brand Name column only
            if (colIndex === 3 && !filterBox.data('loaded')) {
                $.ajax({
                    url: brandsData.ajax_url,
                    method: 'GET',
                    dataType: 'json',
                    data: {
                    action: 'get_all_brands'
                    },
                    success: function(response) {
                        console.log(response);
                        if (response && response.length) {

                            let checkboxes = `
                                <label style="display:block; margin-bottom:5px; font-weight:bold;">
                                    <input type="checkbox" id="select-all-brands" class="col3-select-all"> Select All
                                </label>
                            `;
                            
                            checkboxes += response.map(brand => `
                                <label style="display:block; margin-bottom:3px;">
                                    <input type="checkbox" name="b_name" class="col3-filter" value="${brand}"> ${brand}
                                </label>
                            `).join('');
                            filterBox.find('.checkbox-group').html(checkboxes);
                            filterBox.data('loaded', true);

                            // filterBox.find('.col3-select-all').on('change', function() {
                            //     const checked = $(this).is(':checked');
                            //     filterBox.find('.col3-filter').prop('checked', checked).trigger('change');
                            // });
                        }
                    },
                    error: function(err) {
                        console.error('Error fetching brands:', err);
                        filterBox.find('.checkbox-group').html('<p style="color:red;">Failed to load brands</p>');
                    }
                });
            }
        }
        // Prevent clicks inside filter box from closing
        filterBox.off('click').on('click', function(e) {
            e.stopPropagation();
        });
    });

    // First column: search by <img> alt attribute
    let imageColumnSearch = '';

    // 🔹 Custom search function for image column
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (!imageColumnSearch) return true; // no search, all rows pass
    
        const cell = brandTable.cell(dataIndex, 1).node(); // column 1 = image
        const imgAlt = jQuery(cell).find('img').attr('alt') || '';
        return imgAlt.toLowerCase().includes(imageColumnSearch.toLowerCase());
    });

    // 🔹 Apply text filters live
    jQuery('#brands-table thead').on('keyup change', '.column-search', function () {
        const colIndex = jQuery(this).data('col');
        const searchValue = this.value;
    
        if (colIndex === 1) {
            imageColumnSearch = searchValue;
        } else {
            // Other columns: normal search
            brandTable
            .column(colIndex)
            .search(searchValue);
        }
        brandTable.draw();
    });

    jQuery('.brand-table thead').on('change', '.col3-filter', function() {
        console.log('table works checkbox checking');
        let selectedBrands = jQuery('.col3-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex(jQuery(this).val());
        }).get();

        if (selectedBrands.length) {
            let regex = '^(' + selectedBrands.join('|') + ')$';
            brandTable.column(3).search(regex, true, false).draw();
        } else {
            brandTable.column(3).search('').draw();
        }
    });

    // 🔹 Apply status filter checkboxes
    jQuery('#brands-table thead').on('change', '.status-filter', function () {
        let selectedStatuses = jQuery('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex(jQuery(this).val());
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Active|Inactive)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            brandTable.column(5).search(regex, true, false).draw();
        } else {
            // Reset when nothing checked
            brandTable.column(5).search('').draw();
        }
    });

    // Optional: Set pagination numbers to 5
    $.fn.DataTable.ext.pager.numbers_length = 5;

    var brandTable = jQuery('#brands-table').DataTable({
        dom: '<"top">rt<"bottom"lip>',
        pageLength: 25,
        lengthMenu: [5, 25, 50, 100],
        order: [[2, 'asc']],
        paging: true,
        pagingType: "full_numbers",
        responsive: true,
        scrollX: false,
        columnDefs: [
            { orderable: false, targets: [0, 1, 6] },
            { searchable: false, targets: [0] }
        ],
        language: {
            search: "",
            searchPlaceholder: "Search by name or ID...",
            lengthMenu: "Show _MENU_ entries",
            zeroRecords: "No matching brands found",
            info: "Showing _START_ to _END_ of _TOTAL_ brands",
            infoEmpty: "Showing 0 to 0 of 0 brands",
            infoFiltered: "(filtered from _MAX_ total brands)",
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

            // Clean up old ellipses
            pagination.find('.ellipsis').remove();

            if (totalPages > 7) {
                pagination.find('.paginate_button').each(function () {
                    var pageNum = parseInt(jQuery(this).text(), 10);
                    if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                        jQuery(this).hide(); // Hide middle pages
                    }
                });

                // Insert ellipsis before the last page
                if (currentPage < totalPages - 2) {
                    jQuery('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                }

                // Insert ellipsis after the first page
                if (currentPage > 3) {
                    jQuery('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                }
            }
        },
        initComplete: function () {
            jQuery('.dataTables_length select').addClass('results-per-page');
        }
    });

    // Brands table header filter functionality End -----------------
    // External search integration
    jQuery('#brand-search').on('keyup', function () {
        brandTable.search(this.value).draw();
    });

    document.getElementById('brand-search').addEventListener('input', function () {
        const searchValue = this.value.toLowerCase();
        const cards = document.querySelectorAll('.brand-thumbnail-grid .brand-card');

        cards.forEach(card => {
            const title = card.querySelector('h3').dataset.title.toLowerCase();
            if (title.includes(searchValue)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });

    jQuery('#select-all').on('click', function () {
        const isChecked = jQuery(this).is(':checked');

        brandTable.rows().every(function () {
            const row = this.node();
            jQuery(row).find('.brand-checkbox').prop('checked', isChecked);
        });
    });

    // Export button handler
    // jQuery('#export-brands').on('click', function () {
    //     const searchValue = brandTable.search();

    //     $.ajax({
    //         url: brandsData.ajax_url,
    //         type: 'POST',
    //         data: {
    //             action: 'export_brands',
    //             search: searchValue,
    //         },
    //         xhrFields: {
    //             responseType: 'blob'
    //         },
    //         success: function (response) {
    //             const blob = new Blob([response]);
    //             const link = document.createElement('a');
    //             link.href = window.URL.createObjectURL(blob);
    //             link.download = 'brands-export.csv';
    //             link.click();
    //         },
    //         error: function (xhr, status, error) {
    //             console.error('Export failed:', error);
    //         }
    //     });
    // });

    jQuery('#list-view-btn').on('click', function () {
        jQuery('#brand-thumbnail-view').hide();
        jQuery('#brand-list-view').show();
        jQuery('#list-view-btn').addClass('active');
        jQuery('#thumbnail-view-btn').removeClass('active');
    });

    jQuery('#thumbnail-view-btn').on('click', function () {
        jQuery('#brand-list-view').hide();
        jQuery('#brand-thumbnail-view').show();
        jQuery('#thumbnail-view-btn').addClass('active');
        jQuery('#list-view-btn').removeClass('active');
    });
    // Open modal
    jQuery('#create-new-brand').on('click', function () {
        // console.log('create-new-brand');
        jQuery('#create-brand-modal').fadeIn();
    });

    // Close modal
    jQuery('.close-modal').on('click', function () {
        jQuery('#create-brand-modal').fadeOut();
    });

    // Upload brand logo
    var mediaUploader;
    // Trigger file input when button clicked
    jQuery('.upload-logo-btn').on('click', function (e) {
        // console.log('I am inside the page load....');
        e.preventDefault();
        jQuery('#brand_logo_file').click();
    });

    // Preview image after file selection
    jQuery('#brand_logo_file').on('change', function () {
        const file = this.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (e) {
                jQuery('#brand-logo-preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });


    // Handle form submit
    jQuery('#create-brand-form').on('submit', function (e) {
        e.preventDefault();

        const messageBox = jQuery('.creat-brand-success');
        messageBox.removeClass('error success').text('');

        const form = jQuery(this)[0];
        const formData = new FormData(form);

        formData.append('action', 'create_new_brand');

        const fileInput = jQuery('#brand_logo_file')[0];
        if (fileInput.files.length > 0) {
            formData.append('brand_logo_file', fileInput.files[0]);
        }

        $.ajax({
            url: brandsData.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    messageBox.addClass('success').text('Brand created successfully!');
                    setTimeout(() => {
                        window.location.href = (typeof brandsData !== 'undefined' && brandsData.siteUrl) ? brandsData.siteUrl.replace(/\/$/, '') + '/brands-list/' : '/brands-list/';
                    }, 1500);
                } else {
                    messageBox.addClass('error').text('Error: ' + res.data);
                }
            },
            error: function (xhr, status, error) {
                messageBox.addClass('error').text('AJAX Error: ' + error);
            }
        });
    });



    let brandProductTable = null;

    // Edit Brand Click Handler
    jQuery(document).on('click', '.edit-brand', function (e) {
        e.preventDefault();
        jQuery('#brand-edit-form').show();
        jQuery('.back-to-brandlist-wrapper').show();


        let termId = jQuery(this).data('term-id');
        jQuery('#brand-edit-error').removeClass('error').text('');

        jQuery('.brand-listing-section, .brand-management-header').hide();
        jQuery('.brand-edit-view').show();

        $.ajax({
            url: brandsData.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_brand_details',
                term_id: termId
            },
            success: function (response) {
                if (response.success) {
                    // Update thumbnail fields
                    updateImagePreview('thumbnail', response.data.thumbnail, response.data.thumbnail_id);
                    // Update basic fields
                    jQuery('#edit-brand-id').val(response.data.term_id);
                    jQuery('#display-brand-id').text(response.data.term_id);
                    // console.log('XXXXXXXXXXXXXXXX', response.data.term_id);
                    jQuery('#brand-name-display').text(response.data.name);
                    jQuery('#brand-description').val(response.data.description);
                    jQuery('#brand-status').val(response.data.status);

                    // Update status badge
                    // First remove previous status classes and then add the current one
                    let statusClass = response.data.status.toLowerCase().replace(/\s+/g, '-');
                    jQuery('#brand-status')
                        .removeClass(function (index, className) {
                            return (className.match(/(^|\s)status-\S+/g) || []).join(' ');
                        })
                        .addClass('status ' + statusClass);
                    // console.log('XXX', statusClass);



                    // Update logo
                    updateImagePreview('brand-logo', response.data.logo, response.data.logo_id);

                    // Initialize/reinitialize DataTable
                    if (brandProductTable) {
                        brandProductTable.destroy();
                    }

                    // Update the DataTable initialization for brand products
                    brandProductTable = jQuery('#brand-product-assigned-table').DataTable({
                        processing: true,
                        scrollX: true,
                        responsive: true,
                        serverSide: true,
                        ajax: {
                            url: brandsData.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'fetch_brand_products',
                                term_id: termId
                            }
                        },
                        columns: [
                            {
                                data: 'rank',
                                className: 'dt-center',
                                orderable: false
                            },
                            {
                                data: 'image',
                                render: function (data) {
                                    return `<div class="image img-rounded"><span class="image-inner"><img src="${data}" style="max-width:50px;max-height:50px;"></span></div>`;
                                },
                                orderable: false
                            },
                            { data: 'name', className: 'card-name' },
                            { data: 'denomination_type', className: 'dt-center' },
                            {
                                data: 'denomination',
                                className: 'dt-right',
                                render: function (data) {
                                    return data ? `$${parseFloat(data).toFixed(2)}` : 'N/A';
                                }
                            },
                            {
                                data: 'status',
                                className: 'dt-center',
                                render: function (data) {
                                    let displayText = data === 'draft' ? 'Awaiting Publishing' : data;
                                    let className = data === 'Awaiting Publishing' ? 'awaiting-publishing' : data.toLowerCase();
                                    return `<div class="status ${className}"><span>${displayText}</span></div>`;
                                }
                            }
                        ],
                        paging: true,
                        pageLength: 10,
                        dom: '<"top"f>rt<"bottom"lip>',
                        responsive: true,
                        order: [[2, 'asc']],
                        language: {
                            search: "Search products:",
                            lengthMenu: "Show _MENU_ products per page",
                            info: "Showing _START_ to _END_ of _TOTAL_ products",
                            paginate: {
                                previous: "&laquo;",
                                next: "&raquo;"
                            }
                        }
                    });
                } else {
                    jQuery('#brand-edit-error').addClass('error').text('Error: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                jQuery('#brand-edit-error').addClass('error').text('AJAX Error: ' + error);
            }
        });
    });

    // Brand Form Submit Handler
    jQuery('#save-brand-button').on('click', function (e) {
        e.preventDefault();

        const form = jQuery('#brand-edit-form')[0];
        const formData = new FormData(form);

        formData.append('action', 'save_brand_changes');

        const fileInput = jQuery('#thumbnail-upload')[0];
        if (fileInput && fileInput.files.length > 0) {
            formData.append('brand_logo_file', fileInput.files[0]);
        }

        const button = jQuery(this);
        const messageContainer = jQuery('.success-brand-message');

        messageContainer.removeClass('error success').text('');
        button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: brandsData.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                button.prop('disabled', false).text('Save Changes');

                if (response.success) {
                    updateBrandRowInList(response.data);
                    messageContainer.addClass('success').text('Brand updated successfully!');
                } else {
                    messageContainer.addClass('error').text('Error: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                button.prop('disabled', false).text('Save Changes');
                messageContainer.addClass('error').text('AJAX Error: ' + error);
            }
        });
    });



    // Update image preview function
    function updateImagePreview(target, url, id) {
        const preview = jQuery(`#${target}-preview`);
        const removeBtn = jQuery(`.remove-image-button[data-target="${target}"]`);

        if (url) {
            preview.attr('src', url).show();
            jQuery(`#${target}-id`).val(id);
            removeBtn.show();
        } else {
            preview.hide().attr('src', '');
            jQuery(`#${target}-id`).val('');
            removeBtn.hide();
        }
    }

    // Image Upload Handling
    // Open file picker when upload button is clicked
    jQuery('.upload-image-button').on('click', function () {
        const target = jQuery(this).data('target');
        jQuery(`#${target}-upload`).click(); // trigger hidden file input
    });

    // Handle image preview when a file is selected
    jQuery('.image-upload-input').on('change', function (event) {
        const target = jQuery(this).data('target');
        const file = event.target.files[0];

        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (e) {
                jQuery(`#${target}-preview`).attr('src', e.target.result).show();
                jQuery(`.remove-image-button[data-target="${target}"]`).show();
            };
            reader.readAsDataURL(file);

            // You can also store the file name or actual file object if needed
            jQuery(`#${target}-id`).val(file.name); // Optional: you might want to store something else
        }
    });

    // Optional: Remove image
    jQuery('.remove-image-button').on('click', function () {
        const target = jQuery(this).data('target');

        // Hide preview
        jQuery(`#${target}-preview`).attr('src', '').hide();

        // Clear input values
        jQuery('#brand_logo_id').val(''); // Use correct ID
        jQuery(`#${target}-upload`).val('');

        // Hide remove button
        jQuery(this).hide();
    });


    jQuery('.remove-image-button').on('click', function () {
        const target = jQuery(this).data('target');

        // Hide preview
        jQuery(`#${target}-preview`).attr('src', '').hide();

        // Clear input values
        jQuery(`#${target}-id`).val(''); // This one is important
        jQuery(`#${target}-upload`).val('');

        // Hide remove button
        jQuery(this).hide();

        // Optional: re-enable upload button
        jQuery(`.upload-image-button[data-target="${target}"]`).show();
    });


    // Helper to update list view
    function updateBrandRowInList(data) {
        const row = jQuery(`#brands-table tbody tr td:nth-child(3):contains("${data.term_id}")`).closest('tr');

        if (row.length) {
            row.find('td:nth-child(4)').text(data.name);
            row.find('td:nth-child(5)').text(data.count); // Update assigned count if needed
            row.find('td.status').text(data.status.charAt(0).toUpperCase() + data.status.slice(1))
                .removeClass().addClass('status status-' + data.status);

            if (data.logo) {
                row.find('.brand-thumbnail img').attr('src', data.logo);
            }

        }
    }
    // Update the product assignment functions in your JS
    function showAddProductsPopup(termId) {
        jQuery('#add-products-popup').show();
        loadProductsForPopup(termId, 'product_brand');
    }

    function loadProductsForPopup(termId, taxonomy) {
        $.ajax({
            url: brandsData.ajax_url,
            type: 'POST',
            data: {
                action: 'get_products_for_popup',
                term_id: termId,
                taxonomy: taxonomy
            },
            success: function (response) {
                if (response.success) {
                    renderProductsList(response.data.products, response.data.assigned_products);
                }
            }
        });
    }

    // Update the assign products handler
    jQuery('#assign-products').on('click', function () {
        const termId = jQuery('#edit-brand-id').val();
        const productIds = [];

        jQuery('#products-list input[type="checkbox"]:checked').each(function () {
            productIds.push(jQuery(this).val());
        });

        $.ajax({
            url: brandsData.ajax_url,
            type: 'POST',
            data: {
                action: 'assign_products_to_brand',
                term_id: termId,
                product_ids: productIds
            },
            success: function (response) {
                if (response.success) {
                    brandProductTable.ajax.reload();
                }
            }
        });
    });


    // Back to list handling
    jQuery(document).on('click', '.back-to-brandlist', function () {
        jQuery('.brand-edit-view').hide();
        jQuery('#brand-edit-form').hide();
        jQuery('.back-to-brandlist-wrapper').hide();
        jQuery('.brand-listing-section, .brand-management-header').show();
    });

    // Add product assignment functionality
    jQuery(document).on('click', '.add-product-btn', function () {
        const termId = jQuery('#edit-brand-id').val();
        const messageBox = jQuery('.error-fetching-products');

        messageBox.removeClass('error success').text('');

        if (!termId) {
            messageBox.addClass('error').text('No brand selected!');
            return;
        }
        showAddProductsPopup(termId);
    });

    function showAddProductsPopup(termId) {
        jQuery('#add-products-popup').show();
        loadProductsForBrandPopup(termId);
    }

    function loadProductsForBrandPopup(termId) {
        $.ajax({
            url: brandsData.ajax_url,
            type: 'POST',
            data: {
                action: 'get_products_for_brand_popup',
                term_id: termId
            },
            beforeSend: function () {
                jQuery('#products-list').html('<li class="loading">Loading products...</li>');
            },
            success: function (response) {
                if (response.success) {
                    renderProductsList(response.data.products, response.data.assigned_products);
                } else {
                    jQuery('#products-list').html('<li class="error">Error loading products</li>');
                }
            },
            error: function () {
                jQuery('#products-list').html('<li class="error">Error loading products</li>');
            }
        });
    }

    function renderProductsList(products, assignedProducts) {
        const assignedIds = assignedProducts.map(id => id.toString());
        const $list = jQuery('#products-list').empty();

        if (products.length === 0) {
            $list.append('<li>No products found</li>');
            return;
        }

        products.forEach(product => {
            const isAssigned = assignedIds.includes(product.id.toString());
            $list.append(`
        <li>
            <input type="checkbox" id="product-${product.id}" value="${product.id}" ${isAssigned ? 'checked' : ''}>
            <img src="${product.image}" class="product-image" alt="${product.name}">
            <label for="product-${product.id}">${product.name}</label>
        </li>
    `);
        });
    }


    // Handle product assignment
    jQuery('#assign-products').on('click', function () {
        const termId = jQuery('#edit-brand-id').val();
        const productIds = [];
        const messageBox = jQuery('.added-product-message');

        // Clear previous message
        messageBox.removeClass('error success').text('');

        jQuery('#products-list input[type="checkbox"]:checked').each(function () {
            productIds.push(jQuery(this).val());
        });

        $.ajax({
            url: brandsData.ajax_url,
            type: 'POST',
            data: {
                action: 'assign_products_to_brand',
                term_id: termId,
                product_ids: productIds
            },
            beforeSend: function () {
                jQuery('#assign-products').prop('disabled', true).text('Assigning...');
            },
            success: function (response) {
                jQuery('#assign-products').prop('disabled', false).text('Add Products');

                if (response.success) {
                    if (brandProductTable) {
                        brandProductTable.ajax.reload();
                    }
                    // Update assigned count
                    const countElement = jQuery(`#brands-table td[data-term-id="${termId}"] .assigned-count`);
                    if (countElement.length) {
                        countElement.text(response.data.count);
                    }
                    messageBox.addClass('success').text('Products assigned successfully.');
                    setTimeout(() => {
                        jQuery('#add-products-popup').hide();
                    }, 1500);
                } else {
                    messageBox.addClass('error').text('Error: ' + response.data);
                }
            },
            error: function () {
                jQuery('#assign-products').prop('disabled', false).text('Add Products');
                messageBox.addClass('error').text('Error assigning products.');
            }
        });
    });

    // Add this to your existing JavaScript
    jQuery('#export-brands-product').on('click', function () {
        // Get current DataTable parameters
        const termId = jQuery('#edit-brand-id').val();
        const searchValue = brandProductTable.search();

        // Create temporary form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = brandsData.ajax_url; // ✅ Use localized ajax_url

        // Add parameters
        const params = {
            action: 'export_brands_products',
            term_id: termId,
            search: searchValue,
        };

        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = key;
                hiddenField.value = params[key];
                form.appendChild(hiddenField);
            }
        }

        document.body.appendChild(form);
        form.submit();
    });
    // Add search functionality
    jQuery('#product-search').on('input', function () {
        const searchTerm = jQuery(this).val().toLowerCase();
        jQuery('#products-list li').each(function () {
            const text = jQuery(this).text().toLowerCase();
            jQuery(this).toggle(text.includes(searchTerm));
        });
    });

    // Close popup
    jQuery('.close-popup, .popup-overlay').on('click', function (e) {
        if (e.target === this) {
            jQuery('#add-products-popup').hide();
        }
    });

});