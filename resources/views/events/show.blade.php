@extends('layouts.app')

@section('title', $event->name . ' — WeDigBio')


@section('content')
    <div
        id="event-dashboard-root"
        data-event-slug="{{ $event->slug }}"
        data-is-live="{{ $event->is_live ? '1' : '0' }}"
        data-has-data="{{ $hasData ? '1' : '0' }}"
        data-reload-ms="{{ (int) config('wedigbio.refresh.live_chart_ms', 900000) }}"
        data-no-data-reload-ms="{{ (int) config('wedigbio.refresh.live_no_data_retry_ms', 60000) }}"
        data-api-summary="{{ route('api.charts.summary', $event) }}"
        data-api-total="{{ route('api.charts.total-activity', $event) }}"
        data-api-hourly="{{ route('api.charts.hourly-activity', $event) }}"
        data-api-by-center="{{ route('api.charts.activity-by-center', $event) }}"
    >
    @include('events.partials.show.header', ['event' => $event, 'hasData' => $hasData])

    @if (! $hasData)
        @include('events.partials.show.no-data-state', ['event' => $event])
    @else
        {{-- Summary cards --}}
        <div id="summary-cards" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-10">
            @include('events.partials.dashboard.summary-card', [
                'valueId' => 'card-weighted',
                'valueClass' => 'text-amber-600',
                'label' => 'Total Contributions',
            ])
            @include('events.partials.dashboard.summary-card', [
                'valueId' => 'card-raw',
                'valueClass' => 'text-slate-900 dark:text-gray-200',
                'label' => 'Total Records',
            ])
            @include('events.partials.dashboard.summary-card', [
                'valueId' => 'card-centers',
                'valueClass' => 'text-slate-900 dark:text-gray-200',
                'label' => 'Centers Reporting',
            ])
            @include('events.partials.dashboard.summary-card', [
                'valueId' => 'card-live',
                'valueClass' => $event->is_live ? 'text-green-600' : 'text-slate-500 dark:text-gray-400',
                'label' => 'Status',
                'value' => $event->is_live ? 'Live' : 'Archived',
            ])
        </div>

        {{-- Charts --}}
        <div class="space-y-8">
            @include('events.partials.dashboard.chart-card', [
                'title' => 'Total Transcription Activity',
                'canvasId' => 'chart-total',
                'heightClass' => 'h-[300px]',
            ])

            @include('events.partials.dashboard.chart-card', [
                'title' => 'Activity per Hour',
                'canvasId' => 'chart-hourly',
                'heightClass' => 'h-[300px]',
            ])

            @include('events.partials.dashboard.chart-card', [
                'title' => 'Activity by Center',
                'canvasId' => 'chart-by-center',
                'heightClass' => 'h-[500px]',
            ])
        </div>
    @endif
    </div>
@endsection
