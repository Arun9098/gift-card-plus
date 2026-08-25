jQuery(document).ready(function ($) {



    // Trigger file dialog
    jQuery('.upload-link').on('click', function (e) {
        e.preventDefault();
        jQuery('.upload-file-cs').trigger('click');
    });

    // Show selected file name
    jQuery('.upload-file-cs').on('change', function () {
        let fileName = jQuery(this).val().split('\\').pop();
        jQuery('.selected-file-name').text(fileName);
    });



    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    let selectedProductId = null;
    let autoPopulateCheckbox = $('input[name="auto_populate"]');
    let deliveryCostField = $('#delivery_cost'); // Delivery cost input field
    let parentSkuWarning = $('<div class="sku-warning-message" style="color: red; font-size: 13px; margin-top: 5px;"></div>');

    // Inject the warning container below the input if not present
    if ($('#parent_sku').next('.sku-warning-message').length === 0) {
        $('#parent_sku').after(parentSkuWarning);
    }

    $('#parent_sku').autocomplete({
        source: function (request, response) {
            const selectedSkuType = $('input[name="sku_type"]:checked').val();
            if (selectedSkuType === 'Child') {
                console.log('child inside');
                $.ajax({
                    url: ajax_sku.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'search_product_sku',
                        search_term: request.term,
                        nonce: ajax_sku.nonce
                    },
                    success: function (data) {
                        if (data.success && data.data.length > 0) {
                            response(data.data.map(function (item) {
                                return {
                                    label: item.sku + ' - ' + item.name,
                                    value: item.sku,
                                    id: item.id
                                };
                            }));
                            parentSkuWarning.text(''); // Clear any warning
                        } else {
                            response([]); // No results
                            selectedProductId = null;
                        }
                    }
                });
            }
        },
        minLength: 1,
        select: function (event, ui) {
            selectedProductId = ui.item.id;
            autoPopulateCheckbox.prop("disabled", false);
            // console.log("Selected Product ID:", selectedProductId);
            parentSkuWarning.text(''); // Clear warning

            // Enable checkbox if SKU exists

            if (autoPopulateCheckbox.is(':checked')) {
                fetchProductDetails();
            }
        }
    });

    // When user types and no match is found, disable the checkbox
    $('#parent_sku').on('input', function () {
        selectedProductId = null;
        autoPopulateCheckbox.prop("disabled", true);
        parentSkuWarning.text('');
        clearFields();
    });

    $('#parent_sku').on('blur', function () {
        const enteredVal = $(this).val().trim();
        if (enteredVal !== '' && !selectedProductId) {
            parentSkuWarning.text('The entered Parent SKU does not exist.');
        } else {
            parentSkuWarning.text('');
        }
    });

    function syncSkuTypeUI(skuType, resetValues) {
        var $parentSkuField = $('#parent_sku');
        var $parentSkuLabel = $("label[for='parent_sku']");

        if (skuType === 'Child') {
            $('#parent_sku_field_wrapper, #auto_populate_field_wrapper').show();
            $('#parent_sku').attr('required', true); // ✅ Add required
            if (resetValues) {
                clearFields();
                selectedProductId = null;
                autoPopulateCheckbox.prop('checked', false).prop('disabled', true);
                $('#parent_sku').val('');
            }
            // Add * to label if not already present
            if (!$parentSkuLabel.text().includes('*')) {
                $parentSkuLabel.append(' <span class="required-asterisk">*</span>');
            }
        } else if(skuType === 'Parent') {
            $('#parent_sku_field_wrapper, #auto_populate_field_wrapper').hide();
            $('#parent_sku').removeAttr('required'); // ✅ Remove required
            if (resetValues) {
                clearFields();
                selectedProductId = null;
                autoPopulateCheckbox.prop('checked', false).prop('disabled', true);
                $('#parent_sku').val('');
            }
            $parentSkuLabel.find('.required-asterisk').remove();
        } else{
            $('#parent_sku_field_wrapper, #auto_populate_field_wrapper').hide();
            $('#parent_sku').removeAttr('required'); // ✅ Remove required
            if (resetValues) {
                selectedProductId = null;
                autoPopulateCheckbox.prop('checked', false).prop('disabled', true);
                $('#parent_sku').val('');
            }
            $parentSkuLabel.find('.required-asterisk').remove();
        }
    }

    $('input[name="sku_type"]').on('change', function () {
        syncSkuTypeUI($(this).val(), true);
    });

    // Ensure correct visibility on edit page load (when Child is preselected).
    syncSkuTypeUI($('input[name="sku_type"]:checked').val(), false);
    
    

    autoPopulateCheckbox.change(function () {
        if ($(this).is(':checked') && selectedProductId) {
            fetchProductDetails();
        } else {
            clearFields();
            $(this).prop('checked', false); // Uncheck (in case needed)
        }
    });

    function fetchProductDetails() {
        if (!selectedProductId) return;

        console.log("Fetching product details for ID:", selectedProductId);
        $.ajax({
            url: ajax_sku.ajax_url,
            type: 'POST',
            data: {
                action: 'get_product_details',
                product_id: selectedProductId,
                nonce: ajax_sku.nonce
            },
            success: function (response) {
                console.log("AJAX Response:", response);
                if (response.success) {
                    var productDetails = response.data;
                    if (productDetails) {

                        let $shortDesc = $('#short_description');
                        $shortDesc.html(productDetails.short_description).trigger('input').addClass('auto-populated');
                        if (productDetails.short_description) {
                            $shortDesc.addClass('filled-data');
                        }

                        let $shortInput = $('#short_description_input');
                        $shortInput.val(productDetails.short_description).addClass('auto-populated');
                        if (productDetails.short_description) {
                            $shortInput.addClass('filled-data');
                        }
                        updateHiddenInput('short_description');
                    
                        // Long Description
                        let $longDesc = $('#long_description');
                        $longDesc.html(productDetails.long_description).trigger('input').addClass('auto-populated');
                        if (productDetails.long_description) {
                            $longDesc.addClass('filled-data');
                        }
                        updateHiddenInput('long_description');
                    
                        // Terms & Conditions
                        let $terms = $('#terms_conditions');
                        $terms.html(productDetails.terms_conditions).trigger('input').addClass('auto-populated');
                        if (productDetails.terms_conditions) {
                            $terms.addClass('filled-data');
                        }
                        updateHiddenInput('terms_conditions');
                    
                        // How to Use
                        let $howToUse = $('#how_to_use');
                        $howToUse.html(productDetails.how_to_use).trigger('input').addClass('auto-populated');
                        if (productDetails.how_to_use) {
                            $howToUse.addClass('filled-data');
                        }
                        updateHiddenInput('how_to_use');
                    
                        // Expiry Date
                        if (productDetails._expire_date) {
                            let $expire = $('#_expire_date');
                            $expire.html(productDetails._expire_date).trigger('input').addClass('auto-populated filled-data');
                            updateHiddenInput('_expire_date');
                        }

                        let onsiteFrom = productDetails._onsite_from || "";
                        let onsiteTo = productDetails._onsite_to || "";
                        
                        let alwaysOnCheckbox = $('#always_on');
                        let onSiteFrom = document.getElementById("_onsite_from_label");
                        let onSiteTo = document.getElementById("_onsite_to_label");
                        
                        // Show/hide fields and checkbox logic
                        if (!onsiteFrom && !onsiteTo) {
                            $('#_onsite_from').val('');
                            $('#_onsite_to').val('');
                            alwaysOnCheckbox.prop('checked', true);
                            onSiteFrom.style.display = "none";
                            onSiteTo.style.display = "none";
                            // console.log('✅ Checkbox checked - No onsite dates');
                        } else {
                            // One or both dates exist → don't pre-fill the other if missing
                            $('#_onsite_from').val(onsiteFrom).trigger('change').trigger('input').addClass('auto-populated filled-data');
                            $('#_onsite_to').val(onsiteTo).trigger('change').trigger('input').addClass('auto-populated filled-data');
                            alwaysOnCheckbox.prop('checked', false);
                            onSiteFrom.style.display = "block";
                            onSiteTo.style.display = "block";
                            // console.log('❌ Checkbox unchecked - At least one onsite date exists');
                        }
                        

                        // ✅ Update delivery cost field
                        // if (productDetails.delivery_cost) {
                        // deliveryCostField.val(productDetails.delivery_cost).trigger('input').addClass('auto-populated');
                        // }
                        if (productDetails.preset_delivery_class) {
                            $('#presetDeliveryClass').prop('checked', true);
                            $('#presetClasses').show().val(productDetails.preset_delivery_class).addClass('auto-populated filled-data');                      
                        } else {
                            $('#presetDeliveryClass').prop('checked', false);
                            $('#presetClasses').hide().val('').removeClass('filled-data');
                            // deliveryCostField.val("");
                        }
                    }
                } else {
                    console.error("Error fetching product details:", response.data);
                }
            },
            error: function (error) {
                console.error("AJAX Error:", error);
            }
        });
    }

    function updateHiddenInput(editorId) {
        document.getElementById(editorId + "_input").value = document.getElementById(editorId).innerHTML;
    }
    
    // Remove button functionality (when clicking the X icon)
    $(document).on('click', '.remove-brand-logo', function () {
        $('#brand_thumbnail_url').val('');
        $('#brand_logo_preview').html('');
    });

    function clearFields() {
        console.log("Clearing fields as checkbox is unchecked or SKU is invalid.");
        
        $('#short_description').html('').trigger('input').removeClass('auto-populated filled-data');
        $('#short_description_input').val('').removeClass('auto-populated filled-data');

        $('#long_description').html('').trigger('input').removeClass('auto-populated filled-data');
        $('#long_description_input').val('').removeClass('auto-populated filled-data');

        $('#terms_conditions').html('').trigger('input').removeClass('auto-populated filled-data');
        $('#terms_conditions_input').val('').removeClass('auto-populated filled-data');

        $('#how_to_use').html('').trigger('input').removeClass('auto-populated filled-data');
        $('#how_to_use_input').val('').removeClass('auto-populated filled-data');

        $('#_expire_date').val('').trigger('input').removeClass('auto-populated filled-data');
        $('#_onsite_from').val('').trigger('input').removeClass('auto-populated filled-data');
        $('#_onsite_to').val('').trigger('input').removeClass('auto-populated filled-data');
        // $('#delivery_cost').val('').trigger('input').removeClass('auto-populated');
    
        $('#presetDeliveryClass').prop('checked', false);
        $('#presetClasses').hide().val('');
        $('#presetClasses').val('').removeClass('auto-populated filled-data');
    
        // ✅ Don't disable checkbox here
        $('#auto_populate_from_parent_sku').prop('checked', false);
    
        // Hide onsite fields again if needed
        $('#_onsite_from_label, #_onsite_to_label').hide();
        $('#always_on').prop('checked', true);
    }
    


    document.querySelectorAll(".edit-icon").forEach(icon => {
        icon.addEventListener("click", function () {
            let label = this.previousElementSibling;
            label.contentEditable = true;
            label.focus();
    
            label.addEventListener("blur", function () {
                label.contentEditable = false;
                let fieldName = label.getAttribute("for");
    
                // Save updated label to localStorage
                localStorage.setItem("label_" + fieldName, label.textContent);
                
                // Store the updated label in a hidden input for form submission
                let hiddenInput = document.querySelector(`input[name="label_${fieldName}"]`);
                if (!hiddenInput) {
                    hiddenInput = document.createElement("input");
                    hiddenInput.type = "hidden";
                    hiddenInput.name = "label_" + fieldName;
                    document.querySelector("form").appendChild(hiddenInput);
                }
                hiddenInput.value = label.textContent;
            });
        });
    });
    
    // Restore saved labels on page load
    // document.querySelectorAll("label").forEach(label => {
    //     let fieldName = label.getAttribute("for");
    //     let savedText = localStorage.getItem("label_" + fieldName);
    //     if (savedText) {
    //         label.textContent = savedText;
    //     }
    // });
    


});
