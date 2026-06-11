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
use Illuminate\Http\Client\PendingRequest;

class BiospexJsonSourceAdapter implements SourceAdapter
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

        $timestampNow = now()->toIso8601String();
        // Parse rowStart from pageToken (should be numeric offset or null)
        $rowStart = (int) ($pageToken ?? 0);
        // Don't allow negative offset
        $rowStart = max(0, $rowStart);

        $request = $this->applyAuth(
            Http::timeout(30)->acceptJson(),
            $source,
        );

        $query = array_filter([
            'event' => $event->slug,
            'rowStart' => $rowStart,
            'timestampStart' => $since?->toIso8601String(),
            'timestampEnd' => $timestampNow,
        ], fn ($value) => filled($value));

        $query = array_merge($query, $this->queryAuthParams($source));

        $response = $request->get($source->base_url, $query);

        $response->throw();

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];
        $rows = Arr::get($payload, 'items', []);

        $records = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $timestamp = Arr::get($row, 'timestamp');
            $guid = Arr::get($row, 'guid');

            if (blank($timestamp) || blank($guid)) {
                continue;
            }

            $center = (string) (Arr::get($row, 'project') ?: $source->name ?: $source->slug);
            $project = Arr::get($row, 'project');
            $workUnit = Arr::get($row, 'discretionaryState.workUnit')
                ?? Arr::get($row, 'discretionarystate_workunit')
                ?? 1;

            $records[] = new NormalizedRecord(
                sourceGuid: (string) $guid,
                center: $center,
                project: is_string($project) ? $project : null,
                description: Arr::get($row, 'description'),
                timestampUtc: CarbonImmutable::parse((string) $timestamp)->utc(),
                workUnit: is_numeric($workUnit) ? (float) $workUnit : 1.0,
                rawCount: 1,
                payload: $row,
            );
        }

        return new SourcePage(
            records: $records,
            nextPageToken: $this->calculateNextPageToken($payload, $rowStart),
        );
    }

    private function calculateNextPageToken(array $payload, int $currentRowStart): ?string
    {
        $start = (int) Arr::get($payload, 'start', 0);
        $rows = (int) Arr::get($payload, 'rows', self::DEFAULT_ROWS);
        $numFound = (int) Arr::get($payload, 'numFound', 0);

        // Calculate next offset: current start + page size
        $nextRowStart = $start + $rows;

        // Only return token if there are more records to fetch
        if ($nextRowStart < $numFound) {
            return (string) $nextRowStart;
        }

        return null;
    }

    private function applyAuth(PendingRequest $request, Source $source): PendingRequest
    {
        $config = is_array($source->auth_config) ? $source->auth_config : [];

        return match ($source->auth_type) {
            'bearer_token' => filled($config['token'] ?? null)
                ? $request->withToken((string) $config['token'])
                : $request,
            'basic_auth' => filled($config['username'] ?? null)
                ? $request->withBasicAuth((string) $config['username'], (string) ($config['password'] ?? ''))
                : $request,
            'header_key' => filled($config['header'] ?? null)
                ? $request->withHeaders([(string) $config['header'] => (string) ($config['header_value'] ?? '')])
                : $request,
            default => $request,
        };
    }

    /**
     * @return array<string, string>
     */
    private function queryAuthParams(Source $source): array
    {
        if ($source->auth_type !== 'query_secret') {
            return [];
        }

        $config = is_array($source->auth_config) ? $source->auth_config : [];
        $param = $config['param'] ?? null;

        if (! filled($param)) {
            return [];
        }

        return [(string) $param => (string) ($config['value'] ?? '')];
    }
}
