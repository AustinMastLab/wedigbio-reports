# Ingestion, Queues and Scheduler (WeDigBio Reports)

## Ingestion Adapter Notes
### DigiVol (`digivol_json`)
`app/Ingestion/Adapters/DigivolJsonSourceAdapter.php` always sets `center = $source->name` (fallback `$source->slug`). It does **not** read institution, project, or other fields from the JSON payload for center identification. This ensures all DigiVol contributions are grouped under one center per source record, regardless of JSON structure variation.
## Queue and Scheduler Operations
- **Scheduler entry point**: `* * * * * cd /data/web/wedigbio-reports && php artisan schedule:run >> /dev/null 2>&1` (run every minute via cron)
- **Queue lifecycle**:
  1. Scheduler executes `PollSourcesJob` every minute
  2. Job queries active, live events within start/end UTC bounds
  3. For each enabled source, dispatches `IngestPageJob` to Beanstalkd queue
  4. Workers (Supervisor-managed) consume jobs and execute ingestion/aggregation
  5. All checkpoint writes are tracked in `source_checkpoints` table for visibility
- **Queue/Worker Deployment** (Supervisor pattern):
  ```bash
  sudo cp ops/supervisor/wedigbio-ingest.conf /etc/supervisor/conf.d/
  sudo supervisorctl reread && sudo supervisorctl update
  sudo supervisorctl status wedigbio-ingest:*
  ```
- **Monitoring commands**:
  - `php artisan health:queues` — queue backend, tube, failed count, latest checkpoint
  - `php artisan queue:restart` — restart all workers gracefully
  - `php artisan ingest:poll` — manually trigger polling cycle
See `OPS_RUNBOOK.md` and `PROJECT_HANDOFF.md` for deployment procedures and live-event validation checks.
