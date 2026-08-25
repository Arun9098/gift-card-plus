/* global jQuery */
(function ($) {
    jQuery(document).ready(function () {
        var $select = $('#report-type');
        var $btn = $('#export-report-btn');
        var $input = $('#report_type_input');
        var $loadingOverlay = $('#page-loading-overlay');


        var origin = window.location.origin
        // Show the export button when a non-empty option is chosen
        $select.on('change', function () {
            var val = $(this).val();
            console.log('val: ',val);
            console.log('origin: ',origin);
            if (val) {
                $loadingOverlay.addClass('active');
                $('body').css('pointer-events', 'none');
                $('body').css('opacity', '0.8');

                // Delay slightly to show loader before redirect
                setTimeout(function() {
                    window.location.href = origin + "/reports?report_type=" + val;
                }, 300);
            } else {
                window.location.href = origin+"/reports";
                // $input.val('');
                // $btn.hide();
            }
        });

        // Optional: client-side check on submit
        jQuery('#report-export-form').on('submit', function (e) {
            if (!$input.val()) {
                e.preventDefault();
                alert('Please select a report type to export.');
                return false;
            }
        });
    });

     // 🔹 Helper to update activated filter class
     function updateActivatedFilterState(colIndex) {
        const $th = $(`.dataTables_wrapper thead th:eq(${colIndex})`);
        const $filterBox = $(`.filter-box[data-col="${colIndex}"]`);

        let isActive = false;

        // Check text inputs
        $filterBox.find('input[type="text"]').each(function() {
            if ($(this).val().trim() !== '') {
                isActive = true;
            }
        });

        // Check date range
        const fromDate = $filterBox.find('.date-from').val();
        const toDate = $filterBox.find('.date-to').val();
        const singleDate = $filterBox.find('.single-date').val();
        const dateOnly = $filterBox.find('.date-only').val();
        const timeOnly = $filterBox.find('.time-only').val();
        if (fromDate || toDate) {
            isActive = true;
        }
        
        if (singleDate || dateOnly || timeOnly) {
            isActive = true;
        }

        // Check status checkboxes
        if ($filterBox.find('.status-filter:checked').length > 0) {
            isActive = true;
        }

        // Check business name checkboxes
        if ($filterBox.find('.col5-filter:checked').length > 0) {
            isActive = true;
        }

        // Toggle class based on filter activity
        if (isActive) {
            $th.addClass('activated-filter-column');
        } else {
            $th.removeClass('activated-filter-column');
        }
    }

    // 🔹 Listen for changes in all filter inputs
    $(document).on('keyup change', '.dataTables_wrapper thead .column-search, .status-filter, .col5-filter, .date-from, .date-to', function() {
        console.log('This is working');
        const colIndex = $(this).data('col') || $(this).closest('.filter-box').data('col');
        updateActivatedFilterState(colIndex);
    });


})(jQuery);