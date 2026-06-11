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

namespace App\Jobs;

use App\Models\TranscriptionRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AggregateHourlyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    /**
     * Rebuild hourly aggregates from raw transcription records for one event.
     *
     * Aggregates are grouped by hour boundary and center label, then upserted
     * in batches so repeated executions remain safe and deterministic.
     */
    public function handle(): void
    {
        $buckets = [];
        $now = now()->toDateTimeString();

        TranscriptionRecord::where('event_id', $this->eventId)
            ->select('id', 'center', 'timestamp_utc', 'work_unit', 'raw_count')
            ->lazyById(500)
            ->each(function (TranscriptionRecord $record) use (&$buckets, $now): void {
                // Truncate to hour boundary
                $hour = $record->timestamp_utc->startOfHour()->toDateTimeString();
                $key  = $record->center . '|' . $hour;

                if (! isset($buckets[$key])) {
                    $buckets[$key] = [
                        'event_id'        => $this->eventId,
                        'bucket_hour_utc' => $hour,
                        'center'          => $record->center,
                        'weighted_sum'    => 0.0,
                        'raw_sum'         => 0,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }

                $buckets[$key]['weighted_sum'] += (float) $record->work_unit;
                $buckets[$key]['raw_sum']      += (int) $record->raw_count;
            });

        if (empty($buckets)) {
            return;
        }

        foreach (array_chunk(array_values($buckets), 200) as $batch) {
            DB::table('chart_aggregates_hourly')->upsert(
                $batch,
                ['event_id', 'bucket_hour_utc', 'center'],
                ['weighted_sum', 'raw_sum', 'updated_at'],
            );
        }
    }
}

