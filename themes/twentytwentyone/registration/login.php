<?php 
/**
 * Template Name: Custom Login Form
 * Template for WooCommerce custom login with OTP verification
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if(is_user_logged_in()){
    wp_redirect( site_url('/my-account/') );
}


// Clean up transient when we're showing the password-reset message via URL param
$show_password_reset_message = isset($_GET['password_reset']) && $_GET['password_reset'] === '1';
if ($show_password_reset_message && get_transient('fp_password_reset_msg')) {
   delete_transient('fp_password_reset_msg');
}

get_header(); ?>

	
	<div class="custom-login-container lg-form-section">
	    <div class="custom-login-box" id="login-box">
			<div class="step-heading">
				<h2 class="custom-heading">Sign in</h2>
				<?php if ($show_password_reset_message) : ?>
				<div class="login-password-reset-success sm-main-form" role="alert">
					<svg class="login-password-reset-success-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
						<path d="M8 12l3 3 5-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<p>Your password has been reset. Please sign in below.</p>
				</div>
				<?php endif; ?>
			</div>
	        <form id="custom-login-form" class="sm-main-form">
	            <div class="form-group flex-row">
					<div class="control-wrapper col">
						<!-- <label for="user_login" class="custom-label">Email</label> -->
						<input type="email" id="user_login" name="log" required placeholder="Enter your email" class="form-control">
					 </div>
	            </div>

	            <div class="form-group flex-row">
					<div class="control-wrapper col">
						<!-- <label for="user_pass" class="custom-label">Password</label> -->
						<div class="password-input-wrapper">
							<input type="password" id="user_pass" name="pwd" required placeholder="Enter your password" class="form-control" autocomplete="current-password" oninvalid="this.setCustomValidity('Please enter your password to continue')" oninput="this.setCustomValidity('')">
							<button type="button" class="password-toggle-btn" id="user_pass_toggle" aria-label="Show password">
								<svg class="password-eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<svg class="password-eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
									<path d="M17.94 17.94C16.2306 19.243 14.1491 19.9649 12 20C5 20 1 12 1 12C2.24389 9.68192 3.96914 7.65663 6.06 6.06M9.9 4.24C10.5883 4.0789 11.2931 3.99836 12 4C19 4 23 12 23 12C22.393 13.1356 21.6691 14.2048 20.84 15.19M14.12 14.12C13.8454 14.4148 13.5141 14.6512 13.1462 14.8151C12.7782 14.9791 12.3809 15.0673 11.9781 15.0744C11.5753 15.0815 11.1751 15.0074 10.8016 14.8565C10.4281 14.7056 10.0887 14.4811 9.80385 14.1962C9.51897 13.9113 9.29439 13.572 9.14351 13.1984C8.99262 12.8249 8.91853 12.4247 8.92563 12.0219C8.93274 11.6191 9.02091 11.2218 9.18488 10.8538C9.34884 10.4859 9.58525 10.1546 9.88 9.88M1 1L23 23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</button>
						</div>
					</div>
	            </div>
  				<div class="custom-forgot-password">
	                <a href="<?php echo site_url() . '/forget-password'; ?>" class="custom-link">Forgot password?</a>
	            </div>
				<input type="hidden" id="recaptcha_token_login" name="recaptcha_token" value="">
				<div class="form-group">
					<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( GCP_RECAPTCHA_SITE_KEY ); ?>" data-callback="gcpwOnLoginRecaptcha" data-expired-callback="gcpwOnLoginRecaptchaExpired"></div>
				</div>
				<div class="top-spacing-32">
	            <button type="submit" name="submit" class="custom-login-btn btn btn-primary btn-full-width btn-black-p2">Sign in</button>
	            <p id="error-msg" class="has-error error-message"></p>
				</div>
	            <div class="custom-signup">
	                <p class="custom-text">Don't have an account? <a href="<?php echo site_url() . '/user-registration'; ?>" class="custom-link">Sign up</a></p>
	                <!-- <p class="custom-text">Switch to <a href="<?php //echo site_url() . '/login'; ?>" class="custom-link bold-text">giftcardsplus Business</a></p> -->
	            </div>
	        </form>

	    </div>

	</div>
<?php if ($show_password_reset_message) : ?>
<script>
(function() {
    if (typeof window.history.replaceState === 'function') {
        var url = new URL(window.location.href);
        url.searchParams.delete('password_reset');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
})();
</script>
<?php endif; ?>
 
<script>
    var latestLoginToken = '';

    function gcpwOnLoginRecaptcha(token) {
        latestLoginToken = token;
        var field = document.getElementById('recaptcha_token_login');
        if (field) field.value = token;
    }

    function gcpwOnLoginRecaptchaExpired() {
        latestLoginToken = '';
        var field = document.getElementById('recaptcha_token_login');
        if (field) field.value = '';
    }

(function($) {
    // Inject token into the login AJAX call regardless of how the external JS builds its data
    $.ajaxPrefilter(function(options) {
        if (options.data && options.data.indexOf('action=send_login_otp') !== -1 && latestLoginToken) {
            options.data += '&recaptcha_token=' + encodeURIComponent(latestLoginToken);
        }
    });
})(jQuery);
</script>

<?php
get_footer();