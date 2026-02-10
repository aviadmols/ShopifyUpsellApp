<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    /**
     * List offers for the shop (shop from query or session).
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }
        $offers = Offer::where('shop_id', $shop->id)->with('rule:id,name')->orderBy('id')->get();
        return response()->json(['data' => $offers]);
    }

    /**
     * Store a new offer.
     */
    public function store(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'product_variant_id' => 'required|string',
            'discount_type' => 'in:none,percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:500',
            'rule_id' => 'nullable|exists:rules,id',
        ]);
        $validated['shop_id'] = $shop->id;
        $offer = Offer::create($validated);
        return response()->json(['data' => $offer], 201);
    }

    /**
     * Show single offer.
     */
    public function show(Request $request, Offer $offer): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $offer->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['data' => $offer->load('rule')]);
    }

    /**
     * Update offer.
     */
    public function update(Request $request, Offer $offer): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $offer->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'product_variant_id' => 'sometimes|string',
            'discount_type' => 'in:none,percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:500',
            'rule_id' => 'nullable|exists:rules,id',
        ]);
        $offer->update($validated);
        return response()->json(['data' => $offer->fresh()]);
    }

    /**
     * Delete offer.
     */
    public function destroy(Request $request, Offer $offer): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $offer->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $offer->delete();
        return response()->json(null, 204);
    }

    protected function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->query('shop') ?? $request->input('shop') ?? session('shop_domain');
        if (! $shopDomain) {
            return null;
        }
        return Shop::where('shop_domain', $shopDomain)->whereNull('uninstalled_at')->first();
    }
}
