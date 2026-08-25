<?php 
if (!defined('ABSPATH')) {
    exit;
}

// Get saved values
$user_id = get_current_user_id();

$saved_events  = (array) get_user_meta($user_id, 'interested_events', true);
$saved_hobbies = (array) get_user_meta($user_id, 'hobbies', true);

$marketing_email = get_user_meta($user_id, 'marketing_emails', true);
$marketing_sms   = get_user_meta($user_id, 'sms_notifications', true);
?>
<div id="preferences-step" class="registration-step active">
    <div class="preference-wrap">
        <div class="step-heading">
            <h2 class="main-heading sm-h2">Event Preferences</h2>
            <p class="sub-heading">Choose which events you’d love to receive notifications and updates about</p>
        </div>

        <form class="preferences-form">

            <!-- EVENTS -->
            <div class="form-row preferences-multi-select">
                <h4 class="preferences-subheading">Events</h4>
                <div class="multi-select-container">
                  <?php
                    if (have_rows('events', 'option')):
                        while (have_rows('events', 'option')): the_row();
                            $event_label = get_sub_field('event_name');

                            if ($event_label) {
                                $event_key = sanitize_title($event_label);

                                echo '<div class="select-option">';
                                echo '<input type="checkbox" 
                                            id="event_' . $event_key . '" 
                                            name="events[]" 
                                            value="' . $event_key . '" 
                                            ' . checked(in_array($event_key, $saved_events), true, false) . '>';
                                echo '<label for="event_' . $event_key . '">' . esc_html($event_label) . '</label>';
                                echo '</div>';
                            }

                        endwhile;
                    endif;
                    ?>
                </div>
            </div>

            <!-- HOBBIES -->
            <div class="form-row preferences-multi-select">
                <h4 class="preferences-subheading">Hobbies</h4>
                <div class="multi-select-container">
                    <?php
                    if (have_rows('hobbies', 'option')):

                        while (have_rows('hobbies', 'option')): the_row();
                            $hobby_label = get_sub_field('hobbie_name');

                            if ($hobby_label) {
                                $hobby_key = sanitize_title($hobby_label);

                                echo '<div class="select-option">';
                                echo '<input type="checkbox" 
                                            id="hobby_' . $hobby_key . '" 
                                            name="hobbies[]" 
                                            value="' . $hobby_key . '" 
                                            ' . checked(in_array($hobby_key, $saved_hobbies), true, false) . '>';
                                echo '<label for="hobby_' . $hobby_key . '">' . esc_html($hobby_label) . '</label>';
                                echo '</div>';
                            }

                        endwhile;

                    endif;
                    ?>
                </div>
            </div>

            <div class="sm-main-form">
                <div class="selected-preferences"></div>
            </div>

        </form>
    </div>
    
    <!-- MARKETING PREFERENCES -->
    <div class="marketing-wrap">
        <h2 class="marketing-title">Marketing Preferences</h2>
        <p>How would you like to hear about our offers and promotions?</p>

        <div class="checkbox-wrap">
            <label>
                <input type="checkbox" class="marketing-email"
                       <?php checked($marketing_email, 1); ?>> Marketing emails
            </label>

            <label>
                <input type="checkbox" class="sms-notifications"
                       <?php checked($marketing_sms, 1); ?>> SMS notifications
            </label>
        </div>

        <button class="save-preferences btn btn-primary btn-ln btn-black-p2" id="save-preferences">Save Preferences</button>
    </div>
    <div class="preference-message"></div>

</div>
