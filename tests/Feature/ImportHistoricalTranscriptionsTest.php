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

use App\Models\ChartAggregateHourly;
use App\Models\Event;
use App\Models\Source;
use App\Models\TranscriptionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportHistoricalTranscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_historical_marks_event_when_year_has_no_transcription_rows(): void
    {
        $basePath = storage_path('framework/testing/historical-import-empty');
        File::deleteDirectory($basePath);

        File::ensureDirectoryExists($basePath . '/2025/newdata');
        File::put($basePath . '/2025/newdata/ddf.csv', implode("\n", [
            '"project","guid","center"',
            '"No Timestamp Project","abc-123","Unknown"',
            '',
        ]));

        $exitCode = Artisan::call('import:historical', [
            '--path' => $basePath,
        ]);

        $this->assertSame(0, $exitCode);

        $event = Event::where('slug', '2025')->firstOrFail();
        $this->assertSame(0, $event->transcriptionRecords()->count());
        $this->assertSame(0, $event->sources()->count());
        $this->assertStringContainsString('No transcription rows were available for this event year.', (string) $event->notes);
    }

    public function test_import_historical_maps_final_c_items_rows_to_citsciscribe_source(): void
    {
        $basePath = storage_path('framework/testing/historical-import-citsci');
        File::deleteDirectory($basePath);

        File::ensureDirectoryExists($basePath . '/2017/newdata');
        File::put($basePath . '/2017/newdata/final-cItems.csv', implode("\n", [
            '"description","guid","timestamp","subject.link"',
            '"CitSciScribe Transcription","citsci-1","2017-10-19 08:25:56","http://citsciscribe.org"',
            '',
        ]));

        $exitCode = Artisan::call('import:historical', [
            '--path' => $basePath,
        ]);

        $this->assertSame(0, $exitCode);

        $citsci = Source::where('slug', 'citsciscribe')->firstOrFail();
        $this->assertDatabaseHas('transcription_records', [
            'source_id' => $citsci->id,
            'source_guid' => 'citsci-1',
        ]);
    }

    public function test_import_historical_maps_split_year_filename_to_smithsonian_source(): void
    {
        $basePath = storage_path('framework/testing/historical-import-sitc');
        File::deleteDirectory($basePath);

        File::ensureDirectoryExists($basePath . '/2017/newdata');
        File::put($basePath . '/2017/newdata/final-sitcItems.csv', implode("\n", [
            '"project","description","guid","timestamp","subject.link","subject.thumbnailUri","contributor.transcriber"',
            '"Bailey - Texas, California, and New Mexico, April 1900 - August 1900","User walkerfricks transcribedAndSetForReview SIA-MODSI4155_12-443_bailey_v_o_028","sitc-1","2017-10-23T11:21:29+00:00","https://transcription.si.edu/transcribe/11196/SIA-MODSI4155_12-443_bailey_v_o_028","https://ids.si.edu/ids/deliveryService?max_w=210&id=SIA-MODSI4155_12-443_bailey_v_o_028","walkerfricks"',
            '',
        ]));

        $exitCode = Artisan::call('import:historical', [
            '--path' => $basePath,
        ]);

        $this->assertSame(0, $exitCode);

        $smithsonian = Source::where('slug', 'smithsonian')->firstOrFail();
        $this->assertDatabaseHas('transcription_records', [
            'source_id' => $smithsonian->id,
            'source_guid' => 'sitc-1',
        ]);
    }

    public function test_import_historical_uses_ddf_center_column_for_source_mapping(): void
    {
        $basePath = storage_path('framework/testing/historical-import-ddf');
        File::deleteDirectory($basePath);

        File::ensureDirectoryExists($basePath . '/2019/newdata');
        File::put($basePath . '/2019/newdata/ddf.csv', implode("\n", [
            '"project","description","guid","timestamp","center","discretionaryState.workUnit","id"',
            '"Project Two","First ddf row","33333333-3333-3333-3333-333333333333","2019-10-16 15:55:05","Les herbonautes","3.92","1"',
            '"Project Two","Second ddf row","44444444-4444-4444-4444-444444444444","2019-10-16 16:10:05","DigiVol","1","2"',
            '',
        ]));

        $exitCode = Artisan::call('import:historical', [
            '--path' => $basePath,
        ]);

        $this->assertSame(0, $exitCode);

        $digiVol = Source::where('slug', 'digivol')->firstOrFail();
        $herbonautes = Source::where('slug', 'les-herbonautes')->firstOrFail();

        $this->assertDatabaseHas('transcription_records', [
            'source_id' => $digiVol->id,
            'source_guid' => '44444444-4444-4444-4444-444444444444',
        ]);
        $this->assertDatabaseHas('transcription_records', [
            'source_id' => $herbonautes->id,
            'source_guid' => '33333333-3333-3333-3333-333333333333',
        ]);
    }

    public function test_import_historical_sets_spring_for_lite_event_slug(): void
    {
        $basePath = storage_path('framework/testing/historical-import-lite');
        File::deleteDirectory($basePath);

        File::ensureDirectoryExists($basePath . '/2020-lite/newdata');
        File::put($basePath . '/2020-lite/newdata/ddf.csv', implode("\n", [
            '"project","description","guid","timestamp","center","discretionaryState.workUnit","id"',
            '"Project Lite","Lite row","55555555-5555-5555-5555-555555555555","2020-04-06 10:33:03","DoeDat","1","9"',
            '',
        ]));

        $exitCode = Artisan::call('import:historical', [
            '--path' => $basePath,
        ]);

        $this->assertSame(0, $exitCode);

        $event = Event::where('slug', '2020-lite')->firstOrFail();
        $this->assertSame('spring', $event->season);
        $this->assertSame('2020', $event->display_alias);
    }

    public function test_import_historical_command_handles_split_and_ddf_csv_formats(): void
    {
        $basePath = storage_path('framework/testing/historical-import');
        File::deleteDirectory($basePath);

        File::ensureDirectoryExists($basePath . '/2016/newdata');
        File::ensureDirectoryExists($basePath . '/2019/newdata');

        File::put($basePath . '/2016/newdata/final-digItems.csv', implode("\n", [
            '"project","guid","timestamp","discretionaryState","description","subject.link","subject.thumbnailUrl"',
            '"Project One","11111111-1111-1111-1111-111111111111","2016-10-24T11:39:22Z","Transcribed","First split row","http://example.test/task/1","http://example.test/thumb/1.jpg"',
            '"Project One","22222222-2222-2222-2222-222222222222","2016-10-24T12:15:00Z","Transcribed","Second split row","http://example.test/task/2","http://example.test/thumb/2.jpg"',
            '',
        ]));

        File::put($basePath . '/2019/newdata/ddf.csv', implode("\n", [
            '"project","description","guid","timestamp","center","discretionaryState.workUnit","id"',
            '"Project Two","First ddf row","33333333-3333-3333-3333-333333333333","2019-10-16 15:55:05","Les herbonautes","3.92","1"',
            '"Project Two","Second ddf row","44444444-4444-4444-4444-444444444444","2019-10-16 16:10:05","DigiVol","1","2"',
            '',
        ]));

        $exitCode = Artisan::call('import:historical', [
            '--path' => $basePath,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('events', ['slug' => '2016']);
        $this->assertDatabaseHas('events', ['slug' => '2019']);
        $this->assertDatabaseHas('sources', ['slug' => 'digivol']);
        $this->assertDatabaseHas('sources', ['slug' => 'les-herbonautes']);
        $this->assertDatabaseMissing('sources', ['slug' => 'historical-csv']);
        $this->assertDatabaseHas('transcription_records', [
            'source_guid' => '11111111-1111-1111-1111-111111111111',
            'center' => 'DigiVol',
        ]);

        $this->assertSame(4, TranscriptionRecord::count());
        $this->assertSame(4, ChartAggregateHourly::count(), 'Hourly aggregates should be created for each record hour/center bucket');

        $event2016 = Event::where('slug', '2016')->firstOrFail();
        $event2019 = Event::where('slug', '2019')->firstOrFail();
        $digiVol = Source::where('slug', 'digivol')->firstOrFail();
        $herbonautes = Source::where('slug', 'les-herbonautes')->firstOrFail();

        $this->assertTrue($event2016->sources()->whereKey($digiVol->id)->exists());
        $this->assertTrue($event2019->sources()->whereKey($digiVol->id)->exists());
        $this->assertTrue($event2019->sources()->whereKey($herbonautes->id)->exists());
        $this->assertSame('spring', $event2016->season);
        $this->assertSame('spring', $event2019->season);
        $this->assertSame('WeDigBio 2016', $event2016->name);
        $this->assertSame('WeDigBio 2019', $event2019->name);
    }
}

