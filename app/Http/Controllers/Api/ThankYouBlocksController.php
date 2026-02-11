<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use App\Models\Shop;
use App\Models\ThankYouBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThankYouBlocksController extends Controller
{
    /**
     * Return blocks to render on thank you / order status page.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response()->json(['error' => 'Shop not found'], 404);
        }

        $placement = Placement::where('shop_id', $shop->id)->where('placement_type', 'thank_you')->first();
        if (! $placement) {
            return response()->json(['blocks' => []]);
        }

        $blockIds = $placement->getBlockIds();
        if (empty($blockIds)) {
            $blocks = ThankYouBlock::where('shop_id', $shop->id)->orderBy('sort_order')->get();
        } else {
            $blocks = ThankYouBlock::where('shop_id', $shop->id)->whereIn('id', $blockIds)->get();
            $order = array_flip(array_map('intval', $blockIds));
            $blocks = $blocks->sortBy(fn (ThankYouBlock $b) => $order[$b->id] ?? 999)->values();
        }

        $data = $blocks->map(fn (ThankYouBlock $b) => [
            'id' => $b->id,
            'type' => $b->type,
            'config' => $b->config,
        ])->values()->all();

        return response()->json(['blocks' => $data]);
    }

    protected function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->query('shop') ?? $request->input('shop') ?? $request->header('X-Shop-Domain');
        if (! $shopDomain) {
            return null;
        }
        return Shop::findByDomainOrAlternates($shopDomain);
    }
}
