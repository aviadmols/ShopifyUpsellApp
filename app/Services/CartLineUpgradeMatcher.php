<?php

namespace App\Services;

/**
 * Matches cart lines to upgrade mappings (subscription / bundle_swap) and builds
 * items list + actions for the upgrade card API.
 *
 * Config: upgrade_mappings (array of match + action_type + target_variant_id etc.),
 * plans (optional), optional cart-level conditions (subtotal_min, items_count_min).
 * Context: line_items, subtotal.
 */
class CartLineUpgradeMatcher
{
    /**
     * Normalize variant ID to Shopify GID for applyCartLinesChange (merchandiseId).
     */
    public static function variantToGid(?string $id): string
    {
        $id = trim((string) $id);
        if ($id === '') {
            return '';
        }
        if (str_starts_with($id, 'gid://')) {
            return $id;
        }
        $numeric = preg_replace('/\D/', '', $id);

        return $numeric !== '' ? 'gid://shopify/ProductVariant/'.$numeric : $id;
    }

    /**
     * Normalize selling plan ID to Shopify GID for applyCartLinesChange.
     */
    public static function sellingPlanToGid(?string $id): string
    {
        $id = trim((string) $id);
        if ($id === '') {
            return '';
        }
        if (str_starts_with($id, 'gid://')) {
            return $id;
        }
        $numeric = preg_replace('/\D/', '', $id);

        return $numeric !== '' ? 'gid://shopify/SellingPlan/'.$numeric : $id;
    }

    /**
     * Match a single cart line against a mapping's "match" criteria.
     *
     * @param  array<string, mixed>  $line  line_item (id, product_id, variant_id, properties, sku?)
     * @param  array<string, mixed>  $match  match config (product_id?, variant_id?, sku_regex?, sku_segment?, line_item_property_exists?, line_item_property_equals?)
     */
    public function lineMatches(array $line, array $match): bool
    {
        if ($match === []) {
            return false;
        }

        if (isset($match['product_id']) && (string) $match['product_id'] !== '') {
            $lineProductId = $this->normalizeId((string) ($line['product_id'] ?? ''));
            $matchProductId = $this->normalizeId((string) $match['product_id']);
            if ($lineProductId !== $matchProductId) {
                return false;
            }
        }

        if (isset($match['variant_id']) && (string) $match['variant_id'] !== '') {
            $lineVariantId = $this->normalizeId((string) ($line['variant_id'] ?? $line['merchandiseId'] ?? ''));
            $matchVariantId = $this->normalizeId((string) $match['variant_id']);
            if ($lineVariantId !== $matchVariantId) {
                return false;
            }
        }

        $sku = trim((string) ($line['sku'] ?? ''));
        if (isset($match['sku_regex']) && (string) $match['sku_regex'] !== '') {
            $regex = (string) $match['sku_regex'];
            if ($sku === '' || @preg_match($regex, $sku) !== 1) {
                return false;
            }
        }
        if (isset($match['sku_segment']) && (string) $match['sku_segment'] !== '') {
            $segment = (string) $match['sku_segment'];
            if ($sku === '' || str_contains($sku, $segment) === false) {
                return false;
            }
        }

        $properties = $this->extractProperties($line);
        if (isset($match['line_item_property_exists']) && (string) $match['line_item_property_exists'] !== '') {
            $key = (string) $match['line_item_property_exists'];
            if (! array_key_exists($key, $properties) && ! $this->propertyExistsCaseInsensitive($key, $properties)) {
                return false;
            }
        }
        if (isset($match['line_item_property_equals'])) {
            $eq = $match['line_item_property_equals'];
            if (is_array($eq)) {
                foreach ($eq as $key => $expected) {
                    $val = $properties[$key] ?? $this->propertyGetCaseInsensitive($key, $properties);
                    if ((string) $val !== (string) $expected) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Run matcher: return payload with items, plans, actions.
     *
     * @param  array<string, mixed>  $config  block config (upgrade_mappings, plans, headline, description, cta_label, cart_subtotal_min?, cart_items_count_min?)
     * @param  array<string, mixed>  $context  request context (line_items, subtotal)
     * @return array{enabled: bool, items: array, plans: array, actions: array, headline?: string, description?: string, cta_label?: string}
     */
    public function run(array $config, array $context): array
    {
        $subtotal = (float) ($context['subtotal'] ?? 0);
        $lineItems = $context['line_items'] ?? $context['lineItems'] ?? [];
        if (! is_array($lineItems)) {
            $lineItems = [];
        }

        $subtotalMin = isset($config['cart_subtotal_min']) ? (float) $config['cart_subtotal_min'] : null;
        $itemsCountMin = isset($config['cart_items_count_min']) ? (int) $config['cart_items_count_min'] : null;
        if ($subtotalMin !== null && $subtotal < $subtotalMin) {
            return $this->emptyPayload($config);
        }
        if ($itemsCountMin !== null && count($lineItems) < $itemsCountMin) {
            return $this->emptyPayload($config);
        }

        $mappings = $config['upgrade_mappings'] ?? [];
        if (! is_array($mappings) || $mappings === []) {
            return $this->emptyPayload($config);
        }

        $items = [];
        $actions = [];
        $usedLineIds = [];
        /** @var array{headline: string, description: string, cta_label: string}|null $firstOverrides */
        $firstOverrides = null;

        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lineId = $line['id'] ?? null;
            if ($lineId === null) {
                continue;
            }
            foreach ($mappings as $mapping) {
                if (! is_array($mapping)) {
                    continue;
                }
                $match = $mapping['match'] ?? [];
                if (! $this->lineMatches($line, $match)) {
                    continue;
                }

                $actionType = (string) ($mapping['action_type'] ?? 'subscription');
                $targetVariantId = $mapping['target_variant_id'] ?? $mapping['targetVariantId'] ?? null;
                $plans = $mapping['plans'] ?? [];
                if (is_array($plans) && $plans !== [] && isset($plans[0]['target_variant_id'])) {
                    $targetVariantId = $plans[0]['target_variant_id'] ?? $targetVariantId;
                }
                $targetVariantId = $targetVariantId ? self::variantToGid((string) $targetVariantId) : '';
                if ($targetVariantId === '') {
                    continue;
                }

                $quantity = (int) ($mapping['quantity'] ?? $line['quantity'] ?? 1);
                $quantity = $quantity < 1 ? 1 : $quantity;

                $items[] = [
                    'line_id' => $lineId,
                    'product_title' => $line['product_title'] ?? $line['productTitle'] ?? $line['title'] ?? 'Item',
                    'variant_title' => $line['variant_title'] ?? $line['variantTitle'] ?? null,
                ];

                // Allow selling plan on mapping root, or via first mapping plan entry.
                $sellingPlanId = '';
                if ($actionType === 'subscription') {
                    $sellingPlanId = (string) ($mapping['selling_plan_id'] ?? '');
                    if ($sellingPlanId === '' && is_array($plans) && isset($plans[0]['selling_plan_id'])) {
                        $sellingPlanId = (string) $plans[0]['selling_plan_id'];
                    }
                    $sellingPlanId = self::sellingPlanToGid($sellingPlanId);
                }

                $actions[] = [
                    'type' => 'removeCartLine',
                    'lineId' => $lineId,
                    'quantity' => (int) ($line['quantity'] ?? 1),
                ];
                $addAction = [
                    'type' => 'addCartLine',
                    'merchandiseId' => $targetVariantId,
                    'quantity' => $quantity,
                ];
                if ($sellingPlanId !== '') {
                    $addAction['sellingPlanId'] = $sellingPlanId;
                }
                $actions[] = $addAction;

                // Capture first-match text overrides (optional).
                if ($firstOverrides === null) {
                    $firstOverrides = [
                        'headline' => (string) ($mapping['headline'] ?? ''),
                        'description' => (string) ($mapping['description'] ?? ''),
                        'cta_label' => (string) ($mapping['cta_label'] ?? ''),
                    ];
                }

                $usedLineIds[$lineId] = true;
                break;
            }
        }

        $plans = $config['plans'] ?? [];
        if (! is_array($plans)) {
            $plans = [];
        }
        $plansPayload = [];
        foreach ($plans as $p) {
            if (! is_array($p)) {
                continue;
            }
            $id = $p['id'] ?? $p['value'] ?? '';
            if ((string) $id === '') {
                continue;
            }
            $plansPayload[] = [
                'id' => (string) $id,
                'label' => (string) ($p['label'] ?? $p['name'] ?? $id),
            ];
        }

        $enabled = count($items) > 0;
        $out = [
            'enabled' => $enabled,
            'items' => $items,
            'plans' => $plansPayload,
            'actions' => $actions,
        ];
        $headline = (string) ($config['headline'] ?? '');
        $description = (string) ($config['description'] ?? '');
        $cta = (string) ($config['cta_label'] ?? '');
        if (is_array($firstOverrides)) {
            if (trim((string) ($firstOverrides['headline'] ?? '')) !== '') {
                $headline = (string) $firstOverrides['headline'];
            }
            if (trim((string) ($firstOverrides['description'] ?? '')) !== '') {
                $description = (string) $firstOverrides['description'];
            }
            if (trim((string) ($firstOverrides['cta_label'] ?? '')) !== '') {
                $cta = (string) $firstOverrides['cta_label'];
            }
        }
        $out['headline'] = $headline;
        $out['description'] = $description;
        $out['cta_label'] = $cta !== '' ? $cta : 'Upgrade';

        return $out;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{enabled: false, items: array, plans: array, actions: array, headline?: string, description?: string, cta_label?: string}
     */
    private function emptyPayload(array $config): array
    {
        $out = [
            'enabled' => false,
            'items' => [],
            'plans' => [],
            'actions' => [],
        ];
        if (isset($config['headline'])) {
            $out['headline'] = (string) $config['headline'];
        }
        if (isset($config['description'])) {
            $out['description'] = (string) $config['description'];
        }
        if (isset($config['cta_label'])) {
            $out['cta_label'] = (string) $config['cta_label'];
        }

        return $out;
    }

    private function normalizeId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }
        if (str_starts_with($id, 'gid://')) {
            preg_match('/\d+/', $id, $m);
            return $m ? $m[0] : $id;
        }
        return preg_replace('/\D/', '', $id) ?: $id;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, string>
     */
    private function extractProperties(array $line): array
    {
        $candidates = [
            $line['properties'] ?? null,
            $line['attributes'] ?? null,
            $line['customAttributes'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            $out = [];
            foreach ($candidate as $k => $v) {
                $key = trim((string) $k);
                $value = trim((string) $v);
                if ($key !== '' && $value !== '') {
                    $out[$key] = $value;
                }
            }
            if ($out !== []) {
                return $out;
            }
        }
        return [];
    }

    /**
     * @param  array<string, string>  $properties
     */
    private function propertyExistsCaseInsensitive(string $key, array $properties): bool
    {
        $keyLower = mb_strtolower($key);
        foreach ($properties as $k => $v) {
            if (mb_strtolower((string) $k) === $keyLower) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param  array<string, string>  $properties
     */
    private function propertyGetCaseInsensitive(string $key, array $properties): ?string
    {
        $keyLower = mb_strtolower($key);
        foreach ($properties as $k => $v) {
            if (mb_strtolower((string) $k) === $keyLower) {
                return (string) $v;
            }
        }
        return null;
    }
}
