jQuery(document).ready(function ($) {
    function checkClientBillingApproval(userId) {
        $.ajax({
            url: paymentAjax.ajax_url, // Provided by WordPress in admin
            method: 'POST',
            data: {
                action: 'check_approved_billing',
                user_id: userId
            },
            success: function (response) {
                console.log('response',response);
                if (response.success && response.data.approved == true) {
                    // console.log('1');
                    // console.log('2');
                    jQuery('#client-tab').removeClass('disabled');
                    // console.log('3');
                    jQuery('#prepaid-tab').addClass('disabled');
                } else {
                    // console.log('5');
                    jQuery('#client-tab').addClass('disabled');
                    // console.log('6');
                    jQuery('#prepaid-tab').removeClass('disabled');
                }
            },
            error: function () {
                // console.log('7');
                console.error('Error checking approved_billing status.');
                jQuery('#prepaid-tab').addClass('disabled');
            }
        });
    }

    // Initial check on page load
    const initialUserId = jQuery('#business-user-dropdown').val();
    if (initialUserId) {
        // console.log('initialUserId',initialUserId);
        checkClientBillingApproval(initialUserId);
    }

    // Re-check when dropdown changes
    jQuery('#business-user-dropdown').on('change', function () {
        const selectedUserId = jQuery(this).val();
        if (selectedUserId) {
        // console.log('selectedUserId',selectedUserId);
            checkClientBillingApproval(selectedUserId);
        }
    });
});