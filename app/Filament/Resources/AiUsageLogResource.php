<?php

namespace App\Filament\Resources;

use App\Models\AiUsageLog;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

use BackedEnum;

class AiUsageLogResource extends Resource
{
    protected static ?string $model = AiUsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'AI Xarajatlari';
    protected static ?string $pluralLabel = 'AI Loglari';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('Foydalanuvchi')->searchable(),
                TextColumn::make('provider')
                    ->label('AI Provayder')
                    ->badge()
                    ->colors([
                        'success' => 'gemini',
                        'warning' => 'groq',
                        'primary' => 'openrouter',
                    ]),
                TextColumn::make('model')->label('Model')->searchable(),
                TextColumn::make('prompt_tokens')->label('Prompt Tokens'),
                TextColumn::make('completion_tokens')->label('Response Tokens'),
                TextColumn::make('cost')
                    ->label('Xarajat ($)')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 6))
                    ->sortable(),
                TextColumn::make('action')->label('Harakat')->sortable(),
                TextColumn::make('created_at')->label('Sana')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->options([
                        'gemini' => 'Gemini',
                        'groq' => 'Groq',
                        'openrouter' => 'OpenRouter',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => AiUsageLogResource\Pages\ListAiUsageLogs::route('/'),
        ];
    }
}
