# Deployment

- This project deploys with GitHub Actions (`.github/workflows/deploy.yml`) and `deployphp` (`deploy.php`, `deploy/custom.php`). Pushes to `main` deploy to production. The development server is deployed only by running the workflow manually (Actions → Build and Deploy → Run workflow); there is no `development` branch, and local work is merged into `main` after testing. Add `[skip deploy]` or `[no deploy]` to a commit message to push to `main` without deploying.
- Assets are built in CI and downloaded during deploy (`deploy:ci-artifacts`); do not expect server-side frontend builds.
- The deploy runs migrations, `update:queries`, cache rebuilds, renders the Supervisor config from `resources/supervisor/` into shared `storage/app/supervisor/` (`app:deploy-files`), and restarts queue workers.
- There is no OPcache reset step. The site's nginx config passes PHP the real release path (`fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;` and `fastcgi_param DOCUMENT_ROOT $realpath_root;`), so each release is a new set of files to OPcache and is served as soon as `current` switches.
- See `OPS_RUNBOOK.md` and `PROJECT_HANDOFF.md` for deployment procedures and live-event validation.
