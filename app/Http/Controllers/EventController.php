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

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EventController extends Controller
{
    /**
     * Render the public event index page.
     *
     * Events are limited to public entries and preloaded with
     * transcription record counts for badge/status display.
     */
    public function index()
    {
        /** @var Collection<int, Event> $events */
        $events = Schema::hasTable('events')
            ? Event::where('is_public', true)
                ->withCount('transcriptionRecords')
                ->orderByDesc('starts_at')
                ->get()
            : collect();

        return view('events.index', compact('events'));
    }

    /**
     * Render the public stats page for a single event.
     */
    public function show(Event $event)
    {
        abort_unless($event->is_public, 404);

        $hasData = $event->transcriptionRecords()->exists();

        return view('events.show', compact('event', 'hasData'));
    }
}
