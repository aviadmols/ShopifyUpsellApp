<?php

namespace App\Services;

use App\Filament\Widgets\WidgetRegistry;

/**
 * Builds a centralized schema of all block types, config fields, and rule conditions
 * for the AI widget generator.
 */
class BlockAISchemaService
{
    /** @return array<string, mixed> */
    public function fullSchema(): array
    {
        return [
            'endpoints' => $this->endpoints(),
            'surfaces' => WidgetRegistry::surfaces(),
            'block_types' => $this->blockTypes(),
            'rule_conditions' => $this->ruleConditions(),
        ];
    }

    /** @return array<int, array<string, string>> */
    private function endpoints(): array
    {
        return [
            [
                'name' => 'Checkout offers',
                'url' => 'POST /api/checkout/offers',
                'expects' => 'shop, block_id (widget ID), subtotal, line_items (array with product_id, variant_id, quantity, properties). Optional: utms, url_params.',
                'returns' => 'offers, blocks, ui. Block is shown in Shopify Checkout when Widget ID = this block id.',
            ],
            [
                'name' => 'Checkout upgrade card',
                'url' => 'POST /api/checkout/upgrade-card',
                'expects' => 'shop, block_id (widget ID), subtotal, line_items (array with id, product_id, variant_id, quantity, properties, optional sku). Optional: session_key.',
                'returns' => 'enabled, headline, description, items (line_id, product_title, variant_title), plans, cta_label, actions (removeCartLine/addCartLine for applyCartLinesChange).',
            ],
            [
                'name' => 'Thank you blocks',
                'url' => 'GET /api/thankyou/blocks',
                'expects' => 'shop, order_id.',
                'returns' => 'Blocks for thank-you page.',
            ],
            [
                'name' => 'Post-purchase',
                'url' => 'Post-purchase extension API',
                'expects' => 'Shopify post-purchase context.',
                'returns' => 'Funnel offers.',
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function blockTypes(): array
    {
        $types = [];
        foreach (WidgetRegistry::allTypeLabels() as $type => $label) {
            $types[$type] = [
                'label' => $label,
                'surfaces' => $this->surfacesForType($type),
                'config_schema' => $this->configSchemaForType($type),
            ];
        }
        return $types;
    }

    /** @return array<string> */
    private function surfacesForType(string $type): array
    {
        $surfaces = [];
        foreach (WidgetRegistry::surfaces() as $s) {
            $opts = WidgetRegistry::typeOptionsForSurface($s);
            if (isset($opts[$type])) {
                $surfaces[] = $s;
            }
        }
        return $surfaces;
    }

    /** @return array<string, string> */
    private function configSchemaForType(string $type): array
    {
        if ($type === 'upsell') {
            return [
                'offer_ids' => 'array of offer IDs (integers)',
                'max_offers' => 'int, default 3',
                'display_mode' => 'stacked|single|grid|row',
                'section_heading' => 'string',
                'title_size' => 'small|medium|large|extraLarge',
                'show_price' => 'bool',
                'show_description' => 'bool',
                'button_kind' => 'primary|secondary|plain',
                'card_spacing' => 'tight|loose|extraLoose',
                'show_quantity' => 'bool',
                'runtime_variables' => 'object (optional). Defines computed template vars usable in text fields as {var_name}. Example: dog_names_message via plural_message_from_property on line item property "Dog Name".',
            ];
        }
        if ($type === 'checkout_upgrade_card') {
            return [
                'headline' => 'string. Card headline.',
                'description' => 'string. Short description in the card.',
                'cta_label' => 'string. Button label (e.g. Upgrade to subscription).',
                'upgrade_mappings' => 'array of objects. Each object: match (product_id?, variant_id?, sku_regex?, sku_segment?, line_item_property_exists?, line_item_property_equals?), action_type (subscription|bundle_swap), target_variant_id (Shopify variant GID or numeric), quantity (default 1), plans (optional array of { id, label, target_variant_id?, selling_plan_id? } for subscription).',
                'plans' => 'optional array of { id, label } for dropdown (e.g. Deliver every 1 month).',
                'cart_subtotal_min' => 'optional number. Show card only when cart subtotal >= this.',
                'cart_items_count_min' => 'optional int. Show card only when cart has at least this many items.',
                'runtime_variables' => 'object (optional). Computed template vars usable in headline/description/cta_label as {var_name}.',
            ];
        }
        if ($type === 'progress_bar') {
            return [
                'progress_bar_type' => 'free_shipping|discount',
                'progress_bar_goal' => 'float',
                'progress_bar_message_below' => 'string, use {amount}',
                'progress_bar_message_achieved' => 'string',
                'runtime_variables' => 'object (optional). Computed template vars usable in text fields as {var_name}.',
            ];
        }
        if ($type === 'content_icon_features') {
            return [
                'icon_features' => 'array of {icon: lock|bag|truck|gift|checkCircle, title, subtitle}',
                'runtime_variables' => 'object (optional). Computed template vars usable in text fields as {var_name}.',
            ];
        }
        if (in_array($type, ['content_banner', 'content_rich_text', 'content_button'], true)) {
            return [
                'title' => 'string',
                'body' => 'string',
                'image_url' => 'string',
                'button_label' => 'string',
                'button_url' => 'string',
                'text_size' => 'small|medium|large',
                'spacing' => 'tight|loose',
                'runtime_variables' => 'object (optional). Computed template vars usable in text fields as {var_name}.',
            ];
        }
        if ($type === 'content_product_card') {
            return [
                'title' => 'string',
                'body' => 'string',
                'image_url' => 'string',
                'product_id' => 'string',
                'price_text' => 'string',
                'button_label' => 'string',
                'button_url' => 'string',
                'text_size' => 'small|medium|large',
                'spacing' => 'tight|loose',
                'runtime_variables' => 'object (optional). Computed template vars usable in text fields as {var_name}.',
            ];
        }
        if ($type === 'post_purchase_funnel') {
            return [
                'offer_ids' => 'array of offer IDs',
                'max_offers' => 'int',
                'cooldown_hours' => 'int',
                'funnel_headline_template' => 'string, use {first_name}',
                'cta_text' => 'string',
                'decline_text' => 'string',
                'runtime_variables' => 'object (optional). Computed template vars usable in text fields as {var_name}.',
            ];
        }
        return [];
    }

    /** @return array<int, array<string, string>> */
    private function ruleConditions(): array
    {
        return [
            ['field' => 'subtotal_gte', 'value_format' => 'number', 'description' => 'Cart subtotal >= value'],
            ['field' => 'subtotal_lte', 'value_format' => 'number', 'description' => 'Cart subtotal <= value'],
            ['field' => 'line_items_has_product_id', 'value_format' => 'product ID', 'description' => 'Cart has this product ID'],
            ['field' => 'line_items_has_any_product_id', 'value_format' => 'comma product IDs', 'description' => 'Cart has any of these products'],
            ['field' => 'line_items_has_variant_id', 'value_format' => 'variant ID or GID', 'description' => 'Cart has this variant ID (numeric or gid://shopify/ProductVariant/...)'],
            ['field' => 'line_items_has_any_variant_id', 'value_format' => 'comma variant IDs or GIDs', 'description' => 'Cart has any of these variants'],
            ['field' => 'customer_has_tag', 'value_format' => 'tag', 'description' => 'Customer has tag'],
            ['field' => 'shipping_country_in', 'value_format' => 'US,IL', 'description' => 'Shipping country in list'],
            ['field' => 'utm_param_equals', 'value_format' => 'param,value', 'description' => 'UTM param equals'],
            ['field' => 'utm_param_contains', 'value_format' => 'param,substring', 'description' => 'UTM param contains'],
            ['field' => 'url_param_equals', 'value_format' => 'param,value', 'description' => 'URL param equals'],
            ['field' => 'url_param_contains', 'value_format' => 'param,substring', 'description' => 'URL param contains'],
            ['field' => 'line_item_property_equals', 'value_format' => 'key,value', 'description' => 'Line item property (e.g. subscription, no)'],
            ['field' => 'line_item_property_exists', 'value_format' => 'key', 'description' => 'Line item has property key (e.g. SKU in properties)'],
            ['field' => 'line_item_sku_matches', 'value_format' => 'regex', 'description' => 'At least one line item SKU matches regex (e.g. /^XXX-XXX-\\d+-XXX$/)'],
            ['field' => 'line_item_sku_segment_between', 'value_format' => 'segment_index,min,max or separator,segment_index,min,max', 'description' => 'SKU split by separator (default -), segment at index numeric between min and max'],
        ];
    }
}
