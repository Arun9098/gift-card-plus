jQuery(document).ready(function ($) {
    const $select = jQuery('#select-email');
    const $senderName = jQuery('#sender-name');
    const $senderEmail = jQuery('#sender-email');
    const $trigger = jQuery('#send-trigger');
    const $textarea = jQuery('#email-template-content');
    const $preview = jQuery('#template-preview');
    const $form = jQuery('#email-settings-form');
    const $subject = jQuery('#email-subject');

    let templates = [], usedTriggers = [];

    // Initialize WordPress TinyMCE Editor
    if (typeof wp !== 'undefined' && wp.editor) {
        wp.editor.initialize('email-template-content', {
            tinymce: {
                height: 300,
                wpautop: true,
                branding: false,
                menubar: true,
                toolbar1: 'formatselect | bold italic underline | bullist numlist | link image | alignleft aligncenter alignright | code fullscreen',
                plugins: 'lists link image code fullscreen',
                setup: function (editor) {
                    editor.on('change keyup', () => {
                        editor.save();
                        renderPreview();
                    });
                }
            },
            quicktags: true,
            mediaButtons: false
        });
    }

    //  Fetch Templates
    $.post(et_email_settings.ajax_url, { action: 'et_get_email_templates' }, function (response) {
        if (response.success) {
            templates = response.data;
            usedTriggers = [];

            $select.html('<option value="">Select</option>');
            templates.forEach(t => {
                $select.append(`<option value="${t.id}">${t.title}</option>`);
                if (t.trigger) usedTriggers.push(t.trigger);
            });
        } else {
            $('<div class="error-message" style="color:red; margin-bottom:10px;">Failed to load template.</div>')
            .insertBefore('label[for="select-email"]');    
            // alert('Failed to load email templates.');
        }
    });


    // Clear all fields
    function clearFormFields() {
        $senderName.val('');
        $senderEmail.val('');
        $subject.val('');
        $trigger.val('').find('option').prop('disabled', false);

        const editor = tinymce.get('email-template-content');
        if (editor) {
            editor.setContent('');
        } else {
            $textarea.val('');
        }
        renderPreview();
    }
    //  Handle template selection
    $select.on('change', function () {
        const id = $(this).val();
        if (!id) {
            clearFormFields();
            return;
        }


        $.post(et_email_settings.ajax_url, {
            action: 'et_get_single_email_template',
            email_id: id
        }, function (response) {
            if (!response.success) {
                $('<div class="error-message" style="color:red; margin-bottom:10px;">Failed to load template.</div>')
                .insertBefore('label[for="select-email"]');        
                return;
            }

            const t = response.data;
            const requiredDomain = '@delivery.giftcardsplus.com.au';
    
            $senderName.val(t.sender_name);
    
            // Strip domain for display
            let senderUsername = t.sender_email;
            if (senderUsername.endsWith(requiredDomain)) {
                senderUsername = senderUsername.replace(requiredDomain, '');
            }
            $senderEmail.val(senderUsername);
    
            $subject.val(t.subject);
            $trigger.val(t.trigger);
    
            // Update editor content
            const editor = tinymce.get('email-template-content');
            
            if (editor && !editor.isHidden()) {
                editor.setContent(t.content);
            } else {
                $textarea.val(t.content);
            }

            renderPreview();

            // Disable used triggers except current
            $trigger.find('option').prop('disabled', false);
            usedTriggers.forEach(tr => {
                if (tr !== t.trigger) {
                    $trigger.find(`option[value="${tr}"]`).prop('disabled', true);
                }
            });
        });
    });

    // Function to show error message
    function showError($field, message) {
        $field.next('.field-error').remove(); // Remove existing error
        $field.after(`<div class="field-error" style="color: red; margin-top: 4px;">${message}</div>`);
    }

    // Function to clear error message
    function clearError($field) {
        $field.next('.field-error').remove();
    }

    function validateFormFields() {
        let isValid = true;

        // Validate Select Email
        // if (!$select.val()) {
        //     showError($select, 'Please select an email template.');
        //     isValid = false;
        // } else {
        //     clearError($select);
        // }

        // Validate Sender Name
        // if (!$senderName.val().trim()) {
        //     showError($senderName, 'Please enter sender name.');
        //     isValid = false;
        // } else {
        //     clearError($senderName);
        // }

        // Validate Sender Email
        const senderEmailVal = $senderEmail.val().trim();
        if (!senderEmailVal) {
            showError($senderEmail, 'Please enter sender email.');
            isValid = false;
        } else if (senderEmailVal.includes('@')) {
            showError($senderEmail, 'Please enter a valid email address.');
            isValid = false;
        } else {
            clearError($senderEmail);
        }

        // Validate Email Subject
        // if (!$subject.val().trim()) {
        //     showError($subject, 'Please enter email subject.');
        //     isValid = false;
        // } else {
        //     clearError($subject);
        // }

        // Validate Trigger
        // if (!$trigger.val()) {
        //     showError($trigger, 'Please select a trigger.');
        //     isValid = false;
        // } else {
        //     clearError($trigger);
        // }

        // Validate Content
        // const content = tinymce.get('email-template-content')?.getContent()?.trim() || $textarea.val().trim();
        // if (!content) {
        //     showError($textarea, 'Please enter email content.');
        //     isValid = false;
        // } else {
        //     clearError($textarea);
        // }
        // Scroll to first error
        if (!isValid) {
            jQuery('html, body').animate({
                scrollTop: $senderEmail.offset().top - 100
            }, 400);
        }


        return isValid;
    }

    //  Live preview switch
    jQuery('input[name="preview_type"]').on('change', renderPreview);

    function renderPreview() {
        const editor = tinymce.get('email-template-content');
        const content = editor && !editor.isHidden() ? editor.getContent() : $textarea.val();
        const type = jQuery('input[name="preview_type"]:checked').val();
        $preview.html(type === 'html' ? content : `<pre>${jQuery('<div>').text(content).html()}</pre>`);
    }

    //  Save form
    $form.on('submit', function (e) {
        e.preventDefault();
        if (tinymce.get('email-template-content')) {
            tinymce.get('email-template-content').save();
        }
        if (!validateFormFields()) {
            return; // Stop form submission
        }

        $.post(et_email_settings.ajax_url, {
            action: 'et_save_email_template',
            email_id: $select.val(),
            sender_name: $senderName.val(),
            sender_email: $senderEmail.val().trim() + '@delivery.giftcardsplus.com.au',
            trigger: $trigger.val(),
            content: $textarea.val(),
            subject: $subject.val()
        }, function (res) {
            const $messageBox = jQuery('.success-add-template');
            if (res.success) {
                $messageBox
                    .html('<div style="color: green; margin-top: 10px;">Template saved successfully.</div>')
                    .fadeIn();
                } else {
                $messageBox
                    .html(`<div style="color: red; margin-top: 10px;">Error: ${res.data}</div>`)
                    .fadeIn();
                }
                setTimeout(() => {
                    $messageBox.fadeOut();
                }, 5000);
        });
    });
    jQuery('#save-template').on('click', function () {
        jQuery('#email-settings-form').submit(); // Trigger submit on the form
    });
    
    // Test email functionality
    // jQuery('#test-email-input').on('input', function () {
    //     const email = $(this).val().trim();
    //     const isValid = validateEmail(email);
    
    //     jQuery('#send-test-email').prop('disabled', !isValid);
    //     jQuery('#test-email-error').toggle(!isValid);
    // });
    jQuery('#send-test-email').on('click', function () {
        const username = jQuery('#sender-email').val().trim();
        const email = jQuery('#test-email-input').val().trim() || 'noreply@delivery.giftcardsplus.com.au';
        const templateId = jQuery('#select-email').val();
        const senderEmailDomain = jQuery('#sender-domain').val()
        const selectedTemplate = jQuery('#select-email').val();
        if (!selectedTemplate) {
            jQuery('#test-email-error')
                .text('Please select a template')
                .show();
            setTimeout(() => jQuery('#test-email-error').fadeOut(), 3000);
            return;
        }
        const requiredDomain = senderEmailDomain;
        
        // Validate username (no @, no spaces, no domain)
        if (!username || username.includes('@') || /\s/.test(username)) {
            jQuery('#test-email-error').text('Please enter a valid sender username (without @ or domain)').show();
            setTimeout(() => jQuery('#test-email-error').fadeOut(), 3000);
            return;
        }
        
        const fullSenderEmail = `${username}${requiredDomain}`;
        
        if (!templateId) {
            jQuery('.error-template').text('Please select an email template first').css('color', 'red');
            setTimeout(() => jQuery('.error-template').text(''), 3000);
            return;
        }
        
        console.log("Sending email to:", email);
        console.log("Sending email From:", fullSenderEmail);


        $.post(et_email_settings.ajax_url, {
            action: 'et_send_test_email',
            email: email,
            template_id: templateId,
            sender_email: fullSenderEmail
        }, function (response) {
            if (response.success) {
            jQuery('.success-add-template').text(`Test email sent successfully to: ${response.data.email}`).css('color', 'green').show();
            // jQuery('.success-add-template').fadeOut();
            setTimeout(function () {
                jQuery('.success-add-template').fadeOut();
            }, 3000);
            } else {
            jQuery('.error-template').text('Error sending email: ' + response.data).css('color', 'red').show();
            // jQuery('.error-template').text('');
            setTimeout(function () {
                jQuery('.error-template').fadeOut();
            }, 3000);
            }
        }).fail(function () {
            jQuery('.error-template').text('Error sending test email').css('color', 'red').show();
            // jQuery('.error-template').text('');
            setTimeout(function () {
                jQuery('.error-template').fadeOut();
            }, 3000);
        });
    });
      
    
    // Validate pre-filled email on page load
    $(document).ready(function () {
        const email = jQuery('#test-email-input').val().trim();
        const isValid = validateEmail(email);

        jQuery('#send-test-email').prop('disabled', !isValid);
        jQuery('#test-email-error').toggle(!isValid);
    });


    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    const input = document.getElementById("test-email-input");
    const icon = document.createElement("span");
    icon.className = "dashicons dashicons-edit edit-icon";
  
    // Append pencil icon right after the input
    input.parentNode.appendChild(icon);
  
    icon.addEventListener("click", function () {
      input.classList.add("editable");
      input.removeAttribute("readonly");
      input.focus();
    });
  
    // Optional: blur event to remove editable mode
    input.addEventListener("blur", function () {
      input.classList.remove("editable");
      input.setAttribute("readonly", "readonly");
    });
  
    // Set default to readonly initially
    input.setAttribute("readonly", "readonly");
    
});
