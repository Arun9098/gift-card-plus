<?php 
// Add fields to form
add_action('woocommerce_register_form_start', function() {
    ?>
    <p class="form-row form-row-wide">
        <label for="reg_first_name"><?php esc_html_e('First Name', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="first_name" id="reg_first_name" value="<?php echo esc_attr(!empty($_POST['first_name']) ? $_POST['first_name'] : ''); ?>" required />
    </p>

    <p class="form-row form-row-wide">
        <label for="reg_phone"><?php esc_html_e('Mobile Number', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="phone" id="reg_phone" pattern="^(\+?61|0)4\d{8}$" title="Enter valid AU mobile number" value="<?php echo esc_attr(!empty($_POST['phone']) ? $_POST['phone'] : ''); ?>" required />
    </p>

    <p class="form-row form-row-wide">
        <label>
            <input type="checkbox" name="terms" id="reg_terms" required /> I agree to the Terms and Conditions
        </label>
    </p>
    <?php
});
?>