jQuery(document).ready(function($) {
    console.log('Loaded...');
    
    var table = $('#my-orders-table').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        columnDefs: [
            { targets: [0], visible: false }, // hide timestamp column
            { targets: [3,4], orderable: false }
        ]
    });

    
    // Connect your custom search input to DataTables search
    $('.search-input').on('keyup', function () {
        table.search(this.value).draw();
    });
});