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
            'line_items_has_product_id' => $this->lineItemsHasProductId($context, (int) $value),
            'line_items_has_any_product_id' => $this->lineItemsHasAnyProductId($context, (array) $value),
            'customer_has_tag' => $this->customerHasTag($context, (string) $value),
            'shipping_country_in' => $this->shippingCountryIn($context, (array) $value),
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

    protected function lineItemsHasProductId(array $context, int $productId): bool
    {
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        foreach ($lines as $line) {
            $pid = $line['product_id'] ?? $line['productId'] ?? $line['id'] ?? null;
            if ($pid && (int) $pid === $productId) {
                return true;
            }
        }
        return false;
    }

    protected function lineItemsHasAnyProductId(array $context, array $productIds): bool
    {
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        $ids = array_map('intval', $productIds);
        foreach ($lines as $line) {
            $pid = $line['product_id'] ?? $line['productId'] ?? $line['id'] ?? null;
            if ($pid && in_array((int) $pid, $ids, true)) {
                return true;
            }
        }
        return false;
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
}
