<?php

/*
 * Copyright (C) 2026, WeDigBio
 * wedigbio@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Source;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard stats widget for top-level administrative metrics.
 *
 * Exposes a compact set of values that are stable and inexpensive to compute
 * on every dashboard refresh:
 * - total API sources with active/inactive breakdown
 * - total events
 */
class AdminOverviewStats extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalSources = Source::query()->count();
        $activeSources = Source::query()->where('is_active', true)->count();
        $inactiveSources = max(0, $totalSources - $activeSources);
        $totalEvents = Event::query()->count();

        return [
            Stat::make('API Sources', number_format($totalSources))
                ->description(sprintf('%d active / %d inactive', $activeSources, $inactiveSources)),
            Stat::make('Total Events', number_format($totalEvents)),
        ];
    }
}

