<?php
/**
 * Template Name: Offers List Table
 * 
 * Frontend page template for listing all offers in a table format
 * Similar to admin interface but for frontend display
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// DataTables handles pagination client-side – load all offers
$args = [
    'post_type' => 'offer',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'orderby' => 'date',
    'order' => 'DESC',
];

$offers_query = new WP_Query($args);
$offers = $offers_query->posts;
$total_offers = count($offers);

// Helper function to get offer status (Pending / Active / Deactivated)
function get_offer_status_frontend($offer, $meta)
{
    $current_time = current_time('mysql');
    $start_date = $meta['start_date'] ?? '';
    $end_date = $meta['end_date'] ?? '';
    $is_always_on = $meta['always_on'] ?? false;

    if ($is_always_on) {
        return 'Active';
    }
    if (!empty($start_date) && $current_time < $start_date) {
        return 'Pending';
    }
    if (!empty($end_date) && $current_time > $end_date) {
        return 'Deactivated';
    }
    if ((empty($start_date) || $current_time >= $start_date) && (empty($end_date) || $current_time <= $end_date)) {
        return 'Active';
    }
    return 'Pending';
}

// Helper function to format date
function format_offer_date_frontend($date_string)
{
    if (empty($date_string)) {
        return '-';
    }
    $date = strtotime($date_string);
    return date('d/m/Y', $date);
}

// Helper function to get product SKUs and names
function get_offer_products_info_frontend($products, $all_products)
{
    if ($all_products) {
        return [
            'skus' => 'All SKUs',
            'names' => 'All Products'
        ];
    }

    if (empty($products) || !is_array($products)) {
        return [
            'skus' => '-',
            'names' => '-'
        ];
    }

    $skus = [];
    $names = [];

    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $sku = $product->get_sku();
            if ($sku) {
                $skus[] = $sku;
            }
            $names[] = $product->get_name();
        }
    }

    return [
        'skus' => !empty($skus) ? implode(', ', $skus) : '-',
        'names' => !empty($names) ? implode(', ', $names) : '-'
    ];
}

/**
 * HTML list of products for one offer (multiple products shown as separate lines).
 */
function get_offer_products_list_html_frontend($products, $all_products)
{
    if ($all_products) {
        return '<span class="offers-products-all">' . esc_html('All products') . '</span>';
    }
    if (empty($products) || !is_array($products)) {
        return '<span class="offers-products-empty">' . esc_html('—') . '</span>';
    }
    $items = [];
    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $line = esc_html($product->get_name());
            $sku = $product->get_sku();
            if ($sku) {
                $line .= ' <span class="offer-product-sku">(' . esc_html($sku) . ')</span>';
            }
            $items[] = '<li>' . $line . '</li>';
        }
    }
    if (empty($items)) {
        return '<span class="offers-products-empty">' . esc_html('—') . '</span>';
    }
    return '<ul class="offers-product-list-frontend">' . implode('', $items) . '</ul>';
}


/**
 * Normalize a PURL for deduplication (same landing page, different casing/trailing slash).
 */
function offer_purl_normalize_key_frontend($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '__no_purl__';
    }
    $parsed = wp_parse_url($url);
    if (empty($parsed['host'])) {
        return strtolower($url);
    }
    $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : 'https';
    $host = strtolower((string) $parsed['host']);
    $path = isset($parsed['path']) ? $parsed['path'] : '';
    $path = '/' . trim(untrailingslashit($path), '/');
    if ($path === '/') {
        $path = '';
    }
    $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
    $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? '#' . $parsed['fragment'] : '';
    return $scheme . '://' . $host . $path . $query . $fragment;
}

/**
 * Group offers by personalized URL; sort groups by URL. Offers without a URL are last under one bucket.
 *
 * @return array<int|string, array{canonical_purl: string, offers: array<int, array{offer: WP_Post, meta: array}>}>
 */
function get_offers_by_purl_from_table() {
    global $wpdb;
    $table = "{$wpdb->prefix}offer_links";

    $rows = $wpdb->get_results(
        "SELECT offer_id, offer_link, is_used, used_by, used_at FROM {$table} ORDER BY offer_link ASC"
    );

    if (empty($rows)) {
        return [];
    }

    $buckets = [];

    foreach ($rows as $row) {
        $offer = get_post((int) $row->offer_id);
        if (!$offer || $offer->post_type !== 'offer') {
            continue;
        }

        $meta = get_offer_meta($offer->ID);
        $link = trim((string) $row->offer_link);
        $key  = $link !== '' ? offer_purl_normalize_key_frontend($link) : '__no_purl__';

        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'canonical_purl' => $link,
                'offers'         => [],
            ];
        }

        $buckets[$key]['offers'][] = [
            'offer'   => $offer,
            'meta'    => $meta,
            'is_used' => (bool) $row->is_used,
            'used_by' => $row->used_by,
            'used_at' => $row->used_at,
        ];
    }

    return $buckets;
}

/**
 * Merged product list HTML for all offers in one PURL group (deduped by product ID).
 */
function get_offer_group_products_list_html_frontend(array $offer_entries) {
    $any_all = false;
    $product_ids = [];
    foreach ($offer_entries as $entry) {
        $meta = $entry['meta'];
        if (!empty($meta['all_products'])) {
            $any_all = true;
            break;
        }
        if (!empty($meta['products']) && is_array($meta['products'])) {
            foreach ($meta['products'] as $pid) {
                $product_ids[(int) $pid] = true;
            }
        }
    }
    if ($any_all) {
        return get_offer_products_list_html_frontend([], true);
    }
    return get_offer_products_list_html_frontend(array_keys($product_ids), false);
}

/**
 * Status badge HTML for a PURL bucket.
 * Shows "Used" if every entry is used, "Available" if all are available,
 * or "Partially Used" if mixed.
 */
function get_purl_status_badge_html(array $offer_entries) {
    $total    = count($offer_entries);
    $used     = 0;

    foreach ($offer_entries as $entry) {
        if (!empty($entry['is_used'])) {
            $used++;
        }
    }

    if ($used === 0) {
        return '<span class="offers-status-badge status-active">Available</span>';
    }
    if ($used === $total) {
        return '<span class="offers-status-badge status-deactivated">Used</span>';
    }
    return '<span class="offers-status-badge status-pending">Partially Used</span>';
}

/**
 * HTML: each related offer with ID, title, status, optional edit link.
 */
function render_purl_related_offers_html_frontend(array $offer_entries) {
    $edit_page = get_page_by_path('edit-offer');
    $can_edit = $edit_page && (current_user_can('manage_options') || current_user_can('edit_offers'));
    $edit_base = $can_edit ? get_permalink($edit_page->ID) : '';

    $items = [];
    foreach ($offer_entries as $entry) {
        $offer = $entry['offer'];
        $meta = $entry['meta'];
        $status = get_offer_status_frontend($offer, $meta);
        $status_class = esc_attr(strtolower($status));
        $line = '<span class="offers-purl-related-id">#' . esc_html((string) $offer->ID) . '</span> ';
        $line .= '<strong class="offers-purl-related-title">' . esc_html($offer->post_title) . '</strong> ';
        $line .= '<span class="offers-status-badge status-' . $status_class . '">' . esc_html($status) . '</span>';
        if ($can_edit && $edit_base) {
            $href = esc_url(add_query_arg('offer_id', $offer->ID, $edit_base));
            $line .= ' <a href="' . $href . '" class="offers-purl-related-edit">' . esc_html('View/Edit') . '</a>';
        }
        $items[] = '<li>' . $line . '</li>';
    }
    return '<ul class="offers-purl-related-list-frontend">' . implode('', $items) . '</ul>';
}

// Helper function to get user type display
function get_user_type_display_frontend($audience)
{
    if (empty($audience)) {
        return '-';
    }

    $audience_map = [
        'consumer' => 'Consumer',
        'business' => 'Business',
        'both' => 'Consumer & Business',
        'consumer_business' => 'Consumer & Business',
    ];

    return $audience_map[strtolower($audience)] ?? ucfirst($audience);
}
?>

<?php
$current_url = get_permalink();
// if (isset($_GET['page_id'])) {
//     $current_url = add_query_arg('page_id', (int) $_GET['page_id'], $current_url);
// }
$offers_by_purl = get_offers_by_purl_from_table();
?>
<div class="offers-listing-page-frontend">
    <div class="container">
        <h1 class="offers-page-title-frontend">All Plus Offers</h1>

        <div class="offers-tabs-frontend" role="tablist" aria-label="Offers views">
            <button type="button" role="tab" id="offers-tab-all" class="offers-tab-btn-frontend is-active"
                aria-selected="true" aria-controls="offers-tabpanel-all" data-offers-tab="all">All offers</button>
            <button type="button" role="tab" id="offers-tab-purl" class="offers-tab-btn-frontend" aria-selected="false"
                aria-controls="offers-tabpanel-purl" data-offers-tab="purl" tabindex="-1">Personalized URLs</button>
        </div>

        <div id="offers-tabpanel-all" class="offers-tabpanel-frontend is-active" role="tabpanel"
            aria-labelledby="offers-tab-all" data-offers-tabpanel="all">
            <!-- Top bar: Search (left) + Add new / Export (right) -->
            <div class="offers-top-bar-frontend">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="offers-search-input-frontend"
                        class="search-input offers-search-input-frontend" placeholder="Search Offer ID, Title"
                        aria-controls="offers-table-frontend">
                </div>
                <div class="offers-actions-frontend">
                    <?php
                    if (current_user_can('manage_options') || current_user_can('edit_offers')) {
                        $create_page = get_page_by_path('create-offer');
                        if ($create_page) {
                            echo '<a href="' . esc_url(get_permalink($create_page->ID)) . '" class="btn-add-new-frontend btn-black-white btn-primary-black">Add new</a>';
                        }
                    }
                    ?>
                    <button type="button" class="btn-export-frontend btn-black-white btn-primary-white" id="export-offers-btn-frontend">
                        <svg class="btn-import-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="17 14 12 9 7 14" />
                            <line x1="12" y1="9" x2="12" y2="21" />
                        </svg>
                        <span>Export offers</span>
                    </button>
                    <button type="button" class="btn-black-white btn-primary-white import-offers" id="import-offers-btn">
                        <svg class="btn-export-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        <span>Import Offers</span>
                    </button>
                    <input type="file" id="import-offers-csv-input" accept=".csv" style="display:none;"><?php // hidden file picker ?>
                </div>
            </div>

            <!-- Offers Table (DataTables) -->
            <div class="offers-table-wrapper-frontend">
                <table id="offers-table-frontend" class="offers-table-frontend display" style="width:100%">
                    <thead>
                        <tr>
                            <th>Offer ID <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>Offer Title <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>Offer Description <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>Status <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>Start date <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>End date <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>Rank <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>SKU Assigned</th>
                            <th>Product Names Assigned <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>User Type <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                            <th>Details <i class="fa-solid fa-arrow-down col-icon-2"></i> </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($offers)): ?>
                            <?php
                            $rank = 1;
                            foreach ($offers as $offer):
                                $meta = get_offer_meta($offer->ID);
                                $status = get_offer_status_frontend($offer, $meta);
                                $products_info = get_offer_products_info_frontend($meta['products'], $meta['all_products']);
                                $user_type = get_user_type_display_frontend($meta['audience']);

                                $edit_page = get_page_by_path('edit-offer');
                                if ($edit_page && (current_user_can('manage_options') || current_user_can('edit_offers'))) {
                                    $details_url = add_query_arg('offer_id', $offer->ID, get_permalink($edit_page->ID));
                                } else {
                                    $details_url = !empty($meta['link']) ? $meta['link'] : '#';
                                }
                                ?>
                                <tr>
                                    <td><?php echo esc_html($offer->ID); ?></td>
                                    <td><strong><?php echo esc_html($offer->post_title); ?></strong></td>
                                    <td><?php echo esc_html(wp_trim_words($meta['description'], 20)); ?></td>
                                    <td>
                                        <span
                                            class="offers-status-badge status-<?php echo esc_attr(strtolower($status)); ?>"><?php echo esc_html($status); ?></span>
                                    </td>
                                    <td><?php echo format_offer_date_frontend($meta['start_date']); ?></td>
                                    <td><?php echo format_offer_date_frontend($meta['end_date']); ?></td>
                                    <td><?php echo esc_html($rank); ?></td>
                                    <td><?php echo esc_html($products_info['skus']); ?></td>
                                    <td><?php echo esc_html($products_info['names']); ?></td>
                                    <td><?php echo esc_html($user_type); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($details_url); ?>" class="offers-details-link">View/Edit</a>
                                    </td>
                                </tr>
                                <?php
                                $rank++;
                            endforeach;
                            ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="offers-tabpanel-purl" class="offers-tabpanel-frontend" role="tabpanel" aria-labelledby="offers-tab-purl" data-offers-tabpanel="purl" hidden>
            <div class="offers-top-bar-frontend offers-top-bar-purl-frontend">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="offers-purl-search-input-frontend" class="search-input offers-search-input-frontend" placeholder="Search URL, offer ID, title" aria-controls="offers-purl-table-frontend">
                </div>
            </div>
            <div class="offers-table-wrapper-frontend">
                <table id="offers-purl-table-frontend" class="offers-table-frontend offers-purl-table-frontend display" style="width:100%">
                    <thead>
                        <tr>
                            <th>Personalized URL <i class="fa-solid fa-arrow-down col-icon-2"></i></th>
                            <th>Related offers</th>
                            <th>Products</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($offers_by_purl)): ?>
                            <?php foreach ($offers_by_purl as $purl_key => $purl_bucket): ?>
                                <?php
                                $canonical = $purl_bucket['canonical_purl'];
                                $entries = $purl_bucket['offers'];
                                $products_html = get_offer_group_products_list_html_frontend($entries);
                                $related_html = render_purl_related_offers_html_frontend($entries);
                                $allowed_product_kses = [
                                    'ul' => ['class' => true],
                                    'li' => [],
                                    'span' => ['class' => true],
                                ];
                                $allowed_related_kses = [
                                    'ul' => ['class' => true],
                                    'li' => [],
                                    'span' => ['class' => true],
                                    'strong' => ['class' => true],
                                    'a' => ['href' => true, 'class' => true],
                                ];
                                ?>
                                <tr>
                                    <td class="offers-purl-url-cell">
                                        <?php if ($purl_key === '__no_purl__' || $canonical === ''): ?>
                                            <span class="offers-purl-missing"><?php echo esc_html('No personalized URL assigned'); ?></span>
                                        <?php else: ?>
                                            <a href="<?php echo esc_url($canonical); ?>" class="offers-purl-link" target="_blank" rel="noopener noreferrer"><?php echo esc_html($canonical); ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="offers-purl-related-cell"><?php echo wp_kses($related_html, $allowed_related_kses); ?></td>
                                    <td class="offers-purl-products-cell"><?php echo wp_kses($products_html, $allowed_product_kses); ?></td>
                                    <td class="offers-purl-status-cell"><?php echo wp_kses(get_purl_status_badge_html($entries), ['span' => ['class' => true]]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function ($) {

        var purlTable = null;

        function initPurlDataTable() {
            if (purlTable) {
                purlTable.columns.adjust();
                return;
            }
            purlTable = $('#offers-purl-table-frontend').DataTable({
                dom: '<"top">rt<"bottom offers-dt-pagination-wrap"p>',
                paging: true,
                searching: true,
                ordering: true,
                responsive: true,
                scrollX: true,
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
                pagingType: 'full_numbers',
                language: {
                    search: '',
                    searchPlaceholder: 'Search URL, offer ID, title',
                    paginate: {
                        previous: '‹',
                        next: '›',
                        first: '«',
                        last: '»'
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [1, 2] },
                    { searchable: false, targets: [2, 3] }
                ],
                order: [[0, 'asc']],
                drawCallback: function () {
                    var pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
                    var pageInfo = this.api().page.info();
                    var currentPage = pageInfo.page + 1;
                    var totalPages = pageInfo.pages;
                    pagination.find('.ellipsis').remove();
                    if (totalPages > 7) {
                        pagination.find('.paginate_button').each(function () {
                            var pageNum = parseInt($(this).text(), 10);
                            if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                                $(this).hide();
                            }
                        });
                        if (currentPage < totalPages - 2) {
                            $('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                        }
                        if (currentPage > 3) {
                            $('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                        }
                    }
                },
            });
            $('#offers-purl-search-input-frontend').on('keyup', function() {
                purlTable.search(this.value).draw();
            });
        }


        // Tab switching: All offers vs Personalized URLs
        var $tabBtns = $('.offers-tab-btn-frontend');
        var $panels = $('.offers-tabpanel-frontend');
        $tabBtns.on('click', function() {
            var tab = $(this).data('offers-tab');
            $tabBtns.removeClass('is-active').attr('aria-selected', 'false').attr('tabindex', '-1');
            $(this).addClass('is-active').attr('aria-selected', 'true').attr('tabindex', '0');
            $panels.removeClass('is-active').attr('hidden', true);
            var $panel = $panels.filter('[data-offers-tabpanel="' + tab + '"]');
            $panel.addClass('is-active').removeAttr('hidden');
            if (tab === 'purl') {
                initPurlDataTable();
            }
        });
        // Initialize DataTables: 10 rows per page, client-side pagination
        var offersTable = $('#offers-table-frontend').DataTable({
            dom: '<"top">rt<"bottom offers-dt-pagination-wrap"p>',
            paging: true,
            searching: true,
            ordering: true,
            responsive: true,
            scrollX: true,
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
            pagingType: "full_numbers",
            language: {
                search: "",
                searchPlaceholder: "Search Offer ID, Title",
                paginate: {
                    previous: "‹",
                    next: "›",
                    first: "«",
                    last: "»"
                }
            },
            columnDefs: [
                { orderable: false, targets: 10 },
                { searchable: false, targets: 10 }
            ],
            order: [[0, 'asc']],
            drawCallback: function () {
                var pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
                var pageInfo = this.api().page.info();
                var currentPage = pageInfo.page + 1;
                var totalPages = pageInfo.pages;
                pagination.find('.ellipsis').remove();
                if (totalPages > 7) {
                    pagination.find('.paginate_button').each(function () {
                        var pageNum = parseInt($(this).text(), 10);
                        if (pageNum > 2 && pageNum < totalPages - 1 && pageNum !== currentPage) {
                            $(this).hide();
                        }
                    });
                    if (currentPage < totalPages - 2) {
                        $('<span class="ellipsis">...</span>').insertBefore(pagination.find('.paginate_button:last'));
                    }
                    if (currentPage > 3) {
                        $('<span class="ellipsis">...</span>').insertAfter(pagination.find('.paginate_button:first'));
                    }
                }
            },
        });

        // Custom search input -> DataTables search
        $('#offers-search-input-frontend').on('keyup', function () {
            offersTable.search(this.value).draw();
        });

        // Import offers
        $('#import-offers-btn').on('click', function () {
            $('#import-offers-csv-input').val('').trigger('click');
        });

        $('#import-offers-csv-input').on('change', function () {
            var file = this.files[0];
            if (!file) return;

            var formData = new FormData();
            formData.append('action', 'import_offers_csv');
            formData.append('nonce', '<?php echo wp_create_nonce('import_offers_nonce'); ?>');
            formData.append('csv_file', file);

            var $btn = $('#import-offers-btn');
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).find('span').text('Importing…');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (response.success) {
                        var d = response.data;
                        var msg = 'Import complete: ' + d.created + ' created, ' + d.updated + ' updated.';
                        if (d.errors && d.errors.length) {
                            msg += '\n\nWarnings:\n' + d.errors.join('\n');
                        }
                        alert(msg);
                        if (d.created > 0 || d.updated > 0) {
                            location.reload();
                        }
                    } else {
                        alert('Import failed: ' + (response.data || 'Unknown error.'));
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                    alert('Import failed due to a network or server error.');
                },
            });
        });

        // Export offers
        $('#export-offers-btn-frontend').on('click', function () {
            var searchQuery = $('#offers-search-input-frontend').val();
            var exportUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=export_offers';
            if (searchQuery) {
                exportUrl += '&s=' + encodeURIComponent(searchQuery);
            }
            window.location.href = exportUrl;
        });
    });
</script>

<?php get_footer(); ?>