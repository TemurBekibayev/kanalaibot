<?php

namespace App\Filament\Resources;

use App\Models\Post;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

use BackedEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Postlar';
    protected static ?string $pluralLabel = 'Postlar';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('channel_id')
                    ->relationship('channel', 'title')
                    ->disabled()
                    ->required(),
                Textarea::make('draft_content')
                    ->label('AI tayyorlagan matn (Qoralama)')
                    ->rows(6)
                    ->required(),
                Textarea::make('final_content')
                    ->label('Yakuniy matn')
                    ->rows(6),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft (Qoralama)',
                        'scheduled' => 'Rejalashtirilgan',
                        'posted' => 'Kanalga joylangan',
                        'failed' => 'Xatolik / Rad etilgan',
                    ])
                    ->required(),
                DateTimePicker::make('scheduled_at')
                    ->label('Rejalashtirilgan vaqt'),
                DateTimePicker::make('posted_at')
                    ->label('Joylashtirilgan vaqt')
                    ->disabled(),
                TextInput::make('ai_provider')
                    ->label('AI Provayder')
                    ->disabled(),
                TextInput::make('tokens_used')
                    ->numeric()
                    ->disabled(),
                TextInput::make('cost')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('channel.title')->label('Kanal')->searchable(),
                TextColumn::make('draft_content')
                    ->label('Matn')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'scheduled',
                        'success' => 'posted',
                        'danger' => 'failed',
                    ]),
                TextColumn::make('scheduled_at')->label('Rejalashtirildi')->dateTime()->sortable(),
                TextColumn::make('posted_at')->label('Joylandi')->dateTime()->sortable(),
                TextColumn::make('ai_provider')->label('AI')->badge(),
                TextColumn::make('cost')->label('Xarajat ($)')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Rejalashtirilgan',
                        'posted' => 'Kanalga joylangan',
                        'failed' => 'Xato / Bekor qilingan',
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
            'index' => PostResource\Pages\ListPosts::route('/'),
        ];
    }
}
