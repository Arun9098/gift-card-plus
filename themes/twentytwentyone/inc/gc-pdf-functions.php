<?php
require_once(get_template_directory().'/tcpdf/tcpdf.php');
require_once(get_template_directory().'/tcpdf/tcpdf_barcodes_1d.php');
require_once(get_template_directory().'/tcpdf/tcpdf_barcodes_2d.php');

// WB-3.12: Return the private (non-web-accessible) PDF storage directory.
// Creates the directory on first use and writes deny-all .htaccess + index.php guards.
// On WP Engine (Nginx) an Access Rule for this path must also be configured.
function gcp_private_pdf_dir() {
    $dir = wp_upload_dir()['basedir'] . '/gcp-private';
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
        // Deny direct HTTP access on Apache environments.
        file_put_contents( $dir . '/.htaccess', "Order deny,allow\nDeny from all\n" );
        // Prevent directory listing fallback.
        file_put_contents( $dir . '/index.php', '<?php // silence' );
    }
    return $dir;
}

/**
 * Convert a BHN eGift URL to PDF using Render.com Puppeteer server (headless Chrome).
 * Required in wp-config.php: define('BHN_PDF_SERVER_URL', 'https://giftcardsplus.onrender.com/convert');
 */
function bhn_convert_url_to_pdf($url, $logger, $context) {
    if (!defined('BHN_PDF_SERVER_URL') || empty(BHN_PDF_SERVER_URL)) {
        $logger->warning("BHN_PDF_SERVER_URL not defined in wp-config.php.", $context);
        return null;
    }

    $logger->info("Sending BHN eGift URL to PDF server: {$url}", $context);

    $response = wp_remote_post(BHN_PDF_SERVER_URL, [
        'timeout' => 90,
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['url' => $url]),
    ]);

    if (is_wp_error($response)) {
        $logger->warning("PDF server request failed: " . $response->get_error_message(), $context);
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        $logger->warning("PDF server returned HTTP {$status_code}: " . wp_remote_retrieve_body($response), $context);
        return null;
    }

    $pdf_body = wp_remote_retrieve_body($response);
    if (empty($pdf_body) || substr($pdf_body, 0, 5) !== '%PDF-') {
        $logger->warning("PDF server response is not a valid PDF.", $context);
        return null;
    }

    $pdf_path = gcp_private_pdf_dir() . '/bhn-egift-' . bin2hex(random_bytes(16)) . '.pdf';
    if (file_put_contents($pdf_path, $pdf_body) === false) {
        $logger->warning("Failed to save PDF: {$pdf_path}", $context);
        return null;
    }

    $logger->info("PDF saved successfully: {$pdf_path}", $context);
    return $pdf_path;
}

function send_giftcard_email_with_pdf($data, $recipient_email, $order_id = null, $attachments = []) {
    $logger  = wc_get_logger();
    $context = ['source' => 'SEND-EMAIL'];
    $upload_dir = wp_upload_dir();
    $base_dir   = $upload_dir['basedir'];
    $temp_files = [];

    // $attachments = [];

    // Determine whether to attach a PDF based on product meta 'is_it_gift_card_plus_product'.
    // 'Yes' → product is a GiftcardsPlus card; no PDF sent.
    // 'No'  → third-party brand card; PDF is sent as normal.
    $_send_pdf    = true;
    $_opd         = !empty($data['order_product_data']) ? @unserialize($data['order_product_data']) : [];
    $_chk_prod_id = !empty($_opd[0]['product_id']) ? (int) $_opd[0]['product_id'] : 0;
    if (!$_chk_prod_id && !empty($data['gift_card_sku'])) {
        $_chk_prod_id = (int) wc_get_product_id_by_sku($data['gift_card_sku']);
    }
    if ($_chk_prod_id) {
        $_gc_plus_meta = get_post_meta($_chk_prod_id, 'is_it_gift_card_plus_product', true);
        // Meta '1' / 'true' / 1  → GiftcardsPlus product → skip PDF
        // Meta '0' / 'false' / 0 → third-party product   → send PDF
        if ($_gc_plus_meta === '1' || $_gc_plus_meta === 'true' || $_gc_plus_meta == 1) {
            $_send_pdf = false;
            $logger->info("Product {$_chk_prod_id}: is_it_gift_card_plus_product={$_gc_plus_meta} (Yes) — PDF attachment skipped.", $context);
        } else {
            $logger->info("Product {$_chk_prod_id}: is_it_gift_card_plus_product={$_gc_plus_meta} (No) — PDF will be attached.", $context);
        }
    }

    // Brands that require the BHN eGift URL to be converted to PDF as attachment
    $egift_pdf_brands = [
        'david jones',
        'shell',
        'woolworths',
        'google',
        'myer',
        'apple',
        'bunnings',
        'xbox',
        'minecraft',
    ];

    $brand_name = strtolower(trim($data['brand_name'] ?? ''));
    $egift_url  = trim($data['url'] ?? '');

    // Check if brand matches any in the list (case-insensitive, Woolworths catches all sub-brands)
    $is_egift_brand = false;
    $matched_brand  = '';
    foreach ($egift_pdf_brands as $check_brand) {
        if (!empty($brand_name) && strpos($brand_name, $check_brand) !== false) {
            $is_egift_brand = true;
            $matched_brand  = $check_brand;
            break;
        }
    }

    $logger->info("Brand check — brand_name: '{$brand_name}' | matched: " . ($is_egift_brand ? "YES ({$matched_brand})" : 'NO') . " | url: " . ($egift_url ?: '(empty)'), $context);

    if ($_send_pdf) {
        if ($is_egift_brand && !empty($egift_url) && filter_var($egift_url, FILTER_VALIDATE_URL)) {
            $logger->info("Brand '{$brand_name}' matched — converting BHN eGift URL to PDF.", $context);
            $converted_pdf = bhn_convert_url_to_pdf($egift_url, $logger, $context);
            if ($converted_pdf) {
                $attachments[] = $converted_pdf;
                $temp_files[]  = $converted_pdf;
                $logger->info("eGift URL PDF attached for brand '{$brand_name}': {$converted_pdf}", $context);
            } else {
                $logger->warning("eGift URL PDF conversion failed for brand '{$brand_name}' — falling back to generated PDF.", $context);
            }
        } elseif ($is_egift_brand && empty($egift_url)) {
            $logger->warning("Brand '{$brand_name}' matched but BHN eGift URL is empty — falling back to generated PDF. Re-test with a new order.", $context);
        } else {
            $logger->info("Brand '{$brand_name}' — using default generated gift card PDF.", $context);
        }

        // If no eGift PDF was used, generate default gift card PDF
        if (empty($attachments)) {
            $real_filename = 'giftcard-' . bin2hex(random_bytes(16)) . '.pdf';
            $real_path = gcp_private_pdf_dir() . '/' . $real_filename;

            $logger->info("PKG Creating PDF at: {$real_path}", $context);

            try {
                generate_giftcard_pdf($data, $real_path);
            } catch (Exception $e) {
                $logger->error("PDF Generation Error: " . $e->getMessage(), $context);
                $real_path = null;
            } catch (Error $e) {
                $logger->error("PDF Generation Fatal Error: " . $e->getMessage(), $context);
                $real_path = null;
            }

            if (!empty($real_path) && file_exists($real_path)) {
                $attachments[] = $real_path;
                $temp_files[]  = $real_path;
            } else {
                $logger->warning("PDF was not created, sending email without attachment", $context);
            }
        }
    }

    // Add video message as attachment if present
    $video_url = isset($data['video_attachment_url']) ? trim($data['video_attachment_url']) : '';
    if (!empty($video_url) && (strpos($video_url, 'http://') === 0 || strpos($video_url, 'https://') === 0)) {
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $base_dir = $upload_dir['basedir'];
        if (strpos($video_url, $base_url) === 0) {
            $relative = str_replace($base_url . '/', '', $video_url);
            $video_path = $base_dir . '/' . $relative;
            // Also try URL-decoded path in case of encoded characters
            if (!file_exists($video_path)) {
                $video_path = $base_dir . '/' . urldecode($relative);
            }
            if (file_exists($video_path) && is_readable($video_path)) {
                $attachments[] = $video_path;
            }
        } else {
            $attach_id = attachment_url_to_postid($video_url);
            if ($attach_id) {
                $video_path = get_attached_file($attach_id);
                if ($video_path && file_exists($video_path) && is_readable($video_path)) {
                    $attachments[] = $video_path;
                }
            }
        }
    }

    // TEMP DEBUG - remove after confirming
    $logger->info('=== ATTACHMENT DEBUG for order ' . $order_id . ' ===', $context);
    $logger->info('video_attachment_url in $data: ' . (isset($data['video_attachment_url']) ? $data['video_attachment_url'] : 'NOT SET'), $context);
    $logger->info('$attachments count: ' . count($attachments), $context);
    foreach ($attachments as $i => $att) {
        $logger->info('  attachment[' . $i . '] = ' . $att . ' | exists: ' . (file_exists($att) ? 'YES' : 'NO'), $context);
    }
    $logger->info('=== END ATTACHMENT DEBUG ===', $context);

send_gift_cards_email_to_recipient($data, $attachments);

    // Delete temp PDFs at the very end of the PHP request — after SendGrid/mail
    // has fully read and encoded the files, regardless of async queueing.
    if ( ! empty( $temp_files ) ) {
        register_shutdown_function( function() use ( $temp_files ) {
            foreach ( $temp_files as $tmp_file ) {
                if ( file_exists( $tmp_file ) ) {
                    @unlink( $tmp_file );
                }
            }
        } );
    }
}


class MyPDF extends TCPDF {
    
    public function AcceptPageBreak() {
        // When page breaks, reset Y position
        $this->SetY(20); // Start content 20mm from top
        return parent::AcceptPageBreak();
    }
}


/**
 * Normalize text for TCPDF — replace Unicode smart quotes, dashes and
 * special characters that Verdana font cannot render (shows as □).
 */
function bhn_normalize_pdf_text($text) {
    $search = [
        "\u{2018}", "\u{2019}", // ' '  left/right single quote
        "\u{201C}", "\u{201D}", // " "  left/right double quote
        "\u{2013}",             // –    en dash
        "\u{2014}",             // —    em dash
        "\u{00A0}",             // non-breaking space
        "\u{2026}",             // …    ellipsis
        "\u{00B7}",             // ·    middle dot
    ];
    $replace = ["'", "'", '"', '"', '-', '--', ' ', '...', '.'];
    return str_replace($search, $replace, $text);
}

function generate_giftcard_pdf($data, $output_path) {
    // Suppress any errors/warnings that might output HTML/XML
    @ini_set('display_errors', 0);
    @error_reporting(0);
    
    // Clean all output buffers → prevents blank PDF and JSON corruption
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start output buffering to catch any errors
    ob_start();


    $order_product_data = unserialize($data['order_product_data']);

    // Resolve brand name: product_brand taxonomy first, fallback to product name.
    $pdf_brand_name = '';
    $_pdf_prod_id   = !empty($order_product_data[0]['product_id']) ? (int) $order_product_data[0]['product_id'] : 0;
    if ( $_pdf_prod_id ) {
        $_brand_terms = wp_get_post_terms( $_pdf_prod_id, 'product_brand' );
        if ( ! is_wp_error( $_brand_terms ) && ! empty( $_brand_terms ) ) {
            $pdf_brand_name = $_brand_terms[0]->name;
        }
    }
    if ( empty( $pdf_brand_name ) ) {
        $pdf_brand_name = ! empty( $order_product_data[0]['product_name'] )
            ? $order_product_data[0]['product_name']
            : 'Gift Card';
    }

    // Read how-to-use and T&C fields early for pre-measurement
    $_allowed = '<b><strong><br><p><ul><ol><li><em><i>';

    $_pre_how_to_use = $_pdf_prod_id ? trim((string) get_post_meta($_pdf_prod_id, 'how_to_use', true)) : '';
    $_pre_how_to_use = strip_tags($_pre_how_to_use, $_allowed);
    $_pre_how_to_use = bhn_normalize_pdf_text(html_entity_decode($_pre_how_to_use, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    $_pre_terms = $_pdf_prod_id ? trim((string) get_post_meta($_pdf_prod_id, 'terms_conditions', true)) : '';
    $_pre_terms_decoded = json_decode($_pre_terms, true);
    if (is_array($_pre_terms_decoded) && !empty($_pre_terms_decoded['text'])) {
        $_pre_terms = $_pre_terms_decoded['text'];
    }
    $_pre_terms = strip_tags($_pre_terms, $_allowed);
    $_pre_terms = bhn_normalize_pdf_text(html_entity_decode($_pre_terms, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    // Pre-calculate banner height so page is exactly tall enough for content
    $banner_font_size_pre = 37.69;
    $banner_line_h_pre    = 16;
    $banner_pad_pre       = 10;
    $max_text_w_pre       = 190;
    $tmp = new TCPDF('P', 'mm', 'A4');
    $tmp->AddFont('verdana', '', 'verdana.php');
    $tmp->AddFont('verdana', 'B', 'verdanab.php');
    $tmp->SetFont('verdana', '', $banner_font_size_pre);
    $rec_lines_pre   = max(1, ceil($tmp->GetStringWidth('You have received a') / $max_text_w_pre));
    $tmp->SetFont('verdana', 'B', $banner_font_size_pre);
    $brand_lines_pre = max(1, ceil($tmp->GetStringWidth($pdf_brand_name . ' Gift Card.') / $max_text_w_pre));

    // Pre-measure actual How to Use section height using a temp page
    $tmp->AddPage();
    $tmp->SetMargins(0, 0, 0, 0);
    $tmp->SetAutoPageBreak(false, 0);
    $tmp_W = $tmp->getPageWidth();
    $_pre_y0 = 0;
    $tmp->SetFont('verdana', 'B', 15);
    $tmp->writeHTMLCell($tmp_W - 44, 0, 22, $_pre_y0 + 4,
        '<span style="color:#EC008C;font-size:15pt;font-family:Verdana;">How to use:</span>',
        0, 1, false, true, 'L', true);
    $_pre_how_to_use_html = !empty($_pre_how_to_use)
        ? '<span style="font-size:8pt;font-family:Verdana;">' . $_pre_how_to_use . '</span>'
        : '<span style="font-size:8pt;font-family:Verdana;"><strong>In Store:</strong> Simply present your gift card at checkout. The amount will be deducted from your total.</span>'
          . '<br><br><span style="font-size:8pt;font-family:Verdana;"><strong>Online:</strong> Enter the gift card number at the payment step during checkout. The balance will be applied automatically.</span>';
    $tmp->SetFont('verdana', '', 8);
    $tmp->writeHTMLCell($tmp_W - 44, 0, 22, $tmp->GetY() + 5, $_pre_how_to_use_html, 0, 1, false, true, 'L', true);
    if (!empty($_pre_terms)) {
        $tmp->SetFont('verdana', 'B', 8);
        $tmp->writeHTMLCell($tmp_W - 44, 0, 22, $tmp->GetY() + 5,
            '<strong style="font-size:8pt;font-family:Verdana;">Terms &amp; Conditions</strong>',
            0, 1, false, true, 'L', true);
        $tmp->SetFont('verdana', '', 8);
        $_pre_terms_html = '<span style="font-size:8pt;font-family:Verdana;">' . $_pre_terms . '</span>';
        $tmp->writeHTMLCell($tmp_W - 44, 0, 22, $tmp->GetY() + 1, $_pre_terms_html, 0, 1, false, true, 'L', true);
    }
    $how_to_use_h_pre = ($tmp->GetY() + 5) - $_pre_y0;

    unset($tmp);
    $banner_h_pre = ($rec_lines_pre + $brand_lines_pre) * $banner_line_h_pre + ($banner_pad_pre * 2);
    // card-gap(8) + card-cursor-offset(76) + pink-gap(6) + how-to-use(dynamic) + support(38) + footer(20)
    $fixed_below  = 8 + 76 + 6 + $how_to_use_h_pre + 38 + 20;
    $page_h       = 38 + $banner_h_pre + $fixed_below; // page fits content exactly

    $pdf = new TCPDF('P', 'mm', [210, $page_h]);
    //$pdf->getAliasNbPages();
    $pdf->AddFont('arial', '', 'arial.php');
    $pdf->AddFont('arial', 'B', 'arialbd.php');

    $pdf->AddFont('verdana', '', 'verdana.php');
    $pdf->AddFont('verdana', 'B', 'verdanab.php');
    $reflect = new ReflectionClass($pdf);
    
    $prop = $reflect->getProperty('fonts');
    $prop->setAccessible(true);


    $pageWidth = $pdf->getPageWidth();
    $rightMargin = 0; // or set as needed
    
    $pdf->SetMargins(0,0,0,0);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->setPrintHeader(false); // Disable automatic headers
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $W = $pdf->getPageWidth();

    $fonts = $prop->getValue($pdf);
    // Commented on 20251218

    // --- 0. WHITE HEADER STRIP: giftcards plus logo centered ---
    $header_h = 38;  // 144px = ~38mm
    $gcplus_header_path = url_to_path($data['logo_giftcardplus']);
    if (!empty($gcplus_header_path) && file_exists($gcplus_header_path) && can_get_image_size($gcplus_header_path)) {
        list($lw, $lh) = @getimagesize($gcplus_header_path);
        if ($lh > 0 && is_numeric($lw) && is_numeric($lh)) {
            $logo_hdr_w = 82.8; // 313px
            $logo_hdr_h = 20.4; // 77px
            $logo_hdr_x = ($W - $logo_hdr_w) / 2;
            $logo_hdr_y = ($header_h - $logo_hdr_h) / 2;
            $pdf->Image($gcplus_header_path, $logo_hdr_x, $logo_hdr_y, $logo_hdr_w, $logo_hdr_h, '', '', '', false);
        }
    }

    // --- 1. MAGENTA HERO BANNER ---
    $banner_y         = $header_h;
    $banner_font_size = 37.69;
    $banner_line_h    = 16;  // mm per text line
    $banner_pad       = 10;  // top/bottom padding inside banner
    $max_text_w       = $W - 20;

    // Pre-calculate lines needed so banner height fits the text
    $pdf->SetFont('verdana', '', $banner_font_size);
    $received_lines = max(1, ceil($pdf->GetStringWidth('You have received a') / $max_text_w));
    $pdf->SetFont('verdana', 'B', $banner_font_size);
    $brand_lines = max(1, ceil($pdf->GetStringWidth($pdf_brand_name . ' Gift Card.') / $max_text_w));
    $banner_h = ($received_lines + $brand_lines) * $banner_line_h + ($banner_pad * 2);

    $pdf->SetFillColor(236, 0, 140);
    $pdf->Rect(0, $banner_y, $W, $banner_h, 'F');

    // Watermark: repeated "giftcards plus" text in semi-transparent white
    $pdf->SetFont('arial', '', 11);
    $pdf->SetAlpha(0.20);
    $pdf->SetTextColor(255, 255, 255);
    for ($wy = $banner_y - 2; $wy < $banner_y + $banner_h; $wy += 11) {
        for ($wx = -5; $wx < $W + 5; $wx += 42) {
            $pdf->SetXY($wx, $wy);
            $pdf->Cell(42, 9, 'giftcards plus', 0, 0, 'L', false);
        }
    }
    $pdf->SetAlpha(1.0);

    // "You have received a" — regular, wraps if needed
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('verdana', '', $banner_font_size);
    $pdf->SetXY(10, $banner_y + $banner_pad);
    $pdf->MultiCell($max_text_w, $banner_line_h, 'You have received a', 0, 'C', false, 1, 10, '', true);

    // Brand name — bold, wraps if needed
    $pdf->SetFont('verdana', 'B', $banner_font_size);
    $pdf->MultiCell($max_text_w, $banner_line_h, $pdf_brand_name . ' Gift Card.', 0, 'C', false, 1, 10, '', true);

    $logos_y = $banner_y + $banner_h;

    // 4. CARD INFO BOX — spacing in px (auto-converted to mm: 1px = 0.3528mm at 96dpi)
    $px = 0.3528;
    $box_pad_left   = 13 * $px;  // px → mm
    $box_pad_top    = 10 * $px;
    $box_pad_right  = 55 * $px;
    $box_pad_bottom = 47 * $px;
    $img_col_w      = 65;        // image max width in mm
    $img_text_gap   = 58 * $px;  // 58px gap between image and text

    $box_y  = $logos_y + 8;
    $box_x  = 7;
    $box_w  = $W - 14;
    $box_h  = 100; // tall enough for top(17) + content(70) + bottom(13)
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($box_x, $box_y, $box_w, $box_h, 'F');

    // Image: constrained to its column, never affects text position
    $gc_col_w  = $box_pad_left + $img_col_w + $img_text_gap; // total reserved for image side
    $gc_max_w  = $img_col_w;
    $gc_max_h  = $box_h - $box_pad_top - $box_pad_bottom;

    $gift_card_image_url = get_gift_card_image_url($order_product_data, $data);
    $gc_path = url_to_path($gift_card_image_url);

    $gc_rendered_h = $gc_max_h;
    if (!empty($gc_path) && file_exists($gc_path) && can_get_image_size($gc_path)) {
        [$gc_px_w, $gc_px_h] = @getimagesize($gc_path);
        if ($gc_px_w > 0 && $gc_px_h > 0) {
            $scale        = min($gc_max_w / $gc_px_w, $gc_max_h / $gc_px_h);
            $gc_render_w  = $gc_px_w * $scale;
            $gc_render_h  = $gc_px_h * $scale;
        } else {
            $gc_render_w = $gc_max_w;
            $gc_render_h = $gc_max_h;
        }
        // Center image horizontally in its column, top-align with text top padding
        $gc_render_x  = $box_x + $box_pad_left + ($gc_max_w - $gc_render_w) / 2;
        $gc_render_y  = $box_y + $box_pad_top;
        $gc_rendered_h = $gc_render_h;
        $gc_left_x    = $gc_render_x;
        $gc_left_y    = $gc_render_y;

        $pdf->Image($gc_path, $gc_render_x, $gc_render_y, $gc_render_w, $gc_render_h, '', '', '', false);

        // Pink brand band: only for GiftcardsPlus own products.
        // Check flag passed in $data; fall back to reading directly from gift card post meta.
        $_is_gcp_card = !empty($data['is_gift_card_plus']);
        if (!$_is_gcp_card) {
            $_gc_post_id = !empty($data['gift_card_post_id']) ? $data['gift_card_post_id'] : (!empty($data['gift_card_id']) ? $data['gift_card_id'] : 0);
            if ($_gc_post_id) {
                $_gc_meta     = get_post_meta($_gc_post_id, '_is_gc_plus_product', true);
                $_is_gcp_card = ($_gc_meta === '1' || $_gc_meta === true || $_gc_meta === 'true');
            }
            if (!$_is_gcp_card && !empty($data['gift_card_sku'])) {
                $_prod_id     = wc_get_product_id_by_sku($data['gift_card_sku']);
                $_prod_meta   = $_prod_id ? get_post_meta($_prod_id, 'is_it_gift_card_plus_product', true) : '';
                $_is_gcp_card = ($_prod_meta === '1' || $_prod_meta === 'true' || $_prod_meta == 1);
            }
        }
        if ($_is_gcp_card) {
            $band_h = max(6.0, $gc_rendered_h * 0.22);
            $band_y = $gc_left_y + $gc_rendered_h - $band_h;
            $pad    = 2.0; // mm inner padding

            $band_w = $gc_render_w;

            $pdf->SetAlpha(0.55); // transparent overlay like email
            $pdf->SetFillColor(255, 196, 206);
            $pdf->Rect($gc_left_x, $band_y, $band_w, $band_h, 'F');
            $pdf->SetAlpha(1);

            // $pdf->SetAlpha(0.80);
            // $pdf->SetFillColor(255, 196, 206);
            // $pdf->Rect($gc_left_x, $band_y, $gc_desired_width, $band_h, 'F');
            // $pdf->SetAlpha(1.0);

            // GCP logo – left side of band
            $gcplus_band_path = url_to_path($data['logo_giftcardplus']);
            if (!empty($gcplus_band_path) && file_exists($gcplus_band_path) && can_get_image_size($gcplus_band_path)) {
                [$lw, $lh] = @getimagesize($gcplus_band_path);
                if ($lh > 0) {
                    $logo_band_h = $band_h * 0.65;
                    $logo_band_w = $lw / $lh * $logo_band_h;
                    $pdf->Image($gcplus_band_path, $gc_left_x + $pad, $band_y + ($band_h - $logo_band_h) / 2, $logo_band_w, $logo_band_h, '', '', '', false);
                }
            }

            // Denomination amount – right side of band, white bold text
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('verdana', 'B', max(7, (int)($band_h * 1.0)));
            $pdf->SetXY($gc_left_x, $band_y);
            // $pdf->Cell($gc_desired_width - $pad, $band_h, '$' . $data['amount'], 0, 0, 'R', false);
            $pdf->Cell($band_w - $pad, $band_h, '$' . $data['amount'], 0, 0, 'R', false);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetFont('verdana', '', 13);
        }
    } elseif (!empty($gc_path) && is_svg_file($gc_path)) {
    }

    // 3. Card fields at right (align top right of box)
    $pdf->SetFont('verdana', '', 13);
    $pdf->SetTextColor(30,30,30);
    $fields_x = $box_x + $gc_col_w + 5; // fixed — never affected by image size
    $start_y = $box_y + $box_pad_top;
    $pdf->SetXY($fields_x, $start_y);
    $pdf->setFontSpacing(0.2);

    // Product name as bold title
    $pdf->SetFont('verdana', 'B', 12.50);
    $text_col_w = $W - $fields_x - $box_x - $box_pad_right; // respects right margin
    $pdf->Cell($text_col_w, 6, $order_product_data[0]['product_name'], 0, 1, 'L', false);
    $start_y += 8;
    $pdf->SetXY($fields_x, $start_y);

    // Card number
    $pdf->SetFont('verdana', 'B', 12.50);
    $label_width = 50; // wide enough for "Activation Date:"
    $pdf->Cell($label_width, 8, 'Card Number:', 0, 0, 'L', false);
    $pdf->SetFont('verdana', '', 12.50);
    $value_width = 60;
    $card_number_text = $data['card_number'];
    $max_chars = 25;
    if (strlen($card_number_text) > $max_chars) {
        $card_number_text = substr($card_number_text, 0, $max_chars);
    }
    $pdf->Cell($value_width, 8, $card_number_text, 0, 1, 'L', false);

    // Value
    $current_y = $start_y + 8;
    $pdf->SetXY($fields_x, $current_y);
    $pdf->SetFont('verdana', 'B', 12.50);
    $pdf->Cell($label_width, 8, 'Value:', 0, 0, 'L', false);
    $pdf->SetFont('verdana', '', 12.50);
    $pdf->Cell($value_width, 8, '$' . htmlspecialchars($data['amount']), 0, 1, 'L', false);

    // PIN
    $current_y += 8;
    $pdf->SetXY($fields_x, $current_y);
    $pdf->SetFont('verdana', 'B', 12.50);
    $pdf->Cell($label_width, 8, 'PIN:', 0, 0, 'L', false);
    $pdf->SetFont('verdana', '', 12.50);
    $display_pin = !empty($data['card_pin']) ? $data['card_pin'] : (isset($data['pin']) ? $data['pin'] : '');
    $pdf->Cell($value_width, 8, htmlspecialchars($display_pin), 0, 1, 'L', false);

    // Activation Date
    $activation_date = htmlspecialchars($order_product_data[0]['all_fields']['activation_expiry_date'] ?? '');
    if (strlen($activation_date) > $max_chars) {
        $activation_date = substr($activation_date, 0, $max_chars);
    }
    if (!empty(trim($activation_date))) {
        $current_y += 8;
        $pdf->SetXY($fields_x, $current_y);
        $pdf->SetFont('verdana', 'B', 12.50);
        $pdf->Cell($label_width, 8, 'Activation Date:', 0, 0, 'L', false);
        $pdf->SetFont('verdana', '', 12.50);
        $pdf->Cell($value_width, 8, $activation_date, 0, 1, 'L', false);
    }

    // Expiry Date
    $expiry_date = htmlspecialchars($order_product_data[0]['all_fields']['gift_card_expiry_date'] ?? '');
    if (strlen($expiry_date) > $max_chars) {
        $expiry_date = substr($expiry_date, 0, $max_chars);
    }
    if (!empty(trim($expiry_date))) {
        $current_y += 8;
        $pdf->SetXY($fields_x, $current_y);
        $pdf->SetFont('verdana', 'B', 12.50);
        $pdf->Cell($label_width, 8, 'Expiry Date:', 0, 0, 'L', false);
        $pdf->SetFont('verdana', '', 12.50);
        $pdf->Cell($value_width, 8, $expiry_date, 0, 1, 'L', false);
    }

    $pdf->setFontSpacing(0);

    // Set position below the Expiry Date
    $barcode_y = $pdf->GetY() + 2; // 2mm padding below text
    
    // Define barcode style
    $barcode_style = [
        'position'     => '',
        'align'        => 'L',
        'stretch'      => false,
        'fitwidth'     => true,
        'cellfitalign' => '',
        'border'       => false,
        'hpadding'     => 2,
        'vpadding'     => 2,
        'fgcolor'      => [0, 0, 0],
        'bgcolor'      => [255, 255, 255],
        'text'         => false,
    ];

    $barcode_w = $W - $fields_x - $box_x - $box_pad_right;
    $pdf->write1DBarcode($data['card_number'], 'C128', $fields_x, $barcode_y, $barcode_w, 16, 0.6, $barcode_style, 'N');


    // Commented on 20251218
    /*$pdf->MultiCell(60, 7, 
        "Card Number: {$data['card_number']}\nDenomination: \${$data['amount']}\nPIN: {$data['pin']}\nActivation Date: {$data['activation']}\nExpiry Date: {$data['expiry']}",
        0, 'L', false, 1);*/

    // Commented on 20251219
    // Generate unique token for barcode scanning
    // $gift_card_id = $data['gift_card_id'] ?? 0;
    // $scan_token = '';
    // $barcode_url = '';
    // if ($gift_card_id) {
    //     // Generate a unique token based on gift card ID and card number
    //     $scan_token = wp_hash($gift_card_id . '_' . $card_number_text . '_' . time(), 'gift_card_scan');
        
    //     // Store token mapping with card number and PIN for lookup
    //     $token_data = [
    //         'card_number' => $card_number_text,
    //         'pin' => $data['pin'] ?? '',
    //         'gift_card_id' => $gift_card_id,
    //         'created_at' => current_time('mysql')
    //     ];
    
    //     // Store token data in gift card post meta
    //     update_post_meta($gift_card_id, '_scan_token', $scan_token);
    //     update_post_meta($gift_card_id, '_scan_token_data', $token_data);
        
    //     // Also store PIN separately for easy retrieval
    //     if (!empty($data['pin'])) {
    //         update_post_meta($gift_card_id, '_bhn_pin', $data['pin']);
    //     }
    
    //     // Create shorter URL for barcode scanning - use shorter path for better barcode density
    //     $site_url = home_url();
    //     // Ensure URL has proper protocol (http:// or https://) - CRITICAL for mobile recognition
    //     if (!preg_match('/^https?:\/\//', $site_url)) {
    //         $site_url = (is_ssl() ? 'https://' : 'http://') . $site_url;
    //     }
    //     // Remove any trailing slashes and ensure clean URL
    //     $site_url = rtrim($site_url, '/');
    //     // Use shorter URL format: /gc/{token} instead of /view-gift-card/?token={token}
    //     // This makes the barcode less dense and more scannable
    //     // IMPORTANT: URL must start with http:// or https:// for mobile cameras to recognize it
    //     $barcode_url = $site_url . '/gc/' . $scan_token;
    // }

    // $logger = wc_get_logger();
    // $context = ['source' => 'SEND-EMAIL'];

    // // Generate linear (1D) barcode using URL (so scanning redirects to gift card view page)
    // // Optimized for mobile camera scanning
    // // CRITICAL: Mobile cameras recognize URLs that start with http:// or https://
    // $barcode_code = !empty($barcode_url) ? $barcode_url : $card_number_text;

    // // Validate barcode code is not empty and is a valid URL format
    // if (empty($barcode_code)) {
    //     $barcode_code = $card_number_text; // Fallback to card number
    //     $logger->warning("Barcode URL is empty, using card number instead", $context);
    // } else {
    //     // Verify URL format for mobile recognition
    //     if (!preg_match('/^https?:\/\//', $barcode_code)) {
    //         $logger->warning("Barcode code does not start with http:// or https:// - mobile cameras may not recognize it as URL", $context);
    //         // Try to fix it
    //         if (strpos($barcode_code, '://') === false) {
    //             $barcode_code = (is_ssl() ? 'https://' : 'http://') . $barcode_code;
    //             $logger->info("Fixed barcode URL: {$barcode_code}", $context);
    //         }
    //     }
    // }

    // // Log barcode details for debugging
    // $logger->info("Linear Barcode code length: " . strlen($barcode_code), $context);
    // $logger->info("Linear Barcode URL: {$barcode_url}", $context);
    // $logger->info("Linear Barcode token: {$scan_token}", $context);

    // // Use PDF417 barcode - stacked linear format that looks like linear barcode but works with URLs
    // // PDF417 is a 2D stacked barcode that appears linear but has better URL recognition than Code 128
    // $barcode_style = array(
    //     'border' => false,
    //     'padding' => 'auto',
    //     'hpadding' => 'auto',
    //     'vpadding' => 'auto',
    //     'fgcolor' => array(0,0,0), // Pure black for maximum contrast
    //     'bgcolor' => array(255,255,255), // Pure white background
    //     'module_width' => 1,
    //     'module_height' => 1
    // );

    // // Position and size for PDF417 barcode - optimized for mobile scanning
    // // PDF417 looks like a linear/stacked barcode but is 2D and works better with URLs
    // $barcode_width = 85; // Width in mm
    // $barcode_height = 25; // Height in mm (taller for stacked appearance)
    // $barcode_x = $fields_x; // Aligned with card details (same X as fields)
    // $barcode_y = $current_y + 8; // Position below Expiry Date field with spacing

    // // Log the exact barcode code being used
    // $logger->info("PDF417 Barcode code to encode: {$barcode_code}", $context);
    // $logger->info("PDF417 Barcode position: X={$barcode_x}, Y={$barcode_y}, W={$barcode_width}, H={$barcode_height}", $context);

    // // Generate PDF417 barcode (stacked linear format - better URL recognition than Code 128)
    // // PDF417 looks like a linear barcode but is 2D and mobile cameras recognize URLs in it
    // try {
    //     // Generate PDF417 barcode directly in PDF
    //     $pdf->write2DBarcode($barcode_code, 'PDF417', $barcode_x, $barcode_y, $barcode_width, $barcode_height, $barcode_style, 'N');
    //     $logger->info("PDF417 Barcode successfully generated with code length: " . strlen($barcode_code), $context);
    // } catch (Exception $e) {
    //     $logger->error("Error generating PDF417 barcode: " . $e->getMessage(), $context);
    //     // Fallback: try with QR code if PDF417 fails
    //     try {
    //         $qr_style = array(
    //             'border' => false,
    //             'vpadding' => 'auto',
    //             'hpadding' => 'auto',
    //             'fgcolor' => array(0,0,0),
    //             'bgcolor' => array(255,255,255),
    //             'module_width' => 1,
    //             'module_height' => 1
    //         );
    //         $pdf->write2DBarcode($barcode_code, 'QRCODE,M', $barcode_x, $barcode_y, $barcode_width, $barcode_height, $qr_style, 'N');
    //         $logger->info("QR Code generated as fallback: {$barcode_code}", $context);
    //     } catch (Exception $e2) {
    //         $logger->error("Failed to generate fallback barcode: " . $e2->getMessage(), $context);
    //     }
    // }


    //$pdf->AddPage();

    $pdf->SetFont('verdana', '', 12);
    $pdf->SetTextColor(30,30,30);
    $fields_x = $box_x + 95; // move right so it doesn't overlap card
    $pdf->setFontSpacing(0.2);

    // ---------------------Page 2

    // HOW TO USE — pink background sized to content (measured via transaction, no fixed height)
    $pink_bg_y = $pdf->GetY() + 6;

    // Fetch dynamic content directly from product meta (not from snapshot)
    $_pdf_product_id = !empty($order_product_data[0]['product_id']) ? (int) $order_product_data[0]['product_id'] : 0;
    $how_to_use_raw = $_pdf_product_id ? get_post_meta($_pdf_product_id, 'how_to_use', true) : '';
    $how_to_use_raw = trim((string) $how_to_use_raw);
    // Keep basic formatting tags TCPDF supports, strip everything else
    $allowed_tags = '<b><strong><br><p><ul><ol><li><em><i>';
    $how_to_use_raw = strip_tags($how_to_use_raw, $allowed_tags);
    $how_to_use_raw = bhn_normalize_pdf_text(
        html_entity_decode($how_to_use_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );

    $terms_conditions_raw = $_pdf_product_id ? get_post_meta($_pdf_product_id, 'terms_conditions', true) : '';
    $terms_conditions_raw = trim((string) $terms_conditions_raw);
    // terms_conditions is stored as JSON {"type":"INLINE","text":"...HTML..."} — decode and extract text
    $terms_decoded = json_decode($terms_conditions_raw, true);
    if (is_array($terms_decoded) && !empty($terms_decoded['text'])) {
        $terms_conditions_raw = $terms_decoded['text'];
    }
    // Keep basic formatting tags, strip everything else (removes <a> links but keeps text)
    $terms_conditions_raw = strip_tags($terms_conditions_raw, $allowed_tags);
    $terms_conditions_raw = bhn_normalize_pdf_text(
        html_entity_decode($terms_conditions_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );

    // Fallback to hardcoded defaults if fields are empty
    $how_to_use_html = !empty($how_to_use_raw)
        ? "<span style=\"font-size:8pt;font-family:Verdana;\">{$how_to_use_raw}</span>"
        : '<span style="font-size:8pt;font-family:Verdana;"><strong>In Store:</strong> Simply present your gift card at checkout. The amount will be deducted from your total.</span>'
          . '<br><br><span style="font-size:8pt;font-family:Verdana;"><strong>Online:</strong> Enter the gift card number at the payment step during checkout. The balance will be applied automatically.</span>';

    $terms_html = !empty($terms_conditions_raw)
        ? "<span style=\"font-size:8pt;font-family:Verdana;\">{$terms_conditions_raw}</span>"
        : '<span style="font-size:8pt;font-family:Verdana;">This Gift Card cannot be exchanged for cash. Please contact the retailer to see the current balance of this Gift Card.</span>';

    // --- Pass 1: measure content height ---
    $pdf->startTransaction();

    // Heading: Verdana Bold, 15pt, magenta
    $pdf->SetFont('verdana', 'B', 15);
    $pdf->writeHTMLCell($W-44, 0, 22, $pink_bg_y + 4,
        '<span style="color:#EC008C;font-size:15pt;font-family:Verdana;">How to use:</span>',
        0, 1, false, true, 'L', true);

    // How to use body
    $pdf->SetFont('verdana', '', 8);
    $pdf->writeHTMLCell($W-44, 0, 22, $pdf->GetY() + 5, $how_to_use_html, 0, 1, false, true, 'L', true);

    // Terms & Conditions (only if content exists)
    if (!empty($terms_conditions_raw)) {
        $pdf->SetFont('verdana', 'B', 8);
        $pdf->writeHTMLCell($W-44, 0, 22, $pdf->GetY() + 5,
            '<strong style="font-size:8pt;font-family:Verdana;">Terms &amp; Conditions</strong>',
            0, 1, false, true, 'L', true);
        $pdf->SetFont('verdana', '', 8);
        $pdf->writeHTMLCell($W-44, 0, 22, $pdf->GetY() + 1, $terms_html, 0, 1, false, true, 'L', true);
    }

    // Capture measured height, rollback, draw real background, then redraw content
    $how_to_use_section_h = ($pdf->GetY() + 5) - $pink_bg_y;
    $pdf->rollbackTransaction(true);

    // Draw pink background sized to exact content height
    $pdf->SetFillColor(255, 236, 245);
    $pdf->Rect(0, $pink_bg_y, $W, $how_to_use_section_h, 'F');

    // --- Pass 2: draw content for real ---
    $pdf->SetFont('verdana', 'B', 15);
    $pdf->writeHTMLCell($W-44, 0, 22, $pink_bg_y + 4,
        '<span style="color:#EC008C;font-size:15pt;font-family:Verdana;">How to use:</span>',
        0, 1, false, true, 'L', true);
    $pdf->SetFont('verdana', '', 8);
    $pdf->writeHTMLCell($W-44, 0, 22, $pdf->GetY() + 5, $how_to_use_html, 0, 1, false, true, 'L', true);
    if (!empty($terms_conditions_raw)) {
        $pdf->SetFont('verdana', 'B', 8);
        $pdf->writeHTMLCell($W-44, 0, 22, $pdf->GetY() + 5,
            '<strong style="font-size:8pt;font-family:Verdana;">Terms &amp; Conditions</strong>',
            0, 1, false, true, 'L', true);
        $pdf->SetFont('verdana', '', 8);
        $pdf->writeHTMLCell($W-44, 0, 22, $pdf->GetY() + 1, $terms_html, 0, 1, false, true, 'L', true);
    }

    $pdf->SetY($pink_bg_y + $how_to_use_section_h); // advance to section end
    $pdf->setFontSpacing(0);

    // Commented on 20251218
    /*// 5. HOW TO USE
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 8, 'How to use:', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(50,50,50);
    $pdf->MultiCell(0, 7, 
        "● In Store:\n  Simply present your gift card at checkout. The amount will be deducted from your total.\n\n● Online:\n  Enter the gift card number at the payment step during checkout. The balance will be applied automatically.", 
        0, 'L', false, 1);
    $pdf->Ln(2);

    // 6. TERMS
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'Terms & Conditions', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 7, 
        "This Gift Card cannot be exchanged for cash. Please contact the retailer to see the current balance of this Gift Card."
    );*/
    //$pdf->Ln(2);

    // 7. SUPPORT AREA — magenta background #ec008c
    $support_section_h = 38; // adjust this value to change section height (mm)
    $support_y = $pdf->GetY();
    $pdf->SetY($support_y);

    // Magenta background rect
    $pdf->SetFillColor(236, 0, 140); // #ec008c
    $pdf->Rect(0, $support_y, $W, $support_section_h, 'F');

    // Phone image on right — white circle, centered vertically in section
    $phone_image_path = get_template_directory() . '/assets/images/phone.png';
    if (file_exists($phone_image_path) && can_get_image_size($phone_image_path)) {
        [$img_w, $img_h] = @getimagesize($phone_image_path);
        if ($img_w > 0 && $img_h > 0) {
            $phone_render_h = 28;
            $phone_render_w = ($img_w / $img_h) * $phone_render_h;
            $phone_x        = $W - $phone_render_w - 22;
            $phone_y        = $support_y + ($support_section_h - $phone_render_h) / 2;
            $pdf->Image($phone_image_path, $phone_x, $phone_y, $phone_render_w, $phone_render_h, '', '', '', false, 300);
        }
    }

    // Register Ephesis font (TTF must be converted and placed in TCPDF fonts dir)
    $pdf->AddFont('ephesis', '', 'ephesis.php');

    // Title: "Need a little " Verdana Bold 15pt + "help?" Ephesis 25pt — white on magenta
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('verdana', 'B', 15);
    $pdf->writeHTMLCell(
        $W-85, 0, 22, $support_y + 4,
        '<span style="font-family:Verdana;font-size:15pt;color:#ffffff;">Need a little </span>' .
        '<span style="font-family:Ephesis;font-size:25pt;color:#ffffff;">help?</span>',
        0, 1, false, true, 'L', true
    );

    // Description: white text, 8pt Verdana, "plus" in Ephesis
    $pdf->SetFont('verdana', '', 8);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->writeHTMLCell(
        $W-85, 0, 22, $pdf->GetY() + 2,
        '<span style="font-family:Verdana;font-size:8pt;color:#ffffff;">The <strong>giftcards</strong></span>' .
        '<span style="font-family:Ephesis;font-size:16pt;color:#ffffff;">plus</span>' .
        '<span style="font-family:Verdana;font-size:8pt;color:#ffffff;"> Australian base support team is available to help you personalise your experience or answer any questions.</span>',
        0, 1, false, true, 'L', true
    );

    // Email: Verdana 10pt, white
    $pdf->SetFont('verdana', '', 10);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->writeHTMLCell(
        $W-85, 0, 22, $pdf->GetY() + 2,
        '<span style="font-family:Verdana;font-size:10pt;color:#ffffff;">' . esc_html($data['support_email']) . '</span>',
        0, 1, false, true, 'L', true
    );

    // --- DARK FOOTER STRIP with logo + social icons ---
    $dark_footer_y = $support_y + $support_section_h;
    $dark_footer_h = $pdf->getPageHeight() - $dark_footer_y;
    $pdf->SetFillColor(30, 30, 30);
    $pdf->Rect(0, $dark_footer_y, $W, $dark_footer_h, 'F');

    // Left: giftcards plus white logo
    $white_logo_path = get_template_directory() . '/assets/images/White-Pink.png';
    if (file_exists($white_logo_path) && can_get_image_size($white_logo_path)) {
        [$lw, $lh] = @getimagesize($white_logo_path);
        if ($lh > 0) {
            $fl_h = 10; $fl_w = $lw / $lh * $fl_h;
            $pdf->Image($white_logo_path, 22, $dark_footer_y + 5, $fl_w, $fl_h, '', '', '', false);
        }
    } else {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(22, $dark_footer_y + 7);
        $pdf->Cell(30, 6, 'giftcards', 0, 0, 'L');
        $pdf->SetTextColor(236, 0, 140);
        $pdf->Cell(12, 6, 'plus', 0, 0, 'L');
    }

    // Right: social icons + @giftcardsplus
    $soc_y    = $dark_footer_y + 7;
    $soc_x    = $W - 22 - ( $icon_sz * 3 ) - ( $icon_gap * 2 ) - 53;
    $icon_sz  = 6;
    $icon_gap = 8;

    $fb_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">'
        . '<rect width="24" height="24" rx="2" fill="#FFFFFF"/>'
        . '<path d="M14.5 8H16V5.5H14.2C11.8 5.5 10.5 6.9 10.5 9.4V11H8.5V13.5H10.5V19H13.2V13.5H15.5L15.9 11H13.2V9.7C13.2 8.8 13.5 8 14.5 8Z" fill="#1E1A1D"/>'
        . '</svg>';

    $li_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">'
        . '<rect width="24" height="24" rx="2" fill="#FFFFFF"/>'
        . '<path d="M7 9H10V18H7V9ZM8.5 5.5C9.3 5.5 10 6.2 10 7C10 7.8 9.3 8.5 8.5 8.5C7.7 8.5 7 7.8 7 7C7 6.2 7.7 5.5 8.5 5.5ZM11.5 9H14.3V10.2H14.4C14.8 9.4 15.8 8.8 17 8.8C19.5 8.8 20 10.4 20 13V18H17V13.7C17 12.5 17 11 15.5 11C14 11 13.8 12.1 13.8 13.6V18H11V9H11.5Z" fill="#1E1A1D"/>'
        . '</svg>';

    $ig_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">'
        . '<rect width="24" height="24" rx="4" fill="#1E1A1D"/>'
        . '<rect x="4" y="4" width="16" height="16" rx="4" stroke="#FFFFFF" stroke-width="2" fill="none"/>'
        . '<circle cx="12" cy="12" r="3.5" stroke="#FFFFFF" stroke-width="2" fill="none"/>'
        . '<circle cx="17" cy="7" r="1.2" fill="#FFFFFF"/>'
        . '</svg>';

    $tmp_fb = tempnam( sys_get_temp_dir(), 'gcpfb' ) . '.svg';
    $tmp_li = tempnam( sys_get_temp_dir(), 'gcpli' ) . '.svg';
    $tmp_ig = tempnam( sys_get_temp_dir(), 'gcpig' ) . '.svg';

    file_put_contents( $tmp_fb, $fb_svg );
    file_put_contents( $tmp_li, $li_svg );
    file_put_contents( $tmp_ig, $ig_svg );

    $pdf->ImageSVG( $tmp_fb, $soc_x,                 $soc_y, $icon_sz, $icon_sz );
    $pdf->ImageSVG( $tmp_ig, $soc_x + $icon_gap,     $soc_y, $icon_sz, $icon_sz );
    $pdf->ImageSVG( $tmp_li, $soc_x + $icon_gap * 2, $soc_y, $icon_sz, $icon_sz );

    @unlink( $tmp_fb );
    @unlink( $tmp_li );
    @unlink( $tmp_ig );

    // @giftcardsplus handle
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY($soc_x + $icon_gap * 2 + $icon_sz + 3, $soc_y + 0.8);
    $pdf->Cell(50, $icon_sz - 1, '@giftcardsplus', 0, 0, 'L');


    // Clean any output that may have been generated before PDF output
    $output = ob_get_clean();
    if (!empty($output)) {
    }

    $pdf->Output($output_path, 'F');
}


function url_to_path($url) {
    if (empty($url)) return '';
    // If it's already a file path, return as is
    if (file_exists($url)) return $url;
    // Convert URL to path — uploads directory
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];
    $base_path = $upload_dir['basedir'];
    if (strpos($url, $base_url) === 0) {
        return str_replace($base_url, $base_path, $url);
    }
    // Handle child theme (stylesheet) directory URLs
    $stylesheet_uri = get_stylesheet_directory_uri();
    $stylesheet_dir = get_stylesheet_directory();
    if (strpos($url, $stylesheet_uri) === 0) {
        return str_replace($stylesheet_uri, $stylesheet_dir, $url);
    }
    // Handle parent theme directory URLs
    $template_uri = get_template_directory_uri();
    $template_dir = get_template_directory();
    if (strpos($url, $template_uri) === 0) {
        return str_replace($template_uri, $template_dir, $url);
    }
    // Try attachment URL to path (media library)
    $attachment_id = attachment_url_to_postid($url);
    if ($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if ($file_path && file_exists($file_path)) {
            return $file_path;
        }
    }
    return '';
}

// Helper function to check if file is SVG (TCPDF doesn't support SVG)
function is_svg_file($file_path) {
    if (empty($file_path) || !file_exists($file_path)) {
        return false;
    }
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return $extension === 'svg';
}

// Helper function to check if getimagesize will work (excludes SVG)
function can_get_image_size($file_path) {
    if (empty($file_path) || !file_exists($file_path)) {
        return false;
    }
    // SVG files can't be read by getimagesize
    if (is_svg_file($file_path)) {
        return false;
    }
    return true;
}



function get_gift_card_image_url($order_product_data, $data) {
    $gift_card_image_url = '';
    
    // First try to get from order_product_data if available
    // Prefer the selected/customer-chosen image passed in $data (set from _gift_card_image or product image in single-order-functions)
    if (!empty($data['image_url'])) {
        $gift_card_image_url = $data['image_url'];
    } elseif (!empty($data['gift_card_image'])) {
        $gift_card_image_url = $data['gift_card_image'];
    }

    // Then try order_product_data if available
    if (empty($gift_card_image_url) && !empty($order_product_data[0]['gift_card_image'])) {
        $gift_card_image_url = $order_product_data[0]['gift_card_image'];
    } elseif (empty($gift_card_image_url) && !empty($order_product_data[0]['image_url'])) {
        $gift_card_image_url = $order_product_data[0]['image_url'];
    } elseif (empty($gift_card_image_url) && !empty($order_product_data[0]['product_image'])) {
        $gift_card_image_url = $order_product_data[0]['product_image'];
    } elseif (empty($gift_card_image_url) && !empty($order_product_data[0]['img'])) {
        $gift_card_image_url = $order_product_data[0]['img'];
    } elseif (empty($gift_card_image_url) && !empty($order_product_data[0]['all_fields']['gift_card_image'])) {
        $gift_card_image_url = $order_product_data[0]['all_fields']['gift_card_image'];
    } elseif (empty($gift_card_image_url) && !empty($order_product_data[0]['all_fields']['image_url'])) {
        $gift_card_image_url = $order_product_data[0]['all_fields']['image_url'];
    }
    
    // If still not found and we have order_id, try to get from order item meta
    if (empty($gift_card_image_url) && !empty($data['gift_card_id'])) {
        $gift_card_post_id = $data['gift_card_id'];
        $order_id = get_post_meta($gift_card_post_id, '_order_id', true);
        
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                foreach ($order->get_items() as $item) {
                    $item_gift_card_image = wc_get_order_item_meta($item->get_id(), '_gift_card_image');
                    if (!empty($item_gift_card_image)) {
                        $gift_card_image_url = $item_gift_card_image;
                        break;
                    }
                }
            }
        }
    }
    
    // Fallback to logo_brand_main if gift card image not found
    if (empty($gift_card_image_url)) {
        $gift_card_image_url = $data['logo_brand_main'];
    }
    
    return $gift_card_image_url;
}

/**
 * Composites a GCP pink band onto the bottom of a gift card image using PHP GD.
 * Returns the URL of the composited image, or the original URL on failure.
 */
function gcp_composite_email_image($image_url, $logo_url, $amount) {
    if (!function_exists('imagecreatetruecolor') || empty($image_url)) {
        return $image_url;
    }

    // One composite per brand+amount — create once, reuse forever
    $_upload_dir  = wp_upload_dir();
    $_cached_name = 'gcp-composite-' . md5($image_url . $amount) . '.png';
    $_cached_path = $_upload_dir['basedir'] . '/' . $_cached_name;
    $_cached_url  = $_upload_dir['baseurl'] . '/' . $_cached_name;
    if ( file_exists( $_cached_path ) ) {
        return $_cached_url;
    }

    $image_path = url_to_path($image_url);
    if (empty($image_path) || !file_exists($image_path) || !can_get_image_size($image_path)) {
        return $image_url;
    }

    $info = @getimagesize($image_path);
    if (!$info) return $image_url;

    [$px_w, $px_h, $type] = $info;
    if ($px_w <= 0 || $px_h <= 0) return $image_url;

    $src = null;
    if ($type === IMAGETYPE_JPEG)                                  $src = @imagecreatefromjpeg($image_path);
    elseif ($type === IMAGETYPE_PNG)                               $src = @imagecreatefrompng($image_path);
    elseif ($type === IMAGETYPE_GIF)                               $src = @imagecreatefromgif($image_path);
    elseif (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP) $src = @imagecreatefromwebp($image_path);
    if (!$src) return $image_url;

    $out = imagecreatetruecolor($px_w, $px_h);
    imagealphablending($out, true);
    imagesavealpha($out, true);
    imagecopy($out, $src, 0, 0, 0, 0, $px_w, $px_h);
    imagedestroy($src);

    // Pink band: 22% of image height, minimum 40px
    $band_h = max(40, (int)($px_h * 0.22));
    $band_y = $px_h - $band_h;
    $pad    = max(8, (int)($px_w * 0.03));

    // Merge semi-transparent pink band (80% opacity) onto image
    $band_canvas = imagecreatetruecolor($px_w, $band_h);
    $pink = imagecolorallocate($band_canvas, 255, 196, 206);
    imagefilledrectangle($band_canvas, 0, 0, $px_w - 1, $band_h - 1, $pink);
    imagecopymerge($out, $band_canvas, 0, $band_y, 0, 0, $px_w, $band_h, 80);
    imagedestroy($band_canvas);

    // GCP logo – left side of band
    $logo_path = url_to_path($logo_url);
    if (!empty($logo_path) && file_exists($logo_path) && can_get_image_size($logo_path)) {
        $li = @getimagesize($logo_path);
        if ($li && $li[1] > 0) {
            $target_lh = (int)($band_h * 0.60);
            $target_lw = (int)($li[0] / $li[1] * $target_lh);
            $logo_src  = null;
            if ($li[2] === IMAGETYPE_JPEG)    $logo_src = @imagecreatefromjpeg($logo_path);
            elseif ($li[2] === IMAGETYPE_PNG) $logo_src = @imagecreatefrompng($logo_path);
            elseif ($li[2] === IMAGETYPE_GIF) $logo_src = @imagecreatefromgif($logo_path);
            if ($logo_src && $target_lw > 0) {
                $logo_out = imagecreatetruecolor($target_lw, $target_lh);
                imagealphablending($logo_out, false);
                imagesavealpha($logo_out, true);
                imagefilledrectangle($logo_out, 0, 0, $target_lw - 1, $target_lh - 1, imagecolorallocatealpha($logo_out, 0, 0, 0, 127));
                imagealphablending($logo_out, true);
                imagecopyresampled($logo_out, $logo_src, 0, 0, 0, 0, $target_lw, $target_lh, $li[0], $li[1]);
                imagedestroy($logo_src);
                imagecopy($out, $logo_out, $pad, $band_y + (int)(($band_h - $target_lh) / 2), 0, 0, $target_lw, $target_lh);
                imagedestroy($logo_out);
            }
        }
    }

    // Denomination text – right side of band, white
    $white      = imagecolorallocate($out, 255, 255, 255);
    $denom_text = '$' . $amount;
    $font_size  = (int)($band_h * 0.45);
    $font_paths = [
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/liberation/LiberationSans-Bold.ttf',
    ];
    $font_file = '';
    foreach ($font_paths as $fp) {
        if (file_exists($fp)) { $font_file = $fp; break; }
    }
    if ($font_file && function_exists('imagettftext')) {
        $pt   = $font_size * 0.75;
        $bbox = @imagettfbbox($pt, 0, $font_file, $denom_text);
        if ($bbox) {
            $tw = abs($bbox[2] - $bbox[0]);
            $th = abs($bbox[7] - $bbox[1]);
            $tx = $px_w - $pad - $tw;
            $ty = $band_y + (int)(($band_h + $th) / 2);
            imagettftext($out, $pt, 0, $tx, $ty, $white, $font_file, $denom_text);
        }
    } else {
        $font = 5;
        $tw   = strlen($denom_text) * imagefontwidth($font);
        $tx   = $px_w - $pad - $tw;
        $ty   = $band_y + (int)(($band_h - imagefontheight($font)) / 2);
        imagestring($out, $font, $tx, $ty, $denom_text, $white);
    }

    imagepng($out, $_cached_path, 8);
    imagedestroy($out);

    return file_exists( $_cached_path ) ? $_cached_url : $image_url;
}

// Schedule weekly PDF cleanup cron
if (!wp_next_scheduled('gc_cleanup_pdfs')) {
    wp_schedule_event(time(), 'hourly', 'gc_cleanup_pdfs');
}

add_action('gc_cleanup_pdfs', function () {
    // WB-3.12: Scan private PDF dir only. Retention reduced from 7 days to 1 hour.
    $private_dir = gcp_private_pdf_dir();
    $now         = time();
    foreach ( [ 'giftcard-*.pdf', 'bhn-egift-*.pdf' ] as $pattern ) {
        $files = glob( $private_dir . '/' . $pattern );
        if ( $files ) {
            foreach ( $files as $file ) {
                if ( is_file( $file ) && ( $now - filemtime( $file ) >= 3600 ) ) {
                    unlink( $file );
                }
            }
        }
    }
});