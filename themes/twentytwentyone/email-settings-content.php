<?php

// Get email templates from CPT
function get_email_templates() {
    $templates = get_posts(array(
        'post_type' => 'email_template',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    $formatted_templates = array();
    
    foreach ($templates as $template) {
        $formatted_templates[] = array(
            'id' => $template->ID,
            'name' => $template->post_title,
            'sender_name' => get_post_meta($template->ID, '_sender_name', true),
            'sender_address' => get_post_meta($template->ID, '_sender_address', true),
            'trigger' => get_post_meta($template->ID, '_trigger', true),
            'subject' => get_post_meta($template->ID, '_subject', true),
            'html_content' => get_post_meta($template->ID, '_html_content', true),
            'plain_content' => get_post_meta($template->ID, '_plain_content', true)
        );
    }
    
    return $formatted_templates;
}

// Display the email settings page
function display_email_settings() {
    $templates = get_email_templates();
    $selected_template = isset($_GET['template_id']) ? intval($_GET['template_id']) : (!empty($templates) ? $templates[0]['id'] : 0);
    $current_template = current(array_filter($templates, function($t) use ($selected_template) {
        return $t['id'] == $selected_template;
    }));
    ?>
    
    <div class="email-settings-container">
        <h1>Email Settings</h1>
        
        <?php if (empty($templates)): ?>
            <div class="notice notice-warning">
                <p>No email templates found. Please create some first.</p>
            </div>
        <?php else: ?>
            <div class="email-settings-card">
                <div class="settings-section">
                    <h2>Template Configuration</h2>
                    
                    <div class="form-group">
                        <label for="template-select">Select Email</label>
                        <select id="template-select" class="form-control">
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo $template['id']; ?>" <?php selected($template['id'], $selected_template); ?>>
                                    <?php echo esc_html($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sender-name">Default Sender Name</label>
                        <input type="text" id="sender-name" class="form-control" value="<?php echo esc_attr($current_template['sender_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="sender-address">Default Sender Address</label>
                        <input type="email" id="sender-address" class="form-control" value="<?php echo esc_attr($current_template['sender_address']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Send Trigger</label>
                        <p class="form-control-static"><?php echo esc_html($current_template['trigger']); ?></p>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h2>Test Email</h2>
                    
                    <div class="form-group">
                        <label for="test-email">Send Test email to:</label>
                        <div class="input-group">
                            <input type="email" id="test-email" class="form-control" value="">
                            <span class="input-group-btn">
                                <button id="send-test-email" class="btn btn-primary btn-black-white btn-primary-white">Send test email</button>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h2>Template Settings</h2>
                    
                    <div class="template-tabs">
                        <button class="tab-button active" data-tab="template-preview">Preview</button>
                        <button class="tab-button" data-tab="template-html">HTML</button>
                        <button class="tab-button" data-tab="template-plain">Plain Text</button>
                    </div>
                    
                    <div id="template-preview" class="tab-content active">
                        <div class="email-preview">
                            <h3><?php echo esc_html($current_template['subject']); ?></h3>
                            <?php echo wp_kses_post($current_template['html_content']); ?>
                        </div>
                    </div>
                    
                    <div id="template-html" class="tab-content">
                        <pre><?php echo esc_html($current_template['html_content']); ?></pre>
                    </div>
                    
                    <div id="template-plain" class="tab-content">
                        <pre><?php echo esc_html($current_template['plain_content']); ?></pre>
                    </div>
                </div>
                
                <div class="settings-footer">
                    <button id="save-settings" class="btn btn-primary">Save</button>
                    <a href="<?php echo admin_url('post-new.php?post_type=email_template'); ?>" class="btn btn-secondary">Add New Template</a>
                    <a href="<?php echo admin_url('edit.php?post_type=email_template'); ?>" class="btn btn-secondary">Manage Templates</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Success Modal -->
    <div id="success-modal" class="modal">
        <div class="emails-modal-content">
            <span class="close">&times;</span>
            <h2>Success</h2>
            <p id="success-message">Settings saved successfully!</p>
            <button class="btn btn-primary modal-close">OK</button>
        </div>
    </div>
    <?php
}

display_email_settings();