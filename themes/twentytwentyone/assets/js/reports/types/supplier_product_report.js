jQuery(document).ready(function($) {
    const tableId = 'supplier_product_reportTable';
    const VAL     = SUPPLIER_PRODUCT_REPORT_VAL;

    const table = $('#' + tableId).DataTable({
        columns: [
            { title: "Product ID" },
            { title: "Product Name" },
            { title: "SKU" },
            { title: "Status" },
            { title: "Supplier ID" },
            { title: "Supplier Name" },
            { title: "Brand" },
            { title: "Denomination Type" },
            { title: "Min Price ($)" },
            { title: "Max Price ($)" },
            { title: "Buy Price ($)" },
            { title: "Fulfilment Cost ($)" },
            { title: "Delivery Cost ($)" },
            { title: "GST" },
            { title: "Supplier SKU" },
            { title: "Parent SKU" },
            { title: "Redemption Info" },
            { title: "Blackhawk (Y/N)" }
        ],
        pageLength: 25,
        lengthChange: true,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        responsive: true,
        data: [],
        order: [[1, 'asc']],
        dom: 'lBfrtip',
        buttons: [
            { extend: 'copy',  exportOptions: { columns: ':visible' } },
            { extend: 'csv',   exportOptions: { columns: ':visible' } },
            { extend: 'excel', exportOptions: { columns: ':visible' } },
            { extend: 'pdf',   exportOptions: { columns: ':visible' } },
            { extend: 'print', exportOptions: { columns: ':visible' } }
        ]
    });

    $('#' + tableId + ' thead th').each(function(index) {
        const colText  = $(this).text();
        const colSlug  = $(this).data('head_slug');
        const isStatus = index === 3;

        let inputField;
        if (isStatus) {
            inputField = `<div class="status-checkboxes" data-col="${index}">
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="spr-status-filter" value="publish"> Published</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="spr-status-filter" value="draft"> Draft</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="spr-status-filter" value="private"> Private</label>
            </div>`;
        } else {
            inputField = `<input type="text" class="column-search" data-col="${index}" placeholder="Search..." style="width:100%; padding:5px;">`;
        }

        $(this).html(`
            <span class="filter-header-text">${colText}</span>
            <span class="filter-icon" data-col="${index}" style="cursor:pointer;">
                <i class="fa-solid fa-arrow-down"></i>
            </span>
            <div class="filter-box" data-head_slug="${colSlug}" data-col="${index}" style="display:none; background:white; border:1px solid #ccc; padding:5px; z-index:999;">
                ${inputField}
            </div>
        `);
    });

    const filterBoxStates = {};

    $('#' + tableId + ' thead').on('click', '.filter-icon', function(e) {
        e.stopPropagation();
        const colIndex  = $(this).data('col');
        const filterBox = $(`.filter-box[data-col="${colIndex}"]`);
        const isOpen    = filterBoxStates[colIndex];
        if (isOpen) {
            filterBox.hide().removeClass('active_filter');
            filterBox.parent().removeClass('active_filter_wrapper');
            filterBoxStates[colIndex] = false;
        } else {
            filterBox.show().addClass('active_filter');
            filterBox.parent().addClass('active_filter_wrapper');
            filterBoxStates[colIndex] = true;
        }
        filterBox.off('click').on('click', function(e) { e.stopPropagation(); });
    });

    $('#' + tableId + ' thead').on('change', '.spr-status-filter', function() {
        const selected = $('.spr-status-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex($(this).val());
        }).get();
        table.column(3).search(selected.length ? '^(' + selected.join('|') + ')$' : '', true, false).draw();
    });

    $('#' + tableId + ' thead').on('keyup change', '.column-search', function() {
        table.column($(this).data('col')).search(this.value).draw();
    });

    async function fetchProducts(page = 1, perPage = 50) {
        try {
            if (VAL.permissions != 1) throw new Error('Not allowed');
            const res = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });
            if (!res.ok) throw new Error('Failed to fetch');
            return await res.json();
        } catch (e) {
            console.error(e);
            return { products: [], total_products: 0 };
        }
    }

    async function loadProductsForTable() {
        const first = await fetchProducts(1, 50);
        table.clear();
        table.rows.add(first.products.map(spr_data)).draw();
        const totalPages = Math.ceil(first.total_products / 50);
        for (let p = 2; p <= totalPages; p++) {
            const page = await fetchProducts(p, 50);
            table.rows.add(page.products.map(spr_data)).draw(false);
        }
    }

    loadProductsForTable();
});

function spr_data(p) {
    return [
        p.product_id,
        p.product_name,
        p.product_sku,
        p.product_status,
        p.supplier_id,
        p.supplier_name,
        p.brand,
        p.denomination_type,
        p.min_price,
        p.max_price,
        p.buy_price,
        p.fulfilment_cost,
        p.delivery_cost,
        p.gst,
        p.supplier_sku,
        p.parent_sku,
        p.redemption_info,
        p.is_blackhawk_product
    ];
}
