<?php

namespace App\Filament\Resources\PaymentConfirmationResource\Pages;

use App\Filament\Resources\PaymentConfirmationResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentConfirmations extends ListRecords
{
    protected static string $resource = PaymentConfirmationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
