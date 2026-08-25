<?php
/**
 * Order submission and status fetching helpers for BHN integration.
 */

/**
 * Submit an eGift bulk order to BHN.
 *
 * @param array  $post_fields  Payload array (will be JSON-encoded).
 * @param string $uniq_id      Unique request ID for the RequestId header.
 * @return string              Raw JSON response string.
 */
function bhi_submit_order( $post_fields, $uniq_id ) {
    $json_post_fields = json_encode( $post_fields );

    $curl = curl_init();
    curl_setopt_array( $curl, [
        CURLOPT_URL            => BLACKHAWK_INTEGRATION_API_URL . 'rewardsOrderProcessing/v1/submitEgiftBulk',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'RequestId: '  . $uniq_id,
            'MerchantId: ' . ( function_exists( 'gcp_get_bhn_merchant_id' ) ? gcp_get_bhn_merchant_id() : '' ),
        ],
        CURLOPT_POSTFIELDS     => $json_post_fields,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSLCERT        => BLACKHAWK_INTEGRATION_SSLCERT,
        CURLOPT_SSLCERTTYPE    => BLACKHAWK_INTEGRATION_SSLCERTTYPE,
        CURLOPT_SSLCERTPASSWD  => function_exists( 'gcp_get_bhn_ssl_cert_password' ) ? gcp_get_bhn_ssl_cert_password() : '',
    ] );

    $response = curl_exec( $curl );

    if ( curl_errno( $curl ) ) {
        $curl_error = curl_error( $curl );
        $curl_errno = curl_errno( $curl );
        error_log( '[BHN Order] cURL Error: ' . $curl_error );
        curl_close( $curl );
        return json_encode( [
            'success' => false,
            'message' => 'Blackhawk API request failed: ' . $curl_error . ' (cURL errno: ' . $curl_errno . ')',
        ] );
    }

    curl_close( $curl );
    error_log( '[BHN Order] Raw API Response: ' . $response );
    return $response;
}

/**
 * Fetch order status from BHN.
 * First tries by orderNumber; falls back to requestId if not found.
 *
 * @param string      $order_number BHN order number.
 * @param string|null $request_id   Optional BHN request ID for fallback lookup.
 * @return array|null               Decoded response array or null on failure.
 */
function fetchOrderStatus( $order_number, $request_id = null ) {
    $FOS_unique            = uniqid( 'FOS_' );
    $client_program_number = function_exists( 'gcp_get_bhn_client_program_id' ) ? gcp_get_bhn_client_program_id() : '';
    $merchant_id           = function_exists( 'gcp_get_bhn_merchant_id' ) ? gcp_get_bhn_merchant_id() : '';
    $base_url              = BLACKHAWK_INTEGRATION_API_URL . 'rewardsOrderProcessing/v1/orderInfo/byKeys';

    $url      = $base_url . '?orderNumber=' . rawurlencode( $order_number ) . '&clientProgramNumber=' . rawurlencode( $client_program_number );
    $response = bhi_status_curl( $url, $FOS_unique, $merchant_id );

    // Fall back to requestId lookup if order number lookup returned nothing useful.
    if ( ( ! $response || empty( $response['orderNumber'] ) ) && $request_id ) {
        $url      = $base_url . '?requestId=' . rawurlencode( $request_id ) . '&clientProgramNumber=' . rawurlencode( $client_program_number );
        $response = bhi_status_curl( $url, $FOS_unique, $merchant_id );
    }

    return $response;
}

/**
 * Internal cURL helper for order status requests.
 *
 * @param string $url         Full request URL.
 * @param string $FOS_unique  Unique request ID header value.
 * @param string $merchant_id MerchantId header value.
 * @return array|null
 */
function bhi_status_curl( $url, $FOS_unique, $merchant_id ) {
    $curl = curl_init();
    curl_setopt_array( $curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSLCERT        => BLACKHAWK_INTEGRATION_SSLCERT,
        CURLOPT_SSLCERTTYPE    => BLACKHAWK_INTEGRATION_SSLCERTTYPE,
        CURLOPT_SSLCERTPASSWD  => function_exists( 'gcp_get_bhn_ssl_cert_password' ) ? gcp_get_bhn_ssl_cert_password() : '',
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'RequestId: '  . $FOS_unique,
            'MerchantId: ' . $merchant_id,
        ],
    ] );

    $response = curl_exec( $curl );

    if ( curl_errno( $curl ) ) {
        error_log( '[BHN Order Status] cURL error: ' . curl_error( $curl ) );
        curl_close( $curl );
        return null;
    }

    curl_close( $curl );

    $decoded = json_decode( $response, true );
    error_log( '[BHN Order Status] Response: ' . print_r( $decoded, true ) );
    return $decoded;
}

/**
 * Fetch eGift bulk code retrieval info (card numbers, PINs, etc.) for an order.
 *
 * @param string $order_number BHN order number.
 * @return array|null          Decoded response or null on failure.
 */
function fetchOtherOrderData( $order_number ) {
    $FOD_unique            = uniqid( 'FOD_' );
    $client_program_number = function_exists( 'gcp_get_bhn_client_program_id' ) ? gcp_get_bhn_client_program_id() : '';
    $merchant_id           = function_exists( 'gcp_get_bhn_merchant_id' ) ? gcp_get_bhn_merchant_id() : '';

    $curl = curl_init();
    curl_setopt_array( $curl, [
        CURLOPT_URL            => BLACKHAWK_INTEGRATION_API_URL
                                  . 'rewardsOrderProcessing/v1/eGiftBulkCodeRetrievalInfo/byKeys'
                                  . '?orderNumber=' . rawurlencode( $order_number )
                                  . '&clientProgramNumber=' . rawurlencode( $client_program_number ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSLCERT        => BLACKHAWK_INTEGRATION_SSLCERT,
        CURLOPT_SSLCERTTYPE    => BLACKHAWK_INTEGRATION_SSLCERTTYPE,
        CURLOPT_SSLCERTPASSWD  => function_exists( 'gcp_get_bhn_ssl_cert_password' ) ? gcp_get_bhn_ssl_cert_password() : '',
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'RequestId: '  . $FOD_unique,
            'MerchantId: ' . $merchant_id,
        ],
    ] );

    $response = curl_exec( $curl );

    if ( curl_errno( $curl ) ) {
        error_log( '[BHN Other Order Data] cURL error: ' . curl_error( $curl ) );
        curl_close( $curl );
        return null;
    }

    curl_close( $curl );
    error_log( '[BHN Other Order Data] Response: ' . $response );
    return json_decode( $response, true );
}