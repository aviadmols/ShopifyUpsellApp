<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use App\Models\Offer;
use App\Services\OfferBuilderService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOffer extends EditRecord
{
    protected static string $resource = OfferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Offer $offer */
        $offer = $this->record;
        $builderState = app(OfferBuilderService::class)->buildEditState($offer);
        $data = array_merge($data, $builderState);
        if (trim((string) ($offer->product_variant_id ?? '')) !== '') {
            $data['selected_variant_ids'] = [$offer->product_variant_id];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveProductVariantId(array $data, Offer $offer): string
    {
        $variantIds = array_values(array_filter((array) ($data['selected_variant_ids'] ?? [])));

        return $variantIds !== [] ? (string) $variantIds[0] : (string) ($data['product_variant_id'] ?? $offer->product_variant_id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Offer $offer */
        $offer = $record;

        $offerType = (string) ($data['offer_type'] ?? $offer->offer_type ?? 'one_time');
        $offer->update([
            'shop_id' => (int) ($data['shop_id'] ?? $offer->shop_id),
            'title' => (string) ($data['title'] ?? $offer->title),
            'description' => (string) ($data['description'] ?? ''),
            'product_variant_id' => $this->resolveProductVariantId($data, $offer),
            'discount_type' => (string) ($data['discount_type'] ?? $offer->discount_type),
            'discount_value' => in_array(($data['discount_type'] ?? 'none'), ['percentage', 'fixed'], true)
                ? ($data['discount_value'] ?? null)
                : null,
            'image_url' => (string) ($data['image_url'] ?? ''),
            'offer_type' => in_array($offerType, ['one_time', 'subscription', 'both'], true) ? $offerType : 'one_time',
            'selling_plan_id' => trim((string) ($data['selling_plan_id'] ?? $offer->selling_plan_id ?? '')) ?: null,
            'recharge_subscription_variant_id' => trim((string) ($data['recharge_subscription_variant_id'] ?? $offer->recharge_subscription_variant_id ?? '')) ?: null,
            'allow_subscription_in_post_purchase' => (bool) ($data['allow_subscription_in_post_purchase'] ?? $offer->allow_subscription_in_post_purchase ?? false),
        ]);

        $builder = app(OfferBuilderService::class);
        $builder->syncRule($offer, $data);
        $builder->syncPlacements($offer, $data);

        return $offer->fresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
