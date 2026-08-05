<?php

namespace App\Filament\Resources;

use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

use BackedEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Admin Audit Loglari';
    protected static ?string $pluralLabel = 'Audit Loglari';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('admin.name')->label('Admin Nomi')->searchable(),
                TextColumn::make('action')->label('Harakat')->badge()->searchable(),
                TextColumn::make('details')->label('Tafsilotlar')->limit(80)->searchable(),
                TextColumn::make('created_at')->label('Sana')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ActivityLogResource\Pages\ListActivityLogs::route('/'),
        ];
    }
}
