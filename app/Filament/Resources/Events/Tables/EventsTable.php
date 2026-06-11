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

namespace App\Filament\Resources\Events\Tables;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->recordUrl(fn (Event $record): string => EventResource::getUrl('view', ['record' => $record]))
            ->columns([
                // ...existing columns...
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('season')
                    ->placeholder('—'),
                TextColumn::make('starts_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
                IconColumn::make('is_public')
                    ->boolean()
                    ->label('Public'),
                IconColumn::make('is_live')
                    ->boolean()
                    ->label('Live'),
                IconColumn::make('is_archived')
                    ->boolean()
                    ->label('Archived'),
                TextColumn::make('sources_count')
                    ->counts('sources')
                    ->label('Sources')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_public')->label('Public'),
                TernaryFilter::make('is_live')->label('Live'),
                TernaryFilter::make('is_archived')->label('Archived'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Events — Permanent Action')
                        ->modalDescription(new HtmlString(
                            '⚠️ **This action is PERMANENT and cannot be undone.**<br><br>' .
                            'All associated records will be permanently deleted:<br>' .
                            '• Transcription records<br>' .
                            '• Chart aggregates<br>' .
                            '• Ingestion checkpoints<br><br>' .
                            'Are you absolutely certain?'
                        ))
                        ->modalSubmitActionLabel('DELETE')
                        ->color('danger')
                        ->before(function ($records) {
                            // Collect statistics for all selected records
                            $nonArchivedCount = 0;
                            $withActiveJobsCount = 0;

                            foreach ($records as $record) {
                                if (!$record->is_archived) {
                                    $nonArchivedCount++;
                                }
                                if (self::hasActiveIngestionJobs($record)) {
                                    $withActiveJobsCount++;
                                }
                            }

                            // Warn if deleting non-archived events
                            if ($nonArchivedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Deleting Non-Archived Events')
                                    ->body("$nonArchivedCount of the selected events are not archived. They may contain historical value. Ensure you have a recent database backup.")
                                    ->send();
                            }

                            // Warn if deleting events with active jobs
                            if ($withActiveJobsCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Active Ingestion Detected')
                                    ->body("$withActiveJobsCount of the selected events have recent ingestion activity. Consider running php artisan queue:restart before deletion.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Check if there are active ingestion jobs for an event.
     * Consider a checkpoint "active" if it was updated within the last 5 minutes.
     */
    private static function hasActiveIngestionJobs(Event $event): bool
    {
        return $event->checkpoints()
            ->where('last_run_at', '>=', now()->subMinutes(5))
            ->exists();
    }
}
