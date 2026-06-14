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
use Illuminate\Support\Str;

class Event extends Model
{
    public const SEASON_SPRING = 'spring';

    public const SEASON_FALL = 'fall';

    public const ALLOWED_SEASONS = [
        self::SEASON_SPRING,
        self::SEASON_FALL,
    ];

    protected $fillable = [
        'slug', 'year', 'season',
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

    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            $event->slug = $event->generateCanonicalSlug();
        });
    }

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

    public function getDisplayNameAttribute(): string
    {
        $alias = trim((string) ($this->display_alias ?? ''));
        if ($alias !== '') {
            return $alias;
        }

        $slugLabel = trim((string) Str::of((string) $this->slug)->replace('-', ' ')->squish());

        if ($slugLabel === '') {
            $slugLabel = trim(sprintf('%s %s', (string) ($this->year ?? ''), ucfirst((string) ($this->season ?? ''))));
        }

        if (! str_starts_with(Str::lower($slugLabel), 'wedigbio')) {
            $slugLabel = 'WeDigBio ' . $slugLabel;
        }

        $headline = (string) Str::of($slugLabel)->headline();

        return preg_replace('/^Wedigbio\b/', 'WeDigBio', $headline) ?? $headline;
    }

    public static function buildCanonicalSlug(mixed $year, mixed $season): string
    {
        $parts = ['WeDigBio'];

        if ($year !== null && $year !== '') {
            $parts[] = (string) $year;
        }

        if ($season !== null && $season !== '') {
            $parts[] = (string) $season;
        }

        return Str::slug(implode(' ', $parts));
    }

    public function generateCanonicalSlug(): string
    {
        $baseSlug = self::buildCanonicalSlug($this->year, $this->season);

        if ($baseSlug === '') {
            return (string) ($this->slug ?? '');
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (self::query()->where('slug', $slug)->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
