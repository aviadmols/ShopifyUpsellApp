<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Shop;
use App\Services\PostPurchaseIdempotencyService;
use App\Services\RuleEngine;
use App\Services\ShopifyGraphQLService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostPurchaseController extends Controller
{
    public function __construct(
        protected RuleEngine $ruleEngine,
        protected PostPurchaseIdempotencyService $idempotency,
        protected ShopifyGraphQLService $graphql
    ) {}

    /**
     * Decide if post-purchase offer should render; return first eligible offer. Log impression.
     */
    public function shouldRender(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop not found'], 404);
        }

        $placement = Placement::where('shop_id', $shop->id)->where('placement_type', 'post_purchase')->first();
        if (! $placement) {
            return response()->json(['render' => false]);
        }

        $offerIds = $placement->getOfferIds();
        $maxOffers = (int) ($placement->config['max_offers'] ?? 1);
        $context = $this->buildContext($request);

        $eligible = $this->findEligibleOffers($shop, $offerIds, $context, $maxOffers);
        if (empty($eligible)) {
            return response()->json(['render' => false]);
        }

        $offer = $eligible[0];
        $this->idempotency->logEvent($shop, (string) ($context['order_id'] ?? ''), $offer->id, 'impression', $context);

        return response()->json([
            'render' => true,
            'offerId' => $offer->id,
            'variantId' => $offer->product_variant_id,
            'message' => $offer->title,
            'discount' => [
                'type' => $offer->discount_type,
                'value' => $offer->discount_value?->toString(),
            ],
            'image_url' => $offer->image_url,
            'description' => $offer->description,
            'offer_type' => (string) ($offer->offer_type ?? 'one_time'),
            'selling_plan_id' => $offer->selling_plan_id ? (string) $offer->selling_plan_id : null,
            'allow_subscription_in_post_purchase' => (bool) ($offer->allow_subscription_in_post_purchase ?? false),
        ]);
    }

    /**
     * Validate accept request, ensure idempotent, return changeset for Shopify.
     */
    public function accept(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop not found'], 404);
        }

        $orderId = $request->input('order_id');
        $offerId = (int) $request->input('offer_id');
        $variantId = $request->input('variant_id');
        $idempotencyKey = $request->input('idempotency_key');

        if (! $orderId || ! $offerId || ! $variantId) {
            return response()->json(['error' => 'Missing order_id, offer_id, or variant_id'], 422);
        }

        $offer = Offer::where('shop_id', $shop->id)->find($offerId);
        if (! $offer) {
            return response()->json(['error' => 'Offer not found'], 404);
        }

        if ($this->idempotency->alreadyAccepted($shop, (string) $orderId, $offerId)) {
            return response()->json($this->buildChangeset($offer), 200);
        }

        $discountAmount = $this->discountAmount($offer, $request);
        $lineItems = [['variantId' => $variantId, 'quantity' => 1]];
        $changeset = $this->graphql->buildOrderEditChangeset($shop, $orderId, $lineItems, $discountAmount);

        $this->idempotency->recordAccept($shop, (string) $orderId, $offerId, $request->all());

        return response()->json($changeset);
    }

    protected function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->input('shop') ?? $request->header('X-Shop-Domain');
        if (! $shopDomain) {
            return null;
        }
        return Shop::where('shop_domain', $shopDomain)->whereNull('uninstalled_at')->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildContext(Request $request): array
    {
        return [
            'order_id' => $request->input('order.id') ?? $request->input('order_id'),
            'subtotal' => $request->input('order.subtotal') ?? $request->input('subtotal'),
            'line_items' => $request->input('line_items') ?? $request->input('lineItems') ?? [],
            'customer' => $request->input('customer') ?? [],
            'shipping_address' => $request->input('shipping_address') ?? $request->input('shippingAddress') ?? [],
            'shipping_country' => $request->input('shipping_address.country_code') ?? $request->input('shippingAddress.countryCode') ?? null,
            'utms' => $this->utmsFromRequest($request),
            'url_params' => $this->urlParamsFromRequest($request),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function utmsFromRequest(Request $request): array
    {
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $fromInput = $request->input('utms');
        if (is_array($fromInput)) {
            return array_filter(array_map('strval', $fromInput));
        }
        $out = [];
        foreach ($utmKeys as $key) {
            $v = $request->query($key) ?? $request->input($key);
            if ($v !== null && $v !== '') {
                $out[$key] = (string) $v;
            }
        }
        if ($request->hasSession() && $request->session()->has('utms')) {
            $out = array_merge($out, (array) $request->session()->get('utms'));
        }
        return $out;
    }

    /**
     * @return array<string, string>
     */
    protected function urlParamsFromRequest(Request $request): array
    {
        $fromInput = $request->input('url_params');
        if (is_array($fromInput)) {
            return array_filter(array_map('strval', $fromInput));
        }
        $out = array_merge(
            $request->query() ?? [],
            (array) $request->input('query', [])
        );
        return array_filter(array_map('strval', $out));
    }

    /**
     * @param  array<int, int>  $offerIds
     * @return array<Offer>
     */
    protected function findEligibleOffers(Shop $shop, array $offerIds, array $context, int $max): array
    {
        $eligible = [];
        foreach ($offerIds as $id) {
            if (count($eligible) >= $max) {
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
        return $eligible;
    }

    /**
     * @return array{cartLines?: array, discountCodes?: array}
     */
    protected function buildChangeset(Offer $offer): array
    {
        $lineItems = [['variantId' => $offer->product_variant_id, 'quantity' => 1]];
        $discount = $offer->discount_type !== 'none' && $offer->discount_value
            ? (float) $offer->discount_value->toString() : null;
        return $this->graphql->buildOrderEditChangeset($offer->shop, '', $lineItems, $discount);
    }

    protected function discountAmount(Offer $offer, Request $request): ?float
    {
        if ($offer->discount_type === 'none' || ! $offer->discount_value) {
            return null;
        }
        $val = (float) $offer->discount_value->toString();
        if ($offer->discount_type === 'percentage') {
            $subtotal = (float) ($request->input('order.subtotal') ?? $request->input('subtotal') ?? 0);
            return round($subtotal * $val / 100, 2);
        }
        return $val;
    }
}
