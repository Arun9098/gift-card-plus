<?php
/**
 * Invoice PDF Generation Functions
 * 
 * This file contains optimized functions for generating PDF invoices
 * for WooCommerce orders matching the giftcards plus design.
 * 
 * @package GiftCardsPlus
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Get logo path for invoice
 * 
 * @return string|false Logo file path or false if not found
 */
function get_invoice_logo_path() {
    // Try to get logo from ACF options
    if (function_exists('get_field')) {
        $logo = get_field('logo', 'option');
        if (!empty($logo)) {
            if (is_array($logo) && isset($logo['url'])) {
                $logo_url = $logo['url'];
            } elseif (is_string($logo)) {
                $logo_url = $logo;
            }
            
            if (!empty($logo_url)) {
                $logo_path = str_replace(home_url('/'), ABSPATH, $logo_url);
                if (file_exists($logo_path)) {
                    return $logo_path;
                }
            }
        }
        
        // Try giftcards plus logo field
        $gc_logo = get_field('logo_giftcardplus', 'option');
        if (!empty($gc_logo)) {
            if (is_array($gc_logo) && isset($gc_logo['url'])) {
                $logo_url = $gc_logo['url'];
            } elseif (is_string($gc_logo)) {
                $logo_url = $gc_logo;
            }
            
            if (!empty($logo_url)) {
                $logo_path = str_replace(home_url('/'), ABSPATH, $logo_url);
                if (file_exists($logo_path)) {
                    return $logo_path;
                }
            }
        }
    }
    
    // Try theme directory paths
    $logo_paths = array(
        get_template_directory() . '/assets/images/logo.png',
        get_template_directory() . '/assets/img/logo.png',
        get_template_directory() . '/images/logo.png',
        get_template_directory() . '/logo.png',
        get_template_directory() . '/assets/images/giftcardsplus-logo.png',
        get_template_directory() . '/assets/img/giftcardsplus-logo.png',
    );
    
    foreach ($logo_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return false;
}

/**
 * Get order totals data with optimized calculation
 * 
 * @param WC_Order $order WooCommerce order object
 * @return array Order totals breakdown
 */
function get_invoice_order_totals($order) {
    $order_totals = $order->get_meta('_order_totals_breakdown');
    
    if (!empty($order_totals) && is_array($order_totals)) {
        return $order_totals;
    }
    
    // Calculate fees if not cached
    $fees_total = 0;
    $fees_tax = 0;
    foreach ($order->get_fees() as $fee) {
        $fees_total += floatval($fee->get_total());
        $fees_tax += floatval($fee->get_total_tax());
    }
    
    // Calculate discount tax if method exists, otherwise use 0
    $discount_tax = 0;
    if (method_exists($order, 'get_discount_tax')) {
        $discount_tax = $order->get_discount_tax();
    } elseif (method_exists($order, 'get_discount_total_tax')) {
        $discount_tax = $order->get_discount_total_tax();
    }
    
    return array(
        'subtotal' => $order->get_subtotal(),
        'total' => $order->get_total(),
        'total_tax' => $order->get_total_tax(),
        'shipping_total' => $order->get_shipping_total(),
        'shipping_tax' => $order->get_shipping_tax(),
        'discount_total' => $order->get_discount_total(),
        'discount_tax' => $discount_tax,
        'fee_total' => $fees_total,
        'fee_tax' => $fees_tax,
    );
}

/**
 * Calculate fee breakdowns from order fees
 * 
 * @param WC_Order $order WooCommerce order object
 * @return array Fee breakdown (sms_delivery, fulfillment, processing)
 */
function get_invoice_fee_breakdown($order) {
    $sms_delivery_cost = 0;
    $fulfillment_costs = floatval($order->get_meta('fullfillment_total') ?: 0);
    $card_processing_fee = 0;
    
    foreach ($order->get_fees() as $fee) {
        $fee_name = strtolower($fee->get_name());
        $fee_amount = floatval($fee->get_total());
        
        if (strpos($fee_name, 'sms') !== false || strpos($fee_name, 'delivery') !== false) {
            $sms_delivery_cost += $fee_amount;
        } elseif (strpos($fee_name, 'processing') !== false || strpos($fee_name, 'card') !== false) {
            $card_processing_fee += $fee_amount;
        }
    }
    
    return array(
        'sms_delivery' => $sms_delivery_cost,
        'fulfillment' => $fulfillment_costs,
        'processing' => $card_processing_fee,
    );
}

/**
 * Get customer information from order
 * 
 * @param WC_Order $order WooCommerce order object
 * @return array Customer data
 */
function get_invoice_customer_data($order) {
    $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    
    if (empty($customer_name)) {
        $user = $order->get_user();
        $customer_name = $user ? $user->display_name : 'Guest';
    }
    
    return array(
        'name' => $customer_name,
        'email' => $order->get_billing_email(),
    );
}

/**
 * Get payment information from order
 * 
 * @param WC_Order $order WooCommerce order object
 * @return array Payment data
 */
function get_invoice_payment_data($order) {
    $payment_method = $order->get_payment_method_title();
    
    // Format payment status - show "Paid" if order is completed/processing, otherwise show status
    $order_status = $order->get_status();
    if (in_array($order_status, array('completed', 'processing', 'paid'))) {
        $payment_status = 'Paid';
    } else {
        $payment_status = ucfirst($order_status);
    }
    
    // Get card info
    $card_last4 = $order->get_meta('_card_last4') ?: $order->get_meta('last4') ?: '';
    $card_display = !empty($card_last4) ? 'xxxxxxxx' . substr($card_last4, -4) : '';
    
    // Get payment date
    $date_paid = $order->get_date_paid();
    $order_date = $order->get_date_created();
    $payment_date = $date_paid ? $date_paid->date('d/m/Y') : ($order_date ? $order_date->date('d/m/Y') : date('d/m/Y'));
    
    return array(
        'method' => $payment_method,
        'status' => $payment_status,
        'card_display' => $card_display,
        'date' => $payment_date,
    );
}

/**
 * Render PDF header section
 * 
 * @param TCPDF $pdf PDF object
 * @param float $y Current Y position
 * @return float New Y position
 */
function render_invoice_header($pdf, $y) {
    // Company information constants
    // $company_info = array(
    //     'name' => 'giftcards plus',
    //     'full_name' => 'gift card Plus PTY LTD',
    //     'address_line1' => '502, 77 Dunning Avenue',
    //     'address_line2' => 'Rosebery, NSW, 2018',
    //     'abn' => 'ABN: 82 667 618 868',
    // );
    
    // Logo in top right corner
    $logo_file = get_invoice_logo_path();
    $logo_size = 12; // Height in mm
    $logo_x = 195; // Right side
    
    // If logo exists, add it to top right
    if (!empty($logo_file) && file_exists($logo_file)) {
        list($img_w, $img_h) = @getimagesize($logo_file);
        if ($img_h > 0 && is_numeric($img_w) && is_numeric($img_h)) {
            $logo_width = ($img_w / $img_h) * $logo_size;
            $logo_x = 195 - $logo_width; // Position from right
            $pdf->Image($logo_file, $logo_x, $y, $logo_width, $logo_size, '', '', '', false);
        }
    }
    
    // Left: Invoice title
    $pdf->SetFont('helvetica', 'B', 24);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(15, $y);
    $pdf->Cell(60, 10, 'Invoice', 0, 0, 'L');
    
    // Right: Company info (right-aligned)
    $right_x = 110;
    $right_width = 85;
    $line_height = 4;
    $header_start_y = $y;
    
    // Company name in pink (below logo or at same level)
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(255, 105, 180); // Pink color
    $company_name_y = $header_start_y + $logo_size + 2; // Below logo
    $pdf->SetXY($right_x, $company_name_y);
    // $pdf->Cell($right_width, $line_height, $company_info['name'], 0, 1, 'R');
    
    // // Full company name in black
    // $pdf->SetTextColor(0, 0, 0);
    // $pdf->SetXY($right_x, $company_name_y + $line_height);
    // $pdf->Cell($right_width, $line_height, $company_info['full_name'], 0, 1, 'R');
    
    // // Address line 1
    // $pdf->SetXY($right_x, $company_name_y + ($line_height * 2));
    // $pdf->Cell($right_width, $line_height, $company_info['address_line1'], 0, 1, 'R');
    
    // // Address line 2
    // $pdf->SetXY($right_x, $company_name_y + ($line_height * 3));
    // $pdf->Cell($right_width, $line_height, $company_info['address_line2'], 0, 1, 'R');
    
    // // ABN
    // $pdf->SetXY($right_x, $company_name_y + ($line_height * 4));
    // $pdf->Cell($right_width, $line_height, $company_info['abn'], 0, 1, 'R');
    
    // Return Y position after header (company info section)
    return $company_name_y + ($line_height * 5) + 8;
}

/**
 * Render customer and order information section
 * 
 * @param TCPDF $pdf PDF object
 * @param WC_Order $order WooCommerce order object
 * @param array $customer_data Customer information
 * @param float $y Current Y position
 * @return float New Y position
 */
function render_invoice_customer_info($pdf, $order, $customer_data, $y) {
    $invoice_number = $order->get_meta('_invoice_number') ?: $order->get_order_number();
    $order_number = $order->get_order_number();
    $order_date = $order->get_date_created() ? $order->get_date_created()->date('d/m/Y') : date('d/m/Y');
    $po_number = $order->get_meta('_po_number') ?: '';
    $client_reference = $order->get_meta('_client_reference') ?: '';
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    
    // Company information for right column (same as header)
    $company_info = array(
        'full_name' => 'Gift Cards Plus PTY LTD',
        'address_line1' => '502, 77 Dunning Avenue',
        'address_line2' => 'Rosebery, NSW, 2018',
        'abn' => 'ABN: 82 667 618 868',
    );
    
    // Left column - Customer info
    $left_x = 15;
    $line_height = 5;
    
    // SPACING CONTROL: Adjust these values to control the space between labels ("To:", "Email:") and their values
    // Value is in millimeters (mm).
    // - Decrease label_width to reduce space between label and value (make them closer)
    // - Increase label_width to add more space between label and value (move them apart)
    $label_width_to_email = 20; // Width for "To:" and "Email:" labels (default: 20mm, was 40mm)
    $value_width_to_email = 60; // Width for customer name and email values
    
    // To: (with bold name)
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($left_x, $y);
    $pdf->Cell($label_width_to_email, $line_height, 'To:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($value_width_to_email, $line_height, $customer_data['name'], 0, 1, 'L');
    
    // Email:
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($left_x, $y + $line_height);
    $pdf->Cell($label_width_to_email, $line_height, 'Email:', 0, 0, 'L');
    $pdf->Cell($value_width_to_email, $line_height, $customer_data['email'], 0, 1, 'L');
    
    // Right column - Company address info (aligned with To/Email, positioned on right side with left text alignment)
    // Calculate right edge position (195mm is page width - 15mm margin)
    $page_width = 195;
    $right_x = $page_width - 55; // 85mm width from right edge - positions address on right side
    $right_width = 85;
    
    // Full company name (left-aligned text, positioned on right side, aligned with "To:")
    // Cell with left alignment - when text wraps, wrapped lines start from same left position
    $pdf->SetXY($right_x, $y);
    $pdf->Cell($right_width, $line_height, $company_info['full_name'], 0, 1, 'L', false, '', 0, false, 'T', 'M');
    
    // Address line 1 (left-aligned text, positioned on right side, aligned with "Email:")
    $pdf->SetXY($right_x, $y + $line_height);
    $pdf->Cell($right_width, $line_height, $company_info['address_line1'], 0, 1, 'L', false, '', 0, false, 'T', 'M');
    
    // Address line 2
    $pdf->SetXY($right_x, $y + ($line_height * 2));
    $pdf->Cell($right_width, $line_height, $company_info['address_line2'], 0, 1, 'L', false, '', 0, false, 'T', 'M');
    
    // ABN
    $pdf->SetXY($right_x, $y + ($line_height * 3));
    $pdf->Cell($right_width, $line_height, $company_info['abn'], 0, 1, 'L', false, '', 0, false, 'T', 'M');
    
    // Calculate the Y position after both columns
    // Company address section has 4 lines, ensure invoice details start BELOW it
    $customer_section_height = $line_height * 2; // To and Email (2 lines)
    $address_section_height = $line_height * 4; // Company info (4 lines)
    $max_section_height = max($customer_section_height, $address_section_height);
    
    // SPACING CONTROL: Adjust this value to change the space between company address and invoice details section
    // Value is in millimeters (mm). Increase for more space, decrease for less space.
    $spacing_before_invoice_details = 10; // Default: 15mm spacing
    
    // Start invoice section well below the address section
    $invoice_info_start_y = $y + $max_section_height + $spacing_before_invoice_details;
    
    // Invoice/Order info section - positioned BELOW both To/Email and address
    // Two-column layout: Left (Invoice number, Order number, PO Number) and Right (Client reference, Order date)
    // Labels are bold and on their own line, values appear below labels
    
    // SPACING CONTROL: Adjust this value to change the left space/margin before "Client reference" and "Order date" labels
    // Value is in millimeters (mm). 
    // - Increase to move "Client reference" further right (more space from left edge)
    // - Decrease to move "Client reference" closer to left (less space from left edge)
    // - Negative values will move it to the left of the address column
    // Default: 15mm (adds 15mm space from the address column position)
    // TO GIVE MORE SPACE: Increase this number (e.g., 25, 30, 35, etc.)
    $client_reference_left_spacing = 0; // Adjust this to control space before "Client reference" label
    
    // Right column X position (calculated from address column position + spacing)
    $right_column_x = $right_x + $client_reference_left_spacing; // X position for Client reference and Order date
    $right_column_width = $right_width; // Same width as address column (85)
    $label_width = 80;
    $value_width = 80;
    
    // Left column - Invoice number (bold label, value below)
    // --- Horizontal divider above Invoice details ---
    $line_y = $invoice_info_start_y - 4; // small gap above labels
    $pdf->SetDrawColor(220, 220, 220); // light grey
    $pdf->SetLineWidth(0.3);
    $pdf->Line($left_x, $line_y, 190, $line_y);

    // -------------------------------
    // Invoice / Order info (2-column aligned to margins)
    // -------------------------------
    $line_height = 5;
    $block_gap   = 6;

    $left_x = $pdf->getMargins()['left'];

    $right_col_width = 85;
    $page_width = 195;
    $right_x = $page_width - 55;


    // Optional divider above invoice info
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->SetLineWidth(0.3);
    $pdf->Line($left_x, $invoice_info_start_y - 4, $page_width - $right_margin, $invoice_info_start_y - 4);

    // Row 1 — Labels
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY($left_x, $invoice_info_start_y);
    $pdf->Cell(80, $line_height, 'Invoice number', 0, 0, 'L');

    $pdf->SetXY($right_x, $invoice_info_start_y);
    $pdf->Cell($right_col_width, $line_height, 'Client reference', 0, 1, 'L');

    // Row 2 — Values
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($left_x, $invoice_info_start_y + $line_height);
    $pdf->Cell(80, $line_height, $invoice_number, 0, 0, 'L');

    $pdf->SetXY($right_x, $invoice_info_start_y + $line_height);
    $pdf->Cell($right_col_width, $line_height, $client_reference ?: '-', 0, 1, 'L');

    // Space below first value block
    $current_y = $invoice_info_start_y + ($line_height * 2) + $block_gap;

    // Row 3 — Labels
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY($left_x, $current_y);
    $pdf->Cell(80, $line_height, 'Order number', 0, 0, 'L');

    $pdf->SetXY($right_x, $current_y);
    $pdf->Cell($right_col_width, $line_height, 'Order date', 0, 1, 'L');

    // Row 4 — Values
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($left_x, $current_y + $line_height);
    $pdf->Cell(80, $line_height, $order_number, 0, 0, 'L');

    $pdf->SetXY($right_x, $current_y + $line_height);
    $pdf->Cell($right_col_width, $line_height, $order_date, 0, 1, 'L');

    // Space below second value block
    $current_y += ($line_height * 2) + $block_gap;

    // Row 5 — PO Number (left only, full width feel)
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY($left_x, $current_y);
    $pdf->Cell(80, $line_height, 'PO number', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($left_x, $current_y + $line_height);
    $pdf->Cell(80, $line_height, $po_number ?: '-', 0, 1, 'L');

    // Final spacing before Order Details
    return $current_y + ($line_height * 2) + 12;


    
    // // Return Y position after invoice info section (left column has 6 lines: 3 labels + 3 values)
    // $invoice_section_height = $line_height * 6;
    
    // return $invoice_info_start_y + $invoice_section_height + 5;
}

/**
 * Render order details table
 * 
 * @param TCPDF $pdf PDF object
 * @param WC_Order $order WooCommerce order object
 * @param float $y Current Y position
 * @return float New Y position
 */
function render_invoice_order_details($pdf, $order, $y) {
    $left_margin = 19;
    $page_width  = 210;
    $content_width = $page_width - ($left_margin * 2);

    // Section title
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY($left_margin, $y);
    $pdf->Cell($content_width, 6, 'Order Details', 0, 1, 'L');

    $y += 6;

    $y += 4;

    // Table headers
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY($left_margin, $y);
    $pdf->Cell(72, 5, 'Gift Card', 0, 0, 'L');
    $pdf->Cell(20, 5, 'Quantity', 0, 0, 'C');
    $pdf->Cell(50, 5, 'SKU', 0, 0, 'C');
    $pdf->Cell(30, 5, 'Price', 0, 1, 'R');

    $y += 6;

    // Row separator
    $pdf->SetDrawColor(230, 230, 230);
    $pdf->Line($left_margin, $y, 190, $y);

    $y += 3;

    // Items
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $name = $item->get_name();
        $sku  = $product ? $product->get_sku() : '';
        $qty  = $item->get_quantity();
        $total = $item->get_total();

        // Product name
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($left_margin, $y);
        $pdf->Cell(72, 5, $name, 0, 0, 'L');

        // Qty
        $pdf->Cell(20, 5, $qty, 0, 0, 'C');

        // sku
        $pdf->Cell(50, 5, $sku, 0, 0, 'C');

        // Price
        $pdf->Cell(30, 5, '$' . number_format($total, 2), 0, 1, 'R');

        $y += 5;

        // Divider between rows
        $pdf->SetDrawColor(235, 235, 235);
        $pdf->Line($left_margin, $y, 190, $y);
        $y += 4;
    }

    return $y + 6;
}


/**
 * Render summary section (right-aligned)
 * 
 * @param TCPDF $pdf PDF object
 * @param array $totals Order totals
 * @param array $fees Fee breakdown
 * @param float $y Current Y position
 * @return float New Y position (same Y, just for consistency)
 */
function render_invoice_summary($pdf, $totals, $fees, $y) {
    $right_margin = 19;
    $page_width   = 210;

    $label_x = 120;
    $value_x = $page_width - $right_margin;
    $line_h  = 6;

    $pdf->SetFont('helvetica', '', 9);

    $rows = [
        ['Subtotal', $totals['subtotal']],
        ['Discount', -$totals['discount_total']],
        ['SMS Delivery Cost', $fees['sms_delivery']],
        ['Fulfilment costs', $fees['fulfillment']],
        ['Card Processing Fee', $fees['processing']],
    ];

    foreach ($rows as $row) {

        // Label
        $pdf->SetXY($label_x, $y);
        $pdf->Cell(55, $line_h, $row[0], 0, 0, 'L');

        // Value
        $pdf->SetXY($value_x - 35, $y);
        $pdf->Cell(35, $line_h, '$' . number_format($row[1], 2), 0, 1, 'R');

        // Divider line (same style as SKU/Price)
        $pdf->SetDrawColor(235, 235, 235);
        $pdf->Line($label_x, $y + $line_h + 1, $page_width - $right_margin, $y + $line_h + 1);

        $y += $line_h + 3;
    }

    // // TOTAL (bold + stronger divider)
    // $pdf->SetDrawColor(200, 200, 200);
    // $pdf->Line($label_x, $y + 1, $page_width - $right_margin, $y + 1);

    $y += 4;

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY($label_x, $y);
    $pdf->Cell(55, 7, 'TOTAL', 0, 0, 'L');

    $pdf->SetXY($value_x - 35, $y);
    $pdf->Cell(35, 7, '$' . number_format($totals['total'], 2), 0, 1, 'R');

    return $y + 10;
}



/**
 * Render payment status section (left-aligned, positioned at same Y as summary)
 * 
 * @param TCPDF $pdf PDF object
 * @param array $payment_data Payment information
 * @param float $y Current Y position (should match summary start Y)
 * @return float New Y position
 */
function render_invoice_payment_status($pdf, $payment_data, $y) {
    
    // Use margin from page settings (19mm)
    $left_margin = 19;
    $line_height = 5;
    
    // Payment Status - label and value both on same line
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($left_margin, $y);
    $pdf->Cell(50, $line_height, 'Payment Status:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(50, $line_height, $payment_data['status'], 0, 1, 'L');
    
    // Payment method
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY($left_margin, $y + $line_height);
    $pdf->Cell(50, $line_height, 'Payment method:', 0, 0, 'L');
    $pdf->Cell(50, $line_height, $payment_data['method'], 0, 1, 'L');
    
    // Card Number
    $pdf->SetXY($left_margin, $y + ($line_height * 2));
    $pdf->Cell(50, $line_height, 'Card Number:', 0, 0, 'L');
    $pdf->Cell(50, $line_height, $payment_data['card_display'] ?: 'xxxxxxxxxxx', 0, 1, 'L');
    
    // Date
    $pdf->SetXY($left_margin, $y + ($line_height * 3));
    $pdf->Cell(50, $line_height, 'Date:', 0, 0, 'L');
    $pdf->Cell(50, $line_height, $payment_data['date'], 0, 1, 'L');
    
    // Return Y position after all payment info (should match summary end Y)
    return $y + ($line_height * 4);
}

/**
 * Render footer section with black background
 * 
 * @param TCPDF $pdf PDF object
 * @param float $page_height Page height in mm
 * @return void
 */
function render_invoice_footer($pdf, $page_height) {
    $footer_height = 40; // Height of footer section
    $footer_y = $page_height - $footer_height;

    // Draw black background for footer
    $pdf->SetFillColor(30, 30, 30); // Dark gray/black background
    $pdf->Rect(0, $footer_y, 210, $footer_height, 'F');

    // Left side: Logo
    $left_x = 15;
    $footer_center_y = $footer_y + ($footer_height / 2) - 3;

    $logo_file = get_template_directory() . '/assets/images/White-Pink.png';
    if (!empty($logo_file) && file_exists($logo_file)) {
        $logo_height = 8;
        list($img_w, $img_h) = @getimagesize($logo_file);
        if ($img_h > 0) {
            $logo_width = ($img_w / $img_h) * $logo_height;
            $pdf->Image($logo_file, $left_x, $footer_center_y - 2, $logo_width, $logo_height, '', '', '', false);
        }
    } else {
        // Text logo fallback: "giftcards" in white, "plus" in pink
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($left_x, $footer_center_y);
        $pdf->Cell(55, 6, 'giftcards', 0, 0, 'L');

        $pdf->SetTextColor(236, 0, 140); // Pink color for "plus"
        $pdf->Cell(20, 6, 'plus', 0, 1, 'L');

        // TM symbol
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($left_x + 42, $footer_center_y + 2);
        $pdf->Cell(5, 3, 'TM', 0, 0, 'L');
    }

    // Right side: Contact info (aligned to right)
    $right_x = 195; // Right margin
    $line_height = 5;
    $start_y = $footer_y + 8;

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(255, 255, 255);

    // Website URL
    $pdf->SetXY($right_x - 80, $start_y);
    $pdf->Cell(80, $line_height, 'giftcardsplus.com.au', 0, 1, 'R', false, 'https://giftcardsplus.com.au');

    // Contact us: (bold label) + email
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY($right_x - 80, $start_y + $line_height + 2);
    $pdf->Cell(30, $line_height, 'Contact us:', 0, 0, 'R');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, $line_height, 'Support@giftcardsplus.com.au', 0, 1, 'R', false, 'mailto:Support@giftcardsplus.com.au');

    // Instagram icon and handle
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY($right_x - 80, $start_y + ($line_height * 2) + 4);
    // Instagram icon (simple circle representation using 'O' or custom icon)
    $pdf->SetXY($right_x - 40, $start_y + ($line_height * 2) + 4);
    $pdf->Cell(8, $line_height, '', 0, 0, 'R'); // Space for icon
    $pdf->Cell(32, $line_height, '@giftcardsplus', 0, 1, 'R', false, 'https://www.instagram.com/giftcardsplus/');
}

/**
 * Generate PDF Invoice for order
 * Optimized version with separated rendering functions
 * 
 * @param WC_Order $order The WooCommerce order object
 * @return string PDF content as string (for direct output)
 */
function generate_order_invoice_pdf($order) {
    // Load TCPDF only when needed
    if (!class_exists('TCPDF')) {
        require_once(get_template_directory() . '/tcpdf/tcpdf.php');
    }
    
    // Clean output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $invoice_number = $order->get_meta('_invoice_number') ?: $order->get_order_number();
    $pdf->SetCreator('Gift Cards Plus');
    $pdf->SetAuthor('Gift Cards Plus');
    $pdf->SetTitle('Invoice - ' . $invoice_number);
    $pdf->SetSubject('Invoice');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins - Figma: 72px padding = ~19mm
    $margin = 19; // 72px = ~19mm
    $pdf->SetMargins($margin, $margin, $margin);
    
    // DISABLE auto page break to prevent multiple pages
    // We'll manually control content positioning to fit on one page
    $pdf->SetAutoPageBreak(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Get page dimensions
    $page_height = $pdf->getPageHeight();
    $page_width = $pdf->getPageWidth();
    
    // Get all data once
    $customer_data = get_invoice_customer_data($order);
    $payment_data = get_invoice_payment_data($order);
    $order_totals = get_invoice_order_totals($order);
    $fee_breakdown = get_invoice_fee_breakdown($order);
    
    // Render sections - start at margin
    $y = $margin;
    $y = render_invoice_header($pdf, $y);
    $y = render_invoice_customer_info($pdf, $order, $customer_data, $y);
    $y = render_invoice_order_details($pdf, $order, $y);
    
    // Render summary and payment status side-by-side at the same Y position
    // $summary_start_y = $y;
    // $summary_end_y = render_invoice_summary($pdf, $order_totals, $fee_breakdown, $summary_start_y);
    // render_invoice_payment_status($pdf, $payment_data, $summary_start_y);
    
    // // Use the higher Y position for footer calculation
    // $y = max($summary_end_y, $summary_start_y + 20);
    
    $summary_start_y = $y;
    // First render summary (TOTAL etc.)
    $summary_end_y = render_invoice_summary($pdf, $order_totals, $fee_breakdown, $summary_start_y);
    // THEN render payment BELOW summary
    $payment_start_y = $summary_end_y + 6; // spacing after total
    render_invoice_payment_status($pdf, $payment_data, $payment_start_y);
    $y = $payment_start_y + 10; // move forward for footer safety
    // Render footer at bottom of page
    render_invoice_footer($pdf, $page_height + 10);
    
    // Output PDF as string
    return $pdf->Output('', 'S');
}

/**
 * AJAX handler for downloading invoice PDF
 * 
 * @return void
 */
function download_invoice_pdf_callback() {
    gcp_require_admin_ajax();
    // Validate request
    if (!isset($_GET['order_id'])) {
        wp_die('Invalid request.');
    }
    
    $order_id = intval($_GET['order_id']);
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_die('Order not found.');
    }
    
    // Check permissions
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to download invoices.');
    }
    
    $current_user_id = get_current_user_id();
    $order_customer_id = $order->get_customer_id();
    
    // Allow if user is admin, shop manager, or owns the order
    if (!current_user_can('manage_woocommerce') && $current_user_id != $order_customer_id) {
        wp_die('You do not have permission to download this invoice.');
    }
    
    // Generate PDF
    try {
        $pdf_content = generate_order_invoice_pdf($order);
        
        // Get invoice number for filename
        $invoice_number = $order->get_meta('_invoice_number') ?: $order->get_order_number();
        $filename = 'invoice-' . sanitize_file_name($invoice_number) . '.pdf';
        
        // Check if preview mode is requested (add ?preview=1 to URL for preview)
        $is_preview = isset($_GET['preview']) && $_GET['preview'] == '1';
        
        // Output PDF headers
        header('Content-Type: application/pdf');
        
        // Use 'inline' for preview (displays in browser) or 'attachment' for download
        $disposition = $is_preview ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf_content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        echo $pdf_content;
        exit;
    } catch (Exception $e) {
        wp_die('Error generating invoice. Please try again later.');
    }
}

