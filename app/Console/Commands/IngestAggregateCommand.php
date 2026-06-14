<?php

namespace App\Console\Commands;

use App\Jobs\AggregateHourlyJob;
use App\Models\Event;
use Illuminate\Console\Command;

class IngestAggregateCommand extends Command
{
    protected $signature = 'ingest:aggregate';

    protected $description = 'Rebuild hourly aggregates for all active events';

    public function handle(): int
    {
        Event::query()
            ->where('is_archived', false)
            ->each(fn (Event $event) => AggregateHourlyJob::dispatch($event->id));

        $this->info('Dispatched AggregateHourlyJob for all active events');

        return self::SUCCESS;
    }
}

