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

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('year')
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                $set('slug', Event::buildCanonicalSlug($state, $get('season')));
                            }),
                        Select::make('season')
                            ->options([
                                Event::SEASON_SPRING => 'Spring',
                                Event::SEASON_FALL => 'Fall',
                            ])
                            ->required()
                            ->placeholder('Select a season')
                            ->native(false)
                            ->live()
                            ->rules(fn (Get $get, ?Model $record): array => [
                                function ($attribute, $value, Closure $fail) use ($get, $record) {
                                    $year = $get('year');
                                    if ($year === null || $year === '' || $value === null || $value === '') {
                                        return;
                                    }

                                    $existsQuery = Event::query()
                                        ->where('year', (int) $year)
                                        ->where('season', (string) $value);

                                    if ($record !== null) {
                                        $existsQuery->whereKeyNot($record->getKey());
                                    }

                                    if ($existsQuery->exists()) {
                                        $fail('An event already exists for this year and season.');
                                    }
                                },
                            ])
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                $set('slug', Event::buildCanonicalSlug($get('year'), $state));
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->readOnly()
                            ->helperText('Automatically generated from WeDigBio + Year + Season when the event is saved.')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('display_alias')
                            ->maxLength(255)
                            ->helperText('Optional label override shown in lists and views'),
                    ]),

                Section::make('Event Window')
                    ->description('Convert Tonga Start/End Date Time to UTC before saving event windows.')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('tonga_starts_at')
                            ->label('Tonga Start (helper input)')
                            ->helperText('Enter Tonga local time (Pacific/Tongatapu). This auto-fills UTC Start Date Time.')
                            ->timezone('Pacific/Tongatapu')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('Y-m-d H:i')
                            ->format('Y-m-d H:i:s')
                            ->dehydrated(false)
                            ->live(onBlur: true)
                            ->default(fn (?Event $record) => $record?->starts_at?->clone()->timezone('Pacific/Tongatapu'))
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $set('starts_at', self::parseTongaToUtc($state));
                            }),
                        DateTimePicker::make('tonga_ends_at')
                            ->label('Tonga End (helper input)')
                            ->helperText('Enter Tonga local time (Pacific/Tongatapu). This auto-fills UTC End Date Time.')
                            ->timezone('Pacific/Tongatapu')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('Y-m-d H:i')
                            ->format('Y-m-d H:i:s')
                            ->dehydrated(false)
                            ->live(onBlur: true)
                            ->default(fn (?Event $record) => $record?->ends_at?->clone()->timezone('Pacific/Tongatapu'))
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $set('ends_at', self::parseTongaToUtc($state));
                            }),
                        DateTimePicker::make('starts_at')
                            ->label('Start Date Time (UTC)')
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('Y-m-d H:i')
                            ->format('Y-m-d H:i:s')
                            ->timezone('UTC')
                            ->rules(fn (Get $get): array => [
                                function ($attribute, $value, Closure $fail) use ($get) {
                                    if ($value === null || $value === '') {
                                        return;
                                    }
                                    $year = $get('year');
                                    if ($year === null || $year === '') {
                                        return;
                                    }
                                    try {
                                        $startYear = CarbonImmutable::parse($value, 'UTC')->year;
                                    } catch (\Throwable) {
                                        return;
                                    }
                                    if ($startYear !== (int) $year) {
                                        $fail("Start Date year ({$startYear}) must match the Event Year ({$year}).");
                                    }
                                },
                            ]),
                        DateTimePicker::make('ends_at')
                            ->label('End Date Time (UTC)')
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('Y-m-d H:i')
                            ->format('Y-m-d H:i:s')
                            ->timezone('UTC')
                            ->after('starts_at')
                            ->rules(fn (Get $get): array => [
                                function ($attribute, $value, Closure $fail) use ($get) {
                                    if ($value === null || $value === '') {
                                        return;
                                    }
                                    $year     = $get('year');
                                    $startsAt = $get('starts_at');
                                    // Must match the Year field
                                    if ($year !== null && $year !== '') {
                                        try {
                                            $endYear = CarbonImmutable::parse($value, 'UTC')->year;
                                        } catch (\Throwable) {
                                            return;
                                        }
                                        if ($endYear !== (int) $year) {
                                            $fail("End Date year ({$endYear}) must match the Event Year ({$year}).");
                                            return;
                                        }
                                    }
                                    // Must be within the same year as Start Date
                                    if ($startsAt !== null && $startsAt !== '') {
                                        try {
                                            $startYear = CarbonImmutable::parse($startsAt, 'UTC')->year;
                                            $endYear   = CarbonImmutable::parse($value, 'UTC')->year;
                                        } catch (\Throwable) {
                                            return;
                                        }
                                        if ($endYear !== $startYear) {
                                            $fail("End Date ({$endYear}) must be in the same year as Start Date ({$startYear}).");
                                        }
                                    }
                                },
                            ]),
                    ]),

                Section::make('Visibility')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_public')
                            ->helperText('Show on public event list'),
                        Toggle::make('is_live')
                            ->helperText('Enable live chart reload for this event')
                            ->rules(fn (Get $get, ?Model $record): array => [
                                function ($attribute, $value, Closure $fail) use ($record) {
                                    if ($value === true) {
                                        $liveExistsQuery = Event::where('is_live', true);

                                        if ($record !== null) {
                                            $liveExistsQuery->whereKeyNot($record->getKey());
                                        }

                                        if ($liveExistsQuery->exists()) {
                                            $fail('Only one event can be marked as live at a time. Please disable the existing live event first.');
                                        }
                                    }
                                },
                            ]),
                        Toggle::make('is_archived')
                            ->helperText('Mark as archived (static data only)'),
                    ]),

                Section::make('Participating Sources')
                    ->schema([
                        Select::make('sources')
                            ->relationship('sources', 'name', fn ($query) => $query->where('is_active', true))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Select which data sources participate in this event'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }


    private static function buildSlug(mixed $year, mixed $season): string
    {
        return Event::buildCanonicalSlug($year, $season);
    }

    private static function parseTongaToUtc(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone('Pacific/Tongatapu')->utc();
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'Pacific/Tongatapu')->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
