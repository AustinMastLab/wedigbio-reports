# Routes, Chart API and Views (WeDigBio Reports)

## Public routes (`routes/web.php`)
- `/` → `EventController@index` — lists public events (`is_public = true`), ordered by `starts_at` desc
- `/events/embed?chart=total-activity|activity-by-center` → `EventController@embedChart` — embeddable single-chart page for Drupal blocks; auto-discovers current **public live** event and returns an error view for invalid chart type or when no public live event exists
- `/events/{event:slug}` → `EventController@show` — single event dashboard; 404s if `!is_public`
- `EventController::index()` uses a `Schema::hasTable('events')` guard so tests pass without migrations
## API routes (`routes/api.php`, prefix `/api/`)
All routes are scoped to `events/{event:slug}` and handled by `App\Http\Controllers\Api\ChartController`:
- `GET charts/total-activity` — cumulative weighted + raw series per transcription record
- `GET charts/hourly-activity` — hourly/minutely aggregates (from `chart_aggregates_hourly` or fallback to `transcription_records`)
- `GET charts/activity-by-center` — per-center cumulative series (from hourly aggregates or fallback)
- `GET summary` — totals (weighted, raw, center count, first/latest timestamps)
- Chart API responses are wrapped with `Spatie\ResponseCache\Middlewares\CacheResponse` in `routes/api.php`; cache profile `App\Support\ResponseCache\ArchivedEventChartCacheProfile` caches archived events only (live events bypass caching).
All chart endpoints return `{ meta: { event, generated_at, is_live, metric_mode, bucket_size }, series/summary: [...] }`.
- Public dashboard JS currently builds the cumulative "Total contributions" chart from `charts/hourly-activity` (see `renderTotalChart()` in `resources/js/pages/event-show.js`), even though `charts/total-activity` is still exposed and routed in Blade (`data-api-total`).
### ChartController internals
- **`timeColumn(Event $event)`** — Live events use `created_at` (ingestion arrival time); archived use `timestamp_utc` (source-provided timestamp). Keeps live charts accurate during an event.
- **`bucketSize(Event $event)`** — Returns `'minute'` for live events started < 60 minutes ago; `'hour'` otherwise. Exposed in `meta.bucket_size`.
- **`bucketExpr(Event $event)`** — Returns `DATE_FORMAT(col, '%Y-%m-%d %H:%i:00')` or `DATE_FORMAT(col, '%Y-%m-%d %H:00:00')` depending on bucket size.
- **Fallback** — `hourlyActivity` and `activityByCenter` fall back to querying `transcription_records` directly if `chart_aggregates_hourly` is empty (e.g., first minutes of a live event before the aggregation job has run).
## Views
- `resources/views/layouts/app.blade.php` — main layout (Bunny fonts, Tailwind, Vite guarded with `@if(file_exists(...))`, early-init dark-mode script, NSF footer)
- `resources/views/events/index.blade.php` — public event list
- `resources/views/events/show.blade.php` — event dashboard with Chart.js charts and all public UX features (see Frontend section)
- `resources/views/events/embed-chart.blade.php` / `resources/views/events/embed-chart-error.blade.php` — embeddable light-theme chart/error pages used by `/events/embed`
