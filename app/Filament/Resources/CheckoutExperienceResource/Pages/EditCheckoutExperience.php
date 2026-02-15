<?php

namespace App\Filament\Resources\CheckoutExperienceResource\Pages;

use App\Filament\Resources\CheckoutExperienceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
            $data = $this->stripAdvancedDataIfColumnsMissing($data);
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

    /**
     * Avoid SQL errors when deployment is missing advanced cart-line migrations.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function stripAdvancedDataIfColumnsMissing(array $data): array
    {
        try {
            if (Schema::hasColumns('checkout_experiences', ['cart_line_popover_width_mode', 'quantity_rule_mode', 'subscription_rule_mode'])) {
                return $data;
            }
        } catch (Throwable) {
            // If schema check fails, be conservative and strip advanced fields.
        }

        $advancedKeys = [
            'cart_line_popover_width_mode',
            'cart_line_popover_width_preset',
            'cart_line_popover_width_px',
            'cart_line_popover_padding_x',
            'cart_line_quantity_label_text',
            'cart_line_quantity_label_size',
            'cart_line_quantity_label_alignment',
            'cart_line_plus_minus_kind',
            'cart_line_plus_minus_appearance',
            'cart_line_plus_minus_size',
            'cart_line_plus_minus_corner_radius',
            'quantity_rule_mode',
            'quantity_include_product_ids',
            'quantity_exclude_product_ids',
            'quantity_include_collection_ids',
            'quantity_exclude_collection_ids',
            'quantity_include_tags',
            'quantity_exclude_tags',
            'quantity_include_vendors',
            'quantity_exclude_vendors',
            'quantity_include_product_types',
            'quantity_exclude_product_types',
            'quantity_require_subscription_state',
            'quantity_min_subtotal',
            'quantity_max_subtotal',
            'quantity_min_cart_items',
            'quantity_max_cart_items',
            'subscription_rule_mode',
            'subscription_include_product_ids',
            'subscription_exclude_product_ids',
            'subscription_include_collection_ids',
            'subscription_exclude_collection_ids',
            'subscription_include_tags',
            'subscription_exclude_tags',
            'subscription_include_vendors',
            'subscription_exclude_vendors',
            'subscription_include_product_types',
            'subscription_exclude_product_types',
            'subscription_require_subscription_state',
            'subscription_min_subtotal',
            'subscription_max_subtotal',
            'subscription_min_cart_items',
            'subscription_max_cart_items',
        ];
        foreach ($advancedKeys as $key) {
            unset($data[$key]);
        }

        return $data;
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
