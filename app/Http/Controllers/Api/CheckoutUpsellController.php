<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Shop;
use App\Services\RuleEngine;
use App\Services\ShopifyGraphQLService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutUpsellController extends Controller
{
    public function __construct(
        protected RuleEngine $ruleEngine
    ) {}

    /**
     * Return list of offers eligible for checkout (cart context). GET or POST.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop not found'], 404);
        }

        $placement = Placement::where('shop_id', $shop->id)->where('placement_type', 'checkout')->first();
        if (! $placement) {
            return response()->json(['offers' => []]);
        }

        $offerIds = $placement->getOfferIds();
        $maxOffers = (int) ($placement->config['max_offers'] ?? 3);
        $context = $this->buildContext($request);

        $eligible = $this->findEligibleOffers($shop, $offerIds, $context, $maxOffers);
        $data = $this->enrichOffersFromShopify($shop, $eligible);
        $ui = $this->buildUiFromPlacement($placement);

        return response()->json([
            'offers' => $data,
            'display_mode' => $ui['display_mode'],
            'ui' => $ui,
        ]);
    }

    /**
     * Build API payload for offers; enrich title/image from Shopify when missing.
     *
     * @param  array<Offer>  $eligible
     * @return array<int, array<string, mixed>>
     */
    protected function enrichOffersFromShopify(Shop $shop, array $eligible): array
    {
        $service = app(ShopifyGraphQLService::class);
        $out = [];
        foreach ($eligible as $o) {
            $variantId = $this->normalizeVariantIdToGid($o->product_variant_id);
            $title = trim((string) $o->title);
            $imageUrl = trim((string) $o->image_url);
            $price = null;

            if ($variantId !== '') {
                try {
                    $variant = $service->getProductVariant($shop, $variantId);
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
                } catch (DecryptException|\Throwable) {
                    // Keep DB values
                }
            }

            $out[] = [
                'id' => $o->id,
                'title' => $title ?: $o->title,
                'description' => $o->description,
                'variant_id' => $variantId,
                'discount_type' => $o->discount_type,
                'discount_value' => $o->discount_value?->toString(),
                'image_url' => $imageUrl ?: $o->image_url,
                'price' => $price,
                'offer_type' => (string) ($o->offer_type ?? 'one_time'),
                'selling_plan_id' => $o->selling_plan_id ? (string) $o->selling_plan_id : null,
            ];
        }
        return $out;
    }

    /**
     * Build UI config from placement for checkout block.
     *
     * @return array<string, mixed>
     */
    protected function buildUiFromPlacement(Placement $placement): array
    {
        $c = $placement->config ?? [];

        $progressBarEnabled = (bool) ($c['progress_bar_enabled'] ?? false);
        $progressBarGoal = (float) ($c['progress_bar_goal'] ?? 0);
        $progressBar = [
            'enabled' => $progressBarEnabled && $progressBarGoal > 0,
            'type' => (string) ($c['progress_bar_type'] ?? 'free_shipping'),
            'goal' => $progressBarGoal,
            'message_below' => (string) ($c['progress_bar_message_below'] ?? "You're {amount} away from free shipping!"),
            'message_achieved' => (string) ($c['progress_bar_message_achieved'] ?? "You've unlocked free shipping!"),
            'discount_type' => (string) ($c['progress_bar_discount_type'] ?? 'percentage'),
            'discount_value' => (float) ($c['progress_bar_discount_value'] ?? 0),
        ];

        return [
            'display_mode' => (string) ($c['display_mode'] ?? 'stacked'),
            'section_heading' => (string) ($c['section_heading'] ?? 'Add to your order'),
            'title_size' => (string) ($c['title_size'] ?? 'medium'),
            'title_appearance' => (string) ($c['title_appearance'] ?? 'default'),
            'show_price' => (bool) ($c['show_price'] ?? true),
            'show_description' => (bool) ($c['show_description'] ?? true),
            'image_aspect_ratio' => trim((string) ($c['image_aspect_ratio'] ?? '')),
            'image_fit' => (string) ($c['image_fit'] ?? 'cover'),
            'image_corner_radius' => (string) ($c['image_corner_radius'] ?? 'base'),
            'button_kind' => (string) ($c['button_kind'] ?? 'secondary'),
            'button_appearance' => (string) ($c['button_appearance'] ?? 'default'),
            'card_spacing' => (string) ($c['card_spacing'] ?? 'loose'),
            'divider_between_cards' => (bool) ($c['divider_between_cards'] ?? false),
            'progress_bar' => $progressBar,
        ];
    }

    /**
     * Normalize product_variant_id to Shopify GID for useApplyCartLinesChange (merchandiseId).
     */
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

        return $numeric !== '' ? 'gid://shopify/ProductVariant/'.$numeric : $id;
    }

    protected function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->input('shop') ?? $request->query('shop') ?? $request->header('X-Shop-Domain');
        if (! $shopDomain) {
            return null;
        }
        return Shop::findByDomainOrAlternates($shopDomain);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildContext(Request $request): array
    {
        return [
            'subtotal' => $request->input('subtotal') ?? $request->input('cart.subtotal') ?? 0,
            'line_items' => $request->input('line_items') ?? $request->input('cart.line_items') ?? $request->input('lineItems') ?? [],
            'customer' => $request->input('customer') ?? [],
            'shipping_country' => $request->input('shipping_country') ?? $request->input('shippingAddress.countryCode') ?? null,
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
}
