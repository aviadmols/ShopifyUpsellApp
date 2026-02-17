<?php

namespace App\Filament\Resources\WidgetSessionEventResource\Pages;

use App\Filament\Resources\WidgetSessionEventResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWidgetSessionEvents extends ListRecords
{
    protected static string $resource = WidgetSessionEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()?->with('shop');
    }
}
