<?php
/**
 * Plugin Name: Ikonic Contact SMTP Form
 * Description: Lightweight contact form plugin with built-in SMTP settings and email delivery.
 * Version: 1.0.0
 * Author: Ikonic
 * Text Domain: ikonic-contact-smtp-form
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Ikonic_Contact_SMTP_Form {
    private const OPTION_KEY = 'icsf_settings';
    private const NONCE_ACTION = 'icsf_contact_form_submit';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('ikonic_contact_form', [$this, 'render_contact_form']);
        add_action('init', [$this, 'handle_form_submission']);
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
    }

    public function register_admin_menu(): void {
        add_options_page(
            __('Ikonic Contact SMTP', 'ikonic-contact-smtp-form'),
            __('Ikonic Contact SMTP', 'ikonic-contact-smtp-form'),
            'manage_options',
            'ikonic-contact-smtp-form',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting(
            'icsf_settings_group',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->default_settings(),
            ]
        );

        add_settings_section(
            'icsf_general_section',
            __('General Email Settings', 'ikonic-contact-smtp-form'),
            '__return_false',
            'ikonic-contact-smtp-form'
        );

        $fields = [
            'to_email' => __('Recipient Email', 'ikonic-contact-smtp-form'),
            'from_name' => __('From Name', 'ikonic-contact-smtp-form'),
            'from_email' => __('From Email', 'ikonic-contact-smtp-form'),
            'subject_prefix' => __('Subject Prefix', 'ikonic-contact-smtp-form'),
            'success_message' => __('Success Message', 'ikonic-contact-smtp-form'),
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                'icsf_' . $key,
                $label,
                [$this, 'render_text_field'],
                'ikonic-contact-smtp-form',
                'icsf_general_section',
                ['key' => $key]
            );
        }

        add_settings_section(
            'icsf_smtp_section',
            __('SMTP Settings', 'ikonic-contact-smtp-form'),
            function (): void {
                echo '<p>' . esc_html__('Enable SMTP to send form emails using your mail server credentials.', 'ikonic-contact-smtp-form') . '</p>';
            },
            'ikonic-contact-smtp-form'
        );

        add_settings_field(
            'icsf_enable_smtp',
            __('Enable SMTP', 'ikonic-contact-smtp-form'),
            [$this, 'render_checkbox_field'],
            'ikonic-contact-smtp-form',
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
                [$this, 'render_text_field'],
                'ikonic-contact-smtp-form',
                'icsf_smtp_section',
                ['key' => $key]
            );
        }
    }

    public function sanitize_settings(array $input): array {
        $defaults = $this->default_settings();
        $output = $defaults;

        $output['to_email'] = isset($input['to_email']) ? sanitize_email((string) $input['to_email']) : $defaults['to_email'];
        $output['from_name'] = isset($input['from_name']) ? sanitize_text_field((string) $input['from_name']) : $defaults['from_name'];
        $output['from_email'] = isset($input['from_email']) ? sanitize_email((string) $input['from_email']) : $defaults['from_email'];
        $output['subject_prefix'] = isset($input['subject_prefix']) ? sanitize_text_field((string) $input['subject_prefix']) : $defaults['subject_prefix'];
        $output['success_message'] = isset($input['success_message']) ? sanitize_text_field((string) $input['success_message']) : $defaults['success_message'];

        $output['enable_smtp'] = !empty($input['enable_smtp']) ? 1 : 0;
        $output['smtp_host'] = isset($input['smtp_host']) ? sanitize_text_field((string) $input['smtp_host']) : '';
        $output['smtp_port'] = isset($input['smtp_port']) ? absint($input['smtp_port']) : 587;
        $output['smtp_username'] = isset($input['smtp_username']) ? sanitize_text_field((string) $input['smtp_username']) : '';
        $output['smtp_password'] = isset($input['smtp_password']) ? sanitize_text_field((string) $input['smtp_password']) : '';

        $secure = isset($input['smtp_secure']) ? strtolower(sanitize_text_field((string) $input['smtp_secure'])) : 'tls';
        $output['smtp_secure'] = in_array($secure, ['tls', 'ssl', 'none'], true) ? $secure : 'tls';

        return $output;
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Ikonic Contact SMTP Form', 'ikonic-contact-smtp-form'); ?></h1>
            <p><?php esc_html_e('Use shortcode [ikonic_contact_form] in any page or post to render the form.', 'ikonic-contact-smtp-form'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields('icsf_settings_group');
                do_settings_sections('ikonic-contact-smtp-form');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_text_field(array $args): void {
        $settings = $this->get_settings();
        $key = $args['key'];
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';
        $type = $key === 'smtp_password' ? 'password' : 'text';
        printf(
            '<input type="%1$s" id="icsf_%2$s" name="%3$s[%2$s]" value="%4$s" class="regular-text" />',
            esc_attr($type),
            esc_attr($key),
            esc_attr(self::OPTION_KEY),
            esc_attr($value)
        );
    }

    public function render_checkbox_field(array $args): void {
        $settings = $this->get_settings();
        $key = $args['key'];
        $checked = !empty($settings[$key]) ? 'checked' : '';
        printf(
            '<label><input type="checkbox" id="icsf_%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
            esc_attr($key),
            esc_attr(self::OPTION_KEY),
            esc_attr($checked),
            esc_html__('Turn on SMTP mail sending', 'ikonic-contact-smtp-form')
        );
    }

    public function render_contact_form(): string {
        $notice = '';
        if (isset($_GET['icsf_status'])) {
            $status = sanitize_text_field(wp_unslash($_GET['icsf_status']));
            if ($status === 'success') {
                $notice = '<p class="icsf-success" style="color:green;">' . esc_html($this->get_settings()['success_message']) . '</p>';
            } elseif ($status === 'error') {
                $notice = '<p class="icsf-error" style="color:red;">' . esc_html__('There was an issue sending your message. Please try again.', 'ikonic-contact-smtp-form') . '</p>';
            }
        }

        ob_start();
        echo $notice;
        ?>
        <form method="post" class="icsf-contact-form">
            <p>
                <label><?php esc_html_e('Your Name', 'ikonic-contact-smtp-form'); ?></label><br />
                <input type="text" name="icsf_name" required />
            </p>
            <p>
                <label><?php esc_html_e('Your Email', 'ikonic-contact-smtp-form'); ?></label><br />
                <input type="email" name="icsf_email" required />
            </p>
            <p>
                <label><?php esc_html_e('Subject', 'ikonic-contact-smtp-form'); ?></label><br />
                <input type="text" name="icsf_subject" required />
            </p>
            <p>
                <label><?php esc_html_e('Message', 'ikonic-contact-smtp-form'); ?></label><br />
                <textarea name="icsf_message" rows="6" required></textarea>
            </p>
            <?php wp_nonce_field(self::NONCE_ACTION, 'icsf_nonce'); ?>
            <input type="hidden" name="icsf_submit" value="1" />
            <p><button type="submit"><?php esc_html_e('Send Message', 'ikonic-contact-smtp-form'); ?></button></p>
        </form>
        <?php

        return (string) ob_get_clean();
    }

    public function handle_form_submission(): void {
        if (empty($_POST['icsf_submit'])) {
            return;
        }

        if (!isset($_POST['icsf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_nonce'])), self::NONCE_ACTION)) {
            $this->redirect_with_status('error');
        }

        $name = isset($_POST['icsf_name']) ? sanitize_text_field(wp_unslash($_POST['icsf_name'])) : '';
        $email = isset($_POST['icsf_email']) ? sanitize_email(wp_unslash($_POST['icsf_email'])) : '';
        $subject = isset($_POST['icsf_subject']) ? sanitize_text_field(wp_unslash($_POST['icsf_subject'])) : '';
        $message = isset($_POST['icsf_message']) ? sanitize_textarea_field(wp_unslash($_POST['icsf_message'])) : '';

        if ($name === '' || $email === '' || $subject === '' || $message === '' || !is_email($email)) {
            $this->redirect_with_status('error');
        }

        $settings = $this->get_settings();

        $to = !empty($settings['to_email']) ? $settings['to_email'] : get_option('admin_email');
        $formatted_subject = trim($settings['subject_prefix'] . ' ' . $subject);
        $body = "Name: {$name}\n";
        $body .= "Email: {$email}\n";
        $body .= "Subject: {$subject}\n\n";
        $body .= "Message:\n{$message}\n";

        $from_name = !empty($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
        $from_email = !empty($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $name . ' <' . $email . '>',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        $sent = wp_mail($to, $formatted_subject, $body, $headers);
        $this->redirect_with_status($sent ? 'success' : 'error');
    }

    public function configure_phpmailer($phpmailer): void {
        $settings = $this->get_settings();
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

    private function get_settings(): array {
        return wp_parse_args((array) get_option(self::OPTION_KEY, []), $this->default_settings());
    }

    private function default_settings(): array {
        return [
            'to_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'subject_prefix' => '[Contact Form]',
            'success_message' => 'Thanks! Your message has been sent.',
            'enable_smtp' => 0,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_secure' => 'tls',
        ];
    }

    private function redirect_with_status(string $status): void {
        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url('/');
        $redirect_url = add_query_arg('icsf_status', $status, $redirect_url);
        wp_safe_redirect($redirect_url);
        exit;
    }
}

new Ikonic_Contact_SMTP_Form();
