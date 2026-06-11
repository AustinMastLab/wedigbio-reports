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

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Event — Permanent Action')
                ->modalDescription(function ($record) {
                    $description = '⚠️ **This action is PERMANENT and cannot be undone.**<br><br>';
                    $description .= '**Deleting this event will permanently remove:**<br>';
                    $description .= '• All transcription records (' . $record->transcriptionRecords()->count() . ' records)<br>';
                    $description .= '• All hourly chart aggregates (' . $record->chartAggregatesHourly()->count() . ' aggregates)<br>';
                    $description .= '• All ingestion checkpoints (' . $record->checkpoints()->count() . ' checkpoints)<br>';
                    $description .= '• Event-source linkages (' . $record->sources()->count() . ' sources)';

                    if (!$record->is_archived) {
                        $description .= '<br><br>⚠️ **This event is NOT archived** — it may contain historical value.';
                    }

                    if ($this->hasActiveIngestionJobs($record)) {
                        $description .= '<br><br>⚠️ **Active ingestion detected** — consider running `php artisan queue:restart` first.';
                    }

                    return new HtmlString($description);
                })
                ->modalSubmitActionLabel('DELETE')
                ->color('danger')
                ->before(function ($record) {
                    // Check if not archived — issue warning but allow deletion
                    if (!$record->is_archived) {
                        Notification::make()
                            ->warning()
                            ->title('Event Not Archived')
                            ->body('This event was not marked as archived. Ensure you have a recent database backup.')
                            ->send();
                    }

                    // Check for active ingestion jobs — issue warning but allow deletion
                    if ($this->hasActiveIngestionJobs($record)) {
                        Notification::make()
                            ->warning()
                            ->title('Active Ingestion Detected')
                            ->body('Recent ingestion checkpoints found. Consider running php artisan queue:restart before deletion.')
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Check if there are active ingestion jobs for this event.
     * Consider a checkpoint "active" if it was updated within the last 5 minutes.
     */
    private function hasActiveIngestionJobs($event): bool
    {
        return $event->checkpoints()
            ->where('last_run_at', '>=', now()->subMinutes(5))
            ->exists();
    }
}
