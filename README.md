# WeDigBio Reports
Public statistics dashboard and administrative back-office for the [WeDigBio](https://www.wedigbio.org) citizen-science transcription events.
---
## Overview
WeDigBio Reports ingests transcription data from multiple citizen-science platforms, aggregates it by event and institution, and presents interactive charts for each biannual WeDigBio event (Spring and Fall). A Filament-powered admin panel allows authorised staff to manage events, API sources, and ingestion state.
### Key features
| Area | Details |
|---|---|
| **Public dashboard** | Event cards → per-event stats (total contributions, hourly activity, activity by centre) with light/dark theme, UTC/Local timezone toggle |
| **Live ingestion** | Polling jobs pull live transcription data from platform APIs during active events |
| **Historical import** | Artisan command imports all legacy CSV data from `shiny-server/` (2016 – present) |
| **Admin panel** | Filament 5 back-office at `/admin` — manage events, sources, users |
| **Charting** | Chart.js 4 with dynamic granularity (per-minute early in live events, per-hour otherwise), live 15-minute auto-refresh, single-bucket padding, and UTC/Local timezone toggle |
---
## Tech stack
- **PHP 8.5+** / **Laravel 13**
- **Filament 5** — admin panel, widgets, stats overview
- **MySQL 8** — primary database
- **Vite 8** + **Tailwind CSS 4** — asset pipeline
- **Chart.js 4** — public event dashboard charting (bundled via Vite, no CDN)
- **spatie/laravel-responsecache** — response caching for archived chart API endpoints
---
## Frontend charting
- Event chart logic lives in `resources/js/pages/event-show.js`.
- Chart.js is imported through Vite (npm dependency), not loaded from a CDN in Blade templates.
- Three charts per event: cumulative contributions (line), hourly total (bar), activity by centre (multi-line).
- **UTC / Local toggle** — pill toggle below event description persists selection in `localStorage['wedigbio.chart.timezoneMode']`.
- **Dark / Light theme** — public pages sync theme from Filament's `localStorage['theme']`; `.dark` class applied to `<html>` before first paint.
- **Dynamic granularity** — live events < 60 min old use per-minute buckets; all others use per-hour (determined server-side via `ChartController::bucketSize()`).
- **Live refresh** — 15-minute auto-refresh (`data-reload-ms="900000"`); 60-second auto-reload when a live event has no data yet.
- **Response caching** — chart API responses are cached only for archived events (`is_archived=true` and `is_live=false`) via `ArchivedEventChartCacheProfile`; live events bypass caching.
---
## Local development setup
### Prerequisites
- PHP 8.5+
- Composer
- Node.js + npm
- MySQL 8+
### Steps
```bash
# 1. Clone and install PHP dependencies
composer install
# 2. Copy and configure environment
cp .env.example .env
# Edit .env — set DB_*, APP_URL, etc.
# 3. Generate app key
php artisan key:generate
# 4. Run migrations (creates a clean schema)
php artisan migrate
# 5. Install JS dependencies and build assets
npm install
npm run build
# 6. Import all historical transcription data
php artisan import:historical --path="$(pwd)/shiny-server"
# 7. Start the dev server
php artisan serve
---
## Environment variables (Production — push to Parameter Store)
Critical parameters only. Non-critical defaults are managed in `config/` files.

| Key | Example | Notes |
|---|---|---|
| `APP_KEY` | (base64 encoded) | Generated via `php artisan key:generate` |
| `APP_DEBUG` | `false` | Must be false in production |
| `APP_URL` | `https://example.com` | Full public URL |
| `DB_HOST` | `db.internal.example` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_DATABASE` | `your_database_name` | Database name |
| `DB_USERNAME` | `your_database_user` | Database user |
| `DB_PASSWORD` | `…` | Database password |
| `REDIS_HOST` | `127.0.0.1` | Redis host (production-specific) |
| `REDIS_DB` | `2` | Redis database (production-specific) |
| `REDIS_CACHE_DB` | `3` | Redis cache database (production-specific) |
| `MAIL_HOST` | `smtp.gmail.com` | Mail server host |
| `MAIL_PORT` | `587` | Mail server port |
| `MAIL_ENCRYPTION` | `tls` | Mail encryption method |
| `MAIL_USERNAME` | `your-mail-account@example.com` | Mail account |
| `MAIL_PASSWORD` | `…` | Mail password |
| `MAIL_FROM_ADDRESS` | `no-reply@example.com` | From email address |
| `MAIL_FROM_NAME` | `WeDigBio Reports` | From display name |

**Note:** Config file defaults handle session driver, cache store, queue connection, logging levels, and other non-critical settings. Only override via `.env` if production needs differ.
---
## Artisan commands
| Command | Description |
|---|---|
| `php artisan import:historical --path=<dir>` | Import all historical CSVs from a `shiny-server`-style directory tree |
| `php artisan ingest:poll` | Manually trigger one polling cycle for all live events |
| `php artisan ingest:aggregate` | Rebuild hourly aggregates for all events |
| `php artisan health:queues` | Show queue/tube status, checkpoint health, failed job count |
| `php artisan schedule:run` | Run scheduled jobs (poll sources, aggregate hourly) |
| `php artisan queue:work` | Process background ingestion/aggregation jobs |
| `php artisan responsecache:clear` | Clear cached chart API responses (archived events) |
---
## Environment parameter scripts
- `push-env-params <development|production>` pushes keys from `.env.aws.<environment>` into AWS SSM Parameter Store under `/<app>/<environment>/...`.
- `remove-env-params <development|production>` deletes all SSM parameters under `/wedigbio-reports/<environment>` in batches.
- These scripts do **not** store secrets in the repository; they operate only with your local AWS CLI identity and permissions.
- Requirements: configured AWS CLI credentials; `jq` is required by `remove-env-params`.
---
## Deployment
- Deployments run through **GitHub Actions + Deployer**.
- Pushes to `main` trigger the production workflow in `.github/workflows/deploy.yml`; pushes to `development` trigger the development workflow.
- GitHub Actions builds Vite assets with `npm run build` and uploads a deployment artifact; the server does **not** build frontend assets or need `node_modules`.
- Deployer installs the release under `/data/web/wedigbio-reports/releases/<n>` and publishes the active symlink at `/data/web/wedigbio-reports/current`.
- Production Nginx/PHP-FPM should serve Laravel from:
  - `/data/web/wedigbio-reports/current/public`
- Environment files on the server are generated from AWS SSM Parameter Store during deploy via the server-side `generate-env` helper.
- Historical CSV import is **not** part of normal deploys. Run it manually when needed with:

```bash
php artisan import:historical --path=/data/web/wedigbio-reports/shiny-server
```

### Supervisor queue worker config (environment-aware)
- Template file: `ops/supervisor/wedigbio-ingest.conf.template`
- Generated file: `ops/supervisor/wedigbio-ingest.conf` (git-ignored)
- Deployer task `supervisor:generate-config` fills these placeholders per environment:
  - `{{SUPERVISOR_DIRECTORY}}`
  - `{{SUPERVISOR_LOG_FILE}}`
  - `{{APP_ENV}}`
- Environment behavior:
  - `production`/`development`: directory `/data/web/wedigbio-reports/current`, log `/data/web/wedigbio-reports/shared/storage/logs/wedigbio-ingest.log`
  - `local`: directory `/data/web/wedigbio-reports`, log `/data/web/wedigbio-reports/storage/logs/wedigbio-ingest.log`
- During deploy, `supervisor:ensure-log-dir` runs before `supervisor:reload` so `supervisorctl reread` does not fail due to a missing log directory.
- Local setup helper:

```bash
./setup-supervisor-local.sh
```

---
## Public routes
| Route | Description |
|---|---|
| `GET /` | Event index — card list of all public WeDigBio events |
| `GET /events/{slug}` | Per-event statistics page with auto-refresh live charts |
## API routes (internal, used by chart pages)
| Route | Description |
|---|---|
| `GET /api/events/{slug}/charts/total-activity` | Cumulative transcription counts over time |
| `GET /api/events/{slug}/charts/hourly-activity` | Transcriptions by bucket (per-minute or per-hour depending on event age) |
| `GET /api/events/{slug}/charts/activity-by-center` | Transcriptions broken down by institution / centre |
| `GET /api/events/{slug}/summary` | Top-level event summary (total, sources, live flag) |
All chart responses include `meta.bucket_size` (`'minute'` or `'hour'`) and `meta.is_live`.
---
## Data sources
| Slug | Platform |
|---|---|
| `digivol` | DigiVol (Australian Museum) |
| `notes-from-nature` | Notes from Nature |
| `smithsonian` | Smithsonian Transcription Center (SITC) |
| `citsciscribe` | CitSciScribe |
| `les-herbonautes` | Les Herbonautes |
| `doedat` | DoeDat |
---
## Running tests
```bash
php artisan test
```
All tests use an in-memory SQLite database and do not touch the production MySQL instance.
---
## License
Copyright (C) 2026, WeDigBio — [wedigbio@gmail.com](mailto:wedigbio@gmail.com)
This program is free software: you can redistribute it and/or modify it under the terms of the
[GNU General Public License v3](https://www.gnu.org/licenses/gpl-3.0.html) as published by the
Free Software Foundation, either version 3 of the License, or (at your option) any later version.
