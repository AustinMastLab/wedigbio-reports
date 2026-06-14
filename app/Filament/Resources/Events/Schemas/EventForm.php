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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('display_alias')
                            ->maxLength(255)
                            ->helperText('Optional public display alias (e.g. "2020" for "2020-lite")'),
                        TextInput::make('year')
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100),
                        Select::make('season')
                            ->options([
                                Event::SEASON_SPRING => 'Spring',
                                Event::SEASON_FALL => 'Fall',
                            ])
                            ->placeholder('Select a season')
                            ->native(false),
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
                            ->displayFormat('d.m.Y H:i')
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
                            ->displayFormat('d.m.Y H:i')
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
                            ->displayFormat('d.m.Y H:i')
                            ->format('Y-m-d H:i:s')
                            ->timezone('UTC'),
                        DateTimePicker::make('ends_at')
                            ->label('End Date Time (UTC)')
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d.m.Y H:i')
                            ->format('Y-m-d H:i:s')
                            ->timezone('UTC')
                            ->after('starts_at'),
                    ]),

                Section::make('Visibility')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_public')
                            ->helperText('Show on public event list'),
                        Toggle::make('is_live')
                            ->helperText('Enable live chart reload for this event')
                            ->rules([
                                function ($attribute, $value, $fail) {
                                    // If trying to set is_live=true, check if another live event exists
                                    if ($value === true) {
                                        // Get the current record ID if editing (to exclude from check)
                                        $recordId = optional($this->livewire?->data['record'] ?? null)?->id;

                                        $liveExists = Event::where('is_live', true)
                                            ->when($recordId, fn ($q) => $q->whereNot('id', $recordId))
                                            ->exists();

                                        if ($liveExists) {
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
