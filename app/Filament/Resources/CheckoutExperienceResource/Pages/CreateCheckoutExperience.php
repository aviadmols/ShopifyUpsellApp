<?php

namespace App\Filament\Resources\CheckoutExperienceResource\Pages;

use App\Filament\Resources\CheckoutExperienceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCheckoutExperience extends CreateRecord
{
    protected static string $resource = CheckoutExperienceResource::class;

    public function mount(): void
    {
        parent::mount();
        $shopId = request()->query('shop_id');
        if ($shopId !== null && $shopId !== '') {
            $this->form->fill(['shop_id' => (int) $shopId]);
        }
    }
}
