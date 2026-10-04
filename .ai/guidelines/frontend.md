# Frontend (WeDigBio Reports)

- Entry points: `resources/css/app.css`, `resources/js/app.js`
- Font: Bunny-hosted **Instrument Sans** (weights 400/500/600), configured in `vite.config.js`
- Tailwind CSS v4 via `@tailwindcss/vite` plugin (no `tailwind.config.js` — config is CSS-first)
- **Dark mode**: class-based (not OS media query). `resources/css/app.css` declares `@variant dark (&:where(.dark, .dark *));`. The `.dark` class is applied to `<html>` by an early-init script in `app.blade.php` that reads `localStorage['theme']` (the same key Filament admin writes: `'dark'`, `'light'`, or `'system'`).
- **Chart.js 4** for public charting (imported via npm, bundled by Vite)
  - `resources/js/pages/event-show.js` handles event dashboard chart initialization, data fetching, and interactive features
  - `resources/js/pages/event-embed-chart.js` handles `/events/embed` chart rendering (light-only), timezone toggle, and chart-type switching based on `data-chart-type`
  - Three dashboard chart types: cumulative line chart (total activity), bar chart (hourly activity), multi-line chart (activity by center)
## Event dashboard features (`resources/js/pages/event-show.js`)
- **UTC / Local timezone toggle** — Pill toggle below the event description affects all 3 charts simultaneously. The user's browser timezone is detected via `Intl.DateTimeFormat().resolvedOptions().timeZone`. Selection is persisted in `localStorage['wedigbio.chart.timezoneMode']` (`'utc'` or `'local'`). Works on live and archived event views.
- **Theme-aware colors** — Charts read `.dark` on `<html>` at render time. Dark theme uses zinc/amber palette; light theme uses slate/amber.
- **Dynamic time granularity** — For live events started < 60 minutes ago, the API returns per-minute buckets (`meta.bucket_size = 'minute'`); after 60 minutes and for archived events it returns per-hour. Frontend requires no change — it reads whatever the API returns.
- **Single-bucket padding** — `padHourlySeries()` and `padCenterPoints()` prepend a synthetic zero-value point one interval before the first real data point so Chart.js can render a line/bar instead of a single invisible pixel.
- **Live auto-refresh** — Charts reload every 15 minutes via `setInterval` controlled by `data-reload-ms="900000"` on the dashboard root element.
- **No-data auto-reload** — When a live event has no records yet, the page reloads every 60 seconds (via `setTimeout(() => location.reload(), 60000)` in Blade).
- **Refresh notice** — "🔄 Live charts refresh every 15 minutes." appears below the timezone toggle only on live events that have data.
- **Chart labels** — "Total contributions" for the cumulative chart, "Hourly total" for the bar chart.
