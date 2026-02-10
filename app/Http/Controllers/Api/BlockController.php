<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ThankYouBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    /**
     * List thank you blocks for the shop.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }
        $blocks = ThankYouBlock::where('shop_id', $shop->id)->orderBy('sort_order')->orderBy('id')->get();
        return response()->json(['data' => $blocks]);
    }

    /**
     * Store a new block.
     */
    public function store(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }
        $validated = $request->validate([
            'type' => 'required|in:banner,text,button,product_card',
            'config' => 'required|array',
            'sort_order' => 'nullable|integer',
        ]);
        $validated['shop_id'] = $shop->id;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $block = ThankYouBlock::create($validated);
        return response()->json(['data' => $block], 201);
    }

    /**
     * Show single block.
     */
    public function show(Request $request, ThankYouBlock $block): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $block->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['data' => $block]);
    }

    /**
     * Update block. Route parameter name is 'block' for apiResource('blocks').
     */
    public function update(Request $request, ThankYouBlock $block): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $block->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $validated = $request->validate([
            'type' => 'sometimes|in:banner,text,button,product_card',
            'config' => 'sometimes|array',
            'sort_order' => 'nullable|integer',
        ]);
        $block->update($validated);
        return response()->json(['data' => $block->fresh()]);
    }

    /**
     * Delete block.
     */
    public function destroy(Request $request, ThankYouBlock $block): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $block->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $block->delete();
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
