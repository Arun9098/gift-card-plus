<?php
/**
 * Template Name: Custom Registration Form
 * Template for WooCommerce custom registration with OTP verification
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if(is_user_logged_in()){
    wp_redirect( site_url('/my-account/') );
}

get_header(); ?>
<div class="custom-registration-container lg-form-section">
    <div class="registration-progress" style="display:none">
        <div class="progress-step active" data-step="1">
            <span class="step-number">1</span>
            <span class="step-label">Basic Details</span>
        </div>
        <div class="progress-step" data-step="2">
            <span class="step-number">2</span>
            <span class="step-label">Email Verification</span>
        </div>
        <div class="progress-step" data-step="3">
            <span class="step-number">3</span>
            <span class="step-label">Profile Details</span>
        </div>
        <div class="progress-step" data-step="4">
            <span class="step-number">4</span>
            <span class="step-label">Preferences</span>
        </div>
    </div>



    <!-- STEP 1 -->
    <div id="step-1" class="registration-step active">
        <div class="step-heading">
            <h2 class="main-heading sm-h2">Welcome to giftcards <i>plus</i>!</h2>
            <p class="sub-heading">To join, enter your details below</p>
        </div>
        <form id="basic-details-form" class="registration-form sm-main-form">
            <?php wp_nonce_field('custom_registration', 'registration_nonce'); ?>
            <div class="form-group flex-row">
                <div class="control-wrapper col">
                    <input type="text" id="first_name" name="first_name" placeholder="First Name*" class="form-control" autocomplete="off" spellcheck="false" autocorrect="off" autocapitalize="off">
                </div>
            </div>
            
             <div class="form-group flex-row">
                <div class="control-wrapper col">
                  <input type="text" id="surname" name="surname" placeholder="Surname" class="form-control" autocomplete="off" spellcheck="false" autocorrect="off" autocapitalize="off">
                 </div>
            </div>
            
            <div class="form-group flex-row">
                <div class="control-wrapper col">
                    <input type="email" id="email" name="email" placeholder="Email*" class="form-control" autocomplete="off" spellcheck="false" autocorrect="off" autocapitalize="off">
                </div>
            </div>
            
          <div class="form-group flex-row">
               <div class="control-wrapper col">
                    <input type="tel" id="mobile" name="mobile" placeholder="Mobile*" class="form-control" inputmode="tel" autocomplete="off" spellcheck="false" autocorrect="off" autocapitalize="off">
                </div>
            </div>
            
            <div class="form-group flex-row">
                <div class="control-wrapper col">
                    <div class="password-input-wrapper">
                        <input type="password" id="password_cust" placeholder="Password*" name="password_cust" class="form-control" autocomplete="new-password">
                        <button type="button" class="password-toggle-btn" id="password_toggle" aria-label="Show password">
                            <svg class="password-eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <svg class="password-eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                <path d="M17.94 17.94C16.2306 19.243 14.1491 19.9649 12 20C5 20 1 12 1 12C2.24389 9.68192 3.96914 7.65663 6.06 6.06M9.9 4.24C10.5883 4.0789 11.2931 3.99836 12 4C19 4 23 12 23 12C22.393 13.1356 21.6691 14.2048 20.84 15.19M14.12 14.12C13.8454 14.4148 13.5141 14.6512 13.1462 14.8151C12.7782 14.9791 12.3809 15.0673 11.9781 15.0744C11.5753 15.0815 11.1751 15.0074 10.8016 14.8565C10.4281 14.7056 10.0887 14.4811 9.80385 14.1962C9.51897 13.9113 9.29439 13.572 9.14351 13.1984C8.99262 12.8249 8.91853 12.4247 8.92563 12.0219C8.93274 11.6191 9.02091 11.2218 9.18488 10.8538C9.34884 10.4859 9.58525 10.1546 9.88 9.88M1 1L23 23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-meter"></div>
                        <span class="strength-text"></span>
                    </div>
                </div>
            </div>
            
             <div class="form-group flex-row">
                <div class="control-wrapper col ">
                    <div class="form-check checkbox">
                        <input type="checkbox" id="terms_conditions" name="terms_conditions" >
                        <label for="terms_conditions">I accept the <a href="<?php echo esc_url( site_url() . '/wp-content/uploads/' . rawurlencode( 'GC Terms&Conditions.pdf' ) ); ?>" target="_blank">Terms & Conditions</a> and agree to Gift Cards Plus collecting my personal information (PI) pursuant to our <a href="https://www.jandc.com.au/Privacy-Policy">Privacy Policy</a></label>
                    </div>
                </div>
            </div>
            
            <input type="hidden" id="recaptcha_token_reg" name="recaptcha_token" value="">
            <div class="form-group">
                <div class="g-recaptcha" data-sitekey="<?php echo esc_attr( GCP_RECAPTCHA_SITE_KEY ); ?>" data-callback="gcpwOnRegRecaptcha" data-expired-callback="gcpwOnRegRecaptchaExpired"></div>
            </div>
            <div class="form-actions">
                <!-- <button type="button" id="save-exit" class="btn-secondary">Save & Exit</button> -->
                <button type="submit" id="join-now" class="btn btn-primary btn-black-p2 btn-full-width">Join Now</button>
            </div>
            <p class="allready_account">Already have an account? <a href="<?php echo site_url() . '/user-login'; ?>">Sign in</a></p>
            <div class="basic-details-form-error"></div>
        </form>
    </div>

    <!-- Step 2: OTP Verification -->
    <div id="step-2" class="registration-step">
        <div class="step-heading">
            <h2 class="main-heading md-2">Verification Code</h2>
            <p class="sub-heading">Enter the code that we have sent to your email: <span id="user-email"></span></p>
        </div>
       
        <div class="otp-container">
          <form id="otp-verification-form" class="registration-form">
            <div class="otp-inputs">
              <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
              <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
              <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
              <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
              <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
              <input type="text" maxlength="1" inputmode="numeric" class="otp-field" />
            </div>

            <div class="otp-timer">
              <p>Code expires in: <span id="otp-timer">01:00</span></p>
            </div>

            <div class="sm-main-form top-spacing-32">            
            <button type="submit" class="btn-full-width btn btn-primary submit-btn btn-black-p2">Submit</button>
            <button type="button" id="resend-otp" class="resend-otp link-btn custom-resend-btn custom-link btn-full-width btn resend-code btn-black-p2" disabled>Resend Code</button>
            </div>
          </form>
        </div>
    </div>

    <!-- Step 3: Profile Details -->
    <div id="step-3" class="registration-step">
        <div class="step-heading">
            <h2 class="main-heading md-h2">You’re in!</h2>
            <img class="check-imagestep" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/Check-circle-outline.png" alt="Check Icon">
            
            <h4 class="sub-heading">We would love to know a little more about you so we can personalise your experience</h4>
        </div>
        <form id="profile-details-form" class="registration-form">
            <div class="sm-main-form">
                <div class="form-group flex-row">
                    <div class="control-wrapper col">
                        <input type="text" id="date_of_birth" name="date_of_birth" placeholder="Date of Birth" class="form-control">
                    </div>
                </div>
                
                <div class="form-group flex-row">
                        <div class="control-wrapper col">
                            <select id="state" name="state">
                                <option value="">State</option>
                                <option value="NSW">New South Wales</option>
                                <option value="VIC">Victoria</option>
                                <option value="QLD">Queensland</option>
                                <option value="WA">Western Australia</option>
                                <option value="SA">South Australia</option>
                                <option value="TAS">Tasmania</option>
                                <option value="ACT">Australian Capital Territory</option>
                                <option value="NT">Northern Territory</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                </div>
             </div>
            
            <div class="form-row top-spacing-32">
                <div class="profile-marketing-preferences">
                    <strong>Marketing Preferences</strong>
                    <p>Don't miss out! How would you like to hear about our offers and promotions?</p>
                    <div class="form-check checkbox">
                        <input type="checkbox" id="marketing_emails" name="marketing_emails">
                        <label for="marketing_emails">I'd like to receive marketing emails</label>
                    </div>
                    <div class="form-check checkbox">
                        <input type="checkbox" id="sms_notifications" name="sms_notifications">
                        <label for="sms_notifications">I'd like to receive SMS notifications</label>
                    </div>
                </div>
            </div>
            
            <div class="marketing-preferences-button sm-main-form">
                <div class="top-spacing-32">
                <button type="submit" class="btn-full-width btn btn-primary btn-black-p2">Continue</button>
                <button type="button" class="link-btn skip-step">Skip</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Step 4: Preferences -->
    <div id="step-4" class="registration-step">
        <div class="step-heading">
            <h2 class="main-heading sm-h2">Choose your preferences </h2>
            <p class="sub-heading">Please confirm a few details below so we can tailor our updates to what you love</p>
        </div>
        <form id="preferences-form" class="registration-form">
            <div class="form-row preferences-multi-select">
                <h4 class="preferences-subheading">Events</h4>
                <div class="multi-select-container">
                    <?php
                        if (have_rows('events', 'option')):

                            while (have_rows('events', 'option')): the_row();
                                $event_label = get_sub_field('event_name');

                                if ($event_label) {
                                    // Create a safe key
                                    $event_key = sanitize_title($event_label);

                                    echo '<div class="select-option">';
                                    echo '<input type="checkbox" id="event_' . $event_key . '" name="events[]" value="' . $event_key . '">';
                                    echo '<label for="event_' . $event_key . '">' . esc_html($event_label) . '</label>';
                                    echo '</div>';
                                }

                            endwhile;

                        endif;
                    ?>
                </div>
            </div>
            
            <div class="form-row preferences-multi-select">
                <h4 class="preferences-subheading">Hobbies</h4>
                <div class="multi-select-container">
                    <?php
                    if (have_rows('hobbies', 'option')):

                        while (have_rows('hobbies', 'option')): the_row();
                            $hobby_label = get_sub_field('hobbie_name');

                            if ($hobby_label) {
                                // Create a safe key
                                $hobby_key = sanitize_title($hobby_label);

                                echo '<div class="select-option">';
                                echo '<input type="checkbox" id="hobby_' . $hobby_key . '" name="hobbies[]" value="' . $hobby_key . '">';
                                echo '<label for="hobby_' . $hobby_key . '">' . esc_html($hobby_label) . '</label>';
                                echo '</div>';
                            }

                        endwhile;

                    endif;
                    ?>
                </div>
            </div>
            <div class="sm-main-form">
                <div class="space-top-32 text-center">
                    <button type="submit" class="btn-full-width btn btn-primary btn-black-p2">Continue</button>
                    <button type="button" class="link-btn skip-step">Skip</button>
                </div>    
            </div>
        </form>
    </div>

    <!-- Success Message -->
    <div id="registration-success" class="registration-step" style="display:none;">
      <div class="success-message">
        <img class="check-imagestep" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/Group.png" alt="Gift Icon">

        <!-- Dynamic content inserted by JS -->
        <div id="completion-content"></div>
        <div class="space-top-32">
          <a href="<?php echo wc_get_page_permalink('myaccount'); ?>" class="btn-full-width btn btn-primary sm-main-form btn-black-p2">Done</a>
        </div>
      </div>
    </div>

</div>


<script>
    var latestRegToken = '';

    function gcpwOnRegRecaptcha(token) {
        latestRegToken = token;
        var field = document.getElementById('recaptcha_token_reg');
        if (field) field.value = token;
    }

    function gcpwOnRegRecaptchaExpired() {
        latestRegToken = '';
        var field = document.getElementById('recaptcha_token_reg');
        if (field) field.value = '';
    }

(function($) {
    // Inject token into the registration AJAX call regardless of how the external JS builds its data
    $.ajaxPrefilter(function(options) {
        if (options.data && options.data.indexOf('action=custom_create_user') !== -1 && latestRegToken) {
            options.data += '&recaptcha_token=' + encodeURIComponent(latestRegToken);
        }
    });
})(jQuery);
</script>

<?php
get_footer();