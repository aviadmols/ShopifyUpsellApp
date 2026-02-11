<?php

namespace Tests\Unit;

use App\Services\OfferBuilderService;
use PHPUnit\Framework\TestCase;

class OfferBuilderServiceTest extends TestCase
{
    public function test_it_builds_and_conditions_from_rows(): void
    {
        $service = new OfferBuilderService();

        $conditions = $service->buildConditions([
            ['field' => 'subtotal_gte', 'value' => '100'],
            ['field' => 'customer_has_tag', 'value' => 'vip'],
            ['field' => 'shipping_country_in', 'value' => 'US,CA'],
        ], 'and');

        $this->assertArrayHasKey('and', $conditions);
        $this->assertCount(3, $conditions['and']);
        $this->assertSame(['subtotal_gte' => 100.0], $conditions['and'][0]);
        $this->assertSame(['customer_has_tag' => 'vip'], $conditions['and'][1]);
        $this->assertSame(['shipping_country_in' => ['US', 'CA']], $conditions['and'][2]);
    }

    public function test_it_builds_or_conditions_with_list_products(): void
    {
        $service = new OfferBuilderService();

        $conditions = $service->buildConditions([
            ['field' => 'line_items_has_any_product_id', 'value' => '10,11,12'],
        ], 'or');

        $this->assertArrayHasKey('or', $conditions);
        $this->assertSame([['line_items_has_any_product_id' => [10, 11, 12]]], $conditions['or']);
    }

    public function test_it_flattens_conditions_for_rule_form_state(): void
    {
        $service = new OfferBuilderService();

        $state = $service->ruleFormStateFromConditions([
            'or' => [
                ['cart_subtotal_gte' => 50],
                ['shipping_country_in' => ['US', 'GB']],
            ],
        ]);

        $this->assertSame('or', $state['match_type']);
        $this->assertCount(2, $state['rows']);
        $this->assertSame('subtotal_gte', $state['rows'][0]['field']);
        $this->assertSame('50', $state['rows'][0]['value']);
        $this->assertSame('shipping_country_in', $state['rows'][1]['field']);
        $this->assertSame('US,GB', $state['rows'][1]['value']);
    }
}

