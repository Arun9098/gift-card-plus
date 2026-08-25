<?php
/**
 * Normalize “Activation Expiry Date” values for email output.
 *
 * Supports common formats we store/receive:
 * - "25/01/2026 12:00 am" (d/m/Y g:i a)
 * - "2026-01-25T00:00" (datetime-local)
 * - "2026-01-25 00:00:00"
 * - unix timestamps (seconds)
 *
 * Returns an empty string if the date cannot be parsed.
 */
function gc_format_activation_expiry_date_for_email($raw_date, $output_format = 'd/m/Y g:i a') {
	if ($raw_date === null) {
		return '';
	}

	if (is_array($raw_date)) {
		// Some ACF date fields can return arrays; try a common key.
		$raw_date = $raw_date['date'] ?? $raw_date['value'] ?? '';
	}

	$raw_date = is_string($raw_date) ? trim($raw_date) : $raw_date;
	if ($raw_date === '' || $raw_date === false) {
		return '';
	}

	try {
		$dt = null;

		// Numeric timestamp (seconds)
		if (is_numeric($raw_date)) {
			$dt = new DateTime('@' . intval($raw_date));
			$dt->setTimezone(wp_timezone());
		} elseif (is_string($raw_date)) {
			// Normalize datetime-local format
			$normalized = str_replace('T', ' ', $raw_date);

			// Try exact formats first
			$formats = [
				'd/m/Y g:i a',
				'd/m/Y g:i A',
				'd/m/Y H:i',
				'Y-m-d H:i:s',
				'Y-m-d H:i',
				'Y-m-d',
			];

			foreach ($formats as $fmt) {
				$tmp = DateTime::createFromFormat($fmt, $normalized, wp_timezone());
				if ($tmp instanceof DateTime) {
					$errors = DateTime::getLastErrors();
					if (empty($errors['warning_count']) && empty($errors['error_count'])) {
						$dt = $tmp;
						break;
					}
				}
			}

			// Last resort: let PHP try to parse it
			if (!$dt) {
				$dt = new DateTime($normalized, wp_timezone());
			}
		}

		return ($dt instanceof DateTime) ? $dt->format($output_format) : '';
	} catch (Exception $e) {
		return '';
	}
}

function gc_tester(){
	$recipient_gift_cards 	= [];
	$emails_by_date 		= [];
	$current_timestamp 		= current_time('timestamp');
	
	if( isset($_GET['gc_tester']) ){
		// Commented on 20251217
		// $order_id = 6741; // Your test Order ID
		$order = wc_get_order($order_id);

		if (!$order) {
			// Commented on 20251217
			// pr('Error: Order not found.');
			exit;
		}

		// --- (Data Preparation Code - Keep everything here the same) ---
		$invoice_number = $order->get_meta('_invoice_number');
		$sender_name 	= $order->get_meta('_sender_name');
		$sender_email 	= $order->get_meta('_sender_email');
		$get_subtotal 	= wc_price($order->get_subtotal());
		$get_total 		= wc_price($order->get_total());
		
		$customer_id 	= $order->get_customer_id();
		$business_user 	= get_user_by('ID', $customer_id);

		$business_user_name 	= get_user_meta($business_user->ID, 'business_name', true);
		$business_user_email 	= $business_user->user_email;

		// Commented on 20251217
		// pr( '$invoice_number' );
		// pr( $invoice_number );
		// pr( '$get_subtotal' );
		// pr( $get_subtotal );
		// --- (End Data Preparation Code) ---

		foreach ($order->get_items() as $item){
			// ... (Your existing logic to extract item meta) ...
			$recipient_name 			= wc_get_order_item_meta($item->get_id(), '_recipient_name');
			$recipient_email 			= wc_get_order_item_meta($item->get_id(), '_recipient_email');
			$delivery_method 			= wc_get_order_item_meta($item->get_id(), '_delivery_method');
			$unique_gift_card_number 	= wc_get_order_item_meta($item->get_id(), '_gift_card_number_enc');
			$gift_message 				= wc_get_order_item_meta($item->get_id(), '_gift_message');
			$gift_card_sku 				= wc_get_order_item_meta($item->get_id(), '_gift_card_sku');
			$gift_card_price 			= wc_get_order_item_meta($item->get_id(), '_gift_card_price');
			$gift_card_post_id 			= wc_get_order_item_meta($item->get_id(), '_gift_card_post_id');
			$scheduled_date 			= wc_get_order_item_meta($item->get_id(), '_scheduled_date');
			$expiry_type_label 			= wc_get_order_item_meta($item->get_id(), '_activation_expiry_type');
			$gift_subject				= wc_get_order_item_meta($item->get_id(), '_gift_subject');
			
			// Retrieve additional fields needed for email
			$gift_card_name 		= wc_get_order_item_meta($item->get_id(), '_gift_card_name');
			$gift_email_animation 	= wc_get_order_item_meta($item->get_id(), 'gift_email_animation');
			$gift_card_image 		= wc_get_order_item_meta($item->get_id(), '_gift_card_image');
			
			$product_id 	= wc_get_product_id_by_sku($gift_card_sku);
			$recipient_key 	= $recipient_email.'-'.str_replace(' ', '', strtolower($recipient_name));

			if (!isset($recipient_gift_cards[$recipient_key])) {
				$recipient_gift_cards[$recipient_key] = [
					'name' 			=> $recipient_name,
					'email'			=> $recipient_email,
					'sender_name' 	=> $sender_name,
					'sender_email' 	=> $sender_email,
					'cards' 		=> [],
				];
			}

			$temp_card_details = array();

			// Get gift card name - prefer order item meta, fallback to item name
			$final_gift_card_name = !empty($gift_card_name) ? $gift_card_name : $item->get_name();
			
			// Get image URL - prefer order item meta, fallback to product thumbnail
			$final_image_url = !empty($gift_card_image) ? $gift_card_image : get_the_post_thumbnail_url($product_id, 'full');
			
			// Activation Expiry Date can be stored in multiple places depending on flow (order item meta, ACF, post meta).
			// Prefer order item meta first (what was purchased), then fallback to the gift card post.
			$raw_activation_expiry_date =
				wc_get_order_item_meta($item->get_id(), '_activation_expiry_date', true);

			if (empty($raw_activation_expiry_date) && !empty($gift_card_post_id)) {
				// ACF field names usually do NOT include a leading underscore; try both just in case.
				$raw_activation_expiry_date =
					get_field('activation_expiry_date', $gift_card_post_id) ?:
					get_field('_activation_expiry_date', $gift_card_post_id) ?:
					get_post_meta($gift_card_post_id, 'activation_expiry_date', true) ?:
					get_post_meta($gift_card_post_id, '_activation_expiry_date', true);
			}

			$temp_card_details = [
				'recipient_email' 		=> $recipient_email,
				'_gift_card_number_enc' 		=> $unique_gift_card_number,
				'gift_card_name' 		=> $final_gift_card_name,
				'price' 				=> $gift_card_price,
				'subject' 				=> $gift_subject,
				'message' 				=> $gift_message,
				'emailAnimation' 		=> $gift_email_animation,
				'image_url' 			=> $final_image_url,
				'gift_card_post_id' 	=> $gift_card_post_id,
				'scheduled_date' 		=> $scheduled_date,
				'expiry_type' 			=> $expiry_type_label,
				'expiry_date' 			=> $raw_activation_expiry_date,
				'expiry_duration' 		=> get_field('_activation_expiry_duration', $gift_card_post_id),
				'expiry_unit' 			=> get_field('_activation_expiry_unit', $gift_card_post_id),
				'name' 					=> $recipient_name,
				'email' 				=> $recipient_email,
				'sender_name' 			=> $sender_name,
				'sender_email' 			=> $sender_email,
				'business_user_name' 	=> $business_user_name,
				'business_user_email' 	=> $business_user_email
			]; 

			$recipient_gift_cards[$recipient_key]['cards'][] = $temp_card_details;

			$gc_email_date_timestamp 	= strtotime($scheduled_date);
			$gc_email_status 			= 'immediate';

			if ($scheduled_date && $gc_email_date_timestamp > $current_timestamp) { // ADD CONDITION
				$gc_email_status = 'schedule';
			} else {
				$gc_email_status = 'immediate';
			}

			$emails_by_date[$gc_email_status][] = $temp_card_details;
		}
		
		// Commented on 20251217
		// pr('--- Data Prepared ---');
		// pr($emails_by_date);

		
		// =======================================================
		//  <<< !!! NEW CODE TO CALL THE EMAIL FUNCTION !!! >>>
		// =======================================================
		// Commented on 20251217
		// pr('--- Attempting to Send Emails ---');
		
		$email_results = [];
		
		if (isset($emails_by_date['immediate']) && !empty($emails_by_date['immediate'])) {
			
			// Loop through each gift card that needs immediate delivery
			foreach ($emails_by_date['immediate'] as $gcard_data) {
				
				// IMPORTANT: Ensure the email function is defined or included before this point.
				// Call the email function with the prepared data.
				// NOTE: $attachments is an empty array here, as it's not generated in gc_tester
				$attachments = []; 
				$sent_status = send_gift_cards_email_to_recipient($gcard_data, $attachments);
				
				// Collect and display the result
				$email_results[] = [
					'recipient' 		=> $gcard_data['recipient_email'],
					'card' 				=> $gcard_data['gift_card_number'],
					'sent' 				=> $sent_status ? 'SUCCESS' : 'FAILURE',
					'post_meta_updated' => get_post_meta($gcard_data['gift_card_post_id'], '_gift_card_send', true)
				];
			}
		}
		
		// Commented on 20251217
		// pr('--- Email Sending Results ---');
		// pr($email_results);
		
		exit;
	}
}
add_action('init', 'gc_tester');

function send_gift_cards_email_to_recipient($gcard,$attachments) {
	$logger  = wc_get_logger();
	$context = ['source' => 'Test-Data'];

	// Normalize: manual/bulk flow may pass gift_card_id only; ensure gift_card_post_id is set for rest of function
	if ( !empty($gcard['gift_card_id']) && empty($gcard['gift_card_post_id']) ) {
		$gcard['gift_card_post_id'] = $gcard['gift_card_id'];
	}


	// Commented on 20251217
	// $logger->info(
	// 	"📦 Send Email - Full gcard data: " . print_r($gcard, true),
	// 	$context
	// );
	
	// Commented on 20251217
	// Debug specific fields
	// $logger->info(
	// 	"🔍 Key fields check - name: " . (isset($gcard['name']) ? $gcard['name'] : 'NOT SET') . 
	// 	", recipient_name: " . (isset($gcard['recipient_name']) ? $gcard['recipient_name'] : 'NOT SET') .
	// 	", gift_card_name: " . (isset($gcard['gift_card_name']) ? $gcard['gift_card_name'] : 'NOT SET') .
	// 	", sender_name: " . (isset($gcard['sender_name']) ? $gcard['sender_name'] : 'NOT SET'),
	// 	$context
	// );

	// =======================================================
	// FALLBACK: Retrieve missing fields from order item meta
	// =======================================================
	// Always try to retrieve if fields are missing or empty
	if (!empty($gcard)) {
		// Log initial state
		// Commented on 20251217
		// $logger->info(
		// 	"🔍 Initial state - emailAnimation: " . (isset($gcard['emailAnimation']) ? var_export($gcard['emailAnimation'], true) : 'NOT SET') . 
		// 	", image_url: " . (isset($gcard['image_url']) ? var_export($gcard['image_url'], true) : 'NOT SET') .
		// 	", gift_card_post_id: " . (isset($gcard['gift_card_post_id']) ? $gcard['gift_card_post_id'] : 'NOT SET') .
		// 	", gift_card_number: " . (isset($gcard['gift_card_number']) ? $gcard['gift_card_number'] : 'NOT SET'),
		// 	$context
		// );
		
		// Check if we need to retrieve fields (missing or empty)
		$need_emailAnimation 	= !isset($gcard['emailAnimation']) || empty($gcard['emailAnimation']) || trim($gcard['emailAnimation']) === '';
		$need_image_url 		= !isset($gcard['image_url']) || empty($gcard['image_url']) || trim($gcard['image_url']) === '';
		$need_gift_card_name 	= !isset($gcard['gift_card_name']) || empty($gcard['gift_card_name']);
		$need_price 			= !isset($gcard['price']) || empty($gcard['price']);
		
		if ($need_emailAnimation || $need_image_url || $need_gift_card_name || $need_price) {
		
			$gift_card_post_id 	= isset($gcard['gift_card_post_id']) ? $gcard['gift_card_post_id'] : null;
			$gift_card_number 	= isset($gcard['gift_card_number']) ? $gcard['gift_card_number'] : null;
			$recipient_email 	= isset($gcard['recipient_email']) ? $gcard['recipient_email'] : (isset($gcard['email']) ? $gcard['email'] : null);
			$gift_card_name 	= isset($gcard['gift_card_name']) ? $gcard['gift_card_name'] : null;
			$gift_card_sku 		= isset($gcard['gift_card_sku']) ? $gcard['gift_card_sku'] : null;
		
			// Try to find order from gift_card_post_id
			$order_id = null;
			if ($gift_card_post_id) {
				$order_id = get_post_meta($gift_card_post_id, '_order_id', true);
				if (is_array($order_id)) {
					$order_id = !empty($order_id) ? $order_id[0] : null;
				}
			}
		
			// If we don't have order_id yet, try to find it by searching orders with recipient_email
			if (!$order_id && $recipient_email) {
				// Commented on 20251217
				// $logger->info("🔍 Searching for order using recipient_email: " . $recipient_email, $context);
				
				// Search recent orders (last 30 days) for matching recipient email
				$date_from = date('Y-m-d', strtotime('-30 days'));
				$args = array(
					'limit' 		=> 50,
					'orderby' 		=> 'date',
					'order' 		=> 'DESC',
					'date_created' 	=> '>=' . $date_from,
					'status' 		=> array('completed', 'processing', 'on-hold'),
				);
				
				$orders = wc_get_orders($args);
				foreach ($orders as $order) {
					foreach ($order->get_items() as $item) {
						$item_recipient_email = wc_get_order_item_meta($item->get_id(), '_recipient_email');
						if ($item_recipient_email === $recipient_email) {
							// Check if gift_card_name or SKU matches if available
							$match = true;
							if ($gift_card_name) {
								$item_gift_card_name = wc_get_order_item_meta($item->get_id(), '_gift_card_name');
								if ($item_gift_card_name !== $gift_card_name) {
									$match = false;
								}
							}
							if ($match && $gift_card_sku) {
								$item_sku = wc_get_order_item_meta($item->get_id(), '_gift_card_sku');
								if ($item_sku !== $gift_card_sku) {
									$match = false;
								}
							}
							
							if ($match) {
								$order_id = $order->get_id();
								// Commented on 20251217
								// $logger->info("✅ Found order ID: " . $order_id . " by recipient_email match", $context);
								
								// Also update gift_card_post_id and gift_card_number if we found them
								if (!$gift_card_post_id) {
									$gift_card_post_id = wc_get_order_item_meta($item->get_id(), '_gift_card_post_id');
									if ($gift_card_post_id) {
										$gcard['gift_card_post_id'] = $gift_card_post_id;
										$logger->info("✅ Retrieved gift_card_post_id from order item: " . $gift_card_post_id, $context);
									}
								}

								if (!$gift_card_number) {
									$gift_card_number = wc_get_order_item_meta($item->get_id(), '_gift_card_number_enc');
									if ($gift_card_number) {
										$gcard['gift_card_number'] = $gift_card_number;
										// Commented on 20251217
										// $logger->info("✅ Retrieved gift_card_number from order item: " . $gift_card_number, $context);
									}
								}

								break 2; // Break out of both loops
							}
						}
					}
				}
				
				// Commented on 20251217
				// if (!$order_id) {
				// 	$logger->info("❌ Could not find order by recipient_email: " . $recipient_email, $context);
				// }
			}
		
			// If we have an order ID, try to retrieve missing fields from order items
			if ($order_id) {
				$order = wc_get_order($order_id);
				if ($order) {
					foreach ($order->get_items() as $item) {
						$item_gift_card_number = wc_get_order_item_meta($item->get_id(), '_gift_card_number_enc');
						$item_gift_card_post_id = wc_get_order_item_meta($item->get_id(), '_gift_card_post_id');
						$item_recipient_email = wc_get_order_item_meta($item->get_id(), '_recipient_email');
						
						// Match by gift card number, gift card post ID, or recipient email
						$matches = false;
						if ($gift_card_number && $item_gift_card_number == $gift_card_number) {
							$matches = true;
						} elseif ($gift_card_post_id && $item_gift_card_post_id == $gift_card_post_id) {
							$matches = true;
						} elseif ($recipient_email && $item_recipient_email === $recipient_email) {
							// Match by recipient email if we don't have gift_card_number or post_id
							// Also check gift_card_name or SKU if available to ensure correct match
							if ($gift_card_name) {
								$item_gift_card_name = wc_get_order_item_meta($item->get_id(), '_gift_card_name');
								if ($item_gift_card_name === $gift_card_name) {
									$matches = true;
								}
							} elseif ($gift_card_sku) {
								$item_sku = wc_get_order_item_meta($item->get_id(), '_gift_card_sku');
								if ($item_sku === $gift_card_sku) {
									$matches = true;
								}
							} else {
								// If we only have recipient_email, match the first item with that email
								$matches = true;
							}
						}
						
						if ($matches) {
							// Retrieve missing fields
							if ($need_emailAnimation) {
								// Try both possible meta key names
								$gift_email_animation = wc_get_order_item_meta($item->get_id(), 'gift_email_animation');
								if (empty($gift_email_animation)) {
									$gift_email_animation = wc_get_order_item_meta($item->get_id(), '_gift_email_animation');
								}
								if (empty($gift_email_animation)) {
									$gift_email_animation = wc_get_order_item_meta($item->get_id(), 'emailAnimation');
								}
								if (!empty($gift_email_animation)) {
									$gcard['emailAnimation'] = $gift_email_animation;
									// Commented on 20251217
									// $logger->info("✅ Retrieved emailAnimation from order item meta: " . $gift_email_animation, $context);
								} else {
									// Commented on 20251217
									// $logger->info("❌ emailAnimation not found in order item meta for item ID: " . $item->get_id(), $context);
								}
							}
							
							if ($need_gift_card_name) {
								$gift_card_name = wc_get_order_item_meta($item->get_id(), '_gift_card_name');
								if (!empty($gift_card_name)) {
									$gcard['gift_card_name'] = $gift_card_name;
									// Commented on 20251217
									// $logger->info("✅ Retrieved gift_card_name from order item meta: " . $gift_card_name, $context);
								} else {
									// Fallback to item name
									$gcard['gift_card_name'] = $item->get_name();
									// Commented on 20251217
									// $logger->info("✅ Using item name as gift_card_name: " . $item->get_name(), $context);
								}
							}
							
							if ($need_image_url) {
								// Try both possible meta key names
								$gift_card_image = wc_get_order_item_meta($item->get_id(), '_gift_card_image');
								if (empty($gift_card_image)) {
									$gift_card_image = wc_get_order_item_meta($item->get_id(), 'gift_card_image');
								}
								if (empty($gift_card_image)) {
									$gift_card_image = wc_get_order_item_meta($item->get_id(), 'image');
								}
								if (empty($gift_card_image)) {
									$gift_card_image = wc_get_order_item_meta($item->get_id(), 'image_url');
								}
								if (!empty($gift_card_image)) {
									$gcard['image_url'] = $gift_card_image;
									// Commented on 20251217
									// $logger->info("✅ Retrieved image_url from order item meta: " . $gift_card_image, $context);
								} else {
									// Fallback to product thumbnail
									$gift_card_sku = wc_get_order_item_meta($item->get_id(), '_gift_card_sku');
									if ($gift_card_sku) {
										$product_id = wc_get_product_id_by_sku($gift_card_sku);
										if ($product_id) {
											$product_image = get_the_post_thumbnail_url($product_id, 'full');
											if ($product_image) {
												$gcard['image_url'] = $product_image;
												// Commented on 20251217
												// $logger->info("✅ Retrieved image_url from product thumbnail: " . $product_image, $context);
											} else {
												$logger->info("❌ image_url not found in order item meta or product thumbnail for item ID: " . $item->get_id(), $context);
											}
										}
									} else {
										$logger->info("❌ image_url not found in order item meta and no SKU available for item ID: " . $item->get_id(), $context);
									}
								}
							}
							
							if ($need_price) {
								$gift_card_price = wc_get_order_item_meta($item->get_id(), '_gift_card_price');
								if (!empty($gift_card_price)) {
									$gcard['price'] = $gift_card_price;
									// Commented on 20251217
									// $logger->info("✅ Retrieved price from order item meta: " . $gift_card_price, $context);
								} else {
									// Fallback to item total
									$item_total = $item->get_total();
									if ($item_total) {
										$gcard['price'] = $item_total;
										// Commented on 20251217
										// $logger->info("✅ Using item total as price: " . $item_total, $context);
									}
								}
							}
							
							// Also retrieve message if missing
							if (!isset($gcard['message']) || empty($gcard['message'])) {
								$gift_message = wc_get_order_item_meta($item->get_id(), '_gift_message');
								if (!empty($gift_message)) {
									$gcard['message'] = $gift_message;
									// Commented on 20251217
									// $logger->info("✅ Retrieved message from order item meta", $context);
								}
							}
							
							// Retrieve expiry_type if missing
							if (!isset($gcard['expiry_type']) || empty($gcard['expiry_type'])) {
								$expiry_type = wc_get_order_item_meta($item->get_id(), '_activation_expiry_type', true);
								if (!empty($expiry_type)) {
									$gcard['expiry_type'] = $expiry_type;
								}
							}
							
							// Retrieve expiry_date if missing
							if (!isset($gcard['expiry_date']) || empty($gcard['expiry_date'])) {
								$expiry_date = wc_get_order_item_meta($item->get_id(), '_activation_expiry_date', true);
								if (!empty($expiry_date)) {
									$gcard['expiry_date'] = $expiry_date;
								}
							}
							
							break; // Found matching item, no need to continue
						}
					}
				}
			}
		
			// Retrieve expiry_type and expiry_date from gift card post if still missing
			if ($gift_card_post_id) {
				if (!isset($gcard['expiry_type']) || empty($gcard['expiry_type'])) {
					// Fallback to gift card post ACF field
					$acf_expiry_type = get_field('_activation_expiry_type', $gift_card_post_id);
					if (!empty($acf_expiry_type)) {
						// Map the raw value to the label format
						$activation_type_labels = [
							'no_activation_expiry' => 'No Activation Expiry',
							'no_activation_needed' => 'No Activation Needed',
							'activation_set_date' => 'Activated by a Set Date',
							'set_period' => 'Activated within a Set Period',
						];
						$gcard['expiry_type'] = $activation_type_labels[$acf_expiry_type] ?? $acf_expiry_type;
					}
				}
				
				if (!isset($gcard['expiry_date']) || empty($gcard['expiry_date'])) {
					// Fallback to gift card post ACF field
					$acf_expiry_date = get_field('_activation_expiry_date', $gift_card_post_id) ?: get_field('activation_expiry_date', $gift_card_post_id);
					if (!empty($acf_expiry_date)) {
						$gcard['expiry_date'] = $acf_expiry_date;
					}
				}
				
				// Retrieve sender_name if missing
				if (!isset($gcard['sender_name']) || empty($gcard['sender_name'])) {
					$sender_name_from_post = get_post_meta($gift_card_post_id, '_sender_name', true);
					if (!empty($sender_name_from_post)) {
						$gcard['sender_name'] = $sender_name_from_post;
					}
				}
				
				// Retrieve sender_email if missing
				if (!isset($gcard['sender_email']) || empty($gcard['sender_email'])) {
					$sender_email_from_post = get_post_meta($gift_card_post_id, '_sender_email', true);
					if (!empty($sender_email_from_post)) {
						$gcard['sender_email'] = $sender_email_from_post;
					}
				}
			}
			
			// Also try to get sender_name from order if we found an order
			if (isset($order_id) && $order_id) {
				$order = wc_get_order($order_id);
				if ($order) {
					if (!isset($gcard['sender_name']) || empty($gcard['sender_name'])) {
						$sender_name_from_order = $order->get_meta('_sender_name', true);
						if (!empty($sender_name_from_order)) {
							$gcard['sender_name'] = $sender_name_from_order;
						}
					}
					
					if (!isset($gcard['sender_email']) || empty($gcard['sender_email'])) {
						$sender_email_from_order = $order->get_meta('_sender_email', true);
						if (!empty($sender_email_from_order)) {
							$gcard['sender_email'] = $sender_email_from_order;
						}
					}
				}
			}
			
			// If still missing fields and we have gift_card_post_id, try to get from post meta
			if ($gift_card_post_id && ($need_emailAnimation || $need_image_url)) {
				if ($need_emailAnimation) {
					// Try multiple possible meta keys
					$email_animation = get_post_meta($gift_card_post_id, '_gift_email_animation', true);
					if (empty($email_animation)) {
						$email_animation = get_post_meta($gift_card_post_id, 'gift_email_animation', true);
					}
					if (empty($email_animation)) {
						$email_animation = get_post_meta($gift_card_post_id, 'emailAnimation', true);
					}
					if (!empty($email_animation)) {
						$gcard['emailAnimation'] = $email_animation;
						// Commented on 20251217
						// $logger->info("✅ Retrieved emailAnimation from gift card post meta: " . $email_animation, $context);
					} else {
						$logger->info("❌ emailAnimation not found in gift card post meta for post ID: " . $gift_card_post_id, $context);
					}
				}
				
				if (!isset($gcard['image_url']) || empty($gcard['image_url'])) {
					// Try multiple possible meta keys
					$image_url = get_post_meta($gift_card_post_id, '_image_url', true);
					if (empty($image_url)) {
						$image_url = get_post_meta($gift_card_post_id, 'image_url', true);
					}
					if (empty($image_url)) {
						$image_url = get_post_meta($gift_card_post_id, '_gift_card_image', true);
					}
					if (empty($image_url)) {
						$image_url = get_post_meta($gift_card_post_id, 'image', true);
					}
					if (!empty($image_url)) {
						$gcard['image_url'] = $image_url;
						// Commented on 20251217
						// $logger->info("✅ Retrieved image_url from gift card post meta: " . $image_url, $context);
					} else {
						// Try to get from product if we have SKU
						$sku_to_use = null;
						if (isset($gcard['gift_card_sku']) && !empty($gcard['gift_card_sku'])) {
							$sku_to_use = $gcard['gift_card_sku'];
						} elseif ($gift_card_post_id) {
							// Try to get SKU from gift card post meta
							$sku_to_use = get_post_meta($gift_card_post_id, '_product_sku', true);
							if (empty($sku_to_use)) {
								$sku_to_use = get_post_meta($gift_card_post_id, 'product_sku', true);
							}
						}
						
						if ($sku_to_use) {
							$product_id = wc_get_product_id_by_sku($sku_to_use);
							if ($product_id) {
								$product_image = get_the_post_thumbnail_url($product_id, 'full');
								if ($product_image) {
									$gcard['image_url'] = $product_image;
									// Commented on 20251217
									// $logger->info("✅ Retrieved image_url from product thumbnail via SKU: " . $product_image . " (SKU: " . $sku_to_use . ")", $context);
								} else {
									$logger->info("❌ Product thumbnail not found for product ID: " . $product_id . " (SKU: " . $sku_to_use . ")", $context);
								}
							} else {
								$logger->info("❌ Product not found for SKU: " . $sku_to_use, $context);
							}
						}
						
						if ($need_image_url && (!isset($gcard['image_url']) || empty($gcard['image_url']))) {
							$logger->info("❌ image_url not found in gift card post meta for post ID: " . $gift_card_post_id, $context);
						}
					}
				}
			}
			
			// Log final state of key fields after retrieval
			// Commented on 20251217
			// $logger->info(
			// 	"🔍 Final fields after retrieval - emailAnimation: " . (isset($gcard['emailAnimation']) && !empty($gcard['emailAnimation']) ? $gcard['emailAnimation'] : 'NOT SET') . 
			// 	", gift_card_name: " . (isset($gcard['gift_card_name']) && !empty($gcard['gift_card_name']) ? $gcard['gift_card_name'] : 'NOT SET') .
			// 	", image_url: " . (isset($gcard['image_url']) && !empty($gcard['image_url']) ? $gcard['image_url'] : 'NOT SET') .
			// 	", price: " . (isset($gcard['price']) && !empty($gcard['price']) ? $gcard['price'] : 'NOT SET'),
			// 	$context
			// );

		} // End of if ($need_emailAnimation || $need_image_url || ...)

		// Retrieve delivery_method and recipient_phone if not in $gcard array
		if (!empty($gcard)) {
			$gift_card_post_id = isset($gcard['gift_card_post_id']) ? $gcard['gift_card_post_id'] : null;
			
			// Get delivery method
			if (!isset($gcard['delivery_method']) || empty($gcard['delivery_method'])) {
				if ($gift_card_post_id) {
					$gcard['delivery_method'] = get_post_meta($gift_card_post_id, '_delivery_method', true);
				}
				// If still not found, try to get from order item meta
				if (empty($gcard['delivery_method']) && $gift_card_post_id) {
					$order_id = get_post_meta($gift_card_post_id, '_order_id', true);
					if ($order_id) {
						$order = wc_get_order($order_id);
						if ($order) {
							foreach ($order->get_items() as $item) {
								$item_gift_card_post_id = wc_get_order_item_meta($item->get_id(), '_gift_card_post_id');
								if ($item_gift_card_post_id == $gift_card_post_id) {
									$gcard['delivery_method'] = wc_get_order_item_meta($item->get_id(), '_delivery_method');
									break;
								}
							}
						}
					}
				}
			}
			
			// Get recipient phone
			if (!isset($gcard['recipient_phone']) || empty($gcard['recipient_phone'])) {
				if ($gift_card_post_id) {
					$gcard['recipient_phone'] = get_post_meta($gift_card_post_id, '_recipient_phone', true);
				}
			}
		} // End of if (!empty($gcard))


	    if( !empty($gcard) ){

	        // echo 'hello sender name is this below : ';
	        // echo '<pre>';
	        // print_r($gcard['sender_name']);
	        // echo '</pre>';
	        // exit;
	        // DEBUG: Check sender_name retrieval
	        // echo '<pre style="background: #e7f3ff; padding: 20px; margin: 20px; border: 2px solid blue; font-size: 14px;">';
	        // echo "=== SENDER NAME DEBUG ===\n\n";
	        // echo "1. GIFT CARD DATA:\n";
	        // echo "   gcard['sender_name']: " . print_r($gcard['sender_name'] ?? 'NOT SET', true) . "\n";
	        // echo "   gcard['sender_email']: " . print_r($gcard['sender_email'] ?? 'NOT SET', true) . "\n";
	        // echo "   gcard['gift_card_post_id']: " . print_r($gcard['gift_card_post_id'] ?? 'NOT SET', true) . "\n\n";
	        
	        // Try to get from gift card post if still missing
	        // if ((!isset($gcard['sender_name']) || empty($gcard['sender_name'])) && !empty($gcard['gift_card_post_id'])) {
	        //     $gift_card_post_id = $gcard['gift_card_post_id'];
	        //     $sender_name_from_post = get_post_meta($gift_card_post_id, '_sender_name', true);
	            // echo "2. RETRIEVAL FROM GIFT CARD POST:\n";
	            // echo "   Gift Card Post ID: " . $gift_card_post_id . "\n";
	            // echo "   _sender_name from post meta: " . print_r($sender_name_from_post, true) . "\n";
	            // if (!empty($sender_name_from_post)) {
	            //     $gcard['sender_name'] = $sender_name_from_post;
	                // echo "   ✓ sender_name retrieved and set in gcard\n";
	            // }
	            // echo "\n";
	        // }
	        
	        // Try to get from order if still missing
	        // if ((!isset($gcard['sender_name']) || empty($gcard['sender_name'])) && !empty($gcard['gift_card_post_id'])) {
	        //     $gift_card_post_id = $gcard['gift_card_post_id'];
	        //     $order_id_from_post = get_post_meta($gift_card_post_id, '_order_id', true);
	        //     if (is_array($order_id_from_post)) {
	        //         $order_id_from_post = !empty($order_id_from_post) ? $order_id_from_post[0] : null;
	        //     }
	            // echo "3. RETRIEVAL FROM ORDER:\n";
	            // echo "   Order ID from gift card post: " . print_r($order_id_from_post, true) . "\n";
	        //     if ($order_id_from_post) {
	        //         $order = wc_get_order($order_id_from_post);
	        //         if ($order) {
	        //             $sender_name_from_order = $order->get_meta('_sender_name', true);
	        //             // echo "   _sender_name from order meta: " . print_r($sender_name_from_order, true) . "\n";
	        //             if (!empty($sender_name_from_order)) {
	        //                 $gcard['sender_name'] = $sender_name_from_order;
	        //                 // echo "   ✓ sender_name retrieved and set in gcard\n";
	        //             }
	        //         }
	        //     }
	        //     // echo "\n";
	        // }
	        
	        // echo "4. FINAL RESULT:\n";
	        // echo "   Final gcard['sender_name']: " . print_r($gcard['sender_name'] ?? 'NOT SET', true) . "\n";
	        // echo "=====================================";
	        // echo '</pre>';
	        // exit; // Stop execution to see debug output
	        
	        // If no sender name, use the customer's (order placer's) name; fallback to site default
	        $sender_name = isset($gcard['sender_name']) && !empty(trim($gcard['sender_name'])) ? $gcard['sender_name'] : '';
	        if (empty($sender_name)) {
	            $sender_order_id = isset($order_id) && $order_id ? $order_id : null;
	            if (!$sender_order_id && !empty($gcard['gift_card_post_id'])) {
	                $sender_order_id = get_post_meta($gcard['gift_card_post_id'], '_order_id', true);
	                if (is_array($sender_order_id)) {
	                    $sender_order_id = !empty($sender_order_id) ? $sender_order_id[0] : null;
	                }
	            }
	            if ($sender_order_id) {
	                $order_obj = wc_get_order($sender_order_id);
	                if ($order_obj) {
	                    $sender_name = trim($order_obj->get_billing_first_name() . ' ' . $order_obj->get_billing_last_name());
	                    if (empty($sender_name) && $order_obj->get_customer_id()) {
	                        $customer = get_user_by('id', $order_obj->get_customer_id());
	                        if ($customer && !empty(trim($customer->display_name ?? ''))) {
	                            $sender_name = trim($customer->display_name);
	                        }
	                    }
	                }
	            }
	        }
	        $sender_name = !empty($sender_name) ? $sender_name : 'Gift Cards Plus';
	        $sender_email 		= isset($gcard['sender_email']) && !empty($gcard['sender_email']) ? $gcard['sender_email'] : get_option('admin_email');
	        // Check $gcard['subject'] first, then fall back to _gift_subject saved on the gift card post.
        $subject_email = '';
        if ( !empty($gcard['subject']) ) {
            $subject_email = $gcard['subject'];
        } elseif ( !empty($gcard['gift_card_post_id']) ) {
            $subject_email = get_post_meta( $gcard['gift_card_post_id'], '_gift_subject', true );
        }
        if ( empty($subject_email) ) {
            $subject_email = get_option('subject');
        }
	        $business_user_name = isset($gcard['business_user_name']) ? sanitize_text_field($gcard['business_user_name']) : '';


	        // Get delivery method and phone
	        $delivery_method = isset($gcard['delivery_method']) ? strtolower(trim($gcard['delivery_method'])) : 'email';
	        $recipient_phone = isset($gcard['recipient_phone']) ? trim($gcard['recipient_phone']) : '';
	        
	        // Normalize delivery method values
	        $is_sms_only = false;
	        $is_email_only = false;
	        $is_email_sms = false;
	        
	        if (strpos($delivery_method, 'sms') !== false && strpos($delivery_method, 'email') === false) {
	            $is_sms_only = true;
	        } elseif (strpos($delivery_method, 'email') !== false && strpos($delivery_method, 'sms') !== false) {
	            $is_email_sms = true;
	        } elseif (strpos($delivery_method, 'email') !== false || empty($delivery_method)) {
	            $is_email_only = true;
	        } else {
	            // Default to email if unclear
	            $is_email_only = true;
	        }

	        $email_message = '';
	        
	        // Check if we have message, animation, or image
	        $has_message 	= isset($gcard['message']) && trim(strip_tags($gcard['message'])) !== '';
	        $has_animation 	= isset($gcard['emailAnimation']) && !empty($gcard['emailAnimation']) && trim($gcard['emailAnimation']) !== '';
			$has_image_msg 	= isset($gcard['image_message_url']) && !empty($gcard['image_message_url']) && trim($gcard['image_message_url']) !== '';
	        
	        // Debug logging for animation and image
			// Commented on 20251217
	        // $logger->info(
	        // 	"🎬 Animation check - emailAnimation value: " . (isset($gcard['emailAnimation']) ? var_export($gcard['emailAnimation'], true) : 'NOT SET') . 
	        // 	", has_animation: " . ($has_animation ? 'YES' : 'NO'),
	        // 	$context
	        // );
	        
	        $image_url_value = isset($gcard['image_url']) ? $gcard['image_url'] : null;
	        $image_url_check = isset($gcard['image_url']) && !empty($gcard['image_url']) && trim($gcard['image_url']) !== '';

			// Commented on 20251217
	        // $logger->info(
	        // 	"🖼️ Image check - image_url value: " . var_export($image_url_value, true) . 
	        // 	", image_url check result: " . ($image_url_check ? 'PASS' : 'FAIL') .
	        // 	", will show in email: " . ($image_url_check ? 'YES' : 'NO'),
	        // 	$context
	        // );
	        
        	if ($has_message || $has_animation || $has_image_msg) {
	        	$temp_message = isset($gcard['message']) ? $gcard['message'] : '';
	        	
	        	$email_message = '<tr>
	            <td style="padding: 20px 20px;" colspan="2">
	              <div style="padding: 40px 20px; font-family: Verdana; font-size: 18px; letter-spacing: 0%; background-color: #F8F9FF; text-align: center;">';
	        	
	        	// Add message if exists
	        	if ($has_message) {
	        		$email_message .= $temp_message;
	        	}
	        	
	        	// Add animation (GIF) if exists
				if ($has_animation) {
					if ($has_message || $has_image_msg) {
						$email_message .= '<div style="margin-top:15px;">';
					}
					$email_message .= '<img src="' . esc_url($gcard['emailAnimation']) . '" alt="Email Animation" style="max-width:100%; height:auto;" />';
					if ($has_message || $has_image_msg) {
						$email_message .= '</div>';
					}
				}
				
				// Add image message if exists
				if ($has_image_msg) {
					if ($has_message || $has_animation) {
						$email_message .= '<div style="margin-top:15px;">';
					}
					$email_message .= '<img src="' . esc_url($gcard['image_message_url']) . '" alt="Message Image" style="max-width:100%; height:auto;" />';
					if ($has_message || $has_animation) {
						$email_message .= '</div>';
					}
				}
	        	
	        	$email_message .= '</div>
	            	</td>
	  			</tr>';
	        	
				// Commented on 20251217
	        	// $logger->info("✅ Email message section created - Has message: " . ($has_message ? 'Yes' : 'No') . ", Has animation: " . ($has_animation ? 'Yes' : 'No'), $context);
	        } else {
	        	$logger->info("❌ Email message section NOT created - No message or animation found in gcard data", $context);
	        }

			// Commented on 20251217
	        //pr($email_message);
	        // $logger->info(
			// 			"Email Message  Testinggg",$context
			// 		);
	        // $logger->info(
			// 			"Email Message " . print_r($email_message,true),
			// 			$context
			// 		);

	        $subject = "You've received gift card!";
	        if( !empty($subject_email) ){
	        	$subject = $subject_email;
	        }

	        $now 					= new DateTime();
	        $formatted_expiry_date 	= '00/00/0000';
			$expiry_type 			= '';
			$formatted_expiry_date 	= !empty($gcard['expiry_date'])
				? gc_format_activation_expiry_date_for_email($gcard['expiry_date'])
				: '';
			$expiry_type 			= !empty($gcard['expiry_type']) ? $gcard['expiry_type'] : 'No activation expiry';

			$expiry_text = '';

			if (
				strcasecmp($expiry_type, 'No activation expiry') !== 0 &&
				strcasecmp($expiry_type, 'No activation needed') !== 0 &&
				!empty($formatted_expiry_date)
			) {
				$expiry_text = 'Don\'t forget to activate this Gift Card by <strong>' . esc_html($formatted_expiry_date) . '</strong>. ';
			}

			// Commented on 20251217
	        // if (
	        // if (
	        //     !empty($gcard['expiry_type']) &&
	        //     $gcard['expiry_type'] === 'Activated within a Set Period' &&
	        //     !empty($gcard['expiry_duration']) &&
	        //     !empty($gcard['expiry_unit'])
	        // ) {
	        //     $unit = strtolower($gcard['expiry_unit']);
	        //     $duration = intval($gcard['expiry_duration']);

	        //     switch ($unit) {
	        //         case 'days':
	        //             $now->modify("+{$duration} days");
	        //             break;
	        //         case 'weeks':
	        //             $now->modify("+{$duration} weeks");
	        //             break;
	        //         case 'months':
	        //             $now->modify("+{$duration} months");
	        //             break;
	        //         case 'years':
	        //             $now->modify("+{$duration} years");
	        //             break;
	        //     }

	        // 	$formatted_expiry_date = $now->format('F j, Y');
	        // }

			$email_header_banner = $email_footer_text = '';
			$email_header_banner_bg = '#E4F2FB';

			// Banner is based on whether the order was placed by a recognised business user
			// (Havit/Gyprock, checked below), not the product's brand. Default is the cake
			// "Your special delivery" banner used for everyone else. All banners share the
			// same full-width single-image layout (see $email_header_banner below) — only
			// the image/background color changes per business.
			$gift_card_post_id      = isset($gcard['gift_card_post_id']) ? $gcard['gift_card_post_id'] : '';
			$brand_hero_url         = site_url() . '/wp-content/uploads/add-to-wallet-banner.png';
			$business_user_name_lc  = isset($gcard['business_user_name']) ? strtolower($gcard['business_user_name']) : '';

			if( $business_user_name_lc == 'havit rewards'
				|| strpos($business_user_name_lc, 'havit') !== FALSE
			){
				$email_header_banner_bg = '#00AEEF';
				$brand_hero_url = site_url() . '/wp-content/uploads/' . rawurlencode('Havit.png');
				$email_footer_text = '<p style="margin:0 0 16px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">We hope you enjoy your reward from Havit Rewards.</p>';
			}

			if( $business_user_name_lc == 'club gyprock'
				|| strpos($business_user_name_lc, 'gyprock') !== FALSE
			){
				$email_header_banner_bg = '#000000';
				$brand_hero_url = site_url() . '/wp-content/uploads/' . rawurlencode('Club Gyprock.png');
				$email_footer_text = '<p style="margin:0 0 16px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">We hope you enjoy your reward from Club Gyprock.</p>';
			}

			// Full-width single-image banner — cake image by default, Havit/Gyprock image otherwise.
			$email_header_banner = '<table id="hero-banner" style="width:100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="' . $email_header_banner_bg . '">
	            <tbody>
	              <tr>
	                <td style="padding:0;text-align:center;vertical-align:middle;">
	                  <img src="' . esc_url($brand_hero_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="display:block;width:100%;max-width:600px;height:auto;" width="600">
	                </td>
	              </tr>
	            </tbody>
	        </table>';

	        // $letters = chr(rand(65, 90)) . chr(rand(65, 90)); // A–Z
	        // $numbers = rand(1000, 9999);

	        // Support both keys: manual/bulk order flow passes gift_card_id, other flows pass gift_card_post_id
	        $gift_card_post_id = isset($gcard['gift_card_post_id']) ? $gcard['gift_card_post_id'] : (isset($gcard['gift_card_id']) ? $gcard['gift_card_id'] : null);
			// echo '<pre>';
			// print_r($rand_code);
			// echo '</pre>';
			// exit;
			$rand_code = '';
	        if ($gift_card_post_id) {
	            $rand_code = get_post_meta($gift_card_post_id, 'gcard_security_pin', true);
	        }
	        
			// Fallback: if not found, try alternative meta keys on the gift card post
	        if (empty($rand_code) && $gift_card_post_id) {
	            $rand_code = get_post_meta($gift_card_post_id, '_gcard_security_pin', true) ?: 
	                        get_post_meta($gift_card_post_id, 'security_pin', true) ?:
	                        get_post_meta($gift_card_post_id, '_security_pin', true);
	        }
	        // If still empty, use card_pin or pin from $gcard when provided (e.g. manual/bulk flow that pre-fetches PIN)
	        if (empty($rand_code) && !empty($gcard['card_pin'])) {
	            $rand_code = $gcard['card_pin'];
	        }
			if (empty($rand_code) && !empty($gcard['pin'])) {
	            $rand_code = $gcard['pin'];
	        }
	        // Last resort: we have a gift card post but no PIN was ever saved; generate and save one so email shows real code
	        if (empty($rand_code) && $gift_card_post_id) {
	            $rand_code = str_pad( (string) wp_rand(0, 9999), 4, '0', STR_PAD_LEFT );
	            update_post_meta( $gift_card_post_id, 'gcard_security_pin', $rand_code );
	        }


	        $preview_text = 'To add this to you Wallet <a href="'.$redeem_link.'" target="_blank">click here</a> and enter the wallet code <strong>' . ($rand_code ?: '[PIN NOT FOUND]') . '</strong>.';

			$payload = array(
			    'gc_id' => $gift_card_post_id,
			    'email' => $gcard['recipient_email'],
			);

			$payload_encoded = base64_encode( wp_json_encode( $payload ) );

			$signature = hash_hmac(
			    'sha256',
			    $payload_encoded,
			    wp_salt( 'auth' )
			);

			$token = $payload_encoded . '.' . $signature;

			$redeem_link = add_query_arg(
			    array(
			        'action' => 'gcp_add_to_wallet',
			        'token'  => rawurlencode( $token ),
			    ),
			    home_url('/')
			);

	        $redeem_link = esc_url( $redeem_link );

	        // --- CHECK: Is this a Gift Card Plus? ---
	        // ACF radio field 'is_it_gift_card_plus_product' on the product stores 'true'/'false'.
	        // At order time this is copied to the gift_card post as '_is_gc_plus_product'.
	        // Check the saved copy first, then fall back to reading from the parent product directly.
	        $is_gift_card_plus = false;
	        $gc_id_check = $gift_card_post_id;

	        if ( $gc_id_check ) {
	            // 1. Check the saved meta on the gift_card post (_is_gc_plus_product = 'true'/'false')
	            $gc_plus_meta = get_post_meta( $gc_id_check, '_is_gc_plus_product', true );

	            // 2. Fallback: read ACF field directly from the parent product
	            if ( $gc_plus_meta === '' || $gc_plus_meta === false ) {
	                $parent_prod_id = get_post_meta( $gc_id_check, '_product_id', true );

	                if ( ! $parent_prod_id ) {
	                    $parent_prod_id = get_post_meta( $gc_id_check, '_order_product_id', true );
	                }

	                // Last resort: find product via SKU
	                if ( ! $parent_prod_id && ! empty( $gcard['gift_card_sku'] ) ) {
	                    $parent_prod_id = wc_get_product_id_by_sku( $gcard['gift_card_sku'] );
	                }

	                if ( $parent_prod_id ) {
	                    $gc_plus_meta = get_post_meta( $parent_prod_id, 'is_it_gift_card_plus_product', true );
	                }
	            }

	            // 3. Set the boolean flag — ACF radio stores 'true'/'false' as strings
	            if ( $gc_plus_meta === 'true' || $gc_plus_meta === '1' || $gc_plus_meta === true ) {
	                $is_gift_card_plus = true;
	            }
	        }

	        // --- GIFT CARD IMAGE HTML (with pink band composited onto image for GCP cards) ---
	        $_img_url = (isset($gcard['image_url']) && trim($gcard['image_url']) !== '') ? $gcard['image_url'] : '';
	        $gc_image_html = '';
	        if ( $_img_url ) {
	            if ( $is_gift_card_plus && function_exists('gcp_composite_email_image') ) {
	                $_gcp_logo_url  = wp_get_attachment_url('6230');
	                $_denom         = !empty($gcard['price']) ? $gcard['price'] : (!empty($gcard['amount']) ? $gcard['amount'] : '');
	                $_composited    = gcp_composite_email_image($_img_url, $_gcp_logo_url, $_denom);
	                $gc_image_html  = '<img style="padding-bottom:5px; display:block;" width="100%" src="' . esc_url($_composited) . '" alt="Gift Card Image">';
	            } else {
	                $gc_image_html = '<img style="padding-bottom:5px;" width="100%" src="' . esc_url($_img_url) . '" alt="Gift Card Image">';
	            }
	        }




	        // ── Try Email Template post (slug = gift-card-received) ─────────────────

	        // Resolve display values used in both the ET token block and the fallback HTML.
	        $recipient_name_et = isset($gcard['name']) && !empty($gcard['name']) ? $gcard['name'] : (isset($gcard['recipient_name']) ? $gcard['recipient_name'] : 'there');
	        $brand_name_et     = isset($gcard['gift_card_name']) && !empty($gcard['gift_card_name']) ? $gcard['gift_card_name'] : (isset($gcard['card_name']) ? $gcard['card_name'] : 'Gift Card');
	        $amount_et         = isset($gcard['price']) && !empty($gcard['price']) ? $gcard['price'] : (isset($gcard['amount']) ? $gcard['amount'] : '0.00');
	        $gift_card_code_et = $rand_code ?: '';
	        $image_url_et      = isset($gcard['image_url']) && !empty($gcard['image_url']) ? $gcard['image_url'] : '';
	        $message_et        = isset($gcard['message']) ? trim($gcard['message']) : '';
	        $has_message_et    = $message_et !== '';
	        $animation_et      = isset($gcard['emailAnimation']) && !empty($gcard['emailAnimation'])
	            ? '<img src="' . esc_url($gcard['emailAnimation']) . '" alt="Animation" style="max-width:100%;height:auto;display:block;margin:8px auto 0;" />'
	            : '';
	        $image_msg_et      = isset($gcard['image_message_url']) && !empty($gcard['image_message_url'])
	            ? '<img src="' . esc_url($gcard['image_message_url']) . '" alt="Message" style="max-width:100%;height:auto;display:block;margin:8px auto 0;" />'
	            : '';

	        // ── Scenario detection ────────────────────────────────────────────────────
	        // Scenario 4: GiftCardsPlus own card
	        // Scenario 5: Branded-sender card (Havit Rewards, Club Gyprock…)
	        // Scenario 2: Standard/branded card WITH personal message (+ optional animation/image)
	        // Scenario 3: Standard/branded card WITH animation/image ONLY (no text message)
	        // Scenario 1: Standard/branded card with no personalisation at all
	        $has_personalisation = $has_message_et || ( $animation_et !== '' ) || ( $image_msg_et !== '' );
	
	        // ── {personal_message} token ──────────────────────────────────────────────
	        // Shown only for Scenarios 2 & 3 (has message, animation, or image-message).
	        // Empty string for Scenarios 1, 4, 5 so the row/section renders nothing.
	        $personal_msg_et = '';
	        if ( $has_personalisation ) {
	            $personal_msg_et = '<tr style="background-color:#EB148D1A;"><td style="padding:0 40px 16px 40px;">'
	                . '<div style="padding:20px;text-align:center;font-family:Verdana,Geneva,sans-serif;font-size:16px;">';
	            if ( $has_message_et ) {
	                $personal_msg_et .= '<p style="margin:0 0 8px;">' . $message_et . '</p>';
	            }
	            if ( $animation_et !== '' ) {
	                $personal_msg_et .= $animation_et;
	            }
	            if ( $image_msg_et !== '' ) {
	                $personal_msg_et .= $image_msg_et;
	            }
	            $personal_msg_et .= '</div></td></tr>';
	        }

	        // ── {intro_text} token ────────────────────────────────────────────────────
	        // Scenario 4 (GCP): mentions giftcardsplus brand.
	        // All other scenarios: mentions the specific brand card name.
	        if ( $is_gift_card_plus ) {
	            $intro_text_et = 'Ta-da! You\'ve received a <strong>giftcards</strong><em>plus</em>&#8482; digital gift card from <strong>' . esc_html($sender_name) . '</strong>.';
	        } else {
	            $intro_text_et = 'Ta-da! You\'ve received a <strong>' . esc_html($brand_name_et) . '</strong> digital gift card from <strong>' . esc_html($sender_name) . '</strong>.';
	        }

	        // ── {instructions_step4} token ────────────────────────────────────────────
	        // Scenario 4 (GCP): recipient swaps for a brand card of their choice.
	        // Scenarios 1-3, 5 (standard/branded): keep it safe until ready to spend.
	        if ( $is_gift_card_plus ) {
	            $instructions_step4_et = 'Swap your <strong>giftcards</strong><em>plus</em>&#8482; card for any gift card(s) of your choice';
	            $instructions_intro_et = 'To use your <strong>giftcards</strong><em>plus</em>&#8482; card, simply add it to your <strong>giftcards</strong><em>plus</em>&#8482; wallet by following these steps:';
	            $instructions_after_et = '<p style="margin:0 0 16px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#ED018C;font-style:italic;font-weight:bold;">Then it\'s time to shop!</p>'
	                . ( $expiry_text ? '<p style="margin:0 0 16px;font-size:13px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#555555;font-style:italic;">Please note: ' . $expiry_text . '</p>' : '' );
	        } else {
	            $instructions_step4_et = 'Keep it safe until you are ready to spend';
	            $instructions_intro_et = 'How to add the digital card to your <strong>giftcards</strong><em>plus</em>&#8482; wallet:';
	            $_expiry_date_display = !empty($formatted_expiry_date) ? $formatted_expiry_date : '00/00/00';
	            $instructions_after_et = '<p style="margin:0 0 16px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">Prefer to use your gift right now? A PDF file with all the details is attached to this email.</p>'
	                . '<p style="margin:0 0 16px;font-size:14px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#2D2D2D;font-style:italic;">Please ensure you read the retailer-specific instructions and expiry details for this card. Don\'t forget to activate your gift card by ' . esc_html($_expiry_date_display) . '.</p>';
	        }

	        // ── {gift_card_image} token ───────────────────────────────────────────────
	        $gift_card_image_et = $image_url_et
	            ? '<img src="' . esc_url($image_url_et) . '" alt="' . esc_attr($brand_name_et) . '" style="max-width:260px;height:auto;display:block;margin:0 auto 12px;" />'
	            : '';

	        // ── Banner left-image URL (used by some ET themes as a separate token) ────
	        // Mirrors $brand_hero_url: cake banner for everyone except Havit/Gyprock.
	        $banner_left_img_url = $brand_hero_url;

	        // ET token: {banner_html} is substituted directly inside the email template's outer <table>
	        // as a <tr> row, so emit <tr><td>...</td></tr> — NOT a standalone <table> wrapper.
	        // Full-width single image for everyone (cake, or Havit/Gyprock via $brand_hero_url above).
	        $banner_html_et = '<tr><td style="background-color:' . esc_attr($email_header_banner_bg) . ';padding:0;text-align:center;vertical-align:middle;">'
	            . '<img src="' . esc_url($brand_hero_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="display:block;width:100%;max-width:600px;height:auto;" width="600">'
	            . '</td></tr>';

	        $et_tpl = function_exists('et_get_template_by_slug')
	            ? et_get_template_by_slug('gift-card-received', [
	                'first_name'          => $recipient_name_et,
	                'sender_name'         => $sender_name,
	                'brand_name'          => $brand_name_et,
	                'amount'              => $amount_et,
	                'gift_card_code'      => $gift_card_code_et,
	                'gift_card_image'     => $gift_card_image_et,
	                'personal_message'    => $personal_msg_et,
	                'intro_text'           => $intro_text_et,
	                'instructions_intro'   => $instructions_intro_et,
	                'instructions_step4'   => $instructions_step4_et,
	                'instructions_after'   => $instructions_after_et,
	                'expiry_text'          => $expiry_text,
	                'redeem_link'         => $redeem_link,
	                'banner_html'         => $banner_html_et,
	                'banner_left_img'     => $banner_left_img_url,
	                'footer_text'         => $email_footer_text,
	            ])
	            : false;

	        if ( $et_tpl ) {
	            // Only use the email template subject if no custom subject was provided in the order flow.
	            if ( empty( $subject_email ) ) {
	                $subject = $et_tpl['subject'];
	            }
	            // et_email_wrapper wraps content in align="center" — override to left-align
	            $email_body = str_replace(
	                '<td align="center">',
	                '<td align="left">',
	                $et_tpl['body']
	            );
	        } else {

	        $email_body = '';
	        $email_body = '<html>
			  <head>
			    <meta http-equiv="Content-Type" content="text/html;UTF-8" />
			  </head>
			  <body style="margin: 0px; background-color: #F4F3F4; font-family: Helvetica, Arial, sans-serif; font-size:12px;" text="#444444" bgcolor="#F4F3F4" link="#21759B" alink="#21759B" vlink="#21759B" marginheight="0" topmargin="0" marginwidth="0" leftmargin="0">
			    <table border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="#F4F3F4">
			      <tbody>
			        <tr>
			          <td style="padding: 15px;"><center>
			            <table width="600" cellspacing="0" cellpadding="0" align="center" bgcolor="#ffffff">
			              <tbody>
			                <tr>
			                  <td align="left">
			                    <div>
			                      <table id="header" style="padding:28px 0; line-height: 1.6; font-size: 12px; font-family: Verdana, Arial, sans-serif;c olor: #444;width:100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff">
			                        <tbody>
			                          <tr>
			                            <td  style="line-height: 32px; text-align: center; vertical-align: middle;" valign="middle">
			                            	<span style="font-size: 32px;">
			                            		<a style="text-decoration: none;" href="'.esc_url(home_url('/')).'" target="_blank" rel="noopener">
			                            			<img width="228px" height="63px" src="'.site_url().'/wp-content/uploads/2025/09/giftcardsplus-V6-ephisis-logo-4.png" class="logo" alt="'.get_bloginfo('name').'" itemprop="logo">
			                            		</a>
			                            	</span></td>
			                          </tr>
			                        </tbody>
			                      </table>
			                      '.$email_header_banner.'

			                      <table id="content" style="padding-top: 32px; color: #444; line-height: 1.6; font-size: 12px; font-family: Arial, sans-serif; width: 100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff">
			                        <tbody>
			                          <tr>
			                            <td style="padding-left: 40px; padding-right: 40px;" colspan="2">
			                              <div style="color:#2D2D2D; font-family: Verdana; font-weight: bold; font-size: 20px; letter-spacing: 1%;">
			                                  Hi '.$recipient_name_et.',
			                              </div>
			                              <div style="padding: 15px 0; font-family: Verdana; font-size: 18px; letter-spacing: 0%;">
	                                        '.$intro_text_et.'
	                                      </div>
			                            </td>
			                          </tr>
			                          '.( $personal_msg_et ? '<tr><td style="padding:0 40px 16px 40px;" colspan="2">' . $personal_msg_et . '</td></tr>' : '' ).'
			                          <tr>
			                            <td style="padding-left: 40px; padding-right: 40px;" colspan="2" align="left">
			                              <div style="padding: 15px 0; width: 270px; font-family: Gotham; font-weight: bold; font-size: 24px;">
			                                '.$gc_image_html.'
			                                <div class="pricing" style="display: flex; flex-wrap: wrap; justify-content: space-between;">
			                                  <p style="font-family: Verdana; font-size: 15px; margin:0 !important; padding: 0 !important; word-break: break-word;">'.$brand_name_et.'</p>
			                                  <p style="font-family: Verdana; font-size: 15px; margin:0 !important; padding: 0 !important;">$'.$amount_et.'</p>
			                                </div>
			                              </div>
			                            </td>
		                              </tr>
			                          <tr>
			                            <td style="padding-left: 40px; padding-right: 40px;" colspan="2">
			                              '.( $is_gift_card_plus ? '
			                              <p style="margin:0 0 6px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">To use your <strong>giftcards</strong><em>plus</em>&#8482; card, simply add it to your <strong>giftcards</strong><em>plus</em>&#8482; wallet by following these steps:</p>
			                              <ol style="margin:0 0 20px;padding-left:20px;font-size:15px;line-height:180%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">
			                                <li>Click the Add to Wallet button above.</li>
			                                <li>Sign in or create your <strong>giftcards</strong><em>plus</em>&#8482; account.</li>
			                                <li>Enter your unique wallet code: <strong>'.$gift_card_code_et.'</strong></li>
			                                <li>Swap your <strong>giftcards</strong><em>plus</em>&#8482; card for any gift card(s) of your choice</li>
			                              </ol>
			                              <p style="margin:0 0 16px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#ED018C;font-style:italic;font-weight:bold;">Then it\'s time to shop!</p>
			                              '.(($expiry_text) ? '<p style="margin:0 0 16px;font-size:13px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#555555;font-style:italic;">Please note: ' . $expiry_text . '</p>' : '').'
			                              ' : '
			                              <p style="margin:0 0 6px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">How to add the digital card to your <strong>giftcards</strong><em>plus</em>&#8482; wallet:</p>
			                              <ol style="margin:0 0 20px;padding-left:20px;font-size:15px;line-height:180%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">
			                                <li>Click the Add to Wallet button above.</li>
			                                <li>Sign in or create your <strong>giftcards</strong><em>plus</em>&#8482; account.</li>
			                                <li>Enter your unique wallet code: <strong>'.$gift_card_code_et.'</strong></li>
			                                <li>Keep it safe until you are ready to spend</li>
			                              </ol>
			                              <p style="margin:0 0 16px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">Prefer to use your gift right now? A PDF file with all the details is attached to this email.</p>
			                              '.(($expiry_text) ? '<p style="margin:0 0 16px;font-size:13px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#555555;font-style:italic;">' . $expiry_text . '</p>' : '').'
			                              ' ).'
			                              '.$email_footer_text.'
			                              <p style="margin:0 0 4px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">Happy Shopping!</p>
			                              <p style="margin:0 0 16px;font-size:15px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#1a1a1a;">The <strong>giftcards</strong><em>plus</em>&#8482; Team</p>
			                              <p style="margin:0;font-size:12px;line-height:150%;font-family:Verdana,Geneva,sans-serif;color:#888888;font-style:italic;">This is an automated message. Please do not reply to this email, as this inbox is not monitored.</p>
			                            </td>
			                          </tr>
			                        </tbody>
			                      </table>

			                      <table id="footer" style="padding: 32px 0; width: 100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="#F8F9FF">
			                        <tbody>
			                          <tr style="font-family: Verdana; color: #000; font-size: 16px;">
			                            <td style="padding-left: 30px; padding-right: 5px;" width="60%">
			                              <p style="margin: 0; padding-bottom: 12px;font-weight: bold; font-size: 18px;">Need help?</p>
			                              <p style="margin: 0; padding: 12px 0;">Our support team is here to help you personalise your experience or answer any questions.</p>
			                              <p style="margin: 0; padding: 12px 0; font-weight: bold;">
			                                <img style="vertical-align: middle;" width="17.5px" height="14px" src="'.site_url().'/wp-content/uploads/2025/09/email-Icon.png"> <a href="mailto:email@giftcardsplus.com.au" style="vertical-align: middle; color:#000; text-decoration:none;">email@giftcardsplus.com.au</a>
			                              </p>
			                            </td>
			                            <td style="padding-right: 30px;" width="40%">
			                              <img width="197px" height="190px" src="'.site_url().'/wp-content/uploads/2025/09/Circle.png">
			                            </td>
			                          </tr>
			                        </tbody>
			                      </table>

			                      <table id="footer" style="padding: 32px 0; width: 100%;" border="0" cellspacing="0" cellpadding="0" bgcolor="#000">
			                        <tbody>
			                          <tr style="font-family: Verdana; color: #fff; font-size: 14px;">
			                            <td style="padding-left: 30px; padding-right: 5px;">
			                              <p style="margin: 0;">©'.date('Y').' giftcardsplus Pty Ltd. All Rights Reserved.</p>
			                              <p style="margin: 0; padding-top: 12px;">Powered by J&C</p>
			                            </td>
			                            <td width="10%">
			                              <img width="27px" height="27px" src="'.site_url().'/wp-content/uploads/2025/09/instagram-icon.png">
			                            </td>
			                            <td style="padding-right: 30px;" width="10%">
			                              <img width="27px" height="27px" src="'.site_url().'/wp-content/uploads/2025/09/linkedin-icon.png">
			                            </td>
			                          </tr>
			                        </tbody>
			                      </table>
			                    </div>
			                  </td>
			                </tr>
			              </tbody>
			            </table>
			            </center></td>
			        </tr>
			      </tbody>
			    </table>
			  </body>
			</html>';

	        } // end else (no et_tpl)

	        $headers = [
	            'Content-Type: text/html; charset=UTF-8',
	            'From: ' . esc_html($sender_name) . ' <' . sanitize_email($sender_email) . '>',
	        ];

	        // Final check before sending email
	        $final_image_check = isset($gcard['image_url']) && !empty($gcard['image_url']) && trim($gcard['image_url']) !== '';

			// Commented on 20251217
	        // $logger->info(
	        // 	"📧 Final email send check - recipient: " . (isset($gcard['recipient_email']) ? $gcard['recipient_email'] : 'NOT SET') .
	        // 	", image_url in gcard: " . (isset($gcard['image_url']) ? var_export($gcard['image_url'], true) : 'NOT SET') .
	        // 	", image will be included: " . ($final_image_check ? 'YES' : 'NO'),
	        // 	$context
	        // );

	        // Prepare SMS message if needed
	        $sms_sent = false;
	        $email_sent = false;
	        
	        // Send SMS if SMS is selected (SMS only or Email+SMS)
	        if (($is_sms_only || $is_email_sms) && !empty($recipient_phone)) {
	            // Build SMS message
	            $recipient_name  = isset($gcard['name']) && !empty($gcard['name']) ? $gcard['name'] : (isset($gcard['recipient_name']) ? $gcard['recipient_name'] : 'there');
	            $gift_card_name  = isset($gcard['gift_card_name']) && !empty($gcard['gift_card_name']) ? $gcard['gift_card_name'] : (isset($gcard['card_name']) ? $gcard['card_name'] : 'Gift Card');
	            $sender_name_sms = isset($gcard['sender_name']) && !empty($gcard['sender_name']) ? $gcard['sender_name'] : 'Gift Cards Plus';
	            $gc_post_id_sms  = isset($gcard['gift_card_post_id']) ? (int) $gcard['gift_card_post_id'] : 0;

	            // Build wallet link (token-based, same as send_instant_gift_card_sms)
	            $sms_wallet_link = '';
	            if (!empty($gc_post_id_sms) && !empty($gcard['recipient_email'])) {
	                $sms_payload     = base64_encode(wp_json_encode(['gc_id' => $gc_post_id_sms, 'email' => $gcard['recipient_email']]));
	                $sms_signature   = hash_hmac('sha256', $sms_payload, wp_salt('auth'));
	                $sms_token       = $sms_payload . '.' . $sms_signature;
	                $sms_wallet_link = add_query_arg(['action' => 'gcp_add_to_wallet', 'token' => rawurlencode($sms_token)], home_url('/'));
	                if (function_exists('gc_shorten_url_for_sms')) {
	                    $sms_wallet_link = gc_shorten_url_for_sms($sms_wallet_link);
	                }
	            }

	            // Get wallet security code
	            $wallet_code_sms = get_post_meta($gc_post_id_sms, 'gcard_security_pin', true)
	                ?: get_post_meta($gc_post_id_sms, '_gcard_security_pin', true)
	                ?: get_post_meta($gc_post_id_sms, 'security_pin', true)
	                ?: '1234';

	            $sms_message = "Hi {$recipient_name}, you've got a {$gift_card_name} from {$sender_name_sms}! Add it to your giftcards+ wallet: {$sms_wallet_link}. Wallet Code: {$wallet_code_sms}.";

	            /**
	             * DEBUG: exit and print the "product name" (gift card name) used in the SMS.
	             * Trigger by adding `?debug_sms_exit=1` to the request URL while placing the order.
	             * This is intentionally gated so it won't break normal customer orders.
	             */
	            if (isset($_GET['debug_sms_exit']) && $_GET['debug_sms_exit'] == '1' && current_user_can('manage_options')) {
	                wp_die(
	                    "SMS Debug\n\nGift card/product name: {$gift_card_name}\n\nFull SMS:\n{$sms_message}",
	                    'SMS Debug',
	                    ['response' => 200]
	                );
	            }
	            
	            // Send SMS
	            $sms_result = send_sms_via_smsbroadcast($recipient_phone, $sms_message);
	            if ($sms_result && isset($sms_result['success']) && $sms_result['success']) {
	                $sms_sent = true;
	                $logger->info("✅ SMS sent successfully to {$recipient_phone} for gift card #{$gcard['gift_card_post_id']}", $context);
	            } else {
	                $logger->error("❌ SMS failed to send to {$recipient_phone} for gift card #{$gcard['gift_card_post_id']}", $context);
	            }
	        }
	        
	        // Send email only if Email is selected (Email only or Email+SMS)
	        // Skip email if SMS only
	        if (!$is_sms_only) {
	            $email_sent = wp_mail($gcard['recipient_email'], $subject, $email_body, $headers, $attachments);
	            if ($email_sent) {
	                $logger->info("✅ Email sent successfully to {$gcard['recipient_email']} for gift card #{$gcard['gift_card_post_id']}", $context);
	            } else {
	                $logger->error("❌ Email failed to send to {$gcard['recipient_email']} for gift card #{$gcard['gift_card_post_id']}", $context);
	            }
	        }
	        
	        // Update gift card send status
	        if ($is_sms_only) {
	            // SMS only
	            if ($sms_sent) {
	                update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Delivered (SMS)');
	            } else {
	                update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Failed (SMS)');
	            }
	        } elseif ($is_email_sms) {
	            // Email + SMS
	            if ($email_sent && $sms_sent) {
	                update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Delivered (Email+SMS)');
	            } elseif ($email_sent) {
	                update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Delivered (Email only, SMS failed)');
	            } elseif ($sms_sent) {
	                update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Delivered (SMS only, Email failed)');
	            } else {
	                update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Failed (Email+SMS)');
	            }
	        } else {
	            // Email only
	            if ($email_sent) {
	                update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Delivered');
	            } else {
	                update_post_meta($gcard['gift_card_post_id'], '_gift_card_send', 'Failed');
	            }
	        }
	    }
	}
}


// Change "From" name based on order meta for Customer Completed Order email
add_filter( 'woocommerce_email_from_name', function( $from_name, $email ) {
    if ( isset( $email->id ) && 'customer_completed_order' === $email->id && isset( $email->object ) && is_a( $email->object, 'WC_Order' ) ) {
        $order = $email->object;
        $custom_name = $order->get_meta( '_sender_name', true );
        if ( ! empty( $custom_name ) ) {
            return $custom_name;
        }
        
    	$gc_email_templates = get_transient( 'email_templates_wc' );
    	$custom_name = $gc_email_templates['customer_completed_order']['email_sender_name'];
        if ( ! empty( $custom_name ) ) {
            return $custom_name;
        }
    }
    return $from_name;
}, 10, 2 );

// Change "From" email based on order meta for Customer Completed Order email
add_filter( 'woocommerce_email_from_address', function( $from_email, $email ) {
    if ( isset( $email->id ) && 'customer_completed_order' === $email->id && isset( $email->object ) && is_a( $email->object, 'WC_Order' ) ) {
        $order = $email->object;
        $custom_email = $order->get_meta( '_sender_email', true );
        if ( ! empty( $custom_email ) ) {
            return $custom_email;
        }

    	$gc_email_templates = get_transient( 'email_templates_wc' );
    	$custom_email = $gc_email_templates['customer_completed_order']['email_sender_email'];
        if ( ! empty( $custom_email ) ) {
            return $custom_email;
        }
    }
    return $from_email;
}, 10, 2 );
?>