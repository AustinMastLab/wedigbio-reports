<?php

namespace App\Console\Commands;

use App\Models\SourceCheckpoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HealthQueuesCommand extends Command
{
    protected $signature = 'health:queues';

    protected $description = 'Show queue backend/tube and ingestion checkpoint health';

    public function handle(): int
    {
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

        return self::SUCCESS;
    }
}

