
<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
$gif_url = site_url();

function display_gift_card_customisation($order_status = 'create', $order_id = 0)
{
    // $order_status = 'create';
    // if ($order_id > 0) {
    //     $order_id = absint($order_id);
    //     $order_status = 'update';
    // }
    ?>
    <?php
    $order_id = null;
    $order = null;
    $recipients_details_arr = [];
    $order_status = 'create';

    if (isset($_GET['order_id'])) {
        $order_id = absint($_GET['order_id']);

        // Try to get the HPOS order
        $order = wc_get_order($order_id);


        // Fallback: If HPOS active and ID is legacy, map post ID to HPOS ID
        if (!$order) {
            global $wpdb;
            $hpos_id = $wpdb->get_var($wpdb->prepare(
                "SELECT order_id FROM {$wpdb->prefix}wc_orders WHERE post_id = %d",
                $order_id
            ));
            if ($hpos_id) {
                $order = wc_get_order($hpos_id);
            }
        }

        if ($order) {
            $recipients_details_arr = $order->get_meta('_recipients_details_arr', true);
            $order_status = 'update';
            $business_user_id = $order->get_customer_id();
        }
        $sender_name = $order->get_meta('_sender_name');
        $sender_email = $order->get_meta('_sender_email');
        $personalise_all_checkbox = '';
        foreach ( $order->get_items() as $item_id => $item ) {
            $personalise_all_checkbox = $item->get_meta('_personalise_all_checkbox');
            // If you only need the first one, break after this line
            break;
        }
        
        // echo '<pre>';
        // echo '---------------';
        // echo $personalise_all_checkbox;
        // echo '</pre>';
        // exit;
    }
    ?>

    

    <div class="custom-slider-wrapper">
        <div class="gift-card-carousel-container">
            <div class="gift-card-carousel">
                <div class="gift-card-slider owl-carousel">
                    <?php if (!empty($recipients_details_arr)) : ?>
                        <?php 
                        $slide_id = 1;
                       
                        foreach ($recipients_details_arr as $recipient) :
                            $first_name = esc_attr($recipient['first_name'] ?? '');
                            $surname = esc_attr($recipient['surname'] ?? '');
                            $email = esc_attr($recipient['email'] ?? '');
                            $phone = esc_attr($recipient['phone'] ?? '');

                            

                            if (!empty($recipient['gift_cards']) && is_array($recipient['gift_cards'])) :
                                

                                foreach ($recipient['gift_cards'] as $gift_card) :                             

                                    $sku = esc_attr($gift_card['sku'] ?? '');
                                    $price = isset($gift_card['price']) ? wc_price($gift_card['price']) : '';
                                    $title = esc_attr($gift_card['title'] ?? '');
                                    $image = esc_url($gift_card['product_image'] ?? '');
                                    $delivery_method = esc_attr($gift_card['delivery_method'] ?? '');
                                    $gift_message = $gift_card['gift_message'];
                                    $gift_subject = $gift_card['gift_subject'] ?? '';
                                    $gift_text_message = $gift_card['gift_text_message'] ?? '';  
                                    $gift_discounted = $gift_card['gift_discounted'] ?? '';  
                                ?>
                                <div class="gift-card-slide item"
                                    data-id="<?php echo $slide_id; ?>"
                                    data-sku="<?php echo $sku; ?>"
                                    data-first-name="<?php echo $first_name; ?>"
                                    data-surname="<?php echo $surname; ?>"
                                    data-email="<?php echo $email; ?>"
                                    data-phone="<?php echo $phone; ?>"
                                    data-message="<?php echo $gift_message; ?>"
                                    data-subject="<?php echo $gift_subject; ?>"
                                    data-text-message="<?php echo $gift_text_message; ?>"
                                    data-sender=""
                                    data-discounted="<?php echo $gift_discounted; ?>"
                                    data-name="<?php echo $title; ?>"
                                    data-delivery-method="<?php echo $delivery_method; ?>">

                                    <label class="gift-card-checkbox">
                                        <input type="checkbox" id="gift-card-<?php echo $slide_id; ?>" class="gift-card-select" style="display: none;" <?php echo (!empty($gift_card['selected']) && $gift_card['selected'] == 1) ? 'checked' : ''; ?> />
                                        <span class="custom-checkbox"></span>
                                    </label>
                                    <img src="<?php echo $image; ?>" alt="Gift Card" class="gift-card-img" style="opacity: 1;">
                                    <p class="recipient-name"><?php echo $first_name . ' ' . $surname; ?></p>
                                    <p class="gift-card-price"><?php echo $price; ?></p>
                                </div>
                                <?php 
                                $slide_id++;
                                endforeach;
                            endif;
                        endforeach;
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div id="gift-card-preview-modal" class="custom-modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="gift-card-preview-content"></div>
        </div>
    </div>

    <div class="customisation-wrapper">
        <div class="form-group flex-row customisation-checkbox">
            <div class="control-wrapper col custom-flex">
            <span class="custom-checkbox">
                <input type="checkbox" id="personalise-all" class="personalise-all-cards" <?php echo ($personalise_all_checkbox === 'yes') ? 'checked' : ''; ?> />
            </span>
                <label class="personalise-checkbox m-0 ps-2">Apply personalisation to all items on this order</label>
                
            </div> 
        </div> 
        <div class="gift-card-customisation-container">
            <!-- Gift Card Preview -->
            <div class="preview-img-gift-card">
                <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/no-image-icon.png'); ?>" alt="Gift Card" class="w-32 h-20 rounded-md">
                <p class="card-price"></p>
            </div>

            <div class="select-sender-wrapper">
                 <div class="form-group flex-row">
                    <div class="control-wrapper col col-6">
                      <label class="sender-details-label">Sender Details</label>
                        <div class="select-sender-dropdown">
                            <select id="select-sender-dropdown" name="select_sender_details" class="form-select">
                            <option value="" disabled <?php echo empty($sender_name) ? 'selected' : ''; ?>>Select sender</option>
                                <?php
                                $senders = get_field('sender_details', 'user_' . $business_user_id); // Using ACF repeater
                                
                                if (!empty($senders) && is_array($senders)) {
                                    foreach ($senders as $sender) {
                                        $name = isset($sender['sender_name']) ? $sender['sender_name'] : '';
                                        $email = isset($sender['sender_email']) ? $sender['sender_email'] : '';
                                        $selected = ($sender_name === $name) ? 'selected' : '';

                                        echo '<option value="' . esc_attr($name) . '" data-email="' . esc_attr($email) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                                    }
                                }

                                // Add fallback option if the selected sender is not in the list
                                if (!empty($sender_name) && !empty($sender_email)) {
                                    $already_in_list = false;
                                    if (!empty($senders)) {
                                        foreach ($senders as $sender) {
                                            if (isset($sender['sender_name']) && $sender['sender_name'] === $sender_name) {
                                                $already_in_list = true;
                                                break;
                                            }
                                        }
                                    }

                                    if (!$already_in_list) {
                                        echo '<option value="' . esc_attr($sender_name) . '" data-email="' . esc_attr($sender_email) . '" selected>' . esc_html($sender_name) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="customise-email-subject-wrapper">
                <div class="form-group flex-row">
                     <div class="control-wrapper col">
                        <div class="email-template-header labe-flex">
                            <label class="customise-email-subject-label">Email Subject Line (Optional)</label>
                            <button id="email-template-button" class="btn btn-black-white btn-primary-white btn-white email-template-button">Email Templates</button>
                        </div>
                        <input type="text" class="customise-email-subject-input form-control"
                            placeholder="Congrats <First Name>, You have received a <Gift Card Value> <Gift Card Title>">
                    </div>
                </div>
            </div>
            <div class="apply-message-container">
                <label class="apply-message-label">
                    <span class="custom-checkbox">
                        <input type="checkbox" id="apply-message-checkbox" class="apply-message-input"/>
                    </span>
                    Apply message to all selected
                </label>
            </div>
            <?php $info_message = "Use <> to pull in a dynamic variable such as: <Full Name>, <First Name>, <Last Name>, <Email>, <Gift Card>, <Price>, <Sender>"; ?>
            <div class="email-message-container">
                <div class="email-message-inner-container flex-row">
                   <div class="control-wrapper col">
                        <div class="labe-flex">
                            <label class="email-message-label">
                                <strong>Email Message</strong><button type="button" data-bs-custom-class="large-tooltip" class="btn btn-secondary" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="<?php echo esc_attr( $info_message ); ?>">!</button>
                            </label>
                            <button id="remove-email-message-animation-button" class="btn btn-red btn btn-new add-animation-button">- Remove Animation</button>
                            <button id="add-email-message-animation-button" class="btn btn-blue btn btn-new add-animation-button">+ Add Animation</button>
                        </div>
                    </div>
                </div>
                <div id="selected-email-animation-preview" class="selected-email-animation-preview"></div>
                <div class="email-message-wrapper">
                    <?php
                    $content = '';
                    $editor_id = 'email_message_editor';
                    $settings = array(
                        'textarea_name' => 'email_message',
                        'media_buttons' => false,
                        'teeny' => false,
                        'quicktags' => false,
                        'editor_height' => 200
                    );
                    wp_editor($content, $editor_id, $settings);
                    ?>
                </div>
            </div>
       
            <div class="text-message-container">
                <div class="text-message-inner-container">
                    <div class="label-flex">
                        <label class="text-message-label">
                            Text Message(Optional)
                        </label>
                        <button id="remove-text-message-animation-button" class="btn btn-red btn btn-new add-animation-button">- Remove Animation</button>
                        <button id="add-text-message-animation-button" class="btn btn-blue add-animation-button">+ Add Animation</button>
                    </div>
                    <div id="selected-text-animation-preview" class="selected-text-animation-preview" style="margin-top: 1rem;"></div>
                </div>
                <input type="text" id="text-message-input" class="text-message-input"   placeholder="Happy Holidays <First Name>! <Sender Name> has sent you a <Gift Card Value> <Gift Card Title>"/>
            </div>
            <!-- Shared Modal -->
            <div class="animation-modal d-none" id="animation-modal">
                <h4>Select an Animation:</h4>
                <ul id="animation-selection-modal">
               <?php
                // Get animations from ACF options page (repeater field: animation, sub-field: animation)
                if (function_exists('get_field')) {
                    $animation_repeater = get_field('animation', 'option');
                    if (!empty($animation_repeater) && is_array($animation_repeater)) {
                        foreach ($animation_repeater as $index => $row) {
                            $image = isset($row['animation']) ? $row['animation'] : null;
                            if (is_array($image) && !empty($image['url'])) {
                                $animation_id = sanitize_title($image['title'] ?? 'animation-' . ($index + 1));
                                $animation_url = $image['url'];
                                $animation_title = $image['title'] ?? 'Animation ' . ($index + 1);
                                echo '<li data-animation="' . esc_attr($animation_id) . '" data-animation-url="' . esc_url($animation_url) . '">';
                                echo '<img src="' . esc_url($animation_url) . '" alt="' . esc_attr($animation_title) . '">';
                                echo esc_html($animation_title);
                                echo '</li>';
                            } elseif (is_numeric($image)) {
                                $attachment_id = intval($image);
                                $url = wp_get_attachment_url($attachment_id);
                                if ($url) {
                                    $title = get_the_title($attachment_id);
                                    $animation_id = sanitize_title($title ?: 'animation-' . ($index + 1));
                                    echo '<li data-animation="' . esc_attr($animation_id) . '" data-animation-url="' . esc_url($url) . '">';
                                    echo '<img src="' . esc_url($url) . '" alt="' . esc_attr($title) . '">';
                                    echo esc_html($title);
                                    echo '</li>';
                                }
                            }
                        }
                    } else {
                        // Fallback: check for gallery-style field names
                        $alternative_names = array('animation_images', 'animations', 'gift_animations', 'predefined_animations');
                        foreach ($alternative_names as $field_name) {
                            $animation_images = get_field($field_name, 'option');
                            if (!empty($animation_images) && is_array($animation_images)) {
                                foreach ($animation_images as $index => $animation) {
                                    if (is_array($animation) && !empty($animation['url'])) {
                                        $animation_id = sanitize_title($animation['title'] ?? 'animation-' . ($index + 1));
                                        $animation_url = $animation['url'];
                                        $animation_title = $animation['title'] ?? 'Animation ' . ($index + 1);
                                        echo '<li data-animation="' . esc_attr($animation_id) . '" data-animation-url="' . esc_url($animation_url) . '">';
                                        echo '<img src="' . esc_url($animation_url) . '" alt="' . esc_attr($animation_title) . '">';
                                        echo esc_html($animation_title);
                                        echo '</li>';
                                    } elseif (is_numeric($animation)) {
                                        $attachment_id = intval($animation);
                                        $url = wp_get_attachment_url($attachment_id);
                                        if ($url) {
                                            $title = get_the_title($attachment_id);
                                            $animation_id = sanitize_title($title ?: 'animation-' . ($index + 1));
                                            echo '<li data-animation="' . esc_attr($animation_id) . '" data-animation-url="' . esc_url($url) . '">';
                                            echo '<img src="' . esc_url($url) . '" alt="' . esc_attr($title) . '">';
                                            echo esc_html($title);
                                            echo '</li>';
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
                ?>
                </ul>
            </div>
            <div id="animation-modal-overlay" class="animation-modal-overlay d-none"></div>

            <div class="test-buttons-container button-wrapper">
                <button id="send-test-email" class="btn-test-email btn-black-white btn-primary-white btn btn-white size-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="17" viewBox="0 0 20 17" fill="none">
                        <path d="M1 4.125L8.1443 9.12601C8.72283 9.53098 9.01209 9.73346 9.32673 9.81189C9.60466 9.88117 9.89534 9.88117 10.1733 9.81189C10.4879 9.73346 10.7772 9.53098 11.3557 9.12601L18.5 4.125M5.2 15.5H14.3C15.7701 15.5 16.5052 15.5 17.0667 15.2139C17.5607 14.9622 17.9622 14.5607 18.2139 14.0667C18.5 13.5052 18.5 12.7701 18.5 11.3V5.7C18.5 4.22986 18.5 3.49479 18.2139 2.93327C17.9622 2.43935 17.5607 2.03778 17.0667 1.78611C16.5052 1.5 15.7701 1.5 14.3 1.5H5.2C3.72986 1.5 2.99479 1.5 2.43327 1.78611C1.93935 2.03778 1.53778 2.43935 1.28611 2.93327C1 3.49479 1 4.22986 1 5.7V11.3C1 12.7701 1 13.5052 1.28611 14.0667C1.53778 14.5607 1.93935 14.9622 2.43327 15.2139C2.99479 15.5 3.72986 15.5 5.2 15.5Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1 4.125L8.1443 9.12601C8.72283 9.53098 9.01209 9.73346 9.32673 9.81189C9.60466 9.88117 9.89534 9.88117 10.1733 9.81189C10.4879 9.73346 10.7772 9.53098 11.3557 9.12601L18.5 4.125M5.2 15.5H14.3C15.7701 15.5 16.5052 15.5 17.0667 15.2139C17.5607 14.9622 17.9622 14.5607 18.2139 14.0667C18.5 13.5052 18.5 12.7701 18.5 11.3V5.7C18.5 4.22986 18.5 3.49479 18.2139 2.93327C17.9622 2.43935 17.5607 2.03778 17.0667 1.78611C16.5052 1.5 15.7701 1.5 14.3 1.5H5.2C3.72986 1.5 2.99479 1.5 2.43327 1.78611C1.93935 2.03778 1.53778 2.43935 1.28611 2.93327C1 3.49479 1 4.22986 1 5.7V11.3C1 12.7701 1 13.5052 1.28611 14.0667C1.53778 14.5607 1.93935 14.9622 2.43327 15.2139C2.99479 15.5 3.72986 15.5 5.2 15.5Z" stroke="black" stroke-opacity="0.2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1 4.125L8.1443 9.12601C8.72283 9.53098 9.01209 9.73346 9.32673 9.81189C9.60466 9.88117 9.89534 9.88117 10.1733 9.81189C10.4879 9.73346 10.7772 9.53098 11.3557 9.12601L18.5 4.125M5.2 15.5H14.3C15.7701 15.5 16.5052 15.5 17.0667 15.2139C17.5607 14.9622 17.9622 14.5607 18.2139 14.0667C18.5 13.5052 18.5 12.7701 18.5 11.3V5.7C18.5 4.22986 18.5 3.49479 18.2139 2.93327C17.9622 2.43935 17.5607 2.03778 17.0667 1.78611C16.5052 1.5 15.7701 1.5 14.3 1.5H5.2C3.72986 1.5 2.99479 1.5 2.43327 1.78611C1.93935 2.03778 1.53778 2.43935 1.28611 2.93327C1 3.49479 1 4.22986 1 5.7V11.3C1 12.7701 1 13.5052 1.28611 14.0667C1.53778 14.5607 1.93935 14.9622 2.43327 15.2139C2.99479 15.5 3.72986 15.5 5.2 15.5Z" stroke="black" stroke-opacity="0.2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1 4.125L8.1443 9.12601C8.72283 9.53098 9.01209 9.73346 9.32673 9.81189C9.60466 9.88117 9.89534 9.88117 10.1733 9.81189C10.4879 9.73346 10.7772 9.53098 11.3557 9.12601L18.5 4.125M5.2 15.5H14.3C15.7701 15.5 16.5052 15.5 17.0667 15.2139C17.5607 14.9622 17.9622 14.5607 18.2139 14.0667C18.5 13.5052 18.5 12.7701 18.5 11.3V5.7C18.5 4.22986 18.5 3.49479 18.2139 2.93327C17.9622 2.43935 17.5607 2.03778 17.0667 1.78611C16.5052 1.5 15.7701 1.5 14.3 1.5H5.2C3.72986 1.5 2.99479 1.5 2.43327 1.78611C1.93935 2.03778 1.53778 2.43935 1.28611 2.93327C1 3.49479 1 4.22986 1 5.7V11.3C1 12.7701 1 13.5052 1.28611 14.0667C1.53778 14.5607 1.93935 14.9622 2.43327 15.2139C2.99479 15.5 3.72986 15.5 5.2 15.5Z" stroke="black" stroke-opacity="0.2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1 4.125L8.1443 9.12601C8.72283 9.53098 9.01209 9.73346 9.32673 9.81189C9.60466 9.88117 9.89534 9.88117 10.1733 9.81189C10.4879 9.73346 10.7772 9.53098 11.3557 9.12601L18.5 4.125M5.2 15.5H14.3C15.7701 15.5 16.5052 15.5 17.0667 15.2139C17.5607 14.9622 17.9622 14.5607 18.2139 14.0667C18.5 13.5052 18.5 12.7701 18.5 11.3V5.7C18.5 4.22986 18.5 3.49479 18.2139 2.93327C17.9622 2.43935 17.5607 2.03778 17.0667 1.78611C16.5052 1.5 15.7701 1.5 14.3 1.5H5.2C3.72986 1.5 2.99479 1.5 2.43327 1.78611C1.93935 2.03778 1.53778 2.43935 1.28611 2.93327C1 3.49479 1 4.22986 1 5.7V11.3C1 12.7701 1 13.5052 1.28611 14.0667C1.53778 14.5607 1.93935 14.9622 2.43327 15.2139C2.99479 15.5 3.72986 15.5 5.2 15.5Z" stroke="black" stroke-opacity="0.2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1 4.125L8.1443 9.12601C8.72283 9.53098 9.01209 9.73346 9.32673 9.81189C9.60466 9.88117 9.89534 9.88117 10.1733 9.81189C10.4879 9.73346 10.7772 9.53098 11.3557 9.12601L18.5 4.125M5.2 15.5H14.3C15.7701 15.5 16.5052 15.5 17.0667 15.2139C17.5607 14.9622 17.9622 14.5607 18.2139 14.0667C18.5 13.5052 18.5 12.7701 18.5 11.3V5.7C18.5 4.22986 18.5 3.49479 18.2139 2.93327C17.9622 2.43935 17.5607 2.03778 17.0667 1.78611C16.5052 1.5 15.7701 1.5 14.3 1.5H5.2C3.72986 1.5 2.99479 1.5 2.43327 1.78611C1.93935 2.03778 1.53778 2.43935 1.28611 2.93327C1 3.49479 1 4.22986 1 5.7V11.3C1 12.7701 1 13.5052 1.28611 14.0667C1.53778 14.5607 1.93935 14.9622 2.43327 15.2139C2.99479 15.5 3.72986 15.5 5.2 15.5Z" stroke="black" stroke-opacity="0.2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1 4.125L8.1443 9.12601C8.72283 9.53098 9.01209 9.73346 9.32673 9.81189C9.60466 9.88117 9.89534 9.88117 10.1733 9.81189C10.4879 9.73346 10.7772 9.53098 11.3557 9.12601L18.5 4.125M5.2 15.5H14.3C15.7701 15.5 16.5052 15.5 17.0667 15.2139C17.5607 14.9622 17.9622 14.5607 18.2139 14.0667C18.5 13.5052 18.5 12.7701 18.5 11.3V5.7C18.5 4.22986 18.5 3.49479 18.2139 2.93327C17.9622 2.43935 17.5607 2.03778 17.0667 1.78611C16.5052 1.5 15.7701 1.5 14.3 1.5H5.2C3.72986 1.5 2.99479 1.5 2.43327 1.78611C1.93935 2.03778 1.53778 2.43935 1.28611 2.93327C1 3.49479 1 4.22986 1 5.7V11.3C1 12.7701 1 13.5052 1.28611 14.0667C1.53778 14.5607 1.93935 14.9622 2.43327 15.2139C2.99479 15.5 3.72986 15.5 5.2 15.5Z" stroke="black" stroke-opacity="0.2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Send Test Email
                </button>
                <button id="send-test-text" class="btn-test-text btn btn-black-white btn-primary-white btn-white size-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 19 19" fill="none">
                    <path d="M17.45 18.5C15.3 18.5 13.2043 18.0207 11.163 17.062C9.121 16.104 7.31267 14.8373 5.738 13.262C4.16267 11.6873 2.896 9.879 1.938 7.837C0.979334 5.79567 0.5 3.7 0.5 1.55C0.5 1.25 0.6 1 0.8 0.8C1 0.6 1.25 0.5 1.55 0.5H5.6C5.83333 0.5 6.04167 0.575 6.225 0.725C6.40833 0.875 6.51667 1.06667 6.55 1.3L7.2 4.8C7.23333 5.03333 7.22933 5.24567 7.188 5.437C7.146 5.629 7.05 5.8 6.9 5.95L4.475 8.4C5.175 9.6 6.05433 10.725 7.113 11.775C8.171 12.825 9.33333 13.7333 10.6 14.5L12.95 12.15C13.1 12 13.296 11.8873 13.538 11.812C13.7793 11.7373 14.0167 11.7167 14.25 11.75L17.7 12.45C17.9333 12.5 18.125 12.6123 18.275 12.787C18.425 12.9623 18.5 13.1667 18.5 13.4V17.45C18.5 17.75 18.4 18 18.2 18.2C18 18.4 17.75 18.5 17.45 18.5ZM3.525 6.5L5.175 4.85L4.75 2.5H2.525C2.60833 3.18333 2.725 3.85833 2.875 4.525C3.025 5.19167 3.24167 5.85 3.525 6.5ZM16.5 16.45V14.25L14.15 13.775L12.475 15.45C13.125 15.7333 13.7877 15.9583 14.463 16.125C15.1377 16.2917 15.8167 16.4 16.5 16.45Z" fill="black"/>
                    </svg>
                    Send Test Text
                </button>
            </div>
            <div id="gc-email-messages" class="gc-email-messages-container"></div>
        </div>
    </div>
    <div class="save-next-buttons-container page-bottom-toolbar">
        <div class="right-block right">
            <div class="page-bottom-actions">
                <button id="delivery-save-btn" data-action="save-draft" class="btn-save btn-black-white btn-primary-white" data-step="2" data-status="<?php echo esc_attr( $order_status ); ?>" data-order-id="<?php echo esc_attr( $order_id ); ?>">Save</button>
                <button id="delivery-next-btn" class="btn btn-black-white btn-primary-black btn-primary btn-next delivery-next-btn">Next</button>
            </div>
            <div id="save-draft-message-customisation" class="message-box" style="display: none;"></div>
        </div>
    </div>

    <?php
}

// add_action('woocommerce_after_main_content', 'display_gift_card_customisation', 15);
