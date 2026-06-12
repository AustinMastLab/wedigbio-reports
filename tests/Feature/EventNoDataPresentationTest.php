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

class EventNoDataPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_show_displays_no_data_empty_state_when_event_has_no_records(): void
    {
        $event = Event::create([
            'name' => 'WeDigBio 2025',
            'slug' => '2025',
            'year' => 2025,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
            'notes' => 'Imported but no data available for this year.',
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('No transcription data is available for this event yet.');
        $response->assertDontSee('Total Transcription Activity');
    }

    public function test_event_index_shows_no_data_badge_for_events_without_transcription_rows(): void
    {
        Event::create([
            'name' => 'WeDigBio 2025',
            'slug' => '2025',
            'year' => 2025,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $eventWithData = Event::create([
            'name' => 'WeDigBio 2024',
            'slug' => '2024',
            'year' => 2024,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $source = Source::create([
            'name' => 'Sample Source',
            'slug' => 'sample-source',
            'base_url' => 'https://example.test/feed',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        DB::table('transcription_records')->insert([
            'event_id' => $eventWithData->id,
            'source_id' => $source->id,
            'source_guid' => 'guid-1',
            'dedupe_key' => 'dedupe-1',
            'center' => 'Center A',
            'project' => null,
            'description' => null,
            'timestamp_utc' => now(),
            'work_unit' => 1.0,
            'raw_count' => 1,
            'payload_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('WeDigBio 2025');
        $response->assertSee('No data');
    }

    public function test_event_show_displays_archived_import_copy_for_archived_event_without_records(): void
    {
        $event = Event::create([
            'name' => 'WeDigBio 2025',
            'slug' => '2025',
            'year' => 2025,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('This year is still listed for historical completeness, but chart data was not available from source feeds during import.');
        $response->assertDontSee('This live event has started, but no transcription records have arrived from enabled source feeds yet.');
    }

    public function test_event_show_displays_live_no_data_copy_for_live_event_without_records(): void
    {
        $event = Event::create([
            'name' => 'WeDigBio 2026',
            'slug' => '2026',
            'year' => 2026,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('This live event has started, but no transcription records have arrived from enabled source feeds yet.');
        $response->assertDontSee('This year is still listed for historical completeness, but chart data was not available from source feeds during import.');
    }

    public function test_event_show_displays_countdown_copy_for_future_live_event_without_records(): void
    {
        $event = Event::create([
            'name' => 'WeDigBio 2026 Future',
            'slug' => '2026-future',
            'year' => 2026,
            'starts_at' => now()->addHours(2)->addMinutes(15),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('This live event will start in');
        $response->assertSee('id="live-event-countdown-message"', false);
        $response->assertDontSee('id="live-event-started-message"', false);
    }
}
