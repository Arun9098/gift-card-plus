jQuery(document).ready(function($) {
    const tableId = 'brand_listing_reportTable';
    const VAL     = BRAND_LISTING_REPORT_VAL;

    const table = $('#' + tableId).DataTable({
        columns: [
            { title: "Brand ID" },
            { title: "Brand Name" },
            { title: "Brand Slug" },
            { title: "Description" },
            { title: "Total Products" },
            { title: "Active Products" },
            { title: "Supplier" },
            { title: "Thumbnail URL" }
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
        const colText = $(this).text();
        const colSlug = $(this).data('head_slug');
        const inputField = `<input type="text" class="column-search" data-col="${index}" placeholder="Search..." style="width:100%; padding:5px;">`;

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

    $('#' + tableId + ' thead').on('keyup change', '.column-search', function() {
        table.column($(this).data('col')).search(this.value).draw();
    });

    async function fetchBrands(page = 1, perPage = 50) {
        try {
            if (VAL.permissions != 1) throw new Error('Not allowed');
            const res = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });
            if (!res.ok) throw new Error('Failed to fetch');
            return await res.json();
        } catch (e) {
            console.error(e);
            return { brands: [], total_brands: 0 };
        }
    }

    async function loadBrandsForTable() {
        const first = await fetchBrands(1, 50);
        table.clear();
        table.rows.add(first.brands.map(brand_data)).draw();
        const totalPages = Math.ceil(first.total_brands / 50);
        for (let p = 2; p <= totalPages; p++) {
            const page = await fetchBrands(p, 50);
            table.rows.add(page.brands.map(brand_data)).draw(false);
        }
    }

    loadBrandsForTable();
});

function brand_data(b) {
    return [
        b.brand_id,
        b.brand_name,
        b.brand_slug,
        b.brand_description,
        b.total_products,
        b.active_products,
        b.supplier,
        b.brand_thumbnail
    ];
}
