<?php

namespace App\Filament\Resources\CheckoutExperienceResource\Pages;

use App\Filament\Resources\CheckoutExperienceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\View\View;
use Throwable;

class EditCheckoutExperience extends EditRecord
{
    protected static string $resource = CheckoutExperienceResource::class;

    /**
     * Show save errors as notification instead of a blank screen.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (Throwable $e) {
            Log::error('checkout_experience_save_failed', [
                'record_id' => $record->getKey(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Save failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();

            return $record;
        }
    }

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
