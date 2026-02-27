<?php

if (!defined('ABSPATH')) {
    exit;
}

class ICSF_Plugin {
    public const SMTP_OPTION_KEY = 'icsf_smtp_settings';
    public const FORMS_OPTION_KEY = 'icsf_forms';
    public const LOGS_OPTION_KEY = 'icsf_submission_logs';
    public const ADMIN_NONCE_ACTION = 'icsf_admin_action';
    public const SUBMIT_NONCE_PREFIX = 'icsf_submit_';

    public function __construct() {
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
    }

    public function get_forms(): array {
        $forms = get_option(self::FORMS_OPTION_KEY, []);
        return is_array($forms) ? $forms : [];
    }

    public function save_forms(array $forms): void {
        update_option(self::FORMS_OPTION_KEY, $forms);
    }

    public function get_logs(): array {
        $logs = get_option(self::LOGS_OPTION_KEY, []);
        return is_array($logs) ? $logs : [];
    }

    public function add_log(array $entry): void {
        $logs = $this->get_logs();
        $logs[] = $entry;
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        update_option(self::LOGS_OPTION_KEY, $logs, false);
    }

    public function get_smtp_settings(): array {
        return wp_parse_args((array) get_option(self::SMTP_OPTION_KEY, []), $this->default_smtp_settings());
    }

    public function sanitize_smtp_settings(array $input): array {
        $defaults = $this->default_smtp_settings();
        $secure = isset($input['smtp_secure']) ? strtolower(sanitize_text_field((string) $input['smtp_secure'])) : 'tls';

        return [
            'to_email' => isset($input['to_email']) ? sanitize_email((string) $input['to_email']) : $defaults['to_email'],
            'from_name' => isset($input['from_name']) ? sanitize_text_field((string) $input['from_name']) : $defaults['from_name'],
            'from_email' => isset($input['from_email']) ? sanitize_email((string) $input['from_email']) : $defaults['from_email'],
            'enable_smtp' => !empty($input['enable_smtp']) ? 1 : 0,
            'smtp_host' => isset($input['smtp_host']) ? sanitize_text_field((string) $input['smtp_host']) : '',
            'smtp_port' => isset($input['smtp_port']) ? absint($input['smtp_port']) : 587,
            'smtp_username' => isset($input['smtp_username']) ? sanitize_text_field((string) $input['smtp_username']) : '',
            'smtp_password' => isset($input['smtp_password']) ? sanitize_text_field((string) $input['smtp_password']) : '',
            'smtp_secure' => in_array($secure, ['tls', 'ssl', 'none'], true) ? $secure : 'tls',
        ];
    }

    public function default_form(): array {
        return [
            'id' => '',
            'name' => 'Contact Form',
            'to_email' => '',
            'subject_prefix' => '[Contact Form]',
            'success_message' => 'Thanks! Your message has been sent.',
            'theme' => 'minimal',
            'button_text' => 'Send Message',
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
            'glass' => 'Glass',
            'neon' => 'Neon',
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
        $phpmailer->Host = $smtp['smtp_host'];
        $phpmailer->Port = (int) $smtp['smtp_port'];
        $phpmailer->SMTPAuth = !empty($smtp['smtp_username']);

        if (!empty($smtp['smtp_username'])) {
            $phpmailer->Username = $smtp['smtp_username'];
            $phpmailer->Password = $smtp['smtp_password'];
        }

        if (($smtp['smtp_secure'] ?? 'tls') !== 'none') {
            $phpmailer->SMTPSecure = $smtp['smtp_secure'];
        }

        if (!empty($smtp['from_email'])) {
            $phpmailer->From = $smtp['from_email'];
        }

        if (!empty($smtp['from_name'])) {
            $phpmailer->FromName = $smtp['from_name'];
        }
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
