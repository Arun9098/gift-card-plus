jQuery(document).ready(function ($) {
   
    $('#export-brands').on('click', function () {
        let b_id = '';
        let b_name = '';
        let b_assign = '';
        let b_status = '';
    
        // Collect filters
        $('#brands-table_wrapper .dataTables_scrollHead .brand-table thead th .filter-box.active_filter').each(function () {
            var $this = jQuery(this);
            var temp = $this.data('head_slug');
            var inputVal = $this.find('input').val();
            // console.log('bnamme........');
            
            if (temp == 'brand_status') {
                inputVal = $('input[name="b_status"]:checked').map(function() {
                    return $(this).val();
                }).get().join(',');
            }
    
            if (temp == 'brand_name') {
                inputVal = $('input[name="b_name"]:checked').map(function() {
                    return $(this).val();
                }).get().join(',');
            }
            if (temp == 'gift_id') b_id = inputVal;
            if (temp == 'brand_name') b_name = inputVal;
            if (temp == 'assigned') b_assign = inputVal;
            // console.log("Column:", temp, " → Value:", inputVal);
            if (temp == 'brand_status') b_status = inputVal;
            // if (temp == 'brand_name') b_name = inputVal;
            // console.log('bnamme........',b_name);

        });
        
        $('.csv-response').html('');
        $.ajax({
            url: export_brands_data.ajax_url,
            type: 'POST',
            data: {
                action: 'export_brands_batch_stream',
                b_id: b_id,
                b_name: b_name,
                b_assign: b_assign,
                b_status: b_status,
                _ajax_nonce: export_brands_data.nonce
            },
            xhrFields: {
                responseType: 'blob' // allows handling CSV download
            },
            success: function (data, status, xhr) {
                // Check if response is actually CSV
                var type = xhr.getResponseHeader('Content-Type');
                if (type && type.indexOf('text/csv') !== -1) {
                    // ✅ Trigger file download
                    var blob = new Blob([data], { type: 'text/csv' });
                    var link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);
                    link.download = 'brands-export.csv';
                    link.click();
                } else {
                    // ❌ Response is not CSV → show error message
                    var reader = new FileReader();
                    reader.onload = function () {
                        $('.csv-response').html('<div class="error-msg">' + reader.result + '</div>');
                    };
                    reader.readAsText(data);
                }
            },
            error: function (xhr) {
                $('.csv-response').html('<div class="error-msg">Export failed. Please try again.</div>');
            }
        });
    });    
});
