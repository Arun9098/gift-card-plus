document.addEventListener("DOMContentLoaded", function () {



  //Code for the Eligible Gift Cards START ------------------------------------

  // const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
  // const nonce = '<?php echo esc_js( $nonce ); ?>';
  let treeData = {}; // nested brand->parents->children
  let selectedMap = {}; // sku -> { sku, title, brand, product_id }
  let selectedOrder = []; // array of skus in ranked order

  // Utility: render a tree node (brand/parent/child) with checkbox
  function renderTree(data) {
    //console.log('1');

    // data: object { brand: '', parents: [ { parent_sku, title, children: [{sku,title}] }, ... ], individuals: [ {sku,title} ] }
    const $tree = jQuery('#gc_tree').empty();

    // Insert Select All + Deselect All checkboxes inside the tree
    const $controlsRow = jQuery(`
          <div class="gc-top-controls" style="margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid #ddd;">
              <label style="margin-right:15px;">
                  <input type="checkbox" id="gc_select_all_inside" /> Select All
              </label>
              <label>
                  <input type="checkbox" id="gc_deselect_all_inside" /> Deselect All
              </label>
          </div>
      `);
    $tree.append($controlsRow);

    if (!data || Object.keys(data).length === 0) {
      jQuery('#gc_empty_hint').show();
      return;
    }
    jQuery('#gc_empty_hint').hide();

    for (const brandKey of Object.keys(data)) {
      // const brand = data[brandKey];
      const normalizedBrandKey = brandKey.trim();
      const brand = data[brandKey];

      const $brand = jQuery('<div class="gc-brand" style="margin-bottom:10px;"></div>');
      const brandId = 'brand_' + normalizedBrandKey.replace(/[^a-z0-9]/gi, '_');
      const $brandChk = jQuery('<input type="checkbox" class="gc-brand-chk" data-brand="' + normalizedBrandKey + '" id="' + brandId + '" />');
      const $brandLbl = jQuery('<label for="' + brandId + '" style="font-weight:700;margin-left:6px;">' + normalizedBrandKey + ' (' + ((brand.parents?.length || 0) + (brand.individuals?.length || 0)) + ')</label>');
      $brand.append($brandChk).append($brandLbl);

      // parents
      const $parentsWrap = jQuery('<div style="margin-left:18px;margin-top:6px;"></div>');
      if (brand.parents && brand.parents.length) {
        brand.parents.forEach(p => {
          const parentId = 'parent_' + p.parent_sku.replace(/[^a-z0-9]/gi, '_');
          const $parent = jQuery('<div class="gc-parent" style="margin-bottom:6px;"></div>');
          const $parentChk = jQuery('<input type="checkbox" class="gc-parent-chk" data-brand="' + normalizedBrandKey + '" data-parent="' + p.parent_sku + '" id="' + parentId + '" />');
          const $parentLbl = jQuery('<label for="' + parentId + '" style="margin-left:6px;">' + p.title + ' (' + p.parent_sku + ')</label>');
          $parent.append($parentChk).append($parentLbl);

          // children list
          if (p.children && p.children.length) {
            const $childWrap = jQuery('<div style="margin-left:18px;margin-top:4px;"></div>');
            p.children.forEach(c => {
              const childId = 'child_' + c.sku.replace(/[^a-z0-9]/gi, '_');
              const $child = jQuery('<div>');
              const $childChk = jQuery('<input type="checkbox" class="gc-child-chk" data-brand="' + normalizedBrandKey + '" data-parent="' + p.parent_sku + '" data-sku="' + c.sku + '" data-product_id="' + (c.product_id || '') + '" id="' + childId + '" />');
              const $childLbl = jQuery('<label for="' + childId + '" style="margin-left:6px;">' + c.title + ' (' + c.sku + ')</label>');
              $child.append($childChk).append($childLbl);
              $childWrap.append($child);
            });
            $parent.append($childWrap);
          }
          $parentsWrap.append($parent);
        });
      }

      // individuals (no parent)
      if (brand.individuals && brand.individuals.length) {
        const $indsWrap = jQuery('<div class="individual-list"><div>');
        brand.individuals.forEach(i => {
          const indId = 'ind_' + i.sku.replace(/[^a-z0-9]/gi, '_');
          const $ind = jQuery('<div>');
          const $indChk = jQuery('<input type="checkbox" class="gc-ind-chk" data-brand="' + normalizedBrandKey + '" data-sku="' + i.sku + '" data-product_id="' + (i.product_id || '') + '" id="' + indId + '" />');
          const $indLbl = jQuery('<label for="' + indId + '" style="margin-left:6px;">' + i.title + ' (' + i.sku + ')</label>');
          $ind.append($indChk).append($indLbl);
          $indsWrap.append($ind);
        });
        $parentsWrap.append($indsWrap);
      }

      $brand.append($parentsWrap);
      $tree.append($brand);
    }

    bindTreeEvents();
  }


  function updateTopControls() {
    const $items = jQuery('#gc_tree').find('input[type="checkbox"]').not('#gc_select_all_inside, #gc_deselect_all_inside');
    const total = $items.length;
    const checked = $items.filter(':checked').length;

    jQuery('#gc_select_all_inside').prop('checked', checked === total && total > 0);
    jQuery('#gc_deselect_all_inside').prop('checked', checked === 0);

  }
  // Bind events for tree checkboxes
  function bindTreeEvents() {

    // Inside-tree Select All
    // Inside-tree Select All
    jQuery('#gc_select_all_inside').off('change').on('change', function () {
        const checked = jQuery(this).is(':checked');

        // Always uncheck the deselect-all box
        jQuery('#gc_deselect_all_inside').prop('checked', false);

        const $items = jQuery('#gc_tree')
            .find('input[type="checkbox"]')
            .not('#gc_select_all_inside, #gc_deselect_all_inside');

        // Select All → check all
        if (checked) {
            $items.prop('checked', true).trigger('change');
        } else {
            // Uncheck Select All → uncheck everything
            $items.prop('checked', false).trigger('change');

            selectedMap = {};
            selectedOrder = [];
            renderSelectedList();
        }

        updateTopControls();
    });



    // Inside-tree Deselect All
    // Inside-tree Deselect All
    jQuery('#gc_deselect_all_inside').off('change').on('change', function () {
        const checked = jQuery(this).is(':checked');

        // Always uncheck select-all box
        jQuery('#gc_select_all_inside').prop('checked', false);

        const $items = jQuery('#gc_tree')
            .find('input[type="checkbox"]')
            .not('#gc_select_all_inside, #gc_deselect_all_inside');

        if (checked) {
            // checked → deselect everything
            $items.prop('checked', false).trigger('change');
            selectedMap = {};
            selectedOrder = [];
            renderSelectedList();
        } else {
            // unchecked → do nothing (normal toggle)
        }

        updateTopControls();
    });


    //console.log('2');

    // Brand checkbox toggles all under it
    jQuery('.gc-brand-chk').off('change').on('change', function () {
      const brand = String(jQuery(this).data('brand')).trim();
      const checked = jQuery(this).prop('checked');
      const $container = jQuery(this).closest('.gc-brand');

      $container.find('.gc-child-chk, .gc-ind-chk').each(function () {
          jQuery(this).prop('checked', checked).trigger('change');
      });
      
      $container.find('.gc-parent-chk').prop('checked', checked);

      const brandObj = treeData[brand];
      
      if (!brandObj) {
        console.warn("Brand not found in treeData:", brand);
        return;
      }

      if (checked) {
        if (brandObj.parents && brandObj.parents.length) {
            brandObj.parents.forEach(p => {
                addSelection({
                    sku: p.parent_sku,
                    title: p.title,
                    brand: brand,
                    product_id: p.product_id || ''
                });

                p.children?.forEach(c => addSelection({
                    sku: c.sku,
                    title: c.title,
                    brand: brand,
                    product_id: c.product_id || ''
                }));
            });
        }
        if (brandObj.individuals && brandObj.individuals.length) {
            brandObj.individuals.forEach(i => {
                addSelection({
                    sku: i.sku,
                    title: i.title,
                    brand: brand,
                    product_id: i.product_id || ''
                });
            });
        }
      } else {

          if (brandObj.parents && brandObj.parents.length) {
              brandObj.parents.forEach(p => {
                  removeSelection(p.parent_sku); 
                  p.children?.forEach(c => removeSelection(c.sku));
              });
          }

          if (brandObj.individuals && brandObj.individuals.length) {
              brandObj.individuals.forEach(i => removeSelection(i.sku));
          }
      }

      renderSelectedList();
      updateTopControls();
    });


    // Parent checkbox toggles children
    jQuery('.gc-parent-chk').off('change').on('change', function () {
        const checked = jQuery(this).prop('checked');
        const parentSku = jQuery(this).data('parent');
        const brand = String(jQuery(this).data('brand')).trim();
        
        const brandObj = treeData[brand];
        const parentObj = brandObj?.parents.find(x => x.parent_sku === parentSku);

        if (!parentObj) return;

        if (checked) {
            addSelection({
                sku: parentObj.parent_sku,
                title: parentObj.title,
                brand: brand,
                product_id: parentObj.product_id || ''
            });
        } else {
            console.log("Parent Unchecked:", parentSku);
            removeSelection(parentObj.parent_sku);
        }
        const $children = jQuery(`.gc-child-chk[data-brand="${brand}"][data-parent="${parentSku}"]`);
        $children.prop('checked', checked).trigger('change');


        // Add/remove to selected list
        if (checked) {
            parentObj.children?.forEach(c => {
                addSelection({
                    sku: c.sku,
                    title: c.title,
                    brand: brand,
                    product_id: c.product_id || ''
                });
            });
        } else {
            console.log("Parent Unchecked 11:", parentSku);
            parentObj.children?.forEach(c => removeSelection(c.sku));
        }

        renderSelectedList();
        updateTopControls();
    });


    // Child checkbox toggles one item
    jQuery('.gc-child-chk').off('change').on('change', function () {
        const sku = jQuery(this).data('sku');
        const product_id = jQuery(this).data('product_id') || '';
        const checked = jQuery(this).prop('checked');
        // const brand = jQuery(this).data('brand') || '';
        const rawBrand = jQuery(this).data('brand');
        const brand = jQuery('<textarea/>').html(rawBrand).text(); 


        const title = jQuery(this).next('label').text().replace(/\(.+\)/, '').trim();
   
        if (checked) {
            addSelection({ sku, title, brand, product_id });
        } else {
            removeSelection(sku);
        }
    });



    // Individual items
    jQuery('.gc-ind-chk').off('change').on('change', function () {
        const sku = jQuery(this).data('sku');
        const product_id = jQuery(this).data('product_id') || '';
        const checked = jQuery(this).prop('checked');
        const rawBrand = jQuery(this).data('brand');
        const brand = jQuery('<textarea/>').html(rawBrand).text(); 
        // FIXED TITLE EXTRACTION
        const title = jQuery(this).next('label').text().replace(/\(.+\)/, '').trim();

        if (checked) {
            addSelection({ sku, title, brand, product_id });
        } else {
            removeSelection(sku);
        }
    });
  }


  // Add selection (if not exists)
  function addSelection(item) {
    //console.log('3');

    if (!item || !item.sku) return;
    if (!item.product_id) item.product_id = null;
    if (selectedMap[item.sku]) return;
    selectedMap[item.sku] = item;
    selectedOrder.push(item.sku);
    console.log("selectedMap after selecting:", JSON.parse(JSON.stringify(selectedMap)));

    renderSelectedList();
  }

  // Remove selection
  function removeSelection(sku) {
    //console.log('4');
    // console.log("Parent Unchecked:");

    if (!selectedMap[sku]) return;
    delete selectedMap[sku];
    selectedOrder = selectedOrder.filter(s => s !== sku);
    renderSelectedList();
    // uncheck any checkbox in tree matching sku
    jQuery('.gc-child-chk[data-sku="' + sku + '"], .gc-ind-chk[data-sku="' + sku + '"]').prop('checked', false);
  }

  // Render selected list (ranked)
  function renderSelectedList() {

    //console.log('5');

    const $ul = jQuery('#gc_selected_list').empty();

    if (selectedOrder.length === 0) {
      //console.log('test 123');
      jQuery('#gc_selected_section').hide();
    } else {
      //console.log('test 123');
      jQuery('#gc_selected_section').show();
    }

    selectedOrder.forEach(sku => {
      const it = selectedMap[sku];
      if (!it) return;
      const $li = jQuery('<li class="gc-selected-item" data-sku="' + sku + '" style="padding:6px;border:1px solid #ddd;margin-bottom:6px;background:#fff;display:flex;justify-content:space-between;align-items:center;"></li>');
      const left = jQuery('<div><strong>' + (it.title || sku) + '</strong><div style="font-size:12px;color:#666;">' + (it.sku || sku) + (it.brand ? ' — ' + it.brand : '') + '</div></div>');
      const right = jQuery('<div style="display:flex;gap:6px;align-items:center;"></div>');
      const handle = jQuery('<span class="gc-drag-handle" style="cursor:grab;">☰</span>');
      const removeBtn = jQuery('<button type="button" class="button" style="font-size:12px;padding:4px 8px;">Remove</button>').on('click', function () { removeSelection(sku); });
      right.append(handle).append(removeBtn);
      $li.append(left).append(right);
      $ul.append($li);
    });

    // initialize sortable
    if (typeof Sortable !== 'undefined') {
      if (window._gc_sortable) {
        window._gc_sortable.destroy();
      }
      window._gc_sortable = Sortable.create($ul.get(0), {
        handle: '.gc-drag-handle',
        animation: 150,
        onEnd: function (evt) {
          // update selectedOrder according to DOM
          const newOrder = [];
          $ul.children().each(function () { newOrder.push(jQuery(this).data('sku')); });
          selectedOrder = newOrder;
          persistToHidden();
        }
      });
    }

    persistToHidden();
  }

  // Persist selections to hidden input as JSON (sku + rank + title + brand + product_id)
  function persistToHidden() {
    //console.log('6');

    const arr = selectedOrder.map((sku, idx) => {
      const it = selectedMap[sku];
      return {
        sku: sku,
        rank: idx + 1,
        title: it.title || '',
        brand: it.brand || '',
        product_id: it.product_id || ''
      };
    });
    jQuery('#eligible_gift_cards_json').val(JSON.stringify(arr));
  }

  // Search / load tree via AJAX
  let searchTimer = null;
  jQuery('#gc_search').on('input', function () {
    jQuery("#gc_tree").slideDown();
    jQuery('#gc_nested_container').show();

    //console.log('7');

    const q = jQuery(this).val().trim();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      loadTree(q);
    }, 300);
  });

  // Select All
  jQuery('#gc_select_all').on('click', function () {

    // fetch all brands without query
    loadTree('', function () {
      // check all checkboxes then trigger change to add all
      jQuery('#gc_tree').find('input[type="checkbox"]').prop('checked', true).trigger('change');
    });
  });

  // Deselect All
  jQuery('#gc_deselect_all').on('click', function () {

    jQuery('#gc_tree').find('input[type="checkbox"]').prop('checked', false).trigger('change');
    // remove all selections
    selectedMap = {};
    selectedOrder = [];
    renderSelectedList();
  });

  // CSV upload handler (client-side parse)
  jQuery('#gc_csv_upload').on('change', function (e) {

    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (evt) {
      const text = evt.target.result;
      const lines = text.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
      const items = [];
      for (let i = 0; i < lines.length; i++) {
        const parts = lines[i].split(',');
        if (i === 0) continue;

        let sku = parts[0] ? parts[0].trim().replace(/\\\"/g, '') : '';
        let rank = parts[1] ? parseInt(parts[1].trim()) : null;
        if (!sku) continue;
        items.push({ sku: sku, rank: rank });
      }
      if (!items.length) {
        alert('No SKUs found in CSV');
        return;
      }
      // resolve these SKUs via AJAX to get titles/brands/product_ids
      jQuery.ajax({
        url: ajax_object.ajax_url,
        method: 'POST',
        data: {
          action: 'gc_resolve_skus',
          // nonce: nonce,
          items: JSON.stringify(items)
        },
        success: function (resp) {

          if (resp.success) {
            if (resp.data.invalid.length) {
                jQuery('#gc_csv_error')
                    .html(resp.data.invalid.join('<br>'))
                    .show();
            } else {
                jQuery('#gc_csv_error').hide();
            }
            resp.data.valid.forEach(it => addSelection(it));

            renderSelectedList();
          } else {
              jQuery('#gc_csv_error')
                  .html(resp.data.message || 'Unknown error occurred')
                  .show();
          }
        },
        error: function () { alert('Error resolving CSV SKUs'); }
      });
    };
    reader.readAsText(file);
    // clear input
    jQuery(this).val('');
  });

  // Load tree from server (AJAX)
  function loadTree(q = '', cb) {

    jQuery('#gc_loading').show();
    jQuery.ajax({
      url: ajax_object.ajax_url,
      method: 'GET',
      data: {
        action: 'gc_get_giftcards',
        // nonce: nonce,
        q: q
      },
      success: function (resp) {

        jQuery('#gc_loading').hide();
        if (resp.success) {
          let cleaned = {};
          for (let k in resp.data) {
              cleaned[String(k).trim()] = resp.data[k];
          }
          treeData = cleaned;
          const normalized = {};
          Object.keys(treeData).forEach(key => {
              const decodedKey = jQuery('<textarea/>').html(key).text();  // decode &amp;
              normalized[decodedKey] = treeData[key];
          });
          treeData = normalized;

          renderTree(treeData);
          if (cb) cb();
          // Re-check boxes for any already selected
          for (const sku in selectedMap) {
            // Recheck children & individuals
            jQuery('#gc_tree').find('[data-sku="' + sku + '"]').prop('checked', true);

            // Recheck parent checkbox
            jQuery('#gc_tree').find('.gc-parent-chk[data-parent="' + sku + '"]').prop('checked', true);
          }

          syncTreeCheckboxes();
        } else {
          jQuery('#gc_tree').html('<div style="color:#b30000;">Error loading data</div>');
        }
      },
      error: function () {
        jQuery('#gc_loading').hide();
        jQuery('#gc_tree').html('<div style="color:#b30000;">Error loading data</div>');
      }
    });
  }
  
  function syncTreeCheckboxes() {

      // Sync child / individual selections from selectedMap
      jQuery('#gc_tree .gc-child-chk, #gc_tree .gc-ind-chk').each(function(){
          const sku = jQuery(this).data('sku');
          jQuery(this).prop('checked', !!selectedMap[sku]);
      });

      // Sync parent checkboxes using selectedMap (NOT visible children)
      jQuery('#gc_tree .gc-parent').each(function(){
          const $parent = jQuery(this);
          const parentSku = $parent.find('.gc-parent-chk').data('parent');

          // Parent is checked if selectedMap contains parentSku
          const isChecked = !!selectedMap[parentSku];
          $parent.find('.gc-parent-chk').prop('checked', isChecked);
      });

      // Sync brand checkboxes
      jQuery('#gc_tree .gc-brand').each(function(){
          const $brand = jQuery(this);
          const $brandChk = $brand.find('.gc-brand-chk');
          let allChecked = true;

          $brand.find('.gc-parent-chk, .gc-child-chk, .gc-ind-chk').each(function(){
              if (!jQuery(this).prop('checked')) {
                  allChecked = false;
                  return false;
              }
          });

          $brandChk.prop('checked', allChecked);
      });

      updateTopControls();
  }


  // initial: if hidden input has data, populate selectedMap and optionally load minimal info
  (function initFromSaved() {
    const raw = jQuery('#eligible_gift_cards_json').val();
    if (!raw) return;
    try {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr)) {
        // Add to selectedMap but keep product info if exists
        arr.forEach(it => {
          if (it && it.sku) {
            selectedMap[it.sku] = {
              sku: it.sku,
              title: it.title || it.sku,
              brand: it.brand || '',
              product_id: it.product_id || ''
            };
            selectedOrder.push(it.sku);
          }
        });
        renderSelectedList();
      }
    } catch (e) { }
  })();


  const serverSupplier = ajax_object.serverSupplier || '';

  // Persist on form submit & server-side validation will check if required
  jQuery('#post').on('submit', function (e) {

    // If Supplier equals 'Gift Cards Plus' then ensure at least 1 selected
    // const supplierVal = jQuery('[name="supplier"], #supplier, input[name=\"supplier\"]').val() || '<?php echo esc_js( $product_data['supplier'] ?? '' ); ?>';
    const supplierVal = jQuery('[name="supplier"], #supplier, input[name=\"supplier\"]').val() || serverSupplier;
    const isRequired = supplierVal && supplierVal.toString().toLowerCase().indexOf('gift cards plus') !== -1;
    if (isRequired) {
      if (selectedOrder.length === 0) {
        e.preventDefault();
        jQuery('#gc_error').show();
        jQuery('html, body').animate({ scrollTop: jQuery('#gc_eligible_field_wrap').offset().top - 60 }, 200);
        return false;
      }
    }
    jQuery('#gc_error').hide();
    persistToHidden();
  });

  jQuery(document).on('click', function (e) {
    // If clicked OUTSIDE the nested container AND outside the search input
    if (
      !jQuery(e.target).closest('#gc_nested_container').length &&
      !jQuery(e.target).closest('#gc_search').length
    ) {
      jQuery('#gc_tree').slideUp();
      jQuery('#gc_nested_container').hide();
    }
  });

  
  //Code for the Eligible Gift Cards END ------------------------------------

  const costPrice = document.getElementById('cost_price');
  const deliveryCost = document.getElementById('delivery_cost');
  const fulfillmentCost = document.getElementById('j_a_c_fulfillment_cost');
  const gstRate = document.getElementById('_gst');
  const sell_price_fixed = document.getElementById('_sell_price_fixed');
  const totalBuyPriceField = document.getElementById('total_buy_price');
  const totalSellPriceField = document.getElementById('total_sell_price');
  const supplier_fullfillment_price = document.getElementById('_supplier_fullfillment_price');
  const totalBuyPriceIncludingGSTField = document.getElementById('total_buy_price_including_gst');
  const sellPriceLowestDenominationInput = document.getElementById('sell_price_lowest_denomination');
  const variableRangeTo = document.getElementById('variable_range_to');
  const variableRangeFrom = document.getElementById('variable_range_from');
  const redeemAtIntervals = document.getElementById('_reedem_at_intervals');
  const presetCheckbox = document.getElementById('presetDeliveryClass');


  if (presetCheckbox) {
    setTimeout(() => {
      presetCheckbox.dispatchEvent(new Event('change')); // or 'input' if you want
    }, 1000);
  }



  function calculatePrices() {
    const cost = parseFloat(costPrice?.value) || 0;
    const fulfillment = parseFloat(fulfillmentCost?.value) || 0;
    const _sell_price_fixed = parseFloat(sell_price_fixed?.value) || 0;
    const _supplier_fullfillment_price = parseFloat(supplier_fullfillment_price?.value) || 0;
    const delivery = parseFloat(deliveryCost?.value) || 0;
    const gst = parseFloat(gstRate?.value) || 0;
    const sellPriceLowestDenomination = parseFloat(sellPriceLowestDenominationInput?.value) || 0;

    let totalSellPrice = 0;
    if (_sell_price_fixed > 0) {
      totalSellPrice = _sell_price_fixed + fulfillment + delivery;
    } else {
      totalSellPrice = sellPriceLowestDenomination + fulfillment + delivery;
    }

    const totalBuyPrice = cost + _supplier_fullfillment_price;

    const totalBuyPriceIncludingGst = cost + _supplier_fullfillment_price + delivery + gst;

    //const totalSellPrice = totalBuyPrice * 1.2;// Fix: Removed markup
    //const totalSellPrice = _sell_price_fixed + fulfillment + delivery;// Fix: Removed markup
    const totalSellPriceIncludingGst = totalSellPrice + (totalSellPrice * (gst / 100));
    // const totalBuyPriceIncludingGst = totalBuyPrice + gst;

    if (totalBuyPriceField) totalBuyPriceField.value = totalBuyPrice.toFixed(2);
    if (totalSellPriceField) totalSellPriceField.value = totalSellPrice.toFixed(2);
    if (totalBuyPriceIncludingGSTField) totalBuyPriceIncludingGSTField.value = totalBuyPriceIncludingGst.toFixed(2);
    jQuery('#_sell_price_fixed, #cost_price, #sell_price_lowest_denomination').trigger('input');
  }
  [costPrice, sell_price_fixed, supplier_fullfillment_price, fulfillmentCost, sellPriceLowestDenominationInput, deliveryCost, gstRate, variableRangeTo, variableRangeFrom, redeemAtIntervals].forEach(field => {
    if (field) field.addEventListener('input', calculatePrices);
  });

  var presetDropdown = document.getElementById("presetClasses");


  if (presetDropdown) {
    setTimeout(() => {
      presetDropdown.dispatchEvent(new Event('change')); // or 'input' if you want
    }, 1000);
  }

  if (presetDropdown) {
    presetDropdown.addEventListener("change", function () {
      setTimeout(() => {
        calculatePrices();
      }, 200);
    });
  }

  setTimeout(() => {
    calculatePrices();
  }, 500);


  //Preset delivery class checkbox JS
  const presetDeliveryClassCheckbox = document.getElementById('presetDeliveryClass');
  const presetClassesDropdown = document.getElementById('preset-delivery-fields');

  if (presetDeliveryClassCheckbox) {
    presetDeliveryClassCheckbox.addEventListener('change', function () {
      setTimeout(() => {
        calculatePrices();
      }, 200);

      if (this.checked) {
        presetClassesDropdown.style.display = 'block';
      } else {
        presetClassesDropdown.style.display = 'none';
        jQuery('#presetClasses').val('');
        jQuery('#delivery_cost').val('').attr('value', '');
      }
    });
  }

  if (presetDropdown) {
    presetDropdown.addEventListener('change', function () {
      if (presetDeliveryClassCheckbox?.checked) {
        const selectedValue = this.value;
      }
    });
  }
  // ===============================================


  //Discounted Price
  let discountedPriceCheckbox = document.getElementById("discounted_price_checkbox");
  let discountedPrice = document.getElementById("discounted_price_label");
  let discountedFromLabel = document.getElementById("discount_from_label");
  let discountedToLabel = document.getElementById("discount_to_label");
  let discountMargin = document.getElementById("_discount_margin_label");
  let margin = document.getElementById("_margin_label");


  // Function to show/hide fields
  function toggleDiscountFields(isChecked) {
    discountedPrice.style.display = isChecked ? "block" : "none";
    discountedFromLabel.style.display = isChecked ? "block" : "none";
    discountedToLabel.style.display = isChecked ? "block" : "none";
    discountMargin.style.display = isChecked ? "block" : "none";
    margin.style.display = isChecked ? "block" : "none";

    discountedPrice.parentNode.classList.toggle("show", isChecked);
    discountedPrice.parentNode.classList.toggle("hide", !isChecked);
    discountMargin.parentNode.classList.toggle("show", isChecked);
    discountMargin.parentNode.classList.toggle("hide", !isChecked);
    discountedFromLabel.parentNode.classList.toggle("show", isChecked);
    discountedFromLabel.parentNode.classList.toggle("hide", !isChecked);

    if (isChecked) {
      updateDiscountMarginAndValidate();
    } else {
      // document.getElementById("discounted_price").value = "";
      // document.getElementById("_discount_valid_from").value = "";
      // document.getElementById("_discount_valid_to").value = "";
      // document.getElementById("_discount_margin_input").value = "";
      // document.querySelector('input[name="_margin"]').value = "";
    }
  }
  // Update visibility on checkbox change
  discountedPriceCheckbox.addEventListener("change", function () {
    toggleDiscountFields(this.checked);
  });

  toggleDiscountFields(discountedPriceCheckbox.checked);

  function updateDiscountMarginAndValidate() {
    const totalSellP = parseFloat(document.getElementById("_sell_price_fixed").value) || 0;
    const costPrice = parseFloat(document.getElementById("cost_price").value) || 0;
    const supplierPrice = parseFloat(document.getElementById("_supplier_fullfillment_price").value) || 0;

    const totalBuyP = costPrice + supplierPrice;
    // const afterTotal = totalSellP - totalBuyP;

    const isDiscountChecked = document.getElementById("discounted_price_checkbox").checked;
    if (isDiscountChecked) {
      const discountPriceInput = parseFloat(document.getElementById("discounted_price").value) || 0;
      const discountMarginInput = document.getElementById("_discount_margin_input");

      if (discountMarginInput) {
        const TotalDiscountMargin = discountPriceInput - totalBuyP;
        const formattedMargin = TotalDiscountMargin.toFixed(2);
        discountMarginInput.value = formattedMargin;
        discountMarginInput.setAttribute("value", formattedMargin);
      }
    }
    // const marginPer = document.querySelector('input[name="margin_currency"]').value;
    // document.querySelector('input[name="_margin"]').value = marginPer;
  }
  const checkbox = document.querySelector('input[name="discounted_price_checkbox"]');

  function handleCheckboxChange() {
    if (checkbox.checked) {
      updateDiscountMarginAndValidate();
      setTimeout(() => {
        const marginPer = document.querySelector('input[name="margin_currency"]').value;
        document.querySelector('input[name="_margin"]').value = marginPer;
      }, 200);
    }
  }

  checkbox.addEventListener('change', handleCheckboxChange);

  handleCheckboxChange();

  discountedPriceCheckbox.addEventListener("change", function () {
    discountedPrice.style.display = this.checked ? "block" : "none";
    discountedFromLabel.style.display = this.checked ? "block" : "none";
    discountedToLabel.style.display = this.checked ? "block" : "none";
    discountMargin.style.display = this.checked ? "block" : "none";
    margin.style.display = this.checked ? "block" : "none";

    discountedPrice.parentNode.classList.toggle("show", this.checked);
    discountedPrice.parentNode.classList.toggle("hide", !this.checked);
    discountMargin.parentNode.classList.toggle("show", this.checked);
    discountMargin.parentNode.classList.toggle("hide", !this.checked);
    discountedFromLabel.parentNode.classList.toggle("show", this.checked);
    discountedFromLabel.parentNode.classList.toggle("hide", !this.checked);

    let show = this.checked;
    if (show) updateDiscountMarginAndValidate();
  });

  ["cost_price", "_supplier_fullfillment_price", "_sell_price_fixed", "discounted_price"].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("input", updateDiscountMarginAndValidate);
  });

  // ===============================================
  // Stock level
  // let addStockCheckbox = document.getElementById("add_stock_checkbox");
  // let addStockLevel = document.getElementById("_add_stock_level_label");
  // if (addStockCheckbox && addStockCheckbox.checked) {
  //   addStockLevel.style.display = addStockCheckbox.checked ? "block" : "none";
  // }
  // addStockCheckbox.addEventListener("change", function () {
  //     addStockLevel.style.display = this.checked ? "block" : "none";
  // });

  let addStockCheckbox = document.getElementById("add_stock_checkbox");
  let addStockLevel = document.getElementById("_add_stock_level_label");

  function toggleAddStockField() {
    if (!addStockCheckbox || !addStockLevel) {
      return;
    }

    if (addStockCheckbox.checked) {
      addStockLevel.style.display = "block";
    } else {
      addStockLevel.style.display = "none";
      jQuery('#_add_stock_level').val('').attr('value', '');
    }
  }

  // ✅ Run on page load
  toggleAddStockField();

  addStockCheckbox.addEventListener("change", toggleAddStockField);


  //For transaction limit checkbox code
  let addTransactionLimitCheckbox = document.getElementById("add_transaction_limit_checkbox");
  let qtyPerTransaction = document.getElementById("_quantity_per_transaction_label");
  let totalValuePerTransaction = document.getElementById("_total_value_per_transaction_label");

  function toggleTransactionLimitFields() {
    if (!addTransactionLimitCheckbox || !qtyPerTransaction || !totalValuePerTransaction) {
      return;
    }

    if (addTransactionLimitCheckbox.checked) {
      qtyPerTransaction.style.display = "block";
      totalValuePerTransaction.style.display = "block";

      qtyPerTransaction.parentNode.classList.add("show");
      qtyPerTransaction.parentNode.classList.remove("hide");

    } else {
      qtyPerTransaction.style.display = "none";
      totalValuePerTransaction.style.display = "none";

      qtyPerTransaction.parentNode.classList.remove("show");
      qtyPerTransaction.parentNode.classList.add("hide");

      // Clear values when unchecked
      jQuery('#_quantity_per_transaction').val('').attr('value', '');
      jQuery('#_total_value_per_transaction').val('').attr('value', '');

    }
  }

  toggleTransactionLimitFields();

  addTransactionLimitCheckbox.addEventListener("change", toggleTransactionLimitFields);

  // const totalValueInput = document.getElementById('_total_value_per_transaction');
  // totalValueInput.addEventListener('input', function (e) {
  //   let input = e.target;
  //   let value = input.value.replace(/\$/g, ''); // Remove any existing '$' signs
  //   if (!isNaN(value) && value !== '') {
  //     input.value = '$' + value; // Prepend '$' sign
  //   } else {
  //     input.value = ''; // Clear the input if the value is not a number
  //   }
  // });

  let alwaysOn = document.getElementById("always_on");
  let onSiteFrom = document.getElementById("_onsite_from_label");
  let onSiteTo = document.getElementById("_onsite_to_label");

  if (!alwaysOn || !onSiteFrom || !onSiteTo) return;

  function toggleAlwaysOn() {
    if (alwaysOn.checked) {
      // Hide fields when "Always On" is checked
      onSiteFrom.style.display = "none";
      onSiteTo.style.display = "none";
      onSiteFrom.parentNode.classList.add("hide");
      onSiteFrom.parentNode.classList.remove("show");
      // jQuery('#_onsite_from').val('').attr('value', '');
      // jQuery('#_onsite_to').val('').attr('value', '');
    } else {
      // Show fields when unchecked
      onSiteFrom.style.display = "block";
      onSiteTo.style.display = "block";
      onSiteFrom.parentNode.classList.add("show");
      onSiteFrom.parentNode.classList.remove("hide");
    }
  }

  // ✅ Bind event
  alwaysOn.addEventListener("change", toggleAlwaysOn);

  // ✅ Run once on page load
  toggleAlwaysOn();

  // //Always On
  // let alwaysOn = document.getElementById("always_on");
  // let onSiteFrom = document.getElementById("_onsite_from_label");
  // let onSiteTo = document.getElementById("_onsite_to_label");
  // // Ensure fields are hidden initially
  // if (alwaysOn.checked) {
  //   onSiteFrom.style.display = "none";
  //   onSiteTo.style.display = "none";
  //   onSiteFrom.parentNode.classList.add("hide");
  //   onSiteFrom.parentNode.classList.remove("show");
  // }
  // alwaysOn.addEventListener("change", function () {
  //   onSiteFrom.style.display = this.checked ? "none" : "block";
  //   onSiteTo.style.display = this.checked ? "none" : "block";
  //   onSiteFrom.parentNode.classList.remove("hide");
  //   onSiteFrom.parentNode.classList.add("show");

  // });

  //End Here

  let steps = document.querySelectorAll(".form-step");
  let stepIndicators = document.querySelectorAll(".step");
  let nextBtns = document.querySelectorAll(".next-step");
  let prevBtns = document.querySelectorAll(".prev-step");

  let currentStep = 0;
  let maxStepVisited = 0;

  steps[currentStep].classList.add("active");
  stepIndicators[currentStep].classList.add("active");

  // Handle next button click
  nextBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      if (validateStep(currentStep, true)) {
        navigateToStep(currentStep + 1);
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });
  });

  // Handle previous button click
  prevBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      if (validateStep(currentStep, false)) {
        navigateToStep(currentStep - 1);
      }
    });
  });

  // Handle step number click
  stepIndicators.forEach((indicator, index) => {
    indicator.addEventListener("click", function () {
      if (index <= maxStepVisited && validateStep(currentStep, index > currentStep)) {
        navigateToStep(index);
      }
    });
  });

  // Navigation logic
  function navigateToStep(stepIndex) {
    steps[currentStep].style.display = "none";
    steps[currentStep].classList.remove("active");

    currentStep = stepIndex;
    maxStepVisited = Math.max(maxStepVisited, currentStep);

    steps[currentStep].style.display = "block";
    steps[currentStep].classList.add("active");

    updateStepIndicators();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  // Update step UI
  function updateStepIndicators() {
    stepIndicators.forEach((step, index) => {
      step.classList.toggle("active", index === currentStep);
      step.classList.toggle("disabled", index > maxStepVisited);
      step.style.cursor = index > maxStepVisited ? "default" : "pointer";
    });
  }

  // Get label for error
  function getFieldLabel(field) {
    const label = field.closest('.control-wrapper')?.querySelector('label');
    return label ? label.innerText.replace('*', '').trim() : 'This';
  }

  // Validate step logic
  function validateStep(stepIndex, goingForward = true) {
    let step = steps[stepIndex];
    let requiredFields = step.querySelectorAll("input[required], select[required], textarea[required]");
    let isValid = true;
    let firstErrorField = null;
    let isSkuValid = true;

    // Clear old errors
    step.querySelectorAll(".error-message").forEach(msg => msg.remove());
    requiredFields.forEach(field => field.classList.remove("error"));
    const skuType = step.querySelector('input[name="sku_type"]:checked')?.value || '';

    requiredFields.forEach(field => {
      if (field.type === "radio") {
        let name = field.name;
        let group = step.querySelectorAll(`input[name="${name}"]`);
        let isChecked = [...group].some(radio => radio.checked);

        if (!isChecked && (goingForward || field.value.trim() !== "")) {
          isValid = false;

          if (!step.querySelector(`.error-message[data-for="${name}"]`)) {
            let errorMessage = document.createElement("span");
            errorMessage.className = "error-message";
            errorMessage.dataset.for = name;
            errorMessage.style.color = "red";
            errorMessage.style.fontSize = "15px";
            errorMessage.style.display = "block";
            errorMessage.innerText = `This field is required`;
            let lastRadio = group[group.length - 1];
            let parent = lastRadio.parentNode.parentNode;
            parent.appendChild(errorMessage);
            parent.scrollIntoView({ behavior: "smooth", block: "center" });
            if (!firstErrorField) {
              firstErrorField = parent;
            }
          }

          if (!firstErrorField) {
            const container = group[0].closest(".control-wrapper") || group[0];
            firstErrorField = container;
          }
        }

      } else {
        if (field.id === "parent_sku") {

          if (skuType === "Child" && field.value.trim() === "" && goingForward) {
            isValid = false;
            field.classList.add("error");

            if (!field.nextElementSibling || !field.nextElementSibling.classList.contains("error-message")) {
              let errorMessage = document.createElement("span");
              errorMessage.className = "error-message";
              errorMessage.style.color = "red";
              errorMessage.style.fontSize = "15px";
              errorMessage.style.display = "block";
              errorMessage.innerText = "Please add a parent SKU when selecting Child as your SKU Type.";
              field.parentNode.insertBefore(errorMessage, field.nextSibling);
            }

            if (!firstErrorField) firstErrorField = field;
          }
        } else {
          // Regular required field validation
          if (field.value.trim() === "" && goingForward) {
            isValid = false;
            field.classList.add("error");

            if (!field.nextElementSibling || !field.nextElementSibling.classList.contains("error-message")) {
              let errorMessage = document.createElement("span");
              errorMessage.className = "error-message";
              errorMessage.style.color = "red";
              errorMessage.style.fontSize = "15px";
              errorMessage.style.display = "block";
              errorMessage.innerText = `${getFieldLabel(field)} field is required`;
              field.parentNode.insertBefore(errorMessage, field.nextSibling);
            }

            if (!firstErrorField) firstErrorField = field;
          }
        }
      }
    });

    // Custom SKU validation
    const skuInput = document.getElementById("sku");
    const skuError = document.getElementById("sku_validation_error");
    const supplierSkuInput = document.getElementById("supplier_sku");
    const supplierSkuError = document.getElementById("supplier_sku_error");
    const letterOnlyRegex = /^[A-Za-z0-9_.-]*$/;

    if (skuInput && !letterOnlyRegex.test(skuInput.value)) {
      if (skuError) skuError.style.display = "block";
      isValid = false;
      if (!firstErrorField) firstErrorField = skuInput;
    } else if (skuInput) {
      if (skuError) skuError.style.display = "none";
    }

    if (supplierSkuInput && !letterOnlyRegex.test(supplierSkuInput.value)) {
      if (supplierSkuError) supplierSkuError.style.display = "block";
      isValid = false;
      if (!firstErrorField) firstErrorField = supplierSkuInput;
    } else if (supplierSkuInput) {
      if (supplierSkuError) supplierSkuError.style.display = "none";
    }

    // Brand logo / gift card image validations
    if (stepIndex === 0 && !validateBrandLogo()) {
      isValid = false;
      if (!firstErrorField) firstErrorField = document.getElementById('brand_logo');
    }
    if (stepIndex === 0 && !validateGiftCardImage()) {
      isValid = false;
      if (!firstErrorField) firstErrorField = document.getElementById('preview-container');
    }

    if (stepIndex === 1) {
      const redeemValidation = window.validateRedeemInterval();
      if (!redeemValidation.isValid) {
        if (redeemValidation.firstErrorField) {
          redeemValidation.firstErrorField.focus();
        }
        return false;
      }
    }

    // 🔹 Discounted & Onsite Date Validation
    jQuery(".discount-error, .onsite-error").remove();

    // --- Discount date validation ---
    let fromDate = jQuery("#_discount_valid_from").val();
    let toDate = jQuery("#_discount_valid_to").val();
    const discountedCheck = document.getElementById('discounted_price_checkbox');
    if (discountedCheck.checked) {
      if (!fromDate || !toDate) {
        if (!fromDate) {
          jQuery("#_discount_valid_from").after(
            '<div class="discount-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Please select "Discount Valid From".</div>'
          );
          jQuery('html, body').animate({ scrollTop: jQuery("#_discount_valid_from").offset().top - 100 }, 500);
        }

        if (!toDate) {
          jQuery("#_discount_valid_to").after(
            '<div class="discount-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Please select "Discount Valid To".</div>'
          );
          jQuery('html, body').animate({ scrollTop: jQuery("#_discount_valid_to").offset().top - 100 }, 500);
        }
        return; // Stop further validation if any field is empty
      } else {
        // --- Step 2: Compare dates ---
        let from = new Date(fromDate);
        let to = new Date(toDate);

        if (isNaN(from.getTime()) || isNaN(to.getTime())) {
          jQuery("#_discount_valid_to").after(
            '<div class="discount-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Invalid discount date(s).</div>'
          );
          jQuery('html, body').animate({ scrollTop: jQuery("#_discount_valid_to").offset().top - 100 }, 500);
          return;
        }

        if (from.getTime() > to.getTime()) {
          jQuery("#_discount_valid_to").after(
            '<div class="discount-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ "Discount Valid To" must be greater than or equal to "Discount Valid From".</div>'
          );
          jQuery('html, body').animate({ scrollTop: jQuery("#_discount_valid_to").offset().top - 100 }, 500);
          return;
        }
      }

    }

    // --- Onsite date validation (same rules) ---
    let onsiteFromDate = jQuery("#_onsite_from").val();
    let onsiteToDate = jQuery("#_onsite_to").val();
    const alwaysOn = document.getElementById('always_on');
    if (!alwaysOn.checked) {
      // Only validate if at least one of the fields is filled
      if (!onsiteFromDate || !onsiteToDate) {

        // Step 1: Check for empty fields
        if (!onsiteFromDate) {
          jQuery("#_onsite_from").after(
            '<div class="onsite-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Please select "Onsite From".</div>'
          );
          jQuery('html, body').animate({ scrollTop: jQuery("#_onsite_from").offset().top - 100 }, 500);
        }

        if (!onsiteToDate) {
          jQuery("#_onsite_to").after(
            '<div class="onsite-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Please select "Onsite To".</div>'
          );
          jQuery('html, body').animate({ scrollTop: jQuery("#_onsite_to").offset().top - 100 }, 500);
        }
        return;

      } else {
        let onsiteFrom = new Date(onsiteFromDate);
        let onsiteTo = new Date(onsiteToDate);
        if (isNaN(onsiteFrom.getTime()) || isNaN(onsiteTo.getTime())) {
          jQuery("#_onsite_to").after(
            '<div class="onsite-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Invalid onsite date(s).</div>'
          );
          jQuery('html, body').animate({ scrollTop: jQuery("#_onsite_to").offset().top - 100 }, 500);
          return;
        }

        if (onsiteFrom.getTime() > onsiteTo.getTime()) {
          jQuery("#_onsite_to").after(
            '<div class="onsite-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ "Onsite To" must be greater than or equal to "Onsite From".</div>'
          );
          jQuery('html, body').animate({ scrollTop: jQuery("#_onsite_to").offset().top - 100 }, 500);
          return;
        }
      }
    }


    // Custom min/max validation check (block even going backward if invalid values present)
    const allErrorDivs = step.querySelectorAll(".error-message, .field-error, [id$='_error']");
    allErrorDivs.forEach(err => {
      const isVisible = err.style.display !== "none" && err.textContent.trim() !== "";
      if (isVisible) {
        isValid = false;
        if (!firstErrorField) {
          const fieldId = err.id?.replace('_error', '');
          const field = document.getElementById(fieldId);
          if (field) firstErrorField = field;
        }
      }
    });

    if (!isSkuValid && !jQuery('').hasClass('edit_mode')) {
      const errorDiv = document.getElementById('sku_error');
      if (errorDiv) {
        errorDiv.textContent = 'Please enter a unique SKU.';
        errorDiv.style.display = 'block';
      }
      if (skuInput) skuInput.classList.add('error');
      if (skuInput) skuInput.focus();
      return false;
    }

    if (firstErrorField) {
      firstErrorField.scrollIntoView({ behavior: "smooth", block: "center" });
      firstErrorField.focus();
    }

    return isValid;
  }




  // Get label text for error message
  function getFieldLabel(field) {
    let label = field.closest("label") || field.previousElementSibling;
    return label ? label.textContent.trim().replace(":", "") : field.name;
  }

  // let salePriceInput = document.getElementById("sale_price"); // Make sure this ID matches your HTML
  // let regularPriceInput = document.getElementById("regular_price"); // Ensure this is also declared

  // function validateSalePrice() {
  //   let regularPrice = parseFloat(regularPriceInput.value) || 0;
  //   let salePrice = parseFloat(salePriceInput.value) || 0;


  //   let errorMessage = document.getElementById("sale_price_error");


  //   if (salePrice > regularPrice) {
  //     if (!errorMessage) {
  //       errorMessage = document.createElement("span");
  //       errorMessage.id = "sale_price_error";
  //       errorMessage.className = "error-message";
  //       errorMessage.style.color = "red";
  //       errorMessage.style.fontSize = "12px";
  //       errorMessage.style.display = "block";
  //       errorMessage.innerText = "Sale Price cannot be greater than Regular Price.";


  //       salePriceInput.parentNode.insertBefore(errorMessage, salePriceInput.nextSibling);
  //     }
  //     return false;
  //   } else {
  //     if (errorMessage) {
  //       errorMessage.remove();
  //     }
  //   }
  //   return true;
  // }

  // salePriceInput.addEventListener("keyup", validateSalePrice);


  function getFieldLabel(field) {
    let label = document.querySelector(`label[for="${field.id}"]`);
    if (!label) {
      let previousLabel = field.previousElementSibling;
      if (previousLabel && previousLabel.tagName.toLowerCase() === "label") {
        label = previousLabel;
      }
    }
    return label ? label.innerText.replace("*", "").trim() : field.name;
  }

});
// function validateRedeemInterval() {
//   let isValid = true;
//   let firstErrorField = null;

//   const denominationTypeDropdown = document.getElementById('denomination_type-dropdown');
//   const selectedDenominationType = denominationTypeDropdown ? denominationTypeDropdown.value : '';


//   const variableRangeFrom = document.getElementById('variable_range_from');
//   const variableRangeTo = document.getElementById('variable_range_to');
//   const rangeErrorEl = document.getElementById('_range_validation_error'); // Make sure you have an element with this ID for error display

//   const fromValue = parseFloat(variableRangeFrom.value);
//   const toValue = parseFloat(variableRangeTo.value);

//   if (!isNaN(fromValue) && !isNaN(toValue) && fromValue > toValue) {
//     rangeErrorEl.textContent = 'This value must be higher than the ‘Variable Range From’ field2.';
//     rangeErrorEl.style.display = 'block';
//     isValid = false;
//     firstErrorField = variableRangeTo;
//   } else if (rangeErrorEl) {
//     rangeErrorEl.textContent = '';
//     rangeErrorEl.style.display = 'none';
//   }

//   if (selectedDenominationType === 'Variable') {
//     const redeemAtIntervals = document.getElementById('_reedem_at_intervals');
//     const intervalErrorEl = document.getElementById('_redeem_at_intervals_error');
//     const redeemValue = parseFloat(redeemAtIntervals.value);
//     const redeemMax = parseFloat(redeemAtIntervals.max);

//     if (isNaN(redeemValue) || redeemValue > redeemMax) {
//       intervalErrorEl.textContent = `Please enter a valid value maximum(${redeemMax}).`;
//       intervalErrorEl.style.display = 'block';
//       isValid = false;
//       firstErrorField = firstErrorField || redeemAtIntervals;
//     } else {
//       intervalErrorEl.textContent = '';
//       intervalErrorEl.style.display = 'none';
//     }
//   } else {
//     const intervalErrorEl = document.getElementById('_redeem_at_intervals_error');
//     if (intervalErrorEl) {
//       intervalErrorEl.textContent = '';
//       intervalErrorEl.style.display = 'none';
//     }
//   }

//   return {
//     isValid,
//     firstErrorField
//   };
// }
jQuery(document).ready(function ($) {

  window.validateRedeemInterval = function () {
    let isValid = true;
    let firstErrorField = null;

    const denominationTypeDropdown = document.getElementById('denomination_type-dropdown');
    const selectedDenominationType = denominationTypeDropdown ? denominationTypeDropdown.value : '';

    const variableRangeFrom = document.getElementById('variable_range_from');
    const variableRangeTo = document.getElementById('variable_range_to');
    const rangeErrorEl = document.getElementById('_range_validation_error');

    const fromValue = parseFloat(variableRangeFrom.value);
    const toValue = parseFloat(variableRangeTo.value);

    document.getElementById('variable_range_from_error').style.display = 'none';
    document.getElementById('variable_range_to_error').style.display = 'none';
    rangeErrorEl.style.display = 'none';

    // Validation for 'Variable Range From' field
    if (!isNaN(fromValue) && !isNaN(toValue)) {
      if (fromValue > toValue) {
        // Show error for 'From' field if value is greater than 'To'
        document.getElementById('variable_range_from_error').textContent = 'This value must be less than the Variable Range To field. Please update.';
        document.getElementById('variable_range_from_error').style.display = 'block';
        isValid = false;
        firstErrorField = variableRangeFrom;
      }
    }

    // Validation for 'Variable Range To' field
    if (!isNaN(fromValue) && !isNaN(toValue)) {
      if (toValue < fromValue) {
        // //console.log('In----------------');
        // Show error for 'To' field if value is less than 'From'
        document.getElementById('variable_range_to_error').textContent = 'This value must be higher than the Variable Range From field. Please update.';
        document.getElementById('variable_range_to_error').style.display = 'block';
        isValid = false;
        firstErrorField = variableRangeTo;
      }
    }
    if (selectedDenominationType.toLowerCase() === 'variable') {
      const redeemAtIntervals = document.getElementById('_reedem_at_intervals');
      const intervalErrorEl = document.getElementById('_redeem_at_intervals_error');
      const redeemValueRaw = redeemAtIntervals.value.trim();

      // Check if field has any value first
      if (redeemValueRaw !== "") {
        const redeemValue = parseFloat(redeemValueRaw);
        const redeemMax = parseFloat(redeemAtIntervals.max);

        if (isNaN(redeemValue) || redeemValue > redeemMax) {
          intervalErrorEl.textContent = `Please enter a valid value maximum (${redeemMax}).`;
          intervalErrorEl.style.display = 'block';
          isValid = false;
          firstErrorField = firstErrorField || redeemAtIntervals;
        } else {
          intervalErrorEl.textContent = '';
          intervalErrorEl.style.display = 'none';
        }
      } else {
        // If empty, clear previous error
        intervalErrorEl.textContent = '';
        intervalErrorEl.style.display = 'none';
      }
    } else {
      const intervalErrorEl = document.getElementById('_redeem_at_intervals_error');
      if (intervalErrorEl) {
        intervalErrorEl.textContent = '';
        intervalErrorEl.style.display = 'none';
      }
    }


    return {
      isValid,
      firstErrorField
    };
  };
  const varRangeFrom = document.getElementById('variable_range_from');
  if (varRangeFrom) varRangeFrom.addEventListener('keyup', function () {
    window.validateRedeemInterval();
  });

  const varRangeTo = document.getElementById('variable_range_to');
  if (varRangeTo) varRangeTo.addEventListener('keyup', function () {
    window.validateRedeemInterval();
  });
  // grab the fields
  const variableRangeFrom = document.getElementById('variable_range_from');
  const variableRangeTo = document.getElementById('variable_range_to');
  const redeemAtIntervals = document.getElementById('_reedem_at_intervals');
  const intervalErrorEl = document.getElementById('_redeem_at_intervals_error');

  // update min/max whenever from/to changes
  function updateIntervalBounds() {
    const from = parseFloat(variableRangeFrom.value) || 0;
    const to = parseFloat(variableRangeTo.value) || 0;

    // redeemAtIntervals.setAttribute('min', from);
    redeemAtIntervals.setAttribute('max', to);

    // immediately re-validate if there's already a value in the field
    // validateInterval();
  }
  variableRangeFrom.addEventListener('input', updateIntervalBounds);
  variableRangeTo.addEventListener('input', updateIntervalBounds);

  // validate on user input
  function validateInterval() {
    const val = parseFloat(redeemAtIntervals.value);
    const max = parseFloat(redeemAtIntervals.max);

    if (isNaN(val) || val > max) {
      intervalErrorEl.textContent = `Please enter a valid value (maximum ${max}).`;
      intervalErrorEl.style.display = 'block';
      return false;
    } else {
      intervalErrorEl.textContent = '';
      intervalErrorEl.style.display = 'none';
      return true;
    }
  }
  redeemAtIntervals.addEventListener('input', validateInterval);

  // initialize on load
  updateIntervalBounds();

  // function toggleSkuFields() {
  //   const selectedValue = document.querySelector('input[name="sku_type"]:checked')?.value;
  //   const autoPopulateFiledWrappe = document.getElementById('auto_populate_field_wrapper');
  //   const wrapperField = document.getElementById('parent_sku_field_wrapper');

  //   if (selectedValue === 'Child') {
  // autoPopulateFiledWrappe.style.display = 'block';
  //     wrapperField.style.display = 'block';
  //   } else if (selectedValue === 'Parent') {
  //     autoPopulateFiledWrappe.style.display = 'none';
  //     wrapperField.style.display = 'block';
  //   } else {
  //     autoPopulateFiledWrappe.style.display = 'none';
  //     wrapperField.style.display = 'none';
  //   }
  // }

  // // Event listener on radio buttons
  // document.querySelectorAll('input[name="sku_type"]').forEach(radio => {
  //   radio.addEventListener('change', toggleSkuFields);
  // });

  // Initial call
  // document.addEventListener('DOMContentLoaded', toggleSkuFields);

  // $("#total_sell_price, #total_buy_price, #discounted_price input").on("input", function () {
  //   calculateMargins();
  // });

  // function calculateMargins() {
  //   const sellingPrice = parseFloat($("#total_sell_price").val()) || 0;
  //   const buyPrice = parseFloat($("#total_buy_price").val()) || 0;
  //   const discountedPrice = parseFloat($("#discounted_price input").val()) || sellingPrice;

  //   const regularMargin = sellingPrice - buyPrice;
  //   $("#_margin input").val(regularMargin.toFixed(2));

  //   const discountMargin = discountedPrice - buyPrice;
  //   $("#_discount_margin input").val(discountMargin.toFixed(2));

  //   //console.log('Current Margins:', {
  //     sellingPrice: sellingPrice,
  //     buyPrice: buyPrice,
  //     discountedPrice: discountedPrice,
  //     regularMargin: regularMargin,
  //     discountMargin: discountMargin
  //   });
  // }

  // // Initial calculation
  // calculateMargins();

  //Toggle Denomination type DD
  // Toggle visibility for denomination type
  const denominationTypeDropdown = document.getElementById('denomination_type-dropdown');

  if (denominationTypeDropdown) {
    // //console.log('logged');

    // Fields to show for "Variable"
    const fieldsToToggleVariable = [
      'reedem_at_intervals_wrapper',
      'sell_price_lowest_denomination_wrapper',
      'variable_range_from_wrapper',
      'variable_range_to_wrapper',
    ].map(id => document.getElementById(id));

    // Field and label for "Fixed"
    const sellPriceFixedField = document.getElementById('_sell_price_fixed');
    const denominationAmount = document.getElementById('_denomination_amount');
    const sellPriceFixedLabel = document.querySelector("label[for='_sell_price_fixed']"); // Get the label
    const costPriceLabel = document.querySelector("label[for='cost_price']");

    function toggleFields() {
      const selectedValue = denominationTypeDropdown.value;

      // Show fields for "Variable", hide otherwise
      fieldsToToggleVariable.forEach(field => {
        if (field) {
          if (selectedValue.toLowerCase() === 'variable') {
            costPriceLabel.textContent = 'Cost Price Lowest Denomination';
            // //console.log('Inside the Variable...');
            field.style.display = 'block';
            field.parentNode.classList.add('show');
            // //console.log(field.label);
          } else {
            costPriceLabel.textContent = 'Cost Price';
            // //console.log('Inside the Fixed Else...');
            field.style.display = 'none';
            field.parentNode.classList.remove('show');
            // Clear field value inside wrapper if input exists
            const input = field.querySelector('input, select, textarea');
            if (input) input.value = '';
          }
        }
      });

      // Show '_sell_price_fixed' and its label only when "Fixed" is selected
      if (sellPriceFixedField && sellPriceFixedLabel) {
        const displayValue = selectedValue.toLowerCase() === 'fixed' ? 'block' : 'none';
        sellPriceFixedField.style.display = displayValue;
        denominationAmount.style.display = displayValue;
        sellPriceFixedLabel.style.display = displayValue;
        sellPriceFixedField.parentNode.parentNode.classList.toggle('show', selectedValue.toLowerCase() === 'fixed');
        sellPriceFixedField.parentNode.parentNode.classList.toggle('hide', selectedValue.toLowerCase() !== 'fixed');
        denominationAmount.parentNode.parentNode.classList.toggle('show', selectedValue.toLowerCase() === 'fixed');
        denominationAmount.parentNode.parentNode.classList.toggle('hide', selectedValue.toLowerCase() !== 'fixed');
        if (selectedValue.toLowerCase() !== 'fixed') {
          sellPriceFixedField.value = '';
          denominationAmount.value = '';
          // Clear the additional fields
          const fieldsToClear = [
            'cost_price',
            'total_sell_price',
            'total_buy_price',
            'total_buy_price_including_gst'
          ];

          fieldsToClear.forEach(id => {
            const field = document.getElementById(id);
            if (field) field.value = '';
          });
        }
      }

      // if (costPriceLabel) {
      //   if (selectedValue === 'variable') {
      //     costPriceLabel.textContent = 'Cost Price Lowest Denomination';
      //   } else {
      //     costPriceLabel.textContent = 'Cost Price';
      //   }
      // }
      // Handle required attribute dynamically
      document.getElementById('sell_price_lowest_denomination').toggleAttribute('required', selectedValue.toLowerCase() === 'variable');
      document.getElementById('variable_range_from').toggleAttribute('required', selectedValue.toLowerCase() === 'variable');
      document.getElementById('variable_range_to').toggleAttribute('required', selectedValue.toLowerCase() === 'variable');
      document.getElementById('_reedem_at_intervals').toggleAttribute('required', selectedValue.toLowerCase() === 'variable');
      document.getElementById('_sell_price_fixed').toggleAttribute('required', selectedValue.toLowerCase() === 'fixed');
      document.getElementById('_denomination_amount').toggleAttribute('required', selectedValue.toLowerCase() === 'fixed');
    }

    // Listen for changes in the dropdown
    denominationTypeDropdown.addEventListener('change', toggleFields);

    // Run on page load to set initial visibility
    toggleFields();
  }

  jQuery('#_sell_price_fixed, #cost_price, #sell_price_lowest_denomination ,#total_buy_price').on('input', function () {

    let sellRaw = document.getElementById("_sell_price_fixed")?.value;
    let lowestRaw = document.getElementById("sell_price_lowest_denomination")?.value;
    let costRaw = document.getElementById("cost_price")?.value;

    // Decide which sell price to use
    let sellPriceFixed = sellRaw !== '' ? parseFloat(sellRaw) : parseFloat(lowestRaw);
    let costPriceField = parseFloat(costRaw);

    calculateMargin(sellPriceFixed, costPriceField, sellRaw, costRaw);
  });

  function calculateMargin(sellPriceFixed, costPriceField, sellRaw, costRaw) {

    let marginCurrency = document.getElementById("margin_currency");
    let marginPercent = document.getElementById("margin_per");

    if (sellRaw === '' && costRaw === '') {
        marginCurrency.value = '';
        marginPercent.value = '';
        return;
    }

    if (isNaN(sellPriceFixed) || isNaN(costPriceField)) {
        return;
    }

    let marginDollar = sellPriceFixed - costPriceField;
    marginCurrency.value = marginDollar.toFixed(2);

    if (costPriceField === 0) {
        marginPercent.value = '0.00';
    } else {
        let marginPercentage = (marginDollar / costPriceField) * 100;
        marginPercent.value = marginPercentage.toFixed(2);
    }
  }


  // Attach event listener to auto-calculate when prices change
  ["total_sell_price", "total_buy_price", "sell_price_lowest_denomination"].forEach(id => {
    let field = document.getElementById(id);
    if (field) field.addEventListener("input", calculateMargin);
  });

  //Check SKU Exist
  let skuCheckTimeout = null;

  const skuInput = document.getElementById('sku');
  if (skuInput) skuInput.addEventListener('input', function () {
    const sku = this.value.trim();
    const errorDiv = document.getElementById('sku_error');

    // Clear previous timeout if still waiting
    clearTimeout(skuCheckTimeout);

    // Debounce the check to avoid spamming requests
    skuCheckTimeout = setTimeout(() => {
      if (sku === '') {
        errorDiv.style.display = 'none';
        isSkuValid = false;
        return;
      }

      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('edit_product')) {
        // Bypass SKU existence check
        errorDiv.style.display = 'none';
        isSkuValid = true;
        return;
      }
      fetch(ajax_tags.ajax_url + '?action=check_sku_exists&sku=' + encodeURIComponent(sku))
        .then(response => response.json())
        .then(data => {
          if (data.exists) {
            errorDiv.textContent = 'SKU already exists.';
            errorDiv.style.display = 'block';
            isSkuValid = false;
          } else {
            errorDiv.style.display = 'none';
            isSkuValid = true;
          }
        })
        .catch(error => {
          console.error('Error checking SKU:', error);
          isSkuValid = false;
        });
    }, 300); // Delay of 300ms
  });
  //END Here
  // ===========================================

  const uploadArea = document.getElementById('upload-area');
  const fileBrowse = document.getElementById('file-browse');
  const fileInput = document.getElementById('file-input');
  const linkInputArea = document.getElementById('link-input-area');
  const imageLink = document.getElementById('image-link');
  const addLink = document.getElementById('add-link');
  const cancelLink = document.getElementById('cancel-link');
  const previewContainer = document.getElementById('preview-container');

  let selectedFiles = [];
  let selectedURLs = [];
  let existingImages = [];

  // Click to browse files
  uploadArea.addEventListener('click', (e) => {
    if (e.target !== fileBrowse) {
      fileInput.click();
    }
  });

  // Drag and drop handlers
  uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('highlight');
  });

  uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('highlight');
  });

  uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('highlight');
    handleFiles(e.dataTransfer.files);
  });

  // File input change
  fileInput.addEventListener('change', () => {
    handleFiles(fileInput.files);
  });

  // Link handling
  fileBrowse.addEventListener('click', (e) => {
    e.stopPropagation();
    linkInputArea.style.display = 'flex';
    uploadArea.style.display = 'none';
  });

  addLink.addEventListener('click', () => {
    const url = imageLink.value.trim();

    if (!validateImageURL(url)) {
      showUploadError(`Please enter a valid image URL`);
      return;
    }

    const img = new Image();
    img.src = url;

    img.onload = () => {
      const maxWidth = 600;
      const maxHeight = 379;

      if (img.width > maxWidth || img.height > maxHeight) {
        showUploadError(`Image must be maximum ${maxWidth} x ${maxHeight} pixels.`);
        return;
      }

      // Passed size check — now check file size (optional)
      fetch(url, { method: 'HEAD' })
        .then(response => {
          const size = response.headers.get('Content-Length');

          if (size && parseInt(size) > 3 * 1024 * 1024) { // 3MB
            showUploadError(`Image file size exceeds 3MB.`);
            return;
          }

          // ✅ All checks passed — continue
          selectedURLs.push(url);
          previewImage(url);

          const form = document.getElementById("gift-card-form");
          const hiddenInput = document.createElement("input");
          hiddenInput.type = "hidden";
          hiddenInput.name = "product_image_links[]";
          hiddenInput.value = url;
          form.appendChild(hiddenInput);

          imageLink.value = '';
          linkInputArea.style.display = 'none';
          uploadArea.style.display = 'block';
        })
        .catch(() => {
          console.warn('File size validation skipped — fetch failed');
          // You can still allow the image if dimensions were okay
          selectedURLs.push(url);
          previewImage(url);

          const form = document.getElementById("gift-card-form");
          const hiddenInput = document.createElement("input");
          hiddenInput.type = "hidden";
          hiddenInput.name = "product_image_links[]";
          hiddenInput.value = url;
          form.appendChild(hiddenInput);

          imageLink.value = '';
          linkInputArea.style.display = 'none';
          uploadArea.style.display = 'block';
        });
    };


    img.onerror = () => {
      showUploadError(`Image could not be loaded. Please check the URL.`);
    };
  });





  cancelLink.addEventListener('click', () => {
    linkInputArea.style.display = 'none';
    uploadArea.style.display = 'block';
  });

  function handleFiles(files) {


    Array.from(files).forEach(file => {
      if (file.size > 3 * 1024 * 1024) {
        showUploadError(`File size exceeds 3MB limit: ${file.name}`);
        return;
      }

      if (file.type.startsWith('image/')) {
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.src = url;
        img.onload = () => {
          const maxWidth = 600;
          const maxHeight = 379;

          if (img.width > maxWidth || img.height > maxHeight) {
            showUploadError(`${file.name} must be maximum ${maxWidth} x ${maxHeight} pixels.`);
            URL.revokeObjectURL(url); // cleanup if rejected
            return;
          }

          // Store as {file, url}
          selectedFiles.push({ file, url });

          // Preview with blob URL (not base64)
          previewImage(url, false);
        };
      } else {
        showUploadError(`Please upload an image file: ${file.name}`);
      }
    });
  }

  function showUploadError(message) {
    const errorContainer = document.getElementById('upload-error');
    const errorItem = document.createElement('div');
    errorItem.textContent = message;
    errorContainer.appendChild(errorItem);
    setTimeout(() => {
      errorItem.remove(); // Remove the individual error message
    }, 5000);
  }

  // Function to remove invalid preview image
  function removePreview(file) {
    // Find the image preview element in the preview container
    const previewItems = previewContainer.querySelectorAll('.preview-item');
    previewItems.forEach(item => {
      const img = item.querySelector('img');
      if (img && img.src === file) {
        item.remove();  // Remove the preview item from the container
      }
    });
  }



  // Only initialize if we're in edit mode and there are existing preview items
  if (previewContainer.classList.contains('is-edit-mode')) {
    const existingPreviews = previewContainer.querySelectorAll('.preview-item');

    existingPreviews.forEach((previewItem, index) => {
      // Add the preview-image class to the image
      const img = previewItem.querySelector('img');
      if (img) {
        // Ensure consistent styling
        img.classList.add('preview-image');
        img.style.display = 'block';   // Ensure proper display

        // Add remove button
        const removeBtn = document.createElement('button');
        removeBtn.innerHTML = '⋯';
        removeBtn.classList.add('remove-preview-image-btn');
        removeBtn.title = 'Image options';
        removeBtn.type = 'button';
        previewItem.appendChild(removeBtn);

        // Add options container
        const optionsContainer = document.createElement('div');
        optionsContainer.classList.add('image-options');


        // Set as cover option
        const setCoverOption = document.createElement('button');
        setCoverOption.textContent = 'Set as cover image';
        setCoverOption.classList.add('image-option');
        setCoverOption.addEventListener('click', (e) => {
          e.stopPropagation();
          if (previewContainer.children.length > 1) {
            setAsCoverImage(previewItem);
          }
        });
        setCoverOption.type = 'button'; // Prevent form submission


        // Remove option
        const removeOption = document.createElement('button');
        removeOption.textContent = 'Remove';
        removeOption.classList.add('image-option');
        removeOption.type = 'button'; // Prevent form submission
        removeOption.addEventListener('click', (e) => {
          e.stopPropagation();
          const isCover = img.classList.contains('cover-image');
          previewItem.remove();

          // If cover was removed, set new cover
          if (isCover && previewContainer.children.length > 0) {
            const firstImg = previewContainer.querySelector('.preview-item:first-child img');
            if (firstImg) {
              firstImg.classList.add('cover-image');
              const coverLabel = document.createElement('div');
              coverLabel.classList.add('cover-label');
              coverLabel.textContent = 'Cover Image';
              firstImg.parentNode.insertBefore(coverLabel, firstImg);
            }
          }
        });

        // Build options menu
        optionsContainer.appendChild(setCoverOption);
        optionsContainer.appendChild(removeOption);
        optionsContainer.style.display = 'none';
        previewItem.appendChild(optionsContainer);

        // Hover events
        removeBtn.addEventListener('mouseenter', () => {
          optionsContainer.style.display = 'block';
        });

        previewItem.addEventListener('mouseleave', (e) => {
          if (!optionsContainer.contains(e.relatedTarget)) {
            optionsContainer.style.display = 'none';
          }
        });

        optionsContainer.addEventListener('mouseleave', () => {
          optionsContainer.style.display = 'none';
        });

        // Mark first image as cover if not already
        if (index === 0 && !img.classList.contains('cover-image')) {
          img.classList.add('cover-image');
          if (!previewItem.querySelector('.cover-label')) {
            const coverLabel = document.createElement('div');
            coverLabel.classList.add('cover-label');
            coverLabel.textContent = 'Cover Image';
            previewItem.insertBefore(coverLabel, img);
          }
        }
      }
    });
  }


  // Helper function to set an image as cover
  function setAsCoverImage(wrapper) {
    const previewContainer = document.getElementById('preview-container');
    const firstWrapper = previewContainer.children[0];
    if (!firstWrapper || firstWrapper === wrapper) return;

    // Remove cover class from all images
    document.querySelectorAll('.preview-image').forEach(img => {
      img.classList.remove('cover-image');
    });

    // Remove all cover labels
    document.querySelectorAll('.cover-label').forEach(label => {
      label.remove();
    });

    // Add cover class to selected image
    const img = wrapper.querySelector('.preview-image');
    img.classList.add('cover-image');

    // Add cover label
    const coverLabel = document.createElement('div');
    coverLabel.classList.add('cover-label');
    coverLabel.textContent = 'Cover Image';
    wrapper.insertBefore(coverLabel, img);

    const existingId = img.getAttribute('data-image-id');
    const coverValue = existingId ? existingId : img.src;

    jQuery('#cover-img').remove();
    jQuery('<input type="hidden" name="cover-img" id="cover-img" value="' + coverValue + '">').insertBefore(jQuery('.preview-item .cover-label'));

    // Move to first position
    previewContainer.insertBefore(wrapper, firstWrapper);

    syncSelectedFilesWithDOM();
  }

  // Function to preview new images (for create mode)
  function previewImage(source, isURL = false) {
    const previewContainer = document.getElementById('preview-container');
    const wrapper = document.createElement('div');
    wrapper.classList.add('preview-item');

    const img = document.createElement('img');
    img.src = source;
    img.classList.add('preview-image');

    document.getElementById('required-image-error').style.display = 'none';

    // Add cover-image class to first image only
    if (previewContainer.children.length === 0) {
      img.classList.add('cover-image');
      const coverLabel = document.createElement('div');
      coverLabel.classList.add('cover-label');
      coverLabel.textContent = 'Cover Image';
      jQuery('#cover-img').remove();
      wrapper.appendChild(coverLabel);

      const coverImg = document.createElement('input');
      coverImg.type = 'hidden';
      coverImg.name = 'cover-img';
      coverImg.id = 'cover-img';
      coverImg.value = img.src;
      wrapper.appendChild(coverImg);
    }

    // Remove button (X icon)
    const removeBtn = document.createElement('button');
    // removeBtn.innerHTML = '<span class="icon">X</span>';
    removeBtn.classList.add('remove-preview-image-btn');
    removeBtn.innerHTML = '⋯';
    removeBtn.title = 'Image options';
    removeBtn.type = 'button';


    // Options container
    const optionsContainer = document.createElement('div');
    optionsContainer.classList.add('image-options');

    // Set as cover option
    const setCoverOption = document.createElement('button');
    setCoverOption.textContent = 'Set as cover image';
    setCoverOption.classList.add('image-option');
    setCoverOption.addEventListener('click', (e) => {
      e.stopPropagation();
      if (previewContainer.children.length > 1) {
        setAsCoverImage(wrapper);
      }
    });
    setCoverOption.type = 'button'; // Prevent form submission


    // Remove option
    const removeOption = document.createElement('button');
    removeOption.textContent = 'Remove';
    removeOption.classList.add('image-option');
    removeOption.type = 'button'; // Prevent form submission
    removeOption.addEventListener('click', (e) => {
      e.stopPropagation();
      const isCover = img.classList.contains('cover-image');
      wrapper.remove();

      if (isURL) {
        selectedURLs = selectedURLs.filter(url => url !== source);
      } else {
        selectedFiles = selectedFiles.filter(file => {
          const reader = new FileReader();
          reader.readAsDataURL(file);
          return reader.result !== source;
        });
      }

      // If cover was removed, set new cover
      if (isCover && previewContainer.children.length > 0) {
        const firstImg = previewContainer.querySelector('.preview-item:first-child img');
        if (firstImg) {
          firstImg.classList.add('cover-image');
          const coverLabel = document.createElement('div');
          coverLabel.classList.add('cover-label');
          coverLabel.textContent = 'Cover Image';
          firstImg.parentNode.insertBefore(coverLabel, firstImg);
        }
      }
    });

    // Build options menu
    optionsContainer.appendChild(setCoverOption);
    optionsContainer.appendChild(removeOption);
    optionsContainer.style.display = 'none';

    // Hover events
    removeBtn.addEventListener('mouseenter', () => {
      optionsContainer.style.display = 'block';
      removeBtn.classList.add('hovered');
    });

    wrapper.addEventListener('mouseleave', (e) => {
      if (!optionsContainer.contains(e.relatedTarget)) {
        optionsContainer.style.display = 'none';
        removeBtn.classList.remove('hovered');
      }
    });

    optionsContainer.addEventListener('mouseleave', () => {
      optionsContainer.style.display = 'none';
    });

    wrapper.appendChild(img);
    wrapper.appendChild(removeBtn);
    wrapper.appendChild(optionsContainer);
    previewContainer.appendChild(wrapper);
  }

  function swapImages(selectedWrapper) {
    const firstWrapper = previewContainer.children[0];
    if (!firstWrapper || firstWrapper === selectedWrapper) return;

    // Remove cover class and label from current cover
    firstWrapper.classList.remove('cover-image');
    const oldLabel = firstWrapper.querySelector('.cover-label');
    if (oldLabel) oldLabel.remove();

    // Add cover class and label to new cover
    selectedWrapper.classList.add('cover-image');
    const coverLabel = document.createElement('div');
    coverLabel.classList.add('cover-label');
    coverLabel.textContent = 'Cover Image';
    selectedWrapper.insertBefore(coverLabel, selectedWrapper.firstChild);

    // Move the selected wrapper to first position
    previewContainer.insertBefore(selectedWrapper, firstWrapper);
  }

  function validateImageURL(url) {
    return /^(https?:\/\/.*\.(?:png|jpg|jpeg|gif|svg|webp)(?:\?.*)?$)/i.test(url);
  }

  // ===========================================

  function syncSelectedFilesWithDOM() {
    const previewContainer = document.getElementById('preview-container');
    const newFileOrder = [];
    const newUrlOrder = [];
    const newexistingImages = [];

    previewContainer.querySelectorAll('.preview-item').forEach(item => {
      const hiddenInput = item.querySelector('input[type="hidden"]');
      const imgSrc = hiddenInput ? hiddenInput.value : item.querySelector('img').src;

      // If it's a blob/file, match from selectedFiles
      if (imgSrc && !isNaN(parseInt(imgSrc)) && parseInt(imgSrc) > 0) {
        newexistingImages.push(parseInt(imgSrc));
      } else if (imgSrc.startsWith("blob:")) {
        const fileItem = selectedFiles.find(f => f.url === imgSrc);
        if (fileItem) newFileOrder.push(fileItem);
      } else {
        // Otherwise it's from already uploaded URLs
        const urlItem = selectedURLs.find(u => u === imgSrc);
        if (urlItem) newUrlOrder.push(urlItem);
      }
    });

    selectedFiles = newFileOrder;
    selectedURLs = newUrlOrder;
    existingImages = newexistingImages;

  }


  // --- Create Product ---
  $("#create-product").on("click", function (e) {
    e.preventDefault();
    supplierDropdown.on("change", validateEligibleField);

    jQuery("#gift-card-form").on("submit", function (e) {
      if (!validateEligibleField()) {
          e.preventDefault();
          // jQuery('html, body').animate({ scrollTop: jQuery("#gc_error").offset().top - 100 }, 100);
          window.scrollTo(0, jQuery("#gc_error").offset().top - 100);

          // alert("Please select at least 1 Eligible Gift Card for Supplier = J&C");
      }
    });

    //Discounted and onsite dates Validations Start

    $(".discount-error, .onsite-error").remove();

    // --- Discount date validation ---
    let fromDate = $("#_discount_valid_from").val();
    let toDate = $("#_discount_valid_to").val();
    const discountedCheck = document.getElementById('discounted_price_checkbox');
    if (discountedCheck.checked) {
      if (!fromDate || !toDate) {
        if (!fromDate) {
          $("#_discount_valid_from").after(
            '<div class="discount-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Please select "Discount Valid From".</div>'
          );
          $('html, body').animate({ scrollTop: $("#_discount_valid_from").offset().top - 100 }, 500);
        }

        if (!toDate) {
          $("#_discount_valid_to").after(
            '<div class="discount-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Please select "Discount Valid To".</div>'
          );
          $('html, body').animate({ scrollTop: $("#_discount_valid_to").offset().top - 100 }, 500);
        }
        return; // Stop further validation if any field is empty
      } else {
        // --- Step 2: Compare dates ---
        let from = new Date(fromDate);
        let to = new Date(toDate);

        if (isNaN(from.getTime()) || isNaN(to.getTime())) {
          $("#_discount_valid_to").after(
            '<div class="discount-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Invalid discount date(s).</div>'
          );
          $('html, body').animate({ scrollTop: $("#_discount_valid_to").offset().top - 100 }, 500);
          return;
        }

        if (from.getTime() > to.getTime()) {
          $("#_discount_valid_to").after(
            '<div class="discount-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ "Discount Valid To" must be greater than or equal to "Discount Valid From".</div>'
          );
          $('html, body').animate({ scrollTop: $("#_discount_valid_to").offset().top - 100 }, 500);
          return;
        }
      }

    }


    // --- Onsite date validation (same rules) ---
    let onsiteFromDate = $("#_onsite_from").val();
    let onsiteToDate = $("#_onsite_to").val();
    const alwaysOn = document.getElementById('always_on');
    if (!alwaysOn.checked) {
      // Only validate if at least one of the fields is filled
      if (!onsiteFromDate || !onsiteToDate) {

        // Step 1: Check for empty fields
        if (!onsiteFromDate) {
          $("#_onsite_from").after(
            '<div class="onsite-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Please select "Onsite From".</div>'
          );
          $('html, body').animate({ scrollTop: $("#_onsite_from").offset().top - 100 }, 500);
        }

        if (!onsiteToDate) {
          $("#_onsite_to").after(
            '<div class="onsite-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Please select "Onsite To".</div>'
          );
          $('html, body').animate({ scrollTop: $("#_onsite_to").offset().top - 100 }, 500);
        }
        return;

      } else {
        let onsiteFrom = new Date(onsiteFromDate);
        let onsiteTo = new Date(onsiteToDate);
        if (isNaN(onsiteFrom.getTime()) || isNaN(onsiteTo.getTime())) {
          $("#_onsite_to").after(
            '<div class="onsite-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ Invalid onsite date(s).</div>'
          );
          $('html, body').animate({ scrollTop: $("#_onsite_to").offset().top - 100 }, 500);
          return;
        }

        if (onsiteFrom.getTime() > onsiteTo.getTime()) {
          $("#_onsite_to").after(
            '<div class="onsite-error" style="color:red; font-size:14px; margin-top:5px;">⚠️ "Onsite To" must be greater than or equal to "Onsite From".</div>'
          );
          $('html, body').animate({ scrollTop: $("#_onsite_to").offset().top - 100 }, 500);
          return;
        }
      }
    }

    //Discounted and onsite dates Validations End

    let formData = new FormData();
    let form_data = $('#gift-card-form').serialize();
    let upload_image_nonce = $('#upload_image_nonce').val();

    formData.append("action", "create_product_new");
    formData.append("form_data", form_data);
    formData.append("security", upload_image_nonce);
    formData.append("gift_card_title", $("#gift_card_title").val());

    // Remove all existing file/url inputs before rebuilding
    jQuery('#gift-card-form input[name="product_images[]"], #gift-card-form input[name="product_image_urls[]"]').remove();

    // Append selected images
    let pf = [];
    jQuery('#gift-card-form #hidden-file-inputs').remove();

    jQuery("#gift-card-form").append('<div id="hidden-file-inputs"></div>');


    let fileInputContainer = jQuery("#hidden-file-inputs");
    if (!fileInputContainer.length) {
      fileInputContainer = jQuery('<div id="hidden-file-inputs"></div>');
      jQuery("#gift-card-form").append(fileInputContainer);
    } else {
      fileInputContainer.empty(); // clean slate
    }

    syncSelectedFilesWithDOM();

    // Append new ordered files
    selectedFiles.forEach((item, index) => {
      const file = item.file;

      formData.append('product_images[]', file);

      // Create hidden file input for form submission
      let newFileInput = jQuery('<input>', {
        type: 'file',
        name: 'product_images[]',
        style: 'display: none;',
      });

      let dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      newFileInput[0].files = dataTransfer.files;

      fileInputContainer.append(newFileInput);
    });


    // Append existing image IDs in order
    existingImages.forEach((id, index) => {
      formData.append('new_existingImages[]', id);   // ✅ changed key name

      let newHiddenInput = jQuery('<input>', {
        type: 'hidden',
        name: 'new_existingImages[]',  // ✅ changed key name
        value: id
      });

      fileInputContainer.append(newHiddenInput);
    });

    // Append existing URLs in order
    selectedURLs.forEach((url, index) => {
      formData.append('product_image_urls[]', url);

      let newUrlInput = jQuery('<input>', {
        type: 'hidden',
        name: 'product_image_urls[]',
        value: url
      });

      fileInputContainer.append(newUrlInput);
    });
    //jQuery("#gift-card-form #hidden-file-inputs").remove();
    //jQuery("#gift-card-form").append('<div id="hidden-file-inputs">'+fileInputContainer+'</div>');

    jQuery('#gift-card-form').submit();
  });

  //Remove the error on fill the value Start code here

  jQuery('#gift-card-form').on('input change', 'input[required], select[required], textarea[required]', function () {
    const $field = jQuery(this);
    const value = $field.val();
    const type = $field.attr("type");

    // Remove error if field has value
    if (type === 'radio') {
      const name = $field.attr("name");
      if (jQuery(`[name="${name}"]`).is(':checked')) {
        jQuery(`[name="${name}"]`).last().parent().find('.error-message').remove();
        jQuery(`[name="${name}"]`).removeClass('error');
      }
    } else if (value && value.trim() !== '') {
      // $field.next('.error-message').remove();
      $field.next('.error-message').remove();
      $field.removeClass('error');
    }
  });

  // Remove SKU specific error on change
  // jQuery('#sku').on('input', function () {
  //   jQuery('#sku_validation_error, #sku_error').hide().text('');
  //   jQuery(this).removeClass('error');
  // });

  // Remove Supplier SKU specific error
  // jQuery('#supplier_sku').on('input', function () {
  //   jQuery('#supplier_sku_error').hide().text('');
  //   jQuery(this).removeClass('error');
  // });

  const skuRegex = /^[A-Za-z0-9_.-]*$/;

  // Live validation for SKU field
  $('#sku').on('input', function () {
    const $this = $(this);
    const value = $this.val().trim();

    if (skuRegex.test(value) || value === "") {
      $('#sku_validation_error, #sku_error').hide();
      $this.removeClass('error');
    } else {
      $('#sku_validation_error')
        .show()
        .text("Only letters, numbers, `-`,`.` and underscores '_' allowed. No spaces or special characters.");
      $this.addClass('error');
    }
  });

  // Live validation for Supplier SKU field
  $('#supplier_sku').on('input', function () {
    const $this = $(this);
    const value = $this.val().trim();

    if (skuRegex.test(value) || value === "") {
      $('#supplier_sku_error').hide();
      $this.removeClass('error');
    } else {
      $('#supplier_sku_error')
        .show()
        .text("Only letters, numbers, `-`,`.` and underscores '_' allowed. No spaces or special characters.");
      $this.addClass('error');
    }
  });

  // Remove error on expiry type change
  jQuery('#gift_card_expiry_type').on('change', function () {
    jQuery(this).next('.error-message').remove();
    jQuery(this).removeClass('error');
  });


  const supplierDropdown = jQuery("#supplier-dropdown");
  const eligibleSection = jQuery("#gc_eligible_field_wrap");
  const selectedJson = jQuery("#eligible_gift_cards_json");
  const errorBox = jQuery("#gc_error");

  //Remove the error on fill the value End code here
  function validateEligibleField() {
    const supplierVal = supplierDropdown.val();
    const selectedVal = selectedJson.val();

    // Supplier = J&C (value = 234)
    if (supplierVal === "234") {
        eligibleSection.addClass("required-field");

        if (!selectedVal || selectedVal === "[]") {
            errorBox.show();
            return false; // INVALID
        } else {
            errorBox.hide();
            return true; // VALID
        }
    } else {
        eligibleSection.removeClass("required-field");
        errorBox.hide();
        return true; // VALID
    }
  }


  $(".save_step").on("click", function (e) {
    e.preventDefault();

    let formData = new FormData();
    let form_data = $('#gift-card-form').serialize();
    let upload_image_nonce = $('#upload_image_nonce').val();
    let missingFields = [];
    // let pf = [];

    supplierDropdown.on("change", validateEligibleField);

    jQuery("#gift-card-form").on("submit", function (e) {
      if (!validateEligibleField()) {
          e.preventDefault();
          // jQuery('html, body').animate({ scrollTop: jQuery("#gc_error").offset().top - 100 }, 500);
          window.scrollTo(0, jQuery("#gc_error").offset().top - 100);

          // alert("Please select at least 1 Eligible Gift Card for Supplier = J&C");
      }
    });


    jQuery('#gift-card-form #hidden-file-inputs').remove();
    jQuery("#gift-card-form").append('<input type="hidden" value="1" name="exit_save" id="hidden-exit_save" />');

    jQuery(".error-message").remove();
    let firstErrorField = null;

    const skuInput = document.getElementById("sku");
    const skuError = document.getElementById("sku_validation_error");
    const supplierSkuInput = document.getElementById("supplier_sku");
    const supplierSkuError = document.getElementById("supplier_sku_error");
    const letterOnlyRegex = /^[A-Za-z0-9_.-]*$/;

    // Inline validation for SKU
    let skuValid = true;

    if (!letterOnlyRegex.test(skuInput.value)) {
      if (skuError) skuError.style.display = "block";
      skuValid = false;
      if (!firstErrorField) firstErrorField = $(skuInput);
    } else {
      if (skuError) skuError.style.display = "none";
    }

    // Inline validation for Supplier SKU
    let supplierSkuValid = true;
    if (!letterOnlyRegex.test(supplierSkuInput.value)) {
      if (supplierSkuError) supplierSkuError.style.display = "block";
      // supplierSkuError.style.display = "block";
      supplierSkuValid = false;
      if (!firstErrorField) firstErrorField = $(supplierSkuInput);
    } else {
      if (supplierSkuError) supplierSkuError.style.display = "none";
    }
    let redeemValidation = { isValid: true, firstErrorField: null };

    jQuery(".form-step").each(function () {
      jQuery(document).on('change', '#gift_card_expiry_type', function () {
        const selectedVal = jQuery(this).val();
        if (selectedVal) {
          jQuery(this).next('.error-message').remove();
        }
      });
      if (jQuery(this).hasClass("active")) {
        let requiredFields = jQuery(this).find("[required]");
        let handledRadioGroups = [];
        // let missingFields = []; // If you still use this
        let firstErrorField = null;

        requiredFields.each(function () {
          const $field = jQuery(this);
          const type = $field.attr("type");

          // 🔸 Handle radio groups
          if (type === "radio") {
            const name = $field.attr("name");

            if (handledRadioGroups.includes(name)) return; // Already validated this group
            handledRadioGroups.push(name);

            const $group = jQuery(`[name="${name}"]`);
            const isChecked = $group.is(":checked");

            if (!isChecked) {
              const fieldLabel = 'This';

              // Show error after the last radio
              const $lastRadio = $group.last();
              if ($lastRadio.closest('.radio-group-wrapper').find('.error-message').length === 0) {
                $lastRadio.parent().append(
                  `<span class="error-message" style="color: red; font-size: 15px;">${fieldLabel} field is required</span><br>`
                );
              }

              if (!firstErrorField) {
                firstErrorField = $field.closest('.control-wrapper');
              }

              missingFields.push(name);
            } else {
              // Remove existing error messages
              $group.each(function () {
                jQuery(this).siblings('.error-message').remove();
              });
            }
          } else if (!$field.val() || $field.val().trim() === '') {
            const nameOrId = $field.attr("name") || $field.attr("id");
            const cleanName = nameOrId.replace(/\[\]/g, '');
            const fieldLabel = jQuery('label[for="' + $field.attr("id") + '"]').text() || cleanName;

            $field.next('.error-message').remove(); // Remove existing first


            // ✅ Custom message for parent_sku if sku_type is Child
            const isParentSkuField = $field.attr("id") === "parent_sku";
            const skuType = jQuery('input[name="sku_type"]:checked').val();

            if (isParentSkuField && skuType === 'Child') {
              $field.after(`<span class="error-message" style="color: red; font-size: 15px;">Please add a parent SKU when selecting Child as your SKU Type.</span>`);
            } else {
              $field.after(`<span class="error-message" style="color: red; font-size: 15px;">${fieldLabel} field is required</span>`);
            }

            // $field.after(`<span class="error-message" style="color: red; font-size: 15px;">${fieldLabel} field is required</span><br>`);

            if (!firstErrorField) {
              firstErrorField = $field;
            }

            missingFields.push(cleanName);
          } else {
            $field.next('.error-message').remove();
          }
        });

        // ✅ Call redeem interval validation
        redeemValidation = validateRedeemInterval();
        if (!redeemValidation.isValid) {
          firstErrorField = firstErrorField || jQuery(redeemValidation.firstErrorField);
        }

        // ✅ Custom validation
        const allValid = validateAll();
        if (!allValid) {
          return false;
        }

        // 🔹 Remove 'required' from future steps
        jQuery(this).nextAll(".form-step").find("input, select, textarea").removeAttr("required");

        // 🔸 Scroll to first error field
        if (firstErrorField && firstErrorField.length) {
          firstErrorField[0].scrollIntoView({ behavior: "smooth", block: "center" });
          firstErrorField.focus();
        }
      }
    });


    // Check if async SKU check already failed
    if (!isSkuValid) {
      const skuInput = document.getElementById('sku');
      const skuError = document.getElementById('sku_error');

      // Check if there's already an existing error message in the skuError div
      if (skuError.style.display === 'block') {
        skuError.style.display = 'none';
      }

      // Set the new error message
      skuError.textContent = 'Please enter a unique SKU.';
      skuError.style.display = 'block';

      skuInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
      skuInput.focus();

      // Set firstErrorField to the SKU input element, or use the previous firstErrorField
      firstErrorField = firstErrorField || $(skuInput);

      return false; // Prevent form submission or further processing
    }

    if (missingFields.length > 0 || !skuValid || !supplierSkuValid || !redeemValidation.isValid) {
      if (firstErrorField) {
        firstErrorField[0].scrollIntoView({ behavior: "smooth", block: "center" });
        firstErrorField.focus();
      }
      return;
    }
    // Remove all existing file/url inputs before rebuilding
    jQuery('#gift-card-form input[name="product_images[]"], #gift-card-form input[name="product_image_urls[]"]').remove();

    // Append selected images
    let pf = [];
    jQuery('#gift-card-form #hidden-file-inputs').remove();

    jQuery("#gift-card-form").append('<div id="hidden-file-inputs"></div>');


    let fileInputContainer = jQuery("#hidden-file-inputs");
    if (!fileInputContainer.length) {
      fileInputContainer = jQuery('<div id="hidden-file-inputs"></div>');
      jQuery("#gift-card-form").append(fileInputContainer);
    } else {
      fileInputContainer.empty(); // clean slate
    }

    syncSelectedFilesWithDOM();

    // Append new ordered files
    selectedFiles.forEach((item, index) => {
      const file = item.file;

      formData.append('product_images[]', file);

      // Create hidden file input for form submission
      let newFileInput = jQuery('<input>', {
        type: 'file',
        name: 'product_images[]',
        style: 'display: none;',
      });

      let dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      newFileInput[0].files = dataTransfer.files;

      fileInputContainer.append(newFileInput);
    });


    // Append existing image IDs in order
    existingImages.forEach((id, index) => {
      formData.append('new_existingImages[]', id);   // ✅ changed key name

      let newHiddenInput = jQuery('<input>', {
        type: 'hidden',
        name: 'new_existingImages[]',  // ✅ changed key name
        value: id
      });

      fileInputContainer.append(newHiddenInput);
    });

    // Append existing URLs in order
    selectedURLs.forEach((url, index) => {
      formData.append('product_image_urls[]', url);

      let newUrlInput = jQuery('<input>', {
        type: 'hidden',
        name: 'product_image_urls[]',
        value: url
      });

      fileInputContainer.append(newUrlInput);
    });
    // if (selectedFiles.length === 0) {
    //   formData.append('product_images', '');
    // }

    // if (!fileInputContainer.length) {
    //   jQuery("#gift-card-form").append('<div id="hidden-file-inputs"></div>');
    // }

    // selectedFiles.forEach((file, index) => {
    //   formData.append('product_images[]', file);
    //   pf.push(file);

    //   let newFileInput = jQuery('<input>', {
    //     type: 'file',
    //     name: 'product_images[]',
    //     style: 'display: none;',
    //   });

    //   let dataTransfer = new DataTransfer();
    //   dataTransfer.items.add(file);
    //   newFileInput[0].files = dataTransfer.files;

    //   fileInputContainer.append(newFileInput);
    // });

    // selectedFiles.forEach((file, index) => {
    //   // Append to formData safely
    //   // if (file instanceof File) {
    //     formData.append('product_images[]', file);
    //     pf.push(file);

    //     let newFileInput = jQuery('<input>', {
    //       type: 'file',
    //       name: 'product_images[]',
    //       style: 'display: none;',
    //     });

    //     let dataTransfer = new DataTransfer();
    //     dataTransfer.items.add(file);
    //     newFileInput[0].files = dataTransfer.files;

    //     fileInputContainer.append(newFileInput);
    // });


    // ✅ All checks passed — submit the form
    jQuery('#gift-card-form').submit();
  });



  // document.getElementById("_total_value_per_transaction").addEventListener("input", function () {
  //   if (!this.value.startsWith("$")) {
  //     this.value = "$" + this.value.replace(/\$/g, ""); // Ensure only one "$"
  //   }
  // });


  //========================================= selectElement For Category
  let selectElementCat = $("#product_cat");

  function formatCategory(category) {
    if (!category.id) {
      return category.text;
    }

    let option = selectElementCat.find("option[value='" + category.id + "']");
    let isSelected = option.prop("selected");
    let checkbox = '<input type="checkbox" class="select2-checkbox-cat" data-value="' + category.id + '" ' + (isSelected ? 'checked' : '') + ' />';

    return $('<span>' + checkbox + ' ' + category.text + '</span>');
  }

  selectElementCat.select2({
    placeholder: "Select Categories",
    allowClear: true,
    closeOnSelect: false,
    tags: true,
    createTag: function (params) {
      return {
        id: params.term,
        text: params.term,
        newTag: true
      };
    },
    templateResult: formatCategory,
    templateSelection: function (selected) {
      return selected.text;
    },
    dropdownParent: selectElementCat.parent(),
  });

  // Handle checkbox click (scoped to categories)
  $(document).on("click", ".select2-checkbox-cat", function (e) {
    e.stopPropagation();
    e.preventDefault();

    let value = $(this).data("value");
    let option = selectElementCat.find("option[value='" + value + "']");
    let isChecked = $(this).is(":checked");

    // Toggle the checked state
    $(this).prop("checked", !isChecked);
    option.prop("selected", !isChecked);

    selectElementCat.trigger("change");
    return false;
  });

  // Update checkboxes when selection changes (scoped to categories)
  selectElementCat.on("change", function () {
    $(".select2-checkbox-cat").each(function () {
      let value = $(this).data("value");
      let option = selectElementCat.find("option[value='" + value + "']");
      $(this).prop("checked", option.prop("selected"));
    });
  });

  // Add new category input field at the top
  selectElementCat.on("select2:open", function () {
    if ($(".new-category-container").length === 0) {
      let newCategoryHTML = `
        <div class="new-category-container inner-input-container">
          <input type="text" id="new_category_name" placeholder="Type new category">
          <div class="add-new-cat-wrapper add-new">
            <button type="button" id="save_category_btn">✔</button>
            <button type="button" id="cancel_category_btn">✖</button>
          </div>
        </div>
      `;
      $(".select2-dropdown").prepend(newCategoryHTML);
    }
  });

  // Save new category (Frontend only)
  $(document).on("click", "#save_category_btn", function () {
    let newCategory = $("#new_category_name").val().trim();
    if (newCategory === "") {
      alert("Please enter a category name.");
      return;
    }

    // Check if category already exists
    if (selectElementCat.find("option").filter(function () {
      return $(this).text().trim() === newCategory;
    }).length > 0) {
      alert("Category already exists!");
      return;
    }

    // Add new category as an option (Frontend only)
    let newOption = new Option(newCategory, newCategory, true, true);
    selectElementCat.append(newOption).trigger("change");

    // Hide input field and reset
    $("#new_category_name").val("");
  });

  // Cancel category addition
  $(document).on("click", "#cancel_category_btn", function () {
    $("#new_category_name").val("");
  });
  //========================================= selectElementtag
  let selectElementtag = $("#product_tags");

  function formatTag(tag) {
    if (!tag.id) {
      return tag.text;
    }

    let option = selectElementtag.find("option[value='" + tag.id + "']");
    let isSelected = option.prop("selected");
    let checkbox = '<input type="checkbox" class="select2-checkbox-tag" data-value="' + tag.id + '" ' + (isSelected ? 'checked' : '') + ' />';

    return $('<span>' + checkbox + ' ' + tag.text + '</span>');
  }

  selectElementtag.select2({
    placeholder: "Select or Add Tags",
    allowClear: true,
    closeOnSelect: false,
    tags: true,
    createTag: function (params) {
      return {
        id: params.term,
        text: params.term,
        newTag: true
      };
    },
    templateResult: formatTag,
    templateSelection: function (selected) {
      return selected.text;
    },
    dropdownParent: selectElementtag.parent(),
  });

  // Handle checkbox click (scoped to tags)
  $(document).on("click", ".select2-checkbox-tag", function (e) {
    e.stopPropagation();
    e.preventDefault();

    let value = $(this).data("value");
    let option = selectElementtag.find("option[value='" + value + "']");
    let isChecked = $(this).is(":checked");

    // Toggle the checked state
    $(this).prop("checked", !isChecked);
    option.prop("selected", !isChecked);

    selectElementtag.trigger("change");
    return false;
  });

  // Update checkboxes when selection changes (scoped to tags)
  selectElementtag.on("change", function () {
    $(".select2-checkbox-tag").each(function () {
      let value = $(this).data("value");
      let option = selectElementtag.find("option[value='" + value + "']");
      $(this).prop("checked", option.prop("selected"));
    });
  });

  // Add new tag input field at the top
  selectElementtag.on("select2:open", function () {
    if ($(".new-tag-container").length === 0) {
      let newTagHTML = `
                    <div class="new-tag-container inner-input-container">
                        <input type="text" id="new_tag_name" placeholder="Type new tag">
                        <div class="add-new-tag-wrapper add-new">
                            <button type="button" id="save_tag_btn">✔</button>
                            <button type="button" id="cancel_tag_btn">✖</button>
                        </div>
                    </div>
                `;
      $(".select2-dropdown").prepend(newTagHTML);
    }
  });

  // Save new tag (Frontend only)
  $(document).on("click", "#save_tag_btn", function () {
    let newTag = $("#new_tag_name").val().trim();
    if (newTag === "") {
      alert("Please enter a tag name.");
      return;
    }

    // Check if tag already exists
    if (selectElementtag.find("option").filter(function () {
      return $(this).text().trim() === newTag;
    }).length > 0) {
      alert("Tag already exists!");
      return;
    }

    // Add new tag as an option (Frontend only)
    let newOption = new Option(newTag, newTag, true, true);
    selectElementtag.append(newOption).trigger("change");

    // Hide input field and reset
    $("#new_tag_name").val("");
  });

  // Cancel tag addition
  $(document).on("click", "#cancel_tag_btn", function () {
    $("#new_tag_name").val("");
  });

  //========================================= selectElementIcon
  let selectElementIcon = $("#icons-dropdown");

  function formatIcon(icon) {
    if (!icon.id) {
      return icon.text;
    }

    let option = selectElementIcon.find("option[value='" + icon.id + "']");
    let isSelected = option.prop("selected");
    let checkbox = '<input type="checkbox" class="select2-checkbox-icons" data-value="' + icon.id + '" ' + (isSelected ? 'checked' : '') + ' />';

    return $('<span>' + checkbox + ' ' + icon.text + '</span>');
  }

  selectElementIcon.select2({
    placeholder: "Select Icons",
    allowClear: true,
    closeOnSelect: false,
    dropdownAutoWidth: true,
    templateResult: formatIcon,
    templateSelection: function (selected) {
      return selected.text;
    },
    dropdownParent: selectElementIcon.parent(),
  });

  // Handle checkbox click (scoped to icons)
  $(document).on("click", ".select2-checkbox-icons", function (e) {
    e.stopPropagation();
    e.preventDefault();

    let value = $(this).data("value");
    let option = selectElementIcon.find("option[value='" + value + "']");
    let isChecked = $(this).is(":checked");

    // Toggle the checked state
    $(this).prop("checked", !isChecked);
    option.prop("selected", !isChecked);

    selectElementIcon.trigger("change");
    return false;
  });

  // Update checkboxes when selection changes (scoped to icons)
  selectElementIcon.on("change", function () {
    $(".select2-checkbox-icons").each(function () {
      let value = $(this).data("value");
      let option = selectElementIcon.find("option[value='" + value + "']");
      $(this).prop("checked", option.prop("selected"));
    });
  });

  // Modify Select2 Dropdown to Include Input Field at the Top
  selectElementIcon.on("select2:open", function () {
    if ($(".new-icon-container").length === 0) {
      let newIconHTML = `
                        <div class="new-icon-container inner-input-container">
                            <input type="text" id="new_icon_name" placeholder="Type new icon">
                            <div class="add-new-icon-wrapper add-new">
                                <button type="button" id="save_icon_btn">✔</button>
                                <button type="button" id="cancel_icon_btn">✖</button>
                            </div>
                        </div>
                    `;
      $(".select2-dropdown").prepend(newIconHTML);
    }
  });

  // Save new icon (Frontend only)
  $(document).on("click", "#save_icon_btn", function () {
    let newIcon = $("#new_icon_name").val().trim();
    if (newIcon === "") {
      alert("Please enter an icon name.");
      return;
    }

    // Check if icon already exists
    if (selectElementIcon.find("option").filter(function () {
      return $(this).text().trim() === newIcon;
    }).length > 0) {
      alert("Icon already exists!");
      return;
    }

    // Add new icon as an option (Frontend only)
    let newOption = new Option(newIcon, newIcon, true, true);
    selectElementIcon.append(newOption).trigger("change");

    // Hide input field and reset
    $("#new_icon_name").val("");
  });

  // Cancel icon addition
  $(document).on("click", "#cancel_icon_btn", function () {
    $("#new_icon_name").val("");
  });


  //========================================= selectElementFeaturedPlacement (same style as Categories/Tags)
  let selectElementFeatured = $("#featured_placements");

  function formatFeaturedPlacement(option) {
    if (!option.id) {
      return option.text;
    }

    let $opt = selectElementFeatured.find("option[value='" + option.id + "']");
    let isSelected = $opt.prop("selected");
    let checkbox = '<input type="checkbox" class="select2-checkbox-featured" data-value="' + option.id + '" ' + (isSelected ? 'checked' : '') + ' />';

    return $('<span>' + checkbox + ' ' + option.text + '</span>');
  }

  selectElementFeatured.select2({
    placeholder: "Select Placement",
    allowClear: true,
    closeOnSelect: false,
    dropdownAutoWidth: true,
    templateResult: formatFeaturedPlacement,
    templateSelection: function (selected) {
      return selected.text;
    },
    dropdownParent: selectElementFeatured.parent(),
  });

  // Handle checkbox click (scoped to featured placements)
  $(document).on("click", ".select2-checkbox-featured", function (e) {
    e.stopPropagation();
    e.preventDefault();

    let value = $(this).data("value");
    let option = selectElementFeatured.find("option[value='" + value + "']");
    let isChecked = $(this).is(":checked");

    $(this).prop("checked", !isChecked);
    option.prop("selected", !isChecked);

    selectElementFeatured.trigger("change");
    return false;
  });

  // Update checkboxes when selection changes (scoped to featured placements)
  selectElementFeatured.on("change", function () {
    $(".select2-checkbox-featured").each(function () {
      let value = $(this).data("value");
      let option = selectElementFeatured.find("option[value='" + value + "']");
      $(this).prop("checked", option.prop("selected"));
    });
  });


  //========================================= selectElementEligibleRetailer
  let selectElementEligibleRetailer = $("#eligible_retailers");

  function formatRetailer(retailer) {
    if (!retailer.id) {
      return retailer.text;
    }

    let option = selectElementEligibleRetailer.find("option[value='" + retailer.id + "']");
    let isSelected = option.prop("selected");
    let checkbox = '<input type="checkbox" class="select2-checkbox-retailer" data-value="' + retailer.id + '" ' + (isSelected ? 'checked' : '') + ' /> ';

    return $('<span>' + checkbox + retailer.text + '</span>');
  }

  selectElementEligibleRetailer.select2({
    placeholder: "Select Eligible Retailers",
    allowClear: true,
    closeOnSelect: false,
    dropdownAutoWidth: true,
    templateResult: formatRetailer,
    templateSelection: function (selected) {
      return selected.text;
    },
    dropdownParent: selectElementEligibleRetailer.parent(),
  });

  // Handle checkbox click (scoped to retailers)
  $(document).on("click", ".select2-checkbox-retailer", function (e) {
    e.stopPropagation();
    e.preventDefault();

    let value = $(this).data("value");
    let option = selectElementEligibleRetailer.find("option[value='" + value + "']");
    let isChecked = $(this).is(":checked");

    // Toggle the checked state
    $(this).prop("checked", !isChecked);
    option.prop("selected", !isChecked);

    selectElementEligibleRetailer.trigger("change");
    return false;
  });

  // Update checkboxes when selection changes (scoped to retailers)
  selectElementEligibleRetailer.on("change", function () {
    $(".select2-checkbox-retailer").each(function () {
      let value = $(this).data("value");
      let option = selectElementEligibleRetailer.find("option[value='" + value + "']");
      $(this).prop("checked", option.prop("selected"));
    });
  });

  // Add new retailer input field at the top
  selectElementEligibleRetailer.on("select2:open", function () {
    if ($(".new-retailer-container").length === 0) {
      let newRetailerHTML = `
                    <div class="new-retailer-container inner-input-container">
                        <input type="text" id="new_retailer_name" placeholder="Type new retailer">
                        <div class="add-new-retailer-wrapper add-new">
                            <button type="button" id="save_retailer_btn">✔</button>
                            <button type="button" id="cancel_retailer_btn">✖</button>
                        </div>
                    </div>
                `;
      $(".select2-dropdown").prepend(newRetailerHTML);
    }
  });

  // Save new retailer (Frontend only)
  $(document).on("click", "#save_retailer_btn", function () {
    let newRetailer = $("#new_retailer_name").val().trim();
    if (newRetailer === "") {
      alert("Please enter a retailer name.");
      return;
    }

    // Check if retailer already exists
    if (selectElementEligibleRetailer.find("option").filter(function () {
      return $(this).text().trim().toLowerCase() === newRetailer.toLowerCase();
    }).length > 0) {
      alert("Retailer already exists!");
      return;
    }

    // Add new retailer as an option (Frontend only)
    let newOption = new Option(newRetailer, newRetailer, true, true);
    selectElementEligibleRetailer.append(newOption).trigger("change");

    // Hide input field and reset
    $("#new_retailer_name").val("");
  });

  // Cancel retailer addition
  $(document).on("click", "#cancel_retailer_btn", function () {
    $("#new_retailer_name").val("");
  });


  //END Here =====================================
  setTimeout(function () {
    let successMEssage = document.getElementById("success-message");
    if (successMEssage) {
      successMEssage.style.display = "none";
    }
  }, 5000);
  document.getElementById("gift-card-form").reset();
});
// document.getElementById("brand_logo").addEventListener("change", function (event) {
//   let previewDiv = document.getElementById("brand_logo_preview");
//   previewDiv.innerHTML = ""; // Clear previous preview

//   let file = event.target.files[0];

//   if (file) {
//     let reader = new FileReader();
//     reader.onload = function (e) {
//       let img = document.createElement("img");
//       img.src = e.target.result;
//       img.style.maxWidth = "200px"; // Adjust size as needed
//       img.style.maxHeight = "200px";
//       img.style.marginTop = "10px";
//       previewDiv.appendChild(img);
//     };
//     reader.readAsDataURL(file);
//   }
// });       
// Set the minimum date if 'set_date' is selected

document.addEventListener("DOMContentLoaded", function () {
  var expiryType = document.getElementById("gift_card_expiry_type");
  var expiryDateField = document.querySelector(".expiry-date-field");
  var expiryDateInput = document.getElementById("gift_card_expiry_date");
  var expiryDurationField = document.querySelector(".expiry-duration-field");
  function formatDate(dateStr) {
    const [year, month, day] = dateStr.split("-");
    return `${day}/${month}/${year}`;
  }

  function setExpiryDateLimits() {
    const expiryDateInput = document.getElementById("gift_card_expiry_date");
    const errorMessageDiv = document.getElementById("expiry-date-error");

    const today = new Date();
    const todayStr = today.toISOString().split("T")[0];

    // Set max date to 2 years from today
    const maxDate = new Date();
    maxDate.setFullYear(maxDate.getFullYear() + 50);
    const maxDateStr = maxDate.toISOString().split("T")[0];

    expiryDateInput.setAttribute("min", todayStr);
    expiryDateInput.setAttribute("max", maxDateStr);

    expiryDateInput.addEventListener("blur", function () {
      const inputDate = expiryDateInput.value;

      if (inputDate && (inputDate < todayStr || inputDate > maxDateStr)) {
        errorMessageDiv.textContent = `Date must be between ${formatDate(todayStr)} and ${formatDate(maxDateStr)}.`;
        errorMessageDiv.style.display = "block";
        expiryDateInput.value = "";
      } else {
        if (errorMessageDiv) {
          errorMessageDiv.style.display = "none";
        }
      }
    });

    expiryDateInput.addEventListener("focus", function () {
      if (errorMessageDiv) {
        errorMessageDiv.style.display = "none";
      }
    });

    function formatDate(dateString) {
      const [year, month, day] = dateString.split("-");
      return `${day}/${month}/${year}`;
    }
  }

  setExpiryDateLimits();
  // Your full fields array (for later reference)
  const fields = [
    { id: 'discounted_price', min: 0, max: 100000 },
    { id: 'variable_range_from', min: 0, max: 100000 },
    { id: 'variable_range_to', min: 0, max: 100000 },
    { id: '_sell_price_fixed', min: 0, max: 100000 },
    { id: 'cost_price', min: 0, max: 100000 },
    { id: '_supplier_fullfillment_price', min: 0, max: 100000 },
    { id: '_gst', min: 0, max: 100000 },
    { id: 'j_a_c_fulfillment_cost', min: 0, max: 100000 },
    { id: 'delivery_cost', min: 0, max: 100000 },
    { id: '_total_value_per_transaction', min: 0, max: 9999 }
  ];

  // Helper to get field rule from ID
  function getFieldRule(id) {
    return fields.find(f => f.id === id);
  }

  // Validation logic
  function validateField(id) {
    const input = document.getElementById(id);
    const errorDiv = document.getElementById(id + '_error');
    const rule = getFieldRule(id);

    if (!input || !errorDiv || !rule) return;

    const value = input.value.trim();
    const number = parseFloat(value);
    if (value === '') {
      errorDiv.style.display = 'none';
      return;
    }

    if (isNaN(number) || number < rule.min || number > rule.max) {
      errorDiv.textContent = `Value must be between ${rule.min} and ${rule.max}.`;
      errorDiv.style.display = 'block';
    } else {
      errorDiv.style.display = 'none';
    }
  }

  // Custom keyup trigger for the 3 fields
  ['_sell_price_fixed', 'cost_price', '_supplier_fullfillment_price', '_total_value_per_transaction'].forEach(id => {

    const input = document.getElementById(id);
    if (input) {
      input.addEventListener('keyup', () => {
        validateField('discounted_price');
        validateField('_discount_margin_input');
        validateField('_margin');
        validateField('_total_value_per_transaction');
      });
    }
  });

  // Keyup validation for all fields
  fields.forEach(field => {
    const input = document.getElementById(field.id);
    const errorDiv = document.getElementById(field.id + '_error');

    if (input) {
      input.addEventListener('keyup', function () {
        validateField(field.id);
      });
    }
  });

  // Enforce minimum 0 on number fields: prevent values less than 0
  function enforceMinOnNumberInput(input) {
    const min = parseFloat(input.getAttribute('min'));
    if (isNaN(min)) return;
    const value = parseFloat(input.value);
    if (input.value !== '' && !isNaN(value) && value < min) {
      input.value = min;
      const id = input.id;
      if (getFieldRule(id)) validateField(id);
    }
  }

  const numberInputIds = fields.map(f => f.id).concat(['_add_stock_level', '_quantity_per_transaction']);
  numberInputIds.forEach(id => {
    const input = document.getElementById(id);
    if (input && input.type === 'number') {
      input.addEventListener('input', function () { enforceMinOnNumberInput(this); });
      input.addEventListener('change', function () { enforceMinOnNumberInput(this); });
    }
  });
  
  // Run validation on page load (optional)
  window.addEventListener('load', () => {
    fields.forEach(field => validateField(field.id));
  });

  // const expiryType = document.getElementById('gift_card_expiry_type');
  const expiryDateWrapper = document.querySelector('.gift-expiry-date-field');
  const expiryDurationWrapper = document.querySelector('.gift-expiry-duration-field');
  // const expiryDateInput = document.getElementById('gift_card_expiry_date');
  const expiryDurationInput = document.getElementById('gift_card_expiry_duration');
  const expiryUnitInput = document.getElementById('gift_card_expiry_unit');

  function toggleGiftCardExpiryFields() {
    const selectedValue = expiryType.value;
    // Hide and reset
    if (expiryDateWrapper) expiryDateWrapper.style.display = 'none';
    if (expiryDurationWrapper) expiryDurationWrapper.style.display = 'none';

    if (expiryDateInput) expiryDateInput.removeAttribute('required');
    if (expiryDurationInput) expiryDurationInput.removeAttribute('required');
    if (expiryUnitInput) expiryUnitInput.removeAttribute('required');

    // Remove old error if it exists
    const existingError = document.getElementById('expiry-unit-error');
    if (existingError) existingError.remove();

    // === 'gift_set_date'
    if (selectedValue === 'gift_set_date') {
      if (expiryDateWrapper) expiryDateWrapper.style.display = 'block';
      if (expiryDateInput) expiryDateInput.setAttribute('required', 'required');
      if (typeof setMinExpiryDate === 'function') setMinExpiryDate();
    }

    // === 'expiry_period_starts_on_purchase' or 'expiry_period_starts_on_activation'
    if (selectedValue === 'expiry_period_starts_on_purchase' || selectedValue === 'expiry_period_starts_on_activation') {
      if (expiryDurationWrapper) expiryDurationWrapper.style.display = 'block';
      if (expiryDurationInput) expiryDurationInput.setAttribute('required', 'required');
      if (expiryUnitInput) expiryUnitInput.setAttribute('required', 'required');

      // Inject validation error if unit is not selected
      if (!expiryUnitInput.value) {
        const errorDiv = document.createElement('div');
        errorDiv.id = 'expiry-unit-error';
        errorDiv.className = 'validation-error';
        errorDiv.style.color = 'red';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '4px';
        errorDiv.textContent = 'Please select an expiry unit';

        const groupWrapper = expiryUnitInput.closest('.expiry-input-group');
        if (groupWrapper) {
          groupWrapper.insertAdjacentElement('afterend', errorDiv);
        }
      }
    }
  }

  if (expiryType) {
    expiryType.addEventListener('change', toggleGiftCardExpiryFields);
    toggleGiftCardExpiryFields(); // Initial load
  }

  var activationExpiryType = document.getElementById("activation_expiry_type");
  var activationExpiryDateField = document.getElementById("activation_expiry_date_field");
  var activationExpiryPeriodField = document.getElementById("activation_expiry_period_field");
  var activationExpiryDateInput = document.getElementById("activation_expiry_date");
  var activationExpiryDurationInput = document.getElementById("activation_expiry_duration");
  var activationExpiryUnitInput = document.getElementById("activation_expiry_unit");

  function toggleActivationExpiryFields() {
    var selectedValue = activationExpiryType.value;

    // Hide all by default
    activationExpiryDateField.style.display = 'none';
    activationExpiryPeriodField.style.display = 'none';

    // Remove required attributes by default
    if (activationExpiryDateInput) activationExpiryDateInput.removeAttribute('required');
    if (activationExpiryDurationInput) activationExpiryDurationInput.removeAttribute('required');
    if (activationExpiryUnitInput) activationExpiryUnitInput.removeAttribute('required');

    if (selectedValue === "activation_set_date") {
      activationExpiryDateField.style.display = "block";
      if (activationExpiryDateInput) activationExpiryDateInput.setAttribute("required", "required");
    }

    if (selectedValue === "set_period") {
      activationExpiryPeriodField.style.display = "block";
      if (activationExpiryDurationInput) activationExpiryDurationInput.setAttribute("required", "required");
      if (activationExpiryUnitInput) activationExpiryUnitInput.setAttribute("required", "required");
    }
  }

  function setMinDate(input) {
    var today = new Date().toISOString().split("T")[0];
    input.setAttribute("min", today);
  }

  if (activationExpiryType) {
    activationExpiryType.addEventListener("change", toggleActivationExpiryFields);
    toggleActivationExpiryFields(); // Initialize on load
  }

  if (activationExpiryDateInput) {
    setMinDate(activationExpiryDateInput);
  }

  // function toggleGiftCardExpiryFields() {
  //   var selectedValue = expiryType.value;

  //   // Show/hide expiry date field
  //   if (jQuery('.expiry-date-field').length) {
  //     expiryDateField.style.display = selectedValue === "set_date" ? "block" : "none";
  //   }

  //   // Show/hide expiry duration field
  //   if (jQuery('.expiry-duration-field').length) {
  //     expiryDurationField.style.display = (selectedValue === "purchase" || selectedValue === "activation") ? "block" : "none";
  //   }
  //   // Set the minimum date if 'set_date' is selected
  //   if (selectedValue === "set_date") {
  //     setMinExpiryDate();
  //   }
  // }

  // if (expiryType) {
  //   expiryType.addEventListener("change", toggleGiftCardExpiryFields);
  //   toggleGiftCardExpiryFields(); // Run on page load to set initial state
  // }



  // For Delivery cost auto populate new code
  var presetCheckbox = document.getElementById("presetDeliveryClass");
  var presetDropdown = document.getElementById("presetClasses");
  var deliveryCostField = document.getElementById("delivery_cost");

  if (!presetCheckbox || !presetDropdown || !deliveryCostField) return;

  // ✅ Toggle function
  function togglePresetDelivery() {
    if (presetCheckbox.checked) {
      presetDropdown.style.display = "block";
      deliveryCostField.readOnly = true;
      deliveryCostField.value = "";
      if (presetDropdown.value) {
        setTimeout(() => {
          updateDeliveryCost();
        }, 1000);
      }
    } else {
      presetDropdown.style.display = "none";
      deliveryCostField.readOnly = false;
      deliveryCostField.value = ""; // Reset when unchecked
    }
  }

  // ✅ Update delivery cost when a preset class is selected
  function updateDeliveryCost() {
    var selectedOptionText =
      presetDropdown.options[presetDropdown.selectedIndex].text || "";
    var costMatch = selectedOptionText.match(/\$\d+(\.\d{1,2})?/);
    var cost = costMatch ? costMatch[0].replace("$", "") : "";
    deliveryCostField.value = cost;
  }

  // ✅ Bind events
  presetCheckbox.addEventListener("change", togglePresetDelivery);
  presetDropdown.addEventListener("change", updateDeliveryCost);

  // ✅ Run once on page load
  togglePresetDelivery();
  // var presetCheckbox = document.getElementById("presetDeliveryClass");
  // var presetDropdown = document.getElementById("presetClasses");
  // var deliveryCostField = document.getElementById("delivery_cost");

  // // Show/hide the dropdown based on checkbox state and update delivery cost
  // presetCheckbox.addEventListener("change", function () {
  //   setTimeout(() => {
  //     if (this.checked) {
  //       presetDropdown.style.display = "block";
  //       deliveryCostField.readOnly = true;
  //       deliveryCostField.value = "";
  //       // If a preset class is already selected, set its value in delivery cost
  //       if (presetDropdown.value) {
  //         updateDeliveryCost();
  //       }
  //     } else {
  //       presetDropdown.style.display = "none";
  //       deliveryCostField.readOnly = false; // Enable input
  //       deliveryCostField.value = ""; // Reset field when unchecked
  //     }
  //   }, 200);
  // });

  // // Update delivery cost when a class is selected
  // presetDropdown.addEventListener("change", updateDeliveryCost);

  // function updateDeliveryCost() {
  //   var selectedOptionText = presetDropdown.options[presetDropdown.selectedIndex].text;

  //   // Extract the amount using a regex (looking for $ followed by a number)
  //   var costMatch = selectedOptionText.match(/\$\d+(\.\d{1,2})?/);
  //   var cost = costMatch ? costMatch[0].replace("$", "") : "";

  //   deliveryCostField.value = cost; // Set the cost
  // }


});

window.onload = function () {
  let selectedImage = null;

  const dropZone = document.getElementById('dropZone');
  const fileInput = document.getElementById('brand_logo');
  const preview = document.getElementById('brand_logo_preview');
  const hiddenInput = document.getElementById('brand_thumbnail_url');
  const urlInput = document.getElementById('imageUrl');
  const brandDropdown = document.getElementById('product_brand-dropdown');
  const errorBox = document.getElementById('image-error');

  // Handle image URL input
  window.handleUrl = function () {
    errorBox.textContent = '';
    const url = urlInput.value.trim();
    if (!url) return;

    const img = new Image();
    // const img = new Image();
    img.onload = () => {
      if (img.width <= 600 && img.height <= 379) {
        selectedImage = { type: 'url', src: url };
        updatePreview();
        updateHiddenInput();
        urlInput.value = '';
      } else {
        errorBox.textContent = 'Image must be maximum 600x379 pixels.';
      }
    };
    img.onerror = () => {
      console.error('Image load failed', url);
      errorBox.textContent = 'Failed to load image from URL.';
    };
    img.src = url;
  };

  function handleFile(file) {
    errorBox.textContent = '';
    if (!file.type.startsWith('image/')) {
      errorBox.textContent = 'Please upload a valid image file.';
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        if (img.width <= 600 && img.height <= 379) {
          selectedImage = { type: 'upload', src: e.target.result };
          updatePreview();
          updateHiddenInput();
        } else {
          errorBox.textContent = 'Image must be maximum 600x379 pixels.';
        }
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  function updatePreview() {
    preview.innerHTML = '';
    if (selectedImage) {
      const container = document.createElement('div');
      container.className = 'preview-item';

      const img = document.createElement('img');
      img.src = selectedImage.src;
      container.appendChild(img);

      const deleteBtn = document.createElement('button');
      deleteBtn.className = 'delete-btn';
      deleteBtn.type = 'button';
      deleteBtn.innerHTML = '&times;';
      deleteBtn.onclick = () => removeImage();

      container.appendChild(deleteBtn);
      preview.appendChild(container);
    }
  }

  function removeImage() {
    selectedImage = null;
    preview.innerHTML = '';
    hiddenInput.value = '';
    fileInput.value = '';
    // brandDropdown.selectedIndex = 0;
  }

  function updateHiddenInput() {
    hiddenInput.value = selectedImage ? selectedImage.src : '';
  }

  // File input handling
  fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) handleFile(file);
  });

  // Drag and drop events
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
    });
  });

  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.classList.add('highlight'));
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.classList.remove('highlight'));
  });

  dropZone.addEventListener('drop', (e) => {
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
  });

  // Auto-load brand logo if in edit mode with selected brand
  if (brandDropdown && brandDropdown.value) {
    const selectedOption = brandDropdown.options[brandDropdown.selectedIndex];
    const thumbnailUrl = selectedOption.dataset.thumbnail;

    if (thumbnailUrl) {
      // Set the image in the preview
      const preview = document.getElementById('brand_logo_preview');
      preview.innerHTML = `
         <div class="preview-item">
           <img src="${thumbnailUrl}" alt="Brand Logo">
           <button class="delete-btn">&times;</button>
         </div>
       `;

      // Set the hidden input value
      document.getElementById('brand_thumbnail_url').value = thumbnailUrl;
    }
  }

  // Brand dropdown default image loading
  // Update the brand dropdown event listener
  brandDropdown.addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    const thumbnailUrl = selectedOption.dataset.thumbnail;
    const preview = document.getElementById('brand_logo_preview');
    const hiddenInput = document.getElementById('brand_thumbnail_url');

    if (thumbnailUrl) {
      // Update preview with new brand logo
      preview.innerHTML = `
      <div class="preview-item">
        <img src="${thumbnailUrl}" alt="Brand Logo">
        <button class="delete-btn">&times;</button>
      </div>
    `;
      hiddenInput.value = thumbnailUrl;
    } else {
      // Clear if no thumbnail
      preview.innerHTML = '';
      hiddenInput.value = '';
    }
  });
  // Load initial image
  const initialValue = hiddenInput.value;
  if (initialValue) {
    selectedImage = { type: 'url', src: initialValue };
    updatePreview();
  }

};
function validateBrandLogo() {
  const previewContainer = document.getElementById('brand_logo_preview');
  const imageError = document.getElementById('image-error');

  // Check if there's at least one <img> inside the preview container
  const imagePresent = previewContainer.querySelector('img') !== null;

  if (!imagePresent) {
    imageError.textContent = 'Please upload or select a brand logo.';
    imageError.style.display = 'block';
    imageError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return false;
  }

  imageError.textContent = '';
  imageError.style.display = 'none';
  return true;
}


const fields = [
  { id: 'discounted_price', min: 0, max: 100000 },
  { id: 'variable_range_from', min: 0, max: 100000 },
  { id: 'variable_range_to', min: 0, max: 100000 },
  { id: '_sell_price_fixed', min: 0, max: 100000 },
  { id: 'cost_price', min: 0, max: 100000 },
  { id: '_supplier_fullfillment_price', min: 0, max: 100000 },
  { id: '_gst', min: 0, max: 100000 },
  { id: 'j_a_c_fulfillment_cost', min: 0, max: 100000 },
  { id: 'delivery_cost', min: 0, max: 100000 },
  { id: '_total_value_per_transaction', min: 0, max: 9999 }
];

// Helper to get field rule from ID
function getFieldRule(id) {
  return fields.find(f => f.id === id);
}

// This function is already defined above – use it globally, not inside DOMContentLoaded
function validateField(id) {
  const input = document.getElementById(id);
  const errorDiv = document.getElementById(id + '_error');
  const rule = getFieldRule(id);

  if (!input || !errorDiv || !rule) return true;

  const value = input.value.trim();
  const number = parseFloat(value);

  if (value === '') {
    errorDiv.style.display = 'none';
    return true;
  }

  if (isNaN(number) || number < rule.min || number > rule.max) {
    errorDiv.textContent = `Value must be between ${rule.min} and ${rule.max}.`;
    errorDiv.style.display = 'block';
    return false;
  } else {
    errorDiv.style.display = 'none';
    return true;
  }
}



function validateAllFields() {
  let allValid = true;
  let firstInvalidElement = null;

  fields.forEach(field => {
    const isValid = validateField(field.id);
    if (!isValid) {
      if (!firstInvalidElement) {
        firstInvalidElement = document.getElementById(field.id + '_error');
      }
      allValid = false;
    }
  });

  // Scroll to the first error if any
  if (firstInvalidElement) {
    firstInvalidElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  return {
    valid: allValid,
    firstInvalidElement: firstInvalidElement
  };
}



function validateGiftCardImage() {
  const previewContainer = document.getElementById('preview-container'); // your preview div
  const imageError = document.getElementById('required-image-error'); // your error span/div

  const imagePresent = previewContainer.children.length > 0;

  if (!imagePresent) {
    imageError.textContent = 'Please upload or select a gift card image.';
    imageError.style.display = 'block';
    imageError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return false;
  }

  imageError.textContent = '';
  imageError.style.display = 'none';
  return true;
}
function validateAll() {
  // run both validators unconditionally
  const giftValid = validateGiftCardImage();
  const brandValid = validateBrandLogo();
  const fieldValid = validateAllFields(); // validate each field

  return giftValid && brandValid && fieldValid.valid;
}
document.addEventListener("DOMContentLoaded", function () {
  const skuInput = document.getElementById("sku");
  const skuError = document.getElementById("sku_validation_error");

  const supplierSkuInput = document.getElementById("supplier_sku");
  const supplierSkuError = document.getElementById("supplier_sku_error");

  const letterOnlyRegex = /^[A-Za-z0-9_.-]*$/;

  skuInput.addEventListener("keyup", function () {
    const value = this.value;
    if (!letterOnlyRegex.test(value)) {
      skuError.style.display = "block";
    } else {
      skuError.style.display = "none";
    }
  });

  supplierSkuInput.addEventListener("keyup", function () {
    const value = this.value;
    if (!letterOnlyRegex.test(value)) {
      supplierSkuError.style.display = "block";
    } else {
      supplierSkuError.style.display = "none";
    }
  });
});

