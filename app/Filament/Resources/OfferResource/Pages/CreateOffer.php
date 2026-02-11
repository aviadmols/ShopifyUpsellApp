<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use App\Models\Offer;
use App\Models\Shop;
use App\Services\OfferBuilderService;
use App\Services\ShopifyGraphQLService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $builder = app(OfferBuilderService::class);
        $variants = array_values(array_filter((array) ($data['selected_variant_ids'] ?? [])));

        if (empty($variants) && ! empty($data['product_variant_id'])) {
            $variants = [(string) $data['product_variant_id']];
        }

        if (empty($variants)) {
            throw new \RuntimeException('Please choose at least one variant.');
        }

        return DB::transaction(function () use ($data, $variants, $builder) {
            /** @var Offer|null $first */
            $first = null;

            foreach ($variants as $variantId) {
                $payload = $this->basePayload($data, (string) $variantId);
                $payload = $this->applyVariantDefaults($payload, $data['shop_id'] ?? null, (string) $variantId);

                /** @var Offer $offer */
                $offer = Offer::create($payload);
                $builder->syncRule($offer, $data);
                $builder->syncPlacements($offer, $data);

                if (! $first) {
                    $first = $offer;
                }
            }

            return $first ?? Offer::latest('id')->firstOrFail();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function basePayload(array $data, string $variantId): array
    {
        return [
            'shop_id' => (int) $data['shop_id'],
            'title' => (string) ($data['title'] ?? 'Upsell offer'),
            'description' => (string) ($data['description'] ?? ''),
            'product_variant_id' => $variantId,
            'discount_type' => (string) ($data['discount_type'] ?? 'none'),
            'discount_value' => in_array(($data['discount_type'] ?? 'none'), ['percentage', 'fixed'], true)
                ? ($data['discount_value'] ?? null)
                : null,
            'image_url' => (string) ($data['image_url'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  int|string|null  $shopId
     * @return array<string, mixed>
     */
    protected function applyVariantDefaults(array $payload, int|string|null $shopId, string $variantId): array
    {
        if (! $shopId) {
            return $payload;
        }

        $shop = Shop::whereNull('uninstalled_at')->find((int) $shopId);
        if (! $shop) {
            return $payload;
        }

        try {
            $variant = app(ShopifyGraphQLService::class)->getProductVariant($shop, $variantId);
        } catch (\Throwable) {
            return $payload;
        }

        if (! $variant) {
            return $payload;
        }

        $productTitle = (string) ($variant['product']['title'] ?? 'Upsell offer');
        $variantTitle = (string) ($variant['title'] ?? '');
        $autoTitle = $productTitle;
        if ($variantTitle !== '' && strtolower($variantTitle) !== 'default title') {
            $autoTitle .= " - {$variantTitle}";
        }

        if (($payload['title'] ?? '') === 'Upsell offer' || trim((string) ($payload['title'] ?? '')) === '') {
            $payload['title'] = $autoTitle;
        }

        if (empty($payload['image_url'])) {
            $payload['image_url'] = (string) ($variant['product']['featuredImage']['url'] ?? '');
        }

        return $payload;
    }
}
