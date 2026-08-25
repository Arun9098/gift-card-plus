
<?php
//Saving code
if (isset($_POST['save_changes'])) {

    // Security: only logged-in user allowed
    if (!is_user_logged_in()) {
        wp_die('Not allowed.');
    }

    // pr($_POST);
    $user_id = get_current_user_id();

    // Sanitize inputs
    $first_name   = sanitize_text_field($_POST['first_name']);
    $last_name    = sanitize_text_field($_POST['last_name']);
    $phone_number = sanitize_text_field($_POST['phone_number']);
    $dob          = sanitize_text_field($_POST['dob']);
    $state        = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
    $new_password     = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : '';
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    
    wp_update_user([
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name'  => $last_name,
    ]);


    // Update other meta fields
    update_user_meta($user_id, 'mobile', $phone_number);
    update_user_meta($user_id, 'dob', $dob);
    
    // Handle state saving - skip special values (__OTHER__ or __BACK_AU__)
    if ($state !== '__OTHER__' && $state !== '__BACK_AU__') {
        // Determine country based on state value (only if state is not empty)
        if (!empty($state)) {
            if (strpos($state, 'NZ-') === 0) {
                $country = 'NZ';
                // Keep the full "NZ-{key}" value for proper matching on reload
                // Don't strip the prefix - we'll use it to identify NZ states
            } else {
                $country = 'AU';
            }
            
            // Update country based on state selection
            update_user_meta($user_id, 'billing_country', $country);
            update_user_meta($user_id, 'shipping_country', $country);
            update_user_meta($user_id, 'country', $country);
        }
        
        // Prepare state value for WooCommerce (strip NZ- prefix if present)
        // WooCommerce expects just the state code, not "NZ-{code}"
        $wc_state = $state;
        if (strpos($state, 'NZ-') === 0) {
            $wc_state = str_replace('NZ-', '', $state);
        }
        
        // Save the full state value (with NZ- prefix if applicable) to our custom 'state' field
        update_user_meta($user_id, 'state', $state);
        
        // Save state to WooCommerce fields (without NZ- prefix for WooCommerce compatibility)
        update_user_meta($user_id, 'billing_state', $wc_state);
        update_user_meta($user_id, 'shipping_state', $wc_state);
        
        // Also use WooCommerce customer object to ensure proper saving
        if (class_exists('WC_Customer')) {
            $customer = new WC_Customer($user_id);
            $customer->set_billing_state($wc_state);
            $customer->set_shipping_state($wc_state);
            $customer->save();
        }
    }

    // Update password ONLY if changed — requires current password to be correct
    $password_error   = '';
    $password_success = '';
    if (!empty($new_password)) {
        $user_obj = get_userdata($user_id);
        if (empty($current_password)) {
            $password_error = 'Please enter your current password to set a new one.';
        } elseif (!wp_check_password($current_password, $user_obj->user_pass, $user_id)) {
            $password_error = 'Current password is incorrect.';
        } elseif (!validate_password_strength($new_password)) {
            $password_error = 'New password must be at least 12 characters and include an uppercase letter, lowercase letter, a number, and a special character.';
        } else {
            wp_set_password($new_password, $user_id);
            // wp_set_password() clears auth cookies — re-authenticate so user stays logged in
            wp_set_auth_cookie($user_id, true);
            $password_success = 'Password updated successfully.';
        }
    }

    // Recalculate your profile % (calls your existing function)
    if (function_exists('recalc_profile_completion_on_user_save')) {
        recalc_profile_completion_on_user_save($user_id);
    }

}


if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_id = get_current_user_id();


$basic_details_done = ( !empty($current_user->user_firstname) && !empty($current_user->user_lastname) );
$email_verified = !empty($current_user->user_email);
$phone_added = !empty(get_user_meta($user_id, 'mobile', true));
$dob_added = !empty(get_user_meta($user_id, 'dob', true));
$interested_events_added = !empty(get_user_meta($user_id, 'interested_events', true));
$hobbies_added = !empty(get_user_meta($user_id, 'hobbies', true));


$checklist_status = [
    'Basic details completed'   => $basic_details_done,
    'Email verified'            => $email_verified,
    'Add phone number'          => $phone_added,
    'Add date of birth'         => $dob_added,
    'Add Interested Events'  => $interested_events_added,
    'Add Hobbies'            => $hobbies_added,
];

$total_items = count($checklist_status);
$items_completed = count(array_filter($checklist_status));
$percent = floor(($items_completed / $total_items) * 100);
?>

<?php if ( (int) $percent < 100 ) : ?>
<div class="gc-dashboard-wrapper">

    <h2 class="gc-title">Profile Completion</h2>
    <p class="gc-subtitle">Complete your profile to unlock all features and improve your experience</p>

    <!-- Progress Bar -->
    <div class="gc-progress-wrapper">
        <div class="gc-progress-detail">
            <p class="gc-progress-title">Progress</p>
            <span class="gc-progress-text"><?php echo esc_html($percent); ?>% complete</span>
        </div>
        <div class="gc-progress-bar">
            <div class="gc-progress-fill" style="width: <?php echo esc_attr($percent); ?>%;"></div>
        </div>
    </div>

    <!-- Checklist Section -->
    <div class="gc-checklist">

        <?php foreach ($checklist_status as $label => $status): ?>

            <?php if ($status): ?>
                <div class="gc-check-item done">
                    <span class="check-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 0C5.376 0 0 5.376 0 12C0 18.624 5.376 24 12 24C18.624 24 24 18.624 24 12C24 5.376 18.624 0 12 0ZM8.748 17.148L4.44 12.84C3.972 12.372 3.972 11.616 4.44 11.148C4.908 10.68 5.664 10.68 6.132 11.148L9.6 14.604L17.856 6.348C18.324 5.88 19.08 5.88 19.548 6.348C20.016 6.816 20.016 7.572 19.548 8.04L10.44 17.148C9.984 17.616 9.216 17.616 8.748 17.148Z" fill="#027A48"/>
                        </svg>
                    </span>
                    <span class="completed-text"><?php echo esc_html($label); ?></span>
                </div>
            <?php else: ?>
                <div class="gc-check-item pending">
                    <span class="pending-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 0C5.376 0 0 5.376 0 12C0 18.624 5.376 24 12 24C18.624 24 24 18.624 24 12C24 5.376 18.624 0 12 0ZM12 21.6C6.708 21.6 2.4 17.292 2.4 12C2.4 6.708 6.708 2.4 12 2.4C17.292 2.4 21.6 6.708 21.6 12C21.6 17.292 17.292 21.6 12 21.6Z" fill="#636466"/>
                        </svg>
                    </span>
                    <span class="pending-text"><?php echo esc_html($label); ?></span>
                </div>
            <?php endif; ?>

        <?php endforeach; ?>

    </div>

    <button type="button" class="gc-complete-btn btn btn-primary btn-black-p2">Complete profile</button>
</div>
<?php endif; ?>
<div class="gc-profile-update-wrapper">
    <h2 class="gc-title-update">My Details</h2>
    <p class="gc-title">Update my details and personal information</p>


    <form method="post" class="gc-profile-form">

        <div class="gc-form-row">
            <div class="gc-form-group gc-form-firstname" id="section-basic-details">
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" id="user-first_name" placeholder="Enter First Name" required
                    value="<?php echo esc_attr($current_user->user_firstname); ?>" class="gc-input">
            </div>
            <div class="gc-form-group gc-form-surname">
                <label for="last_name">Surname</label>
                <input type="text" name="last_name" id="user-last_name" placeholder="Enter Surname" required
                    value="<?php echo esc_attr($current_user->user_lastname); ?>" class="gc-input">
            </div>
        </div>

        <div class="gc-form-row">
            <div class="gc-form-group gc-form-email" id="section-email">
                <label for="email">Email</label>
                <input type="email" name="email" id="user-email" placeholder="Enter Email" value="<?php echo esc_attr($current_user->user_email); ?>"
                    readonly class="gc-input gc-input-disabled">
            </div>
            <div class="gc-form-group gc-form-mobile" id="section-mobile">
                <label for="phone_number">Mobile</label>
                <input type="tel" name="phone_number" id="user-phone_number" placeholder="Enter Phone" value="<?php echo esc_attr($current_user->mobile); ?>" class="gc-input">
            </div>
        </div>

        <div class="gc-form-row">
            <div class="gc-form-group gc-form-date">
                <label for="dob">Date of birth</label>
               <?php
                    $raw_dob = get_user_meta($user_id, 'dob', true);
                    // $formatted_dob = '';
                    
                    // if (!empty($raw_dob)) {
                    //     $timestamp = strtotime($raw_dob);
                    //     if ($timestamp) {
                    //         $formatted_dob = date("d/m/Y", $timestamp);
                    //     }
                    // }
                ?>
                <input type="date" name="dob" id="user-dob" placeholder="DD/MM/YYYY" value="<?php echo esc_attr($raw_dob); ?>" class="gc-input">
            </div>
            <?php
            $state = get_user_meta($user_id, 'billing_state', true);
            $country = get_user_meta($user_id, 'billing_country', true);

            $au_states = WC()->countries->get_states('AU');
            $nz_states = WC()->countries->get_states('NZ');

            // 🔥 Sort alphabetically by state name (label)
            if (!empty($au_states)) {
                asort($au_states);
            }

            if (!empty($nz_states)) {
                asort($nz_states);
            }
            
            // Check if saved state is a NZ state
            // The state might be saved with "NZ-" prefix OR just the key if country is NZ
            $is_nz_state = (strpos($state, 'NZ-') === 0) || ($country === 'NZ');
            
            // If country is NZ but state doesn't have "NZ-" prefix, add it for matching
            if ($country === 'NZ' && strpos($state, 'NZ-') !== 0 && !empty($state)) {
                $state = 'NZ-' . $state;
            }
            ?>

            <div class="gc-form-group gc-form-select" id="section-address">
                <label for="billing_state">State</label>
                <p class="form-row gc-form-group gc-form-select validate-required validate-state" id="billing_state_field" data-priority="80">
                    <span class="woocommerce-input-wrapper">
                        <select name="billing_state" id="billing_state" class="gc-input">
                            <option value="">Select State</option>

                            <?php if (!$is_nz_state): ?>
                                <!-- Show AU states by default -->
                                <?php foreach ($au_states as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($state, $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                                <!-- Trigger -->
                                <option value="__OTHER__" <?php selected(false, true); ?>>Other</option>
                            <?php else: ?>
                                <!-- Show NZ states if saved state is NZ -->
                                <?php foreach ($nz_states as $key => $label): ?>
                                    <option value="NZ-<?php echo esc_attr($key); ?>" <?php selected($state, 'NZ-' . $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__BACK_AU__"><strong>Select State of Australia</strong></option>
                            <?php endif; ?>
                        </select>
                    </span>
                </p>
                <span class="gc-select-arrow"></span>
            </div>

            <script>
            jQuery(function ($) {

                const auStates = <?php echo wp_json_encode($au_states); ?>;
                const nzStates = <?php echo wp_json_encode($nz_states); ?>;
                const savedState = <?php echo wp_json_encode($state); ?>;
                const isNZState = <?php echo $is_nz_state ? 'true' : 'false'; ?>;

                function loadAUStates() {
                    const $s = $('#billing_state');
                    $s.empty().append('<option value="">Select State</option>');

                    $.each(auStates, function (key, label) {
                        const isSelected = (savedState === key && !isNZState);
                        $s.append(`<option value="${key}" ${isSelected ? 'selected' : ''}>${label}</option>`);
                    });

                    $s.append('<option value="__OTHER__">Other</option>');
                }

                function loadNZStates() {
                    const $s = $('#billing_state');
                    $s.empty().append('<option value="">Select State</option>');

                    $.each(nzStates, function (key, label) {
                        const nzKey = 'NZ-' + key;
                        const isSelected = (savedState === nzKey);
                        $s.append(`<option value="${nzKey}" ${isSelected ? 'selected' : ''}>${label}</option>`);
                    });

                    $s.append('<option value="__BACK_AU__"><strong>Select State of Australia</strong></option>');
                }

                // On page load, if saved state is NZ, load NZ states
                if (isNZState) {
                    loadNZStates();
                } else if (savedState) {
                    // If we have a saved AU state, make sure it's selected
                    $('#billing_state').val(savedState);
                }

                $('#billing_state').on('change', function () {
                    const val = $(this).val();

                    if (val === '__OTHER__') {
                        loadNZStates();
                    }

                    if (val === '__BACK_AU__') {
                        loadAUStates();
                    }
                });

            });
            </script>
        </div>
        <?php if (!empty($password_error)): ?>
            <div class="gc-form-error" style="color:red; margin-bottom:8px;"><?php echo esc_html($password_error); ?></div>
        <?php endif; ?>
        <?php if (!empty($password_success)): ?>
            <div class="gc-form-success" style="color:green; margin-bottom:8px;"><?php echo esc_html($password_success); ?></div>
        <?php endif; ?>
        <div class="gc-form-row">
            <div class="gc-form-group gc-form-password" id="section-current-password">
                <label for="current_password">Current Password</label>
                <input type="password" name="current_password" id="current_password" placeholder="Enter current password" value="" class="gc-input current-password" autocomplete="current-password">
            </div>
        </div>
        <div class="gc-form-row">
            <div class="gc-form-group gc-form-password" id="section-password">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" placeholder="Enter new password" value="" class="gc-input" autocomplete="new-password">
            </div>
            <a href="<?php echo wc_lostpassword_url(); ?>" class="gc-reset-password-link">Reset password </a>
        </div>

        <button type="submit" name="save_changes" class="btn-black-p2 gc-save-btn btn btn-primary">Save changes</button>

        <p class="gc-email-note">
            To update your email address please <a href="#" class="gc-contact-link">contact us.</a>
        </p>

    </form>
</div>