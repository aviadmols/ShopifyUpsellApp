<?php

namespace Tests\Unit;

use App\Services\CartLineUpgradeMatcher;
use PHPUnit\Framework\TestCase;

class CartLineUpgradeMatcherTest extends TestCase
{
    private CartLineUpgradeMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new CartLineUpgradeMatcher;
    }

    public function test_line_matches_by_variant_id(): void
    {
        $line = ['id' => 'line-1', 'variant_id' => '12345', 'product_id' => '67890', 'quantity' => 1, 'properties' => []];
        $this->assertTrue($this->matcher->lineMatches($line, ['variant_id' => '12345']));
        $this->assertTrue($this->matcher->lineMatches($line, ['variant_id' => 'gid://shopify/ProductVariant/12345']));
        $this->assertFalse($this->matcher->lineMatches($line, ['variant_id' => '99999']));
    }

    public function test_line_matches_by_product_id(): void
    {
        $line = ['id' => 'line-1', 'variant_id' => '12345', 'product_id' => '67890', 'quantity' => 1];
        $this->assertTrue($this->matcher->lineMatches($line, ['product_id' => '67890']));
        $this->assertFalse($this->matcher->lineMatches($line, ['product_id' => '11111']));
    }

    public function test_line_matches_by_line_item_property_exists(): void
    {
        $line = ['id' => 'line-1', 'variant_id' => '1', 'properties' => ['Dog Name' => 'Max']];
        $this->assertTrue($this->matcher->lineMatches($line, ['line_item_property_exists' => 'Dog Name']));
        $this->assertFalse($this->matcher->lineMatches($line, ['line_item_property_exists' => 'Cat Name']));
    }

    public function test_line_matches_by_line_item_property_equals(): void
    {
        $line = ['id' => 'line-1', 'variant_id' => '1', 'properties' => ['Dog Name' => 'Max', 'Size' => 'L']];
        $this->assertTrue($this->matcher->lineMatches($line, ['line_item_property_equals' => ['Dog Name' => 'Max']]));
        $this->assertFalse($this->matcher->lineMatches($line, ['line_item_property_equals' => ['Dog Name' => 'Buddy']]));
    }

    public function test_line_matches_by_sku_segment(): void
    {
        $line = ['id' => 'line-1', 'variant_id' => '1', 'sku' => 'SUB-ONE-MONTH'];
        $this->assertTrue($this->matcher->lineMatches($line, ['sku_segment' => 'SUB']));
        $this->assertFalse($this->matcher->lineMatches($line, ['sku_segment' => 'OTHER']));
    }

    public function test_run_returns_enabled_with_items_and_actions_when_line_matches(): void
    {
        $config = [
            'headline' => 'Upgrade to subscribe',
            'description' => 'Save 15%',
            'cta_label' => 'Upgrade',
            'upgrade_mappings' => [
                [
                    'match' => ['variant_id' => '111'],
                    'action_type' => 'subscription',
                    'target_variant_id' => 'gid://shopify/ProductVariant/222',
                    'quantity' => 1,
                ],
            ],
        ];
        $context = [
            'subtotal' => 50,
            'line_items' => [
                ['id' => 'line-a', 'variant_id' => '111', 'product_id' => '1', 'quantity' => 2, 'properties' => [], 'product_title' => 'Product A', 'variant_title' => 'Default'],
            ],
        ];
        $result = $this->matcher->run($config, $context);
        $this->assertTrue($result['enabled']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('line-a', $result['items'][0]['line_id']);
        $this->assertCount(2, $result['actions']);
        $this->assertSame('removeCartLine', $result['actions'][0]['type']);
        $this->assertSame('addCartLine', $result['actions'][1]['type']);
    }

    public function test_run_returns_disabled_when_cart_subtotal_min_not_met(): void
    {
        $config = [
            'headline' => 'Upgrade',
            'cta_label' => 'Upgrade',
            'cart_subtotal_min' => 100,
            'upgrade_mappings' => [
                ['match' => ['variant_id' => '1'], 'action_type' => 'subscription', 'target_variant_id' => '2', 'quantity' => 1],
            ],
        ];
        $context = ['subtotal' => 50, 'line_items' => [['id' => 'l1', 'variant_id' => '1', 'product_id' => 'p1', 'quantity' => 1, 'properties' => []]]];
        $result = $this->matcher->run($config, $context);
        $this->assertFalse($result['enabled']);
        $this->assertSame([], $result['items']);
    }

    public function test_variant_to_gid(): void
    {
        $this->assertSame('gid://shopify/ProductVariant/123', CartLineUpgradeMatcher::variantToGid('123'));
        $this->assertSame('gid://shopify/ProductVariant/123', CartLineUpgradeMatcher::variantToGid('gid://shopify/ProductVariant/123'));
        $this->assertSame('', CartLineUpgradeMatcher::variantToGid(''));
    }

}
