<?php
/**
 * Template Name: Edit Offer
 * 
 * Frontend page for editing offers
 */

// Check if user is administrator
if (!current_user_can('administrator')) {
    wp_die('You do not have permission to access this page.');
}

// Get offer ID from URL
$offer_id = isset($_GET['offer_id']) ? intval($_GET['offer_id']) : 0;

if (!$offer_id) {
    wp_die('Invalid offer ID.');
}

$offer = get_post($offer_id);
if (!$offer || $offer->post_type !== 'offer') {
    wp_die('Offer not found.');
}

get_header();
?>

<div class="page-content offer-page-frontend">
    <?php
    // Get offer meta
    $meta = get_offer_meta($offer_id);
    
    // Parse dates - Convert from YYYY-MM-DD to MM/DD/YYYY
    if (!empty($meta['start_date'])) {
        $start_parts = explode(' ', $meta['start_date']);
        $date_part = $start_parts[0];
        // Convert YYYY-MM-DD to MM/DD/YYYY
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $date_part, $matches)) {
            $date_part = $matches[2] . '/' . $matches[3] . '/' . $matches[1];
        }
        $meta['start_date_only'] = $date_part;
        $meta['start_time_only'] = isset($start_parts[1]) ? $start_parts[1] : '16:00';
    } else {
        $meta['start_date_only'] = '';
        $meta['start_time_only'] = '16:00';
    }
    if (!empty($meta['end_date'])) {
        $end_parts = explode(' ', $meta['end_date']);
        $date_part = $end_parts[0];
        // Convert YYYY-MM-DD to MM/DD/YYYY
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $date_part, $matches)) {
            $date_part = $matches[2] . '/' . $matches[3] . '/' . $matches[1];
        }
        $meta['end_date_only'] = $date_part;
        $meta['end_time_only'] = isset($end_parts[1]) ? $end_parts[1] : '21:00';
    } else {
        $meta['end_date_only'] = '';
        $meta['end_time_only'] = '21:00';
    }
    
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
    $GLOBALS['is_edit'] = true;
    
    include get_template_directory() . '/frontend-templates/offer-form.php';
    ?>
</div>

<?php
get_footer();

