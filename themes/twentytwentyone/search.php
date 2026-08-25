<?php
/**
 * The template for displaying search results pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

get_header();

if ( have_posts() ) {
	?>
	<header class="page-header alignwide">
		<h1 class="page-title">
			<?php
			printf(
				/* translators: %s: Search term. */
				esc_html__( 'Results for "%s"', 'twentytwentyone' ),
				'<span class="page-description search-term">' . esc_html( get_search_query() ) . '</span>'
			);
			?>
		</h1>
	</header><!-- .page-header -->

	<div class="search-result-count default-max-width">
		<?php
		printf(
			esc_html(
				/* translators: %d: The number of search results. */
				_n(
					'We found %d result for your search.',
					'We found %d results for your search.',
					(int) $wp_query->found_posts,
					'twentytwentyone'
				)
			),
			(int) $wp_query->found_posts
		);
		?>
	</div><!-- .search-result-count -->
	
	<?php
	// Display search results in gift card grid format
	?>
	<div class="gc-carousel default-max-width" style="margin-top: 40px;">
		<div class="gc-slide">
			<?php
			// Start the Loop.
			while ( have_posts() ) {
				the_post();
				global $product;
				
				// Check if it's a product post type
				if ( get_post_type() === 'product' ) {
					$product = wc_get_product( get_the_ID() );
					
					echo '
						<div class="gc-card">
							<a href="' . esc_url( get_permalink() ) . '" style="text-decoration: none; color: inherit;">
								<div class="gc-img">' . ( $product ? $product->get_image( 'full' ) : get_the_post_thumbnail( get_the_ID(), 'full' ) ) . '</div>
								<p class="gc-title">' . get_the_title() . '</p>
							</a>
						</div>
					';
				} else {

					if (has_post_thumbnail()) {
						$thumb = get_the_post_thumbnail_url(get_the_ID(), 'medium');
					} else {
						$thumb = get_stylesheet_directory_uri() . '/assets/images/page-default.png';
					}
					?>
					<div class="gc-card">
						<a href="<?php echo esc_url( get_permalink() ); ?>" style="text-decoration: none; color: inherit;">
							<div class="gc-img" style="background-image: url('<?php echo esc_url($thumb); ?>'); background-size: cover; background-position: center;"></div>
							<p class="gc-title"><?php echo esc_html( get_the_title() ); ?></p>
						</a>
					</div>
					<?php
				}
			} // End the loop.
			?>
		</div>
	</div>
	
	<?php
	// Previous/next page navigation.
	twenty_twenty_one_the_posts_navigation();
	?>

	<?php
	// If no content, include the "No posts found" template.
} else {
	get_template_part( 'template-parts/content/content-none' );
}

get_footer();
