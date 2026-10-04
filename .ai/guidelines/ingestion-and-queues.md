# Ingestion, Queues and Scheduler (WeDigBio Reports)

## Ingestion Adapter Notes
### DigiVol (`digivol_json`)
`app/Ingestion/Adapters/DigivolJsonSourceAdapter.php` always sets `center = $source->name` (fallback `$source->slug`). It does **not** read institution, project, or other fields from the JSON payload for center identification. This ensures all DigiVol contributions are grouped under one center per source record, regardless of JSON structure variation.
## Queue and Scheduler Operations
- **Scheduler entry point** (server crontab, every minute): `* * * * * /usr/bin/php /data/web/wedigbio-reports/current/artisan schedule:run >> /dev/null 2>&1`. Call `artisan` by its full path under `current/`; `php artisan schedule:list` shows `PollSourcesJob` every minute and `ingest:aggregate` hourly.
- **Queue lifecycle**:
  1. Scheduler executes `PollSourcesJob` every minute
  2. Job queries active, live events within start/end UTC bounds
  3. For each enabled source, dispatches `IngestPageJob` to Beanstalkd queue
  4. Workers (Supervisor-managed) consume jobs and execute ingestion/aggregation
  5. All checkpoint writes are tracked in `source_checkpoints` table for visibility
- **Queue/Worker Deployment** (Supervisor pattern): templates live in `resources/supervisor/`; each deploy runs `php artisan app:deploy-files --current-path=/data/web/wedigbio-reports/current`, which renders them into shared `storage/app/supervisor/`. The server's Supervisor `[include]` reads `/data/web/wedigbio-reports/current/storage/app/supervisor/*.conf`, then the deploy runs `supervisorctl reread/update` and `queue:restart`. Check workers with:
  ```bash
  sudo supervisorctl status wedigbio-ingest:*
  ```
- **Monitoring commands**:
  - `php artisan health:queues` — queue backend, tube, failed count, latest checkpoint
  - `php artisan queue:restart` — restart all workers gracefully
  - `php artisan ingest:poll` — manually trigger polling cycle
See `OPS_RUNBOOK.md` and `PROJECT_HANDOFF.md` for deployment procedures and live-event validation checks.
