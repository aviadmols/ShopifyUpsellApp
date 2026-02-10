<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    /**
     * List rules for the shop.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }
        $rules = Rule::where('shop_id', $shop->id)->orderBy('id')->get();
        return response()->json(['data' => $rules]);
    }

    /**
     * Store a new rule.
     */
    public function store(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop required'], 422);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'conditions' => 'required|array',
        ]);
        $validated['shop_id'] = $shop->id;
        $rule = Rule::create($validated);
        return response()->json(['data' => $rule], 201);
    }

    /**
     * Show single rule.
     */
    public function show(Request $request, Rule $rule): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $rule->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['data' => $rule]);
    }

    /**
     * Update rule.
     */
    public function update(Request $request, Rule $rule): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $rule->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'conditions' => 'sometimes|array',
        ]);
        $rule->update($validated);
        return response()->json(['data' => $rule->fresh()]);
    }

    /**
     * Delete rule.
     */
    public function destroy(Request $request, Rule $rule): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop || $rule->shop_id !== $shop->id) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $rule->delete();
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
