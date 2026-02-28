<?php

if (!defined('ABSPATH')) {
    exit;
}

class ICSF_Plugin {
    public const SMTP_OPTION_KEY = 'icsf_smtp_settings';
    public const FORMS_OPTION_KEY = 'icsf_forms';
    public const LOGS_OPTION_KEY = 'icsf_submission_logs';
    public const MODULES_OPTION_KEY = 'icsf_modules';
    public const ADMIN_NONCE_ACTION = 'icsf_admin_action';
    public const SUBMIT_NONCE_PREFIX = 'icsf_submit_';

    public function __construct() {
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
    }

    public static function activate(): void {
        $instance = new self();

        if (get_option(self::SMTP_OPTION_KEY, null) === null) {
            add_option(self::SMTP_OPTION_KEY, $instance->default_smtp_settings());
        }

        if (get_option(self::MODULES_OPTION_KEY, null) === null) {
            add_option(self::MODULES_OPTION_KEY, $instance->default_modules());
        }

        if (get_option(self::FORMS_OPTION_KEY, null) === null) {
            add_option(self::FORMS_OPTION_KEY, []);
        }

        if (get_option(self::LOGS_OPTION_KEY, null) === null) {
            add_option(self::LOGS_OPTION_KEY, []);
        }
    }

    public function get_forms(): array {
        $forms = get_option(self::FORMS_OPTION_KEY, []);
        if (!is_array($forms)) {
            return [];
        }

        $normalized = [];
        foreach ($forms as $id => $form) {
            if (!is_array($form)) {
                continue;
            }

            $form['id'] = isset($form['id']) ? sanitize_key((string) $form['id']) : sanitize_key((string) $id);
            if ($form['id'] === '') {
                continue;
            }

            $normalized[$form['id']] = $this->normalize_form($form);
        }

        return $normalized;
    }

    public function save_forms(array $forms): void {
        $clean = [];
        foreach ($forms as $id => $form) {
            if (!is_array($form)) {
                continue;
            }

            $candidate = isset($form['id']) ? sanitize_key((string) $form['id']) : sanitize_key((string) $id);
            if ($candidate === '') {
                continue;
            }

            $form['id'] = $candidate;
            $clean[$candidate] = $this->normalize_form($form);
        }

        update_option(self::FORMS_OPTION_KEY, $clean, false);
    }

    public function get_logs(): array {
        $logs = get_option(self::LOGS_OPTION_KEY, []);
        return is_array($logs) ? $logs : [];
    }

    public function add_log(array $entry): void {
        $logs = $this->get_logs();
        $logs[] = $entry;
        if (count($logs) > 5000) {
            $logs = array_slice($logs, -5000);
        }

        update_option(self::LOGS_OPTION_KEY, $logs, false);
    }

    public function get_modules(): array {
        return wp_parse_args((array) get_option(self::MODULES_OPTION_KEY, []), $this->default_modules());
    }

    public function save_modules(array $modules): void {
        update_option(self::MODULES_OPTION_KEY, wp_parse_args($modules, $this->default_modules()));
    }

    public function get_smtp_settings(): array {
        return wp_parse_args((array) get_option(self::SMTP_OPTION_KEY, []), $this->default_smtp_settings());
    }

    public function sanitize_smtp_settings(array $input): array {
        $defaults = $this->default_smtp_settings();
        $current = $this->get_smtp_settings();
        $secure = isset($input['smtp_secure']) ? strtolower(sanitize_text_field((string) $input['smtp_secure'])) : 'tls';

        $password = isset($input['smtp_password']) ? sanitize_text_field((string) $input['smtp_password']) : '';
        if ($password === '') {
            $password = (string) ($current['smtp_password'] ?? '');
        }

        return [
            'to_email' => isset($input['to_email']) ? sanitize_email((string) $input['to_email']) : $defaults['to_email'],
            'from_name' => isset($input['from_name']) ? sanitize_text_field((string) $input['from_name']) : $defaults['from_name'],
            'from_email' => isset($input['from_email']) ? sanitize_email((string) $input['from_email']) : $defaults['from_email'],
            'enable_smtp' => !empty($input['enable_smtp']) ? 1 : 0,
            'smtp_host' => isset($input['smtp_host']) ? sanitize_text_field((string) $input['smtp_host']) : '',
            'smtp_port' => isset($input['smtp_port']) ? max(1, absint($input['smtp_port'])) : 587,
            'smtp_username' => isset($input['smtp_username']) ? sanitize_text_field((string) $input['smtp_username']) : '',
            'smtp_password' => $password,
            'smtp_secure' => in_array($secure, ['tls', 'ssl', 'none'], true) ? $secure : 'tls',
        ];
    }

    public function default_form(): array {
        return [
            'id' => '',
            'name' => 'Contact Form',
            'status' => 'active',
            'to_email' => '',
            'subject_prefix' => '[Contact Form]',
            'success_message' => 'Thanks! Your message has been sent.',
            'error_message' => 'Sorry, we could not send your message. Please try again.',
            'theme' => 'neo-glass',
            'button_text' => 'Transmit Message',
            'layout' => 'grid',
            'enable_honeypot' => 1,
            'max_submissions_per_hour' => 30,
            'submit_action' => 'message',
            'redirect_url' => '',
            'store_entries' => 1,
            'admin_notify_enabled' => 1,
            'admin_subject_template' => '[{form_name}] New Submission',
            'admin_body_template' => "A new submission was received from {form_name}.\n\n{fields}",
            'autoresponder_enabled' => 0,
            'autoresponder_email_field' => 'your_email',
            'autoresponder_subject' => 'We received your message',
            'autoresponder_body' => "Hi,\n\nThanks for contacting us. Our team will get back to you soon.\n\n- Team",
            'webhook_enabled' => 0,
            'webhook_url' => '',
            'fields' => [
                ['label' => 'Your Name', 'name' => 'your_name', 'type' => 'text', 'required' => 1, 'placeholder' => 'Enter your name', 'options' => []],
                ['label' => 'Your Email', 'name' => 'your_email', 'type' => 'email', 'required' => 1, 'placeholder' => 'Enter your email', 'options' => []],
                ['label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'required' => 1, 'placeholder' => 'Write your message', 'options' => []],
            ],
        ];
    }

    public function theme_options(): array {
        return [
            'minimal' => 'Minimal',
            'neo-glass' => 'Neo Glass',
            'cyber-neon' => 'Cyber Neon',
            'aurora' => 'Aurora',
        ];
    }

    public function verify_admin_request(): bool {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'ikonic-contact-smtp-form'));
        }

        if (!isset($_POST['icsf_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_admin_nonce'])), self::ADMIN_NONCE_ACTION)) {
            wp_die(esc_html__('Invalid nonce.', 'ikonic-contact-smtp-form'));
        }

        return true;
    }

    public function configure_phpmailer($phpmailer): void {
        $smtp = $this->get_smtp_settings();
        if (empty($smtp['enable_smtp']) || empty($smtp['smtp_host']) || empty($smtp['smtp_port'])) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = (string) $smtp['smtp_host'];
        $phpmailer->Port = (int) $smtp['smtp_port'];
        $phpmailer->SMTPAuth = !empty($smtp['smtp_username']);
        $phpmailer->SMTPAutoTLS = true;

        if (!empty($smtp['smtp_username'])) {
            $phpmailer->Username = (string) $smtp['smtp_username'];
            $phpmailer->Password = (string) $smtp['smtp_password'];
        }

        if (($smtp['smtp_secure'] ?? 'tls') !== 'none') {
            $phpmailer->SMTPSecure = (string) $smtp['smtp_secure'];
        }

        if (!empty($smtp['from_email'])) {
            $phpmailer->From = (string) $smtp['from_email'];
        }

        if (!empty($smtp['from_name'])) {
            $phpmailer->FromName = (string) $smtp['from_name'];
        }
    }

    private function normalize_form(array $form): array {
        $defaults = $this->default_form();
        $form = wp_parse_args($form, $defaults);

        $status = sanitize_key((string) $form['status']);
        $layout = sanitize_key((string) $form['layout']);
        $submit_action = sanitize_key((string) $form['submit_action']);
        $theme = sanitize_key((string) $form['theme']);

        $form['id'] = sanitize_key((string) $form['id']);
        $form['name'] = sanitize_text_field((string) $form['name']);
        $form['status'] = in_array($status, ['active', 'inactive'], true) ? $status : 'active';
        $form['to_email'] = sanitize_email((string) $form['to_email']);
        $form['subject_prefix'] = sanitize_text_field((string) $form['subject_prefix']);
        $form['success_message'] = sanitize_text_field((string) $form['success_message']);
        $form['error_message'] = sanitize_text_field((string) $form['error_message']);
        $form['theme'] = array_key_exists($theme, $this->theme_options()) ? $theme : $defaults['theme'];
        $form['button_text'] = sanitize_text_field((string) $form['button_text']);
        $form['layout'] = in_array($layout, ['grid', 'single'], true) ? $layout : 'grid';
        $form['enable_honeypot'] = !empty($form['enable_honeypot']) ? 1 : 0;
        $form['max_submissions_per_hour'] = max(1, absint($form['max_submissions_per_hour']));
        $form['submit_action'] = in_array($submit_action, ['message', 'redirect'], true) ? $submit_action : 'message';
        $form['redirect_url'] = esc_url_raw((string) $form['redirect_url']);
        $form['store_entries'] = !empty($form['store_entries']) ? 1 : 0;
        $form['admin_notify_enabled'] = !empty($form['admin_notify_enabled']) ? 1 : 0;
        $form['admin_subject_template'] = sanitize_text_field((string) $form['admin_subject_template']);
        $form['admin_body_template'] = sanitize_textarea_field((string) $form['admin_body_template']);
        $form['autoresponder_enabled'] = !empty($form['autoresponder_enabled']) ? 1 : 0;
        $form['autoresponder_email_field'] = sanitize_key((string) $form['autoresponder_email_field']);
        $form['autoresponder_subject'] = sanitize_text_field((string) $form['autoresponder_subject']);
        $form['autoresponder_body'] = sanitize_textarea_field((string) $form['autoresponder_body']);
        $form['webhook_enabled'] = !empty($form['webhook_enabled']) ? 1 : 0;
        $form['webhook_url'] = esc_url_raw((string) $form['webhook_url']);

        $clean_fields = [];
        foreach ((array) $form['fields'] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = sanitize_text_field((string) ($field['label'] ?? ''));
            $name = sanitize_key((string) ($field['name'] ?? ''));
            if ($label === '' || $name === '') {
                continue;
            }

            $type = sanitize_key((string) ($field['type'] ?? 'text'));
            if (!in_array($type, ['text', 'email', 'textarea', 'select', 'radio', 'checkbox', 'tel', 'number', 'date', 'url'], true)) {
                $type = 'text';
            }

            $options = [];
            foreach ((array) ($field['options'] ?? []) as $option) {
                $opt = sanitize_text_field((string) $option);
                if ($opt !== '') {
                    $options[] = $opt;
                }
            }

            $clean_fields[] = [
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'required' => !empty($field['required']) ? 1 : 0,
                'placeholder' => sanitize_text_field((string) ($field['placeholder'] ?? '')),
                'options' => $options,
            ];
        }

        $form['fields'] = !empty($clean_fields) ? $clean_fields : $defaults['fields'];

        return $form;
    }

    private function default_modules(): array {
        return [
            'frontend_futuristic_ui' => 1,
            'frontend_progress_meter' => 1,
            'backend_ui_studio' => 1,
            'analytics_advanced' => 1,
            'honeypot' => 1,
            'entries_manager' => 1,
            'autoresponder' => 1,
            'webhook_gateway' => 0,
        ];
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
}
