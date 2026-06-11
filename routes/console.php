<?php

/*
Copyright (C) 2026 - $today.year, WeDigBio
wedigbio@gmail.com
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundaation, either version 3 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

use App\Jobs\AggregateHourlyJob;
use App\Jobs\PollSourcesJob;
use App\Models\Event;
use App\Models\SourceCheckpoint;
use App\Services\HistoricalTranscriptionImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ingest:poll', function () {
    PollSourcesJob::dispatch();
    $this->info('Dispatched PollSourcesJob');
})->purpose('Dispatch polling for all enabled event/source pairs');

Artisan::command('ingest:aggregate', function () {
    Event::where('is_archived', false)->each(fn ($event) => AggregateHourlyJob::dispatch($event->id));
    $this->info('Dispatched AggregateHourlyJob for all active events');
})->purpose('Rebuild hourly aggregates for all active events');

Artisan::command('import:historical {event? : Optional event folder slug to import} {--path= : Base path containing year folders}', function () {
    $importer = app(HistoricalTranscriptionImporter::class);
    $basePath = $this->option('path') ?: base_path('shiny-server');
    $event = $this->argument('event');

    $stats = $importer->import($basePath, $event ?: null);

    $this->info(sprintf(
        'Imported %d event(s), %d file(s), %d row(s) into %d transcription record(s).',
        $stats['events'],
        $stats['files'],
        $stats['rows'],
        $stats['records'],
    ));
})->purpose('Import historical CSV event data into the database');

Artisan::command('health:queues', function () {
    $connection = (string) config('queue.default');
    $queueName = match ($connection) {
        'beanstalkd' => (string) config('queue.connections.beanstalkd.queue'),
        'database' => (string) config('queue.connections.database.queue'),
        'redis' => (string) config('queue.connections.redis.queue'),
        default => 'n/a',
    };

    $failedTable = (string) config('queue.failed.table', 'failed_jobs');
    $failedJobs = DB::table($failedTable)->count();

    $latestCheckpoint = SourceCheckpoint::query()
        ->with(['event:id,slug', 'source:id,slug'])
        ->orderByDesc('last_run_at')
        ->first();

    $latestPair = $latestCheckpoint
        ? sprintf('%s / %s', $latestCheckpoint->event?->slug ?? 'unknown-event', $latestCheckpoint->source?->slug ?? 'unknown-source')
        : 'none';

    $latestStatus = $latestCheckpoint?->last_status ?? 'none';
    $latestRunAt = $latestCheckpoint?->last_run_at?->toDateTimeString() ?? 'never';
    $errorCheckpoints = SourceCheckpoint::query()->where('last_status', 'error')->count();

    $this->table(['Metric', 'Value'], [
        ['Queue Connection', $connection],
        ['Queue Name/Tube', $queueName],
        ['Failed Jobs', number_format($failedJobs)],
        ['Checkpoints in Error', number_format($errorCheckpoints)],
        ['Latest Checkpoint Pair', $latestPair],
        ['Latest Checkpoint Status', $latestStatus],
        ['Latest Checkpoint Run', $latestRunAt],
    ]);
})->purpose('Show queue backend/tube and ingestion checkpoint health');

// Poll every minute (jobs fan out per event/source pair)
Schedule::job(new PollSourcesJob)->everyMinute()->withoutOverlapping();

// Hourly safety net — re-aggregate all active events even if ingestion had no new pages
Schedule::command('ingest:aggregate')->hourly()->withoutOverlapping();
