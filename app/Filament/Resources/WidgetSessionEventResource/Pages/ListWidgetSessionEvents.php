<?php

namespace App\Filament\Resources\WidgetSessionEventResource\Pages;

use App\Filament\Resources\WidgetSessionEventResource;
use App\Filament\Pages\WidgetSessionAISummary;
use App\Filament\Pages\WidgetSessionByUserId;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWidgetSessionEvents extends ListRecords
{
    protected static string $resource = WidgetSessionEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('byUserId')
                ->label('By User ID')
                ->icon('heroicon-o-user-circle')
                ->url(WidgetSessionByUserId::getUrl()),
            Action::make('aiSessionSummary')
                ->label('AI Session Summary')
                ->icon('heroicon-o-sparkles')
                ->url(WidgetSessionAISummary::getUrl())
                ->openUrlInNewTab(),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()?->with('shop');
    }
}
