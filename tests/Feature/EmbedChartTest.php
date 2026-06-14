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

use App\Models\Event;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmbedChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_embed_chart_requires_live_event(): void
    {
        // Create archived event (no live event)
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

        // Embed route should return error message (not chart) when no live event
        $this->get('/events/embed?chart=total-activity')
            ->assertOk()
            ->assertSeeText('No Live Event Found');
    }

    public function test_embed_chart_requires_valid_chart_type(): void
    {
        // Create a live event
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

        // Invalid chart type should return error message
        $response = $this->get('/events/embed?chart=invalid-chart');
        $response->assertOk()
            ->assertSeeText('Invalid or missing chart type');
    }

    public function test_embed_total_activity_chart_loads(): void
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

        $response = $this->get('/events/embed?chart=total-activity');

        $response->assertOk();
        $response->assertViewIs('events.embed-chart');
        $response->assertViewHas('chartType', 'total-activity');
        $response->assertViewHas('chartTitle', 'Total Transcription Activity');
    }

    public function test_embed_activity_by_center_chart_loads(): void
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

        $response = $this->get('/events/embed?chart=activity-by-center');

        $response->assertOk();
        $response->assertViewIs('events.embed-chart');
        $response->assertViewHas('chartType', 'activity-by-center');
        $response->assertViewHas('chartTitle', 'Activity by Center');
    }

    public function test_embed_chart_displays_countdown_for_future_event(): void
    {
        $futureEvent = Event::create([
            'name' => 'Future Live Event',
            'slug' => 'future-event',
            'year' => 2026,
            'starts_at' => now()->addHours(3)->addMinutes(30),
            'ends_at' => now()->addDays(1),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $response = $this->get('/events/embed?chart=total-activity');

        $response->assertOk();
        $response->assertSeeText('Event starts in');
    }

    public function test_embed_chart_passes_start_time_for_countdown(): void
    {
        $futureEvent = Event::create([
            'name' => 'Future Live Event',
            'slug' => 'future-event',
            'year' => 2026,
            'starts_at' => now()->addHours(5),
            'ends_at' => now()->addDays(1),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $response = $this->get('/events/embed?chart=total-activity');

        $response->assertOk();
        $response->assertViewHas('event', function ($event) {
            return $event->starts_at !== null;
        });
    }

    public function test_embed_chart_shows_no_data_message_for_live_event_without_data(): void
    {
        $liveEvent = Event::create([
            'name' => 'Live Event Without Data',
            'slug' => 'live-no-data',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $response = $this->get('/events/embed?chart=total-activity');

        $response->assertOk();
        $response->assertViewHas('hasData', false);
    }

    public function test_embed_chart_shows_data_for_live_event_with_records(): void
    {
        $liveEvent = Event::create([
            'name' => 'Live Event With Data',
            'slug' => 'live-with-data',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'Test Source',
            'slug' => 'test-source',
            'base_url' => 'https://example.test',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        // Insert a transcription record
        DB::table('transcription_records')->insert([
            'event_id' => $liveEvent->id,
            'source_id' => $source->id,
            'source_guid' => 'guid-1',
            'dedupe_key' => 'dedupe-1',
            'center' => 'Test Center',
            'project' => null,
            'description' => null,
            'timestamp_utc' => now(),
            'work_unit' => 1.0,
            'raw_count' => 1,
            'payload_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/events/embed?chart=total-activity');

        $response->assertOk();
        $response->assertViewHas('hasData', true);
    }

    public function test_embed_auto_discovers_live_event(): void
    {
        // Create non-live event (should not be used)
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

        // Create live event (should be used)
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

        $response = $this->get('/events/embed?chart=total-activity');

        $response->assertOk();
        $response->assertViewHas('event', function ($event) use ($liveEvent) {
            return $event->id === $liveEvent->id && $event->is_live;
        });
    }

    public function test_embed_ignores_non_public_live_events(): void
    {
        // Create non-public live event (should be ignored)
        $privateEvent = Event::create([
            'name' => 'Private Live Event',
            'slug' => 'private-live',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => false,
            'is_live' => true,
            'is_archived' => false,
        ]);

        // Request should return "no event" message
        $response = $this->get('/events/embed?chart=total-activity');

        $response->assertOk()
            ->assertSeeText('No Live Event Found');
    }
}
