<?php
/**
 * Plugin Name: Ikonic Contact SMTP Form
 * Description: Contact form builder plugin with built-in SMTP settings and email delivery.
 * Version: 2.0.0
 * Author: Ikonic
 * Text Domain: ikonic-contact-smtp-form
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Ikonic_Contact_SMTP_Form {
    private const SMTP_OPTION_KEY = 'icsf_smtp_settings';
    private const FORMS_OPTION_KEY = 'icsf_forms';
    private const SUBMIT_NONCE_ACTION = 'icsf_contact_form_submit_';
    private const ADMIN_NONCE_ACTION = 'icsf_admin_form_action';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_smtp_settings']);
        add_shortcode('ikonic_contact_form', [$this, 'render_contact_form_shortcode']);
        add_action('init', [$this, 'handle_form_submission']);
        add_action('admin_post_icsf_save_form', [$this, 'handle_save_form']);
        add_action('admin_post_icsf_delete_form', [$this, 'handle_delete_form']);
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

        add_submenu_page(
            'ikonic-contact-form-builder',
            __('Forms', 'ikonic-contact-smtp-form'),
            __('Forms', 'ikonic-contact-smtp-form'),
            'manage_options',
            'ikonic-contact-form-builder',
            [$this, 'render_forms_page']
        );

        add_submenu_page(
            'ikonic-contact-form-builder',
            __('SMTP Settings', 'ikonic-contact-smtp-form'),
            __('SMTP Settings', 'ikonic-contact-smtp-form'),
            'manage_options',
            'ikonic-contact-smtp-settings',
            [$this, 'render_smtp_settings_page']
        );
    }

    public function register_smtp_settings(): void {
        register_setting(
            'icsf_smtp_settings_group',
            self::SMTP_OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_smtp_settings'],
                'default' => $this->default_smtp_settings(),
            ]
        );

        add_settings_section(
            'icsf_smtp_general_section',
            __('General Email Settings', 'ikonic-contact-smtp-form'),
            '__return_false',
            'ikonic-contact-smtp-settings'
        );

        $general_fields = [
            'to_email' => __('Default Recipient Email', 'ikonic-contact-smtp-form'),
            'from_name' => __('From Name', 'ikonic-contact-smtp-form'),
            'from_email' => __('From Email', 'ikonic-contact-smtp-form'),
        ];

        foreach ($general_fields as $key => $label) {
            add_settings_field(
                'icsf_' . $key,
                $label,
                [$this, 'render_smtp_text_field'],
                'ikonic-contact-smtp-settings',
                'icsf_smtp_general_section',
                ['key' => $key]
            );
        }

        add_settings_section(
            'icsf_smtp_section',
            __('SMTP Configuration', 'ikonic-contact-smtp-form'),
            function (): void {
                echo '<p>' . esc_html__('Enable SMTP to send emails through your own SMTP server.', 'ikonic-contact-smtp-form') . '</p>';
            },
            'ikonic-contact-smtp-settings'
        );

        add_settings_field(
            'icsf_enable_smtp',
            __('Enable SMTP', 'ikonic-contact-smtp-form'),
            [$this, 'render_smtp_checkbox_field'],
            'ikonic-contact-smtp-settings',
            'icsf_smtp_section',
            ['key' => 'enable_smtp']
        );

        $smtp_fields = [
            'smtp_host' => __('SMTP Host', 'ikonic-contact-smtp-form'),
            'smtp_port' => __('SMTP Port', 'ikonic-contact-smtp-form'),
            'smtp_username' => __('SMTP Username', 'ikonic-contact-smtp-form'),
            'smtp_password' => __('SMTP Password', 'ikonic-contact-smtp-form'),
            'smtp_secure' => __('SMTP Encryption (tls/ssl/none)', 'ikonic-contact-smtp-form'),
        ];

        foreach ($smtp_fields as $key => $label) {
            add_settings_field(
                'icsf_' . $key,
                $label,
                [$this, 'render_smtp_text_field'],
                'ikonic-contact-smtp-settings',
                'icsf_smtp_section',
                ['key' => $key]
            );
        }
    }

    public function sanitize_smtp_settings(array $input): array {
        $defaults = $this->default_smtp_settings();
        $output = $defaults;

        $output['to_email'] = isset($input['to_email']) ? sanitize_email((string) $input['to_email']) : $defaults['to_email'];
        $output['from_name'] = isset($input['from_name']) ? sanitize_text_field((string) $input['from_name']) : $defaults['from_name'];
        $output['from_email'] = isset($input['from_email']) ? sanitize_email((string) $input['from_email']) : $defaults['from_email'];

        $output['enable_smtp'] = !empty($input['enable_smtp']) ? 1 : 0;
        $output['smtp_host'] = isset($input['smtp_host']) ? sanitize_text_field((string) $input['smtp_host']) : '';
        $output['smtp_port'] = isset($input['smtp_port']) ? absint($input['smtp_port']) : 587;
        $output['smtp_username'] = isset($input['smtp_username']) ? sanitize_text_field((string) $input['smtp_username']) : '';
        $output['smtp_password'] = isset($input['smtp_password']) ? sanitize_text_field((string) $input['smtp_password']) : '';

        $secure = isset($input['smtp_secure']) ? strtolower(sanitize_text_field((string) $input['smtp_secure'])) : 'tls';
        $output['smtp_secure'] = in_array($secure, ['tls', 'ssl', 'none'], true) ? $secure : 'tls';

        return $output;
    }

    public function render_smtp_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SMTP Settings', 'ikonic-contact-smtp-form'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('icsf_smtp_settings_group');
                do_settings_sections('ikonic-contact-smtp-settings');
                submit_button();
                ?>
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

        if ($active_form_id === '') {
            $active_form['id'] = '';
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Ikonic Form Builder', 'ikonic-contact-smtp-form'); ?></h1>
            <p><?php esc_html_e('Create multiple forms and render them using shortcode: [ikonic_contact_form id="your-form-id"].', 'ikonic-contact-smtp-form'); ?></p>

            <h2><?php esc_html_e('Saved Forms', 'ikonic-contact-smtp-form'); ?></h2>
            <table class="widefat striped" style="max-width:1000px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Form Name', 'ikonic-contact-smtp-form'); ?></th>
                        <th><?php esc_html_e('Form ID', 'ikonic-contact-smtp-form'); ?></th>
                        <th><?php esc_html_e('Shortcode', 'ikonic-contact-smtp-form'); ?></th>
                        <th><?php esc_html_e('Actions', 'ikonic-contact-smtp-form'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($forms)) : ?>
                        <tr><td colspan="4"><?php esc_html_e('No forms created yet.', 'ikonic-contact-smtp-form'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($forms as $form) : ?>
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
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <hr />
            <h2><?php esc_html_e('Form Builder', 'ikonic-contact-smtp-form'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="icsf_save_form" />
                <?php wp_nonce_field(self::ADMIN_NONCE_ACTION, 'icsf_admin_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Form Name', 'ikonic-contact-smtp-form'); ?></th>
                        <td><input name="form_name" type="text" class="regular-text" value="<?php echo esc_attr($active_form['name']); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Form ID (slug)', 'ikonic-contact-smtp-form'); ?></th>
                        <td>
                            <input name="form_id" type="text" class="regular-text" value="<?php echo esc_attr($active_form['id']); ?>" placeholder="contact-us" />
                            <p class="description"><?php esc_html_e('Leave empty for new forms to auto-generate from name.', 'ikonic-contact-smtp-form'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Recipient Email', 'ikonic-contact-smtp-form'); ?></th>
                        <td><input name="to_email" type="email" class="regular-text" value="<?php echo esc_attr($active_form['to_email']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Subject Prefix', 'ikonic-contact-smtp-form'); ?></th>
                        <td><input name="subject_prefix" type="text" class="regular-text" value="<?php echo esc_attr($active_form['subject_prefix']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Success Message', 'ikonic-contact-smtp-form'); ?></th>
                        <td><input name="success_message" type="text" class="regular-text" value="<?php echo esc_attr($active_form['success_message']); ?>" /></td>
                    </tr>
                </table>

                <h3><?php esc_html_e('Fields', 'ikonic-contact-smtp-form'); ?></h3>
                <p><?php esc_html_e('Add rows for fields. Supported types: text, email, textarea, select, radio, checkbox.', 'ikonic-contact-smtp-form'); ?></p>
                <table class="widefat striped" style="max-width:1200px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Label', 'ikonic-contact-smtp-form'); ?></th>
                            <th><?php esc_html_e('Name', 'ikonic-contact-smtp-form'); ?></th>
                            <th><?php esc_html_e('Type', 'ikonic-contact-smtp-form'); ?></th>
                            <th><?php esc_html_e('Placeholder', 'ikonic-contact-smtp-form'); ?></th>
                            <th><?php esc_html_e('Options (comma separated)', 'ikonic-contact-smtp-form'); ?></th>
                            <th><?php esc_html_e('Required (1/0)', 'ikonic-contact-smtp-form'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = !empty($active_form['fields']) ? $active_form['fields'] : $this->default_form_fields();
                        foreach ($rows as $i => $field) :
                            ?>
                            <tr>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][label]" value="<?php echo esc_attr($field['label']); ?>" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][name]" value="<?php echo esc_attr($field['name']); ?>" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][type]" value="<?php echo esc_attr($field['type']); ?>" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][placeholder]" value="<?php echo esc_attr($field['placeholder']); ?>" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][options]" value="<?php echo esc_attr(implode(',', $field['options'])); ?>" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $i); ?>][required]" value="<?php echo esc_attr($field['required'] ? '1' : '0'); ?>" /></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php for ($extra = 0; $extra < 5; $extra++) :
                            $index = count($rows) + $extra;
                            ?>
                            <tr>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $index); ?>][label]" value="" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $index); ?>][name]" value="" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $index); ?>][type]" value="text" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $index); ?>][placeholder]" value="" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $index); ?>][options]" value="" /></td>
                                <td><input type="text" name="fields[<?php echo esc_attr((string) $index); ?>][required]" value="0" /></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <?php submit_button(__('Save Form', 'ikonic-contact-smtp-form')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_save_form(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'ikonic-contact-smtp-form'));
        }

        if (!isset($_POST['icsf_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_admin_nonce'])), self::ADMIN_NONCE_ACTION)) {
            wp_die(esc_html__('Invalid nonce.', 'ikonic-contact-smtp-form'));
        }

        $form_name = isset($_POST['form_name']) ? sanitize_text_field(wp_unslash($_POST['form_name'])) : '';
        $form_id_input = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';

        if ($form_name === '') {
            wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder'));
            exit;
        }

        $form_id = $form_id_input !== '' ? $form_id_input : sanitize_title($form_name);
        if ($form_id === '') {
            $form_id = 'form-' . wp_generate_password(6, false, false);
        }

        $to_email = isset($_POST['to_email']) ? sanitize_email(wp_unslash($_POST['to_email'])) : '';
        $subject_prefix = isset($_POST['subject_prefix']) ? sanitize_text_field(wp_unslash($_POST['subject_prefix'])) : '[Contact Form]';
        $success_message = isset($_POST['success_message']) ? sanitize_text_field(wp_unslash($_POST['success_message'])) : 'Thanks! Your message has been sent.';

        $raw_fields = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        $fields = $this->sanitize_form_fields($raw_fields);
        if (empty($fields)) {
            $fields = $this->default_form_fields();
        }

        $forms = $this->get_forms();
        $forms[$form_id] = [
            'id' => $form_id,
            'name' => $form_name,
            'to_email' => $to_email,
            'subject_prefix' => $subject_prefix,
            'success_message' => $success_message,
            'fields' => $fields,
        ];

        update_option(self::FORMS_OPTION_KEY, $forms);
        wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder&form_id=' . rawurlencode($form_id)));
        exit;
    }

    public function handle_delete_form(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'ikonic-contact-smtp-form'));
        }

        if (!isset($_POST['icsf_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_admin_nonce'])), self::ADMIN_NONCE_ACTION)) {
            wp_die(esc_html__('Invalid nonce.', 'ikonic-contact-smtp-form'));
        }

        $form_id = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';
        if ($form_id === '') {
            wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder'));
            exit;
        }

        $forms = $this->get_forms();
        unset($forms[$form_id]);
        update_option(self::FORMS_OPTION_KEY, $forms);

        wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder'));
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
            $form_id = sanitize_key(wp_unslash($_GET['icsf_form_id']));
            if ($form_id === $form['id']) {
                if ($status === 'success') {
                    $notice = '<p class="icsf-success" style="color:green;">' . esc_html($form['success_message']) . '</p>';
                } elseif ($status === 'error') {
                    $notice = '<p class="icsf-error" style="color:red;">' . esc_html__('There was an issue sending your message. Please try again.', 'ikonic-contact-smtp-form') . '</p>';
                }
            }
        }

        ob_start();
        echo $notice;
        ?>
        <form method="post" class="icsf-contact-form">
            <input type="hidden" name="icsf_submit" value="1" />
            <input type="hidden" name="icsf_form_id" value="<?php echo esc_attr($form['id']); ?>" />
            <?php wp_nonce_field(self::SUBMIT_NONCE_ACTION . $form['id'], 'icsf_nonce'); ?>

            <?php foreach ($form['fields'] as $field) : ?>
                <p>
                    <label><?php echo esc_html($field['label']); ?></label><br />
                    <?php $this->render_front_field($field); ?>
                </p>
            <?php endforeach; ?>

            <p><button type="submit"><?php esc_html_e('Send Message', 'ikonic-contact-smtp-form'); ?></button></p>
        </form>
        <?php

        return (string) ob_get_clean();
    }

    public function handle_form_submission(): void {
        if (empty($_POST['icsf_submit'])) {
            return;
        }

        $form_id = isset($_POST['icsf_form_id']) ? sanitize_key(wp_unslash($_POST['icsf_form_id'])) : '';
        $form = $this->resolve_form_by_id($form_id);

        if (empty($form)) {
            $this->redirect_with_status('error', $form_id);
        }

        if (!isset($_POST['icsf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_nonce'])), self::SUBMIT_NONCE_ACTION . $form['id'])) {
            $this->redirect_with_status('error', $form_id);
        }

        $submitted = [];
        foreach ($form['fields'] as $field) {
            $name = $field['name'];
            $value = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';

            if ($field['type'] === 'checkbox' && is_array($value)) {
                $clean = array_map('sanitize_text_field', $value);
                $submitted[$name] = implode(', ', $clean);
            } else {
                $clean = $field['type'] === 'email' ? sanitize_email((string) $value) : sanitize_textarea_field((string) $value);
                $submitted[$name] = $clean;
            }

            if (!empty($field['required']) && trim((string) $submitted[$name]) === '') {
                $this->redirect_with_status('error', $form_id);
            }

            if ($field['type'] === 'email' && $submitted[$name] !== '' && !is_email($submitted[$name])) {
                $this->redirect_with_status('error', $form_id);
            }
        }

        $smtp_settings = $this->get_smtp_settings();
        $to = !empty($form['to_email']) ? $form['to_email'] : $smtp_settings['to_email'];
        if (!is_email($to)) {
            $to = get_option('admin_email');
        }

        $subject = trim($form['subject_prefix'] . ' ' . $form['name']);
        $body = "Form: {$form['name']}\n";
        $body .= "Form ID: {$form['id']}\n\n";
        foreach ($form['fields'] as $field) {
            $body .= $field['label'] . ': ' . ($submitted[$field['name']] ?? '') . "\n";
        }

        $reply_email = $this->find_first_email_value($form['fields'], $submitted);
        $from_name = !empty($smtp_settings['from_name']) ? $smtp_settings['from_name'] : get_bloginfo('name');
        $from_email = !empty($smtp_settings['from_email']) ? $smtp_settings['from_email'] : get_option('admin_email');

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        if ($reply_email !== '') {
            $headers[] = 'Reply-To: <' . $reply_email . '>';
        }

        $sent = wp_mail($to, $subject, $body, $headers);
        $this->redirect_with_status($sent ? 'success' : 'error', $form['id']);
    }

    public function configure_phpmailer($phpmailer): void {
        $settings = $this->get_smtp_settings();

        if (empty($settings['enable_smtp'])) {
            return;
        }

        if (empty($settings['smtp_host']) || empty($settings['smtp_port'])) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $settings['smtp_host'];
        $phpmailer->Port = (int) $settings['smtp_port'];
        $phpmailer->SMTPAuth = !empty($settings['smtp_username']);

        if (!empty($settings['smtp_username'])) {
            $phpmailer->Username = $settings['smtp_username'];
            $phpmailer->Password = $settings['smtp_password'];
        }

        if (($settings['smtp_secure'] ?? 'tls') !== 'none') {
            $phpmailer->SMTPSecure = $settings['smtp_secure'];
        }

        if (!empty($settings['from_email'])) {
            $phpmailer->From = $settings['from_email'];
        }

        if (!empty($settings['from_name'])) {
            $phpmailer->FromName = $settings['from_name'];
        }
    }

    private function render_front_field(array $field): void {
        $required = !empty($field['required']);
        $required_attr = $required ? 'required' : '';
        $name_attr = esc_attr($field['name']);
        $placeholder = esc_attr($field['placeholder']);

        switch ($field['type']) {
            case 'textarea':
                echo '<textarea name="' . $name_attr . '" rows="5" placeholder="' . $placeholder . '" ' . $required_attr . '></textarea>';
                break;
            case 'select':
                echo '<select name="' . $name_attr . '" ' . $required_attr . '>';
                echo '<option value="">' . esc_html__('Select', 'ikonic-contact-smtp-form') . '</option>';
                foreach ($field['options'] as $opt) {
                    echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                }
                echo '</select>';
                break;
            case 'radio':
                foreach ($field['options'] as $idx => $opt) {
                    echo '<label style="margin-right:12px;"><input type="radio" name="' . $name_attr . '" value="' . esc_attr($opt) . '" ' . ($required && $idx === 0 ? 'required' : '') . ' /> ' . esc_html($opt) . '</label>';
                }
                break;
            case 'checkbox':
                foreach ($field['options'] as $opt) {
                    echo '<label style="margin-right:12px;"><input type="checkbox" name="' . $name_attr . '[]" value="' . esc_attr($opt) . '" /> ' . esc_html($opt) . '</label>';
                }
                break;
            case 'email':
                echo '<input type="email" name="' . $name_attr . '" placeholder="' . $placeholder . '" ' . $required_attr . ' />';
                break;
            default:
                echo '<input type="text" name="' . $name_attr . '" placeholder="' . $placeholder . '" ' . $required_attr . ' />';
                break;
        }
    }

    private function sanitize_form_fields(array $raw_fields): array {
        $allowed_types = ['text', 'email', 'textarea', 'select', 'radio', 'checkbox'];
        $clean_fields = [];

        foreach ($raw_fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = isset($field['label']) ? sanitize_text_field((string) $field['label']) : '';
            $name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            $type = isset($field['type']) ? strtolower(sanitize_text_field((string) $field['type'])) : 'text';
            $placeholder = isset($field['placeholder']) ? sanitize_text_field((string) $field['placeholder']) : '';
            $options_raw = isset($field['options']) ? sanitize_text_field((string) $field['options']) : '';
            $required = isset($field['required']) && (string) $field['required'] === '1';

            if ($label === '' || $name === '') {
                continue;
            }

            if (!in_array($type, $allowed_types, true)) {
                $type = 'text';
            }

            $options = [];
            if (in_array($type, ['select', 'radio', 'checkbox'], true) && $options_raw !== '') {
                $parts = array_map('trim', explode(',', $options_raw));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $options[] = sanitize_text_field($part);
                    }
                }
            }

            $clean_fields[] = [
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'placeholder' => $placeholder,
                'required' => $required ? 1 : 0,
                'options' => $options,
            ];
        }

        return $clean_fields;
    }

    private function get_forms(): array {
        $forms = get_option(self::FORMS_OPTION_KEY, []);
        if (!is_array($forms)) {
            return [];
        }
        return $forms;
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
            'fields' => $this->default_form_fields(),
        ];
    }

    private function default_form(): array {
        return [
            'id' => '',
            'name' => 'Contact Form',
            'to_email' => '',
            'subject_prefix' => '[Contact Form]',
            'success_message' => 'Thanks! Your message has been sent.',
            'fields' => $this->default_form_fields(),
        ];
    }

    private function default_form_fields(): array {
        return [
            [
                'label' => 'Your Name',
                'name' => 'your_name',
                'type' => 'text',
                'placeholder' => 'Enter your name',
                'required' => 1,
                'options' => [],
            ],
            [
                'label' => 'Your Email',
                'name' => 'your_email',
                'type' => 'email',
                'placeholder' => 'Enter your email',
                'required' => 1,
                'options' => [],
            ],
            [
                'label' => 'Subject',
                'name' => 'subject',
                'type' => 'text',
                'placeholder' => 'Enter subject',
                'required' => 1,
                'options' => [],
            ],
            [
                'label' => 'Message',
                'name' => 'message',
                'type' => 'textarea',
                'placeholder' => 'Write your message',
                'required' => 1,
                'options' => [],
            ],
        ];
    }

    private function get_smtp_settings(): array {
        return wp_parse_args((array) get_option(self::SMTP_OPTION_KEY, []), $this->default_smtp_settings());
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

    private function render_smtp_text_field(array $args): void {
        $settings = $this->get_smtp_settings();
        $key = $args['key'];
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';
        $type = $key === 'smtp_password' ? 'password' : 'text';

        printf(
            '<input type="%1$s" id="icsf_%2$s" name="%3$s[%2$s]" value="%4$s" class="regular-text" />',
            esc_attr($type),
            esc_attr($key),
            esc_attr(self::SMTP_OPTION_KEY),
            esc_attr($value)
        );
    }

    private function render_smtp_checkbox_field(array $args): void {
        $settings = $this->get_smtp_settings();
        $key = $args['key'];
        $checked = !empty($settings[$key]) ? 'checked' : '';

        printf(
            '<label><input type="checkbox" id="icsf_%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
            esc_attr($key),
            esc_attr(self::SMTP_OPTION_KEY),
            esc_attr($checked),
            esc_html__('Turn on SMTP mail sending', 'ikonic-contact-smtp-form')
        );
    }

    private function find_first_email_value(array $fields, array $submitted): string {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'email') {
                $value = $submitted[$field['name']] ?? '';
                if ($value !== '' && is_email($value)) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function redirect_with_status(string $status, string $form_id): void {
        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url('/');
        $redirect_url = add_query_arg(
            [
                'icsf_status' => $status,
                'icsf_form_id' => $form_id,
            ],
            $redirect_url
        );
        wp_safe_redirect($redirect_url);
        exit;
    }
}

new Ikonic_Contact_SMTP_Form();
