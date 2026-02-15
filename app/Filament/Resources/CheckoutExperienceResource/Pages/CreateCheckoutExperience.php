<?php

namespace App\Filament\Resources\CheckoutExperienceResource\Pages;

use App\Filament\Resources\CheckoutExperienceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Show create errors as notification instead of a blank screen.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (Throwable $e) {
            Log::error('checkout_experience_create_failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Create failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();

            $modelClass = static::getModel();

            return new $modelClass;
        }
    }
}
