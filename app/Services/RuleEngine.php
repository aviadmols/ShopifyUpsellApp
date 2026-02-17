<?php

namespace App\Services;

/**
 * Evaluates JSON rule conditions against a context (order/cart).
 * Condition keys: order_subtotal_gte, order_subtotal_lte, line_items_has_product_ids,
 * customer_has_tag, shipping_country_in, cart_subtotal_gte, etc.
 */
class RuleEngine
{
    /**
     * Evaluate rule conditions (JSON array/object) against context.
     *
     * @param  array<string, mixed>  $conditions  e.g. ["and" => [["order_subtotal_gte" => 100], ["line_items_has_product_id" => 123]]]
     * @param  array<string, mixed>  $context  e.g. ["subtotal" => 150, "line_items" => [...], "customer" => [...], "shipping_country" => "US"]
     * @return bool
     */
    public function evaluate(array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        if (isset($conditions['and'])) {
            return $this->evaluateAnd($conditions['and'], $context);
        }
        if (isset($conditions['or'])) {
            return $this->evaluateOr($conditions['or'], $context);
        }

        return $this->evaluateSingle($conditions, $context);
    }

    /**
     * All conditions must be true.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function evaluateAnd(array $items, array $context): bool
    {
        foreach ($items as $cond) {
            if (! $this->evaluate($cond, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * At least one condition must be true.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function evaluateOr(array $items, array $context): bool
    {
        foreach ($items as $cond) {
            if ($this->evaluate($cond, $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Evaluate a single condition key-value.
     *
     * @param  array<string, mixed>  $condition
     */
    protected function evaluateSingle(array $condition, array $context): bool
    {
        foreach ($condition as $key => $value) {
            return $this->evaluateCondition($key, $value, $context);
        }
        return true;
    }

    /**
     * Dispatch to specific condition handler by key.
     */
    protected function evaluateCondition(string $key, mixed $value, array $context): bool
    {
        return match ($key) {
            'order_subtotal_gte', 'subtotal_gte', 'cart_subtotal_gte' => $this->subtotalGte($context, $value),
            'order_subtotal_lte', 'subtotal_lte', 'cart_subtotal_lte' => $this->subtotalLte($context, $value),
            // Accept numeric IDs or Shopify GIDs (e.g. gid://shopify/Product/123).
            // For backwards-compatibility, "product_id" checks also accept matching variant IDs if product_id isn't present in line payload.
            'line_items_has_product_id' => $this->lineItemsHasProductId($context, $value),
            'line_items_has_any_product_id' => $this->lineItemsHasAnyProductId($context, (array) $value),
            'line_items_has_variant_id' => $this->lineItemsHasVariantId($context, $value),
            'line_items_has_any_variant_id' => $this->lineItemsHasAnyVariantId($context, (array) $value),
            'customer_has_tag' => $this->customerHasTag($context, (string) $value),
            'shipping_country_in' => $this->shippingCountryIn($context, (array) $value),
            'utm_param_equals' => $this->utmParamEquals($context, $value),
            'utm_param_contains' => $this->utmParamContains($context, $value),
            'url_param_equals' => $this->urlParamEquals($context, $value),
            'url_param_contains' => $this->urlParamContains($context, $value),
            'checkout_attribute_equals' => $this->checkoutAttributeEquals($context, $value),
            'checkout_attribute_not_equals' => $this->checkoutAttributeNotEquals($context, $value),
            'checkout_attribute_contains' => $this->checkoutAttributeContains($context, $value),
            'checkout_attribute_exists' => $this->checkoutAttributeExists($context, (string) $value),
            'line_item_property_equals' => $this->lineItemPropertyEquals($context, $value),
            'line_item_property_exists' => $this->lineItemPropertyExists($context, (string) $value),
            'line_item_sku_matches' => $this->lineItemSkuMatches($context, $value),
            'line_item_sku_segment_between' => $this->lineItemSkuSegmentBetween($context, $value),
            default => false,
        };
    }

    protected function subtotalGte(array $context, mixed $min): bool
    {
        $subtotal = (float) ($context['subtotal'] ?? $context['order']['subtotal'] ?? 0);
        return $subtotal >= (float) $min;
    }

    protected function subtotalLte(array $context, mixed $max): bool
    {
        $subtotal = (float) ($context['subtotal'] ?? $context['order']['subtotal'] ?? 0);
        return $subtotal <= (float) $max;
    }

    protected function lineItemsHasProductId(array $context, mixed $productId): bool
    {
        $want = $this->normalizeId($productId);
        if ($want === '') {
            return false;
        }
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $pidRaw = $line['product_id'] ?? $line['productId'] ?? null;
            $vidRaw = $line['variant_id'] ?? $line['variantId'] ?? $line['merchandiseId'] ?? $line['id'] ?? null;

            $pid = $this->normalizeId($pidRaw);
            $vid = $this->normalizeId($vidRaw);

            // Be tolerant: Shopify payloads vary; match either product_id or variant_id.
            if (($pid !== '' && $pid === $want) || ($vid !== '' && $vid === $want)) {
                return true;
            }
        }
        return false;
    }

    protected function lineItemsHasAnyProductId(array $context, array $productIds): bool
    {
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        $ids = array_values(array_filter(array_map(fn ($v) => $this->normalizeId($v), $productIds), fn ($v) => $v !== ''));
        if ($ids === []) {
            return false;
        }
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $pidRaw = $line['product_id'] ?? $line['productId'] ?? null;
            $vidRaw = $line['variant_id'] ?? $line['variantId'] ?? $line['merchandiseId'] ?? $line['id'] ?? null;

            $pid = $this->normalizeId($pidRaw);
            $vid = $this->normalizeId($vidRaw);

            if (($pid !== '' && in_array($pid, $ids, true)) || ($vid !== '' && in_array($vid, $ids, true))) {
                return true;
            }
        }
        return false;
    }

    protected function lineItemsHasVariantId(array $context, mixed $variantId): bool
    {
        $want = $this->normalizeId($variantId);
        if ($want === '') {
            return false;
        }
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $vidRaw = $line['variant_id'] ?? $line['variantId'] ?? $line['merchandiseId'] ?? $line['id'] ?? null;
            $vid = $this->normalizeId($vidRaw);
            if ($vid !== '' && $vid === $want) {
                return true;
            }
        }
        return false;
    }

    protected function lineItemsHasAnyVariantId(array $context, array $variantIds): bool
    {
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        $ids = array_values(array_filter(array_map(fn ($v) => $this->normalizeId($v), $variantIds), fn ($v) => $v !== ''));
        if ($ids === []) {
            return false;
        }
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $vidRaw = $line['variant_id'] ?? $line['variantId'] ?? $line['merchandiseId'] ?? $line['id'] ?? null;
            $vid = $this->normalizeId($vidRaw);
            if ($vid !== '' && in_array($vid, $ids, true)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeId(mixed $id): string
    {
        if ($id === null) {
            return '';
        }
        $str = trim((string) $id);
        if ($str === '') {
            return '';
        }
        if (str_starts_with($str, 'gid://')) {
            preg_match('/\d+/', $str, $m);
            return $m ? $m[0] : '';
        }
        $digits = preg_replace('/\D/', '', $str);
        return $digits !== '' ? $digits : $str;
    }

    protected function customerHasTag(array $context, string $tag): bool
    {
        $tags = $context['customer']['tags'] ?? $context['customer']['tags_array'] ?? [];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }
        return in_array($tag, $tags, true);
    }

    protected function shippingCountryIn(array $context, array $countries): bool
    {
        $country = $context['shipping_country'] ?? $context['shipping_address']['country_code'] ?? $context['shippingAddress']['countryCode'] ?? '';
        return in_array(strtoupper($country), array_map('strtoupper', $countries), true);
    }

    /**
     * UTM/URL params: value can be "param_name,expected_value" or JSON array [param, value].
     * Context: utms (array), url_params (array) — from request query and/or cookies/session.
     */
    protected function utmParamEquals(array $context, mixed $value): bool
    {
        [$param, $expected] = $this->parseParamValue($value);
        $utms = $context['utms'] ?? $context['utm'] ?? [];
        $actual = is_array($utms) ? ($utms[$param] ?? null) : null;
        return $actual !== null && (string) $actual === (string) $expected;
    }

    protected function utmParamContains(array $context, mixed $value): bool
    {
        [$param, $substring] = $this->parseParamValue($value);
        $utms = $context['utms'] ?? $context['utm'] ?? [];
        $actual = is_array($utms) ? ($utms[$param] ?? '') : '';
        return str_contains((string) $actual, (string) $substring);
    }

    protected function urlParamEquals(array $context, mixed $value): bool
    {
        [$param, $expected] = $this->parseParamValue($value);
        $params = $context['url_params'] ?? $context['query'] ?? [];
        $actual = is_array($params) ? ($params[$param] ?? null) : null;
        return $actual !== null && (string) $actual === (string) $expected;
    }

    protected function urlParamContains(array $context, mixed $value): bool
    {
        [$param, $substring] = $this->parseParamValue($value);
        $params = $context['url_params'] ?? $context['query'] ?? [];
        $actual = is_array($params) ? ($params[$param] ?? '') : '';
        return str_contains((string) $actual, (string) $substring);
    }

    protected function checkoutAttributeEquals(array $context, mixed $value): bool
    {
        [$key, $expected] = $this->parseParamValue($value);
        $attrs = $context['checkout_attributes'] ?? [];
        $actual = is_array($attrs) ? ($attrs[$key] ?? null) : null;
        return $actual !== null && (string) $actual === (string) $expected;
    }

    protected function checkoutAttributeContains(array $context, mixed $value): bool
    {
        [$key, $substring] = $this->parseParamValue($value);
        $attrs = $context['checkout_attributes'] ?? [];
        $actual = is_array($attrs) ? ($attrs[$key] ?? '') : '';
        return str_contains((string) $actual, (string) $substring);
    }

    protected function checkoutAttributeNotEquals(array $context, mixed $value): bool
    {
        [$key, $expected] = $this->parseParamValue($value);
        $attrs = $context['checkout_attributes'] ?? [];
        $actual = is_array($attrs) ? ($attrs[$key] ?? null) : null;
        return $actual === null || (string) $actual !== (string) $expected;
    }

    protected function checkoutAttributeExists(array $context, string $key): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }
        $attrs = $context['checkout_attributes'] ?? [];
        if (! is_array($attrs)) {
            return false;
        }
        return array_key_exists($key, $attrs) && (string) ($attrs[$key] ?? '') !== '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parseParamValue(mixed $value): array
    {
        if (is_array($value)) {
            $param = (string) ($value[0] ?? $value['param'] ?? '');
            $val = (string) ($value[1] ?? $value['value'] ?? '');

            return [$param, $val];
        }
        $str = (string) $value;
        if (str_contains($str, ',')) {
            $parts = explode(',', $str, 2);
            return [trim($parts[0]), trim($parts[1] ?? '')];
        }
        return [$str, ''];
    }

    /**
     * Line item property: value "property_key,expected_value" or "property_key" for exists.
     * Context line_items: each item can have 'properties' (array key=>value) or 'attributes'.
     */
    protected function lineItemPropertyEquals(array $context, mixed $value): bool
    {
        [$propKey, $expected] = $this->parseParamValue($value);
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        foreach ($lines as $line) {
            $props = $line['properties'] ?? $line['attributes'] ?? $line['customAttributes'] ?? [];
            if (! is_array($props)) {
                continue;
            }
            $actual = $props[$propKey] ?? null;
            if ($actual !== null && (string) $actual === (string) $expected) {
                return true;
            }
        }
        return false;
    }

    protected function lineItemPropertyExists(array $context, string $propKey): bool
    {
        $propKey = trim($propKey);
        if ($propKey === '') {
            return false;
        }
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        foreach ($lines as $line) {
            $props = $line['properties'] ?? $line['attributes'] ?? $line['customAttributes'] ?? [];
            if (! is_array($props)) {
                continue;
            }
            if (array_key_exists($propKey, $props) && (string) ($props[$propKey] ?? '') !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get SKU for each line item (from sku key or properties).
     *
     * @return array<int, string>
     */
    protected function getLineItemSkus(array $context): array
    {
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        $skus = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = $line['sku'] ?? null;
            if ($sku === null || $sku === '') {
                $props = $line['properties'] ?? $line['attributes'] ?? $line['customAttributes'] ?? [];
                if (is_array($props)) {
                    $sku = $props['sku'] ?? $props['SKU'] ?? '';
                }
            }
            $skus[] = trim((string) $sku);
        }
        return $skus;
    }

    /**
     * At least one line item has SKU matching the given regex.
     * Value: regex string (e.g. "/^XXX-XXX-\d+-XXX$/") or pattern without delimiters (then wrapped as regex).
     */
    protected function lineItemSkuMatches(array $context, mixed $value): bool
    {
        $pattern = trim((string) $value);
        if ($pattern === '') {
            return false;
        }
        if (! str_starts_with($pattern, '/')) {
            $pattern = '/^' . preg_quote($pattern, '/') . '$/';
        }
        foreach ($this->getLineItemSkus($context) as $sku) {
            if ($sku !== '' && @preg_match($pattern, $sku) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * At least one line item has SKU whose segment (split by separator) at index is a number between min and max (inclusive).
     * Value: "segment_index,min,max" or "separator,segment_index,min,max" (default separator "-").
     */
    protected function lineItemSkuSegmentBetween(array $context, mixed $value): bool
    {
        $parts = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
        $separator = '-';
        $segmentIndex = 0;
        $min = 0;
        $max = 0;
        if (count($parts) === 3) {
            $segmentIndex = (int) $parts[0];
            $min = (int) $parts[1];
            $max = (int) $parts[2];
        } elseif (count($parts) >= 4) {
            $separator = (string) $parts[0];
            $segmentIndex = (int) $parts[1];
            $min = (int) $parts[2];
            $max = (int) $parts[3];
        } else {
            return false;
        }
        foreach ($this->getLineItemSkus($context) as $sku) {
            if ($sku === '') {
                continue;
            }
            $segments = explode($separator, $sku);
            $seg = $segments[$segmentIndex] ?? null;
            if ($seg === null || $seg === '') {
                continue;
            }
            $num = (int) $seg;
            if ($num >= $min && $num <= $max) {
                return true;
            }
        }
        return false;
    }
}
