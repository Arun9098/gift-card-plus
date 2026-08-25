jQuery(document).ready(function ($) {
    function updateSelectedCount() {
        let eventsCount = jQuery('input[name="events[]"]:checked').length;
        let hobbiesCount = jQuery('input[name="hobbies[]"]:checked').length;
        let total = eventsCount + hobbiesCount;

        jQuery('.selected-preferences').text('You’ve selected ' + total + ' preferences.: ');
    }

    // Trigger update when any checkbox changes
    jQuery('input[name="events[]"], input[name="hobbies[]"]').on('change', function () {
        updateSelectedCount();
    });

    // Initialize on page load
    updateSelectedCount();


    // Marketing emails: confirm when user unchecks
    jQuery('.marketing-email').on('change', function () {
        var $cb = jQuery(this);
        if (!$cb.is(':checked')) {
            if (!confirm('Are you sure you want to remove marketing emails?')) {
                $cb.prop('checked', true);
            }
        }
    });

    // SMS notifications: confirm when user unchecks
    jQuery('.sms-notifications').on('change', function () {
        var $cb = jQuery(this);
        if (!$cb.is(':checked')) {
            if (!confirm('Are you sure you want to remove SMS notifications? You might miss out on our offers and promotions.')) {
                $cb.prop('checked', true);
            }
        }
    });

    jQuery('#save-preferences').on('click', function (e) {
        e.preventDefault();

        let button = jQuery(this);
        let messageBox = jQuery('.preference-message');

        // Save original text
        let originalText = button.text();

        // Reset message
        messageBox.removeClass('success error').text('');

        // Disable button + add loader text
        button.prop('disabled', true).addClass('disabled').text('Saving...');

        const hobbies = [];
        jQuery('input[name="hobbies[]"]:checked').each(function () {
            hobbies.push(jQuery(this).val());
        });

        const events = [];
        jQuery('input[name="events[]"]:checked').each(function () {
            events.push(jQuery(this).val());
        });

        const marketing_email = jQuery('.marketing-email').is(':checked') ? 1 : 0;
        const marketing_sms = jQuery('.sms-notifications').is(':checked') ? 1 : 0;

        $.ajax({
            url: my_pref_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'save_user_preferences',
                hobbies: hobbies,
                events: events,
                marketing_email: marketing_email,
                marketing_sms: marketing_sms,
                security: my_pref_ajax.nonce
            },

            success: function (response) {
                if (response.success) {
                    messageBox
                        .addClass('success')
                        .text('Preferences saved successfully!');
                } else {
                    messageBox
                        .addClass('error')
                        .text('Something went wrong. Please try again.');
                }

                // Restore button
                button.prop('disabled', false)
                      .removeClass('disabled')
                      .text(originalText);
            },

            error: function () {
                messageBox
                    .addClass('error')
                    .text('Server error. Please try again later.');

                // Restore button
                button.prop('disabled', false)
                      .removeClass('disabled')
                      .text(originalText);
            }
        });
    });
});