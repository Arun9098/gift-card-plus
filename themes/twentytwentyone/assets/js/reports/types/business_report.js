jQuery(document).ready(function($) {
    const tableId = 'business_reportTable';
    const VAL     = BUSINESS_REPORT_VAL;

    const table = $('#' + tableId).DataTable({
        columns: [
            { title: "Business ID" },
            { title: "Business Name" },
            { title: "Float ID" },
            { title: "Client Billing (Y/N)" },
            { title: "Billing Type" },
            { title: "ABN" },
            { title: "Website" },
            { title: "Email" },
            { title: "Mobile" },
            { title: "Address Line 1" },
            { title: "Address Line 2" },
            { title: "Suburb" },
            { title: "State" },
            { title: "Country" },
            { title: "Postcode" },
            { title: "Currency" },
            { title: "Float Balance" },
            { title: "Prepaid Limit" },
            { title: "Team User IDs" },
            { title: "Total Orders" },
            { title: "Total Spend ($)" },
            { title: "Account Created Date" }
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
        const colText     = $(this).text();
        const colSlug     = $(this).data('head_slug');
        const isBilling   = index === 3;

        let inputField;
        if (isBilling) {
            inputField = `<div class="status-checkboxes" data-col="${index}">
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="br-billing-filter" value="Y"> Y</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="br-billing-filter" value="N"> N</label>
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

    $('#' + tableId + ' thead').on('change', '.br-billing-filter', function() {
        const selected = $('.br-billing-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex($(this).val());
        }).get();
        table.column(3).search(selected.length ? '^(' + selected.join('|') + ')$' : '', true, false).draw();
    });

    $('#' + tableId + ' thead').on('keyup change', '.column-search', function() {
        table.column($(this).data('col')).search(this.value).draw();
    });

    async function fetchBusinesses(page = 1, perPage = 50) {
        try {
            if (VAL.permissions != 1) throw new Error('Not allowed');
            const res = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });
            if (!res.ok) throw new Error('Failed to fetch');
            return await res.json();
        } catch (e) {
            console.error(e);
            return { businesses: [], total_businesses: 0 };
        }
    }

    async function loadBusinessesForTable() {
        const first = await fetchBusinesses(1, 50);
        table.clear();
        table.rows.add(first.businesses.map(br_data)).draw();
        const totalPages = Math.ceil(first.total_businesses / 50);
        for (let p = 2; p <= totalPages; p++) {
            const page = await fetchBusinesses(p, 50);
            table.rows.add(page.businesses.map(br_data)).draw(false);
        }
    }

    loadBusinessesForTable();
});

function br_data(b) {
    return [
        b.business_id,
        b.business_name,
        b.business_float_id,
        b.approved_for_client_billing_y_n,
        b.billing_type,
        b.business_abn,
        b.business_website,
        b.email,
        b.mobile,
        b.address_line_1,
        b.address_line_2,
        b.suburb,
        b.state,
        b.country,
        b.postcode,
        b.business_currency,
        b.float_balance,
        b.prepaid_limit,
        b.team_user_ids,
        b.total_orders,
        b.total_spend,
        b.account_created_date
    ];
}
