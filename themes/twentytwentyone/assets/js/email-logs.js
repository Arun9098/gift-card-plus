jQuery(document).ready(function ($) {
 

    const filterBtn = document.querySelector("#filter-btn");
    const filterWrapper = document.querySelector(".date-filter-wrapper");
    
    if (filterBtn && filterWrapper) {
        filterBtn.addEventListener("click", () => {
            filterWrapper.classList.toggle("active");
        });
    }
    
    let zoomeBtn = document.querySelector('#email-content-modal .zoome-btn');
    let zoomeContainer = document.querySelector('#email-content-modal');
    if (zoomeBtn) {
        zoomeBtn.addEventListener("click", () => {
            zoomeContainer.classList.toggle("container");
        });
    }
   
    jQuery('.search-input').on('keyup', function () {
        table.search(this.value).draw();
    });

    
    // Initialize DataTable
    var table = $('#email-logs-datatable').DataTable({
        "pageLength": 10,
        "responsive": true,
        "scrollX": true,
        "lengthMenu": [10, 20, 50, 100,],
        "order": [[0, "desc"]],
        "pagingType": "full_numbers",
        "columnDefs": [
            { "orderable": false, "targets": 0 },
            { "type": "date", "targets": 0 },
            { "searchable": true, "targets": [1, 2, 4] }
        ],
        "dom": "<'top'>rt<'bottom'lip>",
        "language": {
            search: "",
            searchPlaceholder: "Search by name or ID...",
            lengthMenu: "Show _MENU_ entries",
            zeroRecords: "No matching brands found",
            info: "Showing _START_ to _END_ of _TOTAL_ brands",
            infoEmpty: "Showing 0 to 0 of 0 brands",
            infoFiltered: "(filtered from _MAX_ total brands)",
            paginate: {
                first: "«",
                last: "»",
                next: "›",
                previous: "‹"
            }
        },
        initComplete: function () {
            $('.dataTables_length select').addClass('results-per-page');
        }
    });

    // Handle click on "Select all" control
    $('#email-logs-select-all').on('click', function () {
        var rows = table.rows({ 'search': 'applied' }).nodes();
        $('input[type="checkbox"].row-checkbox', rows).prop('checked', this.checked);
    });

    // If any checkbox is unchecked, uncheck the header checkbox
    $('#order-table tbody').on('change', 'input.row-checkbox', function () {
        if (!this.checked) {
            var el = $('#email-logs-select-all').get(0);
            if (el && el.checked) {
                el.checked = false;
            }
        }
    });
    
    // Date range filter

    // Date range filter
    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            var min = $('#date-from').val() ? new Date($('#date-from').val()) : null;
            var max = $('#date-to').val() ? new Date($('#date-to').val()) : null;
            var date = new Date(data[0]); // Sent at column

            if ((min === null && max === null) ||
                (min === null && date <= max) ||
                (min <= date && max === null) ||
                (min <= date && date <= max)) {
                return true;
            }
            return false;
        }
    );
    // Custom search function for status column
    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            var $statusInput = $('#status-filter');
            var statusFilter = $statusInput.length ? $statusInput.val().toLowerCase() : '';
            if (!statusFilter) return true;


            // Get the actual status from data-status attribute
            var rowStatus = $(table.row(dataIndex).node()).find('td:eq(3)').attr('data-status');
            return rowStatus === statusFilter;
        }
    );

    // Status filter change handler
    $('#status-filter').on('change', function () {
        table.draw(); // Trigger custom filter
    });

    // Reset filters


    // Date range filter event
    $('.date-filter').on('change', function () {
        table.draw();
    });

    // Reset all filters
    $('#reset-filters').on('click', function () {
        // Clear filter inputs
        $('#date-from').val('');
        $('#date-to').val('');
        $('#status-filter').val('');

        // Clear all DataTable filters
        table.columns().search('').draw();
        table.search('').draw();
    });
    // Modal functionality
    var modal = document.getElementById("email-content-modal");
    
    // var span = document.getElementsByClassName("close")[0];
    var closeBtn = document.getElementById("close-modal");

    // Close modal handlers
    // span.onclick = function () { modal.style.display = "none"; }
    // if(closeBtn.length){
    //     console.log('inside==============');
        closeBtn.onclick = function () { modal.style.display = "none"; 
            $(".email-logs-container").show();
        }
    // }
    window.onclick = function (event) {
        if (event.target == modal) { modal.style.display = "none"; }
    }

    // Tab functionality
    $('.email-content-tabs').on('click', '.tab-button', function () {
        $('.tab-button').removeClass('active');
        $(this).addClass('active');

        var tabId = $(this).data('tab');
        $('.tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });

    function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }
    const filteredUserId = getQueryParam('user_id');
    console.log('filteredUserId++++++',filteredUserId);

    // Handle view email click
    $('#email-logs-datatable').on('click', '.view-email', function (e) {
        e.preventDefault();
        var logId = $(this).data('logid');
        console.log('logId',logId);
        // Show modal and loading state
        modal.style.display = "block";
        $('.tab-content').removeClass('active');
        $('#raw-content').addClass('active');
        $('.tab-button').removeClass('active');
        $('.tab-button[data-tab="raw-content"]').addClass('active');
        $('#email-raw-content, #email-html-content').empty();
        $('#email-content-loading').show();
        $('.email-logs-container').hide();

        
        // AJAX request to get email content
        $.ajax({
            url: emailLogs.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_email_content',
                log_id: logId,
                user_id: filteredUserId
            },
            success: function (response) {
                if (response.success) {
                    // Update email metadata
                    $('#email-sent-at').text(response.data.sent_at);
                    $('#email-to').text(response.data.to_email);
                    $('#email-subject').text(response.data.subject);

                    // Update raw content
                    $('#email-raw-content').text(response.data.message);

                    // Update HTML preview
                    //$('#email-html-content').html(response.data.message.replace(/\n/g, '<br>'));
                    $('#email-html-content').html(response.data.message);

                    $('#email-content-loading').hide();
                    $('#raw-content').addClass('active');
                } else {
                    $('#email-content-data').html('<p>Error loading email content.</p>');
                    $('#email-content-loading').hide();
                }
            },
            error: function () {
                $('#email-content-data').html('<p>Error loading email content.</p>');
                $('#email-content-loading').hide();
            }
        });
    });

});