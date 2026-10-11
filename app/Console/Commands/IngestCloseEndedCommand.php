<?php

namespace App\Console\Commands;

use App\Jobs\AggregateHourlyJob;
use App\Jobs\IngestPageJob;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestCloseEndedCommand extends Command
{
    /**
     * Minutes after an event ends before it is closed, so records stamped just
     * before the end but published late can still be fetched.
     */
    public const GRACE_MINUTES = 15;

    /**
     * Minutes after an event ends when it is archived even if a source's final
     * catch-up keeps failing, so an outage can't keep it live forever.
     */
    private const FORCE_CLOSE_MINUTES = 60;

    protected $signature = 'ingest:close-ended';

    protected $description = 'Run a final catch-up for live events past their end, then archive them';

    public function handle(): int
    {
        $endedEvents = Event::query()
            ->where('is_live', true)
            ->where('is_archived', false)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->subMinutes(self::GRACE_MINUTES))
            ->with(['sources' => fn ($query) => $query
                ->where('is_active', true)
                ->wherePivot('is_enabled', true)])
            ->get();

        $endedEvents->each(fn (Event $event) => $this->closeEvent($event));

        return self::SUCCESS;
    }

    /**
     * Fetch each source up to the end, rebuild aggregates, then archive.
     *
     * Archived chart responses are cached, so the catch-up and aggregation
     * must finish before the event is archived.
     */
    private function closeEvent(Event $event): void
    {
        $failedSources = $event->sources
            ->reject(fn (Source $source) => $this->catchUp($event, $source))
            ->pluck('slug');

        if ($failedSources->isNotEmpty() && $event->ends_at->gt(now()->subMinutes(self::FORCE_CLOSE_MINUTES))) {
            $this->warn("Keeping {$event->slug} live; final catch-up failed for: {$failedSources->implode(', ')}");

            return;
        }

        AggregateHourlyJob::dispatchSync($event->id);

        $event->update(['is_live' => false, 'is_archived' => true]);

        Log::info('Closed ended event', [
            'event_slug' => $event->slug,
            'failed_sources' => $failedSources->all(),
        ]);
        $this->info("Closed {$event->slug}");
    }

    private function catchUp(Event $event, Source $source): bool
    {
        try {
            IngestPageJob::dispatchSync($event->id, $source->id, catchUp: true);

            return true;
        } catch (Throwable $e) {
            Log::warning('Final catch-up failed', [
                'event_slug' => $event->slug,
                'source_slug' => $source->slug,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
