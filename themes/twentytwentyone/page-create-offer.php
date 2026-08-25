<?php
/**
 * Template Name: Create Offer
 * 
 * Frontend page for creating offers
 */

// Check if user is administrator
if (!current_user_can('administrator')) {
    wp_die('You do not have permission to access this page.');
}

get_header();
?>

<div class="page-content offer-page-frontend">
    <?php
    // Include the offer form template
    $is_edit = false;
    $offer_id = 0;
    $offer = null;
    $meta = [
        'description' => '',
        'image_id' => '',
        'showcase_type' => '',
        'promo_code' => '',
        'link' => '',
        'terms' => '',
        'flag' => '',
        'tags' => [],
        'start_date_only' => '',
        'start_time_only' => '16:00',
        'end_date_only' => '',
        'end_time_only' => '21:00',
        'always_on' => false,
        'audience' => '',
        'products' => [],
        'all_products' => false,
    ];
    
    // Enqueue scripts and styles
    wp_enqueue_media();
    wp_enqueue_editor(); // Enqueue editor scripts for WYSIWYG
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
    wp_enqueue_script('offer-frontend', get_template_directory_uri() . '/assets/js/offer-frontend.js', ['jquery', 'jquery-ui-datepicker'], time(), true);
    wp_enqueue_style('offer-frontend', get_template_directory_uri() . '/assets/css/offer-frontend.css', [], time());
    
    // Make variables available to template
    $GLOBALS['offer_id'] = $offer_id;
    $GLOBALS['offer'] = $offer;
    $GLOBALS['meta'] = $meta;
    $GLOBALS['is_edit'] = $is_edit;
    
    include get_template_directory() . '/frontend-templates/offer-form.php';
    ?>
</div>

<?php
get_footer();

