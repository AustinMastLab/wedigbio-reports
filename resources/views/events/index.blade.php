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
                @include('events.partials.index.event-card', ['event' => $event])
            @endforeach
        </div>
    @endif
@endsection
