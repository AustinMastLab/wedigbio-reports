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

class HttpJsonSourceAdapter implements SourceAdapter
{
    public function fetchPage(
        Event $event,
        Source $source,
        ?string $pageToken = null,
        ?CarbonInterface $since = null,
    ): SourcePage {
        if (blank($source->base_url)) {
            return SourcePage::empty();
        }

        $response = Http::timeout(30)->acceptJson()->get($source->base_url, array_filter([
            'event' => $event->slug,
            'page_token' => $pageToken,
            'since' => $since?->toIso8601String(),
        ], fn ($value) => filled($value)));

        $response->throw();

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        $rows = Arr::get($payload, 'data');
        if (! is_array($rows)) {
            $rows = Arr::get($payload, 'items');
        }
        if (! is_array($rows)) {
            $rows = Arr::get($payload, 'records');
        }
        if (! is_array($rows)) {
            $rows = [];
        }

        $records = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $timestamp = Arr::get($row, 'timestamp_utc') ?? Arr::get($row, 'timestamp');
            $center = Arr::get($row, 'center');

            if (blank($timestamp) || blank($center)) {
                continue;
            }

            $records[] = new NormalizedRecord(
                sourceGuid: (string) (Arr::get($row, 'source_guid') ?? Arr::get($row, 'guid') ?? Arr::get($row, 'id') ?? hash('sha256', json_encode($row))),
                center: (string) $center,
                project: Arr::get($row, 'project'),
                description: Arr::get($row, 'description'),
                timestampUtc: CarbonImmutable::parse((string) $timestamp)->utc(),
                workUnit: (float) (Arr::get($row, 'work_unit') ?? Arr::get($row, 'workUnit') ?? 1),
                rawCount: (int) (Arr::get($row, 'raw_count') ?? Arr::get($row, 'rawCount') ?? 1),
                payload: $row,
            );
        }

        return new SourcePage(
            records: $records,
            nextPageToken: Arr::get($payload, 'next_page_token')
                ?? Arr::get($payload, 'meta.next_page_token')
                ?? Arr::get($payload, 'links.next'),
        );
    }
}
