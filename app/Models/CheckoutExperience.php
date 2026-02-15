<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutExperience extends Model
{
    protected $fillable = [
        'shop_id',
        'quantity_in_upsell_enabled',
        'quantity_default',
        'quantity_min',
        'quantity_max',
        'quantity_in_cart_enabled',
        'cart_line_modify_alignment',
        'cart_line_show_chevron',
        'cart_line_quantity_size',
        'subscription_upgrade_enabled',
        'subscription_upgrade_headline',
        'subscription_upgrade_cta',
    ];

    protected function casts(): array
    {
        return [
            'quantity_in_upsell_enabled' => 'bool',
            'quantity_in_cart_enabled' => 'bool',
            'cart_line_show_chevron' => 'bool',
            'subscription_upgrade_enabled' => 'bool',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get quantity config for API (upsell block).
     */
    public function quantityPayload(): array
    {
        $min = max(1, (int) $this->quantity_min);
        $max = max($min, min(100, (int) $this->quantity_max));
        $default = max($min, min($max, (int) $this->quantity_default));

        return [
            'enabled' => (bool) $this->quantity_in_upsell_enabled,
            'default' => $default,
            'min' => $min,
            'max' => $max,
        ];
    }

    /**
     * Get subscription upgrade config for API.
     */
    public function subscriptionUpgradePayload(): array
    {
        return [
            'enabled' => (bool) $this->subscription_upgrade_enabled,
            'headline' => (string) ($this->subscription_upgrade_headline ?? ''),
            'cta' => (string) ($this->subscription_upgrade_cta ?? 'Upgrade to subscription'),
        ];
    }

    /**
     * Get cart line UI config for API (Modify alignment, chevron, quantity size).
     */
    public function cartLineUiPayload(): array
    {
        $alignment = $this->cart_line_modify_alignment ?? 'left';
        if (! in_array($alignment, ['left', 'center', 'right'], true)) {
            $alignment = 'left';
        }
        $size = $this->cart_line_quantity_size ?? 'medium';
        if (! in_array($size, ['small', 'medium', 'large'], true)) {
            $size = 'medium';
        }

        return [
            'modify_alignment' => $alignment,
            'show_chevron' => (bool) ($this->cart_line_show_chevron ?? true),
            'quantity_size' => $size,
        ];
    }
}
