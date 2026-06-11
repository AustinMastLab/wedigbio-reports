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
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Legacy table-style dashboard widget for yearly transaction totals.
 */
class TransactionsByYearWidget extends Widget
{
    protected string $view = 'filament.widgets.transactions-by-year-widget';

    protected int | string | array $columnSpan = 'full';

    /**
     * Provide rows for the Blade widget view.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'rows' => $this->getTransactionsByYear(),
        ];
    }

    /**
     * Query yearly transcription totals for table display.
     *
     * Uses events.year instead of YEAR(timestamp_utc) for cross-DB compatibility.
     *
     * @return Collection<int, object>
     */
    private function getTransactionsByYear(): Collection
    {
        return TranscriptionRecord::query()
            ->join('events', 'events.id', '=', 'transcription_records.event_id')
            ->selectRaw('events.year as year, COUNT(*) as total_transactions')
            ->groupBy('events.year')
            ->orderBy('events.year')
            ->get();
    }
}
