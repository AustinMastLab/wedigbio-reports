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

use App\Ingestion\SourceAdapterManager;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceCheckpoint;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestPageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $eventId,
        public int $sourceId,
        public ?string $pageToken = null,
    ) {}

    /**
     * Fetch one source page, upsert records idempotently, then continue pagination.
     *
     * When the final page is reached, an aggregate rebuild is dispatched for
     * the event so chart endpoints reflect newly ingested records.
     */
    public function handle(SourceAdapterManager $adapters): void
    {
        $event = Event::findOrFail($this->eventId);
        $source = Source::findOrFail($this->sourceId);

        $checkpoint = SourceCheckpoint::firstOrCreate(
            ['event_id' => $event->id, 'source_id' => $source->id],
            ['last_status' => 'pending'],
        );

        Log::info("IngestPageJob started", [
            'event_slug' => $event->slug,
            'source_slug' => $source->slug,
            'page_token' => $this->pageToken ?? $checkpoint->last_page_token,
            'last_seen' => $checkpoint->last_seen_timestamp?->toIso8601String(),
        ]);

        try {
            $page = $adapters
                ->forType($source->adapter_type)
                ->fetchPage(
                    event: $event,
                    source: $source,
                    pageToken: $this->pageToken ?? $checkpoint->last_page_token,
                    since: $checkpoint->last_seen_timestamp,
                );

            Log::info("IngestPageJob fetched page", [
                'event_slug' => $event->slug,
                'source_slug' => $source->slug,
                'record_count' => count($page->records),
                'next_page_token' => filled($page->nextPageToken) ? 'yes' : 'no',
            ]);

            $rows = [];
            $latestSeen = $checkpoint->last_seen_timestamp
                ? CarbonImmutable::instance($checkpoint->last_seen_timestamp)
                : null;

            foreach ($page->records as $record) {
                $rows[] = $record->toUpsertRow($event->id, $source->id);
                $latestSeen = $latestSeen
                    ? $latestSeen->max($record->timestampUtc)
                    : $record->timestampUtc;
            }

            if ($rows !== []) {
                DB::table('transcription_records')->upsert(
                    $rows,
                    ['dedupe_key'],
                    ['center', 'project', 'description', 'timestamp_utc', 'work_unit', 'raw_count', 'payload_json', 'updated_at'],
                );

                Log::info("IngestPageJob upserted records", [
                    'event_slug' => $event->slug,
                    'source_slug' => $source->slug,
                    'row_count' => count($rows),
                ]);
            }

            $checkpoint->fill([
                'last_seen_timestamp' => $latestSeen,
                'last_page_token' => $page->nextPageToken,
                'last_run_at' => now(),
                'last_status' => 'ok',
                'last_error' => null,
            ])->save();

            if (filled($page->nextPageToken)) {
                Log::info("IngestPageJob dispatching next page", [
                    'event_slug' => $event->slug,
                    'source_slug' => $source->slug,
                    'next_page_token' => $page->nextPageToken,
                ]);
                self::dispatch($event->id, $source->id, $page->nextPageToken);
            } else {
                Log::info("IngestPageJob reached final page, triggering aggregation", [
                    'event_slug' => $event->slug,
                ]);
                // Last page done — rebuild hourly aggregates for this event
                AggregateHourlyJob::dispatch($event->id);
            }
        } catch (Throwable $e) {
            Log::error("IngestPageJob failed", [
                'event_slug' => $event->slug,
                'source_slug' => $source->slug,
                'error' => $e->getMessage(),
            ]);

            $checkpoint->fill([
                'last_run_at' => now(),
                'last_status' => 'error',
                'last_error' => substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }
    }
}
