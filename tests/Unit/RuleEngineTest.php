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
}
