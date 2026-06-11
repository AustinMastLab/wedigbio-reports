@extends('layouts.app')

@section('title', 'WeDigBio Events')

@section('content')
    <h1 class="text-3xl font-bold mb-2 text-slate-900 dark:text-white">WeDigBio Events</h1>
    <p class="text-slate-600 mb-8 dark:text-gray-400">Select an event to view its transcription activity dashboard.</p>

    @if ($events->isEmpty())
        <p class="text-slate-600 dark:text-gray-400">No public events available yet.</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($events as $event)
                @php
                    $eventTitle = trim('WeDigBio ' . $event->year . ' ' . ucfirst((string) ($event->season ?? '')));
                @endphp
                <div class="card-dark hover:border-amber-400/40">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-lg font-semibold text-slate-900 dark:text-white">{{ $eventTitle }}</span>
                        <div class="flex items-center gap-2">
                            @if (($event->transcription_records_count ?? null) === 0)
                                <span class="text-xs bg-amber-500/20 text-amber-700 px-2 py-0.5 rounded-full font-medium dark:text-amber-300">No data</span>
                            @endif
                            @if ($event->is_live)
                                <span class="text-xs bg-emerald-500/20 text-emerald-700 px-2 py-0.5 rounded-full font-medium dark:text-emerald-300">Live</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-sm text-slate-700 dark:text-gray-300">
                        {{ $event->starts_at->format('M j, Y') }}
                        &ndash;
                        {{ $event->ends_at->format('M j, Y') }}
                    </div>
                    @if ($event->notes && ! \Illuminate\Support\Str::startsWith($event->notes, 'Imported from legacy CSVs'))
                        <p class="text-xs text-slate-500 mt-2 dark:text-gray-400">{{ $event->notes }}</p>
                    @endif
                    <div class="mt-4">
                        <a href="{{ route('events.show', $event) }}" class="btn-primary">View Stats</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
