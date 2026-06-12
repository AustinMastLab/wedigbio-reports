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

    /**
     * Render an embeddable light-theme single-chart page for Drupal blocks.
     * Auto-discovers the current live event. Returns "No Event Found" if none exists.
     * Chart type passed via ?chart=total-activity or ?chart=activity-by-center
     */
    public function embedChart()
    {
        $chartType = request()->query('chart');

        // Validate chart type
        if (!in_array($chartType, ['total-activity', 'activity-by-center'])) {
            return view('events.embed-chart-error', [
                'message' => 'Invalid or missing chart type. Use ?chart=total-activity or ?chart=activity-by-center',
            ]);
        }

        // Find the current live event
        $event = Event::where('is_live', true)
            ->where('is_public', true)
            ->first();

        if (!$event) {
            return view('events.embed-chart-error', [
                'message' => 'No live event found.',
            ]);
        }

        $hasData = $event->transcriptionRecords()->exists();
        $chartTitle = $chartType === 'total-activity' ? 'Total Transcription Activity' : 'Activity by Center';

        return view('events.embed-chart', [
            'event' => $event,
            'hasData' => $hasData,
            'chartType' => $chartType,
            'chartTitle' => $chartTitle,
        ]);
    }
}
