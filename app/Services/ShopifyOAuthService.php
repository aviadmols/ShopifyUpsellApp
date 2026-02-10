<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;

/**
 * Shopify OAuth: install redirect, code exchange, store encrypted token.
 */
class ShopifyOAuthService
{
    protected string $apiKey;
    protected string $apiSecret;
    protected string $scopes;

    public function __construct()
    {
        $this->apiKey = config('shopify.api_key', '');
        $this->apiSecret = config('shopify.api_secret', '');
        $this->scopes = config('shopify.scopes', '');
    }

    /**
     * Build install/authorization URL for the given shop.
     */
    public function redirectToInstall(string $shopDomain): string
    {
        $redirectUri = rtrim(config('app.url'), '/') . '/auth/callback';
        $state = bin2hex(random_bytes(16));
        session(['shopify_oauth_state' => $state]);

        $params = http_build_query([
            'client_id' => $this->apiKey,
            'scope' => $this->scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return "https://{$shopDomain}/admin/oauth/authorize?{$params}";
    }

    /**
     * Exchange code for access token and persist for shop.
     */
    public function exchangeCode(string $shopDomain, string $code): Shop
    {
        $url = "https://{$shopDomain}/admin/oauth/access_token";
        $response = Http::post($url, [
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
            'code' => $code,
        ]);

        $response->throw();
        $body = $response->json();
        $this->storeToken($shopDomain, $body['access_token'] ?? '');

        return Shop::where('shop_domain', $shopDomain)->firstOrFail();
    }

    /**
     * Create or update shop and store encrypted access token.
     */
    public function storeToken(string $shopDomain, string $accessToken): Shop
    {
        $shop = Shop::updateOrCreate(
            ['shop_domain' => $shopDomain],
            [
                'access_token' => $accessToken,
                'scope' => $this->scopes,
                'installed_at' => now(),
                'uninstalled_at' => null,
            ]
        );

        return $shop;
    }

    /**
     * Verify HMAC of request query (for OAuth callback).
     */
    public function verifyHmac(array $params): bool
    {
        $hmac = $params['hmac'] ?? '';
        unset($params['hmac'], $params['signature']);
        ksort($params);
        $query = http_build_query($params);
        $computed = hash_hmac('sha256', $query, $this->apiSecret);

        return hash_equals($computed, $hmac);
    }
}
