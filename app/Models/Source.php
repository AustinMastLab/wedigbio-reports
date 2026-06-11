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

class Source extends Model
{
    protected $fillable = [
        'name', 'slug', 'base_url', 'adapter_type',
        'auth_type', 'auth_config',
        'supports_weighting', 'is_active', 'notes',
    ];

    protected $casts = [
        'auth_config'        => 'encrypted:array',
        'supports_weighting' => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class)
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function transcriptionRecords(): HasMany
    {
        return $this->hasMany(TranscriptionRecord::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(SourceCheckpoint::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
