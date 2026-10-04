# Deployment

- This project deploys with GitHub Actions (`.github/workflows/deploy.yml`) and `deployphp` (`deploy.php`, `deploy/custom.php`). Pushes to `main` deploy to production and pushes to `development` deploy to the development server.
- Assets are built in CI and downloaded during deploy (`deploy:ci-artifacts`); do not expect server-side frontend builds.
- The deploy runs migrations, `update:queries`, cache rebuilds, regenerates the Supervisor config from `ops/supervisor/*.conf.template`, and restarts queue workers.
- See `OPS_RUNBOOK.md` and `PROJECT_HANDOFF.md` for deployment procedures and live-event validation.
