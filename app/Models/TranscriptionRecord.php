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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptionRecord extends Model
{
    protected $fillable = [
        'event_id', 'source_id', 'source_guid', 'dedupe_key',
        'center', 'project', 'description',
        'timestamp_utc', 'work_unit', 'raw_count', 'payload_json',
    ];

    protected $casts = [
        'timestamp_utc' => 'datetime',
        'work_unit'     => 'decimal:4',
        'payload_json'  => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
