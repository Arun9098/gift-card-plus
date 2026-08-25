<?php

if ( ! function_exists( 'gcp_decrypt_option' ) ) {
    function gcp_decrypt_option( $option_key ) {
        $encrypted  = get_option( $option_key, '' );
        $enc_secret = defined( 'BHN_ENCRYPTION_SECRET' ) ? BHN_ENCRYPTION_SECRET : ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '' );
        if ( $encrypted === '' || $enc_secret === '' ) {
            return '';
        }
        $key  = hash( 'sha256', $enc_secret, true );
        $data = base64_decode( $encrypted, true );
        if ( $data === false || strlen( $data ) < 16 ) {
            return '';
        }
        $iv  = substr( $data, 0, 16 );
        $ct  = substr( $data, 16 );
        $dec = openssl_decrypt( $ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return $dec !== false ? $dec : '';
    }
}

/**
 * Encrypt a value and store it under the given option key.
 * Counterpart to gcp_decrypt_option() — same AES-256-CBC scheme keyed off
 * BHN_ENCRYPTION_SECRET (falling back to LOGGED_IN_KEY), matching blackhawk-integration.php's
 * gcp_decrypt_option() exactly, since that definition wins at runtime (plugins load first).
 *
 * @param string $option_key
 * @param string $value
 * @return bool True on success, false if the encryption secret is unavailable or encryption failed.
 */
if ( ! function_exists( 'gcp_encrypt_option' ) ) {
    function gcp_encrypt_option( $option_key, $value ) {
        $enc_secret = defined( 'BHN_ENCRYPTION_SECRET' ) ? BHN_ENCRYPTION_SECRET : ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '' );
        if ( $enc_secret === '' ) {
            return false;
        }
        $key = hash( 'sha256', $enc_secret, true );
        $iv  = openssl_random_pseudo_bytes( 16 );
        $ct  = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        if ( $ct === false ) {
            return false;
        }
        return update_option( $option_key, base64_encode( $iv . $ct ) );
    }
}


/**
 * Send SMS via SMSBroadcast API
 * 
 * @param string $phone Phone number (should be in format like 61402472256)
 * @param string $message SMS message content
 * @return array|false Returns response array on success, false on failure
 */
function send_sms_via_smsbroadcast($phone, $message) {
    $logger = wc_get_logger();
    $context = ['source' => 'SMSBroadcast'];
    
    // SMSBroadcast credentials — stored encrypted in options table (gcp_sms_broadcast_username / _password)
    $username = gcp_decrypt_option( 'gcp_sms_broadcast_username' );
    $password = gcp_decrypt_option( 'gcp_sms_broadcast_password' );
    if ( empty( $username ) || empty( $password ) ) {
        return false;
    }
    $api_url = 'https://api.smsbroadcast.com.au/api-adv.php';
    
    // Clean phone number - remove spaces, dashes, and ensure it starts with country code
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If phone doesn't start with 61 (Australia), add it
    if (!preg_match('/^61/', $phone)) {
        // Remove leading 0 if present
        $phone = ltrim($phone, '0');
        $phone = '61' . $phone;
    }
    
    // Validate phone number
    if (empty($phone) || strlen($phone) < 10) {
        $logger->error("Invalid phone number: {$phone}", $context);
        return false;
    }
    
    // Prepare POST data
    $postData = [
        'username' => $username,
        'password' => $password,
        'to'       => $phone,
        'message'  => $message,
        'from'     => 'giftcards+',
        'maxsplit' => 3,
    ];
    
    // Send SMS via cURL
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error) {
        $logger->error("SMS cURL Error: {$error}", $context);
        return false;
    }
    
    if ($http_code !== 200) {
        $logger->error("SMS HTTP Error: {$http_code} - Response: {$response}", $context);
        return false;
    }
    
    // Parse response (format: OK:61402472256:1236870983 or ERROR:message)
    if (strpos($response, 'OK:') === 0) {
        $logger->info("SMS sent successfully to {$phone}. Response: {$response}", $context);
        return [
            'success' => true,
            'response' => $response,
            'phone' => $phone
        ];
    } else {
        $logger->error("SMS send failed. Response: {$response}", $context);
        return false;
    }
}


/**
 * Generate a short random code for URL shortening
 * 
 * @param int $length Length of the code (default 6)
 * @return string
 */
function gc_generate_short_code($length = 6) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $code = '';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, $charactersLength - 1)];
    }
    
    return $code;
}

/**
 * Create custom short URL table if it doesn't exist
 */
function gc_create_short_url_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gc_short_urls';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        short_code varchar(10) NOT NULL,
        long_url text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime NULL,
        click_count int(11) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY short_code (short_code),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Shorten a URL for SMS messages using custom shortener.
 * Creates short URLs like: yoursite.com/s/abc123
 * 
 * @param string $url The long URL to shorten
 * @return string Shortened URL or original URL if shortening fails
 */
function gc_shorten_url_for_sms($url) {
    $url = trim((string) $url);
    if (empty($url)) {
        return '';
    }

    // Decode HTML entities (e.g., &#038; -> &) to ensure clean URLs for SMS
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Basic validation: only shorten http(s) links
    if (!preg_match('#^https?://#i', $url)) {
        return $url;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'gc_short_urls';
    
    // Create table if it doesn't exist
    gc_create_short_url_table();
    
    // Check if URL already exists in database
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT short_code FROM $table_name WHERE long_url = %s LIMIT 1",
        $url
    ));
    
    if ($existing) {
        // Return existing short URL
        $short_url = home_url('/s/' . $existing->short_code);
        return $short_url;
    }
    
    // Generate unique short code
    $max_attempts = 10;
    $short_code = '';
    
    for ($i = 0; $i < $max_attempts; $i++) {
        $code = gc_generate_short_code(6);
        
        // Check if code already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE short_code = %s",
            $code
        ));
        
        if (!$exists) {
            $short_code = $code;
            break;
        }
    }
    
    if (empty($short_code)) {
        // Fallback: use longer code if collisions occur
        $short_code = gc_generate_short_code(8);
    }
    
    // Insert into database
    $inserted = $wpdb->insert(
        $table_name,
        [
            'short_code' => $short_code,
            'long_url' => $url,
            'created_at' => current_time('mysql'),
            'expires_at' => null, // URLs don't expire by default
            'click_count' => 0
        ],
        ['%s', '%s', '%s', '%s', '%d']
    );
    
    if ($inserted) {
        $short_url = home_url('/s/' . $short_code);
        return $short_url;
    }
    
    // If insertion failed, return original URL
    return $url;
}

/**
 * Add rewrite rule for short URLs
 */
function gc_add_short_url_rewrite_rule() {
    add_rewrite_rule('^s/([a-zA-Z0-9]+)/?$', 'index.php?gc_short_code=$matches[1]', 'top');
    add_rewrite_tag('%gc_short_code%', '([a-zA-Z0-9]+)');
}
add_action('init', 'gc_add_short_url_rewrite_rule');

/**
 * Handle short URL redirects via rewrite rule
 */
function gc_handle_short_url_redirect() {
    $short_code = get_query_var('gc_short_code');
    
    if (empty($short_code)) {
        // Fallback: try to extract from REQUEST_URI
        if (isset($_SERVER['REQUEST_URI']) && preg_match('#/s/([a-zA-Z0-9]+)#', $_SERVER['REQUEST_URI'], $matches)) {
            $short_code = sanitize_text_field($matches[1]);
        } else {
            return;
        }
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'gc_short_urls';
    
    // Get long URL from database
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT long_url, click_count FROM $table_name WHERE short_code = %s LIMIT 1",
        $short_code
    ));
    
    if ($result) {
        // Update click count
        $wpdb->update(
            $table_name,
            ['click_count' => $result->click_count + 1],
            ['short_code' => $short_code],
            ['%d'],
            ['%s']
        );
        
        // Redirect to long URL
        wp_redirect($result->long_url, 301);
        exit;
    }
    
    // If short code not found, return 404
    status_header(404);
    nocache_headers();
    include(get_query_template('404'));
    exit;
}

// Hook into template redirect to handle short URLs
add_action('template_redirect', 'gc_handle_short_url_redirect', 1);