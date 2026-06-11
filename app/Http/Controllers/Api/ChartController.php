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

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChartController extends Controller
{
    private function meta(Event $event): array
    {
        return [
            'event'        => $event->slug,
            'generated_at' => now()->toIso8601String(),
            'is_live'      => $event->is_live,
            'metric_mode'  => 'weighted',
            'bucket_size'  => $this->bucketSize($event),
        ];
    }

    private function toIsoHour(mixed $hour): string
    {
        if ($hour instanceof \DateTimeInterface) {
            return Carbon::instance($hour)->toIso8601String();
        }

        return Carbon::parse((string) $hour, 'UTC')->toIso8601String();
    }

    private function timeColumn(Event $event): string
    {
        // Live dashboards should reflect newly picked-up rows, not historical source timestamps.
        return $event->is_live ? 'created_at' : 'timestamp_utc';
    }

    /**
     * For live events, use per-minute buckets during the first hour so early
     * activity is visible at useful resolution. Switch to per-hour after that.
     * Non-live events always use per-hour (or the pre-built aggregate table).
     */
    private function bucketSize(Event $event): string
    {
        if (! $event->is_live || ! $event->starts_at) {
            return 'hour';
        }

        // Event not yet started, or started less than 60 minutes ago → minute buckets
        if (now()->lessThan($event->starts_at) || now()->diffInMinutes($event->starts_at) < 60) {
            return 'minute';
        }

        return 'hour';
    }

    private function bucketExpr(Event $event): string
    {
        $col = $this->timeColumn($event);

        return $this->bucketSize($event) === 'minute'
            ? sprintf("DATE_FORMAT(%s, '%%Y-%%m-%%d %%H:%%i:00')", $col)
            : sprintf("DATE_FORMAT(%s, '%%Y-%%m-%%d %%H:00:00')", $col);
    }

    public function totalActivity(Event $event): JsonResponse
    {
        abort_unless($event->is_public, 404);

        $timeColumn = $this->timeColumn($event);

        $records = $event->transcriptionRecords()
            ->selectRaw("{$timeColumn} as chart_ts")
            ->select('work_unit', 'raw_count')
            ->orderBy('chart_ts')
            ->get();

        $cumulativeWeighted = 0;
        $cumulativeRaw = 0;

        $series = $records->map(function ($r) use (&$cumulativeWeighted, &$cumulativeRaw) {
            $cumulativeWeighted += (float) $r->work_unit;
            $cumulativeRaw += $r->raw_count;

            return [
                'ts'                  => Carbon::parse((string) $r->chart_ts, 'UTC')->toIso8601String(),
                'weighted'            => (float) $r->work_unit,
                'raw'                 => $r->raw_count,
                'cumulative_weighted' => round($cumulativeWeighted, 4),
                'cumulative_raw'      => $cumulativeRaw,
            ];
        });

        return response()->json(['meta' => $this->meta($event), 'series' => $series]);
    }

    public function hourlyActivity(Event $event): JsonResponse
    {
        abort_unless($event->is_public, 404);

        if ($event->is_live) {
            $rows = $event->transcriptionRecords()
                ->selectRaw($this->bucketExpr($event) . ' as bucket_hour_utc')
                ->selectRaw('SUM(work_unit) as weighted')
                ->selectRaw('SUM(raw_count) as raw')
                ->groupBy('bucket_hour_utc')
                ->orderBy('bucket_hour_utc')
                ->get();
        } else {
            $rows = $event->chartAggregatesHourly()
                ->select('bucket_hour_utc', DB::raw('SUM(weighted_sum) as weighted'), DB::raw('SUM(raw_sum) as raw'))
                ->groupBy('bucket_hour_utc')
                ->orderBy('bucket_hour_utc')
                ->get();

            // Fallback for events where queued aggregation has not run yet.
            if ($rows->isEmpty()) {
                $rows = $event->transcriptionRecords()
                    ->selectRaw($this->bucketExpr($event) . ' as bucket_hour_utc')
                    ->selectRaw('SUM(work_unit) as weighted')
                    ->selectRaw('SUM(raw_count) as raw')
                    ->groupBy('bucket_hour_utc')
                    ->orderBy('bucket_hour_utc')
                    ->get();
            }
        }

        $series = $rows->map(fn ($r) => [
            'hour'     => $this->toIsoHour($r->bucket_hour_utc),
            'weighted' => round((float) $r->weighted, 4),
            'raw'      => (int) $r->raw,
        ]);

        return response()->json(['meta' => $this->meta($event), 'series' => $series]);
    }

    public function activityByCenter(Event $event): JsonResponse
    {
        abort_unless($event->is_public, 404);

        if ($event->is_live) {
            $rows = $event->transcriptionRecords()
                ->select('center')
                ->selectRaw($this->bucketExpr($event) . ' as bucket_hour_utc')
                ->selectRaw('SUM(work_unit) as weighted_sum')
                ->selectRaw('SUM(raw_count) as raw_sum')
                ->groupBy('center', 'bucket_hour_utc')
                ->orderBy('center')
                ->orderBy('bucket_hour_utc')
                ->get();
        } else {
            $rows = $event->chartAggregatesHourly()
                ->select('center', 'bucket_hour_utc', 'weighted_sum', 'raw_sum')
                ->orderBy('center')
                ->orderBy('bucket_hour_utc')
                ->get();

            // Fallback for events where queued aggregation has not run yet.
            if ($rows->isEmpty()) {
                $rows = $event->transcriptionRecords()
                    ->select('center')
                    ->selectRaw($this->bucketExpr($event) . ' as bucket_hour_utc')
                    ->selectRaw('SUM(work_unit) as weighted_sum')
                    ->selectRaw('SUM(raw_count) as raw_sum')
                    ->groupBy('center', 'bucket_hour_utc')
                    ->orderBy('center')
                    ->orderBy('bucket_hour_utc')
                    ->get();
            }
        }

        $grouped = $rows->groupBy('center')->map(function ($centerRows, $centerName) {
            $cumulativeWeighted = 0;
            $cumulativeRaw = 0;

            $points = $centerRows->map(function ($r) use (&$cumulativeWeighted, &$cumulativeRaw) {
                $cumulativeWeighted += (float) $r->weighted_sum;
                $cumulativeRaw += $r->raw_sum;

                return [
                    'hour'                => $this->toIsoHour($r->bucket_hour_utc),
                    'weighted'            => round((float) $r->weighted_sum, 4),
                    'cumulative_weighted' => round($cumulativeWeighted, 4),
                    'raw'                 => $r->raw_sum,
                    'cumulative_raw'      => $cumulativeRaw,
                ];
            });

            return ['center' => $centerName, 'points' => $points];
        })->values();

        return response()->json(['meta' => $this->meta($event), 'series' => $grouped]);
    }

    public function summary(Event $event): JsonResponse
    {
        abort_unless($event->is_public, 404);

        $timeColumn = $this->timeColumn($event);

        $totals = $event->transcriptionRecords()
            ->selectRaw("SUM(work_unit) as weighted_total, SUM(raw_count) as raw_total, COUNT(DISTINCT center) as center_count, MIN({$timeColumn}) as first_ts, MAX({$timeColumn}) as latest_ts")
            ->first();

        return response()->json([
            'meta'    => $this->meta($event),
            'summary' => [
                'weighted_total'   => round((float) ($totals->weighted_total ?? 0), 4),
                'raw_total'        => (int) ($totals->raw_total ?? 0),
                'center_count'     => (int) ($totals->center_count ?? 0),
                'first_timestamp'  => $totals->first_ts,
                'latest_timestamp' => $totals->latest_ts,
            ],
        ]);
    }
}
