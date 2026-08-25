jQuery(document).ready(function ($) {
    const $listView = $('#product-list-view-review');
    const $thumbnailView = $('#review-product-thumbnail-view');
    const $listBtn = $('#review-list-view-btn');
    const $thumbBtn = $('#review-thumbnail-view-btn');
    const $grid = $('#review-thumbnail-grid');
    const $pagination = $('#review-thumbnail-pagination');

    let currentPage = 1;

    // Default view
    $thumbnailView.hide();
    $listView.show();

    // List View
    $listBtn.on('click', function () {
        $listBtn.addClass('active');
        $thumbBtn.removeClass('active');
        $thumbnailView.hide();
        $listView.show();
    });

    // Thumbnail View
    $thumbBtn.on('click', function () {
        $thumbBtn.addClass('active');
        $listBtn.removeClass('active');
        $listView.hide();
        $thumbnailView.show();
        loadThumbnails(1);
    });

    // AJAX Loader
    function loadThumbnails(page = 1) {
        $.ajax({
            url: reviewajax.ajax_url,
            method: 'POST',
            data: {
                action: 'load_thumbnail_view_review',
                page: page
            },
            beforeSend: function () {
                $grid.html('<p>Loading...</p>');
            },
            success: function (response) {
                if (response.success) {
                    $grid.html(response.data.html);
                    $pagination.html(response.data.pagination);
                    currentPage = page;
                } else {
                    $grid.html('<p>Error loading thumbnails.</p>');
                }
            },
            error: function () {
                $grid.html('<p>AJAX request failed.</p>');
            }
        });
    }

    // Pagination click
    $(document).on('click', '.pagination button[data-page]', function () {
        const page = parseInt($(this).data('page'));
        loadThumbnails(page);
    });
});
