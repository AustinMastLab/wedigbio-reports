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

namespace App\Filament\Resources\Sources\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Source Details')
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
                        Select::make('adapter_type')
                            ->required()
                            ->options([
                                'biospex_json' => 'Biospex JSON (Notes From Nature)',
                                'digivol_json' => 'DigiVol JSON',
                                'api_json' => 'Generic JSON API',
                            ])
                            ->helperText('How data is ingested from this source'),
                        TextInput::make('base_url')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://api.example.org/contributions')
                            ->columnSpanFull(),
                        Select::make('auth_type')
                            ->options([
                                'bearer_token' => 'Bearer token',
                                'basic_auth' => 'Basic auth (username/password)',
                                'query_secret' => 'Query param secret key',
                                'header_key' => 'Custom header key/value',
                            ])
                            ->helperText('Optional auth strategy for this source endpoint')
                            ->native(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('auth_config.token')
                            ->label('Bearer token')
                            ->password()
                            ->revealable()
                            ->visible(fn ($get) => $get('auth_type') === 'bearer_token')
                            ->columnSpanFull(),
                        TextInput::make('auth_config.username')
                            ->label('Username')
                            ->visible(fn ($get) => $get('auth_type') === 'basic_auth'),
                        TextInput::make('auth_config.password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->visible(fn ($get) => $get('auth_type') === 'basic_auth'),
                        TextInput::make('auth_config.param')
                            ->label('Query parameter name')
                            ->placeholder('secret_key')
                            ->visible(fn ($get) => $get('auth_type') === 'query_secret'),
                        TextInput::make('auth_config.value')
                            ->label('Query parameter value')
                            ->password()
                            ->revealable()
                            ->visible(fn ($get) => $get('auth_type') === 'query_secret'),
                        TextInput::make('auth_config.header')
                            ->label('Header name')
                            ->placeholder('X-API-Key')
                            ->visible(fn ($get) => $get('auth_type') === 'header_key'),
                        TextInput::make('auth_config.header_value')
                            ->label('Header value')
                            ->password()
                            ->revealable()
                            ->visible(fn ($get) => $get('auth_type') === 'header_key'),
                    ]),

                Section::make('Configuration')
                    ->columns(2)
                    ->schema([
                        Toggle::make('supports_weighting')
                            ->helperText('Source sends fractional work_unit values (e.g. Les herbonautes)'),
                        Toggle::make('is_active')
                            ->helperText('Controls live ingestion polling for this API; historical data remains even when disabled'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
