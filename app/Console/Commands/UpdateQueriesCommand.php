<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class UpdateQueriesCommand extends Command
{
    protected $signature = 'update:queries';

    protected $description = 'Run one-off deploy update queries (safe to leave empty)';

    public function handle(): int
    {
        // Keep this list easy to comment out later during deploy tuning.
        $updateQueries = [
            'events_slug_from_year_season',
        ];

        if ($updateQueries === []) {
            $this->info('No update queries configured.');

            return self::SUCCESS;
        }

        foreach ($updateQueries as $queryName) {
            switch ($queryName) {
                case 'events_slug_from_year_season':
                    $this->runEventsSlugFromYearSeason();
                    break;

                default:
                    $this->warn("Unknown update query '{$queryName}' - skipping.");
            }
        }

        return self::SUCCESS;
    }

    private function runEventsSlugFromYearSeason(): void
    {
        $updated = 0;
        $skipped = 0;

        Event::query()
            ->select(['id', 'year', 'season', 'slug'])
            ->orderBy('id')
            ->chunkById(200, function ($events) use (&$updated, &$skipped): void {
                foreach ($events as $event) {
                    if ($event->year === null || blank($event->season)) {
                        $skipped++;

                        continue;
                    }

                    $nextSlug = $event->generateCanonicalSlug();

                    if ($nextSlug === $event->slug) {
                        $skipped++;

                        continue;
                    }

                    Event::query()->whereKey($event->id)->update(['slug' => $nextSlug]);
                    $updated++;
                }
            });

        $this->info("[events_slug_from_year_season] updated {$updated} row(s), skipped {$skipped} row(s).");
    }
}
