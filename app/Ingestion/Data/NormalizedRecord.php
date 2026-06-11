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

namespace App\Ingestion\Data;

use Carbon\CarbonImmutable;

readonly class NormalizedRecord
{
    public function __construct(
        public string $sourceGuid,
        public string $center,
        public ?string $project,
        public ?string $description,
        public CarbonImmutable $timestampUtc,
        public float $workUnit,
        public int $rawCount,
        public array $payload = [],
    ) {}

    public function toUpsertRow(int $eventId, int $sourceId): array
    {
        $dedupeKey = hash('sha256', implode('|', [
            $eventId,
            $sourceId,
            $this->sourceGuid,
            $this->timestampUtc->toIso8601String(),
            $this->center,
            $this->project ?? '',
        ]));

        $now = CarbonImmutable::now('UTC');

        return [
            'event_id' => $eventId,
            'source_id' => $sourceId,
            'source_guid' => $this->sourceGuid,
            'dedupe_key' => $dedupeKey,
            'center' => $this->center,
            'project' => $this->project,
            'description' => $this->description,
            'timestamp_utc' => $this->timestampUtc,
            'work_unit' => round($this->workUnit, 4),
            'raw_count' => max(1, $this->rawCount),
            'payload_json' => json_encode($this->payload),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
