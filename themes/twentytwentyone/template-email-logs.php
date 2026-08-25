<?php
/**
 * Template Name: Email Logs
 */
get_header(); ?>

<div class="wrap">
    <div id="primary" class="content-area">
        <main id="main" class="site-main" role="main">
            <?php
            // if (current_user_can('administrator')) {
                // Our custom email logs display will go here
                include_once(plugin_dir_path(__FILE__) . 'custom-email-logs.php');
            // } else {
            //     echo '<p>You do not have permission to view this page.</p>';
            // }
            ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>