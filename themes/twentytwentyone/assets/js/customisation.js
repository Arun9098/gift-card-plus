document.addEventListener("DOMContentLoaded", function () {
    const nextBtn = document.getElementById("customisation-next-btn");
    const saveBtn = document.getElementById("customisation-save-btn");


    [nextBtn, saveBtn].forEach(button => {
        if (button) {
            button.addEventListener("click", function (e) {
                const action = e.currentTarget.getAttribute("data-action");
                const currentStep = e.currentTarget.getAttribute("data-step");
                const status = e.currentTarget.getAttribute("data-status");
                const order_id = e.currentTarget.getAttribute("data-order-id");
                const edit_order = parseInt(e.currentTarget.getAttribute("data-edit_order"));
                // console.log('action is:', action);
                let invalidRecipientCount = true;

                const invalidRecipients = document.getElementById("invalid-recipients-error-message");
                const errorMessageEl = document.getElementById("customisation-error-message");

                const recipientItemsExist = jQuery('#recipient-table .gift-card-column').length;

                if (recipientItemsExist === 0) {
                    invalidRecipientCount = false;

                    if (invalidRecipients) {
                        //console.log('Clickedd=====');
                        invalidRecipients.textContent = "Please add a recipient and complete the details for this order before proceeding.";
                        invalidRecipients.style.display = "block";
                        errorMessageEl.style.display = "none";
                        errorMessageEl.textContent = '';
                    }
                } else {
                    if (invalidRecipients) invalidRecipients.style.display = "none";
                }

                let allValid = true;
                if( edit_order <= 0 || !edit_order){
                    jQuery(".recipient-first-name, .recipient-surname, .recipient-email, .recipient-phone").each(function () {
                        const error = validateInputField(jQuery(this));
                        if (error) {
                            allValid = false;
                        }
                    });
                }

                fieldsValidFlag = allValid;
                giftCardFlag = true;
                let firstInvalidCell = null; // To track the first invalid cell for scrolling

                jQuery('#recipient-table .gift-card-column').each(function () {
                    const giftCardItem = jQuery(this).find('.gift-card-item');
                    const $td = jQuery(this).closest('td');
                
                    // Remove any existing error
                    $td.find('.invalid-message').remove();
                
                    if (giftCardItem.length === 0) {
                        // console.log('Invalid');
                        giftCardFlag = false;
                
                        // Highlight and show error message
                        jQuery(this).addClass('invalid-field');
                        $td.append('<div class="invalid-message" style="color: red; font-size: 12px; margin-top: 5px;">Please Select Gift Card</div>');
                
                        if (!firstInvalidCell) {
                            firstInvalidCell = jQuery(this);
                        }
                    } else {
                        // console.log('Valid');
                        jQuery(this).removeClass('invalid-field');
                    }
                });
    
                
                if (fieldsValidFlag && giftCardFlag && invalidRecipientCount) {

                    if (errorMessageEl) errorMessageEl.style.display = "none";
                    if (invalidRecipients) invalidRecipients.style.display = "none";

                    if (action === "save-draft") {
                        // console.log("Saving as draft...");
                        const btn = e.currentTarget;
                        if (btn && btn.id === 'customisation-save-btn') {
                            btn.disabled = true;
                            btn.setAttribute('aria-busy', 'true');
                            btn.classList.add('btn-disabled');
                        }

                        const nextStepBtn = document.querySelector('.customisation-next-btn');
                        if (nextStepBtn) {
                            nextStepBtn.disabled = true;
                            nextStepBtn.setAttribute('aria-busy', 'true');
                            nextStepBtn.classList.add('btn-disabled');
                        }
        
                        const recipients = [];
                        const businessDetails = {
                            business_id: jQuery('#business-user-dropdown').val(),
                            order_type: jQuery('#new-order-form-container').data('order_type'),
                            business_name: jQuery('#business-user-dropdown option:selected').text().trim(),
                            sender_name: jQuery('#sender-dropdown option:selected').text(),
                            sender_email: jQuery('#sender-dropdown option:selected').data('email'),
                            campaign: jQuery('#campaign-dropdown').val(),
                            order_name: jQuery('#order-name').val(),
                            po_number: jQuery('#related-po').val(),
                            additional_reference: jQuery('#additional-reference').val(),
                            client_reference: jQuery('#client-reference').val(),
                        };
                        // console.log('businessDetails : ',businessDetails);
                    
                        jQuery('#recipient-table tbody tr.editable-row').each(function () {
                            const row = jQuery(this);
                            const recipient = {
                                first_name: row.find('.recipient-first-name').val(),
                                surname: row.find('.recipient-surname').val(),
                                email: row.find('.recipient-email').val(),
                                phone: row.find('.recipient-phone').val(),
                                gift_cards: [],
                                gift_message: row.find('.gift-message').val(),
                                delivery_method: row.find('.delivery-method').val()
                            };
                    
                            row.find('.gift-card-item').each(function () {
                                const card = jQuery(this);
                                console.log(card);
                                recipient.gift_cards.push({
                                    sku: card.data('sku'),
                                    title: card.data('title'),
                                    price: parseFloat(card.find('.gift-card-price').text().replace('$', '')),
                                    image: card.find('.gift-card-image').attr('src')
                                });
                            });
                    
                            recipients.push(recipient);
                        });
                        console.log('customisation callling');
                    
                        jQuery.ajax({
                            url: draft_order_ajax.ajax_url,
                            method: 'POST',
                            data: {
                                action: 'save_draft_order_with_recipients',
                                nonce: draft_order_ajax.nonce,
                                recipients: JSON.stringify(recipients),
                                current_step: currentStep,
                                business_details: JSON.stringify(businessDetails),
                                sender_name: jQuery('#sender-name').val(),
                                sender_email: jQuery('#sender-email').val(),
                                status: status,
                                order_id: order_id,
                            },
                            success: function (response) {
                                const messageBox = document.getElementById("save-draft-message");
                                messageBox.classList.remove('success-message', 'error-message');
                                if (response.success) {
                                   
                                    if( response.data.is_update ){
                                        messageBox.textContent = 'Order #'+response.data.order_id+' updated successfully...';
                                    }else{
                                        messageBox.textContent = 'Order #'+response.data.order_id+'  created successfully...';                                        
                                    }
                                    messageBox.classList.add('success-message');
                                    messageBox.style.display = "block";
        
                                    const newOrderId = response.data.order_id;
                                    const ordrUpdate = response.data.is_update;
        
                                    btn.setAttribute('data-order-id', newOrderId);
                                    if (ordrUpdate === false) {
                                        btn.setAttribute('data-status', 'update');
                                    }
                                    ['create-order-save-btn', 'delivery-save-btn','place-order-btn'].forEach(id => {
                                        const el = document.getElementById(id);
                                        if (el) el.setAttribute('data-order-id', newOrderId);
                                        el.setAttribute('data-status', 'update');
                                    });
        
                                    setTimeout(() => {
                                        messageBox.classList.remove('success-message', 'error-message');
                                        messageBox.textContent = "";
                                        btn.disabled = false;
                                        btn.setAttribute('aria-busy', 'false');
                                        btn.classList.remove('btn-disabled');
                                        nextStepBtn.disabled = false;
                                        nextStepBtn.setAttribute('aria-busy', 'false');
                                        nextStepBtn.classList.remove('btn-disabled');
                                    }, 3000);
                                    // setTimeout(() => {
                                    //     var order_page_url = window.location.origin+'/order';
                                    //     window.location.href = order_page_url;
                                    // }, 3000);                        
                                } else {
                                    if (btn && btn.id === 'customisation-save-btn') {
                                        btn.disabled = false;
                                        btn.removeAttribute('aria-busy');
                                        btn.classList.remove('btn-disabled');
                                        nextStepBtn.disabled = false;
                                        nextStepBtn.removeAttribute('aria-busy');
                                        nextStepBtn.classList.remove('btn-disabled');
                                    }
                                    messageBox.textContent = 'Error: ' + response.data.message;
                                    messageBox.classList.add('error-message');
                                    messageBox.style.display = "block";
                                }
                            },
                            error: function () {
                                const messageBox = document.getElementById("save-draft-message");
                                messageBox.classList.remove('success-message', 'error-message');
                                messageBox.textContent = 'Unexpected error occurred.';
                                messageBox.classList.add('error-message');
                                messageBox.style.display = "block";
                                if (btn && btn.id === 'customisation-save-btn') {
                                    btn.disabled = false;
                                    btn.removeAttribute('aria-busy');
                                    btn.classList.remove('btn-disabled');
                                    nextStepBtn.disabled = false;
                                    nextStepBtn.removeAttribute('aria-busy');
                                    nextStepBtn.classList.remove('btn-disabled');
                                }
                            }
                        });
                    
                        return;
                    }

                    jQuery('.customisation-container').show();
                    // jQuery('#personalise-all').prop('checked', true).trigger('change');

                    document.querySelectorAll(".table-container, .gift-card-container, #save-and-next-btn").forEach(el => {
                        if (el) el.setAttribute("style", "display: none !important;");
                    });

                    const activeStep = document.querySelector(".step.active-step");
                    if (activeStep) activeStep.classList.remove("active-step");
                    const customizationStep = document.querySelector(".step-indicator .step:nth-child(2)");
                    if (customizationStep) customizationStep.classList.add("active-step");

                    customizationStep.classList.add("back-to-customisation");
                    const backButton = document.getElementById("back-to-order-form");
                    if (backButton) backButton.id = "back-to-recipient-form";

                    // setTimeout(() => {
                    // const personaliseAllCheckbox = document.getElementById("personalise-all");
                    // if (personaliseAllCheckbox) personaliseAllCheckbox.checked = true;
                    // }, 9000);

                    resetPreviewDetails();
                    loadGiftCardData();
                    jQuery('.owl-carousel').removeClass('owl-hidden');
                    // const personaliseAllCheckbox = document.getElementById("personalise-all");

                    // if (personaliseAllCheckbox && personaliseAllCheckbox.checked) {
                    //     // Hide all gift-card checkboxes
                    //     document.querySelectorAll(".gift-card-checkbox").forEach(el => {
                    //         el.style.display = "none";
                    //     });
                    
                    //     // Uncheck all gift-card-select checkboxes
                    //     document.querySelectorAll(".gift-card-select").forEach(cb => {
                    //         cb.checked = false;
                    //     });
                    // } else {
                    //     // Show gift-card checkboxes if personalise-all is unchecked
                    //     document.querySelectorAll(".gift-card-checkbox").forEach(el => {
                    //         el.style.display = "block";
                    //     });
                    // }
                    const urlParams = new URLSearchParams(window.location.search);

                    const personaliseAll = document.querySelector("#personalise-all");
                    const giftCardCheckboxes = document.querySelectorAll(".gift-card-select");
                    
                    if (!urlParams.has('order_id')) {
                        if (personaliseAll) {
                            console.log("Hello User");
                            personaliseAll.checked = true;
                        }
                    }

                    function toggleGiftCardCheckboxes() {
                        if (personaliseAll.checked) {
                            giftCardCheckboxes.forEach(checkbox => {
                                checkbox.checked = true;   // check
                                checkbox.style.display = "none"; // hide
                            });
                        } else {
                            console.log('inside else  ');
                            jQuery('.preview-img-gift-card').hide().empty();
                            giftCardCheckboxes.forEach(checkbox => {
                                checkbox.style.display = "inline-block"; // show
                            });
                        }
                    }
                    
                    // Run once on page load
                    toggleGiftCardCheckboxes();
                    
                    // Listen for changes
                    personaliseAll.addEventListener("change", toggleGiftCardCheckboxes);
                    setTimeout(toggleGiftCardCheckboxes, 500);
                    
                    

                } else {
                    if (errorMessageEl && invalidRecipientCount) {
                        errorMessageEl.textContent = "Please fill in all required fields correctly.";
                        errorMessageEl.style.display = "block";
                    } else if (errorMessageEl) {
                        errorMessageEl.style.display = "none";
                    }


                    if (!firstInvalidCell && !invalidRecipientCount && invalidRecipients) {
                        firstInvalidCell = jQuery(invalidRecipients);
                    }

                    if (firstInvalidCell) {
                        jQuery('html, body').animate({
                            scrollTop: firstInvalidCell.offset().top - 100
                        }, 500);
                    }
                }
            });

        }
    });

    jQuery(document).on("click", "#back-to-recipient-form, .back-to-recipient-form", function ($) {
        jQuery('#multi-step-form').removeClass('d-none');
        jQuery('#new-order-form').hide();

        jQuery('#multi-step-form').addClass('full-width');
        jQuery('#page-spacer-top').hide();
        jQuery('#back-to-order-summary').hide();

        var type = jQuery(this).attr('data-type');
        jQuery('#new-order-form').hide();
        if (type == 'bulk') {
            if (jQuery('#multi-step-form.manual-order').hasClass('d-none')) {
            } else {
                jQuery('#multi-step-form.manual-order').addClass('d-none');
            }
            jQuery('#multi-step-form-bulk').removeClass('d-none');

            jQuery('#multi-step-form-bulk #display-order-name').text(jQuery('#order-name').val());
            // jQuery('#multi-step-form-bulk #display-order-id').text(jQuery('#order-id').val());
            jQuery('#multi-step-form-bulk #display-client-reference').text(jQuery('#client-reference').val());
            jQuery('#multi-step-form-bulk #display-sender').text(jQuery('#sender-dropdown').val());

        } else {
            if (jQuery('#multi-step-form-bulk').hasClass('d-none')) {
            } else {
                jQuery('#multi-step-form-bulk').addClass('d-none');
            }
            jQuery('#multi-step-form.manual-order').removeClass('d-none');
        }

        jQuery('#delivery-method-container').hide();
        jQuery('.customisation-container').hide();
        jQuery('#order-summary-container').hide();
        jQuery('#back-to-delivery-step').hide();
        jQuery('#payment-confirmation-container').hide();

        //jQuery('.step.back-to-recipient-form').removeClass('back-to-recipient-form');
        jQuery('.step.back-to-customisation').removeClass('back-to-customisation');
        jQuery('.step.back-to-delivery-step').removeClass('back-to-delivery-step');
        jQuery('.step.back-to-order-summary').removeClass('back-to-order-summary');

        
        jQuery(".table-container, .gift-card-container, #save-and-next-btn").each(function () {
            jQuery(this).removeAttr("style");
        });
        jQuery('.change__back_status').attr("id", "back-to-order-form").show();
        const activeStep = document.querySelector(".step.active-step");
        if (activeStep) {
            activeStep.classList.remove("active-step");
        }

        // Add active-step back to the Customization step (2nd step)
        const customizationStep = document.querySelector(".step-indicator .step:nth-child(1)");
        if (customizationStep) {
            customizationStep.classList.add("active-step");
        }
    });


    // jQuery(document).on("click", "#back-to-customisation", function () {
    //  jQuery('#multi-step-form').removeClass('d-none');
    //  jQuery('#new-order-form').hide();
    //  jQuery('.delivery-method-container').hide();
    //  jQuery('.customisation-container').show();
    //  jQuery(".customisation-container").each(function () {
    //      jQuery(this).removeAttr("style");
    //  });

    //  jQuery(this).attr("id", "back-to-recipient-form");

    //  // Remove active-step from the current step
    //  const activeStep = document.querySelector(".step.active-step");
    //  if (activeStep) {
    //      activeStep.classList.remove("active-step");
    //  }

    //  // Add active-step back to the Customization step (2nd step)
    //  const customizationStep = document.querySelector(".step-indicator .step:nth-child(2)");
    //  if (customizationStep) {
    //      customizationStep.classList.add("active-step");
    //  }
    // });

    document.getElementById("next-slide")?.addEventListener("click", function () {
        document.querySelector(".gift-card-slider").scrollBy({ left: 220, behavior: "smooth" });
    });

    document.getElementById("prev-slide")?.addEventListener("click", function () {
        document.querySelector(".gift-card-slider").scrollBy({ left: -220, behavior: "smooth" });
    });
    function checkGiftCardPersonalisation(sku, button) {
        jQuery.ajax({
            url: customisationData.ajaxUrl || customisationData.ajax_url,
            type: "POST",
            data: {
                action: "check_image_personalisation",
                sku: sku,
                security: customisationData.nonces.custom,
            },
            success: function (response) {

                if (response.success) {
                    if (response.data.is_checked) { // Check if the ACF field is checked (Yes)
                        jQuery(button).prop("disabled", false).css({
                            "background-color": "#000", // Enable with Red color
                            "cursor": "pointer"
                        });
                    } else {
                        jQuery(button).prop("disabled", true).css({
                            "background-color": "#6C758F", // Disabled color
                            "cursor": "not-allowed"
                        });
                    }
                }
            },
            error: function () {
                console.error("AJAX request failed.");
            },
        });
    }


    // customization.js
    document.addEventListener('bulkDataLoaded', function (event) {
        const data = event.detail.rows;
        const formData = event.detail.form_data;
        console.log('WWWWWWWWWWWWWWHHHHHHHHY',data);
        // console.log('WHY>?',formData);
        loadGiftCardData(data,formData);
        // Set the first product as selected by default for bulk upload
        if (data && data.length > 0) {
            // Set the first product's message in the editor
            const firstMessage = data[0].message || '';
            if (typeof tinyMCE !== "undefined" && tinyMCE.get("email_message_editor")) {
                tinyMCE.get("email_message_editor").setContent(firstMessage);
            } else {
                document.querySelector("#email_message_editor").value = firstMessage;
            }
        }
    });

    function loadGiftCardData(data = null,formData = null) {
        let giftCardData = [];
        let uniqueGiftCardId = 1;

       
        if (data) {
            if(formData){
                activationExpiryType = formData.activation_expiry_type ?? "default";
                activationExpiryDate = formData.activation_expiry_date ?? "";
                activationExpiryDuration = formData.activation_expiry_duration ?? "";
                activationExpiryUnit = formData.activation_expiry_unit ?? "";
                senderDetails = formData.sender_details ?? "";
                applyPersonalisation = formData.apply_personalisation ?? "";
                brandThumbnailUrl = formData.gift_card_image ?? "";
            }
            // Process bulk data from the event
            data.forEach((row) => {
                // console.log(row)
                const firstName = row.first_name ?? "";
                const clientReference = row.client_reference ?? "";
                const originalOrderDate = row.original_o_date ?? "";
                const recipientId = row.recipient_id ?? "";
                const quantity = row.quantity ?? "";
                const poNumber = row.po_number ?? "";
                const personalisation = row.personalisation ?? "";
                const scheduleDatetime = row.schedule_datetime ?? "";
                const surname = row.surname ?? "";
                const email = row.email ?? "";
                const sku = row.sku ?? "";
                const priceText = row.price ?? "";
                const imageSrc = row.image ?? "";
                const message = row.message ?? "";
                const title = row.name ?? "";
                const subject = row.subject ?? "";
                const textAnimation = row.textAnimation ?? "";
                const text_message = row.text_message ?? "";
                const emailAnimation = row.emailAnimation ?? "";
                const deliveryMethod = row.delivery_method ?? "Email";
                const brands = row.brands ?? "";
                const phone = row.phone ?? "";
                

                if (email) {
                    giftCardData.push({
                        id: uniqueGiftCardId++,
                        firstName,
                        clientReference,
                        originalOrderDate,
                        recipientId,
                        quantity,
                        poNumber,
                        personalisation,
                        scheduleDatetime,
                        surname,
                        email,
                        sku,
                        price: priceText,
                        image: imageSrc,
                        message,
                        title,
                        subject,
                        brands,
                        textAnimation,
                        text_message,
                        emailAnimation,
                        deliveryMethod,
                        phone,
                        activationExpiryType,
                        activationExpiryDate,
                        activationExpiryDuration,
                        activationExpiryUnit,
                        senderDetails,
                        applyPersonalisation,
                        brandThumbnailUrl,
                    });
                }
            });

        } else {
            // Process manual order data

            setTimeout(() => {
                document.querySelectorAll(".gift-card-checkbox input").forEach((checkbox) => {
                    checkbox.style.display = "none";
                });
                // console.log('im working befor it');
            }, 200);
            
            jQuery("#recipient-table tbody tr").each(function () {
                const $row = jQuery(this);
                const firstName = $row.find(".recipient-first-name").val()?.trim() || "";
                const surname = $row.find(".recipient-surname").val()?.trim() || "";
                const email = $row.find(".recipient-email").val()?.trim() || "";
                const phone = $row.find(".recipient-phone").val()?.trim() || "";

                // console.log('phone....',phone);

                $row.find(".gift-card-column .gift-card-item").each(function () {
                    const $item = jQuery(this);
                    const imageSrc = $item.find(".gift-card-image").attr("src");
                    const priceText = $item.find(".gift-card-price").text().trim();
                    const sku = $item.data("sku");
                    const message = $item.data('message');
                    const denomintion = $item.data('denomination');
                    const discount = $item.data('discount');
                    const subject = $item.data('subject');
                    const brands = $item.data('brands');
                    const personalisation = $item.data('personalisation');
                    const textAnimation = $item.data('text-animation');
                    const emailAnimation = $item.data('email-animation');
                    const text_message = $item.data('text_message');
                    const personalised = $item.data('personalised');
                    const title = $item.data("title"); // get the product title
                    const activationExpiryType = $item.data("activationExpiryType"); // get the product title
                    const activationExpiryDate = $item.data("activationExpiryDate"); // get the product title
                    const activationExpiryDuration = $item.data("activationExpiryDuration"); // get the product title
                    const activationExpiryUnit = $item.data("activationExpiryUnit"); // get the product title
                    const senderDetails = $item.data("senderDetails");
                    const applyPersonalisation = $item.data("applyPersonalisation"); // get the product title
                    const brandThumbnailUrl = $item.data("brandThumbnailUrl"); // get the product title

                    giftCardData.push({
                        id: uniqueGiftCardId++,
                        firstName,
                        surname,
                        email,
                        image: imageSrc,
                        price: priceText,
                        phone: phone,
                        sku: sku,
                        denomintion: denomintion,
                        title: title,
                        message: message,
                        discount: discount,
                        subject: subject,
                        brands: brands,
                        personalisation,
                        textAnimation: textAnimation,
                        emailAnimation: emailAnimation,
                        text_message: text_message,
                        personalised,
                        deliveryMethod: "",
                        activationExpiryType,
                        activationExpiryDate,
                        activationExpiryDuration,
                        activationExpiryUnit,
                        senderDetails,
                        applyPersonalisation,
                        brandThumbnailUrl
                    });
                });
            });
        }
        const isBulkUpload = !!data;

        // console.log('giftCardData....',giftCardData);
        giftCardData.forEach(item => {
            // console.log('message....', item.message);
            // console.log('subject....', item.subject);
        });
        

        // Add/remove bulk upload mode class
        const $slider = jQuery(".gift-card-slider");
        if (isBulkUpload) {
            // console.log('isBulkUpload....',isBulkUpload);
            $slider.addClass("bulk-upload-mode");
        } else {
            $slider.removeClass("bulk-upload-mode");
        }

        $slider.empty();

        // Create slides

        giftCardData.forEach((card) => {
            // console.log('--------',card.personalisation);
            // console.log('XXXXXXXXXXXX card:', card);
            // console.log('--------',giftCardData.length);
           
            const radioOrCheckbox = isBulkUpload ?
                `<input type="radio" name="gift-card-selection" id="gift-card-${card.id}" class="gift-card-select" ${card.id === 1 ? 'checked' : ''}>` :
                `<input type="checkbox" name="gift-card-selection" id="gift-card-${card.id}" class="gift-card-select" ${card.personalised == 1 ? 'checked' : ''}>`;


                // if(card.personalisation === 'No'){
                //     console.log('-----------------');
                // } else {
                //     console.log('XXXXXXXXXXXX');
                // }
                let subjectAttr = '';
                
                let messageAttr = '';
                
                // Check if the URL contains order_id
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.has('order_id')) {
                    // Add condition for personalisation
                    if (card.personalisation && card.personalisation.toLowerCase() !== "no") {
                        // console.log('XXXXXXXXXXXX');
                        subjectAttr = `data-subject="${card.subject}"`;
                    } else {
                        // console.log('YYYYYYYYYYY');
                        subjectAttr = `data-subject=""`;
                    }
                    
                    if (card.personalisation && card.personalisation.toLowerCase() !== "no") {
                        messageAttr = `data-message="${card.message}"`;
                    } else {
                        messageAttr = `data-message=""`;
                    }
                } else {
                    console.log('in else...');
                     // Add condition for personalisation
                    //  if (card.personalisation && card.personalisation.toLowerCase() !== "no") {
                        //  console.log('XXXXXXXXXXXX else ifff');
                         subjectAttr = `data-subject="${card.subject}"`;
                    //  }
                     
                    //  if (card.personalisation && card.personalisation.toLowerCase() !== "no") {
                         messageAttr = `data-message="${card.message}"`;
                    //  }
                }
                // console.log('card.subject..',subjectAttr);
                // console.log('card.message..',messageAttr);
               
                $slider.append(`
                <div class="gift-card-slide item" 
                    data-id="${card.id}" 
                    data-sku="${card.sku}"
                    data-clientReference="${card.clientReference}"
                    data-first-name="${card.firstName}" 
                    data-surname="${card.surname}"
                    data-email="${card.email}"
                    data-denomination="${card.denomintion}"
                    data-phone="${card.phone}"
                    data-discount="${card.discount}"
                    ${messageAttr}
                    ${subjectAttr}
                    data-email-animation="${card.emailAnimation}"
                    data-text-animation="${card.textAnimation}"
                    data-text-message="${card.text_message}"
                    data-personalised="${card.personalised}"
                    data-name="${card.title}"
                    data-delivery-method="${card.deliveryMethod}"
                    data-activation-expiry-type="${card.activationExpiryType}"
                    data-activation-expiry-date="${card.activationExpiryDate}"
                    data-activation-expiry-duration="${card.activationExpiryDuration}"
                    data-activation-expiry-unit="${card.activationExpiryUnit}"
                    data-sender-details="${card.senderDetails}"
                    data-personalisation="${card.personalisation}"
                    data-apply-personalisation="${card.applyPersonalisation}"
                    data-brands="${card.brands}"
                    data-brand-thumbnail-url="${card.brandThumbnailUrl}">
                    <label class="gift-card-checkbox">
                        ${radioOrCheckbox}
                        <span class="custom-${isBulkUpload ? 'radio' : 'checkbox'}"></span>
                    </label>
                <img src="${card.image}" alt="Gift Card" class="gift-card-img">
                    <p class="recipient-name">${card.firstName} ${card.surname}</p>
                    <p class="gift-card-price"> ${card.price.includes('$') ? card.price : '$' + card.price} </p>
                </div>
            `);
        });
        // setTimeout(() => {
        //     $owlSlider = jQuery('.gift-card-slider');
        //     $owlSlider.trigger('refresh.owl.carousel');

        //     console.log('Added----');
        // }, 3000);

        if (giftCardData.length === 0) {
            $slider.append("<p>No gift cards found.</p>");
        } else {

            if (!document.querySelector('link[href*="owl.carousel.min.css"]')) {
                var owlStylesheet = document.createElement('link');
                owlStylesheet.rel = 'stylesheet';
                owlStylesheet.type = 'text/css';
                owlStylesheet.href = 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css';
                document.head.appendChild(owlStylesheet);
            }
            
            if (!window.jQuery.fn.owlCarousel) {
                var owlScript = document.createElement('script');
                owlScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js';
                owlScript.onload = initCarousel; // call initCarousel once loaded
                document.head.appendChild(owlScript);
            } else {
                initCarousel(); // Already loaded
            }
            
            function initCarousel() {
                if ($slider.hasClass('owl-loaded')) {
                    $slider.trigger('destroy.owl.carousel');
                    $slider.removeClass('owl-loaded owl-carousel');
                    $slider.find('.owl-stage-outer').children().unwrap();
                    $slider.removeData();
                }
            
                $slider.addClass('owl-carousel').owlCarousel({
                    autoWidth: true,
                    margin: 40,
                    nav: true,
                    dots: false,
                    loop: false,
                    navText: [
                        '<i class="fa fa-chevron-left"></i>',
                        '<i class="fa fa-chevron-right"></i>'
                    ]
                });
            }
            

        }
        // Check if the URL contains order_id
        // const urlParams = new URLSearchParams(window.location.search);
        // if (!urlParams.has('order_id')) {
            // Handle message synchronization
        function getEditorContent() {
            if (typeof tinyMCE !== "undefined" && tinyMCE.get("email_message_editor")) {
                return tinyMCE.get("email_message_editor").getContent().trim();
            } else {
                return jQuery("#email_message_editor").val().trim();
            }
        }
    
        function setEditorContent(content) {
            if (typeof tinyMCE !== "undefined" && tinyMCE.get("email_message_editor")) {
                tinyMCE.get("email_message_editor").setContent(content || '');
            } else {
                jQuery("#email_message_editor").val(content || '');
            }
        }
    
        function updateSlidesWithEditorMessage() {
            // console.log('this calllll.....');
            const applyToAll = jQuery("#personalise-all").is(":checked");
            const messageApplyToAll = jQuery("#apply-message-checkbox").is(":checked");
    
            const currentMessage = getEditorContent();
            const currentSubject = jQuery(".customise-email-subject-input").val().trim();
            const currentTextMessage = jQuery("#text-message-input").val().trim();
            const emailAnimationUrl = jQuery("#selected-email-animation-preview img").attr("src") || '';
            const textAnimationUrl = jQuery("#selected-text-animation-preview img").attr("src") || '';        
    
            const slides = (applyToAll || messageApplyToAll)
                ? jQuery(".gift-card-slide")
                : jQuery(".gift-card-slide .gift-card-select:checked").closest(".gift-card-slide");
    
            if (slides.length === 0) return;
    
            slides.each(function () {
                jQuery(this).attr("data-message", currentMessage);
                jQuery(this).attr("data-subject", currentSubject);
                jQuery(this).attr("data-text-message", currentTextMessage);
                if(applyToAll){
                    if (emailAnimationUrl) {
                        jQuery(this).attr("data-email-animation", emailAnimationUrl);
                    }
                    if (textAnimationUrl) {
                        jQuery(this).attr("data-text-animation", textAnimationUrl);
                    }
                }else{
                    jQuery(this).attr("data-email-animation", emailAnimationUrl);
                    jQuery(this).attr("data-text-animation", textAnimationUrl);    
                }
                
            });
            let test = jQuery('#data-text-animation').val();
            console.log('this Slide.....',test);
            console.log('this textAnimationUrl.....',textAnimationUrl);
        }


        // ✅ Observe animation preview changes
        const emailPreviewObserver = new MutationObserver(() => {
            console.log('111111');
            updateSlidesWithEditorMessage();
        });
        emailPreviewObserver.observe(document.getElementById('selected-email-animation-preview'), {
            childList: true,
            subtree: true
        });

        const textPreviewObserver = new MutationObserver(() => {
            updateSlidesWithEditorMessage();
        });
        textPreviewObserver.observe(document.getElementById('selected-text-animation-preview'), {
            childList: true,
            subtree: true
        });
        
        function updateEditorWithSelectedMessages() {
            const selectedSlides = jQuery(".gift-card-slide .gift-card-select:checked").closest(".gift-card-slide");
        
            if (selectedSlides.length === 0) {
                // ✅ No selection → clear all fields
                setEditorContent('');
                jQuery(".customise-email-subject-input").val('');
                jQuery("#text-message-input").val('');
                jQuery('#selected-email-animation-preview').empty();
                jQuery('#selected-text-animation-preview').empty();
                return;
            }
        
            // ✅ Always take values from the first selected card
            const firstSlide = selectedSlides.first();
        
            let firstMessage = firstSlide.attr("data-message") || '';
            let firstSubject = firstSlide.attr("data-subject") || '';
            let firstTextMessage = firstSlide.attr("data-text-message") || '';
            let emailAnimationUrl = firstSlide.attr("data-email-animation") || '';
            let textAnimationUrl = firstSlide.attr("data-text-animation") || '';
        
            const hasAnyValue = firstMessage || firstSubject || firstTextMessage || emailAnimationUrl || textAnimationUrl;
        
            if (hasAnyValue) {
                // ✅ Prefill editor and inputs with first card’s data
                setEditorContent(firstMessage);
                jQuery(".customise-email-subject-input").val(firstSubject);
                jQuery("#text-message-input").val(firstTextMessage);
        
                if (emailAnimationUrl) {
                    jQuery('#selected-email-animation-preview').html(`<img src="${emailAnimationUrl}" alt="email_animation" />`);
                } else {
                    jQuery('#selected-email-animation-preview').empty();
                }
        
                if (textAnimationUrl) {
                    jQuery('#selected-text-animation-preview').html(`<img src="${textAnimationUrl}" alt="text_animation" />`);
                } else {
                    jQuery('#selected-text-animation-preview').empty();
                }
            } else {
                // ✅ First card has no data → clear editor and previews
                setEditorContent('');
                jQuery(".customise-email-subject-input").val('');
                jQuery("#text-message-input").val('');
                jQuery('#selected-email-animation-preview').empty();
                jQuery('#selected-text-animation-preview').empty();
            }
        
            // ✅ Apply first card’s values (or blanks) to all selected cards
            selectedSlides.each(function (index) {
                if (index === 0) return; // skip first
                jQuery(this).attr("data-message", firstMessage);
                jQuery(this).attr("data-subject", firstSubject);
                jQuery(this).attr("data-text-message", firstTextMessage);
                jQuery(this).attr("data-email-animation", emailAnimationUrl);
                jQuery(this).attr("data-text-animation", textAnimationUrl);
            });
        }
    
        function handleSlideSelection() {
            const applyToAll = jQuery("#personalise-all").is(":checked");
            const messageApplyToAll = jQuery("#apply-message-checkbox").is(":checked");
    
            if (applyToAll || messageApplyToAll) return;
    
            updateEditorWithSelectedMessages();
        }
    
        // Handle "personalise-all" checkbox
        // Handle "personalise-all" checkbox
        jQuery("#personalise-all").on("change", function () {
            const isChecked = jQuery(this).is(":checked");

            // Show/hide individual checkboxes
            jQuery(".gift-card-checkbox input").each(function () {
                this.checked = false; // uncheck when personalise-all is active
                this.style.display = isChecked ? "none" : "inline-block";
            });

            if (isChecked) {
                updateSlidesWithEditorMessage();

                // ✅ Check if all slides have same message/subject/text-message
                const slides = jQuery(".gift-card-slide");
                if (slides.length > 0) {
                    const firstMessage = slides.first().attr("data-message") || '';
                    const firstSubject = slides.first().attr("data-subject") || '';
                    const firstTextMessage = slides.first().attr("data-text-message") || '';

                    const allSame = slides.toArray().every(slide =>
                        jQuery(slide).attr("data-message") === firstMessage &&
                        jQuery(slide).attr("data-subject") === firstSubject &&
                        jQuery(slide).attr("data-text-message") === firstTextMessage
                    );

                    if (allSame) {
                        // ✅ Prefill editor and inputs
                        setEditorContent(firstMessage);
                        jQuery(".customise-email-subject-input").val(firstSubject);
                        jQuery("#text-message-input").val(firstTextMessage);
                    }
                }
            }

            handleSlideSelection();
        });

        // Handle "apply-message-checkbox"
        jQuery("#apply-message-checkbox").on("change", function () {
            const isChecked = jQuery(this).is(":checked");
    
            if (isChecked) {
                updateSlidesWithEditorMessage();
            }
    
            handleSlideSelection();
        });
    
        // Subject field change updates selected slides
        jQuery(".customise-email-subject-input").on("keyup change", function () {
            updateSlidesWithEditorMessage();
        });
        jQuery("#text-message-input").on("keyup change paste", function() {
            // Validate 160 character limit with dynamic content
            validateTextMessageLength();
            updateSlidesWithEditorMessage();
        });

    
        // Message editor change updates selected slides
        if (typeof tinyMCE !== "undefined" && tinyMCE.get("email_message_editor")) {
            tinyMCE.get("email_message_editor").on('keyup change', function () {
                updateSlidesWithEditorMessage();
            });
        } else {
            jQuery("#email_message_editor").on("keyup change", function () {
                updateSlidesWithEditorMessage();
            });
        }
    
        // Handle checkbox (individual gift card selection)
        jQuery(document).on("change", ".gift-card-slide .gift-card-select", function () {
            handleSlideSelection();
        });
    
        // On initial page load, prefill message & subject if any are selected
        updateEditorWithSelectedMessages();

        // Handle selection changes
        $slider.on("change", ".gift-card-select", function () {
            updateEditorWithSelectedMessages();
        });

        // Handle editor changes
        const editorChangeHandler = function () {
            updateSlidesWithEditorMessage();
        };

        if (typeof tinyMCE !== "undefined" && tinyMCE.get("email_message_editor")) {
            tinyMCE.get("email_message_editor").on('change', editorChangeHandler);
            tinyMCE.get("email_message_editor").on('keyup', editorChangeHandler);
        } else {
            document.querySelector("#email_message_editor")?.addEventListener('input', editorChangeHandler);
        }

        updatePreviewDetails();
        // }
    }

    
    const personaliseAllCheckbox = document.getElementById("personalise-all");
    const personaliseMesageAllCheckbox = document.getElementById("apply-message-checkbox");
    
    if (personaliseAllCheckbox || personaliseMesageAllCheckbox) {
        const allGiftCardCheckboxes = document.querySelectorAll(".gift-card-checkbox input");
        const giftCardSlides = document.querySelectorAll(".gift-card-slide");
        const previewContainer = document.querySelector(".preview-img-gift-card");
    
        // Listen to change on both checkboxes
        personaliseAllCheckbox.addEventListener("change", () => {
            const isAnyChecked = personaliseAllCheckbox.checked;
            // console.log('isAnyChecked', isAnyChecked);
        
            // Get all checkboxes at the time of change
            const allGiftCardCheckboxes = document.querySelectorAll(".gift-card-checkbox input");
        
            allGiftCardCheckboxes.forEach(input => {
                input.style.display = isAnyChecked ? "none" : "inline-block";
            });
        
            updatePreviewDetails();
        });
        
    
        document.addEventListener("change", function (event) {
            if (event.target.classList.contains("gift-card-select")) {
                updatePreviewDetails();
            }
        });
    
        function updatePreviewDetails() {
            const isAnyChecked = (personaliseAllCheckbox?.checked || false) || (personaliseMesageAllCheckbox?.checked || false);
            previewContainer.innerHTML = "";
    
            if (!isAnyChecked) {
                const checkedCheckboxes = document.querySelectorAll(".gift-card-checkbox input:checked");
    
                if (checkedCheckboxes.length > 0) {
                    previewContainer.style.display = "flex";
    
                    checkedCheckboxes.forEach(checkbox => {
                        const slide = checkbox.closest(".gift-card-slide");
                        const giftCardId = slide.dataset.id;
                        const sku = slide.dataset.sku;
                        const firstName = slide.dataset.firstName;
                        const surname = slide.dataset.surname;
                        const recipientFullName = `${firstName} ${surname}`.trim();
                        const cardImage = slide.querySelector(".gift-card-img");
                        const cardPrice = slide.querySelector(".gift-card-price").textContent.trim();
    
                        const previewItem = document.createElement("div");
                        previewItem.className = "gift-card-preview-item";
                        previewItem.dataset.id = giftCardId;
                        previewItem.innerHTML = `
                            <p class="gift-card-recipient">${recipientFullName}</p>
                            <div class="gift-card-image-container">
                                <img src="${cardImage.src}" class="gift-card-preview-img" style="width: 200px; border-radius: 5px;">
                                <button class="remove-gift-card-personalise" data-id="${giftCardId}">&times; <span class="tooltip-text">Sorry this card is not available for image personalisation</span></button>
                            </div>
                            <p class="card-price">${cardPrice}</p>
                        `;
    
                        const removeButton = previewItem.querySelector(".remove-gift-card-personalise");
                        checkGiftCardPersonalisation(sku, removeButton);
    
                        removeButton.addEventListener("click", function () {
                            const idToRemove = this.dataset.id;
    
                            previewItem.innerHTML = `
                                <div class="gift-card-preview">
                                    <p class="gift-card-recipient">${recipientFullName}</p>
                                    <div class="gift-card-image-container">
                                        <label for="upload-${idToRemove}" class="upload-label">
                                            <div class="custom-file-upload">
                                                <svg width="16" height="20" ...>...</svg>
                                                <span>Upload file</span>
                                                or <span class="lin-text">Create new design</span>
                                            </div>
                                        </label>
                                        <input type="file" id="upload-${idToRemove}" class="gift-card-upload" accept="image/*">
                                        <div class="image-preview gift-card-preview-container" id="preview-${idToRemove}" style="margin-top: 10px;">
                                            <img src="${customisationData.styleUri}/assets/images/pngtree-file-upload-icon-image.png" class="gift-card-preview-img gift-card-img-default" style="max-width: 100px; border-radius: 5px;">
                                        </div>
                                    </div>
                                    <p class="card-price">${cardPrice}</p>
                                </div>
                            `;
    
                            const fileInput = previewItem.querySelector(`#upload-${idToRemove}`);
                            const previewDiv = previewItem.querySelector(`#preview-${idToRemove}`);
                            const uploadLabel = previewItem.querySelector(`label[for="upload-${idToRemove}"]`);
    
                            fileInput.addEventListener("change", function () {
                                if (fileInput.files.length > 0) {
                                    const file = fileInput.files[0];
                                    const formData = new FormData();
                                    formData.append("file", file);
                                    formData.append("action", "upload_gift_card_image");
                                    formData.append("_wpnonce", customisationData.nonces.giftCard);
    
                                    fetch(customisationData.ajaxUrl, {
                                        method: "POST",
                                        body: formData,
                                    })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                const uploadedImageUrl = data.data.url;
                                                fileInput.style.display = "none";
                                                uploadLabel.style.display = "none";
    
                                                previewDiv.innerHTML = `
                                                    <div class="gift-card-preview-container">
                                                        <img src="${uploadedImageUrl}" class="gift-card-preview-img gift-card-img-uploaded" style="max-width: 100px; border-radius: 5px;">
                                                        <button class="remove-gift-card-personalise" style="margin-left: 10px;">&times;</button>
                                                    </div>
                                                `;
    
                                                const sliderImage = document.querySelector(`.gift-card-slide[data-id="${idToRemove}"] .gift-card-img`);
                                                if (sliderImage) {
                                                    sliderImage.src = uploadedImageUrl;
                                                }
    
                                                document.getElementById(`gift-card-${idToRemove}`).checked = true;
                                                localStorage.setItem(`gift-card-${idToRemove}`, uploadedImageUrl);
    
                                                previewDiv.querySelector(".remove-gift-card-personalise").addEventListener("click", function () {
                                                    fileInput.value = "";
                                                    previewDiv.innerHTML = `<img src="${customisationData.styleUri}/assets/images/pngtree-file-upload-icon-image.png" class="gift-card-preview-img gift-card-img-default" style="max-width: 100px; border-radius: 5px;">`;
                                                    fileInput.style.display = "block";
                                                    uploadLabel.style.display = "block";
                                                    if (sliderImage) {
                                                        sliderImage.src = "${customisationData.styleUri}/assets/images/pngtree-file-upload-icon-image.png";
                                                    }
                                                    localStorage.removeItem(`gift-card-${idToRemove}`);
                                                });
                                            } else {
                                                alert("Failed to upload image. Please try again.");
                                            }
                                        })
                                        .catch(error => console.error("Upload error:", error));
                                }
                            });
                        });
    
                        previewContainer.appendChild(previewItem);
                    });
                } else {
                    resetPreviewDetails();
                }
            } else {
                resetPreviewDetails();
            }
        }
    
        function resetPreviewDetails() {
            previewContainer.innerHTML = `
                <img src="${customisationData.styleUri}/assets/images/pngtree-file-upload-icon-image.png" alt="Gift Card" class="w-32 h-20 rounded-md">
                <p class="card-price"></p>
            `;
            previewContainer.style.display = "none";
        }
    
        giftCardSlides.forEach(slide => {
            const giftCardId = slide.getAttribute("data-id");
            const savedImage = localStorage.getItem(`gift-card-${giftCardId}`);
            if (savedImage) {
                const imgElement = slide.querySelector(".gift-card-img");
                imgElement.src = savedImage;
                document.getElementById(`gift-card-${giftCardId}`).checked = true;
            }
        });
    
        updatePreviewDetails(); // Initial load
    }
    



    //Add animation code Start here ------

    const modal = document.getElementById('animation-modal');
    const overlay = document.getElementById('animation-modal-overlay');

    if (modal && overlay) {
        const animationItems = document.querySelectorAll('#animation-selection-modal li');

        let currentPreviewTarget = null;

        function showModal(previewId) {
            modal.classList.remove('d-none');
            overlay.classList.remove('d-none');
            document.body.classList.add('modal-open');
            currentPreviewTarget = document.getElementById(previewId);
        }

        function hideModal() {
            modal.classList.add('d-none');
            overlay.classList.add('d-none');
            document.body.classList.remove('modal-open');
            currentPreviewTarget = null;
        }

        const addTextAnimBtn = document.getElementById('add-text-message-animation-button');
        if (addTextAnimBtn) {
            addTextAnimBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showModal('selected-text-animation-preview');
            });
        }

        const addEmailAnimBtn = document.getElementById('add-email-message-animation-button');
        if (addEmailAnimBtn) {
            addEmailAnimBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showModal('selected-email-animation-preview');
            });
        }

        const removeEmailAnimBtn = document.getElementById('remove-email-message-animation-button');
        if (removeEmailAnimBtn) {
            removeEmailAnimBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const previewId = 'selected-email-animation-preview';
                const previewTarget = document.getElementById(previewId);

                if (previewTarget) {
                    previewTarget.innerHTML = "";
                }
            });
        }

        const removeTextAnimBtn = document.getElementById('remove-text-message-animation-button');
        if (removeTextAnimBtn) {
            removeTextAnimBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const previewId = 'selected-text-animation-preview';
                const previewTarget = document.getElementById(previewId);

                if (previewTarget) {
                    previewTarget.innerHTML = "";
                }
            });
        }

        animationItems.forEach(function (item) {
            item.addEventListener('click', function () {
                const img = this.querySelector('img');
                const src = img.getAttribute('src');
                const alt = img.getAttribute('alt');

                if (currentPreviewTarget) {
                    currentPreviewTarget.innerHTML = `<img src="${src}" alt="${alt}" />`;
                }

                hideModal();
            });
        });

        overlay.addEventListener('click', function () {
            hideModal();
        });
    }

    // document.getElementById("add-text-message-animation-button").addEventListener("click", function () {
    //     currentPreviewTarget = "text-animation-preview";
    //     modal.classList.remove("d-none");
    // });

    // // Handle animation selection
    // const baseUrl = window.location.origin; // Fallback for PHP site_url()

    // const gifMap = {
    //     "Partying-Animation-2": `${baseUrl}/wp-content/uploads/2025/07/Partying-Animation-2.gif`,
    //     "Partying-Animation": `${baseUrl}/wp-content/uploads/2025/07/Partying-Animation.gif`,
    //     "celebration-and-birthday-emoji": `${baseUrl}/wp-content/uploads/2025/07/celebration-and-birthday-emoji.gif`
    // };

    // // Handle animation selection
    // document.querySelectorAll("#animation-selection-modal li").forEach(function (item) {
    //     item.addEventListener("click", function () {
    //         const selected = item.getAttribute("data-animation");
    //         const gifSrc = gifMap[selected] || "";

    //         const previewElement = document.getElementById("selected-animation-preview");
    //         if (gifSrc && previewElement) {
    //             previewElement.innerHTML = `<img src="${gifSrc}" alt="${selected}" style="max-width: 100%; height: auto;">`;
    //         }

    //         // Hide modal
    //         document.getElementById("animation-modal").classList.add("d-none");
    //     });
    // });

    // // Show modal on button click
    // const openBtn = document.getElementById("add-text-message-animation-button");
    // if (openBtn) {
    //     openBtn.addEventListener("click", function (e) {
    //         e.preventDefault();
    //         document.getElementById("animation-modal").classList.remove("d-none");
    //     });
    // }
    
    //Add animation code End here ------


    // --- Helpers for placeholder replacement + test data ---
    function normalizePlaceholders(str) {
        if (!str) return "";
        return str
            .replace(/<Full Name>/gi, "&lt;Full Name&gt;")
            .replace(/<First Name>/gi, "&lt;First Name&gt;")
            .replace(/<Last Name>/gi, "&lt;Last Name&gt;")
            .replace(/<Surname>/gi, "&lt;Surname&gt;")
            .replace(/<Name>/gi, "&lt;Name&gt;")
            .replace(/<Email>/gi, "&lt;Email&gt;")
            .replace(/<Gift Card>/gi, "&lt;Gift Card&gt;")
            .replace(/<Gift Card Title>/gi, "&lt;Gift Card Title&gt;")
            .replace(/<Gift Card Name>/gi, "&lt;Gift Card Name&gt;")
            .replace(/<Gift Card Value>/gi, "&lt;Gift Card Value&gt;")
            .replace(/<Price>/gi, "&lt;Price&gt;")
            .replace(/<Value>/gi, "&lt;Value&gt;")
            .replace(/<Sender>/gi, "&lt;Sender&gt;")
            .replace(/<Sender Name>/gi, "&lt;Sender Name&gt;")
            .replace(/<Brand>/gi, "&lt;Brand&gt;")
            .replace(/<Brands>/gi, "&lt;Brands&gt;")
            .replace(/\[Link\]/gi, "&lt;Link&gt;")
            .replace(/<gift card link>/gi, "&lt;Gift Card Link&gt;")
            .replace(/<Gift Card Link>/gi, "&lt;Gift Card Link&gt;");
    }

    function applyPlaceholders(template, replacements) {
        if (!template || template.trim() === "") return "";
        let normalized = normalizePlaceholders(template);
        Object.keys(replacements).forEach(key => {
            const regex = new RegExp(`&lt;${key}&gt;`, "gi");
            normalized = normalized.replace(regex, replacements[key] || "");
        });
        // Also handle [Link] format directly (in case it wasn't normalized)
        normalized = normalized.replace(/\[Link\]/gi, replacements["Link"] || "");
        
        // Handle any remaining angle bracket placeholders that weren't normalized
        // This catches variations like <Clinton> or <JB Hi-Fi gift card>
        // Replace with actual values if they match common patterns
        if (replacements["Sender Name"] && replacements["Sender Name"].trim()) {
            // Replace any <Sender Name> or <Sender> variations
            normalized = normalized.replace(/&lt;Sender Name&gt;/gi, replacements["Sender Name"]);
            normalized = normalized.replace(/&lt;Sender&gt;/gi, replacements["Sender Name"]);
        }
        
        if (replacements["Gift Card"] && replacements["Gift Card"].trim()) {
            // Replace any <Gift Card> variations
            normalized = normalized.replace(/&lt;Gift Card&gt;/gi, replacements["Gift Card"]);
            normalized = normalized.replace(/&lt;Gift Card Title&gt;/gi, replacements["Gift Card"]);
            normalized = normalized.replace(/&lt;Gift Card Name&gt;/gi, replacements["Gift Card"]);
        }
        
        return normalized;
    }

    function buildPlaceholderMap(context, senderName) {
        // Generate wallet link - for test messages, we use a generic wallet URL
        // In production, this would include gc_id and email query params
        const baseUrl = window.location.origin;
        const walletLink = baseUrl + '/my-account/my-wallet/';
        
        return {
            "Full Name": context.fullName,
            "First Name": context.firstName,
            "Last Name": context.lastName,
            "Surname": context.lastName,
            "Name": context.firstName, // <Name> maps to First Name
            "Email": context.email,
            "Gift Card": context.gcName,
            "Gift Card Title": context.gcName,
            "Gift Card Name": context.gcName,
            "Gift Card Value": context.formattedPrice,
            "Price": context.formattedPrice,
            "Value": context.formattedPrice,
            "Sender": senderName,
            "Sender Name": senderName,
            "Brand": context.gcBrands,
            "Brands": context.gcBrands,
            "Link": walletLink, // [Link] placeholder
            "Gift Card Link": walletLink // <gift card link> placeholder
        };
    }

    function getTestSlideContext() {
        const slide = document.querySelector(".gift-card-slide");
        if (!slide) return null;

        const price = parseFloat(slide.querySelector(".gift-card-price")?.textContent.replace(/[^\d.-]/g, "")) || 0;
        const formattedPrice = "$" + price.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        return {
            slide,
            email: slide.dataset.email || "",
            phone: slide.dataset.phone || "",
            firstName: slide.dataset.firstName || "",
            lastName: slide.dataset.surname || "",
            fullName: `${slide.dataset.firstName || ""} ${slide.dataset.surname || ""}`.trim(),
            gcName: slide.dataset.name || "",
            gcBrands: slide.dataset.brands || "",
            formattedPrice
        };
    }

    function getSenderContext() {
        const selectedSenderOption = jQuery("#select-sender-dropdown option:selected");
        const senderName = selectedSenderOption.val() || selectedSenderOption.text() || "";
        console.log("Selected sender option:", selectedSenderOption);
        console.log("Sender name (val):", selectedSenderOption.val());
        console.log("Sender name (text):", selectedSenderOption.text());
        return {
            name: senderName.trim(),
            email: selectedSenderOption.data("email") || ""
        };
    }


    if (jQuery('#send-test-email').length) {
        document.getElementById("send-test-email").addEventListener("click", function () {

            const context = getTestSlideContext();
            if (!context) {
                gcDisplayMessage("No gift cards available.", "error");
                return;
            }
            const slide = context.slide;
            const senderContext = getSenderContext();
            const senderName = senderContext.name;
            const senderEmail = senderContext.email;

            if (!senderEmail) {
                gcDisplayMessage("Please select a sender.", "error");
                return;
            }

            // --- Subject + Message from editor ---
            let emailSubject = typeof tinyMCE !== "undefined" && tinyMCE.get("customise-email-subject-input")
                ? tinyMCE.get("customise-email-subject-input").getContent().trim()
                : document.querySelector(".customise-email-subject-input")?.value.trim() || "Congrats <First Name>, You have received a <Gift Card Value> <Gift Card Title>";

            let emailMessage = typeof tinyMCE !== "undefined" && tinyMCE.get("email_message_editor")
                ? tinyMCE.get("email_message_editor").getContent().trim()
                : document.querySelector("#email_message_editor")?.value.trim() || "";

            if (!emailMessage) {
                gcDisplayMessage("Email message is required.", "error");
                return;
            }

            // --- ✅ Text Message input support ---
            let textMessage = document.querySelector("#text-message-input")?.value.trim() || "";
            const replacements = buildPlaceholderMap(context, senderName);
            if (textMessage) {
                textMessage = applyPlaceholders(textMessage, replacements);
            }

            // --- 🔥 Apply dynamic replacement ---
            emailSubject = applyPlaceholders(emailSubject, replacements);
            emailMessage = applyPlaceholders(emailMessage, replacements);

            let allGiftCards = document.querySelectorAll(".gift-card-checkbox input.gift-card-select");
            let recipientsChecked = {};
            let recipientsUnchecked = {};

            if (allGiftCards.length === 0) {
                gcDisplayMessage("No gift cards available.", "error");
                return;
            }

            let personaliseAllChecked = document.getElementById("personalise-all").checked;

            /*allGiftCards.forEach(checkbox => {
                let slide = checkbox.closest(".gift-card-slide");
                let email = slide.getAttribute("data-email");
                let price = slide.querySelector(".gift-card-price")?.textContent.trim() || "";
                let imageSrc = slide.querySelector(".gift-card-img")?.getAttribute("src") || "";

                if (email) {
                    let targetGroup = (personaliseAllChecked || checkbox.checked) ? recipientsChecked : recipientsUnchecked;

                    if (!targetGroup[email]) {
                        targetGroup[email] = [];
                    }

                    targetGroup[email].push({ price, image: imageSrc });
                }
            });*/

            for (let checkbox of allGiftCards) {
                let slide = checkbox.closest(".gift-card-slide");
                let email = slide.getAttribute("data-email");
                let price = slide.querySelector(".gift-card-price")?.textContent.trim() || "";
                let imageSrc = slide.querySelector(".gift-card-img")?.getAttribute("src") || "";
                //const brands = slide.dataset.brands || '';

                if (email) {
                    let targetGroup = (personaliseAllChecked || checkbox.checked) ? recipientsChecked : recipientsUnchecked;

                    if (!targetGroup[email]) {
                        targetGroup[email] = [];
                    }

                    targetGroup[email].push({ price, image: imageSrc });
                }

                break; // ✅ exits after first loop
            }

            let checkedRecipientData = [];
            let uncheckedRecipientData = [];

            Object.keys(recipientsChecked).forEach(email => {
                checkedRecipientData.push({
                    email: email,
                    gift_cards: recipientsChecked[email]
                });
            });

            Object.keys(recipientsUnchecked).forEach(email => {
                uncheckedRecipientData.push({
                    email: email,
                    gift_cards: recipientsUnchecked[email]
                });
            });

            if (checkedRecipientData.length === 0 && uncheckedRecipientData.length === 0) {
                gcDisplayMessage("No recipient data found.", "error");
                return;
            }

            // let emailSubject = typeof tinyMCE !== "undefined" && tinyMCE.get("customise-email-subject-input")
            //     ? tinyMCE.get("customise-email-subject-input").getContent().trim()
            //     : document.querySelector(".customise-email-subject-input")?.value.trim() || "";

            // Get all checked gift card slides
            let checkedGiftCards = document.querySelectorAll(".gift-card-checkbox input:checked");

            if (checkedGiftCards.length > 0) {
                let recipientNames = [];
                let giftCardValues = [];

                checkedGiftCards.forEach(checkbox => {
                    let slide = checkbox.closest(".gift-card-slide");
                    let firstName = slide.dataset.firstName || "Customer"; // Fetch first name
                    let giftCardValue = slide.querySelector(".gift-card-price")?.textContent.trim() || "Gift Card";

                    // Add names and values to arrays
                    recipientNames.push(firstName);
                    giftCardValues.push(giftCardValue);
                });

                // Create comma-separated lists
                let recipientNamesList = recipientNames.join(", ");
                let giftCardValuesList = giftCardValues.join(", ");

                // Replace placeholders dynamically
                // emailSubject = emailSubject
                //     .replace(/<First Name>/gi, recipientNamesList)
                //     .replace(/<value>/gi, giftCardValuesList);
            }


            // let emailMessage = typeof tinyMCE !== "undefined" && tinyMCE.get("email_message_editor")
            //     ? tinyMCE.get("email_message_editor").getContent().trim()
            //     : document.querySelector("#email_message_editor")?.value.trim() || "";

            // if (!emailMessage) {
            //     gcDisplayMessage("Email message is required.", "error");
            //     return;
            // }

            jQuery.ajax({
                url: customisationData.ajaxUrl || customisationData.ajaxurl,
                type: "POST",
                data: {
                    action: "send_test_email",
                    nonce: customisationData.nonces.custom,
                    checked_recipients: checkedRecipientData,
                    unchecked_recipients: uncheckedRecipientData,
                    sender_name: senderName,
                    sender_email: senderEmail,
                    subject: emailSubject,
                    message: emailMessage
                },
                success: function (response) {
                    if (response.success) {
                        gcDisplayMessage(response.data, "success");
                    } else {
                        gcDisplayMessage("Error: " + response.data, "error");
                    }
                },
                error: function (xhr, status, error) {
                    console.error("AJAX Error: ", status, error);
                    gcDisplayMessage("AJAX error. Check console for details.", "error");
                }
            });
        });
    }

    // Create modal for test text phone number input
    function createTestTextModal() {
        // Check if modal already exists
        if (document.getElementById('test-text-phone-modal')) {
            return;
        }

        const modalHTML = `
            <div id="test-text-phone-modal" class="test-text-modal" style="display: none;">
                <div class="test-text-modal-overlay"></div>
                <div class="test-text-modal-content">
                    <div class="test-text-modal-header">
                        <h3>Send Test Text Message</h3>
                        <button type="button" class="test-text-modal-close" aria-label="Close">&times;</button>
                    </div>
                    <div class="test-text-modal-body">
                        <div class="form-group">
                            <label for="test-phone-input">Enter Australian Phone Number</label>
                            <input type="tel" id="test-phone-input" class="form-control" placeholder="04XX XXX XXX or 61 4XX XXX XXX" maxlength="15">
                            <small class="form-text text-muted">Enter a valid Australian mobile number (10 digits starting with 04, or international format starting with 61)</small>
                            <div id="test-phone-error" class="error-message" style="display: none; color: red; margin-top: 5px;"></div>
                        </div>
                    </div>
                    <div class="test-text-modal-footer">
                        <button type="button" class="btn btn-secondary test-text-modal-cancel">Cancel</button>
                        <button type="button" class="btn btn-primary test-text-modal-send">Send</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Validate Australian phone number
    function validateAustralianPhone(phone) {
        // Remove all non-digit characters
        const cleaned = phone.replace(/\D/g, '');
        
        // Australian mobile numbers:
        // - 10 digits starting with 04 (domestic format: 04XX XXX XXX)
        // - 11 digits starting with 614 (international format: 61 4XX XXX XXX)
        // - 12 digits starting with 6104 (with leading 0: 61 04XX XXX XXX)
        
        if (cleaned.length === 10 && cleaned.startsWith('04')) {
            return { valid: true, formatted: cleaned };
        }
        
        if (cleaned.length === 11 && cleaned.startsWith('614')) {
            return { valid: true, formatted: cleaned };
        }
        
        if (cleaned.length === 12 && cleaned.startsWith('6104')) {
            // Remove the extra 0 after 61
            const formatted = '61' + cleaned.substring(3);
            return { valid: true, formatted: formatted };
        }
        
        return { valid: false, message: 'Please enter a valid Australian mobile number (10 digits starting with 04, or international format starting with 61)' };
    }

    // Show modal
    function showTestTextModal() {
        const modal = document.getElementById('test-text-phone-modal');
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            // Focus on input
            setTimeout(() => {
                document.getElementById('test-phone-input')?.focus();
            }, 100);
        }
    }

    // Hide modal
    function hideTestTextModal() {
        const modal = document.getElementById('test-text-phone-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            // Clear input and error
            const input = document.getElementById('test-phone-input');
            const error = document.getElementById('test-phone-error');
            if (input) input.value = '';
            if (error) {
                error.style.display = 'none';
                error.textContent = '';
            }
        }
    }

    // Function to validate text message length with dynamic content
    function validateTextMessageLength() {
        const sendTestTextBtn = document.getElementById("send-test-text");
        const textMessageInput = document.querySelector("#text-message-input");
        
        if (!sendTestTextBtn || !textMessageInput) {
            return;
        }

        let textMessageTemplate = textMessageInput.value.trim() || "";
        
        if (!textMessageTemplate) {
            sendTestTextBtn.disabled = false;
            sendTestTextBtn.classList.remove('btn-disabled');
            // Remove any existing character count message
            const existingMsg = document.getElementById('text-message-char-count');
            if (existingMsg) {
                existingMsg.remove();
            }
            return;
        }

        // Get first selected recipient for placeholder replacement
        let checkedCheckboxes = document.querySelectorAll(".gift-card-slider .gift-card-checkbox input.gift-card-select:checked");
        if (checkedCheckboxes.length === 0) {
            checkedCheckboxes = document.querySelectorAll(".gift-card-slide .gift-card-checkbox input.gift-card-select:checked");
        }
        if (checkedCheckboxes.length === 0) {
            checkedCheckboxes = document.querySelectorAll(".owl-item .gift-card-select:checked");
        }

        let finalMessageLength = textMessageTemplate.length;
        let hasRecipient = false;

        if (checkedCheckboxes.length > 0) {
            const firstCheckbox = checkedCheckboxes[0];
            const slide = firstCheckbox.closest(".gift-card-slide");
            
            if (slide) {
                hasRecipient = true;
                const senderContext = getSenderContext();
                
                const getDataAttr = (element, attr) => {
                    const camelCase = attr.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                    if (element.dataset && element.dataset[camelCase] !== undefined) {
                        return element.dataset[camelCase];
                    }
                    return element.getAttribute(`data-${attr}`) || "";
                };

                const price = parseFloat(slide.querySelector(".gift-card-price")?.textContent.replace(/[^\d.-]/g, "")) || 0;
                const formattedPrice = "$" + price.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                const context = {
                    firstName: getDataAttr(slide, "first-name") || "",
                    lastName: getDataAttr(slide, "surname") || "",
                    fullName: `${getDataAttr(slide, "first-name") || ""} ${getDataAttr(slide, "surname") || ""}`.trim(),
                    gcName: getDataAttr(slide, "name") || "",
                    gcBrands: getDataAttr(slide, "brands") || "",
                    formattedPrice: formattedPrice
                };

                // Build personalized message to check length
                const replacements = buildPlaceholderMap(context, senderContext.name || "");
                let personalizedMessage = applyPlaceholders(textMessageTemplate, replacements);
                
                // Handle custom replacements (gift card and sender name)
                if (context.gcName && context.gcName.trim()) {
                    personalizedMessage = personalizedMessage.replace(/&lt;[^&]*(?:gift|card)[^&]*&gt;/gi, context.gcName);
                    personalizedMessage = personalizedMessage.replace(/<[^>]*(?:gift|card)[^>]*>/gi, context.gcName);
                }
                
                if (senderContext.name && senderContext.name.trim()) {
                    personalizedMessage = personalizedMessage.replace(/&lt;([^&]+)&gt;/g, function(match, content) {
                        const standardPlaceholders = ['Full Name', 'First Name', 'Last Name', 'Surname', 'Name', 'Email', 
                                                     'Gift Card', 'Gift Card Title', 'Gift Card Name', 'Gift Card Value',
                                                     'Price', 'Value', 'Sender', 'Sender Name', 'Brand', 'Brands', 'Link', 'Gift Card Link'];
                        const isStandard = standardPlaceholders.some(p => {
                            const normalized = p.toLowerCase().replace(/\s+/g, '');
                            const contentNormalized = content.toLowerCase().replace(/\s+/g, '');
                            return normalized === contentNormalized || contentNormalized.includes(normalized);
                        });
                        if (!isStandard && content.length < 100 && !content.includes('http') && !content.includes('www') && !content.includes('://')) {
                            return senderContext.name;
                        }
                        return match;
                    });
                    personalizedMessage = personalizedMessage.replace(/<([^>]+)>/g, function(match, content) {
                        const standardPlaceholders = ['Full Name', 'First Name', 'Last Name', 'Surname', 'Name', 'Email', 
                                                     'Gift Card', 'Gift Card Title', 'Gift Card Name', 'Gift Card Value',
                                                     'Price', 'Value', 'Sender', 'Sender Name', 'Brand', 'Brands', 'Link', 'Gift Card Link'];
                        const isStandard = standardPlaceholders.some(p => {
                            const normalized = p.toLowerCase().replace(/\s+/g, '');
                            const contentNormalized = content.toLowerCase().replace(/\s+/g, '');
                            return normalized === contentNormalized || contentNormalized.includes(normalized);
                        });
                        if (!isStandard && content.length < 100 && !content.includes('http') && !content.includes('www') && !content.includes('://')) {
                            return senderContext.name;
                        }
                        return match;
                    });
                }
                
                // Remove double quotes
                personalizedMessage = personalizedMessage.replace(/"/g, '').replace(/&quot;/g, '');
                finalMessageLength = personalizedMessage.length;
            }
        }

        // Remove existing character count message
        const existingMsg = document.getElementById('text-message-char-count');
        if (existingMsg) {
            existingMsg.remove();
        }

        // Create character count display
        const charCountMsg = document.createElement('div');
        charCountMsg.id = 'text-message-char-count';
        charCountMsg.style.marginTop = '5px';
        charCountMsg.style.fontSize = '12px';
        
        if (hasRecipient) {
            if (finalMessageLength > 160) {
                charCountMsg.style.color = 'red';
                charCountMsg.textContent = `Message length: ${finalMessageLength} characters (exceeds 160 character limit)`;
                sendTestTextBtn.disabled = true;
                sendTestTextBtn.classList.add('btn-disabled');
            } else {
                charCountMsg.style.color = finalMessageLength > 140 ? '#ff9800' : '#666';
                charCountMsg.textContent = `Message length: ${finalMessageLength} / 160 characters`;
                sendTestTextBtn.disabled = false;
                sendTestTextBtn.classList.remove('btn-disabled');
            }
        } else {
            if (textMessageTemplate.length > 160) {
                charCountMsg.style.color = 'red';
                charCountMsg.textContent = `Template length: ${textMessageTemplate.length} characters (exceeds 160 character limit)`;
                sendTestTextBtn.disabled = true;
                sendTestTextBtn.classList.add('btn-disabled');
            } else {
                charCountMsg.style.color = '#666';
                charCountMsg.textContent = `Template length: ${textMessageTemplate.length} / 160 characters (select a recipient to see final length)`;
                sendTestTextBtn.disabled = false;
                sendTestTextBtn.classList.remove('btn-disabled');
            }
        }
        
        textMessageInput.parentElement.appendChild(charCountMsg);
    }

    if (jQuery('#send-test-text').length) {
        // Create modal on page load
        createTestTextModal();

        // Add real-time validation on text message input
        const textMessageInput = document.querySelector("#text-message-input");
        if (textMessageInput) {
            textMessageInput.addEventListener("keyup", validateTextMessageLength);
            textMessageInput.addEventListener("change", validateTextMessageLength);
            textMessageInput.addEventListener("paste", function() {
                setTimeout(validateTextMessageLength, 10);
            });
        }

        // Also validate when recipient selection changes
        jQuery(document).on("change", ".gift-card-select", function() {
            setTimeout(validateTextMessageLength, 100);
        });

        // Open modal when button is clicked
        document.getElementById("send-test-text").addEventListener("click", function () {
            // Validate text message template first
            let textMessageTemplate = document.querySelector("#text-message-input")?.value.trim() || "";
            if (!textMessageTemplate) {
                gcDisplayMessage("Text message is required.", "error");
                return;
            }

            // Validate 160 character limit (before placeholder replacement)
            if (textMessageTemplate.length > 160) {
                gcDisplayMessage("Message exceeds 160 characters. Please reduce the message length.", "error");
                return;
            }

            // Show the modal
            showTestTextModal();
        });

        // Close modal handlers
        const modal = document.getElementById('test-text-phone-modal');
        if (modal) {
            // Close button
            const closeBtn = modal.querySelector('.test-text-modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', hideTestTextModal);
            }

            // Cancel button
            const cancelBtn = modal.querySelector('.test-text-modal-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', hideTestTextModal);
            }

            // Close on overlay click
            const overlay = modal.querySelector('.test-text-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', hideTestTextModal);
            }

            // Close on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'block') {
                    hideTestTextModal();
                }
            });

            // Send button handler
            const sendBtn = modal.querySelector('.test-text-modal-send');
            if (sendBtn) {
                sendBtn.addEventListener('click', function() {
                    const phoneInput = document.getElementById('test-phone-input');
                    const errorDiv = document.getElementById('test-phone-error');
                    
                    if (!phoneInput) return;

                    const phone = phoneInput.value.trim();
                    
                    if (!phone) {
                        errorDiv.textContent = 'Please enter a phone number';
                        errorDiv.style.display = 'block';
                        return;
                    }

                    // Validate Australian phone number
                    const validation = validateAustralianPhone(phone);
                    if (!validation.valid) {
                        errorDiv.textContent = validation.message;
                        errorDiv.style.display = 'block';
                        return;
                    }

                    // Hide error
                    errorDiv.style.display = 'none';

                    // Get selected recipients data for placeholder replacement
                    const checkedCheckboxes = document.querySelectorAll(".gift-card-slider .gift-card-checkbox input.gift-card-select:checked");
                    if (checkedCheckboxes.length === 0) {
                        checkedCheckboxes = document.querySelectorAll(".gift-card-slide .gift-card-checkbox input.gift-card-select:checked");
                    }
                    if (checkedCheckboxes.length === 0) {
                        checkedCheckboxes = document.querySelectorAll(".owl-item .gift-card-select:checked");
                    }

                    if (checkedCheckboxes.length === 0) {
                        errorDiv.textContent = 'Please select at least one recipient from the carousel';
                        errorDiv.style.display = 'block';
                        return;
                    }

                    const senderContext = getSenderContext();
                    console.log("Sender Context:", senderContext);
                    
                    let textMessageTemplate = document.querySelector("#text-message-input")?.value.trim() || "";

                    // Get first selected recipient for placeholder replacement
                    const firstCheckbox = checkedCheckboxes[0];
                    const slide = firstCheckbox.closest(".gift-card-slide");
                    
                    if (!slide) {
                        errorDiv.textContent = 'Could not find recipient data';
                        errorDiv.style.display = 'block';
                        return;
                    }

                    const getDataAttr = (element, attr) => {
                        const camelCase = attr.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                        if (element.dataset && element.dataset[camelCase] !== undefined) {
                            return element.dataset[camelCase];
                        }
                        return element.getAttribute(`data-${attr}`) || "";
                    };

                    const price = parseFloat(slide.querySelector(".gift-card-price")?.textContent.replace(/[^\d.-]/g, "")) || 0;
                    const formattedPrice = "$" + price.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    // Get gift card name from data-name attribute
                    const giftCardName = getDataAttr(slide, "name") || "";
                    console.log("Gift Card Name from data-name:", giftCardName);
                    console.log("Slide data-name attribute:", slide.getAttribute("data-name"));

                    const context = {
                        firstName: getDataAttr(slide, "first-name") || "",
                        lastName: getDataAttr(slide, "surname") || "",
                        fullName: `${getDataAttr(slide, "first-name") || ""} ${getDataAttr(slide, "surname") || ""}`.trim(),
                        gcName: giftCardName, // Gift card name from data-name attribute
                        gcBrands: getDataAttr(slide, "brands") || "",
                        formattedPrice: formattedPrice
                    };

                    console.log("Context object:", context);
                    console.log("Sender Name from dropdown:", senderContext.name);

                    // Build personalized message
                    const replacements = buildPlaceholderMap(context, senderContext.name || "");
                    console.log("Replacements map:", replacements);
                    console.log("Original message template:", textMessageTemplate);
                    
                    let personalizedMessage = applyPlaceholders(textMessageTemplate, replacements);
                    console.log("Personalized message (after standard placeholders):", personalizedMessage);
                    
                    // Replace any remaining angle bracket content that wasn't a standard placeholder
                    // This handles cases like <Clinton> or <JB Hi-Fi gift card>
                    // Handle both normalized (&lt;...&gt;) and non-normalized (<...>) formats
                    
                    // First, replace gift card name patterns (handles both formats)
                    if (context.gcName && context.gcName.trim()) {
                        // Replace normalized format (&lt;...&gt;) containing "gift" or "card"
                        personalizedMessage = personalizedMessage.replace(/&lt;[^&]*(?:gift|card)[^&]*&gt;/gi, context.gcName);
                        // Replace non-normalized format (<...>) containing "gift" or "card"
                        personalizedMessage = personalizedMessage.replace(/<[^>]*(?:gift|card)[^>]*>/gi, context.gcName);
                        console.log("After gift card replacement:", personalizedMessage);
                    }
                    
                    // Then replace any remaining <...> or &lt;...&gt; that looks like a sender name
                    if (senderContext.name && senderContext.name.trim()) {
                        // Replace normalized format (&lt;...&gt;)
                        personalizedMessage = personalizedMessage.replace(/&lt;([^&]+)&gt;/g, function(match, content) {
                            // Skip standard placeholders
                            const standardPlaceholders = ['Full Name', 'First Name', 'Last Name', 'Surname', 'Name', 'Email', 
                                                         'Gift Card', 'Gift Card Title', 'Gift Card Name', 'Gift Card Value',
                                                         'Price', 'Value', 'Sender', 'Sender Name', 'Brand', 'Brands', 'Link', 'Gift Card Link'];
                            const isStandard = standardPlaceholders.some(p => {
                                const normalized = p.toLowerCase().replace(/\s+/g, '');
                                const contentNormalized = content.toLowerCase().replace(/\s+/g, '');
                                return normalized === contentNormalized || contentNormalized.includes(normalized);
                            });
                            
                            if (isStandard) {
                                return match;
                            }
                            
                            // If it's a reasonable length and doesn't contain URLs, replace with sender name
                            if (content.length < 100 && !content.includes('http') && !content.includes('www') && !content.includes('://')) {
                                console.log(`Replacing normalized placeholder "${content}" with sender name: "${senderContext.name}"`);
                                return senderContext.name;
                            }
                            
                            return match;
                        });
                        
                        // Replace non-normalized format (<...>)
                        personalizedMessage = personalizedMessage.replace(/<([^>]+)>/g, function(match, content) {
                            // Skip standard placeholders
                            const standardPlaceholders = ['Full Name', 'First Name', 'Last Name', 'Surname', 'Name', 'Email', 
                                                         'Gift Card', 'Gift Card Title', 'Gift Card Name', 'Gift Card Value',
                                                         'Price', 'Value', 'Sender', 'Sender Name', 'Brand', 'Brands', 'Link', 'Gift Card Link'];
                            const isStandard = standardPlaceholders.some(p => {
                                const normalized = p.toLowerCase().replace(/\s+/g, '');
                                const contentNormalized = content.toLowerCase().replace(/\s+/g, '');
                                return normalized === contentNormalized || contentNormalized.includes(normalized);
                            });
                            
                            if (isStandard) {
                                return match;
                            }
                            
                            // If it's a reasonable length and doesn't contain URLs, replace with sender name
                            if (content.length < 100 && !content.includes('http') && !content.includes('www') && !content.includes('://')) {
                                console.log(`Replacing placeholder "${content}" with sender name: "${senderContext.name}"`);
                                return senderContext.name;
                            }
                            
                            return match;
                        });
                    }
                    
                    console.log("Personalized message (after custom replacements):", personalizedMessage);
                    
                    // Remove double quotes
                    personalizedMessage = personalizedMessage.replace(/"/g, '').replace(/&quot;/g, '');
                    
                    // Validate final message length
                    if (personalizedMessage.length > 160) {
                        errorDiv.textContent = 'Personalized message exceeds 160 characters. Please reduce the message length.';
                        errorDiv.style.display = 'block';
                        return;
                    }

                    // Debug: Log the final message that will be sent
                    console.log("=== Final Message to be Sent ===");
                    console.log("Phone Number:", validation.formatted);
                    console.log("Sender Name:", senderContext.name);
                    console.log("Gift Card Name:", context.gcName);
                    console.log("Recipient Name:", context.fullName);
                    console.log("Final Message:", personalizedMessage);
                    console.log("Message Length:", personalizedMessage.length, "characters");
                    console.log("=================================");

                    // Disable send button
                    sendBtn.disabled = true;
                    sendBtn.textContent = 'Sending...';

                    // Send SMS
                    jQuery.ajax({
                        url: customisationData.ajaxUrl || customisationData.ajaxurl,
                        type: "POST",
                        data: {
                            action: "send_test_text",
                            nonce: customisationData.nonces.custom,
                            phone: validation.formatted,
                            message: personalizedMessage,
                            sender_name: senderContext.name || ""
                        },
                        success: function (response) {
                            sendBtn.disabled = false;
                            sendBtn.textContent = 'Send';
                            
                            if (response.success) {
                                hideTestTextModal();
                                gcDisplayMessage(response.data, "success");
                            } else {
                                errorDiv.textContent = response.data || 'Failed to send test text';
                                errorDiv.style.display = 'block';
                            }
                        },
                        error: function (xhr, status, error) {
                            sendBtn.disabled = false;
                            sendBtn.textContent = 'Send';
                            console.error("AJAX Error: ", status, error);
                            errorDiv.textContent = 'AJAX error. Please try again.';
                            errorDiv.style.display = 'block';
                        }
                    });
                });
            }
        }
    }


    function gcDisplayMessage(message, type) {
        let messageContainer = document.getElementById("gc-email-messages");
        let messageClass = type === "success" ? "gc-success-message" : "gc-error-message";

        let messageElement = document.createElement("div");
        messageElement.classList.add("gc-message", messageClass);
        messageElement.innerHTML = message;

        messageContainer.innerHTML = "";
        messageContainer.appendChild(messageElement);
        // setTimeout(() => {
            // messageElement.remove();
        // }, 5000);
    }
});

function handleEditProductClick(e, element) {
    // debugger;
    e.preventDefault();
    sessionStorage.setItem('lastPage', window.location.href);

    const url = element.getAttribute('href');
    if (url) {
        window.open(url, '_blank');
    }
}