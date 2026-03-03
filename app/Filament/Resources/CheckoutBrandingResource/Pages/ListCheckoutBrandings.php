<?php

namespace App\Filament\Resources\CheckoutBrandingResource\Pages;

use App\Filament\Resources\CheckoutBrandingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCheckoutBrandings extends ListRecords
{
    protected static string $resource = CheckoutBrandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
