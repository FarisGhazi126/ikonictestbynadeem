<?php

if (!defined('ABSPATH')) {
    exit;
}

class ICSF_Analytics {
    private ICSF_Plugin $plugin;

    public function __construct(ICSF_Plugin $plugin) {
        $this->plugin = $plugin;
    }

    public function log_submission(string $form_id, array $fields, string $status): void {
        $this->plugin->add_log([
            'form_id' => $form_id,
            'created_at' => current_time('mysql'),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'fields' => $fields,
            'status' => $status,
        ]);
    }

    public function query_logs(string $from = '', string $to = ''): array {
        $logs = $this->plugin->get_logs();
        if ($from === '' && $to === '') {
            return $logs;
        }

        $from_ts = $from ? strtotime($from . ' 00:00:00') : null;
        $to_ts = $to ? strtotime($to . ' 23:59:59') : null;

        return array_values(array_filter($logs, static function ($log) use ($from_ts, $to_ts) {
            $ts = strtotime((string) ($log['created_at'] ?? ''));
            if (!$ts) {
                return false;
            }
            if ($from_ts && $ts < $from_ts) {
                return false;
            }
            if ($to_ts && $ts > $to_ts) {
                return false;
            }
            return true;
        }));
    }

    public function build_metrics(array $logs): array {
        $total = count($logs);
        $success = 0;
        $failed = 0;
        $by_form = [];
        $by_day = [];

        foreach ($logs as $log) {
            $status = $log['status'] ?? 'success';
            if ($status === 'success') {
                $success++;
            } else {
                $failed++;
            }

            $form_id = (string) ($log['form_id'] ?? 'unknown');
            $by_form[$form_id] = ($by_form[$form_id] ?? 0) + 1;

            $day = substr((string) ($log['created_at'] ?? ''), 0, 10);
            if ($day !== '') {
                $by_day[$day] = ($by_day[$day] ?? 0) + 1;
            }
        }

        ksort($by_day);
        arsort($by_form);

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'by_form' => $by_form,
            'by_day' => $by_day,
        ];
    }
}
