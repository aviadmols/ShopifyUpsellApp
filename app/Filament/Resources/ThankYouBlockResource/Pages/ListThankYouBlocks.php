<?php

namespace App\Filament\Resources\ThankYouBlockResource\Pages;

use App\Filament\Resources\ThankYouBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListThankYouBlocks extends ListRecords
{
    protected static string $resource = ThankYouBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
