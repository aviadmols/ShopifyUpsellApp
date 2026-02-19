<?php

namespace App\Services;

/**
 * Subscribe & Save (cart-wide): show only when cart has zero subscriptions.
 * Builds payload with savings amount, product list, and actions to convert all mappable lines to subscription.
 */
class CartSubscriptionSaveMatcher
{
    /**
     * Run: return payload for subscription_save block.
     *
     * @param  array<string, mixed>  $config  headline, subtext, frequency, cta_label, after_headline, undo_link_text, savings_mappings
     * @param  array<string, mixed>  $context  line_items, subtotal
     * @return array{enabled: bool, mode: string, headline?: string, subtext?: string, saving?: array, product_list?: array, frequency?: string, cta_label?: string, actions?: array, upgraded?: bool, saved_amount?: string, saved_amount_formatted?: string, undo_link_text?: string, upgraded_line_ids?: array}
     */
    public function run(array $config, array $context): array
    {
        $lineItems = $context['line_items'] ?? $context['lineItems'] ?? [];
        if (! is_array($lineItems)) {
            $lineItems = [];
        }
        $subtotal = (float) ($context['subtotal'] ?? 0);
        $mappings = $config['savings_mappings'] ?? [];
        if (! is_array($mappings) || $mappings === []) {
            return $this->emptyPayload($config);
        }

        $variantToMapping = [];
        foreach ($mappings as $m) {
            if (! is_array($m)) {
                continue;
            }
            $vid = $this->normalizeId((string) ($m['variant_id'] ?? ''));
            if ($vid === '') {
                continue;
            }
            $planId = CartLineUpgradeMatcher::sellingPlanToGid((string) ($m['selling_plan_id'] ?? ''));
            if ($planId === '') {
                continue;
            }
            $variantToMapping[$vid] = [
                'selling_plan_id' => $planId,
                'discount_percent' => max(0, min(100, (float) ($m['discount_percent'] ?? 0))),
            ];
        }
        if ($variantToMapping === []) {
            return $this->emptyPayload($config);
        }

        $totalQty = 0;
        foreach ($lineItems as $line) {
            if (is_array($line)) {
                $totalQty += (int) ($line['quantity'] ?? 1);
            }
        }
        if ($totalQty < 1) {
            $totalQty = 1;
        }

        $matched = [];
        $hasAnySubscription = false;
        $allMatchedHaveSubscription = true;

        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lineId = $line['id'] ?? null;
            if ($lineId === null) {
                continue;
            }
            $variantId = (string) ($line['variant_id'] ?? $line['merchandiseId'] ?? '');
            $vidNorm = $this->normalizeId($variantId);
            $mapping = $variantToMapping[$vidNorm] ?? null;
            if ($mapping === null) {
                continue;
            }

            $lineQty = (int) ($line['quantity'] ?? 1);
            $lineTotal = $this->lineTotal($line, $subtotal, $totalQty);
            $lineSaving = $lineTotal * ($mapping['discount_percent'] / 100);
            $hasPlan = trim((string) ($line['selling_plan_id'] ?? $line['sellingPlanId'] ?? '')) !== '';

            if ($hasPlan) {
                $hasAnySubscription = true;
            } else {
                $allMatchedHaveSubscription = false;
            }

            $matched[] = [
                'line_id' => $lineId,
                'variant_id' => $variantId,
                'product_title' => $line['product_title'] ?? $line['productTitle'] ?? $line['title'] ?? 'Item',
                'variant_title' => $line['variant_title'] ?? $line['variantTitle'] ?? '',
                'line_total' => $lineTotal,
                'saving' => $lineSaving,
                'quantity' => $lineQty,
                'selling_plan_id' => $mapping['selling_plan_id'],
                'discount_percent' => $mapping['discount_percent'],
                'has_subscription' => $hasPlan,
            ];
        }

        if ($matched === []) {
            return $this->emptyPayload($config);
        }

        // Only show "Subscribe & Save" when cart has ZERO subscriptions.
        if ($hasAnySubscription) {
            // Cart already has at least one subscription. If all our matched lines are subscription, show "upgraded" state.
            if ($allMatchedHaveSubscription) {
                $totalSaved = 0;
                $upgradedLineIds = [];
                foreach ($matched as $m) {
                    $totalSaved += $m['saving'];
                    $upgradedLineIds[] = $m['line_id'];
                }
                $currency = $context['currency'] ?? 'USD';
                return [
                    'enabled' => false,
                    'mode' => 'subscription_save',
                    'upgraded' => true,
                    'saved_amount' => (string) round($totalSaved, 2),
                    'saved_amount_formatted' => $this->formatMoney($totalSaved, $currency),
                    'headline' => (string) ($config['after_headline'] ?? 'You saved {{saving.amount}} by upgrading products to a subscription!'),
                    'undo_link_text' => (string) ($config['undo_link_text'] ?? 'Undo savings'),
                    'upgraded_line_ids' => $upgradedLineIds,
                    'items' => [],
                    'plans' => [],
                    'actions' => [],
                ];
            }
            return $this->emptyPayload($config);
        }

        $totalSaving = 0;
        $productList = [];
        $actions = [];

        foreach ($matched as $m) {
            $totalSaving += $m['saving'];
            $productList[] = [
                'product_title' => $m['product_title'],
                'variant_title' => $m['variant_title'],
            ];
            $actions[] = [
                'type' => 'updateCartLine',
                'lineId' => $m['line_id'],
                'merchandiseId' => CartLineUpgradeMatcher::variantToGid($m['variant_id']),
                'sellingPlanId' => $m['selling_plan_id'],
            ];
        }

        $currency = $context['currency'] ?? 'USD';
        $items = array_map(static function ($m) {
            return [
                'line_id' => $m['line_id'],
                'product_title' => $m['product_title'],
                'variant_title' => $m['variant_title'],
            ];
        }, $matched);

        return [
            'enabled' => true,
            'mode' => 'subscription_save',
            'headline' => (string) ($config['headline'] ?? 'UPGRADE TO SUBSCRIPTION AND SAVE'),
            'subtext' => (string) ($config['subtext'] ?? ''),
            'saving' => [
                'amount' => (string) round($totalSaving, 2),
                'formatted' => $this->formatMoney($totalSaving, $currency),
            ],
            'product_list' => $productList,
            'frequency' => (string) ($config['frequency'] ?? ''),
            'cta_label' => (string) ($config['cta_label'] ?? 'SUBSCRIBE & SAVE'),
            'items' => $items,
            'plans' => [],
            'actions' => $actions,
        ];
    }

    private function lineTotal(array $line, float $subtotal, int $totalQty): float
    {
        if (isset($line['cost']) && is_numeric($line['cost'])) {
            return (float) $line['cost'];
        }
        if (isset($line['price']) && is_numeric($line['price'])) {
            $qty = (int) ($line['quantity'] ?? 1);
            return (float) $line['price'] * $qty;
        }
        $cost = $line['cost'] ?? null;
        if (is_array($cost) && isset($cost['totalAmount']['amount'])) {
            return (float) $cost['totalAmount']['amount'];
        }
        $qty = (int) ($line['quantity'] ?? 1);
        return $totalQty > 0 ? ($subtotal * $qty / $totalQty) : 0;
    }

    private function formatMoney(float $amount, string $currency): string
    {
        $symbol = $currency === 'ILS' ? '₪' : ($currency === 'EUR' ? '€' : '$');
        return $symbol . number_format(round($amount, 2), 2);
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
     * @param  array<string, mixed>  $config
     * @return array{enabled: false, mode: string, items: array, plans: array, actions: array}
     */
    private function emptyPayload(array $config): array
    {
        return [
            'enabled' => false,
            'mode' => 'subscription_save',
            'items' => [],
            'plans' => [],
            'actions' => [],
        ];
    }
}
