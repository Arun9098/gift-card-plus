<?php
/**
 * Shortcode: [offer_for_you]
 * 
 * Displays offers that have products assigned to them in Owl Carousel
 * Usage: [offer_for_you limit="4" search="true"]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register the shortcode
add_shortcode('offer_for_you', 'render_offer_for_you_shortcode');

function render_offer_for_you_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts([
        'limit' => -1, // -1 means all offers
        'search' => 'true', // Show search bar
        'filters' => 'true', // Show filters button
        'items' => 4, // Number of items to show in carousel
        'autoplay' => 'false', // Autoplay carousel
        'is_wallet' => 'false',
        'button_text' => 'Shop now',
    ], $atts);
    
    $limit              = intval($atts['limit']);
    $show_search        = $atts['search'] === 'true';
    $show_filters       = $atts['filters'] === 'true';
    $carousel_items     = intval($atts['items']);
    $autoplay_enabled   = $atts['autoplay'] === 'true';
    $is_wallet          = ($atts['is_wallet'] === 'true');
    
    // Owl Carousel is already enqueued globally via enqueue_custom_scripts_owl()
    // But ensure it's loaded if not already
    if (!wp_style_is('giftcard-owl-carousel-css', 'enqueued')) {
        wp_enqueue_style('giftcard-owl-carousel-css', get_template_directory_uri() . '/assets/css/owl-carousel.css', array(), time());
    }
    if (!wp_script_is('giftcard-owl-carousel-js', 'enqueued')) {
        wp_enqueue_script('giftcard-owl-carousel-js', get_template_directory_uri() . '/assets/js/owl-carousel.js', array('jquery'), time(), true);
    }
    
    // Get all published offers that have products
    $offers = get_posts([
        'post_type' => 'offer',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    
    // Filter offers to only include those with products and are currently active
    $offers_with_products = [];
    $current_time = current_time('mysql');
    
    foreach ($offers as $offer) {
        $meta = get_offer_meta($offer->ID);
        $products = $meta['products'] ?? [];
        
        // Check if offer has products or is available for all products
        if (empty($products) && !($meta['all_products'] ?? false)) {
            continue; // Skip offers without products
        }
        
        // Check if offer is "always on" or within date range
        $is_always_on = $meta['always_on'] ?? false;
        $start_date = $meta['start_date'] ?? '';
        $end_date = $meta['end_date'] ?? '';
        
        $is_active = true;
        if (!$is_always_on) {
            if (!empty($start_date) && $current_time < $start_date) {
                $is_active = false; // Offer hasn't started yet
            }
            if (!empty($end_date) && $current_time > $end_date) {
                $is_active = false; // Offer has ended
            }
        }
        
        if ($is_active) {
            $offers_with_products[] = $offer;
        }
    }
    
    if (empty($offers_with_products)) {
        return '<p>No offers available at the moment.</p>';
    }
    
    $offer_count = count($offers_with_products);
    $unique_id = 'offers-carousel-' . uniqid();


    // $unique_id, $offer_count, $carousel_items, $autoplay_enabled are all set above this point
    if ( ! wp_script_is( 'offer-for-you-js', 'enqueued' ) ) {
        wp_enqueue_script( 'offer-for-you-js', get_template_directory_uri() . '/assets/js/offer-for-you.js', array( 'jquery' ), time(), true );
    }

    // $unique_id, $offer_count, $carousel_items, $autoplay_enabled are all set above this point
    wp_localize_script( 'offer-for-you-js', 'bhnOffer', array(
        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'bhn_offer_link_nonce' ),
        'carouselId'    => $unique_id,
        'offerCount'    => $offer_count,
        'carouselItems' => $carousel_items,
        'autoplay'      => $autoplay_enabled ? 'true' : 'false',
    ));

    ob_start();
    ?>
    <div class="offers-for-you-wrapper <?php echo $is_wallet ? '' : 'bg-lightblue'; ?>">
        <!-- Header Section -->
        <div class="offers-header">
            
            <?php if ($show_search): ?>
            <h2>Offers for <em>you</em></h2>
            <div class="offers-search-filter-wrap">
            <div class="search-wrap">
                <div class="offers-search">
                    <input type="text" id="offers-search-input-<?php echo esc_attr($unique_id); ?>" placeholder="Search Plus offers" class="offers-search-field" aria-label="Search Plus offers">
                    <span class="offers-search-clear" role="button" id="offers-search-clear-<?php echo esc_attr($unique_id); ?>" title="Clear search" aria-label="Clear search" tabindex="0">×</span>
                    <span class="search-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21.8189 23.6359L16.6812 18.4895C15.8734 19.1907 14.9309 19.7369 13.8539 20.128C12.7768 20.5191 11.6324 20.7147 10.4207 20.7147C7.51259 20.7147 5.04878 19.7032 3.02927 17.6803C1.00976 15.6574 0 13.2164 0 10.3573C0 7.49828 1.00976 5.05729 3.02927 3.03438C5.04878 1.01146 7.49913 0 10.3803 0C13.2345 0 15.6647 1.01146 17.6707 3.03438C19.6768 5.05729 20.6798 7.49828 20.6798 10.3573C20.6798 11.5171 20.4913 12.6365 20.1143 13.7154C19.7374 14.7943 19.1719 15.8057 18.418 16.7498L23.6365 21.8962C23.8788 22.1119 24 22.3884 24 22.7256C24 23.0627 23.8654 23.3662 23.5961 23.6359C23.3538 23.8786 23.0576 24 22.7075 24C22.3575 24 22.0613 23.8786 21.8189 23.6359ZM10.3803 18.2872C12.5614 18.2872 14.4193 17.5117 15.9542 15.9608C17.489 14.4099 18.2564 12.5421 18.2564 10.3573C18.2564 8.17259 17.489 6.30476 15.9542 4.75386C14.4193 3.20295 12.5614 2.4275 10.3803 2.4275C8.1723 2.4275 6.29415 3.20295 4.74586 4.75386C3.19756 6.30476 2.42342 8.17259 2.42342 10.3573C2.42342 12.5421 3.19756 14.4099 4.74586 15.9608C6.29415 17.5117 8.1723 18.2872 10.3803 18.2872Z" fill="#202224"/>
                        </svg>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($show_filters): ?>
            <button type="button" class="offers-filters-btn" id="offers-filters-btn-<?php echo esc_attr($unique_id); ?>">
                <span class="filters-icon">
                    <svg width="29" height="20" viewBox="0 0 29 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5.33496 9.83594H23.335M0.834961 0.835938H27.835M9.83496 18.8359H18.835" stroke="#344054" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span> Filters
            </button>
            <?php endif; ?>
            </div>
            <div class="offers-navigation">
                <button type="button" class="nav-btn prev-btn owl-prev" id="offers-prev-btn-<?php echo esc_attr($unique_id); ?>">
                    <svg width="21" height="14" viewBox="0 0 21 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63115C1.89551 6.38 1.99528 6.13913 2.17287 5.96154C2.35046 5.78395 2.59133 5.68418 2.84248 5.68418L19.888 5.68418C20.1391 5.68418 20.38 5.78395 20.5576 5.96154C20.7352 6.13913 20.835 6.38 20.835 6.63115C20.835 6.8823 20.7352 7.12317 20.5576 7.30076C20.38 7.47835 20.1391 7.57812 19.888 7.57812L2.84248 7.57812C2.59133 7.57812 2.35046 7.47835 2.17287 7.30076C1.99528 7.12317 1.89551 6.88231 1.89551 6.63115Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63115C1.89551 6.38 1.99528 6.13913 2.17287 5.96154C2.35046 5.78395 2.59133 5.68418 2.84248 5.68418L19.888 5.68418C20.1391 5.68418 20.38 5.78395 20.5576 5.96154C20.7352 6.13913 20.835 6.38 20.835 6.63115C20.835 6.8823 20.7352 7.12317 20.5576 7.30076C20.38 7.47835 20.1391 7.57812 19.888 7.57812L2.84248 7.57812C2.59133 7.57812 2.35046 7.47835 2.17287 7.30076C1.99528 7.12317 1.89551 6.88231 1.89551 6.63115Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63115C1.89551 6.38 1.99528 6.13913 2.17287 5.96154C2.35046 5.78395 2.59133 5.68418 2.84248 5.68418L19.888 5.68418C20.1391 5.68418 20.38 5.78395 20.5576 5.96154C20.7352 6.13913 20.835 6.38 20.835 6.63115C20.835 6.8823 20.7352 7.12317 20.5576 7.30076C20.38 7.47835 20.1391 7.57812 19.888 7.57812L2.84248 7.57812C2.59133 7.57812 2.35046 7.47835 2.17287 7.30076C1.99528 7.12317 1.89551 6.88231 1.89551 6.63115Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63115C1.89551 6.38 1.99528 6.13913 2.17287 5.96154C2.35046 5.78395 2.59133 5.68418 2.84248 5.68418L19.888 5.68418C20.1391 5.68418 20.38 5.78395 20.5576 5.96154C20.7352 6.13913 20.835 6.38 20.835 6.63115C20.835 6.8823 20.7352 7.12317 20.5576 7.30076C20.38 7.47835 20.1391 7.57812 19.888 7.57812L2.84248 7.57812C2.59133 7.57812 2.35046 7.47835 2.17287 7.30076C1.99528 7.12317 1.89551 6.88231 1.89551 6.63115Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63115C1.89551 6.38 1.99528 6.13913 2.17287 5.96154C2.35046 5.78395 2.59133 5.68418 2.84248 5.68418L19.888 5.68418C20.1391 5.68418 20.38 5.78395 20.5576 5.96154C20.7352 6.13913 20.835 6.38 20.835 6.63115C20.835 6.8823 20.7352 7.12317 20.5576 7.30076C20.38 7.47835 20.1391 7.57812 19.888 7.57812L2.84248 7.57812C2.59133 7.57812 2.35046 7.47835 2.17287 7.30076C1.99528 7.12317 1.89551 6.88231 1.89551 6.63115Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63115C1.89551 6.38 1.99528 6.13913 2.17287 5.96154C2.35046 5.78395 2.59133 5.68418 2.84248 5.68418L19.888 5.68418C20.1391 5.68418 20.38 5.78395 20.5576 5.96154C20.7352 6.13913 20.835 6.38 20.835 6.63115C20.835 6.8823 20.7352 7.12317 20.5576 7.30076C20.38 7.47835 20.1391 7.57812 19.888 7.57812L2.84248 7.57812C2.59133 7.57812 2.35046 7.47835 2.17287 7.30076C1.99528 7.12317 1.89551 6.88231 1.89551 6.63115Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M1.89551 6.63115C1.89551 6.38 1.99528 6.13913 2.17287 5.96154C2.35046 5.78395 2.59133 5.68418 2.84248 5.68418L19.888 5.68418C20.1391 5.68418 20.38 5.78395 20.5576 5.96154C20.7352 6.13913 20.835 6.38 20.835 6.63115C20.835 6.8823 20.7352 7.12317 20.5576 7.30076C20.38 7.47835 20.1391 7.57812 19.888 7.57812L2.84248 7.57812C2.59133 7.57812 2.35046 7.47835 2.17287 7.30076C1.99528 7.12317 1.89551 6.88231 1.89551 6.63115Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30217C0.190022 7.21421 0.120054 7.10971 0.0723148 6.99466C0.0245752 6.87961 1.47626e-06 6.75628 1.44903e-06 6.63172C1.42181e-06 6.50716 0.024575 6.38382 0.0723146 6.26878C0.120054 6.15373 0.190022 6.04923 0.27821 5.96126L5.96004 0.279428C6.13786 0.101613 6.37903 0.00171713 6.6305 0.00171707C6.88197 0.00171702 7.12314 0.101612 7.30095 0.279428C7.47877 0.457244 7.57867 0.698414 7.57867 0.949885C7.57867 1.20135 7.47877 1.44252 7.30095 1.62034L2.28768 6.63172L7.30095 11.6431C7.47877 11.8209 7.57867 12.0621 7.57867 12.3135C7.57867 12.565 7.47877 12.8062 7.30096 12.984C7.12314 13.1618 6.88197 13.2617 6.6305 13.2617C6.37903 13.2617 6.13786 13.1618 5.96004 12.984L0.27821 7.30217Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30217C0.190022 7.21421 0.120054 7.10971 0.0723148 6.99466C0.0245752 6.87961 1.47626e-06 6.75628 1.44903e-06 6.63172C1.42181e-06 6.50716 0.024575 6.38382 0.0723146 6.26878C0.120054 6.15373 0.190022 6.04923 0.27821 5.96126L5.96004 0.279428C6.13786 0.101613 6.37903 0.00171713 6.6305 0.00171707C6.88197 0.00171702 7.12314 0.101612 7.30095 0.279428C7.47877 0.457244 7.57867 0.698414 7.57867 0.949885C7.57867 1.20135 7.47877 1.44252 7.30095 1.62034L2.28768 6.63172L7.30095 11.6431C7.47877 11.8209 7.57867 12.0621 7.57867 12.3135C7.57867 12.565 7.47877 12.8062 7.30096 12.984C7.12314 13.1618 6.88197 13.2617 6.6305 13.2617C6.37903 13.2617 6.13786 13.1618 5.96004 12.984L0.27821 7.30217Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30217C0.190022 7.21421 0.120054 7.10971 0.0723148 6.99466C0.0245752 6.87961 1.47626e-06 6.75628 1.44903e-06 6.63172C1.42181e-06 6.50716 0.024575 6.38382 0.0723146 6.26878C0.120054 6.15373 0.190022 6.04923 0.27821 5.96126L5.96004 0.279428C6.13786 0.101613 6.37903 0.00171713 6.6305 0.00171707C6.88197 0.00171702 7.12314 0.101612 7.30095 0.279428C7.47877 0.457244 7.57867 0.698414 7.57867 0.949885C7.57867 1.20135 7.47877 1.44252 7.30095 1.62034L2.28768 6.63172L7.30095 11.6431C7.47877 11.8209 7.57867 12.0621 7.57867 12.3135C7.57867 12.565 7.47877 12.8062 7.30096 12.984C7.12314 13.1618 6.88197 13.2617 6.6305 13.2617C6.37903 13.2617 6.13786 13.1618 5.96004 12.984L0.27821 7.30217Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30217C0.190022 7.21421 0.120054 7.10971 0.0723148 6.99466C0.0245752 6.87961 1.47626e-06 6.75628 1.44903e-06 6.63172C1.42181e-06 6.50716 0.024575 6.38382 0.0723146 6.26878C0.120054 6.15373 0.190022 6.04923 0.27821 5.96126L5.96004 0.279428C6.13786 0.101613 6.37903 0.00171713 6.6305 0.00171707C6.88197 0.00171702 7.12314 0.101612 7.30095 0.279428C7.47877 0.457244 7.57867 0.698414 7.57867 0.949885C7.57867 1.20135 7.47877 1.44252 7.30095 1.62034L2.28768 6.63172L7.30095 11.6431C7.47877 11.8209 7.57867 12.0621 7.57867 12.3135C7.57867 12.565 7.47877 12.8062 7.30096 12.984C7.12314 13.1618 6.88197 13.2617 6.6305 13.2617C6.37903 13.2617 6.13786 13.1618 5.96004 12.984L0.27821 7.30217Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30217C0.190022 7.21421 0.120054 7.10971 0.0723148 6.99466C0.0245752 6.87961 1.47626e-06 6.75628 1.44903e-06 6.63172C1.42181e-06 6.50716 0.024575 6.38382 0.0723146 6.26878C0.120054 6.15373 0.190022 6.04923 0.27821 5.96126L5.96004 0.279428C6.13786 0.101613 6.37903 0.00171713 6.6305 0.00171707C6.88197 0.00171702 7.12314 0.101612 7.30095 0.279428C7.47877 0.457244 7.57867 0.698414 7.57867 0.949885C7.57867 1.20135 7.47877 1.44252 7.30095 1.62034L2.28768 6.63172L7.30095 11.6431C7.47877 11.8209 7.57867 12.0621 7.57867 12.3135C7.57867 12.565 7.47877 12.8062 7.30096 12.984C7.12314 13.1618 6.88197 13.2617 6.6305 13.2617C6.37903 13.2617 6.13786 13.1618 5.96004 12.984L0.27821 7.30217Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30217C0.190022 7.21421 0.120054 7.10971 0.0723148 6.99466C0.0245752 6.87961 1.47626e-06 6.75628 1.44903e-06 6.63172C1.42181e-06 6.50716 0.024575 6.38382 0.0723146 6.26878C0.120054 6.15373 0.190022 6.04923 0.27821 5.96126L5.96004 0.279428C6.13786 0.101613 6.37903 0.00171713 6.6305 0.00171707C6.88197 0.00171702 7.12314 0.101612 7.30095 0.279428C7.47877 0.457244 7.57867 0.698414 7.57867 0.949885C7.57867 1.20135 7.47877 1.44252 7.30095 1.62034L2.28768 6.63172L7.30095 11.6431C7.47877 11.8209 7.57867 12.0621 7.57867 12.3135C7.57867 12.565 7.47877 12.8062 7.30096 12.984C7.12314 13.1618 6.88197 13.2617 6.6305 13.2617C6.37903 13.2617 6.13786 13.1618 5.96004 12.984L0.27821 7.30217Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.27821 7.30217C0.190022 7.21421 0.120054 7.10971 0.0723148 6.99466C0.0245752 6.87961 1.47626e-06 6.75628 1.44903e-06 6.63172C1.42181e-06 6.50716 0.024575 6.38382 0.0723146 6.26878C0.120054 6.15373 0.190022 6.04923 0.27821 5.96126L5.96004 0.279428C6.13786 0.101613 6.37903 0.00171713 6.6305 0.00171707C6.88197 0.00171702 7.12314 0.101612 7.30095 0.279428C7.47877 0.457244 7.57867 0.698414 7.57867 0.949885C7.57867 1.20135 7.47877 1.44252 7.30095 1.62034L2.28768 6.63172L7.30095 11.6431C7.47877 11.8209 7.57867 12.0621 7.57867 12.3135C7.57867 12.565 7.47877 12.8062 7.30096 12.984C7.12314 13.1618 6.88197 13.2617 6.6305 13.2617C6.37903 13.2617 6.13786 13.1618 5.96004 12.984L0.27821 7.30217Z" fill="black" fill-opacity="0.2"/>
                    </svg>
                </button>
                <button type="button" class="nav-btn next-btn owl-next" id="offers-next-btn-<?php echo esc_attr($unique_id); ?>">
                    <svg width="21" height="14" viewBox="0 0 21 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9394 6.63057C18.9394 6.88172 18.8397 7.12258 18.6621 7.30018C18.4845 7.47777 18.2436 7.57754 17.9925 7.57754L0.946972 7.57754C0.69582 7.57754 0.454952 7.47777 0.277361 7.30018C0.0997698 7.12259 1.57115e-07 6.88172 1.2418e-07 6.63057C9.12457e-08 6.37942 0.0997697 6.13855 0.277361 5.96096C0.454952 5.78337 0.69582 5.6836 0.946972 5.6836L17.9925 5.68359C18.2436 5.68359 18.4845 5.78336 18.6621 5.96096C18.8397 6.13855 18.9394 6.37941 18.9394 6.63057Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9394 6.63057C18.9394 6.88172 18.8397 7.12258 18.6621 7.30018C18.4845 7.47777 18.2436 7.57754 17.9925 7.57754L0.946972 7.57754C0.69582 7.57754 0.454952 7.47777 0.277361 7.30018C0.0997698 7.12259 1.57115e-07 6.88172 1.2418e-07 6.63057C9.12457e-08 6.37942 0.0997697 6.13855 0.277361 5.96096C0.454952 5.78337 0.69582 5.6836 0.946972 5.6836L17.9925 5.68359C18.2436 5.68359 18.4845 5.78336 18.6621 5.96096C18.8397 6.13855 18.9394 6.37941 18.9394 6.63057Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9394 6.63057C18.9394 6.88172 18.8397 7.12258 18.6621 7.30018C18.4845 7.47777 18.2436 7.57754 17.9925 7.57754L0.946972 7.57754C0.69582 7.57754 0.454952 7.47777 0.277361 7.30018C0.0997698 7.12259 1.57115e-07 6.88172 1.2418e-07 6.63057C9.12457e-08 6.37942 0.0997697 6.13855 0.277361 5.96096C0.454952 5.78337 0.69582 5.6836 0.946972 5.6836L17.9925 5.68359C18.2436 5.68359 18.4845 5.78336 18.6621 5.96096C18.8397 6.13855 18.9394 6.37941 18.9394 6.63057Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9394 6.63057C18.9394 6.88172 18.8397 7.12258 18.6621 7.30018C18.4845 7.47777 18.2436 7.57754 17.9925 7.57754L0.946972 7.57754C0.69582 7.57754 0.454952 7.47777 0.277361 7.30018C0.0997698 7.12259 1.57115e-07 6.88172 1.2418e-07 6.63057C9.12457e-08 6.37942 0.0997697 6.13855 0.277361 5.96096C0.454952 5.78337 0.69582 5.6836 0.946972 5.6836L17.9925 5.68359C18.2436 5.68359 18.4845 5.78336 18.6621 5.96096C18.8397 6.13855 18.9394 6.37941 18.9394 6.63057Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9394 6.63057C18.9394 6.88172 18.8397 7.12258 18.6621 7.30018C18.4845 7.47777 18.2436 7.57754 17.9925 7.57754L0.946972 7.57754C0.69582 7.57754 0.454952 7.47777 0.277361 7.30018C0.0997698 7.12259 1.57115e-07 6.88172 1.2418e-07 6.63057C9.12457e-08 6.37942 0.0997697 6.13855 0.277361 5.96096C0.454952 5.78337 0.69582 5.6836 0.946972 5.6836L17.9925 5.68359C18.2436 5.68359 18.4845 5.78336 18.6621 5.96096C18.8397 6.13855 18.9394 6.37941 18.9394 6.63057Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9394 6.63057C18.9394 6.88172 18.8397 7.12258 18.6621 7.30018C18.4845 7.47777 18.2436 7.57754 17.9925 7.57754L0.946972 7.57754C0.69582 7.57754 0.454952 7.47777 0.277361 7.30018C0.0997698 7.12259 1.57115e-07 6.88172 1.2418e-07 6.63057C9.12457e-08 6.37942 0.0997697 6.13855 0.277361 5.96096C0.454952 5.78337 0.69582 5.6836 0.946972 5.6836L17.9925 5.68359C18.2436 5.68359 18.4845 5.78336 18.6621 5.96096C18.8397 6.13855 18.9394 6.37941 18.9394 6.63057Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M18.9394 6.63057C18.9394 6.88172 18.8397 7.12258 18.6621 7.30018C18.4845 7.47777 18.2436 7.57754 17.9925 7.57754L0.946972 7.57754C0.69582 7.57754 0.454952 7.47777 0.277361 7.30018C0.0997698 7.12259 1.57115e-07 6.88172 1.2418e-07 6.63057C9.12457e-08 6.37942 0.0997697 6.13855 0.277361 5.96096C0.454952 5.78337 0.69582 5.6836 0.946972 5.6836L17.9925 5.68359C18.2436 5.68359 18.4845 5.78336 18.6621 5.96096C18.8397 6.13855 18.9394 6.37941 18.9394 6.63057Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5567 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.8349 6.50544 20.8349 6.63C20.8349 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5567 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5567 5.95954Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5567 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.8349 6.50544 20.8349 6.63C20.8349 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5567 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5567 5.95954Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5567 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.8349 6.50544 20.8349 6.63C20.8349 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5567 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5567 5.95954Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5567 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.8349 6.50544 20.8349 6.63C20.8349 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5567 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5567 5.95954Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5567 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.8349 6.50544 20.8349 6.63C20.8349 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5567 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5567 5.95954Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5567 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.8349 6.50544 20.8349 6.63C20.8349 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5567 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5567 5.95954Z" fill="black" fill-opacity="0.2"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M20.5567 5.95954C20.6449 6.04751 20.7149 6.15201 20.7626 6.26706C20.8104 6.3821 20.8349 6.50544 20.8349 6.63C20.8349 6.75456 20.8104 6.8779 20.7626 6.99294C20.7149 7.10799 20.6449 7.21249 20.5567 7.30046L14.8749 12.9823C14.6971 13.1601 14.4559 13.26 14.2045 13.26C13.953 13.26 13.7118 13.1601 13.534 12.9823C13.3562 12.8045 13.2563 12.5633 13.2563 12.3118C13.2563 12.0604 13.3562 11.8192 13.534 11.6414L18.5473 6.63L13.534 1.61863C13.3562 1.44081 13.2563 1.19964 13.2563 0.94817C13.2563 0.6967 13.3562 0.455529 13.534 0.277713C13.7118 0.0998972 13.953 9.02461e-07 14.2045 8.69485e-07C14.4559 8.36508e-07 14.6971 0.099897 14.8749 0.277713L20.5567 5.95954Z" fill="black" fill-opacity="0.2"/>
                        </svg>
                </button>
            </div>
        </div>
        
        <!-- Offers Carousel -->
        <div class="offers-carousel-wrapper">
            <div class="owl-carousel owl-theme offers-carousel" id="<?php echo esc_attr($unique_id); ?>">
                <?php foreach ($offers_with_products as $offer): 
                    $meta = get_offer_meta($offer->ID);
                    $products = $meta['products'] ?? [];
                    
                    // Get first product for display (logo, name, etc.)
                    $first_product = null;
                    $product_image_url = '';
                    $product_name = '';
                    
                    // Get all selected product names for display
                    $selected_product_names = [];
                    
                    if (!empty($products)) {
                        foreach ($products as $product_id) {
                            $product = wc_get_product($product_id);
                            if ($product) {
                                $selected_product_names[] = $product->get_name();
                                // Get first product details for image and link
                                if ($first_product === null) {
                                    $first_product = $product;
                                    $product_name = $product->get_name();
                                    $product_image_id = $product->get_image_id();
                                    if ($product_image_id) {
                                        $product_image_url = wp_get_attachment_image_url($product_image_id, 'medium');
                                    }
                                }
                            }
                        }
                    }
                    
                    // Get offer image if available
                    $offer_image_url = '';
                    if (!empty($meta['image_id'])) {
                        $offer_image_url = wp_get_attachment_image_url($meta['image_id'], 'medium');
                    }
                    
                    // Use offer image or product image
                    $display_image_url = $offer_image_url ?: $product_image_url;
                    
                    // Get offer details
                    $offer_title = $offer->post_title;
                    $offer_description = $meta['description'] ?? '';
                    $promo_code = $meta['promo_code'] ?? '';
                    $offer_link = $meta['link'] ?? '';
                    $offer_tags = $meta['tags'] ?? [];
                    $offer_tag = !empty($offer_tags) && is_array($offer_tags) ? $offer_tags[0] : '';
                    $offer_terms = $meta['terms'] ?? '';
                    
                    
                    // Extract discount percentage from description or tag if available
                    $discount_text = '';
                    if (!empty($offer_tag)) {
                        // Try to extract percentage from tag
                        if (preg_match('/(\d+)%/', $offer_tag, $matches)) {
                            $discount_text = $matches[1] . '% OFF';
                        }
                    }
                    if (empty($discount_text) && !empty($offer_description)) {
                        // Try to extract from description
                        if (preg_match('/(\d+)%/', $offer_description, $matches)) {
                            $discount_text = $matches[1] . '% OFF';
                        }
                    }
                    $offer_id = $offer->ID;
                    $offer_showcase = get_post_meta($offer_id, '_offer_showcase_type', true);


                    if( $offer_showcase === 'link' ){
                        $offer_data = get_post_meta($offer_id,'_offer_link', true);
                    } else if ( $offer_showcase === 'promo_code' || $offer_showcase === 'copy' ){
                        $offer_data = get_post_meta($offer_id,'_offer_promo_code', true);
                    }
                    // Determine shop now link
                    $shop_now_link = '#';
                    if (!empty($offer_link)) {
                        $shop_now_link = esc_url($offer_link);
                    } elseif (!empty($first_product)) {
                        $shop_now_link = get_permalink($first_product->get_id());
                    } ?>
                    <div class="offer-card-wrapper">
                        <div class="item offer-card" data-offer-id="<?php echo esc_attr($offer->ID); ?>" data-offer-title="<?php echo esc_attr(strtolower($offer_title)); ?>">
                            <!-- Logo/Brand Image -->
                            <?php if ($display_image_url): ?>
                            <div class="offer-logo">
                                <img src="<?php echo esc_url($display_image_url); ?>" alt="<?php echo esc_attr($offer_title); ?>" class="offer-logo-img">
                            </div>
                            <?php endif; ?>
                            
                            <!-- Exclusive Offer Tag -->
                            <?php if (!empty($offer_tag)): ?>
                            <div class="offer-tag">
                                <?php echo esc_html(strtoupper($offer_tag)); ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Offer Title -->
                            <div class="selected-offers">
                                <?php 
                                if (!empty($selected_product_names)) {
                                    echo esc_html(implode(', ', $selected_product_names));
                                }
                                ?>
                            </div>
                            <h2 class="offer-per offer-title"><?php echo esc_html($offer_title); ?></h2>
                            
                            <!-- Discount Percentage -->
                            <?php if (!empty($discount_text)): ?>
                            <div class="offer-discount"><?php echo esc_html($discount_text); ?></div>
                            <?php endif; ?>
                            
                            <!-- Offer Description -->
                            <?php if (!empty($offer_description)): ?>
                            <p class="offer-description"><?php echo esc_html($offer_description); ?></p>
                            <?php endif; ?>
                            
                            <!-- Promo Code (if available) -->
                            <?php if (!empty($promo_code) && ($meta['showcase_type'] ?? '') === 'promo_code'): ?>
                            <div class="offer-promo-code">
                                <strong>Promo Code:</strong> <?php echo esc_html($promo_code); ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- See Code Button: only for promo_code offers. No button for Link or Copy offer types. -->
                            <?php if ( $offer_showcase === 'promo_code' ): ?>
                                <button type="button" class="gcpw-see-code btn btn-white-p2" data-showcase-type="<?php echo esc_attr($offer_showcase); ?>" data-showcase="<?php echo esc_attr($offer_data); ?>" data-offer-id="<?php echo esc_attr($offer->ID); ?>">
                                    <?php echo esc_html__('See Code', 'gcp-wallet'); ?>
                                </button>
                            <?php endif; ?>
                            <?php if ( $is_wallet ) { ?>          
                                <div class="offer-details">
                                    <span class="gcpw-offer-terms-trigger" role="button" tabindex="0">
                                        <?php esc_html_e( 'Terms & Conditions Apply', 'gcp-wallet' ); ?>
                                    </span>

                                </div>
                            <?php } ?>
                        </div>
                        <?php if ( $is_wallet ) { ?>          
                            <div class="gcpw-offer-terms-popup" role="dialog" aria-modal="true" style="display: none;">
                                <div class="gcpw-popup-content">
                                    <span class="gcpw-popup-close" role="button" aria-label="Close" tabindex="0">&times;</span>
                                    
                                    <h2 class="gcpw-popup-title">
                                        <?php esc_html_e( 'Terms & Conditions', 'gcp-wallet' ); ?>
                                    </h2>
                                    
                                    <div class="gcpw-terms-text">
                                        <?php 
                                        echo wp_kses_post( $offer_terms ? $offer_terms : __( 'No terms available.', 'gcp-wallet' ) ); 
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="offer-popup" class="offer-popup" style="display:none;">
        <div class="offer-popup-content">
            <span class="close-popup">&times;</span>

            <h3 id="popup-title">Promo Code</h3>

            <div id="popup-content-text"></div>

            <button id="copy-btn" style="display:none;">Copy</button>

            <div id="copy-message" style="display:none; color: green; margin-top:10px;">
                Copied!
            </div>
        </div>
    </div>
    
    <?php
    
    return ob_get_clean();
}