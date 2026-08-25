<?php
/**
 * Custom Header File: header-special.php
 * For Twenty Twenty-One child theme or modified parent theme.
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="fontend-user-header" style="padding:20px 0; border-bottom:1px solid #eee;">
    <div class="fontend-user-header__inner" style="max-width:1200px; margin:0 auto; display:flex; align-items:center; justify-content:space-between;">

        <!-- Dynamic Logo -->
        <div class="fontend-user-header__logo">
            <a href="<?php echo esc_url( home_url('/') ); ?>" title="<?php bloginfo('name'); ?>">
                <?php
                if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
                    the_custom_logo();
                } else {
                    echo '<span style="font-size:24px; font-weight:bold;">' . get_bloginfo('name') . '</span>';
                }
                ?>
            </a>
        </div>

        <!-- Dynamic Menu -->
        <nav class="fontend-user-header_nav" role="navigation" aria-label="Special Menu">
            <?php
            wp_nav_menu( array(
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => 'special-menu',
                'fallback_cb' => false,
            ) );
            ?>
        </nav>

    </div>
</header>