<?php
/**
 * Catalog sync — fetches products from BHN and creates/updates WooCommerce products.
 * Every run (cron or manual) is recorded in wp_bhn_cron_log for audit purposes.
 */

// ---------------------------------------------------------------------------
// BHN catalog credential getters — use same gcp_decrypt_option() pattern
// as the rest of the BHN plugin (LOGGED_IN_KEY, AES-256-CBC).
// Values are saved via the BHN Credentials admin page, same as other secrets.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'gcp_get_bhn_supplier_user_id' ) ) {
    function gcp_get_bhn_supplier_user_id() {
        return gcp_decrypt_option( 'gcp_bhn_supplier_user_id' );
    }
}

if ( ! function_exists( 'gcp_get_bhn_catalog_report_email' ) ) {
    function gcp_get_bhn_catalog_report_email() {
        return gcp_decrypt_option( 'gcp_bhn_catalog_report_email' );
    }
}


// STEP 1A: Index catalog by SKU
function bhn_index_catalog_by_sku($catalog)
{
    $indexed = [];

    foreach ($catalog as $product) {
        $sku = $product['contentProviderCode'] ?? $product['sku'] ?? $product['productId'] ?? null;

        if ($sku) {
            $indexed[$sku] = $product;
        }
    }

    return $indexed;
}

// STEP 1B: Compare old vs new
function bhn_get_catalog_diff($old, $new)
{
    $changes = [];

    // NEW + UPDATED
    foreach ($new as $sku => $new_product) {

        if (!isset($old[$sku])) {
            $changes[] = [
                'type' => 'ADDED',
                'sku'  => $sku,
                'field'=> '',
                'old'  => '',
                'new'  => json_encode($new_product)
            ];
            continue;
        }

        $fields_to_track = [
            'productName',
            'parentBrandName',
            'valueRestrictions'
        ];

        foreach ($fields_to_track as $key) {

            $new_value = $new_product[$key] ?? null;
            $old_value = $old[$sku][$key] ?? null;

            if ($new_value != $old_value) {
                $changes[] = [
                    'type'  => 'UPDATED',
                    'sku'   => $sku,
                ];
                break; // one change is enough
            }
        }
    }

    // REMOVED
    foreach ($old as $sku => $old_product) {
        if (!isset($new[$sku])) {
            $changes[] = [
                'type' => 'REMOVED',
                'sku'  => $sku,
                'field'=> '',
                'old'  => json_encode($old_product),
                'new'  => ''
            ];
        }
    }

    return $changes;
}


// -------------------------------------------------------------------------
// Generate a full catalog CSV snapshot and upload it to the Media Library.
//
// @param  array $bhi_products  Raw product array as returned by the BHN API.
// @return int|null             Attachment ID, or null on failure.
// -------------------------------------------------------------------------
function bhn_generate_catalog_csv( $bhi_products ) {
    if ( empty( $bhi_products ) || ! is_array( $bhi_products ) ) {
        return null;
    }

    // Flatten each product so every field the API returns gets a column —
    // nested arrays/objects (e.g. valueRestrictions, termsAndConditions)
    // become dot-notated columns like "valueRestrictions.minimum" instead
    // of being silently dropped.
    $flatten = static function ( $data, $prefix = '' ) use ( &$flatten ) {
        $flat = [];
        foreach ( $data as $key => $value ) {
            $flat_key = $prefix === '' ? $key : $prefix . '.' . $key;
            if ( is_array( $value ) ) {
                $flat += $flatten( $value, $flat_key );
            } else {
                $flat[ $flat_key ] = $value;
            }
        }
        return $flat;
    };

    $flat_products = [];
    $columns       = [];

    foreach ( $bhi_products as $product ) {
        $flat = is_array( $product ) ? $flatten( $product ) : [];
        $flat_products[] = $flat;
        foreach ( array_keys( $flat ) as $key ) {
            $columns[ $key ] = true;
        }
    }
    $columns = array_keys( $columns );

    $upload_dir = wp_upload_dir();
    $filename   = 'catalog-full-' . current_time( 'Y-m-d-His' ) . '.csv';
    $file_path  = trailingslashit( $upload_dir['path'] ) . $filename;

    $fh = fopen( $file_path, 'w' );
    if ( ! $fh ) {
        return null;
    }

    fputcsv( $fh, $columns );

    // Strip HTML tags from text values before writing — some hosts
    // (e.g. WP Engine) content-sniff uploaded files and will refuse to serve
    // a .csv whose content looks like markup rather than plain text.
    $plain = static function ( $value ) {
        return is_string( $value ) ? wp_strip_all_tags( $value ) : $value;
    };

    foreach ( $flat_products as $flat ) {
        $row = [];
        foreach ( $columns as $key ) {
            $row[] = $plain( $flat[ $key ] ?? '' );
        }
        fputcsv( $fh, $row );
    }

    fclose( $fh );

    // Some hosts (e.g. WP Engine) content-sniff uploaded files and can refuse
    // to serve a .csv that contains embedded HTML markup (our product
    // descriptions/redemption info do). Verify the file actually landed on
    // disk before registering it — otherwise we'd create an attachment
    // record pointing at a file that was silently rejected/removed.
    if ( ! file_exists( $file_path ) || ! filesize( $file_path ) ) {
        error_log( "BHN catalog CSV: file was not found on disk after writing ({$file_path}). The host may be blocking/stripping .csv uploads." );
        return null;
    }

    // ---- Register the CSV as a Media Library attachment ----
    $attachment = [
        'post_mime_type' => 'text/csv',
        'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment( $attachment, $file_path );
    if ( is_wp_error( $attach_id ) || ! $attach_id ) {
        return null;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    // Final check: confirm the file WordPress registered still exists where
    // it should — catches the case where something removes the physical
    // file between our write and now (e.g. a host-level scan/quarantine).
    $final_path = get_attached_file( $attach_id );
    if ( ! $final_path || ! file_exists( $final_path ) ) {
        error_log( "BHN catalog CSV: attachment {$attach_id} registered but file missing at {$final_path}. Removing orphaned attachment record." );
        wp_delete_attachment( $attach_id, true );
        return null;
    }

    return $attach_id;
}

// -------------------------------------------------------------------------
// Stream a catalog CSV attachment through PHP instead of linking directly
// to its /wp-content/uploads/... URL.
//
// WP Engine's nginx layer returns a bare "403 Forbidden" for direct requests
// to .csv files in uploads (confirmed by requesting the file URL directly —
// it never reaches WordPress/PHP), so the Media Library's normal file URL
// can never work for this file type on this host. Serving it through
// admin-ajax.php sidesteps that block, since PHP execution isn't restricted.
// -------------------------------------------------------------------------
add_action( 'wp_ajax_bhn_download_catalog_csv', 'bhn_download_catalog_csv' );

function bhn_download_catalog_csv() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized', 403 );
    }

    $attach_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
    if ( ! $attach_id || 'attachment' !== get_post_type( $attach_id ) ) {
        wp_die( 'Invalid attachment.', 404 );
    }

    check_admin_referer( 'bhn_download_catalog_csv_' . $attach_id );

    $file_path = get_attached_file( $attach_id );
    if ( ! $file_path || ! file_exists( $file_path ) ) {
        wp_die( 'File not found on server.', 404 );
    }

    $filename = basename( $file_path );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize( $file_path ) );

    readfile( $file_path );
    exit;
}

function bhn_is_catalog_csv_attachment( $post ) {
    return $post && 'text/csv' === $post->post_mime_type && 0 === strpos( $post->post_title, 'catalog-full-' );
}

function bhn_get_catalog_csv_download_url( $attach_id ) {
    return wp_nonce_url(
        admin_url( 'admin-ajax.php?action=bhn_download_catalog_csv&attachment_id=' . $attach_id ),
        'bhn_download_catalog_csv_' . $attach_id
    );
}

// Add a "Download CSV" link next to BHN catalog CSV attachments in the
// Media Library's row actions, pointing at the admin-ajax streamer above
// instead of the (blocked) direct file URL.
add_filter( 'media_row_actions', 'bhn_add_catalog_csv_download_link', 10, 2 );

function bhn_add_catalog_csv_download_link( $actions, $post ) {
    if ( ! bhn_is_catalog_csv_attachment( $post ) ) {
        return $actions;
    }

    $url = bhn_get_catalog_csv_download_url( $post->ID );
    $actions['bhn_download_csv'] = '<a href="' . esc_url( $url ) . '">Download CSV</a>';

    return $actions;
}

// Also surface a working download link on the single-attachment "Attachment
// details" edit screen, since its built-in "Download file" link always
// points at the blocked static URL and cannot be repointed.
add_filter( 'attachment_fields_to_edit', 'bhn_add_catalog_csv_download_field', 10, 2 );

function bhn_add_catalog_csv_download_field( $form_fields, $post ) {
    if ( ! bhn_is_catalog_csv_attachment( $post ) ) {
        return $form_fields;
    }

    $url = bhn_get_catalog_csv_download_url( $post->ID );

    $form_fields['bhn_download_csv'] = [
        'label' => 'Download (working link)',
        'input' => 'html',
        'html'  => '<a href="' . esc_url( $url ) . '" class="button">Download CSV</a>'
                 . '<p class="description">The default "Download file" link below is blocked by the host for .csv files — use this link instead.</p>',
    ];

    return $form_fields;
}


function bhn_send_catalog_diff_email( $changes, $old_catalog, $new_catalog ) {
    $to      = function_exists( 'gcp_get_bhn_catalog_report_email' ) ? gcp_get_bhn_catalog_report_email() : '';
    $to      = $to ?: get_option( 'admin_email' );
    $subject = 'BHN Catalog Sync Report - ' . current_time( 'Y-m-d H:i:s' );

    // -------------------------------------------------
    // Group changes by type
    // -------------------------------------------------
    $added_skus   = [];
    $updated_skus = [];
    $removed_skus = [];

    foreach ( $changes as $change ) {
        if ( $change['type'] === 'ADDED' ) {
            $added_skus[] = $change['sku'];
        } elseif ( $change['type'] === 'UPDATED' ) {
            $updated_skus[] = $change['sku'];
        } elseif ( $change['type'] === 'REMOVED' ) {
            $removed_skus[] = $change['sku'];
        }
    }

    $added_count   = count( $added_skus );
    $updated_count = count( $updated_skus );
    $removed_count = count( $removed_skus );

    // -------------------------------------------------
    // Helper: build one product card
    // -------------------------------------------------
    $build_product_card = function ( $sku, $status, $old_product, $new_product ) {
        $status_class = strtolower( $status );

        // For REMOVED products show old data; for all others show new data.
        $product = ! empty( $new_product ) ? $new_product : $old_product;

        $product_name = esc_html( $product['productName']     ?? 'N/A' );
        $brand_name   = esc_html( $product['parentBrandName'] ?? 'N/A' );
        $description  = wp_kses_post( $product['productDescription'] ?? '' );
        $redemption   = nl2br( esc_html( $product['redemptionInfo'] ?? '' ) );
        $terms        = nl2br( esc_html( $product['termsAndConditions']['text'] ?? '' ) );

        $min_price = $product['valueRestrictions']['minimum'] ?? '';
        $max_price = $product['valueRestrictions']['maximum'] ?? '';
        $old_min   = $old_product['valueRestrictions']['minimum'] ?? '';
        $old_max   = $old_product['valueRestrictions']['maximum'] ?? '';

        // Price cell: show old -> new for UPDATED, plain range for others.
        if ( $status === 'UPDATED' ) {
            $price_cell = '<span class="highlight-old">Old: ' . esc_html( $old_min ) . ' &ndash; ' . esc_html( $old_max ) . '</span><br>'
                        . '<span class="highlight-new">New: ' . esc_html( $min_price ) . ' &ndash; ' . esc_html( $max_price ) . '</span>';
        } else {
            $price_cell = esc_html( $min_price );
            if ( $max_price !== '' && $max_price !== $min_price ) {
                $price_cell .= ' &ndash; ' . esc_html( $max_price );
            }
        }

        // What changed — shown only for UPDATED cards.
        $changed_fields_html = '';
        if ( $status === 'UPDATED' ) {
            $tracked = [
                'productName'        => 'Product Name',
                'parentBrandName'    => 'Brand Name',
                'productDescription' => 'Description',
                'redemptionInfo'     => 'Redemption Info',
            ];
            $diffs = [];
            foreach ( $tracked as $field => $label ) {
                $o = wp_strip_all_tags( $old_product[ $field ] ?? '' );
                $n = wp_strip_all_tags( $new_product[ $field ] ?? '' );
                if ( $o !== $n ) {
                    $diffs[] = '<tr>'
                             . '<th>' . esc_html( $label ) . '</th>'
                             . '<td>'
                             . '<span class="highlight-old">Old: ' . esc_html( $o ) . '</span><br>'
                             . '<span class="highlight-new">New: ' . esc_html( $n ) . '</span>'
                             . '</td>'
                             . '</tr>';
                }
            }
            if ( ! empty( $diffs ) ) {
                $changed_fields_html = '<tr><th colspan="2" class="section-title">&#9998; Changed Fields</th></tr>'
                                     . implode( '', $diffs );
            }
        }

        return '
        <div class="product">
            <div class="product-header ' . $status_class . '">
                ' . esc_html( $status ) . ' &mdash; ' . $product_name . '
            </div>
            <table>
                <tr><th>SKU</th><td>' . esc_html( $sku ) . '</td></tr>
                <tr><th>Brand</th><td>' . $brand_name . '</td></tr>
                <tr><th>Price Range</th><td>' . $price_cell . '</td></tr>
                <tr><th>Description</th><td>' . $description . '</td></tr>
                <tr><th>Redemption Info</th><td>' . $redemption . '</td></tr>
                <tr><th>Terms &amp; Conditions</th><td>' . $terms . '</td></tr>
                ' . $changed_fields_html . '
            </table>
        </div>';
    };

    // -------------------------------------------------
    // Build email HTML
    // -------------------------------------------------
    $message = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 20px; }
    h2   { color: #222; margin-bottom: 10px; }
    h3   { margin: 30px 0 10px; padding: 10px 15px; border-radius: 4px; color: #fff; font-size: 16px; }
    h3.section-added   { background: #28a745; }
    h3.section-updated { background: #f39c12; }
    h3.section-removed { background: #dc3545; }
    .summary { background: #f5f5f5; border: 1px solid #ddd; padding: 15px 20px; margin-bottom: 30px; border-radius: 4px; }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; color: #fff; font-weight: bold; margin-right: 8px; font-size: 13px; }
    .badge-added   { background: #28a745; }
    .badge-updated { background: #f39c12; }
    .badge-removed { background: #dc3545; }
    .no-changes { background: #e9f7ef; border: 1px solid #b2dfdb; padding: 15px 20px; border-radius: 4px; color: #2e7d32; }
    .product { border: 1px solid #ddd; margin-bottom: 20px; border-radius: 6px; overflow: hidden; }
    .product-header { padding: 10px 15px; color: #fff; font-size: 15px; font-weight: bold; }
    .added   { background: #28a745; }
    .updated { background: #f39c12; }
    .removed { background: #dc3545; }
    table { width: 100%; border-collapse: collapse; }
    table th { width: 200px; background: #f9f9f9; text-align: left; padding: 9px 12px; border: 1px solid #eee; font-size: 13px; vertical-align: top; }
    table td { padding: 9px 12px; border: 1px solid #eee; font-size: 13px; vertical-align: top; }
    .section-title { background: #f0f0f0 !important; font-weight: bold; color: #555; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    .highlight-old { color: #dc3545; font-weight: bold; }
    .highlight-new { color: #28a745; font-weight: bold; }
    hr.divider { border: none; border-top: 2px solid #eee; margin: 30px 0; }
</style>
</head>
<body>';

    // -------------------------------------------------
    // Header + summary block
    // -------------------------------------------------
    $message .= '
    <h2>BHN Catalog Sync Report</h2>
    <div class="summary">
        <strong>Sync completed at:</strong> ' . current_time( 'Y-m-d H:i:s' ) . '<br>
        <strong>Total products in catalog:</strong> ' . count( $new_catalog ) . '<br><br>
        <span class="badge badge-added">' . $added_count . ' Added</span>
        <span class="badge badge-updated">' . $updated_count . ' Updated</span>
        <span class="badge badge-removed">' . $removed_count . ' Removed</span>
    </div>';

    // -------------------------------------------------
    // No changes
    // -------------------------------------------------
    if ( empty( $changes ) ) {
        $message .= '
        <div class="no-changes">
            <strong>No changes detected in this sync.</strong><br>
            All ' . count( $new_catalog ) . ' products were compared against the previous snapshot &#8212;
            no products were added, updated, or removed. No action required.
        </div>';
    }

    // -------------------------------------------------
    // SECTION 1: ADDED products (green)
    // -------------------------------------------------
    if ( ! empty( $added_skus ) ) {
        $message .= '<h3 class="section-added">&#43; Added Products (' . $added_count . ')</h3>';
        foreach ( $added_skus as $sku ) {
            $message .= $build_product_card( $sku, 'ADDED', [], $new_catalog[ $sku ] ?? [] );
        }
        if ( ! empty( $updated_skus ) || ! empty( $removed_skus ) ) {
            $message .= '<hr class="divider">';
        }
    }

    // -------------------------------------------------
    // SECTION 2: UPDATED products (orange)
    // -------------------------------------------------
    if ( ! empty( $updated_skus ) ) {
        $message .= '<h3 class="section-updated">&#8635; Updated Products (' . $updated_count . ')</h3>';
        foreach ( $updated_skus as $sku ) {
            $message .= $build_product_card( $sku, 'UPDATED', $old_catalog[ $sku ] ?? [], $new_catalog[ $sku ] ?? [] );
        }
        if ( ! empty( $removed_skus ) ) {
            $message .= '<hr class="divider">';
        }
    }

    // -------------------------------------------------
    // SECTION 3: REMOVED products (red)
    // -------------------------------------------------
    if ( ! empty( $removed_skus ) ) {
        $message .= '<h3 class="section-removed">&#8722; Removed Products (' . $removed_count . ')</h3>';
        foreach ( $removed_skus as $sku ) {
            $message .= $build_product_card( $sku, 'REMOVED', $old_catalog[ $sku ] ?? [], [] );
        }
    }

    $message .= '</body></html>';

    // -------------------------------------------------
    // Send
    // -------------------------------------------------
    wp_mail( $to, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
}



function blackhawk_run_catalog_sync( $run_type = 'cron' ) {
    if ( ! ini_get( 'safe_mode' ) ) {
        set_time_limit( 300 );
    }

    global $wpdb;

    $log_table   = $wpdb->prefix . 'bhn_cron_log';
    $triggered   = current_time( 'Y-m-d H:i:s' );
    $bhi_uniq_id = uniqid( 'CLI_' );

    // ---- Insert "started" row so even a fatal mid-run is visible ----
    $wpdb->insert( $log_table, [
        'run_type'     => sanitize_text_field( $run_type ),
        'triggered_at' => $triggered,
        'status'       => 'started',
        'notes'        => 'RequestId: ' . $bhi_uniq_id,
    ] );
    $log_id = $wpdb->insert_id;

    // ---- cURL to BHN catalog API (with retry on 504) ----
    $curl_opts = [
        CURLOPT_URL            => BLACKHAWK_INTEGRATION_API_URL
                                  . 'rewardsCatalogProcessing/v1/clientProgram/byKey?clientProgramId='
                                  . ( function_exists( 'gcp_get_bhn_client_program_id' ) ? gcp_get_bhn_client_program_id() : '' ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSLCERT        => BLACKHAWK_INTEGRATION_SSLCERT,
        CURLOPT_SSLCERTTYPE    => BLACKHAWK_INTEGRATION_SSLCERTTYPE,
        CURLOPT_SSLCERTPASSWD  => function_exists( 'gcp_get_bhn_ssl_cert_password' ) ? gcp_get_bhn_ssl_cert_password() : '',
        CURLOPT_HTTPHEADER     => [
            'MerchantId: ' . ( function_exists( 'gcp_get_bhn_merchant_id' ) ? gcp_get_bhn_merchant_id() : '' ),
            'RequestId: '  . $bhi_uniq_id,
            'accept: application/json',
        ],
    ];

    $max_retries  = 3;
    $bhi_response = '';
    $bhi_err      = '';
    $http_code    = 0;

    for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
        $curl = curl_init();
        curl_setopt_array( $curl, $curl_opts );
        $bhi_response = curl_exec( $curl );
        $bhi_err      = curl_error( $curl );
        $http_code    = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
        unset( $curl );

        $is_gateway_error = in_array( $http_code, [ 502, 503, 504 ], true );
        if ( ! $bhi_err && ! $is_gateway_error ) {
            break;
        }

        $reason = $bhi_err ? 'cURL error: ' . $bhi_err : "HTTP {$http_code}";

        if ( $attempt < $max_retries ) {
            sleep( 10 );
        }
    }

    if ( $bhi_err ) {
        $msg = 'cURL error: ' . $bhi_err;
        $wpdb->update( $log_table, [
            'finished_at' => current_time( 'Y-m-d H:i:s' ),
            'status'      => 'failed',
            'notes'       => $msg,
        ], [ 'id' => $log_id ] );
        return;
    }

    $bhi_data = json_decode( $bhi_response, true );

    if ( empty( $bhi_data ) || json_last_error() !== JSON_ERROR_NONE ) {
        $msg = "Invalid JSON from API (HTTP {$http_code}): " . substr( $bhi_response, 0, 300 );
        $wpdb->update( $log_table, [
            'finished_at' => current_time( 'Y-m-d H:i:s' ),
            'status'      => 'failed',
            'notes'       => $msg,
        ], [ 'id' => $log_id ] );
        return;
    }

    // ---- Save summary to options for quick WP-admin visibility ----
    $summary = [ 'requestId' => $bhi_uniq_id, 'http_code' => $http_code ];
    foreach ( [ 'programName', 'programType', 'currency' ] as $key ) {
        if ( isset( $bhi_data[ $key ] ) ) {
            $summary[ $key ] = $bhi_data[ $key ];
        }
    }

    $bhi_products = [];
    if ( isset( $bhi_data['products'] ) && is_array( $bhi_data['products'] ) ) {
        $bhi_products              = $bhi_data['products'];
        $summary['products_count'] = count( $bhi_products );
    }
    update_option( 'blackhawk_integration_last_api_call', $summary );

    if ( empty( $bhi_products ) ) {
        $msg = 'API returned 0 products.';
        $wpdb->update( $log_table, [
            'finished_at' => current_time( 'Y-m-d H:i:s' ),
            'status'      => 'failed',
            'notes'       => $msg,
        ], [ 'id' => $log_id ] );
        return;
    }

    // ---- Require media helpers for CSV upload + image sideloading ----
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    

    // ===============================
    // DIFF + CSV GENERATION START
    // ===============================

    // STEP 1: Get old snapshot
    $old_catalog_raw = get_option( 'bhn_catalog_snapshot', [] );

    // STEP 2: Index both catalogs
    $current_catalog = bhn_index_catalog_by_sku( $bhi_products );
    $old_catalog     = bhn_index_catalog_by_sku( $old_catalog_raw );

    // STEP 3: Get differences
    $changes = bhn_get_catalog_diff( $old_catalog, $current_catalog );

    if ( ! empty( $changes ) ) {
        bhn_send_catalog_diff_email( $changes, $old_catalog, $current_catalog );
    } else {
        bhn_send_catalog_diff_email( [], $old_catalog, $current_catalog );
    }

    // STEP 4: Full catalog CSV snapshot, uploaded to the Media Library
    bhn_generate_catalog_csv( $bhi_products );
    // ===============================
    // DIFF + CSV GENERATION END
    // ===============================

    // ---- ACF supplier field key ----
    $supplier_field_key = defined( 'BHN_ACF_SUPPLIER_FIELD_KEY' ) ? BHN_ACF_SUPPLIER_FIELD_KEY : 'supplier';
    $supplier_user_id   = (int) ( function_exists( 'gcp_get_bhn_supplier_user_id' ) ? gcp_get_bhn_supplier_user_id() : 0 );
    if ( ! $supplier_user_id || ! get_user_by( 'id', $supplier_user_id ) ) {
        $supplier_user_id = 0;
    }

    $created = 0;
    $updated = 0;
    $failed  = 0;

     foreach ( $bhi_products as $bhi_product ) {

        // ----------------------------------------------------------------
        // 1. Extract & sanitise all fields from the API response
        // ----------------------------------------------------------------
        $sku                = isset( $bhi_product['contentProviderCode'] )
                              ? sanitize_text_field( $bhi_product['contentProviderCode'] ) : '';

        $productName        = isset( $bhi_product['productName'] )
                              ? sanitize_text_field( $bhi_product['productName'] ) : '';

        $productDescription = isset( $bhi_product['productDescription'] )
                              ? wp_kses_post( $bhi_product['productDescription'] ) : '';

        $parentBrandName    = isset( $bhi_product['parentBrandName'] )
                              ? sanitize_text_field( $bhi_product['parentBrandName'] ) : '';

        $redemptionInfo     = isset( $bhi_product['redemptionInfo'] )
                              ? sanitize_textarea_field( $bhi_product['redemptionInfo'] ) : '';

        $termsAndConditions = $bhi_product['termsAndConditions']['text'] ?? '';

        $productImage       = isset( $bhi_product['productImage'] )
                              ? esc_url_raw( $bhi_product['productImage'] ) : '';

        $logoImage          = isset( $bhi_product['logoImage'] ) && ! empty( $bhi_product['logoImage'] )
                              ? esc_url_raw( $bhi_product['logoImage'] ) : '';

        $minPrice           = isset( $bhi_product['valueRestrictions']['minimum'] )
                              ? floatval( $bhi_product['valueRestrictions']['minimum'] ) : 0;

        $maxPrice           = isset( $bhi_product['valueRestrictions']['maximum'] )
                              ? floatval( $bhi_product['valueRestrictions']['maximum'] ) : 0;

        if ( empty( $productName ) ) {
            $failed++;
            continue;
        }

        // ----------------------------------------------------------------
        // 2. Get or create the WooCommerce product object
        // ----------------------------------------------------------------
        $product_id = wc_get_product_id_by_sku( $sku );
        $is_new     = empty( $product_id );

        if ( $is_new ) {
            $product_obj = new WC_Product_Simple();
            $product_obj->set_sku( $sku );
            $product_obj->set_status( 'publish' );  // ← was 'draft'
        } else {
            $product_obj = wc_get_product( $product_id );
            if ( ! $product_obj ) {
                $failed++;
                continue;
            }
        }

        // Add this line after the if/else block, before set_name():
        $product_obj->set_catalog_visibility( 'visible' );

        // ----------------------------------------------------------------
        // 3. Core product fields
        // ----------------------------------------------------------------
        $product_obj->set_name( $productName );
        $product_obj->set_description( $productDescription );
        // Price is set to minimum as the base; range meta is stored separately below.
        $product_obj->set_regular_price( $minPrice );
        $product_obj->set_price( $minPrice );

        // ----------------------------------------------------------------
        // 4. Featured image  (productImage)
        // ----------------------------------------------------------------
        if ( ! empty( $productImage ) ) {
            $featured_id = bhn_sideload_image( $productImage, $product_obj->get_id(), $sku );
            if ( $featured_id ) {
                $product_obj->set_image_id( $featured_id );
            }
        }

        // ----------------------------------------------------------------
        // 5. Save product so we have a stable post ID before setting meta
        // ----------------------------------------------------------------
        $updated_product_id = $product_obj->save();

        if ( ! $updated_product_id ) {
            $failed++;
            continue;
        }

        // ----------------------------------------------------------------
        // 6. Gallery image  (logoImage → first gallery slot)
        // ----------------------------------------------------------------
        if ( ! empty( $logoImage ) ) {
            $logo_id = bhn_sideload_image( $logoImage, $updated_product_id, $sku . '-logo' );
            if ( $logo_id ) {
                // WooCommerce stores gallery as underscore-separated attachment IDs.
                update_post_meta( $updated_product_id, '_product_image_gallery', (string) $logo_id );
            }
        } else {
            // Clear gallery if BHN sends no logo for this product.
            update_post_meta( $updated_product_id, '_product_image_gallery', '' );
        }

        // ----------------------------------------------------------------
        // 7. product_brand taxonomy  (parentBrandName)
        // ----------------------------------------------------------------
        if ( ! empty( $parentBrandName ) && taxonomy_exists( 'product_brand' ) ) {
            $term = term_exists( $parentBrandName, 'product_brand' );
            if ( ! $term ) {
                $term = wp_insert_term( $parentBrandName, 'product_brand' );
            }
            if ( ! is_wp_error( $term ) ) {
                $brand_id = is_array( $term ) ? $term['term_id'] : $term;
                wp_set_object_terms( $updated_product_id, intval( $brand_id ), 'product_brand' );
            }
        }

        // ----------------------------------------------------------------
        // 8. Meta fields
        // ----------------------------------------------------------------

        // contentProviderCode  →  _supplier_sku
        update_post_meta( $updated_product_id, '_supplier_sku', $sku );

        // redemptionInfo
        update_post_meta( $updated_product_id, 'redemptionInfo', $redemptionInfo );

        update_post_meta( $updated_product_id, 'terms_conditions', $termsAndConditions );

        // valueRestrictions
        update_post_meta( $updated_product_id, 'variable_range_from', $minPrice );
        update_post_meta( $updated_product_id, 'variable_range_to',   $maxPrice );
        update_post_meta( $updated_product_id, '_reedem_at_intervals',   '1' );
        update_post_meta( $updated_product_id, 'always_on',   'Yes' );

        // ACF price fields (if ACF is active)
        if ( function_exists( 'update_field' ) ) {
            update_field( 'minimum_price',      $minPrice,        $updated_product_id );
            update_field( 'maximum_price',      $maxPrice,        $updated_product_id );
            $denomination_type = ($minPrice == $maxPrice) ? 'fixed' : 'variable';
            update_field( 'denomination_type', $denomination_type,       $updated_product_id );
            update_field( 'variable_range_from', $minPrice,       $updated_product_id );
            update_field( 'variable_range_to',   $maxPrice,       $updated_product_id );
            update_field( '_reedem_at_intervals',   '1',       $updated_product_id );
        }

        // General visibility / BHN flags
        update_post_meta( $updated_product_id, '_visibility',           'visible' );
        update_post_meta( $updated_product_id, '_is_blackhawk_product', 'yes_' . $updated_product_id );

        // ----------------------------------------------------------------
        // 9. ACF supplier field  (user ID 362)
        // ----------------------------------------------------------------
        $supplier_value = $supplier_user_id;
        if ( function_exists( 'acf_get_field' ) ) {
            $field_obj = acf_get_field( $supplier_field_key );
            if ( is_array( $field_obj ) && ! empty( $field_obj['multiple'] ) ) {
                $supplier_value = [ $supplier_user_id ];
            }
        }
        if ( function_exists( 'update_field' ) ) {
            update_field( $supplier_field_key, $supplier_value, $updated_product_id );
        }
        // Raw meta fallback
        update_post_meta( $updated_product_id, 'supplier', $supplier_value );
        if ( $supplier_field_key !== 'supplier' ) {
            update_post_meta( $updated_product_id, '_supplier', $supplier_field_key );
        }

        $is_new ? $created++ : $updated++;
    }

    // ---- Calculate next cron run time ----
    $next_cron     = wp_next_scheduled( 'scheduled_blackhawk_integration_catalogue' );
    $next_cron_str = $next_cron
        ? get_date_from_gmt( date( 'Y-m-d H:i:s', $next_cron ), 'Y-m-d H:i:s' )
        : null;

    // ---- Update log row with final result ----
    $notes = "API products: " . count( $bhi_products )
           . " | Program: "   . ( $summary['programName'] ?? 'N/A' )
           . " | Currency: "  . ( $summary['currency']    ?? 'N/A' )
           . " | RequestId: " . $bhi_uniq_id;

    $wpdb->update( $log_table, [
        'finished_at'      => current_time( 'Y-m-d H:i:s' ),
        'status'           => 'success',
        'products_created' => $created,
        'products_updated' => $updated,
        'products_failed'  => $failed,
        'next_run_at'      => $next_cron_str,
        'notes'            => $notes,
    ], [ 'id' => $log_id ] );

    // Save latest snapshot for next comparison
    update_option('bhn_catalog_snapshot', $bhi_products, false);
}

// -------------------------------------------------------------------------
// Helper: sideload an image from a remote URL, reusing existing attachment
// if the same source URL or filename has already been uploaded.
//
// @param  string $url        Remote image URL.
// @param  int    $post_id    Parent post ID (used only when inserting new).
// @return int|null           Attachment ID, or null on failure.
// -------------------------------------------------------------------------
function bhn_sideload_image( $url, $post_id = 0, $sku = '' ) {
    global $wpdb;

    if ( empty( $url ) ) {
        return null;
    }

    // Strip query string for filename matching (e.g. ?dt=1615227766940).
    $url_without_qs = strtok( $url, '?' );
    $filename        = basename( parse_url( $url_without_qs, PHP_URL_PATH ) );

    $upload_dir = wp_upload_dir();

    // CHECK 1: Match by source URL stored in _bhn_source_url meta (most reliable).
    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_bhn_source_url'
               AND meta_value = %s
             LIMIT 1",
            $url_without_qs
        )
    );

    if ( $existing_id ) {
        $post_exists   = get_post( intval( $existing_id ) );
        $attached_file = get_post_meta( intval( $existing_id ), '_wp_attached_file', true );
        $full_path     = $attached_file ? trailingslashit( $upload_dir['basedir'] ) . $attached_file : '';

        if ( $post_exists && $attached_file && file_exists( $full_path ) ) {
            return intval( $existing_id );
        }

        if ( $post_exists ) {
            wp_delete_attachment( intval( $existing_id ), true );
        }
        // Always delete the orphaned meta so next run starts clean.
        $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_bhn_source_url', 'meta_value' => $url_without_qs ] );
    }

    // CHECK 2: Fallback — match by filename in _wp_attached_file.
    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type  = 'attachment'
               AND pm.meta_key  = '_wp_attached_file'
               AND pm.meta_value LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like( $filename )
        )
    );

    if ( $existing_id ) {
        $post_exists   = get_post( intval( $existing_id ) );
        $attached_file = get_post_meta( intval( $existing_id ), '_wp_attached_file', true );
        $full_path     = $attached_file ? trailingslashit( $upload_dir['basedir'] ) . $attached_file : '';

        if ( $post_exists && $attached_file && file_exists( $full_path ) ) {
            update_post_meta( intval( $existing_id ), '_bhn_source_url', $url_without_qs );
            return intval( $existing_id );
        }

        if ( $post_exists ) {
            wp_delete_attachment( intval( $existing_id ), true );
        }
    }

    $image_id = media_sideload_image( $url, $post_id, '', 'id' );

    if ( is_wp_error( $image_id ) || empty( $image_id ) || ! is_int( $image_id ) ) {
        return null;
    }

    // Set a proper title so the Media Library doesn't show "uploading...".
    $title = ! empty( $sku ) ? sanitize_text_field( $sku ) : pathinfo( $filename, PATHINFO_FILENAME );
    $title = sanitize_text_field( str_replace( [ '-', '_' ], ' ', $title ) );
    wp_update_post( [
        'ID'         => intval( $image_id ),
        'post_title' => $title,
    ] );

    update_post_meta( intval( $image_id ), '_bhn_source_url', $url_without_qs );

    return intval( $image_id );
}