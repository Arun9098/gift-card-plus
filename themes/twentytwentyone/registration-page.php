<?php
/**
 * Template Name: Supplier Registration
 */
get_header();

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['supplier_register_nonce']) && wp_verify_nonce($_POST['supplier_register_nonce'], 'supplier_register_action')) {
    $username   = sanitize_user($_POST['username']);
    $email      = sanitize_email($_POST['email']);
    $password   = sanitize_text_field($_POST['password']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name  = sanitize_text_field($_POST['last_name']);

    $errors = [];

    if (username_exists($username) || email_exists($email)) {
        $errors[] = "Username or email already exists.";
    }

    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {
        $user_id = wp_create_user($username, $password, $email);

        if (!is_wp_error($user_id)) {
            wp_update_user([
                'ID'           => $user_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => $first_name . ' ' . $last_name
            ]);

            // Set role to supplier
            $user = new WP_User($user_id);
            $user->set_role('supplier');

            wp_redirect(home_url('/login'));
            exit;
        } else {
            $errors[] = $user_id->get_error_message();
        }
    }
}
?>

<div class="supplier-auth-form">
  <h2>Register as Supplier</h2>

  <?php
  if (!empty($errors)) {
      foreach ($errors as $error) {
          echo '<p class="error-message">' . esc_html($error) . '</p>';
      }
  }
  ?>

  <form method="post">
    <?php wp_nonce_field('supplier_register_action', 'supplier_register_nonce'); ?>

    <label>First Name</label>
    <input type="text" name="first_name" required />

    <label>Last Name</label>
    <input type="text" name="last_name" required />

    <label>Username</label>
    <input type="text" name="username" required />

    <label>Email</label>
    <input type="email" name="email" required />

    <label>Password</label>
    <input type="password" name="password" required />

    <input type="submit" value="Register" />
  </form>
  <p>Already Registered? <a href="<?php echo esc_url(home_url('/login/')); ?>">Login Here</a></p>
</div>

<?php get_footer(); ?>
