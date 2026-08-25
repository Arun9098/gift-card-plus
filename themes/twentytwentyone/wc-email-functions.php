<?php
// Hook into save_post to set or update the transient when post is created or updated
add_action('save_post', 'update_custom_post_transient', 10, 3);

// Hook into before_delete_post to delete the transient when post is deleted
add_action('before_delete_post', 'delete_custom_post_transient');

function update_custom_post_transient($post_ID, $post, $update) {
    // Only target custom post type
    if ($post->post_type !== 'email_template') {
        return;
    }
    set_email_templates_wc();
}

function delete_custom_post_transient($post_ID) {
    $post_type = get_post_type($post_ID);
    
    if ($post_type === 'email_template') {
        // Delete transient on deletion
        set_email_templates_wc();
    }
}

function set_email_templates_wc(){
    delete_transient('email_templates_wc');

    $email_template_posts = get_posts([
        'post_type' => 'email_template',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    $arr = array();
    foreach ($email_template_posts as $key => $value) {
        $email_trigger = get_post_meta($value, '_et_trigger', true);
        if( isset($email_trigger) && !empty($email_trigger) ){
            $arr[$email_trigger]['email_title'] = get_the_title($value);
            $arr[$email_trigger]['email_content'] = apply_filters('the_content', get_post_field('post_content', $value));
            $arr[$email_trigger]['email_subject'] = get_post_meta($value, '_et_subject', true);
            $arr[$email_trigger]['email_sender_name'] = get_post_meta($value, '_et_sender_name', true);
            $arr[$email_trigger]['email_sender_email'] = get_post_meta($value, '_et_sender_email', true);
            $arr[$email_trigger]['email_trigger'] = $email_trigger;
        }

    }
    // echo '<pre>';
    // print_r($arr);
    // echo '</pre>';
    //exit;
    //$email_templates = get_transient( 'email_templates_wc' )

    // Set transient for 12 hours (change if needed)
    set_transient('email_templates_wc', $arr, 0); 
}

// customer_processing_order
add_filter('woocommerce_email_subject_customer_processing_order', function($subject, $order) {
    $email_templates = get_transient( 'email_templates_wc' );
    if( isset($email_templates['customer_processing_order']) 
    && is_array($email_templates['customer_processing_order']) 
    && isset($email_templates['customer_processing_order']['email_subject']) 
    && !empty($email_templates['customer_processing_order']['email_subject']) ){
        return $email_templates['customer_processing_order']['email_subject'];    
    }
    return $subject;
}, 10, 2);

// customer_completed_order
add_filter('woocommerce_email_subject_customer_completed_order', function($subject, $order) {
    $email_templates = get_transient( 'email_templates_wc' );
    if( isset($email_templates['customer_completed_order']) 
    && is_array($email_templates['customer_completed_order']) 
    && isset($email_templates['customer_completed_order']['email_subject']) 
    && !empty($email_templates['customer_completed_order']['email_subject']) ){
        return $email_templates['customer_completed_order']['email_subject'];    
    }
    return $subject;
}, 10, 2);

// customer_new_account
add_filter('woocommerce_email_subject_customer_new_account', function($subject, $email) {
    $email_templates = get_transient( 'email_templates_wc' );
    if( isset($email_templates['customer_new_account']) 
    && is_array($email_templates['customer_new_account']) 
    && isset($email_templates['customer_new_account']['email_subject']) 
    && !empty($email_templates['customer_new_account']['email_subject']) ){
        return $email_templates['customer_new_account']['email_subject'];    
    }
    return $subject;
}, 10, 2);

// Override WooCommerce email sender name
add_filter('woocommerce_email_from_name', function($from_name, $email) {
    $email_templates = get_transient('email_templates_wc');
    $current_id = $email->id;

    if (
        isset($email_templates[$current_id]) &&
        !empty($email_templates[$current_id]['email_sender_name'])
    ) {
        return $email_templates[$current_id]['email_sender_name'];
    }

    return $from_name;
}, 10, 2);

// Override WooCommerce email sender address
add_filter('woocommerce_email_from_address', function($from_address, $email) {
    $email_templates = get_transient('email_templates_wc');
    $current_id = $email->id;

    if (
        isset($email_templates[$current_id]) &&
        !empty($email_templates[$current_id]['email_sender_email'])
    ) {
        return $email_templates[$current_id]['email_sender_email'];
    }

    return $from_address;
}, 10, 2);


