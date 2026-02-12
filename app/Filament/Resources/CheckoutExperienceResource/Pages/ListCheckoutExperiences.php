<?php

namespace App\Filament\Resources\CheckoutExperienceResource\Pages;

use App\Filament\Resources\CheckoutExperienceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCheckoutExperiences extends ListRecords
{
    protected static string $resource = CheckoutExperienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
