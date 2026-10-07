<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartBucketSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_event_uses_minute_buckets_in_its_first_hour(): void
    {
        $this->travelTo('2026-10-07 11:59:00');
        $event = $this->createLiveFallEvent();

        $response = $this->getJson("/api/events/{$event->slug}/summary");

        $response->assertJsonPath('meta.bucket_size', 'minute');
    }

    public function test_live_event_switches_to_hour_buckets_after_its_first_hour(): void
    {
        $this->travelTo('2026-10-07 13:05:00');
        $event = $this->createLiveFallEvent();

        $response = $this->getJson("/api/events/{$event->slug}/summary");

        $response->assertJsonPath('meta.bucket_size', 'hour');
    }

    private function createLiveFallEvent(): Event
    {
        return Event::create([
            'year' => 2026,
            'season' => 'fall',
            'starts_at' => '2026-10-07 11:00:00',
            'ends_at' => '2026-10-11 10:59:00',
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);
    }
}
