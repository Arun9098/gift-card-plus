<?php
/**
 * Template Name: Add Reminder
 * 
 * Custom form to create tribe_events posts using shortcode
 */

// Register shortcode
add_shortcode('add_reminder_form', 'add_reminder_form_shortcode');

/**
 * Shortcode function to display the reminder form
 */
function add_reminder_form_shortcode($atts) {
    // Handle form submission
    $result = null;
    if (isset($_POST['submit_reminder']) && wp_verify_nonce($_POST['reminder_nonce'], 'add_reminder_action')) {
        $result = handle_reminder_form_submission();
    }
    
    ob_start();
    ?>
    <div class="add-reminder-page">
        <div class="container">
            <h1>Add New Reminder</h1>
            
            <?php if ($result && $result['success']): ?>
                <div class="reminder-success">
                    <p><?php echo esc_html($result['message']); ?></p>
                    <a href="<?php echo esc_url(site_url('/my-account/my-reminders')); ?>" class="btn btn-black-white btn-primary-black">View My Reminders</a>
                </div>
            <?php elseif ($result && !$result['success']): ?>
                <div class="reminder-error">
                    <p><?php echo esc_html($result['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!$result || !$result['success']): ?>
            <form method="POST" action="" class="reminder-form" id="reminder-form">
                <?php wp_nonce_field('add_reminder_action', 'reminder_nonce'); ?>
                
                <div class="form-group">
                    <label for="reminder_title">Person's Name <span class="required">*</span></label>
                    <input type="text" name="reminder_title" id="reminder_title" required
                           value="<?php echo isset($_POST['reminder_title']) ? esc_attr($_POST['reminder_title']) : ''; ?>"
                           placeholder="e.g., John" />
                </div>
                
                <div class="form-group">
                    <label for="reminder_description">Description</label>
                    <textarea name="reminder_description" id="reminder_description" rows="5" 
                              placeholder="Add any additional details about this event"><?php echo isset($_POST['reminder_description']) ? esc_textarea($_POST['reminder_description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="reminder_category">Category <span class="required">*</span></label>
                    <select name="reminder_category" id="reminder_category" required>
                        <option value="">Select Category</option>
                        <?php
                        $categories = get_terms([
                            'taxonomy' => 'tribe_events_cat',
                            'hide_empty' => false,
                        ]);
                        
                        $selected_category = isset($_POST['reminder_category']) ? $_POST['reminder_category'] : '';
                        foreach ($categories as $category) {
                            $selected = selected($selected_category, $category->slug, false);
                            echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="event_start_date">Event Start Date <span class="required">*</span></label>
                        <input type="date" name="event_start_date" id="event_start_date" required 
                               value="<?php echo isset($_POST['event_start_date']) ? esc_attr($_POST['event_start_date']) : ''; ?>" />
                    </div>
                    
                    <div class="form-group">
                        <label for="event_end_date">Event End Date</label>
                        <input type="date" name="event_end_date" id="event_end_date" 
                               value="<?php echo isset($_POST['event_end_date']) ? esc_attr($_POST['event_end_date']) : ''; ?>" />
                        <small class="help-text">Leave empty to use the same as start date</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="event_start_time">Start Time</label>
                        <input type="time" name="event_start_time" id="event_start_time" 
                               value="<?php echo isset($_POST['event_start_time']) ? esc_attr($_POST['event_start_time']) : ''; ?>" />
                        <small class="help-text">Leave empty for all-day events</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_end_time">End Time</label>
                        <input type="time" name="event_end_time" id="event_end_time" 
                               value="<?php echo isset($_POST['event_end_time']) ? esc_attr($_POST['event_end_time']) : ''; ?>" />
                        <small class="help-text">Leave empty for all-day events</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="all_day_event" id="all_day_event" value="1" 
                               <?php echo (isset($_POST['all_day_event']) && $_POST['all_day_event'] == '1') ? 'checked' : 'checked'; ?> />
                        <span>All Day Event</span>
                    </label>
                    <small class="help-text">If checked, the event will be treated as an all-day event (times will be ignored)</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="submit_reminder" class="btn-black-p2 btn btn-primary">Create Reminder</button>
                    <a href="<?php echo esc_url(site_url('/my-account/my-reminders')); ?>" class="btn-white-p2 btn btn-secondary btn-cancel">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .add-reminder-page {
        max-width: 800px;
        margin: 40px auto;
        padding: 20px;
    }
    
    .add-reminder-page h1 {
        margin-bottom: 30px;
        font-size: 28px;
    }
    
    .reminder-form {
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    
    .required {
        color: #e74c3c;
    }
    
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="time"],
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }
    
    .checkbox-label input[type="checkbox"] {
        width: auto;
        cursor: pointer;
    }
    
    .form-group textarea {
        resize: vertical;
    }
    
    .help-text {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        color: #666;
    }
    
    .btn-secondary.btn-cancel{
        border: 1px solid black;
    }
    .btn {
        display: inline-block;
        padding: 12px 24px;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .btn-primary {
        background: #000;
        color: #fff;
    }
    
    .btn-primary:hover {
        background: #333;
    }
    
    .btn-secondary {
        background: #f0f0f0;
        color: #333;
        margin-left: 10px;
    }
    
    .btn-secondary:hover {
        background: #e0e0e0;
    }
    
    .reminder-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .reminder-error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <script>
    jQuery(document).ready(function($) {
        // Toggle time fields based on all-day checkbox
        function toggleTimeFields() {
            var isAllDay = $('#all_day_event').is(':checked');
            if (isAllDay) {
                $('#event_start_time, #event_end_time').closest('.form-group').hide();
                $('#event_start_time, #event_end_time').val('');
            } else {
                $('#event_start_time, #event_end_time').closest('.form-group').show();
            }
        }
        
        // Run on page load
        toggleTimeFields();
        
        // Run when checkbox changes
        $('#all_day_event').on('change', toggleTimeFields);
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Handle reminder form submission
 */
function handle_reminder_form_submission() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return [
            'success' => false,
            'message' => 'You must be logged in to create a reminder.'
        ];
    }
    
    // Validate required fields
    $errors = [];
    
    if (empty($_POST['reminder_title'])) {
        $errors[] = 'Event title is required.';
    }
    
    if (empty($_POST['reminder_category'])) {
        $errors[] = 'Category is required.';
    }
    
    if (empty($_POST['event_start_date'])) {
        $errors[] = 'Event start date is required.';
    }
    
    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => implode(' ', $errors)
        ];
    }
    
    // Get current user
    $user_id = get_current_user_id();

    // Auto-generate title from name + category
    $person_name = sanitize_text_field( $_POST['reminder_title'] );
    $category_slug = sanitize_text_field( $_POST['reminder_category'] );
    $category_label_map = [
        'birthdays'       => 'Birthday',
        'anniversaries'   => 'Anniversary',
        'public-holidays' => 'Public Holiday',
        'religious-holiday' => 'Religious Holiday',
    ];
    if ( isset( $category_label_map[ $category_slug ] ) ) {
        $event_title = $person_name . "'s " . $category_label_map[ $category_slug ];
    } else {
        // For any unlisted category, get the term name from DB
        $term = get_term_by( 'slug', $category_slug, 'tribe_events_cat' );
        $event_title = $term ? $person_name . "'s " . $term->name : $person_name;
    }

    // Prepare post data
    $post_data = [
        'post_title'   => $event_title,
        'post_content' => isset($_POST['reminder_description']) ? wp_kses_post($_POST['reminder_description']) : '',
        'post_type'    => 'tribe_events',
        'post_status'  => 'publish',
        'post_author'  => $user_id,
    ];
    
    // Create the post
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        return [
            'success' => false,
            'message' => 'Error creating reminder: ' . $post_id->get_error_message()
        ];
    }
    
    // Save event dates - match The Events Calendar format exactly
    $start_date = sanitize_text_field($_POST['event_start_date']);
    $end_date = isset($_POST['event_end_date']) && !empty($_POST['event_end_date']) 
                ? sanitize_text_field($_POST['event_end_date']) 
                : $start_date;
    
    // Check if all-day event
    $is_all_day = isset($_POST['all_day_event']) && $_POST['all_day_event'] == '1';
    
    // Get timezone (use WordPress timezone)
    $timezone_string = wp_timezone_string();
    
    // Format dates for The Events Calendar
    if ($is_all_day) {
        // All-day events: Start at 00:00:00, End at 23:59:59
        // Format: YYYY-MM-DD HH:MM:SS
        $formatted_start = $start_date . ' 00:00:00';
        
        // For all-day events, end date should be the same day if not specified, or the specified end date
        if ($end_date == $start_date) {
            // Single day event
            $formatted_end = $start_date . ' 23:59:59';
        } else {
            // Multi-day event
            $formatted_end = $end_date . ' 23:59:59';
        }
        
        // Ensure end date is not before start date
        if (strtotime($end_date) < strtotime($start_date)) {
            $formatted_end = $start_date . ' 23:59:59';
        }
    } else {
        // Timed events: combine date and time
        $start_time = isset($_POST['event_start_time']) && !empty($_POST['event_start_time']) 
                     ? sanitize_text_field($_POST['event_start_time']) 
                     : '00:00:00';
        $end_time = isset($_POST['event_end_time']) && !empty($_POST['event_end_time']) 
                   ? sanitize_text_field($_POST['event_end_time']) 
                   : '23:59:59';
        
        // Ensure times are in HH:MM:SS format
        if (strlen($start_time) == 5) {
            $start_time .= ':00';
        }
        if (strlen($end_time) == 5) {
            $end_time .= ':00';
        }
        
        $formatted_start = $start_date . ' ' . $start_time;
        $formatted_end = $end_date . ' ' . $end_time;
        
        // If end datetime is before start datetime, adjust
        if (strtotime($formatted_end) < strtotime($formatted_start)) {
            $formatted_end = $start_date . ' ' . $end_time;
        }
    }
    
    // Save event dates (required by The Events Calendar)
    // Format must be: YYYY-MM-DD HH:MM:SS
    update_post_meta($post_id, '_EventStartDate', $formatted_start);
    update_post_meta($post_id, '_EventEndDate', $formatted_end);
    
    // Save timezone
    update_post_meta($post_id, '_EventTimezone', $timezone_string);
    
    // Mark as all day event or not (must be 'yes' or 'no' string)
    update_post_meta($post_id, '_EventAllDay', $is_all_day ? 'yes' : 'no');
    
    // Calculate and save UTC dates (required by The Events Calendar)
    $timezone = new DateTimeZone($timezone_string);
    try {
        $start_dt = new DateTime($formatted_start, $timezone);
        $end_dt = new DateTime($formatted_end, $timezone);
        
        $utc_timezone = new DateTimeZone('UTC');
        $start_dt->setTimezone($utc_timezone);
        $end_dt->setTimezone($utc_timezone);
        
        update_post_meta($post_id, '_EventStartDateUTC', $start_dt->format('Y-m-d H:i:s'));
        update_post_meta($post_id, '_EventEndDateUTC', $end_dt->format('Y-m-d H:i:s'));
    } catch (Exception $e) {
        // Fallback if timezone conversion fails - use same as local time
        update_post_meta($post_id, '_EventStartDateUTC', $formatted_start);
        update_post_meta($post_id, '_EventEndDateUTC', $formatted_end);
    }
    
    // Save EventDuration (optional but helpful for TEC)
    $start_timestamp = strtotime($formatted_start);
    $end_timestamp = strtotime($formatted_end);
    if ($start_timestamp && $end_timestamp) {
        $duration = $end_timestamp - $start_timestamp;
        update_post_meta($post_id, '_EventDuration', $duration);
    }
    
    // Save category
    if (!empty($_POST['reminder_category'])) {
        $category = sanitize_text_field($_POST['reminder_category']);
        wp_set_object_terms($post_id, $category, 'tribe_events_cat');
    }
    
    // Save user ID for reminders page filtering
    update_post_meta($post_id, '_gc_user_id', $user_id);
    
    // Schedule reminder email 7 days before event.
    gcp_schedule_reminder_email( $post_id, $user_id, $formatted_start );

    return [
        'success' => true,
        'message' => 'Reminder created successfully!',
        'post_id' => $post_id
    ];
}

get_header();
?>

 <div class="page-content"> 
    <?php echo do_shortcode('[add_reminder_form]'); ?>
 </div> 

<?php
get_footer();
