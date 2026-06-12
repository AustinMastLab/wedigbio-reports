{{-- Event header --}}
<div class="mb-8">
    <div class="mb-1 flex items-center gap-3">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ $event->name }}</h1>
        @if ($event->is_live)
            @include('events.partials.ui.status-badge', [
                'text' => 'Live',
                'class' => 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300',
            ])
        @endif
    </div>
    <p class="text-sm text-slate-600 dark:text-gray-300">
        {{ $event->starts_at->format('F j, Y') }} &ndash; {{ $event->ends_at->format('F j, Y') }}
    </p>
    @if ($event->notes && ! \Illuminate\Support\Str::startsWith($event->notes, 'Imported from legacy CSVs'))
        <p class="mt-1 text-xs text-slate-500 dark:text-gray-400">{{ $event->notes }}</p>
    @endif
    @if ($hasData)
        @include('events.partials.timezone-toggle', [
            'containerClass' => 'mt-3 flex items-center gap-2',
            'labelTextClass' => 'text-xs text-slate-600 dark:text-gray-300',
            'utcId' => 'chart-timezone-label-utc',
            'toggleId' => 'chart-timezone-toggle',
            'localId' => 'chart-timezone-label-local',
        ])
        @if ($event->is_live)
            <p class="mt-1 text-xs text-slate-500 dark:text-gray-400">&#x1F504; Live charts refresh every {{ max(1, (int) round(config('wedigbio.refresh.live_chart_ms', 900000) / 60000)) }} minutes.</p>
        @endif
    @endif
</div>
