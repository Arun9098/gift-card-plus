jQuery(document).ready(function ($) {
    let offset = 0;
    let limit = 100;
    let csvContent = '';
    let isFirstBatch = true;

    // Store filters globally
    let search = '';
    let o_date = '';
    let o_id = '';
    let o_name = '';
    let o_ref = '';
    let o_user = '';
    let o_status = '';
    let o_invoice = '';
    let o_total = '';

    function fetchBatch() {
        console.log('o_ref: ',o_ref);
        console.log('o_id: ',o_id);
        console.log('o_name: ',o_name);
        console.log('o_date from: ', o_date.from);
        console.log('o_date to: ', o_date.to);
        console.log('o_user: ',o_user);
        console.log('o_status: ',o_status);
        console.log('o_invoice: ',o_invoice);
        console.log('o_total: ',o_total);
        // console.log('o_ref: ',o_id);
        // console.log('o_ref: ',o_date);


        $.ajax({
            url: export_orders_data.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'export_orders_batch_stream',
                offset: offset,
                limit: limit,
                search: search,
                o_date_from: o_date.from,
                o_date_to: o_date.to,              
                o_id: o_id,
                o_name: o_name,
                o_ref: o_ref,
                o_user: o_user,
                o_status: o_status,
                o_invoice: o_invoice,
                o_total: o_total,
                _ajax_nonce: export_orders_data.nonce
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data;

                    if (isFirstBatch && data.headers) {
                        csvContent += data.headers + '\n';
                        isFirstBatch = false;
                    }

                    csvContent += data.rows;

                    if (data.done) {
                        // Split CSV into lines
                        const csvLines = csvContent.trim().split('\n');

                        // If only 1 line (header), then no data rows present
                        if (csvLines.length <= 1) {
                            $('#exportStatus').show();
                            $('#exportStatus').html('❌ No data to export.');
                            $('#exportOrdersBtn').val('Export Orders');
                            $('#exportOrdersBtn').css('pointer-events', 'unset');
                        } else {
                            downloadCSV(csvContent);
                            $('#exportOrdersBtn').val('Export Orders');
                            $('#exportOrdersBtn').css('pointer-events', 'unset');
                        }

                    } else {
                        offset += limit;
                        setTimeout(fetchBatch, 100); // Slight delay for async smoothness
                    }
                } else {
                    jQuery('#exportStatus').removeAttr('style');
                    jQuery('#exportStatus').attr('style', 'display: block;text-align: right;margin-bottom: 15px;margin-top: -5px;');
                    $('#exportStatus').html('❌ Error: ' + response.data.message);
                }
            },
            error: function () {
                jQuery('#exportStatus').removeAttr('style');
                jQuery('#exportStatus').attr('style', 'display: block;text-align: right;margin-bottom: 15px;margin-top: -5px;');
                $('#exportStatus').html('❌ AJAX error occurred.');
            }
        });
    }

    function downloadCSV(content) {
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        const now = new Date();
        const dateTime = now.getFullYear() +
            String(now.getMonth() + 1).padStart(2, '0') +
            String(now.getDate()).padStart(2, '0') +
            String(now.getHours()).padStart(2, '0') +
            String(now.getMinutes()).padStart(2, '0') +
            String(now.getSeconds()).padStart(2, '0');

        link.setAttribute("href", url);
        link.setAttribute("download", "GC+ All Orders Export "+dateTime+".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    $('#exportOrdersBtn').on('click', function () {
        offset = 0;
        csvContent = '';
        isFirstBatch = true;
        jQuery('#exportOrdersBtn').val('📦 Starting export...');
        jQuery('#exportOrdersBtn').css('pointer-events', 'none');
        jQuery('#exportStatus').hide();

        var temp = '';
        o_date = { from: '', to: '' }; // Initialize as object
        o_id = o_name = o_ref = o_user = o_status = o_invoice = o_total = '';

        search = $('.order-list-container #order-search').val().trim();

        $('#order-table_wrapper .dataTables_scrollHead .order-table thead th .filter-box.active_filter').each(function (index) {
            var $this = jQuery(this);
            var temp = $this.data('head_slug');
            var inputVal = $this.find('input').val();

            // console.log('Index:', index);
            // console.log('Slug:', temp);
            // console.log('Input Value:', inputVal);

            if (temp == 'order_Status') {
                var inputVal = $('input[name="o_status"]:checked').map(function() {
                    if( $(this).val() == 'Draft' ){
                        return 'Pending';
                    }
                    return $(this).val();
                }).get().join(',');
            }
            if (temp == 'order_date') {
                const fromVal = $this.find('input.date-from').val() || '';
                const toVal = $this.find('input.date-to').val() || '';
                o_date = { from: fromVal, to: toVal };
            }
            
            if (temp == 'order_no') {
                o_id = inputVal;
            }
            if (temp == 'order_name') {
                o_name = inputVal;
            }
            if (temp == 'order_client_reference') {
                o_ref = inputVal;
            }

            if (temp == 'order_User') {
                o_user = inputVal;
            }
            if (temp == 'order_Status') {
                o_status = inputVal;
                console.log('inputVal: ',inputVal);
            }
            if (temp == 'order_Invoice') {
                o_invoice = inputVal;
            }
            if (temp == 'order_Total') {
                o_total = inputVal;
            }
        });
        fetchBatch();
    });
});
