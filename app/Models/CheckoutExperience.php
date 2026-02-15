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
        'cart_line_popover_width_mode',
        'cart_line_popover_width_preset',
        'cart_line_popover_width_px',
        'cart_line_plus_minus_kind',
        'cart_line_plus_minus_appearance',
        'cart_line_plus_minus_size',
        'cart_line_plus_minus_corner_radius',
        'subscription_upgrade_enabled',
        'subscription_upgrade_headline',
        'subscription_upgrade_cta',
        'quantity_rule_mode',
        'quantity_include_product_ids',
        'quantity_exclude_product_ids',
        'quantity_include_collection_ids',
        'quantity_exclude_collection_ids',
        'quantity_include_tags',
        'quantity_exclude_tags',
        'quantity_include_vendors',
        'quantity_exclude_vendors',
        'quantity_include_product_types',
        'quantity_exclude_product_types',
        'quantity_require_subscription_state',
        'quantity_min_subtotal',
        'quantity_max_subtotal',
        'quantity_min_cart_items',
        'quantity_max_cart_items',
        'subscription_rule_mode',
        'subscription_include_product_ids',
        'subscription_exclude_product_ids',
        'subscription_include_collection_ids',
        'subscription_exclude_collection_ids',
        'subscription_include_tags',
        'subscription_exclude_tags',
        'subscription_include_vendors',
        'subscription_exclude_vendors',
        'subscription_include_product_types',
        'subscription_exclude_product_types',
        'subscription_require_subscription_state',
        'subscription_min_subtotal',
        'subscription_max_subtotal',
        'subscription_min_cart_items',
        'subscription_max_cart_items',
    ];

    protected function casts(): array
    {
        return [
            'quantity_in_upsell_enabled' => 'bool',
            'quantity_in_cart_enabled' => 'bool',
            'cart_line_show_chevron' => 'bool',
            'subscription_upgrade_enabled' => 'bool',
            'quantity_include_product_ids' => 'array',
            'quantity_exclude_product_ids' => 'array',
            'quantity_include_collection_ids' => 'array',
            'quantity_exclude_collection_ids' => 'array',
            'quantity_include_tags' => 'array',
            'quantity_exclude_tags' => 'array',
            'quantity_include_vendors' => 'array',
            'quantity_exclude_vendors' => 'array',
            'quantity_include_product_types' => 'array',
            'quantity_exclude_product_types' => 'array',
            'subscription_include_product_ids' => 'array',
            'subscription_exclude_product_ids' => 'array',
            'subscription_include_collection_ids' => 'array',
            'subscription_exclude_collection_ids' => 'array',
            'subscription_include_tags' => 'array',
            'subscription_exclude_tags' => 'array',
            'subscription_include_vendors' => 'array',
            'subscription_exclude_vendors' => 'array',
            'subscription_include_product_types' => 'array',
            'subscription_exclude_product_types' => 'array',
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
     * Get cart line UI config for API (alignment, chevron, quantity size, popover width, +/- style).
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

        $widthMode = $this->cart_line_popover_width_mode ?? 'preset';
        if (! in_array($widthMode, ['preset', 'custom'], true)) {
            $widthMode = 'preset';
        }
        $widthPreset = $this->cart_line_popover_width_preset ?? 'md';
        if (! in_array($widthPreset, ['sm', 'md', 'lg', 'xl'], true)) {
            $widthPreset = 'md';
        }
        $widthPx = $widthMode === 'custom' ? max(200, min(600, (int) ($this->cart_line_popover_width_px ?? 320))) : null;

        $plusMinusKind = $this->cart_line_plus_minus_kind ?? 'plain';
        if (! in_array($plusMinusKind, ['plain', 'secondary', 'primary'], true)) {
            $plusMinusKind = 'plain';
        }
        $plusMinusAppearance = $this->cart_line_plus_minus_appearance ?? 'monochrome';
        if (! in_array($plusMinusAppearance, ['default', 'monochrome', 'critical'], true)) {
            $plusMinusAppearance = 'monochrome';
        }
        $plusMinusSize = $this->cart_line_plus_minus_size ?? 'small';
        if (! in_array($plusMinusSize, ['small', 'medium', 'large'], true)) {
            $plusMinusSize = 'small';
        }
        $plusMinusCornerRadius = $this->cart_line_plus_minus_corner_radius ?? 'base';
        if (! in_array($plusMinusCornerRadius, ['none', 'small', 'base', 'large', 'fullyRounded'], true)) {
            $plusMinusCornerRadius = 'base';
        }

        return [
            'modify_alignment' => $alignment,
            'show_chevron' => (bool) ($this->cart_line_show_chevron ?? true),
            'quantity_size' => $size,
            'popover_width' => [
                'mode' => $widthMode,
                'preset' => $widthPreset,
                'px' => $widthPx,
            ],
            'plus_minus' => [
                'kind' => $plusMinusKind,
                'appearance' => $plusMinusAppearance,
                'size' => $plusMinusSize,
                'corner_radius' => $plusMinusCornerRadius,
            ],
        ];
    }
}
