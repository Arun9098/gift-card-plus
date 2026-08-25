jQuery(document).ready(function($) {
    const tableId = 'individual_business_balance_statementTable';
    const VAL     = INDIVIDUAL_BUSINESS_BALANCE_STATEMENT_VAL;

    const table = $('#' + tableId).DataTable({
        columns: [
            { title: "Business Name" },
            { title: "Business ID" },
            { title: "Approved for Client Billing" },
            { title: "Business Billing Type" },
            { title: "Date/Time" },
            { title: "User" },
            { title: "Balance Type" },
            { title: "Action" },
            { title: "Business Float ID" },
            { title: "Order Number" },
            { title: "Invoice Number" },
            { title: "Status" },
            { title: "Amount ($)" },
            { title: "Reference" },
            { title: "Balance ($)" }
        ],
        pageLength: 25,
        lengthChange: true,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        responsive: true,
        data: [],
        order: [[4, 'desc']],
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
        const colText    = $(this).text();
        const colSlug    = $(this).data('head_slug');
        const isApproved = index === 2;
        const isStatus   = index === 11;

        let inputField;
        if (isApproved) {
            inputField = `<div class="status-checkboxes" data-col="${index}">
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ibbs-approved-filter" value="Y"> Y</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ibbs-approved-filter" value="N"> N</label>
            </div>`;
        } else if (isStatus) {
            inputField = `<div class="status-checkboxes" data-col="${index}">
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ibbs-status-filter" value="Completed"> Completed</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ibbs-status-filter" value="Processing"> Processing</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ibbs-status-filter" value="Cancelled"> Cancelled</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ibbs-status-filter" value="Refunded"> Refunded</label>
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

    $('#' + tableId + ' thead').on('change', '.ibbs-approved-filter', function() {
        const selected = $('.ibbs-approved-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex($(this).val());
        }).get();
        table.column(2).search(selected.length ? '^(' + selected.join('|') + ')$' : '', true, false).draw();
    });

    $('#' + tableId + ' thead').on('change', '.ibbs-status-filter', function() {
        const selected = $('.ibbs-status-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex($(this).val());
        }).get();
        table.column(11).search(selected.length ? '^(' + selected.join('|') + ')$' : '', true, false).draw();
    });

    $('#' + tableId + ' thead').on('keyup change', '.column-search', function() {
        table.column($(this).data('col')).search(this.value).draw();
    });

    async function fetchData(page = 1, perPage = 50) {
        try {
            if (VAL.permissions != 1) throw new Error('Not allowed');
            const res = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });
            if (!res.ok) throw new Error('Failed to fetch');
            return await res.json();
        } catch (e) {
            console.error(e);
            return { orders: [], total_orders: 0 };
        }
    }

    async function loadData() {
        const first = await fetchData(1, 50);
        table.clear();
        table.rows.add(first.orders.map(ibbs_data)).draw();
        const totalPages = Math.ceil(first.total_orders / 50);
        for (let p = 2; p <= totalPages; p++) {
            const page = await fetchData(p, 50);
            table.rows.add(page.orders.map(ibbs_data)).draw(false);
        }
    }

    loadData();
});

function ibbs_data(o) {
    return [
        o.business_name,
        o.business_id,
        o.approved_for_client_billing,
        o.business_billing_type,
        o.date_time,
        o.user,
        o.balance_type,
        o.action,
        o.business_float_id,
        o.order_number,
        o.invoice_number,
        o.status,
        o.amount,
        o.reference,
        o.balance
    ];
}
