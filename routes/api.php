<?php

use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\CheckoutUpsellController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\PostPurchaseController;
use App\Http\Controllers\Api\PreviewController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\ThankYouBlocksController;
use App\Http\Controllers\Api\PlacementController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CORS preflight (OPTIONS) – must match before 404 so browser allows POST/GET
|--------------------------------------------------------------------------
*/
$corsPreflight = function () {
    return response('', 204)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Extension-Secret, X-Checkout-Extension-Secret, X-Shop-Domain');
};

Route::options('checkout/offers', $corsPreflight);
Route::options('checkout/logs', $corsPreflight);
Route::options('post-purchase/should-render', $corsPreflight);
Route::options('post-purchase/accept', $corsPreflight);
Route::options('thankyou/blocks', $corsPreflight);

/*
|--------------------------------------------------------------------------
| Extension endpoints (signed; CORS allowed for Shopify)
|--------------------------------------------------------------------------
*/
Route::middleware(['cors.extensions', 'checkout.extension.signature'])->group(function () {
    Route::post('/post-purchase/should-render', [PostPurchaseController::class, 'shouldRender']);
    Route::post('/post-purchase/accept', [PostPurchaseController::class, 'accept']);
    Route::get('/checkout/offers', [CheckoutUpsellController::class, 'index']);
    Route::post('/checkout/offers', [CheckoutUpsellController::class, 'index']);
    Route::post('/checkout/logs', [CheckoutUpsellController::class, 'log']);
    Route::get('/thankyou/blocks', [ThankYouBlocksController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Admin CRUD (session/OAuth; in production use auth middleware)
|--------------------------------------------------------------------------
*/
Route::middleware(['web'])->group(function () {
    Route::apiResource('offers', OfferController::class);
    Route::apiResource('rules', RuleController::class);
    Route::apiResource('blocks', BlockController::class);
    Route::apiResource('placements', PlacementController::class);
    Route::post('/preview/offer', [PreviewController::class, 'preview']);
});

/*
|--------------------------------------------------------------------------
| Webhooks (HMAC verified)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/app-uninstalled', [WebhookController::class, 'appUninstalled']);
Route::post('/webhooks/orders-create', [WebhookController::class, 'ordersCreate'])->name('webhooks.orders.create');
