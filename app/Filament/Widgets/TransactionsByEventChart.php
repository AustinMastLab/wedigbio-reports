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

namespace App\Filament\Widgets;

use App\Models\ChartAggregateHourly;
use App\Models\Event;
use App\Models\TranscriptionRecord;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/**
 * Admin dashboard chart that summarizes total transactions per event.
 *
 * Labels are normalized to "{year} {season}" to avoid overly long event names,
 * and the chart supports a user-selectable bar/line rendering mode.
 */
class TransactionsByEventChart extends ChartWidget
{
    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Total Transactions by Event';

    public ?string $filter = 'bar';

    /**
     * Return the base chart type used by the widget shell.
     *
     * Dataset type switching (bar/line) is handled per-dataset in getData()
     * so changing filters does not require replacing the full chart instance.
     */
    protected function getType(): string
    {
        // Keep the base chart type stable; switch rendering via dataset type.
        return 'bar';
    }

    /**
     * Expose chart rendering filters in the widget header.
     *
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            'bar' => 'Bar chart',
            'line' => 'Line chart',
        ];
    }

    /**
     * Build chart labels and datasets from persisted transcription records.
     *
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    #[\NoDiscard]
    protected function getData(): array
    {
        $rows = Cache::remember('admin:transactions-by-event:v2', now()->addMinutes(10), function () {
            $events = Event::query()
                ->select('id', 'year', 'season', 'slug')
                ->orderBy('year')
                ->orderByRaw("CASE WHEN season = 'spring' THEN 1 WHEN season = 'fall' THEN 2 ELSE 3 END")
                ->orderBy('slug')
                ->get();

            if ($events->isEmpty()) {
                return [];
            }

            // Fast path: use pre-aggregated hourly totals per event.
            $totalsByEventId = ChartAggregateHourly::query()
                ->selectRaw('event_id, SUM(raw_sum) as total_transactions')
                ->groupBy('event_id')
                ->pluck('total_transactions', 'event_id');

            // Fallback only for events with no aggregate rows yet.
            $missingEventIds = $events->pluck('id')->diff($totalsByEventId->keys());

            if ($missingEventIds->isNotEmpty()) {
                $fallbackTotals = TranscriptionRecord::query()
                    ->selectRaw('event_id, COUNT(*) as total_transactions')
                    ->whereIn('event_id', $missingEventIds)
                    ->groupBy('event_id')
                    ->pluck('total_transactions', 'event_id');

                $totalsByEventId = $totalsByEventId->merge($fallbackTotals);
            }

            // Cache only scalar arrays to avoid unserialize issues across cache drivers.
            return $events->map(static function (Event $event) use ($totalsByEventId): array {
                return [
                    'id' => $event->id,
                    'year' => (int) $event->year,
                    'season' => (string) ($event->season ?? ''),
                    'slug' => (string) $event->slug,
                    'total_transactions' => (int) ($totalsByEventId[$event->id] ?? 0),
                ];
            })->all();
        });

        $labels = collect($rows)
            ->map(function (array $row): string {
                $year = (string) ($row['year'] ?? '');
                $season = trim((string) ($row['season'] ?? ''));

                if ($season === '') {
                    return $year;
                }

                return sprintf('%s %s', $year, ucfirst($season));
            })
            ->all();

        $data = collect($rows)->pluck('total_transactions')->map(fn ($value) => (int) $value)->all();
        $isLine = $this->filter === 'line';

        return [
            'datasets' => [
                [
                    'label' => 'Transactions',
                    'type' => $isLine ? 'line' : 'bar',
                    'data' => $data,
                    ...($isLine ? [
                        'fill' => false,
                        'tension' => 0.25,
                        'pointRadius' => 3,
                        'pointHoverRadius' => 4,
                    ] : [
                        'barPercentage' => 0.55,
                        'categoryPercentage' => 0.7,
                        'maxBarThickness' => 26,
                    ]),
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Configure axis label rotation to keep dense event labels readable.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'ticks' => [
                        'autoSkip' => false,
                        'maxRotation' => 90,
                        'minRotation' => 90,
                    ],
                ],
            ],
        ];
    }
}

