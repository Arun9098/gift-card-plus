jQuery(document).ready(function ($) {
	function handleCustomisationAction(e, isSkip) {
		const senderSelect = document.getElementById("select-sender-dropdown");
		const selectedValue = senderSelect.value;

		$(".select-sender-wrapper .error-message").remove();
		$("#select-sender-dropdown").removeClass("input-error");
		// console.log('!isSkip', !isSkip);
		// console.log('!isSkip', isSkip);
		// console.log(!selectedValue);
		// console.log(senderSelect.selectedIndex === 0);

		if (!isSkip && (!selectedValue)) {
			e.preventDefault();
			// console.log('inside : validate sender');
			// updateFieldsToggle();
			$("#select-sender-dropdown").addClass("input-error");
			$(".select-sender-wrapper").append('<div class="error-message" style="color:red;margin-top:5px;">Please select a sender</div>');
			return;
		}

		// ✅ All validations passed, now fetch button attributes
		const action = e.currentTarget.getAttribute("data-action");
		const currentStep = e.currentTarget.getAttribute("data-step");
		const status = e.currentTarget.getAttribute("data-status");
		const order_id = e.currentTarget.getAttribute("data-order-id");

		// console.log('action is:', action);
		// console.log('currentStep is:', currentStep);
		// console.log('status is:', status);
		// console.log('order_id is:', order_id);
		

		if (action === "save-draft") {
			// console.log("Saving as draft...");
			const btn = e.currentTarget;
			if (btn && btn.id === 'delivery-save-btn') {
				btn.disabled = true;
				btn.setAttribute('aria-busy', 'true');
				btn.classList.add('btn-disabled');
			}
			const nextStepBtn = document.querySelector('.delivery-next-btn');
			if (nextStepBtn) {
				nextStepBtn.disabled = true;
				nextStepBtn.setAttribute('aria-busy', 'true');
				nextStepBtn.classList.add('btn-disabled');
			}

			let personaliseAllCheckbox = jQuery('#personalise-all').is(':checked') ? 'yes' : 'no';
			// console.log('aasa',personaliseAllCheckbox);
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
		

			//For the create message field dynamic START
			const giftCardSlides = document.querySelectorAll(".gift-card-slide");

			// if (giftCardSlides.length === 0) {
			// 	console.log("No gift cards found.");
			// 	return;
			// }
	
			// giftCardSlides.forEach(slide => {
			// 	const email = slide.dataset.email;
			// 	const gc_name = slide.dataset.name;
			// 	const firstName = slide.dataset.firstName || '';
			// 	const lastName = slide.dataset.surname || '';
			// 	const fullName = `${firstName} ${lastName}`.trim();
	
			// 	let message = slide.dataset.message || '';
			// 	if (!message && typeof tinymce !== "undefined" && tinymce.get("email_message_editor")) {
			// 		message = tinymce.get("email_message_editor").getContent().trim();
			// 	}
	
			// 	// Replace placeholders
			// 	message = message.replace("<Full Name>", fullName);
			// 	message = message.replace("<First Name>", firstName);
			// 	message = message.replace("<Last Name>", lastName);
			// 	message = message.replace("<Email>", email);
			// 	message = message.replace("<Gift Card>", gc_name);
	
			// 	const price = parseFloat(slide.querySelector(".gift-card-price").textContent.replace(/[^\d.-]/g, ""));
			// 	message = message.replace("<Price>", "$" + price.toFixed(2));
			// 	message = message.replace("<Value>", "$" + price.toFixed(2));
	
			// 	console.log("Dynamic Message:", message);
			// });

			//For the create message field dynamic END

			const recipientsMap = {};
		
			giftCardSlides.forEach(slide => {
				const email = slide.getAttribute('data-email')?.trim();
				const first_name = slide.getAttribute('data-first-name')?.trim();
				if (!email && first_name) return;
		
				const key = email.toLowerCase()+first_name.toLowerCase();
				if (!recipientsMap[key]) {
					recipientsMap[key] = {
						first_name: first_name,
						surname: slide.getAttribute('data-surname')?? '',
						email: email,
						phone: slide.getAttribute('data-phone')?? '',
						delivery_method: slide.getAttribute('data-delivery-method')?? '',
						gift_cards: []
					};
				}

				// --- For the create message field dynamic START
				// --- Dynamic fields START ---
				const gc_name = slide.dataset.name;
				const gc_brands = slide.dataset.brands || '';
            	const lastName = slide.dataset.surname || '';
				const fullName = `${first_name} ${lastName}`.trim();
				const price = parseFloat(slide.querySelector(".gift-card-price").textContent.replace(/[^\d.-]/g, ""));
				const formattedPrice = "$" + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
				const sender = document.getElementById("select-sender-dropdown")?.value || '';



				// Helper: decode placeholders (<First Name> stays literal, not HTML tag)
				function normalizePlaceholders(str) {
					if (!str) return "";
					return str
						.replace(/<Full Name>/gi, "<Full Name>")
						.replace(/&lt;Full Name&gt;/gi, "<Full Name>")
	                    .replace(/<First Name>/gi, "<First Name>")
	                    .replace(/&lt;First Name&gt;/gi, "<First Name>")
	                    .replace(/<Surname>/gi, "<Surname>")
	                    .replace(/&lt;Surname&gt;/gi, "<Surname>")
	                    .replace(/<Last Name>/gi, "<Last Name>")
	                    .replace(/&lt;Last Name&gt;/gi, "<Last Name>")
	                    .replace(/<Email>/gi, "<Email>")
	                    .replace(/&lt;Email&gt;/gi, "<Email>")
	                    .replace(/<Gift Card>/gi, "<Gift Card>")
	                    .replace(/&lt;Gift Card&gt;/gi, "<Gift Card>")
	                    .replace(/<Gift Card Title>/gi, "<Gift Card Title>")
	                    .replace(/&lt;Gift Card Title&gt;/gi, "<Gift Card Title>")
	                    .replace(/<Gift Card Name>/gi, "<Gift Card Name>")
	                    .replace(/&lt;Gift Card Name&gt;/gi, "<Gift Card Name>")
	                    .replace(/<Gift Card Value>/gi, "<Gift Card Value>")
	                    .replace(/&lt;Gift Card Value&gt;/gi, "<Gift Card Value>")
	                    .replace(/<Price>/gi, "<Price>")
	                    .replace(/&lt;Price&gt;/gi, "<Price>")
	                    .replace(/<Value>/gi, "<Value>")
	                    .replace(/&lt;Value&gt;/gi, "<Value>")
	                    .replace(/<Sender>/gi, "<Sender>")
	                    .replace(/&lt;Sender&gt;/gi, "<Sender>")
	                    .replace(/<Sender Name>/gi, "<Sender Name>")
	                    .replace(/&lt;Sender Name&gt;/gi, "<Sender Name>")
	                    .replace(/<Brand>/gi, "<Brand>")
	                    .replace(/&lt;Brand&gt;/gi, "<Brand>")
	                    .replace(/<Brands>/gi, "<Brands>")
	                    .replace(/&lt;Brands&gt;/gi, "<Brands>");
				}

				// Reusable function for replacing placeholders
				function buildDynamicContent(template) {
					if (!template || template.trim() === "") return "";
					return template
						.replace(/<Full Name>/gi, fullName)
						.replace(/&lt;Full Name&gt;/gi, fullName)
	                    .replace(/<First Name>/gi, first_name)
	                    .replace(/&lt;First Name&gt;/gi, first_name)
	                    .replace(/<Surname>/gi, lastName)
	                    .replace(/&lt;Surname&gt;/gi, lastName)
	                    .replace(/<Last Name>/gi, lastName)
	                    .replace(/&lt;Last Name&gt;/gi, lastName)
	                    .replace(/<Email>/gi, email)
	                    .replace(/&lt;Email&gt;/gi, email)
	                    .replace(/<Gift Card>/gi, gc_name)
	                    .replace(/&lt;Gift Card&gt;/gi, gc_name)
	                    .replace(/<Gift Card Title>/gi, gc_name)
	                    .replace(/&lt;Gift Card Title&gt;/gi, gc_name)
	                    .replace(/<Gift Card Name>/gi, gc_name)
	                    .replace(/&lt;Gift Card Name&gt;/gi, gc_name)
	                    .replace(/<Gift Card Value>/gi, formattedPrice)
	                    .replace(/&lt;Gift Card Value&gt;/gi, formattedPrice)
	                    .replace(/<Price>/gi, formattedPrice)
	                    .replace(/&lt;Price&gt;/gi, formattedPrice)
	                    .replace(/<Value>/gi, formattedPrice)
	                    .replace(/&lt;Value&gt;/gi, formattedPrice)
	                    .replace(/<Sender>/gi, sender)
	                    .replace(/&lt;Sender&gt;/gi, sender)
	                    .replace(/<Sender Name>/gi, sender)
	                    .replace(/&lt;Sender Name&gt;/gi, sender)
	                    .replace(/<Brand>/gi, gc_brands)
	                    .replace(/&lt;Brand&gt;/gi, gc_brands)
	                    .replace(/<Brands>/gi, gc_brands)
	                    .replace(/&lt;Brands&gt;/gi, gc_brands);
				}

				// ✅ Normalize first so raw <...> works in HTML
				const message   = buildDynamicContent(normalizePlaceholders(slide.dataset.message));
				const subject   = buildDynamicContent(normalizePlaceholders(slide.dataset.subject));
				const textMsg   = buildDynamicContent(normalizePlaceholders(slide.dataset.textMessage));

				// console.log("Message:", message);
				// console.log("Subject:", subject);
				// console.log("Text Msg:", textMsg);

				// --- For the create message field dynamic END
				const isSelected = slide.querySelector(".gift-card-select")?.checked ? 1 : 0;

				recipientsMap[key].gift_cards.push({
					sku: slide.getAttribute('data-sku') || '',
					title: slide.getAttribute('data-name') || '',
					price: parseFloat(slide.querySelector('.gift-card-price')?.textContent?.replace('$', '').trim() || 0),
					image: slide.querySelector('.gift-card-img')?.src ?? '',
					gift_message: message,
					gift_text_animation: slide.getAttribute('data-text-animation') ?? '',
					gift_email_animation: slide.getAttribute('data-email-animation') ?? '',
					gift_subject: subject,
					gift_text_message: textMsg,
					selected: isSelected
				});
			});
		
			const recipients = Object.values(recipientsMap);

			// ✅ Collect form data (same as next step)
			const form = document.getElementById('bulk-card-activation-form');
			const formData = new FormData(form);
			let cardActivationData = {};
			formData.forEach((value, key) => {
				cardActivationData[key] = value;
			});

			// ✅ Get gift card preview image
			const previewImg = document.querySelector('.selected-design-card-preview img');
			if (previewImg && previewImg.src) {
				cardActivationData['gift_card_image'] = previewImg.src;
			}


			const applyPersonalisation = document.getElementById('apply-personalisation');
			cardActivationData['apply_personalisation'] = applyPersonalisation && applyPersonalisation.checked ? 1 : 0;

			console.log('delivery method calling ', recipients); // <-- shows correct values
			e.preventDefault();
		
			jQuery.ajax({
				url: draft_order_ajax.ajax_url,
				method: 'POST',
				data: {
					action: 'save_draft_order_with_recipients',
					nonce: draft_order_ajax.nonce,
					recipients: JSON.stringify(recipients),
					form_data: JSON.stringify(cardActivationData),
					current_step: currentStep,
					personaliseAllCheckbox : personaliseAllCheckbox,
					business_details: JSON.stringify(businessDetails),
					sender_name: jQuery('#sender-name').val(),
					sender_email: jQuery('#sender-email').val(),
					status: status,
					order_id: order_id,
				},
				success: function (response) {
					const messageBox = document.getElementById("save-draft-message-customisation");
					const bulkMessageBox = document.getElementById("save-draft-message-bulk");

					messageBox.classList.remove('success-message', 'error-message');
					bulkMessageBox.classList.remove('success-message', 'error-message');
					if (response.success) {
                                   
						if( response.data.is_update ){
							messageBox.textContent = 'Order #'+response.data.order_id+' updated successfully...';
							bulkMessageBox.textContent = 'Order #'+response.data.order_id+' updated successfully...';
						}else{
							messageBox.textContent = 'Order #'+response.data.order_id+'  created successfully...';                                        
							bulkMessageBox.textContent = 'Order #'+response.data.order_id+'  created successfully...';                                        
						}
						
						bulkMessageBox.classList.add('success-message');
						bulkMessageBox.style.display = "block";

						messageBox.classList.add('success-message');
						messageBox.style.display = "block";

						const newOrderId = response.data.order_id;
						const ordrUpdate = response.data.is_update;

						btn.setAttribute('data-order-id', newOrderId);
						if (ordrUpdate === false) {
							btn.setAttribute('data-status', 'update');
						}
						// const saveBtn = document.getElementById('create-order-save-btn');
						// if (saveBtn) {
						// 	saveBtn.setAttribute('data-order_id', newOrderId);
						// }

						['create-order-save-btn', 'customisation-save-btn', 'place-order-btn'].forEach(id => {
							const el = document.getElementById(id);
							if (el) el.setAttribute('data-order-id', newOrderId);
							el.setAttribute('data-status', 'update');
						});

						// ✅ Also update the Save Draft button
						const saveDraftBtn = document.getElementById('save-draft-bulk-card-activation');
						if (saveDraftBtn) {
							saveDraftBtn.setAttribute('data-order-id', newOrderId);
							if (ordrUpdate === false) {
								saveDraftBtn.setAttribute('data-status', 'update');
							}
						}


						setTimeout(() => {
							messageBox.classList.remove('success-message', 'error-message');
							messageBox.textContent = "";
							bulkMessageBox.classList.remove('success-message', 'error-message');
							bulkMessageBox.textContent = "";
							// saveDraftMessage.classList.remove('success-message', 'error-message');
							// saveDraftMessage.textContent = "";
							// let saveDraftMessage = document.getElementById('save-draft-message-bulk');
							jQuery('#save-draft-message-bulk').hide();

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
						messageBox.textContent = 'Error: ' + response.data.success;
						messageBox.classList.add('error-message');
						messageBox.style.display = "block";

						bulkMessageBox.textContent = 'Error: ' + response.data.success;
						bulkMessageBox.classList.add('error-message');
						bulkMessageBox.style.display = "block";
						
						if (btn && btn.id === 'delivery-save-btn') {
							btn.disabled = false;
							btn.removeAttribute('aria-busy');
							btn.classList.remove('btn-disabled');

							nextStepBtn.disabled = false;
							nextStepBtn.removeAttribute('aria-busy');
							nextStepBtn.classList.remove('btn-disabled');
						}
					}
				},
				error: function () {
					const messageBox = document.getElementById("save-draft-message-customisation");
					const bulkMessageBox = document.getElementById("save-draft-message-bulk");
				
					messageBox.classList.remove('success-message', 'error-message');
					bulkMessageBox.classList.remove('success-message', 'error-message');
				
					messageBox.textContent = 'Unexpected error occurred.';
					messageBox.classList.add('error-message');
					messageBox.style.display = "block";
				
					bulkMessageBox.textContent = 'Unexpected error occurred.';
					bulkMessageBox.classList.add('error-message');
					bulkMessageBox.style.display = "block"; 
					// re-enable on AJAX transport failure
					if (btn && btn.id === 'delivery-save-btn') {
						btn.disabled = false;
						btn.removeAttribute('aria-busy');
						btn.classList.remove('btn-disabled');

						nextStepBtn.disabled = false;
						nextStepBtn.removeAttribute('aria-busy');
						nextStepBtn.classList.remove('btn-disabled');
					}
				}
			});
			// console.log('giftCardData ...: ',recipients);
			e.preventDefault();
			return;
		}
		
		
		$(".customisation-container").hide();
		$(".delivery-method-container").show();

		updateFieldsToggle();
		updateScheduleDelivery();
	}

	// Preview button clicked Start Here
	$('#customisation-preview-btn').on('click', function (e) {
		e.preventDefault();

		let previewHtml = '<div class="gift-card-preview-grid">';

		$('.gift-card-slider .gift-card-slide').each(function () {
			const $slide = $(this);
			const image = $slide.find('img').attr('src');
			const price = $slide.find('.gift-card-price').text();
			const name = $slide.data('name');
			const firstName = $slide.data('first-name');
			const surname = $slide.data('surname');
			const email = $slide.data('email');
			const deliveryMethod = $slide.data('delivery-method');

			previewHtml += `
				<div class="gift-card-preview-item" style="border: 1px solid #ddd; margin-bottom: 15px; padding: 10px; border-radius: 5px;">
					<img src="${image}" alt="Gift Card Image" style="max-width: 120px; display: block; margin-bottom: 10px;">
					<p><strong>Recipient:</strong> ${firstName} ${surname}</p>
					<p><strong>Email:</strong> ${email}</p>
					<p><strong>Title:</strong> ${name}</p>
					<p><strong>Price:</strong> ${price}</p>
					<p><strong>Delivery Method:</strong> ${deliveryMethod}</p>
				</div>
			`;
		});

		previewHtml += '</div>';
		$('.gift-card-preview-content').html(previewHtml);
		$('#gift-card-preview-modal').fadeIn();
	});

	// Preview button clicked End Here

	// Close modal
	$(document).on('click', '.close-modal', function () {
		$('#gift-card-preview-modal').fadeOut();
	});


	// Close popup
	$(document).on('click', '.close-modal', function () {
		$('#customisation-preview-modal').fadeOut();
	});

	function updateFieldsToggle() {
		setTimeout(() => {
			const backButton = document.getElementById("back-to-recipient-form");
			if (backButton) {
				// console.log('inside backButton');
				backButton.setAttribute("id", "back-to-customisation");
			}

			const activeStep = document.querySelector(".step.active-step");
			if (activeStep) {
				// console.log('----'+jQuery(activeStep).text());
				//jQuery('.activestep').addClass("back-to-delivery-step");
				// console.log('inside active');
				activeStep.classList.remove("active-step");
			}

			const deliveryStep = document.querySelector(".step-indicator .step:nth-child(3)");
			if (deliveryStep) {
				// console.log('inside deliveryStep');
				deliveryStep.classList.add("active-step");
				deliveryStep.classList.add("back-to-delivery-step");
			}

			// const emailToggle = document.querySelector("#email-toggle");
			// if (emailToggle) {
			// 	emailToggle.checked = true;
			// 	emailToggle.dispatchEvent(new Event("change"));
			// }
			updateDeliveryToggleAvailability();
		}, 10); // Slight delay

	}

	const saveBtn = document.getElementById("delivery-save-btn");
	const nextBtn = document.getElementById("delivery-next-btn");


	[nextBtn, saveBtn].forEach(button => {
		if (button) {
			button.addEventListener("click", function (e) {
				
				handleCustomisationAction(e, false);
				// console.log('Next button clicked');
				if ($('input.bulk-order-flow[name="bulk_order_flow"]').length > 0) {
					const backButton = document.getElementById("back-to-order-form");
					if (backButton) {
						// console.log('inside backButton---------');
						backButton.setAttribute("id", "back-to-customisation");
					}
				
					setTimeout(() => {
						// console.log('I am Called');
						$('#back-to-customisation').show();
					}, 1);
			}	
			});
		}
	});

	// Skip button handler
	const skipBtn = document.getElementById("customisation-skip-btn");
	if (skipBtn) {
		skipBtn.addEventListener("click", function (e) {
			handleCustomisationAction(e, true);
			if ($('input.bulk-order-flow[name="bulk_order_flow"]').length > 0) {
				const backButton = document.getElementById("back-to-order-form");
				if (backButton) {
					backButton.setAttribute("id", "back-to-customisation");
				}
			
				setTimeout(() => {
					// console.log('I am Called');
					$('#back-to-customisation').show();
				}, 1000);
			}			
		});
	}

	$(document).on("click", "#back-to-customisation, .back-to-customisation", function () {
		/**/
        jQuery('#order-summary-container').hide();
        jQuery("#back-to-delivery-step").hide();
		$('#apply-schedule-checkbox').prop('checked', false).trigger('change');
        
        jQuery('#payment-confirmation-container').hide();
        jQuery('#back-to-order-summary').hide();

        document.querySelectorAll(".table-container, .gift-card-container, #save-and-next-btn").forEach(el => {
            if (el) el.setAttribute("style", "display: none !important;");
        });

        /**/

		$('#multi-step-form').removeClass('d-none');
		$('#new-order-form').hide();
		$('.delivery-method-container').hide();
		$('.customisation-container').show();
		$(".customisation-container").each(function () {
			$(this).removeAttr("style");
		});

		$('.change__back_status').attr("id", "back-to-recipient-form").show();
		if ($('input.bulk-order-flow[name="bulk_order_flow"]').length > 0) {
			
			// console.log('inside backToRecipientForm---------');
			$('#back-to-recipient-form').hide();		
			// setTimeout(() => {
			// 	console.log('I am Called');
			// 	$('#back-to-customisation').show();
			// }, 1000);
		}
		
		// Remove active-step from the current step
		const activeStep = document.querySelector(".step.active-step");
		if (activeStep) {
			activeStep.classList.remove("active-step");
		}

		// Add active-step back to the Customization step (2nd step)
		const customizationStep = document.querySelector(".step-indicator .step:nth-child(2)");
		if (customizationStep) {
			customizationStep.classList.add("active-step");
		}

		//jQuery('.step.back-to-recipient-form').removeClass('back-to-recipient-form');
        //jQuery('.step.back-to-customisation').removeClass('back-to-customisation');
        jQuery('.step.back-to-delivery-step').removeClass('back-to-delivery-step');
        jQuery('.step.back-to-order-summary').removeClass('back-to-order-summary');
	});

	//Toggle the delivery methods
	const emailToggle = document.getElementById("email-toggle");
	const smsToggle = document.getElementById("sms-toggle");
	const downloadListToggle = document.getElementById("download-list-toggle");
	const triggerClientToggle = document.getElementById("trigger-client-toggle");

	/**
	 * Disable Email/SMS toggles when there is no recipient email/phone entered.
	 * - Email toggle disabled if no recipient has an email
	 * - SMS toggle disabled if no recipient has a phone
	 *
	 * Sources:
	 * - Recipient table inputs (when present): .recipient-email / .recipient-phone
	 * - Slides (used throughout this file): .gift-card-slide data-email / data-phone
	 */
	function updateDeliveryToggleAvailability() {
		// If toggles aren't on this page/step, bail safely.
		if (!emailToggle && !smsToggle) return;

		const normalize = (v) => (typeof v === 'string' ? v.trim() : '');
		const isRealEmail = (v) => {
			const s = normalize(v);
			if (!s || s === '-' || s.toLowerCase() === 'n/a') return false;
			return true;
		};
		const isRealPhone = (v) => {
			const s = normalize(v);
			if (!s || s === '-' || s.toLowerCase() === 'n/a') return false;
			// Some flows were defaulting phone to 000000000; treat that as "not provided".
			if (/^0{6,}$/.test(s.replace(/\s+/g, ''))) return false;
			return true;
		};

		let hasAnyEmail = false;
		let hasAnyPhone = false;

		// 1) Prefer live inputs if present (recipient step / editable table)
		const emailInputs = document.querySelectorAll('.recipient-email');
		const phoneInputs = document.querySelectorAll('.recipient-phone');
		emailInputs.forEach((el) => {
			if (hasAnyEmail) return;
			hasAnyEmail = isRealEmail(el.value);
		});
		phoneInputs.forEach((el) => {
			if (hasAnyPhone) return;
			hasAnyPhone = isRealPhone(el.value);
		});

		// 2) Fall back to slide data attributes (delivery step / summary)
		if (!hasAnyEmail || !hasAnyPhone) {
			document.querySelectorAll('.gift-card-slide').forEach((slide) => {
				if (!hasAnyEmail) hasAnyEmail = isRealEmail(slide.getAttribute('data-email') || slide.dataset.email);
				if (!hasAnyPhone) hasAnyPhone = isRealPhone(slide.getAttribute('data-phone') || slide.dataset.phone);
			});
		}

		// Apply disable + ensure state is consistent
		if (emailToggle) {
			const shouldDisableEmail = !hasAnyEmail;
			emailToggle.disabled = shouldDisableEmail;
			emailToggle.setAttribute('aria-disabled', shouldDisableEmail ? 'true' : 'false');
			emailToggle.classList.toggle('disabled', shouldDisableEmail);
			emailToggle.closest('.toggle-switch')?.classList.toggle('disabled', shouldDisableEmail);
			if (shouldDisableEmail && emailToggle.checked) {
				emailToggle.checked = false;
				emailToggle.dispatchEvent(new Event('change', { bubbles: true }));
			}
		}

		if (smsToggle) {
			const shouldDisableSms = !hasAnyPhone;
			smsToggle.disabled = shouldDisableSms;
			smsToggle.setAttribute('aria-disabled', shouldDisableSms ? 'true' : 'false');
			smsToggle.classList.toggle('disabled', shouldDisableSms);
			smsToggle.closest('.toggle-switch')?.classList.toggle('disabled', shouldDisableSms);
			if (shouldDisableSms && smsToggle.checked) {
				smsToggle.checked = false;
				smsToggle.dispatchEvent(new Event('change', { bubbles: true }));
			}
		}
	}

	function handleToggle(event) {
		if (event.target.checked) {
			if (event.target === downloadListToggle || event.target === triggerClientToggle) {
				// If "Download List" or "Trigger Client Send" is checked, uncheck Email and SMS
				if (emailToggle) emailToggle.checked = false;
				if (smsToggle) smsToggle.checked = false;
			} else {
				// If Email or SMS is checked, uncheck "Download List" and "Trigger Client Send"
				if (downloadListToggle) downloadListToggle.checked = false;
				if (triggerClientToggle) triggerClientToggle.checked = false;
			}
		}
	}

	if (emailToggle) emailToggle.addEventListener("change", handleToggle);
	if (smsToggle) smsToggle.addEventListener("change", handleToggle);
	if (downloadListToggle) downloadListToggle.addEventListener("change", handleToggle);
	if (triggerClientToggle) triggerClientToggle.addEventListener("change", handleToggle);

	// Keep toggle availability synced as recipients are edited / steps change.
	updateDeliveryToggleAvailability();
	$(document).on('input change', '.recipient-email, .recipient-phone', function () {
		updateDeliveryToggleAvailability();
	});
	$(document.body).on('updated_checkout updated_wc_div', function () {
		updateDeliveryToggleAvailability();
	});

	// Order Summaryb tab
	let orderSummaryTab = document.querySelector('.order-summary-tab-top');
	let orderSummarycontent = document.querySelector('.order-summary-tab-content');
	if (orderSummaryTab) {
		orderSummaryTab.addEventListener('click', () => {
			orderSummarycontent.classList.toggle('active');
			orderSummaryTab.classList.toggle('active');
		});
	}

	// Fetch the gift card data and add it in schedule delivery
	function updateScheduleDelivery() {
		let scheduleData = {};

		// Iterate through gift card slides and group them by email
		// Iterate through gift card slides and group them by email
		const emailToggleChecked = document.querySelector('#email-toggle:checked') !== null;
		// console.log('emailToggleChecked',emailToggleChecked);
		const smsToggleChecked = document.querySelector('#sms-toggle:checked') !== null;
		$(".gift-card-slider .gift-card-slide").each(function () {

			const $slide = $(this);
			const email = $slide.data("email")?.trim();
  			
			const firstName = ($slide.data("first-name") || '').trim();
		  	const surname = ($slide.data("surname") || '');
		  	const fullName = `${firstName} ${surname}`.trim();
			
			const message = ($slide.data("message")).trim().replace(/"/g, '\'');
			// console.log('message',message);

			const price = $slide.find(".gift-card-price").text().trim();;
			
			const sku = $slide.attr('data-sku')?.trim() || '';

			const imageEl = $slide.find(".gift-card-img").attr('src');
			const imageUrl = imageEl ? imageEl : '';

			// Determine delivery method
			// let deliveryMethod = $slide.data("deliveryMethod")?.trim();
			let deliveryMethod = $slide.attr('data-delivery-method')?.trim();

			// console.log('deliveryMethod...',deliveryMethod);
			if (!deliveryMethod) {
				deliveryMethod = getDeliveryMethodFromToggle();
				$slide.attr('data-delivery-method', deliveryMethod);
			}

			const phone = $slide.data("phone") || '000000000';
			const giftCardName = $slide.data("name") || "Gift Card";

			/*
			const sku = $slide.data("sku") || "";
			const email = $slide.data("email") || "N/A";
			const price = $slide.find(".gift-card-price").text().trim();
			const imageUrl = $slide.find("img").attr("src") || "";
			const deliveryMethod = $slide.data("delivery-method") || "Email"; // Get from data attribute*/
			
			// console.log('firstName is a a-----',firstName);
			// console.log('Full name is a a-----',fullName);
			// console.log('surname is a a-----',surname);
			// console.log('sku is a a-----',sku);
			// console.log('email is a a-----',email);
			// console.log('price is a a-----',price);
			// console.log('giftCardName is a a-----',giftCardName);
			// console.log('imageUrl is a a-----',imageUrl);
			// console.log('deliveryMethod is a a-----',deliveryMethod);


			// Store the image and delivery method in the data attribute
			$slide.attr({
				"data-image": imageUrl,
				"data-delivery-method": deliveryMethod
			});

			// Group by email
			if (!scheduleData[email]) {
				scheduleData[email] = {
					firstName: firstName,
					surname: surname,
					recipient: `${firstName} ${surname}`,
					contact: email,
					giftCards: [],
					phone: phone,
					deliveryMethod: deliveryMethod // Store delivery method
				};
			}

			// Add gift card data
			//scheduleData[email].giftCards.push(`${giftCardName} ${price}`);
			scheduleData[email].giftCards.push({
		  		title: `${giftCardName} ${price}`,
		  		name: giftCardName,
	  			sku: sku,
	  			message: message,
		  		price: price,
		  		deliveryMethod: deliveryMethod,
		  		imageUrl: imageUrl
			});
		});


		// Generate HTML table (Displaying only required fields)
		let scheduleHtml = `
			<div class="custom-table-responsive">
			<table class="schedule-delivery-table">
				<thead>
					<tr>
						<th>Recipient</th>
						<th>Contact</th>
						<th>Gift Cards</th>
						<th>Schedule Date/Time</th>
					</tr>
				</thead>
				<tbody>`;

		Object.values(scheduleData).forEach((data, index) => {
			let giftCardsHtml = '';
			let scheduleInputsHtml = '';

			data.giftCards.forEach((gift, giftIndex) => {
				giftCardsHtml += `
					<div class="gift-card-entry gift-card-name-value-wrapper pair-${index}-${giftIndex}" data-sku="${gift.sku}" data-firstname="${data.firstName}" data-surname="${data.surname}" data-fullname="${data.recipient}" data-email="${data.contact}" data-phone="${data.phone}" data-message="${gift.message}" data-name="${gift.name}" data-price="${gift.price}" data-delivery-method="${data.deliveryMethod}" data-image="${gift.imageUrl}">
						${gift.title}
					</div>
					`;

				scheduleInputsHtml += `
							<div class="schedule-entry pair-${index}-${giftIndex}">
								<i class="calendar-icon fa-solid fa-calendar-days" data-recipient-index="${index}" data-gift-index="${giftIndex}" style="cursor: pointer; position:relative; top:15px;"></i>
								<input type="text" class="date-time-picker scheduled_date" id="datepicker-${index}-${giftIndex}"  data-recipient_index="${index}" data-gift_index="${giftIndex}" s style="display: none;">
								<div class="calendar-container" id="calendar-container-${index}-${giftIndex}"></div>
							</div>
							`;
			});

			scheduleHtml += `
						<tr>
							<td>${data.recipient}</td>
							<td>${data.contact}</td>
							<td>${giftCardsHtml}</td>
							<td>${scheduleInputsHtml}</td>
						</tr>
					`;
		});

		scheduleHtml += `</tbody></table> </div>`;

		$(".schedule-content").html(scheduleHtml);
		function syncGiftCardHeights() {
			$(".schedule-delivery-table tbody tr").each(function () {
				const $row = $(this);
				const $giftCards = $row.find(".gift-card-name-value-wrapper");
				const $schedules = $row.find(".schedule-entry");

				$schedules.each(function (i) {
					const $schedule = $(this);
					const height = $schedule.outerHeight();

					const $giftCard = $giftCards.eq(i);
					$giftCard.css({
						"height": height + "px",
						"display": "flex",
						"align-items": "center"
					});
				});
			});
		}

		$(".calendar-icon").each(function () {
			const $icon = $(this);
			const recipientIndex = $icon.data("recipient-index");
			const giftIndex = $icon.data("gift-index");
			const $input = $(`#datepicker-${recipientIndex}-${giftIndex}`);
			const $container = $(`#calendar-container-${recipientIndex}-${giftIndex}`);
			const $entry = $icon.closest(".schedule-entry");
		
			// Avoid duplicate initialization
			if ($input.hasClass("flatpickr-initialized")) return;
		
			const fp = flatpickr($input[0], {
				enableTime: true,
				dateFormat: "Y-m-d h:i K",
				appendTo: $container[0],
				static: true,
				position: "auto",
				minDate: "today",
				onChange: function (selectedDates, dateStr) {
					$entry.find(".selected-datetime").remove();
					$entry.append(`<span class="selected-datetime" style="margin-left: 8px;">${dateStr}</span>`);
		
					const $row = $entry.closest("tr");
					const $giftCardColumn = $row.find("td").eq(2);
					const $giftCardWrapper = $giftCardColumn.find(".gift-card-name-value-wrapper").eq(giftIndex);
					const giftCardHeight = $giftCardWrapper.outerHeight();
					const entryHeight = $entry.outerHeight();
					const maxHeight = Math.max(giftCardHeight, entryHeight);
		
					$giftCardWrapper.css({
						"height": maxHeight + "px",
						"display": "flex",
						"align-items": "center",
					});
					$entry.css({
						"height": maxHeight + "px",
						"display": "flex",
						"align-items": "center",
					});
		
					$(".calendar-icon").css({
						"top": "6px"
					});
		
					fp.close();
				},
			});
		
			$input.addClass("flatpickr-initialized");
		
			$icon.on("click", function (e) {
				e.preventDefault();
				$input[0]._flatpickr.open(); // Always trigger open
			});
		});		
		setTimeout(() => {
			console.log('Sync called');
			syncGiftCardHeights();
		}, 50);
	}
	
	$(document).on("click", ".schedule-delivery-header", function () {
		$(".schedule-delivery-wrapper").toggleClass("open");
		$(".accordion-toggle").html(
			$(".schedule-delivery-wrapper").hasClass("open")
				? '<i class="fa-solid fa-chevron-up"></i>'
				: '<i class="fa-solid fa-chevron-down"></i>'
		);
	});

	function formatDateTime(isoDateTime) {
		const dateObj = new Date(isoDateTime);
		const year = dateObj.getFullYear();
		const month = String(dateObj.getMonth() + 1).padStart(2, '0');
		const day = String(dateObj.getDate()).padStart(2, '0');

		let hours = dateObj.getHours();
		const minutes = String(dateObj.getMinutes()).padStart(2, '0');
		const ampm = hours >= 12 ? 'PM' : 'AM';

		hours = hours % 12;
		hours = hours ? hours : 12; // the hour '0' should be '12'

		return `${year}-${month}-${day} ${hours}:${minutes} ${ampm}`;
	}

	function formatDateToAmPm(datetimeStr) {
		const dateObj = new Date(datetimeStr);
		const year = dateObj.getFullYear();
		const month = String(dateObj.getMonth() + 1).padStart(2, '0');
		const day = String(dateObj.getDate()).padStart(2, '0');
		let hours = dateObj.getHours();
		const minutes = String(dateObj.getMinutes()).padStart(2, '0');
		const ampm = hours >= 12 ? 'PM' : 'AM';
	
		hours = hours % 12 || 12;
	
		return `${year}-${month}-${day} ${hours}:${minutes} ${ampm}`;
	}
	
	$("#apply-schedule-checkbox").on("change", function () {
		const isChecked = $(this).is(":checked");
	
		if (isChecked) {
			$(".schedule-date-container").show();
	
			// Get WordPress server time from hidden field
			const wpNow = $("#wp-current-datetime").val();
			$("#schedule-all-datetime").attr("min", wpNow);
	
			// Scroll to the field
			$('html, body').animate({
				scrollTop: $("#schedule-all-datetime").offset().top - 100
			}, 600);
		} else {
			$(".schedule-date-container").hide();
			$("#schedule-all-datetime").val("");
			$(".schedule-entry .selected-datetime").remove();
			$(".schedule-recipients-text").remove();
		}
	});
	
	$("#schedule-all-datetime").on("change", function () {
		const selectedRaw = $(this).val();
		const wpNow = $("#wp-current-datetime").val();
	
		if (selectedRaw && selectedRaw < wpNow) {
			alert("Please select a date and time in the future.");
			$(this).val("");
			return;
		}
	
		if (selectedRaw) {
			const formatted = formatDateToAmPm(selectedRaw);
	
			$(".schedule-entry").each(function () {
				const $entry = $(this);
				const $input = $entry.find(".date-time-picker");
	
				const fpInstance = $input[0]?._flatpickr;
				if (fpInstance) {
					fpInstance.setDate(selectedRaw, true);
				}
	
				$entry.find(".selected-datetime").remove();
				$entry.append(`<span class="selected-datetime" style="margin-left: 8px;">${formatted}</span>`);
			});
	
			updateScheduleHeader();
		}
	});
	

	// $("#activation-expiry-date").on("change", function () {
	// 	if ($("#apply-schedule-checkbox").is(":checked")) {
	// 		const selectedDate = $(this).val();
	// 		const formattedDate = formatDateTime(selectedDate);

	// 		$(".schedule-entry").each(function () {
	// 			const $entry = $(this);
	// 			$entry.find(".selected-datetime").remove();
	// 			$entry.append(`<span class="selected-datetime">${formattedDate}</span>`);
	// 		});

	// 		updateScheduleHeader();
	// 	}
	// });
});

///Toggle Expiry fields start
var giftExpiryType = document.getElementById("gift_card_expiry_type");
var giftExpiryDateField = document.querySelector(".gift-expiry-date-field");
var giftExpiryDurationField = document.querySelector(".gift-expiry-duration-field");
var giftExpiryDateInput = document.getElementById("gift_card_expiry_date");

var activationExpiryType = document.getElementById("activation_expiry_type");
var activationExpiryDateField = document.getElementById("activation_expiry_date_field");
var activationExpiryPeriodField = document.getElementById("activation_expiry_period_field");
var activationExpiryDateInput = document.getElementById("activation_expiry_date");

function toggleGiftExpiryFields() {
	var selectedValue = giftExpiryType.value;
	giftExpiryDateField.style.display = (selectedValue === "gift_set_date") ? "block" : "none";
	giftExpiryDurationField.style.display = (selectedValue === "expiry_period_starts_on_purchase" || selectedValue === "expiry_period_starts_on_activation") ? "block" : "none";
}

function toggleActivationExpiryFields() {
	var selectedValue = activationExpiryType.value;
	activationExpiryDateField.style.display = (selectedValue === "activation_set_date") ? "block" : "none";
	activationExpiryPeriodField.style.display = (selectedValue === "set_period") ? "block" : "none";
}

function setMinDate(input) {
	var today = new Date().toISOString().split("T")[0];
	input.setAttribute("min", today);
}

if (giftExpiryType) {
	giftExpiryType.addEventListener("change", toggleGiftExpiryFields);
	toggleGiftExpiryFields();
}

if (activationExpiryType) {
	activationExpiryType.addEventListener("change", toggleActivationExpiryFields);
	toggleActivationExpiryFields();
}

if (giftExpiryDateInput) {
	setMinDate(giftExpiryDateInput);
}

if (activationExpiryDateInput) {
	setMinDate(activationExpiryDateInput);
}
const now = new Date();

// Format to 'YYYY-MM-DDTHH:MM'
const year = now.getFullYear();
const month = String(now.getMonth() + 1).padStart(2, '0');
const day = String(now.getDate()).padStart(2, '0');
const hours = String(now.getHours()).padStart(2, '0');
const minutes = String(now.getMinutes()).padStart(2, '0');

const datetimeLocal = `${year}-${month}-${day}T${hours}:${minutes}`;

document.getElementById("activation-expiry-date").setAttribute("min", datetimeLocal);
function updateScheduleHeader() {
	let recipientCount = jQuery(".schedule-delivery-table tbody tr").length;

	jQuery(".schedule-recipients-text").remove();
	jQuery(".schedule-delivery-header .icon-text").append(
		`<span class="schedule-recipients-text">Scheduled Delivery For x${recipientCount} Recipient</span>`
	);
}

function getDeliveryMethodFromToggle() {
	const emailEnabled = document.querySelector('#email-toggle')?.checked;
	const smsEnabled   = document.querySelector('#sms-toggle')?.checked;

	if (emailEnabled && smsEnabled) return 'EMAIL & SMS';
	if (emailEnabled) return 'Email';
	if (smsEnabled) return 'SMS';

	return 'Email'; // fallback
}

function updateSlidesDeliveryMethod() {
	const deliveryMethod = getDeliveryMethodFromToggle();

	jQuery('.gift-card-slider .gift-card-slide').each(function () {
		jQuery(this).attr('data-delivery-method', deliveryMethod);
	});
}
jQuery(document).ready(function ($) {
	// 👇 ADD IT HERE (near other toggle logic)
	jQuery('#email-toggle, #sms-toggle').on('change', function () {
		updateSlidesDeliveryMethod();
		// updateScheduleDelivery();
	});

});

// In delivery.js or customisation.js
jQuery(document).ready(function ($) {

	document.getElementById("continue-step")?.addEventListener("click", function (e) {
		console.log('This is clicked eveny');
		let hasError = false;

		// Clear previous errors
		document.querySelectorAll('.field-error').forEach(el => el.remove());

		let firstErrorEl = null; // Will store the first error to scroll to

		document.getElementById("activation_expiry_type")?.addEventListener("change", function () {
			// Remove any existing error message related to this field
			const nextEl = this.nextElementSibling;
			if (nextEl && nextEl.classList.contains("field-error")) {
				nextEl.remove();
			}
		});
		
		const expiryType = $('#activation_expiry_type').val();

		// Validate Activation Expiry Type
		if (expiryType === 'select_expiry') {
			e.preventDefault();
			hasError = true;

			const errorEl = document.createElement("div");
			errorEl.className = "field-error";
			errorEl.style.color = "red";
			errorEl.style.fontSize = "14px";
			errorEl.style.marginTop = "4px";
			errorEl.textContent = "Please select a valid Activation Expiry Type.";

			document.getElementById("activation_expiry_type").insertAdjacentElement("afterend", errorEl);
			if (!firstErrorEl) firstErrorEl = errorEl;
		}

		// Extra validation based on expiryType selection
		if (expiryType === 'activation_set_date') {
			const dateField = document.getElementById('activation-expiry-date');
			if (!dateField.value) {
				e.preventDefault();
				hasError = true;

				const errorEl = document.createElement("div");
				errorEl.className = "field-error";
				errorEl.style.color = "#ED1E09";
				errorEl.style.fontSize = "14px";
				errorEl.style.marginTop = "4px";
				errorEl.textContent = "Please select an Activation Expiry Date.";

				dateField.insertAdjacentElement("afterend", errorEl);
				if (!firstErrorEl) firstErrorEl = errorEl;
			}
		}

		if (expiryType === 'set_period') {
			const durationField = document.getElementById('activation_expiry_duration');
			const expiryGroup = document.querySelector('.expiry-input-group');
			// console.log('expiryGroup',expiryGroup);
			if (!durationField.value || parseInt(durationField.value) <= 0) {
				e.preventDefault();
				hasError = true;

				const errorEl = document.createElement("div");
				errorEl.className = "field-error";
				errorEl.style.color = "#ED1E09";
				errorEl.style.fontSize = "14px";
				errorEl.style.marginTop = "4px";
				errorEl.textContent = "Please enter a valid Activation Expiry Duration.";

				// durationField.insertAdjacentElement("afterend", errorEl);
				expiryGroup?.after(errorEl);
				if (!firstErrorEl) firstErrorEl = errorEl;
			}
		}

		// Validate Delivery Options
		const isEmail = document.getElementById("email-toggle").checked;
		const isSms = document.getElementById("sms-toggle").checked;
		const isDownload = document.getElementById("download-list-toggle").checked;
		const isTrigger = document.getElementById("trigger-client-toggle").checked;

		const isDeliverySelected = isEmail || isSms || isDownload || isTrigger;

		if (!isDeliverySelected) {
			e.preventDefault();
			hasError = true;

			const deliverySection = document.getElementById("delivery-options-error");

			const errorEl = document.createElement("div");
			errorEl.className = "field-error";
			errorEl.style.color = "#ED1E09";
			errorEl.style.fontSize = "14px";
			errorEl.style.marginTop = "10px";
			errorEl.textContent = "Please select at least one delivery option.";

			deliverySection.insertAdjacentElement("beforeend", errorEl);
			if (!firstErrorEl) firstErrorEl = errorEl;

		}
		if (firstErrorEl) {
			firstErrorEl.scrollIntoView({ behavior: "smooth", block: "center" });
			return;
		}



		// Proceed to next step
		$('#back-to-order-form').addClass('d-none');
		$('#back-to-customisation').hide();
		document.getElementById("delivery-method-container").style.display = "none";
		document.getElementById("order-summary-container").style.display = "block";
		const backToDeliveryBtn = document.getElementById("back-to-delivery-step");
		backToDeliveryBtn.style.display = "block";
		backToDeliveryBtn.setAttribute("disabled", "disabled");
		jQuery('.change__back_status').hide();

		updateStepIndicator(3);
		jQuery('#multi-step-form .step.active-step').addClass('back-to-order-summary');
		populateOrderSummary();
		// deliveryFunc();
	});

	// Remove error on change for activation type
	document.getElementById("activation_expiry_type")?.addEventListener("change", function () {
		const error = this.nextElementSibling;
		if (error && error.classList.contains("field-error")) {
			error.remove();
		}
	});

	// Remove error on change for expiry date
	document.getElementById("activation-expiry-date")?.addEventListener("input", function () {
		const error = this.nextElementSibling;
		if (error && error.classList.contains("field-error")) {
			error.remove();
		}
	});

	// Remove error on input for expiry duration
	document.getElementById("activation_expiry_duration")?.addEventListener("input", function () {
		const expiryGroup = document.querySelector('.expiry-input-group');
		const nextEl = expiryGroup?.nextElementSibling;
		if (nextEl && nextEl.classList.contains("field-error")) {
			nextEl.remove();
		}
	});

	// Remove error for delivery option on toggle
	["email-toggle", "sms-toggle", "download-list-toggle", "trigger-client-toggle"].forEach(id => {
		document.getElementById(id)?.addEventListener("change", () => {
			document.querySelector("#delivery-options-error .field-error")?.remove();
		});
	});

	///Toggle Expiry fields end
	// Back button handler
	const backToDeliveryBtn = document.getElementById("back-to-delivery-step");
	if (backToDeliveryBtn) backToDeliveryBtn.addEventListener("click", function () {
		$('#back-to-customisation').show();
		$('#order-summary-container').hide();
		document.getElementById("order-summary-container").style.display = "none";
		document.getElementById("back-to-delivery-step").style.display = "none";

		document.getElementById("delivery-method-container").style.display = "block";
		$('#back-to-order-form').removeClass('d-none');
		updateStepIndicator(2); // Return to Delivery step

		//jQuery('.step.back-to-recipient-form').removeClass('back-to-recipient-form');
        //jQuery('.step.back-to-customisation').removeClass('back-to-customisation');
        //jQuery('.step.back-to-delivery-step').removeClass('back-to-delivery-step');
        jQuery('.step.back-to-order-summary').removeClass('back-to-order-summary');
	});

	jQuery(document).on("click", ".back-to-delivery-step", function () {
		/**/
		jQuery('#payment-confirmation-container').hide();
        jQuery('#back-to-order-summary').hide();

        document.querySelectorAll(".table-container, .gift-card-container, #save-and-next-btn").forEach(el => {
            if (el) el.setAttribute("style", "display: none !important;");
        });
		/**/

		jQuery('.change__back_status').attr("id", "back-to-customisation").show();
		jQuery('#order-summary-container').hide();
		jQuery("#back-to-delivery-step").hide();
        jQuery('.customisation-container').hide();

		jQuery("#delivery-method-container").show();
		jQuery('#back-to-order-form').removeClass('d-none');
		updateStepIndicator(2); // Return to Delivery step

		//jQuery('.step.back-to-recipient-form').removeClass('back-to-recipient-form');
        //jQuery('.step.back-to-customisation').removeClass('back-to-customisation');
        //jQuery('.step.back-to-delivery-step').removeClass('back-to-delivery-step');
        jQuery('.step.back-to-order-summary').removeClass('back-to-order-summary');
	});

	function updateStepIndicator(stepIndex) {
		document.querySelectorAll(".step-indicator .step").forEach((step, index) => {
			step.classList.toggle("active-step", index === stepIndex);
		});
	}

	// Add address book code START Here

	$('#add-to-address-book').on('click', function () {
		const businessUserId = $('#business-user-dropdown').val(); // ID
		const businessUserName = $('#business-user-dropdown option:selected').text().trim(); // Name

		if (!businessUserId) {
			alert('Please select a business user first.');
			return;
		}

		let recipients = [];

		$('.order-summary-table tbody tr').each(function () {
			const cells = $(this).find('td');

			const recipient = $(cells[0]).text().trim();
			const contact = $(cells[1]).text().trim();

			// Basic check to avoid pushing empty values
			if (recipient && contact) {
				recipients.push({
					recipient: recipient,
					contact: contact,
				});
			}
		});

		// console.log('recipients', recipients);

		$.ajax({
			url: delivery_ajax.ajax_url,
			method: 'POST',
			data: {
				action: 'save_recipients_to_user_acf',
				user_id: businessUserId,
				business_name: businessUserName,
				recipients: JSON.stringify(recipients)
			},
			success: function (response) {
				if (!response.success) {
					$('.success-add-address-book')
						.html(response.data.message)
						.removeClass('text-success')
						.addClass('text-danger');
					return;
				}
			
				let message = response.data.message;
			
				if (response.data.created.length > 0) {
					message += `<br><strong>Created:</strong> ${response.data.created.join(', ')}`;
				}
				if (response.data.skipped.length > 0) {
					message += `<br><strong>Skipped:</strong> ${response.data.skipped.join(', ')}`;
				}
			
				$('.success-add-address-book')
					.html(message)
					.removeClass('text-danger')
					.addClass('text-success');
			},					
			error: function () {
				$('.success-add-address-book')
					.html('Error saving recipients.')
					.removeClass('text-success')
					.addClass('text-danger');
			}
		});
		// console.log('recipients ----',recipients);
	});

	// Add address book code END Here
	async function populateOrderSummary() {
		console.log('populateOrderSummary.... is called',populateOrderSummary);
		const orderSummaryBody = document.getElementById("order-summary-body");
		const orderSummaryTotals = document.getElementById("order-summary-totals");
		const confirmButton = document.getElementById("confirm-to-payment");

		// Disable confirm button while loading
		confirmButton.disabled = true;
		confirmButton.textContent = "Loading...";

		orderSummaryBody.innerHTML = "<tr><td colspan='9'>Loading...</td></tr>";
		orderSummaryTotals.innerHTML = "";

		const recipients = {};
		let orderSubtotal = 0;
		let fulfillmentTotal = 0;
		let deliveryTotal = 0;
		let gstTotal = 0;
		let discountTotal = 0;

		const giftCardSlides = document.querySelectorAll(".gift-card-slide");
		if (giftCardSlides.length === 0) {
			orderSummaryBody.innerHTML = "<tr><td colspan='9'>No gift cards found.</td></tr>";
			confirmButton.disabled = false;
			confirmButton.textContent = "Continue to Payment";
			return;
		}

		const productDataPromises = Array.from(giftCardSlides).map(slide =>
			getProductMetaData(slide.dataset.sku)
		);

		const allProductData = await Promise.all(productDataPromises);

		function getSelectedDeliveryMethod() {
			if (document.getElementById('sms-toggle')?.checked) return 'SMS';
			if (document.getElementById('download-list-toggle')?.checked) return 'Download List';
			if (document.getElementById('trigger-client-toggle')?.checked) return 'Trigger Client Send';
			return 'Email';
		}

		giftCardSlides.forEach((slide, index) => {
			const productData = allProductData[index];

			const email = slide.dataset.email;
			const gc_name = slide.dataset.name;
			const denominationType = slide.dataset.denomination;
			const firstName = slide.dataset.firstName || "";
			const lastName = slide.dataset.surname || "";
			const brands = slide.dataset.brands || "";
			let price = parseFloat(slide.querySelector(".gift-card-price")
				.textContent.replace(/[^\d.-]/g, ''));
			let discount = parseFloat(slide.dataset.discount);
			// if (isNaN(discount) || discount <= 0 || discount > price) {
			// 	discount = price;
			// }
			// console.log('price>>>,',price);
			// console.log('denominationType>>>,',denominationType);
			let message = slide.dataset.message?.trim() || "-";
			const fullName = `${firstName} ${lastName}`.trim();
			const sender = document.getElementById("select-sender-dropdown")?.value || "";

			// Placeholder replace
			if (message !== "-") {
				message = message
					.replace(/<Full Name>|&lt;Full Name&gt;/g, fullName)
					.replace(/<First Name>|&lt;First Name&gt;/g, firstName)
					.replace(/<Surname>|<Last Name>|&lt;Surname&gt;|&lt;Last Name&gt;/g, lastName)
					.replace(/<Email>|&lt;Email&gt;/g, email)
					.replace(/<Gift Card( Name| Title)?>|&lt;Gift Card( Name| Title)?&gt;/g, gc_name)
					.replace(/<Sender( Name)?>|&lt;Sender( Name)?&gt;/g, sender)
					.replace(/<Brand(s)?>|&lt;Brand(s)?&gt;/g, brands)
					.replace(/<Price>|<Value>|<Gift Card Value>|&lt;Price&gt;|&lt;Value&gt;|&lt;Gift Card Value&gt;/g,
						"$" + price.toFixed(2)
					);
			}

			const deliveryMethod = getSelectedDeliveryMethod();
			console.log('deliveryMethod.....',deliveryMethod);

			if (!recipients[email]) {
				recipients[email] = {
					name: fullName,
					email,
					sender,
					products: [],
					recipientTotal: 0
				};
			}

			const fulfillment = parseFloat(productData.fulfillment) || 0;
			const delivery = parseFloat(productData.delivery) || 0;
			const gst = parseFloat(productData.gst) || 0;
			console.log('price>>>',price);
			let finalPrice = price;
			let appliedDiscount = 0;

			// console.log('denominationType...',denominationType);
			// console.log('productData...',productData);
			// ********* CORE LOGIC *********
			if (denominationType == "variable" && !isNaN(discount) && discount < price) {
				// Variable product → discount allowed
				// appliedDiscount = discount;
				appliedDiscount = price - discount;
				// finalPrice = discount;
				finalPrice = discount > 0 ? discount : price;
				console.log("price..", price);
				console.log("discount..", discount);
				console.log("appliedDiscount..", appliedDiscount);
				console.log("finalPrice..", finalPrice);
			}

			// Add to totals
			const productTotal = finalPrice + fulfillment + delivery;

			fulfillmentTotal += fulfillment;
			deliveryTotal += delivery;
			gstTotal += gst;
			orderSubtotal += productTotal;
			discountTotal += appliedDiscount;

			recipients[email].products.push({
				image: slide.querySelector(".gift-card-img").src,
				price: finalPrice,
				originalPrice: price,
				discount: appliedDiscount,
				gst,
				fulfillment,
				delivery,
				message,
				deliveryMethod,
				productTotal
			});
			// console.log("PUSHED PRODUCT →", recipients[email].products[recipients[email].products.length - 1]);

			recipients[email].recipientTotal += productTotal;
		});

		// Build UI table
		orderSummaryBody.innerHTML = "";

		for (const [email, data] of Object.entries(recipients)) {
			const giftCards = [];
			const messages = [];
			const deliveryMethods = [];
			const fulfillments = [];
			const deliveries = [];
			const prices = [];

			data.products.forEach(product => {
				giftCards.push(`
					<div class="gift-card-entry image-block-table">
						<img src="${product.image}" alt="Gift"> $${product.price.toFixed(2)}
					</div>
				`);
				console.log('abcd', product);
				messages.push(`<div class="gift-card-entry">${product.message}</div>`);
				deliveryMethods.push(`<div class="gift-card-entry">${product.deliveryMethod}</div>`);
				fulfillments.push(`<div class="gift-card-entry">$${product.fulfillment.toFixed(2)}</div>`);
				deliveries.push(`<div class="gift-card-entry">$${product.delivery.toFixed(2)}</div>`);
				prices.push(`<div class="gift-card-entry">$${product.price.toFixed(2)}</div>`);
			});

			orderSummaryBody.innerHTML += `
				<tr>
					<td>${data.name}</td>
					<td>${data.email}</td>
					<td>${data.sender}</td>
					<td>${giftCards.join("")}</td>
					<td>${messages.join("")}</td>
					<td>${deliveryMethods.join("")}</td>
					<td>${fulfillments.join("")}</td>
					<td>${deliveries.join("")}</td>
					<td><strong>$${data.recipientTotal.toFixed(2)}</strong></td>
				</tr>
			`;
		}

		const discountedSubtotal = orderSubtotal; // Already after discount
		const orderTotal = discountedSubtotal + gstTotal;

		orderSummaryTotals.innerHTML = `
			<tr class="summary-discount">
				<td colspan="6" class="text-right left">Discount Total</td>
				<td colspan="3" class="text-right right">$${discountTotal.toFixed(2)}</td>
			</tr>

			<tr class="summary-subtotal">
				<td colspan="6" class="text-right left">
					<p>Subtotal</p>
					<span>GST</span>
				</td>
				<td colspan="3" class="text-right right" id="order-subtotal">
					<p class="subtotal-price">$${discountedSubtotal.toFixed(2)}</p>
					<span class="gst-price">$${gstTotal.toFixed(2)}</span>
				</td>
			</tr>

			<tr class="summary-fulfillment">
				<td colspan="6" class="text-right left">Fulfillment Total</td>
				<td colspan="3" class="text-right right" id="fulfillment-total">$${fulfillmentTotal.toFixed(2)}</td>
			</tr>

			<tr class="summary-delivery">
				<td colspan="6" class="text-right left">Delivery Total</td>
				<td colspan="3" class="text-right right" id="delivery-total">$${deliveryTotal.toFixed(2)}</td>
			</tr>

			<tr class="summary-grand-total">
				<td colspan="6" class="text-right left">TOTAL</td>
				<td colspan="3" class="text-right right summary-grand-order-total">$${orderTotal.toFixed(2)}</td>
			</tr>
		`;

		confirmButton.disabled = false;
		confirmButton.textContent = "Continue to Payment";
	}


	// Updated getProductMetaData function
	async function getProductMetaData(sku) {
		try {
			const response = await jQuery.ajax({
				url: delivery_ajax.ajax_url,
				method: 'POST',
				data: {
					action: 'get_product_meta',
					sku: sku,
					security: delivery_ajax.nonce
				}
			});

			if (response.success) {
				return {
					fulfillment: response.data.fulfillment,
					delivery: response.data.delivery,
					gst: response.data.gst
				};
			} else {
				console.error('Product meta error:', response.data.message);
				return { fulfillment: 0, delivery: 0 };
			}
		} catch (error) {
			console.error('AJAX error:', error.responseText);
			return { fulfillment: 0, delivery: 0 };
		}
	}

	function addTabClass(){
			setTimeout(() => {
				jQuery("#credit-tab").trigger("click");
			}, 50);
			$(document).on('click', '#paymentTabs button', function () {
			var target = $(this).data('bs-target');
			var $button = $('#place-order-btn');
	
			$button.removeClass('client-billing bank-transfer credit');
	
			if (target === '#client') {
				$button.addClass('client-billing');
				$button.attr('data-order-type', 'client-billing');
			} else if (target === '#bank') {
				$button.addClass('bank-transfer');
				$button.attr('data-order-type', 'bank-transfer');
			} else if (target === '#prepaid') {
				$button.addClass('prepaid');
				$button.attr('data-order-type', 'prepaid');
			} else if (target === '#credit') {
				$button.addClass('credit');
				$button.attr('data-order-type', 'credit');
			}
		});
	}
	// addTabClass();
	document.getElementById("confirm-to-payment")?.addEventListener("click", function () {
		console.log('Confirm TO PAYMENT CALL');
		$('#back-to-order-form').addClass('d-none');
		document.getElementById("back-to-order-summary").style.display = "block";

		document.getElementById("order-summary-container").style.display = "none";
		document.getElementById("back-to-delivery-step").style.display = "none";

		document.getElementById("payment-confirmation-container").style.display = "block";
		addTabClass();

		setTimeout(updateFloatBalance, 200);
		setTimeout(updatePrepaidRemainingBalance, 200); 
		waitForOrderValuesAndUpdateRemaining();        
		updateStepIndicator(4);
		transferOrderTotals();
		updatePrepaidTotals();
	});

	function updateFloatBalance() {
		// console.log('inside updateFloatBalance()');
	
		const businessUserDropdown = document.getElementById('business-user-dropdown');
		const selectedBusinessUserId = businessUserDropdown?.value;
	
		// console.log('Selected Business User ID:', selectedBusinessUserId);
	
		if (!selectedBusinessUserId) {
			console.warn('No business user selected.');
			return;
		}
	
		fetch(`/wp-admin/admin-ajax.php?action=get_business_user_balance&user_id=${selectedBusinessUserId}`)
			.then(response => response.json())
			.then(data => {
			// console.log('Float Balance:', data.data.balance);
			if (data.success) {
				const balanceInput = document.getElementById('float_balance');
				if (balanceInput) {
					balanceInput.value = `$${data.data.balance}`;
				} else {
					console.warn('Float balance input not found in DOM!');
				}
			} else {
				console.error('Error:', data.message);
			}
		});
	}

	function waitForOrderValuesAndUpdateRemaining(retries = 10) {
		const floatInput = document.getElementById('float_balance');
		const totalInput = document.getElementById('prepaid-order-total');
	
		// If either field doesn't exist, bail
		if (!floatInput || !totalInput) return console.warn("Missing input elements");
	
		const floatVal = floatInput.value.replace(/[^\d.]/g, '');
		const totalVal = totalInput.value.replace(/[^\d.]/g, '');
	
		// Only proceed when both fields have actual numeric values
		if (!floatVal || !totalVal || isNaN(floatVal) || isNaN(totalVal)) {
			if (retries > 0) {
				setTimeout(() => waitForOrderValuesAndUpdateRemaining(retries - 1), 200);
			} else {
				console.warn('❌ Still missing float or total values after retries.');
			}
			return;
		}
	
		setTimeout(() => {
			console.log('Confirm TO PAYMENT CALL settimeout');
			updatePrepaidRemainingBalance();
		}, 300);	
	}
	
	function updatePrepaidRemainingBalance() {
		console.log('inside updatePrepaidRemainingBalance');
		console.log('inside updatePrepaidRemainingBalance 22222222222222');
	
		const floatInput = document.getElementById('float_balance');
		const totalInput = document.getElementById('prepaid-order-total');
		const remainingInput = document.getElementById('prepaid-remaining');
		const payButton = document.getElementById('place-order-btn');
	
		if (!floatInput || !totalInput || !remainingInput) {
			console.warn('One or more inputs missing');
			return;
		}
	
		const floatRaw = floatInput.value.trim();
		const totalRaw = totalInput.value.trim();
	
		if (!floatRaw || !totalRaw) {
			console.warn('Input values are empty');
			return;
		}
	
		const floatVal = parseFloat(floatRaw.replace(/[^\d.]/g, '')) || 0;
		const totalVal = parseFloat(totalRaw.replace(/[^\d.]/g, '')) || 0;
	
		const remaining = floatVal - totalVal;
	
		console.log('inside totalVal', totalVal);
		console.log('inside floatVal', floatVal);
		console.log('inside remaining', remaining);
	
		remainingInput.value = `$${remaining.toFixed(2)}`;
	
		if (remaining < 0) {
			remainingInput.classList.add('is-invalid');
			if (!document.getElementById('remaining-error')) {
				const error = document.createElement('div');
				error.id = 'remaining-error';
				error.className = 'invalid-feedback';
				error.innerText = 'Insufficient balance. Please adjust your order total.';
				remainingInput.parentNode.appendChild(error);
			}
			if (payButton) payButton.disabled = true;
		} else {
			remainingInput.classList.remove('is-invalid');
			const existingError = document.getElementById('remaining-error');
			if (existingError) existingError.remove();
			if (payButton) payButton.disabled = false;
		}
	}
	
	// Back to Order Summary
	document.getElementById("back-to-order-summary")?.addEventListener("click", function () {
		document.getElementById("payment-confirmation-container").style.display = "none";
		document.getElementById("back-to-order-summary").style.display = "none";
		document.getElementById("order-summary-container").style.display = "block";
		document.getElementById("back-to-delivery-step").style.display = "block";

		updateStepIndicator(3); // Return to 4th step
	});

	jQuery(document).on("click", ".back-to-order-summary", function () {

		document.querySelectorAll(".table-container, .gift-card-container, #save-and-next-btn").forEach(el => {
            if (el) el.setAttribute("style", "display: none !important;");
        });

		//jQuery('#change__back_status').show();
		jQuery('#order-summary-container').hide();
		jQuery("#back-to-delivery-step").hide();
		jQuery('#delivery-method-container').hide();
        jQuery('.customisation-container').hide();

		jQuery('#payment-confirmation-container').hide();
		jQuery('#back-to-order-summary').hide();
		jQuery('.change__back_status').hide();
		
		jQuery('#order-summary-container').show();
		jQuery('#back-to-delivery-step').show();

		updateStepIndicator(3); // Return to 4th step
	});

	// Business Invoice Toggle
	let businesstab = document.querySelector('.business-invoice-tab-top');
	let businessContent = document.querySelector('.business-invoice-section .invoice-details');
	if (businesstab) {
		businesstab.addEventListener('click', () => {
			businesstab.classList.toggle('active');
			businessContent.classList.toggle('active');
		});
	}

	// Copy bank reference
	document.getElementById("copy-reference")?.addEventListener("click", function () {
		const refInput = document.getElementById("bank-reference");
		refInput.select();
		document.execCommand("copy");

		// Show temporary "Copied!" tooltip
		const tooltip = new bootstrap.Tooltip(this, {
			title: "Copied!",
			trigger: "manual"
		});
		tooltip.show();
		setTimeout(() => tooltip.hide(), 1000);
	});

	// Update prepaid totals when tab is clicked
	document.getElementById("prepaid-tab")?.addEventListener("click", function(){
		updatePrepaidTotals();
		get_pre_paid_balance();
	});

	// Show only the payment method tab that matches the selected business's billing
	// type: Client Billing businesses can only pay via Client Billing; Instant
	// payment/Float businesses can only pay via Pre-Paid Credit (per spec).
	function updatePaymentTabsForBillingType(userId) {
		const clientTab = document.getElementById('client-tab');
		const prepaidTab = document.getElementById('prepaid-tab');
		if (!clientTab || !prepaidTab) {
			return;
		}

		if (!userId) {
			clientTab.classList.add('disabled');
			prepaidTab.classList.add('disabled');
			return;
		}

		$.ajax({
			url: delivery_ajax.ajax_url,
			method: 'POST',
			data: {
				action: 'check_approved_billing',
				user_id: userId
			},
			success: function (response) {
				const isClientBilling = !!(response && response.success && response.data && response.data.approved);

				clientTab.classList.toggle('disabled', !isClientBilling);
				prepaidTab.classList.toggle('disabled', isClientBilling);

				// If the currently active tab just became disabled, switch to the tab
				// that's actually available for this business.
				const activeTab = document.querySelector('#paymentTabs .nav-link.active');
				const activeTabIsNowDisabled = activeTab && (activeTab.id === 'client-tab' || activeTab.id === 'prepaid-tab') && activeTab.classList.contains('disabled');

				if (activeTabIsNowDisabled) {
					const targetTab = isClientBilling ? clientTab : prepaidTab;
					if (window.bootstrap && window.bootstrap.Tab) {
						new window.bootstrap.Tab(targetTab).show();
					} else {
						targetTab.click();
					}
				}
			}
		});
	}

	$('#business-user-dropdown').on('change', function () {
		updatePaymentTabsForBillingType($(this).val());
	});

	// Run once on load in case a business is pre-selected (e.g. editing a draft order).
	updatePaymentTabsForBillingType($('#business-user-dropdown').val());

	// Place Order Button
	// document.getElementById("place-order-btn")?.addEventListener("click", function() {
	// 	const paymentMethod = getSelectedPaymentMethod();
	// 	const orderData = collectOrderData(paymentMethod);
	// 	processOrder(orderData);
	// });

	// Helper Functions
	function updateStepIndicator(stepIndex) {
		document.querySelectorAll(".step-indicator .step").forEach((step, index) => {
			step.classList.toggle("active-step", index === stepIndex);
		});
	}

	function transferOrderTotals() {
		const subtotal = parseFloat(document.querySelector(".subtotal-price").textContent.replace(/[^\d.-]/g, ''));
		const gst = parseFloat(document.querySelector(".gst-price").textContent.replace(/[^\d.-]/g, ''));
		const total = parseFloat(document.querySelector(".summary-grand-order-total").textContent.replace(/[^\d.-]/g, ''));

		document.getElementById("payment-subtotal").textContent = `$${subtotal.toFixed(2)}`;
		document.getElementById("payment-gst").textContent = `$${gst.toFixed(2)}`;
		document.getElementById("payment-total").textContent = `$${total.toFixed(2)}`;
		document.getElementById("order-payment-total").textContent = `$${total.toFixed(2)}`;

	}

	function get_pre_paid_balance() {
		const businessUserDropdown = document.getElementById('business-user-dropdown');
		const selectedBusinessUserId = businessUserDropdown?.value;
	
	
		if (!selectedBusinessUserId) {
			// console.warn('No business user selected.');
			return;
		}

		fetch(`/wp-admin/admin-ajax.php?action=get_business_user_paid_balance&user_id=${selectedBusinessUserId}`)
			.then(response => response.json())
			.then(data => {
			// console.log('Float Balance:', data.data.balance);
			if (data.success) {
				const balanceInput = $('#pay-with-pre-paid-balance span');
				const balanceremainaigtext = $('#pay-with-pre-paid-remaining span');
				let totalText = $('#order-payment-total').text();
				let total = parseFloat(totalText.replace('$', ''));    // 34
				let balanceremainaig = data.data.balance - total;

				balanceremainaigtext.text(`$${balanceremainaig.toFixed(2)}`);
				
				if (balanceInput) {
					balanceInput.text(`$${data.data.balance}`);
					//balanceremainaigInput
				} else {
					// console.warn('Float balance input not found in DOM!');
				}
			} else {
				// console.error('Error:', data.message);
			}
		});
	}

	function updatePrepaidTotals() {
		const orderTotal = parseFloat(document.getElementById("payment-total").textContent.replace(/[^\d.-]/g, ''));
		// const currentBalance = ; // Would come from your system
		// const remaining = currentBalance - orderTotal;

		//document.getElementById("prepaid-order-total").value = `$${orderTotal.toFixed(2)}`;
		// document.getElementById("prepaid-remaining").value = `$${remaining.toFixed(2)}`;
	}

	function getSelectedPaymentMethod() {
		const activeTab = document.querySelector("#paymentTabs .nav-link.active").id;
		if (activeTab.includes("credit")) return "Credit Card";
		if (activeTab.includes("bank")) return "Bank Transfer";
		if (activeTab.includes("client")) return "Client Billing";
		if (activeTab.includes("prepaid")) return "Pre-Paid Credit";
		return "Unknown";
	}

	// const businessNameDropdown = document.getElementById('business-user-dropdown');
	// console.log('Selected value:', businessNameDropdown.value);
	// console.log('Selected text:', businessNameDropdown.options[businessNameDropdown.selectedIndex].text);
	// const businessValue = businessNameDropdown.options[businessNameDropdown.selectedIndex].text;

	function collectScheduleTableData() {
		const data = [];
		const rows = document.querySelectorAll(".schedule-delivery-table tbody tr");
	
		rows.forEach((row, recipientIndex) => {
			const recipient = row.querySelector("td:nth-child(1)")?.textContent.trim();
			const contact = row.querySelector("td:nth-child(2)")?.textContent.trim();
	
			const giftCardElements = row.querySelectorAll(".gift-card-entry");
			const scheduleInputs = row.querySelectorAll(".date-time-picker");
	
			const giftCards = [];
	
			giftCardElements.forEach((el, giftIndex) => {
				const giftName = el.textContent.trim();
				const sku = el.dataset.sku || ''; // ensure SKU is available as a unique key
				const dateInput = scheduleInputs[giftIndex];
				const dateValue = dateInput ? dateInput.value.trim() : '';
			
				giftCards.push({
					name: giftName,
					sku: sku,
					scheduleDate: dateValue
				});
			});			
	
			data.push({
				recipient,
				contact,
				giftCards
			});
		});
	
		return data;
	}
	
	// Add this to your delivery.js file
	document.getElementById("place-order-btn")?.addEventListener("click", function () {

		// let dataId = jQuery(this).data('order-id');
		// console.log('dataId',dataId);
		let paymentMethod = $(this).attr("data-order-type");
		// console.log('paymentMethod',paymentMethod);
		const orderData = collectOrderData(paymentMethod);
		console.log('orderData...',orderData);
		$(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Placing Order...');
		// return;
		// Process the order via AJAX
		// console.log('orderData...' ,orderData);

		setTimeout(() => {
			processOrder(orderData);
		}, 900);

	});

	function collectOrderData(paymentMethod) {
		const scheduleData = collectScheduleTableData();
		// console.log(scheduleData);
		// const senderName = $('#select-sender-dropdown').val();

		// Collect recipient and product data
		const recipients = {};
		const giftCardSlides = document.querySelectorAll(".gift-card-slide");
		
		/*const scheduleDates = {};
		const dateInputs = document.querySelectorAll('.scheduled_date');
		
		dateInputs.forEach(input => {
		  const recipientIndex = input.dataset.recipientIndex;
		  const giftIndex = input.dataset.giftIndex;
		
		  // Check if Flatpickr instance is attached
		  if (input._flatpickr) {
		    const selectedDates = input._flatpickr.selectedDates; // Array of Date objects
		    if (selectedDates.length > 0) {
		      // Format date string (e.g. "YYYY-MM-DD HH:mm A")
		      const formattedDate = input._flatpickr.formatDate(selectedDates[0], "Y-m-d h:i K");
		
		      if (!scheduleDates[recipientIndex]) {
		        scheduleDates[recipientIndex] = {};
		      }
		      scheduleDates[recipientIndex][giftIndex] = formattedDate;
		    }
		  } else {
		    // Fallback: use input.value if Flatpickr instance isn't available
		    const val = input.value;
		    if (val) {
		      if (!scheduleDates[recipientIndex]) {
		        scheduleDates[recipientIndex] = {};
		      }
		      scheduleDates[recipientIndex][giftIndex] = val;
		    }
		  }
		});
		console.log(scheduleDates);*/

		const emailToggleChecked = document.querySelector('#email-toggle:checked') !== null;
		const smsToggleChecked = document.querySelector('#sms-toggle:checked') !== null;

		// Collect sender information
		const senderDropdown = document.getElementById("select-sender-dropdown");
		const selectedSender = senderDropdown.options[senderDropdown.selectedIndex];
		const senderName = selectedSender.text; // or .value if needed
		const senderEmail = selectedSender.getAttribute('data-email');
		const orderName = document.getElementById("display-order-name").textContent;
		const clientReference = document.getElementById("client-reference").value;
		const fullfillmentTotal = document.getElementById("fulfillment-total").textContent;
		const deliveryTotal = document.getElementById("delivery-total").textContent;
		// console.log('Delivery Total:', deliveryTotal);
		const checkBhnSkus = [];

		for (const slide of giftCardSlides) {
			const email = slide.dataset.email?.trim() || '';
			const phone = slide.dataset.phone?.trim() || '';

			// Skip only if BOTH email and phone are missing
			if (!email && !phone) continue;

			// Use email as key if present, otherwise fall back to phone
			const recipientKey = email || phone;
			
			const firstName = (slide.dataset.firstName || '').trim();
		  	const lastName = (slide.dataset.surname || '').trim();
		  	const fullName = `${firstName} ${lastName}`.trim();
		  	const gc_name = slide.dataset.name || '';
            const gc_brands = slide.dataset.brands || '';
			
			let message = (slide.dataset.message).trim();
			let subject = (slide.dataset.subject || 'Congrats <First Name>, You have received a <Gift Card Value> <Gift Card Title>').trim();
			// console.log('anima is...:', slide.dataset);
			

			const priceText = slide.querySelector(".gift-card-price")?.textContent || '';
  			const price = parseFloat(priceText.replace(/[^\d.-]/g, '')) || 0;
  			const formattedPrice = priceText.trim();
			
			const sku = slide.dataset.sku?.trim() || '';

			if (sku) checkBhnSkus.push(sku);

			const imageEl = slide.querySelector(".gift-card-img");
			const image = imageEl ? imageEl.src : '';

			// Determine delivery method
			// let deliveryMethod = slide.dataset.deliveryMethod?.trim();
			// let deliveryMethod = slide.attr('data-delivery-method')?.trim();
			let deliveryMethod = slide.dataset.deliveryMethod?.trim();

			console.log('Insidde collectorderdata,,,,,',deliveryMethod);

			// console.log('deliveryMethod...',deliveryMethod);
			if (!deliveryMethod) {
				deliveryMethod = getDeliveryMethodFromToggle();
				// $slide.attr('data-delivery-method', deliveryMethod);
				slide.setAttribute('data-delivery-method', deliveryMethod);
			}


			// console.log('populateOrderSummary delivery method',deliveryMethod);
			// console.log('populateOrderSummary giftCardSlides',giftCardSlides);
			// console.log('populateOrderSummary slide...............',slide);

			// const phone = slide.dataset.phone?.trim() || '';

			function normalizePlaceholders(str) {
                if (!str) return "";
                return str
                    .replace(/<Full Name>/gi, "<Full Name>")
                    .replace(/&lt;Full Name&gt;/gi, "<Full Name>")
                    .replace(/<First Name>/gi, "<First Name>")
                    .replace(/&lt;First Name&gt;/gi, "<First Name>")
                    .replace(/<Last Name>/gi, "<Last Name>")
                    .replace(/&lt;Last Name&gt;/gi, "<Last Name>")
                    .replace(/<Surname>/gi, "<Surname>")
                    .replace(/&lt;Surname&gt;/gi, "<Surname>")
                    .replace(/<Email>/gi, "<Email>")
                    .replace(/&lt;Email&gt;/gi, "<Email>")
                    .replace(/<Gift Card>/gi, "<Gift Card>")
                    .replace(/&lt;Gift Card&gt;/gi, "<Gift Card>")
                    .replace(/<Gift Card Title>/gi, "<Gift Card Title>")
                    .replace(/&lt;Gift Card Title&gt;/gi, "<Gift Card Title>")
                    .replace(/<Gift Card Name>/gi, "<Gift Card Name>")
                    .replace(/&lt;Gift Card Name&gt;/gi, "<Gift Card Name>")
                    .replace(/<Gift Card Value>/gi, "<Gift Card Value>")
                    .replace(/&lt;Gift Card Value&gt;/gi, "<Gift Card Value>")
                    .replace(/<Price>/gi, "<Price>")
                    .replace(/&lt;Price&gt;/gi, "<Price>")
                    .replace(/<Value>/gi, "<Value>")
                    .replace(/&lt;Value&gt;/gi, "<Value>")
                    .replace(/<Sender>/gi, "<Sender>")
                    .replace(/&lt;Sender&gt;/gi, "<Sender>")
                    .replace(/<Sender Name>/gi, "<Sender Name>")
                    .replace(/&lt;Sender Name&gt;/gi, "<Sender Name>")
                    .replace(/<Brand>/gi, "<Brand>")
                    .replace(/&lt;Brand&gt;/gi, "<Brand>")
                    .replace(/<Brands>/gi, "<Brands>")
                    .replace(/&lt;Brands&gt;/gi, "<Brands>");
            }
    
            function buildDynamicContent(template) {
                if (!template || template.trim() === "") return "";
                return template
                    .replace(/<Full Name>/gi, fullName)
                    .replace(/&lt;Full Name&gt;/gi, fullName)
                    .replace(/<First Name>/gi, firstName)
                    .replace(/&lt;First Name&gt;/gi, firstName)
                    .replace(/<Last Name>/gi, lastName)
                    .replace(/&lt;Last Name&gt;/gi, lastName)
                    .replace(/<Surname>/gi, lastName)
                    .replace(/&lt;Surname&gt;/gi, lastName)
                    .replace(/<Email>/gi, email)
                    .replace(/&lt;Email&gt;/gi, email)
                    .replace(/<Gift Card>/gi, gc_name)
                    .replace(/&lt;Gift Card&gt;/gi, gc_name)
                    .replace(/<Gift Card Title>/gi, gc_name)
                    .replace(/&lt;Gift Card Title&gt;/gi, gc_name)
                    .replace(/<Gift Card Name>/gi, gc_name)
                    .replace(/&lt;Gift Card Name&gt;/gi, gc_name)
                    .replace(/<Gift Card Value>/gi, formattedPrice)
                    .replace(/&lt;Gift Card Value&gt;/gi, formattedPrice)
                    .replace(/<Price>/gi, formattedPrice)
                    .replace(/&lt;Price&gt;/gi, formattedPrice)
                    .replace(/<Value>/gi, formattedPrice)
                    .replace(/&lt;Value&gt;/gi, formattedPrice)
                    .replace(/<Sender>/gi, senderName)
                    .replace(/&lt;Sender&gt;/gi, senderName)
                    .replace(/<Sender Name>/gi, senderName)
                    .replace(/&lt;Sender Name&gt;/gi, senderName)
                    .replace(/<Brand>/gi, gc_brands)
                    .replace(/&lt;Brand&gt;/gi, gc_brands)
                    .replace(/<Brands>/gi, gc_brands)
                    .replace(/&lt;Brands&gt;/gi, gc_brands);
            }

            subject = buildDynamicContent(normalizePlaceholders(subject));
            message = buildDynamicContent(normalizePlaceholders(message));
			
			if (!recipients[recipientKey]) {
				recipients[recipientKey] = {
					name: fullName,
					firstname: firstName,
					lastname: lastName,
					email: email,
					phone: phone,
					products: []
				};
			}

			const emailAnimation = slide.dataset.emailAnimation || '';
			const textAnimation = slide.dataset.textAnimation || '';
			// console.log('Phone on slide:', slide.dataset.phone);
			// console.log('Message on slide:', slide.dataset.message);


			// Add product to the recipient's products list
			recipients[recipientKey].products.push({
				sku: sku,
				price: price,
				subject: subject,
				message: message,
				deliveryMethod: deliveryMethod,
				image: image,
				emailAnimation: emailAnimation,
				textAnimation: textAnimation
			});
		}

		if (checkBhnSkus.length > 0) {
			jQuery.ajax({
				url: delivery_ajax.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'check_bhn_product',
					security: delivery_ajax.gc_nonce,
					checkBhnSkus: checkBhnSkus,
				},
				success: function (response) {
					if (response.success && Array.isArray(response.data)) {
						const bhnData = response.data; // contains { sku, product_id, bhn_pro }
		
						for (const emailKey in recipients) {
							recipients[emailKey].products.forEach(product => {
								const found = bhnData.find(item => item.sku === product.sku);
								product.bhnPro = found ? found.bhn_pro === true : false;
								//product.bhnPro = found ? found.bhn_pro === '1' : '0';
							});
						}
		
						console.log('✨ Updated recipients with BHN flag:', recipients);
					} else {
						console.warn('⚠️ Unexpected BHN check response:', response);
					}
				},
				error: function (xhr, status, error) {
					console.error('❌ AJAX error checking BHN:', error);
				}
			});
		}		
		
		  
		// console.log('recipients', recipients);

		const relatedPO = document.querySelector('.related-po').value;
		const additionalReference = document.querySelector('.additional-reference').value;
		// const relatedPo = document.getElementById('related-po').value;

		// ✅ Collect activation expiry fields correctly
		const activationExpiryTypeValue = document.getElementById("activation_expiry_type")?.value || '';
		let activationExpiryDateValue = document.getElementById("activation-expiry-date")?.value || '';
		const activationExpiryDurationValue = document.getElementById("activation_expiry_duration")?.value || '';
		const activationExpiryUnitValue = document.getElementById("activation_expiry_unit")?.value || '';
		
		
		// ✅ Calculate expiry date if "set_period" is selected
		if (activationExpiryTypeValue === 'set_period' && activationExpiryDurationValue && activationExpiryUnitValue) {
			const now = new Date();
			let expiryDate = new Date(now); // clone current date
			// console.log('CCCCCCCCCCCCCCCCCC-- ',activationExpiryDateValue);

			const duration = parseInt(activationExpiryDurationValue);
			switch (activationExpiryUnitValue) {
				case 'days':
					expiryDate.setDate(now.getDate() + duration);
					break;
				case 'weeks':
					expiryDate.setDate(now.getDate() + (duration * 7));
					break;
				case 'months':
					expiryDate.setMonth(now.getMonth() + duration);
					break;
				case 'years':
					expiryDate.setFullYear(now.getFullYear() + duration);
					break;
			}

			// Format it as "YYYY-MM-DDTHH:MM"
			const pad = (num) => String(num).padStart(2, '0');
			const formatted = expiryDate.getFullYear() + '-' +
				pad(expiryDate.getMonth() + 1) + '-' +
				pad(expiryDate.getDate()) + 'T' +
				pad(expiryDate.getHours()) + ':' +
				pad(expiryDate.getMinutes());

			activationExpiryDateValue = formatted;
			// return;
		}
		// console.log("activationExpiryTypeValue:", activationExpiryTypeValue);
		// console.log("activationExpiryDateValue:", activationExpiryDateValue);
		// console.log("activationExpiryDurationValue:", activationExpiryDurationValue);
		// console.log("activationExpiryUnitValue:", activationExpiryUnitValue);
		// console.log("clientReference:", clientReference);

		// console.log('Related PO:', relatedPO);



		// ✅ Collect business name text at the time of placing order
		// const businessDropdown = document.getElementById('business-user-dropdown');
		// const businessValue = businessDropdown ? businessDropdown.options[businessDropdown.selectedIndex].text : '';


		const businessDropdown = document.getElementById('business-user-dropdown');
		const selectedBusinessOption = businessDropdown ? businessDropdown.options[businessDropdown.selectedIndex] : null;
		const businessValue = selectedBusinessOption ? selectedBusinessOption.text : '';
		const businessId = selectedBusinessOption ? selectedBusinessOption.getAttribute('data-business-id') : null;
		// console.log('Invaluds is this  businessId.... ',businessId);
		const orderInfo = jQuery('#place-order-btn').data('order-id');
		const campaignDropdown = document.getElementById('campaign-dropdown');
		const campaignValue = campaignDropdown && campaignDropdown.value !== 'Select campaign' ? campaignDropdown.value : '';
		

		// Collect invoice information if needed
		let invoiceDetails = null;
		if (document.getElementById("need-invoice").checked) {
			invoiceDetails = {
				companyName: document.querySelector("#invoice-details input[placeholder='Company Pty Ltd']").value,
				abn: document.querySelector("#invoice-details input[placeholder='12 345 678 901']").value,
				billingAddress: document.querySelector("#invoice-details textarea[placeholder='Street Address, City, State, Postcode']").value,
				notes: document.querySelector("#invoice-details textarea[placeholder='Special instructions for invoice']").value
			};
		}
		// const businessName = document.getElementById("business-user-dropdown")?.value || '';

		// Return structured order data
		return {
			paymentMethod: paymentMethod,
			orderInfo : orderInfo,
			businessId: businessId,
			campaignValue: campaignValue,
			sender: senderName,
			senderEmail: senderEmail,
			orderName: orderName,
			poNumber: relatedPO,
			clientReference: clientReference,
			additionalReference: additionalReference,
			recipients: Object.values(recipients),
			scheduleData: scheduleData,
			invoiceDetails: invoiceDetails,
			subtotal: parseFloat(document.getElementById("payment-subtotal").textContent.replace(/[^\d.-]/g, '')),
			gst: parseFloat(document.getElementById("payment-gst").textContent.replace(/[^\d.-]/g, '')),
			gst2: document.getElementById("payment-gst").textContent,
			total: parseFloat(document.getElementById("payment-total").textContent.replace(/[^\d.-]/g, '')),
			fullfillmentTotal: parseFloat(document.getElementById("fulfillment-total").textContent.replace(/[^\d.-]/g, '')),
			fullfillmentTotal2: fullfillmentTotal,
			deliveryTotal: parseFloat(document.getElementById("delivery-total").textContent.replace(/[^\d.-]/g, '')),
			deliveryTotal2: deliveryTotal,
			activationExpiryTypeValue: activationExpiryTypeValue,
			activationExpiryDateValue: activationExpiryDateValue,
			activationExpiryDurationValue: activationExpiryDurationValue,
			activationExpiryUnitValue: activationExpiryUnitValue,
		};
	}

	
		function processOrder(orderData) {
			// DEBUG: Full payload sent when Place Order is clicked (check Network tab for request with action=place_cod_order to see server response)
			console.log('Place Order – full orderData sent to place_cod_order:', orderData);
			if (orderData && orderData.recipients) {
				orderData.recipients.forEach((r, i) => {
					console.log('Recipient ' + i + ' products (names/titles):', (r.products || []).map(p => ({ name: p.name, title: p.title, sku: p.sku })));
				});
			}

			$.ajax({
				url: delivery_ajax.ajax_url,
				method: 'POST',
				dataType: 'json', // Expect JSON response
				data: {
					action: 'place_cod_order',
					security: delivery_ajax.nonce,
					order_data: orderData
				},
				success: function (response) {
					console.log('AJAX Success - Response:', response);
					
					// Check if response is valid and successful
					if (response && response.success === true) {
						console.log('Response is successful, redirecting...');
						$('#order-error-message').text('').removeClass('alert alert-danger');
						
						jQuery('#new-order-form-container').addClass('order_submitted');
						if (response.data && response.data.scheduled_dates && response.data.scheduled_dates.length > 0) {
							let html = "<strong>Scheduled Delivery Dates:</strong><ul>";
							response.data.scheduled_dates.forEach(date => {
								html += "<li>" + date + "</li>";
							});
							html += "</ul>";
							$('#order-summary-box').html(html);
						}
						
						if (response.data && response.data.redirect_url) {
							console.log('Redirecting to:', response.data.redirect_url);
							window.location.href = response.data.redirect_url;
						} else {
							console.error('No redirect_url in response.data');
							$('#order-error-message').html('<span>Order failed: </span>No redirect URL provided').addClass('alert alert-danger');
						}
					} else {
						// Handle error response (success: false) - show reason when present
						console.error('Response indicates failure. Response:', response);
						const data = response && response.data ? response.data : {};
						const errorMsg = data.reason || data.message || 'Something went wrong.';
						console.log('Order failure reason:', errorMsg, 'Full response:', response);
						$('#order-error-message').html('<span>Order failed: </span>' + errorMsg).addClass('alert alert-danger');
					}
					$("#place-order-btn").html('Place COD Order');
				},
				error: function (xhr, status, error) {
					console.log('AJAX Error:', status, error);
					console.log('Response Text:', xhr.responseText);
					let msg = 'An unknown error occurred.';
					jQuery('#new-order-form-container').removeClass('order_submitted');
					
					// Try to extract JSON from error response (in case HTML was mixed with JSON)
					let responseText = xhr.responseText || '';
					
					// First, try to find JSON in the response
					const jsonMatch = responseText.match(/\{[\s\S]*\}/);
					if (jsonMatch) {
						try {
							const response = JSON.parse(jsonMatch[0]);
							// Check if it's actually a success response that was mis-parsed
							if (response.success === true) {
								// This is actually a success! Handle it as success
								console.log('Found success response in error handler, redirecting...');
								$('#order-error-message').text('').removeClass('alert alert-danger');
								jQuery('#new-order-form-container').addClass('order_submitted');
								if (response.data && response.data.redirect_url) {
									window.location.href = response.data.redirect_url;
								} else {
									msg = 'Order placed successfully, but redirect URL is missing.';
									$('#order-error-message').html('<span>Order failed: </span>' + msg).addClass('alert alert-danger');
								}
								$("#place-order-btn").html('Place COD Order');
								return;
							}
							// It's a real error response - prefer reason when present
							const data = response?.data || {};
							msg = data.reason || data.message || response?.message || msg;
							console.log('Order failure reason (from error handler):', msg, 'Full response:', response);
						} catch (e) {
							// If parsing fails, try parsing the whole response
							try {
								const response = JSON.parse(responseText);
								if (response.success === true) {
									// Success response found
									console.log('Found success response in error handler, redirecting...');
									$('#order-error-message').text('').removeClass('alert alert-danger');
									jQuery('#new-order-form-container').addClass('order_submitted');
									if (response.data && response.data.redirect_url) {
										window.location.href = response.data.redirect_url;
									}
									$("#place-order-btn").html('Place COD Order');
									return;
								}
								const data = response?.data || {};
								msg = data.reason || data.message || response?.message || msg;
							} catch (e2) {
								msg = `Error: ${error}`;
							}
						}
					} else {
						// No JSON found, try parsing whole response
						try {
							const response = JSON.parse(responseText);
							if (response.success === true) {
								// Success response found
								console.log('Found success response in error handler, redirecting...');
								$('#order-error-message').text('').removeClass('alert alert-danger');
								jQuery('#new-order-form-container').addClass('order_submitted');
								if (response.data && response.data.redirect_url) {
									window.location.href = response.data.redirect_url;
								}
								$("#place-order-btn").html('Place COD Order');
								return;
							}
							const data = response?.data || {};
							msg = data.reason || data.message || response?.message || msg;
						} catch (e) {
							msg = `Error: ${error}`;
						}
					}
					
					console.log('Order failure reason (final):', msg, 'Response text:', responseText);
					$('#order-error-message')
						.html('<span>Order failed: </span>' + msg)
						.addClass('alert alert-danger');
					$("#place-order-btn").html('Place COD Order');
				}			
			});
		}



	// function processOrder(orderData) {
	// 	console.log('🟡 Order Data Sent:', orderData);

	// 	// ✅ Step 1: Collect all SKUs from products
	// 	let checkBhnSkus = [];
	// 	if (Array.isArray(orderData.products)) {
	// 		checkBhnSkus = orderData.products.map(p => p.sku);
	// 	}

	// 	// ✅ Step 2: Check BHN products before placing order
	// 	if (checkBhnSkus.length > 0) {
	// 		jQuery.ajax({
	// 			url: delivery_ajax.ajax_url,
	// 			type: 'POST',
	// 			dataType: 'json',
	// 			data: {
	// 				action: 'check_bhn_product',
	// 				checkBhnSkus: checkBhnSkus
	// 			},
	// 			success: function (response) {
	// 				console.log('✅ BHN check response:', response);

	// 				if (response.success && Array.isArray(response.data)) {
	// 					const bhnData = response.data; // [{sku, bhn_pro}, ...]

	// 					// ✅ Update each product in orderData
	// 					orderData.products = orderData.products.map(product => {
	// 						const found = bhnData.find(item => item.sku === product.sku);
	// 						const isBhn = found ? (found.bhn_pro === true || found.bhn_pro === '1') : false;

	// 						return {
	// 							...product,
	// 							bhnPro: isBhn,
	// 							bhnno: isBhn
	// 						};
	// 					});
	// 				} else {
	// 					console.warn('⚠️ Unexpected BHN check response. Proceeding without BHN flags.');
	// 				}

	// 				// ✅ Step 3: After updating data, send order
	// 				sendPlaceOrderAjax(orderData);
	// 			},
	// 			error: function (xhr, status, error) {
	// 				console.error('❌ BHN check failed:', error);
	// 				// Even if it fails, still send order
	// 				sendPlaceOrderAjax(orderData);
	// 			}
	// 		});
	// 	} else {
	// 		console.warn('⚠️ No products found. Skipping BHN check.');
	// 		sendPlaceOrderAjax(orderData);
	// 	}
	// }

	// ✅ Step 3: Your existing order placement AJAX
	// function sendPlaceOrderAjax(orderData) {
	// 	console.log('🚀 Sending Final Order:', orderData);

	// 	$.ajax({
	// 		url: delivery_ajax.ajax_url,
	// 		method: 'POST',
	// 		data: {
	// 			action: 'place_cod_order',
	// 			security: delivery_ajax.nonce,
	// 			order_data: orderData
	// 		},
	// 		success: function (response) {
	// 			if (response.success) {
	// 				$('#order-error-message').text('').removeClass('alert alert-danger');
	// 				jQuery('#new-order-form-container').addClass('order_submitted');

	// 				if (response.data.scheduled_dates && response.data.scheduled_dates.length > 0) {
	// 					let html = "<strong>Scheduled Delivery Dates:</strong><ul>";
	// 					response.data.scheduled_dates.forEach(date => {
	// 						html += "<li>" + date + "</li>";
	// 					});
	// 					html += "</ul>";
	// 					$('#order-summary-box').html(html);
	// 				}

	// 				window.location.href = response.data.redirect_url;
	// 			} else {
	// 				const errorMsg = response.data?.message || 'Something went wrong.';
	// 				$('#order-error-message')
	// 					.html('<span>Order failed: </span>' + errorMsg)
	// 					.addClass('alert alert-danger');
	// 			}

	// 			$("#place-order-btn").html('Place COD Order');
	// 		},
	// 		error: function (xhr, status, error) {
	// 			let msg = 'An unknown error occurred.';
	// 			jQuery('#new-order-form-container').removeClass('order_submitted');
	// 			try {
	// 				const response = JSON.parse(xhr.responseText);
	// 				msg = response?.data?.message || response?.message || msg;
	// 			} catch (e) {
	// 				msg = `Raw response: ${xhr.responseText}`;
	// 			}
	// 			$('#order-error-message')
	// 				.text('Order failed: ' + msg)
	// 				.addClass('alert alert-danger');
	// 			$("#place-order-btn").html('Place COD Order');
	// 		}
	// 	});
	// }

	$('[data-bs-toggle="tooltip"]').tooltip();

});