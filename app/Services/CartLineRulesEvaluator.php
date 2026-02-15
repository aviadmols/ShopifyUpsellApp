<?php

namespace App\Services;

use App\Models\CheckoutExperience;

/**
 * Evaluate cart line rules for quantity and subscription upgrade visibility.
 */
class CartLineRulesEvaluator
{
    /**
     * Normalize product ID to GID for consistent comparison.
     */
    public static function normalizeProductId(?string $id): string
    {
        if ($id === null || trim($id) === '') {
            return '';
        }
        $id = trim($id);
        if (str_starts_with($id, 'gid://shopify/Product/')) {
            preg_match('/\d+/', $id, $m);
            return $m ? 'gid://shopify/Product/'.$m[0] : $id;
        }
        $numeric = preg_replace('/\D/', '', $id);
        return $numeric !== '' ? 'gid://shopify/Product/'.$numeric : $id;
    }

    /**
     * Normalize collection ID to GID for consistent comparison.
     */
    public static function normalizeCollectionId(?string $id): string
    {
        if ($id === null || trim($id) === '') {
            return '';
        }
        $id = trim($id);
        if (str_starts_with($id, 'gid://shopify/Collection/')) {
            preg_match('/\d+/', $id, $m);
            return $m ? 'gid://shopify/Collection/'.$m[0] : $id;
        }
        $numeric = preg_replace('/\D/', '', $id);
        return $numeric !== '' ? 'gid://shopify/Collection/'.$numeric : $id;
    }

    /**
     * Normalize list of product IDs to GIDs.
     *
     * @param  array<int, string>|null  $ids
     * @return array<int, string>
     */
    public static function normalizeProductIdList(?array $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $n = self::normalizeProductId(is_string($id) ? $id : (string) $id);
            if ($n !== '') {
                $out[$n] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Normalize list of collection IDs to GIDs.
     *
     * @param  array<int, string>|null  $ids
     * @return array<int, string>
     */
    public static function normalizeCollectionIdList(?array $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $n = self::normalizeCollectionId(is_string($id) ? $id : (string) $id);
            if ($n !== '') {
                $out[$n] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Check if line matches include rules (product, collection, tags, vendor, product type).
     */
    public static function lineMatchesInclude(
        string $productIdGid,
        array $productMetadata,
        CheckoutExperience $experience,
        string $prefix
    ): bool {
        $includeProductIds = self::normalizeProductIdList($experience->getAttribute($prefix.'_include_product_ids'));
        $includeCollectionIds = self::normalizeCollectionIdList($experience->getAttribute($prefix.'_include_collection_ids'));
        $includeTags = array_filter(array_map('strval', (array) ($experience->getAttribute($prefix.'_include_tags') ?? [])));
        $includeVendors = array_filter(array_map('strval', (array) ($experience->getAttribute($prefix.'_include_vendors') ?? [])));
        $includeTypes = array_filter(array_map('strval', (array) ($experience->getAttribute($prefix.'_include_product_types') ?? [])));

        $productIdNorm = self::normalizeProductId($productIdGid);
        $collections = array_map([self::class, 'normalizeCollectionId'], $productMetadata['collection_ids'] ?? []);
        $tags = array_map('strval', $productMetadata['tags'] ?? []);
        $vendor = trim((string) ($productMetadata['vendor'] ?? ''));
        $productType = trim((string) ($productMetadata['product_type'] ?? ''));

        $hasIncludeRule = $includeProductIds !== [] || $includeCollectionIds !== [] || $includeTags !== []
            || $includeVendors !== [] || $includeTypes !== [];

        if (! $hasIncludeRule) {
            return true;
        }

        if ($includeProductIds !== [] && in_array($productIdNorm, $includeProductIds, true)) {
            return true;
        }
        foreach ($collections as $c) {
            if ($includeCollectionIds !== [] && in_array($c, $includeCollectionIds, true)) {
                return true;
            }
        }
        foreach ($tags as $tag) {
            if ($includeTags !== [] && in_array($tag, $includeTags, true)) {
                return true;
            }
        }
        if ($includeVendors !== [] && $vendor !== '' && in_array($vendor, $includeVendors, true)) {
            return true;
        }
        if ($includeTypes !== [] && $productType !== '' && in_array($productType, $includeTypes, true)) {
            return true;
        }

        return false;
    }

    /**
     * Check if line matches exclude rules.
     */
    public static function lineMatchesExclude(
        string $productIdGid,
        array $productMetadata,
        CheckoutExperience $experience,
        string $prefix
    ): bool {
        $excludeProductIds = self::normalizeProductIdList($experience->getAttribute($prefix.'_exclude_product_ids'));
        $excludeCollectionIds = self::normalizeCollectionIdList($experience->getAttribute($prefix.'_exclude_collection_ids'));
        $excludeTags = array_filter(array_map('strval', (array) ($experience->getAttribute($prefix.'_exclude_tags') ?? [])));
        $excludeVendors = array_filter(array_map('strval', (array) ($experience->getAttribute($prefix.'_exclude_vendors') ?? [])));
        $excludeTypes = array_filter(array_map('strval', (array) ($experience->getAttribute($prefix.'_exclude_product_types') ?? [])));

        $productIdNorm = self::normalizeProductId($productIdGid);
        $collections = array_map([self::class, 'normalizeCollectionId'], $productMetadata['collection_ids'] ?? []);
        $tags = array_map('strval', $productMetadata['tags'] ?? []);
        $vendor = trim((string) ($productMetadata['vendor'] ?? ''));
        $productType = trim((string) ($productMetadata['product_type'] ?? ''));

        if ($excludeProductIds !== [] && in_array($productIdNorm, $excludeProductIds, true)) {
            return true;
        }
        foreach ($collections as $c) {
            if ($excludeCollectionIds !== [] && in_array($c, $excludeCollectionIds, true)) {
                return true;
            }
        }
        foreach ($tags as $tag) {
            if ($excludeTags !== [] && in_array($tag, $excludeTags, true)) {
                return true;
            }
        }
        if ($excludeVendors !== [] && $vendor !== '' && in_array($vendor, $excludeVendors, true)) {
            return true;
        }
        if ($excludeTypes !== [] && $productType !== '' && in_array($productType, $excludeTypes, true)) {
            return true;
        }

        return false;
    }

    /**
     * Check cart-level conditions (subtotal, items count).
     */
    public static function cartConditionsPass(
        ?float $cartSubtotal,
        ?int $cartItemsCount,
        CheckoutExperience $experience,
        string $prefix
    ): bool {
        $minSubtotal = $experience->getAttribute($prefix.'_min_subtotal');
        $maxSubtotal = $experience->getAttribute($prefix.'_max_subtotal');
        $minCartItems = $experience->getAttribute($prefix.'_min_cart_items');
        $maxCartItems = $experience->getAttribute($prefix.'_max_cart_items');

        if ($minSubtotal !== null && $minSubtotal !== '') {
            $min = (float) $minSubtotal;
            if ($cartSubtotal !== null && $cartSubtotal < $min) {
                return false;
            }
        }
        if ($maxSubtotal !== null && $maxSubtotal !== '') {
            $max = (float) $maxSubtotal;
            if ($cartSubtotal !== null && $cartSubtotal > $max) {
                return false;
            }
        }
        if ($minCartItems !== null && $minCartItems !== '') {
            $min = (int) $minCartItems;
            if ($cartItemsCount !== null && $cartItemsCount < $min) {
                return false;
            }
        }
        if ($maxCartItems !== null && $maxCartItems !== '') {
            $max = (int) $maxCartItems;
            if ($cartItemsCount !== null && $cartItemsCount > $max) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check subscription state filter: any, subscription (line has selling plan), one_time (line has no selling plan).
     */
    public static function subscriptionStatePass(bool $lineHasSellingPlan, CheckoutExperience $experience, string $prefix): bool
    {
        $require = $experience->getAttribute($prefix.'_require_subscription_state');
        if ($require === null || $require === '' || $require === 'any') {
            return true;
        }
        if ($require === 'subscription') {
            return $lineHasSellingPlan;
        }
        if ($require === 'one_time') {
            return ! $lineHasSellingPlan;
        }
        return true;
    }

    /**
     * Evaluate quantity rules for one line.
     */
    public static function showQuantity(
        CheckoutExperience $experience,
        string $productIdGid,
        array $productMetadata,
        ?float $cartSubtotal,
        ?int $cartItemsCount,
        bool $lineHasSellingPlan
    ): bool {
        if (! (bool) $experience->quantity_in_cart_enabled) {
            return false;
        }
        $mode = $experience->quantity_rule_mode ?? 'all';
        if ($mode === 'all') {
            return self::cartConditionsPass($cartSubtotal, $cartItemsCount, $experience, 'quantity')
                && self::subscriptionStatePass($lineHasSellingPlan, $experience, 'quantity');
        }
        if (! self::cartConditionsPass($cartSubtotal, $cartItemsCount, $experience, 'quantity')) {
            return false;
        }
        if (! self::subscriptionStatePass($lineHasSellingPlan, $experience, 'quantity')) {
            return false;
        }
        $matchesInclude = self::lineMatchesInclude($productIdGid, $productMetadata, $experience, 'quantity');
        $matchesExclude = self::lineMatchesExclude($productIdGid, $productMetadata, $experience, 'quantity');
        if ($mode === 'include_only') {
            return $matchesInclude;
        }
        if ($mode === 'exclude_only') {
            return ! $matchesExclude;
        }
        if ($mode === 'include_exclude') {
            return $matchesInclude && ! $matchesExclude;
        }
        return true;
    }

    /**
     * Evaluate subscription upgrade rules for one line.
     */
    public static function showSubscription(
        CheckoutExperience $experience,
        string $productIdGid,
        array $productMetadata,
        ?float $cartSubtotal,
        ?int $cartItemsCount,
        bool $lineHasSellingPlan
    ): bool {
        if (! (bool) $experience->subscription_upgrade_enabled) {
            return false;
        }
        // Upgrade is for one-time lines only (to show "upgrade to subscription"); rule can further restrict.
        if ($lineHasSellingPlan) {
            return false;
        }
        $mode = $experience->subscription_rule_mode ?? 'all';
        if ($mode === 'all') {
            return self::cartConditionsPass($cartSubtotal, $cartItemsCount, $experience, 'subscription')
                && self::subscriptionStatePass($lineHasSellingPlan, $experience, 'subscription');
        }
        if (! self::cartConditionsPass($cartSubtotal, $cartItemsCount, $experience, 'subscription')) {
            return false;
        }
        if (! self::subscriptionStatePass($lineHasSellingPlan, $experience, 'subscription')) {
            return false;
        }
        $matchesInclude = self::lineMatchesInclude($productIdGid, $productMetadata, $experience, 'subscription');
        $matchesExclude = self::lineMatchesExclude($productIdGid, $productMetadata, $experience, 'subscription');
        if ($mode === 'include_only') {
            return $matchesInclude;
        }
        if ($mode === 'exclude_only') {
            return ! $matchesExclude;
        }
        if ($mode === 'include_exclude') {
            return $matchesInclude && ! $matchesExclude;
        }
        return true;
    }
}
