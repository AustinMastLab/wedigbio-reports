<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArchivedChartResponseCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_archived_chart_summary_response_is_cached(): void
    {
        config()->set('responsecache.enabled', true);
        config()->set('responsecache.debug.enabled', true);

        $event = Event::create([
            'name' => 'Archived Event',
            'slug' => 'archived-response-cache',
            'year' => 2025,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $source = Source::create([
            'name' => 'Source A',
            'slug' => 'source-a',
            'base_url' => 'https://example.test/source-a',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        DB::table('transcription_records')->insert([
            'event_id' => $event->id,
            'source_id' => $source->id,
            'source_guid' => 'guid-1',
            'dedupe_key' => 'dedupe-1',
            'center' => 'Center A',
            'project' => null,
            'description' => null,
            'timestamp_utc' => now()->subDays(2),
            'work_unit' => 2.5,
            'raw_count' => 3,
            'payload_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Carbon::setTestNow('2026-06-10 00:00:00');
        $firstResponse = $this->getJson(route('api.charts.summary', $event));

        $firstResponse
            ->assertOk()
            ->assertHeader('X-Cache-Status', 'MISS');

        $firstGeneratedAt = $firstResponse->json('meta.generated_at');

        Carbon::setTestNow('2026-06-10 00:00:10');
        $secondResponse = $this->getJson(route('api.charts.summary', $event));

        $secondResponse
            ->assertOk()
            ->assertHeader('X-Cache-Status', 'HIT');

        $this->assertSame($firstGeneratedAt, $secondResponse->json('meta.generated_at'));
    }

    public function test_live_chart_summary_response_is_not_cached(): void
    {
        config()->set('responsecache.enabled', true);
        config()->set('responsecache.debug.enabled', true);

        $event = Event::create([
            'name' => 'Live Event',
            'slug' => 'live-response-cache',
            'year' => 2026,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'Source B',
            'slug' => 'source-b',
            'base_url' => 'https://example.test/source-b',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        DB::table('transcription_records')->insert([
            'event_id' => $event->id,
            'source_id' => $source->id,
            'source_guid' => 'guid-2',
            'dedupe_key' => 'dedupe-2',
            'center' => 'Center B',
            'project' => null,
            'description' => null,
            'timestamp_utc' => now(),
            'work_unit' => 1.0,
            'raw_count' => 1,
            'payload_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Carbon::setTestNow('2026-06-10 00:01:00');
        $firstResponse = $this->getJson(route('api.charts.summary', $event));
        $firstResponse->assertOk()->assertHeader('X-Cache-Status', 'MISS');

        Carbon::setTestNow('2026-06-10 00:01:10');
        $secondResponse = $this->getJson(route('api.charts.summary', $event));
        $secondResponse->assertOk()->assertHeader('X-Cache-Status', 'MISS');

        $this->assertNotSame($firstResponse->json('meta.generated_at'), $secondResponse->json('meta.generated_at'));
    }
}


