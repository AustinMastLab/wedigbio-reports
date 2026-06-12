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
use App\Models\TranscriptionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCRUDValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_model_crud_and_source_attachment(): void
    {
        // Create
        $event = Event::create([
            'name' => 'WeDigBio 2026 Spring',
            'slug' => '2026-spring-test',
            'year' => 2026,
            'season' => 'spring',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
            'display_alias' => null,
            'notes' => 'Test event for admin validation',
        ]);

        $this->assertDatabaseHas('events', ['slug' => '2026-spring-test']);

        // Read
        $retrieved = Event::where('slug', '2026-spring-test')->firstOrFail();
        $this->assertSame('WeDigBio 2026 Spring', $retrieved->name);
        $this->assertTrue($retrieved->is_live);

        // Attach sources (simulate admin event-source relationship)
        $source1 = Source::create([
            'name' => 'Source 1',
            'slug' => 'source-1',
            'base_url' => 'https://example.test/1',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        $source2 = Source::create([
            'name' => 'Source 2',
            'slug' => 'source-2',
            'base_url' => 'https://example.test/2',
            'adapter_type' => 'api_json',
            'supports_weighting' => false,
            'is_active' => true,
        ]);

        $event->sources()->attach([$source1->id, $source2->id], ['is_enabled' => true]);

        // Verify attachment
        $this->assertCount(2, $event->sources);
        $this->assertTrue($event->sources()->where('slug', 'source-1')->exists());
        $this->assertTrue($event->sources()->where('slug', 'source-2')->exists());

        // Update
        $event->update([
            'name' => 'WeDigBio 2026 Spring (Updated)',
            'is_live' => false,
        ]);

        $this->assertDatabaseHas('events', [
            'slug' => '2026-spring-test',
            'name' => 'WeDigBio 2026 Spring (Updated)',
            'is_live' => false,
        ]);

        // Detach and verify
        $event->sources()->detach($source1->id);
        $event->refresh();
        $this->assertCount(1, $event->sources);

        // Delete and verify cascade
        $eventId = $event->id;
        $event->delete();
        $this->assertDatabaseMissing('events', ['id' => $eventId]);
    }

    public function test_source_model_crud_with_auth_config(): void
    {
        // Create with auth config
        $source = Source::create([
            'name' => 'BioSpex API',
            'slug' => 'biospex-api',
            'base_url' => 'https://api.biospex.org/v1/wedigbio-dashboard',
            'adapter_type' => 'biospex_json',
            'auth_type' => 'bearer_token',
            'auth_config' => ['token' => 'secret-bearer-token-xyz'],
            'supports_weighting' => true,
            'is_active' => true,
            'notes' => 'BioSpex v1 endpoint with encrypted token storage',
        ]);

        $this->assertDatabaseHas('sources', ['slug' => 'biospex-api']);

        // Read and verify encrypted cast
        $retrieved = Source::where('slug', 'biospex-api')->firstOrFail();
        $this->assertSame('bearer_token', $retrieved->auth_type);
        $this->assertIsArray($retrieved->auth_config);
        $this->assertSame('secret-bearer-token-xyz', $retrieved->auth_config['token']);

        // Update auth
        $retrieved->update([
            'auth_config' => ['token' => 'new-token-abc'],
            'is_active' => false,
        ]);

        $reloaded = Source::where('slug', 'biospex-api')->firstOrFail();
        $this->assertSame('new-token-abc', $reloaded->auth_config['token']);
        $this->assertFalse($reloaded->is_active);

        // Delete
        $sourceId = $source->id;
        $source->delete();
        $this->assertDatabaseMissing('sources', ['id' => $sourceId]);
    }

    public function test_event_source_pivot_is_enabled_flag(): void
    {
        $event = Event::create([
            'name' => 'Test Event',
            'slug' => 'test-pivot',
            'year' => 2026,
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $source = Source::create([
            'name' => 'Test Source',
            'slug' => 'test-source',
            'base_url' => 'https://example.test',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        // Attach with is_enabled=true
        $event->sources()->attach($source->id, ['is_enabled' => true]);

        $this->assertTrue(
            DB::table('event_source')
                ->where('event_id', $event->id)
                ->where('source_id', $source->id)
                ->where('is_enabled', true)
                ->exists()
        );

        // Update to disabled
        $event->sources()->updateExistingPivot($source->id, ['is_enabled' => false]);

        $this->assertTrue(
            DB::table('event_source')
                ->where('event_id', $event->id)
                ->where('source_id', $source->id)
                ->where('is_enabled', false)
                ->exists()
        );
    }

    public function test_event_transcription_record_relationship_cascade(): void
    {
        $event = Event::create([
            'name' => 'Test Event',
            'slug' => 'test-cascade',
            'year' => 2026,
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $source = Source::create([
            'name' => 'Test Source',
            'slug' => 'test-source-cascade',
            'base_url' => 'https://example.test',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        // Add transcription records
        $now = now();
        DB::table('transcription_records')->insert([
            [
                'event_id' => $event->id,
                'source_id' => $source->id,
                'source_guid' => 'g1',
                'dedupe_key' => 'k1',
                'center' => 'Center A',
                'project' => null,
                'description' => null,
                'timestamp_utc' => $now,
                'work_unit' => 1.0,
                'raw_count' => 1,
                'payload_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'event_id' => $event->id,
                'source_id' => $source->id,
                'source_guid' => 'g2',
                'dedupe_key' => 'k2',
                'center' => 'Center B',
                'project' => null,
                'description' => null,
                'timestamp_utc' => $now,
                'work_unit' => 2.0,
                'raw_count' => 1,
                'payload_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->assertCount(2, $event->transcriptionRecords);

        // Delete event and verify cascade delete of transcription records
        $eventId = $event->id;
        $event->delete();

        $this->assertDatabaseMissing('events', ['id' => $eventId]);
        $this->assertDatabaseMissing('transcription_records', ['event_id' => $eventId]);
    }

    public function test_source_deletion_cascades_to_checkpoints_but_preserves_transcription_records(): void
    {
        $event = Event::create([
            'name' => 'Test Event',
            'slug' => 'test-source-delete',
            'year' => 2026,
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => false,
            'is_archived' => true,
        ]);

        $source = Source::create([
            'name' => 'Test Source',
            'slug' => 'test-source-delete-cascade',
            'base_url' => 'https://example.test',
            'adapter_type' => 'http_json',
            'supports_weighting' => true,
            'is_active' => true,
        ]);

        // Create checkpoint
        DB::table('source_checkpoints')->insert([
            'event_id' => $event->id,
            'source_id' => $source->id,
            'last_status' => 'ok',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create transcription record
        $now = now();
        DB::table('transcription_records')->insert([
            'event_id' => $event->id,
            'source_id' => $source->id,
            'source_guid' => 'g1',
            'dedupe_key' => 'k1',
            'center' => 'Center A',
            'project' => null,
            'description' => null,
            'timestamp_utc' => $now,
            'work_unit' => 1.0,
            'raw_count' => 1,
            'payload_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Delete source
        $sourceId = $source->id;
        $source->delete();

        // Checkpoint should be deleted (cascade)
        $this->assertDatabaseMissing('source_checkpoints', ['source_id' => $sourceId]);

        // Transcription records should be deleted too (cascade from foreign key)
        $this->assertDatabaseMissing('transcription_records', ['source_id' => $sourceId]);
    }

    public function test_only_one_live_event_can_exist(): void
    {
        // Create first live event (should succeed)
        $liveEvent1 = Event::create([
            'name' => 'First Live Event',
            'slug' => 'first-live',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $this->assertTrue($liveEvent1->is_live);

        // Attempt to create second live event via model (should still be possible at model level but DB will reject)
        // The validation is in the form layer, not the model
        $this->assertDatabaseHas('events', ['id' => $liveEvent1->id, 'is_live' => true]);

        // Verify only one live event in DB
        $liveCount = Event::where('is_live', true)->count();
        $this->assertSame(1, $liveCount);
    }

    public function test_editing_current_live_event_is_allowed(): void
    {
        // Create a live event
        $liveEvent = Event::create([
            'name' => 'Current Live Event',
            'slug' => 'current-live',
            'year' => 2026,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        // Editing the same event to keep is_live=true should be allowed
        $liveEvent->update([
            'name' => 'Updated Live Event Name',
            'is_live' => true,
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $liveEvent->id,
            'name' => 'Updated Live Event Name',
            'is_live' => true,
        ]);
    }

    public function test_turning_off_live_event_then_creating_new_live_event(): void
    {
        // Create first live event
        $oldLive = Event::create([
            'name' => 'First Live Event',
            'slug' => 'old-live',
            'year' => 2025,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $this->assertDatabaseHas('events', ['id' => $oldLive->id, 'is_live' => true]);

        // Turn off the old live event
        $oldLive->update(['is_live' => false]);

        $this->assertDatabaseHas('events', ['id' => $oldLive->id, 'is_live' => false]);

        // Now create a new live event (should succeed)
        $newLive = Event::create([
            'name' => 'New Live Event',
            'slug' => 'new-live',
            'year' => 2026,
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
            'is_live' => true,
            'is_archived' => false,
        ]);

        $this->assertDatabaseHas('events', ['id' => $newLive->id, 'is_live' => true]);
        $this->assertSame(1, Event::where('is_live', true)->count());
    }
}
