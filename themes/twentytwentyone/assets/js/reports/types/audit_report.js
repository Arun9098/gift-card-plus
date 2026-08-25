jQuery(document).ready(function($) {
    jQuery.extend(jQuery.fn.dataTable.ext.type.order, {
        "date-dd-mm-yyyy-pre": function (dateStr) {
            if (!dateStr) return 0;
            const parts = dateStr.split('-');
            if (parts.length !== 3) return 0;
            return new Date(parts[2], parseInt(parts[1], 10) - 1, parts[0]).getTime() || 0;
        }
    });

    const tableId = 'audit_reportTable';
    const VAL     = AUDIT_REPORT_VAL;

    const table = $('#' + tableId).DataTable({
        columns: [
            { title: "Order Date" },
            { title: "Order Time" },
            { title: "Order Number" },
            { title: "Order Name" },
            { title: "Business Name" },
            { title: "Business ID" },
            { title: "Order Status" },
            { title: "Gift Card Post ID" },
            { title: "Gift Card Name" },
            { title: "Gift Card SKU" },
            { title: "Denomination" },
            { title: "Card Status" },
            { title: "Delivery Method" },
            { title: "Delivery Email" },
            { title: "Delivery Date" },
            { title: "Delivery Time" },
            { title: "Sender Name" },
            { title: "Sender Email" },
            { title: "Recipient Name" },
            { title: "Payment Method" },
            { title: "Invoice Number" },
            { title: "Campaign" },
            { title: "Activation Expiry Type" },
            { title: "Activation Expiry Date" },
            { title: "Gift Card Expiry Date" }
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
        const isStatus = index === 6;

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
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ar-status-filter" value="Completed"> Completed</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ar-status-filter" value="Processing"> Processing</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ar-status-filter" value="Cancelled"> Cancelled</label>
                <label style="display:block; margin-bottom:3px;"><input type="checkbox" class="ar-status-filter" value="Refunded"> Refunded</label>
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

    $('#' + tableId + ' thead').on('change', '.ar-status-filter', function() {
        const selected = $('.ar-status-filter:checked').map(function() {
            return $.fn.dataTable.util.escapeRegex($(this).val());
        }).get();
        table.column(6).search(selected.length ? '^(' + selected.join('|') + ')$' : '', true, false).draw();
    });

    $('#' + tableId + ' thead').on('keyup change', '.column-search', function() {
        const colIndex = $(this).data('col');
        if (colIndex === 0) {
            const $box = $(`.filter-box[data-col="0"]`);
            const from = $box.find('.date-from').val();
            const to   = $box.find('.date-to').val();
            $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(fn => fn.name !== 'arDateFilter');
            if (from || to) {
                $.fn.dataTable.ext.search.push(function arDateFilter(settings, data) {
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

    async function fetchRecords(page = 1, perPage = 50) {
        try {
            if (VAL.permissions != 1) throw new Error('Not allowed');
            const res = await fetch(`${VAL.rest_url}?page=${page}&per_page=${perPage}`, {
                headers: { 'X-WP-Nonce': VAL.wp_rest_nonce }
            });
            if (!res.ok) throw new Error('Failed to fetch');
            return await res.json();
        } catch (e) {
            console.error(e);
            return { records: [], total_gift_cards: 0 };
        }
    }

    async function loadRecordsForTable() {
        const first = await fetchRecords(1, 50);
        table.clear();
        table.rows.add(first.records.map(audit_data)).draw();
        const totalPages = Math.ceil(first.total_gift_cards / 50);
        for (let p = 2; p <= totalPages; p++) {
            const page = await fetchRecords(p, 50);
            table.rows.add(page.records.map(audit_data)).draw(false);
        }
    }

    loadRecordsForTable();
});

function audit_data(r) {
    return [
        r.order_date,
        r.order_time,
        r.order_number,
        r.order_name,
        r.business_name,
        r.business_id,
        r.order_status,
        r.gift_card_post_id,
        r.gift_card_name,
        r.gift_card_sku,
        r.gift_card_denomination,
        r.gift_card_status,
        r.delivery_method,
        r.delivery_email,
        r.delivery_date,
        r.delivery_time,
        r.sender_name,
        r.sender_email,
        r.recipient_name,
        r.payment_method,
        r.invoice_number,
        r.campaign,
        r.activation_expiry_type,
        r.activation_expiry_date,
        r.gift_card_expiry_date
    ];
}
