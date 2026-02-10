<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies that extension requests are signed with CHECKOUT_EXTENSION_SECRET.
 * Expects header X-Extension-Signature: HMAC(shop + body) or X-Extension-Secret: <secret>.
 */
class VerifyCheckoutExtensionSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('shopify.checkout_extension_secret');
        if (empty($secret)) {
            return response()->json(['error' => 'Extension secret not configured'], 500);
        }

        $provided = $request->header('X-Extension-Secret') ?? $request->header('X-Checkout-Extension-Secret');
        if (! hash_equals($secret, (string) $provided)) {
            return response()->json(['error' => 'Invalid or missing extension signature'], 401);
        }

        return $next($request);
    }
}
