<?php

namespace App\Support\ResponseCache;

use App\Models\Event;
use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

class ArchivedEventChartCacheProfile extends CacheAllSuccessfulGetRequests
{
    public function shouldCacheRequest(Request $request): bool
    {
        if (! parent::shouldCacheRequest($request)) {
            return false;
        }

        if (! $request->routeIs('api.charts.*')) {
            return false;
        }

        $event = $request->route('event');

        if ($event instanceof Event) {
            return $event->is_archived && ! $event->is_live;
        }

        if (! is_string($event) || $event === '') {
            return false;
        }

        $resolvedEvent = Event::query()
            ->select(['is_archived', 'is_live'])
            ->where('slug', $event)
            ->first();

        return (bool) $resolvedEvent?->is_archived && ! (bool) $resolvedEvent?->is_live;
    }

    public function cacheLifetimeInSeconds(Request $request): int
    {
        return (int) env('RESPONSE_CACHE_LIFETIME_ARCHIVED', 900);
    }
}

