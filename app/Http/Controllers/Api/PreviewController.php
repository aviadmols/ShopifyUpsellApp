<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Shop;
use App\Services\RuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    public function __construct(
        protected RuleEngine $ruleEngine
    ) {}

    /**
     * Accept sample order/cart JSON and return which offer would render (for post_purchase or checkout).
     */
    public function preview(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }

        $payload = $request->input('payload') ?? $request->all();
        $placementType = $request->input('placement_type') ?? 'post_purchase';

        $context = $this->normalizeContext($payload);
        $placement = Placement::where('shop_id', $shop->id)->where('placement_type', $placementType)->first();
        if (! $placement) {
            return response()->json(['match' => null, 'message' => 'No placement configured']);
        }

        $offerIds = $placement->config['offer_ids'] ?? [];
        $maxOffers = (int) ($placement->config['max_offers'] ?? 1);
        $eligible = [];

        foreach ($offerIds as $id) {
            if (count($eligible) >= $maxOffers) {
                break;
            }
            $offer = Offer::where('shop_id', $shop->id)->find($id);
            if (! $offer) {
                continue;
            }
            if ($offer->rule_id) {
                $rule = $offer->rule;
                if (! $rule || ! $this->ruleEngine->evaluate($rule->conditions, $context)) {
                    continue;
                }
            }
            $eligible[] = $offer;
        }

        $matched = $eligible[0] ?? null;
        return response()->json([
            'match' => $matched ? [
                'offerId' => $matched->id,
                'title' => $matched->title,
                'variantId' => $matched->product_variant_id,
            ] : null,
            'context_used' => $context,
        ]);
    }

    protected function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->query('shop') ?? $request->input('shop') ?? session('shop_domain');
        if (! $shopDomain) {
            return null;
        }
        return Shop::where('shop_domain', $shopDomain)->whereNull('uninstalled_at')->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeContext(array $payload): array
    {
        $utms = $payload['utms'] ?? [];
        if (! is_array($utms)) {
            $utms = [];
        }
        $urlParams = $payload['url_params'] ?? $payload['query'] ?? [];
        if (! is_array($urlParams)) {
            $urlParams = [];
        }
        return [
            'order_id' => $payload['order_id'] ?? $payload['order']['id'] ?? null,
            'subtotal' => $payload['subtotal'] ?? $payload['order']['subtotal'] ?? 0,
            'line_items' => $payload['line_items'] ?? $payload['lineItems'] ?? $payload['order']['line_items'] ?? [],
            'customer' => $payload['customer'] ?? [],
            'shipping_address' => $payload['shipping_address'] ?? $payload['shippingAddress'] ?? [],
            'shipping_country' => $payload['shipping_country'] ?? $payload['shipping_address']['country_code'] ?? $payload['shippingAddress']['countryCode'] ?? null,
            'utms' => array_filter(array_map('strval', $utms)),
            'url_params' => array_filter(array_map('strval', $urlParams)),
        ];
    }
}
