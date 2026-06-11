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

use App\Models\TranscriptionRecord;
use Filament\Widgets\ChartWidget;

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
        $rows = TranscriptionRecord::query()
            ->join('events', 'events.id', '=', 'transcription_records.event_id')
            ->selectRaw('events.id, events.year, events.season, events.slug, COUNT(*) as total_transactions')
            ->groupBy('events.id', 'events.year', 'events.season', 'events.slug')
            ->orderBy('events.year')
            ->orderByRaw("CASE WHEN events.season = 'spring' THEN 1 WHEN events.season = 'fall' THEN 2 ELSE 3 END")
            ->orderBy('events.slug')
            ->get();

        $labels = $rows
            ->map(function ($row): string {
                $year = (string) $row->year;
                $season = trim((string) ($row->season ?? ''));

                if ($season === '') {
                    return $year;
                }

                return sprintf('%s %s', $year, ucfirst($season));
            })
            ->all();

        $data = $rows->pluck('total_transactions')->map(fn ($value) => (int) $value)->all();
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

