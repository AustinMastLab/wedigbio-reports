# Filament Panel (WeDigBio Reports)

Defined in `app/Providers/Filament/AdminPanelProvider.php`:
- Panel ID: `admin`, path: `/admin`
- Primary color: Amber
- Login page enabled; default widgets: `AccountWidget`, `FilamentInfoWidget`
- Theme preference stored in `localStorage['theme']` by Filament JS (`'dark'`, `'light'`, `'system'`). This is read by the public layout's early-init script to keep public pages in sync.
## Filament Resources (existing)
Resources are grouped into **subdirectories** rather than placed directly in `app/Filament/Resources/`. Each resource group has its own namespace folder containing the resource class, `Pages/`, `Schemas/`, and `Tables/` subfolders:
- `app/Filament/Resources/Events/EventResource.php` (`navigationGroup = 'WeDigBio'`) — CRUD for `events` table
  - Event form includes helper DateTimePickers for **Tonga local time** (`tonga_starts_at`, `tonga_ends_at`) that auto-convert from `Pacific/Tongatapu` to UTC and populate the actual `starts_at`/`ends_at` fields. Pickers use `->native(false)->locale('de')->displayFormat('d.m.Y H:i')` to show a 24-hour JS date picker.
  - Participating Sources dropdown is filtered to `is_active = true` sources only (inactive sources are hidden).
  - Event view (infolist) shows Start and End times in both UTC and EST (America/New_York).
- `app/Filament/Resources/Sources/SourceResource.php` (`navigationGroup = 'WeDigBio'`) — CRUD for `sources` table
  - Source form reveals `weight_field` only when `supports_weighting = true`; use dotted JSON paths such as `discretionaryState.workUnit` or `meta.weight`. `HttpJsonSourceAdapter`, `BiospexJsonSourceAdapter`, and `DigivolJsonSourceAdapter` resolve that path with `Arr::get(...)` and otherwise fall back to `work_unit` / `workUnit`.
Form logic lives in `Schemas/EventForm.php` / `Schemas/SourceForm.php`; table config in `Tables/EventsTable.php` / `Tables/SourcesTable.php`.
When generating new resources, follow this subdirectory pattern: `app/Filament/Resources/<GroupName>/<GroupName>Resource.php`.
- Custom Filament page: `app/Filament/Pages/Videos.php` — auto-discovered page in navigation group `WeDigBio` (sort 30) rendering `resources/views/filament/pages/videos.blade.php`; it serves `storage/app/public/wedigbio.mp4` and `storage/app/public/wedigbio-reports.mp4` via `asset('storage/...')`, so `php artisan storage:link` is required when testing the page locally.
## Filament Widgets (existing)
Dashboard widgets displayed on the Filament admin home page (configured in `AdminPanelProvider`):
- `app/Filament/Widgets/AdminOverviewStats.php` — top-level stats cards (event counts, source counts, etc.)
- `app/Filament/Widgets/TransactionsByEventChart.php` — chart visualizing transcription records by event
- `app/Filament/Widgets/TransactionsByYearWidget.php` exists but is not currently registered in `AdminPanelProvider`.
