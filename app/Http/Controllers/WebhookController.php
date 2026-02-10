<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles Shopify webhooks. Verifies HMAC before processing.
 */
class WebhookController extends Controller
{
    /**
     * Verify webhook HMAC using X-Shopify-Hmac-Sha256 header.
     */
    protected function verifyHmac(Request $request): bool
    {
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $secret = config('shopify.api_secret');
        $body = $request->getContent();
        $computed = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return $hmac && hash_equals($computed, $hmac);
    }

    /**
     * App uninstalled: mark shop as uninstalled.
     */
    public function appUninstalled(Request $request): Response
    {
        if (! $this->verifyHmac($request)) {
            return response('', 401);
        }
        $shopDomain = $request->input('myshopify_domain') ?? $request->input('domain');
        if ($shopDomain) {
            Shop::where('shop_domain', $shopDomain)->update(['uninstalled_at' => now()]);
        }
        return response('', 200);
    }

    /**
     * Orders create (optional): for analytics. Persist minimal data or skip.
     */
    public function ordersCreate(Request $request): Response
    {
        if (! $this->verifyHmac($request)) {
            return response('', 401);
        }
        // Optional: store order id / shop for analytics. Not persisting by default.
        return response('', 200);
    }
}
