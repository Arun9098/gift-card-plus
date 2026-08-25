<?php
/**
 * WooCommerce Product Archive / Category Template
 *
 * Customised category layout to match gift-card grid design.
 */

defined('ABSPATH') || exit;

get_header();

// If this is a product category archive, use the custom layout.

?>

<?php
$category_name = '';
$back_link = wp_get_referer();

if (!$back_link) {
    $back_link = home_url();
}

if (is_product_category() || is_tax('product_brand')) {
    $term = get_queried_object();
    if ($term && !is_wp_error($term)) {
        $category_name = $term->name;
    }
}
?>

<?php if ($category_name): ?>
<div class="woocommerce-breadcrumb" aria-label="Breadcrumb">
    <span class="breacrum-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect x="24" width="24" height="24" rx="12" transform="rotate(90 24 0)" fill="white"></rect>
            <path fill-rule="evenodd" clip-rule="evenodd" d="M5.45703 12.0012C5.45703 11.8083 5.53367 11.6233 5.67007 11.4869C5.80647 11.3505 5.99147 11.2738 6.18438 11.2738L19.2765 11.2738C19.4694 11.2738 19.6544 11.3505 19.7908 11.4869C19.9272 11.6233 20.0039 12.0012 20.0039 12.0012C20.0039 12.1941 19.9272 12.3791 19.7908 12.5155C19.6544 12.6519 19.4694 12.7285 19.2765 12.7285L6.18438 12.7285C5.99147 12.7285 5.80647 12.6519 5.67007 12.5155C5.53367 12.3791 5.45703 12.1941 5.45703 12.0012Z" fill="black" fill-opacity="0.6"></path>
            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.21369 12.5154C4.14595 12.4479 4.09221 12.3676 4.05554 12.2792C4.01888 12.1909 4 12.0961 4 12.0005C4 11.9048 4.01888 11.8101 4.05554 11.7217C4.09221 11.6333 4.14595 11.5531 4.21369 11.4855L8.57773 7.12146C8.71431 6.98488 8.89955 6.90815 9.09269 6.90815C9.28584 6.90815 9.47107 6.98488 9.60765 7.12145C9.74423 7.25803 9.82095 7.44327 9.82095 7.63641C9.82095 7.82956 9.74423 8.0148 9.60765 8.15137L5.75711 12.0005L9.60765 15.8496C9.74423 15.9861 9.82096 16.1714 9.82096 16.3645C9.82096 16.5577 9.74423 16.7429 9.60765 16.8795C9.47108 17.016 9.28584 17.0928 9.09269 17.0928C8.89955 17.0928 8.71431 17.016 8.57774 16.8795L4.21369 12.5154Z" fill="black" fill-opacity="0.6"></path>
        </svg>
    </span>
    <a href="<?php echo esc_url($back_link); ?>" class="breadcrumb-link">All cards</a>
    <span class="breadcrumb-separator">
        <svg width="21" height="24" viewBox="0 0 21 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7.9502 13.4746C7.9502 14.1764 8.20085 14.778 8.70215 15.2793C9.20345 15.776 9.80501 16.0244 10.5068 16.0244C11.2087 16.0244 11.8102 15.776 12.3115 15.2793C12.8083 14.778 13.0566 14.1764 13.0566 13.4746C13.0566 12.7728 12.8083 12.1735 12.3115 11.6768C11.8102 11.1755 11.2087 10.9248 10.5068 10.9248C9.80501 10.9248 9.20345 11.1755 8.70215 11.6768C8.20085 12.1735 7.9502 12.7728 7.9502 13.4746Z" fill="black" fill-opacity="0.6"></path>
        </svg>
    </span>
    <span class="breadcrumb-current"><?php echo esc_html($category_name); ?></span>
</div>
<?php endif; ?>

<?php if (is_tax('product_tag')) {
// echo 'asadd';
}

if (is_product_category()) {

    $term = get_queried_object();
    $category_id = $term->term_id;
    $category_slug = $term->slug;
    $category_name = $term->name;
    $category_desc = term_description($category_id, 'product_cat');

    // Category thumbnail (used as hero image)
    $thumb_id = get_term_meta($category_id, 'thumbnail_id', true);
    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';

?>

<div class="category-page-wrapper">


    <?php
    /**
     * HERO CONTENT
     * We rely on the [home_slider] shortcode itself to read ACF:
     * - First from the current product category term (home_slider field)
     * - If empty, from the Theme Options page (home_slider on 'option')
     */
    echo do_shortcode('[home_slider]');
?>
    <div class="cat-info-wrapper">
        <div class="brand-title-wrapper  ">
            <div class="brand-title-media">
                <?php if ($thumb_url): ?>
                <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($category_name); ?>" />
                <?php
    else: ?>
                <!-- Placeholder, frontend can replace with illustration -->
                <div class="hero-placeholder"></div>
                <?php
    endif; ?>
            </div>

            <?php if ($category_name): ?>
            <h2 class="brand-hero-subtitle">
                <?php echo wp_kses_post($category_name); ?>
            </h2>
            <?php
    endif; ?>
        </div>
        <!-- BEST SELLERS SECTION -->
        <div class="best-seller-wrap home-trending-toppickup-section-wrp section-spacing">
            <div class="category-section-header">
                <h2>
                    <?php esc_html_e('Best Sellers', 'twentytwentyone'); ?>
                </h2>
            </div>
            <?php echo do_shortcode('[trending_now_carousel]'); ?>
        </div>
    </div>
    <div class="section-gift-choice  section-spacing brand-page-gc_pro cat-single-page-gc_pro">
        <?php echo do_shortcode('[gc_products posts_per_page="16" gc-giftcards-for="1" gc-occasion="1" gc-sort="1"]'); ?>
    </div>
    <div class="the-perfect-section-wrap section-spacing">
        <?php echo do_shortcode('[product_tag_carousel]'); ?>
    </div>

    <!-- <div class="business-help-section-wrap ">
            <?php

    // $templates = get_option('wpb_js_templates');
    $templates = get_option('wpb_js_templates');

    // if (!empty($templates)) {
    //     foreach ($templates as $template) {
    //         if (!empty($template['name']) && $template['name'] === 'shop-business-template') {
    //             echo do_shortcode($template['template']);
    //             break; // stop once found
    //         }
    //     }
    // } ?>
        </div> -->

    <div class="need-help test2">
        <?php
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
<?


} else if (is_tax('product_brand')) {


    $term = get_queried_object();
    $brand_id = $term->term_id;
    $brand_slug = $term->slug;
    $brand_name = $term->name;
    $brand_desc = term_description($brand_id, 'product_brand');

    // Category thumbnail (used as hero image)
    $thumb_id = get_term_meta($brand_id, 'thumbnail_id', true);
    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';

    ?>

        <div class="brand-page-wrapper">

            <?php
    echo do_shortcode('[home_slider]');
?>


<div class="brand-title-wrapper  ">
    <div class="brand-title-media">
        <?php if ($thumb_url): ?>
        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($brand_name); ?>" />
        <?php
    else: ?>
        <!-- Placeholder, frontend can replace with illustration -->
        <div class="hero-placeholder"></div>
        <?php
    endif; ?>
    </div>

    <?php if ($brand_name): ?>
    <h2 class="brand-hero-subtitle">
        <?php echo wp_kses_post($brand_name); ?>
    </h2>
    <?php
    endif; ?>
</div>


<div class="section-gift-choice section-spacing brand-page-gc_pro brand-single-page-gc_pro">
    <?php echo do_shortcode('[gc_products posts_per_page="4" gc-sort="1" gc-filter-by="1"]'); ?>
</div>

<!-- //Add here best seller code -->
<div class="best-seller-section home-trending-toppickup-section-wrp section-spacing">
    <h2>Best Sellers</h2>
    <?php echo do_shortcode('[trending_now_carousel]'); ?>
</div>
<div class="the-perfect-section-wrap section-spacing">
    <?php echo do_shortcode('[product_tag_carousel]'); ?>
</div>

<div class="business-help-section-wrap ">
    <?php

    $templates = get_option('wpb_js_templates');

    if (!empty($templates)) {
        foreach ($templates as $template) {
            if (!empty($template['name']) && $template['name'] === 'shop-business-template') {
                echo do_shortcode($template['template']);
                break; // stop once found
            }
        }
    }?>
</div>

<div class="need-help test">
    <?php
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

<?php
}
else {
    /**
     * Fallback – non-taxonomy product archives (e.g. main shop)
     * Use standard WooCommerce content.
     */
?>
<div class="woocommerce-archive-wrapper default-max-width">
    <?php woocommerce_content(); ?>
</div>
<?php
}

get_footer();