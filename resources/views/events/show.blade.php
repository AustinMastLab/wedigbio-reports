@extends('layouts.app')

@section('title', $event->name . ' — WeDigBio')


@section('content')
    <div
        id="event-dashboard-root"
        data-event-slug="{{ $event->slug }}"
        data-is-live="{{ $event->is_live ? '1' : '0' }}"
        data-has-data="{{ $hasData ? '1' : '0' }}"
        data-reload-ms="900000"
        data-api-summary="{{ route('api.charts.summary', $event) }}"
        data-api-total="{{ route('api.charts.total-activity', $event) }}"
        data-api-hourly="{{ route('api.charts.hourly-activity', $event) }}"
        data-api-by-center="{{ route('api.charts.activity-by-center', $event) }}"
    >
    {{-- Event header --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-1">
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ $event->name }}</h1>
            @if ($event->is_live)
                <span class="text-sm bg-emerald-500/20 text-emerald-700 px-2 py-0.5 rounded-full font-medium dark:text-emerald-300">Live</span>
            @endif
        </div>
        <p class="text-slate-600 text-sm dark:text-gray-300">
            {{ $event->starts_at->format('F j, Y') }} &ndash; {{ $event->ends_at->format('F j, Y') }}
        </p>
        @if ($event->notes && ! \Illuminate\Support\Str::startsWith($event->notes, 'Imported from legacy CSVs'))
            <p class="text-xs text-slate-500 mt-1 dark:text-gray-400">{{ $event->notes }}</p>
        @endif
        @if ($hasData)
            <div class="mt-3 flex items-center gap-2 text-xs text-slate-600 dark:text-gray-300">
                <span id="chart-timezone-label-utc" class="font-semibold text-amber-700 dark:text-amber-300">UTC</span>
                <label class="relative inline-flex items-center cursor-pointer" aria-label="Toggle chart timezone">
                    <input id="chart-timezone-toggle" type="checkbox" class="sr-only peer" />
                    <span class="w-11 h-6 bg-slate-400 rounded-full peer peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-500/40 peer-checked:bg-amber-600 transition-colors dark:bg-gray-700"></span>
                    <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform peer-checked:translate-x-5"></span>
                </label>
                <span id="chart-timezone-label-local" class="text-slate-500 dark:text-gray-400">Local</span>
            </div>
            @if ($event->is_live)
                <p class="mt-1 text-xs text-slate-500 dark:text-gray-400">&#x1F504; Live charts refresh every 15&nbsp;minutes.</p>
            @endif
        @endif
    </div>

    @if (! $hasData)
        <div class="card-dark border-amber-400/40 p-6 text-amber-800 dark:text-amber-200">
            <h2 class="text-base font-semibold mb-2">No transcription data is available for this event yet.</h2>
            <p class="text-sm text-amber-700/90 dark:text-amber-100/90">This year is still listed for historical completeness, but chart data was not available from source feeds during import.</p>
            @if ($event->is_live)
                <p class="text-xs mt-3 text-amber-600 dark:text-amber-300/70">This page will automatically check for new data every 60 seconds.</p>
            @endif
        </div>
        @if ($event->is_live)
            <script>setTimeout(() => location.reload(), 60000);</script>
        @endif
    @else
        {{-- Summary cards --}}
        <div id="summary-cards" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-10">
            <div class="card-dark text-center">
                <div id="card-weighted" class="text-2xl font-bold text-amber-600">&mdash;</div>
                <div class="text-xs text-slate-500 mt-1 dark:text-gray-400">Total Contributions</div>
            </div>
            <div class="card-dark text-center">
                <div id="card-raw" class="text-2xl font-bold text-slate-900 dark:text-gray-200">&mdash;</div>
                <div class="text-xs text-slate-500 mt-1 dark:text-gray-400">Total Records</div>
            </div>
            <div class="card-dark text-center">
                <div id="card-centers" class="text-2xl font-bold text-slate-900 dark:text-gray-200">&mdash;</div>
                <div class="text-xs text-slate-500 mt-1 dark:text-gray-400">Centers Reporting</div>
            </div>
            <div class="card-dark text-center">
                <div id="card-live" class="text-2xl font-bold {{ $event->is_live ? 'text-green-600' : 'text-slate-500 dark:text-gray-400' }}">
                    {{ $event->is_live ? 'Live' : 'Archived' }}
                </div>
                <div class="text-xs text-slate-500 mt-1 dark:text-gray-400">Status</div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="space-y-8">
            <div class="card-dark p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4 dark:text-gray-100">Total Transcription Activity</h2>
                <div style="height: 300px;">
                    <canvas id="chart-total" style="width: 100%; height: 100%;"></canvas>
                </div>
            </div>

            <div class="card-dark p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4 dark:text-gray-100">Activity per Hour</h2>
                <div style="height: 300px;">
                    <canvas id="chart-hourly" style="width: 100%; height: 100%;"></canvas>
                </div>
            </div>

            <div class="card-dark p-6">
                <h2 class="text-base font-semibold text-slate-900 mb-4 dark:text-gray-100">Activity by Center</h2>
                <div style="height: 500px;">
                    <canvas id="chart-by-center" style="width: 100%; height: 100%;"></canvas>
                </div>
            </div>
        </div>
    @endif
    </div>
@endsection
