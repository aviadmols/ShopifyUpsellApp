<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
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
     * If block_id is provided, use Block (surface=post_purchase, type=post_purchase_funnel); otherwise Placement (legacy).
     */
    public function shouldRender(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop not found'], 404);
        }

        $blockId = $request->input('block_id');
        if ($blockId !== null && $blockId !== '') {
            $block = Block::where('shop_id', $shop->id)->where('surface', 'post_purchase')->where('type', 'post_purchase_funnel')->find((int) $blockId);
            if ($block) {
                $context = $this->buildContext($request);
                if ($block->rule_id) {
                    $rule = $block->rule;
                    if (! $rule || ! $this->ruleEngine->evaluate($rule->conditions, $context)) {
                        return response()->json(['render' => false]);
                    }
                }
                $offerIds = $block->getOfferIds();
                $maxOffers = (int) (($block->config ?? [])['max_offers'] ?? 1);
                $eligible = $this->findEligibleOffers($shop, $offerIds, $context, $maxOffers);
                if (empty($eligible)) {
                    return response()->json(['render' => false]);
                }
                foreach ($eligible as $o) {
                    $this->idempotency->logEvent($shop, (string) ($context['order_id'] ?? ''), $o->id, 'impression', $context);
                }
                $offersPayload = $this->enrichOffersForPostPurchase($shop, $eligible);
                $funnel = $this->buildFunnelFromConfig($block->config ?? []);

                return response()->json([
                    'render' => true,
                    'offers' => $offersPayload,
                    'funnel' => $funnel,
                ]);
            }
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

        foreach ($eligible as $o) {
            $this->idempotency->logEvent($shop, (string) ($context['order_id'] ?? ''), $o->id, 'impression', $context);
        }

        $offersPayload = $this->enrichOffersForPostPurchase($shop, $eligible);
        $funnel = $this->buildFunnelFromPlacement($placement);

        return response()->json([
            'render' => true,
            'offers' => $offersPayload,
            'funnel' => $funnel,
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
        $quantity = max(1, (int) ($request->input('quantity') ?? 1));
        $idempotencyKey = $request->input('idempotency_key');

        if (! $orderId || ! $offerId || ! $variantId) {
            return response()->json(['error' => 'Missing order_id, offer_id, or variant_id'], 422);
        }

        $offer = Offer::where('shop_id', $shop->id)->find($offerId);
        if (! $offer) {
            return response()->json(['error' => 'Offer not found'], 404);
        }

        if ($this->idempotency->alreadyAccepted($shop, (string) $orderId, $offerId)) {
            return response()->json($this->buildChangeset($offer, $quantity), 200);
        }

        $discountAmount = $this->discountAmount($offer, $request);
        $lineItems = [['variantId' => $variantId, 'quantity' => $quantity]];
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
    protected function buildChangeset(Offer $offer, int $quantity = 1): array
    {
        $lineItems = [['variantId' => $offer->product_variant_id, 'quantity' => $quantity]];
        $discount = $offer->discount_type !== 'none' && $offer->discount_value
            ? (float) $offer->discount_value->toString() : null;
        return $this->graphql->buildOrderEditChangeset($offer->shop, '', $lineItems, $discount);
    }

    /**
     * Enrich offers with price, discounted_price, save_percent for funnel UI.
     *
     * @param  array<Offer>  $offers
     * @return array<int, array<string, mixed>>
     */
    protected function enrichOffersForPostPurchase(Shop $shop, array $offers): array
    {
        $out = [];
        foreach ($offers as $o) {
            $variantId = $this->normalizeVariantIdToGid($o->product_variant_id);
            $title = trim((string) $o->title);
            $imageUrl = trim((string) $o->image_url);
            $price = null;

            if ($variantId !== '') {
                try {
                    $variant = $this->graphql->getProductVariant($shop, $variantId);
                    if ($variant) {
                        if ($title === '') {
                            $productTitle = (string) ($variant['product']['title'] ?? 'Product');
                            $variantTitle = (string) ($variant['title'] ?? '');
                            $title = strtolower($variantTitle ?? '') !== 'default title'
                                ? $productTitle . ' - ' . $variantTitle
                                : $productTitle;
                        }
                        if ($imageUrl === '') {
                            $imageUrl = (string) ($variant['product']['featuredImage']['url'] ?? '');
                        }
                        $price = isset($variant['price']) ? (string) $variant['price'] : null;
                    }
                } catch (\Throwable) {
                    // keep DB values
                }
            }

            $originalPrice = $price !== null && $price !== '' ? (float) $price : null;
            $discountVal = $o->discount_type !== 'none' && $o->discount_value
                ? (float) $o->discount_value->toString() : 0;
            $discountedPrice = $originalPrice;
            $savePercent = null;
            if ($originalPrice !== null && $discountVal > 0) {
                if ($o->discount_type === 'percentage') {
                    $discountedPrice = round($originalPrice * (1 - $discountVal / 100), 2);
                    $savePercent = (int) round($discountVal);
                } else {
                    $discountedPrice = round(max(0, $originalPrice - $discountVal), 2);
                    $savePercent = $originalPrice > 0 ? (int) round(($originalPrice - $discountedPrice) / $originalPrice * 100) : 0;
                }
            }

            $out[] = [
                'offerId' => $o->id,
                'variantId' => $variantId ?: $o->product_variant_id,
                'title' => $title ?: $o->title,
                'description' => $o->description,
                'image_url' => $imageUrl ?: $o->image_url,
                'price' => $originalPrice !== null ? (string) $originalPrice : null,
                'discounted_price' => $discountedPrice !== null ? (string) $discountedPrice : null,
                'save_percent' => $savePercent,
                'discount_type' => $o->discount_type,
                'discount_value' => $o->discount_value?->toString(),
                'offer_type' => (string) ($o->offer_type ?? 'one_time'),
                'selling_plan_id' => $o->selling_plan_id ? (string) $o->selling_plan_id : null,
                'allow_subscription_in_post_purchase' => (bool) ($o->allow_subscription_in_post_purchase ?? false),
            ];
        }
        return $out;
    }

    /**
     * Build funnel UI config from placement for post-purchase extension.
     *
     * @return array<string, mixed>
     */
    protected function buildFunnelFromPlacement(Placement $placement): array
    {
        return $this->buildFunnelFromConfig($placement->config ?? []);
    }

    /**
     * Build funnel UI config from array (Block or Placement config).
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    protected function buildFunnelFromConfig(array $c): array
    {
        $stepLabels = (string) ($c['funnel_step_labels'] ?? 'Order, Offer, Bonus, Done');
        $labels = array_map('trim', explode(',', $stepLabels));

        return [
            'headline_template' => (string) ($c['funnel_headline_template'] ?? ''),
            'show_progress_steps' => (bool) ($c['funnel_show_progress'] ?? true),
            'step_labels' => array_values(array_filter($labels)),
            'show_timer' => (bool) ($c['show_timer'] ?? false),
            'timer_seconds' => max(0, (int) ($c['timer_seconds'] ?? 300)),
            'timer_label' => (string) ($c['timer_label'] ?? 'For a limited time'),
            'urgency_message' => (string) ($c['urgency_message'] ?? ''),
            'cta_text' => (string) ($c['cta_text'] ?? 'Pay Now'),
            'decline_text' => (string) ($c['decline_text'] ?? 'Decline offer'),
            'quantity_default' => max(1, (int) ($c['quantity_default'] ?? 1)),
            'quantity_min' => max(1, (int) ($c['quantity_min'] ?? 1)),
            'quantity_max' => max(1, (int) ($c['quantity_max'] ?? 10)),
        ];
    }

    protected function normalizeVariantIdToGid(?string $id): string
    {
        $id = trim((string) $id);
        if ($id === '') {
            return '';
        }
        if (str_starts_with($id, 'gid://')) {
            return $id;
        }
        $numeric = preg_replace('/\D/', '', $id);

        return $numeric !== '' ? 'gid://shopify/ProductVariant/' . $numeric : $id;
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
