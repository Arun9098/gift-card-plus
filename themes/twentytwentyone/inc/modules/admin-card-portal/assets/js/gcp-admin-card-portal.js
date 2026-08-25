jQuery(document).ready(function($) {

    /////////////////////////////////////
    var otpTimerInterval;

    function startOtpTimer(duration) {
        var timer = duration, minutes, seconds;
        var link = $('#gcp-resend-otp-link');
        var timerSpan = $('#gcp-otp-timer');

        // Disable link
        link.css({ 'pointer-events': 'none', 'opacity': '0.5', 'text-decoration': 'none' });
        timerSpan.show();

        clearInterval(otpTimerInterval); // Clear any existing

        otpTimerInterval = setInterval(function () {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            timerSpan.text("(" + minutes + ":" + seconds + ")");

            if (--timer < 0) {
                clearInterval(otpTimerInterval);
                // Re-enable link
                link.css({ 'pointer-events': 'auto', 'opacity': '1', 'text-decoration': 'underline' });
                timerSpan.hide();
            }
        }, 1000);
    }

    ///////////////////

    // Global State
    var currentCardData = {
        id: 0,
        method: 'email',
        pendingUpdate: {} // Store data for OTP step
    };

    // --- HELPERS ---
    function gcp_portal_change_view(viewId) {
        // Hide all views inside the modal
        $('.gcp-modal-view').hide();
        // Show the specific view
        $('#gcp-view-' + viewId).fadeIn(200);
    }

    // Helper: Close Modal
    function gcp_close_modal() {
        $('#gcp-resend-modal').fadeOut(200, function() {
            gcp_portal_change_view('confirm');
            
            // Reset Buttons
            $('#gcp-confirm-resend-btn').text('Yes').prop('disabled', false);
            $('#gcp-submit-update-btn').text('Update').prop('disabled', false);
            
            // Reset Errors & Visibility
            $('.gcp-error-text').hide();
            $('#gcp-container-email, #gcp-container-mobile').hide();
        });
    }

    function validateEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function validateAusMobile(phone) {
        // Formats: 04XXXXXXXX or +614XXXXXXXX
        var re = /^(\+?61|0)4\d{8}$/;
        // Strip spaces/dashes before checking
        var cleanPhone = phone.replace(/[\s\-]/g, '');
        return re.test(cleanPhone);
    }

    function sendOTP() {
        var btn = $('#gcp-submit-update-btn');
        var originalText = btn.text();
        btn.data('original-text', originalText).text('Sending OTP...').prop('disabled', true);

        $.ajax({
            url: gcp_portal_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gcp_portal_send_update_otp',
                nonce: gcp_portal_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    gcp_portal_change_view('otp');
                    
                    // START 2 MINUTE TIMER
                    startOtpTimer(120); 
                    
                    $('.gcp-otp-digit').first().focus();
                } else {
                    alert(response.data || 'Failed to send OTP.');
                }
            },
            error: function() {
                alert('System error sending OTP.');
            },
            complete: function() {
                btn.text(originalText).prop('disabled', false);
            }
        });
    }

    // --- OPEN RESEND MODAL ---
    $('.gcp-btn-resend').on('click', function(e) {
        e.preventDefault();
        
        // Capture Data from Button Attributes
        var btn = $(this);
        
        // Capture Data
        currentCardData.id = btn.data('card-id');
        currentCardData.method = (btn.data('current-method') || 'email').indexOf('sms') !== -1 ? 'sms' : 'email';

        // Display Logic
        var displayEmail = btn.data('current-email') || "the recipient";
        if(currentCardData.method === 'sms') {
             displayEmail = btn.data('current-phone') || "the recipient";
        }
        $('.gcp-resend-email-target').text(displayEmail);
        $('#gcp-confirm-resend-btn').data('card-id', currentCardData.id); 

        gcp_portal_change_view('confirm');
        $('#gcp-resend-modal').fadeIn(200);
    });

    // --- CLOSE MODAL ---
    // Handle both 'X' button and clicking the dark overlay
    $('.gcp-modal-close, .gcp-modal-overlay').on('click', function(e) {
        if (e.target === this || $(this).hasClass('gcp-modal-close')) {
            gcp_close_modal();
        }
    });

    $(document).keyup(function(e) {
        if (e.key === "Escape" && $('#gcp-resend-modal').is(':visible')) {
            gcp_close_modal();
        }
    });

    // --- CONFIRM RESEND (AJAX) ---
    $('#gcp-confirm-resend-btn').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var cardId = btn.data('card-id');

        // Loading State
        btn.text('Sending...').prop('disabled', true);

        $.ajax({
            url: gcp_portal_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gcp_portal_resend_card',
                card_id: cardId,
                nonce: gcp_portal_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    // SUCCESS: Switch to Success View
                    gcp_portal_change_view('success');
                } else {
                    alert('Error: ' + response.data);
                    btn.text('Yes').prop('disabled', false);
                }
            },
            error: function() {
                alert('System error. Please try again.');
                btn.text('Yes').prop('disabled', false);
            },
            // complete: function() {
            //     // Reset button state
            //     btn.text('Yes').prop('disabled', false);
            // }
        });
    });

    // --- "NO, UPDATE DELIVERY" ---
    $('#gcp-trigger-update-view').on('click', function(e) {
        e.preventDefault();
        
        // Read Current Dropdown Value (Set by PHP)
        $('#gcp-update-method-select').val(currentCardData.method);

        // Show Correct Input Container
        $('.gcp-error-text').hide();
        $('#gcp-container-email, #gcp-container-mobile').hide();
        
        // Show Matching Container
        if (currentCardData.method === 'sms') {
            $('#gcp-container-mobile').show();
        } else {
            $('#gcp-container-email').show();
        }

        gcp_portal_change_view('update');
    });

    // --- 5. HANDLE METHOD TOGGLE (Email vs SMS) ---
    $('#gcp-update-method-select').on('change', function() {
        var method = $(this).val();

        // Hide Errors
        $('.gcp-error-text').hide();
        
        // Toggle Containers
        if (method === 'sms') {
            $('#gcp-container-email').hide();
            $('#gcp-container-mobile').show();
        } else {
            $('#gcp-container-mobile').hide();
            $('#gcp-container-email').show();
        }
    });

    // --- 6. UPDATE & RESEND SUBMIT ---
    $('#gcp-submit-update-btn').on('click', function(e) {
        e.preventDefault();
        var method = $('#gcp-update-method-select').val();
        var value = '';
        var errorBox = null;

        // GRAB VALUE FROM THE VISIBLE INPUT
        if (method === 'sms') {
            value = $('#gcp-input-mobile').val().trim();
            errorBox = $('#gcp-error-mobile');
        } else {
            value = $('#gcp-input-email').val().trim();
            errorBox = $('#gcp-error-email');
        }

        // Validation
        if (value === '') {
            errorBox.text('This field is required.').show();
            return;
        }

        if (method === 'email' && !validateEmail(value)) {
            errorBox.text('Please enter a valid email address.').show();
            return;
        }

        if (method === 'sms' && !validateAusMobile(value)) {
            errorBox.text('Please enter a valid Australian mobile number (e.g., 0412 345 678).').show();
            return;
        }

        // PREPARE DATA FOR OTP STEP
        currentCardData.pendingUpdate = {
            method: method,
            value: value
        };

        // Send OTP
        sendOTP();
    });

    // --- 7. OTP INPUT LOGIC (Auto-advance) ---
    $('.gcp-otp-digit').on('keyup', function(e) {
        var key = e.which || e.keyCode;
        var inputs = $('.gcp-otp-digit');
        var index = inputs.index(this);

        // Allow backspace to go back
        if (key === 8 || key === 46) {
            if (index > 0 && $(this).val().length === 0) {
                inputs.eq(index - 1).focus();
            }
            return;
        }

        // Auto-advance if number entered
        if ($(this).val().length === 1 && index < inputs.length - 1) {
            inputs.eq(index + 1).focus();
        }
        
        // Clear error on type
        $('#gcp-otp-error').hide();
    });

    // Handle Paste (e.g. 123456)
    $('.gcp-otp-digit').on('paste', function(e) {
        var pasteData = e.originalEvent.clipboardData.getData('text');
        var digits = pasteData.replace(/\D/g, '').split(''); // Only numbers
        
        if (digits.length > 0) {
            $('.gcp-otp-digit').each(function(i) {
                if (digits[i]) {
                    $(this).val(digits[i]);
                }
            });
            // Focus last filled or next empty
            var focusIndex = Math.min(digits.length, 5);
            $('.gcp-otp-digit').eq(focusIndex).focus();
            e.preventDefault();
        }
    });

    // --- 8. RESEND OTP LINK ---
    $('#gcp-resend-otp-link').on('click', function(e) {
        e.preventDefault();
        
        var code = '';
        $('.gcp-otp-digit').each(function() {
            code += $(this).val();
        });

        if (code.length < 6) {
            $('#gcp-otp-error').text('Please enter the 6-digit code.').show();
            return;
        }

        var btn = $(this);
        btn.text('Verifying...').prop('disabled', true);

        $.ajax({
            url: gcp_portal_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gcp_portal_verify_otp_and_update',
                nonce: gcp_portal_vars.nonce,
                otp_code: code,
                card_id: currentCardData.id,
                update_data: currentCardData.pendingUpdate
            },
            success: function(response) {
                if (response.success) {
                    // Update the final success message with the NEW value
                    $('.gcp-resend-email-target').text(currentCardData.pendingUpdate.value);
                    gcp_portal_change_view('success');
                } else {
                    $('#gcp-otp-error').text(response.data).show();
                    btn.text('Submit').prop('disabled', false);
                }
            },
            error: function() {
                $('#gcp-otp-error').text('System error occurred.').show();
                btn.text('Submit').prop('disabled', false);
            }
        });
    });

    // --- 9. VERIFY OTP & EXECUTE UPDATE ---
    $('#gcp-verify-otp-btn').on('click', function(e) {
        e.preventDefault();
        
        // Collect Code
        var code = '';
        $('.gcp-otp-digit').each(function() {
            code += $(this).val();
        });

        if (code.length < 6) {
            $('#gcp-otp-error').text('Please enter the 6-digit code.').show();
            return;
        }

        var btn = $(this);
        btn.text('Verifying...').prop('disabled', true);

        $.ajax({
            url: gcp_portal_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gcp_portal_verify_otp_and_update', // We will build this next
                nonce: gcp_portal_vars.nonce,
                otp_code: code,
                card_id: currentCardData.id,
                update_data: currentCardData.pendingUpdate
            },
            success: function(response) {
                if (response.success) {
                    // Success! Show final screen with NEW email
                    $('.gcp-resend-email-target').text(currentCardData.pendingUpdate.value);
                    gcp_portal_change_view('success');
                } else {
                    $('#gcp-otp-error').text(response.data).show();
                    btn.text('Verify & Update').prop('disabled', false);
                }
            },
            error: function() {
                alert('System error.');
                btn.text('Verify & Update').prop('disabled', false);
            }
        });
    });


    // ==========================================
    // WALLET MODAL LOGIC
    // ==========================================

    function gcp_wallet_change_view(viewId) {
        $('.gcp-wallet-view').hide();
        $('#gcp-wallet-view-' + viewId).show();
    }

    function gcp_close_wallet_modal() {
        $('#gcp-wallet-modal').fadeOut(200, function() {
            gcp_wallet_change_view('confirm');
            $('#gcp-wallet-confirm-btn').prop('disabled', false).text('Yes');
        });
    }

    // --- 1. OPEN WALLET MODAL ---
    $('.gcp-btn-add-wallet').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);

        // REQ: Value should be always mail.
        // If email is empty, we show a placeholder or empty string, but NOT phone.
        var email = btn.data('email') || ''; 
        var targetDisplay = email ? email : 'this recipient (No Email Found)';
        
        // Store raw email for logic usage
        $('#gcp-wallet-confirm-btn').data('target-email', email);
        $('#gcp-wallet-confirm-btn').data('card-id', btn.data('card-id'));
        
        gcp_wallet_change_view('confirm');
        $('#gcp-wallet-modal').fadeIn(200);

        // Update UI
        $('.gcp-wallet-target-display').text(targetDisplay);
        
        gcp_wallet_change_view('confirm');
        $('#gcp-wallet-modal').fadeIn(200);
    });

    // --- 2. CLOSE HANDLERS (Wallet) ---
    // Handle X click and Overlay click for Wallet Modal
    $('#gcp-wallet-modal .gcp-modal-close, #gcp-wallet-modal').on('click', function(e) {
        if (e.target === this || $(this).hasClass('gcp-modal-close')) {
            gcp_close_wallet_modal();
        }
    });

    // Update Global Escape Key to handle BOTH modals
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            if ($('#gcp-resend-modal').is(':visible')) {
                gcp_close_modal(); // Close Resend
            }
            if ($('#gcp-wallet-modal').is(':visible')) {
                gcp_close_wallet_modal(); // Close Wallet
            }
        }
    });

    // --- 3. YES (Add to Wallet - Flow A) ---
    $('#gcp-wallet-confirm-btn').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var cardId = btn.data('card-id');
        var email = btn.data('target-email'); // The email we are trying to add to

        if (!email) {
            alert("No email address found for this card. Please update the recipient first.");
            return;
        }

        btn.text('Processing...').prop('disabled', true);

        $.ajax({
            url: gcp_portal_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gcp_portal_add_to_wallet',
                nonce: gcp_portal_vars.nonce,
                card_id: cardId,
                target_email: email
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Update the Link in Success View
                    $('#gcp-wallet-success-link').attr('href', data.wallet_url);
                    $('.gcp-wallet-target-display').text(email);

                    // Check for "Expired" Warning
                    if (data.is_expired) {
                        gcp_wallet_change_view('expired');
                    } else {
                        gcp_wallet_change_view('success');
                    }

                } else {
                    // Show Error (Account missing, Tech error, etc)
                    alert(response.data);
                    btn.text('Yes').prop('disabled', false);
                    // Close modal if it's a "No Account" error so they can try "No, update recipient"
                    if (response.data.indexOf('No giftcardsplus account') !== -1) {
                         // Optional: Keep open? The requirements say "Failed: ... please ask them to sign up".
                         // Leaving open allows them to click "No, update recipient"
                    }
                }
            },
            error: function() {
                alert('Failed: Technical error. Please try again or raise to support.');
                btn.text('Yes').prop('disabled', false);
            }
        });
    });

    // --- 4. EXPIRED "OK" BUTTON ---
    $('#gcp-wallet-expired-ok-btn').on('click', function(e) {
        e.preventDefault();
        // Move to the final Success screen
        gcp_wallet_change_view('success');
    });

    // --- 5. NO UPDATE RECIPIENT (Flow B) ---
    $('#gcp-wallet-trigger-update').on('click', function(e) {
        e.preventDefault();
        gcp_wallet_change_view('update');
        // Pre-fill
        var currentEmail = $('#gcp-wallet-confirm-btn').data('target-email');
        $('#gcp-wallet-update-input').val(currentEmail);
    });

    // ==========================================
    // WALLET FLOW B: SEARCH & OTP
    // ==========================================

    var walletSearchTimeout;

    // 1. USER SEARCH (Input Logic)
    $('#gcp-wallet-search-input').on('keyup', function() {
        var term = $(this).val().trim();
        var resultsBox = $('#gcp-wallet-search-results');
        
        if (term.length < 3) {
            resultsBox.hide();
            return;
        }

        clearTimeout(walletSearchTimeout);
        walletSearchTimeout = setTimeout(function() {
            $.ajax({
                url: gcp_portal_vars.ajax_url,
                data: {
                    action: 'gcp_portal_search_users',
                    term: term,
                    nonce: gcp_portal_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        resultsBox.empty().show();
                        $.each(response.data, function(index, user) {
                            resultsBox.append('<li data-email="' + user.email + '">' + user.text + '</li>');
                        });
                    } else {
                        resultsBox.hide();
                    }
                }
            });
        }, 300); // 300ms debounce
    });

    // 2. SELECT USER (Click Result)
    $(document).on('click', '#gcp-wallet-search-results li', function() {
        var email = $(this).data('email');
        $('#gcp-wallet-search-input').val(email);
        $('#gcp-wallet-search-results').hide();
    });

    // 3. SUBMIT SELECTION -> SEND OTP
    $('#gcp-wallet-submit-update-btn').on('click', function(e) {
        e.preventDefault();
        var email = $('#gcp-wallet-search-input').val().trim();
        
        if (!email) {
            $('#gcp-wallet-update-error').text("Please select a recipient.").show();
            return;
        }

        var btn = $(this);
        btn.text('Sending OTP...').prop('disabled', true);

        // Reuse the existing OTP sender (It sends to Admin regardless of context)
        $.ajax({
            url: gcp_portal_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gcp_portal_send_update_otp',
                nonce: gcp_portal_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Store the selected email for the next step
                    $('#gcp-wallet-verify-otp-btn').data('target-email', email);
                    gcp_wallet_change_view('otp');
                } else {
                    alert('Error: ' + response.data);
                    btn.text('Select & Send OTP').prop('disabled', false);
                }
            },
            error: function() {
                alert('System error sending OTP.');
                btn.text('Select & Send OTP').prop('disabled', false);
            }
        });
    });

    // 4. VERIFY WALLET OTP -> EXECUTE LINK
    $('#gcp-wallet-verify-otp-btn').on('click', function(e) {
        e.preventDefault();
        
        // Collect Code
        var code = '';
        $('#gcp-wallet-otp-inputs .gcp-otp-digit').each(function() {
            code += $(this).val();
        });

        if (code.length < 6) {
            $('#gcp-wallet-otp-error').text('Please enter the 6-digit code.').show();
            return;
        }

        var btn = $(this);
        var cardId = $('#gcp-wallet-confirm-btn').data('card-id'); // Grab ID from confirm btn
        var targetEmail = $(this).data('target-email');

        btn.text('Verifying...').prop('disabled', true);

        $.ajax({
            url: gcp_portal_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gcp_portal_verify_wallet_otp_and_add',
                nonce: gcp_portal_vars.nonce,
                otp_code: code,
                card_id: cardId,
                target_email: targetEmail
            },
            success: function(response) {
                if (response.success) {
                    // SUCCESS!
                    var data = response.data;
                    $('#gcp-wallet-success-link').attr('href', data.wallet_url);
                    $('.gcp-wallet-target-display').text(targetEmail);

                    if (data.is_expired) {
                        gcp_wallet_change_view('expired');
                    } else {
                        gcp_wallet_change_view('success');
                    }
                } else {
                    // Show error in the OTP modal
                    $('#gcp-wallet-otp-error').text(response.data).show();
                    btn.text('Submit').prop('disabled', false);
                }
            },
            error: function() {
                $('#gcp-wallet-otp-error').text('System error.').show();
                btn.text('Submit').prop('disabled', false);
            }
        });
    });
    
    // 5. RESEND OTP (Wallet View)
    $('#gcp-wallet-resend-otp').on('click', function(e){
        e.preventDefault();
        $(this).text('Sending...');
        $.ajax({
            url: gcp_portal_vars.ajax_url,
            type: 'POST',
            data: { action: 'gcp_portal_send_update_otp', nonce: gcp_portal_vars.nonce },
            success: function(response){
                alert(response.success ? 'Code resent!' : 'Failed to resend.');
                $('#gcp-wallet-resend-otp').text('Resend');
            }
        });
    });

    // NOTE: The .gcp-otp-digit input logic (auto-advance/paste) we added earlier 
    // targets ALL .gcp-otp-digit classes, so it will automatically work for these new inputs too.

});