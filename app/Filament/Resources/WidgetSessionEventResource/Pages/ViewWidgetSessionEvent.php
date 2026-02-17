<?php

namespace App\Filament\Resources\WidgetSessionEventResource\Pages;

use App\Filament\Resources\WidgetSessionEventResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWidgetSessionEvent extends ViewRecord
{
    protected static string $resource = WidgetSessionEventResource::class;

    public function mutateFormDataBeforeFill(array $data): array
    {
        $data['shop_domain'] = $this->record->shop_domain;
        return $data;
    }
}
