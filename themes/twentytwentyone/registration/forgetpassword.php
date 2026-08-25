<?php

/**
 * Template Name: Forget Password (AJAX Reset)
 */

if (!defined('ABSPATH')) exit;
if(is_user_logged_in()){
    wp_redirect( site_url('/my-account/') );
}
if (isset($_GET['fp_reset'])) {
    $display_style = 'display:none;';
}
get_header();
?>

<div id="fp-wrapper" class="lg-form-section">
    <!-- STEP 1 — REQUEST RESET -->
    <div id="fp-step-request" class="fp-step reset-password-wrapper" style="<?php echo $display_style;?> ">
        <div class="step-heading">
            <h2>Password reset</h2>
            <p class="sub-heading">Don’t worry, it happens to the best of us.<br>Let’s get you back into your account. Enter your email below and we will send you a reset link.</p>
        </div>
        <div class="sm-main-form">
            <div class="control-wrapper">
                <input type="email" id="fp-email" placeholder="Email" class="form-control" />
            </div>
            <div class="form-group">
                <div id="fp-recaptcha-request"></div>
            </div>
            <button id="fp-request-btn" class="btn btn-black-p2 btn-primary reset-pass-btn btn-full-width">Reset password</button>
            <p id="fp-request-error" class="fp-error error-message"></p>
        </div>
    </div>

    <!-- STEP 2 — EMAIL SENT -->
    <div id="fp-step-sent" class="fp-step reset-password-wrapper" style="display:none;">
        <div class="step-heading">
            <h2>Check your inbox 💌</h2>
            <p class="sub-heading">We’ve sent a reset password link to <span id="fp-sent-email"></span> .</p>
            <p class="sub-heading">Please note it may take several minutes to show up in your inbox. </p>
            <p class="sub-heading forgot-text">Didn’t get the email? Resend now or <a href="<?php echo esc_url(home_url('/contact-us/')); ?>">contact us</a>.</p>
            <button id="resendpass-btn" class="btn-black-p2 sm-main-form fp-resetpass-btn btn btn-primary reset-pass-btn btn-full-width btn-black-p2">Resend</button>
            <p id="resendpass-msg" class="fp-info" style="margin-top:10px;color:#333;"></p>
        </div>
    </div>

    <!-- STEP 3 — EMAIL NOT FOUND -->
    <div id="fp-step-notfound" class="fp-step reset-password-wrapper" style="display:none;">
        <div class="step-heading">
            <h2>Let’s try that again</h2>
            <p class="sub-heading">We don’t have that email address on file. Please re-enter below or <a href="<?php echo site_url('/user-login/'); ?>">sign up</a></p>
        </div>
        <div class="sm-main-form">
            <input type="email" id="fp-email2" class="form-control" placeholder="Email" />
            <p id="fp-resend2-error" class="fp-error error-message invalid-email" style="display:none;"></p>
            <div class="form-group">
                <div id="fp-recaptcha-resend2"></div>
            </div>
            <button id="fp-resend2-btn" class="btn btn-primary btn-full-width btn-black-p2">Resend</button>
        </div>
        <p class="sub-heading having-trouble">Having some trouble? We are always happy to help. <a href="<?php echo site_url('/contact-us/'); ?>"></br>Contact us</a></p>.
    </div>

    <!-- STEP 4 — NEW PASSWORD -->

   
    <div id="fp-step-newpass" class="fp-step reset-password-wrapper" style="display:none;">
        <div class="step-heading">
            <h2>Choose a new password</h2>
        </div>
        <div class="sm-main-form">
            <div class="control-wrapper fp-password-wrap">
                <input type="password" id="fp-newpass" class="form-control" placeholder="New password" autocomplete="new-password" />
                <button type="button" class="fp-toggle-password password-toggle-btn" aria-label="Show password" title="Show password">
                    <svg class="fp-eye-icon fp-eye-open" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="fp-eye-icon fp-eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>
            <div class="control-wrapper fp-password-wrap">
                <input type="password" id="fp-newpass2" placeholder="Re-enter password" class="form-control" autocomplete="new-password" />
                <button type="button" class="fp-toggle-password password-toggle-btn" aria-label="Show password" title="Show password">
                    <svg class="fp-eye-icon fp-eye-open" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="fp-eye-icon fp-eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>
            <p id="fp-reset-error" class="fp-error error-message"></p>
            <button id="fp-resetpass-btn" class="btn btn-primary reset-pass-btn btn-full-width btn-black-p2">Reset password</button>
            <p id="fp-reset-success" style="color:green;"></p>
        </div>
    </div>
</div>
<!-- 
<style>
    #fp-wrapper {
        max-width: 650px;
        margin: 50px auto;
        text-align: center;
    }

    input,
    button {
        width: 100%;
        padding: 12px;
        margin-bottom: 12px;
    }

    button {
        background: black;
        color: white;
        border: 0;
        cursor: pointer;
    }

    .fp-error {
        color: red;
        margin-top: 10px;
    }
 
</style> -->

<script>
    var gcpwFpWidgets = {};

    function gcpwRenderFpWidgets() {
        grecaptcha.ready(function() {
            ['fp-recaptcha-request', 'fp-recaptcha-resend2'].forEach(function(elId) {
                var el = document.getElementById(elId);
                if (el && typeof gcpwFpWidgets[elId] === 'undefined') {
                    gcpwFpWidgets[elId] = grecaptcha.render(elId, {
                        sitekey: '<?php echo esc_js( GCP_RECAPTCHA_SITE_KEY ); ?>'
                    });
                }
            });
        });
    }
    gcpwRenderFpWidgets();

    jQuery(document).ready(function($) {
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
        var lastEmail = ''; // track the last email used so resend works
        var fpResendProof = ''; // proof from initial reCAPTCHA pass, lets step-2 resend skip the checkbox

        function getFpToken(elId) {
            return typeof gcpwFpWidgets[elId] !== 'undefined' ? grecaptcha.getResponse(gcpwFpWidgets[elId]) : '';
        }
        function resetFpWidget(elId) {
            if (typeof gcpwFpWidgets[elId] !== 'undefined') grecaptcha.reset(gcpwFpWidgets[elId]);
        }

        // STEP 1 - Request reset
        $("#fp-request-btn").on("click", function() {
            var email = $("#fp-email").val().trim();
            $("#fp-request-error").text("");
            if (!email) {
                $("#fp-request-error").text("Please enter your email.");
                return;
            }
            if (!isValidEmail(email)) {
                $("#fp-request-error").text("Please enter a valid email address.");
                return;
            }
            var token = getFpToken('fp-recaptcha-request');
            if (!token) {
                $("#fp-request-error").text("Please confirm you're not a robot.");
                return;
            }
            $.post(ajaxurl, {
                action: "fp_request_reset",
                email: email,
                recaptcha_token: token
            }, function(response) {
                resetFpWidget('fp-recaptcha-request');
                if (response.status === "sent") {
                    fpResendProof = response.resend_proof || '';
                    $("#fp-step-request").hide();
                    $("#fp-sent-email").text(email);
                    lastEmail = email;
                    $("#fp-step-sent").show();
                } else if (response.status === "not_found") {
                    $("#fp-step-request").hide();
                    $("#fp-step-notfound").show();
                }
            }, "json");
        });

        // Cooldown helper for resend button
        function startResendCooldown($btn, $msg, email) {
            var cooldown = 30; // seconds
            $btn.prop("disabled", true);
            $btn.text("Resend (" + cooldown + "s)");
            $msg.text("Reset email resent to " + email + ". You can resend again in " + cooldown + " seconds.");

            var interval = setInterval(function() {
                cooldown--;
                if (cooldown <= 0) {
                    clearInterval(interval);
                    $btn.prop("disabled", false);
                    $btn.text("Resend");
                    $msg.text("");
                } else {
                    $btn.text("Resend (" + cooldown + "s)");
                    $msg.text("Reset email resent to " + email + ". You can resend again in " + cooldown + " seconds.");
                }
            }, 1000);
        }

        // STEP 2 - Resend from "Check your inbox" screen
        $("#resendpass-btn").on("click", function() {
            var email = lastEmail || $("#fp-sent-email").text().trim();
            var $btn = $(this);
            var $msg = $("#resendpass-msg");

            if (!email) {
                $msg.text("Email not found. Please go back and enter your email again.");
                return;
            }
            $.post(ajaxurl, {
                action: "fp_request_reset",
                email: email,
                resend_proof: fpResendProof
            }, function(response) {
                if (response.status === "sent") {
                    fpResendProof = response.resend_proof || fpResendProof;
                    $msg.text("Reset email resent to " + email + ". You can resend again in 30 seconds.");
                    startResendCooldown($btn, $msg, email);
                } else {
                    $msg.text("Unable to resend. Please try again.");
                }
            }, "json");
        });


        function isValidEmail(email) {
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailPattern.test(email);
        }

        // STEP 3 - Resend from "Let's try that again" (email not found) screen
        $("#fp-resend2-btn").on("click", function() {
            var email = $("#fp-email2").val().trim();
            var $err = $("#fp-resend2-error");
            $err.hide().text("");

            if (!email) {
                $err.text("Please enter your email.").show();
                return;
            }
            if (!isValidEmail(email)) {
                $err.text("Please enter a valid email address.").show();
                return;
            }
            var token = getFpToken('fp-recaptcha-resend2');
            if (!token) {
                $err.text("Please confirm you're not a robot.").show();
                return;
            }
            $.post(ajaxurl, {
                action: "fp_request_reset",
                email: email,
                recaptcha_token: token
            }, function(response) {
                resetFpWidget('fp-recaptcha-resend2');
                if (response.status === "sent") {
                    fpResendProof = response.resend_proof || '';
                    $err.hide().text("");
                    $("#fp-step-notfound").hide();
                    $("#fp-sent-email").text(email);
                    lastEmail = email;
                    $("#fp-step-sent").show();
                } else if (response.status === "not_found" || response.status === "error") {
                    $err.text(response.message || "This email address was not found. Please check and try again or sign up.").show();
                }
            }, "json");
        });
        
        if ($('#fp-reset-error').text().trim() == '') {
            $("#fp-reset-error").css("visibility", "hidden");
        }   


        // Eye icon: toggle password visibility
        $(document).on("click", ".fp-toggle-password", function() {
            var $btn = $(this);
            var $wrap = $btn.closest(".fp-password-wrap");
            var $input = $wrap.find("input[type='password'], input[type='text']").filter(".form-control");
            if ($input.length === 0) return;
            if ($input.attr("type") === "password") {
                $input.attr("type", "text");
                $btn.addClass("is-visible").attr({ "aria-label": "Hide password", "title": "Hide password" });
            } else {
                $input.attr("type", "password");
                $btn.removeClass("is-visible").attr({ "aria-label": "Show password", "title": "Show password" });
            }
        });

        function showError(message) {

            $("#fp-reset-error").remove();

            // if message empty → do nothing (designer rule)
            if (!message || message.trim() === "") return;

            // create only if message exists
            $("<p>", {
                id: "fp-reset-error",
                class: "fp-error error-message",
                text: message
            }).insertAfter($("#fp-newpass2").closest(".fp-password-wrap"));
        }



        // STEP 4 - Final password reset
        $("#fp-resetpass-btn").on("click", function() {
            // $("#fp-reset-error").remove();

            var pass1 = $("#fp-newpass").val();
            var pass2 = $("#fp-newpass2").val();
            var key = "<?php echo isset($_GET['key']) ? sanitize_text_field($_GET['key']) : ''; ?>";
            var login = "<?php echo isset($_GET['login']) ? sanitize_text_field($_GET['login']) : ''; ?>";
            
            $("#fp-reset-error").remove();

           
            // $("#fp-reset-error").text("");
            // $("#fp-reset-success").text("");

            if (pass1 !== pass2) {
                showError("Oops, passwords don’t match. Please try again.");
                return;
            }
            // Same strong password rules as user registration: 12+ chars, uppercase, lowercase, number, special char
            var strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/;
            if (!strongPasswordRegex.test(pass1)) {
                showError("Please ensure your password is strong and includes at least 12 characters with uppercase, lowercase, number and special character.");
                return;
            }

            $.post(ajaxurl, {
                action: "fp_do_reset_password",
                pass1: pass1,
                pass2: pass2,
                key: key,
                login: login
            }, function(response) {

                $("#fp-reset-error").remove();

                if (response.status === "error") {
                    showError(response.message);
                } else if (response.status === "success") {
                    // Redirect immediately to sign-in page; message will show there
                    window.location.href = "<?php echo esc_url( add_query_arg( 'password_reset', '1', site_url( '/user-login/' ) ) ); ?>";
                }
            }, "json");
        });
    });
</script>

<?php if (isset($_GET['fp_reset'])) : ?>
    <script>
        jQuery(function($) {
            $(".fp-step").hide();
            $("#fp-step-newpass").show();
        });
    </script>
<?php endif; ?>


<?php get_footer(); ?>