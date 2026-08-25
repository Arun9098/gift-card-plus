<?php
/**
 * Template Name: Custom Reset Password
 */
get_header();

$user_login = $_GET['login'] ?? '';
$key = $_GET['key'] ?? '';

if (empty($user_login) || empty($key)) {
    echo "<div class='max-w-md mx-auto mt-10 p-4 border border-red-300 bg-red-100 text-red-800 rounded'>
        <strong>Error:</strong> Invalid password reset link.
    </div>";
    get_footer();
    exit;
}
?>

<style>
    .reset-password-wrapper {
        max-width: 400px;
        margin: 50px auto;
        padding: 30px;
        background-color: #ffffff;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        font-family: 'Segoe UI', sans-serif;
    }
    .reset-password-wrapper h2 {
        text-align: center;
        margin-bottom: 25px;
        font-size: 24px;
        color: #333;
    }
    .reset-password-wrapper label {
        display: block;
        margin-top: 15px;
        font-weight: 600;
        color: #555;
    }
    .reset-password-wrapper input {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    .reset-password-wrapper button {
        margin-top: 20px;
        width: 100%;
        padding: 12px;
        background-color: #0073aa;
        color: white;
        font-weight: bold;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .reset-password-wrapper button:hover {
        background-color: #005c8a;
    }
    #reset-result {
        margin-top: 15px;
        text-align: center;
        font-weight: 500;
    }
    #reset-result.success {
        color: green;
    }
    #reset-result.error {
        color: red;
    }
    .password-field {
        position: relative;
    }
    .password-field input {
        padding-right: 40px; /* space for icon */
    }
    .password-field .toggle-password {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        cursor: pointer;
        color: #555;
    }
</style>
<script>
jQuery(document).ready(function($) {
    $('.toggle-password').on('click', function() {
        const targetInput = $('#' + $(this).data('target'));
        const icon = $(this).find('i');
        const type = targetInput.attr('type') === 'password' ? 'text' : 'password';
        targetInput.attr('type', type);

        icon.toggleClass('fa-eye fa-eye-slash');
    });
});
</script>

<!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" /> -->

<div class="reset-password-wrapper">
    <h2>Set a New Password</h2>
    <form id="custom-reset-password-form">
        <input type="hidden" name="login" value="<?php echo esc_attr($user_login); ?>">
        <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
        <label for="new_password">New Password</label>
        <div class="password-field">
            <input type="password" name="new_password" id="new_password" required>
            <span class="toggle-password" data-target="new_password"><i class="fa fa-eye-slash"></i></span>
        </div>

        <label for="confirm_password">Confirm Password</label>
        <div class="password-field">
            <input type="password" name="confirm_password" id="confirm_password" required>
            <span class="toggle-password" data-target="confirm_password"><i class="fa fa-eye-slash"></i></span>
        </div>


        <button type="submit">Reset Password</button>
        <div id="reset-result"></div>
    </form>
</div>

<script>
jQuery(document).ready(function ($) {
    $('#custom-reset-password-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $result = $('#reset-result').removeClass('success error').text('');
        
        const data = {
            action: 'process_custom_password_reset',
            login: $form.find('[name="login"]').val(),
            key: $form.find('[name="key"]').val(),
            new_password: $form.find('[name="new_password"]').val(),
            confirm_password: $form.find('[name="confirm_password"]').val(),
        };

        $.post(userData.ajax_url, data, function (res) {
            if (res.success) {
                $result.text('Password has been reset successfully.').addClass('success');
                $form[0].reset();
            } else {
                $result.text(res.data || 'Failed to reset password.').addClass('error');
            }
        });
    });
});
</script>

<?php get_footer(); ?>
