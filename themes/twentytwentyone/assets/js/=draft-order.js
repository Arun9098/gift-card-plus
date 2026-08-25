jQuery(document).ready(function ($) {
    $('#customisation-save-btn').on('click', function () {
        let invalidRecipientCount = true;
        let fieldsValidFlag = true;
        let giftCardFlag = true;
        let firstInvalidCell = null;

        const invalidRecipients = document.getElementById("invalid-recipients-error-message");
        const errorMessageEl = document.getElementById("customisation-error-message");

        const recipientItemsExist = $('#recipient-table .gift-card-column').length;

        if (recipientItemsExist === 0) {
            invalidRecipientCount = false;

            if (invalidRecipients) {
                invalidRecipients.textContent = "Please add a recipient and complete the details for this order before proceeding.";
                invalidRecipients.style.display = "block";
                errorMessageEl.style.display = "none";
                errorMessageEl.textContent = '';
            }
        } else {
            if (invalidRecipients) invalidRecipients.style.display = "none";
        }

        // Validate input fields for each row
        $('#recipient-table tbody tr.editable-row').each(function () {
            const row = $(this);

            const firstNameInput = row.find('.recipient-first-name');
            const surnameInput = row.find('.recipient-surname');
            const emailInput = row.find('.recipient-email');
            const phoneInput = row.find('.recipient-phone');

            let rowValid = true;

            // Validate First Name (required)
            if (validateInputField(firstNameInput)) {
                rowValid = false;
                if (!firstInvalidCell) firstInvalidCell = firstNameInput;
            }

            // Surname is optional: validate only if filled
            if (surnameInput.val().trim() && validateInputField(surnameInput)) {
                rowValid = false;
                if (!firstInvalidCell) firstInvalidCell = surnameInput;
            }

            // Email / Phone logic
            const email = emailInput.val().trim();
            const phone = phoneInput.val().trim();

            emailInput.removeClass('invalid-field').siblings('.invalid-message').remove();
            phoneInput.removeClass('invalid-field').siblings('.invalid-message').remove();

            if (!email && !phone) {
                const msg = '<div class="invalid-message" style="color:red;font-size:12px;margin-top:5px;">Enter at least Email or Phone</div>';
                emailInput.addClass('invalid-field').after(msg);
                phoneInput.addClass('invalid-field').after(msg);
                rowValid = false;
                if (!firstInvalidCell) firstInvalidCell = emailInput;
            } else {
                if (email && validateInputField(emailInput)) {
                    rowValid = false;
                    if (!firstInvalidCell) firstInvalidCell = emailInput;
                }
                if (phone && validateInputField(phoneInput)) {
                    rowValid = false;
                    if (!firstInvalidCell) firstInvalidCell = phoneInput;
                }
            }

            if (!rowValid) fieldsValidFlag = false;
        });

        // Validate gift cards assigned per row
        $('#recipient-table .gift-card-column').each(function () {
            const giftCardItem = $(this).find('.gift-card-item');
            const $td = $(this).closest('td');

            $td.find('.invalid-message').remove();

            if (giftCardItem.length === 0) {
                giftCardFlag = false;
                $(this).addClass('invalid-field');
                $td.append('<div class="invalid-message" style="color: red; font-size: 12px; margin-top: 5px;">Please Select Gift Card</div>');

                if (!firstInvalidCell) firstInvalidCell = $(this);
            } else {
                $(this).removeClass('invalid-field');
            }
        });

        if (fieldsValidFlag && giftCardFlag && invalidRecipientCount) {
            if (errorMessageEl) errorMessageEl.style.display = "none";
            if (invalidRecipients) invalidRecipients.style.display = "none";

            // Prepare data for AJAX
            const recipients = [];

            $('#recipient-table tbody tr.editable-row').each(function () {
                const row = $(this);
                const recipient = {
                    first_name: row.find('.recipient-first-name').val().trim(),
                    surname: row.find('.recipient-surname').val().trim(),
                    email: row.find('.recipient-email').val().trim(),
                    phone: row.find('.recipient-phone').val().trim(),
                    gift_cards: []
                };

                row.find('.gift-card-item').each(function () {
                    const card = $(this);
                    recipient.gift_cards.push({
                        sku: card.data('sku'),
                        title: card.data('title'),
                        price: parseFloat(card.find('.gift-card-price').text().replace('$', '').trim()),
                        image: card.find('.gift-card-image').attr('src')
                    });
                });

                recipients.push(recipient);
            });

            $.ajax({
                url: draft_order_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'save_draft_order_with_recipients',
                    recipients: JSON.stringify(recipients),
                    // keep backend happy when this script is used standalone
                    current_step: '0',
                },
                success: function (response) {
                    if (response.success) {
                        alert('Draft order created successfully! Order ID: ' + response.data.order_id);
                        // window.location.href = response.data.redirect_url;
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function () {
                    alert('Unexpected error occurred.');
                }
            });

        } else {
            if (errorMessageEl && invalidRecipientCount) {
                errorMessageEl.textContent = "Please fill in all required fields correctly.";
                errorMessageEl.style.display = "block";
            } else if (errorMessageEl) {
                errorMessageEl.style.display = "none";
            }

            if (!firstInvalidCell && !invalidRecipientCount && invalidRecipients) {
                firstInvalidCell = $(invalidRecipients);
            }

            if (firstInvalidCell) {
                $('html, body').animate({
                    scrollTop: firstInvalidCell.offset().top - 100
                }, 500);
            }
        }
    });

    // Same function as next button
    function validateInputField($input) {
        const value = $input.val().trim();
        const type = $input.attr('type');
        const isEmail = type === 'email' || $input.hasClass('recipient-email');
        const isPhone = $input.hasClass('recipient-phone');

        $input.removeClass('invalid-field');
        $input.siblings('.invalid-message, .validation-error').remove();

        let message = '';

        if (!value) {
            message = 'This field is required.';
        } else if (isEmail && !/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/.test(value)) {
            message = 'Please enter a valid email address.';
        } else if (isPhone && !/^\+?[0-9\s\-]{6,15}$/.test(value)) {
            message = 'Please enter a valid phone number.';
        }

        if (message) {
            $input.addClass('invalid-field');
            $input.after('<div class="invalid-message" style="color:red;font-size:12px;margin-top:5px;">' + message + '</div>');
            return true;
        }

        return false;
    }
});
