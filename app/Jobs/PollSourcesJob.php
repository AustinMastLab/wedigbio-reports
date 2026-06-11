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

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PollSourcesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Scan active events and dispatch ingestion jobs for enabled active sources.
     */
    public function handle(): void
    {
        $now = now();
        Log::info("PollSourcesJob started");

        $events = Event::query()
            ->where('is_archived', false)
            ->where('is_live', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->with(['sources' => fn ($query) => $query
                ->where('is_active', true)
                ->wherePivot('is_enabled', true)])
            ->get();

        Log::info("PollSourcesJob found events", [
            'event_count' => $events->count(),
        ]);

        $jobCount = 0;
        $events->each(function (Event $event) use (&$jobCount): void {
            $sourceCount = $event->sources->count();
            if ($sourceCount > 0) {
                Log::info("PollSourcesJob dispatching for event", [
                    'event_slug' => $event->slug,
                    'source_count' => $sourceCount,
                ]);
            }

            $event->sources->each(function ($source) use ($event, &$jobCount): void {
                IngestPageJob::dispatch($event->id, $source->id);
                $jobCount++;
            });
        });

        Log::info("PollSourcesJob completed", [
            'total_jobs_dispatched' => $jobCount,
        ]);
    }
}

