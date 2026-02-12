<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Services\RuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function __construct(
        protected RuleEngine $ruleEngine
    ) {}

    /**
     * Return first active survey for shop+surface that matches conditions.
     */
    public function active(Request $request): JsonResponse
    {
        $shopDomain = (string) ($request->input('shop') ?? $request->query('shop') ?? $request->header('X-Shop-Domain') ?? '');
        $surface = (string) ($request->input('surface') ?? $request->query('surface') ?? '');
        $surface = strtolower(trim($surface));

        if ($shopDomain === '' || $surface === '') {
            return response()->json(['survey' => null], 200);
        }

        $shop = Shop::findByDomainOrAlternates($shopDomain);
        if (! $shop) {
            return response()->json(['survey' => null], 200);
        }

        $context = $this->buildContext($request);

        $surveys = Survey::where('shop_id', $shop->id)
            ->where('enabled', true)
            ->orderBy('id')
            ->get();

        foreach ($surveys as $survey) {
            $surfaces = is_array($survey->surfaces) ? $survey->surfaces : [];
            if ($surfaces !== [] && ! in_array($surface, $surfaces, true)) {
                continue;
            }

            $conditions = is_array($survey->conditions) ? $survey->conditions : [];
            if (! empty($conditions) && ! $this->ruleEngine->evaluate($conditions, $context)) {
                continue;
            }

            return response()->json([
                'survey' => [
                    'id' => $survey->id,
                    'name' => (string) $survey->name,
                    'ui' => is_array($survey->ui) ? $survey->ui : [],
                    'questions' => is_array($survey->questions) ? $survey->questions : [],
                    'reward' => [
                        'type' => (string) ($survey->reward_type ?? 'code'),
                        'code' => (string) ($survey->reward_code ?? ''),
                        'message' => (string) ($survey->reward_message ?? ''),
                    ],
                ],
            ], 200);
        }

        return response()->json(['survey' => null], 200);
    }

    /**
     * Store a survey response.
     */
    public function respond(Request $request): JsonResponse
    {
        $shopDomain = (string) ($request->input('shop') ?? $request->header('X-Shop-Domain') ?? '');
        $surface = strtolower(trim((string) ($request->input('surface') ?? 'checkout')));
        $surveyId = (int) ($request->input('survey_id') ?? 0);
        $answers = $request->input('answers');

        if ($shopDomain === '' || $surveyId < 1 || ! is_array($answers)) {
            return response()->json(['ok' => false, 'error' => 'Invalid payload'], 422);
        }

        $shop = Shop::findByDomainOrAlternates($shopDomain);
        if (! $shop) {
            return response()->json(['ok' => false, 'error' => 'Shop not found'], 404);
        }

        $survey = Survey::where('shop_id', $shop->id)->find($surveyId);
        if (! $survey) {
            return response()->json(['ok' => false, 'error' => 'Survey not found'], 404);
        }

        $orderId = $request->input('order_id') ?? $request->input('order.id') ?? null;
        $checkoutToken = $request->input('checkout_token') ?? $request->input('checkoutToken') ?? null;
        $customerId = $request->input('customer_id') ?? $request->input('customer.id') ?? null;

        $existing = null;
        if (is_string($orderId) && trim($orderId) !== '') {
            $existing = SurveyResponse::where('survey_id', $survey->id)->where('order_id', (string) $orderId)->first();
        } elseif (is_string($checkoutToken) && trim($checkoutToken) !== '') {
            $existing = SurveyResponse::where('survey_id', $survey->id)->where('checkout_token', (string) $checkoutToken)->first();
        }

        $response = $existing ?? new SurveyResponse();
        $response->survey_id = $survey->id;
        $response->shop_id = $shop->id;
        $response->surface = $surface;
        $response->order_id = is_string($orderId) ? trim($orderId) : null;
        $response->checkout_token = is_string($checkoutToken) ? trim($checkoutToken) : null;
        $response->customer_id = is_string($customerId) ? trim($customerId) : null;
        $response->answers = $answers;
        $response->reward_code_shown = (string) ($survey->reward_code ?? '');
        $response->save();

        return response()->json([
            'ok' => true,
            'reward' => [
                'type' => (string) ($survey->reward_type ?? 'code'),
                'code' => (string) ($survey->reward_code ?? ''),
                'message' => (string) ($survey->reward_message ?? ''),
            ],
        ], 200);
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
            'utms' => is_array($request->input('utms')) ? (array) $request->input('utms') : [],
            'url_params' => is_array($request->input('url_params')) ? (array) $request->input('url_params') : [],
        ];
    }
}

