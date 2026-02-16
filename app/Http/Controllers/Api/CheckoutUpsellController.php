<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\CheckoutExperience;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Shop;
use App\Services\CartLineRulesEvaluator;
use App\Services\CartLineUpgradeMatcher;
use App\Services\RuntimeTemplateVarsService;
use App\Services\RuleEngine;
use App\Services\ShopifyGraphQLService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckoutUpsellController extends Controller
{
    public function __construct(
        protected RuleEngine $ruleEngine
    ) {}

    /**
     * Write structured logs for checkout extension debugging.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logExt(string $event, array $context = []): void
    {
        try {
            Log::channel('checkout_extension')->info($event, $context);
        } catch (\Throwable) {
            // Never break checkout due to logging.
        }
    }

    /**
     * Receive logs from the Checkout extension (widget load, fetch result, errors).
     * Writes to storage/logs/checkout_extension.log.
     */
    public function log(Request $request): JsonResponse
    {
        $payload = $request->all();
        // Avoid accidentally storing huge payloads.
        if (is_array($payload) && isset($payload['line_items']) && is_array($payload['line_items'])) {
            $payload['line_items_count'] = count($payload['line_items']);
            unset($payload['line_items']);
        }
        $this->logExt('checkout_widget_log', $payload);

        return response()->json(['ok' => true], 200);
    }

    /**
     * Return list of offers eligible for checkout (cart context). GET or POST.
     * If block_id is provided, use Block (surface=checkout); otherwise fallback to Placement (legacy).
     */
    public function index(Request $request): JsonResponse
    {
        $blockId = $request->input('block_id') ?? $request->query('block_id');
        $this->logExt('checkout_offers_request', [
            'block_id' => $blockId,
            'shop' => $request->input('shop') ?? $request->query('shop'),
            'subtotal' => $request->input('subtotal') ?? null,
            'line_items_count' => is_array($request->input('line_items')) ? count((array) $request->input('line_items')) : null,
        ]);
        $emptyResponse = fn (string $blockError = null) => response()->json(array_filter([
            'offers' => [],
            'blocks' => [],
            'ui' => [],
            'block_error' => $blockError,
        ]));

        if ($blockId !== null && $blockId !== '') {
            $blockIdInt = (int) $blockId;
            if ($blockIdInt < 1) {
                $this->logExt('checkout_offers_block_invalid', ['block_id' => $blockId]);
                return $emptyResponse('Widget ID must be the number from Admin → Widgets (e.g. 5 or 12). You entered: '.((string) $blockId));
            }
            $block = Block::where('surface', 'checkout')->find($blockIdInt);
            if ($block) {
                $this->logExt('checkout_offers_block_found', ['block_id' => $block->id, 'type' => (string) $block->type, 'shop_id' => $block->shop_id]);
                $shop = $block->shop;
                if ($shop && $shop->uninstalled_at === null) {
                    return $this->responseForBlock($request, $shop, $block);
                }
                $this->logExt('checkout_offers_shop_not_connected', ['block_id' => $block->id, 'shop_id' => $block->shop_id]);
                return $emptyResponse('Widget found but store is not connected. Reinstall the app for this store.');
            }
            $this->logExt('checkout_offers_block_not_found', ['block_id' => $blockIdInt]);
            $otherBlock = Block::find($blockIdInt);
            if ($otherBlock) {
                return $emptyResponse(
                    'Widget ID '.$blockIdInt.' exists but is for "'.ucfirst(str_replace('_', ' ', $otherBlock->surface)).'", not Checkout. In Admin → Widgets create a widget with Surface = Checkout (and Type = Upsell), then put its ID in this block settings.'
                );
            }
            return $emptyResponse(
                'No widget with ID '.$blockIdInt.'. In Admin → Widgets open the list, check the ID column for a Checkout Upsell widget, and enter that number in "Widget ID" here.'
            );
        }

        $shop = $this->resolveShop($request);
        if (! $shop) {
            $this->logExt('checkout_offers_shop_not_found', ['block_id' => $blockId]);
            return $emptyResponse('Shop not found. Set Shop domain in block settings to your store (e.g. mystore.myshopify.com).');
        }

        // Experience-only mode (no block_id): return empty offers without requiring Placement.
        return $emptyResponse();
    }

    /**
     * Upgrade card: return payload (enabled, headline, description, items, plans, cta_label, actions)
     * for the checkout-upgrade-card extension. Resolves block by block_id (surface=checkout, type=checkout_upgrade_card).
     */
    public function upgradeCard(Request $request): JsonResponse
    {
        $blockId = $request->input('block_id');
        $this->logExt('checkout_upgrade_card_request', [
            'block_id' => $blockId,
            'shop' => $request->input('shop'),
            'line_items_count' => is_array($request->input('line_items')) ? count((array) $request->input('line_items')) : 0,
        ]);

        $empty = fn () => response()->json([
            'enabled' => false,
            'items' => [],
            'plans' => [],
            'actions' => [],
        ]);

        if ($blockId === null || $blockId === '') {
            $this->logExt('checkout_upgrade_card_missing_block_id', []);
            return $empty();
        }
        $blockIdInt = (int) $blockId;
        if ($blockIdInt < 1) {
            $this->logExt('checkout_upgrade_card_invalid_block_id', ['block_id' => $blockId]);
            return $empty();
        }

        $block = Block::where('surface', 'checkout')->where('type', 'checkout_upgrade_card')->find($blockIdInt);
        if (! $block) {
            $this->logExt('checkout_upgrade_card_block_not_found', ['block_id' => $blockIdInt]);
            return $empty();
        }

        $shop = $block->shop;
        if (! $shop || $shop->uninstalled_at !== null) {
            $this->logExt('checkout_upgrade_card_shop_not_connected', ['block_id' => $block->id]);
            return $empty();
        }

        $context = $this->buildContext($request);
        if ($block->rule_id) {
            $rule = $block->rule;
            if (! $rule || ! $this->ruleEngine->evaluate($rule->conditions, $context)) {
                $this->logExt('checkout_upgrade_card_rule_failed', ['block_id' => $block->id]);
                return $empty();
            }
        }

        $config = $block->config ?? [];
        $matcher = app(CartLineUpgradeMatcher::class);
        $payload = $matcher->run($config, $context);

        $vars = $this->buildTemplateVars($context, $config);
        if ($vars !== []) {
            foreach (['headline', 'description', 'cta_label'] as $key) {
                if (isset($payload[$key]) && is_string($payload[$key])) {
                    $payload[$key] = $this->interpolateValueRecursive($payload[$key], $vars);
                }
            }
        }

        $this->logExt('checkout_upgrade_card_response', [
            'block_id' => $block->id,
            'enabled' => $payload['enabled'] ?? false,
            'items_count' => count($payload['items'] ?? []),
            'actions_count' => count($payload['actions'] ?? []),
        ]);

        return response()->json($payload);
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
                $this->logExt('checkout_offers_block_rule_failed', ['block_id' => $block->id, 'rule_id' => $block->rule_id]);
                return response()->json([
                    'offers' => [],
                    'blocks' => [],
                    'ui' => [],
                    'resolved_block_id' => $block->id,
                    'is_ai_generated_widget' => $this->isAiGeneratedBlock($block),
                ]);
            }
        }

        $type = (string) $block->type;
        $typeLower = strtolower($type);
        $config = $block->config ?? [];

        if ($type === 'upsell') {
            $this->logExt('checkout_offers_block_upsell_start', ['block_id' => $block->id]);
            try {
                $payload = $this->buildUpsellPayloadForBlock($block, $shop, $context, $request);
                $this->logExt('checkout_offers_block_upsell_response', [
                    'block_id' => $block->id,
                    'shop_id' => $shop->id,
                    'offer_ids_count' => count($block->getOfferIds()),
                    'returned_count' => count($payload['offers'] ?? []),
                    'block_error' => $payload['block_error'] ?? null,
                ]);

                return response()->json($payload);
            } catch (\Throwable $e) {
                $this->logExt('checkout_offers_block_upsell_error', [
                    'block_id' => $block->id,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'offers' => [],
                    'blocks' => [],
                    'ui' => [],
                    'block_error' => 'Temporary error loading offers. Please try again.',
                ]);
            }
        }

        if ($type === 'progress_bar') {
            $ui = $this->buildUiFromBlockConfig($config, true);
            $ui = $this->interpolateTemplateData($ui, $context, (array) $config);

            $this->logExt('checkout_offers_block_progress_bar_response', [
                'block_id' => $block->id,
                'shop_id' => $shop->id,
                'goal' => $ui['progress_bar']['goal'] ?? null,
            ]);

            return response()->json([
                'offers' => [],
                'display_mode' => 'stacked',
                'ui' => $ui,
                'resolved_block_id' => $block->id,
                'is_ai_generated_widget' => $this->isAiGeneratedBlock($block),
            ]);
        }

        if (str_starts_with($typeLower, 'content_')) {
            $blocksPayload = [
                [
                    'id' => $block->id,
                    'type' => $typeLower,
                    'config' => $this->interpolateTemplateData($config, $context, (array) $config),
                ],
            ];

            $this->logExt('checkout_offers_block_content_response', [
                'block_id' => $block->id,
                'shop_id' => $shop->id,
                'type' => $typeLower,
                'config_keys' => array_keys((array) $config),
            ]);

            return response()->json([
                'offers' => [],
                'blocks' => $blocksPayload,
                'display_mode' => 'stacked',
                'ui' => [],
                'resolved_block_id' => $block->id,
                'is_ai_generated_widget' => $this->isAiGeneratedBlock($block),
            ]);
        }

        $this->logExt('checkout_offers_block_unknown_type', ['block_id' => $block->id, 'type' => $type]);
        return response()->json([
            'offers' => [],
            'blocks' => [],
            'ui' => [],
            'resolved_block_id' => $block->id,
            'is_ai_generated_widget' => $this->isAiGeneratedBlock($block),
        ]);
    }

    /**
     * Build upsell payload for a block (for API response or admin health check).
     * When checkout_experience_id is sent in the request and valid, quantity/subscription_upgrade use that experience; otherwise they are disabled.
     *
     * @param  array<string, mixed>  $context  Request context (subtotal, line_items, etc.)
     * @return array{offers: array, display_mode: string, ui: array, quantity: array, subscription_upgrade: array, block_error?: string}
     */
    public function buildUpsellPayloadForBlock(Block $block, Shop $shop, array $context = [], ?Request $request = null): array
    {
        $config = $block->config ?? [];
        $offerIds = $block->getOfferIds();
        $maxOffers = (int) ($config['max_offers'] ?? 3);
        $eligible = $this->findEligibleOffers($shop, $offerIds, $context, $maxOffers);
        $data = $this->enrichOffersFromShopify($shop, $eligible);
        $ui = $this->buildUiFromBlockConfig($config, false);
        $data = $this->interpolateTemplateData($data, $context, (array) $config);
        $ui = $this->interpolateTemplateData($ui, $context, (array) $config);

        $displayMode = (string) ($config['display_mode'] ?? 'stacked');
        $payload = [
            'offers' => $data,
            'display_mode' => $displayMode,
            'ui' => $ui,
            'resolved_block_id' => $block->id,
            'is_ai_generated_widget' => $this->isAiGeneratedBlock($block),
        ];
        if (count($data) === 0 && count($offerIds) === 0) {
            $payload['block_error'] = 'Widget '.$block->id.' found but has no offers. Add offers in Admin → Widgets for this widget.';
        }

        $expId = $request ? (int) $request->input('checkout_experience_id') : 0;
        if ($expId > 0) {
            $experience = CheckoutExperience::where('shop_id', $shop->id)->find($expId);
        } else {
            $experience = $shop->checkoutExperience;
        }
        $quantityPayload = $experience ? $experience->quantityPayload() : ['enabled' => false, 'default' => 1, 'min' => 1, 'max' => 10];
        if (isset($config['show_quantity']) && $config['show_quantity'] === false) {
            $quantityPayload['enabled'] = false;
        }
        $payload['quantity'] = $quantityPayload;
        $payload['subscription_upgrade'] = $experience ? $experience->subscriptionUpgradePayload() : ['enabled' => false, 'headline' => '', 'cta' => 'Upgrade to subscription'];

        return $payload;
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
                            $variantImageUrl = null;
                            $mediaNodes = $variant['media']['nodes'] ?? [];
                            if (is_array($mediaNodes) && isset($mediaNodes[0]['image']['url'])) {
                                $variantImageUrl = (string) $mediaNodes[0]['image']['url'];
                            }
                            $imageUrl = (string) ($variantImageUrl ?? $variant['product']['featuredImage']['url'] ?? '');
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
                'discount_value' => $o->discount_value === null ? null : (is_object($o->discount_value) && method_exists($o->discount_value, 'toString') ? $o->discount_value->toString() : (string) $o->discount_value),
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

    /**
     * Store checkout experience ID for this session (called by the widget when "Checkout Experience ID" is set).
     * checkout_experience_id = ID from Admin → Checkout experience (/admin/checkout-experiences), i.e. CheckoutExperience.id.
     * Not the Block/Widget ID. Key: checkout_experience:{shop}:{session_key}, TTL 1 hour.
     */
    public function setExperience(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['ok' => false, 'message' => 'Shop not found'], 400);
        }
        $sessionKey = $request->input('session_key') ?? $request->query('session_key') ?? '';
        $experienceId = (int) ($request->input('checkout_experience_id') ?? $request->query('checkout_experience_id') ?? 0);
        if ($sessionKey === '' || $experienceId < 1) {
            return response()->json(['ok' => false, 'message' => 'session_key and checkout_experience_id required'], 400);
        }
        // Load the Checkout Experience record from admin/checkout-experiences (same ID as in the URL edit page).
        $experience = CheckoutExperience::where('shop_id', $shop->id)->find($experienceId);
        if (! $experience) {
            return response()->json(['ok' => false, 'message' => 'Checkout Experience not found for this shop'], 404);
        }
        $cacheKey = 'checkout_experience:'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $shop->shop_domain ?? (string) $shop->id).':'.$sessionKey;
        Cache::put($cacheKey, $experienceId, now()->addHour());
        $this->logExt('checkout_experience_set', ['shop_id' => $shop->id, 'experience_id' => $experienceId, 'session_key_preview' => substr($sessionKey, 0, 12).'…']);
        return response()->json(['ok' => true]);
    }

    /**
     * Return checkout experience config for cart-line-item extension (quantity on lines, subscription upgrade).
     * checkout_experience_id = ID from Admin → Checkout experience (/admin/checkout-experiences), not Block/Widget ID.
     * When the extension sends line_product_id, line_variant_id, line_has_selling_plan, cart_subtotal, cart_items_count,
     * we evaluate rules and return show_quantity / show_subscription for that line; otherwise global flags.
     */
    public function experience(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        $defaultCartLineUi = $this->defaultCartLineUiPayload();
        if (! $shop) {
            return response()->json([
                'quantity_in_cart_enabled' => false,
                'show_quantity' => false,
                'show_subscription' => false,
                'subscription_upgrade' => ['enabled' => false, 'headline' => '', 'cta' => 'Upgrade to subscription'],
                'cart_line_ui' => $defaultCartLineUi,
                'cart_line_actions' => [],
            ]);
        }
        $experience = $this->resolveExperience($request, $shop);
        $quantityInCartEnabled = $experience ? (bool) $experience->quantity_in_cart_enabled : false;
        $subscriptionUpgrade = $experience ? $experience->subscriptionUpgradePayload() : ['enabled' => false, 'headline' => '', 'cta' => 'Upgrade to subscription'];
        $cartLineUi = $experience ? $experience->cartLineUiPayload() : $defaultCartLineUi;

        $lineProductId = $request->input('line_product_id') ?? $request->query('line_product_id');
        $lineVariantId = $request->input('line_variant_id') ?? $request->query('line_variant_id');
        $lineHasSellingPlan = (bool) ($request->input('line_has_selling_plan') ?? $request->query('line_has_selling_plan') ?? false);
        $cartSubtotal = $request->input('cart_subtotal') ?? $request->query('cart_subtotal');
        $cartSubtotal = $cartSubtotal !== null && $cartSubtotal !== '' ? (float) $cartSubtotal : null;
        $cartItemsCount = $request->input('cart_items_count') ?? $request->query('cart_items_count');
        $cartItemsCount = $cartItemsCount !== null && $cartItemsCount !== '' ? (int) $cartItemsCount : null;

        $showQuantity = $quantityInCartEnabled;
        $showSubscription = (bool) ($subscriptionUpgrade['enabled'] ?? false);
        $cartLineActionsPayload = [];

        if ($experience && $lineProductId !== null && $lineProductId !== '') {
            $productIdGid = app(ShopifyGraphQLService::class)->normalizeProductIdToGid(trim((string) $lineProductId));
            if ($productIdGid === '' && $lineVariantId !== null && $lineVariantId !== '') {
                $variantGid = $this->normalizeVariantIdToGid(trim((string) $lineVariantId));
                if ($variantGid !== '') {
                    try {
                        $variant = app(ShopifyGraphQLService::class)->getProductVariant($shop, $variantGid);
                        if (isset($variant['product']['id'])) {
                            $productIdGid = (string) $variant['product']['id'];
                        }
                    } catch (\Throwable) {
                        // Keep productIdGid empty
                    }
                }
            }
            if ($productIdGid !== '') {
                $productMetadata = app(ShopifyGraphQLService::class)->getProductMetadata($shop, $productIdGid);
                $showQuantity = CartLineRulesEvaluator::showQuantity(
                    $experience,
                    $productIdGid,
                    $productMetadata,
                    $cartSubtotal,
                    $cartItemsCount,
                    $lineHasSellingPlan
                );
                $showSubscription = CartLineRulesEvaluator::showSubscription(
                    $experience,
                    $productIdGid,
                    $productMetadata,
                    $cartSubtotal,
                    $cartItemsCount,
                    $lineHasSellingPlan
                );
                foreach ($experience->cartLineActions as $action) {
                    if (CartLineRulesEvaluator::actionMatchesLine(
                        $action,
                        $productIdGid,
                        $productMetadata,
                        $cartSubtotal,
                        $cartItemsCount,
                        $lineHasSellingPlan
                    )) {
                        $cartLineActionsPayload[] = [
                            'id' => $action->id,
                            'label' => $action->label,
                            'message' => $action->message ?: null,
                            'action_type' => $action->action_type,
                            'target_variant_gid' => $action->target_variant_gid ?: null,
                            'target_quantity' => (int) $action->target_quantity,
                            'target_selling_plan_id' => $action->target_selling_plan_id ?: null,
                        ];
                    }
                }
            }
        }

        $this->logExt('checkout_experience_response', [
            'shop_id' => $shop->id,
            'experience_id' => $experience?->id,
            'quantity_in_cart_enabled' => $quantityInCartEnabled,
            'show_quantity' => $showQuantity,
            'show_subscription' => $showSubscription,
        ]);

        return response()->json([
            'quantity_in_cart_enabled' => $quantityInCartEnabled,
            'show_quantity' => $showQuantity,
            'show_subscription' => $showSubscription,
            'subscription_upgrade' => $subscriptionUpgrade,
            'cart_line_ui' => $cartLineUi,
            'cart_line_actions' => $cartLineActionsPayload,
        ]);
    }

    /**
     * Default cart_line_ui when no experience (fallback).
     *
     * @return array<string, mixed>
     */
    protected function defaultCartLineUiPayload(): array
    {
        return [
            'modify_alignment' => 'left',
            'show_chevron' => true,
            'quantity_size' => 'medium',
            'popover_width' => ['mode' => 'preset', 'preset' => 'md', 'px' => null, 'padding_x' => 'base'],
            'quantity_label' => ['text' => 'Quantity', 'size' => 'medium', 'alignment' => 'left'],
            'plus_minus' => ['kind' => 'plain', 'appearance' => 'monochrome', 'size' => 'small', 'corner_radius' => 'base'],
        ];
    }

    /**
     * Resolve CheckoutExperience from request (explicit ID, session cache, or shop default).
     */
    protected function resolveExperience(Request $request, Shop $shop): ?CheckoutExperience
    {
        $experienceId = (int) ($request->input('checkout_experience_id') ?? $request->query('checkout_experience_id') ?? 0);
        if ($experienceId > 0) {
            $experience = CheckoutExperience::where('shop_id', $shop->id)->find($experienceId);
            if ($experience) {
                return $experience;
            }
        }
        $sessionKey = $request->input('session_key') ?? $request->query('session_key') ?? '';
        if ($sessionKey !== '') {
            $cacheKey = 'checkout_experience:'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $shop->shop_domain ?? (string) $shop->id).':'.$sessionKey;
            $storedId = Cache::get($cacheKey);
            if ($storedId !== null) {
                $experience = CheckoutExperience::where('shop_id', $shop->id)->find((int) $storedId);
                if ($experience) {
                    return $experience;
                }
            }
        }

        return $shop->checkoutExperience;
    }

    /**
     * Return selling plans available for a variant (for "upgrade to subscription" in cart-line-item).
     */
    public function sellingPlansForVariant(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['selling_plans' => []], 200);
        }
        $variantId = $request->input('variant_id') ?? $request->query('variant_id') ?? '';
        $variantGid = $this->normalizeVariantIdToGid($variantId);
        if ($variantGid === '') {
            return response()->json(['selling_plans' => []], 200);
        }
        try {
            $service = app(ShopifyGraphQLService::class);
            $plans = $service->getSellingPlansForVariant($shop, $variantGid);
            return response()->json(['selling_plans' => $plans]);
        } catch (\Throwable $e) {
            Log::channel('checkout_extension')->warning('selling_plans_for_variant_error', [
                'shop_id' => $shop->id,
                'variant_id' => $variantGid,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['selling_plans' => []], 200);
        }
    }

    protected function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->input('shop') ?? $request->query('shop') ?? $request->header('X-Shop-Domain');
        if (! $shopDomain) {
            return null;
        }
        return Shop::findByDomainOrAlternates($shopDomain);
    }

    protected function isAiGeneratedBlock(Block $block): bool
    {
        return trim((string) ($block->ai_generated_php ?? '')) !== ''
            || trim((string) ($block->ai_generated_description ?? '')) !== ''
            || trim((string) ($block->ai_prompt ?? '')) !== '';
    }

    /**
     * Interpolate placeholders like {dog_name} using line item properties.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function interpolateTemplateData(mixed $value, array $context, array $blockConfig = []): mixed
    {
        $vars = $this->buildTemplateVars($context, $blockConfig);
        if ($vars === []) {
            return $value;
        }

        return $this->interpolateValueRecursive($value, $vars);
    }

    /**
     * @param  array<string, string>  $vars
     * @return mixed
     */
    protected function interpolateValueRecursive(mixed $value, array $vars): mixed
    {
        if (is_string($value)) {
            return preg_replace_callback('/\{([^}]+)\}/', function (array $matches) use ($vars): string {
                $rawToken = trim((string) ($matches[1] ?? ''));
                if ($rawToken === '') {
                    return (string) $matches[0];
                }

                $rawLower = mb_strtolower($rawToken);
                if (str_starts_with($rawLower, 'property:') || str_starts_with($rawLower, 'prop:')) {
                    $parts = explode(':', $rawToken, 2);
                    $propertyName = trim((string) ($parts[1] ?? ''));
                    $lookup = 'prop:' . mb_strtolower($propertyName);

                    return array_key_exists($lookup, $vars)
                        ? (string) $vars[$lookup]
                        : (string) $matches[0];
                }

                $token = $this->normalizeTemplateKey($rawToken);
                if ($token === '') {
                    return (string) $matches[0];
                }

                return array_key_exists($token, $vars)
                    ? (string) $vars[$token]
                    : (string) $matches[0];
            }, $value) ?? $value;
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->interpolateValueRecursive($v, $vars);
            }
            return $out;
        }

        return $value;
    }

    /**
     * Build template variable map from line_items[].properties/attributes/customAttributes.
     *
     * @return array<string, string>
     */
    protected function lineItemPropertyTemplateVars(array $context): array
    {
        $vars = [];
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        if (! is_array($lines)) {
            return $vars;
        }

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $properties = $this->extractLineItemProperties($line);
            foreach ($properties as $key => $val) {
                $normalized = $this->normalizeTemplateKey($key);
                if ($normalized === '' || $val === '') {
                    continue;
                }
                if (! array_key_exists($normalized, $vars)) {
                    $vars[$normalized] = $val;
                }
                $exactKeyLookup = 'prop:' . mb_strtolower(trim((string) $key));
                if ($exactKeyLookup !== 'prop:' && ! array_key_exists($exactKeyLookup, $vars)) {
                    $vars[$exactKeyLookup] = $val;
                }
            }
        }

        return $vars;
    }

    /**
     * Build full template var map: line item properties + computed runtime variables from block config.
     *
     * Block config can define `runtime_variables` (array) which produces additional placeholders
     * like `{dog_names_message}` at runtime (server-side).
     *
     * @return array<string, string>
     */
    protected function buildTemplateVars(array $context, array $blockConfig = []): array
    {
        $vars = $this->lineItemPropertyTemplateVars($context);

        $runtimeDefs = $blockConfig['runtime_variables'] ?? $blockConfig['runtimeVariables'] ?? null;
        if (is_array($runtimeDefs) && $runtimeDefs !== []) {
            $computed = app(RuntimeTemplateVarsService::class)->compute($runtimeDefs, $context);
            foreach ($computed as $key => $val) {
                $normalized = $this->normalizeTemplateKey((string) $key);
                if ($normalized !== '') {
                    $vars[$normalized] = (string) $val;
                }
            }
        }

        return $vars;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, string>
     */
    protected function extractLineItemProperties(array $line): array
    {
        $candidates = [
            $line['properties'] ?? null,
            $line['attributes'] ?? null,
            $line['customAttributes'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $isAssoc = array_keys($candidate) !== range(0, count($candidate) - 1);
            if ($isAssoc) {
                $out = [];
                foreach ($candidate as $k => $v) {
                    $key = trim((string) $k);
                    $value = trim((string) $v);
                    if ($key !== '' && $value !== '') {
                        $out[$key] = $value;
                    }
                }
                if ($out !== []) {
                    return $out;
                }
                continue;
            }

            $out = [];
            foreach ($candidate as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = trim((string) ($row['key'] ?? $row['name'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($key !== '' && $value !== '') {
                    $out[$key] = $value;
                }
            }
            if ($out !== []) {
                return $out;
            }
        }

        return [];
    }

    protected function normalizeTemplateKey(string $key): string
    {
        $normalized = strtolower(trim($key));
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?? '';
        $normalized = preg_replace('/_+/', '_', $normalized) ?? '';
        return trim($normalized, '_');
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
