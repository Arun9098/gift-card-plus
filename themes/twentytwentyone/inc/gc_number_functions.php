<?php
	function encrypt_giftcard($plaintext, $password) {
	    $method = 'AES-256-CBC';
	    $salt = random_bytes(16); // 16 bytes salt
	    $iv = random_bytes(16);   // AES block size = 16 bytes
	    // Derive a 32-byte key using PBKDF2 (SHA-256)
	    $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);
	    // Encrypt using OpenSSL
	    $ciphertext_raw = openssl_encrypt($plaintext, $method, $key, OPENSSL_RAW_DATA, $iv);
	    if ($ciphertext_raw === false) {
	        throw new Exception('Gift card encryption failed.');
	    }
	    // Combine salt + IV + ciphertext → Base64 encode
	    $payload = base64_encode($salt . $iv . $ciphertext_raw);
	    return $payload;
	}
	
	function decrypt_giftcard($payload_base64, $password) {
	    $method = 'AES-256-CBC';
	    $payload = base64_decode($payload_base64, true);
	    if ($payload === false || strlen($payload) < 32) {
	        throw new Exception('Invalid gift card payload.');
	    }

	    $salt = substr($payload, 0, 16);
	    $iv = substr($payload, 16, 16);
	    $ciphertext_raw = substr($payload, 32);

	    $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);
	    $plaintext = openssl_decrypt($ciphertext_raw, $method, $key, OPENSSL_RAW_DATA, $iv);
	    if ($plaintext === false) {
	        throw new Exception('Gift card decryption failed (wrong key or corrupt data).');
	    }
	    return $plaintext;
	}