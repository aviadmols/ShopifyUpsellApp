<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlacementController extends Controller
{
    /**
     * List placements for the shop.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }
        $placements = Placement::where('shop_id', $shop->id)->orderBy('placement_type')->get();
        return response()->json(['data' => $placements]);
    }

    /**
     * Store a new placement.
     */
    public function store(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }
        $validated = $request->validate([
            'placement_type' => 'required|in:checkout,post_purchase,thank_you',
            'config' => 'required|array',
        ]);
        $validated['shop_id'] = $shop->id;
        $placement = Placement::create($validated);
        return response()->json(['data' => $placement], 201);
    }

    /**
     * Show single placement.
     */
    public function show(Request $request, Placement $placement): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $placement->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['data' => $placement]);
    }

    /**
     * Update placement.
     */
    public function update(Request $request, Placement $placement): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $placement->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $validated = $request->validate([
            'placement_type' => 'sometimes|in:checkout,post_purchase,thank_you',
            'config' => 'sometimes|array',
        ]);
        $placement->update($validated);
        return response()->json(['data' => $placement->fresh()]);
    }

    /**
     * Delete placement.
     */
    public function destroy(Request $request, Placement $placement): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $placement->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $placement->delete();
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
