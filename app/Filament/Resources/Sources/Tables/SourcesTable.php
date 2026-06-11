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

namespace App\Filament\Resources\Sources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('adapter_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('base_url')
                    ->searchable()
                    ->limit(45)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('auth_type')
                    ->badge()
                    ->label('Auth')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('supports_weighting')
                    ->boolean()
                    ->label('Weighted'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('events_count')
                    ->counts('events')
                    ->label('Events')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('supports_weighting')->label('Supports weighting'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
