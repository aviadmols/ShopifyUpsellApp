<?php

namespace App\Filament\Resources\CheckoutExperienceResource\Pages;

use App\Filament\Resources\CheckoutExperienceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;

class EditCheckoutExperience extends EditRecord
{
    protected static string $resource = CheckoutExperienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_in_checkout')
                ->label('Test in Checkout')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->modalHeading('Test in Checkout')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): View => view('filament.components.checkout-experience-test', [
                    'record' => $this->record,
                ])),
            Actions\DeleteAction::make(),
        ];
    }
}
