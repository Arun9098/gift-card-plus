<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

?>
			</main><!-- #main -->
		</div><!-- #primary -->
	</div><!-- #content -->

	<?php get_template_part( 'template-parts/footer/footer-widgets' ); ?>

		<?php
		$template = basename(get_page_template_slug(), '.php');
		$restricted_templates = [
			'gift-card-form-2',
			'brands-listing',
			'bulk-create-category',
			'bulk-create-product',
			'custom-reset-password',
			'template-email-logs',
			'template-email-settings',
			'invoice-display',
			'manual-order',
			'page-offers-list',
			'page-edit-offer',
			'product-categories',
			'review-products',
			'sms-settings',
			'supplier-login',
			'supplier-registration',
			'users',
			'reports',
			'product-listing',
			'product-listing-section'
		];

		// Build class dynamically
		$extra_footer_class = (in_array($template, $restricted_templates) || is_page(33) || is_page('gift-card-detail') )
			? ' p1-footer'
			: '';
		?>

		<footer id="colophon" class="site-footer<?php echo $extra_footer_class; ?>">

		<?php 

		$template = basename(get_page_template_slug(), '.php');
		$restricted_templates = [
			'gift-card-form-2',
			'brands-listing',
			'bulk-create-category',
			'bulk-create-product',
			'custom-reset-password',
			'template-email-logs',
			'template-email-settings',
			'invoice-display',
			'manual-order',
			'page-offers-list',
			'page-edit-offer',
			'product-categories',
			'review-products',
			'sms-settings',
			'supplier-login',
			'supplier-registration',
			'users',
			'reports',
			'product-listing',
			'product-listing-section'
		];

		if (in_array($template, $restricted_templates) || is_page(33) || is_page('gift-card-detail') ) {

			?>
			<div class="container p1-footer">
			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav aria-label="<?php esc_attr_e( 'Secondary menu', 'twentytwentyone' ); ?>" class="footer-navigation">
					<ul class="footer-navigation-wrapper">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'footer',
								'items_wrap'     => '%3$s',
								'container'      => false,
								'depth'          => 1,
								'link_before'    => '<span>',
								'link_after'     => '</span>',
								'fallback_cb'    => false,
							)
						);
						?>
					</ul><!-- .footer-navigation-wrapper -->
				</nav><!-- .footer-navigation -->
			<?php endif; ?>
			<div class="site-info">
				<div class="site-name">
						<?php if ( get_bloginfo( 'name' ) && get_theme_mod( 'display_title_and_tagline', true ) ) : ?>
							<?php if ( is_front_page() && ! is_paged() ) : ?>
								<?php bloginfo( 'name' ); ?>
							<?php else : ?>
								<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
					
				</div><!-- .site-name -->

				<?php
				if ( function_exists( 'the_privacy_policy_link' ) ) {
					the_privacy_policy_link( '<div class="privacy-policy">', '</div>' );
				}
				?>

				<div class="powered-by">
			        ©<?php echo date('Y'); ?> <strong>giftcards</strong><em>plus</em> Pty Ltd. All Rights Reserved.
				</div><!-- .powered-by -->

			</div><!-- .site-info -->
			</div>
			<?php
		}
		else{ ?>
			<div class="container">
				<div class="footer-main-wrapper">
				<!-- Site Info -->
					<div class="site-info">
						<div class="site-name">
							<?php if ( has_custom_logo() ) : ?>
								<div class="site-logo"><?php //the_custom_logo(); ?>
								<span class="custom-logo-link">
									<img src="<?php echo site_url(); ?>/wp-content/uploads/2026/02/Transparent-Main-Logo-1-scaled.png" class="custom-logo" width="180" height="49" alt="Gift Card" decoding="async">
								</span>
							</div>
							<?php else : ?>
								<?php if ( get_bloginfo( 'name' ) && get_theme_mod( 'display_title_and_tagline', true ) ) : ?>
									<?php if ( is_front_page() && ! is_paged() ) : ?>
										<?php bloginfo( 'name' ); ?>
									<?php else : ?>
										<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
									<?php endif; ?>
								<?php endif; ?>
							<?php endif; ?>
						</div><!-- .site-name -->

						<?php
						if ( function_exists( 'the_privacy_policy_link' ) ) {
							the_privacy_policy_link( '<div class="privacy-policy">', '</div>' );
						}
						?>

						<!-- Social Icons -->
						<div class="social-icons">
							<?php if ( get_theme_mod( 'mytheme_facebook_url' ) ) : ?>
								<a href="https://www.facebook.com/people/Gift-Cards-Plus/61577001734149/" target="_blank">
									<img src="<?php echo get_template_directory_uri(); ?>/assets/images/fabicon.png" alt="Facebook">
								</a>
							<?php endif; ?>

							<!-- <?php //if ( get_theme_mod( 'mytheme_twitter_url' ) ) : ?>
								<a href="<?php //echo esc_url( get_theme_mod( 'mytheme_twitter_url' ) ); ?>" target="_blank">
									<img src="<?php //echo get_template_directory_uri(); ?>/assets/images/twiticon.png" alt="Twitter">
								</a>
							<?php //endif; ?> -->

							<?php if ( get_theme_mod( 'mytheme_instagram_url' ) ) : ?>
								<a href="https://www.instagram.com/giftcardsplus/?igsh=MWlqczIwZnhwc2xoZg%3D%3D" target="_blank">
									<img src="<?php echo get_template_directory_uri(); ?>/assets/images/instagramicon.png" alt="Instagram">
								</a>
							<?php endif; ?>

							<?php if ( get_theme_mod( 'mytheme_linkedin_url' ) ) : ?>
								<a href="https://www.linkedin.com/company/gift-cards-plus/" target="_blank">
									<img src="<?php echo get_template_directory_uri(); ?>/assets/images/linkdinicon.png" alt="LinkedIn">
								</a>
							<?php endif; ?>
						</div><!-- .social-icons -->
					</div><!-- .site-info -->

					<!-- Footer Navigation -->
					<div class="footer-navigation-container">
						<?php if ( has_nav_menu( 'footer' ) ) : ?>
							<nav aria-label="<?php esc_attr_e( 'Footer menu', 'your-theme' ); ?>" class="footer-navigation">
								<ul class="footer-navigation-wrapper">
									<?php
									wp_nav_menu(
										array(
											'theme_location' => 'footer',
											'items_wrap'     => '%3$s',
											'container'      => false,
											'depth'          => 1,
											'link_before'    => '<span>',
											'link_after'     => '</span>',
											'fallback_cb'    => false,
										)
									);
									?>
								</ul><!-- .footer-navigation-wrapper -->
							</nav><!-- .footer-navigation -->
						<?php endif; ?>
					</div>

					<div class="footer-menus">
						<div class="footer-column">
							<h4><a href="<?php echo site_url('/faq'); ?>">FAQs</a></h4>
							<?php
							if (has_nav_menu('footer-left-menu')) {
								wp_nav_menu(array(
									'theme_location' => 'footer-left-menu',
									'container'      => false,
									'menu_class'     => 'footer-menu',
									'depth'          => 1,
								));
							}
							?>
						</div>

						<div class="footer-column">
							<h4><a href="<?php echo site_url('/about-us'); ?>">About us</a></h4>
							<?php
							if (has_nav_menu('footer-middle-menu')) {
								wp_nav_menu(array(
									'theme_location' => 'footer-middle-menu',
									'container'      => false,
									'menu_class'     => 'footer-menu',
									'depth'          => 1,
								));
							}
							?>
						</div>

						<div class="footer-column">
							<h4><a href="<?php echo site_url('/contact-us'); ?>">Contact us</a></h4>
							<?php
							if (has_nav_menu('footer-right-menu')) {
								wp_nav_menu(array(
									'theme_location' => 'footer-right-menu',
									'container'      => false,
									'menu_class'     => 'footer-menu',
									'depth'          => 1,
								));
							}
							?>
						</div>
					</div>
				</div>

			    <!-- Powered by Section -->
			    <div class="powered-by">
			        ©<?php echo date('Y'); ?> <strong>giftcards</strong><em>plus</em> Pty Ltd. All Rights Reserved.
			    </div>
		    </div>
			<?php
		}
		?>
	</footer><!-- #colophon -->

</div><!-- #page -->

<?php if ( ! current_user_can('administrator') ) : ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var item = document.querySelector('.nav-review-a-product');
        if (item) {
            item.style.display = 'none';
        }
    });
</script>
<?php endif; ?>

<?php wp_footer(); ?>

</body>
</html>
