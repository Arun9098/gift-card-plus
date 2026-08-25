<?php
/**
 * Customer completed order email
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Determine user type ──────────────────────────────────────────────────────
$order_user_id    = $order->get_user_id();
$is_business_user = false;

if ( $order_user_id ) {
	$user = get_user_by( 'id', $order_user_id );
	if ( $user && in_array( 'business_user', (array) $user->roles ) ) {
		$is_business_user = true;
		$header_logo      = site_url() . '/wp-content/uploads/2025/09/gcplus_business.png';
		$side_logo        = site_url() . '/wp-content/uploads/2026/05/Group_bus.png';
		$header_color     = '#202224';
		$content_h_color  = '#14A0B4';
	} else {
		$header_logo     = site_url() . '/wp-content/uploads/2026/04/Pink-Grey.png';
		$side_logo       = site_url() . '/wp-content/uploads/2026/05/Group_cus.png';
		$header_color    = '#fff';
		$content_h_color = '#67D6C8';
	}
}

// ── Common variables ─────────────────────────────────────────────────────────
$first_name    = $order->get_billing_first_name();
$order_number  = $order->get_order_number();
$site_name     = get_bloginfo( 'name' );
$meta_referral = $order->get_meta( '_client_reference' );

// Build {order_referral} string
$order_referral = ( ! empty( trim( $meta_referral ) ) )
	? 'Your reference number for this is ' . $meta_referral . '.'
	: '';

// Build {scheduled_delivery_time}
$delivery_dates = [];
$gc_i           = 1;
foreach ( $order->get_items() as $item_id => $item ) {
	$scheduled_date = wc_get_order_item_meta( $item_id, '_scheduled_gift_card_delivery', true );
	if ( ! empty( $scheduled_date ) ) {
		$delivery_dates[] = strtotime( $scheduled_date );
	}
	$gc_i++;
}
$min_date                = ! empty( $delivery_dates ) ? date( 'd M Y', min( $delivery_dates ) ) : '';
$max_date                = ! empty( $delivery_dates ) ? date( 'd M Y', max( $delivery_dates ) ) : '';
$scheduled_delivery_time = ( $min_date && $max_date && $min_date !== $max_date )
	? $min_date . ' - ' . $max_date
	: $min_date;

// ── Try et_get_template_by_slug ──────────────────────────────────────────────
$tpl = function_exists( 'et_get_template_by_slug' )
	? et_get_template_by_slug( 'complete-order', [
		'first_name'              => $first_name,
		'order_number'            => $order_number,
		'order_referral'          => $order_referral,
		'scheduled_delivery_time' => $scheduled_delivery_time,
		'site_url'                => site_url(),
		'banner_color'            => $content_h_color,
	] )
	: false;

if ( $tpl ) {
	// Remove scheduled delivery sentence if no date set
	if ( empty( $scheduled_delivery_time ) ) {
		$tpl['body'] = preg_replace(
			'/<p[^>]*>Your digital gift card\(s\) are locked in for delivery at\s*\.\s*View.*?<\/p>/is',
			'',
			$tpl['body']
		);
	}
	if ( $gc_i <= 1 ) {
		$tpl['body'] = str_replace( 'Gift Card(s)', 'Gift Card', $tpl['body'] );
	}
	echo $tpl['body'];
	return;
}

// ── Fallback: hardcoded email ─────────────────────────────────────────────────
$email_message = '<p style="padding:15px 0;font-family:Verdana;font-size:18px;letter-spacing:0%;margin:0;">Hi ' . esc_html( $first_name ) . ',</p>
<p style="padding:15px 0;font-family:Verdana;font-size:18px;letter-spacing:0%;margin:0;">We wanted to let you know that the gift card(s) in your order <strong>#' . esc_html( $order_number ) . '</strong> have been delivered. ' . esc_html( $order_referral ) . '</p>';

if ( ! empty( $scheduled_delivery_time ) ) {
	$email_message .= '<p style="padding:15px 0;font-family:Verdana;font-size:18px;letter-spacing:0%;margin:0;">Delivery of your Gift Card(s) is scheduled for ' . esc_html( $scheduled_delivery_time ) . '. We will notify you when this is complete.</p>';
}

$email_message .= '<p style="padding:15px 0;font-family:Verdana;font-size:18px;letter-spacing:0%;margin:0;">If you need any help, feel free to reach out using the email below.</p>
<p style="padding:15px 0;font-family:Verdana;font-size:18px;letter-spacing:0%;margin:0;">Thank you for your order.</p>
<p style="padding:15px 0;font-family:Verdana;font-size:18px;letter-spacing:0%;margin:0;">The <strong>giftcards</strong><em>plus</em>&#8482; Team.</p>
<p style="padding:30px 0;font-family:Verdana;font-style:italic;font-size:14px;letter-spacing:0%;margin:0;">This is an automated message. Please do not reply to this email, as this inbox is not monitored.</p>';

if ( $gc_i <= 1 ) {
	$email_message = str_replace( 'Gift Card(s)', 'Gift Card', $email_message );
}

$email_body = '<html>
  <head><meta http-equiv="Content-Type" content="text/html;UTF-8" /></head>
  <body style="margin:0;background-color:#F4F3F4;font-family:Helvetica,Arial,sans-serif;font-size:12px;" bgcolor="#F4F3F4">
    <table border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="#F4F3F4">
      <tbody><tr><td style="padding:15px;"><center>
        <table width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff">
          <tbody><tr><td align="left"><div>

            <!-- Header logo -->
            <table style="padding:28px 0;width:100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="' . $header_color . '">
              <tbody><tr>
                <td style="text-align:center;vertical-align:middle;">
                  <a style="text-decoration:none;" href="' . site_url() . '" target="_blank">
                    <img width="173" height="64" src="' . $header_logo . '" alt="' . esc_attr( $site_name ) . '" style="display:block;margin:0 auto;">
                  </a>
                </td>
              </tr></tbody>
            </table>

            <!-- Hero banner -->
            <table style="padding:32px 0;width:100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="' . $content_h_color . '">
              <tbody><tr>
                <td style="padding:0 40px;vertical-align:middle;">
                  <table style="width:100%;" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td style="width:50%;vertical-align:middle;" align="left">
                        <img width="188" height="29" src="' . $side_logo . '" alt="' . esc_attr( $site_name ) . '" style="vertical-align:middle;">
                      </td>
                      <td style="width:50%;vertical-align:middle;" align="right">
                        <img width="100%" height="auto" src="' . site_url() . '/wp-content/uploads/2025/10/pink_Gift_Cards_Plus_GSS_Logo.png" alt="' . esc_attr( $site_name ) . '" style="vertical-align:middle;">
                      </td>
                    </tr>
                  </table>
                </td>
              </tr></tbody>
            </table>

            <!-- Content -->
            <table style="padding-top:32px;width:100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff">
              <tbody><tr>
                <td style="padding-left:40px;padding-right:40px;">' . $email_message . '</td>
              </tr></tbody>
            </table>

            <!-- Need help footer -->
            <table style="padding:32px 0;width:100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="#F8F9FF">
              <tbody><tr>
                <td style="padding-left:30px;padding-right:5px;" width="60%">
                  <p style="padding:15px 0;font-family:Verdana;font-size:18px;margin:0;">Need help?</p>
                  <p style="padding:15px 0;font-family:Verdana;font-size:18px;margin:0;">Our support team is here to help you personalise your experience or answer any questions.</p>
                  <p style="padding:15px 0;font-family:Verdana;font-size:18px;margin:0;">
                    <a href="mailto:support@giftcardsplus.com.au" style="color:#000;text-decoration:none;">support@giftcardsplus.com.au</a>
                  </p>
                </td>
                <td style="padding-right:30px;" width="40%">
                  <img width="197" height="190" src="' . site_url() . '/wp-content/uploads/2025/09/Circle.png" alt="">
                </td>
              </tr></tbody>
            </table>

            <!-- Dark footer -->
            <table style="padding:32px 0;width:100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="#000">
              <tbody><tr>
                <td style="padding-left:30px;padding-right:5px;">
                  <p style="padding:15px 0;font-family:Verdana;font-size:18px;color:#fff;margin:0;">&#169; 2025 giftcardsplus Pty Ltd. All Rights Reserved.</p>
                  <p style="padding:15px 0;font-family:Verdana;font-size:18px;color:#fff;margin:0;">Powered by J&amp;C</p>
                </td>
                <td width="10%"><img width="27" height="27" src="' . site_url() . '/wp-content/uploads/2025/09/instagram-icon.png" alt="Instagram"></td>
                <td style="padding-right:30px;" width="10%"><img width="27" height="27" src="' . site_url() . '/wp-content/uploads/2025/09/linkedin-icon.png" alt="LinkedIn"></td>
              </tr></tbody>
            </table>

          </div></td></tr></tbody>
        </table>
      </center></td></tr></tbody>
    </table>
  </body>
</html>';

echo $email_body;
