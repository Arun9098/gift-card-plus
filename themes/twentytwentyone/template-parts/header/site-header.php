<?php
/**
 * Displays the site header.
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

$wrapper_classes = 'site-header';
$wrapper_classes .= has_custom_logo() ? ' has-logo' : '';
$wrapper_classes .= (true === get_theme_mod('display_title_and_tagline', true)) ? ' has-title-and-tagline' : '';
$wrapper_classes .= has_nav_menu('primary') ? ' has-menu' : '';

// Cart count and URL for header (WooCommerce)
$header_cart_count = 0;
$header_cart_url = '#';
if (function_exists('WC') && WC()->cart && is_object(WC()->cart)) {
	$header_cart_count = WC()->cart->get_cart_contents_count();
	$header_cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : $header_cart_url;
}
?>
<?php
$current_user = wp_get_current_user();
$user_roles = (array) $current_user->roles;
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
	'product-listing-section',
];
$is_order_received_endpoint = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received');
$show_customer_confirmation_header = false;

if ($is_order_received_endpoint) {
	$current_user_roles = (array) wp_get_current_user()->roles;
	$is_current_user_customer = in_array('administrator', $current_user_roles, true);
}


if (in_array($template, $restricted_templates) || is_page('gift-card-detail') || !empty($is_current_user_customer)) {
	// ------- Restricted Header (Version A) -------
	?>
	<header class="custom-header">
		<div class="container">
			<nav class="nav-container">
				<?php
				$current_user = wp_get_current_user();
				$menu_location = '';

				// Check user role
				if (in_array('administrator', $current_user->roles)) {
					$menu_location = 'admin-header-menu';
				} elseif (in_array('supplier', $current_user->roles)) {
					$menu_location = 'suppliers-header-menu';
				} else {
					$menu_location = 'logged-out-header-menu';
				}
				wp_nav_menu([
					'theme_location' => $menu_location,
					'menu_class' => 'nav-menu',
					'container' => false
				]);
				?>

				<div class="admin-profile">
					<?php if (is_user_logged_in()):
						$current_user = wp_get_current_user();
						$profile_img = get_avatar_url($current_user->ID);
						$name = $current_user->display_name;
						?>
						<div class="profile-dropdown-wrapper admin-header-profile-dropdown">
							<div class="header-profile-icon" title="<?php echo esc_attr($name); ?>">
								<img src="<?php echo esc_url($profile_img); ?>" alt="Admin Profile" class="profile-pic" />
							</div>
							<span class="admin-profile-name"><?php echo esc_html($name); ?></span>
							<div class="profile-dropdown admin-header-dropdown">
								<a href="<?php echo esc_url(home_url('/my-account')); ?>" class="dropdown-item">My Profile</a>
								<a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="dropdown-item logout-btn">Logout</a>
							</div>
						</div>
					<?php else: ?>
						<a href="<?php echo esc_url(home_url('/login/')); ?>" class="login-link">Login</a>
					<?php endif; ?>
				</div>
			</nav>
			<div class="header-ovarlay"></div>
			<div class="hamburger-icon">
				<span></span>
				<span></span>
				<span></span>
			</div>
		</div>
	</header>
	<?php

} else {
	// ------- Default Header (Version B, your existing) -------
	?>
	<header class="font-end-user-header">
		<div class="container">
			<!-- Logo -->
			<div class="site-logo">
				<a href="<?php echo home_url(); ?>">
					<?php
					if (function_exists('the_custom_logo')) {
						the_custom_logo();
					}
					?>
				</a>
			</div>
			<nav class="nav-container">
				<?php
				$menu_location = '';

				if (is_user_logged_in()) {
					$menu_location = 'front-end-header-menu';
				} else {
					$menu_location = 'front-end-logout-header-menu';
				}

				wp_nav_menu([
					'theme_location' => $menu_location,
					'menu_class' => 'nav-menu',
					'container' => false
				]);
				?>


			</nav>

			<!-- Search Bar -->
			<?php
			// if(!is_page('user-login') && !is_page('user-registration') && !is_page('forget-password')){
			?>
			<div class="header-search-bar">
				<button type="button" class="toggle-search">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
						<path
							d="M21.8189 23.6359L16.6812 18.4895C15.8734 19.1907 14.9309 19.7369 13.8539 20.128C12.7768 20.5191 11.6324 20.7147 10.4207 20.7147C7.51259 20.7147 5.04878 19.7032 3.02927 17.6803C1.00976 15.6574 0 13.2164 0 10.3573C0 7.49828 1.00976 5.05729 3.02927 3.03438C5.04878 1.01146 7.49913 0 10.3803 0C13.2345 0 15.6647 1.01146 17.6707 3.03438C19.6768 5.05729 20.6798 7.49828 20.6798 10.3573C20.6798 11.5171 20.4913 12.6365 20.1143 13.7154C19.7374 14.7943 19.1719 15.8057 18.418 16.7498L23.6365 21.8962C23.8788 22.1119 24 22.3884 24 22.7256C24 23.0627 23.8654 23.3662 23.5961 23.6359C23.3538 23.8786 23.0576 24 22.7075 24C22.3575 24 22.0613 23.8786 21.8189 23.6359ZM10.3803 18.2872C12.5614 18.2872 14.4193 17.5117 15.9542 15.9608C17.489 14.4099 18.2564 12.5421 18.2564 10.3573C18.2564 8.17259 17.489 6.30476 15.9542 4.75386C14.4193 3.20295 12.5614 2.4275 10.3803 2.4275C8.1723 2.4275 6.29415 3.20295 4.74586 4.75386C3.19756 6.30476 2.42342 8.17259 2.42342 10.3573C2.42342 12.5421 3.19756 14.4099 4.74586 15.9608C6.29415 17.5117 8.1723 18.2872 10.3803 18.2872Z"
							fill="#202224" />
					</svg>
				</button>
				<form role="search" method="get" class="search-form" action="<?php echo home_url('/'); ?>">
					<input type="search" class="header-search search-field" placeholder="Search egift cards" name="s"
						value="<?php echo get_search_query(); ?>" />
					<button type="submit" class="search-submit">
						<span class="search-icon">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path
									d="M21.8189 23.6359L16.6812 18.4895C15.8734 19.1907 14.9309 19.7369 13.8539 20.128C12.7768 20.5191 11.6324 20.7147 10.4207 20.7147C7.51259 20.7147 5.04878 19.7032 3.02927 17.6803C1.00976 15.6574 0 13.2164 0 10.3573C0 7.49828 1.00976 5.05729 3.02927 3.03438C5.04878 1.01146 7.49913 0 10.3803 0C13.2345 0 15.6647 1.01146 17.6707 3.03438C19.6768 5.05729 20.6798 7.49828 20.6798 10.3573C20.6798 11.5171 20.4913 12.6365 20.1143 13.7154C19.7374 14.7943 19.1719 15.8057 18.418 16.7498L23.6365 21.8962C23.8788 22.1119 24 22.3884 24 22.7256C24 23.0627 23.8654 23.3662 23.5961 23.6359C23.3538 23.8786 23.0576 24 22.7075 24C22.3575 24 22.0613 23.8786 21.8189 23.6359ZM10.3803 18.2872C12.5614 18.2872 14.4193 17.5117 15.9542 15.9608C17.489 14.4099 18.2564 12.5421 18.2564 10.3573C18.2564 8.17259 17.489 6.30476 15.9542 4.75386C14.4193 3.20295 12.5614 2.4275 10.3803 2.4275C8.1723 2.4275 6.29415 3.20295 4.74586 4.75386C3.19756 6.30476 2.42342 8.17259 2.42342 10.3573C2.42342 12.5421 3.19756 14.4099 4.74586 15.9608C6.29415 17.5117 8.1723 18.2872 10.3803 18.2872Z"
									fill="#202224" />
							</svg>
						</span>
					</button>
				</form>
			</div>
			<?php
			// }
			?>


			<div class="admin-profile">
				<?php if (is_user_logged_in()):
					$current_user = wp_get_current_user();
					$profile_img = get_avatar_url($current_user->ID);
					$name = $current_user->display_name;
					?>
					<div class="header-profile-icon">

						<div class="header-cart-fragment">
							<a href="<?php echo esc_url($header_cart_url); ?>" class="icon-btn header-cart-link" aria-label="<?php echo esc_attr(sprintf(__('Cart (%d items)', 'twentytwentyone'), $header_cart_count)); ?>">
								<span class="header-cart-icon-wrap">
									<img src="<?php echo get_template_directory_uri(); ?>/assets/images/cart_header.png" alt="Cart"
										class="profile-pic">
									<?php if ($header_cart_count > 0): ?>
										<span class="header-cart-count"><?php echo esc_html($header_cart_count); ?></span>
									<?php endif; ?>
								</span>
							</a>
						</div>

						<button class="icon-btn">
							<a href="/my-account/my-wallet/" target="_blank">
							<img src="<?php echo get_template_directory_uri(); ?>/assets/images/header-group.png" alt="Group"
								class="profile-pic">
							</a>
						</button>

						<!-- Profile with dropdown -->
						<div class="profile-dropdown-wrapper">
							<a href="<?php echo home_url('/my-account'); ?>" class="dropdown-item logout-btn">
									<button class="icon-btn profile-btn">
										<img src="<?php echo esc_url($profile_img); ?>" alt="User" class="profile-pic">
									</button>
							</a>

							<div class="profile-dropdown">
								<a href="/my-account" class="dropdown-item">My Account</a>
								<a href="<?php echo wp_logout_url(home_url()); ?>" class="dropdown-item logout-btn">Logout</a>
							</div>
						</div>

					</div>

					<!-- <span><?php /* echo esc_html($name); */ ?></span> -->

				<?php else: ?>
					<a href="<?php echo esc_url(site_url('/user-login'))?>" class="sign-in-link">Sign in</a>
					<a href="<?php echo esc_url(site_url('/user-registration'))?>" class="sign-up-link">Sign up</a>
				<?php endif; ?>
			</div>
			<div class="header-ovarlay"></div>

			<div class="hamburger-icon" id="hamburger">
				<span></span>
				<span></span>
				<span></span>
			</div>

			<nav class="nav-dropdown mobile-only" id="navDropdown">
				<?php if (is_user_logged_in()): ?>
					<ul class="logged-in">
						<li class="menu-item"><a href="<?php echo esc_url(site_url('/brands')); ?>">Gift Cards</a>					
						</li>
						<li><a href="<?php echo esc_url(site_url('/my-account/my-wallet')); ?>">My Wallet</a></li>
						<li class="menu-item has-children"><a href="<?php echo esc_url(site_url('/my-account')); ?>">My Account</a>			
							<ul class="sub-menu">
								<li><a href="<?php echo esc_url(site_url('/my-account/my-wishlist')); ?>">My Favourites</a></li>
								<li><a href="<?php echo esc_url(site_url('/my-account')); ?>">My Profile</a></li>
								<li><a href="<?php echo esc_url(site_url('/my-account/my-reminders/')); ?>">My Gift Calendar</a></li>
								<li><a href="<?php echo esc_url(site_url('/my-account/my-reminders')); ?>">My Address Book </a></li>
							</ul>
						</li>
						<li><a href="<?php echo wp_logout_url(home_url('/')); ?>">Sign out</a></li>
					</ul>
				<?php else: ?>
					<ul class="logged-out">
						<li><a href="<?php echo esc_url(site_url('/')); ?>">Homepage</a></li>
						<li><a href="<?php echo esc_url(site_url('/brands')); ?>">All Gift Cards</a></li>
						<li><a href="<?php echo esc_url(site_url('/user-login/')); ?>">Sign in</a></li>
						<li><a href="<?php echo esc_url(site_url('/user-registration/')); ?>">Sign up</a></li>
					</ul>
				<?php endif; ?>
			</nav>
		</div>
	</header>

	<?php

}
