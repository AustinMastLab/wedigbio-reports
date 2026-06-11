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

namespace Tests\Feature;

use App\Ingestion\SourceAdapterManager;
use App\Jobs\AggregateHourlyJob;
use App\Jobs\IngestPageJob;
use App\Models\ChartAggregateHourly;
use App\Models\Event;
use App\Models\Source;
use App\Models\SourceCheckpoint;
use App\Models\TranscriptionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase_H_ValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingestion_idempotency_same_payload_ingested_twice_no_duplicate(): void
    {
        $event = Event::create([
            'name' => 'Test Event',
            'slug' => 'test-event',
            'year' => 2026,
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

        $event->sources()->attach($source->id, ['is_enabled' => true]);

        $payload = [
            'data' => [
                [
                    'id' => 'rec-1',
                    'center' => 'Center A',
                    'project' => 'Project Alpha',
                    'description' => 'Item 1',
                    'timestamp_utc' => '2026-06-07T10:00:00Z',
                    'work_unit' => 1.5,
                    'raw_count' => 2,
                ],
                [
                    'id' => 'rec-2',
                    'center' => 'Center B',
                    'project' => 'Project Beta',
                    'description' => 'Item 2',
                    'timestamp_utc' => '2026-06-07T11:00:00Z',
                    'work_unit' => 2.25,
                    'raw_count' => 1,
                ],
            ],
            'next_page_token' => null,
        ];

        Http::fake([
            'https://example.test/feed*' => Http::response($payload),
        ]);

        // First ingest
        $job1 = new IngestPageJob($event->id, $source->id);
        $job1->handle(app(SourceAdapterManager::class));

        $this->assertSame(2, TranscriptionRecord::count());

        // Second ingest (same payload)
        $job2 = new IngestPageJob($event->id, $source->id);
        $job2->handle(app(SourceAdapterManager::class));

        // Should still be 2 records (upserted, not duplicated)
        $this->assertSame(2, TranscriptionRecord::count());

        // Verify dedupe_key integrity
        $this->assertSame(2, DB::table('transcription_records')->distinct('dedupe_key')->count());
    }

    public function test_aggregation_correctness_with_fractional_work_unit(): void
    {
        $event = Event::create([
            'name' => 'Fraction Test',
            'slug' => 'fraction-test',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $source = Source::create([
            'name' => 'Test Source',
            'slug' => 'test-source',
            'base_url' => 'https://example.test/data',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        // Insert records with fractional work_units in same hour/center bucket
        $now = now('UTC');
        $base = [
            'event_id' => $event->id,
            'source_id' => $source->id,
            'project' => null,
            'description' => null,
            'payload_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('transcription_records')->insert([
            array_merge($base, [
                'source_guid' => 'g1',
                'dedupe_key' => 'k1',
                'center' => 'Lab A',
                'timestamp_utc' => $now->copy()->setHour(10)->setMinute(15),
                'work_unit' => 1.5,
                'raw_count' => 1,
            ]),
            array_merge($base, [
                'source_guid' => 'g2',
                'dedupe_key' => 'k2',
                'center' => 'Lab A',
                'timestamp_utc' => $now->copy()->setHour(10)->setMinute(45),
                'work_unit' => 2.25,
                'raw_count' => 2,
            ]),
            array_merge($base, [
                'source_guid' => 'g3',
                'dedupe_key' => 'k3',
                'center' => 'Lab A',
                'timestamp_utc' => $now->copy()->setHour(10)->setMinute(59),
                'work_unit' => 0.75,
                'raw_count' => 1,
            ]),
        ]);

        (new AggregateHourlyJob($event->id))->handle();

        $bucket = ChartAggregateHourly::where('event_id', $event->id)
            ->where('center', 'Lab A')
            ->first();

        $this->assertNotNull($bucket);
        // 1.5 + 2.25 + 0.75 = 4.5
        $this->assertEquals(4.5, (float) $bucket->weighted_sum);
        // 1 + 2 + 1 = 4
        $this->assertSame(4, (int) $bucket->raw_sum);

        // Verify precision: work_unit should round to 4 decimal places
        $records = TranscriptionRecord::where('event_id', $event->id)->get();
        foreach ($records as $r) {
            $this->assertEquals(round((float) $r->work_unit, 4), (float) $r->work_unit);
        }
    }

    public function test_live_event_api_response_reflects_is_live_flag(): void
    {
        $liveEvent = Event::create([
            'name' => 'Live Event',
            'slug' => 'live-event',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $archivedEvent = Event::create([
            'name' => 'Archived Event',
            'slug' => 'archived-event',
            'year' => 2025,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $source = Source::create([
            'name' => 'Test Source',
            'slug' => 'test-source',
            'base_url' => 'https://example.test/data',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        DB::table('transcription_records')->insert([
            [
                'event_id' => $liveEvent->id,
                'source_id' => $source->id,
                'source_guid' => 'g1',
                'dedupe_key' => 'k1',
                'center' => 'Center',
                'project' => null,
                'description' => null,
                'timestamp_utc' => now(),
                'work_unit' => 1.0,
                'raw_count' => 1,
                'payload_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => $archivedEvent->id,
                'source_id' => $source->id,
                'source_guid' => 'g2',
                'dedupe_key' => 'k2',
                'center' => 'Center',
                'project' => null,
                'description' => null,
                'timestamp_utc' => now()->subDay(),
                'work_unit' => 1.0,
                'raw_count' => 1,
                'payload_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $liveResponse = app()->handle(\Illuminate\Http\Request::create("/api/events/{$liveEvent->slug}/summary", 'GET'));
        $archiveResponse = app()->handle(\Illuminate\Http\Request::create("/api/events/{$archivedEvent->slug}/summary", 'GET'));

        $liveData = json_decode($liveResponse->getContent(), true);
        $archiveData = json_decode($archiveResponse->getContent(), true);

        $this->assertTrue($liveData['meta']['is_live']);
        $this->assertFalse($archiveData['meta']['is_live']);
    }

    public function test_aggregation_handles_multiple_centers_and_hours(): void
    {
        $event = Event::create([
            'name' => 'Multi-center Test',
            'slug' => 'multi-center-test',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $source = Source::create([
            'name' => 'Test Source',
            'slug' => 'test-source',
            'base_url' => 'https://example.test/data',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        $now = now('UTC');
        $base = [
            'event_id' => $event->id,
            'source_id' => $source->id,
            'project' => null,
            'description' => null,
            'payload_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // 3 centers × 4 hours = 12 buckets
        $records = [];
        foreach (['Center A', 'Center B', 'Center C'] as $center) {
            foreach (range(0, 3) as $hourOffset) {
                $records[] = array_merge($base, [
                    'source_guid' => "guid-{$center}-{$hourOffset}",
                    'dedupe_key' => "key-{$center}-{$hourOffset}",
                    'center' => $center,
                    'timestamp_utc' => $now->copy()->addHours($hourOffset)->setMinute(30),
                    'work_unit' => 1.0,
                    'raw_count' => 1,
                ]);
            }
        }

        DB::table('transcription_records')->insert($records);

        (new AggregateHourlyJob($event->id))->handle();

        $buckets = ChartAggregateHourly::where('event_id', $event->id)->get();

        // Should have 12 unique (center, hour) buckets
        $this->assertCount(12, $buckets);

        // Each bucket should have weighted_sum=1.0, raw_sum=1
        foreach ($buckets as $bucket) {
            $this->assertEquals(1.0, (float) $bucket->weighted_sum);
            $this->assertSame(1, (int) $bucket->raw_sum);
        }
    }
}


