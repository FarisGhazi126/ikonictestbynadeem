<?php

if (!defined('ABSPATH')) {
    exit;
}

class ICSF_Admin {
    private ICSF_Plugin $plugin;
    private ICSF_Forms $forms;
    private ICSF_Analytics $analytics;

    public function __construct(ICSF_Plugin $plugin, ICSF_Forms $forms, ICSF_Analytics $analytics) {
        $this->plugin = $plugin;
        $this->forms = $forms;
        $this->analytics = $analytics;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_smtp_settings']);

        add_action('admin_post_icsf_save_form', [$this, 'handle_save_form']);
        add_action('admin_post_icsf_delete_form', [$this, 'handle_delete_form']);
        add_action('admin_post_icsf_export_logs', [$this, 'handle_export_logs']);
    }

    public function register_menu(): void {
        add_menu_page('Ikonic Form Builder', 'Ikonic Form Builder', 'manage_options', 'ikonic-contact-form-builder', [$this, 'render_forms_page'], 'dashicons-feedback', 58);
        add_submenu_page('ikonic-contact-form-builder', 'Forms', 'Forms', 'manage_options', 'ikonic-contact-form-builder', [$this, 'render_forms_page']);
        add_submenu_page('ikonic-contact-form-builder', 'Analytics', 'Analytics', 'manage_options', 'ikonic-contact-analytics', [$this, 'render_analytics_page']);
        add_submenu_page('ikonic-contact-form-builder', 'SMTP Settings', 'SMTP Settings', 'manage_options', 'ikonic-contact-smtp-settings', [$this, 'render_smtp_page']);
    }

    public function register_smtp_settings(): void {
        register_setting('icsf_smtp_settings_group', ICSF_Plugin::SMTP_OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this->plugin, 'sanitize_smtp_settings'],
            'default' => $this->plugin->get_smtp_settings(),
        ]);

        add_settings_section('icsf_smtp_section', 'SMTP Settings', '__return_false', 'ikonic-contact-smtp-settings');

        $fields = [
            'to_email' => 'Default Recipient Email',
            'from_name' => 'From Name',
            'from_email' => 'From Email',
            'enable_smtp' => 'Enable SMTP',
            'smtp_host' => 'SMTP Host',
            'smtp_port' => 'SMTP Port',
            'smtp_username' => 'SMTP Username',
            'smtp_password' => 'SMTP Password',
            'smtp_secure' => 'SMTP Encryption (tls/ssl/none)',
        ];

        foreach ($fields as $key => $label) {
            add_settings_field('icsf_' . $key, $label, [$this, 'render_smtp_field'], 'ikonic-contact-smtp-settings', 'icsf_smtp_section', ['key' => $key]);
        }
    }

    public function render_smtp_field(array $args): void {
        $smtp = $this->plugin->get_smtp_settings();
        $key = $args['key'];

        if ($key === 'enable_smtp') {
            echo '<label><input type="checkbox" name="' . esc_attr(ICSF_Plugin::SMTP_OPTION_KEY) . '[' . esc_attr($key) . ']" value="1" ' . checked(!empty($smtp[$key]), true, false) . ' /> Enable SMTP</label>';
            return;
        }

        $type = $key === 'smtp_password' ? 'password' : 'text';
        echo '<input type="' . esc_attr($type) . '" class="regular-text" name="' . esc_attr(ICSF_Plugin::SMTP_OPTION_KEY) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) ($smtp[$key] ?? '')) . '" />';
    }

    public function render_smtp_page(): void {
        echo '<div class="wrap"><h1>SMTP Settings</h1><form method="post" action="options.php">';
        settings_fields('icsf_smtp_settings_group');
        do_settings_sections('ikonic-contact-smtp-settings');
        submit_button();
        echo '</form></div>';
    }

    public function render_forms_page(): void {
        $forms = $this->plugin->get_forms();
        $edit_id = isset($_GET['form_id']) ? sanitize_key(wp_unslash($_GET['form_id'])) : '';
        $active = $edit_id && isset($forms[$edit_id]) ? $forms[$edit_id] : $this->plugin->default_form();
        $active = wp_parse_args($active, $this->plugin->default_form());

        echo '<div class="wrap"><h1>Forms</h1>';
        echo '<p>Create multiple forms and embed with <code>[ikonic_contact_form id="form-id"]</code>.</p>';

        echo '<h2>Saved Forms</h2><table class="widefat striped"><thead><tr><th>Name</th><th>ID</th><th>Shortcode</th><th>Actions</th></tr></thead><tbody>';
        if (empty($forms)) {
            echo '<tr><td colspan="4">No forms yet.</td></tr>';
        } else {
            foreach ($forms as $form) {
                echo '<tr><td>' . esc_html($form['name']) . '</td><td>' . esc_html($form['id']) . '</td><td><code>[ikonic_contact_form id="' . esc_html($form['id']) . '"]</code></td><td>';
                echo '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=ikonic-contact-form-builder&form_id=' . rawurlencode($form['id']))) . '">Edit</a> ';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="icsf_delete_form" />';
                echo '<input type="hidden" name="form_id" value="' . esc_attr($form['id']) . '" />';
                wp_nonce_field(ICSF_Plugin::ADMIN_NONCE_ACTION, 'icsf_admin_nonce');
                echo '<button class="button button-small" type="submit">Delete</button></form>';
                echo '</td></tr>';
            }
        }
        echo '</tbody></table>';

        echo '<hr><h2>Form Builder</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="icsf_save_form" />';
        echo '<input type="hidden" name="original_form_id" value="' . esc_attr($edit_id) . '" />';
        wp_nonce_field(ICSF_Plugin::ADMIN_NONCE_ACTION, 'icsf_admin_nonce');

        echo '<table class="form-table">';
        echo '<tr><th>Form Name</th><td><input type="text" class="regular-text" name="form_name" value="' . esc_attr($active['name']) . '" required /></td></tr>';
        echo '<tr><th>Form ID</th><td><input type="text" class="regular-text" name="form_id" value="' . esc_attr($active['id']) . '" /></td></tr>';
        echo '<tr><th>Recipient Email</th><td><input type="email" class="regular-text" name="to_email" value="' . esc_attr($active['to_email']) . '" /></td></tr>';
        echo '<tr><th>Subject Prefix</th><td><input type="text" class="regular-text" name="subject_prefix" value="' . esc_attr($active['subject_prefix']) . '" /></td></tr>';
        echo '<tr><th>Success Message</th><td><input type="text" class="regular-text" name="success_message" value="' . esc_attr($active['success_message']) . '" /></td></tr>';
        echo '<tr><th>Theme</th><td><select name="theme">';
        foreach ($this->plugin->theme_options() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($active['theme'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Button Text</th><td><input type="text" class="regular-text" name="button_text" value="' . esc_attr($active['button_text']) . '" /></td></tr>';
        echo '</table>';

        echo '<h3>Fields</h3>';
        echo '<table class="widefat striped"><thead><tr><th>Label</th><th>Name</th><th>Type</th><th>Placeholder</th><th>Options (csv)</th><th>Required (1/0)</th></tr></thead><tbody>';
        $rows = !empty($active['fields']) ? $active['fields'] : $this->plugin->default_form()['fields'];
        foreach ($rows as $i => $field) {
            echo '<tr>';
            echo '<td><input type="text" name="fields[' . esc_attr((string) $i) . '][label]" value="' . esc_attr($field['label']) . '" /></td>';
            echo '<td><input type="text" name="fields[' . esc_attr((string) $i) . '][name]" value="' . esc_attr($field['name']) . '" /></td>';
            echo '<td><input type="text" name="fields[' . esc_attr((string) $i) . '][type]" value="' . esc_attr($field['type']) . '" /></td>';
            echo '<td><input type="text" name="fields[' . esc_attr((string) $i) . '][placeholder]" value="' . esc_attr($field['placeholder']) . '" /></td>';
            echo '<td><input type="text" name="fields[' . esc_attr((string) $i) . '][options]" value="' . esc_attr(implode(',', $field['options'])) . '" /></td>';
            echo '<td><input type="text" name="fields[' . esc_attr((string) $i) . '][required]" value="' . esc_attr($field['required'] ? '1' : '0') . '" /></td>';
            echo '</tr>';
        }
        for ($n = 0; $n < 5; $n++) {
            $idx = count($rows) + $n;
            echo '<tr><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][label]" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][name]" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][type]" value="text" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][placeholder]" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][options]" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][required]" value="0" /></td></tr>';
        }
        echo '</tbody></table>';

        submit_button('Save Form');
        echo '</form></div>';
    }

    public function handle_save_form(): void {
        if (!$this->plugin->verify_admin_request()) {
            return;
        }

        $name = isset($_POST['form_name']) ? sanitize_text_field(wp_unslash($_POST['form_name'])) : '';
        if ($name === '') {
            wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder'));
            exit;
        }

        $forms = $this->plugin->get_forms();
        $provided_id = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';
        $original_id = isset($_POST['original_form_id']) ? sanitize_key(wp_unslash($_POST['original_form_id'])) : '';

        $id = $provided_id !== '' ? $provided_id : sanitize_title($name);
        if ($id === '') {
            $id = 'form-' . wp_generate_password(6, false, false);
        }

        if ($original_id === '') {
            $id = $this->generate_unique_form_id($id, $forms);
        } elseif ($original_id !== $id && isset($forms[$id])) {
            $id = $this->generate_unique_form_id($id, $forms);
        }

        $raw_fields = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        $fields = $this->forms->sanitize_fields($raw_fields);
        if (empty($fields)) {
            $fields = $this->plugin->default_form()['fields'];
        }

        if ($original_id !== '' && $original_id !== $id) {
            unset($forms[$original_id]);
        }

        $forms[$id] = [
            'id' => $id,
            'name' => $name,
            'to_email' => isset($_POST['to_email']) ? sanitize_email(wp_unslash($_POST['to_email'])) : '',
            'subject_prefix' => isset($_POST['subject_prefix']) ? sanitize_text_field(wp_unslash($_POST['subject_prefix'])) : '[Contact Form]',
            'success_message' => isset($_POST['success_message']) ? sanitize_text_field(wp_unslash($_POST['success_message'])) : 'Thanks! Your message has been sent.',
            'theme' => isset($_POST['theme']) ? sanitize_key(wp_unslash($_POST['theme'])) : 'minimal',
            'button_text' => isset($_POST['button_text']) ? sanitize_text_field(wp_unslash($_POST['button_text'])) : 'Send Message',
            'fields' => $fields,
        ];

        $this->plugin->save_forms($forms);
        wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder&form_id=' . rawurlencode($id)));
        exit;
    }

    public function handle_delete_form(): void {
        if (!$this->plugin->verify_admin_request()) {
            return;
        }

        $id = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';
        $forms = $this->plugin->get_forms();
        unset($forms[$id]);
        $this->plugin->save_forms($forms);

        wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-form-builder'));
        exit;
    }

    public function render_analytics_page(): void {
        $from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
        $to = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '';

        $logs = $this->analytics->query_logs($from, $to);
        $metrics = $this->analytics->build_metrics($logs);
        $forms = $this->plugin->get_forms();

        echo '<div class="wrap"><h1>Advanced Analytics</h1>';
        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="ikonic-contact-analytics" />';
        echo '<label>From <input type="date" name="from" value="' . esc_attr($from) . '" /></label> ';
        echo '<label>To <input type="date" name="to" value="' . esc_attr($to) . '" /></label> ';
        echo '<button class="button">Filter</button>';
        echo '</form>';

        echo '<p><strong>Total:</strong> ' . esc_html((string) $metrics['total']) . ' | <strong>Success:</strong> ' . esc_html((string) $metrics['success']) . ' | <strong>Failed:</strong> ' . esc_html((string) $metrics['failed']) . ' | <strong>Success Rate:</strong> ' . esc_html((string) $metrics['success_rate']) . '%</p>';

        echo '<h2>Submissions by Form</h2><table class="widefat striped"><thead><tr><th>Form</th><th>Count</th></tr></thead><tbody>';
        if (empty($metrics['by_form'])) {
            echo '<tr><td colspan="2">No data.</td></tr>';
        } else {
            foreach ($metrics['by_form'] as $id => $count) {
                $name = isset($forms[$id]['name']) ? $forms[$id]['name'] : $id;
                echo '<tr><td>' . esc_html($name . ' (' . $id . ')') . '</td><td>' . esc_html((string) $count) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        echo '<h2>Daily Trend</h2><table class="widefat striped"><thead><tr><th>Date</th><th>Submissions</th></tr></thead><tbody>';
        if (empty($metrics['by_day'])) {
            echo '<tr><td colspan="2">No data.</td></tr>';
        } else {
            foreach ($metrics['by_day'] as $day => $count) {
                echo '<tr><td>' . esc_html($day) . '</td><td>' . esc_html((string) $count) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        echo '<h2>Recent Entries</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="icsf_export_logs" />';
        wp_nonce_field(ICSF_Plugin::ADMIN_NONCE_ACTION, 'icsf_admin_nonce');
        echo '<button type="submit" class="button button-primary">Export CSV</button></form>';

        echo '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th>Date</th><th>Form</th><th>Status</th><th>IP</th><th>Preview</th></tr></thead><tbody>';
        $recent = array_slice(array_reverse($logs), 0, 50);
        if (empty($recent)) {
            echo '<tr><td colspan="5">No entries.</td></tr>';
        } else {
            foreach ($recent as $row) {
                echo '<tr><td>' . esc_html((string) $row['created_at']) . '</td><td>' . esc_html((string) $row['form_id']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td>' . esc_html((string) $row['ip']) . '</td><td>' . esc_html($this->summary((array) ($row['fields'] ?? []))) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        echo '</div>';
    }

    public function handle_export_logs(): void {
        if (!$this->plugin->verify_admin_request()) {
            return;
        }

        $logs = $this->plugin->get_logs();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=icsf-analytics.csv');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        fputcsv($out, ['date', 'form_id', 'status', 'ip', 'user_agent', 'fields']);
        foreach ($logs as $log) {
            fputcsv($out, [
                $log['created_at'] ?? '',
                $log['form_id'] ?? '',
                $log['status'] ?? '',
                $log['ip'] ?? '',
                $log['ua'] ?? '',
                wp_json_encode($log['fields'] ?? []),
            ]);
        }
        fclose($out);
        exit;
    }

    private function generate_unique_form_id(string $base, array $forms): string {
        $id = $base;
        $suffix = 2;
        while (isset($forms[$id])) {
            $id = $base . '-' . $suffix;
            $suffix++;
        }
        return $id;
    }

    private function summary(array $fields): string {
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
}
