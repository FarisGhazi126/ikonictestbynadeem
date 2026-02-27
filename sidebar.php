<aside>
    <?php if (is_active_sidebar('main-sidebar')) : ?>
        <?php dynamic_sidebar('main-sidebar'); ?>
    <?php else : ?>
        <section class="widget card">
            <h3 class="widget-title"><?php esc_html_e('Archives', 'ikonic-test'); ?></h3>
            <ul><?php wp_get_archives(array('type' => 'monthly')); ?></ul>
        </section>
    <?php endif; ?>
</aside>
