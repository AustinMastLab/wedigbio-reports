# Project Context (WeDigBio Reports)

## Project Overview
Laravel 13 + Filament 5 admin panel application. PHP >= 8.5. `.env.example` is MySQL-first for app runtime (`DB_CONNECTION=mysql`), while tests run in-memory SQLite via `phpunit.xml`. Frontend: Vite 8 + Tailwind CSS v4.

## Key Architecture
- **Admin UI** is entirely Filament 5, mounted at `/admin` via `app/Providers/Filament/AdminPanelProvider.php`.
- Filament panel providers are registered in `bootstrap/providers.php`.
- Filament auto-discovers Resources and Pages by directory convention:
  - Resources → `app/Filament/Resources/` (namespace `App\Filament\Resources`)
  - Pages → `app/Filament/Pages/` (namespace `App\Filament\Pages`)
  - Widgets live in `app/Filament/Widgets/` (namespace `App\Filament\Widgets`) but are registered explicitly in `app/Providers/Filament/AdminPanelProvider.php`
- No custom middleware or service bindings yet; `AppServiceProvider` is empty.
- API JSON error rendering is enabled for `api/*` routes in `bootstrap/app.php`.
- Health check route is configured at `/up` in `bootstrap/app.php`.
- `shiny-server/` is a separate, year-based R/Shiny codebase (e.g. `shiny-server/2019/app.R`); it is not wired into Laravel routes or Composer scripts.
- Historical CSV import reads event directories under `shiny-server/`; it prefers `shiny-server/<year>/newdata/*.csv` and falls back to `shiny-server/<year>/*.csv` (see `HistoricalTranscriptionImporter::discoverCsvFiles()`).
- Ingestion is queue-driven via **Beanstalkd** (tube: `wedigbio-ingest`, dependency: `pda/pheanstalk`):
  - `PollSourcesJob` dispatches every minute (via scheduler with `withoutOverlapping()`), but only polls when event has `is_live=true` AND current UTC falls within `starts_at`/`ends_at`
  - `PollSourcesJob` fans out `IngestPageJob` per enabled event/source pair
  - `IngestPageJob` fetches one page, upserts idempotently via `dedupe_key`, paginates recursively, and dispatches `AggregateHourlyJob` after final page
  - `AggregateHourlyJob` also runs hourly as safety net via scheduler
- Worker process management: **Supervisor** (template: `ops/supervisor/wedigbio-ingest.conf.template`, generated: `ops/supervisor/wedigbio-ingest.conf`), runs `php artisan queue:work beanstalkd --queue=wedigbio-ingest --sleep=3 --tries=3 --timeout=120 --max-time=3600`
- Source adapter selection is centralized in `app/Ingestion/SourceAdapterManager.php` (`http_json`/`api_json`, `biospex_json`, `digivol_json`).
- Historical CSV import is handled by `app/Services/HistoricalTranscriptionImporter.php` and exposed as `import:historical` via `app/Console/Commands/ImportHistoricalCommand.php`.

## Conventions
- PSR-4 autoloading: `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`
- Tests live in `tests/Feature/` and `tests/Unit/`; base class is `Tests\TestCase`
- `app/Filament/` follows a subdirectory-per-resource pattern (see Filament Resources above); use Filament generators as a starting point, then move into the appropriate subdirectory namespace.
- API routes are registered in `bootstrap/app.php` via `api: __DIR__.'/../routes/api.php'`; all API responses for `api/*` are rendered as JSON (error handling configured in `bootstrap/app.php`)
- Ingestion adapter mapping tests read root-level fixture files via `base_path('biospex.json')` and `base_path('digivol.json')` (see `tests/Unit/Ingestion/SourceAdapterMappingTest.php`). Ensure these files are present before running that test class.
- **Polling gate pattern** — Ingestion ONLY runs when ALL of these are true:
  - Event `is_archived = false`
  - Event `is_live = true`
  - Current UTC time >= event `starts_at`
  - Current UTC time <= event `ends_at`
  - Source `is_active = true`
  - Event/Source pivot `is_enabled = true`
  - See `PollSourcesJob::handle()` for implementation
- **Deployment & validation**: see `OPS_RUNBOOK.md` for fresh-db bootstrap, seeding, import procedures, and post-deploy smoke tests; see `PROJECT_HANDOFF.md` for live-event rehearsal checklist before production
- **PHP syntax baseline**: code in `app/Services/HistoricalTranscriptionImporter.php` uses PHP 8.5 pipe expressions (`|>`), so parser/tooling compatibility must match `composer.json` (`"php": "^8.5"`).
- **Model encryption**: `auth_config` in `Source` model uses `encrypted:array` cast; sensitive source credentials are transparently encrypted at rest
- **Timestamps in UTC**: all `timestamp_utc` fields in `transcription_records` and `chart_aggregates_hourly` are stored and queried in UTC; application timezone is set to UTC in `config/app.php`
- **Idempotency**: All ingestion writes use `dedupe_key` (unique on `transcription_records`) for upserts, ensuring repeated job executions are safe
- **Live vs archived chart data**: Live events bucket by `created_at`; archived events bucket by `timestamp_utc`. See `ChartController::timeColumn()`.
- **Chart fallback**: If `chart_aggregates_hourly` is empty for an event, chart API endpoints fall back to querying `transcription_records` directly. This handles the window between first ingestion and first aggregation job run.
- **Single live event rule**: currently enforced at app level in `EventForm` (`is_live` custom validation closure); there is no DB-level unique constraint/trigger committed yet
- **Event slug generation**: slugs are auto-generated from `year` + `season` (e.g., "2026-spring" → "wedigbio-2026-spring"). The `Event::generateCanonicalSlug()` method ensures uniqueness; if a slug collision occurs, a numeric suffix is appended (e.g., "wedigbio-2026-spring-2"). Use `Event::buildCanonicalSlug()` to preview a slug without collision logic. The optional `display_alias` field allows staff to override the programmatic display name.
- **Update queries command**: `php artisan update:queries` runs one-off deploy-time migrations or data transformations (e.g., `events_slug_from_year_season` to backfill slugs after schema changes). The command is safe to run repeatedly and skips transformations already applied. Add new cases under `$updateQueries` array in `UpdateQueriesCommand.php` and implement corresponding private methods.
