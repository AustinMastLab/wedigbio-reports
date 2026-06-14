<?php

namespace App\Console\Commands;

use App\Services\HistoricalTranscriptionImporter;
use Illuminate\Console\Command;

class ImportHistoricalCommand extends Command
{
    protected $signature = 'import:historical {event? : Optional event folder slug to import} {--path= : Base path containing year folders}';

    protected $description = 'Import historical CSV event data into the database';

    public function handle(HistoricalTranscriptionImporter $importer): int
    {
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

        return self::SUCCESS;
    }
}

