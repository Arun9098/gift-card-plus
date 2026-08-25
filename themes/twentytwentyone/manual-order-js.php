<script>
jQuery(document).ready(function($){
    
    var invalidCells = [];
    let businessUserData = <?php echo json_encode($business_user_data); ?>;
    let selectedSender = null;
    var originalCsvData = {};
    var templateHeaders = [];
    var headerMapping = [];
    var editedData = {};
    var rowsPerPage = 20;
    var currentPage = 1;
    let csvRecipientDetails = null;
    var currentFilter = 'all';
    var editMode = false;
    var isCorrectedView = false;
    jQuery('#submit-file-upload').on('click', function () {
        // console.log('updateRowCounts Calling..');
        jQuery('#edit-errors').text('Edit Errors');
        // console.log('CCCCCCCCCCCC');
        // Also reset edit mode if needed
        editMode = false;
        jQuery('#csv-preview-table td.error-cell').each(function () {
            jQuery(this).attr('contenteditable', false);
            jQuery(this).css('background-color', ''); // Revert to normal
        });
        const file = jQuery('#csv-file-input1')[0].files[0];
        if (!file) {
            jQuery('#file-error-msg').text('⚠️ Please select a CSV file.');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'upload_csv_file_bulk');

        jQuery('#upload-progress').css({ display: 'block' });
        jQuery('#progress-bar').css('width', '0%').text('0%');

        $.ajax({
            url: ajaxData.ajax_url, // Use JS variable, not PHP echo here
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        jQuery('#progress-bar').css('width', percentComplete + '%').text(percentComplete.toFixed(2) + '%');
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                jQuery('#upload-progress').hide();
                
                if (response.success) {
                    console.log('response.data: ',response.data);
                    jQuery('#file-upload-modal').modal('hide');
                    originalCsvData = response.data.csv_data;
                    templateHeaders = response.data.template_headers;
                    headerMapping = response.data.header_mapping;
                    isCorrectedView = false;

                    if (response.data.all_matched) {
                        // console.log('MAPPing');
                        applyMappingAndPreview(headerMapping);
                    } else {
                        // console.log('NO MAPPing');
                        showMappingInterface(headerMapping, templateHeaders, originalCsvData.headers);
                    }
                } else {
                    jQuery('#file-error-msg').text(response.data.message || '⚠️ Upload failed.');
                }
            },
            error: function () {
                jQuery('#file-error-msg').text('⚠️ An error occurred during upload.');
                jQuery('#upload-progress').hide();
            }
        });
    });
    
    function extractRecipientIds(csvData) {
        const ids = [];
        const idIndex = csvData.headers.indexOf('Recipient ID');
        if (idIndex === -1) return ids;

        csvData.data.forEach(row => {
            const id = row[idIndex]?.trim();
            ids.push(id);
        });
        console.log('ids', ids);
        return ids;
    }

    // Function to extract recipient IDs from CSV data
    function extractRecipientEmails(csvData) {
        const emails = [];
        const emailIndex = csvData.headers.indexOf('Recipient Email Address');
        if (emailIndex === -1) return emails;

        csvData.data.forEach(row => {
            const email = row[emailIndex]?.trim();
            emails.push(email);    
        });
        console.log('emails', emails);
        return emails;
    }

    function extractRecipientProducts(csvData) {
        const productData = [];
        const productCodeIndex = csvData.headers.indexOf('Product Code');
        const giftCardNameIndex = csvData.headers.indexOf('Gift Card Name');
        const giftCardValueIndex = csvData.headers.indexOf('Gift Card Value');

        csvData.data.forEach(row => {
            const sku = row[productCodeIndex]?.trim();
            const gift_card_name = row[giftCardNameIndex]?.trim();
            const gift_card_value = row[giftCardValueIndex]?.trim();
            
            productData.push({
                'sku': sku,
                'gift_card_name': gift_card_name,
                'gift_card_value': gift_card_value
            });
        });
        console.log('productData', productData);
        return productData;
    }

    function showMappingInterface(headerMapping, templateHeaders, uploadedHeaders) {
        let mappingHtml = '';
        const mandatoryHeaders = [
            'Recipient First Name', 'Delivery Method', 'Recipient Email Address',
            'Recipient Phone Number', 'Product Code', 'Gift Card Name', 'Gift Card Value',
            'Quantity', 'Personalisation'
        ];

        // Check for empty headers
        const emptyHeaders = uploadedHeaders
            .map((header, index) => ({ header, index }))
            .filter(h => !h.header || h.header.trim() === '');

        if (emptyHeaders.length > 0) {
            const emptyColumns = emptyHeaders.map(h => `Column ${h.index + 1}`).join(', ');
            $('#mapping-interface').html(`
                <div id="mandatory-warning" class="text-danger mb-3">
                    ⚠️ The following columns have no headers: ${emptyColumns}.<br>
                    Please update your CSV and ensure all columns have header names identical to the template file.
                </div>
            `);
            $('#mapping-modal').modal('show');
            $('#apply-mapping').hide();

            return; // Stop further execution
        }
        $('#apply-mapping').show();
        $("#mandatory-warning").remove();

        // Generate the mapping interface
        templateHeaders.forEach((templateHeader) => {
            const preSelected = headerMapping[templateHeader] || '';
            const isMandatory = mandatoryHeaders.includes(templateHeader);
            const mandatoryClass = isMandatory && !preSelected ? 'text-danger' : '';

            mappingHtml += `
                <div class="form-group">
                    <label class="${mandatoryClass}">${templateHeader}${isMandatory ? ' (mandatory)' : ''}</label>
                    <select class="form-control mapping-select" data-template="${templateHeader}">
                        <option value="">Select Header</option>
                            ${uploadedHeaders.map(header => {
                                const normalized = header.trim().toLowerCase();
                                const isDisabled = normalized === 'no' ? 'disabled' : '';
                                const isSelected = header === preSelected ? 'selected' : '';
                                
                                console.log(`Rendering header: "${header}", Disabled: ${isDisabled !== ''}, Selected: ${isSelected !== ''}`);

                                return `<option value="${header}" ${isDisabled} ${isSelected} style="${isDisabled ? 'color: gray;' : ''}">${header}</option>`;
                            }).join('')}
                    </select>
                </div>
            `;
        });

        $('#mapping-interface').html(`<div id="mandatory-warning" class="text-danger mb-3"></div>` + mappingHtml);
        $('#mapping-modal').modal('show');

        // Dropdown logic
        function updateDropdownOptions() {
            $('.mapping-select').each(function () {
                const currentSelect = $(this);
                const currentSelectedHeader = currentSelect.val();

                // currentSelect.find('option').prop('disabled', false);
                currentSelect.find('option').each(function () {
                    const optionValue = $(this).val().trim().toLowerCase();
                    if (optionValue !== 'no') {
                        $(this).prop('disabled', false);
                    }
                });
                $('.mapping-select').not(currentSelect).each(function () {
                    const selectedHeader = $(this).val();
                    if (selectedHeader) {
                        currentSelect.find(`option[value="${selectedHeader}"]`).prop('disabled', true);
                    }
                });
            });
        }

        $('.mapping-select').on('change', function () {
            updateDropdownOptions();
        });

        updateDropdownOptions();
    }

    function applyMappingAndPreview(mapping) {
        console.log('originalCsvData: ',originalCsvData);
        const updatedHeaders = templateHeaders;

        // Filter out non-mandatory fields that are not selected
        const filteredHeaders = updatedHeaders.filter(header => mapping[header] !== '');

        // Map the CSV data to the updated headers
        const updatedData = originalCsvData.data.map(row => {
            return filteredHeaders.map(templateHeader => {
                const selectedHeader = mapping[templateHeader];
                const indexInUploaded = originalCsvData.headers.indexOf(selectedHeader);
                return indexInUploaded !== -1 ? row[indexInUploaded] : '';
            });
        });

        // Update the original CSV data with the filtered headers and mapped data
        originalCsvData.headers = filteredHeaders;
        originalCsvData.data = updatedData;

        // Extract recipient IDs from the CSV data
        const recipientIds = extractRecipientIds(originalCsvData);
        const recipientEmails = extractRecipientEmails(originalCsvData);
        const recipientProducts = extractRecipientProducts(originalCsvData);

        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'get_recipient_details_by_emails',
                recipient_ids: recipientIds,
                recipient_emails: recipientEmails,
                recipient_products: recipientProducts
            },
            success: function (response) {
                if (response.success && response.data) {
                    csvRecipientDetails = response.data.data;
                    csvRecipientProducts = response.data.productData;
                    continueWithValidationAndPreview(response.data.data, response.data.productData);
                    //invalidCells = continueWithValidationAndPreview(response.data.data, originalCsvData);
                    //console.log('INVALID in AJAX: ',invalidCells);
                    console.log('1: ',response.data.data);
                } else {
                    console.warn("Recipient details fetch failed:", response);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error fetching recipients:", error);
            }
        });
        console.log('2: ',csvRecipientDetails);


        /*// Fetch recipient details from the server
        fetchRecipientDetailsByEmails(recipientIds, recipientEmails, originalCsvData).then(response => {
            if (response.success) {
                recipientsData = response.data.data;

                // Use updated validation functions
                const invalidRecipientCells = validateRecipientDetails(originalCsvData);
                const invalidEmailCells = validateEmails(originalCsvData);
                const invalidPhoneCells = validatePhoneNumbers(originalCsvData);
                const invalidMandatoryCells = validateMandatoryFields(originalCsvData);

                const invalidScheduledDateCells = validateScheduledDeliveryDates(csvData);
                
                const invalidPersonalizationCells = validatePersonalization(csvData);

                validateProductDetails(originalCsvData).then(invalidProductCells => {
                    const invalidCells = [
                        ...invalidEmailCells,
                        ...invalidPhoneCells,
                        ...invalidMandatoryCells,
                        ...invalidRecipientCells,
                        ...invalidProductCells


                    ...invalidScheduledDateCells,
                    ...invalidPersonalizationCells,
                    ...invalidProductCells
                    ];

                    // Set the current page to 1 and preview the CSV data with invalid cells highlighted
                    currentPage = 1;
                    previewCSVData(originalCsvData, currentPage, invalidCells, mapping);

                    // Update the UI to show the CSV preview and hide other forms
                    jQuery('#csv-preview').removeClass('d-none').show();
                    jQuery('#new-order-form').hide();
                    jQuery('#multi-step-form-bulk').addClass('d-none');
                    // console.log('outside doccc');

                    // Update UI based on validation results
                    if (invalidCells.length === 0) {
                        // console.log('inside doccc');
                        // If no errors, hide error-related UI elements and update the Next button
                        currentFilter = 'all';
                        jQuery('#filter-by').val('all').hide();
                        jQuery('#edit-errors, #remove-error-lines, #download-resubmit').hide();
                        isCorrectedView = true;
                        jQuery('#next-button').text('Confirm and Proceed →');
                    } else {
                        // If errors exist, show error-related UI elements
                        jQuery('.correct-rows-count, .error-rows-count').show();
                    }
                });
            } else {
                // Log an error if fetching recipient details fails
                console.error('Failed to fetch recipient details:', response.data);
            }
        }).catch(error => {
            // Log an error if the AJAX request fails
            console.error('Error fetching recipient details:', error);
        });*/
    }

    function continueWithValidationAndPreview(csvRecipients, csvProducts){
        const csvRecipientDetails = csvRecipients;
        const csvRecipientProducts = csvProducts;
        console.log('3: ',csvRecipientDetails);
        console.log('1.1: ',originalCsvData);
        const csvData = originalCsvData;
        
        invalidCells = [];

        const mandatoryHeaders = [
            'Recipient First Name', 'Delivery Method', 'Product Code', 'Gift Card Name', 'Gift Card Value',
            'Quantity', 'Personalisation'
        ];

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const phoneRegex = /^(?:\+61\s?4\d{2}\s?\d{3}\s?\d{3}|04\d{2}\s?\d{3}\s?\d{3})$/;
        //console.log('phoneRegex111');
        //console.log(phoneRegex);
        const allowedDeliveryMethods = [
            'phone',
            'email',
            'email,phone',
            'email & phone',
            'download',
            'trigger client send'
        ];

        const idIndex = csvData.headers.indexOf('Recipient ID');
        const emailIndex = csvData.headers.indexOf('Recipient Email Address');
        const phoneIndex = csvData.headers.indexOf('Recipient Phone Number');
        const firstNameIndex = csvData.headers.indexOf('Recipient First Name');
        const lastNameIndex = csvData.headers.indexOf('Recipient Surname');
        const deliveryMethodIndex = csvData.headers.indexOf('Delivery Method');

        const dateTimeIndex = csvData.headers.indexOf('Scheduled Delivery Date/Time');
        const personalisationIndex = csvData.headers.indexOf('Personalisation');

        const productCodeIndex = csvData.headers.indexOf('Product Code');
        const giftCardNameIndex = csvData.headers.indexOf('Gift Card Name');
        const giftCardValueIndex = csvData.headers.indexOf('Gift Card Value');


        if (deliveryMethodIndex === -1 || emailIndex === -1) 
            return invalidCells;

        const serverTimeString = user_fetch_ajax?.server_time || '';
        const serverDateRaw = new Date(serverTimeString.replace(' ', 'T'));
        const serverTime = new Date(
            serverDateRaw.getFullYear(),
            serverDateRaw.getMonth(),
            serverDateRaw.getDate(),
            serverDateRaw.getHours(),
            serverDateRaw.getMinutes()
        );

        // Minimum allowed = serverTime + 24 hours (to the minute)
        const minAllowedDate = new Date(serverTime.getTime() + 24 * 60 * 60 * 1000);

        for (let rowIndex = 0; rowIndex < csvData.data.length; rowIndex++) {

            const row = csvData.data[rowIndex];
            const deliveryMethod = row[deliveryMethodIndex]?.trim();
            const lowerDeliveryMethod = deliveryMethod?.toLowerCase();
            const recipientEmail = row[emailIndex]?.trim()?.toLowerCase();
            const recipientID = parseInt(row[idIndex]?.trim());
            let recipientPhone = row[phoneIndex]?.trim()?.replace(/\s+/g, '');

            const recipient = csvRecipientDetails[rowIndex];
            const recipientProducts = csvRecipientProducts[rowIndex];
            const checkEmail = lowerDeliveryMethod?.includes('email');
            const checkPhone = lowerDeliveryMethod?.includes('phone');

            /*console.log('^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^');
            console.log('rowIndex - colIndex: '+rowIndex);
            console.log(recipient);*/
            mandatoryHeaders.forEach(header => {
                const colIndex = csvData.headers.indexOf(header);
                //if (colIndex === -1) return;


                if (!row[colIndex] || row[colIndex].trim() === '') {
                    /*console.log('CHECK rowind: ',rowIndex);
                    console.log('CHECK colIndex: ',colIndex);
                    console.log('CHECK result: FALSE');*/
                    invalidCells.push({
                        rowIndex,
                        colIndex
                    });
                }
            });

            //console.log('############');

            if ( recipientPhone && (/^614\d{8}$/.test(recipientPhone) || /^61\d+$/.test(recipientPhone)) ) {
                recipientPhone = '+' + recipientPhone;
            }

            
            if ( deliveryMethod && !allowedDeliveryMethods.includes(deliveryMethod.toLowerCase()) ) {
                invalidCells.push({
                    rowIndex,
                    colIndex: deliveryMethodIndex,
                    error: 'Please enter a valid Delivery Method'
                });
            }

            // === Email validation
            if (checkEmail && emailIndex !== -1) {
                if (!recipientEmail || !emailRegex.test(recipientEmail)) {
                    invalidCells.push({
                        rowIndex,
                        colIndex: emailIndex,
                        error: !recipientEmail ? 'Email is required' : 'Invalid email format'
                    });
                }
            }

            // === Phone validation
            if (checkPhone && phoneIndex !== -1) {
                // console.log('recipient.phone: ',recipient);
                // console.log('recipientPhone: ',recipientPhone);
                // console.log('recipientPhone CHECK: ',phoneRegex.test(recipientPhone));
                if (!recipientPhone) {
                    invalidCells.push({
                        rowIndex,
                        colIndex: phoneIndex,
                        error: 'Phone number is required'
                    });
                } else if (!phoneRegex.test(recipientPhone)) {
                    invalidCells.push({
                        rowIndex,
                        colIndex: phoneIndex,
                        error: 'Invalid phone format'
                    });
                } else if (recipient && Object.keys(recipient).length > 0) {
                    // Only check against database if recipient exists
                    if (recipient.phone) {
                        const cleanRecipientPhone = recipient.phone?.replace(/\D/g, '') || '';
                        const cleanCsvPhone = recipientPhone.replace(/\D/g, '');

                        if (cleanCsvPhone !== cleanRecipientPhone) {
                            invalidCells.push({
                                rowIndex,
                                colIndex: phoneIndex,
                                error: 'Phone does not match recipient'
                            });
                        }
                    } else {
                        invalidCells.push({
                            rowIndex,
                            colIndex: phoneIndex,
                            error: 'Recipient does not have phone number'
                        });
                    }
                }
            }

            let dateTimeValue = row[dateTimeIndex]?.trim();
            if ( dateTimeValue === '00-00-0000 00:00' || dateTimeValue === '00/00/0000 00:00') {
            }else if( !dateTimeValue || dateTimeValue == null || dateTimeValue == '' ){
                invalidCells.push({
                    rowIndex,
                    colIndex: dateTimeIndex,
                    error: 'Scheduled date/time is required'
                });
            }

            if( dateTimeValue ){
                const match = dateTimeValue.match(/^(\d{2})[-\/](\d{2})[-\/](\d{4}) (\d{2}):(\d{2})$/);
                if (!match) {
                    invalidCells.push({
                        rowIndex,
                        colIndex: dateTimeIndex,
                        error: 'Invalid format (use DD/MM/YYYY HH:mm)'
                    });
                }
            }

            const personalisationValue = (row[personalisationIndex] || '').trim().toLowerCase();

            if (personalisationValue !== 'yes' && personalisationValue !== 'no') {
                invalidCells.push({
                    rowIndex,
                    colIndex : personalisationIndex,
                    error: 'Value must be Yes or No'
                });
            }

                console.log('=========================!!!!!');
                console.log('row: ',row);
                console.log('recipient: ',recipient);
                console.log('recipientProducts: ',recipientProducts);
            if( recipient && Object.keys(recipient).length > 0 ){
                if (firstNameIndex !== -1) {
                    const csvFirstName = row[firstNameIndex]?.trim()?.toLowerCase();
                    const recipientFirstName = recipient.first_name?.trim()?.toLowerCase();
                    console.log("csvFirstName:", csvFirstName);
                    console.log("recipientFirstName:", recipientFirstName);
                    if (recipientFirstName && csvFirstName !== recipientFirstName) {
                        invalidCells.push({
                            rowIndex,
                            colIndex: firstNameIndex,
                            error: 'First name does not match recipient'
                        });
                    }
                }

                if (lastNameIndex !== -1) {
                    const csvLastNameOriginal = row[lastNameIndex];
                    const recipientLastNameOriginal = recipient.last_name;

                    console.log("CSV Last Name (original):", csvLastNameOriginal);
                    console.log("Recipient Last Name (original):", recipientLastNameOriginal);

                    const csvLastName = csvLastNameOriginal?.trim()?.toLowerCase();
                    const recipientLastName = recipientLastNameOriginal?.trim()?.toLowerCase();

                    if (!recipientLastName && csvLastName) {
                        // recipient missing last name, but CSV has one
                        invalidCells.push({
                            rowIndex,
                            colIndex: lastNameIndex,
                            error: 'Surname does not match recipient'
                        });
                    } else if (csvLastName !== recipientLastName) {
                        // mismatch case
                        invalidCells.push({
                            rowIndex,
                            colIndex: lastNameIndex,
                            error: 'Surname does not match recipient'
                        });
                    }
                }

                const recipientUserBy_id = parseInt(recipient.user_by_id);
                const recipientUser_id = parseInt(recipient.user_id);
                
                if (idIndex !== -1) {
                    console.log('============++++========');
                    console.log(recipientUserBy_id);
                    console.log(recipientUser_id);

                    if ( parseInt(recipient.csv_user_id) > 0 && parseInt(recipientUserBy_id) !== parseInt(recipientUser_id)) {
                        invalidCells.push({
                            rowIndex,
                            colIndex: idIndex,
                            error: 'Recipient ID is not match with Email.'
                        });
                    }else if( parseInt(recipientUserBy_id) <= 0 && parseInt(recipientUser_id) <= 0 ){
                        
                    } else if ( parseInt(recipientUserBy_id) <= 0 && parseInt(recipientUser_id) > 0) {

                    }else if (recipientUserBy_id !== recipientUser_id) {
                        invalidCells.push({
                            rowIndex,
                            colIndex: idIndex,
                            error: 'Recipient ID is not match with Email.'
                        });
                    }
                }

                const recipientEmailBy_id = recipient.email_by_id?.trim()?.toLowerCase();;
                const recipientEmail = recipient.email?.trim()?.toLowerCase();
                if (emailIndex !== -1) {

                    /*console.log(recipientEmailBy_id);
                    console.log(recipientEmail);*/
                    
                    if ( !recipientEmailBy_id && !recipientEmail || ( parseInt(recipientUserBy_id) <= 0 && parseInt(recipientUser_id) > 0 ) ) {
                    }else if (recipientEmailBy_id != recipientEmail) {
                        invalidCells.push({
                            rowIndex,
                            colIndex: emailIndex,
                            error: 'Email does not match with Recipient ID.'
                        });
                    }
                }
            }

            if( recipientProducts && Object.keys(recipientProducts).length > 0 ){

                Object.entries(recipientProducts).forEach(([col, product]) => {
                    console.log(`col: ${col}, field: ${product.field}, Message: ${product.message}`);
                    invalidCells.push({
                        rowIndex,
                        colIndex : parseInt(col),
                        error: product.message
                    });
                });

                /*const pmessage = recipientProducts.message?.trim();
                console.log(pmessage);

                invalidCells.push({
                    rowIndex,
                    colIndex : recipientProducts.colIndex,
                    error: pmessage
                });*/
            }

        }
        
        console.log('INVALID: ',invalidCells);

        // currentPage = 1;
        previewCSVData(originalCsvData, currentPage, invalidCells);

        jQuery('#csv-preview').removeClass('d-none').show();
        jQuery('#new-order-form').hide();
        jQuery('#multi-step-form-bulk').addClass('d-none');
        if (invalidCells.length === 0) {
            currentFilter = 'all';
            jQuery('#filter-by').val('all').hide();
            jQuery('#edit-errors, #remove-error-lines, #download-resubmit').hide();
            isCorrectedView = true;
            jQuery('#next-button').text('Confirm and Proceed →');
        }else{
            jQuery('.correct-rows-count, .error-rows-count').show();
        }

        //return invalidCells;

    }

    function previewCSVData(csvData, page, invalidCells = []) {

        // Group errors by row and determine which rows to show based on filter
        const errorsByRow = {};
        invalidCells.forEach(error => {
            if (!errorsByRow[error.rowIndex]) {
                errorsByRow[error.rowIndex] = [];
            }
            errorsByRow[error.rowIndex].push(error);
        });

        // Filter rows based on current filter setting      
        const filteredRowIndices = [];
        csvData.data.forEach((row, rowIndex) => {
            const isErrorRow = errorsByRow[rowIndex] && errorsByRow[rowIndex].length > 0;

            if (currentFilter === 'all' ||
                (currentFilter === 'errors' && isErrorRow) ||
                (currentFilter === 'no-errors' && !isErrorRow)) {
                filteredRowIndices.push(rowIndex);
            }
        });

        // Calculate pagination based on filtered rows
        const totalRows = filteredRowIndices.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        
        if (!page || page < 1) page = 1;
        if (page > totalPages) page = totalPages || 1;
        currentPage = page;
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const visibleRowIndices = filteredRowIndices.slice(start, end);

        // Build headers HTML
        let headersHtml = `<th>No.</th>` + csvData.headers.map(header =>
            `<th>${header}</th>`
        ).join('');

        // Build rows HTML for visible rows only
        let rowsHtml = visibleRowIndices.map((originalRowIndex, displayIndex) => {
            const row = csvData.data[originalRowIndex];
            const rowErrors = errorsByRow[originalRowIndex] || [];
            const isErrorRow = rowErrors.length > 0;

            return `<tr class="${isErrorRow ? 'has-errors' : ''}">` +
                `<td>${originalRowIndex + 1}</td>` + // Original row number
                row.map((cell, colIndex) => {
                    const cellError = rowErrors.find(e => e.colIndex === colIndex);
                    const cellKey = `${originalRowIndex}-${colIndex}`;
                    let cellContent = editedData[cellKey] || cell;


                    const headerName = csvData.headers[colIndex];

                    // If column is 'Recipient Phone Number' and doesn't start with '+', prepend it
                    if (headerName === 'Recipient Phone Number' && cellContent && !cellContent.startsWith('+')) {
                        if (cellContent.startsWith('61')) {
                            cellContent = '+' + cellContent;
                        }
                        // Also update the underlying CSV data to reflect this change
                        csvData.data[originalRowIndex][colIndex] = cellContent;
                        originalCsvData.data[originalRowIndex][colIndex] = cellContent;
                    }

                    if (cellError) {
                        return `<td class="error-cell ${cellKey}" 
                                data-error="${cellError.error || 'Invalid value'}"
                                data-key="${cellKey}"
                                contenteditable="${editMode}">
                            ${cellContent}
                        </td>`;
                    }
                    return `<td class="${cellKey}">${cellContent}</td>`;
                }).join('') +
            '</tr>';
        }).join('');

        // Update the table
        jQuery('#csv-preview-table thead').html(`<tr>${headersHtml}</tr>`);
        jQuery('#csv-preview-table tbody').html(rowsHtml);
        
        // function renderPagination(totalPages, currentPage) {
        //     let paginationHtml = '<ul class="pagination">';
            
        //     // Previous button
        //     paginationHtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        //         <a href="#" class="page-link" data-page="${currentPage - 1}">&laquo; Previous</a></li>`;
            
        //     // Page numbers
        //     for (let i = 1; i <= totalPages; i++) {
        //         paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
        //             <a href="#" class="page-link" data-page="${i}">${i}</a></li>`;
        //     }

        //     // Next button
        //     paginationHtml += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        //         <a href="#" class="page-link" data-page="${currentPage + 1}">Next &raquo;</a></li>`;

        //     paginationHtml += '</ul>';
        //     $('#csv-preview #pagination').html(paginationHtml);

        //     // Click event
        //     $('#csv-preview #pagination .page-link').off('click').on('click', function (e) {
        //         e.preventDefault();
        //         const newPage = parseInt($(this).data('page'));
        //         if (newPage && newPage >= 1 && newPage <= totalPages && newPage !== currentPage) {
        //             currentPage = newPage;
        //             previewCSVData(originalCsvData, currentPage, invalidCells);
        //         }
        //     });
        // }
        function renderPagination(totalPages, currentPage) {
            let paginationHtml = '<ul class="pagination">';

            // Previous button
            paginationHtml += `<li class="page-item prev ${currentPage === 1 ? 'disabled' : ''}">
                <a href="#" class="page-link prev-link" data-page="${currentPage - 1}"><</a>
            </li>`;

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                paginationHtml += `<li class="page-item number ${i === currentPage ? 'active' : ''}">
                    <a href="#" class="page-link number-link" data-page="${i}">${i}</a>
                </li>`;
            }

            // Next button
            paginationHtml += `<li class="page-item next ${currentPage === totalPages ? 'disabled' : ''}">
                <a href="#" class="page-link next-link" data-page="${currentPage + 1}">></a>
            </li>`;

            paginationHtml += '</ul>';
            $('#csv-preview #pagination').html(paginationHtml);

            // Click event
            $('#csv-preview #pagination .page-link').off('click').on('click', function (e) {
                e.preventDefault();
                const newPage = parseInt($(this).data('page'));
                if (newPage && newPage >= 1 && newPage <= totalPages && newPage !== currentPage) {
                    currentPage = newPage;
                    previewCSVData(originalCsvData, currentPage, invalidCells);
                }
            });
        }



        if (totalPages <= 1) {
            jQuery('#csv-preview #pagination').html('');
        } else {
            renderPagination(totalPages, page);
        }

        // Show message if no rows match filter
        if (filteredRowIndices.length === 0) {
            const message = currentFilter === 'errors' ?
                'No rows with errors found' :
                'No rows without errors found please select another CSV file.';
            jQuery('#csv-preview-table tbody').html(
                `<tr><td colspan="${csvData.headers.length + 1}" class="text-center">${message}</td></tr>`
            );
            jQuery('#next-button').hide();
        }

        // Update counts and UI
        updateRowCounts(csvData, invalidCells);
        updateNextButtonState(invalidCells);

        // ✅ Move button visibility logic here
        if (invalidCells.length === 0) {
            currentFilter = 'all';
            jQuery('#filter-by').val('all').hide();
            jQuery('#edit-errors, #remove-error-lines, #download-resubmit').hide();
            isCorrectedView = true;
            jQuery('#next-button').text('Confirm and Proceed →');
        } else {
            jQuery('#edit-errors, #remove-error-lines, #download-resubmit').show();
            jQuery('.correct-rows-count, .error-rows-count').show();
        }
    }

    function updateNextButtonState(invalidCells) {
        if (invalidCells.length === 0) {
            jQuery('#next-button').prop('disabled', false).css('opacity', 1);
            jQuery('#edit-errors').hide();
        } else {
            jQuery('#next-button').prop('disabled', true).css('opacity', 0.5);
            jQuery('#edit-errors').show();
        }
    }

    function updateRowCounts(csvData, invalidCells) {
        const cellMap = new Map();
        invalidCells.forEach(cell => {
            const key = `${cell.rowIndex}-${cell.colIndex}`;
            if (!cellMap.has(key)) {
                cellMap.set(key, cell);
            }
        });

        const uniqueInvalidCells = Array.from(cellMap.values());
        const totalRows = csvData.data.length;


        // ✅ Prevent showing counts when no data exists
        if (totalRows === 0) {
            jQuery('#correct-rows-count').hide().empty();
            jQuery('#error-rows-count').hide().empty();
            return;
        }

        const errorRowsSet = new Set(uniqueInvalidCells.map(cell => cell.rowIndex));
        const errorRows = errorRowsSet.size;
        const correctRows = totalRows - errorRows;
        const totalFieldErrors = uniqueInvalidCells.length;

        const $correctBadge = jQuery('#correct-rows-count');
        const $errorBadge = jQuery('#error-rows-count');

        // Clear messages
        $correctBadge.hide().empty();
        $errorBadge.hide().empty();

        // Case 1: All lines have errors
        if (correctRows === 0 && errorRows > 0) {
            $errorBadge
                .html(`✗ We found ${totalFieldErrors} ${totalFieldErrors === 1 ? 'error' : 'errors'} on ${errorRows} ${errorRows === 1 ? 'line' : 'lines'}.`)
                .show();
            return; // Don’t show success message
        }

        // Case 2: Some lines correct, some lines have errors
        if (correctRows > 0 && errorRows > 0) {
            $correctBadge
                .html(`✓ ${correctRows} ${correctRows === 1 ? 'line has' : 'lines have'} been uploaded successfully without errors.`)
                .show();
            $errorBadge
                .html(`✗ We found ${totalFieldErrors} ${totalFieldErrors === 1 ? 'error' : 'errors'} on ${errorRows} ${errorRows === 1 ? 'line' : 'lines'}.`)
                .show();
            return;
        }

        // Case 3: All lines correct
        if (correctRows === totalRows) {
            $correctBadge
                .html(`✓ All ${correctRows} ${correctRows === 1 ? 'line is' : 'lines are'} valid and uploaded successfully.`)
                .show();
        }
    }

    jQuery('#apply-mapping').on('click', function () {
            const updatedHeaders = [];
            const mandatoryHeaders = [
                'Recipient First Name', 'Delivery Method', 'Recipient Email Address',
                'Recipient Phone Number', 'Product Code', 'Gift Card Name', 'Gift Card Value',
                'Quantity', 'Personalisation'
            ];

            let missingMandatoryFields = [];
            let selectedValues = {}; // Track selected values to prevent duplicates

            $('.mapping-select').each(function () {
                const selectedHeader = $(this).val();
                const templateHeader = $(this).data('template');

                // Check for mandatory fields
                if (mandatoryHeaders.includes(templateHeader) && !selectedHeader) {
                    missingMandatoryFields.push(templateHeader);
                }

                // Check for duplicate selections
                if (selectedHeader && selectedValues[selectedHeader]) {
                    // If the value is already selected, clear the previous selection
                    $(`.mapping-select[data-template="${selectedValues[selectedHeader]}"]`).val('');
                    selectedValues[selectedHeader] = templateHeader; // Update the selected value
                } else if (selectedHeader) {
                    selectedValues[selectedHeader] = templateHeader; // Track the selected value
                }

                updatedHeaders.push({
                    template: templateHeader,
                    selected: selectedHeader
                });
            });

            // Show error if mandatory fields are missing
            if (missingMandatoryFields.length > 0) {
                $('#mandatory-warning').text(`⚠️ Mandatory fields missing: ${missingMandatoryFields.join(', ')}`);
                return;
            }

            // Apply the mapping and show the preview
            applyMappingAndPreview(Object.fromEntries(updatedHeaders.map(h => [h.template, h.selected])));
            $('#mapping-modal').modal('hide');
    });

    jQuery('#csv-preview-table tbody').on('blur', 'td[contenteditable="true"]', function () {
        const cell = $(this);
        const cellKey = cell.data('key');
        const [rowIndexStr, colIndexStr] = cellKey.split('-').map(Number);
        const rowIndex = parseInt(rowIndexStr);
        const colIndex = parseInt(colIndexStr);
        const newValue = cell.text().trim();
        originalCsvData.data[rowIndex][colIndex] = newValue;

        const headerName = originalCsvData.headers[colIndex];

        let emailKeyupTimeout;
        let idKeyupTimeout;

        const recipientIds = extractRecipientIds(originalCsvData);
        const recipientEmails = extractRecipientEmails(originalCsvData);
        const recipientProducts = extractRecipientProducts(originalCsvData);

        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'get_recipient_details_by_emails',
                recipient_ids: recipientIds,
                recipient_emails: recipientEmails,
                recipient_products: recipientProducts
            },
            success: function (response) {
                if (response.success && response.data) {
                    csvRecipientDetails = response.data.data;
                    csvRecipientProducts = response.data.productData;
                    continueWithValidationAndPreview(response.data.data, response.data.productData);
                    //invalidCells = continueWithValidationAndPreview(response.data.data, originalCsvData);
                    //console.log('INVALID in AJAX: ',invalidCells);
                    console.log('1: ',response.data.data);
                } else {
                    console.warn("Recipient details fetch failed:", response);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error fetching recipients:", error);
            }
        });
        // Remove the editing class
        $(this).removeClass('editing');
    });

    jQuery('#remove-error-lines').on('click', function () {
        const $button = $(this);
        const originalText = $button.text();
        $button.text('Removing...').prop('disabled', true);

        editMode = false;
        jQuery('#edit-errors').text('Edit Errors');

        // Collect indexes of error rows in originalCsvData
        const errorIndexes = [];
        originalCsvData.data.forEach((row, rowIndex) => {
            const rowErrors = invalidCells.filter(cell => cell.rowIndex === rowIndex);
            if (rowErrors.length > 0) {
                errorIndexes.push(rowIndex);
            }
        });

        // Remove error rows safely
        errorIndexes.sort((a, b) => b - a).forEach(idx => {
            originalCsvData.data.splice(idx, 1);
        });

        console.log('Updated originalCsvData: ', originalCsvData);

        // Reset page and filter
        currentPage = 1;
        currentFilter = 'all';
        $('#filter-by').val('all').hide();
        isCorrectedView = true;

        // Reset invalidCells since errors are removed
        invalidCells = [];

        // Re-render table with updated data and pagination
        previewCSVData(originalCsvData, currentPage, invalidCells);

        // Hide buttons that no longer apply
        $('#edit-errors, #remove-error-lines, #download-resubmit').hide();

        updateNextButtonState(invalidCells);

        $button.text(originalText).prop('disabled', false);
    });



    jQuery('#edit-errors').on('click', function () {
        editMode = !editMode; // Toggle edit mode
        // Update button text
        $(this).text(editMode ? 'Cancel Editing' : 'Edit Errors');

        jQuery('#csv-preview-table td.error-cell').each(function () {
            $(this).attr('contenteditable', editMode);
            if (editMode) {
                $(this).css('background-color', '#fff3cd');
            } else {
                $(this).css('background-color', '');
            }
        });        
    });

    jQuery('.bulk-order-management-header .action-buttons #download-resubmit').on('click', function () {
        if (!invalidCells || invalidCells.length === 0) {
            alert("No invalid rows to download.");
            return;
        }

        // Group invalid cells by row index
        const errorRowsSet = new Set(invalidCells.map(cell => cell.rowIndex));
        const errorRows = Array.from(errorRowsSet).map(rowIndex => originalCsvData.data[rowIndex]);

        // Prepare CSV content
        let csvContent = "data:text/csv;charset=utf-8," + [
            originalCsvData.headers.join(","), // headers
            ...errorRows.map(e => e.join(",")) // only invalid rows
        ].join("\n");

        // Encode and trigger download
        let encodedUri = encodeURI(csvContent);
        let link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "invalid_rows.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    var currentStep = 'bulk-upload';

    jQuery('#next-button').on('click', function () {
        if (!isCorrectedView) {
            // First click for corrected view (if needed)
            currentFilter = 'all';
            $('#filter-by').val('all').hide();
            $('#edit-errors, #remove-error-lines, #download-resubmit, .correct-rows-count, .error-rows-count').hide();
            previewCSVData(originalCsvData, 1);
            isCorrectedView = true;
            $(this).text('Confirm and Proceed →');
        } else {
            // Proceed to activation
            $('#csv-preview').addClass('d-none');
            $('#card-activation-form').removeClass('d-none');
            currentStep = 'card-activation';
            $(this).hide();

            // Prefill the sender dropdown in the activation form
            if (selectedSender) {
                $('#card-activation-form #sender-dropdown').val(selectedSender);
            }

            // Also prefill other fields that were passed from the order form
            $('#display-order-name').text($('#order-name').val());
            $('#display-order-id').text($('#order-id').val());
            $('#display-client-reference').text($('#client-reference').val());
        }
    });

    
    const clientRef = document.getElementById('client-reference');
    const orderRef = document.querySelector('.order-reference');
    const orderRefFloat = document.getElementById('order_reference');
    const displayClientRef = document.getElementById('display-client-reference');

    if (clientRef && orderRef && orderRefFloat && displayClientRef) {
        // When client reference changes
        clientRef.addEventListener('input', function() {
            orderRef.value = this.value;
            orderRefFloat.value = this.value;
            displayClientRef.textContent = this.value || '—'; // update display
        });

        // When order reference changes
        orderRef.addEventListener('input', function() {
            clientRef.value = this.value;
            displayClientRef.textContent = this.value || '—'; // update display
        });
        orderRefFloat.addEventListener('input', function() {
            clientRef.value = this.value;
            displayClientRef.textContent = this.value || '—'; // update display
        });
    }

    const relatedPo = document.getElementById('related-po');
    const clientPo = document.querySelector('.client-po');

    if (relatedPo && clientPo) {
        // Sync Client → Order
        relatedPo.addEventListener('input', function() {
            clientPo.value = this.value;
        });

        // Sync Order → Client
        clientPo.addEventListener('input', function() {
            relatedPo.value = this.value;
        });
    }

    jQuery('#card-activation-form #next-step, #card-activation-form #save-draft-bulk-card-activation').on('click', function () {
        if (currentStep === 'card-activation' && originalCsvData && originalCsvData.data.length > 0) {
            // console.log('Testing 1: Condition passed');

            var data_action = jQuery(this).data('action');

            const csvData = {
                headers: originalCsvData.headers,
                data: originalCsvData.data
            };
            const form = document.getElementById('bulk-card-activation-form');
            const formData = new FormData(form);
            let cardActivationData = {};

            formData.forEach((value, key) => {
                cardActivationData[key] = value;
            });

            const previewImg = document.querySelector('.selected-design-card-preview img');
            if (previewImg && previewImg.src) {
                cardActivationData['gift_card_image'] = previewImg.src; // replace file with preview src
            }
            const applyPersonalisation = document.getElementById('apply-personalisation');
            cardActivationData['apply_personalisation'] = applyPersonalisation.checked ? 1 : 0;

            // console.log('Testing Form Data :', formData);
            $('<input>', {
                type: 'hidden',
                class: 'bulk-order-flow',
                name: 'bulk_order_flow',
                value: 'bulk-order-flow'
            }).appendTo('#card-activation-form');
            jQuery('#back-to-order-form').hide();
            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'process_bulk_order_data',
                    csv_data: JSON.stringify(csvData),
                    form_data: JSON.stringify(cardActivationData),
                    security: '<?php echo wp_create_nonce("bulk_order_nonce"); ?>'
                },
                success: function (response) {
                    // console.log('Testing 3: AJAX success:', response);

                    if (response.success) {
                        // console.log('Testing 4: Response success');
                        // Hide the card activation form
                        if( data_action != 'save-draft' ){
                            $('#card-activation-form').addClass('d-none');
                        }

                        // Show the correct parent container based on the flow (manual or bulk)
                        if (currentStep === 'card-activation' && data_action != 'save-draft') {
                            $('#multi-step-form-bulk').addClass('d-none'); // hide bulk upload container
                            $('#csv-preview-container').addClass('d-none');
                            $('.table-container').addClass('d-none');
                            $('.table-container').addClass('d-none');
                            $('.gift-card-container').addClass('d-none');
                            $('#save-and-next-btn').addClass('d-none');
                            $('#multi-step-form').removeClass('d-none');
                            $('#multi-step-form').addClass('full-width');
                            // console.log('ashjhdjshjad'); // Show manual order container
                        }

                        if( data_action != 'save-draft' ){
                            // Show the customization form
                            $('.customisation-container').show();
                            // Update the step indicator
                            const activeStep = document.querySelector(".step.active-step");
                            // console.log('activeStep----', activeStep);
                            if (activeStep) activeStep.classList.remove("active-step");
                            const customizationStep = document.querySelector(".step-indicator .step:nth-child(2)");
                            if (customizationStep) customizationStep.classList.add("active-step");

                            // Reset "Personalise All" to unchecked
                            const personaliseAllCheckbox = document.getElementById("personalise-all");
                            if (personaliseAllCheckbox) personaliseAllCheckbox.checked = false;
                        }

                        // Trigger a custom event with the data
                        const event = new CustomEvent('bulkDataLoaded', {
                            detail: {
                                rows: response.data.rows,
                                form_data: response.data.form_data
                            }
                        });
                        // console.log(event);
                        // IMPORTANT : This data will set using AJAX and will recieved in the customisation.js file on line no : 345 (Function file code line no : 3316) 
                        document.dispatchEvent(event);

                        // Explicitly show checkboxes after creation
                        document.querySelectorAll(".gift-card-checkbox input").forEach(checkbox => {
                            checkbox.style.display = "inline-block"; // Force visibility
                        });

                        if( data_action == 'save-draft' && jQuery('.customisation-container #delivery-save-btn').length ){
                            setTimeout(() => {
                                jQuery('.customisation-container #delivery-save-btn').trigger('click');
                            }, 300);
                        }else{
                            jQuery('.customisation-container #delivery-next-btn').trigger('click');
                            $('#multi-step-form > .container').removeAttr('style');
                        }
                    } else {
                        // console.log('Testing 5: Response error:', response.data.message);
                        alert('Error processing bulk order data: ' + response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    // console.log('Testing 6: AJAX error:', error);
                    console.error('AJAX Error:', error);
                    alert('An error occurred while processing the bulk order data.');
                }
            });
        }
    });

    // Modify the business user dropdown change handler to populate both sender dropdowns
    jQuery('#business-user-dropdown').on('change', function () {
        let selectedUserId = $(this).val();
        let senderDropdown = $('#sender-dropdown');
        let selectSenderDropdown = $('#select-sender-dropdown');
        let activationSenderDropdown = $('#card-activation-form #sender-dropdown');

        // Show loading text
        senderDropdown.html('<option selected disabled>Loading...</option>');
        selectSenderDropdown.html('<option selected disabled>Loading...</option>');
        activationSenderDropdown.html('<option selected disabled>Loading...</option>');

        setTimeout(() => {
            senderDropdown.empty().append('<option selected disabled>Select sender</option>');
            selectSenderDropdown.empty().append('<option selected disabled>Select sender</option>');
            activationSenderDropdown.empty().append('<option selected disabled>Select sender</option>');

            if (selectedUserId && businessUserData[selectedUserId]) {
                const senders = businessUserData[selectedUserId]['senders'];

                senders.forEach((sender, index) => {
                    const optionHTML = `<option value="${sender.name}" data-email="${sender.email}">${sender.name}</option>`;
                    senderDropdown.append(optionHTML);
                    selectSenderDropdown.append(optionHTML);
                    activationSenderDropdown.append(optionHTML);
                });

                // ✅ Automatically select the first available sender
                if (senders.length > 0) {
                    senderDropdown.val(senders[0].name).trigger('change');
                    selectSenderDropdown.val(senders[0].name).trigger('change');
                    activationSenderDropdown.val(senders[0].name).trigger('change');
                }
            }
        }, 300);


        // Fetch campaigns via AJAX
        fetch(userSearchAjax.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'get_user_campaigns',
                user_id: selectedUserId,
            })
        })
        .then(res => res.json())
        .then(data => {
            const campaignDropdown = document.getElementById("campaign-dropdown");
            campaignDropdown.innerHTML = '<option disabled selected>Select campaign</option>';

            if (data.success && Array.isArray(data.data.campaigns) && data.data.campaigns.length > 0) {
                data.data.campaigns.forEach(campaign => {
                    const option = document.createElement("option");
                    option.value = campaign;
                    option.textContent = campaign;
                    campaignDropdown.appendChild(option);
                });
            } else {
                const option = document.createElement("option");
                option.disabled = true;
                option.textContent = 'No campaigns found';
                campaignDropdown.appendChild(option);
            }
        })
        .catch(err => {
            console.error("Error fetching campaigns", err);
        });
    });

    // Sync selected sender between both dropdowns
    jQuery('#sender-dropdown').on('change', function () {
        selectedSender = $(this).val();
        // Also update the display sender
        $('#display-sender').text(selectedSender);
    });

    jQuery('#select-sender-dropdown').on('change', function () {
        selectedSender = $(this).val();
        $('#display-sender').text(selectedSender);
    });


    // Data Table for the csv-preview-table table
    // setTimeout(() => {
    //     console.log('Dtaa table...');
    //     dataTableCode = jQuery ('#csv-preview-table'); 
    //     if(dataTableCode.length > 0){
    //         console.log('Dtaa table found...');
    //         $('#csv-preview-table').DataTable({
    //             "pageLength": 21
    //         });
    //     }else{
    //         console.log('Dtaa table Not found...');
    //     }
        
    // }, 20000); 
});
</script>