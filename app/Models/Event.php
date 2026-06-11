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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    public const SEASON_SPRING = 'spring';

    public const SEASON_FALL = 'fall';

    public const ALLOWED_SEASONS = [
        self::SEASON_SPRING,
        self::SEASON_FALL,
    ];

    protected $fillable = [
        'name', 'slug', 'year', 'season',
        'starts_at', 'ends_at',
        'is_public', 'is_live', 'is_archived',
        'display_alias', 'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_public' => 'boolean',
        'is_live' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class)
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(SourceCheckpoint::class);
    }

    public function transcriptionRecords(): HasMany
    {
        return $this->hasMany(TranscriptionRecord::class);
    }

    public function chartAggregatesHourly(): HasMany
    {
        return $this->hasMany(ChartAggregateHourly::class);
    }

    public function chartSnapshots(): HasMany
    {
        return $this->hasMany(ChartSnapshot::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
