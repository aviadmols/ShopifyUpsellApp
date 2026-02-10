<?php

namespace App\Services;

use App\Models\PostPurchaseLog;
use App\Models\Shop;

/**
 * Ensures the same order cannot accept the same post-purchase offer twice.
 */
class PostPurchaseIdempotencyService
{
    /**
     * Generate a unique idempotency key for order + offer.
     */
    public function generateKey(string $orderId, int $offerId): string
    {
        return "pp_{$orderId}_{$offerId}";
    }

    /**
     * Check if this order+offer was already accepted.
     */
    public function alreadyAccepted(Shop $shop, string $orderId, int $offerId): bool
    {
        $key = $this->generateKey($orderId, $offerId);

        return PostPurchaseLog::where('shop_id', $shop->id)
            ->where('idempotency_key', $key)
            ->where('event', 'accept')
            ->exists();
    }

    /**
     * Record an accept event and store idempotency key.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordAccept(Shop $shop, string $orderId, int $offerId, array $payload = []): void
    {
        $key = $this->generateKey($orderId, $offerId);
        PostPurchaseLog::create([
            'shop_id' => $shop->id,
            'order_id' => $orderId,
            'offer_id' => $offerId,
            'event' => 'accept',
            'idempotency_key' => $key,
            'payload' => $payload,
        ]);
    }

    /**
     * Log impression (should-render) or decline.
     *
     * @param  array<string, mixed>  $payload
     */
    public function logEvent(Shop $shop, string $orderId, int $offerId, string $event, array $payload = []): void
    {
        PostPurchaseLog::create([
            'shop_id' => $shop->id,
            'order_id' => $orderId,
            'offer_id' => $offerId,
            'event' => $event,
            'idempotency_key' => null,
            'payload' => $payload,
        ]);
    }
}
