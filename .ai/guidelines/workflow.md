# Developer Workflows (WeDigBio Reports)

## First-time setup
```bash
composer setup
# Runs: composer install, .env copy, key:generate, migrate --force, npm install --ignore-scripts, npm run build
```
## Local development (all services)
```bash
composer dev
# Concurrently starts: php artisan serve, queue:listen, pail (log viewer), vite HMR
```
- No R/Shiny runtime command is defined in `composer.json`; run any Shiny scripts manually from the target `shiny-server/<year>/` directory.
- `laravel/pao` is installed in `require-dev`; if agent tooling commands are missing locally, run `composer install` to restore dev dependencies.
- `vendor/bin/push-env-params` / `vendor/bin/remove-env-params` (from `austinmastlab/deployer-recipes`) manage AWS SSM parameters for `.env.aws.<environment>` values; pass the app name first, e.g. `vendor/bin/push-env-params wedigbio-reports development` (expects AWS CLI and `jq`).
## Ingestion and import commands
```bash
php artisan ingest:poll
php artisan ingest:aggregate
php artisan import:historical {event?} {--path=/absolute/path/to/shiny-server}
php artisan health:queues
php artisan update:queries
```
- Command classes live in `app/Console/Commands/` and are auto-discovered via `withCommands()` in `bootstrap/app.php`; scheduler wiring also lives in `bootstrap/app.php` (`routes/console.php` is currently documentation-only).
- Scheduled jobs: `PollSourcesJob` every minute, `ingest:aggregate` hourly.
- `health:queues` displays queue backend, tube name, failed job count, checkpoint health, and latest ingestion status.
- `update:queries` runs one-off schema/data migrations during deploys (e.g., event slug backfill); safe to re-run (skips completed transformations).
## Run tests
```bash
composer test
# Clears config cache, then runs php artisan test (PHPUnit 12)
```
## Code style (Pint)
```bash
vendor/bin/pint
```
## After adding/changing Filament resources
```bash
php artisan filament:upgrade   # also runs automatically on composer dump-autoload
```
