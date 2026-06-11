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

namespace App\Services;

use App\Jobs\AggregateHourlyJob;
use App\Ingestion\Data\NormalizedRecord;
use App\Models\Event;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HistoricalTranscriptionImporter
{
    /**
     * Import historical CSV exports into canonical event/source/transcription tables.
     *
     * Split-year source files are mapped by filename aliases and ddf.csv years map
     * by center/description text. Records are upserted by dedupe key.
     */
    private const DEFAULT_SOURCE_SLUG = 'notes-from-nature';

    private const FILE_SOURCE_ALIASES = [
        'final-citems' => 'citsciscribe',
        'citems' => 'citsciscribe',
        'final-sitems' => 'smithsonian',
        'sitems' => 'smithsonian',
        'final-sitcitems' => 'smithsonian',
        'sitcitems' => 'smithsonian',
        'final-digitems' => 'digivol',
        'digitems' => 'digivol',
        'final-heritems' => 'les-herbonautes',
        'heritems' => 'les-herbonautes',
        'final-n4items' => 'notes-from-nature',
        'n4items' => 'notes-from-nature',
    ];

    private const TEXT_SOURCE_ALIASES = [
        'doedat' => 'doedat',
        'digivol' => 'digivol',
        'herbonaut' => 'les-herbonautes',
        'sitc' => 'smithsonian',
        'smithsonian' => 'smithsonian',
        'citsciscribe' => 'citsciscribe',
        'notes from nature' => 'notes-from-nature',
        'biospex' => 'notes-from-nature',
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    private const LEGACY_SOURCES = [
        'digivol' => [
            'name' => 'DigiVol',
            'base_url' => 'http://volunteer.ala.org.au/ws/transcriptionFeed.json',
            'adapter_type' => 'digivol_json',
            'supports_weighting' => false,
            'is_active' => true,
            'notes' => 'Legacy WeDigBio endpoint from historical R scripts.',
            'auth_type' => null,
            'auth_config' => null,
        ],
        'les-herbonautes' => [
            'name' => 'Les Herbonautes',
            'base_url' => 'http://lesherbonautes.mnhn.fr/contributions/interval/json',
            'adapter_type' => 'api_json',
            'supports_weighting' => true,
            'is_active' => false,
            'notes' => 'Legacy WeDigBio endpoint from historical R scripts.',
            'auth_type' => null,
            'auth_config' => null,
        ],
        'smithsonian' => [
            'name' => 'Smithsonian Transcription Center',
            'base_url' => 'https://transcription.si.edu/transcribr_wedigbio/activity-feed',
            'adapter_type' => 'api_json',
            'supports_weighting' => false,
            'is_active' => false,
            'notes' => 'Requires secret key query parameter for legacy feed access.',
            'auth_type' => 'query_secret',
            'auth_config' => ['param' => 'secret_key', 'value' => ''],
        ],
        'notes-from-nature' => [
            'name' => 'Notes From Nature (BioSpex)',
            'base_url' => 'https://api.biospex.org/v1/wedigbio-dashboard',
            'adapter_type' => 'biospex_json',
            'supports_weighting' => false,
            'is_active' => true,
            'notes' => 'BioSpex v1 endpoint for Notes From Nature activity.',
            'auth_type' => 'bearer_token',
            'auth_config' => ['token' => ''],
        ],
        'doedat' => [
            'name' => 'DoeDat',
            'base_url' => 'https://www.doedat.be/ws/transcriptionFeed.json',
            'adapter_type' => 'api_json',
            'supports_weighting' => false,
            'is_active' => false,
            'notes' => 'Legacy WeDigBio endpoint from historical R scripts.',
            'auth_type' => null,
            'auth_config' => null,
        ],
        'citsciscribe' => [
            'name' => 'CitSciScribe',
            'base_url' => 'http://citsciscribe.org/api/WeDigBioAPI',
            'adapter_type' => 'api_json',
            'supports_weighting' => false,
            'is_active' => false,
            'notes' => 'Legacy WeDigBio endpoint from historical R scripts.',
            'auth_type' => null,
            'auth_config' => null,
        ],
    ];

    private const CHUNK_SIZE = 1000;

    /**
     * @return array{events:int,files:int,rows:int,records:int}
     */
    public function import(string $basePath, ?string $eventSlug = null): array
    {
        $directories = $this->discoverEventDirectories($basePath, $eventSlug);
        $sourcesBySlug = $this->legacySourcesBySlug();

        $stats = [
            'events' => 0,
            'files' => 0,
            'rows' => 0,
            'records' => 0,
        ];

        foreach ($directories as $slug => $directory) {
            $event = $this->upsertHistoricalEvent($slug, $directory);
            $eventStats = $this->importEventDirectory($event, $sourcesBySlug, $directory);

            if ($eventStats['records'] === 0) {
                $event->forceFill([
                    'notes' => 'Imported from legacy CSVs in ' . basename($directory) . '. No transcription rows were available for this event year.',
                ])->save();
            }

            if ($eventStats['source_ids'] !== []) {
                $event->sources()->syncWithoutDetaching(
                    collect($eventStats['source_ids'])
                        ->mapWithKeys(fn (int $sourceId) => [$sourceId => ['is_enabled' => true]])
                        ->all(),
                );
            }

            $stats['events'] += 1;
            $stats['files'] += $eventStats['files'];
            $stats['rows'] += $eventStats['rows'];
            $stats['records'] += $eventStats['records'];

            (new AggregateHourlyJob($event->id))->handle();
        }

        return $stats;
    }

    /**
     * @return array<string, string>
     */
    private function discoverEventDirectories(string $basePath, ?string $eventSlug): array
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        if (! is_dir($basePath)) {
            throw new RuntimeException("Historical import path not found: {$basePath}");
        }

        if ($eventSlug !== null) {
            $directory = $basePath . DIRECTORY_SEPARATOR . $eventSlug;
            if (! is_dir($directory)) {
                throw new RuntimeException("Historical event directory not found: {$directory}");
            }

            return [$eventSlug => $directory];
        }

        $directories = [];
        foreach (glob($basePath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
            $slug = basename($directory);
            if ($this->containsImportableCsv($directory)) {
                $directories[$slug] = $directory;
            }
        }

        if ($directories === []) {
            throw new RuntimeException("No historical event directories found in {$basePath}");
        }

        ksort($directories);

        return $directories;
    }

    private function containsImportableCsv(string $directory): bool
    {
        return $this->discoverCsvFiles($directory) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function discoverCsvFiles(string $directory): array
    {
        $paths = [];
        $candidates = [];

        $newDataPath = $directory . DIRECTORY_SEPARATOR . 'newdata';
        if (is_dir($newDataPath)) {
            $candidates = glob($newDataPath . DIRECTORY_SEPARATOR . '*.csv') ?: [];
        } else {
            $candidates = glob($directory . DIRECTORY_SEPARATOR . '*.csv') ?: [];
        }

        foreach ($candidates as $candidate) {
            $basename = basename($candidate);

            if ($basename === 'ddf.csv' || str_ends_with($basename, 'Items.csv')) {
                $paths[] = $candidate;
            }
        }

        sort($paths);

        return $paths;
    }

    private function upsertHistoricalEvent(string $slug, string $directory): Event
    {
        [$year, $season, $displayAlias] = $this->deriveEventMetadata($slug);

        return Event::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $this->formatEventName($slug),
                'year' => $year,
                'season' => $season,
                'starts_at' => CarbonImmutable::parse(sprintf('%04d-01-01 00:00:00', $year), 'UTC'),
                'ends_at' => CarbonImmutable::parse(sprintf('%04d-12-31 23:59:59', $year), 'UTC'),
                'is_public' => true,
                'is_live' => false,
                'is_archived' => true,
                'display_alias' => $displayAlias,
                'notes' => 'Imported from legacy CSVs in ' . basename($directory),
            ],
        );
    }

    /**
     * @param array<string, Source> $sourcesBySlug
     * @return array{files:int,rows:int,records:int,source_ids:array<int,int>}
     */
    private function importEventDirectory(Event $event, array $sourcesBySlug, string $directory): array
    {
        $files = $this->discoverCsvFiles($directory);
        $stats = ['files' => 0, 'rows' => 0, 'records' => 0, 'source_ids' => []];
        $minTimestamp = null;
        $maxTimestamp = null;

        foreach ($files as $filePath) {
            $fileStats = $this->importCsvFile($event, $sourcesBySlug, $filePath, $minTimestamp, $maxTimestamp);
            $stats['files'] += 1;
            $stats['rows'] += $fileStats['rows'];
            $stats['records'] += $fileStats['records'];
            foreach ($fileStats['source_ids'] as $sourceId) {
                $stats['source_ids'][$sourceId] = $sourceId;
            }
            $minTimestamp = $fileStats['minTimestamp'] ?? $minTimestamp;
            $maxTimestamp = $fileStats['maxTimestamp'] ?? $maxTimestamp;
        }

        if ($minTimestamp !== null && $maxTimestamp !== null) {
            $event->forceFill([
                'starts_at' => $minTimestamp,
                'ends_at' => $maxTimestamp,
            ])->save();
        }

        return $stats;
    }

    /**
     * @param array<string, Source> $sourcesBySlug
     * @return array<string, mixed>
     */
    private function importCsvFile(Event $event, array $sourcesBySlug, string $filePath, ?CarbonImmutable $currentMinTimestamp, ?CarbonImmutable $currentMaxTimestamp): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: {$filePath}");
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);

            return ['rows' => 0, 'records' => 0, 'minTimestamp' => $currentMinTimestamp, 'maxTimestamp' => $currentMaxTimestamp, 'source_ids' => []];
        }

        $buffer = [];
        $rows = 0;
        $records = 0;
        $minTimestamp = $currentMinTimestamp;
        $maxTimestamp = $currentMaxTimestamp;
        $sourceIds = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === false) {
                continue;
            }

            $row = $this->normalizeCsvRow($headers, $line);
            $source = $this->resolveSourceForRow($row, $filePath, $sourcesBySlug);
            $record = $this->rowToNormalizedRecord($row, $event, $source, $filePath);
            if ($record === null) {
                continue;
            }

            $rows += 1;
            $records += 1;
            $minTimestamp = $minTimestamp ? $minTimestamp->min($record->timestampUtc) : $record->timestampUtc;
            $maxTimestamp = $maxTimestamp ? $maxTimestamp->max($record->timestampUtc) : $record->timestampUtc;
            $sourceIds[$source->id] = $source->id;
            $buffer[] = $record->toUpsertRow($event->id, $source->id);

            if (count($buffer) >= self::CHUNK_SIZE) {
                DB::table('transcription_records')->upsert(
                    $buffer,
                    ['dedupe_key'],
                    ['center', 'project', 'description', 'timestamp_utc', 'work_unit', 'raw_count', 'payload_json', 'updated_at'],
                );
                $buffer = [];
            }
        }

        fclose($handle);

        if ($buffer !== []) {
            DB::table('transcription_records')->upsert(
                $buffer,
                ['dedupe_key'],
                ['center', 'project', 'description', 'timestamp_utc', 'work_unit', 'raw_count', 'payload_json', 'updated_at'],
            );
        }

        return [
            'rows' => $rows,
            'records' => $records,
            'minTimestamp' => $minTimestamp,
            'maxTimestamp' => $maxTimestamp,
            'source_ids' => array_values($sourceIds),
        ];
    }

    /**
     * @return array<string, Source>
     */
    private function legacySourcesBySlug(): array
    {
        $sources = [];

        foreach (self::LEGACY_SOURCES as $slug => $defaults) {
            $source = Source::firstOrNew(['slug' => $slug]);

            $source->name = $source->name ?: (string) $defaults['name'];
            $source->base_url = $source->base_url ?: $defaults['base_url'];
            $source->adapter_type = $source->adapter_type ?: (string) $defaults['adapter_type'];
            $source->supports_weighting = (bool) ($source->exists ? $source->supports_weighting : $defaults['supports_weighting']);
            $source->is_active = (bool) ($source->exists ? $source->is_active : $defaults['is_active']);
            $source->notes = $source->notes ?: (string) $defaults['notes'];
            $source->auth_type = $source->auth_type ?: $defaults['auth_type'];

            if ((blank($source->auth_config) || $source->auth_config === []) && is_array($defaults['auth_config'])) {
                $source->auth_config = $defaults['auth_config'];
            }

            $source->save();
            $sources[$slug] = $source;
        }

        return $sources;
    }

    /**
     * @param array<string, string|null> $row
     * @param array<string, Source> $sourcesBySlug
     */
    #[\NoDiscard]
    private function resolveSourceForRow(array $row, string $filePath, array $sourcesBySlug): Source
    {
        $basename = strtolower(pathinfo($filePath, PATHINFO_FILENAME));

        if ($basename === 'ddf') {
            $center = $this->firstFilledValue($row, ['center']) ?? '';
            $description = $this->firstFilledValue($row, ['description']) ?? '';
            $sourceSlug = $this->resolveSourceSlugFromText($center) ?? $this->resolveSourceSlugFromText($description);

            if ($sourceSlug !== null && isset($sourcesBySlug[$sourceSlug])) {
                return $sourcesBySlug[$sourceSlug];
            }

            return $sourcesBySlug[self::DEFAULT_SOURCE_SLUG] ?? reset($sourcesBySlug);
        }

        $sourceSlug = $this->resolveSourceSlugFromFilename($basename);
        if ($sourceSlug !== null && isset($sourcesBySlug[$sourceSlug])) {
            return $sourcesBySlug[$sourceSlug];
        }

        return $sourcesBySlug[self::DEFAULT_SOURCE_SLUG] ?? reset($sourcesBySlug);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string|null> $line
     * @return array<string, string|null>
     */
    private function normalizeCsvRow(array $headers, array $line): array
    {
        $row = [];
        $max = max(count($headers), count($line));

        for ($i = 0; $i < $max; $i++) {
            $header = $headers[$i] ?? null;
            if ($header === null || $header === '') {
                continue;
            }

            $value = $line[$i] ?? null;
            $row[$header] = $value;
            $row[strtolower($header)] = $value;
            $row[$this->normalizeHeader($header)] = $value;
        }

        return $row;
    }

    /**
     * @param array<string, string|null> $row
     */
    private function rowToNormalizedRecord(array $row, Event $event, Source $source, string $filePath): ?NormalizedRecord
    {
        $timestamp = $this->firstFilledValue($row, [
            'timestamp', 'timestamp_utc', 'created_at', 'completed_at', 'transcribed_at', 'time',
        ]);

        if ($timestamp === null) {
            return null;
        }

        $guid = $this->firstFilledValue($row, ['guid', 'id', 'uuid'])
            ?? hash('sha256', $filePath . '|' . json_encode($row));

        $project = $this->firstFilledValue($row, [
            'project', 'project.name', 'project_name', 'projectid', 'project_id',
        ]);

        $center = $this->determineCenter($row, $filePath, $project, $event, $source);
        $description = $this->firstFilledValue($row, ['description', 'subject.description']);
        $workUnit = $this->firstNumericValue($row, [
            'discretionaryState.workUnit',
            'discretionaryState_workUnit',
            'discretionarystate_workunit',
            'discretionarystate_workunit',
            'discretionarystate_workunit',
            'work_unit',
            'workUnit',
        ]) ?? 1.0;
        $rawCount = $this->firstNumericValue($row, ['raw_count', 'rawCount']) ?? 1;

        try {
            $timestampUtc = CarbonImmutable::parse((string) $timestamp, 'UTC')->utc();
        } catch (\Throwable) {
            return null;
        }

        return new NormalizedRecord(
            sourceGuid: (string) $guid,
            center: (string) $center,
            project: is_string($project) ? $project : null,
            description: is_string($description) ? $description : null,
            timestampUtc: $timestampUtc,
            workUnit: (float) $workUnit,
            rawCount: (int) $rawCount,
            payload: $row,
        );
    }

    /**
     * @param array<string, string|null> $row
     */
    private function determineCenter(array $row, string $filePath, mixed $project, Event $event, Source $source): string
    {
        $center = $this->firstFilledValue($row, ['center']);
        if ($center !== null) {
            return (string) $center;
        }

        $sourceLabel = $this->sourceLabelForSlug($source->slug);
        if ($sourceLabel !== null) {
            return $sourceLabel;
        }


        return is_string($project) && $project !== '' ? $project : ($event->name ?: $event->slug);
    }

    private function sourceLabelForSlug(string $slug): ?string
    {
        return match ($slug) {
            'digivol' => 'DigiVol',
            'les-herbonautes' => 'Les herbonautes',
            'smithsonian' => 'SITC',
            'notes-from-nature' => 'Notes From Nature',
            'citsciscribe' => 'CitSciScribe',
            'doedat' => 'DoeDat',
            default => null,
        };
    }

    private function resolveSourceSlugFromFilename(string $filename): ?string
    {
        $filename = strtolower($filename);

        foreach (self::FILE_SOURCE_ALIASES as $needle => $slug) {
            if ($filename === $needle) {
                return $slug;
            }
        }

        return null;
    }

    private function resolveSourceSlugFromText(string $text): ?string
    {
        $text = $text |> trim(...) |> strtolower(...);
        if ($text === '') {
            return null;
        }

        foreach (self::TEXT_SOURCE_ALIASES as $needle => $slug) {
            if (str_contains($text, $needle)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * @param array<string, string|null> $row
     * @param array<int, string> $keys
     */
    private function firstFilledValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            foreach ([$key, strtolower($key), $this->normalizeHeader($key)] as $candidate) {
                if (! array_key_exists($candidate, $row)) {
                    continue;
                }

                $value = $row[$candidate];
                if ($value === null) {
                    continue;
                }

                $value = trim((string) $value);
                if ($value === '' || strtoupper($value) === 'NA') {
                    continue;
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, string|null> $row
     * @param array<int, string> $keys
     */
    private function firstNumericValue(array $row, array $keys): ?float
    {
        $value = $this->firstFilledValue($row, $keys);
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array{0:int,1:?string,2:?string}
     */
    #[\NoDiscard]
    private function deriveEventMetadata(string $slug): array
    {
        if (! preg_match('/^(\d{4})(?:-(.+))?$/', $slug, $matches)) {
            $year = (int) now()->year;
            return [$year, null, null];
        }

        $year = (int) $matches[1];
        $suffix = $matches[2] ?? null;
        $normalizedSuffix = (string) $suffix |> trim(...) |> strtolower(...);
        $season = str_contains($normalizedSuffix, 'fall') ? Event::SEASON_FALL : Event::SEASON_SPRING;

        if ($suffix === null || $suffix === '') {
            return [$year, $season, null];
        }

        return [$year, $season, $normalizedSuffix === 'lite' ? (string) $year : null];
    }

    private function formatEventName(string $slug): string
    {
        $pretty = str_replace('-', ' ', $slug);

        return 'WeDigBio ' . Str::headline($pretty);
    }

    private function normalizeHeader(string $header): string
    {
        $header = $header |> trim(...) |> strtolower(...);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }
}
