<?php

namespace App\Filament\Resources\PostPurchaseLogResource\Pages;

use App\Filament\Resources\PostPurchaseLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPostPurchaseLogs extends ListRecords
{
    protected static string $resource = PostPurchaseLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
