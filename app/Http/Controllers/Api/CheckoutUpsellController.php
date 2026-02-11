<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
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
     * If block_id is provided, use Block (surface=checkout); otherwise fallback to Placement (legacy).
     */
    public function index(Request $request): JsonResponse
    {
        $blockId = $request->input('block_id') ?? $request->query('block_id');
        if ($blockId !== null && $blockId !== '') {
            $block = Block::where('surface', 'checkout')->find((int) $blockId);
            if ($block) {
                $shop = $block->shop;
                if ($shop && $shop->uninstalled_at === null) {
                    $requestShop = $this->resolveShop($request);
                    if ($requestShop !== null && $requestShop->id !== $shop->id) {
                        return response()->json(['error' => 'Block belongs to another store.'], 403);
                    }
                    return $this->responseForBlock($request, $shop, $block);
                }
            }
        }

        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop not found. Set Shop domain in block settings to your store (e.g. mystore.myshopify.com).'], 404);
        }

        if ($blockId !== null && $blockId !== '') {
            return response()->json([
                'offers' => [],
                'blocks' => [],
                'ui' => [],
                'error' => 'Block not found. Check Widget ID and that the widget exists for this store.',
            ], 404);
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
     * Build response for a single checkout Block (by block_id).
     */
    protected function responseForBlock(Request $request, Shop $shop, Block $block): JsonResponse
    {
        $context = $this->buildContext($request);

        if ($block->rule_id) {
            $rule = $block->rule;
            if (! $rule || ! $this->ruleEngine->evaluate($rule->conditions, $context)) {
                return response()->json(['offers' => [], 'blocks' => [], 'ui' => []]);
            }
        }

        $type = (string) $block->type;
        $config = $block->config ?? [];

        if ($type === 'upsell') {
            $offerIds = $block->getOfferIds();
            $maxOffers = (int) ($config['max_offers'] ?? 3);
            $eligible = $this->findEligibleOffers($shop, $offerIds, $context, $maxOffers);
            $data = $this->enrichOffersFromShopify($shop, $eligible);
            $ui = $this->buildUiFromBlockConfig($config, false);

            return response()->json([
                'offers' => $data,
                'display_mode' => (string) ($config['display_mode'] ?? 'stacked'),
                'ui' => $ui,
            ]);
        }

        if ($type === 'progress_bar') {
            $ui = $this->buildUiFromBlockConfig($config, true);

            return response()->json([
                'offers' => [],
                'display_mode' => 'stacked',
                'ui' => $ui,
            ]);
        }

        if (str_starts_with($type, 'content_')) {
            $blocksPayload = [
                [
                    'id' => $block->id,
                    'type' => $type,
                    'config' => $config,
                ],
            ];

            return response()->json([
                'offers' => [],
                'blocks' => $blocksPayload,
                'display_mode' => 'stacked',
                'ui' => [],
            ]);
        }

        return response()->json(['offers' => [], 'blocks' => [], 'ui' => []]);
    }

    /**
     * Build UI config from block config (upsell or progress_bar).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function buildUiFromBlockConfig(array $config, bool $progressBarOnly): array
    {
        $progressBarEnabled = (bool) ($config['progress_bar_enabled'] ?? false);
        $progressBarGoal = (float) ($config['progress_bar_goal'] ?? 0);
        $progressBar = [
            'enabled' => $progressBarEnabled || $progressBarOnly,
            'type' => (string) ($config['progress_bar_type'] ?? 'free_shipping'),
            'goal' => $progressBarGoal,
            'message_below' => (string) ($config['progress_bar_message_below'] ?? "You're {amount} away from free shipping!"),
            'message_achieved' => (string) ($config['progress_bar_message_achieved'] ?? "You've unlocked free shipping!"),
            'discount_type' => (string) ($config['progress_bar_discount_type'] ?? 'percentage'),
            'discount_value' => (float) ($config['progress_bar_discount_value'] ?? 0),
        ];
        if ($progressBarOnly && $progressBarGoal <= 0) {
            $progressBar['goal'] = (float) ($config['progress_bar_goal'] ?? 100);
        }

        $ui = [
            'progress_bar' => $progressBar,
        ];
        if (! $progressBarOnly) {
            $ui['display_mode'] = (string) ($config['display_mode'] ?? 'stacked');
            $ui['section_heading'] = (string) ($config['section_heading'] ?? 'Add to your order');
            $ui['title_size'] = (string) ($config['title_size'] ?? 'medium');
            $ui['title_appearance'] = (string) ($config['title_appearance'] ?? 'default');
            $ui['show_price'] = (bool) ($config['show_price'] ?? true);
            $ui['show_description'] = (bool) ($config['show_description'] ?? true);
            $ui['image_aspect_ratio'] = trim((string) ($config['image_aspect_ratio'] ?? ''));
            $ui['image_fit'] = (string) ($config['image_fit'] ?? 'cover');
            $ui['image_corner_radius'] = (string) ($config['image_corner_radius'] ?? 'base');
            $ui['button_kind'] = (string) ($config['button_kind'] ?? 'secondary');
            $ui['button_appearance'] = (string) ($config['button_appearance'] ?? 'default');
            $ui['card_spacing'] = (string) ($config['card_spacing'] ?? 'loose');
            $ui['divider_between_cards'] = (bool) ($config['divider_between_cards'] ?? false);
        }

        return $ui;
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
