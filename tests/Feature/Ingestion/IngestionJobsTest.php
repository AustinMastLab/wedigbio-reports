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

namespace Tests\Feature\Ingestion;

use App\Ingestion\SourceAdapterManager;
use App\Jobs\AggregateHourlyJob;
use App\Jobs\IngestPageJob;
use App\Jobs\PollSourcesJob;
use App\Models\ChartAggregateHourly;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceCheckpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IngestionJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingest_page_job_upserts_records_and_updates_checkpoint_idempotently(): void
    {
        $event = Event::create([
            'name' => 'Test Event',
            'slug' => 'test-event',
            'year' => 2026,
            'season' => 'spring',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'Sample API',
            'slug' => 'sample-api',
            'base_url' => 'https://example.test/feed',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        SourceCheckpoint::create([
            'event_id' => $event->id,
            'source_id' => $source->id,
            'last_status' => 'pending',
        ]);

        Http::fake([
            'https://example.test/feed*' => Http::response([
                'data' => [
                    [
                        'id' => 'a-1',
                        'center' => 'Center A',
                        'project' => 'Plants',
                        'description' => 'first',
                        'timestamp_utc' => '2026-06-07T10:00:00Z',
                        'work_unit' => 1.5,
                        'raw_count' => 2,
                    ],
                    [
                        'id' => 'a-2',
                        'center' => 'Center B',
                        'project' => 'Birds',
                        'description' => 'second',
                        'timestamp_utc' => '2026-06-07T11:00:00Z',
                        'work_unit' => 1,
                        'raw_count' => 1,
                    ],
                ],
                'next_page_token' => null,
            ]),
        ]);

        $job = new IngestPageJob($event->id, $source->id);
        $job->handle(app(SourceAdapterManager::class));
        $job->handle(app(SourceAdapterManager::class));

        $this->assertSame(2, DB::table('transcription_records')->count());

        $checkpoint = SourceCheckpoint::where('event_id', $event->id)
            ->where('source_id', $source->id)
            ->first();

        $this->assertNotNull($checkpoint);
        $this->assertSame('ok', $checkpoint->last_status);
        $this->assertNotNull($checkpoint->last_seen_timestamp);
    }

    public function test_poll_sources_job_dispatches_ingest_page_job_for_enabled_pairs(): void
    {
        Bus::fake();

        $event = Event::create([
            'name' => 'Active Event',
            'slug' => 'active-event',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'Sample API',
            'slug' => 'sample-api-2',
            'base_url' => 'https://example.test/feed',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        $event->sources()->attach($source->id, ['is_enabled' => true]);

        (new PollSourcesJob)->handle();

        Bus::assertDispatched(IngestPageJob::class, function (IngestPageJob $job) use ($event, $source) {
            return $job->eventId === $event->id && $job->sourceId === $source->id;
        });
    }

    public function test_poll_sources_job_only_dispatches_for_live_events_within_time_window(): void
    {
        Bus::fake();

        $source = Source::create([
            'name' => 'Window API',
            'slug' => 'window-api',
            'base_url' => 'https://example.test/feed',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        $activeEvent = Event::create([
            'name' => 'Active In Window',
            'slug' => 'active-in-window',
            'year' => 2026,
            'season' => 'spring',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $futureEvent = Event::create([
            'name' => 'Future Event',
            'slug' => 'future-event',
            'year' => 2026,
            'season' => 'fall',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $pastEvent = Event::create([
            'name' => 'Past Event',
            'slug' => 'past-event',
            'year' => 2025,
            'season' => 'fall',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subHour(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $notLiveEvent = Event::create([
            'name' => 'Not Live Event',
            'slug' => 'not-live-event',
            'year' => 2026,
            'season' => 'spring',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => false,
        ]);

        $activeEvent->sources()->attach($source->id, ['is_enabled' => true]);
        $futureEvent->sources()->attach($source->id, ['is_enabled' => true]);
        $pastEvent->sources()->attach($source->id, ['is_enabled' => true]);
        $notLiveEvent->sources()->attach($source->id, ['is_enabled' => true]);

        (new PollSourcesJob)->handle();

        Bus::assertDispatchedTimes(IngestPageJob::class, 1);
        Bus::assertDispatched(IngestPageJob::class, function (IngestPageJob $job) use ($activeEvent, $source) {
            return $job->eventId === $activeEvent->id && $job->sourceId === $source->id;
        });
    }

    public function test_aggregate_hourly_job_builds_buckets_from_transcription_records(): void
    {
        $event = Event::create([
            'name' => 'Agg Event',
            'slug' => 'agg-event',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'Agg Source',
            'slug' => 'agg-source',
            'base_url' => 'https://example.test/agg',
            'adapter_type' => 'http_json',
            'is_active' => true,
        ]);

        // Three records: two in the same hour/center bucket, one in a different hour
        $base = ['event_id' => $event->id, 'source_id' => $source->id,
                 'project' => null, 'description' => null, 'payload_json' => null,
                 'created_at' => now(), 'updated_at' => now()];

        DB::table('transcription_records')->insert([
            array_merge($base, ['source_guid' => 'g1', 'dedupe_key' => 'k1', 'center' => 'NHML',
                'timestamp_utc' => '2026-06-07 10:15:00', 'work_unit' => 1.5, 'raw_count' => 1]),
            array_merge($base, ['source_guid' => 'g2', 'dedupe_key' => 'k2', 'center' => 'NHML',
                'timestamp_utc' => '2026-06-07 10:45:00', 'work_unit' => 0.5, 'raw_count' => 1]),
            array_merge($base, ['source_guid' => 'g3', 'dedupe_key' => 'k3', 'center' => 'NHML',
                'timestamp_utc' => '2026-06-07 11:05:00', 'work_unit' => 1.0, 'raw_count' => 1]),
        ]);

        (new AggregateHourlyJob($event->id))->handle();

        $buckets = ChartAggregateHourly::where('event_id', $event->id)
            ->orderBy('bucket_hour_utc')
            ->get();

        $this->assertCount(2, $buckets);

        // 10:00 bucket: work_unit 1.5 + 0.5 = 2.0, raw 2
        $this->assertEquals('2026-06-07 10:00:00', $buckets[0]->bucket_hour_utc->toDateTimeString());
        $this->assertEquals(2.0, (float) $buckets[0]->weighted_sum);
        $this->assertSame(2, (int) $buckets[0]->raw_sum);

        // 11:00 bucket: work_unit 1.0, raw 1
        $this->assertEquals('2026-06-07 11:00:00', $buckets[1]->bucket_hour_utc->toDateTimeString());
        $this->assertEquals(1.0, (float) $buckets[1]->weighted_sum);
        $this->assertSame(1, (int) $buckets[1]->raw_sum);

        // Running again should upsert (not double-count)
        (new AggregateHourlyJob($event->id))->handle();
        $this->assertCount(2, ChartAggregateHourly::where('event_id', $event->id)->get());
    }
}

