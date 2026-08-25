jQuery(document).ready(function($) {
    jQuery.extend(jQuery.fn.dataTable.ext.type.order, {
        "date-dd-mm-yyyy-pre": function (dateStr) {
            if (!dateStr) return 0;
            const parts = dateStr.split('-');
            if (parts.length !== 3) return 0;
            return new Date(parts[2], parseInt(parts[1], 10) - 1, parts[0]).getTime() || 0;
        }
    });

    const tableId = 'credit_card_payment_reportTable';
    const VAL     = CREDIT_CARD_PAYMENT_REPORT_VAL;

    const table = $('#' + tableId).DataTable({
        columns: [
            { title: "Order Date" },
            { title: "Order Time" },
            { title: "Order Number" },
            { title: "Order Name" },
            { title: "Business Name" },
            { title: "Business ID" },
            { title: "Payment Method" },
            { title: "Payment Method Title" },
            { title: "Transaction ID" },
            { title: "Order Status" },
            { title: "Invoice Number" },
            { title: "Order Total ($)" },
            { title: "Gift Cards Total ($)" },
            { title: "Fulfilment Total ($)" },
            { title: "Delivery Total ($)" },
            { title: "GST" },
            { title: "Campaign" },
            { title: "PO Number" },
            { title: "Client Reference" },
            { title: "Sender Profile" }
        ],
        columnDefs: [{ type: "date-dd-mm-yyyy", targets: 0 }],
        pageLength: 25,
        lengthChange: true,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        responsive: true,
        data: [],
        order: [[0, 'desc']],
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
        const isDate   = index === 0;
        const isStatus = index === 9;

        let inputField;
        if (isDate) {
            inputField = `
                <div style="position:relative; margin-bottom:4px;">
                    <label>From:<br>
                        <input type="date" class="column-search date-from" data-col="${index}" style="width:calc(100% - 20px); padding:5px;">
                        <span class="clear-date remove-date-from" style="position:absolute; right:11px; top:0; cursor:pointer; color:red;">✕</span>
                    </label>
                </div>
                <div style="position:relative;">
                    <label>To:<br>
                        <input type="date" class="column-search date-to" data-col="${index}" style="width:calc(100% - 20px); padding:5px;">
                        <span class="clear-date remove-date-to" style="position:absolute; right:11px; top:0; cursor:pointer; color:red;">✕</span>
                    </label>
                </div>`;
        } else if (isStatus) {
            inputField = `<div class="status-checkboxes" data-col="${index}">
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ccpr-status-filter" value="Completed"> Completed</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ccpr-status-filter" value="Processing"> Processing</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ccpr-status-filter" value="Pending"> Pending</label>
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

    $('#' + tableId + ' thead').on('change', '.ccpr-status-filter', function() {
        const selected = $('.ccpr-status-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex($(this).val());
        }).get();
        table.column(9).search(selected.length ? '^(' + selected.join('|') + ')$' : '', true, false).draw();
    });

    $('#' + tableId + ' thead').on('keyup change', '.column-search', function() {
        const colIndex = $(this).data('col');
        if (colIndex === 0) {
            const $box = $(`.filter-box[data-col="0"]`);
            const from = $box.find('.date-from').val();
            const to   = $box.find('.date-to').val();
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(fn => fn.name !== 'ccprDateFilter');
            if (from || to) {
                $.fn.dataTable.ext.search.push(function ccprDateFilter(settings, data) {
                    if (settings.nTable.id !== tableId) return true;
                    const [d, m, y] = (data[0] || '').split('-');
                    const cell = new Date(y, m - 1, d);
                    if (isNaN(cell)) return false;
                    if (from && cell < new Date(from)) return false;
                    if (to   && cell > new Date(to))   return false;
                    return true;
                });
            }
            table.draw();
            return;
        }
        table.column(colIndex).search(this.value).draw();
    });

    $('.remove-date-from').on('click', function(e) {
        e.stopPropagation();
        $(this).siblings('input.date-from').val('').trigger('change');
    });
    $('.remove-date-to').on('click', function(e) {
        e.stopPropagation();
        $(this).siblings('input.date-to').val('').trigger('change');
    });

    async function fetchOrders(page = 1, perPage = 50) {
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

    async function loadOrdersForTable() {
        const first = await fetchOrders(1, 50);
        table.clear();
        table.rows.add(first.orders.map(ccpr_data)).draw();
        const totalPages = Math.ceil(first.total_orders / 50);
        for (let p = 2; p <= totalPages; p++) {
            const page = await fetchOrders(p, 50);
            table.rows.add(page.orders.map(ccpr_data)).draw(false);
        }
    }

    loadOrdersForTable();
});

function ccpr_data(o) {
    return [
        o.order_date,
        o.order_time,
        o.order_number,
        o.order_name,
        o.business_name,
        o.business_id,
        o.payment_method,
        o.payment_method_title,
        o.transaction_id,
        o.order_status,
        o.invoice_number,
        o.order_total,
        o.gift_cards_total,
        o.fulfilment_total,
        o.delivery_total,
        o.gst,
        o.campaign,
        o.po_number,
        o.client_reference,
        o.sender_profile
    ];
}
