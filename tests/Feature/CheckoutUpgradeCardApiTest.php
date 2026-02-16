<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutUpgradeCardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shopify.checkout_extension_secret' => 'test-secret']);
    }

    public function test_upgrade_card_returns_401_without_extension_secret(): void
    {
        $response = $this->postJson('/api/checkout/upgrade-card', [
            'shop' => 'test.myshopify.com',
            'block_id' => 1,
            'subtotal' => 0,
            'line_items' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_upgrade_card_returns_enabled_false_when_block_id_missing(): void
    {
        $response = $this->postJson('/api/checkout/upgrade-card', [
            'shop' => 'test.myshopify.com',
            'subtotal' => 0,
            'line_items' => [],
        ], [
            'X-Extension-Secret' => 'test-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('enabled', false);
        $response->assertJsonStructure(['enabled', 'items', 'plans', 'actions']);
    }

    public function test_upgrade_card_returns_enabled_false_when_block_not_found(): void
    {
        $response = $this->postJson('/api/checkout/upgrade-card', [
            'shop' => 'test.myshopify.com',
            'block_id' => 99999,
            'subtotal' => 0,
            'line_items' => [],
        ], [
            'X-Extension-Secret' => 'test-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('enabled', false);
    }

    public function test_upgrade_card_returns_payload_structure_when_block_exists(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        $block = Block::create([
            'shop_id' => $shop->id,
            'surface' => 'checkout',
            'type' => 'checkout_upgrade_card',
            'name' => 'Upgrade card',
            'config' => [
                'headline' => 'Upgrade to subscribe',
                'description' => 'Save 15%',
                'cta_label' => 'Upgrade',
                'upgrade_mappings' => [
                    [
                        'match' => ['variant_id' => '111'],
                        'action_type' => 'subscription',
                        'target_variant_id' => '222',
                        'quantity' => 1,
                    ],
                ],
            ],
            'sort_order' => 0,
        ]);

        $response = $this->postJson('/api/checkout/upgrade-card', [
            'shop' => 'test.myshopify.com',
            'block_id' => $block->id,
            'subtotal' => 50,
            'line_items' => [
                ['id' => 'line-1', 'variant_id' => '111', 'product_id' => '1', 'quantity' => 1, 'properties' => [], 'product_title' => 'Product One', 'variant_title' => 'Default'],
            ],
        ], [
            'X-Extension-Secret' => 'test-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['enabled', 'items', 'plans', 'actions', 'headline', 'description', 'cta_label']);
        $response->assertJsonPath('enabled', true);
        $response->assertJsonPath('headline', 'Upgrade to subscribe');
        $response->assertJsonPath('cta_label', 'Upgrade');
        $items = $response->json('items');
        $this->assertCount(1, $items);
        $this->assertSame('line-1', $items[0]['line_id']);
        $this->assertSame('Product One', $items[0]['product_title']);
        $actions = $response->json('actions');
        $this->assertGreaterThanOrEqual(2, count($actions));
        $this->assertSame('removeCartLine', $actions[0]['type']);
        $this->assertSame('addCartLine', $actions[1]['type']);
    }

    public function test_upgrade_card_returns_enabled_false_for_wrong_block_type(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'token',
            'scope' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
        $block = Block::create([
            'shop_id' => $shop->id,
            'surface' => 'checkout',
            'type' => 'upsell',
            'name' => 'Upsell',
            'config' => [],
            'sort_order' => 0,
        ]);

        $response = $this->postJson('/api/checkout/upgrade-card', [
            'shop' => 'test.myshopify.com',
            'block_id' => $block->id,
            'subtotal' => 50,
            'line_items' => [],
        ], [
            'X-Extension-Secret' => 'test-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('enabled', false);
    }
}
