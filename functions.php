<?php
// Always good to ensure this file doesn't get accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Let's enqueue our CSS and JS files
function theme_enqueue_assets() {
    // Add the main style.css file
    wp_enqueue_style('theme-style', get_stylesheet_uri());

    // Optional: Add a custom script file if needed
    wp_enqueue_script('theme-scripts', get_template_directory_uri() . '/js/scripts.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'theme_enqueue_assets');

// Time to register our menu - you’ll see it in the dashboard under "Appearance > Menus"
function theme_register_menu() {
    register_nav_menu('main-menu', __('Main Menu'));
}
add_action('init', 'theme_register_menu');

// Creating our custom "Projects" post type
function theme_projects_post_type() {
    $args = array(
        'label' => __('Projects'),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'rewrite' => array('slug' => 'projects'),
        'menu_icon' => 'dashicons-portfolio', // Just for fun, a nice icon in the dashboard!
    );
    register_post_type('projects', $args);
}
add_action('init', 'theme_projects_post_type');

// Add custom meta boxes for project details
function theme_add_meta_boxes() {
    add_meta_box('project_meta_box', 'Project Details', 'theme_project_meta_box_callback', 'projects', 'normal', 'high');
}
add_action('add_meta_boxes', 'theme_add_meta_boxes');

// This is what displays the meta box in the dashboard
function theme_project_meta_box_callback($post) {
    $fields = [
        'project_name', 
        'project_description', 
        'project_start_date', 
        'project_end_date', 
        'project_url'
    ];

    // Output the inputs for all the fields
    foreach ($fields as $field) {
        $value = get_post_meta($post->ID, $field, true); // Grab saved value
        echo '<p><label for="' . $field . '">' . ucwords(str_replace('_', ' ', $field)) . '</label>';
        echo '<input type="text" id="' . $field . '" name="' . $field . '" value="' . esc_attr($value) . '" style="width:100%;" /></p>';
    }
}

// Save custom field data when the post is saved
function theme_save_meta_boxes($post_id) {
    $fields = [
        'project_name', 
        'project_description', 
        'project_start_date', 
        'project_end_date', 
        'project_url'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}
add_action('save_post', 'theme_save_meta_boxes');

// Adding a custom API endpoint for "Projects"
function theme_register_custom_endpoint() {
    register_rest_route('custom/v1', '/projects', array(
        'methods' => 'GET',
        'callback' => 'theme_get_projects',
    ));
}
add_action('rest_api_init', 'theme_register_custom_endpoint');

function theme_get_projects() {
    $query = new WP_Query(array('post_type' => 'projects', 'posts_per_page' => -1));
    $projects = [];

    while ($query->have_posts()) {
        $query->the_post();
        $projects[] = array(
            'title' => get_the_title(),
            'url' => get_permalink(),
            'start_date' => get_post_meta(get_the_ID(), 'project_start_date', true),
            'end_date' => get_post_meta(get_the_ID(), 'project_end_date', true),
        );
    }
    wp_reset_postdata();

    return $projects; // JSON response
}
