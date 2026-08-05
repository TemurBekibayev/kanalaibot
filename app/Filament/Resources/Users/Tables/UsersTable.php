<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Ism')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? "@{$state}" : '-'),
                TextColumn::make('plan')
                    ->label('Tarif')
                    ->badge()
                    ->colors([
                        'gray' => 'free',
                        'success' => 'premium',
                        'warning' => 'business',
                    ]),
                TextColumn::make('daily_limit')->label('Limit'),
                TextColumn::make('daily_used')->label('Ishlatildi'),
                TextColumn::make('created_at')
                    ->label('Ro\'yxatdan o\'tdi')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('plan')
                    ->label('Tariflar')
                    ->options([
                        'free' => 'Free (Bepul)',
                        'premium' => 'Premium',
                        'business' => 'Business',
                    ]),
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
