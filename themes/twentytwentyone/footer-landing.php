<footer class="landing-footer">
    <div class="container">
        <div class="footer-widgets">
            <?php if ( is_active_sidebar('landing-footer') ) : ?>
                <?php dynamic_sidebar('landing-footer'); ?>
            <?php else: ?>
                <p>&copy; <?php echo date('Y'); ?> Your Company. All Rights Reserved.</p>
            <?php endif; ?>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
