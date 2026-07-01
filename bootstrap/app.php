<?php

use App\Models\Event;
use App\Jobs\PollSourcesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withSchedule(function (Schedule $schedule): void {
        // Poll every minute (jobs fan out per event/source pair)
        $schedule->job(new PollSourcesJob)
            ->everyMinute()
            ->withoutOverlapping()
            ->when(static fn (): bool => Event::query()
                ->where('is_archived', false)
                ->where('is_live', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->exists());

        // Hourly safety net — re-aggregate all active events even if ingestion had no new pages
        $schedule->command('ingest:aggregate')->hourly()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
