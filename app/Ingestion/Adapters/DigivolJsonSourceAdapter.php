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

namespace App\Ingestion\Adapters;

use App\Ingestion\Contracts\SourceAdapter;
use App\Ingestion\Data\NormalizedRecord;
use App\Ingestion\Data\SourcePage;
use App\Models\Event;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class DigivolJsonSourceAdapter implements SourceAdapter
{
    private const DEFAULT_ROWS = 100;

    public function fetchPage(
        Event $event,
        Source $source,
        ?string $pageToken = null,
        ?CarbonInterface $since = null,
    ): SourcePage {
        if (blank($source->base_url)) {
            return SourcePage::empty();
        }

        // Parse rowStart from pageToken (should be numeric offset or null)
        $rowStart = (int) ($pageToken ?? 0);
        // Don't allow negative offset
        $rowStart = max(0, $rowStart);

        $response = Http::timeout(30)->acceptJson()->get($source->base_url, array_filter([
            'event' => $event->slug,
            'rowStart' => $rowStart,
            'since' => $since?->toIso8601String(),
        ], fn ($value) => filled($value)));

        $response->throw();

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        $rows = Arr::get($payload, 'items');
        if (! is_array($rows)) {
            $rows = Arr::get($payload, 'records');
        }
        if (! is_array($rows)) {
            $rows = Arr::get($payload, 'data');
        }
        if (! is_array($rows)) {
            $rows = [];
        }

        $records = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $timestamp = Arr::get($row, 'timestamp')
                ?? Arr::get($row, 'timestamp_utc')
                ?? Arr::get($row, 'created_at')
                ?? Arr::get($row, 'completed_at')
                ?? Arr::get($row, 'transcribed_at');

            if (blank($timestamp)) {
                continue;
            }

            $guid = Arr::get($row, 'guid')
                ?? Arr::get($row, 'id')
                ?? Arr::get($row, 'uuid')
                ?? hash('sha256', json_encode($row));

            $center = $source->name ?: $source->slug;

            $project = Arr::get($row, 'project.name')
                ?? (is_string(Arr::get($row, 'project')) ? Arr::get($row, 'project') : null);

            $description = Arr::get($row, 'description')
                ?? Arr::get($row, 'subject')
                ?? Arr::get($row, 'task.name');

            $workUnit = Arr::get($row, 'work_unit')
                ?? Arr::get($row, 'workUnit')
                ?? Arr::get($row, 'discretionaryState.workUnit')
                ?? 1;

            $rawCount = Arr::get($row, 'raw_count')
                ?? Arr::get($row, 'rawCount')
                ?? 1;

            $records[] = new NormalizedRecord(
                sourceGuid: (string) $guid,
                center: (string) $center,
                project: is_string($project) ? $project : null,
                description: is_string($description) ? $description : null,
                timestampUtc: CarbonImmutable::parse((string) $timestamp)->utc(),
                workUnit: is_numeric($workUnit) ? (float) $workUnit : 1.0,
                rawCount: is_numeric($rawCount) ? (int) $rawCount : 1,
                payload: $row,
            );
        }

        return new SourcePage(
            records: $records,
            nextPageToken: $this->calculateNextPageToken($records, $rowStart),
        );
    }

    private function calculateNextPageToken(array $records, int $currentRowStart): ?string
    {
        // Count records received
        $recordCount = count($records);

        // If we got fewer records than a full page, we've reached the end
        if ($recordCount < self::DEFAULT_ROWS) {
            return null;
        }

        // If we got a full page, there might be more records
        $nextRowStart = $currentRowStart + $recordCount;
        return (string) $nextRowStart;
    }
}
