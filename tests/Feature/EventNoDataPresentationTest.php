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
}

