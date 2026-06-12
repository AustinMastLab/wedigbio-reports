@php
    $eventTitle = trim('WeDigBio ' . $event->year . ' ' . ucfirst((string) ($event->season ?? '')));
@endphp

<div class="card-dark hover:border-amber-400/40">
    <div class="mb-1 flex items-center justify-between">
        <span class="text-lg font-semibold text-slate-900 dark:text-white">{{ $eventTitle }}</span>
        <div class="flex items-center gap-2">
            @if (($event->transcription_records_count ?? null) === 0)
                @include('events.partials.ui.status-badge', [
                    'text' => 'No data',
                    'class' => 'bg-amber-500/20 text-amber-700 dark:text-amber-300',
                ])
            @endif
            @if ($event->is_live)
                @include('events.partials.ui.status-badge', [
                    'text' => 'Live',
                    'class' => 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300',
                ])
            @endif
        </div>
    </div>
    <div class="text-sm text-slate-700 dark:text-gray-300">
        {{ $event->starts_at->format('M j, Y') }}
        &ndash;
        {{ $event->ends_at->format('M j, Y') }}
    </div>
    @if ($event->notes && ! \Illuminate\Support\Str::startsWith($event->notes, 'Imported from legacy CSVs'))
        <p class="mt-2 text-xs text-slate-500 dark:text-gray-400">{{ $event->notes }}</p>
    @endif
    <div class="mt-4">
        <a href="{{ route('events.show', $event) }}" class="btn-primary">View Stats</a>
    </div>
</div>

