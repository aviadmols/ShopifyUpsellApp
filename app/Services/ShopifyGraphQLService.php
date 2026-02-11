<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;

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
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Content-Type' => 'application/json',
        ])->post($this->graphqlUrl($shop->shop_domain), [
            'query' => $query,
            'variables' => $variables,
        ]);

        $response->throw();
        $data = $response->json();

        if (isset($data['errors'])) {
            throw new \RuntimeException('GraphQL errors: ' . json_encode($data['errors']));
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
     * Search product variants for admin picker.
     *
     * @return array<int, array{id: string, label: string, product_title: string, variant_title: string, image_url: string|null, price: string|null}>
     */
    public function searchProductVariants(Shop $shop, string $search = '', int $limit = 25): array
    {
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

        $searchQuery = trim($search) !== ''
            ? "status:active AND ({$search})"
            : 'status:active';

        $data = $this->request($shop, $query, [
            'query' => $searchQuery,
            'first' => max(1, min($limit, 50)),
        ]);

        $items = [];
        foreach (($data['products']['nodes'] ?? []) as $product) {
            $productTitle = (string) ($product['title'] ?? 'Untitled product');
            $image = $product['featuredImage']['url'] ?? null;

            foreach (($product['variants']['nodes'] ?? []) as $variant) {
                if (($variant['availableForSale'] ?? true) === false) {
                    continue;
                }

                $variantTitle = (string) ($variant['title'] ?? 'Default');
                $price = isset($variant['price']) ? (string) $variant['price'] : null;
                $id = (string) ($variant['id'] ?? '');
                if ($id === '') {
                    continue;
                }

                $label = $productTitle;
                if (strtolower($variantTitle) !== 'default title') {
                    $label .= " - {$variantTitle}";
                }
                if ($price !== null && $price !== '') {
                    $label .= " (${$price})";
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
