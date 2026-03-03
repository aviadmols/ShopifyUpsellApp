<?php

namespace App\Filament\Resources\CheckoutBrandingResource\Pages;

use App\Filament\Resources\CheckoutBrandingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCheckoutBranding extends CreateRecord
{
    protected static string $resource = CheckoutBrandingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CheckoutBrandingResource::mergeStructuredBrandingData($data);
    }
}
