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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_action('admin_post_icsf_save_form', [$this, 'handle_save_form']);
        add_action('admin_post_icsf_delete_form', [$this, 'handle_delete_form']);
        add_action('admin_post_icsf_export_logs', [$this, 'handle_export_logs']);
        add_action('admin_post_icsf_save_modules', [$this, 'handle_save_modules']);
    }

    public function register_menu(): void {
        add_menu_page('Ikonic Form Builder', 'Ikonic Form Builder', 'manage_options', 'ikonic-contact-form-builder', [$this, 'render_forms_page'], 'dashicons-superhero', 58);
        add_submenu_page('ikonic-contact-form-builder', 'Forms', 'Forms', 'manage_options', 'ikonic-contact-form-builder', [$this, 'render_forms_page']);
        add_submenu_page('ikonic-contact-form-builder', 'Analytics', 'Analytics', 'manage_options', 'ikonic-contact-analytics', [$this, 'render_analytics_page']);
        add_submenu_page('ikonic-contact-form-builder', 'SMTP Settings', 'SMTP Settings', 'manage_options', 'ikonic-contact-smtp-settings', [$this, 'render_smtp_page']);
        add_submenu_page('ikonic-contact-form-builder', 'UI Studio & Modules', 'UI Studio & Modules', 'manage_options', 'ikonic-contact-modules', [$this, 'render_modules_page']);
    }

    public function enqueue_admin_assets(string $hook): void {
        $allowed = ['toplevel_page_ikonic-contact-form-builder', 'ikonic-form-builder_page_ikonic-contact-analytics', 'ikonic-form-builder_page_ikonic-contact-modules', 'ikonic-form-builder_page_ikonic-contact-smtp-settings'];
        if (!in_array($hook, $allowed, true)) {
            return;
        }

        $css = '.icsf-enterprise{--bg:#0d1633;--bg2:#121f46;--ink:#eaf2ff;--muted:#adc3ea;--line:#324d88;--card:rgba(12,24,58,.88);background:linear-gradient(145deg,var(--bg),var(--bg2));color:var(--ink);padding:20px;border-radius:16px;margin:14px 0;border:1px solid rgba(139,169,241,.25);overflow:hidden}.icsf-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}.icsf-enterprise .icsf-title{font-size:26px;font-weight:700;margin:0;color:#f4f8ff !important}.icsf-kicker{color:var(--muted);font-size:13px;letter-spacing:.08em;text-transform:uppercase}.icsf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.icsf-stat{background:var(--card);border:1px solid var(--line);padding:14px;border-radius:12px}.icsf-stat h3{margin:0 0 6px;font-size:12px;letter-spacing:.05em;color:var(--muted);text-transform:uppercase}.icsf-stat p{margin:0;font-size:26px;font-weight:700;color:#f4f8ff}.icsf-panel{background:#fff;border:1px solid #e3e9f5;border-radius:12px;padding:14px;margin-top:14px}.icsf-panel h2{margin-top:0}.icsf-chip{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#edf3ff;color:#244f9f}.icsf-module{padding:12px;border:1px solid #dce4f3;border-radius:10px;background:#fff;margin-bottom:10px;display:flex;justify-content:space-between;gap:10px}.icsf-form-shell input[type=text],.icsf-form-shell input[type=email],.icsf-form-shell input[type=url],.icsf-form-shell select{min-width:260px}.icsf-table{overflow:auto}.icsf-table table{min-width:980px}.icsf-success{color:#0f9960}.icsf-danger{color:#d14343}.icsf-enterprise .notice,.icsf-enterprise .updated,.icsf-enterprise .error{background:#fff;color:#1d2327;border-left:4px solid #2271b1;margin:10px 0;border-radius:6px}';

        wp_register_style('icsf-admin-inline', false);
        wp_enqueue_style('icsf-admin-inline');
        wp_add_inline_style('icsf-admin-inline', $css);

        $js = "document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.icsf-enterprise .notice, .icsf-enterprise .updated, .icsf-enterprise .error').forEach(function(n){var e=n.closest('.icsf-enterprise');if(e&&e.parentNode){e.parentNode.insertBefore(n,e);}});});";
        wp_register_script('icsf-admin-inline-js', '', [], false, true);
        wp_enqueue_script('icsf-admin-inline-js');
        wp_add_inline_script('icsf-admin-inline-js', $js);
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
            echo '<label><input type="checkbox" name="' . esc_attr(ICSF_Plugin::SMTP_OPTION_KEY) . '[' . esc_attr($key) . ']" value="1" ' . checked(!empty($smtp[$key]), true, false) . ' /> Enable SMTP Delivery</label>';
            return;
        }

        $type = $key === 'smtp_password' ? 'password' : 'text';
        echo '<input type="' . esc_attr($type) . '" class="regular-text" name="' . esc_attr(ICSF_Plugin::SMTP_OPTION_KEY) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) ($smtp[$key] ?? '')) . '" />';
    }

    public function render_smtp_page(): void {
        $smtp = $this->plugin->get_smtp_settings();
        echo '<div class="wrap">';
        echo '<div class="icsf-enterprise"><div class="icsf-toolbar"><div><div class="icsf-kicker">Enterprise Mail Gateway</div><h1 class="icsf-title">SMTP Control Tower</h1></div><span class="icsf-chip">Transport Layer</span></div>';
        echo '<div class="icsf-grid">';
        echo '<div class="icsf-stat"><h3>SMTP State</h3><p>' . (!empty($smtp['enable_smtp']) ? 'On' : 'Off') . '</p></div>';
        echo '<div class="icsf-stat"><h3>Host</h3><p style="font-size:18px">' . esc_html((string) ($smtp['smtp_host'] ?: 'Not set')) . '</p></div>';
        echo '<div class="icsf-stat"><h3>Security</h3><p>' . esc_html(strtoupper((string) $smtp['smtp_secure'])) . '</p></div>';
        echo '<div class="icsf-stat"><h3>Port</h3><p>' . esc_html((string) $smtp['smtp_port']) . '</p></div>';
        echo '</div></div>';

        echo '<div class="icsf-panel"><h2>SMTP Configuration</h2><form method="post" action="options.php">';
        settings_fields('icsf_smtp_settings_group');
        do_settings_sections('ikonic-contact-smtp-settings');
        submit_button('Save SMTP Profile');
        echo '</form></div></div>';
    }

    public function render_modules_page(): void {
        $modules = $this->plugin->get_modules();
        echo '<div class="wrap"><div class="icsf-enterprise"><div class="icsf-toolbar"><div><div class="icsf-kicker">Platform Orchestration</div><h1 class="icsf-title">UI Studio & Modules</h1></div><span class="icsf-chip">Feature Flags</span></div>';
        echo '<p>Enable enterprise modules for frontend experiences, admin controls, analytics, and anti-spam automation.</p></div>';

        echo '<div class="icsf-panel"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="icsf_save_modules" />';
        wp_nonce_field(ICSF_Plugin::ADMIN_NONCE_ACTION, 'icsf_admin_nonce');

        foreach ($modules as $key => $enabled) {
            echo '<div class="icsf-module"><div><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</strong><br><span style="color:#65789b;font-size:12px">Module toggle for enterprise behavior</span></div><label><input type="checkbox" name="modules[' . esc_attr($key) . ']" value="1" ' . checked(!empty($enabled), true, false) . ' /> Enabled</label></div>';
        }

        submit_button('Save Module Matrix');
        echo '</form></div></div>';
    }

    public function handle_save_modules(): void {
        if (!$this->plugin->verify_admin_request()) {
            return;
        }

        $current = $this->plugin->get_modules();
        $incoming = isset($_POST['modules']) && is_array($_POST['modules']) ? wp_unslash($_POST['modules']) : [];
        $updated = [];

        foreach ($current as $key => $_) {
            $updated[$key] = !empty($incoming[$key]) ? 1 : 0;
        }

        $this->plugin->save_modules($updated);
        wp_safe_redirect(admin_url('admin.php?page=ikonic-contact-modules'));
        exit;
    }

    public function render_forms_page(): void {
        $forms = $this->plugin->get_forms();
        $edit_id = isset($_GET['form_id']) ? sanitize_key(wp_unslash($_GET['form_id'])) : '';
        $active = $edit_id && isset($forms[$edit_id]) ? $forms[$edit_id] : $this->plugin->default_form();
        $active = wp_parse_args($active, $this->plugin->default_form());

        echo '<div class="wrap">';
        echo '<div class="icsf-enterprise"><div class="icsf-toolbar"><div><div class="icsf-kicker">Enterprise Builder</div><h1 class="icsf-title">Forms Studio</h1></div><span class="icsf-chip">v6 Enterprise</span></div>';
        echo '<div class="icsf-grid">';
        echo '<div class="icsf-stat"><h3>Total Forms</h3><p>' . esc_html((string) count($forms)) . '</p></div>';
        echo '<div class="icsf-stat"><h3>Current Theme</h3><p style="font-size:18px">' . esc_html((string) $active['theme']) . '</p></div>';
        echo '<div class="icsf-stat"><h3>Current Form</h3><p style="font-size:18px">' . esc_html((string) $active['name']) . '</p></div>';
        echo '<div class="icsf-stat"><h3>Layout</h3><p>' . esc_html((string) strtoupper((string) $active['layout'])) . '</p></div>';
        echo '</div></div>';

        echo '<div class="icsf-panel"><h2>Saved Forms Registry</h2><div class="icsf-table"><table class="widefat striped"><thead><tr><th>Name</th><th>ID</th><th>Shortcode</th><th>Actions</th></tr></thead><tbody>';
        if (empty($forms)) {
            echo '<tr><td colspan="4">No forms yet.</td></tr>';
        } else {
            foreach ($forms as $form) {
                echo '<tr><td>' . esc_html($form['name']) . '</td><td>' . esc_html($form['id']) . '</td><td><code>[ikonic_contact_form id="' . esc_html($form['id']) . '"]</code></td><td>';
                echo '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=ikonic-contact-form-builder&form_id=' . rawurlencode($form['id']))) . '">Edit</a> ';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="icsf_delete_form" /><input type="hidden" name="form_id" value="' . esc_attr($form['id']) . '" />';
                wp_nonce_field(ICSF_Plugin::ADMIN_NONCE_ACTION, 'icsf_admin_nonce');
                echo '<button class="button button-small" type="submit">Delete</button></form>';
                echo '</td></tr>';
            }
        }
        echo '</tbody></table></div></div>';

        echo '<div class="icsf-panel icsf-form-shell"><h2>Enterprise Form Builder</h2>';
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
        echo '<tr><th>Layout</th><td><select name="layout"><option value="grid" ' . selected($active['layout'], 'grid', false) . '>Grid</option><option value="single" ' . selected($active['layout'], 'single', false) . '>Single Column</option></select></td></tr>';
        echo '<tr><th>Enable Honeypot</th><td><label><input type="checkbox" name="enable_honeypot" value="1" ' . checked(!empty($active['enable_honeypot']), true, false) . ' /> Anti-spam trap</label></td></tr>';
        echo '</table>';

        echo '<h3>Field Matrix</h3>';
        echo '<div class="icsf-table"><table class="widefat striped"><thead><tr><th>Label</th><th>Name</th><th>Type</th><th>Placeholder</th><th>Options (csv)</th><th>Required (1/0)</th></tr></thead><tbody>';
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
        for ($n = 0; $n < 6; $n++) {
            $idx = count($rows) + $n;
            echo '<tr><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][label]" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][name]" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][type]" value="text" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][placeholder]" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][options]" /></td><td><input type="text" name="fields[' . esc_attr((string) $idx) . '][required]" value="0" /></td></tr>';
        }
        echo '</tbody></table></div>';

        submit_button('Save Enterprise Form');
        echo '</form></div></div>';
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
            'theme' => isset($_POST['theme']) ? sanitize_key(wp_unslash($_POST['theme'])) : 'neo-glass',
            'button_text' => isset($_POST['button_text']) ? sanitize_text_field(wp_unslash($_POST['button_text'])) : 'Transmit Message',
            'layout' => isset($_POST['layout']) ? sanitize_key(wp_unslash($_POST['layout'])) : 'grid',
            'enable_honeypot' => !empty($_POST['enable_honeypot']) ? 1 : 0,
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

        echo '<div class="wrap"><div class="icsf-enterprise"><div class="icsf-toolbar"><div><div class="icsf-kicker">Observability</div><h1 class="icsf-title">Enterprise Analytics Command Center</h1></div><span class="icsf-chip">Live Metrics</span></div>';
        echo '<div class="icsf-grid">';
        echo '<div class="icsf-stat"><h3>Total</h3><p>' . esc_html((string) $metrics['total']) . '</p></div>';
        echo '<div class="icsf-stat"><h3>Success</h3><p class="icsf-success">' . esc_html((string) $metrics['success']) . '</p></div>';
        echo '<div class="icsf-stat"><h3>Failed</h3><p class="icsf-danger">' . esc_html((string) $metrics['failed']) . '</p></div>';
        echo '<div class="icsf-stat"><h3>Success Rate</h3><p>' . esc_html((string) $metrics['success_rate']) . '%</p></div>';
        echo '</div></div>';

        echo '<div class="icsf-panel"><form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="ikonic-contact-analytics" />';
        echo '<label>From <input type="date" name="from" value="' . esc_attr($from) . '" /></label> ';
        echo '<label>To <input type="date" name="to" value="' . esc_attr($to) . '" /></label> ';
        echo '<button class="button">Filter Window</button>';
        echo '</form>';

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

        echo '<h2>Hourly Heatmap (0-23)</h2><table class="widefat striped"><thead><tr><th>Hour</th><th>Submissions</th></tr></thead><tbody>';
        foreach ($metrics['by_hour'] as $hour => $count) {
            echo '<tr><td>' . esc_html(str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00') . '</td><td>' . esc_html((string) $count) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>Top Submitted Fields</h2><table class="widefat striped"><thead><tr><th>Field</th><th>Count</th></tr></thead><tbody>';
        if (empty($metrics['top_fields'])) {
            echo '<tr><td colspan="2">No data.</td></tr>';
        } else {
            foreach ($metrics['top_fields'] as $field => $count) {
                echo '<tr><td>' . esc_html((string) $field) . '</td><td>' . esc_html((string) $count) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        echo '<h2>Recent Entries</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="icsf_export_logs" />';
        wp_nonce_field(ICSF_Plugin::ADMIN_NONCE_ACTION, 'icsf_admin_nonce');
        echo '<button type="submit" class="button button-primary">Export CSV</button></form>';

        echo '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th>Date</th><th>Form</th><th>Status</th><th>IP</th><th>Preview</th></tr></thead><tbody>';
        $recent = array_slice(array_reverse($logs), 0, 60);
        if (empty($recent)) {
            echo '<tr><td colspan="5">No entries.</td></tr>';
        } else {
            foreach ($recent as $row) {
                echo '<tr><td>' . esc_html((string) $row['created_at']) . '</td><td>' . esc_html((string) $row['form_id']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td>' . esc_html((string) $row['ip']) . '</td><td>' . esc_html($this->summary((array) ($row['fields'] ?? []))) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        echo '</div></div>';
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
