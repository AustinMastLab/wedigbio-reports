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

namespace Tests\Unit\Ingestion;

use App\Ingestion\Adapters\BiospexJsonSourceAdapter;
use App\Ingestion\Adapters\DigivolJsonSourceAdapter;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourceAdapterMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_biospex_adapter_maps_items_payload_to_normalized_records(): void
    {
        Carbon::setTestNow('2026-06-07T16:00:00Z');

        $event = Event::create([
            'name' => 'Event',
            'slug' => 'event',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'Notes From Nature',
            'slug' => 'nf-n',
            'base_url' => 'https://example.test/biospex',
            'adapter_type' => 'biospex_json',
            'auth_type' => 'bearer_token',
            'auth_config' => ['token' => 'test-token'],
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        $fixture = json_decode(file_get_contents(base_path('biospex.json')), true, 512, JSON_THROW_ON_ERROR);
        $expectedCount = count($fixture['items'] ?? []);

        Http::fake([
            'https://example.test/biospex*' => Http::response($fixture),
        ]);

        $since = Carbon::parse('2026-06-07T15:00:00Z');
        $page = app(BiospexJsonSourceAdapter::class)->fetchPage($event, $source, '200', $since);

        $this->assertNotEmpty($page->records);
        $this->assertCount($expectedCount, $page->records);

        Http::assertSent(function ($request) {
            $url = $request->url();
            // Should send rowStart=200 and timestampStart, but NOT timestamp or redundant page_token
            return str_contains($url, 'rowStart=200')
                && str_contains($url, 'timestampStart=2026-06-07T15%3A00%3A00%2B00%3A00')
                && !str_contains($url, 'timestamp=')
                && !str_contains($url, 'page_token')
                && $request->hasHeader('Authorization');
        });

        $first = $page->records[0];
        $this->assertSame('e1c547ae-636d-4f9d-8dfc-02ef990472dc', $first->sourceGuid);
        $this->assertSame('Notes From Nature', $first->center);
        $this->assertSame('Notes From Nature', $first->project);
        $this->assertSame(1.0, $first->workUnit);
        $this->assertSame(1, $first->rawCount);
        $this->assertSame('2026-03-09T03:36:12+00:00', $first->timestampUtc->toIso8601String());

        Carbon::setTestNow();
    }

    public function test_digivol_adapter_maps_items_payload_to_normalized_records(): void
    {
        $event = Event::create([
            'name' => 'Event',
            'slug' => 'event-2',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'DigiVol',
            'slug' => 'digivol-test',
            'base_url' => 'https://example.test/digivol',
            'adapter_type' => 'digivol_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        $fixture = json_decode(file_get_contents(base_path('digivol.json')), true, 512, JSON_THROW_ON_ERROR);
        $expectedCount = count($fixture['items'] ?? []);

        Http::fake([
            'https://example.test/digivol*' => Http::response($fixture),
        ]);

        $page = app(DigivolJsonSourceAdapter::class)->fetchPage($event, $source);

         $this->assertCount($expectedCount, $page->records);

         Http::assertSent(function ($request) {
            return str_contains($request->url(), 'event=event-2');
        });

         $first = $page->records[0];
         $this->assertSame('8a7d6495-e814-4e33-be8c-45d42132dcf9', $first->sourceGuid);
        $this->assertSame('Megadiverse: The Flora and Mycota of Venezuela (Part 6)', $first->center);
        $this->assertSame('Megadiverse: The Flora and Mycota of Venezuela (Part 6)', $first->project);
        $this->assertSame(1.0, $first->workUnit);
        $this->assertSame(1, $first->rawCount);
        $this->assertSame('2026-06-07T15:15:34+00:00', $first->timestampUtc->toIso8601String());
    }

    public function test_digivol_adapter_handles_empty_payload(): void
    {
        $event = Event::create([
            'name' => 'Event',
            'slug' => 'event-3',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $source = Source::create([
            'name' => 'DigiVol',
            'slug' => 'digivol-empty',
            'base_url' => 'https://example.test/digivol-empty',
            'adapter_type' => 'digivol_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://example.test/digivol-empty*' => Http::response([]),
        ]);

        $page = app(DigivolJsonSourceAdapter::class)->fetchPage($event, $source);

        $this->assertCount(0, $page->records);
        $this->assertNull($page->nextPageToken);
    }
}
