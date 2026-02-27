<?php

if (!defined('ABSPATH')) {
    exit;
}

class ICSF_Forms {
    private ICSF_Plugin $plugin;
    private ICSF_Analytics $analytics;

    public function __construct(ICSF_Plugin $plugin, ICSF_Analytics $analytics) {
        $this->plugin = $plugin;
        $this->analytics = $analytics;

        add_shortcode('ikonic_contact_form', [$this, 'render_shortcode']);
        add_action('init', [$this, 'handle_submission']);
    }

    public function get_form(string $id): array {
        $forms = $this->plugin->get_forms();

        if ($id !== '' && isset($forms[$id])) {
            return wp_parse_args($forms[$id], $this->plugin->default_form());
        }

        if (!empty($forms)) {
            return wp_parse_args((array) reset($forms), $this->plugin->default_form());
        }

        return wp_parse_args([
            'id' => 'default-contact',
            'name' => 'Default Contact Form',
        ], $this->plugin->default_form());
    }

    public function sanitize_fields(array $raw_fields): array {
        $allowed_types = ['text', 'email', 'textarea', 'select', 'radio', 'checkbox', 'tel', 'number', 'date'];
        $clean = [];

        foreach ($raw_fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = isset($field['label']) ? sanitize_text_field((string) $field['label']) : '';
            $name = isset($field['name']) ? sanitize_key((string) $field['name']) : '';
            $type = isset($field['type']) ? strtolower(sanitize_text_field((string) $field['type'])) : 'text';
            $placeholder = isset($field['placeholder']) ? sanitize_text_field((string) $field['placeholder']) : '';
            $required = !empty($field['required']) ? 1 : 0;
            $options_raw = isset($field['options']) ? sanitize_text_field((string) $field['options']) : '';

            if ($label === '' || $name === '') {
                continue;
            }

            if (!in_array($type, $allowed_types, true)) {
                $type = 'text';
            }

            $options = [];
            if (in_array($type, ['select', 'radio', 'checkbox'], true) && $options_raw !== '') {
                foreach (array_map('trim', explode(',', $options_raw)) as $opt) {
                    if ($opt !== '') {
                        $options[] = sanitize_text_field($opt);
                    }
                }
            }

            $clean[] = [
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'required' => $required,
                'placeholder' => $placeholder,
                'options' => $options,
            ];
        }

        return $clean;
    }

    public function render_shortcode(array $atts): string {
        $atts = shortcode_atts(['id' => ''], $atts, 'ikonic_contact_form');
        $form = $this->get_form((string) $atts['id']);

        $notice = '';
        if (isset($_GET['icsf_status'], $_GET['icsf_form_id']) && sanitize_key(wp_unslash($_GET['icsf_form_id'])) === $form['id']) {
            $status = sanitize_text_field(wp_unslash($_GET['icsf_status']));
            $notice = $status === 'success'
                ? '<p style="color:green;">' . esc_html($form['success_message']) . '</p>'
                : '<p style="color:red;">' . esc_html__('There was an issue sending your message.', 'ikonic-contact-smtp-form') . '</p>';
        }

        ob_start();
        echo $notice;
        echo '<div class="icsf-theme-' . esc_attr($form['theme']) . '" style="max-width:760px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:14px;">';
        echo '<form method="post">';
        echo '<input type="hidden" name="icsf_submit" value="1" />';
        echo '<input type="hidden" name="icsf_form_id" value="' . esc_attr($form['id']) . '" />';
        wp_nonce_field(ICSF_Plugin::SUBMIT_NONCE_PREFIX . $form['id'], 'icsf_nonce');

        foreach ($form['fields'] as $field) {
            echo '<p><label>' . esc_html($field['label']) . '</label><br />';
            $this->render_field($field);
            echo '</p>';
        }

        echo '<p><button type="submit">' . esc_html($form['button_text']) . '</button></p>';
        echo '</form></div>';

        return (string) ob_get_clean();
    }

    public function handle_submission(): void {
        if (empty($_POST['icsf_submit'])) {
            return;
        }

        $form_id = isset($_POST['icsf_form_id']) ? sanitize_key(wp_unslash($_POST['icsf_form_id'])) : '';
        $form = $this->get_form($form_id);

        if (!isset($_POST['icsf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_nonce'])), ICSF_Plugin::SUBMIT_NONCE_PREFIX . $form['id'])) {
            $this->analytics->log_submission($form['id'], [], 'error');
            $this->redirect('error', $form['id']);
        }

        $data = [];
        foreach ($form['fields'] as $field) {
            $name = $field['name'];
            $raw = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
            $value = is_array($raw) ? implode(', ', array_map('sanitize_text_field', $raw)) : sanitize_textarea_field((string) $raw);

            if ($field['type'] === 'email') {
                $value = sanitize_email((string) $raw);
                if ($value !== '' && !is_email($value)) {
                    $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                    $this->redirect('error', $form['id']);
                }
            }

            if (!empty($field['required']) && trim((string) $value) === '') {
                $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                $this->redirect('error', $form['id']);
            }

            $data[$name] = $value;
        }

        $smtp = $this->plugin->get_smtp_settings();
        $to = !empty($form['to_email']) ? $form['to_email'] : $smtp['to_email'];
        if (!is_email($to)) {
            $to = get_option('admin_email');
        }

        $subject = trim($form['subject_prefix'] . ' ' . $form['name']);
        $body = "Form: {$form['name']}\n";
        foreach ($form['fields'] as $field) {
            $body .= $field['label'] . ': ' . ($data[$field['name']] ?? '') . "\n";
        }

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . ($smtp['from_name'] ?: get_bloginfo('name')) . ' <' . ($smtp['from_email'] ?: get_option('admin_email')) . '>',
        ];

        $sent = wp_mail($to, $subject, $body, $headers);
        $this->analytics->log_submission($form['id'], $data, $sent ? 'success' : 'error');
        $this->redirect($sent ? 'success' : 'error', $form['id']);
    }

    private function render_field(array $field): void {
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
                    echo '<label><input type="radio" name="' . $name . '" value="' . esc_attr($opt) . '" ' . ($idx === 0 ? $required : '') . ' /> ' . esc_html($opt) . '</label> ';
                }
                break;
            case 'checkbox':
                foreach ($field['options'] as $opt) {
                    echo '<label><input type="checkbox" name="' . $name . '[]" value="' . esc_attr($opt) . '" /> ' . esc_html($opt) . '</label> ';
                }
                break;
            case 'email':
            case 'tel':
            case 'number':
            case 'date':
                echo '<input type="' . esc_attr($field['type']) . '" name="' . $name . '" placeholder="' . $placeholder . '" ' . $required . ' />';
                break;
            default:
                echo '<input type="text" name="' . $name . '" placeholder="' . $placeholder . '" ' . $required . ' />';
        }
    }

    private function redirect(string $status, string $form_id): void {
        $url = wp_get_referer() ?: home_url('/');
        $url = add_query_arg(['icsf_status' => $status, 'icsf_form_id' => $form_id], $url);
        wp_safe_redirect($url);
        exit;
    }
}
