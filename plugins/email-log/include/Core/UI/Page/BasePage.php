<?php namespace EmailLog\Core\UI\Page;

use EmailLog\Core\Loadie;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Base class for all Email Log admin pages.
 *
 * @since 2.0.0
 */
abstract class BasePage implements Loadie {

	/**
	 * Current page.
	 *
	 * @var string
	 */
	protected $page;

	/**
	 * Current screen.
	 *
	 * @var \WP_Screen
	 */
	protected $screen;

	/**
	 * Register page.
	 *
	 * @return void
	 */
	abstract public function register_page();

	/**
	 * Setup hooks related to pages.
	 *
	 * @inheritdoc
	 */
	public function load() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	/**
	 * Render admin page footer.
	 */
	protected function render_page_footer() {
		/**
		 * Action to add additional content to email log admin footer.
		 *
		 * @since 1.8
		 */
		do_action( 'el_admin_footer' );
	}

	/**
	 * Return the WP_Screen object for the current page's handle.
	 *
	 * @return \WP_Screen Screen object.
	 */
	public function get_screen() {
		if ( ! isset( $this->screen ) ) {
			$this->screen = \WP_Screen::get( $this->page );
		}

		return $this->screen;
	}

    function sidebar(){
        echo '<div class="sidebar-box pro-ad-box">
            <p class="text-center"><a href="#" data-pro-feature="sidebar-box-logo" class="open-pro-dialog sidebar-box-logo">
            <img src="' . esc_url(EMAIL_LOG_URL . 'assets/img/logo-64x64.png') . '" alt="Email Log PRO" title="Email Log PRO"> Email Log PRO</a><br><b>Get more done for only $9.99!</b></p>
            <ul class="plain-list">
                <li>Detailed Email Log</li>
                <li>24/7 Monitoring from our SaaS</li>
                <li>Instant Notifications When Your Emails Stop Working</li>
                <li>Auto Forward</li>
                <li>Resend Emails</li>
                <li>Licenses &amp; Sites Manager (remote SaaS dashboard)</li>
                <li>White-label Mode</li>
                <li>Complete Codeless Plugin Rebranding</li>
                <li>Email Support From Plugin Developers</li>
            </ul>

            <p class="text-center"><a href="#" class="open-pro-dialog button button-buy" data-pro-feature="sidebar-box-btn">Get PRO Now</a></p>
            </div>';

    if (!defined('EPS_REDIRECT_VERSION') && !defined('WF301_PLUGIN_FILE')) {
      echo '<div class="sidebar-box pro-ad-box box-301">
            <h3 class="textcenter"><b>Problems with redirects?<br>Moving content around or changing posts\' URL?<br>Old URLs giving you problems?<br><br><u>Improve your SEO &amp; manage all redirects in one place!</u></b></h3>

            <p class="text-center"><a href="#" class="install-wp301">
            <img src="' . esc_url(EMAIL_LOG_URL . 'assets/img/wp-301-logo.png') . '" alt="WP 301 Redirects" title="WP 301 Redirects"></a></p>

            <p class="text-center"><a href="#" class="button button-buy install-wp301">Install and activate the <u>free</u> WP 301 Redirects plugin</a></p>

            <p><a href="https://wordpress.org/plugins/eps-301-redirects/" target="_blank">WP 301 Redirects</a> is a free WP plugin maintained by the same team as this Email Log plugin. It has <b>+350,000 users, 5-star rating</b>, and is hosted on the official WP repository.</p>
            </div>';
    }

    echo '<div class="sidebar-box" style="margin: 35px 0;">
            <p>Please <a href="https://wordpress.org/support/plugin/email-log/reviews/#new-post" target="_blank">rate the plugin ★★★★★</a> to <b>keep it up-to-date &amp; maintained</b>. It only takes a second to rate. Thank you! 👋</p>
            </div>';
    echo '</div>';
    echo '</form>';

    echo ' <div id="emaillog-pro-dialog" style="display: none;" title="Email Log PRO is here!"><span class="ui-helper-hidden-accessible"><input type="text"/></span>

        <div class="center logo"><a href="https://wpemaillog.com/?ref=emaillog-free-pricing-table" target="_blank" class="sidebar-box-logo"><img src="' . esc_url(EMAIL_LOG_URL . 'assets/img/logo-64x64.png') . '" alt="Email Log PRO" title="Email Log PRO">  Email Log</a><br>

        </div>

        <table id="emaillog-pro-table">
        <tr>
        <td class="center">Personal License</td>
        <td class="center">Team License</td>
        <td class="center">Agency License</td>
        </tr>

        <tr class="prices">
        <td class="center"><span>$59</span> <b>/year</b></td>
        <td class="center"><span>$99</span> <b>/year</b></td>
        <td class="center"><span>$119</span> <b>/year</b></td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-yes"></span><b>1 Site License</b>  ($59 per site)</td>
        <td><span class="dashicons dashicons-yes"></span><b>5 Sites License</b>  ($19 per site)</td>
        <td><span class="dashicons dashicons-yes"></span><b>100 Sites License</b>  ($1 per site)</td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-yes"></span>All Plugin Features</td>
        <td><span class="dashicons dashicons-yes"></span>All Plugin Features</td>
        <td><span class="dashicons dashicons-yes"></span>All Plugin Features</td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-yes"></span>Detailed Email Log</td>
        <td><span class="dashicons dashicons-yes"></span>Detailed Email Log</td>
        <td><span class="dashicons dashicons-yes"></span>Detailed Email Log</td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-yes"></span>24/7 Monitoring</td>
        <td><span class="dashicons dashicons-yes"></span>24/7 Monitoring</td>
        <td><span class="dashicons dashicons-yes"></span>24/7 Monitoring</td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-yes"></span>Auto Forward &amp; Resend Emails</td>
        <td><span class="dashicons dashicons-yes"></span>Auto Forward &amp; Resend Emails</td>
        <td><span class="dashicons dashicons-yes"></span>Auto Forward &amp; Resend Emails</td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-yes"></span>SaaS Dashboard</td>
        <td><span class="dashicons dashicons-yes"></span>SaaS Dashboard</td>
        <td><span class="dashicons dashicons-yes"></span>SaaS Dashboard</td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-yes"></span>1 Year of Updates &amp; Support</td>
        <td><span class="dashicons dashicons-yes"></span>1 Year of Updates &amp; Support</td>
        <td><span class="dashicons dashicons-yes"></span>1 Year of Updates &amp; Support</td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-no"></span>White-label Mode</td>
        <td><span class="dashicons dashicons-yes"></span>White-label Mode</td>
        <td><span class="dashicons dashicons-yes"></span>White-label Mode</td>
        </tr>

        <tr>
        <td><span class="dashicons dashicons-no"></span>Full Plugin Rebranding</td>
        <td><span class="dashicons dashicons-no"></span>Full Plugin Rebranding</td>
        <td><span class="dashicons dashicons-yes"></span>Full Plugin Rebranding</td>
        </tr>

        <tr>
        <td><a class="button button-buy" data-href-org="https://wpemaillog.com/buy/?product=personal-yearly&ref=pricing-table" href="https://wpemaillog.com/buy/?product=personal-yearly&ref=pricing-table" target="_blank">BUY NOW</a><br>or <a target="_blank" class="button-buy" href="https://wpemaillog.com/buy/?product=personal-ltd-launch&ref=pricing-table" data-href-org="https://wpemaillog.com/buy/?product=personal-ltd-launch&ref=pricing-table">only <del>$159</del> $89 for a lifetime license</a></td>
        <td><a class="button button-buy" data-href-org="https://wpemaillog.com/buy/?product=team-yearly&ref=pricing-table" href="https://wpemaillog.com/buy/?product=team-yearly&ref=pricing-table" target="_blank">BUY NOW</a></td>
        <td><a class="button button-buy" data-href-org="https://wpemaillog.com/buy/?product=agency-yearly&ref=pricing-table" href="https://wpemaillog.com/buy/?product=agency-yearly&ref=pricing-table" target="_blank">BUY NOW</a></td>
        </tr>

        </table>

        <div class="upsell-footer-2 center">Need the plugin only for a <b>short period of time</b>? <a class="button-buy" target="_blank" data-href-org="https://wpemaillog.com/buy/?product=personal-monthly&ref=pricing-table" href="https://wpemaillog.com/buy/?product=personal-monthly&ref=pricing-table"><b>Get it for ONLY $9.99</b><small> /month</small></a> &amp; cancel any time!</div>

        <div class="center footer"><b>100% No-Risk Money Back Guarantee!</b> If you don\'t like the plugin over the next 7 days, we will happily refund 100% of your money. No questions asked! Payments are processed by our merchant of records - <a href="https://paddle.com/" target="_blank">Paddle</a>.</div>';
    }
}
