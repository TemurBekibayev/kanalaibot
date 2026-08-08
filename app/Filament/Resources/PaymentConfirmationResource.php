<?php

namespace App\Filament\Resources;

use App\Models\PaymentConfirmation;
use App\Services\Payment\ManualPaymentProvider;
use App\Services\Telegram\TelegramBotService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

use BackedEnum;

class PaymentConfirmationResource extends Resource
{
    protected static ?string $model = PaymentConfirmation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'To\'lovlarni Moderatsiya Qilish';
    protected static ?string $pluralLabel = 'To\'lovlar';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled()
                    ->required(),
                TextInput::make('amount')
                    ->label('To\'lov summasi')
                    ->disabled()
                    ->numeric(),
                Select::make('status')
                    ->options([
                        'pending' => 'Kutilmoqda',
                        'approved' => 'Tasdiqlangan',
                        'rejected' => 'Rad etilgan',
                    ])
                    ->required(),
                Textarea::make('rejection_reason')
                    ->label('Rad etish sababi')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('Foydalanuvchi')->searchable(),
                TextColumn::make('user.telegram_id')->label('Telegram ID')->copyable(),
                TextColumn::make('amount')->label('Summa (UZS)')->money('UZS', locale: 'uz-UZ')->sortable(),
                TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                ImageColumn::make('screenshot_path')
                    ->label('Kvitansiya')
                    ->disk('public')
                    ->height(80)
                    ->square(),
                TextColumn::make('created_at')->label('Yuborilgan sana')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Kutilmoqda',
                        'approved' => 'Tasdiqlangan',
                        'rejected' => 'Rad etilgan',
                    ]),
            ])
            ->actions([
                // Inline Approve Action
                Action::make('approve')
                    ->label('Tasdiqlash')
                    ->color('success')
                    ->icon('heroicon-m-check')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Select::make('plan')
                            ->label('Tarif Rejasi')
                            ->options([
                                'premium' => 'Premium (50k UZS)',
                                'business' => 'Business (150k UZS)',
                            ])
                            ->default('premium')
                            ->required(),
                    ])
                    ->action(function (PaymentConfirmation $record, array $data) {
                        $provider = new ManualPaymentProvider();
                        $provider->verifyPayment($record->id, [
                            'status' => 'approved',
                            'plan' => $data['plan'],
                        ]);

                        // Send Telegram Message to User
                        $telegram = app(TelegramBotService::class);
                        $msg = "🎉 **Tabriklaymiz!** Sizning to'lovingiz tasdiqlandi va **" . ucfirst($data['plan']) . "** tarifi 30 kunga faollashtirildi!";
                        $telegram->sendMessage($record->user->telegram_id, $msg);
                    }),

                // Inline Reject Action
                Action::make('reject')
                    ->label('Rad etish')
                    ->color('danger')
                    ->icon('heroicon-m-x-mark')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Rad etish sababi')
                            ->default('To\'lov summasi noto\'g\'ri yoki chek soxta.')
                            ->required(),
                    ])
                    ->action(function (PaymentConfirmation $record, array $data) {
                        $provider = new ManualPaymentProvider();
                        $provider->verifyPayment($record->id, [
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        // Send Telegram Message to User
                        $telegram = app(TelegramBotService::class);
                        $msg = "❌ **Kechirasiz!** Siz yuborgan to'lov cheki rad etildi.\nSabab: " . $data['rejection_reason'];
                        $telegram->sendMessage($record->user->telegram_id, $msg);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PaymentConfirmationResource\Pages\ListPaymentConfirmations::route('/'),
        ];
    }
}
