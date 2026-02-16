<?php

namespace Tests\Unit;

use App\Services\RuleEngine;
use PHPUnit\Framework\TestCase;

class RuleEngineTest extends TestCase
{
    protected RuleEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new RuleEngine();
    }

    public function test_utm_param_equals(): void
    {
        $context = ['utms' => ['utm_source' => 'google', 'utm_medium' => 'cpc']];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['utm_param_equals' => 'utm_source,google']]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['utm_param_equals' => 'utm_source,facebook']]],
            $context
        ));
    }

    public function test_utm_param_contains(): void
    {
        $context = ['utms' => ['utm_campaign' => 'summer-sale']];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['utm_param_contains' => 'utm_campaign,summer']]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['utm_param_contains' => 'utm_campaign,winter']]],
            $context
        ));
    }

    public function test_url_param_equals(): void
    {
        $context = ['url_params' => ['ref' => 'affiliate', 'foo' => 'bar']];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['url_param_equals' => 'ref,affiliate']]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['url_param_equals' => 'ref,other']]],
            $context
        ));
    }

    public function test_url_param_contains(): void
    {
        $context = ['url_params' => ['tag' => 'promo-2024']];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['url_param_contains' => 'tag,promo']]],
            $context
        ));
    }

    public function test_line_item_property_equals(): void
    {
        $context = [
            'line_items' => [
                ['product_id' => 1, 'properties' => ['_subscription' => 'true']],
                ['product_id' => 2, 'properties' => ['_key' => 'value']],
            ],
        ];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_item_property_equals' => '_key,value']]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['line_item_property_equals' => '_key,other']]],
            $context
        ));
    }

    public function test_line_item_property_exists(): void
    {
        $context = [
            'line_items' => [
                ['product_id' => 1, 'customAttributes' => ['_gift' => 'yes']],
            ],
        ];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_item_property_exists' => '_gift']]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['line_item_property_exists' => '_missing']]],
            $context
        ));
    }

    public function test_empty_conditions_returns_true(): void
    {
        $this->assertTrue($this->engine->evaluate([], ['subtotal' => 100]));
    }

    /** Checkout extension sends subtotal and line_items; rules should evaluate. */
    public function test_checkout_context_subtotal_and_line_items(): void
    {
        $context = [
            'subtotal' => 85.50,
            'line_items' => [
                ['product_id' => 100, 'variant_id' => 200, 'quantity' => 1],
                ['product_id' => 101, 'variant_id' => 201, 'quantity' => 2],
            ],
        ];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['subtotal_gte' => 50], ['subtotal_lte' => 100]]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['subtotal_gte' => 100]]],
            $context
        ));
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_items_has_product_id' => 101]]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['line_items_has_product_id' => 999]]],
            $context
        ));
    }

    public function test_line_item_sku_matches(): void
    {
        $context = [
            'line_items' => [
                ['product_id' => 1, 'sku' => 'WIDGET-100-BLUE'],
                ['product_id' => 2, 'sku' => 'GADGET-200-RED'],
            ],
        ];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_item_sku_matches' => '/^WIDGET-\d+-/']]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['line_item_sku_matches' => '/^OTHER-/']]],
            $context
        ));
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_item_sku_matches' => 'GADGET-200-RED']]],
            $context
        ));
    }

    public function test_line_item_sku_matches_from_properties(): void
    {
        $context = [
            'line_items' => [
                ['product_id' => 1, 'properties' => ['SKU' => 'SUB-555-X']],
            ],
        ];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_item_sku_matches' => '/^SUB-\d+-/']]],
            $context
        ));
    }

    public function test_line_item_sku_segment_between(): void
    {
        $context = [
            'line_items' => [
                ['product_id' => 1, 'sku' => 'XXX-YYY-250-ZZZ'],
                ['product_id' => 2, 'sku' => 'AAA-BBB-99-CCC'],
            ],
        ];
        // segment index 2 (0-based), min 100, max 300; value "2,100,300"
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_item_sku_segment_between' => '2,100,300']]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['line_item_sku_segment_between' => '2,300,400']]],
            $context
        ));
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_item_sku_segment_between' => '2,50,100']]],
            $context
        ));
    }

    public function test_line_item_sku_segment_between_custom_separator(): void
    {
        $context = [
            'line_items' => [
                ['product_id' => 1, 'sku' => 'A_B_150_C'],
            ],
        ];
        $this->assertTrue($this->engine->evaluate(
            ['and' => [['line_item_sku_segment_between' => '_,2,100,200']]],
            $context
        ));
        $this->assertFalse($this->engine->evaluate(
            ['and' => [['line_item_sku_segment_between' => '_,2,200,300']]],
            $context
        ));
    }
}
