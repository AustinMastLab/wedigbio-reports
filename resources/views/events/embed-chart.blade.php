<!DOCTYPE html>
<html lang="en">
<head>
    @include('events.partials.embed-head', ['title' => $chartTitle . ' — ' . $event->name])
</head>
<body class="antialiased bg-slate-50 text-slate-900">

<div id="embed-chart-root"
    data-event-slug="{{ $event->slug }}"
    data-event-name="{{ $event->name }}"
    data-chart-type="{{ $chartType }}"
    data-is-live="{{ $event->is_live ? '1' : '0' }}"
    data-has-data="{{ $hasData ? '1' : '0' }}"
    data-starts-at="{{ $event->starts_at->toIso8601String() }}"
    data-api-hourly="{{ route('api.charts.hourly-activity', $event) }}"
    data-api-by-center="{{ route('api.charts.activity-by-center', $event) }}"
    data-reload-ms="{{ (int) config('wedigbio.refresh.live_chart_ms', 900000) }}"
    data-no-data-reload-ms="{{ (int) config('wedigbio.refresh.live_no_data_retry_ms', 60000) }}"
    class="min-h-screen p-6">

    <div class="mx-auto max-w-full">
        @include('events.partials.embed.header', ['title' => $chartTitle])

        @include('events.partials.embed.countdown-message')

        @include('events.partials.embed.no-data-message')

        <!-- Chart container -->
        <div id="chart-container"
            class="hidden rounded-lg border border-slate-200 bg-white p-4">
            <div id="chart-wrapper" class="relative h-[400px]">
                <canvas id="embed-chart" class="h-full w-full"></canvas>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                @include('events.partials.timezone-toggle', [
                    'containerClass' => 'flex items-center gap-2',
                    'labelTextClass' => 'text-xs text-slate-400',
                    'utcClass' => 'font-semibold text-amber-700',
                    'separatorClass' => 'text-slate-500',
                    'localClass' => 'text-slate-500',
                    'labelClass' => 'relative inline-flex cursor-pointer items-center',
                    'inputClass' => 'peer h-4 w-4 cursor-pointer',
                    'trackClass' => 'hidden',
                    'thumbClass' => 'hidden',
                    'showSeparator' => true,
                    'utcId' => 'tz-utc',
                    'toggleId' => 'timezone-toggle',
                    'localId' => 'tz-local',
                ])
                @if ($event->is_live)
                    <span class="ml-auto text-slate-500">Refreshes every {{ max(1, (int) round(config('wedigbio.refresh.live_chart_ms', 900000) / 60000)) }} minutes</span>
                @endif
            </div>
        </div>
    </div>

</div>


</body>
</html>
