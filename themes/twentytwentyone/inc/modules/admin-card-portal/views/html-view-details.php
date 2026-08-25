<?php
/**
 * View: Admin Portal - Card Details Screen
 * Matches Figma Design
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---- DATA PREPARATION ----
// $current_card_id is passed from the Router logic
$card_id = $current_card_id;
$post    = get_post( $card_id );

if ( ! $post || $post->post_type !== 'gift_card' ) {
    echo '<div class="gcp-container" style="padding:50px; text-align:center;">Card not found.</div>';
    return;
}

// Fetch Core Meta
$order_id         = get_post_meta( $card_id, '_order_id', true );
$recipient_name   = get_post_meta( $card_id, '_recipient_name', true );
$recipient_email  = get_post_meta( $card_id, '_recipient_email', true );
$recipient_phone  = get_post_meta( $card_id, '_recipient_phone', true );
$delivery_method  = get_post_meta( $card_id, '_delivery_method', true ) ?: 'Email';
$card_price       = get_post_meta( $card_id, '_price', true );

// --- DECRYPTION LOGIC START ---
$enc_card_number = get_post_meta( $card_id, '_gift_card_number_enc', true );
$card_number     = 'Pending'; // Default if no number found

if ( ! empty( $enc_card_number ) ) {
    if ( function_exists( 'decrypt_giftcard_no' ) ) {
        try {
            $card_number = decrypt_giftcard_no( $enc_card_number );
        } catch ( Exception $e ) {
            $card_number = 'Decryption Error'; // Visual feedback if key is wrong or data corrupt
        }
    } else {
        $card_number = 'Function Missing'; // Safety fallback
    }
}
// --- DECRYPTION LOGIC END ---

$card_message       = get_post_meta( $card_id, '_gift_message', true );
$card_image         = get_post_meta( $card_id, '_image_url', true ); 
$status             = get_post_meta( $card_id, '_gift_card_status', true ) ?: 'Completed';
$is_gc_plus         = get_post_meta( $card_id, '_is_gc_plus_product', true );
// Swap balance for "Swap card on behalf of user" — must always be a number so JS never gets NaN
$swap_balance = get_post_meta( $card_id, '_swap_available_amount', true );
if ( $swap_balance === '' || $swap_balance === null || $swap_balance === false ) {
    $swap_balance = get_post_meta( $card_id, '_price', true );
}
if ( $swap_balance === '' || $swap_balance === null || $swap_balance === false ) {
    $swap_balance = isset( $card_price ) ? $card_price : 0;
}
$swap_balance = is_numeric( $swap_balance ) ? floatval( $swap_balance ) : 0;


// Fetch Order Data
$ordered_by   = 'Guest';
$business     = '-';
$invoice_link = '#'; // Placeholder

if ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( $order ) {
        $order_number = $order->get_order_number();
        $ordered_by   = $order->get_formatted_billing_full_name();
        $business     = $order->get_billing_company() ?: '-';
        
        // Check for PDF Invoice plugin (Example: WooCommerce PDF Invoices & Packing Slips)
        if ( function_exists( 'wcpdf_get_document' ) ) {
            // $invoice_link = ... logic to generate URL
        }
    }
}

// Logic for "Contact" field (Email vs Mobile)
$contact_display = $recipient_email;
if ( stripos( $delivery_method, 'sms' ) !== false && $recipient_phone ) {
    $contact_display = $recipient_phone;
    if ( stripos( $delivery_method, 'email' ) !== false ) {
        $contact_display .= ' / ' . $recipient_email;
    }
}

// Gift Card Title (Use Post Title or Product Name)
$card_title = get_the_title( $card_id );

// ---- RENDER PAGE ----
get_header(); 
?>

<div class="gcp-admin-wrapper">
    
    <div class="gcp-sub-header">
        ACME ADS ⌄
    </div>

    <div class="gcp-card-layout">
        
        <div class="gcp-details-column">
            <a href="javascript:history.back()" class="gcp-back-link">&larr;</a>
            
            <h1>Card Details</h1>

            <div class="gcp-details-list">
                <div class="detail-row">
                    <span class="detail-label">Order number:</span>
                    <span class="detail-value"><?php echo esc_html( $order_number ); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Gift card:</span>
                    <span class="detail-value"><?php echo esc_html( $card_title ); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Recipient:</span>
                    <span class="detail-value"><?php echo esc_html( $recipient_name ); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Total Cost:</span>
                    <span class="detail-value"><?php echo wc_price( $card_price ); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Delivery Method:</span>
                    <span class="detail-value">
                        <?php echo esc_html( ucfirst( $delivery_method ) ); ?>
                        <span class="icon-edit">✎</span> </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Contact:</span>
                    <span class="detail-value"><?php echo esc_html( $contact_display ); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Ordered by:</span>
                    <span class="detail-value"><?php echo esc_html( $ordered_by ); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Business:</span>
                    <span class="detail-value"><?php echo esc_html( $business ); ?></span>
                </div>
            </div>
        </div>

        <div class="gcp-visual-column">
            
            <a href="<?php echo esc_url( $invoice_link ); ?>" class="btn-invoice" target="_blank">Download Invoice</a>

            <div class="gcp-main-image">
                <?php if ( $card_image ) { ?>
                    <img src="<?php echo esc_url( $card_image ); ?>" alt="Card Image">
                <?php } else { ?>
                    <div style="height:200px; background:#eee; display:flex; align-items:center; justify-content:center;">No Image</div>
                <?php } ?>
            </div>

            <?php if ( $is_gc_plus ) { ?>
            <button type="button" id="gcpw-admin-swap-trigger" class="button button-primary" data-card-id="<?php echo esc_attr( $card_id ); ?>" data-swap-balance="<?php echo esc_attr( $swap_balance ); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 16V4M7 4L3 8M7 4L11 8M17 8V20M17 20L21 16M17 20L13 16"/></svg>
                Swap card on behalf of user
            </button>
            <?php } ?>

            <div class="gcp-actions-row">
                <?php
                
                $pdf_url = add_query_arg( array(
                    'action'   => 'gcpw_download_gift_card_pdf',
                    'card_id'  => $card_id,
                    'security' => wp_create_nonce( 'gcp_add_wallet_nonce' ),
                ), admin_url( 'admin-ajax.php' ) );
                ?>
                <button class="btn-action gcp-btn-copy" data-link="<?php echo esc_attr( $pdf_url ); ?>">
                    Copy link to card XXXX <?php echo esc_html( substr( $card_number, -4 ) ); ?>
                </button>
                <script>
                    document.querySelector('.gcp-btn-copy').addEventListener('click', function() {
                        console.log('Cliecked...');
                        window.open(this.dataset.link, '_blank');
                    });
                </script>
                
                <button class="btn-action btn-blue-outline gcp-btn-resend" data-card-id="<?php echo $card_id; ?>" >
                    Resend
                </button>

                <?php 
                // Check if Wallet Plugin is active (Adjust class name if needed)
                if ( class_exists( 'Gift_Cards_Plus_Wallet' ) || defined( 'GCP_WALLET_VERSION' ) ) { ?>
                    <button class="btn-action gcp-btn-add-wallet" 
                        data-card-id="<?php echo $card_id; ?>"
                        data-email="<?php echo esc_attr( $recipient_email ); ?>"
                        data-phone="<?php echo esc_attr( $recipient_phone ); ?>"
                        data-method="<?php echo esc_attr( $delivery_method ); ?>">
                        Add to Wallet
                    </button>
                <?php } ?>
            </div>

        </div>
    </div>

    <table class="gcp-items-table">
        <thead>
            <tr>
                <th>gift card</th>
                <th>gift card Number</th>
                <th>Message</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <?php if ( $card_image ) { ?>
                        <img src="<?php echo esc_url( $card_image ); ?>" class="gcp-table-thumb" alt="Thumb">
                    <?php } else { ?>
                        <span>-</span>
                    <?php } ?>
                </td>
                <td style="font-family: monospace; font-size: 15px; letter-spacing: 1px;">
                    <?php echo 'XXXXXXXX' ?>
                </td>
                <td style="max-width: 350px; line-height: 1.4; color: #555;">
                    <?php echo wp_kses_post( $card_message ?: '-' ); ?>
                </td>
                <td>
                    <?php echo wc_price( $card_price ); ?>
                </td>
                <td>
                    <span class="status-badge"><?php echo esc_html( $status ); ?></span>
                </td>
            </tr>
        </tbody>
    </table>

</div>

<div id="gcp-resend-modal" class="gcp-modal-overlay" style="display: none;">
    <div class="gcp-modal-content">
        <span class="gcp-modal-close">&times;</span>
        
        <div id="gcp-view-confirm" class="gcp-modal-view">
            <h3>Are you sure you would like to resend this card to the email <span class="gcp-resend-email-target"></span>?</h3>
            
            <div class="gcp-modal-actions">
                <button id="gcp-confirm-resend-btn" class="btn btn-black">Yes</button>
                <button id="gcp-trigger-update-view" class="btn-link">No, update delivery method</button>
            </div>
        </div>

        <div id="gcp-view-update" class="gcp-modal-view" style="display:none; text-align: left;">
            <h3 style="margin-top:0;">Update the recipient delivery method</h3>
            
            <div class="gcp-form-group">
                <label>Delivery Method</label>
                <select id="gcp-update-method-select" class="gcp-input">
                    <option value="email" <?php selected( stripos($delivery_method, 'sms') === false, true ); ?>>Email</option>
                    <option value="sms" <?php selected( stripos($delivery_method, 'sms') !== false, true ); ?>>Mobile (SMS)</option>
                </select>
            </div>

            <div id="gcp-container-email" class="gcp-form-group" style="display:none;">
                <label>New Email Address</label>
                <input type="text" id="gcp-input-email" class="gcp-input" 
                       placeholder="name@example.com" 
                       value="<?php echo esc_attr($recipient_email); ?>">
                <p id="gcp-error-email" class="gcp-error-text" style="display:none; color:red; font-size:12px; margin-top:5px;"></p>
            </div>

            <div id="gcp-container-mobile" class="gcp-form-group" style="display:none;">
                <label>New Mobile Number</label>
                <input type="text" id="gcp-input-mobile" class="gcp-input" 
                       placeholder="04XX XXX XXX" 
                       value="<?php echo esc_attr($recipient_phone); ?>">
                <p id="gcp-error-mobile" class="gcp-error-text" style="display:none; color:red; font-size:12px; margin-top:5px;"></p>
            </div>

            <div class="gcp-modal-actions">
                <button id="gcp-submit-update-btn" class="btn btn-black" style="width:100%;">Update</button>
            </div>
        </div>

        <div id="gcp-view-otp" class="gcp-modal-view" style="display:none; text-align: center;">
            <h2 style="margin-top:0; font-size: 24px; font-weight: bold; margin-bottom: 10px;">Check your email</h2>
            
            <p style="font-size:14px; color:#333; margin-bottom: 25px; line-height: 1.5;">
                We’ve sent you a one-time passcode. Please enter it below.
            </p>

            <div class="gcp-otp-wrapper" style="margin-bottom: 25px;">
                <div class="gcp-otp-inputs">
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-otp-digit" />
                </div>
                <p id="gcp-otp-error" class="gcp-error-text" style="display:none; color:red; margin-top:10px; font-size:13px;"></p>
            </div>
            
            <div class="gcp-modal-actions">
                <button id="gcp-verify-otp-btn" class="btn btn-black" style="width:100%; height: 48px; font-size: 16px;">Submit</button>
                
                <div style="margin-top: 15px; font-size: 13px; color: #666;">
                    <a href="#" id="gcp-resend-otp-link" class="btn-link" style="font-size:13px; color: #007bff; text-decoration: underline;">Resend now</a>
                    <span id="gcp-otp-timer" style="display:none; color: #999; margin-left: 5px;">(02:00)</span>
                </div>
            </div>
        </div>

        <div id="gcp-view-success" class="gcp-modal-view" style="display: none;">
            <h2 class="gcp-success-title">Success</h2>
            
            <div class="gcp-success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80" fill="none">
                    <path d="M40 0C17.92 0 0 17.92 0 40C0 62.08 17.92 80 40 80C62.08 80 80 62.08 80 40C80 17.92 62.08 0 40 0ZM40 72C22.36 72 8 57.64 8 40C8 22.36 22.36 8 40 8C57.64 8 72 22.36 72 40C72 57.64 57.64 72 40 72ZM55.52 25.16L32 48.68L24.48 41.16C22.92 39.6 20.4 39.6 18.84 41.16C17.28 42.72 17.28 45.24 18.84 46.8L29.2 57.16C30.76 58.72 33.28 58.72 34.84 57.16L61.2 30.8C62.76 29.24 62.76 26.72 61.2 25.16C59.64 23.6 57.08 23.6 55.52 25.16Z" fill="#67D6C8"/>
                </svg>
            </div>

            <p class="gcp-success-text">
                This card has been resent to <span class="gcp-resend-email-target" style="font-weight:600;"></span>
            </p>
        </div>

    </div>
</div>

<div id="gcp-wallet-modal" class="gcp-modal-overlay" style="display: none;">
    <div class="gcp-modal-content">
        <span class="gcp-modal-close">&times;</span>
        
        <div id="gcp-wallet-view-confirm" class="gcp-wallet-view">
            <h3>Are you sure you would like to add this card to the wallet of <span class="gcp-wallet-target-display" style="font-weight:bold;"></span>?</h3>
            
            <div class="gcp-modal-actions">
                <button id="gcp-wallet-confirm-btn" class="btn btn-black">Yes</button>
                <button id="gcp-wallet-trigger-update" class="btn-link">No update recipient</button>
            </div>
        </div>

        <div id="gcp-wallet-view-update" class="gcp-wallet-view" style="display:none; text-align: left;">
            <h3 style="margin-top:0;">Select recipient to add to wallet</h3>
            
            <div class="gcp-form-group" style="position: relative;">
                <label>Search Consumer Email</label>
                <input type="text" id="gcp-wallet-search-input" class="gcp-input" placeholder="Start typing email..." autocomplete="off">
                <ul id="gcp-wallet-search-results" class="gcp-search-dropdown" style="display:none;"></ul>
                <p id="gcp-wallet-update-error" class="gcp-error-text" style="display:none; color:red; font-size:12px; margin-top:5px;"></p>
            </div>

            <div class="gcp-modal-actions">
                <button id="gcp-wallet-submit-update-btn" class="btn btn-black" style="width:100%;">Select & Send OTP</button>
            </div>
        </div>

        <div id="gcp-wallet-view-otp" class="gcp-wallet-view" style="display:none; text-align: center;">
            <h2 style="margin-top:0; font-size: 24px; font-weight: bold; margin-bottom: 10px;">Check your email</h2>
            <p style="font-size:14px; color:#333; margin-bottom: 25px;">
                We’ve sent you a one-time passcode to verify this change.
            </p>

            <div class="gcp-otp-wrapper" style="margin-bottom: 25px;">
                <div class="gcp-otp-inputs" id="gcp-wallet-otp-inputs">
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-wallet-otp-digit gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-wallet-otp-digit gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-wallet-otp-digit gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-wallet-otp-digit gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-wallet-otp-digit gcp-otp-digit" />
                    <input type="text" maxlength="1" inputmode="numeric" class="gcp-wallet-otp-digit gcp-otp-digit" />
                </div>
                <p id="gcp-wallet-otp-error" class="gcp-error-text" style="display:none; color:red; margin-top:10px; font-size:13px;"></p>
            </div>
            
            <div class="gcp-modal-actions">
                <button id="gcp-wallet-verify-otp-btn" class="btn btn-black" style="width:100%;">Submit</button>
                <div style="margin-top: 15px;">
                    <a href="#" id="gcp-wallet-resend-otp" class="btn-link">Resend</a>
                </div>
            </div>
        </div>

        <div id="gcp-wallet-view-success" class="gcp-wallet-view" style="display: none; text-align: center;">
            <h2 class="gcp-success-title">Success</h2>
            <div class="gcp-success-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="11" stroke="#4DB6AC" stroke-width="2" fill="white"/>
                    <circle cx="12" cy="12" r="9" fill="#4DB6AC"/>
                    <path d="M8 12L11 15L16 9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <p class="gcp-success-text">
                Card successfully added to the wallet of <br>
                <a href="#" id="gcp-wallet-success-link" target="_blank" style="color: #007bff; font-weight: 600; text-decoration: underline;">
                    <span class="gcp-wallet-target-display"></span>
                </a>
            </p>
        </div>

        <div id="gcp-wallet-view-expired" class="gcp-wallet-view" style="display: none; text-align: center;">
            <h3 style="margin-top:0;">Card Added (Expired)</h3>
            <div style="padding: 20px 0; color: #d63638;">
                 <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <p style="font-size: 15px; line-height: 1.5; color: #333; margin-bottom: 25px;">
                Please note: This card has expired but is visible under the wallet (check status filters).
            </p>
            <div class="gcp-modal-actions">
                <button id="gcp-wallet-expired-ok-btn" class="btn btn-black" style="width: 100%;">Ok</button>
            </div>
        </div>

    </div>
</div>

<?php get_footer(); ?>