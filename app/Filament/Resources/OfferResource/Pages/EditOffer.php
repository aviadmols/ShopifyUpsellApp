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

        return array_merge($data, $builderState);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Offer $offer */
        $offer = $record;

        $offer->update([
            'shop_id' => (int) ($data['shop_id'] ?? $offer->shop_id),
            'title' => (string) ($data['title'] ?? $offer->title),
            'description' => (string) ($data['description'] ?? ''),
            'product_variant_id' => (string) ($data['product_variant_id'] ?? $offer->product_variant_id),
            'discount_type' => (string) ($data['discount_type'] ?? $offer->discount_type),
            'discount_value' => in_array(($data['discount_type'] ?? 'none'), ['percentage', 'fixed'], true)
                ? ($data['discount_value'] ?? null)
                : null,
            'image_url' => (string) ($data['image_url'] ?? ''),
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
