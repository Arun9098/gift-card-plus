<?php
/**
 * Offer Helper Functions
 * 
 * This file contains helper functions to work with offers and products
 * Include this file in your theme's functions.php or use these functions directly
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all offers that contain a specific product
 * 
 * Usage: $offers = get_offers_for_product(123);
 * Returns: Array of offer post objects
 * 
 * @param int $product_id Product ID
 * @return array Array of offer post objects
 */
function get_offers_for_product($product_id) {
    $offer_ids = get_post_meta($product_id, '_product_offers', true) ?: [];
    if (empty($offer_ids) || !is_array($offer_ids)) {
        return [];
    }
    
    $offers = get_posts([
        'post_type' => 'offer',
        'post__in' => array_map('intval', $offer_ids),
        'posts_per_page' => -1,
        'post_status' => 'any',
    ]);
    
    return $offers;
}

/**
 * Get all products in a specific offer
 * 
 * Usage: $product_ids = get_products_in_offer(456);
 * Returns: Array of product IDs [123, 124, 125]
 * 
 * @param int $offer_id Offer ID
 * @return array Array of product IDs
 */
function get_products_in_offer($offer_id) {
    return get_post_meta($offer_id, '_offer_products', true) ?: [];
}

/**
 * Check if a product is in any offer
 * 
 * Usage: if (is_product_in_offer(123)) { ... }
 * Returns: true if product is in at least one offer, false otherwise
 * 
 * @param int $product_id Product ID
 * @return bool True if product is in at least one offer
 */
function is_product_in_offer($product_id) {
    $offers = get_post_meta($product_id, '_product_offers', true);
    return !empty($offers) && is_array($offers) && count($offers) > 0;
}

/**
 * Get offer IDs for a product
 * 
 * Usage: $offer_ids = get_product_offer_ids(123);
 * Returns: Array of offer IDs [456, 457]
 * 
 * @param int $product_id Product ID
 * @return array Array of offer IDs
 */
function get_product_offer_ids($product_id) {
    $offers = get_post_meta($product_id, '_product_offers', true) ?: [];
    return is_array($offers) ? array_map('intval', $offers) : [];
}

/**
 * Example usage in templates:
 * 
 * // Get all offers for a product
 * $offers = get_offers_for_product($product_id);
 * foreach ($offers as $offer) {
 *     echo $offer->post_title;
 * }
 * 
 * // Get all products in an offer
 * $product_ids = get_products_in_offer($offer_id);
 * foreach ($product_ids as $product_id) {
 *     $product = wc_get_product($product_id);
 *     echo $product->get_name();
 * }
 * 
 * // Check if product is in any offer
 * if (is_product_in_offer($product_id)) {
 *     echo "This product is part of an offer!";
 * }
 */

