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

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Filament\Resources\Events\Tables\EventsTable;
use App\Models\Event;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $navigationLabel = 'Events';

    protected static string|UnitEnum|null $navigationGroup = 'WeDigBio';

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event Summary')
                ->columns(4)
                ->schema([
                    TextEntry::make('name')
                        ->label('Event Name')
                        ->columnSpan(2),
                    TextEntry::make('slug')
                        ->columnSpan(2),
                    TextEntry::make('year'),
                    TextEntry::make('season')
                        ->placeholder('—'),
                    TextEntry::make('display_alias')
                        ->placeholder('—')
                        ->columnSpan(2),
                    TextEntry::make('notes')
                        ->label('Notes Preview')
                        ->placeholder('No notes')
                        ->columnSpanFull(),
                ]),

            Section::make('Event Window')
                ->columns(2)
                ->schema([
                    TextEntry::make('starts_at_summary')
                        ->label('Start Date Time')
                        ->state(fn (Event $record): string => sprintf(
                            'UTC: %s<br>EST: %s',
                            $record->starts_at?->format('M j, Y H:i') ?? '—',
                            $record->starts_at?->setTimezone('America/New_York')->format('M j, Y H:i') ?? '—',
                        ))
                        ->html(),
                    TextEntry::make('ends_at_summary')
                        ->label('End Date Time')
                        ->state(fn (Event $record): string => sprintf(
                            'UTC: %s<br>EST: %s',
                            $record->ends_at?->format('M j, Y H:i') ?? '—',
                            $record->ends_at?->setTimezone('America/New_York')->format('M j, Y H:i') ?? '—',
                        ))
                        ->html(),
                    IconEntry::make('is_public')
                        ->boolean()
                        ->label('Public'),
                    IconEntry::make('is_live')
                        ->boolean()
                        ->label('Live'),
                    IconEntry::make('is_archived')
                        ->boolean()
                        ->label('Archived'),
                ]),

            Section::make('Participating Sources')
                ->schema([
                    TextEntry::make('source_names')
                        ->label('Sources')
                        ->state(fn (Event $record): array => $record->sources->pluck('name')->all())
                        ->bulleted()
                        ->listWithLineBreaks()
                        ->placeholder('No sources attached')
                        ->columnSpanFull(),
                ]),

            Section::make('Record Counts')
                ->columns(4)
                ->schema([
                    TextEntry::make('transcription_records_count')
                        ->label('Transcription Records')
                        ->state(fn (Event $record): string => number_format($record->transcriptionRecords()->count())),
                    TextEntry::make('chart_aggregates_hourly_count')
                        ->label('Hourly Aggregates')
                        ->state(fn (Event $record): string => number_format($record->chartAggregatesHourly()->count())),
                    TextEntry::make('checkpoints_count')
                        ->label('Checkpoints')
                        ->state(fn (Event $record): string => number_format($record->checkpoints()->count())),
                    TextEntry::make('sources_count')
                        ->label('Sources Count')
                        ->state(fn (Event $record): string => number_format($record->sources()->count())),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'view' => ViewEvent::route('/{record}'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
