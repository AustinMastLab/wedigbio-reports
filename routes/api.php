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

use App\Http\Controllers\Api\ChartController;
use Illuminate\Support\Facades\Route;
use Spatie\ResponseCache\Middlewares\CacheResponse;

Route::prefix('events/{event:slug}')->middleware(CacheResponse::class)->group(function () {
    Route::get('charts/total-activity',   [ChartController::class, 'totalActivity'])->name('api.charts.total-activity');
    Route::get('charts/hourly-activity',  [ChartController::class, 'hourlyActivity'])->name('api.charts.hourly-activity');
    Route::get('charts/activity-by-center', [ChartController::class, 'activityByCenter'])->name('api.charts.activity-by-center');
    Route::get('summary',                 [ChartController::class, 'summary'])->name('api.charts.summary');
});

