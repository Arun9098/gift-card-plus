<?php
/* Template Name: Product Categories */

get_header();
?>

<div class="product-categories-wrapper">
    <div class="container">
        <h1>Shop by Category</h1>
        <div class="product-categories-grid">
            <?php
            $args = array(
                'taxonomy'   => 'product_cat',
            );

            $product_categories = get_terms($args);

            if (!empty($product_categories) && !is_wp_error($product_categories)) {
                foreach ($product_categories as $category) {
                    $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
                    $image_url = wp_get_attachment_url($thumbnail_id);
                    $term_link = get_term_link($category);
                    ?>
                    <div class="product-category">
                        <a href="<?php echo esc_url($term_link); ?>">
                            <?php if ($image_url): ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($category->name); ?>">
                            <?php endif; ?>
                            <h2><?php echo esc_html($category->name); ?></h2>
                        </a>
                    </div>
                    <?php
                }
            } else {
                echo '<p>No categories found.</p>';
            }
            ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>
