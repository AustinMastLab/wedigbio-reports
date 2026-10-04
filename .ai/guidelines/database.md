# Database (WeDigBio Reports)

- Runtime `.env.example` defaults to **MySQL** (`DB_CONNECTION=mysql`, `DB_DATABASE=wedigbio_report`); local setup expects a reachable MySQL instance unless you intentionally switch `.env` to SQLite.
- Tests use **SQLite in-memory** (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml`).
- Run migrations: `php artisan migrate`
## Schema (core tables in `database/migrations/2026_06_06_*`)
| Table | Key columns |
|---|---|
| `events` | `slug` (unique, route key, auto-generated from year+season), `year`, `season` (unique pair), `starts_at`, `ends_at`, `is_public`, `is_live`, `is_archived`, `display_alias`, `notes` |
| `sources` | `slug` (unique, route key), `name`, `base_url`, `adapter_type`, `auth_type`, `auth_config` (DB text; model cast `encrypted:array`), `supports_weighting`, `weight_field` (nullable JSON field path for weighted work units), `is_active`, `notes` |
| `event_source` | pivot: `event_id`, `source_id`, `is_enabled` |
| `source_checkpoints` | `event_id`, `source_id`, `last_seen_timestamp`, `last_page_token`, `last_run_at`, `last_status`, `last_error` |
| `transcription_records` | `event_id`, `source_id`, `source_guid`, `dedupe_key` (unique — idempotency key for upserts), `center`, `project`, `description`, `timestamp_utc`, `work_unit` (decimal 10,4), `raw_count`, `payload_json` |
| `chart_aggregates_hourly` | `event_id`, `bucket_hour_utc`, `center`, `weighted_sum`, `raw_sum` |
| `chart_snapshots` | `event_id`, `snapshot_type`, `data_json`, `generated_at` |
- `database/migrations/2026_06_12_000001_add_is_live_unique_constraint.php` is currently a placeholder migration (no DB-level index/trigger created); single-live-event enforcement is done in Filament form validation (`app/Filament/Resources/Events/Schemas/EventForm.php`).
- `database/migrations/2026_06_14_*` (2026-06-14 suite) handle additional schema refinements: event `name` column dropped (replaced by auto-generated slug + optional `display_alias`), and unique constraint added on `[year, season]`.
## Queue / Cache / Session (production `.env`)
- `QUEUE_CONNECTION=beanstalkd`, `BEANSTALKD_QUEUE=wedigbio-ingest`
- `REDIS_DB=2`, `REDIS_PREFIX=wedigbio-report-`
- `CACHE_STORE=redis`, `REDIS_CACHE_DB=3`
- `SESSION_DRIVER=memcached`
