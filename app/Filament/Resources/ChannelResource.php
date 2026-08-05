<?php

namespace App\Filament\Resources;

use App\Models\Channel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

use BackedEnum;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Kanallar';
    protected static ?string $pluralLabel = 'Kanallar';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telegram_id')
                    ->label('Telegram ID')
                    ->required()
                    ->disabled(),
                TextInput::make('title')
                    ->label('Nomi')
                    ->required(),
                TextInput::make('username')
                    ->label('Username')
                    ->prefix('@')
                    ->nullable(),
                Select::make('owner_id')
                    ->relationship('owner', 'name')
                    ->disabled()
                    ->required(),
                Toggle::make('is_active')
                    ->label('Faol')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('telegram_id')->label('Channel ID')->copyable(),
                TextColumn::make('title')->label('Kanal Nomi')->searchable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? "@{$state}" : '-'),
                TextColumn::make('owner.name')->label('Eganing ismi')->searchable(),
                IconColumn::make('is_active')
                    ->label('Faollik')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')->label('Ulagan sana')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Holat')
                    ->options([
                        '1' => 'Faol',
                        '0' => 'Nofaol',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ChannelResource\Pages\ListChannels::route('/'),
        ];
    }
}
