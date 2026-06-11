<?php

namespace Database\Seeders;

use App\Jobs\AggregateHourlyJob;
use App\Models\Event;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    // Centers with realistic weighting profiles
    private const CENTERS = [
        ['name' => 'iDigBio',         'weight' => 1.0],
        ['name' => 'DigiVol',         'weight' => 1.0],
        ['name' => 'Les Herbonautes', 'weight' => 0.5],
        ['name' => 'Smithsonian',     'weight' => 1.0],
        ['name' => 'MNHN',            'weight' => 0.5],
    ];

    public function run(): void
    {
        // ── Events ────────────────────────────────────────────────────────
        $live = Event::firstOrCreate(['slug' => 'wedigbio-2026-spring'], [
            'name'       => 'WeDigBio 2026 Spring',
            'year'       => 2026,
            'season'     => 'spring',
            'starts_at'  => '2026-04-16 00:00:00',
            'ends_at'    => '2026-04-19 23:59:59',
            'is_public'  => true,
            'is_live'    => true,
            'is_archived'=> false,
            'notes'      => 'Active demo event — seeded data',
        ]);

        $archived = Event::firstOrCreate(['slug' => 'wedigbio-2025-fall'], [
            'name'       => 'WeDigBio 2025 Fall',
            'year'       => 2025,
            'season'     => 'fall',
            'starts_at'  => '2025-10-16 00:00:00',
            'ends_at'    => '2025-10-19 23:59:59',
            'is_public'  => true,
            'is_live'    => false,
            'is_archived'=> true,
            'notes'      => 'Archived demo event — seeded data',
        ]);

        // ── Sources ───────────────────────────────────────────────────────
        $biospex = Source::firstOrCreate(['slug' => 'biospex'], [
            'name'               => 'Biospex',
            'base_url'           => 'https://api.biospex.org/v1/wedigbio-dashboard',
            'adapter_type'       => 'biospex_json',
            'supports_weighting' => true,
            'is_active'          => true,
        ]);

        $digivol = Source::firstOrCreate(['slug' => 'digivol'], [
            'name'               => 'DigiVol',
            'base_url'           => 'https://volunteer.ala.org.au/ws/transcriptionFeed.json',
            'adapter_type'       => 'digivol_json',
            'supports_weighting' => false,
            'is_active'          => true,
        ]);

        // ── Attach sources to events ───────────────────────────────────────
        foreach ([$live, $archived] as $event) {
            $event->sources()->syncWithoutDetaching([
                $biospex->id => ['is_enabled' => true],
                $digivol->id => ['is_enabled' => true],
            ]);
        }

        // ── Transcription records ──────────────────────────────────────────
        $this->seedRecords($live,     $biospex, '2026-04-16', '2026-04-19', 300);
        $this->seedRecords($archived, $biospex, '2025-10-16', '2025-10-19', 250);

        // ── Build hourly aggregates from the seeded records ────────────────
        (new AggregateHourlyJob($live->id))->handle();
        (new AggregateHourlyJob($archived->id))->handle();

        $this->command?->info('DemoSeeder: seeded events, sources, records, and hourly aggregates.');
    }

    private function seedRecords(
        Event  $event,
        Source $source,
        string $startDate,
        string $endDate,
        int    $count,
    ): void {
        $start = CarbonImmutable::parse($startDate . ' 00:00:00', 'UTC');
        $end   = CarbonImmutable::parse($endDate   . ' 23:59:59', 'UTC');
        $span  = abs((int) $end->diffInSeconds($start));

        $rows = [];
        $now  = now()->toDateTimeString();

        for ($i = 0; $i < $count; $i++) {
            $center     = self::CENTERS[array_rand(self::CENTERS)];
            $ts         = $start->addSeconds(random_int(0, $span));
            $guid       = (string) Str::uuid();
            $workUnit   = $center['weight'];
            $dedupeKey  = hash('sha256', implode('|', [
                $event->id, $source->id, $guid,
                $ts->toIso8601String(), $center['name'], '',
            ]));

            $rows[] = [
                'event_id'     => $event->id,
                'source_id'    => $source->id,
                'source_guid'  => $guid,
                'dedupe_key'   => $dedupeKey,
                'center'       => $center['name'],
                'project'      => null,
                'description'  => null,
                'timestamp_utc'=> $ts->toDateTimeString(),
                'work_unit'    => $workUnit,
                'raw_count'    => 1,
                'payload_json' => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        // Upsert in chunks (idempotent)
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('transcription_records')->upsert(
                $chunk,
                ['dedupe_key'],
                ['center', 'project', 'description', 'timestamp_utc', 'work_unit', 'raw_count', 'payload_json', 'updated_at'],
            );
        }
    }
}

