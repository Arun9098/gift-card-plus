jQuery(document).ready(function ($) {
    let currentStep = 1;
    let otpTimer;
    let timeLeft = 60;
    let userEmail = '';
    let totalFields = 0;
    let completedFields = 0;
    let pendingKey = '';
    let isAutoValidating = false; // Flag to prevent multiple simultaneous validations
    let registrationComplete = false; // Used to avoid "are you sure" when leaving after success

    // Only run registration flow and "leave page" warning on the registration form page
    var $registrationForm = jQuery('#basic-details-form');
    var isRegistrationPage = $registrationForm.length > 0;

    var isLoginPage = jQuery('#custom-login-form').length > 0;
    let loginComplete = false; // Avoid "are you sure" when leaving after login success

    // Initialize form
    showStep(currentStep);

    // Warn user if they refresh or try to leave during registration — only attach on registration page
    if (isRegistrationPage) {
        window.addEventListener('beforeunload', function (e) {
            if (registrationComplete) return;
            e.preventDefault();
            e.returnValue = '';
            return '';
        });
    }

    // Login flow: "are you sure" when leaving during OTP step (handler removed on success before redirect)

    if (isLoginPage) {
        window._loginBeforeUnloadHandler = function (e) {
            if (loginComplete) return;
            if (jQuery('#otp_inputs').length === 0) return;
            e.preventDefault();
            e.returnValue = '';
            return '';
        };
        window.addEventListener('beforeunload', window._loginBeforeUnloadHandler);
    }
    // Step 1: Basic Details Form
    jQuery('#basic-details-form').on('submit', function (e) {
        e.preventDefault();
        if (validateStep1()) {
            createUserAccount();
        }
    });

    // Step 2: OTP Verification
    jQuery('#otp-verification-form').on('submit', function (e) {
        console.log('otp-verification-form');
        e.preventDefault();
        verifyOTP();
    });

    // Registration form resend OTP button handler
    jQuery(document).on('click', '#resend-otp', function (e) {
        e.preventDefault();
        const $btn = jQuery(this);
        // Check if button is disabled
        if ($btn.prop('disabled')) {
            return false;
        }
        resendOTP();
    });

    // Step 3: Profile Details
    jQuery('#profile-details-form').on('submit', function (e) {
        e.preventDefault();
        saveProfileDetails();
    });

    // Step 4: Preferences
    jQuery('#preferences-form').on('submit', function (e) {
        e.preventDefault();
        savePreferences();
    });

    // Skip buttons
    jQuery('.skip-step').on('click', function () {
        if (currentStep === 4) {
            // Step 4 is the last step — skip goes straight to the success screen
            registrationComplete = true;
            calculateCompletionPercentage();
            jQuery('.registration-step').removeClass('active');
            jQuery('#registration-success').show();
        } else {
            nextStep();
        }
    });

    // Password strength indicator
    jQuery('#password_cust').on('input', function () {
        checkPasswordStrength(jQuery(this).val());
    });

    // Password show/hide toggle for registration form (password_cust)
    jQuery(document).on('click', '#password_toggle', function(e) {
        e.preventDefault();
        const $passwordField = jQuery('#password_cust');
        const $toggleBtn = jQuery(this);
        const $eyeIcon = $toggleBtn.find('.password-eye-icon');
        const $eyeOffIcon = $toggleBtn.find('.password-eye-off-icon');
        
        if ($passwordField.attr('type') === 'password') {
            $passwordField.attr('type', 'text');
            $eyeIcon.hide();
            $eyeOffIcon.show();
            $toggleBtn.attr('aria-label', 'Hide password');
        } else {
            $passwordField.attr('type', 'password');
            $eyeIcon.show();
            $eyeOffIcon.hide();
            $toggleBtn.attr('aria-label', 'Show password');
        }
    });

    // Password show/hide toggle for login form (user_pass)
    jQuery(document).on('click', '#user_pass_toggle', function(e) {
        e.preventDefault();
        const $passwordField = jQuery('#user_pass');
        const $toggleBtn = jQuery(this);
        const $eyeIcon = $toggleBtn.find('.password-eye-icon');
        const $eyeOffIcon = $toggleBtn.find('.password-eye-off-icon');
        
        if ($passwordField.attr('type') === 'password') {
            $passwordField.attr('type', 'text');
            $eyeIcon.hide();
            $eyeOffIcon.show();
            $toggleBtn.attr('aria-label', 'Hide password');
        } else {
            $passwordField.attr('type', 'password');
            $eyeIcon.show();
            $eyeOffIcon.hide();
            $toggleBtn.attr('aria-label', 'Show password');
        }
    });

   
    // Mobile: digits only; auto-format and add + when 61 + 9 digits
    jQuery('#mobile').on('input', function () {
        formatMobileNumber(jQuery(this));
    });

    // Date of birth formatting
    jQuery('#date_of_birth').on('input', function () {
        formatDateOfBirth(jQuery(this));
    });

    /** -------------------------
     *  ERROR MESSAGE HELPERS
     * ------------------------- */
    
    // Function to show error message dynamically
    function showFieldError(fieldId, message) {
        const field = jQuery('#' + fieldId);
        const controlWrapper = field.closest('.control-wrapper');
        
        // Remove existing error if any
        removeFieldError(fieldId);
        
        // Create and insert error message
        const errorSpan = jQuery('<span class="error-message" id="' + fieldId + '_error"></span>');
        errorSpan.text(message);
        controlWrapper.append(errorSpan);
    }
    
    // Function to remove error message
    function removeFieldError(fieldId) {
        jQuery('#' + fieldId + '_error').remove();
    }
    
    // Function to show form-level error in basic-details-form-error container
    function showFormError(message) {
        const errorContainer = jQuery('.basic-details-form-error');
        // Remove existing error
        errorContainer.empty();
        // Add error message
        if (message) {
            const errorDiv = jQuery('<div class="error-message"></div>');
            errorDiv.text(message);
            errorContainer.append(errorDiv);
        }
    }
    
    // Function to remove form-level error
    function removeFormError() {
        jQuery('.basic-details-form-error').empty();
    }
    
    // Function to validate individual field and show/remove error
    function validateField(fieldId, validator) {
        const field = jQuery('#' + fieldId);
        const value = field.val();
        const result = validator(value);
        
        if (result.isValid) {
            removeFieldError(fieldId);
            return true;
        } else {
            showFieldError(fieldId, result.message);
            return false;
        }
    }

    // Auto-remove errors on keyup for Step 1 fields
    jQuery('#first_name').on('keyup', function() {
        const value = jQuery(this).val().trim();
        if (value) {
            removeFieldError('first_name');
        }
        // Remove form-level error when user starts typing
        removeFormError();
    });

    jQuery('#email').on('keyup', function() {
        const value = jQuery(this).val();
        if (value && isValidEmail(value)) {
            removeFieldError('email');
        }
        // Remove form-level error when user starts typing
        removeFormError();
    });

    jQuery('#mobile').on('keyup', function() {
        const value = jQuery(this).val();
        if (value && isValidAustralianMobile(value)) {
            removeFieldError('mobile');
        }
        // Remove form-level error when user starts typing
        removeFormError();
    });

    jQuery('#password_cust').on('keyup', function() {
        const value = jQuery(this).val();
        if (value && isStrongPassword(value)) {
            removeFieldError('password_cust');
        }
        // Remove form-level error when user starts typing
        removeFormError();
    });

    jQuery('#terms_conditions').on('change', function() {
        if (jQuery(this).is(':checked')) {
            removeFieldError('terms_conditions');
        }
        // Remove form-level error when user changes checkbox
        removeFormError();
    });

    function showStep(step) {
        jQuery('.registration-step').removeClass('active');
        jQuery('#step-' + step).addClass('active');

        jQuery('.progress-step').removeClass('active');
        jQuery('.progress-step[data-step="' + step + '"]').addClass('active');

        currentStep = step;

        // Step 3: clear DOB and init datepicker so it opens to a past year (user must pick real birth date)
        if (step === 3) {
            var $dob = jQuery('#profile-details-form #date_of_birth');
            $dob.val('');
            if (!$dob.data('datepicker-initialized')) {
                $dob.datepicker({
                    dateFormat: 'dd-mm-yy',
                    changeMonth: true,
                    changeYear: true,
                    yearRange: '-120:+0',
                    minDate: '-120y',
                    maxDate: 0,
                    defaultDate: '-25y'
                });
                $dob.data('datepicker-initialized', true);
            }
        }
    }

    function nextStep() {
        if (currentStep < 4) {
            showStep(currentStep + 1);
        }
    }

    /** -------------------------
     *  STEP 1 VALIDATION
     * ------------------------- */
    function validateStep1() {
        let isValid = true;
        
        // Remove all existing error messages (field-level and form-level)
        jQuery('.error-message').remove();
        removeFormError();

        // Validate first name
        if (!jQuery('#first_name').val().trim()) {
            showFieldError('first_name', 'First name is required');
            isValid = false;
        }

        // Validate email
        const email = jQuery('#email').val();
        if (!email) {
            showFieldError('email', 'Email is required');
            isValid = false;
        } else if (!isValidEmail(email)) {
            showFieldError('email', 'Please enter a valid email address');
            isValid = false;
        }

        // Validate mobile
        const mobile = jQuery('#mobile').val();
        if (!mobile) {
            showFieldError('mobile', 'Mobile number is required');
            isValid = false;
        } else if (!isValidAustralianMobile(mobile)) {
            // showFieldError('mobile', 'Please enter a valid Australian mobile number');
            showFieldError('mobile', 'Please enter a mobile number starting with +61 or 04 followed by 8 digits');
            isValid = false;
        }

        // Validate password
        const password = jQuery('#password_cust').val();
        if (!password) {
            showFieldError('password_cust', 'Password is required');
            isValid = false;
        } else if (!isStrongPassword(password)) {
            showFieldError('password_cust', 'Please ensure your password is strong and includes at least 12 characters with uppercase, lowercase, number and special character.');
            isValid = false;
        }

        // Validate terms
        if (!jQuery('#terms_conditions').is(':checked')) {
            // For checkbox, we need to find the parent container
            const checkbox = jQuery('#terms_conditions');
            const controlWrapper = checkbox.closest('.control-wrapper');
            if (controlWrapper.find('.error-message').length === 0) {
                const errorSpan = jQuery('<span class="error-message" id="terms_conditions_error"></span>');
                errorSpan.text('You must agree to the Terms & Conditions');
                controlWrapper.append(errorSpan);
            }
            isValid = false;
        }

        return isValid;
    }

    /** -------------------------
     *  AJAX CALLS
     * ------------------------- */
    function createUserAccount() {

        console.log('custom_create_user');
        const formData = {
            action: 'custom_create_user',
            nonce: custom_registration_ajax.nonce,
            first_name: jQuery('#first_name').val(),
            surname: jQuery('#surname').val(),
            email: jQuery('#email').val(),
            mobile: jQuery('#mobile').val(),
            password: jQuery('#password_cust').val(),
            terms_conditions: jQuery('#terms_conditions').is(':checked') ? 1 : 0
        };

        jQuery('#join-now').prop('disabled', true).text('Creating Account...');

        $.ajax({
            url: custom_registration_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    // Remove any form errors on success
                    removeFormError();
                    userEmail = formData.email;
                    pendingKey = response.data.pending_key;
                    jQuery('#user-email').text(userEmail);
                    startOTPTimer();
                    nextStep();
                } else {
                    // Show error in basic-details-form-error container instead of alert
                    showFormError(response.data);
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX errors
                showFormError('An error occurred. Please try again.');
            },
            complete: function () {
                jQuery('#join-now').prop('disabled', false).text('Join Now');
            }
        });
    }

    // Check if all OTP fields are filled (ONLY for registration form, not login form)
    function isOtpComplete() {
        // Only check registration form (#otp-verification-form), exclude login form (#otp_inputs)
        const inputs = jQuery('#otp-verification-form .otp-field');
        if (inputs.length !== 6) return false;
        let allFilled = true;
        inputs.each(function() {
            if (!jQuery(this).val() || jQuery(this).val().trim() === '') {
                allFilled = false;
                return false; // break the loop
            }
        });
        return allFilled;
    }

    // Auto-validate OTP when all fields are filled
    function autoValidateOTP() {
        // Prevent multiple simultaneous validations
        if (isAutoValidating) {
            return;
        }

        if (!isOtpComplete()) {
            return; // Don't validate if not all fields are filled
        }

        const otpCode = getOtpValue();
        if (!otpCode || otpCode.length !== 6) {
            return; // Don't validate if OTP is incomplete
        }

        // Set flag to prevent multiple calls
        isAutoValidating = true;

        const formData = {
            action: 'custom_verify_otp',
            otp_code: otpCode,
            email: userEmail,
            nonce: custom_registration_ajax.nonce,
            is_auto_validation: true, // Flag to indicate this is auto-validation, not submission
        };

        $.ajax({
            url: custom_registration_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    // OTP is correct - add otp-valid class to all fields (visual feedback only)
                    // ONLY for registration form (#otp-verification-form), NOT login form (#otp_inputs)
                    jQuery('#otp-verification-form .otp-field').removeClass('otp-invalid').addClass('otp-valid');
                } else {
                    // OTP is incorrect - add otp-invalid class to all fields (visual feedback only)
                    // Do NOT show error messages here - only visual feedback on cells
                    // ONLY for registration form (#otp-verification-form), NOT login form (#otp_inputs)
                    jQuery('#otp-verification-form .otp-field').removeClass('otp-valid').addClass('otp-invalid');
                }
            },
            error: function() {
                // On error, mark as invalid (visual feedback only)
                // ONLY for registration form (#otp-verification-form), NOT login form (#otp_inputs)
                jQuery('#otp-verification-form .otp-field').removeClass('otp-valid').addClass('otp-invalid');
            },
            complete: function() {
                // Reset flag after validation completes
                isAutoValidating = false;
            }
        });
    }

    // Make autoValidateOTP globally accessible
    window.autoValidateOTP = autoValidateOTP;

    function verifyOTP() {
        const otpCode = getOtpValue();
        if (!otpCode) {
            // Remove ALL existing OTP errors
            jQuery('.error-message#otp_error, span#otp_error').remove();
            // Create and show OTP error dynamically (only if it doesn't exist)
            if (jQuery('#otp_error').length === 0) {
                const otpContainer = jQuery('.otp-inputs').parent();
                const errorSpan = jQuery('<span class="error-message" id="otp_error"></span>');
                errorSpan.text('This code is incorrect please try again or click resend code.').addClass('has-error');
                otpContainer.find('.otp-inputs').after(errorSpan);
            }
            // Only target registration form, not login form
            jQuery('#otp-verification-form .otp-field').removeClass('otp-valid').addClass('otp-invalid');
            return;
        }

        const formData = {
            action: 'custom_verify_otp',
            otp_code: otpCode,
            email: userEmail,
            nonce: custom_registration_ajax.nonce,
            is_auto_validation: false, // This is actual form submission, not auto-validation
        };

        jQuery('#otp-verification-form .submit-btn').prop('disabled', true).text('Verifying...');

        $.ajax({
            url: custom_registration_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    isEmailVerified = true;
                    // Replace the stale (logged-out) nonce with the fresh one returned after login
                    if (response.data && response.data.new_nonce) {
                        custom_registration_ajax.nonce = response.data.new_nonce;
                    }
                    // Only target registration form, not login form
                    jQuery('#otp-verification-form .otp-field').removeClass('otp-invalid').addClass('otp-valid');
                    jQuery('.error-message#otp_error, span#otp_error').remove();
                    clearInterval(otpTimer);
                    nextStep();
                } else {
                    // Remove ALL existing OTP errors
                    jQuery('.error-message#otp_error, span#otp_error').remove();
                    // Create and show OTP error dynamically (only if it doesn't exist)
                    if (jQuery('#otp_error').length === 0) {
                        const otpContainer = jQuery('#otp-verification-form .otp-inputs').parent();
                        const errorSpan = jQuery('<span class="error-message" id="otp_error"></span>');
                        errorSpan.text(response.data).addClass('has-error');
                        otpContainer.find('.otp-inputs').after(errorSpan);
                    }
                    // Only target registration form, not login form
                    jQuery('#otp-verification-form .otp-field').removeClass('otp-valid').addClass('otp-invalid');
                }
            },
            complete: function () {
                jQuery('.btn-primary').prop('disabled', false).text('Continue');
            }
        });
    }

    function resendOTP() {
        jQuery('.resend-error').remove();

        $.ajax({
            url: custom_registration_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'custom_resend_otp',
                email: userEmail,
                pending_key: pendingKey,
                nonce: custom_registration_ajax.nonce,
            },
            beforeSend: function () {
                jQuery('#resend-otp').prop('disabled', true).text('Sending...');
            },
            success: function (response) {
                if (response.success) {
                    // Show "Sent!" message
                    jQuery('#resend-otp').text('Sent!');
                    jQuery('#resend-otp').removeClass('resend-code');
                    jQuery('#resend-otp').addClass('resend-otp');
                    
                    clearInterval(otpTimer);
                    timeLeft = 60;
                    // Clear OTP fields and validation classes
                    jQuery('#otp-verification-form .otp-field').val('').removeClass('otp-valid otp-invalid');
                    jQuery('#otp_error').remove();
                    
                    // After 1 second, restart timer (this will disable the button again and change text)
                    setTimeout(function() {
                        startOTPTimer();
                    }, 1000);
                } else {
                // Create error div
                jQuery('<div class="resend-error"></div>')
                    .text(response.data || 'Failed to resend OTP. Please try again.')
                    .insertAfter('#resend-otp');
                    // Re-enable button on error
                    jQuery('#resend-otp').prop('disabled', false).text('Resend Code');
                }
            },
            error: function() {
                // Create error div
                jQuery('<div class="resend-error"></div>').text('An error occurred. Please try again.').insertAfter('#resend-otp');
                // Re-enable button on error
                jQuery('#resend-otp').prop('disabled', false).text('Resend Code');
            }
        });
    }

    function saveProfileDetails() {
        // Get DOB from datepicker widget when available (more reliable than input.val())
        var dobVal = '';
        var $dobInput = jQuery('#profile-details-form #date_of_birth');
        try {
            var pickerDate = $dobInput.datepicker('getDate');
            if (pickerDate) {
                var y = pickerDate.getFullYear();
                var m = ('0' + (pickerDate.getMonth() + 1)).slice(-2);
                var d = ('0' + pickerDate.getDate()).slice(-2);
                dobVal = y + '-' + m + '-' + d;
            }
        } catch (e) { /* datepicker not initialized or no date */ }
        if (!dobVal) {
            dobVal = ($dobInput.length ? $dobInput.val() : jQuery('#date_of_birth').val()) || '';
        }

        // Only send DOB if it is in the past (birth date cannot be today or future)
        if (dobVal && /^\d{4}-\d{2}-\d{2}$/.test(dobVal)) {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var parts = dobVal.split('-');
            var dobDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            if (dobDate >= today) {
                dobVal = '';
                $dobInput.val('');
                try { $dobInput.datepicker('setDate', null); } catch (e) {}
                alert('Please select a past date of birth (you selected today or a future date). Use the year dropdown to pick your birth year.');
                return;
            }
        }

        const formData = {
            action: 'custom_save_profile',
            email: userEmail,
            date_of_birth: dobVal,
            state: jQuery('#state').val(),
            marketing_emails: jQuery('#marketing_emails').is(':checked') ? 1 : 0,
            sms_notifications: jQuery('#sms_notifications').is(':checked') ? 1 : 0,
            nonce: custom_registration_ajax.nonce,
        };

        jQuery('.btn-primary').prop('disabled', true).text('Saving...');

        $.ajax({
            url: custom_registration_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) nextStep();
                else alert('Error: ' + response.data);
            },
            complete: function () {
                jQuery('.btn-primary').prop('disabled', false).text('Continue');
            }
        });
    }

    function savePreferences() {
        const events = [];
        jQuery('input[name="events[]"]:checked').each(function () {
            events.push(jQuery(this).val());
        });

        const hobbies = [];
        jQuery('input[name="hobbies[]"]:checked').each(function () {
            hobbies.push(jQuery(this).val());
        });

        // const percent = calculateCompletionPercentage(true);

        const formData = {
            action: 'custom_save_preferences',
            email: userEmail,
            events: events,
            hobbies: hobbies,
            // percent: percent,
            nonce: custom_registration_ajax.nonce,
        };

        jQuery('.btn-primary').prop('disabled', true).text('Completing...');

        $.ajax({
            url: custom_registration_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    registrationComplete = true;
                    calculateCompletionPercentage();
                    jQuery('.registration-step').removeClass('active');
                    jQuery('#registration-success').show();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            complete: function () {
                jQuery('.btn-primary').prop('disabled', false).text('Done');
            }
        });
    }

    /** -------------------------
     *  PROGRESS PERCENTAGE
     * ------------------------- */
    let isEmailVerified = false; // Set this to true after OTP success

    function calculateCompletionPercentage(returnOnly = false) {

        let totalFields = 6;
        let completedFields = 0;

        // 1. First + Last Name
        const firstName = jQuery('#first_name').val()?.trim();
        const lastName  = jQuery('#surname').val()?.trim();
        if (firstName !== "" && lastName !== "") completedFields++;

        // 2. Email Verified
        if (isEmailVerified === true) completedFields++;

        // 3. Phone
        const phone = jQuery('#mobile').val()?.trim();
        if (phone !== "") completedFields++;

        // 4. DOB
        const dob = jQuery('#date_of_birth').val()?.trim();
        if (dob !== "") completedFields++;

        // 5. Interested Events
        const events = jQuery('input[name="events[]"]:checked').length;
        if (events > 0) completedFields++;

        // 6. Hobbies
        const hobbies = jQuery('input[name="hobbies[]"]:checked').length;
        if (hobbies > 0) completedFields++;

        let percent = Math.floor((completedFields / totalFields) * 100);

        if (returnOnly) return percent;

        // UI update (same as your existing)
        setTimeout(() => {
            jQuery('#completion-content').empty();

            if (percent === 100) {
                jQuery('#completion-content').html(`
                    <h2>Your profile is complete!</h2>
                    <p>You can edit this anytime in your profile settings.</p>
                `);
            } else {
                jQuery('#completion-content').html(`
                    <div class="step-heading">
                        <div class="progress-bar-container sm-main-form">
                            <div class="progress-bar" style="width:${percent}%;"></div>
                        </div>
                        <h2 class="sm-h2 sm-main-form">Thanks! Your profile is ${percent}% complete</h2>
                    </div>
                    <p class="finalise-text">To finalize, click the profile icon.</p>
                `);
            }
        }, 1000);

        return percent;
    }


    /** -------------------------
     *  OTP + UTILITIES
     * ------------------------- */
    function getOtpValue() {
        let otp = '';
        // Only get OTP from registration form (#otp-verification-form), not login form (#otp_inputs)
        jQuery('#otp-verification-form .otp-field').each(function () {
            otp += jQuery(this).val();
        });
        return otp;
    }

    function startOTPTimer() {
        otpTimer = 60;
        console.log('Hii',otpTimer);
        clearInterval(otpTimer);
        timeLeft = 60;
        jQuery('#resend-otp').prop('disabled', true);

        otpTimer = setInterval(function () {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            jQuery('#otp-timer').text(`${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
            if (timeLeft <= 0) {
                clearInterval(otpTimer);
                jQuery('#resend-otp').prop('disabled', false);
                jQuery('#resend-otp').text('Resend Code').removeClass('resend-code').addClass('resend-otp');
            }
        }, 1000);
    }

    // Utility functions
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidAustralianMobile(mobile) {
        const n = (mobile || '').replace(/\D/g, '');
        return /^04\d{8}$/.test(n) || /^614\d{8}$/.test(n);
    }

    function isStrongPassword(password) {
        // Must match server-side rule: 12+ chars, uppercase, lowercase, number, special char
        return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/.test(password);
    }

    // Australian mobile: 04XX XXX XXX (domestic) or +61 4XX XXX XXX (international)
    function formatMobileNumber(input) {
        let value = (input.val() || '').replace(/\D/g, '');
        if (value.length === 0) {
            input.val('');
            return;
        }
        if (value.startsWith('61') && value.length >= 11) {
            value = value.slice(0, 11);
            // +61 4XX XXX XXX (4 + 2 digits, then 3, then 3)
            input.val('+61 ' + value.slice(2, 5) + ' ' + value.slice(5, 8) + ' ' + value.slice(8, 11));
        } else if (value.startsWith('61')) {
            input.val(value);
        } else if (value.startsWith('04') && value.length >= 10) {
            value = value.slice(0, 10);
            // 04XX XXX XXX (04 + 2 digits, then 3, then 3)
            input.val(value.slice(0, 4) + ' ' + value.slice(4, 7) + ' ' + value.slice(7, 10));
        } else if (value.startsWith('0')) {
            input.val(value);
        } else {
            input.val(value);
        }
    }


    // function formatDateOfBirth(input) {
    //     let value = input.val().replace(/\D/g, '');
    //     if (value.length >= 8) value = value.replace(/(\d{2})(\d{2})(\d{4})/, '$1-$2-$3');
    //     input.val(value);
    // }
    function formatDateOfBirth(input) {
        console.log("Triggers from here");

        let value = input.val();

        if (!value) return;

        // If value is already in YYYY-MM-DD format
        if (value.includes('-') && value.length === 10) {
            let parts = value.split('-'); // [YYYY, MM, DD]
            if (parts[0].length === 4) {
                input.val(parts[2] + '-' + parts[1] + '-' + parts[0]);
                return;
            }
        }

        // Otherwise format numeric input DDMMYYYY → DD-MM-YYYY
        value = value.replace(/\D/g, '');

        if (value.length >= 8) {
            value = value.replace(/(\d{2})(\d{2})(\d{4})/, '$1-$2-$3');
        }

        input.val(value);
    }

    function checkPasswordStrength(password) {
        let strength = 0;

        // Length check — must be 12+ to match server-side rule
        if (password.length >= 12) strength++;

        // Lowercase
        if (/[a-z]/.test(password)) strength++;

        // Uppercase
        if (/[A-Z]/.test(password)) strength++;

        // Numbers
        if (/[0-9]/.test(password)) strength++;

        // ANY punctuation / special character including _ and -
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        // Strength Label — Very Strong only when all 5 criteria pass
        const labels = ['Weak', 'Weak', 'Fair', 'Good', 'Very Strong'];
        const strengthText = labels[strength - 1] || 'Weak';

        // Update UI
        jQuery('.strength-text').text(strengthText);
        jQuery('.strength-meter').css({
            width: (strength * 20) + '%',
            backgroundColor:
                strength <= 2 ? '#dc3545' : // Red
                    strength === 3 ? '#ffc107' : // Yellow
                        strength === 4 ? '#2ecc71' : // Light green
                            '#28a745'                  // Dark green — Very Strong
        });
    }
});

function OTPInputs() {
    console.log('this called...');
    const inputs = jQuery("#otp_inputs .otp-field, .otp-inputs .otp-field");

    if (inputs.length === 0) return; // nothing to bind

    // Ensure each OTP box shows a grey "0" when empty
    inputs.attr('placeholder', '0');

    inputs.off(); // remove old event bindings if function is called again

    inputs.on("input", function () {
        const index = inputs.index(this);
        let value = jQuery(this).val().replace(/[^0-9]/g, "");
        jQuery(this).val(value);

        // Check if this is registration form (#otp-verification-form) or login form (#otp_inputs)
        const isRegistrationForm = jQuery(this).closest('#otp-verification-form').length > 0;
        const isLoginForm = jQuery(this).closest('#otp_inputs').length > 0;

        // Remove validation classes when user is typing (to allow re-validation)
        if (isRegistrationForm || isLoginForm) {
            jQuery(this).removeClass('otp-valid otp-invalid');
        }

        if (value && index < inputs.length - 1) {
            inputs.eq(index + 1).focus();
        }

        if (value.length > 1) {
            const digits = value.split("");
            digits.forEach((digit, i) => {
                if (inputs.eq(index + i).length) {
                    const input = inputs.eq(index + i);
                    input.val(digit);
                    // Remove validation classes when user is typing
                    if (isRegistrationForm || isLoginForm) {
                        input.removeClass('otp-valid otp-invalid');
                    }
                }
            });
            const nextIndex = index + value.length;
            if (inputs.eq(nextIndex).length) {
                inputs.eq(nextIndex).focus();
            }
        }

        // Check if all OTP fields are filled and auto-validate
        if (isRegistrationForm && jQuery('#otp-verification-form .otp-field').length > 0) {
            setTimeout(function() {
                const allFilled = jQuery('#otp-verification-form .otp-field').toArray().every(function(input) {
                    return jQuery(input).val() && jQuery(input).val().trim() !== '';
                });
                if (allFilled && jQuery('#otp-verification-form .otp-field').length === 6) {
                    if (typeof window.autoValidateOTP === 'function') {
                        window.autoValidateOTP();
                    }
                }
            }, 100);
        }

        // Auto-validate for login form
        if (isLoginForm && jQuery('#otp_inputs .otp-field').length > 0) {
            setTimeout(function() {
                const allFilled = jQuery('#otp_inputs .otp-field').toArray().every(function(input) {
                    return jQuery(input).val() && jQuery(input).val().trim() !== '';
                });
                if (allFilled && jQuery('#otp_inputs .otp-field').length === 6) {
                    if (typeof window.autoValidateLoginOTP === 'function') {
                        window.autoValidateLoginOTP();
                    }
                }
            }, 100);
        }
    });

    // Handle Backspace
    inputs.on("keydown", function (e) {
        const index = inputs.index(this);
        if (e.key === "Backspace" && !jQuery(this).val() && index > 0) {
            inputs.eq(index - 1).focus();
        }
    });

    // Handle paste
    inputs.on("paste", function (e) {
        e.preventDefault();
        const paste = (e.originalEvent.clipboardData || window.clipboardData).getData("text");
        const digits = paste.replace(/\D/g, "").split("");

        // Check if this is registration form (#otp-verification-form) or login form (#otp_inputs)
        const isRegistrationForm = jQuery(this).closest('#otp-verification-form').length > 0;
        const isLoginForm = jQuery(this).closest('#otp_inputs').length > 0;

        digits.forEach((digit, i) => {
            if (inputs.eq(i).length) {
                const input = inputs.eq(i);
                input.val(digit);
                // Remove validation classes when user is typing
                if (isRegistrationForm || isLoginForm) {
                    input.removeClass('otp-valid otp-invalid');
                }
            }
        });

        const lastFilled = Math.min(digits.length, inputs.length) - 1;
        inputs.eq(lastFilled).focus();

        // Check if all OTP fields are filled and auto-validate after paste for registration form
        if (isRegistrationForm && jQuery('#otp-verification-form .otp-field').length > 0) {
            setTimeout(function() {
                const allFilled = jQuery('#otp-verification-form .otp-field').toArray().every(function(input) {
                    return jQuery(input).val() && jQuery(input).val().trim() !== '';
                });
                if (allFilled && jQuery('#otp-verification-form .otp-field').length === 6) {
                    if (typeof window.autoValidateOTP === 'function') {
                        window.autoValidateOTP();
                    }
                }
            }, 100);
        }

        // Auto-validate for login form after paste
        if (isLoginForm && jQuery('#otp_inputs .otp-field').length > 0) {
            setTimeout(function() {
                const allFilled = jQuery('#otp_inputs .otp-field').toArray().every(function(input) {
                    return jQuery(input).val() && jQuery(input).val().trim() !== '';
                });
                if (allFilled && jQuery('#otp_inputs .otp-field').length === 6) {
                    if (typeof window.autoValidateLoginOTP === 'function') {
                        window.autoValidateLoginOTP();
                    }
                }
            }, 100);
        }
    });
}
OTPInputs();

document.addEventListener("DOMContentLoaded", function () {

    const inputs = document.querySelectorAll("#otp_inputs .otp-field");  // Updated selector - Login form only
    console.log('1');
    
    inputs.forEach((input, index) => {
        console.log('2');

        // Handle input typing
        input.addEventListener("input", (e) => {
            const value = e.target.value.replace(/[^0-9]/g, ""); // Only digits
            e.target.value = value;

            // Remove validation classes when user is typing (to allow re-validation)
            e.target.classList.remove('otp-valid', 'otp-invalid');

            // Move to next input if filled
            if (value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }

            // Handle pasting multiple digits
            if (value.length > 1) {
                const digits = value.split("");
                for (let i = 0; i < digits.length && index + i < inputs.length; i++) {
                    inputs[index + i].value = digits[i];
                    // Remove validation classes when user is typing
                    inputs[index + i].classList.remove('otp-valid', 'otp-invalid');
                }
                const nextIndex = index + value.length;
                if (nextIndex < inputs.length) {
                    inputs[nextIndex].focus();
                }
            }

            // Check if all OTP fields are filled and auto-validate for login form
            setTimeout(function() {
                const allFilled = Array.from(inputs).every(function(inp) {
                    return inp.value && inp.value.trim() !== '';
                });
                if (allFilled && inputs.length === 6) {
                    if (typeof window.autoValidateLoginOTP === 'function') {
                        window.autoValidateLoginOTP();
                    }
                }
            }, 100);
        });

        // Handle backspace
        input.addEventListener("keydown", (e) => {
            if (e.key === "Backspace" && !input.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        // Handle paste event (for entire OTP)
        input.addEventListener("paste", (e) => {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData("text");
            const digits = paste.replace(/\D/g, "").split("");

            digits.forEach((digit, i) => {
                if (inputs[i]) {
                    inputs[i].value = digit;
                    // Remove validation classes when user is typing
                    inputs[i].classList.remove('otp-valid', 'otp-invalid');
                }
            });

            const lastFilled = Math.min(digits.length, inputs.length) - 1;
            if (inputs[lastFilled]) inputs[lastFilled].focus();
            
            // Check if all OTP fields are filled and auto-validate after paste for login form
            setTimeout(function() {
                const allFilled = Array.from(inputs).every(function(inp) {
                    return inp.value && inp.value.trim() !== '';
                });
                if (allFilled && inputs.length === 6) {
                    if (typeof window.autoValidateLoginOTP === 'function') {
                        window.autoValidateLoginOTP();
                    }
                }
            }, 100);
        });
    });
});
document.addEventListener("DOMContentLoaded", function () {
    const inputs = document.querySelectorAll(".otp-inputs .otp-field");

    inputs.forEach((input, index) => {
        // Handle input typing
        input.addEventListener("input", (e) => {
            const value = e.target.value.replace(/[^0-9]/g, ""); // Only digits
            e.target.value = value;

            // Remove validation classes when user is typing
            e.target.classList.remove('otp-valid', 'otp-invalid');

            // Move to next input if filled
            if (value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }

            // Handle pasting multiple digits
            if (value.length > 1) {
                const digits = value.split("");
                for (let i = 0; i < digits.length && index + i < inputs.length; i++) {
                    inputs[index + i].value = digits[i];
                    inputs[index + i].classList.remove('otp-valid', 'otp-invalid');
                }
                const nextIndex = index + value.length;
                if (nextIndex < inputs.length) {
                    inputs[nextIndex].focus();
                }
            }

            // Check if all OTP fields are filled and auto-validate
            setTimeout(function() {
                const allFilled = Array.from(inputs).every(function(inp) {
                    return inp.value && inp.value.trim() !== '';
                });
                if (allFilled && inputs.length === 6) {
                    if (typeof window.autoValidateOTP === 'function') {
                        window.autoValidateOTP();
                    }
                }
            }, 100);
        });

        // Handle backspace
        input.addEventListener("keydown", (e) => {
            if (e.key === "Backspace" && !input.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        // Handle paste event (for entire OTP)
        input.addEventListener("paste", (e) => {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData("text");
            const digits = paste.replace(/\D/g, "").split("");

            digits.forEach((digit, i) => {
                if (inputs[i]) {
                    inputs[i].value = digit;
                    inputs[i].classList.remove('otp-valid', 'otp-invalid');
                }
            });

            const lastFilled = Math.min(digits.length, inputs.length) - 1;
            if (inputs[lastFilled]) inputs[lastFilled].focus();

            // Check if all OTP fields are filled and auto-validate after paste
            setTimeout(function() {
                const allFilled = Array.from(inputs).every(function(inp) {
                    return inp.value && inp.value.trim() !== '';
                });
                if (allFilled && inputs.length === 6) {
                    if (typeof window.autoValidateOTP === 'function') {
                        window.autoValidateOTP();
                    }
                }
            }, 100);
        });
    });
});


jQuery(document).ready(function ($) {

    // Date of birth datepicker is now initialized when user reaches step 3 (see showStep in first block)

    function initOtpInputs() {
        const inputs = document.querySelectorAll(".otp-inputs .otp-input");
        if (!inputs.length) return;

        inputs.forEach((input, index) => {
            input.addEventListener("input", (e) => {
                const value = e.target.value.replace(/[^0-9]/g, "");
                e.target.value = value;

                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }

                if (value.length > 1) {
                    const digits = value.split("");
                    for (let i = 0; i < digits.length && index + i < inputs.length; i++) {
                        inputs[index + i].value = digits[i];
                    }
                    const nextIndex = index + value.length;
                    if (nextIndex < inputs.length) {
                        inputs[nextIndex].focus();
                    }
                }
            });

            input.addEventListener("keydown", (e) => {
                if (e.key === "Backspace" && !input.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener("paste", (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData("text");
                const digits = paste.replace(/\D/g, "").split("");

                digits.forEach((digit, i) => {
                    if (inputs[i]) inputs[i].value = digit;
                });

                const lastFilled = Math.min(digits.length, inputs.length) - 1;
                if (inputs[lastFilled]) inputs[lastFilled].focus();
            });
        });
    }

    let otpTimerInterval;

    function formatOtpTimer(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return m + ":" + (s < 10 ? "0" : "") + s;
    }


    function startOtpTimer() {
        console.log('Inside the startOtpTimer');
        let seconds = 60; // 5 minutes - resend only when OTP has expired (matches 5 min validity)
        
        clearInterval(otpTimerInterval);

        // Get fresh references to elements each time
        const getElements = () => {
            return {
                timerDisplay: document.getElementById("otp_timer"),
                resendBtn: document.getElementById("resend_otp")
            };
        };

        // Wait a tiny bit to ensure DOM is ready (in case HTML was just inserted)
        setTimeout(() => {
            const elements = getElements();
            if (!elements.timerDisplay || !elements.resendBtn) {
                console.warn('OTP timer elements not found');
                return; // Elements not found, exit
            }

            // Set initial display value (M:SS format, e.g. 5:00, 4:59)
            elements.timerDisplay.textContent = formatOtpTimer(seconds);
            elements.resendBtn.style.pointerEvents = "none";
            elements.resendBtn.style.opacity = "0.5";

            otpTimerInterval = setInterval(() => {
                seconds--;
                
                // Get fresh reference to timer display in case DOM was updated
                const currentElements = getElements();
                if (currentElements.timerDisplay) {
                    currentElements.timerDisplay.textContent = formatOtpTimer(seconds);
                }

                if (seconds <= 0) {
                    clearInterval(otpTimerInterval);
                    
                    // Get fresh references before enabling button
                    const finalElements = getElements();
                    if (finalElements.timerDisplay) {
                        finalElements.timerDisplay.textContent = "0:00";
                    }
                    if (finalElements.resendBtn) {
                        finalElements.resendBtn.style.pointerEvents = "auto";
                        finalElements.resendBtn.style.opacity = "1";
                    }
                }
            }, 1000);
        }, 100); // Small delay to ensure DOM is ready
    }

    // Handle login submit
    jQuery('#custom-login-form').on('submit', function (e) {
        e.preventDefault();

        var email = jQuery('#user_login').val();
        var password = jQuery('#user_pass').val();

        jQuery('#error-msg').html('');
        jQuery('.custom-login-btn').text('Sending OTP...');

        function doSendLoginOtp(recaptcha_token) {
            $.ajax({
                url: custom_registration_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'send_login_otp',
                    email: email,
                    password: password,
                    recaptcha_token: recaptcha_token || ''
                },
                success: function (response) {
                    jQuery('.custom-login-btn').text('Sign in');
                    if (response.success) {
                        jQuery('#login-box').html(response.data.html);
                        initOtpInputs();
                        OTPInputs();
                        startOtpTimer();
                        initResendOtp(email, password);
                    } else {
                        jQuery('#error-msg').html(response.data.message);
                    }
                },
                error: function (xhr) {
                    jQuery('.custom-login-btn').text('Sign in');
                    var msg = 'Something went wrong. Please try again.';
                    try {
                        var json = JSON.parse(xhr.responseText);
                        if (json.data && json.data.message) msg = json.data.message;
                    } catch (e) {}
                    jQuery('#error-msg').html(msg);
                }
            });
        }

        if (typeof grecaptcha !== 'undefined') {
            var loginRecaptchaToken = jQuery('#recaptcha_token_login').val() || grecaptcha.getResponse();
            if (!loginRecaptchaToken) {
                jQuery('.custom-login-btn').text('Sign in');
                jQuery('#error-msg').html("Please confirm you're not a robot.");
                return;
            }
            doSendLoginOtp(loginRecaptchaToken);
        } else {
            doSendLoginOtp('');
        }
    });

    function logingetOtpValue() {
        let otp = '';
        jQuery('#otp_inputs .otp-field').each(function () {
            otp += jQuery(this).val();
        });
        return otp;
    }

    // Check if all login OTP fields are filled
    function isLoginOtpComplete() {
        const inputs = jQuery('#otp_inputs .otp-field');
        if (inputs.length !== 6) return false;
        let allFilled = true;
        inputs.each(function() {
            if (!jQuery(this).val() || jQuery(this).val().trim() === '') {
                allFilled = false;
                return false; // break the loop
            }
        });
        return allFilled;
    }

    let isLoginAutoValidating = false; // Flag to prevent multiple simultaneous validations

    // Auto-validate login OTP when all fields are filled
    function autoValidateLoginOTP() {
        console.log('sdfsdfdssdfdsf');
        // Prevent multiple simultaneous validations
        if (isLoginAutoValidating) {
            return;
        }

        if (!isLoginOtpComplete()) {
            return; // Don't validate if not all fields are filled
        }

        const otpCode = logingetOtpValue();
        if (!otpCode || otpCode.length !== 6) {
            return; // Don't validate if OTP is incomplete
        }

        const otp_token = jQuery('#otp_token').val();
        if (!otp_token) {
            return; // Don't validate if otp_token is not available
        }

        // Set flag to prevent multiple calls
        isLoginAutoValidating = true;

        const formData = {
            action: 'verify_login_otp',
            otp_token: otp_token,
            otp_code: otpCode,
            is_auto_validation: true, // Flag to indicate this is auto-validation, not submission
        };

        $.ajax({
            url: custom_registration_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    // OTP is correct - add otp-valid class to all fields (visual feedback only)
                    jQuery('#otp_inputs .otp-field').removeClass('otp-invalid').addClass('otp-valid');
                } else {
                    // OTP is incorrect - add otp-invalid class to all fields (visual feedback only)
                    // Do NOT show error messages here - only visual feedback on cells
                    jQuery('#otp_inputs .otp-field').removeClass('otp-valid').addClass('otp-invalid');
                }
            },
            error: function() {
                // On error, mark as invalid (visual feedback only)
                jQuery('#otp_inputs .otp-field').removeClass('otp-valid').addClass('otp-invalid');
            },
            complete: function() {
                // Reset flag after validation completes
                isLoginAutoValidating = false;
            }
        });
    }

    // Make autoValidateLoginOTP globally accessible
    window.autoValidateLoginOTP = autoValidateLoginOTP;

    // Handle OTP verification (delegated)
    jQuery(document).on('click', '#verify_otp_btn', function (e) {
        console.log('verify_otp_btn');

        e.preventDefault();

        var otp_code = logingetOtpValue();
        var otp_token = jQuery('#otp_token').val();

        if (!otp_code) {
            // Add otp-invalid class to all fields if OTP is empty
            jQuery('#otp_inputs .otp-field').removeClass('otp-valid').addClass('otp-invalid');
            return;
        }

        if (!otp_token) {
            jQuery('#error-msg').html('Invalid session. Please log in again.');
            return;
        }

        $.ajax({
            url: custom_registration_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_login_otp',
                otp_token: otp_token,
                otp_code: otp_code
            },
            beforeSend: function () {
                jQuery('#verify_otp_btn').text('Verifying...');
            },
            success: function (response) {
                if (response.success) {
                    // OTP is correct - add otp-valid class to all fields
                    jQuery('#otp_inputs .otp-field').removeClass('otp-invalid').addClass('otp-valid');
                    jQuery('#verify_otp_btn').text('Success!');
                    loginComplete = true; // Allow redirect without "are you sure" popup
                    if (typeof window._loginBeforeUnloadHandler === 'function') {
                        window.removeEventListener('beforeunload', window._loginBeforeUnloadHandler);
                    }
                    window.location.href = response.data.redirect;
                } else {
                    // OTP is incorrect - add otp-invalid class to all fields
                    jQuery('#otp_inputs .otp-field').removeClass('otp-valid').addClass('otp-invalid');
                    jQuery('#error-msg').html(response.data.message);
                    jQuery('#verify_otp_btn').text('Verify OTP');
                }
            },
            error: function() {
                // On error, mark as invalid
                jQuery('#otp_inputs .otp-field').removeClass('otp-valid').addClass('otp-invalid');
                jQuery('#verify_otp_btn').text('Verify OTP');
            }
        });
    });


    function initResendOtp(email, password) {
        console.log('this button clicked..');
        // Remove any existing event listeners by cloning and replacing the button
        const resendBtn = document.getElementById("resend_otp");
        if (!resendBtn) return;

        // Remove old event listeners by replacing with a clone
        const newResendBtn = resendBtn.cloneNode(true);
        resendBtn.parentNode.replaceChild(newResendBtn, resendBtn);

        // Add click handler to the new button
        newResendBtn.addEventListener("click", function (e) {
            e.preventDefault();
            jQuery('.resend-error').remove();

            // Check if button is disabled (via pointer-events or disabled attribute)
            if (this.style.pointerEvents === "none" || this.disabled) {
                return false;
            }

            // Disable button while sending
            this.style.pointerEvents = "none";
            this.style.opacity = "0.5";
            const originalText = this.innerHTML;
            this.innerHTML = "Sending...";

            function doResendOtp() {
                $.ajax({
                    url: custom_registration_ajax.ajax_url,
                    type: "POST",
                    data: {
                        action: "send_login_otp",
                        email: email,
                        password: password,
                        prior_otp_token: jQuery('#otp_token').val() || ''
                    },
                    success: function (response) {
                        if (response.success) {
                            newResendBtn.innerHTML = "Resend <span id=\"otp_timer\">5:00</span>";
                            newResendBtn.style.pointerEvents = "none";
                            newResendBtn.style.opacity = "0.5";
                            loginComplete = true;

                            let seconds = 60;
                            const timerSpan = document.getElementById("otp_timer");
                            if (timerSpan) {
                                timerSpan.textContent = formatOtpTimer(seconds);
                            }

                            const countdownInterval = setInterval(function() {
                                seconds--;
                                if (timerSpan) {
                                    timerSpan.textContent = formatOtpTimer(seconds);
                                }
                                if (seconds <= 0) {
                                    clearInterval(countdownInterval);
                                    if (timerSpan) timerSpan.textContent = "0:00";
                                    newResendBtn.style.pointerEvents = "auto";
                                    newResendBtn.style.opacity = "1";
                                }
                            }, 1000);

                            setTimeout(function() {
                                jQuery('#login-box').html(response.data.html);
                                jQuery('#otp_inputs .otp-field').val('').removeClass('otp-valid otp-invalid');
                                initOtpInputs();
                                OTPInputs();
                                startOtpTimer();
                                initResendOtp(email, password);
                            }, 500);
                        } else {
                            var msg = response.data && response.data.message ? response.data.message : 'Failed to resend. Please try again.';
                            jQuery('<div class="resend-error"></div>').text(msg).insertAfter(newResendBtn);
                            newResendBtn.style.pointerEvents = "auto";
                            newResendBtn.style.opacity = "1";
                            newResendBtn.innerHTML = originalText;
                        }
                    },
                    error: function (xhr) {
                        var msg = 'Something went wrong. Please try again.';
                        try {
                            var json = JSON.parse(xhr.responseText);
                            if (json.data && json.data.message) msg = json.data.message;
                        } catch (e) {}
                        jQuery('<div class="resend-error"></div>').text(msg).insertAfter(newResendBtn);
                        newResendBtn.style.pointerEvents = "auto";
                        newResendBtn.style.opacity = "1";
                        newResendBtn.innerHTML = originalText;
                    }
                });
            }

            doResendOtp();
        });
    }
});