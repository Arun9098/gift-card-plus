jQuery(document).ready(function($) {
    'use strict';

    // Date picker initialization
    $('.datepicker').datepicker({
        dateFormat: 'mm/dd/yy',
        changeMonth: true,
        changeYear: true,
    });

    // Image upload - simple drag & drop
    var $imageBox = $('#image-upload-box');
    var $imageIdInput = $('#offer_image_id');
    var $urlSection = $('#url-upload-section');
    var isDragging = false;

    // Initialize has-image class if image already exists
    if ($imageBox.find('.uploaded-image').length) {
        $imageBox.addClass('has-image');
    }

    // Show URL input when "Link" is clicked (use event delegation)
    $(document).on('click', '#upload-link-trigger', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $urlSection = $('#url-upload-section');
        if ($urlSection.length) {
            $urlSection.slideDown(200);
            setTimeout(function() {
                $('#image-url-input').focus();
            }, 250);
        } else {
            console.error('URL upload section not found');
        }
    });

    // Cancel URL upload
    $('#cancel-url-upload').on('click', function() {
        $urlSection.slideUp(200);
        $('#image-url-input').val('');
        $('#url-upload-status').empty();
    });

    // Drag and drop functionality directly on image-upload-box
    $imageBox.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!$imageBox.hasClass('has-image')) {
            $(this).addClass('dragover');
            isDragging = true;
        }
    });

    $imageBox.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        isDragging = false;
    });

    $imageBox.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        isDragging = false;

        if ($imageBox.hasClass('has-image')) {
            return; // Don't allow drop if image already exists
        }

        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    });

    // Click to select file (only when empty, but not on link)
    $imageBox.on('click', function(e) {
        if ($(e.target).closest('#remove-image').length || 
            $(e.target).closest('#upload-link-trigger').length ||
            $imageBox.hasClass('has-image')) {
            return;
        }
        // Create file input and trigger click
        var $fileInput = $('<input type="file" accept="image/svg+xml,image/png,image/jpeg,image/jpg,image/gif" style="display: none;">');
        $('body').append($fileInput);
        $fileInput.on('change', function() {
            if (this.files && this.files.length > 0) {
                handleFileUpload(this.files[0]);
            }
            $fileInput.remove();
        });
        $fileInput.click();
    });

    // Handle file upload - store as base64 temporarily (NO AJAX - NO MEDIA UPLOAD)
    function handleFileUpload(file) {
        // Validate file type
        var validTypes = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Invalid file type. Please upload SVG, PNG, JPG or GIF files only.');
            return;
        }

        // Validate file size (3MB = 3145728 bytes)
        if (file.size > 3145728) {
            alert('File size exceeds 3MB limit. Please upload a smaller file.');
            return;
        }

        // Show loading state on image-upload-box
        $imageBox.html('<div class="upload-loading">Processing...</div>');

        // Read file as base64 - NO AJAX CALL - NO MEDIA UPLOAD
        var reader = new FileReader();
        reader.onload = function(e) {
            var base64Data = e.target.result;
            var filename = file.name;
            
            // Store base64 data and filename in hidden fields (temporary - not saved to media)
            $('#offer_image_data').val(base64Data);
            $('#offer_image_filename').val(filename);
            
            // Clear any existing image ID (we'll create new one on save)
            $('#offer_image_id').val('');
            
            // Display the image preview
            setSelectedImagePreview(base64Data);
        };
        reader.onerror = function() {
            alert('Error reading file. Please try again.');
            resetImageBox();
        };
        // Convert to base64 - this does NOT upload to media library
        reader.readAsDataURL(file);
    }

    function resetImageBox() {
        $imageBox.removeClass('has-image').html(
            '<div class="upload-placeholder" id="upload-placeholder">' +
            '<span class="upload-icon">📄</span>' +
            '<p>Drag and drop image here</p>' +
            '<p class="upload-hint">SVG, PNG, JPG or GIF (max. 3MB)</p>' +
            '<p class="upload-link-text"><a href="#" id="upload-link-trigger">Link</a></p>' +
            '</div>'
        );
        // Clear temporary data
        $('#offer_image_data').val('');
        $('#offer_image_filename').val('');
    }

    function setSelectedImage(imageId, imageUrl) {
        $imageIdInput.val(imageId);
        // Clear temporary data if using existing image
        $('#offer_image_data').val('');
        $('#offer_image_filename').val('');
        $imageBox.addClass('has-image').html(
            '<img src="' + imageUrl + '" alt="Offer Image" class="uploaded-image">' +
            '<button type="button" class="remove-image" id="remove-image">×</button>'
        );
    }

    function setSelectedImagePreview(imageDataUrl) {
        $imageIdInput.val(''); // Clear existing ID
        $imageBox.addClass('has-image').html(
            '<img src="' + imageDataUrl + '" alt="Offer Image" class="uploaded-image">' +
            '<button type="button" class="remove-image" id="remove-image">×</button>'
        );
    }

    // Remove image
    $(document).on('click', '#remove-image', function(e) {
        e.stopPropagation(); // Prevent opening modal
        $imageIdInput.val('');
        resetImageBox();
    });

    // Upload from URL - store as base64 temporarily
    $('#upload-from-url').on('click', function() {
        var imageUrl = $('#image-url-input').val().trim();
        
        if (!imageUrl) {
            $('#url-upload-status').html('<div class="url-error">Please enter an image URL</div>');
            return;
        }
        
        // Validate URL format
        try {
            new URL(imageUrl);
        } catch (e) {
            $('#url-upload-status').html('<div class="url-error">Please enter a valid URL</div>');
            return;
        }
        
        // Show loading
        $('#url-upload-status').html('<div class="url-loading">Loading image from URL...</div>');
        $('#upload-from-url').prop('disabled', true);
        
        // Create an image element to load the URL and convert to base64
        var img = new Image();
        img.crossOrigin = 'anonymous';
        
        img.onload = function() {
            try {
                // Create canvas to convert image to base64
                var canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                var base64Data = canvas.toDataURL('image/png');
                var filename = imageUrl.split('/').pop().split('?')[0] || 'image.png';
                
                // Store base64 data and filename in hidden fields
                $('#offer_image_data').val(base64Data);
                $('#offer_image_filename').val(filename);
                
                // Clear any existing image ID
                $('#offer_image_id').val('');
                
                // Display the image preview
                setSelectedImagePreview(base64Data);
                $urlSection.slideUp(200);
                $('#image-url-input').val('');
                $('#url-upload-status').empty();
                $('#upload-from-url').prop('disabled', false);
            } catch (e) {
                $('#upload-from-url').prop('disabled', false);
                $('#url-upload-status').html('<div class="url-error">Error processing image. Please try a different URL or upload the image directly.</div>');
            }
        };
        
        img.onerror = function() {
            $('#upload-from-url').prop('disabled', false);
            $('#url-upload-status').html('<div class="url-error">Error loading image from URL. The image may be blocked by CORS or the URL may be invalid.</div>');
        };
        
        img.src = imageUrl;
    });
    
    // Allow Enter key to trigger URL upload
    $('#image-url-input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#upload-from-url').click();
        }
    });

    // Showcase type toggle
    $('#offer_showcase_type').on('change', function() {
        var value = $(this).val();
        if (value === 'promo_code') {
            $('#promo-code-field').show();
            $('#link-field').hide();
        } else if (value === 'link') {
            $('#promo-code-field').hide();
            $('#link-field').show();
        }
    });

    // Tags management
    var tags = [];
    var $tagsInput = $('#offer_tags');
    var $tagsList = $('#tags-list');
    var $tagInput = $('#offer_tag_input');

    // Load existing tags
    var existingTags = $tagsInput.val();
    if (existingTags) {
        tags = existingTags.split(',').filter(function(tag) {
            return tag.trim() !== '';
        });
        updateTagsDisplay();
    }

    $('#add-tag-btn').on('click', function() {
        var tag = $tagInput.val().trim();
        if (tag && tags.indexOf(tag) === -1) {
            tags.push(tag);
            updateTagsDisplay();
            $tagInput.val('');
        }
    });

    $tagInput.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#add-tag-btn').click();
        }
    });

    $(document).on('click', '.tag-remove', function() {
        var tag = $(this).data('tag');
        tags = tags.filter(function(t) {
            return t !== tag;
        });
        updateTagsDisplay();
    });

    function updateTagsDisplay() {
        $tagsList.empty();
        tags.forEach(function(tag) {
            $tagsList.append(
                '<span class="tag-pill">' +
                tag +
                '<span class="tag-remove" data-tag="' + tag + '">×</span>' +
                '</span>'
            );
        });
        $tagsInput.val(tags.join(','));
    }

    // Track selected products
    var selectedProducts = {};
    
    // Store product data for later use
    var productDataCache = {};
    
    // Initialize selected products from existing hidden inputs
    $('input[name="offer_products[]"]').each(function() {
        var productId = $(this).val();
        selectedProducts[productId] = true;
    });

    // Product search - show on focus or input
    var searchTimeout;
    var $searchInput = $('#product-search');
    var $productsList = $('#products-list');
    
    // Show products list on focus
    $searchInput.on('focus', function() {
        if ($(this).val().length < 2) {
            // Load all products or recent products
            searchProducts('');
        }
    });

    // Search on input
    $searchInput.on('input', function() {
        var search = $(this).val();
        clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(function() {
            searchProducts(search);
        }, 300);
    });

    function searchProducts(search) {
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'search_products',
                search: search,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    displayProducts(response.data || []);
                } else if (Array.isArray(response)) {
                    displayProducts(response);
                }
            },
            error: function() {
                console.log('Error searching products');
            }
        });
    }

    function displayProducts(products) {
        $productsList.empty();

        if (products.length === 0) {
            $productsList.html('<p class="no-results">No products found</p>');
            return;
        }

        products.forEach(function(product) {
            // Store product data in cache
            productDataCache[product.id] = {
                id: product.id,
                name: product.name,
                sku: product.sku || '-',
                price: product.price || '0'
            };
            
            var isSelected = selectedProducts[product.id] === true;
            var $item = $(
                '<div class="product-item' + (isSelected ? ' product-selected' : '') + '" data-product-id="' + product.id + '">' +
                '<div class="product-item-info">' +
                '<span class="product-name">' + product.name + '</span>' +
                '<span class="product-sku">SKU: ' + (product.sku || '-') + '</span>' +
                '<span class="product-price">Price: $' + (product.price || '0') + '</span>' +
                '</div>' +
                (isSelected ? 
                    '<span class="product-status-badge">Already Added</span>' :
                    '<button type="button" class="btn-add-product" data-product-id="' + product.id + '" data-product-name="' + product.name + '" data-product-sku="' + (product.sku || '-') + '" data-product-price="' + (product.price || '0') + '">Add</button>'
                ) +
                '</div>'
            );
            $productsList.append($item);
        });
    }

    // Add product to selected list
    $(document).on('click', '.btn-add-product', function() {
        var productId = $(this).data('product-id');
        
        // Get product data from cache or button data attributes
        var productData = productDataCache[productId];
        if (!productData) {
            // Fallback to button data attributes if cache is empty
            productData = {
                id: productId,
                name: $(this).data('product-name') || 'Unknown Product',
                sku: $(this).data('product-sku') || '-',
                price: $(this).data('product-price') || '0'
            };
        }
        
        var productName = productData.name;
        var productSku = productData.sku;
        var productPrice = productData.price;

        if (selectedProducts[productId]) {
            return; // Already selected
        }

        // Add to selected products
        selectedProducts[productId] = true;

        // Add to selected products list
        var $selectedList = $('#selected-products-list');
        if ($selectedList.find('.no-products-message').length) {
            $selectedList.empty();
        }

        var $selectedItem = $(
            '<div class="selected-product-item" data-product-id="' + productId + '">' +
            '<input type="hidden" name="offer_products[]" value="' + productId + '">' +
            '<div class="product-info">' +
            '<span class="product-name">' + productName + '</span>' +
            '<span class="product-details">SKU: ' + productSku + ' | Price: $' + productPrice + '</span>' +
            '</div>' +
            '<button type="button" class="btn-remove-product" data-product-id="' + productId + '" title="Remove product">×</button>' +
            '</div>'
        );
        $selectedList.append($selectedItem);

        // Update search results to show product as selected
        $('.product-item[data-product-id="' + productId + '"]')
            .addClass('product-selected')
            .find('.btn-add-product')
            .replaceWith('<span class="product-status-badge">Already Added</span>');

        // Update products table if exists
        updateProductsTable(productId, productName, productSku, productPrice);
    });

    // Remove product from selected list
    $(document).on('click', '.btn-remove-product', function() {
        var productId = $(this).data('product-id');
        var $item = $(this).closest('.selected-product-item');
        
        // Remove from selected products
        delete selectedProducts[productId];

        // Remove from selected list
        $item.remove();

        // If no products selected, show message
        if ($('#selected-products-list .selected-product-item').length === 0) {
            $('#selected-products-list').html('<p class="no-products-message">No products selected. Search and add products below.</p>');
        }

        // Update search results to show product as available
        var productData = productDataCache[productId];
        if (productData) {
            $('.product-item[data-product-id="' + productId + '"]')
                .removeClass('product-selected')
                .find('.product-status-badge')
                .replaceWith(
                    '<button type="button" class="btn-add-product" ' +
                    'data-product-id="' + productData.id + '" ' +
                    'data-product-name="' + productData.name + '" ' +
                    'data-product-sku="' + productData.sku + '" ' +
                    'data-product-price="' + productData.price + '">Add</button>'
                );
        } else {
            // If no cache, just replace with basic button
            $('.product-item[data-product-id="' + productId + '"]')
                .removeClass('product-selected')
                .find('.product-status-badge')
                .replaceWith('<button type="button" class="btn-add-product" data-product-id="' + productId + '">Add</button>');
        }

        // Remove from products table if exists
        $('#products-table-body tr[data-product-id="' + productId + '"]').remove();
    });

    // Update products table
    function updateProductsTable(productId, productName, productSku, productPrice) {
        var $tableBody = $('#products-table-body');
        if ($tableBody.length && !$tableBody.find('tr[data-product-id="' + productId + '"]').length) {
            var $row = $(
                '<tr data-product-id="' + productId + '">' +
                '<td><input type="checkbox" value="' + productId + '" checked disabled></td>' +
                '<td><span class="product-status">Active</span>' + productName + '</td>' +
                '<td>' + productSku + '</td>' +
                '<td>$' + productPrice + '</td>' +
                '</tr>'
            );
            $tableBody.append($row);
        }
    }

    // Always on checkbox - disable date fields
    function toggleDateFields() {
        var $startDate = $('#offer_start_date');
        var $startTime = $('#offer_start_time');
        var $endDate = $('#offer_end_date');
        var $endTime = $('#offer_end_time');
        var isChecked = $('#offer_always_on').prop('checked');

        if (isChecked) {
            $startDate.prop('disabled', true).val('');
            $startTime.prop('disabled', true);
            $endDate.prop('disabled', true).val('');
            $endTime.prop('disabled', true);
        } else {
            $startDate.prop('disabled', false);
            $startTime.prop('disabled', false);
            $endDate.prop('disabled', false);
            $endTime.prop('disabled', false);
        }
    }

    $('#offer_always_on').on('change', toggleDateFields);
    
    // Trigger on page load if checked
    toggleDateFields();

    // Initialize showcase type fields
    $('#offer_showcase_type').trigger('change');
    
    // Handle form submission - upload image first if base64 data exists
    $('#offer-form').on('submit', function(e) {
        var $form = $(this);
        var $imageData = $('#offer_image_data');
        var $imageId = $('#offer_image_id');
        var base64Data = $imageData.val();
        
        // If we have base64 data and no image ID, upload it first via AJAX
        if (base64Data && base64Data.length > 100 && !$imageId.val()) {
            e.preventDefault();
            
            // Show loading state
            var $submitButtons = $form.find('button[type="submit"], input[type="submit"]');
            var originalText = $submitButtons.text() || $submitButtons.val();
            $submitButtons.prop('disabled', true);
            if ($submitButtons.is('button')) {
                $submitButtons.text('Saving...');
            } else {
                $submitButtons.val('Saving...');
            }
            
            // Convert base64 to blob and upload
            var mimeMatch = base64Data.match(/^data:image\/(\w+);base64,/);
            var mimeType = mimeMatch ? mimeMatch[1] : 'png';
            var base64Content = base64Data.replace(/^data:image\/\w+;base64,/, '');
            var binaryString = atob(base64Content);
            var bytes = new Uint8Array(binaryString.length);
            for (var i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            var blobMimeType = mimeType === 'svg+xml' ? 'image/svg+xml' : 'image/' + mimeType;
            var blob = new Blob([bytes], { type: blobMimeType });
            var filename = $('#offer_image_filename').val() || 'offer-image.' + (mimeType === 'svg+xml' ? 'svg' : mimeType);
            
            var formData = new FormData();
            formData.append('action', 'upload_offer_image');
            formData.append('file', blob, filename);
            formData.append('nonce', uploadNonce || nonce);
            
            // Upload image first
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && response.data && response.data.id) {
                        // Set the image ID
                        $imageId.val(response.data.id);
                        // Clear base64 data
                        $imageData.val('');
                        $('#offer_image_filename').val('');
                        // Now submit the form
                        $form.off('submit').submit();
                    } else {
                        alert('Error uploading image: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                        $submitButtons.prop('disabled', false);
                        if ($submitButtons.is('button')) {
                            $submitButtons.text(originalText);
                        } else {
                            $submitButtons.val(originalText);
                        }
                    }
                },
                error: function() {
                    alert('Error uploading image. Please try again.');
                    $submitButtons.prop('disabled', false);
                    if ($submitButtons.is('button')) {
                        $submitButtons.text(originalText);
                    } else {
                        $submitButtons.val(originalText);
                    }
                }
            });
            
            return false;
        }
    });
});

