<?php

namespace Tests\Feature\Ingestion;

use App\Models\Event;
use App\Models\Source;
use App\Models\SourceCheckpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngestCloseEndedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_stays_live_during_the_grace_period(): void
    {
        $this->travelTo('2026-10-11 11:09:00');
        $event = $this->createEndedEventWithDigivol('digivol-grace');

        Http::preventStrayRequests();

        $this->artisan('ingest:close-ended')->assertSuccessful();

        $this->assertTrue($event->fresh()->is_live);
        $this->assertFalse($event->fresh()->is_archived);
    }

    public function test_final_catch_up_fetches_every_page_to_the_end_then_archives(): void
    {
        $this->travelTo('2026-10-11 11:15:00');
        $event = $this->createEndedEventWithDigivol('digivol-close');

        $firstPage = collect(range(1, 100))->map(fn (int $i) => [
            'id' => "dv-{$i}",
            'timestamp' => '2026-10-11T10:52:00Z',
        ])->all();

        Http::preventStrayRequests();
        Http::fake([
            'https://example.test/digivol-close*' => Http::sequence()
                ->push(['items' => $firstPage])
                ->push(['items' => [['id' => 'dv-late', 'timestamp' => '2026-10-11T10:58:30Z']]]),
        ]);

        $this->artisan('ingest:close-ended')->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['rowStart'] === 100
            && $request['timestampStart'] === '2026-10-11T10:40:00+00:00'
            && $request['timestampEnd'] === '2026-10-11T10:59:00+00:00');
        $this->assertSame(101, DB::table('transcription_records')->where('event_id', $event->id)->count());
        $this->assertSame(101, (int) DB::table('chart_aggregates_hourly')->where('event_id', $event->id)->sum('raw_sum'));
        $this->assertFalse($event->fresh()->is_live);
        $this->assertTrue($event->fresh()->is_archived);
    }

    public function test_failed_catch_up_keeps_the_event_live_for_a_retry(): void
    {
        $this->travelTo('2026-10-11 11:20:00');
        $event = $this->createEndedEventWithDigivol('digivol-down');

        Http::preventStrayRequests();
        Http::fake([
            'https://example.test/digivol-down*' => Http::response([], 503),
        ]);

        $this->artisan('ingest:close-ended')->assertSuccessful();

        $this->assertTrue($event->fresh()->is_live);
        $this->assertFalse($event->fresh()->is_archived);
    }

    public function test_event_is_archived_an_hour_after_the_end_even_if_catch_up_fails(): void
    {
        $this->travelTo('2026-10-11 12:00:00');
        $event = $this->createEndedEventWithDigivol('digivol-still-down');

        Http::preventStrayRequests();
        Http::fake([
            'https://example.test/digivol-still-down*' => Http::response([], 503),
        ]);

        $this->artisan('ingest:close-ended')->assertSuccessful();

        $this->assertFalse($event->fresh()->is_live);
        $this->assertTrue($event->fresh()->is_archived);
    }

    private function createEndedEventWithDigivol(string $slug): Event
    {
        $event = Event::create([
            'year' => 2026,
            'season' => 'fall',
            'starts_at' => '2026-10-07 11:00:00',
            'ends_at' => '2026-10-11 10:59:00',
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'DigiVol',
            'slug' => $slug,
            'base_url' => "https://example.test/{$slug}",
            'adapter_type' => 'digivol_json',
            'is_active' => true,
        ]);

        $event->sources()->attach($source->id, ['is_enabled' => true]);

        SourceCheckpoint::create([
            'event_id' => $event->id,
            'source_id' => $source->id,
            'last_seen_timestamp' => '2026-10-11 10:50:00',
            'last_run_at' => '2026-10-11 10:58:00',
            'last_status' => 'ok',
        ]);

        return $event;
    }
}
