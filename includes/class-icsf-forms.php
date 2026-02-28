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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void {
        $modules = $this->plugin->get_modules();
        if (empty($modules['frontend_futuristic_ui'])) {
            return;
        }

        $css = '.icsf-wrap{max-width:900px;margin:28px auto;padding:28px;border-radius:20px}.icsf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.icsf-full{grid-column:1/-1}.icsf-wrap input,.icsf-wrap textarea,.icsf-wrap select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #c8d2e1;box-sizing:border-box}.icsf-wrap label{font-weight:600}.icsf-progress{height:8px;border-radius:8px;background:rgba(255,255,255,.22);overflow:hidden;margin-bottom:14px}.icsf-progress-bar{height:100%;background:linear-gradient(90deg,#00d4ff,#6f7dff)}.icsf-theme-minimal{background:#fff;border:1px solid #e8edf7;box-shadow:0 12px 30px rgba(10,40,90,.08)}.icsf-theme-neo-glass{background:rgba(8,11,26,.72);border:1px solid rgba(126,145,255,.35);backdrop-filter:blur(8px);color:#f5f9ff;box-shadow:0 14px 40px rgba(84,99,255,.25)}.icsf-theme-neo-glass input,.icsf-theme-neo-glass textarea,.icsf-theme-neo-glass select{background:rgba(255,255,255,.93)}.icsf-theme-cyber-neon{background:#0a1024;border:1px solid #00e1ff;box-shadow:0 0 0 1px rgba(0,225,255,.35),0 16px 46px rgba(0,225,255,.2);color:#dbf7ff}.icsf-theme-cyber-neon input,.icsf-theme-cyber-neon textarea,.icsf-theme-cyber-neon select{background:#101938;color:#dbf7ff;border-color:#2d3f77}.icsf-theme-aurora{background:linear-gradient(135deg,#121d4f,#0d6a7d,#4a267d);color:#fff;box-shadow:0 16px 50px rgba(58,74,180,.3)}.icsf-wrap button{padding:12px 22px;border-radius:12px;border:0;font-weight:700;cursor:pointer;background:linear-gradient(90deg,#5f77ff,#00d4ff);color:#fff}@media(max-width:700px){.icsf-grid{grid-template-columns:1fr}}';
        wp_register_style('icsf-inline', false);
        wp_enqueue_style('icsf-inline');
        wp_add_inline_style('icsf-inline', $css);

        $js = "document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.icsf-wrap form').forEach(function(f){var req=f.querySelectorAll('[required]').length;function update(){if(!req)return;var filled=0;f.querySelectorAll('[required]').forEach(function(el){if((el.value||'').trim()!=='')filled++;});var p=Math.round((filled/req)*100);var b=f.querySelector('.icsf-progress-bar');if(b){b.style.width=p+'%';}}f.addEventListener('input',update);update();});});";
        wp_register_script('icsf-inline-js', '', [], false, true);
        wp_enqueue_script('icsf-inline-js');
        wp_add_inline_script('icsf-inline-js', $js);
    }

    public function get_form(string $id): array {
        $forms = $this->plugin->get_forms();

        if ($id !== '' && isset($forms[$id])) {
            return wp_parse_args($forms[$id], $this->plugin->default_form());
        }

        if (!empty($forms)) {
            return wp_parse_args((array) reset($forms), $this->plugin->default_form());
        }

        return wp_parse_args(['id' => 'default-contact', 'name' => 'Default Contact Form'], $this->plugin->default_form());
    }

    public function sanitize_fields(array $raw_fields): array {
        $allowed_types = ['text', 'email', 'textarea', 'select', 'radio', 'checkbox', 'tel', 'number', 'date', 'url', 'hidden', 'password'];
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
            $help_text = isset($field['help_text']) ? sanitize_text_field((string) $field['help_text']) : '';
            $default_value = isset($field['default_value']) ? sanitize_text_field((string) $field['default_value']) : '';
            $width = isset($field['width']) ? sanitize_key((string) $field['width']) : 'half';
            $pattern = isset($field['validation_pattern']) ? sanitize_text_field((string) $field['validation_pattern']) : '';
            $min_length = isset($field['min_length']) && $field['min_length'] !== '' ? max(0, absint($field['min_length'])) : '';
            $max_length = isset($field['max_length']) && $field['max_length'] !== '' ? max(0, absint($field['max_length'])) : '';
            $min = isset($field['min']) ? sanitize_text_field((string) $field['min']) : '';
            $max = isset($field['max']) ? sanitize_text_field((string) $field['max']) : '';
            $step = isset($field['step']) ? sanitize_text_field((string) $field['step']) : '';
            $rows = isset($field['rows']) && $field['rows'] !== '' ? max(2, absint($field['rows'])) : 5;
            $options_raw = isset($field['options']) ? sanitize_text_field((string) $field['options']) : '';

            if ($name === '') {
                continue;
            }

            if ($label === '' && $type !== 'hidden') {
                continue;
            }

            if (!in_array($type, $allowed_types, true)) {
                $type = 'text';
            }

            if (!in_array($width, ['half', 'full'], true)) {
                $width = in_array($type, ['textarea', 'radio', 'checkbox'], true) ? 'full' : 'half';
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
                'label' => $label !== '' ? $label : ucfirst(str_replace('_', ' ', $name)),
                'name' => $name,
                'type' => $type,
                'required' => $required,
                'placeholder' => $placeholder,
                'help_text' => $help_text,
                'default_value' => $default_value,
                'width' => $width,
                'validation_pattern' => $pattern,
                'min_length' => $min_length,
                'max_length' => $max_length,
                'min' => $min,
                'max' => $max,
                'step' => $step,
                'rows' => $rows,
                'options' => $options,
            ];
        }

        return $clean;
    }

    public function render_shortcode(array $atts): string {
        $atts = shortcode_atts(['id' => ''], $atts, 'ikonic_contact_form');
        $form = $this->get_form((string) $atts['id']);

        if (($form['status'] ?? 'active') !== 'active') {
            return '<p>' . esc_html__('This form is currently unavailable.', 'ikonic-contact-smtp-form') . '</p>';
        }

        $notice = '';
        if (isset($_GET['icsf_status'], $_GET['icsf_form_id']) && sanitize_key(wp_unslash($_GET['icsf_form_id'])) === $form['id']) {
            $status = sanitize_text_field(wp_unslash($_GET['icsf_status']));
            $notice = $status === 'success'
                ? '<p style="color:#15b86a;font-weight:600;">' . esc_html($form['success_message']) . '</p>'
                : '<p style="color:#ff5a5f;font-weight:600;">' . esc_html((string) $form['error_message']) . '</p>';
        }

        $modules = $this->plugin->get_modules();

        ob_start();
        echo $notice;
        echo '<div class="icsf-wrap icsf-theme-' . esc_attr($form['theme']) . '">';
        echo '<form method="post" action="' . esc_url(remove_query_arg(['icsf_status', 'icsf_form_id'])) . '">';
        if (!empty($modules['frontend_progress_meter'])) {
            echo '<div class="icsf-progress"><div class="icsf-progress-bar" style="width:0%"></div></div>';
        }

        echo '<input type="hidden" name="icsf_submit" value="1" />';
        echo '<input type="hidden" name="icsf_form_id" value="' . esc_attr($form['id']) . '" />';
        wp_nonce_field(ICSF_Plugin::SUBMIT_NONCE_PREFIX . $form['id'], 'icsf_nonce');

        $grid_class = ($form['layout'] ?? 'grid') === 'single' ? 'icsf-single' : 'icsf-grid';
        echo '<div class="' . esc_attr($grid_class) . '">';
        foreach ($form['fields'] as $field) {
            $is_full = ($field['width'] ?? 'half') === 'full' || $field['type'] === 'textarea' || ($form['layout'] ?? '') === 'single';
            $full = $is_full ? 'icsf-full' : '';
            $label = $field['type'] === 'hidden' ? '' : '<label>' . esc_html($field['label']) . '</label><br />';
            echo '<p class="' . esc_attr($full) . '">' . $label;
            $this->render_field($field);
            if (!empty($field['help_text']) && $field['type'] !== 'hidden') {
                echo '<small style="display:block;opacity:.8;margin-top:4px;">' . esc_html((string) $field['help_text']) . '</small>';
            }
            echo '</p>';
        }

        if (!empty($form['enable_honeypot']) && !empty($modules['honeypot'])) {
            echo '<p class="icsf-full" style="position:absolute;left:-9999px;opacity:0;">';
            echo '<label>Leave blank</label><input type="text" name="icsf_hp" value="" tabindex="-1" autocomplete="off" />';
            echo '</p>';
        }

        echo '<p class="icsf-full"><button type="submit">' . esc_html($form['button_text']) . '</button></p>';
        echo '</div>';
        echo '</form></div>';

        return (string) ob_get_clean();
    }

    public function handle_submission(): void {
        if (empty($_POST['icsf_submit'])) {
            return;
        }

        $form_id = isset($_POST['icsf_form_id']) ? sanitize_key(wp_unslash($_POST['icsf_form_id'])) : '';
        $form = $this->get_form($form_id);

        if (($form['status'] ?? 'active') !== 'active') {
            $this->redirect('error', $form['id']);
        }

        if (!isset($_POST['icsf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icsf_nonce'])), ICSF_Plugin::SUBMIT_NONCE_PREFIX . $form['id'])) {
            $this->analytics->log_submission($form['id'], [], 'error');
            $this->redirect('error', $form['id']);
        }

        $modules = $this->plugin->get_modules();
        if (!empty($form['enable_honeypot']) && !empty($modules['honeypot']) && !empty($_POST['icsf_hp'])) {
            $this->analytics->log_submission($form['id'], [], 'blocked_honeypot');
            $this->redirect('error', $form['id']);
        }

        if (!$this->rate_limit_check($form)) {
            $this->analytics->log_submission($form['id'], [], 'rate_limited');
            $this->redirect('error', $form['id']);
        }

        $data = [];
        foreach ($form['fields'] as $field) {
            $name = $field['name'];
            $raw = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : ($field['default_value'] ?? '');
            $value = is_array($raw) ? implode(', ', array_map('sanitize_text_field', $raw)) : sanitize_textarea_field((string) $raw);

            if ($field['type'] === 'email') {
                $value = sanitize_email((string) $raw);
                if ($value !== '' && !is_email($value)) {
                    $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                    $this->redirect('error', $form['id']);
                }
            }

            if ($field['type'] === 'url') {
                $value = esc_url_raw((string) $raw);
            }

            if (in_array($field['type'], ['number', 'date'], true)) {
                $value = sanitize_text_field((string) $raw);
            }

            if (!empty($field['required']) && trim((string) $value) === '') {
                $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                $this->redirect('error', $form['id']);
            }

            $min_length = isset($field['min_length']) && $field['min_length'] !== '' ? (int) $field['min_length'] : 0;
            $max_length = isset($field['max_length']) && $field['max_length'] !== '' ? (int) $field['max_length'] : 0;
            $len = function_exists('mb_strlen') ? mb_strlen((string) $value) : strlen((string) $value);
            if ($min_length > 0 && $value !== '' && $len < $min_length) {
                $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                $this->redirect('error', $form['id']);
            }
            if ($max_length > 0 && $value !== '' && $len > $max_length) {
                $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                $this->redirect('error', $form['id']);
            }

            $pattern = isset($field['validation_pattern']) ? (string) $field['validation_pattern'] : '';
            if ($pattern !== '' && $value !== '') {
                $regex = '/'. str_replace('/', '\/', $pattern) . '/u';
                if (@preg_match($regex, '') !== false && !preg_match($regex, (string) $value)) {
                    $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                    $this->redirect('error', $form['id']);
                }
            }

            if ($field['type'] === 'number' && $value !== '' && is_numeric($value)) {
                $numeric = (float) $value;
                if ($field['min'] !== '' && is_numeric((string) $field['min']) && $numeric < (float) $field['min']) {
                    $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                    $this->redirect('error', $form['id']);
                }
                if ($field['max'] !== '' && is_numeric((string) $field['max']) && $numeric > (float) $field['max']) {
                    $this->analytics->log_submission($form['id'], [$name => $value], 'error');
                    $this->redirect('error', $form['id']);
                }
            }

            $data[$name] = $value;
        }

        $smtp = $this->plugin->get_smtp_settings();
        $to = !empty($form['to_email']) ? $form['to_email'] : $smtp['to_email'];
        if (!is_email($to)) {
            $to = get_option('admin_email');
        }

        $fields_text = $this->build_fields_text($form, $data);

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . ($smtp['from_name'] ?: get_bloginfo('name')) . ' <' . ($smtp['from_email'] ?: get_option('admin_email')) . '>',
        ];

        $sent = true;

        if (!empty($form['admin_notify_enabled'])) {
            $subject = $this->replace_tokens((string) $form['admin_subject_template'], $form, $data);
            $body = $this->replace_tokens((string) $form['admin_body_template'], $form, $data, $fields_text);
            $sent = wp_mail($to, $subject, $body, $headers);
        }

        if (!empty($modules['autoresponder']) && !empty($form['autoresponder_enabled'])) {
            $email_field = sanitize_key((string) $form['autoresponder_email_field']);
            $recipient = isset($data[$email_field]) ? sanitize_email((string) $data[$email_field]) : '';
            if (is_email($recipient)) {
                $ar_subject = $this->replace_tokens((string) $form['autoresponder_subject'], $form, $data, $fields_text);
                $ar_body = $this->replace_tokens((string) $form['autoresponder_body'], $form, $data, $fields_text);
                wp_mail($recipient, $ar_subject, $ar_body, $headers);
            }
        }

        $entry_payload = !empty($form['store_entries']) ? $data : [];
        $this->analytics->log_submission($form['id'], $entry_payload, $sent ? 'success' : 'error');

        do_action('icsf_after_submission', $form, $data, (bool) $sent);


        if (!empty($modules['webhook_gateway']) && !empty($form['webhook_enabled']) && !empty($form['webhook_url'])) {
            wp_remote_post(esc_url_raw((string) $form['webhook_url']), [
                'timeout' => 8,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'form_id' => $form['id'],
                    'form_name' => $form['name'],
                    'submitted_at' => current_time('mysql'),
                    'status' => $sent ? 'success' : 'error',
                    'fields' => $data,
                ]),
            ]);
        }

        if ($sent && ($form['submit_action'] ?? 'message') === 'redirect' && !empty($form['redirect_url'])) {
            wp_safe_redirect(esc_url_raw((string) $form['redirect_url']));
            exit;
        }

        $this->redirect($sent ? 'success' : 'error', $form['id']);
    }

    private function render_field(array $field): void {
        $required = !empty($field['required']) ? 'required' : '';
        $name = esc_attr($field['name']);
        $placeholder = esc_attr((string) ($field['placeholder'] ?? ''));
        $value = esc_attr((string) ($field['default_value'] ?? ''));
        $attrs = $this->build_field_attributes($field);

        switch ($field['type']) {
            case 'textarea':
                $rows = isset($field['rows']) && (int) $field['rows'] > 1 ? (int) $field['rows'] : 5;
                echo '<textarea name="' . $name . '" rows="' . esc_attr((string) $rows) . '" placeholder="' . $placeholder . '" ' . $required . ' ' . $attrs . '>' . esc_textarea((string) ($field['default_value'] ?? '')) . '</textarea>';
                break;
            case 'select':
                echo '<select name="' . $name . '" ' . $required . ' ' . $attrs . '><option value="">' . esc_html__('Select', 'ikonic-contact-smtp-form') . '</option>';
                foreach ($field['options'] as $opt) {
                    echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                }
                echo '</select>';
                break;
            case 'radio':
                foreach ($field['options'] as $idx => $opt) {
                    echo '<label><input type="radio" name="' . $name . '" value="' . esc_attr($opt) . '" ' . ($idx === 0 ? $required : '') . ' ' . $attrs . ' /> ' . esc_html($opt) . '</label> ';
                }
                break;
            case 'checkbox':
                foreach ($field['options'] as $opt) {
                    echo '<label><input type="checkbox" name="' . $name . '[]" value="' . esc_attr($opt) . '" ' . $attrs . ' /> ' . esc_html($opt) . '</label> ';
                }
                break;
            case 'hidden':
                echo '<input type="hidden" name="' . $name . '" value="' . $value . '" ' . $attrs . ' />';
                break;
            case 'email':
            case 'tel':
            case 'number':
            case 'date':
            case 'url':
            case 'password':
                echo '<input type="' . esc_attr($field['type']) . '" name="' . $name . '" value="' . $value . '" placeholder="' . $placeholder . '" ' . $required . ' ' . $attrs . ' />';
                break;
            default:
                echo '<input type="text" name="' . $name . '" value="' . $value . '" placeholder="' . $placeholder . '" ' . $required . ' ' . $attrs . ' />';
        }
    }

    private function build_field_attributes(array $field): string {
        $attrs = [];

        if (isset($field['min_length']) && $field['min_length'] !== '') {
            $attrs[] = 'minlength="' . esc_attr((string) absint($field['min_length'])) . '"';
        }

        if (isset($field['max_length']) && $field['max_length'] !== '') {
            $attrs[] = 'maxlength="' . esc_attr((string) absint($field['max_length'])) . '"';
        }

        if (isset($field['min']) && $field['min'] !== '') {
            $attrs[] = 'min="' . esc_attr((string) $field['min']) . '"';
        }

        if (isset($field['max']) && $field['max'] !== '') {
            $attrs[] = 'max="' . esc_attr((string) $field['max']) . '"';
        }

        if (isset($field['step']) && $field['step'] !== '') {
            $attrs[] = 'step="' . esc_attr((string) $field['step']) . '"';
        }

        if (!empty($field['validation_pattern'])) {
            $attrs[] = 'pattern="' . esc_attr((string) $field['validation_pattern']) . '"';
        }

        return implode(' ', $attrs);
    }

    private function build_fields_text(array $form, array $data): string {
        $lines = [];
        foreach ($form['fields'] as $field) {
            $label = (string) $field['label'];
            $val = (string) ($data[$field['name']] ?? '');
            $lines[] = $label . ': ' . $val;
        }
        return implode("\n", $lines);
    }

    private function replace_tokens(string $text, array $form, array $data, string $fields_text = ''): string {
        $map = [
            '{form_name}' => (string) ($form['name'] ?? ''),
            '{fields}' => $fields_text !== '' ? $fields_text : $this->build_fields_text($form, $data),
            '{site_name}' => (string) get_bloginfo('name'),
        ];
        foreach ($data as $key => $value) {
            $map['{' . $key . '}'] = (string) $value;
        }
        return strtr($text, $map);
    }

    private function rate_limit_check(array $form): bool {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if ($ip === '') {
            return true;
        }

        $max = isset($form['max_submissions_per_hour']) ? max(1, absint($form['max_submissions_per_hour'])) : 30;
        $key = 'icsf_rate_' . md5($form['id'] . '|' . $ip);
        $count = (int) get_transient($key);

        if ($count >= $max) {
            return false;
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }

    private function redirect(string $status, string $form_id): void {
        $url = wp_get_referer() ?: home_url('/');
        $url = add_query_arg(['icsf_status' => $status, 'icsf_form_id' => $form_id], $url);
        wp_safe_redirect($url);
        exit;
    }
}
