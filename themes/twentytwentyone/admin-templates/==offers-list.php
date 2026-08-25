<?php
/**
 * Template for Offers Listing Page
 * Displays all offers in a table format with search, pagination, and export functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get search query
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Query arguments
$args = [
    'post_type' => 'offer',
    'posts_per_page' => $per_page,
    'offset' => $offset,
    'post_status' => 'any',
    'orderby' => 'date',
    'order' => 'DESC',
];

// Add search if provided
if (!empty($search_query)) {
    $args['s'] = $search_query;
}

// Get offers
$offers_query = new WP_Query($args);
$offers = $offers_query->posts;
$total_offers = $offers_query->found_posts;
$total_pages = ceil($total_offers / $per_page);

// Helper function to get offer status
function get_offer_status($offer, $meta) {
    $current_time = current_time('mysql');
    $start_date = $meta['start_date'] ?? '';
    $end_date = $meta['end_date'] ?? '';
    $is_always_on = $meta['always_on'] ?? false;
    $post_status = $offer->post_status;
    
    // If post is draft or pending, return Pending
    if ($post_status === 'draft' || $post_status === 'pending') {
        return 'Pending';
    }
    
    // If always on, return Active
    if ($is_always_on) {
        return 'Active';
    }
    
    // Check date range
    if (!empty($start_date) && $current_time < $start_date) {
        return 'Pending';
    }
    
    if (!empty($end_date) && $current_time > $end_date) {
        return 'Deactivated';
    }
    
    // If within date range or no dates set
    if ((empty($start_date) || $current_time >= $start_date) && 
        (empty($end_date) || $current_time <= $end_date)) {
        return 'Active';
    }
    
    return 'Pending';
}

// Helper function to format date
function format_offer_date($date_string) {
    if (empty($date_string)) {
        return '-';
    }
    $date = strtotime($date_string);
    return date('d/m/Y', $date);
}

// Helper function to get product SKUs and names
function get_offer_products_info($products, $all_products) {
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

// Helper function to get user type display
function get_user_type_display($audience) {
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

<div class="wrap offers-listing-page">
    <!-- Header Section -->
    <div class="offers-header-section">
        <h1 class="wp-heading-inline">All Plus Offers</h1>
        
        <div class="offers-actions">
            <?php
            // Get frontend create page
            $create_page = get_page_by_path('create-offer');
            if ($create_page) {
                echo '<a href="' . esc_url(get_permalink($create_page->ID)) . '" class="page-title-action">Add new</a>';
            } else {
                echo '<a href="' . admin_url('admin.php?page=create-offer') . '" class="page-title-action">Add new</a>';
            }
            ?>
            <button type="button" class="page-title-action" id="export-offers-btn">Export offers</button>
        </div>
    </div>
    
    <!-- Search Section -->
    <div class="offers-search-section">
        <form method="get" action="" class="search-form">
            <input type="hidden" name="page" value="offers">
            <input type="search" 
                   name="s" 
                   id="offers-search-input" 
                   placeholder="Search Offer ID, Title" 
                   value="<?php echo esc_attr($search_query); ?>"
                   class="search-input">
            <button type="submit" class="search-submit">
                <span class="dashicons dashicons-search"></span>
            </button>
            <?php if (!empty($search_query)): ?>
                <a href="<?php echo admin_url('admin.php?page=offers'); ?>" class="clear-search">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Messages -->
    <?php
    if (isset($_GET['offer_deleted']) && $_GET['offer_deleted'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>Offer deleted successfully.</p></div>';
    }
    if (isset($_GET['offer_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['offer_error'])) . '</p></div>';
    }
    ?>
    
    <!-- Offers Table -->
    <table class="wp-list-table widefat fixed striped offers-table">
        <thead>
            <tr>
                <th class="sortable">
                    <span>Offer ID</span>
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable">
                    <span>Offer Title</span>
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable">
                    <span>Offer Description</span>
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable">
                    <span>Status</span>
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable">
                    <span>Start date</span>
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable">
                    <span>End date</span>
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable">
                    <span>Rank</span>
                    <span class="sort-indicator"></span>
                </th>
                <th>
                    <span>SKU Assigned</span>
                </th>
                <th>
                    <span>Product Names Assigned</span>
                </th>
                <th class="sortable">
                    <span>User Type</span>
                    <span class="sort-indicator"></span>
                </th>
                <th>
                    <span>Details</span>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($offers)): ?>
                <tr>
                    <td colspan="11" class="no-offers">No offers found.</td>
                </tr>
            <?php else: ?>
                <?php 
                $rank = $offset + 1;
                foreach ($offers as $offer): 
                    $meta = get_offer_meta($offer->ID);
                    $status = get_offer_status($offer, $meta);
                    $products_info = get_offer_products_info($meta['products'], $meta['all_products']);
                    $user_type = get_user_type_display($meta['audience']);
                    
                    // Get edit page URL
                    $edit_page = get_page_by_path('edit-offer');
                    if ($edit_page) {
                        $edit_url = add_query_arg('offer_id', $offer->ID, get_permalink($edit_page->ID));
                    } else {
                        $edit_url = admin_url('admin.php?page=offers&action=edit&offer_id=' . $offer->ID);
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html($offer->ID); ?></td>
                        <td><strong><?php echo esc_html($offer->post_title); ?></strong></td>
                        <td><?php echo esc_html(wp_trim_words($meta['description'], 20)); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($status); ?>">
                                <?php echo esc_html($status); ?>
                            </span>
                        </td>
                        <td><?php echo format_offer_date($meta['start_date']); ?></td>
                        <td><?php echo format_offer_date($meta['end_date']); ?></td>
                        <td><?php echo esc_html($rank); ?></td>
                        <td><?php echo esc_html($products_info['skus']); ?></td>
                        <td><?php echo esc_html($products_info['names']); ?></td>
                        <td><?php echo esc_html($user_type); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>">View/Edit</a>
                        </td>
                    </tr>
                <?php 
                    $rank++;
                endforeach; 
                ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                $page_links = paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'type' => 'plain',
                ]);
                
                if ($page_links) {
                    echo '<span class="displaying-num">' . sprintf(
                        _n('%s item', '%s items', $total_offers),
                        number_format_i18n($total_offers)
                    ) . '</span>';
                    echo $page_links;
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Export offers functionality
    $('#export-offers-btn').on('click', function() {
        // You can implement CSV export here
        alert('Export functionality to be implemented');
    });
});
</script>

