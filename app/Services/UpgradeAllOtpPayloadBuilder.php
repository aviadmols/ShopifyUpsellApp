<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Shop;

/**
 * Builds payload for "Upgrade all to subscription (OTP cart)" block.
 * Only enabled when cart has no subscriptions; one click converts all eligible lines to subscription.
 */
class UpgradeAllOtpPayloadBuilder
{
    public function __construct(
        protected ShopifyGraphQLService $shopifyGraphQL,
        protected CartLineUpgradeMatcher $cartLineUpgradeMatcher
    ) {}

    /**
     * Build payload for upgrade-all-OTP block. Returns enabled: false if any line has selling_plan_id.
     * Otherwise returns offer (items, actions, saving) or success state (after upgrade).
     *
     * @param  array<string, mixed>  $context  line_items, subtotal, etc.
     * @return array{enabled: bool, state?: string, items: array, actions: array, actions_undo?: array, headline?: string, subtext?: string, product_list_label?: string, cta_label?: string, success_headline?: string, undo_link_text?: string, saving?: array, frequency?: string, products?: array, ui?: array}
     */
    public function run(Block $block, array $context): array
    {
        $config = $block->config ?? [];
        $lineItems = $context['line_items'] ?? $context['lineItems'] ?? [];
        if (! is_array($lineItems)) {
            $lineItems = [];
        }

        $subtotal = (float) ($context['subtotal'] ?? 0);

        $empty = [
            'enabled' => false,
            'items' => [],
            'plans' => [],
            'actions' => [],
        ];

        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $planId = trim((string) ($line['selling_plan_id'] ?? $line['sellingPlanId'] ?? ''));
            if ($planId !== '') {
                return $empty;
            }
        }

        $items = [];
        $actions = [];
        $totalSaving = 0.0;
        $frequency = '';
        $productsList = [];

        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lineId = $line['id'] ?? null;
            $variantGid = $this->normalizeVariantGid($line['variant_id'] ?? $line['merchandiseId'] ?? $line['merchandise']['id'] ?? '');
            if ($lineId === null || $variantGid === '') {
                continue;
            }

            $plans = $this->shopifyGraphQL->getSellingPlansWithPricingForVariant($block->shop, $variantGid);
            if ($plans === []) {
                continue;
            }

            $first = $plans[0];
            $sellingPlanId = $this->cartLineUpgradeMatcher::sellingPlanToGid($first['id']);
            $name = (string) ($first['name'] ?? 'Subscription');
            if ($frequency === '' && ($first['frequency'] ?? '') !== '') {
                $frequency = (string) $first['frequency'];
            }
            if ($frequency === '') {
                $frequency = $name;
            }

            $quantity = (int) ($line['quantity'] ?? 1);
            $productTitle = (string) ($line['product_title'] ?? $line['productTitle'] ?? $line['title'] ?? 'Item');
            $variantTitle = (string) ($line['variant_title'] ?? $line['variantTitle'] ?? '');
            $price = (float) ($line['price'] ?? 0);
            if ($price <= 0 && isset($line['merchandise']['price']['amount'])) {
                $price = (float) $line['merchandise']['price']['amount'];
            }

            $percentage = $first['percentage'] ?? null;
            $lineSaving = 0.0;
            if ($percentage !== null && $price > 0) {
                $lineSaving = $price * $quantity * ((float) $percentage / 100);
            }
            $totalSaving += $lineSaving;

            $items[] = [
                'line_id' => $lineId,
                'product_title' => $productTitle,
                'variant_title' => $variantTitle,
                'merchandiseId' => $variantGid,
                'selling_plan_id' => $sellingPlanId,
            ];
            $productsList[] = ['product_title' => $productTitle, 'variant_title' => $variantTitle];

            $actions[] = [
                'type' => 'updateCartLine',
                'lineId' => $lineId,
                'merchandiseId' => $variantGid,
                'sellingPlanId' => $sellingPlanId,
            ];
        }

        if ($items === []) {
            return $empty;
        }

        $headline = (string) ($config['headline'] ?? 'UPGRADE TO SUBSCRIPTION AND SAVE');
        $subtext = (string) ($config['subtext'] ?? '');
        $productListLabel = (string) ($config['product_list_label'] ?? 'Deliver every {{frequency}}:');
        $ctaLabel = (string) ($config['cta_label'] ?? 'SUBSCRIBE & SAVE');
        $successHeadline = (string) ($config['success_headline'] ?? 'You saved {{saving.amount}} by upgrading products to a subscription!');
        $undoLinkText = (string) ($config['undo_link_text'] ?? 'Undo savings');
        $ui = is_array($config['ui'] ?? null) ? $config['ui'] : [];

        $savingAmount = round($totalSaving, 2);
        $savingPercent = $subtotal > 0 ? round($totalSaving / $subtotal * 100, 1) : 0;

        $payload = [
            'enabled' => true,
            'state' => 'offer',
            'items' => $items,
            'plans' => [],
            'actions' => $actions,
            'headline' => $headline,
            'subtext' => $subtext,
            'product_list_label' => $productListLabel,
            'cta_label' => $ctaLabel,
            'success_headline' => $successHeadline,
            'undo_link_text' => $undoLinkText,
            'saving' => [
                'amount' => $savingAmount,
                'amount_formatted' => number_format($savingAmount, 2),
                'percent' => $savingPercent,
            ],
            'frequency' => $frequency,
            'products' => $productsList,
            'ui' => $ui,
        ];

        return $payload;
    }

    /**
     * Build success-state payload (after user clicked subscribe): headline_success, actions_undo.
     *
     * @param  array<string, mixed>  $context
     * @return array{enabled: bool, state: string, headline: string, undo_link_text: string, actions_undo: array, saving?: array, ui?: array}
     */
    public function buildSuccessState(Block $block, array $context): array
    {
        $config = $block->config ?? [];
        $lineItems = $context['line_items'] ?? $context['lineItems'] ?? [];
        if (! is_array($lineItems)) {
            $lineItems = [];
        }

        $actionsUndo = [];
        $totalSaving = 0.0;

        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lineId = $line['id'] ?? null;
            $planId = trim((string) ($line['selling_plan_id'] ?? $line['sellingPlanId'] ?? ''));
            if ($lineId === null || $planId === '') {
                continue;
            }
            $actionsUndo[] = [
                'type' => 'updateCartLine',
                'lineId' => $lineId,
                'sellingPlanId' => null,
            ];
        }

        $successHeadline = (string) ($config['success_headline'] ?? 'You saved {{saving.amount}} by upgrading products to a subscription!');
        $undoLinkText = (string) ($config['undo_link_text'] ?? 'Undo savings');
        $ui = is_array($config['ui'] ?? null) ? $config['ui'] : [];

        return [
            'enabled' => true,
            'state' => 'success',
            'headline' => $successHeadline,
            'undo_link_text' => $undoLinkText,
            'actions_undo' => $actionsUndo,
            'saving' => ['amount' => $totalSaving, 'amount_formatted' => number_format($totalSaving, 2), 'percent' => 0],
            'items' => [],
            'plans' => [],
            'actions' => [],
            'ui' => $ui,
        ];
    }

    private function normalizeVariantGid(string $id): string
    {
        return CartLineUpgradeMatcher::variantToGid($id);
    }
}
