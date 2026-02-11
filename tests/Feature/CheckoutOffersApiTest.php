<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Placement;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOffersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shopify.checkout_extension_secret' => 'test-secret']);
    }

    public function test_checkout_offers_returns_401_without_extension_secret(): void
    {
        $response = $this->getJson('/api/checkout/offers?shop=test.myshopify.com');

        $response->assertStatus(401);
    }

    public function test_checkout_offers_returns_404_when_shop_not_found(): void
    {
        $response = $this->getJson('/api/checkout/offers?shop=unknown.myshopify.com', [
            'X-Extension-Secret' => 'test-secret',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Shop not found']);
    }

    public function test_checkout_offers_returns_offers_and_display_mode_when_placement_exists(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'test-token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        Placement::create([
            'shop_id' => $shop->id,
            'placement_type' => 'checkout',
            'config' => ['offer_ids' => [], 'max_offers' => 3, 'display_mode' => 'single'],
        ]);

        $response = $this->getJson('/api/checkout/offers?shop=test.myshopify.com', [
            'X-Extension-Secret' => 'test-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['offers', 'display_mode']);
        $this->assertSame('single', $response->json('display_mode'));
        $this->assertIsArray($response->json('offers'));
    }

    public function test_checkout_offers_with_block_id_returns_block_config(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'test-token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        $block = Block::create([
            'shop_id' => $shop->id,
            'surface' => 'checkout',
            'type' => 'progress_bar',
            'name' => 'Free shipping bar',
            'config' => [
                'progress_bar_enabled' => true,
                'progress_bar_goal' => 100,
                'progress_bar_message_below' => "You're {amount} away!",
                'progress_bar_message_achieved' => 'Unlocked!',
            ],
            'sort_order' => 0,
        ]);

        $response = $this->postJson('/api/checkout/offers', [
            'shop' => 'test.myshopify.com',
            'block_id' => $block->id,
            'subtotal' => 50,
        ], [
            'X-Extension-Secret' => 'test-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ui.progress_bar.enabled', true);
        $this->assertEquals(100, $response->json('ui.progress_bar.goal'));
        $this->assertSame([], $response->json('offers'));
    }

    public function test_checkout_offers_with_invalid_block_id_falls_back_to_placement(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'test-token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        Placement::create([
            'shop_id' => $shop->id,
            'placement_type' => 'checkout',
            'config' => ['offer_ids' => [], 'max_offers' => 3, 'display_mode' => 'stacked'],
        ]);

        $response = $this->postJson('/api/checkout/offers', [
            'shop' => 'test.myshopify.com',
            'block_id' => 99999,
            'subtotal' => 50,
        ], [
            'X-Extension-Secret' => 'test-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['offers', 'display_mode']);
        $this->assertSame('stacked', $response->json('display_mode'));
    }
}
