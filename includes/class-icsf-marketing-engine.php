<?php

if (!defined('ABSPATH')) {
    exit;
}

class ICSF_Marketing_Engine {
    private ICSF_Plugin $plugin;

    public function __construct(ICSF_Plugin $plugin) {
        $this->plugin = $plugin;

        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('icsf_after_submission', [$this, 'capture_submission'], 10, 3);
        add_filter('cron_schedules', [$this, 'register_cron_schedules']);
        add_action('icsf_process_email_queue', [$this, 'process_email_queue']);
    }

    public static function activate(): void {
        self::create_tables();
        self::register_capabilities();
        self::schedule_events();
    }



    public static function deactivate(): void {
        $next = wp_next_scheduled('icsf_process_email_queue');
        if ($next) {
            wp_unschedule_event($next, 'icsf_process_email_queue');
        }
    }

    public static function schedule_events(): void {
        if (!wp_next_scheduled('icsf_process_email_queue')) {
            wp_schedule_event(time() + 120, 'icsf_five_minutes', 'icsf_process_email_queue');
        }
    }

    public static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'icsf_';

        $sql = [];
        $sql[] = "CREATE TABLE {$prefix}contacts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            first_name VARCHAR(190) NOT NULL DEFAULT '',
            last_name VARCHAR(190) NOT NULL DEFAULT '',
            phone VARCHAR(60) NOT NULL DEFAULT '',
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            source_form_id VARCHAR(120) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY source_form_id (source_form_id),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}contact_meta (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(190) NOT NULL,
            meta_value LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY meta_key (meta_key)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}tags (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}contact_tags (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id BIGINT UNSIGNED NOT NULL,
            tag_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contact_tag (contact_id, tag_id),
            KEY tag_id (tag_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}contact_activity (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id BIGINT UNSIGNED NOT NULL,
            activity_type VARCHAR(64) NOT NULL,
            reference_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY activity_type (activity_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}segments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            rules_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}campaigns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL DEFAULT '',
            content_html LONGTEXT NULL,
            segment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            scheduled_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY segment_id (segment_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}campaign_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            contact_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            sent_at DATETIME NULL,
            opened_at DATETIME NULL,
            clicked_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id),
            KEY contact_id (contact_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}automations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            trigger_type VARCHAR(64) NOT NULL,
            trigger_value VARCHAR(190) NOT NULL DEFAULT '',
            workflow_json LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY trigger_type (trigger_type),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}automation_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            automation_id BIGINT UNSIGNED NOT NULL,
            contact_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            message VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY automation_id (automation_id),
            KEY contact_id (contact_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}email_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            automation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            contact_id BIGINT UNSIGNED NOT NULL,
            payload LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            run_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY run_at (run_at),
            KEY status (status),
            KEY contact_id (contact_id)
        ) {$charset_collate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }

    public static function register_capabilities(): void {
        $caps = ['manage_contacts', 'manage_campaigns', 'manage_automations', 'view_analytics'];

        $admin = get_role('administrator');
        if ($admin) {
            foreach ($caps as $cap) {
                $admin->add_cap($cap);
            }
        }

        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('view_analytics');
            $editor->add_cap('manage_contacts');
        }

        if (!get_role('marketing_manager')) {
            add_role('marketing_manager', 'Marketing Manager', [
                'read' => true,
                'manage_contacts' => true,
                'manage_campaigns' => true,
                'manage_automations' => true,
                'view_analytics' => true,
            ]);
        }
    }

    public function register_admin_menu(): void {
        add_submenu_page('ikonic-contact-form-builder', 'Contacts', 'Contacts', 'manage_contacts', 'ikonic-contact-contacts', [$this, 'render_contacts_page']);
        add_submenu_page('ikonic-contact-form-builder', 'Segments', 'Segments', 'manage_contacts', 'ikonic-contact-segments', [$this, 'render_segments_page']);
        add_submenu_page('ikonic-contact-form-builder', 'Campaigns', 'Campaigns', 'manage_campaigns', 'ikonic-contact-campaigns', [$this, 'render_campaigns_page']);
        add_submenu_page('ikonic-contact-form-builder', 'Automations', 'Automations', 'manage_automations', 'ikonic-contact-automations', [$this, 'render_automations_page']);
    }

    public function capture_submission(array $form, array $data, bool $sent): void {
        $email = $this->detect_email($form, $data);
        if (!is_email($email)) {
            return;
        }

        $first_name = $this->detect_field_value($data, ['first_name', 'firstname', 'your_name', 'name']);
        $last_name = $this->detect_field_value($data, ['last_name', 'lastname', 'surname']);
        $phone = $this->detect_field_value($data, ['phone', 'telephone', 'mobile']);

        $contact_id = $this->upsert_contact($email, [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'source_form_id' => isset($form['id']) ? sanitize_key((string) $form['id']) : '',
            'status' => 'active',
        ]);

        if (!$contact_id) {
            return;
        }

        foreach ($data as $key => $value) {
            $this->save_contact_meta((int) $contact_id, sanitize_key((string) $key), maybe_serialize($value));
        }

        $this->log_activity((int) $contact_id, 'form_submission', 0);
        if ($sent) {
            $this->log_activity((int) $contact_id, 'notification_sent', 0);
        }
    }

    public function register_rest_routes(): void {
        register_rest_route('icsf/v1', '/contacts', [
            'methods' => 'GET',
            'permission_callback' => function (): bool {
                return current_user_can('manage_contacts') || current_user_can('manage_options');
            },
            'callback' => [$this, 'rest_list_contacts'],
        ]);

        register_rest_route('icsf/v1', '/contacts/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => function (): bool {
                return current_user_can('manage_contacts') || current_user_can('manage_options');
            },
            'callback' => [$this, 'rest_get_contact'],
        ]);

        register_rest_route('icsf/v1', '/contacts/upsert', [
            'methods' => 'POST',
            'permission_callback' => function (): bool {
                return current_user_can('manage_contacts') || current_user_can('manage_options');
            },
            'callback' => [$this, 'rest_upsert_contact'],
        ]);
 

        register_rest_route('icsf/v1', '/campaigns/queue', [
            'methods' => 'POST',
            'permission_callback' => function (): bool {
                return current_user_can('manage_campaigns') || current_user_can('manage_options');
            },
            'callback' => [$this, 'rest_queue_campaign'],
        ]);
    }

    public function rest_list_contacts(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) $request->get_param('per_page')));
        $offset = ($page - 1) * $per_page;
        $search = sanitize_text_field((string) $request->get_param('search'));

        $table = $this->table('contacts');
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE email LIKE %s OR first_name LIKE %s OR last_name LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $like, $per_page, $offset), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);
        }

        return new WP_REST_Response(['items' => $rows], 200);
    }

    public function rest_get_contact(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request['id'];
        $contact = $this->get_contact($id);
        if (!$contact) {
            return new WP_REST_Response(['message' => 'Not found'], 404);
        }

        return new WP_REST_Response(['item' => $contact], 200);
    }

    public function rest_upsert_contact(WP_REST_Request $request): WP_REST_Response {
        $email = sanitize_email((string) $request->get_param('email'));
        if (!is_email($email)) {
            return new WP_REST_Response(['message' => 'Invalid email'], 400);
        }

        $id = $this->upsert_contact($email, [
            'first_name' => sanitize_text_field((string) $request->get_param('first_name')),
            'last_name' => sanitize_text_field((string) $request->get_param('last_name')),
            'phone' => sanitize_text_field((string) $request->get_param('phone')),
            'status' => sanitize_key((string) ($request->get_param('status') ?: 'active')),
            'source_form_id' => sanitize_key((string) $request->get_param('source_form_id')),
        ]);

        return new WP_REST_Response(['id' => $id], 200);
    }

    public function rest_queue_campaign(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $subject = sanitize_text_field((string) $request->get_param('subject'));
        $html = wp_kses_post((string) $request->get_param('content_html'));
        $status_filter = sanitize_key((string) ($request->get_param('status') ?: 'active'));
        $limit = min(1000, max(1, absint($request->get_param('limit') ?: 200)));

        if ($subject === '' || $html === '') {
            return new WP_REST_Response(['message' => 'Subject and content_html are required'], 400);
        }

        $contacts_table = $this->table('contacts');
        $contacts = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$contacts_table} WHERE status = %s ORDER BY id DESC LIMIT %d", $status_filter, $limit), ARRAY_A);

        $queued = 0;
        foreach ($contacts as $c) {
            $this->queue_contact_email(absint($c['id']), $subject, $html);
            $queued++;
        }

        return new WP_REST_Response(['queued' => $queued], 200);
    }



    public function register_cron_schedules(array $schedules): array {
        if (!isset($schedules['icsf_five_minutes'])) {
            $schedules['icsf_five_minutes'] = [
                'interval' => 300,
                'display' => 'Every 5 Minutes (ICSF)',
            ];
        }

        return $schedules;
    }

    public function process_email_queue(): void {
        global $wpdb;

        $table = $this->table('email_queue');
        $contacts_table = $this->table('contacts');
        $now = current_time('mysql');

        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %s AND run_at <= %s ORDER BY id ASC LIMIT 25", 'queued', $now), ARRAY_A);
        if (empty($rows)) {
            return;
        }

        $admin_email = get_option('admin_email');

        foreach ($rows as $row) {
            $contact_id = isset($row['contact_id']) ? absint($row['contact_id']) : 0;
            $contact = $contact_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT email,status FROM {$contacts_table} WHERE id=%d", $contact_id), ARRAY_A) : null;
            if (!$contact || empty($contact['email']) || ($contact['status'] ?? 'active') !== 'active') {
                $wpdb->update($table, ['status' => 'failed'], ['id' => absint($row['id'])], ['%s'], ['%d']);
                continue;
            }

            $payload = maybe_unserialize((string) ($row['payload'] ?? ''));
            if (!is_array($payload)) {
                $payload = [];
            }

            $subject = isset($payload['subject']) ? sanitize_text_field((string) $payload['subject']) : 'Marketing Update';
            $body = isset($payload['html']) ? wp_kses_post((string) $payload['html']) : '';
            if ($body === '') {
                $body = '<p>Hello,</p><p>You have a new message.</p>';
            }

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            if (is_email($admin_email)) {
                $headers[] = 'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>';
            }

            $sent = wp_mail(sanitize_email((string) $contact['email']), $subject, $body, $headers);
            if ($sent) {
                $wpdb->update($table, ['status' => 'sent'], ['id' => absint($row['id'])], ['%s'], ['%d']);
                $this->log_activity($contact_id, 'campaign_email_sent', isset($row['campaign_id']) ? absint($row['campaign_id']) : 0);
            } else {
                $wpdb->update($table, ['status' => 'failed'], ['id' => absint($row['id'])], ['%s'], ['%d']);
            }
        }
    }

    private function queue_contact_email(int $contact_id, string $subject, string $html, int $campaign_id = 0, int $automation_id = 0, int $delay_minutes = 0): void {
        if ($contact_id <= 0) {
            return;
        }

        global $wpdb;
        $table = $this->table('email_queue');

        $run_at = gmdate('Y-m-d H:i:s', time() + max(0, $delay_minutes) * 60);
        $wpdb->insert($table, [
            'campaign_id' => $campaign_id,
            'automation_id' => $automation_id,
            'contact_id' => $contact_id,
            'payload' => maybe_serialize([
                'subject' => $subject,
                'html' => $html,
            ]),
            'status' => 'queued',
            'run_at' => get_date_from_gmt($run_at),
            'created_at' => current_time('mysql'),
        ], ['%d', '%d', '%d', '%s', '%s', '%s', '%s']);
    }

    public function render_contacts_page(): void {
        if (!current_user_can('manage_contacts') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'ikonic-contact-smtp-form'));
        }

        if (isset($_GET['contact_id']) && absint($_GET['contact_id']) > 0) {
            $this->render_contact_profile(absint($_GET['contact_id']));
            return;
        }

        global $wpdb;
        $table = $this->table('contacts');

        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        echo '<div class="wrap">';
        $this->render_marketing_tabs('ikonic-contact-contacts');
        echo '<h1>Contacts</h1><p>Marketing contact registry with merge-by-email and activity timeline.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Email</th><th>Name</th><th>Phone</th><th>Status</th><th>Source Form</th><th>Updated</th><th>Actions</th></tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="7">No contacts yet.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                echo '<tr>';
                echo '<td>' . esc_html((string) $row['email']) . '</td>';
                echo '<td>' . esc_html($name !== '' ? $name : '-') . '</td>';
                echo '<td>' . esc_html((string) ($row['phone'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['status'] ?? 'active')) . '</td>';
                echo '<td>' . esc_html((string) ($row['source_form_id'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['updated_at'] ?? '')) . '</td>';
                echo '<td><a class="button button-small" href="' . esc_url(admin_url('admin.php?page=ikonic-contact-contacts&contact_id=' . absint($row['id']))) . '">View</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        $pages = (int) ceil($total / $per_page);
        if ($pages > 1) {
            echo '<p style="margin-top:10px;">';
            for ($i = 1; $i <= $pages; $i++) {
                $url = admin_url('admin.php?page=ikonic-contact-contacts&paged=' . $i);
                if ($i === $paged) {
                    echo '<strong style="margin-right:8px;">' . esc_html((string) $i) . '</strong>';
                } else {
                    echo '<a style="margin-right:8px;" href="' . esc_url($url) . '">' . esc_html((string) $i) . '</a>';
                }
            }
            echo '</p>';
        }

        echo '</div>';
    }

    public function render_segments_page(): void {
        $this->render_table_stub('Segments', 'Dynamic segment engine with JSON rule sets (field equals, tag, date range, form submission, status).', $this->table('segments'));
    }

    public function render_campaigns_page(): void {
        $this->render_table_stub('Campaigns', 'Campaign engine with scheduling, queue processing, SMTP delivery, and tracking-ready logs.', $this->table('campaigns'));
    }

    public function render_automations_page(): void {
        $this->render_table_stub('Automations', 'Workflow engine for triggers/actions (form submit, tag added, date reached).', $this->table('automations'));
    }

    private function render_table_stub(string $title, string $description, string $table): void {
        if (!current_user_can('manage_options') && !current_user_can('manage_campaigns') && !current_user_can('manage_automations') && !current_user_can('manage_contacts')) {
            wp_die(esc_html__('Not allowed.', 'ikonic-contact-smtp-form'));
        }

        global $wpdb;
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo '<div class="wrap">';
        $this->render_marketing_tabs($this->slug_from_title($title));
        echo '<h1>' . esc_html($title) . '</h1><p>' . esc_html($description) . '</p><div class="notice notice-info"><p>Total records: ' . esc_html((string) $count) . '</p></div></div>';
    }

    private function render_contact_profile(int $contact_id): void {
        global $wpdb;

        $contact = $this->get_contact($contact_id);
        if (!$contact) {
            echo '<div class="wrap"><h1>Contact not found</h1></div>';
            return;
        }

        $meta_table = $this->table('contact_meta');
        $activity_table = $this->table('contact_activity');

        $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$meta_table} WHERE contact_id = %d ORDER BY id DESC LIMIT 100", $contact_id), ARRAY_A);
        $activities = $wpdb->get_results($wpdb->prepare("SELECT activity_type, reference_id, created_at FROM {$activity_table} WHERE contact_id = %d ORDER BY id DESC LIMIT 100", $contact_id), ARRAY_A);

        echo '<div class="wrap"><h1>Contact Profile</h1>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=ikonic-contact-contacts')) . '">&larr; Back to contacts</a></p>';
        echo '<table class="widefat striped" style="max-width:900px"><tbody>';
        foreach ($contact as $key => $value) {
            echo '<tr><th style="width:220px">' . esc_html((string) $key) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2 style="margin-top:20px">Activity Timeline</h2><table class="widefat striped"><thead><tr><th>Type</th><th>Reference</th><th>Created</th></tr></thead><tbody>';
        if (empty($activities)) {
            echo '<tr><td colspan="3">No activity yet.</td></tr>';
        } else {
            foreach ($activities as $a) {
                echo '<tr><td>' . esc_html((string) $a['activity_type']) . '</td><td>' . esc_html((string) $a['reference_id']) . '</td><td>' . esc_html((string) $a['created_at']) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        echo '<h2 style="margin-top:20px">Known Meta</h2><table class="widefat striped"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>';
        if (empty($meta)) {
            echo '<tr><td colspan="2">No meta records yet.</td></tr>';
        } else {
            foreach ($meta as $m) {
                echo '<tr><td>' . esc_html((string) $m['meta_key']) . '</td><td><code>' . esc_html((string) $m['meta_value']) . '</code></td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    private function detect_email(array $form, array $data): string {
        $candidate = '';
        $configured = isset($form['autoresponder_email_field']) ? sanitize_key((string) $form['autoresponder_email_field']) : '';

        if ($configured !== '' && !empty($data[$configured])) {
            $candidate = sanitize_email((string) $data[$configured]);
        }

        if ($candidate === '') {
            foreach ($data as $key => $value) {
                $is_email_field = strpos((string) $key, 'email') !== false;
                $v = sanitize_email((string) $value);
                if ($is_email_field && is_email($v)) {
                    $candidate = $v;
                    break;
                }
            }
        }

        return $candidate;
    }

    private function detect_field_value(array $data, array $keys): string {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string) $data[$key]) !== '') {
                return sanitize_text_field((string) $data[$key]);
            }
        }

        return '';
    }

    private function upsert_contact(string $email, array $payload): int {
        global $wpdb;

        $table = $this->table('contacts');
        $now = current_time('mysql');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table} WHERE email = %s LIMIT 1", $email), ARRAY_A);

        $data = [
            'email' => $email,
            'first_name' => sanitize_text_field((string) ($payload['first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) ($payload['last_name'] ?? '')),
            'phone' => sanitize_text_field((string) ($payload['phone'] ?? '')),
            'status' => in_array(($payload['status'] ?? 'active'), ['active', 'unsubscribed', 'bounced'], true) ? (string) $payload['status'] : 'active',
            'source_form_id' => sanitize_key((string) ($payload['source_form_id'] ?? '')),
            'updated_at' => $now,
        ];

        if ($existing && isset($existing['id'])) {
            $wpdb->update($table, $data, ['id' => (int) $existing['id']], ['%s', '%s', '%s', '%s', '%s', '%s', '%s'], ['%d']);
            return (int) $existing['id'];
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        return (int) $wpdb->insert_id;
    }

    private function save_contact_meta(int $contact_id, string $meta_key, string $meta_value): void {
        if ($meta_key === '') {
            return;
        }

        global $wpdb;
        $table = $this->table('contact_meta');

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE contact_id = %d AND meta_key = %s LIMIT 1", $contact_id, $meta_key));
        if ($existing) {
            $wpdb->update($table, ['meta_value' => $meta_value], ['id' => (int) $existing], ['%s'], ['%d']);
            return;
        }

        $wpdb->insert($table, [
            'contact_id' => $contact_id,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
        ], ['%d', '%s', '%s']);
    }

    private function log_activity(int $contact_id, string $activity_type, int $reference_id): void {
        global $wpdb;

        $table = $this->table('contact_activity');
        $wpdb->insert($table, [
            'contact_id' => $contact_id,
            'activity_type' => sanitize_key($activity_type),
            'reference_id' => $reference_id,
            'created_at' => current_time('mysql'),
        ], ['%d', '%s', '%d', '%s']);
    }

    private function get_contact(int $contact_id): ?array {
        global $wpdb;

        $table = $this->table('contacts');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $contact_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }


    private function render_marketing_tabs(string $active_page): void {
        $tabs = [
            'ikonic-contact-form-builder' => 'Forms',
            'ikonic-contact-entries' => 'Entries',
            'ikonic-contact-analytics' => 'Analytics',
            'ikonic-contact-contacts' => 'Contacts',
            'ikonic-contact-segments' => 'Segments',
            'ikonic-contact-campaigns' => 'Campaigns',
            'ikonic-contact-automations' => 'Automations',
            'ikonic-contact-smtp-settings' => 'SMTP Settings',
            'ikonic-contact-modules' => 'UI Studio & Modules',
        ];

        echo '<nav class="nav-tab-wrapper" aria-label="Ikonic Form Builder Navigation">';
        foreach ($tabs as $slug => $label) {
            $active_class = $active_page === $slug ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($active_class) . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    private function slug_from_title(string $title): string {
        $map = [
            'Segments' => 'ikonic-contact-segments',
            'Campaigns' => 'ikonic-contact-campaigns',
            'Automations' => 'ikonic-contact-automations',
        ];

        return isset($map[$title]) ? $map[$title] : 'ikonic-contact-form-builder';
    }

    private function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'icsf_' . $suffix;
    }
}
