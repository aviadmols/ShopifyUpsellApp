<?php

namespace App\Http\Controllers;

use App\Services\ShopifyOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Shopify OAuth: install redirect and callback. Stores encrypted token.
 */
class AuthController extends Controller
{
    public function __construct(
        protected ShopifyOAuthService $oauth
    ) {}

    /**
     * Redirect to Shopify install/authorize. Query: shop=store.myshopify.com
     */
    public function install(Request $request): RedirectResponse
    {
        $shop = $request->query('shop');
        if (! $shop) {
            return redirect()->to('/')->with('error', 'Missing shop parameter');
        }
        return redirect()->away($this->oauth->redirectToInstall($shop));
    }

    /**
     * Callback after merchant authorizes. Exchange code for token.
     */
    public function callback(Request $request): RedirectResponse
    {
        $query = $request->query->all();

        // Shopify may redirect with error (e.g. user denied, or invalid request)
        if (! empty($query['error'])) {
            $message = $query['error_description'] ?? $query['error'];
            return redirect()->to('/admin')->with('error', 'Shopify authorization failed: ' . $message);
        }

        if (! $this->oauth->verifyHmac($query)) {
            return redirect()->to('/admin')->with('error', 'Invalid HMAC. Check that SHOPIFY_API_SECRET on the server matches the Client secret in Shopify Partners.');
        }

        $shop = $request->query('shop');
        $code = $request->query('code');
        if (! $shop || ! $code) {
            return redirect()->to('/admin')->with('error', 'Missing shop or code in callback.');
        }

        try {
            $this->oauth->exchangeCode($shop, $code);
        } catch (\Throwable $e) {
            return redirect()->to('/admin')->with('error', 'Could not connect store: ' . $e->getMessage());
        }

        session(['shop_domain' => $shop]);
        return redirect()
            ->to('/admin/shops')
            ->with('success', __('Shop connected successfully.'));
    }
}
