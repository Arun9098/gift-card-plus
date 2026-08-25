 <?php
    /**
     * Template Name: Persona Page
     * Description: A page template that displays product tags as persona cards with filtering
     *
     * Features:
     * - Horizontal slider of all product tags (personas)
     * - Click to filter products by selected tag
     * - Search functionality within filtered products
     * - Sort options (Name, Price, Date)
     */

    // Handle AJAX request for products - MUST be before get_header()
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'products') {
        $tag = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        if ($tag) {
            $args['tax_query'] = [['taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => $tag]];
        }

        // Search only in product titles (partial match)
        if ($search) {
            $args['s'] = $search;
            add_filter('posts_search', function ($search_sql, $wp_query) {
                global $wpdb;
                $search_term = $wp_query->get('s');
                if ($search_term) {
                    $like = '%' . $wpdb->esc_like($search_term) . '%';
                    $search_sql = " AND ({$wpdb->posts}.post_title LIKE '$like') ";
                }
                return $search_sql;
            }, 10, 2);
        }

        switch ($sort) {
            case 'name':
                $args['orderby'] = 'title';
                $args['order'] = $order ?: 'ASC';
                break;
            case 'price':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = $order ?: 'ASC';
                break;
            default:
                $args['orderby'] = 'date';
                $args['order'] = $order ?: 'DESC';
        }

        $query = new WP_Query($args);

        if ($query->have_posts()):
            while ($query->have_posts()): $query->the_post();
                global $product;
                $product_id = get_the_ID();
                $product_title = get_the_title();
                $product_price = $product->get_price_html();
                $product_image = get_the_post_thumbnail_url($product_id, 'medium');
                $product_link = get_permalink($product_id);
                $product_tags = get_the_terms($product_id, 'product_tag');
                $tag_badges = [];
                if ($product_tags && !is_wp_error($product_tags)) {
                    foreach ($product_tags as $ptag) {
                        $tag_badges[] = $ptag->name;
                    }
                }
                $product_icons = get_the_terms($product_id, 'icons');
    ?>
             <div class="persona-product-card card gc-card ">
                 <a href="<?php echo esc_url($product_link); ?>" class="persona-product-link">
                     <div class="persona-product-image gc-img">
                         <?php if ($product_image): ?>
                             <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_title); ?>">
                         <?php else: ?>
                             <div class="persona-product-placeholder"><span><?php echo esc_html(substr($product_title, 0, 1)); ?></span></div>
                         <?php endif; ?>
                     </div>
                     <div class="persona-product-content">
                         <h3 class="persona-product-title gc-title"><?php echo esc_html($product_title); ?></h3>
                         <?php if (!empty($tag_badges)):
                                $visible_tags = array_slice($tag_badges, 0, 2);
                                $hidden_tags  = array_slice($tag_badges, 2);
                            ?>
                             <p class="gc-product-tags product-tags-wrap">
                                 <?php foreach ($visible_tags as $badge): ?>
                                     <span class="product-tag product-tag--default" title="<?php echo esc_attr($badge); ?>"><?php echo esc_html($badge); ?></span>
                                 <?php endforeach; ?>
                                 <?php if (!empty($hidden_tags)): ?>
                                     <span class="product-tag product-tag--more" tabindex="0">&hellip;</span>
                                     <span class="product-tag-tooltip">
                                         <?php foreach ($hidden_tags as $badge): ?>
                                             <span class="product-tag product-tag--default" title="<?php echo esc_attr($badge); ?>"><?php echo esc_html($badge); ?></span>
                                         <?php endforeach; ?>
                                     </span>
                                 <?php endif; ?>
                             </p>
                         <?php endif; ?>
                         <?php if ($product_icons && !is_wp_error($product_icons)): ?>
                             <p class="product-icons">
                                 <?php foreach ($product_icons as $icon): ?>
                                     <span class="icon icon-<?php echo esc_attr($icon->slug); ?>" title="<?php echo esc_attr($icon->name); ?>"><?php echo esc_html($icon->name); ?></span>
                                 <?php endforeach; ?>
                             </p>
                         <?php endif; ?>
                         <!-- <div class="persona-product-price"><?php //echo $product_price; ?></div> -->
                     </div>
                 </a>
             </div>
         <?php
            endwhile;
        else:
            ?>
         <div class="persona-no-products">
             <p>No cards found<?php echo $search ? ' for your search' : ''; ?>.</p>
             <?php if ($search): ?>
                 <button onclick="clearSearchAjax()" class="clear-search-btn">Clear search</button>
             <?php endif; ?>
         </div>
 <?php
        endif;
        wp_reset_postdata();
        // Remove the filter after query
        remove_all_filters('posts_where');
        exit; // Stop here for AJAX - don't load header/footer
    }

    get_header();

    // Get tags in the order configured under the Gift Item Slider admin tab
    $gift_item_slider_ids = get_option('gc_gift_item_slider_selected_tags', array());
    $all_tags = gc_get_ordered_tags_by_option($gift_item_slider_ids);

    // Default placeholder image URL (set this to your default persona image)
    $default_persona_image = ''; // e.g., 'https://yoursite.com/wp-content/uploads/default-persona.jpg'

    // Get selected tag from URL parameter
    $selected_tag_slug = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';
    $selected_tag_name = '';

    if ($selected_tag_slug) {
        $selected_tag = get_term_by('slug', $selected_tag_slug, 'product_tag');
        if ($selected_tag && !is_wp_error($selected_tag)) {
            $selected_tag_name = $selected_tag->name;
        }
    }

    // Search query
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Sort parameter
    $sort_by = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date';
    $sort_order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';

    // ACF field checks for section visibility
    $enable_persona_carousel = get_field('enable_persona_carousel');
    $enable_giftcards_section = get_field('enable_giftcards_section');
    $enable_need_help_panel = get_field('enable_need_help_panel');

    // Set up product query args
    $product_args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];

    // Apply tag filter if selected
    if ($selected_tag_slug) {
        $product_args['tax_query'] = [
            [
                'taxonomy' => 'product_tag',
                'field' => 'slug',
                'terms' => $selected_tag_slug,
            ],
        ];
    }

    // Search only in product titles (partial match)
    if ($search_query) {
        $product_args['s'] = $search_query;
        add_filter('posts_search', function ($search_sql, $wp_query) {
            global $wpdb;
            $search_term = $wp_query->get('s');
            if ($search_term) {
                $like = '%' . $wpdb->esc_like($search_term) . '%';
                $search_sql = " AND ({$wpdb->posts}.post_title LIKE '$like') ";
            }
            return $search_sql;
        }, 10, 2);
    }

    // Apply sorting
    switch ($sort_by) {
        case 'name':
            $product_args['orderby'] = 'title';
            $product_args['order'] = $sort_order ?: 'ASC';
            break;
        case 'price':
            $product_args['orderby'] = 'meta_value_num';
            $product_args['meta_key'] = '_price';
            $product_args['order'] = $sort_order ?: 'ASC';
            break;
        case 'date':
        default:
            $product_args['orderby'] = 'date';
            $product_args['order'] = $sort_order ?: 'DESC';
            break;
    }

    $products_query = new WP_Query($product_args);
    ?>

 <div class="persona-page-wrapper ">
     <div class="choose-your-persona-section section-spacing">
         <div class="container">
             <div class="choose-persona-wrapper">


                 <!-- Breadcrumb -->
                 <div class="persona-breadcrumb woocommerce-breadcrumb" aria-label="Breadcrumb">

                     <a href="<?php echo esc_url(home_url('/')); ?>">
                         <span class="breacrum-icon">
                             <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                 <rect x="24" width="24" height="24" rx="12" transform="rotate(90 24 0)" fill="white" />
                                 <path fill-rule="evenodd" clip-rule="evenodd" d="M5.45703 12.0012C5.45703 11.8083 5.53367 11.6233 5.67007 11.4869C5.80647 11.3505 5.99147 11.2738 6.18438 11.2738L19.2765 11.2738C19.4694 11.2738 19.6544 11.3505 19.7908 11.4869C19.9272 11.6233 20.0039 12.0012 20.0039 12.0012C20.0039 12.1941 19.9272 12.3791 19.7908 12.5155C19.6544 12.6519 19.4694 12.7285 19.2765 12.7285L6.18438 12.7285C5.99147 12.7285 5.80647 12.6519 5.67007 12.5155C5.53367 12.3791 5.45703 12.1941 5.45703 12.0012Z" fill="black" fill-opacity="0.6" />
                                 <path fill-rule="evenodd" clip-rule="evenodd" d="M4.21369 12.5154C4.14595 12.4479 4.09221 12.3676 4.05554 12.2792C4.01888 12.1909 4 12.0961 4 12.0005C4 11.9048 4.01888 11.8101 4.05554 11.7217C4.09221 11.6333 4.14595 11.5531 4.21369 11.4855L8.57773 7.12146C8.71431 6.98488 8.89955 6.90815 9.09269 6.90815C9.28584 6.90815 9.47107 6.98488 9.60765 7.12145C9.74423 7.25803 9.82095 7.44327 9.82095 7.63641C9.82095 7.82956 9.74423 8.0148 9.60765 8.15137L5.75711 12.0005L9.60765 15.8496C9.74423 15.9861 9.82096 16.1714 9.82096 16.3645C9.82096 16.5577 9.74423 16.7429 9.60765 16.8795C9.47108 17.016 9.28584 17.0928 9.09269 17.0928C8.89955 17.0928 8.71431 17.016 8.57774 16.8795L4.21369 12.5154Z" fill="black" fill-opacity="0.6" />
                             </svg>
                         </span>
                         Home
                     </a>

                     <span class="breadcrumb-separator"> <svg width="21" height="24" viewBox="0 0 21 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                             <path d="M7.9502 13.4746C7.9502 14.1764 8.20085 14.778 8.70215 15.2793C9.20345 15.776 9.80501 16.0244 10.5068 16.0244C11.2087 16.0244 11.8102 15.776 12.3115 15.2793C12.8083 14.778 13.0566 14.1764 13.0566 13.4746C13.0566 12.7728 12.8083 12.1735 12.3115 11.6768C11.8102 11.1755 11.2087 10.9248 10.5068 10.9248C9.80501 10.9248 9.20345 11.1755 8.70215 11.6768C8.20085 12.1735 7.9502 12.7728 7.9502 13.4746Z" fill="black" fill-opacity="0.6" />
                         </svg>
                     </span>

                     <span class="breadcrumb-current">
                         Choose Your Persona
                     </span>

                 </div>
                 <!-- Page Title -->
                 <h1 class="persona-page-title">
                     Choose your <span class="font-ephesis">persona</em>
                 </h1>

                 <?php if ($enable_persona_carousel): ?>
                     <!-- Tags Slider Section -->
                     <div class="persona-tags-section">
                         <div class="persona-tags-slider" id="personaTagsSlider">
                             <?php if (!empty($all_tags) && !is_wp_error($all_tags)): ?>
                                 <?php foreach ($all_tags as $tag):
                                        $is_active = ($selected_tag_slug === $tag->slug);
                                        $tag_count = $tag->count;

                                        $image_id = get_term_meta($tag->term_id, 'tag-image-id', true);
                                        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src();
                                    ?>
                                     <div class="persona-tag-card <?php echo $is_active ? 'active' : ''; ?>"
                                         data-tag-slug="<?php echo esc_attr($tag->slug); ?>">
                                         <a href="?tag=<?php echo esc_attr($tag->slug); ?>" class="persona-tag-link">
                                             <div class="persona-tag-image">
                                                 <?php if ($image_url) : ?>
                                                     <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($tag->name); ?>">
                                                 <?php else : ?>
                                                     <div class="persona-tag-placeholder">
                                                         <span><?php echo esc_html(strtoupper(substr($tag->name, 0, 1))); ?></span>
                                                     </div>
                                                 <?php endif; ?>
                                             </div>
                                             <div class="persona-tag-content">
                                                 <h3 class="persona-tag-name"><?php echo esc_html($tag->name); ?></h3>
                                                 <!-- <p class="persona-tag-count"><?php /*echo esc_html($tag_count); */ ?> cards</p> -->
                                             </div>
                                         </a>
                                     </div>
                                 <?php endforeach; ?>
                             <?php else: ?>
                                 <p class="no-tags-message">No personas available.</p>
                             <?php endif; ?>
                         </div>

                     </div>
                 <?php endif; ?>
             </div>
         </div>
     </div>


     <?php if ($selected_tag_slug && $enable_giftcards_section): ?>
     <div class="adventure-seeker-section section-spacing light-blue-section">
         <div class="container">
             <div class="adventure-seeker-section-wrapper">

                     <!-- Products Section Header -->
                     <div class="products-section-header">
                         <div class="section-header-content">
                             <h2 class="section-title">
                                 <?php echo esc_html($selected_tag_name); ?>
                             </h2>
                         </div>

                         <!-- Search & Filter Bar -->
                         <div class="search-filter-bar">
                             <form method="GET" class="search-filter-form" id="personaSearchForm" data-ajax="true">
                                 <input type="hidden" name="tag" id="searchTagInput" value="<?php echo esc_attr($selected_tag_slug); ?>">
                                 <input type="hidden" name="sort" id="searchSortInput" value="<?php echo esc_attr($sort_by); ?>">
                                 <input type="hidden" name="order" id="searchOrderInput" value="<?php echo esc_attr(strtolower($sort_order)); ?>">
                                 <input type="hidden" name="search" id="sortSearchInput" value="<?php echo esc_attr($search_query); ?>">

                                 <!-- Search Input -->
                                 <div class="filter-search-wrapper">
                                     <svg class="filter-search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                         <path d="M21.8189 23.6359L16.6812 18.4895C15.8734 19.1907 14.9309 19.7369 13.8539 20.128C12.7768 20.5191 11.6324 20.7147 10.4207 20.7147C7.51259 20.7147 5.04878 19.7032 3.02927 17.6803C1.00976 15.6574 0 13.2164 0 10.3573C0 7.49828 1.00976 5.05729 3.02927 3.03438C5.04878 1.01146 7.49913 0 10.3803 0C13.2345 0 15.6647 1.01146 17.6707 3.03438C19.6768 5.05729 20.6798 7.49828 20.6798 10.3573C20.6798 11.5171 20.4913 12.6365 20.1143 13.7154C19.7374 14.7943 19.1719 15.8057 18.418 16.7498L23.6365 21.8962C23.8788 22.1119 24 22.3884 24 22.7256C24 23.0627 23.8654 23.3662 23.5961 23.6359C23.3538 23.8786 23.0576 24 22.7075 24C22.3575 24 22.0613 23.8786 21.8189 23.6359ZM10.3803 18.2872C12.5614 18.2872 14.4193 17.5117 15.9542 15.9608C17.489 14.4099 18.2564 12.5421 18.2564 10.3573C18.2564 8.17259 17.489 6.30476 15.9542 4.75386C14.4193 3.20295 12.5614 2.4275 10.3803 2.4275C8.1723 2.4275 6.29415 3.20295 4.74586 4.75386C3.19756 6.30476 2.42342 8.17259 2.42342 10.3573C2.42342 12.5421 3.19756 14.4099 4.74586 15.9608C6.29415 17.5117 8.1723 18.2872 10.3803 18.2872Z" fill="black" fill-opacity="0.25" />
                                     </svg>
                                     <span class="fake-placeholder">Search <strong>giftcards</strong><em>plus</em> cards</span>

                                     <input type="text"
                                         name="search"
                                         id="personaSearchInput"
                                         class="filter-search-input"
                                         placeholder=""
                                         value="<?php echo esc_attr($search_query); ?>">

                                     <button type="button"
                                         class="clear-search-icon"
                                         onclick="clearSearchAjax()"
                                         title="Clear search"
                                         style="<?php echo $search_query ? '' : 'display: none;'; ?>">

                                         <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                             <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                         </svg>
                                     </button>
                                 </div>

                                 <!-- Sort Dropdown -->
                                 <div class="filter-sort-wrapper">

                                     <select name="sort" id="personaSortSelect" class="filter-sort-select">
                                         <option value="date" <?php selected($sort_by, 'date'); ?>>Date</option>
                                         <option value="name" <?php selected($sort_by, 'name'); ?>>Name</option>
                                         <option value="price" <?php selected($sort_by, 'price'); ?>>Price</option>
                                     </select>
                                     <!-- <button type="button" class="filter-sort-order" id="personaSortOrderBtn" title="Toggle sort order">
                                         <?php if ($sort_order === 'ASC'): ?>
                                             <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
                                                 <path d="M7 11L3 7h8l-4 4z" />
                                             </svg>
                                         <?php else: ?>
                                             <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
                                                 <path d="M7 3l4 4H3l4-4z" />
                                             </svg>
                                         <?php endif; ?>
                                     </button> -->
                                     <input type="hidden" name="order" id="sortOrderInput" value="<?php echo esc_attr(strtolower($sort_order)); ?>">
                                 </div>
                             </form>
                         </div>
                     </div>

                     <!-- Products Grid -->
                     <div class="persona-products-section">
                         <div id="personaProductsGrid" class="persona-products-grid">
                             <?php if ($products_query->have_posts()): ?>
                                 <?php while ($products_query->have_posts()):
                                        $products_query->the_post();
                                        global $product;
                                        $product_id = get_the_ID();
                                        $product_title = get_the_title();
                                        $product_price = $product->get_price_html();
                                        $product_image = get_the_post_thumbnail_url($product_id, 'medium');
                                        $product_link = get_permalink($product_id);
                                        $product_tags = get_the_terms($product_id, 'product_tag');
                                        $tag_badges = [];
                                        if ($product_tags && !is_wp_error($product_tags)) {
                                            foreach ($product_tags as $ptag) {
                                                $tag_badges[] = $ptag->name;
                                            }
                                        }
                                        $product_icons = get_the_terms($product_id, 'icons');
                                    ?>
                                     <div class="persona-product-card card gc-card">
                                         <a href="<?php echo esc_url($product_link); ?>" class="persona-product-link">
                                             <div class="persona-product-image gc-img">
                                                 <?php if ($product_image): ?>
                                                     <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_title); ?>">
                                                 <?php else: ?>
                                                     <div class="persona-product-placeholder"><span><?php echo esc_html(substr($product_title, 0, 1)); ?></span></div>
                                                 <?php endif; ?>
                                             </div>
                                             <div class="persona-product-content">
                                                 <h3 class="persona-product-title gc-title"><?php echo esc_html($product_title); ?></h3>
                                                 <?php if (!empty($tag_badges)):
                                                        $visible_tags = array_slice($tag_badges, 0, 2);
                                                        $hidden_tags  = array_slice($tag_badges, 2);
                                                    ?>
                                                     <p class="gc-product-tags product-tags-wrap">
                                                         <?php foreach ($visible_tags as $badge): ?>
                                                             <span class="product-tag product-tag--default" title="<?php echo esc_attr($badge); ?>"><?php echo esc_html($badge); ?></span>
                                                         <?php endforeach; ?>
                                                         <?php if (!empty($hidden_tags)): ?>
                                                             <span class="product-tag product-tag--more" tabindex="0">&hellip;</span>
                                                             <span class="product-tag-tooltip">
                                                                 <?php foreach ($hidden_tags as $badge): ?>
                                                                     <span class="product-tag product-tag--default" title="<?php echo esc_attr($badge); ?>"><?php echo esc_html($badge); ?></span>
                                                                 <?php endforeach; ?>
                                                             </span>
                                                         <?php endif; ?>
                                                     </p>
                                                 <?php endif; ?>
                                                 <?php if ($product_icons && !is_wp_error($product_icons)): ?>
                                                     <p class="product-icons">
                                                         <?php foreach ($product_icons as $icon): ?>
                                                             <span class="icon icon-<?php echo esc_attr($icon->slug); ?>" title="<?php echo esc_attr($icon->name); ?>"><?php echo esc_html($icon->name); ?></span>
                                                         <?php endforeach; ?>
                                                     </p>
                                                 <?php endif; ?>
                                                 <!-- <div class="persona-product-price"><?php //echo $product_price; ?></div> -->
                                             </div>
                                         </a>
                                     </div>
                                 <?php endwhile; ?>
                             <?php else: ?>
                                 <div class="persona-no-products">
                                     <p>No cards found<?php echo $search_query ? ' for your search' : ''; ?>.</p>
                                     <?php if ($search_query): ?>
                                         <button onclick="clearSearchAjax()" class="clear-search-btn">Clear search</button>
                                     <?php endif; ?>
                                 </div>
                             <?php endif; ?>
                             <?php wp_reset_postdata(); ?>
                         </div>
                     </div>
              </div>
         </div>
     </div>
     <?php endif; ?>

     <?php if ($enable_need_help_panel) { ?>

         <div class="section-need-help  persona-help ">
             <div class="container">

                 <?php
                    $templates = get_option('wpb_js_templates');

                    if (!empty($templates)) {
                        foreach ($templates as $template) {
                            if (!empty($template['name']) && $template['name'] === 'template-need-help') {
                                echo do_shortcode($template['template']);
                                break; // stop once found
                            }
                        }
                    }
                    ?>
             </div>
         </div>
         <script>
             jQuery(document).ready(function($) {

                 function toggleFakePlaceholder() {
                     const $input = $("#personaSearchInput");
                     const $placeholder = $(".fake-placeholder");

                     if ($input.val().trim() !== "" || $input.is(":focus")) {
                         $placeholder.css({
                             opacity: "0",
                             visibility: "hidden"
                         });
                     } else {
                         $placeholder.css({
                             opacity: "1",
                             visibility: "visible"
                         });
                     }
                 }

                 // Initial load
                 toggleFakePlaceholder();

                 // Events
                 $("#personaSearchInput").on("input focus blur", function() {
                     toggleFakePlaceholder();
                 });

             });
         </script>
     <?php } ?>
 </div>

 </div>

 <style>
     .light-blue-section {
         background: #00AEC733;
     }

     .section-need-help.persona-help {
         padding: 0;
     }

     .woocommerce-breadcrumb {
         display: flex;
         align-items: center;
         gap: 8px;
         margin-bottom: 20px;
         font-size: 12px;
         text-transform: uppercase;
         letter-spacing: 0.5px;
     }

     body .site-main .woocommerce-breadcrumb {
         padding-top: 0;
     }

     body .site-main .woocommerce-breadcrumb a {
         color: #00000099;
         font-size: 14px;
         text-decoration: none;
         display: flex;
         font-family: 'Verdana';
         align-items: center;
         gap: 4px;
     }

     .woocommerce-breadcrumb a:hover svg path {
         fill: #E91E8C;
     }

     .woocommerce-breadcrumb a:hover {
         color: #E91E8C;
     }

     .breadcrumb-separator {
         color: #999;
     }

     .breadcrumb-current {
         color: #00000099;
         font-weight: 400;
         font-size: 14px;
         font-family: 'Verdana';
     }

     /* Page Title */
     .persona-page-title {
         font-size: 36px;
         font-weight: 400;
         margin: 0 0 30px 0;
         color: #282828;
     }

     .persona-page-title em {
         font-family: 'Ephesis', cursive;
         color: #ED018C;
         font-style: normal;
         font-size: 70px;
     }

     /* Tags Slider Section */
     .persona-tags-section {
         position: relative;
         margin-bottom: 40px;
         background: #0000000D;
         border-radius: 8px;
         padding: 24px 20px 24px 20px;
     }

     .persona-tags-slider {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        scroll-behavior: smooth;
        padding: 0 0 10px 0;
     }

     .persona-tags-slider::-webkit-scrollbar {
         height: 8px;
     }

     .persona-tags-slider::-webkit-scrollbar-track {
         background: #f1f1f1;
         border-radius: 4px;
     }

     .persona-tags-slider::-webkit-scrollbar-thumb {
         background: #c1c1c1;
         border-radius: 4px;
     }

    .choose-your-persona-section.section-spacing{
        padding-bottom:0px;
    }
     /* Tag Card */
     .persona-tag-card {
         flex: 0 0 auto;
         width: 175px;
         padding: 20px 18px;
         background: #fff;
         transition: box-shadow 0.3s ease;
         border-radius: 8px;
     }

     .persona-tag-card:hover {
         background: #00AEC780;
     }

     .persona-tag-card.active {
         background: #00AEC780;
     }

     .persona-tag-image {
         width: 100%;
         aspect-ratio: 138 / 214;
         overflow: hidden;
         border-radius: 8px;
         background: #e0e0e0;

     }

     .persona-tag-image img {
         width: 100%;
         height: 100%;
         object-fit: cover;
         display: block;
         border-radius: 8px;
         object-fit: cover;
         transition: transform 0.3s ease;
     }

     .persona-tag-placeholder {
         width: 100%;
         height: 100%;
         display: flex;
         align-items: center;
         justify-content: center;
         background: linear-gradient(135deg, #E91E8C 0%, #FF6B9D 100%);
         color: #fff;
         font-size: 48px;
         font-weight: 700;
     }

     .persona-tag-content {
         margin-top: 15px;
         text-align: center;
     }

     .fake-placeholder {
         position: absolute;
         top: 21px;
         left: 67px;
         color: #00000040;
         pointer-events: none;
     }

     .filter-search-wrapper:focus-within .fake-placeholder {
         opacity: 0;
         visibility: hidden;
     }

     .filter-search-input:not(:placeholder-shown)+.clear-search-icon+.fake-placeholder {
         opacity: 0;
         visibility: hidden;
     }

     .persona-tag-name {
         font-size: 17px;
         font-weight: 400;
         margin-bottom: 0;
         line-height: 28px;
         display: -webkit-box;
         -webkit-line-clamp: 2;
         -webkit-box-orient: vertical;

         overflow: hidden;
         text-overflow: ellipsis;

         line-height: 1.4;
         max-height: calc(1.4em * 2);
         color: #282828;
         text-decoration: none !important;
         font-family: 'Verdana';
     }

     .persona-tag-count {
         font-size: 12px;
         color: #999;
         margin: 0;
     }

     /* Equal height cards */
     .persona-tag-card {
         display: flex;
         flex-direction: column;
     }

     .persona-tag-link {
         display: flex;
         flex-direction: column;
         height: 100%;
         text-decoration: none;
     }

     .persona-tag-content {
         flex: 1;
     }

     .no-tags-message {
         text-align: center;
         padding: 40px;
         color: #666;
     }

     /* Products Section Header */
     .products-section-header {
         margin-bottom: 30px;
     }

     .section-header-content {
         margin-bottom: 20px;
     }

     .section-title {
         font-size: 28px;
         font-weight: 500;
         margin: 0;
         color: #333;
         display: flex;
         align-items: center;
         gap: 12px;
         flex-wrap: wrap;
     }

     .result-count {
         font-size: 16px;
         color: #666;
         font-weight: 400;
     }


     .search-filter-form {
         display: flex;
         align-items: center;
         gap: 16px;
         flex-wrap: wrap;
     }

     .filter-search-wrapper {
         position: relative;
         flex: 1;
         min-width: 280px;
         max-width: 400px;
     }

     .filter-search-wrapper input {
         min-height: 68px;
         width: 100%;
         border: 1px solid #ED018C;
         padding: 0 33px 0 60px;
         border-radius: 40px;
         font-size: 21px;
     }

     .filter-search-icon {
         position: absolute;
         left: 14px;
         left: 33px;
         top: 50%;
         transform: translateY(-50%);
     }

     .filter-search-input {
         width: 100%;
         padding: 12px 40px 12px 42px;
         border: 2px solid #e0e0e0;
         border-radius: 8px;
         font-size: 15px;
         background: #fff;
         transition: all 0.2s ease;
         outline: none;
     }

     .filter-search-input:focus {
         border-color: #E91E8C;
         box-shadow: 0 0 0 3px rgba(233, 30, 140, 0.1);
     }

     .filter-search-input::placeholder {
         color: #999;
     }

     .clear-search-icon {
         position: absolute;
         right: 10px;
         top: 50%;
         transform: translateY(-50%);
         width: 24px;
         height: 24px;
         border: none;
         background: #f0f0f0;
         border-radius: 50%;
         cursor: pointer;
         display: flex;
         align-items: center;
         justify-content: center;
         color: #666;
         transition: all 0.2s ease;
         padding: 0;
     }

     .clear-search-icon:hover {
         background: #E91E8C;
         color: #fff;
     }

     .filter-sort-wrapper {
         display: flex;
         align-items: center;
         gap: 8px;
         flex-shrink: 0;
     }

     .sort-label {
         font-size: 14px;
         color: #666;
         font-weight: 500;
     }

     .filter-sort-select {
         width: 186px;
         padding: 21px 33px;
         height: auto;
         gap: 10px;
         color: #00000040;
         background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='9' viewBox='0 0 15 9' fill='none'%3E%3Cpath d='M0.832031 0.833008L7.33203 7.33301L13.832 0.833008' stroke='black' stroke-opacity='0.25' stroke-width='1.66667' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
         border-radius: 40px;
         background-position-x: 90%;
         border: 1px solid #D0D5DD;

     }

     .filter-sort-select:focus {
         border-color: #E91E8C;
     }

     .filter-sort-order {
         width: 38px;
         height: 38px;
         border: 2px solid #e0e0e0;
         border-radius: 8px;
         background: #fff;
         cursor: pointer;
         display: flex;
         align-items: center;
         justify-content: center;
         color: #666;
         transition: all 0.2s ease;
         padding: 0;
     }

     .filter-sort-order:hover {
         border-color: #E91E8C;
         color: #E91E8C;
     }

     /* Select Message */
     .persona-select-message {
         text-align: center;
         padding: 60px 20px;
         background: #f9f9f9;
         border-radius: 12px;
         margin-top: 20px;
     }

     .persona-select-message p {
         font-size: 18px;
         color: #666;
         margin: 0;
     }

     /* Products Grid */
     .persona-products-grid {
         display: grid;
         grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
         gap: 20px 26px;
     }


     .persona-product-link {
         display: block;
         text-decoration: none;
         color: inherit;
     }

     .persona-product-image {
         width: 100%;

         overflow: hidden;
         background: #f5f5f5;
         display: flex;
         align-items: center;
         justify-content: center;
     }

     .persona-product-image img {
         width: 100%;
         height: 100%;
         object-fit: cover;
     }

     .persona-product-placeholder {
         width: 80px;
         height: 80px;
         border-radius: 50%;
         background: linear-gradient(135deg, #E91E8C 0%, #FF6B9D 100%);
         display: flex;
         align-items: center;
         justify-content: center;
         color: #fff;
         font-size: 32px;
         font-weight: 700;
     }

     .persona-product-title {
         font-size: 16px;
         font-weight: 600;
         margin: 0 0 10px 0;
         color: #333;
         line-height: 1.3;
     }

     .persona-product-icons {
         display: flex;
         gap: 8px;
         margin-bottom: 10px;
     }

     .persona-product-icons img {
         width: 24px;
         height: 24px;
         object-fit: contain;
     }

     .persona-product-price {
         font-size: 16px;
         font-weight: 600;
         color: #E91E8C;
     }

     .persona-product-price .amount {
         color: #E91E8C;
     }

     /* No Products */
     .persona-no-products {
         text-align: center;
         padding: 60px 20px;
         background: #f9f9f9;
         border-radius: 12px;
     }

     .persona-no-products p {
         font-size: 18px;
         color: #666;
         margin: 0 0 15px 0;
     }

     .clear-search-btn {
         display: inline-block;
         padding: 10px 20px;
         background: #E91E8C;
         color: #fff;
         text-decoration: none;
         border-radius: 25px;
         font-size: 14px;
         transition: background 0.3s ease;
         border: none;
         cursor: pointer;
     }

     .clear-search-btn:hover {
         background: #d11a7d;
     }

     /* AJAX Loading States */
     .persona-products-grid.loading {
         opacity: 0.6;
         pointer-events: none;
     }

     .persona-loading {
         display: flex;
         align-items: center;
         justify-content: center;
         min-height: 200px;
         grid-column: 1 / -1;
     }

     .persona-loading span {
         font-size: 16px;
         color: #666;
     }

     .persona-loading span::after {
         content: '';
         animation: loadingDots 1.5s infinite;
     }

     @keyframes loadingDots {

         0%,
         20% {
             content: '.';
         }

         40% {
             content: '..';
         }

         60%,
         100% {
             content: '...';
         }
     }

     .persona-error {
         text-align: center;
         padding: 40px 20px;
         background: #fff0f0;
         border-radius: 12px;
         grid-column: 1 / -1;
     }

     .persona-error p {
         color: #d11a7d;
         margin: 0;
     }

     /* Responsive */
     @media (max-width:991px) {
         .persona-page-title {
             font-size: 29px;
         }

         .persona-page-title em {
             font-size: 44px;
         }

         .persona-tags-section {
             padding: 15px;
         }

         .section-title {
             font-size: 25px;
         }

         .result-count {
             font-size: 14px;
         }


         .filter-search-wrapper {
             max-width: none;
             min-width: auto;
         }

         .filter-sort-wrapper {
             justify-content: flex-start;
         }

         .persona-products-grid {
             grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
             gap: 14px 16px;
         }
     }

     @media (max-width:767px) {
         .persona-products-grid {
             grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
             gap: 10px 13px;
         }

         body .site-main .woocommerce-breadcrumb {
             display: none;
         }

         .filter-sort-select {
             width: 125px;
             padding: 12px 33px;
             font-size: 16px;
         }

         .persona-tag-name {
             font-size: 15px;
         }

         .gc-card .gc-title.persona-product-title {
             font-size: 12px;
         }

         .filter-search-wrapper input {
             padding: 15px 21px;
             min-height: 50px;
         }

         .fake-placeholder {
             max-width: 160px;
             white-space: nowrap;
             overflow: hidden;
             text-overflow: ellipsis;
             display: block;
             font-size: 16px;
             top: 15px;
             left: 46px;
         }

         .filter-search-icon {
             width: 18px;
             left: 20px;
         }

         .persona-tag-card {
             padding: 18.5px;
         }

         .persona-tags-section {
             width: calc(100% + 40px);
             margin-left: -20px;
             margin-right: -20px;
         }

         .search-filter-form {
             gap: 10px;
         }

         .persona-tag-content {
             margin-top: 14px;
         }

         .products-section-header {
             margin-bottom: 25px;
         }
     }
 </style>

 <script>
     function toggleSortOrder() {
         const orderInput = document.getElementById('sortOrderInput');
         const currentOrder = orderInput.value.toUpperCase();
         const newOrder = currentOrder === 'ASC' ? 'desc' : 'asc';
         orderInput.value = newOrder;
         loadProductsAjax();
     }

     // Tags Slider - Show 7 items at a time + scroll to active tag
     document.addEventListener('DOMContentLoaded', function() {
         const slider = document.getElementById('personaTagsSlider');

         // Scroll to active tag
         if (slider) {
             const activeCard = slider.querySelector('.persona-tag-card.active');
             if (activeCard) {
                 const sliderLeft = slider.getBoundingClientRect().left;
                 const cardLeft   = activeCard.getBoundingClientRect().left;
                 slider.scrollLeft += (cardLeft - sliderLeft) - (slider.clientWidth - activeCard.offsetWidth) / 2;
             }
         }
         const prevBtn = document.getElementById('personaSliderPrev');
         const nextBtn = document.getElementById('personaSliderNext');

         if (slider && prevBtn && nextBtn) {
             const cardWidth = 120; // card width
             const gap = 15; // gap between cards
             const visibleCards = 7;
             const scrollAmount = (cardWidth + gap) * visibleCards;

             function updateNavButtons() {
                 const maxScroll = slider.scrollWidth - slider.clientWidth;
                 prevBtn.disabled = slider.scrollLeft <= 0;
                 nextBtn.disabled = slider.scrollLeft >= maxScroll - 5;
             }

             prevBtn.addEventListener('click', function() {
                 slider.scrollBy({
                     left: -scrollAmount,
                     behavior: 'smooth'
                 });
             });

             nextBtn.addEventListener('click', function() {
                 slider.scrollBy({
                     left: scrollAmount,
                     behavior: 'smooth'
                 });
             });

             slider.addEventListener('scroll', updateNavButtons);
             window.addEventListener('resize', updateNavButtons);
             updateNavButtons();
         }
     });

     // AJAX Search and Filter
     document.addEventListener('DOMContentLoaded', function() {
         const searchInput = document.getElementById('personaSearchInput');
         const sortSelect = document.getElementById('personaSortSelect');
         const sortOrderBtn = document.getElementById('personaSortOrderBtn');
         const productsGrid = document.getElementById('personaProductsGrid');

         let searchTimeout;

         // Search with debounce
         if (searchInput) {
             searchInput.addEventListener('input', function() {
                 clearTimeout(searchTimeout);
                 searchTimeout = setTimeout(function() {
                     loadProductsAjax();
                 }, 300);
             });

             // Search on Enter key
             searchInput.addEventListener('keypress', function(e) {
                 if (e.key === 'Enter') {
                     e.preventDefault();
                     clearTimeout(searchTimeout);
                     loadProductsAjax();
                 }
             });
         }

         // Sort change
         if (sortSelect) {
             sortSelect.addEventListener('change', function() {
                 loadProductsAjax();
             });
         }

         // Sort order toggle
         if (sortOrderBtn) {
             sortOrderBtn.addEventListener('click', function() {
                 const orderInput = document.getElementById('sortOrderInput');
                 const currentOrder = orderInput.value.toUpperCase();
                 const newOrder = currentOrder === 'ASC' ? 'desc' : 'asc';
                 orderInput.value = newOrder;

                 // Update icon
                 const svg = sortOrderBtn.querySelector('svg');
                 if (newOrder === 'ASC') {
                     svg.innerHTML = '<path d="M7 11L3 7h8l-4 4z"/>';
                 } else {
                     svg.innerHTML = '<path d="M7 3l4 4H3l4-4z"/>';
                 }

                 loadProductsAjax();
             });
         }
     });

     function loadProductsAjax() {
         const grid = document.getElementById('personaProductsGrid');
         const tag = document.getElementById('searchTagInput').value;
         const search = document.getElementById('personaSearchInput').value;
         const sort = document.getElementById('personaSortSelect').value;
         const order = document.getElementById('sortOrderInput').value;

         if (!grid) return;

         // Show loading state
         grid.classList.add('loading');
         grid.innerHTML = '<div class="persona-loading"><span>Loading...</span></div>';

         // Build URL
         const params = new URLSearchParams();
         params.append('ajax', 'products');
         if (tag) params.append('tag', tag);
         if (search) params.append('search', search);
         if (sort) params.append('sort', sort);
         if (order) params.append('order', order);

         const url = window.location.pathname + '?' + params.toString();

         // Update browser URL without reload
         const displayParams = new URLSearchParams();
         if (tag) displayParams.append('tag', tag);
         if (search) displayParams.append('search', search);
         if (sort) displayParams.append('sort', sort);
         if (order) displayParams.append('order', order);
         const displayUrl = window.location.pathname + (displayParams.toString() ? '?' + displayParams.toString() : '');
         window.history.pushState({
             ajax: true
         }, '', displayUrl);

         // Fetch products
         fetch(url)
             .then(response => response.text())
             .then(html => {
                 grid.classList.remove('loading');
                 grid.innerHTML = html;

                 // Update section title
                 const tagName = document.querySelector('.section-title')?.dataset?.tagName || '';
                 const productCount = grid.querySelectorAll('.persona-product-card').length;
                 updateSectionTitle(search, tagName, productCount);

                 // Show/hide clear button based on search
                 const clearBtn = document.querySelector('.clear-search-icon');
                 if (clearBtn) {
                     clearBtn.style.display = search ? 'flex' : 'none';
                 }
             })
             .catch(error => {
                 grid.classList.remove('loading');
                 grid.innerHTML = '<div class="persona-error"><p>Error loading products. Please try again.</p></div>';
                 console.error('AJAX Error:', error);
             });
     }

     function clearSearchAjax() {
         const searchInput = document.getElementById('personaSearchInput');
         if (searchInput) {
             searchInput.value = '';
             loadProductsAjax();
         }
     }

     // Update section title based on search state
     function updateSectionTitle(search, tagName, count) {
         const titleEl = document.querySelector('.section-title');
         if (!titleEl) return;

         if (search) {
             titleEl.innerHTML = `Search Results for "${escapeHtml(search)}"<span class="result-count">in ${escapeHtml(tagName)}</span>`;
         } else {
             titleEl.innerHTML = `${escapeHtml(tagName)} Gift Cards<span class="result-count">${count} products</span>`;
         }
     }

     function escapeHtml(text) {
         const div = document.createElement('div');
         div.textContent = text;
         return div.innerHTML;
     }
 </script>

 <?php get_footer(); ?>