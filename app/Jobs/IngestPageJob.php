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
use App\Mail\IngestionOutageAlert;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceCheckpoint;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class IngestPageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Minutes after the last page fetch during which a run counts as in progress.
     */
    private const RUN_IN_PROGRESS_MINUTES = 10;

    /**
     * A job dispatched by polling has no window and opens a new run; each
     * continuation page carries its run's window and the newest timestamp seen.
     */
    public function __construct(
        public int $eventId,
        public int $sourceId,
        public ?string $pageToken = null,
        public ?string $windowStart = null,
        public ?string $windowEnd = null,
        public ?string $latestSeen = null,
    ) {}

    /**
     * Fetch one source page, upsert records idempotently, then continue pagination.
     *
     * Every page of a run reads the same time window, and the checkpoint only
     * advances after the final page, so a failed or interrupted run is fetched
     * again on the next poll. When the final page is reached, an aggregate
     * rebuild is dispatched so chart endpoints reflect newly ingested records.
     */
    public function handle(SourceAdapterManager $adapters): void
    {
        $event = Event::findOrFail($this->eventId);
        $source = Source::findOrFail($this->sourceId);

        $checkpoint = SourceCheckpoint::firstOrCreate(
            ['event_id' => $event->id, 'source_id' => $source->id],
            ['last_status' => 'pending'],
        );

        $isContinuation = $this->windowEnd !== null;

        if (! $isContinuation && $this->hasRunInProgress($checkpoint)) {
            Log::info('IngestPageJob skipped, run already in progress', [
                'event_slug' => $event->slug,
                'source_slug' => $source->slug,
                'page_token' => $checkpoint->last_page_token,
            ]);

            return;
        }

        if ($isContinuation) {
            $windowStart = $this->windowStart !== null ? CarbonImmutable::parse($this->windowStart) : null;
            $windowEnd = CarbonImmutable::parse($this->windowEnd);
        } else {
            $windowStart = $checkpoint->last_seen_timestamp
                ? CarbonImmutable::instance($checkpoint->last_seen_timestamp)
                : null;
            $windowEnd = $event->ends_at
                ? CarbonImmutable::now()->min($event->ends_at)
                : CarbonImmutable::now();
        }

        Log::info('IngestPageJob started', [
            'event_slug' => $event->slug,
            'source_slug' => $source->slug,
            'page_token' => $this->pageToken,
            'window_start' => $windowStart?->toIso8601String(),
            'window_end' => $windowEnd->toIso8601String(),
        ]);

        try {
            $page = $adapters
                ->forType($source->adapter_type)
                ->fetchPage(
                    event: $event,
                    source: $source,
                    pageToken: $this->pageToken,
                    since: $windowStart,
                    until: $windowEnd,
                );

            Log::info('IngestPageJob fetched page', [
                'event_slug' => $event->slug,
                'source_slug' => $source->slug,
                'record_count' => count($page->records),
                'next_page_token' => filled($page->nextPageToken) ? 'yes' : 'no',
            ]);

            $rows = [];
            $latestSeen = $this->latestSeen !== null
                ? CarbonImmutable::parse($this->latestSeen)
                : $windowStart;

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

                Log::info('IngestPageJob upserted records', [
                    'event_slug' => $event->slug,
                    'source_slug' => $source->slug,
                    'row_count' => count($rows),
                ]);
            }

            $hasNextPage = filled($page->nextPageToken);

            $checkpoint->fill([
                'last_page_token' => $hasNextPage ? $page->nextPageToken : null,
                'last_run_at' => now(),
                'last_status' => 'ok',
                'last_error' => null,
                'first_failed_at' => null,
                'failure_alert_sent_at' => null,
            ]);

            if (! $hasNextPage) {
                $checkpoint->last_seen_timestamp = $latestSeen;
            }

            $checkpoint->save();

            if ($hasNextPage) {
                Log::info('IngestPageJob dispatching next page', [
                    'event_slug' => $event->slug,
                    'source_slug' => $source->slug,
                    'next_page_token' => $page->nextPageToken,
                ]);
                self::dispatch(
                    $event->id,
                    $source->id,
                    $page->nextPageToken,
                    $windowStart?->toIso8601String(),
                    $windowEnd->toIso8601String(),
                    $latestSeen?->toIso8601String(),
                );
            } else {
                Log::info('IngestPageJob reached final page, triggering aggregation', [
                    'event_slug' => $event->slug,
                ]);
                // Last page done — rebuild hourly aggregates for this event
                AggregateHourlyJob::dispatch($event->id);
            }
        } catch (Throwable $e) {
            Log::error('IngestPageJob failed', [
                'event_slug' => $event->slug,
                'source_slug' => $source->slug,
                'error' => $e->getMessage(),
            ]);

            $checkpoint->fill([
                'last_run_at' => now(),
                'last_status' => 'error',
                'last_error' => substr($e->getMessage(), 0, 2000),
                'first_failed_at' => $checkpoint->first_failed_at ?? now(),
            ])->save();

            $this->queueOutageAlert($event, $source, $checkpoint);

            throw $e;
        }
    }

    /**
     * A run is in progress while it has a next page and fetched one recently;
     * an older unfinished run is treated as abandoned and replaced.
     */
    private function hasRunInProgress(SourceCheckpoint $checkpoint): bool
    {
        return filled($checkpoint->last_page_token)
            && $checkpoint->last_run_at !== null
            && $checkpoint->last_run_at->gt(now()->subMinutes(self::RUN_IN_PROGRESS_MINUTES));
    }

    private function queueOutageAlert(Event $event, Source $source, SourceCheckpoint $checkpoint): void
    {
        $recipient = config('mail.from.address');

        if (blank($recipient)) {
            return;
        }

        $firstFailedAt = $checkpoint->first_failed_at;
        if ($firstFailedAt === null || $firstFailedAt->gt(now()->subHour())) {
            return;
        }

        $claimed = SourceCheckpoint::query()
            ->whereKey($checkpoint->id)
            ->whereNull('failure_alert_sent_at')
            ->update(['failure_alert_sent_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        Mail::to($recipient)->queue(
            (new IngestionOutageAlert($event, $source, $firstFailedAt))
                ->onQueue(config('queue.connections.beanstalkd.queue', 'wedigbio-ingest')),
        );
    }
}
