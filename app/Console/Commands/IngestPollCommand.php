<?php

namespace App\Console\Commands;

use App\Jobs\PollSourcesJob;
use Illuminate\Console\Command;

class IngestPollCommand extends Command
{
    protected $signature = 'ingest:poll';

    protected $description = 'Dispatch polling for all enabled event/source pairs';

    public function handle(): int
    {
        PollSourcesJob::dispatch();
        $this->info('Dispatched PollSourcesJob');

        return self::SUCCESS;
    }
}

