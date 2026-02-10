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
        if (! $this->oauth->verifyHmac($request->query->all())) {
            return redirect()->to('/')->with('error', 'Invalid HMAC');
        }
        $shop = $request->query('shop');
        $code = $request->query('code');
        if (! $shop || ! $code) {
            return redirect()->to('/')->with('error', 'Missing shop or code');
        }
        $this->oauth->exchangeCode($shop, $code);
        session(['shop_domain' => $shop]);
        return redirect()
            ->to('/admin/shops')
            ->with('success', __('Shop connected successfully.'));
    }
}
