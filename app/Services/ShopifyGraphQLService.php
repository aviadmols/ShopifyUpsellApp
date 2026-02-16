<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shopify Admin GraphQL API client. Uses store access token for merchant API calls.
 */
class ShopifyGraphQLService
{
    protected string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('shopify.api_version', '2024-01');
    }

    /**
     * Build GraphQL endpoint URL for a shop.
     */
    public function graphqlUrl(string $shopDomain): string
    {
        return "https://{$shopDomain}/admin/api/{$this->apiVersion}/graphql.json";
    }

    /**
     * Execute a GraphQL query or mutation for a shop.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function request(Shop $shop, string $query, array $variables = []): array
    {
        $url = $this->graphqlUrl($shop->shop_domain);
        $queryPreview = str_replace(["\r", "\n"], ' ', trim(substr($query, 0, 200))) . (strlen($query) > 200 ? '…' : '');

        Log::channel('shopify_api')->info('Shopify API request', [
            'shop_id' => $shop->id,
            'shop_domain' => $shop->shop_domain,
            'url' => $url,
            'query_preview' => $queryPreview,
            'variables_keys' => array_keys($variables),
        ]);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $status = $response->status();
        $data = $response->json();
        $errors = $data['errors'] ?? null;

        if ($errors) {
            Log::channel('shopify_api')->warning('Shopify API GraphQL errors', [
                'shop_id' => $shop->id,
                'errors' => $errors,
            ]);
            throw new \RuntimeException('GraphQL errors: ' . json_encode($errors));
        }

        if (! $response->successful()) {
            Log::channel('shopify_api')->warning('Shopify API HTTP error', [
                'shop_id' => $shop->id,
                'status' => $status,
                'body_preview' => substr((string) $response->body(), 0, 500),
            ]);
            $response->throw();
        }

        return $data['data'] ?? [];
    }

    /**
     * Fetch product variant by Shopify GID.
     *
     * @return array<string, mixed>|null
     */
    public function getProductVariant(Shop $shop, string $gid): ?array
    {
        $query = <<<'GRAPHQL'
            query getProductVariant($id: ID!) {
                productVariant(id: $id) {
                    id
                    title
                    price
                    media(first: 1) {
                        nodes {
                            ... on MediaImage {
                                image { url }
                            }
                        }
                    }
                    product {
                        id
                        title
                        featuredImage { url }
                    }
                }
            }
        GRAPHQL;

        $data = $this->request($shop, $query, ['id' => $gid]);
        return $data['productVariant'] ?? null;
    }

    /**
     * Get selling plans available for a product variant (for "upgrade to subscription" in checkout).
     * Returns first selling plan per group (Recharge / Shopify subscriptions).
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getSellingPlansForVariant(Shop $shop, string $variantGid): array
    {
        $variant = $this->getProductVariant($shop, $variantGid);
        if (! $variant || ! isset($variant['product']['id'])) {
            return [];
        }
        $productId = (string) $variant['product']['id'];

        $query = <<<'GRAPHQL'
            query getProductSellingPlans($id: ID!) {
                product(id: $id) {
                    id
                    sellingPlanGroups(first: 20) {
                        nodes {
                            id
                            name
                            sellingPlans(first: 5) {
                                nodes {
                                    id
                                    name
                                }
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        $data = $this->request($shop, $query, ['id' => $productId]);
        $product = $data['product'] ?? null;
        if (! $product) {
            return [];
        }
        $groups = $product['sellingPlanGroups']['nodes'] ?? [];
        $plans = [];
        foreach ($groups as $group) {
            $sellingPlans = $group['sellingPlans']['nodes'] ?? [];
            foreach ($sellingPlans as $plan) {
                $id = (string) ($plan['id'] ?? '');
                $name = (string) ($plan['name'] ?? $group['name'] ?? 'Subscription');
                if ($id !== '') {
                    $plans[] = ['id' => $id, 'name' => $name];
                }
            }
        }
        return $plans;
    }

    /**
     * Search product variants for admin picker.
     *
     * @return array<int, array{id: string, label: string, product_title: string, variant_title: string, image_url: string|null, price: string|null}>
     */
    public function searchProductVariants(Shop $shop, string $search = '', int $limit = 25): array
    {
        $items = [];
        $search = trim($search);

        // If search looks like a variant ID (numeric or GID), fetch that variant so it always appears.
        $variantIdToPrepend = $this->parseVariantIdFromSearch($search);
        if ($variantIdToPrepend !== null) {
            try {
                $single = $this->getProductVariant($shop, $variantIdToPrepend);
                if ($single) {
                    $productTitle = (string) ($single['product']['title'] ?? 'Product');
                    $variantTitle = (string) ($single['title'] ?? 'Variant');
                    $price = isset($single['price']) ? (string) $single['price'] : null;
                    $id = (string) ($single['id'] ?? '');
                    $image = $single['product']['featuredImage']['url'] ?? null;
                    $label = $productTitle;
                    if (strtolower($variantTitle) !== 'default title') {
                        $label .= " - {$variantTitle}";
                    }
                    if ($price !== null && $price !== '') {
                        $label .= ' (' . $price . ')';
                    }
                    $items[] = [
                        'id' => $id,
                        'label' => $label,
                        'product_title' => $productTitle,
                        'variant_title' => $variantTitle,
                        'image_url' => $image,
                        'price' => $price,
                    ];
                }
            } catch (\Throwable) {
                // Continue to normal search
            }
        }

        $query = <<<'GRAPHQL'
            query searchProducts($query: String!, $first: Int!) {
                products(query: $query, first: $first) {
                    nodes {
                        id
                        title
                        featuredImage { url }
                        variants(first: 25) {
                            nodes {
                                id
                                title
                                price
                                displayName
                                availableForSale
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        $searchQuery = $search !== ''
            ? "status:active AND ({$search})"
            : 'status:active';

        $data = $this->request($shop, $query, [
            'query' => $searchQuery,
            'first' => max(1, min($limit, 50)),
        ]);

        $seenIds = array_fill_keys(array_column($items, 'id'), true);
        foreach (($data['products']['nodes'] ?? []) as $product) {
            $productTitle = (string) ($product['title'] ?? 'Untitled product');
            $image = $product['featuredImage']['url'] ?? null;

            foreach (($product['variants']['nodes'] ?? []) as $variant) {
                if (($variant['availableForSale'] ?? true) === false) {
                    continue;
                }

                $id = (string) ($variant['id'] ?? '');
                if ($id === '' || isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;

                $variantTitle = (string) ($variant['title'] ?? 'Default');
                $price = isset($variant['price']) ? (string) $variant['price'] : null;

                $label = $productTitle;
                if (strtolower($variantTitle) !== 'default title') {
                    $label .= " - {$variantTitle}";
                }
                if ($price !== null && $price !== '') {
                    $label .= ' (' . $price . ')';
                }

                $items[] = [
                    'id' => $id,
                    'label' => $label,
                    'product_title' => $productTitle,
                    'variant_title' => $variantTitle,
                    'image_url' => $image,
                    'price' => $price,
                ];
            }
        }

        return array_slice($items, 0, $limit);
    }

    /**
     * List active products with variants and selling plans for admin tools.
     *
     * @return array<int, array{id: string, title: string, image_url: string|null, variants: array<int, array{id: string, title: string, price: string|null}>, selling_plans: array<int, array{id: string, name: string}>}>
     */
    public function listProductsWithVariantsAndSellingPlans(Shop $shop, int $first = 50): array
    {
        $query = <<<'GRAPHQL'
            query listProductsWithVariants($first: Int!, $query: String) {
                products(first: $first, query: $query) {
                    nodes {
                        id
                        title
                        featuredImage { url }
                        variants(first: 100) {
                            nodes {
                                id
                                title
                                price
                            }
                        }
                        sellingPlanGroups(first: 20) {
                            nodes {
                                id
                                name
                                sellingPlans(first: 30) {
                                    nodes {
                                        id
                                        name
                                    }
                                }
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        try {
            $data = $this->request($shop, $query, [
                'first' => max(1, min($first, 250)),
                'query' => 'status:active',
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $products = $data['products']['nodes'] ?? [];
        $out = [];
        foreach ($products as $product) {
            $variants = [];
            foreach (($product['variants']['nodes'] ?? []) as $v) {
                $variants[] = [
                    'id' => (string) ($v['id'] ?? ''),
                    'title' => (string) ($v['title'] ?? ''),
                    'price' => isset($v['price']) ? (string) $v['price'] : null,
                ];
            }
            $sellingPlans = [];
            foreach (($product['sellingPlanGroups']['nodes'] ?? []) as $group) {
                foreach (($group['sellingPlans']['nodes'] ?? []) as $plan) {
                    $id = (string) ($plan['id'] ?? '');
                    $name = (string) ($plan['name'] ?? $group['name'] ?? '');
                    if ($id !== '') {
                        $sellingPlans[] = ['id' => $id, 'name' => $name];
                    }
                }
            }
            $out[] = [
                'id' => (string) ($product['id'] ?? ''),
                'title' => (string) ($product['title'] ?? ''),
                'image_url' => $product['featuredImage']['url'] ?? null,
                'variants' => $variants,
                'selling_plans' => $sellingPlans,
            ];
        }
        return $out;
    }

    /**
     * If search string looks like a variant ID (numeric or GID), return GID for getProductVariant.
     */
    protected function parseVariantIdFromSearch(string $search): ?string
    {
        $search = trim($search);
        if ($search === '') {
            return null;
        }
        if (str_starts_with($search, 'gid://shopify/ProductVariant/')) {
            return $search;
        }
        $numeric = preg_replace('/\D/', '', $search);
        if ($numeric !== '' && strlen($numeric) >= 8) {
            return 'gid://shopify/ProductVariant/' . $numeric;
        }
        return null;
    }

    /**
     * Fetch product metadata for cart line rules (collections, tags, vendor, productType).
     * Cached 10 minutes per shop + product to avoid excessive API calls.
     *
     * @return array{collection_ids: array<string>, tags: array<string>, vendor: string, product_type: string}
     */
    public function getProductMetadata(Shop $shop, string $productIdGid): array
    {
        $productIdGid = $this->normalizeProductIdToGid($productIdGid);
        if ($productIdGid === '') {
            return ['collection_ids' => [], 'tags' => [], 'vendor' => '', 'product_type' => ''];
        }
        $cacheKey = 'checkout_product_metadata:'.$shop->id.':'.md5($productIdGid);
        $ttl = now()->addMinutes(10);

        return Cache::remember($cacheKey, $ttl, function () use ($shop, $productIdGid) {
            $query = <<<'GRAPHQL'
                query getProductMetadata($id: ID!) {
                    product(id: $id) {
                        id
                        tags
                        vendor
                        productType
                        collections(first: 250) {
                            nodes { id }
                        }
                    }
                }
            GRAPHQL;
            try {
                $data = $this->request($shop, $query, ['id' => $productIdGid]);
                $product = $data['product'] ?? null;
                if (! $product) {
                    return ['collection_ids' => [], 'tags' => [], 'vendor' => '', 'product_type' => ''];
                }
                $collectionIds = [];
                foreach ($product['collections']['nodes'] ?? [] as $node) {
                    $id = (string) ($node['id'] ?? '');
                    if ($id !== '') {
                        $collectionIds[] = $id;
                    }
                }
                $tags = array_values(array_filter(array_map('strval', $product['tags'] ?? [])));
                return [
                    'collection_ids' => $collectionIds,
                    'tags' => $tags,
                    'vendor' => trim((string) ($product['vendor'] ?? '')),
                    'product_type' => trim((string) ($product['productType'] ?? '')),
                ];
            } catch (\Throwable $e) {
                Log::channel('shopify_api')->warning('getProductMetadata failed', [
                    'shop_id' => $shop->id,
                    'product_id' => $productIdGid,
                    'message' => $e->getMessage(),
                ]);
                return ['collection_ids' => [], 'tags' => [], 'vendor' => '', 'product_type' => ''];
            }
        });
    }

    /**
     * Normalize product ID to Shopify GID.
     */
    public function normalizeProductIdToGid(?string $id): string
    {
        $id = trim((string) $id);
        if ($id === '') {
            return '';
        }
        if (str_starts_with($id, 'gid://shopify/Product/')) {
            return $id;
        }
        $numeric = preg_replace('/\D/', '', $id);

        return $numeric !== '' ? 'gid://shopify/Product/'.$numeric : $id;
    }

    /**
     * Fetch order by Shopify GID (optional; for validation).
     *
     * @return array<string, mixed>|null
     */
    public function getOrder(Shop $shop, string $orderId): ?array
    {
        $gid = str_starts_with($orderId, 'gid://') ? $orderId : "gid://shopify/Order/{$orderId}";
        $query = <<<'GRAPHQL'
            query getOrder($id: ID!) {
                order(id: $id) {
                    id
                    name
                    totalPriceSet { shopMoney { amount } }
                }
            }
        GRAPHQL;

        $data = $this->request($shop, $query, ['id' => $gid]);
        return $data['order'] ?? null;
    }

    /**
     * Create order edit and return changeset for post-purchase: add line + optional discount.
     *
     * @param  array<int, array{variantId: string, quantity: int}>  $lineItems
     * @return array{cartLines?: array, discountCodes?: array}
     */
    public function buildOrderEditChangeset(Shop $shop, string $orderId, array $lineItems, ?float $discountAmount = null): array
    {
        $changeset = [];
        $cartLines = [];
        foreach ($lineItems as $item) {
            $cartLines[] = [
                'variantId' => $item['variantId'],
                'quantity' => $item['quantity'],
            ];
        }
        if (! empty($cartLines)) {
            $changeset['cartLines'] = ['add' => $cartLines];
        }
        if ($discountAmount !== null && $discountAmount > 0) {
            $changeset['discountCodes'] = ['add' => [['code' => 'POSTPURCHASE', 'amount' => (string) $discountAmount]]];
        }

        return $changeset;
    }
}
