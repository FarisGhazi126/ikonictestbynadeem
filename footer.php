<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<footer class="site-footer">
    <section class="footer-cta">
        <div class="container footer-cta-inner">
            <p class="eyebrow"><?php esc_html_e('Get in touch', 'ikonic-test'); ?></p>
            <h2><?php esc_html_e("Let's Get Started", 'ikonic-test'); ?></h2>
            <a class="btn" href="#contact"><?php esc_html_e('Contact Us', 'ikonic-test'); ?></a>
        </div>
    </section>

    <div class="container footer-wrap">
        <div>
            <h3><?php bloginfo('name'); ?></h3>
            <p><?php esc_html_e('Agriculture consulting, modern farming support and high-quality production workflows.', 'ikonic-test'); ?></p>
        </div>
        <div>
            <h4><?php esc_html_e('Quick Links', 'ikonic-test'); ?></h4>
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
    </div>
    <p class="copyright"><?php echo esc_html(date_i18n('Y')); ?> © <?php bloginfo('name'); ?>.</p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
