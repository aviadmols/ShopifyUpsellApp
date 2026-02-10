<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Shop;
use App\Services\RuleEngine;
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

        $offerIds = $placement->config['offer_ids'] ?? [];
        $maxOffers = (int) ($placement->config['max_offers'] ?? 3);
        $context = $this->buildContext($request);

        $eligible = $this->findEligibleOffers($shop, $offerIds, $context, $maxOffers);
        $data = array_map(fn (Offer $o) => [
            'id' => $o->id,
            'title' => $o->title,
            'description' => $o->description,
            'variant_id' => $o->product_variant_id,
            'discount_type' => $o->discount_type,
            'discount_value' => $o->discount_value?->toString(),
            'image_url' => $o->image_url,
        ], $eligible);

        return response()->json(['offers' => $data]);
    }

    protected function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->input('shop') ?? $request->query('shop') ?? $request->header('X-Shop-Domain');
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
            'subtotal' => $request->input('subtotal') ?? $request->input('cart.subtotal') ?? 0,
            'line_items' => $request->input('line_items') ?? $request->input('cart.line_items') ?? $request->input('lineItems') ?? [],
            'customer' => $request->input('customer') ?? [],
            'shipping_country' => $request->input('shipping_country') ?? $request->input('shippingAddress.countryCode') ?? null,
        ];
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
