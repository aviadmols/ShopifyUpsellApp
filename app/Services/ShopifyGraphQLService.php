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
