<?php

namespace Tests\Feature;

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
}
