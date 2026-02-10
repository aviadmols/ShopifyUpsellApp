<?php

namespace Database\Seeders;

use App\Models\Placement;
use App\Models\Shop;
use Illuminate\Database\Seeder;

/**
 * Seeds a demo shop for local development. Replace token after OAuth.
 */
class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::firstOrCreate(
            ['shop_domain' => 'demo-store.myshopify.com'],
            [
                'access_token' => 'placeholder-replace-via-oauth',
                'scope' => config('shopify.scopes'),
                'installed_at' => now(),
            ]
        );

        Placement::firstOrCreate(
            [
                'shop_id' => $shop->id,
                'placement_type' => 'checkout',
            ],
            ['config' => ['offer_ids' => [], 'max_offers' => 3]]
        );
        Placement::firstOrCreate(
            [
                'shop_id' => $shop->id,
                'placement_type' => 'post_purchase',
            ],
            ['config' => ['offer_ids' => [], 'max_offers' => 1, 'cooldown_hours' => 24]]
        );
        Placement::firstOrCreate(
            [
                'shop_id' => $shop->id,
                'placement_type' => 'thank_you',
            ],
            ['config' => ['block_ids' => []]]
        );
    }
}
