<?php

namespace Tests\Unit;

use App\Filament\Widgets\WidgetRegistry;
use App\Models\Block;
use App\Models\BlockOffer;
use App\Models\Offer;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_registry_type_options_for_surface(): void
    {
        $checkout = WidgetRegistry::typeOptionsForSurface('checkout');
        $this->assertArrayHasKey('upsell', $checkout);
        $this->assertArrayHasKey('progress_bar', $checkout);
        $this->assertArrayHasKey('content_icon_features', $checkout);
        $this->assertArrayNotHasKey('post_purchase_funnel', $checkout);

        $postPurchase = WidgetRegistry::typeOptionsForSurface('post_purchase');
        $this->assertArrayHasKey('post_purchase_funnel', $postPurchase);
        $this->assertCount(1, $postPurchase);
    }

    public function test_widget_registry_singleton_types(): void
    {
        $singletons = WidgetRegistry::singletonTypes();
        $this->assertContains('post_purchase_funnel', $singletons);
        $this->assertTrue(WidgetRegistry::isSingleton('post_purchase_funnel'));
        $this->assertFalse(WidgetRegistry::isSingleton('upsell'));
    }

    public function test_widget_registry_has_offers(): void
    {
        $this->assertTrue(WidgetRegistry::hasOffers('upsell'));
        $this->assertTrue(WidgetRegistry::hasOffers('post_purchase_funnel'));
        $this->assertFalse(WidgetRegistry::hasOffers('progress_bar'));
    }

    public function test_block_get_offer_ids_uses_pivot_first(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'pivot-test.myshopify.com',
            'access_token' => 'token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        $offer1 = Offer::create([
            'shop_id' => $shop->id,
            'title' => 'Offer 1',
            'product_variant_id' => 'gid1',
            'discount_type' => 'none',
        ]);
        $offer2 = Offer::create([
            'shop_id' => $shop->id,
            'title' => 'Offer 2',
            'product_variant_id' => 'gid2',
            'discount_type' => 'none',
        ]);
        $block = Block::create([
            'shop_id' => $shop->id,
            'surface' => 'checkout',
            'type' => 'upsell',
            'name' => 'Test',
            'config' => ['offer_ids' => [999]], // legacy config would return [999]
            'sort_order' => 0,
        ]);
        BlockOffer::create(['block_id' => $block->id, 'offer_id' => $offer2->id, 'sort_order' => 1]);
        BlockOffer::create(['block_id' => $block->id, 'offer_id' => $offer1->id, 'sort_order' => 0]);

        $ids = $block->getOfferIds();
        $this->assertSame([$offer1->id, $offer2->id], $ids);
    }

    public function test_block_get_offer_ids_fallback_to_config(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'fallback.myshopify.com',
            'access_token' => 'token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        $block = Block::create([
            'shop_id' => $shop->id,
            'surface' => 'checkout',
            'type' => 'upsell',
            'name' => 'Test',
            'config' => ['offer_ids' => [10, 20]],
            'sort_order' => 0,
        ]);

        $ids = $block->getOfferIds();
        $this->assertSame([10, 20], $ids);
    }
}
