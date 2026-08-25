<?php
/**
 * Template Name: Offers List
 * 
 * Frontend page for listing all offers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="plus-offer-page wpb-content-wrapper">
    <div class="plus-offer-wrap">
        <div class="slider-wrap">
        <?php echo do_shortcode('[home_slider]'); ?>
        </div>
    </div>
    <div class="plus-offer-sec section-spacing">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/images/plus-offers.png" alt="Plus Offers" class="plus-offer-page-icon">

        <h2>Plus Offers</h2>
    </div>
    <div class="offer-for-you-wrap section-spacing">
        <?php echo do_shortcode('[offer_for_you]'); ?>
    </div>
    <div class="all-plus-offers-wrap section-spacing">
        <?php echo do_shortcode('[gc_products posts_per_page="4" gc-giftcards-for="1" gc-occasion="1" gc-sort="1"]'); ?>
    </div>
    
    <?php $templates = get_option('wpb_js_templates'); ?>

    <div class="tis-the-season-wrap ">
        <?php
            if (!empty($templates)) {
                foreach ($templates as $template) {
                    if (!empty($template['name']) && $template['name'] === 'Tis The Season') {
                        echo do_shortcode($template['template']);
                        break; // stop once found
                    }
                }
            } ?>
    </div>
    <div class="the-perfect-section-wrap section-spacing">
        <?php echo do_shortcode('[product_tag_carousel]'); ?>
    </div>
    <div class="shop-business-template-2c-need-help-wrap">
        <?php
        if (!empty($templates)) {
            // echo 'Hiiii';
            // print_r($templates);
            foreach ($templates as $template) {
                if (!empty($template['name']) && $template['name'] === 'shop-business-template-2c-need-help-wrap') {
                    echo do_shortcode($template['template']);
                    break; // stop once found
                }
            }
        } ?>
    </div>
</div>

<?php get_footer();?> 