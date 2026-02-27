<?php
if (!defined('ABSPATH')) {
    exit;
}

function ikonic_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));

    register_nav_menus(
        array(
            'primary' => __('Primary Menu', 'ikonic-test'),
            'footer'  => __('Footer Menu', 'ikonic-test'),
        )
    );
}
add_action('after_setup_theme', 'ikonic_theme_setup');

function ikonic_enqueue_assets() {
    wp_enqueue_style('ikonic-google-fonts', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Poppins:wght@500;600;700;800&display=swap', array(), null);
    wp_enqueue_style('ikonic-theme-style', get_stylesheet_uri(), array('ikonic-google-fonts'), '1.1.0');
}
add_action('wp_enqueue_scripts', 'ikonic_enqueue_assets');

function ikonic_register_sidebar() {
    register_sidebar(
        array(
            'name'          => __('Sidebar', 'ikonic-test'),
            'id'            => 'main-sidebar',
            'description'   => __('Widgets shown on blog and archive templates.', 'ikonic-test'),
            'before_widget' => '<section class="widget">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        )
    );
}
add_action('widgets_init', 'ikonic_register_sidebar');

function ikonic_register_projects_post_type() {
    $args = array(
        'label'        => __('Projects', 'ikonic-test'),
        'public'       => true,
        'has_archive'  => true,
        'menu_icon'    => 'dashicons-portfolio',
        'rewrite'      => array('slug' => 'projects'),
        'supports'     => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest' => true,
    );

    register_post_type('projects', $args);
}
add_action('init', 'ikonic_register_projects_post_type');

function ikonic_add_project_meta_boxes() {
    add_meta_box('ikonic_project_meta_box', __('Project Details', 'ikonic-test'), 'ikonic_project_meta_box_callback', 'projects', 'normal', 'high');
}
add_action('add_meta_boxes', 'ikonic_add_project_meta_boxes');

function ikonic_project_meta_box_callback($post) {
    $fields = array(
        'project_name'        => __('Project Name', 'ikonic-test'),
        'project_description' => __('Short Description', 'ikonic-test'),
        'project_start_date'  => __('Start Date', 'ikonic-test'),
        'project_end_date'    => __('End Date', 'ikonic-test'),
        'project_url'         => __('Project URL', 'ikonic-test'),
    );

    wp_nonce_field('ikonic_save_project_meta', 'ikonic_project_meta_nonce');

    foreach ($fields as $field => $label) {
        $value = get_post_meta($post->ID, $field, true);
        echo '<p><label for="' . esc_attr($field) . '">' . esc_html($label) . '</label>';
        echo '<input type="text" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="' . esc_attr($value) . '" class="widefat" /></p>';
    }
}

function ikonic_save_project_meta_boxes($post_id) {
    if (!isset($_POST['ikonic_project_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ikonic_project_meta_nonce'])), 'ikonic_save_project_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $fields = array('project_name', 'project_description', 'project_start_date', 'project_end_date', 'project_url');

    foreach ($fields as $field) {
        if (!isset($_POST[$field])) {
            continue;
        }

        $value = wp_unslash($_POST[$field]);
        $sanitized = 'project_url' === $field ? esc_url_raw($value) : sanitize_text_field($value);
        update_post_meta($post_id, $field, $sanitized);
    }
}
add_action('save_post_projects', 'ikonic_save_project_meta_boxes');

function ikonic_register_custom_endpoint() {
    register_rest_route(
        'custom/v1',
        '/projects',
        array(
            'methods'             => 'GET',
            'callback'            => 'ikonic_get_projects',
            'permission_callback' => '__return_true',
        )
    );
}
add_action('rest_api_init', 'ikonic_register_custom_endpoint');

function ikonic_get_projects() {
    $query = new WP_Query(
        array(
            'post_type'      => 'projects',
            'posts_per_page' => -1,
        )
    );

    $projects = array();

    while ($query->have_posts()) {
        $query->the_post();
        $projects[] = array(
            'title'       => get_the_title(),
            'url'         => get_permalink(),
            'start_date'  => get_post_meta(get_the_ID(), 'project_start_date', true),
            'end_date'    => get_post_meta(get_the_ID(), 'project_end_date', true),
            'description' => get_post_meta(get_the_ID(), 'project_description', true),
        );
    }
    wp_reset_postdata();

    return rest_ensure_response($projects);
}

function ikonic_customize_register($wp_customize) {
    $wp_customize->add_section('ikonic_home_content', array(
        'title'    => __('Homepage Content', 'ikonic-test'),
        'priority' => 40,
    ));

    $settings = array(
        'ikonic_hero_title'       => __('Agrova Farming & Agriculture WordPress Theme', 'ikonic-test'),
        'ikonic_hero_text'        => __('Build a modern farming website with service highlights, project stories, and blog updates.', 'ikonic-test'),
        'ikonic_about_title'      => __('Fresh Food, Healthy Farming', 'ikonic-test'),
        'ikonic_about_text'       => __('This starter theme replicates the Agrova-style layout and keeps all sections editable from WordPress.', 'ikonic-test'),
        'ikonic_contact_phone'    => __('+1 (307) 555-0133', 'ikonic-test'),
        'ikonic_contact_email'    => __('hello@example.com', 'ikonic-test'),
    );

    foreach ($settings as $setting => $default) {
        $wp_customize->add_setting($setting, array(
            'default'           => $default,
            'sanitize_callback' => 'sanitize_text_field',
        ));

        $wp_customize->add_control($setting, array(
            'label'   => ucwords(str_replace('_', ' ', str_replace('ikonic_', '', $setting))),
            'section' => 'ikonic_home_content',
            'type'    => 'text',
        ));
    }
}
add_action('customize_register', 'ikonic_customize_register');
