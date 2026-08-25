<?php
defined( 'ABSPATH' ) || exit;

// Get current user's wishlist
// $user_id = get_current_user_id();
// $wishlist = get_user_meta($user_id, 'user_wishlist', true);

// if (!is_array($wishlist)) {
//     $wishlist = array();
// }

// // Remove empty values
// $wishlist = array_filter(array_map('intval', $wishlist));

// // Query products from wishlist
// $products = array();
// if (!empty($wishlist)) {
//     $args = array(
//         'post_type'      => 'product',
//         'post_status'    => 'publish',
//         'post__in'       => $wishlist,
//         'posts_per_page' => -1,
//         'orderby'        => 'post__in', // Maintain wishlist order
//     );
    
//     $query = new WP_Query($args);
    
//     if ($query->have_posts()) {
//         while ($query->have_posts()) {
//             $query->the_post();
//             global $product;
//             $products[] = array(
//                 'id'    => get_the_ID(),
//                 'title' => get_the_title(),
//                 'image' => $product->get_image('medium'),
//                 'url'   => get_permalink(),
//                 'tags'  => gc_get_product_tags(get_the_ID())
//             );
//         }
//     }
//     wp_reset_postdata();
// } ?>

<?php 
echo do_shortcode('[gc_products posts_per_page="16" gc-giftcards-for="1" gc-occasion="1" gc-sort="1"]');

?>
<?php $templates = get_option('wpb_js_templates'); ?>
    <div class="page-wishlist shop-business-template-2c-need-help-wrap">
    <?php
    if (!empty($templates)) {
        // echo 'Hiiii';
        // print_r($templates);
        foreach ($templates as $template) {
            if (!empty($template['name']) && $template['name'] === 'template-need-help') {
                echo do_shortcode($template['template']);
                break; // stop once found
            }
        }
    } ?>
</div>

