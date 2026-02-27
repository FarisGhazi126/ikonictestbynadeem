<?php
/**
 * Plugin Name: Ikonic Contact SMTP Form
 * Description: Advanced contact form builder with drag-and-drop fields, analytics, and SMTP delivery.
 * Version: 3.0.0
 * Author: Ikonic
 * Text Domain: ikonic-contact-smtp-form
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Ikonic_Contact_SMTP_Form {
    private const SMTP_OPTION_KEY = 'icsf_smtp_settings';
    private const FORMS_OPTION_KEY = 'icsf_forms';
    private const LOGS_OPTION_KEY = 'icsf_submission_logs';
    private const ADMIN_NONCE_ACTION = 'icsf_admin_form_action';
    private const SUBMIT_NONCE_ACTION = 'icsf_contact_form_submit_';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_smtp_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_shortcode('ikonic_contact_form', [$this, 'render_contact_form_shortcode']);
        add_action('init', [$this, 'handle_form_submission']);

        add_action('admin_post_icsf_save_form', [$this, 'handle_save_form']);
        add_action('admin_post_icsf_delete_form', [$this, 'handle_delete_form']);
        add_action('admin_post_icsf_export_logs', [$this, 'handle_export_logs']);

        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __('Ikonic Form Builder', 'ikonic-contact-smtp-form'),
            __('Ikonic Form Builder', 'ikonic-contact-smtp-form'),
            'manage_options',
            'ikonic-contact-form-builder',
            [$this, 'render_forms_page'],
            'dashicons-feedback',
            58
        );

        add_submenu_page('ikonic-contact-form-builder', __('Forms', 'ikonic-contact-smtp-form'), __('Forms', 'ikonic-contact-smtp-form'), 'manage_options', 'ikonic-contact-form-builder', [$this, 'render_forms_page']);
        add_submenu_page('ikonic-contact-form-builder', __('Analytics', 'ikonic-contact-smtp-form'), __('Analytics', 'ikonic-contact-smtp-form'), 'manage_options', 'ikonic-contact-analytics', [$this, 'render_analytics_page']);
        add_submenu_page('ikonic-contact-form-builder', __('SMTP Settings', 'ikonic-contact-smtp-form'), __('SMTP Settings', 'ikonic-contact-smtp-form'), 'manage_options', 'ikonic-contact-smtp-settings', [$this, 'render_smtp_settings_page']);
    }

    public function enqueue_admin_assets(string $hook): void {
        $allowed = ['toplevel_page_ikonic-contact-form-builder', 'ikonic-form-builder_page_ikonic-contact-analytics'];
        if (!in_array($hook, $allowed, true)) {
            return;
        }

        wp_enqueue_script('jquery-ui-sortable');

        $js = <<<JS
jQuery(function($){
    var tbody = $('#icsf-fields-body');
    if (tbody.length) {
        tbody.sortable({ handle: '.icsf-drag-handle', axis: 'y' });
    }

    $('#icsf-add-field-row').on('click', function(e){
        e.preventDefault();
        var idx = tbody.find('tr').length;
        var row = '<tr>' +
            '<td class="icsf-drag-handle" style="cursor:move;">☰</td>' +
            '<td><input type="text" name="fields['+idx+'][label]" value="" /></td>' +
            '<td><input type="text" name="fields['+idx+'][name]" value="" /></td>' +
            '<td><select name="fields['+idx+'][type]"><option value="text">text</option><option value="email">email</option><option value="textarea">textarea</option><option value="select">select</option><option value="radio">radio</option><option value="checkbox">checkbox</option><option value="tel">tel</option><option value="number">number</option><option value="date">date</option></select></td>' +
            '<td><input type="text" name="fields['+idx+'][placeholder]" value="" /></td>' +
            '<td><input type="text" name="fields['+idx+'][options]" value="" /></td>' +
            '<td><label><input type="checkbox" name="fields['+idx+'][required]" value="1" /> Required</label></td>' +
            '<td><button class="button icsf-remove-row">Remove</button></td>' +
            '</tr>';
        tbody.append(row);
    });

    $(document).on('click', '.icsf-remove-row', function(e){
        e.preventDefault();
        $(this).closest('tr').remove();
    });
});
JS;
        wp_add_inline_script('jquery-ui-sortable', $js);

        $css = <<<CSS
.icsf-form-wrap{max-width:760px;margin:20px auto;padding:28px;border-radius:18px}
.icsf-form-wrap .icsf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.icsf-form-wrap .icsf-field-full{grid-column:1/-1}
.icsf-form-wrap label{display:block;font-weight:600;margin-bottom:6px}
.icsf-form-wrap input,.icsf-form-wrap textarea,.icsf-form-wrap select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #d7dce4;font-size:14px;box-sizing:border-box}
.icsf-form-wrap button{padding:12px 22px;border-radius:12px;border:0;cursor:pointer;font-weight:700}
.icsf-theme-minimal{background:#fff;border:1px solid #eceff4;box-shadow:0 8px 28px rgba(18,38,63,.06)}
.icsf-theme-minimal button{background:#2678ff;color:#fff}
.icsf-theme-glass{background:rgba(20,21,37,.7);border:1px solid rgba(133,145,255,.35);backdrop-filter:blur(8px);box-shadow:0 12px 40px rgba(47,70,255,.25);color:#fff}
.icsf-theme-glass input,.icsf-theme-glass textarea,.icsf-theme-glass select{background:rgba(255,255,255,.92)}
.icsf-theme-glass button{background:linear-gradient(135deg,#6b7bff,#00d1ff);color:#fff}
.icsf-theme-neon{background:#0b1020;border:1px solid #00d1ff;box-shadow:0 0 0 1px rgba(0,209,255,.3),0 16px 50px rgba(0,209,255,.18);color:#e9f8ff}
.icsf-theme-neon input,.icsf-theme-neon textarea,.icsf-theme-neon select{background:#111936;color:#e9f8ff;border-color:#26355f}
.icsf-theme-neon button{background:linear-gradient(90deg,#00d1ff,#6b7bff);color:#071021}
@media(max-width:640px){.icsf-form-wrap .icsf-grid{grid-template-columns:1fr}}
CSS;
        wp_add_inline_style('wp-admin', $css);
    }

    public function register_smtp_settings(): void {
        register_setting('icsf_smtp_settings_group', self::SMTP_OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_smtp_settings'],
            'default' => $this->default_smtp_settings(),
        ]);

        add_settings_section('icsf_smtp_general_section', __('General Email Settings', 'ikonic-contact-smtp-form'), '__return_false', 'ikonic-contact-smtp-settings');
        add_settings_section('icsf_smtp_section', __('SMTP Configuration', 'ikonic-contact-smtp-form'), '__return_false', 'ikonic-contact-smtp-settings');

        $fields = [
            'to_email' => __('Default Recipient Email', 'ikonic-contact-smtp-form'),
            'from_name' => __('From Name', 'ikonic-contact-smtp-form'),
            'from_email' => __('From Email', 'ikonic-contact-smtp-form'),
            'enable_smtp' => __('Enable SMTP', 'ikonic-contact-smtp-form'),
            'smtp_host' => __('SMTP Host', 'ikonic-contact-smtp-form'),
            'smtp_port' => __('SMTP Port', 'ikonic-contact-smtp-form'),
            'smtp_username' => __('SMTP Username', 'ikonic-contact-smtp-form'),
            'smtp_password' => __('SMTP Password', 'ikonic-contact-smtp-form'),
            'smtp_secure' => __('SMTP Encryption (tls/ssl/none)', 'ikonic-contact-smtp-form'),
        ];

        foreach ($fields as $key => $label) {
            add_settings_field('icsf_' . $key, $label, [$this, $key === 'enable_smtp' ? 'render_smtp_checkbox_field' : 'render_smtp_text_field'], 'ikonic-contact-smtp-settings', in_array($key, ['to_email', 'from_name', 'from_email'], true) ? 'icsf_smtp_general_section' : 'icsf_smtp_section', ['key' => $key]);
        }
    }

    public function sanitize_smtp_settings(array $input): array {
        $defaults = $this->default_smtp_settings();
        return [
            'to_email' => isset($input['to_email']) ? sanitize_email((string) $input['to_email']) : $defaults['to_email'],
            'from_name' => isset($input['from_name']) ? sanitize_text_field((string) $input['from_name']) : $defaults['from_name'],
            'from_email' => isset($input['from_email']) ? sanitize_email((string) $input['from_email']) : $defaults['from_email'],
            'enable_smtp' => !empty($input['enable_smtp']) ? 1 : 0,
            'smtp_host' => isset($input['smtp_host']) ? sanitize_text_field((string) $input['smtp_host']) : '',
            'smtp_port' => isset($input['smtp_port']) ? absint($input['smtp_port']) : 587,
            'smtp_username' => isset($input['smtp_username']) ? sanitize_text_field((string) $input['smtp_username']) : '',
            'smtp_password' => isset($input['smtp_password']) ? sanitize_text_field((string) $input['smtp_password']) : '',
            'smtp_secure' => isset($input['smtp_secure']) && in_array(strtolower((string) $input['smtp_secure']), ['tls', 'ssl', 'none'], true) ? strtolower((string) $input['smtp_secure']) : 'tls',
        ];
    }

    public function render_smtp_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SMTP Settings', 'ikonic-contact-smtp-form'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('icsf_smtp_settings_group'); ?>
                <?php do_settings_sections('ikonic-contact-smtp-settings'); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_forms_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $forms = $this->get_forms();
        $active_form_id = isset($_GET['form_id']) ? sanitize_key(wp_unslash($_GET['form_id'])) : '';
        $active_form = $active_form_id && isset($forms[$active_form_id]) ? $forms[$active_form_id] : $this->default_form();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Advanced Form Builder (Drag & Drop)', 'ikonic-contact-smtp-form'); ?></h1>
            <p><?php esc_html_e('Create forms and drag rows to reorder fields. Use shortcode [ikonic_contact_form id="your-id"].', 'ikonic-contact-smtp-form'); ?></p>

            <h2><?php esc_html_e('Saved Forms', 'ikonic-contact-smtp-form'); ?></h2>
            <table class="widefat striped" style="max-width:1050px;">
                <thead><tr><th><?php esc_html_e('Name', 'ikonic-contact-smtp-form'); ?></th><th><?php esc_html_e('ID', 'ikonic-contact-smtp-form'); ?></th><th><?php esc_html_e('Shortcode', 'ikonic-contact-smtp-form'); ?></th><th><?php esc_html_e('Actions', 'ikonic-contact-smtp-form'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($forms)) : ?>
                    <tr><td colspan="4"><?php esc_html_e('No forms yet.', 'ikonic-contact-smtp-form'); ?></td></tr>
                <?php else : foreach ($forms as $form) : ?>
                    <tr>
                        <td><?php echo esc_html($form['name']); ?></td>
                        <td><?php echo esc_html($form['id']); ?></td>
                        <td><code><?php echo esc_html('[ikonic_contact_form id="' . $form['id'] . '"]'); ?></code></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=ikonic-contact-form-builder&form_id=' . rawurlencode($form['id']))); ?>"><?php esc_html_e('Edit', 'ikonic-contact-smtp-form'); ?></a>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                <input type="hidden" name="action" value="icsf_delete_form" />
                                <input type="hidden" name="form_id" value="<?php echo esc_attr($form['id']); ?>" />
                                <?php wp_nonce_field(self::ADMIN_NONCE_ACTION, 'icsf_admin_nonce'); ?>
                                <button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Delete this form?', 'ikonic-contact-smtp-form')); ?>');"><?php esc_html_e('Delete', 'ikonic-contact-smtp-form'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <hr />
            <h2><?php esc_html_e('Builder', 'ikonic-contact-smtp-form'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="icsf_save_form" />
                <?php wp_nonce_field(self::ADMIN_NONCE_ACTION, 'icsf_admin_nonce'); ?>
                <table class="form-table">
                    <tr><th><?php esc_html_e('Form Name', 'ikonic-contact-smtp-form'); ?></th><td><input type="text" name="form_name" class="regular-text" value="<?php echo esc_attr($active_form['name']); ?>" required /></td></tr>
                    <tr><th><?php esc_html_e('Form ID', 'ikonic-contact-smtp-form'); ?></th><td><input type="text" name="form_id" class="regular-text" value="<?php echo esc_attr($active_form['id']); ?>" placeholder="contact-us" /></td></tr>
                    <tr><th><?php esc_html_e('Recipient Email', 'ikonic-contact-smtp-form'); ?></th><td><input type="email" name="to_email" class="regular-text" value="<?php echo esc_attr($active_form['to_email']); ?>" /></td></tr>
                    <tr><th><?php esc_html_e('Subject Prefix', 'ikonic-contact-smtp-form'); ?></th><td><input type="text" name="subject_prefix" class="regular-text" value="<?php echo esc_attr($active_form['subject_prefix']); ?>" /></td></tr>
                    <tr><th><?php esc_html_e('Success Message', 'ikonic-contact-smtp-form'); ?></th><td><input type="text" name="success_message" class="regular-text" value="<?php echo esc_attr($active_form['success_message']); ?>" /></td></tr>
                    <tr>
                        <th><?php esc_html_e('Style Theme', 'ikonic-contact-smtp-form'); ?></th>
                        <td>
                            <select name="style_theme">
                                <?php foreach ($this->style_themes() as $theme_key => $theme_label) : ?>
                                    <option value="<?php echo esc_attr($theme_key); ?>" <?php selected($active_form['style_theme'], $theme_key); ?>><?php echo esc_html($theme_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr><th><?php esc_html_e('Button Text', 'ikonic-contact-smtp-form'); ?></th><td><input type="text" name="button_text" class="regular-text" value="<?php echo esc_attr($active_form['button_text']); ?>" /></td></tr>
                </table>

                <h3><?php esc_html_e('Field Rows (drag using ☰)', 'ikonic-contact-smtp-form'); ?></h3>
                <table class="widefat striped">
                    <thead><tr><th></th><th>Label</th><th>Name</th><th>Type</th><th>Placeholder</th><th>Options</th><th>Required</th><th>Action</th></tr></thead>
                    <tbody id="icsf-fields-body">
                        <?php foreach ($active_form['fields'] as $i => $field) : ?>
                            <tr>
                                <td class="icsf-drag-handle" style="cursor:move;">☰</td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][label]" value="<?php echo esc_attr($field['label']); ?>" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][name]" value="<?php echo esc_attr($field['name']); ?>" /></td>
                                <td>
                                    <select name="fields[<?php echo esc_attr((string) $i); ?>][type]">
                                        <?php foreach ($this->allowed_field_types() as $type) : ?>
                                            <option value="<?php echo esc_attr($type); ?>" <?php selected($field['type'], $type); ?>><?php echo esc_html($type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][placeholder]" value="<?php echo esc_attr($field['placeholder']); ?>" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][options]" value="<?php echo esc_attr(implode(',', $field['options'])); ?>" /></td>
                                <td><label><input type="checkbox" name="fields[<?php echo esc_attr((string) $i); ?>][required]" value="1" <?php checked(!empty($field['required'])); ?>/> Required</label></td>
                                <td><button class="button icsf-remove-row"><?php esc_html_e('Remove', 'ikonic-contact-smtp-form'); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button class="button" id="icsf-add-field-row"><?php esc_html_e('Add Field', 'ikonic-contact-smtp-form'); ?></button></p>
                <?php submit_button(__('Save Form', 'ikonic-contact-smtp-form')); ?>
            </form>
        </div>
        <?php
    }

    public function render_analytics_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $logs = $this->get_logs();
        $forms = $this->get_forms();
        $total = count($logs);
        $last7 = 0;
        $by_form = [];

        foreach ($logs as $log) {
            $fid = $log['form_id'];
            $by_form[$fid] = ($by_form[$fid] ?? 0) + 1;
            if (strtotime($log['created_at']) >= strtotime('-7 days')) {
                $last7++;
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Form Analytics', 'ikonic-contact-smtp-form'); ?></h1>
            <p><strong><?php esc_html_e('Total submissions:', 'ikonic-contact-smtp-form'); ?></strong> <?php echo esc_html((string) $total); ?></p>
            <p><strong><?php esc_html_e('Last 7 days:', 'ikonic-contact-smtp-form'); ?></strong> <?php echo esc_html((string) $last7); ?></p>

            <h2><?php esc_html_e('Submissions by Form', 'ikonic-contact-smtp-form'); ?></h2>
            <table class="widefat striped" style="max-width:800px;">
                <thead><tr><th><?php esc_html_e('Form', 'ikonic-contact-smtp-form'); ?></th><th><?php esc_html_e('Count', 'ikonic-contact-smtp-form'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($by_form)) : ?>
                    <tr><td colspan="2"><?php esc_html_e('No data yet.', 'ikonic-contact-smtp-form'); ?></td></tr>
                <?php else : foreach ($by_form as $fid => $count) : ?>
                    <tr><td><?php echo esc_html(($forms[$fid]['name'] ?? $fid) . ' (' . $fid . ')'); ?></td><td><?php echo esc_html((string) $count); ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Recent Submissions', 'ikonic-contact-smtp-form'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:8px 0 16px;">
                <input type="hidden" name="action" value="icsf_export_logs" />
                <?php wp_nonce_field(self::ADMIN_NONCE_ACTION, 'icsf_admin_nonce'); ?>
                <button type="submit" class="button button-primary"><?php esc_html_e('Export CSV', 'ikonic-contact-smtp-form'); ?></button>
            </form>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Date', 'ikonic-contact-smtp-form'); ?></th><th><?php esc_html_e('Form ID', 'ikonic-contact-smtp-form'); ?></th><th><?php esc_html_e('IP', 'ikonic-contact-smtp-form'); ?></th><th><?php esc_html_e('Summary', 'ikonic-contact-smtp-form'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($logs)) : ?>
                    <tr><td colspan="4"><?php esc_html_e('No submissions yet.', 'ikonic-contact-smtp-form'); ?></td></tr>
                <?php else : foreach (array_slice(array_reverse($logs), 0, 50) as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log['created_at']); ?></td>
                        <td><?php echo esc_html($log['form_id']); ?></td>
                        <td><?php echo esc_html($log['ip']); ?></td>
                        <td><?php echo esc_html($this->log_summary($log['fields'])); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_save_form(): void {
        if (!$this->verify_admin_request()) {
            return;
        }

        $form_name = isset($_POST['form_name']) ? sanitize_text_field(wp_unslash($_POST['form_name'])) : '';
        $form_id_input = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';
        if ($form_name === '') {
            wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder'));
            exit;
        }

        $form_id = $form_id_input ?: sanitize_title($form_name);
        if ($form_id === '') {
            $form_id = 'form-' . wp_generate_password(6, false, false);
        }

        $fields_raw = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        $fields = $this->sanitize_form_fields($fields_raw);
        if (empty($fields)) {
            $fields = $this->default_form_fields();
        }

        $forms = $this->get_forms();
        $forms[$form_id] = [
            'id' => $form_id,
            'name' => $form_name,
            'to_email' => isset($_POST['to_email']) ? sanitize_email(wp_unslash($_POST['to_email'])) : '',
            'subject_prefix' => isset($_POST['subject_prefix']) ? sanitize_text_field(wp_unslash($_POST['subject_prefix'])) : '[Contact Form]',
            'success_message' => isset($_POST['success_message']) ? sanitize_text_field(wp_unslash($_POST['success_message'])) : 'Thanks! Your message has been sent.',
            'style_theme' => $this->sanitize_theme(isset($_POST['style_theme']) ? sanitize_text_field(wp_unslash($_POST['style_theme'])) : 'minimal'),
            'button_text' => isset($_POST['button_text']) ? sanitize_text_field(wp_unslash($_POST['button_text'])) : 'Send Message',
            'fields' => $fields,
        ];

        update_option(self::FORMS_OPTION_KEY, $forms);
        wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder&form_id=' . rawurlencode($form_id)));
        exit;
    }

    public function handle_delete_form(): void {
        if (!$this->verify_admin_request()) {
            return;
        }

        $form_id = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';
        $forms = $this->get_forms();
        unset($forms[$form_id]);
        update_option(self::FORMS_OPTION_KEY, $forms);

        wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder'));
        exit;
    }

    public function handle_export_logs(): void {
        if (!$this->verify_admin_request()) {
            return;
        }

        $logs = $this->get_logs();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=icsf-submissions.csv');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            exit;
        }

        fputcsv($output, ['date', 'form_id', 'ip', 'user_agent', 'fields']);
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['created_at'],
                $log['form_id'],
                $log['ip'],
                $log['ua'],
                wp_json_encode($log['fields']),
            ]);
        }
        fclose($output);
        exit;
    }

    public function render_contact_form_shortcode(array $atts): string {
        $atts = shortcode_atts(['id' => ''], $atts, 'ikonic_contact_form');
        $form = $this->resolve_form_by_id((string) $atts['id']);
        if (empty($form)) {
            return '<p>' . esc_html__('Form not found.', 'ikonic-contact-smtp-form') . '</p>';
        }

        $notice = '';
        if (isset($_GET['icsf_status'], $_GET['icsf_form_id'])) {
            $status = sanitize_text_field(wp_unslash($_GET['icsf_status']));
            $fid = sanitize_key(wp_unslash($_GET['icsf_form_id']));
            if ($fid === $form['id']) {
                $notice = $status === 'success'
                    ? '<p class="icsf-success" style="color:green;">' . esc_html($form['success_message']) . '</p>'
                    : '<p class="icsf-error" style="color:red;">' . esc_html__('There was an issue sending your message.', 'ikonic-contact-smtp-form') . '</p>';
            }
        }

        ob_start();
        echo $notice;
        echo '<style>' . esc_html($this->frontend_styles()) . '</style>';
        ?>
        <div class="icsf-form-wrap <?php echo esc_attr('icsf-theme-' . $form['style_theme']); ?>">
        <form method="post" class="icsf-contact-form">
            <input type="hidden" name="icsf_submit" value="1" />
            <input type="hidden" name="icsf_form_id" value="<?php echo esc_attr($form['id']); ?>" />
            <?php wp_nonce_field(self::SUBMIT_NONCE_ACTION . $form['id'], 'icsf_nonce'); ?>

            <div class="icsf-grid">
            <?php foreach ($form['fields'] as $field) : ?>
                <p class="<?php echo esc_attr($field['type'] === 'textarea' ? 'icsf-field-full' : ''); ?>">
                    <label><?php echo esc_html($field['label']); ?></label><br />
                    <?php $this->render_front_field($field); ?>
                </p>
            <?php endforeach; ?>
            <p class="icsf-field-full"><button type="submit"><?php echo esc_html($form['button_text']); ?></button></p>
            </div>
        </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function handle_form_submission(): void {
        if (empty($_POST['icsf_submit'])) {
            return;
        }

        $form_id = isset($_POST['icsf_form_id']) ? sanitize_key(wp_unslash($_POST['icsf_form_id'])) : '';
        $form = $this->resolve_form_by_id($form_id);

        if (empty($form) || !isset($_POST['icsf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_nonce'])), self::SUBMIT_NONCE_ACTION . $form['id'])) {
            $this->redirect_with_status('error', $form_id);
        }

        $submitted = [];
        foreach ($form['fields'] as $field) {
            $name = $field['name'];
            $raw = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
            $value = is_array($raw) ? implode(', ', array_map('sanitize_text_field', $raw)) : sanitize_textarea_field((string) $raw);

            if ($field['type'] === 'email') {
                $value = sanitize_email((string) $raw);
                if ($value !== '' && !is_email($value)) {
                    $this->redirect_with_status('error', $form_id);
                }
            }

            if (!empty($field['required']) && trim((string) $value) === '') {
                $this->redirect_with_status('error', $form_id);
            }
            $submitted[$name] = $value;
        }

        $smtp = $this->get_smtp_settings();
        $to = !empty($form['to_email']) ? $form['to_email'] : $smtp['to_email'];
        if (!is_email($to)) {
            $to = get_option('admin_email');
        }

        $subject = trim($form['subject_prefix'] . ' ' . $form['name']);
        $body = "Form: {$form['name']}\nForm ID: {$form['id']}\n\n";
        foreach ($form['fields'] as $f) {
            $body .= $f['label'] . ': ' . ($submitted[$f['name']] ?? '') . "\n";
        }

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . ($smtp['from_name'] ?: get_bloginfo('name')) . ' <' . ($smtp['from_email'] ?: get_option('admin_email')) . '>',
        ];

        $reply = $this->find_first_email_value($form['fields'], $submitted);
        if ($reply !== '') {
            $headers[] = 'Reply-To: <' . $reply . '>';
        }

        $sent = wp_mail($to, $subject, $body, $headers);

        $this->store_log([
            'form_id' => $form['id'],
            'created_at' => current_time('mysql'),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'fields' => $submitted,
            'status' => $sent ? 'success' : 'error',
        ]);

        $this->redirect_with_status($sent ? 'success' : 'error', $form['id']);
    }

    public function configure_phpmailer($phpmailer): void {
        $s = $this->get_smtp_settings();
        if (empty($s['enable_smtp']) || empty($s['smtp_host']) || empty($s['smtp_port'])) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $s['smtp_host'];
        $phpmailer->Port = (int) $s['smtp_port'];
        $phpmailer->SMTPAuth = !empty($s['smtp_username']);

        if (!empty($s['smtp_username'])) {
            $phpmailer->Username = $s['smtp_username'];
            $phpmailer->Password = $s['smtp_password'];
        }

        if (($s['smtp_secure'] ?? 'tls') !== 'none') {
            $phpmailer->SMTPSecure = $s['smtp_secure'];
        }

        if (!empty($s['from_email'])) {
            $phpmailer->From = $s['from_email'];
        }
        if (!empty($s['from_name'])) {
            $phpmailer->FromName = $s['from_name'];
        }
    }

    private function sanitize_form_fields(array $raw_fields): array {
        $clean = [];
        $allowed = $this->allowed_field_types();

        foreach ($raw_fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $label = isset($field['label']) ? sanitize_text_field((string) $field['label']) : '';
            $name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            $type = isset($field['type']) ? strtolower(sanitize_text_field((string) $field['type'])) : 'text';
            $placeholder = isset($field['placeholder']) ? sanitize_text_field((string) $field['placeholder']) : '';
            $options_raw = isset($field['options']) ? sanitize_text_field((string) $field['options']) : '';
            $required = !empty($field['required']) ? 1 : 0;

            if ($label === '' || $name === '') {
                continue;
            }
            if (!in_array($type, $allowed, true)) {
                $type = 'text';
            }

            $options = [];
            if (in_array($type, ['select', 'radio', 'checkbox'], true) && $options_raw !== '') {
                foreach (array_map('trim', explode(',', $options_raw)) as $part) {
                    if ($part !== '') {
                        $options[] = sanitize_text_field($part);
                    }
                }
            }

            $clean[] = [
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'placeholder' => $placeholder,
                'required' => $required,
                'options' => $options,
            ];
        }

        return $clean;
    }

    private function render_front_field(array $field): void {
        $required = !empty($field['required']) ? 'required' : '';
        $name = esc_attr($field['name']);
        $placeholder = esc_attr($field['placeholder']);

        switch ($field['type']) {
            case 'textarea':
                echo '<textarea name="' . $name . '" rows="5" placeholder="' . $placeholder . '" ' . $required . '></textarea>';
                break;
            case 'select':
                echo '<select name="' . $name . '" ' . $required . '><option value="">' . esc_html__('Select', 'ikonic-contact-smtp-form') . '</option>';
                foreach ($field['options'] as $opt) {
                    echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                }
                echo '</select>';
                break;
            case 'radio':
                foreach ($field['options'] as $idx => $opt) {
                    echo '<label style="margin-right:12px;"><input type="radio" name="' . $name . '" value="' . esc_attr($opt) . '" ' . ($idx === 0 ? $required : '') . ' /> ' . esc_html($opt) . '</label>';
                }
                break;
            case 'checkbox':
                foreach ($field['options'] as $opt) {
                    echo '<label style="margin-right:12px;"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr($opt) . '" /> ' . esc_html($opt) . '</label>';
                }
                break;
            case 'number':
            case 'date':
            case 'tel':
            case 'email':
                echo '<input type="' . esc_attr($field['type']) . '" name="' . $name . '" placeholder="' . $placeholder . '" ' . $required . ' />';
                break;
            default:
                echo '<input type="text" name="' . $name . '" placeholder="' . $placeholder . '" ' . $required . ' />';
        }
    }

    private function allowed_field_types(): array {
        return ['text', 'email', 'textarea', 'select', 'radio', 'checkbox', 'tel', 'number', 'date'];
    }

    private function default_form_fields(): array {
        return [
            ['label' => 'Your Name', 'name' => 'your_name', 'type' => 'text', 'placeholder' => 'Enter name', 'required' => 1, 'options' => []],
            ['label' => 'Your Email', 'name' => 'your_email', 'type' => 'email', 'placeholder' => 'Enter email', 'required' => 1, 'options' => []],
            ['label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'placeholder' => 'Write message', 'required' => 1, 'options' => []],
        ];
    }

    private function default_form(): array {
        return [
            'id' => '',
            'name' => 'Contact Form',
            'to_email' => '',
            'subject_prefix' => '[Contact Form]',
            'success_message' => 'Thanks! Your message has been sent.',
            'style_theme' => 'minimal',
            'button_text' => 'Send Message',
            'fields' => $this->default_form_fields(),
        ];
    }

    private function get_forms(): array {
        $forms = get_option(self::FORMS_OPTION_KEY, []);
        return is_array($forms) ? $forms : [];
    }

    private function resolve_form_by_id(string $id): array {
        $forms = $this->get_forms();
        if ($id !== '' && isset($forms[$id])) {
            return $forms[$id];
        }
        if (!empty($forms)) {
            return (array) reset($forms);
        }
        return [
            'id' => 'default-contact',
            'name' => 'Default Contact Form',
            'to_email' => '',
            'subject_prefix' => '[Contact Form]',
            'success_message' => 'Thanks! Your message has been sent.',
            'style_theme' => 'minimal',
            'button_text' => 'Send Message',
            'fields' => $this->default_form_fields(),
        ];
    }

    private function style_themes(): array {
        return [
            'minimal' => 'Minimal Clean',
            'glass' => 'Glass Futuristic',
            'neon' => 'Neon Cyber',
        ];
    }

    private function sanitize_theme(string $theme): string {
        return array_key_exists($theme, $this->style_themes()) ? $theme : 'minimal';
    }

    private function frontend_styles(): string {
        return '.icsf-form-wrap{max-width:760px;margin:24px auto;padding:28px;border-radius:18px}.icsf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.icsf-field-full{grid-column:1/-1}.icsf-form-wrap label{display:block;font-weight:600;margin-bottom:6px}.icsf-form-wrap input,.icsf-form-wrap textarea,.icsf-form-wrap select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #d7dce4;font-size:14px;box-sizing:border-box}.icsf-form-wrap button{padding:12px 22px;border-radius:12px;border:0;cursor:pointer;font-weight:700}.icsf-theme-minimal{background:#fff;border:1px solid #eceff4;box-shadow:0 8px 28px rgba(18,38,63,.06)}.icsf-theme-minimal button{background:#2678ff;color:#fff}.icsf-theme-glass{background:rgba(20,21,37,.7);border:1px solid rgba(133,145,255,.35);backdrop-filter:blur(8px);box-shadow:0 12px 40px rgba(47,70,255,.25);color:#fff}.icsf-theme-glass input,.icsf-theme-glass textarea,.icsf-theme-glass select{background:rgba(255,255,255,.92)}.icsf-theme-glass button{background:linear-gradient(135deg,#6b7bff,#00d1ff);color:#fff}.icsf-theme-neon{background:#0b1020;border:1px solid #00d1ff;box-shadow:0 0 0 1px rgba(0,209,255,.3),0 16px 50px rgba(0,209,255,.18);color:#e9f8ff}.icsf-theme-neon input,.icsf-theme-neon textarea,.icsf-theme-neon select{background:#111936;color:#e9f8ff;border-color:#26355f}.icsf-theme-neon button{background:linear-gradient(90deg,#00d1ff,#6b7bff);color:#071021}@media(max-width:640px){.icsf-grid{grid-template-columns:1fr}}';
    }

    private function default_smtp_settings(): array {
        return [
            'to_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'enable_smtp' => 0,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_secure' => 'tls',
        ];
    }

    private function get_smtp_settings(): array {
        return wp_parse_args((array) get_option(self::SMTP_OPTION_KEY, []), $this->default_smtp_settings());
    }

    public function render_smtp_text_field(array $args): void {
        $settings = $this->get_smtp_settings();
        $key = $args['key'];
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';
        $type = $key === 'smtp_password' ? 'password' : 'text';
        printf('<input type="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" />', esc_attr($type), esc_attr(self::SMTP_OPTION_KEY), esc_attr($key), esc_attr($value));
    }

    public function render_smtp_checkbox_field(array $args): void {
        $settings = $this->get_smtp_settings();
        $key = $args['key'];
        printf('<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>', esc_attr(self::SMTP_OPTION_KEY), esc_attr($key), checked(!empty($settings[$key]), true, false), esc_html__('Enable SMTP sending', 'ikonic-contact-smtp-form'));
    }

    private function verify_admin_request(): bool {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'ikonic-contact-smtp-form'));
        }
        if (!isset($_POST['icsf_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_admin_nonce'])), self::ADMIN_NONCE_ACTION)) {
            wp_die(esc_html__('Invalid nonce.', 'ikonic-contact-smtp-form'));
        }
        return true;
    }

    private function find_first_email_value(array $fields, array $submitted): string {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'email') {
                $value = $submitted[$field['name']] ?? '';
                if ($value && is_email($value)) {
                    return $value;
                }
            }
        }
        return '';
    }

    private function store_log(array $entry): void {
        $logs = $this->get_logs();
        $logs[] = $entry;
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }
        update_option(self::LOGS_OPTION_KEY, $logs, false);
    }

    private function get_logs(): array {
        $logs = get_option(self::LOGS_OPTION_KEY, []);
        return is_array($logs) ? $logs : [];
    }

    private function log_summary(array $fields): string {
        $parts = [];
        $i = 0;
        foreach ($fields as $k => $v) {
            $parts[] = $k . ': ' . $v;
            $i++;
            if ($i >= 3) {
                break;
            }
        }
        return implode(' | ', $parts);
    }

    private function redirect_with_status(string $status, string $form_id): void {
        $redirect_url = wp_get_referer() ?: home_url('/');
        $redirect_url = add_query_arg(['icsf_status' => $status, 'icsf_form_id' => $form_id], $redirect_url);
        wp_safe_redirect($redirect_url);
        exit;
    }
}

new Ikonic_Contact_SMTP_Form();
