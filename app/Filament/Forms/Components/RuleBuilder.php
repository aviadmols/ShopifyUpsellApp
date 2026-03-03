<?php

namespace App\Filament\Forms\Components;

use App\Services\OfferBuilderService;
use Filament\Forms;
use Filament\Forms\Form;

/**
 * Reusable form schema for building a rule (conditions + match type).
 * Use for block-level rule or per-offer rule. State keys: rule_match_type, rule_conditions.
 * Build RuleEngine-compatible conditions via buildConditionsFromState().
 */
final class RuleBuilder
{
    public static function schema(string $prefix = ''): array
    {
        $matchKey = $prefix ? "{$prefix}.rule_match_type" : 'rule_match_type';
        $conditionsKey = $prefix ? "{$prefix}.rule_conditions" : 'rule_conditions';

        return [
            Forms\Components\Select::make($matchKey)
                ->label('Match type')
                ->options([
                    'and' => 'All conditions (AND)',
                    'or' => 'Any condition (OR)',
                ])
                ->default('and')
                ->required(),
            Forms\Components\Repeater::make($conditionsKey)
                ->label('Conditions')
                ->defaultItems(0)
                ->schema([
                    Forms\Components\Select::make('field')
                        ->options([
                            'subtotal_gte' => 'Subtotal >=',
                            'subtotal_lte' => 'Subtotal <=',
                            'line_items_has_product_id' => 'Cart has product ID',
                            'line_items_has_any_product_id' => 'Cart has any product IDs (comma separated)',
                            'line_items_has_variant_id' => 'Cart has variant ID',
                            'line_items_has_any_variant_id' => 'Cart has any variant IDs (comma separated)',
                            'line_item_product_title_contains' => 'Cart has product name containing',
                            'line_item_variant_title_contains' => 'Cart has variant name containing',
                            'line_item_product_title_equals' => 'Cart has product name exactly',
                            'line_item_variant_title_equals' => 'Cart has variant name exactly',
                            'line_item_sku_matches' => 'Cart has line with SKU matching (regex or exact)',
                            'line_item_sku_segment_between' => 'Cart has SKU segment between (segment_index,min,max)',
                            'customer_has_tag' => 'Customer has tag',
                            'shipping_country_in' => 'Shipping country in (comma separated ISO codes)',
                            'utm_param_equals' => 'UTM param equals (param_name,value)',
                            'utm_param_contains' => 'UTM param contains (param_name,substring)',
                            'url_param_equals' => 'URL param equals (param_name,value)',
                            'url_param_contains' => 'URL param contains (param_name,substring)',
                            'checkout_attribute_equals' => 'Checkout attribute equals (key,value)',
                            'checkout_attribute_not_equals' => 'Checkout attribute not equals (key,value)',
                            'checkout_attribute_contains' => 'Checkout attribute contains (key,substring)',
                            'checkout_attribute_exists' => 'Checkout attribute exists (key)',
                            'line_items_has_line_without_selling_plan' => 'Cart has line without selling plan (one-time)',
                            'line_items_has_line_with_selling_plan' => 'Cart has line with selling plan (subscription)',
                            'line_item_property_equals' => 'Line item has property (key,value)',
                            'line_item_property_exists' => 'Line item has property key',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('value')
                        ->required()
                        ->maxLength(1000)
                        ->placeholder('e.g. search text, key,value for checkout_attribute, or /regex/ for SKU'),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * Build RuleEngine-compatible conditions array from form state (rule_match_type + rule_conditions).
     *
     * @param  array<string, mixed>  $state  May be nested e.g. ['rule_match_type' => 'and', 'rule_conditions' => [...]]
     * @return array<string, mixed>
     */
    public static function buildConditionsFromState(array $state): array
    {
        $matchType = (string) ($state['rule_match_type'] ?? 'and');
        $rows = $state['rule_conditions'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        return app(OfferBuilderService::class)->buildConditions($rows, $matchType);
    }
}
