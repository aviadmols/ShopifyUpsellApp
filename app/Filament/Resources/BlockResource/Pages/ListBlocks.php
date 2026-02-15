<?php

namespace App\Filament\Resources\BlockResource\Pages;

use App\Filament\Pages\CreateBlockWithAI;
use App\Filament\Resources\BlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlocks extends ListRecords
{
    protected static string $resource = BlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createWithAi')
                ->label('New Widget With AI')
                ->icon('heroicon-o-sparkles')
                ->url(CreateBlockWithAI::getUrl())
                ->color('primary'),
            Actions\CreateAction::make(),
        ];
    }
}
