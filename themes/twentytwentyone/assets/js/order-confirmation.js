jQuery(document).ready(function ($) {



    //Export csv code




    let export_csv_btn = jQuery('#export_csv_btn');
    if (export_csv_btn.length > 0) {

        export_csv_btn.on('click', function (e) {
            const table = document.getElementById("order_confirmation");
            const rows = table.querySelectorAll("tr");
            let csv = [];

            rows.forEach(row => {
                const cols = row.querySelectorAll("th, td");
                let rowData = [];
                cols.forEach(col => {
                    // Remove newlines and commas from cell data
                    let text = col.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/,/g, " ");
                    rowData.push('"' + text.trim() + '"');
                });
                csv.push(rowData.join(","));
            });

            const csvContent = csv.join("\n");
            const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.setAttribute("download", "gift_cards_export.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    //Order search bar code
    
    const orderSearch = document.getElementById('order-confirmation-search');
    if (orderSearch) {
        orderSearch.addEventListener('keyup', function () {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.order_confirmation tbody tr');

            rows.forEach(function (row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }


    //Datatable code

    if (!$.fn.DataTable) {
        console.log('NOT here');
    }

    $.fn.DataTable.ext.pager.numbers_length = 5;

    var shopTable = $('.order_confirmation').DataTable({
        dom: '<"top">rt<"bottom"lip>',
        pageLength: 5,
        lengthMenu: [5, 25, 50, 100],
        order: [[2, 'asc']],
        paging: true,
        pagingType: "full_numbers",
        responsive: true,
        scrollX: true,
        columnDefs: [
            { orderable: false, targets: [0, 6] },
            { searchable: false, targets: [0] }
        ],
        language: {
            search: "",
            searchPlaceholder: "Search by name or ID...",
            lengthMenu: "Show _MENU_ entries",
            zeroRecords: "No matching entries found",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                previous: "‹",
                next: "›",
                first: "«",
                last: "»"
            }
        },
        drawCallback: function () {
            var pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
            var pageInfo = this.api().page.info();
            var currentPage = pageInfo.page + 1;
            var totalPages = pageInfo.pages;

            // Clean up old ellipses
            pagination.find('.ellipsis').remove();

            if (totalPages > 7) {
                pagination.find('.paginate_button').each(function () {
                    var pageNum = parseInt($(this).text(), 10);
                    if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                        $(this).hide(); // Hide middle pages
                    }
                });

                // Insert ellipsis before the last page
                if (currentPage < totalPages - 2) {
                    $('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                }

                // Insert ellipsis after the first page
                if (currentPage > 3) {
                    $('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                }
            }
        },
        initComplete: function () {
            $('.dataTables_length select').addClass('results-per-page');
        }
    });
    window.addEventListener('message', function(event) {

        if (!event.origin.includes('monday.com')) {
            return;
        }

        var data = event.data;
        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch(e) {
            }
        } else {
        }

        var iframe = document.querySelector('.customer-support-section iframe[src*="monday.com"]');
        if (!iframe) {
            return;
        }

        if (typeof data === 'object' && data !== null) {

            if (data.type === 'resize' && data.height) {
                iframe.style.height = data.height + 'px';
                iframe.style.maxHeight = data.height + 'px';
            }

            if (data.type === 'submit_success' || data.type === 'form_submitted' || data.submitted === true) {
                iframe.classList.add('form-submitted');
                iframe.closest('.customer-support-section').classList.add('form-submitted');
                // Override inline styles directly since WPBakery sets max-height inline
                iframe.style.height = '200px';
                iframe.style.maxHeight = '200px';
                iframe.style.minHeight = '600px';
            }
        } else {
        }
    });
});
