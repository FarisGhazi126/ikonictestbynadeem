<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<footer class="site-footer">
    <div class="container footer-wrap">
        <p><?php echo esc_html(date_i18n('Y')); ?> © <?php bloginfo('name'); ?>. <?php esc_html_e('All rights reserved.', 'ikonic-test'); ?></p>
        <?php
        wp_nav_menu(
            array(
                'theme_location' => 'footer',
                'menu_class'     => 'footer-menu',
                'container'      => false,
                'fallback_cb'    => false,
            )
        );
        ?>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
