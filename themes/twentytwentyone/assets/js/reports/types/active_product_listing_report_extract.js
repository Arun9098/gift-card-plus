jQuery(document).ready(function($) {
    //console.log(ORDER_REPORT_VAL);
    const VAL     = ACTIVE_PRODUCT_LISTING_REPORT_EXTRACT_VAL;

    const table = $('#active_product_listing_report_extractsTable').DataTable({
        columns: [
            { title: "Product ID" },
            { title: "Product Status" },
            { title: "Live on Site (Y/N)" },
            { title: "Parent or Child SKU" },
            { title: "Linked to Parent" },
            { title: "Parent SKU" },
            { title: "SKU " },
            { title: "Supplier SKU" },
            { title: "Gift Card Title" },
            { title: "Brand" },
            { title: "Supplier" },
            { title: "Short Description" },
            { title: "Long Description" },
            { title: "Terms & Conditions" },
            { title: "How to Use" },
            { title: "Expiry Date/ Time" },
            { title: "Gift Card Expiry Type" },
            { title: "Gift Card Expiry Date" },
            { title: "Gift Card Expiry period" },
            { title: "Period type " },
            { title: "Gift Card Activation Type" },
            { title: "Gift Card Activation Date" },
            { title: "Gift Card Activation period" },
            { title: "Period type" },
            { title: "Denomination Type" },
            { title: "Denomination" },
            { title: "Cost Price" },
            { title: "Supplier Fulfilment Price" },
            { title: "GST" },
            { title: "GC+ Fulfilment Costs" },
            { title: "Preset delivery class" },
            { title: "Delivery Cost" },
            { title: "Discounted Price" },
            { title: "Discounted Price" },
            { title: "Discount Valid From" },
            { title: "Discount Valid To" },
            { title: "Icons" },
            { title: "Tags" },
            { title: "Categories" },
            { title: "Featured Placements" },
            { title: "Extra Header" },
            { title: "Add stock levels" },
            { title: "Stock levels" },
            { title: "Add transaction limits" },
            { title: "Qty per transaction" },
            { title: "Total value per transaction" },
            { title: "Available for all users" },
            { title: "Always on" },
            { title: "Onsite from (date/ time)" },
            { title: "Onsite to (date/ time)" },
            { title: "Created by (Admin)" },
            { title: "Last Updated by (Admin)" },
            { title: "Brand image" },
            { title: "Card Image 1 (Cover image)" },
            { title: "Card Images" },
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
                            return $('#active_product_listing_report_extractsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
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
                            return $('#active_product_listing_report_extractsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
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
                            return $('#active_product_listing_report_extractsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
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
                            return $('#active_product_listing_report_extractsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
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
                            return $('#active_product_listing_report_extractsTable thead th').eq(columnIdx).clone().children().remove().end().text().trim();
                        }
                    }
                }
            }
        ]
    });

    jQuery('#active_product_listing_report_extractsTable thead th').each(function (index) {
        const colText = jQuery(this).text();
        const colSlug = jQuery(this).data('head_slug');
        
        const isDateColumn = index === 1;
        const isStatusColumn = index === 1;
    
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
                        <input type="checkbox" name="o_status" class="status-filter" value="Active"> Active
                    </label>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="o_status" class="status-filter" value="Awaiting Publishing"> Awaiting Publishing
                    </label>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="o_status" class="status-filter" value="Deactivated"> Deactivated
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
    jQuery('#active_product_listing_report_extractsTable thead').on('click', '.filter-icon', function (e) {
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

    jQuery('#active_product_listing_report_extractsTable thead').on('change', '.status-filter', function () {
        let selectedStatuses = $('.status-filter:checked').map(function () {
            return $.fn.dataTable.util.escapeRegex($(this).val()); // escape safely
        }).get();

        if (selectedStatuses.length) {
            // Build regex like: ^(Draft|Processing|Completed)$
            let regex = '^(' + selectedStatuses.join('|') + ')$';
            table.column(1).search(regex, true, false).draw();
        } else {
            // Reset filter when nothing is selected
            table.column(1).search('').draw();
        }
    });

    // Live filtering
    jQuery('#active_product_listing_report_extractsTable thead').on('keyup change', '.column-search', function () {
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
                    if (settings.nTable.id !== 'active_product_listing_report_extractsTable') return true;
                
                    
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
    
        const isLastColumn = colIndex === $('#active_product_listing_report_extractsTable thead th').length - 1;
    
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

    async function fetchProducts(page = 1, perPage = 50) {
        try {
            
            const response = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });

            if (ACTIVE_PRODUCT_LISTING_REPORT_EXTRACT_VAL.permissions != 1) throw new Error('Not allowed');
            if (!response.ok) throw new Error('Failed to fetch Products');

            const json = await response.json();
            return json;
        } catch (error) {
            console.error('Error fetching Products:', error);
            return { products: [], total_products: 0 };
        }
    }

    async function loadProductsForTable() {
        let page = 1;
        const perPage = 50;

        const firstPage = await fetchProducts(page, perPage);
        table.clear();
        table.rows.add(firstPage.products.map(product => [
            product.product_id,
            product.product_status,
            product.live_on_site_y_n,
            product.parent_or_child_sku,
            product.linked_to_parent,
            product.parent_sku,
            product.sku,
            product.supplier_sku,
            product.gift_card_title,
            product.brand,
            product.supplier,
            product.short_description,
            product.long_description,
            product.terms_conditions,
            product.how_to_use,
            product.expiry_date_time,
            product.gift_card_expiry_type,
            product.gift_card_expiry_date,
            product.gift_card_expiry_period,
            product.period_type,
            product.gift_card_activation_type,
            product.gift_card_activation_date,
            product.gift_card_activation_period,
            product.period_type,
            product.denomination_type,
            product.denomination,
            product.cost_price,
            product.supplier_fulfilment_price,
            product.gst,
            product.gc_fulfilment_costs,
            product.preset_delivery_class,
            product.delivery_cost,
            product.discounted_price,
            product.discounted_price,
            product.discount_valid_from,
            product.discount_valid_to,
            product.icons,
            product.tags,
            product.categories,
            product.featured_placements,
            product.extra_header,
            product.add_stock_levels,
            product.stock_levels,
            product.add_transaction_limits,
            product.qty_per_transaction,
            product.total_value_per_transaction,
            product.available_for_all_users,
            product.always_on,
            product.onsite_from__date_time,
            product.onsite_to__date_time,
            product.created_by_admin,
            product.last_updated_by_admin,
            product.brand_image,
            product.card_image_1_cover_image,
            product.card_images,
        ])).draw();

        const totalPages = Math.ceil(firstPage.total_products / perPage);

        // Optional: Fetch more pages automatically (or lazy load on user scroll)
        // Example: Fetch all pages sequentially
        for (let p = 2; p <= totalPages; p++) {
            const pageData = await fetchProducts(p, perPage);
            table.rows.add(pageData.products.map(product => [
                product.product_id,
                product.product_status,
                product.live_on_site_y_n,
                product.parent_or_child_sku,
                product.linked_to_parent,
                product.parent_sku,
                product.sku,
                product.supplier_sku,
                product.gift_card_title,
                product.brand,
                product.supplier,
                product.short_description,
                product.long_description,
                product.terms_conditions,
                product.how_to_use,
                product.expiry_date_time,
                product.gift_card_expiry_type,
                product.gift_card_expiry_date,
                product.gift_card_expiry_period,
                product.period_type,
                product.gift_card_activation_type,
                product.gift_card_activation_date,
                product.gift_card_activation_period,
                product.period_type,
                product.denomination_type,
                product.denomination,
                product.cost_price,
                product.supplier_fulfilment_price,
                product.gst,
                product.gc_fulfilment_costs,
                product.preset_delivery_class,
                product.delivery_cost,
                product.discounted_price,
                product.discounted_price,
                product.discount_valid_from,
                product.discount_valid_to,
                product.icons,
                product.tags,
                product.categories,
                product.featured_placements,
                product.extra_header,
                product.add_stock_levels,
                product.stock_levels,
                product.add_transaction_limits,
                product.qty_per_transaction,
                product.total_value_per_transaction,
                product.available_for_all_users,
                product.always_on,
                product.onsite_from__date_time,
                product.onsite_to__date_time,
                product.created_by_admin,
                product.last_updated_by_admin,
                product.brand_image,
                product.card_image_1_cover_image,
                product.card_images,
            ])).draw(false);
        }
    }

    loadProductsForTable();
});