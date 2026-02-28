<?php
/**
 * Plugin Name: Ikonic Contact SMTP Form
 * Description: Advanced contact form builder with SMTP delivery, analytics, and modular OOP architecture.
 * Version: 6.1.0
 * Author: Ikonic
 * Text Domain: ikonic-contact-smtp-form
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ICSF_PLUGIN_FILE', __FILE__);
define('ICSF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ICSF_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ICSF_PLUGIN_DIR . 'includes/class-icsf-plugin.php';
require_once ICSF_PLUGIN_DIR . 'includes/class-icsf-admin.php';
require_once ICSF_PLUGIN_DIR . 'includes/class-icsf-forms.php';
require_once ICSF_PLUGIN_DIR . 'includes/class-icsf-analytics.php';

$icsf_plugin = new ICSF_Plugin();
$icsf_analytics = new ICSF_Analytics($icsf_plugin);
$icsf_forms = new ICSF_Forms($icsf_plugin, $icsf_analytics);
new ICSF_Admin($icsf_plugin, $icsf_forms, $icsf_analytics);
