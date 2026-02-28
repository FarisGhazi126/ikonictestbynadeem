# ICSF Marketing Engine Schema & Hooks

## Database Tables (custom, no postmeta)
All tables are created with prefix: `{$wpdb->prefix}icsf_`.

- `contacts`: canonical contact records (unique email)
- `contact_meta`: arbitrary contact key/value fields
- `tags`: tag dictionary
- `contact_tags`: pivot table between contacts and tags
- `contact_activity`: timeline events (submission, send, etc.)
- `segments`: dynamic segment definitions (`rules_json`)
- `campaigns`: campaign definitions (draft/scheduled/sent)
- `campaign_logs`: per-contact delivery/open/click outcomes
- `automations`: automation definitions with triggers/workflow JSON
- `automation_logs`: execution log records
- `email_queue`: background queue for outbound campaign/automation emails

## Performance Notes
- Indexed columns for status/date/filter fields.
- Queue processing runs in batches (`LIMIT 25`) via WP-Cron.
- Contacts list supports pagination and REST endpoint pagination.
- Prepared statements (`$wpdb->prepare`) are used for dynamic reads.

## Hooks Added
### Action Hooks consumed
- `icsf_after_submission` → updates/creates contacts + activity timeline
- `icsf_process_email_queue` (cron) → sends queued emails in background

### Cron additions
- Custom interval: `icsf_five_minutes` (300 seconds)
- Scheduled event: `icsf_process_email_queue`

## REST Routes Added
Namespace: `icsf/v1`

- `GET /contacts`
- `GET /contacts/{id}`
- `POST /contacts/upsert`
- `POST /campaigns/queue`

## Capabilities Added
- `manage_contacts`
- `manage_campaigns`
- `manage_automations`
- `view_analytics`

## Roles
- Administrator: all marketing capabilities
- Marketing Manager: full marketing capabilities
- Editor: `manage_contacts`, `view_analytics`
