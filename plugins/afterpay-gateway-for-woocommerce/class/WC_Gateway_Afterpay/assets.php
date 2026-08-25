<?php
/**
 * These are assets values in the Afterpay - WooCommerce plugin
 */

$get_afterpay_assets = function ( $country ) {

	$global_assets = array();

	$assets = array(
		'US' => array(
			'help_center_url'          => 'https://help.afterpay.com/hc/en-us/requests/new',
			'retailer_url'             => 'https://www.afterpay.com/for-retailers',
			'cart_page_express_button' => '<button id="afterpay_express_button" class="btn-afterpay_express btn-afterpay_express_cart [THEME]" type="button" disabled>Checkout with <img src="[STATIC_URL]en-US/integration/logo/lockup/color-[LOGO_COLOR]-32.svg" alt="Afterpay" /></button>',
		),
		'CA' => array(
			'help_center_url'          => 'https://help.afterpay.com/hc/en-ca/requests/new',
			'retailer_url'             => 'https://www.afterpay.com/en-CA/for-retailers',
			'cart_page_express_button' => '<button id="afterpay_express_button" class="btn-afterpay_express btn-afterpay_express_cart [THEME]" type="button" disabled>Checkout with <img src="[STATIC_URL]en-CA/integration/logo/lockup/color-[LOGO_COLOR]-32.svg" alt="Afterpay" /></button>',
		),
		'AU' => array(
			'help_center_url'          => 'https://help.afterpay.com/hc/en-au/requests/new',
			'retailer_url'             => 'https://www.afterpay.com/en-AU/business',
			'cart_page_express_button' => '<button id="afterpay_express_button" class="btn-afterpay_express btn-afterpay_express_cart [THEME]" type="button" disabled>Checkout with <img src="[STATIC_URL]en-AU/integration/logo/lockup/color-[LOGO_COLOR]-32.svg" alt="Afterpay" /></button>',
		),
		'NZ' => array(
			'help_center_url'          => 'https://help.afterpay.com/hc/en-nz/requests/new',
			'retailer_url'             => 'https://www.afterpay.com/en-NZ/business',
			'cart_page_express_button' => '<button id="afterpay_express_button" class="btn-afterpay_express btn-afterpay_express_cart [THEME]" type="button" disabled>Checkout with <img src="[STATIC_URL]en-NZ/integration/logo/lockup/color-[LOGO_COLOR]-32.svg" alt="Afterpay" /></button>',
		),
	);

	$region_assets = array_key_exists( $country, $assets ) ? $assets[ $country ] : $assets['AU'];

	return array_merge( $global_assets, $region_assets );
};

return $get_afterpay_assets( $this->get_country_code() );
