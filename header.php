<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="topbar">
    <div class="container topbar-inner">
        <p><?php esc_html_e('Mon - Sat: 8:00 am - 7:00 pm', 'ikonic-test'); ?></p>
        <p><?php esc_html_e('Call us: +1 (307) 555-0133', 'ikonic-test'); ?></p>
    </div>
</div>
<header class="site-header">
    <div class="container header-wrap">
        <a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
        <nav class="site-nav" aria-label="<?php esc_attr_e('Primary menu', 'ikonic-test'); ?>">
            <?php
            wp_nav_menu(
                array(
                    'theme_location' => 'primary',
                    'menu_class'     => 'menu-list',
                    'container'      => false,
                    'fallback_cb'    => false,
                )
            );
            ?>
        </nav>
        <a class="header-btn" href="#contact"><?php esc_html_e('Get a Quote', 'ikonic-test'); ?></a>
    </div>
</header>
