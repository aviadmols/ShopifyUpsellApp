<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

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
    /** Line item property key set by the extension when the user upgrades via the cart-wide module. */
    private const LINE_ATTR_UPGRADED_BY_APP = '_zyg_upgraded_subscription';

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
            $matched = $sku !== '' && @preg_match($regex, $sku) === 1;
            if (! $matched) {
                if (preg_last_error() !== PREG_NO_ERROR) {
                    Log::channel('checkout_extension')->warning('cart_line_match_sku_regex_error', [
                        'sku_regex' => $regex,
                        'preg_error' => preg_last_error(),
                    ]);
                }
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

        $lineQty = (int) ($line['quantity'] ?? 1);
        if (isset($match['quantity_min']) && $lineQty < (int) $match['quantity_min']) {
            return false;
        }
        if (isset($match['quantity_max']) && $lineQty > (int) $match['quantity_max']) {
            return false;
        }

        $lineHasSubscription = $this->lineHasSellingPlan($line);
        $lineSellingPlanId = trim((string) ($line['selling_plan_id'] ?? $line['sellingPlanId'] ?? ''));

        $subscriptionRule = (string) ($match['subscription'] ?? 'any');
        if ($subscriptionRule === 'must_be_subscription' && ! $lineHasSubscription) {
            return false;
        }
        if ($subscriptionRule === 'must_be_one_time' && $lineHasSubscription) {
            return false;
        }

        if (isset($match['selling_plan_id']) && (string) $match['selling_plan_id'] !== '') {
            $matchPlanNorm = $this->normalizeId((string) $match['selling_plan_id']);
            $linePlanNorm = $this->normalizeId($lineSellingPlanId);
            if ($matchPlanNorm === '' || $linePlanNorm !== $matchPlanNorm) {
                return false;
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
            Log::channel('checkout_extension')->info('cart_wide_empty_reason', ['reason' => 'subtotal_min', 'subtotal' => $subtotal, 'min' => $subtotalMin]);
            return $this->emptyPayload($config);
        }
        if ($itemsCountMin !== null && count($lineItems) < $itemsCountMin) {
            Log::channel('checkout_extension')->info('cart_wide_empty_reason', ['reason' => 'items_count_min', 'count' => count($lineItems), 'min' => $itemsCountMin]);
            return $this->emptyPayload($config);
        }

        $cartWideEnabled = ! empty($config['cart_wide_enabled']);
        $cartWideMappings = $this->ensureArrayFromConfig($config['cart_wide_mappings'] ?? []);
        if ($cartWideEnabled && $cartWideMappings !== []) {
            return $this->runCartWide($config, $context, $lineItems, $subtotal);
        }

        if ($cartWideEnabled && $cartWideMappings === []) {
            Log::channel('checkout_extension')->info('cart_wide_empty_reason', ['reason' => 'cart_wide_mappings_empty_after_decode', 'line_items_count' => count($lineItems)]);
        } elseif (! $cartWideEnabled) {
            Log::channel('checkout_extension')->info('cart_wide_empty_reason', ['reason' => 'cart_wide_disabled']);
        }

        $mappings = $config['upgrade_mappings'] ?? [];
        if (! is_array($mappings) || $mappings === []) {
            Log::channel('checkout_extension')->info('cart_wide_empty_reason', ['reason' => 'no_upgrade_mappings', 'cart_wide_enabled' => $cartWideEnabled]);
            return $this->emptyPayload($config);
        }

        $items = [];
        $actions = [];
        /** @var array{matched_quantity: string, matched_variant_id: string, matched_product_id: string, matched_is_subscription: string, matched_selling_plan_id: string, matched_product_title: string, matched_variant_title: string}|null $matched */
        $matched = null;
        /** @var array{headline: string, description: string, cta_label: string}|null $firstOverrides */
        $firstOverrides = null;
        /** @var array<string, mixed>|null $firstMatchedMapping */
        $firstMatchedMapping = null;

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

                $lineQty = (int) ($line['quantity'] ?? 1);
                $lineSellingPlanId = trim((string) ($line['selling_plan_id'] ?? $line['sellingPlanId'] ?? ''));
                $lineVariantId = (string) ($line['variant_id'] ?? $line['merchandiseId'] ?? '');
                $lineProductId = (string) ($line['product_id'] ?? $line['productId'] ?? '');

                $items[] = [
                    'line_id' => $lineId,
                    'product_title' => $line['product_title'] ?? $line['productTitle'] ?? $line['title'] ?? 'Item',
                    'variant_title' => $line['variant_title'] ?? $line['variantTitle'] ?? null,
                ];

                // Capture first-match context for template placeholders (matched_quantity, matched_variant_id, etc.).
                if ($matched === null) {
                    $matched = [
                        'matched_quantity' => (string) $lineQty,
                        'matched_variant_id' => $lineVariantId,
                        'matched_product_id' => $lineProductId,
                        'matched_is_subscription' => $lineSellingPlanId !== '' ? '1' : '0',
                        'matched_selling_plan_id' => $lineSellingPlanId,
                        'matched_product_title' => (string) ($line['product_title'] ?? $line['productTitle'] ?? $line['title'] ?? ''),
                        'matched_variant_title' => (string) ($line['variant_title'] ?? $line['variantTitle'] ?? ''),
                    ];
                }

                // Allow selling plan on mapping root, or via first mapping plan entry.
                $sellingPlanId = '';
                if ($actionType === 'subscription') {
                    $sellingPlanId = (string) ($mapping['selling_plan_id'] ?? '');
                    if ($sellingPlanId === '' && is_array($plans) && isset($plans[0]['selling_plan_id'])) {
                        $sellingPlanId = (string) $plans[0]['selling_plan_id'];
                    }
                    $sellingPlanId = self::sellingPlanToGid($sellingPlanId);
                }

                $lineVariantNorm = $this->normalizeId($lineVariantId);
                $targetVariantNorm = $this->normalizeId($targetVariantId);
                $sameVariant = $lineVariantNorm !== '' && $targetVariantNorm !== '' && $lineVariantNorm === $targetVariantNorm;
                $upgradeToSubscriptionOnly = $sameVariant && $actionType === 'subscription' && $sellingPlanId !== '';

                // When upgrading to subscription (same variant, add selling plan): use updateCartLine so the line updates in place without cart jumps. Frontend will resolve current line id at click time (IDs are not stable).
                if ($upgradeToSubscriptionOnly) {
                    $actions[] = [
                        'type' => 'updateCartLine',
                        'lineId' => $lineId,
                        'merchandiseId' => $targetVariantId,
                        'sellingPlanId' => $sellingPlanId,
                    ];
                } else {
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
                }

                // Capture first-match text/image overrides and mapping for plans dropdown.
                if ($firstOverrides === null) {
                    $firstOverrides = [
                        'headline' => (string) ($mapping['headline'] ?? ''),
                        'description' => (string) ($mapping['description'] ?? ''),
                        'cta_label' => (string) ($mapping['cta_label'] ?? ''),
                    ];
                    if (trim((string) ($mapping['display_mode'] ?? '')) === 'image') {
                        $firstOverrides['display_mode'] = 'image';
                        $imgUrl = trim((string) ($mapping['image_url'] ?? ''));
                        if ($imgUrl !== '') {
                            $firstOverrides['image_url'] = $imgUrl;
                        }
                    }
                    $firstMatchedMapping = $mapping;
                }

                break;
            }
        }

        // Use first-matched mapping's plans for dropdown; fallback to card-level plans (backward compat).
        $plans = null;
        if ($firstMatchedMapping !== null && isset($firstMatchedMapping['plans']) && is_array($firstMatchedMapping['plans']) && $firstMatchedMapping['plans'] !== []) {
            $plans = $firstMatchedMapping['plans'];
        }
        if ($plans === null) {
            $plans = $config['plans'] ?? [];
        }
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
        if ($matched !== null) {
            $out['matched'] = $matched;
        }
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
        $firstMappingUi = [];
        if (is_array($firstOverrides) && isset($firstOverrides['display_mode']) && (string) $firstOverrides['display_mode'] === 'image') {
            $firstMappingUi['display_mode'] = 'image';
            $firstMappingUi['image_url'] = (string) ($firstOverrides['image_url'] ?? '');
        }
        if ($firstMatchedMapping !== null && isset($firstMatchedMapping['ui']) && is_array($firstMatchedMapping['ui'])) {
            $firstMappingUi = array_merge($firstMappingUi, $firstMatchedMapping['ui']);
        }
        if ($firstMappingUi !== []) {
            $out['first_mapping_ui'] = $firstMappingUi;
        }

        return $out;
    }

    /**
     * Cart-wide subscription (OTP only):
     * 1) Run when zone loads: require that every line item has selling_plan_id null (no product with a subscription in cart).
     * 2) Match cart variants to cart_wide_mappings; collect matching one-time lines.
     * 3) If any cart line has a plan → do not show offer (success or empty only).
     * 4) Otherwise show offer: total discount for all matched lines, one "Upgrade" click turns them all to subscription.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function runCartWide(array $config, array $context, array $lineItems, float $subtotal): array
    {
        $rawMappings = $this->ensureArrayFromConfig($config['cart_wide_mappings'] ?? []);
        $variantToMapping = [];
        $defaultFrequency = (string) ($config['cart_wide_frequency'] ?? '');
        foreach ($rawMappings as $m) {
            if (! is_array($m)) {
                continue;
            }
            $variantId = $m['variant_id'] ?? $m['variantId'] ?? '';
            if ((string) $variantId === '') {
                continue;
            }
            $norm = $this->normalizeId((string) $variantId);
            if ($norm === '') {
                continue;
            }
            $sellingPlanId = self::sellingPlanToGid((string) ($m['selling_plan_id'] ?? $m['sellingPlanId'] ?? ''));
            $discountPercent = isset($m['discount_percent']) ? (float) $m['discount_percent'] : 0;
            $variantToMapping[$norm] = [
                'selling_plan_id' => $sellingPlanId,
                'discount_percent' => $discountPercent,
                'variant_id_gid' => self::variantToGid((string) $variantId),
                'frequency' => (string) ($m['frequency'] ?? $defaultFrequency),
            ];
        }
        if ($variantToMapping === []) {
            Log::channel('checkout_extension')->info('cart_wide_empty_reason', ['reason' => 'variant_to_mapping_empty', 'raw_mappings_count' => count($rawMappings)]);
            return $this->emptyPayload($config);
        }

        $requiredAttrs = $this->ensureArrayFromConfig($config['cart_wide_required_attributes'] ?? []);
        if ($requiredAttrs !== [] && ! $this->cartWideCheckoutAttributesMatch($context, $requiredAttrs)) {
            Log::channel('checkout_extension')->info('cart_wide_empty_reason', ['reason' => 'required_attributes_no_match']);
            return $this->emptyPayload($config);
        }

        // 1) Check: show offer only when ALL line items have selling_plan_id null (entire cart is one-time).
        $anyCartLineHasPlan = false;
        foreach ($lineItems as $line) {
            if (is_array($line) && $this->lineHasSellingPlan($line)) {
                $anyCartLineHasPlan = true;
                break;
            }
        }

        $hasAnySubscription = false;
        $subscriptionLines = [];
        $oneTimeMatchingLines = [];
        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lineId = $line['id'] ?? null;
            if ($lineId === null) {
                continue;
            }
            $lineVariantId = (string) ($line['variant_id'] ?? $line['merchandiseId'] ?? '');
            $lineNorm = $this->normalizeId($lineVariantId);
            $mapping = $variantToMapping[$lineNorm] ?? null;
            if ($mapping === null) {
                continue;
            }
            $hasPlan = $this->lineHasSellingPlan($line);
            if ($hasPlan) {
                $hasAnySubscription = true;
                $subscriptionLines[] = [
                    'line' => $line,
                    'mapping' => $mapping,
                ];
            } else {
                $oneTimeMatchingLines[] = [
                    'line' => $line,
                    'mapping' => $mapping,
                ];
            }
        }

        if ($hasAnySubscription) {
            $hasUpgradedByUs = false;
            foreach ($subscriptionLines as $entry) {
                if ($this->lineHasUpgradedByUs($entry['line'])) {
                    $hasUpgradedByUs = true;
                    break;
                }
            }
            if (! $hasUpgradedByUs) {
                return $this->emptyPayload($config);
            }
            return $this->cartWideSuccessPayload($config, $subscriptionLines, $subtotal, $lineItems);
        }

        // Do not show upgrade offer if any line in cart has a selling plan (cart must be fully OTP).
        if ($anyCartLineHasPlan || $oneTimeMatchingLines === []) {
            $lineVariantIds = array_map(function ($line) {
                return $this->normalizeId((string) ($line['variant_id'] ?? $line['merchandiseId'] ?? ''));
            }, array_values(array_filter($lineItems, 'is_array')));
            Log::channel('checkout_extension')->info('cart_wide_empty_reason', [
                'reason' => 'no_offer_show',
                'any_cart_line_has_plan' => $anyCartLineHasPlan,
                'one_time_matching_count' => count($oneTimeMatchingLines),
                'mapping_variant_ids' => array_keys($variantToMapping),
                'line_variant_ids_normalized' => $lineVariantIds,
            ]);
            return $this->emptyPayload($config);
        }

        return $this->cartWideOfferPayload($config, $oneTimeMatchingLines, $subtotal, $lineItems);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, array{line: array, mapping: array}>  $subscriptionLines
     * @param  array<int, array<string, mixed>>  $allLines
     * @return array{enabled: bool, items: array, plans: array, actions: array, mode: string, headline?: string, undo_label?: string}
     */
    private function cartWideSuccessPayload(array $config, array $subscriptionLines, float $subtotal, array $allLines): array
    {
        $savingAmount = 0.0;
        $actions = [];
        $items = [];
        $defaultFrequency = (string) ($config['cart_wide_frequency'] ?? '');
        foreach ($subscriptionLines as $entry) {
            $line = $entry['line'];
            $mapping = $entry['mapping'];
            $lineTotal = $this->lineTotalFromLine($line, $subtotal, $allLines);
            $discountPercent = (float) ($mapping['discount_percent'] ?? 0);
            // Line total here is post-discount (subscription price); saving = lineTotal * discountPercent / (100 - discountPercent)
            $denom = 100 - $discountPercent;
            $lineSaving = $denom > 0 ? $lineTotal * $discountPercent / $denom : 0.0;
            $savingAmount += $lineSaving;
            $actions[] = [
                'type' => 'updateCartLine',
                'lineId' => $line['id'],
                'merchandiseId' => $mapping['variant_id_gid'] ?? self::variantToGid((string) ($line['variant_id'] ?? $line['merchandiseId'] ?? '')),
                'sellingPlanId' => null,
            ];
            $items[] = [
                'line_id' => $line['id'],
                'product_title' => $line['product_title'] ?? $line['productTitle'] ?? $line['title'] ?? 'Item',
                'variant_title' => $line['variant_title'] ?? $line['variantTitle'] ?? null,
                'frequency' => (string) ($mapping['frequency'] ?? $defaultFrequency),
                'line_saving' => $lineSaving,
                'line_saving_formatted' => $this->formatMoney($lineSaving),
            ];
        }
        $headline = (string) ($config['cart_wide_success_headline'] ?? 'You saved {{saving.amount}} by upgrading products to a subscription!');
        $undoLabel = (string) ($config['cart_wide_undo_label'] ?? 'Undo savings');
        $undoStyle = (string) ($config['cart_wide_undo_style'] ?? 'plain');
        if (! in_array($undoStyle, ['plain', 'secondary', 'primary'], true)) {
            $undoStyle = 'plain';
        }
        return [
            'enabled' => true,
            'items' => $items,
            'plans' => [],
            'actions' => $actions,
            'mode' => 'cart_wide_success',
            'headline' => $headline,
            'undo_label' => $undoLabel,
            'undo_style' => $undoStyle,
            'saving' => ['amount' => $savingAmount, 'amount_formatted' => $this->formatMoney($savingAmount)],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, array{line: array, mapping: array}>  $matchingLines
     * @param  array<int, array<string, mixed>>  $allLines
     * @return array{enabled: bool, items: array, plans: array, actions: array, mode: string, headline?: string, subtext?: string, cta_label?: string, frequency?: string, saving?: array}
     */
    private function cartWideOfferPayload(array $config, array $matchingLines, float $subtotal, array $allLines): array
    {
        $savingAmount = 0.0;
        $totalQuantity = 0;
        foreach ($allLines as $line) {
            if (is_array($line) && isset($line['quantity'])) {
                $totalQuantity += (int) $line['quantity'];
            }
        }
        $items = [];
        $actions = [];
        $defaultFrequency = (string) ($config['cart_wide_frequency'] ?? '');
        $frequency = $defaultFrequency;
        foreach ($matchingLines as $entry) {
            $line = $entry['line'];
            $mapping = $entry['mapping'];
            $lineTotal = $this->lineTotalFromLine($line, $subtotal, $allLines);
            $discountPercent = (float) ($mapping['discount_percent'] ?? 0);
            $lineSaving = $lineTotal * ($discountPercent / 100);
            $savingAmount += $lineSaving;
            if ($frequency === '' && isset($mapping['frequency'])) {
                $frequency = (string) $mapping['frequency'];
            }
            $sellingPlanId = (string) ($mapping['selling_plan_id'] ?? '');
            $variantGid = (string) ($mapping['variant_id_gid'] ?? self::variantToGid((string) ($line['variant_id'] ?? $line['merchandiseId'] ?? '')));
            $itemFrequency = (string) ($mapping['frequency'] ?? $defaultFrequency);
            $items[] = [
                'line_id' => $line['id'],
                'product_title' => $line['product_title'] ?? $line['productTitle'] ?? $line['title'] ?? 'Item',
                'variant_title' => $line['variant_title'] ?? $line['variantTitle'] ?? null,
                'frequency' => $itemFrequency,
                'line_saving' => $lineSaving,
                'line_saving_formatted' => $this->formatMoney($lineSaving),
            ];
            if ($sellingPlanId !== '') {
                $actions[] = [
                    'type' => 'updateCartLine',
                    'lineId' => $line['id'],
                    'merchandiseId' => $variantGid,
                    'sellingPlanId' => $sellingPlanId,
                ];
            }
        }
        $headline = (string) ($config['cart_wide_headline'] ?? 'UPGRADE TO SUBSCRIPTION AND SAVE');
        $subtext = (string) ($config['cart_wide_subtext'] ?? 'Upgrade your items to subscription and save up to {{saving.amount}} today!');
        $ctaLabel = (string) ($config['cart_wide_cta_label'] ?? 'SUBSCRIBE & SAVE');
        return [
            'enabled' => true,
            'items' => $items,
            'plans' => [],
            'actions' => $actions,
            'mode' => 'cart_wide_offer',
            'headline' => $headline,
            'subtext' => $subtext,
            'cta_label' => $ctaLabel,
            'frequency' => $frequency,
            'saving' => ['amount' => $savingAmount, 'amount_formatted' => $this->formatMoney($savingAmount)],
        ];
    }

    /**
     * Get line total for one line: proportional share of cart subtotal (product prices only, no tax).
     * Always uses context subtotal (from useSubtotalAmount in the extension) so offer and success show the same saving.
     *
     * @param  array<string, mixed>  $line
     * @param  array<int, array<string, mixed>>  $allLines
     */
    private function lineTotalFromLine(array $line, float $subtotal, array $allLines): float
    {
        $qty = (int) ($line['quantity'] ?? 1);
        if ($qty < 1) {
            return 0.0;
        }
        $totalQty = 0;
        foreach ($allLines as $l) {
            if (is_array($l) && isset($l['quantity'])) {
                $totalQty += (int) $l['quantity'];
            }
        }
        if ($totalQty <= 0 || $subtotal <= 0) {
            return 0.0;
        }
        return $subtotal * ($qty / $totalQty);
    }

    private function formatMoney(float $amount): string
    {
        return number_format((float) round($amount, 2), 2);
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
     * Ensure config value is an array (may be stored as JSON string in DB).
     *
     * @param  mixed  $value
     * @return array<int, mixed>
     */
    private function ensureArrayFromConfig(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Check that checkout (order) attributes match the required key/value(s). Required list: [ ['key' => 'x', 'value' => 'a,b'] ].
     * Value can be comma-separated = order attribute must be one of these. Empty value = key must exist and be non-empty.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, string>>  $requiredAttrs
     */
    private function cartWideCheckoutAttributesMatch(array $context, array $requiredAttrs): bool
    {
        $attrs = $context['checkout_attributes'] ?? [];
        if (! is_array($attrs)) {
            $attrs = [];
        }
        foreach ($requiredAttrs as $req) {
            if (! is_array($req)) {
                continue;
            }
            $key = trim((string) ($req['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $orderValue = isset($attrs[$key]) ? trim((string) $attrs[$key]) : '';
            $allowed = trim((string) ($req['value'] ?? ''));
            if ($allowed === '') {
                if ($orderValue === '') {
                    return false;
                }
                continue;
            }
            $allowedList = array_map('trim', explode(',', $allowed));
            $allowedList = array_filter($allowedList, fn ($v) => $v !== '');
            if ($allowedList === []) {
                if ($orderValue === '') {
                    return false;
                }
                continue;
            }
            if (! in_array($orderValue, $allowedList, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * True when the line has the property set by the extension after upgrading via the cart-wide module.
     *
     * @param  array<string, mixed>  $line
     */
    private function lineHasUpgradedByUs(array $line): bool
    {
        $props = $line['properties'] ?? [];
        if (! is_array($props)) {
            return false;
        }
        $val = $props[self::LINE_ATTR_UPGRADED_BY_APP] ?? null;
        if ($val === null && isset($line['attributes']) && is_array($line['attributes'])) {
            foreach ($line['attributes'] as $attr) {
                if (is_array($attr) && ($attr['key'] ?? '') === self::LINE_ATTR_UPGRADED_BY_APP) {
                    $val = $attr['value'] ?? null;
                    break;
                }
            }
        }
        return $val !== null && trim((string) $val) !== '';
    }

    /**
     * True only when the line has a real selling plan (not null, not empty, not the literal string "null").
     * Ensures lines with selling_plan_id: null or missing are treated as one-time.
     *
     * @param  array<string, mixed>  $line
     */
    private function lineHasSellingPlan(array $line): bool
    {
        $raw = $line['selling_plan_id'] ?? $line['sellingPlanId'] ?? null;
        if ($raw === null) {
            return false;
        }
        $s = trim((string) $raw);
        if ($s === '' || strtolower($s) === 'null') {
            return false;
        }
        return true;
    }

    /**
     * Extract line item properties/attributes to a flat key=>value map. Supports both associative arrays
     * and list-of-kv (Checkout UI: [{key, value}]). Keeps keys even when value is empty (for property_exists checks).
     *
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
            $isAssoc = array_keys($candidate) !== range(0, count($candidate) - 1);
            if ($isAssoc) {
                foreach ($candidate as $k => $v) {
                    $key = trim((string) $k);
                    $value = is_scalar($v) ? trim((string) $v) : '';
                    if ($key !== '') {
                        $out[$key] = $value;
                    }
                }
            } else {
                foreach ($candidate as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $key = trim((string) ($row['key'] ?? $row['name'] ?? ''));
                    $value = trim((string) ($row['value'] ?? ''));
                    if ($key !== '') {
                        $out[$key] = $value;
                    }
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
