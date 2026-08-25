<?php

function gc_trim_chars( $text, $max_chars = 25, $ellipsis = '...' ) {
    $text = trim( (string) $text );

    // Use mb_* if available for UTF-8 safety
    if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
        if ( mb_strlen( $text ) <= $max_chars ) {
            return $text;
        }
        return mb_substr( $text, 0, $max_chars ) . $ellipsis;
    } else {
        if ( strlen( $text ) <= $max_chars ) {
            return $text;
        }
        return substr( $text, 0, $max_chars ) . $ellipsis;
    }
}

// Shortcode: Category list + search bar + live search
function custom_giftcard_category_display() {
    ob_start();

    // wp_enqueue_script('instant-search', false, [], false, true);
    if ( is_user_logged_in() ) {
     ?>

    <!-- <div style="position:relative;">
        <form role="search" method="get" class="giftcard-search" action="<?php //echo home_url('/'); ?>">
            <input type="search" name="s" id="instant-cat-search" class="giftcard-search-input" placeholder="Search egift cards" value="<?php //echo get_search_query(); ?>" />
            <span class="clear-search" id="clear-search">&times;</span>
            <button type="submit" class="giftcard-search-button">
                <span class="search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M21.8189 23.6359L16.6812 18.4895C15.8734 19.1907 14.9309 19.7369 13.8539 20.128C12.7768 20.5191 11.6324 20.7147 10.4207 20.7147C7.51259 20.7147 5.04878 19.7032 3.02927 17.6803C1.00976 15.6574 0 13.2164 0 10.3573C0 7.49828 1.00976 5.05729 3.02927 3.03438C5.04878 1.01146 7.49913 0 10.3803 0C13.2345 0 15.6647 1.01146 17.6707 3.03438C19.6768 5.05729 20.6798 7.49828 20.6798 10.3573C20.6798 11.5171 20.4913 12.6365 20.1143 13.7154C19.7374 14.7943 19.1719 15.8057 18.418 16.7498L23.6365 21.8962C23.8788 22.1119 24 22.3884 24 22.7256C24 23.0627 23.8654 23.3662 23.5961 23.6359C23.3538 23.8786 23.0576 24 22.7075 24C22.3575 24 22.0613 23.8786 21.8189 23.6359ZM10.3803 18.2872C12.5614 18.2872 14.4193 17.5117 15.9542 15.9608C17.489 14.4099 18.2564 12.5421 18.2564 10.3573C18.2564 8.17259 17.489 6.30476 15.9542 4.75386C14.4193 3.20295 12.5614 2.4275 10.3803 2.4275C8.1723 2.4275 6.29415 3.20295 4.74586 4.75386C3.19756 6.30476 2.42342 8.17259 2.42342 10.3573C2.42342 12.5421 3.19756 14.4099 4.74586 15.9608C6.29415 17.5117 8.1723 18.2872 10.3803 18.2872Z" fill="#202224"/>
                    </svg>    
                </span>
            </button>
        </form>

        <div class="instant-results" id="instant-results"></div>
    </div> -->
     <?php
    }
    ?>

    <div class="giftcard-category-wrapper owl-carousel">
    <?php
    $args = array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => 0,
        'meta_query' => array(
        array(
            'key'     => 'category_status', // ACF field name
            'value'   => 'active',
            'compare' => '='
        )
    )
    );

    $categories = get_terms($args);

    usort($categories, function ($a, $b) {
        $priority_a = (int) get_term_meta($a->term_id, 'priority', true);
        $priority_b = (int) get_term_meta($b->term_id, 'priority', true);

        // Default to 9999 if empty
        $priority_a = $priority_a ?: 9999;
        $priority_b = $priority_b ?: 9999;

        return $priority_a <=> $priority_b;
    });

    foreach ($categories as $cat) {
        $thumbnail_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
        $image_url = wp_get_attachment_url($thumbnail_id);
        ?>
        <div class="giftcard-category-item">
            <a href="<?php echo get_term_link($cat); ?>">
                <div class="giftcard-category-icon">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($cat->name); ?>">
                </div>
                <p class="gc-cat-title" data-fulltext="<?php echo esc_attr($cat->name); ?>">
                    <?php echo esc_html($cat->name); ?>
                </p>
            </a>
        </div>
        <?php
    }
    ?>
    </div>
    <?php
    if ( is_user_logged_in() ) {?>

    <!-- <div class="giftcard-shop-all"> -->
         <!-- <a class="shop-all-btn" href="<?php //echo home_url('/brands'); ?>">
            <span class="shop-all-btn-desktop">Shop All</span>
            <span class="shop-all-btn-mobile">Browse categories</span>
        </a> -->
    <!-- </div> -->
     <?php
    }
    
    return ob_get_clean();
}
add_shortcode('giftcard_categories', 'custom_giftcard_category_display');

// Home Slider — supports "text banner" (home_slider) or "image banner" (desktop_image_banner) per ACF radio.
function global_home_slider_shortcode() {
    if ( ! function_exists( 'get_field' ) ) {
        return '';
    }

    /* ---------------- CONTEXT ---------------- */
    $context = null;

    if ( is_product_category() ) {
        $term = get_queried_object();
        if ( $term && isset( $term->taxonomy ) ) {
            $context = 'product_cat_' . $term->term_id;
        }
    } elseif ( is_tax( 'product_brand' ) ) {
        $term = get_queried_object();
        if ( $term && isset( $term->taxonomy ) ) {
            $context = 'product_brand_' . $term->term_id;
        }
    } elseif ( is_singular() ) {
        $context = get_queried_object_id();
    }

    $options_context = 'option';

    /* ---------------- GET BANNER TYPE ---------------- */
    $text_banner = $context ? get_field( 'text_banner', $context ) : '';
    if ( empty( $text_banner ) ) {
        $text_banner = get_field( 'text_banner' );
    }
    if ( empty( $text_banner ) ) {
        $text_banner = get_field( 'text_banner', $options_context );
    }

    $type_value = is_array($text_banner) && isset($text_banner['value'])
        ? strtolower(trim($text_banner['value']))
        : strtolower(trim((string)$text_banner));

    $is_image_banner = ( $type_value === 'image banner' );

    /* ================================================================
       IMAGE BANNER BRANCH
    ================================================================ */
    if ( $is_image_banner ) {

        /* ---------------- DESKTOP SLIDES ---------------- */
        $image_slides = $context ? get_field('desktop_image_banner', $context) : [];
        if ( empty($image_slides) ) {
            $image_slides = get_field('desktop_image_banner');
        }
        if ( empty($image_slides) ) {
            $image_slides = get_field('desktop_image_banner', $options_context);
        }

        if ( ! is_array($image_slides) || empty($image_slides) ) {
            return '';
        }

        /* ---------------- MOBILE SLIDES ---------------- */
        $mobile_slides = $context ? get_field('mobile_image_banner', $context) : [];
        if ( empty($mobile_slides) ) {
            $mobile_slides = get_field('mobile_image_banner');
        }
        if ( empty($mobile_slides) ) {
            $mobile_slides = get_field('mobile_image_banner', $options_context);
        }

        if ( ! is_array($mobile_slides) ) {
            $mobile_slides = [];
        }

        /* ---------------- WRAPPER STYLE (first slide) ---------------- */
        $first_img = $image_slides[0]['banner_image'] ?? '';
        $first_desktop_url = '';

        if ( is_array($first_img) && !empty($first_img['url']) ) {
            $first_desktop_url = $first_img['url'];
        } elseif ( is_string($first_img) && !empty($first_img) ) {
            $first_desktop_url = $first_img;
        } elseif ( is_numeric($first_img) ) {
            $first_desktop_url = wp_get_attachment_image_url((int)$first_img, 'full');
        }

        $first_mobile_img = $mobile_slides[0]['banner_image'] ?? '';
        $first_mobile_url = '';

        if ( is_array($first_mobile_img) && !empty($first_mobile_img['url']) ) {
            $first_mobile_url = $first_mobile_img['url'];
        } elseif ( is_string($first_mobile_img) && !empty($first_mobile_img) ) {
            $first_mobile_url = $first_mobile_img;
        } elseif ( is_numeric($first_mobile_img) ) {
            $first_mobile_url = wp_get_attachment_image_url((int)$first_mobile_img, 'full');
        }

        $wrapper_style = '';
        if ( !empty($first_desktop_url) ) {
            $wrapper_style = "--slide-banner-bg-image: url('{$first_desktop_url}');";
        }
        if ( !empty($first_mobile_url) ) {
            $wrapper_style .= "--slide-banner-mobile-image: url('{$first_mobile_url}');";
        }

        /* ---------------- OUTPUT ---------------- */
        $slide_count = count($image_slides);

        $wrapper_class = ($slide_count > 1)
                ? 'owl-carousel home-slider home-slider--image-banner'
                : 'home-slider home-slider--image-banner home-single-banner';

            /* Add full-width-slider if image banner */
            if ( $is_image_banner ) {
                $wrapper_class .= ' full-width-slider';
            }

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $wrapper_class . ' banner-bg-section' ); ?>" style="<?php echo esc_attr($wrapper_style); ?>">
        <?php foreach ( $image_slides as $index => $row ) :

            /* -------- DESKTOP -------- */
            $img = $row['banner_image'] ?? '';
            $desktop_url = '';

            if ( is_array($img) && !empty($img['url']) ) {
                $desktop_url = $img['url'];
            } elseif ( is_string($img) ) {
                $desktop_url = $img;
            } elseif ( is_numeric($img) ) {
                $desktop_url = wp_get_attachment_image_url((int)$img, 'full');
            }

            if ( empty($desktop_url) ) continue;

            /* -------- MOBILE -------- */
           $m_img = $mobile_slides[$index]['banner_image'] ?? '';   

             $mobile_url = '';

            if ( !empty($m_img) ) {
                if ( is_array($m_img) && !empty($m_img['url']) ) {
                    $mobile_url = $m_img['url'];
                } elseif ( is_string($m_img) ) {
                    $mobile_url = $m_img;
                } elseif ( is_numeric($m_img) ) {
                    $mobile_url = wp_get_attachment_image_url((int)$m_img, 'full');
                }
            }


            if ( empty($mobile_url) ) {
                $mobile_url = $desktop_url;
            }

            /* -------- BANNER LINK (per slide) -------- */
            $raw_link     = $row['banner_link'] ?? '';
            $banner_link   = '';
            $banner_target = '';
            if ( is_array( $raw_link ) ) {
                $banner_target = ! empty( $raw_link['target'] ) ? esc_attr( $raw_link['target'] ) : '';
                $banner_link   = ! empty( $raw_link['url'] ) ? esc_url( $raw_link['url'] ) : '';
            } elseif ( ! empty( $raw_link ) ) {
                $banner_link = esc_url( $raw_link );
            }

            /* -------- STYLE PER SLIDE -------- */
            $slide_style = "--slide-banner-bg-image: url('{$desktop_url}');";
            $slide_class = "desktop-banner";

            if ( !empty($mobile_url) ) {
                $slide_style .= "--slide-banner-mobile-image: url('{$mobile_url}');";
                $slide_class = "mobile-banner";
            }
            ?>
                <!-- <div class="slider-item">
                    <div class="slider-image image-banner <?php //echo $slide_class; ?>" style="<?php //echo esc_attr($slide_style); ?>"></div>
                </div> -->

                <div class="slider-item">

                    <?php if ( $banner_link ) : ?>
                    <a href="<?php echo $banner_link; ?>" class="banner-link-wrapper" style="display:block;"<?php echo $banner_target ? " target=\"{$banner_target}\"" : ''; ?>>
                    <?php endif; ?>

                    <div class="slider-image image-banner">
                        <?php if ( !empty($desktop_url) ) : ?>
                            <img class="desktop-img" src="<?php echo esc_url($desktop_url); ?>" alt="Banner Image">
                        <?php endif; ?>

                        <?php if ( !empty($mobile_url) ) : ?>
                            <img class="mobile-img" src="<?php echo esc_url($mobile_url); ?>" alt="Mobile Banner">
                        <?php endif; ?>

                    </div>

                    <?php if ( $banner_link ) : ?>
                    </a>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
            </div>

        <?php if ( $slide_count > 1 ) : ?>
        <script>
        jQuery(function($){
            $('.home-slider').owlCarousel({
                items: 1,
                loop: true,
                dots: true,
                autoplay: false,
                margin: 0,
                smartSpeed: 500
            });
        });
        </script>
        <?php endif;

        return ob_get_clean();
    }

    /* ================================================================
       TEXT BANNER BRANCH
    ================================================================ */
    $slides = array();

    if ( $context ) {
        $slides = get_field( 'home_slider', $context );
    }
    if ( empty( $slides ) && ! $context && is_tax( 'product_brand' ) ) {
        $term = get_queried_object();
        if ( $term && isset( $term->taxonomy ) && $term->taxonomy === 'product_brand' ) {
            $slides = get_field( 'home_slider', 'product_brand_' . $term->term_id );
        }
    }
    if ( empty( $slides ) && ! is_product_category() && ! is_tax( 'product_brand' ) ) {
        $slides = get_field( 'home_slider' );
    }
    if ( empty( $slides ) ) {
        $slides = get_field( 'home_slider', $options_context );
    }

    if ( ! is_array( $slides ) ) {
        $slides = array();
    }

    $slides = array_filter( $slides, function( $slide ) {
        if ( empty( $slide ) || ! is_array( $slide ) ) {
            return false;
        }
        $has_title = ! empty( $slide['slide_title'] );
        $has_image = ! empty( $slide['slide_image'] ) && ! empty( $slide['slide_image']['url'] );
        return $has_title && $has_image;
    } );

    if ( empty( $slides ) ) {
        return '';
    }

    $slide_count = count( $slides );
    $wrapper_class = ( $slide_count > 1 )
        ? 'owl-carousel home-slider'
        : 'home-slider home-single-banner';

    $banner_bg_color = ! empty( $slides[0]['banner_bg_color'] ) ? $slides[0]['banner_bg_color'] : '#E0F6FF';

    ob_start();
    echo '<!-- BG COLOR: ' . esc_html( $banner_bg_color ) . ' -->';
    echo "<style>
        :root {
            --slide-banner-bg-color: {$banner_bg_color};
        }
    </style>";
    ?>
    <div class="<?php echo esc_attr( $wrapper_class . ' banner-bg-section' ); ?>">
    <?php foreach ( $slides as $slide ) : ?>
        <div class="slider-item">
            <div class="slider-content">
                <?php if ( ! empty( $slide['slide_title'] ) ) : ?>
                    <h2><?php echo wp_kses_post( $slide['slide_title'] ); ?></h2>
                <?php endif; ?>
                <?php if ( ! empty( $slide['slide_desc'] ) ) : ?>
                    <p><?php echo esc_html( $slide['slide_desc'] ); ?></p>
                <?php endif; ?>
                <?php if ( ! empty( $slide['button_text'] ) && ! empty( $slide['button_link'] ) ) : ?>
                    <div class="shop-card-banner-btn">
                        <a href="<?php echo esc_url( $slide['button_link'] ); ?>" class="btn-black-p2 btn btn-primary">
                            <?php echo esc_html( $slide['button_text'] ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ( ! empty( $slide['slide_image'] ) ) : ?>
                <div class="slider-image">
                    <img src="<?php echo esc_url( $slide['slide_image']['url'] ); ?>" alt="<?php echo esc_attr( ! empty( $slide['slide_title'] ) ? $slide['slide_title'] : '' ); ?>">
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <?php if ( $slide_count > 1 ) : ?>
    <script>
    jQuery(function($){
        setTimeout(function(){
            $('.home-slider').owlCarousel({
                items: 1,
                loop: true,
                dots: true,
                autoplay: false,
                margin: 0,
                smartSpeed: 500
            });
        }, 300);
    });
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('home_slider', 'global_home_slider_shortcode');
