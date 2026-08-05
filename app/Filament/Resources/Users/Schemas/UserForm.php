<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telegram_id')
                    ->label('Telegram ID')
                    ->required()
                    ->numeric()
                    ->disabled(fn ($context) => $context === 'edit'),
                TextInput::make('name')
                    ->label('Ism')
                    ->nullable(),
                TextInput::make('username')
                    ->label('Username')
                    ->nullable()
                    ->prefix('@'),
                Select::make('plan')
                    ->label('Tarif Rejasi')
                    ->options([
                        'free' => 'Free (Bepul)',
                        'premium' => 'Premium',
                        'business' => 'Business',
                    ])
                    ->required(),
                TextInput::make('daily_limit')
                    ->label('Kunlik AI Limiti')
                    ->required()
                    ->numeric(),
                TextInput::make('daily_used')
                    ->label('Ishlatilgan AI Postlar')
                    ->required()
                    ->numeric(),
            ]);
    }
}
