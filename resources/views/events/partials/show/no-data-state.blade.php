<div class="card-dark border-amber-400/40 p-6 text-amber-800 dark:text-amber-200">
    <h2 class="mb-2 text-base font-semibold">No transcription data is available for this event yet.</h2>
    @if ($event->is_live)
        @if (now()->lt($event->starts_at))
            <p
                id="live-event-countdown-message"
                data-starts-at="{{ $event->starts_at->toIso8601String() }}"
                class="text-sm text-amber-700/90 dark:text-amber-100/90"
            >
                This live event will start in <strong id="live-event-countdown-hours">--</strong>h <strong id="live-event-countdown-minutes">--</strong>m.
            </p>
        @else
            <p id="live-event-started-message" class="text-sm text-amber-700/90 dark:text-amber-100/90">This live event has started, but no transcription records have arrived from enabled source feeds yet.</p>
        @endif
    @else
        <p class="text-sm text-amber-700/90 dark:text-amber-100/90">This year is still listed for historical completeness, but chart data was not available from source feeds during import.</p>
    @endif
    @if ($event->is_live)
        <p id="live-event-refresh-note" @class([
            'mt-3 text-xs text-amber-600 dark:text-amber-300/70',
            'hidden' => now()->lt($event->starts_at),
        ])>This page will automatically check for new data every {{ max(1, (int) round(config('wedigbio.refresh.live_no_data_retry_ms', 60000) / 1000)) }} seconds.</p>
    @endif
</div>

