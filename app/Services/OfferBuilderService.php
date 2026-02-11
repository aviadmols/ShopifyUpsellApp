<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Placement;
use App\Models\Rule;
use App\Models\ThankYouBlock;

class OfferBuilderService
{
    /**
     * Build RuleEngine-compatible conditions JSON from builder rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function buildConditions(array $rows, string $matchType = 'and'): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $field = (string) ($row['field'] ?? '');
            $value = $row['value'] ?? null;

            if ($field === '') {
                continue;
            }

            $condition = $this->buildSingleCondition($field, $value);
            if ($condition !== null) {
                $normalized[] = $condition;
            }
        }

        if (empty($normalized)) {
            return [];
        }

        $group = strtolower($matchType) === 'or' ? 'or' : 'and';

        return [$group => $normalized];
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>|null
     */
    protected function buildSingleCondition(string $field, mixed $value): ?array
    {
        return match ($field) {
            'subtotal_gte' => ['subtotal_gte' => (float) $value],
            'subtotal_lte' => ['subtotal_lte' => (float) $value],
            'line_items_has_product_id' => ['line_items_has_product_id' => (int) $value],
            'line_items_has_any_product_id' => ['line_items_has_any_product_id' => $this->csvToIntArray($value)],
            'customer_has_tag' => ['customer_has_tag' => (string) $value],
            'shipping_country_in' => ['shipping_country_in' => $this->csvToUpperArray($value)],
            'utm_param_equals' => ['utm_param_equals' => (string) $value],
            'utm_param_contains' => ['utm_param_contains' => (string) $value],
            'url_param_equals' => ['url_param_equals' => (string) $value],
            'url_param_contains' => ['url_param_contains' => (string) $value],
            'line_item_property_equals' => ['line_item_property_equals' => (string) $value],
            'line_item_property_exists' => ['line_item_property_exists' => (string) $value],
            default => null,
        };
    }

    /**
     * @param  mixed  $value
     * @return array<int, int>
     */
    protected function csvToIntArray(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('intval', $parts), fn ($v) => $v > 0));
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    protected function csvToUpperArray(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            static fn ($v) => strtoupper(trim((string) $v)),
            $parts
        )));
    }

    /**
     * Create/update/unlink Rule for this offer based on builder inputs.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncRule(Offer $offer, array $data): void
    {
        $enabled = (bool) ($data['rule_enabled'] ?? false);
        $rows = $data['rule_conditions'] ?? [];
        $matchType = (string) ($data['rule_match_type'] ?? 'and');
        $conditions = $this->buildConditions(is_array($rows) ? $rows : [], $matchType);

        if (! $enabled || empty($conditions)) {
            $offer->rule()->dissociate();
            $offer->save();
            return;
        }

        $name = trim((string) ($data['rule_name'] ?? ''));
        if ($name === '') {
            $name = "Rule for offer #{$offer->id}";
        }

        $rule = $offer->rule;
        if ($rule && $rule->offers()->where('id', '!=', $offer->id)->exists()) {
            $rule = null;
        }

        if (! $rule) {
            $rule = new Rule();
            $rule->shop_id = $offer->shop_id;
        }

        $rule->name = $name;
        $rule->conditions = $conditions;
        $rule->save();

        $offer->rule()->associate($rule);
        $offer->save();
    }

    /**
     * Sync offer placement configs (checkout/post_purchase/thank_you).
     *
     * @param  array<string, mixed>  $data
     */
    public function syncPlacements(Offer $offer, array $data): void
    {
        $selected = array_values(array_intersect(
            Placement::placementTypes(),
            (array) ($data['placement_types'] ?? [])
        ));

        $allTypes = Placement::placementTypes();
        foreach ($allTypes as $type) {
            $placement = Placement::firstOrCreate(
                ['shop_id' => $offer->shop_id, 'placement_type' => $type],
                ['config' => []]
            );

            if (in_array($type, $selected, true)) {
                $config = $placement->config ?? [];

                if ($type === 'checkout') {
                    $config = $this->syncCheckoutPlacement($config, $offer, $data);
                } elseif ($type === 'post_purchase') {
                    $config = $this->syncPostPurchasePlacement($config, $offer, $data);
                } elseif ($type === 'thank_you') {
                    $config = $this->syncThankYouPlacement($config, $offer, $data);
                }

                $placement->config = $config;
                $placement->save();
            } else {
                $config = $placement->config ?? [];
                $config['offer_ids'] = $this->removeIdFromList($config['offer_ids'] ?? [], $offer->id);
                if ($type === 'thank_you') {
                    $config['block_ids'] = $this->removeOfferThankYouBlock($offer, $config['block_ids'] ?? []);
                }
                $placement->config = $config;
                $placement->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function syncCheckoutPlacement(array $config, Offer $offer, array $data): array
    {
        $config['offer_ids'] = $this->addIdToList($config['offer_ids'] ?? [], $offer->id);
        $config['max_offers'] = max(1, (int) ($data['checkout_max_offers'] ?? $config['max_offers'] ?? 3));
        $config['priority'] = (int) ($data['checkout_priority'] ?? $config['priority'] ?? 100);
        $config['display_mode'] = (string) ($data['checkout_display_mode'] ?? $config['display_mode'] ?? 'stacked');
        $config['require_expanded'] = (bool) ($data['checkout_require_expanded'] ?? $config['require_expanded'] ?? false);

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function syncPostPurchasePlacement(array $config, Offer $offer, array $data): array
    {
        $config['offer_ids'] = $this->addIdToList($config['offer_ids'] ?? [], $offer->id);
        $config['max_offers'] = max(1, (int) ($data['post_purchase_max_offers'] ?? $config['max_offers'] ?? 1));
        $config['cooldown_hours'] = max(0, (int) ($data['post_purchase_cooldown_hours'] ?? $config['cooldown_hours'] ?? 24));
        $config['allow_reoffer'] = (bool) ($data['post_purchase_allow_reoffer'] ?? $config['allow_reoffer'] ?? false);
        $config['show_timer'] = (bool) ($data['post_purchase_show_timer'] ?? $config['show_timer'] ?? false);
        $config['timer_seconds'] = max(0, (int) ($data['post_purchase_timer_seconds'] ?? $config['timer_seconds'] ?? 300));

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function syncThankYouPlacement(array $config, Offer $offer, array $data): array
    {
        $enable = (bool) ($data['thank_you_enable_product_card'] ?? true);

        if (! $enable) {
            $config['block_ids'] = $this->removeOfferThankYouBlock($offer, $config['block_ids'] ?? []);

            return $config;
        }

        $block = $this->upsertOfferThankYouBlock($offer, $data);
        $config['block_ids'] = $this->addIdToList($config['block_ids'] ?? [], $block->id);

        return $config;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function upsertOfferThankYouBlock(Offer $offer, array $data): ThankYouBlock
    {
        $existing = ThankYouBlock::where('shop_id', $offer->shop_id)
            ->where('type', 'product_card')
            ->get()
            ->first(function (ThankYouBlock $block) use ($offer) {
                return (int) ($block->config['offer_id'] ?? 0) === (int) $offer->id;
            });

        $config = [
            'offer_id' => $offer->id,
            'title' => (string) ($data['thank_you_title'] ?? $offer->title),
            'body' => (string) ($data['thank_you_body'] ?? ($offer->description ?? '')),
            'button_url' => (string) ($data['thank_you_button_url'] ?? ''),
            'product_variant_id' => $offer->product_variant_id,
            'image_url' => $offer->image_url,
            'discount_type' => $offer->discount_type,
            'discount_value' => $offer->discount_value?->toString(),
            'offer_type' => (string) ($offer->offer_type ?? 'one_time'),
            'selling_plan_id' => $offer->selling_plan_id ? (string) $offer->selling_plan_id : null,
        ];

        $sort = (int) ($data['thank_you_sort_order'] ?? 100);

        if ($existing) {
            $existing->config = $config;
            $existing->sort_order = $sort;
            $existing->save();

            return $existing;
        }

        return ThankYouBlock::create([
            'shop_id' => $offer->shop_id,
            'type' => 'product_card',
            'config' => $config,
            'sort_order' => $sort,
        ]);
    }

    /**
     * @param  array<int, int>|string  $list
     * @return array<int, int>
     */
    protected function addIdToList(array|string $list, int $id): array
    {
        $ids = Placement::normalizeIntList($list);
        if (! in_array($id, $ids, true)) {
            $ids[] = $id;
        }

        return array_values($ids);
    }

    /**
     * @param  array<int, int>|string  $list
     * @return array<int, int>
     */
    protected function removeIdFromList(array|string $list, int $id): array
    {
        return array_values(array_filter(
            Placement::normalizeIntList($list),
            fn ($existing) => (int) $existing !== (int) $id
        ));
    }

    /**
     * Remove thank-you block tied to the offer and return updated block_ids.
     *
     * @param  array<int, int>|string  $blockIds
     * @return array<int, int>
     */
    protected function removeOfferThankYouBlock(Offer $offer, array|string $blockIds): array
    {
        $ids = Placement::normalizeIntList($blockIds);

        $blocks = ThankYouBlock::where('shop_id', $offer->shop_id)
            ->where('type', 'product_card')
            ->get();

        foreach ($blocks as $block) {
            if ((int) ($block->config['offer_id'] ?? 0) !== (int) $offer->id) {
                continue;
            }

            $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $block->id));
            $block->delete();
        }

        return $ids;
    }

    /**
     * Build default form state for placement+rule from existing offer relations.
     *
     * @return array<string, mixed>
     */
    public function buildEditState(Offer $offer): array
    {
        $state = [
            'placement_types' => [],
            'offer_type' => (string) ($offer->offer_type ?? 'one_time'),
            'selling_plan_id' => (string) ($offer->selling_plan_id ?? ''),
            'recharge_subscription_variant_id' => (string) ($offer->recharge_subscription_variant_id ?? ''),
            'allow_subscription_in_post_purchase' => (bool) ($offer->allow_subscription_in_post_purchase ?? false),
            'checkout_max_offers' => 3,
            'checkout_priority' => 100,
            'checkout_display_mode' => 'stacked',
            'checkout_require_expanded' => false,
            'post_purchase_max_offers' => 1,
            'post_purchase_cooldown_hours' => 24,
            'post_purchase_allow_reoffer' => false,
            'post_purchase_show_timer' => false,
            'post_purchase_timer_seconds' => 300,
            'thank_you_enable_product_card' => false,
            'thank_you_title' => $offer->title,
            'thank_you_body' => $offer->description,
            'thank_you_button_url' => '',
            'thank_you_sort_order' => 100,
            'rule_enabled' => false,
            'rule_name' => '',
            'rule_match_type' => 'and',
            'rule_conditions' => [],
        ];

        $placements = Placement::where('shop_id', $offer->shop_id)->get()->keyBy('placement_type');

        $checkout = $placements->get('checkout');
        if ($checkout && in_array($offer->id, $checkout->getOfferIds(), true)) {
            $state['placement_types'][] = 'checkout';
            $state['checkout_max_offers'] = (int) ($checkout->config['max_offers'] ?? 3);
            $state['checkout_priority'] = (int) ($checkout->config['priority'] ?? 100);
            $state['checkout_display_mode'] = (string) ($checkout->config['display_mode'] ?? 'stacked');
            $state['checkout_require_expanded'] = (bool) ($checkout->config['require_expanded'] ?? false);
        }

        $post = $placements->get('post_purchase');
        if ($post && in_array($offer->id, $post->getOfferIds(), true)) {
            $state['placement_types'][] = 'post_purchase';
            $state['post_purchase_max_offers'] = (int) ($post->config['max_offers'] ?? 1);
            $state['post_purchase_cooldown_hours'] = (int) ($post->config['cooldown_hours'] ?? 24);
            $state['post_purchase_allow_reoffer'] = (bool) ($post->config['allow_reoffer'] ?? false);
            $state['post_purchase_show_timer'] = (bool) ($post->config['show_timer'] ?? false);
            $state['post_purchase_timer_seconds'] = (int) ($post->config['timer_seconds'] ?? 300);
        }

        $tyBlock = ThankYouBlock::where('shop_id', $offer->shop_id)
            ->where('type', 'product_card')
            ->get()
            ->first(function (ThankYouBlock $block) use ($offer) {
                return (int) ($block->config['offer_id'] ?? 0) === (int) $offer->id;
            });

        if ($tyBlock) {
            $state['placement_types'][] = 'thank_you';
            $state['thank_you_enable_product_card'] = true;
            $state['thank_you_title'] = (string) ($tyBlock->config['title'] ?? $offer->title);
            $state['thank_you_body'] = (string) ($tyBlock->config['body'] ?? ($offer->description ?? ''));
            $state['thank_you_button_url'] = (string) ($tyBlock->config['button_url'] ?? '');
            $state['thank_you_sort_order'] = (int) ($tyBlock->sort_order ?? 100);
        }

        $rule = $offer->rule;
        if ($rule && ! empty($rule->conditions)) {
            $state['rule_enabled'] = true;
            $state['rule_name'] = $rule->name;
            $state['rule_match_type'] = isset($rule->conditions['or']) ? 'or' : 'and';
            $source = (array) ($rule->conditions[$state['rule_match_type']] ?? []);
            $state['rule_conditions'] = $this->flattenConditionsForForm($source);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @return array{match_type: string, rows: array<int, array<string, mixed>>}
     */
    public function ruleFormStateFromConditions(array $conditions): array
    {
        $matchType = isset($conditions['or']) ? 'or' : 'and';
        $source = (array) ($conditions[$matchType] ?? []);

        return [
            'match_type' => $matchType,
            'rows' => $this->flattenConditionsForForm($source),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     * @return array<int, array<string, mixed>>
     */
    protected function flattenConditionsForForm(array $conditions): array
    {
        $rows = [];

        foreach ($conditions as $cond) {
            if (! is_array($cond)) {
                continue;
            }

            foreach ($cond as $field => $value) {
                if (is_array($value)) {
                    $value = implode(',', $value);
                }

                $rows[] = [
                    'field' => $this->normalizeConditionField((string) $field),
                    'value' => (string) $value,
                ];
                break;
            }
        }

        return $rows;
    }

    protected function normalizeConditionField(string $field): string
    {
        return match ($field) {
            'order_subtotal_gte', 'cart_subtotal_gte' => 'subtotal_gte',
            'order_subtotal_lte', 'cart_subtotal_lte' => 'subtotal_lte',
            default => $field,
        };
    }
}

